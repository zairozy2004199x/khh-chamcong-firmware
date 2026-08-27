<?php
/**
 * MÀN MÁY & FIRMWARE TRÊN WEB — bản ngoài internet của hai màn wp-admin cuối cùng:
 * *Máy & Firmware* và *Cổng nhận từ máy*.
 *
 * =============================================================================================
 * VÌ SAO GỘP HAI MÀN WP-ADMIN LÀM MỘT TAB
 * =============================================================================================
 * Anh Thắng 27/08/2026: *"Máy & Firmware · Cổng nhận từ máy"* — hai màn còn lại cần ra web, và
 * anh nêu liền một hơi. Đúng ra chúng là MỘT việc: *cái máy ở cửa hàng có đang nói chuyện được
 * với website không*. Màn Cổng trả lời "cổng có mở không, gói nào bị bỏ và vì sao"; màn Máy trả
 * lời "máy nào còn sống, lệnh nào đang chờ". Khi một cửa hàng mất chấm công, người trực phải đọc
 * cả hai mới biết lỗi nằm ở đâu — mà ở wp-admin chúng là hai mục cách nhau bốn dòng menu.
 *
 * Thứ tự khối cố ý đi từ NGOÀI VÀO TRONG, theo đúng đường một lượt bấm đi:
 *   1. Cổng có mở không (khoá `VHCC_KHOA_MAY`) — cổng đóng thì mọi thứ dưới đây vô nghĩa
 *   2. Máy nào mất nhịp
 *   3. Nhật ký cổng: gói nào bị bỏ, vì sao
 *   4. Danh sách máy · gán cơ sở
 *   5. Tải lại sổ chấm công từ đầu đọc
 *   6. Sổ mặt trong máy (người nghỉ việc vẫn chấm được)
 *   7. Lượt bấm chờ gán
 *   8. Lệnh đang chờ xuống máy
 *   9. Firmware / OTA
 *
 * =============================================================================================
 * MẤY CHỐT KHÔNG ĐƯỢC NỚI
 * =============================================================================================
 * 🔴 CẢ MÀN NÀY LÀ BẬC `he_thong` (Admin). Ở wp-admin, cửa là `manage_options` — tức chỉ người
 *    có tài khoản WordPress quản trị mới vào được. Đưa ra web là bỏ mất lớp gác ấy, nên phải
 *    dựng lại bằng bậc vai, VÀ dựng ở CẢ HAI chỗ: lúc vẽ màn và lúc nhận việc POST. Chỉ gác lúc
 *    vẽ thì ai đoán ra tên `viec` là gửi thẳng POST được — mà một trong mấy việc ấy là đẩy
 *    firmware cho cả 26 máy.
 *
 * 🔴 KHÔNG IN KHOÁ `VHCC_KHOA_MAY` RA MÀN HÌNH, chỉ nói CÓ hay KHÔNG. Trang này chạy ngoài
 *    internet và ảnh chụp màn hình đi khắp nơi; lộ khoá là ai cũng đẩy được lượt chấm công giả
 *    vào bảng lương. Cùng lối với luật không bao giờ in PIN.
 *
 * ⚠️ KHÔNG có lấy một dòng script — cùng luật với cả màn quản trị. Gập/xổ dùng `<details>`.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_WebMay {

	/** Tham số của màn này phải sống sót qua mỗi lượt bấm — xem `VHCC_Web::THAM_SO`. */
	const THAM_SO = array( 'msoma' );

	/** Việc POST của màn này. Danh sách trắng: tên nào không có ở đây thì không phải việc của màn. */
	const VIEC = array( 'may_gan', 'may_quet', 'may_dung_tai_lai', 'may_tai_lai', 'may_xoa_lenh',
		'may_ota', 'may_ota_mot', 'may_go_ota', 'may_sim' );

	public static function la_viec( $viec ) {
		return in_array( (string) $viec, self::VIEC, true );
	}

	/**
	 * NHẬN VIỆC POST.
	 *
	 * 🔴 Gác quyền NGAY ĐÂY, đừng tin vào việc màn không vẽ nút cho người không đủ bậc.
	 *    Nút không vẽ chỉ là không mời; POST thì ai gửi cũng tới.
	 */
	public static function viec( $viec, $toi ) {
		if ( ! VHCC_Vai::duoc( $toi, 'may' ) ) {
			return array( array( 'loi' => 'Màn Máy & Firmware cần đầu việc "Máy chấm công & firmware" '
				. '— mặc định là bậc Admin, hoặc được khai riêng ở Quản lý nhân sự → Chia đầu việc. '
				. 'Một nút ở đây đẩy firmware cho MỌI máy trong chuỗi — hỏng thì mất luôn đường '
				. 'sửa từ xa và phải đi từng cửa hàng cắm USB.' ) );
		}
		$id  = isset( $_POST['may_id'] ) ? (int) $_POST['may_id'] : 0;
		$lay = function ( $k ) {
			return isset( $_POST[ $k ] ) ? wp_unslash( $_POST[ $k ] ) : '';
		};

		if ( 'may_gan' === $viec )          { return array( VHCC_May::gan_may( $id, $lay( 'coso' ) ) ); }
		if ( 'may_sim' === $viec )          { return array( VHCC_May::luu_sim( $id, $lay( 'sim' ) ) ); }
		if ( 'may_quet' === $viec )         { return array( VHCC_May::yeu_cau_quet( $id ) ); }
		if ( 'may_dung_tai_lai' === $viec ) { return array( VHCC_May::dung_tai_lai( $id ) ); }
		if ( 'may_xoa_lenh' === $viec )     { return array( VHCC_May::xoa_lenh( $lay( 'op_id' ) ) ); }
		if ( 'may_go_ota' === $viec )       { return array( VHCC_May::go_ota( $id ) ); }
		if ( 'may_tai_lai' === $viec ) {
			return array( VHCC_May::tai_lai( $id, $lay( 'tu' ), $lay( 'den' ), $lay( 'ma_nv' ) ) );
		}
		/* Thử MỘT máy: cố ý KHÔNG đòi gõ xác nhận — đây chính là bước nên làm trước, đừng dựng
		   rào ở đúng cái việc mình muốn người ta làm. */
		if ( 'may_ota_mot' === $viec ) {
			return array( VHCC_May::dat_ota( $lay( 'ver' ), $lay( 'url' ), '', $id ) );
		}
		/* Đẩy CẢ CHUỖI: đòi gõ đúng chữ. `dat_ota` tự kiểm, không kiểm lại ở đây — hai nơi cùng
		   giữ một luật là sớm muộn hai nơi hiểu khác nhau. */
		if ( 'may_ota' === $viec ) {
			return array( VHCC_May::dat_ota( $lay( 'ver' ), $lay( 'url' ), $lay( 'xac_nhan' ), 0 ) );
		}
		return array();
	}

	// ===================================================================================== màn

	public static function man( $ky, $toi ) {
		if ( ! VHCC_Vai::duoc( $toi, 'may' ) ) {
			echo '<div class="the"><h2>Không vào được màn này</h2>';
			echo '<p class="mo">Màn <b>Máy &amp; Firmware</b> cần đầu việc <b>Máy chấm công &amp; '
				. 'firmware</b> — mặc định là bậc <b>Admin</b>. Người dựng máy không cần lên Admin: '
				. 'khai riêng một dòng ở <b>Quản lý nhân sự → Chia đầu việc</b> là đủ. Ở đây có nút '
				. 'đẩy firmware cho <b>mọi máy trong chuỗi</b> — đẩy nhầm một bản là mất luôn đường '
				. 'sửa từ xa của cả 26 cửa hàng.</p></div>';
			return;
		}
		$m  = VHCC_May::ds_may();
		$ds = ! empty( $m['ok'] ) ? (array) $m['data'] : array();

		self::the_cong();
		self::the_chan_doan();
		self::the_mat_nhip( $ds );
		self::the_nhat_ky();
		self::the_ds_may( $ds, $ky );
		self::the_tai_lai( $ds, $ky );
		self::the_so_mat( $ds, $ky );
		self::the_cho_gan();
		self::the_lenh( $ky );
		self::the_firmware( $ds, $ky );
	}

	/** 1. CỔNG CÓ MỞ KHÔNG. Đứng đầu vì cổng đóng thì mọi khối dưới đây vô nghĩa. */
	private static function the_cong() {
		$duong   = home_url( '/' . VHCC_Nhan::DUONG );
		$co_khoa = defined( 'VHCC_KHOA_MAY' ) && '' !== (string) VHCC_KHOA_MAY;
		echo '<div class="the"><h2>Cổng nhận chấm công từ máy</h2>';
		echo '<p class="mo">Địa chỉ nạp vào máy: <code>' . esc_html( $duong ) . '</code> — '
			. '<b>đúng địa chỉ này, không thêm dấu gạch chéo cuối</b>. Firmware không đi theo '
			. 'chuyển hướng: gặp chuyển hướng nó gọi lại bằng GET và mất trọn lượt bấm.</p>';
		if ( $co_khoa ) {
			/* 🔴 CHỈ NÓI CÓ HAY KHÔNG, KHÔNG IN KHOÁ. Trang này ngoài internet, ảnh chụp màn hình
			   đi khắp nơi — lộ khoá là ai cũng đẩy được lượt chấm công giả vào bảng lương. */
			echo '<p><span class="chu-luc">✓ Đã cấu hình khoá <code>VHCC_KHOA_MAY</code>.</span> '
				. '<span class="mo">Giá trị khoá cố ý không hiện ở đây — trang này chạy ngoài '
				. 'internet.</span></p>';
		} else {
			echo '<div class="bao loi"><b>Chưa cấu hình khoá — cổng đang ĐÓNG, mọi lượt bấm bị chối.'
				. '</b><br>Thêm vào <code>wp-config.php</code>: '
				. '<code>define( \'VHCC_KHOA_MAY\', \'…chuỗi ngẫu nhiên dài…\' );</code><br>'
				. 'Đặt trong <code>wp-config.php</code> chứ không trong cơ sở dữ liệu: bảng cài đặt '
				. 'thì app đọc được, mà app thì có màn hình.</div>';
		}
		echo '</div>';
	}

	/**
	 * CỔNG ĐANG Ở TRẠNG THÁI NÀO — đọc từ chính nhật ký, không bắt người ta tự suy.
	 *
	 * =============================================================================================
	 * Anh Thắng 27/08/2026: *"bắt đầu kết nối máy chấm công để tránh mất dữ liệu"*, kèm ảnh màn
	 * này: khoá đã cấu hình, *"Chưa có máy nào"*, và nhật ký chỉ có ba dòng `GET_VAO_CONG_MAY`.
	 * =============================================================================================
	 * 🔴 BA DÒNG ẤY LÀ MANH MỐI DUY NHẤT, VÀ NÓ ĐANG NẰM IM.
	 *    Người nhìn màn này thấy "khoá ✓" và "chưa có máy nào" thì kết luận sai gần như chắc
	 *    chắn: *chắc firmware chưa nạp*. Nhưng cổng còn có mấy trạng thái khác trông y hệt từ
	 *    bên ngoài, mà chữa thì mỗi cái một đường:
	 *
	 *      • Chưa từng có gói POST nào  -> ESP32 chưa chạy, hoặc chưa nạp firmware, hoặc sai WiFi
	 *      • Có POST nhưng SAI KHOÁ      -> khoá trong firmware khác `VHCC_KHOA_MAY`
	 *      • Chỉ toàn GET               -> firmware BỊ CHUYỂN HƯỚNG: nó gọi lại bằng GET và mất
	 *                                       trọn thân gói. Địa chỉ nạp vào máy sai dạng — thừa
	 *                                       dấu `/` cuối, hoặc `http` thay vì `https`, hoặc
	 *                                       thiếu `www`. WordPress chuẩn hoá đường dẫn bằng
	 *                                       chuyển hướng, và đó là chỗ lượt bấm chết.
	 *
	 * ⚠️ ĐỌC MỖI DÒNG GET LÀ CHƯA ĐỦ ĐỂ KẾT LUẬN. Chính người quản trị mở địa chỉ ấy bằng trình
	 *    duyệt để thử cũng sinh ra đúng dòng ấy. Phân biệt bằng NHỊP: firmware gọi mỗi vài phút
	 *    nên hỏng thì sinh ra hàng chục dòng dày đặc; người mở tay thì được vài dòng rải rác.
	 *    Nói ra cả hai khả năng, đừng chọn hộ.
	 */
	private static function the_chan_doan() {
		$nk = (array) get_option( 'vhcc_nhat_ky_may', array() );
		/* Mỗi dòng nhật ký mang `lan` — số lần chính nó lặp lại (xem `VHCC_Nhan::ghi_loi`). Đếm
		   số DÒNG là đếm nhầm: một máy hỏng đẩy lại nghìn lần vẫn nằm gọn trong một dòng. */
		$dem = array( 'get' => 0, 'khoa' => 0 );
		$moc = array( 'get' => array( '', '' ), 'khoa' => array( '', '' ) );
		$loi_khoa = '';
		foreach ( $nk as $x ) {
			$ma  = isset( $x['ma'] ) ? (string) $x['ma'] : '';
			$k   = 'GET_VAO_CONG_MAY' === $ma ? 'get' : ( 'SAI_KHOA' === $ma ? 'khoa' : '' );
			if ( '' === $k ) { continue; }
			$lan = isset( $x['lan'] ) ? max( 1, (int) $x['lan'] ) : 1;
			$dem[ $k ] += $lan;
			$dau = isset( $x['dau'] ) ? (string) $x['dau'] : ( isset( $x['luc'] ) ? (string) $x['luc'] : '' );
			$cuoi = isset( $x['luc'] ) ? (string) $x['luc'] : '';
			if ( '' === $moc[ $k ][1] ) { $moc[ $k ][1] = $cuoi; }   // dòng đầu mảng = mới nhất
			$moc[ $k ][0] = $dau;                                     // đi tiếp về quá khứ
			if ( 'khoa' === $k && '' === $loi_khoa ) {
				$loi_khoa = isset( $x['loi'] ) ? (string) $x['loi'] : '';
			}
		}

		global $wpdb;
		$so_luot = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'cham_cong' )
			. " WHERE nguon IN ('may','hon-hop')" );
		$so_may  = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'may' ) );

		echo '<div class="the" id="chandoan"><h2>Cổng đang ở trạng thái nào</h2>';

		if ( $so_may > 0 || $so_luot > 0 ) {
			echo '<p><span class="chu-luc">✓ Cổng ĐÃ nhận được dữ liệu thật.</span> '
				. esc_html( $so_may ) . ' máy đã tự hiện ra · '
				. esc_html( number_format( $so_luot ) ) . ' hàng chấm công mang nguồn máy.</p>';
			if ( $dem['khoa'] > 0 ) {
				/* ⚠️ CÓ DỮ LIỆU THẬT KHÔNG CÓ NGHĨA LÀ MỌI MÁY ĐỀU QUA. 25 máy chạy tốt mà máy
				   thứ 26 sai khoá thì màn này vẫn xanh, còn một cửa hàng thì mất công cả tháng. */
				echo '<div class="bao loi"><b>Nhưng vẫn có ' . (int) $dem['khoa'] . ' lượt bị chối vì '
					. 'SAI KHOÁ.</b> Máy khác đang gửi được, nên nhiều khả năng còn một máy lẻ '
					. 'mang khoá cũ — nó đang KHÔNG lên được lượt nào.<br>'
					. esc_html( $loi_khoa ) . '</div>';
			}
			if ( $dem['get'] > 0 ) {
				echo '<p class="mo">Có ' . (int) $dem['get'] . ' lượt GET lẫn vào — thường là người '
					. 'mở địa chỉ ấy bằng trình duyệt để thử. Chỉ đáng lo nếu chúng dày đặc và '
					. 'liên tục.</p>';
			}
			echo '</div>';
			return;
		}

		/* Chưa có gì cả — nói ra ĐÚNG mấy khả năng, kèm cách phân biệt. */
		echo '<div class="bao canh"><b>Cổng chưa nhận được lượt chấm công nào.</b> '
			. 'Khoá đã cấu hình và cổng đang mở, nhưng chưa máy nào gửi được gói POST hợp lệ.</div>';

		/* 🔴 SAI KHOÁ ĐỨNG TRƯỚC MỌI THỨ KHÁC. Nó là câu trả lời DỨT KHOÁT cho câu hỏi đắt nhất
		   của màn này — "máy có chạy không" — và câu trả lời là CÓ: gói đã đi hết quãng đường
		   từ cửa hàng về tới PHP. Còn lại chỉ là một dòng trong `wp-config.php`. Để nó lẫn
		   xuống dưới mấy khả năng "chưa nạp firmware" là chỉ người ta đi sai đường. */
		if ( $dem['khoa'] > 0 ) {
			echo '<p><span class="chu-luc">🔑 Tìm ra rồi: ' . (int) $dem['khoa'] . ' lượt bị chối vì '
				. 'SAI KHOÁ.</span> Nghĩa là <b>máy vẫn chạy và vẫn tới được cổng</b> — gói đi hết '
				. 'quãng đường từ cửa hàng về tới đây. Không phải đi sửa ESP32.</p>';
			echo '<p class="mo">' . esc_html( $loi_khoa ) . '</p>';
			echo '<p class="mo">Lần đầu <b>' . esc_html( $moc['khoa'][0] ) . '</b> · gần nhất <b>'
				. esc_html( $moc['khoa'][1] ) . '</b>. Sửa xong thì lượt bấm đang nằm trong đầu đọc '
				. 'lấy lại được bằng lệnh <b>Tải lại</b> — chưa mất gì cả.</p>';
			echo '</div>';
			return;
		}

		if ( $dem['get'] > 0 ) {
			echo '<p>Nhật ký có <b>' . (int) $dem['get'] . ' lượt GET</b> vào cổng'
				. ( '' !== $moc['get'][1] ? ' (gần nhất <b>' . esc_html( $moc['get'][1] ) . '</b>)' : '' ) . '. '
				. 'Hai cách đọc, và cách phân biệt:</p>';
			echo '<ul class="mo" style="margin:6px 0 0 18px">';
			echo '<li><b>Vài lượt rải rác</b> — gần như chắc là chính anh/chị mở địa chỉ ấy bằng '
				. 'trình duyệt để thử. Không sao cả, nhưng cũng không nói lên được gì về firmware.</li>';
			echo '<li><b>Hàng chục lượt dày đặc, cách nhau vài phút</b> — firmware ĐANG bị chuyển '
				. 'hướng. Nó POST, máy chủ đáp “đi chỗ khác”, nó gọi lại bằng GET và <b>mất trọn '
				. 'thân gói</b>. Chữa ở địa chỉ nạp vào máy: phải đúng <code>'
				. esc_html( home_url( '/' . VHCC_Nhan::DUONG ) ) . '</code> — không thừa dấu '
				. '<code>/</code> cuối, đúng <code>https</code>, đúng có hay không có '
				. '<code>www</code> như tên miền thật.</li>';
			echo '</ul>';
			/* ⚠️ ĐỪNG BẮT NGƯỜI TA TỰ ĐOÁN NHỊP. Ba con số `dau`/`luc`/`lan` đã có sẵn trong sổ,
			   nên chia ra là xong. Nói kèm ngưỡng đã dùng, để ai không đồng ý còn cãi lại được. */
			echo self::doc_nhip( $dem['get'], $moc['get'][0], $moc['get'][1] );
		} else {
			echo '<p class="mo">Nhật ký <b>trống trơn</b> — chưa có lượt nào chạm tới cổng, kể cả '
				. 'lượt hỏng. Nghĩa là gói của máy chưa ra khỏi cửa hàng: ESP32 chưa chạy, chưa nạp '
				. 'firmware, hoặc nó chưa vào được WiFi / 4G.</p>';
		}

		echo '<p class="mo">Thử nhanh ngay từ máy tính: mở địa chỉ cổng bằng trình duyệt. Đáp lại '
			. 'phải là <code>{"status":"ERROR","message":"Cong nay chi nhan POST."}</code> kèm mã '
			. '<b>405</b>. Ra trang khác, ra trang chủ, hay ra 404 thì địa chỉ đang sai — và đó '
			. 'đúng là cái làm firmware mất gói.</p>';
		echo '</div>';
	}

	/**
	 * ĐỌC HỘ CÁI NHỊP: "rải rác" hay "dày đặc"?
	 *
	 * ⚠️ NÓI RA NGƯỠNG ĐÃ DÙNG. Đây là suy đoán, không phải phép đo — máy chỉ có ba con số và
	 *    khoảng cách TRUNG BÌNH thì che mất mọi thứ gồ ghề. Nói kèm ngưỡng thì người đọc còn
	 *    cãi lại được; nói trống không thì thành một lời phán không kiểm chứng nổi.
	 */
	private static function doc_nhip( $lan, $dau, $cuoi ) {
		if ( $lan < 2 || '' === $dau || '' === $cuoi ) {
			return '<p class="mo">Mới <b>' . (int) $lan . ' lượt</b> — quá ít để đọc ra nhịp.</p>';
		}
		$giay = strtotime( $cuoi ) - strtotime( $dau );
		if ( $giay <= 0 ) { return ''; }
		$cach = (int) round( $giay / ( $lan - 1 ) );
		$mo   = $lan >= 6 && $cach <= 1800;
		return '<p class="' . ( $mo ? 'bao loi' : 'mo' ) . '">'
			. ( $mo ? '<b>Nhịp đang giống firmware, không giống người bấm tay.</b> ' : '' )
			. (int) $lan . ' lượt trong ' . esc_html( self::doc_khoang( $giay ) )
			. ', trung bình mỗi ' . esc_html( self::doc_khoang( $cach ) ) . ' một lượt'
			. ( $mo ? ' — đều và dày như vậy thì gần như chắc là firmware đang bị chuyển hướng. '
					. 'Đi sửa địa chỉ nạp vào máy trước.'
					: '. Thưa như vậy thì nhiều khả năng là người mở tay.' )
			. ' <span class="mo">(Ngưỡng đang dùng: từ 6 lượt trở lên và cách nhau dưới 30 phút.)</span></p>';
	}

	private static function doc_khoang( $giay ) {
		$giay = max( 0, (int) $giay );
		if ( $giay < 90 ) { return $giay . ' giây'; }
		if ( $giay < 5400 ) { return round( $giay / 60 ) . ' phút'; }
		if ( $giay < 172800 ) { return round( $giay / 3600 ) . ' giờ'; }
		return round( $giay / 86400 ) . ' ngày';
	}

	/** 2. MÁY MẤT NHỊP — lên trên, vì đây là thứ phải biết ngay. */
	private static function the_mat_nhip( $ds ) {
		$dut = array();
		foreach ( $ds as $x ) {
			if ( empty( $x['song'] ) ) { $dut[] = $x; }
		}
		echo '<div class="the">';
		if ( ! $ds ) {
			echo '<p class="mo">Chưa có máy nào. Máy tự hiện ra ở đây ngay lượt đầu tiên nó gửi nhịp '
				. 'hoặc gửi lượt chấm công — không phải khai tay.</p></div>';
			return;
		}
		if ( ! $dut ) {
			echo '<p><span class="chu-luc">✓ Cả ' . count( $ds ) . ' máy đều đang gửi nhịp.</span></p></div>';
			return;
		}
		/* `VHCC_MayCong` cùng plugin nên gọi thẳng — luật `method_exists` của
		   `kiem-goi-cheo.php` là cho lời gọi sang plugin KHÁC (tiền tố lớp khác). */
		$phut = (int) ( VHCC_MayCong::HET_SONG / 60 );
		echo '<div class="bao loi"><b>' . count( $dut ) . ' máy không gửi nhịp quá '
			. $phut . ' phút.</b><br>'
			. 'Máy đứt thì cửa hàng đó đang <b>KHÔNG</b> chấm công lên được — mà lượt bấm vẫn nằm '
			. 'trong đầu đọc, nên lấy lại được bằng lệnh <b>Tải lại</b> sau khi máy sống. Kiểm điện, '
			. 'mạng, và SIM còn tiền không.</div>';
		echo '<ul class="mo" style="margin:8px 0 0 18px">';
		foreach ( $dut as $x ) {
			echo '<li><b>' . esc_html( $x['cua_hang'] ? $x['cua_hang'] : '(chưa gán cơ sở)' ) . '</b> · '
				. '<code>' . esc_html( $x['serial'] ? $x['serial'] : $x['mac'] ) . '</code> · '
				. ( trim( (string) $x['nhip_luc'] ) !== ''
					? 'nhịp cuối ' . esc_html( $x['nhip_luc'] ) : 'chưa gửi nhịp nào bao giờ' )
				. '</li>';
		}
		echo '</ul></div>';
	}

	/**
	 * 3. NHẬT KÝ CỔNG.
	 *
	 * 🔴 Cổng trả SUCCESS cho cả những gói nó BỎ — buộc phải vậy, không thì firmware đẩy lại vô
	 *    hạn. Nên đây là chỗ DUY NHẤT thấy được cái gì đã bị bỏ và vì sao. Gập sẵn vì ngày thường
	 *    nó rỗng; nhưng có dòng thì mở sẵn, kẻo thứ duy nhất nói ra lỗi lại nằm sau một cú bấm.
	 */
	private static function the_nhat_ky() {
		$nk = get_option( 'vhcc_nhat_ky_may', array() );
		if ( ! is_array( $nk ) ) { $nk = array(); }
		echo '<div class="the"><details' . ( $nk ? ' open' : '' ) . '>';
		echo '<summary><b>Nhật ký cổng</b> — ' . count( $nk ) . ' dòng gần nhất</summary>';
		echo '<p class="mo">Cổng trả <code>SUCCESS</code> cho cả những gói nó <b>bỏ</b> — không thì '
			. 'firmware đẩy lại vô hạn. Nên đây là chỗ <b>duy nhất</b> thấy được cái gì đã bị bỏ và '
			. 'vì sao.</p>';
		if ( ! $nk ) {
			echo '<p class="mo"><i>Chưa có gì. Cổng chưa nhận lượt nào, hoặc mọi lượt đều vào sổ '
				. 'trót lọt.</i></p>';
		} else {
			/* ⚠️ CỘT "SỐ LẦN" KHÔNG PHẢI TRANG TRÍ. Từ 2.75.0 sổ gộp dòng liên tiếp giống hệt
			   nhau, nên một DÒNG ở đây có thể là hàng nghìn lượt. Không hiện `lan` thì người
			   đọc trừ đi mất đúng cái con số nói lên mức độ nặng nhẹ. */
			echo '<div class="cuon"><table><thead><tr><th>Lúc</th><th>Số lần</th><th>Mã</th>'
				. '<th>Chi tiết</th></tr></thead><tbody>';
			foreach ( $nk as $d ) {
				$lan = isset( $d['lan'] ) ? max( 1, (int) $d['lan'] ) : 1;
				$dau = isset( $d['dau'] ) ? (string) $d['dau'] : '';
				echo '<tr><td>' . esc_html( isset( $d['luc'] ) ? $d['luc'] : '' )
					. ( $lan > 1 && '' !== $dau ? '<br><span class="mo">từ ' . esc_html( $dau )
						. '</span>' : '' ) . '</td>'
					. '<td>' . ( $lan > 1 ? '<b>×' . $lan . '</b>' : '1' ) . '</td>'
					. '<td><code>' . esc_html( isset( $d['ma'] ) ? $d['ma'] : '' ) . '</code></td>'
					. '<td>' . esc_html( isset( $d['loi'] ) ? $d['loi'] : '' ) . '</td></tr>';
			}
			echo '</tbody></table></div>';
		}
		echo '</details></div>';
	}

	/** 4. DANH SÁCH MÁY · gán cơ sở. */
	private static function the_ds_may( $ds, $ky ) {
		if ( ! $ds ) { return; }
		echo '<div class="the"><h2>Danh sách máy</h2>';
		echo '<div class="cuon"><table class="stt"><thead><tr><th>Cơ sở</th><th>Serial đầu đọc</th><th>MAC bo</th>'
			. '<th>Nhịp cuối</th><th>Bản firmware</th><th>Đường</th><th>Chờ</th><th>Việc</th>'
			. '</tr></thead><tbody>';
		foreach ( $ds as $x ) {
			echo '<tr><td>' . ( $x['cua_hang'] ? esc_html( $x['cua_hang'] )
					: '<span class="chu-hong">(chưa gán)</span>' ) . '</td>'
				. '<td><code>' . esc_html( $x['serial'] ) . '</code></td>'
				. '<td><code>' . esc_html( $x['mac'] ) . '</code></td>'
				. '<td>' . ( ! empty( $x['con_song'] ) ? '🟢 ' : '🔴 ' )
					. esc_html( (string) $x['nhip_luc'] ) . '</td>'
				. '<td>' . esc_html( (string) $x['fw'] ) . '</td>'
				. '<td>' . esc_html( trim( $x['duong'] . ' ' . $x['ip'] . ' ' . $x['song'] ) ) . '</td>'
				. '<td>' . (int) $x['cho'] . '</td><td>';
			echo '<form method="post" class="hang" style="margin:0;gap:4px">';
			echo '<input type="hidden" name="ky" value="' . esc_attr( $ky ) . '">';
			echo '<input type="hidden" name="may_id" value="' . (int) $x['id'] . '">';
			echo '<select name="coso"><option value="">— chọn cơ sở —</option>';
			foreach ( VHCC_NhanSu::ds_coso() as $cs ) {
				echo '<option value="' . esc_attr( $cs ) . '"'
					. ( 0 === strcasecmp( (string) $cs, (string) $x['cua_hang'] ) ? ' selected' : '' )
					. '>' . esc_html( $cs ) . '</option>';
			}
			echo '</select>';
			echo '<button name="viec" value="may_gan">Gán</button>';
			echo '<button name="viec" value="may_quet">Quét sổ máy</button>';
			echo '<button name="viec" value="may_dung_tai_lai">Dừng tải lại</button>';
			echo '</form></td></tr>';
		}
		echo '</tbody></table></div>';
		echo '<p class="mo">Cơ sở lấy theo <b>mã thiết bị</b>, không tin tên máy tự khai. Đổi phần '
			. 'cứng thì hệ thống chỉ <b>ghi dấu</b> vào cột ghi chú, không tự sửa — "thay bo" và '
			. '"mang bo sang cửa hàng khác" nhìn từ máy chủ giống hệt nhau, mà đoán sai là chấm công '
			. 'cửa hàng mới chảy vào cơ sở cũ.</p>';
		echo '</div>';
	}

	/** 5. TẢI LẠI SỔ CHẤM CÔNG TỪ ĐẦU ĐỌC. */
	private static function the_tai_lai( $ds, $ky ) {
		if ( ! $ds ) { return; }
		echo '<div class="the"><h2>Tải lại sổ chấm công từ đầu đọc</h2>';
		echo '<p class="mo">Dùng khi máy vừa sống lại sau một đợt mất mạng: lượt bấm còn nằm trong '
			. 'đầu đọc, lệnh này bảo máy đọc lại và đẩy lên. Chạy bao nhiêu lần cũng ra một kết quả '
			. '— ô giờ vào/ra chỉ được <b>nới rộng</b>, không bao giờ bị thu hẹp.</p>';
		echo '<form method="post" class="hang">';
		echo '<input type="hidden" name="ky" value="' . esc_attr( $ky ) . '">';
		echo '<div><label>Máy</label>' . self::o_chon_may( $ds, 0 ) . '</div>';
		echo '<div><label>Từ ngày</label><input type="date" name="tu" required></div>';
		echo '<div><label>Đến ngày</label><input type="date" name="den" required></div>';
		echo '<div><label>Chỉ một mã NV</label>'
			. '<input type="text" name="ma_nv" placeholder="để trống = tất cả"></div>';
		echo '<button class="chay" name="viec" value="may_tai_lai">Tải lại</button>';
		echo '</form>';
		echo '<p class="mo">Tối đa 31 ngày mỗi đợt: máy đẩy từng lượt qua 4G nên khoảng rộng làm '
			. 'nghẽn đường truyền hàng giờ.</p></div>';
	}

	/**
	 * 6. SỔ MẶT TRONG ĐẦU ĐỌC.
	 *
	 * 🔴 Người nghỉ việc mà mặt còn trong máy thì VẪN chấm công được, và bảng lương vẫn tính —
	 *    không có gì tự báo, vì mỗi bên đều thấy mình đúng. Đây là chỗ duy nhất đối chiếu hai sổ.
	 */
	private static function the_so_mat( $ds, $ky ) {
		if ( ! $ds ) { return; }
		$xem = isset( $_GET['msoma'] ) ? (int) $_GET['msoma'] : 0;
		echo '<div class="the"><h2>Sổ mặt đang nằm trong đầu đọc</h2>';
		echo '<p class="mo"><b>Người nghỉ việc mà mặt còn trong máy thì VẪN chấm công được</b>, và '
			. 'bảng lương vẫn tính — không có gì tự báo, vì mỗi bên đều thấy mình đúng. Bấm '
			. '<b>Quét sổ máy</b> ở bảng trên, chờ khoảng một phút, rồi xem ở đây.</p>';
		echo '<form method="get" class="hang">';
		/* Biểu mẫu GET này thay CẢ địa chỉ, nên phải chở theo `man` — thiếu nó là bấm "Xem sổ
		   máy này" xong rơi về màn mặc định, và người ta tưởng nút hỏng. */
		echo '<input type="hidden" name="man" value="may">';
		if ( ! get_option( 'permalink_structure' ) ) {
			echo '<input type="hidden" name="vhcc_qt" value="1">';
		}
		echo '<div><label>Máy</label>' . self::o_chon_may( $ds, $xem, 'msoma' ) . '</div>';
		echo '<button>Xem sổ máy này</button></form>';
		if ( $xem > 0 ) { self::the_so_mat_mot( $xem ); }
		echo '</div>';
	}

	private static function the_so_mat_mot( $xem ) {
		$so = VHCC_May::roster( $xem );
		$dc = VHCC_May::doi_chieu_roster( $xem );
		if ( empty( $so['ok'] ) ) {
			echo '<div class="bao loi">' . esc_html( $so['error'] ) . '</div>';
			return;
		}
		if ( ! $so['data'] ) {
			echo '<p class="mo"><i>Máy này chưa đẩy sổ mặt lên lần nào. Bấm "Quét sổ máy" ở bảng '
				. 'trên.</i></p>';
			return;
		}
		echo '<p class="mo" style="margin-top:10px">Trong máy có <b>' . (int) $dc['soMay'] . '</b> mặt · '
			. 'hồ sơ cơ sở <b>' . esc_html( $dc['coso'] ) . '</b> có <b>' . (int) $dc['soWeb']
			. '</b> người.</p>';
		if ( $dc['thua'] ) {
			echo '<div class="bao loi"><b>' . count( $dc['thua'] ) . ' mặt còn trong máy mà hồ sơ '
				. 'không cho phép nữa</b> — những người này vẫn chấm công được:</div>';
			echo '<ul class="mo" style="margin:6px 0 0 18px">';
			foreach ( $dc['thua'] as $x ) {
				echo '<li><code>' . esc_html( $x['ma'] ) . '</code> ' . esc_html( $x['ten'] )
					. ' — ' . esc_html( $x['vi_sao'] ) . '</li>';
			}
			echo '</ul>';
		} else {
			echo '<p><span class="chu-luc">✓ Không có mặt nào thừa trong máy.</span></p>';
		}
		if ( $dc['thieu'] ) {
			$ten = array();
			foreach ( $dc['thieu'] as $x ) { $ten[] = $x['ma'] . ' ' . $x['ten']; }
			echo '<p class="mo"><b>' . count( $dc['thieu'] ) . ' người có hồ sơ mà chưa có mặt trong '
				. 'máy</b> (người mới chưa lấy mặt): ' . esc_html( implode( ' · ', $ten ) ) . '</p>';
		}
		echo '<details><summary><b>Cả sổ mặt</b> — ' . count( $so['data'] ) . ' dòng</summary>';
		echo '<div class="cuon"><table><thead><tr><th>Mã NV</th><th>Họ tên</th><th>Có ảnh mặt</th>'
			. '<th>Quét lúc</th></tr></thead><tbody>';
		foreach ( $so['data'] as $r ) {
			echo '<tr><td><code>' . esc_html( $r['ma_nv'] ) . '</code></td>'
				. '<td>' . esc_html( $r['ho_ten'] ) . '</td>'
				. '<td>' . ( (int) $r['co_anh'] ? '✔️' : '—' ) . '</td>'
				. '<td>' . esc_html( (string) $r['cap_nhat'] ) . '</td></tr>';
		}
		echo '</tbody></table></div></details>';
	}

	/**
	 * 7. LƯỢT BẤM CHỜ GÁN.
	 *
	 * Máy chưa gán cơ sở vẫn được NHẬN và GIỮ lượt bấm — bỏ là mất công của người thật chỉ vì
	 * cái máy chưa được khai.
	 */
	private static function the_cho_gan() {
		$cg = VHCC_DB::rows( 'SELECT * FROM ' . VHCC_DB::t( 'cho_gan' )
			. " WHERE da_chuyen='' ORDER BY nhan_luc DESC LIMIT 200" );
		echo '<div class="the"><details' . ( $cg ? ' open' : '' ) . '>';
		echo '<summary><b>Lượt bấm chờ gán</b> — ' . count( $cg ) . '</summary>';
		echo '<p class="mo">Máy chưa gán cơ sở vẫn được nhận và <b>giữ</b> lượt bấm ở đây — bỏ là '
			. 'mất công của người thật chỉ vì cái máy chưa được khai. <b>Gán cơ sở cho máy là các '
			. 'lượt này tự vào bảng chấm công</b>, không phải gõ tay lại.</p>';
		if ( ! $cg ) {
			echo '<p class="mo"><i>Không có lượt nào đang chờ.</i></p>';
		} else {
			echo '<div class="cuon"><table><thead><tr><th>Nhận lúc</th><th>Serial</th><th>MAC</th>'
				. '<th>Máy tự khai</th><th>Mã NV</th><th>Họ tên</th><th>Thời điểm</th>'
				. '</tr></thead><tbody>';
			foreach ( $cg as $r ) {
				echo '<tr><td>' . esc_html( $r['nhan_luc'] ) . '</td>'
					. '<td><code>' . esc_html( $r['serial'] ) . '</code></td>'
					. '<td><code>' . esc_html( $r['mac'] ) . '</code></td>'
					. '<td>' . esc_html( $r['ten_tu_khai'] ) . '</td>'
					. '<td><code>' . esc_html( $r['ma_nv'] ) . '</code></td>'
					. '<td>' . esc_html( $r['ho_ten'] ) . '</td>'
					. '<td>' . esc_html( $r['thoi_diem'] ) . '</td></tr>';
			}
			echo '</tbody></table></div>';
		}
		echo '</details></div>';
	}

	/** 8. HÀNG ĐỢI LỆNH. */
	private static function the_lenh( $ky ) {
		$lenh = VHCC_May::ds_lenh( '', false, 100 );
		echo '<div class="the"><details' . ( $lenh ? ' open' : '' ) . '>';
		echo '<summary><b>Lệnh đang chờ xuống máy</b> — ' . count( $lenh ) . '</summary>';
		echo '<p class="mo">Hàng đợi này nằm trên <b>chính website</b> — trước 22/08/2026 nó nằm trên '
			. 'Firebase. Lệnh đã gửi mà máy chưa báo xong thì <b>vẫn được gửi lại</b>: "đã gửi" không '
			. 'có nghĩa là "máy nhận được", nhất là trên 4G. Firmware có sổ riêng nên nhận lại lệnh '
			. 'cũ thì nó tự bỏ, không có chuyện thêm hai lần một người.</p>';
		if ( ! $lenh ) {
			echo '<p class="mo"><i>Không có lệnh nào đang chờ.</i></p></details></div>';
			return;
		}
		echo '<div class="cuon"><table><thead><tr><th>Đặt lúc</th><th>Lệnh</th><th>Máy</th>'
			. '<th>Nhân viên</th><th>Khoảng</th><th>Trạng thái</th><th></th></tr></thead><tbody>';
		foreach ( $lenh as $q ) {
			$da_gui = ( VHCC_MayCong::GUI === $q['trang_thai'] );
			echo '<tr><td>' . esc_html( (string) $q['tao_luc'] ) . '</td>'
				. '<td><code>' . esc_html( $q['action'] ) . '</code></td>'
				. '<td>' . esc_html( $q['cua_hang'] ? $q['cua_hang'] : $q['tram'] ) . '</td>'
				. '<td>' . esc_html( trim( $q['ma_nv'] . ' ' . $q['ho_ten'] ) ) . '</td>'
				. '<td>' . esc_html( trim( $q['tu_gio'] . ' → ' . $q['den_gio'], ' →' ) ) . '</td>'
				. '<td>' . ( $da_gui ? 'đã gửi ' . esc_html( (string) $q['gui_luc'] ) : 'đang chờ' ) . '</td>'
				. '<td><form method="post" style="margin:0">'
				. '<input type="hidden" name="ky" value="' . esc_attr( $ky ) . '">'
				. '<input type="hidden" name="op_id" value="' . esc_attr( $q['op_id'] ) . '">'
				. '<button name="viec" value="may_xoa_lenh">Xoá</button>'
				. '</form></td></tr>';
		}
		echo '</tbody></table></div></details></div>';
	}

	/**
	 * 9. FIRMWARE / OTA.
	 *
	 * 🔴 KHỐI NGUY HIỂM NHẤT CỦA CẢ PHẦN MỀM. Link .bin sai dạng là mọi máy 4G không bao giờ tải
	 *    được bản mới — mất luôn đường sửa từ xa của cả chuỗi, phải đi từng cửa hàng cắm USB.
	 *    `VHCC_May::ota_url_hop_le()` chặn link sai dạng, nhưng nó không biết bản .bin có chạy
	 *    được không. Nên nút "thử một máy" phải đứng TRƯỚC nút đẩy cả chuỗi, và đứng gần hơn.
	 */
	private static function the_firmware( $ds, $ky ) {
		echo '<div class="the"><h2>Cập nhật firmware</h2>';
		$ota = VHCC_May::ota_dang_dat();
		$o   = $ota['data'];
		echo '<p class="mo">Lệnh đang đặt cho cả chuỗi: ' . ( '' !== $o['ver']
			? '<b>' . esc_html( $o['ver'] ) . '</b> · <code>' . esc_html( $o['url'] ) . '</code>'
				. ( $o['luc'] ? ' · đặt lúc ' . esc_html( $o['luc'] ) : '' )
			: '<i>không có</i>' ) . '</p>';

		$fw = VHCC_May::fw_dang_chay();
		if ( ! empty( $fw['data'] ) ) {
			$phan = array();
			foreach ( $fw['data'] as $f ) {
				$phan[] = '<b>' . esc_html( $f['ver'] ) . '</b> (' . (int) $f['so'] . ' máy)';
			}
			echo '<p class="mo">Máy đang chạy: ' . implode( ' · ', $phan ) . '</p>';
			if ( count( $fw['data'] ) > 1 ) {
				echo '<p class="mo">Nhiều bản cùng chạy là bình thường ngay sau một lượt đẩy — máy '
					. 'nhận trong vòng 60 giây rồi tải và khởi động lại. Còn lệch sau vài giờ thì máy '
					. 'đó không tải được: xem lại link .bin và SIM của nó.</p>';
			}
		}

		echo '<div class="bao loi"><b>Đọc trước khi đẩy.</b> Lệnh này nạp firmware cho <b>MỌI máy '
			. 'trong chuỗi</b>. Link phải là link <code>raw</code> của nhánh <code>bin</code> — link '
			. '<i>release</i> của GitHub trả HTTP 302 rồi chuyển hướng dài ~943 ký tự, mà module 4G '
			. 'chết ở khoảng 532 ký tự: đẩy link đó là mọi máy 4G <b>không bao giờ</b> tải được, tức '
			. 'mất luôn đường sửa từ xa và phải đi từng cửa hàng cắm USB. Hệ thống chặn link sai '
			. 'dạng, nhưng <b>hãy thử một máy trước</b> — bản hỏng đẩy cho cả chuỗi thì không còn '
			. 'đường gọi về.</div>';

		echo '<form method="post">';
		echo '<input type="hidden" name="ky" value="' . esc_attr( $ky ) . '">';
		echo '<div class="hang">';
		echo '<div><label>Phiên bản *</label><input type="text" name="ver" required></div>';
		echo '<div style="flex:1;min-width:280px"><label>Link .bin (raw, nhánh bin) *</label>'
			. '<input type="text" name="url" style="width:100%" required></div>';
		echo '</div>';
		if ( $ds ) {
			/* Thử MỘT máy đứng TRƯỚC, và không đòi gõ xác nhận: đây là bước nên làm, đừng dựng
			   rào ở đúng cái việc mình muốn người ta làm. */
			echo '<div class="hang" style="margin-top:10px">';
			echo '<div><label>Thử riêng một máy</label>' . self::o_chon_may( $ds, 0 ) . '</div>';
			echo '<button class="chinh" name="viec" value="may_ota_mot">Đặt riêng cho máy này</button>';
			echo '</div>';
			echo '<p class="mo">Không cần gõ xác nhận — đây chính là bước nên làm trước.</p>';
		}
		echo '<div class="hang" style="margin-top:10px">';
		echo '<div><label>Gõ đúng chữ DONG Y để đẩy cả chuỗi</label>'
			. '<input type="text" name="xac_nhan"></div>';
		echo '<button class="nguy" name="viec" value="may_ota">Đẩy cập nhật cho cả chuỗi</button>';
		echo '<button name="viec" value="may_go_ota">Gỡ lệnh cập nhật của cả chuỗi</button>';
		echo '</div></form></div>';
	}

	/** Ô xổ chọn máy — dùng ở ba khối, nên một chỗ dựng. */
	private static function o_chon_may( $ds, $chon, $ten = 'may_id' ) {
		$h = '<select name="' . esc_attr( $ten ) . '">';
		foreach ( $ds as $x ) {
			$h .= '<option value="' . (int) $x['id'] . '"'
				. ( (int) $chon === (int) $x['id'] ? ' selected' : '' ) . '>'
				. esc_html( ( $x['cua_hang'] ? $x['cua_hang'] : '(chưa gán)' ) . ' — '
					. ( $x['serial'] ? $x['serial'] : $x['mac'] ) ) . '</option>';
		}
		return $h . '</select>';
	}
}
