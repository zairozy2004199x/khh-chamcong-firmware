<?php
/**
 * TÍNH TIỀN TỪ CHỈ SỐ MÁY — dòng MÁY TIỀN. Thay phần đầu `JP2_04_TinhToan.gs`.
 *
 * =============================================================================================
 * 🔴 MỌI CON SỐ DOANH THU CỦA JP RA ĐỜI Ở ĐÂY. CHÉP Y NGUYÊN, KHÔNG "DỌN CHO GỌN".
 * =============================================================================================
 * `tools/test/kiem-jp-tinh.php` chạy CHÍNH mã JavaScript gốc bằng node trên 14 dòng SỐ LIỆU
 * THẬT (form AM BD ngày 31/07/2026, bản gốc dùng làm `jpSelfTest_`) rồi đòi PHP ra y hệt từng
 * trường. Lệch một ly là bài kiểm đỏ.
 *
 * ---------------------------------------------------------------------------------------------
 * 🔴 BỐN CHỖ KHÔNG ĐỐI XỨNG — SỬA "CHO CÂN" LÀ HỎNG, VÀ SỔ VẪN CÂN NÊN KHÔNG AI BẮT ĐƯỢC.
 * ---------------------------------------------------------------------------------------------
 *
 * 1. THỪA TIỀN THÌ CỘNG TRỨNG, HỤT TIỀN THÌ KHÔNG TRỪ (`max(0, lechTM)`).
 *    · thừa tiền -> có xung máy không đếm được -> quả trứng ĐÃ RA khỏi máy. Cộng là đúng.
 *    · hụt tiền  -> đồng hồ ĐÃ đếm xung, tức máy đã nhận tiền và ĐÃ nhả trứng; tiền thiếu là
 *      tiền mất SAU KHI bán. Trừ trứng ở đây là xuất kho thiếu -> sổ 632 hụt giá vốn, mà sổ
 *      VẪN CÂN.
 *
 * 2. CHỈ NHÁNH SUY-TỪ-TIỀN MỚI TRỪ HOÀN KHÁCH.
 *    Đồng hồ đếm trứng chỉ đếm quả ĐÃ NHẢ, nên phần khách không lấy vốn đã không nằm trong đó.
 *    Trừ ở cả hai nhánh là xuất kho thiếu — cùng hậu quả với chỗ 1.
 *
 * 3. PHÉP KIỂM CHÉO SO VỚI SỐ ĐÃ TRỪ HOÀN.
 *    Không trừ thì mọi dòng có hoàn đều báo lệch oan đúng bằng số quả hoàn — mà đó là khoản ĐÃ
 *    ĐƯỢC GIẢI THÍCH. Báo sai vài lần là không ai đọc cảnh báo nữa, và cảnh báo thật chìm theo.
 *
 * 4. Ô "THỰC THU" TRỐNG LÀ **CHƯA ĐẾM**, KHÔNG PHẢI ĐẾM ĐƯỢC 0.
 *    Coi trống là 0 thì lệch ra `−(tiền mặt máy báo)`, và bản tổng đặt `adjMachine = Σ lệch`
 *    -> tiền phải nộp của CẢ BÁO CÁO về 0. Mất trắng một kỳ, mà sổ vẫn cân.
 *
 * ---------------------------------------------------------------------------------------------
 * ⚠️ `collection` SUY TỪ `amount`, KHÔNG PHẢI `mActual / 2`.
 *    Cột ấy là "đơn vị 10.000đ" của form giấy — thứ kế toán đối chiếu bằng mắt. Với máy
 *    10.000đ/xung nó phải bằng CHÍNH số xung. Giữ `mAct / 2` là màn hình nói một đằng, tiền
 *    tính một nẻo.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHJP_Tinh {

	const GIA_XUNG_MAC_DINH = 5000;
	const GIA_XUNG_CHO_PHEP = array( 5000, 10000 );
	const GIA_XU       = 50000;    // một xu ăn bao nhiêu tiền ở đồng hồ COIN
	const GIA_XU_TIEN  = 10000;    // và ở đồng hồ MONEY của cùng máy xu
	/* Ngưỡng kêu to khi lệch máy lớn: phải vượt CẢ HAI — ít nhất 200.000đ VÀ hơn 5% doanh thu.
	   Chỉ một điều kiện thì cơ sở nhỏ kêu suốt còn cơ sở lớn im lặng nuốt khoản lớn. */
	const LECH_MAY_SAN = 200000;
	const LECH_MAY_PCT = 0.05;

	/* Sáu loại dòng. Mỗi loại một bảng nhập, một cách tính — xem `dong()`. */
	const DONG_MONEY = 'MONEY';    // máy tiền  — 1 dòng = 1 ô máy (tiền + hàng)
	const DONG_COIN  = 'COIN';     // máy xu    — 1 dòng = 1 vị trí (2 đồng hồ + thu tiền)
	const DONG_STOCK = 'STOCK';    // máy xu    — 1 dòng = 1 mã hàng trong bảng tồn
	const DONG_NGOAI = 'NGOAI';    // cả hai    — 1 dòng = 1 mã hàng giữ NGOÀI máy
	const DONG_MAY   = 'MAY';      // mẫu tách  — 1 dòng = 1 ô máy, CHỈ tiền
	const DONG_HANG  = 'HANG';     // mẫu tách  — 1 dòng = 1 mã hàng, CHỈ hàng

	/** Bảng cảnh báo — mã, phần, câu. Giữ nguyên mã W* vì giao diện và kế toán gọi theo mã. */
	public static function warn_def() {
		return array(
			'MONEY_MISMATCH'   => array( 'code' => 'W1',  'part' => 'REV',
				'msg' => 'Tiền theo đồng hồ lệch so với đồng hồ đếm trứng' ),
			'STOCK_MISMATCH'   => array( 'code' => 'W2',  'part' => 'STOCK',
				'msg' => 'Hàng thực tế lệch so với tồn tính toán' ),
			'METER_BACKWARD'   => array( 'code' => 'W5',  'part' => 'REV',
				'msg' => 'Chỉ số sau nhỏ hơn chỉ số trước' ),
			'MISSING_REASON'   => array( 'code' => 'W6',  'part' => 'REV',
				'msg' => 'Có điều chỉnh nhưng chưa ghi lý do' ),
			'METER_MISSING'    => array( 'code' => 'W8',  'part' => 'REV',
				'msg' => 'Chưa nhập chỉ số sau — chưa tính được tiền' ),
			/* `gop` là danh sách CHO PHÉP gộp: chỉ cảnh báo mang `gop` mới được thu về một
			   dòng. Quên thêm `gop` thì màn hơi dài (thấy ngay) — chiều an toàn nằm phía đó. */
			'PRICE_REMAINDER'  => array( 'code' => 'W9',  'part' => 'REV',
				'msg' => 'Tiền không chia hết cho giá 1 trứng',
				'gop' => 'DU_TIEN', 'gopDv' => 'đ',
				'gopGhiChu' => 'máy nhả tiền theo xung nên dư lẻ là bình thường' ),
			'LECH_TM_APP'      => array( 'code' => 'W10', 'part' => 'REV',
				'msg' => 'Tiền mặt thực tế lệch so với app' ),
			'REFUND_REMAINDER' => array( 'code' => 'W13', 'part' => 'REV',
				'msg' => 'Hoàn khách không chia tròn cho giá 1 trứng' ),
			'PRICE_NO_RATE'    => array( 'code' => 'W14', 'part' => 'REV',
				'msg' => 'Mã hàng không suy ra được giá — không đối chiếu được với tiền, '
					. 'và nếu mã không có trong danh mục thì duyệt xong kho không tìm '
					. 'được lớp tồn nên giá vốn về 0đ (sổ 632 thiếu)' ),
			'LECH_QUY_TRUNG'   => array( 'code' => 'W16', 'part' => 'REV',
				'msg' => 'Tiền thừa đã được quy ra số trứng bán thêm' ),
			'COIN_VS_MONEY'    => array( 'code' => 'W3',  'part' => 'REV',
				'msg' => 'Hai đồng hồ COIN và MONEY không khớp' ),
			'VS_PAYBOX'        => array( 'code' => 'W4',  'part' => 'REV',
				'msg' => 'Không khớp Pay Box (tiền mặt + chuyển khoản)' ),
			'LECH_MAY_LON'     => array( 'code' => 'W15', 'part' => 'REV',
				'msg' => 'Lệch máy lớn bất thường so với doanh thu' ),
			'COIN_REMAINDER'   => array( 'code' => 'W12', 'part' => 'REV',
				'msg' => 'Tổng xu không chia hết cho giá xu',
				'gop' => 'DU_XU', 'gopDv' => ' xu' ),
			/* ───────────── Ba mã KHÔNG do phép tính dòng sinh ra ─────────────
			 * W7 và W17 là của đường ẢNH, W11 là của đường MỞ BÁO CÁO. Chúng phải nằm ở
			 * ĐÂY chứ không nằm rải rác tại nơi dùng: bảng này là nguồn duy nhất của câu
			 * chữ, và `kiem-jp-tinh.php` đối chiếu CẢ BẢNG với mã gốc. Khai ở chỗ dùng là
			 * phép đối chiếu ấy không nhìn thấy, rồi câu chữ hai bản trôi khỏi nhau. */
			'MISSING_PHOTO'    => array( 'code' => 'W7',  'part' => 'STOCK',
				'msg' => 'Thiếu ảnh so với cấu hình' ),
			'KY_CHONG'         => array( 'code' => 'W11', 'part' => 'REV',
				'msg' => 'Kỳ chồng với báo cáo khác' ),
			'THIEU_CUM_QR'     => array( 'code' => 'W17', 'part' => 'STOCK',
				'msg' => 'Có tiền QR/CK mà chưa chọn cụm nên không có chỗ gắn ảnh Pay Box' ),
		);
	}

	/** Dựng một cảnh báo. `$so` chỉ vào bản ghi khi cảnh báo ấy được phép gộp. */
	public static function warn( $ten, $chi_tiet = '', $so = null ) {
		$d = self::warn_def();
		$d = isset( $d[ $ten ] ) ? $d[ $ten ] : array( 'code' => '?', 'part' => 'REV', 'msg' => $ten );
		$w = array( 'code' => $d['code'], 'part' => $d['part'], 'msg' => $d['msg'],
			'detail' => (string) $chi_tiet );
		if ( isset( $d['gop'] ) ) {
			$w['gop'] = $d['gop'];
			$w['so']  = VHJP_Doc::num( $so );
		}
		return $w;
	}

	/**
	 * Chênh lệch hai chỉ số đồng hồ.
	 *
	 * ⚠️ Ô SAU còn TRỐNG là CHƯA NHẬP -> trả 0 và nhắc, chứ không coi là 0. Và chỉ nhắc khi ô
	 *    TRƯỚC có số: trước cũng trống thì dòng ấy chưa ai đụng tới, nhắc chỉ tổ làm nhiễu.
	 * ⚠️ Đồng hồ CHẠY LÙI thì trả 0, KHÔNG trả số âm — số âm chui vào tiền là doanh thu âm.
	 */
	public static function meter_delta( $truoc, $sau, $ten_dong_ho, &$warns ) {
		$b = VHJP_Doc::num( $truoc );
		$a = VHJP_Doc::num( $sau );
		if ( VHJP_Doc::blank( $sau ) ) {
			if ( $b > 0 ) {
				$warns[] = self::warn( 'METER_MISSING', $ten_dong_ho . ': trước = ' . $b . ', sau còn trống' );
			}
			return 0;
		}
		if ( $a < $b ) {
			$warns[] = self::warn( 'METER_BACKWARD', $ten_dong_ho . ': ' . $b . ' → ' . $a );
			return 0;
		}
		return $a - $b;
	}

	/** Giá một xung. Chỉ nhận giá trị trong danh sách cho phép; ngoài ra rơi về mặc định. */
	public static function gia_xung( $v ) {
		$n = VHJP_Doc::num( $v );
		return in_array( $n, self::GIA_XUNG_CHO_PHEP, true ) ? $n : self::GIA_XUNG_MAC_DINH;
	}

	/** Giá một quả của dòng: giá đã khai -> suy từ mã MISA -> suy từ mã hàng. */
	public static function gia_dong( $row ) {
		$g = VHJP_Doc::num( self::o( $row, 'price' ) );
		if ( $g > 0 ) { return $g; }
		$g = VHJP_CauHinh::gia_tu_ma( self::o( $row, 'itemMisa' ) );
		if ( $g > 0 ) { return $g; }
		return VHJP_CauHinh::gia_tu_ma( self::o( $row, 'itemCode' ) );
	}

	/**
	 * Lệch tiền mặt đếm được so với máy báo.
	 *
	 * 🔴 Ô nào TRỐNG cũng trả 0 — xem chỗ 4 trong khối 🔴 đầu tệp. Đừng "dọn" thành phép trừ
	 *    trần.
	 */
	public static function lech_tm( $row ) {
		if ( ! is_array( $row ) ) { return 0; }
		if ( VHJP_Doc::blank( self::o( $row, 'cashReal', '' ) )
			|| VHJP_Doc::blank( self::o( $row, 'cash', '' ) ) ) {
			return 0;
		}
		return VHJP_Doc::num( $row['cashReal'] ) - VHJP_Doc::num( $row['cash'] );
	}

	/**
	 * Hoàn khách quy ra số quả, gắn ở đúng mã hàng của dòng.
	 *
	 * Nhân viên gõ TIỀN hoàn (tiền là con số phải khớp với két, và hoàn lẻ không tròn quả vẫn
	 * ghi được). Số quả do máy suy ra, LÀM TRÒN XUỐNG — kho không nhận số lẻ.
	 */
	public static function hoan_theo_ma( $row, $gia ) {
		$amt = abs( VHJP_Doc::num( self::o( $row, 'refundAmt' ) ) );
		if ( 0 == $amt ) { return array( 'amt' => 0, 'qty' => 0, 'canhBao' => '' ); }
		if ( $gia <= 0 ) {
			return array( 'amt' => $amt, 'qty' => 0, 'canhBao' =>
				'Hoàn khách ' . VHJP_Doc::money( $amt ) . 'đ mà mã '
				. VHJP_Doc::str( self::o( $row, 'itemCode' ) )
				. ' không suy ra được giá — không quy ra được số quả, tiền vẫn trừ đúng' );
		}
		$qty = (int) floor( $amt / $gia );
		$du  = $amt - $qty * $gia;
		return array( 'amt' => $amt, 'qty' => $qty, 'canhBao' => 0 == $du ? '' :
			'Hoàn khách ' . VHJP_Doc::money( $amt ) . 'đ không chia tròn cho giá '
			. VHJP_Doc::money( $gia ) . 'đ — quy ra ' . $qty . ' quả, còn lẻ '
			. VHJP_Doc::money( $du ) . 'đ' );
	}

	/**
	 * W14 — dòng CÓ BÁN HÀNG mà mã không suy ra được giá. NGUỒN DUY NHẤT của cảnh báo này.
	 *
	 * ⚠️ Câu chữ nói HẬU QUẢ, không nói trạng thái: mã không ra giá thì gần như chắc chắn không
	 *    có trong danh mục, nên duyệt xong kho không tìm được lớp tồn -> giá vốn về 0đ và sổ 632
	 *    thiếu TRONG KHI SỔ VẪN CÂN.
	 */
	public static function canh_bao_ma_khong_gia( $row, &$warns ) {
		$ban = VHJP_Doc::num( self::o( $row, 'soldQty' ) );
		if ( $ban <= 0 || VHJP_Doc::num( self::o( $row, 'price' ) ) > 0 ) { return; }
		$warns[] = self::warn( 'PRICE_NO_RATE',
			'Bán ' . $ban . ' cái mà mã ' . VHJP_Doc::str( self::o( $row, 'itemCode' ) )
			. ' không suy ra được giá — không đối chiếu được với tiền, và nếu mã này không có '
			. 'trong danh mục thì duyệt xong kho không tìm được lớp tồn nên ' . $ban
			. ' cái này ra sổ 632 với giá vốn 0đ' );
	}

	/**
	 * Tính MỘT dòng máy tiền. Trả chính dòng ấy, đã điền các cột tính được và `warns`.
	 *
	 * Đọc khối 🔴 ở đầu tệp trước khi đụng vào hàm này.
	 */
	public static function dong_may_tien( $row ) {
		$warns = array();
		$row   = (array) $row;

		/* --- Đồng hồ TIỀN --- */
		$m_act = self::meter_delta( self::o( $row, 'mBefore' ), self::o( $row, 'mAfter' ),
			'Đồng hồ tiền', $warns );
		$row['mActual'] = $m_act;

		$gia_xung = self::gia_xung( self::o( $row, 'giaXung' ) );
		$row['amount']     = $m_act * $gia_xung;
		$row['collection'] = $row['amount'] / 10000;    // đơn vị 10.000đ, đúng như form giấy
		$row['cash']       = $row['amount'] - VHJP_Doc::num( self::o( $row, 'bank' ) );

		$row['lechTM'] = self::lech_tm( $row );
		if ( 0 != $row['lechTM'] ) {
			$warns[] = self::warn( 'LECH_TM_APP', 'Đếm được '
				. ( $row['lechTM'] > 0 ? 'NHIỀU hơn' : 'ÍT hơn' ) . ' máy báo '
				. VHJP_Doc::money( abs( $row['lechTM'] ) ) . 'đ' );
		}

		$gia = self::gia_dong( $row );
		$row['price'] = $gia;

		/* 🔴 Số trứng suy từ TIỀN THẬT SỰ THU ĐƯỢC. Chỉ cộng phần THỪA — xem chỗ 1 đầu tệp. */
		$tien_ban      = $row['amount'] + max( 0, $row['lechTM'] );
		$sold_by_money = $gia > 0 ? (int) floor( $tien_ban / $gia ) : 0;
		$co_dem        = ! VHJP_Doc::blank( self::o( $row, 'hAfter', '' ) );
		$sold_by_meter = $co_dem
			? self::meter_delta( self::o( $row, 'hBefore' ), self::o( $row, 'hAfter' ),
				'Đồng hồ đếm trứng', $warns )
			: 0;

		$hoan = self::hoan_theo_ma( $row, $gia );
		$row['refundAmt'] = $hoan['amt'];
		$row['refundQty'] = $hoan['qty'];
		if ( '' !== $hoan['canhBao'] ) {
			$warns[] = self::warn( 'REFUND_REMAINDER', $hoan['canhBao'] );
		}
		if ( $hoan['amt'] > 0 && '' === VHJP_Doc::str( self::o( $row, 'refundRowNote' ) ) ) {
			$warns[] = self::warn( 'MISSING_REASON',
				'Hoàn khách ' . VHJP_Doc::money( $hoan['amt'] ) . 'đ ở mã này chưa có lý do' );
		}

		/* 🔴 CHỈ nhánh suy-từ-tiền mới trừ hoàn khách — xem chỗ 2 đầu tệp. */
		$sold = $co_dem ? $sold_by_meter : max( 0, $sold_by_money - $hoan['qty'] );
		$row['soldQty'] = $sold;

		if ( $co_dem && $hoan['qty'] > 0 ) {
			$warns[] = self::warn( 'MONEY_MISMATCH',
				'Có đồng hồ đếm trứng nên số bán lấy theo đồng hồ (' . $sold_by_meter
				. ') — hoàn khách ' . $hoan['qty']
				. ' quả KHÔNG trừ thêm, vì đồng hồ chỉ đếm quả đã nhả' );
		}

		/* 🔴 Kiểm chéo so với số ĐÃ TRỪ hoàn — xem chỗ 3 đầu tệp. */
		$tien_du_suc = $sold_by_money - $hoan['qty'];
		if ( $co_dem && $gia > 0 && $sold_by_meter !== $tien_du_suc ) {
			$lech_tien = ( $sold_by_meter - $tien_du_suc ) * $gia;
			$warns[] = self::warn( 'MONEY_MISMATCH',
				'Đồng hồ trứng bán ' . $sold_by_meter . ' · tiền chỉ đủ ' . $tien_du_suc
				. ' (' . VHJP_Doc::money( $tien_ban ) . 'đ'
				. ( $hoan['amt'] > 0 ? ' − hoàn ' . VHJP_Doc::money( $hoan['amt'] ) . 'đ' : '' )
				. ' ÷ ' . VHJP_Doc::money( $gia ) . 'đ) · lệch '
				. VHJP_Doc::money( abs( $lech_tien ) ) . 'đ '
				. ( $lech_tien > 0 ? 'THIẾU TIỀN' : 'THỪA TIỀN' ) );
		}

		/* ⚠️ CHỈ nhắc khi máy THẬT SỰ CÓ đồng hồ đếm trứng. Mọi cơ sở máy tiền đều không có,
		   và cờ `coDhTrung = 'N'` đã ẩn hai ô ấy khỏi form — nhắc chung là một báo cáo 11 ô đẻ
		   ra 11 cảnh báo về một ô KHÔNG TỒN TẠI trên màn hình, mà nhân viên không có cách nào
		   làm nó tắt đi. */
		$co_that = VHJP_Doc::num( self::o( $row, 'hBefore' ) ) > 0
			|| ! VHJP_Doc::blank( self::o( $row, 'hAfter', '' ) );
		if ( ! $co_dem && $co_that && $m_act > 0 ) {
			$warns[] = self::warn( 'METER_MISSING',
				'Chưa nhập đồng hồ đếm trứng — không kiểm chéo được số bán' );
		}

		if ( ! $co_dem && $gia > 0 && $row['lechTM'] > 0 ) {
			$them = (int) floor( $tien_ban / $gia ) - (int) floor( $row['amount'] / $gia );
			if ( $them > 0 ) {
				$warns[] = self::warn( 'LECH_QUY_TRUNG',
					'Thu thừa ' . VHJP_Doc::money( $row['lechTM'] )
					. 'đ so với đồng hồ ⇒ tính thêm ' . $them
					. ' quả đã bán (đồng hồ không đếm được cú đó, nhưng hàng đã ra khỏi máy)' );
			}
		}

		if ( $gia > 0 ) {
			$du = $tien_ban - $sold_by_money * $gia;
			if ( 0 != $du ) {
				$warns[] = self::warn( 'PRICE_REMAINDER',
					'Dư ' . VHJP_Doc::money( $du ) . 'đ so với giá '
					. VHJP_Doc::money( $gia ) . 'đ/trứng', $du );
			}
		}

		/* --- Tồn của dòng --- */
		$open = VHJP_Doc::num( self::o( $row, 'hOpen' ) );
		$them_sl = VHJP_Doc::num( self::o( $row, 'addQty1' ) ) + VHJP_Doc::num( self::o( $row, 'addQty2' ) );
		$tra  = VHJP_Doc::num( self::o( $row, 'returnQty' ) );
		$out  = VHJP_Doc::num( self::o( $row, 'stockOut' ) );
		$left = $open + $them_sl - $sold - $tra;
		$row['hLeft'] = $left;

		if ( ! VHJP_Doc::blank( self::o( $row, 'stockActual', '' ) ) ) {
			$act = VHJP_Doc::num( $row['stockActual'] );
			if ( $act !== $left ) {
				$lech = $act - $left;
				$warns[] = self::warn( 'STOCK_MISMATCH',
					'Tính ' . $left . ' (đầu ' . $open . ' + bổ sung ' . $them_sl
					. ' − bán ' . $sold . ' − trả kho ' . $tra . ')'
					. ' · đếm ' . $act . ' · '
					. ( $lech > 0 ? 'THỪA ' . $lech : 'THIẾU ' . ( -$lech ) ) );
			}
			/* Tồn ngoài lớn hơn tồn đếm được nghĩa là hai ô đang được hiểu khác nhau — nói ra
			   chỗ đó, thay vì báo thiếu hàng. */
			if ( $out > $act ) {
				$warns[] = self::warn( 'STOCK_MISMATCH',
					'Tồn ngoài ' . $out . ' lớn hơn hàng tồn thực tế ' . $act
					. ' — kho đệm là một phần của số đã đếm, không thể nhiều hơn' );
			}
		}

		self::canh_bao_ma_khong_gia( $row, $warns );

		$row['warns']    = $warns;
		$row['warnJson'] = $warns ? wp_json_encode( $warns, JSON_UNESCAPED_UNICODE ) : '';
		return $row;
	}

	/* ═══════════════════════ MÁY XU — DÒNG TIỀN THEO VỊ TRÍ ═══════════════════════ */

	/**
	 * Dòng COIN: hai đồng hồ trên cùng một máy phải nói cùng một con số.
	 *
	 * 🔴 DÒNG COIN KHÔNG GIỮ HÀNG. Máy xu tách hẳn hai bảng: COIN = tiền theo vị trí,
	 *    STOCK = hàng theo mã. Trước đây chỗ này vẫn tính `soldQty` từ mấy ô luôn rỗng nên ra
	 *    0 — vô hại trên màn hình, nhưng thành bẫy lúc kho xuất giá vốn: cộng `soldQty` mọi
	 *    dòng là cộng cả dòng COIN. Ép về 0 cho rõ, và kho lọc theo LOẠI DÒNG chứ không dựa
	 *    vào việc số này tình cờ bằng 0.
	 */
	public static function dong_may_xu( $row ) {
		$warns = array();
		$row   = (array) $row;

		$c_act = self::meter_delta( self::o( $row, 'cBefore' ), self::o( $row, 'cAfter' ), 'COIN', $warns );
		$m_act = self::meter_delta( self::o( $row, 'mBefore' ), self::o( $row, 'mAfter' ), 'MONEY', $warns );

		$theo_xu   = $c_act * self::GIA_XU;
		$theo_tien = $m_act * self::GIA_XU_TIEN;

		$row['cActual']    = $c_act;
		$row['mActual']    = $m_act;
		$row['amount']     = $theo_xu;
		$row['collection'] = 0;

		if ( $theo_xu !== $theo_tien ) {
			$warns[] = self::warn( 'COIN_VS_MONEY', 'COIN ' . VHJP_Doc::money( $theo_xu )
				. 'đ vs MONEY ' . VHJP_Doc::money( $theo_tien ) . 'đ' );
		}
		$thu = VHJP_Doc::num( self::o( $row, 'cash' ) ) + VHJP_Doc::num( self::o( $row, 'bank' ) );
		if ( $thu !== $theo_xu ) {
			$warns[] = self::warn( 'VS_PAYBOX', 'Đồng hồ ' . VHJP_Doc::money( $theo_xu )
				. 'đ vs thu ' . VHJP_Doc::money( $thu ) . 'đ' );
		}

		$row['xuTong']        = 0;
		$row['soldQty']       = 0;
		$row['stockLeftCalc'] = 0;
		return self::dong_xong( $row, $warns );
	}

	/* ═══════════════════════ MÁY XU — DÒNG HÀNG TỒN THEO MÃ ═══════════════════════ */

	/** Dòng STOCK: hàng bán suy từ TỔNG XU KIỂM các ngày trong kỳ, chia cho giá xu. */
	public static function dong_ton_xu( $row ) {
		$warns = array();
		$row   = (array) $row;

		/* Danh sách xu kiểm từng ngày, cất dạng JSON. Hỏng thì coi như rỗng — một chuỗi JSON
		   gãy không được phép làm chết cả lượt tính của báo cáo. */
		$ngay = array();
		$js   = self::o( $row, 'xuDaysJson', '' );
		if ( '' !== $js && null !== $js ) {
			$d = json_decode( (string) $js, true );
			if ( is_array( $d ) ) { $ngay = $d; }
		}
		$tong = 0;
		foreach ( $ngay as $x ) { $tong += VHJP_Doc::num( $x ); }
		$row['xuTong'] = $tong;

		$gia = VHJP_Doc::num( self::o( $row, 'giaXu' ) );
		$ban = $gia > 0 ? (int) floor( $tong / $gia ) : 0;
		$row['soldQty'] = $ban;

		if ( $gia > 0 && 0 != $tong % $gia ) {
			$warns[] = self::warn( 'COIN_REMAINDER', 'Tổng xu ' . $tong
				. ' không chia hết cho giá xu ' . $gia . ' (dư ' . ( $tong % $gia ) . ')',
				$tong % $gia );
		}

		$con = VHJP_Doc::num( self::o( $row, 'stockOpen' ) )
			+ VHJP_Doc::num( self::o( $row, 'addQty1' ) ) + VHJP_Doc::num( self::o( $row, 'addQty2' ) )
			- VHJP_Doc::num( self::o( $row, 'xuLa' ) ) - $ban
			- VHJP_Doc::num( self::o( $row, 'defectQty' ) ) - VHJP_Doc::num( self::o( $row, 'returnQty' ) );
		$row['stockLeftCalc'] = $con;

		if ( ! VHJP_Doc::blank( self::o( $row, 'stockActual', '' ) ) ) {
			$dem = VHJP_Doc::num( $row['stockActual'] );
			if ( $dem !== $con ) {
				$warns[] = self::warn( 'STOCK_MISMATCH',
					'Tính ' . $con . ' · đếm ' . $dem . ' · lệch ' . ( $dem - $con ) );
			}
		}

		$row['amount'] = 0;
		/* Dòng STOCK GIỮ HÀNG nên phải qua phép W14 — xem `canh_bao_ma_khong_gia()`. */
		self::canh_bao_ma_khong_gia( $row, $warns );
		return self::dong_xong( $row, $warns );
	}

	/* ═══════════════════════ HÀNG GIỮ NGOÀI MÁY ═══════════════════════ */

	/**
	 * Dòng NGOAI: hàng cơ sở giữ ngoài máy. KHÔNG sinh tiền, KHÔNG sinh số bán.
	 *
	 * ⚠️ Ép cả `cash` và `bank` về 0. Bảng kho ngoài không có ô nhập tiền nên bình thường
	 *    chúng vốn trống — nhưng một dòng ĐỔI LOẠI (nhân viên sửa bảng) thì số tiền cũ còn
	 *    nằm lại trong ô, và không ai xoá hộ. Ép ở đây thì bất biến "không sinh tiền" đúng cả
	 *    với dữ liệu cũ, không phụ thuộc chỗ khác nhớ lọc.
	 */
	public static function dong_kho_ngoai( $row ) {
		$warns = array();
		$row   = (array) $row;

		$dau   = VHJP_Doc::num( self::o( $row, 'stockOpen' ) );
		$nhap  = VHJP_Doc::num( self::o( $row, 'addQty1' ) );
		$ra_may = VHJP_Doc::num( self::o( $row, 'stockOut' ) );
		$tra   = VHJP_Doc::num( self::o( $row, 'returnQty' ) );
		$con   = $dau + $nhap - $ra_may - $tra;

		$row['stockLeftCalc'] = $con;
		$row['soldQty'] = 0;
		$row['amount']  = 0;
		$row['xuTong']  = 0;
		$row['cash']    = 0;
		$row['bank']    = 0;

		if ( $ra_may < 0 || $nhap < 0 || $tra < 0 ) {
			$warns[] = self::warn( 'STOCK_MISMATCH', 'Có số âm trong dòng kho ngoài' );
		}
		if ( ! VHJP_Doc::blank( self::o( $row, 'stockActual', '' ) ) ) {
			$dem = VHJP_Doc::num( $row['stockActual'] );
			if ( $dem !== $con ) {
				$warns[] = self::warn( 'STOCK_MISMATCH',
					'Kho ngoài: tính ' . $con . ' (đầu ' . $dau . ' + nhập ' . $nhap
					. ' − cấp ra máy ' . $ra_may . ' − trả kho ' . $tra . ')'
					. ' · đếm ' . $dem . ' · '
					. ( $dem > $con ? 'THỪA ' . ( $dem - $con ) : 'THIẾU ' . ( $con - $dem ) ) );
			}
		}
		return self::dong_xong( $row, $warns );
	}

	/* ═══════════════════════ MẪU TÁCH — DÒNG MÁY (CHỈ TIỀN) ═══════════════════════ */

	/**
	 * Bảng này KHÔNG CÒN Ô ĐỒNG HỒ — nhân viên tự điền tiền.
	 *
	 * 🔴 `may_go_tay()` đo bằng `<= 0`, TUYỆT ĐỐI không dùng `blank()`. Ca thật 18/08/2026:
	 *    màn hình tạo ô máy mới với `mBefore = 0` (số 0 THẬT, không phải trống) -> `blank(0)`
	 *    là false -> hệ coi đó là dòng đồng hồ đời cũ và ĐÒI `mAfter` — mà bảng tách không còn
	 *    ô ấy. Kết quả: SBPQ không nộp được báo cáo nào, và không có ô nào để điền cho hết
	 *    chặn.
	 */
	public static function may_go_tay( $row ) {
		return is_array( $row ) && VHJP_Doc::num( self::o( $row, 'mBefore' ) ) <= 0
			&& VHJP_Doc::blank( self::o( $row, 'mAfter', '' ) );
	}

	public static function dong_may_tach( $row ) {
		$warns = array();
		$row   = (array) $row;

		$go_tay = self::may_go_tay( $row );
		$m_act  = $go_tay ? 0
			: self::meter_delta( self::o( $row, 'mBefore' ), self::o( $row, 'mAfter' ),
				'Đồng hồ tiền', $warns );
		$row['mActual'] = $m_act;

		if ( ! $go_tay ) {
			/* Cùng giá xung với dòng máy tiền — hai nhánh này cùng suy tiền từ đồng hồ nên
			   phải cùng một giá. Sửa một nhánh quên nhánh kia là báo cáo đời cũ tính theo giá
			   khác báo cáo mẫu chung cùng khu. */
			$row['amount'] = $m_act * self::gia_xung( self::o( $row, 'giaXung' ) );
			$row['cash']   = $row['amount'] - VHJP_Doc::num( self::o( $row, 'bank' ) );
		} else {
			/* ⚠️ `num_hoac_trong`, KHÔNG phải `num`: ô CHƯA GÕ mà hoá thành số 0 ngay trước
			   lượt lưu thì phép chặn nộp đọc lại chỉ thấy 0 nên THÔI CHẶN — mất đúng cái chốt
			   "trống là CHƯA KHAI". */
			$row['amount'] = VHJP_Doc::num_hoac_trong( self::o( $row, 'amount', '' ) );
			$row['cash']   = VHJP_Doc::num_hoac_trong( self::o( $row, 'cash', '' ) );
		}
		$row['collection'] = VHJP_Doc::num( $row['amount'] ) / 10000;
		$row['lechTM']     = self::lech_tm( $row );

		/* App của cơ sở tự cộng — kiểm xem nó cộng có khớp không. */
		if ( $go_tay && ! VHJP_Doc::blank( self::o( $row, 'amount', '' ) )
			&& ! VHJP_Doc::blank( self::o( $row, 'cash', '' ) ) ) {
			$cong = VHJP_Doc::num( $row['cash'] ) + VHJP_Doc::num( self::o( $row, 'bank' ) );
			if ( $cong !== VHJP_Doc::num( $row['amount'] ) ) {
				$warns[] = self::warn( 'LECH_TM_APP', 'App không cộng khớp: tiền mặt '
					. VHJP_Doc::money( $row['cash'] ) . 'đ + QR '
					. VHJP_Doc::money( self::o( $row, 'bank' ) ) . 'đ = '
					. VHJP_Doc::money( $cong ) . 'đ, nhưng Thành tiền ghi '
					. VHJP_Doc::money( $row['amount'] ) . 'đ' );
			}
		}
		if ( 0 != $row['lechTM'] ) {
			$warns[] = self::warn( 'LECH_TM_APP', 'Tiền mặt thực tế '
				. VHJP_Doc::money( VHJP_Doc::num( self::o( $row, 'cashReal' ) ) ) . 'đ '
				. ( $row['lechTM'] > 0 ? 'NHIỀU hơn' : 'ÍT hơn' ) . ' app '
				. VHJP_Doc::money( abs( $row['lechTM'] ) ) . 'đ' );
		}

		$gia = self::gia_dong( $row );
		$row['price'] = $gia;

		/* Chỉ soi dư khi tiền suy từ ĐỒNG HỒ. Nhân viên gõ tay thì số lẻ là chuyện của app —
		   báo dư trên mọi dòng là báo sai vài lần rồi không ai đọc cảnh báo nữa. */
		if ( $gia > 0 && ! $go_tay && $m_act > 0 ) {
			$du = $row['amount'] % $gia;
			if ( 0 != $du ) {
				$warns[] = self::warn( 'PRICE_REMAINDER', 'Dư ' . VHJP_Doc::money( $du )
					. 'đ so với giá ' . VHJP_Doc::money( $gia ) . 'đ/trứng', $du );
			}
		}

		/* Dòng MÁY ở mẫu tách CHỈ mang tiền — hàng nằm ở dòng HANG. */
		$row['soldQty']       = 0;
		$row['hLeft']         = 0;
		$row['xuTong']        = 0;
		$row['stockLeftCalc'] = 0;
		return self::dong_xong( $row, $warns );
	}

	/* ═══════════════════════ MẪU TÁCH — DÒNG HÀNG (CHỈ HÀNG) ═══════════════════════ */

	/**
	 * Nhân viên gõ ĐÃ BÁN, hệ tính TỒN CUỐI (đổi chiều 05/08/2026).
	 *
	 * 🔴 GIỮ Ô TRỐNG LÀ TRỐNG. Ghi `soldQty = num(...)` thì `''` thành số 0 ngay trước lúc
	 *    lưu, và phép chặn nộp đọc lại chỉ thấy 0 -> THÔI CHẶN. Mất đúng cái chốt "trống là
	 *    CHƯA KHAI", mà chưa khai thì kỳ sau không có tồn đầu và kho không ra được giá vốn.
	 *
	 * ⚠️ `soldQty` ở đây là số NHÂN VIÊN GÕ — số quả THẬT đã ra khỏi máy — nên phần khách
	 *    không lấy vốn đã KHÔNG nằm trong đó. Trừ `refundQty` lần nữa là xuất kho thiếu ->
	 *    632 thiếu giá vốn, mà sổ VẪN CÂN.
	 */
	public static function dong_hang_tach( $row ) {
		$warns = array();
		$row   = (array) $row;

		$gia = self::gia_dong( $row );
		$row['price'] = $gia;

		$dau  = VHJP_Doc::num( self::o( $row, 'stockOpen' ) );
		$nhap = VHJP_Doc::num( self::o( $row, 'addQty1' ) ) + VHJP_Doc::num( self::o( $row, 'addQty2' ) );
		$tra  = VHJP_Doc::num( self::o( $row, 'returnQty' ) );
		$loi  = VHJP_Doc::num( self::o( $row, 'defectQty' ) );
		$co_khai = ! VHJP_Doc::blank( self::o( $row, 'soldQty', '' ) );
		$co_dem  = ! VHJP_Doc::blank( self::o( $row, 'stockActual', '' ) );

		$hoan = self::hoan_theo_ma( $row, $gia );
		$row['refundAmt'] = $hoan['amt'];
		$row['refundQty'] = $hoan['qty'];
		if ( '' !== $hoan['canhBao'] ) { $warns[] = self::warn( 'REFUND_REMAINDER', $hoan['canhBao'] ); }
		if ( $hoan['amt'] > 0 && '' === VHJP_Doc::str( self::o( $row, 'refundRowNote' ) ) ) {
			$warns[] = self::warn( 'MISSING_REASON',
				'Hoàn khách ' . VHJP_Doc::money( $hoan['amt'] ) . 'đ ở mã này chưa có lý do' );
		}

		$ban  = VHJP_Doc::num( self::o( $row, 'soldQty' ) );
		$cuoi = $dau + $nhap - $tra - $loi - $ban;
		$row['soldQty']       = $co_khai ? $ban : '';
		$row['stockLeftCalc'] = $cuoi;

		if ( ! $co_khai ) {
			$warns[] = self::warn( 'METER_MISSING',
				'Chưa khai số đã bán — để trống là CHƯA KHAI, không phải bán được 0. '
				. 'Chưa khai thì kỳ sau không có tồn đầu và kho không ra được giá vốn.' );
		} elseif ( $cuoi < 0 ) {
			$warns[] = self::warn( 'STOCK_MISMATCH',
				'Đã bán ' . $ban . ' LỚN HƠN số có thể bán (' . ( $dau + $nhap - $tra - $loi )
				. ' = đầu ' . $dau . ' + nhập ' . $nhap . ' − trả kho ' . $tra . ' − lỗi ' . $loi
				. ') ⇒ tồn cuối âm ' . $cuoi . '. Sai một trong năm số đó.' );
		}

		if ( $co_dem ) {
			$dem  = VHJP_Doc::num( $row['stockActual'] );
			$lech = $dem - $cuoi;
			if ( 0 != $lech ) {
				$warns[] = self::warn( 'STOCK_MISMATCH',
					'Đếm thực tế ' . $dem . ' vs tồn cuối tính ra ' . $cuoi . ' (đầu ' . $dau
					. ' + nhập ' . $nhap . ' − trả kho ' . $tra . ' − lỗi ' . $loi
					. ' − bán ' . $ban . ') · '
					. ( $lech > 0 ? 'THỪA ' . $lech : 'THIẾU ' . ( -$lech ) ) );
			}
		}

		if ( $dau < 0 || $nhap < 0 || $tra < 0 || $loi < 0 || $ban < 0
			|| ( $co_dem && VHJP_Doc::num( $row['stockActual'] ) < 0 ) ) {
			$warns[] = self::warn( 'STOCK_MISMATCH', 'Có số âm trong dòng hàng' );
		}
		self::canh_bao_ma_khong_gia( $row, $warns );

		/* Dòng HÀNG ở mẫu tách CHỈ mang hàng — tiền nằm ở dòng MÁY. Cộng tiền ở đây là đếm
		   doanh thu hai lần. */
		$row['amount'] = 0;
		$row['cash']   = 0;
		$row['bank']   = 0;
		$row['xuTong'] = 0;
		$row['hLeft']  = 0;
		return self::dong_xong( $row, $warns );
	}

	/* ═══════════════════════ ĐIỀU PHỐI ═══════════════════════ */

	/**
	 * Tính MỘT dòng bất kỳ, chọn cách tính theo LOẠI DÒNG.
	 *
	 * ⚠️ Dòng chưa khai loại thì suy từ loại máy của cơ sở — máy xu ra COIN, còn lại ra
	 *    MONEY. Và GHI LẠI loại vừa suy vào dòng, để lần sau không phải đoán nữa.
	 */
	public static function dong( $row, $loai_may = '' ) {
		$row = (array) $row;
		$k   = VHJP_Doc::str( self::o( $row, 'rowKind' ) );
		if ( '' === $k ) {
			$k = ( VHJP_CauHinh::LOAI_XU === $loai_may ) ? self::DONG_COIN : self::DONG_MONEY;
		}
		$row['rowKind'] = $k;

		if ( self::DONG_NGOAI === $k ) { return self::dong_kho_ngoai( $row ); }
		if ( self::DONG_STOCK === $k ) { return self::dong_ton_xu( $row ); }
		if ( self::DONG_COIN  === $k ) { return self::dong_may_xu( $row ); }
		if ( self::DONG_HANG  === $k ) { return self::dong_hang_tach( $row ); }
		if ( self::DONG_MAY   === $k ) { return self::dong_may_tach( $row ); }
		return self::dong_may_tien( $row );
	}

	/* ═══════════════════════ TỔNG CỦA CẢ BÁO CÁO ═══════════════════════ */

	/** Tổng tiền hoàn khách = khoản chung ở đầu báo cáo + Σ khoản gắn theo từng mã. */
	public static function hoan_tong( $h ) {
		return abs( VHJP_Doc::num( self::o( $h, 'refundCustomer' ) ) )
			+ abs( VHJP_Doc::num( self::o( $h, 'refundRows' ) ) );
	}

	/**
	 * Tính TỔNG của cả báo cáo từ các dòng đã tính.
	 *
	 * =========================================================================================
	 * 🔴 `adjMachine` CHỈ ĐƯỢC ĐẶT TỪ Σ LỆCH KHI THẬT SỰ CÓ ĐẾM.
	 * =========================================================================================
	 * `lech_tm()` trả 0 cho dòng chưa đếm — nên nếu cứ đặt `adjMachine = Σ lệch` bất kể có ai
	 * đếm hay không, thì một báo cáo chưa ai đếm ô "Thực thu" nào vẫn ra tổng lệch 0 và không
	 * sao. NHƯNG chiều ngược lại mới chết: ô "Thực thu" bị hiểu là "đếm được 0" thì Σ lệch ra
	 * đúng `−(toàn bộ tiền mặt máy báo)`, và `cashActual` về 0 — MẤT TRẮNG MỘT KỲ, mà sổ vẫn
	 * cân nên không phép kiểm kế toán nào bắt được.
	 * Nên có cờ `coThucThu`: chỉ khi CÓ ít nhất một ô được đếm thật (hoặc có dòng gõ tay) mới
	 * ghi đè `adjMachine`. Không thì giữ nguyên số kế toán đã nhập tay.
	 *
	 * ⚠️ BA LOẠI DÒNG KHÔNG SINH TIỀN: `HANG` (tiền nằm ở dòng `MAY`), `STOCK` và `NGOAI`.
	 *    Cộng chúng vào là đếm doanh thu hai lần.
	 *
	 * ⚠️ `revHang` là ĐƯỜNG THỨ HAI để đem so, KHÔNG cộng vào doanh thu. Mẫu tách có cả hai
	 *    đường (tiền theo máy, và tiền suy từ hàng bán) — chỗ lệch giữa chúng mới là thứ cần
	 *    nhìn.
	 */
	public static function bao_cao( $head, $rows ) {
		$head = (array) $head;
		$rows = is_array( $rows ) ? $rows : array();

		$rev_may = 0; $rev_bank = 0; $rev_hang = 0; $co_hang = false; $hoan_dong = 0;
		foreach ( $rows as $r ) {
			$k = VHJP_Doc::str( self::o( $r, 'rowKind' ) );
			$hoan_dong += abs( VHJP_Doc::num( self::o( $r, 'refundAmt' ) ) );
			if ( self::DONG_HANG === $k ) {
				$co_hang   = true;
				$rev_hang += VHJP_Doc::num( self::o( $r, 'soldQty' ) )
					* VHJP_Doc::num( self::o( $r, 'price' ) );
				continue;                       // dòng hàng KHÔNG sinh tiền
			}
			if ( self::DONG_STOCK === $k || self::DONG_NGOAI === $k ) { continue; }
			$rev_may  += VHJP_Doc::num( self::o( $r, 'amount' ) );
			$rev_bank += VHJP_Doc::num( self::o( $r, 'bank' ) );
		}
		$rev_tien_may = $rev_may - $rev_bank;

		/* Σ lệch tiền mặt, và CÓ AI ĐẾM KHÔNG — xem khối 🔴 ở trên. */
		$lech = 0; $co_go_tay = false; $co_thuc_thu = false;
		foreach ( $rows as $r ) {
			$k = VHJP_Doc::str( self::o( $r, 'rowKind' ) );
			if ( self::DONG_MAY !== $k && self::DONG_MONEY !== $k ) { continue; }
			if ( self::DONG_MAY === $k && self::may_go_tay( $r ) ) { $co_go_tay = true; }
			if ( ! VHJP_Doc::blank( self::o( $r, 'cashReal', '' ) ) ) { $co_thuc_thu = true; }
			$lech += self::lech_tm( $r );
		}
		$head['coThucThu'] = $co_thuc_thu;
		if ( $co_go_tay || $co_thuc_thu ) { $head['adjMachine'] = $lech; }

		$adj = VHJP_Doc::num( self::o( $head, 'adjMachine' ) );

		$head['refundRows']  = $hoan_dong;
		$hoan                = self::hoan_tong( $head );
		$head['refundTotal'] = $hoan;

		$head['revMeter']     = $rev_may;
		$head['revBank']      = $rev_bank;
		$head['revCashMeter'] = $rev_tien_may;
		$head['cashActual']   = $rev_tien_may + $adj - $hoan;
		$head['totalSubmit']  = $head['cashActual'] + $rev_bank;

		$head['revHang']      = $co_hang ? $rev_hang : 0;
		$head['revMeterRong'] = $rev_may - $hoan;        // tiền đồng hồ SAU khi trừ hoàn
		$head['lechTienHang'] = $co_hang ? ( $head['revMeterRong'] - $rev_hang ) : 0;
		$head['coBangTong']   = $co_hang;

		$w = self::canh_bao_dau( $head );
		$dem = 0;
		foreach ( $rows as $r ) {
			$dem += count( isset( $r['warns'] ) && is_array( $r['warns'] ) ? $r['warns'] : array() );
		}
		$head['warnCount'] = $dem + count( $w );
		$head['warns']     = $w;
		return $head;
	}

	/**
	 * Cảnh báo ở ĐẦU báo cáo (không thuộc dòng nào).
	 *
	 * ⚠️ Tính lại `hoan_tong()` từ chính `$head` chứ không dùng trường tạm của `bao_cao()`:
	 *    hàm này còn được gọi lúc MỞ LẠI báo cáo, khi `$head` đọc thẳng từ cơ sở dữ liệu và
	 *    mấy trường tạm kia không có. Dựa vào trường tạm là câu cảnh báo lúc mở lại in THIẾU
	 *    khoản hoàn.
	 */
	public static function canh_bao_dau( $head ) {
		$w    = array();
		$adj  = VHJP_Doc::num( self::o( $head, 'adjMachine' ) );
		$hoan_chung = abs( VHJP_Doc::num( self::o( $head, 'refundCustomer' ) ) );
		$hoan_t = self::hoan_tong( $head );
		$rong   = VHJP_Doc::num( self::o( $head, 'revMeter' ) ) - $hoan_t;

		if ( 0 != $adj && '' === VHJP_Doc::str( self::o( $head, 'adjMachineNote' ) ) ) {
			$w[] = self::warn( 'MISSING_REASON',
				'Lệch máy ' . VHJP_Doc::money( $adj ) . 'đ chưa có lý do' );
		}

		/* 🔴 Kêu to khi lệch máy LỚN — phải vượt CẢ HAI ngưỡng. Chỉ một điều kiện thì cơ sở
		   nhỏ kêu suốt (rồi không ai đọc nữa) còn cơ sở lớn im lặng nuốt khoản lớn. */
		$dt = VHJP_Doc::num( self::o( $head, 'revMeter' ) );
		if ( abs( $adj ) >= self::LECH_MAY_SAN && abs( $adj ) > self::LECH_MAY_PCT * $dt ) {
			$ty = $dt > 0
				? ' (' . number_format( abs( $adj ) * 100 / $dt, 1, '.', '' ) . '% doanh thu)'
				: ' (kỳ này KHÔNG có doanh thu theo đồng hồ)';
			$w[] = self::warn( 'LECH_MAY_LON',
				'Lệch máy ' . VHJP_Doc::money( $adj ) . 'đ' . $ty . ' — '
				. ( $adj < 0
					? 'đếm được ÍT hơn máy báo, tức quỹ đang THIẾU đúng khoản này'
					: 'đếm được NHIỀU hơn máy báo, tức quỹ đang THỪA đúng khoản này' )
				. '. Số này trừ thẳng vào tiền cơ sở phải nộp và ra bút toán '
				. ( $adj < 0 ? '811' : '711' ) . ', nên soát lại trước khi ký' );
		}
		if ( 0 != $hoan_chung && '' === VHJP_Doc::str( self::o( $head, 'refundNote' ) ) ) {
			$w[] = self::warn( 'MISSING_REASON',
				'Hoàn khách ' . VHJP_Doc::money( $hoan_chung ) . 'đ chưa có lý do' );
		}

		$lech = VHJP_Doc::num( self::o( $head, 'lechTienHang' ) );
		if ( 0 != $lech ) {
			/* Câu chữ này là NGUỒN DUY NHẤT — dùng cho cả lúc lưu lẫn lúc mở lại, ở cả hai
			   web. Sai ở đây là sai ở cả hai. Và phải nêu khoản hoàn đứng giữa: thấy "tiền
			   5.000.000đ vs hàng 4.650.000đ" mà báo lệch 150.000đ thì người đọc tưởng bảng
			   sai, phải thấy khoản hoàn 200.000đ mới cộng ra được. */
			$nguon = VHJP_CauHinh::bc_mau( self::o( $head, 'bcMau' ) ) === VHJP_CauHinh::MAU_TACH
				? 'tiền APP' : 'tiền đồng hồ';
			$w[] = self::warn( 'MONEY_MISMATCH',
				'Bảng tổng LỆCH ' . VHJP_Doc::money( abs( $lech ) ) . 'đ · ' . $nguon . ' '
				. VHJP_Doc::money( VHJP_Doc::num( self::o( $head, 'revMeter' ) ) ) . 'đ'
				. ( $hoan_t > 0
					? ' − hoàn khách ' . VHJP_Doc::money( $hoan_t ) . 'đ = '
						. VHJP_Doc::money( $rong ) . 'đ' : '' )
				. ' vs tiền theo hàng đếm được '
				. VHJP_Doc::money( VHJP_Doc::num( self::o( $head, 'revHang' ) ) ) . 'đ ⇒ '
				. ( $lech > 0
					? 'THIẾU HÀNG (tiền thu nhiều hơn hàng ra)'
					: 'THIẾU TIỀN (hàng ra nhiều hơn tiền thu)' ) );
		}
		return $w;
	}

	/** Đóng gói cảnh báo vào dòng — dùng chung cho mọi loại dòng. */
	private static function dong_xong( $row, $warns ) {
		$row['warns']    = $warns;
		$row['warnJson'] = $warns ? wp_json_encode( $warns, JSON_UNESCAPED_UNICODE ) : '';
		return $row;
	}

	/** Đọc một ô của dòng, không có thì trả mặc định. */
	private static function o( $row, $k, $mac_dinh = '' ) {
		return is_array( $row ) && array_key_exists( $k, $row ) ? $row[ $k ] : $mac_dinh;
	}
}
