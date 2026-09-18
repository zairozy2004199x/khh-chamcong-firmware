<?php
/**
 * ĐƠN DUYỆT CHỈNH BẢNG CÔNG THEO TUẦN — cửa hàng trưởng sửa trên .xlsx, kế toán duyệt và khoá.
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
		self::DUYET   => 'Đã duyệt & khoá tuần',
		self::TU_CHOI => 'Không duyệt',
	);

	/** Lùi xa nhất được phép tải. Quá đây thì đó là việc của kế toán, không phải sửa tuần. */
	const TUAN_LUI_TOI_DA = 12;

	/* ====================================================================== tuần */

	/**
	 * Thứ Hai của tuần chứa `$ngay`. Tuần T2 → CN, theo đúng lời anh Thắng.
	 *
	 * ⚠️ Tính bằng `strtotime` trên chuỗi có hậu tố UTC, KHÔNG dùng `date('N')` của máy chủ —
	 *    máy chủ hosting hay đặt múi giờ UTC trong khi `current_time()` đã cộng lệch sẵn, và
	 *    trộn hai lối là lệch một ngày đúng vào đêm Chủ nhật.
	 */
	public static function thu_hai( $ngay ) {
		$n = self::ngay( $ngay );
		if ( '' === $n ) { return ''; }
		$ts  = strtotime( $n . ' 00:00:00 UTC' );
		$thu = (int) gmdate( 'N', $ts );          // 1 = T2 … 7 = CN
		return gmdate( 'Y-m-d', $ts - ( $thu - 1 ) * 86400 );
	}

	/** Chủ nhật của tuần bắt đầu từ `$tu_ngay`. */
	public static function chu_nhat( $tu_ngay ) {
		$n = self::ngay( $tu_ngay );
		if ( '' === $n ) { return ''; }
		return gmdate( 'Y-m-d', strtotime( $n . ' 00:00:00 UTC' ) + 6 * 86400 );
	}

	/** Thứ Hai của TUẦN TRƯỚC so với hôm nay — tuần mặc định bày ra để tải. */
	public static function tuan_truoc() {
		$t2 = self::thu_hai( (string) current_time( 'Y-m-d' ) );
		return gmdate( 'Y-m-d', strtotime( $t2 . ' 00:00:00 UTC' ) - 7 * 86400 );
	}

	/** Bảy ngày của tuần. */
	public static function bay_ngay( $tu_ngay ) {
		$n = self::ngay( $tu_ngay );
		if ( '' === $n ) { return array(); }
		$ts = strtotime( $n . ' 00:00:00 UTC' );
		$ra = array();
		for ( $i = 0; $i < 7; $i++ ) { $ra[] = gmdate( 'Y-m-d', $ts + $i * 86400 ); }
		return $ra;
	}

	/** 'T2 14/09 → CN 20/09/2026' — để in trên nút và trên đơn. */
	public static function ten_tuan( $tu_ngay ) {
		$t = self::ngay( $tu_ngay );
		if ( '' === $t ) { return ''; }
		$c = self::chu_nhat( $t );
		return 'T2 ' . gmdate( 'd/m', strtotime( $t . ' 00:00:00 UTC' ) )
			. ' → CN ' . gmdate( 'd/m/Y', strtotime( $c . ' 00:00:00 UTC' ) );
	}

	/** Mấy tuần gần đây, mới nhất trước — cho ô chọn tuần. Không bao giờ có tuần đang chạy. */
	public static function ds_tuan( $so = 8 ) {
		$so = max( 1, min( self::TUAN_LUI_TOI_DA, (int) $so ) );
		$t  = self::tuan_truoc();
		$ra = array();
		for ( $i = 0; $i < $so; $i++ ) {
			$ra[] = gmdate( 'Y-m-d', strtotime( $t . ' 00:00:00 UTC' ) - $i * 7 * 86400 );
		}
		return $ra;
	}

	/* ====================================================================== trạng thái tuần */

	/** Dòng đơn ĐÃ DUYỆT của tuần ấy, hoặc null. Có nó ⇔ tuần đã khoá. */
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

	/** Đơn đang chờ của tuần ấy, hoặc null. */
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
	 * Chữ ký của một dòng. Gắn với CƠ SỞ, NGÀY, NGƯỜI và CẢ TUẦN — nên dán dòng từ tuần khác
	 * sang là chữ ký không khớp.
	 *
	 * ⚠️ Dùng `wp_salt()` chứ không phải một hằng tự chế. Đổi khoá WordPress thì mọi tệp đang
	 *    lưu hành mất hiệu lực — đúng, vì lúc ấy phiên đăng nhập cũng mất hết.
	 */
	public static function khoa_dong( $coso, $tu_ngay, $ngay, $ma_nv, $hau_to = '' ) {
		$than = strtolower( VHCC_NhanSu::chuan_coso( $coso ) ) . '|' . self::ngay( $tu_ngay ) . '|'
			. self::ngay( $ngay ) . '|' . strtoupper( trim( (string) $ma_nv ) ) . '|'
			. trim( (string) $hau_to );
		$ky = substr( hash_hmac( 'sha256', $than, self::muoi() ), 0, 10 );
		return self::ngay( $ngay ) . '~' . strtoupper( trim( (string) $ma_nv ) )
			. ( '' !== trim( (string) $hau_to ) ? ( '-' . trim( (string) $hau_to ) ) : '' ) . '~' . $ky;
	}

	/** Đọc ngược một khoá. null = hỏng hoặc không thuộc tuần / cơ sở này. */
	public static function doc_khoa( $khoa, $coso, $tu_ngay ) {
		$p = explode( '~', trim( (string) $khoa ) );
		if ( 3 !== count( $p ) ) { return null; }
		$ngay = self::ngay( $p[0] );
		if ( '' === $ngay ) { return null; }
		$ma = strtoupper( trim( $p[1] ) );
		$ht = '';
		if ( false !== strpos( $ma, '-' ) ) {
			$c  = explode( '-', $ma, 2 );
			$ma = $c[0];
			$ht = $c[1];
		}
		if ( '' === $ma ) { return null; }
		/* So bằng `hash_equals` — so bằng `===` trên chuỗi băm là hở kênh phụ về thời gian.
		   Ở đây gần như vô hại, nhưng viết đúng một lần thì không phải nhớ chỗ nào hại chỗ nào. */
		$mong = self::khoa_dong( $coso, $tu_ngay, $ngay, $ma, $ht );
		if ( ! hash_equals( $mong, trim( (string) $khoa ) ) ) { return null; }
		return array( 'ngay' => $ngay, 'ma_nv' => $ma, 'hau_to' => $ht );
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

	/* ====================================================================== lấy dữ liệu tuần */

	/** Cột của tờ .xlsx. Đổi thứ tự ở đây là đổi cả lúc xuất lẫn lúc đọc — một nguồn duy nhất. */
	const COT = array( 'Ngày', 'Thứ', 'Mã NV', 'Họ tên', 'Giờ vào', 'Giờ ra', 'Số giờ',
		'Lý do sửa', 'KHOÁ — ĐỪNG SỬA' );

	const C_NGAY = 0;
	const C_THU  = 1;
	const C_MA   = 2;
	const C_TEN  = 3;
	const C_VAO  = 4;
	const C_RA   = 5;
	const C_GIO  = 6;
	const C_LYDO = 7;
	const C_KHOA = 8;

	private static function ten_thu( $ngay ) {
		$t = (int) gmdate( 'N', strtotime( $ngay . ' 00:00:00 UTC' ) );
		$b = array( 1 => 'T2', 2 => 'T3', 3 => 'T4', 4 => 'T5', 5 => 'T6', 6 => 'T7', 7 => 'CN' );
		return isset( $b[ $t ] ) ? $b[ $t ] : '';
	}

	/**
	 * Mọi dòng của tuần: MỌI NGƯỜI × BẢY NGÀY, kể cả ngày không có lượt chấm nào.
	 *
	 * 🔴 BÀY CẢ Ô TRỐNG, ĐÓ LÀ CHỦ Ý. Tờ chỉ có những ngày đã chấm thì cửa hàng trưởng không có
	 *    chỗ nào để điền ngày người ta quên bấm — mà đó chính là lý do quy trình này tồn tại.
	 *    Ô trống điền vào thì lúc duyệt đi đường `VHCC_Bu::ghi()` (bù), ô có sẵn thì đi đường
	 *    `VHCC_Bu::sua()`. Hai đường khác nhau, và khác đúng chỗ cần khác.
	 *
	 * ⚠️ Người đã nghỉ việc VẪN có mặt nếu tuần ấy họ còn chấm. Bỏ họ ra là tuần cuối của người
	 *    nghỉ việc không ai sửa được, mà đó lại là tuần hay sai nhất.
	 */
	public static function hang_tuan( $coso, $tu_ngay ) {
		global $wpdb;
		$cs = VHCC_NhanSu::chuan_coso( $coso );
		$t2 = self::ngay( $tu_ngay );
		if ( '' === $cs || '' === $t2 ) { return array(); }
		$cn = self::chu_nhat( $t2 );

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
			foreach ( self::bay_ngay( $t2 ) as $ng ) {
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
		if ( '' === $cs || '' === $t2 ) { return 'Thiếu cơ sở hoặc tuần.'; }
		if ( $t2 !== self::thu_hai( $t2 ) ) { return 'Tuần phải bắt đầu từ thứ Hai.'; }
		if ( ! VHCC_Vai::duoc( $u, self::QUYEN_TAI ) ) {
			return VHCC_Vai::loi( $u, self::QUYEN_TAI, 'Tải bảng công tuần' );
		}
		if ( ! VHCC_NhanSu::co_quyen_coso( $u, $cs ) ) { return 'Không có quyền cơ sở này.'; }

		/* 🔴 KHÔNG TẢI TUẦN ĐANG CHẠY. Anh Thắng: *"sau hết 1 tuần, bắt đầu tuần mới"*. Tuần chưa
		   xong thì người ta còn đang chấm, và sửa một tuần đang chạy là sửa thứ chốc nữa lại đổi. */
		if ( $t2 >= self::thu_hai( (string) current_time( 'Y-m-d' ) ) ) {
			return 'Tuần này chưa kết thúc. Sang tuần mới rồi mới tải được bảng công của nó.';
		}

		/* Tuần đã khoá: chỉ Admin. Anh Thắng: *"admin có quyền tải nếu khóa"*. */
		if ( self::khoa_roi( $cs, $t2 ) && ! VHCC_Vai::duoc( $u, self::QUYEN_KHOA ) ) {
			return 'Tuần ' . self::ten_tuan( $t2 ) . ' đã được kế toán duyệt và khoá — không sửa nữa. '
				. 'Thấy còn sai thì báo kế toán.';
		}
		return '';
	}

	/** Nội dung tệp .xlsx của một tuần. `error` khi không dựng được. */
	public static function xuat( $u, $coso, $tu_ngay ) {
		$chan = self::vi_sao_khong_tai( $u, $coso, $tu_ngay );
		if ( '' !== $chan ) { return array( 'ok' => false, 'error' => $chan ); }
		if ( ! VHCC_Xuat::co_xlsx() ) {
			return array( 'ok' => false,
				'error' => 'Máy chủ chưa bật php-zip nên chưa dựng được .xlsx. Nhờ bên hosting bật giúp.' );
		}

		$cs = VHCC_NhanSu::chuan_coso( $coso );
		$t2 = self::ngay( $tu_ngay );
		$ds = self::hang_tuan( $cs, $t2 );
		if ( ! $ds ) {
			return array( 'ok' => false,
				'error' => 'Cơ sở ' . $cs . ' chưa có người nào trong tuần ' . self::ten_tuan( $t2 ) . '.' );
		}

		$hang = array( self::COT );
		foreach ( $ds as $r ) {
			$hang[] = array(
				VHCC_Xuat::chu( $r['ngay'] ),
				VHCC_Xuat::chu( $r['thu'] ),
				/* 🔴 Mã NV LUÔN là chữ. `0029` để Excel tự đoán là thành số 29 — xem `VHCC_Xuat::o()`. */
				VHCC_Xuat::chu( $r['ma_nv'] . ( '' !== $r['hau_to'] ? ( '-' . $r['hau_to'] ) : '' ) ),
				VHCC_Xuat::chu( $r['ho_ten'] ),
				VHCC_Xuat::chu( $r['vao'] ),
				VHCC_Xuat::chu( $r['ra'] ),
				( null === $r['gio'] ? '' : (float) $r['gio'] ),
				'',
				VHCC_Xuat::chu( $r['khoa'] ),
			);
		}

		$noi = VHCC_Xuat::xlsx( array( array(
			'ten'  => 'Tuan',
			'hang' => $hang,
			'cot'  => array( 12, 6, 18, 26, 10, 10, 9, 34, 30 ),
		) ) );
		if ( null === $noi ) {
			return array( 'ok' => false, 'error' => 'Không dựng được tệp .xlsx trên máy chủ này.' );
		}
		return array( 'ok' => true, 'ten' => self::ten_tep( $cs, $t2 ), 'noi_dung' => $noi,
			'soDong' => count( $ds ) );
	}

	public static function ten_tep( $coso, $tu_ngay ) {
		$cs = preg_replace( '/[^A-Za-z0-9_-]+/', '', (string) $coso );
		return 'bang-cong-' . ( '' !== $cs ? $cs : 'coso' ) . '-tuan-' . self::ngay( $tu_ngay ) . '.xlsx';
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
		$t2 = self::ngay( $tu_ngay );

		if ( self::khoa_roi( $cs, $t2 ) ) {
			return array( 'ok' => false, 'error' => 'Tuần này đã khoá — không nhận tệp nữa.' );
		}

		$doc = VHCC_DocXlsx::doc( $duong_tep );
		if ( empty( $doc['ok'] ) ) { return array( 'ok' => false, 'error' => $doc['error'] ); }

		$kq = self::doi( $cs, $t2, $doc['hang'] );
		if ( empty( $kq['ok'] ) ) { return $kq; }
		if ( ! $kq['doi'] ) {
			return array( 'ok' => false,
				'error' => 'Tệp này không khác bảng công đang có ô nào — chưa có gì để kế toán duyệt.' );
		}

		global $wpdb;
		/* Gửi lượt mới thì lượt chờ cũ thành "không duyệt" — hai đơn cùng chờ cho một tuần là
		   kế toán duyệt nhầm cái cũ. Anh Thắng: *"nếu sai, kế toán sẽ báo cht gửi lại file khác"*. */
		$wpdb->update( VHCC_DB::t( 'don_tuan' ),
			array( 'trang_thai' => self::TU_CHOI,
				'ly_do_choi' => 'Cửa hàng trưởng gửi tệp mới thay cho lượt này.',
				'duyet_luc' => current_time( 'mysql' ) ),
			array( 'coso' => $cs, 'tu_ngay' => $t2, 'trang_thai' => self::CHO ) );

		$wpdb->insert( VHCC_DB::t( 'don_tuan' ), array(
			'coso'       => $cs,
			'tu_ngay'    => $t2,
			'den_ngay'   => self::chu_nhat( $t2 ),
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
			'soDong' => (int) $kq['soDong'], 'doi' => $kq['doi'] );
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
		$t2 = self::ngay( $tu_ngay );

		$hang = array_values( (array) $hang );
		if ( count( $hang ) < 2 ) {
			return array( 'ok' => false, 'error' => 'Tệp không có dòng dữ liệu nào.' );
		}
		$dau = array_shift( $hang );
		if ( ! is_array( $dau ) || ! isset( $dau[ self::C_KHOA ] )
			|| false === mb_strpos( (string) $dau[ self::C_KHOA ], 'KHOÁ' ) ) {
			return array( 'ok' => false, 'error' => 'Tệp này không đúng mẫu bảng công tuần '
				. '(thiếu cột KHOÁ ở cuối). Tải lại tệp mẫu rồi sửa trên đó, đừng dựng tệp mới.' );
		}

		/* Bảng công đang có, tra theo chính cái khoá in trong tệp. */
		$dang = array();
		foreach ( self::hang_tuan( $cs, $t2 ) as $r ) { $dang[ $r['khoa'] ] = $r; }

		$doi = array();
		$so  = 0;
		foreach ( $hang as $i => $d ) {
			if ( ! is_array( $d ) ) { continue; }
			$khoa = isset( $d[ self::C_KHOA ] ) ? trim( (string) $d[ self::C_KHOA ] ) : '';
			if ( '' === $khoa ) { continue; }          // dòng người ta chèn thêm — bỏ, không đoán
			$so++;

			$k = self::doc_khoa( $khoa, $cs, $t2 );
			if ( null === $k ) {
				return array( 'ok' => false, 'error' => 'Dòng ' . ( $i + 2 ) . ' có cột KHOÁ sai. '
					. 'Thường là do dán từ tệp tuần khác sang, hoặc sửa tay vào cột ấy. '
					. 'Tải lại tệp của đúng tuần ' . self::ten_tuan( $t2 ) . ' rồi sửa trên đó.' );
			}
			if ( ! isset( $dang[ $khoa ] ) ) {
				return array( 'ok' => false, 'error' => 'Dòng ' . ( $i + 2 )
					. ' không còn khớp với bảng công (người này có thể đã đổi cơ sở). Tải lại tệp mới.' );
			}
			$cu = $dang[ $khoa ];

			$vao = self::doc_gio( isset( $d[ self::C_VAO ] ) ? $d[ self::C_VAO ] : '' );
			$ra  = self::doc_gio( isset( $d[ self::C_RA ] ) ? $d[ self::C_RA ] : '' );
			if ( null === $vao || null === $ra ) {
				return array( 'ok' => false, 'error' => 'Dòng ' . ( $i + 2 ) . ' (' . $cu['ho_ten']
					. ' ngày ' . $cu['ngay'] . ') có ô giờ không đọc được. Gõ kiểu 24 giờ: 08:00, 17:30.' );
			}
			if ( $vao === $cu['vao'] && $ra === $cu['ra'] ) { continue; }

			$ly_do = trim( (string) ( isset( $d[ self::C_LYDO ] ) ? $d[ self::C_LYDO ] : '' ) );
			if ( mb_strlen( $ly_do, 'UTF-8' ) < 5 ) {
				return array( 'ok' => false, 'error' => 'Dòng ' . ( $i + 2 ) . ' (' . $cu['ho_ten']
					. ' ngày ' . $cu['ngay'] . ') có sửa giờ nhưng chưa ghi Lý do sửa. '
					. 'Mỗi ô giờ sửa đều phải nói vì sao, ít nhất 5 chữ.' );
			}
			if ( '' !== $vao && '' !== $ra && $ra <= $vao ) {
				return array( 'ok' => false, 'error' => 'Dòng ' . ( $i + 2 ) . ' (' . $cu['ho_ten']
					. ' ngày ' . $cu['ngay'] . ') có giờ ra không sau giờ vào.' );
			}

			$doi[] = array(
				'khoa'   => $khoa,
				'ngay'   => $cu['ngay'],
				'maNV'   => $cu['ma_nv'],
				'hauTo'  => $cu['hau_to'],
				'hoTen'  => $cu['ho_ten'],
				'vaoCu'  => $cu['vao'],
				'raCu'   => $cu['ra'],
				'vao'    => $vao,
				'ra'     => $ra,
				'lyDo'   => mb_substr( $ly_do, 0, 200 ),
				'them'   => ( '' === $cu['vao'] && '' === $cu['ra'] ),
			);
		}

		if ( ! $so ) {
			return array( 'ok' => false, 'error' => 'Tệp không có dòng nào mang cột KHOÁ.' );
		}
		return array( 'ok' => true, 'doi' => $doi, 'soDong' => $so );
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
		$chu = 'Cửa hàng ' . $cs . ' gửi đơn chỉnh bảng công tuần ' . self::ten_tuan( $t2 )
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
	 * Anh Thắng: *"chỉ duyệt và gửi 1 lần) nên cần đảm bảo chính xác"*. Duyệt xong là tuần khoá:
	 * không tải lại, không nạp lại, không duyệt lần hai. Nên ở đây chốt kỹ hai chỗ:
	 *   · đơn phải còn ở trạng thái CHỜ — bấm hai lần trên hai tab không ghi hai lượt;
	 *   · tuần chưa có đơn nào đã duyệt — hai đơn của cùng một tuần thì chỉ một cái ăn.
	 *
	 * ⚠️ TUẦN VẪN KHOÁ DÙ CÓ Ô TRƯỢT. Mỗi ô đi qua `VHCC_Bu` với đủ gác cũ, nên vài ô có thể bị
	 *    chối (giờ của chính kế toán, người đã đổi cơ sở…). Không khoá vì vài ô trượt thì cửa
	 *    hàng trưởng phải gửi lại cả tuần cho mấy ô mà kế toán đã cố ý chấp nhận. Ô nào trượt
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
			return array( 'ok' => false, 'error' => 'Tuần ' . self::ten_tuan( $don['tu_ngay'] )
				. ' của cơ sở này đã có một đơn được duyệt rồi.' );
		}

		$xong  = 0;
		$truot = array();
		foreach ( self::doi_cua( $don ) as $o ) {
			$dat = array(
				'coso'   => (string) $don['coso'],
				'ngay'   => (string) $o['ngay'],
				'ma_nv'  => (string) $o['maNV'] . ( '' !== (string) $o['hauTo'] ? ( '-' . $o['hauTo'] ) : '' ),
				'vao'    => (string) $o['vao'],
				'ra'     => (string) $o['ra'],
				'ly_do'  => 'Đơn tuần #' . (int) $don['id'] . ': ' . (string) $o['lyDo'],
			);
			/* Ô trống từ đầu thì là BÙ (điền vào chỗ chưa có), ô đã có giờ thì là SỬA ĐÈ. Hai
			   đường gác khác nhau và ghi nhật ký khác nhau — xem `VHCC_Bu`. */
			$r = ! empty( $o['them'] ) ? VHCC_Bu::ghi( $u, $dat ) : VHCC_Bu::sua( $u, $dat );
			if ( ! empty( $r['ok'] ) ) {
				$xong++;
			} else {
				$truot[] = array( 'ngay' => $o['ngay'], 'maNV' => $o['maNV'],
					'hoTen' => $o['hoTen'], 'error' => isset( $r['error'] ) ? $r['error'] : 'Không ghi được.' );
			}
		}

		$wpdb->update( VHCC_DB::t( 'don_tuan' ),
			array_merge( $ai, array( 'trang_thai' => self::DUYET,
				'ket_qua' => wp_json_encode( array( 'xong' => $xong, 'truot' => $truot ) ) ) ),
			array( 'id' => (int) $don['id'] ) );

		self::bao_gui( $don, true, '', $u, $xong, count( $truot ) );

		return array( 'ok' => true, 'quyet' => self::DUYET, 'xong' => $xong,
			'truot' => $truot, 'khoa' => self::ten_tuan( $don['tu_ngay'] ) );
	}

	private static function bao_gui( $don, $dong_y, $ly_do, $u, $xong = 0, $so_truot = 0 ) {
		if ( ! class_exists( 'VHCC_Chuong' ) || ! method_exists( 'VHCC_Chuong', 'bao' ) ) { return; }
		$ma = trim( (string) $don['ma_nv_gui'] );
		if ( '' === $ma ) { return; }
		$chu = $dong_y
			? ( 'Đơn chỉnh bảng công tuần ' . self::ten_tuan( $don['tu_ngay'] ) . ' đã được duyệt — '
				. (int) $xong . ' ô đã lên bảng công'
				. ( $so_truot > 0 ? ( ', ' . (int) $so_truot . ' ô không ghi được' ) : '' )
				. '. Tuần này đã khoá.' )
			: ( 'Đơn chỉnh bảng công tuần ' . self::ten_tuan( $don['tu_ngay'] )
				. ' không được duyệt. Lý do: ' . $ly_do . ' Sửa lại rồi gửi tệp khác.' );
		VHCC_Chuong::bao( $ma, $chu, 'cc_dontuan:' . (int) $don['id'],
			isset( $u['ma_nv'] ) ? (string) $u['ma_nv'] : '' );
	}
}
