<?php
/**
 * BẢNG LƯƠNG CƠ SỞ — dựng đúng dạng file kế toán đang dùng.
 *
 * =============================================================================================
 * NGUỒN GỐC
 * =============================================================================================
 * Anh Thắng 15/09/2026 gửi `LƯƠNG CƠ SỞ - T08.2026 - Tháng.xlsx` (20 cơ sở, 531 dòng):
 * *"mỗi cơ sở sẽ xuất bảng công giờ ra theo mẫu file như này. Cửa hàng trưởng sẽ xuất ra để nộp
 * kế toán"*.
 *
 * Bảng này KHÔNG phải một cách tính mới. Nó là cách kế toán đang tính, đọc ngược ra từ chính
 * file ấy rồi viết lại thành mã — để cái file làm tay mỗi tháng nay tự ra từ giờ đã chấm.
 *
 * =============================================================================================
 * 🔴 HAI LỐI TÍNH NẰM CHUNG MỘT BẢNG — ĐỌC RA TỪ FILE, KHÔNG PHẢI EM NGHĨ RA
 * =============================================================================================
 *   · **Theo giờ** (gần hết mọi người): `Lương chính = Số giờ × Tiền/h`.
 *     Đối chiếu: Trần Thị Thúy Vy 186,3 × 24.000 = 4.471.200 ✓ · Bùi Xuân Thuận 211,05 × 25.000
 *     = 5.276.250 ✓ · Nguyễn Hoàng Quân 235,43 × 23.000 = 5.414.890 ✓.
 *   · **Theo tháng** (người có lương cơ bản): `Lương chính = Lương cb × Số công thực / Số công YC`.
 *     Đối chiếu: Trần Ngọc Minh Truyền 4.000.000 × 26/26 = 4.000.000 ✓.
 *
 * Phân lối bằng ĐÚNG MỘT điều: hồ sơ có `luong_co_ban > 0` thì theo tháng, không thì theo giờ.
 * Không suy từ chức vụ — trong file có Cửa hàng trưởng ăn theo giờ (Nguyễn Ngọc Kim Ngân, 58h ×
 * 26.000) lẫn Cửa hàng trưởng ăn theo tháng (Trương Thanh Lâm, 4.000.000). Suy theo chức vụ là
 * sai tiền của đúng những người ấy.
 *
 * Và các cột còn lại, cũng đọc từ file:
 *     Tổng lương (M)  = Lương chính (I) + Lương giờ thêm (K) − BHXH (L)
 *     TOTAL SALARY(Z) = Tổng lương (M) + Tổng cộng (U) − Tổng trừ (Y)
 * Đối chiếu: Kim Ngân 1.508.000 + 0 − 500.000 = 1.008.000 ✓ · Mai Thị Yến Nhi 3.574.800 +
 * 2.000.000 − 0 = 5.574.800 ✓ · Vũ Thị Thanh Thảo 5.092.205,12 + 0 − 150.000 = 4.942.205,12 ✓.
 *
 * =============================================================================================
 * 🔴 HỆ CHỈ CHỊU TRÁCH NHIỆM PHẦN NÓ THẬT SỰ BIẾT
 * =============================================================================================
 * Anh Thắng chốt 15/09/2026: mấy cột phụ cấp và giảm trừ (Setup · %KID · Target · Lương thiếu ·
 * HT giữ xe · Trả TN · Hoàn cọc · Phạt · Đặt cọc) và BHXH **để trống cho kế toán điền**.
 *
 * Đó là quyết định đúng, và đây là lý do kỹ thuật của nó: hệ thống không có lấy một mẩu dữ liệu
 * nào về mấy khoản ấy. Điền bừa số 0 vào rồi gọi là "bảng lương" thì người đọc tin nó đủ — mà
 * một cái cọc 500.000 không trừ là trả dư đúng ngần ấy. Ô TRỐNG nói thật; số 0 thì không.
 *
 * Nên bảng này chỉ dựng: **giờ · đơn giá · lương chính** — và để công thức sẵn cho những cột
 * kia, kế toán gõ vào là tổng tự nhảy.
 *
 * ⚠️ `Số giờ thêm` (J) cũng để trống, cố ý. Trong file nó chỉ có ở người ăn lương tháng, và luật
 *    "giờ nào là giờ thêm" chưa ai khai cho cơ sở. Tự cắt lấy phần vượt một mốc nào đó là em
 *    bịa ra một luật tính tiền — thứ tuyệt đối không được làm im lặng.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_BangLuong {

	/** Tên ca của từng hậu tố — cùng bộ chữ với hàng sửa ở màn Bảng công. */
	const TEN_HAU_TO = array( 'TT' => 'Thu tiền', 'TG' => 'Trực ghế', 'CD' => 'Ca đêm / tăng ca',
		'CT' => 'Công tối', 'TC' => 'Tăng cường' );

	/**
	 * Chức vụ của MỘT DÒNG — tức đúng cái chữ sẽ nằm ở cột POSITION.
	 *
	 * 🔴 MỘT NGƯỜI CÓ THỂ RA NHIỀU DÒNG, MỖI DÒNG MỘT GIÁ. Trong file của anh Thắng, Lâm Tú Lanh
	 *    có hai dòng: Lái Tàu 19,2h × 23.000 và Lơ Tàu 49,15h × 21.000. Gộp lại thành một dòng
	 *    là phải chọn lấy một giá cho cả hai — và chọn cách nào cũng sai tiền.
	 *    Hệ thống vốn đã tách sẵn bằng HẬU TỐ (`ma-TT`, `ma-TG`…), nên chỗ này chỉ việc đặt tên
	 *    cho đúng từng dòng.
	 */
	public static function chuc_vu_dong( $hs, $hau_to ) {
		$hau_to = strtoupper( trim( (string) $hau_to ) );
		if ( '' === $hau_to ) {
			$cv = trim( (string) ( isset( $hs['chuc_vu'] ) ? $hs['chuc_vu'] : '' ) );
			if ( '' !== $cv ) { return $cv; }
			$nv = trim( (string) ( isset( $hs['nhiem_vu'] ) ? $hs['nhiem_vu'] : '' ) );
			if ( '' !== $nv ) { $p = explode( ',', $nv ); return trim( $p[0] ); }
			return '';
		}
		if ( isset( self::TEN_HAU_TO[ $hau_to ] ) ) { return self::TEN_HAU_TO[ $hau_to ]; }
		return $hau_to;
	}

	/** Hồ sơ của cả cơ sở, đánh theo mã chữ thường — một lượt đọc, không hỏi từng người. */
	private static function ho_so_cua( $coso ) {
		global $wpdb;
		$dk = VHCC_NhanSu::dk_sql_coso( $coso );
		$o  = array();
		foreach ( VHCC_DB::rows( $wpdb->prepare(
			'SELECT ma_nv, ho_ten, cccd, chuc_vu, nhiem_vu, luong_co_ban, cua_hang, coso_phu FROM '
			. VHCC_DB::t( 'nhan_vien' ) . ' WHERE ' . $dk['sql'], $dk['tv'] ) ) as $r ) {
			if ( ! VHCC_NhanSu::hs_thuoc_coso( $r, $coso ) ) { continue; }
			$o[ strtolower( trim( (string) $r['ma_nv'] ) ) ] = $r;
		}
		return $o;
	}

	/**
	 * Dựng bảng lương của một cơ sở trong một tháng.
	 *
	 * @return array ok · coso · thang · dong[] · tong[] · thieu[]
	 */
	public static function dung( $coso, $thang ) {
		$coso = trim( preg_replace( '/^CS_/i', '', (string) $coso ) );
		if ( '' === $coso ) { return array( 'ok' => false, 'error' => 'Thiếu cơ sở.' ); }
		$tt = VHCC_Luong::tien_to_thang( $thang );
		if ( '' === $tt ) { return array( 'ok' => false, 'error' => 'Tháng không hợp lệ.' ); }

		$hs_ds = self::ho_so_cua( $coso );
		$so_gia = VHCC_GiaGio::so();

		/* Gom giờ theo (mã, hậu tố) — đúng cách `bang_cong_tho()` gom, để hai bảng không lệch. */
		$gom = array();
		foreach ( VHCC_Luong::doc_thang( $coso, $tt ) as $r ) {
			$ma = trim( (string) $r['ma_nv'] );
			if ( '' === $ma ) { continue; }
			$ht  = strtoupper( trim( (string) $r['hau_to'] ) );
			$key = strtolower( $ma ) . '|' . $ht;
			if ( ! isset( $gom[ $key ] ) ) {
				$gom[ $key ] = array( 'ma' => $ma, 'hauTo' => $ht, 'phut' => 0.0, 'ngay' => array(),
					'ten' => trim( (string) $r['ho_ten'] ) );
			}
			$v = $r['gio_vao_giay'];
			$x = $r['gio_ra_giay'];
			/* 🔴 THIẾU MỘT ĐẦU GIỜ THÌ KHÔNG CỘNG PHÚT NÀO, và đếm riêng để nói ra.
			   Quên chấm giờ ra thì không cách nào biết ca dài bao lâu. Coi như 0 là trừ tiền một
			   người vì cái máy lỗi; đoán một ca chuẩn là trả tiền cho giờ không ai làm. Nên:
			   không tính, và BÁO. */
			if ( null === $v || '' === $v || null === $x || '' === $x ) {
				$gom[ $key ]['thieuGio'] = isset( $gom[ $key ]['thieuGio'] ) ? $gom[ $key ]['thieuGio'] + 1 : 1;
				$gom[ $key ]['ngay'][ $r['ngay'] ] = true;
				continue;
			}
			$gom[ $key ]['phut'] += VHCC_Luong::phut_ca( intdiv( (int) $v, 60 ), intdiv( (int) $x, 60 ) );
			$gom[ $key ]['ngay'][ $r['ngay'] ] = true;
		}

		/* Số công chuẩn của tháng — cho lối TÍNH THEO THÁNG. Chưa khai thì KHÔNG đoán. */
		$cfg = VHCC_Luong::vp_cfg( $coso );
		$cong_yc = ( isset( $cfg['ngayCongThang'] ) && (float) $cfg['ngayCongThang'] > 0 )
			? (float) $cfg['ngayCongThang'] : 0.0;

		$dong = array();
		foreach ( $gom as $g ) {
			$kma = strtolower( $g['ma'] );
			$hs  = isset( $hs_ds[ $kma ] ) ? $hs_ds[ $kma ] : array();
			$cv  = self::chuc_vu_dong( $hs, $g['hauTo'] );
			$gio = round( $g['phut'] / 60, 2 );
			$lcb = (float) ( isset( $hs['luong_co_ban'] ) ? $hs['luong_co_ban'] : 0 );
			/* ⚠️ LỐI THEO THÁNG CHỈ ÁP CHO DÒNG CA CHÍNH. Người ăn lương tháng mà có thêm dòng
			   hậu tố (ca đêm, trực ghế) thì dòng ấy là việc NGOÀI lương tháng — tính theo giờ.
			   Nhân lương tháng lên hai lần là trả gấp đôi cho một người. */
			$theo_thang = ( $lcb > 0 && '' === $g['hauTo'] );

			$gia = array( 'gia' => 0.0, 'tu' => 'khong' );
			if ( ! $theo_thang ) {
				$gia = VHCC_GiaGio::tra( $coso, $cv, $g['ma'], $so_gia );
			}
			$cong_thuc = count( $g['ngay'] );
			$luong_chinh = null;
			if ( $theo_thang ) {
				if ( $cong_yc > 0 ) { $luong_chinh = round( $lcb * $cong_thuc / $cong_yc, 2 ); }
			} elseif ( $gia['gia'] > 0 ) {
				$luong_chinh = round( $gio * $gia['gia'], 2 );
			}

			$dong[] = array(
				'ma'        => $g['ma'],
				'hauTo'     => $g['hauTo'],
				'ten'       => ( '' !== trim( (string) ( isset( $hs['ho_ten'] ) ? $hs['ho_ten'] : '' ) ) )
					? trim( (string) $hs['ho_ten'] ) : $g['ten'],
				'cccd'      => trim( (string) ( isset( $hs['cccd'] ) ? $hs['cccd'] : '' ) ),
				'cv'        => $cv,
				'cheDo'     => $theo_thang ? 'thang' : 'gio',
				'luongCb'   => $theo_thang ? $lcb : null,
				'congYc'    => $theo_thang ? ( $cong_yc > 0 ? $cong_yc : null ) : null,
				'congThuc'  => $theo_thang ? $cong_thuc : null,
				'gio'       => $theo_thang ? null : $gio,
				'gia'       => $theo_thang ? null : ( $gia['gia'] > 0 ? $gia['gia'] : null ),
				'giaTu'     => $gia['tu'],
				'luongChinh' => $luong_chinh,
				'soNgay'    => $cong_thuc,
				'thieuGio'  => isset( $g['thieuGio'] ) ? (int) $g['thieuGio'] : 0,
			);
		}

		/* Xếp theo TÊN rồi tới hậu tố, để hai dòng của cùng một người đứng liền nhau — đúng như
		   file của kế toán, và để mắt soi được ngay. */
		usort( $dong, function ( $a, $b ) {
			$c = strcmp( $a['ten'], $b['ten'] );
			if ( 0 !== $c ) { return $c; }
			return strcmp( $a['hauTo'], $b['hauTo'] );
		} );
		$i = 0;
		foreach ( $dong as &$d ) { $d['stt'] = ++$i; }
		unset( $d );

		$thieu_gia = 0;
		$thieu_gio = 0;
		$tong_chinh = 0.0;
		$tong_gio   = 0.0;
		foreach ( $dong as $d ) {
			if ( null === $d['luongChinh'] ) { $thieu_gia++; }
			else { $tong_chinh += $d['luongChinh']; }
			if ( null !== $d['gio'] ) { $tong_gio += $d['gio']; }
			$thieu_gio += $d['thieuGio'];
		}

		return array(
			'ok'    => true,
			'coso'  => $coso,
			'thang' => $tt,
			'dong'  => $dong,
			'tong'  => array( 'nguoi' => count( $dong ), 'gio' => round( $tong_gio, 2 ),
				'luongChinh' => $tong_chinh ),
			'thieu' => array(
				'gia'       => $thieu_gia,
				'congChuan' => ( $cong_yc <= 0 ),
				'gio'       => $thieu_gio,
			),
		);
	}
}
