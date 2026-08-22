<?php
/**
 * MÁY (GHẾ): cấu hình, cơ sở, hàng chờ chạy, nhịp sống, lệnh bật/tắt tay.
 *
 * =============================================================================================
 * HAI THỨ RẤT DỄ LẪN, VÀ LẪN LÀ MẤT TIỀN
 * =============================================================================================
 *   `cho`  — KHÁCH ĐÃ TRẢ TIỀN, ghế chưa chạy. Sinh ra từ webhook. Ghế lấy về rồi chạy.
 *   `lenh` — NGƯỜI BẤM TAY trên màn để cho chạy (khách kêu máy không nhận, đền bù…). KHÔNG có
 *            tiền đi kèm.
 * Gộp hai thứ này vào một bảng là cuối tháng không tách được "chạy vì có tiền" với "chạy vì
 * được cho", tức không biết máy nào đang bị cho chạy chùa. Nên tách hẳn, và `lenh` bắt buộc ghi
 * người đặt.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHG_May {

	/** Quá bao lâu không có nhịp thì coi là máy đứt (giây). */
	const HET_SONG = 120;

	// ======================================================================= cơ sở

	public static function ds_coso() {
		return VHG_DB::rows( 'SELECT * FROM ' . VHG_DB::t( 'coso' ) . ' ORDER BY ten ASC' );
	}

	public static function luu_coso( $id, $ten ) {
		global $wpdb;
		$ten = trim( (string) $ten );
		if ( '' === $ten ) { return array( 'ok' => false, 'error' => 'Thiếu tên cơ sở.' ); }
		$bang = VHG_DB::t( 'coso' );
		if ( (int) $id > 0 ) {
			$wpdb->update( $bang, array( 'ten' => $ten ), array( 'id' => (int) $id ) );
			return array( 'ok' => true, 'id' => (int) $id, 'thong_bao' => 'Đã đổi tên cơ sở.' );
		}
		$co = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $bang WHERE ten=%s LIMIT 1", $ten ) );
		if ( $co ) { return array( 'ok' => true, 'id' => (int) $co, 'thong_bao' => 'Cơ sở này đã có.' ); }
		$wpdb->insert( $bang, array( 'ten' => $ten ) );
		return array( 'ok' => true, 'id' => (int) $wpdb->insert_id, 'thong_bao' => 'Đã thêm cơ sở ' . $ten . '.' );
	}

	/**
	 * Xoá cơ sở. Máy đang gán vào đó KHÔNG bị xoá theo — chỉ thành "chưa gán".
	 * ⚠️ Xoá máy theo là mất cấu hình giá/thời lượng/số tài khoản của những máy đang chạy thật,
	 *    chỉ vì người ta gõ nhầm tên một cơ sở rồi muốn xoá đi làm lại.
	 */
	public static function xoa_coso( $id ) {
		global $wpdb;
		$id = (int) $id;
		$so = (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . VHG_DB::t( 'may' ) . ' WHERE coso_id=%d', $id ) );
		$wpdb->update( VHG_DB::t( 'may' ), array( 'coso_id' => 0 ), array( 'coso_id' => $id ) );
		$wpdb->delete( VHG_DB::t( 'coso' ), array( 'id' => $id ) );
		return array( 'ok' => true, 'thong_bao' => 'Đã xoá cơ sở.'
			. ( $so > 0 ? ' ' . $so . ' máy chuyển thành "chưa gán", KHÔNG bị xoá.' : '' ) );
	}

	// ======================================================================= máy

	public static function ds_may() {
		$may  = VHG_DB::t( 'may' );
		$coso = VHG_DB::t( 'coso' );
		$nhip = VHG_DB::t( 'nhip' );
		$ds = VHG_DB::rows(
			"SELECT m.*, c.ten AS coso_ten, n.trang_thai, n.nguon AS nhip_nguon, n.con_lai,"
			. " n.fw, n.ip, n.luc AS nhip_luc FROM $may m"
			. " LEFT JOIN $coso c ON c.id = m.coso_id"
			. " LEFT JOIN $nhip n ON n.ma_may = m.ma"
			. ' ORDER BY c.ten ASC, m.ma ASC' );
		$gio = current_time( 'timestamp' );
		foreach ( $ds as $i => $x ) {
			$ds[ $i ]['coso_ten'] = (string) $x['coso_ten'];
			$ds[ $i ]['con_song'] = self::con_song( isset( $x['nhip_luc'] ) ? $x['nhip_luc'] : '', $gio );
			$ds[ $i ]['cho']      = self::so_cho( $x['ma'] );
		}
		return $ds;
	}

	/** Bản đồ mã máy -> thông tin, để tra nhanh khi tổng hợp doanh thu. */
	public static function ds_may_theo_ma() {
		$ra = array();
		foreach ( self::ds_may() as $m ) {
			$ra[ $m['ma'] ] = $m;
			/* Tra được cả bằng TÊN KHAI: giao dịch của Tingo mang tên "AMTP 03" chứ không mang
			   mã ghế, nên không có khoá này thì cơ sở của chúng luôn là "chưa gán". */
			if ( '' !== (string) $m['ten_khai'] ) {
				$ra[ VHG_Doc::chuan_ten( $m['ten_khai'] ) ] = $m;
			}
		}
		return $ra;
	}

	public static function con_song( $luc, $bay_gio = null ) {
		if ( '' === trim( (string) $luc ) ) { return false; }
		$t = strtotime( $luc );
		if ( ! $t ) { return false; }
		if ( null === $bay_gio ) { $bay_gio = current_time( 'timestamp' ); }
		return ( $bay_gio - $t ) <= self::HET_SONG;
	}

	/**
	 * MỆNH GIÁ khách bấm trên màn ghế. Khai ở WEB, không nạp cứng vào firmware.
	 *
	 * 🔴 Anh Thắng ngày 22/08/2026: *"có nhiều mệnh giá quét trên máy qr để chọn mà"*. Đúng —
	 *    ghế có bốn nút. Nhưng bản đầu bốn con số đó nằm CỨNG trong firmware, nên đổi giá là
	 *    phải mang USB đi 26 cửa hàng. Ghế lại không có OTA, nên đó là chuyến đi thật.
	 *
	 * ⚠️ `gia`/`phut` KHÔNG phải một mệnh giá — chúng là TỈ LỆ QUY ĐỔI. Ghế tính
	 *    `phút = tiền × phut / gia`, nên 10.000đ=6′ thì bấm 50.000đ ra 30 phút. Nhãn cũ ghi
	 *    "Giá một lượt" là sai, và làm người đọc tưởng ghế chỉ có một mệnh giá.
	 */
	const MENH_GIA_MAC_DINH = array( 20000, 50000, 100000, 200000 );

	public static function menh_gia() {
		$ds = get_option( 'vhg_menh_gia' );
		if ( ! is_array( $ds ) ) { return self::MENH_GIA_MAC_DINH; }
		$ra = array();
		foreach ( $ds as $v ) {
			$v = (int) $v;
			if ( $v >= 1000 && ! in_array( $v, $ra, true ) ) { $ra[] = $v; }
		}
		sort( $ra );
		/* Rỗng thì về mặc định, KHÔNG để rỗng: ghế không còn nút nào để bấm, tức là đường QR
		   chết hẳn ở 26 cửa hàng mà máy chủ vẫn báo mọi thứ bình thường. */
		if ( ! $ra ) { return self::MENH_GIA_MAC_DINH; }
		/* Màn ghế chỉ có BỐN ô. Nhiều hơn là những ô sau không vẽ ra được — cắt ở đây để cái
		   người ta thấy trên web đúng bằng cái ghế hiện. */
		return array_slice( $ra, 0, 4 );
	}

	public static function luu_menh_gia( $ds ) {
		$ra = array();
		foreach ( (array) $ds as $v ) {
			$v = (int) preg_replace( '/\D+/', '', (string) $v );
			if ( $v >= 1000 ) { $ra[] = $v; }
		}
		$ra = array_values( array_unique( $ra ) );
		sort( $ra );
		if ( ! $ra ) { return array( 'ok' => false, 'error' => 'Phải có ít nhất một mệnh giá từ 1.000đ.' ); }
		if ( count( $ra ) > 4 ) { return array( 'ok' => false, 'error' => 'Màn ghế chỉ có 4 ô — khai tối đa 4 mệnh giá.' ); }
		update_option( 'vhg_menh_gia', $ra );
		return array( 'ok' => true, 'thong_bao' => 'Đã lưu ' . count( $ra ) . ' mệnh giá. '
			. 'Ghế lấy về ở lượt nhịp kế tiếp (~30 giây).' );
	}

	/**
	 * TÀI KHOẢN NHẬN TIỀN — khai MỘT LẦN cho cả hệ thống, từng ghế chỉ khai khi cần khác.
	 *
	 * 🔴 Anh Thắng: *"liên kết qua sepay và vietqr mà liên quan gì đến số tk"*. Số tài khoản vẫn
	 *    cần — mã QR phải nói tiền đi về đâu, SePay chỉ BÁO TIN tiền đã về. Nhưng bắt khai lại
	 *    cho từng ghế là thiết kế sai: 26 ghế là 26 lần gõ lại cùng một con số, và đổi tài khoản
	 *    là phải sửa đúng 26 chỗ — sót một chỗ thì tiền của ghế đó chảy về tài khoản cũ, âm thầm.
	 *
	 * Ô của từng ghế giữ lại làm NGOẠI LỆ (ghế đặt ở điểm có tài khoản riêng). Rỗng = dùng chung.
	 */
	public static function nhan_tien_chung() {
		return array(
			'bin'    => trim( (string) get_option( 'vhg_bin', '' ) ),
			'so_tk'  => trim( (string) get_option( 'vhg_so_tk', '' ) ),
			'ten_tk' => trim( (string) get_option( 'vhg_ten_tk', '' ) ),
		);
	}

	/** Tài khoản THỰC DÙNG của một ghế: ô riêng nếu có, không thì ô chung. */
	public static function nhan_tien_cua( $m ) {
		$c = self::nhan_tien_chung();
		$m = (array) $m;
		/* Bản đồ ô-của-ghế -> ô-chung. Tên cột khác tên khoá (`bank_bin` vs `bin`), nên viết ra
		   một chỗ chứ đừng lặp ba lần — lặp là chỗ thứ ba gõ nhầm và ghế đó âm thầm dùng tài
		   khoản rỗng. */
		$ra = array();
		foreach ( array( 'bin' => 'bank_bin', 'so_tk' => 'so_tk', 'ten_tk' => 'ten_tk' ) as $khoa => $cot ) {
			$rieng   = isset( $m[ $cot ] ) ? trim( (string) $m[ $cot ] ) : '';
			$ra[ $khoa ] = '' !== $rieng ? $rieng : $c[ $khoa ];
		}
		return $ra;
	}

	public static function luu_nhan_tien( $bin, $so_tk, $ten_tk ) {
		$bin   = preg_replace( '/\D+/', '', (string) $bin );
		$so_tk = trim( (string) $so_tk );
		if ( '' !== $bin && ! preg_match( '/^\d{6}$/', $bin ) ) {
			return array( 'ok' => false, 'error' => 'Mã ngân hàng (BIN) phải đúng 6 chữ số. VD 970418 = BIDV.' );
		}
		update_option( 'vhg_bin', $bin );
		update_option( 'vhg_so_tk', $so_tk );
		update_option( 'vhg_ten_tk', trim( (string) $ten_tk ) );
		return array( 'ok' => true, 'thong_bao' => 'Đã lưu tài khoản nhận tiền chung. '
			. 'Ghế lấy về ở lượt nhịp kế tiếp (~30 giây), không phải nạp lại firmware.' );
	}

	/**
	 * GÁN MÃ THẬT cho một ghế đã tự hiện ra (mã còn bắt đầu bằng '?').
	 *
	 * 🔴 Đổi mã KHÔNG chỉ là đổi một ô. `ma` là khoá mà doanh thu, hàng chờ, nhịp và lệnh đều
	 *    trỏ tới. Đổi mỗi bảng `may` thì lịch sử của ghế đó mồ côi: lượt khách ĐÃ TRẢ TIỀN mà
	 *    ghế chưa nhận sẽ nằm lại dưới mã cũ, ghế hỏi bằng mã mới nên không bao giờ thấy —
	 *    khách trả tiền xong ghế không chạy, và không có gì trên màn hình nói vì sao.
	 *    Nên dời TẤT CẢ trong cùng một lượt.
	 */
	public static function gan_ma( $ma_cu, $ma_moi, $coso_id = null ) {
		global $wpdb;
		$ma_cu  = trim( (string) $ma_cu );
		$ma_moi = trim( (string) $ma_moi );
		if ( '' === $ma_cu || '' === $ma_moi ) { return array( 'ok' => false, 'error' => 'Thiếu mã.' ); }
		if ( ! preg_match( '/^[A-Za-z0-9]{1,20}$/', $ma_moi ) ) {
			return array( 'ok' => false, 'error' => 'Mã mới chỉ được gồm chữ và số, không dấu, không '
				. 'khoảng trắng. Mã này đi vào nội dung chuyển khoản khách gõ tay.' );
		}
		if ( $ma_cu === $ma_moi ) { return array( 'ok' => true, 'thong_bao' => 'Mã không đổi.' ); }
		$bang = VHG_DB::t( 'may' );
		if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $bang WHERE ma=%s LIMIT 1", $ma_cu ) ) ) {
			return array( 'ok' => false, 'error' => 'Không thấy ghế ' . $ma_cu . '.' );
		}
		if ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $bang WHERE ma=%s LIMIT 1", $ma_moi ) ) ) {
			return array( 'ok' => false, 'error' => 'Mã ' . $ma_moi . ' đã có ghế khác dùng. '
				. 'Hai ghế cùng mã là tiền của ghế này chạy ghế kia.' );
		}
		$dat = array( 'ma' => $ma_moi, 'cap_nhat' => current_time( 'mysql' ) );
		/* Gán cơ sở CÙNG LÚC với gán mã. Người đi lắp ghế biết nó đang ở đâu ngay lúc đó; bắt
		   quay lại một màn khác để chọn cơ sở là bước dễ quên nhất, mà quên thì doanh thu ghế
		   đó rơi vào ô "(chưa gán)" và bảng theo cơ sở sai âm thầm. */
		if ( null !== $coso_id ) { $dat['coso_id'] = (int) $coso_id; }
		$wpdb->update( $bang, $dat, array( 'ma' => $ma_cu ) );
		/* Dời hết những gì trỏ tới mã cũ. `thu` để CUỐI: nó là sổ tiền, và nếu có gì hỏng giữa
		   chừng thì thà sổ tiền còn nguyên mã cũ (đối soát tay được) hơn là hàng chờ mồ côi. */
		$dem = 0;
		foreach ( array( 'cho', 'nhip', 'lenh', 'thu' ) as $b ) {
			$dem += (int) $wpdb->query( $wpdb->prepare(
				'UPDATE ' . VHG_DB::t( $b ) . ' SET ma_may=%s WHERE ma_may=%s', $ma_moi, $ma_cu ) );
		}
		return array( 'ok' => true, 'thong_bao' => 'Đã gán mã ' . $ma_moi . ' cho ghế ' . $ma_cu
			. ' và dời ' . $dem . ' dòng lịch sử sang mã mới.' );
	}

	public static function luu_may( $d ) {
		global $wpdb;
		$ma = trim( (string) ( isset( $d['ma'] ) ? $d['ma'] : '' ) );
		if ( '' === $ma ) { return array( 'ok' => false, 'error' => 'Thiếu mã máy.' ); }
		/* Mã máy đi vào nội dung chuyển khoản mà khách gõ tay ("GHE3 T1ABC"). Dấu và khoảng
		   trắng ở đó là khách gõ sai, gõ sai là tiền vào mà ghế không chạy. Chặn ngay lúc khai. */
		if ( ! preg_match( '/^[A-Za-z0-9]{1,20}$/', $ma ) ) {
			return array( 'ok' => false, 'error' => 'Mã máy chỉ được gồm chữ và số, không dấu, không '
				. 'khoảng trắng (tối đa 20 ký tự). Mã này đi vào nội dung chuyển khoản khách gõ tay — '
				. 'có dấu là khách gõ sai và ghế không chạy.' );
		}
		$hang = array(
			'ma'       => $ma,
			'mac'      => strtoupper( trim( (string) ( isset( $d['mac'] ) ? $d['mac'] : '' ) ) ),
			'coso_id'  => (int) ( isset( $d['coso_id'] ) ? $d['coso_id'] : 0 ),
			'gia'      => max( 0, (int) ( isset( $d['gia'] ) ? $d['gia'] : 10000 ) ),
			'phut'     => max( 1, (int) ( isset( $d['phut'] ) ? $d['phut'] : 6 ) ),
			'so_tk'    => trim( (string) ( isset( $d['so_tk'] ) ? $d['so_tk'] : '' ) ),
			'ten_tk'   => trim( (string) ( isset( $d['ten_tk'] ) ? $d['ten_tk'] : '' ) ),
			'bank_bin' => trim( (string) ( isset( $d['bank_bin'] ) ? $d['bank_bin'] : '' ) ),
			'ten_khai' => VHG_Doc::chuan_ten( isset( $d['ten_khai'] ) ? $d['ten_khai'] : '' ),
			'cap_nhat' => current_time( 'mysql' ),
		);
		$bang = VHG_DB::t( 'may' );
		/* MAC rỗng thì đừng ghi đè MAC đang có — người sửa giá không nên vô tình cắt đứt liên
		   kết giữa ghế thật và dòng cấu hình của nó. */
		if ( '' === $hang['mac'] ) { unset( $hang['mac'] ); }
		$co = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $bang WHERE ma=%s LIMIT 1", $ma ) );
		/* Gán mã thật cho một ghế đang chờ: tìm theo MAC rồi ĐỔI mã của chính dòng đó, chứ không
		   tạo dòng mới — tạo mới là ghế cũ nằm lại mãi trong danh sách chờ gán. */
		if ( ! $co && isset( $hang['mac'] ) ) {
			$cu_mac = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $bang WHERE mac=%s LIMIT 1", $hang['mac'] ) );
			if ( $cu_mac ) { $co = $cu_mac; }
		}
		if ( $co ) { $wpdb->update( $bang, $hang, array( 'id' => (int) $co ) ); }
		else { $wpdb->insert( $bang, $hang ); }
		return array( 'ok' => true, 'thong_bao' => 'Đã lưu máy ' . $ma . '.' );
	}

	public static function xoa_may( $ma ) {
		global $wpdb;
		$wpdb->delete( VHG_DB::t( 'may' ), array( 'ma' => (string) $ma ) );
		/* Doanh thu của máy đó KHÔNG xoá theo — tiền đã thu là chuyện đã xảy ra, xoá cấu hình
		   máy không làm nó chưa xảy ra. */
		return array( 'ok' => true, 'thong_bao' => 'Đã xoá cấu hình máy ' . $ma
			. '. Doanh thu đã ghi của máy này giữ nguyên.' );
	}

	/**
	 * Ghế nào mang MAC này. Trả mảng dòng `may`, hoặc null.
	 *
	 * ⚠️ KHÔNG tự gán mã ghế cho MAC lạ. Ghế chưa khai thì nó hiện ra trong danh sách "chờ gán"
	 *    để người ta gán tay. Đoán bừa là hai ghế cùng nhận một mã, và tiền của ghế này chạy ghế
	 *    kia — mà nhìn từ máy chủ thì không có gì bất thường cả.
	 */
	public static function theo_mac( $mac ) {
		global $wpdb;
		$mac = strtoupper( trim( (string) $mac ) );
		if ( '' === $mac ) { return null; }
		return $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . VHG_DB::t( 'may' ) . ' WHERE mac=%s LIMIT 1', $mac ), ARRAY_A );
	}

	/**
	 * Ghi nhận một ghế đang gọi tới. Trả mã ghế ('' nếu chưa được gán).
	 * MAC lạ -> tạo một dòng CHỜ GÁN (mã rỗng) để nó hiện lên web, chứ không im lặng bỏ qua:
	 * ghế cắm điện mà không hiện ở đâu cả thì người đi lắp không biết mình sai chỗ nào.
	 */
	public static function ghi_nhan( $mac ) {
		global $wpdb;
		$mac = strtoupper( trim( (string) $mac ) );
		if ( '' === $mac ) { return ''; }
		$m = self::theo_mac( $mac );
		if ( $m ) { return (string) $m['ma']; }
		$bang = VHG_DB::t( 'may' );
		/* Mã tạm mang chính MAC: cột `ma` là UNIQUE nên không để rỗng được cho nhiều ghế. Dấu
		   hiệu "chưa gán" là mã bắt đầu bằng `?` — người ta thấy ngay trên màn. */
		$tam = '?' . substr( preg_replace( '/[^0-9A-F]/', '', $mac ), -6 );
		if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $bang WHERE ma=%s LIMIT 1", $tam ) ) ) {
			$wpdb->insert( $bang, array( 'ma' => $tam, 'mac' => $mac, 'gia' => 10000, 'phut' => 6,
				'ghi_chu' => 'ghế tự hiện ra khi cắm điện — chưa gán mã', 'cap_nhat' => current_time( 'mysql' ) ) );
		}
		return $tam;
	}

	/** Ghế chưa được gán mã thật (mã còn bắt đầu bằng '?'). */
	public static function chua_gan() {
		$ds = VHG_DB::rows( 'SELECT * FROM ' . VHG_DB::t( 'may' ) . " WHERE ma LIKE '?%' ORDER BY id ASC" );
		/* Kèm luôn nhịp cuối: màn hình cần biết ghế này còn sống không TRƯỚC khi người ta gán mã
		   cho nó. Gán mã cho một ghế đã tháo đi là để lại một dòng cấu hình không bao giờ dùng.
		   Truy vấn để ở ĐÂY chứ không ở màn hình — màn hình không được tự viết SQL, và luật đó
		   có phép thử canh. */
		foreach ( $ds as $i => $x ) {
			$n = self::nhip_cua( $x['ma'] );
			$ds[ $i ]['nhip_luc'] = $n['luc'];
			$ds[ $i ]['fw']       = $n['fw'];
			$ds[ $i ]['ip']       = $n['ip'];
			$ds[ $i ]['con_song'] = self::con_song( $n['luc'] );
		}
		return $ds;
	}

	/** Nhịp cuối của một ghế. Luôn trả đủ ba khoá, kể cả khi ghế chưa gửi nhịp lần nào. */
	public static function nhip_cua( $ma_may ) {
		global $wpdb;
		$r = $wpdb->get_row( $wpdb->prepare(
			'SELECT luc, fw, ip FROM ' . VHG_DB::t( 'nhip' ) . ' WHERE ma_may=%s LIMIT 1',
			(string) $ma_may ), ARRAY_A );
		return array(
			'luc' => $r ? (string) $r['luc'] : '',
			'fw'  => $r ? (string) $r['fw'] : '',
			'ip'  => $r ? (string) $r['ip'] : '',
		);
	}

	public static function may( $ma ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . VHG_DB::t( 'may' ) . ' WHERE ma=%s LIMIT 1', (string) $ma ), ARRAY_A );
	}

	// ======================================================================= hàng chờ chạy

	/** Tiền đã vào -> xếp cho ghế chạy. Cùng (máy, mã) tới hai lần thì chỉ một hàng. */
	public static function xep_cho_chay( $ma_may, $ma_lenh, $so_tien, $ref, $noi_dung ) {
		global $wpdb;
		$bang = VHG_DB::t( 'cho' );
		$co = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM $bang WHERE ma_may=%s AND ma_lenh=%s LIMIT 1", $ma_may, $ma_lenh ) );
		if ( $co ) { return (int) $co; }
		$wpdb->insert( $bang, array(
			'ma_may' => (string) $ma_may, 'ma_lenh' => (string) $ma_lenh,
			'so_tien' => (int) $so_tien, 'ref' => (string) $ref,
			'noi_dung' => mb_substr( (string) $noi_dung, 0, 250 ),
			'tao_luc' => current_time( 'mysql' ), 'nhan_luc' => null ) );
		return (int) $wpdb->insert_id;
	}

	public static function so_cho( $ma_may ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . VHG_DB::t( 'cho' ) . ' WHERE ma_may=%s AND nhan_luc IS NULL', $ma_may ) );
	}

	public static function ds_cho( $chi_chua_nhan = true, $gioi_han = 200 ) {
		$sql = 'SELECT * FROM ' . VHG_DB::t( 'cho' )
			. ( $chi_chua_nhan ? ' WHERE nhan_luc IS NULL' : '' )
			. ' ORDER BY id DESC LIMIT ' . (int) $gioi_han;
		return VHG_DB::rows( $sql );
	}

	/**
	 * Ghế hỏi "có ai trả tiền cho tôi chưa". Trả lượt CŨ NHẤT chưa nhận và đánh dấu đã nhận.
	 *
	 * ⚠️ ĐÁNH DẤU NGAY, KHÔNG chờ ghế báo chạy xong. Ghế mất điện giữa chừng thì khách mất lượt —
	 *    nhưng KHÔNG đánh dấu thì ghế khởi động lại là chạy lại lượt cũ, và cứ thế mãi. Giữa "mất
	 *    một lượt hiếm khi" và "một lượt chạy vô hạn", chọn cái thứ nhất; cái thứ hai còn làm
	 *    hỏng cả bảng đối soát. Người ta bù tay bằng lệnh bật.
	 */
	public static function lay_luot( $ma_may ) {
		global $wpdb;
		$bang = VHG_DB::t( 'cho' );
		$r = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $bang WHERE ma_may=%s AND nhan_luc IS NULL ORDER BY id ASC LIMIT 1",
			(string) $ma_may ), ARRAY_A );
		if ( ! $r ) { return null; }
		$wpdb->update( $bang, array( 'nhan_luc' => current_time( 'mysql' ) ), array( 'id' => (int) $r['id'] ) );
		return $r;
	}

	// ======================================================================= nhịp sống

	public static function nhip( $ma_may, $d ) {
		global $wpdb;
		$ma_may = trim( (string) $ma_may );
		if ( '' === $ma_may ) { return false; }
		$bang = VHG_DB::t( 'nhip' );
		$hang = array(
			'ma_may'     => $ma_may,
			'trang_thai' => mb_substr( (string) ( isset( $d['trang_thai'] ) ? $d['trang_thai'] : 'idle' ), 0, 20 ),
			'nguon'      => mb_substr( (string) ( isset( $d['nguon'] ) ? $d['nguon'] : '' ), 0, 20 ),
			'con_lai'    => (int) ( isset( $d['con_lai'] ) ? $d['con_lai'] : 0 ),
			'ip'         => mb_substr( (string) ( isset( $d['ip'] ) ? $d['ip'] : '' ), 0, 60 ),
			'fw'         => mb_substr( (string) ( isset( $d['fw'] ) ? $d['fw'] : '' ), 0, 40 ),
			'luc'        => current_time( 'mysql' ),
		);
		$co = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $bang WHERE ma_may=%s LIMIT 1", $ma_may ) );
		if ( $co ) { $wpdb->update( $bang, $hang, array( 'id' => (int) $co ) ); }
		else { $wpdb->insert( $bang, $hang ); }
		return true;
	}

	// ======================================================================= lệnh bật/tắt tay

	/**
	 * Đặt lệnh cho ghế. `$viec` = 'on' (chạy $phut phút) hoặc 'off' (tắt ngay).
	 * ⚠️ BẮT BUỘC ghi người đặt — đây là đường cho không một lượt massage, xem khối ⚠️ đầu tệp.
	 */
	public static function dat_lenh( $ma_may, $viec, $phut, $nguoi, $ly_do = '' ) {
		global $wpdb;
		$ma_may = trim( (string) $ma_may );
		if ( '' === $ma_may ) { return array( 'ok' => false, 'error' => 'Thiếu mã máy.' ); }
		if ( 'on' !== $viec && 'off' !== $viec ) {
			return array( 'ok' => false, 'error' => 'Lệnh chỉ có thể là bật (on) hoặc tắt (off).' );
		}
		$nguoi = trim( (string) $nguoi );
		if ( '' === $nguoi ) { return array( 'ok' => false, 'error' => 'Thiếu tên người đặt lệnh.' ); }
		$phut = (int) $phut;
		if ( 'on' === $viec ) {
			$m = self::may( $ma_may );
			if ( $phut <= 0 ) { $phut = $m ? (int) $m['phut'] : 6; }
			/* Chặn trần: gõ nhầm 600 thay vì 6 là ghế chạy 10 tiếng và không ai ở đó để tắt. */
			if ( $phut > 60 ) {
				return array( 'ok' => false, 'error' => 'Tối đa 60 phút một lệnh. Gõ nhầm số 0 là '
					. 'ghế chạy suốt đêm mà không ai ở đó để tắt.' );
			}
		}
		$wpdb->insert( VHG_DB::t( 'lenh' ), array(
			'ma_may' => $ma_may, 'viec' => $viec, 'phut' => $phut,
			'nguoi' => mb_substr( $nguoi, 0, 190 ), 'ly_do' => mb_substr( (string) $ly_do, 0, 250 ),
			'tao_luc' => current_time( 'mysql' ), 'gui_luc' => null ) );
		return array( 'ok' => true, 'thong_bao' => 'on' === $viec
			? 'Đã đặt lệnh cho máy ' . $ma_may . ' chạy ' . $phut . ' phút. Máy nhận trong ~10 giây.'
			: 'Đã đặt lệnh TẮT máy ' . $ma_may . '.' );
	}

	/** Ghế lấy lệnh cũ nhất chưa gửi. Lấy xong đánh dấu đã gửi. */
	public static function lay_lenh( $ma_may ) {
		global $wpdb;
		$bang = VHG_DB::t( 'lenh' );
		$r = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $bang WHERE ma_may=%s AND gui_luc IS NULL ORDER BY id ASC LIMIT 1",
			(string) $ma_may ), ARRAY_A );
		if ( ! $r ) { return null; }
		$wpdb->update( $bang, array( 'gui_luc' => current_time( 'mysql' ) ), array( 'id' => (int) $r['id'] ) );
		return $r;
	}

	public static function ds_lenh( $gioi_han = 100 ) {
		return VHG_DB::rows( 'SELECT * FROM ' . VHG_DB::t( 'lenh' )
			. ' ORDER BY id DESC LIMIT ' . (int) $gioi_han );
	}
}
