<?php
/**
 * MÃ MUA TRƯỚC — khách mua hôm nay (giá đã giảm), dùng hôm khác, ở BẤT KỲ ghế nào.
 *
 * =============================================================================================
 * BỐN QUYẾT ĐỊNH GỐC, anh Thắng chốt 23/08/2026 — ghi ở đây vì mọi thứ dưới đây đều theo chúng
 * =============================================================================================
 * 1. DOANH THU GHI LÚC BÁN, không phải lúc dùng. Tiền vào két hôm nào thì ghi hôm đó, khớp với
 *    sao kê ngân hàng. Lúc khách dùng mã thì ghế chạy mà KHÔNG ghi thêm đồng nào — nếu không,
 *    cùng một khoản tiền vào sổ hai lần.
 * 2. MỘT MÃ = MỘT LƯỢT ĐÚNG MỆNH GIÁ. Không có số dư, không có tiền lẻ thừa.
 * 3. MÃ KHÔNG HẾT HẠN.
 *    ⚠️ Kéo theo một khoản nợ không bao giờ đóng: mỗi mã đã bán mà chưa dùng là một lượt massage
 *       mình còn nợ khách, và con số đó chỉ cộng lên. Nên `tien_no()` phải có, và màn quản trị
 *       phải hiện nó — một khoản nợ không ai nhìn thấy là khoản nợ sẽ gây bất ngờ.
 * 4. TRA MÃ CẦN SỐ ĐIỆN THOẠI **VÀ** PIN. Số điện thoại KHÔNG phải mật khẩu: người khác đoán ra,
 *    nhìn thấy lúc khách gõ, hoặc chỉ cần thử số quen. Chỉ hỏi số là ai cũng tiêu được mã của
 *    người khác, và mình không có cách nào chứng minh ai đúng ai sai.
 *
 * ⚠️ MÃ KHÔNG ĐỔI RA TIỀN MẶT, và không có đường nào trong tệp này làm chuyện đó. Mã chỉ chạy
 *    được ghế. Mở đường hoàn tiền là mở đường rửa tiền qua một cái ghế massage.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHG_Ma {

	/**
	 * Bảng chữ sinh mã — BỎ HẲN 0/O, 1/I/L, 5/S, 2/Z.
	 *
	 * 🔴 Khách đọc mã từ ảnh chụp màn hình rồi GÕ TAY vào ô trên điện thoại, có khi đọc từ mảnh
	 *    giấy nhân viên viết hộ. Một mã có chữ O và số 0 cạnh nhau là một cuộc cãi nhau ở quầy,
	 *    và người thua luôn là khách. Mất chút không gian mã còn hơn.
	 */
	const CHU = 'ABCDEFGHJKMNPQRTUVWXY346789';
	const DAI = 8;

	/** Tiền tố nội dung chuyển khoản của một đơn MUA MÃ. Xem `VHG_Doc::don_mua()`. */
	const ND_MUA = 'MUA';

	// ===================================================================== chuẩn hoá đầu vào

	/**
	 * Số điện thoại chuẩn — DÙNG CHUNG cho cả lúc bán lẫn lúc tra.
	 *
	 * ⚠️ Chuẩn hoá ở MỘT chỗ. Khách mua lúc gõ "0909 123 456", hôm sau tra lại gõ "+84909123456"
	 *    — cùng một người, mà nếu hai nơi chuẩn hoá khác nhau thì hệ thống nói "không có mã nào",
	 *    và khách đã trả tiền rồi. Đó là lỗi mất tiền thật, không phải lỗi hiển thị.
	 */
	public static function sdt_sach( $v ) {
		$s = preg_replace( '/\D+/', '', (string) $v );
		if ( '' === $s ) { return ''; }
		/* +84 -> 0. Bỏ hẳn số 0 mà không thêm lại là "84909123456" và "0909123456" thành hai
		   người khác nhau. */
		if ( 0 === strpos( $s, '84' ) && strlen( $s ) >= 11 ) { $s = '0' . substr( $s, 2 ); }
		if ( '0' !== substr( $s, 0, 1 ) && 9 === strlen( $s ) ) { $s = '0' . $s; }
		return $s;
	}

	public static function sdt_hop_le( $v ) {
		$s = self::sdt_sach( $v );
		return (bool) preg_match( '/^0\d{8,10}$/', $s );
	}

	public static function pin_hop_le( $v ) {
		return (bool) preg_match( '/^\d{4}$/', trim( (string) $v ) );
	}

	/** Băm PIN. bcrypt chứ không md5/sha: PIN 4 số thì bảng tra sẵn dựng trong vài giây. */
	public static function bam_pin( $pin ) {
		return password_hash( trim( (string) $pin ), PASSWORD_DEFAULT );
	}

	public static function pin_dung( $pin, $bam ) {
		$bam = (string) $bam;
		if ( '' === $bam ) { return false; }
		return password_verify( trim( (string) $pin ), $bam );
	}

	// ===================================================================== bảng giảm giá

	/**
	 * Bảng giảm giá theo mệnh giá: [ mệnh giá => % giảm ].
	 * Chưa khai thì KHÔNG giảm gì — bán đúng giá còn hơn tự ý giảm giá thay chủ.
	 */
	public static function bang_giam() {
		$ds = get_option( 'vhg_ma_giam' );
		$ra = array();
		if ( is_array( $ds ) ) {
			foreach ( $ds as $mg => $pt ) {
				$mg = (int) $mg; $pt = (int) $pt;
				if ( $mg <= 0 ) { continue; }
				/* Chặn 0..70: gõ nhầm một số 0 thành giảm 900% là bán mã với giá âm. Trần 70 vì
				   quá đó thì gần như chắc chắn là gõ nhầm chứ không phải khuyến mãi. */
				$ra[ $mg ] = max( 0, min( 70, $pt ) );
			}
		}
		return $ra;
	}

	public static function giam_cua( $menh_gia ) {
		$b = self::bang_giam();
		$mg = (int) $menh_gia;
		return isset( $b[ $mg ] ) ? (int) $b[ $mg ] : 0;
	}

	/**
	 * Giá bán của một mệnh giá, sau giảm.
	 * Làm tròn XUỐNG bội số 1.000đ: không ai chuyển khoản 84.150đ, và một con số lẻ trên trang
	 * bán hàng làm khách dừng lại tự hỏi mình đọc nhầm chỗ nào.
	 */
	public static function gia_ban( $menh_gia ) {
		$mg = (int) $menh_gia;
		if ( $mg <= 0 ) { return 0; }
		$g = (int) floor( $mg * ( 100 - self::giam_cua( $mg ) ) / 100 );
		$g = (int) ( floor( $g / 1000 ) * 1000 );
		/* Không bao giờ về 0 hay âm, kể cả khi ai đó khai giảm 70% cho mệnh giá 1.000đ. */
		return max( 1000, $g );
	}

	/** Các mệnh giá đang bán, kèm giá đã giảm — nguồn của trang bán mã. */
	public static function ds_menh_gia() {
		$ra = array();
		foreach ( VHG_May::menh_gia() as $g ) {
			$mg = (int) $g['tien'];
			if ( $mg <= 0 ) { continue; }
			$ra[] = array(
				'menh_gia' => $mg,
				'ten'      => (string) $g['ten'],
				'mo_ta'    => (string) $g['mo_ta'],
				'vip'      => ! empty( $g['vip'] ),
				'giam_pt'  => self::giam_cua( $mg ),
				'gia_ban'  => self::gia_ban( $mg ),
			);
		}
		return $ra;
	}

	// ===================================================================== sinh mã

	/**
	 * Sinh một mã CHƯA CÓ TRONG BẢNG.
	 *
	 * ⚠️ Phải hỏi lại cơ sở dữ liệu, không tin vào xác suất. Bảng chữ 27 ký tự, dài 8 thì đụng
	 *    nhau là chuyện hiếm — nhưng "hiếm" nhân với vài năm bán hàng là có thật, và lúc đụng thì
	 *    `INSERT` gãy giữa lúc khách vừa trả tiền xong. Thử vài vòng rồi mới chịu thua.
	 */
	public static function sinh_ma() {
		global $wpdb;
		$t = VHG_DB::t( 'ma' );
		for ( $lan = 0; $lan < 12; $lan++ ) {
			$m = '';
			for ( $i = 0; $i < self::DAI; $i++ ) {
				$m .= self::CHU[ random_int( 0, strlen( self::CHU ) - 1 ) ];
			}
			$co = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t WHERE ma=%s LIMIT 1", $m ) );
			if ( ! $co ) { return $m; }
		}
		return '';
	}

	/** Mã khách gõ vào -> dạng chuẩn. Bỏ gạch nối và khoảng trắng: mã hiện ra có gạch cho dễ đọc. */
	public static function ma_sach( $v ) {
		$s = strtoupper( trim( (string) $v ) );
		$s = preg_replace( '/[^A-Z0-9]/', '', $s );
		return (string) $s;
	}

	/** Mã hiện ra cho người đọc: chia đôi bằng gạch nối. */
	public static function ma_dep( $ma ) {
		$m = self::ma_sach( $ma );
		if ( strlen( $m ) !== self::DAI ) { return $m; }
		return substr( $m, 0, 4 ) . '-' . substr( $m, 4 );
	}

	// ===================================================================== đặt đơn mua

	/**
	 * Khách bấm mua -> tạo ĐƠN, chưa phát mã. Trả về mã đơn để dựng nội dung chuyển khoản.
	 *
	 * ⚠️ GIÁ CHỐT Ở ĐÂY, không tính lại lúc tiền về. Đổi bảng giảm giá giữa chừng mà tính lại là
	 *    khách trả một đằng nhận một nẻo — và bên thiệt luôn là khách, vì họ đã chuyển tiền rồi.
	 */
	public static function dat_don( $sdt, $pin, $menh_gia, $so_luong ) {
		global $wpdb;
		$sdt = self::sdt_sach( $sdt );
		if ( ! self::sdt_hop_le( $sdt ) ) {
			return array( 'ok' => false, 'error' => 'Số điện thoại chưa đúng.' );
		}
		if ( ! self::pin_hop_le( $pin ) ) {
			return array( 'ok' => false, 'error' => 'PIN phải gồm đúng 4 chữ số.' );
		}
		$mg = (int) $menh_gia;
		$sl = max( 1, min( 10, (int) $so_luong ) );
		/* Chỉ bán mệnh giá ĐANG khai. Nhận con số khách gửi lên là mở đường mua mã 1.000.000đ
		   với giá 1.000đ bằng cách sửa gói tin. */
		$hop = false;
		foreach ( self::ds_menh_gia() as $g ) { if ( (int) $g['menh_gia'] === $mg ) { $hop = true; } }
		if ( ! $hop ) { return array( 'ok' => false, 'error' => 'Mệnh giá này không bán.' ); }

		$gia = self::gia_ban( $mg );
		$don = '';
		$t   = VHG_DB::t( 'don_ma' );
		for ( $lan = 0; $lan < 12; $lan++ ) {
			$thu = '';
			for ( $i = 0; $i < 6; $i++ ) { $thu .= self::CHU[ random_int( 0, strlen( self::CHU ) - 1 ) ]; }
			if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t WHERE ma_don=%s LIMIT 1", $thu ) ) ) {
				$don = $thu; break;
			}
		}
		if ( '' === $don ) { return array( 'ok' => false, 'error' => 'Không sinh được mã đơn, thử lại.' ); }

		$wpdb->insert( $t, array(
			'ma_don' => $don, 'sdt' => $sdt, 'pin_bam' => self::bam_pin( $pin ),
			'menh_gia' => $mg, 'gia_ban' => $gia, 'giam_pt' => self::giam_cua( $mg ),
			'so_luong' => $sl, 'phai_tra' => $gia * $sl,
			'tao_luc' => current_time( 'mysql' ), 'xong_luc' => null ) );
		return array( 'ok' => true, 'ma_don' => $don, 'phai_tra' => $gia * $sl,
			'gia_ban' => $gia, 'menh_gia' => $mg, 'so_luong' => $sl,
			'giam_pt' => self::giam_cua( $mg ) );
	}

	public static function don( $ma_don ) {
		global $wpdb;
		$d = strtoupper( trim( (string) $ma_don ) );
		if ( '' === $d ) { return null; }
		return $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . VHG_DB::t( 'don_ma' ) . ' WHERE ma_don=%s LIMIT 1', $d ), ARRAY_A );
	}

	// ===================================================================== phát mã khi tiền về

	/**
	 * Tiền của một đơn đã về -> phát mã.
	 *
	 * 🔴 PHẢI CHỊU ĐƯỢC GỌI LẠI. Webhook của SePay bắn lại là chuyện bình thường, và mỗi lượt bắn
	 *    lại mà phát thêm mã là cho không hàng trăm nghìn đồng. `xong_luc` khác NULL = đã phát
	 *    rồi, trả lại đúng bộ mã cũ chứ không sinh bộ mới.
	 *
	 * ⚠️ KHÔNG ghi doanh thu ở đây. Dòng doanh thu do `VHG_Thu::nhan()` ghi từ chính gói webhook,
	 *    như mọi khoản tiền khác — ghi thêm ở đây là cộng đôi đúng khoản vừa nhận.
	 */
	public static function phat_ma( $ma_don, $ref_ban = '' ) {
		global $wpdb;
		$d = self::don( $ma_don );
		if ( ! $d ) { return array( 'ok' => false, 'error' => 'Không thấy đơn ' . $ma_don . '.' ); }

		if ( ! empty( $d['xong_luc'] ) ) {
			return array( 'ok' => true, 'lap_lai' => true, 'sdt' => $d['sdt'],
				'ma' => self::ds_ma_cua_don( $d['ma_don'] ) );
		}

		$ra = array();
		$sl = max( 1, (int) $d['so_luong'] );
		for ( $i = 0; $i < $sl; $i++ ) {
			$m = self::sinh_ma();
			if ( '' === $m ) { continue; }
			$ok = $wpdb->insert( VHG_DB::t( 'ma' ), array(
				'ma' => $m, 'sdt' => (string) $d['sdt'], 'pin_bam' => (string) $d['pin_bam'],
				'menh_gia' => (int) $d['menh_gia'], 'gia_ban' => (int) $d['gia_ban'],
				'giam_pt' => (int) $d['giam_pt'],
				/* Nối mã về đúng dòng doanh thu đã trả tiền cho nó. Không có sợi dây này thì
				   câu "mã này khách trả bao nhiêu, hôm nào" không trả lời được. */
				'ref_ban' => (string) ( '' !== $ref_ban ? $ref_ban : 'don-' . $d['ma_don'] ),
				'tao_luc' => current_time( 'mysql' ), 'dung_luc' => null, 'huy' => 0 ) );
			if ( $ok ) { $ra[] = $m; }
		}
		$wpdb->update( VHG_DB::t( 'don_ma' ), array( 'xong_luc' => current_time( 'mysql' ) ),
			array( 'ma_don' => $d['ma_don'] ) );
		return array( 'ok' => true, 'lap_lai' => false, 'sdt' => $d['sdt'], 'ma' => $ra );
	}

	public static function ds_ma_cua_don( $ma_don ) {
		global $wpdb;
		$d = self::don( $ma_don );
		if ( ! $d ) { return array(); }
		$r = VHG_DB::rows( $wpdb->prepare(
			'SELECT ma FROM ' . VHG_DB::t( 'ma' ) . ' WHERE ref_ban=%s ORDER BY id ASC',
			'don-' . $d['ma_don'] ) );
		if ( ! $r ) {
			/* Đơn trả tiền qua webhook thì `ref_ban` là ref của giao dịch, không phải "don-…".
			   Rơi về tra theo số điện thoại + mốc giờ phát. */
			$r = VHG_DB::rows( $wpdb->prepare(
				'SELECT ma FROM ' . VHG_DB::t( 'ma' ) . ' WHERE sdt=%s AND tao_luc>=%s'
				. ' ORDER BY id ASC LIMIT %d',
				$d['sdt'], (string) $d['tao_luc'], max( 1, (int) $d['so_luong'] ) ) );
		}
		$ra = array();
		foreach ( $r as $x ) { $ra[] = (string) $x['ma']; }
		return $ra;
	}

	// ===================================================================== tra mã

	/**
	 * Tra mã của một người: cần SỐ ĐIỆN THOẠI **VÀ** PIN.
	 *
	 * 🔴 Số điện thoại KHÔNG phải mật khẩu. Người khác đoán ra, nhìn thấy lúc khách gõ, hoặc chỉ
	 *    cần thử vài số quen. Chỉ hỏi số là ai cũng tiêu được mã của người khác — và lúc cãi
	 *    nhau ở quầy thì mình không có gì để chứng minh ai đúng.
	 *
	 * ⚠️ CÙNG MỘT CÂU BÁO LỖI cho "không có số này" và "sai PIN". Nói rõ "số này có mã nhưng sai
	 *    PIN" là xác nhận giúp người dò rằng họ đã tìm đúng người, và việc còn lại chỉ là 10.000
	 *    lần thử. Hãm thử ở tầng gọi (`VHG_Trang`).
	 */
	public static function tra( $sdt, $pin ) {
		global $wpdb;
		$s = self::sdt_sach( $sdt );
		$loi = array( 'ok' => false, 'error' => 'Không tìm thấy mã nào cho số điện thoại và PIN này.' );
		if ( ! self::sdt_hop_le( $s ) || ! self::pin_hop_le( $pin ) ) { return $loi; }

		$hang = VHG_DB::rows( $wpdb->prepare(
			'SELECT * FROM ' . VHG_DB::t( 'ma' ) . ' WHERE sdt=%s AND huy=0 ORDER BY id DESC LIMIT 200', $s ) );
		if ( ! $hang ) { return $loi; }

		/* PIN băm riêng từng mã (khách có thể đặt PIN khác nhau ở hai lần mua). Khớp được dòng
		   nào thì trả dòng đó — không lấy PIN của dòng đầu làm chuẩn cho cả danh sách. */
		$chua = array(); $roi = array();
		foreach ( $hang as $h ) {
			if ( ! self::pin_dung( $pin, $h['pin_bam'] ) ) { continue; }
			$m = array(
				'ma'       => self::ma_dep( $h['ma'] ),
				'menh_gia' => (int) $h['menh_gia'],
				'gia_ban'  => (int) $h['gia_ban'],
				'giam_pt'  => (int) $h['giam_pt'],
				'tao_luc'  => (string) $h['tao_luc'],
				'dung_luc' => (string) $h['dung_luc'],
				'dung_may' => (string) $h['dung_may'],
			);
			if ( '' === (string) $h['dung_luc'] || null === $h['dung_luc'] ) { $chua[] = $m; }
			else { $roi[] = $m; }
		}
		if ( ! $chua && ! $roi ) { return $loi; }
		return array( 'ok' => true, 'sdt' => $s, 'chua_dung' => $chua, 'da_dung' => $roi );
	}

	// ===================================================================== dùng mã

	/**
	 * Dùng một mã -> xếp cho ghế chạy.
	 *
	 * 🔴 KHÔNG GHI THÊM ĐỒNG DOANH THU NÀO. Tiền đã vào sổ hôm khách MUA mã. Ghi lần nữa ở đây là
	 *    cùng một khoản tiền đếm hai lần, và bảng đối soát sẽ nói dối theo hướng có lợi cho mình
	 *    — kiểu nói dối khó phát hiện nhất.
	 *
	 * ⚠️ ĐÁNH DẤU ĐÃ DÙNG **TRƯỚC**, xếp hàng chờ SAU, và đánh dấu bằng một câu UPDATE có điều
	 *    kiện `dung_luc IS NULL`. Cơ sở dữ liệu là trọng tài: hai người cùng gõ một mã trong cùng
	 *    một giây thì chỉ một câu UPDATE đụng được vào hàng, người kia nhận 0 dòng và bị từ chối.
	 *    Đọc-rồi-mới-ghi ở PHP thì cả hai đều thấy "chưa dùng" và cả hai ghế cùng chạy.
	 */
	public static function dung( $ma, $ma_may ) {
		global $wpdb;
		$m = self::ma_sach( $ma );
		$may = trim( (string) $ma_may );
		if ( '' === $m ) { return array( 'ok' => false, 'error' => 'Chưa nhập mã.' ); }
		if ( '' === $may ) { return array( 'ok' => false, 'error' => 'Chưa biết dùng cho ghế nào.' ); }
		if ( ! VHG_May::may( $may ) ) {
			return array( 'ok' => false, 'error' => 'Không có ghế ' . $may . '.' );
		}

		$t = VHG_DB::t( 'ma' );
		$h = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE ma=%s LIMIT 1", $m ), ARRAY_A );
		if ( ! $h )              { return array( 'ok' => false, 'error' => 'Mã không đúng.' ); }
		if ( (int) $h['huy'] )   { return array( 'ok' => false, 'error' => 'Mã này đã bị huỷ.' ); }
		if ( ! empty( $h['dung_luc'] ) ) {
			return array( 'ok' => false, 'error' => 'Mã này đã dùng lúc ' . $h['dung_luc']
				. ( '' !== (string) $h['dung_may'] ? ' ở ghế ' . $h['dung_may'] : '' ) . '.' );
		}

		$luc = current_time( 'mysql' );
		$n = $wpdb->query( $wpdb->prepare(
			"UPDATE $t SET dung_luc=%s, dung_may=%s WHERE ma=%s AND dung_luc IS NULL AND huy=0",
			$luc, $may, $m ) );
		if ( ! $n ) {
			/* Không đụng được dòng nào = người khác vừa dùng xong trong đúng khoảnh khắc này. */
			return array( 'ok' => false, 'error' => 'Mã này vừa được dùng ở nơi khác.' );
		}

		/* Xếp vào hàng chờ đúng như một lượt đã trả tiền: ghế lấy về và chạy theo MỆNH GIÁ (không
		   phải giá bán). Khách mua mã 100.000đ thì được đúng lượt 100.000đ, dù họ chỉ trả 85.000đ.
		   `ma_lenh` = chính mã đó, nên hàng chờ có khoá duy nhất và gọi lại không xếp hai lần. */
		VHG_May::xep_cho_chay( $may, 'MA' . $m, (int) $h['menh_gia'], 'ma-' . $m,
			'Dùng mã mua trước ' . self::ma_dep( $m ) );

		return array( 'ok' => true, 'ma' => self::ma_dep( $m ), 'ma_may' => $may,
			'menh_gia' => (int) $h['menh_gia'],
			'thong_bao' => 'Đã nhận mã ' . self::ma_dep( $m ) . ' — ghế ' . $may
				. ' sẽ chạy trong ít giây.' );
	}

	// ===================================================================== số liệu

	/**
	 * TIỀN ĐANG NỢ KHÁCH: tổng mệnh giá của mọi mã đã bán mà chưa dùng.
	 *
	 * 🔴 Mã KHÔNG hết hạn (anh Thắng chốt 23/08/2026), nên con số này chỉ cộng lên và không bao
	 *    giờ tự đóng. Nó là một khoản nợ thật: mỗi mã chưa dùng là một lượt massage mình còn nợ.
	 *    Phải hiện ra ở màn quản trị — một khoản nợ không ai nhìn thấy là khoản nợ sẽ gây bất ngờ.
	 *
	 * Tính theo MỆNH GIÁ chứ không theo giá bán: thứ mình nợ là lượt massage, không phải số tiền
	 * khách đã trả.
	 */
	public static function tien_no() {
		global $wpdb;
		$t = VHG_DB::t( 'ma' );
		$r = $wpdb->get_row( "SELECT COUNT(*) AS so_ma, COALESCE(SUM(menh_gia),0) AS tong,"
			. " COALESCE(SUM(gia_ban),0) AS da_thu FROM $t"
			. ' WHERE huy=0 AND dung_luc IS NULL', ARRAY_A );
		return array( 'so_ma' => (int) ( $r ? $r['so_ma'] : 0 ),
			'tong' => (int) ( $r ? $r['tong'] : 0 ),
			'da_thu' => (int) ( $r ? $r['da_thu'] : 0 ) );
	}

	/** Mã trong một kỳ, để đối soát. */
	public static function ds( $ky = 'month', $gioi_han = 300 ) {
		global $wpdb;
		$tu  = VHG_Thu::dau_ky( $ky );
		$sql = 'SELECT * FROM ' . VHG_DB::t( 'ma' ) . ' WHERE 1=1';
		if ( '' !== $tu ) { $sql = $wpdb->prepare( $sql . ' AND tao_luc >= %s', $tu ); }
		$sql .= ' ORDER BY id DESC LIMIT ' . (int) $gioi_han;
		return VHG_DB::rows( $sql );
	}

	/** Tổng của một kỳ: bán mấy mã, thu bao nhiêu, đã dùng mấy. */
	public static function tong( $ky = 'month' ) {
		global $wpdb;
		$t   = VHG_DB::t( 'ma' );
		$tu  = VHG_Thu::dau_ky( $ky );
		$sql = "SELECT COUNT(*) AS ban, COALESCE(SUM(gia_ban),0) AS thu,"
			. " COALESCE(SUM(menh_gia),0) AS menh,"
			. " SUM(CASE WHEN dung_luc IS NOT NULL THEN 1 ELSE 0 END) AS da_dung"
			. " FROM $t WHERE huy=0";
		if ( '' !== $tu ) { $sql = $wpdb->prepare( $sql . ' AND tao_luc >= %s', $tu ); }
		$r = $wpdb->get_row( $sql, ARRAY_A );
		return array( 'ban' => (int) ( $r ? $r['ban'] : 0 ), 'thu' => (int) ( $r ? $r['thu'] : 0 ),
			'menh' => (int) ( $r ? $r['menh'] : 0 ),
			'da_dung' => (int) ( $r ? $r['da_dung'] : 0 ) );
	}
}
