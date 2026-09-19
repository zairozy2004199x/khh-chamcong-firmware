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
		/* ═══════════════════════════════════════════════════════════════════════════════════
		 * LỊCH NGHỈ LỄ — ĐỌC MỘT LẦN CHO CẢ BẢNG.
		 *
		 * Anh Thắng 18/09/2026: *"chỗ set lịch lương lễ và ngày lễ, ngày đó x2 hay x3"*, và anh
		 * chọn hệ số TỰ SET, danh sách CHUNG CẢ CHUỖI. Xem `VHCC_NgayLe`.
		 *
		 * ⚠️ Chưa khai ngày nào (hoặc hệ số vẫn là 1) thì `dang_chay()` trả `false` và cả nhánh
		 *    này đứng im — mọi cơ sở đang chạy không đổi một đồng nào sau khi cài bản này.
		 * ⚠️ Gác `class_exists` cùng hàm với lời gọi — luật của `kiem-goi-cheo.php`.
		 * ═══════════════════════════════════════════════════════════════════════════════════ */
		$le_cfg = null;
		if ( class_exists( 'VHCC_NgayLe' ) && method_exists( 'VHCC_NgayLe', 'cfg' )
			&& method_exists( 'VHCC_NgayLe', 'dang_chay' ) && method_exists( 'VHCC_NgayLe', 'he_so_cua' ) ) {
			$c_le = VHCC_NgayLe::cfg();
			if ( VHCC_NgayLe::dang_chay( $c_le ) ) { $le_cfg = $c_le; }
		}

		/* ═══════════════════════════════════════════════════════════════════════════════════
		 * GIỜ THEO LOẠI DO CHÍNH NHÂN VIÊN KHAI (`VHCC_LoaiGio`) — nếu cơ sở có bật.
		 *
		 * 🔴 NGƯỜI KHAI THẮNG KẾ TOÁN GÕ, VÀ THẮNG TRỌN GÓI CHO TỪNG NGƯỜI.
		 *    Hai nguồn cùng nói về một thứ: mấy dòng `VHCC_ChotLuong` kế toán gõ cuối tháng, và
		 *    mấy lượt chấm nhân viên tự khai lúc kết ca. CỘNG CẢ HAI là đếm hai lần — 2 giờ MC
		 *    thành 4, và bảng vẫn đầy số nên không ai thấy. Nên phải chọn một, và chọn theo
		 *    TỪNG NGƯỜI: người nào đã tự khai thì đọc bản khai của người ấy, người chưa khai
		 *    (nghỉ việc giữa chừng, cơ sở mới bật tính năng) vẫn đọc bản kế toán gõ.
		 *
		 * ⚠️ Ô `nguon` đi kèm từng dòng để màn còn nói ra dòng ấy từ đâu. Không có nó thì kế
		 *    toán sửa một dòng, lưu, và không hiểu vì sao số cũ quay lại.
		 * ═══════════════════════════════════════════════════════════════════════════════════ */
		$tu_khai = VHCC_LoaiGio::gio_theo_viec( $coso, $tt );

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
		/* 🔴 MÃ BỊ ẨN KHÔNG VÀO BẢNG LƯƠNG — nhưng phép lọc KHÔNG còn ở đây nữa.
		   Anh Thắng 16/09/2026: *"khi ẩn thì nó không ảnh hưởng đến bảng công"*, rồi *"nếu ẩn
		   thì ẩn luôn, không hiện tất cả các tháng"*. `VHCC_Luong::doc_thang()` nay lọc sẵn ở
		   cửa vào cho MỌI nơi đọc nó (xem khối chú thích trong hàm ấy), nên lọc lại ở đây là
		   hai luật ẩn nằm trong một plugin — thứ sẽ lệch nhau vào một ngày nào đó. */
		$gom = array();
		foreach ( VHCC_Luong::doc_thang( $coso, $tt ) as $r ) {
			$ma = trim( (string) $r['ma_nv'] );
			if ( '' === $ma ) { continue; }
			$key = strtolower( $ma );
			if ( ! isset( $gom[ $key ] ) ) {
				$gom[ $key ] = array( 'ma' => $ma, 'phut' => 0.0, 'ngay' => array(),
					'phutLe' => array(), 'phutNgay' => array(),
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
			/* ═══════════════════════════════════════════════════════════════════════════════
			 * 🔴 GIỜ RA < GIỜ VÀO LÀ CA VẮT QUA NỬA ĐÊM — CỘNG BÙ, ĐỪNG CHẶN.
			 *
			 * Anh Thắng 16/09/2026, nói thẳng luật: *"qua đêm hệ thống sẽ hiểu: 22h00 - 2h00 là
			 * 4 tiếng, vì 22h ngày 16 và 2h ngày 17, thì coi như nó sẽ tính 22h00-24h00, sau đó
			 * quay lại 00h00 - 2h00"*. `VHCC_Luong::phut_ca()` ngay dưới làm đúng việc ấy.
			 *
			 * ⚠️ BẢN 4.6.0 CHẶN CHỖ NÀY — SAI, ĐÃ GỠ. Em từng tin rằng ca đêm luôn được trải
			 *    phẳng lúc ghi nên `ra < vao` chỉ còn là rác, rồi hỏi anh Thắng *"hàng ra < vào
			 *    mà KHÔNG phải ca đêm thì làm gì"* và nhận về *"tính 0 giờ"*. Anh trả lời đúng
			 *    câu được hỏi; TIỀN ĐỀ của câu hỏi mới là thứ sai: chỉ VĂN PHÒNG chấm qua cổng
			 *    online mới trải phẳng (`VHCC_Online::trai_phang`), còn máy chấm công và nạp
			 *    .csv thì không. Chặn ở đây là xoá trắng 4 giờ của một ca đêm thật.
			 *
			 * ⚠️ Hai engine khác đã làm đúng luật này từ lâu và có phép thử khoá lại:
			 *    `VHCC_Pdf::phut_lam()` và chính `phut_ca()`. Bảng lương lệch với chúng thì
			 *    tờ giấy in ra và màn hình nói hai con số khác nhau cho cùng một ngày.
			 * ═══════════════════════════════════════════════════════════════════════════════ */
			/* ⚠️ CA GÃY — khoảng nghỉ giữa ca đi kèm, `phut_ca()` trừ nó bên trong. Đây là con số
			   NHÂN VỚI ĐƠN GIÁ, nên quên ở đây là trả dư tiền thật cho mấy giờ người ta về nhà. */
			$p_ca = VHCC_Luong::phut_ca( intdiv( (int) $v, 60 ), intdiv( (int) $x, 60 ),
				isset( $r['nghi_tu_giay'] ) ? $r['nghi_tu_giay'] : null,
				isset( $r['nghi_den_giay'] ) ? $r['nghi_den_giay'] : null );
			$gom[ $key ]['phut'] += $p_ca;
			$gom[ $key ]['ngay'][ $r['ngay'] ] = true;
			/* Phút của TỪNG NGÀY — mẫu số lương tháng quy đổi theo bậc giờ, và phải quy đổi
			   trên từng ngày một. Xem `VHCC_QuyCong::cong_cua_thang()`. */
			$nd = (string) $r['ngay'];
			$gom[ $key ]['phutNgay'][ $nd ] = ( isset( $gom[ $key ]['phutNgay'][ $nd ] )
				? $gom[ $key ]['phutNgay'][ $nd ] : 0.0 ) + $p_ca;

			/* 🔴 PHÚT CỦA NGÀY LỄ ĐỂ RIÊNG, GOM THEO HỆ SỐ — và VẪN NẰM TRONG `phut`.
			   Giờ lễ không phải một loại giờ khác: nó vẫn là giờ làm của người ấy, vẫn vào giờ
			   tổng, vẫn trừ ra giờ khác như mọi giờ. Chỗ khác duy nhất là nó được trả THÊM. Tách
			   hẳn ra khỏi `phut` là giờ tổng tụt xuống, và mọi con số đối chiếu với bảng công
			   lệch theo — sai ở chỗ không ai ngờ tới.
			   Gom theo hệ số vì hai ngày lễ có thể khai hai hệ số khác nhau (Tết x3, Quốc khánh
			   x2); cộng chung rồi nhân một lần là trả sai cả hai. */
			if ( null !== $le_cfg && $p_ca > 0 ) {
				$hs_le = VHCC_NgayLe::he_so_cua( (string) $r['ngay'], $le_cfg );
				if ( $hs_le > 1.0 ) {
					$kh = (string) $hs_le;
					$gom[ $key ]['phutLe'][ $kh ] = ( isset( $gom[ $key ]['phutLe'][ $kh ] )
						? $gom[ $key ]['phutLe'][ $kh ] : 0.0 ) + $p_ca;
				}
			}
		}

		/* Số công chuẩn của tháng — cho lối TÍNH THEO THÁNG. Chưa khai thì KHÔNG đoán. */
		$cfg = VHCC_Luong::vp_cfg( $coso );
		$cong_yc = ( isset( $cfg['ngayCongThang'] ) && (float) $cfg['ngayCongThang'] > 0 )
			? (float) $cfg['ngayCongThang'] : 0.0;

		$so_khac = VHCC_ChotLuong::so();
		/* Bảng quy đổi giờ → công, đọc MỘT LẦN cho cả bảng lương. Chỉ người ăn lương tháng đi
		   qua nó; xem khối chú thích ở chỗ tính `$cong_thuc` bên dưới.
		   ⚠️ Gác `class_exists` cùng hàm với lời gọi — luật của `kiem-goi-cheo.php`. */
		$bac_cong = ( class_exists( 'VHCC_QuyCong' ) && method_exists( 'VHCC_QuyCong', 'bac' )
			&& method_exists( 'VHCC_QuyCong', 'cong_cua_thang' ) ) ? VHCC_QuyCong::bac() : null;
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
			$so_ngay = count( $g['ngay'] );

			/* ═══════════════════════════════════════════════════════════════════════════════
			 * 🔴 SỐ CÔNG THỰC CỦA NGƯỜI ĂN LƯƠNG THÁNG: QUY ĐỔI TỪ GIỜ, KHÔNG ĐẾM ĐẦU NGÀY
			 * ═══════════════════════════════════════════════════════════════════════════════
			 * Anh Thắng 19/09/2026: *"nhân viên tính theo công tháng thì khi tích vào đó, nv sẽ
			 * quy đổi theo 4 tiếng 1/2 công và 8h là 1 công (bổ sung bảng set)"*.
			 *
			 * Lối cũ đếm SỐ NGÀY CÓ CHẤM, không nhìn ngày ấy làm mấy giờ: tạt vào hai tiếng rồi
			 * về cũng trọn một công, y như người làm mười hai tiếng. Với người ăn lương tháng
			 * thì đó là sai tiền theo cả hai chiều — và bảng vẫn đầy số nên không ai kêu.
			 *
			 * ⚠️ NGÀY THIẾU MỘT ĐẦU GIỜ NAY RA 0 CÔNG, khác lối cũ (nó đếm trọn một ngày).
			 *    Không có giờ ra thì không biết ca dài bao lâu, mà đây là MẪU SỐ của lương —
			 *    đoán trọn một công là trả tiền cho một ngày chưa ai xác nhận. Mấy ngày ấy đã
			 *    được đếm riêng trong `thieuGio` và màn bảng lương nói ra; sửa lượt chấm hoặc
			 *    duyệt đơn bù là nó vào lại ngay.
			 * ⚠️ Chỉ chạm tới người ăn lương tháng. Người tính theo giờ vẫn lấy giờ nhân đơn
			 *    giá, không đi qua bảng bậc một bước nào.
			 * ═══════════════════════════════════════════════════════════════════════════════ */
			$gio_ngay = array();
			foreach ( ( isset( $g['phutNgay'] ) ? $g['phutNgay'] : array() ) as $n_x => $p_x ) {
				$gio_ngay[ $n_x ] = round( $p_x / 60, 2 );
			}
			$cong_thuc = ( null !== $bac_cong )
				? VHCC_QuyCong::cong_cua_thang( $gio_ngay, $bac_cong )
				: $so_ngay;

			/* 🔴 GIỜ TỔNG LÀ GIỜ CHÍNH, TRỪ ĐI MẤY DÒNG ĂN GIÁ KHÁC.
			   Luật của anh Thắng 16/09/2026: kế toán chỉ gõ NGOẠI LỆ (MC 2h, Hỗ Trợ 6h…), phần
			   còn lại tự là việc chính. Đối chiếu file T08: 118 + 2 + 6 = 126 giờ chấm công. */
			$khai_toi = isset( $tu_khai[ $kma ] ) ? $tu_khai[ $kma ] : array();
			$vc_chon  = VHCC_ChotLuong::viec_chinh( $coso, $tt, $g['ma'], $so_khac );

			if ( $khai_toi ) {
				/* 🔴 CHỈ TỰ CHỌN VIỆC CHÍNH KHI BẢN KHAI PHỦ HẾT GIỜ CÔNG.
				   Chưa ai chọn việc chính mà bản khai phủ trọn số giờ thì lấy việc NHIỀU GIỜ
				   NHẤT — đúng câu *"chọn cái đầu tiên làm giờ chính"* của anh Thắng 16/09/2026,
				   và tránh một dòng chính đứng ở 0 giờ trông như người ấy không đi làm.

				   Nhưng CÒN GIỜ CHƯA KHAI thì tuyệt đối không tự chọn. Người làm 18 giờ mới
				   khai 9 giờ Hỗ Trợ: lấy Hỗ Trợ làm việc chính là 9 giờ CHƯA KHAI cũng ăn giá
				   Hỗ Trợ — một đơn giá không ai khai cho chúng, dựng ra từ một phép đoán. Để
				   trống thì nhánh dưới lùi về chức vụ trong hồ sơ, và 9 giờ kia thành giờ
				   khác đúng như nó được khai. */
				$tong_khai = 0.0;
				foreach ( $khai_toi as $g_k ) { $tong_khai += (float) $g_k; }
				if ( '' === $vc_chon && round( $tong_khai, 2 ) >= round( $gio_tong, 2 ) ) {
					arsort( $khai_toi );
					$vc_chon = (string) key( $khai_toi );
				}
				$kvc  = VHCC_GiaGio::khoa_cv( $vc_chon );
				$khac = array();
				foreach ( $khai_toi as $ten_v => $gio_v ) {
					/* Giờ của chính việc chính KHÔNG phải "giờ khác" — nó là phần còn lại, và
					   `giờ chính = giờ tổng − giờ khác` tự ra đúng. Kể nó vào đây là trừ hai lần. */
					if ( VHCC_GiaGio::khoa_cv( $ten_v ) === $kvc ) { continue; }
					$khac[] = array( 'viec' => (string) $ten_v, 'gio' => (float) $gio_v,
						'nguon' => 'nhanvien' );
				}
			} else {
				$khac = VHCC_ChotLuong::cua( $coso, $tt, $g['ma'], $so_khac );
			}

			$gio_khac = 0.0;
			foreach ( $khac as $k ) { $gio_khac += (float) $k['gio']; }
			$gio_khac = round( $gio_khac, 2 );
			$gio_chinh = round( $gio_tong - $gio_khac, 2 );
			/* Chặn ở `VHCC_ChotLuong::dat()` rồi, nhưng giờ chấm công có thể TỤT sau lúc nhập (ai
			   đó xoá một lượt chấm nhầm). Nên vẫn phải đỡ ở đây: không để ra giờ âm, và ĐẾM. */
			if ( $gio_chinh < 0 ) { $gio_chinh = 0.0; $vuot++; }

			/* ═══════════════════════════════════════════════════════════════════════════════
			 * ĂN LƯƠNG THÁNG — KHAI RIÊNG TỪNG NGƯỜI TỪNG THÁNG THÌ ĐÈ LÊN HỒ SƠ.
			 *
			 * Anh Thắng 16/09/2026: *"có bạn nhận lương tháng không phải theo giờ, nên tách bạn
			 * đó ra, khi tích vào bạn đó, nhập lương và ngày công là ra lương tháng"*.
			 *
			 * Trước bản này chỉ có hai nguồn: `luong_co_ban` của HỒ SƠ (màn khác, quyền khác) và
			 * `Số công YC` khai CHUNG cho cả cơ sở. Nay người chốt lương khai thẳng trên bảng
			 * lương, cho đúng một người, đúng một tháng.
			 *
			 * ⚠️ LỚP ĐÈ, KHÔNG PHẢI LỐI THAY THẾ. Không khai thì lui về hồ sơ + cấu hình cơ sở,
			 *    y như trước — cơ sở nào đang chạy ổn không phải khai lại gì.
			 * ⚠️ Số công chuẩn để trống thì MƯỢN con số chung của cơ sở; khai riêng thì con số
			 *    riêng thắng. Người làm nửa tháng rồi nghỉ có công chuẩn khác cả cơ sở.
			 * ⚠️ BIẾN RIÊNG CHO TỪNG NGƯỜI (`$cong_yc_ng`), KHÔNG GHI ĐÈ `$cong_yc`. Con số chung
			 *    của cơ sở khai MỘT LẦN ở ngoài vòng lặp; đè thẳng lên nó là người khai riêng
			 *    làm đổi luôn công chuẩn của MỌI NGƯỜI đứng sau trong danh sách — sai tiền của
			 *    người không liên quan, và sai theo thứ tự abc nên gần như không lần ra được.
			 *    Em đã viết đúng cái lỗi ấy ở bản nháp. */
			$lt        = VHCC_ChotLuong::thang_cua( $coso, $tt, $g['ma'], $so_khac );
			$cong_yc_ng = $cong_yc;
			if ( $lt ) {
				$lcb = (float) $lt['lcb'];
				if ( null !== $lt['congYc'] ) { $cong_yc_ng = (float) $lt['congYc']; }
			}

			$theo_thang = ( $lcb > 0 );
			$mot = function ( $cv, $gio, $la_chinh ) use ( $coso, $g, $so_gia, $ten, $cccd,
				$theo_thang, $lcb, $cong_yc_ng, $cong_thuc, $so_ngay, $gio_tong, $gio_khac ) {
				$cong_yc = $cong_yc_ng;
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
					/* 🔴 `soNgay` LÀ SỐ NGÀY CÓ CHẤM, KHÔNG PHẢI SỐ CÔNG QUY ĐỔI. Hai con số
					   nay khác nhau (25 ngày có thể ra 23,5 công). Trộn chúng là cột "số ngày
					   làm" trên tờ nộp bỗng hiện số lẻ, và không ai hiểu vì sao. */
					'soNgay' => $so_ngay,
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
			$d_chinh = $mot( '' !== $vc_chon ? $vc_chon : self::chuc_vu_chinh( $hs ),
				$gio_chinh, true );
			$d_chinh['thieuGio'] = isset( $g['thieuGio'] ) ? (int) $g['thieuGio'] : 0;

			/* ═══════════════════════════════════════════════════════════════════════════════
			 * PHỤ TRỘI NGÀY LỄ — CỘNG PHẦN CHÊNH, KHÔNG TÍNH LẠI TỪ ĐẦU
			 * ═══════════════════════════════════════════════════════════════════════════════
			 * Giờ lễ đã được trả một lần rồi, ở trong `gio_chinh × đơn giá` như mọi giờ khác.
			 * Nên ở đây chỉ cộng PHẦN CÒN THIẾU: `giờ lễ × đơn giá × (hệ số − 1)`. Tính lại
			 * trọn gói `giờ lễ × đơn giá × hệ số` là trả hai lần cho cùng mấy giờ ấy.
			 *
			 * ⚠️ ĐI VÀO `luongChinh`, KHÔNG chen vào mảng `cong`. Mảng ấy là mấy ô tiền KẾ TOÁN
			 *    GÕ (`VHCC_ChotLuong::CONG`), mỗi ô ứng một cột cố định của tờ nộp; nhét một
			 *    khoản máy tự tính vào đó là kế toán mở ra thấy một con số mình không gõ, sửa
			 *    đi, lưu, và tháng sau nó quay lại.
			 *
			 * ⚠️ NGƯỜI ĂN LƯƠNG THÁNG THÌ KHÔNG. Lương của họ là `lcb × công thực / công chuẩn`
			 *    — không có đơn giá giờ nào để nhân, và một ngày lễ đã nằm sẵn trong tháng
			 *    lương ấy. Dựng ra một đơn giá giờ cho họ là bịa. Ai muốn trả thêm thì gõ tay
			 *    một khoản cộng, đúng cửa của nó.
			 *
			 * ⚠️ TÍNH THEO ĐƠN GIÁ CỦA DÒNG CHÍNH. Máy chỉ biết người ấy làm mấy giờ ngày nào,
			 *    KHÔNG biết mấy giờ hôm mùng 2 là giờ MC hay giờ Hỗ Trợ — không dữ liệu nào nói
			 *    ra điều đó. Nên phụ trội tính theo giá việc chính, và màn nói rõ như vậy.
			 * ═══════════════════════════════════════════════════════════════════════════════ */
			$d_chinh['gioLe']     = 0.0;
			$d_chinh['phuTroiLe'] = 0.0;
			$d_chinh['leTheo']    = array();
			if ( ! empty( $g['phutLe'] ) && 'gio' === $d_chinh['cheDo']
				&& null !== $d_chinh['gia'] && $d_chinh['gia'] > 0 ) {
				$them = 0.0;
				foreach ( $g['phutLe'] as $hs_k => $p_le ) {
					$h  = (float) $hs_k;
					$gl = round( $p_le / 60, 2 );
					$t  = round( $gl * (float) $d_chinh['gia'] * ( $h - 1.0 ), 2 );
					$d_chinh['gioLe'] += $gl;
					$d_chinh['leTheo'][] = array( 'heSo' => $h, 'gio' => $gl, 'tien' => $t );
					$them += $t;
				}
				$d_chinh['gioLe']     = round( $d_chinh['gioLe'], 2 );
				$d_chinh['phuTroiLe'] = round( $them, 2 );
				if ( null !== $d_chinh['luongChinh'] ) {
					$d_chinh['luongChinh'] = round( (float) $d_chinh['luongChinh'] + $them, 2 );
				}
			}
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
				/* Phụ trội lễ gắn TRỌN VÀO DÒNG CHÍNH — cùng lý do với khoản cộng/trừ: một
				   người ra ba dòng nhưng mấy giờ lễ ấy chỉ được trả thêm một lần. Mấy khoá này
				   vẫn có mặt, để nơi đọc khỏi phải `isset` từng dòng. */
				$d_k['gioLe'] = 0.0; $d_k['phuTroiLe'] = 0.0; $d_k['leTheo'] = array();
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
		/* ═══════════════════════════════════════════════════════════════════════════════════
		 * 🔴 MỘT TỆP CÓ THỂ CHỨA NHIỀU CƠ SỞ — anh Thắng 16/09/2026: *"nếu chọn 1 cơ sở, xuất
		 *    bảng lương có 1 cơ sở, nếu chọn 2, 3 cơ sở thì ghép nhiều bảng vào trong 1 file"*.
		 *
		 * Đọc ngược từ chính file kế toán anh gửi (`Lương cơ sở HCM 2026`, 20 khối trong MỘT tờ):
		 * tựa và hai dòng tiêu đề cột chỉ có MỘT LẦN ở đầu tờ, rồi mỗi cơ sở là một KHỐI —
		 * dòng mở khối mang số La Mã ở cột A và tên cơ sở ở cột B, các dòng người, rồi một dòng
		 * cộng riêng của khối ấy.
		 *
		 * ⚠️ MỘT TỜ, KHÔNG PHẢI MỖI CƠ SỞ MỘT TỜ. Kế toán cộng dọc cả tờ và dò bằng mắt từ trên
		 *    xuống; tách tờ là bắt họ mở đi mở lại 20 tab.
		 * ⚠️ `$coso` nhận CHUỖI (một cơ sở), chuỗi ngăn bởi dấu phẩy, hoặc mảng. Nơi gọi cũ
		 *    truyền một chuỗi và vẫn chạy y như trước.
		 * ═══════════════════════════════════════════════════════════════════════════════════ */
		$ds_cs = is_array( $coso ) ? $coso : explode( ',', (string) $coso );
		$ds_cs = array_values( array_filter( array_map( 'trim', $ds_cs ), 'strlen' ) );
		if ( ! $ds_cs ) { return array( 'ok' => false, 'error' => 'Chưa chọn cơ sở nào để xuất.' ); }

		/* Dựng bảng của từng cơ sở TRƯỚC, để một cơ sở hỏng thì nói ra ngay bằng tên nó, chứ
		   không dựng được nửa tệp rồi mới ngã. */
		$ds_b = array();
		foreach ( $ds_cs as $cs_x ) {
			$b_x = self::dung( $cs_x, $thang );
			if ( empty( $b_x['ok'] ) ) {
				return array( 'ok' => false,
					'error' => 'Cơ sở ' . $cs_x . ': ' . ( isset( $b_x['error'] ) ? $b_x['error'] : 'không dựng được.' ) );
			}
			$ds_b[ $cs_x ] = $b_x;
		}
		$b = reset( $ds_b );          // bảng ĐẦU — dùng cho tháng, tên tờ, và giá trị trả về

		$chu  = function ( $v, $s ) { return array( 'v' => VHCC_Xuat::chu( $v ), 's' => $s ); };
		$o    = function ( $v, $s ) { return VHCC_Xuat::o_kieu( $v, $s ); };
		/* ⚠️ PHẢI NHẬN CẢ THAM SỐ THỨ BA. Bản nháp giữ closure hai tham số, nên mọi giá trị đã
		   tính truyền xuống đều rơi im lặng — tệp vẫn ra, vẫn có công thức, và vẫn trống trơn ở
		   mọi nơi không tính lại. Đúng cái lỗi đang đi sửa, lặp lại một tầng cao hơn. */
		$ct   = function ( $f, $s, $v = null ) { return VHCC_Xuat::ct( $f, $s, $v ); };
		$trong = function ( $s ) { return VHCC_Xuat::o_kieu( null, $s ); };

		$T = VHCC_Xuat::TIEN;
		$G = VHCC_Xuat::GIO;
		$V = VHCC_Xuat::CHU_V;
		$B = VHCC_Xuat::BANG;

		/* 🔴 DÒNG NGÀY GHI "Tháng 8/2026", ĐÚNG NHƯ FILE KẾ TOÁN ĐANG DÙNG.
		   Ô A5 của file anh Thắng gửi là một NGÀY mang định dạng `"Tháng "m/yyyy`, tức mắt đọc
		   ra "Tháng 8/2026". Bản trước ghi `31/08/2026` — cùng một tháng, nhưng khác chữ, và
		   người đối chiếu hai tờ cạnh nhau sẽ khựng lại đúng ở dòng đầu tiên.
		   ⚠️ Ghi thẳng bằng CHỮ, không ghi kiểu ngày rồi gắn định dạng: bộ xuất chưa có kiểu
		      ngày, mà thêm một kiểu ô mới chỉ để in một dòng tựa là đổi lõi để chữa cái vỏ. */
		$cuoi_thang = 'Tháng ' . (int) substr( $b['thang'], 5, 2 ) . '/' . substr( $b['thang'], 0, 4 );
		$ten_cs = '' !== trim( (string) $ten_cs ) ? trim( (string) $ten_cs ) : $b['coso'];

		$rong = function ( $n ) { return array_fill( 0, $n, '' ); };
		$hang = array();
		$hang[] = array_merge( array( 'K&H CO. LTD' ), $rong( 26 ) );
		$hang[] = array_merge( array( 'ACCOUNTING' ), $rong( 26 ) );
		$hang[] = $rong( 27 );
		$hang[] = array_merge( array( $o( 'BẢNG TÍNH - THANH TOÁN TIỀN LƯƠNG', VHCC_Xuat::TUA ) ), $rong( 26 ) );
		$hang[] = array_merge( array( $o( $cuoi_thang, VHCC_Xuat::TUA ) ), $rong( 26 ) );
		/* Dòng 6: tên cơ sở khi xuất MỘT cơ sở. Xuất nhiều thì để trống — tên của từng cơ sở
		   nằm ở dòng mở khối của chính nó, ghi lại ở đây một cái tên là nói dối về 19 cái kia. */
		$hang[] = array_merge( array( $o( count( $ds_b ) > 1 ? '' : $ten_cs, VHCC_Xuat::DAM ) ), $rong( 26 ) );

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

		/* ═══════════════════════════════════════════════════════════════════════════════════
		 * MỖI CƠ SỞ MỘT KHỐI: dòng mở khối (số La Mã + tên) · các dòng người · dòng cộng khối.
		 *
		 * ⚠️ `$r` ĐẾM XUYÊN SUỐT CẢ TỜ, không đếm lại từ đầu mỗi khối. Mọi công thức trong dòng
		 *    (`=G12*H12`) đều bám số dòng THẬT trong tờ; đếm lại từ 9 là khối thứ hai trở đi trỏ
		 *    vào đúng mấy dòng của khối thứ nhất — ra số, trông như thật, mà sai của người khác.
		 * ═══════════════════════════════════════════════════════════════════════════════════ */
		$r   = self::DONG_DAU;
		$i_k = 0;
		/* Cộng dồn từng cột của KHỐI, để dòng cộng cũng mang sẵn con số — cùng lý do với mấy ô
		   công thức ở trên: không có số sẵn thì dòng cộng trống trơn ở mọi nơi không tính lại. */
		$cong_khoi = array();
		foreach ( $ds_b as $cs_k => $b_k ) {
		$i_k++;
		/* Dòng mở khối — cột A số La Mã, cột B tên cơ sở, đúng khuôn file kế toán đang dùng. */
		$mo_khoi = array( $o( self::so_la_ma( $i_k ), VHCC_Xuat::TONG ),
			$o( VHCC_NhanSu::ten_coso( $cs_k ), VHCC_Xuat::TONG ) );
		for ( $i = 2; $i < 27; $i++ ) { $mo_khoi[] = $trong( VHCC_Xuat::TONG ); }
		$hang[] = $mo_khoi;
		$r++;
		$dau_khoi  = $r;
		$cong_khoi = array();   // cộng dồn RIÊNG từng khối — xem chú thích vùng SUM

		foreach ( $b_k['dong'] as $x ) {
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
				/* ═══════════════════════════════════════════════════════════════════════════
				 * 🔴 MỖI Ô CÔNG THỨC KÈM LUÔN GIÁ TRỊ ĐÃ TÍNH.
				 *
				 * Anh Thắng 16/09/2026: *"Sao bảo làm y chang lại xuất bảng không có gì"* — kèm
				 * chính tệp hệ xuất ra. Soi ra thì tệp ấy có 43 ô công thức và KHÔNG ô nào kèm
				 * giá trị, còn file mẫu của kế toán có 560 ô đều kèm. Không kèm thì tệp chỉ có
				 * số khi mở bằng ứng dụng chịu tính lại; xem nhanh trên điện thoại, Google
				 * Sheets hay ô xem trước của hòm thư thì cột Lương chính trống trơn.
				 *
				 * Mấy con số dưới đây là của CHÍNH lõi lương vừa tính, cùng một phép với công
				 * thức — không phải số đoán. `fullCalcOnLoad` vẫn giữ, nên kế toán gõ vào ô phụ
				 * cấp là Excel tính lại ngay.
				 * ═══════════════════════════════════════════════════════════════════════════ */
				$v_i = ( null === $x['luongChinh'] ) ? null : (float) $x['luongChinh'];
				$la_c = ! empty( $x['laChinh'] );
				$v_u = $la_c ? (float) $x['tongCong'] : 0.0;
				$v_y = $la_c ? (float) $x['tongTru'] : 0.0;
				$v_z = ( null === $v_i ) ? null
					: round( $v_i + (float) $v_u - (float) $v_y, 2 );
				$dong[] = ( 'thang' === $x['cheDo'] )
					? $ct( 'E' . $r . '*G' . $r . '/F' . $r, $T, $v_i )
					: $ct( 'G' . $r . '*H' . $r, $T, $v_i );              // I
				$dong[] = $trong( $G );                                   // J giờ thêm
				$dong[] = $trong( $T );                                   // K lương giờ thêm
				$dong[] = $trong( $T );                                   // L BHXH
				/* K và L để trống nên M = I. */
				$dong[] = $ct( 'I' . $r . '+K' . $r . '-L' . $r, $T, $v_i );   // M tổng lương
				/* 🔴 BẢY CỘT CỘNG ĐỔ THẲNG TỪ SỐ CỬA HÀNG TRƯỞNG ĐÃ GÕ — anh Thắng 16/09/2026
				   đổi quyết định hôm trước ("để trống, kế toán điền"): *"mấy cột đó sẽ do cửa
				   hàng trưởng nhập"*. Ô nào chưa gõ vẫn để TRỐNG chứ không ghi 0: một tờ lương
				   đầy số 0 trông như đã xét hết mọi khoản, trong khi chưa ai gõ gì. */
				foreach ( array_keys( VHCC_ChotLuong::CONG ) as $k_c ) {   // N..T
					$dong[] = empty( $x['cong'][ $k_c ] ) ? $trong( $T )
						: $o( (float) $x['cong'][ $k_c ], $T );
				}
				$dong[] = $ct( 'SUM(N' . $r . ':T' . $r . ')', $T, $v_u );    // U
				$dong[] = empty( $x['tru']['phat'] ) ? $trong( $T )
					: $o( (float) $x['tru']['phat'], $T );                // V phạt
				$dong[] = $trong( $T );                                   // W (cột trống của mẫu)
				$dong[] = empty( $x['tru']['datCoc'] ) ? $trong( $T )
					: $o( (float) $x['tru']['datCoc'], $T );              // X đặt cọc
				$dong[] = $ct( 'SUM(V' . $r . ':X' . $r . ')', $T, $v_y );    // Y
				$dong[] = $ct( 'M' . $r . '+U' . $r . '-Y' . $r, $T, $v_z );  // Z
			}
			$dong[] = $o( implode( ' · ', $ghi ), $V );                   // AA
			$hang[] = $dong;
			if ( $co_gia ) {
				foreach ( array( 'I' => $v_i, 'M' => $v_i, 'U' => $v_u, 'Y' => $v_y, 'Z' => $v_z )
					as $c_k => $v_k ) {
					if ( null === $v_k ) { continue; }
					if ( ! isset( $cong_khoi[ $c_k ] ) ) { $cong_khoi[ $c_k ] = 0.0; }
					$cong_khoi[ $c_k ] += (float) $v_k;
				}
				foreach ( array_keys( VHCC_ChotLuong::CONG ) as $i_c => $k_c ) {
					$c_k = chr( ord( 'N' ) + $i_c );
					$v_k = empty( $x['cong'][ $k_c ] ) ? 0.0 : (float) $x['cong'][ $k_c ];
					if ( ! isset( $cong_khoi[ $c_k ] ) ) { $cong_khoi[ $c_k ] = 0.0; }
					$cong_khoi[ $c_k ] += $v_k;
				}
				foreach ( array( 'V' => 'phat', 'X' => 'datCoc' ) as $c_k => $k_t ) {
					$v_k = empty( $x['tru'][ $k_t ] ) ? 0.0 : (float) $x['tru'][ $k_t ];
					if ( ! isset( $cong_khoi[ $c_k ] ) ) { $cong_khoi[ $c_k ] = 0.0; }
					$cong_khoi[ $c_k ] += $v_k;
				}
			}
			$r++;
		}

		/* Dòng cộng của KHỐI — cộng thẳng bằng SUM để kế toán sửa một ô là tổng theo ngay.
		   ⚠️ Vùng SUM chạy từ DÒNG ĐẦU CỦA KHỐI NÀY, không phải từ dòng 9 của cả tờ: cộng từ 9
		      là khối thứ hai ôm luôn mọi khối trước nó, và con số ấy trông vẫn hợp lý. */
		$cuoi = $r - 1;
		$tong = array( $trong( VHCC_Xuat::TONG ),
			$o( 'TỔNG — ' . VHCC_NhanSu::ten_coso( $cs_k ), VHCC_Xuat::TONG ),
			$trong( VHCC_Xuat::TONG ), $trong( VHCC_Xuat::TONG ), $trong( VHCC_Xuat::TONG ),
			$trong( VHCC_Xuat::TONG ), $trong( VHCC_Xuat::TONG ), $trong( VHCC_Xuat::TONG ) );
		if ( $cuoi >= $dau_khoi ) {
			foreach ( array( 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U',
				'V', 'W', 'X', 'Y', 'Z' ) as $c ) {
				$tong[] = $ct( 'SUM(' . $c . $dau_khoi . ':' . $c . $cuoi . ')', VHCC_Xuat::TONG,
					isset( $cong_khoi[ $c ] ) ? round( $cong_khoi[ $c ], 2 ) : null );
			}
		} else {
			for ( $i = 0; $i < 18; $i++ ) { $tong[] = $trong( VHCC_Xuat::TONG ); }
		}
		$tong[] = $trong( VHCC_Xuat::TONG );
		$hang[] = $tong;
		$r++;
		}   /* hết một khối cơ sở */

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

	/**
	 * Số La Mã cho dòng mở khối — I, II, III… đúng như cột A của file kế toán đang dùng.
	 *
	 * ⚠️ Quá 3999 thì trả lại chính con số. Không ai có 3999 cơ sở, nhưng một hàm chuyển đổi mà
	 *    trả chuỗi rỗng ở rìa thì dòng mở khối mất số thứ tự và không ai hiểu vì sao.
	 */
	public static function so_la_ma( $n ) {
		$n = (int) $n;
		if ( $n < 1 || $n > 3999 ) { return (string) $n; }
		$bang = array( 1000 => 'M', 900 => 'CM', 500 => 'D', 400 => 'CD', 100 => 'C', 90 => 'XC',
			50 => 'L', 40 => 'XL', 10 => 'X', 9 => 'IX', 5 => 'V', 4 => 'IV', 1 => 'I' );
		$ra = '';
		foreach ( $bang as $gt => $ch ) {
			while ( $n >= $gt ) { $ra .= $ch; $n -= $gt; }
		}
		return $ra;
	}

	/** Tên tệp gửi về trình duyệt. */
	public static function ten_tep( $coso, $thang ) {
		$cs = preg_replace( '/[^A-Za-z0-9_-]+/', '-', (string) $coso );
		return 'LUONG-' . trim( $cs, '-' ) . '-' . str_replace( '-', '.', (string) $thang ) . '.xlsx';
	}
}
