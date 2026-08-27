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
		if ( 'sua' !== $u['viec'] ) { return array( 'ok' => false, 'message' => 'Chỉ hoàn tác được thao tác sửa số liệu ở đây.' ); }
		$ct = json_decode( (string) $u['chi_tiet'], true );
		if ( ! is_array( $ct ) ) { return array( 'ok' => false, 'message' => 'Nhật ký không đọc được.' ); }
		$n = 0;
		foreach ( $ct as $o ) {
			$wpdb->update( VHG_DB::t( 'bc_dong' ), array(
				'chi_so_truoc' => $o['chi_so_truoc'], 'chi_so_sau' => $o['chi_so_sau'], 'actual' => $o['actual'],
				'tien_mat' => $o['tien_mat'], 'qr' => $o['qr'], 'dieu_chinh' => $o['dieu_chinh'], 'tong' => $o['tong'],
				'ghi_chu' => $o['ghi_chu'] ), array( 'report_id' => $o['report_id'], 'ma_may' => $o['ma_may'] ) );
			$n++;
		}
		$wpdb->update( VHG_DB::t( 'bc_undo' ), array( 'da_hoan_tac' => 1 ), array( 'id' => $id ) );
		return array( 'ok' => true, 'changed' => $n, 'message' => 'Đã hoàn tác ' . $n . ' ghế.' );
	}
}
