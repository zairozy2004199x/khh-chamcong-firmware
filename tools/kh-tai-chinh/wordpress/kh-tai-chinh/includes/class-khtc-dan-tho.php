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
	 *   dau_hieu : những chữ PHẢI có trong dòng tiêu đề, đủ cả mới tính là khớp.
	 *              Một cổng xuất được nhiều kiểu file (MoMo: bản "MS.*" từ cổng
	 *              đối soát và bản "Transaction report" từ MoMo Business) thì
	 *              ghi nhiều bộ dấu hiệu, khớp bộ nào cũng nhận.
	 *   dich     : 'sao_ke' (vào bảng giao dịch) | 'cong' (vào một đợt đối soát)
	 *   ten_cot  : trường → các TÊN TIÊU ĐỀ có thể mang trường đó, ưu tiên theo
	 *              thứ tự. Cột được tìm THEO TÊN, không theo vị trí: kế toán hay
	 *              chèn thêm cột "Lọc ngày" vào file gốc, và file gốc tải thẳng từ
	 *              cổng thì không có cột đó — cùng một cổng, hai file, cột lệch
	 *              nhau một. Theo vị trí thì một trong hai đọc sai hết.
	 *   cot      : chỉ số cột 0-based dự phòng, chỉ dùng khi không thấy tên
	 *   loc      : [tên cột, giá trị] — chỉ nhận dòng có đúng giá trị đó
	 */
	public static function dinh_dang() {
		return array(
			'qr' => array(
				'ten'      => 'Sao kê QR ngân hàng',
				'dau_hieu' => array( 'mã tham chiếu', 'số tiền đến' ),
				'dich'     => 'sao_ke',
				'loc'      => array( 'trạng thái', 'thành công' ),
				'ten_cot'  => array(
					'ngay'        => array( 'thời gian tt', 'thời gian thanh toán', 'thời gian tạo' ),
					'thu'         => array( 'số tiền đến' ),
					'chi'         => array( 'số tiền đi' ),
					'ma_gd'       => array( 'mã tham chiếu' ),
					'ma_cua_hang' => array( 'mã cửa hàng' ),
					'dien_giai'   => array( 'nội dung tt', 'nội dung thanh toán', 'nội dung' ),
				),
				'cot'      => array( 'ngay' => 1, 'thu' => 2, 'chi' => 3, 'ma_gd' => 6, 'ma_cua_hang' => 9, 'dien_giai' => 12 ),
			),
			'payoo' => array(
				'ten'      => 'Payoo',
				'dau_hieu' => array( 'số tiền thanh toán', 'phí xử lý giao dịch' ),
				'dich'     => 'cong',
				'ten_cot'  => array(
					'ngay'        => array( 'ngày giao dịch' ),
					'ma_gd'       => array( 'số hóa đơn', 'số hoá đơn' ),
					'thu'         => array( 'số tiền thanh toán' ),
					'phi'         => array( 'phí xử lý giao dịch' ),
					'ma_cua_hang' => array( 'cửa hàng' ),
					'dien_giai'   => array( 'cửa hàng' ),
				),
				'cot'      => array( 'ngay' => 2, 'ma_gd' => 13, 'thu' => 21, 'phi' => 22, 'ma_cua_hang' => 1, 'dien_giai' => 1 ),
			),
			'vnpay' => array(
				'ten'      => 'VNPay',
				'dau_hieu' => array( 'mã điểm thu', 'điểm thu' ),
				'dich'     => 'cong',
				'loc'      => array( 'trạng thái', 'thành công' ),
				'ten_cot'  => array(
					'ngay'        => array( 'thời gian gd', 'thời gian giao dịch' ),
					'ma_gd'       => array( 'mã giao dịch' ),
					// Trước KM: cổng vẫn hạch toán trả đủ số này cho mình khi có khuyến mại
					// (KM là tiền cổng bỏ ra) — soi trên file thật 23/09/2026.
					'thu'         => array( 'số tiền trước km', 'số tiền hạch toán thu hộ', 'số tiền sau km' ),
					'phi'         => array( 'số tiền phí thu hộ' ),
					'ma_cua_hang' => array( 'mã điểm thu' ),
					'dien_giai'   => array( 'điểm thu' ),
					// Kèm nội dung đơn vào diễn giải: dòng mini app ghi "thanh toan don
					// hang 141819 tu funzone" ở đây — không giữ thì sau không tra được cơ sở.
					'them'        => array( 'thông tin đặt hàng', 'chi tiết sản phẩm', 'ghi chú' ),
				),
				'cot'      => array( 'ngay' => 2, 'ma_gd' => 3, 'thu' => 22, 'phi' => 27, 'ma_cua_hang' => 5, 'dien_giai' => 6 ),
			),
			// Đơn Zalo mini app — KHÔNG phải tiền. Đích là bảng tra "mã đơn → cơ sở"
			// để dòng VNPay/MoMo "thanh toan don hang N tu funzone" tìm được điểm.
			'zalo_app' => array(
				'ten'      => 'Đơn hàng Zalo mini app',
				'dau_hieu' => array( 'mã đơn hàng', 'tên sản phẩm', 'tổng tiền phải trả' ),
				'dich'     => 'don_app',
				'ten_cot'  => array(
					'ma_don' => array( 'mã đơn hàng' ),
					'ngay'   => array( 'ngày đặt hàng', 'ngày tạo' ),
					'co_so'  => array( 'tên sản phẩm' ),
					'tien'   => array( 'tổng tiền phải trả', 'tổng tiền hàng' ),
					'tt_don' => array( 'trạng thái đơn hàng' ),
					'tt_tt'  => array( 'trạng thái thanh toán' ),
				),
				'cot'      => array( 'ma_don' => 0, 'ngay' => 1, 'co_so' => 22, 'tien' => 18, 'tt_don' => 11, 'tt_tt' => 14 ),
			),
			'momo' => array(
				'ten'      => 'MoMo',
				'dau_hieu' => array(
					array( 'ms.transid', 'ms.total amount' ),          // bản đối soát "MS.*" (file tháng 8 kế toán ghép)
					array( 'mã đơn hàng gốc', 'tên cửa hàng' ),          // "Transaction report" tải từ MoMo Business
				),
				'dich'     => 'cong',
				'loc'      => array( array( 'ms.trạng thái gd', 'trạng thái' ), 'thành công' ),
				'ten_cot'  => array(
					'ngay'        => array( 'thời gian', 'ms.ngày hoàn thành' ),
					// Mã đơn hàng, không phải Mã giao dịch: đợt tháng 8 đã nạp theo mã
					// đơn hàng; đổi cột là chặn trùng giữa hai tháng hỏng.
					'ma_gd'       => array( 'mã đơn hàng', 'ms.transid' ),
					'thu'         => array( 'ms.total amount', 'số tiền' ),
					'ma_cua_hang' => array( 'ms.mã cửa hàng', 'mã cửa hàng' ),
					'dien_giai'   => array( 'tên cửa hàng', 'ms.mã cửa hàng', 'mã cửa hàng' ),
					'them'        => array( 'mô tả giao dịch', 'ms.mã hđơn/ sđt nhận' ),
				),
				'cot'      => array( 'ngay' => 24, 'ma_gd' => 25, 'thu' => 5, 'ma_cua_hang' => 14, 'dien_giai' => 14 ),
			),
		);
	}

	/** Chuẩn tên tiêu đề để so: thường, bỏ đơn vị "(₫)" "(vnd)", gọn khoảng trắng. */
	public static function chuan_ten( $s ) {
		$s = mb_strtolower( trim( (string) $s ) );
		$s = preg_replace( '/\((₫|vnd|đ)\)/u', '', $s );
		return trim( preg_replace( '/\s+/u', ' ', $s ) );
	}

	/**
	 * Từ dòng tiêu đề, tìm chỉ số cột cho từng trường theo tên. Không thấy tên
	 * thì lùi về chỉ số dự phòng.
	 *
	 * @return array [ 'cot' => trường→chỉ số, 'theo_ten' => trường→tên đã khớp (để màn hình cho xem), 'loc' => [chỉ số, giá trị]|null ]
	 */
	public static function tim_cot( $dd, $tieu_de ) {
		$ten = array_map( array( __CLASS__, 'chuan_ten' ), $tieu_de );
		$tim = function ( $ung ) use ( $ten ) {
			foreach ( (array) $ung as $u ) {
				$i = array_search( self::chuan_ten( $u ), $ten, true );
				if ( false !== $i ) { return $i; }
			}
			return null;
		};
		$cot = array(); $theo_ten = array();
		foreach ( $dd['ten_cot'] ?? array() as $truong => $ung ) {
			$i = $tim( $ung );
			if ( null !== $i ) { $cot[ $truong ] = $i; $theo_ten[ $truong ] = $tieu_de[ $i ]; }
		}
		foreach ( $dd['cot'] as $truong => $i ) {
			if ( ! isset( $cot[ $truong ] ) ) { $cot[ $truong ] = $i; }
		}
		$loc = null;
		if ( isset( $dd['loc'] ) ) {
			list( $ten_loc, $gia_tri ) = $dd['loc'];
			$i = is_int( $ten_loc ) ? $ten_loc : $tim( $ten_loc );
			// Không có cột trạng thái thì không lọc — thà nhận thừa dòng còn hơn lọc theo cột bừa.
			if ( null !== $i ) { $loc = array( $i, $gia_tri ); }
		}
		return array( 'cot' => $cot, 'theo_ten' => $theo_ten, 'loc' => $loc );
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
		$dong = preg_split( '/\r\n|\r|\n/', KHTC_Tep::chuan_unicode( $text ) );
		foreach ( array_slice( $dong, 0, 12 ) as $i => $d ) {
			$thap = mb_strtolower( $d );
			foreach ( self::dinh_dang() as $khoa => $dd ) {
				// Một bộ dấu hiệu hay nhiều bộ — chuẩn về danh sách các bộ.
				$cac_bo = is_array( reset( $dd['dau_hieu'] ) ) ? $dd['dau_hieu'] : array( $dd['dau_hieu'] );
				foreach ( $cac_bo as $bo ) {
					$du = true;
					foreach ( $bo as $dh ) {
						if ( false === mb_strpos( $thap, $dh ) ) { $du = false; break; }
					}
					if ( $du ) { return array( $khoa, $dd, $i ); }
				}
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
		$text = KHTC_Tep::chuan_unicode( $text );
		$nd   = self::nhan_dang( $text );
		if ( ! $nd ) {
			return new WP_Error(
				'khong_nhan',
				'Không nhận ra đây là tệp gì. Nhớ copy cả DÒNG TIÊU ĐỀ (dòng có chữ "Mã tham chiếu", "Số tiền thanh toán", "Mã điểm thu" hoặc "MS.TransID"), không chỉ copy phần số.'
			);
		}
		list( $khoa, $dd, $i_tieu_de ) = $nd;
		$dong  = preg_split( '/\r\n|\r|\n/', (string) $text );
		$tc    = self::tim_cot( $dd, array_map( 'trim', self::o( $dong[ $i_tieu_de ] ) ) );
		$c     = $tc['cot'];
		if ( 'don_app' === $dd['dich'] ) {
			return self::doc_don_app( $khoa, $dd, $dong, $i_tieu_de, $tc );
		}

		$rows      = array();
		$bo_loc    = 0;   // dòng bị loại vì trạng thái không phải "Thành công"
		$thieu_cot = 0;   // dòng ngắn hơn tiêu đề, thường là dòng tổng cuối bảng
		$tong      = 0;
		$tong_phi  = 0;

		foreach ( array_slice( $dong, $i_tieu_de + 1 ) as $d ) {
			if ( '' === trim( $d ) ) { continue; }
			$o = array_map( 'trim', self::o( $d ) );

			$can = max( $c ) ;
			if ( count( $o ) <= $can ) { $thieu_cot++; continue; }

			if ( $tc['loc'] ) {
				list( $cot_loc, $gia_tri ) = $tc['loc'];
				if ( mb_strtolower( trim( (string) ( $o[ $cot_loc ] ?? '' ) ) ) !== $gia_tri ) { $bo_loc++; continue; }
			}

			$ngay = KHTC_GiaoDich::doc_ngay( $o[ $c['ngay'] ] );
			if ( '' === $ngay ) { $thieu_cot++; continue; }

			$thu = KHTC_GiaoDich::doc_so( $o[ $c['thu'] ] ?? 0 );
			$chi = isset( $c['chi'] ) ? KHTC_GiaoDich::doc_so( $o[ $c['chi'] ] ?? 0 ) : 0;
			if ( ! $thu && ! $chi ) { continue; }

			$dien_giai = (string) ( $o[ $c['dien_giai'] ] ?? '' );
			if ( isset( $c['them'] ) ) {
				$them = trim( (string) ( $o[ $c['them'] ] ?? '' ) );
				// Không kèm khi nó chỉ lặp lại mã giao dịch (MoMo ghi mã đơn vào "Mô tả").
				if ( '' !== $them && $them !== $dien_giai && $them !== trim( (string) ( $o[ $c['ma_gd'] ] ?? '' ) ) ) { $dien_giai = trim( $dien_giai . ' — ' . $them, ' —' ); }
			}
			$hang = array(
				'ngay'        => mysql2date( 'd/m/Y', $ngay ),
				'dien_giai'   => $dien_giai,
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
			'theo_ten'  => $tc['theo_ten'],   // trường → tên cột đã lấy, để màn hình cho người soát
		);
	}

	/**
	 * Bảng đơn mini app: mỗi dòng file là một SẢN PHẨM, nhiều dòng một đơn.
	 * Gom về một dòng mỗi đơn; đơn có sản phẩm ở hai cơ sở khác nhau thì ghi
	 * cả hai nối bằng " + " — tên đó sẽ không có trong danh mục và hiện ra
	 * cho người quyết, không tự chọn một cái.
	 */
	private static function doc_don_app( $khoa, $dd, $dong, $i_tieu_de, $tc ) {
		$c    = $tc['cot'];
		$don  = array();
		$thieu = 0;
		foreach ( array_slice( $dong, $i_tieu_de + 1 ) as $d ) {
			if ( '' === trim( $d ) ) { continue; }
			$o = array_map( 'trim', self::o( $d ) );
			if ( count( $o ) <= max( $c['ma_don'], $c['co_so'], $c['tien'] ) ) { $thieu++; continue; }
			$ma = ltrim( (string) $o[ $c['ma_don'] ], "# \t" );
			if ( '' === $ma ) { $thieu++; continue; }
			$ngay_tho = (string) ( $o[ $c['ngay'] ] ?? '' );
			// .xls không gắn định dạng ngày thì ô ngày ra số Excel
			if ( is_numeric( $ngay_tho ) && (float) $ngay_tho > 20000 ) { $ngay_tho = KHTC_Tep::ngay_excel( (float) $ngay_tho ); }
			$ngay  = KHTC_GiaoDich::doc_ngay( $ngay_tho );
			$co_so = (string) ( $o[ $c['co_so'] ] ?? '' );
			if ( ! isset( $don[ $ma ] ) ) {
				$don[ $ma ] = array(
					'ma_don' => $ma,
					'ngay'   => $ngay ? mysql2date( 'd/m/Y', $ngay ) : '',
					'co_so'  => $co_so,
					'tien'   => KHTC_GiaoDich::doc_so( $o[ $c['tien'] ] ?? 0 ),
					'tt_don' => (string) ( $o[ $c['tt_don'] ] ?? '' ),
					'tt_tt'  => (string) ( $o[ $c['tt_tt'] ] ?? '' ),
				);
			} elseif ( '' !== $co_so && false === mb_strpos( $don[ $ma ]['co_so'], $co_so ) ) {
				$don[ $ma ]['co_so'] .= ' + ' . $co_so;
			}
		}
		$tong = 0;
		foreach ( $don as $r ) { $tong += (int) $r['tien']; }
		return array(
			'dinh_dang' => $khoa,
			'ten'       => $dd['ten'],
			'dich'      => 'don_app',
			'rows'      => array_values( $don ),
			'bo_loc'    => 0,
			'thieu_cot' => $thieu,
			'tong'      => $tong,
			'tong_phi'  => 0,
			'theo_ten'  => $tc['theo_ten'],
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
