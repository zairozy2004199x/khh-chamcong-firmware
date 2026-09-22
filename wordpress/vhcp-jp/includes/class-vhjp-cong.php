<?php
/**
 * CỔNG — dịch `google.script.run` của Apps Script thành lệnh gọi PHP.
 *
 * =============================================================================================
 * 🔴 NHỜ CỔNG NÀY, 11 TỆP GIAO DIỆN CỦA JP CHẠY GẦN NHƯ NGUYÊN VẸN.
 * =============================================================================================
 * Giao diện JP (≈11.000 dòng) gọi máy chủ qua đúng một chỗ — hàm `srv(fn, ...)` trong
 * `Js01_Core.html` — và nó dựng trên `google.script.run`. Dựng lại đúng cái API ấy bằng
 * JavaScript (`assets/js/gas-shim.js`) thì phần giao diện KHÔNG PHẢI VIẾT LẠI. Đây đúng lối bộ
 * Chi Phí đã đi, và anh Thắng gửi lại bản ấy làm mẫu ngày 21/09/2026.
 *
 * ---------------------------------------------------------------------------------------------
 * 🔴 BẢNG HÀM LÀ DANH SÁCH CHO PHÉP, KHÔNG PHẢI DANH SÁCH LOẠI TRỪ.
 * ---------------------------------------------------------------------------------------------
 * Tên hàm đi vào từ trình duyệt. Gọi thẳng `call_user_func` trên cái tên ấy là mở cửa cho người
 * ngoài chạy BẤT KỲ hàm nào của WordPress. Chỉ tên có trong `map()` mới chạy được; mọi tên
 * khác bị chối trước khi chạm tới bất cứ thứ gì.
 *
 * ---------------------------------------------------------------------------------------------
 * 🔴 GÁC QUYỀN Ở MÁY CHỦ, KHÔNG TIN GIAO DIỆN.
 * ---------------------------------------------------------------------------------------------
 * Bản Apps Script gác quyền bằng cách gọi `jpNeedKT_()` bên TRONG từng hàm — tức là quên một
 * hàm là hở một cửa, và không ai đếm được còn hở chỗ nào. Ở đây bảng `vai_can()` nằm CẠNH bảng
 * hàm, nên đọc một lượt là thấy hết, và bài kiểm đếm được.
 *
 * ---------------------------------------------------------------------------------------------
 * ⚠️ BA ĐƯỜNG VÀO, CÙNG MỘT BỘ XỬ LÝ.
 * ---------------------------------------------------------------------------------------------
 * Nhiều hosting (LiteSpeed/ModSecurity) hay plugin bảo mật chặn thẳng `/wp-json/` và trả 403
 * kèm một trang HTML — không phải lỗi của app. Nên có thêm `admin-ajax.php`. Bộ Chi Phí đã gặp
 * thật; chép sẵn đường lùi còn hơn để anh Thắng ngồi chờ lúc cài lên host mới.
 *
 * ⚠️ THẺ PHIÊN LÀ THAM SỐ ĐẦU TIÊN, không phải một trường riêng — vì giao diện JP gọi
 *    `srv('jpBootstrap', APP.token)`. Giữ đúng hình dạng ấy là giao diện không phải sửa.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHJP_Cong {

	/** Gọi được KHÔNG cần thẻ phiên. Đúng một hàm, và phải luôn đúng một. */
	public static function cong_khai() {
		return array( 'jpLoginPin' );
	}

	/**
	 * Ba đường phải sống khi tài khoản còn PIN mặc định — để còn đổi được PIN.
	 * Mở thêm hàm nào vào đây là mở đúng ngần ấy cửa cho tài khoản chưa đổi PIN.
	 */
	public static function cho_pin_mac_dinh() {
		return array( 'jpBootstrap', 'jpDoiPin', 'jpLogout' );
	}

	/**
	 * Hàm nào đòi vai kế toán. Gác ở đây, KHÔNG gác bên trong từng hàm.
	 *
	 * ⚠️ `jpCfgListUsers` phải nằm trong này: nó trả danh sách tài khoản. Bản Apps Script gác
	 *    nó bên trong hàm; sót một chỗ như vậy là ai có đường dẫn cũng đọc được.
	 */
	public static function chi_ke_toan() {
		return array(
			'jpCfgListUsers', 'jpCfgSaveUser',
			'jpKtListReports', 'jpKtGetReport', 'jpKtApprove', 'jpKtReject',
			'jpCfgSaveLocation', 'jpCfgSaveCluster', 'jpCfgSaveMachine',
			'jpCfgSaveItem', 'jpCfgImportItems',
			'jpTaoPinCoSo', 'jpPinTheoCoSo',
		);
	}

	/**
	 * Hàm nào đòi vai NHÂN VIÊN CƠ SỞ.
	 *
	 * ⚠️ `jpGetReport` KHÔNG nằm trong này, và đó là chủ ý: kế toán phải mở được báo cáo của
	 *    mọi cơ sở để còn duyệt. Cửa hẹp hơn nằm bên trong — `VHJP_BaoCao::lay()` vẫn đòi
	 *    đúng cơ sở với người không phải kế toán.
	 */
	public static function chi_nhan_vien() {
		return array( 'jpMyReports', 'jpOpenReport', 'jpSaveReport', 'jpSubmitReport' );
	}

	/** Bảng tên hàm (như bên Apps Script) -> callable PHP. Danh sách CHO PHÉP. */
	public static function map() {
		return array(
			/* phiên */
			'jpLoginPin'          => array( 'VHJP_Cong', 'dang_nhap' ),
			'jpLogout'            => array( 'VHJP_Cong', 'thoat' ),
			'jpBootstrap'         => array( 'VHJP_Cong', 'khoi_dong' ),
			'jpDoiPin'            => array( 'VHJP_Cong', 'doi_pin' ),

			/* danh mục */
			'jpCfgListLocations'  => array( 'VHJP_Cong', 'ds_coso' ),
			'jpCfgSaveLocation'   => array( 'VHJP_Cong', 'luu_coso' ),
			'jpCfgListClusters'   => array( 'VHJP_Cong', 'ds_cum' ),
			'jpCfgSaveCluster'    => array( 'VHJP_Cong', 'luu_cum' ),
			'jpCfgListMachines'   => array( 'VHJP_Cong', 'ds_may' ),
			'jpCfgSaveMachine'    => array( 'VHJP_Cong', 'luu_may' ),
			'jpCfgListItems'      => array( 'VHJP_Cong', 'ds_hang' ),
			'jpCfgSaveItem'       => array( 'VHJP_Cong', 'luu_hang' ),

			/* báo cáo — đường ĐỌC */
			'jpMyReports'         => array( 'VHJP_Cong', 'bc_cua_toi' ),
			'jpGetReport'         => array( 'VHJP_Cong', 'bc_lay' ),
			'jpOpenReport'        => array( 'VHJP_Cong', 'bc_mo' ),
			'jpSaveReport'        => array( 'VHJP_Cong', 'bc_luu' ),
			'jpSubmitReport'      => array( 'VHJP_Cong', 'bc_nop' ),
			'jpPhotoProgress'     => array( 'VHJP_Cong', 'anh_tien_do' ),

			/* ảnh */
			'jpUploadPhoto'       => array( 'VHJP_Cong', 'anh_tai_len' ),
			'jpDeletePhoto'       => array( 'VHJP_Cong', 'anh_xoa' ),
			'jpAnhXem'            => array( 'VHJP_Cong', 'anh_xem' ),
			'jpAnhTheoCoSo'       => array( 'VHJP_Cong', 'anh_theo_coso' ),

			/* kế toán duyệt */
			'jpKtListReports'     => array( 'VHJP_Cong', 'kt_ds' ),
			'jpKtGetReport'       => array( 'VHJP_Cong', 'kt_lay' ),
			'jpKtApprove'         => array( 'VHJP_Cong', 'kt_ky' ),
			'jpKtReject'          => array( 'VHJP_Cong', 'kt_tra_ve' ),
		);
	}

	/**
	 * CÁC HÀM CHƯA CHUYỂN — khai TÊN ĐẦY ĐỦ, không để trống.
	 *
	 * 🔴 ĐÂY LÀ BẢN KIỂM ĐẾM CỦA CẢ CUỘC CHUYỂN, KHÔNG PHẢI GHI CHÚ CHO VUI.
	 *    `kiem-jp-cong.php` đọc THẲNG 11 tệp giao diện gốc, bóc ra mọi tên hàm mà giao diện
	 *    thật sự gọi, rồi đòi: bảng làm rồi + bảng chưa làm = ĐÚNG danh sách ấy, không thừa
	 *    không thiếu. Nhờ vậy:
	 *      · quên chuyển một hàm -> bài kiểm đỏ, chứ không phải người dùng bấm vào mới biết;
	 *      · chuyển xong mà quên xoá khỏi đây -> cũng đỏ;
	 *      · giao diện gọi một tên không ai biết -> cũng đỏ.
	 *    Còn bao nhiêu dòng trong mảng này là còn ngần ấy việc — đếm được, không phải ước.
	 */
	public static function chua_lam() {
		return array(
			/* nộp tiền */
			'jpAddPayment', 'jpCongNoNcc', 'jpCongNoNhanVien', 'jpDeletePayment', 'jpMyMonthHistory',
			'jpMyUnpaid', 'jpPaymentHistory', 'jpSuaNgayNop',
			/* kế toán tổng hợp */
			'jpBangCanDoiPhatSinh', 'jpDoiTkKhoCu', 'jpKetQuaKinhDoanh', 'jpKiemTraButToan',
			'jpQuetDayChuyen', 'jpSo632', 'jpSoCongNo', 'jpSoNhatKyChung',
			/* báo cáo của nhân viên */
			'jpBaoCaoDoanhThuNgay', 'jpGetOpening', 'jpGuiDeNghiTonDau',
			'jpReopenIn24h', 'jpRevenueBoard',
			'jpStockBoard', 'jpSuaKyBaoCao',
			/* cấu hình & tiện ích */
			'jpCfgImportItems', 'jpCfgListUsers', 'jpCfgSaveUser', 'jpDungHeThongMotPhat',
			'jpKiemTraNhanh', 'jpNapBuTonDauKy31_7', 'jpNapCoSo', 'jpNapDanhMucHangJP',
			'jpNapTonDauKy31_7', 'jpPinTheoCoSo', 'jpSapXepLaiKy', 'jpTaoPinCoSo', 'jpTinhLaiCanhBao',
			'jpTinhTrangDungHeThong', 'jpXoaBaoCaoNhap',
			/* đối soát ngân hàng */
			'jpChuaKhaiCachThu', 'jpConfirmPaidManual', 'jpDatLichDoiSoatNH', 'jpDocGiaoDichNganHang',
			'jpDoiSoatNganHang', 'jpKhaiCachThu', 'jpLichDoiSoatNH', 'jpReconApply', 'jpReconHistory',
			'jpReconPreview', 'jpReconUndo', 'jpXacNhanCotNganHang',
			/* kho hai tầng */
			'jpKhoDanhSachLoai', 'jpKhoDanhSachNcc', 'jpKhoHuyNhap', 'jpKhoHuyTraNcc', 'jpKhoKiemKe',
			'jpKhoKiemKeTon', 'jpKhoLichSuDauKy', 'jpKhoLichSuKiemKe', 'jpKhoLichSuNhap',
			'jpKhoLichSuTraNcc', 'jpKhoLichSuXuat', 'jpKhoNhap', 'jpKhoNhapXuatTon', 'jpKhoSoDuDauKy',
			'jpKhoTheKho', 'jpKhoTonKho', 'jpKhoTraNcc', 'jpKhoTraNccLo', 'jpKhoXuat', 'jpKhoXuatLai',
			/* kế toán duyệt */
			'jpKtDanhSachDeNghi', 'jpKtPaymentBoard', 'jpKtReopen', 'jpKtXuLyDeNghi',
		);
	}

	/* ═══════════════════════ BỘ XỬ LÝ ═══════════════════════ */

	/**
	 * Chạy một lệnh gọi. Trả `array( 'ma' => mã HTTP, 'than' => dữ liệu )`.
	 *
	 * ⚠️ KHÔNG ném lỗi ra ngoài: cổng mà ném là hosting trả một trang HTML lỗi, và giao diện
	 *    nhận về thứ không giải mã được rồi báo một câu vô nghĩa.
	 */
	public static function goi( $fn, $args ) {
		$fn   = (string) $fn;
		$args = is_array( $args ) ? array_values( $args ) : array();
		$map  = self::map();

		if ( ! isset( $map[ $fn ] ) ) {
			/* Chưa chuyển thì nói rõ là CHƯA CHUYỂN, khác hẳn "không có hàm này" — người dùng
			   cần biết là tính năng sẽ có, còn người sửa cần biết là mình chưa làm. */
			if ( in_array( $fn, self::chua_lam(), true ) ) {
				return array( 'ma' => 501, 'than' => array( 'ok' => false,
					'msg' => 'Phần này chưa chuyển sang bản trên hosting: ' . $fn ) );
			}
			return array( 'ma' => 400, 'than' => array( 'ok' => false, 'msg' => 'Lệnh không hợp lệ' ) );
		}

		/* Hàm công khai: gọi thẳng, không đọc thẻ. */
		if ( in_array( $fn, self::cong_khai(), true ) ) {
			return self::chay( $map[ $fn ], $args, null );
		}

		/* Mọi hàm khác: thẻ phiên là THAM SỐ ĐẦU TIÊN — đúng hình dạng giao diện JP đang gửi. */
		$token = isset( $args[0] ) ? $args[0] : '';
		$kiem  = VHJP_Auth::kiem( $token, in_array( $fn, self::cho_pin_mac_dinh(), true ) );
		if ( empty( $kiem['ok'] ) ) {
			/* Giữ nguyên hai mã lỗi mà giao diện JP đang bắt theo chuỗi (`Js01_Core.html`):
			   `SESSION_EXPIRED` bày lại ô PIN, `PHAI_DOI_PIN` mở màn đổi PIN. Đổi chữ ở đây là
			   giao diện im lặng không hiểu, và người dùng kẹt ở một màn trắng. */
			$ma = 'PHAI_DOI_PIN' === $kiem['ma'] ? 'PHAI_DOI_PIN' : 'SESSION_EXPIRED';
			return array( 'ma' => 401, 'than' => array( 'ok' => false, 'error' => $ma,
				'msg' => isset( $kiem['msg'] ) ? $kiem['msg'] : $ma ) );
		}

		if ( in_array( $fn, self::chi_ke_toan(), true ) && ! VHJP_Auth::la_kt( $kiem['user'] ) ) {
			VHJP_NhatKy::ghi( $kiem['user'], 'CHAN_QUYEN', '', $fn, '' );
			return array( 'ma' => 403, 'than' => array( 'ok' => false,
				'msg' => 'Việc này cần tài khoản kế toán' ) );
		}

		if ( in_array( $fn, self::chi_nhan_vien(), true ) && ! VHJP_Auth::la_nv( $kiem['user'] ) ) {
			VHJP_NhatKy::ghi( $kiem['user'], 'CHAN_QUYEN', '', $fn, '' );
			return array( 'ma' => 403, 'than' => array( 'ok' => false,
				'msg' => VHJP_Auth::cau_chan_nv( $kiem['user'] ) ) );
		}

		return self::chay( $map[ $fn ], $args, $kiem['user'] );
	}

	/** Gọi hàm thật, bắt mọi lỗi để cổng luôn trả JSON. */
	private static function chay( $ham, $args, $nguoi ) {
		try {
			return array( 'ma' => 200, 'than' => call_user_func( $ham, $args, $nguoi ) );
		} catch ( Throwable $e ) {
			/* 🔴 KHÔNG đẩy câu lỗi gốc ra trình duyệt: nó hay kèm đường dẫn tệp trên máy chủ và
			   một mẩu câu SQL. Ghi vào nhật ký cho người sửa, trả câu chung cho người dùng. */
			VHJP_NhatKy::ghi( $nguoi, 'LOI_COD', '', '', array( 'loi' => $e->getMessage() ) );
			return array( 'ma' => 500, 'than' => array( 'ok' => false,
				'msg' => 'Máy chủ gặp lỗi khi xử lý. Đã ghi nhật ký.' ) );
		}
	}

	/* ═══════════════════════ CÁC HÀM ĐÃ CHUYỂN ═══════════════════════ */
	/* Mỗi hàm nhận `( $args, $nguoi )` — `$args[0]` là thẻ phiên, đúng hình dạng giao diện gửi. */

	public static function dang_nhap( $args ) {
		return VHJP_Auth::dang_nhap( isset( $args[0] ) ? $args[0] : '' );
	}
	public static function thoat( $args ) {
		return VHJP_Auth::thoat( isset( $args[0] ) ? $args[0] : '' );
	}
	/**
	 * Dựng màn hình đầu tiên — danh mục người này được phép thấy, kèm mấy hằng đơn giá.
	 *
	 * ⚠️ MỘT LƯỢT GỌI, KHÔNG BA. Bản Apps Script từng tách thành ba đợt nối tiếp và anh Andy
	 *    đo được *"bấm cái nào cũng thấy load lâu quá"*. Gộp lại vẫn đáng giữ ở đây: mỗi lượt
	 *    gọi là một vòng mạng, và nhân viên gặp màn này mỗi ca.
	 *
	 * ⚠️ Phải sống KỂ CẢ khi tài khoản còn PIN mặc định — không thì không ai dựng nổi màn hình
	 *    để bấm nút đổi PIN, và cả hệ kẹt.
	 */
	public static function khoi_dong( $args, $nguoi ) {
		$coso = array();
		$ma_coso = array();
		foreach ( VHJP_CauHinh::ds_coso() as $l ) {
			if ( ! VHJP_Auth::xem_duoc_coso( $nguoi, $l['id'] ) ) { continue; }
			$coso[]    = $l;
			$ma_coso[] = (string) $l['id'];
		}
		$loc = function ( $ds ) use ( $ma_coso ) {
			$ra = array();
			foreach ( $ds as $x ) {
				if ( in_array( (string) $x['locationId'], $ma_coso, true ) ) { $ra[] = $x; }
			}
			return $ra;
		};
		return array(
			'ok'        => true,
			'user'      => VHJP_Auth::user_cong_khai( $nguoi ),
			'locations' => $coso,
			'clusters'  => $loc( VHJP_CauHinh::ds_cum() ),
			'machines'  => $loc( VHJP_CauHinh::ds_may() ),
			'items'     => VHJP_CauHinh::ds_hang(),
			'rates'     => array(
				'moneyPulse' => VHJP_Tinh::GIA_XUNG_MAC_DINH,
				'coin'       => VHJP_Tinh::GIA_XU,
				'coinMoney'  => VHJP_Tinh::GIA_XU_TIEN,
			),
		);
	}

	public static function doi_pin( $args ) {
		return VHJP_Auth::doi_pin(
			isset( $args[0] ) ? $args[0] : '',
			isset( $args[1] ) ? $args[1] : '',
			isset( $args[2] ) ? $args[2] : '' );
	}

	public static function ds_coso()  { return VHJP_CauHinh::ds_coso( true ); }
	public static function ds_hang()  { return VHJP_CauHinh::ds_hang( true ); }
	public static function ds_cum( $args ) {
		return VHJP_CauHinh::ds_cum( isset( $args[1] ) ? $args[1] : '', true );
	}
	public static function ds_may( $args ) {
		return VHJP_CauHinh::ds_may( isset( $args[1] ) ? $args[1] : '', true );
	}
	public static function luu_coso( $args, $nguoi ) {
		return VHJP_CauHinh::luu_coso( $nguoi, isset( $args[1] ) ? $args[1] : array() );
	}
	public static function luu_cum( $args, $nguoi ) {
		return VHJP_CauHinh::luu_cum( $nguoi, isset( $args[1] ) ? $args[1] : array() );
	}
	public static function luu_may( $args, $nguoi ) {
		return VHJP_CauHinh::luu_may( $nguoi, isset( $args[1] ) ? $args[1] : array() );
	}
	public static function luu_hang( $args, $nguoi ) {
		return VHJP_CauHinh::luu_hang( $nguoi, isset( $args[1] ) ? $args[1] : array() );
	}

	/* ── báo cáo: đường ĐỌC ──
	 * Đường GHI (`jpOpenReport` · `jpSaveReport` · `jpSubmitReport`) chưa chuyển, nên mở được
	 * báo cáo ĐÃ CÓ chứ chưa tạo mới được. Giao diện vẫn nhận "chưa chuyển" ở mấy nút kia. */
	public static function bc_cua_toi( $args, $nguoi ) {
		return VHJP_BaoCao::cua_toi( $nguoi, isset( $args[1] ) ? $args[1] : 0 );
	}
	public static function bc_lay( $args, $nguoi ) {
		return VHJP_BaoCao::lay( $nguoi, isset( $args[1] ) ? $args[1] : '' );
	}
	/* `jpOpenReport(token, locationId, fromDate, toDate, machineType)` — giữ đúng thứ tự tham
	   số giao diện đang gửi, không đổi sang một object cho "gọn": đổi là phải sửa giao diện,
	   mà giao diện thì cố ý giữ nguyên văn để hai bản còn so số được. */
	public static function bc_luu( $args, $nguoi ) {
		return VHJP_BaoCao::luu( $nguoi, isset( $args[1] ) ? $args[1] : array() );
	}
	/* ── kế toán duyệt ── (gác vai kế toán ở `chi_ke_toan()`, cạnh bảng hàm) */
	public static function kt_ds( $args, $nguoi ) {
		return VHJP_Duyet::ds_bao_cao( $nguoi, isset( $args[1] ) ? $args[1] : array() );
	}
	public static function kt_lay( $args, $nguoi ) {
		return VHJP_Duyet::lay( $nguoi, isset( $args[1] ) ? $args[1] : '' );
	}
	public static function kt_ky( $args, $nguoi ) {
		return VHJP_Duyet::ky( $nguoi, isset( $args[1] ) ? $args[1] : '',
			isset( $args[2] ) ? $args[2] : '', isset( $args[3] ) ? $args[3] : '' );
	}
	public static function kt_tra_ve( $args, $nguoi ) {
		return VHJP_Duyet::tra_ve( $nguoi, isset( $args[1] ) ? $args[1] : '',
			isset( $args[2] ) ? $args[2] : '' );
	}

	public static function bc_nop( $args, $nguoi ) {
		return VHJP_BaoCao::nop( $nguoi, isset( $args[1] ) ? $args[1] : '' );
	}
	/* ⚠️ `jpPhotoProgress` KHÔNG gác vai nhân viên: kế toán mở báo cáo cũng phải thấy còn
	   thiếu mấy chỗ ảnh, đó là căn cứ để trả về. Cửa hẹp hơn nằm trong `VHJP_Anh::tien_do()`. */
	public static function anh_tien_do( $args, $nguoi ) {
		return VHJP_Anh::tien_do( $nguoi, isset( $args[1] ) ? $args[1] : '' );
	}

	/* ⚠️ `$args[0]` là THẺ PHIÊN — cổng đã đổi thẻ ra `$nguoi` trước khi gọi tới đây, nên mọi
	   tham số nghiệp vụ bắt đầu từ `$args[1]`. Đếm nhầm một nấc là hàm nhận thẻ làm dữ liệu. */
	public static function anh_tai_len( $args, $nguoi ) {
		return VHJP_Anh::tai_len( $nguoi, isset( $args[1] ) ? $args[1] : array() );
	}
	public static function anh_xoa( $args, $nguoi ) {
		return VHJP_Anh::xoa( $nguoi, isset( $args[1] ) ? $args[1] : '' );
	}
	public static function anh_xem( $args, $nguoi ) {
		return VHJP_Anh::xem( $nguoi, isset( $args[1] ) ? $args[1] : '' );
	}
	public static function anh_theo_coso( $args, $nguoi ) {
		return VHJP_Anh::theo_coso( $nguoi, isset( $args[1] ) ? $args[1] : array() );
	}
	public static function bc_mo( $args, $nguoi ) {
		return VHJP_BaoCao::mo( $nguoi,
			isset( $args[1] ) ? $args[1] : '',
			isset( $args[2] ) ? $args[2] : '',
			isset( $args[3] ) ? $args[3] : '',
			isset( $args[4] ) ? $args[4] : '' );
	}
}
