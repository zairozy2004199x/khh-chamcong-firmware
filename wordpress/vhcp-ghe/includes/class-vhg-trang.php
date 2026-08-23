<?php
/**
 * TRANG NGOÀI `/ghe` — bản thay cho dashboard "POSH massage" của Apps Script.
 *
 * =============================================================================================
 * KHÁC HẲN TRANG `/cham-cong`: TRANG NÀY TỰ CHỨA
 * =============================================================================================
 * Trang chấm công lấy giao diện thẳng từ Apps Script để khỏi tồn tại "bản chép" lệch với bản
 * gốc. Trang này KHÔNG làm vậy, cố ý: cả hệ thống ghế đã rời hẳn Google, nên đi vòng qua Apps
 * Script là dựng lại đúng cái phụ thuộc vừa gỡ. Giao diện nằm trong plugin, dữ liệu lấy từ
 * MySQL, không gọi ra ngoài lượt nào.
 *
 * =============================================================================================
 * BA LUẬT CỦA MÀN NÀY
 * =============================================================================================
 * 1. KHÔNG BAO GIỜ CHUYỂN HƯỚNG. Giống hai cổng máy: WordPress rất thích thêm/bỏ dấu gạch cuối,
 *    mà trang này người ta lưu vào màn hình chính điện thoại — một lượt 301 là mất phiên.
 *
 * 2. LỖI PHẢI NÓI RA. Ghế mất kết nối, tiền đã vào mà ghế chưa nhận — hai chuyện đó để TRÊN
 *    CÙNG, trên cả con số doanh thu. Người mở trang này lúc 9 giờ tối đang đứng cạnh một ghế
 *    không chạy và một khách đang cáu; họ cần câu trả lời trước, không cần báo cáo tháng.
 *
 * 3. BẬT TAY LÀ CHO KHÔNG MỘT LƯỢT. Nút đó có, vì thực tế cần, nhưng phải ghi lại ai bấm và
 *    lúc nào — cuối tháng còn giải thích được vì sao một ghế chạy nhiều hơn số tiền thu.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHG_Trang {

	/** Tên hệ thống — khai MỘT chỗ. Nó hiện ở thẻ tiêu đề trình duyệt, màn đăng nhập và dải đầu
	    trang; ba chỗ gõ tay là ba chỗ lệch nhau sau lần đổi tên đầu tiên. */
	const TEN_HE_THONG = 'Hệ Thống Thanh Toán Ghế Massage Tự Động POSH';
	const TEN_NGAN     = 'POSH Massage';

	public static function slug() {
		$s = get_option( 'vhg_slug' );
		$s = $s ? sanitize_title( $s ) : 'ghe';
		return $s ? $s : 'ghe';
	}

	public static function url() {
		if ( get_option( 'permalink_structure' ) ) { return home_url( '/' . self::slug() . '/' ); }
		return add_query_arg( 'vhg', 'app', home_url( '/' ) );
	}

	public static function init() {
		add_rewrite_rule( '^' . self::slug() . '/?$', 'index.php?vhg_app=1', 'top' );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'parse_request', array( __CLASS__, 'chan_chuyen_huong' ), 0 );
		add_action( 'template_redirect', array( __CLASS__, 'phuc_vu' ), 0 );
	}

	public static function query_vars( $v ) { $v[] = 'vhg_app'; return $v; }

	private static function la_trang() {
		if ( 1 === (int) get_query_var( 'vhg_app' ) ) { return true; }
		if ( isset( $_GET['vhg'] ) && 'app' === $_GET['vhg'] ) { return true; }
		$d = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
		$d = trim( (string) parse_url( $d, PHP_URL_PATH ), '/' );
		$s = self::slug();
		return $d === $s || substr( $d, - ( strlen( $s ) + 1 ) ) === '/' . $s;
	}

	/** Luật 1. Xem khối đầu tệp. */
	public static function chan_chuyen_huong() {
		if ( ! self::la_trang() ) { return; }
		add_filter( 'redirect_canonical', '__return_false', 99 );
		remove_action( 'template_redirect', 'redirect_canonical' );
	}

	public static function phuc_vu() {
		if ( ! self::la_trang() ) { return; }
		if ( isset( $_GET['api'] ) || isset( $_POST['api'] ) ) {
			self::api();
			if ( ! defined( 'VHG_TEST' ) ) { exit; }
			return;
		}
		self::ve();
		if ( ! defined( 'VHG_TEST' ) ) { exit; }
	}

	// =========================================================================================
	// API — JSON, tất cả đi qua POST và mang token của phiên
	// =========================================================================================

	private static function tra( $d ) {
		if ( ! headers_sent() ) {
			status_header( 200 );
			nocache_headers();
			header( 'Content-Type: application/json; charset=utf-8' );
		}
		echo wp_json_encode( $d );
	}

	private static function than() {
		if ( defined( 'VHG_TEST' ) && isset( $GLOBALS['VHG_THAN'] ) ) { return (string) $GLOBALS['VHG_THAN']; }
		$t = file_get_contents( 'php://input' );
		return is_string( $t ) ? $t : '';
	}

	public static function api() {
		$d = json_decode( self::than(), true );
		if ( ! is_array( $d ) ) { $d = array(); }
		foreach ( $_POST as $k => $v ) { if ( ! isset( $d[ $k ] ) ) { $d[ $k ] = $v; } }
		$viec = isset( $_GET['api'] ) ? (string) $_GET['api']
			: (string) ( isset( $d['api'] ) ? $d['api'] : '' );
		$viec = preg_replace( '/[^a-z_]/', '', strtolower( $viec ) );

		if ( 'login' === $viec ) {
			self::tra( VHG_Auth::login( isset( $d['pin'] ) ? $d['pin'] : '' ) );
			return;
		}

		$tok = (string) ( isset( $d['token'] ) ? $d['token'] : '' );
		$ai  = VHG_Auth::user_by_token( $tok );
		if ( ! $ai ) {
			/* `het_phien` là MÃ, không phải câu chữ: giao diện phải phân biệt được "hết phiên,
			   hiện lại ô PIN" với "lỗi khác, đừng đá người ta ra". Bắt theo câu chữ thì sửa một
			   dấu phẩy trong thông báo là đăng nhập lại vô hạn. */
			self::tra( array( 'ok' => false, 'ma' => 'het_phien',
				'error' => 'Phiên đã hết hoặc quyền đã bị thu — đăng nhập lại.' ) );
			return;
		}

		if ( 'logout' === $viec ) { self::tra( VHG_Auth::logout( $tok ) ); return; }

		if ( 'so_lieu' === $viec ) {
			self::tra( self::so_lieu( isset( $d['ky'] ) ? $d['ky'] : 'today', $ai ) );
			return;
		}

		if ( 'bat' === $viec || 'tat' === $viec || 'khoi_dong_lai' === $viec ) {
			$r = VHG_May::dat_lenh(
				isset( $d['ma_may'] ) ? $d['ma_may'] : '',
				'bat' === $viec ? 'on' : ( 'tat' === $viec ? 'off' : 'reboot' ),
				isset( $d['phut'] ) ? $d['phut'] : 0,
				/* Ghi TÊN NGƯỜI ĐANG CẦM PHIÊN, không lấy tên từ gói gửi lên. Luật 3: bật tay là
				   cho không một lượt, nên chữ ký phải là thứ người bấm không tự khai được. */
				$ai['name'],
				isset( $d['ly_do'] ) ? $d['ly_do'] : '' );
			self::tra( $r );
			return;
		}

		if ( 'gan_ma' === $viec ) {
			/* 🔴 Gán ghế NGAY TRÊN ĐIỆN THOẠI. Người đi lắp ghế ở Aeon Tân Phú cầm cái điện
			 *    thoại, không cầm wp-admin. Bắt họ nhắn về văn phòng nhờ ai đó vào wp-admin gán
			 *    hộ là thêm một vòng chờ, và trong lúc chờ thì ghế đứng đó không thu được đồng nào.
			 *
			 * ⚠️ Ghi TÊN NGƯỜI CẦM PHIÊN vào nhật ký, không lấy tên từ gói gửi lên: gán mã là
			 *    đổi khoá của một dòng doanh thu, phải biết ai làm. */
			$r = VHG_May::gan_ma(
				isset( $d['ma_cu'] ) ? $d['ma_cu'] : '',
				isset( $d['ma_moi'] ) ? $d['ma_moi'] : '',
				isset( $d['coso_id'] ) ? (int) $d['coso_id'] : null );
			if ( ! empty( $r['ok'] ) ) {
				VHG_Nhat_Ky::ghi( array( 'nguon' => 'he-thong', 'ghi_chu' =>
					$ai['name'] . ' gán mã ghế: ' . (string) ( isset( $d['ma_cu'] ) ? $d['ma_cu'] : '' )
					. ' -> ' . (string) ( isset( $d['ma_moi'] ) ? $d['ma_moi'] : '' ) ) );
			}
			self::tra( $r );
			return;
		}

		if ( 'ma_tra' === $viec ) {
			/* Nhân viên tra hộ khách QUÊN PIN — chỉ cần số điện thoại.
			   ⚠️ Đường này bỏ qua PIN, nên nó CHỈ được nằm ở đây: trang `/ghe` đã qua cổng PIN
			      nhân viên. Trang của khách không có việc này, và không được có. */
			$sdt_tra = isset( $d['sdt'] ) ? $d['sdt'] : '';
			$kq_ma   = VHG_Ma::tra_nhan_vien( $sdt_tra );
			/* 🔴 TRA MỘT LẦN, RA CẢ HAI. Khách đứng ở quầy nói "em có mua trước" — họ không nhớ
			   mình mua MÃ hay nạp VÍ, và không có lý do gì phải nhớ. Bắt nhân viên tra hai chỗ
			   là có ngày họ tra một chỗ, thấy trống, rồi nói với khách là "không có gì". */
			$kq_vi = VHG_Vi::tra_nhan_vien( $sdt_tra );
			if ( ! empty( $kq_vi['ok'] ) ) {
				$kq_ma['vi'] = array(
					'dung'    => (int) $kq_vi['so_du']['dung'],
					'cho'     => (int) $kq_vi['so_du']['cho'],
					'tong'    => (int) $kq_vi['so_du']['tong'],
					'con_cho' => (int) $kq_vi['so_du']['con_cho'],
					'khoa'    => empty( $kq_vi['so_du']['khoa'] ) ? 0 : 1,
				);
			}
			/* Không có mã NHƯNG có ví thì vẫn là một lượt tra THÀNH CÔNG — đừng trả `ok=false`
			   chỉ vì một trong hai vế trống. */
			if ( empty( $kq_ma['ok'] ) && ! empty( $kq_vi['ok'] ) ) {
				$kq_ma = array( 'ok' => true, 'ds' => array(), 'vi' => $kq_ma['vi'] );
			}
			self::tra( $kq_ma );
			return;
		}

		if ( 'qua_trao' === $viec ) {
			/* Trao quà là một hành động có hậu quả (một phần quà chỉ trao được một lần), nhưng
			   KHÔNG phải quyết định về tiền như huỷ mã — người đứng quầy trao được. */
			self::tra( VHG_Vi::trao_qua(
				isset( $d['id'] ) ? (int) $d['id'] : 0, (string) $ai['ten'] ) );
			return;
		}

		if ( 'ma_huy' === $viec ) {
			/* 🔴 Huỷ mã là quyết định về TIỀN: khách đã trả rồi. Chỉ Admin và Quản lý — người
			   đứng quầy không nên tự quyết chuyện hoàn/không hoàn, và nếu có quyết thì cũng
			   không ai biết để hỏi lại. */
			if ( ! in_array( $ai['role'], array( 'Admin', 'Quản lý' ), true ) ) {
				self::tra( array( 'ok' => false,
					'error' => 'Chỉ Admin hoặc Quản lý mới huỷ được mã đã bán.' ) );
				return;
			}
			$r = VHG_Ma::huy( isset( $d['ma'] ) ? $d['ma'] : '',
				isset( $d['ly_do'] ) ? $d['ly_do'] : '', $ai['name'] );
			if ( ! empty( $r['ok'] ) ) {
				VHG_Nhat_Ky::ghi( array( 'nguon' => 'he-thong', 'ghi_chu' => $ai['name']
					. ' huỷ mã ' . (string) ( isset( $d['ma'] ) ? $d['ma'] : '' )
					. ' — ' . (string) ( isset( $d['ly_do'] ) ? $d['ly_do'] : '' ) ) );
			}
			self::tra( $r );
			return;
		}

		if ( 'so_may' === $viec ) {
			/* Số liệu MỘT ghế cho màn chốt ca. Gọi riêng chứ không nhét vào lượt `so_lieu`: nó
			   chỉ cần khi người ta bấm Thu tiền mặt, mà `so_lieu` chạy mỗi lần tải trang. */
			$ma = trim( (string) ( isset( $d['ma_may'] ) ? $d['ma_may'] : '' ) );
			/* `ds_may_theo_ma()` chứ không `may()`: chỉ bản này mới kèm tên cơ sở (có JOIN). Màn
			   chốt ca phải nói rõ đang đếm tiền của ghế nào Ở ĐÂU — người đi thu tiền đi nhiều
			   cơ sở trong một buổi. */
			$bd = VHG_May::ds_may_theo_ma();
			$m  = isset( $bd[ $ma ] ) ? $bd[ $ma ] : null;
			if ( ! $m ) { self::tra( array( 'ok' => false, 'error' => 'Không thấy ghế ' . $ma . '.' ) ); return; }
			self::tra( array( 'ok' => true, 'ma_may' => $ma,
				'coso' => (string) ( isset( $m['coso_ten'] ) ? $m['coso_ten'] : '' ),
				'gia'  => (int) VHG_May::ty_le_cua( $m )['gia'],
				'hom_nay' => VHG_Thu::tong_may( $ma, 'today' ),
				'tuan'    => VHG_Thu::tong_may( $ma, 'week' ),
				'thang'   => VHG_Thu::tong_may( $ma, 'month' ),
				'tat_ca'  => VHG_Thu::tong_may( $ma, 'all' ) ) );
			return;
		}

		if ( 'tien_mat' === $viec ) {
			self::tra( VHG_Thu::thu_tien_mat(
				isset( $d['ma_may'] ) ? $d['ma_may'] : '',
				isset( $d['so_tien'] ) ? $d['so_tien'] : 0,
				$ai['name'] ) );
			return;
		}

		self::tra( array( 'ok' => false, 'error' => 'Việc không rõ: ' . $viec ) );
	}

	/**
	 * Toàn bộ số liệu một màn, MỘT LƯỢT GỌI.
	 *
	 * Gọi tách ra bốn lượt thì trên 4G ở trung tâm thương mại là bốn cơ hội hỏng, và màn hình
	 * hiện nửa vời — doanh thu có mà tình trạng ghế trống, người đọc không biết đang xem cái gì.
	 */
	private static function so_lieu( $ky, $ai ) {
		$ky  = in_array( $ky, array( 'today', 'week', 'month', 'year', 'all' ), true ) ? $ky : 'today';
		$t   = VHG_Thu::tong_hop( $ky );
		$may = array();
		foreach ( VHG_May::ds_may() as $m ) {
			$may[] = array(
				'ma'      => $m['ma'],
				'coso'    => $m['coso_ten'] ? $m['coso_ten'] : '',
				'song'    => ! empty( $m['con_song'] ),
				'tt'      => (string) $m['trang_thai'],
				'con_lai' => (int) $m['con_lai'],
				'cho'     => (int) $m['cho'],
				'gia'     => (int) VHG_May::ty_le_cua( $m )['gia'],
				'phut'    => (int) VHG_May::ty_le_cua( $m )['phut'],
				/* Cục nhận tiền: gửi CẢ mã lẫn câu giải thích. Người đứng quầy không tra bảng mã
				   — mà đây lại đúng là người phải chạy ra xem cái máy. */
				'tm'      => (string) $m['tm_loi'],
				'tm_cu'   => (string) $m['tm_cuoi'],
				/* Gửi CẢ hai ngôn ngữ trong một lượt. Gửi theo ngôn ngữ đang chọn thì mỗi lần
				   đổi VI/EN lại phải gọi lại máy chủ — trên 4G là vài giây đứng nhìn cho một
				   việc hoàn toàn nằm trong máy người ta. */
				'tm_chu'    => VHG_May::loi_tien_chu( '' !== (string) $m['tm_loi']
					? $m['tm_loi'] : $m['tm_cuoi'] ),
				'tm_chu_en' => VHG_May::loi_tien_chu( '' !== (string) $m['tm_loi']
					? $m['tm_loi'] : $m['tm_cuoi'], 'en' ),
			);
		}
		/* Ghế đang chờ gán mã + danh sách cơ sở: gửi kèm luôn trong lượt số liệu, không thêm
		   lượt gọi. Xem ghi chú "một lượt gọi ra đủ màn" ở dưới. */
		$cho_gan = array();
		foreach ( VHG_May::chua_gan() as $g ) {
			$cho_gan[] = array( 'ma' => $g['ma'], 'mac' => $g['mac'],
				'song' => ! empty( $g['con_song'] ), 'luc' => (string) $g['nhip_luc'] );
		}
		$ds_coso = array();
		foreach ( VHG_May::ds_coso() as $c ) {
			$ds_coso[] = array( 'id' => (int) $c['id'], 'ten' => (string) $c['ten'] );
		}
		/* NHẬT KÝ BẬT TỪ XA — gửi kèm trong chính lượt số liệu, không thêm lượt gọi. Mỗi lần bấm
		   Bật là CHO KHÔNG một lượt: cuối tháng nhìn "ghế chạy 180 lượt, thu 140" thì 40 lượt kia
		   phải giải thích được bằng con số, không bằng trí nhớ. Kèm tổng THÁNG bất kể đang xem kỳ
		   nào — câu hỏi thật lúc đối chiếu luôn là "tháng này bao nhiêu". */
		$bat_ky    = VHG_May::tong_lenh( $ky );
		$bat_may   = VHG_May::tong_lenh_may( $ky );
		$bat_thang = VHG_May::tong_lenh( 'month' );
		$bat_ngay  = VHG_May::tong_lenh_ngay( $ky );
		$bat_ds    = array();
		foreach ( VHG_May::ds_lenh_bat( $ky, 60 ) as $l ) {
			$bat_ds[] = array( 'luc' => (string) $l['tao_luc'], 'ma' => (string) $l['ma_may'],
				'phut' => (int) $l['phut'], 'nguoi' => (string) $l['nguoi'],
				'ly_do' => (string) $l['ly_do'],
				/* `gui_luc` rỗng = ghế chưa lấy lệnh (đang mất mạng). Vẫn tính vào nhật ký, nhưng
				   phải hiện ra: người đọc cần phân biệt "đã chạy" với "sẽ chạy khi ghế lên". */
				'da_gui' => '' !== trim( (string) $l['gui_luc'] ) );
		}

		$cho = array();
		foreach ( VHG_May::ds_cho( true, 50 ) as $c ) {
			$cho[] = array( 'luc' => $c['tao_luc'], 'ma_may' => $c['ma_may'],
				'so_tien' => (int) $c['so_tien'], 'ma_lenh' => $c['ma_lenh'] );
		}
		$gd = array();
		foreach ( VHG_Thu::ds( $ky, 60 ) as $r ) {
			$gd[] = array(
				'luc'     => $r['luc'],
				'may'     => '' !== $r['ma_may'] ? $r['ma_may'] : ( '' !== $r['ten_khai'] ? $r['ten_khai'] : '' ),
				'nguon'   => $r['nguon'],
				'so_tien' => (int) $r['so_tien'],
				'noi_dung' => $r['noi_dung'],
			);
		}
		return array( 'ok' => true, 'ky' => $ky, 'ai' => $ai, 'tong' => $t,
			'may' => $may, 'cho' => $cho, 'gd' => $gd,
			'choGan' => $cho_gan, 'coso' => $ds_coso,
			'bat' => array( 'ky' => $bat_ky, 'thang' => $bat_thang,
				'ngay' => $bat_ngay, 'may' => $bat_may, 'ds' => $bat_ds ),
			/* Tab Thu tiền: tách hai đường tiền mặt (ghế nuốt / người thu) — xem khối giải thích
			   trên VHG_Thu::ND_GHE_NUOT. Gửi kèm trong lượt này, không thêm lượt gọi. */
			'thu' => array( 'ds' => VHG_Thu::ds_tien_mat( $ky, 80 ),
				'may' => array_values( VHG_Thu::theo_may_tien_mat( $ky ) ) ),
			/* Mã mua trước: tổng kỳ + khoản đang NỢ (mã không hết hạn nên nó chỉ cộng lên). */
			'ma' => array( 'tong' => VHG_Ma::tong( $ky ), 'no' => VHG_Ma::tien_no(),
				'ds' => VHG_Ma::ds( $ky, 120 ), 'quyen_huy' =>
					in_array( $ai['role'], array( 'Admin', 'Quản lý' ), true ) ? 1 : 0 ),
			/* 🔴 VÍ ĐI CÙNG MÃ, KHÔNG TÁCH RA TAB RIÊNG.
			   Anh Thắng 23/08/2026: *"trên wed cũng chưa có số dư của ví khách"*.
			   Hai thứ này cùng trả lời một câu hỏi của nhân viên đang đứng ở quầy: *"khách này
			   còn gì chưa dùng?"* — tách hai tab là bắt họ nhớ khách mua kiểu nào trước khi tra,
			   mà chính khách cũng không nhớ.
			   ⚠️ `so_du` là NỢ, đúng như `VHG_Ma::tien_no()` bên trên: tiền đã thu, dịch vụ chưa
			      trả. Hai con số phải đứng CẠNH NHAU thì mới ra được tổng nợ thật. */
			'vi' => array( 'no' => VHG_Vi::tong_no(), 'ds' => self::vi_gon( VHG_Vi::ds_vi( 60 ) ),
				'co_ban' => VHG_Vi::goi_nap() ? 1 : 0 ),
			/* Quà chờ trao — việc CÓ THẬT của người đứng quầy, nên nó phải nằm trên màn họ mở
			   cả ca chứ không nằm trong wp-admin. Số điện thoại che ngay từ máy chủ. */
			'qua' => array( 'cho' => self::qua_gon( VHG_Vi::qua_cho_trao( 40 ) ),
				'tong' => VHG_Vi::tong_qua(), 'bat' => VHG_Vi::tich_cf()['bat'] ? 1 : 0 ),
			'luc' => current_time( 'H:i:s' ) );
	}

	/**
	 * Rút gọn danh sách ví trước khi gửi ra trình duyệt.
	 *
	 * 🔴 CHE SỐ ĐIỆN THOẠI, VÀ CẮT LUÔN SỐ ĐẦY ĐỦ RA KHỎI GÓI TIN.
	 *    Che ở giao diện là chưa đủ: số đầy đủ vẫn nằm trong gói JSON, và mở tab Network ra là
	 *    thấy — hoặc chỉ cần một dòng trong bảng điều khiển trình duyệt là xuất được cả danh
	 *    sách khách hàng. Phải cắt ở MÁY CHỦ, trước khi gửi.
	 *
	 * ⚠️ Cũng vì thế mà hàm này KHÔNG dùng chung với màn quản trị: màn kia chạy trong wp-admin,
	 *    đã qua cổng đăng nhập của WordPress và có quyền cao hơn. Trang `/ghe` là màn nhân viên
	 *    ca nào cũng mở, thường để nguyên trên một cái máy tính ở quầy.
	 *
	 * Bốn số cuối vẫn còn — đủ để nhân viên đối chiếu với khách đang đứng trước mặt.
	 */
	/** Quà chờ trao, đã che số điện thoại — cùng lý do với `vi_gon()`. */
	private static function qua_gon( $ds ) {
		$ra = array();
		foreach ( (array) $ds as $q ) {
			$ra[] = array(
				'id'      => (int) $q['id'],
				'sdt_che' => VHG_Ma::sdt_che( isset( $q['sdt'] ) ? $q['sdt'] : '' ),
				'ghi_chu' => (string) ( isset( $q['ghi_chu'] ) ? $q['ghi_chu'] : '' ),
				'moc'     => (int) ( isset( $q['moc'] ) ? $q['moc'] : 0 ),
				'tao_luc' => (string) ( isset( $q['tao_luc'] ) ? $q['tao_luc'] : '' ),
			);
		}
		return $ra;
	}

	private static function vi_gon( $ds ) {
		$ra = array();
		foreach ( (array) $ds as $v ) {
			$ra[] = array(
				'sdt_che'    => VHG_Ma::sdt_che( isset( $v['sdt'] ) ? $v['sdt'] : '' ),
				'so_du_dung' => (int) ( isset( $v['so_du_dung'] ) ? $v['so_du_dung'] : 0 ),
				'so_du_cho'  => (int) ( isset( $v['so_du_cho'] ) ? $v['so_du_cho'] : 0 ),
				'da_nap'     => (int) ( isset( $v['da_nap'] ) ? $v['da_nap'] : 0 ),
				'da_tieu'    => (int) ( isset( $v['da_tieu'] ) ? $v['da_tieu'] : 0 ),
				'khoa'       => empty( $v['khoa'] ) ? 0 : 1,
			);
		}
		return $ra;
	}

	// =========================================================================================
	// GIAO DIỆN
	// =========================================================================================

	public static function ve() {
		if ( ! headers_sent() ) {
			status_header( 200 );
			nocache_headers();
			header( 'Content-Type: text/html; charset=utf-8' );
		}
		$api = esc_url( self::url() );
		/* Ảnh nền do người dùng khai trong Cài đặt. `esc_url_raw` rồi mới nhét vào CSS: chuỗi này
		   đi thẳng vào một thuộc tính style, nên một dấu nháy lọt qua là chèn được CSS tuỳ ý. */
		$nen = esc_url_raw( (string) get_option( 'vhg_anh_nen', '' ) );
		$lop = '';
		$bien_nen = '';
		if ( '' !== $nen && ! preg_match( '/["\\\\()]/', $nen ) ) {
			$lop       = ' class="co-anh"';
			$bien_nen  = ' style="--nen:url(&quot;' . esc_attr( $nen ) . '&quot;)"';
		}
		echo '<!doctype html><html lang="vi"><head><meta charset="utf-8">'
			. '<meta name="viewport" content="width=device-width,initial-scale=1">'
			. '<title>' . esc_html( self::TEN_HE_THONG ) . '</title>'
			/* Người đứng quầy lưu trang này vào màn hình chính điện thoại. */
			. '<meta name="theme-color" content="#12141f">'
			. '<style>' . self::css() . VHG_Chan::css() . '</style></head><body' . $lop . $bien_nen . '>'
			. '<div id="app"></div>'
			. '<script>window.VHG_API=' . wp_json_encode( $api ) . ';'
				. 'window.VHG_TEN=' . wp_json_encode( self::TEN_HE_THONG ) . ';</script>'
			. '<script>' . self::js() . '</script>'
			/* Chân trang pháp lý — DỰNG Ở MÁY CHỦ, ngoài `#app`. Nằm trong JS thì JS hỏng là
			   thông tin công ty biến mất; xem VHG_Chan::html(). */
			. VHG_Chan::html()
			. '</body></html>';
	}

	private static function css() {
		return <<<'CSS'
*{box-sizing:border-box}
/* ============================================================================================
 * NỀN ẢNH PHÒNG GHẾ.
 *
 * Lớp ảnh để `position:fixed` ở `body::before` chứ KHÔNG phải `background-attachment:fixed`
 * trên body: Safari trên iOS lờ hẳn thuộc tính đó, mà điện thoại mới là nơi trang này sống.
 * Ảnh còn được phủ một lớp tối; không phủ thì chữ trắng nằm trên vùng sáng của ảnh là không
 * đọc nổi, và ảnh nào cũng có một vùng sáng ở đâu đó.
 *
 * Chưa khai ảnh thì rơi về dải màu tự dựng — KHÔNG để trắng trơn và cũng không tải ảnh từ đâu
 * khác về: trang này mở trên 4G ở trung tâm thương mại, và một ảnh nền không tải được sẽ để lại
 * đúng cái nền trắng chữ trắng đó.
 * ============================================================================================ */
body::before{content:"";position:fixed;inset:0;z-index:-2;
  background:radial-gradient(1200px 620px at 78% 8%,#3a2f1c 0%,transparent 62%),
             radial-gradient(900px 520px at 12% 92%,#1d2647 0%,transparent 60%),
             linear-gradient(160deg,#12141f 0%,#171a2e 46%,#0f1120 100%);
  background-size:cover;background-position:center}
body.co-anh::before{background-image:var(--nen);background-size:cover;background-position:center}
/* Lớp phủ tối RIÊNG, không trộn vào ảnh: trộn thì mỗi lần đổi ảnh lại phải chỉnh lại độ tối. */
body::after{content:"";position:fixed;inset:0;z-index:-1;pointer-events:none;
  background:linear-gradient(180deg,rgba(9,10,18,.80) 0%,rgba(9,10,18,.68) 38%,rgba(9,10,18,.86) 100%)}
body{margin:0;background:#12141f;color:#e8ebff;min-height:100vh;
  font:15px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
.wrap{max-width:1180px;margin:0 auto;padding:14px}
h1{font-size:19px;margin:0}
h1 small{display:block;font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:#a79a7d;font-weight:400;margin-top:3px}

/* --- Dải đầu trang: khối kính, dính trên cùng ---
   Dính vì đây là chỗ có nút Thoát và đồng hồ; cuộn xuống bảng giao dịch dài rồi phải cuộn
   ngược lên mới thoát được là một cái phiền không đáng có. */
.top{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:0 0 14px;
  position:sticky;top:0;z-index:20;padding:11px 14px;
  background:rgba(16,18,30,.80);-webkit-backdrop-filter:blur(14px);backdrop-filter:blur(14px);
  border:1px solid rgba(240,180,41,.20);border-radius:14px;
  box-shadow:0 10px 30px rgba(0,0,0,.42)}
.top .sp{flex:1}
.hieu{display:flex;align-items:center;gap:11px;min-width:0}
/* Ô biểu tượng: viền vàng mảnh, nền vàng rất nhạt — cùng ngôn ngữ với các nhãn vàng khác. */
.hieu-o{width:38px;height:38px;flex:none;display:flex;align-items:center;justify-content:center;
  border-radius:11px;font-size:19px;background:rgba(240,180,41,.13);
  border:1px solid rgba(240,180,41,.34)}
.dh-top{font-variant-numeric:tabular-nums;font-weight:600;color:#f0b429;letter-spacing:.04em}
/* Nút đổi ngôn ngữ: hai ô dính nhau, ô đang chọn tô vàng. Để cạnh đồng hồ vì cả hai là thứ
   người ta liếc chứ không bấm thường xuyên. */
.nn{display:inline-flex;border:1px solid rgba(255,255,255,.15);border-radius:9px;overflow:hidden}
.nn button{border:0;border-radius:0;padding:6px 10px;font-size:12px;font-weight:600;letter-spacing:.06em}
.nn button+button{border-left:1px solid rgba(255,255,255,.15)}
.nn-doi{margin-top:14px;display:flex;justify-content:center}
.tabs{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:14px}
button{font:inherit;cursor:pointer;border-radius:9px;border:1px solid rgba(255,255,255,.15);
  background:rgba(255,255,255,.07);color:#e8ebff;padding:7px 13px;transition:background .15s,border-color .15s}
button:hover{background:rgba(255,255,255,.13);border-color:rgba(240,180,41,.4)}
button.on{background:#f0b429;border-color:#f0b429;color:#221a00;font-weight:600}
button.on:hover{background:#f7c246}
button.ghost{background:transparent}
input,select{font:inherit;border-radius:9px;border:1px solid rgba(255,255,255,.15);
  background:rgba(10,12,22,.55);color:#e8ebff;padding:7px 10px;width:100%}
input:focus,select:focus{outline:none;border-color:#f0b429}
.kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(165px,1fr));gap:10px;margin-bottom:14px}
.kpi{background:rgba(24,27,44,.72);-webkit-backdrop-filter:blur(10px);backdrop-filter:blur(10px);
  border:1px solid rgba(255,255,255,.09);border-radius:12px;padding:12px 14px}
.kpi .lb{font-size:10px;letter-spacing:.1em;text-transform:uppercase;color:#a79a7d}
.kpi .vl{font-size:21px;font-weight:700;margin-top:3px;word-break:break-all}
.kpi .sb{font-size:12px;color:#8d93c4}
.vl.a{color:#f0b429}.vl.b{color:#5fa8ff}.vl.c{color:#4ade80}.vl.d{color:#e8ebff}
.card{background:rgba(22,25,40,.74);-webkit-backdrop-filter:blur(12px);backdrop-filter:blur(12px);
  border:1px solid rgba(255,255,255,.09);border-radius:14px;padding:13px 15px;margin-bottom:14px;
  box-shadow:0 8px 26px rgba(0,0,0,.34)}
/* Tiêu đề khối: chữ hoa, giãn chữ, một vạch vàng bên trái — để mắt bắt được ranh giới giữa các
   khối ngay cả khi tất cả cùng là kính mờ trên một tấm ảnh. */
.card h2{font-size:12px;margin:0 0 11px;letter-spacing:.12em;text-transform:uppercase;
  color:#f0b429;font-weight:700;padding-left:10px;border-left:3px solid #f0b429;line-height:1.25}
table{width:100%;border-collapse:collapse;font-size:13px}
th{text-align:left;font-size:10px;letter-spacing:.08em;text-transform:uppercase;color:#a79a7d;font-weight:500;padding:0 8px 7px 0;border-bottom:1px solid rgba(240,180,41,.22)}
td{padding:8px 8px 8px 0;border-bottom:1px solid rgba(255,255,255,.07);vertical-align:middle}
tr:last-child td{border-bottom:0}
.r{text-align:right}
.pill{display:inline-block;padding:2px 9px;border-radius:99px;font-size:11px;font-weight:600}
.p-ok{background:#12351f;color:#4ade80}.p-run{background:#2a2410;color:#f0b429}
.p-wait{background:#111f3d;color:#5fa8ff}.p-off{background:#3a1418;color:#ff8087}
.warn{background:rgba(58,20,24,.82);-webkit-backdrop-filter:blur(10px);backdrop-filter:blur(10px);
  border:1px solid #7c2732;border-radius:14px;padding:12px 14px;margin-bottom:14px}
.warn b{color:#ff8087}
.note{background:rgba(42,36,16,.82);-webkit-backdrop-filter:blur(10px);backdrop-filter:blur(10px);
  border:1px solid #6b551a;border-radius:14px;padding:12px 14px;margin-bottom:14px}
.note b{color:#f0b429}
.mut{color:#9aa0c2;font-size:12px}
.login{max-width:360px;margin:12vh auto;padding:28px 24px;background:rgba(20,23,38,.80);
  -webkit-backdrop-filter:blur(16px);backdrop-filter:blur(16px);
  border:1px solid rgba(240,180,41,.26);border-radius:18px;text-align:center;
  box-shadow:0 22px 60px rgba(0,0,0,.55)}
.login .hieu-o{margin:0 auto 12px;width:46px;height:46px;font-size:23px}
.login h1{margin-bottom:6px}
.login input{text-align:center;letter-spacing:.5em;font-size:21px;margin:16px 0 10px}
.err{color:#ff8087;font-size:13px;min-height:19px;margin-top:8px}
.act{display:flex;gap:5px;flex-wrap:wrap;align-items:center}
/* --- Tab chính --- */
.nav{display:flex;gap:6px;margin-bottom:14px;border-bottom:1px solid rgba(240,180,41,.2);padding-bottom:10px}
.nav button{border-radius:9px 9px 0 0}
/* --- Thẻ ghế (tab Điều khiển) ---
   Bảng hợp cho đối soát (so số theo cột), nhưng KHÔNG hợp cho điều khiển: người bấm đang đứng
   cạnh một con ghế cụ thể và cần thấy đúng nó, to và rõ, chứ không dò theo hàng. */
.ghe-luoi{display:grid;grid-template-columns:repeat(auto-fill,minmax(258px,1fr));gap:12px}
.ghe{background:rgba(20,23,38,.78);-webkit-backdrop-filter:blur(10px);backdrop-filter:blur(10px);
  border:1px solid rgba(255,255,255,.10);border-radius:14px;padding:14px;
  box-shadow:0 6px 20px rgba(0,0,0,.3)}
.ghe.dut{border-color:#7c2732;background:rgba(38,20,26,.78)}
/* Ghế đang chạy: viền vàng ĐẬM hơn hẳn, vì trong một lưới 26 thẻ kính mờ giống nhau thì cái
   đang chạy phải bắt được mắt từ đầu bên kia quầy. */
.ghe.chay{border-color:rgba(240,180,41,.65);box-shadow:0 0 0 1px rgba(240,180,41,.18),0 6px 22px rgba(0,0,0,.36)}
.ghe-dau{display:flex;align-items:center;gap:8px;margin-bottom:2px}
.ghe-ma{font-size:17px;font-weight:700}
.ghe-cs{font-size:12px;color:#a79a7d;margin-bottom:10px}
.ghe-dh{font-size:31px;font-weight:700;color:#f0b429;margin:6px 0 2px;
  font-variant-numeric:tabular-nums;letter-spacing:.01em}
.ghe-tien-loi{margin:8px 0;padding:8px 10px;border-radius:8px;font-size:12px;line-height:1.45}
.ghe-tien-loi div{font-weight:400;margin-top:3px;opacity:.92}
.ghe-tien-loi.dang{background:rgba(220,60,50,.16);border:1px solid rgba(220,60,50,.5);
  color:#ffb4ae;font-weight:700}
.ghe-tien-loi.cu{background:rgba(240,180,41,.12);border:1px solid rgba(240,180,41,.4);
  color:#f0c76b;font-weight:600}
.ghe-nut{display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-top:11px}
.ghe-nut button{padding:9px 6px;font-size:13px}
.b-bat{background:#12351f;border-color:#2f6b45;color:#8ff0b0}
.b-tat{background:#3a1418;border-color:#7c2732;color:#ff8087}
.b-kd{background:#111f3d;border-color:#2b4a80;color:#9ecbff}
.ghe-hang{display:flex;gap:6px;align-items:center;margin-top:8px}
.ghe-hang input{width:70px}
.ghe-hang label{font-size:11px;color:#a79a7d}
/* --- Bảng chốt ca thu tiền --- */
.man{position:fixed;inset:0;background:rgba(8,10,22,.82);display:flex;align-items:center;
  justify-content:center;padding:14px;z-index:50;overflow:auto}
.hop{background:#1e2240;border:1px solid #3a4170;border-radius:14px;
  padding:18px;max-width:440px;width:100%}
.hop h3{margin:0 0 2px;font-size:18px}
.hop .cs{font-size:12px;color:#8d93c4;margin-bottom:14px}
.so-hang{display:flex;justify-content:space-between;align-items:baseline;padding:9px 0;
  border-bottom:1px solid #252a4b}
.so-hang:last-of-type{border-bottom:0}
.so-hang .nh{font-size:13px;color:#8d93c4}
.so-hang .gt{font-size:16px;font-weight:700}
.so-hang.to{border-top:1px solid #3a4170;margin-top:6px;padding-top:12px}
.so-hang.to .nh{color:#e8ebff;font-weight:600}
.so-hang.to .gt{font-size:21px;color:#f0b429}
.o-thu{margin:14px 0 8px}
.o-thu input{font-size:23px;text-align:right;padding:11px 13px;font-weight:700}
.phim{display:grid;grid-template-columns:repeat(3,1fr);gap:6px;margin:10px 0}
.phim button{padding:13px 0;font-size:17px}
.hop-nut{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:12px}
.hop-nut button{padding:12px 0}
.canh{background:#3a1418;border:1px solid #7c2732;border-radius:9px;padding:9px 11px;
  font-size:12px;color:#ff8087;margin-top:10px}
.act input{width:66px;padding:5px 7px}
.act select{font:inherit;border-radius:8px;border:1px solid #343a63;background:#151831;color:#e8ebff;padding:5px 7px;max-width:130px}
.note code{background:#151831;padding:1px 5px;border-radius:5px}
.act button{padding:5px 10px;font-size:12px}
@media(max-width:560px){.hide-sm{display:none}.wrap{padding:10px}}

/* ============================================================================================
 * MÀN MÁY TÍNH. Bản đầu chỉ ngắm điện thoại nên trên màn rộng nó bó vào một cột giữa, hai bên
 * bỏ trống hơn nửa màn hình — mà người ngồi văn phòng đối soát cuối ngày lại dùng đúng màn đó.
 *
 * Không chỉ nới bề ngang: xếp "Theo cơ sở" và "Theo ghế" NẰM CẠNH NHAU. Hai bảng đó là hai
 * cách nhìn cùng một số tiền, đặt cạnh nhau thì so được bằng mắt; xếp dọc thì phải cuộn qua
 * lại và người ta thôi không so nữa.
 * ============================================================================================ */
@media(min-width:1100px){
  .wrap{max-width:1400px;padding:20px 26px}
  body{font-size:15.5px}
  h1{font-size:22px}
  .kpis{gap:14px}
  .kpi{padding:14px 18px}
  .kpi .vl{font-size:25px}
  .card{padding:16px 18px}
  .card h2{font-size:15px}
  table{font-size:14px}
  td{padding:10px 10px 10px 0}
  /* Hai bảng tổng hợp nằm cạnh nhau */
  .doi{display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:start}
  .doi .card{margin-bottom:0}
  /* Ô nhập trong cột "Việc" đang dính sát nhau ở màn rộng — cho chúng thở */
  .act{gap:8px}
  .act input{width:78px}
}
@media(min-width:1500px){
  .wrap{max-width:1560px}
  .kpis{grid-template-columns:repeat(4,1fr)}
}
CSS;
	}

	private static function js() {
		return <<<'JS'
(function(){
var API = window.VHG_API, TOK = null, KY = 'today', D = null, ban = false;
var TEN_HT = window.VHG_TEN || 'POSH Massage';
/* Tab đang mở. Nhớ lại giữa các lần tải: người đang điều khiển ghế bấm ↻ mà bị đá về tab đối
   soát là mỗi lượt bấm mất thêm một cú bấm nữa. */
var TAB = 'doi-soat';
try { TAB = localStorage.getItem('vhg_tab') || 'doi-soat'; } catch(e) {}

/* ============================================================================================
 * HAI NGÔN NGỮ.
 *
 * Cặp Việt/Anh viết NGAY TẠI CHỖ dùng — `L('Đối soát','Reconciliation')` — chứ không gom vào
 * một bảng khoá kiểu `t('tab.doisoat')`. Bảng khoá đọc gọn hơn ở chỗ dùng, nhưng ở đây nó sai:
 * cả tệp này là những câu giải thích dài cho người đứng quầy, mà một câu tiếng Việt nằm cách
 * bản dịch của nó bốn trăm dòng thì sửa một bên quên bên kia là chuyện chắc chắn xảy ra. Để
 * cạnh nhau thì không sửa lệch được.
 *
 * ⚠️ CON SỐ KHÔNG DỊCH. Tiền vẫn định dạng kiểu Việt Nam ("50.000đ") ở cả hai ngôn ngữ: đây là
 *    tiền Việt đếm trong két Việt, và người nước ngoài đọc bảng này vẫn phải đối chiếu với tờ
 *    tiền thật trên tay. Đổi dấu chấm/phẩy theo tiếng Anh là mời người ta đọc nhầm 50.000 thành
 *    năm mươi.
 * ============================================================================================ */
var NN = 'vi';
try { NN = localStorage.getItem('vhg_nn') === 'en' ? 'en' : 'vi'; } catch(e) {}
function L(vi, en){ return NN === 'en' ? en : vi; }
function nutNN(){
  return '<span class="nn">'
    + '<button data-nn="vi"' + (NN==='vi'?' class="on"':'') + '>VI</button>'
    + '<button data-nn="en"' + (NN==='en'?' class="on"':'') + '>EN</button></span>';
}
function noiNN(){
  [].forEach.call(document.querySelectorAll('[data-nn]'), function(b){
    b.onclick = function(){ datNN(b.getAttribute('data-nn')); };
  });
}
function datNN(n){
  NN = (n === 'en') ? 'en' : 'vi';
  try { localStorage.setItem('vhg_nn', NN); } catch(e) {}
  document.documentElement.setAttribute('lang', NN);
  /* Vẽ lại từ dữ liệu ĐANG CÓ. Gọi lại máy chủ chỉ để đổi chữ là bắt người ta chờ 4G cho một
     việc hoàn toàn nằm trong máy họ. */
  if (D) ve(); else veLogin('');
}

/* ============================================================================================
 * TỰ CẬP NHẬT.
 *
 * 🔴 Anh Thắng 22/08/2026: *"bấm điều khiển máy chạy, nhưng trên web thời gian chưa chạy"*. Đúng
 *    — trang chỉ tải khi mở hoặc khi bấm ↻. Người đứng cạnh ghế bấm Bật, ghế chạy thật, nhưng
 *    web vẫn nói "Rảnh"; họ tưởng lệnh không ăn nên bấm lần nữa — mà mỗi lần bấm Bật là CHO
 *    KHÔNG một lượt nữa.
 *
 * Hai nhịp khác nhau, cố ý:
 *   · Tab ĐIỀU KHIỂN 5 giây — người đang đứng đó chờ ghế phản hồi.
 *   · Tab ĐỐI SOÁT 30 giây — số tiền không đổi từng giây, mà trang này mở suốt ngày trên 4G.
 *
 * Và số đếm ngược tự trừ MỖI GIÂY giữa hai lượt hỏi, chứ không đứng im rồi nhảy 5 giây một
 * lần: một con số đứng im là dấu hiệu ghế treo, đừng để giao diện tự tạo ra dấu hiệu đó.
 * ============================================================================================ */
var NHIP_MS = { 'dieu-khien': 5000, 'doi-soat': 30000 };
var hen = null, demGiay = null;
try { TOK = localStorage.getItem('vhg_tok'); } catch(e) {}

var app = document.getElementById('app');
function esc(s){ return String(s==null?'':s).replace(/[&<>"]/g, function(c){
  return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]; }); }
function tien(n){ return (Number(n)||0).toLocaleString('vi-VN') + 'đ'; }
/* "còn 4 ngày 3 giờ" — câu người đọc là hiểu.
   ⚠️ Nói GIỐNG HỆT ba nơi kia: VHG_Ma::doc_con_cho() bên máy chủ, và docCho() bên trang khách.
      Cùng một khoảng thời gian mà ba nơi nói ba kiểu là nhân viên đọc một đằng, khách đọc một
      nẻo, rồi cãi nhau xem ai đúng. */
function docCho(giay){
  var g = Math.max(0, Number(giay) || 0);
  var ngay = Math.floor(g / 86400), gio = Math.floor((g % 86400) / 3600);
  if (ngay > 0) return ngay + L(' ngày',' days') + (gio > 0 ? ' ' + gio + L(' giờ',' h') : '');
  if (gio > 0)  return gio + L(' giờ',' hours');
  return Math.max(1, Math.ceil(g / 60)) + L(' phút',' min');
}
/* mm:ss có số 0 ở đầu — ĐÚNG KIỂU MÀN GHẾ VẼ (`snprintf("%02d:%02d")`). Ghế hiện "04:57" mà
   web hiện "4:57" thì cùng một con số ra hai kiểu, và người đối chiếu bằng mắt sẽ dừng lại một
   nhịp để tự hỏi hai chỗ có nói cùng một thứ không. Chiều rộng cố định còn đỡ nhảy chữ khi
   đếm qua mốc 10 phút. */
function mmss(s){ s=Math.max(0,Number(s)||0);
  return String(Math.floor(s/60)).padStart(2,'0') + ':' + String(s%60).padStart(2,'0'); }

function goi(viec, d, xong){
  d = d || {}; d.token = TOK;
  var x = new XMLHttpRequest();
  x.open('POST', API + (API.indexOf('?')<0?'?':'&') + 'api=' + viec, true);
  x.setRequestHeader('Content-Type','application/json');
  x.onreadystatechange = function(){
    if (x.readyState !== 4) return;
    var r = null;
    try { r = JSON.parse(x.responseText); } catch(e){}
    /* Máy chủ trả rác (tường lửa hosting chèn trang chặn, mạng đứt giữa chừng) KHÔNG được
       thành "hết phiên" — đá người ta ra rồi họ gõ lại PIN và gặp đúng lỗi đó. */
    if (!r) { xong({ ok:false, error:L('Không đọc được trả lời của máy chủ (mạng hoặc tường lửa).',
      'Could not read the server reply (network or firewall).') }); return; }
    if (r.ma === 'het_phien') { TOK = null; try{localStorage.removeItem('vhg_tok');}catch(e){} veLogin(L('Phiên đã hết — đăng nhập lại.','Session expired — please sign in again.')); return; }
    xong(r);
  };
  x.send(JSON.stringify(d));
}

// ------------------------------------------------------------------ đăng nhập
function veLogin(loi){
  app.innerHTML =
    '<div class="login"><div class="hieu-o">💆</div>'
    + '<h1>' + esc(TEN_HT) + '<small>' + L('Doanh thu &amp; tình trạng ghế','Revenue &amp; chair status')
    + '</small></h1>'
    + '<input id="pin" type="tel" inputmode="numeric" maxlength="8" placeholder="PIN" autocomplete="off">'
    + '<button id="vao" class="on" style="width:100%">' + L('Vào','Sign in') + '</button>'
    + '<div class="err" id="e">' + esc(loi||'') + '</div>'
    + '<div class="nn-doi">' + nutNN() + '</div></div>';
  noiNN();
  var pin = document.getElementById('pin'), e = document.getElementById('e');
  function thu(){
    var v = (pin.value||'').trim();
    if (!v) { e.textContent = L('Chưa nhập PIN.','Please enter your PIN.'); return; }
    e.textContent = L('Đang kiểm…','Checking…');
    goi('login', { pin: v }, function(r){
      if (!r.ok) { e.textContent = r.error || L('PIN không đúng','Incorrect PIN'); pin.value=''; pin.focus(); return; }
      TOK = r.token; try{localStorage.setItem('vhg_tok',TOK);}catch(er){}
      tai();
    });
  }
  document.getElementById('vao').onclick = thu;
  pin.addEventListener('keydown', function(ev){ if (ev.key === 'Enter') thu(); });
  pin.focus();
}

// ------------------------------------------------------------------ màn chính
function tai(im){
  goi('so_lieu', { ky: KY }, function(r){
    if (!r.ok) { if (!im) veLogin(r.error || ''); return; }
    D = r; ve();
  });
}

/* Hẹn lượt hỏi kế tiếp. Luôn huỷ lượt cũ trước: không thì mỗi lần vẽ lại là thêm một đồng hồ,
   và sau mươi phút trang tự hỏi máy chủ vài chục lần một giây. */
function henLai(){
  if (hen) { clearTimeout(hen); hen = null; }
  if (!TOK) return;
  hen = setTimeout(function(){
    /* KHÔNG hỏi khi: người dùng đang chờ một lệnh chạy xong, đang mở bảng chốt ca (vẽ lại là
       xoá mất số họ đang gõ), hoặc trang đang ẩn (điện thoại trong túi — hỏi cũng không ai đọc,
       chỉ tốn 4G). */
    if (ban || CHOT || document.hidden) { henLai(); return; }
    tai(true);
  }, NHIP_MS[TAB] || 30000);
}

/* Đồng hồ đếm ngược chạy TẠI CHỖ giữa hai lượt hỏi. Chỉ đụng vào phần chữ của con số, không
   vẽ lại cả trang — vẽ lại mỗi giây là mất luôn ô đang gõ dở và nút đang bấm. */
function chayDongHo(){
  if (demGiay) { clearInterval(demGiay); demGiay = null; }
  if (TAB !== 'dieu-khien' || !D) return;
  demGiay = setInterval(function(){
    if (!D || document.hidden) return;
    var co = false;
    D.may.forEach(function(m){
      if (m.tt !== 'running' || !m.song) return;
      if (m.con_lai > 0) { m.con_lai--; co = true; }
      var o = document.querySelector('[data-dh="' + m.ma + '"]');
      if (o) o.textContent = mmss(m.con_lai);
    });
    /* Hết giờ tại chỗ thì hỏi lại ngay, đừng đợi hết nhịp 5 giây: lúc đó trạng thái ghế vừa
       đổi và đó chính là thứ người ta đang chờ xem. */
    if (!co) { clearInterval(demGiay); demGiay = null; if (!ban && !CHOT) tai(true); }
  }, 1000);
}

function ve(){
  var t = D.tong, h = '';

  h += '<div class="wrap"><div class="top">'
    + '<div class="hieu"><div class="hieu-o">💆</div>'
    + '<h1>' + esc(TEN_HT) + '<small>' + esc(D.ai.name) + ' · ' + esc(D.ai.role) + '</small></h1></div>'
    + '<span class="sp"></span>'
    /* Đồng hồ chạy từng giây như bảng thiết kế. Lấy giờ MÁY CHỦ làm mốc rồi tự tích, không lấy
       giờ điện thoại: điện thoại nhân viên hay lệch, mà mọi con số khác trên trang này đều theo
       giờ máy chủ — hai loại giờ cạnh nhau là mời người ta đối chiếu nhầm. */
    + '<span class="dh-top" id="dh-top">' + esc(D.luc) + '</span>'
    + nutNN()
    + '<button id="lam-moi" class="ghost" title="' + L('Tải lại','Refresh') + '">↻</button>'
    + '<button id="thoat" class="ghost">' + L('Thoát','Sign out') + '</button></div>';

  h += '<div class="nav">'
    + '<button data-tab="doi-soat"' + (TAB==='doi-soat'?' class="on"':'') + '>📊 '
      + L('Đối soát','Reconciliation') + '</button>'
    + '<button data-tab="thu-tien"' + (TAB==='thu-tien'?' class="on"':'') + '>💵 '
      + L('Thu tiền','Cash collection') + '</button>'
    + '<button data-tab="kich-hoat"' + (TAB==='kich-hoat'?' class="on"':'') + '>⚡ '
      + L('Kích hoạt ghế','Chair activation') + '</button>'
    + '<button data-tab="ma"' + (TAB==='ma'?' class="on"':'') + '>🎁 '
      + L('Mã giảm giá','Discount codes') + '</button>'
    + '<button data-tab="dieu-khien"' + (TAB==='dieu-khien'?' class="on"':'') + '>🎛 '
      + L('Điều khiển ghế','Chair control') + '</button>'
    + '</div>';

  /* Ba tab BÁO CÁO đều xem theo kỳ, nên bộ chọn kỳ hiện cho cả ba. Tab Điều khiển thì không:
     ở đó không có con số nào theo kỳ, để bộ chọn ra là mời người ta bấm rồi tự hỏi vừa đổi gì. */
  if (TAB === 'doi-soat' || TAB === 'thu-tien' || TAB === 'kich-hoat' || TAB === 'ma') {
    h += '<div class="tabs">';
    [['today',L('Hôm nay','Today')],['week',L('Tuần này','This week')],['month',L('Tháng này','This month')],
     ['year',L('Năm nay','This year')],['all',L('Tất cả','All time')]]
      .forEach(function(k){ h += '<button data-ky="' + k[0] + '"' + (KY===k[0]?' class="on"':'') + '>' + k[1] + '</button>'; });
    h += '</div>';
  }

  /* GHẾ CHỜ GÁN — trên cùng luôn, trên cả cảnh báo mất kết nối.
     Ghế vừa cắm điện xong là thứ người đang đứng cạnh nó cần thấy đầu tiên; và chừng nào chưa
     gán mã thì nó KHÔNG vẽ được QR, tức là không thu được đồng nào. */
  if (D.choGan && D.choGan.length) {
    h += '<div class="note"><b>' + D.choGan.length + ' '
      + L('ghế vừa nối mạng, chưa có mã','chairs just came online with no code') + '</b> — '
      + L('ghế chưa gán mã thì không hiện được QR. Đặt mã ngắn (VD <code>AMTP01</code>): mã này đi '
          + 'vào nội dung chuyển khoản khách gõ tay.',
          'a chair with no code cannot show a QR. Give it a short code (e.g. <code>AMTP01</code>): '
          + 'this code goes into the transfer memo the customer types by hand.')
      + '<table style="margin-top:8px"><tr><th>MAC</th><th class="hide-sm">'
      + L('Tình trạng','Status') + '</th>'
      + '<th class="r">' + L('Gán mã + cơ sở','Assign code + branch') + '</th></tr>'
      + D.choGan.map(function(g, i){
          return '<tr><td><code>' + esc(g.mac) + '</code><br><span class="mut">' + esc(g.ma) + '</span></td>'
            + '<td class="hide-sm"><span class="pill ' + (g.song?'p-ok':'p-off') + '">'
            + (g.song?L('đang sống','online'):L('mất kết nối','offline')) + '</span></td>'
            + '<td class="r"><div class="act" style="justify-content:flex-end">'
            + '<input type="text" placeholder="AMTP01" data-gma="' + esc(g.ma) + '" style="width:96px">'
            + '<select data-gcs="' + esc(g.ma) + '"><option value="0">— '
            + L('cơ sở','branch') + ' —</option>'
            + (D.coso||[]).map(function(c){
                return '<option value="' + c.id + '">' + esc(c.ten) + '</option>'; }).join('')
            + '</select><button data-gan="' + esc(g.ma) + '">' + L('Gán','Assign')
            + '</button></div></td></tr>'; }).join('')
      + '</table></div>';
  }

  /* LUẬT 2: hỏng để TRÊN CÙNG, trên cả con số doanh thu. */
  var dut = D.may.filter(function(m){ return !m.song; });
  if (dut.length) {
    h += '<div class="warn"><b>' + dut.length + ' ' + L('ghế mất kết nối','chairs offline') + '</b> — '
      + esc(dut.map(function(m){ return m.ma + (m.coso ? ' (' + m.coso + ')' : ''); }).join(', '))
      + '. ' + L('Khách vẫn quét được tem QR trên ghế, tiền vẫn vào, nhưng ghế KHÔNG chạy.',
                 'Customers can still scan the QR sticker on the chair and the money still arrives, '
                 + 'but the chair will NOT run.') + '</div>';
  }
  if (D.cho.length) {
    h += '<div class="note"><b>' + D.cho.length + ' '
      + L('lượt đã trả tiền mà ghế chưa nhận','paid sessions the chair has not picked up') + '</b> — '
      + L('bình thường ghế lấy trong ~10 giây.','a chair normally picks one up within ~10 seconds.')
      + '<table style="margin-top:8px"><tr><th>' + L('Lúc','Time') + '</th><th>'
      + L('Ghế','Chair') + '</th>'
      + '<th class="r">' + L('Số tiền','Amount') + '</th></tr>'
      + D.cho.slice(0,8).map(function(c){
          return '<tr><td>' + esc(c.luc) + '</td><td>' + esc(c.ma_may) + '</td>'
            + '<td class="r">' + tien(c.so_tien) + '</td></tr>'; }).join('')
      + '</table></div>';
  }

  if (TAB === 'dieu-khien') { h += veDieuKhien() + '</div>'; app.innerHTML = h; noi(); return; }
  if (TAB === 'thu-tien')   { h += veThuTien()   + '</div>'; app.innerHTML = h; noi(); return; }
  if (TAB === 'kich-hoat')  { h += veKichHoat()  + '</div>'; app.innerHTML = h; noi(); return; }
  if (TAB === 'ma')        { h += veMa()        + '</div>'; app.innerHTML = h; noi(); return; }

  h += '<div class="kpis">'
    + kpi(L('Tổng doanh thu','Total revenue'), tien(t.tong), t.so_luot + ' ' + L('lượt','sessions'), 'a')
    + kpi(L('Chuyển khoản (QR)','Bank transfer (QR)'), tien(t.qr), t.qr_luot + ' ' + L('lượt','sessions'), 'b')
    + kpi(L('Tiền mặt','Cash'), tien(t.tien_mat), t.tien_mat_luot + ' ' + L('lượt','sessions'), 'c')
    + kpi(L('Đang chờ ghế nhận','Waiting for a chair'), String(D.cho.length),
        L('đã trả, chưa chạy','paid, not started'), 'd')
    + '</div>';

  // --- tình trạng ghế: chỉ LIỆT KÊ ở tab đối soát; bấm nút thì sang tab Điều khiển
  h += '<div class="card"><h2>' + L('Tình trạng ghế','Chair status') + '</h2><table><tr><th>'
    + L('Ghế','Chair') + '</th><th class="hide-sm">' + L('Cơ sở','Branch') + '</th>'
    + '<th>' + L('Trạng thái','State') + '</th><th class="r">' + L('Còn lại','Remaining') + '</th></tr>';
  if (!D.may.length) h += '<tr><td colspan="4" class="mut">'
    + L('Chưa khai ghế nào.','No chairs registered yet.') + '</td></tr>';
  D.may.forEach(function(m){
    var p = trangThai(m);
    h += '<tr><td><b>' + esc(m.ma) + '</b></td>'
      + '<td class="hide-sm">' + esc(m.coso || L('(chưa gán)','(unassigned)')) + '</td>'
      + '<td><span class="pill ' + p[0] + '">' + p[1] + '</span></td>'
      + '<td class="r">' + (m.tt === 'running' && m.song ? mmss(m.con_lai) : '') + '</td></tr>';
  });
  h += '</table><p class="mut" style="margin:9px 0 0">'
    + L('Bật/tắt ghế ở tab <b>🎛 Điều khiển ghế</b>.',
        'Turn chairs on/off in the <b>🎛 Chair control</b> tab.') + '</p></div>';

  /* Hai bảng tổng hợp trong một khối: trên màn rộng chúng nằm cạnh nhau (xem .doi trong CSS),
     trên điện thoại vẫn xếp dọc như cũ. */
  h += '<div class="doi">';
  h += bang(L('Theo cơ sở','By branch'),
    [L('Cơ sở','Branch'),L('Lượt','Sessions'),'QR',L('Tiền mặt','Cash'),L('Tổng','Total')],
    Object.keys(t.theo_coso).map(function(k){ var c = t.theo_coso[k];
      return ['<b>' + esc(c.coso) + '</b>', c.so_luot, tien(c.qr), tien(c.tien_mat), '<b>' + tien(c.tong) + '</b>']; }));
  h += bang(L('Theo ghế','By chair'),
    [L('Ghế','Chair'),L('Cơ sở','Branch'),L('Lượt','Sessions'),'QR',L('Tiền mặt','Cash'),L('Tổng','Total')],
    Object.keys(t.theo_may).map(function(k){ var m = t.theo_may[k];
      return ['<b>' + esc(m.may) + '</b>', esc(m.coso), m.so_luot, tien(m.qr), tien(m.tien_mat), '<b>' + tien(m.tong) + '</b>']; }));
  h += '</div>';

  // --- giao dịch
  h += bang(L('Giao dịch gần đây','Recent transactions'),
    [L('Thời gian','Time'),L('Ghế','Chair'),L('Nguồn','Source'),L('Nội dung','Memo'),L('Số tiền','Amount')],
    D.gd.map(function(g){
      return [esc(g.luc), esc(g.may || '—'),
        '<span class="pill ' + (g.nguon === 'cash' ? 'p-ok' : 'p-wait') + '">'
          + (g.nguon === 'cash' ? L('Tiền mặt','Cash') : String(g.nguon).toUpperCase()) + '</span>',
        '<span class="mut">' + esc(g.noi_dung) + '</span>', tien(g.so_tien)]; }));

  h += '</div>';
  app.innerHTML = h;
  noi();
}

/* Trạng thái một ghế -> [lớp CSS, chữ]. MỘT chỗ duy nhất: hai tab cùng hiện trạng thái này,
   khai hai nơi là sớm muộn một tab nói "Rảnh" còn tab kia nói "Đang chạy". */
function trangThai(m){
  if (!m.song)              return ['p-off',L('Mất kết nối','Offline')];
  if (m.tt === 'running')   return ['p-run',L('Đang chạy','Running')];
  if (m.tt === 'wait_pay')  return ['p-wait',L('Chờ trả tiền','Awaiting payment')];
  return ['p-ok',L('Rảnh','Idle')];
}

/* TAB ĐIỀU KHIỂN — mỗi ghế một thẻ, không phải một hàng bảng.
   Người bấm đang đứng cạnh một con ghế cụ thể và cần thấy đúng nó, to và rõ; dò theo hàng
   trong bảng là nguồn của việc bấm nhầm sang ghế bên cạnh. */
function veDieuKhien(){
  if (!D.may.length) {
    return '<div class="card"><h2>' + L('Điều khiển ghế','Chair control') + '</h2><p class="mut">'
      + L('Chưa khai ghế nào. Cắm ghế lên là nó tự hiện ở khối <b>Ghế vừa nối mạng</b> trong tab Đối soát.',
          'No chairs registered yet. Power one on and it appears by itself under '
          + '<b>Chairs just came online</b> in the Reconciliation tab.') + '</p></div>';
  }
  var h = '<div class="card"><h2>' + L('Quản lý ghế · Điều khiển','Chair management · Control')
    + ' — ' + D.may.length + ' ' + L('ghế','chairs') + '</h2>'
    + '<p class="mut" style="margin:0 0 12px">'
    + L('Bật tay là <b>cho không một lượt</b> — hệ thống ghi lại ai bấm và lúc nào, để cuối tháng '
        + 'còn giải thích được vì sao một ghế chạy nhiều hơn số tiền thu.',
        'Turning a chair on by hand is <b>a free session</b> — the system records who pressed it and '
        + 'when, so at month end you can still explain why a chair ran more than it took in.')
    + '</p><div class="ghe-luoi">';

  D.may.forEach(function(m){
    var p = trangThai(m);
    var lop = !m.song ? ' dut' : (m.tt === 'running' ? ' chay' : '');
    h += '<div class="ghe' + lop + '">'
      + '<div class="ghe-dau"><span class="ghe-ma">' + esc(m.ma) + '</span>'
      + '<span class="pill ' + p[0] + '">' + p[1] + '</span></div>'
      + '<div class="ghe-cs">' + esc(m.coso || L('(chưa gán cơ sở)','(no branch)')) + '</div>';

    /* 🔴 CỤC NHẬN TIỀN HỎNG — nằm ngay trên thẻ ghế, không giấu trong wp-admin. Người phải chạy
       ra xem cái máy là người đứng quầy, mà họ không có tài khoản WordPress. Nói luôn PHẢI LÀM
       GÌ chứ không phải một cái mã lỗi: "lỗi ket" thì ai cũng chịu. */
    if (m.tm) {
      h += '<div class="ghe-tien-loi dang">⚠ '
        + L('Cục nhận tiền đang hỏng','Bill acceptor is faulty right now')
        + '<div>' + esc(L(m.tm_chu, m.tm_chu_en || m.tm_chu)) + '</div></div>';
    } else if (m.tm_cu) {
      h += '<div class="ghe-tien-loi cu">'
        + L('Cục nhận tiền đã hỏng lúc trước','Bill acceptor failed earlier')
        + '<div>' + esc(L(m.tm_chu, m.tm_chu_en || m.tm_chu)) + '</div></div>';
    }

    /* Số đếm ngược to: đó là thứ người đứng cạnh ghế nhìn để biết còn bao lâu. */
    if (m.tt === 'running' && m.song) {
      h += '<div class="ghe-dh" data-dh="' + esc(m.ma) + '">' + mmss(m.con_lai)
        + '</div><div class="mut">' + L('còn lại','remaining') + '</div>';
    } else if (!m.song) {
      h += '<div class="mut" style="margin:8px 0">'
        + L('Ghế không gửi nhịp. Khách vẫn quét được tem QR trên ghế, <b>tiền vẫn vào nhưng ghế '
            + 'không chạy</b>.',
            'The chair is not sending a heartbeat. Customers can still scan the QR sticker on it, '
            + '<b>the money still arrives but the chair will not run</b>.') + '</div>';
    } else if (m.cho > 0) {
      h += '<div class="mut" style="margin:8px 0">' + m.cho + ' '
        + L('lượt đã trả tiền đang chờ ghế nhận.','paid sessions waiting for the chair to pick up.')
        + '</div>';
    } else {
      h += '<div class="mut" style="margin:8px 0">' + L('Sẵn sàng','Ready') + ' · ' + tien(m.gia)
        + ' = ' + m.phut + ' ' + L('phút','min') + '</div>';
    }

    h += '<div class="ghe-hang"><label>' + L('Số phút','Minutes') + '</label>'
      + '<input type="number" min="1" max="60" value="' + m.phut + '" data-phut="' + esc(m.ma) + '">'
      + '<label>' + L('Tiền mặt','Cash') + '</label>'
      + '<input type="number" min="1000" step="1000" value="' + m.gia + '" data-tien="' + esc(m.ma) + '">'
      + '</div>';

    h += '<div class="ghe-nut">'
      + '<button class="b-bat" data-bat="' + esc(m.ma) + '">▶ ' + L('Bật','Start') + '</button>'
      + '<button class="b-tat" data-tat="' + esc(m.ma) + '">■ ' + L('Tắt','Stop') + '</button>'
      + '<button data-mat="' + esc(m.ma) + '">💵 ' + L('Thu tiền mặt','Collect cash') + '</button>'
      + '<button class="b-kd" data-kd="' + esc(m.ma) + '">⟳ ' + L('Khởi động lại','Reboot') + '</button>'
      + '</div></div>';
  });
  return h + '</div></div>';
}

/* ============================================================================================
 * TAB THU TIỀN.
 *
 * 🔴 CÓ HAI ĐƯỜNG TIỀN MẶT, VÀ BẢNG DOANH THU KHÔNG PHÂN BIỆT ĐƯỢC CHÚNG:
 *      · "Ghế nuốt"  — khách nhét tờ tiền vào máy, ghế chạy ngay. Tiền còn nằm trong ghế.
 *      · "Người thu" — người đi thu mở ngăn, đếm được bao nhiêu ghi bấy nhiêu.
 *    Ghế có cục nhận tiền chạy tốt MÀ người thu vẫn bấm "Thu tiền mặt" là CỘNG ĐÔI: cùng một
 *    xấp tiền vào sổ hai lần. Doanh thu tháng phồng lên mà không ai thấy, vì hai dòng nhìn
 *    giống hệt nhau trong bảng giao dịch.
 *
 *    Không cấm — có ghế không lắp cục nhận tiền, ở đó nút bấm tay là đường DUY NHẤT. Nên tab này
 *    tách hai loại ra và KÊU LÊN khi một ghế có cả hai trong cùng kỳ.
 * ============================================================================================ */
function veThuTien(){
  var t = (D.thu || { ds: [], may: [] });
  var mat_ghe = 0, mat_ng = 0, qr = 0, lan = 0, canh = [];
  t.may.forEach(function(m){
    mat_ghe += m.mat_ghe; mat_ng += m.mat_nguoi; qr += m.qr; lan += m.so_lan_thu;
    if (m.cong_doi) canh.push(m.may);
  });

  var h = '';
  if (canh.length) {
    h += '<div class="warn"><b>' + canh.length + ' '
      + L('ghế có CẢ hai đường tiền mặt trong kỳ này','chairs took cash through BOTH paths this period')
      + '</b> — ' + esc(canh.join(', ')) + '. '
      + L('Ghế vừa tự nuốt tiền, vừa có người bấm "Thu tiền mặt". Nếu đó là cùng một xấp tiền thì '
          + 'doanh thu đang <b>cộng đôi</b>. Ghế mới lắp cục nhận tiền giữa kỳ thì bình thường — '
          + 'soi bảng dưới rồi huỷ dòng thừa ở tab Đối soát.',
          'The chair swallowed notes itself AND someone pressed "Collect cash". If that is the same '
          + 'pile of money, revenue is <b>double counted</b>. It is normal if the acceptor was '
          + 'fitted mid-period — check the table below and cancel the extra rows in Reconciliation.')
      + '</div>';
  }

  h += '<div class="kpis">'
    + kpi(L('Ghế tự nuốt tiền','Chair took notes'), tien(mat_ghe),
        L('khách nhét vào máy','customer inserted'), 'c')
    + kpi(L('Người đi thu ghi sổ','Recorded by staff'), tien(mat_ng),
        lan + ' ' + L('lần bấm thu','collections'), 'a')
    + kpi(L('Chuyển khoản (QR)','Bank transfer (QR)'), tien(qr), '', 'b')
    + kpi(L('Tổng kỳ này','Period total'), tien(mat_ghe + mat_ng + qr), '', 'd')
    + '</div>';

  h += '<div class="card"><h2>' + L('Theo ghế','By chair') + '</h2><table><tr><th>'
    + L('Ghế','Chair') + '</th><th class="hide-sm">' + L('Cơ sở','Branch') + '</th>'
    + '<th class="r">' + L('Ghế nuốt','Acceptor') + '</th><th class="r">'
    + L('Người thu','Staff') + '</th><th class="r">QR</th><th class="r">'
    + L('Tổng','Total') + '</th></tr>';
  if (!t.may.length) h += '<tr><td colspan="6" class="mut">'
    + L('Chưa có đồng nào trong kỳ này.','No money in this period.') + '</td></tr>';
  t.may.forEach(function(m){
    h += '<tr><td><b>' + esc(m.may) + '</b>'
      + (m.cong_doi ? ' <span class="pill p-off">' + L('cần soi','check') + '</span>' : '') + '</td>'
      + '<td class="hide-sm">' + esc(m.coso || '—') + '</td>'
      + '<td class="r">' + tien(m.mat_ghe) + '</td>'
      + '<td class="r">' + tien(m.mat_nguoi) + '</td>'
      + '<td class="r">' + tien(m.qr) + '</td>'
      + '<td class="r"><b>' + tien(m.tong) + '</b></td></tr>';
  });
  h += '</table></div>';

  h += '<div class="card"><h2>' + L('Từng lượt tiền mặt','Every cash entry') + '</h2><table><tr><th>'
    + L('Lúc','Time') + '</th><th>' + L('Ghế','Chair') + '</th><th>' + L('Kiểu','Kind') + '</th>'
    + '<th class="hide-sm">' + L('Ai thu','Collected by') + '</th><th class="r">'
    + L('Số tiền','Amount') + '</th></tr>';
  if (!t.ds.length) h += '<tr><td colspan="5" class="mut">'
    + L('Chưa có lượt tiền mặt nào.','No cash entries yet.') + '</td></tr>';
  t.ds.forEach(function(r){
    var ng = r.kieu === 'nguoi';
    h += '<tr><td>' + esc(r.luc) + '</td><td><b>' + esc(r.ma_may || '—') + '</b></td>'
      + '<td><span class="pill ' + (ng ? 'p-run' : 'p-ok') + '">'
        + (ng ? L('người thu','staff') : L('ghế nuốt','acceptor')) + '</span></td>'
      + '<td class="hide-sm">' + esc(r.nguoi || '—') + '</td>'
      + '<td class="r">' + tien(r.so_tien) + '</td></tr>';
  });
  return h + '</table></div>';
}

/* ============================================================================================
 * TAB MÃ GIẢM GIÁ — quản lý mã đã bán.
 *
 * Bốn câu hỏi thật ở quầy, theo đúng thứ tự người ta hỏi:
 *   1. "Kỳ này bán được bao nhiêu mã, thu bao nhiêu?"  -> ô tổng
 *   2. "Đang nợ khách bao nhiêu?"                       -> ô nợ, tô riêng
 *   3. "Khách này quên PIN, còn mã nào không?"          -> ô tra theo số điện thoại
 *   4. "Mã này sao lại không dùng được?"                -> bảng, có cột đã dùng / đã huỷ
 * ============================================================================================ */
var MA_TRA = null;   // kết quả tra theo số điện thoại (null = chưa tra)

function veMa(){
  var M = D.ma || { tong:{ban:0,thu:0,menh:0,da_dung:0}, no:{so_ma:0,tong:0,da_thu:0}, ds:[], quyen_huy:0 };
  var h = '<div class="kpis">'
    + kpi(L('Bán trong kỳ','Sold this period'), String(M.tong.ban) + ' ' + L('mã','codes'),
        tien(M.tong.thu) + ' ' + L('đã thu','collected'), 'a')
    + kpi(L('Đã dùng','Redeemed'), String(M.tong.da_dung) + ' ' + L('mã','codes'), '', 'c')
    /* Khoản NỢ tô riêng: mã không hết hạn nên con số này chỉ cộng lên và không bao giờ tự đóng.
       Mỗi mã chưa dùng là một lượt massage còn nợ khách. */
    + kpi(L('ĐANG NỢ KHÁCH','OWED TO CUSTOMERS'), tien(M.no.tong),
        M.no.so_ma + ' ' + L('mã chưa dùng','unused codes'), 'd')
    + '</div>';

  /* ══════════════════════════════════════════════════════════════════════════════════════════
   * VÍ KHÁCH — đứng ngay dưới ô mã, không tách tab.
   *
   * 🔴 TỔNG NỢ THẬT = nợ mã + nợ ví. Nhìn riêng một vế là thấy một nửa sự thật, và cái nửa
   *    thiếu luôn là nửa làm con số đẹp lên. Nên hiện luôn cả tổng.
   * ═════════════════════════════════════════════════════════════════════════════════════════ */
  var V = D.vi || { no:{dung:0,cho:0,tong:0,so_vi:0}, ds:[], co_ban:0 };
  if (V.co_ban || V.no.tong > 0 || V.ds.length) {
    h += '<div class="kpis">'
      + kpi(L('SỐ DƯ VÍ KHÁCH','CUSTOMER WALLET BALANCE'), tien(V.no.tong),
          V.no.so_vi + ' ' + L('ví','wallets')
            + (V.no.cho > 0 ? ' · ' + tien(V.no.cho) + ' ' + L('đang chờ','on hold') : ''), 'd')
      + kpi(L('TỔNG NỢ KHÁCH','TOTAL OWED'), tien(M.no.tong + V.no.tong),
          L('mã chưa dùng + số dư ví','unused codes + wallet balance'), 'd')
      + '</div>';
  }

  h += '<div class="card"><h2>' + L('Khách quên PIN — tra hộ','Customer forgot PIN — look up')
    + '</h2><p class="mut" style="margin:0 0 10px">'
    + L('Nhập số điện thoại khách mua. Chỉ nhân viên tra được kiểu này — trang của khách vẫn '
        + 'phải có PIN.',
        'Enter the phone number the customer bought with. Only staff can look up this way — the '
        + 'customer page still requires the PIN.') + '</p>'
    + '<div class="act"><input id="ma-sdt" placeholder="0909 123 456" style="max-width:220px">'
    + '<button id="ma-tra" class="on">' + L('Tra','Look up') + '</button></div>'
    + '<div class="err" id="ma-e"></div>';
  if (MA_TRA) {
    /* Ví hiện TRƯỚC bảng mã: nếu khách có ví thì đó thường là thứ họ đang hỏi. */
    if (MA_TRA.vi) {
      h += '<div class="ok" style="margin-top:10px">'
        + L('Số dư ví tiêu được: ','Wallet available: ') + '<b>' + tien(MA_TRA.vi.dung) + '</b>'
        + (MA_TRA.vi.cho > 0
            ? '<br>⏳ ' + tien(MA_TRA.vi.cho) + ' ' + L('đang trong hạn chờ','on hold')
              + (MA_TRA.vi.con_cho > 0
                  ? ' — ' + L('dùng được sau ','available in ') + docCho(MA_TRA.vi.con_cho) : '')
            : '')
        + (MA_TRA.vi.khoa ? '<br><b style="color:#ff6b6b">' + L('VÍ ĐANG KHOÁ','WALLET LOCKED') + '</b>' : '')
        + '</div>';
    }
    if (!MA_TRA.ds.length) {
      h += '<p class="mut" style="margin-top:10px">'
        + (MA_TRA.vi ? L('Số này không có mã lẻ nào (chỉ có ví).','No individual codes (wallet only).')
                     : L('Số này chưa mua mã, cũng chưa có ví.','No codes and no wallet for this number.'))
        + '</p>';
    } else {
      h += bangMa(MA_TRA.ds, M.quyen_huy, true);
    }
  }
  h += '</div>';

  /* ══════════════════════════════════════════════════════════════════════════════════════════
   * QUÀ CHỜ TRAO — ĐỨNG TRƯỚC BẢNG VÍ.
   *
   * Đây là VIỆC PHẢI LÀM của người đang đứng quầy, còn bảng ví là số liệu để tra. Việc phải làm
   * thì đứng trước; số liệu để tra thì đứng sau.
   * ═════════════════════════════════════════════════════════════════════════════════════════ */
  var Q = D.qua || { cho:[], tong:{so:0,tien:0,cho:0}, bat:0 };
  if (Q.cho.length) {
    h += '<div class="card"><h2>🎁 ' + L('Quà chờ trao','Gifts to hand over')
      + ' (' + Q.cho.length + ')</h2>'
      + '<p class="mut" style="margin:0 0 10px">'
      + L('Khách đọc số điện thoại — đối chiếu bốn số cuối rồi bấm Đã trao.',
          'The customer gives their phone number — match the last 4 digits, then tap Handed over.')
      + '</p><table><thead><tr><th>' + L('Số điện thoại','Phone') + '</th>'
      + '<th>' + L('Phần quà','Gift') + '</th>'
      + '<th>' + L('Đủ mốc lúc','Earned at') + '</th><th></th></tr></thead><tbody>';
    Q.cho.forEach(function(q){
      h += '<tr><td><b>' + esc(q.sdt_che) + '</b></td>'
        + '<td>' + esc(q.ghi_chu || L('Quà tri ân','Loyalty gift'))
        + ' <span class="mut">(' + Lf2(L('đủ {0} lượt','{0} stamps'), q.moc) + ')</span></td>'
        + '<td class="mut">' + esc(String(q.tao_luc).slice(0,16)) + '</td>'
        + '<td><button class="on" data-trao="' + q.id + '">'
        + L('Đã trao','Handed over') + '</button></td></tr>';
    });
    h += '</tbody></table></div>';
  }

  /* Danh sách ví còn tiền — nợ nằm ở đâu, ai giữ nhiều nhất.
     ⚠️ Số điện thoại đã CHE từ máy chủ (VHG_Ma::sdt_che). Màn này nhân viên ca nào cũng mở;
        in đủ số là biến bảng tiền thành danh bạ khách hàng, bôi đen là chép được cả nghìn số. */
  if (V.ds.length) {
    h += '<div class="card"><h2>' + L('Ví khách còn tiền','Wallets with balance') + '</h2>'
      /* ══════════════════════════════════════════════════════════════════════════════════
       * 🔴 THỨ TỰ CỘT LÀ MỘT PHÉP TÍNH, VÀ PHẢI ĐỌC RA ĐƯỢC.
       *
       * Anh Thắng 23/08/2026: *"Số tiền đang chờ và số tiền đã tiêu cộng lại sai với số đã
       * nạp"*. Con số KHÔNG sai — 30.000 + 120.000 + 90.000 = 240.000, khớp đúng. Anh cộng
       * hai cột và thiếu cột thứ ba.
       *
       * Nhưng đó là lỗi của cái bảng, không phải của người đọc. Bảng cũ xếp "Đã nạp" nằm GIỮA
       * "Đang chờ" và "Đã tiêu" — đặt một con số TỔNG vào giữa hai số HẠNG thì mắt tự nối hai
       * cái cạnh nhau lại rồi so với nó, và tất nhiên là lệch.
       *
       * Nay tổng đứng TRƯỚC, và tiêu đề mang luôn dấu `=` `+` `+`. Đọc từ trái sang là ra
       * đúng phép tính, không phải đoán.
       * ═════════════════════════════════════════════════════════════════════════════════════ */
      + '<table><thead><tr><th>' + L('Số điện thoại','Phone') + '</th>'
      + '<th>' + L('Đã nạp','Topped up') + '</th>'
      + '<th>= ' + L('Tiêu được','Available') + '</th>'
      + '<th>+ ' + L('Đang chờ','On hold') + '</th>'
      + '<th>+ ' + L('Đã tiêu','Spent') + '</th>'
      + '<th>' + L('Tình trạng','Status') + '</th></tr></thead><tbody>';
    V.ds.forEach(function(v){
      /* 🔴 TỰ KIỂM NGAY TRÊN BẢNG. Ba số hạng phải cộng đúng bằng tổng; lệch là có chuyện với
         tiền của khách, và phải HIỆN RA chứ không phải để ai đó tình cờ nhẩm ra. */
      var lech = (v.so_du_dung + v.so_du_cho + v.da_tieu) - v.da_nap;
      h += '<tr><td>' + esc(v.sdt_che) + '</td>'
        + '<td><b>' + tien(v.da_nap) + '</b>'
        + (lech !== 0 ? '<br><b style="color:#ff6b6b">' + L('lệch ','off by ') + tien(lech) + '</b>' : '')
        + '</td>'
        + '<td>' + tien(v.so_du_dung) + '</td>'
        + '<td>' + (v.so_du_cho > 0 ? tien(v.so_du_cho) : '—') + '</td>'
        + '<td>' + tien(v.da_tieu) + '</td>'
        + '<td>' + (v.khoa ? '<b style="color:#ff6b6b">' + L('ĐANG KHOÁ','LOCKED') + '</b>'
                           : '<span class="mut">' + L('bình thường','normal') + '</span>') + '</td></tr>';
    });
    h += '</tbody></table>'
      + '<p class="mut" style="margin:8px 0 0">'
      + L('<b>Đã nạp</b> = tổng mọi khoản CỘNG vào ví (gồm cả hoàn tiền và chỉnh tay), '
          + 'nên nó luôn bằng <b>tiêu được + đang chờ + đã tiêu</b>. Lệch là có chuyện — bảng sẽ báo đỏ.',
          '<b>Topped up</b> = every credit to the wallet (including refunds and manual adjustments), '
          + 'so it always equals <b>available + on hold + spent</b>. Any mismatch is flagged in red.')
      + '</p></div>';
  }

  h += '<div class="card"><h2>' + L('Mã đã bán trong kỳ','Codes sold this period') + '</h2>'
    + (M.ds.length ? bangMa(M.ds, M.quyen_huy, false)
        : '<p class="mut">' + L('Chưa bán mã nào trong kỳ này.','No codes sold in this period.')
          + '</p>')
    + '</div>';
  return h;
}

function bangMa(ds, quyen, hien_sdt){
  var h = '<table style="margin-top:8px"><tr><th>' + L('Mã','Code') + '</th>'
    + (hien_sdt ? '' : '<th class="hide-sm">' + L('Số ĐT','Phone') + '</th>')
    + '<th class="r">' + L('Mệnh giá','Value') + '</th><th class="r hide-sm">'
    + L('Khách trả','Paid') + '</th><th>' + L('Tình trạng','Status') + '</th>'
    + (quyen ? '<th></th>' : '') + '</tr>';
  ds.forEach(function(m){
    var tt, lop;
    if (m.huy)           { tt = L('đã huỷ','cancelled') + (m.huy_ly_do ? ' · ' + esc(m.huy_ly_do) : ''); lop = 'p-off'; }
    else if (m.dung_luc) { tt = L('đã dùng','used') + ' ' + esc(m.dung_luc)
                              + (m.dung_may ? ' · ' + esc(m.dung_may) : ''); lop = 'p-wait'; }
    else                 { tt = L('còn dùng được','usable'); lop = 'p-ok'; }
    h += '<tr><td><b style="font-variant-numeric:tabular-nums">' + esc(m.ma) + '</b>'
      + '<br><span class="mut">' + esc(m.tao_luc) + '</span></td>'
      + (hien_sdt ? '' : '<td class="hide-sm">' + esc(m.sdt) + '</td>')
      + '<td class="r">' + tien(m.menh_gia) + '</td>'
      + '<td class="r hide-sm">' + tien(m.gia_ban)
        + (m.giam_pt ? '<br><span class="mut">-' + m.giam_pt + '%</span>' : '') + '</td>'
      + '<td><span class="pill ' + lop + '">' + tt + '</span></td>';
    /* Nút huỷ CHỈ hiện cho mã còn dùng được. Mã đã dùng thì ghế chạy rồi — đánh dấu huỷ lúc đó
       là sổ nói dối theo hướng có lợi cho mình. */
    if (quyen) {
      h += '<td class="r">' + ( (!m.huy && !m.dung_luc)
        ? '<button data-mahuy="' + esc(m.ma) + '">' + L('Huỷ','Cancel') + '</button>' : '' ) + '</td>';
    }
    h += '</tr>';
  });
  return h + '</table>';
}

/* TAB KÍCH HOẠT GHẾ — ghế nào đã bật tay, mấy lần, tổng bao lâu, và vì sao. */
function veKichHoat(){
  var b = D.bat || { ky:{so_lan:0,tong_phut:0,so_ghe:0}, thang:{so_lan:0,tong_phut:0},
                     ngay:[], may:[], ds:[] };
  var h = '<div class="kpis">'
    + kpi(L('Kỳ đang xem','Selected period'), String(b.ky.so_lan) + ' ' + L('lần','times'),
        b.ky.tong_phut + ' ' + L('phút · trên','min · across') + ' ' + b.ky.so_ghe + ' '
        + L('ghế','chairs'), 'a')
    + kpi(L('TỔNG THÁNG NÀY','TOTAL THIS MONTH'), String(b.thang.so_lan) + ' ' + L('lần','times'),
        b.thang.tong_phut + ' ' + L('phút','min'), 'd')
    + '</div>';

  h += '<div class="card"><h2>' + L('Ghế đã kích hoạt','Chairs activated') + '</h2>'
    + '<p class="mut" style="margin:0 0 10px">'
    + L('Mỗi lần bấm Bật là <b>cho không một lượt</b>: ghế chạy, điện tốn, mà sổ doanh thu không '
        + 'có đồng nào. Một ghế đứng đầu bảng tháng này qua tháng khác thì hoặc nó hỏng thật, '
        + 'hoặc có người đang quen tay — cả hai đều đáng biết.',
        'Every Start press is <b>a free session</b>: the chair runs, power is spent, and the revenue '
        + 'book shows nothing. A chair that tops this table month after month is either genuinely '
        + 'faulty or someone has got into the habit — both are worth knowing.') + '</p>';
  h += '<table><tr><th>' + L('Ghế','Chair') + '</th><th class="hide-sm">' + L('Cơ sở','Branch')
    + '</th><th class="r">' + L('Số lần','Times') + '</th><th class="r">'
    + L('Tổng phút','Total minutes') + '</th><th class="r hide-sm">' + L('Lần cuối','Last')
    + '</th></tr>';
  if (!b.may.length) h += '<tr><td colspan="5" class="mut">'
    + L('Chưa ghế nào được bật tay trong kỳ này.','No chair was started by hand in this period.')
    + '</td></tr>';
  b.may.forEach(function(m){
    h += '<tr><td><b>' + esc(m.ma) + '</b></td><td class="hide-sm">' + esc(m.coso || '—') + '</td>'
      + '<td class="r">' + m.so_lan + '</td><td class="r"><b>' + m.tong_phut + '</b></td>'
      + '<td class="r hide-sm"><span class="mut">' + esc(m.lan_cuoi || '—') + '</span></td></tr>';
  });
  h += '</table></div>';

  if (b.ngay.length) {
    h += '<div class="card"><h2>' + L('Theo ngày','By day') + '</h2><table><tr><th>'
      + L('Ngày','Day') + '</th><th class="r">' + L('Số lần','Times') + '</th><th class="r">'
      + L('Tổng phút','Total minutes') + '</th></tr>';
    b.ngay.forEach(function(n){
      h += '<tr><td>' + esc(n.ngay) + '</td><td class="r">' + n.so_lan + '</td>'
        + '<td class="r"><b>' + n.tong_phut + '</b></td></tr>';
    });
    h += '</table></div>';
  }

  h += '<div class="card"><h2>' + L('Nhật ký kích hoạt','Activation log') + '</h2><table><tr><th>'
    + L('Lúc','Time') + '</th><th>' + L('Ghế','Chair') + '</th><th class="hide-sm">'
    + L('Ai bấm','Pressed by') + '</th><th>' + L('Lý do','Reason') + '</th><th class="r">'
    + L('Phút','Min') + '</th></tr>';
  if (!b.ds.length) h += '<tr><td colspan="5" class="mut">'
    + L('Chưa có lượt nào.','Nothing yet.') + '</td></tr>';
  b.ds.forEach(function(l){
    /* Lệnh ghế CHƯA LẤY phải hiện khác: "đã chạy" và "sẽ chạy khi ghế lên mạng" là hai thứ khác
       nhau khi đang đứng đối chiếu với sổ. */
    h += '<tr><td>' + esc(l.luc)
      + (l.da_gui ? '' : '<br><span class="pill p-wait">' + L('ghế chưa lấy','not picked up')
          + '</span>') + '</td>'
      + '<td><b>' + esc(l.ma) + '</b></td>'
      + '<td class="hide-sm">' + esc(l.nguoi || '—') + '</td>'
      + '<td>' + esc(l.ly_do || '—') + '</td>'
      + '<td class="r">' + l.phut + '</td></tr>';
  });
  return h + '</table></div>';
}

function kpi(lb, vl, sb, m){
  return '<div class="kpi"><div class="lb">' + lb + '</div><div class="vl ' + m + '">' + vl
    + '</div><div class="sb">' + sb + '</div></div>';
}
function bang(ten, cot, hang){
  var h = '<div class="card"><h2>' + ten + '</h2><table><tr>'
    + cot.map(function(c,i){ return '<th' + (i>=cot.length-3?' class="r"':'') + '>' + c + '</th>'; }).join('')
    + '</tr>';
  if (!hang.length) h += '<tr><td colspan="' + cot.length + '" class="mut">'
    + L('Chưa có số liệu kỳ này.','No data for this period.') + '</td></tr>';
  hang.forEach(function(r){
    h += '<tr>' + r.map(function(o,i){ return '<td' + (i>=cot.length-3?' class="r"':'') + '>' + o + '</td>'; }).join('') + '</tr>';
  });
  return h + '</table></div>';
}

/* ============================================================================================
 * BẢNG CHỐT CA THU TIỀN.
 *
 * 🔴 Bản trước bấm "Thu tiền mặt" là hỏi "ghi 10.000đ?" rồi ghi luôn. Sai với việc thật: người
 *    đi thu tiền mở ngăn ghế ra, đếm được một xấp, và cần biết HỆ THỐNG NGHĨ là bao nhiêu để
 *    đối chiếu. Không có con số đó thì họ gõ đại số mình đếm được, và chênh lệch — nếu có —
 *    không bao giờ lộ ra.
 *
 * Nên: hiện số liệu TRƯỚC, nhập tiền SAU. Và hiện cả QR lẫn tổng tháng, vì câu hỏi thật lúc
 * đứng ở cửa hàng là "ghế này tháng này ra bao nhiêu", không phải "hôm nay bao nhiêu".
 * ============================================================================================ */
var CHOT = null;   // { ma_may, so } — bảng đang mở

function moChotCa(ma){
  if (ban) return;
  goi('so_may', { ma_may: ma }, function(r){
    if (!r.ok) { alert(r.error || L('Không lấy được số liệu ghế.','Could not load chair figures.')); return; }
    CHOT = { ma: ma, so: r, go: '' };
    veChotCa();
  });
}

function hangSo(nhan, gt, lop){
  return '<div class="so-hang' + (lop||'') + '"><span class="nh">' + nhan + '</span>'
    + '<span class="gt">' + gt + '</span></div>';
}

function veChotCa(){
  var r = CHOT.so, cu = document.getElementById('man-chot');
  if (cu) cu.remove();
  var d = document.createElement('div');
  d.className = 'man'; d.id = 'man-chot';
  d.innerHTML = '<div class="hop">'
    + '<h3>' + L('Thu tiền mặt','Collect cash') + ' — ' + esc(CHOT.ma) + '</h3>'
    + '<div class="cs">' + esc(r.coso || L('(chưa gán cơ sở)','(no branch)')) + '</div>'
    + hangSo(L('Tiền mặt hôm nay','Cash today'), tien(r.hom_nay.tien_mat))
    + hangSo(L('Chuyển khoản (QR) hôm nay','Bank transfer (QR) today'), tien(r.hom_nay.qr))
    + hangSo(L('Tổng hôm nay','Total today') + ' · ' + r.hom_nay.so_luot + ' '
        + L('lượt','sessions'), tien(r.hom_nay.tong))
    + hangSo(L('Tiền mặt tháng này','Cash this month'), tien(r.thang.tien_mat))
    + hangSo(L('Chuyển khoản (QR) tháng này','Bank transfer (QR) this month'), tien(r.thang.qr))
    + hangSo(L('TỔNG THÁNG NÀY','TOTAL THIS MONTH') + ' · ' + r.thang.so_luot + ' '
        + L('lượt','sessions'), tien(r.thang.tong), ' to')
    + '<div class="o-thu"><label class="mut">'
    + L('Số tiền mặt đã đếm được','Cash counted') + '</label>'
    + '<input id="chot-tien" type="text" inputmode="numeric" value="' + esc(CHOT.go) + '" placeholder="0"></div>'
    + '<div class="phim">'
    + ['1','2','3','4','5','6','7','8','9','000','0','⌫'].map(function(k){
        return '<button data-phim="' + k + '">' + k + '</button>'; }).join('')
    + '</div>'
    /* ⚠️ Nói thẳng: đây là GHI SỔ, không phải mở ngăn tiền. Người bấm tưởng nó mở khoá ghế thì
       họ bấm rồi đứng đợi, và bấm lại — mỗi lần bấm là một dòng doanh thu. */
    + '<div class="canh">'
    + L('Nút này <b>ghi sổ</b> số tiền mặt đã thu, không mở ngăn tiền của ghế. Bấm một lần thôi — '
        + 'mỗi lần bấm là một dòng doanh thu.',
        'This button <b>records</b> the cash you collected; it does not open the chair\'s cash box. '
        + 'Press it once — every press is another revenue entry.') + '</div>'
    + '<div class="hop-nut">'
    + '<button id="chot-huy" class="ghost">' + L('Thoát','Cancel') + '</button>'
    + '<button id="chot-ok" class="on">' + L('Xác nhận thu','Confirm') + '</button>'
    + '</div></div>';
  document.body.appendChild(d);

  var o = document.getElementById('chot-tien');
  [].forEach.call(d.querySelectorAll('[data-phim]'), function(b){
    b.onclick = function(){
      var k = b.getAttribute('data-phim');
      var v = (o.value || '').replace(/\D/g, '');
      if (k === '⌫') v = v.slice(0, -1); else v = (v + k).slice(0, 12);
      CHOT.go = v;
      o.value = v ? Number(v).toLocaleString('vi-VN') : '';
    };
  });
  o.addEventListener('input', function(){
    var v = (o.value || '').replace(/\D/g, '').slice(0, 12);
    CHOT.go = v;
    o.value = v ? Number(v).toLocaleString('vi-VN') : '';
  });
  document.getElementById('chot-huy').onclick = dongChotCa;
  d.onclick = function(ev){ if (ev.target === d) dongChotCa(); };
  document.getElementById('chot-ok').onclick = function(){
    var v = Number((o.value || '').replace(/\D/g, '')) || 0;
    if (v <= 0) { alert(L('Chưa nhập số tiền mặt đã đếm được.','Enter the cash amount you counted.'));
      o.focus(); return; }
    if (!confirm(L('Ghi ' + v.toLocaleString('vi-VN') + 'đ tiền mặt cho ghế ' + CHOT.ma + '?',
        'Record ' + v.toLocaleString('vi-VN') + 'đ cash for chair ' + CHOT.ma + '?'))) return;
    dongChotCa();
    lam('tien_mat', { ma_may: CHOT.ma, so_tien: v });
  };
  o.focus();
}

function dongChotCa(){
  var d = document.getElementById('man-chot');
  if (d) d.remove();
  CHOT = null;
}

/* Đồng hồ dải đầu trang. Mốc là giờ MÁY CHỦ (`D.luc`), sau đó tự tích từng giây — lấy giờ điện
   thoại thì nó lệch với mọi con số khác trên trang, mà hai loại giờ cạnh nhau là mời người ta
   đối chiếu nhầm. Mỗi lượt hỏi máy chủ lại đặt mốc, nên nó không trôi được. */
var dhTop = null;
function chayDongHoTop(){
  if (dhTop) { clearInterval(dhTop); dhTop = null; }
  var o = document.getElementById('dh-top');
  if (!o || !D || !D.luc) return;
  var m = String(D.luc).match(/(\d{1,2}):(\d{2})(?::(\d{2}))?/);
  if (!m) return;
  var g = Number(m[1]), p = Number(m[2]), gi = Number(m[3] || 0);
  var dau = String(D.luc).slice(0, m.index);
  function ve2(n){ return String(n).padStart(2,'0'); }
  dhTop = setInterval(function(){
    if (document.hidden) return;
    gi++; if (gi > 59) { gi = 0; p++; }
    if (p > 59) { p = 0; g = (g + 1) % 24; }
    o.textContent = dau + ve2(g) + ':' + ve2(p) + ':' + ve2(gi);
  }, 1000);
}

function noi(){
  henLai();
  chayDongHo();
  chayDongHoTop();
  noiNN();
  document.getElementById('lam-moi').onclick = function(){ tai(); };
  document.getElementById('thoat').onclick = function(){
    goi('logout', {}, function(){ TOK = null; try{localStorage.removeItem('vhg_tok');}catch(e){} veLogin(''); });
  };
  [].forEach.call(document.querySelectorAll('[data-ky]'), function(b){
    b.onclick = function(){ KY = b.getAttribute('data-ky'); tai(); };
  });
  [].forEach.call(document.querySelectorAll('[data-tab]'), function(b){
    b.onclick = function(){
      TAB = b.getAttribute('data-tab');
      try { localStorage.setItem('vhg_tab', TAB); } catch(e) {}
      /* Vẽ lại từ dữ liệu ĐANG CÓ, không gọi lại máy chủ: đổi tab không phải đổi dữ liệu, và
         trên 4G mỗi lượt gọi thừa là một lần chờ. */
      ve();
    };
  });
  [].forEach.call(document.querySelectorAll('[data-kd]'), function(b){
    b.onclick = function(){
      var m = b.getAttribute('data-kd');
      if (!confirm(L(
        'Khởi động lại ghế ' + m + '?\n\nGhế sẽ tự khởi động khi đang RẢNH — nếu có khách đang '
          + 'massage thì nó chờ hết lượt rồi mới khởi động, không cắt ngang.\n'
          + 'Sau khi khởi động, ghế mất khoảng 30 giây mới gửi nhịp lại.',
        'Reboot chair ' + m + '?\n\nThe chair reboots itself once it is IDLE — if someone is in it, '
          + 'it waits for the session to finish and does not cut in.\n'
          + 'After rebooting it takes about 30 seconds to send a heartbeat again.'))) return;
      lam('khoi_dong_lai', { ma_may: m });
    };
  });
  function so(attr, ma){
    var el = document.querySelector('[' + attr + '="' + ma + '"]');
    return el ? el.value : 0;
  }
  /* ⚠️ CHẶN BẤM HAI LẦN. Trên 4G một lượt bấm có thể mất 3 giây không thấy gì xảy ra, và phản
     xạ của mọi người là bấm lại. Với "Thu tiền mặt" thì bấm hai lần là GHI HAI LẦN — số tiền
     thật vào sổ gấp đôi. Khoá nút cho tới khi máy chủ trả lời. */
  function lam(viec, d){
    if (ban) return;
    ban = true;
    [].forEach.call(document.querySelectorAll('button'), function(b){ b.disabled = true; });
    goi(viec, d, function(r){
      ban = false;
      if (r && r.ok === false && r.error) alert(r.error);
      else if (r && r.thong_bao) alert(r.thong_bao);
      tai();
    });
  }
  [].forEach.call(document.querySelectorAll('[data-bat]'), function(b){
    b.onclick = function(){ var m = b.getAttribute('data-bat');
      var ly = prompt(L('Bật ghế ' + m + ' — đây là CHO KHÔNG một lượt.\nLý do:',
        'Start chair ' + m + ' — this is a FREE session.\nReason:')); if (ly === null) return;
      lam('bat', { ma_may: m, phut: so('data-phut', m), ly_do: ly }); };
  });
  [].forEach.call(document.querySelectorAll('[data-tat]'), function(b){
    b.onclick = function(){ var m = b.getAttribute('data-tat');
      if (!confirm(L('Tắt ghế ' + m + ' ngay?','Stop chair ' + m + ' now?'))) return;
      lam('tat', { ma_may: m }); };
  });
  [].forEach.call(document.querySelectorAll('[data-gan]'), function(b){
    b.onclick = function(){
      var cu = b.getAttribute('data-gan');
      var o  = document.querySelector('[data-gma="' + cu + '"]');
      var cs = document.querySelector('[data-gcs="' + cu + '"]');
      var moi = (o && o.value || '').trim();
      if (!moi) { alert(L('Chưa nhập mã ghế.','Enter a chair code.')); if (o) o.focus(); return; }
      /* Chặn ngay trên máy: mã đi vào nội dung chuyển khoản khách GÕ TAY, có dấu hay khoảng
         trắng là khách gõ sai và ghế không chạy. Máy chủ cũng chặn — chặn hai lớp vì câu báo
         lỗi ở đây tới ngay, còn đi một vòng máy chủ thì trên 4G là vài giây đứng nhìn. */
      if (!/^[A-Za-z0-9]{1,20}$/.test(moi)) {
        alert(L('Mã chỉ được gồm chữ và số, không dấu, không khoảng trắng.',
          'The code may contain letters and digits only — no accents, no spaces.')); return;
      }
      lam('gan_ma', { ma_cu: cu, ma_moi: moi, coso_id: cs ? cs.value : 0 });
    };
  });
  [].forEach.call(document.querySelectorAll('[data-mat]'), function(b){
    b.onclick = function(){ moChotCa(b.getAttribute('data-mat')); };
  });

  /* 🔴 TRAO QUÀ: bấm xong TẢI LẠI danh sách, kể cả khi hỏng. Hỏng ở đây gần như luôn có nghĩa
     là người khác vừa trao xong phần quà đó — danh sách trên màn này đã cũ, giữ nguyên nó là
     nhân viên bấm tiếp vào những dòng không còn tồn tại. */
  [].forEach.call(document.querySelectorAll('[data-trao]'), function(b){
    b.onclick = function(){
      if (b.disabled) return;
      b.disabled = true; b.textContent = L('Đang ghi…','Saving…');
      goi('qua_trao', { id: Number(b.getAttribute('data-trao')) }, function(r){
        if (!r.ok) { alert(r.error || L('Không ghi được.','Could not save.')); }
        tai(true);
      });
    };
  });

  var mtra = document.getElementById('ma-tra');
  if (mtra) mtra.onclick = function(){
    var e = document.getElementById('ma-e');
    e.textContent = L('Đang tra…','Looking up…');
    goi('ma_tra', { sdt: document.getElementById('ma-sdt').value }, function(r){
      if (!r.ok) { e.textContent = r.error || L('Không tra được.','Lookup failed.'); MA_TRA = null; return; }
      e.textContent = ''; MA_TRA = r; ve();
    });
  };
  [].forEach.call(document.querySelectorAll('[data-mahuy]'), function(b){
    b.onclick = function(){
      var m = b.getAttribute('data-mahuy');
      /* Bắt ghi LÝ DO, và nói thẳng là tiền KHÔNG tự hoàn — người bấm phải biết mình vừa làm
         gì và chưa làm gì. */
      var ly = prompt(L('Huỷ mã ' + m + '?\n\nTiền đã thu KHÔNG tự hoàn — hoàn ở ngân hàng, và '
          + 'huỷ dòng tiền ở tab Đối soát nếu cần.\nLý do huỷ:',
        'Cancel code ' + m + '?\n\nThe money collected is NOT refunded automatically — refund at '
          + 'the bank, and cancel the revenue row in Reconciliation if needed.\nReason:'));
      if (ly === null) return;
      lam('ma_huy', { ma: m, ly_do: ly });
    };
  });
}

/* Mở lại trang sau khi khoá màn: hỏi NGAY chứ đừng đợi hết nhịp. Người ta mở ra là để xem
   ngay bây giờ, không phải để nhìn số liệu của 30 giây trước. */
document.addEventListener('visibilitychange', function(){
  if (!document.hidden && TOK && !ban && !CHOT) tai(true);
});

if (TOK) tai(); else veLogin('');
})();
JS;
	}
}
