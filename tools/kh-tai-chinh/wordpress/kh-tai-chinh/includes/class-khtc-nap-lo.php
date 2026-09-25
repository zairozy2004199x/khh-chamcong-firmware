<?php
/**
 * Nạp một lượt nhiều tệp — cả bộ file của một ngày trong một lần chọn.
 *
 * Mỗi sáng kế toán có 12–14 tệp: 6 sao kê QR (mỗi tài khoản một tệp), 2 MoMo,
 * 2 VNPay, 2 Payoo, 1 đơn mini app — của HAI pháp nhân. Nạp từng tệp, mỗi tệp
 * lại phải nhớ đổi pháp nhân, chọn tài khoản, là 13 chỗ để chọn nhầm.
 *
 * Máy quyết được ba thứ nhờ chính nội dung tệp:
 *   - sao kê QR ghi "Tài khoản nhận: 11521268 - MB" → tài khoản đó ở pháp nhân nào
 *   - tệp cổng mang mã cửa hàng → mã nằm trong danh mục bên nào
 *   - đơn mini app là bảng tra, không mang tiền → lưu cho cả hai bên
 * Không quyết được (tài khoản chưa khai, mã chẳng có bên nào) thì BỎ QUA tệp
 * đó và nói rõ, không đoán. Ghi đi qua đúng các hàm nạp thường ngày nên mọi
 * chặn trùng vẫn còn: nạp lại cả bộ hôm qua thì ra 0 dòng mới, không nhân đôi.
 */

defined( 'ABSPATH' ) || exit;

class KHTC_NapLo {

