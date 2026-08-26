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
