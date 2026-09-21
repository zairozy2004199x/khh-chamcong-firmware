<?php
/**
 * ĐỌC GÓI TIỀN VÀO — bản dịch `_extractEvents` / `_eventFromRow` / `_pick` / `_num` của Code.gs.
 *
 * =============================================================================================
 * VÌ SAO PHẦN NÀY TÁCH RA MỘT TỆP RIÊNG, VÀ KHÔNG CHẠM CƠ SỞ DỮ LIỆU
 * =============================================================================================
 * Đây là chỗ quyết định "gói này là bao nhiêu tiền, của máy nào" — tức chỗ quyết định TIỀN. Nó
 * phải thử được bằng con số, không cần bảng, không cần HTTP. Mọi hàm ở đây là hàm THUẦN.
 *
 * =============================================================================================
 * BA BÊN GỬI, BA KIỂU GÓI — VÀ KHÔNG BÊN NÀO BÁO TRƯỚC KHI ĐỔI
 * =============================================================================================
 *   SePay   : JSON có tên trường (transferAmount, content, referenceCode…)
 *   VietQR  : JSON có tên trường, nhưng tên KHÁC (amount, description, reference…)
 *   Tingo   : JSON `{values:[[cột0, cột1, …]]}` — MẢNG, không có tên trường nào cả
 *
 * Nên phần đọc phải rộng: dò theo NHIỀU tên có thể, và với gói mảng thì đoán theo HÌNH DẠNG ô.
 * Đoán thì có lúc sai — nên mọi gói đều được ghi nhật ký kèm thân thô (xem class-vhg-cong.php).
 * Rộng ở chỗ ĐỌC, chặt ở chỗ GHI: đó là cách duy nhất vừa không mất tiền vừa không đếm nhầm.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHG_Doc {

	/**
	 * Số tiền từ một ô bất kỳ. "20.000", "20,000", "20000đ", 20000 -> 20000.
	 * ⚠️ Bỏ MỌI ký tự không phải chữ số (giữ dấu trừ). Người Việt viết 20.000 còn máy Anh-Mỹ
	 *    viết 20,000 — coi dấu chấm là thập phân ở đây là biến 20.000đ thành 20đ.
	 */
	public static function so( $v ) {
		if ( null === $v || '' === $v ) { return 0; }
		if ( is_int( $v ) || is_float( $v ) ) { return (int) $v; }
		$s = preg_replace( '/[^0-9-]/', '', (string) $v );
		return ( '' === $s || '-' === $s ) ? 0 : (int) $s;
	}

	/** Giá trị đầu tiên tồn tại theo danh sách tên, dò trên nhiều nhánh (kể cả lồng nhau). */
	public static function lay( $nhanh, $ten ) {
		foreach ( $nhanh as $o ) {
			if ( ! is_array( $o ) ) { continue; }
			foreach ( $ten as $k ) {
				if ( isset( $o[ $k ] ) && '' !== $o[ $k ] && null !== $o[ $k ] ) { return $o[ $k ]; }
			}
		}
		return '';
	}

	/**
	 * Tách gói thành danh sách giao dịch. Trả mảng các
	 * array( so_tien, noi_dung, ref, ten_khai, tien_ra ).
	 */
	public static function tach( $goi ) {
		$ra = array();
		if ( ! is_array( $goi ) ) { return $ra; }

		/* --- Dạng Tingo: {values:[[...],[...]]} --- */
		if ( isset( $goi['values'] ) && is_array( $goi['values'] ) && isset( $goi['values'][0] )
			&& is_array( $goi['values'][0] ) ) {
			foreach ( $goi['values'] as $hang ) {
				$ev = self::tu_hang( $hang );
				if ( $ev ) { $ra[] = $ev; }
			}
			if ( $ra ) { return $ra; }
		}

		/* --- Dạng có tên trường: SePay / VietQR, kể cả lồng trong data/transaction/... --- */
		$nhanh = array( $goi );
		foreach ( array( 'data', 'transaction', 'payload', 'result', 'body' ) as $k ) {
			if ( isset( $goi[ $k ] ) && is_array( $goi[ $k ] ) ) { $nhanh[] = $goi[ $k ]; }
		}
		$tien = self::so( self::lay( $nhanh, array( 'transferAmount', 'amount', 'creditAmount',
			'amountIn', 'value', 'money', 'transactionAmount', 'totalAmount', 'payAmount',
			'orderAmount', 'amountValue', 'netAmount', 'price', 'soTien' ) ) );
		$nd = (string) self::lay( $nhanh, array( 'content', 'description', 'addInfo', 'note',
			'comment', 'remark', 'transactionContent', 'orderInfo', 'message', 'detail',
			'contentPayment', 'noiDung', 'ndct' ) );
		$ref = (string) self::lay( $nhanh, array( 'referenceCode', 'reference', 'id', 'tid',
			'transactionId', 'refId', 'ftCode', 'transactionCode', 'orderCode', 'orderId',
			'partnerRefId', 'requestId', 'maThamChieu' ) );
		$ten = (string) self::lay( $nhanh, array( 'storeName', 'shopName', 'merchantName',
			'tenCuaHang', 'tenCH', 'store', 'terminal', 'terminalName', 'posName', 'maCuaHang' ) );
		$huong = strtolower( (string) self::lay( $nhanh, array( 'transferType', 'type', 'direction' ) ) );
		/* GIỜ CỦA BÊN GỬI. SePay gửi `transactionDate` ("2026-08-22 20:08:26" — giờ Việt Nam).
		   Lấy được thì dùng, vì đó là giờ trên sao kê ngân hàng, tức giờ người ta đối soát. Giờ
		   máy chủ chỉ là phương án cuối: WordPress mặc định chạy múi giờ UTC, lệch 7 tiếng so
		   với sao kê, và không ai nhìn ra điều đó cho tới lúc phải khớp từng dòng. */
		/* SỐ TÀI KHOẢN NHẬN, theo lời BÊN GỬI. Đây là SỰ THẬT ĐỐI CHỨNG duy nhất cho ô "tài
		   khoản nhận tiền" khai tay ở màn quản trị — xem VHG_May::doi_chieu_tk(). */
		/* 🔴 TÁCH RIÊNG SỐ TÀI KHOẢN VÀ TÀI KHOẢN ẢO (VA). Bản trước gộp làm một danh sách nên
		 *    `accountNumber` luôn thắng và VA bị vứt — mất đúng thông tin cần để trả lời câu
		 *    "mã QR phải trỏ vào cái nào".
		 *
		 *    Ngày 22/08/2026 anh Thắng quét thử: ngân hàng trừ tiền bình thường, SePay KHÔNG
		 *    thấy giao dịch nào. Firmware gốc của hệ thống cũ trỏ vào VA (`96247POSH`), còn mã
		 *    QR mới trỏ vào số tài khoản thường — hai đích khác nhau, và chỉ một trong hai là
		 *    cái SePay theo dõi. Không tách hai ô này thì không có cách nào thấy điều đó. */
		$tk_nhan = trim( (string) self::lay( $nhanh, array( 'accountNumber', 'account_number',
			'accountNo', 'soTaiKhoan', 'stk' ) ) );
		$tk_ao   = trim( (string) self::lay( $nhanh, array( 'subAccount', 'sub_account',
			'virtualAccount', 'va', 'taiKhoanAo' ) ) );
		if ( '' === $tk_nhan ) { $tk_nhan = $tk_ao; }
		$luc = VHG_Doc::ngay( (string) self::lay( $nhanh, array( 'transactionDate', 'transaction_date',
			'transTime', 'transactionTime', 'when', 'payDate', 'paidAt', 'createdAt', 'created_at',
			'thoiGian', 'ngayGiaoDich' ) ) );

		/* Gói RỖNG HẲN thì đừng đẻ ra một giao dịch 0 đồng.
		   ⚠️ Bản Apps Script luôn `push` ở nhánh này, nên MỌI request lạ (bot dò đường, lượt kiểm
		      tra sức khoẻ của bên gửi) đều sinh một dòng "bỏ qua" trong nhật ký. Nhật ký đó chính
		      là chỗ người ta soi khi tiền không về — làm nhiễu nó là làm hỏng công cụ chẩn đoán. */
		if ( 0 === $tien && '' === $nd && '' === $ref ) { return $ra; }

		$ra[] = array(
			'so_tien'  => abs( $tien ),
			'noi_dung' => $nd,
			'ref'      => $ref,
			'ten_khai' => $ten,
			'tk_nhan'  => $tk_nhan,
			'tk_ao'    => $tk_ao,
			'luc'      => $luc,
			'tien_ra'  => ( 'out' === $huong || 'debit' === $huong || $tien < 0 ),
		);
		return $ra;
	}

	/**
	 * Đọc MỘT hàng của gói mảng (Tingo) bằng hình dạng ô — thứ tự cột đổi được theo cấu hình
	 * bên gửi, nên không thể đếm theo chỉ số.
	 */
	public static function tu_hang( $hang ) {
		if ( ! is_array( $hang ) || ! $hang ) { return null; }
		$tien = 0; $nd = ''; $ref = ''; $ten = ''; $ra_tien = false;

		foreach ( $hang as $o ) {
			$o = trim( (string) $o );
			if ( '' === $o ) { continue; }
			$thuong = mb_strtolower( $o, 'UTF-8' );

			/* Hướng tiền. "Giao dịch đi" / "chuyển tiền đi" -> TIỀN RA, không phải doanh thu. */
			foreach ( array( 'giao dịch đi', 'giao dich di', 'chuyển tiền đi', 'chuyen tien di', 'tiền đi' ) as $x ) {
				if ( false !== mb_strpos( $thuong, $x ) ) { $ra_tien = true; }
			}
			/* Ô "Loại giao dịch" — KHÔNG được lấy làm nội dung hay tên máy. */
			$la_loai = (bool) preg_match( '/giao\s*d[ịi]ch|chuy[eể]n\s*ti[eề]n/ui', $o );

			$n = self::so( $o );
			/* Số tiền: ô toàn chữ số (cho phép . ,) và >= 1000. Lấy giá trị LỚN NHẤT để bỏ cột
			   số thứ tự — cột STT luôn nhỏ, còn tiền thì không bao giờ dưới 1000. */
			if ( preg_match( '/^[0-9][0-9.,]*$/', $o ) && $n >= 1000 && $n > $tien ) { $tien = $n; }

			/* Nội dung: ô DÀI NHẤT có khoảng trắng + chữ, không phải ngày/giờ/tên ngân hàng. */
			if ( ! $la_loai && false !== strpos( $o, ' ' ) && preg_match( '/[A-Za-z]/', $o )
				&& false === strpos( $o, '/' ) && false === strpos( $o, ':' )
				&& ! preg_match( '/BIDV|VCB|VIETCOMBANK|MBBANK|\bMB\b|ACB|TCB|VPB|AGRIBANK|SACOMBANK|BANK/i', $o )
				&& mb_strlen( $o, 'UTF-8' ) > mb_strlen( $nd, 'UTF-8' ) ) { $nd = $o; }

			/* Mã tham chiếu: có '-', không khoảng trắng, gồm cả chữ lẫn số. */
			if ( '' === $ref && false !== strpos( $o, '-' ) && false === strpos( $o, ' ' )
				&& preg_match( '/[A-Za-z]/', $o ) && preg_match( '/[0-9]/', $o ) ) { $ref = $o; }

			/* Tên máy ở cột riêng: "VC GP 08", "AMTP 03" — chữ, kết thúc bằng số, ngắn. */
			if ( '' === $ten && ! $la_loai && mb_strlen( $o, 'UTF-8' ) <= 25
				&& preg_match( '/[A-Za-zÀ-ỹ]/u', $o ) && preg_match( '/\s[0-9]+$/', $o )
				&& ! preg_match( '/^VQR/i', $o ) && false === strpos( $o, '/' ) && false === strpos( $o, ':' )
				&& ! preg_match( '/BIDV|BANK/i', $o ) ) { $ten = $o; }
		}

		if ( $tien <= 0 && '' === $nd && '' === $ref ) { return null; }
		return array( 'so_tien' => $tien, 'noi_dung' => $nd, 'ref' => $ref,
			'ten_khai' => $ten, 'tien_ra' => $ra_tien );
	}

	/**
	 * Tên máy tách từ nội dung chuyển khoản.
	 * "VQR26310A58CRGN7 AMTP 03" -> "AMTP 03". Bỏ tiền tố VQR…, bỏ nội dung chung vô nghĩa.
	 */
	public static function ten_may( $noi_dung ) {
		$s = trim( (string) $noi_dung );
		$s = trim( preg_replace( '/^VQR\S*\s+/i', '', $s ) );
		if ( '' === $s || preg_match( '/^payment\s*for\s*order$/i', $s ) ) { return ''; }
		return self::chuan_ten( $s );
	}

	/** Chuẩn hoá tên máy: viết HOA, gộp khoảng trắng. Một tên một dạng, để gộp nhóm không lệch. */
	public static function chuan_ten( $s ) {
		$s = trim( (string) $s );
		return '' === $s ? '' : preg_replace( '/\s+/', ' ', mb_strtoupper( $s, 'UTF-8' ) );
	}

	/**
	 * Cơ sở suy từ tên máy: bỏ số máy ở cuối. "AMTP 03" -> "AMTP"; "VC GP 08" -> "VC GP".
	 * ⚠️ Đây là phép ĐOÁN, chỉ dùng khi máy chưa được khai trong bảng `may`. Máy đã khai thì cơ
	 *    sở lấy theo bảng — người khai luôn đúng hơn phép đoán từ chuỗi.
	 */
	public static function coso_tu_ten( $ten_may ) {
		$s = trim( (string) $ten_may );
		if ( preg_match( '/^(.*?)\s*[0-9]+(?:[.,][0-9]+)?$/', $s, $m ) && '' !== trim( $m[1] ) ) {
			return trim( $m[1] );
		}
		return $s;
	}

	/**
	 * Mã ghế + mã lượt từ nội dung chuẩn ta đặt trong QR: "GHE<ghế> <mã>".
	 * Trả array( ma_may, ma_lenh ); rỗng nếu không khớp.
	 */
	public static function ghe_va_ma( $noi_dung ) {
		$s = mb_strtoupper( (string) $noi_dung, 'UTF-8' );
		if ( preg_match( '/GHE\s*([A-Z0-9]+)\s+([A-Z0-9]+)/u', $s, $m ) ) {
			return array( $m[1], $m[2] );
		}
		return array( '', '' );
	}

	/**
	 * Nội dung của một đơn MUA MÃ -> mã đơn. Rỗng nếu không phải đơn mua mã.
	 *
	 * ⚠️ ĐÒI RANH GIỚI TỪ trước "MUA". Không có nó thì `GHEAMUA…` hay tên người "THANH MUA" cũng
	 *    khớp, và một lượt tiền của ghế bị đem đi phát mã. Ngân hàng chèn "CT DEN:<mã> " vào đầu
	 *    nên chuỗi tới nơi luôn có khoảng trắng trước phần mình dựng.
	 * ⚠️ Và mã đơn phải ĐÚNG 6 ký tự trong bảng chữ mình sinh — nới ra là mọi chuỗi hoa sau chữ
	 *    MUA đều thành "mã đơn", rồi tra không thấy và lượt tiền đó rơi vào im lặng.
	 */
	public static function don_mua( $noi_dung ) {
		$s = mb_strtoupper( (string) $noi_dung, 'UTF-8' );
		if ( preg_match( '/(?:^|[^A-Z0-9])MUA\s*([A-Z0-9]{6})(?![A-Z0-9])/u', $s, $m ) ) {
			return $m[1];
		}
		return '';
	}

	/**
	 * Ngày kiểu Tingo "dd-MM-yyyy HH:mm:ss" hoặc "dd/MM/yyyy HH:mm" -> "yyyy-mm-dd HH:ii:ss".
	 * ⚠️ KHÔNG đoán bừa: chuỗi không đúng khuôn thì trả rỗng để nơi gọi lấy giờ máy chủ. Đoán sai
	 *    ngày là giao dịch rơi sang tháng khác, và bảng đối soát tháng đó sai mà không ai thấy.
	 */
	public static function ngay( $s ) {
		$s = trim( (string) $s );
		if ( preg_match( '/^(\d{1,2})[-\/](\d{1,2})[-\/](\d{4})[ T](\d{1,2}):(\d{2})(?::(\d{2}))?$/', $s, $m ) ) {
			return sprintf( '%04d-%02d-%02d %02d:%02d:%02d',
				$m[3], $m[2], $m[1], $m[4], $m[5], isset( $m[6] ) ? $m[6] : 0 );
		}
		if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})[ T](\d{1,2}):(\d{2})(?::(\d{2}))?/', $s, $m ) ) {
			return sprintf( '%04d-%02d-%02d %02d:%02d:%02d',
				$m[1], $m[2], $m[3], $m[4], $m[5], isset( $m[6] ) ? $m[6] : 0 );
		}
		return '';
	}
}
