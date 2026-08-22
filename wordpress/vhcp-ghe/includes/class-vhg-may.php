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
			. " n.fw, n.ip, n.nd_tien_to, n.tre_ms, n.luc AS nhip_luc FROM $may m"
			. " LEFT JOIN $coso c ON c.id = m.coso_id"
			. " LEFT JOIN $nhip n ON n.ma_may = m.ma"
			. ' ORDER BY c.ten ASC, m.ma ASC' );
		$gio = current_time( 'timestamp' );
		foreach ( $ds as $i => $x ) {
			$ds[ $i ]['coso_ten'] = (string) $x['coso_ten'];
			$tt = self::tinh_trang_nhip( isset( $x['nhip_luc'] ) ? $x['nhip_luc'] : '', $gio );
			/* ==================================================================================
			 * SỐ GIÂY CÒN LẠI PHẢI TRỪ ĐI TUỔI CỦA CHÍNH CON SỐ ĐÓ.
			 *
			 * 🔴 Anh Thắng 22/08/2026: *"bấm thử điều khiển ghế thì lệch 12s — thời gian máy QR
			 *    nhanh hơn 11s"*. Không phải cố ý chừa thời gian cho khách lên ghế; đó là tuổi
			 *    của dữ liệu.
			 *
			 *    Ghế gửi nhịp 30 giây một lần. Nó nói "còn 300 giây" lúc 21:46:00. Web hỏi lúc
			 *    21:46:11 và nhận đúng con số 300 đó — nhưng ghế đã chạy thêm 11 giây rồi. Web
			 *    tự trừ mỗi giây từ 300, nên nó chậm hơn ghế đúng 11 giây, mãi mãi, cho tới lượt
			 *    nhịp sau. Trung bình lệch nửa chu kỳ nhịp = 15 giây.
			 *
			 * Máy chủ biết nhịp đó tới lúc nào, nên trừ được. Sửa ở ĐÂY chứ không bắt ghế gửi
			 * nhịp dày hơn: ghế chạy 4G, mỗi lượt nhịp là tiền, và 26 ghế × mỗi 5 giây là một
			 * khoản không nhỏ cho một con số chỉ để nhìn.
			 *
			 * ⚠️ Chỉ trừ khi ghế ĐANG CHẠY. Ghế rảnh thì `con_lai` vốn là 0; ghế mất kết nối thì
			 *    con số nào cũng vô nghĩa — trừ tiếp chỉ tạo ra một đồng hồ chạy lùi trông như
			 *    thật, mà thật ra không ai biết ghế còn chạy hay không.
			 * ================================================================================== */
			$con = (int) ( isset( $x['con_lai'] ) ? $x['con_lai'] : 0 );
			if ( $tt['song'] && 'running' === (string) $x['trang_thai'] && null !== $tt['giay'] ) {
				/* Trừ HAI phần, hai nguồn khác nhau:
				   · tuổi của nhịp: từ lúc máy chủ nhận tới bây giờ — máy chủ tự biết.
				   · nửa quãng đi: ghế tính con số TRƯỚC khi gọi, dấu giờ đóng LÚC NHẬN. Máy chủ
				     không thấy quãng này, nên ghế phải tự khai (`tre_ms` = lượt trước mất bao lâu).
				     Nửa chứ không trọn: `tre_ms` là cả đi lẫn về, chỉ chiều đi mới nằm giữa hai mốc. */
				$con = max( 0, $con - (int) $tt['giay']
					- (int) round( (int) ( isset( $x['tre_ms'] ) ? $x['tre_ms'] : 0 ) / 2000 ) );
			}
			$ds[ $i ]['con_lai']      = $con;
			$ds[ $i ]['nhip_giay']    = $tt['giay'];
			$ds[ $i ]['con_song']     = $tt['song'];
			$ds[ $i ]['chua_bao_gio'] = $tt['chua_bao_gio'];
			$ds[ $i ]['nhip_chu']     = $tt['chu'];
			$ds[ $i ]['cho']          = self::so_cho( $x['ma'] );
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

	/**
	 * Ghế này đang thế nào, NÓI BẰNG CÂU NGƯỜI ĐỌC ĐƯỢC.
	 *
	 * 🔴 Anh Thắng 22/08/2026: *"đã add, nhưng máy chưa nhận"*. Màn hình lúc đó chỉ nói "Mất kết
	 *    nối" — đúng, nhưng vô dụng, vì nó gộp BA ca đi sửa ở ba nơi khác hẳn:
	 *      · CHƯA BAO GIỜ gửi nhịp  -> ghế chưa nạp firmware mới, hoặc nạp rồi mà sai địa chỉ
	 *                                  web / sai khoá. Đi kiểm cổng USB và secrets.h.
	 *      · Gửi rồi, vừa mới im    -> mạng chập. Đợi một lượt nhịp (~30 giây) rồi xem lại.
	 *      · Gửi rồi, im đã lâu     -> ghế mất điện, rớt 4G, hoặc treo. Đi tới nơi.
	 *    Ba câu đó khác nhau ở chỗ NGƯỜI ĐỌC PHẢI LÀM GÌ TIẾP. Gộp làm một là bắt người ta đoán.
	 *
	 * @return array [ 'song' => bool, 'chua_bao_gio' => bool, 'giay' => int|null, 'chu' => string ]
	 */
	public static function tinh_trang_nhip( $luc, $bay_gio = null ) {
		$luc = trim( (string) $luc );
		if ( null === $bay_gio ) { $bay_gio = current_time( 'timestamp' ); }
		if ( '' === $luc ) {
			return array( 'song' => false, 'chua_bao_gio' => true, 'giay' => null,
				'chu' => 'chưa bao giờ gửi nhịp' );
		}
		$t = strtotime( $luc );
		if ( ! $t ) {
			return array( 'song' => false, 'chua_bao_gio' => true, 'giay' => null,
				'chu' => 'chưa bao giờ gửi nhịp' );
		}
		$giay = max( 0, (int) ( $bay_gio - $t ) );
		if ( $giay <= self::HET_SONG ) {
			return array( 'song' => true, 'chua_bao_gio' => false, 'giay' => $giay,
				'chu' => 'đang sống · ' . self::truoc_day( $giay ) );
		}
		return array( 'song' => false, 'chua_bao_gio' => false, 'giay' => $giay,
			'chu' => 'im từ ' . self::truoc_day( $giay ) );
	}

	/** "12 giây trước" / "5 phút trước" / "3 giờ trước" / "2 ngày trước". */
	public static function truoc_day( $giay ) {
		$giay = max( 0, (int) $giay );
		if ( $giay < 60 )    { return $giay . ' giây trước'; }
		if ( $giay < 3600 )  { return (int) floor( $giay / 60 ) . ' phút trước'; }
		if ( $giay < 86400 ) { return (int) floor( $giay / 3600 ) . ' giờ trước'; }
		return (int) floor( $giay / 86400 ) . ' ngày trước';
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
	const SO_O_MAN_GHE = 4;   // màn ghế 320×240 vẽ được đúng bốn ô

	/* Bảng giá anh Thắng dựng (ảnh 22/08/2026). Đúng tỉ lệ 50.000đ = 15 phút cho cả bốn gói. */
	const MENH_GIA_MAC_DINH = array(
		array( 'tien' => 50000,  'ten' => 'Gói cơ bản',      'phut' => 0, 'mo_ta' => 'Khởi động & thư giãn nhẹ', 'vip' => 0 ),
		array( 'tien' => 100000, 'ten' => 'Gói phổ biến',    'phut' => 0, 'mo_ta' => 'Sâu & phục hồi',           'vip' => 0 ),
		array( 'tien' => 150000, 'ten' => 'Gói chuyên sâu',  'phut' => 0, 'mo_ta' => 'Trị liệu & giảm đau',      'vip' => 0 ),
		array( 'tien' => 200000, 'ten' => 'Gói thượng hạng', 'phut' => 0, 'mo_ta' => 'Đẳng cấp & quà tặng',      'vip' => 1 ),
	);

	/**
	 * Bốn gói trên màn ghế: [ ['tien','ten','phut'], … ]
	 *
	 * `phut = 0` nghĩa là TÍNH THEO TỈ LỆ QUY ĐỔI của ghế. Để 0 là cách đúng trong hầu hết
	 * trường hợp: đổi tỉ lệ một lần thì cả bốn gói theo, không phải sửa bốn ô rồi lệch một ô.
	 * Chỉ khai số phút cụ thể khi gói đó CỐ Ý không theo tỉ lệ (gói khuyến mãi, gói kèm quà).
	 */
	public static function menh_gia() {
		$ds = get_option( 'vhg_menh_gia' );
		if ( ! is_array( $ds ) ) { return self::MENH_GIA_MAC_DINH; }
		$ra   = array();
		$thay = array();
		foreach ( $ds as $v ) {
			/* Bản 1.3.0 lưu một mảng SỐ trơn. Đọc được cả hai dạng — nếu không thì nâng cấp
			   plugin là bốn gói đang chạy biến mất và ghế về bộ mặc định, âm thầm. */
			$hang = is_array( $v ) ? $v : array( 'tien' => $v, 'ten' => '', 'phut' => 0 );
			$tien = (int) ( isset( $hang['tien'] ) ? $hang['tien'] : 0 );
			if ( $tien < 1000 || isset( $thay[ $tien ] ) ) { continue; }
			$thay[ $tien ] = 1;
			$ra[] = array(
				'tien'  => $tien,
				'ten'   => trim( (string) ( isset( $hang['ten'] ) ? $hang['ten'] : '' ) ),
				'phut'  => max( 0, (int) ( isset( $hang['phut'] ) ? $hang['phut'] : 0 ) ),
				'mo_ta' => trim( (string) ( isset( $hang['mo_ta'] ) ? $hang['mo_ta'] : '' ) ),
				'vip'   => empty( $hang['vip'] ) ? 0 : 1,
			);
		}
		usort( $ra, function ( $a, $b ) { return $a['tien'] - $b['tien']; } );
		/* Rỗng thì về mặc định, KHÔNG để rỗng: ghế không còn nút nào để bấm, tức là đường QR
		   chết hẳn ở 26 cửa hàng mà máy chủ vẫn báo mọi thứ bình thường. */
		if ( ! $ra ) { return self::MENH_GIA_MAC_DINH; }
		/* Màn ghế chỉ có BỐN ô. Nhiều hơn là những ô sau không vẽ ra được — cắt ở đây để cái
		   người ta thấy trên web đúng bằng cái ghế hiện. */
		return array_slice( $ra, 0, self::SO_O_MAN_GHE );
	}

	/** Số phút thực của một gói với một ghế: khai cứng nếu có, không thì theo tỉ lệ quy đổi. */
	public static function phut_goi( $goi, $gia_quy_doi, $phut_quy_doi ) {
		$goi = (array) $goi;
		if ( ! empty( $goi['phut'] ) ) { return (int) $goi['phut']; }
		$gia = (int) $gia_quy_doi;
		if ( $gia <= 0 ) { return 0; }
		return (int) floor( (int) $goi['tien'] * (int) $phut_quy_doi / $gia );
	}

	public static function luu_menh_gia( $ds ) {
		$ra   = array();
		$thay = array();
		foreach ( (array) $ds as $v ) {
			$v    = (array) $v;
			$tien = (int) preg_replace( '/\D+/', '', (string) ( isset( $v['tien'] ) ? $v['tien'] : '' ) );
			if ( $tien < 1000 ) { continue; }
			if ( isset( $thay[ $tien ] ) ) {
				return array( 'ok' => false, 'error' => 'Hai gói cùng số tiền ' . number_format( $tien, 0, ',', '.' )
					. 'đ. Khách bấm hai nút giống hệt nhau thì không biết mình chọn gì.' );
			}
			$thay[ $tien ] = 1;
			$ra[] = array(
				'tien'  => $tien,
				'ten'   => mb_substr( trim( (string) ( isset( $v['ten'] ) ? $v['ten'] : '' ) ), 0, 30 ),
				'phut'  => max( 0, min( 240, (int) ( isset( $v['phut'] ) ? $v['phut'] : 0 ) ) ),
				'mo_ta' => mb_substr( trim( (string) ( isset( $v['mo_ta'] ) ? $v['mo_ta'] : '' ) ), 0, 40 ),
				'vip'   => empty( $v['vip'] ) ? 0 : 1,
			);
		}
		usort( $ra, function ( $a, $b ) { return $a['tien'] - $b['tien']; } );
		if ( ! $ra ) { return array( 'ok' => false, 'error' => 'Phải có ít nhất một gói từ 1.000đ.' ); }
		if ( count( $ra ) > self::SO_O_MAN_GHE ) {
			return array( 'ok' => false, 'error' => 'Màn ghế chỉ có ' . self::SO_O_MAN_GHE
				. ' ô — khai tối đa ' . self::SO_O_MAN_GHE . ' gói.' );
		}
		update_option( 'vhg_menh_gia', $ra );
		return array( 'ok' => true, 'thong_bao' => 'Đã lưu ' . count( $ra ) . ' gói. '
			. 'Ghế lấy về ở lượt nhịp kế tiếp (~30 giây).' );
	}

	/**
	 * Bốn gói ở dạng ghế đọc được: khoá ngắn, và TÊN ĐÃ BỎ DẤU.
	 *
	 * 🔴 Font của màn ghế (TFT_eSPI) KHÔNG vẽ được dấu tiếng Việt — "Gói phổ biến" hiện ra
	 *    thành một hàng ô vuông. Bỏ dấu ở MÁY CHỦ chứ không ở ghế: ghế không có OTA, nên mọi
	 *    thứ sửa được bằng máy chủ thì phải sửa ở máy chủ.
	 *
	 * ⚠️ Khoá một chữ (`t`,`n`,`p`) vì gói nhịp đi qua 4G và ghế giải mã trong bộ đệm cố định.
	 *    Tên khoá dài là tốn đúng chỗ mà một cái tên gói cần.
	 */
	public static function menh_gia_cho_ghe( $gia_quy_doi, $phut_quy_doi ) {
		$ra = array();
		foreach ( self::menh_gia() as $g ) {
			$ra[] = array(
				't' => (int) $g['tien'],
				'n' => self::bo_dau_hoa( $g['ten'], 16 ),
				'p' => self::phut_goi( $g, $gia_quy_doi, $phut_quy_doi ),
				/* Mô tả dài hơn tên: nó nằm trên một dòng riêng dưới đáy thẻ, font nhỏ nhất.
				   24 ký tự là bề ngang một thẻ 148px ở font 1. */
				'm' => self::bo_dau_hoa( $g['mo_ta'], 24 ),
				'v' => (int) $g['vip'],
			);
		}
		return $ra;
	}

	/** Bỏ dấu, viết hoa, cắt cho vừa bề ngang một ô trên màn ghế. */
	public static function bo_dau_hoa( $s, $dai = 18 ) {
		$s = mb_strtolower( trim( (string) $s ), 'UTF-8' );
		$cap = array(
			'a' => 'áàảãạăắằẳẵặâấầẩẫậ', 'e' => 'éèẻẽẹêếềểễệ', 'i' => 'íìỉĩị',
			'o' => 'óòỏõọôốồổỗộơớờởỡợ', 'u' => 'úùủũụưứừửữự', 'y' => 'ýỳỷỹỵ', 'd' => 'đ',
		);
		foreach ( $cap as $tron => $co ) {
			$s = str_replace( preg_split( '//u', $co, -1, PREG_SPLIT_NO_EMPTY ), $tron, $s );
		}
		/* Còn ký tự ngoài ASCII nghĩa là bảng trên thiếu chữ đó — BỎ ĐI chứ đừng gửi xuống ghế,
		   vì màn sẽ vẽ ra ô vuông và người ta tưởng ghế hỏng. */
		$s = preg_replace( '/[^\x20-\x7E]/', '', $s );
		$s = trim( preg_replace( '/\s+/', ' ', $s ) );
		return mb_strtoupper( mb_substr( $s, 0, max( 1, (int) $dai ) ), 'UTF-8' );
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

	/**
	 * TỈ LỆ QUY ĐỔI CHUNG — bao nhiêu tiền ra bao nhiêu phút.
	 *
	 * 🔴 Anh Thắng 22/08/2026: *"không điều chỉnh được loại mệnh giá à"*. Bốn gói thì khai được,
	 *    nhưng SỐ PHÚT của chúng lại do tỉ lệ quyết định — mà tỉ lệ nằm tận ô "Thêm / sửa máy",
	 *    tách khỏi chỗ khai gói và phải lưu lại từng máy một. Nên nhìn bảng gói thì tưởng đã
	 *    khai xong, mà số phút vẫn là số cũ.
	 *
	 *    Tệ hơn: bảng xem trước lúc đó gọi cứng `menh_gia_cho_ghe(10000, 6)` — nó in ra một
	 *    bảng số phút KHÔNG phải số ghế sẽ chạy. Một bảng "xem trước" nói sai còn hại hơn không
	 *    có bảng nào, vì người ta tin nó rồi thôi không đi kiểm.
	 *
	 * Nên tỉ lệ đi cùng đường với tài khoản nhận tiền: khai MỘT LẦN, ghế nào cần khác mới khai
	 * riêng. `gia`/`phut` của máy bằng 0 = dùng chung.
	 */
	const GIA_MAC_DINH  = 50000;
	const PHUT_MAC_DINH = 15;

	public static function ty_le_chung() {
		$gia  = (int) get_option( 'vhg_gia', 0 );
		$phut = (int) get_option( 'vhg_phut', 0 );
		return array(
			'gia'  => $gia > 0 ? $gia : self::GIA_MAC_DINH,
			'phut' => $phut > 0 ? $phut : self::PHUT_MAC_DINH,
		);
	}

	/** Tỉ lệ THỰC DÙNG của một ghế: ô riêng nếu có (>0), không thì ô chung. */
	public static function ty_le_cua( $m ) {
		$c = self::ty_le_chung();
		$m = (array) $m;
		$gia  = isset( $m['gia'] ) ? (int) $m['gia'] : 0;
		$phut = isset( $m['phut'] ) ? (int) $m['phut'] : 0;
		return array(
			'gia'  => $gia > 0 ? $gia : $c['gia'],
			'phut' => $phut > 0 ? $phut : $c['phut'],
		);
	}

	public static function luu_ty_le( $gia, $phut ) {
		$gia  = (int) preg_replace( '/\D+/', '', (string) $gia );
		$phut = (int) preg_replace( '/\D+/', '', (string) $phut );
		if ( $gia < 1000 )              { return array( 'ok' => false, 'error' => 'Số tiền quy đổi phải từ 1.000đ.' ); }
		if ( $phut < 1 || $phut > 240 ) { return array( 'ok' => false, 'error' => 'Số phút quy đổi phải từ 1 đến 240.' ); }
		update_option( 'vhg_gia', $gia );
		update_option( 'vhg_phut', $phut );
		return array( 'ok' => true, 'thong_bao' => 'Đã lưu tỉ lệ ' . number_format( $gia, 0, ',', '.' )
			. 'đ = ' . $phut . ' phút. Ghế lấy về ở lượt nhịp kế tiếp (~30 giây).' );
	}

	/**
	 * Bỏ hết tỉ lệ khai riêng của từng ghế, cho tất cả dùng chung.
	 * Có nút này vì những ghế khai từ bản cũ đều mang tỉ lệ riêng (bản cũ không có ô chung), nên
	 * đổi ô chung mà chúng không theo — và không có gì trên màn nói vì sao.
	 */
	public static function bo_ty_le_rieng() {
		global $wpdb;
		$n = (int) $wpdb->query( 'UPDATE ' . VHG_DB::t( 'may' ) . ' SET gia=0, phut=0 WHERE gia>0 OR phut>0' );
		return array( 'ok' => true, 'thong_bao' => $n
			? 'Đã cho ' . $n . ' ghế dùng tỉ lệ chung.'
			: 'Tất cả ghế vốn đã dùng tỉ lệ chung.' );
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

	/**
	 * Nhớ số tài khoản mà BÊN GỬI báo là đã nhận tiền.
	 *
	 * 🔴 VÌ SAO CẦN. Ngày 22/08/2026 anh Thắng quét thử mã QR bằng app BIDV và bị chối: *"Định
	 *    dạng tài khoản định danh không hợp lệ (174)"*. Nguyên nhân: ô số tài khoản khai tay
	 *    thiếu MỘT chữ số — `888815678` thay vì `8888815678`.
	 *
	 *    Một chữ số. Không có gì trên màn hình sai cả: mã QR vẫn dựng ra, vẫn trông như thật,
	 *    vẫn dán được lên 26 cái ghế. Chỉ tới lúc có khách đứng quét mới lộ — và lúc đó thì đã
	 *    mất một buổi bán hàng ở 26 cửa hàng.
	 *
	 *    Không có cách nào kiểm số tài khoản bằng luật chữ số: mỗi ngân hàng một khuôn, BIDV còn
	 *    có mấy loại tài khoản định danh dài ngắn khác nhau. NHƯNG mình có một sự thật đối chứng
	 *    miễn phí: mỗi lượt webhook, SePay nói rõ tiền vừa vào TÀI KHOẢN NÀO. Nếu số đó khác số
	 *    đang khai thì một trong hai sai — và đó là câu duy nhất đáng nói ra.
	 */
	public static function nho_tk_ben_gui( $so_tk, $tk_ao = '' ) {
		$so_tk = trim( (string) $so_tk );
		if ( '' === $so_tk ) { return; }
		update_option( 'vhg_tk_ben_gui', array( 'so' => $so_tk,
			'va' => trim( (string) $tk_ao ), 'luc' => current_time( 'mysql' ) ) );
	}

	/**
	 * Số đang khai có khớp số bên gửi báo không.
	 * @return array [ 'co' => bool đã từng thấy, 'khop' => bool, 'ben_gui' => string, 'luc' => string ]
	 */
	public static function doi_chieu_tk() {
		$g = get_option( 'vhg_tk_ben_gui' );
		$ben_gui = is_array( $g ) && isset( $g['so'] ) ? trim( (string) $g['so'] ) : '';
		if ( '' === $ben_gui ) {
			return array( 'co' => false, 'khop' => true, 'ben_gui' => '', 'va' => '', 'luc' => '' );
		}
		$khai = self::nhan_tien_chung();
		/* So bằng CHỮ VÀ SỐ, không chỉ chữ số.
		 *
		 * 🔴 Bản trước bỏ hết chữ cái. Tài khoản ảo của SePay là chuỗi CÓ CHỮ (`96247POSH`), nên
		 *    khai VA vào đây là nó biến thành `96247` rồi so với `8888815678` — báo đỏ oan cho
		 *    đúng cấu hình chạy được. Chỉ bỏ dấu cách và gạch: bên gửi có khi trả
		 *    "888 881 5678", khác cách viết không phải khác tài khoản. */
		$a = strtoupper( preg_replace( '/[^0-9A-Za-z]/', '', (string) $khai['so_tk'] ) );
		$b = strtoupper( preg_replace( '/[^0-9A-Za-z]/', '', $ben_gui ) );
		$va = is_array( $g ) && isset( $g['va'] ) ? trim( (string) $g['va'] ) : '';
		/* Khớp NẾU trùng số tài khoản HOẶC trùng tài khoản ảo: mã QR trỏ vào cái nào cũng được,
		   miễn là cái SePay đang theo dõi. Chỉ đòi trùng đúng một ô là báo đỏ oan cho cấu hình
		   đang chạy tốt — và một cảnh báo oan là một cảnh báo bị bỏ qua mãi về sau. */
		$c = strtoupper( preg_replace( '/[^0-9A-Za-z]/', '', $va ) );
		$khop = ( '' !== $a && $a === $b ) || ( '' !== $c && $c === $a );
		return array( 'co' => true, 'khop' => $khop, 'ben_gui' => $ben_gui, 'va' => $va,
			'luc' => is_array( $g ) && isset( $g['luc'] ) ? (string) $g['luc'] : '' );
	}

	/**
	 * TIỀN TỐ BẮT BUỘC TRONG NỘI DUNG CHUYỂN KHOẢN.
	 *
	 * 🔴 MẮT XÍCH CUỐI CÙNG, tìm ra 22/08/2026 trên chính trang Tạo QR của SePay:
	 *
	 *      "SEVQR — VietinBank cá nhân/hộ kinh doanh BẮT BUỘC nội dung CK phải chứa `sevqr`
	 *       để định tuyến giao dịch qua SePay."
	 *
	 *    Không có chuỗi đó thì tiền vẫn vào tài khoản, ngân hàng vẫn báo thành công, nhưng
	 *    SePay KHÔNG BAO GIỜ THẤY — nên không có webhook, và ghế không chạy. Đúng cái đã xảy
	 *    ra: lượt 2.000đ quét từ trang SePay (có SEVQR) thì về, lượt 10.000đ quét chỗ khác
	 *    (không có SEVQR) thì biến mất không dấu vết.
	 *
	 *    Đây là kiểu hỏng tệ nhất trong cả hệ thống: KHÔNG AI BÁO GÌ CẢ. Khách trả tiền xong,
	 *    ngân hàng nói "thành công", ghế đứng im, và trong sổ của mình không có một dòng nào —
	 *    kể cả dòng "có gói lạ bắn tới". Không có gì để đi tìm.
	 *
	 * ⚠️ Tiền tố tuỳ NGÂN HÀNG và tuỳ LOẠI tài khoản, nên KHÔNG gán cứng "SEVQR" trong mã:
	 *    tài khoản doanh nghiệp, hay ngân hàng khác, có thể không cần — mà thừa một chuỗi lạ
	 *    trong nội dung là tốn chỗ của mã lượt (VietQR chỉ cho 25 ký tự).
	 */
	public static function tien_to_nd() {
		return trim( (string) get_option( 'vhg_tien_to_nd', '' ) );
	}

	public static function luu_tien_to_nd( $v ) {
		/* Chỉ chữ và số: nội dung chuyển khoản đi qua nhiều hệ thống, dấu và ký tự lạ là chỗ
		   bị cắt hoặc bị đổi mà không ai báo. */
		$v = strtoupper( preg_replace( '/[^0-9A-Za-z]/', '', (string) $v ) );
		if ( strlen( $v ) > 10 ) {
			return array( 'ok' => false, 'error' => 'Tiền tố tối đa 10 ký tự — nội dung chuyển khoản '
				. 'chỉ có 25 chỗ, để dành cho mã ghế và mã lượt.' );
		}
		update_option( 'vhg_tien_to_nd', $v );
		return array( 'ok' => true, 'thong_bao' => '' === $v
			? 'Đã bỏ tiền tố nội dung chuyển khoản.'
			: 'Đã đặt tiền tố "' . $v . '". Ghế lấy về ở lượt nhịp kế tiếp (~30 giây).' );
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
		$mac_go = trim( (string) ( isset( $d['mac'] ) ? $d['mac'] : '' ) );
		if ( '' !== $mac_go && '' === self::chuan_mac( $mac_go ) ) {
			/* Im lặng bỏ qua một MAC gõ sai là tệ nhất: người ta tưởng đã gắn ghế, còn ghế thật
			   thì vẫn hiện ra như một ghế mới ở danh sách chờ. */
			return array( 'ok' => false, 'error' => 'Địa chỉ MAC không đúng khuôn — phải là 12 ký tự '
				. '0-9/A-F, ví dụ AA:BB:CC:DD:EE:FF. Đang nhận: "' . esc_html( $mac_go ) . '".' );
		}
		$hang = array(
			'ma'       => $ma,
			'mac'      => self::chuan_mac( isset( $d['mac'] ) ? $d['mac'] : '' ),
			'coso_id'  => (int) ( isset( $d['coso_id'] ) ? $d['coso_id'] : 0 ),
			/* 0 = DÙNG CHUNG (xem ty_le_cua). Không ép về mặc định nữa: ép là mọi ghế thành
			   "khai riêng" và ô chung mất tác dụng ngay lúc khai máy đầu tiên. */
			'gia'      => max( 0, (int) ( isset( $d['gia'] ) ? $d['gia'] : 0 ) ),
			'phut'     => max( 0, (int) ( isset( $d['phut'] ) ? $d['phut'] : 0 ) ),
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
	/**
	 * MAC về ĐÚNG MỘT DẠNG: `AA:BB:CC:DD:EE:FF`.
	 *
	 * 🔴 Ghế gửi lên dạng có hai chấm và chữ hoa (`snprintf("%02X:...")`). Người gõ tay thì gõ
	 *    đủ kiểu: thường, gạch ngang, hoặc dính liền không dấu. Không chuẩn hoá thì cùng một
	 *    con ghế mà bảng có hai dòng — dòng khai tay không bao giờ khớp, và ghế hiện ra như một
	 *    ghế mới. Chuẩn hoá ở MỘT chỗ, dùng cho cả đường ghế gửi lên lẫn đường người gõ vào.
	 *
	 * Chuỗi không phải MAC -> trả RỖNG, không trả bừa: một MAC nửa vời còn tệ hơn không có MAC,
	 * vì nó trông như đã gắn ghế.
	 */
	public static function chuan_mac( $mac ) {
		$s = strtoupper( preg_replace( '/[^0-9A-Fa-f]/', '', (string) $mac ) );
		if ( 12 !== strlen( $s ) ) { return ''; }
		return implode( ':', str_split( $s, 2 ) );
	}

	public static function theo_mac( $mac ) {
		global $wpdb;
		$mac = self::chuan_mac( $mac );
		if ( '' === $mac ) { return null; }
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
		$mac = self::chuan_mac( $mac );
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
			'SELECT luc, fw, ip, nd_tien_to FROM ' . VHG_DB::t( 'nhip' ) . ' WHERE ma_may=%s LIMIT 1',
			(string) $ma_may ), ARRAY_A );
		return array(
			'luc' => $r ? (string) $r['luc'] : '',
			'fw'  => $r ? (string) $r['fw'] : '',
			'ip'  => $r ? (string) $r['ip'] : '',
			'nd_tien_to' => $r ? (string) $r['nd_tien_to'] : '',
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
			/* Chặn trên 65535 vì cột là SMALLINT UNSIGNED: quá tầm thì MySQL cắt về 65535 ở chế
			   độ lỏng, hoặc từ chối cả hàng ở chế độ chặt — mất luôn nhịp vì một con số chỉ để
			   chỉnh đồng hồ. Ghế mất mạng lâu có thể gửi lên con số rất lớn. */
			'tre_ms'     => max( 0, min( 65535, (int) ( isset( $d['tre'] ) ? $d['tre'] : 0 ) ) ),
			'ip'         => mb_substr( (string) ( isset( $d['ip'] ) ? $d['ip'] : '' ), 0, 60 ),
			'nd_tien_to' => mb_substr( trim( (string) ( isset( $d['nd'] ) ? $d['nd'] : '' ) ), 0, 20 ),
			/* 80, KHỚP VỚI CỘT. Cắt 40 ở đây là cách hỏng âm thầm: chuỗi phiên bản
			   "ghe-massage 2026-08-22e (tien to noi dung CK tu web)" dài 51 ký tự, cắt xong
			   thành nửa câu, và màn đối chiếu firmware chỉ đó nói ghế đang chạy bản nào. */
			'fw'         => mb_substr( (string) ( isset( $d['fw'] ) ? $d['fw'] : '' ), 0, 80 ),
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
		if ( ! in_array( $viec, array( 'on', 'off', 'reboot' ), true ) ) {
			return array( 'ok' => false, 'error' => 'Lệnh chỉ có thể là bật (on), tắt (off) '
				. 'hoặc khởi động lại (reboot).' );
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
		if ( 'on' === $viec ) {
			return array( 'ok' => true, 'thong_bao' => 'Đã đặt lệnh cho máy ' . $ma_may . ' chạy '
				. $phut . ' phút. Máy nhận trong ~10 giây.' );
		}
		if ( 'reboot' === $viec ) {
			return array( 'ok' => true, 'thong_bao' => 'Đã đặt lệnh KHỞI ĐỘNG LẠI máy ' . $ma_may
				. '. Máy nhận trong ~10 giây rồi tự khởi động, mất khoảng 30 giây mới gửi nhịp lại.' );
		}
		return array( 'ok' => true, 'thong_bao' => 'Đã đặt lệnh TẮT máy ' . $ma_may . '.' );
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
