<?php
/**
 * BÁO CÁO DOANH THU THEO CƠ SỞ — port từ web Apps Script "POSH v3 · THU TIỀN (nhân viên)".
 *
 * Anh Thắng 27/08/2026: đưa app thu-tiền-nhập-báo-cáo vào web ghế (tab trên /ghe).
 *
 * 🔴 CÔNG THỨC BẤT BIẾN (giữ y app gốc):
 *      actual   = (chỉ số sau − chỉ số trước) × đơn_vị
 *      tiền mặt = actual − QR ± điều_chỉnh
 *      tổng     = tiền mặt + QR
 *    Server TỰ TÍNH và TỰ ÉP chỉ số trước — KHÔNG tin số client gửi.
 *    App gốc cứng ×10000; ở đây dùng VHG_Quy::don_vi() để KHỚP với chốt ca máy trạm.
 *
 * 🔴 "DÙNG CHUNG BẢNG CHỈ SỐ" với chốt ca: chi_so_truoc() lấy chỉ số sau gần nhất TRƯỚC ngày báo
 *    cáo từ CẢ `bc_dong` LẪN `chot` — một dòng thời gian chỉ số duy nhất, không đếm đôi.
 *
 * 🔴 1 BÁO CÁO / CƠ SỞ / NGÀY: UNIQUE(coso_key, ngay) ở tầng CSDL; gửi lại = cập nhật.
 *
 * ════════════════════════════════════════════════════════════════════════════════════════════
 * PHÂN QUYỀN BẰNG PIN RIÊNG (bảng `bc_pin`), KHÔNG dùng token /ghe.
 *
 * Anh Thắng 27/08/2026: *"mỗi nhân viên 1 PIN, gán cho cơ sở rồi; đăng nhập thấy cơ sở mình. Sau
 * PIN này dùng CHUNG với nhân sự K&H để chấm công, nộp báo cáo và ghi chi phí"*.
 *
 * → PIN là DANH TÍNH CHUNG. Mỗi PIN khai: tên nhân viên + danh sách cơ sở (+ ghế riêng nếu cần).
 *   Đăng nhập báo cáo = nhập PIN (bc_boot). Về sau nối `bc_pin.pin` sang PIN chấm công là một mối.
 *
 * ⛔ REPO CÔNG KHAI → KHÔNG hardcode PIN trong mã. Admin tự nhập bc_pin ở màn cấu hình.
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

	/** Thông tin 1 PIN: [ 'ten', 'coso'=>[...], 'coso_key'=>[...], 'ghe'=>[...] ] hoặc null. */
	public static function pin_info( $pin ) {
		global $wpdb;
		$pin = trim( (string) $pin );
		if ( '' === $pin ) { return null; }
		$r = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . VHG_DB::t( 'bc_pin' ) . ' WHERE pin=%s AND active=1 LIMIT 1', $pin ), ARRAY_A );
		if ( ! $r ) { return null; }
		$tach = function ( $v ) {
			return array_values( array_filter( array_map( 'trim', preg_split( '/[;,]/', (string) $v ) ) ) );
		};
		$coso = $tach( $r['coso'] );
		$ck = array();
		foreach ( $coso as $c ) { $ck[ self::squash( $c ) ] = true; }
		return array( 'ten' => (string) $r['ten'], 'coso' => $coso,
			'coso_key' => $ck, 'ghe' => $tach( $r['ghe'] ) );
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

	/** Ghế trong phạm vi PIN: [ ['ma','ten','coso'], ... ]. */
	public static function ds_ghe( $q ) {
		$ra = array();
		foreach ( VHG_May::ds_may() as $m ) {
			$coso = (string) ( isset( $m['coso_ten'] ) ? $m['coso_ten'] : '' );
			if ( ! self::trong_pham_vi( $q, $coso, (string) $m['ma'] ) ) { continue; }
			$ra[] = array(
				'ma'   => (string) $m['ma'],
				'ten'  => (string) ( '' !== (string) $m['ten_khai'] ? $m['ten_khai'] : $m['ma'] ),
				'coso' => $coso,
			);
		}
		return $ra;
	}

	// ══════════════════════════════════════════════════════════════════ CHỈ SỐ (dùng chung)

	/**
	 * CHỈ SỐ TRƯỚC = chỉ số sau gần nhất có ngày < $ngay, lấy CẢ `bc_dong` LẪN `chot`; có mốc reset
	 * (`may.moc_chiso`) hiệu lực ≤ $ngay và mới hơn thì lấy mốc. Trả (int) hoặc null (lần đầu).
	 */
	public static function chi_so_truoc( $ma_may, $ngay ) {
		global $wpdb;
		$ma = (string) $ma_may;
		$ngay = self::ngay_( $ngay );
		if ( '' === $ma || '' === $ngay ) { return null; }
		$found_cs = null; $found_d = '';
		$r1 = $wpdb->get_row( $wpdb->prepare(
			'SELECT chi_so_sau cs, ngay d FROM ' . VHG_DB::t( 'bc_dong' )
			. ' WHERE ma_may=%s AND ngay < %s AND chi_so_sau IS NOT NULL ORDER BY ngay DESC, chi_so_sau DESC LIMIT 1',
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
			if ( $od && $od <= $ngay && ( '' === $found_d || $found_d < $od ) ) { return (int) $mo['cs']; }
		}
		return null === $found_cs ? null : (int) $found_cs;
	}

	public static function lay_chiso_truoc( $codes, $ngay ) {
		$out = array();
		foreach ( (array) $codes as $c ) { $out[ (string) $c ] = self::chi_so_truoc( $c, $ngay ); }
		return $out;
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
		$adj = (int) ( isset( $r['dieu_chinh'] ) ? $r['dieu_chinh'] : 0 );
		$actual = ( null === $before || null === $after ) ? 0 : ( $after - $before ) * $dv;
		$r['chi_so_truoc'] = $before; $r['chi_so_sau'] = $after;
		$r['qr'] = $qr; $r['dieu_chinh'] = $adj;
		$r['actual'] = $actual;
		$r['tien_mat'] = $actual - $qr + $adj;
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
			'coso' => array_keys( $cs ), 'ghe' => $ghe, 'khoa' => $khoa_loc );
	}

	// ══════════════════════════════════════════════════════════════════ 1 BÁO CÁO/NGÀY

	private static function header_( $coso, $ngay ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . VHG_DB::t( 'bc' ) . ' WHERE coso_key=%s AND ngay=%s LIMIT 1',
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
			. ' FROM ' . VHG_DB::t( 'bc_dong' ) . ' WHERE report_id=%s AND chi_so_sau IS NOT NULL', (string) $rid ), ARRAY_A );
		return array( 'so' => (int) $r['so'], 'tien_mat' => (int) $r['tm'], 'qr' => (int) $r['qr'], 'tong' => (int) $r['tg'] );
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
			$truoc_ht = self::chi_so_truoc( $ma, $ngay );
			$before = ( null !== $truoc_ht ) ? $truoc_ht : self::songuyen_( isset( $r0['meterBefore'] ) ? $r0['meterBefore'] : '' );
			$r = array( 'ma_may' => $ma, 'ten' => (string) ( isset( $r0['chairName'] ) ? $r0['chairName'] : $ma ),
				'ngay' => $ngay, 'chi_so_truoc' => $before, 'chi_so_sau' => (int) $after,
				'qr' => (int) ( isset( $r0['qr'] ) ? $r0['qr'] : 0 ),
				'dieu_chinh' => (int) ( isset( $r0['adjust'] ) ? $r0['adjust'] : 0 ),
				'ghi_chu' => mb_substr( trim( (string) ( isset( $r0['note'] ) ? $r0['note'] : '' ) ), 0, 250 ) );
			self::tinh_( $r );
			if ( null !== $r['chi_so_truoc'] && $r['chi_so_sau'] < $r['chi_so_truoc'] ) {
				return array( 'ok' => false, 'message' => 'Ghế ' . $r['ten'] . ': chỉ số sau (' . $r['chi_so_sau']
					. ') nhỏ hơn chỉ số trước (' . $r['chi_so_truoc'] . '). Máy vừa thay/đổi điểm thì gửi đề nghị đổi chỉ số.' );
			}
			$rows[] = $r;
		}
		if ( ! count( $rows ) ) { return array( 'ok' => false, 'message' => 'Chưa nhập chỉ số sau cho ghế nào.' ); }

		$prev = self::header_( $coso, $ngay );
		$rid  = $prev ? (string) $prev['report_id'] : ( 'RPT-' . current_time( 'YmdHis' ) . '-' . wp_rand( 100, 999 ) );
		$now  = current_time( 'mysql' );
		$pay  = self::doc_payment_( isset( $p['payment'] ) ? $p['payment'] : array(), $rows );
		$kt_locked = $prev && ! empty( $prev['kt_doi_soat'] );

		$header = array( 'report_id' => $rid, 'ngay' => $ngay, 'coso' => $coso, 'coso_key' => self::squash( $coso ),
			'nhan_vien' => $q['ten'], 'sua_luc' => $now );
		if ( ! $kt_locked ) {
			$header = array_merge( $header, array(
				'nop_hinhthuc' => $pay['hinhthuc'], 'nop_trang_thai' => $pay['trang_thai'], 'nop_so_tien' => $pay['so_tien'],
				'nop_ngay' => $pay['ngay'], 'nop_ghichu' => $pay['ghichu'], 'unpaid_lydo' => $pay['unpaid_lydo'],
				'ck_ref' => $pay['ck_ref'], 'ck_bank' => $pay['ck_bank'] ) );
		}
		if ( $prev ) { $wpdb->update( VHG_DB::t( 'bc' ), $header, array( 'report_id' => $rid ) ); }
		else { $header['tao_luc'] = $now; $wpdb->insert( VHG_DB::t( 'bc' ), $header ); }

		$ct = self::luu_nhieu_anh_( isset( $p['proofs'] ) ? $p['proofs'] : null, $rid, 'chungtu' );
		if ( count( $ct ) ) { $wpdb->update( VHG_DB::t( 'bc' ), array( 'chung_tu' => wp_json_encode( $ct ) ), array( 'report_id' => $rid ) ); }

		$anh_ghe = self::chia_anh_ghe_( isset( $p['images'] ) ? $p['images'] : array(), count( $rows ), $rid );

		$chia_nop = self::chia_nop_( $rows, $pay );

		$gui_ma = array();
		foreach ( $rows as $i => $r ) {
			$gui_ma[ $r['ma_may'] ] = true;
			$np = isset( $chia_nop[ $r['ma_may'] ] ) ? $chia_nop[ $r['ma_may'] ]
				: array( 'nop_so_tien' => 0, 'nop_trang_thai' => '', 'nop_hinhthuc' => '', 'nop_ngay' => null );
			$data = array( 'report_id' => $rid, 'ma_may' => $r['ma_may'], 'ten' => $r['ten'], 'ngay' => $ngay,
				'chi_so_truoc' => $r['chi_so_truoc'], 'chi_so_sau' => $r['chi_so_sau'], 'actual' => $r['actual'],
				'tien_mat' => $r['tien_mat'], 'qr' => $r['qr'], 'dieu_chinh' => $r['dieu_chinh'],
				'tong' => $r['tong'], 'ghi_chu' => $r['ghi_chu'],
				'nop_so_tien' => $np['nop_so_tien'], 'nop_trang_thai' => $np['nop_trang_thai'],
				'nop_hinhthuc' => $np['nop_hinhthuc'], 'nop_ngay' => $np['nop_ngay'] );
			if ( isset( $anh_ghe[ $i ] ) && count( $anh_ghe[ $i ] ) ) { $data['anh'] = wp_json_encode( $anh_ghe[ $i ] ); }
			$cu = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . VHG_DB::t( 'bc_dong' ) . ' WHERE report_id=%s AND ma_may=%s', $rid, $r['ma_may'] ) );
			if ( $cu ) { $wpdb->update( VHG_DB::t( 'bc_dong' ), $data, array( 'id' => (int) $cu ) ); }
			else { $wpdb->insert( VHG_DB::t( 'bc_dong' ), $data ); }
		}

		$bo = array();
		if ( $prev ) {
			$cu = $wpdb->get_results( $wpdb->prepare( 'SELECT ma_may FROM ' . VHG_DB::t( 'bc_dong' ) . ' WHERE report_id=%s', $rid ), ARRAY_A );
			foreach ( (array) $cu as $c ) {
				$ma = (string) $c['ma_may'];
				if ( isset( $gui_ma[ $ma ] ) ) { continue; }
				$wpdb->update( VHG_DB::t( 'bc_dong' ),
					array( 'chi_so_sau' => null, 'actual' => 0, 'tien_mat' => 0, 'qr' => 0, 'dieu_chinh' => 0, 'tong' => 0,
						'ghi_chu' => 'bỏ khỏi báo cáo lúc gửi lại ' . current_time( 'Y-m-d' ),
						'nop_so_tien' => 0, 'nop_trang_thai' => '', 'nop_hinhthuc' => '', 'nop_ngay' => null ),
					array( 'report_id' => $rid, 'ma_may' => $ma ) );
				$bo[] = $ma;
			}
		}

		$dong_yc = self::dong_yeucau_( $coso, $ngay, $q['ten'] . ' · ' . $rid );
		return array( 'ok' => true, 'reportId' => $rid, 'rows' => count( $rows ), 'updated' => (bool) $prev,
			'boGhe' => $bo, 'dongYeuCau' => $dong_yc,
			'message' => ( $prev ? ( 'Đã CẬP NHẬT báo cáo ' . $coso . ' ngày ' . $ngay . '.' )
				: ( 'Đã gửi báo cáo ' . $coso . ' ngày ' . $ngay . '.' ) )
				. ( count( $bo ) ? ( ' Đã bỏ ' . count( $bo ) . ' ghế.' ) : '' )
				. ( $dong_yc ? ( ' Hoàn thành ' . $dong_yc . ' yêu cầu kế toán.' ) : '' )
				. ( $kt_locked ? ' (Giữ số kế toán đã đối soát.)' : '' ) );
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
	private static function chia_anh_ghe_( $images, $so_ghe, $rid ) {
		$imgs = is_array( $images ) ? array_values( $images ) : array();
		$n = count( $imgs ); $out = array();
		if ( $n <= 0 || $so_ghe <= 0 ) { return $out; }
		$per = max( 1, (int) round( $n / $so_ghe ) ); $k = 0;
		for ( $i = 0; $i < $so_ghe; $i++ ) {
			$from = $i * $per;
			$den = ( $i === $so_ghe - 1 ) ? $n : min( $from + $per, $n );
			$urls = array();
			for ( $j = $from; $j < $den; $j++ ) {
				$u = self::luu_anh_( $imgs[ $j ], $rid, 'ghe' . ( $i + 1 ) . '-' . ( ++$k ) );
				if ( '' !== $u ) { $urls[] = $u; }
			}
			if ( count( $urls ) ) { $out[ $i ] = $urls; }
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
				'SELECT * FROM ' . VHG_DB::t( 'bc_dong' ) . ' WHERE report_id=%s AND chi_so_sau IS NOT NULL ORDER BY id ASC', $h['report_id'] ), ARRAY_A );
			$ghe = array(); $tong = 0;
			foreach ( $dong as $d ) {
				$tong += (int) $d['tong'];
				$ghe[] = array( 'chairCode' => $d['ma_may'], 'chairName' => $d['ten'],
					'meterBefore' => self::songuyen_( $d['chi_so_truoc'] ), 'meterAfter' => self::songuyen_( $d['chi_so_sau'] ),
					'actual' => (int) $d['actual'], 'cash' => (int) $d['tien_mat'], 'qr' => (int) $d['qr'],
					'adjust' => (int) $d['dieu_chinh'], 'note' => $d['ghi_chu'] );
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
		$r = array( 'ma_may' => $ma, 'ngay' => $h['ngay'],
			'chi_so_sau' => array_key_exists( 'meterAfter', $patch ) ? $patch['meterAfter'] : $d['chi_so_sau'],
			'qr' => array_key_exists( 'qr', $patch ) ? (int) $patch['qr'] : (int) $d['qr'],
			'dieu_chinh' => array_key_exists( 'adjust', $patch ) ? (int) $patch['adjust'] : (int) $d['dieu_chinh'],
			'ghi_chu' => array_key_exists( 'note', $patch ) ? mb_substr( trim( (string) $patch['note'] ), 0, 250 ) : $d['ghi_chu'] );
		$truoc = self::chi_so_truoc( $ma, $h['ngay'] );
		$r['chi_so_truoc'] = ( null !== $truoc ) ? $truoc : self::songuyen_( $d['chi_so_truoc'] );
		self::tinh_( $r );
		if ( null !== $r['chi_so_truoc'] && null !== $r['chi_so_sau'] && $r['chi_so_sau'] < $r['chi_so_truoc'] ) {
			return array( 'ok' => false, 'message' => 'Chỉ số sau nhỏ hơn chỉ số trước — gửi đề nghị đổi chỉ số nếu vừa thay máy.' );
		}
		$wpdb->update( VHG_DB::t( 'bc_dong' ), array( 'chi_so_truoc' => $r['chi_so_truoc'], 'chi_so_sau' => $r['chi_so_sau'],
			'actual' => $r['actual'], 'tien_mat' => $r['tien_mat'], 'qr' => $r['qr'], 'dieu_chinh' => $r['dieu_chinh'],
			'tong' => $r['tong'], 'ghi_chu' => $r['ghi_chu'] ), array( 'id' => (int) $d['id'] ) );
		$wpdb->update( VHG_DB::t( 'bc' ), array( 'sua_luc' => current_time( 'mysql' ) ), array( 'report_id' => $rid ) );
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
			. ' WHERE DATE_FORMAT(d.ngay,%s)=%s AND d.chi_so_sau IS NOT NULL ORDER BY d.ngay DESC', '%Y-%m', $thang ), ARRAY_A );
		$ra = array();
		foreach ( (array) $dong as $d ) {
			if ( ! self::trong_pham_vi( $q, $d['coso'], $d['ma_may'] ) ) { continue; }
			$ra[] = array( 'date' => self::ngay_( $d['ngay'] ), 'locName' => $d['coso'], 'chairCode' => $d['ma_may'],
				'cash' => (int) $d['tien_mat'], 'qr' => (int) $d['qr'], 'total' => (int) $d['tong'] );
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
