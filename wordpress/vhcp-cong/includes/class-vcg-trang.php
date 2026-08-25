<?php
/**
 * VCG_Trang — trang `/cong` ngoài web, và hai lượt gọi của màn nạp.
 *
 * Anh Thắng: *"thao tác và sử dụng bên ngoài web hết"*. Nên không có màn nào trong trang quản
 * trị WordPress; tất cả nằm ở `khmatrix.com/cong`.
 *
 * HAI BƯỚC, KHÔNG PHẢI MỘT:
 *     xem_truoc  — đọc tệp, đếm, trả về tóm tắt. KHÔNG ghi gì cả.
 *     nap        — ghi thật, sau khi người ta đã nhìn tóm tắt và bấm xác nhận.
 *
 * 🔴 Tách hai bước là chốt an toàn chính của màn này. Nạp nhầm tệp — nhầm cơ sở, nhầm tháng,
 *    nhầm bản cũ — là chuyện xảy ra thật, và một khi đã ghi thì không có nút hoàn tác. Bắt
 *    người ta nhìn "cơ sở TUTU_TP · tháng 7 và 8 · 204 lượt" trước khi bấm là chặn được gần
 *    hết số ca đó, mà chỉ tốn một cú bấm.
 *
 * @package vhcp-cong
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VCG_Trang {

	const DUONG = 'cong';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'luat' ) );
		add_filter( 'query_vars', array( __CLASS__, 'bien' ) );
		add_action( 'template_redirect', array( __CLASS__, 'phuc_vu' ) );
		add_action( 'wp_ajax_vcg_xem_truoc', array( __CLASS__, 'ajax_xem_truoc' ) );
		add_action( 'wp_ajax_vcg_nap', array( __CLASS__, 'ajax_nap' ) );
	}

	public static function luat() {
		add_rewrite_rule( '^' . self::DUONG . '/?$', 'index.php?vcg_app=1', 'top' );
	}

	public static function bien( $v ) { $v[] = 'vcg_app'; return $v; }

	public static function phuc_vu() {
		if ( ! get_query_var( 'vcg_app' ) ) { return; }
		$nguoi = VCG_Nguoi::hien_tai();
		status_header( 200 );
		nocache_headers();
		include VCG_DUONG_DAN . 'templates/app.php';
		exit;
	}

	/* ============================ HAI LƯỢT GỌI ============================ */

	private static function tra( $d, $ma = 200 ) {
		status_header( $ma );
		wp_send_json( $d, $ma );
	}

	/** Đọc tệp đã tải lên -> mảng hàng. Trả null nếu không đọc được. */
	private static function doc_tep( $khoa ) {
		if ( empty( $_FILES[ $khoa ]['tmp_name'] ) || ! is_uploaded_file( $_FILES[ $khoa ]['tmp_name'] ) ) {
			return null;
		}
		$f = fopen( $_FILES[ $khoa ]['tmp_name'], 'r' );
		if ( ! $f ) { return null; }
		$ds = array();
		while ( false !== ( $r = fgetcsv( $f, 0, ',' ) ) ) { $ds[] = $r; }
		fclose( $f );
		/* Google Sheets luôn kèm dấu BOM ở đầu tệp. Không bỏ thì ô đầu tiên mang thêm ba byte
		   vô hình, và mọi phép so tên cột đều trượt mà nhìn bằng mắt thì thấy giống hệt nhau. */
		if ( isset( $ds[0][0] ) ) { $ds[0][0] = preg_replace( '/^\xEF\xBB\xBF/', '', $ds[0][0] ); }
		return $ds;
	}

	/** Kiểm quyền + nonce chung cho cả hai lượt. Trả mảng người, hoặc thoát bằng lỗi. */
	private static function canh_cua( $loai ) {
		if ( ! check_ajax_referer( 'vcg_nap', 'nonce', false ) ) {
			self::tra( array( 'ok' => false, 'loi' => 'Phiên đã hết hạn, tải lại trang.' ), 403 );
		}
		$nguoi = VCG_Nguoi::hien_tai();
		$duoc  = ( 'nv' === $loai )
			? VCG_Quyen::nap_nhan_vien( $nguoi['vai'] )
			: VCG_Quyen::nap_co_so( $nguoi['vai'] );
		if ( ! $duoc ) {
			/* Nói RÕ vì sao bị chặn. Báo "không có quyền" cụt lủn là người ta gọi điện hỏi, còn
			   nói rõ vai nào mới nạp được thì họ tự biết phải nhờ ai. */
			$vi = ( 'nv' === $loai )
				? 'Chỉ Admin mới nạp được sheet nhân viên — đây là dữ liệu chung của mọi phần mềm.'
				: 'Chỉ Admin, quản lý vùng và cửa hàng trưởng mới nạp được sheet cơ sở.';
			self::tra( array( 'ok' => false, 'loi' => $vi, 'vai' => $nguoi['vai'] ), 403 );
		}
		return $nguoi;
	}

	public static function ajax_xem_truoc() {
		$loai  = isset( $_POST['loai'] ) ? sanitize_key( $_POST['loai'] ) : '';
		$nguoi = self::canh_cua( $loai );
		$hang  = self::doc_tep( 'tep' );
		if ( null === $hang ) { self::tra( array( 'ok' => false, 'loi' => 'Không đọc được tệp.' ), 400 ); }

		if ( 'nv' === $loai ) {
			$kq = VCG_Nap::doc_nhan_vien( $hang );
			$dv = array();
			foreach ( $kq['gan'] as $g ) { $dv[ $g['don_vi'] ] = 1; }
			self::tra( array(
				'ok'     => true,
				'loai'   => 'nv',
				'nguoi'  => count( $kq['nguoi'] ),
				'gan'    => count( $kq['gan'] ),
				'don_vi' => count( $dv ),
				'bo_qua' => $kq['bo_qua'],
				'mau'    => array_slice( $kq['nguoi'], 0, 5 ),
			) );
		}

		$co_so = isset( $_POST['co_so'] ) ? sanitize_text_field( wp_unslash( $_POST['co_so'] ) ) : '';
		if ( '' === $co_so ) {
			$co_so = VCG_Nap::co_so_tu_ten( isset( $_FILES['tep']['name'] ) ? $_FILES['tep']['name'] : '' );
		}
		if ( '' === $co_so ) {
			self::tra( array( 'ok' => false,
				'loi' => 'Không đoán được cơ sở từ tên tệp. Đặt tên tệp dạng CS_TUTU_TP.csv, hoặc chọn cơ sở.' ), 400 );
		}
		/* Cửa hàng trưởng chỉ nạp cơ sở mình. Kiểm ở ĐÂY chứ không chỉ ở giao diện — giao diện
		   là gợi ý, máy chủ mới là chốt. */
		if ( ! VCG_Quyen::duoc_co_so( $nguoi['vai'], $nguoi['co_so'], $co_so ) ) {
			self::tra( array( 'ok' => false, 'loi' => 'Bạn không phụ trách cơ sở ' . $co_so . '.' ), 403 );
		}

		$canh_bao = null;
		$luot     = VCG_Nap::doc_co_so( $hang, $co_so, $canh_bao );
		$thang    = array();
		$nguoi_ds = array();
		foreach ( $luot as $x ) {
			$thang[ substr( $x['ngay'], 0, 7 ) ] = 1;
			$nguoi_ds[ $x['ma_nv'] ] = 1;
		}
		ksort( $thang );
		self::tra( array(
			'ok'    => true,
			'loai'  => 'cs',
			'co_so' => $co_so,
			'luot'  => count( $luot ),
			'nguoi' => count( $nguoi_ds ),
			'thang' => array_keys( $thang ),
			'khoi'  => count( VCG_Nap::tim_khoi( $hang ) ),
			'mau'   => array_slice( $luot, 0, 5 ),
			/* 🔴 CẢNH BÁO ĐI KÈM BƯỚC XEM TRƯỚC, không phải sau khi đã ghi. Chỗ hỏng trong tệp
			   là chuyện của SHEET GỐC — mã NV ghi số trần, một người mang hai mã, ô giờ gõ tay
			   hai mốc. Máy vẫn nạp được, nhưng người nạp phải nhìn thấy trước khi bấm, vì cách
			   sửa đúng là sửa Sheet chứ không phải sửa bảng sau. */
			'canh_bao' => $canh_bao,
		) );
	}

	public static function ajax_nap() {
		$loai  = isset( $_POST['loai'] ) ? sanitize_key( $_POST['loai'] ) : '';
		$nguoi = self::canh_cua( $loai );
		$hang  = self::doc_tep( 'tep' );
		if ( null === $hang ) { self::tra( array( 'ok' => false, 'loi' => 'Không đọc được tệp.' ), 400 ); }

		if ( 'nv' === $loai ) {
			$kq = VCG_Nhap::ghi_nhan_vien( VCG_Nap::doc_nhan_vien( $hang ) );
			self::tra( array( 'ok' => true, 'loai' => 'nv' ) + $kq );
		}

		$co_so = isset( $_POST['co_so'] ) ? sanitize_text_field( wp_unslash( $_POST['co_so'] ) ) : '';
		if ( '' === $co_so ) {
			$co_so = VCG_Nap::co_so_tu_ten( isset( $_FILES['tep']['name'] ) ? $_FILES['tep']['name'] : '' );
		}
		if ( '' === $co_so || ! VCG_Quyen::duoc_co_so( $nguoi['vai'], $nguoi['co_so'], $co_so ) ) {
			self::tra( array( 'ok' => false, 'loi' => 'Không được phép nạp cơ sở này.' ), 403 );
		}
		$canh_bao = null;
		$kq = VCG_Nhap::ghi_cong( VCG_Nap::doc_co_so( $hang, $co_so, $canh_bao ) );
		self::tra( array( 'ok' => true, 'loai' => 'cs', 'co_so' => $co_so,
			'canh_bao' => $canh_bao ) + $kq );
	}
}
