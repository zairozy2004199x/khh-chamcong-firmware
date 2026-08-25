<?php
/**
 * PHÂN QUYỀN · ĐỔI PIN · TRA PIN THEO CCCD — và ba bộ đếm chống dò.
 *
 * =============================================================================================
 * BỘ ĐẾM NẰM TRONG BẢNG, KHÔNG NẰM TRONG CACHE — VÀ ĐÂY LÀ CHỖ CỐ Ý KHÁC BẢN GỐC
 * =============================================================================================
 * Bên Apps Script cả ba bộ đếm sống trong `CacheService`. Ở WordPress, `transient` nằm trên object
 * cache: cache bị xoá hoặc đầy là bộ đếm về 0 — tức HÌNH PHẠT TỰ BỎ đúng lúc kẻ dò đang dò, mà
 * cách làm cache đầy thì ai cũng làm được. Đếm cho bảo mật phải nằm chỗ không ai xoá hộ, nên ở
 * đây là bảng `nhip_do`.
 *
 * Ba bộ đếm, ba mục đích khác nhau — đừng gộp:
 *   1. ĐỔI PIN đụng PIN người khác   `DOIPIN_NGUONG` = 5 lần / 10 phút
 *      Chặn dò xem số nào đã có người dùng. Chỉ đếm lượt ĐỤNG TRÚNG.
 *   2. TRA PIN theo TỪNG SỐ CCCD     `TRA_PIN_MOI_SO` = 5 lượt / 10 phút
 *      Chặn nhắm vào một người. Đếm MỌI lượt của số đó.
 *   3. TRA PIN toàn hệ thống         `TRA_PIN_TONG` = 30 lượt TRƯỢT / 10 phút
 *      ⚠️ CHỈ đếm lượt TRƯỢT. Đếm cả lượt đúng thì một buổi sáng đông người quên PIN là cả cửa
 *         hàng tự khoá nhau — mà đó là lúc cần tra nhất.
 *
 * ⚠️ Nhật ký tra PIN ghi CCCD ĐÃ CHE và KHÔNG BAO GIỜ ghi PIN. Người xem được nhật ký thường
 *    nhiều hơn người được xem PIN.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_Quyen {

	const CUA_SO       = 600;   // giây — cửa sổ đếm của cả ba bộ
	const DOIPIN_NGUONG = 5;
	const TRA_PIN_MOI_SO = 5;
	const TRA_PIN_TONG   = 30;

	/** PIN bị cấm — dãy dễ đoán nhất. Đây là chìa khoá vào toàn bộ chấm công của chuỗi. */
	/**
	 * PIN bị chặn.
	 *
	 * Hai nhóm, khác nhau về lý do:
	 *   · Dễ đoán ai cũng thử: 000000, 111111, 123456…
	 *   · ĐÃ BỊ LỘ trong quá trình làm việc này: `888888` (PIN admin mặc định của app gốc, có
	 *     trong lịch sử chat) và `859624`. Chúng không "dễ đoán" nhưng đã ra ngoài, mà một mật
	 *     khẩu đã ra ngoài thì mạnh hay yếu không còn nghĩa gì.
	 *
	 * ⚠️ Chặn ở chỗ ĐẶT MẬT KHẨU, không chặn ở chỗ đăng nhập — ai đang dùng thì vẫn dùng được
	 *    cho tới khi tự đổi. Chặn đăng nhập là khoá người ta ra khỏi hệ thống của chính họ mà
	 *    không báo trước.
	 */
	const PIN_CAM = array( '000000', '111111', '123456', '654321', '888888', '999999',
		'012345', '121212', '859624' );

	// ======================================================================= bộ đếm

	/**
	 * Đếm một lượt cho `$khoa`. Trả array('so', 'qua').
	 * Cửa sổ hết hạn thì đếm lại từ 1 — không cộng dồn vô hạn.
	 */
	public static function dem( $khoa, $tran ) {
		global $wpdb;
		$bang = VHCC_DB::t( 'nhip_do' );
		$moc  = gmdate( 'Y-m-d H:i:s', (int) current_time( 'timestamp' ) - self::CUA_SO );
		$cu = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $bang WHERE khoa=%s", $khoa ), ARRAY_A );
		if ( ! $cu ) {
			$wpdb->insert( $bang, array( 'khoa' => $khoa, 'so_lan' => 1,
				'cua_so_tu' => current_time( 'mysql' ) ) );
			return array( 'so' => 1, 'qua' => 1 > $tran );
		}
		if ( $cu['cua_so_tu'] < $moc ) {                       // cửa sổ cũ đã hết -> đếm lại
			$wpdb->update( $bang, array( 'so_lan' => 1, 'cua_so_tu' => current_time( 'mysql' ) ),
				array( 'id' => (int) $cu['id'] ) );
			return array( 'so' => 1, 'qua' => 1 > $tran );
		}
		$so = (int) $cu['so_lan'] + 1;
		$wpdb->update( $bang, array( 'so_lan' => $so ), array( 'id' => (int) $cu['id'] ) );
		return array( 'so' => $so, 'qua' => $so > $tran );
	}

	/** Đọc số đếm mà KHÔNG cộng thêm — dùng để kiểm trước khi làm việc. */
	public static function doc_dem( $khoa ) {
		global $wpdb;
		$moc = gmdate( 'Y-m-d H:i:s', (int) current_time( 'timestamp' ) - self::CUA_SO );
		$r = $wpdb->get_row( $wpdb->prepare(
			'SELECT so_lan, cua_so_tu FROM ' . VHCC_DB::t( 'nhip_do' ) . ' WHERE khoa=%s', $khoa ), ARRAY_A );
		if ( ! $r || $r['cua_so_tu'] < $moc ) { return 0; }
		return (int) $r['so_lan'];
	}

	/** Hàm THUẦN, tách ra để thử được mà không phải ngồi chờ hết cửa sổ 10 phút. */
	public static function doi_pin_con_duoc( $so_lan ) {
		return (int) $so_lan < self::DOIPIN_NGUONG;
	}

	// ======================================================================= phân quyền

	public static function ds_phan_quyen( $u ) {
		if ( ! VHCC_NhanSu::co_quan_tri_nv( $u ) ) { return array(); }
		return VHCC_DB::rows( 'SELECT * FROM ' . VHCC_DB::t( 'phan_quyen' ) . ' ORDER BY ho_ten' );
	}

	/** Từng quyền một -> true/false. Khai ở VHCC_Vai::QUYEN, không khai lại ở đây. */
	public static function bang_quyen( $u ) {
		$ra = array();
		foreach ( array_keys( VHCC_Vai::QUYEN ) as $q ) { $ra[ $q ] = VHCC_Vai::duoc( $u, $q ); }
		return $ra;
	}

	/** Quyền của chính người đang đăng nhập — để giao diện biết ẩn/hiện gì. */
	public static function quyen_cua( $u ) {
		return array(
			'vaiTro'      => VHCC_Vai::cua( $u ),
			'vaiTroTen'   => VHCC_Vai::ten( $u ),
			'bac'         => VHCC_Vai::bac( $u ),
			'suaHoSo'     => VHCC_NhanSu::co_sua_ho_so( $u ),
			'quanTriNV'   => VHCC_NhanSu::co_quan_tri_nv( $u ),
			'xemLuong'    => VHCC_NhanSu::co_xem_luong( $u ),
			'vaoManLuong' => VHCC_Luong::co_quyen( isset( $u['role'] ) ? $u['role'] : '' ),
			'dsCoSo'      => VHCC_NhanSu::ds_coso_cua( $u ),
			/* Bảng quyền đầy đủ, để giao diện ẩn/hiện theo ĐÚNG một nguồn thay vì tự suy từ
			   tên vai trò — suy ở giao diện là bộ luật thứ hai, và nó lệch trước tiên. */
			'quyen'       => self::bang_quyen( $u ),
		);
	}

	/**
	 * Lưu một dòng phân quyền.
	 * ⚠️ `vai_tro` KHÔNG bị chặn theo danh sách — bên Apps Script `saveRole` chỉ `.toUpperCase()`
	 *    rồi ghi thẳng, và mọi chỗ kiểm quyền đều so BẰNG với tên vai trò cụ thể. Chặn danh sách
	 *    ở đây là làm hỏng những dòng vai trò đang có mà không ai biết.
	 */
	public static function luu_phan_quyen( $u, $dat ) {
		global $wpdb;
		if ( ! VHCC_NhanSu::co_quan_tri_nv( $u ) ) {
			return array( 'ok' => false, 'error' => 'Sửa phân quyền — ' . VHCC_NhanSu::LOI_QT );
		}
		$pin = trim( isset( $dat['pin'] ) ? (string) $dat['pin'] : '' );
		$loi = self::pin_hop_le( $pin );
		if ( '' !== $loi ) { return array( 'ok' => false, 'error' => $loi ); }
		$ghi = array(
			'pin' => $pin,
			'ho_ten' => trim( isset( $dat['ho_ten'] ) ? (string) $dat['ho_ten'] : '' ),
			/* Sổ `phan_quyen` là bản sao sổ của app gốc, lưu vai trò dạng MÃ HOA. Quy về mã bằng
			   VHCC_Vai::ma() chứ không `strtoupper` — strtoupper không nâng được chữ có dấu, nên
			   "Quản lý" gõ vào ô này sẽ lưu thành "QUảN Lý", một vai không ai đọc ra. */
			'vai_tro' => VHCC_Vai::ma( isset( $dat['vai_tro'] ) ? $dat['vai_tro'] : '' ),
			'cua_hang' => trim( isset( $dat['cua_hang'] ) ? (string) $dat['cua_hang'] : '' ),
			'ma_cc_online' => trim( isset( $dat['ma_cc_online'] ) ? (string) $dat['ma_cc_online'] : '' ),
			'coso_cc_online' => VHCC_NhanSu::chuan_coso( isset( $dat['coso_cc_online'] ) ? $dat['coso_cc_online'] : '' ),
			'cap_nhat' => current_time( 'mysql' ),
		);
		$cu = $wpdb->get_row( $wpdb->prepare(
			'SELECT id FROM ' . VHCC_DB::t( 'phan_quyen' ) . ' WHERE pin=%s', $pin ), ARRAY_A );
		if ( $cu ) { $wpdb->update( VHCC_DB::t( 'phan_quyen' ), $ghi, array( 'id' => (int) $cu['id'] ) ); }
		else       { $wpdb->insert( VHCC_DB::t( 'phan_quyen' ), $ghi ); }
		return array( 'ok' => true, 'taoMoi' => ! $cu );
	}

	public static function xoa_phan_quyen( $u, $pin ) {
		global $wpdb;
		if ( ! VHCC_NhanSu::co_quan_tri_nv( $u ) ) {
			return array( 'ok' => false, 'error' => 'Xoá phân quyền — ' . VHCC_NhanSu::LOI_QT );
		}
		$pin = trim( (string) $pin );
		/* ⚠️ Không cho tự xoá dòng của CHÍNH MÌNH: xoá xong là mất quyền, mà mất quyền thì không
		   vào lại được để sửa. Đây là kiểu tự khoá mình ra ngoài mà không có đường lùi. */
		if ( isset( $u['pin'] ) && trim( (string) $u['pin'] ) === $pin ) {
			return array( 'ok' => false, 'error' => 'Không xoá được dòng phân quyền của chính bạn — '
				. 'xoá xong là mất quyền và không vào lại được để sửa.' );
		}
		$con = (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'phan_quyen' ) . ' WHERE vai_tro=%s AND pin<>%s',
			'ADMIN', $pin ) );
		$la_admin = 'ADMIN' === strtoupper( (string) $wpdb->get_var( $wpdb->prepare(
			'SELECT vai_tro FROM ' . VHCC_DB::t( 'phan_quyen' ) . ' WHERE pin=%s', $pin ) ) );
		/* ⚠️ Không cho xoá ADMIN CUỐI CÙNG. Không còn admin nào là không ai cấp lại quyền được,
		   và cách duy nhất còn lại là sửa tay trong cơ sở dữ liệu. */
		if ( $la_admin && 0 === $con ) {
			return array( 'ok' => false, 'error' => 'Đây là tài khoản ADMIN duy nhất còn lại. '
				. 'Xoá là không ai cấp lại quyền được nữa — cấp thêm một admin khác trước.' );
		}
		$wpdb->delete( VHCC_DB::t( 'phan_quyen' ), array( 'pin' => $pin ) );
		return array( 'ok' => true );
	}

	/** Tài khoản ĐƯỢC BẬT chấm công online = dòng phân quyền có khai Mã NV. */
	public static function ds_bat_cham_cong_online() {
		return VHCC_DB::rows( 'SELECT pin, ho_ten, vai_tro, ma_cc_online, coso_cc_online FROM '
			. VHCC_DB::t( 'phan_quyen' ) . " WHERE ma_cc_online <> '' ORDER BY ho_ten" );
	}

	// ======================================================================= PIN

	/** Luật PIN, y bản gốc. Trả '' nếu dùng được. */
	public static function pin_hop_le( $pin ) {
		$p = trim( (string) $pin );
		if ( '' === $p ) { return 'Chưa nhập mật khẩu.'; }
		if ( ! preg_match( '/^[0-9]{6}$/', $p ) ) { return 'Mật khẩu phải là ĐÚNG 6 chữ số (0-9).'; }
		if ( preg_match( '/^(\d)\1{5}$/', $p ) ) {
			return 'Mật khẩu không được là 6 chữ số giống nhau (111111…).';
		}
		/* Dãy liên tiếp — kiểm bằng cách tìm chuỗi con trong một dãy dài, đúng cách bản gốc làm.
		   Bắt được cả 123456, 234567, 654321, 543210… mà không phải liệt kê từng cái. */
		if ( false !== strpos( '012345678901234567890', $p )
			|| false !== strpos( '098765432109876543210', $p ) ) {
			return 'Mật khẩu không được là dãy liên tiếp (123456, 654321…).';
		}
		if ( in_array( $p, self::PIN_CAM, true ) ) {
			return 'Mật khẩu này nằm trong danh sách bị chặn vì quá dễ đoán.';
		}
		return '';
	}

	/**
	 * ĐỔI PIN. Bản dịch `doiPin`.
	 *
	 * ⚠️ Chặn nhịp độ TRƯỚC khi đụng bảng: quá ngưỡng thì không cho biết thêm gì nữa về số nào có
	 *    người dùng.
	 * ⚠️ VẪN nói thật "mật khẩu này đã có người dùng". Giấu lý do chỉ làm người ngay khó chịu — kẻ
	 *    dò vẫn phân biệt được thành công/thất bại bằng cách khác. Cái phải chặn là DÒ HÀNG LOẠT,
	 *    và chặn bằng SỐ LẦN, không bằng cách giấu.
	 */
	public static function doi_pin( $pin_cu, $pin_moi, $pin_moi_lai ) {
		global $wpdb;
		$cu  = trim( (string) $pin_cu );
		$moi = trim( (string) $pin_moi );
		$lai = trim( (string) $pin_moi_lai );

		if ( '' === $moi ) { return array( 'ok' => false, 'error' => 'Chưa nhập mật khẩu mới.' ); }
		if ( $moi !== $lai ) {
			return array( 'ok' => false, 'error' => 'Hai lần nhập mật khẩu mới KHÔNG giống nhau.' );
		}
		if ( $moi === $cu ) {
			return array( 'ok' => false, 'error' => 'Mật khẩu mới trùng mật khẩu đang dùng.' );
		}
		$loi = self::pin_hop_le( $moi );
		if ( '' !== $loi ) { return array( 'ok' => false, 'error' => $loi ); }

		if ( ! self::doi_pin_con_duoc( self::doc_dem( 'doipin_' . $cu ) ) ) {
			return array( 'ok' => false, 'error' => 'Bạn đã thử quá nhiều mật khẩu đang có người dùng. '
				. 'Chờ ít phút rồi thử lại, hoặc nhờ Admin cấp giúp một mật khẩu.' );
		}

		$bang = VHCC_DB::t( 'phan_quyen' );
		$toi = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM $bang WHERE pin=%s", $cu ), ARRAY_A );
		if ( ! $toi ) {
			return array( 'ok' => false, 'error' => 'Không thấy tài khoản đang đăng nhập trong bảng phân quyền.' );
		}
		$trung = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM $bang WHERE pin=%s", $moi ), ARRAY_A );
		if ( $trung && (int) $trung['id'] !== (int) $toi['id'] ) {
			self::dem( 'doipin_' . $cu, self::DOIPIN_NGUONG );
			return array( 'ok' => false, 'error' => 'Mật khẩu này đã có người dùng, chọn số khác.' );
		}
		// CHỈ ghi cột PIN, không đụng vai trò / cửa hàng.
		$wpdb->update( $bang, array( 'pin' => $moi, 'cap_nhat' => current_time( 'mysql' ) ),
			array( 'id' => (int) $toi['id'] ) );
		return array( 'ok' => true, 'ghiChu' => 'Đã đổi mật khẩu. Mật khẩu cũ mất hiệu lực ngay.' );
	}

	/**
	 * CẤP PIN HÀNG LOẠT cho người chưa có tài khoản.
	 * ⚠️ KHÔNG bao giờ ghi `ho_ten` bằng MÃ NV khi hồ sơ bỏ trống tên. Bản gốc từng viết
	 *    `name: <tên> || <mã>`, và hậu quả là màn hình chào "Xin chào, MNNV2MTD0026". Tên trống thì
	 *    để trống, rồi hiện mã ở chỗ hiện mã.
	 */
	public static function cap_pin_hang_loat( $u, $ds_ma ) {
		global $wpdb;
		if ( ! VHCC_NhanSu::co_quan_tri_nv( $u ) ) {
			return array( 'ok' => false, 'error' => 'Cấp PIN hàng loạt — ' . VHCC_NhanSu::LOI_QT );
		}
		$bang = VHCC_DB::t( 'phan_quyen' );
		$da = array();
		foreach ( (array) $wpdb->get_col( "SELECT pin FROM $bang" ) as $x ) { $da[ $x ] = 1; }
		$cap = array();
		$bo_qua = array();
		foreach ( (array) $ds_ma as $ma ) {
			$ma = trim( (string) $ma );
			if ( '' === $ma ) { continue; }
			$hs = VHCC_NhanSu::ho_so( $ma );
			if ( ! $hs ) { $bo_qua[] = $ma . ' (chưa có hồ sơ)'; continue; }
			$co = $wpdb->get_var( $wpdb->prepare(
				"SELECT pin FROM $bang WHERE ma_cc_online=%s LIMIT 1", $ma ) );
			if ( $co ) { $bo_qua[] = $ma . ' (đã có tài khoản)'; continue; }
			$pin = self::sinh_pin( $da );
			if ( '' === $pin ) { $bo_qua[] = $ma . ' (không sinh được PIN chưa dùng)'; continue; }
			$da[ $pin ] = 1;
			$wpdb->insert( $bang, array( 'pin' => $pin,
				'ho_ten' => (string) $hs['ho_ten'],          // trống thì để TRỐNG, không lấy mã
				'vai_tro' => 'NHAN_VIEN',
				'cua_hang' => (string) $hs['cua_hang'],
				'ma_cc_online' => $ma,
				'coso_cc_online' => VHCC_NhanSu::chuan_coso( $hs['cua_hang'] ),
				'cap_nhat' => current_time( 'mysql' ) ) );
			$cap[] = array( 'ma' => $ma, 'ten' => (string) $hs['ho_ten'], 'pin' => $pin );
		}
		return array( 'ok' => true, 'cap' => $cap, 'boQua' => $bo_qua );
	}

	/** PIN 6 số chưa ai dùng, và phải qua được `pin_hop_le` (không sinh ra PIN dễ đoán). */
	private static function sinh_pin( $da ) {
		for ( $i = 0; $i < 500; $i++ ) {
			$p = sprintf( '%06d', wp_rand( 0, 999999 ) );
			if ( isset( $da[ $p ] ) ) { continue; }
			if ( '' !== self::pin_hop_le( $p ) ) { continue; }
			return $p;
		}
		return '';
	}

	// ======================================================================= tra PIN theo CCCD

	public static function chuan_cccd( $v ) { return preg_replace( '/\D+/', '', (string) $v ); }

	/** Che giữa, để 3 số đầu và 3 số cuối — đủ đối chiếu nhật ký, không đủ để dùng lại. */
	public static function che_cccd( $s ) {
		$s = self::chuan_cccd( $s );
		if ( strlen( $s ) < 7 ) { return '' !== $s ? ( substr( $s, 0, 1 ) . '***' ) : ''; }
		return substr( $s, 0, 3 ) . '***' . substr( $s, -3 );
	}

	private static function ghi_nhat_ky( $cccd, $ket_qua, $ma_nv = '', $ghi_chu = '' ) {
		global $wpdb;
		$wpdb->insert( VHCC_DB::t( 'nhat_ky_tra_pin' ), array(
			'luc' => current_time( 'mysql' ),
			'cccd_che' => self::che_cccd( $cccd ),          // CHE, và tuyệt đối không ghi PIN
			'ket_qua' => (string) $ket_qua, 'ma_nv' => (string) $ma_nv,
			'ghi_chu' => (string) $ghi_chu ) );
	}

	/**
	 * Tra PIN theo CCCD. KHÔNG cần đăng nhập — nhân viên quên PIN thì làm sao đăng nhập để tra.
	 * Chính vì vậy ba bộ đếm ở trên là thứ duy nhất đứng giữa cửa này và một lượt dò hàng loạt.
	 */
	public static function tra_pin_theo_cccd( $cccd ) {
		global $wpdb;
		$so = self::chuan_cccd( $cccd );
		if ( strlen( $so ) < 9 || strlen( $so ) > 12 ) {
			return array( 'ok' => false,
				'error' => 'Số căn cước phải đủ 12 số (hoặc 9 số nếu là CMND cũ).' );
		}
		/* Chặn theo TỪNG SỐ trước, để một người gõ nhầm vài lần không làm khoá cả hệ thống. */
		if ( self::dem( 'trapin_so_' . $so, self::TRA_PIN_MOI_SO )['qua'] ) {
			self::ghi_nhat_ky( $so, 'chan-qua-nhieu-lan', '',
				'quá ' . self::TRA_PIN_MOI_SO . ' lượt/10 phút cho cùng một số' );
			return array( 'ok' => false,
				'error' => 'Số này đã tra quá nhiều lần. Chờ 10 phút hoặc hỏi quản lý cửa hàng.' );
		}
		if ( self::doc_dem( 'trapin_hong' ) > self::TRA_PIN_TONG ) {
			self::ghi_nhat_ky( $so, 'chan-toan-he-thong', '',
				'quá ' . self::TRA_PIN_TONG . ' lượt hỏng/10 phút' );
			return array( 'ok' => false,
				'error' => 'Hệ thống đang tạm khoá tra cứu (có quá nhiều lượt tra sai). '
					. 'Nhờ quản lý cửa hàng lấy giúp mã PIN.' );
		}

		/* KHỚP TUYỆT ĐỐI, không khớp một phần: khớp một phần là gõ 4 số cũng ra người khác. */
		$hs = null;
		foreach ( VHCC_DB::rows( 'SELECT ma_nv, ho_ten, cccd FROM ' . VHCC_DB::t( 'nhan_vien' )
			. " WHERE cccd <> ''" ) as $r ) {
			if ( self::chuan_cccd( $r['cccd'] ) === $so ) { $hs = $r; break; }
		}
		if ( ! $hs ) {
			self::dem( 'trapin_hong', self::TRA_PIN_TONG );
			self::ghi_nhat_ky( $so, 'khong-thay' );
			return array( 'ok' => false, 'error' => 'Không tìm thấy số căn cước này trong hệ thống. '
				. 'Nếu anh/chị chưa có hồ sơ thì dùng ô "Gửi thông tin vào máy chấm công".' );
		}
		$pq = $wpdb->get_row( $wpdb->prepare(
			'SELECT pin, coso_cc_online FROM ' . VHCC_DB::t( 'phan_quyen' )
			. ' WHERE LOWER(ma_cc_online)=LOWER(%s) LIMIT 1', $hs['ma_nv'] ), ARRAY_A );
		if ( ! $pq || '' === trim( (string) $pq['pin'] ) ) {
			self::dem( 'trapin_hong', self::TRA_PIN_TONG );
			self::ghi_nhat_ky( $so, 'chua-co-tai-khoan', $hs['ma_nv'] );
			return array( 'ok' => false, 'error' => 'Hồ sơ có nhưng chưa được cấp mật khẩu đăng nhập. '
				. 'Nhờ quản lý cửa hàng cấp giúp.' );
		}
		self::ghi_nhat_ky( $so, 'tra-duoc', $hs['ma_nv'] );
		return array( 'ok' => true, 'pin' => $pq['pin'], 'ten' => $hs['ho_ten'],
			'coSo' => VHCC_NhanSu::chuan_coso( $pq['coso_cc_online'] ) );
	}

	// ======================================================================= dọn tài khoản

	/** Các PIN bị khai trùng cho nhiều người — dấu hiệu hai người dùng một tài khoản. */
	public static function tim_pin_trung( $u ) {
		if ( ! VHCC_NhanSu::co_quan_tri_nv( $u ) ) { return array(); }
		return VHCC_DB::rows( 'SELECT ma_cc_online, COUNT(*) so, GROUP_CONCAT(pin) cac_pin FROM '
			. VHCC_DB::t( 'phan_quyen' ) . " WHERE ma_cc_online <> ''"
			. ' GROUP BY LOWER(ma_cc_online) HAVING so > 1' );
	}

	/**
	 * Gộp hai tài khoản trỏ về cùng một mã NV: giữ một dòng, xoá dòng kia.
	 * ⚠️ KHÔNG đụng gì tới bảng chấm công. Chấm công gắn với MÃ NV, không gắn với PIN — gộp tài
	 *    khoản là việc của bảng phân quyền, và nếu nó chạm sang chấm công thì một lần dọn tài khoản
	 *    thành một lần đổi công.
	 */
	public static function gop_tai_khoan( $u, $pin_giu, $pin_xoa ) {
		global $wpdb;
		if ( ! VHCC_NhanSu::co_quan_tri_nv( $u ) ) {
			return array( 'ok' => false, 'error' => 'Gộp tài khoản — ' . VHCC_NhanSu::LOI_QT );
		}
		$giu = trim( (string) $pin_giu );
		$xoa = trim( (string) $pin_xoa );
		if ( '' === $giu || '' === $xoa || $giu === $xoa ) {
			return array( 'ok' => false, 'error' => 'Cần hai PIN khác nhau.' );
		}
		$bang = VHCC_DB::t( 'phan_quyen' );
		$a = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $bang WHERE pin=%s", $giu ), ARRAY_A );
		$b = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $bang WHERE pin=%s", $xoa ), ARRAY_A );
		if ( ! $a || ! $b ) { return array( 'ok' => false, 'error' => 'Không thấy một trong hai PIN.' ); }
		if ( strtolower( trim( (string) $a['ma_cc_online'] ) ) !== strtolower( trim( (string) $b['ma_cc_online'] ) ) ) {
			return array( 'ok' => false, 'error' => 'Hai tài khoản này trỏ về HAI mã NV khác nhau ('
				. $a['ma_cc_online'] . ' và ' . $b['ma_cc_online'] . ') — không phải trùng, '
				. 'gộp là mất một người.' );
		}
		$wpdb->delete( $bang, array( 'pin' => $xoa ) );
		return array( 'ok' => true, 'daXoa' => $xoa, 'conLai' => $giu );
	}
}
