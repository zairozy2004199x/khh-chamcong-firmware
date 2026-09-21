<?php
/**
 * DỰNG CHUỖI VietQR (EMVCo) — bản dịch `buildVietQR` / `_tlv` / `_crc16` của Code.gs.
 *
 * Chuỗi này in ra tem dán trên ghế. In sai một ký tự là khách quét không ra, hoặc tệ hơn: ra
 * SỐ TIỀN KHÁC. Nên phần này là hàm thuần, và có phép thử bằng con số.
 *
 * ⚠️ CRC16/CCITT-FALSE, khởi tạo 0xFFFF, đa thức 0x1021, KHÔNG đảo bit, KHÔNG xor cuối. Đây là
 *    biến thể EMVCo dùng; các biến thể CRC16 khác cho ra chuỗi khác và điện thoại từ chối quét.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHG_QR {

	/** Một trường EMVCo: mã 2 số + độ dài 2 số + giá trị. */
	public static function tlv( $ma, $gia_tri ) {
		$gia_tri = (string) $gia_tri;
		return $ma . substr( '0' . strlen( $gia_tri ), -2 ) . $gia_tri;
	}

	public static function crc16( $s ) {
		$crc = 0xFFFF;
		$n = strlen( $s );
		for ( $i = 0; $i < $n; $i++ ) {
			$crc ^= ( ord( $s[ $i ] ) & 0xFF ) << 8;
			for ( $b = 0; $b < 8; $b++ ) {
				$crc = ( $crc & 0x8000 ) ? ( ( $crc << 1 ) ^ 0x1021 ) : ( $crc << 1 );
				$crc &= 0xFFFF;
			}
		}
		return substr( '000' . strtoupper( dechex( $crc ) ), -4 );
	}

	/**
	 * Chuỗi VietQR chuyển khoản nhanh (QRIBFTTA).
	 * `$so_tien` = 0 nghĩa là QR để khách tự nhập số tiền.
	 */
	public static function dung( $bank_bin, $so_tk, $so_tien, $noi_dung ) {
		$s  = self::tlv( '00', '01' );
		$s .= self::tlv( '01', $so_tien ? '12' : '11' );   // 12 = QR dùng MỘT LẦN (có số tiền)
		$ben = self::tlv( '00', (string) $bank_bin ) . self::tlv( '01', (string) $so_tk );
		$s .= self::tlv( '38', self::tlv( '00', 'A000000727' ) . self::tlv( '01', $ben )
			. self::tlv( '02', 'QRIBFTTA' ) );
		$s .= self::tlv( '53', '704' );                    // 704 = VND
		if ( $so_tien ) { $s .= self::tlv( '54', (string) (int) $so_tien ); }
		$s .= self::tlv( '58', 'VN' );
		if ( '' !== (string) $noi_dung ) { $s .= self::tlv( '62', self::tlv( '08', (string) $noi_dung ) ); }
		$s .= '6304';
		return $s . self::crc16( $s );
	}

	/**
	 * Tên ngân hàng theo mã BIN của Napas.
	 *
	 * Chỉ liệt kê những ngân hàng thật sự dùng trong hệ thống này, kể cả ngân hàng phát hành
	 * tài khoản ảo. Không có trong bảng thì trả rỗng — thà nói "không rõ" còn hơn đoán tên một
	 * ngân hàng, vì người đọc sẽ tin cái tên đó và thôi không đi kiểm.
	 */
	const NGAN_HANG = array(
		'970418' => 'BIDV',        '970436' => 'Vietcombank', '970415' => 'VietinBank',
		'970422' => 'MB',          '970407' => 'Techcombank', '970416' => 'ACB',
		'970448' => 'OCB',         '970432' => 'VPBank',      '970405' => 'Agribank',
		'970423' => 'TPBank',      '970443' => 'SHB',         '970441' => 'VIB',
		'970426' => 'MSB',         '970429' => 'SCB',         '970403' => 'Sacombank',
		'970431' => 'Eximbank',    '970437' => 'HDBank',      '970454' => 'VietCapitalBank',
		'970400' => 'SaigonBank',  '970419' => 'NCB',         '970428' => 'NamABank',
	);

	public static function ten_ngan_hang( $bin ) {
		$bin = preg_replace( '/\D+/', '', (string) $bin );
		return isset( self::NGAN_HANG[ $bin ] ) ? self::NGAN_HANG[ $bin ] : '';
	}

	/**
	 * ĐỌC NGƯỢC một chuỗi VietQR ra từng trường.
	 *
	 * 🔴 Vì sao cần. Ngày 22/08/2026 anh Thắng quét thử ba lần, ba lỗi khác nhau từ app BIDV:
	 *    "sai định dạng tài khoản (174)", rồi "vấn tin bị timeout (199)". Mỗi lần chỉ biết là
	 *    HỎNG, không biết trong mã có gì. Mà chuỗi QR là 130 ký tự dính liền — nhìn bằng mắt
	 *    thì không đọc ra nổi số tài khoản nằm ở đâu, chứ đừng nói đối chiếu.
	 *
	 *    Mỗi lượt thử như vậy là một lượt chuyển tiền thật và một chuyến ra chỗ để ghế. Đọc
	 *    ngược ngay trên màn quản trị thì kiểm được TRƯỚC khi đi.
	 *
	 * ⚠️ Kiểm luôn CRC. Chuỗi sai CRC thì mọi app ngân hàng đều từ chối, và đó là lỗi của phép
	 *    dựng chứ không phải của số tài khoản — hai ca đi sửa ở hai nơi khác hẳn.
	 *
	 * @return array [ 'ok', 'bin', 'so_tk', 'so_tien', 'noi_dung', 'crc_dung', 'loai', 'loi' ]
	 */
	public static function doc( $chuoi ) {
		$s  = trim( (string) $chuoi );
		$ra = array( 'ok' => false, 'bin' => '', 'so_tk' => '', 'so_tien' => 0,
			'noi_dung' => '', 'crc_dung' => false, 'loai' => '', 'loi' => '' );
		if ( strlen( $s ) < 8 ) { $ra['loi'] = 'Chuỗi quá ngắn.'; return $ra; }

		/* CRC: bốn ký tự cuối, tính trên toàn bộ phần trước KỂ CẢ "6304". */
		$than = substr( $s, 0, -4 );
		$ra['crc_dung'] = ( strtoupper( substr( $s, -4 ) ) === self::crc16( $than ) );

		$cay = self::tach_tlv( $s );
		if ( ! $cay ) { $ra['loi'] = 'Không đọc được cấu trúc TLV.'; return $ra; }

		$ra['loai']     = isset( $cay['01'] ) ? $cay['01'] : '';
		$ra['so_tien']  = isset( $cay['54'] ) ? (int) $cay['54'] : 0;
		if ( isset( $cay['62'] ) ) {
			$c62 = self::tach_tlv( $cay['62'], false );
			$ra['noi_dung'] = isset( $c62['08'] ) ? $c62['08'] : '';
		}
		if ( isset( $cay['38'] ) ) {
			$c38 = self::tach_tlv( $cay['38'], false );
			if ( isset( $c38['01'] ) ) {
				$ben = self::tach_tlv( $c38['01'], false );
				$ra['bin']   = isset( $ben['00'] ) ? $ben['00'] : '';
				$ra['so_tk'] = isset( $ben['01'] ) ? $ben['01'] : '';
			}
		}
		$ra['ok'] = ( '' !== $ra['bin'] && '' !== $ra['so_tk'] );
		if ( ! $ra['ok'] ) { $ra['loi'] = 'Không thấy mã ngân hàng hoặc số tài khoản trong mã.'; }
		return $ra;
	}

	/**
	 * Tách một chuỗi TLV thành [ mã => giá trị ].
	 * `$bo_crc` = bỏ bốn ký tự CRC ở cuối (chỉ đúng với chuỗi ngoài cùng).
	 *
	 * ⚠️ Chuỗi hỏng thì DỪNG và trả về những gì đọc được, đừng chạy tiếp: độ dài sai một ký tự
	 *    là mọi trường sau đó lệch hết, và những giá trị lệch đó trông vẫn như dữ liệu thật.
	 */
	private static function tach_tlv( $s, $bo_crc = true ) {
		$s = (string) $s;
		if ( $bo_crc && strlen( $s ) > 4 ) { $s = substr( $s, 0, -4 ); }
		$ra = array();
		$i  = 0;
		$n  = strlen( $s );
		while ( $i + 4 <= $n ) {
			$ma  = substr( $s, $i, 2 );
			$dai = substr( $s, $i + 2, 2 );
			if ( ! ctype_digit( $ma ) || ! ctype_digit( $dai ) ) { break; }
			$dai = (int) $dai;
			if ( $i + 4 + $dai > $n ) { break; }
			$ra[ $ma ] = substr( $s, $i + 4, $dai );
			$i += 4 + $dai;
		}
		return $ra;
	}

	/**
	 * Chuỗi QR cho một ghế, kèm mã lượt.
	 *
	 * ⚠️ Nội dung phải đúng khuôn `GHE<mã ghế> <mã lượt>` — đó là thứ `VHG_Doc::ghe_va_ma()` đọc
	 *    lại khi tiền về. Đổi khuôn ở một bên mà quên bên kia là tiền vào mà ghế không chạy.
	 *
	 * `$so_tien` = 0 nghĩa là "lấy tỉ lệ của ghế" (chuỗi mẫu trong màn quản trị). Trang web đặt
	 * một lượt theo MỆNH GIÁ khách chọn thì truyền con số đó vào — cùng một khuôn nội dung, nên
	 * webhook chạy ghế bằng ĐÚNG đoạn mã đang chạy cho QR trên màn ghế, không thêm nhánh nào ở
	 * chỗ tiền đi vào. Khác biệt duy nhất: mình BIẾT TRƯỚC mã lượt đó của ai, và chỉ nhờ vậy mới
	 * tích lượt ưu đãi được (xem VHG_Vi::dat_ghe / tich_don_ghe).
	 */
	public static function cho_ghe( $ma_may, $ma_lenh = '', $so_tien = 0 ) {
		$m = VHG_May::may( $ma_may );
		if ( ! $m ) { return array( 'ok' => false, 'error' => 'Chưa khai máy ' . $ma_may . '.' ); }
		/* 🔴 GHẾ CHƯA GÁN MÃ THÌ KHÔNG DỰNG QR ĐƯỢC — và phải nói ra, không được dựng bừa.
		 *
		 * Mã tạm bắt đầu bằng `?`, nên nội dung sẽ là `GHE?DD9858 K7M2P`. Phép đọc ngược
		 * (`VHG_Doc::ghe_va_ma`) khớp `GHE` rồi đòi ngay chữ-hoặc-số, gặp `?` là trượt — trả về
		 * rỗng. Nghĩa là: khách quét, tiền VÀO THẬT, mà máy chủ không biết của ghế nào và ghế
		 * không bao giờ chạy. Tiền không mất (vẫn vào sổ) nhưng khách đứng đó không được massage.
		 *
		 * Firmware đã chặn ở đầu kia (màn hiện "GHE CHUA DUOC GAN MA", không cho bấm chọn gói),
		 * nhưng máy chủ KHÔNG được dựa vào đó: một bản firmware cũ, hay chính bảng quản trị gọi
		 * hàm này để xem trước, là lại có một chuỗi QR hỏng trông y như thật. */
		if ( '' !== (string) $m['ma'] && '?' === $m['ma'][0] ) {
			return array( 'ok' => false, 'error' => 'Ghế này chưa được gán mã (' . $m['ma'] . '). '
				. 'Gán mã ở mục "Ghế chờ gán mã" rồi mới có QR — mã tạm không đọc ngược được khi '
				. 'tiền về.' );
		}
		/* Tài khoản nhận tiền khai MỘT LẦN cho cả hệ thống; ô của từng ghế chỉ là ngoại lệ.
		   Xem VHG_May::nhan_tien_cua(). */
		$tk = VHG_May::nhan_tien_cua( $m );
		if ( '' === $tk['so_tk'] ) {
			return array( 'ok' => false, 'error' => 'Chưa khai số tài khoản/VA nhận tiền — khai một lần '
				. 'ở mục "Tài khoản nhận tiền (dùng chung)" trong màn Máy & cơ sở.' );
		}
		if ( '' === $tk['bin'] ) {
			return array( 'ok' => false, 'error' => 'Chưa khai mã ngân hàng (BIN) — khai một lần ở mục '
				. '"Tài khoản nhận tiền (dùng chung)" trong màn Máy & cơ sở.' );
		}
		$ma_lenh = '' !== $ma_lenh ? $ma_lenh : self::ma_luot();
		$nd = self::noi_dung( $m['ma'], $ma_lenh );
		/* Số tiền: KHÁCH CHỌN MỆNH GIÁ thì lấy đúng con số đó (đơn `loai='ghe'` đặt từ trang
		   web — xem VHG_Vi::dat_ghe). Không truyền thì rơi về tỉ lệ THỰC DÙNG của ghế (riêng
		   nếu có, không thì chung) như chuỗi mẫu trong màn quản trị vẫn dùng.
		   ⚠️ Lấy thẳng cột `gia` là ghế dùng chung ra số 0, và một mã QR 0 đồng thì quét ra lỗi. */
		$tien = (int) $so_tien;
		if ( $tien <= 0 ) {
			$tl   = VHG_May::ty_le_cua( $m );
			$tien = (int) $tl['gia'];
		}
		/* Trả kèm TÀI KHOẢN THẬT ĐÃ DÙNG để dựng chuỗi. Nơi gọi cần in số tài khoản ra cho ai
		   muốn chuyển tay, và ghế này có thể đang dùng tài khoản riêng chứ không phải tài khoản
		   chung — hỏi lại `nhan_tien_cua()` ở đầu kia là có ngày hai bên trả lời khác nhau, tức
		   là mã QR một đằng còn dòng chữ bên dưới nó một nẻo. */
		return array( 'ok' => true, 'chuoi' => self::dung( $tk['bin'], $tk['so_tk'], $tien, $nd ),
			'noi_dung' => $nd, 'so_tien' => $tien, 'ma_lenh' => $ma_lenh,
			'so_tk' => $tk['so_tk'], 'ten_tk' => $tk['ten_tk'], 'bin' => $tk['bin'] );
	}

	/**
	 * Nội dung chuyển khoản của một lượt: `<tiền tố> GHE<ghế> <mã lượt>`.
	 *
	 * Tiền tố đứng TRƯỚC. Ngân hàng nào cắt bớt nội dung thì cắt từ cuối, mà tiền tố là thứ
	 * quyết định SePay có THẤY giao dịch hay không — mất nó là mất cả lượt (không có webhook,
	 * không có dòng nào trong sổ), còn mất mã lượt thì vẫn gán tay được.
	 *
	 * ⚠️ VietQR chỉ cho 25 ký tự ở ô nội dung. Dài hơn là ngân hàng cắt, và cắt ở đâu thì tuỳ
	 *    ngân hàng — nên `canh_bao_dai()` phải nói ra TRƯỚC khi ai đó đặt mã ghế 20 ký tự.
	 */
	const ND_TOI_DA = 25;

	public static function noi_dung( $ma_may, $ma_lenh ) {
		$t = VHG_May::tien_to_nd();
		return ( '' !== $t ? $t . ' ' : '' ) . 'GHE' . $ma_may . ' ' . $ma_lenh;
	}

	/**
	 * Nội dung chuyển khoản của một ĐƠN MUA MÃ TRƯỚC: "<tiền tố> MUA<mã đơn>".
	 * Ngắn hơn hẳn nội dung của ghế nên không lo trần 25 ký tự — "SEVQR MUAK7M2PQ" là 15.
	 */
	public static function noi_dung_mua( $ma_don ) {
		$t = VHG_May::tien_to_nd();
		return ( '' !== $t ? $t . ' ' : '' ) . VHG_Ma::ND_MUA . strtoupper( trim( (string) $ma_don ) );
	}

	/**
	 * QR trả tiền cho một đơn mua mã. Dùng chung tài khoản nhận của hệ thống.
	 * ⚠️ Đi qua đúng `nhan_tien_cua()` như QR của ghế, nên chốt "chưa khai tài khoản thì không
	 *    dựng QR" áp dụng luôn — không có đường tắt nào dựng ra một mã QR không nhận được tiền.
	 */
	public static function cho_don_mua( $ma_don, $so_tien ) {
		$tk = VHG_May::nhan_tien_cua( array() );
		if ( '' === $tk['so_tk'] || '' === $tk['bin'] ) {
			return array( 'ok' => false, 'error' => 'Chưa khai tài khoản nhận tiền — vào Ghế Massage '
				. '→ Máy &amp; cơ sở để khai.' );
		}
		$tien = (int) $so_tien;
		if ( $tien <= 0 ) { return array( 'ok' => false, 'error' => 'Số tiền phải lớn hơn 0.' ); }
		$nd = self::noi_dung_mua( $ma_don );
		return array( 'ok' => true, 'chuoi' => self::dung( $tk['bin'], $tk['so_tk'], $tien, $nd ),
			'noi_dung' => $nd, 'so_tien' => $tien, 'so_tk' => $tk['so_tk'],
			'ten_tk' => $tk['ten_tk'], 'bin' => $tk['bin'] );
	}

	/** Nội dung có vượt 25 ký tự không — trả câu cảnh báo, hoặc rỗng nếu vừa. */
	public static function canh_bao_dai( $ma_may ) {
		$nd  = self::noi_dung( $ma_may, 'K7M2P' );
		$dai = strlen( $nd );
		if ( $dai <= self::ND_TOI_DA ) { return ''; }
		return 'Nội dung chuyển khoản dài ' . $dai . ' ký tự, vượt giới hạn ' . self::ND_TOI_DA
			. ' của VietQR ("' . $nd . '"). Ngân hàng sẽ cắt bớt — đặt mã ghế ngắn hơn, hoặc bỏ bớt tiền tố.';
	}

	/**
	 * Mã lượt ngẫu nhiên, 5 ký tự CHỮ HOA + SỐ.
	 * ⚠️ Bỏ các ký tự nhìn giống nhau (O/0, I/1) — khách phải gõ tay chuỗi này vào nội dung
	 *    chuyển khoản khi app ngân hàng không tự điền. Gõ nhầm là tiền vào mà ghế không chạy.
	 */
	public static function ma_luot() {
		$chu = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
		$ra = '';
		for ( $i = 0; $i < 5; $i++ ) { $ra .= $chu[ random_int( 0, strlen( $chu ) - 1 ) ]; }
		return $ra;
	}
}
