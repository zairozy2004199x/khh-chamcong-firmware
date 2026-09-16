<?php
/**
 * XUẤT BÁO CÁO — CSV.
 *
 * 🔴 CSV CHỨ KHÔNG PHẢI XLSX. Máy chủ sinh xlsx phải kéo cả một thư viện, mà thứ người ta làm
 *    ngay sau khi tải về là mở bằng Excel rồi tự định dạng. CSV mở được bằng Excel, Google Sheet,
 *    LibreOffice, và không thêm một cục phụ thuộc nào vào plugin.
 *
 * 🔴 DẤU BOM Ở ĐẦU TỆP (\xEF\xBB\xBF). Thiếu nó thì Excel trên Windows đọc UTF-8 thành ký tự rác:
 *    "GHOST HOUSE - GO BÃ€ Rá»ŠA". Người nhận sẽ nghĩ dữ liệu hỏng chứ không nghĩ tại Excel.
 *
 * 🔴 XUẤT ĐÚNG THỨ NGƯỜI XUẤT ĐƯỢC PHÉP XEM. Mọi hàm ở đây đi qua các lớp có sẵn (`VHVH_Tien::ds`
 *    …), không tự viết câu SQL mới — viết lại là một chỗ nữa có thể quên mất chốt lọc cơ sở.
 *
 * @package VHCP_VanHanh
 */

defined( 'ABSPATH' ) || exit;

class VHVH_BaoCao {

	const LOAI = array(
		'doanh_thu' => 'Doanh thu & chi phí theo ngày',
		'danh_gia'  => 'Vi phạm & khen theo kỳ',
		'su_co'     => 'Sự cố',
		'viec'      => 'Giao việc',
		'tiktok'    => 'TikTok',
		'trich_cam' => 'Trích cam',
	);

	/**
	 * Một ô CSV.
	 *
	 * ⚠️ Ô bắt đầu bằng `=`, `+`, `-`, `@` bị Excel hiểu là CÔNG THỨC. Một cái tên như
	 *    "=Cơ sở A" sẽ chạy như công thức, và tệ hơn: đó là đường để nhét công thức độc vào máy
	 *    người mở tệp. Thêm một dấu nháy đơn ở đầu thì Excel coi là chữ.
	 */
	public static function o( $v ) {
		$v = (string) $v;
		if ( '' !== $v && false !== strpos( '=+-@', $v[0] ) ) { $v = "'" . $v; }
		if ( preg_match( '/[",\r\n]/', $v ) ) { return '"' . str_replace( '"', '""', $v ) . '"'; }
		return $v;
	}

	public static function dong( $cot ) {
		return implode( ',', array_map( array( __CLASS__, 'o' ), $cot ) ) . "\r\n";
	}

