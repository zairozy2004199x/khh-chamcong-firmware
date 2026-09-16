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

	/** Độ rộng 27 cột A..AA, lấy đúng theo file kế toán đang dùng. */
	const RONG_COT = array( 7.1, 36.6, 15.9, 20.7, 16.4, 17.0, 14.0, 18.6, 18.7, 18.0, 19.6,
		13.9, 17.7, 12.9, 18.0, 15.3, 15.9, 16.1, 14.0, 14.0, 15.9, 13.3, 9.0, 14.7, 14.6,
		18.1, 28.7 );

	/** Dòng đầu tiên chứa dữ liệu người (1-indexed) — ngay dưới hai dòng tiêu đề 7 và 8. */
	const DONG_DAU = 9;

	/**
	 * Dựng tệp .xlsx đúng dạng file kế toán đang dùng.
	 *
	 * =========================================================================================
	 * 🔴 NHỮNG CỘT HỆ KHÔNG BIẾT THÌ ĐỂ TRỐNG — NHƯNG CÓ CÔNG THỨC SẴN
	 * =========================================================================================
	 * Phụ cấp, giảm trừ, BHXH, giờ thêm: hệ không có dữ liệu (xem chú thích đầu lớp). Nhưng để
	 * trống suông thì kế toán gõ vào xong vẫn phải tự cộng tay — tức là xuất ra để đó. Nên mỗi
	 * dòng mang sẵn `U=SUM(N:T)`, `Y=SUM(V:X)`, `M=I+K−L`, `Z=M+U−Y`: gõ một con số vào giữa là
	 * TỔNG tự nhảy, đúng như cái file họ đang làm tay.
	 *
	 * 🔴 DÒNG CHƯA KHAI ĐƠN GIÁ THÌ KHÔNG CÓ CÔNG THỨC NÀO HẾT.
	 *    Để công thức `M=I+K−L` chạy trên một dòng có I trống thì M ra **0** — và số 0 ấy trông
	 *    y như một người tháng này không có lương, chứ không phải "chưa ai khai đơn giá cho việc
	 *    này". Ô trống thì người đọc dừng lại hỏi; số 0 thì người đọc tin. Nên dòng thiếu giá để
	 *    trống trọn, và cột ghi chú nói thẳng ra.
	 *
	 * ⚠️ Số CĂN CƯỚC phải là CHỮ. `079000000000` (số minh hoạ) để Excel tự đoán là mất số 0 đầu, thành
	 *    79000000000 — và đó là số sẽ đi vào hồ sơ bảo hiểm.
	 *    (Số trên là số BỊA. Repo này CÔNG KHAI — CLAUDE.md §4 — nên chú thích không dán số
	 *    căn cước thật của ai, kể cả để làm ví dụ.)
	 */
	public static function to_xlsx( $coso, $thang, $ten_cs = '' ) {
		$b = self::dung( $coso, $thang );
		if ( empty( $b['ok'] ) ) { return $b; }

		$chu  = function ( $v, $s ) { return array( 'v' => VHCC_Xuat::chu( $v ), 's' => $s ); };
		$o    = function ( $v, $s ) { return VHCC_Xuat::o_kieu( $v, $s ); };
		$ct   = function ( $f, $s ) { return VHCC_Xuat::ct( $f, $s ); };
		$trong = function ( $s ) { return VHCC_Xuat::o_kieu( null, $s ); };

		$T = VHCC_Xuat::TIEN;
		$G = VHCC_Xuat::GIO;
		$V = VHCC_Xuat::CHU_V;
		$B = VHCC_Xuat::BANG;

		$cuoi_thang = gmdate( 'd/m/Y', strtotime( $b['thang'] . '-01 12:00:00 UTC +1 month -1 day' ) );
		$ten_cs = '' !== trim( (string) $ten_cs ) ? trim( (string) $ten_cs ) : $b['coso'];

		$rong = function ( $n ) { return array_fill( 0, $n, '' ); };
		$hang = array();
		$hang[] = array_merge( array( 'K&H CO. LTD' ), $rong( 26 ) );
		$hang[] = array_merge( array( 'ACCOUNTING' ), $rong( 26 ) );
		$hang[] = $rong( 27 );
		$hang[] = array_merge( array( $o( 'BẢNG TÍNH - THANH TOÁN TIỀN LƯƠNG', VHCC_Xuat::TUA ) ), $rong( 26 ) );
		$hang[] = array_merge( array( $o( $cuoi_thang, VHCC_Xuat::TUA ) ), $rong( 26 ) );
		$hang[] = array_merge( array( $o( $ten_cs, VHCC_Xuat::DAM ) ), $rong( 26 ) );

		/* Hai dòng tiêu đề — chữ lấy nguyên văn từ file, kể cả mấy chỗ viết tắt. */
		$h1 = array( 'STT', 'NAME', 'CCCD', 'POSITION', 'Lương cb', '', 'Số công thực', 'Tiền/h',
			'Lương chính', 'Số giờ thêm', "Lương\n giờ thêm+ lương làm lễ", 'BHXH', 'Tổng lương',
			'Các khoản cộng vào lương', '', '', '', '', '', '', '',
			'Các khoản giảm trừ vào lương', '', '', '', 'TOTAL SALARY', 'NOTES' );
		$h2 = array( '', '', '', '', '', 'Số công YC', '', '', '', '', '', '', '',
			'Setup', '%KID - Trách nhiệm', 'Target ', 'Lương Thiếu ', 'HT giữ xe, HT đi lại',
			'Trả TN', 'Hoàn cọc', 'Tổng', 'Phạt', '', 'Đặt cọc', 'Cộng', '', '' );
		foreach ( array( $h1, $h2 ) as $h ) {
			$d = array();
			foreach ( $h as $x ) { $d[] = $o( $x, $B ); }
			$hang[] = $d;
		}

		$r = self::DONG_DAU;
		foreach ( $b['dong'] as $x ) {
			$co_gia = ( null !== $x['luongChinh'] );
			$ghi = array();
			if ( 'khong' === $x['giaTu'] && 'thang' !== $x['cheDo'] ) {
				$ghi[] = 'CHƯA KHAI ĐƠN GIÁ GIỜ cho "' . $x['cv'] . '" — chưa ra được tiền';
			}
			if ( 'thang' === $x['cheDo'] && null === $x['congYc'] ) {
				$ghi[] = 'CHƯA KHAI SỐ CÔNG CHUẨN CỦA THÁNG — chưa ra được tiền';
			}
			if ( $x['thieuGio'] > 0 ) {
				$ghi[] = $x['thieuGio'] . ' ngày thiếu giờ vào hoặc giờ ra, KHÔNG tính vào số giờ';
			}

			$dong = array();
			$dong[] = $o( $x['stt'], VHCC_Xuat::STT );
			$dong[] = $o( $x['ten'], $V );
			$dong[] = $chu( $x['cccd'], $V );
			$dong[] = $o( $x['cv'], $V );
			$dong[] = ( null === $x['luongCb'] ) ? $trong( $T ) : $o( (float) $x['luongCb'], $T );
			$dong[] = ( null === $x['congYc'] ) ? $trong( $G ) : $o( (float) $x['congYc'], $G );
			/* 🔴 CỘT G MANG HAI THỨ, ĐÚNG NHƯ FILE GỐC: người ăn theo giờ thì đây là SỐ GIỜ,
			   người ăn lương tháng thì đây là SỐ NGÀY CÔNG. Tách thành hai cột là lệch mẫu, mà
			   kế toán thì đối chiếu bằng mắt theo đúng vị trí cột. */
			$dong[] = ( 'thang' === $x['cheDo'] )
				? $o( (float) $x['congThuc'], $G )
				: $o( (float) $x['gio'], $G );
			$dong[] = ( null === $x['gia'] ) ? $trong( $T ) : $o( (float) $x['gia'], $T );
			if ( ! $co_gia ) {
				/* Dòng chưa ra được tiền: KHÔNG một công thức nào — xem chú thích trên. */
				$dong[] = $trong( $T );                                   // I
				for ( $i = 9; $i <= 25; $i++ ) { $dong[] = $trong( $T ); }
			} else {
				$dong[] = ( 'thang' === $x['cheDo'] )
					? $ct( 'E' . $r . '*G' . $r . '/F' . $r, $T )
					: $ct( 'G' . $r . '*H' . $r, $T );                    // I
				$dong[] = $trong( $G );                                   // J giờ thêm
				$dong[] = $trong( $T );                                   // K lương giờ thêm
				$dong[] = $trong( $T );                                   // L BHXH
				$dong[] = $ct( 'I' . $r . '+K' . $r . '-L' . $r, $T );    // M tổng lương
				for ( $i = 0; $i < 7; $i++ ) { $dong[] = $trong( $T ); }  // N..T
				$dong[] = $ct( 'SUM(N' . $r . ':T' . $r . ')', $T );      // U
				$dong[] = $trong( $T );                                   // V phạt
				$dong[] = $trong( $T );                                   // W
				$dong[] = $trong( $T );                                   // X đặt cọc
				$dong[] = $ct( 'SUM(V' . $r . ':X' . $r . ')', $T );      // Y
				$dong[] = $ct( 'M' . $r . '+U' . $r . '-Y' . $r, $T );    // Z
			}
			$dong[] = $o( implode( ' · ', $ghi ), $V );                   // AA
			$hang[] = $dong;
			$r++;
		}

		/* Dòng tổng — cộng thẳng bằng SUM để kế toán sửa một ô là tổng theo ngay. */
		$cuoi = $r - 1;
		$tong = array( $trong( VHCC_Xuat::TONG ),
			$o( 'TỔNG — ' . $ten_cs, VHCC_Xuat::TONG ),
			$trong( VHCC_Xuat::TONG ), $trong( VHCC_Xuat::TONG ), $trong( VHCC_Xuat::TONG ),
			$trong( VHCC_Xuat::TONG ), $trong( VHCC_Xuat::TONG ), $trong( VHCC_Xuat::TONG ) );
		if ( $cuoi >= self::DONG_DAU ) {
			foreach ( array( 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U',
				'V', 'W', 'X', 'Y', 'Z' ) as $c ) {
				$tong[] = $ct( 'SUM(' . $c . self::DONG_DAU . ':' . $c . $cuoi . ')', VHCC_Xuat::TONG );
			}
		} else {
			for ( $i = 0; $i < 18; $i++ ) { $tong[] = $trong( VHCC_Xuat::TONG ); }
		}
		$tong[] = $trong( VHCC_Xuat::TONG );
		$hang[] = $tong;

		$gop = array( 'A4:AA4', 'A5:AA5', 'A6:AA6', 'A7:A8', 'B7:B8', 'C7:C8', 'D7:D8', 'E7:E8',
			'G7:G8', 'H7:H8', 'I7:I8', 'J7:J8', 'K7:K8', 'L7:L8', 'M7:M8', 'N7:U7', 'V7:X7',
			'Z7:Z8', 'AA7:AA8' );

		return array( 'ok' => true, 'bang' => $b, 'to' => array( array(
			'ten' => 'Lương ' . $b['thang'],
			'cot' => self::RONG_COT,
			'gop' => $gop,
			'damDongDau' => false,
			'hang' => $hang,
		) ) );
	}

	/** Tên tệp gửi về trình duyệt. */
	public static function ten_tep( $coso, $thang ) {
		$cs = preg_replace( '/[^A-Za-z0-9_-]+/', '-', (string) $coso );
		return 'LUONG-' . trim( $cs, '-' ) . '-' . str_replace( '-', '.', (string) $thang ) . '.xlsx';
	}
}
