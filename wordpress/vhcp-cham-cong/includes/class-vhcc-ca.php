<?php
/**
 * CA LÀM VIỆC — khai ca theo cơ sở, và TÁCH một lượt chấm ra thành từng ca.
 *
 * Anh Thắng 26/08/2026: *"bổ sung phần tách ca, để biết bạn đó làm ca nào, ca đó mấy tiếng, từ
 * ca nào đến ca nào"*.
 *
 * ⚠️ KHÁC bản Apps Script một chỗ có chủ ý. Bên đó (`getWorkPayReport`) chỉ tính ca mà người ta
 *    ĐÃ ĐƯỢC XẾP LỊCH trước: không có dòng trong sheet lịch thì lượt chấm ấy bị bỏ qua hoàn
 *    toàn. Ở đây tách ca SUY TỪ CHÍNH GIỜ CHẤM, không cần lịch — vì cơ sở của anh Thắng hiện có
 *    hàng nghìn lượt chấm mà chưa có lịch xếp, và một bảng bỏ trắng tất cả thì chẳng nói lên gì.
 *    Xếp lịch vẫn có ích (để biết ĐÁNG LẼ ai làm ca nào), nhưng nó là câu hỏi khác.
 *
 * 🔴 CA QUA NỬA ĐÊM LÀ CHUYỆN THƯỜNG, KHÔNG PHẢI NGOẠI LỆ.
 *    Ca 3 chạy 22:00 → 06:00. Nếu chỉ so `tu <= x && x < den` thì ca này KHÔNG BAO GIỜ khớp
 *    (22:00 không nhỏ hơn 06:00), và cả ca đêm của mọi cơ sở biến mất khỏi bảng mà không có gì
 *    báo — chỉ là tổng giờ tự nhiên hụt đi. Nên mọi phép so đều làm trên trục PHÚT TUYỆT ĐỐI và
 *    thử cả ba vị trí của khung ca: hôm qua, hôm nay, ngày mai.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_Ca {

	/** Khoá lưu trong bảng `cai_dat`. Bản đồ { 'CO_SO' => [ ca… ], '*' => [ ca… ] }. */
	const O = 'CA_CO_SO';

	/** Ca mặc định — đúng bộ ba của bản gốc (`DEFAULT_SHIFTS`). */
	const MAC_DINH = array(
		array( 'ten' => 'Ca 1', 'tu' => '06:00', 'den' => '14:00', 'tuW' => '', 'denW' => '' ),
		array( 'ten' => 'Ca 2', 'tu' => '14:00', 'den' => '22:00', 'tuW' => '', 'denW' => '' ),
		array( 'ten' => 'Ca 3', 'tu' => '22:00', 'den' => '06:00', 'tuW' => '', 'denW' => '' ),
	);

	/** Lượt ngắn hơn ngần này thì KHÔNG kể vào một ca — tránh rác vài phút do bấm nhầm. */
	const PHUT_TOI_THIEU = 15;

	/* ==================================================================== khai ca */

	public static function ban_do() {
		$m = VHCC_Luong::cai_dat( self::O, array() );
		return is_array( $m ) ? $m : array();
	}

	/**
	 * Ca của một cơ sở: khai riêng -> khai chung ('*') -> mặc định.
	 * Cùng thứ tự dò của bản gốc (`_shiftsOf`) — đổi thứ tự là đổi giờ công của cả cơ sở.
	 */
	public static function cua( $coso ) {
		$coso = VHCC_NhanSu::chuan_coso( $coso );
		$m    = self::ban_do();
		foreach ( array( $coso, '*' ) as $k ) {
			if ( '' !== $k && ! empty( $m[ $k ] ) && is_array( $m[ $k ] ) ) {
				$ds = self::lam_sach( $m[ $k ] );
				if ( $ds ) { return $ds; }
			}
		}
		return self::MAC_DINH;
	}

	/** Cơ sở này đang dùng ca RIÊNG hay đang mượn ca chung / mặc định. */
	public static function nguon_ca( $coso ) {
		$coso = VHCC_NhanSu::chuan_coso( $coso );
		$m    = self::ban_do();
		if ( ! empty( $m[ $coso ] ) && self::lam_sach( $m[ $coso ] ) ) { return 'rieng'; }
		if ( ! empty( $m['*'] ) && self::lam_sach( $m['*'] ) )         { return 'chung'; }
		return 'mac_dinh';
	}

	/** Bỏ ca thiếu tên hoặc thiếu giờ — ca nửa vời làm lệch tổng mà không ai thấy. */
	public static function lam_sach( $ds ) {
		$ra = array();
		foreach ( (array) $ds as $s ) {
			$s   = (array) $s;
			$ten = trim( (string) ( isset( $s['ten'] ) ? $s['ten'] : '' ) );
			$tu  = self::gio( isset( $s['tu'] ) ? $s['tu'] : '' );
			$den = self::gio( isset( $s['den'] ) ? $s['den'] : '' );
			if ( '' === $ten || '' === $tu || '' === $den || $tu === $den ) { continue; }
			$ra[] = array( 'ten' => $ten, 'tu' => $tu, 'den' => $den,
				'tuW'  => self::gio( isset( $s['tuW'] ) ? $s['tuW'] : '' ),
				'denW' => self::gio( isset( $s['denW'] ) ? $s['denW'] : '' ) );
		}
		return $ra;
	}

	public static function luu( $u, $coso, $ds ) {
		if ( ! VHCC_Vai::duoc( $u, 'lich_lam' ) ) {
			return array( 'ok' => false, 'error' => 'Khai ca cần quyền Cửa hàng trưởng trở lên.' );
		}
		$coso = VHCC_NhanSu::chuan_coso( $coso );
		if ( '' === $coso ) { return array( 'ok' => false, 'error' => 'Chưa chọn cơ sở.' ); }
		if ( ! VHCC_NhanSu::co_quyen_coso( $u, $coso ) ) {
			return array( 'ok' => false, 'error' => 'Không có quyền cơ sở này.' );
		}
		$sach = self::lam_sach( $ds );
		$m    = self::ban_do();
		/* Danh sách RỖNG = "bỏ khai riêng, quay về dùng ca chung", chứ không phải "cơ sở này
		   không có ca nào". Không có ca nào thì mọi giờ công rơi ra ngoài mọi ca. */
		if ( $sach ) { $m[ $coso ] = $sach; } else { unset( $m[ $coso ] ); }
		VHCC_Luong::dat_cai_dat( self::O, $m, $u );
		return array( 'ok' => true, 'so_ca' => count( $sach ), 'coSo' => $coso );
	}

	/* ==================================================================== tách ca */

	/**
	 * Số phút GIAO NHAU giữa [a1,a2] và [b1,b2] trên trục phút tuyệt đối. 0 nếu không giao.
	 * Hàm thuần, không biết gì về ca hay ngày — nên thử được bằng con số trần.
	 */
	public static function giao( $a1, $a2, $b1, $b2 ) {
		$tu  = max( (int) $a1, (int) $b1 );
		$den = min( (int) $a2, (int) $b2 );
		return ( $den > $tu ) ? ( $den - $tu ) : 0;
	}

	/**
	 * Tách một lượt chấm thành từng ca.
	 *
	 * @param array $ds_ca  ca của cơ sở (đã lấy qua `cua()`).
	 * @param bool  $cuoi_tuan  T7/CN thì dùng giờ cuối tuần nếu ca có khai.
	 * @param int   $vao_giay  giây trong ngày; có thể > 86400 nếu là hàng ca đêm đã trải phẳng.
	 * @return array ds[] mỗi phần tử { ten, tu, den, phut } + tong_phut + ngoai_ca (phút không
	 *               thuộc ca nào).
	 */
	public static function tach( $ds_ca, $vao_giay, $ra_giay, $cuoi_tuan = false ) {
		$trong = array( 'ds' => array(), 'tong_phut' => 0, 'ngoai_ca' => 0 );
		if ( null === $vao_giay || '' === $vao_giay || null === $ra_giay || '' === $ra_giay ) {
			return $trong;
		}
		$a1 = intdiv( (int) $vao_giay, 60 );
		$a2 = intdiv( (int) $ra_giay, 60 );
		if ( $a2 <= $a1 ) { return $trong; }
		$trong['tong_phut'] = $a2 - $a1;

		$da_phu = 0;
		foreach ( (array) $ds_ca as $s ) {
			$tu  = ( $cuoi_tuan && '' !== $s['tuW'] )  ? $s['tuW']  : $s['tu'];
			$den = ( $cuoi_tuan && '' !== $s['denW'] ) ? $s['denW'] : $s['den'];
			$b1  = self::phut( $tu );
			$b2  = self::phut( $den );
			if ( null === $b1 || null === $b2 ) { continue; }
			/* Ca qua nửa đêm: 22:00 -> 06:00 nghĩa là kết thúc ở ngày HÔM SAU. */
			if ( $b2 <= $b1 ) { $b2 += 1440; }
			/* Thử ca ở cả ba vị trí: hôm qua · hôm nay · ngày mai. Một lượt vào 02:00 thuộc ca
			   đêm của HÔM QUA, còn một lượt vào 23:00 kéo sang ca đêm của HÔM NAY — không xét đủ
			   ba thì một trong hai luôn rơi ra ngoài. */
			$phut = 0;
			foreach ( array( -1440, 0, 1440 ) as $dich ) {
				$phut += self::giao( $a1, $a2, $b1 + $dich, $b2 + $dich );
			}
			if ( $phut < self::PHUT_TOI_THIEU ) { continue; }
			$trong['ds'][] = array( 'ten' => $s['ten'], 'tu' => $tu, 'den' => $den, 'phut' => $phut );
			$da_phu += $phut;
		}
		/* Giờ không thuộc ca nào — phải kể ra, đừng nuốt. Nuốt đi thì tổng theo ca hụt so với
		   tổng giờ làm mà không chỗ nào giải thích được phần hụt. */
		$trong['ngoai_ca'] = max( 0, $trong['tong_phut'] - $da_phu );
		return $trong;
	}

	/**
	 * ĐỘ DÀI của một ca, tính bằng phút. Ca qua nửa đêm đếm sang ngày hôm sau.
	 * Hàm thuần — vào là một khai báo ca, ra là một con số.
	 */
	public static function dai_ca( $s, $cuoi_tuan = false ) {
		$s   = (array) $s;
		$tu  = ( $cuoi_tuan && ! empty( $s['tuW'] ) )  ? $s['tuW']  : ( isset( $s['tu'] ) ? $s['tu'] : '' );
		$den = ( $cuoi_tuan && ! empty( $s['denW'] ) ) ? $s['denW'] : ( isset( $s['den'] ) ? $s['den'] : '' );
		$b1  = self::phut( $tu );
		$b2  = self::phut( $den );
		if ( null === $b1 || null === $b2 ) { return 0; }
		if ( $b2 <= $b1 ) { $b2 += 1440; }
		return $b2 - $b1;
	}

	/**
	 * LÀM TRÒN GIỜ CÔNG THEO KHUNG CA.
	 *
	 * Anh Thắng 27/08/2026: *"lấy giờ ca làm giờ công, cứ chấm trong ca (bao gồm vào ra) phần
	 * công đủ giờ (nhưng nếu bạn nào chấm thiếu giờ thì hiện cảnh báo ô vàng)"*. Và 28/08, khi
	 * nhìn lưới đầy 5.1 · 6.9 · 13.1: *"Chưa làm tròn giờ theo ca"*.
	 *
	 * 🔴 CON SỐ LẺ LÀ CON SỐ SAI Ở CỬA HÀNG TÍNH THEO CA. Người vào 05:57 và về 14:03 không làm
	 *    8.1 tiếng — họ làm ĐÚNG MỘT CA. Sáu phút thừa ấy là thời gian đi bộ từ cửa vào máy, và
	 *    cộng nó vào là trả tiền cho việc bấm máy sớm; ngược lại người bấm muộn ba phút bị trừ.
	 *    Cả hai đều sai, và sai theo hai hướng nên tổng tháng nhìn vẫn "hợp lý".
	 *
	 * Luật, đúng như anh nói:
	 *   • Có mặt trong ca, THIẾU không quá ngưỡng  -> tính TRỌN ca. Không cảnh báo.
	 *   • Thiếu QUÁ ngưỡng                          -> tính đúng phần có mặt, và BÁO thiếu (ô vàng).
	 *   • Lượt chấm không chạm ca nào               -> giữ nguyên giờ thật, và báo "ngoài mọi ca".
	 *
	 * ⚠️ NHÁNH "NGOÀI MỌI CA" KHÔNG ĐƯỢC TRẢ 0. Khung ca khai sai (hoặc chưa khai) thì mọi lượt
	 *    rơi ra ngoài — trả 0 là cả tháng của cả cửa hàng thành số không, mà bảng vẫn trông
	 *    bình thường. Giữ giờ thật và kêu lên là cách duy nhất để chuyện ấy lộ ra ngay.
	 *
	 * ⚠️ Giờ NGOÀI ca (làm thêm đầu/cuối) KHÔNG cộng vào — đây là "lấy giờ ca làm giờ công".
	 *    Nó vẫn nằm nguyên ở cột "Ngoài ca" của bảng Tổng giờ theo ca, không bị nuốt.
	 *
	 * Hàm THUẦN: vào là khai báo ca + hai mốc giây + ngưỡng, ra là một mảng con số.
	 *
	 * @return array phut (giờ công sau khi làm tròn, tính bằng phút) · tron (có ca nào được làm
	 *               tròn lên không) · thieu (mảng [ten, thieu_phut] của ca thiếu quá ngưỡng) ·
	 *               ngoai_moi_ca (bool).
	 */
	public static function lam_tron( $ds_ca, $vao_giay, $ra_giay, $cuoi_tuan = false, $nguong = 0 ) {
		$ra = array( 'phut' => 0, 'tron' => false, 'thieu' => array(), 'ngoai_moi_ca' => false );
		$tc = self::tach( $ds_ca, $vao_giay, $ra_giay, $cuoi_tuan );
		if ( ! $tc['ds'] ) {
			/* Không chạm ca nào: giữ giờ thật. `tong_phut` là 0 khi lượt chấm không hợp lệ, và
			   lúc ấy `ngoai_moi_ca` cũng chẳng có gì để kêu. */
			$ra['phut']         = (int) $tc['tong_phut'];
			$ra['ngoai_moi_ca'] = ( $ra['phut'] > 0 );
			return $ra;
		}
		$nguong = max( 0, (int) $nguong );
		foreach ( $tc['ds'] as $o ) {
			$dai = 0;
			foreach ( (array) $ds_ca as $c ) {
				if ( (string) $c['ten'] === (string) $o['ten'] ) { $dai = self::dai_ca( $c, $cuoi_tuan ); break; }
			}
			$co    = (int) $o['phut'];
			$thieu = $dai - $co;
			if ( $dai > 0 && $thieu > 0 && $thieu <= $nguong ) {
				$ra['phut'] += $dai;
				$ra['tron']  = true;
				continue;
			}
			$ra['phut'] += $co;
			if ( $dai > 0 && $thieu > $nguong ) {
				$ra['thieu'][] = array( 'ten' => (string) $o['ten'], 'phut' => (int) $thieu );
			}
		}
		return $ra;
	}

	/**
	 * Mã ngắn của ca theo VỊ TRÍ: ca thứ nhất -> C1, thứ hai -> C2…
	 *
	 * Dùng vị trí chứ không cắt từ tên, vì tên ca do người dùng đặt: "Ca sáng" và "Ca chiều" cắt
	 * ngắn kiểu nào cũng ra hai mã trông giống nhau, mà ô lưới thì chỉ rộng chừng ba ký tự. Vị
	 * trí thì luôn phân biệt được, và bảng chú giải ngay dưới lưới nói rõ C1 là ca nào.
	 */
	public static function ma_ngan( $i ) {
		return 'C' . ( (int) $i + 1 );
	}

	/**
	 * Ca ĂN NHIỀU GIỜ NHẤT trong một lượt — để tô màu ô theo đúng ca người ta làm chính.
	 * Trả về chỉ số trong danh sách ca, hoặc -1 nếu lượt không thuộc ca nào.
	 */
	public static function ca_chinh( $ds_ca, $tach ) {
		$nhieu = -1;
		$phut  = 0;
		foreach ( (array) $tach['ds'] as $o ) {
			if ( $o['phut'] <= $phut ) { continue; }
			foreach ( (array) $ds_ca as $i => $c ) {
				if ( $c['ten'] === $o['ten'] ) { $nhieu = (int) $i; $phut = (int) $o['phut']; }
			}
		}
		return $nhieu;
	}

	/** "C1·C2" — mã ngắn của MỌI ca lượt đó chạm vào, để in thẳng vào ô lưới. */
	public static function ma_o( $ds_ca, $tach ) {
		$ma = array();
		foreach ( (array) $tach['ds'] as $o ) {
			foreach ( (array) $ds_ca as $i => $c ) {
				if ( $c['ten'] === $o['ten'] ) { $ma[] = self::ma_ngan( $i ); }
			}
		}
		if ( ! empty( $tach['ngoai_ca'] ) && ! $ma ) { return '?'; }
		return implode( '·', $ma );
	}

	/** "Ca 1 → Ca 3" — câu trả lời cho *"từ ca nào đến ca nào"*. '' nếu không thuộc ca nào. */
	public static function tu_den( $tach ) {
		$ds = isset( $tach['ds'] ) ? (array) $tach['ds'] : array();
		if ( ! $ds ) { return ''; }
		$dau = $ds[0]['ten'];
		$cuoi = $ds[ count( $ds ) - 1 ]['ten'];
		return ( $dau === $cuoi ) ? $dau : ( $dau . ' → ' . $cuoi );
	}

	/** "Ca 1 5h · Ca 2 3h" — dòng chữ gọn để nhét vào chú thích rê chuột. */
	public static function chu( $tach ) {
		$c = array();
		foreach ( (array) $tach['ds'] as $x ) {
			$c[] = $x['ten'] . ' ' . VHCC_Cham::chu_gio( $x['phut'] ) . ' (' . $x['tu'] . '–' . $x['den'] . ')';
		}
		if ( ! empty( $tach['ngoai_ca'] ) ) {
			$c[] = 'ngoài ca ' . VHCC_Cham::chu_gio( $tach['ngoai_ca'] );
		}
		return implode( "\n", $c );
	}

	/* ==================================================================== xuất */

	/**
	 * Ba trang tính cho tệp .xlsx: chi tiết từng ca · tổng theo người × ca · từng lượt chấm.
	 *
	 * 🔴 Trang ĐẦU là thứ anh Thắng hỏi: *"chi tiết ca đó từ mấy h đến mấy h"*. Mỗi dòng là MỘT
	 *    CA của một người trong một ngày — không phải một ngày một dòng. Một người làm vắt hai
	 *    ca thì ra hai dòng, và mỗi dòng nói rõ khung ca lẫn số giờ thật nằm trong khung ấy.
	 *    Gộp lại một dòng là mất đúng cái người đọc cần: giờ nào thuộc ca nào.
	 */
	public static function to_xuat( $b, $coso ) {
		$ds_ca  = self::cua( $coso );
		$thu_vn = array( 'CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7' );

		$chi_tiet = array( array( 'Ngày', 'Thứ', 'Mã NV', 'Họ tên', 'Hàng', 'Giờ vào', 'Giờ ra',
			'Tổng giờ làm', 'Ca', 'Ca bắt đầu', 'Ca kết thúc', 'Giờ trong ca', 'Ghi chú' ) );
		$luot = array( array( 'Ngày', 'Thứ', 'Mã NV', 'Họ tên', 'Hàng', 'Giờ vào', 'Giờ ra',
			'Tổng giờ làm', 'Các ca', 'Từ ca → đến ca', 'Ghi chú' ) );
		$theo_nguoi = array();

		foreach ( (array) $b['hang'] as $r ) {
			$ngay  = (string) $r['ngay'];
			$thu   = $thu_vn[ (int) gmdate( 'w', strtotime( $ngay . ' 00:00:00 UTC' ) ) ];
			$ma    = (string) $r['maNV'];
			$ten   = (string) $r['hoTen'];
			$hang  = ( '' !== $r['hauTo'] ) ? $r['hauTo'] : 'chính';
			$vao   = (string) $r['vao'];
			$ra    = (string) $r['ra'];
			$gio   = ( null === $r['phut'] ) ? '' : round( $r['phut'] / 60, 2 );

			$ghi = '';
			if ( '' !== $vao && '' === $ra )      { $ghi = 'THIẾU GIỜ RA — quên bấm lúc về'; }
			elseif ( null === $r['phut'] && '' !== $vao && '' !== $ra ) {
				$ghi = 'GIỜ RA SỚM HƠN GIỜ VÀO — dấu hiệu ghi sai';
			}

			$x = self::tach( $ds_ca, $r['vaoGiay'], $r['raGiay'], self::la_cuoi_tuan( $ngay ) );

			$luot[] = array( $ngay, $thu, VHCC_Xuat::chu( $ma ), $ten, $hang, $vao, $ra, $gio,
				self::ma_o( $ds_ca, $x ), self::tu_den( $x ), $ghi );

			if ( ! isset( $theo_nguoi[ $ma ] ) ) {
				$theo_nguoi[ $ma ] = array( 'ten' => $ten, 'ca' => array(), 'ngoai' => 0 );
			}
			foreach ( $x['ds'] as $o ) {
				$chi_tiet[] = array( $ngay, $thu, VHCC_Xuat::chu( $ma ), $ten, $hang, $vao, $ra, $gio,
					$o['ten'], $o['tu'], $o['den'], round( $o['phut'] / 60, 2 ), $ghi );
				$theo_nguoi[ $ma ]['ca'][ $o['ten'] ] = ( isset( $theo_nguoi[ $ma ]['ca'][ $o['ten'] ] )
					? $theo_nguoi[ $ma ]['ca'][ $o['ten'] ] : 0 ) + (int) $o['phut'];
			}
			/* Lượt KHÔNG rơi vào ca nào vẫn phải có một dòng ở trang chi tiết — bỏ đi thì tổng
			   của trang chi tiết hụt so với trang lượt, và không ai biết hụt vì đâu. */
			if ( ! $x['ds'] ) {
				$chi_tiet[] = array( $ngay, $thu, VHCC_Xuat::chu( $ma ), $ten, $hang, $vao, $ra, $gio,
					'(ngoài ca)', '', '',
					$x['ngoai_ca'] ? round( $x['ngoai_ca'] / 60, 2 ) : '',
					'' !== $ghi ? $ghi : 'Không nằm trong khung ca nào đang khai' );
			}
			$theo_nguoi[ $ma ]['ngoai'] += (int) $x['ngoai_ca'];
		}

		uasort( $theo_nguoi, function ( $a, $c ) { return strcasecmp( $a['ten'], $c['ten'] ); } );

		$dau = array( 'Mã NV', 'Họ tên' );
		foreach ( $ds_ca as $c ) { $dau[] = $c['ten'] . ' (' . $c['tu'] . '–' . $c['den'] . ')'; }
		$dau[] = 'Ngoài ca';
		$dau[] = 'TỔNG giờ';
		$tong = array( $dau );
		foreach ( $theo_nguoi as $ma => $x ) {
			$dong = array( VHCC_Xuat::chu( $ma ), $x['ten'] );
			$cong = 0;
			foreach ( $ds_ca as $c ) {
				$p = isset( $x['ca'][ $c['ten'] ] ) ? (int) $x['ca'][ $c['ten'] ] : 0;
				$cong += $p;
				$dong[] = $p ? round( $p / 60, 2 ) : '';
			}
			$cong += (int) $x['ngoai'];
			$dong[] = $x['ngoai'] ? round( $x['ngoai'] / 60, 2 ) : '';
			$dong[] = round( $cong / 60, 2 );
			$tong[] = $dong;
		}

		return array(
			array( 'ten' => 'Chi tiết ca', 'hang' => $chi_tiet ),
			array( 'ten' => 'Tổng theo ca', 'hang' => $tong ),
			array( 'ten' => 'Từng lượt chấm', 'hang' => $luot ),
		);
	}

	/* ==================================================================== phụ */

	/** 'H:mm' / 'HH:mm' -> 'HH:mm'; rỗng hoặc sai -> ''. */
	public static function gio( $s ) {
		$s = trim( (string) $s );
		if ( '' === $s ) { return ''; }
		if ( ! preg_match( '/^(\d{1,2}):(\d{2})/', $s, $m ) ) { return ''; }
		$h = (int) $m[1];
		$p = (int) $m[2];
		if ( $h > 23 || $p > 59 ) { return ''; }
		return sprintf( '%02d:%02d', $h, $p );
	}

	/** 'HH:mm' -> phút trong ngày. null nếu không đọc được. */
	public static function phut( $s ) {
		$g = self::gio( $s );
		if ( '' === $g ) { return null; }
		list( $h, $p ) = explode( ':', $g );
		return (int) $h * 60 + (int) $p;
	}

	public static function la_cuoi_tuan( $ngay ) {
		$t = (int) gmdate( 'w', strtotime( (string) $ngay . ' 00:00:00 UTC' ) );
		return ( 0 === $t || 6 === $t );
	}
}
