<?php
/**
 * Đối soát: so dòng tiền cổng thanh toán báo về với sao kê ngân hàng.
 *
 * MỘT ĐỢT ĐỐI SOÁT gồm: chọn kênh (VietQR / Payoo / VNPay / Zalo / MoMo), chọn
 * tài khoản ngân hàng nhận tiền, chọn khoảng ngày, rồi dán bảng cổng gửi về.
 * Máy ghép từng dòng cổng với từng dòng sao kê và chỉ ra bốn nhóm:
 *
 *   Khớp          — cổng có, ngân hàng có, số tiền bằng nhau
 *   Lệch tiền     — cùng mã giao dịch nhưng số tiền không bằng
 *   Thiếu         — cổng báo có mà ngân hàng chưa về
 *   Thừa          — ngân hàng có mà cổng không báo
 *
 * BỐN LƯỢT GHÉP, theo thứ tự chắc chắn giảm dần. Một dòng đã ghép rồi thì lượt
 * sau không đụng tới nữa — nếu không, một dòng sao kê có thể bị hai dòng cổng
 * cùng nhận, và tổng "khớp" sẽ lớn hơn số tiền thật về tài khoản.
 *
 *   1. Trùng mã giao dịch          — chắc nhất, kể cả ngày lệch
 *   2. Trùng ngày và trùng số tiền
 *   3. Trùng số tiền, ngày lệch trong dung sai (cổng chốt T+1, T+2)
 *   4. Trùng số tiền SAU KHI TRỪ PHÍ — cổng cắt phí trước khi chuyển về
 *
 * Lượt 4 là lượt hay bị bỏ sót nhất. Payoo và VNPay chuyển về số ròng: cổng ghi
 * 1.000.000, phí 11.000, ngân hàng về 989.000. Thiếu lượt này thì gần như mọi
 * dòng đều rơi vào "Thiếu" và bảng đối soát thành vô dụng.
 *
 * @package KHTC
 */

defined( 'ABSPATH' ) || exit;

class KHTC_DoiSoat {

	/** Cổng chốt tiền không cùng ngày phát sinh; T+3 phủ hết các cổng đang dùng. */
	const DUNG_SAI_NGAY = 3;

	public static function kenh() {
		return array(
			'vietqr' => 'VietQR',
			'payoo'  => 'Payoo',
			'vnpay'  => 'VNPay',
			'zalo'   => 'Zalo Mini App',
			'momo'   => 'MoMo',
			'khac'   => 'Khác',
		);
	}

	public static function ten_kenh( $k ) {
		$ds = self::kenh();
		return isset( $ds[ $k ] ) ? $ds[ $k ] : $k;
	}

	public static function ten_kieu( $k ) {
		$ds = array(
			'khop_ma'      => 'Khớp mã giao dịch',
			'khop_ngay'    => 'Khớp ngày + số tiền',
			'khop_lech'    => 'Khớp số tiền, lệch ngày',
			'khop_tru_phi' => 'Khớp sau khi trừ phí',
			'lech_tien'    => 'Lệch tiền',
		);
		return isset( $ds[ $k ] ) ? $ds[ $k ] : $k;
	}

	// ----------------------------------------------------------------- đợt

