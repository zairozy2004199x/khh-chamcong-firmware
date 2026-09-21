<?php
/**
 * NẠP BẢNG CÔNG CŨ — khuôn "MỖI NGÀY MỘT DÒNG, MỖI NGƯỜI BA CỘT".
 *
 * =================================================================================================
 * Anh Thắng 21/09/2026: *"anh đang có bảng công cũ, làm sao để nạp vào mà không cần copy"*.
 * =================================================================================================
 *
 * 🔴 ĐÂY LÀ KHUÔN NGƯỢC VỚI `VHCC_NapCong`, ĐỪNG NHẦM HAI CÁI.
 *    `VHCC_NapCong` đọc bảng của Sheets cũ: mỗi NGƯỜI một dòng, cột gom theo NGÀY.
 *    Tệp này đọc bảng của anh Thắng: mỗi NGÀY một dòng, cột gom theo NGƯỜI.
 *    Cùng là "bảng chấm công" nhưng xoay 90 độ, nên không dùng chung một bộ đọc được.
 *
 *    dòng 1:  BẢNG CHẤM CÔNG THÁNG 4/2026
 *    dòng 2:  Thời gian/Ngày | Ngân |  |  | K.Oanh (LT) |  |  | K.Oanh | …
 *    dòng 3:                 | Check in | Check out | Số giờ làm | Check in | …
 *    dòng 4+: 1 | 0 | 0 | 00:00 | 09:35 | 13:10 | 03:35 | …
 *
 *    Tên người chỉ ghi ở cột ĐẦU cụm (ô gộp), hai ô sau để trống — y như `VHCC_NapCong` phải
 *    chịu với cụm ngày. Ô trống ghi `0`, không phải rỗng.
 *
 * =================================================================================================
 * 🔴 HAI CA TRONG MỘT NGÀY: NỐI THẲNG HAI ĐẦU LÀ TRẢ DƯ TIỀN
 * =================================================================================================
 * Anh Thắng 21/09/2026 xác nhận cụm `(LT)` và cụm thường là HAI CA của CÙNG một người trong
 * cùng một ngày. Phần lớn là hai ca liền nhau (09:35→13:00 rồi 13:00→17:00), nối lại thành
 * 09:35→17:00 là đúng.
 *
 * NHƯNG KHÔNG PHẢI LÚC NÀO CŨNG LIỀN. Ngay trong tệp anh gửi, ngày 11 của N.Kiệt là
 * `08:30→13:00` và `17:05→22:00`. Nối thẳng hai đầu ra 08:30→22:00 = 13 giờ 30, trong khi người
 * ta làm 4:30 + 4:55 = 9 giờ 25. Dư bốn tiếng, cho MỘT người trong MỘT ngày — nhân với cả bảng
 * thì đó là một khoản tiền thật.
 *
 * Nên khoảng giữa hai ca được ghi vào `nghi_tu_giay`/`nghi_den_giay` — đúng cột mà hệ đã có sẵn
 * cho ca gãy, và mọi phép tính công trong hệ đã biết trừ nó ra.
 *
 * ⚠️ BA CA TRỞ LÊN THÌ CHỐI, KHÔNG ĐOÁN. Một hàng chỉ giữ được MỘT khoảng nghỉ. Gặp ba ca mà
 *    vẫn ghi bừa một khoảng là giấu mất một khoảng khác, và giấu theo hướng trả dư.
 *
 * ⚠️ HÀM `doc()` LÀ HÀM THUẦN — không chạm cơ sở dữ liệu, không cần WordPress. Nhờ vậy thử
 *    được thẳng trên tệp thật của anh Thắng bằng một lượt chạy php, trước khi ghi một dòng nào.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_NapDoc {

	/** Nhãn nguồn — cùng nhãn với `VHCC_NapCong`: cả hai đều là bảng cũ nạp vào. */
	const NGUON = 'sheet';

	/** Một cụm của một người dài bao nhiêu cột (Check in · Check out · Số giờ làm). */
	const CUM = 3;

	/**
	 * ĐỌC CẢ BẢNG.
	 *
	 * @param array $dong mảng các dòng, mỗi dòng là mảng ô (đã đọc từ .xlsx hoặc .csv).
	 * @return array ok · thang · nguoi[] · luot[] · canh[]
	 */
	public static function doc( $dong ) {
		$dong = array_values( (array) $dong );
		$ra = array( 'ok' => false, 'thang' => '', 'nguoi' => array(), 'luot' => array(),
			'canh' => array() );

		/* ═══════════════════════════════════════════════════════════════════════════════════
		 * 🔴 NHIỀU BẢNG CHỒNG NHAU TRONG MỘT TỆP THÌ CHỐI — KHÔNG ĐỌC BỪA BẢNG ĐẦU.
		 *
		 * Tệp anh Thắng gửi 21/09/2026 có 33 bảng xếp dọc, từ tháng 1/2024 tới 2026. Bản đầu của
		 * bộ đọc này lấy THÁNG ở sáu dòng trên cùng (ra "1/2024") rồi đi tìm dòng "Check in"
		 * trong CẢ tệp — và dòng ấy mãi tới bảng tháng 11/2024 mới xuất hiện. Kết quả: hai năm
		 * công của mọi người bị đóng dấu tháng 1/2024, chồng đè lên nhau, mà không một câu báo.
		 * Bảng vẫn ra số, số vẫn hợp lệ, chỉ là sai hết.
		 *
		 * Anh Thắng 21/09/2026: *"Để anh copy 1 tháng thôi — tránh lẫn lộn dữ liệu"*. Đúng, và
		 * chốt này là thứ bắt máy tuân theo cách làm ấy thay vì trông vào trí nhớ người dùng.
		 * ═══════════════════════════════════════════════════════════════════════════════════ */
		$ds_thang = self::ds_thang( $dong );
		if ( count( $ds_thang ) > 1 ) {
			$ra['canh'][] = '🔴 Tệp này có ' . count( $ds_thang ) . ' bảng chồng nhau ('
				. implode( ' · ', array_slice( $ds_thang, 0, 6 ) )
				. ( count( $ds_thang ) > 6 ? ' …' : '' ) . '). Mỗi lần chỉ nạp MỘT tháng — '
				. 'cắt riêng tháng cần nạp ra một tệp rồi nạp lại.';
			return $ra;
		}
		$thang = self::tim_thang( $dong );
		if ( '' === $thang ) {
			$ra['canh'][] = 'Không thấy tháng trong tệp. Dòng tiêu đề phải có dạng '
				. '"BẢNG CHẤM CÔNG THÁNG 4/2026".';
			return $ra;
		}
		$ra['thang'] = $thang;

		$i_cot = self::tim_dong_cot( $dong );
		if ( null === $i_cot ) {
			$ra['canh'][] = 'Không thấy dòng "Check in / Check out". Tệp này không phải khuôn '
				. 'bảng công theo ngày.';
			return $ra;
		}

		/* Dòng tên người nằm NGAY TRÊN dòng "Check in". Không dò theo số dòng cố định: bảng có
		   thể có một hay hai dòng tiêu đề phía trên, tuỳ người xuất. */
		$i_ten = $i_cot - 1;
		if ( $i_ten < 0 ) {
			$ra['canh'][] = 'Không thấy dòng tên người phía trên dòng "Check in".';
			return $ra;
		}

		$cum = self::tim_cum( $dong[ $i_ten ], $dong[ $i_cot ] );
		if ( ! $cum ) {
			$ra['canh'][] = 'Không thấy cụm cột nào của người nào.';
			return $ra;
		}

		/* Gom theo (tên gốc, ngày) — hai cụm của cùng một người rơi vào cùng một ô. */
		$gom = array();
		$ten_thay = array();
		for ( $i = $i_cot + 1; $i < count( $dong ); $i++ ) {
			$o_ngay  = isset( $dong[ $i ][0] ) ? $dong[ $i ][0] : '';
			$ngay_so = self::so_ngay( $o_ngay );
			if ( null === $ngay_so ) { continue; }   // Lễ · Bù · Biên Bản · Tổng · dòng trống
			$ngay = $thang . '-' . str_pad( (string) $ngay_so, 2, '0', STR_PAD_LEFT );
			if ( ! self::ngay_that( $ngay ) ) {
				$ra['canh'][] = 'Tháng ' . $thang . ' không có ngày ' . $ngay_so . ' — bỏ dòng ấy.';
				continue;
			}
			/* Ô ngày có ghi chú ("1 (LỄ*2)", "26 (27 TẾT)", "29 M1") — nạp giờ bình thường,
			   nhưng phải KỂ RA: hệ số ngày lễ KHÔNG nằm trong tệp này. */
			$chu = self::chu_ngay( $o_ngay );
			if ( '' !== $chu ) {
				$ra['canh'][] = 'Ngày ' . $ngay_so . ' có ghi chú "' . $chu . '" — chỉ nạp GIỜ, '
					. 'không nạp hệ số lễ. Khai ngày lễ ở màn Cấu hình thì hệ tự nhân.';
			}
			$soat = array();
			foreach ( $cum as $c ) {
				$o_v = isset( $dong[ $i ][ $c['cot'] ] ) ? $dong[ $i ][ $c['cot'] ] : '';
				$o_r = isset( $dong[ $i ][ $c['cot'] + 1 ] ) ? $dong[ $i ][ $c['cot'] + 1 ] : '';
				/* 🔴 Ô GÕ SAI KHÁC HẲN Ô TRỐNG, VÀ PHẢI NÓI RA.
				   Trong tệp anh Thắng có mấy ô như `11:4`, `11:0` — thiếu một chữ số. Đọc về
				   `null` thì ca ấy thành ca thiếu một đầu giờ, im lặng, y như ô trống. Người ta
				   nhìn bảng nạp xong thấy "thiếu giờ ra" rồi đi bù tay, trong khi chuyện thật là
				   BẢNG GỐC gõ sai một ô — sửa ở bảng gốc mới đúng chỗ. */
				foreach ( array( 'vào' => $o_v, 'ra' => $o_r ) as $ben => $o_x ) {
					if ( self::gio_xau( $o_x ) ) {
						$ra['canh'][] = '⚠ ' . $c['nhan'] . ' ngày ' . $ngay_so . ': ô giờ ' . $ben
							. ' ghi "' . trim( (string) $o_x ) . '" — không đọc được, sửa ở bảng gốc.';
					}
				}
				$vao  = self::gio( $o_v );
				$ra_g = self::gio( $o_r );

				/* Cột thứ ba ("Số giờ làm") của chính bảng gốc — để đối chiếu, KHÔNG để nạp.
				   Xem `soat_ngay()` cho lý do vì sao không bao giờ nạp theo cột này. */
				if ( null !== $vao && null !== $ra_g && $ra_g > $vao ) {
					$o_h = isset( $dong[ $i ][ $c['cot'] + 2 ] ) ? $dong[ $i ][ $c['cot'] + 2 ] : '';
					$ghi = self::gio_tho( $o_h );
					if ( null !== $ghi ) {
						$soat[] = array( 'ten' => $c['nhan'], 'that' => $ra_g - $vao, 'ghi' => $ghi );
					}
				}

				if ( null === $vao && null === $ra_g ) { continue; }
				$ten_thay[ $c['ten'] ] = true;
				$gom[ $c['ten'] ][ $ngay ][] = array( 'vao' => $vao, 'ra' => $ra_g, 'cum' => $c['nhan'] );
			}

			foreach ( self::soat_ngay( $ngay_so, $soat ) as $c_soat ) { $ra['canh'][] = $c_soat; }
		}

		$ra['nguoi'] = array_keys( $ten_thay );
		sort( $ra['nguoi'] );

		foreach ( $gom as $ten => $theo_ngay ) {
			foreach ( $theo_ngay as $ngay => $ca ) {
				$l = self::gop_ca( $ten, $ngay, $ca );
				if ( isset( $l['canh'] ) ) { $ra['canh'][] = $l['canh']; }
				if ( ! empty( $l['bo'] ) ) { continue; }
				$ra['luot'][] = $l;
			}
		}
		/* Xếp theo ngày rồi tên: nhật ký nạp đọc lại được theo trình tự thời gian. */
		usort( $ra['luot'], function ( $a, $b ) {
			$c = strcmp( $a['ngay'], $b['ngay'] );
			return ( 0 !== $c ) ? $c : strcmp( $a['ten'], $b['ten'] );
		} );

		$ra['ok'] = true;
		return $ra;
	}

	/**
	 * GỘP CÁC CA CỦA MỘT NGƯỜI TRONG MỘT NGÀY.
	 *
	 * @return array ten · ngay · vao · ra · nghiTu · nghiDen · soCa · (canh) · (bo)
	 */
	private static function gop_ca( $ten, $ngay, $ca ) {
		/* Ca thiếu một đầu vẫn giữ — hệ có đường bù giờ cho nó, và bỏ đi là mất luôn dấu vết
		   rằng hôm ấy người này CÓ đi làm. */
		$du = array();
		$thieu = 0;
		foreach ( $ca as $x ) {
			if ( null !== $x['vao'] && null !== $x['ra'] ) { $du[] = $x; } else { $thieu++; }
		}

		$l = array( 'ten' => $ten, 'ngay' => $ngay, 'vao' => null, 'ra' => null,
			'nghiTu' => null, 'nghiDen' => null, 'soCa' => count( $ca ) );

		if ( ! $du ) {
			/* Chỉ có ca thiếu đầu — lấy đầu nào có. */
			foreach ( $ca as $x ) {
				if ( null !== $x['vao'] && null === $l['vao'] ) { $l['vao'] = $x['vao']; }
				if ( null !== $x['ra'] ) { $l['ra'] = $x['ra']; }
			}
			$l['canh'] = $ten . ' ' . $ngay . ': ca thiếu một đầu giờ — nạp vào rồi bù sau.';
			return $l;
		}

		usort( $du, function ( $a, $b ) { return $a['vao'] - $b['vao']; } );
		$l['vao'] = $du[0]['vao'];
		$l['ra']  = $du[ count( $du ) - 1 ]['ra'];

		/* 🔴 BA CA TRỞ LÊN THÌ CHỐI. Một hàng chỉ giữ được MỘT khoảng nghỉ; ghi bừa một khoảng
		   là giấu mất khoảng khác, và giấu theo hướng TRẢ DƯ. */
		if ( count( $du ) > 2 ) {
			$l['bo'] = true;
			$l['canh'] = '🔴 ' . $ten . ' ' . $ngay . ': có ' . count( $du ) . ' ca trong một ngày — '
				. 'một hàng chỉ ghi được MỘT khoảng nghỉ giữa ca, nên bỏ qua ngày này. Nhập tay.';
			return $l;
		}

		if ( 2 === count( $du ) ) {
			$het1 = (int) $du[0]['ra'];
			$dau2 = (int) $du[1]['vao'];
			if ( $dau2 > $het1 ) {
				/* Khoảng giữa hai ca — trừ ra, nếu không thì trả dư đúng bằng khoảng ấy. */
				$l['nghiTu']  = $het1;
				$l['nghiDen'] = $dau2;
			} elseif ( $dau2 < $het1 ) {
				$l['canh'] = '⚠ ' . $ten . ' ' . $ngay . ': hai ca CHỒNG nhau ('
					. VHCC_DB::hhmm( $du[0]['vao'] ) . '–' . VHCC_DB::hhmm( $het1 ) . ' và '
					. VHCC_DB::hhmm( $dau2 ) . '–' . VHCC_DB::hhmm( $du[1]['ra'] )
					. ') — nạp thành một khung liền, xem lại bảng gốc.';
			}
		}
		if ( $thieu > 0 ) {
			$l['canh'] = '⚠ ' . $ten . ' ' . $ngay . ': có thêm ' . $thieu
				. ' ca thiếu một đầu giờ — chỉ nạp ca đủ cặp.';
		}
		return $l;
	}

	/**
	 * ĐỐI CHIẾU CỘT "SỐ GIỜ LÀM" CỦA BẢNG GỐC VỚI GIỜ VÀO/RA — CHO MỘT NGÀY.
	 *
	 * ═════════════════════════════════════════════════════════════════════════════════════════
	 * 🔴 KHÔNG BAO GIỜ NẠP THEO CỘT NÀY. Nó là số TIỀN CÔNG, không phải số giờ đã làm.
	 * ═════════════════════════════════════════════════════════════════════════════════════════
	 * Trong bảng anh Thắng, ngày lễ được nhân sẵn vào cột ấy: 1/5/2026 mọi ô đều GẤP ĐÔI
	 * (13:00→17:05 ghi thành 08:10), còn mấy ngày Tết 19–21/2/2026 thì GẤP BA (10:25→22:00 ghi
	 * thành 34:45). Nạp theo cột ấy là trả gấp hai, gấp ba lần nữa chồng lên hệ số của hệ.
	 * Ta nạp theo GIỜ VÀO/RA, và cột này chỉ dùng để SOI.
	 *
	 * Soi thì đáng: trên 2820 ô của tệp thật, phép này lôi ra đúng hai ô hỏng thật —
	 * `8/2026 ngày 30 T.Bình (LT) 13:00→17:00 ghi 8:00` và một ô y hệt ở 6/2026 — những ô gấp
	 * đôi LẺ LOI giữa một ngày thường. Bốn tiếng cho một người một ngày, và nếu không soi thì
	 * sau khi nạp xong, tổng của hệ lệch với sổ cũ bốn tiếng mà không ai biết vì đâu.
	 *
	 * ⚠️ PHẢI PHÂN BIỆT "CẢ NGÀY NHÂN HỆ SỐ" VỚI "MỘT Ô LẺ LOI". Kêu tất thì một tháng có Tết
	 *    đẻ ra hàng chục dòng đều đặn và không ai đọc dòng nào nữa — kể cả dòng thật. Cả ngày
	 *    cùng một bội số thì đó là ngày lễ, kể MỘT dòng; một ô lệch riêng giữa những ô khớp thì
	 *    đó là chỗ gõ sai, kể ĐÍCH DANH.
	 *
	 * @param array $ds mỗi phần tử: ten · that (giây, suy từ giờ vào/ra) · ghi (giây, cột gốc).
	 */
	private static function soat_ngay( $ngay_so, $ds ) {
		if ( ! $ds ) { return array(); }
		$khop = 0;
		$boi  = array();
		$le   = array();   // ô có bội số nguyên (2, 3, 4)
		$la   = array();   // ô lệch không theo bội nào
		foreach ( $ds as $x ) {
			if ( $x['that'] <= 0 ) { continue; }
			if ( $x['ghi'] === $x['that'] ) { $khop++; continue; }
			if ( 0 === $x['ghi'] % $x['that'] ) {
				$k = intdiv( $x['ghi'], $x['that'] );
				if ( $k >= 2 && $k <= 4 ) {
					$boi[ $k ] = ( isset( $boi[ $k ] ) ? $boi[ $k ] : 0 ) + 1;
					$x['boi']  = $k;
					$le[]      = $x;
					continue;
				}
			}
			$la[] = $x;
		}
		if ( ! $le && ! $la ) { return array(); }

		/* Bội số trội nhất trong ngày. Nhiều ô cùng một bội, và không ít hơn số ô khớp -> cả
		   ngày ăn hệ số, tức ngày lễ. */
		$k_troi = 0;
		$n_troi = 0;
		foreach ( $boi as $k => $n ) { if ( $n > $n_troi ) { $k_troi = $k; $n_troi = $n; } }

		$ra = array();
		if ( $n_troi >= 2 && $n_troi >= $khop ) {
			$ra[] = 'Ngày ' . $ngay_so . ': bảng gốc tính GẤP ' . $k_troi . ' ở ' . $n_troi
				. ' ô — đây là ngày lễ. Hệ chỉ nạp GIỜ THẬT; khai ngày lễ ở màn Cấu hình để hệ '
				. 'tự nhân, đừng nhân hai lần.';
			/* Ô KHÔNG theo bội số chung của ngày lễ vẫn phải kể riêng. */
			foreach ( $le as $x ) {
				if ( $x['boi'] !== $k_troi ) { $la[] = $x; }
			}
		} else {
			/* Không thành ngày lễ -> mọi ô nhân hệ số đều là ô lẻ loi, tức chỗ đáng ngờ nhất. */
			foreach ( $le as $x ) { $la[] = $x; }
		}

		foreach ( array_slice( $la, 0, 12 ) as $x ) {
			$ra[] = '🔴 Ngày ' . $ngay_so . ', ' . $x['ten'] . ': giờ vào/ra ra '
				. self::gio_chu( $x['that'] ) . ' nhưng cột "Số giờ làm" của bảng ghi '
				. self::gio_chu( $x['ghi'] ) . ' — nạp theo giờ vào/ra, xem lại bảng gốc.';
		}
		if ( count( $la ) > 12 ) {
			$ra[] = '…và ' . ( count( $la ) - 12 ) . ' ô nữa lệch trong ngày ' . $ngay_so . '.';
		}
		return $ra;
	}

	/**
	 * Ô giờ của cột "Số giờ làm" -> giây. Nhận cả số giờ VƯỢT 24 (`34:45`, `27:00`).
	 *
	 * ⚠️ KHÔNG dùng `gio()` cho cột này. `gio()` chặn giờ > 23 vì nó đọc GIỜ TRONG NGÀY; còn đây
	 *    là KHOẢNG THỜI GIAN, và chính mấy ô vượt 24 (`34:45` của ngày Tết gấp ba) mới là thứ
	 *    cần soi nhất. Dùng nhầm hàm là chúng rơi về null và biến mất khỏi phép đối chiếu.
	 */
	private static function gio_tho( $o ) {
		$s = trim( (string) $o );
		if ( '' === $s || ! preg_match( '/^(\d{1,3}):(\d{2})(?::(\d{2}))?$/', $s, $m ) ) { return null; }
		$p = (int) $m[2];
		$y = isset( $m[3] ) ? (int) $m[3] : 0;
		if ( $p > 59 || $y > 59 ) { return null; }
		return (int) $m[1] * 3600 + $p * 60 + $y;
	}

	/** Giây -> "8:00" / "34:45". Không dùng `VHCC_DB::hhmm` vì hàm ấy cuộn vòng 24 giờ. */
	private static function gio_chu( $giay ) {
		$giay = (int) $giay;
		return intdiv( $giay, 3600 ) . ':' . str_pad( (string) intdiv( $giay % 3600, 60 ), 2, '0', STR_PAD_LEFT );
	}

	/* ============================================================================ ghi vào */

	/**
	 * ĐỌC RỒI GHI VÀO BẢNG CHẤM CÔNG.
	 *
	 * ⚠️ ĐI QUA ĐÚNG `VHCC_Nhan::ghi_gio()` — như `VHCC_NapCong`. Nạp lại bao nhiêu lần cũng
	 *    không sinh trùng, và không bao giờ thu hẹp giờ đã có.
	 *
	 * @param array $map  tên trong tệp => mã NV, do người ta vừa chọn ở màn ghép. Trộn ĐÈ lên
	 *                    sổ ghép đã lưu, rồi lưu lại — anh Thắng 21/09/2026 muốn "nhớ cho lần sau".
	 * @param bool  $chi_xem true = chỉ đếm và kể, KHÔNG ghi một dòng nào. Mặc định true.
	 */
	public static function nap( $u, $coso, $dong, $map = array(), $chi_xem = true, $chi_trong = true ) {
		if ( ! VHCC_Vai::duoc( $u, 'nap_cong' ) ) {
			return array( 'ok' => false, 'error' => 'Nạp dữ liệu công cần quyền Quản lý trở lên.' );
		}
		$coso = VHCC_NhanSu::chuan_coso( $coso );
		if ( '' === $coso ) {
			return array( 'ok' => false, 'error' => 'Chưa chọn cơ sở để nạp vào.' );
		}
		if ( ! VHCC_NhanSu::co_quyen_coso( $u, $coso ) ) {
			return array( 'ok' => false, 'error' => 'Không có quyền cơ sở này.' );
		}

		$d = self::doc( $dong );
		if ( empty( $d['ok'] ) ) { return $d; }
		$canh = $d['canh'];

		/* ═══════════════════════════════════════════════════════════════════════════════════
		 * 🔴 THÁNG NÀY TRONG SỔ ĐÃ CÓ GÌ CHƯA — ĐẾM VÀ NÓI RA TRƯỚC KHI GHI.
		 *
		 * Tiêu đề bảng là thứ DUY NHẤT nói tháng nào, và nó gõ bằng tay nên gõ sai được. Ngay
		 * trong tệp anh Thắng gửi 21/09/2026 có HAI bảng cùng đề "THÁNG 8/2026" — bảng sau là
		 * tháng khác, chỉ là chép tiêu đề quên sửa. Cắt bảng sau ra nạp thì cả tháng ấy rơi vào
		 * tháng 8, trộn với số liệu thật của tháng 8, và không câu nào báo: bộ đọc tin tiêu đề,
		 * `ghi_gio()` chỉ nới khung nên cũng không kêu.
		 *
		 * Không có cách nào để máy BIẾT tiêu đề sai. Nhưng đưa ra con số "tháng này đã có 122
		 * ngày công của 9 người" ngay trên màn Xem trước thì người đang cầm bảng nhận ra ngay —
		 * họ biết tháng ấy đáng lẽ trống.
		 * ═══════════════════════════════════════════════════════════════════════════════════ */
		$co_san = self::dem_thang_trong_so( $coso, $d['thang'] );
		if ( $co_san['luot'] > 0 ) {
			$canh[] = '⚠ Tháng ' . self::thang_chu( $d['thang'] ) . ' của cơ sở ' . $coso . ' ĐÃ CÓ '
				. $co_san['luot'] . ' ngày công của ' . $co_san['nguoi'] . ' người trong sổ. '
				. 'Nạp thêm thì hai bên trộn vào nhau — kiểm lại tiêu đề bảng có đúng tháng không.';
		}

		/* Sổ ghép đã lưu + những ô người ta vừa chọn. Ô vừa chọn ĐÈ lên sổ: đó là người sửa lại
		   một phép ghép cũ sai, và phép sửa ấy phải thắng. */
		$so   = self::so_ghep( $coso );
		$moi  = self::loc_map( $map, $d['nguoi'] );
		$ghep = array_merge( $so, $moi );

		/* Tên đã ghép mà mã ấy KHÔNG còn hồ sơ -> coi như chưa ghép. Người nghỉ việc bị xoá hồ
		   sơ thì sổ ghép còn trỏ tới một mã ma, và ghi vào đó là công rơi vào chỗ không ai xem. */
		$ten_hs = array();
		foreach ( $ghep as $t => $ma ) {
			$hs = VHCC_NhanSu::ho_so( $ma );
			if ( ! $hs ) {
				unset( $ghep[ $t ] );
				$canh[] = '⚠ Tên "' . $t . '" từng ghép với mã ' . $ma . ' nhưng mã ấy không còn '
					. 'hồ sơ — chọn lại người.';
				continue;
			}
			$ten_hs[ $ma ] = (string) $hs['ho_ten'];
		}

		/* Lưu NGAY ở bước xem trước luôn, không đợi "Nạp thật": người ta hay xem đi xem lại vài
		   lần, và bắt chọn lại hai chục cái tên ở mỗi lượt xem là thứ khiến không ai dùng màn này. */
		if ( $moi ) { self::luu_ghep( $u, $coso, $moi ); }

		/* Gợi ý cho những tên CHƯA ghép — chỉ gợi, không tự lấy. */
		$ds_nv  = VHCC_NhanSu::ds_nhan_vien( $u, $coso );
		$nguoi  = array();
		$thieu  = 0;
		foreach ( $d['nguoi'] as $t ) {
			$ma = isset( $ghep[ $t ] ) ? $ghep[ $t ] : '';
			$goi = ( '' === $ma ) ? self::goi_y( $t, $ds_nv ) : array();
			if ( '' === $ma ) { $thieu++; }
			$nguoi[] = array(
				'ten'   => $t,
				'ma'    => $ma,
				'tenHS' => ( '' !== $ma && isset( $ten_hs[ $ma ] ) ) ? $ten_hs[ $ma ] : '',
				'tuDau' => ( '' === $ma ) ? '' : ( isset( $moi[ $t ] ) ? 'moi' : 'luu' ),
				'goiY'  => $goi,
			);
		}

		/* Giờ đang có sẵn trong sổ của tháng ấy — hỏi MỘT LẦN cho cả tháng, không hỏi từng ngày. */
		$da_co = self::gio_dang_co( $coso, $d['thang'] );

		$ghi = 0;
		$bo_luot = 0;
		$nghi_ghi = 0;
		$bo_trung = 0;
		$phinh = array();
		$ngay_co = array();
		foreach ( $d['luot'] as $x ) {
			$ngay_co[ $x['ngay'] ] = 1;
			if ( ! isset( $ghep[ $x['ten'] ] ) ) { $bo_luot++; continue; }
			$ma = $ghep[ $x['ten'] ];

			/* ═══════════════════════════════════════════════════════════════════════════════
			 * 🔴 NGÀY TRONG SỔ ĐÃ CÓ GIỜ — CHỖ CHẾT CỦA CẢ BỘ NẠP.
			 *
			 * Anh Thắng 21/09/2026: *"Nạp vào mà có giờ cũ, nó sẽ lấy theo giờ nạp"*. KHÔNG.
			 * `ghi_gio()` chỉ NỚI khung [vào, ra] — không thu hẹp, không thay. Đó là luật đúng
			 * cho máy chấm công (hai lượt quẹt của cùng một ngày phải gộp lại), nhưng đem sang
			 * đây thì thành bẫy:
			 *
			 *     máy ghi 08:00→13:00 (5 giờ) · bảng ghi 17:00→22:00 (5 giờ)
			 *     -> sổ thành 08:00→22:00 = 14 GIỜ
			 *
			 * Không phải 5, không phải 10. Hai nguồn không biết nhau nên không có khoảng nghỉ
			 * nào được ghi vào giữa — và cái khoảng ấy chính là thứ `dat_nghi_giua()` sinh ra khi
			 * hai ca cùng nằm TRONG bảng. Chín tiếng dư cho một người một ngày, im lặng.
			 *
			 * Không thể tự đoán hộ: máy ghi 13:06→17:05 còn bảng ghi 13:00→17:05 là CÙNG một ca
			 * gõ lệch vài phút, ghi một khoảng nghỉ vào giữa đó là bậy. Nên đây là chỗ phải để
			 * NGƯỜI quyết, và mặc định phải là cái an toàn: KHÔNG ĐỤNG ngày đã có giờ.
			 * ═══════════════════════════════════════════════════════════════════════════════ */
			$k  = $ma . '|' . $x['ngay'];
			$cu = isset( $da_co[ $k ] ) ? $da_co[ $k ] : null;
			if ( $cu ) {
				$hop = self::gop_khung( $cu, $x );
				if ( $hop ) { $phinh[] = $hop; }
				if ( $chi_trong ) { $bo_trung++; continue; }
			}

			if ( $chi_xem ) { $ghi++; continue; }


			/* 🔴 TÊN GHI VÀO SỔ LÀ TÊN TRONG HỒ SƠ, KHÔNG PHẢI TÊN VIẾT TẮT Ở BẢNG.
			   Anh Thắng 21/09/2026 hỏi thẳng: *"nạp vào tên hệ thống tự do hay sao, có cần sửa
			   tên đúng tên trên bản chấm công không"*. Không cần sửa gì cả — `N.Kiệt` ở bảng chỉ
			   là cái NHÃN để chọn người; thứ đi vào sổ là MÃ NV, kèm họ tên đầy đủ lấy từ hồ sơ.
			   Nhờ vậy tháng sau anh gõ `Kiệt` thay vì `N.Kiệt` cũng không đẻ ra người thứ hai:
			   chỉ là một cái nhãn chưa ghép, chọn lại một lần rồi hệ nhớ.

			   ⚠️ HỒ SƠ BỎ TRỐNG HỌ TÊN thì lấy tạm nhãn ở bảng. `isset()` không bắt được chuỗi
			      rỗng, nên bản đầu ghi một cái tên TRẮNG vào bảng công — mà hàng ấy có mã, có
			      giờ, chỉ thiếu tên, nên nhìn bảng thì tưởng hỏng dữ liệu chứ không ai nghĩ là
			      hồ sơ thiếu tên. Có nhãn còn hơn có ô trắng. */
			$ten = ( isset( $ten_hs[ $ma ] ) && '' !== trim( (string) $ten_hs[ $ma ] ) )
				? $ten_hs[ $ma ] : $x['ten'];
			if ( null !== $x['vao'] ) {
				VHCC_Nhan::ghi_gio( $coso, $x['ngay'], $ma, $ten, (int) $x['vao'], '', self::NGUON );
			}
			if ( null !== $x['ra'] ) {
				VHCC_Nhan::ghi_gio( $coso, $x['ngay'], $ma, $ten, (int) $x['ra'], '', self::NGUON );
			}
			$ghi++;

			/* 🔴 KHOẢNG NGHỈ GHI SAU CÙNG, VÀ PHẢI KIỂM KẾT QUẢ. `dat_nghi_giua()` chối khi hàng
			   trong sổ không bao được khoảng ấy — nghĩa là giờ trong sổ đã khác tệp (người ta sửa
			   tay, hoặc máy đã ghi khác). Ghi bừa một khoảng nghỉ vào một khung giờ khác là TRỪ
			   nhầm của người ta mấy tiếng, và trừ im lặng. Chối thì phải kể ra. */
			if ( null !== $x['nghiTu'] && null !== $x['nghiDen'] ) {
				if ( VHCC_Nhan::dat_nghi_giua( $coso, $x['ngay'], $ma, $x['nghiTu'], $x['nghiDen'] ) ) {
					$nghi_ghi++;
				} else {
					$canh[] = '🔴 ' . $x['ten'] . ' ' . $x['ngay'] . ': không đặt được khoảng nghỉ '
						. VHCC_DB::hhmm( $x['nghiTu'] ) . '–' . VHCC_DB::hhmm( $x['nghiDen'] )
						. ' (giờ trong sổ đã khác tệp) — ngày này công sẽ TÍNH DƯ, xem lại tay.';
				}
			}
		}

		if ( $thieu > 0 ) {
			$canh[] = '⚠ Còn ' . $thieu . ' tên chưa ghép với người nào — ' . $bo_luot
				. ' lượt của họ KHÔNG được nạp. Chọn người cho từng tên rồi bấm lại.';
		}

		/* Kể ra những ngày hai bên CÙNG có giờ, và kể NẶNG nhất trước — ngày mà gộp khung lại
		   thì giờ công PHÌNH RA hơn cả hai nguồn. Đó là ngày trả dư nếu cứ nạp đè. */
		usort( $phinh, function ( $a, $b ) { return $b['them'] - $a['them']; } );
		$nang = array();
		foreach ( $phinh as $z ) { if ( $z['them'] > 0 ) { $nang[] = $z; } }
		if ( $nang ) {
			$canh[] = '🔴 ' . count( $nang ) . ' ngày mà sổ và bảng ghi KHÁC nhau, gộp lại thì giờ '
				. 'công PHÌNH RA (máy chấm ca này, bảng ghi ca kia, không có khoảng nghỉ nào ở giữa). '
				. ( $chi_trong
					? 'Đang bật "chỉ điền ngày còn trống" nên mấy ngày này KHÔNG bị đụng tới.'
					: '⚠ Đang TẮT "chỉ điền ngày còn trống" — mấy ngày này SẼ bị nới rộng khung.' );
			foreach ( array_slice( $nang, 0, 10 ) as $z ) {
				$canh[] = '   · ' . $z['ten'] . ' ' . $z['ngay'] . ': sổ ' . $z['cu']
					. ', bảng ' . $z['bang'] . ' → gộp thành ' . $z['moi']
					. ' (+' . self::gio_chu( $z['them'] * 60 ) . ')';
			}
			if ( count( $nang ) > 10 ) { $canh[] = '   …và ' . ( count( $nang ) - 10 ) . ' ngày nữa.'; }
		}
		if ( $bo_trung > 0 ) {
			$canh[] = 'Bỏ qua ' . $bo_trung . ' ngày đã có giờ trong sổ (đang bật "chỉ điền ngày '
				. 'còn trống"). Muốn nạp đè thì bỏ dấu tích ấy đi — nhưng đọc kỹ mấy dòng trên trước.';
		}

		return array( 'ok' => true, 'chi_xem' => (bool) $chi_xem, 'coSo' => $coso,
			'co_san' => $co_san,
			'thang' => $d['thang'], 'nguoi' => $nguoi, 'so_nguoi' => count( $d['nguoi'] ),
			'so_thieu' => $thieu, 'so_ngay' => count( $ngay_co ), 'so_luot' => count( $d['luot'] ),
			'da_ghi' => $ghi, 'bo_luot' => $bo_luot, 'nghi_ghi' => $nghi_ghi,
			'chi_trong' => (bool) $chi_trong, 'bo_trung' => $bo_trung,
			'so_trung' => count( $phinh ), 'so_phinh' => count( $nang ), 'canh' => $canh );
	}

	/**
	 * Sổ đã có bao nhiêu ngày công của cơ sở này trong tháng ấy.
	 *
	 * ⚠️ HỎI THEO KHOẢNG NGÀY, không `LIKE 'thang-%'`. `esc_like` che dấu `_` theo luật MySQL mà
	 *    SQLite không theo, nên cùng một câu cho hai kết quả khác nhau giữa máy thật và máy thử —
	 *    và sai theo hướng ĐẾM THIẾU, tức im lặng bỏ qua đúng cảnh báo cần đưa ra.
	 */
	private static function dem_thang_trong_so( $coso, $thang ) {
		global $wpdb;
		$bang = VHCC_DB::t( 'cham_cong' );
		$r = $wpdb->get_row( $wpdb->prepare(
			"SELECT COUNT(*) AS luot, COUNT(DISTINCT ma_nv) AS nguoi FROM $bang
			 WHERE coso=%s AND ngay >= %s AND ngay <= %s",
			$coso, $thang . '-01', $thang . '-31' ), ARRAY_A );
		return array(
			'luot'  => $r ? (int) $r['luot'] : 0,
			'nguoi' => $r ? (int) $r['nguoi'] : 0,
		);
	}

	/* ====================================================================== giữ tệp tạm */

	/**
	 * GIỮ BẢNG VỪA TẢI LÊN ĐỂ BƯỚC GHÉP TÊN DÙNG LẠI.
	 *
	 * 🔴 VÌ SAO PHẢI GIỮ. Màn này có hai bước: xem trước + ghép tên, rồi mới nạp thật. Ô chọn
	 *    tệp của trình duyệt KHÔNG điền lại được bằng mã — bảo mật của trình duyệt cấm. Không
	 *    giữ tệp thì mỗi lần sửa một ô ghép là phải chọn lại tệp, và ai cũng sẽ bỏ giữa chừng
	 *    rồi quay về gõ tay — tức là mất đúng cái mà anh Thắng hỏi ("không cần copy").
	 *
	 * ⚠️ MÃ PHẢI ĐOÁN KHÔNG RA, và chỉ CHÍNH NGƯỜI tải lên mới lấy lại được. Bảng công là dữ
	 *    liệu người thật; một mã đếm tăng dần là ai cũng đọc được bảng của cửa hàng khác.
	 */
	const GIU_GIAY = 3600;

	public static function giu_bang( $u, $hang ) {
		$ma = wp_generate_password( 24, false, false );
		set_transient( 'vhcc_napdoc_' . $ma, array(
			'nv'   => self::ai( $u ),
			'hang' => $hang,
		), self::GIU_GIAY );
		return $ma;
	}

	public static function lay_bang( $u, $ma ) {
		$ma = preg_replace( '/[^A-Za-z0-9]/', '', (string) $ma );
		if ( '' === $ma ) { return null; }
		$d = get_transient( 'vhcc_napdoc_' . $ma );
		if ( ! is_array( $d ) || ! isset( $d['hang'] ) ) { return null; }
		if ( self::ai( $u ) !== ( isset( $d['nv'] ) ? $d['nv'] : '' ) ) { return null; }
		return $d['hang'];
	}

	/** Dấu người dùng để buộc tệp tạm vào đúng một người. */
	private static function ai( $u ) {
		$a = (array) $u;
		$x = isset( $a['ma_nv'] ) ? strtoupper( trim( (string) $a['ma_nv'] ) ) : '';
		if ( '' === $x ) { $x = isset( $a['name'] ) ? (string) $a['name'] : ''; }
		return $x;
	}

	/**
	 * GIỜ ĐANG CÓ SẴN TRONG SỔ CỦA MỘT THÁNG — hỏi MỘT LẦN cho cả tháng.
	 *
	 * ⚠️ Hỏi từng ngày là một tháng 122 ngày công thành 122 truy vấn, và bước Xem trước (thứ
	 *    người ta bấm đi bấm lại mỗi lần sửa một ô ghép tên) sẽ ì ra thấy rõ.
	 *
	 * @return array [ "MÃ|yyyy-mm-dd" => array( vao, ra, nghiTu, nghiDen ) ] — chỉ hàng CÓ giờ.
	 */
	private static function gio_dang_co( $coso, $thang ) {
		global $wpdb;
		$bang = VHCC_DB::t( 'cham_cong' );
		$rows = VHCC_DB::rows( $wpdb->prepare(
			"SELECT ma_nv, ngay, gio_vao_giay, gio_ra_giay, nghi_tu_giay, nghi_den_giay
			 FROM $bang WHERE coso=%s AND ngay >= %s AND ngay <= %s",
			$coso, $thang . '-01', $thang . '-31' ) );
		$ra = array();
		foreach ( (array) $rows as $r ) {
			$v = ( null === $r['gio_vao_giay'] || '' === $r['gio_vao_giay'] ) ? null : (int) $r['gio_vao_giay'];
			$x = ( null === $r['gio_ra_giay'] || '' === $r['gio_ra_giay'] ) ? null : (int) $r['gio_ra_giay'];
			if ( null === $v && null === $x ) { continue; }   // hàng rỗng không tính là "đã có giờ"
			$ra[ $r['ma_nv'] . '|' . $r['ngay'] ] = array(
				'vao'     => $v,
				'ra'      => $x,
				'nghiTu'  => ( null === $r['nghi_tu_giay'] || '' === $r['nghi_tu_giay'] ) ? null : (int) $r['nghi_tu_giay'],
				'nghiDen' => ( null === $r['nghi_den_giay'] || '' === $r['nghi_den_giay'] ) ? null : (int) $r['nghi_den_giay'],
			);
		}
		return $ra;
	}

	/**
	 * NẾU NẠP ĐÈ THÌ KHUNG GIỜ THÀNH GÌ, VÀ DƯ RA BAO NHIÊU PHÚT.
	 *
	 * Mô phỏng đúng phép NỚI của `VHCC_Nhan::ghi_gio()`: lấy đầu sớm nhất và đuôi muộn nhất của
	 * cả hai bên, và KHÔNG sinh khoảng nghỉ nào ở giữa — vì hai nguồn không biết nhau.
	 *
	 * 🔴 `them` là số phút DƯ RA so với nguồn NHIỀU GIỜ HƠN, không phải so với sổ. Ngày mà bảng
	 *    đúng hơn sổ (bảng 8 giờ, sổ 4 giờ, gộp ra 8 giờ) thì nạp đè là SỬA ĐÚNG, không phải làm
	 *    phình — kể nó ra cùng một rổ với ngày trả dư là làm loãng đúng cái cảnh báo cần đọc.
	 */
	private static function gop_khung( $cu, $x ) {
		$bv = ( null === $x['vao'] ) ? null : (int) $x['vao'];
		$br = ( null === $x['ra'] ) ? null : (int) $x['ra'];
		$cv = $cu['vao'];
		$cr = $cu['ra'];

		$gio = function ( $v, $r, $ntu = null, $nden = null ) {
			if ( null === $v || null === $r ) { return 0; }
			return (int) VHCC_Pdf::phut_lam( $v, $r, $ntu, $nden );
		};
		$p_cu   = $gio( $cv, $cr, $cu['nghiTu'], $cu['nghiDen'] );
		$p_bang = $gio( $bv, $br, $x['nghiTu'], $x['nghiDen'] );

		$mv = ( null === $cv ) ? $bv : ( ( null === $bv ) ? $cv : min( $cv, $bv ) );
		$mr = ( null === $cr ) ? $br : ( ( null === $br ) ? $cr : max( $cr, $br ) );
		/* ═══════════════════════════════════════════════════════════════════════════════════
		 * 🔴 KHOẢNG NGHỈ SAU KHI GỘP: BẢNG THẮNG, RỒI MỚI ĐẾN SỔ. Bản đầu chỉ xét khoảng nghỉ
		 *    của SỔ và bỏ quên của BẢNG — phép thử bắt được ngay: sổ 09:00→13:00 gặp bảng
		 *    08:30→22:00 (nghỉ 13:00–17:05) bị báo là "phình +4:05", trong khi thật ra `nap()`
		 *    gọi `dat_nghi_giua()` với khoảng của BẢNG và ra đúng 9h25 — không phình một phút nào.
		 *    Một lời cảnh báo sai chỗ còn tệ hơn không cảnh báo: nó dạy người ta bỏ qua cảnh báo.
		 *
		 * Thứ tự phải khớp ĐÚNG việc `nap()` làm:
		 *   · bảng CÓ khoảng nghỉ và nó nằm gọn trong khung mới -> `dat_nghi_giua()` nhận, lấy nó;
		 *   · bảng có nhưng không lọt (hàm ấy chối) -> khoảng của sổ còn nguyên, xét tới nó;
		 *   · bảng không có -> `nap()` không gọi gì, khoảng của sổ giữ nguyên.
		 * ═══════════════════════════════════════════════════════════════════════════════════ */
		$lot = function ( $tu, $den ) use ( $mv, $mr ) {
			return ( null !== $tu && null !== $den && null !== $mv && null !== $mr
				&& $tu >= $mv && $den <= $mr );
		};
		$b_tu  = isset( $x['nghiTu'] ) ? $x['nghiTu'] : null;
		$b_den = isset( $x['nghiDen'] ) ? $x['nghiDen'] : null;
		if ( $lot( $b_tu, $b_den ) ) {
			$ntu = $b_tu; $nden = $b_den;
		} elseif ( $lot( $cu['nghiTu'], $cu['nghiDen'] ) ) {
			$ntu = $cu['nghiTu']; $nden = $cu['nghiDen'];
		} else {
			$ntu = null; $nden = null;
		}
		$p_moi = $gio( $mv, $mr, $ntu, $nden );

		$hm = function ( $v, $r ) {
			return ( null === $v ? '—' : VHCC_DB::hhmm( $v ) ) . '→' . ( null === $r ? '—' : VHCC_DB::hhmm( $r ) );
		};
		return array(
			'ten'  => $x['ten'],
			'ngay' => $x['ngay'],
			'cu'   => $hm( $cv, $cr ),
			'bang' => $hm( $bv, $br ),
			'moi'  => $hm( $mv, $mr ),
			'them' => $p_moi - max( $p_cu, $p_bang ),
		);
	}

	/* ========================================================================== sổ ghép tên */

	/**
	 * SỔ GHÉP TÊN — "N.Kiệt" (trong bảng cũ) => mã NV, nhớ theo từng cơ sở.
	 *
	 * Anh Thắng 21/09/2026 chọn *"Màn ghép tên, nhớ cho lần sau"*.
	 *
	 * ⚠️ NHỚ THEO CƠ SỞ, không nhớ chung. Hai cửa hàng đều có một cô "Ngân" là chuyện thường,
	 *    và một sổ chung sẽ lặng lẽ rót công của cô này sang mã của cô kia.
	 * ⚠️ MỘT KHOÁ CHO TẤT CẢ CƠ SỞ, không mỗi cơ sở một khoá: cột `khoa` chỉ có 120 ký tự, và
	 *    một mã cơ sở dài là khoá bị cắt cụt — hai cơ sở khác nhau dùng chung một dòng.
	 */
	const KHOA_GHEP = 'napdoc_ghep';

	public static function so_ghep( $coso ) {
		$coso = VHCC_NhanSu::chuan_coso( $coso );
		$tat = VHCC_Luong::cai_dat( self::KHOA_GHEP, array() );
		if ( ! is_array( $tat ) || ! isset( $tat[ $coso ] ) || ! is_array( $tat[ $coso ] ) ) {
			return array();
		}
		$ra = array();
		foreach ( $tat[ $coso ] as $t => $ma ) {
			$t  = trim( (string) $t );
			$ma = trim( (string) $ma );
			if ( '' !== $t && '' !== $ma ) { $ra[ $t ] = $ma; }
		}
		return $ra;
	}

	public static function luu_ghep( $u, $coso, $map ) {
		$coso = VHCC_NhanSu::chuan_coso( $coso );
		if ( '' === $coso ) { return false; }
		$tat = VHCC_Luong::cai_dat( self::KHOA_GHEP, array() );
		if ( ! is_array( $tat ) ) { $tat = array(); }
		$cu = ( isset( $tat[ $coso ] ) && is_array( $tat[ $coso ] ) ) ? $tat[ $coso ] : array();
		foreach ( (array) $map as $t => $ma ) {
			$t  = trim( (string) $t );
			$ma = trim( (string) $ma );
			if ( '' === $t ) { continue; }
			/* Chọn lại ô trống = GỠ phép ghép, không phải giữ nguyên cái cũ. Không gỡ được thì
			   một phép ghép sai nằm lại trong sổ vĩnh viễn, mà màn thì trông như đã sửa xong. */
			if ( '' === $ma ) { unset( $cu[ $t ] ); } else { $cu[ $t ] = $ma; }
		}
		$tat[ $coso ] = $cu;
		VHCC_Luong::dat_cai_dat( self::KHOA_GHEP, $tat, $u );
		return true;
	}

	/** Chỉ giữ những khoá CÓ THẬT trong tệp — ô lạ gửi lên không được chui vào sổ. */
	private static function loc_map( $map, $nguoi ) {
		$ra = array();
		foreach ( (array) $map as $t => $ma ) {
			$t = trim( (string) $t );
			if ( '' === $t || ! in_array( $t, (array) $nguoi, true ) ) { continue; }
			$ma = trim( (string) $ma );
			if ( '' !== $ma ) { $ra[ $t ] = $ma; }
		}
		return $ra;
	}

	/**
	 * GỢI Ý MÃ NV CHO MỘT TÊN VIẾT TẮT.
	 *
	 * Bảng cũ ghi tên gọi, có khi kèm chữ đầu: `Ngân`, `N.Kiệt`, `K.Oanh`. Hồ sơ thì ghi đủ
	 * `Nguyễn Tuấn Kiệt`. Luật khớp: TIẾNG CUỐI phải trùng, và mỗi chữ viết tắt phía trước phải
	 * khớp (theo đúng thứ tự) với một tiếng đứng trước trong họ tên.
	 *
	 * 🔴 CHỈ GỢI Ý, KHÔNG BAO GIỜ TỰ LẤY. Ghép nhầm hai người là công của người này chui vào
	 *    bảng lương người kia — sai im lặng, và sai ra tiền. Vì vậy hàm này trả về CẢ DANH SÁCH
	 *    khớp; ra nhiều hơn một người thì màn để trống ô chọn, bắt người ta tự quyết.
	 *
	 * @return array danh sách mã NV khớp (có thể rỗng, có thể nhiều).
	 */
	public static function goi_y( $ten, $ds_nv ) {
		$phan = preg_split( '/[.\s_·,\-]+/u', trim( (string) $ten ), -1, PREG_SPLIT_NO_EMPTY );
		if ( ! $phan ) { return array(); }
		$cuoi = VHCC_Luong::bo_chu( $phan[ count( $phan ) - 1 ] );
		if ( '' === $cuoi ) { return array(); }
		$tat = array();
		for ( $i = 0; $i < count( $phan ) - 1; $i++ ) {
			$c = VHCC_Luong::bo_chu( $phan[ $i ] );
			if ( '' !== $c ) { $tat[] = $c; }
		}

		$ra = array();
		foreach ( (array) $ds_nv as $hs ) {
			$ma = isset( $hs['ma_nv'] ) ? trim( (string) $hs['ma_nv'] ) : '';
			if ( '' === $ma ) { continue; }
			/* Tên trong tệp CHÍNH LÀ mã NV -> khớp thẳng, khỏi đoán. */
			if ( 0 === strcasecmp( $ma, trim( (string) $ten ) ) ) { return array( $ma ); }
			$tu = preg_split( '/\s+/u', trim( (string) ( isset( $hs['ho_ten'] ) ? $hs['ho_ten'] : '' ) ),
				-1, PREG_SPLIT_NO_EMPTY );
			if ( ! $tu ) { continue; }
			if ( VHCC_Luong::bo_chu( $tu[ count( $tu ) - 1 ] ) !== $cuoi ) { continue; }
			$truoc = array();
			for ( $i = 0; $i < count( $tu ) - 1; $i++ ) { $truoc[] = VHCC_Luong::bo_chu( $tu[ $i ] ); }
			if ( self::khop_tat( $tat, $truoc ) ) { $ra[] = $ma; }
		}
		return $ra;
	}

	/** Mỗi chữ viết tắt khớp một tiếng đứng trước, THEO THỨ TỰ, không dùng lại tiếng đã khớp. */
	private static function khop_tat( $tat, $truoc ) {
		$j = 0;
		foreach ( $tat as $t ) {
			$thay = false;
			while ( $j < count( $truoc ) ) {
				$w = $truoc[ $j ];
				$j++;
				if ( $w === $t || ( 1 === strlen( $t ) && '' !== $w && $w[0] === $t ) ) {
					$thay = true;
					break;
				}
			}
			if ( ! $thay ) { return false; }
		}
		return true;
	}

	/* ============================================================================== đọc ô */

	/**
	 * MỌI DÒNG TIÊU ĐỀ THÁNG TRONG CẢ TỆP — dùng để phát hiện nhiều bảng chồng nhau.
	 *
	 * ⚠️ QUÉT CẢ TỆP, không quét sáu dòng đầu như `tim_thang()`. Đó chính là chỗ hỏng: bảng thứ
	 *    hai trở đi nằm ở giữa tệp, và chỉ quét đầu tệp thì không bao giờ thấy chúng.
	 *
	 * 🔴 GIỮ CẢ BẢN TRÙNG, KHÔNG GỘP. Đây là chỗ em suýt để lọt: bản đầu gộp tháng trùng làm
	 *    một, nên hai bảng CÙNG MỘT THÁNG đếm ra 1 và chốt chồng bảng không nổ. Mà đó đúng là
	 *    ca của tệp thật — anh Thắng có HAI bảng cùng đề "THÁNG 8/2026", bảng sau là tháng khác
	 *    chép tiêu đề quên sửa. Chính cái ca nguy nhất lại là cái ca phép gộp bỏ qua.
	 *
	 * @return array tên tháng nguyên văn, theo thứ tự xuất hiện, GIỮ NGUYÊN bản trùng —
	 *               số phần tử = số bảng trong tệp.
	 */
	public static function ds_thang( $dong ) {
		$ra = array();
		foreach ( (array) $dong as $d ) {
			foreach ( (array) $d as $o ) {
				$t = trim( (string) $o );
				if ( '' === $t ) { continue; }
				if ( ! preg_match( '#TH[ÁA]NG\s*(\d{1,2})\s*[/\-]\s*(\d{4})#iu', $t, $m ) ) { continue; }
				$ra[] = (int) $m[1] . '/' . $m[2];
				break;   // một DÒNG tiêu đề = một bảng, dù dòng ấy có mấy ô nhắc lại tháng
			}
		}
		return $ra;
	}

	/**
	 * "2026-08" -> "8/2026" — VIẾT ĐÚNG KIỂU BẢNG GỐC ĐANG VIẾT.
	 *
	 * ⚠️ Không phải chuyện thẩm mỹ. Việc quan trọng nhất của màn Xem trước là để người ta soi
	 *    xem hệ có đọc ĐÚNG tháng không (tiêu đề bảng gõ tay nên gõ sai được — tệp thật của anh
	 *    Thắng có hai bảng cùng đề "THÁNG 8/2026"). Bắt người ta dịch "2026-08" trong đầu rồi
	 *    mới so với "THÁNG 8/2026" trên giấy là thêm một bước dễ bỏ qua, đúng ở chỗ không được
	 *    phép bỏ qua.
	 */
	public static function thang_chu( $thang ) {
		$t = trim( (string) $thang );
		if ( ! preg_match( '/^(\d{4})-(\d{2})$/', $t, $m ) ) { return $t; }
		return (int) $m[2] . '/' . $m[1];
	}

	/** "BẢNG CHẤM CÔNG THÁNG 4/2026" -> "2026-04". '' nếu không thấy. */
	public static function tim_thang( $dong ) {
		/* 🔴 QUÉT CẢ TỆP, KHÔNG PHẢI SÁU DÒNG ĐẦU. Bảng anh Thắng xuất ra có khi chừa mấy dòng
		   trống hoặc một dòng ghi chú phía trên tiêu đề, và khi ấy bộ đọc chối một tệp hoàn toàn
		   đọc được. Chốt "chỉ một bảng một tệp" ở `doc()` đã lo phần chồng bảng, nên quét rộng ở
		   đây không còn rủi ro lấy nhầm tiêu đề của bảng khác. */
		foreach ( (array) $dong as $d ) {
			foreach ( (array) $d as $o ) {
				$s = trim( (string) $o );
				if ( '' === $s ) { continue; }
				if ( preg_match( '#TH[ÁA]NG\s*(\d{1,2})\s*[/\-]\s*(\d{4})#iu', $s, $m ) ) {
					$t = (int) $m[1];
					if ( $t >= 1 && $t <= 12 ) {
						return $m[2] . '-' . str_pad( (string) $t, 2, '0', STR_PAD_LEFT );
					}
				}
			}
		}
		return '';
	}

	/** Chỉ số dòng có "Check in". null nếu không thấy. */
	private static function tim_dong_cot( $dong ) {
		foreach ( (array) $dong as $i => $d ) {
			foreach ( (array) $d as $o ) {
				if ( 'check in' === strtolower( trim( (string) $o ) ) ) { return $i; }
			}
		}
		return null;
	}

	/**
	 * Các cụm cột của từng người.
	 *
	 * 🔴 DÒ "Check in" TỪ CHÍNH DÒNG CỘT, không cộng +3 từ cụm trước. Bảng của người ta có thể
	 *    chèn thêm một cột ghi chú giữa chừng, và mọi phép cộng cố định lệch hết — lệch kiểu ấy
	 *    không báo lỗi, nó chỉ lấy nhầm ô và cả tháng sai giờ.
	 *
	 * @return array [ ['cot'=>int, 'ten'=>string, 'nhan'=>string] ]
	 */
	private static function tim_cum( $d_ten, $d_cot ) {
		$ra = array();
		$ten_gan = '';
		foreach ( (array) $d_cot as $c => $o ) {
			$t = trim( (string) ( isset( $d_ten[ $c ] ) ? $d_ten[ $c ] : '' ) );
			/* Tên chỉ ghi ở cột đầu cụm (ô gộp) — nhớ lại tên gần nhất cho hai ô sau. */
			if ( '' !== $t && '0' !== $t ) { $ten_gan = $t; }
			if ( 'check in' !== strtolower( trim( (string) $o ) ) ) { continue; }
			if ( '' === $ten_gan ) { continue; }
			$ra[] = array( 'cot' => (int) $c, 'ten' => self::ten_goc( $ten_gan ), 'nhan' => $ten_gan );
		}
		return $ra;
	}

	/**
	 * "K.Oanh (LT)" -> "K.Oanh".
	 *
	 * Anh Thắng 21/09/2026: hai cụm là HAI CA của CÙNG một người. Nên phần trong ngoặc bị bỏ để
	 * hai cụm gom về một tên — và từ đó về một mã NV.
	 *
	 * ⚠️ CHỈ BỎ NGOẶC Ở CUỐI. Bỏ mọi thứ trong ngoặc ở bất kỳ đâu thì "Nguyễn (Bé) Hai" cụt mất
	 *    phần giữa, và hai người khác nhau có thể gom nhầm về một.
	 */
	public static function ten_goc( $s ) {
		$t = trim( (string) $s );
		$t = preg_replace( '/\s*\([^()]*\)\s*$/u', '', $t );
		return trim( $t );
	}

	/**
	 * Ô CỘT ĐẦU -> SỐ NGÀY, hoặc null (Lễ · Bù · Biên Bản · Tổng · rỗng).
	 *
	 * ═════════════════════════════════════════════════════════════════════════════════════════
	 * 🔴 NHẬN CẢ NHÃN CÓ ĐUÔI — `1 (LỄ*2)`, `26 (27 TẾT)`, `29 M1`, `1 Mùng 4`, `7 28t`.
	 * ═════════════════════════════════════════════════════════════════════════════════════════
	 * Bản đầu đòi ô ngày phải là SỐ TRẦN (`^\d{1,2}$`). Tệp thật anh Thắng gửi 21/09/2026 có 17
	 * dòng kiểu ấy, và dòng nào cũng CÓ GIỜ — riêng dòng `1 (LỄ*2)` của tháng 5/2025 có 18 ô giờ
	 * của 7 người. Đòi số trần là nuốt sạch mấy ngày đó, IM LẶNG: không lỗi, không cảnh báo, chỉ
	 * là bảng công thiếu vài ngày mà toàn những ngày lễ tết — tức những ngày TRẢ NHIỀU TIỀN NHẤT.
	 *
	 * ⚠️ PHẢI CÓ RANH GIỚI SAU SỐ (`\b`), kẻo `2024` đọc thành ngày 20 và một dòng tiêu đề lạc
	 *    vào giữa bảng biến thành một ngày công.
	 * ⚠️ `Lễ`, `Bù`, `Tổng`, `Biên Bản` vẫn rơi về null vì không mở đầu bằng số — đúng ý: đó là
	 *    dòng tổng kết, không phải ngày. Riêng dòng `Bù` CÓ KHI mang giờ thật (tệp anh Thắng có
	 *    một dòng `17:01 22:06 05:05`) nhưng không có ngày nào để gắn vào, nên không đoán.
	 */
	private static function so_ngay( $o ) {
		$s = trim( (string) $o );
		if ( '' === $s || ! preg_match( '/^(\d{1,2})\b/u', $s, $m ) ) { return null; }
		$n = (int) $m[1];
		return ( $n >= 1 && $n <= 31 ) ? $n : null;
	}

	/** Phần ghi chú sau số ngày ("1 (LỄ*2)" -> "(LỄ*2)"), hoặc '' khi ô là số trần. */
	private static function chu_ngay( $o ) {
		$s = trim( (string) $o );
		if ( '' === $s || ! preg_match( '/^\d{1,2}\b(.*)$/u', $s, $m ) ) { return ''; }
		return trim( $m[1] );
	}

	/**
	 * Ô CÓ CHỮ NHƯNG KHÔNG ĐỌC ĐƯỢC THÀNH GIỜ — `11:4`, `11:0`, `abc`.
	 *
	 * ⚠️ `0` KHÔNG TÍNH LÀ GÕ SAI. Cả bảng điền `0` cho ô không có ca; kêu lên ở đó là mỗi tháng
	 *    đẻ ra hàng nghìn dòng cảnh báo và không ai đọc dòng nào nữa.
	 */
	private static function gio_xau( $o ) {
		$s = trim( (string) $o );
		if ( '' === $s ) { return false; }
		/* ⚠️ MỌI CÁCH VIẾT SỐ KHÔNG đều rơi về đây: `0`, `00`, `0,0`, `0.00`. Bản đầu liệt kê
		   riêng chuỗi `0` rồi mới tới khuôn này — thừa, và phá thử cho thấy gỡ dòng ấy bài vẫn
		   xanh vì khuôn dưới đỡ hộ. Một chốt là đủ, và một chốt thì phá thử soi được. */
		if ( preg_match( '/^0+([.,]0+)?$/', $s ) ) { return false; }
		return ( null === self::gio( $s ) );
	}

	/**
	 * Ô giờ -> giây trong ngày, hoặc null.
	 *
	 * 🔴 `0` LÀ Ô TRỐNG, KHÔNG PHẢI NỬA ĐÊM. Bảng của anh Thắng điền `0` vào mọi ô không có ca.
	 *    Đọc nó thành 00:00:00 là mỗi người tự nhiên có một lượt chấm lúc nửa đêm ở mọi ngày
	 *    nghỉ — và bảng công đầy những ngày công 0 giờ mà nhìn thì tưởng có đi làm.
	 */
	private static function gio( $o ) {
		$s = trim( (string) $o );
		if ( '' === $s ) { return null; }

		$giay = null;
		/* Excel có thể trả về ô giờ dưới dạng số thập phân của một ngày (0.5 = 12:00). */
		if ( preg_match( '/^\d*\.\d+$/', $s ) ) {
			$giay = (int) round( (float) $s * 86400 );
		} elseif ( preg_match( '/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $s, $m ) ) {
			$g = (int) $m[1];
			$p = (int) $m[2];
			$y = isset( $m[3] ) ? (int) $m[3] : 0;
			if ( $g > 23 || $p > 59 || $y > 59 ) { return null; }
			$giay = $g * 3600 + $p * 60 + $y;
		} else {
			return null;
		}

		/* 🔴 CHẶN Ở ĐÂY, SAU KHI ĐÃ QUY VỀ GIÂY — không chặn theo mặt chữ.
		   Bản đầu liệt kê mấy dạng chữ (`0`, `00:00`, `00:00:00`) và tưởng thế là đủ. Phá thử
		   cho thấy bỏ hẳn dòng ấy mà bài vẫn XANH: chuỗi `0` trơn không khớp khuôn giờ nên rơi
		   về null hộ. Rồi phép thử mới lộ ra dạng còn sót: ô số `0.0` của Excel — nó KHỚP nhánh
		   thập phân, ra 0 giây, và thành một lượt chấm lúc nửa đêm.
		   Quy về giây rồi mới chặn thì mọi dạng viết của số không đều rơi về một chỗ.

		   ⚠️ CÁI GIÁ: ai đó chấm công đúng 00:00:00 thật thì cũng bị bỏ. Chấp nhận được — trong
		      bảng này `0` nghĩa là KHÔNG CÓ CA, và điều đó đúng với hàng nghìn ô, còn lượt chấm
		      đúng nửa đêm thì chưa từng thấy một ô nào. */
		return ( $giay > 0 ) ? $giay : null;
	}

	private static function ngay_that( $ngay ) {
		list( $y, $m, $d ) = array_map( 'intval', explode( '-', $ngay ) );
		return checkdate( $m, $d, $y );
	}
}
