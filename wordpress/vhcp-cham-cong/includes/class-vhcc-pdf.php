<?php
/**
 * TỜ BẢNG CHẤM CÔNG ĐỂ IN — bản WordPress của `xuatPdfChamCong`.
 *
 * =============================================================================================
 * VÌ SAO LÀ "TRANG IN" MÀ KHÔNG PHẢI TỆP .PDF
 * =============================================================================================
 * Bên Apps Script, `Utilities.newBlob(html).getAs('application/pdf')` dùng bộ chuyển HTML→PDF của
 * Google. WordPress trên hosting chia sẻ KHÔNG có thứ tương đương, và cài một thư viện PDF cho PHP trên
 * hosting chia sẻ là thêm một chỗ hỏng mà lúc hỏng thì không in được bảng công.
 *
 * Nên trang này là HTML in khổ A4: bấm Ctrl+P (hay "Chia sẻ → In" trên điện thoại) rồi chọn "Lưu
 * thành PDF". Ra đúng tệp PDF, đúng khuôn giấy, mà không phụ thuộc thư viện nào.
 * Toàn bộ CSS `@page`, `thead{display:table-header-group}`, `tr{page-break-inside:avoid}` giữ y
 * bản gốc — vốn chính HTML này là thứ bộ chuyển của Google đang dựng ra.
 *
 * =============================================================================================
 * MỘT CHỖ CỐ Ý KHÁC BẢN GỐC
 * =============================================================================================
 * `xuatPdfChamCong` nhận `req.tongHop` và `req.chiTiet` TỪ TRÌNH DUYỆT: giao diện tự tính rồi đẩy
 * số lên, máy chủ chỉ đổ vào khuôn. Nghĩa là con số trên tờ giấy chấm công là con số máy khách
 * gửi lên, và ai sửa được yêu cầu là sửa được tờ giấy.
 * Ở đây máy chủ TỰ TÍNH từ MySQL. Giao diện chỉ nói "cơ sở nào, từ ngày nào đến ngày nào".
 *
 * ⚠️ GIỮ nguyên chuyện CẮT BỚT CÓ BÁO: quá `MAX_CHI_TIET` dòng thì cắt và IN HẲN dòng cảnh báo
 *    lên giấy. Cắt im lặng trên một tờ bảng công là tờ giấy trông đầy đủ trong khi thiếu người.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_Pdf {

	const MAX_CHI_TIET = 4000;
	const MAX_TONG_HOP = 500;
	const MAX_O        = 120;

	public static function ten_cong_ty() {
		$t = get_option( 'vhcc_ten_cong_ty', '' );
		return '' !== trim( (string) $t ) ? trim( (string) $t ) : 'CÔNG TY TNHH GIẢI TRÍ K&H';
	}

	/** Cắt độ dài một ô để một ô rác không kéo dài cả trang, rồi thoát HTML. */
	public static function esc( $s ) {
		return esc_html( mb_substr( (string) $s, 0, self::MAX_O, 'UTF-8' ) );
	}

	/** 'yyyy-MM-dd' -> 'dd/MM/yyyy'. Không đúng khuôn thì GIỮ NGUYÊN, không tự đoán. */
	public static function ngay_vn( $s ) {
		return preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', trim( (string) $s ), $m )
			? ( $m[3] . '/' . $m[2] . '/' . $m[1] ) : (string) $s;
	}

	/** Tên tệp: KHÔNG dấu, không khoảng trắng — máy in / Zalo đỡ đổi tên lung tung. */
	public static function ten_tep( $coso, $tu, $den ) {
		/* Cờ `u`: không có nó thì phép thay chạy theo BYTE, nên một chữ có dấu ("ơ") thành 2-3 dấu
		   gạch thay vì một. Bản gốc chạy trên chuỗi JS nên một chữ ra một gạch — giữ cho khớp, để
		   tên tệp của cùng một cơ sở không khác nhau giữa hai hệ. */
		$st = preg_replace( '/[^A-Za-z0-9_\-]/u', '_', preg_replace( '/^CS_/', '', (string) $coso ) );
		$a  = preg_replace( '/[^0-9]/', '', (string) $tu );
		$b  = preg_replace( '/[^0-9]/', '', (string) $den );
		return 'BangCong_' . ( '' !== $st ? $st : 'CoSo' ) . '_' . $a . ( ( $b && $b !== $a ) ? '-' . $b : '' );
	}

	/**
	 * Số PHÚT làm của một hàng. `null` khi thiếu một trong hai đầu — thiếu thì KHÔNG đoán.
	 *
	 * ⚠️ MỘT chỗ tính duy nhất. Bản đầu của tệp này tính giờ làm ở hai nơi (ô chi tiết và cột tổng
	 *    hợp) mỗi nơi một bản; hai bản tính giờ trên cùng một tờ giấy là đúng cái mà Code.gs tự
	 *    cảnh báo — sớm muộn lệch nhau, và lệch giờ trên bảng chấm công là lệch tiền.
	 */
	public static function phut_lam( $vao, $ra ) {
		if ( null === $vao || null === $ra || '' === $vao || '' === $ra ) { return null; }
		$p = intdiv( (int) $ra, 60 ) - intdiv( (int) $vao, 60 );
		/* Hàng ca đêm đã trải phẳng nên hiệu luôn dương; hàng chính vắt qua nửa đêm thì cộng bù,
		   giống `_mtdTinhLuong`. Không xử thì ra số ÂM trên tờ giấy chấm công. */
		if ( $p < 0 ) { $p += 1440; }
		return $p;
	}

	/** Cùng số đó, dạng chữ để in. */
	public static function gio_lam( $vao, $ra ) {
		$p = self::phut_lam( $vao, $ra );
		return null === $p ? '' : ( number_format( $p / 60, 2 ) . 'h' );
	}

	/**
	 * Gom dữ liệu của tờ giấy. Trả cả phần bị cắt để nơi in còn báo được.
	 * Tính Ở MÁY CHỦ — xem khối cảnh báo ở đầu tệp.
	 */
	public static function gom( $coso, $tu, $den ) {
		global $wpdb;
		$hang = VHCC_DB::rows( $wpdb->prepare(
			'SELECT ngay, ma_nv, hau_to, ho_ten, gio_vao_giay, gio_ra_giay FROM '
			. VHCC_DB::t( 'cham_cong' )
			. ' WHERE coso=%s AND ngay >= %s AND ngay <= %s ORDER BY ngay, ho_ten, ma_nv, hau_to',
			$coso, $tu, $den ) );

		$chi_tiet = array();
		$tong = array();
		foreach ( $hang as $r ) {
			$ma = trim( (string) $r['ma_nv'] );
			if ( '' === $ma ) { continue; }
			$hau_to = strtoupper( trim( (string) $r['hau_to'] ) );
			$ma_hien = $ma . ( '' !== $hau_to ? '-' . $hau_to : '' );
			$ten = '' !== trim( (string) $r['ho_ten'] ) ? $r['ho_ten'] : $ma_hien;
			$vao = ( null === $r['gio_vao_giay'] || '' === $r['gio_vao_giay'] ) ? null : (int) $r['gio_vao_giay'];
			$ra  = ( null === $r['gio_ra_giay'] || '' === $r['gio_ra_giay'] ) ? null : (int) $r['gio_ra_giay'];

			$chi_tiet[] = array( 'ngay' => $r['ngay'], 'ma' => $ma_hien, 'ten' => $ten,
				'vao' => VHCC_DB::hhmmss( $vao ), 'ra' => VHCC_DB::hhmmss( $ra ),
				'gio' => self::gio_lam( $vao, $ra ) );

			if ( ! isset( $tong[ $ma_hien ] ) ) {
				$tong[ $ma_hien ] = array( 'ma' => $ma_hien, 'ten' => $ten,
					'soNgay' => 0, 'thieuRa' => 0, 'phut' => 0 );
			}
			$tong[ $ma_hien ]['soNgay']++;
			if ( null === $ra ) { $tong[ $ma_hien ]['thieuRa']++; }
			/* Ngày thiếu một đầu KHÔNG cộng giờ — `phut_lam` trả null. Cộng bừa 0 hay cộng nửa ngày
			   là tự bịa giờ làm cho một ngày mà máy không biết người ta làm bao lâu. */
			$p = self::phut_lam( $vao, $ra );
			if ( null !== $p ) { $tong[ $ma_hien ]['phut'] += $p; }
		}
		$tong = array_values( $tong );
		usort( $tong, function ( $a, $b ) { return strcmp( $a['ten'], $b['ten'] ); } );

		return array(
			'tongHop'    => array_slice( $tong, 0, self::MAX_TONG_HOP ),
			'chiTiet'    => array_slice( $chi_tiet, 0, self::MAX_CHI_TIET ),
			'soChiTiet'  => count( $chi_tiet ),
			'biCat'      => count( $chi_tiet ) > self::MAX_CHI_TIET,
			'biCatTong'  => count( $tong ) > self::MAX_TONG_HOP,
		);
	}

	/**
	 * SỐ CÔNG TRONG KHOẢNG NGÀY — ĐI QUA ĐÚNG ENGINE CỦA CƠ SỞ.
	 *
	 * 🔴 Anh Thắng 31/08/2026: *"tại sao bảng công trên wed với bảng công in ra khác nhau"* và
	 *    *"Cơ sở đã set công, tại sao lại xuất ra giờ làm chi đâu, không cần thiết, cần số công
	 *    chính xác như wed"*.
	 *
	 *    Tờ in trước bản này đọc THẲNG bảng `cham_cong` rồi ĐẾM SỐ HÀNG và CỘNG GIỜ THÔ — nó
	 *    không hề biết cơ sở ấy đang tính theo công hay theo giờ. Lưới web thì đi qua engine
	 *    Văn phòng: khung giờ, bậc thang 0.5 · 1 · 1.5, tăng ca, ca đêm, công bù, và luật
	 *    "thiếu một đầu giờ thì ngày ấy 0 công". Hai đường tính hai kiểu nên hai con số KHÔNG
	 *    THỂ bằng nhau — Huỳnh Minh Nhật ra 21 trên giấy và 16.5 trên màn, Huỳnh Quang Thắng
	 *    ra 27 trên giấy và 31 trên màn (ngày làm dài được 1.5 công nên tổng vượt số ngày).
	 *
	 *    Tờ giấy này đưa nhân viên KÝ. Hai bảng cùng tên "bảng công" mà lệch nhau là chỗ sinh
	 *    tranh cãi đắt nhất, và bên nào đúng thì không ai chỉ ra được. Nay chỉ còn MỘT nguồn:
	 *    engine của cơ sở.
	 *
	 * ⚠️ ENGINE TÍNH THEO THÁNG, TỜ IN NHẬN KHOẢNG NGÀY BẤT KỲ. Nên chạy engine cho TỪNG THÁNG
	 *    mà khoảng chạm tới, rồi lọc lấy đúng những ngày nằm trong khoảng. Cắt theo tháng rồi
	 *    cộng thẳng là sai khi anh in nửa tháng; mà tính riêng cho một khúc ngày cũng sai, vì
	 *    ca đêm đêm cuối tháng đẩy công sang ngày đầu tháng sau.
	 *
	 * @return array [ 'nguoi' => [ma => ['ma','ten','cong','thieuRa']], 'o' => [ma][ngày] => công ]
	 */
	public static function cong_theo_khoang( $coso, $tu, $den ) {
		$ra = array( 'nguoi' => array(), 'o' => array(), 'co' => array() );
		if ( ! class_exists( 'VHCC_Luong' ) || ! method_exists( 'VHCC_Luong', 'vp_bang_cong_va_luong' ) ) {
			return $ra;
		}
		$t1 = strtotime( (string) $tu . ' 00:00:00 UTC' );
		$t2 = strtotime( (string) $den . ' 00:00:00 UTC' );
		if ( false === $t1 || false === $t2 || $t2 < $t1 ) { return $ra; }

		/* Danh sách THÁNG mà khoảng này chạm tới. Vòng lặp đi theo mốc "ngày 1 của tháng" chứ
		   không cộng 30 ngày một bước — cộng ngày thì tháng 2 nhảy qua mất tháng 3. */
		$thang = array();
		$m = strtotime( gmdate( 'Y-m', $t1 ) . '-01 00:00:00 UTC' );
		$het = strtotime( gmdate( 'Y-m', $t2 ) . '-01 00:00:00 UTC' );
		while ( $m <= $het ) {
			$thang[] = gmdate( 'Y-m', $m );
			$m = strtotime( '+1 month', $m );
		}

		foreach ( $thang as $tt ) {
			$b = VHCC_Luong::vp_bang_cong_va_luong( $coso, $tt );
			$ten = array();
			foreach ( (array) $b['rows'] as $e ) { $ten[ (string) $e['ma'] ] = (string) $e['ten']; }
			foreach ( (array) $b['detail'] as $d ) {
				$ngay = (string) $d['ngay'];
				$g    = strtotime( $ngay . ' 00:00:00 UTC' );
				if ( false === $g || $g < $t1 || $g > $t2 ) { continue; }
				$ma = (string) $d['ma'];
				if ( ! isset( $ra['nguoi'][ $ma ] ) ) {
					$ra['nguoi'][ $ma ] = array( 'ma' => $ma,
						'ten' => isset( $ten[ $ma ] ) ? $ten[ $ma ] : $ma,
						'cong' => 0.0, 'thieuRa' => 0 );
				}
				$ra['nguoi'][ $ma ]['cong'] += (float) $d['tong'];
				$ra['o'][ $ma ][ $ngay ] = (float) $d['tong'];
				/* Cùng luật "thiếu một đầu giờ" với ô đỏ trên lưới — kể cả hàng ca đêm. */
				$thieu_o = ( ( ( '' !== $d['vao'] ) !== ( '' !== $d['ra'] ) )
					|| ( ( '' !== $d['h2vao'] ) !== ( '' !== $d['h2ra'] ) ) );
				if ( $thieu_o ) { $ra['nguoi'][ $ma ]['thieuRa']++; }
				/* 🔴 CỜ CỦA TỪNG Ô — thứ làm cho lưới in ra giấy nói được đúng những gì lưới
				   trên màn nói. Giấy in đen trắng KHÔNG có màu để tô, nên dấu `?` và nhãn cơ sở
				   ghép là tất cả những gì còn lại; bỏ chúng thì ô `0` vì quên bấm trông y hệt
				   ô `0` vì nghỉ — hai chuyện xử lý khác hẳn nhau. */
				$ra['co'][ $ma ][ $ngay ] = array(
					'thieu'  => $thieu_o,
					'dem'    => (float) $d['congDem'],
					'demHut' => ( ! empty( $d['demThieuGio'] ) || ! empty( $d['demChuaDuCap'] ) ),
					'tuCoSo' => (string) $d['tuCoSo'],
				);
			}
		}
		foreach ( $ra['nguoi'] as $ma => $n ) {
			$ra['nguoi'][ $ma ]['cong'] = round( $n['cong'], 2 );
		}
		uasort( $ra['nguoi'], function ( $a, $b ) { return strcmp( $a['ten'], $b['ten'] ); } );
		return $ra;
	}

	/**
	 * LƯỚI CẢ THÁNG TRÊN GIẤY — bảng ngang, mỗi cột một ngày.
	 *
	 * 🔴 Anh Thắng 31/08/2026 gửi ảnh chính cái lưới trên màn: *"thiếu bảng này"*. Sau khi bỏ
	 *    mục chi tiết từng ngày, tờ giấy chỉ còn bảng tổng hợp — mỗi người một dòng, một con
	 *    số. Kế toán đối soát bằng LƯỚI: nhìn ngang một hàng là thấy ngày nào nghỉ, ngày nào
	 *    quên bấm, ngày nào 1.5. Một con số tổng không thay được việc ấy.
	 *
	 * 🔴 GIỮ CỜ, VÌ GIẤY KHÔNG CÓ MÀU. Màn hình tô ô đỏ; giấy in đen trắng thì dấu `?` và nhãn
	 *    cơ sở ghép là thứ duy nhất còn lại. Bỏ chúng đi thì ô `0` vì quên bấm trông y hệt ô
	 *    `0` vì nghỉ — mà hai chuyện ấy xử lý khác hẳn nhau.
	 *
	 * ⚠️ Ô TRỐNG ≠ Ô `0`. Trống là hôm ấy không có dữ liệu chấm công; `0` là có đi làm mà
	 *    không ra công. In `0` cho cả hai là bảng công nói dối trên giấy có chữ ký.
	 *
	 * Hàm THUẦN: vào là mấy mảng, ra là một chuỗi. Thử được bằng dữ liệu trần.
	 *
	 * @param string $tt      Tháng `YYYY-MM`.
	 * @param array  $nguoi   [ ['ma','ten'], … ] theo đúng thứ tự muốn in.
	 * @param array  $o       [mã][ngày `YYYY-MM-DD`] => số (công hoặc giờ).
	 * @param array  $co      [mã][ngày] => ['thieu','dem','demHut','tuCoSo'] — có thể rỗng.
	 * @param bool   $la_cong true = ô là số công · false = ô là số giờ.
	 */
	public static function luoi_in( $tt, $nguoi, $o, $co, $la_cong = true ) {
		$moc = strtotime( (string) $tt . '-01 00:00:00 UTC' );
		if ( false === $moc ) { return ''; }
		$so_ngay = (int) gmdate( 't', $moc );
		$thu_vn  = array( 'CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7' );

		$out = array();
		$out[] = '<table class="luoi"><thead><tr><th class="ten">Nhân viên</th>';
		for ( $i = 1; $i <= $so_ngay; $i++ ) {
			$w  = (int) gmdate( 'w', strtotime( sprintf( '%s-%02d 00:00:00 UTC', $tt, $i ) ) );
			$cn = ( 0 === $w || 6 === $w );
			$out[] = '<th class="' . ( $cn ? 'cn' : '' ) . '">' . $i
				. '<div class="thu">' . $thu_vn[ $w ] . '</div></th>';
		}
		$out[] = '<th class="tg">TỔNG</th></tr></thead><tbody>';

		if ( ! $nguoi ) {
			$out[] = '<tr><td colspan="' . ( $so_ngay + 2 ) . '" class="c">(Không có dữ liệu)</td></tr>';
		}
		foreach ( $nguoi as $n ) {
			$ma = (string) $n['ma'];
			$out[] = '<tr><td class="ten">' . self::esc( (string) $n['ten'] ) . '</td>';
			$cong = 0.0;
			for ( $i = 1; $i <= $so_ngay; $i++ ) {
				$ngay = sprintf( '%s-%02d', $tt, $i );
				if ( ! isset( $o[ $ma ][ $ngay ] ) ) {
					$out[] = '<td class="c"></td>';
					continue;
				}
				$gia   = (float) $o[ $ma ][ $ngay ];
				$cong += $gia;
				$c     = isset( $co[ $ma ][ $ngay ] ) ? $co[ $ma ][ $ngay ] : array();
				$thieu = ! empty( $c['thieu'] );
				$them  = '';
				/* Dòng nhỏ 🌙 y như trên màn: `🌙` một mình = đêm đó có làm (công sang hôm sau) ·
				   `🌙 kèm số` = công đêm tính vào ngày này · `🌙0` = có làm mà không ra công. */
				if ( ! empty( $c['dem'] ) )          { $them .= '<div class="nho">🌙' . self::esc( self::so_cong( $c['dem'] ) ) . '</div>'; }
				elseif ( ! empty( $c['demHut'] ) )   { $them .= '<div class="nho thieu">🌙0</div>'; }
				if ( ! empty( $c['tuCoSo'] ) )       { $them .= '<div class="nho ghep">' . self::esc( (string) $c['tuCoSo'] ) . '</div>'; }
				$out[] = '<td class="c' . ( $thieu ? ' o-thieu' : '' ) . '">'
					. self::esc( $la_cong ? self::so_cong( $gia ) : self::so_cong( $gia ) . 'h' )
					. ( $thieu ? '<span class="thieu"> ?</span>' : '' ) . $them . '</td>';
			}
			$out[] = '<td class="tg b">' . self::esc( self::so_cong( $cong ) . ( $la_cong ? '' : 'h' ) )
				. '</td></tr>';
		}
		$out[] = '</tbody></table>';
		return implode( '', $out );
	}

	/**
	 * Danh sách THÁNG mà khoảng ngày này chạm tới — `['2026-08','2026-09']`.
	 *
	 * ⚠️ Đi theo mốc "ngày 1 của tháng", KHÔNG cộng 30 ngày một bước: cộng ngày thì tháng 2
	 *    nhảy qua mất tháng 3.
	 */
	public static function ds_thang( $tu, $den ) {
		$t1 = strtotime( (string) $tu . ' 00:00:00 UTC' );
		$t2 = strtotime( (string) $den . ' 00:00:00 UTC' );
		if ( false === $t1 || false === $t2 || $t2 < $t1 ) { return array(); }
		$ds  = array();
		$m   = strtotime( gmdate( 'Y-m', $t1 ) . '-01 00:00:00 UTC' );
		$het = strtotime( gmdate( 'Y-m', $t2 ) . '-01 00:00:00 UTC' );
		while ( $m <= $het ) {
			$ds[] = gmdate( 'Y-m', $m );
			$m = strtotime( '+1 month', $m );
		}
		return $ds;
	}

	/**
	 * Lưới của cơ sở tính THEO GIỜ: gom `chiTiet` về [mã trần][ngày] => số giờ.
	 *
	 * ⚠️ CỘNG CẢ HÀNG PHỤ (`-CD` ca đêm, `-TC` tăng cường) VÀO Ô CỦA NGƯỜI ẤY. Bảng tổng hợp
	 *    tách chúng thành dòng riêng vì mã in ra khác nhau, nhưng LƯỚI thì mỗi người một hàng —
	 *    để hàng phụ thành hàng riêng ở đây là một người hiện hai lần trên cùng tờ giấy.
	 */
	public static function luoi_gio_tu_chi_tiet( $chi_tiet ) {
		$o  = array();
		$co = array();
		foreach ( (array) $chi_tiet as $r ) {
			$ma = isset( $r['maTran'] ) ? (string) $r['maTran'] : (string) $r['ma'];
			$ng = (string) $r['ngay'];
			if ( ! isset( $o[ $ma ][ $ng ] ) ) { $o[ $ma ][ $ng ] = 0.0; }
			if ( isset( $r['phut'] ) && null !== $r['phut'] ) {
				$o[ $ma ][ $ng ] += round( ( (int) $r['phut'] ) / 60, 2 );
			}
			$thieu = ( ( '' !== trim( (string) $r['vao'] ) ) !== ( '' !== trim( (string) $r['ra'] ) ) );
			if ( $thieu || ! empty( $co[ $ma ][ $ng ]['thieu'] ) ) {
				$co[ $ma ][ $ng ] = array( 'thieu' => true );
			}
		}
		foreach ( $o as $ma => $ds ) {
			foreach ( $ds as $ng => $v ) { $o[ $ma ][ $ng ] = round( $v, 2 ); }
		}
		return array( $o, $co );
	}

	/** `2026-08` -> `08/2026`. Khuôn lạ thì GIỮ NGUYÊN, không tự đoán. */
	public static function thang_vn( $tt ) {
		$tt = (string) $tt;
		return preg_match( '/^(\d{4})-(\d{2})$/', $tt, $m ) ? $m[2] . '/' . $m[1] : $tt;
	}

	/** Số công in ra giấy: bỏ đuôi `.00`, giữ `.5`. Cùng thước với lưới web. */
	public static function so_cong( $v ) {
		$v = round( (float) $v, 2 );
		return rtrim( rtrim( number_format( $v, 2, '.', '' ), '0' ), '.' );
	}

	/** Cả tờ giấy, dạng HTML đứng một mình (in được ngay). */
	public static function trang_in( $coso, $tu, $den, $nguoi_xuat = '', $co_chi_tiet = false ) {
		$coso = trim( preg_replace( '/^CS_/', '', (string) $coso ) );
		$d = self::gom( $coso, $tu, $den );
		/* ⚠️ Gác `method_exists` CÙNG THÂN HÀM với lời gọi — luật của `kiem-goi-cheo.php`. */
		$la_cong = ( class_exists( 'VHCC_Luong' ) && method_exists( 'VHCC_Luong', 'cach_tinh' )
			&& 'cong' === VHCC_Luong::cach_tinh( $coso ) );
		$tc = $la_cong ? self::cong_theo_khoang( $coso, $tu, $den )
			: array( 'nguoi' => array(), 'o' => array(), 'co' => array() );
		$khoang = ( $den && $den !== $tu )
			? ( 'Từ ngày ' . self::ngay_vn( $tu ) . ' đến ngày ' . self::ngay_vn( $den ) )
			: ( 'Ngày ' . self::ngay_vn( $tu ) );
		$in_luc = gmdate( 'd/m/Y H:i', (int) current_time( 'timestamp' ) );
		$ten_tep = self::ten_tep( $coso, $tu, $den );

		$h = array();
		$h[] = '<!DOCTYPE html><html lang="vi"><head><meta charset="UTF-8">';
		$h[] = '<meta name="viewport" content="width=device-width,initial-scale=1">';
		$h[] = '<title>' . esc_html( $ten_tep ) . '</title><style>';
		/* 🔴 XOAY NGANG. Lưới cả tháng là 31 cột ngày + tên + tổng; nhét vào khổ dọc thì mỗi
		   cột còn chưa tới 5mm và con số dính vào nhau. Cả tờ xoay ngang chứ không riêng mục
		   lưới: `@page` không đổi hướng giữa chừng được, mà hai tờ hai hướng thì kẹp vào cặp
		   tài liệu cũng không ai lật nổi. */
		$h[] = '@page{size:A4 landscape;margin:8mm 8mm}';
		$h[] = 'body{font-family:Arial,Helvetica,sans-serif;color:#111;font-size:11px;margin:0;padding:10mm}';
		$h[] = 'h1{font-size:16px;margin:0 0 2px;text-align:center;text-transform:uppercase}';
		$h[] = '.cty{font-size:11px;font-weight:bold;text-align:center;margin:0}';
		$h[] = '.sub{font-size:11px;text-align:center;color:#333;margin:0 0 2px}';
		$h[] = '.meta{font-size:10px;color:#555;text-align:center;margin:0 0 10px}';
		$h[] = 'h2{font-size:12px;margin:14px 0 4px;border-bottom:1px solid #888;padding-bottom:2px}';
		$h[] = 'table{border-collapse:collapse;width:100%}';
		$h[] = 'th,td{border:1px solid #999;padding:3px 5px;font-size:10px}';
		$h[] = 'th{background:#e8eef5;font-weight:bold;text-align:center}';
		// Sang trang mới vẫn lặp lại dòng tiêu đề, và không cắt một hàng làm hai trang.
		$h[] = 'thead{display:table-header-group}';
		$h[] = 'tr{page-break-inside:avoid}';
		$h[] = '.c{text-align:center}.r{text-align:right}.b{font-weight:bold}';
		$h[] = '.thieu{color:#b00;font-weight:bold}';
		/* Hàng tên người: nền xám để tách khối, và KHÔNG được đứng lẻ ở cuối trang — một cái
		   tên nằm cuối tờ này còn các ngày của người ấy sang tờ sau là đúng thứ làm người đọc
		   tưởng người đó không có ngày nào. */
		$h[] = 'tr.nhom td{background:#dde5ee;border-top:2px solid #666}';
		$h[] = 'tr.nhom{page-break-after:avoid;page-break-inside:avoid}';
		$h[] = '.ghi{font-size:9px;color:#666;margin-top:6px}';
		/* Lưới: chữ nhỏ hơn hẳn hai bảng kia, và ô ngày bó sát — 31 cột phải vừa bề ngang tờ
		   giấy mà vẫn đọc được con số. */
		/* 🔴 `table-layout:fixed` — MỌI CỘT NGÀY BẰNG NHAU. Để trình duyệt tự co giãn thì ô nào
		   có nhãn cơ sở ghép (`SETUP_VP`, 9 ký tự) kéo rộng gấp đôi, và cả bảng lệch nhịp: mắt
		   dò theo cột số 17 sẽ trượt sang 18. Cột rộng bằng nhau thì dò dọc mới tin được. */
		$h[] = 'table.luoi{table-layout:fixed}';
		$h[] = 'table.luoi th,table.luoi td{padding:1px 1px;font-size:8.5px;text-align:center;'
			. 'overflow:hidden}';
		$h[] = 'table.luoi th.ten,table.luoi td.ten{width:104px}';
		$h[] = 'table.luoi th.tg,table.luoi td.tg{width:32px}';
		$h[] = 'table.luoi td.ten,table.luoi th.ten{text-align:left;white-space:nowrap;'
			. 'max-width:120px;overflow:hidden;text-overflow:ellipsis;font-size:9px}';
		$h[] = 'table.luoi .thu{font-weight:400;font-size:7px;color:#555}';
		$h[] = 'table.luoi th.cn{background:#f6dede}';   // cuối tuần: nền khác, in đen trắng vẫn thấy
		$h[] = 'table.luoi td.o-thieu{background:#fdeaea}';
		$h[] = 'table.luoi .nho{font-size:6.5px;line-height:1.1;color:#444;white-space:nowrap;'
			. 'overflow:hidden;text-overflow:ellipsis}';
		$h[] = 'table.luoi .ghep{background:#e6f0fb}';
		$h[] = 'table.luoi td.tg,table.luoi th.tg{background:#eef2f7}';
		$h[] = '.ky{margin-top:22px;width:100%}';
		$h[] = '.ky td{border:none;text-align:center;font-size:10px;padding-top:4px}';
		/* Thanh nút chỉ có trên màn hình, KHÔNG in ra giấy. */
		$h[] = '.thanh{position:sticky;top:0;background:#fffbe6;border:1px solid #e0c86a;padding:8px 10px;'
			. 'margin:-6mm -6mm 10px;font-size:12px;border-radius:4px}';
		$h[] = '.thanh button{font-size:13px;padding:5px 14px;cursor:pointer}';
		$h[] = '@media print{.thanh{display:none}body{padding:0}}';
		$h[] = '</style></head><body>';

		$h[] = '<div class="thanh"><button onclick="window.print()">In / Lưu thành PDF</button> '
			. 'Trong hộp thoại in, chọn <b>Đích: Lưu thành PDF</b> và khổ <b>A4</b>. '
			. 'Tên tệp gợi ý: <code>' . esc_html( $ten_tep ) . '.pdf</code></div>';

		$h[] = '<p class="cty">' . self::esc( self::ten_cong_ty() ) . '</p>';
		$h[] = '<h1>Bảng chấm công</h1>';
		$h[] = '<p class="sub">Cơ sở: <b>' . self::esc( $coso ) . '</b></p>';
		$h[] = '<p class="sub">' . self::esc( $khoang ) . '</p>';
		$h[] = '<p class="meta">In lúc ' . self::esc( $in_luc )
			. ( '' !== $nguoi_xuat ? ' · người xuất: ' . self::esc( $nguoi_xuat ) : '' ) . '</p>';

		$h[] = '<h2>1. Tổng hợp theo nhân viên</h2>';
		/* 🔴 ĐƠN VỊ CỦA TỜ GIẤY THEO CÁCH TÍNH CỦA CƠ SỞ, KHÔNG PHẢI MỘT KIỂU CHO TẤT CẢ.
		   Anh Thắng 31/08/2026: *"Cơ sở đã set công, tại sao lại xuất ra giờ làm chi đâu, không
		   cần thiết, cần số công chính xác như wed"*. Cơ sở khai tính THEO CÔNG thì tờ giấy in
		   SỐ CÔNG, lấy thẳng từ engine đã dựng nên lưới web — một nguồn, một con số. Cơ sở tính
		   theo giờ vẫn in giờ như cũ: ở đó giờ MỚI là đơn vị trả lương. */
		if ( $la_cong ) {
			$h[] = '<table><thead><tr><th style="width:70px">Mã NV</th><th>Họ và tên</th>'
				. '<th style="width:70px">Số công</th><th style="width:70px">Thiếu giờ ra</th>'
				. '</tr></thead><tbody>';
			if ( ! $tc['nguoi'] ) {
				$h[] = '<tr><td colspan="4" class="c">(Không có dữ liệu)</td></tr>';
			}
			$tong_cong = 0.0;
			foreach ( $tc['nguoi'] as $r ) {
				$tong_cong += (float) $r['cong'];
				$h[] = '<tr><td class="c">' . self::esc( $r['ma'] ) . '</td><td>' . self::esc( $r['ten'] ) . '</td>'
					. '<td class="c b">' . self::esc( self::so_cong( $r['cong'] ) ) . '</td>'
					. '<td class="c' . ( $r['thieuRa'] ? ' thieu' : '' ) . '">' . (int) $r['thieuRa'] . '</td></tr>';
			}
			if ( $tc['nguoi'] ) {
				$h[] = '<tr><td colspan="2" class="r b">CỘNG</td>'
					. '<td class="c b">' . self::esc( self::so_cong( $tong_cong ) ) . '</td><td></td></tr>';
			}
			$h[] = '</tbody></table>';
			/* Nói thẳng ra con số này ở đâu mà có — người cầm tờ giấy phải kiểm lại được. */
			$h[] = '<p class="ghi">Cột <b>Số công</b> là <b>số công đã tính</b> theo cách tính đã khai '
				. 'cho cơ sở này (khung giờ, tăng ca, ca đêm, công bù) — <b>đúng con số ở lưới cả '
				. 'tháng trên trang quản trị</b>, không phải số ngày có bấm máy. Ngày <b>thiếu một '
				. 'đầu giờ</b> (chỉ bấm vào, hoặc chỉ bấm ra) thì <b>không ra công</b>; cột Thiếu '
				. 'giờ ra đếm đúng những ngày ấy — bù nốt giờ còn thiếu là công lên ngay.</p>';
		} else {
			$h[] = '<table><thead><tr><th style="width:70px">Mã NV</th><th>Họ và tên</th>'
				. '<th style="width:60px">Ngày công</th><th style="width:70px">Thiếu giờ ra</th>'
				. '<th style="width:110px">Tổng giờ làm</th></tr></thead><tbody>';
			if ( ! $d['tongHop'] ) {
				$h[] = '<tr><td colspan="5" class="c">(Không có dữ liệu)</td></tr>';
			}
			foreach ( $d['tongHop'] as $r ) {
				$h[] = '<tr><td class="c">' . self::esc( $r['ma'] ) . '</td><td>' . self::esc( $r['ten'] ) . '</td>'
					. '<td class="c">' . (int) $r['soNgay'] . '</td>'
					. '<td class="c' . ( $r['thieuRa'] ? ' thieu' : '' ) . '">' . (int) $r['thieuRa'] . '</td>'
					. '<td class="r b">' . esc_html( number_format( $r['phut'] / 60, 2 ) ) . 'h</td></tr>';
			}
			$h[] = '</tbody></table>';
		}

		/* =====================================================================================
		 * 🔴 GOM THEO NGƯỜI, KHÔNG PHẢI THEO NGÀY.
		 * =====================================================================================
		 * Anh Thắng 28/08/2026: *"in theo ngày hàng dọc theo 1 nhân viên cho dễ nhìn nhé em"*.
		 *
		 * Bản cũ xếp theo NGÀY, nên mỗi ngày là một cụm lẫn lộn hai chục người, và muốn xem một
		 * người làm những ngày nào thì phải rà mắt qua cả 355 dòng nhặt ra tên ấy. Tờ giấy này
		 * đưa cho NHÂN VIÊN KÝ — mà chữ ký là ký vào công của CHÍNH MÌNH, nên các ngày của một
		 * người phải nằm liền một khối.
		 *
		 * ⚠️ Bỏ hai cột Mã NV / Họ tên ở từng dòng: gom rồi thì mỗi dòng lặp lại tên là thừa,
		 *    mà tờ A4 thì hết chỗ. Tên nằm ở hàng đầu mỗi khối, in đậm.
		 */
		/* =====================================================================================
		 * 🔴 LƯỚI CẢ THÁNG — anh Thắng 31/08/2026 gửi ảnh chính cái lưới trên màn: *"thiếu bảng
		 *    này"*, và chốt *"đúng, cùng hàng nhân viên luôn"*.
		 * =====================================================================================
		 * Bảng tổng hợp cho MỘT con số mỗi người; lưới cho biết con số ấy ghép từ những ngày
		 * nào. Kế toán đối soát bằng lưới, nên nó phải có mặt trên tờ giấy đem đi ký.
		 *
		 * ⚠️ MỖI THÁNG MỘT LƯỚI. Lưới là lưới THÁNG (cột 1..31); in khoảng bắc qua hai tháng mà
		 *    ép vào một bảng thì hoặc mất nửa sau, hoặc cột ngày mang hai nghĩa.
		 */
		$ds_thang_in = self::ds_thang( $tu, $den );
		if ( $la_cong ) {
			$luoi_o  = $tc['o'];
			$luoi_co = $tc['co'];
			$luoi_ng = array_values( $tc['nguoi'] );
		} else {
			list( $luoi_o, $luoi_co ) = self::luoi_gio_tu_chi_tiet( $d['chiTiet'] );
			/* 🔴 MỖI NGƯỜI ĐÚNG MỘT HÀNG. `tongHop` tách hàng phụ (`MÃ-CD`, `MÃ-TC`) thành dòng
			   riêng vì mã in ra khác nhau — bê nguyên sang lưới là một người hiện hai lần. */
			$luoi_ng = array();
			$da_ma   = array();
			foreach ( $d['tongHop'] as $r_l ) {
				$ph = explode( '-', (string) $r_l['ma'], 2 );
				if ( isset( $da_ma[ $ph[0] ] ) ) { continue; }
				$da_ma[ $ph[0] ] = 1;
				$luoi_ng[] = array( 'ma' => $ph[0], 'ten' => (string) $r_l['ten'] );
			}
		}
		$so_muc = 1;
		foreach ( $ds_thang_in as $tt_in ) {
			$so_muc++;
			$h[] = '<h2>' . $so_muc . '. Lưới cả tháng ' . self::esc( self::thang_vn( $tt_in ) )
				. ' — mỗi ô là ' . ( $la_cong ? 'số công' : 'số giờ' ) . ' của ngày đó</h2>';
			$h[] = self::luoi_in( $tt_in, $luoi_ng, $luoi_o, $luoi_co, $la_cong );
		}
		if ( $ds_thang_in ) {
			$h[] = '<p class="ghi">Ô <b>trống</b> = ngày ấy không có dữ liệu chấm công · ô <b>0</b> '
				. '= có đi làm mà không ra công · dấu <b>?</b> và nền hồng = <b>thiếu một đầu giờ</b> '
				. '(chỉ bấm vào, hoặc chỉ bấm ra) nên ngày ấy không tính công — bù nốt giờ còn thiếu '
				. 'là công lên ngay. Dòng nhỏ <b>🌙</b> là phần ca đêm; <b>🌙0</b> = đêm đó có làm mà '
				. 'không ra công. Nhãn <b>nền xanh nhạt</b> là ngày chấm ở cơ sở đã ghép vào bảng này '
				. '— công của nó ĐÃ nằm trong cột TỔNG.</p>';
		}

		/* 🔴 MỤC CHI TIẾT TỪNG NGÀY: MẶC ĐỊNH KHÔNG IN.
		   Anh Thắng 31/08/2026: *"Với bảng chi tiết theo từng nhân viên không cần thiết, vì kế
		   toán đối soát rồi"*. Đúng: khúc ấy là 300-400 dòng, chiếm gần hết tập giấy, mà việc nó
		   phục vụ — dò lại từng lượt bấm — đã làm xong ở màn quản trị trước khi in.
		   ⚠️ GIỮ ĐƯỜNG BẬT LẠI (`&ct=1`) chứ không xoá hàm: ngày có người khiếu nại một ngày cụ
		      thể thì tờ giấy có chi tiết là thứ đưa ra được. Bỏ hẳn thì lúc ấy phải viết lại. */
		if ( $co_chi_tiet ) {
			$h[] = '<h2>' . ( $so_muc + 1 ) . '. Chi tiết theo từng nhân viên</h2>';
			$h[] = self::khoi_theo_nguoi( $d['tongHop'], $d['chiTiet'], $la_cong ? $tc['o'] : null );
		}

		/* Cắt bớt thì phải IN HẲN lên giấy. Cắt im lặng là tờ giấy trông đầy đủ trong khi thiếu
		   người. ⚠️ Hai câu này nói về MỤC CHI TIẾT — không in mục ấy thì đừng doạ người đọc về
		   một khúc không có trên giấy. */
		if ( $co_chi_tiet && $d['biCat'] ) {
			$h[] = '<p class="ghi thieu">⚠ Kỳ này có ' . (int) $d['soChiTiet'] . ' dòng, nhiều hơn '
				. self::MAX_CHI_TIET . ' nên phần chi tiết ĐÃ BỊ CẮT BỚT. Hãy in theo tuần để có đủ.</p>';
		}
		/* Mục 1 của cơ sở tính THEO CÔNG dựng từ engine, KHÔNG đi qua `MAX_TONG_HOP` — nên câu
		   cảnh báo ấy chỉ đúng ở nhánh theo giờ. */
		if ( ! $la_cong && $d['biCatTong'] ) {
			$h[] = '<p class="ghi thieu">⚠ Phần tổng hợp cũng bị cắt vì quá ' . self::MAX_TONG_HOP
				. ' người.</p>';
		}
		if ( $co_chi_tiet ) {
			$h[] = '<p class="ghi">Dòng ghi "THIẾU" ở cột Giờ ra là quên check-out — cần bổ sung trước '
				. 'khi chốt công.</p>';
		}

		$h[] = '<table class="ky"><tr>'
			. '<td style="width:50%"><b>NHÂN VIÊN XÁC NHẬN</b><br>(ký, ghi rõ họ tên)<br><br><br><br></td>'
			. '<td style="width:50%"><b>CỬA HÀNG TRƯỞNG</b><br>(ký, ghi rõ họ tên)<br><br><br><br></td>'
			. '</tr></table>';
		$h[] = '</body></html>';
		return implode( '', $h );
	}

	/**
	 * PHẦN CHI TIẾT CỦA TỜ IN, GOM THEO NGƯỜI.
	 *
	 * Tách thành hàm THUẦN (vào: hai mảng · ra: một chuỗi) để thử được bằng dữ liệu trần —
	 * cảnh "mục 1 bị cắt vì quá 500 người" mà phải dựng 500 hồ sơ thật thì không ai thử, và
	 * đúng cái nhánh ấy là nhánh dễ bỏ sót người nhất.
	 */
	public static function khoi_theo_nguoi( $tong_hop, $chi_tiet, $cong_o = null ) {
		$out = array();
		/* Thứ tự người ĐI THEO MỤC 1, không sắp lại: hai mục trên cùng tờ giấy mà xếp khác nhau
		   thì người đọc phải dò lại từ đầu mỗi lần liếc qua liếc lại. */
		$thu_tu = array();
		$ten_cua = array();
		$phut_cua = array();
		foreach ( $tong_hop as $r ) {
			$k = (string) $r['ma'];
			$thu_tu[]        = $k;
			$ten_cua[ $k ]   = (string) $r['ten'];
			$phut_cua[ $k ]  = (int) $r['phut'];
		}
		$theo_ma = array();
		foreach ( $chi_tiet as $r ) {
			$theo_ma[ (string) $r['ma'] ][] = $r;
		}
		/* Người có dòng chi tiết mà KHÔNG có trong mục 1 (mục 1 bị cắt vì quá đông) vẫn phải in
		   ra — cắt im lặng là tờ giấy trông đầy đủ trong khi thiếu người. */
		foreach ( array_keys( $theo_ma ) as $k ) {
			if ( ! in_array( $k, $thu_tu, true ) ) { $thu_tu[] = $k; }
		}

		/* 🔴 CỘT THỨ TƯ ĐỔI THEO ĐƠN VỊ CỦA CƠ SỞ. Cơ sở tính theo công mà mục 2 vẫn in giờ thô
		   thì hai mục trên cùng tờ giấy nói hai đơn vị, và người ký không biết mình ký vào cái
		   nào. `$cong_o` là bảng [mã trần][ngày] => công, lấy từ chính engine dựng nên lưới web. */
		$la_cong = is_array( $cong_o );
		$out[] = '<table><thead><tr><th style="width:80px">Ngày</th>'
			. '<th style="width:80px">Giờ vào</th><th style="width:80px">Giờ ra</th>'
			. '<th style="width:80px">' . ( $la_cong ? 'Công' : 'Giờ làm' ) . '</th>'
			. '<th>Ghi chú</th></tr></thead><tbody>';
		if ( ! $chi_tiet ) {
			$out[] = '<tr><td colspan="5" class="c">(Không có dữ liệu)</td></tr>';
		}
		foreach ( $thu_tu as $ma_ng ) {
			if ( empty( $theo_ma[ $ma_ng ] ) ) { continue; }
			$ten_ng = isset( $ten_cua[ $ma_ng ] ) ? $ten_cua[ $ma_ng ]
				: (string) $theo_ma[ $ma_ng ][0]['ten'];
			$tong_ng = isset( $phut_cua[ $ma_ng ] ) ? $phut_cua[ $ma_ng ] : 0;
			/* Khối của HÀNG PHỤ (`MÃ-CD`, `MÃ-TC`) — engine gom công của nó vào người ở dòng
			   chính, nên ở đây KHÔNG in lại số công: in lại là người đọc cộng hai lần. */
			$phan  = explode( '-', $ma_ng, 2 );
			$ma_tran = $phan[0];
			$la_phu  = ( count( $phan ) > 1 );
			$cong_ng = null;
			if ( $la_cong && ! $la_phu && isset( $cong_o[ $ma_tran ] ) ) {
				$cong_ng = 0.0;
				foreach ( $theo_ma[ $ma_ng ] as $r_c ) {
					$n_c = (string) $r_c['ngay'];
					if ( isset( $cong_o[ $ma_tran ][ $n_c ] ) ) { $cong_ng += (float) $cong_o[ $ma_tran ][ $n_c ]; }
				}
			}
			$out[] = '<tr class="nhom"><td colspan="3"><b>' . self::esc( $ten_ng ) . '</b> · '
				. self::esc( $ma_ng )
				. ( $la_cong && $la_phu ? ' <i>(hàng phụ — công đã tính vào dòng chính)</i>' : '' ) . '</td>'
				. '<td class="c b">'
				. ( $la_cong
					? ( null === $cong_ng ? '' : self::esc( self::so_cong( $cong_ng ) ) )
					: esc_html( number_format( $tong_ng / 60, 2 ) ) . 'h' )
				. '</td>'
				. '<td class="c">' . count( $theo_ma[ $ma_ng ] ) . ' ngày</td></tr>';
			foreach ( $theo_ma[ $ma_ng ] as $r ) {
				$co_ra = '' !== trim( (string) $r['ra'] );
				$n_o   = (string) $r['ngay'];
				if ( $la_cong ) {
					/* Ngày KHÔNG có trong bảng công (engine bỏ vì hậu tố lạ, hoặc ngày ấy 0 công
					   nên không có dòng) thì ô để TRỐNG chứ không in số 0 bịa ra. */
					$o_cong = ( ! $la_phu && isset( $cong_o[ $ma_tran ][ $n_o ] ) )
						? self::esc( self::so_cong( $cong_o[ $ma_tran ][ $n_o ] ) ) : '';
				} else {
					$o_cong = self::esc( $r['gio'] );
				}
				$out[] = '<tr><td class="c">' . self::esc( self::ngay_vn( $r['ngay'] ) ) . '</td>'
					. '<td class="c">' . self::esc( $r['vao'] ) . '</td>'
					. '<td class="c' . ( $co_ra ? '' : ' thieu' ) . '">'
					. ( $co_ra ? self::esc( $r['ra'] ) : 'THIẾU' ) . '</td>'
					. '<td class="c">' . $o_cong . '</td>'
					. '<td>' . ( $co_ra ? '' : 'Quên check-out' ) . '</td></tr>';
			}
		}
		$out[] = '</tbody></table>';
		return implode( '', $out );
	}

}
