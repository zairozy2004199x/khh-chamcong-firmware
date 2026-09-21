<?php
/**
 * XUẤT BẢNG CÔNG RA MỘT TẤM ẢNH.
 *
 * Anh Thắng 28/08/2026: *"Thêm xuất dạng ảnh ra ( kèm thêm giờ vào ra nữa nhé )"*.
 *
 * 🔴 VÌ SAO SVG CHỨ KHÔNG PHẢI PNG.
 *    PNG dựng bằng GD phải có một tệp phông chữ TTF đi kèm mới viết được tiếng Việt —
 *    `imagestring()` của GD chỉ biết bảng chữ ASCII, nên "NGUYỄN HUỲNH TƯỜNG VY" ra một dãy ô
 *    vuông. Nhét cả một phông Unicode vào plugin là thêm non một megabyte cho mỗi bản cài, mà
 *    vẫn chưa chắc: hosting nào tắt phần mở rộng `gd` là nút bấm xong không ra gì.
 *    SVG thì máy chủ chỉ ghép chuỗi — không cần phần mở rộng nào, không cần phông nào, chữ
 *    tiếng Việt luôn đúng vì trình xem dùng phông của chính nó. Mở bằng trình duyệt, kéo thả
 *    vào Word / Excel / PowerPoint, hoặc chuột phải lưu thành .png đều được.
 *
 * 🔴 HÀM DỰNG ẢNH LÀ HÀM THUẦN. Vào là dữ liệu đã đọc sẵn, ra là một chuỗi. Không đọc CSDL,
 *    không đọc `$_GET`, không gửi header — nhờ vậy bộ thử soi được từng ô của tấm ảnh bằng
 *    biểu thức, thay vì chỉ đếm được số byte.
 *
 * ⚠️ MỌI CHỮ ĐI QUA `xml()`. Một dấu `&` trong tên cơ sở (K&H) là đủ để cả tệp SVG thành XML
 *    hỏng, và trình duyệt bỏ trắng — không vẽ nửa chừng, không báo gì.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_Anh {

	const W_TEN   = 216;   // bề ngang cột tên
	const W_NGAY  = 76;    // bề ngang một cột ngày — vừa đủ chuỗi "05:57-14:03" cỡ 8px
	const H_DAU   = 78;    // chiều cao khối tiêu đề
	const H_COT   = 34;    // chiều cao hàng tên ngày
	const H_DONG  = 15;    // chiều cao một dòng trong ô (một lượt chấm)
	const H_HANG  = 34;    // chiều cao tối thiểu một hàng người

	/** Nền theo ca — cùng bộ màu với lưới trên web, để hai bên nhìn ra cùng một ca. */
	const NEN_CA = array( '#eff6ff', '#f0fdf4', '#faf5ff', '#fff7ed' );

	public static function xml( $s ) {
		return htmlspecialchars( (string) $s, ENT_QUOTES | ENT_XML1, 'UTF-8' );
	}

	/** "08:59:31" -> "08:59". Giây trong ô ảnh chỉ tốn chỗ mà không nói thêm gì. */
	public static function hhmm( $s ) {
		$s = trim( (string) $s );
		return ( strlen( $s ) >= 5 ) ? substr( $s, 0, 5 ) : $s;
	}

	/**
	 * Dựng tấm ảnh .svg của cả tháng.
	 *
	 * @param array  $b       kết quả `VHCC_Cham::bang_cham_cong`.
	 * @param array  $ds_ca   ca của cơ sở (để tô màu và biết mã ca).
	 * @param string $kieu    cách tính công của cơ sở ('gio' | 'ca' | 'ngay' | 'cong').
	 * @param int    $nguong  ngưỡng đi trễ của cơ sở, tính bằng phút.
	 * @param string $ngay_xuat  ngày in lên góc ảnh; để trống thì bỏ dòng ấy.
	 */
	public static function svg( $b, $ds_ca, $kieu = 'gio', $nguong = 0, $ngay_xuat = '' ) {
		$tt  = isset( $b['thang'] ) ? (string) $b['thang'] : '';
		$moc = strtotime( $tt . '-01 00:00:00 UTC' );
		if ( false === $moc ) { return ''; }
		$so_ngay = (int) gmdate( 't', $moc );
		$thu_vn  = array( 'CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7' );

		/* Gom [mã][ngày] = danh sách lượt. Một ngày có thể có nhiều lượt (hàng `-CD` ca đêm,
		   `-TC` tăng cường) — mỗi lượt một dòng trong ô, chứ không gộp lại thành một con số. */
		$o   = array();
		$ten = array();
		foreach ( (array) $b['hang'] as $r ) {
			$ma = (string) $r['maNV'];
			$d  = (int) substr( (string) $r['ngay'], 8, 2 );
			$o[ $ma ][ $d ][] = $r;
			if ( ! isset( $ten[ $ma ] ) || '' === $ten[ $ma ] ) { $ten[ $ma ] = (string) $r['hoTen']; }
		}
		uasort( $ten, function ( $a, $c ) { return strcasecmp( $a, $c ); } );

		/* Chiều cao từng hàng phụ thuộc người ĐÓ có ngày nào hai lượt không — dựng trước để
		   biết cả tấm ảnh cao bao nhiêu, vì SVG phải khai chiều cao ngay ở thẻ đầu. */
		$cao = array();
		foreach ( $ten as $ma => $x ) {
			$nhieu = 1;
			foreach ( (array) ( isset( $o[ $ma ] ) ? $o[ $ma ] : array() ) as $ds ) {
				$nhieu = max( $nhieu, count( $ds ) );
			}
			$cao[ $ma ] = max( self::H_HANG, 4 + $nhieu * ( self::H_DONG + 9 ) );
		}
		$rong = self::W_TEN + $so_ngay * self::W_NGAY;
		$dai  = self::H_DAU + self::H_COT + array_sum( $cao ) + 30;

		$s  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$s .= '<svg xmlns="http://www.w3.org/2000/svg" width="' . $rong . '" height="' . $dai . '"'
			. ' viewBox="0 0 ' . $rong . ' ' . $dai . '" font-family="Arial, Helvetica, sans-serif">';
		$s .= '<rect width="100%" height="100%" fill="#ffffff"/>';

		/* ---- tiêu đề ---- */
		$s .= '<text x="16" y="30" font-size="19" font-weight="bold" fill="#0f172a">'
			. self::xml( 'Bảng chấm công · ' . ( isset( $b['coSo'] ) ? $b['coSo'] : '' ) ) . '</text>';
		$s .= '<text x="16" y="50" font-size="13" fill="#475569">'
			. self::xml( 'Tháng ' . $tt . ' · ' . count( $ten ) . ' người · '
				. ( isset( VHCC_Luong::CACH_TINH_TEN[ $kieu ] )
					? VHCC_Luong::CACH_TINH_TEN[ $kieu ] : $kieu )
				. ( 'ca' === $kieu ? ' · ngưỡng trễ ' . (int) $nguong . ' phút' : '' ) )
			. '</text>';
		$s .= '<text x="16" y="68" font-size="11.5" fill="#64748b">'
			. self::xml( 'Mỗi ô: số giờ công · mã ca · giờ vào–giờ ra thật như máy ghi.'
				. ( '' !== $ngay_xuat ? '  Xuất ngày ' . $ngay_xuat . '.' : '' ) ) . '</text>';

		/* ---- hàng tên ngày ---- */
		$y0 = self::H_DAU;
		$s .= '<rect x="0" y="' . $y0 . '" width="' . $rong . '" height="' . self::H_COT
			. '" fill="#f1f5f9"/>';
		$s .= '<text x="10" y="' . ( $y0 + 21 ) . '" font-size="12" font-weight="bold" fill="#0f172a">'
			. self::xml( 'Nhân viên' ) . '</text>';
		for ( $i = 1; $i <= $so_ngay; $i++ ) {
			$x  = self::W_TEN + ( $i - 1 ) * self::W_NGAY;
			$t  = (int) gmdate( 'w', strtotime( sprintf( '%s-%02d 00:00:00 UTC', $tt, $i ) ) );
			$cn = ( 0 === $t || 6 === $t );
			if ( $cn ) {
				$s .= '<rect x="' . $x . '" y="' . $y0 . '" width="' . self::W_NGAY . '" height="'
					. ( $dai - $y0 - 30 ) . '" fill="#fef2f2" opacity="0.55"/>';
			}
			$s .= '<text x="' . ( $x + self::W_NGAY / 2 ) . '" y="' . ( $y0 + 15 )
				. '" font-size="12" font-weight="bold" text-anchor="middle" fill="'
				. ( $cn ? '#dc2626' : '#0f172a' ) . '">' . $i . '</text>';
			$s .= '<text x="' . ( $x + self::W_NGAY / 2 ) . '" y="' . ( $y0 + 28 )
				. '" font-size="10" text-anchor="middle" fill="'
				. ( $cn ? '#dc2626' : '#64748b' ) . '">' . self::xml( $thu_vn[ $t ] ) . '</text>';
		}

		/* ---- từng người ---- */
		$y = $y0 + self::H_COT;
		foreach ( $ten as $ma => $ho_ten ) {
			$h = $cao[ $ma ];
			$s .= '<line x1="0" y1="' . $y . '" x2="' . $rong . '" y2="' . $y
				. '" stroke="#e2e8f0" stroke-width="1"/>';
			$s .= '<text x="10" y="' . ( $y + 17 ) . '" font-size="11.5" fill="#0f172a">'
				. self::xml( self::cat( $ho_ten, 27 ) ) . '</text>';
			$s .= '<text x="10" y="' . ( $y + 30 ) . '" font-size="9.5" fill="#94a3b8">'
				. self::xml( $ma ) . '</text>';

			for ( $i = 1; $i <= $so_ngay; $i++ ) {
				$x  = self::W_TEN + ( $i - 1 ) * self::W_NGAY;
				$ds = isset( $o[ $ma ][ $i ] ) ? $o[ $ma ][ $i ] : array();
				if ( ! $ds ) {
					$s .= '<text x="' . ( $x + self::W_NGAY / 2 ) . '" y="' . ( $y + 20 )
						. '" font-size="11" text-anchor="middle" fill="#cbd5e1">·</text>';
					continue;
				}
				$yy = $y + 4;
				foreach ( $ds as $r ) {
					$s  .= self::o_mot( $r, $x, $yy, $ds_ca, $kieu, $nguong );
					$yy += self::H_DONG + 9;
				}
			}
			$y += $h;
		}
		$s .= '<line x1="0" y1="' . $y . '" x2="' . $rong . '" y2="' . $y
			. '" stroke="#cbd5e1" stroke-width="1"/>';
		$s .= '<text x="16" y="' . ( $y + 20 ) . '" font-size="10.5" fill="#94a3b8">'
			. self::xml( 'Ô vàng = chấm thiếu giờ so với khung ca. Dấu ? = thiếu giờ ra.' )
			. '</text>';
		$s .= '</svg>';
		return $s;
	}

	/** Cắt tên quá dài — cột tên rộng cố định, chữ tràn ra là đè lên cột ngày đầu tiên. */
	public static function cat( $s, $n ) {
		$s = trim( (string) $s );
		if ( function_exists( 'mb_strlen' ) ) {
			return ( mb_strlen( $s, 'UTF-8' ) > $n ) ? ( mb_substr( $s, 0, $n - 1, 'UTF-8' ) . '…' ) : $s;
		}
		return ( strlen( $s ) > $n ) ? ( substr( $s, 0, $n - 1 ) . '…' ) : $s;
	}

	/**
	 * MỘT LƯỢT CHẤM -> một cụm trong ô: số giờ · mã ca · giờ vào–giờ ra.
	 *
	 * 🔴 GIỜ VÀO – GIỜ RA LÀ GIỜ THẬT MÁY GHI, kể cả khi ô đã làm tròn theo ca. Đây đúng là chỗ
	 *    anh Thắng cần: cầm tấm ảnh là đối chiếu được con số công với giờ bấm máy, không phải mở
	 *    thêm tệp nào. Giấu giờ thật đi thì tấm ảnh chỉ còn là một bảng số không kiểm được.
	 */
	private static function o_mot( $r, $x, $y, $ds_ca, $kieu, $nguong ) {
		$w  = self::W_NGAY;
		$gx = $x + $w / 2;

		if ( null === $r['phut'] ) {
			$thieu_ra = ( '' !== $r['vao'] && '' === $r['ra'] );
			$s  = '<rect x="' . ( $x + 2 ) . '" y="' . $y . '" width="' . ( $w - 4 ) . '" height="'
				. ( self::H_DONG + 7 ) . '" fill="#fef2f2" stroke="#fecaca" stroke-width="1" rx="3"/>';
			$s .= '<text x="' . $gx . '" y="' . ( $y + 15 ) . '" font-size="12" font-weight="bold"'
				. ' text-anchor="middle" fill="#dc2626">' . ( $thieu_ra ? '?' : '—' ) . '</text>';
			$s .= '<text x="' . $gx . '" y="' . ( $y + 24 ) . '" font-size="8" text-anchor="middle"'
				. ' fill="#b91c1c">' . self::xml( self::hhmm( $r['vao'] ) . '–'
					. ( '' !== $r['ra'] ? self::hhmm( $r['ra'] ) : '?' ) ) . '</text>';
			return $s;
		}

		$tc   = VHCC_Ca::tach( $ds_ca, $r['vaoGiay'], $r['raGiay'], VHCC_Ca::la_cuoi_tuan( $r['ngay'] ) );
		$i_ca = VHCC_Ca::ca_chinh( $ds_ca, $tc );
		$ma_o = VHCC_Ca::ma_o( $ds_ca, $tc );

		$phut = (int) $r['phut'];
		$vang = false;
		if ( 'ca' === $kieu ) {
			$lt   = VHCC_Ca::lam_tron( $ds_ca, $r['vaoGiay'], $r['raGiay'],
				VHCC_Ca::la_cuoi_tuan( $r['ngay'] ), $nguong );
			$phut = (int) $lt['phut'];
			$vang = ( ! empty( $lt['thieu'] ) || ! empty( $lt['ngoai_moi_ca'] ) );
		}
		$so = ( 'ngay' === $kieu )
			? (string) VHCC_Luong::cong_co_di( $r['vaoGiay'], $r['raGiay'] )
			: rtrim( rtrim( number_format( $phut / 60, 1, '.', '' ), '0' ), '.' );

		$nen = $vang ? '#fffbeb'
			: ( $i_ca >= 0 ? self::NEN_CA[ $i_ca % 4 ] : '#ffffff' );
		$vien = $vang ? '#f59e0b' : '#e2e8f0';

		$s  = '<rect x="' . ( $x + 2 ) . '" y="' . $y . '" width="' . ( $w - 4 ) . '" height="'
			. ( self::H_DONG + 7 ) . '" fill="' . $nen . '" stroke="' . $vien
			. '" stroke-width="1" rx="3"/>';
		$s .= '<text x="' . ( $gx - ( '' !== $ma_o ? 12 : 0 ) ) . '" y="' . ( $y + 14 )
			. '" font-size="12" font-weight="bold" text-anchor="middle" fill="#0f172a">'
			. self::xml( $so ) . '</text>';
		if ( '' !== $ma_o ) {
			$s .= '<text x="' . ( $gx + 18 ) . '" y="' . ( $y + 14 ) . '" font-size="8"'
				. ' text-anchor="middle" fill="#64748b">' . self::xml( $ma_o ) . '</text>';
		}
		/* Dòng giờ vào–ra: đúng thứ anh Thắng dặn "kèm thêm giờ vào ra nữa nhé". */
		$s .= '<text x="' . $gx . '" y="' . ( $y + 23 ) . '" font-size="8" text-anchor="middle"'
			. ' fill="#475569">' . self::xml( self::hhmm( $r['vao'] ) . '–' . self::hhmm( $r['ra'] ) )
			. '</text>';
		return $s;
	}
}
