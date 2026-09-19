<?php
/**
 * SỔ BẢO HIỂM XÃ HỘI — ai đóng, mỗi tháng trừ bao nhiêu.
 *
 * =================================================================================================
 * 🔴 VÌ SAO CÓ TỆP NÀY
 * =================================================================================================
 * Anh Thắng 19/09/2026, kèm ảnh tờ lương thật: *"Đối với nhân viên cố định sẽ có thêm bảo hiểm xã
 * hội. Bổ sung tab bên Phân Quyền Kế toán để kế toán chốt BHXH bạn nào đóng sẽ được thêm vào
 * bảng"*.
 *
 * Cột BHXH vốn đã có mặt trong bố cục tờ nộp (cột L), và công thức tổng lương đã trừ nó từ lâu
 * (`M = I + K − L`) — nhưng hệ chưa bao giờ có dữ liệu để đổ vào, nên ô ấy luôn trống và kế toán
 * phải gõ tay vào tệp sau khi xuất. Gõ tay ngoài tệp thì tháng sau gõ lại từ đầu, và không ai
 * soi được ai đang đóng.
 *
 * =================================================================================================
 * ⚠️ HAI QUYẾT ĐỊNH CỦA ANH THẮNG, ĐỪNG TỰ ĐỔI
 * =================================================================================================
 *   · **Kế toán gõ thẳng số tiền**, máy KHÔNG tự tính theo tỉ lệ. Đối chiếu tờ thật: Truyền lương
 *     cơ bản 4.000.000 mà BHXH 596.610 — không phải 10,5% của 4.000.000. Mức đóng bảo hiểm là một
 *     con số khác hẳn lương cơ bản, và nó nằm ngoài hệ. Tự nhân một tỉ lệ nào đó là bịa ra một con
 *     số trông như thật rồi trừ thẳng vào lương người ta.
 *   · **Khai một lần, tự lặp hằng tháng.** Sổ này là DANH SÁCH NGƯỜI ĐANG ĐÓNG, không phải một ô
 *     nhập của riêng tháng nào. Vào bảo hiểm thì bật một lần, nghỉ thì tắt.
 *
 * 🔴 VÌ TỰ LẶP NÊN PHẢI CÓ **THÁNG BẮT ĐẦU**. Không có nó thì hôm nay khai một người là bảng lương
 *    của mọi tháng trước cũng mọc thêm một khoản trừ — viết lại quá khứ của những tháng đã trả
 *    tiền xong. Mặc định là tháng đang mở, tức "từ nay trở đi", đúng thứ người khai đang nghĩ.
 *
 * ⚠️ BHXH LÀ KHOẢN **TRỪ**. Đối chiếu tờ thật: 4.000.000 − 596.610 = 3.403.390.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_Bhxh {

	const O = 'BHXH_SO';

	/** Cửa KẾ TOÁN. Đây là khoản trừ thẳng vào lương và nó tự lặp mọi tháng. */
	const QUYEN = 'bhxh';

	/** Trần vô lý cho một ô tiền BHXH một tháng — trên mức này gần như chắc là gõ dư số 0. */
	const TIEN_TOI_DA = 50000000;

	/* ====================================================================== đọc */

	public static function so() {
		$d = VHCC_Luong::cai_dat( self::O, null );
		return is_array( $d ) ? $d : array();
	}

	public static function khoa_ma( $ma ) {
		return strtolower( trim( (string) $ma ) );
	}

	/**
	 * Một người tháng ấy đóng bao nhiêu. `0.0` nghĩa là KHÔNG đóng.
	 *
	 * ⚠️ TRẢ 0 CHỨ KHÔNG TRẢ `null`. Nơi gọi đem nó đi TRỪ; `null` lỡ lọt vào một phép cộng
	 *    chuỗi là thành số 0 ở chỗ này và chuỗi rỗng ở chỗ kia.
	 */
	public static function cua( $ma_nv, $thang, $so = null ) {
		$s = ( null === $so ) ? self::so() : $so;
		$m = self::khoa_ma( $ma_nv );
		if ( '' === $m || ! isset( $s[ $m ] ) ) { return 0.0; }
		$d = $s[ $m ];
		if ( ! is_array( $d ) || empty( $d['tien'] ) ) { return 0.0; }

		/* 🔴 CHƯA TỚI THÁNG BẮT ĐẦU THÌ CHƯA TRỪ. Xem khối chú thích đầu lớp: không có chốt này
		   thì khai hôm nay là mọi tháng đã chốt xong cũng mọc thêm một khoản trừ. */
		$tt = VHCC_Luong::tien_to_thang( $thang );
		$tu = isset( $d['tu'] ) ? (string) $d['tu'] : '';
		if ( '' !== $tt && '' !== $tu && $tt < $tu ) { return 0.0; }

		return (float) $d['tien'];
	}

	/** Người này có trong sổ không (kể cả khi chưa tới tháng bắt đầu). */
	public static function co( $ma_nv, $so = null ) {
		$s = ( null === $so ) ? self::so() : $so;
		$m = self::khoa_ma( $ma_nv );
		return ( '' !== $m && isset( $s[ $m ] ) && ! empty( $s[ $m ]['tien'] ) );
	}

	/** Cả sổ, đã xếp theo tên — để vẽ ra màn. */
	public static function ds( $so = null ) {
		$s  = ( null === $so ) ? self::so() : $so;
		$ra = array();
		foreach ( $s as $m => $d ) {
			if ( ! is_array( $d ) || empty( $d['tien'] ) ) { continue; }
			$ra[] = array(
				'ma'   => isset( $d['ma'] ) ? (string) $d['ma'] : strtoupper( (string) $m ),
				'ten'  => isset( $d['ten'] ) ? (string) $d['ten'] : '',
				'tien' => (float) $d['tien'],
				'tu'   => isset( $d['tu'] ) ? (string) $d['tu'] : '',
			);
		}
		usort( $ra, function ( $a, $b ) {
			$c = strcasecmp( $a['ten'], $b['ten'] );
			return ( 0 !== $c ) ? $c : strcasecmp( $a['ma'], $b['ma'] );
		} );
		return $ra;
	}

	/** Tổng một tháng — cho dòng tóm tắt trên màn khai. */
	public static function tong_thang( $thang, $so = null ) {
		$s = ( null === $so ) ? self::so() : $so;
		$t = 0.0;
		foreach ( array_keys( $s ) as $m ) { $t += self::cua( $m, $thang, $s ); }
		return round( $t, 2 );
	}

	/* ====================================================================== ghi */

	private static function gac( $u ) {
		if ( VHCC_Vai::duoc( $u, self::QUYEN ) ) { return ''; }
		return VHCC_Vai::loi( $u, self::QUYEN, 'Sửa sổ BHXH' );
	}

	/**
	 * Cho một người vào sổ, hoặc sửa số tiền của người đã có.
	 *
	 * @param mixed $tien Số tiền trừ mỗi tháng. Để trống hay 0 = BỎ khỏi sổ.
	 * @param mixed $tu   Tháng bắt đầu `yyyy-mm`. Để trống = tháng đang chạy.
	 */
	public static function dat( $u, $ma_nv, $tien, $tu = '' ) {
		$loi = self::gac( $u );
		if ( '' !== $loi ) { return array( 'ok' => false, 'error' => $loi ); }

		$ma = trim( (string) $ma_nv );
		$m  = self::khoa_ma( $ma );
		if ( '' === $m ) { return array( 'ok' => false, 'error' => 'Thiếu mã nhân viên.' ); }

		$hs = VHCC_NhanSu::ho_so( $ma );
		if ( ! $hs ) {
			return array( 'ok' => false, 'error' => 'Không có ai mang mã "' . $ma
				. '" trong sổ nhân sự. Kiểm lại mã — trừ tiền nhầm người thì tháng sau mới lộ.' );
		}

		$s = self::so();
		$t = VHCC_NhanSu::so_tien( $tien );
		if ( $t <= 0 ) {
			/* Số 0 hay ô trống = BỎ khỏi sổ, không phải "đóng 0đ". Một dòng 0đ nằm trong sổ
			   trông như đã xét xong và bằng không. */
			unset( $s[ $m ] );
			VHCC_Luong::dat_cai_dat( self::O, $s, $u );
			return array( 'ok' => true, 'bo' => true, 'ma' => $ma );
		}
		if ( $t > self::TIEN_TOI_DA ) {
			return array( 'ok' => false, 'error' => 'Số tiền BHXH quá lớn ('
				. number_format( $t, 0, ',', '.' ) . 'đ) — gõ dư số 0?' );
		}

		$tt = VHCC_Luong::tien_to_thang( $tu );
		if ( '' === $tt ) { $tt = substr( (string) current_time( 'Y-m-d' ), 0, 7 ); }

		$s[ $m ] = array(
			'ma'   => strtoupper( $ma ),
			/* Nhớ tên lúc khai để màn còn đọc được cả khi người ấy đã nghỉ và bị lọc khỏi
			   danh sách nhân sự — một dòng trừ tiền không tên là một dòng không ai dám xoá. */
			'ten'  => (string) ( isset( $hs['ho_ten'] ) ? $hs['ho_ten'] : '' ),
			'tien' => round( $t, 2 ),
			'tu'   => $tt,
		);
		VHCC_Luong::dat_cai_dat( self::O, $s, $u );
		return array( 'ok' => true, 'ma' => strtoupper( $ma ), 'tien' => round( $t, 2 ), 'tu' => $tt );
	}

	public static function xoa( $u, $ma_nv ) {
		$loi = self::gac( $u );
		if ( '' !== $loi ) { return array( 'ok' => false, 'error' => $loi ); }
		$m = self::khoa_ma( $ma_nv );
		$s = self::so();
		if ( '' === $m || ! isset( $s[ $m ] ) ) {
			return array( 'ok' => false, 'error' => 'Người này không có trong sổ BHXH.' );
		}
		$ten = isset( $s[ $m ]['ten'] ) ? (string) $s[ $m ]['ten'] : strtoupper( $m );
		unset( $s[ $m ] );
		VHCC_Luong::dat_cai_dat( self::O, $s, $u );
		return array( 'ok' => true, 'ten' => $ten );
	}
}
