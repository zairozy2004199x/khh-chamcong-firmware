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
		'qtNcc'  => 'duyetNCC',
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
				$vai = trim( (string) ( isset( $u['vai'] ) ? $u['vai'] : '' ) );
				$bans[ $khoa ] = array(
					'vai'   => $vai,
					'coso'  => trim( (string) ( isset( $u['coso'] ) ? $u['coso'] : '' ) ),
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
	 * Vai này có được duyệt tạm ứng ở bản ấy không — HỎI CHÍNH BẢNG PHÂN QUYỀN CỦA BẢN ẤY.
	 *
	 * ⚠️ Không có đường lui "vai tên là Quản lý thì cho qua". Bảng phân quyền sửa được trên màn,
	 *    và anh Thắng đã sửa nó thật; đoán theo tên vai là nói ngược lại thứ người ta vừa khai.
	 */
	public static function duoc_duyet( $khoa, $vai ) {
		$vai = trim( (string) $vai );
		if ( '' === $vai ) { return false; }
		$lop_cfg = VHCPT_Ban::lop( $khoa, 'Cfg' );
		if ( ! $lop_cfg || ! class_exists( $lop_cfg ) || ! method_exists( $lop_cfg, 'get_quyen' ) ) {
			return false;
		}
		$q = (array) call_user_func( array( $lop_cfg, 'get_quyen' ) );
		if ( ! isset( $q[ self::QUYEN_DUYET ] ) || ! is_array( $q[ self::QUYEN_DUYET ] ) ) { return false; }
		$hang = $q[ self::QUYEN_DUYET ];
		return ! empty( $hang[ $vai ] );
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
		), self::HAN );
		return $the;
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
