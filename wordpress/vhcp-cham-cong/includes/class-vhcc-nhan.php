<?php
/**
 * CỔNG NHẬN CHẤM CÔNG TỪ MÁY — bản WordPress của `doPost` bên Apps Script.
 *
 * Đây là ĐƯỜNG NÓNG của cả chuỗi: mỗi lượt nhân viên bấm mặt lên đầu đọc là một lượt vào đây.
 * Sai ở đây không hiện ra ngay — nó hiện ra cuối tháng, ở bảng lương, khi không còn cách nào
 * dựng lại lượt bấm đã mất.
 *
 * =============================================================================================
 * BỐN LUẬT CỦA FIRMWARE — đọc từ chính esp32_hik_chamcong_full.ino, không phải đoán
 * =============================================================================================
 *
 * 1. FIRMWARE COI THÀNH CÔNG LÀ: `code == 200 && resp.indexOf("SUCCESS") >= 0` (dòng 880).
 *    Tức nó tìm CHUỖI CON "SUCCESS" ở bất kỳ đâu trong thân trả về. Khác đi là nó thử lại 3 lần
 *    rồi BỎ LUỘT BẤM ĐÓ.
 *    Nên: gói rác, gói thử đường truyền, giờ sai khuôn, máy chưa gán cửa hàng — tất cả đều phải
 *    trả SUCCESS. Không phải vì chúng thành công, mà vì bắt firmware thử lại một gói KHÔNG BAO
 *    GIỜ hợp lệ là đẩy lại vô hạn. Chỉ ĐÚNG MỘT ca được trả khác SUCCESS: máy chủ hỏng thật
 *    (mất kết nối cơ sở dữ liệu) — lúc đó thử lại là ĐÚNG, vì lượt bấm hợp lệ mà chưa ghi được.
 *
 * 2. FIRMWARE KHÔNG THEO CHUYỂN HƯỚNG (`HTTPC_DISABLE_FOLLOW_REDIRECTS`, dòng 856). Gặp
 *    301/302/307 nó lấy `Location` rồi gọi lại bằng **GET** (dòng 864-871) — tức MẤT TRỌN thân
 *    POST, mất luôn lượt bấm, mà vẫn có thể thấy chữ "SUCCESS" trong trang WordPress trả về rồi
 *    tưởng là xong.
 *    Nên cổng này phải KHÔNG BAO GIỜ bị chuyển hướng: xem `chan_chuyen_huong()` dưới.
 *    Đây là cái bẫy riêng của WordPress mà Apps Script không có.
 *
 * 3. ĐƯỜNG 4G GỬI KHÔNG KÈM ẢNH (`"image":""`, dòng 840-845) để né giới hạn AT+HTTPDATA.
 *    Nên "không có ảnh" là chuyện BÌNH THƯỜNG, không phải lỗi — không được vì thiếu ảnh mà bỏ giờ.
 *
 * 4. GÓI THỬ ĐƯỜNG TRUYỀN: mỗi lần 4G nối lại (tức mỗi lần bật máy) firmware đẩy một gói
 *    `employeeNo:"TEST4G"`, `time:"test"` vào ĐÚNG đường ghi chấm công. Bên Sheet nó từng tạo
 *    ra một khối tháng tên "test" trong sheet tiền lương. Chặn ở MÁY CHỦ chứ không chỉ ở
 *    firmware, vì sửa firmware phải OTA từng máy còn máy chủ sửa một lần là mọi máy sạch ngay.
 *
 * =============================================================================================
 * KHÁC APPS SCRIPT MỘT CHỖ CÓ CHỦ Ý: CỔNG NÀY ĐÒI KHOÁ
 * =============================================================================================
 * `/exec` của Apps Script mở ẩn danh (buộc phải vậy, vì máy gọi không đăng nhập được). Nghĩa là
 * ai có link là ghi được chấm công cho bất kỳ ai, bất kỳ ngày nào. Ở WordPress em không phải
 * chịu chuyện đó: cổng này đòi một khoá dùng chung.
 *   · Giai đoạn GHI SONG SONG: người gọi là Apps Script (máy chủ tới máy chủ) — khoá nằm trong
 *     Script Property, không xuống firmware, không xuống trình duyệt.
 *   · Giai đoạn ĐÃ CHUYỂN: firmware mang khoá, nạp cùng lượt OTA vốn đã phải làm để trỏ máy về
 *     WordPress. Không phát sinh thêm một lượt OTA nào.
 * Khoá đặt trong `wp-config.php` (`VHCC_KHOA_MAY`), KHÔNG đặt trong bảng `cai_dat` — bảng đó
 * app đọc được, mà app thì có màn hình.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_Nhan {

	/** Đường của máy. Cố định, không có dấu gạch chéo cuối — xem chan_chuyen_huong(). */
	const DUONG = 'cham-cong-may';

	public static function init() {
		add_rewrite_rule( '^' . self::DUONG . '/?$', 'index.php?vhcc_nhan=1', 'top' );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'parse_request', array( __CLASS__, 'chan_chuyen_huong' ), 0 );
		add_action( 'template_redirect', array( __CLASS__, 'phuc_vu' ), 0 );
	}

	public static function query_vars( $v ) { $v[] = 'vhcc_nhan'; return $v; }

	private static function la_duong_may() {
		$d = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
		$d = trim( (string) parse_url( $d, PHP_URL_PATH ), '/' );
		return $d === self::DUONG || substr( $d, - ( strlen( self::DUONG ) + 1 ) ) === '/' . self::DUONG;
	}

	/**
	 * Tắt MỌI chuyển hướng trên đường của máy.
	 *
	 * WordPress tự chuyển hướng nhiều chỗ mà bình thường là tiện: thêm dấu gạch chéo cuối, đổi
	 * về tên miền chuẩn trong Cài đặt, đổi http -> https. Với trình duyệt thì vô hại. Với firmware
	 * thì MẤT LƯỢT BẤM: nó gọi lại bằng GET nên thân POST bay mất, và trang WordPress trả về có
	 * thể tình cờ chứa chữ "SUCCESS" -> firmware báo "ĐỒNG BỘ THÀNH CÔNG" trong khi không có gì
	 * được ghi. Hỏng kiểu này IM LẶNG, đúng loại tệ nhất.
	 *
	 * `redirect_canonical` trả về false là bỏ chuyển hướng. Chặn ở `parse_request` ưu tiên 0 để
	 * chắc chắn gài xong trước khi bất cứ ai kịp chuyển hướng.
	 */
	public static function chan_chuyen_huong() {
		if ( ! self::la_duong_may() ) { return; }
		add_filter( 'redirect_canonical', '__return_false', 99 );
		remove_action( 'template_redirect', 'redirect_canonical' );
		add_filter( 'wp_redirect', array( __CLASS__, 'khong_chuyen_huong' ), 99, 2 );
	}

	/** Có ai đó vẫn cố chuyển hướng -> huỷ, và ghi lại để không im lặng. */
	public static function khong_chuyen_huong( $dich, $tt ) {
		self::ghi_loi( 'CHUYEN_HUONG', 'có nơi cố chuyển hướng đường của máy sang ' . $dich . ' (' . $tt . ')' );
		return false;
	}

	/** Trả JSON rồi dừng. `status` để đầu để chữ SUCCESS chắc chắn nằm trong thân. */
	private static function tra( $ma, $tt ) {
		if ( ! headers_sent() ) {
			status_header( $ma );
			nocache_headers();
			header( 'Content-Type: application/json; charset=utf-8' );
		}
		echo wp_json_encode( $tt );
		if ( ! defined( 'VHCC_TEST' ) ) { exit; }
	}

	/** SUCCESS = "đừng đẩy lại gói này nữa", KHÔNG phải "đã ghi". Xem luật 1 ở đầu tệp. */
	private static function xong( $them = array() ) {
		self::tra( 200, array_merge( array( 'status' => 'SUCCESS' ), $them ) );
	}

	/** Chỉ dùng khi MÁY CHỦ hỏng: lượt bấm hợp lệ mà chưa ghi được -> firmware PHẢI thử lại. */
	private static function loi( $vi_sao, $ma = 500 ) {
		self::tra( $ma, array( 'status' => 'ERROR', 'message' => $vi_sao ) );
	}

	public static function phuc_vu() {
		if ( ! get_query_var( 'vhcc_nhan' ) && ! self::la_duong_may() ) { return; }

		if ( 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) ) {
			/* GET vào đây gần như luôn là dấu hiệu của luật 2: firmware bị chuyển hướng rồi gọi
			   lại bằng GET, thân POST đã mất. Trả 405 và KHÔNG có chữ SUCCESS trong thân, để
			   firmware biết là thất bại và thử lại — thay vì đọc được "SUCCESS" ở đâu đó rồi
			   tưởng đã ghi. Ghi lại luôn vì đây là triệu chứng cần thấy. */
			self::ghi_loi( 'GET_VAO_CONG_MAY', 'có lượt GET vào cổng máy — coi như dấu hiệu bị chuyển hướng' );
			self::tra( 405, array( 'status' => 'ERROR', 'message' => 'Cong nay chi nhan POST.' ) );
			return;
		}

		$tho = self::than_yeu_cau();

		/* ⚠️ THÂN BỊ CẮT KHÁC HẲN THÂN HỎNG — và trộn hai ca này lại là BỎ IM LẶNG lượt bấm thật.
		   Ảnh mặt base64 có thể vài trăm KB. Vượt `post_max_size` thì PHP không báo lỗi gì cả: nó
		   giao cho ta một thân NGẮN HƠN `Content-Length`, JSON hỏng — trông y như gói rác. Bỏ nó
		   như bỏ gói rác là mất một lượt chấm công thật vì một dòng cấu hình PHP, không ai thấy.
		   Nên tách riêng: so `Content-Length` với độ dài thật rồi trả ERROR. Đẩy lại trên cùng
		   đường truyền thì vẫn cắt y vậy — nhưng ERROR để firmware ghi "thất bại" vào sổ của nó
		   và người ta còn đọc được nhật ký, thay vì cổng lặng lẽ báo SUCCESS cho một lượt đã mất.
		   (Đường 4G gửi KHÔNG kèm ảnh nên gói nhỏ — ca này chỉ xảy ra trên đường WiFi.) */
		$dai_khai = isset( $_SERVER['CONTENT_LENGTH'] ) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
		if ( $dai_khai > 0 && strlen( $tho ) < $dai_khai ) {
			self::ghi_loi( 'THAN_BI_CAT', 'Content-Length khai ' . $dai_khai . ' byte, nhận được '
				. strlen( $tho ) . ' byte — gần như chắc là post_max_size / upload_max_filesize quá nhỏ '
				. 'cho ảnh mặt. MẤT lượt bấm này. Nâng post_max_size trong cài đặt PHP của hosting.' );
			self::loi( 'Than yeu cau bi cat (post_max_size?) - MAT luot bam nay.', 413 );
			return;
		}

		$d = json_decode( $tho, true );
		if ( ! is_array( $d ) ) {
			/* Thân ĐỦ mà vẫn không phải JSON -> gói rác thật; đẩy lại bao nhiêu lần cũng hỏng y
			   vậy nên BỎ, đừng bắt đẩy lại vô hạn. Vẫn ghi lại để không im lặng. */
			self::ghi_loi( 'JSON_HONG', 'thân yêu cầu đủ độ dài nhưng không phải JSON ('
				. strlen( $tho ) . ' byte)' );
			self::xong( array( 'boQua' => true, 'note' => 'Than yeu cau khong phai JSON -> bo qua.' ) );
			return;
		}

		if ( ! self::khoa_dung( $d ) ) {
			/* Sai khoá KHÔNG được trả SUCCESS: người gọi hợp lệ mà cấu hình thiếu khoá thì phải
			   thấy hỏng ngay, chứ không phải im lặng mất chấm công cả cơ sở. 401 để phân biệt với
			   máy chủ hỏng. */
			self::loi( 'Sai khoa hoac chua cau hinh VHCC_KHOA_MAY.', 401 );
			return;
		}

		/* --- VIỆC KHÁC CỦA MÁY: nhịp sống, lấy lệnh, báo xong, OTA, roster… -------------------
		   Từ bản 2.0.0 những việc này chạy thẳng trên host thay vì qua Firebase. Chúng đi CHUNG
		   đường và CHUNG khoá với chấm công — xem đầu class-vhcc-may-cong.php để biết vì sao
		   không mở đường riêng.
		   Đặt SAU khối kiểm khoá và TRƯỚC phần đọc lượt bấm: gói có `viec` KHÔNG phải lượt chấm
		   công, để nó chạy tiếp xuống dưới là sinh ra cảnh "GIO_SAI_KHUON" đầy nhật ký. */
		if ( isset( $d['viec'] ) && '' !== trim( (string) $d['viec'] ) ) {
			$kq = VHCC_MayCong::phuc_vu( $d['viec'], $d );
			self::xong( is_array( $kq ) ? $kq : array() );
			return;
		}

		$ma_nv   = isset( $d['employeeNo'] ) ? trim( (string) $d['employeeNo'] ) : '';
		$ho_ten  = isset( $d['name'] ) ? trim( (string) $d['name'] ) : '';
		$luc     = isset( $d['time'] ) ? trim( (string) $d['time'] ) : '';
		$anh     = isset( $d['image'] ) ? (string) $d['image'] : '';
		$serial  = isset( $d['hikSerial'] ) ? trim( (string) $d['hikSerial'] ) : '';
		$mac     = isset( $d['macAddress'] ) ? trim( (string) $d['macAddress'] ) : '';
		$model   = isset( $d['hikModel'] ) ? trim( (string) $d['hikModel'] ) : '';
		$tu_khai = isset( $d['stationName'] ) ? trim( (string) $d['stationName'] ) : '';

		/* --- Luật 4: gói thử đường truyền. Kiểm cả cờ `selftest` lẫn mã TEST4G, y như Code.gs. */
		if ( ( isset( $d['selftest'] ) && true === $d['selftest'] ) || 'TEST4G' === strtoupper( $ma_nv ) ) {
			self::ghi_loi( 'GOI_THU_DUONG', 'máy ' . ( $tu_khai ? $tu_khai : $mac ) . ' đẩy gói thử đường truyền' );
			self::xong( array( 'boQua' => true, 'note' => 'Goi THU DUONG TRUYEN -> khong ghi cham cong.' ) );
			return;
		}

		/* --- Khuôn ngày giờ. Kiểm KHUÔN chứ không chỉ chặn đúng chữ "test": chặn theo tên là lần
		   sau ai đổi chữ trong gói thử là lọt tiếp. Chỉ nhận 'yyyy-MM-dd HH:mm(:ss)'. */
		$phan  = preg_split( '/\s+/', $luc );
		$ngay  = isset( $phan[0] ) ? $phan[0] : '';
		$gio   = isset( $phan[1] ) ? $phan[1] : '';
		$giay  = VHCC_DB::giay( $gio );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $ngay ) || null === $giay || ! self::ngay_that( $ngay ) ) {
			self::ghi_loi( 'GIO_SAI_KHUON', 'máy ' . ( $tu_khai ? $tu_khai : $mac ) . ' gửi time="' . $luc
				. '" (NV ' . $ma_nv . ') -> bỏ qua' );
			self::xong( array( 'boQua' => true, 'note' => 'time="' . $luc . '" khong dung khuon -> bo qua.' ) );
			return;
		}
		if ( '' === $ma_nv ) {
			self::ghi_loi( 'THIEU_MA_NV', 'gói không có employeeNo (máy ' . ( $tu_khai ? $tu_khai : $mac ) . ')' );
			self::xong( array( 'boQua' => true, 'note' => 'Thieu employeeNo -> bo qua.' ) );
			return;
		}

		/* --- Cơ sở lấy theo MÃ THIẾT BỊ, KHÔNG tin tên máy tự khai. --- */
		$gm = self::giai_ma_tram( $serial, $mac, $tu_khai, $model );
		if ( $gm['choGan'] ) {
			/* Máy chưa gán cơ sở -> giữ tạm, TUYỆT ĐỐI không tạo cơ sở mới từ lời khai của máy.
			   Bỏ lượt bấm này là mất công của người thật chỉ vì cái máy chưa được khai. */
			$luu = self::luu_cho_gan( $serial, $mac, $tu_khai, $ma_nv, $ho_ten, $luc, strlen( $anh ) > 100 );
			self::xong( array( 'choGan' => true, 'luu' => $luu,
				'note' => 'May chua gan co so - da giu tam, vao web gan co so cho may nay.' ) );
			return;
		}
		$coso = $gm['station'];

		$kq = self::ghi_gio( $coso, $ngay, $ma_nv, $ho_ten, $giay, $anh );
		if ( isset( $kq['loi'] ) ) {
			/* Cơ sở dữ liệu hỏng — ĐÂY là ca duy nhất phải để firmware thử lại. */
			self::ghi_loi( 'GHI_HONG', $kq['loi'] );
			self::loi( 'Khong ghi duoc: ' . $kq['loi'] );
			return;
		}
		self::xong( array( 'loai' => $kq['loai'], 'coSo' => $coso, 'img' => $kq['anh'] ) );
	}

	/**
	 * Chính `_ghiGioVaoRa` của Code.gs, dịch nguyên luật.
	 *
	 * Ô giờ vào / giờ ra là CẶP [sớm nhất, muộn nhất] của ngày, và chỉ được NỚI RỘNG, KHÔNG BAO
	 * GIỜ THU HẸP. Nhờ vậy nạp lại cả tháng theo thứ tự nào, đứt ở đâu, chạy lại bao nhiêu lần
	 * cũng ra một kết quả. Đây là thứ làm cho bước GHI SONG SONG hai nơi rồi đối số hàng có nghĩa:
	 * hai bên nhận cùng một tập lượt bấm thì phải ra cùng một cặp giờ, bất kể thứ tự đến.
	 *
	 * Bốn nhánh, đúng như bản gốc:
	 *   trùng     — lượt đã có ở ô vào hoặc ô ra -> không đụng gì
	 *   vào       — chưa có giờ vào -> đây là giờ vào
	 *   giữa      — nằm trong khoảng đã phủ -> không đụng gì (KHÔNG thu hẹp giờ ra)
	 *   ra        — muộn hơn khoảng -> nới giờ ra
	 *   đảoThứTự  — sớm hơn giờ vào -> thành giờ vào mới; giờ vào CŨ chỉ tụt xuống làm giờ ra khi
	 *               ô giờ ra còn TRỐNG (đã có giờ ra muộn hơn thì giữ nguyên, vì "muộn nhất trong
	 *               ngày" mới đúng nghĩa ô đó).
	 */
	/**
	 * QUYẾT ĐỊNH thuần: cặp giờ đang có + một lượt mới -> nhánh nào, cặp mới là gì.
	 *
	 * Tách riêng khỏi phần ghi cơ sở dữ liệu vì đây là chỗ duy nhất quyết định TIỀN, nên nó phải
	 * thử được trực tiếp bằng con số, không cần bảng, không cần HTTP.
	 *
	 * Dùng CHUNG cho cả đường máy và đường chấm công online, kể cả hàng ca đêm. Bên Apps Script
	 * ca đêm phải có hàm ghi RIÊNG (`_ghiGioDem`) vì ô sheet giữ chuỗi 'HH:mm:ss' nên 06:00 luôn
	 * "sớm hơn" 22:00 -> ca đêm bị đảo thành 16 tiếng ban ngày. Ở đây giờ là SỐ GIÂY, nên chỉ cần
	 * trải phẳng trục (giờ sau nửa đêm + 86400) TRƯỚC khi vào hàm này là cùng một luật chạy đúng
	 * cho cả hai. Một luật thay vì hai — chính điều Code.gs tự cảnh báo: hai bản tính giờ lệch
	 * nhau là lệch tiền lương.
	 */
	public static function quyet_dinh_gio( $vao, $ra, $moi ) {
		if ( $moi === $vao || $moi === $ra ) { return array( 'loai' => 'trung' ); }
		if ( null === $vao ) {
			return array( 'loai' => 'vao', 'vao' => $moi, 'anh_vao' => true );
		}
		if ( $moi >= $vao ) {
			// Nằm trong khoảng đã phủ -> KHÔNG thu hẹp giờ ra.
			if ( null !== $ra && $moi < $ra ) { return array( 'loai' => 'giua' ); }
			return array( 'loai' => 'ra', 'ra' => $moi, 'anh_ra' => true );
		}
		/* Sớm hơn giờ vào -> thành giờ vào mới. Giờ vào CŨ chỉ tụt xuống làm giờ ra khi ô giờ ra
		   còn TRỐNG: đã có giờ ra muộn hơn thì giữ nguyên, vì "muộn nhất trong ngày" mới đúng
		   nghĩa ô đó. Ghi đè vô điều kiện là ca làm mất giờ ra thật (22:05) khi lượt sớm nhất
		   tới sau cùng — rất hay gặp lúc nạp lại vì đầu đọc trả trang không theo thứ tự. */
		$kq = array( 'loai' => 'daoThuTu', 'vao' => $moi, 'anh_vao' => true );
		if ( null === $ra ) { $kq['ra'] = $vao; $kq['chuyen_anh_vao_sang_ra'] = true; }
		return $kq;
	}

	/**
	 * Ghi một lượt vào bảng chấm công.
	 *
	 * `$giay` phải là giờ ĐÃ trải phẳng nếu đây là hàng ca đêm — nơi gọi lo việc đó, xem
	 * VHCC_Online::trai_phang(). `$ma_nv` nhận cả mã có hậu tố ('NV001-CD').
	 */
	public static function ghi_gio( $coso, $ngay, $ma_nv, $ho_ten, $giay, $anh_b64, $nguon = 'may', $ghi_chu = null ) {
		global $wpdb;
		$bang = VHCC_DB::t( 'cham_cong' );
		list( $ma_goc, $hau_to ) = self::tach_hau_to( $ma_nv );

		$cu = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $bang WHERE coso=%s AND ngay=%s AND ma_nv=%s AND hau_to=%s",
			$coso, $ngay, $ma_goc, $hau_to ), ARRAY_A );

		$vao = ( $cu && null !== $cu['gio_vao_giay'] && '' !== $cu['gio_vao_giay'] ) ? (int) $cu['gio_vao_giay'] : null;
		$ra  = ( $cu && null !== $cu['gio_ra_giay'] && '' !== $cu['gio_ra_giay'] ) ? (int) $cu['gio_ra_giay'] : null;

		$qd = self::quyet_dinh_gio( $vao, $ra, $giay );
		if ( 'trung' === $qd['loai'] || 'giua' === $qd['loai'] ) {
			return array( 'loai' => $qd['loai'], 'anh' => 'khong-doi' );
		}

		$anh_moi = '';
		$ghi_anh = strlen( $anh_b64 ) > 100;
		if ( $ghi_anh ) {
			$anh_moi = self::luu_anh( $coso, $ngay, $ma_nv, $giay, $anh_b64 );
			/* Lưu ảnh trượt -> VẪN GHI GIỜ, chỉ mất ảnh. Giờ là tiền, ảnh là bằng chứng phụ. */
			if ( '' === $anh_moi ) { $ghi_anh = false; }
		}

		$loai = $qd['loai'];
		$dat  = array();
		if ( array_key_exists( 'vao', $qd ) ) { $dat['gio_vao_giay'] = $qd['vao']; }
		if ( array_key_exists( 'ra', $qd ) ) { $dat['gio_ra_giay'] = $qd['ra']; }
		if ( $ghi_anh && ! empty( $qd['anh_vao'] ) ) { $dat['anh_vao'] = $anh_moi; }
		if ( $ghi_anh && ! empty( $qd['anh_ra'] ) ) { $dat['anh_ra'] = $anh_moi; }
		if ( ! empty( $qd['chuyen_anh_vao_sang_ra'] ) ) { $dat['anh_ra'] = $cu ? (string) $cu['anh_vao'] : ''; }

		/* Ô "Thời gian trong ngày" của sheet: 'HH:mm' hoặc 'HH:mm HH:mm'. Tính lại từ cặp SAU khi
		   đã đặt, chứ không chắp từ nhánh — chắp từ nhánh là chỗ dễ lệch nhất với bản gốc. */
		$vao_moi = array_key_exists( 'gio_vao_giay', $dat ) ? $dat['gio_vao_giay'] : $vao;
		$ra_moi  = array_key_exists( 'gio_ra_giay', $dat ) ? $dat['gio_ra_giay'] : $ra;
		$dat['chuan'] = ( null === $ra_moi )
			? VHCC_DB::hhmm( $vao_moi )
			: VHCC_DB::hhmm( $vao_moi ) . ' ' . VHCC_DB::hhmm( $ra_moi );

		if ( null !== $ghi_chu && '' !== $ghi_chu ) { $dat['ghi_chu'] = $ghi_chu; }
		if ( $cu ) {
			/* Hàng đã có thì `nguon` chỉ được NỚI, không được ghi đè: một ngày có thể vừa có lượt
			   máy vừa có lượt online, và `nguon` chính là thứ phép đối số hàng dùng để chỉ đếm
			   lượt của MÁY. Ghi đè thành cái đến sau là mất dấu, rồi phép đối chiếu báo lệch mà
			   không ai biết lệch vì đâu. */
			if ( trim( (string) $cu['nguon'] ) !== '' && trim( (string) $cu['nguon'] ) !== $nguon ) {
				$dat['nguon'] = 'hon-hop';
			}
			$ok = $wpdb->update( $bang, $dat, array( 'id' => (int) $cu['id'] ) );
		} else {
			$dat['coso']   = $coso;
			$dat['ngay']   = $ngay;
			$dat['ma_nv']  = $ma_goc;
			$dat['hau_to'] = $hau_to;
			$dat['ho_ten'] = $ho_ten;
			$dat['nguon']  = $nguon;
			$dat['ghi_luc'] = current_time( 'mysql' );
			$ok = $wpdb->insert( $bang, $dat );
		}
		if ( false === $ok ) { return array( 'loi' => 'MySQL: ' . $wpdb->last_error ); }

		return array( 'loai' => $loai, 'anh' => $ghi_anh ? ( 'ok:' . $anh_moi ) : ( strlen( $anh_b64 ) > 100 ? 'ERR' : 'no-img' ) );
	}

	/**
	 * Tách hậu tố nhiệm vụ / ca khỏi mã — bản dịch `_tachMaNhiemVu`.
	 * -TT Thu Tiền · -TG Trực Ghế · -CD tăng ca/ca đêm · -CT công tối (cũ) · -TC tăng cường.
	 * KHÔNG cắt hậu tố lạ: mã `NV-XX` là mã thật tên vậy, cắt bừa là gộp công hai người.
	 */
	public static function tach_hau_to( $ma ) {
		$ma = trim( (string) $ma );
		if ( preg_match( '/^(.*?)-(TT|TG|CD|CT|TC)$/i', $ma, $m ) ) {
			return array( trim( $m[1] ), strtoupper( $m[2] ) );
		}
		return array( $ma, '' );
	}

	/**
	 * Máy -> cơ sở. Bản dịch `_giaiMaTram`: tra theo SERIAL trước, rồi MAC.
	 * Chỉ nhận lời khai của máy khi cơ sở đó ĐÃ CÓ THẬT; không bao giờ tạo cơ sở mới từ lời khai.
	 */
	public static function giai_ma_tram( $serial, $mac, $tu_khai, $model ) {
		$tu_khai = trim( preg_replace( '/^CS_/', '', (string) $tu_khai ) );
		$m       = self::ghi_nhan_may( $serial, $mac, $tu_khai, $model );
		$gan     = $m ? trim( preg_replace( '/^CS_/', '', (string) $m['cua_hang'] ) ) : '';
		if ( '' !== $gan ) {
			return array( 'station' => $gan, 'nguon' => 'bang',
				'lech'  => ( '' !== $tu_khai && strtolower( $tu_khai ) !== strtolower( $gan ) ),
				'choGan' => false );
		}
		if ( '' !== $tu_khai && self::coso_co_that( $tu_khai ) ) {
			return array( 'station' => $tu_khai, 'nguon' => 'tu-khai', 'lech' => false, 'choGan' => false );
		}
		return array( 'station' => '', 'nguon' => 'tu-khai', 'lech' => false, 'choGan' => true );
	}

	/** Cơ sở "có thật" = đã có trong bảng máy hoặc đã có lượt chấm công. Không tự tạo bao giờ. */
	private static function coso_co_that( $coso ) {
		global $wpdb;
		$a = $wpdb->get_var( $wpdb->prepare(
			'SELECT 1 FROM ' . VHCC_DB::t( 'may' ) . ' WHERE LOWER(cua_hang)=LOWER(%s) LIMIT 1', $coso ) );
		if ( $a ) { return true; }
		$b = $wpdb->get_var( $wpdb->prepare(
			'SELECT 1 FROM ' . VHCC_DB::t( 'cham_cong' ) . ' WHERE LOWER(coso)=LOWER(%s) LIMIT 1', $coso ) );
		return (bool) $b;
	}

	/**
	 * Ghi nhận máy. Bản dịch `_ghiNhanMay`, giữ nguyên chỗ QUAN TRỌNG NHẤT:
	 * phần cứng đổi thì CHỈ GHI DẤU, KHÔNG tự sửa và KHÔNG bao giờ ghi đè cơ sở đã gán.
	 * Vì "thay bo ESP32" và "mang bo sang cửa hàng khác" nhìn từ máy chủ giống hệt nhau — firmware
	 * nhớ serial trong NVS nên bo mang đi vẫn khai serial cũ. Đoán sai là chấm công cửa hàng mới
	 * chảy vào cơ sở cũ: sai người, sai lương, không ai thấy.
	 */
	private static function ghi_nhan_may( $serial, $mac, $tu_khai, $model ) {
		global $wpdb;
		$bang = VHCC_DB::t( 'may' );
		$m = null;
		if ( '' !== $serial ) {
			$m = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $bang WHERE LOWER(serial)=LOWER(%s) LIMIT 1", $serial ), ARRAY_A );
		}
		if ( ! $m && '' !== $mac ) {
			$m = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $bang WHERE LOWER(mac)=LOWER(%s) LIMIT 1", $mac ), ARRAY_A );
		}
		if ( ! $m ) {
			$wpdb->insert( $bang, array(
				'serial' => $serial, 'mac' => $mac, 'cua_hang' => '', 'model' => $model,
				'ten_tu_khai' => $tu_khai, 'lan_cuoi_thay' => current_time( 'mysql' ),
				'ghi_chu' => 'máy mới — CHƯA GÁN cơ sở',
			) );
			return null;
		}

		$dat = array( 'ten_tu_khai' => $tu_khai, 'lan_cuoi_thay' => current_time( 'mysql' ) );
		// Bổ sung ô còn TRỐNG thì được; ô đã có giá trị KHÁC thì chỉ ghi dấu.
		if ( '' === trim( (string) $m['serial'] ) && '' !== $serial ) { $dat['serial'] = $serial; }
		if ( '' === trim( (string) $m['mac'] ) && '' !== $mac ) { $dat['mac'] = $mac; }
		if ( '' === trim( (string) $m['model'] ) && '' !== $model ) { $dat['model'] = $model; }

		$dau = array();
		if ( '' !== $serial && '' !== trim( (string) $m['serial'] )
			&& strtolower( trim( $m['serial'] ) ) !== strtolower( $serial ) ) {
			$dau[] = '⚠️ SERIAL ĐẦU ĐỌC ĐỔI: ' . $m['serial'] . ' -> ' . $serial . ' lúc ' . current_time( 'mysql' )
				. ' — kiểm xem có phải bo bị mang sang cơ sở khác. CHƯA tự sửa.';
			self::ghi_loi( 'SERIAL_DOI', 'MAC ' . $mac . ': ' . $m['serial'] . ' -> ' . $serial );
		}
		if ( '' !== $mac && '' !== trim( (string) $m['mac'] )
			&& strtolower( trim( $m['mac'] ) ) !== strtolower( $mac ) ) {
			$dau[] = '⚠️ MAC BO ĐỔI: ' . $m['mac'] . ' -> ' . $mac . ' lúc ' . current_time( 'mysql' )
				. ' — thay bo thì cập nhật tay. CHƯA tự sửa.';
			self::ghi_loi( 'MAC_DOI', 'serial ' . $serial . ': ' . $m['mac'] . ' -> ' . $mac );
		}
		if ( $dau ) { $dat['ghi_chu'] = implode( ' | ', $dau ); }

		$wpdb->update( $bang, $dat, array( 'id' => (int) $m['id'] ) );
		return array_merge( $m, $dat );
	}

	/** Lượt bấm của máy chưa gán cơ sở. Giữ nguyên lời khai của máy, không suy diễn gì. */
	private static function luu_cho_gan( $serial, $mac, $tu_khai, $ma_nv, $ho_ten, $luc, $co_anh ) {
		global $wpdb;
		$ok = $wpdb->insert( VHCC_DB::t( 'cho_gan' ), array(
			'nhan_luc' => current_time( 'mysql' ), 'serial' => $serial, 'mac' => $mac,
			'ten_tu_khai' => $tu_khai, 'ma_nv' => $ma_nv, 'ho_ten' => $ho_ten,
			'thoi_diem' => $luc, 'co_anh' => $co_anh ? 1 : 0, 'da_chuyen' => '',
		) );
		return false === $ok ? 'loi' : 'da-giu';
	}

	/**
	 * Ảnh chấm công. Bản gốc đẩy lên Drive; ở đây ghi vào thư mục tải lên của WordPress theo
	 * đúng cây `<cơ sở>/Tháng MM-yyyy/` cho khớp bản gốc, và trả về đường dẫn tương đối.
	 * Trượt thì trả rỗng — nơi gọi VẪN GHI GIỜ.
	 */
	private static function luu_anh( $coso, $ngay, $ma_nv, $giay, $b64 ) {
		$nhi = base64_decode( $b64, true );
		if ( false === $nhi || strlen( $nhi ) < 100 ) { return ''; }
		$u = wp_upload_dir();
		if ( ! empty( $u['error'] ) ) { return ''; }
		$thang = 'Tháng ' . substr( $ngay, 5, 2 ) . '-' . substr( $ngay, 0, 4 );
		$tuong = 'vhcc-anh/' . sanitize_file_name( $coso ) . '/' . sanitize_file_name( $thang );
		$thu   = $u['basedir'] . '/' . $tuong;
		if ( ! wp_mkdir_p( $thu ) ) { return ''; }
		$ten = sanitize_file_name( $ma_nv . '_' . $ngay . '_' . str_replace( ':', '-', VHCC_DB::hhmmss( $giay ) ) . '.jpg' );
		return ( false === file_put_contents( $thu . '/' . $ten, $nhi ) ) ? '' : ( $tuong . '/' . $ten );
	}

	/** Ngày có thật (chặn 2026-02-31, 2026-13-01). Khuôn đúng mà ngày không có là vẫn phải bỏ. */
	private static function ngay_that( $ngay ) {
		list( $y, $m, $d ) = array_map( 'intval', explode( '-', $ngay ) );
		return checkdate( $m, $d, $y );
	}

	private static function than_yeu_cau() {
		if ( defined( 'VHCC_TEST' ) && isset( $GLOBALS['VHCC_THAN'] ) ) { return (string) $GLOBALS['VHCC_THAN']; }
		$t = file_get_contents( 'php://input' );
		return false === $t ? '' : $t;
	}

	/** So khoá bằng hash_equals — so bằng `===` là rò rỉ độ dài khớp qua thời gian đáp. */
	private static function khoa_dung( $d ) {
		$that = defined( 'VHCC_KHOA_MAY' ) ? (string) VHCC_KHOA_MAY : '';
		if ( '' === $that ) { return false; }          // chưa cấu hình = đóng, không phải mở
		$gui = '';
		if ( isset( $_SERVER['HTTP_X_VHCC_KEY'] ) ) { $gui = (string) $_SERVER['HTTP_X_VHCC_KEY']; }
		elseif ( isset( $d['key'] ) ) { $gui = (string) $d['key']; }
		return '' !== $gui && hash_equals( $that, $gui );
	}

	/** Nhật ký sự cố của cổng. Bản dịch `_fbGhiLoi` — ghi để đọc được, không để im lặng. */
	private static function ghi_loi( $ma, $loi ) {
		$ds = get_option( 'vhcc_nhat_ky_may', array() );
		if ( ! is_array( $ds ) ) { $ds = array(); }
		array_unshift( $ds, array( 'luc' => current_time( 'mysql' ), 'ma' => $ma, 'loi' => $loi ) );
		update_option( 'vhcc_nhat_ky_may', array_slice( $ds, 0, 200 ), false );
	}
}
