<?php
/**
 * VCG_DB — bảng dữ liệu của bản chấm công dựng mới, và luật gộp giờ.
 *
 * BA BẢNG, KHÔNG PHẢI MỘT
 *   cong_nv        hồ sơ người   — một dòng một người
 *   cong_nv_donvi  gán cơ sở     — một dòng một cặp người–đơn vị
 *   cong_ngay      chấm công     — một dòng một người một ngày một cơ sở
 *
 * Tách `cong_nv_donvi` ra riêng vì anh Thắng 25/08/2026: *"nhân viên có thể làm ở nhiều gian
 * qua cột đơn vị làm việc"*. Nhét cơ sở vào một ô trong `cong_nv` là mỗi người còn đúng một
 * cơ sở, và tệp thật có 21 người mất dữ liệu ngay lần nạp đầu.
 *
 * @package vhcp-cong
 */

if ( ! defined( 'ABSPATH' ) ) { if ( ! defined( 'VCG_TEST' ) ) { exit; } }

class VCG_DB {

	const PHIEN_BAN = '1.1.0';

	public static function bang( $ten ) {
		global $wpdb;
		return $wpdb->prefix . 'cong_' . $ten;
	}

	/**
	 * Luật gộp GIỜ VÀO / GIỜ RA — chỗ duy nhất quyết định TIỀN LƯƠNG.
	 *
	 * Ô giờ vào và giờ ra là cặp **[sớm nhất, muộn nhất] của ngày**, và **chỉ được NỚI RỘNG,
	 * không bao giờ THU HẸP**.
	 *
	 * 🔴 Vì sao luật này bắt buộc: anh Thắng nạp CSV, và người ta nạp lại. Nạp lại cả tháng,
	 *    nạp đè tệp cũ, nạp hai tệp chồng lấn nhau, nạp lúc nửa chừng rồi nạp tiếp. Nếu phép
	 *    gộp không cho ra CÙNG MỘT kết quả bất kể thứ tự và số lần, thì mỗi lần nạp lại là một
	 *    bảng công khác, và không ai biết bảng nào đúng. Luật "chỉ nới rộng" làm phép gộp không
	 *    phụ thuộc thứ tự — nạp kiểu gì cũng ra một kết quả.
	 *
	 * Tách thành hàm THUẦN, không đụng cơ sở dữ liệu, để thử được bằng con số.
	 * Xem tools/test/kiem-nap-cong.php.
	 *
	 * @param int|null $cu_vao  giờ vào đang có (giây), null nếu chưa có
	 * @param int|null $cu_ra   giờ ra đang có (giây), null nếu chưa có
	 * @param int|null $m_vao   giờ vào của lượt mới
	 * @param int|null $m_ra    giờ ra của lượt mới
	 * @return array{vao:?int,ra:?int,doi:bool}
	 */
	public static function gop_gio( $cu_vao, $cu_ra, $m_vao, $m_ra ) {
		/* Gom mọi mốc giờ có mặt rồi lấy sớm nhất / muộn nhất. Cách này đúng theo định nghĩa của
		   hai ô đó, và tự nhiên không phụ thuộc thứ tự — không cần bốn nhánh if lồng nhau, cũng
		   không có nhánh nào để quên. */
		$moc = array();
		foreach ( array( $cu_vao, $cu_ra, $m_vao, $m_ra ) as $g ) {
			if ( null !== $g ) { $moc[] = (int) $g; }
		}
		if ( ! $moc ) { return array( 'vao' => null, 'ra' => null, 'doi' => false ); }
		$vao = min( $moc );
		$ra  = max( $moc );
		/* Một mốc duy nhất trong ngày -> đó là GIỜ VÀO, ô giờ ra còn trống. Đặt bằng nhau là
		   sinh ra ca "làm 0 phút" trông như đã ra ca, và bảng công cộng thiếu một buổi. */
		if ( $vao === $ra ) { $ra = null; }
		$doi = ( $vao !== $cu_vao ) || ( $ra !== $cu_ra );
		return array( 'vao' => $vao, 'ra' => $ra, 'doi' => $doi );
	}

