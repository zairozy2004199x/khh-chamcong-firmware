<?php
/**
 * NHẬP DỮ LIỆU CŨ — dán/tải CSV (hoặc TSV) xuất từ Google Sheet vào bảng MySQL.
 *
 * Cách làm ở Google Sheet: mở từng tab → Tệp → Tải xuống → CSV, rồi vào
 * "Vận Hành Chi Phí → Nhập dữ liệu" chọn đúng loại tab và tải file lên.
 *
 * Ngày tháng đọc theo kiểu VIỆT NAM (ngày trước: 20/08/2026). Số có dấu phân cách
 * nghìn (1.234.567 hoặc 1,234,567) đều đọc được.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCP_Import {

	/** Danh sách loại tab nhập được: nhãn + số cột mong đợi + số dòng bỏ qua đầu tab. */
	public static function types() {
		return array(
			'DonHang'       => array( 'label' => 'DonHang — đơn vận hành (28 cột)', 'skip' => 0 ),
			'TamUng'        => array( 'label' => 'TamUng — tạm ứng theo cơ sở (3 cột)', 'skip' => 0 ),
			'ChiPhi'        => array( 'label' => 'ChiPhi — dòng chi của đơn (20 cột; mã tài khoản tự gán theo Nhóm mặt hàng)', 'skip' => 0 ),
			'DA_Index'      => array( 'label' => 'DA_Index — danh mục dự án kỹ thuật (7 cột)', 'skip' => 0 ),
			'DA_Sheet'      => array( 'label' => 'Tab 1 dự án kỹ thuật (13 cột, bỏ 4 dòng đầu) — cần chọn dự án; cột 14 "Loại chi phí" tùy chọn, trống thì tự suy theo loại dự án', 'skip' => 4 ),
			'MK_Don'        => array( 'label' => 'MK_Don — đơn marketing (8 cột)', 'skip' => 0 ),
			'MK_Line'       => array( 'label' => 'MK_Line — hạng mục marketing (12 cột; cột 13 "Loại chi phí" tùy chọn để tự gán mã)', 'skip' => 0 ),
			'BP_Index'      => array( 'label' => 'BP_Index — danh mục đợt Công tác/Setup (10 cột)', 'skip' => 0 ),
			'BP_Sheet'      => array( 'label' => 'Tab 1 đợt Công tác/Setup (11 cột, bỏ 4 dòng đầu) — cần chọn đợt; cột 12 "Loại chi phí" tùy chọn để tự gán mã', 'skip' => 4 ),
			'SoChi'         => array( 'label' => 'SoChi — sổ chi phí phẳng (Ngày · Cơ sở · Loại chi phí · Nội dung · ĐVT · SL · ĐG · Số tiền · Hình thức chi · Thuế suất · VAT · Đối tượng · Ghi chú · Ảnh)', 'skip' => 0 ),
			'NhatKy'        => array( 'label' => 'NhatKy — nhật ký hoạt động (6 cột)', 'skip' => 0 ),
			'CH_CoSo'       => array( 'label' => 'CH_CoSo — cấu hình cơ sở', 'skip' => 0 ),
			'CH_Nhom'       => array( 'label' => 'CH_Nhom — cấu hình nhóm mặt hàng', 'skip' => 0 ),
			'CH_PhanLoai'   => array( 'label' => 'CH_PhanLoai — phân loại thanh toán', 'skip' => 0 ),
			'CH_DoiTuong'   => array( 'label' => 'CH_DoiTuong — đối tượng', 'skip' => 0 ),
			'CH_QR'         => array( 'label' => 'CH_QR — tài khoản nhận tiền (VietQR)', 'skip' => 0 ),
			'CH_NguoiDung'  => array( 'label' => 'CH_NguoiDung — người dùng & PIN', 'skip' => 0 ),
			'CH_TKNo'       => array( 'label' => 'CH_TKNo — ma trận TK Nợ', 'skip' => 0 ),
			'CH_SSO'        => array( 'label' => 'CH_SSO — phân vai trò theo email', 'skip' => 0 ),
			'CH_Quyen'      => array( 'label' => 'CH_Quyen — ma trận phân quyền', 'skip' => 0 ),
			'CH_LoaiChiPhi' => array( 'label' => 'CH_LoaiChiPhi — danh mục loại chi phí + mã tài khoản', 'skip' => 0 ),
			'CH_TaiKhoan'   => array( 'label' => 'CH_TaiKhoan — hệ thống tài khoản của kế toán', 'skip' => 0 ),
			'CH_MangTK'     => array( 'label' => 'CH_MangTK — mảng kinh doanh → nhóm tài khoản', 'skip' => 0 ),

			// ---- TỰ DÒ THEO TÊN TIÊU ĐỀ: không phụ thuộc thứ tự cột, dùng cho bảng tính đang chạy
			'TD_Don'     => array( 'label' => '★ Tự dò tiêu đề — ĐƠN VẬN HÀNH (tab VH_Index)', 'skip' => 0, 'td' => 'don' ),
			'TD_ChiPhi'  => array( 'label' => '★ Tự dò tiêu đề — DÒNG CHI CỦA ĐƠN (tab VH_Line)', 'skip' => 0, 'td' => 'chiphi' ),
			'TD_BPIndex' => array( 'label' => '★ Tự dò tiêu đề — DANH MỤC ĐỢT Công tác/Setup', 'skip' => 0, 'td' => 'bp_index' ),
			'TD_BPLine'  => array( 'label' => '★ Tự dò tiêu đề — DÒNG CHI Công tác/Setup (tab CT_ChiTiet, khóa theo Mã chuyến)', 'skip' => 0, 'td' => 'bp_line' ),
			'TD_DALine'  => array( 'label' => '★ Tự dò tiêu đề — DÒNG HẠNG MỤC DỰ ÁN (tab DA …; cần chọn dự án nếu file không có cột Mã dự án)', 'skip' => 0, 'td' => 'da_line' ),
			'TD_MKDon'   => array( 'label' => '★ Tự dò tiêu đề — ĐƠN MARKETING', 'skip' => 0, 'td' => 'mk_don' ),
			'TD_MKLine'  => array( 'label' => '★ Tự dò tiêu đề — HẠNG MỤC MARKETING', 'skip' => 0, 'td' => 'mk_line' ),
			'TD_SoChi'   => array( 'label' => '★ Tự dò tiêu đề — SỔ CHI PHÍ PHẲNG', 'skip' => 0, 'td' => 'sochi' ),
		);
	}

	// ---------------------------------------------------------------- đọc CSV

	/**
	 * Nội dung có phải file nhị phân (Excel/zip/PDF/ảnh) chứ không phải CSV?
	 *
	 * Nạp thẳng .xlsx vào đây thì trước kia app cứ thế đọc, ra một mớ ký tự rác và
	 * ghi vào bảng. Nhận ra sớm rồi báo đúng việc cần làm: mở bằng Excel/Google Sheet
	 * rồi "Tải xuống → CSV".
	 *
	 * @return string '' nếu là văn bản, ngược lại là câu báo lỗi.
	 */
	public static function loi_nhi_phan( $text ) {
		$t = (string) $text;
		if ( $t === '' ) { return ''; }
		$dau = substr( $t, 0, 8 );
		$sig = array(
			"PK\x03\x04"     => 'file Excel (.xlsx) hoặc file nén (.zip)',
			"\xD0\xCF\x11\xE0" => 'file Excel cũ (.xls)',
			'%PDF'            => 'file PDF',
			"\x89PNG"         => 'file ảnh PNG',
			"\xFF\xD8\xFF"    => 'file ảnh JPG',
			'{\rtf'           => 'file RTF',
		);
		foreach ( $sig as $magic => $ten ) {
			if ( strpos( $dau, $magic ) === 0 ) {
				return 'Đây là ' . $ten . ', không phải CSV. Mở file bằng Excel / Google Sheet rồi chọn "Tải xuống → Giá trị được phân tách bằng dấu phẩy (.csv)" và nạp lại file .csv đó.';
			}
		}
		// Không nhận ra chữ ký nhưng lẫn byte 0 / quá nhiều ký tự điều khiển -> vẫn là nhị phân
		$mau = substr( $t, 0, 4096 );
		if ( strpos( $mau, "\0" ) !== false ) {
			return 'File này là dữ liệu nhị phân, không phải CSV. Xuất lại từ Excel / Google Sheet ra định dạng .csv rồi nạp.';
		}
		$xau = 0;
		$n   = strlen( $mau );
		for ( $i = 0; $i < $n; $i++ ) {
			$c = ord( $mau[ $i ] );
			if ( $c < 9 || ( $c > 13 && $c < 32 ) ) { $xau++; }
		}
		if ( $n > 0 && $xau / $n > 0.05 ) {
			return 'File này không đọc được như văn bản (có thể là Excel hoặc file nén). Xuất lại ra .csv rồi nạp.';
		}
		if ( ! mb_check_encoding( $mau, 'UTF-8' ) ) {
			return 'File không phải mã UTF-8 nên tiếng Việt sẽ thành ký tự lạ. Mở lại bằng Google Sheet rồi "Tải xuống → CSV" (Google luôn xuất UTF-8).';
		}
		return '';
	}

	/**
	 * Nạp một tab bằng cách KHỚP TÊN TIÊU ĐỀ. Trả về báo cáo gồm cả cột app bỏ qua,
	 * để không có dữ liệu nào âm thầm biến mất.
	 */
	private static function nap_theo_tieu_de( $bang, $rows, $replace, $ma_chon, $coso_mac_dinh = '' ) {
		global $wpdb;
		$k = VHCP_Nap::khop( $bang, $rows );
		if ( ! empty( $k['loi'] ) ) { return VHCP_Util::err( $k['loi'] ); }
		$hd = $k['hd'];
		$o  = function ( $r, $f ) use ( $hd ) { return VHCP_Nap::o( $r, $hd, $f ); };

		$n = 0; $skipped = 0; $thieu_ma = 0; $chua_co_cha = array();

		switch ( $bang ) {
			case 'don':
				$t = VHCP_DB::t( 'don' );
				if ( $replace ) { $wpdb->query( "DELETE FROM $t" ); }
				foreach ( $k['rows'] as $r ) {
					$md = $o( $r, 'ma_don' );
					if ( $md === '' ) { $skipped++; continue; }
					$wpdb->delete( $t, array( 'ma_don' => $md ) );
					$wpdb->insert( $t, array(
						'ma_don'        => $md,
						'ky'            => $o( $r, 'ky' ),
						'nguoi_lap'     => $o( $r, 'nguoi_lap' ),
						'ngay_tao'      => self::dt( $o( $r, 'ngay_tao' ) ),
						'trang_thai'    => ( $o( $r, 'trang_thai' ) !== '' ? $o( $r, 'trang_thai' ) : 'Nháp' ),
						'ghi_chu'       => $o( $r, 'ghi_chu' ),
						'nguoi_duyet'   => $o( $r, 'nguoi_duyet' ),
						'ngay_duyet'    => self::dt( $o( $r, 'ngay_duyet' ) ),
						'nguoi_qt'      => $o( $r, 'nguoi_qt' ),
						'ngay_qt'       => self::dt( $o( $r, 'ngay_qt' ) ),
						'tam_ung_duyet' => self::n( $o( $r, 'tam_ung_duyet' ) ),
						'hinh_thuc_tt'  => $o( $r, 'hinh_thuc_tt' ),
					) );
					// Cơ sở của đơn nằm ở từng dòng chi -> ghi sẵn 1 dòng tạm ứng theo cơ sở nếu có
					$cs = $o( $r, 'coso' );
					if ( $cs !== '' ) {
						$tu = VHCP_DB::t( 'tamung' );
						$wpdb->delete( $tu, array( 'ma_don' => $md, 'coso' => $cs ) );
						$wpdb->insert( $tu, array( 'ma_don' => $md, 'coso' => $cs, 'so' => self::n0( $o( $r, 'du_toan' ) ) ) );
					}
					$n++;
				}
				break;

			case 'chiphi':
				$t = VHCP_DB::t( 'chiphi' );
				if ( $replace ) { $wpdb->query( "DELETE FROM $t" ); }
				foreach ( $k['rows'] as $r ) {
					$md = $o( $r, 'ma_don' );
					$nd = $o( $r, 'noi_dung' );
					if ( $md === '' && $nd === '' ) { $skipped++; continue; }
					$sl  = self::n( $o( $r, 'so_luong' ) );
					$dg  = self::n( $o( $r, 'don_gia' ) );
					$tt  = $o( $r, 'thanh_tien' ) !== '' ? self::n0( $o( $r, 'thanh_tien' ) ) : ( VHCP_Util::num( $sl ) * VHCP_Util::num( $dg ) );
					$cs  = $o( $r, 'coso' );
					$loai = $o( $r, 'loai_cp' );
					$tk  = ( $loai !== '' )
						? VHCP_Cfg::resolve_tk( $loai, $o( $r, 'phan_loai_tt' ), array(), $cs )
						: VHCP_Don::tk_of_line( $o( $r, 'nhom' ), $o( $r, 'phan_loai_tt' ) );
					if ( trim( (string) $tk['tk_no'] ) === '' ) { $thieu_ma++; }
					$id = $o( $r, 'id' ) !== '' ? $o( $r, 'id' ) : VHCP_Util::uid( 'CP' );
					$wpdb->delete( $t, array( 'id' => $id ) );
					$wpdb->insert( $t, array(
						'id'           => $id,
						'ma_don'       => $md,
						'coso'         => $cs,
						'ngay'         => self::d( $o( $r, 'ngay' ) ),
						'phan_loai_tt' => $o( $r, 'phan_loai_tt' ),
						'doi_tuong'    => $o( $r, 'doi_tuong' ),
						'nhom'         => $o( $r, 'nhom' ),
						'noi_dung'     => $nd,
						'dvt'          => $o( $r, 'dvt' ),
						'so_luong'     => $sl,
						'don_gia'      => $dg,
						'thanh_tien'   => $tt,
						'ghi_chu'      => $o( $r, 'ghi_chu' ),
						'anh'          => $o( $r, 'anh' ),
						'thue_suat'    => self::n( $o( $r, 'thue_suat' ) ),
						'tien_thue'    => self::n( $o( $r, 'tien_thue' ) ),
						'thuc_mua'     => self::n( $o( $r, 'thuc_mua' ) ),
						'loai_cp'      => $loai,
						'tk_no'        => $tk['tk_no'],
						'tk_co'        => $tk['tk_co'],
						'ma_dt'        => isset( $tk['ma_dt'] ) ? $tk['ma_dt'] : '',
					) );
					$n++;
				}
				break;

			case 'bp_index':
				$t = VHCP_DB::t( 'bp_index' );
				if ( $replace ) { $wpdb->query( "DELETE FROM $t" ); }
				foreach ( $k['rows'] as $r ) {
					$m = $o( $r, 'ma' );
					if ( $m === '' ) { $skipped++; continue; }
					$wpdb->delete( $t, array( 'ma' => $m ) );
					$wpdb->insert( $t, array(
						'ma'         => $m,
						'loai'       => ( $o( $r, 'loai' ) !== '' ? $o( $r, 'loai' ) : 'Công tác' ),
						'ten'        => $o( $r, 'ten' ),
						'nguoi'      => $o( $r, 'nguoi' ),
						'dia_diem'   => $o( $r, 'dia_diem' ),
						'ky'         => $o( $r, 'ky' ),
						'trang_thai' => ( $o( $r, 'trang_thai' ) !== '' ? $o( $r, 'trang_thai' ) : 'Đang xử lý' ),
						'ngay_tao'   => $o( $r, 'ngay_tao' ),
						'nguoi_tao'  => $o( $r, 'nguoi_tao' ),
					) );
					$n++;
				}
				break;

			case 'bp_line':
				$t = VHCP_DB::t( 'bp_line' );
				if ( $replace ) { $wpdb->query( "DELETE FROM $t" ); }
				$dia = array();
				foreach ( VHCP_BP::all_with_lines() as $b ) { $dia[ (string) $b['ma'] ] = (string) $b['dia_diem']; }
				$row_no = array();
				foreach ( $k['rows'] as $r ) {
					$m  = $o( $r, 'ma' ) !== '' ? $o( $r, 'ma' ) : $ma_chon;
					$nd = $o( $r, 'noi_dung' );
					if ( $m === '' || ( $nd === '' && ! self::n0( $o( $r, 'thuc_te' ) ) ) ) { $skipped++; continue; }
					if ( ! isset( $dia[ $m ] ) ) { $chua_co_cha[ $m ] = 1; $skipped++; continue; }
					if ( ! isset( $row_no[ $m ] ) ) {
						$row_no[ $m ] = max( VHCP_DB::DATA_ROW - 1, (int) $wpdb->get_var( $wpdb->prepare( "SELECT MAX(row_no) FROM $t WHERE ma=%s", $m ) ) );
					}
					$row_no[ $m ]++;
					$sl   = self::n0( $o( $r, 'so_luong' ) );
					$dg   = self::n0( $o( $r, 'don_gia' ) );
					$loai = $o( $r, 'loai_cp' );
					$tk   = ( $loai !== '' ) ? VHCP_Cfg::resolve_tk( $loai, $o( $r, 'hinh_thuc' ), array(), $dia[ $m ] ) : array( 'tk_no' => '', 'tk_co' => '', 'ma_dt' => '' );
					if ( $tk['tk_no'] === '' ) { $thieu_ma++; }
					$wpdb->insert( $t, array(
						'ma'         => $m,
						'row_no'     => $row_no[ $m ],
						'noi_dung'   => $nd,
						'so_luong'   => $sl,
						'don_gia'    => $dg,
						'thanh_tien' => ( $o( $r, 'thanh_tien' ) !== '' ? self::n0( $o( $r, 'thanh_tien' ) ) : $sl * $dg ),
						'du_toan'    => self::n0( $o( $r, 'du_toan' ) ),
						'thuc_te'    => self::n0( $o( $r, 'thuc_te' ) ),
						'hinh_thuc'  => $o( $r, 'hinh_thuc' ),
						'vat'        => $o( $r, 'vat' ),
						'ngay'       => $o( $r, 'ngay' ),
						'note'       => $o( $r, 'note' ),
						'ho_so'      => $o( $r, 'ho_so' ),
						'loai_cp'    => $loai,
						'tk_no'      => $tk['tk_no'],
						'tk_co'      => $tk['tk_co'],
						'ma_dt'      => $tk['ma_dt'],
					) );
					$n++;
				}
				break;

			case 'da_line':
				$t = VHCP_DB::t( 'da_line' );
				$row_no = array();
				foreach ( $k['rows'] as $r ) {
					$da = $o( $r, 'ma_da' ) !== '' ? $o( $r, 'ma_da' ) : $ma_chon;
					$nd = $o( $r, 'noi_dung' );
					if ( $da === '' ) { return VHCP_Util::err( 'File không có cột "Mã dự án" — chọn dự án ở ô "Dự án / Đợt nhận dòng" rồi nạp lại.' ); }
					if ( $nd === '' && ! self::n0( $o( $r, 'thuc_te' ) ) && ! self::n0( $o( $r, 'du_toan' ) ) ) { $skipped++; continue; }
					if ( ! VHCP_DuAn::find( $da ) ) { $chua_co_cha[ $da ] = 1; $skipped++; continue; }
					if ( ! isset( $row_no[ $da ] ) ) {
						if ( $replace ) { $wpdb->delete( $t, array( 'ma_da' => $da ) ); }
						$row_no[ $da ] = max( VHCP_DB::DATA_ROW - 1, (int) $wpdb->get_var( $wpdb->prepare( "SELECT MAX(row_no) FROM $t WHERE ma_da=%s", $da ) ) );
					}
					$row_no[ $da ]++;
					$sl   = self::n0( $o( $r, 'so_luong' ) );
					$dg   = self::n0( $o( $r, 'don_gia' ) );
					$gian = $o( $r, 'gian' );
					$loai = $o( $r, 'loai_cp' );
					$tk   = ( $loai !== '' ) ? VHCP_Cfg::resolve_tk( $loai, $o( $r, 'hinh_thuc' ), array(), $gian ) : array( 'tk_no' => '', 'tk_co' => '', 'ma_dt' => '' );
					if ( $tk['tk_no'] === '' ) { $thieu_ma++; }
					$wpdb->insert( $t, array(
						'ma_da'      => $da,
						'row_no'     => $row_no[ $da ],
						'noi_dung'   => $nd,
						'du_toan'    => self::n0( $o( $r, 'du_toan' ) ),
						'thuc_te'    => self::n0( $o( $r, 'thuc_te' ) ),
						'so_luong'   => $sl,
						'don_gia'    => $dg,
						'thanh_tien' => ( $o( $r, 'thanh_tien' ) !== '' ? self::n0( $o( $r, 'thanh_tien' ) ) : $sl * $dg ),
						'vat'        => $o( $r, 'vat' ),
						'anh'        => $o( $r, 'anh' ),
						'gian'       => $gian,
						'note'       => $o( $r, 'note' ),
						'cap_cha'    => $o( $r, 'cap_cha' ),
						'hinh_thuc'  => $o( $r, 'hinh_thuc' ),
						'ho_so'      => $o( $r, 'ho_so' ),
						'loai_cp'    => $loai,
						'tk_no'      => $tk['tk_no'],
						'tk_co'      => $tk['tk_co'],
						'ma_dt'      => $tk['ma_dt'],
					) );
					$n++;
				}
				break;

			case 'mk_don':
				$t = VHCP_DB::t( 'mk_don' );
				if ( $replace ) { $wpdb->query( "DELETE FROM $t" ); }
				foreach ( $k['rows'] as $r ) {
					$m = $o( $r, 'ma' );
					if ( $m === '' ) { $skipped++; continue; }
					$wpdb->delete( $t, array( 'ma' => $m ) );
					$wpdb->insert( $t, array(
						'ma'         => $m,
						'coso'       => $o( $r, 'coso' ),
						'ten'        => $o( $r, 'ten' ),
						'ky'         => $o( $r, 'ky' ),
						'kenh'       => $o( $r, 'kenh' ),
						'trang_thai' => ( $o( $r, 'trang_thai' ) !== '' ? $o( $r, 'trang_thai' ) : 'Đang chạy' ),
						'ngay_tao'   => $o( $r, 'ngay_tao' ),
						'nguoi_tao'  => $o( $r, 'nguoi_tao' ),
					) );
					$n++;
				}
				break;

			case 'mk_line':
				$t = VHCP_DB::t( 'mk_line' );
				if ( $replace ) { $wpdb->query( "DELETE FROM $t" ); }
				$cs_don = array();
				foreach ( VHCP_MK::all_dons() as $d ) { $cs_don[ (string) $d['ma'] ] = (string) $d['coso']; }
				foreach ( $k['rows'] as $r ) {
					$md = $o( $r, 'ma_don' );
					if ( $md === '' ) { $skipped++; continue; }
					if ( ! isset( $cs_don[ $md ] ) ) { $chua_co_cha[ $md ] = 1; $skipped++; continue; }
					$loai = $o( $r, 'loai_cp' );
					$tk   = ( $loai !== '' ) ? VHCP_Cfg::resolve_tk( $loai, $o( $r, 'hinh_thuc' ), array(), $cs_don[ $md ] ) : array( 'tk_no' => '', 'tk_co' => '', 'ma_dt' => '' );
					if ( $tk['tk_no'] === '' ) { $thieu_ma++; }
					$id = $o( $r, 'id' ) !== '' ? $o( $r, 'id' ) : VHCP_Util::uid( 'MKL' );
					$wpdb->delete( $t, array( 'id' => $id ) );
					$wpdb->insert( $t, array(
						'id'        => $id,
						'ma_don'    => $md,
						'kenh'      => $o( $r, 'kenh' ),
						'noi_dung'  => $o( $r, 'noi_dung' ),
						'du_toan'   => self::n0( $o( $r, 'du_toan' ) ),
						'thuc_te'   => self::n0( $o( $r, 'thuc_te' ) ),
						'hinh_thuc' => $o( $r, 'hinh_thuc' ),
						'vat'       => $o( $r, 'vat' ),
						'ket_qua'   => self::n0( $o( $r, 'ket_qua' ) ),
						'ngay'      => $o( $r, 'ngay' ),
						'note'      => $o( $r, 'note' ),
						'ho_so'     => $o( $r, 'ho_so' ),
						'loai_cp'   => $loai,
						'tk_no'     => $tk['tk_no'],
						'tk_co'     => $tk['tk_co'],
						'ma_dt'     => $tk['ma_dt'],
					) );
					$n++;
				}
				break;

			case 'sochi':
				$t = VHCP_DB::t( 'so_chi' );
				if ( $replace ) { $wpdb->query( "DELETE FROM $t" ); }

				// DÒNG TỔNG HỢP: tab dự án ghi hạng mục lớn thành một dòng riêng ("Nhân Công"
				// 12.000.000) rồi liệt kê các dòng con bên dưới cùng thuộc hạng mục đó. Nạp cả
				// hai là ĐẾM HAI LẦN. Nên dòng nào có tên trùng với một "hạng mục lớn" của
				// dòng khác thì chỉ giữ DỰ TOÁN, số thực chi để 0 — tổng vẫn đúng mà không mất
				// ngân sách của hạng mục.
				$la_hang_muc = array();
				foreach ( $k['rows'] as $r ) {
					$hm = $o( $r, 'hang_muc' );
					if ( $hm !== '' ) { $la_hang_muc[ mb_strtolower( $hm ) ] = 1; }
				}
				$dong_tong = 0; $loai_suy_ra = 0; $loai_moi = array(); $tong_tien = 0; $dong_khong_tien = 0;

				foreach ( $k['rows'] as $r ) {
					$loai = $o( $r, 'loai' );
					$nd   = $o( $r, 'noi_dung' );
					if ( $loai === '' && $nd === '' ) { continue; }
					// File không có cột "Loại chi phí" (tab dự án) -> lấy HẠNG MỤC LỚN làm loại,
					// dòng hạng mục thì lấy chính tên nó. Đúng cách gom của bảng tính cũ.
					if ( $loai === '' ) {
						$loai = $o( $r, 'hang_muc' ) !== '' ? $o( $r, 'hang_muc' ) : $nd;
						if ( $loai !== '' ) { $loai_suy_ra++; }
					}
					if ( $loai !== '' ) { $loai_moi[ $loai ] = 1; }
					// Dòng của một dự án: mã dự án lấy từ cột trong file, không có thì lấy
					// mã chọn ở ô "Dự án / Đợt nhận dòng" (bộ đọc Sheet truyền tên tab vào đây).
					$mda = $o( $r, 'ma_du_an' ) !== '' ? $o( $r, 'ma_du_an' ) : $ma_chon;
					$res = VHCP_SoChi::add( array(
						'ngay'     => $o( $r, 'ngay' ),
						'coso'     => ( $o( $r, 'coso' ) !== '' ? $o( $r, 'coso' ) : $coso_mac_dinh ),
						'loai'     => $loai,
						'noiDung'  => $nd,
						'dvt'      => $o( $r, 'dvt' ),
						'soLuong'  => self::n( $o( $r, 'so_luong' ) ),
						'donGia'   => self::n( $o( $r, 'don_gia' ) ),
						'soTien'   => ( isset( $la_hang_muc[ mb_strtolower( $nd ) ] ) ? 0 : self::n( VHCP_Nap::o_so( $r, $k, 'so_tien' ) ) ),
						'hinhThuc' => $o( $r, 'hinh_thuc' ),
						'thueSuat' => self::n( $o( $r, 'thue_suat' ) ),
						'vat'      => $o( $r, 'vat' ),
						'doiTuong' => $o( $r, 'doi_tuong' ),
						// "Bộ phận / Gian" không phải cơ sở nên gộp vào ghi chú, không bỏ mất
						'ghiChu'   => trim( ( $o( $r, 'bo_phan' ) !== '' ? '[' . $o( $r, 'bo_phan' ) . '] ' : '' ) . $o( $r, 'ghi_chu' ) ),
						'anh'      => $o( $r, 'anh' ),
						'maDuAn'   => $mda,
						'hangMuc'  => $o( $r, 'hang_muc' ),
						'duToan'   => self::n( VHCP_Nap::o_so( $r, $k, 'du_toan' ) ),
						'hoSo'     => $o( $r, 'ho_so' ),
						// dòng đã xuất MISA ở hệ cũ -> vào app cũng là đã xuất, khỏi xuất trùng
						'ngayXuat' => $o( $r, 'ngay_xuat' ),
					), 'Nạp từ Sheet' );
					if ( empty( $res['success'] ) ) { $skipped++; continue; }
					if ( trim( (string) $res['tkNo'] ) === '' ) { $thieu_ma++; }
					if ( isset( $la_hang_muc[ mb_strtolower( $nd ) ] ) ) { $dong_tong++; }
					// Đếm luôn tổng tiền và số dòng ra 0 đồng: nạp xong mà tổng bằng 0 thì biết
					// ngay là đọc sai cột tiền, khỏi phải soi từng dòng trên màn hình.
					$tien_dong = VHCP_Util::num( isset( $res['soTien'] ) ? $res['soTien'] : 0 );
					$tong_tien += $tien_dong;
					if ( ! $tien_dong && ! isset( $la_hang_muc[ mb_strtolower( $nd ) ] ) ) { $dong_khong_tien++; }
					$n++;
				}
				// Đưa các tên loại vừa gặp vào danh mục để còn khai mã được cho chúng
				$them_loai = VHCP_Cfg::them_loai_neu_thieu( array_keys( $loai_moi ) );
				break;

			default:
				return VHCP_Util::err( 'Bảng đích chưa hỗ trợ tự dò' );
		}

		return VHCP_Util::ok( array(
			'inserted'   => $n,
			'skipped'    => $skipped,
			'thieuMa'    => $thieu_ma,
			'dongTong'   => isset( $dong_tong ) ? $dong_tong : 0,
			'loaiSuyRa'  => isset( $loai_suy_ra ) ? $loai_suy_ra : 0,
			'themLoai'   => isset( $them_loai ) ? $them_loai : 0,
			'tongTien'   => isset( $tong_tien ) ? $tong_tien : 0,
			'khongTien'  => isset( $dong_khong_tien ) ? $dong_khong_tien : 0,
			'dongTieuDe' => $k['dongTieuDe'],
			'cotDung'    => array_keys( $hd ),
			'cotThieu'   => $k['thieu'],
			'cotLa'      => $k['la'],
			'chuaCoCha'  => array_map( 'strval', array_keys( $chua_co_cha ) ),
		) );
	}

	/** Trích 80 ký tự đầu để soi nhanh khi file không tách được dòng nào. */
	private static function xem_dau( $text ) {
		$d = trim( mb_substr( preg_replace( '/\s+/u', ' ', (string) $text ), 0, 80 ) );
		return $d === '' ? '(rỗng)' : '"' . $d . '…"';
	}

	/** Tách bảng từ nội dung CSV/TSV (đọc được cả ô nhiều dòng nhờ fgetcsv). */
	public static function parse( $text ) {
		$text = str_replace( array( "\r\n", "\r" ), "\n", (string) $text );
		$text = preg_replace( '/^\xEF\xBB\xBF/', '', $text );
		if ( trim( $text ) === '' ) { return array(); }

		$first = strtok( $text, "\n" );
		$delim = ',';
		$best  = substr_count( (string) $first, ',' );
		foreach ( array( "\t" => substr_count( (string) $first, "\t" ), ';' => substr_count( (string) $first, ';' ) ) as $d => $n ) {
			if ( $n > $best ) { $best = $n; $delim = $d; }
		}

		$fh = fopen( 'php://temp', 'r+' );
		fwrite( $fh, $text );
		rewind( $fh );
		$rows = array();
		while ( false !== ( $r = fgetcsv( $fh, 0, $delim, '"' ) ) ) {
			if ( $r === array( null ) ) { continue; }   // dòng trống
			$rows[] = $r;
		}
		fclose( $fh );
		return $rows;
	}

	/** Số kiểu Việt Nam / kiểu Mỹ đều đọc được; ô trống -> null. */
	private static function n( $v, $default_null = true ) {
		$s = trim( (string) $v );
		if ( $s === '' ) { return $default_null ? null : 0; }
		$s = str_replace( array( ' ', "\xc2\xa0", '₫', 'đ', 'VND' ), '', $s );
		$neg = ( strpos( $s, '(' ) !== false && strpos( $s, ')' ) !== false ) || strpos( $s, '-' ) === 0;
		$s = str_replace( array( '(', ')', '+' ), '', $s );
		$has_dot   = strpos( $s, '.' ) !== false;
		$has_comma = strpos( $s, ',' ) !== false;
		if ( $has_dot && $has_comma ) {
			// dấu nào ở sau cùng là dấu thập phân
			if ( strrpos( $s, ',' ) > strrpos( $s, '.' ) ) { $s = str_replace( '.', '', $s ); $s = str_replace( ',', '.', $s ); }
			else { $s = str_replace( ',', '', $s ); }
		} elseif ( $has_comma ) {
			$s = ( preg_match( '/,\d{3}(\D|$)/', $s ) ) ? str_replace( ',', '', $s ) : str_replace( ',', '.', $s );
		} elseif ( $has_dot ) {
			if ( preg_match( '/^-?\d{1,3}(\.\d{3})+$/', $s ) ) { $s = str_replace( '.', '', $s ); }
		}
		$s = preg_replace( '/[^0-9.\-]/', '', $s );
		if ( $s === '' || $s === '-' ) { return $default_null ? null : 0; }
		$f = (float) $s;
		if ( $neg && $f > 0 ) { $f = -$f; }
		return $f;
	}

	private static function n0( $v ) {
		$x = self::n( $v, false );
		return $x === null ? 0 : $x;
	}

	private static function d( $v ) {
		return VHCP_Util::parse_date( $v );
	}

	/** Ngày + giờ → 'Y-m-d H:i:s' (null nếu ô trống). */
	private static function dt( $v ) {
		$s = trim( (string) $v );
		if ( $s === '' ) { return null; }
		if ( preg_match( '#^(\d{4})-(\d{2})-(\d{2})[ T](\d{1,2}):(\d{2})(?::(\d{2}))?#', $s, $m ) ) {
			return sprintf( '%s-%s-%s %02d:%02d:%02d', $m[1], $m[2], $m[3], $m[4], $m[5], isset( $m[6] ) ? $m[6] : 0 );
		}
		if ( preg_match( '#^(\d{1,2})[/.-](\d{1,2})[/.-](\d{2,4})(?:[ T](\d{1,2}):(\d{2})(?::(\d{2}))?)?#', $s, $m ) ) {
			$y = (int) $m[3];
			if ( $y < 100 ) { $y += 2000; }
			return sprintf( '%04d-%02d-%02d %02d:%02d:%02d', $y, $m[2], $m[1], isset( $m[4] ) ? $m[4] : 0, isset( $m[5] ) ? $m[5] : 0, isset( $m[6] ) ? $m[6] : 0 );
		}
		$d = self::d( $s );
		return $d ? $d . ' 00:00:00' : null;
	}

	private static function c( $row, $i ) {
		return isset( $row[ $i ] ) ? trim( (string) $row[ $i ] ) : '';
	}

	// ---------------------------------------------------------------- nhập

	/**
	 * @param string $type   khóa trong types()
	 * @param string $text   nội dung CSV
	 * @param array  $opts   ['header'=>bool, 'replace'=>bool, 'ma'=>string (cho DA_Sheet/BP_Sheet)]
	 */
	public static function run( $type, $text, $opts = array() ) {
		$loi = self::loi_nhi_phan( $text );
		if ( $loi !== '' ) { return VHCP_Util::err( $loi ); }
		global $wpdb;
		$types = self::types();
		if ( ! isset( $types[ $type ] ) ) { return VHCP_Util::err( 'Loại tab không hợp lệ' ); }

		// Chưa chọn file / chưa dán gì: nói đúng chuyện đó, đừng bắt người dùng đi soi file.
		if ( trim( (string) $text ) === '' ) {
			return VHCP_Util::err( 'Chưa chọn file CSV (và ô "dán nội dung" cũng đang trống). Bấm "Choose File" chọn file .csv rồi nạp lại.' );
		}

		$header  = ! empty( $opts['header'] );
		$replace = ! empty( $opts['replace'] );
		$ma      = isset( $opts['ma'] ) ? trim( (string) $opts['ma'] ) : '';
		$skip    = (int) $types[ $type ]['skip'];

		$rows = self::parse( $text );
		if ( ! count( $rows ) ) {
			return VHCP_Util::err( 'File có ' . number_format( strlen( $text ) ) . ' ký tự nhưng không tách được dòng nào. '
				. 'Thường là do file lưu sai định dạng — mở lại bằng Google Sheet rồi "Tải xuống → CSV". '
				. 'Đầu file đang là: ' . self::xem_dau( $text ) );
		}

		// Số dòng thực tế ít hơn số dòng phải bỏ -> nói rõ, đừng báo "không đọc được dòng nào"
		$tho = count( $rows );
		if ( $skip > 0 && $tho <= $skip ) {
			return VHCP_Util::err( 'Tab này bỏ ' . $skip . ' dòng đầu (dòng tiêu đề của bảng tính) nhưng file chỉ có ' . $tho . ' dòng. '
				. 'Anh xuất đúng tab chưa? Tab dự án / đợt phải xuất cả phần tiêu đề ở trên.' );
		}
		$rows_goc = $rows;   // nhánh tự dò cần cả dòng tiêu đề để khớp tên cột
		if ( $skip > 0 ) { $rows = array_slice( $rows, $skip ); }
		elseif ( $header ) {
			if ( $tho === 1 ) {
				return VHCP_Util::err( 'File chỉ có 1 dòng và đang tích "Dòng đầu là tiêu đề" nên không còn dòng dữ liệu nào. '
					. 'Bỏ tích ô đó nếu dòng đầu đã là dữ liệu.' );
			}
			$rows = array_slice( $rows, 1 );
		}
		if ( ! count( $rows ) ) { return VHCP_Util::err( 'Không còn dòng dữ liệu nào sau khi bỏ dòng tiêu đề — kiểm tra lại file.' ); }

		$n = 0; $skipped = 0; $thieu_ma = 0;   // thieu_ma: dòng nạp xong vẫn chưa có TK Nợ

		// ---- TỰ DÒ THEO TÊN TIÊU ĐỀ
		if ( ! empty( $types[ $type ]['td'] ) ) {
			$cs_md = isset( $opts['coso'] ) ? trim( (string) $opts['coso'] ) : '';
			return self::nap_theo_tieu_de( $types[ $type ]['td'], $rows_goc, $replace, $ma, $cs_md );
		}

		// ---- các bảng cấu hình CH_* : ghi thẳng dạng hàng JSON
		if ( strpos( $type, 'CH_' ) === 0 ) {
			$want = count( VHCP_Cfg::headers( $type ) );
			$clean = array();
			foreach ( $rows as $r ) {
				if ( self::c( $r, 0 ) === '' ) { $skipped++; continue; }
				$row = array();
				for ( $i = 0; $i < max( $want, count( $r ) ); $i++ ) { $row[] = self::c( $r, $i ); }
				$clean[] = array_slice( $row, 0, max( $want, 1 ) );
				$n++;
			}
			if ( $replace ) { VHCP_Cfg::write( $type, $clean, false ); }
			else { foreach ( $clean as $row ) { VHCP_Cfg::append( $type, $row ); } }
			VHCP_Cfg::clear_cache();
			// Nạp cấu hình cũ có sẵn TK Nợ -> copy luôn sang danh mục LOẠI CHI PHÍ,
			// để dòng nhập sau đó tự mang mã đúng mà không phải khai lại bằng tay.
			$dong_bo = 0;
			if ( $type === VHCP_Cfg::NHOM || $type === VHCP_Cfg::TKNO ) {
				$r       = VHCP_Cfg::dong_bo_tk_loai();
				$dong_bo = (int) $r['updated'];
				$thieu_ma = (int) $r['thieuMa'];
			}
			return VHCP_Util::ok( array( 'inserted' => $n, 'skipped' => $skipped, 'dongBoLoai' => $dong_bo, 'thieuMa' => $thieu_ma ) );
		}

		switch ( $type ) {

			case 'DonHang':
				$t = VHCP_DB::t( 'don' );
				if ( $replace ) { $wpdb->query( "DELETE FROM $t" ); }
				foreach ( $rows as $r ) {
					$ma_don = self::c( $r, 0 );
					if ( $ma_don === '' ) { $skipped++; continue; }
					$data = array(
						'ma_don'           => $ma_don,
						'ky'               => self::c( $r, 1 ),
						'nguoi_lap'        => self::c( $r, 2 ),
						'ngay_tao'         => self::dt( self::c( $r, 3 ) ),
						'trang_thai'       => ( self::c( $r, 4 ) !== '' ? self::c( $r, 4 ) : 'Nháp' ),
						'ghi_chu'          => self::c( $r, 5 ),
						'nguoi_duyet'      => self::c( $r, 6 ),
						'ngay_duyet'       => self::dt( self::c( $r, 7 ) ),
						'nguoi_qt'         => self::c( $r, 8 ),
						'ngay_qt'          => self::dt( self::c( $r, 9 ) ),
						'chenh_lech_qt'    => self::n0( self::c( $r, 10 ) ),
						'xu_ly'            => self::c( $r, 11 ),
						'so_tien_thuc_mua' => self::n( self::c( $r, 12 ) ),
						'hinh_thuc_tt'     => self::c( $r, 13 ),
						'hoa_don_qt'       => self::c( $r, 14 ),
						'ngay_xuat_cn'     => self::dt( self::c( $r, 15 ) ),
						'nguoi_qt_ncc'     => self::c( $r, 16 ),
						'ngay_qt_ncc'      => self::dt( self::c( $r, 17 ) ),
						'ngay_xuat_ncc'    => self::dt( self::c( $r, 18 ) ),
						'tam_ung_duyet'    => self::n( self::c( $r, 19 ) ),
						'nguoi_cap'        => self::c( $r, 20 ),
						'ngay_cap'         => self::dt( self::c( $r, 21 ) ),
						'ht_cap'           => self::c( $r, 22 ),
						'anh_cap'          => self::c( $r, 23 ),
						'tat_toan'         => self::c( $r, 24 ),
						'ngay_tat_toan'    => self::dt( self::c( $r, 25 ) ),
						'du_phong'         => self::n( self::c( $r, 26 ) ),
						'bu_tru'           => self::n( self::c( $r, 27 ) ),
					);
					$wpdb->delete( $t, array( 'ma_don' => $ma_don ) );
					$wpdb->insert( $t, $data );
					$n++;
				}
				break;

			case 'TamUng':
				$t = VHCP_DB::t( 'tamung' );
				if ( $replace ) { $wpdb->query( "DELETE FROM $t" ); }
				foreach ( $rows as $r ) {
					$ma_don = self::c( $r, 0 );
					if ( $ma_don === '' ) { $skipped++; continue; }
					$wpdb->query( $wpdb->prepare(
						"INSERT INTO $t (ma_don,coso,so) VALUES (%s,%s,%f) ON DUPLICATE KEY UPDATE so=VALUES(so)",
						$ma_don, self::c( $r, 1 ), self::n0( self::c( $r, 2 ) )
					) );
					$n++;
				}
				break;

			case 'ChiPhi':
				$t = VHCP_DB::t( 'chiphi' );
				if ( $replace ) { $wpdb->query( "DELETE FROM $t" ); }
				foreach ( $rows as $r ) {
					$id = self::c( $r, 0 );
					if ( $id === '' || self::c( $r, 1 ) === '' ) { $skipped++; continue; }
					$data = array(
						'id'           => $id,
						'ma_don'       => self::c( $r, 1 ),
						'coso'         => self::c( $r, 2 ),
						'ngay'         => self::d( self::c( $r, 3 ) ),
						'phan_loai_tt' => self::c( $r, 4 ),
						'doi_tuong'    => self::c( $r, 5 ),
						'nhom'         => self::c( $r, 6 ),
						'noi_dung'     => self::c( $r, 7 ),
						'dvt'          => self::c( $r, 8 ),
						'so_luong'     => self::n( self::c( $r, 9 ) ),
						'don_gia'      => self::n( self::c( $r, 10 ) ),
						'thanh_tien'   => self::n0( self::c( $r, 11 ) ),
						'ghi_chu'      => self::c( $r, 12 ),
						'anh'          => self::c( $r, 13 ),
						'tao_luc'      => self::dt( self::c( $r, 14 ) ),
						'thue_suat'    => self::n( self::c( $r, 15 ) ),
						'tien_thue'    => self::n( self::c( $r, 16 ) ),
						'thuc_mua'     => self::n( self::c( $r, 17 ) ),
						'cn_xu_ly'     => VHCP_Util::cn_flag( self::c( $r, 18 ) ) ? 1 : 0,
						'phat_sinh'    => VHCP_Util::is_phat_sinh( self::c( $r, 19 ) ) ? 1 : 0,
					);
					// Gán mã tài khoản ngay khi nạp: TK Nợ theo LOẠI CHI PHÍ (= cột Nhóm mặt hàng),
					// TK Có theo phân loại thanh toán — y như nhập tay, khỏi phải bấm gán mã sau.
					$tk = VHCP_Don::tk_of_line( $data['nhom'], $data['phan_loai_tt'] );
					$data['tk_no'] = $tk['tk_no'];
					$data['tk_co'] = $tk['tk_co'];
					if ( $tk['tk_no'] === '' ) { $thieu_ma++; }
					$wpdb->delete( $t, array( 'id' => $id ) );
					$wpdb->insert( $t, $data );
					$n++;
				}
				break;

			case 'DA_Index':
				$t = VHCP_DB::t( 'da_index' );
				if ( $replace ) { $wpdb->query( "DELETE FROM $t" ); }
				foreach ( $rows as $r ) {
					$ma_da = self::c( $r, 0 );
					if ( $ma_da === '' ) { $skipped++; continue; }
					$wpdb->delete( $t, array( 'ma_da' => $ma_da ) );
					$wpdb->insert( $t, array(
						'ma_da'      => $ma_da,
						'ten'        => self::c( $r, 1 ),
						'loai'       => self::c( $r, 2 ),
						'trang_thai' => ( self::c( $r, 4 ) !== '' ? self::c( $r, 4 ) : 'Đang làm' ),
						'ngay_tao'   => self::dt( self::c( $r, 5 ) ),
						'nguoi_tao'  => self::c( $r, 6 ),
					) );
					$n++;
				}
				break;

			case 'DA_Sheet':
				if ( $ma === '' ) { return VHCP_Util::err( 'Chọn dự án kỹ thuật để nạp các dòng hạng mục vào' ); }
				$da_rec = VHCP_DuAn::find( $ma );
				if ( ! $da_rec ) { return VHCP_Util::err( 'Không tìm thấy dự án: ' . $ma ); }
				$loai_mac_dinh = VHCP_DuAn::loai_cp_mac_dinh( $da_rec['loai'] );
				$t = VHCP_DB::t( 'da_line' );
				if ( $replace ) { $wpdb->delete( $t, array( 'ma_da' => $ma ) ); }
				$row_no = (int) $wpdb->get_var( $wpdb->prepare( "SELECT MAX(row_no) FROM $t WHERE ma_da=%s", $ma ) );
				$row_no = max( VHCP_DB::DATA_ROW - 1, $row_no );
				foreach ( $rows as $r ) {
					$nd = self::c( $r, 0 );
					if ( $nd === '' && ! self::n0( self::c( $r, 1 ) ) && ! self::n0( self::c( $r, 2 ) ) ) { $skipped++; continue; }
					$row_no++;
					$sl = self::n0( self::c( $r, 3 ) );
					$dg = self::n0( self::c( $r, 4 ) );
					// Loại chi phí: lấy cột 14 nếu file có, trống thì suy theo loại dự án.
					$loai_cp = self::c( $r, 13 );
					if ( $loai_cp === '' ) { $loai_cp = $loai_mac_dinh; }
					$tk = ( $loai_cp !== '' ) ? VHCP_Cfg::resolve_tk( $loai_cp, self::c( $r, 11 ), array(), self::c( $r, 8 ) ) : array( 'tk_no' => '', 'tk_co' => '', 'ma_dt' => '' );
					if ( $tk['tk_no'] === '' ) { $thieu_ma++; }
					$wpdb->insert( $t, array(
						'ma_da'      => $ma,
						'row_no'     => $row_no,
						'noi_dung'   => $nd,
						'du_toan'    => self::n0( self::c( $r, 1 ) ),
						'thuc_te'    => self::n0( self::c( $r, 2 ) ),
						'so_luong'   => $sl,
						'don_gia'    => $dg,
						'thanh_tien' => ( self::c( $r, 5 ) !== '' ? self::n0( self::c( $r, 5 ) ) : $sl * $dg ),
						'vat'        => self::c( $r, 6 ),
						'anh'        => self::c( $r, 7 ),
						'gian'       => self::c( $r, 8 ),
						'note'       => self::c( $r, 9 ),
						'cap_cha'    => self::c( $r, 10 ),
						'hinh_thuc'  => self::c( $r, 11 ),
						'ho_so'      => self::c( $r, 12 ),
						'loai_cp'    => $loai_cp,
						'tk_no'      => $tk['tk_no'],
						'tk_co'      => $tk['tk_co'],
						'ma_dt'      => $tk['ma_dt'],
					) );
					$n++;
				}
				break;

			case 'MK_Don':
				$t = VHCP_DB::t( 'mk_don' );
				if ( $replace ) { $wpdb->query( "DELETE FROM $t" ); }
				foreach ( $rows as $r ) {
					$k = self::c( $r, 0 );
					if ( $k === '' ) { $skipped++; continue; }
					$wpdb->delete( $t, array( 'ma' => $k ) );
					$wpdb->insert( $t, array(
						'ma'         => $k,
						'coso'       => self::c( $r, 1 ),
						'ten'        => self::c( $r, 2 ),
						'ky'         => self::c( $r, 3 ),
						'kenh'       => self::c( $r, 4 ),
						'trang_thai' => ( self::c( $r, 5 ) !== '' ? self::c( $r, 5 ) : 'Đang chạy' ),
						'ngay_tao'   => self::c( $r, 6 ),
						'nguoi_tao'  => self::c( $r, 7 ),
					) );
					$n++;
				}
				break;

			case 'MK_Line':
				$t = VHCP_DB::t( 'mk_line' );
				if ( $replace ) { $wpdb->query( "DELETE FROM $t" ); }
				$coso_don = array();
				foreach ( VHCP_MK::all_dons() as $d ) { $coso_don[ (string) $d['ma'] ] = (string) $d['coso']; }
				foreach ( $rows as $r ) {
					$k = self::c( $r, 0 );
					if ( $k === '' || self::c( $r, 1 ) === '' ) { $skipped++; continue; }
					$loai_cp = self::c( $r, 12 );
					$md      = self::c( $r, 1 );
					$tk = ( $loai_cp !== '' ) ? VHCP_Cfg::resolve_tk( $loai_cp, self::c( $r, 6 ), array(), isset( $coso_don[ $md ] ) ? $coso_don[ $md ] : '' ) : array( 'tk_no' => '', 'tk_co' => '', 'ma_dt' => '' );
					if ( $tk['tk_no'] === '' ) { $thieu_ma++; }
					$wpdb->delete( $t, array( 'id' => $k ) );
					$wpdb->insert( $t, array(
						'id'        => $k,
						'ma_don'    => self::c( $r, 1 ),
						'loai_cp'   => $loai_cp,
						'tk_no'     => $tk['tk_no'],
						'tk_co'     => $tk['tk_co'],
						'ma_dt'     => $tk['ma_dt'],
						'kenh'      => self::c( $r, 2 ),
						'noi_dung'  => self::c( $r, 3 ),
						'du_toan'   => self::n0( self::c( $r, 4 ) ),
						'thuc_te'   => self::n0( self::c( $r, 5 ) ),
						'hinh_thuc' => self::c( $r, 6 ),
						'vat'       => self::c( $r, 7 ),
						'ket_qua'   => self::n0( self::c( $r, 8 ) ),
						'ngay'      => self::c( $r, 9 ),
						'note'      => self::c( $r, 10 ),
						'ho_so'     => self::c( $r, 11 ),
					) );
					$n++;
				}
				break;

			case 'BP_Index':
				$t = VHCP_DB::t( 'bp_index' );
				if ( $replace ) { $wpdb->query( "DELETE FROM $t" ); }
				foreach ( $rows as $r ) {
					$k = self::c( $r, 0 );
					if ( $k === '' ) { $skipped++; continue; }
					$wpdb->delete( $t, array( 'ma' => $k ) );
					$wpdb->insert( $t, array(
						'ma'         => $k,
						'loai'       => self::c( $r, 1 ),
						'ten'        => self::c( $r, 2 ),
						'nguoi'      => self::c( $r, 3 ),
						'dia_diem'   => self::c( $r, 4 ),
						'ky'         => self::c( $r, 5 ),
						'trang_thai' => ( self::c( $r, 7 ) !== '' ? self::c( $r, 7 ) : 'Đang xử lý' ),
						'ngay_tao'   => self::c( $r, 8 ),
						'nguoi_tao'  => self::c( $r, 9 ),
					) );
					$n++;
				}
				break;

			case 'BP_Sheet':
				if ( $ma === '' ) { return VHCP_Util::err( 'Chọn đợt Công tác/Setup để nạp các dòng vào' ); }
				if ( ! VHCP_BP::find( $ma ) ) { return VHCP_Util::err( 'Không tìm thấy đợt: ' . $ma ); }
				$t = VHCP_DB::t( 'bp_line' );
				if ( $replace ) { $wpdb->delete( $t, array( 'ma' => $ma ) ); }
				$row_no = (int) $wpdb->get_var( $wpdb->prepare( "SELECT MAX(row_no) FROM $t WHERE ma=%s", $ma ) );
				$row_no = max( VHCP_DB::DATA_ROW - 1, $row_no );
				$dot_rec  = VHCP_BP::find( $ma );
				$dia_diem = $dot_rec ? (string) $dot_rec['dia_diem'] : '';
				foreach ( $rows as $r ) {
					$nd = self::c( $r, 0 );
					if ( $nd === '' && ! self::n0( self::c( $r, 4 ) ) && ! self::n0( self::c( $r, 5 ) ) ) { $skipped++; continue; }
					$row_no++;
					$sl = self::n0( self::c( $r, 1 ) );
					$dg = self::n0( self::c( $r, 2 ) );
					$loai_cp = self::c( $r, 11 );
					$tk = ( $loai_cp !== '' ) ? VHCP_Cfg::resolve_tk( $loai_cp, self::c( $r, 6 ), array(), $dia_diem ) : array( 'tk_no' => '', 'tk_co' => '', 'ma_dt' => '' );
					if ( $tk['tk_no'] === '' ) { $thieu_ma++; }
					$wpdb->insert( $t, array(
						'ma'         => $ma,
						'loai_cp'    => $loai_cp,
						'tk_no'      => $tk['tk_no'],
						'tk_co'      => $tk['tk_co'],
						'ma_dt'      => $tk['ma_dt'],
						'row_no'     => $row_no,
						'noi_dung'   => $nd,
						'so_luong'   => $sl,
						'don_gia'    => $dg,
						'thanh_tien' => ( self::c( $r, 3 ) !== '' ? self::n0( self::c( $r, 3 ) ) : $sl * $dg ),
						'du_toan'    => self::n0( self::c( $r, 4 ) ),
						'thuc_te'    => self::n0( self::c( $r, 5 ) ),
						'hinh_thuc'  => self::c( $r, 6 ),
						'vat'        => self::c( $r, 7 ),
						'ngay'       => self::c( $r, 8 ),
						'note'       => self::c( $r, 9 ),
						'ho_so'      => self::c( $r, 10 ),
					) );
					$n++;
				}
				break;

			case 'SoChi':
				$t = VHCP_DB::t( 'so_chi' );
				if ( $replace ) { $wpdb->query( "DELETE FROM $t" ); }
				foreach ( $rows as $r ) {
					$loai = self::c( $r, 2 );
					if ( $loai === '' && self::c( $r, 3 ) === '' ) { $skipped++; continue; }
					// Mã tài khoản lấy từ danh mục loại chi phí ngay khi nạp, đúng như nhập tay.
					$res = VHCP_SoChi::add( array(
						'ngay'     => self::c( $r, 0 ),
						'coso'     => self::c( $r, 1 ),
						'loai'     => $loai,
						'noiDung'  => self::c( $r, 3 ),
						'dvt'      => self::c( $r, 4 ),
						'soLuong'  => self::n( self::c( $r, 5 ) ),   // qua bộ đọc số kiểu Việt Nam (1.234.567)
						'donGia'   => self::n( self::c( $r, 6 ) ),
						'soTien'   => self::n( self::c( $r, 7 ) ),
						'hinhThuc' => ( self::c( $r, 8 ) !== '' ? self::c( $r, 8 ) : 'Tạm ứng NV' ),
						'thueSuat' => self::n( self::c( $r, 9 ) ),
						'vat'      => self::c( $r, 10 ),
						'doiTuong' => self::c( $r, 11 ),
						'ghiChu'   => self::c( $r, 12 ),
						'anh'      => self::c( $r, 13 ),
					), 'Nhập từ CSV' );
					if ( empty( $res['success'] ) ) { $skipped++; continue; }
					if ( empty( $res['tkNo'] ) ) { $thieu_ma++; }
					$n++;
				}
				break;

			case 'NhatKy':
				$t = VHCP_DB::t( 'log' );
				if ( $replace ) { $wpdb->query( "DELETE FROM $t" ); }
				foreach ( $rows as $r ) {
					if ( self::c( $r, 0 ) === '' && self::c( $r, 1 ) === '' ) { $skipped++; continue; }
					$wpdb->insert( $t, array(
						'tg'        => self::dt( self::c( $r, 0 ) ),
						'nguoi'     => self::c( $r, 1 ),
						'vai_tro'   => self::c( $r, 2 ),
						'hanh_dong' => self::c( $r, 3 ),
						'doi_tuong' => self::c( $r, 4 ),
						'chi_tiet'  => self::c( $r, 5 ),
					) );
					$n++;
				}
				break;

			default:
				return VHCP_Util::err( 'Chưa hỗ trợ loại tab này' );
		}

		VHCP_Cfg::clear_cache();
		return VHCP_Util::ok( array( 'inserted' => $n, 'skipped' => $skipped, 'thieuMa' => $thieu_ma ) );
	}

	/** Đếm dòng từng bảng — hiện ở trang quản trị. */
	public static function counts() {
		global $wpdb;
		$out = array();
		$map = array(
			'Đơn vận hành'        => 'don',
			'Tạm ứng theo cơ sở'  => 'tamung',
			'Dòng chi phí'        => 'chiphi',
			'Dự án kỹ thuật'      => 'da_index',
			'Dòng hạng mục dự án' => 'da_line',
			'Đơn marketing'       => 'mk_don',
			'Hạng mục marketing'  => 'mk_line',
			'Đợt Công tác/Setup'  => 'bp_index',
			'Dòng Công tác/Setup' => 'bp_line',
			'Dòng sổ chi phí'     => 'so_chi',
			'Hàng cấu hình'       => 'cfg',
			'Nhật ký'             => 'log',
		);
		foreach ( $map as $label => $tbl ) {
			$t = VHCP_DB::t( $tbl );
			$out[ $label ] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t" );
		}
		return $out;
	}
}
