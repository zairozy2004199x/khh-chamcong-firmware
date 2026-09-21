<?php
/**
 * NỐI DỰ ÁN VỚI HỆ CHI PHÍ — gán đơn, và gom tiền về để hiện trên màn dự án.
 *
 * Anh Thắng 30/08/2026: *"liên kết đến hệ thống chi phí"*.
 *
 * =============================================================================================
 * 🔴 KHÔNG CHÉP TIỀN SANG KHO NÀY. HỎI MỖI LẦN HIỆN.
 * =============================================================================================
 * Chép số tiền của đơn sang bảng dự án là hai kho cùng giữ một con số. Đơn được sửa, được duyệt
 * lại, được tất toán — mỗi lần như thế bản chép ở đây lại lệch thêm một ít, và tới lúc hai màn
 * hình nói hai con số thì không ai biết kho nào đúng. Bảng `vhda_don` vì vậy chỉ giữ MÃ ĐƠN.
 *
 * Đổi lại, mỗi lần vẽ màn dự án là một lượt hỏi sang plugin chi phí. Chấp nhận được: một dự án
 * có vài đơn, không phải vài nghìn.
 *
 * =============================================================================================
 * ⚠️ CHƯA CÀI PLUGIN CHI PHÍ THÌ NÓI THẲNG, ĐỪNG HIỆN SỐ 0
 * =============================================================================================
 * Số 0 trông y như "dự án chưa tiêu đồng nào" — mà thật ra là "không hỏi được ai". Hai chuyện
 * ấy khác hẳn nhau, và nhầm cái thứ hai thành cái thứ nhất là đọc sai cả bảng.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHDA_Tien {

	/** Hệ chi phí có sẵn sàng để hỏi không. Dò TỪNG HÀM, không dò mỗi tên lớp. */
	public static function co_he_chi_phi() {
		return class_exists( 'VHCP_Don' ) && method_exists( 'VHCP_Don', 'list_dons' );
	}

	public static function ds_don( $du_an_id ) {
		global $wpdb;
		$rs = $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . VHDA_DB::t( 'don' ) . ' WHERE du_an_id=%d ORDER BY id ASC',
			(int) $du_an_id ), ARRAY_A );
		return is_array( $rs ) ? $rs : array();
	}

	/**
	 * GÁN MỘT ĐƠN CHI PHÍ VÀO DỰ ÁN.
	 *
	 * ⚠️ KIỂM ĐƠN CÓ THẬT TRƯỚC KHI GÁN. Gán bừa một mã gõ nhầm thì màn dự án hiện một dòng trỏ
	 *    vào hư không, và tổng tiền thiếu đúng phần của đơn thật mà không ai để ý.
	 */
	public static function gan_don( $u, $ma_du_an, $ma_don ) {
		global $wpdb;
		$loi = VHDA_Quyen::vi_sao_khong( $u, 'gan_don' );
		if ( '' !== $loi ) { return array( 'ok' => false, 'loi' => $loi ); }
		$d = VHDA_DuAn::mot( $ma_du_an );
		if ( ! $d ) { return array( 'ok' => false, 'loi' => 'Không tìm thấy dự án.' ); }

		$md = trim( (string) $ma_don );
		if ( '' === $md ) { return array( 'ok' => false, 'loi' => 'Chưa nhập mã đơn.' ); }
		if ( ! self::co_he_chi_phi() ) {
			return array( 'ok' => false, 'loi' => 'Chưa cài plugin Vận hành chi phí trên site này, '
				. 'nên chưa gán được đơn.' );
		}
		if ( ! method_exists( 'VHCP_Don', 'don_row' ) ) {
			return array( 'ok' => false, 'loi' => 'Bản plugin Chi phí đang cài chưa mở đường tra đơn. '
				. 'Nhờ quản trị cập nhật nó.' );
		}
		if ( ! VHCP_Don::don_row( $md ) ) {
			return array( 'ok' => false, 'loi' => 'Không có đơn nào mang mã "' . $md . '".' );
		}

		$da = $wpdb->get_var( $wpdb->prepare(
			'SELECT id FROM ' . VHDA_DB::t( 'don' ) . ' WHERE du_an_id=%d AND ma_don=%s',
			(int) $d['id'], $md ) );
		if ( $da ) { return array( 'ok' => false, 'loi' => 'Đơn này đã gán vào dự án rồi.' ); }

		$wpdb->insert( VHDA_DB::t( 'don' ), array(
			'du_an_id'  => (int) $d['id'], 'ma_don' => $md,
			'nguoi_gan' => is_array( $u ) && isset( $u['name'] ) ? (string) $u['name'] : '',
			'gan_luc'   => function_exists( 'current_time' ) ? current_time( 'mysql' ) : gmdate( 'Y-m-d H:i:s' ),
		) );
		return array( 'ok' => true, 'id' => (int) $wpdb->insert_id );
	}

	public static function bo_don( $u, $ma_du_an, $ma_don ) {
		global $wpdb;
		$loi = VHDA_Quyen::vi_sao_khong( $u, 'gan_don' );
		if ( '' !== $loi ) { return array( 'ok' => false, 'loi' => $loi ); }
		$d = VHDA_DuAn::mot( $ma_du_an );
		if ( ! $d ) { return array( 'ok' => false, 'loi' => 'Không tìm thấy dự án.' ); }
		$wpdb->delete( VHDA_DB::t( 'don' ),
			array( 'du_an_id' => (int) $d['id'], 'ma_don' => trim( (string) $ma_don ) ) );
		return array( 'ok' => true );
	}

	/**
	 * GOM TIỀN CỦA CÁC ĐƠN ĐÃ GÁN.
	 *
	 * @return array co:bool · tamUng · thucChi · conLai · dong[] · thieu[]
	 *   `co` = false nghĩa là KHÔNG HỎI ĐƯỢC (chưa cài plugin chi phí) — khác hẳn "tổng bằng 0".
	 *   `thieu` liệt kê mã đơn đã gán mà bên kia không còn thấy: đơn bị xoá, hoặc mã gõ sai từ
	 *   trước lúc có phép kiểm. Nói ra để người ta gỡ, chứ lặng lẽ bỏ qua thì tổng thiếu mà
	 *   không ai biết vì sao.
	 */
	public static function tong( $du_an_id ) {
		$ra = array( 'co' => false, 'tamUng' => 0, 'thucChi' => 0, 'conLai' => 0,
			'dong' => array(), 'thieu' => array() );
		$gan = self::ds_don( $du_an_id );
		if ( ! count( $gan ) ) { $ra['co'] = self::co_he_chi_phi(); return $ra; }
		if ( ! self::co_he_chi_phi() ) { return $ra; }

		/* Lấy MỘT LƯỢT cả danh sách rồi tra trong bộ nhớ — hỏi từng đơn là mỗi đơn một lượt
		   truy vấn, và một dự án lớn thì đó là vài chục lượt cho một lần vẽ màn. */
		/* ⚠️ Gác lại NGAY TẠI ĐÂY dù `co_he_chi_phi()` ở trên cũng đã hỏi — luật
		   `tools/test/kiem-goi-cheo.php`: cái gác phải nằm CÙNG THÂN HÀM với lời gọi. Gác ở hàm
		   khác thì hôm nào có người sửa hàm ấy là lời gọi này hụt, mà gọi hụt một hàm tĩnh là
		   Fatal error, trắng cả trang. */
		if ( ! method_exists( 'VHCP_Don', 'list_dons' ) ) { return $ra; }
		$bo = array();
		foreach ( (array) VHCP_Don::list_dons() as $x ) {
			if ( isset( $x['maDon'] ) ) { $bo[ (string) $x['maDon'] ] = $x; }
		}
		$ra['co'] = true;
		foreach ( $gan as $g ) {
			$md = (string) $g['ma_don'];
			if ( ! isset( $bo[ $md ] ) ) { $ra['thieu'][] = $md; continue; }
			$x  = $bo[ $md ];
			$tu = (int) ( isset( $x['tamUng'] ) ? $x['tamUng'] : 0 );
			$tc = (int) ( isset( $x['thucChi'] ) ? $x['thucChi'] : 0 );
			$ra['tamUng']  += $tu;
			$ra['thucChi'] += $tc;
			$ra['dong'][]   = array(
				'maDon'     => $md,
				'ky'        => (string) ( isset( $x['ky'] ) ? $x['ky'] : '' ),
				'coso'      => (string) ( isset( $x['coso'] ) ? $x['coso'] : '' ),
				'trangThai' => (string) ( isset( $x['trangThai'] ) ? $x['trangThai'] : '' ),
				'tamUng'    => $tu,
				'thucChi'   => $tc,
			);
		}
		$ra['conLai'] = $ra['tamUng'] - $ra['thucChi'];
		return $ra;
	}

	/**
	 * SO TIỀN ĐÃ CHI VỚI GIÁ TRỊ HỢP ĐỒNG — hàm thuần, để bộ thử gọi thẳng.
	 *
	 * 🔴 GIÁ TRỊ HỢP ĐỒNG BẰNG 0 THÌ KHÔNG SO. Chia cho 0 ra vô cực, mà "vượt vô cực phần trăm"
	 *    thì chẳng nói lên điều gì — dự án chưa khai giá trị là chuyện thường, nhất là lúc mới
	 *    nhận hợp đồng.
	 *
	 * @return array co:bool · phanTram:int · vuot:bool
	 */
	public static function so_voi_hop_dong( $gia_tri, $thuc_chi ) {
		$gt = (int) $gia_tri;
		$tc = (int) $thuc_chi;
		if ( $gt <= 0 ) { return array( 'co' => false, 'phanTram' => 0, 'vuot' => false ); }
		$pt = (int) round( $tc * 100 / $gt );
		return array( 'co' => true, 'phanTram' => $pt, 'vuot' => $tc > $gt );
	}
}
