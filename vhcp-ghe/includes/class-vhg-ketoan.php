<?php
/**
 * TRANG KẾ TOÁN — port từ web Apps Script "POSH v3 · KẾ TOÁN".
 *
 * Đọc/ghi CHUNG bảng `bc`/`bc_dong` với nhân viên (không đồng bộ, không đẩy). Khác app gốc: dữ
 * liệu là MySQL nên KHÔNG có mấy trò của Sheet (khử trùng dòng cuối, dời dòng giữa 90 tab, tab
 * lạc tên) — UNIQUE ở tầng CSDL lo hết.
 *
 * 🔴 GIỮ NGUYÊN CÁC BẤT BIẾN CỦA APP GỐC:
 *   · `kt_sua` (sửa số liệu) TÍNH LẠI actual/tiền mặt/tổng theo công thức.
 *   · Đối soát nộp tiền / áp QR CHỈ ghi đúng ô, KHÔNG tính lại tiền mặt (làm ở bước sau).
 *   · Xoá KHÔNG mất: qua `bc_rac` (thùng rác), hoàn tác được — bài học mất 1279 dòng ở web cũ.
 *   · Đổi ngày kiểm khoá CẢ HAI đầu (không cho lách khoá).
 *   · Công thức: actual = (sau − trước) × đơn_vị ; tiền mặt = actual − QR ± điều_chỉnh.
 *
 * Phân quyền: gác ở tầng trang (token + vai trò Chốt/Quản lý/Admin). Ở đây nhận `$boi` = tên
 * người thao tác (từ phiên), KHÔNG tin tên gói tin gửi lên.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHG_KeToan {

	public static function don_vi() { return VHG_Quy::don_vi(); }
	public static function squash( $s ) { return VHG_BaoCao::squash( $s ); }
	public static function ngay_( $v ) { return VHG_BaoCao::ngay_( $v ); }

	/** 'YYYY-MM' hoặc 'YYYY_MM' → 'YYYY-MM'. Rỗng = tháng hiện tại. */
	public static function thang_( $v ) {
		$s = trim( (string) $v );
		if ( preg_match( '/^(\d{4})[-_](\d{2})$/', $s, $m ) ) { return $m[1] . '-' . $m[2]; }
		return current_time( 'Y-m' );
	}

	private static function songuyen_( $v ) { return ( '' === $v || null === $v ) ? null : (int) $v; }

	private static function dang_khoa( $coso, $ngay ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . VHG_DB::t( 'bc_khoa' ) . ' WHERE coso_key=%s AND ngay=%s',
			self::squash( $coso ), self::ngay_( $ngay ) ) ) > 0;
	}

	// ══════════════════════════════════════════════════════════════════ DANH SÁCH / CHI TIẾT

	/** Danh sách báo cáo tháng, gom theo cơ sở + ngày. */
	public static function ds( $thang ) {
		global $wpdb;
		$th = self::thang_( $thang );
		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT h.report_id, h.coso, h.coso_key, h.ngay, h.nhan_vien, h.nop_so_tien,'
			. ' d.chi_so_sau, d.actual, d.tien_mat, d.qr, d.dieu_chinh, d.tong, d.nop_so_tien nd_nop,'
			. ' d.kt_duyet, d.anh'
			. ' FROM ' . VHG_DB::t( 'bc' ) . ' h JOIN ' . VHG_DB::t( 'bc_dong' ) . ' d ON d.report_id=h.report_id'
			. ' WHERE DATE_FORMAT(h.ngay,%s)=%s', '%Y-%m', $th ), ARRAY_A );
		$khoa = array();
		foreach ( $wpdb->get_results( 'SELECT coso_key, ngay FROM ' . VHG_DB::t( 'bc_khoa' ), ARRAY_A ) as $k ) {
			$khoa[ $k['coso_key'] . '|' . self::ngay_( $k['ngay'] ) ] = true;
		}
		$g = array();
		foreach ( (array) $rows as $r ) {
			if ( null === $r['chi_so_sau'] ) { continue; }   // dòng đã void (bỏ khỏi báo cáo)
			$k = $r['coso_key'] . '|' . self::ngay_( $r['ngay'] );
			if ( ! isset( $g[ $k ] ) ) {
				$g[ $k ] = array( 'key' => $k, 'coso' => $r['coso'], 'ngay' => self::ngay_( $r['ngay'] ),
					'reportId' => $r['report_id'], 'staff' => $r['nhan_vien'],
					'chairs' => 0, 'actual' => 0, 'cash' => 0, 'qr' => 0, 'adjust' => 0, 'total' => 0,
					'paid' => 0, 'confirmedChairs' => 0, 'photos' => 0, 'chairsNoPhoto' => 0,
					'locked' => isset( $khoa[ $r['coso_key'] . '|' . self::ngay_( $r['ngay'] ) ] ) );
			}
			$o = &$g[ $k ];
			$o['chairs']++; $o['actual'] += (int) $r['actual']; $o['cash'] += (int) $r['tien_mat'];
			$o['qr'] += (int) $r['qr']; $o['adjust'] += (int) $r['dieu_chinh']; $o['total'] += (int) $r['tong'];
			$o['paid'] += (int) $r['nd_nop'];
			if ( (int) $r['kt_duyet'] ) { $o['confirmedChairs']++; }
			$anh = trim( (string) $r['anh'] );
			$na = ( '' === $anh ) ? 0 : count( (array) json_decode( $anh, true ) );
			$o['photos'] += $na;
			if ( ! $na ) { $o['chairsNoPhoto']++; }
			unset( $o );
		}
		$ra = array_values( $g );
		usort( $ra, function ( $a, $b ) {
			if ( $a['ngay'] !== $b['ngay'] ) { return $a['ngay'] < $b['ngay'] ? 1 : -1; }
			return strcmp( $a['coso'], $b['coso'] );
		} );
		return array( 'ok' => true, 'thang' => $th, 'rows' => $ra );
	}

	/** Chi tiết từng ghế của (cơ sở, ngày). */
	public static function chi_tiet( $coso, $ngay ) {
		global $wpdb;
		$ck = self::squash( $coso ); $d = self::ngay_( $ngay );
		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT d.* FROM ' . VHG_DB::t( 'bc_dong' ) . ' d JOIN ' . VHG_DB::t( 'bc' ) . ' h ON h.report_id=d.report_id'
			. ' WHERE h.coso_key=%s AND d.ngay=%s ORDER BY d.ten ASC', $ck, $d ), ARRAY_A );
		$ghe = array(); $sum = array( 'actual' => 0, 'cash' => 0, 'qr' => 0, 'adjust' => 0, 'total' => 0, 'paid' => 0 );
		$rid = '';
		foreach ( (array) $rows as $r ) {
			if ( null === $r['chi_so_sau'] ) { continue; }
			$rid = (string) $r['report_id'];
			$anh = trim( (string) $r['anh'] );
			$ghe[] = array( 'reportId' => $r['report_id'], 'chairCode' => $r['ma_may'], 'chairName' => $r['ten'],
				'meterBefore' => self::songuyen_( $r['chi_so_truoc'] ), 'meterAfter' => self::songuyen_( $r['chi_so_sau'] ),
				'actual' => (int) $r['actual'], 'cash' => (int) $r['tien_mat'], 'qr' => (int) $r['qr'],
				'adjust' => (int) $r['dieu_chinh'], 'total' => (int) $r['tong'], 'note' => (string) $r['ghi_chu'],
				'paid' => (int) $r['nop_so_tien'], 'payStatus' => (string) $r['nop_trang_thai'],
				'payMethod' => (string) $r['nop_hinhthuc'],
				'confirmed' => (int) $r['kt_duyet'] ? 1 : 0,
				'anh' => '' === $anh ? array() : (array) json_decode( $anh, true ) );
			$sum['actual'] += (int) $r['actual']; $sum['cash'] += (int) $r['tien_mat']; $sum['qr'] += (int) $r['qr'];
			$sum['adjust'] += (int) $r['dieu_chinh']; $sum['total'] += (int) $r['tong']; $sum['paid'] += (int) $r['nop_so_tien'];
		}
		return array( 'ok' => true, 'coso' => (string) $coso, 'ngay' => $d, 'reportId' => $rid,
			'rows' => $ghe, 'sum' => $sum, 'locked' => self::dang_khoa( $coso, $d ) );
	}

	// ══════════════════════════════════════════════════════════════════ SỬA (tính lại tiền)

	/**
	 * SỬA số liệu một ghế — TÍNH LẠI actual/tiền mặt/tổng. Ghi undo (giá trị cũ). Ngày khoá thì chặn.
	 * $patch: { meterAfter, qr, adjust, note }.
	 */
	public static function sua( $rid, $ma, $patch, $boi ) {
		global $wpdb;
		$rid = (string) $rid; $ma = (string) $ma;
		$h = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . VHG_DB::t( 'bc' ) . ' WHERE report_id=%s LIMIT 1', $rid ), ARRAY_A );
		if ( ! $h ) { return array( 'ok' => false, 'message' => 'Không thấy báo cáo.' ); }
		if ( self::dang_khoa( $h['coso'], $h['ngay'] ) ) { return array( 'ok' => false, 'message' => 'Ngày này đang KHOÁ — mở lại trước khi sửa.' ); }
		$d = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . VHG_DB::t( 'bc_dong' ) . ' WHERE report_id=%s AND ma_may=%s LIMIT 1', $rid, $ma ), ARRAY_A );
		if ( ! $d ) { return array( 'ok' => false, 'message' => 'Không thấy dòng ghế.' ); }
		$patch = is_array( $patch ) ? $patch : array();

		$dv = self::don_vi();
		$after = array_key_exists( 'meterAfter', $patch ) ? self::songuyen_( $patch['meterAfter'] ) : self::songuyen_( $d['chi_so_sau'] );
		$qr = array_key_exists( 'qr', $patch ) ? (int) $patch['qr'] : (int) $d['qr'];
		$adj = array_key_exists( 'adjust', $patch ) ? (int) $patch['adjust'] : (int) $d['dieu_chinh'];
		$note = array_key_exists( 'note', $patch ) ? mb_substr( trim( (string) $patch['note'] ), 0, 250 ) : (string) $d['ghi_chu'];
		/* Ép chỉ số trước từ dòng thời gian dùng chung (không tin số cũ nếu có mốc mới hơn). */
		$truoc = VHG_BaoCao::chi_so_truoc( $ma, $h['ngay'] );
		$before = ( null !== $truoc ) ? $truoc : self::songuyen_( $d['chi_so_truoc'] );
		if ( null !== $before && null !== $after && $after < $before ) {
			return array( 'ok' => false, 'message' => 'Chỉ số sau (' . $after . ') nhỏ hơn trước (' . $before . '). Nếu thay máy thì duyệt đề nghị đổi chỉ số.' );
		}
		$actual = ( null === $before || null === $after ) ? 0 : ( $after - $before ) * $dv;
		$cash = $actual - $qr + $adj;
		$tong = $cash + $qr;

		self::undo_ghi_( 'sua', $rid . '·' . $ma, array( array(
			'report_id' => $rid, 'ma_may' => $ma,
			'chi_so_truoc' => $d['chi_so_truoc'], 'chi_so_sau' => $d['chi_so_sau'], 'actual' => $d['actual'],
			'tien_mat' => $d['tien_mat'], 'qr' => $d['qr'], 'dieu_chinh' => $d['dieu_chinh'], 'tong' => $d['tong'],
			'ghi_chu' => $d['ghi_chu'] ) ), $boi );

		$wpdb->update( VHG_DB::t( 'bc_dong' ), array(
			'chi_so_truoc' => $before, 'chi_so_sau' => $after, 'actual' => $actual,
			'tien_mat' => $cash, 'qr' => $qr, 'dieu_chinh' => $adj, 'tong' => $tong, 'ghi_chu' => $note ),
			array( 'id' => (int) $d['id'] ) );
		$wpdb->update( VHG_DB::t( 'bc' ), array( 'sua_luc' => current_time( 'mysql' ) ), array( 'report_id' => $rid ) );
		return array( 'ok' => true, 'message' => 'Đã sửa ghế ' . $ma . '.',
			'row' => array( 'meterBefore' => $before, 'meterAfter' => $after, 'actual' => $actual,
				'cash' => $cash, 'qr' => $qr, 'adjust' => $adj, 'total' => $tong, 'note' => $note ) );
	}

	// ══════════════════════════════════════════════════════════════════ DUYỆT (per ghế)

	/** Duyệt / bỏ duyệt các ghế. $targets: [{report_id, ma_may}]. Căn cứ xuất MISA. */
	public static function duyet( $targets, $on, $boi ) {
		global $wpdb;
		$list = is_array( $targets ) ? $targets : array();
		if ( ! count( $list ) ) { return array( 'ok' => false, 'message' => 'Không có ghế nào để duyệt.' ); }
		$stamp = $on ? current_time( 'mysql' ) : null;
		$n = 0;
		foreach ( $list as $t ) {
			$rid = (string) ( isset( $t['report_id'] ) ? $t['report_id'] : '' );
			$ma  = (string) ( isset( $t['ma_may'] ) ? $t['ma_may'] : '' );
			if ( '' === $rid || '' === $ma ) { continue; }
			$n += (int) $wpdb->update( VHG_DB::t( 'bc_dong' ),
				array( 'kt_duyet' => $on ? 1 : 0, 'kt_duyet_luc' => $stamp ),
				array( 'report_id' => $rid, 'ma_may' => $ma ) );
		}
		return array( 'ok' => true, 'changed' => $n );
	}

	/** Duyệt / bỏ duyệt CẢ báo cáo (mọi ghế của cơ sở + ngày). */
	public static function duyet_ngay( $coso, $ngay, $on, $boi ) {
		global $wpdb;
		$ck = self::squash( $coso ); $d = self::ngay_( $ngay );
		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT d.report_id, d.ma_may FROM ' . VHG_DB::t( 'bc_dong' ) . ' d JOIN ' . VHG_DB::t( 'bc' ) . ' h ON h.report_id=d.report_id'
			. ' WHERE h.coso_key=%s AND d.ngay=%s AND d.chi_so_sau IS NOT NULL', $ck, $d ), ARRAY_A );
		$t = array();
		foreach ( (array) $rows as $r ) { $t[] = array( 'report_id' => $r['report_id'], 'ma_may' => $r['ma_may'] ); }
		if ( ! count( $t ) ) { return array( 'ok' => false, 'message' => 'Không thấy ghế nào.' ); }
		return self::duyet( $t, $on, $boi );
	}

	// ══════════════════════════════════════════════════════════════════ KHOÁ NGÀY

	/**
	 * Khoá / mở NGÀY cho TOÀN BỘ cơ sở (hoặc một cơ sở nếu $coso khác rỗng). Chặn gửi/sửa/nộp.
	 */
	public static function khoa( $ngay, $on, $coso, $boi ) {
		global $wpdb;
		$d = self::ngay_( $ngay );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ) { return array( 'ok' => false, 'message' => 'Ngày không đúng dạng.' ); }
		$mot = trim( (string) $coso );
		$cs = array();
		if ( '' !== $mot ) { $cs[ self::squash( $mot ) ] = $mot; }
		else {
			foreach ( VHG_May::ds_may() as $m ) {
				$c = trim( (string) ( isset( $m['coso_ten'] ) ? $m['coso_ten'] : '' ) );
				if ( '' !== $c ) { $cs[ self::squash( $c ) ] = $c; }
			}
			/* Cơ sở có báo cáo ngày đó mà danh mục không còn — vẫn khoá, không để hở. */
			$hs = $wpdb->get_results( $wpdb->prepare( 'SELECT DISTINCT coso, coso_key FROM ' . VHG_DB::t( 'bc' ) . ' WHERE ngay=%s', $d ), ARRAY_A );
			foreach ( (array) $hs as $h ) { $cs[ $h['coso_key'] ] = $h['coso']; }
		}
		$them = 0; $xoa = 0;
		foreach ( $cs as $ck => $ten ) {
			$co = (int) $wpdb->get_var( $wpdb->prepare(
				'SELECT COUNT(*) FROM ' . VHG_DB::t( 'bc_khoa' ) . ' WHERE coso_key=%s AND ngay=%s', $ck, $d ) );
			if ( $on && ! $co ) {
				$wpdb->insert( VHG_DB::t( 'bc_khoa' ), array( 'coso' => $ten, 'coso_key' => $ck, 'ngay' => $d,
					'khoa_luc' => current_time( 'mysql' ), 'boi' => (string) $boi ) );
				$them++;
			} elseif ( ! $on && $co ) {
				$wpdb->delete( VHG_DB::t( 'bc_khoa' ), array( 'coso_key' => $ck, 'ngay' => $d ) );
				$xoa++;
			}
		}
		return array( 'ok' => true, 'ngay' => $d, 'locked' => (bool) $on,
			'them' => $them, 'xoa' => $xoa, 'so_coso' => count( $cs ),
			'message' => $on ? ( 'Đã khoá ' . $them . ' cơ sở ngày ' . $d . '.' )
				: ( 'Đã mở ' . $xoa . ' cơ sở ngày ' . $d . '.' ) );
	}

	/** Ngày đã khoá — cho màn kế toán. */
	public static function khoa_ds() {
		global $wpdb;
		$r = $wpdb->get_results( 'SELECT coso, ngay, khoa_luc, boi FROM ' . VHG_DB::t( 'bc_khoa' ) . ' ORDER BY ngay DESC LIMIT 500', ARRAY_A );
		$ra = array();
		foreach ( (array) $r as $x ) { $ra[] = array( 'coso' => $x['coso'], 'ngay' => self::ngay_( $x['ngay'] ),
			'luc' => (string) $x['khoa_luc'], 'boi' => (string) $x['boi'] ); }
		return array( 'ok' => true, 'rows' => $ra );
	}

	// ══════════════════════════════════════════════════════════════════ XOÁ (thùng rác)

	/** Xoá ghế khỏi báo cáo — CHUYỂN sang thùng rác (hoàn tác được). Bắt buộc lý do. Ngày khoá thì bỏ. */
	public static function xoa( $targets, $ly_do, $boi ) {
		global $wpdb;
		$why = trim( (string) $ly_do );
		if ( '' === $why ) { return array( 'ok' => false, 'message' => 'Phải ghi lý do xoá.' ); }
		$list = is_array( $targets ) ? $targets : array();
		if ( ! count( $list ) ) { return array( 'ok' => false, 'message' => 'Chưa chọn ghế nào.' ); }
		$now = current_time( 'mysql' ); $moved = 0; $locked = 0;
		foreach ( $list as $t ) {
			$rid = (string) ( isset( $t['report_id'] ) ? $t['report_id'] : '' );
			$ma  = (string) ( isset( $t['ma_may'] ) ? $t['ma_may'] : '' );
			if ( '' === $rid || '' === $ma ) { continue; }
			$d = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . VHG_DB::t( 'bc_dong' ) . ' WHERE report_id=%s AND ma_may=%s LIMIT 1', $rid, $ma ), ARRAY_A );
			if ( ! $d ) { continue; }
			$h = $wpdb->get_row( $wpdb->prepare( 'SELECT coso, ngay FROM ' . VHG_DB::t( 'bc' ) . ' WHERE report_id=%s LIMIT 1', $rid ), ARRAY_A );
			if ( $h && self::dang_khoa( $h['coso'], $h['ngay'] ) ) { $locked++; continue; }
			$wpdb->insert( VHG_DB::t( 'bc_rac' ), array(
				'report_id' => $rid, 'ma_may' => $ma, 'ngay' => $d['ngay'],
				'coso' => $h ? $h['coso'] : '', 'snapshot' => wp_json_encode( $d ),
				'ly_do' => mb_substr( $why, 0, 250 ), 'boi' => (string) $boi, 'tao_luc' => $now ) );
			$wpdb->delete( VHG_DB::t( 'bc_dong' ), array( 'id' => (int) $d['id'] ) );
			$moved++;
		}
		return array( 'ok' => true, 'moved' => $moved, 'skippedLocked' => $locked,
			'message' => 'Đã chuyển ' . $moved . ' ghế vào thùng rác.' . ( $locked ? ( ' Bỏ ' . $locked . ' ghế ngày đang khoá.' ) : '' ) );
	}

	public static function rac_ds( $gh = 100 ) {
		global $wpdb;
		$gh = max( 1, min( 500, (int) $gh ) );
		$r = $wpdb->get_results( $wpdb->prepare(
			'SELECT id, report_id, ma_may, ngay, coso, ly_do, boi, tao_luc FROM ' . VHG_DB::t( 'bc_rac' )
			. ' WHERE hoan_luc IS NULL ORDER BY id DESC LIMIT %d', $gh ), ARRAY_A );
		$ra = array();
		foreach ( (array) $r as $x ) { $ra[] = array( 'id' => (int) $x['id'], 'reportId' => $x['report_id'],
			'chairCode' => $x['ma_may'], 'ngay' => self::ngay_( $x['ngay'] ), 'coso' => $x['coso'],
			'ly_do' => $x['ly_do'], 'boi' => $x['boi'], 'luc' => (string) $x['tao_luc'] ); }
		return array( 'ok' => true, 'rows' => $ra );
	}

	/** Hoàn tác: đưa dòng từ thùng rác về `bc_dong`. $ids = [id,...]. */
	public static function rac_hoan( $ids, $boi ) {
		global $wpdb;
		$ids = is_array( $ids ) ? $ids : array();
		$n = 0; $bo = 0;
		foreach ( $ids as $id ) {
			$id = (int) $id;
			$x = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . VHG_DB::t( 'bc_rac' ) . ' WHERE id=%d AND hoan_luc IS NULL LIMIT 1', $id ), ARRAY_A );
			if ( ! $x ) { continue; }
			$snap = json_decode( (string) $x['snapshot'], true );
			if ( ! is_array( $snap ) ) { $bo++; continue; }
			unset( $snap['id'] );
			/* Còn báo cáo gốc? report_id vẫn có trong `bc`? Nếu bc header đã mất thì vẫn insert dòng —
			   nhưng thường header còn (chỉ xoá dòng ghế). Trùng (report_id,ma_may) thì bỏ qua. */
			$co = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . VHG_DB::t( 'bc_dong' ) . ' WHERE report_id=%s AND ma_may=%s',
				$snap['report_id'], $snap['ma_may'] ) );
			if ( $co ) { $bo++; continue; }
			$wpdb->insert( VHG_DB::t( 'bc_dong' ), $snap );
			$wpdb->update( VHG_DB::t( 'bc_rac' ), array( 'hoan_luc' => current_time( 'mysql' ) ), array( 'id' => $id ) );
			$n++;
		}
		return array( 'ok' => true, 'restored' => $n, 'bad' => $bo,
			'message' => 'Đã hoàn tác ' . $n . ' ghế.' . ( $bo ? ( ' Bỏ ' . $bo . ' (đã có dòng hoặc lỗi).' ) : '' ) );
	}

	// ══════════════════════════════════════════════════════════════════ ĐỔI NGÀY BÁO CÁO

	/**
	 * Đổi NGÀY của cả báo cáo một cơ sở. Kiểm khoá CẢ HAI đầu. Ngày mới ĐÃ CÓ báo cáo cơ sở đó =
	 * CHẶN (1 báo cáo/cơ sở/ngày — UNIQUE), nhờ kế toán xoá/gộp trước.
	 */
	public static function doi_ngay( $coso, $ngay_cu, $ngay_moi, $ly_do, $boi ) {
		global $wpdb;
		$ck = self::squash( $coso ); $dc = self::ngay_( $ngay_cu ); $dm = self::ngay_( $ngay_moi );
		$why = trim( (string) $ly_do );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $dc ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $dm ) ) {
			return array( 'ok' => false, 'message' => 'Ngày không đúng dạng.' );
		}
		if ( $dc === $dm ) { return array( 'ok' => false, 'message' => 'Ngày mới trùng ngày cũ.' ); }
		if ( '' === $why ) { return array( 'ok' => false, 'message' => 'Phải ghi lý do đổi ngày.' ); }
		if ( $dm > current_time( 'Y-m-d' ) ) { return array( 'ok' => false, 'message' => 'Ngày mới ở tương lai — kiểm lại.' ); }
		if ( self::dang_khoa( $coso, $dc ) ) { return array( 'ok' => false, 'message' => 'Ngày cũ đang KHOÁ — mở trước.' ); }
		if ( self::dang_khoa( $coso, $dm ) ) { return array( 'ok' => false, 'message' => 'Ngày mới đang KHOÁ — mở trước.' ); }
		$h = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . VHG_DB::t( 'bc' ) . ' WHERE coso_key=%s AND ngay=%s LIMIT 1', $ck, $dc ), ARRAY_A );
		if ( ! $h ) { return array( 'ok' => false, 'message' => 'Không thấy báo cáo ' . $coso . ' ngày ' . $dc . '.' ); }
		$trung = $wpdb->get_var( $wpdb->prepare( 'SELECT report_id FROM ' . VHG_DB::t( 'bc' ) . ' WHERE coso_key=%s AND ngay=%s LIMIT 1', $ck, $dm ) );
		if ( $trung ) {
			return array( 'ok' => false, 'message' => 'Ngày mới ' . $dm . ' ĐÃ CÓ báo cáo của cơ sở này. '
				. 'Xoá/gộp báo cáo ngày đó trước rồi đổi lại (1 báo cáo/cơ sở/ngày).' );
		}
		$rid = (string) $h['report_id'];
		$wpdb->update( VHG_DB::t( 'bc' ), array( 'ngay' => $dm, 'sua_luc' => current_time( 'mysql' ) ), array( 'report_id' => $rid ) );
		$wpdb->update( VHG_DB::t( 'bc_dong' ), array( 'ngay' => $dm ), array( 'report_id' => $rid ) );
		self::undo_ghi_( 'doi_ngay', $rid, array( array( 'report_id' => $rid, 'ngay_cu' => $dc, 'ngay_moi' => $dm ) ), $boi );
		return array( 'ok' => true, 'message' => 'Đã đổi ngày báo cáo ' . $coso . ': ' . $dc . ' → ' . $dm . '.' );
	}

	// ══════════════════════════════════════════════════════════════════ NHẬT KÝ / HOÀN TÁC

	private static function undo_ghi_( $viec, $ly_do, $chi_tiet, $boi ) {
		global $wpdb;
		$wpdb->insert( VHG_DB::t( 'bc_undo' ), array( 'viec' => $viec, 'ly_do' => mb_substr( (string) $ly_do, 0, 250 ),
			'chi_tiet' => wp_json_encode( $chi_tiet ), 'da_hoan_tac' => 0, 'boi' => (string) $boi, 'tao_luc' => current_time( 'mysql' ) ) );
	}

	public static function undo_ds( $gh = 40 ) {
		global $wpdb;
		$gh = max( 1, min( 200, (int) $gh ) );
		$r = $wpdb->get_results( $wpdb->prepare(
			'SELECT id, viec, ly_do, da_hoan_tac, boi, tao_luc FROM ' . VHG_DB::t( 'bc_undo' ) . ' ORDER BY id DESC LIMIT %d', $gh ), ARRAY_A );
		$ra = array();
		foreach ( (array) $r as $x ) { $ra[] = array( 'id' => (int) $x['id'], 'viec' => $x['viec'], 'ly_do' => $x['ly_do'],
			'daHoanTac' => (int) $x['da_hoan_tac'], 'boi' => $x['boi'], 'luc' => (string) $x['tao_luc'] ); }
		return array( 'ok' => true, 'rows' => $ra );
	}

	/** Hoàn tác một thao tác 'sua' (trả lại giá trị cũ của các ô). */
	public static function undo( $id, $boi ) {
		global $wpdb;
		$id = (int) $id;
		$u = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . VHG_DB::t( 'bc_undo' ) . ' WHERE id=%d LIMIT 1', $id ), ARRAY_A );
		if ( ! $u ) { return array( 'ok' => false, 'message' => 'Không thấy dòng nhật ký.' ); }
		if ( (int) $u['da_hoan_tac'] ) { return array( 'ok' => false, 'message' => 'Đã hoàn tác trước đó.' ); }
		if ( ! in_array( $u['viec'], array( 'sua', 'qr', 'doisoat', 'nhap' ), true ) ) {
			return array( 'ok' => false, 'message' => 'Thao tác này không hoàn tác được ở đây.' );
		}
		$ct = json_decode( (string) $u['chi_tiet'], true );
		if ( ! is_array( $ct ) ) { return array( 'ok' => false, 'message' => 'Nhật ký không đọc được.' ); }

		/* Hoàn tác NHẬP: xoá đúng các báo cáo đã chép vào (cả header lẫn dòng ghế). */
		if ( 'nhap' === $u['viec'] ) {
			$rids = isset( $ct['report_ids'] ) && is_array( $ct['report_ids'] ) ? $ct['report_ids'] : array();
			$n = 0;
			foreach ( $rids as $rid ) {
				$rid = (string) $rid;
				$n  += (int) $wpdb->delete( VHG_DB::t( 'bc_dong' ), array( 'report_id' => $rid ) );
				$wpdb->delete( VHG_DB::t( 'bc' ), array( 'report_id' => $rid ) );
			}
			$wpdb->update( VHG_DB::t( 'bc_undo' ), array( 'da_hoan_tac' => 1 ), array( 'id' => $id ) );
			return array( 'ok' => true, 'changed' => $n, 'message' => 'Đã gỡ ' . count( $rids ) . ' báo cáo vừa nhập (' . $n . ' ghế).' );
		}

		$n = 0;
		foreach ( $ct as $o ) {
			$key = array( 'report_id' => $o['report_id'], 'ma_may' => $o['ma_may'] );
			if ( 'sua' === $u['viec'] ) {
				$wpdb->update( VHG_DB::t( 'bc_dong' ), array(
					'chi_so_truoc' => $o['chi_so_truoc'], 'chi_so_sau' => $o['chi_so_sau'], 'actual' => $o['actual'],
					'tien_mat' => $o['tien_mat'], 'qr' => $o['qr'], 'dieu_chinh' => $o['dieu_chinh'], 'tong' => $o['tong'],
					'ghi_chu' => $o['ghi_chu'] ), $key );
			} elseif ( 'qr' === $u['viec'] ) {
				$wpdb->update( VHG_DB::t( 'bc_dong' ), array( 'qr' => $o['qr'], 'tong' => $o['tong'] ), $key );
			} else { // doisoat — trả lại ô nộp tiền
				$wpdb->update( VHG_DB::t( 'bc_dong' ), array( 'nop_so_tien' => $o['nop_so_tien'],
					'nop_trang_thai' => $o['nop_trang_thai'], 'nop_hinhthuc' => $o['nop_hinhthuc'], 'nop_ngay' => $o['nop_ngay'] ), $key );
			}
			$n++;
		}
		$wpdb->update( VHG_DB::t( 'bc_undo' ), array( 'da_hoan_tac' => 1 ), array( 'id' => $id ) );
		return array( 'ok' => true, 'changed' => $n, 'message' => 'Đã hoàn tác ' . $n . ' ghế.' );
	}

	// ══════════════════════════════════════════════════════════════════ DUYỆT ĐỀ NGHỊ CHỈ SỐ

	/** Danh sách đề nghị đổi/xoá chỉ số. $tatca=false → chỉ 'cho_duyet'. */
	public static function denghi_ds( $tatca ) {
		global $wpdb;
		$sql = $tatca
			? 'SELECT * FROM ' . VHG_DB::t( 'bc_denghi' ) . ' ORDER BY id DESC LIMIT 100'
			: $wpdb->prepare( 'SELECT * FROM ' . VHG_DB::t( 'bc_denghi' ) . ' WHERE trang_thai=%s ORDER BY id DESC LIMIT 100', 'cho_duyet' );
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		$ra = array();
		foreach ( (array) $rows as $d ) {
			$chan = (int) $wpdb->get_var( $wpdb->prepare(
				'SELECT COUNT(*) FROM ' . VHG_DB::t( 'bc_dong' ) . ' WHERE ma_may=%s AND ngay>=%s AND chi_so_sau IS NOT NULL',
				$d['ma_may'], self::ngay_( $d['tu_ngay'] ) ) );
			$ra[] = array( 'id' => $d['id'], 'chairCode' => $d['ma_may'], 'chairName' => $d['ten'],
				'coso' => $d['coso'], 'nhanVien' => $d['nhan_vien'], 'fromDate' => self::ngay_( $d['tu_ngay'] ),
				'loai' => $d['loai'], 'meterOpening' => self::songuyen_( $d['chi_so'] ), 'lyDo' => $d['ly_do'],
				'trangThai' => $d['trang_thai'], 'duyetBoi' => $d['duyet_boi'], 'ghiChuKeToan' => $d['ghi_chu_kt'],
				'taoLuc' => (string) $d['tao_luc'], 'banGhiChan' => $chan );
		}
		return array( 'ok' => true, 'rows' => $ra );
	}

	/**
	 * DUYỆT đề nghị — đặt mốc chỉ số (may.moc_chiso/moc_chiso_ngay) hiệu lực TỪ NGÀY áp dụng.
	 * KHÔNG đụng dòng doanh thu cũ. loai 'xoa' → mốc 0. Cảnh báo nếu ghế đã có bản ghi từ ngày đó.
	 */
	public static function denghi_duyet( $id, $ghichu, $boi ) {
		global $wpdb;
		$id = (string) $id;
		$d = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . VHG_DB::t( 'bc_denghi' ) . ' WHERE id=%s LIMIT 1', $id ), ARRAY_A );
		if ( ! $d ) { return array( 'ok' => false, 'message' => 'Không thấy đề nghị.' ); }
		if ( 'cho_duyet' !== $d['trang_thai'] ) { return array( 'ok' => false, 'message' => 'Đề nghị đã xử lý (' . $d['trang_thai'] . ').' ); }
		$from = self::ngay_( $d['tu_ngay'] );
		$so = ( 'xoa' === $d['loai'] ) ? 0 : (int) $d['chi_so'];
		$ma = strtoupper( trim( (string) $d['ma_may'] ) );
		$co = $wpdb->get_var( $wpdb->prepare( 'SELECT ma FROM ' . VHG_DB::t( 'may' ) . ' WHERE ma=%s', $ma ) );
		if ( ! $co ) { return array( 'ok' => false, 'message' => 'Ghế ' . $ma . ' không có trong danh mục.' ); }
		$wpdb->update( VHG_DB::t( 'may' ), array( 'moc_chiso' => $so, 'moc_chiso_ngay' => $from ), array( 'ma' => $ma ) );
		$wpdb->update( VHG_DB::t( 'bc_denghi' ), array( 'trang_thai' => 'duyet', 'duyet_boi' => (string) $boi,
			'duyet_luc' => current_time( 'mysql' ), 'ghi_chu_kt' => mb_substr( trim( (string) $ghichu ), 0, 250 ) ),
			array( 'id' => $id ) );
		$chan = (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . VHG_DB::t( 'bc_dong' ) . ' WHERE ma_may=%s AND ngay>=%s AND chi_so_sau IS NOT NULL', $ma, $from ) );
		return array( 'ok' => true,
			'message' => 'Đã duyệt: ghế ' . $ma . ' mốc chỉ số ' . number_format( $so, 0, ',', '.' ) . ' từ ' . $from . '.',
			'canhBao' => $chan ? ( 'Ghế này đã có ' . $chan . ' bản ghi từ ' . $from . ' trở đi — mốc mới KHÔNG áp cho các bản ghi đó.' ) : '' );
	}

	public static function denghi_tuchoi( $id, $ghichu, $boi ) {
		global $wpdb;
		$id = (string) $id; $ly = trim( (string) $ghichu );
		if ( '' === $ly ) { return array( 'ok' => false, 'message' => 'Từ chối thì phải ghi lý do cho nhân viên.' ); }
		$d = $wpdb->get_row( $wpdb->prepare( 'SELECT trang_thai FROM ' . VHG_DB::t( 'bc_denghi' ) . ' WHERE id=%s LIMIT 1', $id ), ARRAY_A );
		if ( ! $d ) { return array( 'ok' => false, 'message' => 'Không thấy đề nghị.' ); }
		if ( 'cho_duyet' !== $d['trang_thai'] ) { return array( 'ok' => false, 'message' => 'Đề nghị đã xử lý.' ); }
		$wpdb->update( VHG_DB::t( 'bc_denghi' ), array( 'trang_thai' => 'tu_choi', 'duyet_boi' => (string) $boi,
			'duyet_luc' => current_time( 'mysql' ), 'ghi_chu_kt' => mb_substr( $ly, 0, 250 ) ), array( 'id' => $id ) );
		return array( 'ok' => true, 'message' => 'Đã từ chối đề nghị. Nhân viên đọc được lý do.' );
	}

	// ══════════════════════════════════════════════════════════════════ YÊU CẦU CƠ SỞ

	/** Kế toán gửi yêu cầu cơ sở làm bổ sung / sửa. loai 'bo_sung' | 'sua'. */
	public static function yeucau_tao( $coso, $ngay, $loai, $noidung, $boi ) {
		global $wpdb;
		$coso = trim( (string) $coso ); $d = self::ngay_( $ngay );
		$loai = ( 'sua' === $loai ) ? 'sua' : 'bo_sung';
		$nd = trim( (string) $noidung );
		if ( '' === $coso ) { return array( 'ok' => false, 'message' => 'Thiếu cơ sở.' ); }
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ) { return array( 'ok' => false, 'message' => 'Ngày không đúng dạng.' ); }
		if ( '' === $nd ) { return array( 'ok' => false, 'message' => 'Ghi rõ yêu cầu gì để nhân viên biết.' ); }
		$trung = (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . VHG_DB::t( 'bc_yeucau' ) . ' WHERE coso_key=%s AND ngay=%s AND trang_thai=%s',
			self::squash( $coso ), $d, 'cho_lam' ) );
		if ( $trung ) { return array( 'ok' => false, 'message' => 'Cơ sở này đã có yêu cầu đang chờ cho ngày ' . $d . '.' ); }
		$id = 'YC-' . current_time( 'YmdHis' ) . '-' . wp_rand( 100, 999 );
		$wpdb->insert( VHG_DB::t( 'bc_yeucau' ), array( 'id' => $id, 'tao_luc' => current_time( 'mysql' ),
			'coso' => $coso, 'coso_key' => self::squash( $coso ), 'ngay' => $d, 'loai' => $loai,
			'noi_dung' => mb_substr( $nd, 0, 500 ), 'tao_boi' => (string) $boi, 'trang_thai' => 'cho_lam' ) );
		return array( 'ok' => true, 'id' => $id, 'message' => 'Đã gửi yêu cầu cho ' . $coso . ' ngày ' . $d . '.' );
	}

	public static function yeucau_ds( $thang ) {
		global $wpdb;
		$th = trim( (string) $thang );
		$rows = $wpdb->get_results( 'SELECT * FROM ' . VHG_DB::t( 'bc_yeucau' ) . ' ORDER BY id DESC LIMIT 200', ARRAY_A );
		$ra = array();
		foreach ( (array) $rows as $y ) {
			$ng = self::ngay_( $y['ngay'] );
			if ( '' !== $th && substr( $ng, 0, 7 ) !== self::thang_( $th ) ) { continue; }
			$ra[] = array( 'id' => $y['id'], 'coSo' => $y['coso'], 'ngay' => $ng, 'loai' => $y['loai'],
				'loaiChu' => ( 'sua' === $y['loai'] ? 'Sửa báo cáo' : 'Làm bổ sung' ), 'noiDung' => $y['noi_dung'],
				'trangThai' => $y['trang_thai'], 'taoBoi' => $y['tao_boi'], 'taoLuc' => (string) $y['tao_luc'],
				'xongBoi' => $y['xong_boi'], 'xongLuc' => (string) $y['xong_luc'] );
		}
		return array( 'ok' => true, 'rows' => $ra );
	}

	public static function yeucau_huy( $id, $boi ) {
		global $wpdb;
		$id = (string) $id;
		$y = $wpdb->get_row( $wpdb->prepare( 'SELECT trang_thai FROM ' . VHG_DB::t( 'bc_yeucau' ) . ' WHERE id=%s LIMIT 1', $id ), ARRAY_A );
		if ( ! $y ) { return array( 'ok' => false, 'message' => 'Không thấy yêu cầu.' ); }
		if ( 'huy' === $y['trang_thai'] ) { return array( 'ok' => false, 'message' => 'Đã rút lại trước đó.' ); }
		$wpdb->update( VHG_DB::t( 'bc_yeucau' ), array( 'trang_thai' => 'huy', 'xong_luc' => current_time( 'mysql' ),
			'xong_boi' => (string) $boi ), array( 'id' => $id ) );
		return array( 'ok' => true, 'message' => 'Đã rút lại yêu cầu.' );
	}

	// ══════════════════════════════════════════════════════════════════ ĐỐI SOÁT NỘP TIỀN

	/** Phân bổ tiền nhận cho ghế theo `cash` phải nộp — ổn định (ngày rồi mã) ⇒ chạy lại y nguyên. */
	private static function allocate_( $chairs, $amount ) {
		$left = max( 0, (int) $amount ); $out = array();
		usort( $chairs, function ( $a, $b ) {
			if ( $a['ngay'] !== $b['ngay'] ) { return $a['ngay'] < $b['ngay'] ? -1 : 1; }
			return strcmp( (string) $a['ma'], (string) $b['ma'] );
		} );
		foreach ( $chairs as $c ) {
			$need = max( 0, (int) $c['cash'] );
			if ( $need <= 0 ) { continue; }
			$give = min( $left, $need ); if ( $give <= 0 ) { continue; }
			$left -= $give;
			$out[] = array( 'report_id' => $c['report_id'], 'ma' => $c['ma'], 'ngay' => $c['ngay'], 'paid' => $give, 'need' => $need );
		}
		return array( 'rows' => $out, 'left' => $left );
	}

	/** Nhu cầu nộp tiền mặt theo cơ sở trong tháng. Trả [coso_key => {coso, need, already, chairs[]}]. */
	private static function need_( $thang ) {
		global $wpdb;
		$th = self::thang_( $thang );
		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT d.report_id, d.ma_may, d.ngay, d.tien_mat, d.nop_so_tien, h.coso, h.coso_key'
			. ' FROM ' . VHG_DB::t( 'bc_dong' ) . ' d JOIN ' . VHG_DB::t( 'bc' ) . ' h ON h.report_id=d.report_id'
			. ' WHERE DATE_FORMAT(d.ngay,%s)=%s AND d.chi_so_sau IS NOT NULL', '%Y-%m', $th ), ARRAY_A );
		$out = array();
		foreach ( (array) $rows as $r ) {
			if ( (int) $r['tien_mat'] <= 0 ) { continue; }
			$k = $r['coso_key'];
			if ( ! isset( $out[ $k ] ) ) { $out[ $k ] = array( 'coso' => $r['coso'], 'need' => 0, 'already' => 0, 'chairs' => array() ); }
			$out[ $k ]['need'] += (int) $r['tien_mat'];
			$out[ $k ]['already'] += (int) $r['nop_so_tien'];
			$out[ $k ]['chairs'][] = array( 'report_id' => $r['report_id'], 'ma' => $r['ma_may'],
				'ngay' => self::ngay_( $r['ngay'] ), 'cash' => (int) $r['tien_mat'] );
		}
		return $out;
	}

	/** Dấu vân tay lô dữ liệu — chặn áp trùng (băm ngày|tiền|diễn giải). */
	private static function batch_( $kind, $rows ) {
		$s = array();
		foreach ( (array) $rows as $r ) {
			$s[] = self::ngay_( isset( $r['date'] ) ? $r['date'] : '' ) . '|' . (int) ( isset( $r['amount'] ) ? $r['amount'] : 0 )
				. '|' . self::squash( isset( $r['desc'] ) ? $r['desc'] : '' );
		}
		sort( $s );
		return $kind . '-' . substr( md5( implode( ';', $s ) ), 0, 16 );
	}
	private static function batch_da_ap_( $batch ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . VHG_DB::t( 'bc_undo' ) . ' WHERE viec=%s AND ly_do=%s AND da_hoan_tac=0',
			'doisoat', (string) $batch ) ) > 0;
	}

	/** Ghi các ô nộp tiền (SET tuyệt đối) theo danh sách phân bổ; ghi undo. */
	private static function ghi_nop_( $patches, $hinhthuc, $ngay_nop, $batch, $reason, $boi ) {
		global $wpdb;
		$before = array(); $n = 0;
		foreach ( $patches as $p ) {
			$cu = $wpdb->get_row( $wpdb->prepare(
				'SELECT nop_so_tien, nop_trang_thai, nop_hinhthuc, nop_ngay FROM ' . VHG_DB::t( 'bc_dong' )
				. ' WHERE report_id=%s AND ma_may=%s LIMIT 1', $p['report_id'], $p['ma'] ), ARRAY_A );
			if ( ! $cu ) { continue; }
			$before[] = array( 'report_id' => $p['report_id'], 'ma_may' => $p['ma'],
				'nop_so_tien' => $cu['nop_so_tien'], 'nop_trang_thai' => $cu['nop_trang_thai'],
				'nop_hinhthuc' => $cu['nop_hinhthuc'], 'nop_ngay' => $cu['nop_ngay'] );
			$tt = ( $p['paid'] >= $p['need'] ) ? 'paid' : ( $p['paid'] > 0 ? 'thieu' : 'unpaid' );
			$wpdb->update( VHG_DB::t( 'bc_dong' ), array(
				'nop_so_tien' => (int) $p['paid'], 'nop_trang_thai' => $tt,
				'nop_hinhthuc' => $p['paid'] > 0 ? $hinhthuc : '', 'nop_ngay' => $p['paid'] > 0 ? $ngay_nop : null ),
				array( 'report_id' => $p['report_id'], 'ma_may' => $p['ma'] ) );
			$n++;
		}
		$wpdb->insert( VHG_DB::t( 'bc_undo' ), array( 'viec' => 'doisoat', 'ly_do' => (string) $batch . ' · ' . mb_substr( (string) $reason, 0, 200 ),
			'chi_tiet' => wp_json_encode( $before ), 'da_hoan_tac' => 0, 'boi' => (string) $boi, 'tao_luc' => current_time( 'mysql' ) ) );
		return $n;
	}

	/** Danh sách cơ sở còn phải nộp tiền mặt trong tháng — cho màn xác nhận tay. */
	public static function can_nop( $thang ) {
		$need = self::need_( $thang );
		$ra = array();
		foreach ( $need as $n ) {
			$con = (int) $n['need'] - (int) $n['already'];
			if ( $con <= 0 ) { continue; }
			$ra[] = array( 'coso' => $n['coso'], 'phaiNop' => (int) $n['need'], 'daGhi' => (int) $n['already'],
				'conLai' => $con, 'soGhe' => count( $n['chairs'] ) );
		}
		usort( $ra, function ( $a, $b ) { return $b['conLai'] - $a['conLai']; } );
		return array( 'ok' => true, 'thang' => self::thang_( $thang ), 'rows' => $ra,
			'tongConLai' => array_sum( array_map( function ( $x ) { return $x['conLai']; }, $ra ) ) );
	}

	/** Xác nhận nộp TAY (không cần file). $pheps=[{coso, amount, method:'cash'|'transfer', payDate}]. */
	public static function nop_tay( $pheps, $thang, $apply, $reason, $boi ) {
		$need = self::need_( $thang );
		$byKey = array();
		foreach ( $need as $k => $n ) { $byKey[ self::squash( $n['coso'] ) ] = $n; }
		$rows = array(); $patches = array(); $khong = array();
		foreach ( (array) $pheps as $p ) {
			$coso = trim( (string) ( isset( $p['coso'] ) ? $p['coso'] : '' ) );
			$tien = (int) ( isset( $p['amount'] ) ? $p['amount'] : 0 );
			if ( '' === $coso || $tien <= 0 ) { continue; }
			$ht = ( 'transfer' === ( isset( $p['method'] ) ? $p['method'] : 'cash' ) ) ? 'transfer' : 'cash';
			$ng = isset( $p['payDate'] ) && $p['payDate'] ? self::ngay_( $p['payDate'] ) : current_time( 'Y-m-d' );
			$n = isset( $byKey[ self::squash( $coso ) ] ) ? $byKey[ self::squash( $coso ) ] : null;
			if ( ! $n ) { $khong[] = $coso; continue; }
			$al = self::allocate_( $n['chairs'], $tien );
			foreach ( $al['rows'] as $x ) { $x['_ht'] = $ht; $x['_ng'] = $ng; $patches[] = $x; }
			$rows[] = array( 'coso' => $coso, 'hinhThuc' => $ht, 'phaiNop' => (int) $n['need'],
				'xacNhan' => $tien, 'soGheGhi' => count( $al['rows'] ), 'thua' => $al['left'] );
		}
		$batch = self::batch_( 'TAY', $rows );
		if ( ! $apply ) {
			return array( 'ok' => true, 'apply' => false, 'batch' => $batch, 'rows' => $rows,
				'khong' => $khong, 'soGheSeGhi' => count( $patches ),
				'message' => 'Xem trước: sẽ ghi ' . count( $patches ) . ' ghế của ' . count( $rows ) . ' cơ sở.' );
		}
		if ( '' === trim( (string) $reason ) ) { return array( 'ok' => false, 'message' => 'Phải ghi lý do xác nhận tay.' ); }
		if ( ! count( $patches ) ) { return array( 'ok' => false, 'batch' => $batch, 'message' => 'Không có ghế nào để ghi.' ); }
		if ( self::batch_da_ap_( $batch ) ) { return array( 'ok' => false, 'batch' => $batch, 'message' => 'Nội dung này đã xác nhận trước đó (chống cộng đôi).' ); }
		/* Gom theo (hình thức, ngày) để ghi_nop_ đặt đúng nhãn — ở đây mỗi patch mang _ht/_ng riêng. */
		$n = 0;
		foreach ( $patches as $p ) {
			$n += self::ghi_nop_( array( $p ), $p['_ht'], $p['_ng'], $batch, $reason, $boi );
		}
		return array( 'ok' => true, 'apply' => true, 'batch' => $batch, 'soGheGhi' => $n,
			'message' => 'Đã xác nhận tay ' . $n . ' ghế của ' . count( $rows ) . ' cơ sở.' );
	}

	/** Đối soát CHUYỂN KHOẢN theo sao kê + bảng mã nộp tiền. $bankRows=[{date,amount,desc}]. */
	public static function doisoat_ck( $bankRows, $thang, $apply, $reason, $boi ) {
		global $wpdb;
		$rowsIn = is_array( $bankRows ) ? $bankRows : array();
		$batch = self::batch_( 'CK', $rowsIn );
		if ( $apply && self::batch_da_ap_( $batch ) ) {
			return array( 'ok' => false, 'batch' => $batch, 'message' => 'File này đã áp trước đó (chống cộng đôi).' );
		}
		$ma = $wpdb->get_results( 'SELECT code, coso FROM ' . VHG_DB::t( 'bc_ma_nop' ), ARRAY_A );
		$codes = array();
		foreach ( (array) $ma as $m ) {
			$k = self::squash( $m['code'] );
			if ( strlen( $k ) >= 4 ) { $codes[] = array( 'key' => $k, 'coso' => $m['coso'] ); }
		}
		usort( $codes, function ( $a, $b ) { return strlen( $b['key'] ) - strlen( $a['key'] ); } );
		$byLoc = array(); $unknown = array(); $amb = array(); $tong = 0;
		foreach ( $rowsIn as $r ) {
			$amt = (int) round( (float) ( isset( $r['amount'] ) ? $r['amount'] : 0 ) );
			if ( $amt <= 0 ) { continue; }
			$tong += $amt;
			$sq = self::squash( isset( $r['desc'] ) ? $r['desc'] : '' );
			$hit = array();
			foreach ( $codes as $c ) { if ( strpos( $sq, $c['key'] ) !== false ) { $hit[ self::squash( $c['coso'] ) ] = $c['coso']; } }
			$locs = array_values( $hit );
			if ( count( $locs ) > 1 ) { $amb[] = array( 'amount' => $amt, 'desc' => mb_substr( (string) $r['desc'], 0, 120 ), 'locs' => $locs ); continue; }
			if ( ! count( $locs ) ) { $unknown[] = array( 'amount' => $amt, 'desc' => mb_substr( (string) $r['desc'], 0, 120 ) ); continue; }
			$ck = self::squash( $locs[0] );
			if ( ! isset( $byLoc[ $ck ] ) ) { $byLoc[ $ck ] = array( 'coso' => $locs[0], 'amount' => 0 ); }
			$byLoc[ $ck ]['amount'] += $amt;
		}
		$need = self::need_( $thang );
		$rows = array(); $patches = array();
		foreach ( $byLoc as $ck => $b ) {
			$n = isset( $need[ $ck ] ) ? $need[ $ck ] : null;
			$willWrite = 0;
			if ( $n ) {
				$al = self::allocate_( $n['chairs'], $b['amount'] );
				$willWrite = count( $al['rows'] );
				foreach ( $al['rows'] as $x ) { $patches[] = $x; }
			}
			$rows[] = array( 'coso' => $b['coso'], 'bank' => $b['amount'], 'need' => $n ? (int) $n['need'] : 0,
				'already' => $n ? (int) $n['already'] : 0, 'willWrite' => $willWrite );
		}
		if ( ! $apply ) {
			return array( 'ok' => true, 'apply' => false, 'batch' => $batch, 'rows' => $rows,
				'unknown' => $unknown, 'ambiguous' => $amb, 'bankTotal' => $tong, 'codes' => count( $codes ),
				'willWrite' => count( $patches ), 'message' => 'Xem trước: khớp ' . count( $rows ) . ' cơ sở, '
					. count( $unknown ) . ' giao dịch không rõ mã, ' . count( $amb ) . ' giao dịch khớp nhiều mã.' );
		}
		if ( ! count( $patches ) ) {
			return array( 'ok' => false, 'batch' => $batch, 'rows' => $rows, 'unknown' => $unknown, 'ambiguous' => $amb,
				'message' => 'Không ghi được ô nào (không cơ sở nào vừa có tiền vào khớp mã vừa còn nợ tiền mặt).' );
		}
		$n = self::ghi_nop_( $patches, 'transfer', current_time( 'Y-m-d' ), $batch, $reason, $boi );
		return array( 'ok' => true, 'apply' => true, 'batch' => $batch, 'soGheGhi' => $n,
			'unknown' => $unknown, 'ambiguous' => $amb,
			'message' => 'Đã ghi nộp CK cho ' . $n . ' ghế.' );
	}

	// ══════════════════════════════════════════════════════════════════ BẢNG MÃ NỘP TIỀN

	public static function ma_nop_ds() {
		global $wpdb;
		$r = $wpdb->get_results( 'SELECT id, code, coso, ghi_chu FROM ' . VHG_DB::t( 'bc_ma_nop' ) . ' ORDER BY coso ASC', ARRAY_A );
		return array( 'ok' => true, 'rows' => $r ? $r : array() );
	}
	public static function ma_nop_luu( $id, $code, $coso, $ghichu ) {
		global $wpdb;
		$code = trim( (string) $code ); $coso = trim( (string) $coso );
		if ( '' === $code || '' === $coso ) { return array( 'ok' => false, 'message' => 'Thiếu mã hoặc cơ sở.' ); }
		$data = array( 'code' => $code, 'coso' => $coso, 'coso_key' => self::squash( $coso ), 'ghi_chu' => mb_substr( trim( (string) $ghichu ), 0, 250 ) );
		if ( (int) $id > 0 ) { $wpdb->update( VHG_DB::t( 'bc_ma_nop' ), $data, array( 'id' => (int) $id ) ); }
		else { $wpdb->insert( VHG_DB::t( 'bc_ma_nop' ), $data ); }
		return array( 'ok' => true, 'message' => 'Đã lưu mã nộp tiền.' );
	}
	public static function ma_nop_xoa( $id ) {
		global $wpdb;
		$wpdb->delete( VHG_DB::t( 'bc_ma_nop' ), array( 'id' => (int) $id ) );
		return array( 'ok' => true, 'message' => 'Đã xoá.' );
	}

	// ══════════════════════════════════════════════════════════════════ ĐỐI CHIẾU QR

	/** QR webhook (ngân hàng đẩy về) của một ghế trong một ngày — số CHUẨN. */
	private static function qr_web_( $ma, $ngay ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COALESCE(SUM(so_tien),0) FROM ' . VHG_DB::t( 'thu' )
			. ' WHERE ma_may=%s AND DATE(luc)=%s AND huy=0 AND nguon<>%s', $ma, self::ngay_( $ngay ), VHG_Thu::TIEN_MAT ) );
	}

	/** Đối chiếu QR tháng: QR nhân viên nhập ↔ QR webhook. Trả các ghế LỆCH (để áp sửa). */
	public static function qr_ds( $thang ) {
		global $wpdb;
		$th = self::thang_( $thang );
		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT d.report_id, d.ma_may, d.ten, d.ngay, d.qr, h.coso'
			. ' FROM ' . VHG_DB::t( 'bc_dong' ) . ' d JOIN ' . VHG_DB::t( 'bc' ) . ' h ON h.report_id=d.report_id'
			. ' WHERE DATE_FORMAT(d.ngay,%s)=%s AND d.chi_so_sau IS NOT NULL', '%Y-%m', $th ), ARRAY_A );
		$ra = array(); $khop = 0; $tongBc = 0; $tongWeb = 0;
		foreach ( (array) $rows as $r ) {
			$web = self::qr_web_( $r['ma_may'], $r['ngay'] );
			$bc = (int) $r['qr'];
			$tongBc += $bc; $tongWeb += $web;
			if ( $bc === $web ) { $khop++; continue; }
			$ra[] = array( 'report_id' => $r['report_id'], 'ma_may' => $r['ma_may'], 'ten' => $r['ten'],
				'coso' => $r['coso'], 'ngay' => self::ngay_( $r['ngay'] ), 'bcQr' => $bc, 'webQr' => $web, 'lech' => $web - $bc );
		}
		usort( $ra, function ( $a, $b ) { return abs( $b['lech'] ) - abs( $a['lech'] ); } );
		return array( 'ok' => true, 'thang' => $th, 'soLech' => count( $ra ), 'khop' => $khop,
			'tongBc' => $tongBc, 'tongWeb' => $tongWeb, 'rows' => array_slice( $ra, 0, 300 ) );
	}

	/**
	 * ÁP SỬA QR — SET qr = QR webhook, tổng = tiền mặt + qr mới. TIỀN MẶT GIỮ NGUYÊN (bất biến #4).
	 * $targets=[{report_id, ma_may, ngay}]. Ngày khoá thì bỏ. Ghi undo. Cảnh báo (tiền mặt+QR)≠actual.
	 */
	public static function qr_ap( $targets, $reason, $boi ) {
		global $wpdb;
		$list = is_array( $targets ) ? $targets : array();
		if ( ! count( $list ) ) { return array( 'ok' => false, 'message' => 'Chưa chọn ghế nào.' ); }
		$before = array(); $n = 0; $khoa = 0; $warn = array();
		foreach ( $list as $t ) {
			$rid = (string) ( isset( $t['report_id'] ) ? $t['report_id'] : '' );
			$ma  = (string) ( isset( $t['ma_may'] ) ? $t['ma_may'] : '' );
			if ( '' === $rid || '' === $ma ) { continue; }
			$d = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . VHG_DB::t( 'bc_dong' ) . ' WHERE report_id=%s AND ma_may=%s LIMIT 1', $rid, $ma ), ARRAY_A );
			if ( ! $d ) { continue; }
			$h = $wpdb->get_row( $wpdb->prepare( 'SELECT coso, ngay FROM ' . VHG_DB::t( 'bc' ) . ' WHERE report_id=%s LIMIT 1', $rid ), ARRAY_A );
			if ( $h && self::dang_khoa( $h['coso'], $h['ngay'] ) ) { $khoa++; continue; }
			$web = self::qr_web_( $ma, $d['ngay'] );
			$cash = (int) $d['tien_mat'];
			$before[] = array( 'report_id' => $rid, 'ma_may' => $ma, 'qr' => $d['qr'], 'tong' => $d['tong'] );
			$wpdb->update( VHG_DB::t( 'bc_dong' ), array( 'qr' => $web, 'tong' => $cash + $web ),
				array( 'id' => (int) $d['id'] ) );
			$n++;
			$expect = (int) $d['actual'] - $web + (int) $d['dieu_chinh'];
			if ( $expect !== $cash ) { $warn[] = array( 'ma_may' => $ma, 'gap' => $cash - $expect ); }
		}
		if ( count( $before ) ) {
			$wpdb->insert( VHG_DB::t( 'bc_undo' ), array( 'viec' => 'qr', 'ly_do' => mb_substr( (string) $reason, 0, 250 ),
				'chi_tiet' => wp_json_encode( $before ), 'da_hoan_tac' => 0, 'boi' => (string) $boi, 'tao_luc' => current_time( 'mysql' ) ) );
		}
		return array( 'ok' => true, 'changed' => $n, 'skippedLocked' => $khoa, 'warn' => $warn,
			'message' => 'Đã áp QR cho ' . $n . ' ghế (tiền mặt giữ nguyên).'
				. ( $khoa ? ( ' Bỏ ' . $khoa . ' ghế ngày khoá.' ) : '' )
				. ( count( $warn ) ? ( ' ⚠ ' . count( $warn ) . ' ghế (tiền mặt+QR)≠actual.' ) : '' ) );
	}

	// ══════════════════════════════════════════════════════════════════ SỔ CÔNG NỢ

	private static function thang_dich_( $thang, $delta ) {
		if ( ! preg_match( '/^(\d{4})-(\d{2})$/', $thang, $m ) ) { return ''; }
		$y = (int) $m[1]; $mo = (int) $m[2] + $delta;
		while ( $mo > 12 ) { $mo -= 12; $y++; }
		while ( $mo < 1 ) { $mo += 12; $y--; }
		return $y . '-' . str_pad( $mo, 2, '0', STR_PAD_LEFT );
	}

	/** Gom phải thu / đã nhận (tách TM/CK theo nop_hinhthuc) theo tháng + cơ sở, TOÀN BỘ dữ liệu. */
	private static function congno_theo_thang_() {
		global $wpdb;
		$rows = $wpdb->get_results(
			'SELECT DATE_FORMAT(d.ngay,"%Y-%m") th, h.coso, h.coso_key, d.tien_mat, d.nop_so_tien, d.nop_hinhthuc, d.qr'
			. ' FROM ' . VHG_DB::t( 'bc_dong' ) . ' d JOIN ' . VHG_DB::t( 'bc' ) . ' h ON h.report_id=d.report_id'
			. ' WHERE d.chi_so_sau IS NOT NULL', ARRAY_A );
		$out = array();
		foreach ( (array) $rows as $r ) {
			$th = (string) $r['th']; $k = $r['coso_key'];
			if ( ! isset( $out[ $th ] ) ) { $out[ $th ] = array(); }
			if ( ! isset( $out[ $th ][ $k ] ) ) { $out[ $th ][ $k ] = array( 'coso' => $r['coso'], 'phaiThu' => 0, 'daNhan' => 0, 'tm' => 0, 'ck' => 0, 'qr' => 0 ); }
			$o = &$out[ $th ][ $k ];
			$o['phaiThu'] += (int) $r['tien_mat']; $o['daNhan'] += (int) $r['nop_so_tien']; $o['qr'] += (int) $r['qr'];
			if ( 'transfer' === $r['nop_hinhthuc'] ) { $o['ck'] += (int) $r['nop_so_tien']; } else { $o['tm'] += (int) $r['nop_so_tien']; }
			unset( $o );
		}
		return $out;
	}

	/** Sổ công nợ một tháng: dư đầu (chốt + lũy kế) → phát sinh → đã nhận → dư cuối, theo cơ sở. */
	public static function cong_no( $thang ) {
		global $wpdb;
		$th = self::thang_( $thang );
		$byM = self::congno_theo_thang_();
		$opens = $wpdb->get_results( 'SELECT thang, coso, coso_key, so_tien FROM ' . VHG_DB::t( 'bc_congno_dau' ), ARRAY_A );
		$base = array();
		foreach ( (array) $opens as $o ) {
			if ( $o['thang'] > $th ) { continue; }
			if ( ! isset( $base[ $o['coso_key'] ] ) || $o['thang'] > $base[ $o['coso_key'] ]['thang'] ) {
				$base[ $o['coso_key'] ] = array( 'thang' => $o['thang'], 'so_tien' => (int) $o['so_tien'], 'coso' => $o['coso'] );
			}
		}
		$locs = array();
		foreach ( $byM as $m => $cs ) { if ( $m <= $th ) { foreach ( $cs as $k => $v ) { $locs[ $k ] = $v['coso']; } } }
		foreach ( $base as $k => $b ) { if ( ! isset( $locs[ $k ] ) ) { $locs[ $k ] = $b['coso']; } }
		$rows = array();
		$thangs = array_keys( $byM ); sort( $thangs );
		foreach ( $locs as $k => $ten ) {
			$b = isset( $base[ $k ] ) ? $base[ $k ] : null;
			$opening = $b ? $b['so_tien'] : 0;
			foreach ( $thangs as $m ) {
				if ( $m >= $th ) { continue; }
				if ( $b && $m < $b['thang'] ) { continue; }
				if ( isset( $byM[ $m ][ $k ] ) ) { $opening += (int) $byM[ $m ][ $k ]['phaiThu'] - (int) $byM[ $m ][ $k ]['daNhan']; }
			}
			$cur = isset( $byM[ $th ][ $k ] ) ? $byM[ $th ][ $k ] : array( 'phaiThu' => 0, 'daNhan' => 0, 'tm' => 0, 'ck' => 0, 'qr' => 0 );
			$closing = $opening + (int) $cur['phaiThu'] - (int) $cur['daNhan'];
			$rows[] = array( 'coso' => $ten, 'opening' => $opening, 'phaiThu' => (int) $cur['phaiThu'],
				'daNhan' => (int) $cur['daNhan'], 'daNhanTM' => (int) $cur['tm'], 'daNhanCK' => (int) $cur['ck'],
				'qr' => (int) $cur['qr'], 'chuaNop' => (int) $cur['phaiThu'] - (int) $cur['daNhan'], 'closing' => $closing );
		}
		usort( $rows, function ( $a, $b ) { return $b['closing'] - $a['closing']; } );
		$daChot = false;
		foreach ( (array) $opens as $o ) { if ( $o['thang'] === self::thang_dich_( $th, 1 ) ) { $daChot = true; break; } }
		return array( 'ok' => true, 'thang' => $th, 'thangSau' => self::thang_dich_( $th, 1 ), 'rows' => $rows, 'daChot' => $daChot );
	}

	/** Chốt sổ: dư cuối tháng → dư đầu tháng sau (chỉ cơ sở ≠ 0). Chạy lại được. */
	public static function cong_no_chot( $thang, $reason, $boi ) {
		global $wpdb;
		$led = self::cong_no( $thang );
		$next = self::thang_dich_( self::thang_( $thang ), 1 );
		if ( '' === trim( (string) $reason ) ) { return array( 'ok' => false, 'message' => 'Phải ghi lý do chốt sổ.' ); }
		$them = 0; $sua = 0;
		foreach ( $led['rows'] as $r ) {
			if ( (int) $r['closing'] === 0 ) {
				$wpdb->delete( VHG_DB::t( 'bc_congno_dau' ), array( 'coso_key' => self::squash( $r['coso'] ), 'thang' => $next ) );
				continue;
			}
			$co = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . VHG_DB::t( 'bc_congno_dau' ) . ' WHERE coso_key=%s AND thang=%s', self::squash( $r['coso'] ), $next ) );
			$data = array( 'thang' => $next, 'coso' => $r['coso'], 'coso_key' => self::squash( $r['coso'] ),
				'so_tien' => (int) $r['closing'], 'chot_luc' => current_time( 'mysql' ), 'boi' => (string) $boi, 'ghi_chu' => mb_substr( (string) $reason, 0, 250 ) );
			if ( $co ) { $wpdb->update( VHG_DB::t( 'bc_congno_dau' ), $data, array( 'id' => (int) $co ) ); $sua++; }
			else { $wpdb->insert( VHG_DB::t( 'bc_congno_dau' ), $data ); $them++; }
		}
		return array( 'ok' => true, 'next' => $next, 'them' => $them, 'sua' => $sua,
			'message' => 'Đã chốt sổ ' . self::thang_( $thang ) . ' → ' . $next . ' (' . ( $them + $sua ) . ' cơ sở còn dư).' );
	}

	public static function cong_no_mo( $thang, $boi ) {
		global $wpdb;
		$next = self::thang_dich_( self::thang_( $thang ), 1 );
		$n = (int) $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . VHG_DB::t( 'bc_congno_dau' ) . ' WHERE thang=%s', $next ) );
		return array( 'ok' => true, 'message' => 'Đã mở lại: xoá ' . $n . ' dòng dư đầu kỳ của ' . $next . '.' );
	}

	// ══════════════════════════════════════════════════════════════════ UNIT ID MISA

	public static function ma_misa_ds() {
		global $wpdb;
		$r = $wpdb->get_results( 'SELECT coso_key, coso, unit_id, unit_name, vung, thu_tu, ghi_chu FROM ' . VHG_DB::t( 'bc_ma_misa' ) . ' ORDER BY thu_tu ASC, coso ASC', ARRAY_A );
		return array( 'ok' => true, 'rows' => $r ? $r : array() );
	}
	public static function ma_misa_luu( $coso, $unit_id, $unit_name, $vung, $thu_tu, $ghichu ) {
		global $wpdb;
		$coso = trim( (string) $coso );
		if ( '' === $coso ) { return array( 'ok' => false, 'message' => 'Thiếu cơ sở.' ); }
		$ck = self::squash( $coso );
		$data = array( 'coso_key' => $ck, 'coso' => $coso, 'unit_id' => mb_substr( trim( (string) $unit_id ), 0, 40 ),
			'unit_name' => mb_substr( trim( (string) $unit_name ), 0, 190 ), 'vung' => mb_substr( trim( (string) $vung ), 0, 80 ),
			'thu_tu' => (int) $thu_tu, 'ghi_chu' => mb_substr( trim( (string) $ghichu ), 0, 250 ) );
		$co = $wpdb->get_var( $wpdb->prepare( 'SELECT coso_key FROM ' . VHG_DB::t( 'bc_ma_misa' ) . ' WHERE coso_key=%s', $ck ) );
		if ( $co ) { $wpdb->update( VHG_DB::t( 'bc_ma_misa' ), $data, array( 'coso_key' => $ck ) ); }
		else { $wpdb->insert( VHG_DB::t( 'bc_ma_misa' ), $data ); }
		return array( 'ok' => true, 'message' => 'Đã lưu Unit ID cho ' . $coso . '.' );
	}
	public static function ma_misa_xoa( $coso_key ) {
		global $wpdb;
		$wpdb->delete( VHG_DB::t( 'bc_ma_misa' ), array( 'coso_key' => (string) $coso_key ) );
		return array( 'ok' => true, 'message' => 'Đã xoá.' );
	}
	/** Mồi: tạo một dòng cho mỗi cơ sở đang có trong danh mục ghế (unit_id để trống, kế toán điền). */
	public static function ma_misa_seed() {
		global $wpdb;
		$them = 0;
		$seen = array();
		foreach ( VHG_May::ds_may() as $m ) {
			$coso = trim( (string) ( isset( $m['coso_ten'] ) ? $m['coso_ten'] : '' ) );
			if ( '' === $coso ) { continue; }
			$ck = self::squash( $coso );
			if ( isset( $seen[ $ck ] ) ) { continue; }
			$seen[ $ck ] = true;
			$co = $wpdb->get_var( $wpdb->prepare( 'SELECT coso_key FROM ' . VHG_DB::t( 'bc_ma_misa' ) . ' WHERE coso_key=%s', $ck ) );
			if ( $co ) { continue; }
			$wpdb->insert( VHG_DB::t( 'bc_ma_misa' ), array( 'coso_key' => $ck, 'coso' => $coso,
				'unit_id' => '', 'unit_name' => $coso, 'vung' => '', 'thu_tu' => 0, 'ghi_chu' => 'điền Unit ID' ) );
			$them++;
		}
		return array( 'ok' => true, 'them' => $them, 'message' => 'Đã mồi ' . $them . ' cơ sở (điền Unit ID rồi lưu).' );
	}

	// ══════════════════════════════════════════════════════════════════ SELF-TEST (chỉ đọc)

	/**
	 * KIỂM TRA NHANH logic thuần (không đụng CSDL) — port các bất biến từ app gốc (11_KiemTraNhanh).
	 * Chạy được cả trên web (Admin bấm) lẫn ngoài dòng lệnh. Canh đúng chỗ dễ vỡ: phân bổ tiền
	 * (ổn định), mã lô (chống áp trùng), dịch tháng, đọc ngày, gộp mã.
	 */
	public static function selftest() {
		$t = array();
		$ok = function ( $name, $cond, $detail = '' ) use ( &$t ) { $t[] = array( 'name' => $name, 'pass' => (bool) $cond, 'detail' => (string) $detail ); };
		$eq = function ( $name, $got, $want ) use ( &$t ) {
			$g = wp_json_encode( $got ); $w = wp_json_encode( $want );
			$t[] = array( 'name' => $name, 'pass' => $g === $w, 'detail' => $g === $w ? '' : ( 'ra ' . $g . ' · cần ' . $w ) );
		};

		$eq( 'Đọc ngày yyyy-mm-dd', self::ngay_( '2026-08-05x' ), '2026-08-05' );
		$eq( 'Tháng dịch +1 qua năm', self::thang_dich_( '2026-12', 1 ), '2027-01' );
		$eq( 'Tháng dịch -1 qua năm', self::thang_dich_( '2026-01', -1 ), '2025-12' );
		$eq( 'Gộp mã bỏ khoảng trắng', self::squash( 'KH00119 MTDMN 0001' ), 'KH00119MTDMN0001' );

		$chairs = array(
			array( 'report_id' => 'R', 'ma' => 'B', 'ngay' => '2026-08-02', 'cash' => 50000 ),
			array( 'report_id' => 'R', 'ma' => 'A', 'ngay' => '2026-08-01', 'cash' => 30000 ),
			array( 'report_id' => 'R', 'ma' => 'C', 'ngay' => '2026-08-03', 'cash' => 20000 ),
		);
		$nhan = function ( $al ) { return array_map( function ( $x ) { return $x['ma'] . ':' . $x['paid']; }, $al['rows'] ); };
		$a1 = self::allocate_( $chairs, 100000 );
		$eq( 'Nộp đủ: chia hết 3 ghế', $nhan( $a1 ), array( 'A:30000', 'B:50000', 'C:20000' ) );
		$eq( 'Nộp đủ: không dư', $a1['left'], 0 );
		$a2 = self::allocate_( $chairs, 60000 );
		$eq( 'Nộp thiếu: ưu tiên ngày sớm', $nhan( $a2 ), array( 'A:30000', 'B:30000' ) );
		$eq( 'Nộp thừa: giữ phần dư', self::allocate_( $chairs, 120000 )['left'], 20000 );
		$eq( 'Phân bổ ỔN ĐỊNH (chạy lại y nguyên)', self::allocate_( $chairs, 60000 ), $a2 );
		$eq( 'Nộp 0 không ghi ô nào', count( self::allocate_( $chairs, 0 )['rows'] ), 0 );

		$f1 = array( array( 'date' => '2026-08-01', 'amount' => 1500000, 'desc' => 'KH119 nop' ), array( 'date' => '2026-08-02', 'amount' => 30000, 'desc' => 'QR SBCD1' ) );
		$f2 = array( $f1[1], $f1[0] );
		$f3 = array( array( 'date' => '2026-08-01', 'amount' => 1500001, 'desc' => 'KH119 nop' ), $f1[1] );
		$eq( 'Cùng dữ liệu khác thứ tự = CÙNG lô', self::batch_( 'CK', $f1 ), self::batch_( 'CK', $f2 ) );
		$ok( 'Lệch 1 đồng = KHÁC lô', self::batch_( 'CK', $f1 ) !== self::batch_( 'CK', $f3 ) );
		$ok( 'Khác loại đối soát = KHÁC lô', self::batch_( 'CK', $f1 ) !== self::batch_( 'TAY', $f1 ) );

		$pass = 0; foreach ( $t as $x ) { if ( $x['pass'] ) { $pass++; } }
		return array( 'ok' => true, 'passed' => $pass, 'total' => count( $t ), 'failed' => count( $t ) - $pass, 'tests' => $t );
	}

	// ══════════════════════════════════════════════════════════════════ XUẤT MISA (chứng từ)

	private static function dmy_( $d ) {
		$m = self::ngay_( $d );
		return preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $m, $x ) ? ( $x[3] . '/' . $x[2] . '/' . $x[1] ) : $m;
	}

	/**
	 * CHỨNG TỪ MISA — CHỈ ghế ĐÃ DUYỆT. Tiền mặt 1 dòng, QR 1 dòng (bản chỉ-tiền-mặt bỏ dòng QR).
	 * Trả AoA (mảng dòng) + meta; client dựng CSV tải về. Số chứng từ: 1 số/1 ngày (tuỳ chọn).
	 */
	public static function misa_chungtu( $from, $to, $thang, $chi_tien_mat, $so_ct_dau ) {
		global $wpdb;
		$f = self::ngay_( $from ); $t = self::ngay_( $to );
		$where = 'd.kt_duyet=1 AND d.chi_so_sau IS NOT NULL';
		$args = array();
		if ( '' !== $f && '' !== $t ) { $where .= ' AND d.ngay BETWEEN %s AND %s'; $args[] = $f; $args[] = $t; }
		else { $where .= ' AND DATE_FORMAT(d.ngay,%s)=%s'; $args[] = '%Y-%m'; $args[] = self::thang_( $thang ); }
		$sql = 'SELECT d.ngay, d.ma_may, d.ten, d.tien_mat, d.qr, d.dieu_chinh, d.ghi_chu, d.nop_trang_thai, h.coso'
			. ' FROM ' . VHG_DB::t( 'bc_dong' ) . ' d JOIN ' . VHG_DB::t( 'bc' ) . ' h ON h.report_id=d.report_id'
			. ' WHERE ' . $where . ' ORDER BY d.ngay ASC, d.ma_may ASC';
		$rows = $wpdb->get_results( $args ? $wpdb->prepare( $sql, $args ) : $sql, ARRAY_A );

		/* Số chứng từ: tách 'NVKMN1542' → tiền tố + số + độ rộng. */
		$goc = null;
		$s = trim( (string) $so_ct_dau );
		if ( '' !== $s && preg_match( '/^(.*?)(\d+)\s*$/', $s, $mm ) ) {
			$goc = array( 'tien' => $mm[1], 'so' => (int) $mm[2], 'rong' => strlen( $mm[2] ) );
		}
		$soCt = function ( $i ) use ( $goc ) {
			if ( ! $goc ) { return ''; }
			return $goc['tien'] . str_pad( (string) ( $goc['so'] + $i ), $goc['rong'], '0', STR_PAD_LEFT );
		};

		$head = array( 'Ngày chứng từ', 'Ngày hạch toán', 'Số chứng từ', 'Diễn giải', 'Diễn giải (Hạch toán)',
			'TK Nợ', 'TK Có', 'Số tiền', 'Số tiền quy đổi', 'Mã đối tượng Nợ', 'Mã đơn vị',
			'Tên đơn vị', 'Tên đối tượng nợ', 'Tên cơ sở', 'Ghi chú' );
		$aoa = array( $head );
		$ngayCua = ''; $iNgay = -1; $tm = 0; $qr = 0; $soCtTheoNgay = array();
		foreach ( (array) $rows as $r ) {
			$d = self::ngay_( $r['ngay'] );
			$dg = 'Doanh thu Posh MN ' . self::dmy_( $d );
			$cash = (int) $r['tien_mat']; $q = (int) $r['qr'];
			$dong = function ( $sotien, $ghichu ) use ( &$aoa, &$ngayCua, &$iNgay, &$soCtTheoNgay, $d, $soCt, $dg, $r ) {
				if ( $d !== $ngayCua ) { $ngayCua = $d; $iNgay++; }
				$sc = $soCt( $iNgay );
				if ( '' !== $sc ) { $soCtTheoNgay[ $d ] = $sc; }
				$aoa[] = array( $d, $d, $sc, $dg, $dg, '131', '5113', (int) $sotien, '', '', (string) $r['ma_may'],
					(string) $r['ten'], '', (string) $r['coso'], $ghichu );
			};
			if ( $cash ) {
				$gc = 'Nộp tiền mặt';
				$st = strtolower( (string) $r['nop_trang_thai'] );
				if ( 'transfer' === $r['nop_trang_thai'] || strpos( $st, 'transfer' ) !== false ) { $gc = 'Chuyển khoản'; }
				$tm += $cash; $dong( $cash, $gc );
			}
			if ( $q && ! $chi_tien_mat ) { $qr += $q; $dong( $q, 'QR ngân hàng' ); }
		}
		return array( 'ok' => true, 'aoa' => $aoa, 'soCot' => count( $head ), 'rows' => count( $aoa ) - 1,
			'soNgay' => $iNgay + 1, 'soCtTheoNgay' => $soCtTheoNgay, 'tienMat' => $tm, 'tienQr' => $qr,
			'tong' => $tm + $qr, 'chiTienMat' => (bool) $chi_tien_mat,
			'fileName' => 'Chung_Tu_Doanh_Thu_POSH' . ( $chi_tien_mat ? '_CHI_TIEN_MAT' : '' ) . '.csv' );
	}

	// ══════════════════════════════════════════════════════════════════ BÁO CÁO NGÀY (cross-tab)

	public static function baocao_ngay( $thang, $chi_da_duyet ) {
		global $wpdb;
		$th = self::thang_( $thang );
		list( $y, $mo ) = array_map( 'intval', explode( '-', $th ) );
		$soNgay = (int) gmdate( 't', gmmktime( 0, 0, 0, $mo, 1, $y ) );
		$w = 'd.chi_so_sau IS NOT NULL AND DATE_FORMAT(d.ngay,%s)=%s' . ( $chi_da_duyet ? ' AND d.kt_duyet=1' : '' );
		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT h.coso, h.coso_key, DAY(d.ngay) ng, d.tong FROM ' . VHG_DB::t( 'bc_dong' ) . ' d'
			. ' JOIN ' . VHG_DB::t( 'bc' ) . ' h ON h.report_id=d.report_id WHERE ' . $w, '%Y-%m', $th ), ARRAY_A );
		$ma = array();
		foreach ( $wpdb->get_results( 'SELECT coso_key, unit_id, unit_name, vung, thu_tu FROM ' . VHG_DB::t( 'bc_ma_misa' ), ARRAY_A ) as $m ) {
			$ma[ $m['coso_key'] ] = $m;
		}
		$cs = array();
		foreach ( (array) $rows as $r ) {
			$k = $r['coso_key'];
			if ( ! isset( $cs[ $k ] ) ) {
				$m = isset( $ma[ $k ] ) ? $ma[ $k ] : array();
				$cs[ $k ] = array( 'coso' => $r['coso'], 'unit_id' => isset( $m['unit_id'] ) ? $m['unit_id'] : '',
					'unit_name' => ! empty( $m['unit_name'] ) ? $m['unit_name'] : $r['coso'],
					'vung' => isset( $m['vung'] ) ? $m['vung'] : '', 'thu_tu' => isset( $m['thu_tu'] ) ? (int) $m['thu_tu'] : 0,
					'ngay' => array(), 'tong' => 0 );
			}
			$cs[ $k ]['ngay'][ (int) $r['ng'] ] = ( isset( $cs[ $k ]['ngay'][ (int) $r['ng'] ] ) ? $cs[ $k ]['ngay'][ (int) $r['ng'] ] : 0 ) + (int) $r['tong'];
			$cs[ $k ]['tong'] += (int) $r['tong'];
		}
		/* Xếp theo vùng (thu_tu nhỏ trước, rồi tên), thiếu unit_id dồn cuối. */
		$ds = array_values( $cs );
		usort( $ds, function ( $a, $b ) {
			$va = $a['unit_id'] ? 0 : 1; $vb = $b['unit_id'] ? 0 : 1;
			if ( $va !== $vb ) { return $va - $vb; }
			if ( $a['vung'] !== $b['vung'] ) { return strcmp( $a['vung'], $b['vung'] ); }
			if ( $a['thu_tu'] !== $b['thu_tu'] ) { return $a['thu_tu'] - $b['thu_tu']; }
			return strcmp( (string) $a['unit_id'], (string) $a['unit_id'] );
		} );
		$tuan_ = function ( $d ) { return $d <= 7 ? 0 : ( $d <= 14 ? 1 : ( $d <= 21 ? 2 : 3 ) ); };
		$tg = (int) get_option( 'vhg_sgd_ty_gia', 20000 ); if ( $tg <= 0 ) { $tg = 20000; }

		$head = array( 'Unit ID', 'Tên cơ sở' );
		for ( $i = 1; $i <= $soNgay; $i++ ) { $head[] = str_pad( (string) $i, 2, '0', STR_PAD_LEFT ); }
		$head[] = 'Total';
		for ( $w = 1; $w <= 4; $w++ ) { $head[] = 'Week ' . $w; }
		$head[] = 'Total';

		$tongNgay = array(); $tongTuan = array( 0, 0, 0, 0 ); $tongCong = 0; $thieu = array();
		/* Dựng MỘT khối theo hệ số $chia (1 = VND, $tg = SGD). Cùng dữ liệu, khác chia. */
		$khoi = function ( $chia, $nhan ) use ( $ds, $soNgay, $tuan_, &$tongNgay, &$tongTuan, &$tongCong, &$thieu ) {
			$lam_dau = ( 1 === $chia );
			$so = function ( $v ) use ( $chia ) { return 1 === $chia ? (int) $v : round( $v / $chia, 1 ); };
			$aoa = array();
			$d0 = array( 'DAILY REPORT', $nhan ); for ( $i = 0; $i < $soNgay + 6; $i++ ) { $d0[] = ''; } $aoa[] = $d0;
			$head2 = array( 'Unit ID', "Unit's name" );
			for ( $i = 1; $i <= $soNgay; $i++ ) { $head2[] = str_pad( (string) $i, 2, '0', STR_PAD_LEFT ); }
			$head2[] = 'Total'; for ( $w = 1; $w <= 4; $w++ ) { $head2[] = 'Week ' . $w; } $head2[] = 'Total';
			$aoa[] = $head2;
			foreach ( $ds as $o ) {
				$r = array( $o['unit_id'], $o['unit_name'] );
				$t = 0; $tw = array( 0, 0, 0, 0 );
				for ( $i = 1; $i <= $soNgay; $i++ ) {
					$v = isset( $o['ngay'][ $i ] ) ? (int) $o['ngay'][ $i ] : 0;
					$r[] = $v ? $so( $v ) : '';
					$t += $v; $tw[ $tuan_( $i ) ] += $v;
					if ( $lam_dau ) { $tongNgay[ $i ] = ( isset( $tongNgay[ $i ] ) ? $tongNgay[ $i ] : 0 ) + $v; }
				}
				$r[] = $so( $t );
				for ( $w = 0; $w < 4; $w++ ) { $r[] = $so( $tw[ $w ] ); if ( $lam_dau ) { $tongTuan[ $w ] += $tw[ $w ]; } }
				$r[] = $so( $t );
				if ( $lam_dau ) { $tongCong += $t; if ( ! $o['unit_id'] ) { $thieu[] = $o['coso']; } }
				$aoa[] = $r;
			}
			$tr = array( 'TỔNG', $nhan );
			for ( $i = 1; $i <= $soNgay; $i++ ) { $tr[] = $so( isset( $tongNgay[ $i ] ) ? $tongNgay[ $i ] : 0 ); }
			$tr[] = $so( $tongCong );
			for ( $w = 0; $w < 4; $w++ ) { $tr[] = $so( $tongTuan[ $w ] ); }
			$tr[] = $so( $tongCong );
			$aoa[] = $tr;
			return $aoa;
		};

		$aoa = $khoi( 1, 'VND' );
		$aoa[] = array_fill( 0, $soNgay + 8, '' );          // dòng trống ngăn hai khối
		foreach ( $khoi( $tg, 'SGD' ) as $r ) { $aoa[] = $r; }

		return array( 'ok' => true, 'aoa' => $aoa, 'soCot' => count( $head ), 'thang' => $th,
			'soCoSo' => count( $ds ), 'tong' => $tongCong, 'tyGiaSgd' => $tg, 'tongSgd' => round( $tongCong / $tg, 1 ),
			'thieuUnitId' => $thieu, 'chiDaDuyet' => (bool) $chi_da_duyet,
			'fileName' => 'Bao_Cao_Ngay_POSH_' . str_replace( '-', '_', $th ) . ( $chi_da_duyet ? '_da_duyet' : '' ) . '.csv' );
	}

	public static function cong_no_dat( $thang, $coso, $so_tien, $ghichu, $boi ) {
		global $wpdb;
		$th = self::thang_( $thang ); $coso = trim( (string) $coso );
		if ( '' === $coso ) { return array( 'ok' => false, 'message' => 'Thiếu cơ sở.' ); }
		$data = array( 'thang' => $th, 'coso' => $coso, 'coso_key' => self::squash( $coso ),
			'so_tien' => (int) $so_tien, 'chot_luc' => current_time( 'mysql' ), 'boi' => (string) $boi, 'ghi_chu' => mb_substr( trim( (string) $ghichu ), 0, 250 ) );
		$co = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . VHG_DB::t( 'bc_congno_dau' ) . ' WHERE coso_key=%s AND thang=%s', self::squash( $coso ), $th ) );
		if ( $co ) { $wpdb->update( VHG_DB::t( 'bc_congno_dau' ), $data, array( 'id' => (int) $co ) ); }
		else { $wpdb->insert( VHG_DB::t( 'bc_congno_dau' ), $data ); }
		return array( 'ok' => true, 'message' => 'Đã đặt dư đầu kỳ ' . $th . ' · ' . $coso . '.' );
	}

	// ══════════════════════════════════════════════════════════════════ NHẬP DOANH THU CŨ (CSV)

	/**
	 * Đọc ô nộp tiền của một dòng CSV cũ về đúng bộ từ vựng dòng ghế:
	 *   hinhthuc ∈ {cash, transfer, ''}   trang_thai ∈ {paid, thieu, unpaid}
	 * $tm = tiền mặt PHẢI nộp của ghế (cột `cash`). So `paidAmount` với nó để ra paid/thieu.
	 */
	private static function nop_tu_csv_( $r0, $tm ) {
		$method = strtolower( trim( (string) ( isset( $r0['method'] ) ? $r0['method'] : '' ) ) );
		$ref    = trim( (string) ( isset( $r0['ref'] ) ? $r0['ref'] : '' ) );
		$bank   = trim( (string) ( isset( $r0['bank'] ) ? $r0['bank'] : '' ) );
		$paid   = self::songuyen_( isset( $r0['paidAmount'] ) ? $r0['paidAmount'] : '' );
		$paid   = ( null === $paid ) ? 0 : (int) $paid;
		$ngay   = self::ngay_( isset( $r0['paidDate'] ) ? $r0['paidDate'] : '' );

		/* Chuẩn hoá trạng thái về ASCII: nhãn cũ có dấu ("Đã nộp (CK)", "Nộp thiếu"). strtolower KHÔNG
		   hạ được chữ có dấu — phải remove_accents trước, nếu không /da nop/ và /thieu/ đều trượt. */
		$st_raw = trim( (string) ( isset( $r0['paidStatus'] ) ? $r0['paidStatus'] : '' ) );
		$status = strtolower( function_exists( 'remove_accents' ) ? remove_accents( $st_raw ) : $st_raw );

		$is_transfer = ( 'transfer' === $method ) || ( '' !== $ref ) || ( '' !== $bank )
			|| ( false !== strpos( $status, 'transfer' ) ) || ( false !== strpos( $status, '(ck)' ) );
		$is_cash  = ( 'cash' === $method ) || ( false !== strpos( $status, 'paid_cash' ) )
			|| ( false !== strpos( $status, '(tm)' ) ) || ( false !== strpos( $status, 'tien mat' ) );
		$thieu    = ( false !== strpos( $status, 'thieu' ) );
		$paidfull = ! $thieu && ( ( false !== strpos( $status, 'paid_cash' ) )
			|| ( false !== strpos( $status, 'paid_transfer' ) ) || ( false !== strpos( $status, 'da nop' ) ) );
		$unpaid   = ! $paidfull && ( ( 'unpaid' === $method ) || ( false !== strpos( $status, 'unpaid' ) )
			|| ( false !== strpos( $status, 'chua' ) ) || ( false !== strpos( $status, 'khong nop' ) ) );

		$hthuc = $is_transfer ? 'transfer' : ( ( $is_cash || $paid > 0 || $paidfull ) ? 'cash' : '' );

		/* Số tiền đã nộp: lấy cột `cashPaidAmount`. CHỈ khi trạng thái khẳng định ĐÃ NỘP ĐỦ MÀ cột
		   tiền bỏ TRỐNG/0 (dữ liệu cũ hay để trống) mới suy ra = phần tiền mặt phải thu. Số dương
		   nhỏ hơn là NỘP THIẾU thật — không được nống lên thành đủ. */
		$amt = $paid;
		if ( $paidfull && $tm > 0 && $amt <= 0 ) { $amt = $tm; }

		if ( ( $unpaid && $amt <= 0 ) || ( '' === $hthuc && $amt <= 0 ) ) {
			return array( 'so_tien' => 0, 'trang_thai' => 'unpaid', 'hinhthuc' => '', 'ngay' => null );
		}
		if ( $tm <= 0 || $amt >= $tm ) { $tt = 'paid'; }
		elseif ( $amt > 0 )           { $tt = 'thieu'; }
		else                          { return array( 'so_tien' => 0, 'trang_thai' => 'unpaid', 'hinhthuc' => '', 'ngay' => null ); }
		if ( '' === $hthuc ) { $hthuc = 'cash'; }
		return array( 'so_tien' => max( 0, $amt ), 'trang_thai' => $tt, 'hinhthuc' => $hthuc,
			'ngay' => ( '' !== $ngay ) ? $ngay : null );
	}

	/** Cơ sở id theo TÊN — khớp bỏ dấu để không tạo trùng; chưa có thì tạo mới. Trả 0 nếu bí. */
	private static function coso_id_( $ten, &$moi ) {
		if ( ! class_exists( 'VHG_May' ) ) { return 0; }
		$key = self::squash( $ten );
		foreach ( (array) VHG_May::ds_coso() as $c ) {
			if ( self::squash( isset( $c['ten'] ) ? $c['ten'] : '' ) === $key ) { return (int) $c['id']; }
		}
		$r = VHG_May::luu_coso( 0, $ten );
		if ( ! empty( $r['id'] ) ) { $moi[ $key ] = true; return (int) $r['id']; }
		return 0;
	}

	private static function may_ton_tai_( $ma ) {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare(
			'SELECT id FROM ' . VHG_DB::t( 'may' ) . ' WHERE ma=%s LIMIT 1', (string) $ma ) );
	}

	/** Dựng đầy đủ 1 dòng bc_dong từ 1 dòng CSV (tiền chép nguyên). '_np' kèm để header cộng. */
	private static function dong_moi_( $rid, $ngay, $ma, $r0, $duyet, $nay ) {
		$before = self::songuyen_( isset( $r0['before'] ) ? $r0['before'] : '' );
		$after  = self::songuyen_( isset( $r0['after'] ) ? $r0['after'] : '' );
		$tm     = (int) ( isset( $r0['cash'] ) ? $r0['cash'] : 0 );
		$np     = self::nop_tu_csv_( $r0, $tm );
		$anh    = array();
		if ( isset( $r0['images'] ) && is_array( $r0['images'] ) ) {
			foreach ( $r0['images'] as $u ) { $u = trim( (string) $u ); if ( '' !== $u ) { $anh[] = $u; } }
		}
		return array(
			'report_id'      => $rid,
			'ma_may'         => (string) $ma,
			'ten'            => mb_substr( (string) ( isset( $r0['chairName'] ) ? $r0['chairName'] : $ma ), 0, 190 ),
			'ngay'           => $ngay,
			'chi_so_truoc'   => $before,
			'chi_so_sau'     => $after,
			'actual'         => (int) ( isset( $r0['actual'] ) ? $r0['actual'] : 0 ),
			'tien_mat'       => $tm,
			'qr'             => (int) ( isset( $r0['qr'] ) ? $r0['qr'] : 0 ),
			'dieu_chinh'     => (int) ( isset( $r0['adjust'] ) ? $r0['adjust'] : 0 ),
			'tong'           => (int) ( isset( $r0['total'] ) ? $r0['total'] : 0 ),
			'ghi_chu'        => mb_substr( trim( (string) ( isset( $r0['note'] ) ? $r0['note'] : '' ) ), 0, 250 ),
			'anh'            => count( $anh ) ? wp_json_encode( $anh ) : null,
			'nop_so_tien'    => $np['so_tien'],
			'nop_trang_thai' => $np['trang_thai'],
			'nop_hinhthuc'   => $np['hinhthuc'],
			'nop_ngay'       => $np['ngay'],
			'kt_duyet'       => $duyet ? 1 : 0,
			'kt_duyet_luc'   => $duyet ? $nay : null,
			'_np'            => $np,
		);
	}

	/**
	 * BỔ SUNG vào 1 dòng ghế ĐÃ CÓ — CHỈ điền ô đang TRỐNG, không đè giá trị đã nhập.
	 * Anh Thắng 27/08: *"chỉ đè dữ liệu khi nó trống, tránh đè lần 2"*. Trả mảng cột cần cập nhật
	 * (rỗng = không đụng gì → chạy lại lần 2 không thay đổi gì, idempotent).
	 */
	private static function dien_o_( $ex, $r0 ) {
		$upd = array();
		$before = self::songuyen_( isset( $r0['before'] ) ? $r0['before'] : '' );
		$after  = self::songuyen_( isset( $r0['after'] ) ? $r0['after'] : '' );
		$tm     = (int) ( isset( $r0['cash'] ) ? $r0['cash'] : 0 );
		$co_so  = ( null !== $ex['chi_so_sau'] && '' !== (string) $ex['chi_so_sau'] );
		if ( ! $co_so ) {   // ghế chưa có chỉ số → cho điền số liệu
			if ( null === $ex['chi_so_truoc'] && null !== $before ) { $upd['chi_so_truoc'] = $before; }
			if ( null !== $after )                                  { $upd['chi_so_sau']   = $after; }
			if ( 0 === (int) $ex['actual'] )     { $upd['actual']     = (int) ( isset( $r0['actual'] ) ? $r0['actual'] : 0 ); }
			if ( 0 === (int) $ex['tien_mat'] )   { $upd['tien_mat']   = $tm; }
			if ( 0 === (int) $ex['qr'] )         { $upd['qr']         = (int) ( isset( $r0['qr'] ) ? $r0['qr'] : 0 ); }
			if ( 0 === (int) $ex['dieu_chinh'] ) { $upd['dieu_chinh'] = (int) ( isset( $r0['adjust'] ) ? $r0['adjust'] : 0 ); }
			if ( 0 === (int) $ex['tong'] )       { $upd['tong']       = (int) ( isset( $r0['total'] ) ? $r0['total'] : 0 ); }
		}
		$note = mb_substr( trim( (string) ( isset( $r0['note'] ) ? $r0['note'] : '' ) ), 0, 250 );
		if ( '' === trim( (string) $ex['ghi_chu'] ) && '' !== $note ) { $upd['ghi_chu'] = $note; }
		$anh = array();
		if ( isset( $r0['images'] ) && is_array( $r0['images'] ) ) {
			foreach ( $r0['images'] as $u ) { $u = trim( (string) $u ); if ( '' !== $u ) { $anh[] = $u; } }
		}
		if ( '' === trim( (string) $ex['anh'] ) && count( $anh ) ) { $upd['anh'] = wp_json_encode( $anh ); }
		$np = self::nop_tu_csv_( $r0, $tm );
		if ( 0 === (int) $ex['nop_so_tien'] && in_array( (string) $ex['nop_trang_thai'], array( '', 'unpaid' ), true ) && $np['so_tien'] > 0 ) {
			$upd['nop_so_tien']    = $np['so_tien'];
			$upd['nop_trang_thai'] = $np['trang_thai'];
			$upd['nop_hinhthuc']   = $np['hinhthuc'];
			$upd['nop_ngay']       = $np['ngay'];
		}
		return $upd;
	}

	/**
	 * NHẬP DOANH THU CŨ từ bảng xuất web THU TIỀN cũ (port 08_ImportDoanhThu.gs).
	 *
	 * Anh Thắng 27/08/2026: *"đẩy dữ liệu cũ tháng 8 lên"*.
	 *
	 * 🔴 TIỀN CHÉP NGUYÊN TỪ FILE, KHÔNG TÍNH LẠI TỪ CHỈ SỐ.
	 *    Web cũ nhân ×10000 mỗi đơn vị chỉ số (vd 31→43 = 12 → actual 120.000). Hệ mới mặc định
	 *    ×5000 (VHG_Quy::don_vi()). Tính lại theo chỉ số = SAI. Số cũ đã chốt, chỉ chép: actual/
	 *    tiền_mặt/qr/điều_chỉnh/tổng lấy đúng cột trong file. Chỉ số trước/sau vẫn chép để NỐI dòng
	 *    thời gian chỉ số sang tháng sau (chi_so_truoc() dùng chung `bc_dong` + `chot`).
	 *
	 * 🔴 1 BÁO CÁO / CƠ SỞ / NGÀY: gộp dòng ghế theo (coso_key, ngay).
	 *    · Chưa có → tạo mới (ghi nhật ký hoàn tác viec='nhap').
	 *    · Đã có, MẶC ĐỊNH = BỔ SUNG: thêm ghế còn thiếu, và CHỈ điền ô đang trống (không đè giá
	 *      trị đã nhập) — chạy lại lần 2 không thay đổi gì. Anh Thắng: "chỉ đè khi trống".
	 *    · Đã có, $ghi_de = GHI ĐÈ HẲN: xoá bản cũ rồi chép lại (khi thật sự muốn thay).
	 *
	 * 🔴 $tao_ghe: tiện thể thêm CƠ SỞ + GHẾ vào danh mục (chỉ tạo cái CHƯA CÓ, không đụng ghế đã
	 *    khai giá/số tài khoản). Anh Thắng 27/08: "tiện thể bổ sung cơ sở và số lượng ghế luôn".
	 *
	 * @param array $rows  mỗi dòng: date, loc, chairCode, chairName, staff, before, after, actual,
	 *                     cash, qr, adjust, total, reportId, note, method(cash|transfer|unpaid),
	 *                     paidStatus, paidAmount, paidDate, ref, bank, images(mảng url)
	 */
	public static function nhap_doanhthu( $rows, $ghi_de, $danh_dau_duyet, $tao_ghe, $boi ) {
		global $wpdb;
		$rows = is_array( $rows ) ? $rows : array();
		if ( ! count( $rows ) ) { return array( 'ok' => false, 'message' => 'Không có dòng nào để nhập.' ); }
		$ghi_de  = ! empty( $ghi_de );
		$duyet   = ! empty( $danh_dau_duyet );
		$tao_ghe = ! empty( $tao_ghe );

		$loi = array();
		$nhom = array();   // (coso_key|ngay) => header + ['dong'=>[ma=>row]]
		foreach ( $rows as $i => $r0 ) {
			$r0   = (array) $r0;
			$ngay = self::ngay_( isset( $r0['date'] ) ? $r0['date'] : '' );
			$loc  = trim( (string) ( isset( $r0['loc'] ) ? $r0['loc'] : '' ) );
			$ma   = trim( (string) ( isset( $r0['chairCode'] ) ? $r0['chairCode'] : '' ) );
			if ( '' === $ngay || '' === $loc || '' === $ma ) {
				if ( count( $loi ) < 20 ) { $loi[] = 'Dòng ' . ( (int) $i + 1 ) . ': thiếu ngày/cơ sở/mã ghế.'; }
				continue;
			}
			$key = self::squash( $loc ) . '|' . $ngay;
			if ( ! isset( $nhom[ $key ] ) ) {
				$nhom[ $key ] = array( 'coso' => $loc, 'ngay' => $ngay, 'staff' => '', 'reportId' => '', 'dong' => array() );
			}
			if ( '' === $nhom[ $key ]['staff'] && ! empty( $r0['staff'] ) ) { $nhom[ $key ]['staff'] = trim( (string) $r0['staff'] ); }
			if ( '' === $nhom[ $key ]['reportId'] && ! empty( $r0['reportId'] ) ) { $nhom[ $key ]['reportId'] = trim( (string) $r0['reportId'] ); }
			$nhom[ $key ]['dong'][ $ma ] = $r0;   // cùng ghế/ngày: dòng sau đè dòng trước
		}
		if ( ! count( $nhom ) ) { return array( 'ok' => false, 'message' => 'Không đọc được dòng hợp lệ nào.', 'loi' => $loi ); }

		$them = 0; $de = 0; $bs = 0; $so_dong = 0; $rid_da = array();
		$ghe_moi = 0; $coso_moi = array();
		$nay = current_time( 'mysql' );
		$coso_id_cache = array();
		$dong_bang = VHG_DB::t( 'bc_dong' );

		foreach ( $nhom as $g ) {
			$coso = $g['coso']; $ngay = $g['ngay']; $ck = self::squash( $coso );

			// (3) BỔ SUNG DANH MỤC — cơ sở + ghế còn thiếu. Không đụng ghế đã khai.
			if ( $tao_ghe && class_exists( 'VHG_May' ) ) {
				if ( ! isset( $coso_id_cache[ $ck ] ) ) { $coso_id_cache[ $ck ] = self::coso_id_( $coso, $coso_moi ); }
				$coso_id = (int) $coso_id_cache[ $ck ];
				foreach ( $g['dong'] as $ma => $r0 ) {
					$ma = (string) $ma;
					if ( ! preg_match( '/^[A-Za-z0-9]{1,20}$/', $ma ) ) { continue; }  // mã có dấu/gạch: bỏ khai danh mục
					if ( self::may_ton_tai_( $ma ) ) { continue; }                     // ghế đã có → giữ nguyên
					VHG_May::luu_may( array( 'ma' => $ma, 'coso_id' => $coso_id,
						'ten_khai' => (string) ( isset( $r0['chairName'] ) ? $r0['chairName'] : $ma ) ) );
					$ghe_moi++;
				}
			}

			$cu = $wpdb->get_row( $wpdb->prepare(
				'SELECT * FROM ' . VHG_DB::t( 'bc' ) . ' WHERE coso_key=%s AND ngay=%s LIMIT 1', $ck, $ngay ), ARRAY_A );
			if ( $cu && $ghi_de ) {   // GHI ĐÈ HẲN: xoá bản cũ rồi chép lại như mới
				$wpdb->delete( $dong_bang, array( 'report_id' => $cu['report_id'] ) );
				$wpdb->delete( VHG_DB::t( 'bc' ), array( 'id' => (int) $cu['id'] ) );
				$cu = null; $de++;
			}

			if ( ! $cu ) {
				// ---- TẠO MỚI báo cáo + toàn bộ dòng ----
				$rid = (string) $g['reportId'];
				if ( '' === $rid || $wpdb->get_var( $wpdb->prepare(
					'SELECT id FROM ' . VHG_DB::t( 'bc' ) . ' WHERE report_id=%s', $rid ) ) ) {
					$rid = 'IMP-' . str_replace( '-', '', $ngay ) . '-' . substr( md5( $ck . '|' . $ngay ), 0, 8 );
				}
				$rid = mb_substr( $rid, 0, 40 );

				$hdr_paid = 0; $hdr_hthuc = ''; $hdr_ngay = null; $hdr_ref = ''; $hdr_bank = '';
				foreach ( $g['dong'] as $ma => $r0 ) {
					$d = self::dong_moi_( $rid, $ngay, $ma, $r0, $duyet, $nay );
					$np = $d['_np']; unset( $d['_np'] );
					$wpdb->insert( $dong_bang, $d );
					$so_dong++;
					$hdr_paid += $np['so_tien'];
					if ( 'transfer' === $np['hinhthuc'] ) { $hdr_hthuc = 'transfer'; }
					elseif ( 'cash' === $np['hinhthuc'] && '' === $hdr_hthuc ) { $hdr_hthuc = 'cash'; }
					if ( $np['ngay'] && ( null === $hdr_ngay || $np['ngay'] > $hdr_ngay ) ) { $hdr_ngay = $np['ngay']; }
					if ( '' === $hdr_ref && ! empty( $r0['ref'] ) )   { $hdr_ref  = mb_substr( trim( (string) $r0['ref'] ), 0, 120 ); }
					if ( '' === $hdr_bank && ! empty( $r0['bank'] ) ) { $hdr_bank = mb_substr( trim( (string) $r0['bank'] ), 0, 60 ); }
				}
				$hdr_tt = ( $hdr_paid > 0 && '' !== $hdr_hthuc )
					? ( 'transfer' === $hdr_hthuc ? 'paid_transfer' : 'paid_cash' ) : 'unpaid';
				$wpdb->insert( VHG_DB::t( 'bc' ), array(
					'report_id'      => $rid,
					'ngay'           => $ngay,
					'coso'           => mb_substr( (string) $coso, 0, 190 ),
					'coso_key'       => $ck,
					'nhan_vien'      => mb_substr( (string) $g['staff'], 0, 190 ),
					'nop_hinhthuc'   => ( '' === $hdr_hthuc ) ? 'unpaid' : $hdr_hthuc,
					'nop_trang_thai' => $hdr_tt,
					'nop_so_tien'    => $hdr_paid,
					'nop_ngay'       => $hdr_ngay,
					'ck_ref'         => $hdr_ref,
					'ck_bank'        => $hdr_bank,
					'tao_luc'        => $nay,
					'sua_luc'        => $nay,
				) );
				$them++; $rid_da[] = $rid;   // chỉ báo cáo TẠO MỚI mới ghi hoàn tác
			} else {
				// ---- BỔ SUNG vào báo cáo ĐÃ CÓ: thêm ghế thiếu, điền ô trống, KHÔNG đè ----
				$rid = (string) $cu['report_id'];
				$co_bs = false;
				foreach ( $g['dong'] as $ma => $r0 ) {
					$ma = (string) $ma;
					$ex = $wpdb->get_row( $wpdb->prepare(
						'SELECT * FROM ' . $dong_bang . ' WHERE report_id=%s AND ma_may=%s LIMIT 1', $rid, $ma ), ARRAY_A );
					if ( ! $ex ) {
						$d = self::dong_moi_( $rid, $ngay, $ma, $r0, $duyet, $nay ); unset( $d['_np'] );
						$wpdb->insert( $dong_bang, $d );
						$so_dong++; $co_bs = true;
						continue;
					}
					$upd = self::dien_o_( $ex, $r0 );
					if ( count( $upd ) ) {
						$wpdb->update( $dong_bang, $upd, array( 'report_id' => $rid, 'ma_may' => $ma ) );
						$co_bs = true;
					}
				}
				// Header: chỉ điền chỗ trống (không đè tên nhân viên/số CK đã có).
				$hupd = array();
				if ( '' === trim( (string) $cu['nhan_vien'] ) && '' !== trim( (string) $g['staff'] ) ) { $hupd['nhan_vien'] = mb_substr( (string) $g['staff'], 0, 190 ); }
				if ( count( $hupd ) ) { $wpdb->update( VHG_DB::t( 'bc' ), $hupd, array( 'id' => (int) $cu['id'] ) ); }
				if ( $co_bs ) { $bs++; }
			}
		}

		if ( count( $rid_da ) ) {
			self::undo_ghi_( 'nhap', 'Nhập doanh thu cũ — ' . count( $rid_da ) . ' báo cáo mới.',
				array( 'report_ids' => $rid_da ), $boi );
		}
		$so_coso_moi = count( $coso_moi );
		return array( 'ok' => true, 'them' => $them, 'ghi_de' => $de, 'bo_sung' => $bs,
			'so_dong' => $so_dong, 'so_bao_cao' => count( $rid_da ), 'ghe_moi' => $ghe_moi,
			'coso_moi' => $so_coso_moi, 'loi' => $loi,
			'message' => 'Xong: tạo mới ' . $them . ' báo cáo, bổ sung ' . $bs . ' báo cáo đã có, ghi đè hẳn '
				. $de . '. Thêm ' . $so_dong . ' dòng ghế'
				. ( $tao_ghe ? ( ' · danh mục +' . $so_coso_moi . ' cơ sở, +' . $ghe_moi . ' ghế' ) : '' ) . '.'
				. ( count( $loi ) ? ' Có ' . count( $loi ) . ' dòng lỗi bị bỏ.' : '' ) );
	}
}
