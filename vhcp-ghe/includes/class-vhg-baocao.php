<?php
/**
 * BÁO CÁO DOANH THU THEO CƠ SỞ — port từ web Apps Script "POSH v3 · THU TIỀN (nhân viên)".
 *
 * Anh Thắng 27/08/2026: đưa app thu-tiền-nhập-báo-cáo vào web ghế (tab trên /ghe).
 *
 * 🔴 CÔNG THỨC (giữ y app gốc, TRỪ "Thực thu" — xem cập nhật 29/08/2026 bên dưới):
 *      actual   = (chỉ số sau − chỉ số trước) × đơn_vị
 *      tiền mặt = actual − QR
 *      tổng     = tiền mặt + QR
 *    Server TỰ TÍNH và TỰ ÉP chỉ số trước — KHÔNG tin số client gửi.
 *    App gốc cứng ×10000; ở đây dùng VHG_Quy::don_vi() để KHỚP với chốt ca máy trạm.
 *
 * 🔴 29/08/2026 — CỘT "TĂNG/GIẢM" ĐỔI THÀNH "THỰC THU": GHI ĐÈ, KHÔNG CÒN CỘNG DỒN.
 *    Anh Thắng: *"cột này là cột thực thu"* rồi *"khi nhập thực thu ở cột này, tiền cộng sẽ lấy
 *    theo cột này"*. Trước đây cột "Tăng/Giảm" (`dieu_chinh`) CỘNG vào công thức tiền mặt
 *    (`actual − qr + điều_chỉnh`) — nay đổi hẳn: có gõ ở cột này thì tiền mặt LẤY ĐÚNG số đó
 *    (`$r0['actualOverride']`, xem `luu()`), không còn tính theo actual−qr nữa; bỏ trống thì vẫn
 *    tính theo công thức y như chưa từng có cột này. Áp dụng cho MỌI hàng, không chỉ hàng bất
 *    thường (chỉ số ngược / công thức ra âm) như cơ chế "Thực thu ghi đè" ban đầu — hàng bất
 *    thường vẫn BẮT BUỘC phải có (cộng thêm lý do), hàng thường thì đây là lựa chọn. Cột
 *    `dieu_chinh` trong CSDL vẫn còn (lưu lại số đã gõ để đối chiếu), chỉ là không dùng nó trong
 *    phép tính `tien_mat` ở `tinh_()` nữa — số ghi đè áp riêng ở `luu()`/`sua_dong()` sau đó.
 *
 * 🔴 "DÙNG CHUNG BẢNG CHỈ SỐ" với chốt ca: chi_so_truoc() lấy chỉ số sau gần nhất TRƯỚC ngày báo
 *    cáo từ CẢ `bc_dong` LẪN `chot` — một dòng thời gian chỉ số duy nhất, không đếm đôi.
 *
 * 🔴 1 BÁO CÁO / CƠ SỞ / NGÀY: UNIQUE(coso_key, ngay) ở tầng CSDL; gửi lại = cập nhật.
 *
 * ════════════════════════════════════════════════════════════════════════════════════════════
 * PHÂN QUYỀN BẰNG PIN — DÙNG CHUNG PIN NHÂN SỰ K&H, KHÔNG dùng token /ghe.
 *
 * Anh Thắng 27/08/2026: *"mỗi nhân viên 1 PIN, gán cho cơ sở rồi; đăng nhập thấy cơ sở mình"*,
 * rồi chốt: *"Nhân viên lấy từ hệ thống nhân sự qua, các bạn đã có PIN sẵn"*.
 *
 * → PIN LÀ DANH TÍNH CHUNG, VÀ NGUỒN GỐC LÀ NHÂN SỰ. Nhân viên đã có PIN chấm công (danh sách
 *   người dùng của VHG_Auth — bảng dùng chung `{prefix}vhcp_cfg` hàng `CH_NguoiDung`, hoặc danh
 *   sách riêng của plugin). Đăng nhập báo cáo = nhập ĐÚNG PIN đó. Không bắt Admin nhập lại PIN.
 *   Cơ sở nhìn thấy = cột "cơ sở" trong hồ sơ nhân sự của người đó.
 *
 * → BẢNG `bc_pin` GIỜ LÀ BẢNG NGOẠI LỆ (không bắt buộc), để Admin:
 *      · cho một người phạm vi KHÁC hồ sơ nhân sự (thêm cơ sở, hoặc giới hạn theo từng GHẾ), hoặc
 *      · KHOÁ một PIN khỏi trang báo cáo (thêm hàng với active=0) mà không đụng hồ sơ nhân sự.
 *   Có hàng bc_pin cho PIN nào thì hàng đó THẮNG hồ sơ nhân sự cho riêng PIN ấy (kể cả để tắt).
 *
 * ⛔ REPO CÔNG KHAI → KHÔNG hardcode PIN trong mã. PIN nằm ở nhân sự / bc_pin, không ở nguồn.
 * ⛔ FAIL CLOSED: PIN sai/rỗng ⇒ KHÔNG trả dữ liệu (không lộ danh mục/doanh thu toàn công ty).
 * ════════════════════════════════════════════════════════════════════════════════════════════
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHG_BaoCao {

	const GIO_SUA = 24;   // nhân viên sửa được trong ngần này giờ kể từ lúc gửi

	public static function don_vi() { return VHG_Quy::don_vi(); }

	/** Bỏ dấu + bỏ ký tự lạ + hoa, so tên cơ sở bất kể cách gõ ("GO Dĩ An" ≡ "godian"). */
	public static function squash( $s ) {
		return preg_replace( '/[^A-Z0-9]/', '', strtoupper( remove_accents( (string) $s ) ) );
	}

	// ══════════════════════════════════════════════════════════════════ PIN & PHẠM VI

	/** Tách chuỗi "a; b, c" → ['a','b','c'] (ngăn bởi phẩy hoặc chấm phẩy). */
	private static function tach_( $v ) {
		return array_values( array_filter( array_map( 'trim', preg_split( '/[;,]/', (string) $v ) ) ) );
	}

	/**
	 * Chuẩn hoá PIN để SO KHỚP với hồ sơ nhân sự — mượn luật của VHG_Auth::pin_sach:
	 * cắt đuôi ".0" của bảng tính TRƯỚC, rồi mới bỏ ký tự lạ (thứ tự ngược là sai âm thầm).
	 */
	private static function pin_chuan_( $v ) {
		if ( class_exists( 'VHG_Auth' ) && method_exists( 'VHG_Auth', 'pin_sach' ) ) {
			return VHG_Auth::pin_sach( $v );
		}
		$s = trim( (string) $v );
		if ( preg_match( '/^(\d+)\.0*$/', $s, $m ) ) { $s = $m[1]; }
		return preg_replace( '/\D+/', '', $s );
	}

	/** Dựng phạm vi từ tên + chuỗi cơ sở + chuỗi ghế. */
	private static function pham_vi_( $ten, $coso_str, $ghe_str ) {
		$coso = self::tach_( $coso_str );
		$ck = array();
		foreach ( $coso as $c ) { $ck[ self::squash( $c ) ] = true; }
		return array( 'ten' => (string) $ten, 'coso' => $coso,
			'coso_key' => $ck, 'ghe' => self::tach_( $ghe_str ) );
	}

	/**
	 * Thông tin 1 PIN: [ 'ten', 'coso'=>[...], 'coso_key'=>[...], 'ghe'=>[...] ] hoặc null.
	 *
	 * THỨ TỰ TRA (xem đầu tệp): (1) bảng ngoại lệ `bc_pin` THẮNG nếu có hàng cho PIN này —
	 * kể cả active=0 nghĩa là Admin KHOÁ tường minh ⇒ trả null; (2) không có ngoại lệ thì tra
	 * PIN trong hồ sơ NHÂN SỰ (VHG_Auth::users), phạm vi = cột "cơ sở" của người đó.
	 */
	public static function pin_info( $pin ) {
		global $wpdb;
		$pin_raw = trim( (string) $pin );
		if ( '' === $pin_raw ) { return null; }

		// (1) Ngoại lệ do Admin khai — thắng hồ sơ nhân sự cho riêng PIN này (kể cả để tắt).
		$r = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . VHG_DB::t( 'bc_pin' ) . ' WHERE pin=%s LIMIT 1', $pin_raw ), ARRAY_A );
		if ( $r ) {
			if ( 1 !== (int) $r['active'] ) { return null; }   // Admin khoá PIN này khỏi báo cáo
			return self::pham_vi_( $r['ten'], $r['coso'], $r['ghe'] );
		}

		// (2) PIN nhân sự sẵn có — "các bạn đã có PIN sẵn", không cần Admin nhập lại.
		if ( class_exists( 'VHG_Auth' ) ) {
			$pin_c = self::pin_chuan_( $pin_raw );
			if ( '' !== $pin_c ) {
				$users = VHG_Auth::users();
				if ( ! is_wp_error( $users ) ) {
					foreach ( (array) $users as $u ) {
						if ( '' === (string) $u['pin'] ) { continue; }
						if ( self::pin_chuan_( $u['pin'] ) === $pin_c ) {
							return self::pham_vi_( $u['ten'], $u['coso'], '' );
						}
					}
				}
			}
		}
		return null;
	}

	/** Bản ghi (cơ sở, ghế) có thuộc phạm vi PIN không. $q null ⇒ false (fail closed). */
	public static function trong_pham_vi( $q, $coso, $ma = '' ) {
		if ( ! $q ) { return false; }
		$co_coso = ! empty( $q['coso_key'] );
		$co_ghe  = ! empty( $q['ghe'] );
		if ( ! $co_coso && ! $co_ghe ) { return true; }                 // PIN không giới hạn = toàn quyền
		if ( $co_ghe && '' !== (string) $ma && in_array( (string) $ma, $q['ghe'], true ) ) { return true; }
		if ( $co_coso && isset( $q['coso_key'][ self::squash( $coso ) ] ) ) { return true; }
		return false;
	}

	/** Ghế trong phạm vi PIN: [ ['ma','ten','coso'], ... ].
	 *
	 * 🔴 DUY NHẤT chỗ lọc cờ `may.an` (ghế đã dọn/điều chuyển — anh Thắng 29/08/2026). Trang quản
	 * trị (bảng "Máy (ghế)", đối chiếu, kế toán…) đọc thẳng `VHG_May::ds_may()` không qua đây, nên
	 * vẫn thấy đủ ghế kể cả đã dọn — chỉ MÀN NHÂN VIÊN NHẬP CHỈ SỐ (dùng đúng hàm này) mất ghế đó. */
	public static function ds_ghe( $q ) {
		$ra = array();
		foreach ( VHG_May::ds_may() as $m ) {
			if ( ! empty( $m['an'] ) ) { continue; }
			$coso = (string) ( isset( $m['coso_ten'] ) ? $m['coso_ten'] : '' );
			if ( ! self::trong_pham_vi( $q, $coso, (string) $m['ma'] ) ) { continue; }
			$ra[] = array(
				'ma'   => (string) $m['ma'],
				'ten'  => (string) ( '' !== (string) $m['ten_khai'] ? $m['ten_khai'] : $m['ma'] ),
				'coso' => $coso,
			);
		}
		/* Xếp theo cơ sở rồi TÊN GHẾ dạng người-đọc: VHM-1, VHM-2, … VHM-10 (không phải VHM-1,
		   VHM-10, VHM-2). `strnatcasecmp` hiểu số trong tên nên "-2" đứng trước "-10"; sắp ở NGUỒN
		   để cả bảng nhập chỉ số lẫn ô xổ ghế đều cùng thứ tự, khỏi mỗi màn một kiểu. */
		usort( $ra, function( $a, $b ) {
			return strnatcasecmp( (string) $a['coso'], (string) $b['coso'] )
				?: strnatcasecmp( (string) $a['ten'], (string) $b['ten'] );
		} );
		return $ra;
	}

	// ══════════════════════════════════════════════════════════════════ CHỈ SỐ (dùng chung)

	/**
	 * CHỈ SỐ TRƯỚC = chỉ số sau gần nhất có ngày < $ngay, lấy CẢ `bc_dong` LẪN `chot`; có mốc reset
	 * (`may.moc_chiso`) hiệu lực ≤ $ngay và mới hơn thì lấy mốc. Trả (int) hoặc null (lần đầu).
	 */
	/**
	 * Chi tiết đầy đủ của chi_so_truoc(): CẢ giá trị LẪN NGÀY của mốc đó. Ngày mốc dùng để giới
	 * hạn đúng khoảng "lượt kích ghế từ xa" mà báo cáo NÀY bao phủ (xem kich_xa_tru() bên dưới)
	 * — không có ngày mốc thì không biết trừ lượt kích từ đâu tới đâu, dễ đếm đôi với kỳ trước.
	 */
	private static function chi_so_truoc_ct_( $ma_may, $ngay, $toi = false ) {
		global $wpdb;
		$ma = (string) $ma_may;
		$ngay = self::ngay_( $ngay );
		if ( '' === $ma || '' === $ngay ) { return array( 'cs' => null, 'ngay' => '' ); }
		$found_cs = null; $found_d = '';
		/* $toi=true → tính CẢ chỉ số sau CỦA CHÍNH NGÀY ĐÓ (các lần thu trước trong ngày) làm mốc,
		   để "thu lần nữa" nối tiếp lần trước; sắp lan DESC để lấy đúng lần thu MỚI NHẤT trong ngày.
		   $toi=false (mặc định) giữ nguyên: chỉ lấy chỉ số sau của ngày TRƯỚC ngày báo cáo. */
		$ss = $toi ? '<=' : '<';
		$r1 = $wpdb->get_row( $wpdb->prepare(
			'SELECT chi_so_sau cs, ngay d FROM ' . VHG_DB::t( 'bc_dong' )
			. " WHERE ma_may=%s AND ngay $ss %s AND chi_so_sau IS NOT NULL ORDER BY ngay DESC, lan DESC, chi_so_sau DESC LIMIT 1",
			$ma, $ngay ), ARRAY_A );
		if ( $r1 ) { $found_cs = (int) $r1['cs']; $found_d = (string) $r1['d']; }
		$r2 = $wpdb->get_row( $wpdb->prepare(
			'SELECT chi_so cs, DATE(tao_luc) d FROM ' . VHG_DB::t( 'chot' )
			. ' WHERE ma_may=%s AND DATE(tao_luc) < %s ORDER BY d DESC, chi_so DESC LIMIT 1',
			$ma, $ngay ), ARRAY_A );
		if ( $r2 && (string) $r2['d'] > $found_d ) { $found_cs = (int) $r2['cs']; $found_d = (string) $r2['d']; }
		$mo = $wpdb->get_row( $wpdb->prepare(
			'SELECT moc_chiso cs, moc_chiso_ngay d FROM ' . VHG_DB::t( 'may' ) . ' WHERE ma=%s LIMIT 1', $ma ), ARRAY_A );
		if ( $mo && null !== $mo['cs'] && $mo['d'] ) {
			$od = self::ngay_( $mo['d'] );
			if ( $od && $od <= $ngay && ( '' === $found_d || $found_d < $od ) ) {
				return array( 'cs' => (int) $mo['cs'], 'ngay' => $od );
			}
		}
		return array( 'cs' => null === $found_cs ? null : (int) $found_cs, 'ngay' => $found_d );
	}

	public static function chi_so_truoc( $ma_may, $ngay, $toi = false ) {
		return self::chi_so_truoc_ct_( $ma_may, $ngay, $toi )['cs'];
	}

	public static function lay_chiso_truoc( $codes, $ngay, $toi = false ) {
		$out = array();
		foreach ( (array) $codes as $c ) { $out[ (string) $c ] = self::chi_so_truoc( $c, $ngay, $toi ); }
		return $out;
	}

	/**
	 * NỐI DÒNG THỜI GIAN — anh Thắng 29/08/2026: *"nhập vào ngày nằm giữa 2 ngày thì chỉ số tự
	 * hiểu và chèn vào giữa… chỉ số cũ ngày hôm sau tự nhảy chỉnh lại"*.
	 *
	 * Sau khi lưu / sửa / bỏ / đổi ngày một ghế ở ngày D, LẦN ĐỌC KẾ TIẾP của đúng ghế đó (ngày >
	 * D, có chỉ số sau) phải lấy chỉ số sau vừa chốt của D làm chỉ số trước. Chỉ đụng ĐÚNG một hàng
	 * kế tiếp: chi_so_truoc = "chỉ số sau gần nhất TRƯỚC ngày đó", nên chèn ở D chỉ đổi mốc của lần
	 * đọc đầu tiên sau D; các lần sau nữa mốc vẫn là hàng liền trước chúng, không cần dây chuyền.
	 *
	 * Lấy mốc mới bằng chi_so_truoc() SỐNG (đã tính cả hàng vừa lưu / đã bỏ hàng vừa xoá) nên dùng
	 * chung cho cả chèn, sửa, lẫn bỏ ghế khỏi báo cáo.
	 *
	 * ⚠️ KHÔNG tự ghi tiền RÁC. Hàng kế tiếp là "Thực thu ghi đè" (đã chốt tay), hoặc nối xong hoá
	 *    bất thường (sau < trước mới / tiền mặt ra âm) → CHỈ đổi chi_so_truoc, GIỮ tiền cũ, ghim ghi
	 *    chú để kế toán kiểm; không im lặng đổi số nộp. Hàng thường thì tính lại actual/tiền/tổng.
	 *    (kich_tien không lưu ở bc_dong nên coi như 0 khi nối lại — lượt kích hiếm; nếu có, số lệch
	 *    nhẹ và lộ ra ở đối chiếu, không âm thầm sai sổ.)
	 */
	public static function noi_tiep( $ma_may, $ngay ) {
		global $wpdb;
		$ma = (string) $ma_may; $d = self::ngay_( $ngay );
		if ( '' === $ma || '' === $d ) { return; }
		$r = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . VHG_DB::t( 'bc_dong' )
			. ' WHERE ma_may=%s AND ngay > %s AND chi_so_sau IS NOT NULL ORDER BY ngay ASC LIMIT 1',
			$ma, $d ), ARRAY_A );
		if ( $r ) { self::ap_moc_( $r ); }
	}

	/**
	 * Tính lại chỉ số trước của ĐÚNG hàng tại (ghế, ngày) từ dòng thời gian sống. Dùng khi CHÍNH
	 * báo cáo đó vừa bị đổi ngày (doi_ngay) — chỉ số trước của nó phải theo mốc mới, không phải mốc
	 * của ngày cũ. chi_so_truoc() luôn nhìn ngày < ngày hàng nên không tự lấy chính nó.
	 */
	public static function noi_hang( $ma_may, $ngay ) {
		global $wpdb;
		$ma = (string) $ma_may; $d = self::ngay_( $ngay );
		if ( '' === $ma || '' === $d ) { return; }
		$r = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . VHG_DB::t( 'bc_dong' )
			. ' WHERE ma_may=%s AND ngay=%s AND chi_so_sau IS NOT NULL ORDER BY id ASC LIMIT 1',
			$ma, $d ), ARRAY_A );
		if ( $r ) { self::ap_moc_( $r ); }
	}

	/** Áp mốc chỉ số trước SỐNG cho một hàng bc_dong đã lấy về, theo đúng chính sách an toàn tiền. */
	private static function ap_moc_( $r ) {
		global $wpdb;
		$ma = (string) $r['ma_may'];
		$moi = self::chi_so_truoc( $ma, (string) $r['ngay'] );   // mốc sống (ngày < ngày hàng này)
		if ( null === $moi ) { return; }
		if ( (int) $r['chi_so_truoc'] === (int) $moi ) { return; }   // không đổi
		$sau     = (int) $r['chi_so_sau'];
		$ghi_de  = ( false !== mb_strpos( (string) $r['ghi_chu'], 'Thực thu ghi đè' ) );
		$hoa_bt  = ( $sau < (int) $moi );
		$co      = '↺ Chỉ số trước tự nối lại ' . (int) $r['chi_so_truoc'] . '→' . (int) $moi
			. ' (chèn/sửa ngày trước đó) — kế toán kiểm Thực thu';
		if ( $ghi_de || $hoa_bt ) {
			$note = trim( (string) $r['ghi_chu'] );
			if ( false === mb_strpos( $note, '↺ Chỉ số trước tự nối lại' ) ) {
				$note = mb_substr( trim( ( '' !== $note ? $note . ' · ' : '' ) . $co ), 0, 250 );
			}
			$wpdb->update( VHG_DB::t( 'bc_dong' ),
				array( 'chi_so_truoc' => (int) $moi, 'ghi_chu' => $note ), array( 'id' => (int) $r['id'] ) );
			return;
		}
		$rr = array( 'chi_so_truoc' => (int) $moi, 'chi_so_sau' => $sau,
			'qr' => (int) $r['qr'], 'dieu_chinh' => (int) $r['dieu_chinh'] );
		self::tinh_( $rr );
		if ( $rr['tien_mat'] < 0 ) {   // nối xong hoá âm → giữ tiền cũ, ghim ghi chú
			$note = trim( (string) $r['ghi_chu'] );
			if ( false === mb_strpos( $note, '↺ Chỉ số trước tự nối lại' ) ) {
				$note = mb_substr( trim( ( '' !== $note ? $note . ' · ' : '' ) . $co ), 0, 250 );
			}
			$wpdb->update( VHG_DB::t( 'bc_dong' ),
				array( 'chi_so_truoc' => (int) $moi, 'ghi_chu' => $note ), array( 'id' => (int) $r['id'] ) );
			return;
		}
		$wpdb->update( VHG_DB::t( 'bc_dong' ), array(
			'chi_so_truoc' => (int) $moi, 'actual' => $rr['actual'],
			'tien_mat' => $rr['tien_mat'], 'tong' => $rr['tong'] ), array( 'id' => (int) $r['id'] ) );
	}

	// ══════════════════════════════════════════════════════════════════ kích ghế từ xa (trừ chùa)

	/**
	 * TRỪ LƯỢT KÍCH GHẾ TỪ XA KHỎI DOANH THU.
	 *
	 * 🔴 Anh Thắng 28/08/2026: *"khi nhập chỉ số sau, máy sẽ đối chiếu thêm với hệ thống kích
	 *    ghế từ xa, để trừ số lượt đã kích ghế cho khách ra. Nếu ghế nào có kích thì báo, không
	 *    có thì thôi."* — mỗi lượt Hotline/Admin bấm Bật tay (bảng `lenh`, xem VHG_May) là CHO
	 *    KHÔNG một lượt, không có tiền đi kèm, nhưng chỉ số cơ trên ghế vẫn nhảy y như khách trả
	 *    tiền thật. Không trừ ra là doanh thu bị TÍNH THỪA đúng bằng số lượt cho không đó.
	 *
	 * 🔴 QUY ĐỔI đã chốt (anh Thắng chọn): *"mỗi lượt kích = đúng 1 đơn vị chỉ số (như giá tiền
	 *    mỗi lần)"* — dùng ĐÚNG giá/phút RIÊNG của ghế đó (VHG_May::ty_le_cua, không phải giá
	 *    chung) nhân số lượt, ra thẳng SỐ TIỀN cần trừ. Trừ tiền trực tiếp khỏi `actual`, không
	 *    quy ngược ra "mấy đơn vị chỉ số" rồi nhân lại — quy hai chiều qua chia lấy tròn là chỗ
	 *    vài trăm đồng biến mất mà không ai giải thích được.
	 *
	 * ⚠️ KHOẢNG THỜI GIAN đối chiếu = đúng quãng chỉ số báo cáo NÀY bao phủ: SAU mốc chỉ số
	 *    trước (loại trừ, tránh đếm đôi với báo cáo trước) tới HẾT ngày báo cáo — xem
	 *    dem_luot_kich() bên VHG_May.
	 */
	public static function kich_xa_tru( $ma_may, $ngay_bc ) {
		$ma  = (string) $ma_may;
		$den = self::ngay_( $ngay_bc );
		$ra  = array( 'so_luot' => 0, 'tien' => 0 );
		if ( '' === $ma || '' === $den ) { return $ra; }
		$ct = self::chi_so_truoc_ct_( $ma, $den );
		$so_luot = class_exists( 'VHG_May' ) ? VHG_May::dem_luot_kich( $ma, $ct['ngay'], $den ) : 0;
		if ( $so_luot <= 0 ) { return $ra; }
		$m   = VHG_May::may( $ma );
		$gia = (int) VHG_May::ty_le_cua( $m ? $m : array() )['gia'];
		return array( 'so_luot' => $so_luot, 'tien' => $gia * $so_luot );
	}

	// ══════════════════════════════════════════════════════════════════ tiện ích

	public static function ngay_( $v ) {
		$s = trim( (string) $v );
		return preg_match( '/^(\d{4})-(\d{2})-(\d{2})/', $s, $m ) ? $m[0] : '';
	}
	private static function songuyen_( $v ) { return ( '' === $v || null === $v ) ? null : (int) $v; }
	private static function tinh_( &$r ) {
		$dv = self::don_vi();
		$before = self::songuyen_( isset( $r['chi_so_truoc'] ) ? $r['chi_so_truoc'] : null );
		$after  = self::songuyen_( isset( $r['chi_so_sau'] ) ? $r['chi_so_sau'] : null );
		$qr = (int) ( isset( $r['qr'] ) ? $r['qr'] : 0 );
		/* 🔴 `dieu_chinh` KHÔNG CÒN CỘNG VÀO TIỀN MẶT — anh Thắng 29/08/2026: "cột này là cột
		   thực thu" + "khi nhập thực thu ở cột này, tiền cộng sẽ lấy theo cột này". Cột trước
		   đây "Tăng/Giảm" cộng thẳng vào công thức; nay đổi thành "Thực thu" — GHI ĐÈ hẳn tiền
		   mặt khi có gõ (xem khối `actualOverride` ở `luu()`), không cộng dồn nữa. Vẫn giữ cột
		   `dieu_chinh` trong CSDL để lưu lại số đã gõ (đối chiếu/báo cáo cũ), chỉ là không dùng
		   nó trong phép tính `tien_mat` ở đây nữa — số ghi đè do `luu()` áp SAU khi gọi hàm này. */
		$adj = (int) ( isset( $r['dieu_chinh'] ) ? $r['dieu_chinh'] : 0 );
		/* Trừ tiền lượt kích ghế từ xa (cho không) ra khỏi actual — xem kich_xa_tru() ở trên.
		   luu() đổ số này vào $r['kich_tien'] TRƯỚC khi gọi tinh_(); không có lượt kích nào (đa
		   số trường hợp) thì mặc định 0, công thức y hệt trước giờ. */
		$kich_tien = (int) ( isset( $r['kich_tien'] ) ? $r['kich_tien'] : 0 );
		$actual = ( null === $before || null === $after ) ? 0 : ( $after - $before ) * $dv - $kich_tien;
		$r['chi_so_truoc'] = $before; $r['chi_so_sau'] = $after;
		$r['qr'] = $qr; $r['dieu_chinh'] = $adj;
		$r['kich_tien'] = $kich_tien;
		$r['actual'] = $actual;
		$r['tien_mat'] = $actual - $qr;
		$r['tong'] = $r['tien_mat'] + $qr;
		return $r;
	}
	private static function ngay_sai_( $ngay ) {
		$d = self::ngay_( $ngay );
		if ( '' === $d ) { return 'Ngày báo cáo không đọc được.'; }
		$hn = current_time( 'Y-m-d' );
		$so = (int) round( ( strtotime( $d ) - strtotime( $hn ) ) / 86400 );
		if ( $so > 1 )    { return 'Ngày báo cáo ' . $d . ' ở tương lai (hôm nay ' . $hn . ').'; }
		if ( $so < -366 ) { return 'Ngày ' . $d . ' cách hôm nay quá 1 năm — nhờ kế toán nhập trực tiếp.'; }
		return '';
	}
	public static function dang_khoa( $coso, $ngay ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . VHG_DB::t( 'bc_khoa' ) . ' WHERE coso_key=%s AND ngay=%s',
			self::squash( $coso ), self::ngay_( $ngay ) ) ) > 0;
	}

	// ══════════════════════════════════════════════════════════════════ ĐĂNG NHẬP / BOOTSTRAP

	public static function boot( $pin ) {
		global $wpdb;
		$q = self::pin_info( $pin );
		if ( ! $q ) { return array( 'ok' => false, 'pinOk' => false, 'error' => 'PIN không đúng hoặc đã ngừng dùng.' ); }
		$ghe = self::ds_ghe( $q );
		$cs = array();
		foreach ( $ghe as $g ) { if ( '' !== $g['coso'] ) { $cs[ $g['coso'] ] = true; } }
		$khoa = $wpdb->get_results( 'SELECT coso, ngay FROM ' . VHG_DB::t( 'bc_khoa' ), ARRAY_A );
		$khoa_loc = array();
		foreach ( (array) $khoa as $k ) {
			if ( self::trong_pham_vi( $q, $k['coso'] ) ) {
				$khoa_loc[] = array( 'coso' => $k['coso'], 'ngay' => self::ngay_( $k['ngay'] ) );
			}
		}
		return array( 'ok' => true, 'pinOk' => true, 'staff' => $q['ten'],
			'today' => current_time( 'Y-m-d' ), 'don_vi' => self::don_vi(),
			'coso' => array_keys( $cs ), 'ghe' => $ghe, 'khoa' => $khoa_loc,
			'chamCongUrl' => self::cham_cong_url() );
	}

	/**
	 * Đường vào THẲNG trang chấm công online (trạm PIN của plugin Chấm Công) — anh Thắng
	 * 30/08/2026: *"bổ sung link chấm công online cho nhân viên thao tác nhanh khi làm xong và
	 * chấm công đi về ... bấm là vào thẳng trang check in luôn"*.
	 *
	 * ⚠️ Y HỆT LUẬT GỌI CHÉO CỦA CẢ HỆ (`tools/test/kiem-goi-cheo.php` ở nhánh chi phí): gác
	 *    `class_exists && method_exists` NGAY TẠI ĐÂY, không giả định plugin Chấm Công có mặt —
	 *    hai plugin cài ĐỘC LẬP nhau. `VHCC_Tram::url()` đọc đúng đường dẫn đã cấu hình (có thể
	 *    khác mặc định nếu Admin đổi ở màn cấu hình), nên ưu tiên gọi thẳng; thiếu plugin thì lùi
	 *    về đường mặc định `VHCC_Tram::SLUG_MD` (‘cham-cong-online’) — vẫn còn một đường vào hợp
	 *    lý thay vì trơ nút bấm.
	 */
	private static function cham_cong_url() {
		if ( class_exists( 'VHCC_Tram' ) && method_exists( 'VHCC_Tram', 'url' ) ) {
			return VHCC_Tram::url();
		}
		return home_url( '/cham-cong-online/' );
	}

		/**
	 * Đăng nhập báo cáo THẲNG bằng danh tính đã xác thực qua token /ghe — không hỏi lại PIN.
	 *
	 * Anh Thắng 28/08/2026: *"2 trang này là 1 cơ sở dữ liệu, tại sao qua trang báo cáo lại phải
	 * đăng nhập lại"*. Đúng — PIN báo cáo với PIN /ghe là CÙNG một PIN nhân sự (xem đầu tệp).
	 *
	 * ⚠️ KHÔNG SUY PHẠM VI THẲNG TỪ `$ai` (tên+cơ sở của token). Làm vậy là ĐI VÒNG qua toàn bộ
	 *    luật ở `pin_info()`: ngoại lệ `bc_pin` (Admin mở phạm vi khác hồ sơ, hoặc KHOÁ PIN khỏi
	 *    riêng trang báo cáo) sẽ không còn được xét — một người bị Admin khoá báo cáo tường minh
	 *    (bc_pin.active=0) vẫn lọt vào được nếu đi đường tắt này.
	 *
	 * → Thay vào đó: tìm PIN THẬT của người này trong hồ sơ nhân sự (khớp CẢ tên lẫn cơ sở —
	 *    chỉ khớp tên có thể trùng giữa hai người khác nhau trong 400 nhân sự, "GO BÀ RỊA" ×
	 *    trùng tên là chuyện đã gặp), rồi gọi thẳng `boot()` với đúng PIN đó — đi lại NGUYÊN VẸN
	 *    con đường cũ, chỉ khỏi bắt người dùng gõ tay.
	 *
	 * ⚠️ TRẢ PIN VỀ CHO CLIENT. Nghe ngược với "không bao giờ IN PIN ra màn hình" ở đầu tệp,
	 *    nhưng đó là nói về HIỂN THỊ — client vẫn cần giữ PIN trong biến JS để đính kèm mọi lượt
	 *    gọi sau (`goi()` tự thêm `d.pin=PIN`), giống hệt như nếu người dùng tự gõ. Không hiển
	 *    thị nó ra đâu cả, và kênh đã xác thực bằng token trước khi tới được đây.
	 *
	 * 🔴 NGUỒN PIN: PHIÊN TRƯỚC, DÒ NGƯỢC LÀ ĐƯỜNG LUI.
	 *    Bản đầu (28/08/2026) chỉ có đường dò ngược: khớp (tên, cơ sở) trong `VHG_Auth::users()`,
	 *    khớp không ra thì hạ xuống khớp tên suông nếu đúng MỘT người trùng tên. Cách đó TRƯỢT ở
	 *    đúng ca Võ Nguyễn Hồng Nhung 28–29/08/2026: `coso` trong phiên là ẢNH CHỤP lúc đăng
	 *    nhập, còn `users()` luôn đọc SỐNG — hồ sơ đổi cơ sở sau khi đăng nhập (hoặc cơ sở phụ
	 *    được gộp thêm vào chuỗi, xem `VHCC_DayGhe::ho_so_day()`) là lệch ngay khớp (1); mà khớp
	 *    (2) cũng trượt nếu trùng tên với người khác trong 400+ nhân sự — cả hai lặng lẽ rớt về
	 *    cổng PIN cũ, không có gì báo hiệu tính năng đã chạy mà chạy sai.
	 *
	 *    Từ bản này, `login()` ghi kèm PIN đã dùng vào chính phiên (`phien.pin`) — PIN đó đã qua
	 *    xác thực đúng MỘT lần rồi, dùng lại thẳng là hết mọi kiểu trượt khớp ở trên. Đường dò
	 *    ngược GIỮ LẠI làm cầu nối cho phiên cũ phát TRƯỚC bản này (chưa có `phien.pin`) — mất
	 *    dần khi những phiên đó hết hạn hoặc người dùng đăng xuất/đăng nhập lại.
	 *
	 * ⚠️ `$pin_phien` ĐI RIÊNG, KHÔNG NẰM TRONG `$ai`. `$ai` (từ `VHG_Auth::user_by_token()`)
	 *    được nhúng thẳng vào JSON trả cho trình duyệt ở `so_lieu()` — mọi lượt tải trang chính,
	 *    của MỌI người. Nhét PIN vào đó là in PIN ra network tab của tất cả mọi phiên, đúng thứ
	 *    cả tệp này tránh từ đầu ("KHÔNG bao giờ IN PIN ra màn hình"). Gọi riêng
	 *    `VHG_Auth::pin_phien_tu_token()` đúng MỘT chỗ (dispatch `bc_boot_tu_token`) và truyền
	 *    tay vào đây, thay vì để nó trôi theo `$ai` qua những chỗ không ngờ tới.
	 */
	public static function boot_tu_ai( $ai, $pin_phien = '' ) {
		$ten = trim( (string) ( isset( $ai['name'] ) ? $ai['name'] : '' ) );
		if ( '' === $ten ) { return array( 'ok' => false, 'pinOk' => false, 'error' => 'Không xác định được người dùng.' ); }
		$pin = trim( (string) $pin_phien );
		$coso_ai = trim( (string) ( isset( $ai['coso'] ) ? $ai['coso'] : '' ) );
		/* 🔎 VÌ SAO CÓ `$vi_sao` — bản 1.63.1 im lặng rớt về đường dò cũ khi `$pin_phien` rỗng, và
		   khi đường dò cũ CŨNG trượt thì lỗi trả về không nói được TRƯỢT Ở BƯỚC NÀO (phiên chưa
		   có cột `pin`? không tìm ra tên? trùng tên?) — người ngồi máy chỉ thấy "vẫn bắt đăng
		   nhập" và không ai đoán được lý do tiếp theo là gì. Ghi lại từng bước để câu lỗi cuối nói
		   thẳng ra, thay vì một câu chung chung lặp lại y hệt bất kể nguyên nhân. */
		$vi_sao = ( '' !== $pin_phien ) ? 'co_pin_phien' : 'thieu_pin_phien';
		if ( '' === $pin && class_exists( 'VHG_Auth' ) ) {
			$users = VHG_Auth::users();
			if ( is_wp_error( $users ) ) {
				$vi_sao .= '; loi_users:' . $users->get_error_message();
			} else {
				/* 🔴 KHỚP CẢ TÊN LẪN CƠ SỞ TRƯỚC, TÊN SUÔNG SAU — xem khối 🔴 phía trên vì sao đây
				   chỉ còn là đường lui cho phiên cũ, không phải đường chính. */
				$theo_ten = array();
				foreach ( (array) $users as $u ) {
					if ( '' === (string) $u['pin'] ) { continue; }
					if ( trim( (string) $u['ten'] ) !== $ten ) { continue; }
					if ( trim( (string) $u['coso'] ) === $coso_ai ) { $pin = (string) $u['pin']; break; }
					$theo_ten[] = $u;
				}
				if ( '' === $pin && 1 === count( $theo_ten ) ) { $pin = (string) $theo_ten[0]['pin']; }
				if ( '' === $pin ) {
					$vi_sao .= '; ten="' . $ten . '" coso_phien="' . $coso_ai . '" trung_ten=' . count( $theo_ten )
						. '; tong_users=' . count( $users );
					if ( count( $theo_ten ) ) {
						$cs_khac = array();
						foreach ( $theo_ten as $u ) { $cs_khac[] = (string) $u['coso']; }
						$vi_sao .= '; coso_ho_so=' . implode( ' | ', $cs_khac );
					}
				}
			}
		}
		if ( '' === $pin ) {
			return array( 'ok' => false, 'pinOk' => false,
				'error' => 'Chưa có PIN trong hồ sơ nhân sự — nhờ Admin cấp PIN rồi vào lại.',
				'viSao' => $vi_sao );
		}
		$r = self::boot( $pin );
		if ( ! empty( $r['ok'] ) ) { $r['pin'] = $pin; }
		else { $r['viSao'] = $vi_sao . '; boot_that_bai'; }
		return $r;
	}

	// ══════════════════════════════════════════════════════════════════ 1 BÁO CÁO/NGÀY

	private static function header_( $coso, $ngay ) {
		global $wpdb;
		/* Nhiều lần thu/ngày: trả LẦN MỚI NHẤT (lan cao nhất) — dùng để hiển thị/kiểm tra, không
		   còn dùng để "sửa đè" (xem 🔴 trong luu()). */
		return $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . VHG_DB::t( 'bc' ) . ' WHERE coso_key=%s AND ngay=%s ORDER BY lan DESC LIMIT 1',
			self::squash( $coso ), self::ngay_( $ngay ) ), ARRAY_A );
	}
	private static function header_theo_id_( $rid ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . VHG_DB::t( 'bc' ) . ' WHERE report_id=%s LIMIT 1', (string) $rid ), ARRAY_A );
	}
	private static function tong_bc_( $rid ) {
		global $wpdb;
		$r = $wpdb->get_row( $wpdb->prepare(
			'SELECT COUNT(*) so, COALESCE(SUM(tien_mat),0) tm, COALESCE(SUM(qr),0) qr, COALESCE(SUM(tong),0) tg'
			. ' FROM ' . VHG_DB::t( 'bc_dong' ) . ' WHERE report_id=%s AND (chi_so_sau IS NOT NULL OR tong<>0 OR actual<>0)', (string) $rid ), ARRAY_A );
		return array( 'so' => (int) $r['so'], 'tien_mat' => (int) $r['tm'], 'qr' => (int) $r['qr'], 'tong' => (int) $r['tg'] );
	}

	/**
	 * Doanh thu THEO TỪNG GHẾ trong một tháng — dùng cho "Sổ ghế theo điểm" ở trang quản trị
	 * (Máy & cơ sở). Anh Thắng 29/08/2026: "Doanh thu ghế trong tháng, Doanh thu QR, Doanh Thu
	 * Tiền mặt". Gộp thẳng từ `bc_dong` theo `ma_may` — không giữ bản số riêng, khỏi lệch với
	 * số kế toán đang duyệt.
	 *
	 * @param string $thang 'YYYY-MM'; rỗng = tháng hiện tại.
	 * @return array [ ma_may => ['tien_mat'=>, 'qr'=>, 'tong'=>] ]
	 */
	public static function doanh_thu_thang_theo_may( $thang = '' ) {
		global $wpdb;
		$thang = trim( (string) $thang );
		if ( ! preg_match( '/^\d{4}-\d{2}$/', $thang ) ) { $thang = current_time( 'Y-m' ); }
		$dau  = $thang . '-01';
		$cuoi = gmdate( 'Y-m-t', strtotime( $dau ) );
		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT ma_may, COALESCE(SUM(tien_mat),0) tm, COALESCE(SUM(qr),0) qr, COALESCE(SUM(tong),0) tg'
			. ' FROM ' . VHG_DB::t( 'bc_dong' ) . ' WHERE ngay BETWEEN %s AND %s GROUP BY ma_may',
			$dau, $cuoi ), ARRAY_A );
		$ra = array();
		foreach ( (array) $rows as $r ) {
			$ra[ (string) $r['ma_may'] ] = array(
				'tien_mat' => (int) $r['tm'], 'qr' => (int) $r['qr'], 'tong' => (int) $r['tg'],
			);
		}
		return $ra;
	}

	public static function kiem_ngay( $coso, $ngay, $pin ) {
		$q = self::pin_info( $pin );
		if ( ! self::trong_pham_vi( $q, $coso ) ) { return array( 'exists' => false ); }
		$h = self::header_( $coso, $ngay );
		if ( ! $h ) { return array( 'exists' => false ); }
		$s = self::tong_bc_( $h['report_id'] );
		return array( 'exists' => true, 'report_id' => $h['report_id'], 'chairs' => $s['so'],
			'staff' => (string) $h['nhan_vien'], 'cash' => $s['tien_mat'], 'qr' => $s['qr'], 'total' => $s['tong'] );
	}

	// ══════════════════════════════════════════════════════════════════ GỬI BÁO CÁO

	public static function luu( $payload, $pin ) {
		global $wpdb;
		$q = self::pin_info( $pin );
		if ( ! $q ) { return array( 'ok' => false, 'ma' => 'het_phien', 'message' => 'PIN không hợp lệ — đăng nhập lại.' ); }
		$p = is_array( $payload ) ? $payload : array();
		$rows_in = ( isset( $p['rows'] ) && is_array( $p['rows'] ) ) ? $p['rows'] : array();
		if ( ! count( $rows_in ) ) { return array( 'ok' => false, 'message' => 'Chưa nhập số liệu ghế nào.' ); }

		$ngay = self::ngay_( isset( $p['date'] ) ? $p['date'] : '' );
		$coso = trim( (string) ( isset( $p['loc'] ) ? $p['loc'] : ( isset( $rows_in[0]['locName'] ) ? $rows_in[0]['locName'] : '' ) ) );
		if ( '' === $ngay ) { return array( 'ok' => false, 'message' => 'Chọn ngày báo cáo.' ); }
		if ( '' === $coso ) { return array( 'ok' => false, 'message' => 'Chọn cơ sở.' ); }
		if ( ! self::trong_pham_vi( $q, $coso ) ) { return array( 'ok' => false, 'message' => 'Cơ sở ' . $coso . ' không thuộc phạm vi PIN của bạn.' ); }
		$ln = self::ngay_sai_( $ngay );
		if ( '' !== $ln ) { return array( 'ok' => false, 'message' => $ln ); }
		if ( self::dang_khoa( $coso, $ngay ) ) {
			return array( 'ok' => false, 'message' => 'Cơ sở ' . $coso . ' ngày ' . $ngay . ' đang KHOÁ — nhờ kế toán mở lại.' );
		}

		$rows = array();
		foreach ( $rows_in as $r0 ) {
			$ma = trim( (string) ( isset( $r0['chairCode'] ) ? $r0['chairCode'] : ( isset( $r0['ma'] ) ? $r0['ma'] : '' ) ) );
			if ( '' === $ma ) { continue; }
			if ( ! self::trong_pham_vi( $q, $coso, $ma ) ) { continue; }
			$after = isset( $r0['meterAfter'] ) ? $r0['meterAfter'] : ( isset( $r0['chi_so_sau'] ) ? $r0['chi_so_sau'] : '' );
			if ( '' === (string) $after || null === $after ) { continue; }
			/* Mỗi lượt gửi LUÔN là một lần thu mới (xem khối 🔴 ở dưới) → mốc lấy CẢ chỉ số sau của
			   các lần thu trước trong CHÍNH ngày đó (toi=true), để nối tiếp lần gần nhất. */
			$truoc_ht = self::chi_so_truoc( $ma, $ngay, true );
			$before = ( null !== $truoc_ht ) ? $truoc_ht : self::songuyen_( isset( $r0['meterBefore'] ) ? $r0['meterBefore'] : '' );
			/* CHỈ SỐ BẤT THƯỜNG (sau < trước) — anh Thắng 28/08: "hiện ra lý do lỗi tại hàng máy
			   lỗi, nhân viên nhập lý do. Khi nhập lý do thì lần 2 sẽ cho gửi báo cáo (nó sẽ báo
			   về cho kế toán để check doanh thu)". Trước đây CHẶN CỨNG luôn — đúng cho máy thật
			   sự đổi điểm (đã có đường riêng "Đề nghị đổi chỉ số"), nhưng nặng cho lỗi gõ nhầm.
			   Có lý do thì cho qua, và GHÉP LÝ DO VÀO GHI CHÚ có tiền tố cảnh báo dễ nhận — kế
			   toán mở báo cáo ra là thấy ngay, không cần đoán ô nào bất thường. ⚠️ VẪN CHỈ TIN
			   "CÓ LÝ DO HAY KHÔNG", không phải tin số liệu đúng — chốt "fail closed" giữ nguyên
			   khi KHÔNG có lý do, y hệt trước giờ. */
			$ly_do_bt = mb_substr( trim( (string) ( isset( $r0['abnormalReason'] ) ? $r0['abnormalReason'] : '' ) ), 0, 200 );
			$ghi_chu  = trim( (string) ( isset( $r0['note'] ) ? $r0['note'] : '' ) );
			if ( '' !== $ly_do_bt ) {
				$ghi_chu = trim( '⚠ CHỈ SỐ BẤT THƯỜNG: ' . $ly_do_bt . ( '' !== $ghi_chu ? ' · ' . $ghi_chu : '' ) );
			}
			/* ĐỐI CHIẾU LƯỢT KÍCH GHẾ TỪ XA — anh Thắng 28/08: "nếu ghế nào có kích thì báo,
			   không có thì thôi". Tính TRƯỚC khi tinh_() để trừ thẳng vào actual; "báo" bằng cách
			   ghép vào ghi chú, không im lặng sửa số — kế toán mở báo cáo là thấy vì sao actual
			   không khớp thẳng công thức (sau-trước)×đơn_vị. Xem VHG_BaoCao::kich_xa_tru(). */
			$kx = self::kich_xa_tru( $ma, $ngay );
			if ( $kx['so_luot'] > 0 ) {
				$ghi_chu = trim( '🔧 Đã trừ ' . $kx['so_luot'] . ' lượt kích ghế từ xa ('
					. number_format( $kx['tien'], 0, ',', '.' ) . 'đ)'
					. ( '' !== $ghi_chu ? ' · ' . $ghi_chu : '' ) );
			}
			$r = array( 'ma_may' => $ma, 'ten' => (string) ( isset( $r0['chairName'] ) ? $r0['chairName'] : $ma ),
				'ngay' => $ngay, 'chi_so_truoc' => $before, 'chi_so_sau' => (int) $after,
				'qr' => (int) ( isset( $r0['qr'] ) ? $r0['qr'] : 0 ),
				'dieu_chinh' => (int) ( isset( $r0['adjust'] ) ? $r0['adjust'] : 0 ),
				'kich_tien' => $kx['tien'],
				'ghi_chu' => mb_substr( $ghi_chu, 0, 250 ),
				/* Ảnh gắn CỨNG vào đúng ghế này — {chiso, vesinh}, mỗi ô một dataUrl hoặc vắng
				   mặt. Thay cho cách cũ chia đều một xấp ảnh chung theo THỨ TỰ ghế trong bảng
				   (`chia_anh_ghe_()`, nay bỏ) — chụp lộn thứ tự với 20 ghế là chuyện dễ xảy ra,
				   và ảnh gán nhầm ghế chỉ lộ ra khi kế toán soát thấy sai. */
				'images' => ( isset( $r0['images'] ) && is_array( $r0['images'] ) ) ? $r0['images'] : array() );
			self::tinh_( $r );
			/* BẤT THƯỜNG = chỉ số đi ngược (sau < trước) HOẶC công thức thô tính ra ÂM (QR nhập
			   lớn hơn actual — công thức tien_mat = actual − qr, KHÔNG còn cộng điều_chỉnh, xem
			   tinh_()). Anh Thắng 28/08, ảnh AM-BD-1: chỉ số ĐÚNG chiều (597→610, actual 130.000)
			   nhưng QR gõ 240.000 > actual, tien_mat ra -110.000 — "sao lại để -110". Ghế không
			   bao giờ nộp tiền mặt âm; QR lớn hơn actual là QR gõ sai hoặc actual thiếu, cả hai
			   đều cần người xác nhận, không phải trừ ra âm rồi lặng lẽ ghi sổ — nên xét CẢ HAI
			   điều kiện, không chỉ chỉ số. */
			$chi_so_nguoc = ( null !== $r['chi_so_truoc'] && $r['chi_so_sau'] < $r['chi_so_truoc'] );
			$bat_thuong = $chi_so_nguoc || $r['tien_mat'] < 0;

			/* 🔴 MÁY ĐỨNG YÊN MÀ CÓ QR — CHỈ CẢNH BÁO, VẪN CHO GỬI.
			   Anh Thắng 31/08/2026: *"Khi chỉ số đứng yên (nhưng lại có chỉ số QR) dẫn đến chỉ
			   số tiền mặt âm. Lúc này nhân viên sẽ nhập thực thu là 0. Thì vẫn cho phép gửi báo
			   cáo."* và *"Chỉ đưa ra cảnh báo, nhưng vẫn cho phép gửi báo cáo bình thường, và
			   chỉ số tiền mặt lúc này vẫn ghi nhận thực thu, và chỉ số QR vẫn là QR."*

			   Ca này KHÁC HẲN ca AM-BD-1 (597→610, chỉ số TĂNG mà QR gõ 240.000 > actual
			   130.000): ở đó một trong hai con số gõ sai và phải có người kiểm. Còn ở đây chỉ số
			   ĐỨNG YÊN mà có lượt QR nghĩa là khách trả QR nhưng bộ đếm không nhảy — chuyện
			   thường ngày, và lý do thì đã nằm sẵn trong chính con số. Bắt gõ lại lý do mỗi lượt
			   là bắt người ta chép lại điều màn hình vừa nói.

			   🔴 CHẶT ĐÚNG MỘT CA, KHÔNG NỚI CẢ NHÁNH ÂM. Điều kiện là chỉ số sau BẰNG ĐÚNG chỉ
			      số trước — không phải "âm thì cho qua". Nới cả nhánh âm là mở lại đúng ca anh
			      Thắng bắt chặn hôm 28/08, và số âm lại lặng lẽ vào sổ.

			   ⚠️ VẪN PHẢI CÓ THỰC THU. "Nhập thực thu là 0" — số 0 ấy là lời khai của nhân viên
			      rằng ca này không thu được đồng tiền mặt nào; bỏ trống thì tiền mặt rơi về công
			      thức và ghi số ÂM vào sổ. Không có số khai thì không có gì để ghi nhận. */
			$dung_yen = ( null !== $r['chi_so_truoc'] && (int) $r['chi_so_sau'] === (int) $r['chi_so_truoc'] );
			$may_dung_co_qr = ( $dung_yen && (int) $r['qr'] > 0 && $r['tien_mat'] < 0 );
			/* 🔴 "THỰC THU" GHI ĐÈ CHO MỌI HÀNG, KHÔNG CHỈ HÀNG BẤT THƯỜNG. Anh Thắng 29/08/2026:
			   "cột này là cột thực thu" + "khi nhập thực thu ở cột này, tiền cộng sẽ lấy theo cột
			   này" — cột "Tăng/Giảm" cũ (chỉ cộng dồn) nay đổi hẳn thành "Thực thu": bất kỳ hàng
			   nào có gõ, tiền mặt phải nộp LẤY ĐÚNG số đó, không còn tính theo actual−qr nữa. Hàng
			   bất thường vẫn BẮT BUỘC phải có (như cũ, cộng thêm lý do) vì công thức của nó không
			   đáng tin; hàng bình thường thì đây là lựa chọn — nhân viên gõ khi tiền mặt đếm thực
			   tế khác số máy tính ra (thiếu/dư quỹ, làm tròn…). */
			$thuc_thu = isset( $r0['actualOverride'] ) ? self::songuyen_( $r0['actualOverride'] ) : null;
			if ( $bat_thuong && ! ( $may_dung_co_qr && null !== $thuc_thu ) ) {
				if ( '' === $ly_do_bt ) {
					$ly_ban_dau = $chi_so_nguoc
						? ( 'chỉ số sau (' . $r['chi_so_sau'] . ') nhỏ hơn chỉ số trước (' . $r['chi_so_truoc'] . ')' )
						: ( 'tiền mặt tính ra ÂM (' . number_format( $r['tien_mat'], 0, ',', '.' ) . 'đ) — QR lớn hơn Actual' );
					return array( 'ok' => false, 'message' => 'Ghế ' . $r['ten'] . ': ' . $ly_ban_dau
						. '. Ghi lý do ở ô đỏ và nhập đúng số tiền thật vào cột Thực thu tiền mặt rồi gửi lại.' );
				}
				if ( null === $thuc_thu ) {
					return array( 'ok' => false, 'message' => 'Ghế ' . $r['ten']
						. ': cần nhập Thực thu tiền mặt (số tiền nộp thật) vì chỉ số bất thường không tính được theo công thức.' );
				}
			}
			/* Cảnh báo đi VÀO GHI CHÚ, để kế toán mở báo cáo ra là thấy — "chỉ cảnh báo" nghĩa
			   là không chặn tay nhân viên, không phải là im lặng với người soát sổ. */
			if ( $may_dung_co_qr && null !== $thuc_thu ) {
				$r['ghi_chu'] = mb_substr( trim( '⚠ MÁY ĐỨNG YÊN (' . (int) $r['chi_so_sau']
					. ') mà có QR ' . number_format( (int) $r['qr'], 0, ',', '.' ) . 'đ'
					. ( '' !== $r['ghi_chu'] ? ' · ' . $r['ghi_chu'] : '' ) ), 0, 250 );
			}
			if ( null !== $thuc_thu ) {
				$r['tien_mat'] = $thuc_thu;
				$r['tong']     = $r['tien_mat'] + $r['qr'];
				$r['ghi_chu']  = mb_substr( trim( $r['ghi_chu'] . ' · Thực thu ghi đè: '
					. number_format( $thuc_thu, 0, ',', '.' ) . 'đ' ), 0, 250 );
			}
			$rows[] = $r;
		}
		if ( ! count( $rows ) ) { return array( 'ok' => false, 'message' => 'Chưa nhập chỉ số sau cho ghế nào.' ); }

		$ck = self::squash( $coso );
		/* 🔴 MỖI LẦN GỬI = MỘT LẦN THU MỚI, KHÔNG BAO GIỜ ĐÈ LÊN LẦN CŨ.
		   Anh Thắng 29/08/2026: *"không nên bấm + thu lần nữa, mà sẽ tự hiểu và chèn vào giữa,
		   nghĩa là chọn ngày đó thì doanh thu ngày đó thôi"*. Bản 1.63.0 bắt bấm nút "➕ Thu lần
		   nữa" TRƯỚC khi nhập mới hiểu là thêm lần — quên bấm (hoặc không biết có nút đó) thì gửi
		   lại vô tình SỬA ĐÈ lên lần trước, mất chỉ số/tiền của lần đó.
		   Nay bỏ hẳn khái niệm "gửi lại = sửa đè" ở đúng màn nhập chính này: mọi lượt Gửi đều tạo
		   report_id MỚI + `lan` kế tiếp trong ngày, chỉ số trước LUÔN nối tiếp lần gần nhất
		   (`chi_so_truoc(..., true)` ở trên, không còn nhánh `$lan_moi` false/true nữa — luôn là
		   true). Muốn SỬA một lần đã gửi (gõ nhầm) thì đi qua màn Sửa/Lịch sử 24h (`bc_edit`),
		   không qua đường nhập chỉ số chính này — hai việc khác nhau, không được lẫn vào một nút. */
		$lan = (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COALESCE(MAX(lan),0)+1 FROM ' . VHG_DB::t( 'bc' ) . ' WHERE coso_key=%s AND ngay=%s', $ck, $ngay ) );
		if ( $lan < 1 ) { $lan = 1; }
		$rid  = 'RPT-' . current_time( 'YmdHis' ) . '-' . wp_rand( 100, 999 );
		$now  = current_time( 'mysql' );
		$pay  = self::doc_payment_( isset( $p['payment'] ) ? $p['payment'] : array(), $rows );

		$header = array( 'report_id' => $rid, 'ngay' => $ngay, 'lan' => $lan, 'coso' => $coso, 'coso_key' => $ck,
			'nhan_vien' => $q['ten'], 'sua_luc' => $now, 'tao_luc' => $now,
			'nop_hinhthuc' => $pay['hinhthuc'], 'nop_trang_thai' => $pay['trang_thai'], 'nop_so_tien' => $pay['so_tien'],
			'nop_ngay' => $pay['ngay'], 'nop_ghichu' => $pay['ghichu'], 'unpaid_lydo' => $pay['unpaid_lydo'],
			'ck_ref' => $pay['ck_ref'], 'ck_bank' => $pay['ck_bank'] );
		$wpdb->insert( VHG_DB::t( 'bc' ), $header );

		$ct = self::luu_nhieu_anh_( isset( $p['proofs'] ) ? $p['proofs'] : null, $rid, 'chungtu' );
		if ( count( $ct ) ) { $wpdb->update( VHG_DB::t( 'bc' ), array( 'chung_tu' => wp_json_encode( $ct ) ), array( 'report_id' => $rid ) ); }

		$chia_nop = self::chia_nop_( $rows, $pay );

		$gui_ma = array();
		foreach ( $rows as $i => $r ) {
			$gui_ma[ $r['ma_may'] ] = true;
			$np = isset( $chia_nop[ $r['ma_may'] ] ) ? $chia_nop[ $r['ma_may'] ]
				: array( 'nop_so_tien' => 0, 'nop_trang_thai' => '', 'nop_hinhthuc' => '', 'nop_ngay' => null );
			$data = array( 'report_id' => $rid, 'ma_may' => $r['ma_may'], 'ten' => $r['ten'], 'ngay' => $ngay, 'lan' => $lan,
				'chi_so_truoc' => $r['chi_so_truoc'], 'chi_so_sau' => $r['chi_so_sau'], 'actual' => $r['actual'],
				'tien_mat' => $r['tien_mat'], 'qr' => $r['qr'], 'dieu_chinh' => $r['dieu_chinh'],
				'tong' => $r['tong'], 'ghi_chu' => $r['ghi_chu'],
				'nop_so_tien' => $np['nop_so_tien'], 'nop_trang_thai' => $np['nop_trang_thai'],
				'nop_hinhthuc' => $np['nop_hinhthuc'], 'nop_ngay' => $np['nop_ngay'] );
			/* Ảnh chỉ số + ảnh vệ sinh của ĐÚNG ghế này — gắn theo nhãn, không chia đều theo thứ
			   tự nữa (xem chỗ khai 'images' ở $r phía trên). Giữ NGUYÊN dạng JSON mảng URL cho
			   cột `anh` (ktdRow() bên class-vhg-trang.php đang đọc mảng phẳng) — chỉ đổi CÁCH
			   NÓ ĐƯỢC ĐIỀN, ảnh nào vào đúng ghế đó thay vì đoán theo thứ tự. */
			$anh_urls = array();
			$imgs_r = isset( $r['images'] ) && is_array( $r['images'] ) ? $r['images'] : array();
			if ( ! empty( $imgs_r['chiso'] ) ) {
				$u = self::luu_anh_( array( 'dataUrl' => $imgs_r['chiso'], 'name' => 'chiso.jpg' ), $rid, $r['ma_may'] . '-chiso' );
				if ( '' !== $u ) { $anh_urls[] = $u; }
			}
			if ( ! empty( $imgs_r['vesinh'] ) ) {
				$u = self::luu_anh_( array( 'dataUrl' => $imgs_r['vesinh'], 'name' => 'vesinh.jpg' ), $rid, $r['ma_may'] . '-vesinh' );
				if ( '' !== $u ) { $anh_urls[] = $u; }
			}
			if ( count( $anh_urls ) ) { $data['anh'] = wp_json_encode( $anh_urls ); }
			$cu = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . VHG_DB::t( 'bc_dong' ) . ' WHERE report_id=%s AND ma_may=%s', $rid, $r['ma_may'] ) );
			if ( $cu ) { $wpdb->update( VHG_DB::t( 'bc_dong' ), $data, array( 'id' => (int) $cu ) ); }
			else { $wpdb->insert( VHG_DB::t( 'bc_dong' ), $data ); }
		}

		/* Nối dòng thời gian: ghế vừa gửi có thể làm lệch mốc của lần đọc kế tiếp — chèn/sửa ngày
		   giữa thì chỉ số trước của ngày sau tự nối lại (anh Thắng 29/08/2026). Đặt SAU khi mọi
		   hàng của lần này đã ghi xong để mốc sống tính đúng.
		   ⚠️ KHÔNG còn khối "bỏ ghế khỏi báo cáo cũ" (`$bo`) — khối đó xử lý ca "gửi lại đè lên
		   report_id cũ, ghế nào rớt khỏi lượt gửi này thì xoá số của ghế đó". Report_id giờ LUÔN
		   MỚI (không còn `$prev`/"gửi lại = sửa đè"), nên một report_id vừa tạo không thể có ghế
		   nào từ trước để mà "bỏ" — khối đó không còn đường nào chạy tới, xoá hẳn thay vì để chết. */
		foreach ( array_keys( $gui_ma ) as $ma_nt ) { self::noi_tiep( $ma_nt, $ngay ); }

		$dong_yc = self::dong_yeucau_( $coso, $ngay, $q['ten'] . ' · ' . $rid );
		$phien = self::phien_upsert_( $pin, $ngay );   // cập nhật tiến độ phiên thu ngày
		return array( 'ok' => true, 'reportId' => $rid, 'rows' => count( $rows ), 'updated' => false,
			'boGhe' => array(), 'dongYeuCau' => $dong_yc, 'phien' => $phien,
			'message' => 'Đã gửi báo cáo ' . $coso . ' ngày ' . $ngay . ( $lan > 1 ? ( ' (lần ' . $lan . ')' ) : '' ) . '.'
				. ( $dong_yc ? ( ' Hoàn thành ' . $dong_yc . ' yêu cầu kế toán.' ) : '' )
				. ( ( $phien && ! empty( $phien['du'] ) )
					? ( ' ✓ ĐỦ BÁO CÁO cả ' . $phien['so_coso'] . ' cơ sở hôm nay — đã gộp gửi kế toán (tổng '
						. number_format( (int) $phien['tong'], 0, ',', '.' ) . 'đ).' )
					: ( ( $phien && $phien['so_coso'] > 0 )
						? ( ' Đã thu ' . $phien['so_coso_xong'] . '/' . $phien['so_coso'] . ' cơ sở hôm nay.' )
						: '' ) ) );
	}

	/**
	 * BÁO CÁO TỔNG — nộp THAY bảng chi tiết từng ghế khi không làm được (máy hỏng, không kịp đo
	 * từng ghế, khách đông không tách kịp…). Anh Thắng 29/08/2026: *"Ô này là nộp báo cáo tổng
	 * nếu không làm báo cáo kia, chứ không phải ảnh chứng từ nộp tiền"* — ô ảnh vốn chỉ để đính
	 * QR chuyển khoản/hoá đơn kèm báo cáo chi tiết, nay còn thêm vai trò thứ hai: MỘT MÌNH nó
	 * (kèm ô "Số tiền nộp" đọc thành Tổng doanh thu) đủ để nộp cả báo cáo khi không có chi tiết.
	 *
	 * KHÔNG có chỉ số/QR riêng từng ghế — chỉ MỘT số Tổng doanh thu do nhân viên tự cộng, và ẢNH
	 * CHỨNG TỪ LÀ BẮT BUỘC (không có gì khác làm bằng chứng cho một con số không có công thức
	 * nào kiểm lại được). Ghi thành đúng MỘT dòng `bc_dong` với mã máy RỖNG, gắn cờ rõ trong ghi
	 * chú để kế toán biết ngay đây là báo cáo không có chi tiết — "Đối chiếu máy"
	 * (`VHG_BaoCao::doi_chieu()`) tự bỏ qua vì không tra ra máy nào khớp mã rỗng, không báo lệch
	 * giả cho một dòng vốn không gắn với máy nào.
	 */
	public static function luu_tong( $payload, $pin ) {
		global $wpdb;
		$q = self::pin_info( $pin );
		if ( ! $q ) { return array( 'ok' => false, 'ma' => 'het_phien', 'message' => 'PIN không hợp lệ — đăng nhập lại.' ); }
		$p = is_array( $payload ) ? $payload : array();

		$ngay = self::ngay_( isset( $p['date'] ) ? $p['date'] : '' );
		$coso = trim( (string) ( isset( $p['loc'] ) ? $p['loc'] : '' ) );
		if ( '' === $ngay ) { return array( 'ok' => false, 'message' => 'Chọn ngày báo cáo.' ); }
		if ( '' === $coso ) { return array( 'ok' => false, 'message' => 'Chọn cơ sở.' ); }
		if ( ! self::trong_pham_vi( $q, $coso ) ) { return array( 'ok' => false, 'message' => 'Cơ sở ' . $coso . ' không thuộc phạm vi PIN của bạn.' ); }
		$ln = self::ngay_sai_( $ngay );
		if ( '' !== $ln ) { return array( 'ok' => false, 'message' => $ln ); }
		if ( self::dang_khoa( $coso, $ngay ) ) {
			return array( 'ok' => false, 'message' => 'Cơ sở ' . $coso . ' ngày ' . $ngay . ' đang KHOÁ — nhờ kế toán mở lại.' );
		}

		$tong = self::songuyen_( isset( $p['tong'] ) ? $p['tong'] : null );
		if ( null === $tong || $tong <= 0 ) {
			return array( 'ok' => false, 'message' => 'Nhập đúng Tổng doanh thu (ô "Số tiền nộp") để gửi báo cáo tổng.' );
		}
		/* Ảnh là BẮT BUỘC cho báo cáo tổng — kiểm TRƯỚC khi tạo bất kỳ dòng nào, khỏi phải tạo
		   rồi xoá report_id nếu thiếu ảnh. */
		$co_anh = ! empty( $p['proofs'] ) && is_array( $p['proofs'] ) && ! empty( $p['proofs']['qr'] ) && is_array( $p['proofs']['qr'] );
		if ( ! $co_anh ) {
			return array( 'ok' => false, 'message' => 'Cần đính ít nhất 1 ảnh chứng từ để gửi báo cáo tổng.' );
		}

		$pm  = is_array( isset( $p['payment'] ) ? $p['payment'] : null ) ? $p['payment'] : array();
		$rr  = array( 'ma_may' => '', 'tien_mat' => $tong );   // hàng "ảo" duy nhất, cho doc_payment_()/chia_nop_() chạy chung công thức với luu()
		$pay = self::doc_payment_( $pm, array( $rr ) );

		$ck  = self::squash( $coso );
		$lan = (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COALESCE(MAX(lan),0)+1 FROM ' . VHG_DB::t( 'bc' ) . ' WHERE coso_key=%s AND ngay=%s', $ck, $ngay ) );
		if ( $lan < 1 ) { $lan = 1; }
		$rid = 'RPT-' . current_time( 'YmdHis' ) . '-' . wp_rand( 100, 999 );
		$now = current_time( 'mysql' );

		$header = array( 'report_id' => $rid, 'ngay' => $ngay, 'lan' => $lan, 'coso' => $coso, 'coso_key' => $ck,
			'nhan_vien' => $q['ten'], 'sua_luc' => $now, 'tao_luc' => $now,
			'nop_hinhthuc' => $pay['hinhthuc'], 'nop_trang_thai' => $pay['trang_thai'], 'nop_so_tien' => $pay['so_tien'],
			'nop_ngay' => $pay['ngay'], 'nop_ghichu' => $pay['ghichu'], 'unpaid_lydo' => $pay['unpaid_lydo'],
			'ck_ref' => $pay['ck_ref'], 'ck_bank' => $pay['ck_bank'] );
		$wpdb->insert( VHG_DB::t( 'bc' ), $header );

		$ct = self::luu_nhieu_anh_( $p['proofs'], $rid, 'chungtu' );
		if ( ! count( $ct ) ) {
			/* Nén/đọc ảnh lỗi hết cả (hiếm — dữ liệu ảnh hỏng) → không còn gì làm bằng chứng, huỷ
			   report vừa tạo thay vì để lại một báo cáo tổng không có ảnh nào đính kèm. */
			$wpdb->delete( VHG_DB::t( 'bc' ), array( 'report_id' => $rid ) );
			return array( 'ok' => false, 'message' => 'Không đọc được ảnh chứng từ — thử chọn lại ảnh rồi gửi lại.' );
		}
		$wpdb->update( VHG_DB::t( 'bc' ), array( 'chung_tu' => wp_json_encode( $ct ) ), array( 'report_id' => $rid ) );

		$chia_nop = self::chia_nop_( array( $rr ), $pay );
		$np = isset( $chia_nop[''] ) ? $chia_nop[''] : array( 'nop_so_tien' => 0, 'nop_trang_thai' => '', 'nop_hinhthuc' => '', 'nop_ngay' => null );

		$ghi_chu = '⚠ BÁO CÁO TỔNG — không có chi tiết từng ghế (chỉ số/QR riêng), xem ảnh chứng từ đính kèm để đối chiếu.';
		$ghi_note = trim( (string) ( isset( $pm['note'] ) ? $pm['note'] : '' ) );
		if ( '' !== $ghi_note ) { $ghi_chu .= ' · ' . $ghi_note; }

		$wpdb->insert( VHG_DB::t( 'bc_dong' ), array(
			'report_id' => $rid, 'ma_may' => '', 'ten' => '(Báo cáo tổng — không chi tiết)',
			'ngay' => $ngay, 'lan' => $lan,
			'chi_so_truoc' => null, 'chi_so_sau' => null, 'actual' => 0,
			'tien_mat' => $tong, 'qr' => 0, 'dieu_chinh' => 0, 'tong' => $tong,
			'ghi_chu' => mb_substr( $ghi_chu, 0, 250 ),
			'nop_so_tien' => $np['nop_so_tien'], 'nop_trang_thai' => $np['nop_trang_thai'],
			'nop_hinhthuc' => $np['nop_hinhthuc'], 'nop_ngay' => $np['nop_ngay'] ) );

		$dong_yc = self::dong_yeucau_( $coso, $ngay, $q['ten'] . ' · ' . $rid );
		$phien   = self::phien_upsert_( $pin, $ngay );
		return array( 'ok' => true, 'reportId' => $rid, 'rows' => 1, 'dongYeuCau' => $dong_yc, 'phien' => $phien,
			'message' => 'Đã gửi báo cáo TỔNG (không chi tiết) ' . $coso . ' ngày ' . $ngay
				. ( $lan > 1 ? ( ' (lần ' . $lan . ')' ) : '' ) . ' — ' . number_format( $tong, 0, ',', '.' ) . 'đ.'
				. ( $dong_yc ? ( ' Hoàn thành ' . $dong_yc . ' yêu cầu kế toán.' ) : '' ) );
	}

	private static function doc_payment_( $pm, $rows ) {
		$method = in_array( isset( $pm['method'] ) ? $pm['method'] : 'cash', array( 'cash', 'transfer', 'unpaid' ), true ) ? $pm['method'] : 'cash';
		$tong = 0; foreach ( $rows as $r ) { $tong += (int) $r['tien_mat']; }
		$tt = 'unpaid'; $st = 0;
		if ( 'cash' === $method || 'transfer' === $method ) {
			$tt = ( 'cash' === $method ) ? 'paid_cash' : 'paid_transfer';
			$raw = ( ! isset( $pm['amount'] ) || '' === $pm['amount'] || null === $pm['amount'] ) ? null : (int) $pm['amount'];
			$st = ( null === $raw ) ? $tong : $raw;
		}
		return array( 'hinhthuc' => $method, 'trang_thai' => $tt, 'so_tien' => $st,
			'ngay' => ( 'unpaid' === $tt ) ? null : self::ngay_( isset( $pm['date'] ) ? $pm['date'] : current_time( 'Y-m-d' ) ),
			'ghichu' => mb_substr( trim( (string) ( isset( $pm['note'] ) ? $pm['note'] : '' ) ), 0, 250 ),
			'unpaid_lydo' => ( 'unpaid' === $tt ) ? mb_substr( trim( (string) ( isset( $pm['unpaidReason'] ) ? $pm['unpaidReason'] : '' ) ), 0, 250 ) : '',
			'ck_ref' => mb_substr( trim( (string) ( isset( $pm['ref'] ) ? $pm['ref'] : '' ) ), 0, 120 ),
			'ck_bank' => mb_substr( trim( (string) ( isset( $pm['bank'] ) ? $pm['bank'] : '' ) ), 0, 60 ) );
	}

	/**
	 * NỘP THEO GHẾ — phân bổ số tiền nhân viên khai nộp xuống TỪNG ghế theo `tien_mat` phải nộp.
	 *
	 * Anh Thắng 27/08/2026 chốt *"nộp theo ghế"*. App gốc lưu tiền-đã-nộp ở từng dòng ghế, và đối
	 * soát/công nợ cộng theo ghế — nên số tiền khai lúc gửi phải rải xuống ghế ngay, y cách
	 * `allocatePaid_` của kế toán (ổn định: chạy lại ra y nguyên).
	 *
	 * `nop_hinhthuc` ghi RÕ 'cash'/'transfer' (không nhét vào chuỗi trạng thái) → sổ công nợ tách
	 * cột TM/CK theo cột này, khỏi vụ đoán ' (CK)' như bản Sheet.
	 *
	 * Trả map [ ma_may => ['nop_so_tien','nop_trang_thai','nop_hinhthuc','nop_ngay'] ].
	 */
	private static function chia_nop_( $rows, $pay ) {
		$out = array();
		$method = isset( $pay['hinhthuc'] ) ? $pay['hinhthuc'] : 'cash';
		$ngay_nop = ( isset( $pay['ngay'] ) && $pay['ngay'] ) ? $pay['ngay'] : null;
		if ( 'unpaid' === $method ) {
			foreach ( $rows as $r ) {
				$out[ $r['ma_may'] ] = array( 'nop_so_tien' => 0, 'nop_trang_thai' => 'unpaid',
					'nop_hinhthuc' => '', 'nop_ngay' => null );
			}
			return $out;
		}
		$hthuc = ( 'transfer' === $method ) ? 'transfer' : 'cash';
		$con = max( 0, (int) ( isset( $pay['so_tien'] ) ? $pay['so_tien'] : 0 ) );
		$ds = $rows;
		usort( $ds, function ( $a, $b ) { return strcmp( (string) $a['ma_may'], (string) $b['ma_may'] ); } );
		foreach ( $ds as $r ) {
			$can = max( 0, (int) $r['tien_mat'] );
			$cap = min( $con, $can );
			$con -= $cap;
			$tt = ( $can > 0 && $cap >= $can ) ? 'paid' : ( $cap > 0 ? 'thieu' : 'unpaid' );
			$out[ $r['ma_may'] ] = array(
				'nop_so_tien' => $cap,
				'nop_trang_thai' => $tt,
				'nop_hinhthuc' => $cap > 0 ? $hthuc : '',
				'nop_ngay' => ( $cap > 0 && $ngay_nop ) ? $ngay_nop : null );
		}
		return $out;
	}

	// ══════════════════════════════════════════════════════════════════ ẢNH -> thư viện WP

	private static function luu_anh_( $img, $rid, $stt ) {
		$data = (string) ( isset( $img['dataUrl'] ) ? $img['dataUrl'] : '' );
		if ( strpos( $data, ',' ) === false ) { return ''; }
		list( , $b64 ) = explode( ',', $data, 2 );
		$bin = base64_decode( $b64 );
		if ( false === $bin || '' === $bin ) { return ''; }
		$ten = sanitize_file_name( $rid . '-' . $stt . '-' . ( isset( $img['name'] ) ? $img['name'] : 'anh.jpg' ) );
		if ( ! preg_match( '/\.(jpe?g|png|gif|webp)$/i', $ten ) ) { $ten .= '.jpg'; }
		$up = wp_upload_bits( $ten, null, $bin );
		return empty( $up['error'] ) ? (string) $up['url'] : '';
	}
	private static function luu_nhieu_anh_( $proofs, $rid, $tt ) {
		$out = array();
		if ( ! is_array( $proofs ) ) { return $out; }
		$i = 0;
		foreach ( array( 'qr', 'cash', 'transfer' ) as $nhom ) {
			if ( empty( $proofs[ $nhom ] ) || ! is_array( $proofs[ $nhom ] ) ) { continue; }
			foreach ( $proofs[ $nhom ] as $img ) {
				$u = self::luu_anh_( $img, $rid, $tt . '-' . $nhom . '-' . ( ++$i ) );
				if ( '' !== $u ) { $out[] = $u; }
			}
		}
		return $out;
	}

	// ══════════════════════════════════════════════════════════════════ 24H · LỊCH SỬ · NỘP

	private static function con_han_( $tao_luc ) {
		$t = strtotime( (string) $tao_luc );
		return $t ? ( ( current_time( 'timestamp' ) - $t ) < self::GIO_SUA * 3600 ) : false;
	}

	public static function ds_24h( $pin ) {
		global $wpdb;
		$q = self::pin_info( $pin );
		if ( ! $q ) { return array(); }
		$hs = $wpdb->get_results( 'SELECT * FROM ' . VHG_DB::t( 'bc' ) . ' ORDER BY tao_luc DESC LIMIT 200', ARRAY_A );
		$ra = array();
		foreach ( (array) $hs as $h ) {
			if ( ! self::trong_pham_vi( $q, $h['coso'] ) ) { continue; }
			if ( ! self::con_han_( $h['tao_luc'] ) ) { continue; }
			$dong = $wpdb->get_results( $wpdb->prepare(
				'SELECT * FROM ' . VHG_DB::t( 'bc_dong' ) . ' WHERE report_id=%s AND (chi_so_sau IS NOT NULL OR tong<>0 OR actual<>0) ORDER BY id ASC', $h['report_id'] ), ARRAY_A );
			$ghe = array(); $tong = 0;
			foreach ( $dong as $d ) {
				$tong += (int) $d['tong'];
				/* 🔴 CHỈ TRẢ `adjust` KHI THẬT SỰ LÀ GHI ĐÈ. Cột `dieu_chinh` trước 29/08/2026 là số
				   CỘNG DỒN (bản cũ), không phải Thực thu — đưa nguyên số cũ đó ra làm giá trị ghi
				   đè là đổi hẳn ý nghĩa của một con số lịch sử mà không ai yêu cầu. Chỉ có báo cáo
				   nào ĐÃ ghi đè thật (có dấu "Thực thu ghi đè" trong ghi chú, do `luu()`/`sua_dong()`
				   gắn vào) mới trả số ra; còn lại trả `null` — màn Sửa hiện Ô TRỐNG, không phải 0,
				   để không ai tưởng nhầm 0đ là một lượt ghi đè. */
				$co_ghi_de = ( false !== mb_strpos( (string) $d['ghi_chu'], 'Thực thu ghi đè' ) );
				/* Ảnh đã đính từ lúc gửi ban đầu (nếu có) — cho màn Sửa 24h biết TRƯỚC khi bắt gõ gì,
				   để không bắt đính lại ảnh cho ghế đã có sẵn ảnh rồi. Xem ràng buộc "tối thiểu 1 ảnh"
				   ở sua_dong() bên dưới — anh Thắng 29/08/2026: "bổ sung thêm ảnh trong báo cáo 24h". */
				$anh_ds = array();
				$anh_raw = (string) ( isset( $d['anh'] ) ? $d['anh'] : '' );
				if ( '' !== $anh_raw ) { $tmp = json_decode( $anh_raw, true ); if ( is_array( $tmp ) ) { $anh_ds = array_values( array_filter( $tmp ) ); } }
				$ghe[] = array( 'chairCode' => $d['ma_may'], 'chairName' => $d['ten'],
					'meterBefore' => self::songuyen_( $d['chi_so_truoc'] ), 'meterAfter' => self::songuyen_( $d['chi_so_sau'] ),
					'actual' => (int) $d['actual'], 'cash' => (int) $d['tien_mat'], 'qr' => (int) $d['qr'],
					'adjust' => $co_ghi_de ? (int) $d['dieu_chinh'] : null, 'note' => $d['ghi_chu'], 'anh' => $anh_ds );
			}
			$ra[] = array( 'reportId' => $h['report_id'], 'date' => self::ngay_( $h['ngay'] ),
				'locName' => $h['coso'], 'rows' => count( $ghe ), 'total' => $tong, 'chairs' => $ghe );
		}
		return $ra;
	}

	public static function sua_dong( $rid, $ma, $patch, $pin ) {
		global $wpdb;
		$q = self::pin_info( $pin );
		if ( ! $q ) { return array( 'ok' => false, 'ma' => 'het_phien', 'message' => 'PIN không hợp lệ.' ); }
		$rid = (string) $rid; $ma = (string) $ma;
		if ( '' === $rid || '' === $ma ) { return array( 'ok' => false, 'message' => 'Thiếu mã báo cáo hoặc ghế.' ); }
		$h = self::header_theo_id_( $rid );
		if ( ! $h ) { return array( 'ok' => false, 'message' => 'Không thấy báo cáo.' ); }
		if ( ! self::trong_pham_vi( $q, $h['coso'], $ma ) ) { return array( 'ok' => false, 'message' => 'Báo cáo này không thuộc phạm vi của bạn.' ); }
		if ( ! self::con_han_( $h['tao_luc'] ) ) { return array( 'ok' => false, 'message' => 'Báo cáo đã quá ' . self::GIO_SUA . ' giờ nên khoá. Nhờ kế toán.' ); }
		if ( self::dang_khoa( $h['coso'], $h['ngay'] ) ) { return array( 'ok' => false, 'message' => 'Ngày này đang KHOÁ — nhờ kế toán.' ); }
		$d = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . VHG_DB::t( 'bc_dong' ) . ' WHERE report_id=%s AND ma_may=%s LIMIT 1', $rid, $ma ), ARRAY_A );
		if ( ! $d ) { return array( 'ok' => false, 'message' => 'Không thấy dòng cần sửa.' ); }
		$patch = is_array( $patch ) ? $patch : array();
		/* 🔴 "THỰC THU" QUA MÀN SỬA 24H CŨNG GHI ĐÈ, GIỐNG HỆT LÚC GỬI ĐẦU. Anh Thắng 29/08/2026:
		   đổi cột "Tăng/Giảm" (cộng dồn) thành "Thực thu" (ghi đè) — sửa ở đây phải cùng luật,
		   không thì cùng một cột lại xử khác nhau tuỳ màn sửa ở đâu.
		   ⚠️ KHÔNG ĐỘNG PATCH THÌ GIỮ NGUYÊN TRẠNG THÁI GHI ĐÈ CŨ — đọc lại từ dấu "Thực thu ghi
		   đè" trong ghi chú (bc_recent() cũng dùng đúng dấu này để biết có nên hiện số ra ô hay
		   không), chứ không phải cứ có số trong cột `dieu_chinh` là ghi đè: cột đó còn giữ số CỘNG
		   DỒN của những báo cáo cũ trước ngày đổi luật, đưa thẳng số đó ra làm ghi đè là đổi tiền
		   một báo cáo cũ mà không ai bấm Lưu gì cả. */
		$gt_de_cu = ( false !== mb_strpos( (string) $d['ghi_chu'], 'Thực thu ghi đè' ) ) ? (int) $d['dieu_chinh'] : null;
		$co_doi_ad = array_key_exists( 'adjust', $patch );
		$thuc_thu = $co_doi_ad
			? ( ( null === $patch['adjust'] || '' === trim( (string) $patch['adjust'] ) ) ? null : (int) $patch['adjust'] )
			: $gt_de_cu;
		$ghi_chu_goc = array_key_exists( 'note', $patch ) ? mb_substr( trim( (string) $patch['note'] ), 0, 250 ) : (string) $d['ghi_chu'];
		/* Bỏ dấu ghi đè CŨ trước khi tính lại — gắn lại mới bên dưới nếu vẫn còn ghi đè, tránh ghi
		   chú phình ra nhiều lần "Thực thu ghi đè" khi sửa đi sửa lại cùng một dòng. */
		$ghi_chu_goc = trim( preg_replace( '/\s*·?\s*Thực thu ghi đè:[^·]*/u', '', $ghi_chu_goc ) );
		$r = array( 'ma_may' => $ma, 'ngay' => $h['ngay'],
			'chi_so_sau' => array_key_exists( 'meterAfter', $patch ) ? $patch['meterAfter'] : $d['chi_so_sau'],
			'qr' => array_key_exists( 'qr', $patch ) ? (int) $patch['qr'] : (int) $d['qr'],
			'dieu_chinh' => null !== $thuc_thu ? $thuc_thu : 0,
			'ghi_chu' => $ghi_chu_goc );
		$truoc = self::chi_so_truoc( $ma, $h['ngay'] );
		$r['chi_so_truoc'] = ( null !== $truoc ) ? $truoc : self::songuyen_( $d['chi_so_truoc'] );
		self::tinh_( $r );
		if ( null !== $r['chi_so_truoc'] && null !== $r['chi_so_sau'] && $r['chi_so_sau'] < $r['chi_so_truoc'] ) {
			return array( 'ok' => false, 'message' => 'Chỉ số sau nhỏ hơn chỉ số trước — gửi đề nghị đổi chỉ số nếu vừa thay máy.' );
		}
		if ( null !== $thuc_thu ) {
			$r['tien_mat'] = $thuc_thu;
			$r['tong']     = $r['tien_mat'] + $r['qr'];
			$r['ghi_chu']  = mb_substr( trim( $r['ghi_chu'] . ( '' !== $r['ghi_chu'] ? ' · ' : '' ) . 'Thực thu ghi đè: '
				. number_format( $thuc_thu, 0, ',', '.' ) . 'đ' ), 0, 250 );
		}
		/* 🔴 TỐI THIỂU 1 ẢNH MỖI GHẾ — anh Thắng 29/08/2026: "bổ sung thêm ảnh trong báo cáo 24h
		   nhé (tối thiểu 1 ảnh nhé)". Ghế đã có sẵn ảnh từ lúc gửi ban đầu (`anh` JSON không rỗng)
		   thì không bắt đính lại; ghế CHƯA có ảnh nào thì lượt Sửa này phải kèm ít nhất 1 ảnh mới
		   (chỉ số hoặc vệ sinh) mới cho lưu — chốt an toàn lặp lại ở client (theGheSua()), server
		   vẫn tự kiểm lại vì client có thể bị bỏ qua/lỗi thời. */
		$anh_hien = array();
		$anh_raw  = (string) ( isset( $d['anh'] ) ? $d['anh'] : '' );
		if ( '' !== $anh_raw ) { $tmp = json_decode( $anh_raw, true ); if ( is_array( $tmp ) ) { $anh_hien = array_values( array_filter( $tmp ) ); } }
		$anh_moi = array();
		$imgs_p  = ( isset( $patch['images'] ) && is_array( $patch['images'] ) ) ? $patch['images'] : array();
		if ( ! empty( $imgs_p['chiso'] ) ) {
			$u = self::luu_anh_( array( 'dataUrl' => $imgs_p['chiso'], 'name' => 'chiso.jpg' ), $rid, $ma . '-sua-chiso-' . time() );
			if ( '' !== $u ) { $anh_moi[] = $u; }
		}
		if ( ! empty( $imgs_p['vesinh'] ) ) {
			$u = self::luu_anh_( array( 'dataUrl' => $imgs_p['vesinh'], 'name' => 'vesinh.jpg' ), $rid, $ma . '-sua-vesinh-' . time() );
			if ( '' !== $u ) { $anh_moi[] = $u; }
		}
		$anh_tong = array_merge( $anh_hien, $anh_moi );
		if ( ! count( $anh_tong ) ) {
			return array( 'ok' => false, 'message' => 'Ghế này chưa có ảnh nào — cần đính ít nhất 1 ảnh (chỉ số hoặc vệ sinh) mới lưu được.' );
		}
		$data_up = array( 'chi_so_truoc' => $r['chi_so_truoc'], 'chi_so_sau' => $r['chi_so_sau'],
			'actual' => $r['actual'], 'tien_mat' => $r['tien_mat'], 'qr' => $r['qr'], 'dieu_chinh' => $r['dieu_chinh'],
			'tong' => $r['tong'], 'ghi_chu' => $r['ghi_chu'] );
		/* 🔴 "NỘP" (nop_so_tien) BỊ KẸT SỐ CŨ NẾU KHÔNG SỬA THEO — anh Thắng 29/08/2026 phát hiện ở
		   màn kế toán: ghế VP-PQ-16 Tiền mặt đã ghi đè xuống 830.000đ nhưng cột "Nộp" vẫn đứng ở
		   990.000đ (số actual TRƯỚC khi ghi đè). `nop_so_tien` được `chia_nop_()` rải MỘT LẦN lúc
		   gửi báo cáo theo đúng `tien_mat` tại thời điểm đó; sửa `tien_mat` sau này (24h) không tự
		   động kéo `nop_so_tien` theo, vì nó là một cột lưu riêng, không phải tính lại mỗi lần đọc.
		   Chỉ tự sửa lại khi dòng này trước đó ĐÃ NỘP DUY NHẤT VỪA ĐỦ đúng số `tien_mat` cũ (case
		   phổ biến nhất — "nộp đủ" là mặc định) → nộp cũng sửa theo đúng số MỚI, vẫn coi là đủ.
		   Trường hợp nộp DỞ DANG (nop_so_tien khác tien_mat cũ) thì KHÔNG đụng vào — không đủ dữ
		   kiện để biết phải cộng/trừ phần chênh vào đâu giữa nhiều ghế cùng report, để nguyên cho
		   kế toán tự đối chiếu còn hơn đoán sai. */
		$tien_mat_cu = (int) $d['tien_mat'];
		if ( $tien_mat_cu !== (int) $r['tien_mat'] && 'unpaid' !== (string) $d['nop_trang_thai']
			&& (int) $d['nop_so_tien'] === $tien_mat_cu ) {
			$data_up['nop_so_tien'] = $r['tien_mat'];
		}
		if ( count( $anh_moi ) ) { $data_up['anh'] = wp_json_encode( $anh_tong ); }
		$wpdb->update( VHG_DB::t( 'bc_dong' ), $data_up, array( 'id' => (int) $d['id'] ) );
		$wpdb->update( VHG_DB::t( 'bc' ), array( 'sua_luc' => current_time( 'mysql' ) ), array( 'report_id' => $rid ) );
		self::noi_tiep( $ma, $h['ngay'] );   // sửa chỉ số sau → ngày kế tiếp tự nối lại chỉ số trước
		$yc = self::dong_yeucau_( $h['coso'], $h['ngay'], $q['ten'] . ' · sửa 24h · ' . $rid );
		return array( 'ok' => true, 'dongYeuCau' => $yc );
	}

	public static function lich_su( $thang, $pin ) {
		global $wpdb;
		$q = self::pin_info( $pin );
		if ( ! $q ) { return array(); }
		$thang = preg_match( '/^\d{4}-\d{2}$/', (string) $thang ) ? (string) $thang : current_time( 'Y-m' );
		$dong = $wpdb->get_results( $wpdb->prepare(
			'SELECT d.*, h.coso FROM ' . VHG_DB::t( 'bc_dong' ) . ' d JOIN ' . VHG_DB::t( 'bc' ) . ' h ON h.report_id=d.report_id'
			. ' WHERE DATE_FORMAT(d.ngay,%s)=%s AND (d.chi_so_sau IS NOT NULL OR d.tong<>0 OR d.actual<>0) ORDER BY d.ngay DESC', '%Y-%m', $thang ), ARRAY_A );
		$ra = array();
		foreach ( (array) $dong as $d ) {
			if ( ! self::trong_pham_vi( $q, $d['coso'], $d['ma_may'] ) ) { continue; }
			$ra[] = array( 'date' => self::ngay_( $d['ngay'] ), 'locName' => $d['coso'], 'chairCode' => $d['ma_may'],
				'cash' => (int) $d['tien_mat'], 'qr' => (int) $d['qr'], 'total' => (int) $d['tong'] );
		}
		return $ra;
	}

	/**
	 * LỊCH SỬ CHỐT CA của CHÍNH nhân viên đang xem — anh Thắng 29/08/2026: "Bổ sung lịch sử chốt
	 * ca nhân viên". Khác với `lich_su()` (doanh thu từng ghế/ngày): đây là lịch sử TỪNG NGÀY đã
	 * chốt ca ra sao — đủ báo cáo hết cơ sở hay chốt sớm (kèm lý do, cơ sở bỏ qua), đọc thẳng từ
	 * `bc_phien` (một dòng/ngày/nhân viên, ghi mỗi lần gửi báo cáo — xem `phien_upsert_()`/
	 * `chot_som()`). Lọc theo đúng PIN đang gọi — không cần `trong_pham_vi()` như các hàm khác,
	 * vì `bc_phien.pin` vốn đã là PIN của chính người đó.
	 */
	public static function lich_su_ca( $thang, $pin ) {
		global $wpdb;
		$q = self::pin_info( $pin );
		if ( ! $q ) { return array(); }
		$pin  = trim( (string) $pin );
		$thang = preg_match( '/^\d{4}-\d{2}$/', (string) $thang ) ? (string) $thang : current_time( 'Y-m' );
		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . VHG_DB::t( 'bc_phien' ) . ' WHERE pin=%s AND DATE_FORMAT(ngay,%s)=%s ORDER BY ngay DESC',
			$pin, '%Y-%m', $thang ), ARRAY_A );
		$ra = array();
		foreach ( (array) $rows as $r ) {
			$ra[] = array(
				'ngay' => self::ngay_( $r['ngay'] ),
				'trangThai' => (string) $r['trang_thai'],
				'chotSom' => (bool) (int) $r['chot_som'],
				'soCoSo' => (int) $r['so_coso'], 'soCoSoXong' => (int) $r['so_coso_xong'],
				'tongTienMat' => (int) $r['tong_tien_mat'], 'tongQr' => (int) $r['tong_qr'], 'tong' => (int) $r['tong'],
				'lyDo' => (string) $r['ly_do'],
				'boQua' => array_values( array_filter( array_map( 'trim', preg_split( '/[;,]/', (string) $r['bo_qua'] ) ) ) ),
				'guiLuc' => (string) $r['gui_luc'],
			);
		}
		return $ra;
	}

	public static function chua_nop( $pin ) {
		global $wpdb;
		$q = self::pin_info( $pin );
		if ( ! $q ) { return array(); }
		$hs = $wpdb->get_results( 'SELECT * FROM ' . VHG_DB::t( 'bc' ) . ' ORDER BY ngay DESC LIMIT 500', ARRAY_A );
		$ra = array();
		foreach ( (array) $hs as $h ) {
			if ( ! self::trong_pham_vi( $q, $h['coso'] ) ) { continue; }
			$s = self::tong_bc_( $h['report_id'] );
			$con = $s['tien_mat'] - (int) $h['nop_so_tien'];
			if ( $con <= 0 ) { continue; }
			$ra[] = array( 'reportId' => $h['report_id'], 'date' => self::ngay_( $h['ngay'] ),
				'locName' => $h['coso'], 'staff' => $h['nhan_vien'], 'need' => $s['tien_mat'], 'paid' => (int) $h['nop_so_tien'] );
		}
		return $ra;
	}

	public static function nop_bosung( $rid, $ngay, $so_tien, $hinhthuc, $pin ) {
		global $wpdb;
		$q = self::pin_info( $pin );
		if ( ! $q ) { return array( 'ok' => false, 'ma' => 'het_phien', 'message' => 'PIN không hợp lệ.' ); }
		$rid = (string) $rid;
		$h = self::header_theo_id_( $rid );
		if ( ! $h ) { return array( 'ok' => false, 'message' => 'Không thấy báo cáo.' ); }
		if ( ! self::trong_pham_vi( $q, $h['coso'] ) ) { return array( 'ok' => false, 'message' => 'Báo cáo này không thuộc phạm vi của bạn.' ); }
		if ( self::dang_khoa( $h['coso'], $h['ngay'] ) ) { return array( 'ok' => false, 'message' => 'Ngày ' . self::ngay_( $h['ngay'] ) . ' đang KHOÁ — nhờ kế toán.' ); }
		$s = self::tong_bc_( $rid );
		$con = max( 0, $s['tien_mat'] - (int) $h['nop_so_tien'] );
		$raw = ( '' === $so_tien || null === $so_tien ) ? null : (int) $so_tien;
		$add = ( null === $raw ) ? $con : $raw;
		$wpdb->update( VHG_DB::t( 'bc' ), array(
			'nop_so_tien' => (int) $h['nop_so_tien'] + $add,
			'nop_ngay' => self::ngay_( $ngay ? $ngay : current_time( 'Y-m-d' ) ),
			'nop_trang_thai' => ( 'transfer' === $hinhthuc ) ? 'paid_transfer' : 'paid_cash',
			'nop_ghichu' => trim( (string) $h['nop_ghichu'] . ' | bổ sung ' . current_time( 'Y-m-d' ) ) ), array( 'report_id' => $rid ) );
		return array( 'ok' => true, 'add' => $add, 'conThieu' => $con );
	}

	// ══════════════════════════════════════════════════════════════════ ĐỀ NGHỊ CHỈ SỐ

	public static function denghi_gui( $p, $pin ) {
		global $wpdb;
		$q = self::pin_info( $pin );
		if ( ! $q ) { return array( 'ok' => false, 'ma' => 'het_phien', 'message' => 'PIN không hợp lệ.' ); }
		$p = is_array( $p ) ? $p : array();
		$code = trim( (string) ( isset( $p['chairCode'] ) ? $p['chairCode'] : '' ) );
		$from = self::ngay_( isset( $p['fromDate'] ) ? $p['fromDate'] : '' );
		$loai = ( 'xoa' === ( isset( $p['loai'] ) ? $p['loai'] : '' ) ) ? 'xoa' : 'dat_lai';
		$lydo = trim( (string) ( isset( $p['lyDo'] ) ? $p['lyDo'] : '' ) );
		if ( '' === $code ) { return array( 'ok' => false, 'message' => 'Thiếu mã ghế.' ); }
		if ( '' === $from ) { return array( 'ok' => false, 'message' => 'Ngày áp dụng không đúng.' ); }
		if ( '' === $lydo ) { return array( 'ok' => false, 'message' => 'Phải ghi lý do.' ); }
		$so = null;
		if ( 'dat_lai' === $loai ) {
			$raw = trim( (string) ( isset( $p['meterOpening'] ) ? $p['meterOpening'] : '' ) );
			if ( '' === $raw ) { return array( 'ok' => false, 'message' => 'Phải nhập chỉ số đề nghị.' ); }
			$so = (int) preg_replace( '/[^\d-]/', '', $raw );
		}
		$m = null;
		foreach ( VHG_May::ds_may() as $x ) { if ( (string) $x['ma'] === $code ) { $m = $x; break; } }
		if ( ! $m ) { return array( 'ok' => false, 'message' => 'Không thấy ghế ' . $code . '.' ); }
		if ( ! self::trong_pham_vi( $q, (string) $m['coso_ten'], $code ) ) { return array( 'ok' => false, 'message' => 'Ghế này không thuộc phạm vi của bạn.' ); }
		$trung = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . VHG_DB::t( 'bc_denghi' ) . ' WHERE ma_may=%s AND tu_ngay=%s AND trang_thai=%s', $code, $from, 'cho_duyet' ) );
		if ( $trung ) { return array( 'ok' => false, 'message' => 'Ghế này đã có đề nghị cùng ngày đang chờ duyệt.' ); }
		$id = 'DN-' . current_time( 'YmdHis' ) . '-' . $code;
		$wpdb->insert( VHG_DB::t( 'bc_denghi' ), array( 'id' => $id, 'tao_luc' => current_time( 'mysql' ), 'nhan_vien' => $q['ten'],
			'coso' => (string) $m['coso_ten'], 'ma_may' => $code, 'ten' => (string) ( '' !== (string) $m['ten_khai'] ? $m['ten_khai'] : $code ),
			'tu_ngay' => $from, 'chi_so' => $so, 'loai' => $loai, 'ly_do' => mb_substr( $lydo, 0, 250 ), 'trang_thai' => 'cho_duyet' ) );
		return array( 'ok' => true, 'id' => $id, 'message' => 'Đã gửi đề nghị, chờ kế toán duyệt.' );
	}

	public static function denghi_ds( $coso, $pin ) {
		global $wpdb;
		$q = self::pin_info( $pin );
		if ( ! $q ) { return array(); }
		$rows = $wpdb->get_results( 'SELECT * FROM ' . VHG_DB::t( 'bc_denghi' ) . ' ORDER BY id DESC LIMIT 50', ARRAY_A );
		$ra = array();
		foreach ( (array) $rows as $d ) {
			if ( '' !== trim( (string) $coso ) && self::squash( $coso ) !== self::squash( $d['coso'] ) ) { continue; }
			if ( ! self::trong_pham_vi( $q, $d['coso'], $d['ma_may'] ) ) { continue; }
			$ra[] = array( 'chairCode' => $d['ma_may'], 'chairName' => $d['ten'], 'loai' => $d['loai'],
				'meterOpening' => self::songuyen_( $d['chi_so'] ), 'fromDate' => self::ngay_( $d['tu_ngay'] ),
				'trangThai' => $d['trang_thai'], 'lyDo' => $d['ly_do'], 'duyetBoi' => $d['duyet_boi'], 'ghiChuKeToan' => $d['ghi_chu_kt'] );
		}
		return $ra;
	}

	// ══════════════════════════════════════════════════════════════════ YÊU CẦU KẾ TOÁN

	public static function yeucau_ds( $pin ) {
		global $wpdb;
		$q = self::pin_info( $pin );
		if ( ! $q ) { return array( 'ok' => false, 'rows' => array() ); }
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . VHG_DB::t( 'bc_yeucau' ) . ' WHERE trang_thai=%s ORDER BY ngay DESC LIMIT 100', 'cho_lam' ), ARRAY_A );
		$ra = array();
		foreach ( (array) $rows as $y ) {
			if ( '' === trim( (string) $y['coso'] ) ) { continue; }
			if ( ! self::trong_pham_vi( $q, $y['coso'] ) ) { continue; }
			$ra[] = array( 'id' => $y['id'], 'coSo' => $y['coso'], 'ngay' => self::ngay_( $y['ngay'] ),
				'loai' => $y['loai'], 'loaiChu' => ( 'sua' === $y['loai'] ? 'Sửa báo cáo' : 'Làm bổ sung' ),
				'noiDung' => $y['noi_dung'], 'taoLuc' => (string) $y['tao_luc'] );
		}
		return array( 'ok' => true, 'rows' => $ra );
	}

	private static function dong_yeucau_( $coso, $ngay, $boi ) {
		global $wpdb;
		return (int) $wpdb->query( $wpdb->prepare(
			'UPDATE ' . VHG_DB::t( 'bc_yeucau' ) . " SET trang_thai='da_lam', xong_luc=%s, xong_boi=%s WHERE trang_thai=%s AND coso_key=%s AND ngay=%s",
			current_time( 'mysql' ), (string) $boi, 'cho_lam', self::squash( $coso ), self::ngay_( $ngay ) ) );
	}

	// ══════════════════════════════════════════════════════════════════ PHIÊN THU MỘT NGÀY

	/**
	 * TÌNH TRẠNG PHIÊN THU của một nhân viên trong một ngày.
	 *
	 * Anh Thắng 27/08/2026: nhập tới máy cuối → hệ thống báo ĐỦ BÁO CÁO rồi gộp cả ngày gửi kế
	 * toán. Nên phải biết: nhân viên phải thu MẤY cơ sở (phạm vi PIN), đã gửi được mấy, còn thiếu
	 * cơ sở nào, tổng tiền tới giờ. Suy TRỰC TIẾP từ dữ liệu (bảng `bc`) + dòng phiên (`bc_phien`),
	 * không giữ hai bản số dễ lệch.
	 */
	public static function phien_tinh( $pin, $ngay ) {
		global $wpdb;
		$q = self::pin_info( $pin );
		if ( ! $q ) { return null; }
		$ngay = self::ngay_( $ngay );
		if ( '' === $ngay ) { $ngay = current_time( 'Y-m-d' ); }

		/* Phạm vi cơ sở phải thu = các cơ sở có ghế thuộc PIN (gồm cả ghế lẻ). */
		$scope_key = array();
		foreach ( self::ds_ghe( $q ) as $g ) {
			$c = (string) $g['coso'];
			if ( '' !== $c ) { $scope_key[ self::squash( $c ) ] = $c; }
		}

		/* Cơ sở ĐÃ gửi báo cáo hôm nay (có ít nhất 1 ghế thực nhập), trong phạm vi.
		   Cộng dồn theo coso_key — một cơ sở có thể có NHIỀU report_id trong ngày (thu nhiều lần,
		   xem v1.63.0/1.63.4), theo_coso phải GỘP hết các lần lại mới ra đúng tổng của cơ sở đó. */
		$done = array(); $theo_coso = array(); $tm = 0; $qr = 0; $tg = 0;
		$hs = $wpdb->get_results( $wpdb->prepare(
			'SELECT report_id, coso, coso_key FROM ' . VHG_DB::t( 'bc' ) . ' WHERE ngay=%s', $ngay ), ARRAY_A );
		foreach ( (array) $hs as $h ) {
			if ( ! isset( $scope_key[ $h['coso_key'] ] ) ) { continue; }
			$s = self::tong_bc_( $h['report_id'] );
			if ( $s['so'] <= 0 ) { continue; }
			$done[ $h['coso_key'] ] = $h['coso'];
			if ( ! isset( $theo_coso[ $h['coso_key'] ] ) ) {
				$theo_coso[ $h['coso_key'] ] = array( 'ten' => $h['coso'], 'tien_mat' => 0, 'qr' => 0, 'tong' => 0 );
			}
			$theo_coso[ $h['coso_key'] ]['tien_mat'] += $s['tien_mat'];
			$theo_coso[ $h['coso_key'] ]['qr']       += $s['qr'];
			$theo_coso[ $h['coso_key'] ]['tong']     += $s['tong'];
			$tm += $s['tien_mat']; $qr += $s['qr']; $tg += $s['tong'];
		}

		$conlai = array();
		foreach ( $scope_key as $k => $ten ) { if ( ! isset( $done[ $k ] ) ) { $conlai[] = $ten; } }
		$so = count( $scope_key ); $xong = count( $done );

		$row = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . VHG_DB::t( 'bc_phien' ) . ' WHERE pin=%s AND ngay=%s',
			trim( (string) $pin ), $ngay ), ARRAY_A );

		return array(
			'ngay' => $ngay, 'staff' => $q['ten'],
			'so_coso' => $so, 'so_coso_xong' => $xong,
			'du' => ( $so > 0 && $xong >= $so ),
			'coso_scope' => array_values( $scope_key ),
			'coso_xong'  => array_values( $done ),
			'coso_conlai' => $conlai,
			'theo_coso' => array_values( $theo_coso ),   // {ten,tien_mat,qr,tong} mỗi cơ sở đã gửi
			'tong_tien_mat' => $tm, 'tong_qr' => $qr, 'tong' => $tg,
			'trang_thai' => $row ? (string) $row['trang_thai'] : ( ( $so > 0 && $xong >= $so ) ? 'da_gui' : 'dang_thu' ),
			'chot_som' => $row ? (int) $row['chot_som'] : 0,
			'ly_do' => $row ? (string) $row['ly_do'] : '',
			'bo_qua' => $row ? array_values( array_filter( array_map( 'trim', preg_split( '/[;,]/', (string) $row['bo_qua'] ) ) ) ) : array(),
			'gui_luc' => $row ? (string) $row['gui_luc'] : '',
		);
	}

	/** Ghi/cập nhật dòng phiên sau mỗi lần gửi báo cáo. Trả tình trạng đã tính. */
	private static function phien_upsert_( $pin, $ngay ) {
		global $wpdb;
		$st = self::phien_tinh( $pin, $ngay );
		if ( ! $st ) { return null; }
		$pin = trim( (string) $pin );
		$now = current_time( 'mysql' );
		$cur = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . VHG_DB::t( 'bc_phien' ) . ' WHERE pin=%s AND ngay=%s', $pin, $st['ngay'] ), ARRAY_A );
		/* Đã chốt sớm thì GIỮ nguyên trạng thái đó (không tự lật lại); chưa mà đủ cơ sở → 'da_gui'. */
		$tt = ( $cur && 'chot_som' === $cur['trang_thai'] ) ? 'chot_som' : ( $st['du'] ? 'da_gui' : 'dang_thu' );
		$data = array( 'pin' => $pin, 'ngay' => $st['ngay'], 'nhan_vien' => $st['staff'],
			'trang_thai' => $tt, 'so_coso' => $st['so_coso'], 'so_coso_xong' => $st['so_coso_xong'],
			'tong_tien_mat' => $st['tong_tien_mat'], 'tong_qr' => $st['tong_qr'], 'tong' => $st['tong'], 'sua_luc' => $now );
		if ( $cur ) {
			if ( 'da_gui' === $tt && empty( $cur['gui_luc'] ) ) { $data['gui_luc'] = $now; }
			$wpdb->update( VHG_DB::t( 'bc_phien' ), $data, array( 'pin' => $pin, 'ngay' => $st['ngay'] ) );
		} else {
			$data['tao_luc'] = $now;
			if ( 'da_gui' === $tt ) { $data['gui_luc'] = $now; }
			$wpdb->insert( VHG_DB::t( 'bc_phien' ), $data );
		}
		$st['trang_thai'] = $tt;
		return $st;
	}

	/** Tình trạng phiên cho giao diện (endpoint riêng). */
	public static function phien( $pin, $ngay ) {
		$st = self::phien_tinh( $pin, $ngay );
		if ( ! $st ) { return array( 'ok' => false, 'ma' => 'het_phien', 'message' => 'PIN không hợp lệ.' ); }
		$st['ok'] = true;
		return $st;
	}

	/**
	 * XIN CHỐT CA SỚM — còn 1–2 điểm chưa thu được thì chốt luôn phần đã thu, gửi kế toán.
	 *
	 * Anh Thắng 27/08/2026. Bắt buộc lý do (điểm nào chưa thu, vì sao). Ghi lại danh sách cơ sở
	 * BỎ QUA để kế toán biết còn thiếu, KHÔNG âm thầm coi như xong.
	 */
	public static function chot_som( $ngay, $ly_do, $pin ) {
		global $wpdb;
		$q = self::pin_info( $pin );
		if ( ! $q ) { return array( 'ok' => false, 'ma' => 'het_phien', 'message' => 'PIN không hợp lệ — đăng nhập lại.' ); }
		$ly = trim( (string) $ly_do );
		if ( '' === $ly ) { return array( 'ok' => false, 'message' => 'Phải ghi lý do xin chốt sớm (điểm nào chưa thu, vì sao).' ); }
		$st = self::phien_tinh( $pin, $ngay );
		if ( ! $st ) { return array( 'ok' => false, 'message' => 'Không đọc được phiên thu.' ); }
		if ( $st['so_coso_xong'] <= 0 ) {
			return array( 'ok' => false, 'message' => 'Chưa gửi báo cáo cơ sở nào — không có gì để chốt.' );
		}
		$pin = trim( (string) $pin ); $now = current_time( 'mysql' );
		$bo = implode( ', ', $st['coso_conlai'] );
		$data = array( 'pin' => $pin, 'ngay' => $st['ngay'], 'nhan_vien' => $st['staff'],
			'trang_thai' => 'chot_som', 'chot_som' => 1, 'ly_do' => mb_substr( $ly, 0, 250 ),
			'bo_qua' => mb_substr( $bo, 0, 1000 ),
			'so_coso' => $st['so_coso'], 'so_coso_xong' => $st['so_coso_xong'],
			'tong_tien_mat' => $st['tong_tien_mat'], 'tong_qr' => $st['tong_qr'], 'tong' => $st['tong'],
			'gui_luc' => $now, 'sua_luc' => $now );
		$cur = $wpdb->get_var( $wpdb->prepare(
			'SELECT pin FROM ' . VHG_DB::t( 'bc_phien' ) . ' WHERE pin=%s AND ngay=%s', $pin, $st['ngay'] ) );
		if ( $cur ) { $wpdb->update( VHG_DB::t( 'bc_phien' ), $data, array( 'pin' => $pin, 'ngay' => $st['ngay'] ) ); }
		else { $data['tao_luc'] = $now; $wpdb->insert( VHG_DB::t( 'bc_phien' ), $data ); }
		$st['trang_thai'] = 'chot_som'; $st['chot_som'] = 1; $st['bo_qua'] = $st['coso_conlai'];
		return array( 'ok' => true, 'phien' => $st,
			'message' => 'Đã chốt ca sớm ngày ' . $st['ngay'] . ' — gửi kế toán ' . $st['so_coso_xong']
				. '/' . $st['so_coso'] . ' cơ sở.' . ( count( $st['coso_conlai'] ) ? ( ' Bỏ qua: ' . $bo . '.' ) : '' ) );
	}

	// ══════════════════════════════════════════════════════════════════ ĐỐI CHIẾU MÁY ONLINE

	/**
	 * ĐỐI CHIẾU BÁO CÁO ↔ MÁY ONLINE — "xem nhân viên thu đúng các giá trị chưa" (anh Thắng 27/08).
	 *
	 * So từng ghế trong ngày:
	 *   · QR:       báo cáo nhân viên nhập  ↔  QR webhook ngân hàng đẩy về (số CHUẨN, khớp phải bằng).
	 *   · Tiền mặt: `actual` (máy đếm = (sau−trước)×đơn_vị)  ↔  tiền mặt ghế TỰ BÁO về (`ND_GHE_NUOT`).
	 *               Lệch = ghế mất mạng / sót xung — cùng bản chất `lech_may` của chốt ca.
	 *
	 * CHỈ ĐỌC, không ghi gì. Trả cả số khớp lẫn số lệch để giao diện tô ghế lệch cho nhân viên soát.
	 *
	 * ⚠️ Cắt theo NGÀY (`DATE(luc)`). Máy chủ hệ này lệch múi ~7h (xem VHG_Quy::may_bao), nên giao
	 *    dịch sát nửa đêm có thể rơi lệch ngày một chút — con số tiền mặt ở đây là để SOÁT, không
	 *    phải để ghi sổ. QR đối chiếu theo cùng ngày báo cáo nên vẫn là thước chính.
	 */
	public static function doi_chieu( $pin, $ngay ) {
		global $wpdb;
		$q = self::pin_info( $pin );
		if ( ! $q ) { return array( 'ok' => false, 'ma' => 'het_phien', 'message' => 'PIN không hợp lệ.' ); }
		$ngay = self::ngay_( $ngay );
		if ( '' === $ngay ) { $ngay = current_time( 'Y-m-d' ); }
		/* Mốc SAU cùng dạng 'Y-m-d 00:00:00' để so bằng khoảng — xem lý do đổi ở khối truy vấn
		   bên dưới. DATETIME không mang múi giờ nên đây vẫn LÀ đúng ngày lịch được lưu, không
		   phải một phép quy đổi múi giờ nào khác. */
		$ngay_sau = gmdate( 'Y-m-d', strtotime( $ngay . ' +1 day' ) );

		$scope_key = array();
		foreach ( self::ds_ghe( $q ) as $g ) {
			if ( '' !== (string) $g['coso'] ) { $scope_key[ self::squash( $g['coso'] ) ] = true; }
		}

		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT d.qr, d.actual, d.ma_may, d.ten, h.coso, h.coso_key FROM ' . VHG_DB::t( 'bc_dong' ) . ' d'
			. ' JOIN ' . VHG_DB::t( 'bc' ) . ' h ON h.report_id=d.report_id'
			. ' WHERE d.ngay=%s AND (d.chi_so_sau IS NOT NULL OR d.tong<>0 OR d.actual<>0)', $ngay ), ARRAY_A );
		$rows = array_values( array_filter( (array) $rows, function ( $r ) use ( $scope_key ) {
			return isset( $scope_key[ $r['coso_key'] ] );
		} ) );

		/* 🔴 GOM MỘT LƯỢT CHO CẢ NGÀY, KHÔNG HỎI TỪNG GHẾ.
		   Anh Thắng gặp "Không đọc được trả lời của máy chủ" khi bấm Đối chiếu máy ở cơ sở nhiều
		   ghế. Bản cũ hỏi HAI câu riêng cho MỖI ghế (`WHERE ma_may=%s AND DATE(luc)=%s ...`) —
		   cơ sở vài chục ghế thành vài chục lượt hỏi DB trong một lần bấm, mà `DATE(luc)=%s` lại
		   bọc cột `luc` trong một hàm nên MySQL không dùng được phần `luc` của khoá `may
		   (ma_may,luc)` để giới hạn khoảng — mỗi lượt phải dò hết mọi hàng của riêng máy đó,
		   không chỉ hàng của ngày đang xem. Cộng dồn nhiều chục lượt như vậy là vượt hẳn 25 giây
		   goi() chờ, và trình duyệt báo "mạng hoặc tường lửa" — đúng như lỗi anh Thắng gặp.
		   Sửa hai chỗ cùng lúc: (1) đổi sang `luc>=... AND luc<...` — dạng khoảng, dùng được cả
		   hai cột của khoá `may`; (2) gom lại đúng HAI câu `GROUP BY ma_may` cho MỌI ghế cần
		   trong một lượt, tra kết quả bằng mã máy trong bộ nhớ thay vì hỏi lại DB từng ghế. */
		$may_qr_map   = array();
		$may_cash_map = array();
		$ma_ds = array();
		foreach ( $rows as $r ) { $ma_ds[ (string) $r['ma_may'] ] = true; }
		$ma_ds = array_keys( $ma_ds );
		if ( $ma_ds ) {
			$tThu = VHG_DB::t( 'thu' );
			$cho  = implode( ',', array_fill( 0, count( $ma_ds ), '%s' ) );
			foreach ( $wpdb->get_results( $wpdb->prepare(
				"SELECT ma_may, COALESCE(SUM(so_tien),0) AS tong FROM $tThu"
				. " WHERE luc>=%s AND luc<%s AND huy=0 AND nguon<>%s AND ma_may IN ($cho) GROUP BY ma_may",
				array_merge( array( $ngay . ' 00:00:00', $ngay_sau . ' 00:00:00', VHG_Thu::TIEN_MAT ), $ma_ds )
			), ARRAY_A ) as $x ) {
				$may_qr_map[ (string) $x['ma_may'] ] = (int) $x['tong'];
			}
			foreach ( $wpdb->get_results( $wpdb->prepare(
				"SELECT ma_may, COALESCE(SUM(so_tien),0) AS tong FROM $tThu"
				. " WHERE luc>=%s AND luc<%s AND huy=0 AND nguon=%s AND noi_dung=%s AND ma_may IN ($cho) GROUP BY ma_may",
				array_merge( array( $ngay . ' 00:00:00', $ngay_sau . ' 00:00:00', VHG_Thu::TIEN_MAT, VHG_Thu::ND_GHE_NUOT ), $ma_ds )
			), ARRAY_A ) as $x ) {
				$may_cash_map[ (string) $x['ma_may'] ] = (int) $x['tong'];
			}
		}

		$ds = array();
		$tong = array( 'bc_qr' => 0, 'may_qr' => 0, 'bc_actual' => 0, 'may_cash' => 0,
			'lech_qr' => 0, 'lech_cash' => 0, 'so_lech' => 0 );
		foreach ( $rows as $r ) {
			$ma = (string) $r['ma_may'];
			$may_qr   = isset( $may_qr_map[ $ma ] ) ? $may_qr_map[ $ma ] : 0;
			$may_cash = isset( $may_cash_map[ $ma ] ) ? $may_cash_map[ $ma ] : 0;
			$bc_qr = (int) $r['qr']; $bc_actual = (int) $r['actual'];
			$lq = $bc_qr - $may_qr; $lc = $bc_actual - $may_cash;
			$khop = ( 0 === $lq && 0 === $lc );
			if ( ! $khop ) { $tong['so_lech']++; }
			$tong['bc_qr'] += $bc_qr; $tong['may_qr'] += $may_qr;
			$tong['bc_actual'] += $bc_actual; $tong['may_cash'] += $may_cash;
			$tong['lech_qr'] += $lq; $tong['lech_cash'] += $lc;
			$ds[] = array( 'ma_may' => $ma, 'ten' => (string) $r['ten'], 'coso' => (string) $r['coso'],
				'bc_qr' => $bc_qr, 'may_qr' => $may_qr, 'lech_qr' => $lq,
				'bc_actual' => $bc_actual, 'may_cash' => $may_cash, 'lech_cash' => $lc,
				'khop' => $khop ? 1 : 0 );
		}
		return array( 'ok' => true, 'ngay' => $ngay, 'so_ghe' => count( $ds ),
			'so_lech' => $tong['so_lech'], 'tong' => $tong, 'ghe' => $ds );
	}

	// ══════════════════════════════════════════════════════════════════ HỎI-ĐÁP (hướng dẫn)

	/** Mồi bảng hỏi-đáp nếu trống — CHỈ hướng dẫn thao tác, không dữ liệu nhạy cảm. */
	private static function hoidap_seed_() {
		global $wpdb;
		if ( (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHG_DB::t( 'bc_hoidap' ) ) > 0 ) { return; }
		$seed = array(
			array( 'dang nhap, pin, vao', 'Đăng nhập thế nào?', 'Nhập mã PIN Admin cấp cho bạn. Vào đúng là thấy các cơ sở bạn phụ trách.' ),
			array( 'chi so truoc, khoa, doi chi so, sua chi so', 'Chỉ số trước bị khoá, muốn sửa?', 'Chỉ số trước hệ thống tự lấy từ lần thu trước, không tự sửa. Máy vừa thay/đổi điểm thì vào mục "Đề nghị đổi/xoá chỉ số" gửi kế toán duyệt.' ),
			array( 'du bao cao, gui ke toan, xong', 'Khi nào là "đủ báo cáo"?', 'Nhập xong cơ sở CUỐI trong ngày, hệ thống báo "ĐỦ BÁO CÁO" và gộp gửi kế toán kèm tổng kết tiền.' ),
			array( 'chot som, chua thu, thieu diem', 'Còn điểm chưa thu được thì sao?', 'Bấm "Xin chốt ca sớm", ghi rõ điểm nào chưa thu và lý do — hệ thống chốt phần đã thu, phần còn lại ghi bỏ qua.' ),
			array( 'nop bo sung, nop bu, chua nop', 'Nộp bù báo cáo hôm trước?', 'Vào mục "Nộp bổ sung", chọn báo cáo còn thiếu rồi nhập số đã nộp (tiền mặt hoặc chuyển khoản).' ),
			array( 'sua, 24h, nham', 'Nhập nhầm, sửa được không?', 'Báo cáo trong 24 giờ sửa được ở mục "Báo cáo trong 24h": bung ra sửa chỉ số sau/QR/tăng-giảm/ghi chú rồi Lưu.' ),
			array( 'anh, hinh, chup', 'Gửi ảnh thế nào?', 'Ở thẻ "Ảnh báo cáo" chọn ảnh theo thứ tự ghế (2 ảnh/ghế). Ảnh chứng từ nộp tiền chọn ở ô bên dưới. Web tự nén trước khi gửi.' ),
			array( 'doi chieu, may, lech', 'Đối chiếu máy là gì?', 'Bấm "Đối chiếu máy" để so QR và tiền mặt bạn nhập với số máy/ngân hàng báo về. Ghế nào lệch sẽ tô đỏ để kiểm lại.' ),
		);
		foreach ( $seed as $i => $s ) {
			$wpdb->insert( VHG_DB::t( 'bc_hoidap' ), array( 'tu_khoa' => $s[0], 'cau_hoi' => $s[1],
				'tra_loi' => $s[2], 'thu_tu' => $i + 1, 'active' => 1 ) );
		}
	}

	/** Trả lời câu hỏi hướng dẫn — khớp từ khoá. Rỗng → trả gợi ý. Không khớp → truot. */
	public static function hoi_dap( $pin, $cau ) {
		global $wpdb;
		$q = self::pin_info( $pin );
		if ( ! $q ) { return array( 'ok' => false, 'ma' => 'het_phien', 'message' => 'PIN không hợp lệ.' ); }
		self::hoidap_seed_();
		$rows = $wpdb->get_results( 'SELECT tu_khoa, cau_hoi, tra_loi FROM ' . VHG_DB::t( 'bc_hoidap' )
			. ' WHERE active=1 ORDER BY thu_tu ASC, id ASC', ARRAY_A );
		$goiY = array();
		foreach ( (array) $rows as $r ) { if ( count( $goiY ) < 6 ) { $goiY[] = (string) $r['cau_hoi']; } }
		$c = trim( (string) $cau );
		if ( '' === $c ) { return array( 'ok' => true, 'goiY' => $goiY ); }
		$sq = self::squash( $c );
		$best = null; $diem = 0;
		foreach ( (array) $rows as $r ) {
			$s = 0;
			foreach ( preg_split( '/[;,]/', (string) $r['tu_khoa'] ) as $kw ) {
				$k = self::squash( $kw );
				if ( '' !== $k && strpos( $sq, $k ) !== false ) { $s++; }
			}
			if ( '' !== self::squash( $r['cau_hoi'] ) && $sq === self::squash( $r['cau_hoi'] ) ) { $s += 5; }
			if ( $s > $diem ) { $diem = $s; $best = $r; }
		}
		if ( ! $best || $diem <= 0 ) { return array( 'ok' => true, 'truot' => true, 'goiY' => $goiY ); }
		return array( 'ok' => true, 'cauHoi' => (string) $best['cau_hoi'], 'traLoi' => (string) $best['tra_loi'], 'goiY' => $goiY );
	}

	// ══════════════════════════════════════════════════════════════════ QUẢN LÝ PIN (Admin)

	/** Danh sách PIN (cho màn Admin). CHỈ Admin gọi (gác ở tầng trang). */
	public static function pin_ds() {
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT * FROM ' . VHG_DB::t( 'bc_pin' ) . ' ORDER BY ten ASC', ARRAY_A );
		$ra = array();
		foreach ( (array) $rows as $r ) {
			$ra[] = array( 'pin' => $r['pin'], 'ten' => $r['ten'], 'coso' => $r['coso'],
				'ghe' => $r['ghe'], 'active' => (int) $r['active'] );
		}
		return $ra;
	}

	/** Thêm/sửa 1 PIN. coso/ghe là chuỗi nhiều mục ngăn bởi phẩy hoặc chấm phẩy. */
	public static function pin_luu( $p ) {
		global $wpdb;
		$p = is_array( $p ) ? $p : array();
		$pin = trim( (string) ( isset( $p['pin'] ) ? $p['pin'] : '' ) );
		$ten = trim( (string) ( isset( $p['ten'] ) ? $p['ten'] : '' ) );
		if ( ! preg_match( '/^\d{3,10}$/', $pin ) ) { return array( 'ok' => false, 'error' => 'PIN phải là 3–10 chữ số.' ); }
		if ( '' === $ten ) { return array( 'ok' => false, 'error' => 'Thiếu tên nhân viên.' ); }
		$data = array( 'pin' => $pin, 'ten' => mb_substr( $ten, 0, 190 ),
			'coso' => mb_substr( trim( (string) ( isset( $p['coso'] ) ? $p['coso'] : '' ) ), 0, 2000 ),
			'ghe' => mb_substr( trim( (string) ( isset( $p['ghe'] ) ? $p['ghe'] : '' ) ), 0, 1000 ),
			'active' => empty( $p['active'] ) ? 0 : 1 );
		$co = $wpdb->get_var( $wpdb->prepare( 'SELECT pin FROM ' . VHG_DB::t( 'bc_pin' ) . ' WHERE pin=%s', $pin ) );
		if ( $co ) { $wpdb->update( VHG_DB::t( 'bc_pin' ), $data, array( 'pin' => $pin ) ); }
		else { $data['tao_luc'] = current_time( 'mysql' ); $wpdb->insert( VHG_DB::t( 'bc_pin' ), $data ); }
		return array( 'ok' => true, 'thong_bao' => 'Đã lưu PIN cho ' . $ten . '.' );
	}

	public static function pin_xoa( $pin ) {
		global $wpdb;
		$wpdb->delete( VHG_DB::t( 'bc_pin' ), array( 'pin' => trim( (string) $pin ) ) );
		return array( 'ok' => true, 'thong_bao' => 'Đã xoá PIN.' );
	}
}
