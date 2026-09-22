<?php
/**
 * Dán thô — copy nguyên cả sheet, máy tự nhận là file gì và tự lấy đúng cột.
 *
 * VÌ SAO CẦN: mỗi cổng xuất một kiểu file, cột nằm mỗi chỗ một khác. Bắt kế
 * toán sắp lại cột trong Excel cho khớp ô dán là việc thừa làm mỗi tháng, và
 * là chỗ sai lặng lẽ: kéo nhầm một cột thì số vẫn vào sổ, chỉ là vào sai chỗ.
 *
 * Nhận dạng bằng DÒNG TIÊU ĐỀ, không phải bằng tên tệp hay thứ tự cột. Tên
 * tệp thì người dùng đổi; thứ tự cột thì cổng đổi khi nâng cấp. Tiêu đề là thứ
 * ổn định nhất, và nếu cổng có đổi thật thì máy báo "không nhận ra" — thà thế
 * còn hơn lấy nhầm cột rồi im lặng.
 *
 * KHÔNG tự ghi thẳng vào sổ. Nhận dạng xong thì trả về bảng đã chuẩn hoá để
 * màn hình cho xem trước; ghi hay không là người dùng bấm.
 *
 * @package KHTC
 */

defined( 'ABSPATH' ) || exit;

class KHTC_DanTho {

	/**
	 * Các định dạng nhận được.
	 *
	 *   dau_hieu : những chữ PHẢI có trong dòng tiêu đề, đủ cả mới tính là khớp
	 *   dich     : 'sao_ke' (vào bảng giao dịch) | 'cong' (vào một đợt đối soát)
	 *   cot      : chỉ số cột 0-based, lấy từ chính tệp thật của công ty
	 *   loc      : [cột, giá trị] — chỉ nhận dòng có đúng giá trị đó
	 */
	public static function dinh_dang() {
		return array(
			'qr' => array(
				'ten'      => 'Sao kê QR ngân hàng',
				'dau_hieu' => array( 'mã tham chiếu', 'số tiền đến' ),
				'dich'     => 'sao_ke',
				'loc'      => array( 5, 'thành công' ),
				'cot'      => array(
					'ngay'        => 1,
					'thu'         => 2,
					'chi'         => 3,
					'ma_gd'       => 6,
					'ma_cua_hang' => 9,
					'dien_giai'   => 12,
				),
			),
			'payoo' => array(
				'ten'      => 'Payoo',
				'dau_hieu' => array( 'số tiền thanh toán', 'phí xử lý giao dịch' ),
				'dich'     => 'cong',
				'cot'      => array(
					'ngay'        => 2,
					'ma_gd'       => 13,
					'thu'         => 21,
					'phi'         => 22,
					'ma_cua_hang' => 1,
					'dien_giai'   => 1,
				),
			),
			'vnpay' => array(
				'ten'      => 'VNPay',
				'dau_hieu' => array( 'mã điểm thu', 'điểm thu' ),
				'dich'     => 'cong',
				'cot'      => array(
					'ngay'        => 2,
					'ma_gd'       => 3,
					'thu'         => 22,
					'phi'         => 23,
					'ma_cua_hang' => 5,
					'dien_giai'   => 6,
				),
			),
			'momo' => array(
				'ten'      => 'MoMo',
				'dau_hieu' => array( 'ms.transid', 'ms.total amount' ),
				'dich'     => 'cong',
				'loc'      => array( 18, 'thành công' ),
				'cot'      => array(
					'ngay'        => 24,
					'ma_gd'       => 25,
					'thu'         => 5,
					'ma_cua_hang' => 14,
					'dien_giai'   => 14,
				),
			),
		);
	}

	/** Cắt dòng thành ô. Tab (copy từ Excel) hoặc dấu phẩy. */
	private static function o( $d ) {
		return ( strpos( $d, "\t" ) !== false ) ? explode( "\t", $d ) : str_getcsv( $d, ',', '"', '' );
	}

	/**
	 * Tìm dòng tiêu đề và cho biết đó là định dạng nào.
	 *
	 * Quét 12 dòng đầu chứ không chỉ dòng 1: tệp thật của công ty có một hai
	 * dòng tổng cộng nằm TRÊN tiêu đề, copy cả sheet là dính theo.
	 *
	 * @return array|null [khoá, định dạng, số dòng tiêu đề (0-based)]
	 */
	public static function nhan_dang( $text ) {
		$dong = preg_split( '/\r\n|\r|\n/', (string) $text );
		foreach ( array_slice( $dong, 0, 12 ) as $i => $d ) {
			$thap = mb_strtolower( $d );
			foreach ( self::dinh_dang() as $khoa => $dd ) {
				$du = true;
				foreach ( $dd['dau_hieu'] as $dh ) {
					if ( false === mb_strpos( $thap, $dh ) ) { $du = false; break; }
				}
				if ( $du ) { return array( $khoa, $dd, $i ); }
			}
		}
		return null;
	}

