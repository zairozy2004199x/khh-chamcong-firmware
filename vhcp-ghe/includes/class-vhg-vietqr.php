<?php
/**
 * KHO SỐ VIETQR THỰC CỦA GHẾ — bảng vhg_bc_vqr: (ngày × cơ sở × mã ghế) → tiền VietQR thật về ngân hàng.
 *
 * 🔴 VÌ SAO CÓ KHO (anh Thắng 25/09/2026): *"khi có dữ liệu thêm thì ghi vào máy, để cần đọc ngay, chứ sao
 *    kê nó đang quá tải mà cứ gọi qua là lúc được lúc không"* và *"lúc bấm xem, là nó tự đẩy đọc và nạp vào
 *    trang ghế luôn"*. Trước 2.142.0 Báo cáo tổng gọi sang SAOKE_App::vietqr_theo_coso_ngay() tính lại từ
 *    đầu MỖI LẦN bấm Xem — chọn 01→25/09 là "không nối được tới máy chủ" (PHP quá 30s bị ngắt). Nay số
 *    nằm sẵn ở đây:
 *      · Sao Kê ĐẨY sang: webhook về → cong_gd() (một câu UPSERT); gán máy tay / nạp file / đổi bản đồ cửa
 *        hàng / đổi ánh xạ → nhan_ngay() ghi đè trọn ngày bị đụng.
 *      · Bấm Xem: ngày nào trong khoảng chưa có trong kho thì tự kéo về (≤ 3 ngày kéo ngay trong lượt, nhiều
 *        hơn thì màn hình kéo từng đợt ~8s qua kt_vqr_dongbo) rồi mới vẽ bảng. Hôm nay / hôm qua kéo lại nếu
 *        kho cũ quá 10 phút — kẻo một webhook lỡ mà không ai biết.
 *
 * 🔴 KHO LÀ SỐ SUY RA — nguồn thật vẫn là wp_saoke_cong bên Sao Kê. Ghi đè / xoá kho không mất một đồng:
 *    kéo lại là ra. Vì thế luật "không xoá dòng tiền" (bc, bc_dong, thu, chot, nop) KHÔNG áp cho bảng này,
 *    và gộp sổ cơ sở chỉ cần quên các ngày mang tên cũ (quen_coso) để lượt Xem kế tiếp kéo lại dưới tên đích.
 *
 * BA RỔ, khớp y nguyên SAOKE_App::vietqr_theo_may_ngay() — không đồng nào rơi:
 *      coso_key=X, ma_may=M   → tiền quy được tới GHẾ M của cơ sở X
 *      coso_key=X, ma_may=''  → tiền quy được cơ sở X mà chưa rõ máy
 *      coso_key='', ma_may='' → tiền không quy được cơ sở nào (khongKhop). Dòng này LUÔN có khi ngày đã đồng
 *                               bộ (kể cả = 0): nó là DẤU "ngày này đã có trong kho" và mang cap_luc của ngày.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHG_VietQR {
	const NGAN_SACH_GIAY = 8;     // một lượt dong_bo() làm chừng này giây rồi trả 'tiep' cho màn hình gọi tiếp
	const TU_KEO_TOI_DA  = 3;     // bấm Xem: thiếu ≤ 3 ngày thì kéo ngay trong lượt; nhiều hơn → kéo từng đợt
	const CU_SAU_GIAY    = 600;   // hôm nay / hôm qua: kho cũ hơn 10 phút thì kéo lại lúc Xem
	const KHOANG_TOI_DA  = 400;   // ds_ngay() không nở quá chừng này ngày

	private static function t() { return VHG_DB::t( 'bc_vqr' ); }
	private static function key_( $coso ) { $coso = trim( (string) $coso ); return '' === $coso ? '' : (string) VHG_BaoCao::squash( $coso ); }
	public static function ngay_( $s ) { return preg_match( '/^(\d{4}-\d{2}-\d{2})/', trim( (string) $s ), $m ) ? $m[1] : ''; }
	public static function saoke_co() { return class_exists( 'SAOKE_App' ) && method_exists( 'SAOKE_App', 'vietqr_theo_may_ngay' ); }

	/** Dãy 'Y-m-d' từ $tu tới $den (đảo nếu ngược), tính bằng UTC để không lệch múi giờ. */
	public static function ds_ngay( $tu, $den ) {
		$tu = self::ngay_( $tu ); $den = self::ngay_( $den ); $out = array();
		if ( '' === $tu || '' === $den ) { return $out; }
		if ( $tu > $den ) { $x = $tu; $tu = $den; $den = $x; }
		$a = strtotime( $tu . ' UTC' ); $b = strtotime( $den . ' UTC' );
		for ( $t = $a; $t <= $b && count( $out ) < self::KHOANG_TOI_DA; $t += 86400 ) { $out[] = gmdate( 'Y-m-d', $t ); }
		return $out;
	}

	/* ══════════════ THUẦN — bốc ra chạy thử được, không đụng DB ══════════════ */

	/** Dòng cần ghi cho MỘT ngày từ kết quả SAOKE_App::vietqr_theo_may_ngay( $ngay, $ngay ). Luôn kèm dấu ngày. */
	public static function dong_tu_kq( $ngay, $kq ) {
		$out = array(); $khong = (int) ( isset( $kq['khongKhop'] ) ? $kq['khongKhop'] : 0 );
		foreach ( (array) ( isset( $kq['vq'] ) ? $kq['vq'] : array() ) as $cs => $theoMay ) {
			foreach ( (array) $theoMay as $ma => $theoNgay ) {
				foreach ( (array) $theoNgay as $ng => $tien ) {
					if ( (string) $ng !== (string) $ngay ) { continue; }   // chỉ ngày này — kết quả rộng hơn thì phần ngoài không thuộc dòng ngày
					$out[] = array( 'coso' => (string) $cs, 'ma' => (string) $ma, 'tien' => (int) $tien );
				}
			}
		}
		foreach ( (array) ( isset( $kq['chuaMay'] ) ? $kq['chuaMay'] : array() ) as $cs => $theoNgay ) {
			foreach ( (array) $theoNgay as $ng => $tien ) {
				if ( (string) $ng !== (string) $ngay ) { continue; }
				$out[] = array( 'coso' => (string) $cs, 'ma' => '', 'tien' => (int) $tien );
			}
		}
		$out[] = array( 'coso' => '', 'ma' => '', 'tien' => $khong );   // dấu "ngày đã đồng bộ" + tiền không khớp
		return $out;
	}
	/** Gộp dòng theo (khoá cơ sở, mã ghế) — hai cách gõ một tên ra cùng khoá thì CỘNG, không đè. */
	public static function gop_dong( $ds ) {
		$gop = array();
		foreach ( (array) $ds as $d ) {
			$key = self::key_( $d['coso'] ); $ma = ( '' === $key ) ? '' : (string) $d['ma'];
			$k = $key . '|' . $ma;
			if ( ! isset( $gop[ $k ] ) ) { $gop[ $k ] = array( 'coso' => ( '' === $key ) ? '' : trim( (string) $d['coso'] ), 'coso_key' => $key, 'ma_may' => $ma, 'so_tien' => 0 ); }
			$gop[ $k ]['so_tien'] += (int) $d['tien'];
		}
		return array_values( $gop );
	}
	/** Kho → theo CƠ SỞ × NGÀY (dạng vietqr_theo_coso_ngay). $ten = [ coso_key => tên đang dùng ] để dòng cũ mang tên mới. */
	public static function gom_coso( $rows, $ten = array() ) {
		$vq = array(); $khong = 0; $ngayCo = array();
		foreach ( (array) $rows as $r ) {
			$ng = (string) $r['ngay']; $key = (string) $r['coso_key']; $tien = (int) $r['so_tien'];
			if ( '' === $key ) { if ( '' === (string) $r['ma_may'] ) { $ngayCo[ $ng ] = 1; } $khong += $tien; continue; }
			$cs = isset( $ten[ $key ] ) ? $ten[ $key ] : (string) $r['coso'];
			if ( ! isset( $vq[ $cs ] ) ) { $vq[ $cs ] = array(); }
			$vq[ $cs ][ $ng ] = ( isset( $vq[ $cs ][ $ng ] ) ? $vq[ $cs ][ $ng ] : 0 ) + $tien;
		}
		ksort( $ngayCo );
		return array( 'vq' => $vq, 'khongKhop' => $khong, 'ngayCo' => array_keys( $ngayCo ) );
	}
	/** Kho → theo CƠ SỞ × MÁY × NGÀY + "chưa rõ máy" (dạng vietqr_theo_may_ngay). */
	public static function gom_may( $rows, $ten = array() ) {
		$vq = array(); $chua = array(); $khong = 0; $ngayCo = array();
		foreach ( (array) $rows as $r ) {
			$ng = (string) $r['ngay']; $key = (string) $r['coso_key']; $ma = (string) $r['ma_may']; $tien = (int) $r['so_tien'];
			if ( '' === $key ) { if ( '' === $ma ) { $ngayCo[ $ng ] = 1; } $khong += $tien; continue; }
			$cs = isset( $ten[ $key ] ) ? $ten[ $key ] : (string) $r['coso'];
			if ( '' === $ma ) {
				if ( ! isset( $chua[ $cs ] ) ) { $chua[ $cs ] = array(); }
				$chua[ $cs ][ $ng ] = ( isset( $chua[ $cs ][ $ng ] ) ? $chua[ $cs ][ $ng ] : 0 ) + $tien;
				continue;
			}
			if ( ! isset( $vq[ $cs ] ) ) { $vq[ $cs ] = array(); }
			if ( ! isset( $vq[ $cs ][ $ma ] ) ) { $vq[ $cs ][ $ma ] = array(); }
			$vq[ $cs ][ $ma ][ $ng ] = ( isset( $vq[ $cs ][ $ma ][ $ng ] ) ? $vq[ $cs ][ $ma ][ $ng ] : 0 ) + $tien;
		}
		ksort( $ngayCo );
		return array( 'vq' => $vq, 'chuaMay' => $chua, 'khongKhop' => $khong, 'ngayCo' => array_keys( $ngayCo ) );
	}
	/** Ngày trong dãy mà kho chưa có dấu. */
	public static function ngay_thieu_tu( $ds_ngay, $ngay_co ) {
		$co = array_fill_keys( (array) $ngay_co, 1 ); $out = array();
		foreach ( (array) $ds_ngay as $n ) { if ( ! isset( $co[ $n ] ) ) { $out[] = $n; } }
		return $out;
	}
	/**
	 * Bấm Xem → ngày nào kéo NGAY trong lượt: hôm nay & hôm qua nếu kho cũ quá CU_SAU_GIAY (webhook có thể lỡ),
	 * cộng ngày thiếu nếu ≤ TU_KEO_TOI_DA. Thiếu nhiều hơn thì để màn hình kéo từng đợt — không ôm 25 ngày trong
	 * một lượt rồi lại quá giờ. $cap_luc = [ ngày => 'Y-m-d H:i:s' ] (dấu ngày); $bay_gio cùng múi giờ với nó.
	 */
	public static function chon_ngay_keo( $ds_ngay, $thieu, $hom_nay, $cap_luc, $bay_gio ) {
		$homQua = gmdate( 'Y-m-d', strtotime( $hom_nay . ' UTC' ) - 86400 );
		$moc = strtotime( $bay_gio . ' UTC' ); $chon = array();
		foreach ( (array) $ds_ngay as $ng ) {
			if ( $ng !== $hom_nay && $ng !== $homQua ) { continue; }
			if ( in_array( $ng, (array) $thieu, true ) ) { continue; }   // thiếu thì nhánh dưới xét
			$cl = isset( $cap_luc[ $ng ] ) ? strtotime( $cap_luc[ $ng ] . ' UTC' ) : 0;
			if ( $moc - $cl >= self::CU_SAU_GIAY ) { $chon[] = $ng; }
		}
		if ( count( (array) $thieu ) <= self::TU_KEO_TOI_DA ) { foreach ( (array) $thieu as $ng ) { $chon[] = $ng; } }
		return array_values( array_unique( $chon ) );
	}
	/** 'Y-m-d H:i:s' → 'H:i d/m/Y' để in; không đổi múi giờ (đã là giờ WP). */
	public static function in_luc( $mysql ) {
		$s = (string) $mysql;
		return strlen( $s ) >= 16 ? ( substr( $s, 11, 5 ) . ' ' . substr( $s, 8, 2 ) . '/' . substr( $s, 5, 2 ) . '/' . substr( $s, 0, 4 ) ) : $s;
	}

	/* ══════════════ ĐỌC KHO ══════════════ */

	private static function doc_( $tu, $den ) {
		global $wpdb;
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT ngay, coso, coso_key, ma_may, so_tien, cap_luc FROM ' . self::t() . ' WHERE ngay BETWEEN %s AND %s', $tu, $den ), ARRAY_A );
	}
	private static function ten_theo_key_() {
		$m = array();
		foreach ( (array) VHG_May::ds_coso() as $c ) { $k = self::key_( $c['ten'] ); if ( '' !== $k && ! isset( $m[ $k ] ) ) { $m[ $k ] = (string) $c['ten']; } }
		return $m;
	}
	private static function cap_luc_theo_ngay_( $rows ) {
		$m = array();
		foreach ( (array) $rows as $r ) { if ( '' === (string) $r['coso_key'] && '' === (string) $r['ma_may'] ) { $m[ (string) $r['ngay'] ] = (string) $r['cap_luc']; } }
		return $m;
	}
	private static function cap_luc_moi_nhat_( $rows ) {
		$max = '';
		foreach ( (array) $rows as $r ) { if ( (string) $r['cap_luc'] > $max ) { $max = (string) $r['cap_luc']; } }
		return '' === $max ? '' : self::in_luc( $max );
	}
	/** Ngày trong khoảng chưa có trong kho. */
	public static function ngay_thieu( $tu, $den ) {
		global $wpdb;
		$ds = self::ds_ngay( $tu, $den ); if ( ! $ds ) { return array(); }
		$co = (array) $wpdb->get_col( $wpdb->prepare( 'SELECT ngay FROM ' . self::t() . " WHERE ngay BETWEEN %s AND %s AND coso_key='' AND ma_may=''", $ds[0], $ds[ count( $ds ) - 1 ] ) );
		return self::ngay_thieu_tu( $ds, $co );
	}
	private static function ngay_da_co_( $ngay ) {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::t() . " WHERE ngay=%s AND coso_key='' AND ma_may='' LIMIT 1", $ngay ) );
	}
	/** VietQR thực theo CƠ SỞ × NGÀY cho Báo cáo tổng / MISA. $tu_dong = kéo ngay ngày đáng kéo trước khi đọc. */
	public static function theo_coso_ngay( $tu, $den, $tu_dong = true ) {
		$rong = array( 'co' => false, 'vq' => array(), 'khongKhop' => 0, 'thieuNgay' => array(), 'capLuc' => '', 'saoKe' => self::saoke_co() ? 1 : 0 );
		$tu = self::ngay_( $tu ); $den = self::ngay_( $den ); if ( '' === $tu || '' === $den ) { return $rong; }
		if ( $tu > $den ) { $x = $tu; $tu = $den; $den = $x; }
		$thieu = $tu_dong ? self::tu_dong_bo_( $tu, $den ) : self::ngay_thieu( $tu, $den );
		$rows = self::doc_( $tu, $den );
		$g = self::gom_coso( $rows, self::ten_theo_key_() );
		return array( 'co' => count( $g['ngayCo'] ) > 0, 'vq' => $g['vq'], 'khongKhop' => $g['khongKhop'],
			'thieuNgay' => $thieu, 'capLuc' => self::cap_luc_moi_nhat_( $rows ), 'saoKe' => $rong['saoKe'] );
	}
	/** VietQR thực theo CƠ SỞ × MÁY × NGÀY (đối chiếu từng ghế). */
	public static function theo_may_ngay( $tu, $den, $tu_dong = true ) {
		$rong = array( 'co' => false, 'vq' => array(), 'chuaMay' => array(), 'khongKhop' => 0, 'thieuNgay' => array(), 'capLuc' => '', 'saoKe' => self::saoke_co() ? 1 : 0 );
		$tu = self::ngay_( $tu ); $den = self::ngay_( $den ); if ( '' === $tu || '' === $den ) { return $rong; }
		if ( $tu > $den ) { $x = $tu; $tu = $den; $den = $x; }
		$thieu = $tu_dong ? self::tu_dong_bo_( $tu, $den ) : self::ngay_thieu( $tu, $den );
		$rows = self::doc_( $tu, $den );
		$g = self::gom_may( $rows, self::ten_theo_key_() );
		return array( 'co' => count( $g['ngayCo'] ) > 0, 'vq' => $g['vq'], 'chuaMay' => $g['chuaMay'], 'khongKhop' => $g['khongKhop'],
			'thieuNgay' => $thieu, 'capLuc' => self::cap_luc_moi_nhat_( $rows ), 'saoKe' => $rong['saoKe'] );
	}

	/* ══════════════ GHI KHO ══════════════ */

	/** Ghi đè trọn MỘT ngày từ kết quả SAOKE_App::vietqr_theo_may_ngay( $ngay, $ngay ). Sao Kê gọi sang; dong_bo() cũng dùng. */
	public static function nhan_ngay( $ngay, $kq ) {
		global $wpdb;
		$ngay = self::ngay_( $ngay ); if ( '' === $ngay ) { return array( 'ok' => false, 'error' => 'Ngày sai.' ); }
		if ( ! is_array( $kq ) || empty( $kq['co'] ) ) { return array( 'ok' => false, 'error' => 'Sao Kê không trả dữ liệu.' ); }
		$t = self::t(); $luc = current_time( 'mysql' );
		$ds = self::gop_dong( self::dong_tu_kq( $ngay, $kq ) );
		$wpdb->query( 'START TRANSACTION' );
		$wpdb->delete( $t, array( 'ngay' => $ngay ) );
		foreach ( $ds as $g ) {
			$wpdb->insert( $t, array( 'ngay' => $ngay, 'coso' => mb_substr( $g['coso'], 0, 190 ), 'coso_key' => mb_substr( $g['coso_key'], 0, 150 ),
				'ma_may' => mb_substr( $g['ma_may'], 0, 40 ), 'so_tien' => (int) $g['so_tien'], 'cap_luc' => $luc ) );
		}
		$wpdb->query( 'COMMIT' );
		return array( 'ok' => true, 'ngay' => $ngay, 'dong' => count( $ds ) );
	}
	/** MỘT giao dịch mới (webhook) → cộng thẳng vào kho. Ngày chưa có trong kho → kéo trọn ngày thay vì cộng lẻ. */
	public static function cong_gd( $ngay, $coso, $ma, $tien ) {
		global $wpdb;
		$ngay = self::ngay_( $ngay ); $tien = (int) $tien;
		if ( '' === $ngay || $tien <= 0 ) { return false; }
		/* Cộng lẻ lên một ngày TRỐNG là kho chỉ giữ phần từ lúc cài trở đi mà lại mang dấu "đã đồng bộ" —
		   giao dịch này đã nằm trong saoke_cong rồi nên kéo trọn ngày là có nó. */
		if ( ! self::ngay_da_co_( $ngay ) ) { return self::keo_ngay_( $ngay ); }
		$key = self::key_( $coso ); $ma = ( '' === $key ) ? '' : (string) $ma; $coso = ( '' === $key ) ? '' : trim( (string) $coso );
		$t = self::t();
		$wpdb->query( $wpdb->prepare( "INSERT INTO $t (ngay, coso, coso_key, ma_may, so_tien, cap_luc) VALUES (%s,%s,%s,%s,%d,%s)"
			. ' ON DUPLICATE KEY UPDATE so_tien = so_tien + VALUES(so_tien), coso = VALUES(coso), cap_luc = VALUES(cap_luc)',
			$ngay, mb_substr( $coso, 0, 190 ), mb_substr( $key, 0, 150 ), mb_substr( $ma, 0, 40 ), $tien, current_time( 'mysql' ) ) );
		/* dấu ngày cũng mang cap_luc mới — "cập nhật lúc" trên màn hình là lúc kho đổi lần cuối */
		$wpdb->query( $wpdb->prepare( "UPDATE $t SET cap_luc=%s WHERE ngay=%s AND coso_key='' AND ma_may=''", current_time( 'mysql' ), $ngay ) );
		return true;
	}
	private static function keo_ngay_( $ngay ) {
		if ( ! self::saoke_co() ) { return false; }
		$r = self::nhan_ngay( $ngay, SAOKE_App::vietqr_theo_may_ngay( $ngay, $ngay ) );
		return ! empty( $r['ok'] );
	}
	/* Bấm Xem → kéo ngay những ngày đáng kéo (xem chon_ngay_keo). Trả: ngày CÒN thiếu sau đó. */
	private static function tu_dong_bo_( $tu, $den ) {
		$dsNgay = self::ds_ngay( $tu, $den );
		$capLuc = self::cap_luc_theo_ngay_( self::doc_( $tu, $den ) );
		$thieu = self::ngay_thieu_tu( $dsNgay, array_keys( $capLuc ) );
		if ( ! self::saoke_co() ) { return $thieu; }
		$chon = self::chon_ngay_keo( $dsNgay, $thieu, current_time( 'Y-m-d' ), $capLuc, current_time( 'mysql' ) );
		foreach ( $chon as $ng ) { self::keo_ngay_( $ng ); }
		return count( $chon ) ? self::ngay_thieu( $tu, $den ) : $thieu;
	}
	/**
	 * Kéo từng đợt cho màn hình: làm tới hết ngân sách giây rồi trả 'tiep' = ngày kế để gọi tiếp.
	 * $chi_thieu = chỉ ngày chưa có; false = kéo lại cả ngày đã có (nút ↻ sau khi đổi bản đồ / ánh xạ).
	 */
	public static function dong_bo( $tu, $den, $chi_thieu = true ) {
		if ( ! self::saoke_co() ) { return array( 'ok' => false, 'error' => 'Chưa cài plugin Sao Kê (hoặc bản Sao Kê cũ chưa có vietqr_theo_may_ngay) — Ghế không kéo được số VietQR.' ); }
		$dsNgay = self::ds_ngay( $tu, $den );
		if ( ! $dsNgay ) { return array( 'ok' => false, 'error' => 'Thiếu khoảng ngày.' ); }
		$tu = $dsNgay[0]; $den = $dsNgay[ count( $dsNgay ) - 1 ];
		$ds = $chi_thieu ? self::ngay_thieu( $tu, $den ) : $dsNgay;
		$bat = microtime( true ); $xong = array();
		foreach ( $ds as $ng ) {
			if ( count( $xong ) && ( microtime( true ) - $bat ) > self::NGAN_SACH_GIAY ) {
				return array( 'ok' => true, 'tu' => $tu, 'den' => $den, 'xong' => $xong, 'tiep' => $ng, 'conLai' => count( $ds ) - count( $xong ) );
			}
			self::keo_ngay_( $ng ); $xong[] = $ng;
		}
		return array( 'ok' => true, 'tu' => $tu, 'den' => $den, 'xong' => $xong, 'tiep' => '', 'conLai' => 0 );
	}
	/**
	 * ĐÁNH DẤU NGÀY CẦN TÍNH LẠI — xoá trọn dòng của các ngày ấy (kể cả dấu) để lượt Xem kế tiếp tự kéo lại từ Sao Kê.
	 * 2.143.0, anh Thắng 25/09/2026 *"chậm quá"*: nạp bù 24.261 dòng, mỗi đợt Sao Kê tính lại trọn từng ngày đụng tới
	 * (một ngày ~1.000 giao dịch → hàng trăm câu ghi), rồi đợt sau cùng ngày ấy lại tính lần nữa. Nạp file không cần số
	 * ngay — chỉ cần kho BIẾT ngày ấy cũ; kéo lại khi có người xem (≤3 ngày ngay trong lượt, nhiều hơn màn hình kéo từng đợt).
	 * Webhook về ngày đã quên → cong_gd() thấy chưa có dấu → kéo trọn ngày (có cả giao dịch ấy) — vẫn đúng.
	 */
	public static function quen_ngay( $ds_ngay ) {
		global $wpdb; $t = self::t(); $n = 0; $ds = array();
		foreach ( (array) $ds_ngay as $ng ) { $ng = self::ngay_( $ng ); if ( '' !== $ng ) { $ds[ $ng ] = 1; } }
		foreach ( array_keys( $ds ) as $ng ) { $n += (int) $wpdb->query( $wpdb->prepare( "DELETE FROM $t WHERE ngay=%s", $ng ) ); }
		return $n;
	}
	/** Gộp sổ / đổi tên cơ sở: quên các NGÀY có dòng mang khoá cũ — lượt Xem kế tiếp kéo lại dưới tên đích. Trả số dòng bỏ. */
	public static function quen_coso( $coso_key ) {
		global $wpdb; $t = self::t(); $coso_key = (string) $coso_key;
		if ( '' === $coso_key ) { return 0; }
		$ngay = (array) $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT ngay FROM $t WHERE coso_key=%s", $coso_key ) );
		if ( ! $ngay ) { return 0; }
		$n = 0;
		foreach ( $ngay as $ng ) { $n += (int) $wpdb->query( $wpdb->prepare( "DELETE FROM $t WHERE ngay=%s", $ng ) ); }
		return $n;
	}
}
