<?php
/**
 * NẠP THEO TÊN TIÊU ĐỀ — không phụ thuộc thứ tự cột.
 *
 * Bảng tính đang dùng của K&H đặt cột theo thứ tự khác bộ nạp theo vị trí (VD tab
 * VH_Index: Mã đơn · Gian/Cơ sở · Kỳ · Trạng thái…, còn bộ nạp theo vị trí chờ
 * Mã đơn · Kỳ · Người lập · Ngày tạo…). Nạp lệch cột thì mọi ô đều là chữ hợp lệ nên
 * KHÔNG có gì báo lỗi — số liệu sai âm thầm. Vì vậy lớp này đọc DÒNG TIÊU ĐỀ rồi khớp
 * theo tên cột, thiếu cột thì báo, cột lạ cũng báo, không đoán bừa.
 *
 * @package VHCP
 */

defined( 'ABSPATH' ) || exit;

class VHCP_Nap {

	/** Bỏ dấu, hạ chữ, bỏ mọi thứ không phải chữ/số -> so tên cột kiểu "gần đúng". */
	public static function kh( $s ) {
		$s = mb_strtolower( trim( (string) $s ) );
		$map = array(
			'a' => 'áàảãạăắằẳẵặâấầẩẫậ', 'e' => 'éèẻẽẹêếềểễệ', 'i' => 'íìỉĩị',
			'o' => 'óòỏõọôốồổỗộơớờởỡợ', 'u' => 'úùủũụưứừửữự', 'y' => 'ýỳỷỹỵ', 'd' => 'đ',
		);
		foreach ( $map as $plain => $acc ) {
			$s = str_replace( preg_split( '//u', $acc, -1, PREG_SPLIT_NO_EMPTY ), $plain, $s );
		}
		return preg_replace( '/[^a-z0-9]/', '', $s );
	}

