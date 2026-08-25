<?php
/**
 * ĐỐI CHIẾU KHUÔN MẶT CỦA ẢNH CHẤM CÔNG ONLINE.
 *
 * =============================================================================================
 * 🔴 NÓ GẮN CỜ, NÓ KHÔNG GÁC CỬA
 * =============================================================================================
 * Anh Thắng 25/08/2026 chọn phương án B: *"so mặt ở máy chủ, chỉ gắn cờ nghi ngờ, không chặn
 * ai"*. Đây là chỗ quyết định toàn bộ thiết kế của tệp này, nên nói cho rõ:
 *
 * Nhận diện khuôn mặt sai theo HAI HƯỚNG, và cả hai đều tệ:
 *   · CHỐI NHẦM người thật — nhân viên đứng đó không chấm công được, phải gọi quản lý. Ảnh
 *     selfie ngược sáng, đeo khẩu trang, mới cắt tóc là đủ để lệch.
 *   · NHẬN NHẦM người khác — chấm công hộ trót lọt, đúng cái mà chụp ảnh sinh ra để chặn.
 *
 * Nếu đem nó ra gác cửa thì hôm nào máy nhận sai là cả cơ sở tắc, và người bị chối không có
 * đường nào tự sửa. Còn gắn cờ thì sai sót thành MỘT DÒNG cần xem lại, không thành một người
 * mất công. Cùng loại cờ mà quản lý đang dùng, cùng màn xử lý.
 *
 * ⇒ `soi()` KHÔNG BAO GIỜ trả về "chặn". Nó luôn để lượt chấm đi qua, và chỉ ghi lại nghi ngờ.
 *
 * =============================================================================================
 * MẪU LẤY TỪ CHÍNH TẤM ẢNH CHẤM CÔNG ĐẦU TIÊN
 * =============================================================================================
 * Anh Thắng: *"được, lấy tấm ảnh đầu tiên làm mặt"*. Cách này tránh phải chụp lại 240 người —
 * thứ mà thực tế sẽ không bao giờ làm xong.
 *
 * ⚠️ NHƯNG MẪU ĐẦU TIÊN CHƯA ĐƯỢC TIN NGAY. Nếu ngày đầu chính là ngày có người chấm hộ, thì
 *    mẫu ghi lại khuôn mặt của người chấm hộ, và từ đó về sau hệ thống gắn cờ ngược: người
 *    thật bị coi là giả. Nên mẫu vào trạng thái `cho` (chờ duyệt) — quản lý mở màn Hồ sơ nhìn
 *    một lượt rồi duyệt. Trong lúc chờ duyệt, mẫu vẫn dùng để so, nhưng cờ ghi rõ là mẫu chưa
 *    được duyệt, để người đọc cờ biết cân nhắc.
 *
 * =============================================================================================
 * MÁY CHỦ KHÔNG BIẾT GÌ VỀ THƯ VIỆN NHẬN DIỆN
 * =============================================================================================
 * Nó chỉ nhận một dãy 128 con số và làm toán. Trình duyệt tính dãy số ấy bằng thư viện nào,
 * đổi thư viện lúc nào — máy chủ không quan tâm, miễn là cùng một thư viện cho mẫu và cho lượt
 * so. Đổi thư viện thì mọi mẫu cũ thành vô nghĩa, nên `dai_vector()` khoá số chiều lại: dãy
 * dài khác là chối thẳng, chứ không lặng lẽ so hai thứ không so được với nhau.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_Mat {

	/** Số chiều của dãy đặc trưng. Đổi con số này là mọi mẫu cũ thành vô nghĩa. */
	const DAI_VECTOR = 128;

	/**
	 * Ngưỡng khoảng cách (Euclid) giữa hai dãy đặc trưng.
	 *
	 * Đây là các con số của cả ngành, không phải em bịa: dưới ~0.45 là gần như chắc chắn cùng
	 * một người; trên ~0.6 là gần như chắc chắn hai người khác nhau; ở giữa là vùng KHÔNG BIẾT.
	 *
	 * 🔴 VÙNG GIỮA KHÔNG GẮN CỜ. Gắn cờ ở vùng không biết là đẻ ra một đống cờ mà xem xong
	 *    chẳng kết luận được gì — và một tuần sau không ai mở màn cờ nữa. Cờ phải hiếm thì mới
	 *    có người đọc.
	 */
	const D_KHOP   = 0.45;   // <= : coi như đúng người
	const D_LECH   = 0.60;   // >  : gắn cờ
	const D_GOP    = 0.38;   // <= : chắc tới mức dám gộp vào mẫu

	/** Gộp tối đa bấy nhiêu lần rồi thôi — mẫu đủ chín thì đừng động vào nữa. */
	const GOP_TOI_DA = 20;

	public static function bat() {
		return '1' === (string) get_option( 'vhcc_mat_bat', '1' );
	}

	/**
	 * Chế độ: 'im' = so và ghi nhật ký nhưng KHÔNG gắn cờ · 'co' = gắn cờ thật.
	 *
	 * 🔴 MẶC ĐỊNH LÀ 'im', VÀ ĐÓ LÀ CHỦ Ý. Ngưỡng 0,60 là con số của ngành chứ không phải của
	 *    K&H — nó phụ thuộc ánh sáng từng cơ sở, camera từng đời máy, khẩu trang. Bật cờ ngay
	 *    bằng số mặc định dẫn tới một trong hai kết cục, cả hai đều hỏng:
	 *      · cả trăm cờ oan -> hai tuần sau không ai mở màn cờ nữa, và cờ THẬT chìm theo;
	 *      · không cờ nào -> tưởng mọi thứ sạch, trong khi ngưỡng đang quá lỏng.
	 *    Chạy im vài tuần, đọc nhật ký, rồi mới chọn ngưỡng theo số ĐO ĐƯỢC.
	 */
	public static function che_do() {
		return 'co' === (string) get_option( 'vhcc_mat_che_do', 'im' ) ? 'co' : 'im';
	}

	/** Ngưỡng gắn cờ, chỉnh được ở Cài đặt khi thực tế báo nhầm nhiều quá. */
	public static function nguong_lech() {
		$v = (float) get_option( 'vhcc_mat_nguong', self::D_LECH );
		if ( $v < 0.3 || $v > 1.2 ) { return self::D_LECH; }   // số vô lý -> về mặc định
		return $v;
	}

	// ==================================================================== thư viện

	/**
	 * Thư mục đặt thư viện nhận diện. Anh Thắng tải về rồi bỏ vào đây.
	 *
	 * =========================================================================================
	 * 🔴 VÌ SAO TỰ ĐẶT VÀO PLUGIN, KHÔNG GỌI CDN
	 * =========================================================================================
	 * Anh Thắng chọn: *"Anh tải file rồi bỏ vào thư mục plugin"*. Đúng hơn hẳn gọi CDN:
	 *   · Trang chấm công không còn phụ thuộc một địa chỉ ngoài mà mình không kiểm soát. CDN
	 *     đổi đường dẫn, hết hạn tên miền, hay nhà mạng chặn — là cả tính năng chết mà không
	 *     có gì báo.
	 *   · Model là 6 MB. Tải từ chính hosting của mình thì nhân viên ở cơ sở tải một lần rồi
	 *     trình duyệt nhớ, và tốc độ do mình quyết chứ không do CDN quốc tế.
	 *   · Không gửi thông tin lượt truy cập của nhân viên sang máy chủ của bên thứ ba.
	 *
	 * ⚠️ THƯ MỤC TRỐNG LÀ CHUYỆN BÌNH THƯỜNG, KHÔNG PHẢI LỖI. Chưa bỏ file vào thì tính năng
	 *    im lặng không chạy, mọi thứ khác nguyên vẹn. Màn Cài đặt nói rõ còn thiếu file nào.
	 */
	/**
	 * Nơi đặt thư viện: `wp-content/uploads/vhcc-mat/`.
	 *
	 * =========================================================================================
	 * 🔴 NGOÀI THƯ MỤC PLUGIN, VÀ ĐÓ LÀ ĐIỂM CHÍNH
	 * =========================================================================================
	 * Anh Thắng 25/08/2026: *"sao tải xong về nó lại mất tiêu rồi"*. Vì bản trước để thư viện ở
	 * `plugins/vhcp-cham-cong/assets/mat/`, mà **cài đè plugin bằng tệp .zip thì WordPress XOÁ
	 * SẠCH thư mục plugin cũ rồi giải nén bản mới đè lên**. Bảy megabyte vừa tải bay theo, và
	 * lần cập nhật nào cũng bay lại.
	 *
	 * Em có ghi cảnh báo ấy trong DOC-TRUOC.txt — nhưng ghi cảnh báo về một cái bẫy mà vẫn để
	 * cái bẫy nằm đó thì không phải là sửa. Chỗ đúng là `uploads/`: WordPress không đụng tới nó
	 * khi cập nhật plugin, và các bản sao lưu của hosting đều gói nó theo.
	 *
	 * ⚠️ VẪN NHẬN THƯ MỤC CŨ. Ai đã kịp bỏ tệp vào trong plugin thì không phải làm lại — xem
	 *    `thu_muc_cu()` và cách `thu_vien()` dò cả hai nơi.
	 */
	public static function thu_muc() {
		$up = wp_upload_dir();
		return trailingslashit( $up['basedir'] ) . 'vhcc-mat/';
	}

	public static function url_thu_muc() {
		$up = wp_upload_dir();
		return trailingslashit( $up['baseurl'] ) . 'vhcc-mat/';
	}

	/** Chỗ cũ trong plugin — còn đọc được thì vẫn dùng, nhưng không tải mới vào đó nữa. */
	public static function thu_muc_cu() {
		return VHCC_DIR . 'assets/mat/';
	}

	public static function url_thu_muc_cu() {
		return VHCC_URL . 'assets/mat/';
	}

	/**
	 * Những file phải có, theo từng bộ.
	 *
	 * Nhận CẢ HAI bộ đang lưu hành, vì tên file của chúng khác nhau và anh Thắng có thể tải
	 * nhầm bộ — bắt đúng một bộ là dựng một cái bẫy im lặng ngay ở bước cài đặt.
	 */
	public static function bo_file() {
		return array(
			/* face-api.js bản gốc (justadudewhohacks) — phổ biến nhất, tên file ổn định. */
			'goc' => array(
				'js'  => 'face-api.min.js',
				'mau' => array(
					'tiny_face_detector_model-weights_manifest.json',
					'tiny_face_detector_model-shard1',
					'face_landmark_68_tiny_model-weights_manifest.json',
					'face_landmark_68_tiny_model-shard1',
					'face_recognition_model-weights_manifest.json',
					'face_recognition_model-shard1',
					'face_recognition_model-shard2',
				),
			),
		);
	}

	/**
	 * Thư viện đã sẵn sàng chưa, và còn thiếu gì.
	 *
	 * 🔴 KIỂM Ở MÁY CHỦ, không để trình duyệt tự dò. Trình duyệt dò thiếu file thì nó nhận về
	 *    một trang lỗi 404 của WordPress, cố đọc như JavaScript, rồi ném lỗi giữa lúc người ta
	 *    đang chấm công. Máy chủ biết chắc file có hay không — hỏi nó một lần là xong.
	 */
	public static function thu_vien() {
		/* Dò CHỖ MỚI trước, rồi mới tới chỗ cũ trong plugin. Ai đã kịp bỏ tệp vào plugin thì
		   vẫn chạy được cho tới lần cập nhật kế tiếp — lúc đó tệp mất, và hệ thống tự quay về
		   báo thiếu, không im lặng chạy sai. */
		foreach ( array( array( self::thu_muc(), self::url_thu_muc() ),
			array( self::thu_muc_cu(), self::url_thu_muc_cu() ) ) as $noi ) {
			$ra = self::soi_thu_muc( $noi[0], $noi[1] );
			if ( $ra['co'] ) { return $ra; }
		}
		/* Không nơi nào đủ: báo theo CHỖ MỚI, vì đó là nơi sẽ tải vào. */
		return self::soi_thu_muc( self::thu_muc(), self::url_thu_muc() );
	}

	/** Một thư mục có đủ bộ tệp không, và thiếu gì. */
	private static function soi_thu_muc( $goc, $url ) {
		$ra = array( 'co' => false, 'bo' => '', 'js' => '', 'mau_url' => '', 'thieu' => array(),
			'noi' => $goc );

		foreach ( self::bo_file() as $ten_bo => $bo ) {
			$thieu = array();
			if ( ! is_readable( $goc . $bo['js'] ) ) { $thieu[] = $bo['js']; }
			foreach ( $bo['mau'] as $f ) {
				if ( ! is_readable( $goc . $f ) ) { $thieu[] = $f; }
			}
			if ( ! $thieu ) {
				return array( 'co' => true, 'bo' => $ten_bo, 'noi' => $goc,
					'js'      => $url . $bo['js'],
					'mau_url' => rtrim( $url, '/' ),
					'thieu'   => array() );
			}
			/* Giữ danh sách thiếu của bộ NÀO GẦN ĐỦ NHẤT — báo "thiếu 7 file" khi người ta đã
			   bỏ vào 6 file là làm họ tưởng mình làm sai từ đầu. */
			if ( ! $ra['thieu'] || count( $thieu ) < count( $ra['thieu'] ) ) {
				$ra['thieu'] = $thieu;
				$ra['bo']    = $ten_bo;
			}
		}
		return $ra;
	}

	/**
	 * Nơi tải thư viện về.
	 *
	 * Địa chỉ tệp thô trên GitHub của chính kho mã nguồn mở face-api.js. Ghim vào một PHIÊN BẢN
	 * (thẻ `v0.22.2`), không dùng `master`: nhánh master đổi lúc nào không ai báo, và một bản
	 * model mới có thể không đọc được bằng dãy đặc trưng cũ — nghĩa là mọi mẫu khuôn mặt đã lấy
	 * thành vô nghĩa, mà không có gì đỏ lên cả.
	 */
	const NGUON = 'https://raw.githubusercontent.com/justadudewhohacks/face-api.js/v0.22.2/';

	/** Tệp nào lấy ở thư mục nào trong kho. */
	private static function duong_trong_kho( $ten ) {
		return ( 'face-api.min.js' === $ten ) ? 'dist/' . $ten : 'weights/' . $ten;
	}

	/**
	 * Tải các tệp còn thiếu về thư mục plugin.
	 *
	 * =========================================================================================
	 * 🔴 TẢI TỪNG ĐỢT, KHÔNG CỐ TẢI HẾT TRONG MỘT LƯỢT
	 * =========================================================================================
	 * Model nặng nhất khoảng 4 MB. Hosting chia sẻ thường cắt một lượt PHP ở 30 giây, và cắt
	 * thì cắt GIỮA CHỪNG: tệp đang ghi dở nằm lại trên đĩa với kích thước sai, lần sau
	 * `is_readable()` thấy có tệp nên báo "đủ rồi", rồi thư viện chết ở trình duyệt của nhân
	 * viên. Nên: canh đồng hồ, gần hết giờ thì dừng và bảo bấm tiếp.
	 *
	 * ⚠️ GHI RA TỆP TẠM RỒI MỚI ĐỔI TÊN. Ghi thẳng vào tên thật mà đứt giữa chừng là để lại
	 *    đúng cái tệp hỏng vừa nói. Đổi tên là thao tác gọn của hệ tệp — hoặc xong hẳn, hoặc
	 *    chưa có gì.
	 *
	 * ⚠️ Chỉ Admin. Đây là lệnh bảo máy chủ đi tải tệp từ internet về thư mục mã nguồn.
	 */
	public static function tai_ve( $u ) {
		if ( ! VHCC_Vai::duoc( $u, 'he_thong' ) ) {
			return array( 'ok' => false, 'error' => VHCC_Vai::loi( $u, 'he_thong', 'Tải thư viện nhận diện' ) );
		}
		/* ⚠️ Soi CHỖ MỚI, không hỏi `thu_vien()`. `thu_vien()` trả "đủ rồi" khi tệp còn nằm ở
		   thư mục cũ trong plugin — mà chỗ ấy sẽ bị xoá ở lần cập nhật plugin tới. Bấm nút này
		   là muốn CHUYỂN sang chỗ an toàn, nên phải hỏi chỗ an toàn thiếu gì. */
		$dich = self::thu_muc();
		$tv   = self::soi_thu_muc( $dich, self::url_thu_muc() );
		if ( $tv['co'] ) { return array( 'ok' => true, 'xong' => true, 'tai' => 0, 'con' => 0 ); }

		if ( ! wp_mkdir_p( $dich ) || ! is_writable( $dich ) ) {
			return array( 'ok' => false, 'error' => 'Không ghi được vào ' . $dich
				. '. Hosting đang khoá quyền ghi thư mục plugin — cách khác là tự tải tệp rồi '
				. 'bỏ vào bằng File Manager.' );
		}

		/* Còn bao nhiêu giây thì dừng. Chừa rộng tay: một tệp 4 MB trên đường truyền chậm có
		   thể ngốn hơn chục giây, và bị cắt giữa chừng thì mất công hơn là dừng sớm. */
		$han  = (int) ini_get( 'max_execution_time' );
		if ( $han <= 0 ) { $han = 30; }
		$het  = time() + max( 10, $han - 12 );

		$tai = 0; $loi = array();
		foreach ( $tv['thieu'] as $ten ) {
			if ( time() > $het ) { break; }

			/* Có sẵn ở thư mục cũ thì CHÉP, đừng tải lại 7 MB từ internet. Đây đúng tình huống
			   anh Thắng đang gặp: tệp còn nguyên trong plugin, chỉ cần dời sang chỗ mà cập nhật
			   plugin không xoá. Vẫn xét nội dung trước khi chép — tệp cũ cũng có thể hỏng dở. */
			$cu = self::thu_muc_cu() . $ten;
			if ( is_readable( $cu ) && true === self::xet_tep( $ten, file_get_contents( $cu ) ) ) {
				if ( copy( $cu, $dich . $ten ) ) { $tai++; continue; }
			}

			$kq = self::tai_mot( $ten, $dich );
			if ( true === $kq ) { $tai++; } else { $loi[] = $ten . ': ' . $kq; }
		}

		$con = self::thu_vien();
		return array( 'ok' => true, 'xong' => ! empty( $con['co'] ), 'tai' => $tai,
			'con' => count( $con['thieu'] ), 'loi' => $loi );
	}

	/** Tải một tệp. Trả true, hoặc câu giải thích vì sao không được. */
	private static function tai_mot( $ten, $dich ) {
		/* Chỉ nhận tên NẰM TRONG danh sách khai sẵn. Ghép tên từ bên ngoài vào đường dẫn là mở
		   đường cho `../` đi ngược lên thư mục khác. */
		$bo = self::bo_file();
		$hop_le = array_merge( array( $bo['goc']['js'] ), $bo['goc']['mau'] );
		if ( ! in_array( $ten, $hop_le, true ) ) { return 'tên tệp không nằm trong danh sách'; }

		$kq = wp_remote_get( self::NGUON . self::duong_trong_kho( $ten ), array(
			'timeout'  => 60,
			'headers'  => array( 'User-Agent' => 'VHCC-ChamCong/' . VHCC_VERSION ),
		) );
		if ( is_wp_error( $kq ) ) { return $kq->get_error_message(); }
		$ma = (int) wp_remote_retrieve_response_code( $kq );
		if ( 200 !== $ma ) { return 'máy chủ trả mã ' . $ma; }

		$than = wp_remote_retrieve_body( $kq );
		$xet  = self::xet_tep( $ten, $than );
		if ( true !== $xet ) { return $xet; }

		/* Tệp tạm rồi đổi tên — xem chú thích ở `tai_ve()`. */
		$tam = $dich . $ten . '.dang-tai';
		if ( false === file_put_contents( $tam, $than ) ) { return 'không ghi được tệp'; }
		if ( ! rename( $tam, $dich . $ten ) ) { @unlink( $tam ); return 'không đổi tên được tệp'; }
		return true;
	}

	/**
	 * Nội dung tải về có đúng thứ mình cần không.
	 *
	 * 🔴 KHÔNG TIN MÃ 200. Nhà mạng chèn trang quảng cáo, GitHub trả trang lỗi HTML, tường lửa
	 *    trả trang đăng nhập — tất cả đều kèm mã 200. Ghi một trang HTML dưới tên
	 *    `face_recognition_model-shard1` thì mọi thứ trông như đã cài xong, và hỏng ở đúng chỗ
	 *    không ai soi: trình duyệt của nhân viên, lúc họ đang chấm công.
	 */
	private static function xet_tep( $ten, $than ) {
		$dai = strlen( (string) $than );

		/* ⚠️ NGƯỠNG KÍCH THƯỚC PHẢI THEO TỪNG LOẠI, không một con số cho tất cả. Tệp khai báo
		   trọng số (`*_manifest.json`) có thể chỉ vài trăm byte — đặt một ngưỡng chung đủ lớn
		   để bắt trang lỗi thì nó chối luôn cả tệp thật. Với JSON thì phép thử đúng không phải
		   là kích thước mà là *có đọc ra được không*: một trang HTML thì không bao giờ đọc ra. */
		if ( '.json' === substr( $ten, -5 ) ) {
			$j = json_decode( $than, true );
			if ( ! is_array( $j ) ) { return 'không phải JSON — nhiều khả năng là trang lỗi HTML'; }
			return true;
		}
		if ( '.js' === substr( $ten, -3 ) ) {
			if ( $dai < 50000 ) { return 'tệp thư viện quá nhỏ (' . $dai . ' byte)'; }
			if ( false === strpos( $than, 'faceapi' ) && false === strpos( $than, 'TinyFaceDetector' ) ) {
				return 'không thấy mã face-api trong tệp';
			}
			return true;
		}
		/* Tệp trọng số là nhị phân: không đọc được nội dung, nhưng một trang HTML thì nhận ra
		   ngay từ mấy ký tự đầu. */
		$dau = ltrim( substr( $than, 0, 32 ) );
		if ( 0 === stripos( $dau, '<!doctype' ) || 0 === stripos( $dau, '<html' ) ) {
			return 'nhận về một trang HTML, không phải dữ liệu model';
		}
		if ( $dai < 10000 ) { return 'tệp model quá nhỏ (' . $dai . ' byte)'; }
		return true;
	}

	/** Xoá sạch thư viện đã tải — để tải lại từ đầu khi nghi tệp hỏng. */
	public static function xoa_thu_vien( $u ) {
		if ( ! VHCC_Vai::duoc( $u, 'he_thong' ) ) {
			return array( 'ok' => false, 'error' => VHCC_Vai::loi( $u, 'he_thong', 'Xoá thư viện nhận diện' ) );
		}
		$bo = self::bo_file();
		$so = 0;
		/* Xoá ở CẢ HAI nơi — còn sót một bản ở thư mục cũ thì `thu_vien()` vẫn thấy "đủ", và
		   người bấm "xoá để tải lại" không hiểu vì sao chẳng có gì đổi. */
		foreach ( array( self::thu_muc(), self::thu_muc_cu() ) as $noi ) {
			foreach ( array_merge( array( $bo['goc']['js'] ), $bo['goc']['mau'] ) as $f ) {
				if ( is_file( $noi . $f ) && @unlink( $noi . $f ) ) { $so++; }
			}
		}
		return array( 'ok' => true, 'so' => $so );
	}

	// ==================================================================== toán

	/**
	 * Dãy số hợp lệ -> mảng float. Không hợp lệ -> null.
	 *
	 * ⚠️ Chối cả dãy CÓ ĐỦ SỐ CHIỀU nhưng chứa giá trị vô lý (NaN, vô cực, số khổng lồ). Một
	 *    dãy rác lọt vào làm mẫu là người đó bị gắn cờ mọi ngày về sau, và không ai lần ra vì
	 *    sao — nhìn vào bảng chỉ thấy "mẫu đã có".
	 */
	public static function doc_vector( $v ) {
		if ( is_string( $v ) ) { $v = json_decode( $v, true ); }
		if ( ! is_array( $v ) || count( $v ) !== self::DAI_VECTOR ) { return null; }
		$ra = array();
		foreach ( $v as $x ) {
			if ( ! is_numeric( $x ) ) { return null; }
			$f = (float) $x;
			if ( ! is_finite( $f ) || $f > 100 || $f < -100 ) { return null; }
			$ra[] = $f;
		}
		return $ra;
	}

	/** Khoảng cách Euclid. Hai dãy phải cùng số chiều — gọi `doc_vector` trước. */
	public static function khoang_cach( $a, $b ) {
		$t = 0.0;
		for ( $i = 0; $i < self::DAI_VECTOR; $i++ ) {
			$d  = $a[ $i ] - $b[ $i ];
			$t += $d * $d;
		}
		return sqrt( $t );
	}

	/** Trung bình có trọng số: mẫu cũ nặng `$n` phần, dãy mới nặng 1 phần. */
	public static function gop( $cu, $moi, $n ) {
		$n  = max( 1, (int) $n );
		$ra = array();
		for ( $i = 0; $i < self::DAI_VECTOR; $i++ ) {
			$ra[] = ( $cu[ $i ] * $n + $moi[ $i ] ) / ( $n + 1 );
		}
		return $ra;
	}

	// ==================================================================== kho mẫu

	public static function mau( $ma_nv ) {
		global $wpdb;
		$ma = trim( (string) $ma_nv );
		if ( '' === $ma ) { return null; }
		$r = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . VHCC_DB::t( 'mat_mau' ) . ' WHERE ma_nv=%s', $ma ), ARRAY_A );
		return $r ? $r : null;
	}

	private static function luu_mau( $ma_nv, $vector, $them ) {
		global $wpdb;
		$bang = VHCC_DB::t( 'mat_mau' );
		$ghi  = array_merge( array(
			'ma_nv'    => trim( (string) $ma_nv ),
			'vector'   => wp_json_encode( array_map( function ( $x ) { return round( $x, 6 ); }, $vector ) ),
			'cap_nhat' => current_time( 'mysql' ),
		), (array) $them );
		$cu = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $bang WHERE ma_nv=%s", $ghi['ma_nv'] ) );
		if ( $cu ) { $wpdb->update( $bang, $ghi, array( 'id' => (int) $cu ) ); }
		else       { $wpdb->insert( $bang, $ghi ); }
	}

	// ==================================================================== việc chính

	/**
	 * Soi một dãy đặc trưng vừa gửi lên.
	 *
	 * 🔴 KHÔNG BAO GIỜ trả về thứ gì chặn được lượt chấm công. Chỗ gọi (VHCC_Tram) cũng đã ghi
	 *    giờ XONG rồi mới gọi tới đây — cố ý, để dù tệp này ném lỗi thì lượt chấm vẫn nguyên.
	 *
	 * @param array  $u      người đang đăng nhập (ma_nv, ho_ten, coso)
	 * @param mixed  $vector dãy 128 số từ trình duyệt
	 * @param string $ngay   ngày của lượt chấm (để gắn cờ đúng ngày)
	 * @param string $coso   cơ sở của lượt chấm
	 */
	public static function soi( $u, $vector, $ngay = '', $coso = '' ) {
		if ( ! self::bat() ) { return array( 'ok' => true, 'bo_qua' => 'tat' ); }

		$ma = trim( isset( $u['ma_nv'] ) ? (string) $u['ma_nv'] : '' );
		if ( '' === $ma ) { return array( 'ok' => true, 'bo_qua' => 'khong_ma' ); }

		$v = self::doc_vector( $vector );
		if ( null === $v ) { return array( 'ok' => true, 'bo_qua' => 'vector_hong' ); }

		if ( '' === $ngay ) { $ngay = current_time( 'Y-m-d' ); }
		$coso = VHCC_NhanSu::chuan_coso( '' !== $coso ? $coso : ( isset( $u['coso'] ) ? $u['coso'] : '' ) );

		$mau = self::mau( $ma );

		/* ---- chưa có mẫu: lấy chính tấm này làm mẫu, CHỜ DUYỆT ---- */
		if ( ! $mau ) {
			self::luu_mau( $ma, $v, array(
				'so_lan'      => 1,
				'trang_thai'  => 'cho',
				'nguon_ngay'  => $ngay,
				'nguon_coso'  => $coso,
				'ghi_chu'     => 'Lấy từ lượt chấm công đầu tiên',
				'tao_luc'     => current_time( 'mysql' ),
			) );
			return array( 'ok' => true, 'ket_qua' => 'lay_mau' );
		}

		$cu = self::doc_vector( $mau['vector'] );
		if ( null === $cu ) {
			/* Mẫu trong kho hỏng (đổi thư viện, dữ liệu lỗi) -> thay bằng dãy mới, chờ duyệt
			   lại. Cứ để mẫu hỏng nằm đó thì người này bị gắn cờ mỗi ngày mà không rõ vì sao. */
			self::luu_mau( $ma, $v, array( 'so_lan' => 1, 'trang_thai' => 'cho',
				'nguon_ngay' => $ngay, 'nguon_coso' => $coso,
				'ghi_chu' => 'Mẫu cũ không đọc được — lấy lại từ lượt này',
				'tao_luc' => current_time( 'mysql' ) ) );
			return array( 'ok' => true, 'ket_qua' => 'lay_lai_mau' );
		}

		$d      = self::khoang_cach( $cu, $v );
		$nguong = self::nguong_lech();

		/* ---- khớp chắc: gộp cho mẫu chín dần ---- */
		if ( $d <= self::D_GOP && (int) $mau['so_lan'] < self::GOP_TOI_DA ) {
			self::luu_mau( $ma, self::gop( $cu, $v, (int) $mau['so_lan'] ),
				array( 'so_lan' => (int) $mau['so_lan'] + 1 ) );
			return array( 'ok' => true, 'ket_qua' => 'khop', 'd' => round( $d, 4 ), 'gop' => true );
		}

		if ( $d <= $nguong ) {
			/* Khớp, hoặc nằm trong vùng KHÔNG BIẾT. Không gắn cờ — xem chú thích ở D_KHOP.
			   Vẫn ghi nhật ký: muốn chọn ngưỡng thì phải thấy CẢ phân bố, không chỉ cái đuôi.
			   Chỉ có số của phần lệch thì không biết đám đông người thật nằm ở đâu. */
			$kq = ( $d <= self::D_KHOP ) ? 'khop' : 'kho_noi';
			self::ghi_nhat_ky( $ma, $ngay, $coso, $d, $kq, false );
			return array( 'ok' => true, 'ket_qua' => $kq, 'd' => round( $d, 4 ) );
		}

		/* ---- lệch: GẮN CỜ, không chặn ---- */
		if ( 'im' === self::che_do() ) {
			/* Đang đo, chưa gắn cờ. Vẫn ghi nhật ký — đó chính là thứ để lát nữa chọn ngưỡng. */
			self::ghi_nhat_ky( $ma, $ngay, $coso, $d, 'lech', false );
			return array( 'ok' => true, 'ket_qua' => 'lech', 'd' => round( $d, 4 ), 'im' => true );
		}
		$flag = self::gan_co( $u, $ma, $ngay, $coso, $d, $mau );
		self::ghi_nhat_ky( $ma, $ngay, $coso, $d, 'lech', true );
		return array( 'ok' => true, 'ket_qua' => 'lech', 'd' => round( $d, 4 ), 'flagId' => $flag );
	}

	/**
	 * Ghi một dòng nhật ký đối chiếu.
	 *
	 * ⚠️ MỘT DÒNG CHO MỖI (người, ngày, kết quả) — không phải mỗi lượt. Vào, ra, ca tối là ba
	 *    lượt mỗi ngày; ghi hết thì bảng phình gấp ba mà không nói thêm điều gì. Giữ dòng có
	 *    khoảng cách LỚN NHẤT trong ngày: đó mới là lượt đáng xem.
	 */
	private static function ghi_nhat_ky( $ma, $ngay, $coso, $d, $ket_qua, $co_gan ) {
		global $wpdb;
		$bang = VHCC_DB::t( 'mat_nhat_ky' );
		$cu = $wpdb->get_row( $wpdb->prepare(
			"SELECT id, d FROM $bang WHERE ma_nv=%s AND ngay=%s AND ket_qua=%s",
			$ma, $ngay, $ket_qua ), ARRAY_A );
		$ghi = array( 'ma_nv' => $ma, 'ngay' => $ngay, 'coso' => $coso,
			'd' => round( $d, 4 ), 'ket_qua' => $ket_qua, 'co_gan' => $co_gan ? 1 : 0,
			'tao_luc' => current_time( 'mysql' ) );
		if ( ! $cu ) { $wpdb->insert( $bang, $ghi ); return; }
		if ( (float) $cu['d'] < $d ) { $wpdb->update( $bang, $ghi, array( 'id' => (int) $cu['id'] ) ); }
	}

	/**
	 * Thống kê để chọn ngưỡng: mỗi khoảng 0,05 có bao nhiêu lượt.
	 *
	 * Đây là bảng anh Thắng đọc sau vài tuần chạy im. Nhìn vào đó thấy khoảng nào là "đám đông
	 * người thật" và khoảng nào là cái đuôi thưa đáng ngờ — ngưỡng nằm ở chỗ hai đám tách ra,
	 * không phải ở con số em viết sẵn trong mã.
	 */
	public static function thong_ke( $u, $tu_ngay = '', $den_ngay = '' ) {
		global $wpdb;
		if ( ! VHCC_Vai::duoc( $u, 'ho_so' ) ) { return array( 'ok' => false, 'error' => VHCC_Vai::loi( $u, 'ho_so', 'Xem thống kê đối chiếu mặt' ) ); }
		$bang = VHCC_DB::t( 'mat_nhat_ky' );
		$dk   = '';
		$tv   = array();
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $tu_ngay ) )  { $dk .= ' AND ngay >= %s'; $tv[] = $tu_ngay; }
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $den_ngay ) ) { $dk .= ' AND ngay <= %s'; $tv[] = $den_ngay; }
		$sql = "SELECT d, ket_qua, co_gan, ma_nv, ngay, coso FROM $bang WHERE 1=1" . $dk . ' ORDER BY d DESC';
		$r   = $tv ? $wpdb->get_results( $wpdb->prepare( $sql, $tv ), ARRAY_A )
		           : $wpdb->get_results( $sql, ARRAY_A );

		$o = array();
		foreach ( (array) $r as $x ) {
			$k = (string) ( floor( (float) $x['d'] / 0.05 ) * 0.05 );
			if ( ! isset( $o[ $k ] ) ) { $o[ $k ] = 0; }
			$o[ $k ]++;
		}
		ksort( $o, SORT_NUMERIC );
		return array( 'ok' => true, 'tong' => count( (array) $r ), 'o' => $o,
			/* 30 lượt lệch nhất — mở ảnh của đúng mấy lượt này là biết ngưỡng đặt ở đâu. */
			'dau' => array_slice( (array) $r, 0, 30 ) );
	}

	/**
	 * Ghi một cờ "mặt không khớp".
	 *
	 * Ghi THẲNG vào bảng cờ, không đi qua `VHCC_Cham::luu_ghi_chu()`: hàm đó gác theo quyền cơ
	 * sở của NGƯỜI ĐANG BẤM, mà ở đây không có ai bấm cả — máy tự ghi. Nhân viên vừa chấm công
	 * thì không có quyền cơ sở nào (đúng thiết kế), nên gọi qua đó là cờ không bao giờ ghi được.
	 *
	 * ⚠️ MỘT NGÀY MỘT CỜ cho mỗi người: `flag_id` dựng từ (mã NV, ngày). Người chấm vào và ra,
	 *    có khi thêm ca tối — ba lượt lệch là ba cờ giống hệt nhau, và màn cờ thành bãi rác.
	 */
	private static function gan_co( $u, $ma, $ngay, $coso, $d, $mau ) {
		global $wpdb;
		$flag = 'MAT' . str_replace( '-', '', $ngay ) . '-' . strtoupper( $ma );
		$chua_duyet = ( 'duyet' !== (string) $mau['trang_thai'] );
		$chu = 'Ảnh chấm công KHÔNG khớp mẫu khuôn mặt (lệch ' . round( $d, 2 )
			. ', ngưỡng ' . round( self::nguong_lech(), 2 ) . '). '
			. 'Mở ảnh của lượt này xem có đúng người không.'
			. ( $chua_duyet
				? ' ⚠️ Mẫu của người này CHƯA ĐƯỢC DUYỆT (tự lấy từ lượt chấm đầu tiên ngày '
					. (string) $mau['nguon_ngay'] . ') — nếu chính tấm mẫu ấy mới là tấm sai thì'
					. ' cờ này ngược, hãy xoá mẫu để hệ thống lấy lại.'
				: '' );

		$ghi = array(
			'flag_id'    => $flag,
			'coso'       => $coso,
			'ngay'       => $ngay,
			'ma_nv'      => $ma,
			'ho_ten'     => trim( isset( $u['ho_ten'] ) ? (string) $u['ho_ten'] : '' ),
			'ghi_chu'    => $chu,
			'nguoi_gan'  => 'Hệ thống (đối chiếu khuôn mặt)',
			'trang_thai' => 'Cần kiểm',
			'tao_luc'    => current_time( 'mysql' ),
		);
		$bang = VHCC_DB::t( 'ghi_chu' );
		$cu   = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $bang WHERE flag_id=%s", $flag ) );
		if ( $cu ) { $wpdb->update( $bang, $ghi, array( 'id' => (int) $cu ) ); }
		else       { $wpdb->insert( $bang, $ghi ); }
		return $flag;
	}

	// ==================================================================== quản trị

	/** Danh sách mẫu, để màn quản trị duyệt / xoá. */
	public static function ds( $u, $trang_thai = '' ) {
		global $wpdb;
		if ( ! VHCC_Vai::duoc( $u, 'ho_so' ) ) { return array(); }
		$bang = VHCC_DB::t( 'mat_mau' );
		$hs   = VHCC_DB::t( 'nhan_vien' );
		$sql  = "SELECT m.*, n.ho_ten, n.cua_hang FROM $bang m LEFT JOIN $hs n ON n.ma_nv = m.ma_nv";
		if ( in_array( $trang_thai, array( 'cho', 'duyet' ), true ) ) {
			$sql = $wpdb->prepare( $sql . ' WHERE m.trang_thai=%s ORDER BY m.cap_nhat DESC', $trang_thai );
		} else {
			$sql .= ' ORDER BY m.trang_thai ASC, m.cap_nhat DESC';
		}
		$r = $wpdb->get_results( $sql, ARRAY_A );
		$out = array();
		foreach ( (array) $r as $x ) {
			/* KHÔNG trả dãy đặc trưng ra màn hình. Nó là dữ liệu sinh trắc học, và màn hình
			   này không dùng tới nó — chỉ cần biết đã có mẫu, gộp mấy lần, duyệt chưa. */
			unset( $x['vector'] );
			$out[] = $x;
		}
		return $out;
	}

	public static function duyet( $u, $ma_nv ) {
		global $wpdb;
		if ( ! VHCC_Vai::duoc( $u, 'ho_so' ) ) {
			return array( 'ok' => false, 'error' => VHCC_Vai::loi( $u, 'ho_so', 'Duyệt mẫu khuôn mặt' ) );
		}
		$ma = trim( (string) $ma_nv );
		if ( ! self::mau( $ma ) ) { return array( 'ok' => false, 'error' => 'Không thấy mẫu của mã ' . $ma . '.' ); }
		$wpdb->update( VHCC_DB::t( 'mat_mau' ),
			array( 'trang_thai' => 'duyet', 'nguoi_duyet' => isset( $u['name'] ) ? (string) $u['name'] : '',
				'cap_nhat' => current_time( 'mysql' ) ),
			array( 'ma_nv' => $ma ) );
		return array( 'ok' => true );
	}

	/**
	 * Xoá mẫu — lượt chấm công tiếp theo sẽ tự lấy mẫu mới.
	 * Đây là đường sửa cho tình huống mẫu đầu tiên bắt nhầm mặt người khác.
	 */
	public static function xoa( $u, $ma_nv ) {
		global $wpdb;
		if ( ! VHCC_Vai::duoc( $u, 'ho_so' ) ) {
			return array( 'ok' => false, 'error' => VHCC_Vai::loi( $u, 'ho_so', 'Xoá mẫu khuôn mặt' ) );
		}
		$ma = trim( (string) $ma_nv );
		if ( '' === $ma ) { return array( 'ok' => false, 'error' => 'Thiếu mã NV.' ); }
		$wpdb->delete( VHCC_DB::t( 'mat_mau' ), array( 'ma_nv' => $ma ) );
		return array( 'ok' => true, 'thong_bao' => 'Đã xoá mẫu của ' . $ma
			. '. Lượt chấm công tới sẽ tự lấy mẫu mới.' );
	}

	/** Đếm nhanh cho màn quản trị: bao nhiêu mẫu, bao nhiêu đang chờ duyệt. */
	public static function dem() {
		global $wpdb;
		$bang = VHCC_DB::t( 'mat_mau' );
		if ( ! VHCC_DB::co_bang( $bang ) ) { return array( 'tong' => 0, 'cho' => 0 ); }
		return array(
			'tong' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $bang" ),
			'cho'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $bang WHERE trang_thai='cho'" ),
		);
	}
}
