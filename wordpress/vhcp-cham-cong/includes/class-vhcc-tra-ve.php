<?php
/**
 * CHỜ TRẢ VỀ NHÂN SỰ — người đã nghỉ, tháng này không phát sinh công thì trả lại kho nhân sự.
 *
 * =================================================================================================
 * Anh Thắng 28/08/2026, nguyên văn:
 *   *"Thêm dấu tích, nhân viên nghỉ việc, trong tháng đó không phát sinh công, nó tự đẩy ngược về
 *   nhân sự. Khi tích thì trong cửa hàng đó vẫn có, nhưng nằm phía là chờ trả về nhân sự"*.
 * =================================================================================================
 *
 * 🔴 TÍCH KHÔNG PHẢI LÀ XOÁ, VÀ CŨNG KHÔNG PHẢI LÀ TRẢ NGAY.
 *    Tích xong người ấy VẪN Ở TRONG CỬA HÀNG — chỉ là nằm riêng một nhóm ở cuối bảng. Đó đúng
 *    là điều anh dặn, và nó có lý: người nghỉ giữa tháng vẫn còn công của những ngày đã làm,
 *    và bảng lương tháng ấy vẫn phải tra ra tên họ. Đẩy đi ngay là bảng lương có mã mà không
 *    có người.
 *
 * 🔴 CHỈ TRẢ KHI THÁNG ẤY ĐÃ HẾT.
 *    "Trong tháng đó không phát sinh công" đọc vào tháng ĐANG CHẠY thì luôn đúng vào ngày mùng
 *    một — và cả cửa hàng bị trả về nhân sự sạch trong một buổi sáng. Tháng phải qua rồi mới
 *    biết nó có phát sinh công hay không.
 *
 * ⚠️ TRẢ VỀ = GỠ KHỎI CỬA HÀNG, KHÔNG PHẢI XOÁ HỒ SƠ. Hồ sơ còn nguyên trong sổ nhân sự, mọi
 *    lượt chấm công cũ còn nguyên. Chỉ ô "Cửa hàng" được xoá trắng, để người ấy thôi hiện trong
 *    danh sách của cửa hàng và quay về kho chung — chỗ bên nhân sự xếp họ đi đâu tiếp.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_TraVe {

	/** Cùng cửa quyền với khai ca và duyệt đơn: việc trong phạm vi một cửa hàng. */
	const QUYEN = 'lich_lam';

	/* ====================================================================== tích / bỏ tích */

	/**
	 * Tích (hoặc bỏ tích) "chờ trả về nhân sự" cho một người.
	 *
	 * ⚠️ Cơ sở đọc từ CHÍNH HỒ SƠ, không từ biểu mẫu — mã NV gõ tay được, và một cửa hàng
	 *    trưởng gõ đúng mã là tích được người của cửa hàng khác.
	 */
	public static function dat( $u, $ma_nv, $bat ) {
		global $wpdb;
		if ( ! VHCC_Vai::duoc( $u, self::QUYEN ) ) {
			return array( 'ok' => false,
				'error' => 'Đánh dấu chờ trả về nhân sự cần vai Cửa hàng trưởng trở lên.' );
		}
		$ma = trim( (string) $ma_nv );
		if ( '' === $ma ) { return array( 'ok' => false, 'error' => 'Thiếu Mã NV.' ); }
		$hs = VHCC_NhanSu::ho_so( $ma );
		if ( ! $hs ) { return array( 'ok' => false, 'error' => 'Không tìm thấy hồ sơ ' . $ma . '.' ); }
		$cs = VHCC_NhanSu::chuan_coso( isset( $hs['cua_hang'] ) ? $hs['cua_hang'] : '' );
		if ( '' === $cs ) {
			return array( 'ok' => false, 'error' => 'Hồ sơ ' . $ma . ' không thuộc cửa hàng nào — '
				. 'đã ở kho nhân sự rồi, không có gì để trả về.' );
		}
		if ( ! VHCC_NhanSu::co_quyen_coso( $u, $cs ) ) {
			return array( 'ok' => false, 'error' => 'Người này thuộc cửa hàng khác.' );
		}
		$bat = (bool) $bat;
		$wpdb->update( VHCC_DB::t( 'nhan_vien' ), array(
			'cho_tra_ve'  => $bat ? 1 : 0,
			'cho_tra_luc' => $bat ? current_time( 'mysql' ) : null,
			'cho_tra_boi' => $bat ? trim( (string) ( isset( $u['name'] ) ? $u['name'] : '' ) ) : '',
		), array( 'ma_nv' => $ma ) );
		return array( 'ok' => true, 'ma_nv' => $ma, 'bat' => $bat, 'coSo' => $cs,
			'ho_ten' => trim( (string) ( isset( $hs['ho_ten'] ) ? $hs['ho_ten'] : '' ) ) );
	}

	/** Người này có đang chờ trả về không. */
	public static function dang_cho( $hs ) {
		if ( ! is_array( $hs ) ) { return false; }
		return ! empty( $hs['cho_tra_ve'] );
	}

	/** Mã của mọi người đang chờ trả về ở một cửa hàng — một lượt hỏi cho cả lưới. */
	public static function ds_cho( $coso ) {
		global $wpdb;
		$cs = VHCC_NhanSu::chuan_coso( $coso );
		if ( '' === $cs ) { return array(); }
		/* Người tích thêm cơ sở này cũng phải hiện cờ "chờ trả về" ở lưới của cơ sở này — họ có
		   mặt làm việc ở đây thật, và người bấm nút trả về đang đứng ở đây. Mệnh đề nới, lọc
		   chính xác ngay dưới. */
		$dk_cs = VHCC_NhanSu::dk_sql_coso( $cs );
		$r = $wpdb->get_results( $wpdb->prepare(
			'SELECT ma_nv, ho_ten, cua_hang, coso_phu, trang_thai_lam_viec, cho_tra_luc, cho_tra_boi FROM '
			. VHCC_DB::t( 'nhan_vien' ) . ' WHERE ' . $dk_cs['sql'] . ' AND cho_tra_ve=1',
			$dk_cs['tv'] ), ARRAY_A );
		$ra = array();
		foreach ( (array) $r as $x ) {
			if ( ! VHCC_NhanSu::hs_thuoc_coso( $x, $cs ) ) { continue; }
			$ra[ trim( (string) $x['ma_nv'] ) ] = $x;
		}
		return $ra;
	}

	/* ====================================================================== tự trả về */

	/**
	 * CÓ NÊN TRẢ NGƯỜI NÀY VỀ NHÂN SỰ KHÔNG.
	 *
	 * Hàm THUẦN — vào là bốn dữ kiện, ra là true/false. Đây là chỗ quyết định sửa dữ liệu của
	 * người thật, nên nó phải thử được bằng con số trần, không cần dựng bảng, không cần chờ
	 * sang tháng sau mới biết đúng sai.
	 *
	 * Ba điều kiện, thiếu một là KHÔNG trả:
	 *   1. đã tích "chờ trả về"        — người ta phải chủ động đánh dấu, máy không tự quyết
	 *   2. tháng ấy KHÔNG có lượt chấm — đúng chữ anh dặn
	 *   3. tháng ấy ĐÃ HẾT             — xem chú thích đầu tệp
	 *
	 * ⚠️ CỐ Ý KHÔNG đòi "trạng thái làm việc = Đã nghỉ". Sổ nhân sự nạp từ Sheets có ô trạng
	 *    thái để trống hàng loạt; đòi thêm điều kiện ấy là cái tích của cửa hàng trưởng không
	 *    bao giờ ăn, mà chẳng có gì nói cho họ biết vì sao.
	 */
	public static function nen_tra( $da_tich, $so_luot_thang, $thang, $hom_nay = '' ) {
		if ( ! $da_tich ) { return false; }
		if ( (int) $so_luot_thang > 0 ) { return false; }
		return self::thang_da_het( $thang, $hom_nay );
	}

	/** Tháng này đã qua hẳn chưa (so với hôm nay). Hàm thuần, tách riêng để thử bằng chuỗi trần. */
	public static function thang_da_het( $thang, $hom_nay = '' ) {
		$th = trim( (string) $thang );
		if ( ! preg_match( '/^\d{4}-\d{2}$/', $th ) ) { return false; }
		if ( '' === $hom_nay ) { $hom_nay = substr( (string) current_time( 'Y-m-d' ), 0, 10 ); }
		return ( $th < substr( (string) $hom_nay, 0, 7 ) );
	}

	/**
	 * QUÉT một cửa hàng trong một tháng và trả những ai đủ điều kiện về nhân sự.
	 *
	 * @return array ds[] = [ ma_nv, ho_ten ] những người vừa được trả.
	 */
	public static function quet( $coso, $thang, $nguoi = '' ) {
		global $wpdb;
		$cs = VHCC_NhanSu::chuan_coso( $coso );
		$th = trim( (string) $thang );
		$ra = array();
		if ( '' === $cs || ! preg_match( '/^\d{4}-\d{2}$/', $th ) ) { return $ra; }
		if ( ! self::thang_da_het( $th ) ) { return $ra; }

		foreach ( self::ds_cho( $cs ) as $ma => $x ) {
			$so = (int) $wpdb->get_var( $wpdb->prepare(
				'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'cham_cong' )
				. ' WHERE ma_nv=%s AND ngay LIKE %s', $ma, $th . '-%' ) );
			if ( ! self::nen_tra( true, $so, $th ) ) { continue; }
			/* 🔴 GỠ KHỎI CỬA HÀNG, KHÔNG XOÁ HỒ SƠ. Và bỏ luôn cái tích: người đã về kho thì
			   không còn "đang chờ trả về" nữa — để nguyên là lần sau xếp họ vào cửa hàng mới,
			   cái tích cũ lại âm thầm đẩy họ ra. */
			$wpdb->update( VHCC_DB::t( 'nhan_vien' ), array(
				'cua_hang'    => '',
				'cho_tra_ve'  => 0,
				'cho_tra_luc' => null,
				'cho_tra_boi' => '',
				'cap_nhat'    => current_time( 'mysql' ),
			), array( 'ma_nv' => $ma ) );
			$ra[] = array( 'ma_nv' => $ma, 'ho_ten' => trim( (string) $x['ho_ten'] ),
				'coSo' => $cs, 'thang' => $th, 'boi' => (string) $nguoi );
		}
		return $ra;
	}
}