	/**
	 * Trải phẳng trục thời gian cho CA ĐÊM.
	 *
	 * Giờ sau nửa đêm mà thuộc ca hôm trước thì cộng 86400 giây TRƯỚC khi vào `gop_gio`. Nhờ vậy
	 * 06:00 hôm sau nằm SAU 22:00 hôm trước trên cùng một trục, và một luật chạy đúng cho cả ca
	 * ngày lẫn ca đêm.
	 *
	 * Bản Apps Script phải viết hẳn một hàm ghi riêng cho ca đêm (`_ghiGioDem`) vì ô sheet giữ
	 * chuỗi 'HH:mm:ss' nên 06:00 luôn "sớm hơn" 22:00 — ca đêm bị đảo thành 16 tiếng ban ngày.
	 * Ở đây giờ là SỐ GIÂY nên chỉ cần trải trục. Một luật thay vì hai, đúng điều `Code.gs` tự
	 * cảnh báo: hai bản tính giờ lệch nhau là lệch tiền lương.
	 *
	 * @param int  $giay   giờ trong ngày
	 * @param int  $moc_dem  giờ dưới mốc này thì coi là thuộc ca đêm hôm trước (mặc định 06:00)
	 */
	public static function trai_dem( $giay, $moc_dem = 21600 ) {
		/* ⚠️ NHỎ HƠN HOẶC BẰNG, không phải nhỏ hơn. Mốc 06:00 CHẴN chính là giờ ra ca đêm hay
		   gặp nhất — ca 22:00–06:00. Dùng dấu nhỏ hơn thì đúng cái giờ phổ biến nhất lại không
		   được trải, và ca đêm đó bị tính thành 16 tiếng ban ngày. Phép thử bắt được đúng chỗ
		   này; biên là nơi lỗi thích nấp. */
		return ( null !== $giay && $giay <= $moc_dem ) ? ( (int) $giay + 86400 ) : $giay;
	}

	/* ==========================================================================================
	 *  ĐỌC BẢNG CÔNG — một cơ sở, một tháng
	 * ========================================================================================== */

	/** Các cơ sở đang CÓ dữ liệu chấm công, kèm số lượt. Dùng dựng ô chọn cơ sở. */
	public static function ds_co_so() {
		global $wpdb;
		$t   = self::bang( 'ngay' );
		$dem = array();
		foreach ( $wpdb->get_results( "SELECT co_so, COUNT(*) AS so FROM $t GROUP BY co_so", ARRAY_A ) as $r ) {
			$dem[ (string) $r['co_so'] ] = (int) $r['so'];
		}
		/* Hợp nhất với DANH MỤC: cơ sở đã khai mà chưa có lượt nào vẫn phải hiện, kèm số 0.
		   Ẩn nó đi là người ta tưởng cơ sở đó không tồn tại, thay vì hiểu là chưa nạp. */
		foreach ( self::danh_muc_co_so() as $ma ) {
			if ( ! isset( $dem[ $ma ] ) ) { $dem[ $ma ] = 0; }
		}
		ksort( $dem );
		$ra = array();
		foreach ( $dem as $ma => $so ) { $ra[] = array( 'co_so' => $ma, 'so' => $so ); }
		return $ra;
	}

	/** Các tháng đang có dữ liệu của một cơ sở, mới nhất trước. */
	public static function ds_thang( $co_so ) {
		global $wpdb;
		$t = self::bang( 'ngay' );
		return $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT DATE_FORMAT(ngay,'%%Y-%%m') FROM $t WHERE co_so=%s ORDER BY 1 DESC", $co_so ) );
	}

