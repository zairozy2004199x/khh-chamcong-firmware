<?php
/**
 * DANH SÁCH NGƯỜI DÙNG RIÊNG của plugin chấm công.
 *
 * Anh Thắng: *"anh để chỉ plugin này thôi mà"* — plugin phải chạy được MỘT MÌNH, không bắt cài
 * kèm plugin Vận Hành Chi Phí chỉ để có chỗ khai người dùng.
 *
 * Trước bản này, chọn "Danh sách riêng" là tắc: option `vhcc_nguoidung` chỉ được ĐỌC, không màn
 * nào ghi vào. Nghĩa là chọn xong thì không ai đăng nhập được, và màn hình không hề nói ra. Đây
 * là lớp bù chỗ đó.
 *
 * 🔴 KHÔNG BAO GIỜ IN PIN RA MÀN HÌNH. Chỉ hiện số chữ số. Ảnh màn hình đi khắp nơi — trong
 *    chính việc này đã mất một khoá cầu nối vì một ảnh gửi qua chat. Sửa PIN thì gõ lại, không
 *    hiện PIN cũ ra để sửa.
 *
 * 🔴 KHÔNG XOÁ ĐƯỢC NGƯỜI CUỐI CÙNG CÒN VÀO ĐƯỢC. Xoá là không ai đăng nhập nổi nữa, mà đường
 *    lùi duy nhất là sửa thẳng cơ sở dữ liệu. Cùng luật với "không xoá ADMIN cuối cùng".
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_NguoiDung {

	const O = 'vhcc_nguoidung';

	/**
	 * PIN bị chặn. Dùng lại ĐÚNG danh sách của màn Phân quyền — hai bản danh sách PIN yếu thì
	 * sớm muộn lệch nhau, và bên lỏng hơn thành cửa vào.
	 */
	public static function pin_bi_cam() { return VHCC_Quyen::PIN_CAM; }

	/**
	 * CÁC KHO PIN CŨ có thể nạp sang danh sách riêng.
	 *
	 * Anh Thắng: *"pin nằm ở dữ liệu cũ chứ"* và *"bên chi phí không liên quan gì chấm công,
	 * anh vẫn dùng danh sách riêng"*. Nên hình dạng đúng là: **danh sách riêng là kho thật**,
	 * còn hai chỗ dưới đây chỉ là nơi NẠP MỘT LẦN từ dữ liệu cũ sang.
	 */
	const NGUON_CU = array(
		'ho_so' => 'Hồ sơ Nhân sự trên host (cột PIN đăng nhập)',
		'app'   => 'Sổ Phân quyền của app gốc (kéo từ Google Sheet về)',
		'chung' => 'Bảng người dùng của plugin Vận Hành Chi Phí',
	);

	/**
	 * Đọc một kho cũ. `ho_so` đọc thẳng bảng `nhan_vien` — chỗ file .csv nhân viên đổ vào.
	 *
	 * Vì sao tách riêng khỏi VHCC_Auth: hồ sơ nhân sự KHÔNG phải một nguồn đăng nhập. Nó là hồ
	 * sơ; cổng PIN không đọc nó. Ở đây nó chỉ là chỗ để NẠP MỘT LẦN sang danh sách riêng, và
	 * đúng như vậy thì xoá một người khỏi danh sách đăng nhập không làm mất hồ sơ của họ.
	 */
	public static function doc_kho( $tu ) {
		if ( 'ho_so' !== $tu ) { return VHCC_Auth::users_cua( (string) $tu ); }
		$ra = array();
		foreach ( VHCC_DB::rows( 'SELECT ho_ten, pin_dang_nhap, chuc_vu, cua_hang FROM '
			. VHCC_DB::t( 'nhan_vien' ) . " WHERE pin_dang_nhap <> ''" ) as $r ) {
			$ra[] = array(
				'ten'    => trim( (string) $r['ho_ten'] ),
				'pin'    => VHCC_Auth::pin_sach( $r['pin_dang_nhap'] ),
				/* `Chức vụ` của sheet nhân viên là chức vụ ("Máy tự động"), KHÔNG phải vai trò
				   đăng nhập. Nhận ra thì dùng, không thì để rỗng — để chỗ gọi biết mà hỏi anh
				   Thắng chọn vai trò, thay vì lặng lẽ đặt hết thành 'Nhân viên' rồi không ai
				   đăng nhập được mà màn hình vẫn báo "đã nạp N người". */
				'vaiTro' => self::vai_tro_biet( $r['chuc_vu'] ),
				'coso'   => trim( (string) $r['cua_hang'] ),
			);
		}
		return $ra;
	}

	/** Có ai đăng nhập được với nguồn ĐANG CHỌN không? */
	public static function co_ai_vao_duoc() {
		$u = VHCC_Auth::users();
		if ( is_wp_error( $u ) ) { return false; }
		$cho = VHCC_Auth::vai_tro_vao();
		foreach ( $u as $x ) {
			if ( '' !== $x['pin'] && in_array( $x['vaiTro'], $cho, true ) ) { return true; }
		}
		return false;
	}

	/**
	 * Soi CẢ HAI kho cũ: mỗi kho có bao nhiêu người, bao nhiêu người vào được.
	 *
	 * "PIN của mọi người đang nằm ở đâu" là câu phải trả lời được TRƯỚC khi chọn nguồn. Trước
	 * bản này màn Cài đặt chỉ đếm được kho ĐANG CHỌN, nên chọn nhầm là thấy số 0 mà không biết
	 * kho bên cạnh đang có đủ người.
	 */
	public static function do_kho_cu() {
		$ra  = array();
		$cho = VHCC_Auth::vai_tro_vao();
		foreach ( array_keys( self::NGUON_CU ) as $tu ) {
			$u = self::doc_kho( $tu );
			if ( is_wp_error( $u ) ) {
				$ra[ $tu ] = array( 'co' => 0, 'vao' => 0, 'loi' => $u->get_error_message() );
				continue;
			}
			$vao = 0;
			foreach ( $u as $x ) {
				if ( '' !== $x['pin'] && in_array( $x['vaiTro'], $cho, true ) ) { $vao++; }
			}
			$ra[ $tu ] = array( 'co' => count( $u ), 'vao' => $vao, 'loi' => '' );
		}
		return $ra;
	}

	/**
	 * NẠP SỔ PIN CŨ SANG DANH SÁCH RIÊNG — giữ nguyên PIN của từng người.
	 *
	 * 🔴 KHÔNG chặn PIN yếu/đã lộ ở đây. Đây là PIN người ta ĐANG DÙNG THẬT; chặn lúc nạp là
	 *    khoá đúng những người đó ra khỏi hệ thống, mà màn hình chỉ báo "bỏ qua N dòng" — kiểu
	 *    hỏng im lặng tệ nhất. Nạp hết, rồi KÊU TÊN ra để đổi. Chặn PIN yếu là việc của lúc
	 *    ĐẶT PIN mới (`pin_hop_le`), không phải lúc nhận dữ liệu cũ.
	 *
	 * ⚠️ Chỉ THÊM, không sửa, không xoá ai đang có. Nạp lại lần hai là không-làm-gì, nhờ vậy
	 *    bấm nhầm hai lần không nhân đôi danh sách.
	 *
	 * @param string $tu       khoá trong NGUON_CU.
	 * @param bool   $chi_xem  true = chỉ đếm, không ghi.
	 * @param string $coso     rỗng = cả chuỗi; có tên = CHỈ cơ sở đó.
	 */
	public static function nap_tu_cu( $tu, $chi_xem = true, $coso = '', $vt_mac_dinh = '' ) {
		$tu = (string) $tu;
		if ( ! isset( self::NGUON_CU[ $tu ] ) ) {
			return array( 'ok' => false, 'error' => 'Không rõ nạp từ kho nào.' );
		}
		$u = self::doc_kho( $tu );
		if ( is_wp_error( $u ) ) { return array( 'ok' => false, 'error' => $u->get_error_message() ); }
		if ( ! count( $u ) ) {
			return array( 'ok' => false, 'error' => self::NGUON_CU[ $tu ] . ' đang TRỐNG — không có gì để nạp.' );
		}

		return self::them_nhieu( $u, $chi_xem, $coso, $vt_mac_dinh );
	}

	/**
	 * THÊM NHIỀU NGƯỜI CÙNG LÚC — lõi dùng chung cho mọi đường nạp (kho cũ, dán từ Sheet).
	 *
	 * Một lõi duy nhất, vì mấy luật dưới đây mà có hai bản thì sớm muộn lệch nhau, và bên lỏng
	 * hơn thành cửa vào:
	 *
	 * 🔴 KHÔNG chặn PIN yếu/đã lộ. Đây là PIN người ta ĐANG DÙNG THẬT; chặn lúc nạp là khoá
	 *    đúng những người đó ra khỏi hệ thống, mà màn hình chỉ báo "bỏ qua N dòng" — kiểu hỏng
	 *    im lặng tệ nhất. Nạp hết, rồi KÊU TÊN ra để đổi. Chặn PIN yếu là việc của lúc ĐẶT PIN
	 *    mới (`pin_hop_le`), không phải lúc nhận dữ liệu cũ.
	 * ⚠️ Chỉ THÊM, không sửa, không xoá ai đang có. Nạp lại lần hai là không-làm-gì, nhờ vậy
	 *    bấm nhầm hai lần không nhân đôi danh sách.
	 *
	 * @param array  $nguoi   [ ['ten','pin','vaiTro','coso'], … ]
	 * @param bool   $chi_xem true = chỉ đếm, không ghi.
	 * @param string $coso    rỗng = nhận hết; có tên = CHỈ cơ sở đó.
	 */
	public static function them_nhieu( $nguoi, $chi_xem = true, $coso = '', $vt_mac_dinh = '' ) {
		$ds     = self::ds();
		$pin_co = array();
		foreach ( $ds as $x ) { if ( '' !== $x['pin'] ) { $pin_co[ $x['pin'] ] = $x['ten']; } }

		/* Lọc theo cơ sở — anh Thắng: *"nếu dữ liệu lỗi, cho anh kéo riêng từng cơ sở… cho
		   nhanh"*. Kéo cả chuỗi mà một cửa hàng có dòng hỏng thì phải soi cả sổ mới biết hỏng ở
		   đâu; kéo từng cơ sở thì mỗi lượt là một danh sách ngắn, sai chỗ nào thấy ngay. */
		$coso = trim( (string) $coso );
		/* Vai trò mặc định cho dòng KHÔNG đọc ra được vai trò. Để rỗng thì rơi về 'Nhân viên' —
		   bậc thấp nhất, tức là nạp xong KHÔNG AI đăng nhập được. Đó là hỏng im lặng, nên phải
		   ĐẾM lại và kể ra ở màn hình. */
		if ( ! in_array( $vt_mac_dinh, VHCC_Auth::VAI_TRO_TAT_CA, true ) ) { $vt_mac_dinh = 'Nhân viên'; }
		$lech = 0; $them = 0; $bo = array(); $yeu = array(); $ten_moi = array(); $vt_trong = 0;

		foreach ( (array) $nguoi as $x ) {
			$x   = (array) $x;
			$ten = trim( (string) ( isset( $x['ten'] ) ? $x['ten'] : '' ) );
			$pin = trim( (string) ( isset( $x['pin'] ) ? $x['pin'] : '' ) );
			$cs  = trim( (string) ( isset( $x['coso'] ) ? $x['coso'] : '' ) );
			if ( '' === $ten ) { continue; }
			if ( '' !== $coso && ! self::cung_coso( $cs, $coso ) ) { $lech++; continue; }
			/* Cổng đăng nhập đòi 4–8 chữ số. Nạp về một PIN ngoài khuôn đó thì người ta ngồi gõ
			   mãi một thứ vốn không bao giờ khớp — nói thẳng tên ra. */
			if ( ! preg_match( '/^\d{4,8}$/', $pin ) ) {
				$bo[] = $ten . ( '' === $pin ? ': chưa có PIN'
					: ': PIN ' . mb_strlen( $pin ) . ' ký tự, cổng đăng nhập đòi 4–8 chữ số' );
				continue;
			}
			if ( isset( $pin_co[ $pin ] ) ) {
				/* Cùng PIN mà KHÁC TÊN là hai người chung một PIN — nhật ký không phân biệt được
				   ai làm việc gì. Phải kêu lên. Cùng tên thì chỉ là nạp lại, im lặng bỏ qua. */
				if ( $pin_co[ $pin ] !== $ten ) {
					$bo[] = $ten . ': PIN trùng với ' . $pin_co[ $pin ] . ' đã có trong danh sách';
				}
				continue;
			}
			$vt = isset( $x['vaiTro'] ) ? (string) $x['vaiTro'] : '';
			if ( ! in_array( $vt, VHCC_Auth::VAI_TRO_TAT_CA, true ) ) { $vt = $vt_mac_dinh; $vt_trong++; }
			if ( '' !== self::pin_hop_le( $pin ) ) { $yeu[] = $ten; }
			$pin_co[ $pin ] = $ten;
			$ten_moi[]      = $ten;
			$them++;
			if ( $chi_xem ) { continue; }
			$ds[] = array( 'id' => bin2hex( random_bytes( 8 ) ), 'ten' => $ten, 'pin' => $pin,
				'vaiTro' => $vt, 'coso' => ( '' !== $cs ? $cs : $coso ) );
		}
		if ( ! $chi_xem && $them ) { update_option( self::O, $ds, false ); }
		return array( 'ok' => true, 'them' => $them, 'bo' => $bo, 'yeu' => $yeu, 'ten' => $ten_moi,
			'tong' => count( (array) $nguoi ), 'coso' => $coso, 'lech' => $lech,
			'vt_trong' => $vt_trong, 'vt_mac_dinh' => $vt_mac_dinh,
			'vao' => self::so_vao_duoc( $chi_xem ? null : self::ds() ) );
	}

	/**
	 * VAI TRÒ của sổ cũ -> vai trò ở đây. Nhận cả mã hoa của app gốc lẫn chữ người gõ tay.
	 *
	 * Sổ cũ gõ tay nên cùng một vai trò ra nhiều kiểu: `QUAN_LY`, `Quản lý`, `quanly`, `QL`.
	 * Không nhận ra thì rơi về 'Nhân viên' — bậc THẤP NHẤT. KHÔNG đoán lên cao: đoán nhầm lên
	 * Admin là mở toàn bộ bảng lương cho một dòng gõ sai chính tả.
	 */
	public static function doc_vai_tro( $tho ) {
		$biet = self::vai_tro_biet( $tho );
		return '' !== $biet ? $biet : 'Nhân viên';
	}

	/** Như trên nhưng trả '' khi KHÔNG nhận ra — để đoán xem cột nào là cột Vai trò. */
	public static function vai_tro_biet( $tho ) {
		$t = mb_strtolower( trim( (string) $tho ), 'UTF-8' );
		$k = preg_replace( '/[^a-z0-9]+/u', '', self::bo_dau( $t ) );
		$ban_do = array(
			'admin'         => 'Admin',
			'quanly'        => 'Quản lý',
			'ql'            => 'Quản lý',
			'ketoan'        => 'Kế toán cá nhân',
			'ketoancanhan'  => 'Kế toán cá nhân',
			'ketoanncc'     => 'Kế toán NCC',
			'cuahangtruong' => 'Cửa hàng trưởng',
			'cht'           => 'Cửa hàng trưởng',
			'nhanvien'      => 'Nhân viên',
			'nv'            => 'Nhân viên',
		);
		return isset( $ban_do[ $k ] ) ? $ban_do[ $k ] : '';
	}

	/** Bỏ dấu tiếng Việt — chỉ để SO SÁNH, không bao giờ để lưu hay hiện ra. */
	public static function bo_dau( $s ) {
		$n = array(
			'a' => 'àáạảãâầấậẩẫăằắặẳẵ', 'e' => 'èéẹẻẽêềếệểễ', 'i' => 'ìíịỉĩ',
			'o' => 'òóọỏõôồốộổỗơờớợởỡ', 'u' => 'ùúụủũưừứựửữ', 'y' => 'ỳýỵỷỹ', 'd' => 'đ',
		);
		$s = mb_strtolower( (string) $s, 'UTF-8' );
		foreach ( $n as $thay => $bo ) {
			foreach ( preg_split( '//u', $bo, -1, PREG_SPLIT_NO_EMPTY ) as $c ) {
				$s = str_replace( $c, $thay, $s );
			}
		}
		return $s;
	}

	/**
	 * DÁN THẲNG TỪ GOOGLE SHEETS — đường nạp KHÔNG cần cầu nối Apps Script.
	 *
	 * 🔴 Vì sao có đường này: anh Thắng *"nếu dữ liệu lỗi, cho anh kéo riêng từng cơ sở (bao gồm
	 *    tên đăng nhập và PIN) là dữ liệu chấm công cũ cho nhanh"*, và *"không chạy qua app
	 *    script nữa"*. Đường kéo qua cầu nối còn đó, nhưng nó phụ thuộc app gốc còn sống, còn
	 *    đúng WEB_KEY, còn đúng bản Deploy. Bôi đen mấy dòng trong Sheet rồi dán vào đây thì
	 *    không phụ thuộc cái gì cả — và đúng "riêng từng cơ sở" vì anh chỉ bôi đen cơ sở đó.
	 *
	 * ⚠️ ĐOÁN CỘT THEO NỘI DUNG, không bắt đúng thứ tự. Sổ mỗi cơ sở một kiểu; bắt người ta sắp
	 *    lại cột trước khi dán là mời gõ tay lại từ đầu. Cột nào phần lớn là 4–8 chữ số thì đó
	 *    là PIN; cột chữ dài nhất còn lại là họ tên.
	 */
	public static function nap_dan( $van_ban, $chi_xem = true, $coso = '', $vt_mac_dinh = '' ) {
		$dong = preg_split( '/\r\n|\r|\n/', (string) $van_ban );
		$o    = array();
		foreach ( $dong as $d ) {
			if ( '' === trim( $d ) ) { continue; }
			/* Dán từ Sheets ra TAB. Người gõ tay hay dùng dấu phẩy hoặc chấm phẩy. */
			if ( false !== strpos( $d, "\t" ) )     { $c = explode( "\t", $d ); }
			elseif ( false !== strpos( $d, ';' ) )  { $c = explode( ';', $d ); }
			elseif ( false !== strpos( $d, ',' ) )  { $c = explode( ',', $d ); }
			else                                    { $c = preg_split( '/\s{2,}/', trim( $d ) ); }
			$o[] = array_map( 'trim', $c );
		}
		if ( ! count( $o ) ) {
			return array( 'ok' => false, 'error' => 'Chưa dán gì vào ô.' );
		}

		$so_cot = 0;
		foreach ( $o as $h ) { $so_cot = max( $so_cot, count( $h ) ); }
		if ( $so_cot < 2 ) {
			return array( 'ok' => false, 'error' => 'Mỗi dòng phải có ÍT NHẤT 2 cột (họ tên và PIN). '
				. 'Bôi đen cả hai cột trong Google Sheets rồi Ctrl+C, dán thẳng vào đây — '
				. 'Sheets tự chèn dấu Tab giữa các cột.' );
		}

		/* Dòng đầu là tiêu đề thì bỏ đi — chữ "PIN", "Họ tên"… không phải một người. */
		$dau = self::doc_tieu_de( $o[0] );
		if ( null !== $dau ) { array_shift( $o ); }
		if ( ! count( $o ) ) {
			return array( 'ok' => false, 'error' => 'Chỉ có dòng tiêu đề, chưa có dòng dữ liệu nào.' );
		}

		$cot = ( null !== $dau ) ? $dau : self::doan_cot( $o, $so_cot );
		if ( ! isset( $cot['pin'] ) ) {
			return array( 'ok' => false, 'error' => 'Không nhận ra cột nào là PIN. '
				. 'PIN phải là 4–8 CHỮ SỐ. Nếu Google Sheets đang cắt số 0 ở đầu (0123 -> 123) thì '
				. 'định dạng cột đó thành Văn bản rồi chép lại, hoặc thêm dòng tiêu đề có chữ "PIN".' );
		}
		if ( ! isset( $cot['ten'] ) ) {
			return array( 'ok' => false, 'error' => 'Không nhận ra cột nào là HỌ TÊN. '
				. 'Thêm một dòng tiêu đề có chữ "Họ tên" rồi dán lại.' );
		}

		$lay = function ( $h, $i ) { return ( null !== $i && isset( $h[ $i ] ) ) ? $h[ $i ] : ''; };
		$coso  = trim( (string) $coso );
		$nguoi = array();
		foreach ( $o as $h ) {
			if ( isset( $cot['coso'] ) ) {
				$cs = $lay( $h, $cot['coso'] );
			} else {
				/* KHÔNG nhận ra cột Cơ sở (dán không tiêu đề, hoặc bôi đen thiếu cột) mà người
				   dùng lại đang lọc theo cơ sở: tìm tên cơ sở đó ở BẤT KỲ cột nào trong dòng.
				   Không làm vậy thì lọc cơ sở nào cũng ra 0 người, và màn hình chỉ báo "không
				   có ai" — người dùng tưởng sổ trống chứ không nghĩ là thiếu một cột. */
				$cs = '';
				if ( '' !== $coso ) {
					foreach ( $h as $o_cell ) {
						if ( self::cung_coso( $o_cell, $coso ) ) { $cs = $o_cell; break; }
					}
				}
			}
			$nguoi[] = array(
				'ten'    => $lay( $h, $cot['ten'] ),
				'pin'    => preg_replace( '/\.0+$/', '', trim( (string) $lay( $h, $cot['pin'] ) ) ),
				'vaiTro' => self::doc_vai_tro( $lay( $h, isset( $cot['vaiTro'] ) ? $cot['vaiTro'] : null ) ),
				'coso'   => $cs,
			);
		}
		$kq          = self::them_nhieu( $nguoi, $chi_xem, $coso, $vt_mac_dinh );
		$kq['cot']   = $cot;
		$kq['tieude'] = ( null !== $dau );
		return $kq;
	}

	/**
	 * Dòng này có phải TIÊU ĐỀ không. Trả bản đồ cột, hoặc null nếu không phải tiêu đề.
	 *
	 * Có tiêu đề thì tin tiêu đề — chắc hơn mọi phép đoán theo nội dung.
	 */
	public static function doc_tieu_de( $h ) {
		$cot = array();
		foreach ( (array) $h as $i => $o ) {
			$k = preg_replace( '/[^a-z0-9]+/u', '', self::bo_dau( $o ) );
			if ( '' === $k ) { continue; }
			if ( ! isset( $cot['pin'] ) && ( 'pin' === $k || 'matkhau' === $k ) ) { $cot['pin'] = $i; continue; }
			if ( ! isset( $cot['ten'] ) && in_array( $k, array( 'hoten', 'ten', 'hovaten',
				'tendangnhap', 'nhanvien', 'tennhanvien' ), true ) ) { $cot['ten'] = $i; continue; }
			if ( ! isset( $cot['vaiTro'] ) && in_array( $k, array( 'vaitro', 'quyen', 'chucvu' ), true ) ) {
				$cot['vaiTro'] = $i; continue;
			}
			if ( ! isset( $cot['coso'] ) && in_array( $k, array( 'coso', 'cuahang', 'chinhanh',
				'diadiem' ), true ) ) { $cot['coso'] = $i; continue; }
		}
		/* Phải nhận ra CẢ HAI cột bắt buộc mới coi là tiêu đề. Nhận nửa vời rồi bỏ dòng đó đi là
		   mất một người mà không ai biết. */
		return ( isset( $cot['pin'] ) && isset( $cot['ten'] ) ) ? $cot : null;
	}

	/** Không có tiêu đề thì ĐOÁN THEO NỘI DUNG từng cột. */
	public static function doan_cot( $o, $so_cot ) {
		$diem_pin = array(); $diem_ten = array(); $diem_vt = array();
		for ( $i = 0; $i < $so_cot; $i++ ) {
			$pin = 0; $ten = 0; $vt = 0;
			foreach ( $o as $h ) {
				$v = isset( $h[ $i ] ) ? trim( (string) $h[ $i ] ) : '';
				if ( '' === $v ) { continue; }
				if ( preg_match( '/^\d{4,8}(\.0+)?$/', $v ) ) { $pin++; continue; }
				/* Cột VAI TRÒ đoán được nhờ nó có TỪ VỰNG đóng: `QUAN_LY`, `Kế toán`, `NHAN_VIEN`…
				   Không đoán cột này thì dán không tiêu đề là mọi người thành 'Nhân viên' và
				   KHÔNG AI đăng nhập được — mà màn hình vẫn báo "đã nạp N người". */
				if ( '' !== self::vai_tro_biet( $v ) ) { $vt++; continue; }
				if ( preg_match( '/\p{L}\s+\p{L}/u', $v ) ) { $ten++; }   // có khoảng trắng giữa chữ
			}
			$diem_pin[ $i ] = $pin;
			$diem_ten[ $i ] = $ten;
			$diem_vt[ $i ]  = $vt;
		}
		$cot = array();
		arsort( $diem_pin );
		$i_pin = key( $diem_pin );
		if ( $diem_pin[ $i_pin ] > 0 ) { $cot['pin'] = $i_pin; }
		arsort( $diem_ten );
		foreach ( $diem_ten as $i => $d ) {
			if ( $d > 0 && ( ! isset( $cot['pin'] ) || $i !== $cot['pin'] ) ) { $cot['ten'] = $i; break; }
		}
		arsort( $diem_vt );
		foreach ( $diem_vt as $i => $d ) {
			if ( $d > 0 && $i !== ( isset( $cot['pin'] ) ? $cot['pin'] : -1 )
				&& $i !== ( isset( $cot['ten'] ) ? $cot['ten'] : -1 ) ) { $cot['vaiTro'] = $i; break; }
		}
		return $cot;
	}

	/**
	 * Hai tên cơ sở có phải một không.
	 *
	 * Sổ cũ gõ tay nên cùng một cửa hàng ra nhiều kiểu: `TUTU_BT`, `tutu bt`, ` TuTu-BT `. So
	 * bằng `===` thì kéo riêng cơ sở nào cũng ra 0 người, mà màn hình chỉ báo "không có ai" —
	 * người dùng tưởng sổ trống chứ không nghĩ là so tên hỏng.
	 */
	public static function cung_coso( $a, $b ) {
		$rua = function ( $x ) {
			$x = mb_strtolower( trim( (string) $x ), 'UTF-8' );
			return preg_replace( '/[^a-z0-9]+/u', '', $x );
		};
		return $rua( $a ) === $rua( $b ) && '' !== $rua( $b );
	}

	/**
	 * Các cơ sở có trong một kho cũ, kèm số người và số người vào được.
	 *
	 * Người KHÔNG khai cơ sở gom vào khoá '' — vẫn phải hiện ra, không thì kéo hết từng cơ sở
	 * xong vẫn thiếu người mà không biết thiếu ai.
	 */
	public static function ds_coso_cu( $tu ) {
		$u = self::doc_kho( (string) $tu );
		if ( is_wp_error( $u ) ) { return array(); }
		$cho = VHCC_Auth::vai_tro_vao();
		$ra  = array();
		foreach ( $u as $x ) {
			if ( '' === trim( (string) $x['ten'] ) ) { continue; }
			$cs = trim( (string) $x['coso'] );
			if ( ! isset( $ra[ $cs ] ) ) { $ra[ $cs ] = array( 'co' => 0, 'vao' => 0, 'pin' => 0 ); }
			$ra[ $cs ]['co']++;
			if ( preg_match( '/^\d{4,8}$/', trim( (string) $x['pin'] ) ) ) { $ra[ $cs ]['pin']++; }
			if ( '' !== $x['pin'] && in_array( $x['vaiTro'], $cho, true ) ) { $ra[ $cs ]['vao']++; }
		}
		ksort( $ra );
		return $ra;
	}

	/**
	 * MỞ MỘT ĐƯỜNG VÀO LÚC CÀI — chạy đúng một lần.
	 *
	 * Cài xong mà chưa ai đăng nhập được thì trang chấm công đứng ở cổng PIN với dòng "Chưa có
	 * tài khoản nào đăng nhập được", và KHÔNG có đường nào tự mở ngoài sửa thẳng database. Đúng
	 * thứ anh Thắng gặp.
	 *
	 * 🔴 THỨ TỰ QUAN TRỌNG — *"pin nằm ở dữ liệu cũ chứ"*:
	 *    1. Đã có người vào được -> không đụng gì.
	 *    2. NẠP SỔ PIN CŨ (sổ Phân quyền của app gốc) sang danh sách riêng. Mọi người đăng nhập
	 *       bằng ĐÚNG PIN họ vẫn dùng, không phải cấp lại lần hai.
	 *    3. Chỉ khi vẫn không ai vào được mới khai một Admin với PIN ngẫu nhiên. Đây là ĐƯỜNG
	 *       CÙNG, không phải cách làm chính.
	 *
	 * ⚠️ KHÔNG tự nạp từ plugin Vận Hành Chi Phí. Anh Thắng: *"bên chi phí không liên quan gì
	 *    chấm công"* — kéo người bên đó sang là tự ý nối lại hai hệ thống anh đã tách. Vẫn nạp
	 *    được, nhưng phải do người bấm nút ở màn Cài đặt.
	 *
	 * ⚠️ ĐẶT Ở ĐÂY, KHÔNG ĐẶT TRONG class-vhcc-auth.php. Tệp đó là ĐƯỜNG ĐĂNG NHẬP; danh sách
	 *    PIN cấm không được có mặt ở đó, kẻo có ngày ai đó lỡ đem nó ra chặn lúc đăng nhập —
	 *    khoá người ta ra khỏi hệ thống của chính họ bằng một PIN họ đang dùng thật. Có phép
	 *    thử ghim đúng điều này.
	 *
	 * @return string '' = không làm gì · 'nap' = nạp từ sổ cũ · 'gieo' = phải khai tài khoản mới.
	 */
	public static function mo_duong_vao() {
		if ( get_option( 'vhcc_da_mo_duong' ) ) { return ''; }
		update_option( 'vhcc_da_mo_duong', 1 );

		if ( self::co_ai_vao_duoc() ) { return ''; }

		/* Bước 2 — đi tìm sổ PIN cũ TRƯỚC khi bịa ra PIN mới. Hồ sơ Nhân sự trước (chỗ file .csv
		   nhân viên đổ vào), rồi tới sổ Phân quyền của app gốc. */
		$nap = array( 'ok' => true, 'them' => 0, 'yeu' => array(), 'bo' => array() );
		foreach ( array( 'ho_so', 'app' ) as $tu_cu ) {
			$thu = self::nap_tu_cu( $tu_cu, false );
			if ( empty( $thu['ok'] ) ) { continue; }
			$nap['them'] += (int) $thu['them'];
			$nap['yeu']   = array_merge( $nap['yeu'], (array) $thu['yeu'] );
			$nap['bo']    = array_merge( $nap['bo'], (array) $thu['bo'] );
		}
		if ( $nap['them'] > 0 ) {
			self::doi_sang_rieng();
			update_option( 'vhcc_mo_duong_nap', array( 'so' => (int) $nap['them'],
				'yeu' => $nap['yeu'], 'bo' => $nap['bo'] ) );
			/* Nạp về mà không ai mang vai trò được vào (sổ cũ phần lớn là Nhân viên / Cửa hàng
			   trưởng) thì vẫn tắc — rơi xuống bước 3. Nhưng người đã nằm sẵn trong danh sách,
			   nên chỉ cần tích thêm vai trò là xong, khỏi gõ tay 26 cửa hàng. */
			if ( self::so_vao_duoc() > 0 ) { return 'nap'; }
		}

		/* Bước 3 — ĐƯỜNG CÙNG: khai một Admin với PIN ngẫu nhiên, hiện ĐÚNG MỘT LẦN ở wp-admin.
		   KHÔNG dùng PIN cố định kiểu 1111: ai đọc được mã nguồn là vào được mọi bản cài. */
		$pin = self::pin_ngau_nhien();
		self::doi_sang_rieng();
		$ds   = (array) get_option( self::O );
		$ds[] = array( 'id' => md5( 'admin-lan-dau' ), 'ten' => 'Admin', 'pin' => $pin,
			'vaiTro' => 'Admin', 'coso' => '' );
		update_option( self::O, $ds );
		update_option( 'vhcc_pin_lan_dau', $pin );          // để wp-admin hiện một lần
		return 'gieo';
	}

	/**
	 * KHAI MỘT TÀI KHOẢN ADMIN TOÀN QUYỀN, PIN sinh ngẫu nhiên.
	 *
	 * Anh Thắng: *"khai luôn tk admin để toàn quyền"*. Khác `mo_duong_vao()` ở chỗ đây là việc
	 * CÓ NGƯỜI BẤM, làm được nhiều lần, không phụ thuộc cờ chạy-một-lần.
	 *
	 * 🔴 PIN trả về ĐÚNG MỘT LẦN cho chỗ gọi in ra, và KHÔNG lưu vào option nào. Lưu lại để
	 *    "xem lại sau" là để một PIN Admin còn dùng được nằm sẵn trong cơ sở dữ liệu — ai đọc
	 *    được database là vào thẳng. Quên thì khai cái mới, đừng dựng đường xem lại.
	 */
	public static function khai_admin( $ten = '' ) {
		$ten = trim( (string) $ten );
		if ( '' === $ten ) { $ten = 'Admin'; }
		/* Trùng tên thì nối số — hai người cùng tên trong nhật ký là không phân biệt được ai. */
		$dang_co = array();
		foreach ( self::ds() as $u ) { $dang_co[ mb_strtolower( $u['ten'], 'UTF-8' ) ] = 1; }
		if ( isset( $dang_co[ mb_strtolower( $ten, 'UTF-8' ) ] ) ) {
			$i = 2;
			while ( isset( $dang_co[ mb_strtolower( $ten . ' ' . $i, 'UTF-8' ) ] ) ) { $i++; }
			$ten = $ten . ' ' . $i;
		}

		/* PIN phải chưa ai dùng — hai người cùng PIN thì nhật ký không biết ai làm việc gì. */
		$dung_roi = array();
		foreach ( self::ds() as $u ) { if ( '' !== $u['pin'] ) { $dung_roi[ $u['pin'] ] = 1; } }
		$pin = '';
		for ( $lan = 0; $lan < 200; $lan++ ) {
			$thu = self::pin_ngau_nhien();
			if ( ! isset( $dung_roi[ $thu ] ) ) { $pin = $thu; break; }
		}
		if ( '' === $pin ) {
			return array( 'ok' => false, 'error' => 'Không bốc được PIN chưa ai dùng. '
				. 'Danh sách đang quá dày PIN 6 số — xoá bớt tài khoản cũ rồi thử lại.' );
		}

		/* Nguồn phải là "danh sách riêng", không thì tài khoản vừa khai không đăng nhập được. */
		self::doi_sang_rieng();
		$ds   = (array) get_option( self::O );
		$ds[] = array( 'id' => bin2hex( random_bytes( 8 ) ), 'ten' => $ten, 'pin' => $pin,
			'vaiTro' => 'Admin', 'coso' => '' );
		update_option( self::O, $ds, false );
		return array( 'ok' => true, 'ten' => $ten, 'pin' => $pin );
	}

	/** PIN 6 số ngẫu nhiên, bốc lại nếu rơi vào danh sách bị chặn. */
	protected static function pin_ngau_nhien() {
		do {
			$pin = '';
			for ( $i = 0; $i < 6; $i++ ) { $pin .= (string) wp_rand( 0, 9 ); }
		} while ( '' !== self::pin_hop_le( $pin ) );
		return $pin;
	}

	/**
	 * Chuyển nguồn sang "danh sách riêng" — vì tài khoản vừa nạp/khai nằm ở đó; để nguyên nguồn
	 * cũ thì cổng vẫn đọc kho bên kia và PIN mới vẫn không vào được.
	 *
	 * GHI LẠI nguồn cũ: đổi cấu hình sau lưng người dùng mà không kể lại là cách chắc nhất để
	 * nửa năm sau không ai hiểu vì sao danh sách người dùng "biến mất".
	 */
	protected static function doi_sang_rieng() {
		$cu = VHCC_Auth::nguon();
		if ( 'rieng' === $cu ) { return; }
		update_option( 'vhcc_nguon_nguoidung', 'rieng' );
		update_option( 'vhcc_gieo_doi_nguon', $cu );
	}

	/** Nguồn người dùng TRƯỚC lúc mở đường, '' nếu không phải đổi. Chỉ để màn Cài đặt kể lại. */
	public static function gieo_doi_nguon() { return (string) get_option( 'vhcc_gieo_doi_nguon' ); }

	/** Báo cáo lượt nạp tự động lúc cài: ['so','yeu','bo'] — hoặc [] nếu không có. */
	public static function mo_duong_nap() { return (array) get_option( 'vhcc_mo_duong_nap', array() ); }

	/** PIN lần đầu (chỉ wp-admin đọc); '' khi quản trị đã bấm "tôi ghi lại rồi". */
	public static function pin_lan_dau() { return (string) get_option( 'vhcc_pin_lan_dau' ); }
	public static function quen_pin_lan_dau() {
		delete_option( 'vhcc_pin_lan_dau' );
		delete_option( 'vhcc_gieo_doi_nguon' );
		delete_option( 'vhcc_mo_duong_nap' );
	}

	/** Danh sách đã chuẩn hoá: [ ['id','ten','pin','vaiTro','coso'], … ] */
	public static function ds() {
		$tho = get_option( self::O );
		$ra  = array();
		foreach ( (array) $tho as $u ) {
			$u   = (array) $u;
			$ten = trim( (string) ( isset( $u['ten'] ) ? $u['ten'] : '' ) );
			if ( '' === $ten ) { continue; }
			$ra[] = array(
				'id'     => (string) ( isset( $u['id'] ) ? $u['id'] : md5( $ten ) ),
				'ten'    => $ten,
				'pin'    => (string) ( isset( $u['pin'] ) ? $u['pin'] : '' ),
				'vaiTro' => (string) ( isset( $u['vaiTro'] ) ? $u['vaiTro'] : 'Kế toán cá nhân' ),
				'coso'   => (string) ( isset( $u['coso'] ) ? $u['coso'] : '' ),
			);
		}
		return $ra;
	}

	/** Bao nhiêu người trong danh sách này VÀO ĐƯỢC (vai trò nằm trong danh sách cho vào). */
	public static function so_vao_duoc( $ds = null ) {
		$ds  = ( null === $ds ) ? self::ds() : $ds;
		$cho = VHCC_Auth::vai_tro_vao();
		$n   = 0;
		foreach ( $ds as $u ) { if ( in_array( $u['vaiTro'], $cho, true ) ) { $n++; } }
		return $n;
	}

	/**
	 * Thêm hoặc sửa một người.
	 *
	 * @param string $id     rỗng = thêm mới.
	 * @param string $pin    rỗng KHI SỬA = giữ PIN cũ (vì màn hình không hiện PIN cũ ra).
	 */
	public static function luu( $id, $ten, $pin, $vai_tro, $coso ) {
		$id      = trim( (string) $id );
		$ten     = trim( (string) $ten );
		$pin     = trim( (string) $pin );
		$vai_tro = trim( (string) $vai_tro );
		$coso    = trim( (string) $coso );

		if ( '' === $ten ) { return array( 'ok' => false, 'error' => 'Thiếu họ tên.' ); }
		if ( ! in_array( $vai_tro, VHCC_Auth::VAI_TRO_TAT_CA, true ) ) {
			return array( 'ok' => false, 'error' => 'Vai trò không hợp lệ.' );
		}

		$ds  = self::ds();
		$cu  = null;
		foreach ( $ds as $u ) { if ( '' !== $id && $u['id'] === $id ) { $cu = $u; break; } }
		if ( '' !== $id && null === $cu ) {
			return array( 'ok' => false, 'error' => 'Không thấy người cần sửa (đã bị xoá?).' );
		}

		/* Sửa mà để trống ô PIN = giữ PIN cũ. Bắt gõ lại PIN mỗi lần đổi tên là mời người ta
		   đặt một PIN dễ nhớ hơn cho đỡ phiền. */
		if ( '' === $pin && null !== $cu ) { $pin = $cu['pin']; }

		$loi = self::pin_hop_le( $pin );
		if ( '' !== $loi ) { return array( 'ok' => false, 'error' => $loi ); }

		foreach ( $ds as $u ) {
			if ( $u['pin'] === $pin && ( null === $cu || $u['id'] !== $cu['id'] ) ) {
				return array( 'ok' => false, 'error' => 'PIN này đã cấp cho ' . $u['ten']
					. '. Hai người cùng PIN thì nhật ký không phân biệt được ai làm việc gì.' );
			}
		}

		if ( null === $cu ) {
			$ds[] = array( 'id' => bin2hex( random_bytes( 8 ) ), 'ten' => $ten, 'pin' => $pin,
				'vaiTro' => $vai_tro, 'coso' => $coso );
		} else {
			foreach ( $ds as $i => $u ) {
				if ( $u['id'] === $cu['id'] ) {
					$ds[ $i ] = array( 'id' => $cu['id'], 'ten' => $ten, 'pin' => $pin,
						'vaiTro' => $vai_tro, 'coso' => $coso );
				}
			}
		}
		update_option( self::O, $ds, false );
		return array( 'ok' => true, 'thong_bao' => ( null === $cu ? 'Đã thêm ' : 'Đã sửa ' ) . $ten . '.' );
	}

	/** PIN dùng cho cổng đăng nhập trang chấm công: 4–8 chữ số (đúng khuôn `VHCC_Auth::login`). */
	public static function pin_hop_le( $pin ) {
		$pin = trim( (string) $pin );
		if ( ! preg_match( '/^\d{4,8}$/', $pin ) ) {
			return 'PIN phải là 4–8 CHỮ SỐ. (Số 0 ở đầu vẫn tính — nhưng nếu chép từ Google Sheets '
				. 'thì kiểm lại, Sheets coi 0123 là số nên lưu thành 123.)';
		}
		if ( in_array( $pin, self::pin_bi_cam(), true ) ) {
			return 'PIN này nằm trong danh sách bị chặn: hoặc quá dễ đoán, hoặc đã bị lộ.';
		}
		if ( preg_match( '/^(\d)\1+$/', $pin ) ) {
			return 'PIN không được là một chữ số lặp lại (1111, 222222…).';
		}
		if ( false !== strpos( '012345678901234567890', $pin )
			|| false !== strpos( '098765432109876543210', $pin ) ) {
			return 'PIN không được là dãy liên tiếp (1234, 654321…).';
		}
		return '';
	}

	/**
	 * Xoá một người.
	 *
	 * ⚠️ Chặn khi đó là NGƯỜI CUỐI CÙNG còn vào được — xoá xong thì không ai đăng nhập nổi, và
	 *    đường lùi duy nhất là sửa thẳng cơ sở dữ liệu.
	 */
	public static function xoa( $id ) {
		$id = trim( (string) $id );
		$ds = self::ds();
		$ai = null;
		foreach ( $ds as $u ) { if ( $u['id'] === $id ) { $ai = $u; break; } }
		if ( null === $ai ) { return array( 'ok' => false, 'error' => 'Không thấy dòng cần xoá.' ); }

		$cho = VHCC_Auth::vai_tro_vao();
		if ( in_array( $ai['vaiTro'], $cho, true ) && self::so_vao_duoc( $ds ) <= 1 ) {
			return array( 'ok' => false, 'error' => 'Đây là người CUỐI CÙNG còn vào được hệ thống. '
				. 'Xoá là không ai đăng nhập nổi nữa, mà đường lùi duy nhất là sửa thẳng cơ sở dữ liệu. '
				. 'Thêm người khác trước đã.' );
		}

		$moi = array();
		foreach ( $ds as $u ) { if ( $u['id'] !== $id ) { $moi[] = $u; } }
		update_option( self::O, $moi, false );
		return array( 'ok' => true, 'thong_bao' => 'Đã xoá ' . $ai['ten'] . '.' );
	}
}