	/**
	 * Từ điển tên cột -> tên trường, cho từng bảng đích.
	 * Mỗi trường liệt kê mọi cách gọi đã gặp; thêm cách gọi mới chỉ cần thêm vào đây.
	 */
	public static function tu_dien( $bang ) {
		$d = array(
			// ---- đơn vận hành (tab VH_Index / DonHang)
			'don' => array(
				'ma_don'      => array( 'Mã đơn', 'Mã' ),
				'coso'        => array( 'Gian/Cơ sở', 'Cơ sở', 'Gian', 'Gian hàng' ),
				'ky'          => array( 'Kỳ', 'Kỳ / tuần', 'Tuần' ),
				'trang_thai'  => array( 'Trạng thái' ),
				'ngay_tao'    => array( 'Ngày tạo', 'Ngày lập' ),
				'nguoi_lap'   => array( 'Người tạo', 'Người lập' ),
				'du_toan'     => array( 'Dự toán tổng', 'Dự toán' ),
				'ghi_chu'     => array( 'Ghi chú' ),
				'nguoi_duyet' => array( 'Người duyệt' ),
				'ngay_duyet'  => array( 'Ngày duyệt' ),
				'nguoi_qt'    => array( 'Người QT', 'Người quyết toán' ),
				'ngay_qt'     => array( 'Ngày QT', 'Ngày quyết toán' ),
				'tam_ung_duyet' => array( 'Tạm ứng duyệt', 'Tạm ứng' ),
				'hinh_thuc_tt'  => array( 'Hình thức TT', 'Hình thức thanh toán' ),
			),
			// ---- dòng chi của đơn (tab VH_Line / ChiPhi)
			'chiphi' => array(
				'id'           => array( 'ID dòng', 'ID', 'Mã dòng' ),
				'ma_don'       => array( 'Mã đơn', 'Mã' ),
				'coso'         => array( 'Cơ sở', 'Gian/Cơ sở', 'Gian' ),
				'ngay'         => array( 'Ngày' ),
				'phan_loai_tt' => array( 'Phân loại TT', 'Phân loại thanh toán', 'Phân loại' ),
				'doi_tuong'    => array( 'Đối tượng', 'NCC', 'Nhà cung cấp' ),
				'nhom'         => array( 'Nhóm mặt hàng', 'Nhóm' ),
				'noi_dung'     => array( 'Nội dung', 'Tên hàng', 'Mặt hàng' ),
				'dvt'          => array( 'ĐVT', 'Đơn vị', 'Đơn vị tính' ),
				'so_luong'     => array( 'Số lượng', 'SL' ),
				'don_gia'      => array( 'Đơn giá', 'ĐG' ),
				'thanh_tien'   => array( 'Thành tiền' ),
				'ghi_chu'      => array( 'Ghi chú' ),
				'anh'          => array( 'Ảnh', 'Hình', 'Hóa đơn' ),
				'thue_suat'    => array( 'Thuế suất', 'Thuế' ),
				'tien_thue'    => array( 'Tiền thuế' ),
				'thuc_mua'     => array( 'Thực mua', 'Thực chi' ),
				'loai_cp'      => array( 'Loại chi phí' ),
			),
			// ---- dòng chi Công tác / Setup (tab CT_ChiTiet — phẳng, khóa theo Mã chuyến)
			'bp_line' => array(
				'ma'         => array( 'Mã chuyến', 'Mã đợt', 'Mã' ),
				'noi_dung'   => array( 'Nội dung' ),
				'so_luong'   => array( 'Số lượng', 'SL' ),
				'don_gia'    => array( 'Đơn giá', 'ĐG' ),
				'thanh_tien' => array( 'Thành tiền' ),
				'du_toan'    => array( 'Ngân sách (dự toán)', 'Ngân sách', 'Dự toán' ),
				'thuc_te'    => array( 'Thực chi', 'Thực tế' ),
				'hinh_thuc'  => array( 'Hình thức chi', 'Hình thức' ),
				'vat'        => array( 'VAT' ),
				'ngay'       => array( 'Ngày' ),
				'note'       => array( 'Ghi chú' ),
				'ho_so'      => array( 'Hồ sơ', 'Ảnh', 'Chứng từ' ),
				'loai_cp'    => array( 'Loại chi phí' ),
			),
			// ---- danh mục đợt Công tác / Setup
			'bp_index' => array(
				'ma'         => array( 'Mã chuyến', 'Mã đợt', 'Mã' ),
				'loai'       => array( 'Loại' ),
				'ten'        => array( 'Tên chuyến', 'Tên đợt', 'Tên' ),
				'nguoi'      => array( 'Người đi', 'Người', 'Nhân sự' ),
				'dia_diem'   => array( 'Địa điểm', 'Nơi đến', 'Cơ sở' ),
				'ky'         => array( 'Kỳ' ),
				'trang_thai' => array( 'Trạng thái' ),
				'ngay_tao'   => array( 'Ngày tạo' ),
				'nguoi_tao'  => array( 'Người tạo' ),
			),
			// ---- dòng hạng mục dự án kỹ thuật (tab DA ...)
			'da_line' => array(
				'ma_da'      => array( 'Mã dự án', 'Mã DA', 'Mã công trình' ),
				'noi_dung'   => array( 'Nội dung', 'Hạng mục' ),
				'du_toan'    => array( 'Dự toán', 'Ngân sách (dự toán)', 'Ngân sách' ),
				'thuc_te'    => array( 'Thực tế', 'Thực chi' ),
				'so_luong'   => array( 'Số lượng', 'SL' ),
				'don_gia'    => array( 'Đơn giá', 'ĐG' ),
				'thanh_tien' => array( 'Thành tiền' ),
				'vat'        => array( 'VAT' ),
				'anh'        => array( 'Ảnh', 'Hình' ),
				'gian'       => array( 'Gian', 'Bộ phận / Gian', 'Gian hàng', 'Cơ sở' ),
				'note'       => array( 'Ghi chú' ),
				'cap_cha'    => array( 'Cấp cha', 'Thuộc hạng mục', 'Hạng mục cha' ),
				'hinh_thuc'  => array( 'Hình thức chi', 'Hình thức' ),
				'ho_so'      => array( 'Hồ sơ', 'Chứng từ' ),
				'loai_cp'    => array( 'Loại chi phí' ),
			),
			// ---- đơn marketing
			'mk_don' => array(
				'ma'         => array( 'Mã đơn', 'Mã' ),
				'coso'       => array( 'Cơ sở', 'Gian/Cơ sở' ),
				'ten'        => array( 'Tên chiến dịch', 'Tên sự kiện', 'Tên' ),
				'ky'         => array( 'Kỳ' ),
				'kenh'       => array( 'Kênh', 'Kênh chính' ),
				'trang_thai' => array( 'Trạng thái' ),
				'ngay_tao'   => array( 'Ngày tạo' ),
				'nguoi_tao'  => array( 'Người tạo' ),
			),
			// ---- hạng mục marketing
			'mk_line' => array(
				'id'        => array( 'ID dòng', 'ID' ),
				'ma_don'    => array( 'Mã đơn', 'Mã' ),
				'kenh'      => array( 'Kênh' ),
				'noi_dung'  => array( 'Nội dung' ),
				'du_toan'   => array( 'Dự toán', 'Ngân sách' ),
				'thuc_te'   => array( 'Thực chi', 'Thực tế' ),
				'hinh_thuc' => array( 'Hình thức chi', 'Hình thức' ),
				'vat'       => array( 'VAT' ),
				'ket_qua'   => array( 'Kết quả' ),
				'ngay'      => array( 'Ngày' ),
				'note'      => array( 'Ghi chú' ),
				'ho_so'     => array( 'Hồ sơ' ),
				'loai_cp'   => array( 'Loại chi phí' ),
			),
			// ---- sổ chi phí phẳng
			'sochi' => array(
				'ngay'      => array( 'Ngày' ),
				'coso'      => array( 'Cơ sở', 'Gian/Cơ sở' ),
				'loai'      => array( 'Loại chi phí', 'Loại' ),
				'noi_dung'  => array( 'Nội dung' ),
				'dvt'       => array( 'ĐVT', 'Đơn vị tính' ),
				'so_luong'  => array( 'Số lượng', 'SL' ),
				'don_gia'   => array( 'Đơn giá' ),
				'so_tien'   => array( 'Số tiền', 'Thành tiền', 'Thực chi' ),
				'hinh_thuc' => array( 'Hình thức chi', 'Hình thức' ),
				'thue_suat' => array( 'Thuế suất' ),
				'vat'       => array( 'VAT' ),
				'doi_tuong' => array( 'Đối tượng' ),
				'ghi_chu'   => array( 'Ghi chú' ),
				'anh'       => array( 'Ảnh', 'Hồ sơ' ),
			),
		);
		return isset( $d[ $bang ] ) ? $d[ $bang ] : array();
	}