	/**
	 * Bảng công một cơ sở một tháng.
	 *
	 * Trả về: 'ngay_dau'/'ngay_cuoi', 'nguoi' => [ ma_nv => ho_ten ], 'o' => [ ma_nv => [ ngay => lượt ] ].
	 *
	 * 🔴 ĐỌC ĐÚNG MỘT LƯỢT rồi gom trong PHP. Truy vấn từng người từng ngày là 30 ngày × 25
	 *    người = 750 lệnh cho một lần mở màn hình — chậm tới mức không ai dùng, và chậm kiểu
	 *    đó thì người ta quay về mở Google Sheet.
	 */
	public static function bang_cong( $co_so, $thang ) {
		global $wpdb;
		$t = self::bang( 'ngay' );
		if ( ! preg_match( '/^\d{4}-\d{2}$/', (string) $thang ) ) { return null; }
		$dau  = $thang . '-01';
		$cuoi = gmdate( 'Y-m-t', strtotime( $dau ) );

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT ngay, ma_nv, ho_ten, vao, ra, anh_vao, anh_ra
			   FROM $t WHERE co_so=%s AND ngay BETWEEN %s AND %s
			  ORDER BY ma_nv ASC, ngay ASC", $co_so, $dau, $cuoi ), ARRAY_A );

		$nguoi = array();
		$o     = array();
		foreach ( (array) $rows as $r ) {
			$ma = (string) $r['ma_nv'];
			/* Tên lấy theo lượt MỚI NHẤT có tên — người đổi tên giữa tháng thì hiện tên mới,
			   chứ không phải tên của ngày đầu tháng. */
			if ( '' !== trim( (string) $r['ho_ten'] ) ) { $nguoi[ $ma ] = (string) $r['ho_ten']; }
			elseif ( ! isset( $nguoi[ $ma ] ) ) { $nguoi[ $ma ] = ''; }
			$o[ $ma ][ (string) $r['ngay'] ] = array(
				'vao'     => ( null === $r['vao'] ) ? null : (int) $r['vao'],
				'ra'      => ( null === $r['ra'] ) ? null : (int) $r['ra'],
				'anh_vao' => (string) $r['anh_vao'],
				'anh_ra'  => (string) $r['anh_ra'],
			);
		}
		/* Xếp theo TÊN, không theo mã. Người ta tìm nhau bằng tên. */
		uasort( $nguoi, function ( $a, $b ) { return strcoll( $a, $b ); } );

		return array(
			'co_so'    => (string) $co_so,
			'thang'    => (string) $thang,
			'ngay_dau' => $dau,
			'ngay_cuoi'=> $cuoi,
			'so_ngay'  => (int) gmdate( 't', strtotime( $dau ) ),
			'nguoi'    => $nguoi,
			'o'        => $o,
		);
	}

	/**
	 * Số giờ làm của một lượt, tính bằng GIÂY. null nếu chưa đủ hai mốc.
	 *
	 * 🔴 CA ĐÊM: giờ ra nhỏ hơn giờ vào nghĩa là đã qua nửa đêm -> cộng 86400. Không cộng thì
	 *    ca 22:00–06:00 ra số ÂM, và bảng tổng tháng trừ mất 16 tiếng của người ta.
	 *    Hàm thuần, thử được bằng con số — xem tools/test/kiem-nap-cong.php.
	 */
	public static function so_gio( $vao, $ra ) {
		if ( null === $vao || null === $ra ) { return null; }
		$vao = (int) $vao; $ra = (int) $ra;
		if ( $ra < $vao ) { $ra += 86400; }
		return $ra - $vao;
	}

	/* ==========================================================================================
	 *  RÀ SOÁT THIẾU — "đã đủ chưa", chứ không bắt người ta tự dò
	 *
	 *  Anh Thắng 25/08/2026: "cho ra giao diện web để anh xem thiếu cơ sở nào, thiếu chấm công
	 *  nào". Đây là câu hỏi đúng: dữ liệu nạp bằng tay từng tệp CSV thì thiếu là chuyện thường,
	 *  mà thiếu KHÔNG BÁO GÌ CẢ — bảng vẫn mở ra bình thường, chỉ là ít dòng hơn.
	 * ========================================================================================== */

