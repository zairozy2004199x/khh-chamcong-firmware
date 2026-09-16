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
 *     Đối chiếu ba dòng của file: 186,3 × 24.000 = 4.471.200 ✓ · 211,05 × 25.000 = 5.276.250 ✓
 *     · 235,43 × 23.000 = 5.414.890 ✓.
 *   · **Theo tháng** (người có lương cơ bản): `Lương chính = Lương cb × Số công thực / Số công YC`.
 *     Đối chiếu: 4.000.000 × 26/26 = 4.000.000 ✓.
 *
 * Phân lối bằng ĐÚNG MỘT điều: hồ sơ có `luong_co_ban > 0` thì theo tháng, không thì theo giờ.
 * Không suy từ chức vụ — trong file có Cửa hàng trưởng ăn theo giờ (58h × 26.000) lẫn Cửa hàng
 * trưởng ăn theo tháng (4.000.000/tháng). Suy theo chức vụ là sai tiền của đúng những người ấy.
 *
 * Và các cột còn lại, cũng đọc từ file:
 *     Tổng lương (M)  = Lương chính (I) + Lương giờ thêm (K) − BHXH (L)
 *     TOTAL SALARY(Z) = Tổng lương (M) + Tổng cộng (U) − Tổng trừ (Y)
 * Đối chiếu ba dòng: 1.508.000 + 0 − 500.000 = 1.008.000 ✓ · 3.574.800 + 2.000.000 − 0 =
 * 5.574.800 ✓ · 5.092.205,12 + 0 − 150.000 = 4.942.205,12 ✓.
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


	/**
	 * Chức vụ của DÒNG CHÍNH — tức chữ ở cột POSITION cho phần giờ ăn giá chính.
	 *
	 * 🔴 KHÔNG CÒN ĐOÁN TÊN TỪ HẬU TỐ CA. Bản trước có một bảng cố định (`TT` => 'Thu tiền'…) và
	 *    nó sai ngay lượt đối chiếu đầu tiên với file thật: mảng Khu vui chơi gọi `TT` là **Lơ
	 *    Tàu**, nên mười dòng tra đơn giá bằng một cái tên không ai khai và ra 0đ. Anh Thắng
	 *    16/09/2026: *"nhiều mảng sẽ có khác nữa, nên nếu thiết kế theo tàu thì lại không đúng"*.
	 *    Tên việc nay do người gõ (`VHCC_ChotLuong`), còn dòng chính lấy đúng chức vụ trong hồ sơ.
	 */
	public static function chuc_vu_chinh( $hs ) {
		$cv = trim( (string) ( isset( $hs['chuc_vu'] ) ? $hs['chuc_vu'] : '' ) );
		if ( '' !== $cv ) { return $cv; }
		$nv = trim( (string) ( isset( $hs['nhiem_vu'] ) ? $hs['nhiem_vu'] : '' ) );
		if ( '' !== $nv ) { $p = explode( ',', $nv ); return trim( $p[0] ); }
		return '';
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

		/* 🔴 GOM VỀ MỘT TỔNG MỖI NGƯỜI — KHÔNG TÁCH THEO HẬU TỐ.
		   Anh Thắng 16/09/2026: *"trên chấm công sẽ chỉ có giờ tổng"*. Máy ghi một con số giờ cho
		   mỗi người; trong đó có mấy giờ dẫn chương trình, mấy giờ hỗ trợ thì máy không biết, và
		   không dữ liệu nào suy ra được. Bản trước của em tách dòng theo hậu tố ca rồi ĐẶT TÊN
		   cho từng dòng bằng một bảng cố định (`TT` => 'Thu tiền'…) — sai hai lần: mảng Khu vui
		   chơi gọi `TT` là **Lơ Tàu**, còn mảng khác lại có MC / Hỗ Trợ / Partime, những việc
		   không gắn với hậu tố nào cả. Đối chiếu file T08 thật thì mười dòng ra 0đ vì tra đơn giá
		   bằng một cái tên không ai khai.
		   Nay: hậu tố chỉ còn là chuyện của lưới chấm công; ở đây mọi lượt của một người cộng vào
		   MỘT tổng, rồi `VHCC_GioKhac` trừ ra phần ăn giá khác. */
		$gom = array();
		foreach ( VHCC_Luong::doc_thang( $coso, $tt ) as $r ) {
			$ma = trim( (string) $r['ma_nv'] );
			if ( '' === $ma ) { continue; }
			$key = strtolower( $ma );
			if ( ! isset( $gom[ $key ] ) ) {
				$gom[ $key ] = array( 'ma' => $ma, 'phut' => 0.0, 'ngay' => array(),
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

		$so_khac = VHCC_ChotLuong::so();
		$dong = array();
		$vuot  = 0;
		foreach ( $gom as $g ) {
			$kma = strtolower( $g['ma'] );
			$hs  = isset( $hs_ds[ $kma ] ) ? $hs_ds[ $kma ] : array();
			$gio_tong = round( $g['phut'] / 60, 2 );
			$lcb = (float) ( isset( $hs['luong_co_ban'] ) ? $hs['luong_co_ban'] : 0 );
			$ten = ( '' !== trim( (string) ( isset( $hs['ho_ten'] ) ? $hs['ho_ten'] : '' ) ) )
				? trim( (string) $hs['ho_ten'] ) : $g['ten'];
			$cccd = trim( (string) ( isset( $hs['cccd'] ) ? $hs['cccd'] : '' ) );
			$cong_thuc = count( $g['ngay'] );

			/* 🔴 GIỜ TỔNG LÀ GIỜ CHÍNH, TRỪ ĐI MẤY DÒNG ĂN GIÁ KHÁC.
			   Luật của anh Thắng 16/09/2026: kế toán chỉ gõ NGOẠI LỆ (MC 2h, Hỗ Trợ 6h…), phần
			   còn lại tự là việc chính. Đối chiếu file T08: 118 + 2 + 6 = 126 giờ chấm công. */
			$khac = VHCC_ChotLuong::cua( $coso, $tt, $g['ma'], $so_khac );
			$gio_khac = 0.0;
			foreach ( $khac as $k ) { $gio_khac += (float) $k['gio']; }
			$gio_khac = round( $gio_khac, 2 );
			$gio_chinh = round( $gio_tong - $gio_khac, 2 );
			/* Chặn ở `VHCC_ChotLuong::dat()` rồi, nhưng giờ chấm công có thể TỤT sau lúc nhập (ai
			   đó xoá một lượt chấm nhầm). Nên vẫn phải đỡ ở đây: không để ra giờ âm, và ĐẾM. */
			if ( $gio_chinh < 0 ) { $gio_chinh = 0.0; $vuot++; }

			$theo_thang = ( $lcb > 0 );
			$mot = function ( $cv, $gio, $la_chinh ) use ( $coso, $g, $so_gia, $ten, $cccd,
				$theo_thang, $lcb, $cong_yc, $cong_thuc, $gio_tong, $gio_khac ) {
				$gia = VHCC_GiaGio::tra( $coso, $cv, $g['ma'], $so_gia );
				$luong = null;
				if ( $la_chinh && $theo_thang ) {
					if ( $cong_yc > 0 ) { $luong = round( $lcb * $cong_thuc / $cong_yc, 2 ); }
				} elseif ( $gia['gia'] > 0 ) {
					$luong = round( $gio * $gia['gia'], 2 );
				}
				return array(
					'ma'    => $g['ma'],
					'ten'   => $ten,
					'cccd'  => $cccd,
					'cv'    => $cv,
					'laChinh' => $la_chinh,
					'cheDo' => ( $la_chinh && $theo_thang ) ? 'thang' : 'gio',
					'luongCb'  => ( $la_chinh && $theo_thang ) ? $lcb : null,
					'congYc'   => ( $la_chinh && $theo_thang ) ? ( $cong_yc > 0 ? $cong_yc : null ) : null,
					'congThuc' => ( $la_chinh && $theo_thang ) ? $cong_thuc : null,
					'gio'   => ( $la_chinh && $theo_thang ) ? null : $gio,
					'gia'   => ( $la_chinh && $theo_thang ) ? null : ( $gia['gia'] > 0 ? $gia['gia'] : null ),
					'giaTu' => $gia['tu'],
					'luongChinh' => $luong,
					'soNgay' => $cong_thuc,
					'gioTong' => $gio_tong,
					'gioKhac' => $gio_khac,
					/* Dòng giờ khác KHÔNG mang khoản tiền nào — xem chú thích ở dòng chính. */
					'cong' => array(), 'tru' => array(), 'tongCong' => 0.0, 'tongTru' => 0.0,
				);
			};

			/* Dòng CHÍNH luôn có, kể cả khi giờ chính bằng 0 — bỏ đi là người đọc không thấy
			   người ấy đâu trong bảng, tưởng sót. */
			/* 🔴 TÊN CỦA DÒNG CHÍNH: LẤY CÁI NGƯỜI TA CHỌN TRƯỚC, HỒ SƠ CHỈ LÀ ĐƯỜNG LÙI.
			   Hồ sơ của anh Thắng ghi chức vụ là **"Khu vui chơi"** — tên MẢNG kinh doanh, không
			   phải chức vụ ăn lương. Không ai khai đơn giá cho tên mảng cả, nên bảy dòng đứng im
			   ở "CHƯA KHAI ĐƠN GIÁ" mà không có lối ra: khai giá cho tên mảng là sai về nghĩa,
			   không khai thì bảng lương rỗng.
			   Từ 16/09/2026 người chốt lương CHỌN việc chính cho từng người
			   (`VHCC_ChotLuong::viec_chinh()`), và phần giờ còn lại ăn theo giá của việc ấy.
			   Chưa chọn thì vẫn lùi về tên hồ sơ — không im lặng bỏ trống dòng của một người. */
			$vc_chon = VHCC_ChotLuong::viec_chinh( $coso, $tt, $g['ma'], $so_khac );
			$d_chinh = $mot( '' !== $vc_chon ? $vc_chon : self::chuc_vu_chinh( $hs ),
				$gio_chinh, true );
			$d_chinh['thieuGio'] = isset( $g['thieuGio'] ) ? (int) $g['thieuGio'] : 0;
			/* 🔴 KHOẢN CỘNG / TRỪ GẮN VÀO DÒNG CHÍNH, KHÔNG RẢI RA MỌI DÒNG.
			   Một người có thể ra ba dòng (chính + MC + Hỗ Trợ) nhưng cái cọc 200.000 chỉ trừ
			   MỘT LẦN. Rải ra mỗi dòng là trừ ba lần — và bảng vẫn có số nên không ai thấy. */
			$kt = VHCC_ChotLuong::tien_cua( $coso, $tt, $g['ma'], $so_khac );
			$tt_tien = VHCC_ChotLuong::tong_tien( $coso, $tt, $g['ma'], $so_khac );
			$d_chinh['cong']     = $kt['cong'];
			$d_chinh['tru']      = $kt['tru'];
			$d_chinh['tongCong'] = $tt_tien['cong'];
			$d_chinh['tongTru']  = $tt_tien['tru'];
			$dong[] = $d_chinh;
			foreach ( $khac as $k ) {
				$d_k = $mot( (string) $k['viec'], round( (float) $k['gio'], 2 ), false );
				$d_k['thieuGio'] = 0;
				$dong[] = $d_k;
			}
		}

		/* Xếp theo TÊN rồi tới hậu tố, để hai dòng của cùng một người đứng liền nhau — đúng như
		   file của kế toán, và để mắt soi được ngay. */
		usort( $dong, function ( $a, $b ) {
			$c = strcmp( $a['ten'], $b['ten'] );
			if ( 0 !== $c ) { return $c; }
			/* Dòng chính đứng trước các dòng giờ khác của cùng người — đúng như file kế toán. */
			if ( $a['laChinh'] !== $b['laChinh'] ) { return $a['laChinh'] ? -1 : 1; }
			return strcmp( $a['cv'], $b['cv'] );
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
	 * ⚠️ Số CĂN CƯỚC phải là CHỮ. `000000000000` (số BỊA để minh hoạ) để Excel tự đoán là
	 *    mất số 0 đầu — và đó là số sẽ đi vào hồ sơ bảo hiểm.
	 *    🔴 KHO NÀY CÔNG KHAI — KHÔNG DÁN SỐ CĂN CƯỚC THẬT CỦA AI, KỂ CẢ LÀM VÍ DỤ.
	 *    Em đã vấp đúng lỗi này 15/09/2026: chép thẳng một số căn cước thật từ file lương của
	 *    anh Thắng vào chú thích. Số minh hoạ phải trông-là-biết-giả (toàn số 0), không phải
	 *    một số "có vẻ thật" — số có vẻ thật là thứ lần sau người ta chép lại mà không hỏi.
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
				/* 🔴 BẢY CỘT CỘNG ĐỔ THẲNG TỪ SỐ CỬA HÀNG TRƯỞNG ĐÃ GÕ — anh Thắng 16/09/2026
				   đổi quyết định hôm trước ("để trống, kế toán điền"): *"mấy cột đó sẽ do cửa
				   hàng trưởng nhập"*. Ô nào chưa gõ vẫn để TRỐNG chứ không ghi 0: một tờ lương
				   đầy số 0 trông như đã xét hết mọi khoản, trong khi chưa ai gõ gì. */
				foreach ( array_keys( VHCC_ChotLuong::CONG ) as $k_c ) {   // N..T
					$dong[] = empty( $x['cong'][ $k_c ] ) ? $trong( $T )
						: $o( (float) $x['cong'][ $k_c ], $T );
				}
				$dong[] = $ct( 'SUM(N' . $r . ':T' . $r . ')', $T );      // U
				$dong[] = empty( $x['tru']['phat'] ) ? $trong( $T )
					: $o( (float) $x['tru']['phat'], $T );                // V phạt
				$dong[] = $trong( $T );                                   // W (cột trống của mẫu)
				$dong[] = empty( $x['tru']['datCoc'] ) ? $trong( $T )
					: $o( (float) $x['tru']['datCoc'], $T );              // X đặt cọc
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