	public static function ds_dot( $cty = null ) {
		global $wpdb;
		$cty = $cty ? $cty : KHTC_Cty::dang_chon();
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT d.*, n.ten AS ten_ngan_hang FROM ' . KHTC_DB::bang( 'doi_soat' ) . ' d
				 LEFT JOIN ' . KHTC_DB::bang( 'ngan_hang' ) . ' n ON n.id = d.ngan_hang_id
				 WHERE d.cty = %s ORDER BY d.id DESC',
				$cty
			)
		);
	}

	public static function mot_dot( $id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT d.*, n.ten AS ten_ngan_hang FROM ' . KHTC_DB::bang( 'doi_soat' ) . ' d
				 LEFT JOIN ' . KHTC_DB::bang( 'ngan_hang' ) . ' n ON n.id = d.ngan_hang_id
				 WHERE d.id = %d AND d.cty = %s',
				(int) $id,
				KHTC_Cty::dang_chon()
			)
		);
	}

	public static function tao_dot( $d ) {
		global $wpdb;
		$tu  = KHTC_GiaoDich::doc_ngay( $d['tu'] ?? '' );
		$den = KHTC_GiaoDich::doc_ngay( $d['den'] ?? '' );
		if ( '' === $tu || '' === $den ) {
			return new WP_Error( 'ky', 'Chưa chọn đủ khoảng ngày cho đợt đối soát.' );
		}
		if ( $tu > $den ) {
			return new WP_Error( 'ky', 'Ngày bắt đầu đang sau ngày kết thúc.' );
		}
		if ( empty( $d['ngan_hang_id'] ) ) {
			return new WP_Error( 'nh', 'Chưa chọn tài khoản ngân hàng nhận tiền.' );
		}
		$kenh = isset( self::kenh()[ $d['kenh'] ?? '' ] ) ? $d['kenh'] : 'khac';
		$ten  = trim( (string) ( $d['ten'] ?? '' ) );
		if ( '' === $ten ) {
			$ten = self::ten_kenh( $kenh ) . ' ' . mysql2date( 'd/m', $tu ) . '–' . mysql2date( 'd/m/Y', $den );
		}
		$wpdb->insert(
			KHTC_DB::bang( 'doi_soat' ),
			array(
				'cty'          => KHTC_Cty::dang_chon(),
				'ten'          => $ten,
				'kenh'         => $kenh,
				'ngan_hang_id' => (int) $d['ngan_hang_id'],
				'tu'           => $tu,
				'den'          => $den,
				'tao_luc'      => current_time( 'mysql' ),
				'tao_boi'      => wp_get_current_user()->display_name,
			),
			array( '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
		);
		$moi = (int) $wpdb->insert_id;
		KHTC_NhatKy::ghi( 'them', 'doi_soat', $moi, 'Tạo đợt đối soát ' . $ten );
		return $moi;
	}

	public static function xoa_dot( $id ) {
		global $wpdb;
		$dot = self::mot_dot( $id );
		if ( ! $dot ) { return; }
		KHTC_NhatKy::ghi_xoa( 'doi_soat', $id, 'Xoá đợt đối soát ' . $dot->ten );
		$wpdb->delete( KHTC_DB::bang( 'ds_dong' ), array( 'dot_id' => (int) $id ), array( '%d' ) );
		$wpdb->delete( KHTC_DB::bang( 'doi_soat' ), array( 'id' => (int) $id ), array( '%d' ) );
	}

	// ------------------------------------------------------------ nạp dòng

	/**
	 * Dán bảng cổng gửi về. Mỗi dòng: Ngày · Mã GD · Số tiền · Phí · Nội dung.
	 * Cách nhau bằng Tab (copy thẳng từ Excel) hoặc dấu phẩy. Phí bỏ trống = 0.
	 *
	 * Dòng trùng mã giao dịch với dòng đã có trong cùng đợt thì BỎ QUA. Dán hai
	 * lần cùng một bảng là chuyện thường xuyên xảy ra, và không chặn thì tổng
	 * cổng gấp đôi trong khi sao kê giữ nguyên — ra một bảng lệch không có thật.
	 */
	public static function nap_dong( $dot_id, $text ) {
		global $wpdb;
		$dot = self::mot_dot( $dot_id );
		if ( ! $dot ) {
			return array( 'them' => 0, 'trung' => 0, 'loi' => array( 'Không tìm thấy đợt đối soát.' ) );
		}

		$da_co = $wpdb->get_col(
			$wpdb->prepare( 'SELECT ma_gd FROM ' . KHTC_DB::bang( 'ds_dong' ) . " WHERE dot_id = %d AND ma_gd <> ''", (int) $dot_id )
		);
		$da_co = array_flip( $da_co );

		$them = 0;
		$trung = 0;
		$loi  = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $i => $d ) {
			$d = trim( $d );
			if ( '' === $d ) { continue; }
			$o = ( strpos( $d, "\t" ) !== false ) ? explode( "\t", $d ) : str_getcsv( $d, ',', '"', '' );
			$o = array_map( 'trim', $o );
			if ( count( $o ) < 3 ) {
				$loi[] = 'Dòng ' . ( $i + 1 ) . ': cần ít nhất Ngày, Mã GD, Số tiền.';
				continue;
			}
			$ngay = KHTC_GiaoDich::doc_ngay( $o[0] );
			if ( '' === $ngay ) {
				$loi[] = 'Dòng ' . ( $i + 1 ) . ': ngày "' . $o[0] . '" không đọc được.';
				continue;
			}
			$ma = (string) $o[1];
			if ( '' !== $ma && isset( $da_co[ $ma ] ) ) {
				$trung++;
				continue;
			}
			$wpdb->insert(
				KHTC_DB::bang( 'ds_dong' ),
				array(
					'dot_id'    => (int) $dot_id,
					'ngay'      => $ngay,
					'ma_gd'     => $ma,
					'so_tien'   => abs( KHTC_GiaoDich::doc_so( $o[2] ) ),
					'phi'       => isset( $o[3] ) ? abs( KHTC_GiaoDich::doc_so( $o[3] ) ) : 0,
					'dien_giai' => isset( $o[4] ) ? (string) $o[4] : '',
					// Cột 6 không bắt buộc. Có nó thì đợt cổng này gom được
					// thành hoá đơn theo điểm, y như sao kê ngân hàng.
					'ma_cua_hang' => isset( $o[5] ) ? (string) $o[5] : '',
				),
				array( '%d', '%s', '%s', '%d', '%d', '%s', '%s' )
			);
			if ( '' !== $ma ) { $da_co[ $ma ] = 1; }
			$them++;
		}
		KHTC_NhatKy::ghi( 'nap', 'ds_dong', (int) $dot_id, sprintf( 'Nạp %d dòng cổng vào %s%s', $them, $dot->ten, $trung ? ', bỏ ' . $trung . ' trùng mã' : '' ) );
		return array( 'them' => $them, 'trung' => $trung, 'loi' => $loi );
	}

	public static function xoa_dong_cua_dot( $dot_id ) {
		global $wpdb;
		$wpdb->delete( KHTC_DB::bang( 'ds_dong' ), array( 'dot_id' => (int) $dot_id ), array( '%d' ) );
	}

	// -------------------------------------------------------------- ghép

	/**
	 * Chạy lại toàn bộ phép ghép cho một đợt và ghi kết quả vào từng dòng.
	 *
	 * Ghép lại từ đầu mỗi lần chứ không ghép thêm: sao kê có thể vừa được nạp
	 * bổ sung, và một dòng "Thiếu" hôm qua hôm nay đã có tiền về. Ghép thêm thì
	 * kết quả phụ thuộc thứ tự bấm nút — chạy lại từ đầu thì không.
	 *
	 * KHOÁ SỔ KHÔNG CHẶN VIỆC NÀY, cố ý: phép ghép chỉ ghi cờ "dòng cổng này
	 * ứng với dòng sao kê kia", không đụng tới ngày, số tiền hay phân loại của
	 * bất cứ bản ghi nào. Chốt sổ xong vẫn phải đối chiếu lại được với cổng.
	 *
	 * @return array Thống kê từng nhóm.
	 */
	public static function chay( $dot_id ) {
		global $wpdb;
		$dot = self::mot_dot( $dot_id );
		if ( ! $dot ) {
			return new WP_Error( 'dot', 'Không tìm thấy đợt đối soát.' );
		}

		$b_dong = KHTC_DB::bang( 'ds_dong' );
		$wpdb->query( $wpdb->prepare( "UPDATE $b_dong SET khop_gd_id = 0, kieu_khop = '' WHERE dot_id = %d", (int) $dot_id ) );

		$dong = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM $b_dong WHERE dot_id = %d ORDER BY ngay, id", (int) $dot_id )
		);

		// Sao kê trong kỳ, nới hai đầu theo dung sai để bắt được dòng về muộn.
		$ngoai = self::DUNG_SAI_NGAY;
		$gd    = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . KHTC_DB::bang( 'giao_dich' ) . "
				 WHERE cty = %s AND ngan_hang_id = %d AND loai = 'thu'
				   AND ngay >= DATE_SUB(%s, INTERVAL %d DAY)
				   AND ngay <= DATE_ADD(%s, INTERVAL %d DAY)
				 ORDER BY ngay, id",
				$dot->cty,
				(int) $dot->ngan_hang_id,
				$dot->tu,
				$ngoai,
				$dot->den,
				$ngoai
			)
		);

		$ghep = self::ghep( $dong, $gd, self::DUNG_SAI_NGAY );

		foreach ( $ghep as $dong_id => $kq ) {
			$wpdb->update(
				$b_dong,
				array( 'khop_gd_id' => $kq[0], 'kieu_khop' => $kq[1] ),
				array( 'id' => $dong_id ),
				array( '%d', '%s' ),
				array( '%d' )
			);
		}

		$wpdb->update(
			KHTC_DB::bang( 'doi_soat' ),
			array( 'chay_luc' => current_time( 'mysql' ) ),
			array( 'id' => (int) $dot_id ),
			array( '%s' ),
			array( '%d' )
		);

		$kq = self::ket_qua( $dot_id );
		KHTC_NhatKy::ghi(
			'doi_soat',
			'doi_soat',
			(int) $dot_id,
			sprintf( 'Chạy đối soát %s — khớp %d, lệch tiền %d, thiếu %d, thừa %d', $dot->ten, count( $kq['khop'] ), count( $kq['lech'] ), count( $kq['thieu'] ), count( $kq['thua'] ) )
		);
		return $kq;
	}

	/**
	 * Phép ghép thuần: nhận hai danh sách, trả về dòng cổng nào ăn dòng sao kê nào.
	 *
	 * Tách riêng khỏi chay() để kiểm thử được mà không cần MySQL. Đây là chỗ duy
	 * nhất quyết định một đồng nằm ở nhóm "Khớp" hay "Thiếu", nên nó phải kiểm
	 * thử được — dò bằng mắt trên dữ liệu thật thì không bao giờ đủ.
	 *
	 * @param array $dong     Dòng cổng: cần id, ngay, ma_gd, so_tien, phi.
	 * @param array $gd       Dòng sao kê: cần id, ngay, ma_gd, so_tien.
	 * @param int   $dung_sai Số ngày cho phép lệch ở lượt 3 và 4.
	 * @return array [dong_id => [gd_id, kiểu ghép]]
	 */
	public static function ghep( $dong, $gd, $dung_sai = self::DUNG_SAI_NGAY ) {
		// Ba bảng tra cứu để không phải quét lại danh sách sao kê cho từng dòng cổng.
		$theo_ma   = array();
		$theo_tien = array();
		$con_trong = array();
		foreach ( $gd as $g ) {
			$con_trong[ (int) $g->id ] = $g;
			if ( '' !== (string) $g->ma_gd ) {
				$theo_ma[ (string) $g->ma_gd ][] = (int) $g->id;
			}
			$theo_tien[ (int) $g->so_tien ][] = (int) $g->id;
		}

		$ghep = array();   // dong_id => [gd_id, kieu]
		$dung = array();   // gd_id đã bị nhận

		// Lượt 1 — trùng mã giao dịch. Chắc nhất, nên chạy trước và không cần
		// nhìn tới ngày: cổng chốt T+2 vẫn mang đúng mã sang sao kê.
		foreach ( $dong as $d ) {
			$ma = (string) $d->ma_gd;
			if ( '' === $ma || ! isset( $theo_ma[ $ma ] ) ) { continue; }
			foreach ( $theo_ma[ $ma ] as $gid ) {
				if ( isset( $dung[ $gid ] ) ) { continue; }
				$g    = $con_trong[ $gid ];
				$rong = (int) $d->so_tien - (int) $d->phi;
				$bang = ( (int) $g->so_tien === (int) $d->so_tien ) || ( (int) $g->so_tien === $rong );
				$ghep[ (int) $d->id ] = array( $gid, $bang ? 'khop_ma' : 'lech_tien' );
				$dung[ $gid ]         = true;
				break;
			}
		}

		// Lượt 2–4 — ghép theo số tiền, chắc chắn giảm dần.
		$luot = array(
			array( 'kieu' => 'khop_ngay',    'tru_phi' => false, 'dung_sai' => 0 ),
			array( 'kieu' => 'khop_lech',    'tru_phi' => false, 'dung_sai' => $dung_sai ),
			array( 'kieu' => 'khop_tru_phi', 'tru_phi' => true,  'dung_sai' => $dung_sai ),
		);
		foreach ( $luot as $l ) {
			foreach ( $dong as $d ) {
				if ( isset( $ghep[ (int) $d->id ] ) ) { continue; }
				if ( $l['tru_phi'] && (int) $d->phi <= 0 ) { continue; }
				$tien = $l['tru_phi'] ? ( (int) $d->so_tien - (int) $d->phi ) : (int) $d->so_tien;
				if ( ! isset( $theo_tien[ $tien ] ) ) { continue; }
				foreach ( $theo_tien[ $tien ] as $gid ) {
					if ( isset( $dung[ $gid ] ) ) { continue; }
					if ( self::cach_ngay( $d->ngay, $con_trong[ $gid ]->ngay ) > $l['dung_sai'] ) { continue; }
					$ghep[ (int) $d->id ] = array( $gid, $l['kieu'] );
					$dung[ $gid ]         = true;
					break;
				}
			}
		}

		return $ghep;
	}

	/** Số ngày cách nhau giữa hai ngày YYYY-MM-DD, luôn không âm. */
	public static function cach_ngay( $a, $b ) {
		$ta = strtotime( $a . ' 00:00:00 UTC' );
		$tb = strtotime( $b . ' 00:00:00 UTC' );
		if ( ! $ta || ! $tb ) { return PHP_INT_MAX; }
		return (int) round( abs( $ta - $tb ) / DAY_IN_SECONDS );
	}

	// ------------------------------------------------------------ kết quả

	/**
	 * Kết quả một đợt, chia làm bốn nhóm cộng phần tổng.
	 *
	 * "Thừa" lấy từ sao kê trong ĐÚNG kỳ (không nới dung sai): một dòng ngân
	 * hàng ngày 03/08 khớp với dòng cổng ngày 31/07 là bình thường, nhưng đem
	 * nó ra khỏi kỳ rồi báo thừa thì kế toán phải đi tìm một thứ không sai.
	 */
	public static function ket_qua( $dot_id ) {
		global $wpdb;
		$dot = self::mot_dot( $dot_id );
		if ( ! $dot ) { return null; }

		$dong = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT d.*, g.ngay AS gd_ngay, g.so_tien AS gd_so_tien, g.dien_giai AS gd_dien_giai
				 FROM ' . KHTC_DB::bang( 'ds_dong' ) . ' d
				 LEFT JOIN ' . KHTC_DB::bang( 'giao_dich' ) . ' g ON g.id = d.khop_gd_id
				 WHERE d.dot_id = %d ORDER BY d.ngay, d.id',
				(int) $dot_id
			)
		);

		$khop = array();
		$lech = array();
		$thieu = array();
		$da_nhan = array();
		$tong_cong = 0;
		$tong_phi  = 0;

		foreach ( $dong as $d ) {
			$tong_cong += (int) $d->so_tien;
			$tong_phi  += (int) $d->phi;
			if ( (int) $d->khop_gd_id ) { $da_nhan[ (int) $d->khop_gd_id ] = true; }

			if ( 'lech_tien' === $d->kieu_khop ) {
				$lech[] = $d;
			} elseif ( $d->kieu_khop ) {
				$khop[] = $d;
			} else {
				$thieu[] = $d;
			}
		}

		$gd_trong_ky = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . KHTC_DB::bang( 'giao_dich' ) . "
				 WHERE cty = %s AND ngan_hang_id = %d AND loai = 'thu' AND ngay >= %s AND ngay <= %s
				 ORDER BY ngay, id",
				$dot->cty,
				(int) $dot->ngan_hang_id,
				$dot->tu,
				$dot->den
			)
		);
		$thua      = array();
		$tong_ngan = 0;
		foreach ( $gd_trong_ky as $g ) {
			$tong_ngan += (int) $g->so_tien;
			if ( ! isset( $da_nhan[ (int) $g->id ] ) ) { $thua[] = $g; }
		}

		// Ghép nhờ lượt nào — để màn hình nói ra chất lượng của kết quả, chứ
		// không chỉ nói số dòng khớp.
		$theo_luot = array();
		foreach ( $dong as $d ) {
			if ( ! $d->kieu_khop || 'lech_tien' === $d->kieu_khop ) { continue; }
			$theo_luot[ $d->kieu_khop ] = ( $theo_luot[ $d->kieu_khop ] ?? 0 ) + 1;
		}

		return array(
			'dot'        => $dot,
			'khop'       => $khop,
			'lech'       => $lech,
			'thieu'      => $thieu,
			'thua'       => $thua,
			'tong_cong'  => $tong_cong,
			'tong_phi'   => $tong_phi,
			'tong_ngan'  => $tong_ngan,
			'theo_luot'  => $theo_luot,
			'canh_bao'   => self::canh_bao( $tong_cong, $tong_phi, $tong_ngan, count( $dong ), $theo_luot ),
			'tien_khop'  => array_sum( array_map( function ( $d ) { return (int) $d->so_tien; }, $khop ) ),
			'tien_thieu' => array_sum( array_map( function ( $d ) { return (int) $d->so_tien; }, $thieu ) ),
			'tien_thua'  => array_sum( array_map( function ( $g ) { return (int) $g->so_tien; }, $thua ) ),
			'tien_lech'  => array_sum( array_map( function ( $d ) { return (int) $d->so_tien - (int) $d->gd_so_tien; }, $lech ) ),
		);
	}

	/**
	 * Nhìn tổng thể xem kết quả có đáng tin không, trước khi đọc từng nhóm.
	 *
	 * Lý do có hàm này: chạy thử dữ liệu thật tháng 8/2026 với một tệp Payoo và
	 * sao kê của MỘT TÀI KHOẢN KHÁC — hai dòng tiền không liên quan gì nhau —
	 * mà máy vẫn báo "Khớp 291 dòng". Không dòng nào khớp theo mã; cả 291 là
	 * trùng ngẫu nhiên ngày và số tiền, vì sao kê QR có 517 dòng đúng 100.000 đ,
	 * 390 dòng 20.000 đ, 377 dòng 50.000 đ. Với mệnh giá tròn và lượng lớn thì
	 * đụng nhau là chắc chắn.
	 *
	 * Không sửa phép ghép: khi hai tệp ĐÚNG là của nhau, ghép nhiều dòng cùng
	 * mệnh giá trong một ngày vẫn ra tổng đúng, đó mới là việc của đối soát.
	 * Từ chối ghép chỉ vì trùng mệnh giá sẽ phá đúng trường hợp bình thường.
	 * Cái phải sửa là sự im lặng: con số "Khớp 291" tự nó trông yên tâm.
	 *
	 * @return string Câu cảnh báo, '' nếu không có gì đáng ngờ.
	 */
	public static function canh_bao( $tong_cong, $tong_phi, $tong_ngan, $so_dong, $theo_luot ) {
		if ( ! $so_dong || ! $tong_cong ) { return ''; }

		// Cổng chuyển về số ròng, nên chênh lệch đúng bằng phí mới là bình
		// thường. Nới thêm 5% cho dòng về muộn qua kỳ.
		$mong  = $tong_cong - $tong_phi;
		$lech  = abs( $tong_ngan - $mong );
		if ( $mong > 0 && $lech > $mong * 0.05 ) {
			return sprintf(
				'Tổng cổng trừ phí là %s đ nhưng tài khoản chỉ nhận %s đ — lệch %s đ. Kiểm lại xem có chọn đúng tài khoản và đúng kỳ không, trước khi đọc bốn nhóm bên dưới.',
				number_format( $mong, 0, ',', '.' ),
				number_format( $tong_ngan, 0, ',', '.' ),
				number_format( $lech, 0, ',', '.' )
			);
		}

		// Không dòng nào khớp theo mã: mọi cặp đều đoán theo ngày và số tiền.
		$theo_ma = (int) ( $theo_luot['khop_ma'] ?? 0 );
		$khop    = array_sum( $theo_luot );
		if ( $khop > 20 && 0 === $theo_ma ) {
			return sprintf(
				'Cả %d dòng khớp đều chỉ dựa vào ngày và số tiền, không dòng nào trùng mã giao dịch. Nếu tệp cổng có cột mã thì nạp lại kèm cột đó; sao kê nhiều dòng cùng mệnh giá thì kiểu ghép này dễ bắt nhầm cặp.',
				$khop
			);
		}
		return '';
	}

	// ------------------------------------------------------------- tải về

	/**
	 * Xuất cả bốn nhóm ra một file CSV để gửi cho cổng hoặc cho kế toán trưởng.
	 *
	 * Mở đầu bằng BOM UTF-8: thiếu nó thì Excel bản Việt đọc "Thiếu" thành
	 * "Thiáº¿u" và cả file thành vô dụng đúng ở khâu gửi đi.
	 */
	public static function tai_csv() {
		$id = (int) ( $_GET['khtc_tai'] ?? 0 );
		if ( ! $id ) { return; }
		if ( ! is_user_logged_in() || ! current_user_can( KHTC_CAP ) ) { return; }
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'khtc_tai_' . $id ) ) {
			return;
		}
		$kq = self::ket_qua( $id );
		if ( ! $kq ) { return; }
		$d = $kq['dot'];

		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="doi-soat-' . $id . '-' . $d->tu . '_' . $d->den . '.csv"' );
		$f = fopen( 'php://output', 'w' );
		fwrite( $f, "\xEF\xBB\xBF" );

		fputcsv( $f, array( 'Đợt', $d->ten ) );
		fputcsv( $f, array( 'Kênh', self::ten_kenh( $d->kenh ) ) );
		fputcsv( $f, array( 'Kỳ', $d->tu . ' → ' . $d->den ) );
		fputcsv( $f, array( 'Cổng báo về', $kq['tong_cong'] ) );
		fputcsv( $f, array( 'Ngân hàng trong kỳ', $kq['tong_ngan'] ) );
		fputcsv( $f, array( 'Chênh lệch', $kq['tong_cong'] - $kq['tong_ngan'] ) );
		fputcsv( $f, array( 'Phí cổng', $kq['tong_phi'] ) );
		fputcsv( $f, array() );

		fputcsv( $f, array( 'Nhóm', 'Ngày cổng', 'Mã GD', 'Tiền cổng', 'Phí', 'Ngày NH', 'Tiền NH', 'Kiểu ghép', 'Nội dung' ) );
		foreach ( $kq['khop'] as $r ) {
			fputcsv( $f, array( 'Khớp', $r->ngay, $r->ma_gd, $r->so_tien, $r->phi, $r->gd_ngay, $r->gd_so_tien, self::ten_kieu( $r->kieu_khop ), $r->dien_giai ) );
		}
		foreach ( $kq['lech'] as $r ) {
			fputcsv( $f, array( 'Lệch tiền', $r->ngay, $r->ma_gd, $r->so_tien, $r->phi, $r->gd_ngay, $r->gd_so_tien, 'Lệch ' . ( (int) $r->so_tien - (int) $r->gd_so_tien ), $r->dien_giai ) );
		}
		foreach ( $kq['thieu'] as $r ) {
			fputcsv( $f, array( 'Thiếu', $r->ngay, $r->ma_gd, $r->so_tien, $r->phi, '', '', '', $r->dien_giai ) );
		}
		foreach ( $kq['thua'] as $g ) {
			fputcsv( $f, array( 'Thừa', '', $g->ma_gd, '', '', $g->ngay, $g->so_tien, '', $g->dien_giai ) );
		}
		fclose( $f );
		exit;
	}
}
