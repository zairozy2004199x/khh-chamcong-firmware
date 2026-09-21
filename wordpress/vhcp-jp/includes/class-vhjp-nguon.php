<?php
/**
 * NGUỒN DỮ LIỆU — lớp truy cập DUY NHẤT. Thay mục ②③ của `JP2_01_Core.gs`.
 *
 * =============================================================================================
 * KHÔNG LỚP NÀO KHÁC ĐƯỢC CHẠM `$wpdb`.
 * =============================================================================================
 * Bản gốc đặt đúng luật này và ghi ngay đầu `JP2_01_Core.gs`: *"Lớp truy cập dữ liệu duy nhất.
 * KHÔNG file nào khác được gọi thẳng SpreadsheetApp — sai ở đâu chỉ cần sửa trong file này."*
 * Chính vì bản gốc giữ được luật ấy mà việc thay Google Sheets bằng MySQL chỉ phải viết lại
 * một lớp, thay vì rà 28.735 dòng. Giữ luật, không thì lần chuyển sau trả giá.
 *
 * ---------------------------------------------------------------------------------------------
 * 🔴 BỎ `_row`. KHOÁ SỬA/XOÁ LÀ KHOÁ CHÍNH, KHÔNG PHẢI SỐ THỨ TỰ DÒNG.
 * ---------------------------------------------------------------------------------------------
 * Bên Sheets, `jpUpdate_(tab, rowIndex, obj)` ghi theo SỐ DÒNG. Số dòng thì TRÔI: ai xoá một
 * dòng phía trên là mọi số dòng dưới nó lùi một bậc, và lượt ghi tiếp theo đè vào nhầm báo cáo
 * của người khác. Bên này có khoá chính thật nên dùng nó — mất hẳn một lớp lỗi.
 *
 * ---------------------------------------------------------------------------------------------
 * 🔴 TÊN CỘT KHÔNG THAM SỐ HOÁ ĐƯỢC ⇒ PHẢI LỌC THEO LƯỢC ĐỒ.
 * ---------------------------------------------------------------------------------------------
 * `tim($tab, $key, $val)` nhận tên cột từ người gọi, mà tên cột thì không nhét vào `prepare()`
 * được — nó phải ghép thẳng vào câu SQL. Nên mọi tên cột đi qua đây đều bị đối chiếu với lược
 * đồ trước; cột lạ thì KHÔNG bao giờ tới được câu SQL.
 *
 * ⚠️ Và đó cũng đúng hành vi cũ: bên kia cột không có trong header thì `jpFind_` trả MẢNG RỖNG
 *    chứ không nổ. Mã gọi khắp nơi dựa vào chuyện đó.
 *
 * ---------------------------------------------------------------------------------------------
 * 🔴 LƯỢT GHI HỎNG PHẢI NÓI RA.
 * ---------------------------------------------------------------------------------------------
 * `$wpdb->insert()` trả `false` khi câu lệnh hỏng. Không hỏi tới giá trị ấy là hàm gật đầu cho
 * một dòng chưa hề được ghi, rồi mọi thứ phía sau chạy tiếp như thật. Bộ Ghế đã mất một giao
 * dịch vì đúng nước đi đó (`VHG_Thu::ghi`, sửa 16/09/2026) — không lặp lại ở đây.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHJP_Nguon {

	/** Nhớ lược đồ đã bóc, để khỏi phân tích lại mỗi lượt gọi. */
	private static $cot = array();

	/** Cột số nhận `NULL` — xem `cot_so_nhan_null()`. */
	private static $cot_so = array();

	/**
	 * Danh sách cột của một tab, đọc từ chính lược đồ MySQL.
	 *
	 * Tab lạ thì trả mảng rỗng — người gọi tự thấy không có cột nào và dừng, giống hệt cách
	 * bên kia xử lý header không khớp.
	 */
	public static function cot( $tab ) {
		if ( isset( self::$cot[ $tab ] ) ) { return self::$cot[ $tab ]; }
		$ban_do = VHJP_DB::ban_do();
		if ( ! isset( $ban_do[ $tab ] ) ) { return self::$cot[ $tab ] = array(); }
		$than = VHJP_DB::bang();
		$than = isset( $than[ $ban_do[ $tab ] ] ) ? $than[ $ban_do[ $tab ] ] : '';
		$ra = array();
		foreach ( explode( "\n", $than ) as $d ) {
			$d = trim( $d );
			/* Chỉ dòng KHAI CỘT, bỏ mọi dòng khoá. Tên cột có thể bọc dấu huyền (`rows`). */
			if ( preg_match( '/^(PRIMARY KEY|UNIQUE KEY|KEY)\b/i', $d ) ) { continue; }
			if ( preg_match( '/^`?([A-Za-z_][A-Za-z0-9_]*)`?\s+[A-Z]/', $d, $m ) ) { $ra[] = $m[1]; }
		}
		return self::$cot[ $tab ] = $ra;
	}

	/**
	 * Cột NÀO LÀ CỘT SỐ NHẬN NULL — đọc từ chính lược đồ, cùng một chỗ với `cot()`.
	 *
	 * =========================================================================================
	 * 🔴 "CHƯA AI GÕ" PHẢI XUỐNG SỔ LÀ `NULL`, KHÔNG PHẢI CHUỖI RỖNG.
	 * =========================================================================================
	 * Bên Google Sheets ô trống là `''`, và ô nào cũng nhận `''` vì Sheets không có kiểu cột.
	 * Bên này thì có: `mAfter DECIMAL(15,3) NULL`. Nhét `''` vào cột ấy thì
	 *   · MySQL ở chế độ CHẶT (mặc định từ 5.7) CHỐI CẢ DÒNG — mất luôn dòng báo cáo;
	 *   · MySQL ở chế độ LỎNG lặng lẽ đổi thành `0` — tức biến "chưa ai gõ" thành "đã gõ số
	 *     0". Đó đúng là lỗi mất tiền im lặng mà `VHJP_BaoCao` dựng lên để chặn: ô "Thực thu"
	 *     hiểu là đếm được 0 ⇒ lệch ra −(toàn bộ tiền mặt máy báo) ⇒ TỔNG PHẢI NỘP về 0, mà sổ
	 *     vẫn cân nên không phép kiểm kế toán nào bắt được.
	 * Cùng một câu `''` cho ra hai kết cục khác hẳn nhau tuỳ cấu hình hosting — thứ không bao
	 * giờ được để dây dưa.
	 *
	 * ⚠️ ĐỔI Ở ĐÂY, MỘT CHỖ, ngay trước khi chạm sổ — chứ không bắt từng nơi gọi tự nhớ. Đường
	 *    ghi còn dài (lưu nháp · nộp · duyệt · kho), và luật "nơi gọi phải nhớ" thì chỉ đúng
	 *    tới người thứ hai.
	 *
	 * ⚠️ CHỈ cột SỐ và CHỈ khi cột ấy nhận `NULL`. Cột chữ `NOT NULL DEFAULT ''` (`note`,
	 *    `topupNote`…) mà cũng đổi sang `NULL` là chối cả dòng vì lý do ngược lại — ở đó chuỗi
	 *    rỗng mới là giá trị đúng.
	 */
	public static function cot_so_nhan_null( $tab ) {
		if ( isset( self::$cot_so[ $tab ] ) ) { return self::$cot_so[ $tab ]; }
		$ban_do = VHJP_DB::ban_do();
		if ( ! isset( $ban_do[ $tab ] ) ) { return self::$cot_so[ $tab ] = array(); }
		$than = VHJP_DB::bang();
		$than = isset( $than[ $ban_do[ $tab ] ] ) ? $than[ $ban_do[ $tab ] ] : '';
		$ra = array();
		foreach ( explode( "\n", $than ) as $d ) {
			$d = trim( $d );
			if ( preg_match( '/^(PRIMARY KEY|UNIQUE KEY|KEY)\b/i', $d ) ) { continue; }
			if ( ! preg_match( '/^`?([A-Za-z_][A-Za-z0-9_]*)`?\s+([A-Z]+)/', $d, $m ) ) { continue; }
			$kieu = strtoupper( $m[2] );
			if ( ! in_array( $kieu, array( 'INT', 'BIGINT', 'SMALLINT', 'TINYINT',
				'DECIMAL', 'FLOAT', 'DOUBLE', 'DATE', 'DATETIME', 'TIMESTAMP' ), true ) ) { continue; }
			/* `NOT NULL` thì để yên — ở đó `''` sẽ thành 0 đúng như cột được khai. */
			if ( preg_match( '/\bNOT\s+NULL\b/i', $d ) ) { continue; }
			$ra[] = $m[1];
		}
		return self::$cot_so[ $tab ] = $ra;
	}

	/** Cột này có thật không. Đây là chốt chặn duy nhất giữa tên cột người gọi đưa và câu SQL. */
	public static function co_cot( $tab, $cot ) {
		return in_array( (string) $cot, self::cot( $tab ), true );
	}

	/** Tên bảng thật của một tab, hoặc '' nếu tab lạ. */
	public static function bang( $tab ) {
		$ban_do = VHJP_DB::ban_do();
		return isset( $ban_do[ $tab ] ) ? VHJP_DB::t( $ban_do[ $tab ] ) : '';
	}

	/** Cột khoá chính của một tab. */
	public static function khoa( $tab ) {
		$c = self::cot( $tab );
		if ( in_array( 'id', $c, true ) ) { return 'id'; }
		if ( in_array( 'stt', $c, true ) ) { return 'stt'; }
		/* Bảng lấy cột khác làm khoá (`hang` lấy `code`, `bank_gd` lấy `refId`). */
		foreach ( array( 'code', 'refId' ) as $k ) {
			if ( in_array( $k, $c, true ) ) { return $k; }
		}
		return '';
	}

	/** Bọc tên cột cho an toàn cú pháp. Chỉ gọi SAU khi đã đối chiếu lược đồ. */
	private static function oc( $cot ) {
		return '`' . str_replace( '`', '', (string) $cot ) . '`';
	}

	/** Mọi dòng của một tab. */
	public static function doc( $tab ) {
		global $wpdb;
		$b = self::bang( $tab );
		if ( '' === $b ) { return array(); }
		$r = $wpdb->get_results( 'SELECT * FROM ' . $b, ARRAY_A );
		return is_array( $r ) ? $r : array();
	}

	/**
	 * Lọc theo một cặp cột/giá trị.
	 *
	 * ⚠️ So sánh theo CHUỖI, y bản gốc (`String(v[i][c]) !== s`). Cột `locationId` lưu `'L-7'`
	 *    nhưng cũng có nơi truyền số 7 vào; bên kia hai thứ ấy khớp nhau, nên bên này cũng phải.
	 */
	public static function tim( $tab, $cot, $gia_tri ) {
		global $wpdb;
		$b = self::bang( $tab );
		/* Cột lạ -> MẢNG RỖNG, không nổ. Đúng hành vi cũ, và là chốt chặn chèn SQL. */
		if ( '' === $b || ! self::co_cot( $tab, $cot ) ) { return array(); }
		/* ⚠️ KHÔNG bọc `CAST(... AS CHAR)` quanh cột. Nghe thì giống "so theo chuỗi y bản gốc",
		   nhưng cột `DECIMAL(15,2)` giữ 20000 sẽ ra chuỗi "20000.00" và thôi khớp "20000" —
		   tức là tự tay dựng ra một lớp lệch KHÔNG hề có bên Sheets. Để `%s` ràng giá trị và
		   cơ sở dữ liệu tự so: cột chữ thì so chữ, cột số thì "1" tự thành 1. Đúng cùng cách
		   bên gốc đối xử với số và chuỗi như nhau, mà không đẻ thêm ca lạ. */
		$r = $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . $b . ' WHERE ' . self::oc( $cot ) . ' = %s',
			(string) $gia_tri ), ARRAY_A );
		return is_array( $r ) ? $r : array();
	}

	/** Dòng đầu tiên khớp, hoặc `null`. */
	public static function tim_mot( $tab, $cot, $gia_tri ) {
		$r = self::tim( $tab, $cot, $gia_tri );
		return $r ? $r[0] : null;
	}

	/**
	 * Bỏ mọi khoá không phải tên cột thật.
	 *
	 * ⚠️ BỎ IM LẶNG, y bản gốc: bên kia dựng dòng theo header nên trường lạ trong object tự rơi
	 *    ra. Mã chuyển sang hay mang theo trường tạm (`__kho`, `__con`, `__daTru` của FIFO) —
	 *    nổ vì mấy trường ấy là chặn đúng đường chạy bình thường.
	 */
	private static function loc( $tab, $obj ) {
		$so = self::cot_so_nhan_null( $tab );
		$ra = array();
		foreach ( (array) $obj as $k => $v ) {
			if ( ! self::co_cot( $tab, $k ) ) { continue; }
			/* 🔴 "chưa ai gõ" -> NULL, chỉ ở cột số nhận NULL. Xem `cot_so_nhan_null()`. */
			if ( '' === $v && in_array( $k, $so, true ) ) { $v = null; }
			$ra[ $k ] = $v;
		}
		return $ra;
	}

	/**
	 * Thêm một dòng. Trả dòng đã ghi, hoặc `false` khi ghi hỏng.
	 *
	 * 🔴 Hỏng thì trả `false`, KHÔNG trả về object như thật. Xem khối 🔴 cuối phần đầu tệp.
	 */
	public static function them( $tab, $obj ) {
		global $wpdb;
		$b = self::bang( $tab );
		if ( '' === $b ) { return false; }
		$hang = self::loc( $tab, $obj );
		if ( ! $hang ) { return false; }
		$ok = $wpdb->insert( $b, $hang );
		if ( false === $ok ) { return false; }
		return $hang;
	}

	/**
	 * Thêm nhiều dòng. Trả SỐ DÒNG đã ghi.
	 *
	 * ⚠️ Rỗng thì không đụng cơ sở dữ liệu và trả 0 — y bản gốc.
	 * ⚠️ Ghi từng dòng chứ không gộp một câu: gộp thì một dòng hỏng là mất cả khối mà người gọi
	 *    chỉ thấy `false`, không biết dòng nào. Đây là đường tiền, cần biết dòng nào trượt.
	 */
	public static function them_nhieu( $tab, $ds ) {
		if ( ! $ds ) { return 0; }
		$n = 0;
		foreach ( $ds as $o ) {
			if ( false !== self::them( $tab, $o ) ) { $n++; }
		}
		return $n;
	}

	/**
	 * Sửa một dòng theo KHOÁ CHÍNH. Trả `true`/`false`.
	 *
	 * ⚠️ `$wpdb->update` trả `0` khi không giá trị nào đổi — đó là lượt LÀNH, hay gặp nhất khi
	 *    ghi lại y nguyên. Chỉ `false` mới là hỏng, nên so sánh CHẶT.
	 */
	public static function sua( $tab, $ma, $obj ) {
		global $wpdb;
		$b = self::bang( $tab );
		$k = self::khoa( $tab );
		if ( '' === $b || '' === $k ) { return false; }
		$hang = self::loc( $tab, $obj );
		/* Đừng để lượt sửa đổi luôn khoá chính — người gọi đưa cả object đọc ra rồi sửa vài
		   trường là chuyện thường, và khoá nằm sẵn trong đó. */
		unset( $hang[ $k ] );
		if ( ! $hang ) { return false; }
		return false !== $wpdb->update( $b, $hang, array( $k => $ma ) );
	}

	/** Xoá một dòng theo khoá chính. */
	public static function xoa( $tab, $ma ) {
		global $wpdb;
		$b = self::bang( $tab );
		$k = self::khoa( $tab );
		if ( '' === $b || '' === $k ) { return false; }
		return false !== $wpdb->delete( $b, array( $k => $ma ) );
	}

	/**
	 * Khoá chính LỚN NHẤT bắt đầu bằng một đoạn cho trước. Dùng để sinh mã chạy tiếp.
	 *
	 * ⚠️ Nằm ở ĐÂY chứ không ở `VHJP_Ma`, vì luật "chỉ một lớp chạm `$wpdb`" không có ngoại lệ
	 *    — có một ngoại lệ là lần sau có hai, rồi ba. `kiem-jp-nguon.php` canh đúng chuyện ấy,
	 *    và nó đã bắt được lượt vi phạm đầu tiên ngay hôm viết `VHJP_Ma`.
	 */
	public static function ma_lon_nhat( $tab, $dau ) {
		global $wpdb;
		$b = self::bang( $tab );
		$k = self::khoa( $tab );
		if ( '' === $b || '' === $k ) { return ''; }
		$oc = self::oc( $k );
		$ma = $wpdb->get_var( $wpdb->prepare(
			'SELECT ' . $oc . ' FROM ' . $b . ' WHERE ' . $oc . ' LIKE %s ORDER BY ' . $oc
			. ' DESC LIMIT 1', $wpdb->esc_like( (string) $dau ) . '%' ) );
		return null === $ma ? '' : (string) $ma;
	}

	/**
	 * Bảng này đã có thật trong cơ sở dữ liệu chưa.
	 *
	 * ⚠️ Nằm ở đây chứ không ở màn quản trị, vì luật "chỉ một lớp chạm `$wpdb`" không có ngoại
	 *    lệ — có một ngoại lệ là lần sau có hai. `kiem-jp-nguon.php` bắt được lượt vi phạm này
	 *    ngay hôm viết màn quản trị, lần thứ hai trong mạch JP.
	 */
	public static function co_bang( $tab ) {
		global $wpdb;
		$b = self::bang( $tab );
		if ( '' === $b ) { return false; }
		return $b === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $b ) );
	}

	/** Câu lỗi của lượt xuống cơ sở dữ liệu gần nhất. */
	public static function loi_cuoi() {
		global $wpdb;
		return (string) $wpdb->last_error;
	}

	/** Xoá mọi dòng khớp một cặp cột/giá trị. Trả số dòng đã xoá, hoặc `false`. */
	public static function xoa_theo( $tab, $cot, $gia_tri ) {
		global $wpdb;
		$b = self::bang( $tab );
		if ( '' === $b || ! self::co_cot( $tab, $cot ) ) { return false; }
		$n = $wpdb->query( $wpdb->prepare(
			'DELETE FROM ' . $b . ' WHERE ' . self::oc( $cot ) . ' = %s',
			(string) $gia_tri ) );
		return false === $n ? false : (int) $n;
	}
}
