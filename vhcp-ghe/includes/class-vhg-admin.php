<?php
/**
 * MÀN HÌNH — Ghế massage.
 *
 * ⚠️ MỌI MÀN Ở ĐÂY ĐỀU MỎNG: gác quyền, đọc biểu mẫu, gọi lớp nghiệp vụ, hiện kết quả. Không một
 *    dòng luật tiền nào, không một câu ghi bảng nào. Hai bản luật là sớm muộn lệch nhau, và lúc
 *    lệch thì màn cho bấm mà lớp dưới chặn — hoặc tệ hơn, ngược lại.
 *
 * ⚠️ KHÔNG CÓ PIN RIÊNG. Bản Apps Script gác bằng `DASHBOARD_PIN` ghi thẳng trong mã, mà mã thì
 *    nằm ở repo công khai — ai đọc được là bật/tắt được ghế và xoá được doanh thu. Ở đây màn nằm
 *    trong wp-admin: người xem phải đăng nhập WordPress, và quyền thì WordPress đã quản. Bớt
 *    được một bí mật là bớt một chỗ lộ.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHG_Admin {

	const CAP = 'manage_options';

	/**
	 * KHỐI VÍ trên màn Máy & cơ sở: khai gói nạp, nhìn khoản nợ, tra một ví.
	 *
	 * 🔴 KHOẢN NỢ ĐỨNG NGAY CẠNH BẢNG GÓI NẠP, không giấu ở tab khác.
	 *    Bán gói nạp thì tháng nào doanh thu cũng đẹp — tiền vào trước, dịch vụ trả sau. Con số
	 *    duy nhất nói ra sự thật là "khách đã trả tiền nhưng chưa tiêu bao nhiêu", và nó phải
	 *    nằm đúng chỗ người ta ngồi khai gói nạp, đúng lúc họ định nâng mức tặng thêm.
	 */
	public static function khoi_vi() {
		$gn = VHG_Vi::goi_nap();
		$no = VHG_Vi::tong_no();

		echo '<h2>Gói nạp ví</h2>';
		echo '<p class="description">Nạp trước, tiêu dần ở bất kỳ ghế nào. Khác với mã: số dư '
			. '<b>tiêu lẻ từng lượt</b>, không phải một mã một lượt.</p>';

		/* 🔴 NỢ TRƯỚC, BẢNG SAU. Người mở màn này để nâng mức tặng thêm phải nhìn thấy khoản nợ
		   hiện tại TRƯỚC khi gõ con số mới. */
		$mau = $no['tong'] > 0 ? '#b32d2e' : '#666';
		echo '<div style="border-left:4px solid ' . esc_attr( $mau ) . ';background:#fff;'
			. 'padding:10px 14px;max-width:900px;margin:0 0 14px">'
			. '<b>Khách đã trả tiền nhưng chưa tiêu: <span style="color:' . esc_attr( $mau )
			. ';font-size:16px">' . esc_html( self::tien( $no['tong'] ) ) . '</span></b>'
			. ' <span class="description">(' . (int) $no['so_vi'] . ' ví'
			. ( $no['cho'] > 0 ? ', trong đó ' . esc_html( self::tien( $no['cho'] ) )
				. ' còn trong hạn chờ' : '' ) . ')</span>'
			. '<div class="description" style="margin-top:4px">Đây là <b>khoản nợ</b>, không phải '
			. 'doanh thu để dành: tiền đã vào tài khoản, nhưng dịch vụ thì chưa trả. Nâng mức tặng '
			. 'thêm là nâng luôn con số này.</div></div>';

		echo '<form method="post"><table class="widefat striped" style="max-width:640px"><thead><tr>'
			. '<th>Khách trả</th><th>Khách nhận vào ví</th><th>Được thêm</th><th>Lợi</th>'
			. '</tr></thead><tbody>';
		wp_nonce_field( 'vhg' );
		/* Luôn thừa một dòng trống để khai thêm mà không phải bấm "thêm dòng". */
		$so_dong = max( 4, count( $gn ) + 1 );
		for ( $i = 0; $i < $so_dong; $i++ ) {
			$g = isset( $gn[ $i ] ) ? $gn[ $i ] : array( 'nap' => '', 'nhan' => '', 'them' => 0, 'loi_pt' => 0 );
			echo '<tr><td><input type="number" name="gn_nap[]" min="1000" step="1000" value="'
				. ( '' === $g['nap'] ? '' : (int) $g['nap'] ) . '" style="width:130px" /></td>'
				. '<td><input type="number" name="gn_nhan[]" min="1000" step="1000" value="'
				. ( '' === $g['nhan'] ? '' : (int) $g['nhan'] ) . '" style="width:130px" /></td>'
				. '<td>' . ( $g['them'] > 0 ? esc_html( self::tien( $g['them'] ) ) : '—' ) . '</td>'
				. '<td>' . ( $g['loi_pt'] > 0 ? '+' . (int) $g['loi_pt'] . '%' : '—' ) . '</td></tr>';
		}
		echo '</tbody></table><p><button class="button button-primary" name="vhg" value="goi_nap">'
			. 'Lưu gói nạp</button></p></form>';
		echo '<p class="description">Bỏ trống cả hai ô = bỏ gói đó. Khách nhận <b>phải ≥</b> khách '
			. 'trả — nhận ít hơn thì khách lỗ, hệ thống từ chối lưu.<br>'
			. 'Số dư nạp cũng có <b>hạn chờ</b> giống mã mua trước (đang đặt: '
			. (int) VHG_Ma::cho_ngay_mac_dinh() . ' ngày), và dùng chung ô cài đặt đó.</p>';

		/* ⚠️ CÔNG TẮC bán mã lẻ để NGAY DƯỚI bảng gói nạp — đó là nơi người ta vừa quyết định
		   chuyển hẳn sang bán gói nạp, nên cũng là nơi họ tìm cách tắt cái cũ. */
		$dang_ban = VHG_Ma::con_ban_ma();
		echo '<h3>Bán mã lẻ</h3>';
		echo '<form method="post" style="max-width:900px">';
		wp_nonce_field( 'vhg' );
		echo '<p><label><input type="checkbox" name="ban_ma_bat" value="1"'
			. ( $dang_ban ? ' checked' : '' ) . ' /> <b>Còn bán mã lẻ</b> '
			. '(khách mua từng mã một, dùng một lần)</label></p>';
		echo '<p><button class="button" name="vhg" value="ban_ma">Lưu</button></p></form>';
		echo '<p class="description">Bỏ tích = trang khách chỉ còn <b>Nạp ví</b>, và cổng cũng '
			. 'từ chối đơn mua mã mới (không chỉ giấu tab — giấu tab mà cổng vẫn nhận là ai còn '
			. 'giữ link cũ vẫn đặt được đơn rồi trả tiền cho thứ mình đã ngừng bán).<br>'
			. '<b>Mã đã bán vẫn dùng được bình thường</b> — tắt là ngừng bán thêm, không phải '
			. 'huỷ hàng đã bán.</p>';

		/* Danh sách ví còn tiền: nợ nằm ở đâu, ai giữ nhiều nhất. */
		$ds_vi = VHG_Vi::ds_vi( 30 );
		if ( $ds_vi ) {
			echo '<h3>Ví còn tiền (' . count( $ds_vi ) . ' nhiều nhất)</h3>';
			echo '<table class="widefat striped" style="max-width:900px"><thead><tr>'
				. '<th>Số điện thoại</th><th>Tiêu được</th><th>Đang chờ</th><th>Đã nạp</th>'
				. '<th>Đã tiêu</th><th>Trạng thái</th></tr></thead><tbody>';
			foreach ( $ds_vi as $v ) {
				/* ⚠️ CHE SỐ ĐIỆN THOẠI. Màn này nhân viên ca nào cũng mở; in đủ số là biến bảng
				   tiền thành danh bạ khách hàng, bôi đen là chép được cả nghìn số. */
				echo '<tr><td>' . esc_html( VHG_Ma::sdt_che( $v['sdt'] ) ) . '</td>'
					. '<td>' . esc_html( self::tien( (int) $v['so_du_dung'] ) ) . '</td>'
					. '<td>' . ( (int) $v['so_du_cho'] > 0
						? esc_html( self::tien( (int) $v['so_du_cho'] ) ) : '—' ) . '</td>'
					. '<td>' . esc_html( self::tien( (int) $v['da_nap'] ) ) . '</td>'
					. '<td>' . esc_html( self::tien( (int) $v['da_tieu'] ) ) . '</td>'
					. '<td>' . ( ! empty( $v['khoa'] ) ? '<b style="color:#b32d2e">ĐANG KHOÁ</b>' : 'bình thường' )
					. '</td></tr>';
			}
			echo '</tbody></table>';
		}

		/* Chỉnh tay + khoá. Để CUỐI khối và trong một form riêng: đây là đường đụng thẳng vào
		   tiền của khách, không nên nằm lẫn với ô khai cấu hình. */
		echo '<h3>Chỉnh ví một khách</h3>';
		echo '<form method="post" style="max-width:900px">';
		wp_nonce_field( 'vhg' );
		echo '<p><label>Số điện thoại <input name="vi_sdt" style="width:160px" placeholder="0909123456" /></label> '
			. '<label>Số tiền <input type="number" name="vi_tien" step="1000" style="width:130px" '
			. 'placeholder="50000 hoặc -50000" /></label> '
			. '<label>Lý do <input name="vi_ly_do" style="width:280px" placeholder="đền bù ghế hỏng giữa lượt" /></label></p>';
		echo '<p><button class="button" name="vhg" value="vi_chinh">Cộng / trừ số dư</button> '
			. '<button class="button" name="vhg" value="vi_khoa">Khoá ví</button> '
			. '<button class="button" name="vhg" value="vi_mo">Mở khoá</button></p>';
		echo '</form>';
		echo '<p class="description"><b>Lý do là bắt buộc</b> khi cộng/trừ — một dòng "+500.000đ" '
			. 'không lý do là thứ không ai giải thích được sau ba tháng, kể cả người vừa bấm. '
			. 'Mọi thay đổi đều ghi vào sổ ví kèm tên người làm.<br>'
			. 'Số âm để trừ (VD <code>-50000</code>). Trừ quá số dư thì hệ thống từ chối — ví không '
			. 'bao giờ âm.</p>';
	}

	/**
	 * Ô khai TÍCH LƯỢT ƯU ĐÃI.
	 *
	 * Anh Thắng 23/08/2026: *"10k 1 lượt tích"*, *"sau 10 lượt, khách được ưu đãi tặng quà"*.
	 *
	 * 🔴 HIỆN CHI PHÍ CỦA CHƯƠNG TRÌNH NGAY CẠNH Ô KHAI, cùng lý do với khoản nợ ví: người đang
	 *    ngồi hạ mốc từ 10 xuống 5 phải nhìn thấy mình đã phát bao nhiêu quà và còn nợ bao nhiêu
	 *    phần chưa trao, TRƯỚC khi gõ con số mới.
	 */
	public static function khoi_tich() {
		$cf = VHG_Vi::tich_cf();
		$tq = VHG_Vi::tong_qua();
		echo '<h2>Tích lượt ưu đãi</h2>';
		/* 🔴 CÂU NÀY PHẢI ĐÚNG LUẬT ĐANG CHẠY. Bản trước ghi ngược hẳn (tiêu ví thì tích, trả QR
		   thì không) — và người đọc nó là người quyết định bật hay tắt cả chương trình. Luật nay
		   theo anh Thắng 23/08/2026: *"Tích lượt qua quét QR tại máy luôn, chỉ có tiền mặt thì
		   không"*, và *"nạp ví thì nó có ưu đãi sẵn rồi"*. */
		echo '<p class="description"><b>Chỉ đường CHUYỂN KHOẢN tại ghế mới tích lượt</b> — khách mở '
			. 'trang từ tem QR trên ghế, đăng nhập ví, rồi chọn mệnh giá và trả bằng ngân hàng.<br>'
			. 'Tiêu bằng SỐ DƯ VÍ thì <b>không</b> tích: tiền nạp đã được khuyến mãi một lần rồi.<br>'
			. 'Tiền mặt, và QR quét thẳng trên màn hình ghế, cũng <b>không</b> tích — hai đường đó '
			. 'không mang số điện thoại nên hệ thống không biết đó là ai.</p>';

		$mau_q = $tq['cho'] > 0 ? '#b32d2e' : '#666';
		echo '<div style="border-left:4px solid ' . esc_attr( $mau_q ) . ';background:#fff;'
			. 'padding:10px 14px;max-width:900px;margin:0 0 14px">'
			. '<b>Đã phát ' . (int) $tq['so'] . ' phần thưởng</b>'
			. ( $tq['tien'] > 0 ? ' · đã cộng vào ví ' . esc_html( self::tien( $tq['tien'] ) ) : '' )
			. ' · <span style="color:' . esc_attr( $mau_q ) . '"><b>' . (int) $tq['cho']
			. ' phần quà chưa trao</b></span>'
			. '<div class="description" style="margin-top:4px">Hạ mốc xuống là quà phát nhanh hơn — '
			. 'và cả hai con số trên cùng lớn theo.</div></div>';

		echo '<form method="post" style="max-width:900px">';
		wp_nonce_field( 'vhg' );
		echo '<p><label><input type="checkbox" name="tich_bat" value="1"'
			. ( ! empty( $cf['bat'] ) ? ' checked' : '' ) . ' /> <b>Bật tích lượt</b></label></p>';
		echo '<table class="form-table"><tbody>';
		echo '<tr><th scope="row">Mỗi lượt tích</th><td>'
			. '<input type="number" name="tich_moi_luot" min="1000" step="1000" value="'
			. (int) $cf['moi_luot'] . '" style="width:140px" /> đ chuyển khoản tại ghế'
			. '<p class="description">Trả 50.000đ với mức 10.000đ = 5 lượt. Quy đổi làm tròn xuống, '
			. 'và tính trên SỐ TIỀN THẬT nhận được chứ không tính trên mệnh giá khách chọn.</p>'
			. '</td></tr>';
		echo '<tr><th scope="row">Mốc thưởng</th><td>'
			. '<input type="number" name="tich_moc" min="2" max="100" value="'
			. (int) $cf['moc'] . '" style="width:100px" /> lượt</td></tr>';
		echo '<tr><th scope="row">Phần thưởng</th><td><select name="tich_kieu">';
		foreach ( array(
			'ca_hai' => 'Cả hai — cộng tiền vào ví VÀ tặng quà tại quầy',
			'luot'   => 'Chỉ lượt miễn phí — cộng thẳng tiền vào ví',
			'qua'    => 'Chỉ quà tại quầy — nhân viên trao tay',
		) as $k => $v ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $cf['kieu'], $k, false ) . '>'
				. esc_html( $v ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th scope="row">Trị giá lượt miễn phí</th><td>'
			. '<input type="number" name="tich_gia_tri" min="0" step="1000" value="'
			. (int) $cf['gia_tri'] . '" style="width:140px" /> đ'
			. '<p class="description">Cộng thẳng vào ví khách, <b>không có hạn chờ</b> — quà mà bắt '
			. 'chờ thêm 5 ngày thì thành phiền. Để 0 chỉ được khi chọn "Chỉ quà tại quầy".</p></td></tr>';
		echo '<tr><th scope="row">Tên quà</th><td>'
			. '<input name="tich_ten_qua" value="' . esc_attr( $cf['ten_qua'] ) . '" '
			. 'class="regular-text" style="width:100%;max-width:420px" '
			. 'placeholder="VD: Khăn bông K&amp;H" /></td></tr>';
		echo '</tbody></table>';
		echo '<p><button class="button button-primary" name="vhg" value="tich">Lưu tích lượt</button></p>';
		echo '</form>';
	}

	/**
	 * Ô khai CHÂN TRANG PHÁP LÝ.
	 *
	 * Anh Thắng 23/08/2026: *"cuối trang bổ sung nội dung này cho uy tín"*.
	 *
	 * 🔴 KHAI ĐƯỢC, KHÔNG NHÉT CỨNG. Địa chỉ công ty đổi, người đại diện đổi, số điện thoại đổi
	 *    — nhét cứng là mỗi lần đổi phải sửa mã rồi cài lại plugin cho một dòng chữ, và trong
	 *    lúc chờ thì trang đang nói SAI thông tin pháp lý của chính mình, đúng chỗ đặt ra để
	 *    tạo tin cậy.
	 */
	public static function khoi_chan() {
		$c = VHG_Chan::thong_tin();
		echo '<h2>Chân trang (thông tin công ty)</h2>';
		echo '<p class="description">Hiện ở cuối <b>trang khách</b> (/' . esc_html( VHG_Shop::slug() )
			. ') và <b>trang nhân viên</b> (/' . esc_html( VHG_Trang::slug() ) . '). '
			. 'Khách chuyển tiền cho một cái mã QR thì họ cần biết mình đang trả cho ai.</p>';
		echo '<form method="post" style="max-width:900px">';
		wp_nonce_field( 'vhg' );
		echo '<p><label><input type="checkbox" name="chan_hien" value="1"'
			. ( ! empty( $c['hien'] ) ? ' checked' : '' ) . ' /> <b>Hiện chân trang</b></label></p>';
		echo '<table class="form-table"><tbody>';
		$nhan = array(
			'ten'        => 'Tên công ty',
			'ten_qt'     => 'Tên quốc tế',
			'mst'        => 'Mã số thuế',
			'dia_chi'    => 'Địa chỉ',
			'dai_dien'   => 'Người đại diện',
			'dien_thoai' => 'Điện thoại',
			'email'      => 'Email',
			'ngay_hd'    => 'Hoạt động từ',
			'co_quan'    => 'Cơ quan quản lý thuế',
		);
		foreach ( $nhan as $k => $v ) {
			echo '<tr><th scope="row">' . esc_html( $v ) . '</th><td>'
				. '<input name="chan_' . esc_attr( $k ) . '" value="' . esc_attr( $c[ $k ] ) . '" '
				. 'class="regular-text" style="width:100%;max-width:560px" /></td></tr>';
		}
		echo '<tr><th scope="row">Chi nhánh</th><td>'
			. '<textarea name="chan_chi_nhanh" rows="6" style="width:100%;max-width:560px">'
			. esc_textarea( $c['chi_nhanh'] ) . '</textarea>'
			. '<p class="description">Mỗi dòng một chi nhánh.</p></td></tr>';
		echo '</tbody></table>';
		echo '<p><button class="button button-primary" name="vhg" value="chan">Lưu chân trang</button></p>';
		echo '</form>';
		/* Xem trước NGAY TẠI ĐÂY bằng chính hàm dựng thật — chứ đừng dựng một bản xem trước
		   riêng. Hai bản dựng là có ngày bản xem trước nói một đằng, trang thật hiện một nẻo. */
		$xem_chan = VHG_Chan::html();
		if ( '' !== $xem_chan ) {
			echo '<h3>Xem trước</h3>';
			echo '<div style="background:#1b1a17;border-radius:8px;max-width:900px;overflow:hidden">'
				. '<style>' . VHG_Chan::css() . '</style>' . $xem_chan . '</div>';
		}
	}

	/** Tên người đang thao tác — để ghi vào sổ ví. Mọi lượt chỉnh tay phải có tên, không thì
	    ba tháng sau không ai giải thích được dòng "+500.000đ" ấy là của ai. */
	public static function ai() {
		$u = function_exists( 'wp_get_current_user' ) ? wp_get_current_user() : null;
		$t = $u && ! empty( $u->display_name ) ? (string) $u->display_name : '';
		return '' !== $t ? $t : 'quản trị';
	}

	/**
	 * Địa chỉ màn đối soát — Trang Vận Hành đọc hàm này để dựng thẻ.
	 *
	 * ⚠️ Trỏ vào wp-admin, KHÁC hai app kia (chúng có trang công khai gác bằng PIN riêng). Nghĩa
	 *    là người xem phải có tài khoản WordPress. Đó là CỐ Ý: màn này xem được doanh thu, bật
	 *    tắt được ghế và xoá được bản ghi tiền — không phải thứ để sau một mã PIN sáu số.
	 */
	public static function app_url() {
		return admin_url( 'admin.php?page=vhg' );
	}

	public static function menu() {
		add_menu_page( 'Ghế Massage', 'Ghế Massage', self::CAP, 'vhg', array( __CLASS__, 'trang_thu' ), 'dashicons-money-alt', 27 );
		add_submenu_page( 'vhg', 'Đối soát doanh thu', 'Đối soát doanh thu', self::CAP, 'vhg', array( __CLASS__, 'trang_thu' ) );
		add_submenu_page( 'vhg', 'Máy & cơ sở', 'Máy & cơ sở', self::CAP, 'vhg-may', array( __CLASS__, 'trang_may' ) );
		add_submenu_page( 'vhg', 'Nhận tiền & nhật ký', 'Nhận tiền & nhật ký', self::CAP, 'vhg-cong', array( __CLASS__, 'trang_cong' ) );
		add_submenu_page( 'vhg', 'Trang ngoài & PIN', 'Trang ngoài & PIN', self::CAP, 'vhg-trang', array( __CLASS__, 'trang_ngoai' ) );
		add_submenu_page( 'vhg', 'Tem QR dán ghế', 'Tem QR dán ghế', self::CAP, 'vhg-tem', array( __CLASS__, 'trang_tem' ) );
		add_submenu_page( 'vhg', 'Chốt tiền (chỉ số ghế)', 'Chốt tiền (chỉ số ghế)', self::CAP, 'vhg-chottien', array( __CLASS__, 'trang_chottien' ) );
		add_submenu_page( 'vhg', 'Nạp file firmware', 'Nạp file firmware', self::CAP, 'vhg-fw', array( __CLASS__, 'trang_fw' ) );
	}

	// ======================================================================= tiện ích chung

	private static function gac() {
		if ( ! current_user_can( self::CAP ) ) { wp_die( 'Không đủ quyền.' ); }
	}

	public static function ve_bao( $ds ) {
		foreach ( (array) $ds as $b ) {
			if ( ! empty( $b['ok'] ) ) {
				echo '<div class="notice notice-success"><p>'
					. esc_html( ! empty( $b['thong_bao'] ) ? $b['thong_bao'] : 'Đã lưu.' ) . '</p></div>';
			} else {
				echo '<div class="notice notice-error"><p>'
					. esc_html( isset( $b['error'] ) ? $b['error'] : 'Không chạy được lệnh.' ) . '</p></div>';
			}
		}
	}

	/**
	 * Giờ đọc được cho người: "22/08 14:46".
	 *
	 * ⚠️ KHÔNG đổi múi giờ ở đây. Chuỗi trong bảng đã là giờ của site (`current_time('mysql')`),
	 *    mà WordPress đặt múi giờ PHP về UTC — nên `strtotime` + `gmdate` đưa lại đúng con số
	 *    đã lưu. Thêm một phép đổi múi nữa là lệch 7 tiếng, và lệch đúng theo kiểu trông vẫn
	 *    như một cái giờ thật.
	 */
	private static function gio( $s ) {
		$s = trim( (string) $s );
		if ( '' === $s || 0 === strpos( $s, '0000-00-00' ) ) { return ''; }
		$t = strtotime( $s );
		return false === $t ? $s : gmdate( 'd/m H:i', $t );
	}

	public static function tien( $n ) {
		return number_format( (int) $n, 0, ',', '.' ) . 'đ';
	}

	private static function ky() {
		$k = isset( $_GET['ky'] ) ? sanitize_key( wp_unslash( $_GET['ky'] ) ) : 'today';
		return in_array( $k, array( 'today', 'week', 'month', 'year', 'all' ), true ) ? $k : 'today';
	}

	private static function chon_ky( $trang ) {
		$ky = self::ky();
		$ten = array( 'today' => 'Hôm nay', 'week' => 'Tuần này', 'month' => 'Tháng này',
			'year' => 'Năm nay', 'all' => 'Tất cả' );
		echo '<p>';
		foreach ( $ten as $k => $t ) {
			$url = add_query_arg( array( 'page' => $trang, 'ky' => $k ), admin_url( 'admin.php' ) );
			echo '<a class="button' . ( $k === $ky ? ' button-primary' : '' ) . '" href="'
				. esc_url( $url ) . '">' . esc_html( $t ) . '</a> ';
		}
		echo '</p>';
		return $ky;
	}

	// ======================================================================= 1. ĐỐI SOÁT

	public static function trang_thu() {
		self::gac();
		$bao = array();
		if ( isset( $_POST['vhg'] ) ) {
			check_admin_referer( 'vhg' );
			$viec = sanitize_text_field( wp_unslash( $_POST['vhg'] ) );
			$nguoi = wp_get_current_user()->display_name;
			if ( 'tien_mat' === $viec ) {
				$bao[] = VHG_Thu::thu_tien_mat( wp_unslash( $_POST['ma_may'] ), wp_unslash( $_POST['so_tien'] ), $nguoi );
			} elseif ( 'bat' === $viec ) {
				$bao[] = VHG_May::dat_lenh( wp_unslash( $_POST['ma_may'] ), 'on',
					isset( $_POST['phut'] ) ? wp_unslash( $_POST['phut'] ) : 0, $nguoi,
					isset( $_POST['ly_do'] ) ? wp_unslash( $_POST['ly_do'] ) : '' );
			} elseif ( 'tat' === $viec ) {
				$bao[] = VHG_May::dat_lenh( wp_unslash( $_POST['ma_may'] ), 'off', 0, $nguoi, '' );
			} elseif ( 'huy_gd' === $viec ) {
				$bao[] = VHG_Thu::huy( wp_unslash( $_POST['ref'] ), 'gỡ tay bởi ' . $nguoi );
			} elseif ( 'bo_huy_gd' === $viec ) {
				$bao[] = VHG_Thu::bo_huy( wp_unslash( $_POST['ref'] ) );
			} elseif ( 'gan_may_gd' === $viec ) {
				$bao[] = VHG_Thu::gan_may( wp_unslash( $_POST['ref'] ),
					wp_unslash( $_POST['ma_may'] ), $nguoi );
			}
		}

		echo '<div class="wrap"><h1>Đối soát doanh thu ghế massage</h1>';
		self::ve_bao( $bao );
		self::canh_bao_mui_gio();
		$ky = self::chon_ky( 'vhg' );
		$t = VHG_Thu::tong_hop( $ky );

		/* ---- 1. Máy mất nhịp: để TRÊN CÙNG. Máy đứt thì khách quét QR, tiền vào, mà ghế không
		     chạy — người ta đứng ở quầy cãi nhau ngay lúc đó, không phải cuối tháng mới biết. */
		$may = VHG_May::ds_may();
		$dut = array();
		foreach ( $may as $m ) { if ( empty( $m['con_song'] ) ) { $dut[] = $m; } }
		if ( $dut ) {
			echo '<div class="notice notice-error"><p><strong>' . count( $dut ) . ' ghế không gửi nhịp '
				. 'quá ' . (int) ( VHG_May::HET_SONG / 60 ) . ' phút.</strong> Ghế đứt mà khách vẫn quét được '
				. 'tem QR trên ghế: <strong>tiền vào nhưng ghế không chạy</strong>. Kiểm điện và mạng ngay.</p><ul>';
			foreach ( $dut as $m ) {
				echo '<li><strong>' . esc_html( $m['ma'] ) . '</strong> · '
					. esc_html( $m['coso_ten'] ? $m['coso_ten'] : '(chưa gán cơ sở)' ) . ' · '
					. ( trim( (string) $m['nhip_luc'] ) !== ''
						? 'nhịp cuối ' . esc_html( $m['nhip_luc'] ) : 'chưa gửi nhịp nào bao giờ' )
					. '</li>';
			}
			echo '</ul></div>';
		} elseif ( $may ) {
			echo '<p style="color:#046b2d">✔️ Cả ' . count( $may ) . ' ghế đều đang gửi nhịp.</p>';
		}

		/* ---- 2. Tiền đã vào mà ghế chưa nhận ---- */
		$cho = VHG_May::ds_cho( true, 200 );
		if ( $cho ) {
			echo '<div class="notice notice-warning"><p><strong>' . count( $cho ) . ' lượt đã trả tiền '
				. 'mà ghế chưa nhận.</strong> Bình thường thì ghế lấy trong ~10 giây. Còn đọng lâu nghĩa là '
				. 'ghế đó đang đứt mạng — tiền đã vào sổ, nhưng khách chưa được massage.</p><ul>';
			foreach ( array_slice( $cho, 0, 20 ) as $c ) {
				echo '<li>' . esc_html( $c['tao_luc'] ) . ' · ghế <strong>' . esc_html( $c['ma_may'] )
					. '</strong> · ' . esc_html( self::tien( $c['so_tien'] ) )
					. ' · mã <code>' . esc_html( $c['ma_lenh'] ) . '</code></li>';
			}
			echo '</ul></div>';
		}

		/* ---- KPI ---- */
		echo '<div style="display:flex;gap:16px;flex-wrap:wrap;margin:16px 0">';
		foreach ( array(
			array( 'Tổng doanh thu', self::tien( $t['tong'] ), $t['so_luot'] . ' lượt' ),
			array( 'Chuyển khoản (QR)', self::tien( $t['qr'] ), $t['qr_luot'] . ' lượt' ),
			array( 'Tiền mặt', self::tien( $t['tien_mat'] ), $t['tien_mat_luot'] . ' lượt' ),
			array( 'Đang chờ ghế nhận', count( $cho ), 'đã trả, chưa chạy' ),
		) as $k ) {
			echo '<div style="border:1px solid #c3c4c7;background:#fff;border-radius:8px;padding:12px 16px;min-width:170px">'
				. '<div style="font-size:11px;text-transform:uppercase;color:#646970">' . esc_html( $k[0] ) . '</div>'
				. '<div style="font-size:22px;font-weight:700;margin-top:4px">' . esc_html( $k[1] ) . '</div>'
				. '<div style="font-size:12px;color:#646970">' . esc_html( $k[2] ) . '</div></div>';
		}
		echo '</div>';

		/* ---- Theo cơ sở ---- */
		echo '<h2>Theo cơ sở</h2><table class="widefat striped"><thead><tr><th>Cơ sở</th>'
			. '<th>Số máy</th><th>Lượt</th><th>QR</th><th>Tiền mặt</th><th>Tổng</th></tr></thead><tbody>';
		if ( ! $t['theo_coso'] ) { echo '<tr><td colspan="6"><em>Chưa có doanh thu kỳ này.</em></td></tr>'; }
		foreach ( $t['theo_coso'] as $c ) {
			echo '<tr><td><strong>' . esc_html( $c['coso'] ) . '</strong></td><td>' . (int) $c['so_may']
				. '</td><td>' . (int) $c['so_luot'] . '</td><td>' . esc_html( self::tien( $c['qr'] ) )
				. '</td><td>' . esc_html( self::tien( $c['tien_mat'] ) ) . '</td><td><strong>'
				. esc_html( self::tien( $c['tong'] ) ) . '</strong></td></tr>';
		}
		echo '</tbody></table>';

		/* ---- Theo máy ---- */
		echo '<h2>Theo máy</h2><table class="widefat striped"><thead><tr><th>Máy</th><th>Cơ sở</th>'
			. '<th>Lượt</th><th>QR</th><th>Tiền mặt</th><th>Tổng</th></tr></thead><tbody>';
		if ( ! $t['theo_may'] ) { echo '<tr><td colspan="6"><em>Chưa có doanh thu kỳ này.</em></td></tr>'; }
		foreach ( $t['theo_may'] as $m ) {
			echo '<tr><td><strong>' . esc_html( $m['may'] ) . '</strong></td><td>' . esc_html( $m['coso'] )
				. '</td><td>' . (int) $m['so_luot'] . '</td><td>' . esc_html( self::tien( $m['qr'] ) )
				. '</td><td>' . esc_html( self::tien( $m['tien_mat'] ) ) . '</td><td><strong>'
				. esc_html( self::tien( $m['tong'] ) ) . '</strong></td></tr>';
		}
		echo '</tbody></table>';

		/* ---- Tình trạng ghế + điều khiển ---- */
		echo '<h2>Tình trạng ghế</h2>';
		echo '<p><em>Bật tay là <strong>cho không một lượt massage</strong> — hệ thống ghi lại ai bấm và '
			. 'lúc nào, để cuối tháng còn giải thích được vì sao một ghế chạy nhiều hơn số tiền thu.</em></p>';
		echo '<table class="widefat striped"><thead><tr><th>Máy</th><th>Cơ sở</th><th>Trạng thái</th>'
			. '<th>Còn lại</th><th>Chờ</th><th>Việc</th></tr></thead><tbody>';
		if ( ! $may ) { echo '<tr><td colspan="6"><em>Chưa khai máy nào. Sang màn "Máy &amp; cơ sở".</em></td></tr>'; }
		foreach ( $may as $m ) {
			$tt = empty( $m['con_song'] ) ? '🔴 Mất kết nối'
				: ( 'running' === $m['trang_thai'] ? '▶️ Đang chạy'
					: ( 'wait_pay' === $m['trang_thai'] ? '⏳ Chờ thanh toán' : '🟢 Rảnh' ) );
			echo '<tr><td><strong>' . esc_html( $m['ma'] ) . '</strong></td>'
				. '<td>' . esc_html( $m['coso_ten'] ? $m['coso_ten'] : '(chưa gán)' ) . '</td>'
				. '<td>' . esc_html( $tt ) . '</td>'
				. '<td>' . ( 'running' === $m['trang_thai'] && ! empty( $m['con_song'] )
					? esc_html( gmdate( 'i:s', max( 0, (int) $m['con_lai'] ) ) ) : '' ) . '</td>'
				. '<td>' . (int) $m['cho'] . '</td><td>';
			echo '<form method="post" style="display:flex;gap:4px;flex-wrap:wrap;align-items:center">';
			echo wp_nonce_field( 'vhg', '_wpnonce', true, false );
			echo '<input type="hidden" name="ma_may" value="' . esc_attr( $m['ma'] ) . '" />';
			echo '<input type="number" name="phut" min="1" max="60" value="' . (int) $m['phut']
				. '" style="width:64px" title="Số phút" />';
			echo '<input type="text" name="ly_do" placeholder="lý do" style="width:130px" />';
			echo '<button class="button" name="vhg" value="bat">Bật</button>';
			echo '<button class="button" name="vhg" value="tat">Tắt</button>';
			echo '<input type="number" name="so_tien" min="1000" step="1000" value="' . (int) $m['gia']
				. '" style="width:90px" title="Số tiền thu tay" />';
			echo '<button class="button" name="vhg" value="tien_mat">Thu tiền mặt</button>';
			echo '</form></td></tr>';
		}
		echo '</tbody></table>';

		/* ---- Giao dịch gần đây ---- */
		echo '<h2>Giao dịch gần đây</h2><table class="widefat striped"><thead><tr><th>Thời gian</th>'
			. '<th>Máy</th><th>Nguồn</th><th>Số tiền</th><th>Nội dung</th><th>Mã tham chiếu</th>'
			. '<th></th></tr></thead><tbody>';
		$ds = VHG_Thu::ds( $ky, 100 );
		if ( ! $ds ) { echo '<tr><td colspan="7"><em>Chưa có giao dịch kỳ này.</em></td></tr>'; }
		foreach ( $ds as $r ) {
			$ten = '' !== $r['ma_may'] ? $r['ma_may'] : ( '' !== $r['ten_khai'] ? $r['ten_khai'] : '—' );
			echo '<tr><td>' . esc_html( $r['luc'] ) . '</td><td>' . esc_html( $ten ) . '</td>'
				. '<td>' . esc_html( VHG_Thu::TIEN_MAT === $r['nguon'] ? 'Tiền mặt' : strtoupper( $r['nguon'] ) )
				. '</td><td>' . esc_html( self::tien( $r['so_tien'] ) ) . '</td>'
				. '<td>' . esc_html( $r['noi_dung'] ) . '</td>'
				. '<td><code>' . esc_html( $r['ref'] ) . '</code></td>'
				. '<td><form method="post" onsubmit="return confirm(\'Gỡ giao dịch này khỏi báo cáo? '
				. 'Dòng vẫn được giữ lại, bỏ huỷ được.\')">'
				. wp_nonce_field( 'vhg', '_wpnonce', true, false )
				. '<input type="hidden" name="ref" value="' . esc_attr( $r['ref'] ) . '" />'
				. '<button class="button button-small" name="vhg" value="huy_gd">Huỷ</button>'
				. '</form></td></tr>';
		}
		echo '</tbody></table>';

		/* ==================================================================================
		 * TIỀN ĐÃ VÀO MÀ CHƯA GẮN ĐƯỢC GHẾ NÀO.
		 *
		 * 🔴 Ca thật, 22/08/2026 22:25: tiền về tài khoản, SePay thấy, webhook bắn về đúng nơi —
		 *    mà ghế không chạy, vì nội dung chuyển khoản do ngân hàng tự sinh
		 *    (`CT DEN:145T26811LG6HQZL SEVQR …`) không mang `GHE<ghế> <mã lượt>`.
		 *
		 *    Ca này KHÔNG hiếm: khách gõ tay nội dung mà gõ sai, app ngân hàng cắt bớt nội dung,
		 *    hoặc khách quét nhầm tem của ghế bên cạnh. Lúc đó tiền đã vào sổ và khách đang đứng
		 *    đó. Trước đây cách duy nhất là bấm "Bật tay" — nhưng bấm thế thì sổ ghi CHO KHÔNG
		 *    một lượt, tức là nói mình tặng khách trong khi khách đã trả tiền. Sai cả hai đầu.
		 * ================================================================================== */
		$chua_ro = VHG_Thu::ds_chua_ro( 30 );
		if ( $chua_ro ) {
			echo '<h2>Tiền đã vào mà chưa rõ ghế (' . count( $chua_ro ) . ')</h2>';
			echo '<p><em>Nội dung chuyển khoản không mang <code>GHE&lt;mã ghế&gt; &lt;mã lượt&gt;</code> nên '
				. 'máy chủ không biết của ghế nào. <b>Tiền vẫn nằm nguyên trong sổ</b> — chọn ghế rồi bấm '
				. 'Gán là ghế chạy, và doanh thu ghi đúng ghế đó chứ không thành "cho không một lượt".</em></p>';
			echo '<table class="widefat striped"><thead><tr><th>Thời gian</th><th>Số tiền</th>'
				. '<th>Nội dung</th><th>Mã tham chiếu</th><th>Gán cho ghế</th></tr></thead><tbody>';
			foreach ( $chua_ro as $r ) {
				echo '<tr><td>' . esc_html( $r['luc'] ) . '</td>'
					. '<td><b>' . esc_html( self::tien( $r['so_tien'] ) ) . '</b></td>'
					. '<td><code style="font-size:11px">' . esc_html( $r['noi_dung'] ) . '</code></td>'
					. '<td><code style="font-size:11px">' . esc_html( $r['ref'] ) . '</code></td>'
					. '<td><form method="post" style="display:flex;gap:6px;align-items:center">'
					. wp_nonce_field( 'vhg', '_wpnonce', true, false )
					. '<input type="hidden" name="ref" value="' . esc_attr( $r['ref'] ) . '" />'
					. '<select name="ma_may" required><option value="">— chọn ghế —</option>';
				foreach ( VHG_May::ds_may() as $m_c ) {
					echo '<option value="' . esc_attr( $m_c['ma'] ) . '">' . esc_html( $m_c['ma'] )
						. ( '' !== $m_c['coso_ten'] ? ' · ' . esc_html( $m_c['coso_ten'] ) : '' ) . '</option>';
				}
				echo '</select><button class="button button-primary" name="vhg" value="gan_may_gd">'
					. 'Gán &amp; cho chạy</button></form></td></tr>';
			}
			echo '</tbody></table>';
		}

		/* ---- Đã huỷ: có huỷ thì phải xem lại được, không thì huỷ thành mất tăm ---- */
		$dh = VHG_Thu::ds_huy( 100 );
		if ( $dh ) {
			echo '<h2>Đã gỡ khỏi báo cáo (' . count( $dh ) . ')</h2>';
			echo '<p><em>Những dòng này KHÔNG cộng vào doanh thu, nhưng vẫn nằm trong cơ sở dữ liệu — '
				. 'vừa để trả lời câu "sao hôm đó lệch", vừa để cùng giao dịch ấy bắn lại lần nữa không '
				. 'vào sổ như một khoản mới.</em></p>';
			echo '<table class="widefat striped"><thead><tr><th>Thời gian</th><th>Số tiền</th>'
				. '<th>Nội dung</th><th>Lý do</th><th>Mã tham chiếu</th><th></th></tr></thead><tbody>';
			foreach ( $dh as $r ) {
				echo '<tr><td>' . esc_html( $r['luc'] ) . '</td>'
					. '<td>' . esc_html( self::tien( $r['so_tien'] ) ) . '</td>'
					. '<td>' . esc_html( $r['noi_dung'] ) . '</td>'
					. '<td>' . esc_html( $r['huy_ly_do'] ) . '</td>'
					. '<td><code>' . esc_html( $r['ref'] ) . '</code></td>'
					. '<td><form method="post">'
					. wp_nonce_field( 'vhg', '_wpnonce', true, false )
					. '<input type="hidden" name="ref" value="' . esc_attr( $r['ref'] ) . '" />'
					. '<button class="button button-small" name="vhg" value="bo_huy_gd">Đưa lại vào sổ</button>'
					. '</form></td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '</div>';
	}

	/**
	 * MÚI GIỜ CỦA WEBSITE PHẢI LÀ GIỜ VIỆT NAM.
	 *
	 * 🔴 Ngày 22/08/2026: SePay ghi giao dịch lúc 20:08:26, website ghi 13:08:29 — lệch đúng 7
	 *    tiếng, vì WordPress mới cài mặc định chạy giờ UTC. Hai hậu quả:
	 *      · Đối soát với sao kê ngân hàng thành mò kim: cùng một giao dịch, hai giờ khác nhau.
	 *      · Báo cáo "Hôm nay" cắt sai ngày. Lượt lúc 0h–7h sáng rơi về ngày HÔM TRƯỚC, nên chốt
	 *        ca đêm ra số khác chốt sổ ngân hàng, mà không có gì trên màn hình nói vì sao.
	 *
	 * Từ bản này giao dịch qua webhook ưu tiên lấy giờ của BÊN GỬI, nên phần lớn đã đúng dù máy
	 * chủ sai. Nhưng tiền mặt và giao dịch không kèm giờ vẫn lấy giờ máy chủ — nên vẫn phải sửa,
	 * và màn hình phải nói ra chứ không để người dùng tự phát hiện bằng cách so từng dòng.
	 */
	private static function canh_bao_mui_gio() {
		$lech = (int) ( wp_timezone()->getOffset( new DateTime( 'now', new DateTimeZone( 'UTC' ) ) ) );
		if ( 7 * 3600 === $lech ) { return; }
		$gio = round( $lech / 3600, 1 );
		echo '<div class="notice notice-warning"><p><b>Múi giờ của website đang là UTC'
			. esc_html( ( $gio >= 0 ? '+' : '' ) . $gio ) . ', không phải giờ Việt Nam (UTC+7).</b> '
			. 'Giao dịch tiền mặt và mọi mốc "Hôm nay / Tuần này" sẽ lệch '
			. esc_html( (string) abs( 7 - $gio ) ) . ' tiếng so với sao kê ngân hàng.</p>'
			. '<p>Sửa ở <a href="' . esc_url( admin_url( 'options-general.php' ) ) . '">Settings → General → '
			. 'Timezone</a>, chọn <code>Ho Chi Minh</code>. Sửa xong thì các dòng CŨ vẫn giữ giờ cũ — '
			. 'chỉ dòng mới mới đúng.</p></div>';
	}

	// ======================================================================= 2. MÁY & CƠ SỞ

	public static function trang_may() {
		self::gac();
		$bao = array();
		if ( isset( $_POST['vhg'] ) ) {
			check_admin_referer( 'vhg' );
			$viec = sanitize_text_field( wp_unslash( $_POST['vhg'] ) );
			if ( 'coso' === $viec ) {
				$bao[] = VHG_May::luu_coso( isset( $_POST['coso_id'] ) ? (int) $_POST['coso_id'] : 0,
					wp_unslash( $_POST['ten'] ) );
			} elseif ( 'xoa_coso' === $viec ) {
				$bao[] = VHG_May::xoa_coso( (int) $_POST['coso_id'] );
			} elseif ( 'may' === $viec ) {
				$bao[] = VHG_May::luu_may( array(
					'ma' => wp_unslash( $_POST['ma'] ), 'coso_id' => (int) $_POST['coso_id'],
					'mac' => isset( $_POST['mac'] ) ? wp_unslash( $_POST['mac'] ) : '',
					'gia' => wp_unslash( $_POST['gia'] ), 'phut' => wp_unslash( $_POST['phut'] ),
					'so_tk' => wp_unslash( $_POST['so_tk'] ), 'ten_tk' => wp_unslash( $_POST['ten_tk'] ),
					'bank_bin' => wp_unslash( $_POST['bank_bin'] ), 'ten_khai' => wp_unslash( $_POST['ten_khai'] ) ) );
			} elseif ( 'xoa_may' === $viec ) {
				$bao[] = VHG_May::xoa_may( wp_unslash( $_POST['ma'] ) );
			} elseif ( 'an_may' === $viec ) {
				$bao[] = VHG_May::dat_an( wp_unslash( $_POST['ma'] ), ! empty( $_POST['an'] ) );
			} elseif ( 'gan_ma' === $viec ) {
				$bao[] = VHG_May::gan_ma( wp_unslash( $_POST['ma_cu'] ), wp_unslash( $_POST['ma_moi'] ),
					isset( $_POST['coso_id'] ) ? (int) $_POST['coso_id'] : null );
			} elseif ( 'nhan_tien' === $viec ) {
				$bao[] = VHG_May::luu_nhan_tien( wp_unslash( $_POST['bin'] ),
					wp_unslash( $_POST['so_tk'] ), wp_unslash( $_POST['ten_tk'] ) );
			} elseif ( 'ty_le' === $viec ) {
				$bao[] = VHG_May::luu_ty_le( wp_unslash( $_POST['gia_c'] ), wp_unslash( $_POST['phut_c'] ) );
			} elseif ( 'bo_rieng' === $viec ) {
				$bao[] = VHG_May::bo_ty_le_rieng();
			} elseif ( 'tien_to' === $viec ) {
				$bao[] = VHG_May::luu_tien_to_nd( wp_unslash( $_POST['tien_to_nd'] ) );
			} elseif ( 'chot_don_vi' === $viec ) {
				$bao[] = VHG_Quy::luu_don_vi( wp_unslash( $_POST['chot_don_vi'] ) );
			} elseif ( 'menh_gia' === $viec ) {
				$ten  = isset( $_POST['mg_ten'] ) ? (array) wp_unslash( $_POST['mg_ten'] ) : array();
				$tien = isset( $_POST['mg_tien'] ) ? (array) wp_unslash( $_POST['mg_tien'] ) : array();
				$ph   = isset( $_POST['mg_phut'] ) ? (array) wp_unslash( $_POST['mg_phut'] ) : array();
				$mo  = isset( $_POST['mg_mota'] ) ? (array) wp_unslash( $_POST['mg_mota'] ) : array();
				/* Ô tích gửi về CHỈ SỐ của dòng, không gửi về theo thứ tự — ô không tích thì
				   trình duyệt bỏ hẳn khỏi gói POST, nên đếm theo thứ tự là lệch ngay từ dòng
				   đầu tiên không tích. */
				$vip = isset( $_POST['mg_vip'] ) ? array_map( 'intval', (array) $_POST['mg_vip'] ) : array();
				$goi  = array();
				foreach ( $tien as $i => $v ) {
					$goi[] = array( 'tien' => $v,
						'ten'   => isset( $ten[ $i ] ) ? sanitize_text_field( $ten[ $i ] ) : '',
						'mo_ta' => isset( $mo[ $i ] ) ? sanitize_text_field( $mo[ $i ] ) : '',
						'phut'  => isset( $ph[ $i ] ) ? (int) $ph[ $i ] : 0,
						'vip'   => in_array( (int) $i, $vip, true ) ? 1 : 0 );
				}
				$bao[] = VHG_May::luu_menh_gia( $goi );
			} elseif ( 'goi_nap' === $viec ) {
				$gn_n = isset( $_POST['gn_nap'] ) ? (array) wp_unslash( $_POST['gn_nap'] ) : array();
				$gn_h = isset( $_POST['gn_nhan'] ) ? (array) wp_unslash( $_POST['gn_nhan'] ) : array();
				$gn_d = array();
				foreach ( $gn_n as $i => $v ) {
					$gn_d[] = array( 'nap' => $v,
						'nhan' => isset( $gn_h[ $i ] ) ? $gn_h[ $i ] : 0 );
				}
				$bao[] = VHG_Vi::luu_goi_nap( $gn_d );
			} elseif ( 'tich' === $viec ) {
				$bao[] = VHG_Vi::luu_tich_cf( array(
					'bat'      => isset( $_POST['tich_bat'] ) ? 1 : 0,
					'moi_luot' => isset( $_POST['tich_moi_luot'] ) ? wp_unslash( $_POST['tich_moi_luot'] ) : '',
					'moc'      => isset( $_POST['tich_moc'] ) ? wp_unslash( $_POST['tich_moc'] ) : '',
					'kieu'     => isset( $_POST['tich_kieu'] ) ? sanitize_text_field( wp_unslash( $_POST['tich_kieu'] ) ) : '',
					'gia_tri'  => isset( $_POST['tich_gia_tri'] ) ? wp_unslash( $_POST['tich_gia_tri'] ) : '',
					'ten_qua'  => isset( $_POST['tich_ten_qua'] ) ? sanitize_text_field( wp_unslash( $_POST['tich_ten_qua'] ) ) : '',
				) );
			} elseif ( 'chan' === $viec ) {
				$ch = array( 'hien' => isset( $_POST['chan_hien'] ) ? 1 : 0 );
				foreach ( array_keys( VHG_Chan::mac_dinh() ) as $k_ch ) {
					if ( 'hien' === $k_ch ) { continue; }
					$ch[ $k_ch ] = isset( $_POST[ 'chan_' . $k_ch ] )
						? sanitize_textarea_field( wp_unslash( $_POST[ 'chan_' . $k_ch ] ) ) : '';
				}
				$bao[] = VHG_Chan::luu( $ch );
			} elseif ( 'ban_ma' === $viec ) {
				update_option( 'vhg_ban_ma', empty( $_POST['ban_ma_bat'] ) ? 0 : 1 );
				$bao[] = array( 'ok' => true, 'thong_bao' => empty( $_POST['ban_ma_bat'] )
					? 'Đã NGỪNG bán mã lẻ. Mã đã bán vẫn dùng được bình thường.'
					: 'Đã bật lại bán mã lẻ.' );
			} elseif ( 'vi_chinh' === $viec ) {
				$bao[] = VHG_Vi::chinh_tay(
					isset( $_POST['vi_sdt'] ) ? wp_unslash( $_POST['vi_sdt'] ) : '',
					isset( $_POST['vi_tien'] ) ? (int) $_POST['vi_tien'] : 0,
					isset( $_POST['vi_ly_do'] ) ? sanitize_text_field( wp_unslash( $_POST['vi_ly_do'] ) ) : '',
					self::ai() );
			} elseif ( 'vi_khoa' === $viec || 'vi_mo' === $viec ) {
				$bao[] = VHG_Vi::khoa(
					isset( $_POST['vi_sdt'] ) ? wp_unslash( $_POST['vi_sdt'] ) : '',
					'vi_khoa' === $viec,
					isset( $_POST['vi_ly_do'] ) ? sanitize_text_field( wp_unslash( $_POST['vi_ly_do'] ) ) : '',
					self::ai() );
			}
		}

		echo '<div class="wrap"><h1>Máy &amp; cơ sở</h1>';
		self::ve_bao( $bao );

		$coso = VHG_May::ds_coso();
		echo '<h2>Cơ sở</h2><table class="widefat striped"><thead><tr><th>Tên</th><th>Số máy</th>'
			. '<th></th></tr></thead><tbody>';
		$may = VHG_May::ds_may();
		$dem = array();
		foreach ( $may as $m ) { $k = (int) $m['coso_id']; $dem[ $k ] = ( isset( $dem[ $k ] ) ? $dem[ $k ] : 0 ) + 1; }
		if ( ! $coso ) { echo '<tr><td colspan="3"><em>Chưa có cơ sở nào.</em></td></tr>'; }
		foreach ( $coso as $c ) {
			echo '<tr><td><strong>' . esc_html( $c['ten'] ) . '</strong></td><td>'
				. (int) ( isset( $dem[ (int) $c['id'] ] ) ? $dem[ (int) $c['id'] ] : 0 ) . '</td><td><form method="post">';
			echo wp_nonce_field( 'vhg', '_wpnonce', true, false );
			echo '<input type="hidden" name="coso_id" value="' . (int) $c['id'] . '" />'
				. '<button class="button button-small" name="vhg" value="xoa_coso">Xoá</button>'
				. '</form></td></tr>';
		}
		echo '</tbody></table>';
		echo '<form method="post" style="margin-top:8px;display:flex;gap:8px;align-items:flex-end">';
		wp_nonce_field( 'vhg' );
		echo '<label>Thêm cơ sở <input type="text" name="ten" placeholder="VD: Tutu Tân Phú" required /></label>';
		echo '<input type="hidden" name="coso_id" value="0" />';
		echo '<button class="button button-primary" name="vhg" value="coso">Thêm</button></form>';
		echo '<p><em>Xoá cơ sở KHÔNG xoá máy trong đó — máy chỉ thành "chưa gán". Xoá theo là mất '
			. 'cấu hình giá/số tài khoản của những máy đang chạy thật.</em></p>';

		/* =====================================================================================
		 * GHẾ CHỜ GÁN — ĐẶT NGAY DƯỚI CƠ SỞ, TRÊN BẢNG MÁY.
		 *
		 * 🔴 Ghế nhận nhau với máy chủ bằng ĐỊA CHỈ MAC, không bằng mã. Cắm điện là nó tự hiện ra
		 *    đây với mã tạm `?xxxxxx`. Người đi lắp chỉ cần gán mã thật + cơ sở, KHÔNG phải gõ
		 *    MAC — gõ tay 12 ký tự hex là gõ sai.
		 *
		 * ⚠️ Đây là chỗ anh Thắng vướng ngày 22/08/2026: khai tay một dòng mang chính MAC làm mã.
		 *    Dòng đó KHÔNG gắn với con ghế nào (cột `mac` rỗng), nên khi ghế cắm điện nó vẫn đẻ ra
		 *    một dòng thứ hai — hai dòng cho một cái ghế, và cái đang chạy thật là dòng kia.
		 * ===================================================================================== */
		$cho_gan = VHG_May::chua_gan();
		echo '<h2>Ghế chờ gán mã (' . count( $cho_gan ) . ')</h2>';
		/* Câu này ở CẢ HAI trạng thái, không riêng lúc danh sách rỗng: nó trả lời câu hỏi
		   "sao tôi không khai được máy" — mà người ta hỏi câu đó ngay khi ĐANG nhìn thấy một
		   dòng chờ và không biết nó ở đâu ra. */
		echo '<p><em>Ghế <b>tự hiện ra đây khi cắm điện và nối được mạng</b> — không phải khai tay. '
			. 'Máy chủ nhận ra ghế bằng địa chỉ MAC, nên không ai phải gõ 12 ký tự hex.</em></p>';
		if ( ! $cho_gan ) {
			echo '<p><em>Không có ghế nào đang chờ. Chưa thấy ghế nào thì kiểm: ghế đã cắm chưa, có '
				. 'wifi/4G chưa, và đã nạp firmware trỏ về <code>' . esc_html( home_url( '/' ) )
				. '</code> chưa.</em></p>';
		} else {
			echo '<table class="widefat striped"><thead><tr><th>MAC (ghế tự khai)</th><th>Mã tạm</th>'
				. '<th>Nhịp cuối</th><th>Gán mã thật + cơ sở</th></tr></thead><tbody>';
			foreach ( $cho_gan as $g ) {
				echo '<tr><td><code>' . esc_html( $g['mac'] ) . '</code></td>'
					. '<td><code>' . esc_html( $g['ma'] ) . '</code></td>'
					. '<td>' . esc_html( $g['nhip_luc'] )
					. ( ! empty( $g['con_song'] ) ? ' <span style="color:#046b2d">● đang sống</span>'
						: ' <span style="color:#b32d2e">● mất kết nối</span>' )
					. '<br><span class="description">' . esc_html( $g['ip'] ) . ' · ' . esc_html( $g['fw'] )
					. '</span></td><td>';
				echo '<form method="post" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">';
				echo wp_nonce_field( 'vhg', '_wpnonce', true, false );
				echo '<input type="hidden" name="ma_cu" value="' . esc_attr( $g['ma'] ) . '" />';
				echo '<input type="text" name="ma_moi" required pattern="[A-Za-z0-9]{1,20}" '
					. 'placeholder="VD: AMTP01" style="width:130px" />';
				echo '<select name="coso_id"><option value="0">— chưa gán cơ sở —</option>';
				foreach ( $coso as $c ) {
					echo '<option value="' . (int) $c['id'] . '">' . esc_html( $c['ten'] ) . '</option>';
				}
				echo '</select>';
				echo '<button class="button button-primary" name="vhg" value="gan_ma">Gán</button>';
				echo '</form></td></tr>';
			}
			echo '</tbody></table>';
			echo '<p><em>Mã đi vào nội dung chuyển khoản khách gõ tay (<code>GHE&lt;mã&gt; &lt;mã lượt&gt;</code>) '
				. '— <b>đặt ngắn</b>. <code>AMTP01</code> cho ra <code>GHEAMTP01 K7M2P</code> (15 ký tự); '
				. 'lấy MAC làm mã cho ra 21 ký tự, gõ sai một ký tự là tiền vào mà ghế không chạy.<br>'
				. 'Gán mã sẽ dời luôn doanh thu và lượt đang chờ của ghế đó sang mã mới — không mất gì.</em></p>';
		}

		/* ---- Tài khoản nhận tiền: KHAI MỘT LẦN ---- */
		$tk_chung = VHG_May::nhan_tien_chung();
		/* 🔴 KHAI Ở ĐÂY, KHÔNG PHẢI Ở DƯỚI. Bản trước khai `$tl_c` tận khối "Tỉ lệ quy đổi" nằm
		   BÊN DƯỚI, nên chỗ dựng mã QR mẫu ở trên nhận `null` -> số tiền 0đ. Và QR 0 đồng là
		   LOẠI QR KHÁC HẲN (tĩnh, ô `01` = "11" thay vì "12") — nghĩa là bảng "xem trước" đang
		   xem trước một thứ không phải cái ghế dựng ra. Đúng cái lỗi mà bảng này sinh ra để
		   tránh, và nó lọt vào chính bảng đó. */
		$tl_c = VHG_May::ty_le_chung();
		echo '<h2>Tài khoản nhận tiền (dùng chung cả hệ thống)</h2>';
		echo '<p><em>Ghế <b>tự vẽ mã QR</b> trên màn của nó, nên nó phải biết tiền đi về đâu. SePay chỉ '
			. 'BÁO TIN tiền đã về, không quyết định tiền đi đâu — nên vẫn cần ba ô này.<br>'
			. 'Khai <b>một lần</b>, mọi ghế lấy về trong ~30 giây. Không phải nạp lại firmware.</em></p>';
		if ( '' === $tk_chung['so_tk'] || '' === $tk_chung['bin'] ) {
			echo '<div class="notice notice-error inline"><p><b>Chưa khai tài khoản nhận tiền.</b> '
				. 'Ghế sẽ không vẽ được mã QR — khách không quét được, không thu được đồng nào qua QR. '
				. '(Tiền mặt vẫn chạy.)</p></div>';
		}
		/* 🔴 ĐỐI CHIẾU VỚI SỰ THẬT. Xem khối giải thích ở VHG_May::nho_tk_ben_gui(). */
		$dc = VHG_May::doi_chieu_tk();
		if ( $dc['co'] && ! $dc['khop'] ) {
			echo '<div class="notice notice-error inline"><p><b>Số tài khoản đang khai KHÁC số mà '
				. 'bên gửi báo đã nhận tiền.</b></p>'
				. '<table class="widefat striped" style="max-width:420px;margin:6px 0"><tbody>'
				. '<tr><td>Đang khai ở đây</td><td><code>' . esc_html( $tk_chung['so_tk'] ) . '</code></td></tr>'
				. '<tr><td>Bên gửi (SePay) báo</td><td><code>' . esc_html( $dc['ben_gui'] ) . '</code>'
				. ' <span class="description">' . esc_html( $dc['luc'] ) . '</span></td></tr>'
				. ( '' !== (string) $dc['va']
					? '<tr><td>Tài khoản ảo (VA) bên gửi báo</td><td><code>' . esc_html( $dc['va'] )
						. '</code> <span class="description">thường ĐÂY mới là số cần điền ở trên</span></td></tr>'
					: '' )
				. '</tbody></table>'
				. '<p>Một trong hai sai. Sai ở ô này thì <b>mã QR trên 26 cái ghế đều hỏng</b> — mà nó '
				. 'vẫn dựng ra được, vẫn trông như thật, chỉ tới lúc có khách đứng quét mới lộ. '
				. 'Thiếu hoặc thừa <b>một chữ số</b> là đủ để app ngân hàng báo '
				. '“định dạng tài khoản không hợp lệ”.</p></div>';
		} elseif ( $dc['co'] ) {
			echo '<p><span style="color:#046b2d">✔️ Số tài khoản khớp với số bên gửi báo</span> '
				. '<span class="description">(lượt gần nhất ' . esc_html( $dc['luc'] ) . ')</span></p>';
		}
		echo '<form method="post"><table class="form-table">';
		wp_nonce_field( 'vhg' );
		echo '<tr><th>Số TK / VA nhận tiền</th><td><input name="so_tk" value="'
			. esc_attr( $tk_chung['so_tk'] ) . '" class="regular-text code" />'
			/* 🔴 BÀI HỌC NGÀY 22/08/2026. Anh Thắng quét thử: ngân hàng trừ tiền bình thường,
			   app hiện đúng tên chủ tài khoản, mà SePay KHÔNG thấy giao dịch nào và ghế không
			   chạy. Vì tiền vào TÀI KHOẢN GỐC, còn SePay theo dõi TÀI KHOẢN ẢO (VA).
			   Tiền không mất — nó nằm đúng trong tài khoản của mình — nhưng hệ thống mù với nó,
			   nên khách trả tiền xong đứng đó mà ghế không chạy. Ghi ra đây vì nhìn hai chuỗi số
			   thì không có gì gợi ý cái nào đúng. */
			/* ⚠️ ĐỪNG KHẲNG ĐỊNH PHẢI DÙNG VA. Bản trước ghi cứng "điền SỐ VA, không phải số tài
			   khoản" — suy đoán, và suy đoán sai. Bằng chứng ngược lại có sẵn ngay trong nhật ký:
			   lượt 20.000đ ngày 22/08/2026 chuyển vào SỐ TÀI KHOẢN THƯỜNG đã hiện trong SePay và
			   đã bắn webhook về đây. Nghĩa là SePay theo dõi cả tài khoản gốc.

			   Một câu hướng dẫn ghi cứng bên trong màn hình còn nguy hơn một câu nói miệng: nó
			   ở lại mãi và người sau đọc sẽ tin. Nên nói theo BẰNG CHỨNG: dùng số nào đã từng có
			   giao dịch chạy được. */
			. '<p class="description">Điền số mà <b>SePay thật sự nhìn thấy</b> — có thể là số tài '
			. 'khoản ngân hàng, cũng có thể là số VA. Cách chắc nhất: mở trang SePay → '
			. '<b>Giao dịch</b>, tìm một lượt đã về thành công, xem lượt đó ghi số nào thì điền số đó.<br>'
			. 'Nếu chưa có lượt nào: thử số tài khoản ngân hàng trước (ngân hàng nào cũng tra được), '
			. 'rồi mới thử VA. VA có thể có chữ (VD <code>96247POSH</code>) — điền nguyên văn, và BIN '
			. 'phải là ngân hàng <b>phát hành VA</b> chứ không phải ngân hàng mình quen dùng.</p></td></tr>';
		echo '<tr><th>Mã ngân hàng (BIN)</th><td><input name="bin" value="'
			. esc_attr( $tk_chung['bin'] ) . '" style="width:120px" placeholder="970418" />'
			. '<p class="description">Napas BIN, 6 chữ số. 970418 = BIDV · 970436 = Vietcombank · '
			. '970415 = VietinBank · 970422 = MB · 970407 = Techcombank · 970416 = ACB · '
			. '970448 = OCB.<br>'
			/* BIN phải là ngân hàng PHÁT HÀNH VA, không phải ngân hàng mình quen dùng. VA do
			   SePay mở qua một ngân hàng đối tác, và ngân hàng đó có khi khác hẳn. */
			. '⚠️ Nếu ô trên điền VA thì BIN phải là <b>ngân hàng phát hành VA đó</b> — xem ở '
			. 'trang SePay, bấm vào số VA. Ngân hàng phát hành VA có thể khác ngân hàng mình quen dùng.<br>'
			. '<b>Sai BIN là QR quét ra ngân hàng khác và tiền không về tài khoản của mình.</b></p></td></tr>';
		echo '<tr><th>Tên tài khoản</th><td><input name="ten_tk" value="'
			. esc_attr( $tk_chung['ten_tk'] ) . '" class="regular-text" /></td></tr>';
		echo '</table><p><button class="button button-primary" name="vhg" value="nhan_tien">Lưu tài khoản</button></p></form>';

		/* ==================================================================================
		 * 🔴 TIỀN TỐ BẮT BUỘC TRONG NỘI DUNG — MẮT XÍCH IM LẶNG NHẤT CỦA CẢ HỆ THỐNG.
		 *
		 * Tìm ra 22/08/2026 trên chính trang Tạo QR của SePay:
		 *   "SEVQR — VietinBank cá nhân/hộ kinh doanh BẮT BUỘC nội dung CK phải chứa `sevqr`
		 *    để định tuyến giao dịch qua SePay."
		 *
		 * Không có chuỗi đó thì tiền vẫn vào tài khoản, ngân hàng vẫn báo thành công, nhưng
		 * SePay KHÔNG BAO GIỜ THẤY — không webhook, ghế không chạy, và trong sổ của mình không
		 * có MỘT DÒNG NÀO, kể cả dòng "có gói lạ bắn tới". Không có gì để đi tìm.
		 * ================================================================================== */
		$tien_to = VHG_May::tien_to_nd();
		echo '<h3>Tiền tố bắt buộc trong nội dung chuyển khoản</h3>';
		echo '<form method="post" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">';
		wp_nonce_field( 'vhg' );
		echo '<input type="text" name="tien_to_nd" value="' . esc_attr( $tien_to ) . '" '
			. 'style="width:130px" placeholder="VD: SEVQR" /> '
			. '<button class="button button-primary" name="vhg" value="tien_to">Lưu tiền tố</button></form>';
		if ( '' === $tien_to ) {
			echo '<div class="notice notice-warning inline"><p><b>Chưa khai tiền tố.</b> '
				. 'Với <b>VietinBank tài khoản cá nhân / hộ kinh doanh</b>, SePay bắt buộc nội dung '
				. 'chuyển khoản phải chứa <code>SEVQR</code> mới định tuyến được giao dịch.<br>'
				. 'Thiếu nó thì <b>tiền vẫn vào tài khoản và ngân hàng vẫn báo thành công, nhưng SePay '
				. 'không bao giờ thấy</b> — không webhook, ghế không chạy, và trong sổ này không có một '
				. 'dòng nào để đi tìm. Đây là mắt xích im lặng nhất của cả hệ thống.<br>'
				. 'Xem ở trang SePay → <b>Tạo QR</b>, dòng chữ đỏ cạnh ô "Nội dung chuyển khoản".</p></div>';
		}
		/* ==================================================================================
		 * CHỈ SỐ MÀN ĐẾM CỦA MÁY TIỀN MẶT.
		 *
		 * Anh Thắng 23/08/2026: *"trên máy có 1 màn hình đếm tiền mặt nữa, nên nhập vào để trừ
		 * chỉ số cho ngày hôm sau"*.
		 *
		 * 🔴 KHAI SAI CON SỐ NÀY LÀ MỌI LƯỢT CHỐT CA SAI THEO CÙNG MỘT HỆ SỐ — và nó sai một
		 *    cách rất giống thật: bảng vẫn đầy số, vẫn cộng ra tổng, chỉ là lệch gấp mấy lần.
		 *    Màn đếm mỗi hãng hiển thị một kiểu (số tờ / số xung / thẳng số tiền), nên phải đi
		 *    ra tận nơi nhét thử một tờ rồi xem màn nhảy bao nhiêu, đừng đoán.
		 * ================================================================================== */
		$dv = VHG_Quy::don_vi();
		echo '<h3>Chỉ số màn đếm tiền mặt (dùng cho chốt ca)</h3>';
		echo '<form method="post" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">';
		wp_nonce_field( 'vhg' );
		echo 'Mỗi <b>1 đơn vị</b> trên màn đếm = <input type="number" name="chot_don_vi" min="1" '
			. 'step="1000" value="' . (int) $dv . '" style="width:120px" /> đ '
			. '<button class="button button-primary" name="vhg" value="chot_don_vi">Lưu</button></form>';
		echo '<p class="description">Nhân viên chốt ca nhập chỉ số đọc trên màn máy đếm; hệ thống lấy '
			. '<b>chỉ số lần này trừ chỉ số lần trước</b>, nhân với con số trên, ra số tiền máy đếm '
			. 'nói nó đã nuốt — rồi so với tiền đếm được trong ngăn và với sổ.<br>'
			. '<b>Cách kiểm:</b> nhét một tờ ' . esc_html( self::tien( $dv ) ) . ' vào máy và xem màn '
			. 'đếm nhảy đúng <b>1</b> đơn vị hay không. Nhảy 2 thì khai lại một nửa, nhảy '
			. '5.000 thì màn đó đang hiện thẳng số tiền — khai <b>1</b>.<br>'
			. 'Firmware đang để <code>CASH_VND_PER_PULSE 5000</code> (1 xung = 5.000đ, theo DIP của '
			. 'cục nhận tiền); mặc định ở đây lấy đúng con số đó.</p>';

		$nd_mau = VHG_QR::noi_dung( 'AMTP01', 'K7M2P' );
		echo '<p class="description">Nội dung một lượt sẽ là: <code>' . esc_html( $nd_mau ) . '</code> ('
			. strlen( $nd_mau ) . '/' . VHG_QR::ND_TOI_DA . ' ký tự).<br>'
			. 'Ngân hàng khác hoặc tài khoản doanh nghiệp thường KHÔNG cần — để trống. Thừa một chuỗi '
			. 'lạ là tốn chỗ của mã lượt.</p>';

		/* ==================================================================================
		 * ĐỌC NGƯỢC MÃ QR RA TỪNG TRƯỜNG.
		 *
		 * 🔴 Anh Thắng quét thử ba lần, ba lỗi khác nhau từ app ngân hàng: "sai định dạng tài
		 *    khoản (174)", rồi "vấn tin bị timeout (199)". Mỗi lần chỉ biết là HỎNG, không biết
		 *    trong mã có gì — mà chuỗi QR là 130 ký tự dính liền, nhìn bằng mắt không đọc ra nổi
		 *    số tài khoản nằm ở đâu.
		 *
		 *    Mỗi lượt thử như vậy là một lượt chuyển tiền thật và một chuyến ra chỗ để ghế. Đọc
		 *    ngược ngay ở đây thì kiểm được TRƯỚC khi đi.
		 * ================================================================================== */
		if ( '' !== $tk_chung['so_tk'] && '' !== $tk_chung['bin'] ) {
			/* 🔴 GỌI ĐÚNG HÀM DỰNG NỘI DUNG, ĐỪNG GÕ CỨNG CHUỖI MẪU.
			 *
			 * Anh Thắng 22/08/2026: *"bấm lưu mà sao nó không chèn vào mã qr dựng"*. Ô tiền tố
			 * đã lưu, dòng chú thích ngay trên đã hiện "SEVQR GHEAMTP01 K7M2P" — nhưng bảng đọc
			 * ngược vẫn ra "GHEMAU K7M2P", vì chỗ này gõ cứng chuỗi đó thay vì gọi `noi_dung()`.
			 *
			 * Đây là LẦN THỨ BA bảng xem trước nói dối theo cùng một kiểu (trước đó: tỉ lệ gõ
			 * cứng 10000/6, rồi số tiền 0đ do dùng biến chưa khai). Cùng một gốc: bảng xem
			 * trước tự dựng lấy dữ liệu thay vì đi qua đúng con đường mà bản thật đi.
			 *
			 * Luật từ đây: mọi thứ bảng này hiện phải LẤY TỪ CÙNG MỘT HÀM mà đường thật dùng.
			 * Có phép thử chốt điều đó, không chỉ chốt cái kết quả. */
			$mau = VHG_QR::dung( $tk_chung['bin'], $tk_chung['so_tk'], (int) $tl_c['gia'],
				VHG_QR::noi_dung( 'MAU', 'K7M2P' ) );
			$doc = VHG_QR::doc( $mau );
			$ten_nh = VHG_QR::ten_ngan_hang( $doc['bin'] );
			echo '<h3>Mã QR sẽ dựng ra — đọc ngược để kiểm</h3>';
			echo '<table class="widefat striped" style="max-width:560px"><tbody>'
				. '<tr><td>Ngân hàng (BIN)</td><td><code>' . esc_html( $doc['bin'] ) . '</code> '
				. ( '' !== $ten_nh ? '<b>' . esc_html( $ten_nh ) . '</b>'
					: '<span style="color:#b32d2e">không có trong bảng mã Napas — kiểm lại</span>' ) . '</td></tr>'
				. '<tr><td>Số tài khoản / VA</td><td><code>' . esc_html( $doc['so_tk'] ) . '</code></td></tr>'
				. '<tr><td>Số tiền</td><td>' . esc_html( self::tien( $doc['so_tien'] ) ) . '</td></tr>'
				. '<tr><td>Nội dung</td><td><code>' . esc_html( $doc['noi_dung'] ) . '</code></td></tr>'
				. '<tr><td>Mã kiểm (CRC)</td><td>' . ( $doc['crc_dung']
					? '<span style="color:#046b2d">✔️ đúng</span>'
					: '<span style="color:#b32d2e">✘ SAI — mọi app ngân hàng sẽ từ chối</span>' ) . '</td></tr>'
				. '</tbody></table>';
			/* ==============================================================================
			 * SO VỚI MÃ DO CHÍNH SEPAY SINH RA.
			 *
			 * 🔴 Ngày 22/08/2026 anh Thắng quét thử bốn lần, bốn lỗi khác nhau từ app ngân hàng
			 *    (174 sai khuôn, 199 không tra được, 096 lỗi hệ thống nhà cung cấp). Tới đây thì
			 *    đoán tiếp là vô ích: không có cách nào biết lỗi ở CHUỖI MÌNH DỰNG hay ở CHÍNH
			 *    CÁI VA/ngân hàng đó.
			 *
			 *    SePay có bộ sinh mã QR của riêng họ, ăn cùng bốn tham số. Quét mã của họ:
			 *      · Họ chạy, mình không  -> lỗi ở phép dựng của mình. Sửa ở đây.
			 *      · Cả hai cùng hỏng     -> VA hoặc ngân hàng phát hành chưa nhận chuyển khoản
			 *                                từ ngoài. Việc phải hỏi SePay, không sửa được bằng mã.
			 *    Một phép thử tách được hai ca đó đáng giá hơn mười lần đoán.
			 *
			 * ⚠️ Chỉ là ĐƯỜNG DẪN để anh Thắng tự mở, KHÔNG nhúng ảnh vào trang quản trị: nhúng
			 *    là mỗi lần mở màn này lại gửi số tài khoản của mình sang một máy chủ khác.
			 * ============================================================================== */
			$u_sepay = 'https://qr.sepay.vn/img?' . http_build_query( array(
				'acc'    => $tk_chung['so_tk'],
				'bank'   => '' !== $ten_nh ? $ten_nh : $tk_chung['bin'],
				'amount' => (int) $tl_c['gia'],
				'des'    => VHG_QR::noi_dung( 'MAU', 'K7M2P' ),
			) );
			echo '<p><a class="button" href="' . esc_url( $u_sepay ) . '" target="_blank" rel="noopener">'
				. 'Mở mã QR do SePay sinh (cùng tham số)</a> '
				. '<span class="description">— quét mã đó bằng app ngân hàng để tách hai ca.</span></p>';
			echo '<p class="description"><b>Quét mã của SePay rồi so:</b><br>'
				. '· <b>Mã SePay CHẠY, mã của mình hỏng</b> — lỗi ở phép dựng chuỗi bên này, báo em.<br>'
				. '· <b>Cả hai cùng hỏng</b> — VA hoặc ngân hàng phát hành chưa nhận chuyển khoản từ '
				. 'ngân hàng khác. Đây là việc phải hỏi SePay, <b>không sửa được bằng mã</b>.</p>';

			echo '<p class="description"><b>App ngân hàng báo gì thì đọc thế nấy:</b><br>'
				. '· <b>“Định dạng tài khoản không hợp lệ”</b> — số tài khoản/VA sai khuôn của ngân '
				. 'hàng đó. Đếm lại từng chữ số, và xem có phải đang điền tài khoản gốc thay vì VA không.<br>'
				. '· <b>“Vấn tin bị timeout”</b> — khuôn đã đúng nhưng ngân hàng KHÔNG TRA ĐƯỢC tài '
				. 'khoản. Gần như luôn là <b>BIN không khớp ngân hàng phát hành</b>: mã đang trỏ vào '
				. ( '' !== $ten_nh ? esc_html( $ten_nh ) : 'một ngân hàng không rõ' )
				. ', tài khoản/VA phải do ĐÚNG ngân hàng đó phát hành.<br>'
				. '· <b>Hiện tên chủ tài khoản của mình</b> — mã ĐÚNG, chuyển được. Sau khi chuyển, '
				. 'xem trang SePay có hiện lượt đó không: có thì xong; không thì SePay chưa theo dõi '
				. 'số này, đổi sang số khác (VA hoặc tài khoản gốc, tuỳ cái nào SePay thấy).<br>'
				. '· <b>Hiện tên lạ</b> — <b>DỪNG NGAY</b>, đừng chuyển. Sai số tài khoản.<br>'
				. '· <b>“Lỗi hệ thống nhà cung cấp dịch vụ”</b> — ngân hàng nhận trả lời nhưng từ '
				. 'chối. Mã của mình đã tới được đúng nơi; vướng nằm ở phía VA. Quét thử mã SePay ở '
				. 'trên để chắc.</p>';
		}

		/* ---- Tỉ lệ quy đổi: khai chung, ĐẶT NGAY TRÊN bảng gói ---- ($tl_c khai ở đầu hàm)
		 * 🔴 Trước đây tỉ lệ nằm tận ô "Thêm / sửa máy", tách khỏi chỗ khai gói và phải lưu lại
		 *    từng máy một. Nên nhìn bảng gói thì tưởng đã khai xong, mà số phút vẫn là số cũ —
		 *    đúng chỗ anh Thắng vướng: *"không điều chỉnh được loại mệnh giá à"*. Số phút của
		 *    bốn gói do CẶP SỐ NÀY quyết định, nên nó phải nằm ngay đây. */
		echo '<h2>Tỉ lệ quy đổi (dùng chung cả hệ thống)</h2>';
		echo '<p><em>Bao nhiêu tiền ra bao nhiêu phút. <b>Số phút của bốn gói dưới đây tính theo cặp '
			. 'số này</b> — đổi một lần là cả bốn gói theo.</em></p>';
		echo '<form method="post" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">';
		wp_nonce_field( 'vhg' );
		echo '<input type="number" name="gia_c" min="1000" step="1000" value="' . (int) $tl_c['gia']
			. '" style="width:120px" /> đ = '
			. '<input type="number" name="phut_c" min="1" max="240" value="' . (int) $tl_c['phut']
			. '" style="width:80px" /> phút '
			. '<button class="button button-primary" name="vhg" value="ty_le">Lưu tỉ lệ</button></form>';

		/* Ghế khai từ bản cũ đều mang tỉ lệ riêng (bản cũ không có ô chung), nên đổi ô chung mà
		   chúng không theo — và không có gì trên màn nói vì sao. Nói ra, kèm nút gỡ. */
		$so_rieng = 0;
		foreach ( $may as $m_r ) { if ( (int) $m_r['gia'] > 0 || (int) $m_r['phut'] > 0 ) { $so_rieng++; } }
		if ( $so_rieng ) {
			echo '<div class="notice notice-warning inline"><p><b>' . (int) $so_rieng . ' ghế đang khai '
				. 'tỉ lệ RIÊNG</b> nên KHÔNG theo ô chung ở trên. Ghế khai từ bản cũ đều thế, vì bản cũ '
				. 'không có ô chung.</p>'
				. '<form method="post">' . wp_nonce_field( 'vhg', '_wpnonce', true, false )
				. '<button class="button" name="vhg" value="bo_rieng">Cho tất cả dùng tỉ lệ chung</button>'
				. '</form></div>';
		}

		/* ---- Gói trên màn ghế ---- */
		$mg = VHG_May::menh_gia();
		echo '<h2>Gói trên màn ghế</h2>';
		echo '<p><em>Bốn nút khách bấm để chọn. Khai ở đây thì ghế lấy về trong ~30 giây — '
			. '<b>ghế không có OTA</b>, nên nếu khai cứng trong firmware thì đổi giá là phải mang USB '
			. 'đi từng cửa hàng.</em></p>';
		echo '<form method="post"><table class="widefat striped" style="max-width:900px"><thead><tr>'
			. '<th>Nút</th><th>Tên gói</th><th>Mô tả một dòng</th><th>Số tiền</th><th>Số phút</th>'
			. '<th>VVIP</th></tr></thead><tbody>';
		wp_nonce_field( 'vhg' );
		for ( $i = 0; $i < VHG_May::SO_O_MAN_GHE; $i++ ) {
			$g = isset( $mg[ $i ] ) ? $mg[ $i ]
				: array( 'tien' => '', 'ten' => '', 'phut' => 0, 'mo_ta' => '', 'vip' => 0 );
			echo '<tr><td>' . ( $i + 1 ) . '</td>'
				/* maxlength khớp đúng VHG_May::CHU_VUA_O — nói giới hạn NGAY LÚC GÕ, chứ đừng để
				   anh Thắng gõ xong, bấm lưu, rồi mới thấy chữ bị cắt cụt mà không biết vì sao. */
				. '<td><input name="mg_ten[]" value="' . esc_attr( $g['ten'] ) . '" '
				. 'maxlength="' . (int) VHG_May::CHU_VUA_O . '" '
				. 'placeholder="VD: Gói phổ biến" style="width:100%" /></td>'
				. '<td><input name="mg_mota[]" value="' . esc_attr( $g['mo_ta'] ) . '" '
				. 'maxlength="' . (int) VHG_May::CHU_VUA_O . '" '
				. 'placeholder="VD: Sâu &amp; phục hồi" style="width:100%" /></td>'
				. '<td><input type="number" name="mg_tien[]" min="1000" step="1000" value="'
				. ( '' === $g['tien'] ? '' : (int) $g['tien'] ) . '" style="width:110px" /></td>'
				. '<td><input type="number" name="mg_phut[]" min="0" max="240" value="'
				. ( empty( $g['phut'] ) ? '' : (int) $g['phut'] ) . '" style="width:80px" placeholder="tự tính" /></td>'
				. '<td style="text-align:center"><input type="checkbox" name="mg_vip[]" value="' . $i . '"'
				. checked( true, ! empty( $g['vip'] ), false ) . ' /></td>'
				. '</tr>';
		}
		echo '</tbody></table><p><button class="button button-primary" name="vhg" value="menh_gia">'
			. 'Lưu gói</button></p></form>';
		echo '<p class="description"><b>Số phút để trống là đúng trong hầu hết trường hợp</b> — ghế tự '
			. 'tính theo tỉ lệ quy đổi, nên đổi tỉ lệ một lần là cả bốn gói theo. Chỉ điền số phút khi '
			. 'gói đó <b>cố ý</b> không theo tỉ lệ (gói khuyến mãi, gói kèm quà).<br>'
			. 'Bỏ trống số tiền = bỏ nút đó. Tên gói có dấu vẫn khai bình thường — máy chủ tự bỏ dấu '
			. 'khi gửi xuống ghế, vì font màn ghế không vẽ được dấu tiếng Việt.</p>';

		self::khoi_vi();
		self::khoi_tich();
		self::khoi_chan();

		/* Xem trước ĐÚNG như ghế sẽ hiện: đã bỏ dấu, đã tính ra phút. Một bảng xem trước bằng
		   chính dữ liệu sắp gửi đi là cách duy nhất thấy trước "GOI PHO BIEN" trông thế nào,
		   thay vì đi tới tận cửa hàng mới biết tên bị cắt cụt. */
		/* 🔴 XEM TRƯỚC PHẢI DÙNG TỈ LỆ THẬT. Bản trước gọi cứng `(10000, 6)` nên in ra một bảng
		   số phút KHÔNG phải số ghế sẽ chạy — anh Thắng khai 50k/100k/150k/200k mà bảng nói
		   30/60/90/120 phút, trong khi bảng giá là 15/30/45/60. Một bảng "xem trước" nói sai còn
		   hại hơn không có bảng nào: người ta tin nó rồi thôi không đi kiểm. */
		$xem = VHG_May::menh_gia_cho_ghe( (int) $tl_c['gia'], (int) $tl_c['phut'] );
		echo '<h3>Ghế sẽ hiện (với tỉ lệ ' . esc_html( self::tien( $tl_c['gia'] ) ) . ' = '
			. (int) $tl_c['phut'] . ' phút)</h3>';
		echo '<table class="widefat striped" style="max-width:560px"><thead><tr><th>Tên trên màn ghế</th>'
			. '<th>Mô tả trên màn ghế</th><th>Số tiền</th><th>Phút</th></tr></thead><tbody>';
		foreach ( $xem as $x ) {
			echo '<tr><td><code>' . esc_html( '' !== $x['n'] ? $x['n'] : '(không tên)' ) . '</code>'
				. ( $x['v'] ? ' <b style="color:#996800">VVIP</b>' : '' ) . '</td>'
				. '<td><code>' . esc_html( $x['m'] ) . '</code></td>'
				. '<td>' . esc_html( self::tien( $x['t'] ) ) . '</td>'
				. '<td>' . (int) $x['p'] . ' phút</td></tr>';
		}
		echo '</tbody></table>';
		echo '<p class="description">Chữ trên màn ghế <b>không dấu và viết hoa</b> — font của màn '
			. 'không vẽ được dấu tiếng Việt. Tên cắt còn 16 ký tự, mô tả 24 ký tự cho vừa bề ngang '
			. 'một thẻ. Gói tích <b>VVIP</b> được vẽ nền vàng và có nhãn ở góc, như tấm bảng giá.</p>';

		/* ===== NHẬT KÝ BẬT TỪ XA =========================================================
		   Mỗi lần bấm Bật là CHO KHÔNG một lượt massage. Cuối tháng nhìn "ghế chạy 180 lượt,
		   thu 140" thì 40 lượt kia phải giải thích được bằng con số, không bằng trí nhớ.
		   Để ở đây, ngay trên bảng máy: người mở màn này là người đi tra, không phải khách. */
		$bat_th = VHG_May::tong_lenh( 'month' );
		echo '<h2>Bật ghế từ xa — tháng này</h2>';
		echo '<p><b>' . (int) $bat_th['so_lan'] . ' lần</b>, tổng <b>' . (int) $bat_th['tong_phut']
			. ' phút</b>, trên ' . (int) $bat_th['so_ghe'] . ' ghế. '
			. '<em>Đây là số lượt ghế chạy mà sổ doanh thu không có đồng nào — dùng để giải thích '
			. 'chênh lệch giữa số lượt chạy và số lượt thu tiền.</em></p>';
		$bat_ng = VHG_May::tong_lenh_ngay( 'month' );
		if ( ! $bat_ng ) {
			echo '<p><em>Tháng này chưa ai bật ghế từ xa.</em></p>';
		} else {
			echo '<table class="widefat striped" style="max-width:560px"><thead><tr><th>Ngày</th>'
				. '<th>Số lần</th><th>Tổng phút</th></tr></thead><tbody>';
			foreach ( $bat_ng as $b_ ) {
				echo '<tr><td>' . esc_html( $b_['ngay'] ) . '</td><td>' . (int) $b_['so_lan']
					. '</td><td><strong>' . (int) $b_['tong_phut'] . '</strong></td></tr>';
			}
			echo '</tbody></table>';
			echo '<table class="widefat striped" style="margin-top:10px"><thead><tr><th>Lúc</th>'
				. '<th>Ghế</th><th>Ai bấm</th><th>Lý do</th><th>Phút</th><th>Ghế đã lấy lệnh</th>'
				. '</tr></thead><tbody>';
			foreach ( VHG_May::ds_lenh_bat( 'month', 100 ) as $l_ ) {
				/* Cột cuối phân biệt "đã chạy" với "sẽ chạy khi ghế lên mạng" — hai thứ khác nhau
				   khi đang đứng đối chiếu với sổ. */
				$da_ = '' !== trim( (string) $l_['gui_luc'] );
				echo '<tr><td>' . esc_html( self::gio( $l_['tao_luc'] ) ) . '</td>'
					. '<td><strong>' . esc_html( $l_['ma_may'] ) . '</strong></td>'
					. '<td>' . esc_html( $l_['nguoi'] ? $l_['nguoi'] : '—' ) . '</td>'
					. '<td>' . esc_html( $l_['ly_do'] ? $l_['ly_do'] : '—' ) . '</td>'
					. '<td>' . (int) $l_['phut'] . '</td>'
					. '<td>' . ( $da_ ? esc_html( self::gio( $l_['gui_luc'] ) )
						: '<span style="color:#8a6d00">chưa lấy</span>' ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}

		/* SỔ DOANH THU THEO GHẾ — anh Thắng 29/08/2026: "Sổ ra từng ghế theo điểm gồm các cột
		   Doanh thu ghế trong tháng, Doanh thu QR, Doanh thu Tiền mặt". Chọn tháng qua GET (không
		   POST) để bấm Xem không đụng gì tới các form Lưu/Xoá khác trên cùng trang. */
		$thang_ghe = isset( $_GET['thang_ghe'] ) ? sanitize_text_field( wp_unslash( $_GET['thang_ghe'] ) ) : '';
		if ( ! preg_match( '/^\d{4}-\d{2}$/', $thang_ghe ) ) { $thang_ghe = current_time( 'Y-m' ); }
		$dt_ghe = VHG_BaoCao::doanh_thu_thang_theo_may( $thang_ghe );

		echo '<h2>Máy (ghế) — ' . count( $may ) . ' máy</h2>';
		echo '<form method="get" style="margin-bottom:8px;display:flex;gap:8px;align-items:flex-end">'
			. '<input type="hidden" name="page" value="' . esc_attr( isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : 'vhg-may' ) . '" />'
			. '<label>Doanh thu tháng <input type="month" name="thang_ghe" value="' . esc_attr( $thang_ghe ) . '" /></label>'
			. '<button class="button">Xem</button></form>';
		echo '<table class="widefat striped"><thead><tr><th>Mã</th><th>MAC</th><th>Nhịp cuối</th>'
			. '<th>Cơ sở</th><th>Tỉ lệ quy đổi</th><th>Tài khoản nhận</th><th>QR</th>'
			. '<th>Doanh thu tháng ' . esc_html( $thang_ghe ) . '</th><th>QR</th><th>Tiền mặt</th>'
			. '<th>Đã dọn/điều chuyển</th><th></th></tr></thead><tbody>';
		if ( ! $may ) { echo '<tr><td colspan="12"><em>Chưa khai máy nào. Cắm ghế lên là nó tự hiện ở '
			. 'mục <b>Ghế chờ gán mã</b> phía trên.</em></td></tr>'; }
		$co_im = false;
		$canh_dai = array();
		$lech_nd  = array();
		$loi_tien = array();
		foreach ( $may as $m ) {
			$qr    = VHG_QR::cho_ghe( $m['ma'], 'MAU' );
			$tk_m  = VHG_May::nhan_tien_cua( $m );
			if ( empty( $m['con_song'] ) ) { $co_im = true; }
			$cb_dai = VHG_QR::canh_bao_dai( $m['ma'] );
			if ( '' !== $cb_dai ) { $canh_dai[] = $cb_dai; }
			/* 🔴 GHẾ ĐÃ NẠP FIRMWARE MỚI CHƯA — nhìn từ web không có cách nào biết, trừ khi ghế
			   TỰ KHAI. Ghế còn firmware cũ thì không hiểu ô "tiền tố" nên vẫn dựng nội dung
			   thiếu nó, và tiền vẫn biến mất y như trước. Người ta sửa ô trên web, thấy bảng
			   xem trước đúng, rồi tưởng xong. */
			if ( ! empty( $m['con_song'] )
				&& (string) $m['nd_tien_to'] !== VHG_May::tien_to_nd() ) {
				$lech_nd[] = array( 'ma' => $m['ma'], 'ghe' => (string) $m['nd_tien_to'],
					'fw' => (string) $m['fw'] );
			}
			/* 🔴 CỤC NHẬN TIỀN. Gom cả lỗi ĐANG diễn ra lẫn lỗi ĐÃ QUA, nhưng đánh dấu rõ cái nào
			   là cái nào — hai việc khác hẳn: một cái là chạy ra sửa ngay, một cái là đối chiếu
			   sổ sách. Ghế mất kết nối vẫn hiện, khác với cảnh báo firmware ở dưới: firmware
			   thì "chưa nạp" là chuyện của web, còn tiền đếm sai là tiền đã mất rồi. */
			if ( '' !== (string) $m['tm_loi'] || '' !== (string) $m['tm_cuoi'] ) {
				$loi_tien[] = array( 'ma' => $m['ma'], 'coso' => (string) $m['coso_ten'],
					'dang' => (string) $m['tm_loi'], 'cuoi' => (string) $m['tm_cuoi'],
					'lan' => (int) $m['tm_lan'], 'luc' => (string) $m['tm_luc'],
					'to' => (string) $m['tm_to'] );
			}
			$tl_m     = VHG_May::ty_le_cua( $m );
			$rieng_tl = ( (int) $m['gia'] > 0 || (int) $m['phut'] > 0 );
			$rieng = '' !== trim( (string) $m['so_tk'] ) || '' !== trim( (string) $m['bank_bin'] );
			echo '<tr><td><strong>' . esc_html( $m['ma'] ) . '</strong></td>'
				/* MAC là thứ ghế dùng để nhận ra chính nó. Hiện ra để còn đối chiếu khi một ghế
				   "không thấy đâu" — MAC rỗng nghĩa là dòng này khai tay và CHƯA gắn với ghế nào. */
				. '<td>' . ( '' !== (string) $m['mac'] ? '<code>' . esc_html( $m['mac'] ) . '</code>'
					: '<span style="color:#b32d2e" title="Dòng này khai tay, chưa ghế nào nhận">chưa gắn ghế</span>' ) . '</td>'
				/* 🔴 NHỊP CUỐI — cột quan trọng nhất khi ghế "không nhận". Ba ca đi sửa ở ba nơi
				   khác hẳn nhau, nên phải nói rõ đang ở ca nào chứ không gộp thành "mất kết nối". */
				. '<td>' . ( ! empty( $m['con_song'] )
					? '<span style="color:#046b2d">● ' . esc_html( $m['nhip_chu'] ) . '</span>'
					: '<span style="color:#b32d2e">● ' . esc_html( $m['nhip_chu'] ) . '</span>' )
				. ( '' !== (string) $m['fw']
					? '<br><span class="description">' . esc_html( $m['ip'] ) . ' · '
						. esc_html( $m['fw'] ) . '</span>' : '' ) . '</td>'
				. '<td>' . esc_html( $m['coso_ten'] ? $m['coso_ten'] : '(chưa gán)' ) . '</td>'
				. '<td>' . esc_html( self::tien( $tl_m['gia'] ) ) . ' = ' . (int) $tl_m['phut'] . ' phút'
				. ( $rieng_tl ? '<br><span class="description">khai riêng</span>'
					: '<br><span class="description">dùng chung</span>' ) . '</td>'
				. '<td><code>' . esc_html( $tk_m['so_tk'] ) . '</code> · ' . esc_html( $tk_m['bin'] )
				. ( $rieng ? '<br><span class="description">khai riêng</span>'
					: '<br><span class="description">dùng chung</span>' ) . '</td>'
				. '<td>' . ( ! empty( $qr['ok'] )
					? '<code style="font-size:10px;word-break:break-all">' . esc_html( substr( $qr['chuoi'], 0, 40 ) ) . '…</code>'
					: '<span style="color:#b32d2e">' . esc_html( $qr['error'] ) . '</span>' ) . '</td>';
			$dt = isset( $dt_ghe[ $m['ma'] ] ) ? $dt_ghe[ $m['ma'] ] : array( 'tien_mat' => 0, 'qr' => 0, 'tong' => 0 );
			echo '<td><strong>' . esc_html( self::tien( $dt['tong'] ) ) . '</strong></td>'
				. '<td>' . esc_html( self::tien( $dt['qr'] ) ) . '</td>'
				. '<td>' . esc_html( self::tien( $dt['tien_mat'] ) ) . '</td>';
			$an_may = ! empty( $m['an'] );
			/* Checkbox TỰ GỬI khi tích/bỏ tích (onchange submit) — khớp cách "mg_vip" đã làm ở
			   khối mệnh giá: ô không tích thì trình duyệt bỏ hẳn khỏi POST, server đọc vắng mặt
			   là tắt. Không cần nút Lưu riêng, tích là ăn ngay. */
			echo '<td>' . ( $an_may ? '<span style="color:#b32d2e;font-weight:600">✔ Đã dọn</span>' : '<span class="description">Đang dùng</span>' )
				. '<form method="post" style="margin-top:4px">';
			echo wp_nonce_field( 'vhg', '_wpnonce', true, false );
			echo '<input type="hidden" name="ma" value="' . esc_attr( $m['ma'] ) . '" />'
				. '<input type="hidden" name="vhg" value="an_may" />'
				. '<label><input type="checkbox" name="an" value="1" onchange="this.form.submit()"'
				. ( $an_may ? ' checked' : '' ) . '> đã dọn/điều chuyển</label></form></td>';
			echo '<td><form method="post">';
			echo wp_nonce_field( 'vhg', '_wpnonce', true, false );
			echo '<input type="hidden" name="ma" value="' . esc_attr( $m['ma'] ) . '" />'
				. '<button class="button button-small" name="vhg" value="xoa_may">Xoá</button></form></td></tr>';
		}
		echo '</tbody></table>';
		echo '<p><em>Tích <b>"đã dọn/điều chuyển"</b> để ẩn ghế đó khỏi trang thu tiền của nhân viên'
			. ' (nhân viên hết thấy để nhập chỉ số) — <b>không xoá gì</b>, doanh thu cũ và cấu hình'
			. ' máy vẫn giữ nguyên, bỏ tích là dùng lại bình thường.</em></p>';

		/* Chỉ dẫn hiện ra ĐÚNG LÚC có ghế đang im. Bảng "nhịp cuối" nói ghế đang ở ca nào; khối
		   này nói ca đó thì đi làm gì. Hiện thường trực là người ta thôi đọc. */
		if ( $loi_tien ) {
			/* Ghế nào ĐANG hỏng thì lên đầu: đó là ghế phải chạy ra xem ngay. */
			usort( $loi_tien, function ( $a, $b ) {
				$x = ( '' !== $a['dang'] ) ? 0 : 1;
				$y = ( '' !== $b['dang'] ) ? 0 : 1;
				return $x === $y ? strcmp( $a['ma'], $b['ma'] ) : $x - $y;
			} );
			$co_dang = false;
			foreach ( $loi_tien as $l ) { if ( '' !== $l['dang'] ) { $co_dang = true; } }
			echo '<div class="notice notice-' . ( $co_dang ? 'error' : 'warning' ) . ' inline">'
				. '<p><b>Cục nhận tiền báo lỗi</b> — ghế tự phát hiện, không phải ai nhập tay.</p>'
				. '<table class="widefat striped" style="max-width:900px;margin:6px 0"><thead><tr>'
				. '<th>Ghế</th><th>Tình trạng</th><th>Việc phải làm</th><th>Số lần</th>'
				. '<th>Lần cuối</th><th>Tờ gần nhất</th></tr></thead><tbody>';
			foreach ( $loi_tien as $l ) {
				$ma_l = '' !== $l['dang'] ? $l['dang'] : $l['cuoi'];
				echo '<tr><td><strong>' . esc_html( $l['ma'] ) . '</strong>'
					. ( '' !== $l['coso'] ? '<br><span class="description">' . esc_html( $l['coso'] )
						. '</span>' : '' ) . '</td>'
					. '<td>' . ( '' !== $l['dang']
						? '<span style="color:#b32d2e;font-weight:600">● ĐANG HỎNG</span>'
						: '<span style="color:#8a6d00">○ đã hết, còn dấu vết</span>' ) . '</td>'
					. '<td>' . esc_html( VHG_May::loi_tien_chu( $ma_l ) ) . '</td>'
					. '<td>' . (int) $l['lan'] . '</td>'
					. '<td>' . esc_html( '' !== $l['luc'] ? self::gio( $l['luc'] ) : '—' ) . '</td>'
					/* "Tờ gần nhất" là SỐ LIỆU, không phải lỗi: cả ngày không ai trả tiền mặt là
					   chuyện bình thường. Để cạnh đây vì khi ĐÃ có lỗi thì nó nói lỗi bắt đầu từ
					   bao giờ — máy nuốt tiền từ trưa thì cột này đứng im từ trưa. */
					. '<td>' . esc_html( '' !== $l['to'] ? self::gio( $l['to'] ) : 'chưa lần nào' )
					. '</td></tr>';
			}
			echo '</tbody></table></div>';
		}
		if ( $lech_nd ) {
			echo '<div class="notice notice-error inline"><p><b>Ghế chưa nhận được tiền tố nội dung '
				. '— nạp lại firmware bằng USB.</b></p>'
				. '<table class="widefat striped" style="max-width:640px;margin:6px 0"><thead><tr>'
				. '<th>Ghế</th><th>Web khai</th><th>Ghế đang dùng</th><th>Firmware ghế</th>'
				. '</tr></thead><tbody>';
			foreach ( $lech_nd as $l ) {
				echo '<tr><td><b>' . esc_html( $l['ma'] ) . '</b></td>'
					. '<td><code>' . esc_html( VHG_May::tien_to_nd() ) . '</code></td>'
					. '<td>' . ( '' === $l['ghe']
						? '<span style="color:#b32d2e">(trống)</span>'
						: '<code>' . esc_html( $l['ghe'] ) . '</code>' ) . '</td>'
					. '<td><span class="description">' . esc_html( $l['fw'] ) . '</span></td></tr>';
			}
			echo '</tbody></table>'
				. '<p>Ghế <b>tự dựng nội dung chuyển khoản</b> lúc khách bấm chọn gói, nên nó phải biết '
				. 'chuỗi này. Bản firmware cũ không hiểu ô tiền tố — sửa trên web bao nhiêu lần cũng '
				. 'không tới được ghế, và <b>tiền vẫn biến mất không dấu vết</b> y như trước.</p>'
				. '<p>Ghế <b>không có OTA</b>: phải cắm USB nạp lại. Bảng này hết dòng nào là ghế đó xong.</p></div>';
		}
		if ( $canh_dai ) {
			echo '<div class="notice notice-error inline"><p><b>Nội dung chuyển khoản quá dài:</b></p><ul '
				. 'style="margin-left:18px;list-style:disc">';
			foreach ( array_unique( $canh_dai ) as $c_d ) { echo '<li>' . esc_html( $c_d ) . '</li>'; }
			echo '</ul></div>';
		}
		if ( $co_im ) {
			echo '<div class="notice notice-warning inline"><p><b>Có ghế không gửi nhịp.</b> Ba ca, '
				. 'ba chỗ sửa khác nhau:</p><ol style="margin-left:20px;list-style:decimal">'
				. '<li><b>“chưa bao giờ gửi nhịp”</b> — ghế chưa từng nói chuyện với website. Kiểm: '
				. 'đã nạp firmware mới bằng USB chưa; trong <code>secrets.h</code> địa chỉ web có '
				. 'đúng <code>' . esc_html( home_url( '/' ) ) . '</code> không; khoá máy có khớp '
				. '<code>VHG_KHOA_MAY</code> trong <code>wp-config.php</code> không.</li>'
				. '<li><b>“im từ vài phút trước”</b> — mạng chập. Đợi một lượt nhịp (~30 giây) rồi '
				. 'tải lại trang.</li>'
				. '<li><b>“im từ vài giờ/ngày trước”</b> — ghế mất điện, rớt 4G, hoặc treo. Thử '
				. '<b>Khởi động lại</b> ở tab Điều khiển của trang ngoài; không lên thì phải tới nơi.</li>'
				. '</ol><p>Ghế mất nhịp thì <b>khách vẫn quét được tem QR dán trên ghế, tiền vẫn vào, '
				. 'nhưng ghế không chạy</b> — nên đây là việc gấp, không phải việc để mai.</p></div>';
		}

		echo '<h3>Thêm / sửa máy</h3><form method="post">';
		wp_nonce_field( 'vhg' );
		echo '<table class="form-table">';
		echo '<tr><th>Mã máy *</th><td><input type="text" name="ma" required pattern="[A-Za-z0-9]{1,20}" />'
			. '<p class="description">Chỉ chữ và số, không dấu, không khoảng trắng. Mã này đi vào nội dung '
			. 'chuyển khoản khách gõ tay (<code>GHE&lt;mã&gt; &lt;mã lượt&gt;</code>) — có dấu là khách gõ sai '
			. 'và ghế không chạy. Khai lại đúng mã cũ = sửa máy đó.</p></td></tr>';
		echo '<tr><th>Cơ sở</th><td><select name="coso_id"><option value="0">— chưa gán —</option>';
		foreach ( $coso as $c ) {
			echo '<option value="' . (int) $c['id'] . '">' . esc_html( $c['ten'] ) . '</option>';
		}
		echo '</select></td></tr>';
		/* 🔴 Ô MAC — anh Thắng 22/08/2026: *"không có chỗ nhập mac, chỉ có mã"*. Đúng, và dòng
		   khai tay không có MAC là dòng KHÔNG GẮN VỚI GHẾ NÀO: ghế cắm điện lên sẽ đẻ ra một
		   dòng thứ hai, và dòng đang chạy thật là dòng kia.
		   Nhưng cách đúng vẫn là để ghế tự hiện ra rồi bấm Gán — nên ô này nói thẳng điều đó. */
		echo '<tr><th>Địa chỉ MAC</th><td><input type="text" name="mac" class="regular-text code" '
			. 'placeholder="AA:BB:CC:DD:EE:FF — để trống nếu chưa biết" />'
			. '<p class="description"><b>Thường không cần gõ.</b> Cắm ghế lên là nó tự hiện ở mục '
			. '<b>Ghế chờ gán mã</b> phía trên, bấm Gán là xong — khỏi gõ 12 ký tự hex.<br>'
			. 'Chỉ gõ tay khi ghế chưa nối được mạng mà anh muốn khai trước. Gõ sai một ký tự là '
			. 'ghế thật không nhận ra dòng này, và nó sẽ hiện ra như một ghế mới.<br>'
			. 'Để trống khi <b>sửa</b> một máy đã có thì MAC cũ được giữ nguyên, không bị xoá.</p></td></tr>';
		/* 🔴 KHÔNG PHẢI "GIÁ MỘT LƯỢT". Hai ô này là TỈ LỆ QUY ĐỔI: ghế tính
		   `phút = tiền × phút / giá`. Nhãn cũ ghi "Giá một lượt" làm anh Thắng tưởng ghế chỉ có
		   một mệnh giá, trong khi màn ghế có bốn nút. Nên đổi nhãn, và in thẳng bảng quy đổi ra
		   — một bảng số cụ thể nói rõ hơn mọi câu giải thích. */
		echo '<tr><th>Tỉ lệ riêng (tuỳ chọn)</th><td>'
			. '<input type="number" name="gia" value="" min="0" step="1000" style="width:110px" placeholder="0 = chung" /> đ '
			. '= <input type="number" name="phut" value="" min="0" max="240" style="width:70px" placeholder="0" /> phút'
			. '<p class="description"><b>Để trống là đúng trong hầu hết trường hợp</b> — ghế dùng tỉ lệ '
			. 'chung khai ở trên (' . esc_html( self::tien( $tl_c['gia'] ) ) . ' = ' . (int) $tl_c['phut']
			. ' phút). Chỉ điền khi ghế này cố ý chạy tỉ lệ khác.</p></td></tr>';
		echo '<tr><th>Tài khoản riêng (tuỳ chọn)</th><td>'
			. '<input type="text" name="so_tk" class="regular-text code" placeholder="số TK — trống = dùng chung" />'
			. ' <input type="text" name="bank_bin" style="width:110px" placeholder="BIN" />'
			. ' <input type="text" name="ten_tk" style="width:180px" placeholder="tên TK" />'
			. '<p class="description"><b>Để trống cả ba là đúng trong hầu hết trường hợp</b> — ghế dùng '
			. 'tài khoản chung khai ở trên. Chỉ điền khi ghế này nhận tiền vào một tài khoản khác.</p></td></tr>';
		echo '<tr><th>Tên trên sao kê</th><td><input type="text" name="ten_khai" class="regular-text" placeholder="VD: AMTP 03" />'
			. '<p class="description">Tên máy như Tingo/VietQR ghi trong sao kê. Khai đúng thì doanh thu nhập '
			. 'từ Excel tự gộp vào đúng máy này.</p></td></tr>';
		echo '</table><p><button class="button button-primary" name="vhg" value="may">Lưu máy</button></p></form></div>';
	}

	// ======================================================================= 3. CỔNG & NHẬT KÝ

	public static function trang_cong() {
		self::gac();
		$bao = array();
		if ( isset( $_POST['vhg'] ) ) {
			check_admin_referer( 'vhg' );
			$viec = sanitize_text_field( wp_unslash( $_POST['vhg'] ) );
			if ( 'nhap_gd' === $viec ) {
				$bao[] = VHG_Nhap::nhap_giao_dich( VHG_Nhap::bang_tu_van_ban( wp_unslash( $_POST['bang'] ) ) );
			} elseif ( 'nhap_bd' === $viec ) {
				$bao[] = VHG_Nhap::nhap_ban_do( VHG_Nhap::bang_tu_van_ban( wp_unslash( $_POST['bang'] ) ) );
			} elseif ( 'ap_lai' === $viec ) {
				$bao[] = VHG_Nhap::ap_lai_ban_do();
			} elseif ( 'don_tien_ra' === $viec ) {
				$bao[] = VHG_Nhap::don_tien_ra();
			} elseif ( 'xoa_log' === $viec ) {
				$bao[] = VHG_Nhat_Ky::xoa();
			} elseif ( 'xoa_bd' === $viec ) {
				$bao[] = VHG_Nhap::xoa_ban_do( wp_unslash( $_POST['khoa'] ) );
			}
		}

		echo '<div class="wrap"><h1>Nhận tiền &amp; nhật ký</h1>';
		self::ve_bao( $bao );

		/* ---- Link webhook ---- */
		$co_khoa = '' !== VHG_Cong::khoa( 'VHG_KHOA_WEBHOOK' );
		echo '<h2>Link webhook để dán vào bên gửi</h2>';
		if ( ! $co_khoa ) {
			echo '<div class="notice notice-error"><p><strong>Chưa khai <code>VHG_KHOA_WEBHOOK</code> trong '
				. 'wp-config.php nên cổng đang ĐÓNG</strong> — mọi lượt bắn tới đều bị từ chối. Thêm dòng này '
				. 'vào <code>wp-config.php</code> (chuỗi ngẫu nhiên dài, tự đặt):</p>'
				. '<p><code>define( \'VHG_KHOA_WEBHOOK\', \'…chuỗi dài ngẫu nhiên…\' );</code></p>'
				. '<p>Khoá KHÔNG nằm trong mã nguồn, cố ý: mã nguồn nằm ở repo công khai.</p></div>';
		} else {
			$khoa = rawurlencode( VHG_Cong::khoa( 'VHG_KHOA_WEBHOOK' ) );
			$goc  = home_url( '/' . VHG_Cong::DUONG_TIEN );
			echo '<table class="form-table">';
			echo '<tr><th>VietQR / Tingo</th><td><input type="text" class="large-text" readonly '
				. 'onclick="this.select()" value="' . esc_attr( $goc . '?src=vietqr&token=' . $khoa ) . '" /></td></tr>';
			echo '<tr><th>SePay</th><td><input type="text" class="large-text" readonly '
				. 'onclick="this.select()" value="' . esc_attr( $goc . '?token=' . $khoa ) . '" /></td></tr>';
			echo '</table>';
			echo '<p><em>Mở link này bằng trình duyệt (GET) sẽ ra một câu xác nhận cổng còn sống — đó là cách '
				. 'nhanh nhất để biết tường lửa của hosting có chặn đường này không.</em></p>';
		}
		echo '<p><strong>Nội dung chuyển khoản nên có dạng <code>GHE&lt;mã máy&gt; &lt;mã lượt&gt;</code></strong> '
			. '(VD <code>GHE3 T1ABC</code>) để tự khớp đúng ghế. Không khớp thì tiền <strong>vẫn vào sổ</strong>, '
			. 'chỉ là chưa gắn được máy — đối soát tay sau, không mất.</p>';

		/* ---- Nhập bảng ---- */
		echo '<h2>Nhập bảng giao dịch từ Tingo / VietQR</h2>';
		echo '<p>Copy từ Excel (<strong>bôi đen cả dòng tiêu đề</strong>) rồi dán vào ô dưới. Nhập lại đúng '
			. 'file đó <strong>không cộng đôi</strong>: mỗi giao dịch ghi theo mã tham chiếu của ngân hàng.</p>';
		echo '<form method="post">';
		wp_nonce_field( 'vhg' );
		echo '<textarea name="bang" rows="6" class="large-text code" placeholder="Mã tham chiếu	Mã điểm bán	Mã cửa hàng	Số tiền đến (VND)	Nội dung TT	Thời gian tạo"></textarea>';
		echo '<p><button class="button button-primary" name="vhg" value="nhap_gd">Nhập giao dịch</button> '
			. '<button class="button" name="vhg" value="nhap_bd">Nhập danh sách Voice Box (bản đồ máy)</button></p></form>';

		echo '<form method="post" style="margin-bottom:16px">';
		wp_nonce_field( 'vhg' );
		echo '<button class="button" name="vhg" value="ap_lai">Áp lại bản đồ cho giao dịch chưa rõ máy</button> '
			. '<button class="button" name="vhg" value="don_tien_ra">Dọn giao dịch tiền ra bị tính nhầm</button>';
		echo '</form>';

		/* ---- Bản đồ máy ---- */
		$bd = VHG_Nhap::ds_ban_do();
		echo '<h2>Bản đồ máy (' . count( $bd ) . ')</h2>';
		echo '<p><em>Giao dịch nội dung "PaymentForOrder" không nói máy nào — chỉ Mã điểm bán mới nói. Bảng này '
			. 'học từ những giao dịch CÓ tên rồi áp cho những giao dịch không có. Dòng do người khai '
			. '(<strong>khai tay</strong>) thì máy không ghi đè.</em></p>';
		echo '<table class="widefat striped"><thead><tr><th>Khoá (mã điểm bán / mã cửa hàng)</th>'
			. '<th>Tên máy</th><th>Nguồn</th><th>Cập nhật</th><th></th></tr></thead><tbody>';
		if ( ! $bd ) { echo '<tr><td colspan="5"><em>Chưa có.</em></td></tr>'; }
		foreach ( array_slice( $bd, 0, 200 ) as $r ) {
			echo '<tr><td><code>' . esc_html( $r['khoa'] ) . '</code></td><td>' . esc_html( $r['ten_may'] ) . '</td>'
				. '<td>' . ( (int) $r['tu_hoc'] ? 'máy tự học' : '<strong>khai tay</strong>' ) . '</td>'
				. '<td>' . esc_html( (string) $r['cap_nhat'] ) . '</td><td><form method="post">';
			echo wp_nonce_field( 'vhg', '_wpnonce', true, false );
			echo '<input type="hidden" name="khoa" value="' . esc_attr( $r['khoa'] ) . '" />'
				. '<button class="button button-small" name="vhg" value="xoa_bd">Xoá</button></form></td></tr>';
		}
		echo '</tbody></table>';

		/* ---- Nhật ký ---- */
		$log = VHG_Nhat_Ky::ds( 100 );
		echo '<h2>Nhật ký cổng nhận tiền (' . count( $log ) . ')</h2>';
		echo '<p><em>Ghi <strong>mọi</strong> lượt bắn tới, kể cả lượt bị từ chối vì sai khoá. Đó là cách duy '
			. 'nhất phân biệt "bên gửi chưa bắn" với "bắn rồi mà mình chặn" — hai ca đó đi sửa ở hai nơi '
			. 'khác hẳn. Giữ ' . (int) VHG_Nhat_Ky::GIU . ' dòng gần nhất.</em></p>';
		echo '<table class="widefat striped"><thead><tr><th>Lúc</th><th>Nguồn</th><th>Số tiền</th>'
			. '<th>Nội dung</th><th>Máy</th><th>Ghi chú</th></tr></thead><tbody>';
		if ( ! $log ) { echo '<tr><td colspan="6"><em>Chưa có lượt nào bắn tới. Thử chuyển 1.000đ để kiểm tra.</em></td></tr>'; }
		foreach ( $log as $l ) {
			echo '<tr><td>' . esc_html( $l['luc'] ) . '</td><td>' . esc_html( $l['nguon'] ) . '</td>'
				. '<td>' . esc_html( self::tien( $l['so_tien'] ) ) . '</td>'
				. '<td>' . esc_html( $l['noi_dung'] ) . '</td>'
				. '<td>' . esc_html( $l['ma_may'] ? $l['ma_may'] : $l['ten_khai'] ) . '</td>'
				. '<td>' . esc_html( $l['ghi_chu'] ) . '</td></tr>';
		}
		echo '</tbody></table>';
		if ( $log ) {
			echo '<h3>Gói thô mới nhất</h3><p><em>Bên gửi đổi tên trường mà không báo là chuyện thường. '
				. 'Đây là thứ duy nhất cho biết họ đang gửi gì.</em></p>';
			echo '<pre style="background:#f6f7f7;border:1px solid #c3c4c7;padding:10px;overflow:auto;max-height:220px">'
				. esc_html( (string) $log[0]['tho'] ) . '</pre>';
		}
		echo '<form method="post">';
		wp_nonce_field( 'vhg' );
		echo '<p><button class="button" name="vhg" value="xoa_log">Xoá nhật ký</button> '
			. '<em>Không ảnh hưởng doanh thu đã ghi.</em></p></form>';
		echo '</div>';
	}

	// ======================================================================= 4. TRANG NGOÀI & PIN

	/**
	 * Khai đường dẫn trang ngoài, ai vào được, và danh sách PIN riêng khi không dùng chung.
	 *
	 * ⚠️ MÀN NÀY KHÔNG BAO GIỜ IN PIN — chỉ in SỐ CHỮ SỐ. Ảnh màn hình đi khắp nơi; trong chính
	 *    dự án này đã mất một khoá cầu nối và một khoá webhook vì ảnh gửi qua chat.
	 */
	/**
	 * TRANG IN TEM QR — mỗi ghế một tấm, dán lên chính ghế đó.
	 *
	 * 🔴 Vì sao tem phải do HỆ THỐNG in ra, không phải chủ tự vào một trang tạo QR nào đó gõ tay:
	 *    gõ nhầm một ký tự trong địa chỉ là cả một cửa hàng dán tem dẫn đi đâu không rõ, và
	 *    không ai phát hiện cho tới khi khách kêu. Ở đây địa chỉ do chính hệ thống dựng, đúng
	 *    cái mà mục "Dùng mã" sẽ đọc.
	 *
	 * ⚠️ IN RA GIẤY thì to bao nhiêu cũng được — khác hẳn mã trên màn ghế vốn bị bó trong đúng
	 *    `VHG_Ma::QR_VUNG_PX` pixel. Đừng chép con số đó vào đây thành chữ: nó đã đổi một lần
	 *    (58 -> 70 ngày 23/08/2026) và mọi chỗ chép tay đều thành sai.
	 *    Nên tem in là đường CHẮC CHẮN, mã trên màn là đường TIỆN.
	 */
	public static function trang_tem() {
		self::gac();
		$may = VHG_May::ds_may();
		echo '<div class="wrap"><h1>Tem QR dán lên ghế</h1>';
		if ( ! get_option( 'permalink_structure' ) ) {
			echo '<div class="notice notice-error inline"><p><b>Chưa bật Đường dẫn tĩnh.</b> '
				. 'Địa chỉ ngắn cho tem chỉ chạy khi WordPress dùng đường dẫn đẹp. '
				. 'Vào <a href="' . esc_url( admin_url( 'options-permalink.php' ) ) . '">Cài đặt → '
				. 'Đường dẫn tĩnh</a>, chọn <b>Tên bài viết</b> rồi Lưu.</p></div></div>';
			return;
		}
		echo '<p>Mỗi ghế một tem, mang <b>đúng mã ghế đó</b>. Khách quét tem nào là hệ thống biết '
			. 'họ đang ngồi ghế đó — khỏi phải chọn từ danh sách, khỏi chọn lộn.</p>'
			. '<p class="description">In ra, cắt theo khung, dán lên tay vịn hoặc cạnh thùng tiền. '
			. 'Dán <b>đúng ghế</b>: tem AMTP01 mà dán lên ghế khác thì mã của khách chạy sai ghế.</p>'
			. '<p><button class="button button-primary" onclick="window.print()">In trang này</button></p>';

		if ( ! $may ) {
			echo '<p><em>Chưa khai ghế nào.</em></p></div>';
			return;
		}

		echo '<style>@media print{.vhg-an{display:none!important}#adminmenumain,#wpadminbar,'
			. '#wpfooter,.notice{display:none!important}#wpcontent{margin-left:0!important}'
			. '.vhg-tem{break-inside:avoid;page-break-inside:avoid}}'
			. '.vhg-luoi{display:flex;flex-wrap:wrap;gap:14px;margin-top:16px}'
			. '.vhg-tem{width:230px;border:1px dashed #999;border-radius:10px;padding:12px;'
			. 'text-align:center;background:#fff}'
			. '.vhg-tem .ma{font-size:22px;font-weight:800;letter-spacing:.06em;margin-top:6px}'
			. '.vhg-tem .cs{font-size:12px;color:#555}'
			. '.vhg-tem .moi{font-size:13px;font-weight:600;margin-bottom:6px}'
			. '.vhg-tem .dc{font-size:10px;color:#666;word-break:break-all;margin-top:6px}</style>';
		echo '<div class="vhg-luoi">';
		$hong = array();
		foreach ( $may as $m ) {
			$ma = (string) $m['ma'];
			if ( '' === $ma || '?' === $ma[0] ) { continue; }
			/* Tem in dùng địa chỉ ĐẦY ĐỦ (có https://): giấy thì không thiếu chỗ, mà có scheme
			   thì mọi máy quét đều mở được ngay, khỏi phụ thuộc máy có tự đoán ra là link hay không. */
			$u = VHG_Shop::url_ghe( $ma );
			/* Mức sửa lỗi M cho tem in: cao hơn L nên chịu được vết xước, mờ mực, cong giấy —
			   tem sống trên tay vịn ghế nhiều năm. Mã trên màn ghế thì dùng L vì ở đó từng module
			   là quý, còn giấy thì không thiếu chỗ. */
			$o = VHG_QRVe::ma_tran( $u, 'M' );
			if ( ! $o ) { $hong[] = $ma; continue; }
			echo '<div class="vhg-tem"><div class="moi">Mua mã giảm giá</div>'
				. VHG_QRVe::svg( $o, 190 )
				. '<div class="ma">' . esc_html( $ma ) . '</div>'
				. '<div class="cs">' . esc_html( $m['coso_ten'] ? $m['coso_ten'] : '' ) . '</div>'
				. '<div class="dc">' . esc_html( $u ) . '</div></div>';
		}
		echo '</div>';
		/* 🔴 Ghế nào không dựng được tem thì NÓI RA. Im lặng bỏ qua là chủ in ra thiếu vài tấm mà
		   không biết, rồi vài cái ghế đứng đó không ai quét được. */
		if ( $hong ) {
			echo '<div class="notice notice-error inline vhg-an"><p><b>Không dựng được tem cho: '
				. esc_html( implode( ', ', $hong ) ) . '</b> — mã ghế quá dài. Đặt lại mã ngắn hơn '
				. '(dưới 12 ký tự) ở màn Máy &amp; cơ sở.</p></div>';
		}
		echo '</div>';
	}

	public static function trang_ngoai() {
		self::gac();
		$bao = array();
		if ( isset( $_POST['vhg'] ) ) {
			check_admin_referer( 'vhg' );
			$viec = sanitize_text_field( wp_unslash( $_POST['vhg'] ) );
			if ( 'luu_trang' === $viec ) {
				$slug = sanitize_title( wp_unslash( $_POST['slug'] ) );
				if ( '' === $slug ) { $slug = 'ghe'; }
				if ( get_option( 'vhg_slug' ) !== $slug ) { update_option( 'vhg_flush_rewrite', 1 ); }
				update_option( 'vhg_slug', $slug );

				$nguon = sanitize_text_field( wp_unslash( $_POST['nguon'] ) );
				update_option( 'vhg_nguon_nguoidung', 'rieng' === $nguon ? 'rieng' : 'chung' );

				$vt = array();
				foreach ( (array) ( isset( $_POST['vai_tro'] ) ? $_POST['vai_tro'] : array() ) as $x ) {
					$x = sanitize_text_field( wp_unslash( $x ) );
					if ( in_array( $x, VHG_Auth::VAI_TRO_TAT_CA, true ) ) { $vt[] = $x; }
				}
				update_option( 'vhg_vai_tro_vao', $vt );
				$vtc = isset( $_POST['vai_tro_chot'] ) ? (array) wp_unslash( $_POST['vai_tro_chot'] ) : array();
				$vtc = array_values( array_filter( array_map( 'strval', $vtc ),
					function ( $x ) { return in_array( $x, VHG_Auth::VAI_TRO_TAT_CA, true ); } ) );
				/* Admin luôn có — ô đó `disabled` nên trình duyệt KHÔNG gửi nó lên, và không thêm
				   lại ở đây thì mỗi lần bấm Lưu là Admin tự rơi khỏi danh sách chốt doanh số. */
				if ( ! in_array( 'Admin', $vtc, true ) ) { array_unshift( $vtc, 'Admin' ); }
				update_option( 'vhg_vai_tro_chot', $vtc );

				/* Ảnh nền trang ngoài. `esc_url_raw` bỏ mọi thứ không phải URL; ô này rỗng thì
				   trang rơi về dải màu tự dựng, KHÔNG phải nền trắng. */
				$nen = esc_url_raw( trim( (string) wp_unslash( $_POST['anh_nen'] ) ) );
				update_option( 'vhg_anh_nen', $nen );

				/* Bảng giảm giá bán mã trước, theo từng mệnh giá. Khai một ô rỗng = không giảm. */
				$gi = array();
				foreach ( (array) ( isset( $_POST['giam'] ) ? $_POST['giam'] : array() ) as $mg => $pt ) {
					$mg = (int) $mg; $pt = (int) $pt;
					if ( $mg > 0 && $pt > 0 ) { $gi[ $mg ] = min( 70, $pt ); }
				}
				update_option( 'vhg_ma_giam', $gi );
				update_option( 'vhg_ma_cho_ngay',
					max( 0, min( 365, isset( $_POST['ma_cho_ngay'] ) ? (int) $_POST['ma_cho_ngay'] : 5 ) ) );
				/* Ô quảng cáo: rỗng = tắt. Xem VHG_May::qc_ma() — `false`/rỗng phải ra -1, không
				   phải 0, nếu không thì "chưa khai" thành "bật ô đầu tiên". */
				$qo = isset( $_POST['qc_o'] ) ? trim( (string) wp_unslash( $_POST['qc_o'] ) ) : '';
				update_option( 'vhg_qc_o', '' === $qo ? '' : (int) $qo );
				$qg = isset( $_POST['qc_giay'] ) ? (int) $_POST['qc_giay'] : 30;
				update_option( 'vhg_qc_giay', max( 5, min( 300, $qg ) ) );
				$bao[] = array( 'ok' => true, 'thong_bao' => 'Đã lưu. Địa chỉ trang: ' . VHG_Trang::url() );
			} elseif ( 'them_nd' === $viec ) {
				$bao[] = self::them_nguoi_dung(
					wp_unslash( $_POST['ten'] ), wp_unslash( $_POST['pin'] ),
					wp_unslash( $_POST['vai_tro_moi'] ), wp_unslash( $_POST['coso'] ) );
			} elseif ( 'xoa_nd' === $viec ) {
				$ds = (array) get_option( 'vhg_nguoidung' );
				unset( $ds[ (int) $_POST['i'] ] );
				update_option( 'vhg_nguoidung', array_values( $ds ) );
				$bao[] = array( 'ok' => true, 'thong_bao' => 'Đã xoá.' );
			}
		}

		echo '<div class="wrap"><h1>Trang ngoài &amp; PIN</h1>';
		self::ve_bao( $bao );
		echo '<p>Trang cho nhân viên cửa hàng mở trên điện thoại — xem doanh thu, biết ghế nào đang '
			. 'đứng, thu tiền mặt. <b>Không cần tài khoản WordPress</b>, chỉ cần PIN.</p>';
		echo '<p><a class="button button-primary" href="' . esc_url( VHG_Trang::url() ) . '" target="_blank">'
			. esc_html( VHG_Trang::url() ) . '</a></p>';
		/* 🔴 Permalinks kiểu "Plain" thì luật đường dẫn KHÔNG chạy — `/ghe` trả 404, và trang chỉ
		   mở được bằng địa chỉ `?vhg=app` xấu và khó đọc cho người phải gõ trên điện thoại. Nói
		   ra ở đây, vì triệu chứng (404) không hề gợi tới nguyên nhân (một ô cài đặt của
		   WordPress ở màn khác hẳn). */
		if ( ! get_option( 'permalink_structure' ) ) {
			echo '<div class="notice notice-warning inline"><p><b>WordPress đang để đường dẫn kiểu '
				. '“Plain”</b> nên <code>/' . esc_html( VHG_Trang::slug() ) . '</code> sẽ trả 404. '
				. 'Vào <a href="' . esc_url( admin_url( 'options-permalink.php' ) ) . '">Settings → '
				. 'Permalinks</a> chọn <b>Post name</b> rồi Lưu. Hai cổng máy '
				. '(<code>/ghe-tien</code>, <code>/ghe-may</code>) cũng cần đúng thứ đó.</p></div>';
		}

		/* Một khối form DUY NHẤT cho phần cài đặt. Các form con (thêm/xoá người) dựng RIÊNG ở
		   dưới, KHÔNG lồng — xem bài học ở class-vhcc-admin.php: <form> lồng <form> thì trình
		   duyệt lặng lẽ gộp ô nhập vào form cha, và một ô `required` ở cuối trang chặn luôn
		   nút Lưu ở đầu trang. */
		echo '<form method="post">';
		wp_nonce_field( 'vhg' );
		echo '<table class="form-table">';
		echo '<tr><th>Đường dẫn trang</th><td>' . esc_html( home_url( '/' ) )
			. '<input name="slug" value="' . esc_attr( VHG_Trang::slug() ) . '" class="regular-text" /> /'
			. '<p class="description">Mặc định <code>ghe</code>.</p></td></tr>';

		/* Ảnh nền: dán ĐỊA CHỈ ảnh, không phải tải lên từ đây. Làm ô tải lên nghĩa là ôm luôn
		   phần cắt cỡ, nén, và dọn ảnh cũ — trong khi Thư viện của WordPress đã làm đủ, và ảnh
		   nằm đó thì còn thay được mà không đụng tới plugin này. */
		/* ===== BÁN MÃ TRƯỚC ============================================================== */
		echo '<tr><th>Giảm giá khi mua mã trước</th><td>';
		echo '<p class="description" style="margin-top:0">Khách mua mã hôm nay với giá đã giảm, '
			. 'dùng hôm khác, ở <b>bất kỳ ghế nào</b>. Để trống hoặc 0 là <b>không bán</b> mệnh giá đó.</p>';
		$giam_ht = VHG_Ma::bang_giam();
		echo '<table class="widefat striped" style="max-width:520px"><thead><tr><th>Mệnh giá</th>'
			. '<th>Giảm (%)</th><th>Khách trả</th></tr></thead><tbody>';
		foreach ( VHG_May::menh_gia() as $g_ ) {
			$mg_ = (int) $g_['tien'];
			$pt_ = isset( $giam_ht[ $mg_ ] ) ? (int) $giam_ht[ $mg_ ] : 0;
			echo '<tr><td>' . esc_html( self::tien( $mg_ ) ) . '</td>'
				. '<td><input type="number" min="0" max="70" name="giam[' . $mg_ . ']" value="'
				. $pt_ . '" style="width:80px" /></td>'
				. '<td><b>' . esc_html( self::tien( VHG_Ma::gia_ban( $mg_ ) ) ) . '</b></td></tr>';
		}
		echo '</tbody></table>';
		/* 🔴 Chốt chống mua-xong-dùng-liền. Không có nó thì bảng giá sập: ai cũng mua mã rẻ rồi
		   quét ngay thay vì trả giá gốc tại ghế. */
		echo '<p style="margin-top:10px"><label>Mã dùng được sau '
			. '<input type="number" min="0" max="365" name="ma_cho_ngay" value="'
			. (int) VHG_Ma::cho_ngay_mac_dinh() . '" style="width:70px" /> ngày kể từ lúc mua</label></p>';
		echo '<p class="description">Giảm giá là để đổi lấy việc khách <b>trả tiền trước</b>. '
			. 'Không có thời gian chờ thì khách đứng ngay cạnh ghế cũng mua mã rẻ rồi quét luôn — '
			. 'và không ai trả giá gốc tại ghế nữa.<br>'
			. '<b>0 = cho dùng ngay.</b> Số ngày được <b>đóng băng vào từng mã lúc bán</b>: đổi ô này '
			. 'không áp ngược cho mã đã bán, vì đó là điều kiện khách đã trả tiền để nhận.</p>';
		/* 🔴 Khoản NỢ. Mã không hết hạn nên con số này chỉ cộng lên và không bao giờ tự đóng.
		   Hiện ngay cạnh ô khai giảm giá: đó là chỗ người ta quyết định giảm bao nhiêu. */
		$no_ = VHG_Ma::tien_no();
		if ( $no_['so_ma'] > 0 ) {
			echo '<p class="description" style="margin-top:8px"><b style="color:#b32d2e">Đang nợ khách '
				. esc_html( self::tien( $no_['tong'] ) ) . '</b> — ' . (int) $no_['so_ma']
				. ' mã đã bán mà chưa dùng (đã thu ' . esc_html( self::tien( $no_['da_thu'] ) )
				. '). Mã <b>không hết hạn</b>, nên con số này chỉ cộng lên: mỗi mã chưa dùng là một '
				. 'lượt massage còn nợ khách.</p>';
		}
		echo '<p class="description" style="margin-top:8px">Trang bán mã cho khách: '
			. '<a href="' . esc_url( VHG_Shop::url() ) . '" target="_blank">'
			. esc_html( VHG_Shop::url() ) . '</a><br>'
			. 'Tem dán ở từng ghế thì thêm mã ghế vào cuối, ví dụ <code>'
			. esc_html( VHG_Shop::url( 'AMTP01' ) ) . '</code> — khách quét tem ghế nào thì mục '
			. '<b>Dùng mã</b> tự chạy đúng ghế đó.</p></td></tr>';

		echo '<tr><th>Mời mua mã trên màn ghế</th><td>';
		$qc_ = VHG_May::qc_ma();
		$qc_o_tho = get_option( 'vhg_qc_o' );
		echo '<label>Ô số <input type="number" min="0" max="3" name="qc_o" value="'
			. ( ( false === $qc_o_tho || '' === $qc_o_tho ) ? '' : (int) $qc_o_tho )
			. '" style="width:70px" placeholder="tắt" /></label> '
			. '<label style="margin-left:14px">Mỗi vế <input type="number" min="5" max="300" '
			. 'name="qc_giay" value="' . (int) $qc_["giay"] . '" style="width:80px" /> giây</label>';
		/* 🔴 Hiện CON SỐ, không hiện lời hứa. Mã QR trong ô gói chỉ được VHG_Ma::QR_VUNG_PX px; địa chỉ dài thêm
		   vài ký tự là mã rơi xuống 1 pixel mỗi module — nhìn vẫn "có mã QR" mà không máy nào
		   quét nổi, và không ai báo cho cửa hàng. */
		$may_qr = VHG_May::ds_may();
		$ma_dau = '';
		foreach ( $may_qr as $m_q ) {
			if ( '' !== (string) $m_q['ma'] && '?' !== $m_q['ma'][0] ) { $ma_dau = $m_q['ma']; break; }
		}
		if ( '' !== $ma_dau ) {
			/* Ô trên màn ghế thì dùng bản bỏ scheme — vùng vẽ trên màn không chứa nổi bản đầy đủ. */
			$u_ngan = VHG_Shop::url_ngan( $ma_dau );
			$qr_o   = VHG_Ma::qr_o_goi( $u_ngan );
			$xau    = ( (int) $qr_o['px'] < 2 );
			echo '<p class="description" style="margin-top:8px">Mã QR ghế tự vẽ trong ô đó dẫn tới '
				. '<code>' . esc_html( '' !== $u_ngan ? $u_ngan : '(chưa có — cần bật Đường dẫn tĩnh)' )
				. '</code><br>' . ( $xau ? '<b style="color:#b32d2e">' : '' )
				. esc_html( $qr_o['chu'] ) . ( $xau ? '</b>' : '' );
			if ( '' !== $u_ngan ) {
				echo '<br>Địa chỉ dài ' . (int) $qr_o['dai'] . ' ký tự'
					. ( ! empty( $qr_o['alnum'] ) ? '' : ' (có ký tự thường/lạ nên mã đặc hơn — '
						. 'nên để đường dẫn chỉ gồm chữ và số)' ) . '. '
					. 'Muốn mã to hơn thì đặt <b>đường dẫn trang bán mã</b> ngắn lại, ví dụ '
					. '<code>m</code> thay cho <code>mua-ma</code>.';
			}
			echo '<br><b>Tem in dán cạnh thùng tiền vẫn nên có</b>: in ra thì to bao nhiêu cũng '
				. 'được, còn mã trên màn ghế bị giới hạn bởi kích thước ô — nó là đường tiện, '
				. 'không phải đường duy nhất.</p>';
		}
		echo '<p class="description">Ô đó trên màn ghế sẽ <b>luân phiên</b>: một lúc hiện gói như '
			. 'thường, một lúc hiện lời mời mua mã giảm giá. Ô đánh số từ <b>0</b> (trên trái), '
			. '1 (trên phải), 2 (dưới trái), 3 (dưới phải) — anh Thắng muốn ô 100.000đ thì điền số '
			. 'của ô đó.<br><b>Để trống là tắt.</b> Chưa khai giảm giá thì cũng tự tắt: mời khách '
			. 'tới một trang không giảm đồng nào là mất lòng tin ngay lần đầu.<br>'
			. 'Vế quảng cáo <b>vẫn bấm được</b> — chạm vào là mua gói đó như thường.</p></td></tr>';

		echo '<tr><th>Ảnh nền trang ngoài</th><td>'
			. '<input name="anh_nen" value="' . esc_attr( (string) get_option( 'vhg_anh_nen', '' ) )
			. '" class="large-text" placeholder="https://khmatrix.com/wp-content/uploads/…/phong-ghe.jpg" />'
			. '<p class="description">Vào <b>Thư viện</b> tải ảnh phòng ghế lên, mở ảnh ra rồi chép '
			. 'ô <b>URL của tệp</b> dán vào đây. Ảnh được phủ một lớp tối để chữ còn đọc được, nên '
			. 'ảnh sáng hay tối đều dùng được.<br>Để trống thì trang dùng dải màu tự dựng — '
			. '<b>không</b> bị nền trắng.<br>Ảnh nền tải mỗi lần mở trang, mà nhân viên mở trên 4G: '
			. 'nên chọn ảnh <b>dưới 300KB</b>, cỡ chừng 1600px là thừa đẹp.</p></td></tr>';

		$nguon = VHG_Auth::nguon();
		echo '<tr><th>Nguồn người dùng &amp; PIN</th><td>';
		echo '<label><input type="radio" name="nguon" value="chung"' . checked( 'chung', $nguon, false )
			. ' /> Dùng chung với plugin <b>Vận hành chi phí</b> (khuyến nghị)</label><br>';
		echo '<label><input type="radio" name="nguon" value="rieng"' . checked( 'rieng', $nguon, false )
			. ' /> Danh sách riêng của plugin này</label>';
		if ( 'chung' === $nguon && ! VHG_Auth::co_bang_chung() ) {
			echo '<p style="color:#b32d2e"><b>Không thấy bảng của plugin Vận hành chi phí</b> — '
				. 'chưa ai đăng nhập được. Chuyển sang danh sách riêng, hoặc cài plugin đó.</p>';
		}
		echo '</td></tr>';

		$cho = VHG_Auth::vai_tro_vao();
		echo '<tr><th>Vai trò vào được</th><td>';
		foreach ( VHG_Auth::VAI_TRO_TAT_CA as $vt ) {
			echo '<label style="margin-right:14px"><input type="checkbox" name="vai_tro[]" value="'
				. esc_attr( $vt ) . '"' . checked( true, in_array( $vt, $cho, true ), false ) . ' /> '
				. esc_html( $vt ) . '</label>';
		}
		echo '<p class="description">Bỏ hết dấu tích = quay về mặc định, KHÔNG phải khoá sạch — '
			. 'rỗng là không ai vào được, kể cả Admin, và không có đường tự mở lại ngoài cơ sở dữ liệu.'
			. '<br>Cửa hàng trưởng mặc định VÀO ĐƯỢC màn này (khác plugin chấm công): người đứng quầy '
			. 'chính là người cần biết ghế nào đang đứng.'
			. '<br><b>Nhân viên</b> cũng vào được, nhưng chỉ thấy đúng tab <b>Quỹ &amp; nộp tiền</b>: '
			. 'quét QR trên ghế để chốt ca, và nộp tiền mình đang cầm. Không thấy doanh thu, không '
			. 'thấy tiền của người khác, không điều khiển được ghế.</p></td></tr>';

		/* ==================================================================================
		 * AI ĐƯỢC CHỐT DOANH SỐ.
		 *
		 * Anh Thắng 23/08/2026: *"Để cấu hình tài khoản kế toán vào chốt doanh số sau khi nhân
		 * viên thu tiền"*.
		 *
		 * 🔴 QUYỀN RIÊNG, KHÔNG PHẢI QUYỀN QUẢN LÝ. Nhét việc này vào nhóm Quản lý thì muốn kế
		 *    toán nhận tiền là phải cấp cho kế toán quyền Quản lý — tức là cấp luôn quyền huỷ mã
		 *    khách đã trả tiền, gán mã ghế, và tiêu ví của khách mà không cần PIN.
		 * ================================================================================== */
		$cho_chot = VHG_Auth::vai_tro_chot();
		echo '<tr><th>Vai trò chốt doanh số</th><td>';
		foreach ( VHG_Auth::VAI_TRO_TAT_CA as $vt ) {
			$la_admin = ( 'Admin' === $vt );
			echo '<label style="margin-right:14px"><input type="checkbox" name="vai_tro_chot[]" value="'
				. esc_attr( $vt ) . '"' . checked( true, in_array( $vt, $cho_chot, true ), false )
				. ( $la_admin ? ' disabled' : '' ) . ' /> ' . esc_html( $vt )
				. ( $la_admin ? ' <i>(luôn có)</i>' : '' ) . '</label>';
		}
		echo '<p class="description">Đây là người <b>xác nhận đã nhận đủ tiền</b> nhân viên nộp về, '
			. 'và <b>huỷ lượt nộp</b> khi ghi nhầm. Hai việc đó quyết định con số doanh thu tiền mặt '
			. 'cuối cùng.<br>Tích thêm <b>Kế toán cá nhân</b> nếu kế toán là người xuống nhận tiền — '
			. 'họ sẽ chốt được doanh số mà <b>không</b> có quyền huỷ mã, gán ghế hay tiêu ví khách.'
			. '<br>Admin luôn nằm trong danh sách: bỏ sót thì không còn ai chốt được, và không có '
			. 'đường tự mở lại ngoài cơ sở dữ liệu.</p></td></tr>';

		echo '</table><p><button class="button button-primary" name="vhg" value="luu_trang">Lưu</button></p></form>';

		/* ---- Ai vào được, PIN dài mấy số ---- */
		$u = VHG_Auth::users();
		if ( is_wp_error( $u ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $u->get_error_message() ) . '</p></div>';
			$u = array();
		}
		$vao = array();
		foreach ( $u as $x ) { if ( in_array( $x['vaiTro'], $cho, true ) ) { $vao[] = $x; } }
		echo '<h2>Ai vào được (' . count( $vao ) . '/' . count( $u ) . ')</h2>';
		if ( ! $vao ) {
			echo '<div class="notice notice-warning inline"><p><b>Chưa ai đăng nhập được trang ngoài.</b> '
				. 'Tích thêm vai trò ở trên, hoặc thêm người vào danh sách riêng bên dưới.</p></div>';
		} else {
			echo '<table class="widefat striped" style="max-width:620px"><thead><tr><th>Tên</th>'
				. '<th>Vai trò</th><th>Cơ sở</th><th>PIN dài</th></tr></thead><tbody>';
			foreach ( $vao as $x ) {
				/* ⚠️ CHỈ SỐ CHỮ SỐ, không bao giờ in PIN. */
				$dai = '' === $x['pin'] ? '<span style="color:#b32d2e">chưa có</span>'
					: ( preg_match( '/^\d{4,8}$/', $x['pin'] )
						? strlen( $x['pin'] ) . ' số'
						: '<span style="color:#b32d2e">' . strlen( $x['pin'] ) . ' ký tự — không dùng được</span>' );
				echo '<tr><td><b>' . esc_html( $x['ten'] ) . '</b></td><td>' . esc_html( $x['vaiTro'] )
					. '</td><td>' . esc_html( $x['coso'] ) . '</td><td>' . wp_kses_post( $dai ) . '</td></tr>';
			}
			echo '</tbody></table>';
			echo '<p class="description">Bảng này <b>không in PIN</b> — chỉ số chữ số, đủ để biết mình '
				. 'đang gõ thiếu hay thừa.</p>';
		}

		/* ---- Danh sách riêng ---- */
		if ( 'rieng' === $nguon ) {
			$ds = (array) get_option( 'vhg_nguoidung' );
			echo '<h2>Danh sách riêng</h2>';
			if ( $ds ) {
				echo '<table class="widefat striped" style="max-width:620px"><thead><tr><th>Tên</th>'
					. '<th>Vai trò</th><th>Cơ sở</th><th>PIN dài</th><th></th></tr></thead><tbody>';
				foreach ( $ds as $i => $x ) {
					$x = (array) $x;
					echo '<tr><td><b>' . esc_html( isset( $x['ten'] ) ? $x['ten'] : '' ) . '</b></td>'
						. '<td>' . esc_html( isset( $x['vaiTro'] ) ? $x['vaiTro'] : '' ) . '</td>'
						. '<td>' . esc_html( isset( $x['coso'] ) ? $x['coso'] : '' ) . '</td>'
						. '<td>' . strlen( (string) ( isset( $x['pin'] ) ? $x['pin'] : '' ) ) . ' số</td>'
						. '<td><form method="post">' . wp_nonce_field( 'vhg', '_wpnonce', true, false )
						. '<input type="hidden" name="i" value="' . (int) $i . '" />'
						. '<button class="button button-small" name="vhg" value="xoa_nd">Xoá</button>'
						. '</form></td></tr>';
				}
				echo '</tbody></table>';
			}
			echo '<form method="post" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;margin:10px 0">';
			wp_nonce_field( 'vhg' );
			echo '<label>Họ tên<br><input name="ten" required style="width:180px" /></label>';
			echo '<label>PIN (4–8 số)<br><input name="pin" inputmode="numeric" required style="width:110px" /></label>';
			echo '<label>Vai trò<br><select name="vai_tro_moi">';
			foreach ( VHG_Auth::VAI_TRO_TAT_CA as $vt ) {
				echo '<option value="' . esc_attr( $vt ) . '"' . selected( $vt, 'Cửa hàng trưởng', false ) . '>'
					. esc_html( $vt ) . ( in_array( $vt, $cho, true ) ? '' : ' — không vào được' ) . '</option>';
			}
			echo '</select></label>';
			/* 🔴 CHỌN TỪ DANH SÁCH CƠ SỞ THẬT, KHÔNG GÕ TAY.
			 *
			 * Anh Thắng 23/08/2026: *"Để gán nhân viên theo cơ sở"*.
			 *
			 * Gõ tay là "Nha Trang" với "Nha trang" thành hai cơ sở khác nhau, và người thu gõ
			 * lệch một dấu cách thì KHÔNG chốt được ghế nào cả — mà câu báo lỗi lại nói "ghế
			 * thuộc cơ sở khác", nghe như lỗi của cái ghế. So khớp ở `VHG_Quy::truoc_khi_chot()`
			 * là so ĐÚNG NGUYÊN VĂN tên cơ sở, nên chỗ khai phải là chỗ không gõ sai được. */
			echo '<label>Cơ sở<br><select name="coso"><option value="">— cả chuỗi —</option>';
			foreach ( VHG_May::ds_coso() as $c_ ) {
				echo '<option value="' . esc_attr( $c_['ten'] ) . '">' . esc_html( $c_['ten'] ) . '</option>';
			}
			echo '</select></label>';
			echo '<button class="button button-primary" name="vhg" value="them_nd">Thêm người</button></form>';
			echo '<p class="description">Quên PIN thì xoá dòng đó rồi thêm lại — màn này không in PIN ra.'
				. '<br><b>Cơ sở</b> quyết định người đó chốt ca được ở đâu: gán cơ sở thì chỉ chốt được '
				. 'ghế của cơ sở đó; để <i>cả chuỗi</i> thì chốt được mọi ghế. Chốt nhầm ghế ở cơ sở '
				. 'khác không chỉ ghi sai sổ — nó <b>đóng mốc chỉ số</b> của ghế đó, và người thu thật '
				. 'ở đấy hôm sau sẽ thấy quãng bị cắt mất.</p>';
		}
		echo '</div>';
	}

	/**
	 * Thêm một người vào danh sách riêng.
	 *
	 * ⚠️ CÔNG KHAI vì tab Cấu hình trên trang /ghe gọi CHUNG hàm này (xem `VHG_Trang::cau_hinh`).
	 *    Chép ra bản thứ hai là hai bộ luật cho một việc — rồi chỗ này quên chặn PIN trùng, chỗ
	 *    kia quên chặn PIN dễ đoán, và không ai thấy cho tới lúc hai người cùng một PIN.
	 * ⚠️ CHẶN PIN TRÙNG. Hai người cùng PIN thì `login()` khớp người ĐẦU TIÊN trong danh sách —
	 *    người thứ hai gõ đúng PIN của mình mà vào nhầm quyền của người khác, im lặng.
	 */
	public static function them_nguoi_dung( $ten, $pin, $vai_tro, $coso ) {
		$ten = trim( (string) $ten );
		$pin = VHG_Auth::pin_sach( $pin );
		if ( '' === $ten ) { return array( 'ok' => false, 'error' => 'Thiếu họ tên.' ); }
		if ( ! preg_match( '/^\d{4,8}$/', $pin ) ) {
			return array( 'ok' => false, 'error' => 'PIN phải gồm 4–8 chữ số.' );
		}
		/* PIN quá dễ đoán là mở cửa doanh thu 26 cửa hàng cho một lượt thử tay. */
		if ( preg_match( '/^(\d)\1+$/', $pin ) || false !== strpos( '01234567890', $pin )
			|| false !== strpos( '09876543210', $pin ) ) {
			return array( 'ok' => false, 'error' => 'PIN quá dễ đoán — chọn PIN khác.' );
		}
		$ds = (array) get_option( 'vhg_nguoidung' );
		foreach ( $ds as $x ) {
			$x = (array) $x;
			if ( (string) ( isset( $x['pin'] ) ? $x['pin'] : '' ) === $pin ) {
				return array( 'ok' => false, 'error' => 'PIN này đã có người dùng — chọn PIN khác.' );
			}
		}
		$vt = in_array( (string) $vai_tro, VHG_Auth::VAI_TRO_TAT_CA, true ) ? (string) $vai_tro : 'Cửa hàng trưởng';
		$ds[] = array( 'ten' => $ten, 'pin' => $pin, 'vaiTro' => $vt, 'coso' => trim( (string) $coso ) );
		update_option( 'vhg_nguoidung', array_values( $ds ) );
		return array( 'ok' => true, 'thong_bao' => 'Đã thêm ' . $ten . '.' );
	}

	// ======================================================================= NẠP FILE FIRMWARE

	/**
	 * Tab "Nạp file firmware": tải .bin ghế lên web -> các máy TỰ tải, khỏi mang thẻ SD.
	 *   · app .bin   -> cho CON THỢ NẠP (ô "Link firmware GHE") và ghế OTA.
	 *   · merged .bin -> cho TRANG NẠP USB (esp-web-tools).
	 * Xem VHG_Fw: file để trong uploads/vhg-firmware, phục vụ bằng đường uploads (khỏi rewrite).
	 */
	public static function trang_fw() {
		self::gac();
		$bao = array();
		if ( isset( $_POST['vhg'] ) ) {
			check_admin_referer( 'vhg' );
			$viec = sanitize_text_field( wp_unslash( $_POST['vhg'] ) );
			if ( 'fw_nap' === $viec ) {
				$bao = VHG_Fw::xu_ly( $_POST, isset( $_FILES ) ? $_FILES : array() );
			} elseif ( 'fw_xoa' === $viec ) {
				$bao = VHG_Fw::xoa();
			}
		}

		echo '<div class="wrap"><h1>Nạp file firmware ghế</h1>';
		self::ve_bao( $bao );

		echo '<p class="description">Tải tệp <b>.bin</b> firmware ghế lên đây. Máy chủ giữ tệp, các thiết bị '
			. 'TỰ tải về — khỏi mang thẻ SD đi từng nơi. <b>Không kèm bí mật vào repo:</b> tệp nằm ở uploads '
			. 'trên máy chủ, chỉ đưa link cho người trong nhà.</p>';

		$meta = VHG_Fw::meta();
		$ver  = isset( $meta['ver'] ) ? $meta['ver'] : '';
		$u_app = VHG_Fw::url_app();
		$u_mrg = VHG_Fw::url_merged();
		$u_ota = VHG_Fw::url_json_ota();
		$u_usb = VHG_Fw::url_json_usb();

		// ---- Tình trạng hiện tại ----
		echo '<h2>Đang có trên web</h2>';
		if ( '' === $u_app && '' === $u_mrg ) {
			echo '<p><b style="color:#b32d2e">Chưa có firmware nào.</b> Tải lên bên dưới.</p>';
		} else {
			echo '<table class="widefat" style="max-width:900px"><tbody>';
			echo '<tr><th style="width:180px">Phiên bản</th><td><b>' . esc_html( $ver ) . '</b>'
				. ( ! empty( $meta['cap_nhat'] ) ? ' <span class="description">· cập nhật ' . esc_html( self::gio( $meta['cap_nhat'] ) )
					. ( ! empty( $meta['nguoi'] ) ? ' bởi ' . esc_html( $meta['nguoi'] ) : '' ) . '</span>' : '' )
				. '</td></tr>';
			self::fw_dong( 'App .bin (OTA / thợ nạp)', $u_app );
			self::fw_dong( 'Merged .bin (nạp USB)', $u_mrg );
			echo '</tbody></table>';

			echo '<h2>Link để dán vào máy</h2>';
			echo '<table class="widefat" style="max-width:900px"><tbody>';
			self::fw_dong( 'Link firmware GHẾ (dán vào ô "Link firmware GHE" của con thợ nạp)', $u_ota );
			self::fw_dong( 'Manifest USB (dán vào data-manifest thẻ ghế của trang nạp USB)', $u_usb );
			echo '</tbody></table>';
			echo '<p class="description">Con thợ nạp: portal → chọn đích <b>Ghế massage QR</b> → dán link firmware GHẾ ở trên → '
				. '<b>Tải bản mới về thẻ</b> → mang tới gần ghế → Nạp.</p>';
		}

		// ---- Biểu mẫu tải lên ----
		echo '<h2>Tải firmware lên</h2>';
		echo '<form method="post" enctype="multipart/form-data">';
		wp_nonce_field( 'vhg' );
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th scope="row"><label for="fw_ver">Phiên bản</label></th><td>'
			. '<input name="fw_ver" id="fw_ver" type="text" class="regular-text" placeholder="vd: ghe-massage 2026-09-02b" value="'
			. esc_attr( $ver ) . '"><p class="description">Chỉ để hiển thị + ghi vào manifest.</p></td></tr>';
		echo '<tr><th scope="row"><label for="fw_app">App .bin (OTA / thợ nạp)</label></th><td>'
			. '<input name="fw_app" id="fw_app" type="file" accept=".bin"><p class="description">Ảnh <b>APP</b> (Arduino: <code>*.ino.bin</code>) '
			. '— thứ Update.h ghi vào phân vùng app. Dùng cho con thợ nạp và OTA. KHÁC file merged.</p></td></tr>';
		echo '<tr><th scope="row"><label for="fw_merged">Merged .bin (nạp USB)</label></th><td>'
			. '<input name="fw_merged" id="fw_merged" type="file" accept=".bin"><p class="description">Ảnh <b>GỘP</b> ('
			. '<code>esptool merge_bin</code>, offset 0) — dùng cho trang nạp USB (esp-web-tools). Không bắt buộc.</p></td></tr>';
		echo '</tbody></table>';
		echo '<p><button class="button button-primary" name="vhg" value="fw_nap">Tải lên & cập nhật</button></p>';
		echo '</form>';

		if ( '' !== $u_app || '' !== $u_mrg ) {
			echo '<hr><form method="post" onsubmit="return confirm(\'Xoá firmware ghế trên web?\');">';
			wp_nonce_field( 'vhg' );
			echo '<button class="button" name="vhg" value="fw_xoa">Xoá firmware trên web</button></form>';
		}
		echo '</div>';
	}

	// ======================================================================= CHỐT TIỀN (chỉ số ghế)

	/**
	 * Tab "Chốt tiền": lịch sử các lượt chốt tiền theo chỉ số CỘNG DỒN đọc từ ghế (bảng chot_tien).
	 * Máy trạm nối AP ghế -> GET /chotso -> gửi tm/qr -> web trừ kỳ trước. Đây là chỗ XEM lại.
	 */
	public static function trang_chottien() {
		self::gac();
		$ky = self::chon_ky( 'vhg-chottien' );
		$ds = VHG_Quy::chot_tien_ds( $ky, 800 );

		$t_tm = 0; $t_qr = 0;
		foreach ( $ds as $c ) { $t_tm += (int) $c['tm_ky']; $t_qr += (int) $c['qr_ky']; }

		echo '<div class="wrap"><h1>Chốt tiền — chỉ số đọc từ ghế</h1>';
		echo '<p class="description">Máy trạm tới gần ghế, nối AP, đọc thẳng chỉ số <b>tiền mặt</b> + <b>QR</b> '
			. 'cộng dồn của ghế rồi chốt. Cột <b>Kỳ này</b> = chỉ số lần này − lần chốt trước.</p>';

		echo '<p><b>Tổng kỳ này:</b> tiền mặt <b>' . esc_html( self::tien( $t_tm ) ) . '</b> · QR <b>'
			. esc_html( self::tien( $t_qr ) ) . '</b> · cộng <b>' . esc_html( self::tien( $t_tm + $t_qr ) )
			. '</b> · ' . count( $ds ) . ' lượt.</p>';

		if ( ! $ds ) {
			echo '<p>Chưa có lượt chốt tiền nào trong kỳ này.</p></div>';
			return;
		}

		echo '<table class="widefat striped"><thead><tr>'
			. '<th>Lúc</th><th>Ghế</th><th>Cơ sở</th><th>Người</th>'
			. '<th style="text-align:right">Tiền mặt (chỉ số)</th><th style="text-align:right">TM kỳ này</th>'
			. '<th style="text-align:right">QR (chỉ số)</th><th style="text-align:right">QR kỳ này</th>'
			. '<th>Ghi chú</th></tr></thead><tbody>';
		foreach ( $ds as $c ) {
			$ld = ! empty( $c['lan_dau'] );
			echo '<tr>';
			echo '<td>' . esc_html( self::gio( $c['tao_luc'] ) ) . '</td>';
			echo '<td><b>' . esc_html( $c['ma_may'] ) . '</b></td>';
			echo '<td>' . esc_html( $c['coso'] ) . '</td>';
			echo '<td>' . esc_html( $c['nguoi'] ) . '</td>';
			echo '<td style="text-align:right">' . esc_html( self::tien( $c['tm'] ) ) . '</td>';
			echo '<td style="text-align:right">' . ( $ld ? '<span class="description">lần đầu</span>'
				: '<b>' . esc_html( self::tien( $c['tm_ky'] ) ) . '</b>' ) . '</td>';
			echo '<td style="text-align:right">' . esc_html( self::tien( $c['qr'] ) ) . '</td>';
			echo '<td style="text-align:right">' . ( $ld ? '<span class="description">lần đầu</span>'
				: '<b>' . esc_html( self::tien( $c['qr_ky'] ) ) . '</b>' ) . '</td>';
			echo '<td>' . esc_html( $c['ghi_chu'] ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table></div>';
	}

	private static function fw_dong( $nhan, $url ) {
		echo '<tr><th style="width:340px">' . esc_html( $nhan ) . '</th><td>';
		if ( '' === $url ) {
			echo '<span class="description">— chưa có —</span>';
		} else {
			$id = 'fwu_' . wp_rand( 1000, 9999 );
			echo '<input id="' . esc_attr( $id ) . '" type="text" readonly class="large-text code" value="'
				. esc_url( $url ) . '" onfocus="this.select()" style="max-width:640px">'
				. ' <a class="button" href="' . esc_url( $url ) . '" target="_blank" rel="noopener">Mở</a>';
		}
		echo '</td></tr>';
	}

}