	/**
	 * Tìm dòng tiêu đề rồi khớp cột theo tên.
	 *
	 * @return array [ 'hd' => [trường => chỉ số cột], 'rows' => dòng dữ liệu,
	 *                 'thieu' => trường chưa thấy cột, 'la' => tên cột app không dùng,
	 *                 'dongTieuDe' => số dòng tiêu đề (1-based) ]
	 */
	public static function khop( $bang, $rows ) {
		$td = self::tu_dien( $bang );
		if ( ! count( $td ) ) { return array( 'loi' => 'Bảng đích không hợp lệ' ); }

		// Tên cột -> trường (bảng tra phẳng)
		$ten_ve = array();
		foreach ( $td as $field => $tens ) {
			foreach ( $tens as $t ) { $ten_ve[ self::kh( $t ) ] = $field; }
		}

		// Dòng tiêu đề = dòng khớp được nhiều tên cột nhất trong 8 dòng đầu
		$best = -1; $best_n = 0;
		$gioi_han = min( 8, count( $rows ) );
		for ( $i = 0; $i < $gioi_han; $i++ ) {
			$n = 0;
			foreach ( (array) $rows[ $i ] as $o ) {
				if ( isset( $ten_ve[ self::kh( $o ) ] ) ) { $n++; }
			}
			if ( $n > $best_n ) { $best_n = $n; $best = $i; }
		}
		if ( $best < 0 || $best_n < 2 ) {
			return array( 'loi' => 'Không tìm được dòng tiêu đề — file này không có tên cột nào app nhận ra. Kiểm tra lại anh có xuất đúng tab không.' );
		}

		$hd = array(); $la = array();
		foreach ( (array) $rows[ $best ] as $i => $o ) {
			$k = self::kh( $o );
			if ( $k === '' ) { continue; }
			if ( isset( $ten_ve[ $k ] ) && ! isset( $hd[ $ten_ve[ $k ] ] ) ) { $hd[ $ten_ve[ $k ] ] = $i; }
			elseif ( ! isset( $ten_ve[ $k ] ) ) { $la[] = trim( (string) $o ); }
		}

		$thieu = array();
		foreach ( $td as $field => $tens ) {
			if ( ! isset( $hd[ $field ] ) ) { $thieu[] = $tens[0]; }
		}

		return array(
			'hd'         => $hd,
			'rows'       => array_slice( $rows, $best + 1 ),
			'thieu'      => $thieu,
			'la'         => $la,
			'dongTieuDe' => $best + 1,
		);
	}

	/** Danh sách bảng đích có thể tự dò, theo thứ tự nạp (danh mục trước, dòng chi sau). */
	public static function cac_bang() {
		return array( 'don', 'bp_index', 'mk_don', 'chiphi', 'bp_line', 'da_line', 'mk_line', 'sochi' );
	}

	/**
	 * Điểm khớp của một bảng dữ liệu với một bảng đích = số tên cột nhận ra được.
	 * Dùng để tự chọn "tab này là bảng gì" khi đọc cả file Google Sheet một lượt.
	 */
	public static function diem( $bang, $rows ) {
		$k = self::khop( $bang, $rows );
		if ( ! empty( $k['loi'] ) ) { return 0; }
		return count( $k['hd'] );
	}

	/**
	 * Đoán bảng đích của một tab: chọn bảng khớp được nhiều tên cột nhất.
	 * Yêu cầu tối thiểu 3 cột khớp để không nhận bừa.
	 *
	 * @return array [bang, diem, tatCa]
	 */
	public static function doan_bang( $rows ) {
		$diem = array();
		foreach ( self::cac_bang() as $b ) { $diem[ $b ] = self::diem( $b, $rows ); }
		arsort( $diem );
		$top = key( $diem );
		$max = $diem[ $top ];
		return array(
			'bang'  => ( $max >= 3 ? $top : '' ),
			'diem'  => $max,
			'tatCa' => $diem,
		);
	}

	/** Lấy ô theo TÊN TRƯỜNG (rỗng nếu file không có cột đó). */
	public static function o( $row, $hd, $field ) {
		if ( ! isset( $hd[ $field ] ) ) { return ''; }
		$i = $hd[ $field ];
		return isset( $row[ $i ] ) ? trim( (string) $row[ $i ] ) : '';
	}
}
