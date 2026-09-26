<?php
/**
 * KHOẢN GIỮ LẠI, TRẢ SAU — %KID, Target… set hằng tháng cho nhân viên thấy, nhưng KHÔNG trả trong
 * lương tháng ấy; dồn lại, tới kỳ quản lý chọn (giữa năm, cuối năm) mới trả.
 *
 * Anh Thắng 26/09/2026: *"có 1 số cột sẽ chỉ trả vào cuối năm hoặc giữa năm, do giám đốc chọn,
 * nhưng mà vẫn set hàng tháng để nhân viên biết, nhưng không trả vào lương liền mà giữ đó, giống
 * cọc hoặc hoàn cọc … dữ liệu lương này rất quan trọng, vì giữ liệu lâu dài"*, rồi chốt: *"Tạo
 * luồng tích chọn để sau quản lý quyết định, chứ giờ nói trước cũng không chắc chắn"*.
 *
 * =================================================================================================
 * HAI CUỐN SỔ, KHÔNG ĐỤNG SỔ KHOẢN CỘNG
 * =================================================================================================
 * Số tiền của từng tháng vẫn nằm đúng chỗ cũ (`VHCC_ChotLuong` — kế toán gõ như mọi khoản cộng).
 * Lớp này chỉ thêm:
 *   1. SỔ KHOẢN GIỮ (`O_KHOAN`): khoản nào đang giữ, TỪ THÁNG NÀO tới tháng nào. Quản lý tích/bỏ
 *      tích lúc nào cũng được.
 *   2. SỔ CÁC LẦN TRẢ (`O_TRA`): trả cho ai, khoản nào, bao nhiêu, vào lương tháng nào, ai bấm, lúc
 *      nào.
 *
 * 🔴 KHOẢN GIỮ THEO THỜI HẠN, KHÔNG PHẢI MỘT CỜ BẬT/TẮT. Một cờ chung cho mọi tháng thì bỏ tích
 *    %KID vào tháng 12 là mười một tháng trước đó lặng lẽ thành "đã trả trong lương tháng" — bảng
 *    lương các tháng cũ đổi số, và số đã dồn biến mất. Nên tích là mở một khoảng TỪ tháng đang
 *    xem; bỏ tích là đóng khoảng ấy ở tháng TRƯỚC tháng đang xem. Tháng cũ giữ nguyên cách tính.
 *
 * 🔴 KHÔNG BAO GIỜ TỰ XOÁ. Không có đường nào dọn sổ theo thời gian — số dồn của năm trước vẫn
 *    phải đọc lại được khi trả. Huỷ một lần trả là việc tay, có tên người bấm.
 *
 * ⚠️ Tiền trả ra cộng vào ĐÚNG CỘT KHOẢN ẤY của tháng được chọn (trả %KID dồn 6 tháng thì cột
 *    %KID tháng ấy mang cả số dồn) — kế toán đối chiếu theo cột như file đang dùng.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_GiuLuong {

	/** { khoản => [ [ 'tu' => 'YYYY-MM', 'den' => 'YYYY-MM' | '' ], … ] } */
	const O_KHOAN = 'LUONG_KHOAN_GIU';

	/** { khoá cơ sở => { mã => [ { id, thang, khoan, tien, luc, boi }, … ] } } */
	const O_TRA = 'LUONG_GIU_TRA';

	/** Cùng bậc với chính bảng lương. */
	const QUYEN = 'luong';

	private static function khoa_cs( $coso ) {
		return VHCC_Luong::bo_chu( preg_replace( '/^CS_/i', '', trim( (string) $coso ) ) );
	}

	/** Tháng liền trước 'YYYY-MM'. */
	public static function thang_truoc( $th ) {
		$y = (int) substr( $th, 0, 4 ); $m = (int) substr( $th, 5, 2 ) - 1;
		if ( $m < 1 ) { $m = 12; $y--; }
		return sprintf( '%04d-%02d', $y, $m );
	}

	/* ============================================================== sổ khoản giữ */

	public static function so_khoan() {
		$d = VHCC_Luong::cai_dat( self::O_KHOAN, null );
		return is_array( $d ) ? $d : array();
	}

	/** Khoản `$k` có bị giữ ở tháng `$th` không. */
	public static function dang_giu( $k, $th, $so = null ) {
		$so = ( null === $so ) ? self::so_khoan() : $so;
		foreach ( ( isset( $so[ $k ] ) ? (array) $so[ $k ] : array() ) as $kh ) {
			$tu = isset( $kh['tu'] ) ? (string) $kh['tu'] : '';
			$den = isset( $kh['den'] ) ? (string) $kh['den'] : '';
			if ( '' !== $tu && $th >= $tu && ( '' === $den || $th <= $den ) ) { return true; }
		}
		return false;
	}

	/** Các khoản đang giữ ở tháng `$th`. */
	public static function khoan_giu_thang( $th, $so = null ) {
		$so = ( null === $so ) ? self::so_khoan() : $so;
		$ra = array();
		foreach ( array_keys( VHCC_ChotLuong::CONG ) as $k ) {
			if ( self::dang_giu( $k, $th, $so ) ) { $ra[] = $k; }
		}
		return $ra;
	}

	/** Có khoản nào TỪNG được giữ không (để màn biết có cần bày bảng tích luỹ). */
	public static function co_tung_giu() {
		foreach ( self::so_khoan() as $ds ) { if ( ! empty( $ds ) ) { return true; } }
		return false;
	}

	/**
	 * Tích / bỏ tích các khoản giữ, áp TỪ tháng `$th`.
	 *
	 * @param array $bat Danh sách khoản ĐƯỢC TÍCH trên màn (mọi khoản khác coi như bỏ tích).
	 */
	public static function dat_khoan( $u, $bat, $th ) {
		if ( ! VHCC_Vai::duoc( $u, self::QUYEN ) ) {
			return array( 'ok' => false, 'error' => VHCC_Vai::loi( $u, self::QUYEN, 'Chọn khoản giữ lại' ) );
		}
		$th = VHCC_Luong::tien_to_thang( $th );
		if ( '' === $th ) { return array( 'ok' => false, 'error' => 'Tháng không hợp lệ.' ); }
		$bat = array_values( array_intersect( array_keys( VHCC_ChotLuong::CONG ), (array) $bat ) );
		$so  = self::so_khoan();
		$doi = array();
		foreach ( array_keys( VHCC_ChotLuong::CONG ) as $k ) {
			$ds = isset( $so[ $k ] ) ? array_values( (array) $so[ $k ] ) : array();
			$muon = in_array( $k, $bat, true );
			$dang = self::dang_giu( $k, $th, $so );
			if ( $muon && ! $dang ) {
				/* Khoảng mở ở tháng SAU (đã tích sẵn cho tương lai) thì kéo nó về sớm hơn, không
				   đẻ hai khoảng chồng nhau. */
				$keo = false;
				foreach ( $ds as $i => $kh ) {
					if ( (string) $kh['tu'] > $th ) { $ds[ $i ]['tu'] = $th; $keo = true; break; }
				}
				if ( ! $keo ) { $ds[] = array( 'tu' => $th, 'den' => '' ); }
				$doi[] = '+' . VHCC_ChotLuong::CONG[ $k ];
			} elseif ( ! $muon && $dang ) {
				$truoc = self::thang_truoc( $th );
				foreach ( $ds as $i => $kh ) {
					$den = (string) $kh['den'];
					if ( (string) $kh['tu'] <= $th && ( '' === $den || $th <= $den ) ) {
						if ( (string) $kh['tu'] > $truoc ) { unset( $ds[ $i ] ); }   // mới mở đúng tháng này
						else { $ds[ $i ]['den'] = $truoc; }
					}
				}
				$ds = array_values( $ds );
				$doi[] = '−' . VHCC_ChotLuong::CONG[ $k ];
			}
			if ( $ds ) { $so[ $k ] = $ds; } else { unset( $so[ $k ] ); }
		}
		VHCC_Luong::dat_cai_dat( self::O_KHOAN, $so, $u );
		return array( 'ok' => true, 'doi' => $doi );
	}

	/**
	 * Mấy câu ghi chú cho một dòng lương (bảng trên màn, cột NOTES của tờ .xlsx, phiếu lương) —
	 * MỘT chỗ viết câu, ba nơi đọc cùng một chữ.
	 */
	public static function ghi_chu( $d ) {
		$ra = array();
		$vi = function ( $ds ) {
			$o = array();
			foreach ( (array) $ds as $k => $v ) {
				$o[] = ( isset( VHCC_ChotLuong::CONG[ $k ] ) ? VHCC_ChotLuong::CONG[ $k ] : $k )
					. ' ' . number_format( (float) $v, 0, ',', '.' );
			}
			return implode( ', ', $o );
		};
		if ( ! empty( $d['giu'] ) ) { $ra[] = 'giữ lại tháng này (chưa trả): ' . $vi( $d['giu'] ); }
		if ( ! empty( $d['traGiu'] ) ) { $ra[] = 'trả khoản đã giữ: ' . $vi( $d['traGiu'] ); }
		$con = array();
		foreach ( ( isset( $d['tichLuy'] ) ? (array) $d['tichLuy'] : array() ) as $k => $x ) {
			if ( (float) $x['con'] > 0 ) { $con[ $k ] = $x['con']; }
		}
		if ( $con ) { $ra[] = 'tích luỹ còn giữ: ' . $vi( $con ); }
		return $ra;
	}

	/* ============================================================== sổ các lần trả */

	public static function so_tra() {
		$d = VHCC_Luong::cai_dat( self::O_TRA, null );
		return is_array( $d ) ? $d : array();
	}

	/** Các lần trả của một người ở một cơ sở. */
	public static function ds_tra( $coso, $ma, $so = null ) {
		$so = ( null === $so ) ? self::so_tra() : $so;
		$k = self::khoa_cs( $coso ); $m = strtolower( trim( (string) $ma ) );
		return ( isset( $so[ $k ][ $m ] ) && is_array( $so[ $k ][ $m ] ) ) ? array_values( $so[ $k ][ $m ] ) : array();
	}

	/** Tiền trả ra (theo khoản) vào lương tháng `$th`. */
	public static function tra_trong_thang( $coso, $th, $ma, $so = null ) {
		$ra = array();
		foreach ( self::ds_tra( $coso, $ma, $so ) as $t ) {
			if ( (string) $t['thang'] !== $th ) { continue; }
			$ra[ $t['khoan'] ] = round( ( isset( $ra[ $t['khoan'] ] ) ? $ra[ $t['khoan'] ] : 0 ) + (float) $t['tien'], 2 );
		}
		return $ra;
	}

	/**
	 * Số đã giữ / đã trả / còn lại của một người, theo khoản.
	 *
	 * @param string $den_th '' = mọi tháng; 'YYYY-MM' = chỉ tính tháng giữ và lần trả ≤ tháng ấy.
	 * @return array { khoản => [ 'giu' => …, 'tra' => …, 'con' => … ] } — chỉ khoản có số.
	 */
	public static function tich_luy( $coso, $ma, $den_th = '', $so_cl = null, $so_k = null, $so_t = null ) {
		$so_cl = ( null === $so_cl ) ? VHCC_ChotLuong::so() : $so_cl;
		$so_k  = ( null === $so_k ) ? self::so_khoan() : $so_k;
		$k = self::khoa_cs( $coso ); $m = strtolower( trim( (string) $ma ) );
		$ra = array();
		foreach ( ( isset( $so_cl[ $k ] ) ? (array) $so_cl[ $k ] : array() ) as $th => $o ) {
			if ( '' !== $den_th && (string) $th > $den_th ) { continue; }
			$cong = isset( $o['tien'][ $m ]['cong'] ) ? (array) $o['tien'][ $m ]['cong'] : array();
			foreach ( $cong as $kh => $v ) {
				if ( (float) $v <= 0 || ! self::dang_giu( $kh, (string) $th, $so_k ) ) { continue; }
				if ( ! isset( $ra[ $kh ] ) ) { $ra[ $kh ] = array( 'giu' => 0.0, 'tra' => 0.0, 'con' => 0.0 ); }
				$ra[ $kh ]['giu'] += (float) $v;
			}
		}
		foreach ( self::ds_tra( $coso, $ma, $so_t ) as $t ) {
			if ( '' !== $den_th && (string) $t['thang'] > $den_th ) { continue; }
			$kh = (string) $t['khoan'];
			if ( ! isset( $ra[ $kh ] ) ) { $ra[ $kh ] = array( 'giu' => 0.0, 'tra' => 0.0, 'con' => 0.0 ); }
			$ra[ $kh ]['tra'] += (float) $t['tien'];
		}
		foreach ( $ra as $kh => $x ) {
			$ra[ $kh ] = array( 'giu' => round( $x['giu'], 2 ), 'tra' => round( $x['tra'], 2 ),
				'con' => round( $x['giu'] - $x['tra'], 2 ) );
		}
		return $ra;
	}

	/** Mọi mã có số giữ hoặc lần trả ở một cơ sở — để bày bảng tích luỹ. */
	public static function ma_co_so( $coso ) {
		$k = self::khoa_cs( $coso );
		$so_k = self::so_khoan();
		$ma = array();
		foreach ( ( isset( VHCC_ChotLuong::so()[ $k ] ) ? (array) VHCC_ChotLuong::so()[ $k ] : array() ) as $th => $o ) {
			foreach ( ( isset( $o['tien'] ) ? (array) $o['tien'] : array() ) as $m => $x ) {
				foreach ( ( isset( $x['cong'] ) ? (array) $x['cong'] : array() ) as $kh => $v ) {
					if ( (float) $v > 0 && self::dang_giu( $kh, (string) $th, $so_k ) ) { $ma[ $m ] = 1; }
				}
			}
		}
		$so_t = self::so_tra();
		foreach ( ( isset( $so_t[ $k ] ) ? (array) $so_t[ $k ] : array() ) as $m => $ds ) {
			if ( $ds ) { $ma[ $m ] = 1; }
		}
		return array_keys( $ma );
	}

	/**
	 * Trả tiền đã giữ vào lương tháng `$th`.
	 *
	 * @param array $ds [ [ 'ma' => …, 'khoan' => …, 'tien' => … ], … ] — chỉ mấy ô ĐÃ TÍCH.
	 *
	 * ⚠️ Không trả quá số còn lại — tính tới tháng trả VÀ trên cả sổ (một lần trả ở tháng sau đã
	 *    ăn bớt thì tháng trước không được trả lại phần ấy lần nữa).
	 * ⚠️ Một ô hỏng không làm đổ cả lượt: ghi ô đúng, kể ra ô sai.
	 */
	public static function tra( $u, $coso, $th, $ds ) {
		if ( ! VHCC_Vai::duoc( $u, self::QUYEN ) ) {
			return array( 'ok' => false, 'error' => VHCC_Vai::loi( $u, self::QUYEN, 'Trả khoản giữ lại' ) );
		}
		if ( ! VHCC_NhanSu::co_quyen_coso( $u, $coso ) ) {
			return array( 'ok' => false, 'error' => 'Không có quyền trên cơ sở "' . $coso . '".' );
		}
		$th = VHCC_Luong::tien_to_thang( $th );
		if ( '' === $th ) { return array( 'ok' => false, 'error' => 'Tháng không hợp lệ.' ); }
		$k  = self::khoa_cs( $coso );
		$so = self::so_tra();
		$xong = 0; $tong = 0.0; $hong = array();
		foreach ( (array) $ds as $x ) {
			$m  = strtolower( trim( (string) ( isset( $x['ma'] ) ? $x['ma'] : '' ) ) );
			$kh = (string) ( isset( $x['khoan'] ) ? $x['khoan'] : '' );
			if ( '' === $m || ! isset( VHCC_ChotLuong::CONG[ $kh ] ) ) { continue; }
			$tien = VHCC_NhanSu::so_tien( isset( $x['tien'] ) ? $x['tien'] : '' );
			$tl_th  = self::tich_luy( $coso, $m, $th, null, null, $so );
			$tl_het = self::tich_luy( $coso, $m, '', null, null, $so );
			$con = min( isset( $tl_th[ $kh ] ) ? $tl_th[ $kh ]['con'] : 0.0,
				isset( $tl_het[ $kh ] ) ? $tl_het[ $kh ]['con'] : 0.0 );
			if ( $tien <= 0 ) { $hong[] = $m . ' · ' . VHCC_ChotLuong::CONG[ $kh ] . ': số tiền trả phải lớn hơn 0'; continue; }
			if ( $tien > $con + 0.001 ) {
				$hong[] = $m . ' · ' . VHCC_ChotLuong::CONG[ $kh ] . ': chỉ còn ' . number_format( $con, 0, ',', '.' )
					. 'đ đã giữ tới tháng ' . $th . ' — không trả ' . number_format( $tien, 0, ',', '.' ) . 'đ được';
				continue;
			}
			if ( ! isset( $so[ $k ][ $m ] ) || ! is_array( $so[ $k ][ $m ] ) ) { $so[ $k ][ $m ] = array(); }
			$so[ $k ][ $m ][] = array( 'id' => substr( md5( $k . $m . $kh . $th . microtime() . wp_rand() ), 0, 12 ),
				'thang' => $th, 'khoan' => $kh, 'tien' => round( $tien, 2 ),
				'luc' => current_time( 'mysql' ), 'boi' => isset( $u['name'] ) ? (string) $u['name'] : '' );
			$xong++; $tong += $tien;
		}
		if ( $xong ) { VHCC_Luong::dat_cai_dat( self::O_TRA, $so, $u ); }
		return array( 'ok' => true, 'xong' => $xong, 'tong' => round( $tong, 2 ), 'hong' => $hong );
	}

	/** Huỷ những lần trả đã tích (theo `id`). */
	public static function huy( $u, $coso, $ids ) {
		if ( ! VHCC_Vai::duoc( $u, self::QUYEN ) ) {
			return array( 'ok' => false, 'error' => VHCC_Vai::loi( $u, self::QUYEN, 'Huỷ lần trả khoản giữ lại' ) );
		}
		if ( ! VHCC_NhanSu::co_quyen_coso( $u, $coso ) ) {
			return array( 'ok' => false, 'error' => 'Không có quyền trên cơ sở "' . $coso . '".' );
		}
		$ids = array_map( 'strval', (array) $ids );
		$k = self::khoa_cs( $coso );
		$so = self::so_tra();
		$bo = 0;
		foreach ( ( isset( $so[ $k ] ) ? (array) $so[ $k ] : array() ) as $m => $ds ) {
			$giu = array();
			foreach ( (array) $ds as $t ) {
				if ( in_array( (string) $t['id'], $ids, true ) ) { $bo++; continue; }
				$giu[] = $t;
			}
			if ( $giu ) { $so[ $k ][ $m ] = $giu; } else { unset( $so[ $k ][ $m ] ); }
		}
		if ( $bo ) { VHCC_Luong::dat_cai_dat( self::O_TRA, $so, $u ); }
		return array( 'ok' => true, 'bo' => $bo );
	}
}
