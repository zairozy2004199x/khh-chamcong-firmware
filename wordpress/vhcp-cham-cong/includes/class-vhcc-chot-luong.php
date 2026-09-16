<?php
/**
 * CHỐT LƯƠNG THÁNG CỦA MỘT NGƯỜI — giờ ăn giá khác, các khoản cộng, các khoản trừ.
 *
 * =============================================================================================
 * 🔴 VÌ SAO CHỈ NHẬP GIỜ "KHÁC", KHÔNG CHIA CẢ SỐ GIỜ
 * =============================================================================================
 * Anh Thắng 16/09/2026: *"kế toán sẽ làm 1 việc, nhập giờ lương khác, nó sẽ trừ giờ tổng đi nếu
 * có giờ khác là được, tức giờ tổng là giờ chính"*.
 *
 * Trước đó anh nói câu làm sập cả thiết kế cũ của em: *"trên chấm công sẽ chỉ có giờ tổng"*.
 * Máy chấm công ghi MỘT con số giờ cho mỗi người mỗi cơ sở — nó không biết trong 126 giờ ấy có
 * 2 giờ dẫn chương trình và 6 giờ hỗ trợ. Việc tách ra là quyết định của NGƯỜI, làm lúc chốt
 * lương, và không suy ra được từ dữ liệu nào cả.
 *
 * Nhưng bắt kế toán chia trọn 126 giờ thành từng dòng thì mỗi người mỗi tháng là mấy lần gõ, mà
 * 99% số giờ vốn là việc chính. Nên luật của anh Thắng gọn hơn hẳn và ít sai hơn:
 *
 *      giờ chính  =  giờ chấm công  −  tổng các dòng giờ khác
 *
 * Kế toán chỉ gõ NGOẠI LỆ. Không gõ gì thì cả tháng là giờ chính — đúng thực tế, và không ai
 * phải làm gì cho phần lớn nhân sự.
 *
 * Đối chiếu file T08/2026 của anh: một người có 118 giờ Partime + 2 giờ MC + 6 giờ Hỗ Trợ. Tổng
 * 126. Kế toán chỉ cần gõ hai dòng sau (2 và 6); 118 tự ra.
 *
 * =============================================================================================
 * 🔴 TÊN VIỆC LÀ DANH SÁCH TỰ DO — KHÔNG ĐÓNG CỨNG
 * =============================================================================================
 * Anh Thắng: *"nhiều mảng sẽ có khác nữa, nên nếu thiết kế theo tàu thì lại không đúng"*.
 *
 * Bản trước của em đặt tên dòng theo một bảng CỐ ĐỊNH gắn với hậu tố ca (`TT` => 'Thu tiền'…).
 * Sai hai lần: mảng Khu vui chơi gọi `TT` là **Lơ Tàu**, còn mảng khác lại có MC, Hỗ Trợ,
 * Partime — những việc không có hậu tố nào cả. Tên việc phải do người gõ, và đơn giá tra theo
 * chính cái tên ấy qua `VHCC_GiaGio`.
 *
 * ⚠️ TỔNG GIỜ KHÁC KHÔNG ĐƯỢC VƯỢT GIỜ CHẤM CÔNG. Vượt là giờ chính ra ÂM — tức trừ tiền một
 *    người vì kế toán gõ nhầm một số. Chối lượt ghi và nói rõ đang lệch bao nhiêu.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_ChotLuong {

	/** Khoá trong bảng `cai_dat`. */
	const O = 'CHOT_LUONG_THANG';

	/** Nhập giờ lương = đụng vào tiền → bậc Kế toán, cùng cửa với bảng lương và sổ đơn giá. */
	const QUYEN = 'cong_coso';

	/** Nhiều hơn ngần này giờ trong một dòng thì gần như chắc là gõ nhầm (744 = cả tháng 31 ngày). */
	const GIO_TOI_DA = 744;

	/* ==================================================================== đọc */

	public static function so() {
		$d = VHCC_Luong::cai_dat( self::O, null );
		return is_array( $d ) ? $d : array();
	}

	private static function khoa_cs( $coso ) {
		return VHCC_Luong::bo_chu( preg_replace( '/^CS_/i', '', trim( (string) $coso ) ) );
	}

	/**
	 * Các dòng giờ khác của một cơ sở trong một tháng.
	 *
	 * @return array [ ma_nv_thường => [ [ 'viec' => 'MC', 'gio' => 2.0 ], … ] ]
	 */
	public static function thang( $coso, $thang, $so = null ) {
		$so = ( null === $so ) ? self::so() : $so;
		$k  = self::khoa_cs( $coso );
		$tt = VHCC_Luong::tien_to_thang( $thang );
		if ( '' === $k || '' === $tt ) { return array(); }
		return ( isset( $so[ $k ][ $tt ]['gio'] ) && is_array( $so[ $k ][ $tt ]['gio'] ) )
			? $so[ $k ][ $tt ]['gio'] : array();
	}

	/** Các dòng của MỘT người. */
	public static function cua( $coso, $thang, $ma_nv, $so = null ) {
		$ds = self::thang( $coso, $thang, $so );
		$m  = strtolower( trim( (string) $ma_nv ) );
		return ( isset( $ds[ $m ] ) && is_array( $ds[ $m ] ) ) ? $ds[ $m ] : array();
	}

	/**
	 * VIỆC CHÍNH của một người trong tháng — tên chức vụ mà PHẦN GIỜ CÒN LẠI ăn theo.
	 *
	 * =========================================================================================
	 * 🔴 VÌ SAO PHẢI KHAI RIÊNG, KHÔNG SUY TỪ HỒ SƠ ĐƯỢC
	 * =========================================================================================
	 * Trước bản này, dòng chính của bảng lương lấy tên từ hồ sơ (`chuc_vu_chinh()`). Chạy số
	 * thật của anh Thắng thì bảy dòng đều ghi **"Khu vui chơi"** — mà đó là tên MẢNG kinh doanh,
	 * không phải một chức vụ ăn lương. Không ai khai đơn giá cho "Khu vui chơi" cả, nên cả bảy
	 * dòng đứng im ở "CHƯA KHAI ĐƠN GIÁ" và không có cách nào thoát ra: khai giá cho tên mảng là
	 * sai về nghĩa, mà không khai thì bảng lương rỗng.
	 *
	 * Anh Thắng 16/09/2026: *"Mặc định nhân viên nếu làm 1 công việc thì chọn xong, giờ tự chốt,
	 * hoặc chọn cái đầu tiên làm giờ chính, cái giờ sau nhập thêm thì giờ chính giảm đi"*.
	 *
	 * Nên việc chính là một LỰA CHỌN của người chốt lương, đúng như mấy dòng giờ khác — chỉ khác
	 * ở chỗ nó KHÔNG phải gõ số giờ: giờ của nó là phần còn lại.
	 *
	 *     giờ chính = giờ chấm công − tổng giờ khác
	 *
	 * Chọn xong một việc mà không gõ dòng nào khác thì cả tháng ăn giá ấy — đúng "nếu làm 1 công
	 * việc thì chọn xong, giờ tự chốt".
	 *
	 * ⚠️ CHƯA CHỌN THÌ TRẢ RỖNG, và chỗ dựng bảng tự lùi về tên trong hồ sơ. Đoán bừa một cái
	 *    tên ở đây là đoán ra tiền.
	 */
	public static function viec_chinh( $coso, $thang, $ma_nv, $so = null ) {
		$so = ( null === $so ) ? self::so() : $so;
		$k  = self::khoa_cs( $coso );
		$tt = VHCC_Luong::tien_to_thang( $thang );
		$m  = strtolower( trim( (string) $ma_nv ) );
		return isset( $so[ $k ][ $tt ]['chinh'][ $m ] )
			? trim( (string) $so[ $k ][ $tt ]['chinh'][ $m ] ) : '';
	}

	/** Tổng giờ khác của một người — dùng để trừ ra khỏi giờ chấm công. */
	public static function tong_cua( $coso, $thang, $ma_nv, $so = null ) {
		$t = 0.0;
		foreach ( self::cua( $coso, $thang, $ma_nv, $so ) as $d ) {
			$t += (float) ( isset( $d['gio'] ) ? $d['gio'] : 0 );
		}
		return round( $t, 2 );
	}

	/** Mọi tên việc đã từng gõ ở cơ sở này — để màn nhập gợi ý, khỏi gõ lại và khỏi gõ lệch. */
	public static function ten_da_dung( $coso, $so = null ) {
		$so = ( null === $so ) ? self::so() : $so;
		$k  = self::khoa_cs( $coso );
		$ra = array();
		if ( ! isset( $so[ $k ] ) || ! is_array( $so[ $k ] ) ) { return $ra; }
		foreach ( $so[ $k ] as $thang_x ) {
			foreach ( (array) ( isset( $thang_x['gio'] ) ? $thang_x['gio'] : array() ) as $dong ) {
				foreach ( (array) $dong as $d ) {
					$v = trim( (string) ( isset( $d['viec'] ) ? $d['viec'] : '' ) );
					if ( '' !== $v && ! in_array( $v, $ra, true ) ) { $ra[] = $v; }
				}
			}
		}
		sort( $ra );
		return $ra;
	}

	/* ==================================================================== khoản tiền */

	/**
	 * BẢY CỘT CỘNG VÀ HAI CỘT TRỪ — lấy đúng tên và đúng thứ tự trong file kế toán.
	 *
	 * 🔴 ĐÓNG CỨNG DANH SÁCH NÀY LÀ CỐ Ý, ngược với tên việc (tự do). Hai thứ khác hẳn nhau:
	 *    tên việc là chữ của từng cửa hàng ("Lơ Tàu", "MC", "Partime"), còn mấy cột này là BỐ CỤC
	 *    của tờ nộp kế toán — cột nào đứng thứ mấy là thứ kế toán soi bằng mắt theo vị trí. Cho
	 *    thêm cột tự do ở đây là mỗi cửa hàng nộp một tờ khác nhau, và người gom 20 tờ lại phải
	 *    dò từng cái.
	 *
	 * ⚠️ Khoá (`setup`, `kid`…) KHÔNG được đổi khi đã có dữ liệu: nó là khoá lưu, không phải nhãn.
	 *    Muốn đổi chữ hiện ra thì sửa phần tên, giữ nguyên khoá.
	 */
	const CONG = array(
		'setup'      => 'Setup',
		'kid'        => '%KID - Trách nhiệm',
		'target'     => 'Target',
		'luongThieu' => 'Lương Thiếu',
		'htXe'       => 'HT giữ xe, HT đi lại',
		'traTN'      => 'Trả TN',
		'hoanCoc'    => 'Hoàn cọc',
	);
	const TRU = array(
		'phat'   => 'Phạt',
		'datCoc' => 'Đặt cọc',
	);

	/** Trần vô lý cho một ô tiền — trên mức này gần như chắc là gõ dư số 0. */
	const TIEN_TOI_DA = 100000000;

	/** Các khoản tiền của một người trong tháng: [ 'cong' => [...], 'tru' => [...] ]. */
	public static function tien_cua( $coso, $thang, $ma_nv, $so = null ) {
		$so = ( null === $so ) ? self::so() : $so;
		$k  = self::khoa_cs( $coso );
		$tt = VHCC_Luong::tien_to_thang( $thang );
		$m  = strtolower( trim( (string) $ma_nv ) );
		$o  = array( 'cong' => array(), 'tru' => array() );
		if ( '' === $k || '' === $tt || '' === $m ) { return $o; }
		$d = isset( $so[ $k ][ $tt ]['tien'][ $m ] ) ? $so[ $k ][ $tt ]['tien'][ $m ] : null;
		if ( ! is_array( $d ) ) { return $o; }
		foreach ( array( 'cong', 'tru' ) as $nh ) {
			if ( isset( $d[ $nh ] ) && is_array( $d[ $nh ] ) ) { $o[ $nh ] = $d[ $nh ]; }
		}
		return $o;
	}

	/** Tổng cộng và tổng trừ của một người — hai con số đi thẳng vào cột U và Y của tờ nộp. */
	public static function tong_tien( $coso, $thang, $ma_nv, $so = null ) {
		$t = self::tien_cua( $coso, $thang, $ma_nv, $so );
		$c = 0.0; $r = 0.0;
		foreach ( self::CONG as $k => $x ) { $c += (float) ( isset( $t['cong'][ $k ] ) ? $t['cong'][ $k ] : 0 ); }
		foreach ( self::TRU  as $k => $x ) { $r += (float) ( isset( $t['tru'][ $k ] ) ? $t['tru'][ $k ] : 0 ); }
		return array( 'cong' => round( $c, 2 ), 'tru' => round( $r, 2 ) );
	}

	/* ══════════════════════════════════════════════════════════════════════════════════════════
	 * ĂN LƯƠNG THÁNG — khai theo TỪNG NGƯỜI, TỪNG THÁNG, ngay trên bảng lương
	 * ══════════════════════════════════════════════════════════════════════════════════════════
	 * Anh Thắng 16/09/2026: *"Trong 1 cửa hàng, có bạn nhận lương tháng không phải theo giờ, nên
	 * tách bạn đó ra, khi tích vào bạn đó, nhập lương và ngày công là ra lương tháng"*.
	 *
	 * Lõi tính lương VỐN ĐÃ biết lối tháng (`Lương cb × Số công thực / Số công YC`). Thiếu là
	 * ĐƯỜNG KHAI: `luong_co_ban` nằm trong HỒ SƠ (màn khác, quyền khác), còn `Số công YC` là một
	 * con số khai CHUNG cho cả cơ sở ở tab Cấu hình. Muốn cho một người ăn lương tháng thì phải
	 * đi hai màn, và con số công chuẩn thì dùng chung với mọi người.
	 *
	 * 🔴 VÌ SAO KHAI THEO THÁNG, KHÔNG SỬA HỒ SƠ:
	 *    Lương cơ bản trong hồ sơ là thứ có thật và dùng cho nhiều việc (bảo hiểm, hợp đồng).
	 *    Bảng lương tháng 8 phải giữ nguyên con số của tháng 8 kể cả khi tháng 10 người ta tăng
	 *    lương — sửa hồ sơ là viết lại quá khứ của mọi tháng đã chốt. Sổ này đứng cạnh mấy khoản
	 *    cộng/trừ, cùng một cơ sở/tháng/mã, nên tháng nào ra số của tháng ấy.
	 *
	 * ⚠️ KHÔNG khai ở đây thì lui về hồ sơ + cấu hình cơ sở, y như trước. Đây là LỚP ĐÈ, không
	 *    phải lối thay thế — cơ sở nào đang chạy ổn không phải khai lại gì.
	 */

	/** Trần vô lý cho số công chuẩn một tháng. Quá 31 là gõ nhầm cột. */
	const CONG_TOI_DA = 31;

	/**
	 * Người này tháng này có khai ăn lương tháng không.
	 * Trả `null` nếu không khai; nếu có thì `array( 'lcb' => …, 'congYc' => … )`.
	 */
	public static function thang_cua( $coso, $thang, $ma_nv, $so = null ) {
		$so = ( null === $so ) ? self::so() : $so;
		$k  = self::khoa_cs( $coso );
		$tt = VHCC_Luong::tien_to_thang( $thang );
		$m  = strtolower( trim( (string) $ma_nv ) );
		if ( '' === $k || '' === $tt || '' === $m ) { return null; }
		$d = isset( $so[ $k ][ $tt ]['luongThang'][ $m ] ) ? $so[ $k ][ $tt ]['luongThang'][ $m ] : null;
		if ( ! is_array( $d ) || empty( $d['lcb'] ) ) { return null; }
		return array(
			'lcb'    => (float) $d['lcb'],
			'congYc' => ( isset( $d['congYc'] ) && (float) $d['congYc'] > 0 ) ? (float) $d['congYc'] : null,
		);
	}

	/**
	 * Bật / tắt lối ăn lương tháng cho MỘT người trong MỘT tháng.
	 *
	 * @param bool  $bat     Ô tích. Tắt = xoá hẳn khai báo, người ấy quay về tính theo giờ.
	 * @param mixed $luong_cb Lương cơ bản tháng ấy.
	 * @param mixed $cong_yc  Số công chuẩn của tháng. Để trống = mượn con số chung của cơ sở.
	 */
	public static function dat_thang( $u, $coso, $thang, $ma_nv, $bat, $luong_cb, $cong_yc ) {
		$chan = self::gac( $u, $coso );
		if ( '' !== $chan ) { return array( 'ok' => false, 'error' => $chan ); }
		$k  = self::khoa_cs( $coso );
		$tt = VHCC_Luong::tien_to_thang( $thang );
		$m  = strtolower( trim( (string) $ma_nv ) );
		if ( '' === $k || '' === $tt || '' === $m ) {
			return array( 'ok' => false, 'error' => 'Thiếu cơ sở, tháng hoặc mã nhân viên.' );
		}
		$so = self::so();
		if ( ! $bat ) {
			unset( $so[ $k ][ $tt ]['luongThang'][ $m ] );
			if ( empty( $so[ $k ][ $tt ]['luongThang'] ) ) { unset( $so[ $k ][ $tt ]['luongThang'] ); }
			if ( empty( $so[ $k ][ $tt ] ) ) { unset( $so[ $k ][ $tt ] ); }
			VHCC_Luong::dat_cai_dat( self::O, $so, $u );
			return array( 'ok' => true, 'bat' => false );
		}
		$lcb = VHCC_NhanSu::so_tien( $luong_cb );
		if ( $lcb <= 0 ) {
			return array( 'ok' => false, 'error' => 'Tích "Ăn lương tháng" thì phải gõ Lương cơ bản — '
				. 'không có nó thì không ra được tiền, mà để trống rồi vẫn tích là bảng lương im lặng '
				. 'trả 0đ cho người ấy.' );
		}
		if ( $lcb > self::TIEN_TOI_DA ) {
			return array( 'ok' => false, 'error' => 'Lương cơ bản quá lớn ('
				. number_format( $lcb, 0, ',', '.' ) . 'đ) — gõ dư số 0?' );
		}
		$yc = trim( (string) $cong_yc );
		if ( '' !== $yc ) {
			$yc = (float) str_replace( ',', '.', $yc );
			if ( $yc <= 0 || $yc > self::CONG_TOI_DA ) {
				return array( 'ok' => false, 'error' => 'Số công chuẩn phải từ 1 tới '
					. self::CONG_TOI_DA . ' ngày — gõ "' . trim( (string) $cong_yc ) . '" thì không ra được tiền.' );
			}
		} else {
			$yc = 0;
		}
		$so[ $k ][ $tt ]['luongThang'][ $m ] = array( 'lcb' => $lcb, 'congYc' => $yc );
		VHCC_Luong::dat_cai_dat( self::O, $so, $u );
		return array( 'ok' => true, 'bat' => true, 'lcb' => $lcb, 'congYc' => $yc );
	}

	/**
	 * Ghi các khoản tiền của MỘT người.
	 *
	 * ⚠️ Ô TRỐNG = KHÔNG CÓ KHOẢN ẤY, không phải số 0. Ghi 0 vào sổ thì tờ xuất ra đầy số 0 —
	 *    mà một tờ lương đầy 0 trông như đã xét hết mọi khoản, trong khi thật ra chưa ai gõ gì.
	 */
	public static function dat_tien( $u, $coso, $thang, $ma_nv, $cong, $tru ) {
		$chan = self::gac( $u, $coso );
		if ( '' !== $chan ) { return array( 'ok' => false, 'error' => $chan ); }
		$k  = self::khoa_cs( $coso );
		$tt = VHCC_Luong::tien_to_thang( $thang );
		$m  = strtolower( trim( (string) $ma_nv ) );
		if ( '' === $k || '' === $tt || '' === $m ) {
			return array( 'ok' => false, 'error' => 'Thiếu cơ sở, tháng hoặc mã nhân viên.' );
		}
		$sach = array( 'cong' => array(), 'tru' => array() );
		foreach ( array( 'cong' => self::CONG, 'tru' => self::TRU ) as $nh => $bang ) {
			$vao = ( 'cong' === $nh ) ? (array) $cong : (array) $tru;
			foreach ( $bang as $khoa => $ten ) {
				$v = isset( $vao[ $khoa ] ) ? trim( (string) $vao[ $khoa ] ) : '';
				if ( '' === $v ) { continue; }
				$n = VHCC_NhanSu::so_tien( $v );
				if ( $n < 0 ) {
					return array( 'ok' => false, 'error' => 'Ô "' . $ten . '" không nhận số âm — '
						. 'muốn trừ tiền thì gõ vào nhóm "Các khoản giảm trừ".' );
				}
				if ( $n > self::TIEN_TOI_DA ) {
					return array( 'ok' => false, 'error' => 'Ô "' . $ten . '" quá lớn ('
						. number_format( $n, 0, ',', '.' ) . 'đ) — gõ dư số 0?' );
				}
				if ( $n > 0 ) { $sach[ $nh ][ $khoa ] = $n; }
			}
		}
		$so = self::so();
		if ( $sach['cong'] || $sach['tru'] ) { $so[ $k ][ $tt ]['tien'][ $m ] = $sach; }
		else {
			unset( $so[ $k ][ $tt ]['tien'][ $m ] );
			if ( empty( $so[ $k ][ $tt ]['tien'] ) ) { unset( $so[ $k ][ $tt ]['tien'] ); }
			if ( empty( $so[ $k ][ $tt ] ) ) { unset( $so[ $k ][ $tt ] ); }
			if ( empty( $so[ $k ] ) ) { unset( $so[ $k ] ); }
		}
		VHCC_Luong::dat_cai_dat( self::O, $so, $u );
		$t = self::tong_tien( $coso, $thang, $ma_nv );
		return array( 'ok' => true, 'cong' => $t['cong'], 'tru' => $t['tru'] );
	}

	/**
	 * Gác chung cho mọi lượt ghi.
	 *
	 * 🔴 CỬA HÀNG TRƯỞNG NHẬP ĐƯỢC — anh Thắng 16/09/2026: *"mấy cột đó sẽ do cửa hàng trưởng
	 *    nhập"*, đổi lại quyết định hôm 15/09 (*"để trống, kế toán điền"*). Quyết định của anh,
	 *    và nó có cái giá phải nói ra: cửa hàng trưởng nay chạm được vào TIỀN của người trong cơ
	 *    sở mình — Phạt, Đặt cọc, Target. `co_quyen_coso()` chốt phạm vi: cơ sở khác vẫn không
	 *    đụng được. Kế toán vẫn nên soi lại trước khi trả, và mọi ô đều hiện nguyên trên tờ xuất.
	 */
	private static function gac( $u, $coso ) {
		if ( ! VHCC_Vai::duoc( $u, self::QUYEN ) ) {
			return VHCC_Vai::loi( $u, self::QUYEN, 'Nhập khoản lương tháng' );
		}
		if ( ! VHCC_NhanSu::co_quyen_coso( $u, $coso ) ) {
			return 'Không có quyền trên cơ sở "' . $coso . '".';
		}
		return '';
	}

	/* ==================================================================== ghi */

	/**
	 * Ghi lại toàn bộ các dòng giờ khác của MỘT người trong MỘT tháng.
	 *
	 * @param array $dong  [ [ 'viec' => 'MC', 'gio' => '2' ], … ]. Mảng rỗng = xoá hết.
	 * @param float $gio_cham  Giờ chấm công của người ấy — để chặn chia quá tay.
	 */
	/**
	 * @param string|null $viec_chinh  null = không đụng tới; '' = bỏ khai; chuỗi = đặt việc chính.
	 */
	public static function dat( $u, $coso, $thang, $ma_nv, $dong, $gio_cham, $viec_chinh = null ) {
		$chan = self::gac( $u, $coso );
		if ( '' !== $chan ) { return array( 'ok' => false, 'error' => $chan ); }
		$k  = self::khoa_cs( $coso );
		$tt = VHCC_Luong::tien_to_thang( $thang );
		$m  = strtolower( trim( (string) $ma_nv ) );
		if ( '' === $k || '' === $tt || '' === $m ) {
			return array( 'ok' => false, 'error' => 'Thiếu cơ sở, tháng hoặc mã nhân viên.' );
		}

		$sach = array();
		$tong = 0.0;
		foreach ( (array) $dong as $d ) {
			$viec = trim( (string) ( isset( $d['viec'] ) ? $d['viec'] : '' ) );
			$gio_s = trim( (string) ( isset( $d['gio'] ) ? $d['gio'] : '' ) );
			/* Dòng trống hoàn toàn thì bỏ qua — biểu mẫu luôn có mấy dòng trống để gõ thêm. */
			if ( '' === $viec && '' === $gio_s ) { continue; }
			if ( '' === $viec ) {
				return array( 'ok' => false, 'error' => 'Có dòng gõ số giờ mà chưa đặt tên việc.' );
			}
			$gio = (float) str_replace( ',', '.', $gio_s );
			if ( $gio <= 0 ) {
				return array( 'ok' => false, 'error' => 'Số giờ của "' . $viec . '" phải lớn hơn 0.' );
			}
			if ( $gio > self::GIO_TOI_DA ) {
				return array( 'ok' => false, 'error' => 'Số giờ của "' . $viec . '" quá lớn ('
					. $gio . ' giờ) — cả tháng chỉ có ' . self::GIO_TOI_DA . ' giờ.' );
			}
			$sach[] = array( 'viec' => $viec, 'gio' => round( $gio, 2 ) );
			$tong  += $gio;
		}

		/* 🔴 KHÔNG ĐƯỢC VƯỢT GIỜ CHẤM CÔNG. Vượt là giờ chính ra ÂM — trừ tiền một người vì một
		   con số gõ nhầm, mà bảng vẫn có số nên nhìn qua không thấy gì lạ. */
		$gio_cham = round( (float) $gio_cham, 2 );
		if ( round( $tong, 2 ) > $gio_cham ) {
			return array( 'ok' => false, 'error' => 'Tổng giờ khác (' . round( $tong, 2 )
				. 'h) LỚN HƠN giờ chấm công của người này (' . $gio_cham . 'h) — giờ chính sẽ ra số âm. '
				. 'Sửa lại, hoặc bù giờ chấm công trước.' );
		}

		$so = self::so();
		if ( $sach ) { $so[ $k ][ $tt ]['gio'][ $m ] = $sach; }
		else {
			unset( $so[ $k ][ $tt ]['gio'][ $m ] );
			if ( empty( $so[ $k ][ $tt ]['gio'] ) ) { unset( $so[ $k ][ $tt ]['gio'] ); }
		}
		/* Việc chính — `null` nghĩa là lượt gọi này không nói gì về nó, giữ nguyên cái đang có.
		   Phân biệt được "không nói" với "bỏ khai" là cần: mọi lượt lưu giờ khác mà cũng xoá
		   luôn việc chính thì người ta gõ thêm một dòng MC là mất giá của cả phần giờ còn lại. */
		if ( null !== $viec_chinh ) {
			$vc = trim( (string) $viec_chinh );
			if ( '' !== $vc ) { $so[ $k ][ $tt ]['chinh'][ $m ] = $vc; }
			else {
				unset( $so[ $k ][ $tt ]['chinh'][ $m ] );
				if ( empty( $so[ $k ][ $tt ]['chinh'] ) ) { unset( $so[ $k ][ $tt ]['chinh'] ); }
			}
		}
		if ( empty( $so[ $k ][ $tt ] ) ) { unset( $so[ $k ][ $tt ] ); }
		if ( empty( $so[ $k ] ) ) { unset( $so[ $k ] ); }
		VHCC_Luong::dat_cai_dat( self::O, $so, $u );
		return array( 'ok' => true, 'so' => count( $sach ), 'tong' => round( $tong, 2 ),
			'chinh' => round( $gio_cham - $tong, 2 ) );
	}
}