	/**
	 * Đọc bảng dán thô thành các dòng đã chuẩn hoá.
	 *
	 * @return array|WP_Error [dinh_dang, ten, dich, rows, bo_loc, thieu_cot, tong, tong_phi]
	 */
	public static function doc( $text ) {
		$nd = self::nhan_dang( $text );
		if ( ! $nd ) {
			return new WP_Error(
				'khong_nhan',
				'Không nhận ra đây là tệp gì. Nhớ copy cả DÒNG TIÊU ĐỀ (dòng có chữ "Mã tham chiếu", "Số tiền thanh toán", "Mã điểm thu" hoặc "MS.TransID"), không chỉ copy phần số.'
			);
		}
		list( $khoa, $dd, $i_tieu_de ) = $nd;
		$c = $dd['cot'];

		$rows      = array();
		$bo_loc    = 0;   // dòng bị loại vì trạng thái không phải "Thành công"
		$thieu_cot = 0;   // dòng ngắn hơn tiêu đề, thường là dòng tổng cuối bảng
		$tong      = 0;
		$tong_phi  = 0;

		$dong = preg_split( '/\r\n|\r|\n/', (string) $text );
		foreach ( array_slice( $dong, $i_tieu_de + 1 ) as $d ) {
			if ( '' === trim( $d ) ) { continue; }
			$o = array_map( 'trim', self::o( $d ) );

			$can = max( $c ) ;
			if ( count( $o ) <= $can ) { $thieu_cot++; continue; }

			if ( isset( $dd['loc'] ) ) {
				list( $cot_loc, $gia_tri ) = $dd['loc'];
				if ( mb_strtolower( (string) ( $o[ $cot_loc ] ?? '' ) ) !== $gia_tri ) { $bo_loc++; continue; }
			}

			$ngay = KHTC_GiaoDich::doc_ngay( $o[ $c['ngay'] ] );
			if ( '' === $ngay ) { $thieu_cot++; continue; }

			$thu = KHTC_GiaoDich::doc_so( $o[ $c['thu'] ] ?? 0 );
			$chi = isset( $c['chi'] ) ? KHTC_GiaoDich::doc_so( $o[ $c['chi'] ] ?? 0 ) : 0;
			if ( ! $thu && ! $chi ) { continue; }

			$hang = array(
				'ngay'        => mysql2date( 'd/m/Y', $ngay ),
				'dien_giai'   => (string) ( $o[ $c['dien_giai'] ] ?? '' ),
				'so_tien'     => $thu ? $thu : $chi,
				'loai'        => $thu ? 'thu' : 'chi',
				'ma_gd'       => (string) ( $o[ $c['ma_gd'] ] ?? '' ),
				'ma_cua_hang' => (string) ( $o[ $c['ma_cua_hang'] ] ?? '' ),
				'phi'         => isset( $c['phi'] ) ? KHTC_GiaoDich::doc_so( $o[ $c['phi'] ] ?? 0 ) : 0,
			);
			$tong     += $hang['so_tien'];
			$tong_phi += $hang['phi'];
			$rows[]    = $hang;
		}

		return array(
			'dinh_dang' => $khoa,
			'ten'       => $dd['ten'],
			'dich'      => $dd['dich'],
			'rows'      => $rows,
			'bo_loc'    => $bo_loc,
			'thieu_cot' => $thieu_cot,
			'tong'      => $tong,
			'tong_phi'  => $tong_phi,
		);
	}

	/** Đổi các dòng đã chuẩn hoá thành bảng dán cho màn hình Sao kê. */
	public static function ra_sao_ke( $rows ) {
		$ra = array();
		foreach ( $rows as $r ) {
			$ra[] = implode( "\t", array( $r['ngay'], $r['dien_giai'], $r['so_tien'], $r['loai'], $r['ma_gd'], $r['ma_cua_hang'] ) );
		}
		return implode( "\n", $ra );
	}

	/** Đổi thành bảng dán cho một đợt đối soát. */
	public static function ra_cong( $rows ) {
		$ra = array();
		foreach ( $rows as $r ) {
			$ra[] = implode( "\t", array( $r['ngay'], $r['ma_gd'], $r['so_tien'], $r['phi'], $r['dien_giai'], $r['ma_cua_hang'] ) );
		}
		return implode( "\n", $ra );
	}
}
