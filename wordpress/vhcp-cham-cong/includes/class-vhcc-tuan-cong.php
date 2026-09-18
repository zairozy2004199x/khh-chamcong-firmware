<?php
/**
 * ĐƠN DUYỆT CHỈNH BẢNG CÔNG THEO THÁNG — cửa hàng trưởng sửa trên .xlsx, kế toán duyệt và khoá.
 *
 * =============================================================================================
 * 🔴 KỲ LÀ MỘT THÁNG, DÙ TÊN LỚP VẪN LÀ "TuanCong"
 * =============================================================================================
 * Anh Thắng 18/09/2026, ngay trong ngày chạy thử bản tuần: *"Với đi định theo tháng nhé"*. Lý do
 * thì rõ khi nhìn việc kế tiếp: tờ này sửa xong là để TÍNH LƯƠNG, mà lương chốt theo tháng. Bản
 * tuần bắt cửa hàng trưởng làm bốn lượt rồi kế toán duyệt bốn lượt cho một kỳ lương, và bốn ổ
 * khoá rời nhau — sót một tuần là lương sai mà không ai thấy.
 *
 * ⚠️ TÊN LỚP, TÊN TỆP VÀ BẢNG `don_tuan` GIỮ NGUYÊN. Đổi tên là đổi cả tên bảng đang có dữ liệu
 *    thật, cộng một lượt di trú — để lấy về đúng một chữ trong tên. Không đáng. Chỗ nào đọc mã
 *    này thì nhớ: `tu_ngay` là NGÀY 1 của tháng, `den_ngay` là ngày cuối tháng.
 *
 * ⚠️ ĐƠN CŨ THEO TUẦN VẪN CÒN TRONG BẢNG. `ten_ky()` nhìn cặp `tu_ngay`/`den_ngay` mà đoán ra
 *    đó là tuần hay tháng, nên màn kế toán và màn lịch sử in đúng nhãn cho cả hai đời. Đừng
 *    thay nó bằng một câu `'Tháng ' . …` cho gọn — mấy đơn cũ sẽ mang nhãn sai.
 *
 * Anh Thắng 18/09/2026: *"sau hết 1 tuần, bắt đầu tuần mới, lúc này bảng công sẽ cho tải 1 file
 * excel của tuần trước, cửa hàng trưởng sẻ sửa trong đó, và gửi cho kế toán. kế toán sẽ xem bảng
 * công đó và duyệt, thì sẽ đẩy lên bảng công và khóa lại, (chỉ duyệt và gửi 1 lần) nên cần đảm
 * bảo chính xác."* Và bốn câu chốt sau đó:
 *
 *   · tuần tính **T2 → CN**;
 *   · **chỉ tuần chưa khoá** mới tải được, Admin thì tải được cả tuần đã khoá;
 *   · kế toán quên duyệt thì **tuần nằm chờ** — cửa hàng trưởng đằng nào cũng không sửa được;
 *   · sai thì **kế toán báo gửi lại file khác** — tức "một lần" là một lần DUYỆT, không phải một
 *     lần gửi.
 *
 * =============================================================================================
 * 🔴 VÌ SAO CÓ CỘT KHOÁ XẤU XÍ Ở CUỐI TỜ
 * =============================================================================================
 * Tệp đi ra khỏi hệ, qua tay người, qua Excel, rồi quay về. Trong quãng ấy người ta sắp xếp lại
 * dòng, chèn dòng, xoá dòng, dán từ tuần khác sang. Ghép lại theo TÊN là ghép nhầm: tên trùng,
 * tên gõ lệch dấu, tên có hai khoảng trắng — và ghép nhầm thì giờ công của người này chui sang
 * người kia, im lặng, tới kỳ lương mới lộ.
 *
 * Nên mỗi dòng mang một khoá `cơ sở|ngày|mã NV|hậu tố` kèm chữ ký HMAC. Đọc lên thì:
 *   · khoá hỏng / bị sửa tay  → chối cả tệp, chỉ đúng dòng;
 *   · khoá của tuần khác      → chối, vì chữ ký gắn với đúng khoảng ngày ấy;
 *   · dòng bị xoá             → coi như không đổi, KHÔNG coi là xoá giờ (xem `doi()`).
 *
 * ⚠️ CHỮ KÝ KHÔNG PHẢI ĐỂ CHỐNG NGƯỜI XẤU. Ai sửa được tệp thì cũng đăng nhập được vào hệ. Nó để
 *    chống TAI NẠN: dán nhầm, kéo nhầm chuột, gửi nhầm tệp tuần trước. Đó mới là thứ hay xảy ra.
 *
 * =============================================================================================
 * 🔴 DUYỆT LÀ ĐI QUA `VHCC_Bu`, KHÔNG GHI THẲNG VÀO BẢNG
 * =============================================================================================
 * Ghi thẳng `UPDATE cham_cong` thì nhanh hơn và bỏ qua được mọi phiền phức — cũng bỏ qua luôn
 * nhật ký "Đã động vào giờ công", chốt không-tự-sửa-giờ-mình, và chốt cơ sở. Một lượt duyệt sửa
 * hàng trăm ô; đó là chỗ CUỐI CÙNG được phép đi tắt.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_TuanCong {

	/** Ai tải được tệp tuần. Cửa hàng trưởng — đúng người ngồi sửa. */
	const QUYEN_TAI = 'cong_coso';

	/** Ai duyệt. Kế toán — cùng bậc với `sua_gio`, vì duyệt chính là sửa hàng loạt. */
	const QUYEN_DUYET = 'sua_gio';

	/** Ai tải được tuần ĐÃ KHOÁ. Anh Thắng: *"admin có quyền tải nếu khóa"*. */
	const QUYEN_KHOA = 'he_thong';

	const CHO     = 'cho';
	const DUYET   = 'duyet';
	const TU_CHOI = 'tu_choi';

	const TEN_TT = array(
		self::CHO     => 'Chờ kế toán duyệt',
		self::DUYET   => 'Đã duyệt & khoá tháng',
		self::TU_CHOI => 'Không duyệt',
	);

	/** Lùi xa nhất được phép tải — sáu tháng là hết mọi kỳ lương còn tranh cãi được. */
	const THANG_LUI_TOI_DA = 6;

	/* ====================================================================== tháng (kỳ hiện hành) */

	/** Ngày 1 của tháng chứa `$ngay`. '' khi ngày rác. */
	public static function dau_thang( $ngay ) {
		$n = self::ngay( $ngay );
		if ( '' === $n ) { return ''; }
		return substr( $n, 0, 7 ) . '-01';
	}

	/**
	 * Ngày cuối của tháng bắt đầu từ `$dau`.
	 *
	 * ⚠️ Đếm bằng `t` của `gmdate`, đừng cộng 30 ngày: tháng 2 và năm nhuận sai ngay, và sai
	 *    đúng vào tháng mà ai cũng soát kỹ nhất.
	 */
	public static function cuoi_thang( $dau ) {
		$n = self::dau_thang( $dau );
		if ( '' === $n ) { return ''; }
		return gmdate( 'Y-m-t', strtotime( $n . ' 00:00:00 UTC' ) );
	}

	/** Mọi ngày của tháng, theo thứ tự. */
	public static function ngay_cua( $dau ) {
		$n = self::dau_thang( $dau );
		if ( '' === $n ) { return array(); }
		$ts  = strtotime( $n . ' 00:00:00 UTC' );
		$het = (int) gmdate( 't', $ts );
		$ra  = array();
		for ( $i = 0; $i < $het; $i++ ) { $ra[] = gmdate( 'Y-m-d', $ts + $i * 86400 ); }
		return $ra;
	}

	/** 'Tháng 09/2026' — nhãn của kỳ. */
	public static function ten_thang( $dau ) {
		$n = self::dau_thang( $dau );
		if ( '' === $n ) { return ''; }
		return 'Tháng ' . gmdate( 'm/Y', strtotime( $n . ' 00:00:00 UTC' ) );
	}

	/**
	 * Mấy tháng gần đây, mới nhất trước — cho ô chọn kỳ.
	 *
	 * 🔴 CÓ CẢ THÁNG ĐANG CHẠY, khác hẳn bản tuần. Một tuần chờ hết là chờ vài ngày; một tháng
	 *    chờ hết là cửa hàng trưởng nhìn thấy giờ sai từ mùng 2 mà tới mùng 1 tháng sau mới
	 *    sửa được. Đổi lại, khoá vẫn là việc của kế toán và vẫn phải tích ô xác nhận — chốt
	 *    "duyệt một lần" không suy suyển.
	 */
	public static function ds_thang( $so = 6 ) {
		$so = max( 1, min( self::THANG_LUI_TOI_DA, (int) $so ) );
		$d  = self::dau_thang( (string) current_time( 'Y-m-d' ) );
		$ra = array();
		for ( $i = 0; $i < $so; $i++ ) {
			$ra[] = $d;
			$d    = gmdate( 'Y-m-01', strtotime( $d . ' 00:00:00 UTC' ) - 86400 );
		}
		return $ra;
	}

	/**
	 * NHÃN CỦA MỘT KỲ — tháng hay tuần, tự đoán.
	 *
	 * 🔴 ĐỪNG RÚT GỌN THÀNH `ten_thang()`. Bảng `don_tuan` còn giữ đơn đời tuần (18/09/2026);
	 *    in nhãn tháng cho một đơn chỉ gồm bảy ngày là nói sai kỳ ngay trên màn duyệt.
	 *    `den_ngay` là thứ tách bạch hai đời — có tháng mà ngày 1 rơi đúng thứ Hai.
	 */
	public static function ten_ky( $tu_ngay, $den_ngay = '' ) {
		$t = self::ngay( $tu_ngay );
		if ( '' === $t ) { return ''; }
		$d = self::ngay( $den_ngay );
		if ( '01' === substr( $t, 8, 2 ) && ( '' === $d || $d === self::cuoi_thang( $t ) ) ) {
			return self::ten_thang( $t );
		}
		return self::ten_tuan_( $t );
	}

	/* ====================================================================== tuần (đời cũ) */

	/**
	 * 'T2 14/09 → CN 20/09/2026' — nhãn đời tuần, cho mấy đơn nạp trước 18/09/2026.
	 *
	 * ⚠️ Chỉ `ten_ky()` gọi tới, và chỉ để IN RA. Không còn chỗ nào dựng kỳ theo tuần nữa; mấy
	 *    hàm mốc tuần (`thu_hai`, `bay_ngay`, `ds_tuan`…) đã gỡ cùng lượt đổi sang tháng.
	 */
	private static function ten_tuan_( $tu_ngay ) {
		$t = self::ngay( $tu_ngay );
		if ( '' === $t ) { return ''; }
		$ts = strtotime( $t . ' 00:00:00 UTC' );
		return 'T2 ' . gmdate( 'd/m', $ts ) . ' → CN ' . gmdate( 'd/m/Y', $ts + 6 * 86400 );
	}

	/* ====================================================================== trạng thái tuần */

	/** Dòng đơn ĐÃ DUYỆT của kỳ ấy, hoặc null. Có nó ⇔ kỳ đã khoá. */
	public static function don_khoa( $coso, $tu_ngay ) {
		global $wpdb;
		$cs = VHCC_NhanSu::chuan_coso( $coso );
		$t  = self::ngay( $tu_ngay );
		if ( '' === $cs || '' === $t ) { return null; }
		$r = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . VHCC_DB::t( 'don_tuan' )
			. ' WHERE LOWER(coso)=LOWER(%s) AND tu_ngay=%s AND trang_thai=%s LIMIT 1',
			$cs, $t, self::DUYET ), ARRAY_A );
		return $r ? $r : null;
	}

	public static function khoa_roi( $coso, $tu_ngay ) {
		return null !== self::don_khoa( $coso, $tu_ngay );
	}

	/** Đơn đang chờ của kỳ ấy, hoặc null. */
	public static function don_cho( $coso, $tu_ngay ) {
		global $wpdb;
		$cs = VHCC_NhanSu::chuan_coso( $coso );
		$t  = self::ngay( $tu_ngay );
		if ( '' === $cs || '' === $t ) { return null; }
		$r = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . VHCC_DB::t( 'don_tuan' )
			. ' WHERE LOWER(coso)=LOWER(%s) AND tu_ngay=%s AND trang_thai=%s ORDER BY id DESC LIMIT 1',
			$cs, $t, self::CHO ), ARRAY_A );
		return $r ? $r : null;
	}

	/** Một đơn theo id. */
	public static function mot( $id ) {
		global $wpdb;
		$id = (int) $id;
		if ( $id <= 0 ) { return null; }
		$r = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . VHCC_DB::t( 'don_tuan' ) . ' WHERE id=%d', $id ), ARRAY_A );
		return $r ? $r : null;
	}

	/* ====================================================================== khoá dòng */

	/**
	 * Chữ ký của một dòng. Gắn với CƠ SỞ, NGÀY, NGƯỜI và CẢ KỲ — nên dán dòng từ tháng khác
	 * sang là chữ ký không khớp.
	 *
	 * ⚠️ Dùng `wp_salt()` chứ không phải một hằng tự chế. Đổi khoá WordPress thì mọi tệp đang
	 *    lưu hành mất hiệu lực — đúng, vì lúc ấy phiên đăng nhập cũng mất hết.
	 */
	public static function khoa_dong( $coso, $tu_ngay, $ngay, $ma_nv, $hau_to = '' ) {
		/* `$ngay` giữ lại trong chữ ký hàm cho nơi gọi cũ, nhưng KHÔNG dùng: từ bản ngang
		   18/09/2026 mỗi NGƯỜI một dòng, ngày đọc từ dòng tiêu đề. Một khoá cho cả dòng. */
		unset( $ngay );
		$ma = strtoupper( trim( (string) $ma_nv ) );
		$ht = trim( (string) $hau_to );
		$than = strtolower( VHCC_NhanSu::chuan_coso( $coso ) ) . '|' . self::ngay( $tu_ngay )
			. '|' . $ma . '|' . $ht;
		$ky = substr( hash_hmac( 'sha256', $than, self::muoi() ), 0, 10 );
		/* 🔴 BA PHẦN, HẬU TỐ CÓ CHỖ RIÊNG — kể cả khi rỗng (`MA~~ký`).
		   Bản đầu viết `MA-HT~ký` và cắt hậu tố theo dấu gạch. Sai, vì MÃ NV CỦA CHÍNH HỆ NÀY
		   CÓ DẤU GẠCH: `TAM-FZLTVT-008` bị cắt thành mã `TAM` + hậu tố `FZLTVT-008`, chữ ký
		   dựng lại không khớp, và MỌI tệp tuần của cơ sở dùng mã kiểu ấy đều bị chối ngay dòng
		   đầu. Anh Thắng gặp đúng lúc nạp thật (18/09/2026).
		   ⚠️ Đừng quay lại lối một dấu ngăn: dấu nào cũng có ngày lọt vào mã NV. */
		return $ma . '~' . $ht . '~' . $ky;
	}

	/** Đọc ngược một khoá dòng. null = hỏng, hoặc không thuộc tuần / cơ sở này. */
	public static function doc_khoa( $khoa, $coso, $tu_ngay ) {
		$p = explode( '~', trim( (string) $khoa ) );
		if ( 3 !== count( $p ) ) { return null; }
		$ma = strtoupper( trim( $p[0] ) );
		$ht = trim( $p[1] );
		if ( '' === $ma ) { return null; }
		/* So bằng `hash_equals` — so bằng `===` trên chuỗi băm là hở kênh phụ về thời gian.
		   Ở đây gần như vô hại, nhưng viết đúng một lần thì không phải nhớ chỗ nào hại chỗ nào. */
		$mong = self::khoa_dong( $coso, $tu_ngay, '', $ma, $ht );
		if ( ! hash_equals( $mong, trim( (string) $khoa ) ) ) { return null; }
		return array( 'ma_nv' => $ma, 'hau_to' => $ht );
	}

	private static function muoi() {
		if ( function_exists( 'wp_salt' ) ) { return (string) wp_salt( 'vhcc_tuan_cong' ); }
		return defined( 'AUTH_SALT' ) ? (string) AUTH_SALT : 'vhcc-tuan-cong';
	}

	/* ====================================================================== tiện */

	public static function ngay( $v ) {
		$s = trim( (string) $v );
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $s ) ? $s : '';
	}

	/** 'HH:mm' từ giây, hoặc '' khi trống. */
	public static function hhmm( $giay ) {
		if ( null === $giay || '' === $giay ) { return ''; }
		$g = (int) $giay;
		if ( $g < 0 ) { return ''; }
		return sprintf( '%02d:%02d', intdiv( $g, 3600 ) % 24, intdiv( $g % 3600, 60 ) );
	}

	/**
	 * Đọc một ô giờ người ta gõ trong Excel. Trả 'HH:mm', '' (ô trống = xoá), hoặc null (rác).
	 *
	 * ⚠️ EXCEL BIẾN Ô GIỜ THÀNH PHÂN SỐ CỦA MỘT NGÀY. Gõ `08:00` vào ô định dạng Thời gian thì
	 *    tệp lưu ra số `0.3333333`, không phải chữ "08:00". Không hiểu nhánh ấy thì mọi ô giờ
	 *    người ta gõ lại đều thành rác, và cả tệp bị chối mà không ai hiểu vì sao.
	 */
	public static function doc_gio( $v ) {
		$s = trim( (string) $v );
		if ( '' === $s ) { return ''; }

		/* Số thập phân 0..1 (và đúng 1 = 24:00) → phân số của một ngày. */
		if ( preg_match( '/^\d*[.,]\d+$/', $s ) || '1' === $s ) {
			$f = (float) str_replace( ',', '.', $s );
			if ( $f < 0 || $f > 1 ) { return null; }
			$phut = (int) round( $f * 1440 );
			if ( $phut >= 1440 ) { $phut = 1439; }
			return sprintf( '%02d:%02d', intdiv( $phut, 60 ), $phut % 60 );
		}
		/* 'HH:mm' hoặc 'HH:mm:ss' — Excel hay thêm giây khi lưu lại. */
		if ( preg_match( '/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $s, $m ) ) {
			$h = (int) $m[1];
			$p = (int) $m[2];
			if ( $h > 23 || $p > 59 ) { return null; }
			return sprintf( '%02d:%02d', $h, $p );
		}
		/* Gõ liền '0800'. */
		if ( preg_match( '/^(\d{2})(\d{2})$/', $s, $m ) ) {
			$h = (int) $m[1];
			$p = (int) $m[2];
			if ( $h > 23 || $p > 59 ) { return null; }
			return sprintf( '%02d:%02d', $h, $p );
		}
		return null;
	}

	/**
	 * Hai giờ thành MỘT ô hai hàng. Ô trống hẳn khi chưa có giờ nào.
	 *
	 * ⚠️ Chỉ có giờ vào thì vẫn viết một hàng, KHÔNG viết "08:00\n" với hàng hai rỗng: Excel
	 *    hiển thị ô ấy y hệt ô một hàng, nhưng lúc đọc lại thì ra hai mẩu và mẩu sau rỗng —
	 *    khác nhau ở chỗ không ai nhìn thấy được.
	 */
	public static function o_gio( $vao, $ra ) {
		$v = trim( (string) $vao );
		$r = trim( (string) $ra );
		if ( '' === $v && '' === $r ) { return ''; }
		if ( '' === $r ) { return $v; }
		if ( '' === $v ) { return "\n" . $r; }   // hiếm: có giờ ra mà không có giờ vào
		return $v . "\n" . $r;
	}

	/**
	 * Đọc ngược một ô hai hàng. Trả array( vào, ra ), hoặc null khi có mẩu không đọc được.
	 *
	 * ⚠️ NHẬN CẢ DẤU GẠCH VÀ MŨI TÊN. Người ta gõ tay vào ô thì hay viết `08:00-17:00` hoặc
	 *    `08:00 → 17:00` thay vì bấm Alt+Enter. Chối mấy kiểu ấy là chối đúng thứ người ta
	 *    định nói, và họ không đoán ra mình sai ở đâu.
	 */
	public static function doc_o_gio( $o ) {
		$s = trim( (string) $o );
		if ( '' === $s ) { return array( '', '' ); }

		$p = preg_split( '/\r\n|\r|\n|\s*(?:→|->|–|—|-)\s*/u', $s );
		$p = array_values( array_filter( array_map( 'trim', (array) $p ), function ( $x ) {
			return '' !== $x;
		} ) );

		if ( ! $p ) { return array( '', '' ); }
		if ( count( $p ) > 2 ) { return null; }

		$v = self::doc_gio( $p[0] );
		if ( null === $v ) { return null; }
		if ( 1 === count( $p ) ) { return array( $v, '' ); }
		$r = self::doc_gio( $p[1] );
		if ( null === $r ) { return null; }
		return array( $v, $r );
	}

	/* ====================================================================== lấy dữ liệu tuần */

	/* ═══════════════════════════════════════════════════════════════════════════════════════
	 * BỐ CỤC TỜ: MỘT NGƯỜI MỘT DÒNG, BẢY NGÀY NẰM NGANG
	 *
	 * Anh Thắng 18/09/2026: *"File excel sửa bảng công thì hiện ngày theo chiều ngang để dễ
	 * nhìn"*, kèm ảnh tờ bản đầu — mỗi người bảy dòng, tên lặp lại bảy lần, và phải cuộn mới
	 * thấy hết một người. Đúng: bảng công trên màn vốn nằm ngang, tờ Excel phải giống nó thì
	 * mắt mới soát được.
	 *
	 *   Mã NV │ Họ tên │ T2 07/09 │ T3 08/09 │ … │ Tổng giờ │ Lý do sửa │ KHOÁ
	 *                    08:00       09:00
	 *                    17:00       18:00
	 *
	 * MỘT Ô MỘT NGÀY, HAI HÀNG TRONG Ô — anh Thắng 18/09/2026: *"Chung 1 ô đi, làm 2 hàng trong
	 * 1 ô cũng được"*. Tách vào/ra thành hai cột là tờ rộng gấp đôi và phải cuộn ngang; gộp lại
	 * thì bảy ngày nằm gọn trên một màn.
	 *
	 * ⚠️ HAI HÀNG NGĂN BẰNG XUỐNG DÒNG TRONG Ô (Alt+Enter của Excel). Lúc đọc thì tách theo
	 *    xuống dòng; ô một hàng thì hiểu là CHỈ CÓ GIỜ VÀO, chưa có giờ ra — đúng cảnh người
	 *    quên bấm lúc về, và là cảnh hay gặp nhất.
	 *
	 * 🔴 NGÀY ĐỌC TỪ CHÍNH DÒNG TIÊU ĐỀ, KHÔNG ĐẾM THEO VỊ TRÍ CỘT. Người ta chèn thêm một cột
	 *    để ghi chú, hay kéo cột đi chỗ khác — đếm vị trí thì mọi giờ lệch sang ngày bên cạnh,
	 *    im lặng. Nên mỗi ô tiêu đề mang sẵn ngày dạng `YYYY-MM-DD`, và lúc đọc thì dò ngược
	 *    từ chữ ấy ra. Thiếu ngày nào thì chối cả tệp chứ không đoán.
	 *
	 * ⚠️ MỘT LÝ DO CHO CẢ DÒNG, không phải mỗi ngày một ô lý do. Ba mươi mốt ô lý do nữa là tờ
	 *    rộng gấp đôi và gần như luôn để trống. Đổi mấy ngày của cùng một người thì thường cùng một lý
	 *    do ("máy hỏng hôm ấy"); cần tách bạch thì gửi hai lượt.
	 * ═══════════════════════════════════════════════════════════════════════════════════════ */

	const C_MA  = 0;
	const C_TEN = 1;
	/** Cột ngày bắt đầu từ đây: mỗi ngày ĐÚNG MỘT Ô, hai hàng bên trong. */
	const C_NGAY_DAU = 2;
	const O_MOI_NGAY = 1;

	/**
	 * Ba cột đuôi. Từ bản tháng chúng KHÔNG còn ở vị trí cố định — tháng 28 ngày và tháng 31
	 * ngày lệch nhau ba cột.
	 *
	 * 🔴 Đây là lý do `doi()` dò cột theo CHỮ trong dòng tiêu đề chứ không đếm. Mã nào còn đếm
	 *    `C_NGAY_DAU + 7` là đọc nhầm sang ô giờ ngay khi qua tháng khác.
	 */
	public static function c_tong( $tu_ngay ) {
		return self::C_NGAY_DAU + count( self::ngay_cua( $tu_ngay ) ) * self::O_MOI_NGAY;
	}
	public static function c_lydo( $tu_ngay ) { return self::c_tong( $tu_ngay ) + 1; }
	public static function c_khoa( $tu_ngay ) { return self::c_tong( $tu_ngay ) + 2; }

	/** Dòng tiêu đề của tờ. */
	public static function cot( $tu_ngay ) {
		$c = array( 'Mã NV', 'Họ tên' );
		foreach ( self::ngay_cua( $tu_ngay ) as $ng ) {
			$c[] = self::ten_thu( $ng ) . ' ' . $ng;
		}
		$c[] = 'Tổng giờ tháng';
		$c[] = 'Lý do sửa';
		$c[] = 'KHOÁ — ĐỪNG SỬA';
		return $c;
	}

	public static function ten_thu( $ngay ) {
		$t = (int) gmdate( 'N', strtotime( $ngay . ' 00:00:00 UTC' ) );
		$b = array( 1 => 'T2', 2 => 'T3', 3 => 'T4', 4 => 'T5', 5 => 'T6', 6 => 'T7', 7 => 'CN' );
		return isset( $b[ $t ] ) ? $b[ $t ] : '';
	}

	/**
	 * Mọi dòng của kỳ: MỌI NGƯỜI × MỌI NGÀY TRONG THÁNG, kể cả ngày không có lượt chấm nào.
	 *
	 * 🔴 BÀY CẢ Ô TRỐNG, ĐÓ LÀ CHỦ Ý. Tờ chỉ có những ngày đã chấm thì cửa hàng trưởng không có
	 *    chỗ nào để điền ngày người ta quên bấm — mà đó chính là lý do quy trình này tồn tại.
	 *    Ô trống điền vào thì lúc duyệt đi đường `VHCC_Bu::ghi()` (bù), ô có sẵn thì đi đường
	 *    `VHCC_Bu::sua()`. Hai đường khác nhau, và khác đúng chỗ cần khác.
	 *
	 * ⚠️ Người đã nghỉ việc VẪN có mặt nếu tháng ấy họ còn chấm. Bỏ họ ra là tháng cuối của người
	 *    nghỉ việc không ai sửa được, mà đó lại là tháng hay sai nhất.
	 */
	public static function hang_ky( $coso, $tu_ngay ) {
		global $wpdb;
		$cs = VHCC_NhanSu::chuan_coso( $coso );
		$t2 = self::dau_thang( $tu_ngay );
		if ( '' === $cs || '' === $t2 ) { return array(); }
		$cn = self::cuoi_thang( $t2 );

		$cham = $wpdb->get_results( $wpdb->prepare(
			'SELECT ngay, ma_nv, hau_to, ho_ten, gio_vao_giay, gio_ra_giay FROM '
			. VHCC_DB::t( 'cham_cong' )
			. ' WHERE LOWER(coso)=LOWER(%s) AND ngay BETWEEN %s AND %s ORDER BY ma_nv, ngay, hau_to',
			$cs, $t2, $cn ), ARRAY_A );
		$cham = is_array( $cham ) ? $cham : array();

		/* Gom theo người, và nhớ luôn tên để dòng trống cũng có tên. */
		$co   = array();
		$ten  = array();
		foreach ( $cham as $r ) {
			$ma = strtoupper( trim( (string) $r['ma_nv'] ) );
			$co[ $ma . '|' . $r['ngay'] . '|' . (string) $r['hau_to'] ] = $r;
			if ( '' === trim( (string) ( isset( $ten[ $ma ] ) ? $ten[ $ma ] : '' ) ) ) {
				$ten[ $ma ] = (string) $r['ho_ten'];
			}
		}

		/* Danh sách người: lấy từ hồ sơ cơ sở, cộng thêm ai có chấm mà hồ sơ không kể ra. */
		$nguoi = array();
		foreach ( (array) $wpdb->get_results( $wpdb->prepare(
			'SELECT ma_nv, ho_ten FROM ' . VHCC_DB::t( 'nhan_vien' )
			. " WHERE LOWER(cua_hang)=LOWER(%s) AND trang_thai_lam_viec NOT IN ('Nghỉ việc','Nghỉ hẳn')"
			. ' ORDER BY ho_ten', $cs ), ARRAY_A ) as $r ) {
			$ma = strtoupper( trim( (string) $r['ma_nv'] ) );
			if ( '' === $ma ) { continue; }
			$nguoi[ $ma ] = (string) $r['ho_ten'];
		}
		foreach ( $ten as $ma => $tn ) {
			if ( ! isset( $nguoi[ $ma ] ) ) { $nguoi[ $ma ] = $tn; }
		}
		if ( ! $nguoi ) { return array(); }
		uasort( $nguoi, function ( $a, $b ) { return strcasecmp( (string) $a, (string) $b ); } );

		$ra = array();
		foreach ( $nguoi as $ma => $tn ) {
			foreach ( self::ngay_cua( $t2 ) as $ng ) {
				/* Hậu tố: mỗi hậu tố là một hàng riêng của cùng một người (ca đêm, tăng cường). */
				$ht_co = array( '' );
				foreach ( $co as $k => $_x ) {
					$p = explode( '|', $k );
					if ( 3 === count( $p ) && $p[0] === $ma && $p[1] === $ng && '' !== $p[2]
						&& ! in_array( $p[2], $ht_co, true ) ) {
						$ht_co[] = $p[2];
					}
				}
				foreach ( $ht_co as $ht ) {
					$r = isset( $co[ $ma . '|' . $ng . '|' . $ht ] ) ? $co[ $ma . '|' . $ng . '|' . $ht ] : null;
					$v = ( $r && null !== $r['gio_vao_giay'] && '' !== $r['gio_vao_giay'] ) ? (int) $r['gio_vao_giay'] : null;
					$o = ( $r && null !== $r['gio_ra_giay'] && '' !== $r['gio_ra_giay'] ) ? (int) $r['gio_ra_giay'] : null;
					$ra[] = array(
						'ngay'   => $ng,
						'thu'    => self::ten_thu( $ng ),
						'ma_nv'  => $ma,
						'hau_to' => $ht,
						'ho_ten' => ( $r ? (string) $r['ho_ten'] : $tn ),
						'vao'    => self::hhmm( $v ),
						'ra'     => self::hhmm( $o ),
						'gio'    => ( ( null !== $v && null !== $o && $o > $v )
							? round( ( $o - $v ) / 3600, 2 ) : null ),
						'khoa'   => self::khoa_dong( $cs, $t2, $ng, $ma, $ht ),
					);
				}
			}
		}
		return $ra;
	}

	/* ====================================================================== xuất */

	/** '' = được tải; khác rỗng = câu chối, nói rõ vì sao. */
	public static function vi_sao_khong_tai( $u, $coso, $tu_ngay ) {
		$cs = VHCC_NhanSu::chuan_coso( $coso );
		$t2 = self::ngay( $tu_ngay );
		if ( '' === $cs || '' === $t2 ) { return 'Thiếu cơ sở hoặc tháng.'; }
		if ( $t2 !== self::dau_thang( $t2 ) ) { return 'Kỳ phải bắt đầu từ ngày 1 của tháng.'; }
		if ( ! VHCC_Vai::duoc( $u, self::QUYEN_TAI ) ) {
			return VHCC_Vai::loi( $u, self::QUYEN_TAI, 'Tải bảng công tháng' );
		}
		if ( ! VHCC_NhanSu::co_quyen_coso( $u, $cs ) ) { return 'Không có quyền cơ sở này.'; }

		/* 🔴 THÁNG CHƯA TỚI thì chối — tờ của nó rỗng, tải về chỉ tổ nạp nhầm.
		   Nhưng THÁNG ĐANG CHẠY thì CHO, khác hẳn bản tuần. Chờ hết tuần là chờ vài ngày; chờ
		   hết tháng là thấy giờ sai từ mùng 2 mà tới mùng 1 tháng sau mới sửa được — và lúc ấy
		   thì lương đã trả. Khoá vẫn nằm ở tay kế toán, nên chốt "duyệt một lần" còn nguyên. */
		if ( $t2 > self::dau_thang( (string) current_time( 'Y-m-d' ) ) ) {
			return 'Tháng này chưa tới. Chỉ tải được tháng đang chạy hoặc tháng đã qua.';
		}

		/* Tháng đã khoá: chỉ Admin. Anh Thắng: *"admin có quyền tải nếu khóa"*. */
		if ( self::khoa_roi( $cs, $t2 ) && ! VHCC_Vai::duoc( $u, self::QUYEN_KHOA ) ) {
			return self::ten_ky( $t2 ) . ' đã được kế toán duyệt và khoá — không sửa nữa. '
				. 'Thấy còn sai thì báo kế toán.';
		}
		return '';
	}

	/** Nội dung tệp .xlsx của một tháng. `error` khi không dựng được. */
	public static function xuat( $u, $coso, $tu_ngay ) {
		$chan = self::vi_sao_khong_tai( $u, $coso, $tu_ngay );
		if ( '' !== $chan ) { return array( 'ok' => false, 'error' => $chan ); }
		if ( ! VHCC_Xuat::co_xlsx() ) {
			return array( 'ok' => false,
				'error' => 'Máy chủ chưa bật php-zip nên chưa dựng được .xlsx. Nhờ bên hosting bật giúp.' );
		}

		$cs = VHCC_NhanSu::chuan_coso( $coso );
		$t2 = self::dau_thang( $tu_ngay );
		$ds = self::hang_ky( $cs, $t2 );
		if ( ! $ds ) {
			return array( 'ok' => false,
				'error' => 'Cơ sở ' . $cs . ' chưa có người nào trong ' . self::ten_ky( $t2 ) . '.' );
		}

		/* Gom theo NGƯỜI (mã + hậu tố) — mỗi người một dòng, bảy ngày nằm ngang. */
		$theo_nguoi = array();
		foreach ( $ds as $r ) {
			$k = $r['ma_nv'] . '|' . $r['hau_to'];
			if ( ! isset( $theo_nguoi[ $k ] ) ) {
				$theo_nguoi[ $k ] = array( 'ma' => $r['ma_nv'], 'ht' => $r['hau_to'],
					'ten' => $r['ho_ten'], 'ngay' => array() );
			}
			$theo_nguoi[ $k ]['ngay'][ $r['ngay'] ] = $r;
		}

		$ngay_ky = self::ngay_cua( $t2 );
		$hang    = array( self::cot( $t2 ) );
		foreach ( $theo_nguoi as $n ) {
			$dong = array(
				/* 🔴 Mã NV LUÔN là chữ. `0029` để Excel tự đoán là thành số 29 — xem `VHCC_Xuat::o()`.
				   Kiểu GIUA_DOC vì ô ngày bên cạnh cao hai dòng. */
				VHCC_Xuat::o_kieu( VHCC_Xuat::chu( $n['ma']
					. ( '' !== $n['ht'] ? ( '-' . $n['ht'] ) : '' ) ), VHCC_Xuat::GIUA_DOC ),
				VHCC_Xuat::o_kieu( VHCC_Xuat::chu( $n['ten'] ), VHCC_Xuat::GIUA_DOC ),
			);
			$tong = 0.0;
			foreach ( $ngay_ky as $ng ) {
				$r = isset( $n['ngay'][ $ng ] ) ? $n['ngay'][ $ng ] : null;
				/* Kiểu HAI_HÀNG — thiếu `wrapText` thì Excel dính hai giờ làm một hàng. */
				$dong[] = VHCC_Xuat::o_kieu( self::o_gio( $r ? $r['vao'] : '', $r ? $r['ra'] : '' ),
					VHCC_Xuat::HAI_HANG );
				if ( $r && null !== $r['gio'] ) { $tong += (float) $r['gio']; }
			}
			$dong[] = VHCC_Xuat::o_kieu( ( $tong > 0 ? round( $tong, 2 ) : '' ), VHCC_Xuat::GIUA_DOC );
			$dong[] = VHCC_Xuat::o_kieu( '', VHCC_Xuat::GIUA_DOC );
			/* Khoá gắn với NGƯỜI + KỲ + CƠ SỞ. Ngày thì đọc từ dòng tiêu đề, nên một khoá cho
			   cả dòng là đủ — và ngắn hơn hẳn ba mươi mốt cái khoá của bản dọc. */
			$dong[] = VHCC_Xuat::o_kieu(
				VHCC_Xuat::chu( self::khoa_dong( $cs, $t2, '', $n['ma'], $n['ht'] ) ),
				VHCC_Xuat::GIUA_DOC );
			$hang[] = $dong;
		}

		/* ⚠️ Ô ngày hẹp lại còn 9 so với bản tuần (13): ba mươi mốt cột ngày ở bề rộng cũ là tờ
		   dài gấp bốn màn hình, phải cuộn ngang mới thấy hết một người — đúng cái mà bố cục
		   ngang sinh ra để tránh. 9 vẫn đủ cho 'HH:mm' trên hai hàng. */
		$noi = VHCC_Xuat::xlsx( array( array(
			'ten'  => 'Thang',
			'hang' => $hang,
			'cot'  => array_merge( array( 18, 26 ), array_fill( 0, count( $ngay_ky ), 9 ),
				array( 11, 34, 26 ) ),
		) ) );
		if ( null === $noi ) {
			return array( 'ok' => false, 'error' => 'Không dựng được tệp .xlsx trên máy chủ này.' );
		}
		return array( 'ok' => true, 'ten' => self::ten_tep( $cs, $t2 ), 'noi_dung' => $noi,
			'soDong' => count( $theo_nguoi ) );
	}

	public static function ten_tep( $coso, $tu_ngay ) {
		$cs = preg_replace( '/[^A-Za-z0-9_-]+/', '', (string) $coso );
		return 'bang-cong-' . ( '' !== $cs ? $cs : 'coso' ) . '-thang-'
			. substr( self::dau_thang( $tu_ngay ), 0, 7 ) . '.xlsx';
	}

	/* ====================================================================== nạp lên */

	/**
	 * Đọc tệp cửa hàng trưởng gửi lên, đối chiếu với bảng công đang có, DỰNG ĐƠN CHỜ DUYỆT.
	 *
	 * 🔴 KHÔNG GHI MỘT Ô NÀO VÀO BẢNG CÔNG Ở BƯỚC NÀY. Nạp chỉ đẻ ra một cái đơn. Ghi là việc
	 *    của `duyet()`, sau khi kế toán đã nhìn. Trộn hai bước là cửa hàng trưởng tự sửa được
	 *    bảng công bằng cách nạp tệp — đúng thứ vừa bị cấm ở `sua_gio`.
	 */
	public static function nap( $u, $coso, $tu_ngay, $duong_tep ) {
		$chan = self::vi_sao_khong_tai( $u, $coso, $tu_ngay );
		if ( '' !== $chan ) { return array( 'ok' => false, 'error' => $chan ); }

		$cs = VHCC_NhanSu::chuan_coso( $coso );
		$t2 = self::dau_thang( $tu_ngay );

		if ( self::khoa_roi( $cs, $t2 ) ) {
			return array( 'ok' => false, 'error' => 'Tháng này đã khoá — không nhận tệp nữa.' );
		}

		$doc = VHCC_DocXlsx::doc( $duong_tep );
		if ( empty( $doc['ok'] ) ) { return array( 'ok' => false, 'error' => $doc['error'] ); }

		$kq = self::doi( $cs, $t2, $doc['hang'] );
		if ( empty( $kq['ok'] ) ) { return $kq; }
		if ( ! $kq['doi'] ) {
			return array( 'ok' => false,
				'error' => 'Mọi ô giờ trong tệp đều TRÙNG với bảng công đang có, nên đã bỏ qua hết — '
					. 'không còn gì để kế toán duyệt. Nếu vừa sửa xong thì xem lại đã lưu tệp chưa, '
					. 'và đã gửi đúng tệp vừa sửa chưa.' );
		}

		global $wpdb;
		/* Gửi lượt mới thì lượt chờ cũ thành "không duyệt" — hai đơn cùng chờ cho một tháng là
		   kế toán duyệt nhầm cái cũ. Anh Thắng: *"nếu sai, kế toán sẽ báo cht gửi lại file khác"*. */
		$wpdb->update( VHCC_DB::t( 'don_tuan' ),
			array( 'trang_thai' => self::TU_CHOI,
				'ly_do_choi' => 'Cửa hàng trưởng gửi tệp mới thay cho lượt này.',
				'duyet_luc' => current_time( 'mysql' ) ),
			array( 'coso' => $cs, 'tu_ngay' => $t2, 'trang_thai' => self::CHO ) );

		$wpdb->insert( VHCC_DB::t( 'don_tuan' ), array(
			'coso'       => $cs,
			'tu_ngay'    => $t2,
			'den_ngay'   => self::cuoi_thang( $t2 ),
			'ma_nv_gui'  => isset( $u['ma_nv'] ) ? (string) $u['ma_nv'] : '',
			'ten_gui'    => isset( $u['name'] ) ? (string) $u['name'] : '',
			'gui_luc'    => current_time( 'mysql' ),
			'trang_thai' => self::CHO,
			'so_dong'    => (int) $kq['soDong'],
			'so_doi'     => count( $kq['doi'] ),
			'doi'        => wp_json_encode( $kq['doi'] ),
		) );
		$id = (int) $wpdb->insert_id;

		self::bao_ke_toan( $cs, $t2, $id, $u, count( $kq['doi'] ) );

		return array( 'ok' => true, 'id' => $id, 'soDoi' => count( $kq['doi'] ),
			'soDong' => (int) $kq['soDong'], 'doi' => $kq['doi'],
			'soTrung' => (int) $kq['soTrung'], 'soLap' => (int) $kq['soLap'] );
	}

	/**
	 * Đối chiếu tệp với bảng công đang có.
	 *
	 * ⚠️ DÒNG BỊ XOÁ KHỎI TỆP = KHÔNG ĐỔI, không phải "xoá giờ". Người ta hay lọc bảng rồi lưu
	 *    lại, và lúc ấy Excel giữ nguyên nhưng người ta tưởng mình đã xoá. Hiểu dòng thiếu là
	 *    lệnh xoá thì một cú lọc nhầm là bay cả tuần công. Muốn xoá giờ thì XOÁ NỘI DUNG Ô, để
	 *    dòng lại — cách ấy rõ ràng và cố ý.
	 */
	public static function doi( $coso, $tu_ngay, $hang ) {
		$cs = VHCC_NhanSu::chuan_coso( $coso );
		$t2 = self::dau_thang( $tu_ngay );

		$hang = array_values( (array) $hang );
		if ( count( $hang ) < 2 ) {
			return array( 'ok' => false, 'error' => 'Tệp không có dòng dữ liệu nào.' );
		}
		$dau = array_values( (array) array_shift( $hang ) );

		/* 🔴 DÒ NGÀY TỪ CHÍNH DÒNG TIÊU ĐỀ. Đếm vị trí cột thì chèn thêm một cột ghi chú là mọi
		   giờ lệch sang ngày bên cạnh, im lặng. Mỗi ô tiêu đề mang sẵn `YYYY-MM-DD` + 'vào'/'ra'. */
		$cot_ngay = array();          // chỉ số cột -> ngày
		$co_khoa  = -1;
		$co_lydo  = -1;
		foreach ( $dau as $i_c => $o ) {
			$chu = trim( (string) $o );
			if ( false !== mb_strpos( $chu, 'KHOÁ' ) ) { $co_khoa = $i_c; continue; }
			if ( false !== mb_strpos( $chu, 'Lý do' ) ) { $co_lydo = $i_c; continue; }
			if ( ! preg_match( '/(\d{4}-\d{2}-\d{2})/', $chu, $m ) ) { continue; }
			$cot_ngay[ $i_c ] = $m[1];
		}

		if ( $co_khoa < 0 ) {
			return array( 'ok' => false, 'error' => 'Tệp này không đúng mẫu bảng công tháng '
				. '(thiếu cột KHOÁ). Tải lại tệp mẫu rồi sửa trên đó, đừng dựng tệp mới.' );
		}
		$bay = self::ngay_cua( $t2 );
		$thay = array();
		foreach ( $cot_ngay as $x ) { $thay[ $x ] = true; }
		foreach ( $bay as $ng ) {
			if ( ! isset( $thay[ $ng ] ) ) {
				return array( 'ok' => false, 'error' => 'Tệp thiếu cột của ngày ' . $ng
					. '. Đừng xoá hay đổi tên cột ngày — tải lại tệp mẫu rồi sửa trên đó.' );
			}
		}

		/* Bảng công đang có, tra theo khoá dòng rồi tới ngày. */
		$dang = array();
		foreach ( self::hang_ky( $cs, $t2 ) as $r ) {
			$dang[ $r['khoa'] ][ $r['ngay'] ] = $r;
		}

		$doi      = array();
		$so       = 0;
		$so_lap   = 0;
		$so_trung = 0;
		$da_gap = array();
		foreach ( $hang as $i_d => $d ) {
			if ( ! is_array( $d ) ) { continue; }
			$d    = array_values( $d );
			$khoa = isset( $d[ $co_khoa ] ) ? trim( (string) $d[ $co_khoa ] ) : '';
			if ( '' === $khoa ) { continue; }          // dòng người ta chèn thêm — bỏ, không đoán

			/* 🔴 DÒNG LẶP LẠI CÙNG MỘT KHOÁ THÌ BỎ QUA, KHÔNG CHỐI CẢ TỆP — anh Thắng 18/09/2026:
			   *"Khi up giờ trùng thì bỏ qua nhé"*. Tờ một tháng dài nên người ta hay dán thêm
			   một khối để đối chiếu rồi quên xoá. Lấy cả hai dòng thì cùng một ngày vào đơn hai
			   lần, và lúc duyệt ô sau đè lên ô trước — im lặng. Lấy dòng ĐẦU: đó là dòng nằm
			   đúng chỗ tệp mẫu sinh ra, khối dán thêm bao giờ cũng ở dưới. */
			if ( isset( $da_gap[ $khoa ] ) ) { $so_lap++; continue; }
			$da_gap[ $khoa ] = true;

			$so++;
			$dong_so = $i_d + 2;

			$k = self::doc_khoa( $khoa, $cs, $t2 );
			if ( null === $k ) {
				return array( 'ok' => false, 'error' => 'Dòng ' . $dong_so . ' có cột KHOÁ sai. '
					. 'Thường là do dán từ tệp tháng khác sang, hoặc sửa tay vào cột ấy. '
					. 'Tải lại tệp của đúng ' . self::ten_ky( $t2 ) . ' rồi sửa trên đó.' );
			}
			if ( ! isset( $dang[ $khoa ] ) ) {
				return array( 'ok' => false, 'error' => 'Dòng ' . $dong_so
					. ' không còn khớp với bảng công (người này có thể đã đổi cơ sở). Tải lại tệp mới.' );
			}

			/* Gom hai ô vào/ra của từng ngày trên dòng này. */
			$moi = array();
			foreach ( $cot_ngay as $i_c => $ng_c ) {
				$g = self::doc_o_gio( isset( $d[ $i_c ] ) ? $d[ $i_c ] : '' );
				if ( null === $g ) {
					$ten_x = isset( $dang[ $khoa ][ $ng_c ]['ho_ten'] ) ? $dang[ $khoa ][ $ng_c ]['ho_ten'] : '';
					return array( 'ok' => false, 'error' => 'Dòng ' . $dong_so . ' (' . $ten_x
						. ') có ô giờ ngày ' . $ng_c . ' không đọc được. Trong một ô viết giờ vào ở '
						. 'hàng trên, giờ ra ở hàng dưới (Alt+Enter để xuống hàng), kiểu 24 giờ: '
						. '08:00 rồi 17:30.' );
				}
				$moi[ $ng_c ] = array( 'vao' => $g[0], 'ra' => $g[1] );
			}

			$ly_do   = ( $co_lydo >= 0 && isset( $d[ $co_lydo ] ) ) ? trim( (string) $d[ $co_lydo ] ) : '';
			$dong_doi = array();

			foreach ( $bay as $ng ) {
				if ( ! isset( $dang[ $khoa ][ $ng ] ) ) { continue; }
				$cu  = $dang[ $khoa ][ $ng ];
				$vao = isset( $moi[ $ng ]['vao'] ) ? $moi[ $ng ]['vao'] : $cu['vao'];
				$ra  = isset( $moi[ $ng ]['ra'] ) ? $moi[ $ng ]['ra'] : $cu['ra'];
				/* Ô GIỜ TRÙNG SẴN THÌ BỎ QUA — không vào đơn, không bắt kế toán đọc. Tờ một
				   tháng có hơn ba nghìn ô mà thường chỉ vài ô đổi; đây là nhánh chạy nhiều
				   nhất của cả hàm. */
				if ( $vao === $cu['vao'] && $ra === $cu['ra'] ) { $so_trung++; continue; }
				if ( '' !== $vao && '' !== $ra && $ra <= $vao ) {
					return array( 'ok' => false, 'error' => 'Dòng ' . $dong_so . ' (' . $cu['ho_ten']
						. ' ngày ' . $ng . ') có giờ ra không sau giờ vào.' );
				}
				$dong_doi[] = array(
					'khoa'  => $khoa,
					'ngay'  => $ng,
					'maNV'  => $cu['ma_nv'],
					'hauTo' => $cu['hau_to'],
					'hoTen' => $cu['ho_ten'],
					'vaoCu' => $cu['vao'],
					'raCu'  => $cu['ra'],
					'vao'   => $vao,
					'ra'    => $ra,
					'them'  => ( '' === $cu['vao'] && '' === $cu['ra'] ),
				);
			}

			if ( ! $dong_doi ) { continue; }

			/* ⚠️ MỘT LÝ DO CHO CẢ DÒNG. Sửa ba ngày của cùng một người thì thường cùng một lý do;
			   ba mươi mốt ô lý do nữa là tờ rộng gấp đôi và gần như luôn để trống. */
			if ( mb_strlen( $ly_do, 'UTF-8' ) < 5 ) {
				return array( 'ok' => false, 'error' => 'Dòng ' . $dong_so . ' ('
					. $dong_doi[0]['hoTen'] . ') có sửa giờ nhưng chưa ghi Lý do sửa. '
					. 'Mỗi dòng có sửa đều phải nói vì sao, ít nhất 5 chữ.' );
			}
			foreach ( $dong_doi as $o ) {
				$o['lyDo'] = mb_substr( $ly_do, 0, 200 );
				$doi[]     = $o;
			}
		}

		if ( ! $so ) {
			return array( 'ok' => false, 'error' => 'Tệp không có dòng nào mang cột KHOÁ.' );
		}
		return array( 'ok' => true, 'doi' => $doi, 'soDong' => $so,
			'soTrung' => $so_trung, 'soLap' => $so_lap );
	}

	/**
	 * Ai duyệt được đơn này — để rung chuông cho đúng người.
	 *
	 * ⚠️ HỎI TỪNG NGƯỜI BẰNG CHÍNH `VHCC_Vai::duoc()`, không tự dựng lại luật bậc ở đây. Chép
	 *    lại luật là hai bản luật, và tới ngày ai đó khai một dòng ngoại lệ thì bản chép không
	 *    biết — người được chỉ định không nhận được tin, còn người bị khoá thì vẫn nhận.
	 *
	 * ⚠️ Chỉ lấy người CÓ MÃ NV. Hộp thư đánh địa chỉ bằng mã; tài khoản không mã thì không có
	 *    hộp nào để gửi tới, và họ vẫn thấy đơn ở màn "Đơn duyệt chỉnh bảng công lương".
	 */
	public static function ai_duyet() {
		global $wpdb;
		$ra = array();
		foreach ( (array) $wpdb->get_results(
			'SELECT ma_nv, ho_ten, vai_tro, cua_hang FROM ' . VHCC_DB::t( 'nhan_vien' )
			. " WHERE ma_nv <> '' AND trang_thai_lam_viec NOT IN ('Nghỉ việc','Nghỉ hẳn')",
			ARRAY_A ) as $r ) {
			$nguoi = array(
				'ma_nv' => (string) $r['ma_nv'],
				'name'  => (string) $r['ho_ten'],
				'role'  => (string) $r['vai_tro'],
				'coso'  => (string) $r['cua_hang'],
			);
			if ( VHCC_Vai::duoc( $nguoi, self::QUYEN_DUYET ) ) { $ra[] = (string) $r['ma_nv']; }
		}
		return array_values( array_unique( $ra ) );
	}

	private static function bao_ke_toan( $cs, $t2, $id, $u, $so_doi ) {
		if ( ! class_exists( 'VHCC_Chuong' ) || ! method_exists( 'VHCC_Chuong', 'bao' ) ) { return; }
		$chu = 'Cửa hàng ' . $cs . ' gửi đơn chỉnh bảng công ' . self::ten_ky( $t2 )
			. ' — ' . (int) $so_doi . ' ô giờ chờ duyệt.';
		foreach ( self::ai_duyet() as $ma ) {
			VHCC_Chuong::bao( $ma, $chu, 'cc_dontuan:' . (int) $id,
				isset( $u['ma_nv'] ) ? (string) $u['ma_nv'] : '' );
		}
	}

	/* ====================================================================== duyệt */

	/** Đơn đang chờ, mới nhất trước — màn của kế toán. */
	public static function ds_cho( $u, $so = 50 ) {
		global $wpdb;
		if ( ! VHCC_Vai::duoc( $u, self::QUYEN_DUYET ) ) { return array(); }
		$r = $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . VHCC_DB::t( 'don_tuan' )
			. ' WHERE trang_thai=%s ORDER BY gui_luc ASC, id ASC LIMIT %d',
			self::CHO, max( 1, min( 200, (int) $so ) ) ), ARRAY_A );
		return is_array( $r ) ? $r : array();
	}

	/** Đơn đã xử gần đây — để soát lại, và để thấy tuần nào đã khoá. */
	public static function ds_xong( $u, $so = 30 ) {
		global $wpdb;
		if ( ! VHCC_Vai::duoc( $u, self::QUYEN_DUYET ) ) { return array(); }
		$r = $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . VHCC_DB::t( 'don_tuan' )
			. ' WHERE trang_thai<>%s ORDER BY duyet_luc DESC, id DESC LIMIT %d',
			self::CHO, max( 1, min( 200, (int) $so ) ) ), ARRAY_A );
		return is_array( $r ) ? $r : array();
	}

	/** Danh sách ô sẽ đổi của một đơn, đã giải mã. */
	public static function doi_cua( $don ) {
		if ( ! is_array( $don ) || empty( $don['doi'] ) ) { return array(); }
		$d = json_decode( (string) $don['doi'], true );
		return is_array( $d ) ? $d : array();
	}

	/**
	 * KẾ TOÁN DUYỆT HOẶC CHỐI.
	 *
	 * =========================================================================================
	 * 🔴 DUYỆT LÀ MỘT LƯỢT, KHÔNG QUAY LẠI ĐƯỢC
	 * =========================================================================================
	 * Anh Thắng: *"chỉ duyệt và gửi 1 lần) nên cần đảm bảo chính xác"*. Duyệt xong là tháng khoá:
	 * không tải lại, không nạp lại, không duyệt lần hai. Nên ở đây chốt kỹ hai chỗ:
	 *   · đơn phải còn ở trạng thái CHỜ — bấm hai lần trên hai tab không ghi hai lượt;
	 *   · tháng chưa có đơn nào đã duyệt — hai đơn của cùng một tháng thì chỉ một cái ăn.
	 *
	 * ⚠️ THÁNG VẪN KHOÁ DÙ CÓ Ô TRƯỢT. Mỗi ô đi qua `VHCC_Bu` với đủ gác cũ, nên vài ô có thể bị
	 *    chối (giờ của chính kế toán, người đã đổi cơ sở…). Không khoá vì vài ô trượt thì cửa
	 *    hàng trưởng phải gửi lại cả tháng cho mấy ô mà kế toán đã cố ý chấp nhận. Ô nào trượt
	 *    ghi thẳng vào `ket_qua` của đơn, kế toán đọc lại được và xử tay từng ô.
	 */
	public static function duyet( $u, $id, $dong_y = true, $ly_do_choi = '' ) {
		global $wpdb;

		if ( ! VHCC_Vai::duoc( $u, self::QUYEN_DUYET ) ) {
			return array( 'ok' => false,
				'error' => VHCC_Vai::loi( $u, self::QUYEN_DUYET, 'Duyệt đơn chỉnh bảng công' ) );
		}
		$don = self::mot( $id );
		if ( ! $don ) { return array( 'ok' => false, 'error' => 'Không thấy đơn này.' ); }
		if ( self::CHO !== (string) $don['trang_thai'] ) {
			return array( 'ok' => false, 'error' => 'Đơn này đã xử rồi ('
				. ( isset( self::TEN_TT[ $don['trang_thai'] ] ) ? self::TEN_TT[ $don['trang_thai'] ] : $don['trang_thai'] )
				. '). Tải lại trang để xem trạng thái mới.' );
		}

		$ai = array(
			'ma_nv_duyet' => isset( $u['ma_nv'] ) ? (string) $u['ma_nv'] : '',
			'ten_duyet'   => isset( $u['name'] ) ? (string) $u['name'] : '',
			'duyet_luc'   => current_time( 'mysql' ),
		);

		if ( ! $dong_y ) {
			$ly = trim( (string) $ly_do_choi );
			if ( mb_strlen( $ly, 'UTF-8' ) < 5 ) {
				return array( 'ok' => false,
					'error' => 'Không duyệt thì phải nói vì sao — cửa hàng trưởng còn sửa lại mà gửi tệp khác.' );
			}
			$wpdb->update( VHCC_DB::t( 'don_tuan' ),
				array_merge( $ai, array( 'trang_thai' => self::TU_CHOI,
					'ly_do_choi' => mb_substr( $ly, 0, 250 ) ) ),
				array( 'id' => (int) $don['id'] ) );
			self::bao_gui( $don, false, $ly, $u );
			return array( 'ok' => true, 'quyet' => self::TU_CHOI );
		}

		if ( self::khoa_roi( $don['coso'], $don['tu_ngay'] ) ) {
			return array( 'ok' => false, 'error' => self::ten_ky( $don['tu_ngay'], $don['den_ngay'] )
				. ' của cơ sở này đã có một đơn được duyệt rồi.' );
		}

		/* 🔴 ĐỌC LẠI BẢNG CÔNG NGAY LÚC DUYỆT, đừng tin cờ `them` chụp lúc nạp. Giữa lúc cửa
		   hàng gửi và lúc kế toán bấm có thể vài ngày — trong quãng ấy máy chấm công đã ghi
		   thêm, hoặc một đơn xin bù lẻ đã được duyệt. Tin cờ cũ thì:
		     · ô nay đã có giờ mà vẫn đi đường BÙ → `VHCC_Bu::ghi()` chối "đã có đủ giờ rồi",
		       ô trượt, mà tháng thì vừa khoá xong;
		     · ô nay đã đúng y giờ cần ghi → vẫn ghi một lượt nữa, đẻ thêm một dòng nhật ký
		       "đã động vào giờ công" cho một thay đổi không có thật.
		   Anh Thắng 18/09/2026: *"Khi up giờ trùng thì bỏ qua nhé"*. */
		$gio_nay = array();
		foreach ( self::hang_ky( $don['coso'], $don['tu_ngay'] ) as $r ) {
			$gio_nay[ $r['ma_nv'] . '|' . $r['hau_to'] . '|' . $r['ngay'] ] = $r;
		}

		$xong  = 0;
		$trung = 0;
		$truot = array();
		foreach ( self::doi_cua( $don ) as $o ) {
			$dat = array(
				'coso'   => (string) $don['coso'],
				'ngay'   => (string) $o['ngay'],
				'ma_nv'  => (string) $o['maNV'] . ( '' !== (string) $o['hauTo'] ? ( '-' . $o['hauTo'] ) : '' ),
				'vao'    => (string) $o['vao'],
				'ra'     => (string) $o['ra'],
				'ly_do'  => 'Đơn tháng #' . (int) $don['id'] . ': ' . (string) $o['lyDo'],
			);

			$k   = (string) $o['maNV'] . '|' . (string) $o['hauTo'] . '|' . (string) $o['ngay'];
			$nay = isset( $gio_nay[ $k ] ) ? $gio_nay[ $k ] : null;

			/* Đã đúng y giờ cần ghi → BỎ QUA, không tính là trượt. */
			if ( $nay && (string) $nay['vao'] === (string) $o['vao']
				&& (string) $nay['ra'] === (string) $o['ra'] ) {
				$trung++;
				continue;
			}

			/* Ô đang TRỐNG thì là BÙ (điền vào chỗ chưa có), ô đang CÓ GIỜ thì là SỬA ĐÈ. Hai
			   đường gác khác nhau và ghi nhật ký khác nhau — xem `VHCC_Bu`. */
			$dang_trong = $nay ? ( '' === (string) $nay['vao'] && '' === (string) $nay['ra'] )
				: ! empty( $o['them'] );
			$r = $dang_trong ? VHCC_Bu::ghi( $u, $dat ) : VHCC_Bu::sua( $u, $dat );
			if ( ! empty( $r['ok'] ) ) {
				$xong++;
			} else {
				$truot[] = array( 'ngay' => $o['ngay'], 'maNV' => $o['maNV'],
					'hoTen' => $o['hoTen'], 'error' => isset( $r['error'] ) ? $r['error'] : 'Không ghi được.' );
			}
		}

		$wpdb->update( VHCC_DB::t( 'don_tuan' ),
			array_merge( $ai, array( 'trang_thai' => self::DUYET,
				'ket_qua' => wp_json_encode( array( 'xong' => $xong, 'trung' => $trung,
					'truot' => $truot ) ) ) ),
			array( 'id' => (int) $don['id'] ) );

		self::bao_gui( $don, true, '', $u, $xong, count( $truot ) );

		return array( 'ok' => true, 'quyet' => self::DUYET, 'xong' => $xong, 'trung' => $trung,
			'truot' => $truot, 'khoa' => self::ten_ky( $don['tu_ngay'], $don['den_ngay'] ) );
	}

	private static function bao_gui( $don, $dong_y, $ly_do, $u, $xong = 0, $so_truot = 0 ) {
		if ( ! class_exists( 'VHCC_Chuong' ) || ! method_exists( 'VHCC_Chuong', 'bao' ) ) { return; }
		$ma = trim( (string) $don['ma_nv_gui'] );
		if ( '' === $ma ) { return; }
		$chu = $dong_y
			? ( 'Đơn chỉnh bảng công ' . self::ten_ky( $don['tu_ngay'], $don['den_ngay'] )
				. ' đã được duyệt — ' . (int) $xong . ' ô đã lên bảng công'
				. ( $so_truot > 0 ? ( ', ' . (int) $so_truot . ' ô không ghi được' ) : '' )
				. '. Tháng này đã khoá.' )
			: ( 'Đơn chỉnh bảng công ' . self::ten_ky( $don['tu_ngay'], $don['den_ngay'] )
				. ' không được duyệt. Lý do: ' . $ly_do . ' Sửa lại rồi gửi tệp khác.' );
		VHCC_Chuong::bao( $ma, $chu, 'cc_dontuan:' . (int) $don['id'],
			isset( $u['ma_nv'] ) ? (string) $u['ma_nv'] : '' );
	}
}
