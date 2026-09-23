<?php
/**
 * DỌN CA ĐÊM LẺ — ghép hai lượt bấm đã ghi sai thành một hàng ca đêm.
 *
 * =================================================================================================
 * Anh Thắng 23/09/2026: *"hệ thống nó không hiểu công setup"* — và khi anh mở ô để sửa tay thì
 * bị chối *"sửa ở hàng ca đêm (mã kèm -CD)"*, trong khi ngày ấy không có hàng nào như thế.
 * =================================================================================================
 *
 * Trước bản 4.77.0, lượt bấm vào cơ sở PHỤ (`SETUP_VP`) không được định tuyến ca đêm, nên một ca
 * setup 19:51 → 04:02 hôm sau nằm trong sổ thành HAI HÀNG THƯỜNG, mỗi hàng một đầu giờ:
 *
 *     SETUP_VP  06/09  NBT  vào 19:51  ra —
 *     SETUP_VP  07/09  NBT  vào 04:02  ra —
 *
 * Bản vá chỉ sửa được lượt bấm TỪ NAY; mấy hàng đã ghi thì không tự lành, và cũng không sửa tay
 * được qua form (form đúng khi chối giờ ra sớm hơn giờ vào trên hàng thường). Lượt dọn này làm
 * đúng việc mà lẽ ra máy đã làm lúc bấm: ghép cặp ấy thành
 *
 *     SETUP_VP  06/09  NBT-CD  vào 19:51  ra 04:02(+24h)
 *
 * 🔴 LUẬT GHÉP CHẶT, CHỐI THÌ KỂ RA, KHÔNG ĐOÁN:
 *   · hàng 1 phải là hàng THƯỜNG, có vào, KHÔNG có ra, giờ vào từ `ngayDen` trở đi (17:00) —
 *     một lượt vào 08:00 mà không ra là ca ngày quên bấm, không phải ca đêm;
 *   · hàng 2 phải là hàng THƯỜNG của ĐÚNG NGÀY HÔM SAU, cùng người, cùng cơ sở, có vào, không có
 *     ra, giờ vào tới hết `ngayDen` — sau đó là mở ca mới, không phải đóng ca cũ;
 *   · ngày hôm trước CHƯA có hàng ca đêm nào — có rồi thì để người xử tay, không nhét đè.
 *   Hàng nào chỉ có một nửa (không tìm được nửa kia) thì kể ra để đi bù, KHÔNG động vào.
 *
 * 🔴 CHỈ CHẠY Ở CƠ SỞ MÀ LUẬT LÀ VĂN PHÒNG (chính hoặc phụ đã ghép). Cửa hàng tính theo giờ
 *    không có khái niệm hàng ca đêm; chạy ở đó là bịa ra một loại hàng cơ sở ấy không có.
 *
 * ⚠️ XEM TRƯỚC LÀ MẶC ĐỊNH. Lượt dọn xoá hàng và ghi hàng — người bấm phải thấy danh sách cặp
 *    TRƯỚC, đúng như mọi cửa nạp dữ liệu khác của hệ.
 * ⚠️ MỌI CON SỐ ĐỔI CHỖ ĐỀU CÓ NHẬT KÝ (`VHCC_Bu::nhat_ky_don`): vào của hàng cũ, ra lấy từ lượt
 *    nào, lượt nào bị gỡ. Ba tháng sau phải trả lời được "sao ngày 07 mất một lượt bấm".
 * ⚠️ ẢNH VÀ VỊ TRÍ ĐI THEO GIỜ. Ảnh lúc bấm vào của hàng 1 thành ảnh vào; ảnh lúc bấm của hàng 2
 *    thành ảnh ra. Đó là bằng chứng của lượt bấm, mất là mất thật.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_DonDem {

	/**
	 * TÌM các cặp ghép được và các lượt lẻ — hàm ĐỌC, không ghi gì.
	 *
	 * @return array cap[] (ma, ten, ngay, vao, ngaySau, ra, id1, id2, r1, r2) · le[] (ma, ten, ngay, vao, lyDo)
	 */
	public static function tim( $coso, $thang ) {
		global $wpdb;
		$coso = VHCC_NhanSu::chuan_coso( $coso );
		$cfg  = VHCC_Online::vp_cfg( $coso );
		$ngay_den = VHCC_DB::giay( $cfg['ngayDen'] );
		if ( null === $ngay_den ) { $ngay_den = 17 * 3600; }

		/* Đọc tới NGÀY 1 THÁNG SAU: ca đêm cuối tháng có nửa kia nằm ở đầu tháng kế. */
		$tu  = $thang . '-01';
		$den = VHCC_Luong::ngay_sau( $thang . '-31' );
		$bang = VHCC_DB::t( 'cham_cong' );
		$rows = VHCC_DB::rows( $wpdb->prepare(
			"SELECT id, ngay, ma_nv, ho_ten, hau_to, gio_vao_giay, gio_ra_giay, nguon,
			        anh_vao, vt_vao FROM $bang
			 WHERE coso=%s AND ngay >= %s AND ngay <= %s ORDER BY ma_nv, ngay",
			$coso, $tu, $den ) );

		$thuong = array();   // [ma][ngay] => hàng thường chỉ có VÀO
		$co_dem = array();   // [ma][ngay] => true nếu đã có hàng -CD
		$ten    = array();
		foreach ( (array) $rows as $r ) {
			$ma = trim( (string) $r['ma_nv'] );
			$ht = strtoupper( trim( (string) $r['hau_to'] ) );
			if ( '' === $ma ) { continue; }
			if ( ! isset( $ten[ $ma ] ) ) { $ten[ $ma ] = (string) $r['ho_ten']; }
			if ( 'CD' === $ht ) { $co_dem[ $ma ][ $r['ngay'] ] = true; continue; }
			if ( '' !== $ht ) { continue; }
			$co_vao = ( null !== $r['gio_vao_giay'] && '' !== $r['gio_vao_giay'] );
			$co_ra  = ( null !== $r['gio_ra_giay'] && '' !== $r['gio_ra_giay'] );
			if ( $co_vao && ! $co_ra ) { $thuong[ $ma ][ $r['ngay'] ] = $r; }
		}

		$cap = array();
		$le  = array();
		$da_dung = array();   // id đã vào một cặp — hàng 2 của cặp này không được làm hàng 1 của cặp khác
		foreach ( $thuong as $ma => $theo_ngay ) {
			ksort( $theo_ngay );
			foreach ( $theo_ngay as $ngay => $r1 ) {
				if ( isset( $da_dung[ $r1['id'] ] ) ) { continue; }
				/* Chỉ xét ngày TRONG tháng làm hàng 1 — ngày 1 tháng sau chỉ được làm hàng 2. */
				if ( 0 !== strpos( $ngay, $thang . '-' ) ) { continue; }
				$vao1 = (int) $r1['gio_vao_giay'];
				$mot = array( 'ma' => $ma, 'ten' => $ten[ $ma ], 'ngay' => $ngay,
					'vao' => VHCC_DB::hhmm( $vao1 ) );
				if ( $vao1 % VHCC_DB::NGAY_GIAY < $ngay_den ) {
					$mot['lyDo'] = 'giờ vào trước ' . VHCC_DB::hhmm( $ngay_den ) . ' — ca ngày quên bấm ra, không phải ca đêm';
					$le[] = $mot;
					continue;
				}
				if ( ! empty( $co_dem[ $ma ][ $ngay ] ) ) {
					$mot['lyDo'] = 'ngày này ĐÃ có hàng ca đêm — xử tay, không nhét đè';
					$le[] = $mot;
					continue;
				}
				$sau = VHCC_Luong::ngay_sau( $ngay );
				$r2  = isset( $theo_ngay[ $sau ] ) ? $theo_ngay[ $sau ] : null;
				if ( ! $r2 || isset( $da_dung[ $r2['id'] ] ) ) {
					$mot['lyDo'] = 'chưa thấy lượt ra ở ngày hôm sau — bù tay nếu người ấy có về bấm';
					$le[] = $mot;
					continue;
				}
				$vao2 = (int) $r2['gio_vao_giay'];
				if ( $vao2 % VHCC_DB::NGAY_GIAY > $ngay_den ) {
					$mot['lyDo'] = 'lượt hôm sau lúc ' . VHCC_DB::hhmm( $vao2 ) . ' là MỞ ca mới, không phải giờ ra';
					$le[] = $mot;
					continue;
				}
				$cap[] = array( 'ma' => $ma, 'ten' => $ten[ $ma ], 'ngay' => $ngay,
					'vao' => VHCC_DB::hhmm( $vao1 ), 'ngaySau' => $sau, 'ra' => VHCC_DB::hhmm( $vao2 ),
					'id1' => (int) $r1['id'], 'id2' => (int) $r2['id'], 'r1' => $r1, 'r2' => $r2 );
				$da_dung[ $r1['id'] ] = true;
				$da_dung[ $r2['id'] ] = true;
			}
		}
		return array( 'cap' => $cap, 'le' => $le, 'ngayDen' => VHCC_DB::hhmm( $ngay_den ) );
	}

	/**
	 * XEM TRƯỚC hoặc CHẠY THẬT.
	 *
	 * @param bool $thuc false = chỉ kể, không ghi (mặc định).
	 */
	public static function chay( $u, $coso, $thang, $thuc = false ) {
		global $wpdb;
		$coso  = VHCC_NhanSu::chuan_coso( $coso );
		$thang = trim( (string) $thang );
		if ( ! VHCC_Vai::duoc( $u, 'sua_gio' ) ) {
			return array( 'ok' => false, 'error' => VHCC_Vai::loi( $u, 'sua_gio', 'Dọn ca đêm lẻ' ) );
		}
		if ( '' === $coso ) { return array( 'ok' => false, 'error' => 'Chưa chọn cơ sở.' ); }
		if ( ! VHCC_NhanSu::co_quyen_coso( $u, $coso ) ) {
			return array( 'ok' => false, 'error' => 'Không có quyền cơ sở này.' );
		}
		if ( ! preg_match( '/^\d{4}-\d{2}$/', $thang ) ) {
			return array( 'ok' => false, 'error' => 'Tháng không đúng dạng (yyyy-mm).' );
		}
		if ( ! VHCC_Online::la_van_phong( $coso ) ) {
			return array( 'ok' => false, 'error' => 'Cơ sở ' . $coso . ' không tính theo luật Văn phòng '
				. '(không có hàng ca đêm) — không có gì để dọn.' );
		}

		$t = self::tim( $coso, $thang );
		$da_ghi = 0;
		$ky_ghi = 0;
		$loi    = array();
		if ( $thuc ) {
			$bang = VHCC_DB::t( 'cham_cong' );
			foreach ( $t['cap'] as $c ) {
				$r1 = $c['r1']; $r2 = $c['r2'];
				$vao  = (int) $r1['gio_vao_giay'];
				$ra   = (int) $r2['gio_vao_giay'] + VHCC_DB::NGAY_GIAY;   // trục phẳng: đứng SAU giờ vào
				$ma_cd = $c['ma'] . '-' . VHCC_Online::DUOI_CD;
				$nguon = trim( (string) $r1['nguon'] );
				if ( '' === $nguon ) { $nguon = 'online'; }
				$ly_do = 'Dọn ca đêm lẻ: ghép lượt ' . $c['ngaySau'] . ' ' . $c['ra']
					. ' (hàng thường) làm giờ ra của ca đêm ' . $c['ngay'];

				/* Dựng hàng -CD qua đúng cổng ghi chung: vào rồi ra. Hàng -CD ngày ấy chưa có
				   (đã lọc ở `tim()`), nên hai lượt nới từ hàng trống ra đúng cặp. */
				$k1 = VHCC_Nhan::ghi_gio( $coso, $c['ngay'], $ma_cd, $c['ten'], $vao, '', $nguon );
				$k2 = VHCC_Nhan::ghi_gio( $coso, $c['ngay'], $ma_cd, $c['ten'], $ra,  '', $nguon );
				if ( isset( $k1['loi'] ) || isset( $k2['loi'] ) ) {
					$loi[] = $c['ten'] . ' ' . $c['ngay'] . ': không dựng được hàng ca đêm — '
						. ( isset( $k1['loi'] ) ? $k1['loi'] : $k2['loi'] );
					continue;
				}
				/* Ảnh & vị trí đi theo giờ: bằng chứng của lượt bấm, mất là mất thật. */
				$moi = VHCC_Online::hang( $coso, $c['ngay'], $c['ma'], VHCC_Online::DUOI_CD );
				if ( $moi ) {
					$them = array();
					if ( '' !== (string) $r1['anh_vao'] ) { $them['anh_vao'] = (string) $r1['anh_vao']; }
					if ( '' !== (string) $r1['vt_vao'] )  { $them['vt_vao']  = (string) $r1['vt_vao']; }
					if ( '' !== (string) $r2['anh_vao'] ) { $them['anh_ra']  = (string) $r2['anh_vao']; }
					if ( '' !== (string) $r2['vt_vao'] )  { $them['vt_ra']   = (string) $r2['vt_vao']; }
					if ( $them ) { $wpdb->update( $bang, $them, array( 'id' => (int) $moi['id'] ) ); }
				}
				/* Gỡ hai hàng thường. Xoá theo ID đã đọc ở bước tìm — không xoá theo điều kiện
				   rộng, kẻo giữa hai bước có lượt mới rơi vào. */
				$wpdb->delete( $bang, array( 'id' => (int) $c['id1'] ) );
				$wpdb->delete( $bang, array( 'id' => (int) $c['id2'] ) );

				if ( class_exists( 'VHCC_Bu' ) && method_exists( 'VHCC_Bu', 'nhat_ky_don' ) ) {
					VHCC_Bu::nhat_ky_don( $u, $coso, $c['ngay'],    $ma_cd,   'vao', $vao, $vao, $ly_do ); $ky_ghi++;
					VHCC_Bu::nhat_ky_don( $u, $coso, $c['ngay'],    $ma_cd,   'ra',  $ra,  null, $ly_do ); $ky_ghi++;
					VHCC_Bu::nhat_ky_don( $u, $coso, $c['ngaySau'], $c['ma'], 'vao', null, (int) $r2['gio_vao_giay'],
						'Dọn ca đêm lẻ: lượt này là giờ RA của ca đêm ' . $c['ngay'] . ', đã chuyển sang hàng ' . $ma_cd ); $ky_ghi++;
				}
				$da_ghi++;
			}
		}
		/* Không trả nguyên hàng thô ra màn — chỉ phần người đọc cần. */
		$cap_goi = array();
		foreach ( $t['cap'] as $c ) { unset( $c['r1'], $c['r2'] ); $cap_goi[] = $c; }
		return array( 'ok' => true, 'chi_xem' => ! $thuc, 'coSo' => $coso, 'thang' => $thang,
			'ngayDen' => $t['ngayDen'], 'cap' => $cap_goi, 'le' => $t['le'],
			'da_ghi' => $da_ghi, 'ky_ghi' => $ky_ghi, 'loi' => $loi );
	}
}