	/**
	 * Ngày hôm nay theo giờ Việt Nam.
	 *
	 * 🔴 KHÔNG dùng date() trần. Máy chủ đang chạy UTC, lệch 7 tiếng — từ 17h chiều VN trở đi là
	 *    date() trả về ngày HÔM QUA, và mọi phép so "đã nạp tới đâu" lệch nguyên một ngày.
	 *    current_time() đi theo múi giờ khai trong WordPress; hàm mo_ta_mui_gio() bên dưới nói
	 *    thẳng ra màn hình múi giờ đang là gì để không ai phải đoán.
	 */
	public static function hom_nay() {
		return function_exists( 'current_time' ) ? current_time( 'Y-m-d' ) : gmdate( 'Y-m-d' );
	}

	/** Múi giờ WordPress đang khai — hiện ra màn hình để lệch ngày thì thấy ngay vì sao. */
	public static function mo_ta_mui_gio() {
		if ( ! function_exists( 'get_option' ) ) { return 'UTC'; }
		$tz = get_option( 'timezone_string' );
		if ( $tz ) { return $tz; }
		$o = (float) get_option( 'gmt_offset' );
		return 'UTC' . ( $o >= 0 ? '+' : '' ) . rtrim( rtrim( number_format( $o, 1, '.', '' ), '0' ), '.' );
	}

