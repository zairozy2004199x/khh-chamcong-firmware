<?php
/**
 * ĐƠN VỊ — K&H và POSH đứng chung một hệ, nhưng KHÔNG nhìn thấy số của nhau.
 *
 * Anh Thắng 26/08/2026:
 *   *"Anh có thêm bộ Phận Posh sẽ lên chi trong này. Bộ phận khác nên sẽ tách biệt không xem
 *   được doanh thu của nhau."*
 *   *"Kế toán Posh chỉ thấy chi phí Posh. Còn kế toán cá nhân được set thấy cả bộ phận thì nhìn
 *   chung luôn."*
 *   *"Bên Posh người duyệt là Quản Lý Posh."*
 *
 * ==========================================================================================
 * 🔴 VÌ SAO GỌI LÀ "ĐƠN VỊ" MÀ KHÔNG GỌI "BỘ PHẬN".
 *    Trong plugin này chữ "Bộ phận" ĐÃ CÓ CHỦ: nó là cột trên danh mục loại chi phí và trên
 *    tài khoản người dùng, mang nghĩa mảng chi phí của kế toán (Cơ sở · Kỹ thuật · Marketing ·
 *    Công tác · Setup · Văn phòng) và nó quyết định người ta vào được tab nào. Dùng lại đúng
 *    chữ ấy cho một thứ khác hẳn là hai khái niệm chồng lên nhau trong cùng một màn hình, và
 *    người đọc mã sáu tháng sau không có cách nào biết `boPhan` đang nói về cái nào.
 *    Nên trên màn ghi rõ **Đơn vị (K&H · POSH)**, trong mã là `don_vi` / `donVi`.
 *
 * ==========================================================================================
 * 🔴 HAI CỘT, HAI VIỆC KHÁC NHAU — đừng gộp:
 *
 *    "Đơn vị"      = NHÀ của người đó. Đơn họ lập mang đơn vị này. Một người một nhà.
 *    "Xem đơn vị"  = những đơn vị họ được ĐỌC. Có thể nhiều, có thể để trống.
 *
 *    Gộp làm một thì không dựng nổi "kế toán cá nhân nhìn chung cả hai": nhà thì phải là một
 *    (đơn họ lập phải rơi về đâu đó), mà tầm nhìn thì phải là nhiều.
 *
 * ⚠️ ĐỂ TRỐNG "Xem đơn vị" KHÔNG PHẢI LÀ "KHÔNG XEM GÌ" — nó là "theo mặc định của vai".
 *    Nếu để trống mà hiểu thành cấm hết thì ngay giây phút bản này lên, mọi kế toán đang chạy
 *    đều mù: 240 người dùng, không ai có ô đó, và không ai hiểu vì sao đơn biến mất. Một bản
 *    nâng cấp không được phép làm gãy thứ đang chạy để chờ người ta đi khai lại từng dòng.
 *    Mặc định: Admin · Kế toán · Quản lý -> XEM CẢ; còn lại -> chỉ nhà mình.
 *    Muốn siết kế toán POSH lại thì khai thẳng "POSH" vào ô đó — một dòng, cố ý, thấy được.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCP_DonVi {

	/** Nhà mặc định. Đơn cũ (chưa có cột) và người chưa khai đều rơi về đây. */
	const MAC_DINH = 'K&H';

	/**
	 * Vai được nhìn cả hệ khi ô "Xem đơn vị" còn trống.
	 *
	 * ⚠️ PHẢI LÀ TÊN VAI GỐC ĐÚNG NHƯ `VHCP_Cfg::VAI_GOC` VIẾT — 'Kế toán cá nhân' và
	 *    'Kế toán NCC', không phải 'Kế toán'. Viết sai một chữ thì `vai_goc()` đưa vai lạ về
	 *    'Nhân viên', và kế toán nào cũng chỉ còn thấy đơn do chính mình lập: danh sách trống
	 *    trơn, không câu lỗi nào, không ai đoán ra vì sao.
	 */
	const VAI_XEM_CA = array( 'Admin', 'Quản lý', 'Kế toán cá nhân', 'Kế toán NCC' );

	/* ====================================================================== danh sách */

	/**
	 * Các đơn vị đang có.
	 *
	 * ⚠️ ĐỌC TỪ CHÍNH BẢNG NGƯỜI DÙNG, không giữ một danh sách riêng. Hai danh sách là sớm
	 *    muộn lệch: ai đó khai "POSH " (thừa dấu cách) cho một tài khoản thì đơn vị ấy có
	 *    thật trong dữ liệu mà không có trong danh mục — và mọi ô lọc dựng từ danh mục sẽ
	 *    không bao giờ chạm tới đơn của họ.
	 */
	public static function ds() {
		$ra = array( self::MAC_DINH => 1 );
		foreach ( VHCP_Cfg::get_users() as $u ) {
			$d = self::chuan( isset( $u['donVi'] ) ? $u['donVi'] : '' );
			$ra[ $d ] = 1;
		}
		foreach ( self::ds_trong_don() as $d ) { $ra[ $d ] = 1; }
		$out = array_keys( $ra );
		sort( $out );
		return $out;
	}

	/** Đơn vị đang thật sự nằm trên các đơn — kể cả đơn vị không còn ai làm. */
	private static function ds_trong_don() {
		global $wpdb;
		$t = VHCP_DB::t( 'don' );
		$c = VHCP_DB::rows( "SELECT DISTINCT don_vi FROM $t" );
		$out = array();
		foreach ( (array) $c as $r ) {
			$d = trim( (string) ( isset( $r['don_vi'] ) ? $r['don_vi'] : '' ) );
			if ( '' !== $d ) { $out[] = $d; }
		}
		return $out;
	}

	/** Bỏ khoảng trắng thừa; rỗng -> nhà mặc định. KHÔNG hạ chữ thường: tên hiện lên màn. */
	public static function chuan( $x ) {
		$x = trim( (string) $x );
		return ( '' === $x ) ? self::MAC_DINH : $x;
	}

	/** So hai tên đơn vị: bỏ qua hoa/thường và khoảng trắng hai đầu. */
	public static function bang( $a, $b ) {
		return 0 === strcasecmp( self::chuan( $a ), self::chuan( $b ) );
	}

	/* ====================================================================== của ai */

	/** Dòng cấu hình của một người, tra theo TÊN (tên là khoá của bảng người dùng). */
	private static function dong_nguoi( $ten ) {
		$k = mb_strtolower( trim( (string) $ten ) );
		if ( '' === $k ) { return null; }
		foreach ( VHCP_Cfg::get_users() as $u ) {
			if ( mb_strtolower( trim( (string) $u['ten'] ) ) === $k ) { return $u; }
		}
		return null;
	}

	/** NHÀ của một người. Không tìm thấy hồ sơ -> nhà mặc định. */
	public static function cua_nguoi( $ten ) {
		$u = self::dong_nguoi( $ten );
		return self::chuan( ( $u && isset( $u['donVi'] ) ) ? $u['donVi'] : '' );
	}

	/** Nhà của người đang gọi. */
	public static function cua_toi() {
		return self::cua_nguoi( VHCP_Auth::nguoi() );
	}

	/**
	 * Những đơn vị người đang gọi ĐƯỢC ĐỌC.
	 *
	 * @return array|null Mảng tên đơn vị · `null` nghĩa là XEM CẢ (không lọc gì).
	 *
	 * 🔴 TRẢ `null` CHỨ KHÔNG TRẢ "toàn bộ danh sách". Trả danh sách thì lời gọi phía sau phải
	 *    tin rằng danh sách ấy đủ — mà nó dựng từ bảng người dùng, nên một đơn mang đơn vị lạ
	 *    (nhập từ sổ cũ, người khai xong rồi nghỉ) sẽ rơi ra ngoài và BIẾN MẤT khỏi màn của
	 *    Admin. `null` = không lọc, nên không có gì rơi ra được.
	 */
	public static function xem_duoc() {
		$u = self::dong_nguoi( VHCP_Auth::nguoi() );
		$xem = ( $u && isset( $u['xemDonVi'] ) ) ? trim( (string) $u['xemDonVi'] ) : '';
		if ( '' !== $xem ) {
			$ds = array();
			foreach ( explode( ',', $xem ) as $x ) {
				$x = trim( $x );
				if ( '' !== $x ) { $ds[] = $x; }
			}
			if ( $ds ) { return $ds; }
		}
		/* Ô để trống -> theo mặc định của VAI GỐC (vai tự tạo đã quy về gốc ở cửa vào). */
		if ( in_array( VHCP_Auth::vai_tro(), self::VAI_XEM_CA, true ) ) { return null; }
		return array( self::cua_toi() );
	}

	/** Người đang gọi có được đọc đơn vị này không. */
	public static function duoc_xem( $don_vi ) {
		$ds = self::xem_duoc();
		if ( null === $ds ) { return true; }
		foreach ( $ds as $x ) { if ( self::bang( $x, $don_vi ) ) { return true; } }
		return false;
	}

	/**
	 * ĐIỀU KIỆN SQL lọc theo đơn vị — cho những chỗ lọc TRƯỚC `LIMIT`.
	 *
	 * 🔴 LỌC TRONG SQL, KHÔNG LỌC SAU KHI ĐÃ LẤY. Câu tìm đơn có `LIMIT 60`: lấy 60 dòng rồi
	 *    mới bỏ đơn của bên kia là kế toán POSH gõ một từ khoá phổ biến và nhận về 3 kết quả,
	 *    trong khi bên họ có 50 đơn khớp — 57 chỗ kia đã bị đơn của K&H chiếm mất rồi.
	 *
	 * ⚠️ Ô RỖNG PHẢI TÍNH LÀ NHÀ MẶC ĐỊNH. Mọi đơn lập trước bản này đều có `don_vi = ''`;
	 *    quên nhánh đó là toàn bộ sổ cũ biến mất khỏi màn của chính K&H.
	 *
	 * @return array|null array( 'sql' => '(...)', 'tv' => array(...) ) · null = không lọc gì
	 */
	public static function dieu_kien_sql( $cot = 'd.don_vi' ) {
		$ds = self::xem_duoc();
		if ( null === $ds ) { return null; }
		$ve = array();
		$tv = array();
		foreach ( $ds as $x ) {
			$ve[] = "LOWER(TRIM($cot)) = LOWER(%s)";
			$tv[] = self::chuan( $x );
			if ( self::bang( $x, self::MAC_DINH ) ) { $ve[] = "TRIM($cot) = ''"; }
		}
		if ( ! $ve ) { return null; }
		return array( 'sql' => '( ' . implode( ' OR ', $ve ) . ' )', 'tv' => $tv );
	}

	/** Người đang gọi có nhìn được nhiều hơn một đơn vị không (để màn biết có nên tách khối). */
	public static function nhieu_don_vi() {
		$ds = self::xem_duoc();
		if ( null === $ds ) { return count( self::ds() ) > 1; }
		return count( $ds ) > 1;
	}

	/* ====================================================================== chốt ở cửa API */

	/**
	 * CHỐT MỘT LƯỢT CHO MỌI HÀM CÓ MÃ ĐƠN — gọi từ `VHCP_Api::handle()`.
	 *
	 * 🔴 VÌ SAO KHÔNG RẢI CHỐT VÀO TỪNG HÀM. Riêng `VHCP_Don` đã có 15 hàm đụng vào một đơn mà
	 *    KHÔNG đi qua `loi_khong_phai_don_minh()`: duyệt · cấp tiền · trả lại · xác nhận quyết
	 *    toán · tất toán · xoá · và bốn bản "nhiều đơn một lượt". Chúng không gác quyền sở hữu
	 *    vì trước giờ chỉ cần quyền theo VAI là đủ — mà đơn vị thì vuông góc với vai: Quản lý
	 *    POSH vẫn là Quản lý, vẫn đủ quyền bấm duyệt, chỉ là không được duyệt đơn của K&H.
	 *    Rải 15 chốt là 15 chỗ để quên, và hàm thứ 16 viết sau này thì chắc chắn quên.
	 *
	 * ⚠️ ĐỌC TÊN THAM SỐ TỪ CHÍNH CHỮ KÝ HÀM, không giữ một bảng "hàm nào có mã đơn ở đâu".
	 *    Bảng ấy là thứ hai nơi phải sửa mỗi lần thêm hàm — và nơi thứ hai thì sớm muộn lệch.
	 *    Quy ước trong mã này đã sẵn nhất quán: `$ma_don` · `$ma_dons` · `$id` (id dòng).
	 *
	 * @return string '' nếu được phép, ngược lại là câu chối.
	 */
	public static function chan_theo_ham( $callable, $args ) {
		if ( ! is_array( $callable ) || 2 !== count( $callable ) ) { return ''; }
		if ( null === self::xem_duoc() ) { return ''; }   // xem cả -> khỏi soi
		try {
			$rf = new ReflectionMethod( $callable[0], $callable[1] );
		} catch ( ReflectionException $e ) {
			return '';
		}
		$ts = $rf->getParameters();
		if ( ! $ts || ! array_key_exists( 0, $args ) ) { return ''; }
		$ten = $ts[0]->getName();

		if ( 'ma_don' === $ten ) {
			return self::vi_sao_khong_dung( (string) $args[0] );
		}
		if ( 'ma_dons' === $ten ) {
			foreach ( (array) $args[0] as $m ) {
				$loi = self::vi_sao_khong_dung( (string) $m );
				if ( '' !== $loi ) { return $loi; }
			}
			return '';
		}
		/* `$id` = id DÒNG chi. Chặn ở đây nữa dù `loi_khong_phai_dong_minh()` cũng chặn: hai
		   lớp không tốn gì, mà một ngày nào đó ai bỏ lớp kia thì vẫn còn lớp này. */
		if ( 'id' === $ten && is_array( $callable ) && 'VHCP_Don' === $callable[0] ) {
			$l = VHCP_Don::line_row( $args[0] );
			if ( ! $l ) { return ''; }
			return self::vi_sao_khong_dung( (string) $l['ma_don'] );
		}
		return '';
	}

	/* ====================================================================== của đơn */

	/**
	 * Đơn vị của một đơn.
	 *
	 * ⚠️ Đơn cũ có cột rỗng (bảng mới nới, dữ liệu cũ chưa có gì) -> `chuan()` đưa về nhà mặc
	 *    định. Đó là hành vi ĐÚNG: trước khi có POSH thì mọi đơn đều là đơn K&H.
	 */
	public static function cua_don( $ma_don ) {
		$d = VHCP_Don::don_row( $ma_don );
		if ( ! $d ) { return self::MAC_DINH; }
		return self::chuan( isset( $d['don_vi'] ) ? $d['don_vi'] : '' );
	}

	/**
	 * Chối một câu nếu người đang gọi không được đụng vào đơn này. '' là được phép.
	 *
	 * 🔴 MỘT CÂU CHO CẢ "không có đơn" LẪN "không được xem". Nói khác nhau là ai cũng dò được
	 *    bên kia có bao nhiêu đơn bằng cách đổi mã trên thanh địa chỉ — mà số đơn của POSH cũng
	 *    là thứ anh Thắng vừa bảo phải tách.
	 */
	public static function vi_sao_khong_dung( $ma_don ) {
		$d = VHCP_Don::don_row( $ma_don );
		if ( ! $d ) { return 'Không tìm thấy đơn'; }
		if ( ! self::duoc_xem( self::chuan( isset( $d['don_vi'] ) ? $d['don_vi'] : '' ) ) ) {
			return 'Không tìm thấy đơn';
		}
		return '';
	}
}
