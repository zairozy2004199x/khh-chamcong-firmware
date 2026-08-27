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
 *    Server TỰ TÍNH và TỰ ÉP chỉ số trước — KHÔNG tin số client gửi (enfoce_).
 *    App gốc cứng ×10000; ở đây dùng VHG_Quy::don_vi() để KHỚP với chốt ca máy trạm
 *    (cùng một máy đếm → một đơn vị).
 *
 * 🔴 "DÙNG CHUNG BẢNG CHỈ SỐ" với chốt ca: chi_so_truoc() lấy chỉ số sau gần nhất TRƯỚC ngày báo
 *    cáo từ CẢ `bc_dong` LẪN `chot` (chốt ca) — một dòng thời gian chỉ số duy nhất, không đếm đôi.
 *
 * 🔴 1 BÁO CÁO / CƠ SỞ / NGÀY: UNIQUE(coso_key, ngay) ở tầng CSDL; gửi lại = cập nhật.
 *
 * Phạm vi nhân viên (bản đầu): theo CƠ SỞ gán cho user trong VHG_Auth. User không gán cơ sở
 * (Admin/Quản lý) = xem/gửi cả chuỗi.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHG_BaoCao {

	const GIO_SUA = 24;   // nhân viên sửa được trong ngần này giờ kể từ lúc gửi

	/** Đơn vị mỗi chỉ số (đồng) — dùng CHUNG với chốt ca. */
	public static function don_vi() { return VHG_Quy::don_vi(); }

	/** Bỏ dấu + bỏ ký tự lạ + hoa, để so tên cơ sở bất kể cách gõ ("GO Dĩ An" ≡ "godian"). */
	public static function squash( $s ) {
		$s = remove_accents( (string) $s );
		return preg_replace( '/[^A-Z0-9]/', '', strtoupper( $s ) );
	}

	/** Cơ sở của user (''=toàn quyền, xem/gửi cả chuỗi). */
	public static function coso_cua( $ai ) {
		return isset( $ai['coso'] ) ? trim( (string) $ai['coso'] ) : '';
	}

	/** Bản ghi thuộc phạm vi của user? '' = toàn quyền. */
	public static function trong_pham_vi( $coso_toi, $coso_ghe ) {
		$t = trim( (string) $coso_toi );
		if ( '' === $t ) { return true; }
		return self::squash( $t ) === self::squash( $coso_ghe );
	}

	// ══════════════════════════════════════════════════════════════════ DANH MỤC GHẾ

	/** Ghế trong phạm vi user: [ ['ma','ten','coso'], ... ]. */
	public static function ds_ghe( $coso_toi ) {
		$ra = array();
		foreach ( VHG_May::ds_may() as $m ) {
			$coso = (string) ( isset( $m['coso_ten'] ) ? $m['coso_ten'] : '' );
			if ( ! self::trong_pham_vi( $coso_toi, $coso ) ) { continue; }
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
	 * CHỈ SỐ TRƯỚC của một ghế cho ngày báo cáo = chỉ số sau gần nhất có ngày < $ngay, lấy từ CẢ
	 * `bc_dong` LẪN `chot`. Có mốc reset (`may.moc_chiso`) hiệu lực ≤ $ngay và mới hơn số tìm được
	 * thì lấy mốc. Trả về (int) hoặc null (ghế chưa có số nào → lần đầu, nhận số client gõ).
	 */
	public static function chi_so_truoc( $ma_may, $ngay ) {
		global $wpdb;
		$ma = (string) $ma_may;
		$ngay = self::ngay_( $ngay );
		if ( '' === $ma || '' === $ngay ) { return null; }

		$found_cs = null; $found_d = '';
		// từ báo cáo
		$r1 = $wpdb->get_row( $wpdb->prepare(
			'SELECT chi_so_sau AS cs, ngay AS d FROM ' . VHG_DB::t( 'bc_dong' )
			. ' WHERE ma_may=%s AND ngay < %s AND chi_so_sau IS NOT NULL ORDER BY ngay DESC, chi_so_sau DESC LIMIT 1',
			$ma, $ngay ), ARRAY_A );
		if ( $r1 ) { $found_cs = (int) $r1['cs']; $found_d = (string) $r1['d']; }
		// từ chốt ca (chi_so tại thời điểm chốt)
		$r2 = $wpdb->get_row( $wpdb->prepare(
			'SELECT chi_so AS cs, DATE(tao_luc) AS d FROM ' . VHG_DB::t( 'chot' )
			. ' WHERE ma_may=%s AND DATE(tao_luc) < %s ORDER BY d DESC, chi_so DESC LIMIT 1',
			$ma, $ngay ), ARRAY_A );
		if ( $r2 && (string) $r2['d'] > $found_d ) { $found_cs = (int) $r2['cs']; $found_d = (string) $r2['d']; }

		// mốc reset (thay máy / đổi điểm) trên bảng `may`
		$mo = $wpdb->get_row( $wpdb->prepare(
			'SELECT moc_chiso AS cs, moc_chiso_ngay AS d FROM ' . VHG_DB::t( 'may' ) . ' WHERE ma=%s LIMIT 1',
			$ma ), ARRAY_A );
		if ( $mo && null !== $mo['cs'] && $mo['d'] ) {
			$od = self::ngay_( $mo['d'] );
			if ( $od && $od <= $ngay && ( '' === $found_d || $found_d < $od ) ) {
				return (int) $mo['cs'];
			}
		}
		return null === $found_cs ? null : (int) $found_cs;
	}

	/** Map { ma_may: chi_so_truoc|null } cho nhiều ghế. */
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
	private static function songuyen_( $v ) {
		if ( '' === $v || null === $v ) { return null; }
		return (int) $v;
	}
	/** actual / cash / tong theo công thức bất biến. */
	private static function tinh_( &$r ) {
		$dv = self::don_vi();
		$before = self::songuyen_( isset( $r['chi_so_truoc'] ) ? $r['chi_so_truoc'] : null );
		$after  = self::songuyen_( isset( $r['chi_so_sau'] ) ? $r['chi_so_sau'] : null );
		$qr = (int) ( isset( $r['qr'] ) ? $r['qr'] : 0 );
		$adj = (int) ( isset( $r['dieu_chinh'] ) ? $r['dieu_chinh'] : 0 );
		$actual = ( null === $before || null === $after ) ? 0 : ( $after - $before ) * $dv;
		$r['chi_so_truoc'] = $before;
		$r['chi_so_sau']   = $after;
		$r['qr'] = $qr; $r['dieu_chinh'] = $adj;
		$r['actual']  = $actual;
		$r['tien_mat'] = $actual - $qr + $adj;
		$r['tong']     = $r['tien_mat'] + $qr;
		return $r;
	}
	private static function ngay_sai_( $ngay ) {
		$d = self::ngay_( $ngay );
		if ( '' === $d ) { return 'Ngày báo cáo không đọc được.'; }
		$hom_nay = current_time( 'Y-m-d' );
		$so = (int) round( ( strtotime( $d ) - strtotime( $hom_nay ) ) / 86400 );
		if ( $so > 1 )    { return 'Ngày báo cáo ' . $d . ' ở tương lai (hôm nay ' . $hom_nay . ').'; }
		if ( $so < -366 ) { return 'Ngày báo cáo ' . $d . ' cách hôm nay quá 1 năm — nhờ kế toán nhập trực tiếp.'; }
		return '';
	}
	/** Ngày này của cơ sở này đang khoá? */
	public static function dang_khoa( $coso, $ngay ) {
		global $wpdb;
		$n = (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . VHG_DB::t( 'bc_khoa' ) . ' WHERE coso_key=%s AND ngay=%s',
			self::squash( $coso ), self::ngay_( $ngay ) ) );
		return $n > 0;
	}

	// ══════════════════════════════════════════════════════════════════ BOOTSTRAP

	public static function boot( $ai ) {
		global $wpdb;
		$coso_toi = self::coso_cua( $ai );
		$ghe = self::ds_ghe( $coso_toi );
		$cs = array();
		foreach ( $ghe as $g ) { if ( '' !== $g['coso'] ) { $cs[ $g['coso'] ] = true; } }
		$khoa = $wpdb->get_results(
			'SELECT coso, ngay FROM ' . VHG_DB::t( 'bc_khoa' ), ARRAY_A );
		$khoa_loc = array();
		foreach ( (array) $khoa as $k ) {
			if ( self::trong_pham_vi( $coso_toi, $k['coso'] ) ) {
				$khoa_loc[] = array( 'coso' => $k['coso'], 'ngay' => self::ngay_( $k['ngay'] ) );
			}
		}
		return array(
			'ok'      => true,
			'staff'   => (string) $ai['name'],
			'today'   => current_time( 'Y-m-d' ),
			'don_vi'  => self::don_vi(),
			'coso'    => array_keys( $cs ),
			'ghe'     => $ghe,
			'khoa'    => $khoa_loc,
		);
	}

	// ══════════════════════════════════════════════════════════════════ KIỂM 1 BÁO CÁO/NGÀY

	/** Header báo cáo của (cơ sở, ngày) — null nếu chưa có. */
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

	public static function kiem_ngay( $coso, $ngay, $ai ) {
		if ( ! self::trong_pham_vi( self::coso_cua( $ai ), $coso ) ) {
			return array( 'exists' => false );
		}
		$h = self::header_( $coso, $ngay );
		if ( ! $h ) { return array( 'exists' => false ); }
		$sum = self::tong_bc_( $h['report_id'] );
		return array( 'exists' => true, 'report_id' => $h['report_id'],
			'chairs' => $sum['so'], 'staff' => (string) $h['nhan_vien'],
			'cash' => $sum['tien_mat'], 'qr' => $sum['qr'], 'total' => $sum['tong'] );
	}

	private static function tong_bc_( $rid ) {
		global $wpdb;
		$r = $wpdb->get_row( $wpdb->prepare(
			'SELECT COUNT(*) so, COALESCE(SUM(tien_mat),0) tm, COALESCE(SUM(qr),0) qr,'
			. ' COALESCE(SUM(tong),0) tg FROM ' . VHG_DB::t( 'bc_dong' )
			. ' WHERE report_id=%s AND chi_so_sau IS NOT NULL', (string) $rid ), ARRAY_A );
		return array( 'so' => (int) $r['so'], 'tien_mat' => (int) $r['tm'],
			'qr' => (int) $r['qr'], 'tong' => (int) $r['tg'] );
	}

	// ══════════════════════════════════════════════════════════════════ GỬI BÁO CÁO

	public static function luu( $payload, $ai ) {
		global $wpdb;
		$p = is_array( $payload ) ? $payload : array();
		$coso_toi = self::coso_cua( $ai );
		$rows_in = isset( $p['rows'] ) && is_array( $p['rows'] ) ? $p['rows'] : array();
		if ( ! count( $rows_in ) ) { return array( 'ok' => false, 'message' => 'Chưa nhập số liệu ghế nào.' ); }

		$ngay = self::ngay_( isset( $p['date'] ) ? $p['date'] : '' );
		$coso = trim( (string) ( isset( $p['loc'] ) ? $p['loc'] : ( isset( $rows_in[0]['locName'] ) ? $rows_in[0]['locName'] : '' ) ) );
		if ( '' === $ngay ) { return array( 'ok' => false, 'message' => 'Chọn ngày báo cáo.' ); }
		if ( '' === $coso ) { return array( 'ok' => false, 'message' => 'Chọn cơ sở.' ); }
		if ( ! self::trong_pham_vi( $coso_toi, $coso ) ) {
			return array( 'ok' => false, 'message' => 'Cơ sở ' . $coso . ' không thuộc phạm vi của bạn.' );
		}
		$loi_ngay = self::ngay_sai_( $ngay );
		if ( '' !== $loi_ngay ) { return array( 'ok' => false, 'message' => $loi_ngay ); }
		if ( self::dang_khoa( $coso, $ngay ) ) {
			return array( 'ok' => false, 'message' => 'Cơ sở ' . $coso . ' ngày ' . $ngay
				. ' đang KHOÁ — nhờ kế toán mở lại nếu cần nhập bổ sung.' );
		}

		/* chuẩn hoá + ÉP chỉ số trước theo hệ thống, rồi tính lại tiền */
		$rows = array();
		foreach ( $rows_in as $r0 ) {
			$ma = trim( (string) ( isset( $r0['chairCode'] ) ? $r0['chairCode'] : ( isset( $r0['ma'] ) ? $r0['ma'] : '' ) ) );
			if ( '' === $ma ) { continue; }
			$after = isset( $r0['meterAfter'] ) ? $r0['meterAfter'] : ( isset( $r0['chi_so_sau'] ) ? $r0['chi_so_sau'] : '' );
			if ( '' === (string) $after || null === $after ) { continue; }   // ghế không thu (coThuGhe)
			$truoc_ht = self::chi_so_truoc( $ma, $ngay );
			$before = ( null !== $truoc_ht )
				? $truoc_ht                                              // ép theo hệ thống
				: self::songuyen_( isset( $r0['meterBefore'] ) ? $r0['meterBefore'] : '' );  // lần đầu: nhận client
			$r = array(
				'ma_may' => $ma,
				'ten'    => (string) ( isset( $r0['chairName'] ) ? $r0['chairName'] : $ma ),
				'ngay'   => $ngay,
				'chi_so_truoc' => $before,
				'chi_so_sau'   => (int) $after,
				'qr'     => (int) ( isset( $r0['qr'] ) ? $r0['qr'] : 0 ),
				'dieu_chinh' => (int) ( isset( $r0['adjust'] ) ? $r0['adjust'] : 0 ),
				'ghi_chu' => mb_substr( trim( (string) ( isset( $r0['note'] ) ? $r0['note'] : '' ) ), 0, 250 ),
			);
			self::tinh_( $r );
			// chặn số âm (sau < trước) — server chặn vì giao diện sửa được
			if ( null !== $r['chi_so_truoc'] && $r['chi_so_sau'] < $r['chi_so_truoc'] ) {
				return array( 'ok' => false, 'message' => 'Ghế ' . $r['ten'] . ': chỉ số sau ('
					. $r['chi_so_sau'] . ') nhỏ hơn chỉ số trước (' . $r['chi_so_truoc'] . '). '
					. 'Máy vừa thay/đổi điểm thì gửi đề nghị đổi chỉ số để kế toán duyệt.' );
			}
			$rows[] = $r;
		}
		if ( ! count( $rows ) ) { return array( 'ok' => false, 'message' => 'Chưa nhập chỉ số sau cho ghế nào.' ); }

		/* 1 báo cáo/cơ sở/ngày: có rồi -> cập nhật đúng report_id đó */
		$prev = self::header_( $coso, $ngay );
		$rid  = $prev ? (string) $prev['report_id']
			: ( 'RPT-' . current_time( 'YmdHis' ) . '-' . wp_rand( 100, 999 ) );

		$now = current_time( 'mysql' );
		/* Header + nộp tiền */
		$pay = self::doc_payment_( isset( $p['payment'] ) ? $p['payment'] : array(), $rows );
		$kt_locked = $prev && ! empty( $prev['kt_doi_soat'] );   // kế toán đã đối soát -> không đè nộp tiền
		$header = array(
			'report_id' => $rid, 'ngay' => $ngay, 'coso' => $coso, 'coso_key' => self::squash( $coso ),
			'nhan_vien' => (string) $ai['name'], 'sua_luc' => $now,
		);
		if ( ! $kt_locked ) {
			$header = array_merge( $header, array(
				'nop_hinhthuc'   => $pay['hinhthuc'],
				'nop_trang_thai' => $pay['trang_thai'],
				'nop_so_tien'    => $pay['so_tien'],
				'nop_ngay'       => $pay['ngay'],
				'nop_ghichu'     => $pay['ghichu'],
				'unpaid_lydo'    => $pay['unpaid_lydo'],
				'ck_ref'         => $pay['ck_ref'],
				'ck_bank'        => $pay['ck_bank'],
			) );
		}
		if ( $prev ) {
			$wpdb->update( VHG_DB::t( 'bc' ), $header, array( 'report_id' => $rid ) );
		} else {
			$header['tao_luc'] = $now;
			$wpdb->insert( VHG_DB::t( 'bc' ), $header );
		}

		/* Ảnh chứng từ nộp tiền -> chung_tu (giữ cũ nếu lần này không kèm) */
		$ct = self::luu_nhieu_anh_( isset( $p['proofs'] ) ? $p['proofs'] : null, $rid, 'chungtu' );
		if ( count( $ct ) ) {
			$wpdb->update( VHG_DB::t( 'bc' ), array( 'chung_tu' => wp_json_encode( $ct ) ), array( 'report_id' => $rid ) );
		}

		/* Ảnh ghế: chia theo tỉ lệ round(số ảnh / số ghế), dư dồn ghế cuối */
		$anh_ghe = self::chia_anh_ghe_( isset( $p['images'] ) ? $p['images'] : array(), count( $rows ), $rid );

		/* Ghi dòng ghế (upsert theo report_id|ma_may) */
		$gui_ma = array();
		foreach ( $rows as $i => $r ) {
			$gui_ma[ $r['ma_may'] ] = true;
			$data = array(
				'report_id' => $rid, 'ma_may' => $r['ma_may'], 'ten' => $r['ten'], 'ngay' => $ngay,
				'chi_so_truoc' => $r['chi_so_truoc'], 'chi_so_sau' => $r['chi_so_sau'],
				'actual' => $r['actual'], 'tien_mat' => $r['tien_mat'], 'qr' => $r['qr'],
				'dieu_chinh' => $r['dieu_chinh'], 'tong' => $r['tong'], 'ghi_chu' => $r['ghi_chu'],
			);
			if ( isset( $anh_ghe[ $i ] ) && count( $anh_ghe[ $i ] ) ) {
				$data['anh'] = wp_json_encode( $anh_ghe[ $i ] );
			}
			$cu = $wpdb->get_var( $wpdb->prepare(
				'SELECT id FROM ' . VHG_DB::t( 'bc_dong' ) . ' WHERE report_id=%s AND ma_may=%s',
				$rid, $r['ma_may'] ) );
			if ( $cu ) { $wpdb->update( VHG_DB::t( 'bc_dong' ), $data, array( 'id' => (int) $cu ) ); }
			else       { $wpdb->insert( VHG_DB::t( 'bc_dong' ), $data ); }
		}

		/* Ghế lần trước có, lần này KHÔNG gửi -> void (chi_so_sau NULL, tiền 0) để không tính nữa
		   và getLastMeters bỏ qua. Giữ dòng để còn dấu vết. */
		$bo = array();
		if ( $prev ) {
			$cũ = $wpdb->get_results( $wpdb->prepare(
				'SELECT ma_may FROM ' . VHG_DB::t( 'bc_dong' ) . ' WHERE report_id=%s', $rid ), ARRAY_A );
			foreach ( (array) $cũ as $c ) {
				$ma = (string) $c['ma_may'];
				if ( isset( $gui_ma[ $ma ] ) ) { continue; }
				$wpdb->update( VHG_DB::t( 'bc_dong' ),
					array( 'chi_so_sau' => null, 'actual' => 0, 'tien_mat' => 0, 'qr' => 0, 'dieu_chinh' => 0, 'tong' => 0,
						'ghi_chu' => 'bỏ khỏi báo cáo lúc gửi lại ' . current_time( 'Y-m-d' ) ),
					array( 'report_id' => $rid, 'ma_may' => $ma ) );
				$bo[] = $ma;
			}
		}

		/* Đóng yêu cầu kế toán khớp cơ sở + ngày (việc phụ, không làm hỏng việc ghi). */
		$dong_yc = self::dong_yeucau_( $coso, $ngay, (string) $ai['name'] . ' · ' . $rid );

		$so = count( $rows );
		return array( 'ok' => true, 'reportId' => $rid, 'rows' => $so, 'updated' => (bool) $prev,
			'boGhe' => $bo, 'dongYeuCau' => $dong_yc,
			'message' => ( $prev
				? ( 'Đã CẬP NHẬT báo cáo ' . $coso . ' ngày ' . $ngay . '.' )
				: ( 'Đã gửi báo cáo ' . $coso . ' ngày ' . $ngay . '.' ) )
				. ( count( $bo ) ? ( ' Đã bỏ ' . count( $bo ) . ' ghế khỏi báo cáo.' ) : '' )
				. ( $dong_yc ? ( ' Hoàn thành ' . $dong_yc . ' yêu cầu kế toán.' ) : '' )
				. ( $kt_locked ? ' (Giữ nguyên số kế toán đã đối soát.)' : '' ) );
	}

	/** Đọc khối payment từ client -> giá trị lưu header. */
	private static function doc_payment_( $pm, $rows ) {
		$method = in_array( isset( $pm['method'] ) ? $pm['method'] : 'cash', array( 'cash', 'transfer', 'unpaid' ), true )
			? $pm['method'] : 'cash';
		$tong_cash = 0;
		foreach ( $rows as $r ) { $tong_cash += (int) $r['tien_mat']; }
		$trang_thai = 'unpaid'; $so_tien = 0;
		if ( 'cash' === $method || 'transfer' === $method ) {
			$trang_thai = ( 'cash' === $method ) ? 'paid_cash' : 'paid_transfer';
			$raw = ( ! isset( $pm['amount'] ) || '' === $pm['amount'] || null === $pm['amount'] ) ? null : (int) $pm['amount'];
			$so_tien = ( null === $raw ) ? $tong_cash : $raw;
		}
		return array(
			'hinhthuc' => $method, 'trang_thai' => $trang_thai, 'so_tien' => $so_tien,
			'ngay' => ( 'unpaid' === $trang_thai ) ? null : self::ngay_( isset( $pm['date'] ) ? $pm['date'] : current_time( 'Y-m-d' ) ),
			'ghichu' => mb_substr( trim( (string) ( isset( $pm['note'] ) ? $pm['note'] : '' ) ), 0, 250 ),
			'unpaid_lydo' => ( 'unpaid' === $trang_thai ) ? mb_substr( trim( (string) ( isset( $pm['unpaidReason'] ) ? $pm['unpaidReason'] : '' ) ), 0, 250 ) : '',
			'ck_ref' => mb_substr( trim( (string) ( isset( $pm['ref'] ) ? $pm['ref'] : '' ) ), 0, 120 ),
			'ck_bank' => mb_substr( trim( (string) ( isset( $pm['bank'] ) ? $pm['bank'] : '' ) ), 0, 60 ),
		);
	}

	// ══════════════════════════════════════════════════════════════════ ẢNH -> thư viện WP

	/** Lưu 1 ảnh dataURL vào uploads, trả URL ('' nếu lỗi). */
	private static function luu_anh_( $img, $rid, $stt ) {
		$data = (string) ( isset( $img['dataUrl'] ) ? $img['dataUrl'] : '' );
		if ( strpos( $data, ',' ) === false ) { return ''; }
		list( , $b64 ) = explode( ',', $data, 2 );
		$bin = base64_decode( $b64 );
		if ( false === $bin || '' === $bin ) { return ''; }
		$ten = sanitize_file_name( $rid . '-' . $stt . '-' . ( isset( $img['name'] ) ? $img['name'] : 'anh.jpg' ) );
		if ( ! preg_match( '/\.(jpe?g|png|gif|webp)$/i', $ten ) ) { $ten .= '.jpg'; }
		$up = wp_upload_bits( $ten, null, $bin );
		if ( ! empty( $up['error'] ) ) { return ''; }
		return (string) $up['url'];
	}

	/** Lưu các nhóm chứng từ (qr/cash/transfer) -> mảng URL. */
	private static function luu_nhieu_anh_( $proofs, $rid, $tien_to ) {
		$out = array();
		if ( ! is_array( $proofs ) ) { return $out; }
		$i = 0;
		foreach ( array( 'qr', 'cash', 'transfer' ) as $nhom ) {
			if ( empty( $proofs[ $nhom ] ) || ! is_array( $proofs[ $nhom ] ) ) { continue; }
			foreach ( $proofs[ $nhom ] as $img ) {
				$u = self::luu_anh_( $img, $rid, $tien_to . '-' . $nhom . '-' . ( ++$i ) );
				if ( '' !== $u ) { $out[] = $u; }
			}
		}
		return $out;
	}

	/** Chia ảnh ghế theo tỉ lệ round(số ảnh/số ghế), dư dồn ghế cuối. Trả [ i => [urls] ]. */
	private static function chia_anh_ghe_( $images, $so_ghe, $rid ) {
		$imgs = is_array( $images ) ? array_values( $images ) : array();
		$n = count( $imgs );
		$out = array();
		if ( $n <= 0 || $so_ghe <= 0 ) { return $out; }
		$per = max( 1, (int) round( $n / $so_ghe ) );
		$k = 0;
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
		if ( ! $t ) { return false; }
		return ( current_time( 'timestamp' ) - $t ) < self::GIO_SUA * 3600;
	}

	/** Báo cáo trong 24h của user — có thể sửa. */
	public static function ds_24h( $ai ) {
		global $wpdb;
		$coso_toi = self::coso_cua( $ai );
		$hs = $wpdb->get_results( 'SELECT * FROM ' . VHG_DB::t( 'bc' ) . ' ORDER BY tao_luc DESC LIMIT 200', ARRAY_A );
		$ra = array();
		foreach ( (array) $hs as $h ) {
			if ( ! self::trong_pham_vi( $coso_toi, $h['coso'] ) ) { continue; }
			if ( ! self::con_han_( $h['tao_luc'] ) ) { continue; }
			$dong = $wpdb->get_results( $wpdb->prepare(
				'SELECT * FROM ' . VHG_DB::t( 'bc_dong' ) . ' WHERE report_id=%s AND chi_so_sau IS NOT NULL ORDER BY id ASC',
				$h['report_id'] ), ARRAY_A );
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

	/** NV sửa 1 ghế trong 24h — server tính lại. patch: {meterAfter,qr,adjust,note}. */
	public static function sua_dong( $rid, $ma, $patch, $ai ) {
		global $wpdb;
		$rid = (string) $rid; $ma = (string) $ma;
		if ( '' === $rid || '' === $ma ) { return array( 'ok' => false, 'message' => 'Thiếu mã báo cáo hoặc ghế.' ); }
		$h = self::header_theo_id_( $rid );
		if ( ! $h ) { return array( 'ok' => false, 'message' => 'Không thấy báo cáo.' ); }
		if ( ! self::trong_pham_vi( self::coso_cua( $ai ), $h['coso'] ) ) {
			return array( 'ok' => false, 'message' => 'Báo cáo này không thuộc cơ sở của bạn.' );
		}
		if ( ! self::con_han_( $h['tao_luc'] ) ) {
			return array( 'ok' => false, 'message' => 'Báo cáo đã quá ' . self::GIO_SUA . ' giờ nên khoá, không sửa được. Nhờ kế toán.' );
		}
		if ( self::dang_khoa( $h['coso'], $h['ngay'] ) ) {
			return array( 'ok' => false, 'message' => 'Ngày này đang KHOÁ — nhờ kế toán mở lại.' );
		}
		$d = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . VHG_DB::t( 'bc_dong' ) . ' WHERE report_id=%s AND ma_may=%s LIMIT 1', $rid, $ma ), ARRAY_A );
		if ( ! $d ) { return array( 'ok' => false, 'message' => 'Không thấy dòng cần sửa.' ); }

		$patch = is_array( $patch ) ? $patch : array();
		$r = array( 'ma_may' => $ma, 'ngay' => $h['ngay'],
			'chi_so_sau' => array_key_exists( 'meterAfter', $patch ) ? $patch['meterAfter'] : $d['chi_so_sau'],
			'qr' => array_key_exists( 'qr', $patch ) ? (int) $patch['qr'] : (int) $d['qr'],
			'dieu_chinh' => array_key_exists( 'adjust', $patch ) ? (int) $patch['adjust'] : (int) $d['dieu_chinh'],
			'ghi_chu' => array_key_exists( 'note', $patch ) ? mb_substr( trim( (string) $patch['note'] ), 0, 250 ) : $d['ghi_chu'],
		);
		/* ÉP chỉ số trước theo hệ thống (không cho NV sửa) rồi tính lại. */
		$truoc = self::chi_so_truoc( $ma, $h['ngay'] );
		$r['chi_so_truoc'] = ( null !== $truoc ) ? $truoc : self::songuyen_( $d['chi_so_truoc'] );
		self::tinh_( $r );
		if ( null !== $r['chi_so_truoc'] && null !== $r['chi_so_sau'] && $r['chi_so_sau'] < $r['chi_so_truoc'] ) {
			return array( 'ok' => false, 'message' => 'Chỉ số sau nhỏ hơn chỉ số trước — gửi đề nghị đổi chỉ số nếu vừa thay máy.' );
		}
		$wpdb->update( VHG_DB::t( 'bc_dong' ), array(
			'chi_so_truoc' => $r['chi_so_truoc'], 'chi_so_sau' => $r['chi_so_sau'],
			'actual' => $r['actual'], 'tien_mat' => $r['tien_mat'], 'qr' => $r['qr'],
			'dieu_chinh' => $r['dieu_chinh'], 'tong' => $r['tong'], 'ghi_chu' => $r['ghi_chu'],
		), array( 'id' => (int) $d['id'] ) );
		$wpdb->update( VHG_DB::t( 'bc' ), array( 'sua_luc' => current_time( 'mysql' ) ), array( 'report_id' => $rid ) );
		$dong_yc = self::dong_yeucau_( $h['coso'], $h['ngay'], (string) $ai['name'] . ' · sửa 24h · ' . $rid );
		return array( 'ok' => true, 'dongYeuCau' => $dong_yc );
	}

	/** Lịch sử tháng ('yyyy-MM') — từng ghế. */
	public static function lich_su( $thang, $ai ) {
		global $wpdb;
		$coso_toi = self::coso_cua( $ai );
		$thang = preg_match( '/^\d{4}-\d{2}$/', (string) $thang ) ? (string) $thang : current_time( 'Y-m' );
		$dong = $wpdb->get_results( $wpdb->prepare(
			'SELECT d.*, h.coso FROM ' . VHG_DB::t( 'bc_dong' ) . ' d'
			. ' JOIN ' . VHG_DB::t( 'bc' ) . ' h ON h.report_id=d.report_id'
			. ' WHERE DATE_FORMAT(d.ngay,%s)=%s AND d.chi_so_sau IS NOT NULL ORDER BY d.ngay DESC',
			'%Y-%m', $thang ), ARRAY_A );
		$ra = array();
		foreach ( (array) $dong as $d ) {
			if ( ! self::trong_pham_vi( $coso_toi, $d['coso'] ) ) { continue; }
			$ra[] = array( 'date' => self::ngay_( $d['ngay'] ), 'locName' => $d['coso'],
				'chairCode' => $d['ma_may'], 'cash' => (int) $d['tien_mat'], 'qr' => (int) $d['qr'],
				'total' => (int) $d['tong'] );
		}
		return $ra;
	}

	/** Báo cáo còn nợ tiền mặt (need − paid > 0) trong phạm vi user. */
	public static function chua_nop( $ai ) {
		global $wpdb;
		$coso_toi = self::coso_cua( $ai );
		$hs = $wpdb->get_results( 'SELECT * FROM ' . VHG_DB::t( 'bc' ) . ' ORDER BY ngay DESC LIMIT 500', ARRAY_A );
		$ra = array();
		foreach ( (array) $hs as $h ) {
			if ( ! self::trong_pham_vi( $coso_toi, $h['coso'] ) ) { continue; }
			$sum = self::tong_bc_( $h['report_id'] );
			$con = $sum['tien_mat'] - (int) $h['nop_so_tien'];
			if ( $con <= 0 ) { continue; }
			$ra[] = array( 'reportId' => $h['report_id'], 'date' => self::ngay_( $h['ngay'] ),
				'locName' => $h['coso'], 'staff' => $h['nhan_vien'], 'need' => $sum['tien_mat'],
				'paid' => (int) $h['nop_so_tien'] );
		}
		return $ra;
	}

	public static function nop_bosung( $rid, $ngay, $so_tien, $hinhthuc, $ai ) {
		global $wpdb;
		$rid = (string) $rid;
		$h = self::header_theo_id_( $rid );
		if ( ! $h ) { return array( 'ok' => false, 'message' => 'Không thấy báo cáo.' ); }
		if ( ! self::trong_pham_vi( self::coso_cua( $ai ), $h['coso'] ) ) {
			return array( 'ok' => false, 'message' => 'Báo cáo này không thuộc cơ sở của bạn.' );
		}
		if ( self::dang_khoa( $h['coso'], $h['ngay'] ) ) {
			return array( 'ok' => false, 'message' => 'Ngày ' . self::ngay_( $h['ngay'] ) . ' đang KHOÁ — nhờ kế toán mở lại.' );
		}
		$sum = self::tong_bc_( $rid );
		$con = max( 0, $sum['tien_mat'] - (int) $h['nop_so_tien'] );
		$raw = ( '' === $so_tien || null === $so_tien ) ? null : (int) $so_tien;
		$add = ( null === $raw ) ? $con : $raw;
		$moi = (int) $h['nop_so_tien'] + $add;
		$wpdb->update( VHG_DB::t( 'bc' ), array(
			'nop_so_tien' => $moi,
			'nop_ngay'    => self::ngay_( $ngay ? $ngay : current_time( 'Y-m-d' ) ),
			'nop_trang_thai' => ( 'transfer' === $hinhthuc ) ? 'paid_transfer' : 'paid_cash',
			'nop_ghichu'  => trim( (string) $h['nop_ghichu'] . ' | bổ sung ' . current_time( 'Y-m-d' ) ),
		), array( 'report_id' => $rid ) );
		return array( 'ok' => true, 'add' => $add, 'conThieu' => $con );
	}

	// ══════════════════════════════════════════════════════════════════ ĐỀ NGHỊ CHỈ SỐ

	public static function denghi_gui( $p, $ai ) {
		global $wpdb;
		$p = is_array( $p ) ? $p : array();
		$code = trim( (string) ( isset( $p['chairCode'] ) ? $p['chairCode'] : '' ) );
		$from = self::ngay_( isset( $p['fromDate'] ) ? $p['fromDate'] : '' );
		$loai = ( 'xoa' === ( isset( $p['loai'] ) ? $p['loai'] : '' ) ) ? 'xoa' : 'dat_lai';
		$lydo = trim( (string) ( isset( $p['lyDo'] ) ? $p['lyDo'] : '' ) );
		if ( '' === $code ) { return array( 'ok' => false, 'message' => 'Thiếu mã ghế.' ); }
		if ( '' === $from ) { return array( 'ok' => false, 'message' => 'Ngày áp dụng không đúng.' ); }
		if ( '' === $lydo ) { return array( 'ok' => false, 'message' => 'Phải ghi lý do đề nghị.' ); }
		$so = null;
		if ( 'dat_lai' === $loai ) {
			$raw = trim( (string) ( isset( $p['meterOpening'] ) ? $p['meterOpening'] : '' ) );
			if ( '' === $raw ) { return array( 'ok' => false, 'message' => 'Phải nhập chỉ số đề nghị.' ); }
			$so = (int) preg_replace( '/[^\d-]/', '', $raw );
		}
		$m = null;
		foreach ( VHG_May::ds_may() as $x ) { if ( (string) $x['ma'] === $code ) { $m = $x; break; } }
		if ( ! $m ) { return array( 'ok' => false, 'message' => 'Không thấy ghế ' . $code . '.' ); }
		if ( ! self::trong_pham_vi( self::coso_cua( $ai ), (string) $m['coso_ten'] ) ) {
			return array( 'ok' => false, 'message' => 'Ghế này không thuộc cơ sở của bạn.' );
		}
		$trung = (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . VHG_DB::t( 'bc_denghi' )
			. ' WHERE ma_may=%s AND tu_ngay=%s AND trang_thai=%s', $code, $from, 'cho_duyet' ) );
		if ( $trung ) { return array( 'ok' => false, 'message' => 'Ghế này đã có đề nghị cùng ngày đang chờ duyệt.' ); }
		$id = 'DN-' . current_time( 'YmdHis' ) . '-' . $code;
		$wpdb->insert( VHG_DB::t( 'bc_denghi' ), array(
			'id' => $id, 'tao_luc' => current_time( 'mysql' ), 'nhan_vien' => (string) $ai['name'],
			'coso' => (string) $m['coso_ten'], 'ma_may' => $code, 'ten' => (string) ( '' !== (string) $m['ten_khai'] ? $m['ten_khai'] : $code ),
			'tu_ngay' => $from, 'chi_so' => $so, 'loai' => $loai, 'ly_do' => mb_substr( $lydo, 0, 250 ),
			'trang_thai' => 'cho_duyet',
		) );
		return array( 'ok' => true, 'id' => $id,
			'message' => 'Đã gửi đề nghị, chờ kế toán duyệt. Chỉ số hiện tại giữ nguyên tới khi được duyệt.' );
	}

	public static function denghi_ds( $coso, $ai ) {
		global $wpdb;
		$coso_toi = self::coso_cua( $ai );
		$rows = $wpdb->get_results(
			'SELECT * FROM ' . VHG_DB::t( 'bc_denghi' ) . ' ORDER BY id DESC LIMIT 50', ARRAY_A );
		$ra = array();
		foreach ( (array) $rows as $d ) {
			if ( '' !== trim( (string) $coso ) && self::squash( $coso ) !== self::squash( $d['coso'] ) ) { continue; }
			if ( ! self::trong_pham_vi( $coso_toi, $d['coso'] ) ) { continue; }
			$ra[] = array( 'chairCode' => $d['ma_may'], 'chairName' => $d['ten'], 'loai' => $d['loai'],
				'meterOpening' => self::songuyen_( $d['chi_so'] ), 'fromDate' => self::ngay_( $d['tu_ngay'] ),
				'trangThai' => $d['trang_thai'], 'lyDo' => $d['ly_do'], 'duyetBoi' => $d['duyet_boi'],
				'ghiChuKeToan' => $d['ghi_chu_kt'] );
		}
		return $ra;
	}

	// ══════════════════════════════════════════════════════════════════ YÊU CẦU KẾ TOÁN

	public static function yeucau_ds( $ai ) {
		global $wpdb;
		$coso_toi = self::coso_cua( $ai );
		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . VHG_DB::t( 'bc_yeucau' ) . ' WHERE trang_thai=%s ORDER BY ngay DESC LIMIT 100',
			'cho_lam' ), ARRAY_A );
		$ra = array();
		foreach ( (array) $rows as $y ) {
			if ( '' === trim( (string) $y['coso'] ) ) { continue; }
			if ( ! self::trong_pham_vi( $coso_toi, $y['coso'] ) ) { continue; }
			$ra[] = array( 'id' => $y['id'], 'coSo' => $y['coso'], 'ngay' => self::ngay_( $y['ngay'] ),
				'loai' => $y['loai'], 'loaiChu' => ( 'sua' === $y['loai'] ? 'Sửa báo cáo' : 'Làm bổ sung' ),
				'noiDung' => $y['noi_dung'], 'taoLuc' => (string) $y['tao_luc'] );
		}
		return array( 'ok' => true, 'rows' => $ra );
	}

	/** Đóng yêu cầu khớp cơ sở + ngày sau khi gửi/sửa. Trả số yêu cầu đã đóng. */
	private static function dong_yeucau_( $coso, $ngay, $boi ) {
		global $wpdb;
		$n = $wpdb->query( $wpdb->prepare(
			'UPDATE ' . VHG_DB::t( 'bc_yeucau' ) . " SET trang_thai='da_lam', xong_luc=%s, xong_boi=%s"
			. ' WHERE trang_thai=%s AND coso_key=%s AND ngay=%s',
			current_time( 'mysql' ), (string) $boi, 'cho_lam', self::squash( $coso ), self::ngay_( $ngay ) ) );
		return (int) $n;
	}
}