	/**
	 * Sinh nội dung CSV.
	 *
	 * @return array array( 'ok' => bool, 'ten' => tên tệp, 'csv' => nội dung )
	 */
	public static function xuat( $u, $d ) {
		$loai = isset( $d['loai'] ) ? (string) $d['loai'] : '';
		if ( ! isset( self::LOAI[ $loai ] ) ) {
			return array( 'ok' => false, 'error' => 'Chọn loại báo cáo.' );
		}
		$coso = isset( $d['coso'] ) ? (string) $d['coso'] : '';
		$ky   = isset( $d['ky'] ) ? (string) $d['ky'] : current_time( 'Y-m' );
		if ( ! preg_match( '/^\d{4}-\d{2}$/', $ky ) ) { $ky = current_time( 'Y-m' ); }
		$tu   = $ky . '-01';
		$den  = gmdate( 'Y-m-t', strtotime( $tu . ' 00:00:00 UTC' ) );

		$csv = "\xEF\xBB\xBF";
		switch ( $loai ) {
			case 'doanh_thu':
				$csv .= self::dong( array( 'Ngày', 'Cơ sở', 'Lượt khách', 'Doanh thu', 'Chi phí',
					'Còn lại', 'Tiền mặt', 'Chuyển khoản', 'Momo', 'Trạng thái', 'Người nộp',
					'Người duyệt', 'Ghi chú' ) );
				foreach ( VHVH_Tien::ds( $u, $tu, $den, $coso ) as $x ) {
					$tt = isset( $x['tra_tien'] ) ? $x['tra_tien'] : array();
					$csv .= self::dong( array( $x['ngay'], $x['coso'], $x['khach'], $x['tong_thu'],
						$x['tong_chi'], $x['tong_thu'] - $x['tong_chi'],
						isset( $tt['mat'] ) ? $tt['mat'] : 0,
						isset( $tt['ck'] ) ? $tt['ck'] : 0,
						isset( $tt['momo'] ) ? $tt['momo'] : 0,
						self::ten_tt( $x['tt'] ), $x['nguoi_nop'], $x['nguoi_duyet'], $x['ghi'] ) );
				}
				break;

			case 'danh_gia':
				$csv .= self::dong( array( 'Kỳ', 'Cơ sở', 'Mã NV', 'Họ tên', 'Loại', 'Nội dung',
					'Mức', 'Lần thứ', 'Điểm', 'Mức phạt tham khảo', 'Người ghi', 'Ghi chú', 'Lúc' ) );
				foreach ( VHVH_DanhGia::ds( $u, $ky, $coso ) as $x ) {
					$csv .= self::dong( array( $x['ky'], $x['coso'], $x['ma_nv'], $x['ten'],
						'khen' === $x['loai'] ? 'Khen' : 'Vi phạm', $x['nhan'],
						isset( VHVH_DanhGia::MUC_NHAN[ $x['muc'] ] ) ? VHVH_DanhGia::MUC_NHAN[ $x['muc'] ] : $x['muc'],
						$x['lan_thu'], $x['diem'], $x['phat'], $x['nguoi_ghi'], $x['ghi'], $x['tao'] ) );
				}
				break;

			case 'su_co':
				$csv .= self::dong( array( 'Cơ sở', 'Tiêu đề', 'Mức', 'Trạng thái', 'Trễ hạn',
					'Người báo', 'Người xử lý', 'Hạn', 'Đóng lúc', 'Đóng bởi', 'Mô tả' ) );
				foreach ( VHVH_SuCo::ds( $u, $coso, '' ) as $x ) {
					$csv .= self::dong( array( $x['coso'], $x['tieu_de'],
						isset( VHVH_SuCo::MUC[ $x['muc'] ] ) ? VHVH_SuCo::MUC[ $x['muc'] ] : $x['muc'],
						'mo' === $x['tt'] ? 'Đang mở' : 'Đã đóng', $x['tre'] ? 'TRỄ' : '',
						$x['bao_ten'], $x['giao_ten'], $x['han'], $x['dong_luc'], $x['dong_boi'],
						$x['mo_ta'] ) );
				}
				break;

			case 'viec':
				$csv .= self::dong( array( 'Cơ sở', 'Việc', 'Người nhận', 'Người giao', 'Hạn',
					'Trạng thái', 'Trễ hạn', 'Xong lúc', 'Xong bởi', 'Mô tả' ) );
				foreach ( VHVH_Viec::ds( $u, $coso, '' ) as $x ) {
					$csv .= self::dong( array( $x['coso'], $x['tieu_de'], $x['giao_ten'],
						$x['nguoi_giao'], $x['han'], 'chua' === $x['tt'] ? 'Chưa xong' : 'Đã xong',
						$x['tre'] ? 'TRỄ' : '', $x['xong_luc'], $x['xong_boi'], $x['mo_ta'] ) );
				}
				break;

			case 'tiktok':
				$csv .= self::dong( array( 'Ngày đăng', 'Cơ sở', 'Người đăng', 'Kênh', 'Đường dẫn',
					'Lượt xem', 'Thưởng', 'Chốt lúc' ) );
				foreach ( VHVH_KD::tk_ds( $u, $ky, $coso ) as $x ) {
					$csv .= self::dong( array( $x['ngay_dang'], $x['coso'], $x['ten'], $x['kenh'],
						$x['duong_dan'], null === $x['luot_xem'] ? '' : $x['luot_xem'],
						$x['tien'], $x['chot_luc'] ) );
				}
				break;

			case 'trich_cam':
				$csv .= self::dong( array( 'Ngày đăng ký', 'Cơ sở', 'Người đăng ký', 'Số hoá đơn',
					'SĐT khách', 'Đã xong', 'Xong lúc' ) );
				foreach ( VHVH_KD::tc_ds( $u, $ky, $coso ) as $x ) {
					$csv .= self::dong( array( $x['ngay_dk'], $x['coso'], $x['ten'], $x['so_hd'],
						$x['sdt'], $x['xong'] ? 'Rồi' : '', $x['xong_luc'] ) );
				}
				break;
		}

		$ten = 'van-hanh-' . $loai . '-' . $ky
			. ( '' !== $coso ? '-' . sanitize_title( $coso ) : '' ) . '.csv';
		return array( 'ok' => true, 'ten' => $ten, 'csv' => $csv );
	}

	public static function ten_tt( $tt ) {
		$m = array( 'cho' => 'Chờ duyệt', 'duyet' => 'Đã duyệt', 'tu_choi' => 'Bị từ chối' );
		return isset( $m[ $tt ] ) ? $m[ $tt ] : $tt;
	}
}
