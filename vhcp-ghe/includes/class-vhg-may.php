<?php
/**
 * MÁY (GHẾ): cấu hình, cơ sở, hàng chờ chạy, nhịp sống, lệnh bật/tắt tay.
 *
 * =============================================================================================
 * HAI THỨ RẤT DỄ LẪN, VÀ LẪN LÀ MẤT TIỀN
 * =============================================================================================
 *   `cho`  — KHÁCH ĐÃ TRẢ TIỀN, ghế chưa chạy. Sinh ra từ webhook. Ghế lấy về rồi chạy.
 *   `lenh` — NGƯỜI BẤM TAY trên màn để cho chạy (khách kêu máy không nhận, đền bù…). KHÔNG có
 *            tiền đi kèm.
 * Gộp hai thứ này vào một bảng là cuối tháng không tách được "chạy vì có tiền" với "chạy vì
 * được cho", tức không biết máy nào đang bị cho chạy chùa. Nên tách hẳn, và `lenh` bắt buộc ghi
 * người đặt.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHG_May {

	/** Quá bao lâu không có nhịp thì coi là máy đứt (giây). */
	const HET_SONG = 120;

	// ======================================================================= cơ sở

	public static function ds_coso() {
		return VHG_DB::rows( 'SELECT * FROM ' . VHG_DB::t( 'coso' ) . ' ORDER BY ten ASC' );
	}

	public static function luu_coso( $id, $ten ) {
		global $wpdb;
		$ten = trim( (string) $ten );
		if ( '' === $ten ) { return array( 'ok' => false, 'error' => 'Thiếu tên cơ sở.' ); }
		$bang = VHG_DB::t( 'coso' );
		if ( (int) $id > 0 ) {
			$wpdb->update( $bang, array( 'ten' => $ten ), array( 'id' => (int) $id ) );
			return array( 'ok' => true, 'id' => (int) $id, 'thong_bao' => 'Đã đổi tên cơ sở.' );
		}
		$co = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $bang WHERE ten=%s LIMIT 1", $ten ) );
		if ( $co ) { return array( 'ok' => true, 'id' => (int) $co, 'thong_bao' => 'Cơ sở này đã có.' ); }
		$wpdb->insert( $bang, array( 'ten' => $ten ) );
		return array( 'ok' => true, 'id' => (int) $wpdb->insert_id, 'thong_bao' => 'Đã thêm cơ sở ' . $ten . '.' );
	}

	/**
	 * Xoá cơ sở. Máy đang gán vào đó KHÔNG bị xoá theo — chỉ thành "chưa gán".
	 * ⚠️ Xoá máy theo là mất cấu hình giá/thời lượng/số tài khoản của những máy đang chạy thật,
	 *    chỉ vì người ta gõ nhầm tên một cơ sở rồi muốn xoá đi làm lại.
	 */
	public static function xoa_coso( $id ) {
		global $wpdb;
		$id = (int) $id;
		$so = (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . VHG_DB::t( 'may' ) . ' WHERE coso_id=%d', $id ) );
		$wpdb->update( VHG_DB::t( 'may' ), array( 'coso_id' => 0 ), array( 'coso_id' => $id ) );
		$wpdb->delete( VHG_DB::t( 'coso' ), array( 'id' => $id ) );
		return array( 'ok' => true, 'thong_bao' => 'Đã xoá cơ sở.'
			. ( $so > 0 ? ' ' . $so . ' máy chuyển thành "chưa gán", KHÔNG bị xoá.' : '' ) );
	}

	// ======================================================================= ô quảng cáo mã

	/**
	 * Ô nào trên màn ghế luân phiên hiện lời mời mua mã giảm giá, và mỗi vế bao lâu.
	 *
	 * 🔴 Anh Thắng 23/08/2026: *"nó sẽ hiện đè chỗ mệnh giá 100k — 100k hiện 30s, mã giảm giá
	 *    hiện 30s, luân phiên"*. Tem QR thì dán cứng cạnh thùng tiền, nên ô này chỉ MỜI, không
	 *    vẽ mã.
	 *
	 * ⚠️ MẶC ĐỊNH TẮT. Chưa khai bảng giảm giá mà đã mời khách mua mã là mời họ tới một trang bán
	 *    hàng không giảm đồng nào — mất lòng tin ngay lần đầu, và lần sau họ không quét nữa.
	 * ⚠️ Ô phải NẰM TRONG SỐ Ô ĐANG CÓ. Khai ô số 4 trong khi ghế chỉ hiện 3 gói là một ô quảng
	 *    cáo không bao giờ xuất hiện, mà trên web nhìn vẫn như đã bật.
	 */
	public static function qc_ma() {
		$o = get_option( 'vhg_qc_o' );
		/* ⚠️ `get_option` trả `false` khi CHƯA KHAI, và `(int) false` là 0 — tức là bật ô đầu
		   tiên thay vì tắt. Đúng ngược lại điều mình muốn, và ngược một cách im lặng: cửa hàng
		   chưa khai gì đã tự mời khách mua mã. Phải xét cả `false`. */
		$o = ( false === $o || null === $o || '' === $o ) ? -1 : (int) $o;
		$giay = (int) get_option( 'vhg_qc_giay', 30 );
		/* 5..300: dưới 5 giây là chữ nhấp nháy không ai đọc kịp; trên 5 phút thì một trong hai
		   vế coi như không tồn tại. */
		$giay = max( 5, min( 300, $giay ) );
		$n = count( self::menh_gia() );
		if ( $o < 0 || $o >= $n ) { return array( 'o' => -1, 'giay' => $giay, 'giam' => 0 ); }
		/* Giảm cao nhất trong bảng — đó là con số đáng đưa lên màn. Không có giảm nào thì tắt. */
		$giam = 0;
		foreach ( VHG_Ma::bang_giam() as $pt ) { if ( (int) $pt > $giam ) { $giam = (int) $pt; } }
		if ( $giam <= 0 ) { return array( 'o' => -1, 'giay' => $giay, 'giam' => 0 ); }
		return array( 'o' => $o, 'giay' => $giay, 'giam' => $giam );
	}

	// ======================================================================= nhật ký bật từ xa

	/**
	 * NHẬT KÝ BẬT GHẾ TỪ XA — để đối chiếu sau này.
	 *
	 * 🔴 Vì sao đây là bảng phải có, chứ không phải "tính năng thêm cho đẹp":
	 *    Mỗi lần bấm Bật là CHO KHÔNG một lượt massage. Ghế chạy, điện tốn, khách được phục vụ,
	 *    mà sổ doanh thu không có đồng nào. Cuối tháng nhìn "ghế AMTP01 chạy 180 lượt, thu 140
	 *    lượt" thì 40 lượt kia phải giải thích được — bằng con số, không bằng trí nhớ.
	 *
	 * ⚠️ Đếm cả lệnh CHƯA GỬI XUỐNG GHẾ. `gui_luc` rỗng nghĩa là ghế chưa lấy (đang mất mạng),
	 *    nhưng người bấm thì đã bấm rồi và ghế sẽ chạy khi lên mạng. Lọc bỏ nó đi là nhật ký nói
	 *    ít hơn sự thật đúng vào những ngày mạng chập chờn — tức là đúng những ngày cần tra nhất.
	 * ⚠️ CHỈ đếm `viec='on'`. Lệnh `off` không cho ai cái gì cả; gộp vào là thổi phồng con số
	 *    "cho không" bằng những lần người ta tắt ghế đi.
	 */
	/* Tên là `ds_lenh_bat` chứ không `ds_lenh`: đã có sẵn `ds_lenh()` liệt kê MỌI lệnh cho màn
	   gỡ rối. Hai hàm hai việc, và trùng tên thì PHP báo lỗi ngay — nhưng nếu chỉ khác tham số
	   thì người đọc sau này mới là người phải đoán. */
	public static function ds_lenh_bat( $ky = 'month', $gioi_han = 500 ) {
		global $wpdb;
		$t  = VHG_DB::t( 'lenh' );
		$tu = VHG_Thu::dau_ky( $ky );
		$sql = "SELECT * FROM $t WHERE viec='on'";
		if ( '' !== $tu ) { $sql = $wpdb->prepare( $sql . ' AND tao_luc >= %s', $tu ); }
		$sql .= ' ORDER BY tao_luc DESC, id DESC LIMIT ' . (int) $gioi_han;
		return VHG_DB::rows( $sql );
	}

	/**
	 * Gộp theo NGÀY: mỗi ngày bật mấy lần, tổng bao nhiêu phút.
	 *
	 * Gộp bằng SQL chứ không kéo hết về rồi cộng trong PHP: bảng này chỉ có thêm, không bao giờ
	 * bớt, nên "cả năm" của 26 ghế là con số lớn dần mãi — và màn đối soát mở suốt ngày trên 4G.
	 */
	public static function tong_lenh_ngay( $ky = 'month' ) {
		global $wpdb;
		$t  = VHG_DB::t( 'lenh' );
		$tu = VHG_Thu::dau_ky( $ky );
		$sql = "SELECT DATE(tao_luc) AS ngay, COUNT(*) AS so_lan, SUM(phut) AS tong_phut"
			. " FROM $t WHERE viec='on'";
		if ( '' !== $tu ) { $sql = $wpdb->prepare( $sql . ' AND tao_luc >= %s', $tu ); }
		$sql .= ' GROUP BY DATE(tao_luc) ORDER BY ngay DESC LIMIT 400';
		$ra = array();
		foreach ( VHG_DB::rows( $sql ) as $r ) {
			$ra[] = array( 'ngay' => (string) $r['ngay'], 'so_lan' => (int) $r['so_lan'],
				'tong_phut' => (int) $r['tong_phut'] );
		}
		return $ra;
	}

	/**
	 * Gộp theo GHẾ: ghế nào đã kích hoạt tay, mấy lần, tổng bao nhiêu phút, lần gần nhất khi nào.
	 *
	 * Đây là câu hỏi đầu tiên lúc đối chiếu: "ghế nào hay được bật không?". Một ghế lên đầu bảng
	 * tháng này qua tháng khác thì hoặc nó hỏng thật, hoặc có người đang quen tay — hai chuyện
	 * đều đáng biết, và không chuyện nào lộ ra từ bảng gộp theo ngày.
	 */
	public static function tong_lenh_may( $ky = 'month' ) {
		global $wpdb;
		$t  = VHG_DB::t( 'lenh' );
		$tu = VHG_Thu::dau_ky( $ky );
		$sql = "SELECT ma_may, COUNT(*) AS so_lan, SUM(phut) AS tong_phut, MAX(tao_luc) AS lan_cuoi"
			. " FROM $t WHERE viec='on'";
		if ( '' !== $tu ) { $sql = $wpdb->prepare( $sql . ' AND tao_luc >= %s', $tu ); }
		$sql .= ' GROUP BY ma_may ORDER BY so_lan DESC, tong_phut DESC LIMIT 200';
		$ra  = array();
		$may = self::ds_may_theo_ma();
		foreach ( VHG_DB::rows( $sql ) as $r ) {
			$m = (string) $r['ma_may'];
			$ra[] = array( 'ma' => $m,
				'coso' => isset( $may[ $m ] ) ? (string) $may[ $m ]['coso_ten'] : '',
				'so_lan' => (int) $r['so_lan'], 'tong_phut' => (int) $r['tong_phut'],
				'lan_cuoi' => (string) $r['lan_cuoi'] );
		}
		return $ra;
	}

	/** Tổng gọn của một kỳ: bao nhiêu lần, bao nhiêu phút, trên mấy ghế. */
	public static function tong_lenh( $ky = 'month' ) {
		global $wpdb;
		$t  = VHG_DB::t( 'lenh' );
		$tu = VHG_Thu::dau_ky( $ky );
		$sql = "SELECT COUNT(*) AS so_lan, SUM(phut) AS tong_phut, COUNT(DISTINCT ma_may) AS so_ghe"
			. " FROM $t WHERE viec='on'";
		if ( '' !== $tu ) { $sql = $wpdb->prepare( $sql . ' AND tao_luc >= %s', $tu ); }
		$r = $wpdb->get_row( $sql, ARRAY_A );
		return array( 'so_lan' => (int) ( $r ? $r['so_lan'] : 0 ),
			'tong_phut' => (int) ( $r ? $r['tong_phut'] : 0 ),
			'so_ghe' => (int) ( $r ? $r['so_ghe'] : 0 ) );
	}

	// ======================================================================= cục nhận tiền

	/**
	 * Các mã lỗi cục nhận tiền mà ghế được phép khai. Kèm câu người đứng quầy đọc là hiểu phải
	 * làm gì — "lỗi E03" thì ai cũng chịu.
	 *
	 * ⚠️ CHỈ NHẬN MÃ TRONG DANH SÁCH NÀY. Ghế nói gì cũng ghi vào cột là mở đường cho một chuỗi
	 *    lạ chui thẳng vào màn quản trị; mà cổng máy chỉ có một khoá chung, ai biết khoá là gửi
	 *    được. Mã lạ -> coi như không có lỗi, và điều đó AN TOÀN: bỏ sót một cảnh báo còn hơn
	 *    tin một cảnh báo bịa.
	 */
	const LOI_TIEN = array(
		/* --- Cổng tiền SERIAL (bản mới, cong_tien.h) --------------------------------------- */
		'ket'   => 'Tờ tiền vào khay tạm mà KHÔNG nuốt được — cục nhận báo có tiền nhưng quá lâu '
			. 'không xong (lúc ghế đang rảnh). Cục nhận kẹt/lỗi, hoặc ghế không phản hồi. Rút điện '
			. 'cục nhận 10 giây rồi cắm lại; còn nữa thì kiểm dây bus tiền và mạch chia mức về ghế.',
		'qr'    => 'Bơm QR mà ghế KHÔNG ăn — ghế không đáp xác nhận. Kiểm dây bus tiền (IO27 sang '
			. 'ghế), rơ-le bypass có đang ở chế độ ESP không, và mạch chia mức 3,3→5V.',
		'ghekhongchay' => 'ĐÃ TRẢ TIỀN MÀ GHẾ KHÔNG CHẠY — khách đã trả tiền nhưng motor ghế không '
			. 'hoạt động (chân báo-chạy của bo ghế vẫn ở mức tắt), nên đồng hồ QR ĐANG CHỜ, CHƯA trừ '
			. 'giờ. Nguyên nhân: ghế lỗi/kẹt cơ/mất nguồn động cơ, hoặc lệnh chưa xuống (mạng chập '
			. 'chờn). RA KIỂM NGAY kẻo khách trả tiền mà không được dùng.',
		'ghedungdotngot' => 'GHẾ DỪNG ĐỘT NGỘT giữa chừng — đang massage mà ghế ngừng chạy trong khi '
			. 'khách CÒN giờ. Ghế dừng quá 30 giây không chạy lại nên phiên ĐÃ TỰ KẾT THÚC (tắt QR). '
			. 'Ghế kẹt/lỗi/mất nguồn động cơ, hoặc có người bấm dừng. RA KIỂM; nếu khách chưa dùng '
			. 'hết giờ thì xử lý bù cho khách.',
		/* --- Cục nhận tiền ICT tự báo bệnh (mã 0x2X đo thật trên bus) ---------------------- */
		'ictgiay' => 'Cục nhận tiền báo: có tờ NHÉT VÀO nhưng KHÔNG PHẢI TIỀN (giấy lạ/tờ giả/rách '
			. 'quá). Rút tờ đó ra. Nếu tờ tiền thật vẫn báo vậy thì lau cảm biến hoặc kiểm cấu hình mệnh giá.',
		'ictcbd'  => 'Cục nhận tiền: KẸT CẢM BIẾN LỐI RA DƯỚI — có dị vật/tờ tiền chắn cảm biến phía '
			. 'dưới. Mở cục nhận, lấy dị vật, lau cảm biến.',
		'ictlt'   => 'Cục nhận tiền: KẸT ĐẦU RA LỐI TRÊN — tờ tiền/dị vật kẹt ở đường ra phía trên '
			. '(chỗ đẩy vào thùng). Mở lấy ra, kiểm đường tiền.',
		'ictnc'   => 'Cục nhận tiền: KẸT TỜ TIỀN NỬA CHỪNG — tờ tiền mắc giữa đường, chưa vào chưa ra. '
			. 'Rút tờ ra, kiểm con lăn/băng tải.',
		'ictla'   => 'Cục nhận tiền BÁO LỖI (mã chưa rõ tên) — mở xem có kẹt/dị vật/đầy thùng không, '
			. 'và xem log Serial của ghế để biết mã cụ thể rồi báo kỹ thuật.',
		/* --- Đường XUNG cũ (giữ lại phòng bo còn chạy pulse; firmware serial không sinh nữa) - */
		'lech'  => 'Đếm xung lệch — một đợt tiền ra số không chia hết cho 10.000đ, tức là mất '
			. 'hoặc thừa xung. TIỀN ĐANG ĐẾM SAI: đối chiếu két với sổ ngay.',
		'khoa'  => 'Đã khoá mà vẫn nhận tiền — dây INHIBIT tuột hoặc sai cực. Tiền vào két mà ghế '
			. 'không tính giờ, và trên sổ sẽ không có dòng nào.',
		'nhieu' => 'Nhiễu trên đường xung — nhiều cạnh giả bị lọc trong một đợt. Chưa chắc đã sai '
			. 'tiền, nhưng là dấu hiệu dây tín hiệu đi sát dây nguồn hoặc mát kém.',
	);

	/** Bản tiếng Anh, khai cùng chỗ với bản tiếng Việt — hai tệp là hai lần quên sửa một bên. */
	const LOI_TIEN_EN = array(
		'ket'   => 'A bill sat in escrow but was NOT stacked — the acceptor reported a bill but never '
			. 'finished (while the chair was idle). The acceptor is jammed/faulty, or the chair did '
			. 'not respond. Unplug the acceptor for 10s and plug it back in; if it persists, check '
			. 'the money-bus wiring and the level shifter to the chair.',
		'qr'    => 'QR credit was injected but the chair did NOT accept it (no acknowledgement). Check '
			. 'the money bus (IO27 to the chair), that the bypass relay is in ESP mode, and the '
			. '3.3->5V level shifter.',
		'ghekhongchay' => 'PAID AND TIMING BUT THE CHAIR IS NOT RUNNING — the customer paid, the timer '
			. 'is counting, but the chair motor is not running (the chair board\'s run pin is still low). '
			. 'The chair may be faulty, mechanically jammed, or lost motor power. CHECK NOW so the '
			. 'customer is not charged without service.',
		'ghedungdotngot' => 'CHAIR STOPPED MID-SESSION — the chair stopped while the customer still had '
			. 'time left. It stayed stopped for over 30s without resuming, so the session AUTO-ENDED (QR '
			. 'off). The chair is jammed/faulty/lost motor power, or someone pressed stop. CHECK; if the '
			. 'customer had unused time, compensate them.',
		'ictgiay' => 'Bill acceptor: a NON-CASH item was inserted (paper/fake/too torn). Remove it. If '
			. 'a real bill still triggers this, clean the sensor or check the denomination config.',
		'ictcbd'  => 'Bill acceptor: LOWER EXIT SENSOR JAMMED — debris/a bill is blocking the lower '
			. 'sensor. Open the acceptor, remove it, clean the sensor.',
		'ictlt'   => 'Bill acceptor: UPPER EXIT JAM — a bill/debris is stuck at the upper exit (into the '
			. 'stacker). Open and clear it.',
		'ictnc'   => 'Bill acceptor: BILL JAMMED MIDWAY — a bill is stuck in the path. Remove it, check '
			. 'the rollers/belt.',
		'ictla'   => 'Bill acceptor reported an error (code not yet named) — open and check for a jam/'
			. 'debris/full stacker, and read the chair Serial log for the exact code.',
		'lech'  => 'Pulse count is off — one batch came to an amount not divisible by 10,000đ, so a '
			. 'pulse was lost or added. MONEY IS BEING MISCOUNTED: reconcile the cash box now.',
		'khoa'  => 'Credited while inhibited — the INHIBIT wire is loose or has the wrong polarity. '
			. 'Cash reaches the box but the chair does not credit it, and no entry is recorded.',
		'nhieu' => 'Noise on the pulse line — many false edges filtered out in one batch. It may not '
			. 'have miscounted yet, but the signal wire is running too close to power, or the '
			. 'ground is poor.',
	);

	public static function ma_loi_tien( $v ) {
		$s = strtolower( trim( (string) $v ) );
		return isset( self::LOI_TIEN[ $s ] ) ? $s : '';
	}

	public static function loi_tien_chu( $ma, $nn = 'vi' ) {
		$ma = self::ma_loi_tien( $ma );
		if ( '' === $ma ) { return ''; }
		return 'en' === $nn ? self::LOI_TIEN_EN[ $ma ] : self::LOI_TIEN[ $ma ];
	}

	/**
	 * Ghế khai TUỔI (bao nhiêu giây trước) -> máy chủ đổi ra giờ tuyệt đối của mình.
	 * `-1` (hoặc số âm) = chưa từng xảy ra, trả về null chứ không phải "vừa xong".
	 */
	public static function luc_tu_tuoi( $giay ) {
		$g = (int) $giay;
		if ( $g < 0 ) { return null; }
		/* Chặn trên 1 năm: ghế chạy lâu thì millis() rất lớn, mà một con số vượt tầm DATETIME
		   làm hỏng cả hàng nhịp — mất luôn thứ quan trọng hơn vì một cái mốc chỉ để tham khảo. */
		if ( $g > 31536000 ) { return null; }
		return gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - $g );
	}

	// ======================================================================= máy

	public static function ds_may() {
		$may  = VHG_DB::t( 'may' );
		$coso = VHG_DB::t( 'coso' );
		$nhip = VHG_DB::t( 'nhip' );
		$ds = VHG_DB::rows(
			"SELECT m.*, c.ten AS coso_ten, n.trang_thai, n.nguon AS nhip_nguon, n.con_lai,"
			. " n.fw, n.ip, n.nd_tien_to, n.tre_ms, n.tm_loi, n.tm_cuoi, n.tm_lan,"
			. " n.tm_luc, n.tm_to, n.khoa, n.kt, n.luc AS nhip_luc FROM $may m"
			. " LEFT JOIN $coso c ON c.id = m.coso_id"
			. " LEFT JOIN $nhip n ON n.ma_may = m.ma"
			. ' ORDER BY c.ten ASC, m.ma ASC' );
		$gio = current_time( 'timestamp' );
		foreach ( $ds as $i => $x ) {
			$ds[ $i ]['coso_ten'] = (string) $x['coso_ten'];
			$tt = self::tinh_trang_nhip( isset( $x['nhip_luc'] ) ? $x['nhip_luc'] : '', $gio );
			/* ==================================================================================
			 * SỐ GIÂY CÒN LẠI PHẢI TRỪ ĐI TUỔI CỦA CHÍNH CON SỐ ĐÓ.
			 *
			 * 🔴 Anh Thắng 22/08/2026: *"bấm thử điều khiển ghế thì lệch 12s — thời gian máy QR
			 *    nhanh hơn 11s"*. Không phải cố ý chừa thời gian cho khách lên ghế; đó là tuổi
			 *    của dữ liệu.
			 *
			 *    Ghế gửi nhịp 30 giây một lần. Nó nói "còn 300 giây" lúc 21:46:00. Web hỏi lúc
			 *    21:46:11 và nhận đúng con số 300 đó — nhưng ghế đã chạy thêm 11 giây rồi. Web
			 *    tự trừ mỗi giây từ 300, nên nó chậm hơn ghế đúng 11 giây, mãi mãi, cho tới lượt
			 *    nhịp sau. Trung bình lệch nửa chu kỳ nhịp = 15 giây.
			 *
			 * Máy chủ biết nhịp đó tới lúc nào, nên trừ được. Sửa ở ĐÂY chứ không bắt ghế gửi
			 * nhịp dày hơn: ghế chạy 4G, mỗi lượt nhịp là tiền, và 26 ghế × mỗi 5 giây là một
			 * khoản không nhỏ cho một con số chỉ để nhìn.
			 *
			 * ⚠️ Chỉ trừ khi ghế ĐANG CHẠY. Ghế rảnh thì `con_lai` vốn là 0; ghế mất kết nối thì
			 *    con số nào cũng vô nghĩa — trừ tiếp chỉ tạo ra một đồng hồ chạy lùi trông như
			 *    thật, mà thật ra không ai biết ghế còn chạy hay không.
			 * ================================================================================== */
			$con = (int) ( isset( $x['con_lai'] ) ? $x['con_lai'] : 0 );
			if ( $tt['song'] && 'running' === (string) $x['trang_thai'] && null !== $tt['giay'] ) {
				/* Trừ HAI phần, hai nguồn khác nhau:
				   · tuổi của nhịp: từ lúc máy chủ nhận tới bây giờ — máy chủ tự biết.
				   · nửa quãng đi: ghế tính con số TRƯỚC khi gọi, dấu giờ đóng LÚC NHẬN. Máy chủ
				     không thấy quãng này, nên ghế phải tự khai (`tre_ms` = lượt trước mất bao lâu).
				     Nửa chứ không trọn: `tre_ms` là cả đi lẫn về, chỉ chiều đi mới nằm giữa hai mốc. */
				$con = max( 0, $con - (int) $tt['giay']
					- (int) round( (int) ( isset( $x['tre_ms'] ) ? $x['tre_ms'] : 0 ) / 2000 ) );
			}
			$ds[ $i ]['con_lai']      = $con;
			$ds[ $i ]['nhip_giay']    = $tt['giay'];
			$ds[ $i ]['con_song']     = $tt['song'];
			$ds[ $i ]['chua_bao_gio'] = $tt['chua_bao_gio'];
			$ds[ $i ]['nhip_chu']     = $tt['chu'];
			$ds[ $i ]['cho']          = self::so_cho( $x['ma'] );
			$ds[ $i ]['khoa']         = ! empty( $x['khoa'] ) ? 1 : 0;
			$ds[ $i ]['kt']           = ! empty( $x['kt'] ) ? 1 : 0;
		}
		return $ds;
	}

	/** Bản đồ mã máy -> thông tin, để tra nhanh khi tổng hợp doanh thu. */
	public static function ds_may_theo_ma() {
		$ra = array();
		foreach ( self::ds_may() as $m ) {
			$ra[ $m['ma'] ] = $m;
			/* Tra được cả bằng TÊN KHAI: giao dịch của Tingo mang tên "AMTP 03" chứ không mang
			   mã ghế, nên không có khoá này thì cơ sở của chúng luôn là "chưa gán". */
			if ( '' !== (string) $m['ten_khai'] ) {
				$ra[ VHG_Doc::chuan_ten( $m['ten_khai'] ) ] = $m;
			}
		}
		return $ra;
	}

	/**
	 * Ghế này đang thế nào, NÓI BẰNG CÂU NGƯỜI ĐỌC ĐƯỢC.
	 *
	 * 🔴 Anh Thắng 22/08/2026: *"đã add, nhưng máy chưa nhận"*. Màn hình lúc đó chỉ nói "Mất kết
	 *    nối" — đúng, nhưng vô dụng, vì nó gộp BA ca đi sửa ở ba nơi khác hẳn:
	 *      · CHƯA BAO GIỜ gửi nhịp  -> ghế chưa nạp firmware mới, hoặc nạp rồi mà sai địa chỉ
	 *                                  web / sai khoá. Đi kiểm cổng USB và secrets.h.
	 *      · Gửi rồi, vừa mới im    -> mạng chập. Đợi một lượt nhịp (~30 giây) rồi xem lại.
	 *      · Gửi rồi, im đã lâu     -> ghế mất điện, rớt 4G, hoặc treo. Đi tới nơi.
	 *    Ba câu đó khác nhau ở chỗ NGƯỜI ĐỌC PHẢI LÀM GÌ TIẾP. Gộp làm một là bắt người ta đoán.
	 *
	 * @return array [ 'song' => bool, 'chua_bao_gio' => bool, 'giay' => int|null, 'chu' => string ]
	 */
	public static function tinh_trang_nhip( $luc, $bay_gio = null ) {
		$luc = trim( (string) $luc );
		if ( null === $bay_gio ) { $bay_gio = current_time( 'timestamp' ); }
		if ( '' === $luc ) {
			return array( 'song' => false, 'chua_bao_gio' => true, 'giay' => null,
				'chu' => 'chưa bao giờ gửi nhịp' );
		}
		$t = strtotime( $luc );
		if ( ! $t ) {
			return array( 'song' => false, 'chua_bao_gio' => true, 'giay' => null,
				'chu' => 'chưa bao giờ gửi nhịp' );
		}
		$giay = max( 0, (int) ( $bay_gio - $t ) );
		if ( $giay <= self::HET_SONG ) {
			return array( 'song' => true, 'chua_bao_gio' => false, 'giay' => $giay,
				'chu' => 'đang sống · ' . self::truoc_day( $giay ) );
		}
		return array( 'song' => false, 'chua_bao_gio' => false, 'giay' => $giay,
			'chu' => 'im từ ' . self::truoc_day( $giay ) );
	}

	/** "12 giây trước" / "5 phút trước" / "3 giờ trước" / "2 ngày trước". */
	public static function truoc_day( $giay ) {
		$giay = max( 0, (int) $giay );
		if ( $giay < 60 )    { return $giay . ' giây trước'; }
		if ( $giay < 3600 )  { return (int) floor( $giay / 60 ) . ' phút trước'; }
		if ( $giay < 86400 ) { return (int) floor( $giay / 3600 ) . ' giờ trước'; }
		return (int) floor( $giay / 86400 ) . ' ngày trước';
	}

	public static function con_song( $luc, $bay_gio = null ) {
		if ( '' === trim( (string) $luc ) ) { return false; }
		$t = strtotime( $luc );
		if ( ! $t ) { return false; }
		if ( null === $bay_gio ) { $bay_gio = current_time( 'timestamp' ); }
		return ( $bay_gio - $t ) <= self::HET_SONG;
	}

	/**
	 * MỆNH GIÁ khách bấm trên màn ghế. Khai ở WEB, không nạp cứng vào firmware.
	 *
	 * 🔴 Anh Thắng ngày 22/08/2026: *"có nhiều mệnh giá quét trên máy qr để chọn mà"*. Đúng —
	 *    ghế có bốn nút. Nhưng bản đầu bốn con số đó nằm CỨNG trong firmware, nên đổi giá là
	 *    phải mang USB đi 26 cửa hàng. Ghế lại không có OTA, nên đó là chuyến đi thật.
	 *
	 * ⚠️ `gia`/`phut` KHÔNG phải một mệnh giá — chúng là TỈ LỆ QUY ĐỔI. Ghế tính
	 *    `phút = tiền × phut / gia`, nên 10.000đ=6′ thì bấm 50.000đ ra 30 phút. Nhãn cũ ghi
	 *    "Giá một lượt" là sai, và làm người đọc tưởng ghế chỉ có một mệnh giá.
	 */
	const SO_O_MAN_GHE = 4;   // màn ghế 320×240 vẽ được đúng bốn ô

	/* Bảng giá anh Thắng dựng (ảnh 22/08/2026). Đúng tỉ lệ 50.000đ = 15 phút cho cả bốn gói. */
	const MENH_GIA_MAC_DINH = array(
		array( 'tien' => 50000,  'ten' => 'Gói cơ bản',      'phut' => 0, 'mo_ta' => 'Khởi động & thư giãn nhẹ', 'vip' => 0 ),
		array( 'tien' => 100000, 'ten' => 'Gói phổ biến',    'phut' => 0, 'mo_ta' => 'Sâu & phục hồi',           'vip' => 0 ),
		array( 'tien' => 150000, 'ten' => 'Gói chuyên sâu',  'phut' => 0, 'mo_ta' => 'Trị liệu & giảm đau',      'vip' => 0 ),
		array( 'tien' => 200000, 'ten' => 'Gói thượng hạng', 'phut' => 0, 'mo_ta' => 'Đẳng cấp & quà tặng',      'vip' => 1 ),
	);

	/**
	 * Bốn gói trên màn ghế: [ ['tien','ten','phut'], … ]
	 *
	 * `phut = 0` nghĩa là TÍNH THEO TỈ LỆ QUY ĐỔI của ghế. Để 0 là cách đúng trong hầu hết
	 * trường hợp: đổi tỉ lệ một lần thì cả bốn gói theo, không phải sửa bốn ô rồi lệch một ô.
	 * Chỉ khai số phút cụ thể khi gói đó CỐ Ý không theo tỉ lệ (gói khuyến mãi, gói kèm quà).
	 */
	public static function menh_gia() {
		$ds = get_option( 'vhg_menh_gia' );
		if ( ! is_array( $ds ) ) { return self::MENH_GIA_MAC_DINH; }
		$ra   = array();
		$thay = array();
		foreach ( $ds as $v ) {
			/* Bản 1.3.0 lưu một mảng SỐ trơn. Đọc được cả hai dạng — nếu không thì nâng cấp
			   plugin là bốn gói đang chạy biến mất và ghế về bộ mặc định, âm thầm. */
			$hang = is_array( $v ) ? $v : array( 'tien' => $v, 'ten' => '', 'phut' => 0 );
			$tien = (int) ( isset( $hang['tien'] ) ? $hang['tien'] : 0 );
			if ( $tien < 1000 || isset( $thay[ $tien ] ) ) { continue; }
			$thay[ $tien ] = 1;
			$ra[] = array(
				'tien'  => $tien,
				'ten'   => trim( (string) ( isset( $hang['ten'] ) ? $hang['ten'] : '' ) ),
				'phut'  => max( 0, (int) ( isset( $hang['phut'] ) ? $hang['phut'] : 0 ) ),
				'mo_ta' => trim( (string) ( isset( $hang['mo_ta'] ) ? $hang['mo_ta'] : '' ) ),
				'vip'   => empty( $hang['vip'] ) ? 0 : 1,
			);
		}
		usort( $ra, function ( $a, $b ) { return $a['tien'] - $b['tien']; } );
		/* Rỗng thì về mặc định, KHÔNG để rỗng: ghế không còn nút nào để bấm, tức là đường QR
		   chết hẳn ở 26 cửa hàng mà máy chủ vẫn báo mọi thứ bình thường. */
		if ( ! $ra ) { return self::MENH_GIA_MAC_DINH; }
		/* Màn ghế chỉ có BỐN ô. Nhiều hơn là những ô sau không vẽ ra được — cắt ở đây để cái
		   người ta thấy trên web đúng bằng cái ghế hiện. */
		return array_slice( $ra, 0, self::SO_O_MAN_GHE );
	}

	/** Số phút thực của một gói với một ghế: khai cứng nếu có, không thì theo tỉ lệ quy đổi. */
	public static function phut_goi( $goi, $gia_quy_doi, $phut_quy_doi ) {
		$goi = (array) $goi;
		if ( ! empty( $goi['phut'] ) ) { return (int) $goi['phut']; }
		$gia = (int) $gia_quy_doi;
		if ( $gia <= 0 ) { return 0; }
		return (int) floor( (int) $goi['tien'] * (int) $phut_quy_doi / $gia );
	}

	/* ============================================================================================
	 * 🔴 CHỮ DÀI QUÁ Ô THÌ TRÀN RA NGOÀI, VÀ PHẦN TRÀN KHÔNG AI XOÁ ĐƯỢC.
	 *
	 * Ô gói trên màn ghế rộng 150px, font 1 ăn 6px mỗi ký tự -> vừa đúng 25 ký tự. Cắt ở 24 để
	 * chừa mép. Trước đây cắt ở 30 (tên) và 40 (mô tả) — tức là cho phép chuỗi 180px và 240px
	 * nằm trong ô 150px.
	 *
	 * Vì sao tràn lại KHÔNG TỰ HẾT: ô luân phiên vẽ lại bằng cách tô đúng hình chữ nhật 150×84
	 * của nó. Phần chữ đã vẽ RA NGOÀI hình đó thì nét tô không chạm tới, nên nó nằm lại trên màn
	 * cho tới lần vẽ toàn màn kế tiếp. Anh Thắng chụp được đúng kiểu này 23/08/2026: một dòng chữ
	 * của vế quảng cáo còn nằm đè lên dòng mô tả và dải chữ dưới cùng khi ô đã quay về mệnh giá.
	 *
	 * Chưa lộ ra chỉ vì tên đang gõ ngắn. Gõ một cái tên 30 ký tự là dính ngay.
	 * ============================================================================================ */
	const CHU_VUA_O = 24;

	public static function luu_menh_gia( $ds ) {
		$ra   = array();
		$thay = array();
		foreach ( (array) $ds as $v ) {
			$v    = (array) $v;
			$tien = (int) preg_replace( '/\D+/', '', (string) ( isset( $v['tien'] ) ? $v['tien'] : '' ) );
			if ( $tien < 1000 ) { continue; }
			if ( isset( $thay[ $tien ] ) ) {
				return array( 'ok' => false, 'error' => 'Hai gói cùng số tiền ' . number_format( $tien, 0, ',', '.' )
					. 'đ. Khách bấm hai nút giống hệt nhau thì không biết mình chọn gì.' );
			}
			$thay[ $tien ] = 1;
			$ra[] = array(
				'tien'  => $tien,
				'ten'   => mb_substr( trim( (string) ( isset( $v['ten'] ) ? $v['ten'] : '' ) ), 0, self::CHU_VUA_O ),
				'phut'  => max( 0, min( 240, (int) ( isset( $v['phut'] ) ? $v['phut'] : 0 ) ) ),
				'mo_ta' => mb_substr( trim( (string) ( isset( $v['mo_ta'] ) ? $v['mo_ta'] : '' ) ), 0, self::CHU_VUA_O ),
				'vip'   => empty( $v['vip'] ) ? 0 : 1,
			);
		}
		usort( $ra, function ( $a, $b ) { return $a['tien'] - $b['tien']; } );
		if ( ! $ra ) { return array( 'ok' => false, 'error' => 'Phải có ít nhất một gói từ 1.000đ.' ); }
		if ( count( $ra ) > self::SO_O_MAN_GHE ) {
			return array( 'ok' => false, 'error' => 'Màn ghế chỉ có ' . self::SO_O_MAN_GHE
				. ' ô — khai tối đa ' . self::SO_O_MAN_GHE . ' gói.' );
		}
		update_option( 'vhg_menh_gia', $ra );
		return array( 'ok' => true, 'thong_bao' => 'Đã lưu ' . count( $ra ) . ' gói. '
			. 'Ghế lấy về ở lượt nhịp kế tiếp (~30 giây).' );
	}

	/**
	 * Bốn gói ở dạng ghế đọc được: khoá ngắn, và TÊN ĐÃ BỎ DẤU.
	 *
	 * 🔴 Font của màn ghế (TFT_eSPI) KHÔNG vẽ được dấu tiếng Việt — "Gói phổ biến" hiện ra
	 *    thành một hàng ô vuông. Bỏ dấu ở MÁY CHỦ chứ không ở ghế: ghế không có OTA, nên mọi
	 *    thứ sửa được bằng máy chủ thì phải sửa ở máy chủ.
	 *
	 * ⚠️ Khoá một chữ (`t`,`n`,`p`) vì gói nhịp đi qua 4G và ghế giải mã trong bộ đệm cố định.
	 *    Tên khoá dài là tốn đúng chỗ mà một cái tên gói cần.
	 */
	public static function menh_gia_cho_ghe( $gia_quy_doi, $phut_quy_doi ) {
		$ra = array();
		foreach ( self::menh_gia() as $g ) {
			$ra[] = array(
				't' => (int) $g['tien'],
				'n' => self::bo_dau_hoa( $g['ten'], 16 ),
				'p' => self::phut_goi( $g, $gia_quy_doi, $phut_quy_doi ),
				/* Mô tả dài hơn tên: nó nằm trên một dòng riêng dưới đáy thẻ, font nhỏ nhất.
				   24 ký tự là bề ngang một thẻ 148px ở font 1. */
				'm' => self::bo_dau_hoa( $g['mo_ta'], 24 ),
				'v' => (int) $g['vip'],
			);
		}
		return $ra;
	}

	/** Bỏ dấu, viết hoa, cắt cho vừa bề ngang một ô trên màn ghế. */
	public static function bo_dau_hoa( $s, $dai = 18 ) {
		$s = mb_strtolower( trim( (string) $s ), 'UTF-8' );
		$cap = array(
			'a' => 'áàảãạăắằẳẵặâấầẩẫậ', 'e' => 'éèẻẽẹêếềểễệ', 'i' => 'íìỉĩị',
			'o' => 'óòỏõọôốồổỗộơớờởỡợ', 'u' => 'úùủũụưứừửữự', 'y' => 'ýỳỷỹỵ', 'd' => 'đ',
		);
		foreach ( $cap as $tron => $co ) {
			$s = str_replace( preg_split( '//u', $co, -1, PREG_SPLIT_NO_EMPTY ), $tron, $s );
		}
		/* Còn ký tự ngoài ASCII nghĩa là bảng trên thiếu chữ đó — BỎ ĐI chứ đừng gửi xuống ghế,
		   vì màn sẽ vẽ ra ô vuông và người ta tưởng ghế hỏng. */
		$s = preg_replace( '/[^\x20-\x7E]/', '', $s );
		$s = trim( preg_replace( '/\s+/', ' ', $s ) );
		return mb_strtoupper( mb_substr( $s, 0, max( 1, (int) $dai ) ), 'UTF-8' );
	}

	/**
	 * TÀI KHOẢN NHẬN TIỀN — khai MỘT LẦN cho cả hệ thống, từng ghế chỉ khai khi cần khác.
	 *
	 * 🔴 Anh Thắng: *"liên kết qua sepay và vietqr mà liên quan gì đến số tk"*. Số tài khoản vẫn
	 *    cần — mã QR phải nói tiền đi về đâu, SePay chỉ BÁO TIN tiền đã về. Nhưng bắt khai lại
	 *    cho từng ghế là thiết kế sai: 26 ghế là 26 lần gõ lại cùng một con số, và đổi tài khoản
	 *    là phải sửa đúng 26 chỗ — sót một chỗ thì tiền của ghế đó chảy về tài khoản cũ, âm thầm.
	 *
	 * Ô của từng ghế giữ lại làm NGOẠI LỆ (ghế đặt ở điểm có tài khoản riêng). Rỗng = dùng chung.
	 */
	public static function nhan_tien_chung() {
		return array(
			'bin'    => trim( (string) get_option( 'vhg_bin', '' ) ),
			'so_tk'  => trim( (string) get_option( 'vhg_so_tk', '' ) ),
			'ten_tk' => trim( (string) get_option( 'vhg_ten_tk', '' ) ),
		);
	}

	/**
	 * TỈ LỆ QUY ĐỔI CHUNG — bao nhiêu tiền ra bao nhiêu phút.
	 *
	 * 🔴 Anh Thắng 22/08/2026: *"không điều chỉnh được loại mệnh giá à"*. Bốn gói thì khai được,
	 *    nhưng SỐ PHÚT của chúng lại do tỉ lệ quyết định — mà tỉ lệ nằm tận ô "Thêm / sửa máy",
	 *    tách khỏi chỗ khai gói và phải lưu lại từng máy một. Nên nhìn bảng gói thì tưởng đã
	 *    khai xong, mà số phút vẫn là số cũ.
	 *
	 *    Tệ hơn: bảng xem trước lúc đó gọi cứng `menh_gia_cho_ghe(10000, 6)` — nó in ra một
	 *    bảng số phút KHÔNG phải số ghế sẽ chạy. Một bảng "xem trước" nói sai còn hại hơn không
	 *    có bảng nào, vì người ta tin nó rồi thôi không đi kiểm.
	 *
	 * Nên tỉ lệ đi cùng đường với tài khoản nhận tiền: khai MỘT LẦN, ghế nào cần khác mới khai
	 * riêng. `gia`/`phut` của máy bằng 0 = dùng chung.
	 */
	const GIA_MAC_DINH  = 50000;
	const PHUT_MAC_DINH = 15;

	public static function ty_le_chung() {
		$gia  = (int) get_option( 'vhg_gia', 0 );
		$phut = (int) get_option( 'vhg_phut', 0 );
		return array(
			'gia'  => $gia > 0 ? $gia : self::GIA_MAC_DINH,
			'phut' => $phut > 0 ? $phut : self::PHUT_MAC_DINH,
		);
	}

	/** Tỉ lệ THỰC DÙNG của một ghế: ô riêng nếu có (>0), không thì ô chung. */
	public static function ty_le_cua( $m ) {
		$c = self::ty_le_chung();
		$m = (array) $m;
		$gia  = isset( $m['gia'] ) ? (int) $m['gia'] : 0;
		$phut = isset( $m['phut'] ) ? (int) $m['phut'] : 0;
		return array(
			'gia'  => $gia > 0 ? $gia : $c['gia'],
			'phut' => $phut > 0 ? $phut : $c['phut'],
		);
	}

	public static function luu_ty_le( $gia, $phut ) {
		$gia  = (int) preg_replace( '/\D+/', '', (string) $gia );
		$phut = (int) preg_replace( '/\D+/', '', (string) $phut );
		if ( $gia < 1000 )              { return array( 'ok' => false, 'error' => 'Số tiền quy đổi phải từ 1.000đ.' ); }
		if ( $phut < 1 || $phut > 240 ) { return array( 'ok' => false, 'error' => 'Số phút quy đổi phải từ 1 đến 240.' ); }
		update_option( 'vhg_gia', $gia );
		update_option( 'vhg_phut', $phut );
		return array( 'ok' => true, 'thong_bao' => 'Đã lưu tỉ lệ ' . number_format( $gia, 0, ',', '.' )
			. 'đ = ' . $phut . ' phút. Ghế lấy về ở lượt nhịp kế tiếp (~30 giây).' );
	}

	/**
	 * Bỏ hết tỉ lệ khai riêng của từng ghế, cho tất cả dùng chung.
	 * Có nút này vì những ghế khai từ bản cũ đều mang tỉ lệ riêng (bản cũ không có ô chung), nên
	 * đổi ô chung mà chúng không theo — và không có gì trên màn nói vì sao.
	 */
	public static function bo_ty_le_rieng() {
		global $wpdb;
		$n = (int) $wpdb->query( 'UPDATE ' . VHG_DB::t( 'may' ) . ' SET gia=0, phut=0 WHERE gia>0 OR phut>0' );
		return array( 'ok' => true, 'thong_bao' => $n
			? 'Đã cho ' . $n . ' ghế dùng tỉ lệ chung.'
			: 'Tất cả ghế vốn đã dùng tỉ lệ chung.' );
	}

	/** Tài khoản THỰC DÙNG của một ghế: ô riêng nếu có, không thì ô chung. */
	public static function nhan_tien_cua( $m ) {
		$c = self::nhan_tien_chung();
		$m = (array) $m;
		/* Bản đồ ô-của-ghế -> ô-chung. Tên cột khác tên khoá (`bank_bin` vs `bin`), nên viết ra
		   một chỗ chứ đừng lặp ba lần — lặp là chỗ thứ ba gõ nhầm và ghế đó âm thầm dùng tài
		   khoản rỗng. */
		$ra = array();
		foreach ( array( 'bin' => 'bank_bin', 'so_tk' => 'so_tk', 'ten_tk' => 'ten_tk' ) as $khoa => $cot ) {
			$rieng   = isset( $m[ $cot ] ) ? trim( (string) $m[ $cot ] ) : '';
			$ra[ $khoa ] = '' !== $rieng ? $rieng : $c[ $khoa ];
		}
		return $ra;
	}

	/**
	 * Nhớ số tài khoản mà BÊN GỬI báo là đã nhận tiền.
	 *
	 * 🔴 VÌ SAO CẦN. Ngày 22/08/2026 anh Thắng quét thử mã QR bằng app BIDV và bị chối: *"Định
	 *    dạng tài khoản định danh không hợp lệ (174)"*. Nguyên nhân: ô số tài khoản khai tay
	 *    thiếu MỘT chữ số — `888815678` thay vì `8888815678`.
	 *
	 *    Một chữ số. Không có gì trên màn hình sai cả: mã QR vẫn dựng ra, vẫn trông như thật,
	 *    vẫn dán được lên 26 cái ghế. Chỉ tới lúc có khách đứng quét mới lộ — và lúc đó thì đã
	 *    mất một buổi bán hàng ở 26 cửa hàng.
	 *
	 *    Không có cách nào kiểm số tài khoản bằng luật chữ số: mỗi ngân hàng một khuôn, BIDV còn
	 *    có mấy loại tài khoản định danh dài ngắn khác nhau. NHƯNG mình có một sự thật đối chứng
	 *    miễn phí: mỗi lượt webhook, SePay nói rõ tiền vừa vào TÀI KHOẢN NÀO. Nếu số đó khác số
	 *    đang khai thì một trong hai sai — và đó là câu duy nhất đáng nói ra.
	 */
	public static function nho_tk_ben_gui( $so_tk, $tk_ao = '' ) {
		$so_tk = trim( (string) $so_tk );
		if ( '' === $so_tk ) { return; }
		update_option( 'vhg_tk_ben_gui', array( 'so' => $so_tk,
			'va' => trim( (string) $tk_ao ), 'luc' => current_time( 'mysql' ) ) );
	}

	/**
	 * Số đang khai có khớp số bên gửi báo không.
	 * @return array [ 'co' => bool đã từng thấy, 'khop' => bool, 'ben_gui' => string, 'luc' => string ]
	 */
	public static function doi_chieu_tk() {
		$g = get_option( 'vhg_tk_ben_gui' );
		$ben_gui = is_array( $g ) && isset( $g['so'] ) ? trim( (string) $g['so'] ) : '';
		if ( '' === $ben_gui ) {
			return array( 'co' => false, 'khop' => true, 'ben_gui' => '', 'va' => '', 'luc' => '' );
		}
		$khai = self::nhan_tien_chung();
		/* So bằng CHỮ VÀ SỐ, không chỉ chữ số.
		 *
		 * 🔴 Bản trước bỏ hết chữ cái. Tài khoản ảo của SePay là chuỗi CÓ CHỮ (`96247POSH`), nên
		 *    khai VA vào đây là nó biến thành `96247` rồi so với `8888815678` — báo đỏ oan cho
		 *    đúng cấu hình chạy được. Chỉ bỏ dấu cách và gạch: bên gửi có khi trả
		 *    "888 881 5678", khác cách viết không phải khác tài khoản. */
		$a = strtoupper( preg_replace( '/[^0-9A-Za-z]/', '', (string) $khai['so_tk'] ) );
		$b = strtoupper( preg_replace( '/[^0-9A-Za-z]/', '', $ben_gui ) );
		$va = is_array( $g ) && isset( $g['va'] ) ? trim( (string) $g['va'] ) : '';
		/* Khớp NẾU trùng số tài khoản HOẶC trùng tài khoản ảo: mã QR trỏ vào cái nào cũng được,
		   miễn là cái SePay đang theo dõi. Chỉ đòi trùng đúng một ô là báo đỏ oan cho cấu hình
		   đang chạy tốt — và một cảnh báo oan là một cảnh báo bị bỏ qua mãi về sau. */
		$c = strtoupper( preg_replace( '/[^0-9A-Za-z]/', '', $va ) );
		$khop = ( '' !== $a && $a === $b ) || ( '' !== $c && $c === $a );
		return array( 'co' => true, 'khop' => $khop, 'ben_gui' => $ben_gui, 'va' => $va,
			'luc' => is_array( $g ) && isset( $g['luc'] ) ? (string) $g['luc'] : '' );
	}

	/**
	 * TIỀN TỐ BẮT BUỘC TRONG NỘI DUNG CHUYỂN KHOẢN.
	 *
	 * 🔴 MẮT XÍCH CUỐI CÙNG, tìm ra 22/08/2026 trên chính trang Tạo QR của SePay:
	 *
	 *      "SEVQR — VietinBank cá nhân/hộ kinh doanh BẮT BUỘC nội dung CK phải chứa `sevqr`
	 *       để định tuyến giao dịch qua SePay."
	 *
	 *    Không có chuỗi đó thì tiền vẫn vào tài khoản, ngân hàng vẫn báo thành công, nhưng
	 *    SePay KHÔNG BAO GIỜ THẤY — nên không có webhook, và ghế không chạy. Đúng cái đã xảy
	 *    ra: lượt 2.000đ quét từ trang SePay (có SEVQR) thì về, lượt 10.000đ quét chỗ khác
	 *    (không có SEVQR) thì biến mất không dấu vết.
	 *
	 *    Đây là kiểu hỏng tệ nhất trong cả hệ thống: KHÔNG AI BÁO GÌ CẢ. Khách trả tiền xong,
	 *    ngân hàng nói "thành công", ghế đứng im, và trong sổ của mình không có một dòng nào —
	 *    kể cả dòng "có gói lạ bắn tới". Không có gì để đi tìm.
	 *
	 * ⚠️ Tiền tố tuỳ NGÂN HÀNG và tuỳ LOẠI tài khoản, nên KHÔNG gán cứng "SEVQR" trong mã:
	 *    tài khoản doanh nghiệp, hay ngân hàng khác, có thể không cần — mà thừa một chuỗi lạ
	 *    trong nội dung là tốn chỗ của mã lượt (VietQR chỉ cho 25 ký tự).
	 */
	public static function tien_to_nd() {
		return trim( (string) get_option( 'vhg_tien_to_nd', '' ) );
	}

	public static function luu_tien_to_nd( $v ) {
		/* Chỉ chữ và số: nội dung chuyển khoản đi qua nhiều hệ thống, dấu và ký tự lạ là chỗ
		   bị cắt hoặc bị đổi mà không ai báo. */
		$v = strtoupper( preg_replace( '/[^0-9A-Za-z]/', '', (string) $v ) );
		if ( strlen( $v ) > 10 ) {
			return array( 'ok' => false, 'error' => 'Tiền tố tối đa 10 ký tự — nội dung chuyển khoản '
				. 'chỉ có 25 chỗ, để dành cho mã ghế và mã lượt.' );
		}
		update_option( 'vhg_tien_to_nd', $v );
		return array( 'ok' => true, 'thong_bao' => '' === $v
			? 'Đã bỏ tiền tố nội dung chuyển khoản.'
			: 'Đã đặt tiền tố "' . $v . '". Ghế lấy về ở lượt nhịp kế tiếp (~30 giây).' );
	}

	public static function luu_nhan_tien( $bin, $so_tk, $ten_tk ) {
		$bin   = preg_replace( '/\D+/', '', (string) $bin );
		$so_tk = trim( (string) $so_tk );
		if ( '' !== $bin && ! preg_match( '/^\d{6}$/', $bin ) ) {
			return array( 'ok' => false, 'error' => 'Mã ngân hàng (BIN) phải đúng 6 chữ số. VD 970418 = BIDV.' );
		}
		update_option( 'vhg_bin', $bin );
		update_option( 'vhg_so_tk', $so_tk );
		update_option( 'vhg_ten_tk', trim( (string) $ten_tk ) );
		return array( 'ok' => true, 'thong_bao' => 'Đã lưu tài khoản nhận tiền chung. '
			. 'Ghế lấy về ở lượt nhịp kế tiếp (~30 giây), không phải nạp lại firmware.' );
	}

	/**
	 * GÁN MÃ THẬT cho một ghế đã tự hiện ra (mã còn bắt đầu bằng '?').
	 *
	 * 🔴 Đổi mã KHÔNG chỉ là đổi một ô. `ma` là khoá mà doanh thu, hàng chờ, nhịp và lệnh đều
	 *    trỏ tới. Đổi mỗi bảng `may` thì lịch sử của ghế đó mồ côi: lượt khách ĐÃ TRẢ TIỀN mà
	 *    ghế chưa nhận sẽ nằm lại dưới mã cũ, ghế hỏi bằng mã mới nên không bao giờ thấy —
	 *    khách trả tiền xong ghế không chạy, và không có gì trên màn hình nói vì sao.
	 *    Nên dời TẤT CẢ trong cùng một lượt.
	 */
	public static function gan_ma( $ma_cu, $ma_moi, $coso_id = null ) {
		global $wpdb;
		$ma_cu  = trim( (string) $ma_cu );
		$ma_moi = trim( (string) $ma_moi );
		if ( '' === $ma_cu || '' === $ma_moi ) { return array( 'ok' => false, 'error' => 'Thiếu mã.' ); }
		if ( ! preg_match( '/^[A-Za-z0-9]{1,20}$/', $ma_moi ) ) {
			return array( 'ok' => false, 'error' => 'Mã mới chỉ được gồm chữ và số, không dấu, không '
				. 'khoảng trắng. Mã này đi vào nội dung chuyển khoản khách gõ tay.' );
		}
		if ( $ma_cu === $ma_moi ) { return array( 'ok' => true, 'thong_bao' => 'Mã không đổi.' ); }
		$bang = VHG_DB::t( 'may' );
		if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $bang WHERE ma=%s LIMIT 1", $ma_cu ) ) ) {
			return array( 'ok' => false, 'error' => 'Không thấy ghế ' . $ma_cu . '.' );
		}
		if ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $bang WHERE ma=%s LIMIT 1", $ma_moi ) ) ) {
			return array( 'ok' => false, 'error' => 'Mã ' . $ma_moi . ' đã có ghế khác dùng. '
				. 'Hai ghế cùng mã là tiền của ghế này chạy ghế kia.' );
		}
		$dat = array( 'ma' => $ma_moi, 'cap_nhat' => current_time( 'mysql' ) );
		/* Gán cơ sở CÙNG LÚC với gán mã. Người đi lắp ghế biết nó đang ở đâu ngay lúc đó; bắt
		   quay lại một màn khác để chọn cơ sở là bước dễ quên nhất, mà quên thì doanh thu ghế
		   đó rơi vào ô "(chưa gán)" và bảng theo cơ sở sai âm thầm. */
		if ( null !== $coso_id ) { $dat['coso_id'] = (int) $coso_id; }
		$wpdb->update( $bang, $dat, array( 'ma' => $ma_cu ) );
		/* Dời hết những gì trỏ tới mã cũ. `thu` để CUỐI: nó là sổ tiền, và nếu có gì hỏng giữa
		   chừng thì thà sổ tiền còn nguyên mã cũ (đối soát tay được) hơn là hàng chờ mồ côi. */
		$dem = 0;
		foreach ( array( 'cho', 'nhip', 'lenh', 'thu' ) as $b ) {
			$dem += (int) $wpdb->query( $wpdb->prepare(
				'UPDATE ' . VHG_DB::t( $b ) . ' SET ma_may=%s WHERE ma_may=%s', $ma_moi, $ma_cu ) );
		}
		return array( 'ok' => true, 'thong_bao' => 'Đã gán mã ' . $ma_moi . ' cho ghế ' . $ma_cu
			. ' và dời ' . $dem . ' dòng lịch sử sang mã mới.' );
	}

	public static function luu_may( $d ) {
		global $wpdb;
		$ma = trim( (string) ( isset( $d['ma'] ) ? $d['ma'] : '' ) );
		if ( '' === $ma ) { return array( 'ok' => false, 'error' => 'Thiếu mã máy.' ); }
		/* Mã máy đi vào nội dung chuyển khoản mà khách gõ tay ("GHE3 T1ABC"). Dấu và khoảng
		   trắng ở đó là khách gõ sai, gõ sai là tiền vào mà ghế không chạy. Chặn ngay lúc khai. */
		if ( ! preg_match( '/^[A-Za-z0-9]{1,20}$/', $ma ) ) {
			return array( 'ok' => false, 'error' => 'Mã máy chỉ được gồm chữ và số, không dấu, không '
				. 'khoảng trắng (tối đa 20 ký tự). Mã này đi vào nội dung chuyển khoản khách gõ tay — '
				. 'có dấu là khách gõ sai và ghế không chạy.' );
		}
		$mac_go = trim( (string) ( isset( $d['mac'] ) ? $d['mac'] : '' ) );
		if ( '' !== $mac_go && '' === self::chuan_mac( $mac_go ) ) {
			/* Im lặng bỏ qua một MAC gõ sai là tệ nhất: người ta tưởng đã gắn ghế, còn ghế thật
			   thì vẫn hiện ra như một ghế mới ở danh sách chờ. */
			return array( 'ok' => false, 'error' => 'Địa chỉ MAC không đúng khuôn — phải là 12 ký tự '
				. '0-9/A-F, ví dụ AA:BB:CC:DD:EE:FF. Đang nhận: "' . esc_html( $mac_go ) . '".' );
		}
		$hang = array(
			'ma'       => $ma,
			'mac'      => self::chuan_mac( isset( $d['mac'] ) ? $d['mac'] : '' ),
			'coso_id'  => (int) ( isset( $d['coso_id'] ) ? $d['coso_id'] : 0 ),
			/* 0 = DÙNG CHUNG (xem ty_le_cua). Không ép về mặc định nữa: ép là mọi ghế thành
			   "khai riêng" và ô chung mất tác dụng ngay lúc khai máy đầu tiên. */
			'gia'      => max( 0, (int) ( isset( $d['gia'] ) ? $d['gia'] : 0 ) ),
			'phut'     => max( 0, (int) ( isset( $d['phut'] ) ? $d['phut'] : 0 ) ),
			'so_tk'    => trim( (string) ( isset( $d['so_tk'] ) ? $d['so_tk'] : '' ) ),
			'ten_tk'   => trim( (string) ( isset( $d['ten_tk'] ) ? $d['ten_tk'] : '' ) ),
			'bank_bin' => trim( (string) ( isset( $d['bank_bin'] ) ? $d['bank_bin'] : '' ) ),
			'ten_khai' => VHG_Doc::chuan_ten( isset( $d['ten_khai'] ) ? $d['ten_khai'] : '' ),
			'cap_nhat' => current_time( 'mysql' ),
		);
		$bang = VHG_DB::t( 'may' );
		/* MAC rỗng thì đừng ghi đè MAC đang có — người sửa giá không nên vô tình cắt đứt liên
		   kết giữa ghế thật và dòng cấu hình của nó. */
		if ( '' === $hang['mac'] ) { unset( $hang['mac'] ); }
		$co = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $bang WHERE ma=%s LIMIT 1", $ma ) );
		/* Gán mã thật cho một ghế đang chờ: tìm theo MAC rồi ĐỔI mã của chính dòng đó, chứ không
		   tạo dòng mới — tạo mới là ghế cũ nằm lại mãi trong danh sách chờ gán. */
		if ( ! $co && isset( $hang['mac'] ) ) {
			$cu_mac = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $bang WHERE mac=%s LIMIT 1", $hang['mac'] ) );
			if ( $cu_mac ) { $co = $cu_mac; }
		}
		if ( $co ) { $wpdb->update( $bang, $hang, array( 'id' => (int) $co ) ); }
		else { $wpdb->insert( $bang, $hang ); }
		return array( 'ok' => true, 'thong_bao' => 'Đã lưu máy ' . $ma . '.' );
	}

	/** Chuyển ghế sang cơ sở khác — CHỈ đổi coso_id, giữ nguyên giá/thời lượng/số tài khoản.
	 *  (gan_ma trả về sớm khi mã không đổi nên không dùng để đổi mỗi cơ sở được.) */
	public static function dat_coso( $ma, $coso_id ) {
		global $wpdb;
		$ma = trim( (string) $ma );
		if ( '' === $ma ) { return array( 'ok' => false, 'error' => 'Thiếu mã ghế.' ); }
		$wpdb->update( VHG_DB::t( 'may' ),
			array( 'coso_id' => (int) $coso_id, 'cap_nhat' => current_time( 'mysql' ) ),
			array( 'ma' => $ma ) );
		return array( 'ok' => true, 'thong_bao' => 'Đã chuyển cơ sở cho ghế ' . $ma . '.' );
	}

	public static function xoa_may( $ma ) {
		global $wpdb;
		$wpdb->delete( VHG_DB::t( 'may' ), array( 'ma' => (string) $ma ) );
		/* Doanh thu của máy đó KHÔNG xoá theo — tiền đã thu là chuyện đã xảy ra, xoá cấu hình
		   máy không làm nó chưa xảy ra. */
		return array( 'ok' => true, 'thong_bao' => 'Đã xoá cấu hình máy ' . $ma
			. '. Doanh thu đã ghi của máy này giữ nguyên.' );
	}

	/**
	 * Ghế nào mang MAC này. Trả mảng dòng `may`, hoặc null.
	 *
	 * ⚠️ KHÔNG tự gán mã ghế cho MAC lạ. Ghế chưa khai thì nó hiện ra trong danh sách "chờ gán"
	 *    để người ta gán tay. Đoán bừa là hai ghế cùng nhận một mã, và tiền của ghế này chạy ghế
	 *    kia — mà nhìn từ máy chủ thì không có gì bất thường cả.
	 */
	/**
	 * MAC về ĐÚNG MỘT DẠNG: `AA:BB:CC:DD:EE:FF`.
	 *
	 * 🔴 Ghế gửi lên dạng có hai chấm và chữ hoa (`snprintf("%02X:...")`). Người gõ tay thì gõ
	 *    đủ kiểu: thường, gạch ngang, hoặc dính liền không dấu. Không chuẩn hoá thì cùng một
	 *    con ghế mà bảng có hai dòng — dòng khai tay không bao giờ khớp, và ghế hiện ra như một
	 *    ghế mới. Chuẩn hoá ở MỘT chỗ, dùng cho cả đường ghế gửi lên lẫn đường người gõ vào.
	 *
	 * Chuỗi không phải MAC -> trả RỖNG, không trả bừa: một MAC nửa vời còn tệ hơn không có MAC,
	 * vì nó trông như đã gắn ghế.
	 */
	public static function chuan_mac( $mac ) {
		$s = strtoupper( preg_replace( '/[^0-9A-Fa-f]/', '', (string) $mac ) );
		if ( 12 !== strlen( $s ) ) { return ''; }
		return implode( ':', str_split( $s, 2 ) );
	}

	public static function theo_mac( $mac ) {
		global $wpdb;
		$mac = self::chuan_mac( $mac );
		if ( '' === $mac ) { return null; }
		if ( '' === $mac ) { return null; }
		return $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . VHG_DB::t( 'may' ) . ' WHERE mac=%s LIMIT 1', $mac ), ARRAY_A );
	}

	/**
	 * Ghi nhận một ghế đang gọi tới. Trả mã ghế ('' nếu chưa được gán).
	 * MAC lạ -> tạo một dòng CHỜ GÁN (mã rỗng) để nó hiện lên web, chứ không im lặng bỏ qua:
	 * ghế cắm điện mà không hiện ở đâu cả thì người đi lắp không biết mình sai chỗ nào.
	 */
	public static function ghi_nhan( $mac ) {
		global $wpdb;
		$mac = self::chuan_mac( $mac );
		if ( '' === $mac ) { return ''; }
		$m = self::theo_mac( $mac );
		if ( $m ) { return (string) $m['ma']; }
		$bang = VHG_DB::t( 'may' );
		/* Mã tạm mang chính MAC: cột `ma` là UNIQUE nên không để rỗng được cho nhiều ghế. Dấu
		   hiệu "chưa gán" là mã bắt đầu bằng `?` — người ta thấy ngay trên màn. */
		$tam = '?' . substr( preg_replace( '/[^0-9A-F]/', '', $mac ), -6 );
		if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $bang WHERE ma=%s LIMIT 1", $tam ) ) ) {
			$wpdb->insert( $bang, array( 'ma' => $tam, 'mac' => $mac, 'gia' => 10000, 'phut' => 6,
				'ghi_chu' => 'ghế tự hiện ra khi cắm điện — chưa gán mã', 'cap_nhat' => current_time( 'mysql' ) ) );
		}
		return $tam;
	}

	/** Ghế chưa được gán mã thật (mã còn bắt đầu bằng '?'). */
	public static function chua_gan() {
		$ds = VHG_DB::rows( 'SELECT * FROM ' . VHG_DB::t( 'may' ) . " WHERE ma LIKE '?%' ORDER BY id ASC" );
		/* Kèm luôn nhịp cuối: màn hình cần biết ghế này còn sống không TRƯỚC khi người ta gán mã
		   cho nó. Gán mã cho một ghế đã tháo đi là để lại một dòng cấu hình không bao giờ dùng.
		   Truy vấn để ở ĐÂY chứ không ở màn hình — màn hình không được tự viết SQL, và luật đó
		   có phép thử canh. */
		foreach ( $ds as $i => $x ) {
			$n = self::nhip_cua( $x['ma'] );
			$ds[ $i ]['nhip_luc'] = $n['luc'];
			$ds[ $i ]['fw']       = $n['fw'];
			$ds[ $i ]['ip']       = $n['ip'];
			$ds[ $i ]['con_song'] = self::con_song( $n['luc'] );
		}
		return $ds;
	}

	/** Nhịp cuối của một ghế. Luôn trả đủ ba khoá, kể cả khi ghế chưa gửi nhịp lần nào. */
	public static function nhip_cua( $ma_may ) {
		global $wpdb;
		$r = $wpdb->get_row( $wpdb->prepare(
			'SELECT luc, fw, ip, nd_tien_to FROM ' . VHG_DB::t( 'nhip' ) . ' WHERE ma_may=%s LIMIT 1',
			(string) $ma_may ), ARRAY_A );
		return array(
			'luc' => $r ? (string) $r['luc'] : '',
			'fw'  => $r ? (string) $r['fw'] : '',
			'ip'  => $r ? (string) $r['ip'] : '',
			'nd_tien_to' => $r ? (string) $r['nd_tien_to'] : '',
		);
	}

	public static function may( $ma ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . VHG_DB::t( 'may' ) . ' WHERE ma=%s LIMIT 1', (string) $ma ), ARRAY_A );
	}

	// ======================================================================= hàng chờ chạy

	/** Tiền đã vào -> xếp cho ghế chạy. Cùng (máy, mã) tới hai lần thì chỉ một hàng. */
	public static function xep_cho_chay( $ma_may, $ma_lenh, $so_tien, $ref, $noi_dung ) {
		global $wpdb;
		$bang = VHG_DB::t( 'cho' );
		$co = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM $bang WHERE ma_may=%s AND ma_lenh=%s LIMIT 1", $ma_may, $ma_lenh ) );
		if ( $co ) { return (int) $co; }
		$wpdb->insert( $bang, array(
			'ma_may' => (string) $ma_may, 'ma_lenh' => (string) $ma_lenh,
			'so_tien' => (int) $so_tien, 'ref' => (string) $ref,
			'noi_dung' => mb_substr( (string) $noi_dung, 0, 250 ),
			'tao_luc' => current_time( 'mysql' ), 'nhan_luc' => null ) );
		return (int) $wpdb->insert_id;
	}

	public static function so_cho( $ma_may ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . VHG_DB::t( 'cho' ) . ' WHERE ma_may=%s AND nhan_luc IS NULL', $ma_may ) );
	}

	public static function ds_cho( $chi_chua_nhan = true, $gioi_han = 200 ) {
		$sql = 'SELECT * FROM ' . VHG_DB::t( 'cho' )
			. ( $chi_chua_nhan ? ' WHERE nhan_luc IS NULL' : '' )
			. ' ORDER BY id DESC LIMIT ' . (int) $gioi_han;
		return VHG_DB::rows( $sql );
	}

	/**
	 * Ghế hỏi "có ai trả tiền cho tôi chưa". Trả lượt CŨ NHẤT chưa nhận và đánh dấu đã nhận.
	 *
	 * ⚠️ ĐÁNH DẤU NGAY, KHÔNG chờ ghế báo chạy xong. Ghế mất điện giữa chừng thì khách mất lượt —
	 *    nhưng KHÔNG đánh dấu thì ghế khởi động lại là chạy lại lượt cũ, và cứ thế mãi. Giữa "mất
	 *    một lượt hiếm khi" và "một lượt chạy vô hạn", chọn cái thứ nhất; cái thứ hai còn làm
	 *    hỏng cả bảng đối soát. Người ta bù tay bằng lệnh bật.
	 */
	public static function lay_luot( $ma_may ) {
		global $wpdb;
		$bang = VHG_DB::t( 'cho' );
		$r = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $bang WHERE ma_may=%s AND nhan_luc IS NULL ORDER BY id ASC LIMIT 1",
			(string) $ma_may ), ARRAY_A );
		if ( ! $r ) { return null; }
		$wpdb->update( $bang, array( 'nhan_luc' => current_time( 'mysql' ) ), array( 'id' => (int) $r['id'] ) );
		return $r;
	}

	// ======================================================================= nhịp sống

	public static function nhip( $ma_may, $d ) {
		global $wpdb;
		$ma_may = trim( (string) $ma_may );
		if ( '' === $ma_may ) { return false; }
		$bang = VHG_DB::t( 'nhip' );
		$hang = array(
			'ma_may'     => $ma_may,
			'trang_thai' => mb_substr( (string) ( isset( $d['trang_thai'] ) ? $d['trang_thai'] : 'idle' ), 0, 20 ),
			'nguon'      => mb_substr( (string) ( isset( $d['nguon'] ) ? $d['nguon'] : '' ), 0, 20 ),
			'con_lai'    => (int) ( isset( $d['con_lai'] ) ? $d['con_lai'] : 0 ),
			/* Chặn trên 65535 vì cột là SMALLINT UNSIGNED: quá tầm thì MySQL cắt về 65535 ở chế
			   độ lỏng, hoặc từ chối cả hàng ở chế độ chặt — mất luôn nhịp vì một con số chỉ để
			   chỉnh đồng hồ. Ghế mất mạng lâu có thể gửi lên con số rất lớn. */
			'tre_ms'     => max( 0, min( 65535, (int) ( isset( $d['tre'] ) ? $d['tre'] : 0 ) ) ),
			'tm_loi'     => self::ma_loi_tien( isset( $d['tm_loi'] ) ? $d['tm_loi'] : '' ),
			'tm_cuoi'    => self::ma_loi_tien( isset( $d['tm_cuoi'] ) ? $d['tm_cuoi'] : '' ),
			'tm_lan'     => max( 0, min( 65535, (int) ( isset( $d['tm_lan'] ) ? $d['tm_lan'] : 0 ) ) ),
			/* Ghế đếm bằng millis() của chính nó, không có đồng hồ thật — nên nó khai TUỔI
			   (bao nhiêu giây trước), còn máy chủ đổi ra giờ tuyệt đối theo đồng hồ của mình.
			   Đây là cách duy nhất đúng khi hai bên không chung đồng hồ. */
			'tm_luc'     => self::luc_tu_tuoi( isset( $d['tm_giay'] ) ? $d['tm_giay'] : -1 ),
			'tm_to'      => self::luc_tu_tuoi( isset( $d['tm_to'] ) ? $d['tm_to'] : -1 ),
			'khoa'       => ! empty( $d['khoa'] ) ? 1 : 0,   // ghế đang KHÓA lỗi (chờ hotline mở)
			'kt'         => ! empty( $d['kt'] ) ? 1 : 0,     // ghế đang CHẾ ĐỘ KỸ THUẬT (test)
			'ip'         => mb_substr( (string) ( isset( $d['ip'] ) ? $d['ip'] : '' ), 0, 60 ),
			'nd_tien_to' => mb_substr( trim( (string) ( isset( $d['nd'] ) ? $d['nd'] : '' ) ), 0, 20 ),
			/* 80, KHỚP VỚI CỘT. Cắt 40 ở đây là cách hỏng âm thầm: chuỗi phiên bản
			   "ghe-massage 2026-08-22e (tien to noi dung CK tu web)" dài 51 ký tự, cắt xong
			   thành nửa câu, và màn đối chiếu firmware chỉ đó nói ghế đang chạy bản nào. */
			'fw'         => mb_substr( (string) ( isset( $d['fw'] ) ? $d['fw'] : '' ), 0, 80 ),
			'luc'        => current_time( 'mysql' ),
		);

		/* ══════════════════════════════════════════════════════════════════════════════════
		 * TRẠNG THÁI CHẠY THẬT (chân báo-chạy của bo ghế) -> NHẬT KÝ BẬT/TẮT.
		 *
		 * ⚠️ CHỈ XÉT KHI GHẾ CÓ KHAI `chay`. Firmware cũ (chưa bật DO_GHECHAY) không gửi trường
		 *    này — coi vắng mặt là 0 thì mỗi nhịp của ghế cũ ghi một lượt "tắt" giả. Vắng mặt =
		 *    -1 = "ghế không đo được", KHÔNG đụng vào cột `chay` và KHÔNG ghi nhật ký.
		 * ⚠️ Chỉ ghi khi TRẠNG THÁI ĐỔI so với nhịp trước. Nhịp lặp lại cùng trạng thái không phải
		 *    một lần bật/tắt mới. Lần đầu một ghế gửi nhịp thì chưa có mốc để so -> không ghi
		 *    (tránh một lượt "bật" giả ngay sau khi nâng cấp cho ghế đang chạy). */
		$chay_moi = array_key_exists( 'chay', $d ) ? ( empty( $d['chay'] ) ? 0 : 1 ) : -1;
		if ( $chay_moi >= 0 ) { $hang['chay'] = $chay_moi; }

		$cu = $wpdb->get_row( $wpdb->prepare(
			"SELECT id, chay FROM $bang WHERE ma_may=%s LIMIT 1", $ma_may ), ARRAY_A );
		if ( $cu ) {
			if ( $chay_moi >= 0 && (int) $cu['chay'] !== $chay_moi ) {
				/* Ghế khai tuổi của lần đổi trạng thái (`chay_giay`); đổi ra giờ tuyệt đối. Không
				   khai được thì lấy giờ nhận làm mốc — trễ vài giây, vẫn đúng thứ tự. */
				$luc_doi = self::luc_tu_tuoi( isset( $d['chay_giay'] ) ? $d['chay_giay'] : -1 );
				if ( null === $luc_doi ) { $luc_doi = current_time( 'mysql' ); }
				self::ghi_bat_tat( $ma_may, $chay_moi ? 'bat' : 'tat', $luc_doi );
			}
			$wpdb->update( $bang, $hang, array( 'id' => (int) $cu['id'] ) );
		} else {
			$wpdb->insert( $bang, $hang );
		}
		return true;
	}

	/**
	 * GHI MỘT LẦN BẬT/TẮT vào nhật ký vận hành.
	 *
	 * `giay` chỉ có nghĩa cho dòng 'tat': đo từ lần 'bat' gần nhất của CHÍNH ghế đó tới lượt tắt
	 * này. Tính sẵn ở đây thay vì ghép cặp lúc đọc — bảng lịch sử mở trên 4G, ghép cặp mỗi lần
	 * mở là quét cả bảng chỉ để hiện một cột.
	 */
	public static function ghi_bat_tat( $ma_may, $su_kien, $luc ) {
		global $wpdb;
		$ma_may  = mb_substr( trim( (string) $ma_may ), 0, 40 );
		$su_kien = ( 'bat' === $su_kien ) ? 'bat' : 'tat';
		if ( '' === $ma_may ) { return; }
		$t   = VHG_DB::t( 'bat_tat' );
		$luc = '' !== trim( (string) $luc ) ? $luc : current_time( 'mysql' );

		$giay = 0;
		if ( 'tat' === $su_kien ) {
			$bat = $wpdb->get_var( $wpdb->prepare(
				"SELECT luc FROM $t WHERE ma_may=%s AND su_kien='bat' ORDER BY id DESC LIMIT 1", $ma_may ) );
			if ( $bat ) {
				$g = strtotime( (string) $luc ) - strtotime( (string) $bat );
				/* Chặn 0..24h: mốc lỗi hoặc ghế chạy vắt ngày làm ra con số vô lý — thà để 0 (không
				   biết) còn hơn một cột "chạy 3 ngày" mà không ai tin nổi. */
				if ( $g > 0 && $g <= 86400 ) { $giay = (int) $g; }
			}
		}
		$wpdb->insert( $t, array(
			'ma_may'  => $ma_may,
			'su_kien' => $su_kien,
			'luc'     => $luc,
			'giay'    => $giay,
			'tao_luc' => current_time( 'mysql' ),
		) );
	}

	/**
	 * LỊCH SỬ BẬT/TẮT — mới nhất trước, kèm tên cơ sở.
	 *
	 * ⚠️ Trả cả `coso` để màn lọc/gom theo cơ sở được ngay, không phải tra lại từng dòng.
	 */
	public static function ds_bat_tat( $ky = 'week', $gioi_han = 500 ) {
		global $wpdb;
		$t  = VHG_DB::t( 'bat_tat' );
		$tu = VHG_Thu::dau_ky( $ky );
		$gh = max( 1, min( 1000, (int) $gioi_han ) );
		$sql = "SELECT * FROM $t";
		if ( '' !== $tu ) { $sql = $wpdb->prepare( $sql . ' WHERE luc >= %s', $tu ); }
		$sql .= ' ORDER BY id DESC LIMIT ' . $gh;
		$may = self::ds_may_theo_ma();
		$ra  = array();
		foreach ( VHG_DB::rows( $sql ) as $r ) {
			$m = (string) $r['ma_may'];
			$ra[] = array(
				'id'      => (int) $r['id'],
				'ma'      => $m,
				'coso'    => isset( $may[ $m ] ) ? (string) $may[ $m ]['coso_ten'] : '',
				'su_kien' => (string) $r['su_kien'],
				'luc'     => (string) $r['luc'],
				'giay'    => (int) $r['giay'],
			);
		}
		return $ra;
	}

	/**
	 * Gộp theo GHẾ trong kỳ: mấy lần chạy, tổng bao nhiêu phút chạy thật, lần cuối khi nào.
	 * Đếm số lần chạy = số dòng 'bat'; tổng phút = tổng `giay` của các dòng 'tat'.
	 */
	public static function tong_bat_tat_may( $ky = 'week' ) {
		global $wpdb;
		$t  = VHG_DB::t( 'bat_tat' );
		$tu = VHG_Thu::dau_ky( $ky );
		$dk = '' !== $tu ? $wpdb->prepare( ' AND luc >= %s', $tu ) : '';
		$sql = "SELECT ma_may,
			SUM(CASE WHEN su_kien='bat' THEN 1 ELSE 0 END) AS so_lan,
			SUM(CASE WHEN su_kien='tat' THEN giay ELSE 0 END) AS tong_giay,
			MAX(luc) AS lan_cuoi
			FROM $t WHERE 1=1$dk GROUP BY ma_may ORDER BY so_lan DESC LIMIT 200";
		$may = self::ds_may_theo_ma();
		$ra  = array();
		foreach ( VHG_DB::rows( $sql ) as $r ) {
			$m = (string) $r['ma_may'];
			$ra[] = array( 'ma' => $m,
				'coso' => isset( $may[ $m ] ) ? (string) $may[ $m ]['coso_ten'] : '',
				'so_lan' => (int) $r['so_lan'],
				'tong_phut' => (int) round( (int) $r['tong_giay'] / 60 ),
				'lan_cuoi' => (string) $r['lan_cuoi'] );
		}
		return $ra;
	}

	// ======================================================================= lệnh bật/tắt tay

	/**
	 * Đặt lệnh cho ghế. `$viec` = 'on' (chạy $phut phút) hoặc 'off' (tắt ngay).
	 * ⚠️ BẮT BUỘC ghi người đặt — đây là đường cho không một lượt massage, xem khối ⚠️ đầu tệp.
	 */
	public static function dat_lenh( $ma_may, $viec, $phut, $nguoi, $ly_do = '' ) {
		global $wpdb;
		$ma_may = trim( (string) $ma_may );
		if ( '' === $ma_may ) { return array( 'ok' => false, 'error' => 'Thiếu mã máy.' ); }
		if ( ! in_array( $viec, array( 'on', 'off', 'reboot', 'mokhoa', 'test' ), true ) ) {
			return array( 'ok' => false, 'error' => 'Lệnh chỉ có thể là bật (on), tắt (off), '
				. 'khởi động lại (reboot), mở khoá lỗi (mokhoa) hoặc chế độ kỹ thuật (test).' );
		}
		$nguoi = trim( (string) $nguoi );
		if ( '' === $nguoi ) { return array( 'ok' => false, 'error' => 'Thiếu tên người đặt lệnh.' ); }
		$phut = (int) $phut;
		if ( 'on' === $viec ) {
			$m = self::may( $ma_may );
			if ( $phut <= 0 ) { $phut = $m ? (int) $m['phut'] : 6; }
			/* Chặn trần: gõ nhầm 600 thay vì 6 là ghế chạy 10 tiếng và không ai ở đó để tắt. */
			if ( $phut > 60 ) {
				return array( 'ok' => false, 'error' => 'Tối đa 60 phút một lệnh. Gõ nhầm số 0 là '
					. 'ghế chạy suốt đêm mà không ai ở đó để tắt.' );
			}
		}
		$wpdb->insert( VHG_DB::t( 'lenh' ), array(
			'ma_may' => $ma_may, 'viec' => $viec, 'phut' => $phut,
			'nguoi' => mb_substr( $nguoi, 0, 190 ), 'ly_do' => mb_substr( (string) $ly_do, 0, 250 ),
			'tao_luc' => current_time( 'mysql' ), 'gui_luc' => null ) );
		if ( 'on' === $viec ) {
			return array( 'ok' => true, 'thong_bao' => 'Đã đặt lệnh cho máy ' . $ma_may . ' chạy '
				. $phut . ' phút. Máy nhận trong ~10 giây.' );
		}
		if ( 'reboot' === $viec ) {
			return array( 'ok' => true, 'thong_bao' => 'Đã đặt lệnh KHỞI ĐỘNG LẠI máy ' . $ma_may
				. '. Máy nhận trong ~10 giây rồi tự khởi động, mất khoảng 30 giây mới gửi nhịp lại.' );
		}
		if ( 'mokhoa' === $viec ) {
			return array( 'ok' => true, 'thong_bao' => 'Đã đặt lệnh MỞ KHOÁ LỖI máy ' . $ma_may
				. '. Máy nhận trong ~10 giây rồi cho quét QR lại.' );
		}
		if ( 'test' === $viec ) {
			return array( 'ok' => true, 'thong_bao' => ( $phut ? 'BẬT' : 'TẮT' )
				. ' chế độ kỹ thuật cho máy ' . $ma_may . '. Máy nhận trong ~10 giây. '
				. ( $phut ? 'Trong chế độ này ghế không khoá lỗi, tự tắt sau 15 phút.' : '' ) );
		}
		return array( 'ok' => true, 'thong_bao' => 'Đã đặt lệnh TẮT máy ' . $ma_may . '.' );
	}

	/** Ghế lấy lệnh cũ nhất chưa gửi. Lấy xong đánh dấu đã gửi. */
	public static function lay_lenh( $ma_may ) {
		global $wpdb;
		$bang = VHG_DB::t( 'lenh' );
		$r = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $bang WHERE ma_may=%s AND gui_luc IS NULL ORDER BY id ASC LIMIT 1",
			(string) $ma_may ), ARRAY_A );
		if ( ! $r ) { return null; }
		$wpdb->update( $bang, array( 'gui_luc' => current_time( 'mysql' ) ), array( 'id' => (int) $r['id'] ) );
		return $r;
	}

	public static function ds_lenh( $gioi_han = 100 ) {
		return VHG_DB::rows( 'SELECT * FROM ' . VHG_DB::t( 'lenh' )
			. ' ORDER BY id DESC LIMIT ' . (int) $gioi_han );
	}
}
