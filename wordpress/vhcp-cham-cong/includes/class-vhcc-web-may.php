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
	 * 🔴 Gác `he_thong` NGAY ĐÂY, đừng tin vào việc màn không vẽ nút cho người không đủ bậc.
	 *    Nút không vẽ chỉ là không mời; POST thì ai gửi cũng tới.
	 */
	public static function viec( $viec, $toi ) {
		if ( ! VHCC_Vai::duoc( $toi, 'he_thong' ) ) {
			return array( array( 'loi' => 'Màn Máy & Firmware cần bậc Admin. '
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
		if ( ! VHCC_Vai::duoc( $toi, 'he_thong' ) ) {
			echo '<div class="the"><h2>Không vào được màn này</h2>';
			echo '<p class="mo">Màn <b>Máy &amp; Firmware</b> cần bậc <b>Admin</b>. Ở đây có nút đẩy '
				. 'firmware cho <b>mọi máy trong chuỗi</b> — đẩy nhầm một bản là mất luôn đường sửa '
				. 'từ xa của cả 26 cửa hàng.</p></div>';
			return;
		}
		$m  = VHCC_May::ds_may();
		$ds = ! empty( $m['ok'] ) ? (array) $m['data'] : array();

		self::the_cong();
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
			echo '<div class="cuon"><table><thead><tr><th>Lúc</th><th>Mã</th><th>Chi tiết</th>'
				. '</tr></thead><tbody>';
			foreach ( $nk as $d ) {
				echo '<tr><td>' . esc_html( isset( $d['luc'] ) ? $d['luc'] : '' ) . '</td>'
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
		echo '<div class="cuon"><table><thead><tr><th>Cơ sở</th><th>Serial đầu đọc</th><th>MAC bo</th>'
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
		echo '<button class="chinh" name="viec" value="may_tai_lai">Tải lại</button>';
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
