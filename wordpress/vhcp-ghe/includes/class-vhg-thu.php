<?php
/**
 * GHI NHẬN TIỀN VÀO — chỗ duy nhất trong plugin được phép ghi bảng doanh thu.
 *
 * =============================================================================================
 * 🔴 LUẬT: GHI THEO `ref`, VÀ CHỈ MỘT ĐƯỜNG GHI
 * =============================================================================================
 * Mỗi giao dịch ngân hàng có một mã tham chiếu. Ghi theo mã đó (UNIQUE ở bảng) nghĩa là:
 *   · webhook bắn lại lần hai  -> ghi đè lên đúng hàng cũ, KHÔNG cộng thêm;
 *   · nhập lại đúng file Excel -> cũng vậy;
 *   · nhập file chồng lấn ngày -> phần trùng tự hoà, phần mới thì thêm.
 * Nhờ vậy "nhập lại cho chắc" là việc AN TOÀN. Bỏ luật này là mất luôn tính chất đó, và không
 * ai dám bấm nút nhập lần thứ hai nữa.
 *
 * ⚠️ Không có `ref` thì TỰ SINH một mã ổn định từ (thời điểm + số tiền + nội dung) chứ KHÔNG
 *    sinh ngẫu nhiên. Ngẫu nhiên là mỗi lần bắn lại thành một hàng mới — đúng thứ luật trên
 *    đang tránh. Mã tự sinh có tiền tố `tu-` để người đọc biết đây không phải mã của ngân hàng.
 *
 * =============================================================================================
 * ⚠️ CHỈ NỚI, KHÔNG THU HẸP — với thông tin đã biết về một giao dịch
 * =============================================================================================
 * Một giao dịch có thể tới HAI lần theo hai đường: webhook lúc phát sinh (chưa biết máy nào),
 * rồi file Excel Tingo hôm sau (có Mã điểm bán -> tra ra máy). Lượt sau phải ĐIỀN THÊM tên máy,
 * chứ lượt sau mà xoá trắng tên máy lượt trước đã biết thì đối soát đi lùi.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHG_Thu {

	/** Nguồn tiền. `cash` là thu tay tại quầy — người bấm, không qua ngân hàng. */
	const QR     = 'qr';
	const VIETQR = 'vietqr';
	const SEPAY  = 'sepay';
	const TIEN_MAT = 'cash';

	/**
	 * Ghi một giao dịch. `$d` gồm: ref, luc, ma_may, ma_lenh, so_tien, nguon, noi_dung,
	 * ten_khai, vvb, ma_ch. Trả array( 'ok', 'moi' => true/false, 'ref' ).
	 */
	public static function ghi( $d ) {
		global $wpdb;
		$tien = (int) ( isset( $d['so_tien'] ) ? $d['so_tien'] : 0 );
		if ( $tien <= 0 ) { return array( 'ok' => false, 'error' => 'Số tiền phải lớn hơn 0.' ); }

		$luc = isset( $d['luc'] ) ? VHG_Doc::ngay( $d['luc'] ) : '';
		if ( '' === $luc ) { $luc = current_time( 'mysql' ); }

		$ref = trim( (string) ( isset( $d['ref'] ) ? $d['ref'] : '' ) );
		if ( '' === $ref ) { $ref = self::ref_tu_sinh( $luc, $tien, isset( $d['noi_dung'] ) ? $d['noi_dung'] : '' ); }

		$bang = VHG_DB::t( 'thu' );
		$cu = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $bang WHERE ref=%s LIMIT 1", $ref ), ARRAY_A );

		$hang = array(
			'ref'      => $ref,
			'luc'      => $luc,
			'so_tien'  => $tien,
			'nguon'    => (string) ( isset( $d['nguon'] ) ? $d['nguon'] : self::QR ),
			'noi_dung' => mb_substr( (string) ( isset( $d['noi_dung'] ) ? $d['noi_dung'] : '' ), 0, 250 ),
			'ma_may'   => (string) ( isset( $d['ma_may'] ) ? $d['ma_may'] : '' ),
			'ma_lenh'  => (string) ( isset( $d['ma_lenh'] ) ? $d['ma_lenh'] : '' ),
			'ten_khai' => (string) ( isset( $d['ten_khai'] ) ? $d['ten_khai'] : '' ),
			'vvb'      => (string) ( isset( $d['vvb'] ) ? $d['vvb'] : '' ),
			'ma_ch'    => (string) ( isset( $d['ma_ch'] ) ? $d['ma_ch'] : '' ),
			'ghi_luc'  => current_time( 'mysql' ),
		);

		if ( $cu ) {
			/* CHỈ NỚI: giá trị mới rỗng thì GIỮ giá trị cũ. Xem khối ⚠️ ở đầu tệp — lượt sau
			   biết thêm thì điền thêm, lượt sau không biết thì đừng xoá cái lượt trước đã biết. */
			foreach ( array( 'ma_may', 'ma_lenh', 'ten_khai', 'vvb', 'ma_ch', 'noi_dung' ) as $c ) {
				if ( '' === $hang[ $c ] && '' !== (string) $cu[ $c ] ) { $hang[ $c ] = $cu[ $c ]; }
			}
			$wpdb->update( $bang, $hang, array( 'id' => (int) $cu['id'] ) );
			return array( 'ok' => true, 'moi' => false, 'ref' => $ref );
		}
		$wpdb->insert( $bang, $hang );
		return array( 'ok' => true, 'moi' => true, 'ref' => $ref );
	}

	/**
	 * Mã tham chiếu tự sinh khi bên gửi không có. ỔN ĐỊNH theo nội dung giao dịch, không ngẫu
	 * nhiên — xem khối ⚠️ ở đầu tệp.
	 */
	public static function ref_tu_sinh( $luc, $tien, $noi_dung ) {
		return 'tu-' . substr( md5( $luc . '|' . $tien . '|' . trim( (string) $noi_dung ) ), 0, 16 );
	}

	/**
	 * Nhận MỘT giao dịch từ webhook: ghi doanh thu, và nếu nội dung khớp "GHE<ghế> <mã>" thì
	 * xếp vào hàng chờ cho ghế chạy.
	 *
	 * ⚠️ TIỀN RA thì KHÔNG ghi doanh thu. Webhook biến động số dư gửi cả tiền vào lẫn tiền ra;
	 *    tính cả hai là doanh thu phồng lên bằng đúng số tiền mình tự chuyển đi.
	 */
	public static function nhan( $nguon, $ev ) {
		$tien = (int) $ev['so_tien'];
		$nd   = (string) $ev['noi_dung'];
		$ten  = VHG_Doc::ten_may( $nd );
		if ( '' === $ten && ! empty( $ev['ten_khai'] ) ) { $ten = VHG_Doc::chuan_ten( $ev['ten_khai'] ); }

		if ( ! empty( $ev['tien_ra'] ) || $tien <= 0 ) {
			return array( 'ok' => true, 'bo_qua' => true,
				'ghi_chu' => ! empty( $ev['tien_ra'] ) ? 'tiền ra — không phải doanh thu' : 'số tiền = 0',
				'ten_khai' => $ten );
		}

		list( $ma_may, $ma_lenh ) = VHG_Doc::ghe_va_ma( $nd );
		$kq = self::ghi( array(
			'ref' => $ev['ref'], 'so_tien' => $tien, 'noi_dung' => $nd, 'nguon' => $nguon,
			'ma_may' => $ma_may, 'ma_lenh' => $ma_lenh, 'ten_khai' => $ten,
		) );
		if ( empty( $kq['ok'] ) ) { return $kq; }

		/* Khớp mẫu "GHE<ghế> <mã>" -> ghế được phép chạy. KHÔNG khớp thì tiền vẫn vào sổ, chỉ là
		   chưa gắn được máy — đối soát tay sau, đừng bỏ. */
		if ( '' !== $ma_may && '' !== $ma_lenh ) {
			VHG_May::xep_cho_chay( $ma_may, $ma_lenh, $tien, $kq['ref'], $nd );
		}
		return array( 'ok' => true, 'ref' => $kq['ref'], 'moi' => $kq['moi'],
			'ma_may' => $ma_may, 'ma_lenh' => $ma_lenh, 'ten_khai' => $ten, 'so_tien' => $tien );
	}

	/** Thu tiền mặt tại quầy — người bấm trên màn, không qua ngân hàng. */
	public static function thu_tien_mat( $ma_may, $so_tien, $nguoi ) {
		$ma_may = trim( (string) $ma_may );
		if ( '' === $ma_may ) { return array( 'ok' => false, 'error' => 'Thiếu mã máy.' ); }
		$so_tien = (int) $so_tien;
		if ( $so_tien <= 0 ) { return array( 'ok' => false, 'error' => 'Số tiền phải lớn hơn 0.' ); }
		$luc = current_time( 'mysql' );
		return self::ghi( array(
			'ref' => 'mat-' . $ma_may . '-' . strtotime( $luc ) . '-' . $so_tien,
			'luc' => $luc, 'so_tien' => $so_tien, 'nguon' => self::TIEN_MAT,
			'ma_may' => $ma_may, 'noi_dung' => 'Thu tiền mặt · ' . $nguoi,
		) );
	}

	// ======================================================================= đọc

	/**
	 * Mốc đầu kỳ (chuỗi mysql, theo giờ website). Bản dịch `_periodStartMs`.
	 * 'all' -> chuỗi rỗng nghĩa là không lọc.
	 */
	public static function dau_ky( $ky ) {
		$nay = current_time( 'timestamp' );
		switch ( $ky ) {
			case 'today': return gmdate( 'Y-m-d 00:00:00', $nay );
			case 'week':
				/* Tuần bắt đầu THỨ HAI. `N` cho 1..7 với 1 = thứ hai — dùng `w` là tuần bắt đầu
				   chủ nhật, tức doanh thu chủ nhật rơi sang tuần sau. */
				$thu = (int) gmdate( 'N', $nay );
				return gmdate( 'Y-m-d 00:00:00', $nay - ( $thu - 1 ) * 86400 );
			case 'month': return gmdate( 'Y-m-01 00:00:00', $nay );
			case 'year':  return gmdate( 'Y-01-01 00:00:00', $nay );
		}
		return '';
	}

	/** Danh sách giao dịch trong kỳ. */
	public static function ds( $ky = 'today', $gioi_han = 500 ) {
		global $wpdb;
		$tu = self::dau_ky( $ky );
		$sql = 'SELECT * FROM ' . VHG_DB::t( 'thu' );
		if ( '' !== $tu ) { $sql = $wpdb->prepare( $sql . ' WHERE luc >= %s', $tu ); }
		$sql .= ' ORDER BY luc DESC, id DESC LIMIT ' . (int) $gioi_han;
		return VHG_DB::rows( $sql );
	}

	/**
	 * Tổng hợp một kỳ: tổng tiền, theo nguồn, theo máy, theo cơ sở.
	 *
	 * Cơ sở lấy theo BẢNG MÁY nếu máy đã khai; chưa khai thì mới đoán từ tên. Người khai luôn
	 * đúng hơn phép đoán từ chuỗi — mà đoán thì "AMTP 03" và "AMTP03" ra hai cơ sở khác nhau.
	 */
	public static function tong_hop( $ky = 'today' ) {
		$ds  = self::ds( $ky, 100000 );
		$may = VHG_May::ds_may_theo_ma();
		$ra = array( 'tong' => 0, 'so_luot' => 0, 'qr' => 0, 'qr_luot' => 0,
			'tien_mat' => 0, 'tien_mat_luot' => 0, 'theo_may' => array(), 'theo_coso' => array() );

		foreach ( $ds as $r ) {
			$tien = (int) $r['so_tien'];
			$ra['tong'] += $tien; $ra['so_luot']++;
			if ( VHG_Thu::TIEN_MAT === $r['nguon'] ) { $ra['tien_mat'] += $tien; $ra['tien_mat_luot']++; }
			else { $ra['qr'] += $tien; $ra['qr_luot']++; }

			$ten = '' !== $r['ma_may'] ? $r['ma_may'] : ( '' !== $r['ten_khai'] ? $r['ten_khai'] : '(chưa rõ máy)' );
			$cs  = self::coso_cua( $r, $may );
			if ( ! isset( $ra['theo_may'][ $ten ] ) ) {
				$ra['theo_may'][ $ten ] = array( 'may' => $ten, 'coso' => $cs, 'so_luot' => 0,
					'qr' => 0, 'tien_mat' => 0, 'tong' => 0 );
			}
			$ra['theo_may'][ $ten ]['so_luot']++;
			$ra['theo_may'][ $ten ]['tong'] += $tien;
			if ( VHG_Thu::TIEN_MAT === $r['nguon'] ) { $ra['theo_may'][ $ten ]['tien_mat'] += $tien; }
			else { $ra['theo_may'][ $ten ]['qr'] += $tien; }

			if ( ! isset( $ra['theo_coso'][ $cs ] ) ) {
				$ra['theo_coso'][ $cs ] = array( 'coso' => $cs, 'so_may' => array(), 'so_luot' => 0,
					'qr' => 0, 'tien_mat' => 0, 'tong' => 0 );
			}
			$ra['theo_coso'][ $cs ]['so_luot']++;
			$ra['theo_coso'][ $cs ]['tong'] += $tien;
			$ra['theo_coso'][ $cs ]['so_may'][ $ten ] = 1;
			if ( VHG_Thu::TIEN_MAT === $r['nguon'] ) { $ra['theo_coso'][ $cs ]['tien_mat'] += $tien; }
			else { $ra['theo_coso'][ $cs ]['qr'] += $tien; }
		}

		foreach ( $ra['theo_coso'] as $k => $v ) {
			$ra['theo_coso'][ $k ]['so_may'] = count( $v['so_may'] );
		}
		ksort( $ra['theo_may'] );
		ksort( $ra['theo_coso'] );
		$ra['theo_may']  = array_values( $ra['theo_may'] );
		$ra['theo_coso'] = array_values( $ra['theo_coso'] );
		return $ra;
	}

	/** Cơ sở của một giao dịch: bảng máy trước, đoán từ tên sau. */
	private static function coso_cua( $r, $may ) {
		$ma = (string) $r['ma_may'];
		if ( '' !== $ma && isset( $may[ $ma ] ) && '' !== $may[ $ma ]['coso_ten'] ) {
			return $may[ $ma ]['coso_ten'];
		}
		$ten = (string) $r['ten_khai'];
		if ( '' !== $ten ) {
			$k = VHG_Doc::chuan_ten( $ten );
			if ( isset( $may[ $k ] ) && '' !== $may[ $k ]['coso_ten'] ) { return $may[ $k ]['coso_ten']; }
			$doan = VHG_Doc::coso_tu_ten( $ten );
			if ( '' !== $doan ) { return $doan; }
		}
		return '(chưa gán cơ sở)';
	}
}
