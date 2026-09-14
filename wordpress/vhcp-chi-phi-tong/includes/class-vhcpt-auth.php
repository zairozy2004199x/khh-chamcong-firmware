<?php
/**
 * ĐĂNG NHẬP TRANG TỔNG — MƯỢN SỔ NGƯỜI DÙNG CỦA CÁC BẢN, KHÔNG ĐẺ SỔ THỨ TƯ.
 *
 * ══════════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 VÌ SAO KHÔNG CÓ SỔ NGƯỜI DÙNG RIÊNG. Trang này mở ra để một người duyệt đơn của ba mảng.
 *    Cho nó sổ riêng thì mỗi lần đổi người duyệt phải khai hai nơi, và cái nơi bị quên là nơi
 *    vẫn còn mở cửa cho người đã nghỉ. Mượn sổ của các bản thì quyền ở đây LUÔN đúng bằng quyền
 *    bên kia, không có gì để lệch.
 *
 * 🔴 VÀO ĐƯỢC BẢN NÀO LÀ DUYỆT ĐƯỢC BẢN ẤY — KHÔNG HƠN. Một người có thể có tài khoản ở cả ba
 *    mảng, hoặc chỉ một. Trang tổng gom lại đúng những bản họ có mặt, và ở mỗi bản hỏi chính
 *    bảng phân quyền của bản ấy xem vai đó có được duyệt không. Dựng một thang quyền riêng ở
 *    đây là dựng đường thứ hai trả lời cùng một câu hỏi — đã cắn một lần bên `VHCP_DonVi` với
 *    `VAI_XEM_CA`, và hai đường thì lệch nhau.
 *
 * ⚠️ KHÔNG BAO GIỜ IN PIN RA MÀN, không ghi PIN vào log, không trả PIN về trình duyệt. Trang
 *    chạy ngoài internet. Phiên mang THẺ, không mang PIN.
 *
 * ⚠️ SO PIN BẰNG `hash_equals`. So bằng `===` thì thời gian so phụ thuộc số ký tự khớp đầu —
 *    trên một cổng ai cũng gọi được, đó là một khe đo được.
 * ══════════════════════════════════════════════════════════════════════════════════════════════
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCPT_Auth {

	/** Thẻ phiên sống bao lâu (giây). */
	const HAN = 43200;

	/* ══════════════════════════════════════════════════════════════════════════════════════════
	 * MỖI VIỆC MỘT HÀNH ĐỘNG RIÊNG — KHÔNG GÁC CHUNG BẰNG "duyetTU"
	 * ══════════════════════════════════════════════════════════════════════════════════════════
	 * 🔴 LỖI CỦA BẢN 1.0.0, VÁ Ở ĐÂY. Bản ấy gác MỌI việc ghi bằng đúng một hành động `duyetTU`.
	 *    Nhưng bảng phân quyền của các bản có bốn việc khác nhau cho bốn vai khác nhau:
	 *      · duyetTU   — Quản lý duyệt tạm ứng
	 *      · capTU     — Kế toán cá nhân cấp (chuyển) tiền
	 *      · traDon    — Quản lý VÀ cả hai kế toán đều trả lại đơn được
	 *      · duyetNCC  — Kế toán NCC xác nhận phần nhà cung cấp
	 *    Gác chung bằng `duyetTU` thì Kế toán cá nhân — người DUY NHẤT được cấp tiền — không làm
	 *    được gì trên trang tổng, dù bên trang mảng họ làm bình thường. Anh Thắng 14/09/2026:
	 *    *"trang tổng là trang do quản lý và kế toán duyệt chi phí"* — kế toán bị khoá ngoài thì
	 *    trang tổng chỉ làm được nửa việc nó sinh ra để làm.
	 *
	 * ⚠️ TÊN HÀNH ĐỘNG LÀ GIAO KÈO VỚI CÁC BẢN (`VHCP_Cfg::actions()`). Gõ sai một chữ thì hàm
	 *    tra trả về "không có quyền" — im lặng, và nhìn y như người ấy chưa được khai quyền.
	 * ══════════════════════════════════════════════════════════════════════════════════════════ */
	const VIEC = array(
		'duyet'  => 'duyetTU',
		'cap'    => 'capTU',
		'traLai' => 'traDon',
		'qtCn'   => 'xacNhanQT',
		'qtNcc'  => 'duyetNCC',
		'misa'   => 'xuatMISA',
	);

	/** Giữ lại tên cũ cho chỗ nào còn hỏi "được duyệt tạm ứng không". */
	const QUYEN_DUYET = 'duyetTU';

	/** Người của lượt gọi này — đặt bởi `xac_the()`. */
	private static $toi = null;

	/**
	 * Tìm người theo PIN trên KHẮP các bản đang cài.
	 *
	 * @return array|null [ 'ten', 'bans' => [ khoá => ['vai','coso','duyet'] ] ]
	 *
	 * 🔴 CHỐI KHI MỘT PIN RA HAI NGƯỜI KHÁC TÊN. Hai bản có thể có hai người khác nhau trùng
	 *    PIN — hiếm, nhưng khi xảy ra thì cho vào là cho một người mang danh người kia đi duyệt
	 *    tiền. Chối và nói ra, để có người đi sửa; đoán bừa thì không ai biết mà sửa.
	 */
	public static function tim_theo_pin( $pin ) {
		$pin = trim( (string) $pin );
		if ( '' === $pin ) { return null; }
		$ten = '';
		$bans = array();
		foreach ( VHCPT_Ban::ds() as $khoa => $b ) {
			$lop_cfg = VHCPT_Ban::lop( $khoa, 'Cfg' );
			/* ⚠️ Gác CÙNG HÀM với lời gọi — luật `tools/test/kiem-goi-cheo.php`. */
			if ( ! $lop_cfg || ! class_exists( $lop_cfg ) || ! method_exists( $lop_cfg, 'get_users' ) ) {
				continue;
			}
			foreach ( (array) call_user_func( array( $lop_cfg, 'get_users' ) ) as $u ) {
				$pu = trim( (string) ( isset( $u['pin'] ) ? $u['pin'] : '' ) );
				if ( '' === $pu || ! hash_equals( $pu, $pin ) ) { continue; }
				$tu = trim( (string) ( isset( $u['ten'] ) ? $u['ten'] : '' ) );
				if ( '' === $tu ) { continue; }
				if ( '' !== $ten && 0 !== strcasecmp( $ten, $tu ) ) {
					return array( 'loi' => 'PIN này đang dùng cho hai người khác tên ở hai mảng '
						. '(«' . $ten . '» và «' . $tu . '»). Đổi PIN một bên rồi vào lại.' );
				}
				$ten = ( '' === $ten ) ? $tu : $ten;
				$vai = self::vai_cua( $u );
				$bans[ $khoa ] = array(
					'vai'   => $vai,
					'coso'  => trim( (string) ( isset( $u['coso'] ) ? $u['coso'] : '' ) ),
					/* ⚠️ GIỮ ĐỦ THỨ CẦN ĐỂ MƯỢN PHIÊN bên bản ấy — xem `VHCPT_Ban::muon_phien()`.
					   Thiếu `boPhan` là người bị bó bộ phận sang đó thành KHÔNG bó, tức nhìn
					   thấy sổ của mọi mảng; thiếu `maNv` là mấy chốt "đơn của mình" mất sợi dây
					   nối. Cả hai đều hỏng im lặng. */
					'boPhan' => trim( (string) ( isset( $u['boPhan'] ) ? $u['boPhan'] : '' ) ),
					'maNv'   => trim( (string) ( isset( $u['maNv'] ) ? $u['maNv'] : '' ) ),
					'duyet' => self::duoc_duyet( $khoa, $vai ),
					/* Cả bảng quyền, tra một lượt rồi cất vào thẻ phiên: mỗi lượt tra là một
					   `get_quyen()` của bản kia, mà màn nào cũng cần hỏi bốn việc. */
					'quyen' => self::bang_quyen( $khoa, $vai ),
				);
				break;
			}
		}
		if ( '' === $ten ) { return null; }
		return array( 'ten' => $ten, 'bans' => $bans );
	}

	/**
	 * VAI CỦA MỘT DÒNG NGƯỜI DÙNG — ĐỌC ĐÚNG TÊN Ô MÀ BẢN KIA ĐẶT.
	 *
	 * ══════════════════════════════════════════════════════════════════════════════════════════
	 * 🔴 ĐÃ CẮN THẬT 14/09/2026. Anh Thắng đăng nhập trang tổng bằng tài khoản ADMIN và màn ghi
	 *    *"KVC · (chưa có vai) · chỉ xem"*, kèm dải vàng *"Vai của bạn không được duyệt, cấp tiền
	 *    hay trả lại đơn ở mảng nào"* — bằng chính tài khoản cao nhất của hệ.
	 *
	 *    Bản mảng trả dòng người dùng với ô tên là **`vaiTro`** (xem `VHCP_Cfg::cfg_static()`),
	 *    còn chỗ này hỏi `$u['vai']`. Không có ô ấy -> chuỗi rỗng -> `bang_quyen()` trả về toàn
	 *    `false` NGAY Ở DÒNG ĐẦU, trước cả nhánh nới cho Admin. Nên bản 1.2.0 vá Admin mà không
	 *    cứu được gì: nó vá nhánh thứ hai của một hàm đã thoát ở nhánh thứ nhất.
	 *
	 * 🔴 ĐỌC NHẦM KHOÁ LÀ KIỂU HỎNG TỆ NHẤT Ở ĐÂY: không một câu lỗi nào: mọi người vẫn đăng
	 *    nhập được, tên vẫn đúng, bảng đơn vẫn đầy — chỉ là không ai bấm được gì, và trang thì
	 *    nói dối rằng đó là do phân quyền. Người đi sửa sẽ ngồi sửa bảng phân quyền, mãi không
	 *    ra, vì chỗ hỏng không nằm ở đó.
	 *
	 * ⚠️ NHẬN CẢ BA TÊN. `vaiTro` là tên thật hôm nay; `vai` và `role` để dành cho bản mảng cũ
	 *    hoặc bản sau này đổi tên — trang tổng đọc sổ của bốn plugin, mà chúng nâng cấp lệch
	 *    nhau. Thà nhận rộng ở CỬA ĐỌC còn hơn im lặng trả về "chưa có vai".
	 * ══════════════════════════════════════════════════════════════════════════════════════════
	 */
	public static function vai_cua( $u ) {
		$u = (array) $u;
		foreach ( array( 'vaiTro', 'vai', 'role' ) as $o ) {
			if ( isset( $u[ $o ] ) && '' !== trim( (string) $u[ $o ] ) ) {
				return trim( (string) $u[ $o ] );
			}
		}
		return '';
	}

	/**
	 * Vai này có được duyệt tạm ứng ở bản ấy không — HỎI CHÍNH BẢNG PHÂN QUYỀN CỦA BẢN ẤY.
	 *
	 * ⚠️ Không có đường lui "vai tên là Quản lý thì cho qua". Bảng phân quyền sửa được trên màn,
	 *    và anh Thắng đã sửa nó thật; đoán theo tên vai là nói ngược lại thứ người ta vừa khai.
	 */
	public static function duoc_duyet( $khoa, $vai ) {
		/* ⚠️ ĐI QUA `bang_quyen()`, KHÔNG TỰ TRA LẠI. Bản trước tra thẳng ma trận ở đây, nên nó
		   mắc y nguyên cái lỗi Admin ở trên — hai đường trả lời cùng một câu hỏi thì cái nào
		   được vá cũng để lại cái kia sai. */
		$b = self::bang_quyen( $khoa, $vai );
		return ! empty( $b['duyet'] );
	}

	/**
	 * Bảng quyền của một vai ở một bản: [ việc của trang tổng => bool ].
	 *
	 * ⚠️ MỘT LƯỢT `get_quyen()` CHO CẢ BỐN VIỆC. Hỏi từng việc một là bốn lượt đọc bảng phân
	 *    quyền của bản kia cho mỗi người, mỗi lượt đăng nhập.
	 */
	public static function bang_quyen( $khoa, $vai ) {
		$ra = array();
		foreach ( self::VIEC as $viec => $hd ) { $ra[ $viec ] = false; }
		$vai = trim( (string) $vai );
		if ( '' === $vai ) { return $ra; }
		$lop_cfg = VHCPT_Ban::lop( $khoa, 'Cfg' );
		/* ⚠️ Gác CÙNG HÀM với lời gọi — luật `tools/test/kiem-goi-cheo.php`. */
		if ( ! $lop_cfg || ! class_exists( $lop_cfg ) || ! method_exists( $lop_cfg, 'get_quyen' ) ) {
			return $ra;
		}

		/* ══════════════════════════════════════════════════════════════════════════════════════
		 * 🔴 ADMIN ĐỨNG NGOÀI MA TRẬN PHÂN QUYỀN — VÀ BẢN 1.1.0 KHÔNG BIẾT ĐIỀU ĐÓ.
		 * ══════════════════════════════════════════════════════════════════════════════════════
		 * `VHCP_Cfg::roles()` trả về *Giám đốc · Quản lý · Kế toán cá nhân · Kế toán NCC · Nhân
		 * viên* (+ vai tự tạo). **Không có 'Admin'.** Bảng phân quyền chỉ có cột cho những vai
		 * ấy, nên `$q['duyetTU']['Admin']` KHÔNG TỒN TẠI. Bên trang mảng, Admin được cho qua
		 * bằng một luật riêng ("Admin toàn quyền", xem app.html); trang tổng tra thẳng ma trận
		 * nên đọc ra `false` — và Admin đăng nhập vào thấy *"vai của bạn không được duyệt, cấp
		 * tiền hay trả lại đơn ở mảng nào"*.
		 *
		 * Anh Thắng 14/09/2026, sau khi cài: *"chi phí tổng chưa có"*. Đúng — cửa mở nhưng
		 * trong đó không bấm được gì, kể cả bằng tài khoản cao nhất của hệ.
		 *
		 * 🔴 ĐÂY KHÔNG PHẢI "ĐOÁN THEO TÊN VAI" — thứ mà chính tệp này cấm ở khối trên. Khác
		 *    nhau ở chỗ: 'Quản lý' CÓ trong ma trận, nên đoán hộ nó là nói ngược lại thứ người
		 *    ta vừa khai. 'Admin' thì KHÔNG có cột nào để khai cả — tra nó là hỏi một câu bảng
		 *    ấy không có chỗ trả lời. Nên điều kiện phải là "vai này không nằm trong `roles()`
		 *    của bản ấy", chứ không phải "vai này tên là Admin": ngày nào Admin được đưa vào ma
		 *    trận thì nó tự quay về tra bình thường, không cần ai nhớ sửa chỗ này.
		 * ══════════════════════════════════════════════════════════════════════════════════════ */
		if ( 'Admin' === $vai && method_exists( $lop_cfg, 'roles' ) ) {
			$vai_ds = (array) call_user_func( array( $lop_cfg, 'roles' ) );
			if ( ! in_array( 'Admin', $vai_ds, true ) ) {
				foreach ( self::VIEC as $viec => $hd ) { $ra[ $viec ] = true; }
				return $ra;
			}
		}

		$q = (array) call_user_func( array( $lop_cfg, 'get_quyen' ) );
		foreach ( self::VIEC as $viec => $hd ) {
			$ra[ $viec ] = ( isset( $q[ $hd ] ) && is_array( $q[ $hd ] ) && ! empty( $q[ $hd ][ $vai ] ) );
		}
		return $ra;
	}

	/**
	 * Người đang đăng nhập có làm được VIỆC này ở BẢN này không.
	 *
	 * 🔴 ĐỌC TỪ THẺ PHIÊN, KHÔNG HỎI LẠI BẢNG PHÂN QUYỀN Ở ĐÂY. Thẻ được dựng lúc đăng nhập từ
	 *    chính bảng ấy; hỏi lại giữa chừng thì một lượt sửa phân quyền đang diễn ra sẽ cho hai
	 *    câu trả lời khác nhau trong cùng một lượt bấm. Muốn quyền mới có hiệu lực thì đăng
	 *    nhập lại — thẻ sống 12 giờ.
	 */
	public static function duoc( $khoa, $viec ) {
		if ( ! self::$toi ) { return false; }
		$k = strtolower( trim( (string) $khoa ) );
		$b = isset( self::$toi['bans'][ $k ] ) ? self::$toi['bans'][ $k ] : null;
		if ( ! $b ) { return false; }
		if ( ! isset( self::VIEC[ $viec ] ) ) { return false; }
		/* Thẻ cũ (phát trước bản này) chưa có bảng quyền — lui về đúng một việc duyệt tạm ứng
		   mà nó có, chứ không cho qua hết. Người dùng đăng nhập lại là có đủ. */
		if ( ! isset( $b['quyen'] ) || ! is_array( $b['quyen'] ) ) {
			return ( 'duyet' === $viec ) && ! empty( $b['duyet'] );
		}
		return ! empty( $b['quyen'][ $viec ] );
	}

	/** Những bản người này làm được VIỆC ấy. */
	public static function ban_lam_duoc( $viec ) {
		$ra = array();
		if ( ! self::$toi ) { return $ra; }
		foreach ( (array) self::$toi['bans'] as $khoa => $b ) {
			if ( self::duoc( $khoa, $viec ) ) { $ra[] = $khoa; }
		}
		return $ra;
	}

	/* ═══════════════════════════════════════════════════════════════ AI ĐƯỢC VÀO CỬA */

	/** Hai sổ ngoại lệ — xem `duoc_vao()`. Lưu tên đã hạ chữ thường. */
	const O_THEM = 'vhcpt_vao_them';
	const O_CHAN = 'vhcpt_vao_chan';

	/**
	 * NGƯỜI NÀY CÓ ĐƯỢC VÀO TRANG TỔNG KHÔNG.
	 *
	 * ══════════════════════════════════════════════════════════════════════════════════════════
	 * Anh Thắng 14/09/2026: *"Trang tổng thêm phần cấu hình để đẩy nhân sự kế toán, và quản lý
	 * qua để duyệt đơn và xử lý đơn, nhân viên thì không cần"*.
	 *
	 * 🔴 LUẬT NỀN LÀ THEO QUYỀN, KHÔNG PHẢI THEO DANH SÁCH TÊN. Nếu cửa vào là một danh sách tên
	 *    khai tay thì mỗi lần tuyển một kế toán mới, người ấy khai đủ quyền bên trang mảng, làm
	 *    được mọi việc ở đó — nhưng trang tổng vẫn chối, cho tới khi có ai nhớ ra là phải vào
	 *    wp-admin thêm tên. Cái "có ai nhớ ra" đó là thứ không bao giờ nên nằm trong một quy
	 *    trình tiền bạc.
	 *
	 *    Nên: ai có ÍT NHẤT MỘT quyền xử lý đơn (duyệt · cấp tiền · trả lại · quyết toán · xuất
	 *    MISA) ở bất kỳ mảng nào thì vào được. Đúng là kế toán và quản lý. Nhân viên thuần chỉ
	 *    lập đơn, không có quyền nào trong số ấy — nên họ không vào, đúng như anh nói, mà không
	 *    ai phải khai gì.
	 *
	 * ⚠️ HAI SỔ NGOẠI LỆ cho những ca luật nền không với tới:
	 *      · CHO VÀO THÊM — người cần theo dõi mà không cần bấm gì (giám đốc, kế toán trưởng
	 *        đang bàn giao). Họ vào và chỉ xem được.
	 *      · CHẶN — người có quyền bên trang mảng nhưng tạm không cho vào trang tổng (nghỉ
	 *        việc chưa xoá sổ, đang bàn giao).
	 *    Chặn thắng cho-vào: hai sổ cùng có tên thì phía cấm phải thắng, luôn luôn.
	 * ══════════════════════════════════════════════════════════════════════════════════════════
	 *
	 * @param array  $bans sổ mảng của người ấy (như `tim_theo_pin()` trả về)
	 * @param string $ten  tên người ấy
	 */
	public static function duoc_vao( $bans, $ten ) {
		$k = mb_strtolower( trim( (string) $ten ) );
		if ( in_array( $k, self::so( self::O_CHAN ), true ) ) { return false; }
		if ( in_array( $k, self::so( self::O_THEM ), true ) ) { return true; }
		return self::co_viec_nao( $bans );
	}

	/** Sổ mảng này có quyền xử lý đơn ở mảng nào không. */
	public static function co_viec_nao( $bans ) {
		foreach ( (array) $bans as $b ) {
			$q = isset( $b['quyen'] ) && is_array( $b['quyen'] ) ? $b['quyen'] : array();
			foreach ( self::VIEC as $viec => $hd ) {
				if ( ! empty( $q[ $viec ] ) ) { return true; }
			}
		}
		return false;
	}

	/** Đọc một sổ ngoại lệ. Tên đã hạ chữ thường, không trùng, không rỗng. */
	public static function so( $o ) {
		$v = get_option( $o, array() );
		$ra = array();
		foreach ( (array) $v as $x ) {
			$x = mb_strtolower( trim( (string) $x ) );
			if ( '' !== $x && ! in_array( $x, $ra, true ) ) { $ra[] = $x; }
		}
		return $ra;
	}

	/** Ghi một sổ ngoại lệ. */
	public static function dat_so( $o, $ds ) {
		$ra = array();
		foreach ( (array) $ds as $x ) {
			$x = mb_strtolower( trim( (string) $x ) );
			if ( '' !== $x && ! in_array( $x, $ra, true ) ) { $ra[] = $x; }
		}
		update_option( $o, $ra );
	}

	/**
	 * MỌI NGƯỜI CỦA MỌI MẢNG — cho màn cấu hình trong wp-admin.
	 *
	 * 🔴 KHÔNG TRẢ PIN RA. Màn này chạy trong wp-admin, nhưng luật vẫn là luật: PIN không rời
	 *    khỏi chỗ nó nằm, không đi vào HTML, không nằm trong ảnh chụp màn hình gửi cho nhau.
	 *
	 * ⚠️ GOM THEO TÊN: một người thường có mặt ở nhiều mảng (cùng PIN, cùng tên). Bày ba dòng
	 *    cho một người là ba ô tích cho một quyết định, và chúng sẽ lệch nhau.
	 *
	 * @return array [ tên => [ 'ten', 'bans' => [khoá => ['vai','quyen']], 'coViec' => bool ] ]
	 */
	public static function moi_nguoi() {
		$ra = array();
		foreach ( VHCPT_Ban::ds() as $khoa => $b ) {
			$lop_cfg = VHCPT_Ban::lop( $khoa, 'Cfg' );
			/* ⚠️ Gác CÙNG HÀM với lời gọi — luật `tools/test/kiem-goi-cheo.php`. */
			if ( ! $lop_cfg || ! class_exists( $lop_cfg ) || ! method_exists( $lop_cfg, 'get_users' ) ) {
				continue;
			}
			foreach ( (array) call_user_func( array( $lop_cfg, 'get_users' ) ) as $u ) {
				$ten = trim( (string) ( isset( $u['ten'] ) ? $u['ten'] : '' ) );
				if ( '' === $ten ) { continue; }
				$k = mb_strtolower( $ten );
				if ( ! isset( $ra[ $k ] ) ) { $ra[ $k ] = array( 'ten' => $ten, 'bans' => array() ); }
				$vai = self::vai_cua( $u );
				$ra[ $k ]['bans'][ $khoa ] = array( 'vai' => $vai, 'quyen' => self::bang_quyen( $khoa, $vai ) );
			}
		}
		foreach ( $ra as $k => $x ) {
			$ra[ $k ]['coViec'] = self::co_viec_nao( $x['bans'] );
			$ra[ $k ]['vao']    = self::duoc_vao( $x['bans'], $x['ten'] );
		}
		ksort( $ra );
		return $ra;
	}

	/* ══════════════════════════════════════════════════════════════════ THẺ PHIÊN */

	/**
	 * Phát thẻ. Thẻ là chuỗi ngẫu nhiên; những gì cần biết về người ấy nằm ở PHÍA MÁY CHỦ.
	 *
	 * 🔴 KHÔNG NHÉT VAI/QUYỀN VÀO CHÍNH CÁI THẺ. Thẻ đi qua tay người dùng; thứ gì nằm trong nó
	 *    là thứ họ sửa được. Vai nằm ở transient, thẻ chỉ là chìa khoá tra.
	 */
	public static function phat_the( $nguoi ) {
		$the = wp_generate_password( 40, false, false );
		set_transient( 'vhcpt_the_' . hash( 'sha256', $the ), array(
			'ten'  => (string) $nguoi['ten'],
			'bans' => (array) $nguoi['bans'],
			'luc'  => time(),
			/* 🔴 ĐÓNG DẤU BẢN VÀO THẺ — xem `lam_moi_neu_cu()`. */
			'ban'  => defined( 'VHCPT_VERSION' ) ? VHCPT_VERSION : '',
		), self::HAN );
		return $the;
	}

	/**
	 * THẺ PHÁT TRƯỚC LƯỢT NÂNG CẤP THÌ TỰ LÀM MỚI QUYỀN.
	 *
	 * ══════════════════════════════════════════════════════════════════════════════════════════
	 * 🔴 ĐÃ CẮN THẬT 14/09/2026, HAI LẦN LIỀN. Bản 1.7.1 vá đúng lỗi đọc nhầm ô `vaiTro`, anh
	 *    Thắng nạp lên, F5 — và màn vẫn ghi *"KVC · (chưa có vai) · chỉ xem"*. *"chưa thấy gì"*.
	 *
	 *    Vì vai và quyền được tra MỘT LẦN lúc đăng nhập rồi cất vào thẻ phiên (transient, sống
	 *    12 giờ). Thẻ anh đang cầm phát ra từ bản CŨ, mang đúng cái vai rỗng của lỗi vừa vá. Vá
	 *    xong, cài xong, F5 xong — thẻ cũ vẫn nói y như trước.
	 *
	 *    Đăng xuất rồi vào lại là hết. Nhưng KHÔNG AI ĐOÁN RA ĐIỀU ĐÓ: màn không nói gì về thẻ,
	 *    nó nói về phân quyền. Người dùng sẽ kết luận bản vá không chạy — và đó là kết luận hợp
	 *    lý với những gì họ nhìn thấy.
	 *
	 * ⚠️ NÊN: thẻ mang dấu BẢN phát ra nó. Bản đang chạy khác dấu ấy -> tra lại vai và quyền từ
	 *    sổ các mảng NGAY trong lượt gọi này, rồi ghi đè vào thẻ. Người dùng không phải làm gì.
	 *
	 * 🔴 TRA LẠI THEO TÊN, KHÔNG ĐÒI PIN LẠI. Thẻ này đã được xác thực bằng PIN lúc phát; tên
	 *    trong thẻ do máy chủ ghi, không phải thứ người dùng gửi lên. Đòi PIN lần nữa chỉ để đọc
	 *    lại chính bảng phân quyền của họ là bắt người ta trả giá cho lỗi của mình.
	 *
	 * ⚠️ CHỈ LÀM MỚI KHI ĐỔI BẢN, KHÔNG LÀM MỖI LƯỢT GỌI. Hỏi lại bảng phân quyền giữa chừng thì
	 *    một lượt sửa phân quyền đang diễn ra sẽ cho hai câu trả lời khác nhau trong cùng một
	 *    lượt bấm — lý do ban đầu người ta cất quyền vào thẻ. Đổi bản là mốc rõ ràng và hiếm.
	 * ══════════════════════════════════════════════════════════════════════════════════════════
	 *
	 * @return array người (đã làm mới nếu cần)
	 */
	public static function lam_moi_neu_cu( $the, $ng ) {
		$ban_nay = defined( 'VHCPT_VERSION' ) ? VHCPT_VERSION : '';
		$ban_the = isset( $ng['ban'] ) ? (string) $ng['ban'] : '';
		if ( '' !== $ban_nay && $ban_the === $ban_nay ) { return $ng; }

		$ten = trim( (string) ( isset( $ng['ten'] ) ? $ng['ten'] : '' ) );
		if ( '' === $ten ) { return $ng; }

		$bans = self::bans_theo_ten( $ten );
		if ( ! $bans ) { return $ng; }   // không tra ra thì giữ nguyên, đừng tước quyền của ai

		$ng['bans'] = $bans;
		$ng['ban']  = $ban_nay;
		set_transient( 'vhcpt_the_' . hash( 'sha256', (string) $the ), $ng, self::HAN );
		return $ng;
	}

	/**
	 * Dựng lại sổ mảng của một người theo TÊN — cùng hình dạng `tim_theo_pin()` trả về.
	 *
	 * ⚠️ ĐỂ Ở MỘT CHỖ RIÊNG chứ không chép lại thân `tim_theo_pin()`: hai bản chép tay của cùng
	 *    một việc là chỗ lệch nhau sau vài lượt sửa, mà lệch ở đây nghĩa là quyền của người này
	 *    khác nhau tuỳ họ vừa đăng nhập hay vừa được làm mới thẻ.
	 */
	public static function bans_theo_ten( $ten ) {
		$k = mb_strtolower( trim( (string) $ten ) );
		if ( '' === $k ) { return array(); }
		$bans = array();
		foreach ( VHCPT_Ban::ds() as $khoa => $b ) {
			$lop_cfg = VHCPT_Ban::lop( $khoa, 'Cfg' );
			/* ⚠️ Gác CÙNG HÀM với lời gọi — luật `tools/test/kiem-goi-cheo.php`. */
			if ( ! $lop_cfg || ! class_exists( $lop_cfg ) || ! method_exists( $lop_cfg, 'get_users' ) ) {
				continue;
			}
			foreach ( (array) call_user_func( array( $lop_cfg, 'get_users' ) ) as $u ) {
				if ( mb_strtolower( trim( (string) ( isset( $u['ten'] ) ? $u['ten'] : '' ) ) ) !== $k ) { continue; }
				$vai = self::vai_cua( $u );
				$bans[ $khoa ] = array(
					'vai'    => $vai,
					'coso'   => trim( (string) ( isset( $u['coso'] ) ? $u['coso'] : '' ) ),
					'boPhan' => trim( (string) ( isset( $u['boPhan'] ) ? $u['boPhan'] : '' ) ),
					'maNv'   => trim( (string) ( isset( $u['maNv'] ) ? $u['maNv'] : '' ) ),
					'duyet'  => self::duoc_duyet( $khoa, $vai ),
					'quyen'  => self::bang_quyen( $khoa, $vai ),
				);
				break;
			}
		}
		return $bans;
	}

	/** Tra thẻ. Trả về người, hoặc null. */
	public static function nguoi_cua_the( $the ) {
		$the = trim( (string) $the );
		if ( '' === $the ) { return null; }
		$v = get_transient( 'vhcpt_the_' . hash( 'sha256', $the ) );
		return is_array( $v ) ? $v : null;
	}

	public static function bo_the( $the ) {
		$the = trim( (string) $the );
		if ( '' === $the ) { return; }
		delete_transient( 'vhcpt_the_' . hash( 'sha256', $the ) );
	}

	/** Đặt người của lượt gọi này. */
	public static function dat_toi( $nguoi ) { self::$toi = is_array( $nguoi ) ? $nguoi : null; }

	public static function toi() { return self::$toi; }

	/** Tên người đang đăng nhập, hoặc chuỗi rỗng. */
	public static function ten() {
		return self::$toi ? trim( (string) self::$toi['ten'] ) : '';
	}

	/** Những bản người này được DUYỆT. Rỗng = không duyệt được gì. */
	public static function ban_duyet_duoc() {
		$ra = array();
		if ( ! self::$toi ) { return $ra; }
		foreach ( (array) self::$toi['bans'] as $khoa => $b ) {
			if ( ! empty( $b['duyet'] ) ) { $ra[] = $khoa; }
		}
		return $ra;
	}

	/** Những bản người này ĐỌC được (có mặt trong sổ của bản ấy). */
	public static function ban_doc_duoc() {
		if ( ! self::$toi ) { return array(); }
		return array_keys( (array) self::$toi['bans'] );
	}
}