	/**
	 * NGÀY TRỐNG: ngày nào nằm giữa khoảng đã có dữ liệu mà không có dòng nào.
	 *
	 * 🔴 ĐẾM TỪ NGÀY ĐẦU CÓ DỮ LIỆU, KHÔNG TỪ MÙNG 1. Cơ sở mới mở ngày 15 mà đếm từ mùng 1 thì
	 *    lúc nào cũng "thiếu 14 ngày" — báo động giả, và báo động giả thì người ta thôi nhìn
	 *    bảng này, rồi bỏ qua luôn cái thiếu thật.
	 *
	 * 🔴 DỪNG Ở MỐC CUỐI, KHÔNG Ở CUỐI THÁNG. Tháng đang chạy mà đếm cả ngày chưa tới là dòng
	 *    nào cũng đỏ rực.
	 *
	 * Hàm THUẦN — vào là mảng ngày, ra là mảng ngày. Thử được bằng con số, không cần cơ sở dữ
	 * liệu. Đây cũng là chỗ trước đây viết lặp ở hai nơi, mà lặp thì sớm muộn hai nơi lệch nhau.
	 *
	 * @param array  $co_ngay  các ngày ĐÃ có dữ liệu ('Y-m-d')
	 * @param string $moc_cuoi soi tới hết ngày này
	 */
	public static function ngay_trong( $co_ngay, $moc_cuoi ) {
		$co = array();
		foreach ( (array) $co_ngay as $d ) {
			$d = trim( (string) $d );
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ) { $co[ $d ] = 1; }
		}
		if ( ! $co ) { return array(); }
		$ds = array_keys( $co );
		sort( $ds );
		$dau = $ds[0];
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $moc_cuoi ) || $moc_cuoi < $dau ) { return array(); }
		$ra = array();
		for ( $d = $dau; $d <= $moc_cuoi; $d = gmdate( 'Y-m-d', strtotime( $d . ' +1 day' ) ) ) {
			if ( ! isset( $co[ $d ] ) ) { $ra[] = $d; }
		}
		return $ra;
	}

	/**
	 * Ghi một mã cơ sở vào danh mục. Gọi mỗi lần nạp tệp cơ sở, KỂ CẢ khi tệp đó 0 lượt.
	 *
	 * Nạp một tệp rỗng cũng là một thông tin: cơ sở đó có thật, chỉ chưa có ai chấm. Không ghi
	 * lại thì lần sau mở màn rà soát, cơ sở đó lại biến mất.
	 */
	public static function ghi_co_so( $ma, $ten = '' ) {
		global $wpdb;
		$ma = trim( (string) $ma );
		if ( '' === $ma ) { return; }
		$t = self::bang( 'co_so' );
		$wpdb->query( $wpdb->prepare(
			"INSERT INTO $t (ma, ten, tao_luc) VALUES (%s,%s,%s)
			 ON DUPLICATE KEY UPDATE ten = IF(VALUES(ten)='', ten, VALUES(ten))",
			$ma, (string) $ten, current_time( 'mysql' ) ) );
	}

	/** Danh mục cơ sở đã khai. */
	public static function danh_muc_co_so() {
		global $wpdb;
		$t = self::bang( 'co_so' );
		return $wpdb->get_col( "SELECT ma FROM $t ORDER BY ma ASC" );
	}

	/** Mọi cơ sở TỪNG có dữ liệu, kèm lượt gần nhất. Dùng để biết cơ sở nào tháng này vắng mặt. */
	public static function co_so_tung_co() {
		global $wpdb;
		$t   = self::bang( 'ngay' );
		$gom = array();
		foreach ( $wpdb->get_results(
			"SELECT co_so, MAX(ngay) AS ngay_cuoi, COUNT(*) AS tong FROM $t GROUP BY co_so", ARRAY_A ) as $r ) {
			$gom[ (string) $r['co_so'] ] = array(
				'co_so' => (string) $r['co_so'], 'ngay_cuoi' => (string) $r['ngay_cuoi'], 'tong' => (int) $r['tong'] );
		}
		/* Cơ sở đã khai mà CHƯA CÓ LƯỢT NÀO vẫn vào bảng rà soát, với 0 lượt và không ngày nào.
		   Đó là dòng đáng nhìn nhất trong bảng, không phải dòng nên giấu. */
		foreach ( self::danh_muc_co_so() as $ma ) {
			if ( ! isset( $gom[ $ma ] ) ) {
				$gom[ $ma ] = array( 'co_so' => $ma, 'ngay_cuoi' => '', 'tong' => 0 );
			}
		}
		ksort( $gom );
		return array_values( $gom );
	}

	/**
	 * Rà soát một tháng: mỗi cơ sở một dòng.
	 *
	 * Trả mỗi cơ sở: số người · số lượt · số NGÀY có ai đó chấm · số lượt thiếu giờ ra ·
	 * ngày cuối có dữ liệu · ngày trống (chỉ khi trong khoảng đã có dữ liệu).
	 */
	public static function ra_soat( $thang ) {
		global $wpdb;
		if ( ! preg_match( '/^\d{4}-\d{2}$/', (string) $thang ) ) { return null; }
		$t    = self::bang( 'ngay' );
		$dau  = $thang . '-01';
		$cuoi = gmdate( 'Y-m-t', strtotime( $dau ) );

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT co_so, ngay, ma_nv, vao, ra FROM $t WHERE ngay BETWEEN %s AND %s", $dau, $cuoi ), ARRAY_A );

		$gom = array();
		foreach ( (array) $rows as $r ) {
			$cs = (string) $r['co_so'];
			if ( ! isset( $gom[ $cs ] ) ) {
				$gom[ $cs ] = array( 'nguoi' => array(), 'ngay' => array(), 'luot' => 0, 'thieu_ra' => 0, 'thieu_vao' => 0 );
			}
			$gom[ $cs ]['nguoi'][ (string) $r['ma_nv'] ] = 1;
			$gom[ $cs ]['ngay'][ (string) $r['ngay'] ]   = 1;
			$gom[ $cs ]['luot']++;
			if ( null !== $r['vao'] && null === $r['ra'] ) { $gom[ $cs ]['thieu_ra']++; }
			if ( null === $r['vao'] && null !== $r['ra'] ) { $gom[ $cs ]['thieu_vao']++; }
		}

		$hom_nay  = self::hom_nay();
		/* Soi tới HÔM NAY, không tới cuối tháng — xem ngay_trong() để biết vì sao. */
		$moc_cuoi = ( $hom_nay < $cuoi ) ? $hom_nay : $cuoi;

		$ds = array();
		foreach ( self::co_so_tung_co() as $c ) {
			$cs = (string) $c['co_so'];
			$g  = isset( $gom[ $cs ] ) ? $gom[ $cs ] : null;
			$co_ngay = $g ? array_keys( $g['ngay'] ) : array();
			sort( $co_ngay );

			/* Ngày TRỐNG: chỉ tính trong khoảng từ ngày đầu có dữ liệu tới mốc cuối. Đếm từ mùng 1
			   là cơ sở mới mở giữa tháng lúc nào cũng "thiếu 14 ngày" — báo động giả. */
			$trong = self::ngay_trong( $co_ngay, $moc_cuoi );
			$ds[] = array(
				'co_so'      => $cs,
				'co_du_lieu' => (bool) $g,
				'nguoi'      => $g ? count( $g['nguoi'] ) : 0,
				'luot'       => $g ? $g['luot'] : 0,
				'so_ngay'    => count( $co_ngay ),
				'ngay_dau'   => $co_ngay ? $co_ngay[0] : '',
				'ngay_cuoi'  => $co_ngay ? $co_ngay[ count( $co_ngay ) - 1 ] : '',
				'thieu_ra'   => $g ? $g['thieu_ra'] : 0,
				'thieu_vao'  => $g ? $g['thieu_vao'] : 0,
				'ngay_trong' => $trong,
				/* Lượt gần nhất của cơ sở này ở BẤT KỲ tháng nào — để phân biệt "gian đã đóng"
				   với "quên nạp tháng này". */
				'moi_nhat'   => (string) $c['ngay_cuoi'],
			);
		}
		return array(
			'thang'    => (string) $thang,
			'hom_nay'  => $hom_nay,
			'moc_cuoi' => $moc_cuoi,
			'mui_gio'  => self::mo_ta_mui_gio(),
			'ds'       => $ds,
		);
	}

	/**
	 * Chi tiết thiếu của MỘT cơ sở trong tháng: ai thiếu ngày nào.
	 *
	 * Hai loại "thiếu" khác hẳn nhau, phải tách:
	 *   · thiếu GIỜ RA  — có chấm vào mà không chấm ra (quên bấm)
	 *   · KHÔNG CHẤM    — cả ngày không có dòng nào (nghỉ? hay máy hỏng?)
	 */
	public static function thieu_chi_tiet( $co_so, $thang ) {
		$b = self::bang_cong( $co_so, $thang );
		if ( null === $b ) { return null; }
		$hom_nay  = self::hom_nay();
		$moc_cuoi = ( $hom_nay < $b['ngay_cuoi'] ) ? $hom_nay : $b['ngay_cuoi'];

		$thieu_ra = array();
		foreach ( $b['o'] as $ma => $ngays ) {
			foreach ( $ngays as $ng => $o ) {
				if ( null !== $o['vao'] && null === $o['ra'] ) {
					$thieu_ra[] = array( 'ma_nv' => $ma, 'ho_ten' => $b['nguoi'][ $ma ], 'ngay' => $ng, 'vao' => $o['vao'] );
				}
			}
		}
		usort( $thieu_ra, function ( $a, $c ) { return strcmp( $a['ngay'], $c['ngay'] ); } );

		/* Ngày cả cơ sở không ai chấm — tính từ ngày đầu CÓ dữ liệu tới hôm nay. */
		$co = array();
		foreach ( $b['o'] as $ngays ) { foreach ( $ngays as $ng => $x ) { $co[ $ng ] = 1; } }
		$trong = self::ngay_trong( array_keys( $co ), $moc_cuoi );
		return array(
			'co_so'      => $b['co_so'],
			'thang'      => $b['thang'],
			'thieu_ra'   => $thieu_ra,
			'ngay_trong' => $trong,
			'so_nguoi'   => count( $b['nguoi'] ),
		);
	}

	/** Lệnh dựng bảng. Gọi qua dbDelta nên phải theo đúng khuôn WordPress đòi. */
	public static function lenh_tao( $charset ) {
		$nv    = self::bang( 'nv' );
		$dv    = self::bang( 'nv_donvi' );
		$ngay  = self::bang( 'ngay' );
		$cs    = self::bang( 'co_so' );
		return array(
			/* Hồ sơ người. `ma_nv` là khoá chính: một người một dòng.
			   Mã dạng chuỗi dài (MNNV2KVC0106) và có cả mã ngắn tự đặt (TP15, TUTP02) trong
			   cùng một cơ sở — nên VARCHAR rộng, tuyệt đối không ép về số. */
			"CREATE TABLE $nv (
				ma_nv VARCHAR(64) NOT NULL,
				ho_ten VARCHAR(191) NOT NULL DEFAULT '',
				mien VARCHAR(64) NOT NULL DEFAULT '',
				phong_ban VARCHAR(128) NOT NULL DEFAULT '',
				loai_hinh VARCHAR(64) NOT NULL DEFAULT '',
				gioi_tinh VARCHAR(16) NOT NULL DEFAULT '',
				ngay_sinh DATE NULL,
				sdt VARCHAR(32) NOT NULL DEFAULT '',
				cccd VARCHAR(32) NOT NULL DEFAULT '',
				ngay_cap_cccd DATE NULL,
				loai_hop_dong VARCHAR(32) NOT NULL DEFAULT '',
				tao_luc VARCHAR(32) NOT NULL DEFAULT '',
				sua_luc DATETIME NULL,
				PRIMARY KEY (ma_nv),
				KEY ho_ten (ho_ten),
				KEY cccd (cccd)
			) $charset;",

			/* Gán cơ sở. Khoá là CẶP mã+đơn vị — đó là chỗ giữ được "một người nhiều gian". */
			"CREATE TABLE $dv (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				ma_nv VARCHAR(64) NOT NULL,
				don_vi VARCHAR(191) NOT NULL,
				tinh_thanh VARCHAR(64) NOT NULL DEFAULT '',
				tao_luc VARCHAR(32) NOT NULL DEFAULT '',
				PRIMARY KEY (id),
				UNIQUE KEY nguoi_donvi (ma_nv, don_vi),
				KEY don_vi (don_vi)
			) $charset;",

			/* Chấm công. Khoá duy nhất là bộ ba cơ sở+ngày+người — nạp lại cùng một tệp thì
			   cập nhật đúng dòng cũ chứ không đẻ thêm dòng mới. Cùng lý do với luật nới rộng:
			   nạp lại phải ra cùng một bảng công. */
			"CREATE TABLE $ngay (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				co_so VARCHAR(64) NOT NULL,
				ngay DATE NOT NULL,
				ma_nv VARCHAR(64) NOT NULL,
				ho_ten VARCHAR(191) NOT NULL DEFAULT '',
				vao INT NULL,
				ra INT NULL,
				anh_vao TEXT NULL,
				anh_ra TEXT NULL,
				nguon VARCHAR(16) NOT NULL DEFAULT 'csv',
				sua_luc DATETIME NULL,
				PRIMARY KEY (id),
				UNIQUE KEY mot_luot (co_so, ngay, ma_nv),
				KEY theo_nguoi (ma_nv, ngay),
				KEY theo_co_so (co_so, ngay)
			) $charset;",

			/* DANH MỤC CƠ SỞ — anh Thắng 25/08/2026: "cho dù cơ sở đó không có chấm công vẫn
			   hiện (để biết cơ sở đó tồn tại)".

			   🔴 VÌ SAO PHẢI CÓ BẢNG RIÊNG: trước đây danh sách cơ sở suy ra từ chính bảng chấm
			   công. Cơ sở chưa nạp lượt nào thì KHÔNG TỒN TẠI với hệ — nó biến mất khỏi ô chọn
			   và khỏi bảng rà soát. Mà đó đúng là cơ sở đáng lo nhất: "chưa nạp gì cả" khác hẳn
			   "không có cơ sở đó". Suy ra từ dữ liệu thì không bao giờ phân biệt được hai thứ. */
			"CREATE TABLE $cs (
				ma VARCHAR(64) NOT NULL,
				ten VARCHAR(191) NOT NULL DEFAULT '',
				tao_luc DATETIME NULL,
				PRIMARY KEY (ma)
			) $charset;",
		);
	}
}
