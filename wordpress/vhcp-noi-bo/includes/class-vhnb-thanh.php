<?php
/**
 * CHỌN NÚT NÀO HIỆN TRÊN THANH ĐẦU TRANG NỘI BỘ.
 *
 * =================================================================================================
 * VIỆC NÓ GIẢI QUYẾT
 * =================================================================================================
 * Anh Thắng 17/09/2026: *"ẩn bớt các trang"*. Thanh đầu trang đang bày MỌI app mà `VHTC_Trang`
 * biết — Cổng, Chấm công, Chấm công online, năm bản Vận hành chi phí theo vùng, Ghế massage, Dự
 * án, Thư viện hợp đồng, Thoát. Mười hai nút, tràn xuống hai dòng, và trên điện thoại thì ba.
 *
 * Hàng nút dài không chỉ xấu. Nó làm đúng một việc tệ: cái nút người ta cần hằng ngày (Chấm công)
 * nằm lẫn giữa mười cái họ chưa bấm bao giờ, nên mỗi lần phải đọc cả hàng.
 *
 * =================================================================================================
 * 🔴 BA CHỐT
 * =================================================================================================
 * 1. ẨN KHỎI THANH KHÔNG PHẢI CẤM QUYỀN. Đây thuần là dọn màn hình: người có quyền vào trang ấy
 *    vẫn vào được bằng địa chỉ, và vẫn thấy nó ở **Cổng K&H**. Ai nhầm hai việc này rồi dùng nó
 *    thay cho phân quyền thì cấm hụt mà cứ tưởng đã cấm — nên câu ấy phải nằm ngay trên màn khai.
 *
 * 2. 🔴 KHÔNG ẨN ĐƯỢC CỔNG K&H. Cổng là trang liệt kê mọi app; ẩn nốt nó thì mấy trang vừa ẩn
 *    không còn đường nào tới nữa ngoài gõ tay địa chỉ. Ẩn cả hàng nút mà vẫn còn Cổng thì không
 *    mất gì; ẩn cả Cổng là nhốt người dùng trong đúng một trang.
 *
 * 3. 🔴 NHỚ THEO ĐỊA CHỈ, VÀ ĐỔI ĐỊA CHỈ THÌ NÚT HIỆN LẠI. Một trang đổi đường dẫn là khoá cũ
 *    không khớp nữa. Hai hướng hỏng ngược nhau: hoặc nút cũ biến mất, hoặc nút mới hiện ra. Chọn
 *    HIỆN RA — thừa một nút thì nhìn thấy ngay và bấm bỏ tích lại; mất một nút thì không ai biết
 *    để đi tìm, và người cần nó chỉ kết luận "trang kia hỏng rồi".
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHNB_Thanh {

	/** Danh sách địa chỉ BỊ ẨN. Nhớ cái bị ẩn chứ không nhớ cái được hiện — xem chốt 3. */
	const O = 'vhnb_thanh_an';

	/** Địa chỉ của Cổng K&H, nếu có. '' = chưa cài plugin Cổng. */
	public static function cong() {
		if ( class_exists( 'VHTC_Trang' ) && method_exists( 'VHTC_Trang', 'url' ) ) {
			return (string) VHTC_Trang::url();
		}
		return '';
	}

	/** Trang này có phải Cổng không — Cổng thì không ẩn được (chốt 2). */
	public static function la_cong( $url ) {
		$c = self::cong();
		return ( '' !== $c && (string) $url === $c );
	}

	public static function ds_an() {
		$d = get_option( self::O );
		return is_array( $d ) ? array_values( array_filter( array_map( 'strval', $d ) ) ) : array();
	}

	public static function dat_an( $ds ) {
		$sach = array();
		foreach ( (array) $ds as $u ) {
			$u = trim( (string) $u );
			if ( '' === $u ) { continue; }
			if ( self::la_cong( $u ) ) { continue; }          // chốt 2
			if ( ! in_array( $u, $sach, true ) ) { $sach[] = $u; }
		}
		update_option( self::O, $sach, false );
		return array( 'ok' => true, 'so' => count( $sach ) );
	}

	/** Trang này có được hiện trên thanh không. */
	public static function hien( $url ) {
		if ( self::la_cong( $url ) ) { return true; }
		return ! in_array( (string) $url, self::ds_an(), true );
	}

	/**
	 * Danh sách đầy đủ để màn khai vẽ ô tích — gồm cả trang đang bị ẩn.
	 *
	 * ⚠️ PHẢI LẤY TỪ CÙNG MỘT NGUỒN với thanh thật (`VHNB_Trang::ds_trang_khac`). Dựng danh sách
	 *    riêng ở đây là hai danh sách, và trang nào chỉ có ở một bên thì hoặc không bao giờ ẩn
	 *    được, hoặc bị ẩn mà không có ô nào bỏ tích lại.
	 */
	public static function ds_khai() {
		if ( ! class_exists( 'VHNB_Trang' ) || ! method_exists( 'VHNB_Trang', 'ds_trang_khac' ) ) {
			return array();
		}
		$ra = array();
		foreach ( VHNB_Trang::ds_trang_khac() as $t ) {
			$t['laCong'] = self::la_cong( $t['url'] );
			$t['hien']   = self::hien( $t['url'] );
			$ra[] = $t;
		}
		return $ra;
	}
}
