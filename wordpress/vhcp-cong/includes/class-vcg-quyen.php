<?php
/**
 * VCG_Quyen — ai được làm gì.
 *
 * Anh Thắng chốt 25/08/2026: bốn vai — Admin · Quản lý vùng · Kế toán · Cửa hàng trưởng.
 *
 * MỌI PHÉP KIỂM QUYỀN ĐI QUA ĐÂY, không rải if khắp nơi. Rải ra là kiểu gì cũng sót một chỗ,
 * và chỗ sót đó không ai thấy cho tới khi có người vào được thứ không thuộc về họ.
 *
 * Hai chỗ CỐ Ý SIẾT, ghi lý do để người sau không nới ra vì tưởng là thiếu sót:
 *
 *   1. Sheet NHÂN VIÊN chỉ Admin nạp. Nó là dữ liệu chung của mọi plugin — lương, hợp đồng,
 *      ghế đều đọc từ đó. Một lần nạp nhầm tệp cũ là sai lan sang cả hệ và rất khó lần ra.
 *
 *   2. Kế toán CHỈ XEM, không nạp. Kế toán là người ĐỐI CHIẾU bảng công để tính lương; vừa nạp
 *      vừa đối chiếu thì không còn ai soát chéo, sai một lần là không ai bắt được. Bản gốc
 *      `Code.gs` cũng cố ý không cho kế toán quyền gì thêm trong Chấm công.
 *
 * @package vhcp-cong
 */

if ( ! defined( 'ABSPATH' ) ) { if ( ! defined( 'VCG_TEST' ) ) { exit; } }

class VCG_Quyen {

	const ADMIN   = 'ADMIN';
	const QL_VUNG = 'QUAN_LY';
	const KE_TOAN = 'KE_TOAN';
	const CHT     = 'CUA_HANG_TRUONG';

	/** Nạp sheet nhân viên — dữ liệu chung của mọi plugin. */
	public static function nap_nhan_vien( $vai ) {
		return self::ADMIN === self::chuan( $vai );
	}

	/** Nạp sheet cơ sở. Kế toán KHÔNG nạp — xem ghi chú đầu tệp. */
	public static function nap_co_so( $vai ) {
		$v = self::chuan( $vai );
		return in_array( $v, array( self::ADMIN, self::QL_VUNG, self::CHT ), true );
	}

	/** Xem bảng chấm công. Cả bốn vai đều xem được — khác nhau ở PHẠM VI, không ở việc có xem được hay không. */
	public static function xem( $vai ) {
		return in_array( self::chuan( $vai ),
			array( self::ADMIN, self::QL_VUNG, self::KE_TOAN, self::CHT ), true );
	}

	/**
	 * Cơ sở nào người này được đụng tới.
	 *
	 * Trả `true` nghĩa là MỌI cơ sở; trả mảng nghĩa là chỉ những cơ sở trong mảng.
	 *
	 * 🔴 Trả `true` và trả `array()` là hai thứ NGƯỢC NHAU: một bên mở hết, một bên đóng hết.
	 *    Nơi gọi phải phân biệt bằng `true === $pv`, không được dùng `empty()` — `empty(true)`
	 *    là false nhưng `empty(array())` cũng là false ở PHP 8 chỉ khi mảng rỗng... nói cách
	 *    khác đừng đoán, cứ so sánh thẳng với `true`.
	 */
	public static function pham_vi( $vai, $co_so_phu_trach ) {
		$v = self::chuan( $vai );
		if ( self::ADMIN === $v || self::KE_TOAN === $v ) { return true; }
		$ds = array();
		foreach ( (array) $co_so_phu_trach as $c ) {
			$c = trim( (string) $c );
			if ( '' !== $c ) { $ds[] = $c; }
		}
		return $ds;
	}

	/** Người này có được đụng vào đúng cơ sở này không. */
	public static function duoc_co_so( $vai, $co_so_phu_trach, $co_so ) {
		$pv = self::pham_vi( $vai, $co_so_phu_trach );
		if ( true === $pv ) { return true; }
		return in_array( trim( (string) $co_so ), $pv, true );
	}

	/**
	 * Chuẩn hoá tên vai.
	 *
	 * Vai trò trong hệ cũ là CHUỖI TỰ DO — `saveRole` chỉ `.toUpperCase()` rồi ghi thẳng, không
	 * hề chặn danh sách. Nên dữ liệu thật có thể là 'admin', 'Admin', 'ADMIN ', hay cả
	 * 'CỬA HÀNG TRƯỞNG' có dấu. So chuỗi thô là lúc thì đúng lúc thì trượt, mà trượt về phía
	 * TỪ CHỐI thì người ta báo ngay, còn trượt về phía CHO PHÉP thì không ai báo.
	 */
	public static function chuan( $vai ) {
		$v = strtoupper( trim( (string) $vai ) );
		$v = str_replace( array( ' ', '-' ), '_', $v );
		$bang = array(
			'CHT'              => self::CHT,
			'CUAHANGTRUONG'    => self::CHT,
			'QUANLY'           => self::QL_VUNG,
			'QUAN_LY_VUNG'     => self::QL_VUNG,
			'KETOAN'           => self::KE_TOAN,
		);
		$khong_gach = str_replace( '_', '', $v );
		if ( isset( $bang[ $v ] ) ) { return $bang[ $v ]; }
		if ( isset( $bang[ $khong_gach ] ) ) { return $bang[ $khong_gach ] ; }
		return $v;
	}
}
