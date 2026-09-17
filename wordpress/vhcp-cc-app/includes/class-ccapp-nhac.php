<?php
/**
 * NHẮC CHẤM CÔNG — thông báo đẩy tới điện thoại đã cài app.
 *
 * =================================================================================================
 * 🔴 GỬI THÔNG BÁO RỖNG, KHÔNG GỬI NỘI DUNG. ĐÂY LÀ QUYẾT ĐỊNH LỚN NHẤT CỦA CẢ BỘ.
 * =================================================================================================
 * Web Push cho phép đính kèm nội dung, nhưng nội dung ấy BẮT BUỘC phải mã hoá đầu-cuối bằng
 * ECDH + HKDF + AES-128-GCM theo RFC 8291 — khoảng hai trăm dòng mã mật mã tự viết, mà sai một
 * byte thì hãng vẫn nhận 201 còn điện thoại thì im. Không có cách nào thử nó ở máy mình: phải có
 * một iPhone thật, đã cài app thật, đã cấp quyền thật.
 *
 * Nên bộ này gửi lượt đẩy KHÔNG có nội dung — hoàn toàn hợp lệ, và không cần mã hoá gì cả. Thợ nền
 * nhận được tín hiệu rỗng ấy thì tự hỏi máy chủ *"tôi là máy này, đang có lời nhắc nào cho tôi?"*
 * rồi mới hiện chữ. Đổi lại:
 *
 *   · KHÔNG có nội dung nào đi qua máy chủ của Apple/Google. Tên nhân viên, tên cơ sở, giờ công
 *     không rời khỏi hosting của mình — điều mà bản có mã hoá cũng đạt được, nhưng bản này đạt
 *     được mà không cần tin vào hai trăm dòng mật mã chưa ai thử.
 *   · Mất mạng đúng lúc nhận đẩy thì thợ nền không hỏi được -> hiện câu chung chung. Chấp nhận
 *     được: câu chung chung vẫn nhắc đúng việc, và người ta mở app ra là thấy đủ.
 *
 * =================================================================================================
 * 🔴 CHỈ NHẮC NGƯỜI CHƯA CHẤM. Nhắc cả người đã chấm là mỗi sáng cả công ty nhận một thông báo
 *    thừa, và tới tuần thứ hai thì không ai đọc thông báo của app này nữa — kể cả người thật sự
 *    quên. Một thông báo bị bỏ qua tệ hơn không có thông báo nào.
 *
 * ⚠️ WP-CRON CHỈ CHẠY KHI CÓ NGƯỜI MỞ TRANG. Website ít khách thì 7h50 không ai vào, và lời nhắc
 *    đi lúc 9h — tức là vô dụng. Màn Cài đặt nói thẳng điều này và chỉ cách đặt cron thật ở
 *    hosting. Không nói ra thì người ta tưởng bộ nhắc hỏng, trong khi nó chưa từng được gọi.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CCAPP_Nhac {

	const O_LICH   = 'ccapp_lich_nhac';     // cấu hình giờ nhắc
	const HOOK     = 'ccapp_quet_nhac';
	const NHIP     = 'ccapp_5_phut';

	/** Cửa sổ bắn: lời nhắc đi trong khoảng [giờ nhắc, giờ nhắc + bấy nhiêu phút). */
	const CUA_SO_PHUT = 15;

	const VAO = 'vao';
	const RA  = 'ra';

	/** Lỗi liên tiếp tới ngần này thì thôi, coi như máy ấy đã gỡ app. */
	const LOI_TOI_DA = 5;

	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'nhip' ) );
		add_action( self::HOOK, array( __CLASS__, 'quet' ) );
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + 60, self::NHIP, self::HOOK );
		}
	}

	public static function nhip( $ds ) {
		$ds[ self::NHIP ] = array( 'interval' => 300, 'display' => 'Mỗi 5 phút (nhắc chấm công)' );
		return $ds;
	}

	public static function bang() {
		global $wpdb;
		return $wpdb->prefix . 'ccapp_may';
	}

	/**
	 * Bảng máy đã đăng ký nhận nhắc.
	 *
	 * ⚠️ `endpoint` là khoá duy nhất, và nó DÀI (Apple trả về địa chỉ ~200 ký tự, có hãng dài
	 *    hơn). MySQL không cho UNIQUE trên cột dài quá ~191 ký tự với utf8mb4, nên khoá duy nhất
	 *    đặt trên `dau_moi` = SHA-256 của endpoint, còn endpoint đầy đủ để TEXT. Đặt UNIQUE thẳng
	 *    lên một VARCHAR(190) là endpoint bị CẮT CỤT lúc ghi — và một địa chỉ cụt thì POST vào
	 *    đâu cũng 404, im lặng, mãi mãi.
	 */
	public static function dung_bang() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c = $wpdb->get_charset_collate();
		$t = self::bang();
		dbDelta( "CREATE TABLE $t (
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			dau_moi CHAR(64) NOT NULL,
			endpoint TEXT NOT NULL,
			ma_nv VARCHAR(40) NOT NULL DEFAULT '',
			coso VARCHAR(120) NOT NULL DEFAULT '',
			tao_luc DATETIME NULL,
			gui_cuoi DATETIME NULL,
			dau_gui_cuoi VARCHAR(40) NOT NULL DEFAULT '',
			so_loi INT NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY dau_moi (dau_moi),
			KEY ma_nv (ma_nv)
		) $c" );
	}

	private static function dau_moi( $endpoint ) {
		return hash( 'sha256', (string) $endpoint );
	}

	/* ===================================================================== đăng ký máy */

	/**
	 * Máy đăng ký nhận nhắc. `$u` là người đang đăng nhập ở trạm.
	 *
	 * ⚠️ MÃ NV LẤY TỪ PHIÊN. Cho client tự khai mã là ai cũng đăng ký hộ máy mình vào tên người
	 *    khác — rồi nhận thông báo nhắc thay họ, và người kia thì không bao giờ được nhắc.
	 */
	public static function dang_ky( $u, $dat ) {
		global $wpdb;
		$ma = trim( (string) ( isset( $u['ma_nv'] ) ? $u['ma_nv'] : '' ) );
		if ( '' === $ma ) {
			return array( 'ok' => false, 'error' => 'Tài khoản này chưa bật chấm công online.' );
		}
		$ep = trim( (string) ( isset( $dat['endpoint'] ) ? $dat['endpoint'] : '' ) );
		if ( '' === $ep || 0 !== strpos( $ep, 'https://' ) ) {
			return array( 'ok' => false, 'error' => 'Địa chỉ nhận thông báo không hợp lệ.' );
		}
		$t   = self::bang();
		$dm  = self::dau_moi( $ep );
		$cu  = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t WHERE dau_moi=%s", $dm ) );
		$hang = array(
			'dau_moi'  => $dm,
			'endpoint' => $ep,
			'ma_nv'    => $ma,
			'coso'     => trim( (string) ( isset( $u['coso'] ) ? $u['coso'] : '' ) ),
			'so_loi'   => 0,
		);
		if ( $cu ) {
			/* Cùng một máy, người khác đăng nhập -> ĐỔI CHỦ, không thêm hàng. Máy dùng chung ở
			   cửa hàng là chuyện thường; để hai hàng cùng endpoint thì người cũ vẫn nhận nhắc
			   trên máy người mới đang cầm. */
			$wpdb->update( $t, $hang, array( 'id' => (int) $cu ) );
			return array( 'ok' => true, 'moi' => false );
		}
		$hang['tao_luc'] = current_time( 'mysql' );
		$wpdb->insert( $t, $hang );
		return array( 'ok' => true, 'moi' => true );
	}

	/**
	 * Hãng đổi địa chỉ hộp thư -> dời hàng cũ sang địa chỉ mới, GIỮ NGUYÊN chủ.
	 *
	 * 🔴 KHÔNG TẠO HÀNG MỚI KHI KHÔNG TÌM THẤY ĐỊA CHỈ CŨ. Thợ nền gọi cửa này mà không có thẻ
	 *    phiên nào (lúc ấy app đang đóng), nên "biết địa chỉ cũ" là toàn bộ chứng cứ mình có.
	 *    Cho phép tạo mới là ai POST một cặp địa chỉ bịa cũng đẻ được một hàng — vô hại về dữ
	 *    liệu, nhưng nó biến bảng máy thành chỗ đổ rác và mỗi lượt quét phải gửi HTTP cho chúng.
	 */
	public static function doi_dia_chi( $cu, $moi ) {
		global $wpdb;
		$cu  = trim( (string) $cu );
		$moi = trim( (string) $moi );
		if ( '' === $cu || '' === $moi || 0 !== strpos( $moi, 'https://' ) ) {
			return array( 'ok' => false, 'error' => 'Địa chỉ không hợp lệ.' );
		}
		$t = self::bang();
		$h = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM $t WHERE dau_moi=%s", self::dau_moi( $cu ) ), ARRAY_A );
		if ( ! $h ) { return array( 'ok' => false, 'error' => 'Không có máy nào mang địa chỉ cũ ấy.' ); }
		/* Địa chỉ mới có thể đã nằm sẵn trong bảng (thợ nền chạy hai lượt) — xoá cái trùng trước,
		   không thì `UNIQUE KEY dau_moi` chối lượt cập nhật và hàng cũ kẹt lại với địa chỉ chết. */
		$wpdb->delete( $t, array( 'dau_moi' => self::dau_moi( $moi ) ) );
		$wpdb->update( $t, array(
			'dau_moi'  => self::dau_moi( $moi ),
			'endpoint' => $moi,
			'so_loi'   => 0,
		), array( 'id' => (int) $h['id'] ) );
		return array( 'ok' => true );
	}

	public static function huy( $endpoint ) {
		global $wpdb;
		$wpdb->delete( self::bang(), array( 'dau_moi' => self::dau_moi( $endpoint ) ) );
		return array( 'ok' => true );
	}

	/* ===================================================================== lịch nhắc */

	public static function mac_dinh() {
		return array(
			'bat'   => false,
			'vao'   => '07:50',
			'ra'    => '17:30',
			'ngay'  => array( 1, 2, 3, 4, 5, 6 ),   // 0 = Chủ nhật … 6 = Thứ Bảy
			'coso'  => array(),                      // cơ sở => array('vao'=>…, 'ra'=>…), rỗng = theo chung
		);
	}

	public static function lich() {
		$d = get_option( self::O_LICH );
		if ( ! is_array( $d ) ) { return self::mac_dinh(); }
		return array_merge( self::mac_dinh(), $d );
	}

	public static function dat_lich( $moi ) {
		$m = self::mac_dinh();
		$r = array(
			'bat'  => ! empty( $moi['bat'] ),
			'vao'  => self::gio( isset( $moi['vao'] ) ? $moi['vao'] : $m['vao'], $m['vao'] ),
			'ra'   => self::gio( isset( $moi['ra'] ) ? $moi['ra'] : $m['ra'], $m['ra'] ),
			'ngay' => array(),
			'coso' => array(),
		);
		foreach ( (array) ( isset( $moi['ngay'] ) ? $moi['ngay'] : $m['ngay'] ) as $n ) {
			$n = (int) $n;
			if ( $n >= 0 && $n <= 6 && ! in_array( $n, $r['ngay'], true ) ) { $r['ngay'][] = $n; }
		}
		foreach ( (array) ( isset( $moi['coso'] ) ? $moi['coso'] : array() ) as $cs => $o ) {
			$cs = trim( (string) $cs );
			if ( '' === $cs || ! is_array( $o ) ) { continue; }
			$v = self::gio( isset( $o['vao'] ) ? $o['vao'] : '', '' );
			$x = self::gio( isset( $o['ra'] ) ? $o['ra'] : '', '' );
			if ( '' === $v && '' === $x ) { continue; }   // không khai gì = theo giờ chung
			$r['coso'][ $cs ] = array( 'vao' => $v, 'ra' => $x );
		}
		update_option( self::O_LICH, $r, false );
		return array( 'ok' => true, 'lich' => $r );
	}

	/** 'HH:MM' hoặc chuỗi rỗng. Gõ bậy thì trả `$du_phong` chứ không tự bịa một giờ. */
	public static function gio( $s, $du_phong = '' ) {
		$s = trim( (string) $s );
		if ( '' === $s ) { return $du_phong; }
		if ( ! preg_match( '/^(\d{1,2}):(\d{2})$/', $s, $m ) ) { return $du_phong; }
		$h = (int) $m[1];
		$p = (int) $m[2];
		if ( $h < 0 || $h > 23 || $p < 0 || $p > 59 ) { return $du_phong; }
		return sprintf( '%02d:%02d', $h, $p );
	}

	/** Giờ nhắc áp cho một cơ sở: khai riêng thì theo riêng, không thì theo giờ chung. */
	public static function gio_cua( $coso, $loai, $lich = null ) {
		$l  = ( null === $lich ) ? self::lich() : $lich;
		$cs = trim( (string) $coso );
		foreach ( (array) $l['coso'] as $ten => $o ) {
			if ( 0 === strcasecmp( (string) $ten, $cs ) ) {
				$g = isset( $o[ $loai ] ) ? (string) $o[ $loai ] : '';
				if ( '' !== $g ) { return $g; }
			}
		}
		return isset( $l[ $loai ] ) ? (string) $l[ $loai ] : '';
	}

	/* ===================================================================== quyết định nhắc */

	/**
	 * HÔM NAY, LÚC NÀY, MÁY NÀY CÓ ĐÁNG NHẮC KHÔNG — và nhắc chuyện gì.
	 *
	 * Hàm THUẦN, nhận sẵn mọi thứ nó cần. Cố ý không tự đi hỏi CSDL: đây là chỗ duy nhất trong bộ
	 * có luật thật sự, và luật thì phải thử được bằng con số trần chứ không phải bằng cách chờ
	 * tới 7 giờ 50 sáng mai.
	 *
	 * @param string $bay_gio    'HH:MM' theo giờ máy chủ.
	 * @param int    $thu        0=CN … 6=T7.
	 * @param string $g_vao      giờ nhắc chấm vào của cơ sở ấy, '' = không nhắc.
	 * @param string $g_ra       giờ nhắc chấm ra.
	 * @param bool   $da_vao     hôm nay đã có giờ vào chưa.
	 * @param bool   $da_ra      hôm nay đã có giờ ra chưa.
	 * @param array  $lich       cấu hình (để đọc `bat` và `ngay`).
	 * @param string $da_gui     dấu lượt đã gửi gần nhất, dạng 'YYYY-MM-DD|loai'.
	 * @param string $hom_nay    'YYYY-MM-DD'.
	 * @return string '' = không nhắc, hoặc self::VAO / self::RA.
	 */
	public static function nen_nhac( $bay_gio, $thu, $g_vao, $g_ra, $da_vao, $da_ra,
		$lich, $da_gui, $hom_nay ) {

		if ( empty( $lich['bat'] ) ) { return ''; }
		if ( ! in_array( (int) $thu, array_map( 'intval', (array) $lich['ngay'] ), true ) ) { return ''; }

		$nay = self::phut( $bay_gio );
		if ( null === $nay ) { return ''; }

		/* 🔴 GIỜ RA XÉT TRƯỚC GIỜ VÀO. Người quên chấm vào buổi sáng thì tới chiều `da_vao` vẫn
		   là false, và nếu xét giờ vào trước thì 17h30 họ nhận lời nhắc "nhớ chấm VÀO" — sai
		   việc, và làm họ bấm một lượt tạo ra giờ vào lúc 17h31. Chiều rồi thì việc cần nhắc là
		   chấm RA. */
		$pr = self::phut( $g_ra );
		if ( null !== $pr && $nay >= $pr && $nay < $pr + self::CUA_SO_PHUT && ! $da_ra ) {
			if ( $hom_nay . '|' . self::RA !== $da_gui ) { return self::RA; }
			return '';
		}

		$pv = self::phut( $g_vao );
		if ( null !== $pv && $nay >= $pv && $nay < $pv + self::CUA_SO_PHUT && ! $da_vao ) {
			if ( $hom_nay . '|' . self::VAO !== $da_gui ) { return self::VAO; }
		}
		return '';
	}

	public static function phut( $hhmm ) {
		$g = self::gio( $hhmm, '' );
		if ( '' === $g ) { return null; }
		list( $h, $p ) = explode( ':', $g );
		return (int) $h * 60 + (int) $p;
	}

	public static function chu_nhac( $loai ) {
		if ( self::RA === $loai ) {
			return array(
				'tieu_de' => 'Chưa chấm giờ ra',
				'than'    => 'Hôm nay chưa có giờ ra. Mở app chấm trước khi về — thiếu một đầu giờ '
					. 'là ngày ấy không tính công.',
			);
		}
		return array(
			'tieu_de' => 'Chưa chấm công hôm nay',
			'than'    => 'Tới giờ làm rồi mà chưa thấy lượt chấm nào. Mở app chấm giúp em.',
		);
	}

	/* ===================================================================== quét & gửi */

	/**
	 * Vòng quét, chạy 5 phút một lượt.
	 *
	 * ⚠️ MỘT LƯỢT QUÉT KHÔNG ĐƯỢC GỬI QUÁ NHIỀU. Mỗi lượt gửi là một lượt HTTP ra ngoài, và
	 *    WP-Cron chạy TRONG một lượt tải trang của một người khách nào đó — ba trăm lượt HTTP
	 *    nối tiếp là trang ấy treo. Cắt trần rồi để lượt quét sau làm nốt; cửa sổ 15 phút đủ cho
	 *    ba lượt quét.
	 */
	public static function quet( $tran = 60 ) {
		global $wpdb;
		$lich = self::lich();
		if ( empty( $lich['bat'] ) ) { return 0; }
		if ( ! CCAPP_Khoa::co() && '' === CCAPP_Khoa::pub() ) { return 0; }
		/* 🔴 GÁC `method_exists` NGAY TẠI ĐÂY, không chỉ `class_exists`. Bốn plugin cài độc lập
		   nên bản có thể lệch: `class_exists()` chỉ nói CÓ PLUGIN, không nói bản ấy CÓ HÀM mình
		   định gọi. Gọi hụt một hàm tĩnh là Fatal — mà vòng này chạy trong WP-Cron, tức trong
		   một lượt tải trang của một người khách nào đó, và Fatal ở đó là TRẮNG TRANG cho họ.
		   `tools/test/kiem-goi-cheo.php` canh luật này cho cả kho. */
		if ( ! class_exists( 'VHCC_DB' ) || ! method_exists( 'VHCC_DB', 't' ) ) { return 0; }

		$hom_nay = current_time( 'Y-m-d' );
		$bay_gio = current_time( 'H:i' );
		$thu     = (int) current_time( 'w' );

		$t  = self::bang();
		$ds = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $t WHERE so_loi < %d AND ma_nv <> '' LIMIT 500", self::LOI_TOI_DA ), ARRAY_A );
		if ( ! $ds ) { return 0; }

		$gui = 0;
		foreach ( $ds as $may ) {
			if ( $gui >= (int) $tran ) { break; }
			$coso = self::coso_cua( $may['ma_nv'], $may['coso'] );
			list( $da_vao, $da_ra ) = self::da_cham( $may['ma_nv'], $coso, $hom_nay );
			$loai = self::nen_nhac(
				$bay_gio, $thu,
				self::gio_cua( $coso, self::VAO, $lich ),
				self::gio_cua( $coso, self::RA, $lich ),
				$da_vao, $da_ra, $lich,
				(string) $may['dau_gui_cuoi'], $hom_nay
			);
			if ( '' === $loai ) { continue; }

			$kq = self::day( (string) $may['endpoint'] );
			if ( 'mat' === $kq ) {
				/* Hãng nói hộp thư này không còn (410/404) — gỡ hẳn. Giữ lại là mỗi lượt quét
				   lại gửi một lượt HTTP chắc chắn hỏng, mãi mãi. */
				$wpdb->delete( $t, array( 'id' => (int) $may['id'] ) );
				continue;
			}
			if ( 'ok' !== $kq ) {
				$wpdb->update( $t, array( 'so_loi' => (int) $may['so_loi'] + 1 ), array( 'id' => (int) $may['id'] ) );
				continue;
			}
			$wpdb->update( $t, array(
				'so_loi'       => 0,
				'gui_cuoi'     => current_time( 'mysql' ),
				'dau_gui_cuoi' => $hom_nay . '|' . $loai,
			), array( 'id' => (int) $may['id'] ) );
			$gui++;
		}
		return $gui;
	}

	/** Cơ sở để tra bảng công: lấy từ hồ sơ, không lấy từ ô đã lưu lúc đăng ký (hồ sơ đổi được). */
	private static function coso_cua( $ma_nv, $du_phong ) {
		/* Cùng luật gác với `quet()`: hỏi `method_exists` cho TỪNG hàm, ngay tại chỗ gọi. */
		$co_chuan = class_exists( 'VHCC_NhanSu' ) && method_exists( 'VHCC_NhanSu', 'chuan_coso' );
		if ( class_exists( 'VHCC_NhanSu' ) && method_exists( 'VHCC_NhanSu', 'ho_so' ) ) {
			$hs = VHCC_NhanSu::ho_so( $ma_nv );
			if ( $hs && ! empty( $hs['cua_hang'] ) ) {
				return $co_chuan ? VHCC_NhanSu::chuan_coso( $hs['cua_hang'] ) : trim( (string) $hs['cua_hang'] );
			}
		}
		return $co_chuan ? VHCC_NhanSu::chuan_coso( $du_phong ) : trim( (string) $du_phong );
	}

	/**
	 * Hôm nay người ấy đã có giờ vào / giờ ra chưa.
	 *
	 * ⚠️ HỎI MỌI HÀNG CỦA NGÀY, KHÔNG CHỈ HÀNG CHÍNH. Người kiêm nhiệm vụ có hàng phụ (`-TT`,
	 *    `-TG`) và người ca đêm có hàng `-CD`; chỉ hỏi hàng chính là nhắc nhầm người đã chấm.
	 * ⚠️ VÀ HỎI MỌI CƠ SỞ, không riêng cơ sở trong hồ sơ: người làm hai nơi có thể đã chấm ở nơi
	 *    kia. Nhắc họ "chưa chấm" khi họ vừa chấm xong ở cửa hàng bên cạnh là đúng loại thông báo
	 *    làm người ta tắt thông báo.
	 */
	private static function da_cham( $ma_nv, $coso, $ngay ) {
		global $wpdb;
		if ( ! class_exists( 'VHCC_DB' ) || ! method_exists( 'VHCC_DB', 't' ) ) {
			/* Không đọc được bảng công thì KHÔNG đoán là "chưa chấm". Đoán sai phía ấy là gửi
			   thông báo cho cả công ty, kể cả người vừa chấm xong. */
			return array( true, true );
		}
		$t = VHCC_DB::t( 'cham_cong' );
		$r = $wpdb->get_row( $wpdb->prepare(
			"SELECT MAX(gio_vao_giay IS NOT NULL) co_vao, MAX(gio_ra_giay IS NOT NULL) co_ra
			 FROM $t WHERE ma_nv=%s AND ngay=%s", $ma_nv, $ngay ), ARRAY_A );
		if ( ! $r ) { return array( false, false ); }
		return array( ! empty( $r['co_vao'] ), ! empty( $r['co_ra'] ) );
	}

	/**
	 * Đẩy một tín hiệu RỖNG tới một hộp thư.
	 *
	 * @return string 'ok' | 'mat' (hộp thư không còn) | 'hong'
	 */
	public static function day( $endpoint ) {
		$jwt = CCAPP_Khoa::jwt( $endpoint );
		if ( '' === $jwt ) { return 'hong'; }
		$r = wp_remote_post( $endpoint, array(
			'timeout' => 10,
			'headers' => array(
				'TTL'           => '3600',
				'Content-Length' => '0',
				/* `aes128gcm` KHÔNG được khai khi không có nội dung — khai mà thân rỗng thì có
				   hãng trả 400. Không nội dung thì không có Content-Encoding. */
				'Authorization' => 'vapid t=' . $jwt . ', k=' . CCAPP_Khoa::pub(),
			),
			'body'    => '',
		) );
		if ( is_wp_error( $r ) ) { return 'hong'; }
		$ma = (int) wp_remote_retrieve_response_code( $r );
		if ( 404 === $ma || 410 === $ma ) { return 'mat'; }
		return ( $ma >= 200 && $ma < 300 ) ? 'ok' : 'hong';
	}

	/**
	 * Thợ nền hỏi: *"máy này đang có lời nhắc gì?"* — trả về chữ để hiện.
	 *
	 * ⚠️ TRẢ LỜI THEO ENDPOINT, KHÔNG THEO THẺ PHIÊN. Lúc thông báo tới thì app đang đóng và
	 *    không có phiên nào; thợ nền chỉ cầm được đúng cái endpoint của chính nó. Endpoint là
	 *    một chuỗi ngẫu nhiên dài do hãng cấp, biết nó tức là đang chạy trên đúng máy ấy.
	 * ⚠️ VÀ KHÔNG TRẢ VỀ GÌ RIÊNG TƯ. Không tên, không mã NV, không giờ công — chỉ một câu nhắc.
	 *    Đây là cửa mở không cần đăng nhập; thứ gì đi qua nó thì coi như ai đoán trúng endpoint
	 *    cũng đọc được.
	 */
	public static function loi_nhac( $endpoint ) {
		global $wpdb;
		$chung = self::chu_nhac( self::VAO );
		$ep = trim( (string) $endpoint );
		if ( '' === $ep ) { return $chung; }
		$t = self::bang();
		$h = $wpdb->get_row( $wpdb->prepare(
			"SELECT dau_gui_cuoi FROM $t WHERE dau_moi=%s", self::dau_moi( $ep ) ), ARRAY_A );
		if ( ! $h || '' === (string) $h['dau_gui_cuoi'] ) { return $chung; }
		$p = explode( '|', (string) $h['dau_gui_cuoi'] );
		return self::chu_nhac( isset( $p[1] ) ? $p[1] : self::VAO );
	}
}
