<?php
/**
 * VÍ — GÓI NẠP VÀ SỐ DƯ CỦA KHÁCH
 *
 * Anh Thắng 23/08/2026: *"bán mã giảm giá theo gói nạp. Nạp 100k được 120k. Nạp 200 được 300k.
 * Nạp 500 được 800k"*.
 *
 * ════════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 KHOẢN CHÊNH LÀ NỢ, KHÔNG PHẢI DOANH THU.
 *
 *    Khách nạp 100.000đ và nhận 120.000đ giá trị. Tiền vào tài khoản là 100.000đ — nhưng cửa
 *    hàng vừa hứa trả lại 120.000đ dịch vụ. Ngày nạp, cửa hàng KHÔNG lãi 100.000đ; nó nhận
 *    100.000đ tiền mặt và ghi nợ 120.000đ.
 *
 *    Doanh thu thật chỉ phát sinh LÚC KHÁCH TIÊU, và nó là phần khách tiêu chứ không phải phần
 *    khách trả. Nên:
 *      · lúc NẠP  -> ghi vào sổ thu (tiền thật đã vào) VÀ cộng nợ ví
 *      · lúc TIÊU -> trừ nợ ví, KHÔNG ghi thêm một đồng doanh thu nào
 *
 *    Ghi doanh thu ở cả hai đầu là đếm hai lần — đúng cái bẫy mà bộ thử của plugin này canh từ
 *    đầu ("đường tiền không đếm hai lần"). `tieu()` KHÔNG gọi VHG_Thu::ghi(), có chủ đích.
 *
 * ════════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 HAI CỘT SỐ DƯ.
 *
 *    `so_du_dung` tiêu được ngay. `so_du_cho` còn trong hạn chờ (mua trước 5 ngày mới dùng —
 *    cùng lý do với mã: giảm giá là để đổi lấy việc trả tiền TRƯỚC, nạp xong tiêu ngay thì
 *    không có gì được đổi).
 *
 *    Gộp một cột là mất khả năng nói *"anh có 120k, nhưng ngày mai mới tiêu được"* — mà đó
 *    đúng là câu khách sẽ hỏi khi ví báo có tiền mà ghế không chạy.
 *
 * ════════════════════════════════════════════════════════════════════════════════════════════
 * ⚠️ TIÊU TIỀN LUÔN QUA `UPDATE ... WHERE so_du_dung >= x`.
 *
 *    Đọc số dư ra PHP, so sánh, rồi ghi lại là sai: hai người cùng bấm ở hai ghế trong cùng
 *    một khoảnh khắc thì CẢ HAI đọc thấy đủ tiền, cả hai đều trừ, và ví âm. Chỉ tầng SQL mới
 *    chặn được, vì chỉ nó nhìn thấy hai lượt ghi đó là hai.
 *
 *    Cùng một luật với `VHG_Ma::dung()`, và ở đây còn quan trọng hơn: mã dùng hai lần thì mất
 *    một lượt, ví âm thì mất bao nhiêu tuỳ khách bấm nhanh cỡ nào.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHG_Vi {

	/** Trần một lần nạp. Không phải để chặn khách sộp — để chặn gói tin bị sửa. */
	const NAP_TOI_DA = 20000000;

	// ═════════════════════════════════════════════════════════ gói nạp (cấu hình)

	/**
	 * Bảng gói nạp anh Thắng khai: nạp bao nhiêu, nhận bao nhiêu.
	 *
	 * ⚠️ `get_option` trả về `false` khi CHƯA khai bao giờ, và `(array) false` ra `array()` chứ
	 *    không nổ — nhưng đừng dựa vào đó, nói thẳng ra cho người đọc sau khỏi phải thử.
	 */
	public static function goi_nap() {
		$o  = get_option( 'vhg_goi_nap' );
		$ra = array();
		foreach ( ( is_array( $o ) ? $o : array() ) as $g ) {
			$nap  = (int) ( isset( $g['nap'] ) ? $g['nap'] : 0 );
			$nhan = (int) ( isset( $g['nhan'] ) ? $g['nhan'] : 0 );
			if ( $nap < 1000 || $nhan < $nap ) { continue; }
			$ra[] = array(
				'nap'     => $nap,
				'nhan'    => $nhan,
				'them'    => $nhan - $nap,
				/* Phần trăm LỢI so với tiền bỏ ra — đó là con số khách so sánh, không phải
				   phần trăm giảm giá. Nạp 100k được 120k là "lợi 20%", nói "giảm 16,7%" thì
				   đúng về số học mà không ai hiểu. */
				'loi_pt'  => (int) round( ( $nhan - $nap ) * 100 / $nap ),
			);
		}
		usort( $ra, function ( $a, $b ) { return $a['nap'] - $b['nap']; } );
		return $ra;
	}

	/** Lưu bảng gói nạp. Trả về thông báo cho màn quản trị, không tự ý im lặng bỏ dòng hỏng. */
	public static function luu_goi_nap( $ds ) {
		$ra   = array();
		$thay = array();
		foreach ( (array) $ds as $g ) {
			$g    = (array) $g;
			$nap  = (int) preg_replace( '/\D+/', '', (string) ( isset( $g['nap'] ) ? $g['nap'] : '' ) );
			$nhan = (int) preg_replace( '/\D+/', '', (string) ( isset( $g['nhan'] ) ? $g['nhan'] : '' ) );
			if ( $nap < 1000 && $nhan < 1000 ) { continue; }        // dòng trống, bỏ qua im lặng
			if ( $nap < 1000 ) {
				return array( 'ok' => false, 'error' => 'Gói nạp phải từ 1.000đ trở lên.' );
			}
			if ( $nap > self::NAP_TOI_DA ) {
				return array( 'ok' => false, 'error' => 'Gói nạp tối đa '
					. number_format( self::NAP_TOI_DA, 0, ',', '.' ) . 'đ.' );
			}
			/* 🔴 NHẬN ÍT HƠN NẠP thì đó không phải khuyến mãi, đó là móc túi khách. Chặn ở đây
			   chứ đừng để một lần gõ nhầm thành một tuần bán hàng sai. */
			if ( $nhan < $nap ) {
				return array( 'ok' => false, 'error' => 'Gói nạp '
					. number_format( $nap, 0, ',', '.' ) . 'đ đang cho nhận '
					. number_format( $nhan, 0, ',', '.' ) . 'đ — nhận ít hơn nạp thì khách lỗ.' );
			}
			if ( isset( $thay[ $nap ] ) ) {
				return array( 'ok' => false, 'error' => 'Hai gói cùng nạp '
					. number_format( $nap, 0, ',', '.' ) . 'đ. Khách thấy hai nút giống hệt nhau.' );
			}
			$thay[ $nap ] = 1;
			$ra[] = array( 'nap' => $nap, 'nhan' => $nhan );
		}
		usort( $ra, function ( $a, $b ) { return $a['nap'] - $b['nap']; } );
		update_option( 'vhg_goi_nap', $ra );
		return array( 'ok' => true, 'thong_bao' => $ra
			? 'Đã lưu ' . count( $ra ) . ' gói nạp.'
			: 'Đã tắt bán gói nạp (không còn gói nào).' );
	}

	/** Gói nạp ĐANG bán ứng với số tiền này, hoặc null. Nhận con số khách gửi lên là mở đường
	    nạp 1.000đ nhận 10.000.000đ bằng cách sửa gói tin. */
	public static function goi( $nap ) {
		$n = (int) $nap;
		foreach ( self::goi_nap() as $g ) { if ( (int) $g['nap'] === $n ) { return $g; } }
		return null;
	}

	// ═════════════════════════════════════════════════════════ ví

	/** Ví của một số điện thoại, hoặc null. */
	public static function vi( $sdt ) {
		global $wpdb;
		$s = VHG_Ma::sdt_sach( $sdt );
		if ( '' === $s ) { return null; }
		$t = VHG_DB::t( 'vi' );
		$r = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE sdt=%s LIMIT 1", $s ), ARRAY_A );
		return $r ? $r : null;
	}

	/**
	 * Mở ví nếu chưa có. Trả về hàng ví.
	 *
	 * ⚠️ PIN CHỈ ĐẶT LÚC MỞ VÍ. Ví đã có rồi mà lượt nạp sau gửi PIN khác thì KHÔNG ghi đè —
	 *    ghi đè là ai biết số điện thoại của khách cũng đổi được PIN ví của người ta, chỉ cần
	 *    nạp thêm 1.000đ. Muốn đổi PIN thì đi đường `doi_pin()`, đường đó hỏi PIN cũ.
	 *
	 * 🔴 NHẬN PIN ĐÃ BĂM SẴN, không nhận PIN thô.
	 *    Người gọi duy nhất là `nap()`, và ở đó PIN đã băm từ lúc đặt đơn — băm lại là băm một
	 *    chuỗi băm. Bản đầu nhận PIN thô rồi gọi `mo($s, '', '')` để "tạo ví trống", nhưng
	 *    `VHG_Ma::bam_pin('')` KHÔNG trả về chuỗi rỗng — nó trả về một chuỗi băm hợp lệ CỦA
	 *    chuỗi rỗng. Nên nhánh "chưa có PIN thì gán PIN thật" không bao giờ chạy, ví mở ra mang
	 *    PIN của chuỗi rỗng, và khách nạp tiền xong không đăng nhập được vào ví của chính mình.
	 *    Bộ thử bắt được ngay ở phép "tiêu được".
	 */
	public static function mo( $sdt, $pin_bam, $cc_bam = '' ) {
		global $wpdb;
		$s = VHG_Ma::sdt_sach( $sdt );
		if ( '' === $s ) { return null; }
		$co = self::vi( $s );
		if ( $co ) {
			/* Ví cũ chưa khai căn cước mà nay khách khai thì NHẬN — đó là thêm một đường lấy
			   lại PIN, không phải thay một khoá đang có. */
			if ( '' === (string) $co['cc_bam'] && '' !== (string) $cc_bam ) {
				$wpdb->update( VHG_DB::t( 'vi' ),
					array( 'cc_bam' => (string) $cc_bam, 'sua_luc' => current_time( 'mysql' ) ),
					array( 'sdt' => $s ) );
				$co = self::vi( $s );
			}
			return $co;
		}
		$wpdb->insert( VHG_DB::t( 'vi' ), array(
			'sdt' => $s, 'pin_bam' => (string) $pin_bam, 'cc_bam' => (string) $cc_bam,
			'so_du_dung' => 0, 'so_du_cho' => 0, 'da_nap' => 0, 'da_tieu' => 0, 'khoa' => 0,
			'tao_luc' => current_time( 'mysql' ), 'sua_luc' => current_time( 'mysql' ) ) );
		return self::vi( $s );
	}

	/**
	 * Chuyển các khoản NẠP đã tới hạn từ `so_du_cho` sang `so_du_dung`.
	 *
	 * 🔴 LẬT CỜ TRƯỚC, CHUYỂN TIỀN SAU, VÀ CHỈ NGƯỜI LẬT ĐƯỢC CỜ MỚI CHUYỂN.
	 *    `UPDATE ... WHERE id=x AND da_chin=0` chỉ đụng được dòng ở đúng MỘT lượt gọi. Hai
	 *    lượt chạy song song (khách bấm ở hai ghế cùng lúc) thì lượt thua nhận 0 dòng và không
	 *    cộng gì. Làm ngược lại — cộng tiền rồi mới lật cờ — là cộng hai lần.
	 *
	 * Gọi LƯỜI: chạy mỗi lần đọc hoặc tiêu ví, thay vì hẹn giờ. Hẹn giờ trên WordPress phụ
	 * thuộc có người vào trang hay không, mà quán vắng thì tiền của khách kẹt lại trong hạn chờ
	 * dù đã tới ngày.
	 */
	public static function chin( $sdt ) {
		global $wpdb;
		$s = VHG_Ma::sdt_sach( $sdt );
		if ( '' === $s ) { return 0; }
		$ts  = VHG_DB::t( 'vi_so' );
		$tv  = VHG_DB::t( 'vi' );
		$nay = current_time( 'mysql' );
		$ds  = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, thay_doi FROM $ts
			  WHERE sdt=%s AND da_chin=0 AND dung_duoc_tu IS NOT NULL AND dung_duoc_tu<=%s",
			$s, $nay ), ARRAY_A );
		$tong = 0;
		foreach ( (array) $ds as $d ) {
			$n = $wpdb->query( $wpdb->prepare(
				"UPDATE $ts SET da_chin=1 WHERE id=%d AND da_chin=0", (int) $d['id'] ) );
			if ( ! $n ) { continue; }          // lượt khác vừa lật xong — để họ cộng
			$so = (int) $d['thay_doi'];
			$wpdb->query( $wpdb->prepare(
				"UPDATE $tv SET so_du_dung=so_du_dung+%d, so_du_cho=so_du_cho-%d, sua_luc=%s
				  WHERE sdt=%s", $so, $so, $nay, $s ) );
			$tong += $so;
		}
		return $tong;
	}

	/**
	 * Số dư: tiêu được ngay / còn chờ / tổng, kèm mốc gần nhất tiền chờ chín.
	 * Luôn chạy `chin()` trước — đọc ra một con số cũ là khách nhìn thấy tiền vẫn kẹt.
	 */
	public static function so_du( $sdt ) {
		global $wpdb;
		$s = VHG_Ma::sdt_sach( $sdt );
		$khong = array( 'co_vi' => false, 'dung' => 0, 'cho' => 0, 'tong' => 0,
			'cho_toi' => '', 'con_cho' => 0, 'khoa' => false );
		if ( '' === $s ) { return $khong; }
		self::chin( $s );
		$v = self::vi( $s );
		if ( ! $v ) { return $khong; }
		$dung = (int) $v['so_du_dung'];
		$cho  = (int) $v['so_du_cho'];
		$moc  = '';
		if ( $cho > 0 ) {
			$ts  = VHG_DB::t( 'vi_so' );
			$moc = (string) $wpdb->get_var( $wpdb->prepare(
				"SELECT MIN(dung_duoc_tu) FROM $ts WHERE sdt=%s AND da_chin=0", $s ) );
		}
		return array(
			'co_vi'   => true,
			'dung'    => $dung,
			'cho'     => $cho,
			'tong'    => $dung + $cho,
			'cho_toi' => $moc,
			'con_cho' => '' !== $moc ? max( 0, strtotime( $moc ) - current_time( 'timestamp' ) ) : 0,
			'khoa'    => ! empty( $v['khoa'] ),
		);
	}

	// ═════════════════════════════════════════════════════════ đặt đơn nạp

	/**
	 * Khách bấm chọn gói nạp -> tạo đơn chờ trả tiền.
	 *
	 * Chốt `nhan_tien` NGAY LÚC ĐẶT, không tính lại lúc tiền về: giữa lúc khách bấm và lúc tiền
	 * vào, chủ có thể đã sửa bảng gói nạp — tính lại là khách trả một đằng nhận một nẻo. Cùng
	 * một luật với đơn mua mã.
	 */
	public static function dat_don( $sdt, $pin, $nap, $cc = '' ) {
		global $wpdb;
		$s = VHG_Ma::sdt_sach( $sdt );
		if ( ! VHG_Ma::sdt_hop_le( $s ) ) {
			return array( 'ok' => false, 'error' => 'Số điện thoại chưa đúng.' );
		}
		if ( ! VHG_Ma::pin_hop_le( $pin ) ) {
			return array( 'ok' => false, 'error' => 'PIN phải gồm đúng 4 chữ số.' );
		}
		$g = self::goi( $nap );
		if ( ! $g ) { return array( 'ok' => false, 'error' => 'Gói nạp này không bán.' ); }

		/* 🔴 VÍ ĐÃ CÓ thì PIN phải KHỚP. Không có chốt này thì ai biết số điện thoại của khách
		   cũng nạp 1.000đ kèm PIN mới, rồi tiêu sạch số dư của người ta. Đây là chỗ tiền thật,
		   không phải chỗ tiện tay. */
		$v = self::vi( $s );
		if ( $v && ! VHG_Ma::pin_dung( $pin, (string) $v['pin_bam'] ) ) {
			return array( 'ok' => false, 'error' => 'Số điện thoại này đã có ví, nhưng PIN chưa đúng. '
				. 'Nhập đúng PIN của ví, hoặc báo nhân viên để lấy lại PIN.' );
		}
		if ( $v && ! empty( $v['khoa'] ) ) {
			return array( 'ok' => false, 'error' => 'Ví này đang tạm khoá. Anh/chị báo nhân viên giúp.' );
		}

		$t   = VHG_DB::t( 'don_ma' );
		$don = '';
		for ( $lan = 0; $lan < 12; $lan++ ) {
			$thu = '';
			for ( $i = 0; $i < 6; $i++ ) {
				$thu .= VHG_Ma::CHU[ random_int( 0, strlen( VHG_Ma::CHU ) - 1 ) ];
			}
			if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t WHERE ma_don=%s LIMIT 1", $thu ) ) ) {
				$don = $thu; break;
			}
		}
		if ( '' === $don ) { return array( 'ok' => false, 'error' => 'Không sinh được mã đơn, thử lại.' ); }

		$cho = VHG_Ma::cho_ngay_mac_dinh();
		$wpdb->insert( $t, array(
			'ma_don' => $don, 'sdt' => $s, 'pin_bam' => VHG_Ma::bam_pin( $pin ),
			'cc_bam' => VHG_Ma::bam_cc( $cc ),
			'loai' => 'nap', 'nhan_tien' => (int) $g['nhan'],
			/* `menh_gia` giữ số tiền NẠP để màn đối soát cũ đọc được đơn này mà không phải sửa;
			   `phai_tra` mới là thứ webhook đối chiếu. */
			'menh_gia' => (int) $g['nap'], 'gia_ban' => (int) $g['nap'],
			'giam_pt' => (int) $g['loi_pt'], 'cho_ngay' => $cho,
			'so_luong' => 1, 'phai_tra' => (int) $g['nap'],
			'tao_luc' => current_time( 'mysql' ), 'xong_luc' => null ) );

		return array( 'ok' => true, 'ma_don' => $don, 'loai' => 'nap',
			'phai_tra' => (int) $g['nap'], 'nhan_tien' => (int) $g['nhan'],
			'them' => (int) $g['them'], 'loi_pt' => (int) $g['loi_pt'], 'cho_ngay' => $cho );
	}

	// ═════════════════════════════════════════════════════════ tiền về -> cộng ví

	/**
	 * Webhook báo đã nhận tiền -> cộng vào ví.
	 *
	 * ⚠️ PHẢI CHỊU ĐƯỢC GỌI LẠI. Webhook bắn lại là chuyện bình thường (bên gửi không nhận được
	 *    phản hồi thì gửi tiếp). Chốt là `xong_luc` trên đơn: đã có thì trả về kết quả cũ và
	 *    KHÔNG cộng thêm đồng nào. Giống hệt `VHG_Ma::phat_ma()`.
	 */
	public static function nap( $ma_don, $ref_ban = '' ) {
		global $wpdb;
		$d = VHG_Ma::don( $ma_don );
		if ( ! $d ) { return array( 'ok' => false, 'error' => 'Không thấy đơn ' . $ma_don . '.' ); }
		if ( 'nap' !== (string) ( isset( $d['loai'] ) ? $d['loai'] : '' ) ) {
			return array( 'ok' => false, 'error' => 'Đơn ' . $ma_don . ' không phải đơn nạp ví.' );
		}
		$s = VHG_Ma::sdt_sach( $d['sdt'] );

		if ( ! empty( $d['xong_luc'] ) ) {
			$sd = self::so_du( $s );
			return array( 'ok' => true, 'lap_lai' => true, 'sdt' => $s,
				'nhan_tien' => (int) $d['nhan_tien'], 'so_du' => $sd );
		}

		$so = (int) $d['nhan_tien'];
		if ( $so <= 0 ) { return array( 'ok' => false, 'error' => 'Đơn nạp không có số tiền nhận.' ); }

		/* Ví chưa có thì mở NGAY BẰNG PIN đã chốt trên đơn — chứ không mở trống rồi vá sau.
		   Ví đã có thì `dat_don()` đã bắt PIN phải khớp, nên `mo()` chỉ trả về ví cũ và không
		   đụng gì vào PIN của nó. */
		self::mo( $s, (string) $d['pin_bam'],
			(string) ( isset( $d['cc_bam'] ) ? $d['cc_bam'] : '' ) );

		$cho_ngay = (int) ( isset( $d['cho_ngay'] ) ? $d['cho_ngay'] : 0 );
		$moc      = VHG_Ma::dung_duoc_tu( current_time( 'mysql' ), $cho_ngay );
		$cho_ngay = '' === $moc ? 0 : $cho_ngay;                 // không có mốc = chín ngay

		$ref = '' !== (string) $ref_ban ? (string) $ref_ban : 'don-' . $d['ma_don'];
		self::ghi_so( $s, $so, $cho_ngay > 0 ? 'nap-cho' : 'nap', $ref, '',
			'Nạp ' . number_format( (int) $d['phai_tra'], 0, ',', '.' ) . 'đ nhận '
				. number_format( $so, 0, ',', '.' ) . 'đ', '', $moc );

		$wpdb->update( VHG_DB::t( 'don_ma' ), array( 'xong_luc' => current_time( 'mysql' ) ),
			array( 'ma_don' => $d['ma_don'] ) );

		$sd = self::so_du( $s );
		return array( 'ok' => true, 'sdt' => $s, 'nhan_tien' => $so,
			'cho_ngay' => $cho_ngay, 'dung_duoc_tu' => $moc, 'so_du' => $sd );
	}

	/**
	 * Ghi MỘT dòng sổ và chỉnh số dư tương ứng. Đây là đường DUY NHẤT được đụng vào `vi`.
	 *
	 * 🔴 Cộng tiền thì đi lối này. TRỪ tiền thì đi `tru()` — vì trừ phải có chốt "đủ tiền mới
	 *    trừ" ở tầng SQL, mà chốt đó không gộp chung được với đường cộng.
	 */
	protected static function ghi_so( $sdt, $so_tien, $loai, $ref, $ma_may, $ghi_chu, $ai = '', $moc = '' ) {
		global $wpdb;
		$s   = VHG_Ma::sdt_sach( $sdt );
		$so  = (int) $so_tien;
		$nay = current_time( 'mysql' );
		$cho = ( $so > 0 && '' !== (string) $moc );

		if ( $so > 0 ) {
			$wpdb->query( $wpdb->prepare(
				"UPDATE " . VHG_DB::t( 'vi' ) . " SET "
				. ( $cho ? "so_du_cho=so_du_cho+%d" : "so_du_dung=so_du_dung+%d" )
				. ", da_nap=da_nap+%d, sua_luc=%s WHERE sdt=%s", $so, $so, $nay, $s ) );
		}
		$v  = self::vi( $s );
		$du = $v ? (int) $v['so_du_dung'] + (int) $v['so_du_cho'] : 0;

		$wpdb->insert( VHG_DB::t( 'vi_so' ), array(
			'sdt' => $s, 'thay_doi' => $so, 'so_du_sau' => $du, 'loai' => (string) $loai,
			'dung_duoc_tu' => $cho ? $moc : null, 'da_chin' => $cho ? 0 : 1,
			'ref' => (string) $ref, 'ma_may' => (string) $ma_may,
			'ghi_chu' => mb_substr( (string) $ghi_chu, 0, 255 ), 'ai' => (string) $ai,
			'luc' => $nay ) );
		return (int) $wpdb->insert_id;
	}

	// ═════════════════════════════════════════════════════════ tiêu

	/**
	 * Trừ tiền ví — CHỐT DUY NHẤT chống ví âm.
	 *
	 * Trả về true nếu trừ được. `$wpdb->query` trả về SỐ DÒNG ĐỤNG ĐƯỢC: 0 nghĩa là điều kiện
	 * `so_du_dung >= x` không thoả TẠI THỜI ĐIỂM GHI — tức là hoặc thiếu tiền, hoặc người khác
	 * vừa tiêu xong trong đúng khoảnh khắc đó. Hai trường hợp ấy xử như nhau.
	 */
	protected static function tru( $sdt, $so_tien ) {
		global $wpdb;
		$s  = VHG_Ma::sdt_sach( $sdt );
		$so = (int) $so_tien;
		if ( $so <= 0 ) { return false; }
		$n = $wpdb->query( $wpdb->prepare(
			"UPDATE " . VHG_DB::t( 'vi' ) . "
			    SET so_du_dung=so_du_dung-%d, da_tieu=da_tieu+%d, sua_luc=%s
			  WHERE sdt=%s AND khoa=0 AND so_du_dung>=%d",
			$so, $so, current_time( 'mysql' ), $s, $so ) );
		return (bool) $n;
	}

	/**
	 * Khách quét mã QR tại ghế, nhập số điện thoại + PIN + chọn gói -> ghế chạy.
	 *
	 * ⚠️ TRỪ TIỀN TRƯỚC, XẾP GHẾ SAU, VÀ XẾP HỎNG THÌ TRẢ TIỀN LẠI.
	 *    Xếp trước rồi mới trừ là ghế chạy xong mà chưa chắc trừ được — mất tiền thật. Trừ
	 *    trước thì trường hợp xấu nhất là tiền bị giữ vài dòng lệnh rồi hoàn, và có một dòng
	 *    sổ nói rõ chuyện gì đã xảy ra.
	 *
	 * 🔴 KHÔNG ghi doanh thu ở đây. Doanh thu đã ghi lúc NẠP (tiền thật vào tài khoản lúc đó).
	 *    Ghi thêm ở đây là đếm hai lần — xem chú thích đầu tệp.
	 */
	public static function tieu( $sdt, $pin, $menh_gia, $ma_may ) {
		$s   = VHG_Ma::sdt_sach( $sdt );
		$may = trim( (string) $ma_may );
		$mg  = (int) $menh_gia;

		if ( '' === $may ) { return array( 'ok' => false, 'error' => 'Chưa biết dùng cho ghế nào.' ); }
		if ( ! VHG_May::may( $may ) ) {
			return array( 'ok' => false, 'error' => 'Không có ghế ' . $may . '.' );
		}
		/* Chỉ tiêu được vào MỆNH GIÁ ĐANG KHAI. Nhận con số khách gửi lên là mở đường chạy một
		   lượt 100.000đ mà chỉ trừ 1.000đ bằng cách sửa gói tin. */
		$hop = null;
		foreach ( VHG_Ma::ds_menh_gia() as $g ) {
			if ( (int) $g['menh_gia'] === $mg ) { $hop = $g; }
		}
		if ( ! $hop ) { return array( 'ok' => false, 'error' => 'Gói này không có trên hệ thống.' ); }

		$v = self::vi( $s );
		/* ⚠️ MỘT CÂU LỖI CHO CẢ HAI TRƯỜNG HỢP (chưa có ví / sai PIN). Nói "số này chưa có ví"
		   là biến ô đăng nhập thành máy dò xem số nào đã mua hàng. */
		if ( ! $v || ! VHG_Ma::pin_dung( $pin, (string) $v['pin_bam'] ) ) {
			return array( 'ok' => false, 'error' => 'Số điện thoại hoặc PIN chưa đúng.' );
		}
		if ( ! empty( $v['khoa'] ) ) {
			return array( 'ok' => false, 'error' => 'Ví này đang tạm khoá. Anh/chị báo nhân viên giúp.' );
		}

		$sd = self::so_du( $s );                                  // kèm chín tiền tới hạn
		if ( $sd['dung'] < $mg ) {
			$loi = 'Số dư tiêu được còn ' . number_format( $sd['dung'], 0, ',', '.' ) . 'đ, chưa đủ '
				. number_format( $mg, 0, ',', '.' ) . 'đ.';
			/* Có tiền mà chưa tiêu được thì NÓI RA, đừng để khách tưởng ví bị mất tiền. */
			if ( $sd['cho'] > 0 ) {
				$loi .= ' Còn ' . number_format( $sd['cho'], 0, ',', '.' ) . 'đ đang trong hạn chờ'
					. ( $sd['con_cho'] > 0
						? ', dùng được sau ' . VHG_Ma::doc_con_cho( $sd['con_cho'] ) : '' ) . '.';
			}
			return array( 'ok' => false, 'thieu_tien' => true, 'so_du' => $sd, 'error' => $loi );
		}

		if ( ! self::tru( $s, $mg ) ) {
			return array( 'ok' => false, 'error' => 'Số dư vừa thay đổi ở nơi khác. Anh/chị thử lại giúp.' );
		}

		$lenh = 'VI' . strtoupper( substr( md5( $s . '|' . $may . '|' . microtime( true ) ), 0, 12 ) );
		$id   = self::ghi_so( $s, -$mg, 'tieu', $lenh, $may,
			'Chạy ghế ' . $may . ' gói ' . number_format( $mg, 0, ',', '.' ) . 'đ' );

		$xep = VHG_May::xep_cho_chay( $may, $lenh, $mg, 'vi-' . $lenh,
			'Tiêu số dư ví ' . VHG_Ma::sdt_che( $s ) );
		if ( empty( $xep ) ) {
			/* 🔴 XẾP KHÔNG ĐƯỢC THÌ HOÀN NGAY, và hoàn qua sổ chứ không sửa lén con số. Khách
			   mở sổ ra phải thấy đủ hai dòng: trừ, rồi hoàn. */
			self::ghi_so( $s, $mg, 'hoan', $lenh, $may, 'Hoàn: ghế không nhận được lệnh' );
			return array( 'ok' => false, 'error' => 'Ghế ' . $may
				. ' chưa nhận được lệnh. Số dư đã hoàn lại, anh/chị thử lại giúp.' );
		}

		$sau = self::so_du( $s );
		return array( 'ok' => true, 'ma_may' => $may, 'menh_gia' => $mg, 'so_so' => $id,
			'so_du' => $sau,
			'thong_bao' => 'Đã trừ ' . number_format( $mg, 0, ',', '.' ) . 'đ — ghế ' . $may
				. ' sẽ chạy trong ít giây. Số dư còn '
				. number_format( $sau['dung'], 0, ',', '.' ) . 'đ.' );
	}

	// ═════════════════════════════════════════════════════════ nhân viên / quản trị

	/** Sổ ví của một khách, mới nhất trước. */
	public static function ds_so( $sdt, $gioi_han = 100 ) {
		global $wpdb;
		$s = VHG_Ma::sdt_sach( $sdt );
		if ( '' === $s ) { return array(); }
		$ts = VHG_DB::t( 'vi_so' );
		return VHG_DB::rows( $wpdb->prepare(
			"SELECT * FROM $ts WHERE sdt=%s ORDER BY id DESC LIMIT %d",
			$s, max( 1, min( 500, (int) $gioi_han ) ) ) );
	}

	/** Danh sách ví còn tiền, nhiều nhất trước — để nhìn ra khoản nợ nằm ở đâu. */
	public static function ds_vi( $gioi_han = 200 ) {
		global $wpdb;
		$tv = VHG_DB::t( 'vi' );
		return VHG_DB::rows( $wpdb->prepare(
			"SELECT * FROM $tv WHERE so_du_dung>0 OR so_du_cho>0
			  ORDER BY (so_du_dung+so_du_cho) DESC LIMIT %d",
			max( 1, min( 1000, (int) $gioi_han ) ) ) );
	}

	/**
	 * 🔴 TỔNG NỢ: số dư khách đã trả tiền nhưng CHƯA tiêu.
	 *
	 * Đây là tiền cửa hàng đã cầm nhưng chưa làm gì để đổi lấy nó. Màn quản trị phải hiện con
	 * số này cạnh doanh thu, vì nhìn doanh thu một mình thì tháng bán gói nạp nào cũng đẹp.
	 */
	public static function tong_no() {
		global $wpdb;
		$tv = VHG_DB::t( 'vi' );
		$r  = $wpdb->get_row( "SELECT COALESCE(SUM(so_du_dung),0) d, COALESCE(SUM(so_du_cho),0) c,
			COUNT(*) n FROM $tv WHERE so_du_dung>0 OR so_du_cho>0", ARRAY_A );
		$d = (int) ( isset( $r['d'] ) ? $r['d'] : 0 );
		$c = (int) ( isset( $r['c'] ) ? $r['c'] : 0 );
		return array( 'dung' => $d, 'cho' => $c, 'tong' => $d + $c,
			'so_vi' => (int) ( isset( $r['n'] ) ? $r['n'] : 0 ) );
	}

	/** Nhân viên tra ví hộ khách quên PIN. KHÔNG được gọi từ trang của khách — xem VHG_Ma::tra_nhan_vien. */
	public static function tra_nhan_vien( $sdt ) {
		$s = VHG_Ma::sdt_sach( $sdt );
		if ( ! VHG_Ma::sdt_hop_le( $s ) ) {
			return array( 'ok' => false, 'error' => 'Số điện thoại chưa đúng.' );
		}
		$sd = self::so_du( $s );
		if ( ! $sd['co_vi'] ) {
			return array( 'ok' => false, 'error' => 'Số này chưa có ví.' );
		}
		return array( 'ok' => true, 'sdt' => $s, 'so_du' => $sd, 'so' => self::ds_so( $s, 30 ) );
	}

	/**
	 * Chỉnh tay số dư (đền bù, sửa sai, thu hồi). LUÔN qua sổ, luôn có lý do và tên người làm.
	 *
	 * ⚠️ Trừ tay cũng phải qua `tru()` — không được để số dư âm chỉ vì người chỉnh gõ nhầm.
	 */
	public static function chinh_tay( $sdt, $so_tien, $ly_do, $ai ) {
		$s  = VHG_Ma::sdt_sach( $sdt );
		$so = (int) $so_tien;
		$ld = trim( (string) $ly_do );
		if ( ! VHG_Ma::sdt_hop_le( $s ) ) {
			return array( 'ok' => false, 'error' => 'Số điện thoại chưa đúng.' );
		}
		if ( 0 === $so ) { return array( 'ok' => false, 'error' => 'Chưa nhập số tiền.' ); }
		/* 🔴 BẮT BUỘC có lý do. Một dòng "+500.000đ" không lý do trong sổ tiền là thứ không ai
		   giải thích được sau ba tháng, kể cả người vừa bấm. */
		if ( '' === $ld ) { return array( 'ok' => false, 'error' => 'Phải ghi lý do chỉnh số dư.' ); }
		if ( ! self::vi( $s ) ) { return array( 'ok' => false, 'error' => 'Số này chưa có ví.' ); }

		if ( $so < 0 ) {
			if ( ! self::tru( $s, -$so ) ) {
				$sd = self::so_du( $s );
				return array( 'ok' => false, 'error' => 'Số dư tiêu được chỉ còn '
					. number_format( $sd['dung'], 0, ',', '.' ) . 'đ, không trừ được '
					. number_format( -$so, 0, ',', '.' ) . 'đ.' );
			}
			self::ghi_so( $s, $so, 'tay', '', '', $ld, (string) $ai );
		} else {
			self::ghi_so( $s, $so, 'tay', '', '', $ld, (string) $ai );
		}
		return array( 'ok' => true, 'so_du' => self::so_du( $s ),
			'thong_bao' => 'Đã chỉnh ' . ( $so > 0 ? '+' : '' )
				. number_format( $so, 0, ',', '.' ) . 'đ cho ' . VHG_Ma::sdt_che( $s ) . '.' );
	}

	/** Khoá / mở ví. Dùng khi nghi ngờ, để giữ tiền lại mà không xoá gì. */
	public static function khoa( $sdt, $bat, $ly_do, $ai ) {
		global $wpdb;
		$s = VHG_Ma::sdt_sach( $sdt );
		if ( ! self::vi( $s ) ) { return array( 'ok' => false, 'error' => 'Số này chưa có ví.' ); }
		$wpdb->update( VHG_DB::t( 'vi' ),
			array( 'khoa' => $bat ? 1 : 0, 'sua_luc' => current_time( 'mysql' ) ),
			array( 'sdt' => $s ) );
		self::ghi_so( $s, 0, $bat ? 'khoa' : 'mo-khoa', '', '',
			trim( (string) $ly_do ), (string) $ai );
		return array( 'ok' => true, 'thong_bao' => ( $bat ? 'Đã khoá ví ' : 'Đã mở ví ' )
			. VHG_Ma::sdt_che( $s ) . '.' );
	}

	/** Đổi PIN ví — hỏi PIN cũ. Quên hẳn thì đi đường căn cước ở `lay_lai_pin()`. */
	public static function doi_pin( $sdt, $pin_cu, $pin_moi ) {
		global $wpdb;
		$s = VHG_Ma::sdt_sach( $sdt );
		$v = self::vi( $s );
		if ( ! $v || ! VHG_Ma::pin_dung( $pin_cu, (string) $v['pin_bam'] ) ) {
			return array( 'ok' => false, 'error' => 'Số điện thoại hoặc PIN chưa đúng.' );
		}
		if ( ! VHG_Ma::pin_hop_le( $pin_moi ) ) {
			return array( 'ok' => false, 'error' => 'PIN mới phải gồm đúng 4 chữ số.' );
		}
		$wpdb->update( VHG_DB::t( 'vi' ),
			array( 'pin_bam' => VHG_Ma::bam_pin( $pin_moi ), 'sua_luc' => current_time( 'mysql' ) ),
			array( 'sdt' => $s ) );
		return array( 'ok' => true, 'thong_bao' => 'Đã đổi PIN ví.' );
	}

	/**
	 * Lấy lại PIN bằng số căn cước — CHỈ với ví đã khai căn cước lúc mở.
	 * Cùng luật với `VHG_Ma::lay_lai_pin()`: ví không khai thì không có đường này, và nói thẳng.
	 */
	public static function lay_lai_pin( $sdt, $cc, $pin_moi ) {
		global $wpdb;
		$s = VHG_Ma::sdt_sach( $sdt );
		$v = self::vi( $s );
		if ( ! VHG_Ma::pin_hop_le( $pin_moi ) ) {
			return array( 'ok' => false, 'error' => 'PIN mới phải gồm đúng 4 chữ số.' );
		}
		if ( ! $v || '' === (string) $v['cc_bam'] ) {
			return array( 'ok' => false, 'error' => 'Ví này không khai số căn cước lúc mở, '
				. 'nên không lấy lại PIN qua mạng được. Anh/chị báo nhân viên giúp.' );
		}
		if ( ! VHG_Ma::cc_dung( $cc, (string) $v['cc_bam'] ) ) {
			return array( 'ok' => false, 'error' => 'Số điện thoại hoặc căn cước chưa đúng.' );
		}
		$wpdb->update( VHG_DB::t( 'vi' ),
			array( 'pin_bam' => VHG_Ma::bam_pin( $pin_moi ), 'sua_luc' => current_time( 'mysql' ) ),
			array( 'sdt' => $s ) );
		self::ghi_so( $s, 0, 'doi-pin', '', '', 'Lấy lại PIN bằng số căn cước' );
		return array( 'ok' => true, 'thong_bao' => 'Đã đặt PIN mới cho ví.' );
	}
}
