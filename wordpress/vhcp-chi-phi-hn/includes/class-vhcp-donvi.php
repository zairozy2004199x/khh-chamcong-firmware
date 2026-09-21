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

class VHCPHN_DonVi {

	/** Nhà mặc định. Đơn cũ (chưa có cột) và người chưa khai đều rơi về đây. */
	const MAC_DINH = 'K&H';

	/**
	 * Vai được nhìn cả hệ khi ô "Xem đơn vị" còn trống.
	 *
	 * ⚠️ PHẢI LÀ TÊN VAI GỐC ĐÚNG NHƯ `VHCPHN_Cfg::VAI_GOC` VIẾT — 'Kế toán cá nhân' và
	 *    'Kế toán NCC', không phải 'Kế toán'. Viết sai một chữ thì `vai_goc()` đưa vai lạ về
	 *    'Nhân viên', và kế toán nào cũng chỉ còn thấy đơn do chính mình lập: danh sách trống
	 *    trơn, không câu lỗi nào, không ai đoán ra vì sao.
	 */
	const VAI_XEM_CA = array( 'Admin', 'Quản lý', 'Kế toán cá nhân', 'Kế toán NCC' );

	/* ====================================================================== danh sách */

	/**
	 * Các đơn vị đang có.
	 *
	 * ⚠️ ĐỌC TỪ CHÍNH DỮ LIỆU, không giữ một danh sách riêng. Hai danh sách là sớm muộn lệch:
	 *    ai đó khai "POSH " (thừa dấu cách) ở một chỗ thì đơn vị ấy có thật trong dữ liệu mà
	 *    không có trong danh mục — và mọi ô lọc dựng từ danh mục sẽ không bao giờ chạm tới
	 *    đơn của họ.
	 *
	 * 🔴 PHẢI ĐỌC CẢ DANH MỤC CƠ SỞ, VÀ ĐỌC TRƯỚC HAI NGUỒN KIA.
	 *    Anh Thắng 08/09/2026, sau khi đã khai xong khối "ĐƠN VỊ POSH · 1 cơ sở" ở danh mục
	 *    cơ sở: *"đơn vị posh chưa có"* — hộp tích "Xem đơn vị" vẫn trơ mỗi K&H.
	 *
	 *    Vì hàm này trước chỉ nhìn bảng NGƯỜI DÙNG và cột `don_vi` trên ĐƠN. Cả hai đều là
	 *    thứ có SAU: muốn có người POSH thì phải tích được "Xem đơn vị POSH", muốn tích được
	 *    thì POSH phải nằm trong danh sách này, muốn nằm trong danh sách thì phải có người
	 *    POSH. Vòng luẩn quẩn khoá chặt — không cách nào mở được đơn vị thứ hai.
	 *
	 *    Mà DANH MỤC CƠ SỞ mới đúng là nơi khai: `cua_coso()` đã lấy chính bảng ấy làm chốt
	 *    duy nhất cho câu "dòng chi này của bên nào". Nơi khai ranh giới và nơi liệt kê ranh
	 *    giới phải là một, không thì khai xong vẫn như chưa khai.
	 */
	public static function ds() {
		/* ⚠️ ĐỘT BIẾN TƯƠNG ĐƯƠNG, ghi lại để lần sau khỏi đuổi theo: bỏ hạt giống này KHÔNG
		   đổi kết quả hôm nay, vì `get_users()` luôn gieo lại tài khoản Admin với ô Đơn vị
		   trống, và ô trống thì `chuan()` đưa về đúng nhà mặc định. Giữ nó vì cái "luôn" ấy là
		   chuyện của MỘT hàm khác: ngày nào `get_users()` thôi gieo, mất K&H khỏi danh sách là
		   mọi dòng cũ (ô Đơn vị trống) rơi vào một đơn vị không ô lọc nào chạm tới — hỏng lặng
		   lẽ, không câu lỗi nào. `kiem-don-vi-moi-hien-ra.php` có phép đối chứng canh giả định
		   ấy, nên nếu nó gãy thì bài kiểm đỏ ở đúng chỗ gãy. */
		$ra = array( self::MAC_DINH => 1 );
		/* Danh mục cơ sở — NƠI KHAI. `cosoDonVi` đã qua `chuan()` lúc dựng, xem `cfg_static()`. */
		$cfg = VHCPHN_Cfg::cfg_static();
		foreach ( (array) ( isset( $cfg['cosoDonVi'] ) ? $cfg['cosoDonVi'] : array() ) as $d ) {
			$d = self::chuan( $d );
			$ra[ $d ] = 1;
		}
		foreach ( VHCPHN_Cfg::get_users() as $u ) {
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
		$t = VHCPHN_DB::t( 'don' );
		$c = VHCPHN_DB::rows( "SELECT DISTINCT don_vi FROM $t" );
		$out = array();
		foreach ( (array) $c as $r ) {
			$d = trim( (string) ( isset( $r['don_vi'] ) ? $r['don_vi'] : '' ) );
			if ( '' !== $d ) { $out[] = $d; }
		}
		return $out;
	}

	/* ══════════════════════════════════════════════════════════════════════════════════════
	 * MỘT ĐƠN = MỘT CƠ SỞ, HAY MỘT ĐƠN NHIỀU CƠ SỞ — TUỲ ĐƠN VỊ.
	 *
	 * Anh Thắng 09/09/2026: *"đối với kvc chọn theo cơ sở để lên đơn, còn đối với [POSH], 1 đơn
	 * sẽ nhiều cơ sở cho từng chi phí nhỏ"*. Chốt trục phân biệt: *"theo trục đơn vị"*.
	 *
	 * 🔴 KHÔNG PHẢI DỰNG MỚI — LÀ MỞ LẠI. Bảng tạm ứng khoá theo CẶP `(ma_don, coso)` ngay từ
	 *    đầu, và màn đơn vẫn trả `tamUng` dạng bảng tra theo cơ sở. Luật "một đơn = một cơ sở"
	 *    là lớp khoá GẮN THÊM ngày 01/09/2026, đúng lúc ấy đúng cho KVC: tiền giao cho một người
	 *    ở một gian rồi đối chiếu theo gian đó, nên xin tạm ứng nơi này mà chi nơi khác là sai.
	 *    Mảng POSH đi ngược lại: một đợt chi rải qua nhiều gian, mỗi dòng nhỏ một gian.
	 *
	 * ⚠️ ĐỐI CHIẾU THỪA/THIẾU VẪN TÍNH THEO CẢ ĐƠN, không tách theo gian — anh Thắng chốt
	 *    *"tính theo 1 đơn"*. Phần cộng tiền sẵn đã cộng hết mọi cơ sở của đơn nên không đụng.
	 *
	 * ⚠️ KHAI THÊM ĐƠN VỊ thì đặt khoá `vhcphn_dv_nhieu_coso` (danh sách ngăn bằng dấu phẩy).
	 *    Để mặc định trong hằng chứ không rải chữ "POSH" khắp mã: đổi một chỗ là xong.
	 * ══════════════════════════════════════════════════════════════════════════════════════ */

	/** Đơn vị nào cho một đơn ghép nhiều cơ sở — mặc định, đổi được bằng khoá cấu hình. */
	const NHIEU_COSO_MAC_DINH = 'POSH';

	/** Đơn vị này có cho một đơn ghép nhiều cơ sở không. */
	public static function nhieu_coso( $don_vi ) {
		$ds = get_option( 'vhcphn_dv_nhieu_coso', null );
		if ( ! is_string( $ds ) || '' === trim( $ds ) ) { $ds = self::NHIEU_COSO_MAC_DINH; }
		foreach ( explode( ',', $ds ) as $x ) {
			if ( '' !== trim( $x ) && self::bang( $x, $don_vi ) ) { return true; }
		}
		return false;
	}

	/** Đơn này có được ghép nhiều cơ sở không — tra theo đơn vị ghi trên chính đơn ấy. */
	public static function don_nhieu_coso( $ma_don ) {
		$d = VHCPHN_Don::don_row( $ma_don );
		if ( ! $d ) { return false; }
		return self::nhieu_coso( self::chuan( isset( $d['don_vi'] ) ? $d['don_vi'] : '' ) );
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
		foreach ( VHCPHN_Cfg::get_users() as $u ) {
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
		return self::cua_nguoi( VHCPHN_Auth::nguoi() );
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
		$u = self::dong_nguoi( VHCPHN_Auth::nguoi() );
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
		if ( in_array( VHCPHN_Auth::vai_tro(), self::VAI_XEM_CA, true ) ) { return null; }
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
	 * CHỐT MỘT LƯỢT CHO MỌI HÀM CÓ MÃ ĐƠN — gọi từ `VHCPHN_Api::handle()`.
	 *
	 * 🔴 VÌ SAO KHÔNG RẢI CHỐT VÀO TỪNG HÀM. Riêng `VHCPHN_Don` đã có 15 hàm đụng vào một đơn mà
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

		/* ⚠️ HAI NHÁNH DƯỚI CHỈ DÀNH CHO `VHCPHN_Don`. `VHCPHN_Mk::add_line()` cũng nhận `$ma_don`,
		   nhưng đó là mã ĐỢT MARKETING — tra nó trong bảng đơn vận hành thì không bao giờ
		   thấy, và người dùng nhận "Không tìm thấy đơn" cho một thao tác hoàn toàn hợp lệ.
		   Chỉ cắn người BỊ giới hạn đơn vị (ai xem cả thì đã thoát ở dòng `xem_duoc()` phía
		   trên) — tức đúng người POSH sắp dùng. Mảng khác đi tiếp xuống chốt `don_vi_cua()`. */
		$la_don = ( 'VHCPHN_Don' === $callable[0] );
		if ( 'ma_don' === $ten && $la_don ) {
			return self::vi_sao_khong_dung( (string) $args[0] );
		}
		if ( 'ma_dons' === $ten && $la_don ) {
			foreach ( (array) $args[0] as $m ) {
				$loi = self::vi_sao_khong_dung( (string) $m );
				if ( '' !== $loi ) { return $loi; }
			}
			return '';
		}
		/* `$id` = id DÒNG chi. Chặn ở đây nữa dù `loi_khong_phai_dong_minh()` cũng chặn: hai
		   lớp không tốn gì, mà một ngày nào đó ai bỏ lớp kia thì vẫn còn lớp này. */
		if ( 'id' === $ten && $la_don ) {
			$l = VHCPHN_Don::line_row( $args[0] );
			if ( ! $l ) { return ''; }
			return self::vi_sao_khong_dung( (string) $l['ma_don'] );
		}

		/* 🔴 BỐN MẢNG CÒN LẠI ĐI QUA ĐÚNG CỬA NÀY (anh Thắng 08/09/2026: *"dùng chung web chi
		   phí, nhưng 2 bộ phận không nhìn thấy nhau"*).

		   Sổ chi phí · Kỹ thuật · Marketing · Công tác/Setup gộp lại hơn 60 hàm đụng vào một
		   bản ghi. Rải chốt vào từng hàm là 60 chỗ để quên, và hàm thứ 61 viết sau này thì
		   chắc chắn quên — đúng lý do khối 🔴 ở đầu hàm đã nêu cho mảng Đơn.

		   Mỗi lớp tự khai `don_vi_cua( $khoa )`: nó biết bản ghi của mình neo vào đâu (cơ sở
		   hay người tạo), còn chỗ này chỉ hỏi và so. Thêm mảng thứ năm thì khai một hàm là
		   xong, không phải đụng vào đây.

		   ⚠️ `null` = KHÔNG TÌM THẤY BẢN GHI -> cho qua, để hàm thật trả câu lỗi của nó. Chối
		      ở đây thì người dùng nhận "không được xem" cho một thứ không tồn tại, và đó lại
		      là cách dò xem bên kia có bản ghi nào — thứ vừa phải bịt. */
		if ( method_exists( $callable[0], 'don_vi_cua' ) ) {
			/* Truyền CẢ TÊN THAM SỐ, không chỉ giá trị. `$id` của `VHCPHN_SoChi` là một dòng
			   sổ, còn `$id` của `VHCPHN_Mk` là một dòng trong đợt — hai thứ tra ở hai bảng
			   khác nhau. Đoán từ giá trị thì lúc đoán trượt sẽ trả `null`, mà `null` là
			   CHO QUA: một lần đoán trượt là một dòng tiền sửa được xuyên hai bên sổ. */
			$dv = call_user_func( array( $callable[0], 'don_vi_cua' ), $args[0], $ten );
			foreach ( ( is_array( $dv ) ? $dv : array( $dv ) ) as $x ) {
				if ( null !== $x && ! self::duoc_xem( $x ) ) { return 'Không tìm thấy dữ liệu'; }
			}
		}
		return '';
	}

	/**
	 * AI ĐANG KHAI "XEM ĐƠN VỊ" LẠC RA NGOÀI DANH SÁCH — trả [ tên người => tên khai lạc ].
	 *
	 * Anh Thắng 08/09/2026: *"Làm phần phân quyền ai xem được"*.
	 *
	 * 🔴 KHAI SAI Ô NÀY HỎNG THEO KIỂU IM LẶNG NHẤT. Ô "Xem đơn vị" trước là ô gõ tay; gõ
	 *    "POS", hay nhầm sang tên CƠ SỞ ("POSH HCM"), thì tên ấy không khớp đơn vị nào — người
	 *    đó không xem được gì, màn trắng trơn, không câu lỗi nào. Người khai thì tin là xong.
	 *
	 *    Bản 1.91.0 đổi ô ấy thành hộp tích nên khai mới không lạc được nữa. Nhưng dòng đã khai
	 *    từ trước vẫn nằm đó, và chúng chính là những dòng cần soi.
	 *
	 * ⚠️ KHÔNG TỰ RỬA. Đơn vị mới có thể vừa khai cho một người mà chưa ai/đơn nào mang nó, nên
	 *    `ds()` chưa thấy. Rửa là xoá mất phân quyền vừa đặt, và tệ hơn nữa là không bao giờ
	 *    tạo được đơn vị mới. Chỉ BÁO, để người khai tự quyết.
	 */
	public static function ai_khai_lac() {
		$co = array();
		foreach ( self::ds() as $d ) { $co[ mb_strtolower( trim( $d ) ) ] = 1; }
		$ra = array();
		foreach ( VHCPHN_Cfg::get_users() as $u ) {
			$ten = trim( (string) ( isset( $u['ten'] ) ? $u['ten'] : '' ) );
			$xem = trim( (string) ( isset( $u['xemDonVi'] ) ? $u['xemDonVi'] : '' ) );
			/* ⚠️ ĐỘT BIẾN TƯƠNG ĐƯƠNG, ghi lại để lần sau khỏi đuổi theo: bỏ vế `'' === $xem`
			   KHÔNG đổi kết quả — `explode( ',', '' )` trả về một phần tử rỗng, và vòng dưới
			   `continue` ngay ở nó, nên `$lac` vẫn rỗng. Giữ vế ấy vì nó nói thẳng ra ý "bỏ
			   trống là hợp lệ", và vì nó cắt hẳn một vòng lặp cho phần lớn tài khoản. */
			if ( '' === $ten || '' === $xem ) { continue; }
			$lac = array();
			foreach ( explode( ',', $xem ) as $x ) {
				$x = trim( $x );
				if ( '' === $x ) { continue; }
				if ( ! isset( $co[ mb_strtolower( $x ) ] ) ) { $lac[] = $x; }
			}
			if ( $lac ) { $ra[] = array( 'ten' => $ten, 'lac' => implode( ', ', $lac ) ); }
		}
		return $ra;
	}

	/* ====================================================================== của cơ sở */

	/**
	 * ĐƠN VỊ CỦA MỘT CƠ SỞ — chốt DUY NHẤT cho mọi mảng chi phí gắn với cơ sở.
	 *
	 * Anh Thắng 08/09/2026: *"dùng chung web chi phí, nhưng 2 bộ phận không nhìn thấy nhau"*,
	 * và *"cơ sở lấy từ bên posh là các cơ sở posh đang hoạt động"*.
	 *
	 * 🔴 VÌ SAO RANH GIỚI LÀ CƠ SỞ, KHÔNG PHẢI MỘT CỘT `don_vi` RIÊNG TRÊN TỪNG BẢNG.
	 *    Bốn mảng còn lại (Sổ chi phí · Kỹ thuật · Marketing · Công tác/Setup) nằm ở bốn cặp
	 *    bảng riêng. Thêm cột vào cả bốn thì có bốn chỗ phải nhớ GHI lúc tạo, bốn chỗ phải lấp
	 *    cho dữ liệu cũ, và mảng thứ năm viết sau này chắc chắn quên — mà một dòng quên ghi là
	 *    một khoản chi của POSH nằm trong sổ của K&H, không ai thấy để sửa.
	 *
	 *    Cơ sở thì mảng nào cũng có, đã có sẵn trên mọi dòng, và nó đúng theo nghĩa nghiệp vụ:
	 *    tiền chi cho gian hàng của POSH là tiền của POSH, bất kể ai gõ vào máy. Một nguồn
	 *    sự thật, không có gì để quên ghi, và dữ liệu cũ tự đúng — cơ sở cũ đều là cơ sở K&H.
	 *
	 * ⚠️ CƠ SỞ LẠ (gõ tay, nhập từ sổ cũ, đã xoá khỏi danh mục) -> nhà mặc định, KHÔNG phải
	 *    "không của ai". Trả về một đơn vị không ai xem được thì dòng ấy biến mất khỏi mọi
	 *    màn, kể cả màn của Admin — tiền có thật mà không ai nhìn thấy là thứ tệ hơn hẳn việc
	 *    nó tạm nằm nhầm bên.
	 */
	public static function cua_coso( $ten_coso ) {
		$k = mb_strtolower( trim( (string) $ten_coso ) );
		if ( '' === $k ) { return self::MAC_DINH; }
		/* ⚠️ `cfg_static()` chứ KHÔNG phải `get_config()`. Bảng tra `cosoDonVi` dựng trong
		   `cfg_static()`, và `get_config()` chỉ chuyển tiếp một phần các khoá của nó — hỏi
		   nhầm chỗ thì bảng rỗng, mọi cơ sở rơi về nhà mặc định, và hai bên nhìn thấy nhau
		   trở lại mà không câu lỗi nào. `get_config()` còn quét cả bảng chi phí để dựng danh
		   sách đối tượng: nặng hơn hẳn, mà chỗ này chỉ cần đúng một bảng tra. */
		$cfg = VHCPHN_Cfg::cfg_static();
		$m   = isset( $cfg['cosoDonVi'] ) ? (array) $cfg['cosoDonVi'] : array();
		return self::chuan( isset( $m[ $k ] ) ? $m[ $k ] : '' );
	}

	/** Người đang gọi có được đọc chi phí của cơ sở này không. */
	public static function xem_duoc_coso( $ten_coso ) {
		return self::duoc_xem( self::cua_coso( $ten_coso ) );
	}

	/**
	 * Danh sách CƠ SỞ người đang gọi được nhìn — để ô chọn cơ sở không bày gian của bên kia.
	 *
	 * @return array|null Mảng tên cơ sở · `null` = xem cả (không lọc gì).
	 */
	public static function coso_xem_duoc() {
		if ( null === self::xem_duoc() ) { return null; }
		$ra  = array();
		$cfg = VHCPHN_Cfg::cfg_static();
		foreach ( (array) ( isset( $cfg['coso'] ) ? $cfg['coso'] : array() ) as $x ) {
			$ten = trim( (string) ( isset( $x['ten'] ) ? $x['ten'] : '' ) );
			if ( '' === $ten ) { continue; }
			if ( self::duoc_xem( isset( $x['donVi'] ) ? $x['donVi'] : '' ) ) { $ra[] = $ten; }
		}
		return $ra;
	}

	/* ====================================================================== của đơn */

	/**
	 * Đơn vị của một đơn.
	 *
	 * ⚠️ Đơn cũ có cột rỗng (bảng mới nới, dữ liệu cũ chưa có gì) -> `chuan()` đưa về nhà mặc
	 *    định. Đó là hành vi ĐÚNG: trước khi có POSH thì mọi đơn đều là đơn K&H.
	 */
	public static function cua_don( $ma_don ) {
		$d = VHCPHN_Don::don_row( $ma_don );
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
		$d = VHCPHN_Don::don_row( $ma_don );
		if ( ! $d ) { return 'Không tìm thấy đơn ' . $ma_don . ' trong sổ.'; }
		$dv = self::chuan( isset( $d['don_vi'] ) ? $d['don_vi'] : '' );
		if ( ! self::duoc_xem( $dv ) ) { return self::loi_khac_don_vi_( $d, $dv ); }
		return '';
	}

	/**
	 * CÂU TỪ CHỐI CHO ĐƠN THUỘC ĐƠN VỊ KHÁC — nói rõ với CHÍNH CHỦ, giữ kín với người lạ.
	 *
	 * =========================================================================================
	 * 🔴 VÌ SAO PHẢI TÁCH LÀM HAI. Cắn thật 09/09/2026: anh Thắng lập đơn xong, màn báo "Đã tạo
	 *    đơn" rồi lập tức "Không tìm thấy đơn". Bảy chữ ấy khi đó phát ra từ BA chỗ khác hẳn
	 *    nhau — đơn không có trong bảng · đơn thuộc đơn vị khác · đơn của người khác — nên
	 *    không ai lần ra được chỗ nào, kể cả người viết ra chúng. Một câu lỗi không phân biệt
	 *    được ba nguyên nhân thì không phải câu lỗi, nó là một bức tường.
	 *
	 * 🔴 NHƯNG KHÔNG ĐƯỢC NÓI HẾT CHO MỌI NGƯỜI. Câu mờ "không tìm thấy" là CỐ Ý: nói thẳng
	 *    "đơn này của K&H" cho kế toán POSH là biến ô gõ mã đơn thành cái máy dò — gõ thử một
	 *    loạt mã là biết bên kia có những đơn nào, đúng thứ chốt này sinh ra để bịt.
	 *
	 * Chỗ cắt: NGƯỜI LẬP ra đơn thì được nói thẳng. Đơn của chính họ, họ đã biết nó tồn tại và
	 * biết nó của mảng nào — nói ra không lộ gì mới, mà giấu đi thì đúng là bịt mắt người đang
	 * cần thấy nhất. Người khác giữ nguyên câu mờ.
	 */
	private static function loi_khac_don_vi_( $d, $dv ) {
		$lap = mb_strtolower( trim( (string) ( isset( $d['nguoi_lap'] ) ? $d['nguoi_lap'] : '' ) ) );
		$toi = mb_strtolower( trim( (string) VHCPHN_Auth::nguoi() ) );
		if ( '' === $lap || $lap !== $toi ) { return 'Không tìm thấy đơn'; }
		$ds = self::xem_duoc();
		return 'Đơn này thuộc đơn vị "' . $dv . '", còn tài khoản của anh/chị chỉ xem được: '
			. ( null === $ds ? '(tất cả)' : implode( ', ', $ds ) ) . '. '
			. 'Đơn lấy đơn vị từ NGƯỜI LẬP lúc tạo — sửa ô "Đơn vị" của người lập ở Cấu hình → '
			. 'Người dùng & Phân quyền rồi lập lại đơn, hoặc nhờ Quản lý chuyển đơn sang đơn vị đúng.';
	}
}
