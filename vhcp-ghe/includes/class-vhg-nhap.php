<?php
/**
 * NHẬP BẢNG GIAO DỊCH & BẢN ĐỒ MÁY (Tingo / VietQR xuất ra Excel).
 *
 * =============================================================================================
 * VÌ SAO PHẢI CÓ ĐƯỜNG NHẬP TAY TRONG KHI ĐÃ CÓ WEBHOOK
 * =============================================================================================
 * Webhook chỉ bắn lúc phát sinh. Ba ca webhook KHÔNG cứu được:
 *   · webhook chưa cấu hình / cấu hình sai trong mấy ngày đầu -> mất hẳn khoảng đó;
 *   · máy chủ sập hoặc tường lửa chặn một buổi -> bên gửi thử vài lần rồi thôi;
 *   · giao dịch nội dung "PaymentForOrder" -> webhook không biết máy nào, chỉ file Excel mới có
 *     cột Mã điểm bán để tra ra.
 * Nhập lại file Excel là cách vá cả ba. Và nhập lại AN TOÀN vì `ref` là UNIQUE — xem
 * class-vhg-thu.php.
 *
 * =============================================================================================
 * BẢN ĐỒ MÁY: TỰ HỌC, NHƯNG NGƯỜI KHAI THÌ THẮNG
 * =============================================================================================
 * Phần lớn giao dịch mang nội dung vô nghĩa. Nhưng MỘT SỐ giao dịch của cùng máy đó lại có tên
 * trong nội dung. Nên: học (Mã điểm bán -> tên máy) từ những dòng CÓ tên, rồi áp cho những dòng
 * KHÔNG có. Dòng người khai tay (`tu_hoc = 0`) thì máy KHÔNG được ghi đè — người khai luôn đúng
 * hơn phép học từ dữ liệu bẩn.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHG_Nhap {

	// ======================================================================= bản đồ máy

	public static function ds_ban_do() {
		return VHG_DB::rows( 'SELECT * FROM ' . VHG_DB::t( 'ban_do' ) . ' ORDER BY ten_may ASC, khoa ASC' );
	}

	/** Tra tên máy theo Mã điểm bán (VVB…) hoặc Mã cửa hàng. */
	public static function tra( $khoa ) {
		global $wpdb;
		$khoa = strtoupper( trim( (string) $khoa ) );
		if ( '' === $khoa ) { return ''; }
		return (string) $wpdb->get_var( $wpdb->prepare(
			'SELECT ten_may FROM ' . VHG_DB::t( 'ban_do' ) . ' WHERE khoa=%s LIMIT 1', $khoa ) );
	}

	/**
	 * Ghi một cặp (khoá -> tên máy).
	 * `$tu_hoc = true` là do máy suy ra từ dữ liệu; false là người khai tay.
	 * ⚠️ Máy KHÔNG được ghi đè dòng người khai. Xem khối ⚠️ ở đầu tệp.
	 */
	public static function dat( $khoa, $ten_may, $tu_hoc = true, $vvb = '', $ma_ch = '' ) {
		global $wpdb;
		$khoa = strtoupper( trim( (string) $khoa ) );
		$ten_may = VHG_Doc::chuan_ten( $ten_may );
		if ( '' === $khoa || '' === $ten_may ) { return false; }
		$bang = VHG_DB::t( 'ban_do' );
		$cu = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $bang WHERE khoa=%s LIMIT 1", $khoa ), ARRAY_A );
		if ( $cu ) {
			if ( $tu_hoc && ! (int) $cu['tu_hoc'] ) { return false; }   // người khai thắng
			$wpdb->update( $bang, array( 'ten_may' => $ten_may, 'tu_hoc' => $tu_hoc ? 1 : 0,
				'vvb' => $vvb ? strtoupper( $vvb ) : $cu['vvb'],
				'ma_ch' => $ma_ch ? strtoupper( $ma_ch ) : $cu['ma_ch'],
				'cap_nhat' => current_time( 'mysql' ) ), array( 'id' => (int) $cu['id'] ) );
			return true;
		}
		$wpdb->insert( $bang, array( 'khoa' => $khoa, 'ten_may' => $ten_may,
			'vvb' => strtoupper( (string) $vvb ), 'ma_ch' => strtoupper( (string) $ma_ch ),
			'tu_hoc' => $tu_hoc ? 1 : 0, 'cap_nhat' => current_time( 'mysql' ) ) );
		return true;
	}

	public static function xoa_ban_do( $khoa ) {
		global $wpdb;
		$wpdb->delete( VHG_DB::t( 'ban_do' ), array( 'khoa' => strtoupper( trim( (string) $khoa ) ) ) );
		return array( 'ok' => true, 'thong_bao' => 'Đã xoá một dòng bản đồ máy.' );
	}

	// ======================================================================= đọc bảng dán/tải lên

	/**
	 * Văn bản dán từ Excel -> mảng hai chiều. Tách bằng TAB nếu có, không thì bằng dấu phẩy.
	 * ⚠️ Dò TAB TRƯỚC: dán từ Excel là TAB, mà nội dung ô thì đầy dấu phẩy. Tách bằng phẩy trước
	 *    là một ô "Nguyễn Văn A, Q1" vỡ thành hai cột và cả bảng lệch.
	 */
	public static function bang_tu_van_ban( $tho ) {
		$ra = array();
		$tho = str_replace( "\r", '', (string) $tho );
		foreach ( explode( "\n", $tho ) as $dong ) {
			if ( '' === trim( $dong ) ) { continue; }
			$o = ( false !== strpos( $dong, "\t" ) ) ? explode( "\t", $dong ) : str_getcsv( $dong );
			$ra[] = array_map( function ( $x ) { return trim( (string) $x ); }, $o );
		}
		return $ra;
	}

	/** Tìm chỉ số cột theo một trong các tên (so khớp KHÔNG dấu, không hoa thường). */
	public static function cot( $tieu_de, $ten ) {
		foreach ( $tieu_de as $i => $h ) {
			$h = mb_strtolower( trim( (string) $h ), 'UTF-8' );
			foreach ( $ten as $t ) {
				if ( '' !== $h && false !== mb_strpos( $h, mb_strtolower( $t, 'UTF-8' ) ) ) { return $i; }
			}
		}
		return -1;
	}

	// ======================================================================= nhập giao dịch

	/**
	 * Nhập bảng giao dịch. `$bang` gồm dòng tiêu đề + các dòng dữ liệu.
	 * Trả số dòng nhập, số dòng gắn được máy, số dòng chưa rõ máy, số cặp học thêm.
	 */
	public static function nhap_giao_dich( $bang ) {
		if ( ! $bang || count( $bang ) < 2 ) {
			return array( 'ok' => false, 'error' => 'Cần cả dòng TIÊU ĐỀ và ít nhất một dòng dữ liệu. '
				. 'Copy từ Excel nhớ bôi đen cả dòng đầu.' );
		}
		$h = $bang[0];
		$i_ref = self::cot( $h, array( 'mã tham chiếu', 'tham chiếu', 'reference' ) );
		$i_vvb = self::cot( $h, array( 'mã điểm bán', 'điểm bán', 'voice box' ) );
		$i_ch  = self::cot( $h, array( 'mã cửa hàng', 'cửa hàng' ) );
		$i_tien= self::cot( $h, array( 'số tiền đến', 'tiền đến', 'số tiền' ) );
		$i_nd  = self::cot( $h, array( 'nội dung' ) );
		$i_luc = self::cot( $h, array( 'thời gian tạo', 'thời gian tt', 'thời gian' ) );
		if ( $i_ref < 0 || $i_tien < 0 ) {
			return array( 'ok' => false, 'error' => 'Không thấy cột "Mã tham chiếu" và/hoặc "Số tiền" '
				. 'trong dòng tiêu đề. Kiểm tra lại xem đã copy cả dòng tiêu đề chưa.' );
		}

		$lay = function ( $d, $i ) { return ( $i >= 0 && isset( $d[ $i ] ) ) ? trim( (string) $d[ $i ] ) : ''; };

		/* --- Vòng 1: HỌC. Dòng nào có tên máy trong nội dung thì dạy cho bản đồ. --- */
		$hoc = 0;
		for ( $r = 1; $r < count( $bang ); $r++ ) {
			$d = $bang[ $r ];
			$ten = VHG_Doc::ten_may( $lay( $d, $i_nd ) );
			if ( '' === $ten ) { continue; }
			$vvb = $lay( $d, $i_vvb ); $ma_ch = $lay( $d, $i_ch );
			if ( '' !== $vvb && self::dat( $vvb, $ten, true, $vvb, $ma_ch ) ) { $hoc++; }
			if ( '' !== $ma_ch ) { self::dat( $ma_ch, $ten, true, $vvb, $ma_ch ); }
		}

		/* --- Vòng 2: GHI. Tên máy lấy theo thứ tự: nội dung -> bản đồ (VVB) -> bản đồ (mã CH). --- */
		$vao = 0; $co_ten = 0; $chua_ro = 0; $bo = 0;
		for ( $r = 1; $r < count( $bang ); $r++ ) {
			$d = $bang[ $r ];
			$ref = $lay( $d, $i_ref );
			if ( '' === $ref ) { continue; }
			$tien = VHG_Doc::so( $lay( $d, $i_tien ) );
			if ( $tien <= 0 ) { $bo++; continue; }
			$nd  = $lay( $d, $i_nd );
			$vvb = $lay( $d, $i_vvb ); $ma_ch = $lay( $d, $i_ch );
			$ten = VHG_Doc::ten_may( $nd );
			if ( '' === $ten && '' !== $vvb )   { $ten = self::tra( $vvb ); }
			if ( '' === $ten && '' !== $ma_ch ) { $ten = self::tra( $ma_ch ); }

			list( $ma_may, $ma_lenh ) = VHG_Doc::ghe_va_ma( $nd );
			$kq = VHG_Thu::ghi( array(
				'ref' => $ref, 'luc' => $lay( $d, $i_luc ), 'so_tien' => $tien,
				'nguon' => VHG_Thu::VIETQR, 'noi_dung' => $nd,
				'ma_may' => $ma_may, 'ma_lenh' => $ma_lenh,
				'ten_khai' => $ten, 'vvb' => $vvb, 'ma_ch' => $ma_ch,
			) );
			if ( empty( $kq['ok'] ) ) { $bo++; continue; }
			$vao++;
			if ( '' !== $ten ) { $co_ten++; } else { $chua_ro++; }
		}

		return array( 'ok' => true, 'vao' => $vao, 'co_ten' => $co_ten, 'chua_ro' => $chua_ro,
			'hoc' => $hoc, 'bo' => $bo,
			'thong_bao' => 'Nhập ' . $vao . ' giao dịch · gắn được máy ' . $co_ten . ' · chưa rõ máy '
				. $chua_ro . ' · học thêm ' . $hoc . ' máy'
				. ( $bo > 0 ? ' · bỏ ' . $bo . ' dòng không có số tiền' : '' )
				. '. Nhập lại đúng file này KHÔNG cộng đôi — mỗi giao dịch ghi theo mã tham chiếu.' );
	}

	/**
	 * Nhập danh sách Voice Box: Mã Voice Box + Mã Cửa hàng -> Tên Cửa hàng.
	 * Đây là người KHAI, nên `tu_hoc = false` và máy sẽ không ghi đè.
	 */
	public static function nhap_ban_do( $bang ) {
		if ( ! $bang ) { return array( 'ok' => false, 'error' => 'Chưa có dữ liệu.' ); }
		$h = $bang[0];
		$i_vvb = self::cot( $h, array( 'mã voice box', 'voice box', 'mã điểm bán', 'điểm bán' ) );
		$i_ch  = self::cot( $h, array( 'mã cửa hàng', 'cửa hàng' ) );
		$i_ten = self::cot( $h, array( 'tên cửa hàng', 'tên cửa', 'tên máy' ) );
		if ( ( $i_vvb < 0 && $i_ch < 0 ) || $i_ten < 0 ) {
			return array( 'ok' => false, 'error' => 'Không nhận ra cột "Mã Voice Box"/"Mã Cửa hàng" '
				. 'và "Tên Cửa hàng". Copy kèm cả dòng tiêu đề.' );
		}
		$so = 0;
		for ( $r = 1; $r < count( $bang ); $r++ ) {
			$d = $bang[ $r ];
			$ten = isset( $d[ $i_ten ] ) ? trim( (string) $d[ $i_ten ] ) : '';
			if ( '' === $ten ) { continue; }
			$vvb = ( $i_vvb >= 0 && isset( $d[ $i_vvb ] ) ) ? trim( (string) $d[ $i_vvb ] ) : '';
			$ch  = ( $i_ch >= 0 && isset( $d[ $i_ch ] ) ) ? trim( (string) $d[ $i_ch ] ) : '';
			if ( '' !== $vvb && self::dat( $vvb, $ten, false, $vvb, $ch ) ) { $so++; }
			if ( '' !== $ch ) { self::dat( $ch, $ten, false, $vvb, $ch ); }
		}
		return array( 'ok' => true, 'so' => $so, 'thong_bao' => 'Đã khai ' . $so . ' máy vào bản đồ. '
			. 'Nhập lại bảng giao dịch để áp tên máy cho những giao dịch chưa rõ.' );
	}

	/**
	 * Áp lại bản đồ cho những giao dịch CHƯA có tên máy.
	 * Dùng sau khi khai/sửa bản đồ — khỏi phải nhập lại cả file Excel.
	 */
	public static function ap_lai_ban_do() {
		global $wpdb;
		$bang = VHG_DB::t( 'thu' );
		$ds = VHG_DB::rows( "SELECT id, vvb, ma_ch FROM $bang WHERE ten_khai='' AND ma_may=''"
			. " AND (vvb <> '' OR ma_ch <> '') LIMIT 5000" );
		$so = 0;
		foreach ( $ds as $r ) {
			$ten = '' !== $r['vvb'] ? self::tra( $r['vvb'] ) : '';
			if ( '' === $ten && '' !== $r['ma_ch'] ) { $ten = self::tra( $r['ma_ch'] ); }
			if ( '' === $ten ) { continue; }
			$wpdb->update( $bang, array( 'ten_khai' => $ten ), array( 'id' => (int) $r['id'] ) );
			$so++;
		}
		return array( 'ok' => true, 'so' => $so,
			'thong_bao' => 'Đã gắn tên máy cho ' . $so . ' giao dịch trước đây chưa rõ.' );
	}

	/**
	 * Dọn giao dịch TIỀN RA bị ghi nhầm thành doanh thu.
	 * ⚠️ Chỉ soi những dòng có dấu hiệu rõ ràng trong nội dung. KHÔNG đoán theo số tiền hay theo
	 *    máy — đoán sai ở đây là xoá mất doanh thu thật, mà xoá rồi thì không dựng lại được.
	 */
	public static function don_tien_ra() {
		global $wpdb;
		$bang = VHG_DB::t( 'thu' );
		$ds = VHG_DB::rows( "SELECT id, noi_dung, ten_khai FROM $bang" );
		$xoa = 0;
		foreach ( $ds as $r ) {
			$blob = mb_strtolower( $r['noi_dung'] . ' ' . $r['ten_khai'], 'UTF-8' );
			$dinh = false;
			foreach ( array( 'giao dịch đi', 'giao dich di', 'chuyển tiền đi', 'chuyen tien di', 'tiền đi' ) as $x ) {
				if ( false !== mb_strpos( $blob, $x ) ) { $dinh = true; break; }
			}
			if ( ! $dinh ) { continue; }
			$wpdb->delete( $bang, array( 'id' => (int) $r['id'] ) );
			$xoa++;
		}
		return array( 'ok' => true, 'so' => $xoa,
			'thong_bao' => 'Đã xoá ' . $xoa . ' giao dịch TIỀN RA bị tính nhầm thành doanh thu.' );
	}
}
