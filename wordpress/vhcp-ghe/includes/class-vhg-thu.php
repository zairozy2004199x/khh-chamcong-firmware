<?php
/**
 * GHI NHẬN TIỀN VÀO — chỗ duy nhất trong plugin được phép ghi bảng doanh thu.
 *
 * =============================================================================================
 * 🔴 LUẬT: GHI THEO `ref`, VÀ CHỈ MỘT ĐƯỜNG GHI
 * =============================================================================================
 * Mỗi giao dịch ngân hàng có một mã tham chiếu. Ghi theo mã đó (UNIQUE ở bảng) nghĩa là:
 *   · webhook bắn lại lần hai  -> ghi đè lên đúng hàng cũ, KHÔNG cộng thêm;
 *   · nhập lại đúng file Excel -> cũng vậy;
 *   · nhập file chồng lấn ngày -> phần trùng tự hoà, phần mới thì thêm.
 * Nhờ vậy "nhập lại cho chắc" là việc AN TOÀN. Bỏ luật này là mất luôn tính chất đó, và không
 * ai dám bấm nút nhập lần thứ hai nữa.
 *
 * ⚠️ Không có `ref` thì TỰ SINH một mã ổn định từ (thời điểm + số tiền + nội dung) chứ KHÔNG
 *    sinh ngẫu nhiên. Ngẫu nhiên là mỗi lần bắn lại thành một hàng mới — đúng thứ luật trên
 *    đang tránh. Mã tự sinh có tiền tố `tu-` để người đọc biết đây không phải mã của ngân hàng.
 *
 * =============================================================================================
 * ⚠️ CHỈ NỚI, KHÔNG THU HẸP — với thông tin đã biết về một giao dịch
 * =============================================================================================
 * Một giao dịch có thể tới HAI lần theo hai đường: webhook lúc phát sinh (chưa biết máy nào),
 * rồi file Excel Tingo hôm sau (có Mã điểm bán -> tra ra máy). Lượt sau phải ĐIỀN THÊM tên máy,
 * chứ lượt sau mà xoá trắng tên máy lượt trước đã biết thì đối soát đi lùi.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHG_Thu {

	/** Nguồn tiền. `cash` là thu tay tại quầy — người bấm, không qua ngân hàng. */
	const QR     = 'qr';
	const VIETQR = 'vietqr';
	const SEPAY  = 'sepay';
	const TIEN_MAT = 'cash';

	/**
	 * Ghi một giao dịch. `$d` gồm: ref, luc, ma_may, ma_lenh, so_tien, nguon, noi_dung,
	 * ten_khai, vvb, ma_ch. Trả array( 'ok', 'moi' => true/false, 'ref' ).
	 */
	public static function ghi( $d ) {
		global $wpdb;
		$tien = (int) ( isset( $d['so_tien'] ) ? $d['so_tien'] : 0 );
		if ( $tien <= 0 ) { return array( 'ok' => false, 'error' => 'Số tiền phải lớn hơn 0.' ); }

		$luc = isset( $d['luc'] ) ? VHG_Doc::ngay( $d['luc'] ) : '';
		if ( '' === $luc ) { $luc = current_time( 'mysql' ); }

		$ref = trim( (string) ( isset( $d['ref'] ) ? $d['ref'] : '' ) );
		if ( '' === $ref ) { $ref = self::ref_tu_sinh( $luc, $tien, isset( $d['noi_dung'] ) ? $d['noi_dung'] : '' ); }

		$bang = VHG_DB::t( 'thu' );
		$cu = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $bang WHERE ref=%s LIMIT 1", $ref ), ARRAY_A );

		$hang = array(
			'ref'      => $ref,
			'luc'      => $luc,
			'so_tien'  => $tien,
			'nguon'    => (string) ( isset( $d['nguon'] ) ? $d['nguon'] : self::QR ),
			'noi_dung' => mb_substr( (string) ( isset( $d['noi_dung'] ) ? $d['noi_dung'] : '' ), 0, 250 ),
			'ma_may'   => (string) ( isset( $d['ma_may'] ) ? $d['ma_may'] : '' ),
			'ma_lenh'  => (string) ( isset( $d['ma_lenh'] ) ? $d['ma_lenh'] : '' ),
			'ten_khai' => (string) ( isset( $d['ten_khai'] ) ? $d['ten_khai'] : '' ),
			'vvb'      => (string) ( isset( $d['vvb'] ) ? $d['vvb'] : '' ),
			'ma_ch'    => (string) ( isset( $d['ma_ch'] ) ? $d['ma_ch'] : '' ),
			'ghi_luc'  => current_time( 'mysql' ),
		);

		if ( $cu ) {
			/* CHỈ NỚI: giá trị mới rỗng thì GIỮ giá trị cũ. Xem khối ⚠️ ở đầu tệp — lượt sau
			   biết thêm thì điền thêm, lượt sau không biết thì đừng xoá cái lượt trước đã biết. */
			foreach ( array( 'ma_may', 'ma_lenh', 'ten_khai', 'vvb', 'ma_ch', 'noi_dung' ) as $c ) {
				if ( '' === $hang[ $c ] && '' !== (string) $cu[ $c ] ) { $hang[ $c ] = $cu[ $c ]; }
			}
			$wpdb->update( $bang, $hang, array( 'id' => (int) $cu['id'] ) );
			return array( 'ok' => true, 'moi' => false, 'ref' => $ref );
		}
		$wpdb->insert( $bang, $hang );
		return array( 'ok' => true, 'moi' => true, 'ref' => $ref );
	}

	/**
	 * Mã tham chiếu tự sinh khi bên gửi không có. ỔN ĐỊNH theo nội dung giao dịch, không ngẫu
	 * nhiên — xem khối ⚠️ ở đầu tệp.
	 */
	public static function ref_tu_sinh( $luc, $tien, $noi_dung ) {
		return 'tu-' . substr( md5( $luc . '|' . $tien . '|' . trim( (string) $noi_dung ) ), 0, 16 );
	}

	/**
	 * Nhận MỘT giao dịch từ webhook: ghi doanh thu, và nếu nội dung khớp "GHE<ghế> <mã>" thì
	 * xếp vào hàng chờ cho ghế chạy.
	 *
	 * ⚠️ TIỀN RA thì KHÔNG ghi doanh thu. Webhook biến động số dư gửi cả tiền vào lẫn tiền ra;
	 *    tính cả hai là doanh thu phồng lên bằng đúng số tiền mình tự chuyển đi.
	 */
	public static function nhan( $nguon, $ev ) {
		$tien = (int) $ev['so_tien'];
		$nd   = (string) $ev['noi_dung'];

		/* 🔴 NỘI DUNG ĐÃ KHỚP `GHE<ghế> <mã>` THÌ ĐỪNG SUY RA "TÊN MÁY" TỪ CHÍNH NÓ NỮA.
		 *
		 * Ảnh trang ngoài ngày 22/08/2026: bảng "Theo cơ sở" mọc ra một dòng tên
		 * `GHEAMTP01 TEST` — đó không phải cơ sở nào cả, mà là nội dung chuyển khoản bị đem đi
		 * đoán tên máy rồi đoán tiếp thành tên cơ sở. Ghế đã biết chắc là AMTP01 rồi.
		 *
		 * `ten_khai` chỉ có nghĩa cho đường NHẬP EXCEL của Tingo/VietQR, nơi giao dịch mang tên
		 * máy ("AMTP 03") chứ không mang mã ghế. Đường webhook của mình luôn có mã ghế trong nội
		 * dung, nên ở đây suy thêm là chỉ tạo ra rác. */
		list( $ma_may, $ma_lenh ) = VHG_Doc::ghe_va_ma( $nd );
		$ten = '';
		if ( '' === $ma_may ) {
			$ten = VHG_Doc::ten_may( $nd );
			if ( '' === $ten && ! empty( $ev['ten_khai'] ) ) { $ten = VHG_Doc::chuan_ten( $ev['ten_khai'] ); }
		}

		/* 🔴 GÓI THỬ CỦA SEPAY KHÔNG PHẢI TIỀN.
		 *
		 * Nút "Gửi thử" trên trang SePay bắn một gói y như thật, có `transferAmount` hẳn hoi —
		 * ngày 22/08/2026 nó đẻ ra một dòng doanh thu 10.000đ không hề tồn tại. Ai bấm thử lại
		 * là thêm một dòng nữa, mà mỗi dòng là một lần sổ sách lệch với sao kê ngân hàng.
		 *
		 * Vẫn GHI NHẬT KÝ như mọi lượt khác — người khai webhook cần thấy gói thử đã tới nơi,
		 * đó chính là mục đích của nút Gửi thử. Chỉ không vào sổ tiền.
		 *
		 * ⚠️ Nhận diện bằng nội dung ĐÚNG NGUYÊN VĂN, không dùng `strpos`. Khách chuyển khoản
		 *    ghi "TT SEPAY TEST WEBHOOK 20K" là tiền thật — khớp một phần rồi bỏ qua là MẤT
		 *    TIỀN THẬT, tệ hơn hẳn cái nó chữa. */
		if ( 'SEPAY TEST WEBHOOK' === strtoupper( trim( $nd ) ) ) {
			return array( 'ok' => true, 'bo_qua' => true, 'ten_khai' => '',
				'ghi_chu' => 'gói THỬ của SePay — cổng nhận được, nhưng KHÔNG ghi vào sổ tiền' );
		}

		if ( ! empty( $ev['tien_ra'] ) || $tien <= 0 ) {
			return array( 'ok' => true, 'bo_qua' => true,
				'ghi_chu' => ! empty( $ev['tien_ra'] ) ? 'tiền ra — không phải doanh thu' : 'số tiền = 0',
				'ten_khai' => $ten );
		}

		$kq = self::ghi( array(
			'ref' => $ev['ref'], 'so_tien' => $tien, 'noi_dung' => $nd, 'nguon' => $nguon,
			'ma_may' => $ma_may, 'ma_lenh' => $ma_lenh, 'ten_khai' => $ten,
			/* Giờ của BÊN GỬI, không phải giờ máy chủ. Máy chủ đặt sai múi giờ là mọi giao dịch
			   lệch đúng bằng chênh lệch đó, và đối soát với sao kê ngân hàng thành mò kim. Bên
			   gửi không kèm giờ thì `ghi()` mới lấy giờ máy chủ. */
			'luc' => isset( $ev['luc'] ) ? $ev['luc'] : '',
		) );
		if ( empty( $kq['ok'] ) ) { return $kq; }

		/* Khớp mẫu "GHE<ghế> <mã>" -> ghế được phép chạy. KHÔNG khớp thì tiền vẫn vào sổ, chỉ là
		   chưa gắn được máy — đối soát tay sau, đừng bỏ. */
		if ( '' !== $ma_may && '' !== $ma_lenh ) {
			VHG_May::xep_cho_chay( $ma_may, $ma_lenh, $tien, $kq['ref'], $nd );
		}
		return array( 'ok' => true, 'ref' => $kq['ref'], 'moi' => $kq['moi'],
			'ma_may' => $ma_may, 'ma_lenh' => $ma_lenh, 'ten_khai' => $ten, 'so_tien' => $tien );
	}

	/**
	 * HUỶ một giao dịch đã ghi — ĐÁNH DẤU, KHÔNG XOÁ.
	 *
	 * 🔴 Vì sao không DELETE, dù DELETE ngắn hơn ba dòng:
	 *    1. `ref` là UNIQUE, và đó là thứ DUY NHẤT chặn cộng đôi. Xoá dòng đi thì đúng giao dịch
	 *       ấy bắn lại (webhook thử lại, hoặc nhập lại file Excel) sẽ vào sổ như một khoản mới.
	 *       Nghĩa là phép "sửa sổ" tự mở lại đúng cái lỗ nó vừa vá.
	 *    2. Mất chỗ duy nhất trả lời câu "sao hôm đó lệch 10.000đ". Dòng còn nằm đó kèm lý do
	 *       thì người đối soát đọc một cái là xong.
	 *
	 * Gỡ luôn lượt CHƯA CHẠY trong hàng chờ: huỷ tiền mà vẫn để ghế chạy là cho không một lượt.
	 * Lượt ghế ĐÃ NHẬN thì để nguyên — ghế chạy rồi, xoá dấu vết đi là sổ nói dối.
	 */
	public static function huy( $ref, $ly_do = '' ) {
		global $wpdb;
		$ref = trim( (string) $ref );
		if ( '' === $ref ) { return array( 'ok' => false, 'error' => 'Thiếu mã tham chiếu.' ); }
		$bang = VHG_DB::t( 'thu' );
		$r = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $bang WHERE ref=%s LIMIT 1", $ref ), ARRAY_A );
		if ( ! $r ) { return array( 'ok' => false, 'error' => 'Không thấy giao dịch ' . $ref . '.' ); }
		if ( (int) $r['huy'] === 1 ) {
			return array( 'ok' => true, 'thong_bao' => 'Giao dịch này đã huỷ từ trước.' );
		}
		$wpdb->update( $bang, array( 'huy' => 1,
			'huy_ly_do' => mb_substr( (string) $ly_do, 0, 190 ) ), array( 'id' => (int) $r['id'] ) );

		$go = $wpdb->query( $wpdb->prepare(
			'DELETE FROM ' . VHG_DB::t( 'cho' ) . ' WHERE ref=%s AND nhan_luc IS NULL', $ref ) );

		return array( 'ok' => true, 'so_tien' => (int) $r['so_tien'], 'go_cho' => (int) $go,
			'thong_bao' => 'Đã huỷ giao dịch ' . self::tien_ngan( $r['so_tien'] ) . ' (' . $ref . ')'
				. ( $go > 0 ? ' và gỡ ' . (int) $go . ' lượt chưa chạy khỏi hàng chờ.' : '.' ) );
	}

	/** Bỏ huỷ — huỷ nhầm thì phải lùi được, không thì người ta ngại bấm và để rác trong sổ. */
	public static function bo_huy( $ref ) {
		global $wpdb;
		$ref = trim( (string) $ref );
		if ( '' === $ref ) { return array( 'ok' => false, 'error' => 'Thiếu mã tham chiếu.' ); }
		$n = $wpdb->query( $wpdb->prepare( 'UPDATE ' . VHG_DB::t( 'thu' )
			. " SET huy=0, huy_ly_do='' WHERE ref=%s", $ref ) );
		return $n
			? array( 'ok' => true, 'thong_bao' => 'Đã đưa giao dịch ' . $ref . ' trở lại sổ.' )
			: array( 'ok' => false, 'error' => 'Không thấy giao dịch ' . $ref . '.' );
	}

	/** Danh sách giao dịch ĐÃ HUỶ — có huỷ thì phải xem lại được, không thì huỷ thành mất tăm. */
	public static function ds_huy( $gioi_han = 200 ) {
		return VHG_DB::rows( 'SELECT * FROM ' . VHG_DB::t( 'thu' )
			. ' WHERE huy=1 ORDER BY luc DESC, id DESC LIMIT ' . (int) $gioi_han );
	}

	private static function tien_ngan( $v ) {
		return number_format( (float) $v, 0, ',', '.' ) . 'đ';
	}

	/** Thu tiền mặt tại quầy — người bấm trên màn, không qua ngân hàng. */
	public static function thu_tien_mat( $ma_may, $so_tien, $nguoi ) {
		$ma_may = trim( (string) $ma_may );
		if ( '' === $ma_may ) { return array( 'ok' => false, 'error' => 'Thiếu mã máy.' ); }
		$so_tien = (int) $so_tien;
		if ( $so_tien <= 0 ) { return array( 'ok' => false, 'error' => 'Số tiền phải lớn hơn 0.' ); }
		$luc = current_time( 'mysql' );
		return self::ghi( array(
			'ref' => 'mat-' . $ma_may . '-' . strtotime( $luc ) . '-' . $so_tien,
			'luc' => $luc, 'so_tien' => $so_tien, 'nguon' => self::TIEN_MAT,
			'ma_may' => $ma_may, 'noi_dung' => 'Thu tiền mặt · ' . $nguoi,
		) );
	}

	// ======================================================================= đọc

	/**
	 * Mốc đầu kỳ (chuỗi mysql, theo giờ website). Bản dịch `_periodStartMs`.
	 * 'all' -> chuỗi rỗng nghĩa là không lọc.
	 */
	public static function dau_ky( $ky ) {
		$nay = current_time( 'timestamp' );
		switch ( $ky ) {
			case 'today': return gmdate( 'Y-m-d 00:00:00', $nay );
			case 'week':
				/* Tuần bắt đầu THỨ HAI. `N` cho 1..7 với 1 = thứ hai — dùng `w` là tuần bắt đầu
				   chủ nhật, tức doanh thu chủ nhật rơi sang tuần sau. */
				$thu = (int) gmdate( 'N', $nay );
				return gmdate( 'Y-m-d 00:00:00', $nay - ( $thu - 1 ) * 86400 );
			case 'month': return gmdate( 'Y-m-01 00:00:00', $nay );
			case 'year':  return gmdate( 'Y-01-01 00:00:00', $nay );
		}
		return '';
	}

	/**
	 * Tổng của MỘT ghế trong một kỳ: số lượt, QR, tiền mặt, tổng.
	 *
	 * Cộng bằng SQL chứ không kéo hết giao dịch về rồi cộng trong PHP: màn chốt ca hỏi bốn kỳ
	 * cho một ghế, mà "tất cả" của một ghế chạy cả năm là hàng chục nghìn dòng. Kéo về để cộng
	 * là mỗi lần bấm Thu tiền mặt lại đợi vài giây — đúng lúc người ta đang đứng đếm tiền.
	 *
	 * ⚠️ Bỏ dòng đã huỷ (`huy=0`), y như `ds()`. Sót chỗ này là màn chốt ca nói một số, bảng đối
	 *    soát nói số khác, và người đếm tiền không biết tin cái nào.
	 */
	public static function tong_may( $ma_may, $ky = 'today' ) {
		global $wpdb;
		$ma_may = trim( (string) $ma_may );
		$ra = array( 'so_luot' => 0, 'qr' => 0, 'tien_mat' => 0, 'tong' => 0 );
		if ( '' === $ma_may ) { return $ra; }
		$bang = VHG_DB::t( 'thu' );
		$tu   = self::dau_ky( $ky );
		$sql  = "SELECT nguon, COUNT(*) AS n, COALESCE(SUM(so_tien),0) AS t FROM $bang"
			. ' WHERE huy=0 AND ma_may=%s';
		$tham = array( $ma_may );
		if ( '' !== $tu ) { $sql .= ' AND luc >= %s'; $tham[] = $tu; }
		$sql .= ' GROUP BY nguon';
		foreach ( VHG_DB::rows( $wpdb->prepare( $sql, $tham ) ) as $r ) {
			$t = (int) $r['t'];
			$ra['so_luot'] += (int) $r['n'];
			$ra['tong']    += $t;
			if ( self::TIEN_MAT === $r['nguon'] ) { $ra['tien_mat'] += $t; } else { $ra['qr'] += $t; }
		}
		return $ra;
	}

	/** Danh sách giao dịch trong kỳ. */
	public static function ds( $ky = 'today', $gioi_han = 500 ) {
		global $wpdb;
		$tu = self::dau_ky( $ky );
		/* `huy=0` LỌC Ở ĐÂY, không ở từng nơi gọi. `ds()` là cửa duy nhất mọi báo cáo đi qua
		   (`tong_hop` gọi lại chính nó), nên lọc một chỗ là cả hệ thống theo. Lọc rải rác từng
		   nơi thì thêm một màn mới là quên một chỗ, và chỗ quên đó cộng tiền đã huỷ vào doanh thu. */
		$sql = 'SELECT * FROM ' . VHG_DB::t( 'thu' ) . ' WHERE huy=0';
		if ( '' !== $tu ) { $sql = $wpdb->prepare( $sql . ' AND luc >= %s', $tu ); }
		$sql .= ' ORDER BY luc DESC, id DESC LIMIT ' . (int) $gioi_han;
		return VHG_DB::rows( $sql );
	}

	/**
	 * Tổng hợp một kỳ: tổng tiền, theo nguồn, theo máy, theo cơ sở.
	 *
	 * Cơ sở lấy theo BẢNG MÁY nếu máy đã khai; chưa khai thì mới đoán từ tên. Người khai luôn
	 * đúng hơn phép đoán từ chuỗi — mà đoán thì "AMTP 03" và "AMTP03" ra hai cơ sở khác nhau.
	 */
	public static function tong_hop( $ky = 'today' ) {
		$ds  = self::ds( $ky, 100000 );
		$may = VHG_May::ds_may_theo_ma();
		$ra = array( 'tong' => 0, 'so_luot' => 0, 'qr' => 0, 'qr_luot' => 0,
			'tien_mat' => 0, 'tien_mat_luot' => 0, 'theo_may' => array(), 'theo_coso' => array() );

		foreach ( $ds as $r ) {
			$tien = (int) $r['so_tien'];
			$ra['tong'] += $tien; $ra['so_luot']++;
			if ( VHG_Thu::TIEN_MAT === $r['nguon'] ) { $ra['tien_mat'] += $tien; $ra['tien_mat_luot']++; }
			else { $ra['qr'] += $tien; $ra['qr_luot']++; }

			$ten = '' !== $r['ma_may'] ? $r['ma_may'] : ( '' !== $r['ten_khai'] ? $r['ten_khai'] : '(chưa rõ máy)' );
			$cs  = self::coso_cua( $r, $may );
			if ( ! isset( $ra['theo_may'][ $ten ] ) ) {
				$ra['theo_may'][ $ten ] = array( 'may' => $ten, 'coso' => $cs, 'so_luot' => 0,
					'qr' => 0, 'tien_mat' => 0, 'tong' => 0 );
			}
			$ra['theo_may'][ $ten ]['so_luot']++;
			$ra['theo_may'][ $ten ]['tong'] += $tien;
			if ( VHG_Thu::TIEN_MAT === $r['nguon'] ) { $ra['theo_may'][ $ten ]['tien_mat'] += $tien; }
			else { $ra['theo_may'][ $ten ]['qr'] += $tien; }

			if ( ! isset( $ra['theo_coso'][ $cs ] ) ) {
				$ra['theo_coso'][ $cs ] = array( 'coso' => $cs, 'so_may' => array(), 'so_luot' => 0,
					'qr' => 0, 'tien_mat' => 0, 'tong' => 0 );
			}
			$ra['theo_coso'][ $cs ]['so_luot']++;
			$ra['theo_coso'][ $cs ]['tong'] += $tien;
			$ra['theo_coso'][ $cs ]['so_may'][ $ten ] = 1;
			if ( VHG_Thu::TIEN_MAT === $r['nguon'] ) { $ra['theo_coso'][ $cs ]['tien_mat'] += $tien; }
			else { $ra['theo_coso'][ $cs ]['qr'] += $tien; }
		}

		foreach ( $ra['theo_coso'] as $k => $v ) {
			$ra['theo_coso'][ $k ]['so_may'] = count( $v['so_may'] );
		}
		ksort( $ra['theo_may'] );
		ksort( $ra['theo_coso'] );
		$ra['theo_may']  = array_values( $ra['theo_may'] );
		$ra['theo_coso'] = array_values( $ra['theo_coso'] );
		return $ra;
	}

	/** Cơ sở của một giao dịch: bảng máy trước, đoán từ tên sau. */
	/**
	 * Cơ sở của một giao dịch.
	 *
	 * 🔴 KHÔNG BAO GIỜ BỊA RA MỘT CƠ SỞ KHÔNG TỒN TẠI. Bản trước đoán tên cơ sở bằng cách cắt
	 *    số ở cuối tên máy, và nhận BẤT KỲ chuỗi nào ra. Kết quả trên màn hình thật: hai dòng
	 *    "cơ sở" tên `GHEAMTP01 TEST` và `SEPAY TEST WEBHOOK`. Một bảng đối soát mọc ra những
	 *    cơ sở không có thật là bảng không dùng được — người đọc không phân biệt nổi đâu là cơ
	 *    sở quên khai, đâu là rác.
	 *
	 * Phép đoán vẫn giữ (đường nhập Excel của Tingo mang tên máy "AMTP 03" chứ không mang mã
	 * ghế), nhưng CHỈ nhận khi tên đoán ra TRÙNG một cơ sở đã khai. Đoán trúng thì gộp đúng;
	 * đoán trượt thì nói "chưa gán" — câu đó đúng và làm được gì đó với nó.
	 */
	private static function coso_cua( $r, $may ) {
		$ma = (string) $r['ma_may'];
		if ( '' !== $ma && isset( $may[ $ma ] ) && '' !== $may[ $ma ]['coso_ten'] ) {
			return $may[ $ma ]['coso_ten'];
		}
		/* Ghế đã khai mà chưa gán cơ sở -> DỪNG. Đừng quay sang đoán từ nội dung chuyển khoản:
		   ghế đã biết chắc rồi, đoán thêm chỉ ra rác. */
		if ( '' !== $ma && isset( $may[ $ma ] ) ) { return '(chưa gán cơ sở)'; }

		$ten = (string) $r['ten_khai'];
		if ( '' !== $ten ) {
			$k = VHG_Doc::chuan_ten( $ten );
			if ( isset( $may[ $k ] ) && '' !== $may[ $k ]['coso_ten'] ) { return $may[ $k ]['coso_ten']; }
			$doan = VHG_Doc::coso_tu_ten( $ten );
			if ( '' !== $doan ) {
				$that = self::coso_da_khai( $doan );
				if ( '' !== $that ) { return $that; }
			}
		}
		return '(chưa gán cơ sở)';
	}

	/** Tên cơ sở ĐÃ KHAI trùng với chuỗi này (bỏ dấu, không phân biệt hoa thường), hoặc rỗng. */
	private static function coso_da_khai( $ten ) {
		$k = VHG_May::bo_dau_hoa( $ten );
		if ( '' === $k ) { return ''; }
		foreach ( VHG_May::ds_coso() as $c ) {
			if ( VHG_May::bo_dau_hoa( $c['ten'] ) === $k ) { return (string) $c['ten']; }
		}
		return '';
	}
}
