<?php
/**
 * Sinh file CSV MẪU (chỉ có dòng tiêu đề) cho từng tab nạp được.
 * Mục đích: so cột file cũ với cột app đang chờ, tránh nạp lệch cột.
 *   php tools/mau-csv.php [thư-mục-ra]
 */
$out = isset( $argv[1] ) ? rtrim( $argv[1], '/' ) : 'dist/mau-csv';

$mau = array(
	'DonHang' => array( 'Mã đơn', 'Kỳ', 'Người lập', 'Ngày tạo', 'Trạng thái', 'Ghi chú', 'Người duyệt', 'Ngày duyệt', 'Người QT', 'Ngày QT', 'Chênh lệch QT', 'Xử lý', 'Số tiền thực mua', 'Hình thức TT', 'Hóa đơn QT', 'Ngày xuất CN', 'Người QT NCC', 'Ngày QT NCC', 'Ngày xuất NCC', 'Tạm ứng duyệt', 'Người cấp', 'Ngày cấp', 'HT cấp', 'Ảnh cấp', 'Tất toán', 'Ngày tất toán', 'Dự phòng', 'Bù trừ' ),
	'TamUng'  => array( 'Mã đơn', 'Cơ sở', 'Số tiền' ),
	'ChiPhi'  => array( 'ID dòng', 'Mã đơn', 'Cơ sở', 'Ngày', 'Phân loại TT', 'Đối tượng', 'Nhóm mặt hàng', 'Nội dung', 'ĐVT', 'Số lượng', 'Đơn giá', 'Thành tiền', 'Ghi chú', 'Ảnh', 'Tạo lúc', 'Thuế suất', 'Tiền thuế', 'Thực mua', 'CN xử lý', 'Phát sinh' ),
	'SoChi'   => array( 'Ngày', 'Cơ sở', 'Loại chi phí', 'Nội dung', 'ĐVT', 'Số lượng', 'Đơn giá', 'Số tiền', 'Hình thức chi', 'Thuế suất', 'VAT', 'Đối tượng', 'Ghi chú', 'Ảnh' ),
	'DA_Index' => array( 'Mã dự án', 'Tên dự án', 'Loại', '(bỏ trống)', 'Trạng thái', 'Ngày tạo', 'Người tạo' ),
	'DA_Sheet' => array( 'Nội dung', 'Dự toán', 'Thực tế', 'Số lượng', 'Đơn giá', 'Thành tiền', 'VAT', 'Ảnh', 'Gian', 'Ghi chú', 'Cấp cha', 'Hình thức', 'Hồ sơ', 'Loại chi phí (tùy chọn)' ),
	'MK_Don'  => array( 'Mã đơn', 'Cơ sở', 'Tên chiến dịch', 'Kỳ', 'Kênh', 'Trạng thái', 'Ngày tạo', 'Người tạo' ),
	'MK_Line' => array( 'ID dòng', 'Mã đơn', 'Kênh', 'Nội dung', 'Dự toán', 'Thực tế', 'Hình thức', 'VAT', 'Kết quả', 'Ngày', 'Ghi chú', 'Hồ sơ', 'Loại chi phí (tùy chọn)' ),
	'BP_Index' => array( 'Mã đợt', 'Loại (Công tác/Setup)', 'Tên đợt', 'Người đi', 'Địa điểm', 'Kỳ', '(bỏ trống)', 'Trạng thái', 'Ngày tạo', 'Người tạo' ),
	'BP_Sheet' => array( 'Nội dung', 'Số lượng', 'Đơn giá', 'Thành tiền', 'Dự toán', 'Thực tế', 'Hình thức', 'VAT', 'Ngày', 'Ghi chú', 'Hồ sơ', 'Loại chi phí (tùy chọn)' ),
	'NhatKy'  => array( 'Thời gian', 'Người', 'Vai trò', 'Hành động', 'Đối tượng', 'Chi tiết' ),
	'CH_CoSo' => array( 'Cơ sở', 'Mã đơn vị', 'Phân loại lớn', 'Tên MISA' ),
	'CH_TaiKhoan' => array( 'Số hiệu', 'Tên tài khoản', 'Tính chất' ),
	'CH_LoaiChiPhi' => array( 'Loại chi phí', 'TK Nợ', 'TK Có', 'Mã đối tượng', 'Bộ phận', 'Ghi chú', 'Tên MISA', 'Loại' ),
	'CH_TKNo' => array( 'Loại chi phí', 'Mảng kinh doanh (Phân loại lớn)', 'TK Nợ' ),
	'CH_MangTK' => array( 'Phân loại lớn', 'Nhóm TK', 'Từ khóa trong tên TK', 'Ghi chú' ),
	'CH_Nhom' => array( 'Nhóm mặt hàng', 'Loại (ncc/canhan/both)', 'TK Nợ', 'Bộ phận' ),
	'CH_PhanLoai' => array( 'Phân loại TT', 'TK Có' ),
	'CH_DoiTuong' => array( 'Đối tượng', 'Mã đối tượng', 'Loại (NV/NCC)' ),
	'CH_NguoiDung' => array( 'Tên', 'PIN', 'Vai trò', 'Cơ sở', 'TK Có', 'Mã đối tượng', 'Bộ phận' ),
	'CH_QR'   => array( 'Khóa', 'Giá trị' ),
	'CH_SSO'  => array( 'Email', 'Vai trò Chi Phí', 'Cơ sở' ),
);

// Tab phải bỏ 4 dòng đầu khi nạp -> file mẫu chèn sẵn 4 dòng trống cho giống thật
$bo4 = array( 'DA_Sheet' => 1, 'BP_Sheet' => 1 );

@mkdir( $out, 0777, true );
foreach ( $mau as $ten => $cols ) {
	$fh = fopen( "$out/$ten.csv", 'w' );
	fwrite( $fh, "\xEF\xBB\xBF" );
	if ( isset( $bo4[ $ten ] ) ) {
		fputcsv( $fh, array( 'DÒNG 1 — app bỏ qua' ) );
		fputcsv( $fh, array( 'DÒNG 2 — app bỏ qua' ) );
		fputcsv( $fh, array( 'DÒNG 3 — app bỏ qua' ) );
		fputcsv( $fh, $cols );                                   // dòng 4 = tiêu đề, cũng bị bỏ
		fputcsv( $fh, array_fill( 0, count( $cols ), '' ) );      // dòng 5 = dòng dữ liệu đầu tiên
	} else {
		fputcsv( $fh, $cols );
	}
	fclose( $fh );
	echo str_pad( $ten, 16 ), count( $cols ), " cột", ( isset( $bo4[ $ten ] ) ? '  (bỏ 4 dòng đầu, dữ liệu từ dòng 5)' : '' ), "\n";
}
echo "\nĐã ghi vào $out/\n";
