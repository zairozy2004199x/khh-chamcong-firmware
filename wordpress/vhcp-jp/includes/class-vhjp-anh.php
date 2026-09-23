<?php
/**
 * ẢNH — phần ĐẾM và CẢNH BÁO của `JP2_07_Anh.gs`.
 *
 * =============================================================================================
 * ⚠️ MỚI CÓ PHẦN ĐẾM, CHƯA CÓ PHẦN TẢI ẢNH LÊN.
 * =============================================================================================
 * Bản gốc cất ảnh trên Google Drive (`jpUploadPhoto` · `jpAnhXem` · `jpDeletePhoto`). Đường ấy
 * chưa chuyển — nó phải đi qua thư viện ảnh của WordPress, và đó là một lát riêng.
 *
 * Nhưng phần ĐẾM thì chuyển được ngay và phải chuyển ngay, vì nó KHÔNG đụng Drive: nó chỉ đọc
 * bảng dòng · khu vực · ảnh · máy · cụm rồi so với cấu hình. Có nó thì:
 *   · lúc NỘP mới chốt được cảnh báo thiếu ảnh vào báo cáo để kế toán còn thấy;
 *   · lúc MỞ báo cáo mới trả được `anhTienDo` — thứ bản gốc cố ý gộp vào cùng một lượt gọi
 *     vì *"bấm cái nào cũng load lâu quá"*.
 *
 * ---------------------------------------------------------------------------------------------
 * 🔴 THIẾU ẢNH THÌ CẢNH BÁO, KHÔNG CHẶN NỘP.
 * ---------------------------------------------------------------------------------------------
 * Mạng ở trung tâm thương mại hay hỏng; chặn là nhân viên mắc kẹt cả buổi. Nhưng phải LƯU LẠI
 * vào báo cáo, chứ không chỉ hiện thoáng trên máy nhân viên lúc bấm nộp — không lưu thì kế toán
 * mở báo cáo ra không thấy gì và không có căn cứ trả về.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHJP_Anh {

	/**
	 * Cảnh báo thiếu ảnh của MỘT báo cáo. Dùng chung cho màn tiến độ ảnh (nhân viên) và cho
	 * lúc nộp.
	 *
	 * ⚠️ `$ma_coso` để phép kiểm biết cơ sở này CÓ cụm nhận QR hay không (W17). Người gọi
	 *    không đưa thì TỰ ĐỌC, đừng bỏ qua im lặng — bỏ qua là thêm một người gọi mới ở đâu
	 *    đó là W17 tắt mà không ai biết.
	 */
	public static function canh_bao( $ma_bc, $ma_coso = '' ) {
		$rows   = VHJP_Nguon::tim( 'JP_Rows', 'reportId', $ma_bc );
		$zones  = VHJP_Nguon::tim( 'JP_Zones', 'reportId', $ma_bc );
		$photos = VHJP_Nguon::tim( 'JP_Photos', 'reportId', $ma_bc );

		$ban_do_may = array();
		foreach ( VHJP_Nguon::doc( 'JP_Machines' ) as $m ) { $ban_do_may[ (string) $m['id'] ] = $m; }
		$ban_do_cum = array();
		foreach ( VHJP_Nguon::doc( 'JP_Clusters' ) as $c ) { $ban_do_cum[ (string) $c['id'] ] = $c; }

		$loc = VHJP_Doc::str( $ma_coso );
		if ( '' === $loc ) {
			$h = VHJP_Nguon::tim_mot( 'JP_Reports', 'id', $ma_bc );
			$loc = $h ? VHJP_Doc::str( $h['locationId'] ) : '';
		}

		return array(
			'warns' => VHJP_Tinh::kiem_anh( $rows, $zones, $photos, $ban_do_may, $ban_do_cum, $loc ),
			'rows' => $rows, 'zones' => $zones, 'photos' => $photos,
			'banDoMay' => $ban_do_may, 'banDoCum' => $ban_do_cum,
		);
	}

	/**
	 * TIẾN ĐỘ ẢNH — cần bao nhiêu, có bao nhiêu, còn thiếu mấy chỗ.
	 *
	 * ⚠️ `missing` là SỐ CHỖ THIẾU ẢNH, nên CHỈ đếm cảnh báo W7 — đừng đếm cả W17. W17 không
	 *    phải một chỗ thiếu ảnh: nó nói *"chưa chọn cụm nên không có chỗ nào để gắn"*. Đếm cả
	 *    vào là dòng trên màn in "còn thiếu N+1 chỗ" trong khi chỉ có N ô ảnh trống — một con
	 *    số đúng một chút mà sai, tệ hơn là không có.
	 *
	 * ⚠️ Vẫn trả ĐỦ `warns` — W17 phải hiện ra ở khối cảnh báo, chỉ là không đếm vào con số.
	 */
	public static function tien_do( $u, $ma_bc ) {
		$head = VHJP_Nguon::tim_mot( 'JP_Reports', 'id', $ma_bc );
		if ( ! $head ) { throw new Exception( 'Không tìm thấy báo cáo' ); }
		if ( ! VHJP_Auth::la_kt( $u ) ) { VHJP_Auth::can_coso( $u, $head['locationId'] ); }

		$d = self::canh_bao( $ma_bc, $head['locationId'] );

		$can = 0;
		foreach ( $d['rows'] as $r ) {
			$k = VHJP_Doc::str( $r['rowKind'] );
			/* Bảng tồn kho và kho ngoài không có đồng hồ ⇒ không cần ảnh chỉ số. */
			if ( VHJP_Tinh::DONG_STOCK === $k || VHJP_Tinh::DONG_NGOAI === $k ) { continue; }
			$m = isset( $d['banDoMay'][ (string) $r['machineId'] ] )
				? $d['banDoMay'][ (string) $r['machineId'] ] : null;
			$can += $m ? VHJP_Doc::so_anh( $m['photoCount'] ) : 1;
		}
		foreach ( $d['zones'] as $z ) {
			$c = isset( $d['banDoCum'][ (string) $z['clusterId'] ] )
				? $d['banDoCum'][ (string) $z['clusterId'] ] : null;
			$can += $c ? VHJP_Doc::so_anh( $c['photoCount'],
				'Y' === VHJP_Doc::str( $c['hasQR'] ) ? 1 : 0 ) : 0;
		}

		$ma_w7 = VHJP_Tinh::warn_def()['MISSING_PHOTO']['code'];
		$thieu = 0;
		foreach ( $d['warns'] as $w ) {
			if ( VHJP_Doc::str( $w['code'] ) === $ma_w7 ) { $thieu++; }
		}

		return array( 'ok' => true, 'need' => $can, 'got' => count( $d['photos'] ),
			'missing' => $thieu, 'warns' => $d['warns'] );
	}
}
