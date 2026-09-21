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
					. 'và nếu mã không có trong danh mục thì kho không tìm được lớp tồn' ),
			'LECH_QUY_TRUNG'   => array( 'code' => 'W16', 'part' => 'REV',
				'msg' => 'Tiền thừa đã được quy ra số trứng bán thêm' ),
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

	/** Đọc một ô của dòng, không có thì trả mặc định. */
	private static function o( $row, $k, $mac_dinh = '' ) {
		return is_array( $row ) && array_key_exists( $k, $row ) ? $row[ $k ] : $mac_dinh;
	}
}
