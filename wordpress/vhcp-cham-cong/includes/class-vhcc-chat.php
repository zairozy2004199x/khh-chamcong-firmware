<?php
/**
 * CHAT NHÓM THEO CƠ SỞ — chỗ nhắn nhau của những người cùng làm một nơi.
 *
 * =================================================================================================
 * Anh Thắng 20/09/2026: *"chat nhóm trên thành viên trên áp apk"*.
 * =================================================================================================
 *
 * 🔴 PHÒNG LÀ CƠ SỞ, VÀ KHÔNG AI TỰ TẠO PHÒNG.
 * Cám dỗ đầu tiên là làm "tạo nhóm" như Zalo: ai cũng lập nhóm, mời ai tuỳ ý. Đừng. Trong một
 * chuỗi cửa hàng, thứ người ta thật sự cần là nhắn cho những người ĐANG CÙNG LÀM MỘT CHỖ — ca
 * sau nhắn ca trước, cửa hàng trưởng dặn cả cửa hàng. Cho tự lập nhóm thì ba tháng sau có bốn
 * mươi cái nhóm chết, người mới vào không biết nhóm nào còn sống, và không ai dọn.
 *
 * Phòng theo cơ sở thì DANH SÁCH THÀNH VIÊN TỰ ĐÚNG: nghỉ việc là ra khỏi phòng, chuyển cơ sở là
 * đổi phòng, không ai phải đi mời hay đi đuổi ai.
 *
 * 🔴 AI ĐƯỢC VÀO PHÒNG — HỎI ĐÚNG CÁI GÁC ĐÃ CÓ, KHÔNG DỰNG GÁC MỚI.
 * `VHCC_NhanSu::co_quyen_coso()` (phụ trách) hoặc `co_cham_coso()` (có chấm công ở đó) — đúng cặp
 * mà danh bạ dùng. Dựng một phép gác riêng cho chat là sớm muộn hai phép lệch nhau, và lệch theo
 * hướng nào cũng sai: chặt hơn thì người đi làm thật không vào được, lỏng hơn thì người cơ sở
 * khác đọc được chuyện nội bộ của cửa hàng này.
 *
 * ⚠️ GÁC CẢ LÚC ĐỌC, KHÔNG CHỈ LÚC GỬI. Gác mỗi lúc gửi là ai gõ đúng tên cơ sở cũng đọc được cả
 *    lịch sử — mà tên cơ sở thì in đầy trên mọi màn.
 *
 * 🔴 TÊN PHÒNG KHÔNG BAO GIỜ LẤY TỪ CLIENT MÀ KHÔNG ĐỐI CHIẾU. Nó đi qua `chuan_coso()` rồi qua
 *    phép gác trên, y như `VHCC_Online::cham_cong()` làm với cơ sở của lượt chấm.
 *
 * ⚠️ TIN NHẮN LÀ CHỮ, KHÔNG PHẢI HTML. Cất nguyên văn, thoát lúc hiện. Cất bản đã thoát là tới
 *    lúc xuất ra chỗ khác (hay đọc lại bằng mắt) thì thấy `&amp;` giữa câu.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_Chat {

	/** Dài nhất một tin. Dài hơn thì đó là một cái tài liệu, không phải một câu nhắn. */
	const DAI_TOI_DA = 1000;

	/** Một lượt đọc lấy nhiều nhất bấy nhiêu tin. */
	const MOI_LUOT = 80;

	/**
	 * Tự xoá được tin của CHÍNH MÌNH trong bấy nhiêu phút.
	 *
	 * ⚠️ CÓ HẠN GIỜ, và hạn ngắn. Gõ nhầm rồi xoá ngay là chuyện thường. Nhưng xoá được tin của
	 *    ba tuần trước thì cái phòng này thôi làm được việc quan trọng nhất của nó: làm chứng
	 *    cho một câu dặn. Người ta sẽ chối "tôi có nhắn đâu".
	 */
	const PHUT_XOA = 15;

	/**
	 * Dấu ngăn trong khoá phòng riêng. Chọn ký tự KHÔNG bao giờ có trong tên cơ sở hay mã NV.
	 *
	 * ⚠️ Nếu một ngày nào đó nó lọt vào được thì `tach_rieng()` đọc lệch và hai người nói chuyện
	 *    với nhau qua hai cái phòng khác nhau. Nên `phong_rieng()` CHỐI thẳng khi thấy nó, chứ
	 *    không cắt bỏ cho êm: cắt bỏ là hai mã khác nhau ra cùng một khoá.
	 */
	const NGAN = '|';

	/** Tiền tố của phòng RIÊNG (1-1), để phân biệt với phòng cơ sở. */
	const DAU_RIENG = '@';

	/* ============================================================================ phòng */

	/* ═════════════════════════════════════════════════════════════════════════════════════
	 * CHAT RIÊNG HAI NGƯỜI — anh Thắng 20/09/2026: *"Chọn thành viên cùng cửa hàng và chat"*.
	 * ═════════════════════════════════════════════════════════════════════════════════════
	 * 🔴 KHOÁ PHÒNG PHẢI KHÔNG PHỤ THUỘC THỨ TỰ. A mở chat với B và B mở chat với A phải rơi vào
	 *    ĐÚNG MỘT phòng. Ghép theo thứ tự người gọi (`nguoi_mo|nguoi_kia`) là dựng ra hai phòng,
	 *    mỗi người thấy một nửa cuộc nói chuyện — và cả hai đều tưởng người kia không trả lời.
	 *    Nên hai mã được SẮP XẾP trước khi ghép.
	 *
	 * 🔴 VÀ PHẢI KÈM CƠ SỞ. Không kèm thì hai người từng làm chung, nay mỗi người một nơi, vẫn
	 *    dùng chung cái phòng cũ — tức là một kênh riêng nằm ngoài mọi phép gác theo cơ sở.
	 *    Kèm cơ sở thì phòng riêng cũng chịu đúng cái gác của phòng chung.
	 * ═════════════════════════════════════════════════════════════════════════════════════ */

	/** Khoá phòng riêng giữa hai người tại một cơ sở. '' khi không dựng được. */
	public static function phong_rieng( $coso, $ma_a, $ma_b ) {
		$c = VHCC_NhanSu::chuan_coso( (string) $coso );
		$a = trim( (string) $ma_a );
		$b = trim( (string) $ma_b );
		if ( '' === $c || '' === $a || '' === $b ) { return ''; }
		/* Tự nhắn cho mình thì không phải một cuộc nói chuyện. */
		if ( 0 === strcasecmp( $a, $b ) ) { return ''; }
		foreach ( array( $c, $a, $b ) as $x ) {
			if ( false !== strpos( $x, self::NGAN ) ) { return ''; }
		}
		$hai = array( $a, $b );
		sort( $hai, SORT_STRING );
		return self::DAU_RIENG . $c . self::NGAN . $hai[0] . self::NGAN . $hai[1];
	}

	/** Khoá phòng riêng -> array( coso, ma_a, ma_b ), hoặc null nếu không phải phòng riêng. */
	public static function tach_rieng( $phong ) {
		$p = (string) $phong;
		if ( '' === $p || self::DAU_RIENG !== substr( $p, 0, 1 ) ) { return null; }
		$o = explode( self::NGAN, substr( $p, 1 ) );
		if ( 3 !== count( $o ) ) { return null; }
		if ( '' === $o[0] || '' === $o[1] || '' === $o[2] ) { return null; }
		return array( 'coSo' => $o[0], 'a' => $o[1], 'b' => $o[2] );
	}

	/**
	 * DANH SÁCH CUỘC NÓI CHUYỆN RIÊNG đang có của một người, kèm số chưa đọc.
	 *
	 * ⚠️ Chỉ liệt kê phòng ĐÃ CÓ TIN. Liệt kê sẵn mọi người trong danh bạ là một danh sách dài
	 *    toàn dòng trống, và người ta thôi đọc nó. Muốn nhắn cho ai chưa từng nhắn thì bấm sang
	 *    danh bạ — đó là việc của danh bạ.
	 */
	public static function rieng_cua( $u ) {
		global $wpdb;
		$ma = isset( $u['ma_nv'] ) ? trim( (string) $u['ma_nv'] ) : '';
		if ( '' === $ma ) { return array(); }
		$bt = VHCC_DB::t( 'chat_tin' );
		$bd = VHCC_DB::t( 'chat_doc' );
		$br = VHCC_DB::t( 'chat_rieng' );

		/* 🔴 TRA BẰNG CỘT, KHÔNG BẰNG `LIKE` TRÊN KHOÁ PHÒNG. Xem chú thích của bảng
		   `chat_rieng` ở `VHCC_DB`: `LIKE '%|CH_A1%'` khớp luôn `|CH_A12`, và mã NV có dấu gạch
		   dưới thì `esc_like()` cho ra một câu MySQL hiểu còn SQLite thì không. */
		$hang = VHCC_DB::rows( $wpdb->prepare(
			"SELECT * FROM $br WHERE ma_a=%s OR ma_b=%s ORDER BY phong LIMIT 500", $ma, $ma ) );

		$ra = array();
		foreach ( (array) $hang as $h ) {
			$p = (string) $h['phong'];
			if ( ! self::duoc_vao( $u, $p ) ) { continue; }
			$kia = ( 0 === strcmp( (string) $h['ma_a'], $ma ) ) ? (string) $h['ma_b'] : (string) $h['ma_a'];

			$cuoi = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT id_cuoi FROM $bd WHERE ma_nv=%s AND phong=%s", $ma, $p ) );
			$chua = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM $bt WHERE phong=%s AND id>%d AND ma_nv<>%s", $p, $cuoi, $ma ) );
			$tin = $wpdb->get_row( $wpdb->prepare(
				"SELECT ho_ten, chu, tao_luc, ma_nv, da_xoa FROM $bt WHERE phong=%s ORDER BY id DESC LIMIT 1",
				$p ), ARRAY_A );
			/* TÊN NGƯỜI KIA — ba nấc, đúng thứ tự này.
			   1. Tên trong chính mấy tin họ đã gửi: người nghỉ việc hay đổi tên vẫn hiện đúng
			      cái tên lúc nhắn, nên cuộc nói chuyện cũ đọc lại vẫn khớp.
			   2. Chưa nhắn câu nào thì tra hồ sơ.
			   🔴 Bản đầu THIẾU nấc 2, và anh Thắng chụp lại đúng cảnh ấy 21/09/2026: mở một cuộc
			      mình vừa nhắn trước mà người kia chưa trả lời, tiêu đề hiện `MNNV1CTY0002` —
			      một dãy mã, không phải một cái tên. Đó là cảnh THƯỜNG GẶP NHẤT của tính năng
			      này (nhắn trước rồi chờ), chứ không phải trường hợp hiếm. */
			$ten_kia = (string) $wpdb->get_var( $wpdb->prepare(
				"SELECT ho_ten FROM $bt WHERE phong=%s AND ma_nv=%s ORDER BY id DESC LIMIT 1",
				$p, $kia ) );
			if ( '' === $ten_kia ) {
				$ten_kia = (string) $wpdb->get_var( $wpdb->prepare(
					'SELECT ho_ten FROM ' . VHCC_DB::t( 'nhan_vien' ) . ' WHERE ma_nv=%s', $kia ) );
			}

			$ra[] = array(
				'phong'   => $p,
				'coSo'    => (string) $h['coso'],
				'maKia'   => $kia,
				'tenKia'  => ( '' !== $ten_kia ) ? $ten_kia : $kia,
				'chuaDoc' => $chua,
				'cuoi'    => ( $tin && empty( $tin['da_xoa'] ) )
					? ( '' !== (string) $tin['chu'] ? (string) $tin['chu'] : '[tệp đính kèm]' )
					: '',
				'luc'     => $tin ? (string) $tin['tao_luc'] : '',
			);
		}
		return $ra;
	}

	/** Ghi một phòng riêng vào sổ cuộc nói chuyện. Không phải phòng riêng thì thôi. */
	private static function ghi_so_rieng( $phong ) {
		global $wpdb;
		$t = self::tach_rieng( $phong );
		if ( ! $t ) { return; }
		$b = VHCC_DB::t( 'chat_rieng' );
		$co = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $b WHERE phong=%s", $phong ) );
		if ( $co ) { return; }
		$wpdb->insert( $b, array(
			'phong' => (string) $phong, 'coso' => $t['coSo'], 'ma_a' => $t['a'], 'ma_b' => $t['b'] ) );
	}

	/** Người này có trong danh bạ của cơ sở kia không — để biết được phép mở chat riêng không. */
	private static function cung_co_so( $ma, $coso ) {
		global $wpdb;
		$c = VHCC_NhanSu::chuan_coso( (string) $coso );
		if ( '' === $c || '' === trim( (string) $ma ) ) { return false; }
		$hs = $wpdb->get_row( $wpdb->prepare(
			'SELECT cua_hang, coso_phu FROM ' . VHCC_DB::t( 'nhan_vien' ) . ' WHERE ma_nv=%s',
			trim( (string) $ma ) ), ARRAY_A );
		if ( ! $hs ) { return false; }
		foreach ( array( 'cua_hang', 'coso_phu' ) as $cot ) {
			foreach ( explode( ',', (string) $hs[ $cot ] ) as $x ) {
				if ( 0 === strcasecmp( VHCC_NhanSu::chuan_coso( $x ), $c ) ) { return true; }
			}
		}
		return false;
	}


	/** Người này được vào phòng của cơ sở nào — danh sách để màn vẽ ra mấy cái tab phòng. */
	public static function phong_cua( $u ) {
		$ra = array();
		if ( ! class_exists( 'VHCC_NhanSu' ) ) { return $ra; }
		$ma = isset( $u['ma_nv'] ) ? trim( (string) $u['ma_nv'] ) : '';
		if ( '' === $ma ) { return $ra; }

		/* Người làm HAI nơi thì có HAI phòng — và đó là điều đúng: hai cửa hàng ấy không liên
		   quan gì nhau, gộp chung một phòng là cửa hàng này đọc chuyện cửa hàng kia. */
		$hs = VHCC_NhanSu::ho_so( $ma );
		$ds = array();
		if ( $hs ) {
			foreach ( array( 'cua_hang', 'coso_phu' ) as $cot ) {
				foreach ( explode( ',', (string) ( isset( $hs[ $cot ] ) ? $hs[ $cot ] : '' ) ) as $x ) {
					$c = VHCC_NhanSu::chuan_coso( $x );
					if ( '' !== $c ) { $ds[ strtolower( $c ) ] = $c; }
				}
			}
		}
		foreach ( $ds as $c ) {
			if ( self::duoc_vao( $u, $c ) ) { $ra[] = $c; }
		}
		sort( $ra );
		return $ra;
	}

	/**
	 * CỬA DUY NHẤT quyết định "người này có ở trong phòng ấy không" — cho CẢ HAI loại phòng.
	 *
	 * 🔴 MỘT HÀM, HAI LOẠI PHÒNG. Tách ra hai hàm là sớm muộn một nơi gọi nhầm hàm của loại kia,
	 *    và nhầm theo hướng nào cũng sai: chặt hơn thì người trong cuộc không vào được phòng
	 *    riêng của chính mình, lỏng hơn thì người thứ ba đọc được.
	 */
	public static function duoc_vao( $u, $phong ) {
		$p = trim( (string) $phong );
		$t = self::tach_rieng( $p );

		if ( $t ) {
			$ma = isset( $u['ma_nv'] ) ? trim( (string) $u['ma_nv'] ) : '';
			if ( '' === $ma ) { return false; }
			/* 🔴 PHẢI LÀ MỘT TRONG HAI NGƯỜI. So bằng `strcmp`, không `strcasecmp`: mã NV là
			   định danh, và ở hệ này đã có lần hai hồ sơ chỉ khác nhau chữ hoa. */
			if ( 0 !== strcmp( $t['a'], $ma ) && 0 !== strcmp( $t['b'], $ma ) ) { return false; }
			/* 🔴 VÀ CẢ HAI PHẢI CÙNG CƠ SỞ ẤY. Không kiểm thì hai người từng làm chung, nay mỗi
			   người một nơi, vẫn giữ một kênh riêng nằm ngoài mọi phép gác theo cơ sở. */
			$c = VHCC_NhanSu::chuan_coso( $t['coSo'] );
			if ( '' === $c ) { return false; }
			if ( ! VHCC_NhanSu::co_quyen_coso( $u, $c ) && ! VHCC_NhanSu::co_cham_coso( $u, $c ) ) {
				return false;
			}
			$kia = ( 0 === strcmp( $t['a'], $ma ) ) ? $t['b'] : $t['a'];
			return self::cung_co_so( $kia, $c );
		}

		$c = VHCC_NhanSu::chuan_coso( $p );
		if ( '' === $c ) { return false; }
		return VHCC_NhanSu::co_quyen_coso( $u, $c ) || VHCC_NhanSu::co_cham_coso( $u, $c );
	}

	/**
	 * TÊN PHÒNG ĐÚNG KHUÔN — cho cả phòng cơ sở lẫn phòng riêng. '' khi không dùng được.
	 *
	 * 🔴 `ds()` và `gui()` PHẢI đi qua đây, không gọi thẳng `chuan_coso()`. `chuan_coso()` cắt ở
	 *    dấu phẩy đầu và bỏ ký tự lạ — đưa một khoá phòng riêng `@CS|A|B` vào đó là nó trả về
	 *    một chuỗi khác, và tin rơi vào một cái phòng thứ ba mà không ai đọc được. Đã có đúng
	 *    lỗi kiểu này ở `VHCC_Online::cham_cong()` (thẻ cơ sở nhiều nơi bị ghi nguyên chuỗi).
	 */
	private static function chuan_phong( $phong ) {
		$p = trim( (string) $phong );
		if ( '' === $p ) { return ''; }
		$t = self::tach_rieng( $p );
		if ( $t ) {
			/* Dựng LẠI khoá từ ba mảnh: người gọi có thể gửi lên hai mã đảo thứ tự, và khoá đảo
			   thứ tự là một cái phòng khác. Dựng lại là ép về đúng một khoá duy nhất. */
			return self::phong_rieng( $t['coSo'], $t['a'], $t['b'] );
		}
		return VHCC_NhanSu::chuan_coso( $p );
	}

	/* ============================================================================== đọc */

	/**
	 * Tin trong một phòng.
	 *
	 * @param int $tu_id Chỉ lấy tin MỚI HƠN số này. 0 = lấy trang cuối.
	 */
	public static function ds( $u, $coso, $tu_id = 0 ) {
		global $wpdb;
		$c = self::chuan_phong( $coso );
		if ( '' === $c || ! self::duoc_vao( $u, $c ) ) {
			return array( 'ok' => false, 'error' => 'Không có quyền vào phòng chat này.' );
		}
		$b = VHCC_DB::t( 'chat_tin' );
		$tu = max( 0, (int) $tu_id );

		/* 🔴 `tu_id > 0` -> lấy TIN MỚI theo thứ tự TĂNG; `tu_id = 0` -> lấy trang CUỐI, nên
		   phải xếp GIẢM rồi đảo lại. Dùng chung một câu cho cả hai là hoặc mở phòng ra thấy tin
		   của năm ngoái, hoặc mỗi lượt hỏi lại kéo cả nghìn tin xuống điện thoại. */
		if ( $tu > 0 ) {
			$r = VHCC_DB::rows( $wpdb->prepare(
				"SELECT * FROM $b WHERE phong=%s AND id>%d ORDER BY id ASC LIMIT %d",
				$c, $tu, self::MOI_LUOT ) );
		} else {
			$r = array_reverse( (array) VHCC_DB::rows( $wpdb->prepare(
				"SELECT * FROM $b WHERE phong=%s ORDER BY id DESC LIMIT %d",
				$c, self::MOI_LUOT ) ) );
		}

		$ma = trim( (string) $u['ma_nv'] );
		$ds = array();
		foreach ( (array) $r as $x ) {
			$xoa = ! empty( $x['da_xoa'] );
			$ds[] = array(
				'id'     => (int) $x['id'],
				'maNV'   => (string) $x['ma_nv'],
				'hoTen'  => (string) $x['ho_ten'],
				/* Tin đã xoá VẪN TRẢ VỀ, dưới dạng một chỗ trống có ghi chú. Xoá hẳn khỏi danh
				   sách thì hai người đang nói chuyện thấy hai mạch khác nhau, và người vừa bị
				   trả lời một câu không còn tồn tại thì không hiểu chuyện gì. */
				'chu'    => $xoa ? '' : (string) $x['chu'],
				'daXoa'  => $xoa,
				/* Tệp của tin đã xoá KHÔNG trả về — xoá tin mà vẫn còn cái ảnh treo đó thì việc
				   xoá chẳng còn nghĩa gì. */
				'tep'    => ( $xoa || '' === (string) $x['tep'] ) ? null : array(
					'ten'  => (string) $x['tep_ten'],
					'loai' => (string) $x['tep_loai'],
					'co'   => (int) $x['tep_co'],
					'anh'  => isset( self::TEP_KIEU[ (string) $x['tep_loai'] ] )
						&& self::TEP_KIEU[ (string) $x['tep_loai'] ][1],
				),
				'luc'    => (string) $x['tao_luc'],
				'cuaToi' => ( 0 === strcasecmp( (string) $x['ma_nv'], $ma ) ),
			);
		}

		self::danh_dau_doc( $ma, $c, $ds );
		return array( 'ok' => true, 'coSo' => $c, 'ds' => $ds );
	}

	/* ============================================================================ đính kèm

	   Anh Thắng 21/09/2026: *"chat thêm đính kèm ảnh và tệp"*.

	   =============================================================================================
	   🔴 TỆP ĐÍNH KÈM KHÔNG ĐƯỢC NẰM Ở MỘT ĐỊA CHỈ AI CŨNG MỞ ĐƯỢC
	   =============================================================================================
	   Ảnh chấm công nằm thẳng trong `wp-content/uploads` và ai có đường dẫn cũng xem được — chấp
	   nhận được, vì đó là ảnh khuôn mặt chụp tại quầy, và tên tệp thì đoán ra được từ mã NV +
	   ngày giờ. Tệp chat thì KHÁC HẲN: nó là thứ người ta cố tình gửi riêng cho một người.
	   Đặt nó vào một địa chỉ công khai là bỏ luôn cái gác vừa dựng ở trên — phòng thì khoá, mà
	   nội dung trong phòng thì để ngoài cửa.

	   Nên tệp đi qua `xem_tep()`, và hàm ấy hỏi đúng `duoc_vao()` như mọi đường khác.

	   ⚠️ TÊN TỆP TRÊN ĐĨA LÀ CHUỖI NGẪU NHIÊN, không phải tên người dùng đặt. Hai lý do: tên
	      người đặt có thể trùng, và có thể mang ký tự dựng đường dẫn (`../`). Tên gốc chỉ được
	      cất làm CHỮ để hiện ra.

	   🔴 KHÔNG NHẬN SVG, DÙ NÓ LÀ ẢNH. Tệp SVG chứa được `<script>`; phục vụ nó inline là mở một
	      lỗ chèn mã ngay trong tên miền của mình — và nó lọt qua mọi phép kiểm "có phải ảnh
	      không" viết theo kiểu thông thường.

	   🔴 CHỈ ẢNH MỚI HIỆN INLINE. Mọi thứ khác phục vụ kèm `Content-Disposition: attachment` và
	      một kiểu nội dung KHÔNG chạy được. Thiếu chốt này thì một tệp .html gửi qua chat là một
	      trang chạy được trên tên miền công ty, đọc được thẻ phiên của người mở nó.
	*/

	/** Dài nhất của một tệp đính kèm (byte). */
	const TEP_TOI_DA = 8388608;   // 8 MB

	/**
	 * Kiểu tệp nhận, và kiểu nội dung dùng khi phục vụ lại.
	 *
	 * ⚠️ PHỤC VỤ THEO BẢNG NÀY, KHÔNG THEO THỨ TRÌNH DUYỆT KHAI. Tin lời khai của client là
	 *    nhận một tệp .html mang nhãn `image/png` rồi trả về đúng `text/html`.
	 */
	const TEP_KIEU = array(
		'jpg'  => array( 'image/jpeg', true ),
		'jpeg' => array( 'image/jpeg', true ),
		'png'  => array( 'image/png', true ),
		'gif'  => array( 'image/gif', true ),
		'webp' => array( 'image/webp', true ),
		'heic' => array( 'image/heic', true ),
		'pdf'  => array( 'application/pdf', false ),
		'doc'  => array( 'application/msword', false ),
		'docx' => array( 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', false ),
		'xls'  => array( 'application/vnd.ms-excel', false ),
		'xlsx' => array( 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', false ),
		'csv'  => array( 'text/csv', false ),
		'txt'  => array( 'text/plain', false ),
		'zip'  => array( 'application/zip', false ),
	);

	/** Đuôi tệp đã chuẩn hoá, hoặc '' nếu không nhận. */
	private static function duoi_tep( $ten ) {
		$d = strtolower( (string) pathinfo( (string) $ten, PATHINFO_EXTENSION ) );
		return isset( self::TEP_KIEU[ $d ] ) ? $d : '';
	}

	/**
	 * Cất một tệp. Trả về array('duong','ten','loai','co') hoặc array('error').
	 *
	 * @param string $b64 nội dung tệp, đã mã hoá base64 (có hoặc không có tiền tố data:).
	 */
	private static function luu_tep( $b64, $ten_goc ) {
		$s = (string) $b64;
		$vt = strpos( $s, 'base64,' );
		if ( false !== $vt ) { $s = substr( $s, $vt + 7 ); }
		$nhi = base64_decode( $s, true );
		if ( false === $nhi || strlen( $nhi ) < 8 ) {
			return array( 'error' => 'Tệp hỏng hoặc rỗng.' );
		}
		if ( strlen( $nhi ) > self::TEP_TOI_DA ) {
			return array( 'error' => 'Tệp lớn quá '
				. round( self::TEP_TOI_DA / 1048576 ) . ' MB. Nén lại hoặc gửi qua đơn từ.' );
		}
		$duoi = self::duoi_tep( $ten_goc );
		if ( '' === $duoi ) {
			return array( 'error' => 'Chỉ nhận ảnh (jpg, png, gif, webp, heic) và tệp '
				. 'pdf, doc, docx, xls, xlsx, csv, txt, zip.' );
		}

		/* 🔴 ẢNH THÌ PHẢI THẬT SỰ LÀ ẢNH. Đổi đuôi một tệp .html thành .png là đủ để lọt phép
		   kiểm đuôi; `getimagesize()` đọc chính nội dung nên nó không bị lừa như vậy.
		   (`heic` thì PHP thường không đọc được — bỏ qua phép này cho riêng nó, và nó vẫn được
		   phục vụ kèm `attachment` nếu không nhận ra là ảnh.) */
		if ( self::TEP_KIEU[ $duoi ][1] && 'heic' !== $duoi ) {
			$do = @getimagesizefromstring( $nhi );
			if ( false === $do ) {
				return array( 'error' => 'Tệp này khai là ảnh nhưng nội dung không phải ảnh.' );
			}
		}

		$u = wp_upload_dir();
		if ( ! empty( $u['error'] ) ) { return array( 'error' => 'Máy chủ không ghi được tệp.' ); }
		$tuong = 'vhcc-chat/' . gmdate( 'Y-m' );
		$thu = $u['basedir'] . '/' . $tuong;
		if ( ! wp_mkdir_p( $thu ) ) { return array( 'error' => 'Máy chủ không tạo được thư mục.' ); }

		/* Chặn liệt kê thư mục và chặn Apache chạy bất cứ thứ gì trong đó. Không thay được phép
		   gác ở `xem_tep()` — nginx bỏ qua .htaccess — nhưng là một lớp nữa, và rẻ. */
		if ( ! file_exists( $u['basedir'] . '/vhcc-chat/index.php' ) ) {
			file_put_contents( $u['basedir'] . '/vhcc-chat/index.php', "<?php // im lang\n" );
			file_put_contents( $u['basedir'] . '/vhcc-chat/.htaccess',
				"Deny from all\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n" );
		}

		/* Tên trên đĩa là chuỗi ngẫu nhiên — xem khối chú thích trên. */
		$ten_dia = bin2hex( random_bytes( 16 ) ) . '.' . $duoi;
		if ( false === file_put_contents( $thu . '/' . $ten_dia, $nhi ) ) {
			return array( 'error' => 'Máy chủ không ghi được tệp.' );
		}
		return array(
			'duong' => $tuong . '/' . $ten_dia,
			/* Tên gốc CHỈ để hiện. Cắt ngắn và bỏ đường dẫn: `../../wp-config.php` là một cái
			   tên hợp lệ với người dùng, và nó không bao giờ được chạm tới đĩa. */
			'ten'   => mb_substr( sanitize_file_name( (string) $ten_goc ), 0, 120 ),
			'loai'  => $duoi,
			'co'    => strlen( $nhi ),
		);
	}

	/**
	 * PHỤC VỤ MỘT TỆP ĐÍNH KÈM. Tự kết thúc lượt gọi.
	 *
	 * 🔴 GÁC TRƯỚC, ĐỌC ĐĨA SAU. Đọc tệp rồi mới hỏi quyền là đã đọc mất rồi — và một lỗi nào đó
	 *    ở giữa là nó lọt ra ngoài.
	 */
	public static function xem_tep( $u, $id ) {
		global $wpdb;
		$x = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . VHCC_DB::t( 'chat_tin' ) . ' WHERE id=%d', (int) $id ), ARRAY_A );
		if ( ! $x || '' === (string) $x['tep'] || ! empty( $x['da_xoa'] ) ) { self::chet_tep( 404 ); }
		if ( ! self::duoc_vao( $u, (string) $x['phong'] ) ) { self::chet_tep( 403 ); }

		$up = wp_upload_dir();
		$duong = $up['basedir'] . '/' . (string) $x['tep'];
		/* ⚠️ Chốt đường dẫn: đường trong cơ sở dữ liệu do mã này ghi, nhưng một lần sửa tay hay
		   một lỗi ở đâu đó là `../../` — và lúc ấy hàm này phục vụ bất kỳ tệp nào trên máy chủ. */
		$that = realpath( $duong );
		$goc  = realpath( $up['basedir'] . '/vhcc-chat' );
		if ( ! $that || ! $goc || 0 !== strpos( $that, $goc ) || ! is_readable( $that ) ) {
			self::chet_tep( 404 );
		}

		$duoi = (string) $x['tep_loai'];
		$kieu = isset( self::TEP_KIEU[ $duoi ] ) ? self::TEP_KIEU[ $duoi ] : array( 'application/octet-stream', false );
		nocache_headers();
		header( 'Content-Type: ' . $kieu[0] );
		header( 'Content-Length: ' . filesize( $that ) );
		header( 'X-Content-Type-Options: nosniff' );
		/* 🔴 CHỈ ẢNH MỚI INLINE. Mọi thứ khác tải về. Xem khối chú thích trên. */
		$ten = (string) $x['tep_ten'];
		header( 'Content-Disposition: ' . ( $kieu[1] ? 'inline' : 'attachment' )
			. '; filename="' . rawurlencode( $ten ) . '"' );
		readfile( $that );
		exit;
	}

	private static function chet_tep( $ma ) {
		status_header( (int) $ma );
		header( 'Content-Type: text/plain; charset=utf-8' );
		echo 'khong xem duoc tep';
		exit;
	}

	/* ============================================================================== ghi */

	public static function gui( $u, $coso, $chu, $tep_b64 = '', $tep_ten = '' ) {
		global $wpdb;
		$c = self::chuan_phong( $coso );
		if ( '' === $c || ! self::duoc_vao( $u, $c ) ) {
			return array( 'ok' => false, 'error' => 'Không có quyền nhắn vào phòng này.' );
		}
		/* ⚠️ Cắt khoảng trắng RỒI mới đo. Một tin toàn dấu cách lọt qua phép đo độ dài thô, và
		   nó hiện ra thành một bong bóng rỗng không ai xoá được ngoài chính người gửi. */
		$s = trim( (string) $chu );
		$co_tep = ( '' !== trim( (string) $tep_b64 ) );
		/* Có tệp thì KHÔNG bắt buộc gõ chữ — gửi mỗi tấm ảnh là chuyện thường. */
		if ( '' === $s && ! $co_tep ) { return array( 'ok' => false, 'error' => 'Chưa gõ gì.' ); }
		if ( mb_strlen( $s, 'UTF-8' ) > self::DAI_TOI_DA ) {
			return array( 'ok' => false, 'error' => 'Tin dài quá ' . self::DAI_TOI_DA
				. ' ký tự. Tách ra làm mấy tin, hoặc gửi thành tệp qua đơn từ.' );
		}

		$t_duong = ''; $t_ten = ''; $t_loai = ''; $t_co = 0;
		if ( $co_tep ) {
			$kq = self::luu_tep( $tep_b64, $tep_ten );
			if ( isset( $kq['error'] ) ) { return array( 'ok' => false, 'error' => $kq['error'] ); }
			$t_duong = $kq['duong']; $t_ten = $kq['ten']; $t_loai = $kq['loai']; $t_co = $kq['co'];
		}

		$ok = $wpdb->insert( VHCC_DB::t( 'chat_tin' ), array(
			'tep'      => $t_duong,
			'tep_ten'  => $t_ten,
			'tep_loai' => $t_loai,
			'tep_co'   => $t_co,
			'phong'   => $c,
			'ma_nv'   => trim( (string) $u['ma_nv'] ),
			/* Chép TÊN vào hàng, không tra lại lúc hiện. Người đổi tên hay nghỉ việc thì mấy
			   câu họ đã nhắn vẫn phải mang đúng cái tên lúc nhắn — đó là điều làm nó thành một
			   cuốn sổ đọc lại được, chứ không phải một danh sách trỏ vào hồ sơ hiện tại. */
			'ho_ten'  => (string) ( isset( $u['ho_ten'] ) ? $u['ho_ten'] : $u['name'] ),
			'chu'     => $s,
			'tao_luc' => current_time( 'mysql' ),
		) );
		if ( false === $ok ) { return array( 'ok' => false, 'error' => 'MySQL: ' . $wpdb->last_error ); }

		$id = (int) $wpdb->insert_id;
		/* Người vừa gửi thì coi như đã đọc tới đây — nếu không, chính họ có một tin chưa đọc. */
		self::dat_da_doc( trim( (string) $u['ma_nv'] ), $c, $id );
		/* Phòng riêng: ghi vào sổ cuộc nói chuyện ở TIN ĐẦU TIÊN. Ghi lúc mở màn thì mở ra rồi
		   không nhắn gì cũng đẻ ra một dòng trong danh sách của người kia. */
		self::ghi_so_rieng( $c );
		return array( 'ok' => true, 'id' => $id, 'coSo' => $c );
	}

	/**
	 * Tự xoá tin CỦA MÌNH, trong hạn.
	 *
	 * 🔴 XOÁ MỀM. Cột `da_xoa` thay vì `DELETE`: chỗ trống có ghi chú giữ được mạch hội thoại,
	 *    còn xoá hẳn thì câu trả lời phía dưới treo lơ lửng. Và khi có tranh cãi thì "đã có một
	 *    tin ở đây, người gửi tự xoá" là một thông tin, còn im lặng thì không.
	 */
	public static function xoa( $u, $id ) {
		global $wpdb;
		$b = VHCC_DB::t( 'chat_tin' );
		$i = (int) $id;
		$x = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $b WHERE id=%d", $i ), ARRAY_A );
		if ( ! $x ) { return array( 'ok' => false, 'error' => 'Không thấy tin này.' ); }

		$ma = trim( (string) $u['ma_nv'] );
		if ( 0 !== strcasecmp( (string) $x['ma_nv'], $ma ) ) {
			/* ⚠️ KỂ CẢ ADMIN. Cho quản lý xoá lời của người khác là biến cuốn sổ này thành thứ
			   sửa được — và lúc ấy nó không làm chứng cho ai được nữa, kể cả cho quản lý. */
			return array( 'ok' => false, 'error' => 'Chỉ tự xoá được tin của chính mình.' );
		}
		$tuoi = time() - (int) strtotime( (string) $x['tao_luc'] );
		if ( $tuoi > self::PHUT_XOA * 60 ) {
			return array( 'ok' => false, 'error' => 'Quá ' . self::PHUT_XOA
				. ' phút thì không xoá được nữa.' );
		}
		/* 🔴 XOÁ CẢ TỆP TRÊN ĐĨA. Đánh dấu `da_xoa` mà để tệp nằm lại là người đã bấm xoá vẫn
		   còn cái ảnh của mình trên máy chủ — và `xem_tep()` chối, nhưng bản sao lưu thì không. */
		if ( '' !== (string) $x['tep'] ) {
			$up = wp_upload_dir();
			$d = realpath( $up['basedir'] . '/' . (string) $x['tep'] );
			$g = realpath( $up['basedir'] . '/vhcc-chat' );
			if ( $d && $g && 0 === strpos( $d, $g ) ) { @unlink( $d ); }
		}
		$wpdb->update( $b, array( 'da_xoa' => 1, 'chu' => '', 'tep' => '' ), array( 'id' => $i ) );
		return array( 'ok' => true, 'id' => $i );
	}

	/* ========================================================================== chưa đọc */

	/** Số tin chưa đọc của từng phòng. Trả mảng cơ sở => số. */
	public static function chua_doc( $u ) {
		global $wpdb;
		$ra = array();
		$ma = isset( $u['ma_nv'] ) ? trim( (string) $u['ma_nv'] ) : '';
		if ( '' === $ma ) { return $ra; }
		$bt = VHCC_DB::t( 'chat_tin' );
		$bd = VHCC_DB::t( 'chat_doc' );
		foreach ( self::phong_cua( $u ) as $c ) {
			$cuoi = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT id_cuoi FROM $bd WHERE ma_nv=%s AND phong=%s", $ma, $c ) );
			/* ⚠️ KHÔNG đếm tin của CHÍNH MÌNH. Gửi xong mà app báo "1 tin chưa đọc" thì lần sau
			   không ai tin con số ấy nữa. */
			$so = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM $bt WHERE phong=%s AND id>%d AND ma_nv<>%s",
				$c, $cuoi, $ma ) );
			if ( $so > 0 ) { $ra[ $c ] = $so; }
		}
		return $ra;
	}

	private static function danh_dau_doc( $ma, $coso, $ds ) {
		$max = 0;
		foreach ( $ds as $x ) { if ( (int) $x['id'] > $max ) { $max = (int) $x['id']; } }
		if ( $max > 0 ) { self::dat_da_doc( $ma, $coso, $max ); }
	}

	/**
	 * ⚠️ CHỈ TIẾN, KHÔNG LÙI. Mở lại một trang cũ (hay một lượt hỏi tới trễ) không được kéo mốc
	 *    đã đọc lùi về — lùi một lần là mấy chục tin đã đọc bỗng "chưa đọc" trở lại.
	 */
	private static function dat_da_doc( $ma, $coso, $id ) {
		global $wpdb;
		$b = VHCC_DB::t( 'chat_doc' );
		$cu = $wpdb->get_var( $wpdb->prepare(
			"SELECT id_cuoi FROM $b WHERE ma_nv=%s AND phong=%s", $ma, $coso ) );
		if ( null === $cu ) {
			$wpdb->insert( $b, array( 'ma_nv' => $ma, 'phong' => $coso, 'id_cuoi' => (int) $id ) );
			return;
		}
		if ( (int) $id > (int) $cu ) {
			$wpdb->update( $b, array( 'id_cuoi' => (int) $id ),
				array( 'ma_nv' => $ma, 'phong' => $coso ) );
		}
	}
}
