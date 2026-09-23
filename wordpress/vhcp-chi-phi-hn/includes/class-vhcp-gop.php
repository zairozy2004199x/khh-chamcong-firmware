<?php
/**
 * SOÁT TRƯỚC KHI GỘP — ĐẾM HỘ BA KHO, ĐỐI CHIẾU BA DANH MỤC. CHỈ ĐỌC.
 *
 * Anh Thắng 20/09/2026, sau khi em hỏi bên MTĐ/VP đã có dữ liệu chưa: *"vậy gộp đi em"*.
 *
 * ══════════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 LỚP NÀY KHÔNG GHI MỘT DÒNG NÀO. Một chữ cũng không.
 * ══════════════════════════════════════════════════════════════════════════════════════════════
 * Nó là cái bảng đối chiếu đặt trước mặt anh Thắng để anh gật hay lắc, TRƯỚC khi có bất cứ lệnh
 * dời nào chạy. Lý do phải tách hẳn phần đọc ra khỏi phần ghi:
 *
 *   · Em không mở được cơ sở dữ liệu thật của anh. Con số duy nhất đáng tin là con số do chính
 *     WordPress của anh đếm ra — nên việc đếm phải nằm trong plugin, không nằm trong đầu em.
 *   · Gộp là việc KHÔNG HOÀN TÁC ĐƯỢC bằng một nút. Ba kho tách hẳn nhau; trộn xong rồi mà phát
 *     hiện hai cơ sở trùng tên là hai gian hàng KHÁC NHAU thì tiền đã cộng vào nhau mất rồi.
 *   · Cho nên: đọc trước, in ra, hỏi người, rồi mới viết bước dời.
 *
 * ══════════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 VÌ SAO GỘP DANH MỤC (BƯỚC 4) PHẢI XONG TRƯỚC KHI DỜI ĐƠN (BƯỚC 3)
 * ══════════════════════════════════════════════════════════════════════════════════════════════
 * Ba bản plugin là ba BẢN SAO tách ra từ cùng một gốc (`tools/tach-ban-vung.sh`), mỗi bản một
 * tiền tố bảng riêng — bản gốc không mang mã, hai bản vùng mang mã của mình. Cả bảng cấu hình cũng riêng,
 * nên DANH MỤC LOẠI CHI PHÍ của ba bên là ba danh sách khác nhau, khai tay vào ba lúc khác nhau.
 *
 * Dòng chi không giữ mã tài khoản theo khóa, nó giữ **TÊN loại chi phí** (`chiphi.nhom`,
 * `so_chi.loai`). Dời đơn sang trước khi gộp danh mục thì mỗi dòng ấy trỏ tới một cái tên KHÔNG
 * CÓ trong danh mục của app gộp → không suy ra được TK Nợ → đúng cái cảnh *"⚠ chưa gắn mã"* anh
 * gặp hôm trước, nhưng lần này hàng loạt, trên dữ liệu thật.
 *
 * Nên `soat()` đếm luôn số dòng **mồ côi** ấy: bao nhiêu dòng bên MTĐ/VP đang gọi tên một loại
 * chi phí mà bên KVC chưa có. Con số ấy chính là số dòng sẽ mất mã nếu làm ngược thứ tự.
 *
 * ══════════════════════════════════════════════════════════════════════════════════════════════
 * ⚠️ KHÔNG BAO GIỜ ĐỂ MÃ PIN LỌT RA BẢNG ĐỐI CHIẾU
 * ══════════════════════════════════════════════════════════════════════════════════════════════
 * Bảng `CH_NguoiDung` có cột PIN. Bảng đối chiếu này hiện trên màn Cấu hình và người ta hay chụp
 * màn hình gửi nhau. Phần người dùng ở đây chỉ trả về TÊN, VAI TRÒ, CƠ SỞ và một chữ "có/chưa"
 * cho ô PIN — tuyệt đối không trả giá trị PIN. Xem `nguoi_gon()`.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCPHN_Gop {

	/**
	 * CÁC KHO ĐANG CÓ TRÊN SITE — HỎI CƠ SỞ DỮ LIỆU, KHÔNG GÕ SẴN.
	 *
	 * ══════════════════════════════════════════════════════════════════════════════════════════
	 * 🔴 VÌ SAO KHÔNG GÕ SẴN MỘT HẰNG [ MÃ VÙNG => TIỀN TỐ ]
	 * ══════════════════════════════════════════════════════════════════════════════════════════
	 * Bản đầu gõ sẵn đúng như thế (một hằng ba dòng, mỗi dòng một tiền tố), và
	 * `kiem-tach-ban-vung.php` đỏ ngay — đúng lý:
	 *
	 *   · Luật của bộ này là **bản gốc không được dính một chuỗi nào của bản vùng**. Luật ấy
	 *     sinh ra sau ngày 08/09/2026, hôm anh Thắng mở `/chi-phi/` ra thấy TRỐNG TRƠN vì hai
	 *     bản cùng đăng ký một đường REST. Chuỗi chung giữa hai bản là mầm của đúng loại lỗi ấy,
	 *     nên chốt gác MỌI chuỗi chứ không liệt kê từng khoá — liệt kê thì khoá mới lại lọt.
	 *   · Và gõ sẵn thì `tach-ban-vung.sh` lúc sinh bản vùng sẽ đổi luôn tiền tố của vùng KHÁC
	 *     đang nằm trong hằng ấy — nó quét mọi chữ `vhcphn` — thành một tiền tố KHÔNG TỒN TẠI.
	 *     Bản mới bày ra bảng đối chiếu trống rỗng rồi nói "bên kia không có gì", mà sự thật là
	 *     nó đang dò một cái tên bịa.
	 *
	 * Nên hỏi thẳng cơ sở dữ liệu: bảng `…vhcphn<mã>_don` nào đang có thì đó là một kho. Cách này
	 * còn đúng cả với bản vùng thứ tư chưa ai nghĩ ra, và với bản đổi tên tay.
	 *
	 * ⚠️ `<mã>` LẤY TỪ TÊN BẢNG CHÍNH LÀ MÃ KHỐI. `tach-ban-vung.sh` viết `const KHOI` bằng đúng
	 *    mã vùng nó đang sinh, và đổi tiền tố bảng bằng đúng mã ấy — nên hai thứ luôn là một.
	 *    Bản gốc có mã rỗng, khối của nó đọc từ `VHCPHN_DB::khoi()`.
	 *
	 * @return array [ mã khối => tiền tố bảng ], kho của chính bản này đứng đầu.
	 */
	public static function kho() {
		global $wpdb;
		$rieng = array();
		$khac  = array();
		/* ⚠️ ĐỘT BIẾN TƯƠNG ĐƯƠNG — hai lớp gác dưới đây gỡ RIÊNG từng lớp thì bài kiểm vẫn xanh,
		   phải gỡ CẢ HAI mới đỏ. Không phải lỗ hổng của phép thử: mỗi lớp một mình đã đủ chặn
		   con mồi `wpxvhcphnzz_don` trong `kiem-soat-gop.php`. Giữ cả hai là cố ý — lớp SQL lọc
		   sớm cho rẻ, lớp lưới tên bảng gác những cái tên mà câu SQL chưa lường (bảng thêm về
		   sau, tên gõ tay, mã vùng có ký tự lạ). `kiem-soat-gop.php` có phép gỡ cả hai.

		   🔴 CHE `_` CỦA TIỀN TỐ. Trong SQL, `_` là ký tự thay thế MỘT ký tự bất kỳ — `wp_vhcphn…`
		   để trần thì nó vớ luôn bảng của một bản WordPress KHÁC cài chung cơ sở dữ liệu
		   (tiền tố `wpx`), rồi ta dựng ra một cái kho ma và báo cho anh Thắng số liệu của
		   site người khác. Cùng lý do cho `_don` ở cuối. */
		$mau   = $wpdb->esc_like( $wpdb->prefix . 'vhcphn' ) . '%' . $wpdb->esc_like( '_don' );
		foreach ( (array) $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $mau ) ) as $ten ) {
			/* Neo hai đầu: `%` của SQL rộng hơn ta cần, và `_` cũng là ký tự thay thế nên câu dò
			   trên còn vớ được vài cái tên không phải kho. Cái lưới thật nằm ở đây. */
			if ( ! preg_match( '#^' . preg_quote( $wpdb->prefix, '#' ) . 'vhcphn([a-z0-9]*)_don$#', (string) $ten, $m ) ) { continue; }
			$tien_to = 'vhcphn' . $m[1] . '_';
			if ( '' === $m[1] ) { $rieng[ VHCPHN_DB::khoi() ] = $tien_to; continue; }
			$khac[ $m[1] ] = $tien_to;
		}
		ksort( $khac );
		return $rieng + $khac;
	}

	/** Kho của chính bản đang chạy — mọi kho khác đem so với nó. */
	private static function kho_nha() {
		return VHCPHN_DB::khoi();
	}

	/** Bảng nghiệp vụ cần đếm, kèm cột tiền để cộng (rỗng = chỉ đếm dòng). */
	const DEM = array(
		'don'      => '',
		'tamung'   => 'so',
		'chiphi'   => 'thanh_tien',
		'so_chi'   => 'so_tien',
		'da_index' => '',
		'da_line'  => 'thuc_te',
		'mk_don'   => '',
		'mk_line'  => 'thuc_te',
		'bp_index' => '',
		'bp_line'  => 'thuc_te',
		'log'      => '',
	);

	/** Danh mục phải đối chiếu, kèm CHỈ SỐ CỘT dùng làm khóa và các cột đem ra so. */
	const DANH_MUC = array(
		/* Loại chi phí: khóa là TÊN, so TK Nợ + TK Có. Đây là danh mục nguy nhất — hai bên cùng
		   một tên mà khác mã tài khoản thì gộp xong sổ kế toán sai mà không ai thấy. */
		'CH_LoaiChiPhi' => array( 'khoa' => 0, 'so' => array( 1, 2 ) ),
		/* Cơ sở: khóa là TÊN gian hàng, so Mã đơn vị MISA + Tên MISA. Cơ sở được nhận ra bằng
		   chuỗi tên (xem `VHCPHN_Cfg::coso_la()`) nên trùng tên là trùng thật — hoặc là tai nạn. */
		'CH_CoSo'       => array( 'khoa' => 0, 'so' => array( 1, 3 ) ),
		/* Người dùng: khóa là TÊN, so Vai trò + Cơ sở. 🔴 KHÔNG so PIN, không trả PIN. */
		'CH_NguoiDung'  => array( 'khoa' => 0, 'so' => array( 2, 3 ) ),
		'CH_BoPhan'     => array( 'khoa' => 0, 'so' => array() ),
		/* `CH_VaiTro` KHÔNG có trong `VHCPHN_Cfg::headers()` — bảng ấy đọc theo chỉ số trần
		   (xem `vai_tuy_bien()`). Nên tên cột khai ngay tại chỗ, bằng `nhan`; thiếu nó thì
		   bảng đối chiếu in ra "cột 1" và người đọc không biết đang lệch cái gì. */
		'CH_VaiTro'     => array( 'khoa' => 0, 'so' => array( 1 ), 'nhan' => array( 'Vai trò', 'Kế thừa từ', 'Bộ phận' ) ),
	);

	/** Chỉ số cột PIN trong `CH_NguoiDung` — che đi, không bao giờ trả ra. */
	const COT_PIN = 1;

	// ------------------------------------------------------------------ tiện ích đọc theo kho

	/** Tên bảng đầy đủ của một kho. Không dùng `VHCPHN_DB::t()` vì nó khóa cứng vào kho KVC. */
	private static function bang( $khoi, $ten ) {
		global $wpdb;
		$kho     = self::kho();
		$tien_to = isset( $kho[ $khoi ] ) ? $kho[ $khoi ] : '';
		if ( '' === $tien_to ) { return ''; }
		return $wpdb->prefix . $tien_to . $ten;
	}

	/**
	 * BẢNG NÀY CÓ THẬT KHÔNG?
	 *
	 * 🔴 Phải hỏi, không được đoán. Máy của anh Thắng có thể chưa cài bản MTĐ, hoặc cài rồi lại
	 *    gỡ. Chạy `SELECT COUNT(*)` vào bảng không có thì `$wpdb` thật trả `false` lặng lẽ và
	 *    ta cộng `false` vào tổng thành 0 — báo "bên ấy không có gì" trong khi sự thật là
	 *    "bên ấy chưa cài". Hai câu đó khác nhau hoàn toàn: một cái cho phép gộp, cái kia không.
	 */
	private static function co_bang( $ten_bang ) {
		global $wpdb;
		if ( '' === $ten_bang ) { return false; }
		$thay = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $ten_bang ) );
		return (string) $thay === (string) $ten_bang;
	}

	/** Đọc một bảng cấu hình của MỘT kho bất kỳ (bản sao của `VHCPHN_Cfg::read()` có tiền tố). */
	private static function cfg_cua( $khoi, $bang ) {
		global $wpdb;
		$t = self::bang( $khoi, 'cfg' );
		if ( ! self::co_bang( $t ) ) { return null; }
		$n    = count( VHCPHN_Cfg::headers( $bang ) );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT cols FROM $t WHERE bang=%s ORDER BY stt ASC, id ASC", $bang ), ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $r ) {
			$a = json_decode( (string) $r['cols'], true );
			if ( ! is_array( $a ) ) { $a = array(); }
			for ( $i = count( $a ); $i < $n; $i++ ) { $a[ $i ] = ''; }
			$out[] = $a;
		}
		return $out;
	}

	/** So tên thì phải so kiểu người đọc: bỏ khoảng trắng thừa, không phân biệt hoa thường. */
	private static function chuan( $s ) {
		$s = preg_replace( '/\s+/u', ' ', trim( (string) $s ) );
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $s, 'UTF-8' ) : strtolower( $s );
	}

	// ------------------------------------------------------------------ phần 1: đếm kho

	/**
	 * Đếm dòng + cộng tiền cho MỘT kho.
	 *
	 * @return array|null null nếu kho ấy chưa cài (không phải "rỗng" — xem `co_bang()`).
	 */
	public static function dem_kho( $khoi ) {
		global $wpdb;
		$t_don = self::bang( $khoi, 'don' );
		if ( ! self::co_bang( $t_don ) ) { return null; }

		$ra = array( 'khoi' => $khoi, 'bang' => array(), 'tong_tien' => 0.0 );
		foreach ( self::DEM as $ten => $cot_tien ) {
			$t = self::bang( $khoi, $ten );
			if ( ! self::co_bang( $t ) ) {
				/* Kho có nhưng thiếu đúng bảng này — bản cũ chưa nâng cấp. Nói ra, đừng lấp
				   bằng số 0: số 0 đọc thành "không có dữ liệu", còn đây là "không biết". */
				$ra['bang'][ $ten ] = array( 'co' => false, 'dong' => null, 'tien' => null );
				continue;
			}
			$dong = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t" );
			$tien = null;
			if ( '' !== $cot_tien ) {
				$tien = (float) $wpdb->get_var( "SELECT COALESCE(SUM($cot_tien),0) FROM $t" );
				/* 🔴 CHỈ CỘNG `tamung` + `so_chi` VÀO TỔNG TIỀN, không cộng hết.
				   `chiphi` là chi tiết BÊN TRONG đơn mà `tamung` đã gói lại; `da_line` /
				   `mk_line` / `bp_line` cũng là chi tiết của chứng từ riêng. Cộng tuốt là
				   đếm một đồng hai lần rồi đưa anh Thắng một con số không khớp với bất cứ
				   báo cáo nào anh đang nhìn — mà số liệu đối chiếu mà sai thì thà đừng có. */
				if ( 'tamung' === $ten || 'so_chi' === $ten ) { $ra['tong_tien'] += $tien; }
			}
			$ra['bang'][ $ten ] = array( 'co' => true, 'dong' => $dong, 'tien' => $tien );
		}

		/* Đơn tách theo trạng thái — để anh Thắng biết bên ấy là đơn nháp hay đơn đã chạy tiền. */
		$ra['trang_thai'] = array();
		foreach ( (array) $wpdb->get_results( "SELECT trang_thai, COUNT(*) AS n FROM $t_don GROUP BY trang_thai ORDER BY n DESC", ARRAY_A ) as $r ) {
			$ra['trang_thai'][ (string) $r['trang_thai'] ] = (int) $r['n'];
		}

		/* Và theo khối đã đóng dấu — kho MTĐ đáng lẽ chỉ có dấu 'mtd'. Lệch là có bản cài sai
		   `const KHOI`, phải biết TRƯỚC khi dời chứ không phải sau. */
		$ra['dau_khoi'] = array();
		foreach ( (array) $wpdb->get_results( "SELECT khoi, COUNT(*) AS n FROM $t_don GROUP BY khoi ORDER BY n DESC", ARRAY_A ) as $r ) {
			$ra['dau_khoi'][ (string) $r['khoi'] ] = (int) $r['n'];
		}

		return $ra;
	}

	// ------------------------------------------------------------------ phần 2: đối chiếu danh mục

	/**
	 * So một danh mục của kho `$khoi` với chính danh mục ấy bên KVC.
	 *
	 * Ba rổ, và chỉ rổ thứ ba mới cần người quyết:
	 *   · `rieng`  — chỉ bên kia có. Gộp = thêm dòng vào KVC. An toàn.
	 *   · `trung`  — hai bên có, mọi ô đem so đều khớp. Gộp = không làm gì. An toàn.
	 *   · `dung_do`— hai bên có CÙNG TÊN mà KHÁC NỘI DUNG. 🔴 Phải hỏi người.
	 */
	public static function doi_chieu( $khoi, $bang ) {
		$luat = isset( self::DANH_MUC[ $bang ] ) ? self::DANH_MUC[ $bang ] : null;
		if ( null === $luat ) { return null; }

		$ben_kia = self::cfg_cua( $khoi, $bang );
		if ( null === $ben_kia ) { return null; }
		$ben_nay = (array) self::cfg_cua( self::kho_nha(), $bang );

		$chi_muc = array();
		foreach ( $ben_nay as $r ) {
			$k = self::chuan( isset( $r[ $luat['khoa'] ] ) ? $r[ $luat['khoa'] ] : '' );
			if ( '' === $k ) { continue; }
			/* Giữ dòng ĐẦU khi KVC lỡ có hai dòng cùng tên — cùng luật với `VHCPHN_Cfg::write()`. */
			if ( ! isset( $chi_muc[ $k ] ) ) { $chi_muc[ $k ] = $r; }
		}

		$ra = array( 'bang' => $bang, 'rieng' => array(), 'trung' => 0, 'dung_do' => array(), 'tu_trung' => array() );
		$da_xet = array();
		foreach ( $ben_kia as $r ) {
			$ten = trim( (string) ( isset( $r[ $luat['khoa'] ] ) ? $r[ $luat['khoa'] ] : '' ) );
			$k   = self::chuan( $ten );
			if ( '' === $k ) { continue; }
			/* Bên kia tự trùng tên với chính nó — đếm một lần, nhưng nói ra. */
			if ( isset( $da_xet[ $k ] ) ) { $ra['tu_trung'][] = $ten; continue; }
			$da_xet[ $k ] = 1;

			if ( ! isset( $chi_muc[ $k ] ) ) {
				$ra['rieng'][] = self::hien( $bang, $r );
				continue;
			}
			$lech = array();
			foreach ( $luat['so'] as $c ) {
				$a = trim( (string) ( isset( $chi_muc[ $k ][ $c ] ) ? $chi_muc[ $k ][ $c ] : '' ) );
				$b = trim( (string) ( isset( $r[ $c ] ) ? $r[ $c ] : '' ) );
				if ( $a === $b ) { continue; }
				/* Một bên bỏ trống thì KHÔNG phải đụng độ — chỉ là bên ấy chưa khai. Gộp lấy ô
				   có chữ. Đụng độ thật là HAI BÊN CÙNG KHAI mà khai khác nhau. */
				if ( '' === $a || '' === $b ) { continue; }
				$hd = isset( $luat['nhan'] ) ? $luat['nhan'] : VHCPHN_Cfg::headers( $bang );
				$lech[] = array(
					'cot' => isset( $hd[ $c ] ) ? $hd[ $c ] : ( 'cột ' . $c ),
					'kvc' => $a,
					'kia' => $b,
				);
			}
			if ( $lech ) {
				$ra['dung_do'][] = array( 'ten' => $ten, 'lech' => $lech );
			} else {
				$ra['trung']++;
			}
		}
		return $ra;
	}

	/**
	 * Một dòng danh mục đem ra hiển thị.
	 *
	 * 🔴 CỬA DUY NHẤT MÀ DÒNG `CH_NguoiDung` ĐI QUA TRƯỚC KHI RA NGOÀI — và nó bịt cột PIN ở đây.
	 *    Bịt ở một chỗ thì thêm màn hình mới cũng không lọt; bịt ở màn hình thì màn sau lại quên.
	 */
	private static function hien( $bang, $r ) {
		$r = array_values( (array) $r );
		if ( VHCPHN_Cfg::USER === $bang ) {
			$co_pin = '' !== trim( (string) ( isset( $r[ self::COT_PIN ] ) ? $r[ self::COT_PIN ] : '' ) );
			$r[ self::COT_PIN ] = $co_pin ? '(có PIN)' : '(chưa có PIN)';
		}
		return $r;
	}

	// ------------------------------------------------------------------ phần 3: dòng mồ côi

	/**
	 * BAO NHIÊU DÒNG SẼ MẤT MÃ NẾU DỜI TRƯỚC KHI GỘP DANH MỤC?
	 *
	 * Đếm các TÊN loại chi phí / TÊN cơ sở mà bên kia đang dùng trong dữ liệu thật nhưng danh
	 * mục bên KVC chưa có. Mỗi cái tên ấy, sau khi dời, là một nhóm dòng chi không suy ra được
	 * TK Nợ — đúng cảnh *"⚠ chưa gắn mã"*.
	 */
	public static function mo_coi( $khoi ) {
		global $wpdb;
		$ra = array( 'loai' => array(), 'coso' => array() );

		$ten_loai = array();
		foreach ( (array) self::cfg_cua( self::kho_nha(), VHCPHN_Cfg::LOAI ) as $r ) {
			$k = self::chuan( isset( $r[0] ) ? $r[0] : '' );
			if ( '' !== $k ) { $ten_loai[ $k ] = 1; }
		}
		$ten_coso = array();
		foreach ( (array) self::cfg_cua( self::kho_nha(), VHCPHN_Cfg::COSO ) as $r ) {
			$k = self::chuan( isset( $r[0] ) ? $r[0] : '' );
			if ( '' !== $k ) { $ten_coso[ $k ] = 1; }
		}

		/* [bảng, cột, rổ] — mọi chỗ dữ liệu thật gọi tên một mục danh mục. */
		$nguon = array(
			array( 'chiphi', 'nhom', 'loai' ),
			array( 'so_chi', 'loai', 'loai' ),
			array( 'chiphi', 'coso', 'coso' ),
			array( 'so_chi', 'coso', 'coso' ),
			array( 'tamung', 'coso', 'coso' ),
			array( 'mk_don', 'coso', 'coso' ),
		);
		foreach ( $nguon as $n ) {
			list( $ten_bang, $cot, $ro ) = $n;
			$t = self::bang( $khoi, $ten_bang );
			if ( ! self::co_bang( $t ) ) { continue; }
			$co = ( 'loai' === $ro ) ? $ten_loai : $ten_coso;
			foreach ( (array) $wpdb->get_results( "SELECT $cot AS v, COUNT(*) AS n FROM $t WHERE $cot<>'' GROUP BY $cot", ARRAY_A ) as $r ) {
				$v = trim( (string) $r['v'] );
				if ( '' === $v || isset( $co[ self::chuan( $v ) ] ) ) { continue; }
				if ( ! isset( $ra[ $ro ][ $v ] ) ) { $ra[ $ro ][ $v ] = 0; }
				$ra[ $ro ][ $v ] += (int) $r['n'];
			}
		}
		arsort( $ra['loai'] );
		arsort( $ra['coso'] );
		return $ra;
	}

	// ------------------------------------------------------------------ cổng gọi

	/**
	 * BẢNG ĐỐI CHIẾU ĐẦY ĐỦ. CHỈ ĐỌC.
	 *
	 * Trả về cho mỗi kho MTĐ / VP: số dòng + tiền từng bảng, phân bố trạng thái đơn, dấu khối
	 * đang đóng, ba rổ đối chiếu danh mục, và danh sách tên mồ côi kèm số dòng đang dùng nó.
	 */
	public static function soat() {
		$ra = array(
			'kho'     => array(),
			'chi_doc' => true,
			/* Nhắc lại thứ tự ngay trong dữ liệu trả về, để màn hình nào hiện bảng này cũng
			   phải hiện kèm — người đọc bảng số thường là người sắp bấm nút dời. */
			'thu_tu'  => 'Gộp danh mục (bước 4) TRƯỚC, dời dữ liệu (bước 3) SAU.',
		);
		$nha = self::kho_nha();
		$ra['nha'] = $nha;
		foreach ( array_keys( self::kho() ) as $khoi ) {
			$dem = self::dem_kho( $khoi );
			if ( null === $dem ) {
				$ra['kho'][ $khoi ] = array( 'cai_dat' => false );
				continue;
			}
			$mot = array( 'cai_dat' => true ) + $dem;
			if ( $nha !== $khoi ) {
				$mot['danh_muc'] = array();
				foreach ( array_keys( self::DANH_MUC ) as $b ) {
					$d = self::doi_chieu( $khoi, $b );
					if ( null !== $d ) { $mot['danh_muc'][ $b ] = $d; }
				}
				$mot['mo_coi'] = self::mo_coi( $khoi );
			}
			$ra['kho'][ $khoi ] = $mot;
		}
		return VHCPHN_Util::ok( $ra );
	}
}