	/**
	 * @param array $ds_tep [ ['ten' => string, 'trang' => [ ['ten','van_ban','so_dong'] ]] ]  (kết quả KHTC_Tep::doc_moi_trang)
	 * @return array [ 'ket_qua' => [ mỗi tệp: ten, dang, cty, dich, them, trung, la, ghi_chu, bo ], 'cty_da_nap' => [cty => [tu, den, nh[], dot[]]] ]
	 */
	public static function nap( $ds_tep ) {
		$cty_goc  = KHTC_Cty::dang_chon();
		$chi      = KHTC_Cty::chi_duoc();   // người nạp chỉ được một bên → tệp bên kia bỏ, không ghi
		$ket_qua  = array();
		$theo_cty = array();
		$ghi = function ( $cty, $rows, $nh = 0, $dot = 0 ) use ( &$theo_cty ) {
			$iso = array();
			foreach ( $rows as $r ) { $n = KHTC_GiaoDich::doc_ngay( $r['ngay'] ); if ( '' !== $n ) { $iso[] = $n; } }
			if ( ! $iso ) { return; }
			$t = &$theo_cty[ $cty ];
			$t['tu']  = isset( $t['tu'] ) ? min( $t['tu'], min( $iso ) ) : min( $iso );
			$t['den'] = isset( $t['den'] ) ? max( $t['den'], max( $iso ) ) : max( $iso );
			if ( $nh ) { $t['nh'][ $nh ] = 1; }
			if ( $dot ) { $t['dot'][ $dot ] = 1; }
		};

		foreach ( $ds_tep as $tep ) {
			$kq = array( 'ten' => (string) ( $tep['ten'] ?? $tep['ten_tep'] ?? '' ), 'dang' => '', 'cty' => '', 'dich' => '', 'them' => 0, 'trung' => 0, 'la' => 0, 'ghi_chu' => '', 'bo' => false );
			if ( ! empty( $tep['loi'] ) ) { $kq['bo'] = true; $kq['ghi_chu'] = (string) $tep['loi']; $ket_qua[] = $kq; continue; }
			// Sheet nào là bảng của cổng / sao kê / đơn
			$ung = array();
			foreach ( $tep['trang'] as $t ) {
				$nd = KHTC_DanTho::nhan_dang( $t['van_ban'] );
				if ( $nd ) { $ung[] = $t['van_ban']; }
			}
			if ( ! $ung ) {
				$kq['bo'] = true; $kq['ghi_chu'] = 'Không sheet nào là bảng của cổng / sao kê / đơn mini app.';
				$ket_qua[] = $kq; continue;
			}
			foreach ( $ung as $vb ) {
				$k = KHTC_DanTho::doc( $vb );
				if ( is_wp_error( $k ) ) { $kq['bo'] = true; $kq['ghi_chu'] = $k->get_error_message(); break; }
				$kq['dang'] = $k['ten'];
				$kq['dich'] = $k['dich'];

				// ---- đơn mini app: bảng tra, lưu cho cả hai pháp nhân
				if ( 'don_app' === $k['dich'] ) {
					$rows = array_map( function ( $x ) { $x['ngay'] = KHTC_GiaoDich::doc_ngay( $x['ngay'] ); return $x; }, $k['rows'] );
					$moi = 0;
					foreach ( array_keys( KHTC_Cty::ds() ) as $c ) { KHTC_Cty::chon( $c, true ); $r = KHTC_DonApp::nap( $rows ); $moi = max( $moi, $r['them'] ); }
					$kq['cty']     = 'cả hai';
					$kq['them']    = $moi;
					$kq['trung']   = count( $rows ) - $moi;
					$kq['ghi_chu'] = sprintf( '%s đơn trong tệp, %s đơn mới, lưu cho cả hai pháp nhân. Bảng tra cơ sở, không mang tiền.', number_format( count( $rows ), 0, ',', '.' ), number_format( $moi, 0, ',', '.' ) );
					continue;
				}
				if ( ! $k['rows'] ) { $kq['bo'] = true; $kq['ghi_chu'] = 'Không đọc được dòng nào (' . $k['thieu_cot'] . ' dòng thiếu cột, ' . $k['bo_loc'] . ' dòng bị lọc).'; continue; }

				// ---- sao kê QR: tài khoản nhận quyết pháp nhân
				if ( 'sao_ke' === $k['dich'] ) {
					$so_tk = self::so_tk_trong( $k['rows'] );
					$nh    = $so_tk ? self::tim_tai_khoan( $so_tk ) : null;
					if ( ! $nh ) {
						$kq['bo'] = true;
						$kq['ghi_chu'] = $so_tk
							? sprintf( 'Tài khoản nhận %s chưa khai ở mục Ngân hàng (bên nào cũng không có). Khai xong nạp lại tệp này.', $so_tk )
							: 'Không thấy cột "Tài khoản nhận" để biết tệp của tài khoản nào.';
						continue;
					}
					if ( '' !== $chi && $chi !== $nh->cty ) { $kq['bo'] = true; $kq['ghi_chu'] = 'Tệp của ' . KHTC_Cty::ten( $nh->cty ) . ' (tài khoản ' . $nh->ten . '), bạn chỉ được vào sổ ' . KHTC_Cty::ten( $chi ) . '.'; continue; }
					KHTC_Cty::chon( $nh->cty, true );
					$r = KHTC_GiaoDich::dan_hang_loat( (int) $nh->id, KHTC_DanTho::ra_sao_ke( $k['rows'] ) );
					$kq['cty']   = $nh->cty;
					$kq['them']  = $r['them'];
					$kq['trung'] = $r['trung'];
					$kq['la']    = self::tien_la( $k['rows'], $nh->cty );
					$kq['ghi_chu'] = 'Vào tài khoản “' . $nh->ten . '”' . ( $r['loi'] ? '; ' . count( $r['loi'] ) . ' dòng lỗi' : '' );
					$ghi( $nh->cty, $k['rows'], (int) $nh->id );
					continue;
				}

				// ---- tệp cổng: mã cửa hàng quyết pháp nhân
				$phan = self::phan_cty_theo_ma( $k['rows'] );
				if ( '' === $phan['cty'] ) {
					$kq['bo'] = true;
					$kq['ghi_chu'] = 'Không mã cửa hàng nào có trong danh mục của bên nào — không biết tệp của pháp nhân nào. Thêm mã vào danh mục (hoặc nạp riêng tệp này ở đúng bên) rồi nạp lại.';
					continue;
				}
				if ( '' !== $chi && $chi !== $phan['cty'] ) { $kq['bo'] = true; $kq['ghi_chu'] = 'Tệp của ' . KHTC_Cty::ten( $phan['cty'] ) . ' (theo mã cửa hàng), bạn chỉ được vào sổ ' . KHTC_Cty::ten( $chi ) . '.'; continue; }
				KHTC_Cty::chon( $phan['cty'], true );
				$nh_dot = self::tai_khoan_mac_dinh( $phan['cty'] );
				if ( ! $nh_dot ) { $kq['bo'] = true; $kq['ghi_chu'] = 'Pháp nhân ' . KHTC_Cty::ten( $phan['cty'] ) . ' chưa có tài khoản ngân hàng nào để gắn đợt.'; continue; }
				$theo_thang = array();
				foreach ( $k['rows'] as $r ) { $theo_thang[ substr( KHTC_GiaoDich::doc_ngay( $r['ngay'] ), 0, 7 ) ][] = $r; }
				ksort( $theo_thang );
				$ten_dot = array();
				foreach ( $theo_thang as $thang => $rows ) {
					$id = KHTC_DoiSoat::dot_thang( $k['dinh_dang'], $thang, $nh_dot );
					if ( is_wp_error( $id ) ) { $kq['ghi_chu'] .= ' ' . $id->get_error_message(); continue; }
					$r = KHTC_DoiSoat::nap_dong( $id, KHTC_DanTho::ra_cong( $rows ) );
					$kq['them']  += $r['them'];
					$kq['trung'] += $r['trung'];
					if ( $r['them'] > 0 ) { KHTC_DoiSoat::mo_rong_ky( $id ); KHTC_DoiSoat::chay( $id ); }
					$d = KHTC_DoiSoat::mot_dot( $id );
					$ten_dot[] = $d ? $d->ten : '#' . $id;
					$ghi( $phan['cty'], $rows, 0, (int) $id );
				}
				$kq['cty'] = $phan['cty'];
				$kq['la']  = $phan['la'];
				$kq['ghi_chu'] = trim( 'Vào đợt “' . implode( '”, “', array_unique( $ten_dot ) ) . '”' . ( $phan['ghi_chu'] ? '. ' . $phan['ghi_chu'] : '' ) . $kq['ghi_chu'] );
			}
			$ket_qua[] = $kq;
		}
		KHTC_Cty::thoi_ep();
		foreach ( $theo_cty as $c => &$t ) { $t['nh'] = array_keys( $t['nh'] ?? array() ); $t['dot'] = array_keys( $t['dot'] ?? array() ); }
		unset( $t );
		KHTC_NhatKy::ghi( 'nap', 'sao_luu', 0, sprintf( 'Nạp một lượt %d tệp: %s', count( $ds_tep ), implode( '; ', array_map( function ( $x ) { return $x['ten'] . ( $x['bo'] ? ' (bỏ)' : ' +' . $x['them'] ); }, $ket_qua ) ) ) );
		return array( 'ket_qua' => $ket_qua, 'cty_da_nap' => $theo_cty );
	}

