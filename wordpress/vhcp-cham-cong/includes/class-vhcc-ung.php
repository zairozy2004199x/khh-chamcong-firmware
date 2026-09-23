<?php
/**
 * LƯỚI ỨNG DỤNG — tab "Ứng dụng" của trạm, mỗi ô là một hệ mà người này thật sự vào được.
 *
 * =============================================================================================
 * 🔴 MỖI Ô MỘT CỔNG RIÊNG. KHÔNG CÓ MỘT PHÉP GÁC CHUNG NÀO Ở ĐÂY
 * =============================================================================================
 * Cái bẫy của màn này là trông nó như một danh sách đồng nhất, nên rất dễ viết một vòng lặp
 * `foreach ( VHCC_Cong::ds() as … ) if ( duoc_vao() ) …` rồi tưởng xong. Làm vậy là sai với
 * đúng ô quan trọng nhất.
 *
 * `VHCC_Cong::SO` CỐ Ý chỉ khai những trang gác cửa bằng PHIÊN CHẤM CÔNG — chú thích ở đầu
 * `class-vhcc-cong.php` viết thẳng: *"Đừng thêm một dòng `'ghe' => …` vào mảng dưới đây: nó sẽ
 * cho tích, và tích xong không có gì đổi."* Hệ ghế POSH có phiên riêng bằng PIN, cố ý tách ra
 * vì đó là màn có doanh thu và phải đá được một người ra ngay mà không kéo theo app kia. Nó
 * không đọc `ma_nv`, nên mọi ngoại lệ khai bên chấm công đều không bám vào đâu.
 *
 * Vậy ô POSH gác bằng thứ khác, và là thứ duy nhất có thật: NGƯỜI NÀY ĐÃ ĐƯỢC ĐẨY SANG SỔ
 * NGƯỜI DÙNG CỦA HỆ GHẾ CHƯA — `VHCC_DayGhe::da_day()`, đúng cột "Ghế massage" ở màn Quản lý
 * nhân sự.
 *
 * =============================================================================================
 * 🔴 CHƯA ĐƯỢC CẤP THÌ Ô MỜ, KHÔNG PHẢI Ô VẮNG — ĐỔI 17/09/2026
 * =============================================================================================
 * Bản trước ẩn hẳn ô chưa được cấp, lý lẽ là "đừng để người ta bấm vào rồi nhận một câu chối
 * không hiểu nổi". Anh Thắng đổi: *"vẫn hiện nhưng ẩn mờ không bấm được (…) để ai cũng biết
 * công ty mình đầy đủ chức năng"*.
 *
 * Cách mới thực ra giải quyết nỗi lo cũ TỐT HƠN cách cũ: ô mờ KHÔNG PHẢI thẻ `<a>`, nên không
 * có cú bấm chết nào cả, mà lại nói thẳng "chưa được cấp" kèm chỗ đi xin. Ẩn hẳn thì người ta
 * không biết thứ ấy tồn tại để mà xin.
 *
 * ⚠️ RANH GIỚI PHẢI GIỮ: MỜ chỉ dành cho hệ CÓ CÀI mà người này chưa được cấp. Hệ chưa cài trên
 *    site thì vẫn ẩn hẳn — bày một ô "Chi phí cơ sở" mờ trên một site không có plugin chi phí
 *    là nói công ty có một thứ không tồn tại, và người đi xin sẽ xin một cái không ai cấp được.
 *
 * ⚠️ Ô MỜ KHÔNG MANG ĐỊA CHỈ. Không phải vì địa chỉ là bí mật (`/chi-phi` thì ai cũng đoán ra),
 *    mà để không có đường nào biến nó thành bấm được bằng một dòng CSS sửa nhầm. Gác thật vẫn
 *    nằm ở cửa vào của chính trang kia — đây chỉ là lớp ngoài.
 *
 * =============================================================================================
 * ⚠️ Ô POSH DẪN SANG MỘT PHIÊN KHÁC, VÀ MÀN HÌNH PHẢI NÓI RA ĐIỀU ĐÓ
 * =============================================================================================
 * Bấm vào ô POSH là sang `/ghe/`, và ở đó họ phải gõ PIN LẦN NỮA — không phải lỗi, mà là chốt
 * an toàn của hệ ghế. Nhưng nếu màn hình im lặng thì người dùng gặp một cửa PIN bất ngờ và
 * kết luận "app lỗi, mất đăng nhập". Nên ô ấy mang sẵn một dòng chữ nhỏ nói trước.
 *
 * Ba ô còn lại dùng chung thẻ phiên với trạm nên bấm là vào thẳng.
 *
 * =============================================================================================
 * =============================================================================================
 * ⚠️ LƯỚI DỰNG Ở TRÌNH DUYỆT, NHƯNG PHÉP GÁC VẪN Ở MÁY CHỦ
 * =============================================================================================
 * Bản đầu của tệp này có một hàm `ve()` in thẳng HTML từ `templates/tram.php`, với lý lẽ "gác
 * ở máy chủ thì không lộ mình bị khoá gì". Lý lẽ đúng, nhưng CHỖ GỌI thì sai: `VHCC_Tram::render()`
 * chạy TRƯỚC khi có ai đăng nhập — trạm gửi xuống một cái vỏ rỗng rồi mới hỏi PIN bằng JS. Lúc
 * vẽ template chưa biết người dùng là ai, nên `ve( $u )` không có `$u` mà truyền.
 *
 * Nên luồng đúng là: JS gọi `?viec=ung` SAU khi đăng nhập, máy chủ chạy `ds()` rồi trả về ĐÚNG
 * những ô người ấy vào được. Phép gác vẫn nằm trọn ở máy chủ và vẫn không lộ gì — trình duyệt
 * chưa bao giờ nhận được ô bị khoá để mà ẩn đi.
 *
 * =============================================================================================
 * ⚠️ HÀM `url()` PHẢI CÓ THẬT TRƯỚC KHI GỌI
 * =============================================================================================
 * Mỗi ô hỏi một lớp ở plugin khác. Plugin ấy có thể chưa cài, hoặc cài bản cũ chưa có `url()`.
 * Gọi hụt một hàm tĩnh là Fatal error — trắng cả trang trạm, tức là mất luôn đường chấm công
 * chỉ vì một ô phụ. Nên mọi lời gọi chéo đều qua `class_exists` + `method_exists` (luật của
 * `tools/test/kiem-goi-cheo.php`).
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_Ung {

	/* ══════════════════════════════════════════════════════════════════════════════════════
	 * CHIA NHÓM — anh Thắng 17/09/2026: *"tính năng nhiều thì ô chức năng nhỏ lại… nhiều tính
	 * năng thì tách phân loại theo từng tính năng"*, kèm ảnh một app chia mục WORKPLACE / HRM.
	 *
	 * 🔴 THỨ TỰ NHÓM KHAI Ở ĐÂY, KHÔNG SẮP THEO CHỮ CÁI. Sắp theo chữ cái thì nhóm nào lên đầu
	 *    là chuyện ngẫu nhiên của tiếng Việt, và nó đổi chỗ mỗi lần thêm một nhóm mới — người
	 *    dùng nhớ vị trí bằng mắt chứ không đọc lại tiêu đề mỗi lần.
	 *
	 * ⚠️ Ô mang tên nhóm KHÔNG có trong danh sách này thì rơi xuống cuối, giữ nguyên thứ tự
	 *    khai. Thà thừa một nhóm lạ ở cuối còn hơn nuốt mất một ô người ta đang cần.
	 */
	const NHOM = array( 'Vận hành', 'Quản lý cửa hàng', 'Của tôi', 'Thông tin chung' );

	/** Nhóm của các ô, theo đúng thứ tự trên. Ô nào không khai nhóm thì về 'Vận hành'. */
	public static function ds_nhom( $ds ) {
		$co = array();
		foreach ( (array) $ds as $x ) {
			$n = ( isset( $x['nhom'] ) && '' !== trim( (string) $x['nhom'] ) )
				? (string) $x['nhom'] : self::NHOM[0];
			if ( ! in_array( $n, $co, true ) ) { $co[] = $n; }
		}
		$ra = array();
		foreach ( self::NHOM as $n ) { if ( in_array( $n, $co, true ) ) { $ra[] = $n; } }
		foreach ( $co as $n ) { if ( ! in_array( $n, $ra, true ) ) { $ra[] = $n; } }
		return $ra;
	}

	/**
	 * Danh sách ô cho MỘT người. Trả mảng: ten · mo · url · icon · mau · nhom · ghi_chu · ngoai.
	 *
	 * @param array $u Người đang đăng nhập (mảng phiên của `VHCC_Tram::nguoi()`).
	 */
	public static function ds( $u ) {
		$ma = isset( $u['ma_nv'] ) ? $u['ma_nv'] : '';
		$o  = array();

		/* ══════════════════════════════════════════════════════════════════════════════════
		   Ô "QUẢN TRỊ CHẤM CÔNG" ĐÃ BỎ — anh Thắng 17/09/2026: *"ẩn trang quản trị chấm công
		   trên app chấm công"*.

		   Lịch sử để người sau khỏi tưởng là sót: nó vốn là dòng "Trang quản trị →" ở cuối màn
		   chính, rồi thành một ô trong lưới này. Nay bỏ hẳn khỏi app điện thoại.

		   ⚠️ HỆ QUẢ, NÓI RA CHỨ KHÔNG GIẤU: đây từng là đường DUY NHẤT từ trạm sang trang quản
		      trị. Bỏ rồi thì trên điện thoại không còn lối sang — phải vào từ Cổng K&H hoặc
		      máy tính. Chấp nhận được vì trang ấy là bảng công / nhân sự / lịch: bảng rộng,
		      làm trên máy tính, không phải thứ mở giữa ca ở cơ sở.

		   🔴 KHÔNG XOÁ `VHCC_VeTram::nut()` BÊN `class-vhcc-web.php`. Trang quản trị vẫn có thể
		      được mở từ nơi khác với `?ve=tram` (một đường dẫn cũ ai đó lưu lại, chẳng hạn), và
		      lúc ấy nút về trạm vẫn phải còn. Ô biến mất khỏi lưới không có nghĩa là đường về
		      cũng biến mất.
		   ══════════════════════════════════════════════════════════════════════════════════ */

		/* ---- 1. Nộp báo cáo POSH --------------------------------------------------------
		   🔴 KHÔNG hỏi `VHCC_Cong::duoc_vao( $u, 'ghe' )` — xem khối chú thích đầu tệp. Cổng
		      thật là sổ người dùng của hệ ghế. */
		if ( self::co_lop( 'VHG_Trang', 'url' ) && self::co_lop( 'VHCC_DayGhe', 'da_day' ) ) {
			$o[] = self::o(
				VHCC_DayGhe::da_day( $ma ),
				array(
					'ten'     => 'Nộp báo cáo POSH',
					'nhom'    => 'Vận hành',
					'mo'      => 'Doanh thu ghế, tiền mặt, chứng từ',
					'url'     => VHCC_VeTram::danh_dau( VHG_Trang::url() ),
					'icon'    => '🪑',
					'mau'     => 'vang',
					'ghi_chu' => 'Gõ lại chính PIN chấm công của bạn',
					'xin'     => 'Xin quản lý đẩy sang hệ ghế (cột Ghế massage).',
				)
			);
		}

		/* ---- 2. Báo cáo FABi ----------------------------------------------------
		   🔴 GÁC BẰNG SỔ ĐÃ ĐẨY, KHÔNG BẰNG VAI "Cửa hàng trưởng". Hai thứ ấy KHÔNG trùng
		      nhau: có cửa hàng trưởng mới lên chưa được đẩy, có người vai khác được đẩy vì
		      kiêm việc. Đoán theo vai là bộ luật quyền thứ hai, mà bộ thứ hai bao giờ cũng
		      lệch trước.

		   ⚠️ `co_he_bao_cao()` dò TỪNG HÀM chứ không dò tên lớp: plugin `khh-doanh-thu` cài
		      độc lập nên bản có thể lệch. */
		if ( self::co_lop( 'VHCC_DayBaoCao', 'co_he_bao_cao' )
			&& VHCC_DayBaoCao::co_he_bao_cao()
			&& function_exists( 'khh_dt_link' )
			&& method_exists( 'VHCC_DayBaoCao', 'da_day' ) ) {
			$o[] = self::o(
				VHCC_DayBaoCao::da_day( $ma ),
				array(
					/* ⚠️ TÊN Ô ĐỔI 17/09/2026 — anh Thắng: *"Bổ sung Báo Cáo Fabi vào Vận Hành"*.
					   Dò ra thì ô này VỐN ĐÃ là FABi: nó trỏ vào `khh-doanh-thu`, mà tên đầy đủ
					   của plugin ấy là *"K&H — Báo cáo doanh thu FABi"* (nạp file xuất từ máy POS
					   FABi / iPOS). Thêm một ô mới tên "Báo cáo FABi" là hai ô cạnh nhau cùng mở
					   một trang — người dùng bấm thử cả hai rồi không hiểu khác nhau chỗ nào.
					   Nên: ĐỔI NHÃN cho đúng cái tên người ta gọi hằng ngày, không đẻ thêm ô. */
					'ten'  => 'Báo cáo FABi',
					'nhom' => 'Vận hành',
					'mo'   => 'Doanh thu POS, tiền nộp, bill huỷ',
					'url'  => VHCC_VeTram::danh_dau( khh_dt_link() ),
					'icon' => '🏪',
					'mau'  => 'luc',
					'xin'  => 'Xin quản lý đẩy sang Báo cáo cơ sở.',
					/* Màn ấy đăng nhập bằng CHÍNH PIN chấm công (xem đầu
					   `class-vhcc-day-bao-cao.php`) — nên KHÔNG có dòng nhắc gõ lại như ô POSH.
					   Hai hệ khác nhau đúng ở chỗ này, và nói sai thì người dùng ngồi chờ một
					   cửa PIN không bao giờ hiện. */
				)
			);
		}

		/* ---- 3. Chi phí cơ sở -----------------------------------------------------------
		   🔴 CŨNG KHÔNG hỏi `VHCC_Cong::duoc_vao( $u, 'chi_phi' )`. Sổ quyền trang cố ý vắng
		      mặt app chi phí vì nó có SỔ NGƯỜI DÙNG RIÊNG (`VHCP_Cfg`), và chú thích ở đó nói
		      rõ: *"hứa ở đây mà không có hiệu lực thì tệ hơn là không hứa"*. */
		if ( self::co_lop( 'VHCC_DayChiPhi', 'co_he' )
			&& VHCC_DayChiPhi::co_he()
			&& self::co_lop( 'VHCP_App', 'app_url' )
			&& method_exists( 'VHCC_DayChiPhi', 'da_day' ) ) {
			$o[] = self::o(
				VHCC_DayChiPhi::da_day( $ma ),
				array(
					'ten'  => 'Chi phí cơ sở',
					'nhom' => 'Vận hành',
					'mo'   => 'Đề nghị chi, chứng từ, duyệt',
					'url'  => VHCC_VeTram::danh_dau( VHCP_App::app_url() ),
					'icon' => '💰',
					'mau'  => 'cam',
					'xin'  => 'Xin quản lý đẩy sang Vận hành chi phí.',
				)
			);
		}

		/* ---- 4. Nội bộ ------------------------------------------------------------------ */
		if ( self::co_lop( 'VHNB_Trang', 'url' ) ) {
			$o[] = self::o(
				self::duoc_vao( $u, 'noi_bo' ),
				array(
					'ten'  => 'Nội bộ',
					'nhom' => 'Thông tin chung',
					'mo'   => 'Thông báo, tài liệu chung',
					'url'  => VHCC_VeTram::danh_dau( VHNB_Trang::url() ),
					'icon' => '📄',
					'mau'  => 'tim',
					'xin'  => 'Xin quản lý mở quyền vào trang Nội bộ.',
				)
			);
		}

		/* ---- 4b. JP Capsule ---------------------------------------------------------------
		   Anh Thắng 22/09/2026: đẩy JP vào app điện thoại chấm công, đăng nhập một lần (SSO).

		   🔴 CỬA Ở ĐÂY KHÔNG PHẢI VAI — LÀ "MÃ NV ĐÃ ĐƯỢC NỐI VỚI MỘT TÀI KHOẢN JP ĐANG BẬT".
		   JP có sổ người dùng riêng; bấm sang là `jpSsoChamCong` tra Mã NV trong sổ ấy, thấy
		   thì mở phiên không hỏi PIN, không thấy thì trả về màn PIN của JP — mà nhân viên
		   không có PIN ấy. Nên hỏi đúng câu JP sẽ hỏi (`VHJP_Auth::theo_ma_nv`) ngay lúc vẽ
		   ô: ai vào được thì ô sáng, ai chưa được nối thì ô khoá kèm câu chỉ đường.
		   Đoán theo vai ở đây là dựng một bộ luật thứ hai, và bộ thứ hai bao giờ cũng lệch.

		   ⚠️ Gác `method_exists` ĐÚNG HAI HÀM sắp gọi, ngay trong thân hàm này — luật
		      `tools/test/kiem-goi-cheo.php`. JP là plugin cài độc lập, có thể vắng mặt. */
		if ( self::co_lop( 'VHJP_Trang', 'dia_chi' ) && self::co_lop( 'VHJP_Auth', 'theo_ma_nv' ) ) {
			$ma_nv  = isset( $u['ma_nv'] ) ? (string) $u['ma_nv'] : '';
			$da_noi = '' !== $ma_nv && (bool) VHJP_Auth::theo_ma_nv( $ma_nv );
			$o[] = self::o(
				$da_noi,
				array(
					'ten'  => 'JP Capsule',
					'nhom' => 'Vận hành',
					'mo'   => 'Báo cáo máy JP, nộp tiền, kho',
					'url'  => VHCC_VeTram::danh_dau( (string) VHJP_Trang::dia_chi( false ) ),
					'icon' => '🥚',
					'mau'  => 'vang',
					'xin'  => 'Nhờ kế toán JP nối Mã NV của anh/chị với tài khoản JP (JP Capsule → Nối tài khoản).',
				)
			);
		}

		/* ---- 5. Thêm nhân sự mới ---------------------------------------------------------
		   Anh Thắng 17/09/2026: *"Chuyển sang thêm nhân sự là 1 tính năng"*, kèm ảnh khoanh
		   đúng ô trống trong lưới.

		   🔴 Ô NÀY KHÔNG DẪN ĐI ĐÂU — nó mở một màn NGAY TRONG TRẠM (`man`), khác hẳn bốn ô
		      trên (mỗi ô là một địa chỉ sang app khác). Trước bản này lưới chỉ biết ô có `url`;
		      nay biết cả hai loại, và ô khoá thì mất CẢ HAI đường (xem `o()`).

		   ⚠️ GÁC BẰNG `them_nv` — đúng cái quyền mà `VHCC_NhanSu::them_nv_cua_hang()` đòi.
		      Bày ô cho người không có quyền rồi để họ gõ xong mới bị chối là bắt người ta làm
		      không công; còn gác ở đây bằng MỘT quyền khác với cửa thật là hai luật, và hai
		      luật thì lệch. */
		if ( VHCC_Vai::duoc( $u, 'them_nv' ) ) {
			$o[] = self::o( true, array(
				'ten'  => 'Thêm nhân sự',
				'nhom' => 'Quản lý cửa hàng',
				'mo'   => 'Mở hồ sơ tạm cho người mới vào làm',
				'man'  => 'mThemNv',
				'icon' => '🧑‍💼',
				'mau'  => 'xanh',
			) );
		}

		/* ---- 6. Phiếu lương ---------------------------------------------------------------
		   Anh Thắng 17/09/2026 gạch chéo khối cuối tab Công và chỉ sang ô trống trong lưới:
		   *"Phiếu lương cho vào vị trí này (nhân viên thì 1 phiếu của chính mình). Cửa hàng
		   trưởng thì có chính mình và cả cửa hàng"*.

		   ⚠️ NHÓM ĐỔI THEO VAI, CỐ Ý. Với nhân viên đây là giấy tờ CỦA HỌ; với cửa hàng trưởng
		      nó còn mở ra lương cả cơ sở, tức là một công cụ quản lý — và anh Thắng khoanh đúng
		      ô ấy trong hàng QUẢN LÝ CỬA HÀNG. Một cái tên nằm dưới đúng tiêu đề thì người ta
		      tìm ra bằng mắt; nhét lương của chính mình vào mục "Quản lý cửa hàng" cho một
		      nhân viên thì họ không bao giờ nghĩ để nhìn vào đó.

		   🔴 Ô NÀY KHÔNG GÁC GÌ CẢ, CỐ Ý. Ai cũng có phiếu lương của chính mình; tháng nào chưa
		      công bố thì `VHCC_PhieuLuong::phieu()` chối ngay ở máy chủ, và màn nói rõ là đang
		      chờ kế toán. Giấu ô đi thì người chưa có tháng nào lại tưởng hệ không có mục ấy. */
		$o[] = self::o( true, array(
			'ten'  => 'Phiếu lương',
			'nhom' => VHCC_Vai::duoc( $u, 'cong_coso' ) ? 'Quản lý cửa hàng' : 'Của tôi',
			'mo'   => 'Lương tháng đã công bố',
			'man'  => 'mPhieu',
			'icon' => '🧾',
			'mau'  => 'luc',
		) );

		/* ---- 9. Nhân sự cửa hàng ----------------------------------------------------------
		   Anh Thắng 17/09/2026: *"Thêm tab Nhân Sự trong Quản Lý Cửa Hàng"*, kèm ảnh màn "Danh
		   sách nhân sự" của một app HRM.

		   ⚠️ GÁC BẰNG `ho_so_xem` — đúng quyền mà `VHCC_CuaHang::nhan_su()` đòi, không phải
		      `cong_coso` của cả tab. Hai đầu việc khác nhau: xem bảng công là một chuyện, xem
		      hồ sơ người ta là chuyện khác, và bảng vai tách chúng ra từ lâu. */
		if ( VHCC_Vai::duoc( $u, VHCC_CuaHang::QUYEN_NS ) ) {
			$o[] = self::o( true, array(
				'ten'  => 'Nhân sự',
				'nhom' => 'Quản lý cửa hàng',
				'mo'   => 'Danh sách người của cơ sở mình',
				'man'  => 'mNhanSu',
				'icon' => '👥',
				'mau'  => 'luc',
			) );
		}

		/* ---- 10b. Giờ công lương -----------------------------------------------------------
		   Anh Thắng 18/09/2026: *"Trong app chấm công online có tab Giờ Công Lương. Nhân viên sẽ
		   thấy giờ làm mình trong ngày hoặc ngày trước và tự bấm set loại giờ làm trong những
		   ngày đó và gửi cửa hàng trưởng duyệt. Cũng cho phép bật tắt"*.

		   🔴 Ô NÀY CHỈ HIỆN Ở CƠ SỞ ĐÃ BẬT. Đang thử nghiệm từng cơ sở — bày ô ở nơi chưa bật
		      là người ta bấm vào rồi gặp một màn chối, và đi hỏi vòng quanh. `self::o( false, … )`
		      vẽ ô KHOÁ, còn ở đây phải không vẽ gì cả: tính năng chưa tồn tại với họ. */
		if ( class_exists( 'VHCC_LoaiGio' ) && method_exists( 'VHCC_LoaiGio', 'hien_tab' )
			&& VHCC_LoaiGio::hien_tab( isset( $u['coso'] ) ? $u['coso'] : '',
				isset( $u['ma_nv'] ) ? $u['ma_nv'] : '' ) ) {
			$o[] = self::o( true, array(
				'ten'  => 'Giờ công lương',
				'nhom' => 'Của tôi',
				'mo'   => 'Khai việc mình làm từng ngày — gửi cửa hàng trưởng duyệt',
				'man'  => 'mGioLuong',
				'icon' => '🧾',
				'mau'  => 'luc',
			) );
		}

		/* ---- 9c. Nhắn tin ------------------------------------------------------------------
		   Anh Thắng 20/09/2026: *"Tạo tính năng mini chat trong app. Chọn thành viên cùng cửa
		   hàng và chat"*.

		   🔴 Ô NÀY KHÔNG GÁC GÌ, CỐ Ý. Ai đi làm cũng nhắn được cho người cùng chỗ mình làm —
		      đó là việc bình thường nhất trong một cửa hàng, và gác nó là bắt người ta quay về
		      Zalo, nơi công ty không thấy gì và người nghỉ việc vẫn còn trong nhóm.
		      Gác thật nằm ở `VHCC_Chat::duoc_vao()`: phòng nào cũng phải là chỗ mình đang làm. */
		/* ⚠️ NHÓM "THÔNG TIN CHUNG", KHÔNG PHẢI "CỦA TÔI" — anh Thắng 21/09/2026: *"Cho phần
		   nhắn tin xuống thông tin chung"*. Đúng: mấy ô trong "Của tôi" đều là ĐƠN TỪ của
		   riêng mình (xin bù giờ, xin nghỉ, đi trễ) — việc mình làm rồi chờ người khác duyệt.
		   Nhắn tin thì ngược lại, nó là chỗ nói chuyện với người khác, cùng loại với Nội bộ. */
		$o[] = self::o( true, array(
			'ten'  => 'Nhắn tin',
			'nhom' => 'Thông tin chung',
			'mo'   => 'Nhắn cho cả cửa hàng, hoặc riêng một người',
			'man'  => 'mChat',
			'icon' => '💬',
			'mau'  => 'lam',
		) );

		/* ---- 10. Xin bù giờ ---------------------------------------------------------------
		   Anh Thắng 18/09/2026: *"lệnh bù giờ từ nhân viên gửi lên, CHT sẽ nhận và duyệt và đẩy
		   tiếp lên cho kế toán"*. Nhân viên nào cũng gửi được — không gác gì thêm. */
		$o[] = self::o( true, array(
			'ten'  => 'Xin bù giờ',
			'nhom' => 'Của tôi',
			'mo'   => 'Quên bấm máy — xin bù giờ cho một ngày',
			'man'  => 'mXinBu',
			'icon' => '⏱️',
			'mau'  => 'xanh',
		) );

		/* ---- 9. Khai giờ khác -------------------------------------------------------------
		   Anh Thắng 18/09/2026: *"Nhân viên có quyền nhập giờ khác vào đây để cửa hàng cũng biết
		   để theo dõi"*. Nằm nhóm "Của tôi" vì đây là việc của chính người ấy, và KHÔNG gác
		   quyền gì thêm — bậc thấp nhất cũng khai được, đó là điểm của nó. */
		$o[] = self::o( true, array(
			'ten'  => 'Khai giờ khác',
			'nhom' => 'Của tôi',
			'mo'   => 'Giờ làm thêm để cửa hàng theo dõi — không tính lương',
			'man'  => 'mKhaiGio',
			'icon' => '✍️',
			'mau'  => 'cam',
		) );

		/* ---- 8. Gửi đơn xin nghỉ ----------------------------------------------------------
		   Anh Thắng 17/09/2026 khoanh đúng khối ấy: *"Chuyển này thành 1 tính năng"*. */
		$o[] = self::o( true, array(
			'ten'  => 'Xin nghỉ',
			'nhom' => 'Của tôi',
			'mo'   => 'Nghỉ phép, nghỉ ốm, việc riêng',
			'man'  => 'mXinNghi',
			'icon' => '🌴',
			'mau'  => 'tim',
		) );

		/* ---- 7. Gửi đơn đi trễ ------------------------------------------------------------
		   Anh Thắng 17/09/2026: *"Gửi đơn đi trễ là 1 tính năng"*, khoanh đúng khối ấy ở tab
		   Tôi. Cùng một lối với hai ô trên: việc thỉnh thoảng mới làm thì đừng nằm giữa một
		   trang cuộn dài. */
		$o[] = self::o( true, array(
			'ten'  => 'Gửi đơn đi trễ',
			'nhom' => 'Của tôi',
			'mo'   => 'Xin phép đi trễ một buổi',
			'man'  => 'mXinTre',
			'icon' => '⏰',
			'mau'  => 'vang',
		) );

		/* ═══════════════════════════════════════════════════════════════════════════════════
		 * 🔴 GẮN VÉ DANH TÍNH VÀO MỌI Ô DẪN SANG APP KHÁC — MỘT CHỖ DUY NHẤT
		 * ═══════════════════════════════════════════════════════════════════════════════════
		 * Anh Thắng 19/09/2026, hai ảnh cạnh nhau: trạm đang là *Trần Ngọc Minh Truyền · TUTU_TP*,
		 * bấm sang Vận Hành Chi Phí thì hiện *Nguyễn Văn Bin · FARM_PT*. *"Phải tự link chung 1
		 * tk chứ"*.
		 *
		 * Ô Ứng dụng vốn chỉ là một đường dẫn TRƠN. Sang tới nơi, app kia không biết ai vừa bấm
		 * nên lấy thẻ cũ còn sót trong máy — thẻ của người gần nhất gõ PIN trên điện thoại ấy.
		 * Hậu quả thật: người này tạo và duyệt đơn chi phí dưới danh nghĩa người kia.
		 *
		 * 🔴 GẮN Ở ĐÂY, KHÔNG GẮN Ở TỪNG Ô. Bốn ô dựng ở bốn khối cách xa nhau; thêm ô thứ năm
		 *    mà quên gắn là ô ấy lặng lẽ quay về lối cũ — và lối cũ không báo lỗi, nó chỉ hiện
		 *    sai tên. Vòng lặp này bắt mọi ô có `url`, kể cả ô sẽ thêm sau.
		 * ⚠️ Ô KHOÁ KHÔNG CÓ `url` (xem `o()`) nên không tốn vé. Ô mở màn trong trạm (`man`)
		 *    cũng vậy — nó không đi đâu cả.
		 * ═══════════════════════════════════════════════════════════════════════════════════ */
		foreach ( $o as &$o_x ) {
			if ( ! empty( $o_x['url'] ) ) { $o_x['url'] = VHCC_Ve::gan( $o_x['url'], $u ); }
		}
		unset( $o_x );

		return $o;
	}

	/**
	 * Hệ đích CÓ TRÊN SITE NÀY không — lớp có VÀ hàm có.
	 *
	 * ⚠️ Dò cả HÀM, không chỉ tên lớp. Bốn plugin cài độc lập nên bản có thể lệch; lớp CÓ mà
	 *    hàm KHÔNG là Fatal error — trắng cả trang trạm, tức mất luôn đường chấm công chỉ vì
	 *    một ô phụ. Luật của `tools/test/kiem-goi-cheo.php`.
	 */
	private static function co_lop( $lop, $ham ) {
		return class_exists( $lop ) && method_exists( $lop, $ham );
	}

	/**
	 * Dựng một ô, mở hay khoá.
	 *
	 * 🔴 KHOÁ THÌ BỎ HẲN `url`, không chỉ thêm một cờ. Giao diện dựng thẻ `<a>` khi có `url`
	 *    và thẻ `<div>` khi không — nên không có `url` nghĩa là KHÔNG CÓ CÁCH NÀO bấm được,
	 *    kể cả khi một dòng CSS sửa nhầm làm ô trông như bấm được. Một cờ boolean thì chỉ cần
	 *    một chỗ quên kiểm là ô khoá lại thành ô mở.
	 */
	private static function o( $mo_duoc, $x ) {
		$x['mo_duoc'] = (bool) $mo_duoc;
		if ( ! $x['mo_duoc'] ) {
			unset( $x['url'] );
			/* 🔴 Ô MỞ MÀN TRONG TRẠM CŨNG PHẢI MẤT ĐƯỜNG MỞ, y như ô dẫn sang app khác. Bỏ mỗi
			   `url` mà quên `man` là ô khoá trông thì mờ nhưng bấm vẫn ra màn — đúng cái lỗi
			   mà chú thích trên vừa nói là không được để xảy ra. */
			unset( $x['man'] );
			/* Dòng nhắc (như "gõ lại PIN") chỉ có nghĩa khi vào được. Giữ lại trên ô khoá là
			   bày hai câu cùng lúc, mà câu cần đọc là câu "chưa được cấp". */
			unset( $x['ghi_chu'] );
		} else {
			unset( $x['xin'] );
		}
		return $x;
	}

	/**
	 * Gác theo sổ `VHCC_Cong`, nhưng KHÔNG chết nếu sổ vắng mặt.
	 *
	 * ⚠️ Sổ trả "cho qua" với trang không khai — đó là luật của chính nó ("để SIẾT có chủ ý,
	 *    không phải để trở thành nơi duy nhất cho phép"). Giữ nguyên nghĩa ấy ở đây: thiếu sổ
	 *    thì vẫn hiện ô, vì quyền thật đã được gác lần nữa ở cửa vào của chính trang kia.
	 */
	private static function duoc_vao( $u, $khoa ) {
		if ( ! class_exists( 'VHCC_Cong' ) || ! method_exists( 'VHCC_Cong', 'duoc_vao' ) ) {
			return true;
		}
		return (bool) VHCC_Cong::duoc_vao( $u, $khoa );
	}

}
