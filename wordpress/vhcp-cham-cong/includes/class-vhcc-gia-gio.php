<?php
/**
 * SỔ ĐƠN GIÁ GIỜ CỦA CƠ SỞ — ba tầng: cả chuỗi · từng cơ sở · từng người.
 *
 * =============================================================================================
 * 🔴 VÌ SAO PHẢI CÓ SỔ NÀY
 * =============================================================================================
 * Anh Thắng 15/09/2026 gửi file `LƯƠNG CƠ SỞ - T08.2026`: *"mỗi cơ sở sẽ xuất bảng công giờ ra
 * theo mẫu file như này"*. Trong file có cột **Tiền/h** — 21.000 · 22.000 · 23.000 · 24.000 ·
 * 25.000 · 26.000 · 27.000 — mà hệ thống KHÔNG có lấy một chỗ nào chứa số ấy.
 *
 * Trước bản này, cơ sở thường (không phải Máy tự động, không phải Văn phòng) chạy qua
 * `VHCC_Luong::bang_cong_tho()` và trả về `coLuong => false`: chỉ có GIỜ, không có một đồng nào.
 * Muốn ra tiền thì phải có đơn giá, và đơn giá phải do người khai — không có cách nào suy ra.
 *
 * =============================================================================================
 * 🔴 BA TẦNG, VÀ VÌ SAO KHÔNG PHẢI MỘT
 * =============================================================================================
 * Đọc chính file của anh Thắng thì thấy cả ba tầng đều cần thật:
 *
 *   · Cùng CHỨC VỤ mà khác CƠ SỞ là khác giá — "NV" ở Aeon Tân Phú 22.000, ở Lotte Gò Vấp
 *     23.000. Nên không thể có một bảng duy nhất cho cả chuỗi.
 *   · Cùng CƠ SỞ mà khác CHỨC VỤ là khác giá — Aeon Bình Tân: Lái Tàu 23.000, Lơ Tàu 21.000.
 *     Nên khoá phải là (cơ sở × chức vụ), không phải chỉ cơ sở.
 *   · Vẫn có người lệch khỏi mức chung — Lotte Gò Vấp: ba người "NV" ăn 23.000, riêng Bùi Xuân
 *     Thuận 25.000. Bắt khai tay 240 người là không ai làm; bỏ hẳn đường đè riêng là tháng nào
 *     cũng phải sửa tay file Excel sau khi xuất, tức là xuất ra để đó.
 *
 * Thứ tự tra: **người → cơ sở → chung**. Tầng dưới chỉ đỡ khi tầng trên chưa khai.
 *
 * =============================================================================================
 * 🔴 CHƯA KHAI THÌ TRẢ 0 VÀ NÓI RA, TUYỆT ĐỐI KHÔNG ĐOÁN
 * =============================================================================================
 * Cùng một luật với `ngayCongThang` của khối Văn phòng (xem `the_thieu_khai()`): **hệ thống
 * KHÔNG đoán đơn giá**. Đoán là sai tiền của cả một nhóm người cùng lúc, mà bảng vẫn đầy số nên
 * chẳng ai nghi — và tới lúc phát hiện thì lương đã trả rồi.
 *
 * Nên `tra()` trả về cả `tu` (giá này đến từ tầng nào), và `khong` nghĩa là CHƯA KHAI. Chỗ dựng
 * bảng phải đếm số dòng `khong` rồi nói thẳng ra màn, chứ không lặng lẽ nhân với 0.
 *
 * ⚠️ KHOÁ CHỨC VỤ BỎ DẤU, BỎ HOA THƯỜNG (`VHCC_Luong::bo_chu`). Hồ sơ do người gõ tay nên cùng
 *    một việc có đủ kiểu viết — "Lái Tàu", "lái tàu", "LAI TAU", "Lái tàu ". Khoá theo chuỗi
 *    thô là khai một kiểu rồi tra kiểu khác không thấy, và người khai đinh ninh mình khai rồi.
 *    Tên hiển thị vẫn giữ nguyên bản người gõ — khoá để TRA, tên để ĐỌC.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_GiaGio {

	/** Khoá trong bảng `cai_dat`. */
	const O = 'GIA_GIO_COSO';

	/** Khai đơn giá = đụng vào tiền của cả cơ sở → bậc Kế toán trở lên, cùng cửa với `luong`. */
	const QUYEN = 'luong';

	/** Trần vô lý: trên mức này gần như chắc chắn là gõ dư số 0. */
	const TRAN = 2000000;

	/* ==================================================================== đọc sổ */

	/**
	 * Cả sổ, đã chuẩn hoá về đúng ba nhánh.
	 *
	 * ⚠️ ĐỌC KHÔNG ĐƯỢC GHI. Bản đầu của mấy sổ khác từng vừa đọc vừa `update_option` để "gieo
	 *    mặc định" — làm mọi bài soát chỉ-đọc thành nói dối, và một lượt xem bảng lương lại sửa
	 *    cấu hình. Ở đây chỉ đọc, thiếu thì trả nhánh rỗng.
	 */
	public static function so() {
		$d = VHCC_Luong::cai_dat( self::O, null );
		$o = array( 'chung' => array(), 'coso' => array(), 'nguoi' => array() );
		if ( ! is_array( $d ) ) { return $o; }
		foreach ( array( 'chung', 'coso', 'nguoi' ) as $nhanh ) {
			if ( isset( $d[ $nhanh ] ) && is_array( $d[ $nhanh ] ) ) { $o[ $nhanh ] = $d[ $nhanh ]; }
		}
		return $o;
	}

	/** Khoá tra của một chức vụ: bỏ dấu, bỏ hoa thường, bỏ khoảng trắng. */
	public static function khoa_cv( $cv ) {
		return VHCC_Luong::bo_chu( (string) $cv );
	}

	/** Khoá tra của một cơ sở — cùng phép chuẩn hoá, để `CS_` và hoa thường không đẻ ra hai sổ. */
	public static function khoa_cs( $coso ) {
		return VHCC_Luong::bo_chu( preg_replace( '/^CS_/i', '', trim( (string) $coso ) ) );
	}

	/** Khoá tra của một mã NV. */
	public static function khoa_ma( $ma ) {
		return strtolower( trim( (string) $ma ) );
	}

	/**
	 * Đơn giá giờ của (cơ sở, chức vụ, người).
	 *
	 * @return array('gia' => float, 'tu' => 'nguoi'|'coso'|'chung'|'khong')
	 */
	public static function tra( $coso, $chuc_vu, $ma_nv = '', $so = null ) {
		$so = ( null === $so ) ? self::so() : $so;
		$kcv = self::khoa_cv( $chuc_vu );
		$kcs = self::khoa_cs( $coso );
		$kma = self::khoa_ma( $ma_nv );

		/* Tầng 1 — riêng người. `*` = mọi chức vụ của người ấy; khoá chức vụ cụ thể thắng `*`. */
		if ( '' !== $kma && isset( $so['nguoi'][ $kma ] ) && is_array( $so['nguoi'][ $kma ] ) ) {
			$n = $so['nguoi'][ $kma ];
			if ( '' !== $kcv && isset( $n[ $kcv ] ) && (float) $n[ $kcv ] > 0 ) {
				return array( 'gia' => (float) $n[ $kcv ], 'tu' => 'nguoi' );
			}
			if ( isset( $n['*'] ) && (float) $n['*'] > 0 ) {
				return array( 'gia' => (float) $n['*'], 'tu' => 'nguoi' );
			}
		}
		/* Tầng 2 — cơ sở × chức vụ. */
		if ( '' !== $kcs && '' !== $kcv
			&& isset( $so['coso'][ $kcs ][ $kcv ] ) && (float) $so['coso'][ $kcs ][ $kcv ] > 0 ) {
			return array( 'gia' => (float) $so['coso'][ $kcs ][ $kcv ], 'tu' => 'coso' );
		}
		/* Tầng 3 — cả chuỗi, theo chức vụ. */
		if ( '' !== $kcv && isset( $so['chung'][ $kcv ] ) && (float) $so['chung'][ $kcv ] > 0 ) {
			return array( 'gia' => (float) $so['chung'][ $kcv ], 'tu' => 'chung' );
		}
		/* 🔴 CHƯA KHAI. Không đoán, và nói rõ là chưa khai — xem chú thích đầu tệp. */
		return array( 'gia' => 0.0, 'tu' => 'khong' );
	}

	/* ==================================================================== ghi sổ */

	/**
	 * Làm sạch một bảng {chức vụ => đơn giá}.
	 *
	 * ⚠️ Ô ĐỂ TRỐNG = XOÁ khỏi sổ, KHÔNG phải ghi 0. Ghi 0 thì tầng dưới bị chặn lại (0 không
	 *    phải "chưa khai" theo phép tra ở trên nếu mình nhận số 0), nên mức chung không đỡ được
	 *    nữa mà màn thì trông như đã xoá. Xoá hẳn khoá mới đúng nghĩa "thôi không khai riêng".
	 *
	 * @return array( 'ds' => array, 'loi' => array )  loi = những chức vụ gõ sai
	 */
	public static function sach_bang( $vao ) {
		$ds  = array();
		$loi = array();
		foreach ( (array) $vao as $cv => $gia ) {
			$k = ( '*' === $cv ) ? '*' : self::khoa_cv( $cv );
			if ( '' === $k ) { continue; }
			$s = trim( (string) $gia );
			if ( '' === $s ) { continue; }                       // trống = xoá
			$n = VHCC_NhanSu::so_tien( $s );
			if ( $n <= 0 || $n > self::TRAN ) { $loi[] = (string) $cv; continue; }
			$ds[ $k ] = (float) $n;
		}
		return array( 'ds' => $ds, 'loi' => $loi );
	}

	private static function ghi( $u, $so ) {
		return VHCC_Luong::dat_cai_dat( self::O, $so, $u );
	}

	private static function gac( $u ) {
		if ( ! VHCC_Vai::duoc( $u, self::QUYEN ) ) {
			return VHCC_Vai::loi( $u, self::QUYEN, 'Khai đơn giá giờ' )
				. ' Đơn giá quyết định tiền của cả cơ sở, nên nó đứng cùng cửa với bảng lương.';
		}
		return '';
	}

	public static function dat_chung( $u, $bang ) {
		$chan = self::gac( $u );
		if ( '' !== $chan ) { return array( 'ok' => false, 'error' => $chan ); }
		$r  = self::sach_bang( $bang );
		if ( $r['loi'] ) {
			return array( 'ok' => false, 'error' => 'Đơn giá phải là số dương và dưới '
				. number_format( self::TRAN, 0, ',', '.' ) . 'đ/giờ — sai ở: '
				. implode( ', ', $r['loi'] ) . '.' );
		}
		$so = self::so();
		$so['chung'] = $r['ds'];
		self::ghi( $u, $so );
		return array( 'ok' => true, 'so' => count( $r['ds'] ) );
	}

	public static function dat_coso( $u, $coso, $bang ) {
		$chan = self::gac( $u );
		if ( '' !== $chan ) { return array( 'ok' => false, 'error' => $chan ); }
		$kcs = self::khoa_cs( $coso );
		if ( '' === $kcs ) { return array( 'ok' => false, 'error' => 'Thiếu cơ sở.' ); }
		$r = self::sach_bang( $bang );
		if ( $r['loi'] ) {
			return array( 'ok' => false, 'error' => 'Đơn giá phải là số dương và dưới '
				. number_format( self::TRAN, 0, ',', '.' ) . 'đ/giờ — sai ở: '
				. implode( ', ', $r['loi'] ) . '.' );
		}
		$so = self::so();
		if ( $r['ds'] ) { $so['coso'][ $kcs ] = $r['ds']; }
		else            { unset( $so['coso'][ $kcs ] ); }
		self::ghi( $u, $so );
		return array( 'ok' => true, 'so' => count( $r['ds'] ) );
	}

	public static function dat_nguoi( $u, $ma_nv, $bang ) {
		$chan = self::gac( $u );
		if ( '' !== $chan ) { return array( 'ok' => false, 'error' => $chan ); }
		$kma = self::khoa_ma( $ma_nv );
		if ( '' === $kma ) { return array( 'ok' => false, 'error' => 'Thiếu mã nhân viên.' ); }
		$r = self::sach_bang( $bang );
		if ( $r['loi'] ) {
			return array( 'ok' => false, 'error' => 'Đơn giá phải là số dương và dưới '
				. number_format( self::TRAN, 0, ',', '.' ) . 'đ/giờ — sai ở: '
				. implode( ', ', $r['loi'] ) . '.' );
		}
		$so = self::so();
		if ( $r['ds'] ) { $so['nguoi'][ $kma ] = $r['ds']; }
		else            { unset( $so['nguoi'][ $kma ] ); }
		self::ghi( $u, $so );
		return array( 'ok' => true, 'so' => count( $r['ds'] ) );
	}
}