	/** Số tài khoản nhận ghi trong sao kê QR (cột "Tài khoản nhận", dạng "11521268 - MB"). */
	public static function so_tk_trong( $rows ) {
		foreach ( $rows as $r ) {
			$tk = (string) ( $r['tk'] ?? '' );
			if ( preg_match( '/(\d{6,})/', $tk, $m ) ) { return $m[1]; }
		}
		return '';
	}

	/** Tìm tài khoản theo số ở CẢ HAI pháp nhân — tệp sao kê không biết nhãn, nó biết số. */
	public static function tim_tai_khoan( $so_tk ) {
		global $wpdb;
		$so = ltrim( preg_replace( '/\D/', '', (string) $so_tk ), '0' );
		if ( '' === $so ) { return null; }
		$rows = $wpdb->get_results( 'SELECT * FROM ' . KHTC_DB::bang( 'ngan_hang' ) . " WHERE so_tk <> '' ORDER BY id" );
		foreach ( $rows as $r ) {
			if ( ltrim( preg_replace( '/\D/', '', (string) $r->so_tk ), '0' ) === $so ) { return $r; }
		}
		return null;
	}

	/** Tài khoản đầu tiên của pháp nhân — chỉ để gắn cho đợt cổng mới tạo. */
	private static function tai_khoan_mac_dinh( $cty ) {
		$ds = KHTC_NganHang::ds( $cty );
		return $ds ? (int) $ds[0]->id : 0;
	}

	/**
	 * Tệp cổng thuộc bên nào: cộng tiền theo mã cửa hàng khớp danh mục mỗi bên.
	 * @return array cty ('' nếu không bên nào), la (tiền không bên nào có), ghi_chu
	 */
	public static function phan_cty_theo_ma( $rows ) {
		$tra = array();
		foreach ( array_keys( KHTC_Cty::ds() ) as $c ) { $tra[ $c ] = KHTC_Diem::bang_tra( $c ); }
		$tien = array_fill_keys( array_keys( $tra ), 0 );
		$la = 0;
		foreach ( $rows as $r ) {
			$ma = trim( (string) $r['ma_cua_hang'] ); $t = (int) $r['so_tien']; $co = false;
			foreach ( $tra as $c => $bt ) { if ( '' !== $ma && isset( $bt[ $ma ] ) ) { $tien[ $c ] += $t; $co = true; break; } }
			if ( ! $co ) { $la += $t; }
		}
		arsort( $tien );
		$cty  = key( $tien );
		$nhat = reset( $tien );
		$nhi  = count( $tien ) > 1 ? array_values( $tien )[1] : 0;
		if ( $nhat <= 0 ) { return array( 'cty' => '', 'la' => $la, 'ghi_chu' => '' ); }
		$ghi = '';
		if ( $nhi > 0 ) { $ghi = sprintf( 'Có mã khớp cả hai bên (%s đ khớp bên kia) — xếp về bên khớp nhiều hơn, soát lại.', number_format( $nhi, 0, ',', '.' ) ); }
		return array( 'cty' => $cty, 'la' => $la, 'ghi_chu' => $ghi );
	}

	/** Tiền mang mã không có trong danh mục của pháp nhân đó. */
	private static function tien_la( $rows, $cty ) {
		$bt = KHTC_Diem::bang_tra( $cty ); $la = 0;
		foreach ( $rows as $r ) { $ma = trim( (string) $r['ma_cua_hang'] ); if ( '' === $ma || ! isset( $bt[ $ma ] ) ) { $la += (int) $r['so_tien']; } }
		return $la;
	}
}
