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

	/** Cả tờ giấy, dạng HTML đứng một mình (in được ngay). */
	public static function trang_in( $coso, $tu, $den, $nguoi_xuat = '' ) {
		$coso = trim( preg_replace( '/^CS_/', '', (string) $coso ) );
		$d = self::gom( $coso, $tu, $den );
		$khoang = ( $den && $den !== $tu )
			? ( 'Từ ngày ' . self::ngay_vn( $tu ) . ' đến ngày ' . self::ngay_vn( $den ) )
			: ( 'Ngày ' . self::ngay_vn( $tu ) );
		$in_luc = gmdate( 'd/m/Y H:i', (int) current_time( 'timestamp' ) );
		$ten_tep = self::ten_tep( $coso, $tu, $den );

		$h = array();
		$h[] = '<!DOCTYPE html><html lang="vi"><head><meta charset="UTF-8">';
		$h[] = '<meta name="viewport" content="width=device-width,initial-scale=1">';
		$h[] = '<title>' . esc_html( $ten_tep ) . '</title><style>';
		$h[] = '@page{size:A4;margin:12mm 10mm}';
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
		$h[] = '<h2>2. Chi tiết theo từng nhân viên</h2>';
		$h[] = self::khoi_theo_nguoi( $d['tongHop'], $d['chiTiet'] );

		/* Cắt bớt thì phải IN HẲN lên giấy. Cắt im lặng là tờ giấy trông đầy đủ trong khi thiếu người. */
		if ( $d['biCat'] ) {
			$h[] = '<p class="ghi thieu">⚠ Kỳ này có ' . (int) $d['soChiTiet'] . ' dòng, nhiều hơn '
				. self::MAX_CHI_TIET . ' nên phần chi tiết ĐÃ BỊ CẮT BỚT. Hãy in theo tuần để có đủ.</p>';
		}
		if ( $d['biCatTong'] ) {
			$h[] = '<p class="ghi thieu">⚠ Phần tổng hợp cũng bị cắt vì quá ' . self::MAX_TONG_HOP
				. ' người.</p>';
		}
		$h[] = '<p class="ghi">Dòng ghi "THIẾU" ở cột Giờ ra là quên check-out — cần bổ sung trước '
			. 'khi chốt công.</p>';

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
	public static function khoi_theo_nguoi( $tong_hop, $chi_tiet ) {
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

		$out[] = '<table><thead><tr><th style="width:80px">Ngày</th>'
			. '<th style="width:80px">Giờ vào</th><th style="width:80px">Giờ ra</th>'
			. '<th style="width:80px">Giờ làm</th><th>Ghi chú</th></tr></thead><tbody>';
		if ( ! $chi_tiet ) {
			$out[] = '<tr><td colspan="5" class="c">(Không có dữ liệu)</td></tr>';
		}
		foreach ( $thu_tu as $ma_ng ) {
			if ( empty( $theo_ma[ $ma_ng ] ) ) { continue; }
			$ten_ng = isset( $ten_cua[ $ma_ng ] ) ? $ten_cua[ $ma_ng ]
				: (string) $theo_ma[ $ma_ng ][0]['ten'];
			$tong_ng = isset( $phut_cua[ $ma_ng ] ) ? $phut_cua[ $ma_ng ] : 0;
			$out[] = '<tr class="nhom"><td colspan="3"><b>' . self::esc( $ten_ng ) . '</b> · '
				. self::esc( $ma_ng ) . '</td>'
				. '<td class="c b">' . esc_html( number_format( $tong_ng / 60, 2 ) ) . 'h</td>'
				. '<td class="c">' . count( $theo_ma[ $ma_ng ] ) . ' ngày</td></tr>';
			foreach ( $theo_ma[ $ma_ng ] as $r ) {
				$co_ra = '' !== trim( (string) $r['ra'] );
				$out[] = '<tr><td class="c">' . self::esc( self::ngay_vn( $r['ngay'] ) ) . '</td>'
					. '<td class="c">' . self::esc( $r['vao'] ) . '</td>'
					. '<td class="c' . ( $co_ra ? '' : ' thieu' ) . '">'
					. ( $co_ra ? self::esc( $r['ra'] ) : 'THIẾU' ) . '</td>'
					. '<td class="c">' . self::esc( $r['gio'] ) . '</td>'
					. '<td>' . ( $co_ra ? '' : 'Quên check-out' ) . '</td></tr>';
			}
		}
		$out[] = '</tbody></table>';
		return implode( '', $out );
	}

}
