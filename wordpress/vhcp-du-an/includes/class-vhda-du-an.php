<?php
/**
 * LÕI HỆ DỰ ÁN — lập dự án, chuyển chặng, bàn giao bộ phận, cập nhật tiến độ.
 *
 * 🔴 MỌI CHỐT QUYỀN NẰM Ở ĐÂY, KHÔNG NẰM Ở MÀN HÌNH. Giấu một cái nút chỉ là trang trí: biểu
 *    mẫu POST dựng được từ bên ngoài, và người biết mã đơn thì gọi thẳng được. Lọc trên giao
 *    diện là để MẮT không thấy; chốt ở lõi mới là để TAY không với tới.
 *
 * 🔴 KHÔNG HÀM NÀO `echo` HAY `exit`. Tất cả trả về mảng `ok`/`loi`. Hàm có `exit` thì bài kiểm
 *    gọi nó là bài kiểm tự chết giữa đường — mà phần đáng thử nhất của một hệ quy trình chính
 *    là phần quyết định cho đi hay không cho đi.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHDA_DuAn {

	/** Giờ máy chủ, một chỗ duy nhất — bài kiểm ghim đồng hồ bằng `vhcp_test_dat_gio`. */
	private static function bay_gio() {
		return function_exists( 'current_time' ) ? current_time( 'mysql' ) : gmdate( 'Y-m-d H:i:s' );
	}

	private static function ten_cua( $u ) {
		return is_array( $u ) && isset( $u['name'] ) ? trim( (string) $u['name'] ) : '';
	}

	private static function ma_nv_cua( $u ) {
		if ( ! is_array( $u ) ) { return ''; }
		foreach ( array( 'maNV', 'ma_nv' ) as $k ) {
			if ( ! empty( $u[ $k ] ) ) { return trim( (string) $u[ $k ] ); }
		}
		return '';
	}

	/** Ngày dạng YYYY-MM-DD, hoặc '' nếu gõ sai khuôn. Không đoán hộ: ngày rác lọt vào là lịch sai. */
	public static function ngay( $s ) {
		$s = trim( (string) $s );
		if ( '' === $s ) { return ''; }
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $s ) ? $s : '';
	}

	private static function ghi_nhat_ky( $du_an_id, $viec, $dat ) {
		global $wpdb;
		$wpdb->insert( VHDA_DB::t( 'nhat_ky' ), array(
			'du_an_id'  => (int) $du_an_id,
			'viec'      => (string) $viec,
			'tu_chang'  => isset( $dat['tu'] ) ? (string) $dat['tu'] : '',
			'den_chang' => isset( $dat['den'] ) ? (string) $dat['den'] : '',
			'bo_phan'   => isset( $dat['bo_phan'] ) ? (string) $dat['bo_phan'] : '',
			'chi_tiet'  => isset( $dat['chi_tiet'] ) ? (string) $dat['chi_tiet'] : '',
			'nguoi'     => isset( $dat['nguoi'] ) ? (string) $dat['nguoi'] : '',
			'ma_nv'     => isset( $dat['ma_nv'] ) ? (string) $dat['ma_nv'] : '',
			'luc'       => self::bay_gio(),
		) );
	}

	/* ==================================================================== đọc */

	public static function mot( $ma ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . VHDA_DB::t( 'du_an' ) . ' WHERE ma=%s', (string) $ma ), ARRAY_A );
	}

	public static function mot_theo_id( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . VHDA_DB::t( 'du_an' ) . ' WHERE id=%d', (int) $id ), ARRAY_A );
	}

	/**
	 * Danh sách dự án. `$loc['chang']` lọc theo chặng; `$loc['coso']` theo cơ sở.
	 *
	 * ⚠️ MỚI ĐỘNG NHẤT LÊN ĐẦU (`sua_luc`), không phải mới lập nhất. Dự án lập tuần trước mà
	 *    hôm nay vừa có bộ phận báo tiến độ thì đó mới là thứ cần nhìn.
	 */
	public static function ds( $loc = array() ) {
		global $wpdb;
		$dk = array( '1=1' ); $tv = array();
		if ( ! empty( $loc['chang'] ) ) { $dk[] = 'chang=%s'; $tv[] = (string) $loc['chang']; }
		if ( ! empty( $loc['coso'] ) )  { $dk[] = 'coso=%s';  $tv[] = (string) $loc['coso']; }
		$sql = 'SELECT * FROM ' . VHDA_DB::t( 'du_an' ) . ' WHERE ' . implode( ' AND ', $dk )
			. ' ORDER BY (sua_luc IS NULL), sua_luc DESC, id DESC';
		if ( $tv ) { $sql = $wpdb->prepare( $sql, $tv ); }
		$rs = $wpdb->get_results( $sql, ARRAY_A );
		return is_array( $rs ) ? $rs : array();
	}

	public static function viec_cua( $du_an_id ) {
		global $wpdb;
		$rs = $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . VHDA_DB::t( 'viec' ) . ' WHERE du_an_id=%d ORDER BY id ASC',
			(int) $du_an_id ), ARRAY_A );
		return is_array( $rs ) ? $rs : array();
	}

	public static function nhat_ky_cua( $du_an_id, $gioi_han = 100 ) {
		global $wpdb;
		$rs = $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . VHDA_DB::t( 'nhat_ky' ) . ' WHERE du_an_id=%d ORDER BY id DESC LIMIT %d',
			(int) $du_an_id, (int) $gioi_han ), ARRAY_A );
		return is_array( $rs ) ? $rs : array();
	}

	/**
	 * TIẾN ĐỘ CHUNG = trung bình phần trăm các bộ phận. HÀM THUẦN.
	 *
	 * ⚠️ CHƯA BÀN GIAO CHO AI thì trả `null`, KHÔNG trả 0. Hai thứ ấy khác hẳn nhau: 0% là "đã
	 *    giao mà chưa ai làm gì", còn `null` là "chưa giao cho ai" — hiện lẫn lộn thì sếp nhìn
	 *    bảng tưởng cả phòng đang ngồi chơi.
	 */
	public static function tien_do_chung( $ds_viec ) {
		$ds_viec = (array) $ds_viec;
		if ( ! count( $ds_viec ) ) { return null; }
		$t = 0;
		foreach ( $ds_viec as $v ) {
			$p = (int) ( isset( $v['phan_tram'] ) ? $v['phan_tram'] : 0 );
			$t += max( 0, min( 100, $p ) );
		}
		return (int) round( $t / count( $ds_viec ) );
	}

	/**
	 * CÒN MẤY NGÀY NỮA TỚI NGÀY ẤY — âm là đã qua. HÀM THUẦN.
	 *
	 * ⚠️ Ngày rỗng hoặc sai khuôn trả `null`, KHÔNG trả 0. `null` là "chưa chốt ngày", còn 0 là
	 *    "đúng hôm nay" — hiện lẫn lộn thì dự án chưa chốt ngày nào lại nằm chung ô với dự án
	 *    khai trương sáng mai.
	 */
	public static function con_may_ngay( $ngay, $hom_nay = '' ) {
		$n = self::ngay( $ngay );
		if ( '' === $n ) { return null; }
		$h = self::ngay( $hom_nay );
		if ( '' === $h ) {
			$h = function_exists( 'current_time' ) ? current_time( 'Y-m-d' ) : gmdate( 'Y-m-d' );
		}
		$a = strtotime( $n . ' 00:00:00' );
		$b = strtotime( $h . ' 00:00:00' );
		if ( ! $a || ! $b ) { return null; }
		return (int) round( ( $a - $b ) / 86400 );
	}

	/**
	 * MỘT BỘ PHẬN CÓ ĐANG TRỄ HẠN KHÔNG. HÀM THUẦN.
	 *
	 * 🔴 XONG RỒI THÌ KHÔNG TRỄ, dù quá hạn. Hạn là để giục việc chưa xong; tô đỏ một việc đã
	 *    làm xong chỉ làm người ta quen mắt với màu đỏ, rồi bỏ qua cả những cái đỏ thật.
	 *
	 * ⚠️ CHƯA ĐẶT HẠN cũng không trễ — không có mốc thì không so được với cái gì.
	 */
	public static function tre_han( $v, $hom_nay = '' ) {
		$v = (array) $v;
		if ( ! empty( $v['xong'] ) || 100 <= (int) ( isset( $v['phan_tram'] ) ? $v['phan_tram'] : 0 ) ) {
			return false;
		}
		/* ⛔ `null !== $con` là thừa về mặt kết quả — PHP so `null < 0` ra false, nên bỏ nó đi
		   thì hàm vẫn chạy đúng và phá thử "chưa đặt hạn cũng coi là trễ" luôn sống. Giữ vì nó
		   nói ra Ý ĐỊNH: "không có hạn" là một trạng thái riêng, không phải một con số nhỏ hơn
		   0. Ngày nào `con_may_ngay()` đổi cách báo "không có" (chuỗi rỗng, -1, false…) thì
		   dòng này vẫn đúng, còn phép so trần thì sai lặng lẽ. */
		$con = self::con_may_ngay( isset( $v['han'] ) ? $v['han'] : '', $hom_nay );
		return ( null !== $con && $con < 0 );
	}

	/**
	 * TÓM TẮT CẢ BẢNG cho dải thẻ số ở đầu màn. HÀM THUẦN — nhận vào danh sách đã đọc sẵn.
	 *
	 * 🔴 NHẬN DỮ LIỆU VÀO, KHÔNG TỰ ĐỌC CSDL. Nhờ vậy bộ thử dựng được mọi cảnh (sắp mở cửa,
	 *    trễ hạn, vừa huỷ) mà không phải ghi một dòng nào xuống bảng — và mấy con số này là thứ
	 *    sếp nhìn đầu tiên mỗi sáng, nên chúng phải đúng trong cả những cảnh hiếm.
	 *
	 * @param array $ds   danh sách dự án (như `ds()` trả về)
	 * @param array $viec bản đồ du_an_id => danh sách phần việc
	 * @return array tong · dang_chay · sap_mo · tre · xong · huy · tien_do
	 */
	public static function tom_tat( $ds, $viec = array(), $hom_nay = '', $sap_trong = 7 ) {
		$ra = array( 'tong' => 0, 'dang_chay' => 0, 'sap_mo' => 0, 'tre' => 0,
			'xong' => 0, 'huy' => 0, 'tien_do' => null );
		$tong_td = 0; $so_td = 0;
		foreach ( (array) $ds as $d ) {
			$ra['tong']++;
			$ch = (string) ( isset( $d['chang'] ) ? $d['chang'] : '' );
			if ( VHDA_Luong::HUY === $ch )       { $ra['huy']++;  continue; }
			if ( VHDA_Luong::XONG === $ch )      { $ra['xong']++; }
			else                                  { $ra['dang_chay']++; }

			/* SẮP MỞ CỬA = còn trong ngần này ngày và CHƯA mở. Đã qua ngày mở cửa mà vẫn đếm là
			   "sắp" thì con số ấy chỉ tăng chứ không bao giờ giảm. */
			$con = self::con_may_ngay( isset( $d['ngay_mo_cua'] ) ? $d['ngay_mo_cua'] : '', $hom_nay );
			if ( null !== $con && $con >= 0 && $con <= (int) $sap_trong
				&& VHDA_Luong::XONG !== $ch && VHDA_Luong::MO_CUA !== $ch ) { $ra['sap_mo']++; }

			$id = (int) ( isset( $d['id'] ) ? $d['id'] : 0 );
			$dsv = isset( $viec[ $id ] ) ? (array) $viec[ $id ] : array();
			foreach ( $dsv as $v ) { if ( self::tre_han( $v, $hom_nay ) ) { $ra['tre']++; break; } }

			$td = self::tien_do_chung( $dsv );
			/* Dự án CHƯA GIAO cho ai không được kéo tụt tiến độ trung bình xuống — nó chưa bắt
			   đầu, chứ không phải đang đứng ở 0%. */
			if ( null !== $td ) { $tong_td += $td; $so_td++; }
		}
		if ( $so_td ) { $ra['tien_do'] = (int) round( $tong_td / $so_td ); }
		return $ra;
	}

	/* ==================================================================== ghi */

	public static function lap( $u, $dat ) {
		global $wpdb;
		$loi = VHDA_Quyen::vi_sao_khong( $u, 'lap' );
		if ( '' !== $loi ) { return array( 'ok' => false, 'loi' => $loi ); }

		$ten = trim( (string) ( isset( $dat['ten'] ) ? $dat['ten'] : '' ) );
		if ( '' === $ten ) { return array( 'ok' => false, 'loi' => 'Chưa đặt tên dự án.' ); }

		$ma  = 'DA' . gmdate( 'ymd' ) . strtoupper( substr( md5( uniqid( '', true ) ), 0, 5 ) );
		$luc = self::bay_gio();
		$wpdb->insert( VHDA_DB::t( 'du_an' ), array(
			'ma'          => $ma,
			'ten'         => $ten,
			'coso'        => trim( (string) ( isset( $dat['coso'] ) ? $dat['coso'] : '' ) ),
			'khach'       => trim( (string) ( isset( $dat['khach'] ) ? $dat['khach'] : '' ) ),
			'so_hop_dong' => trim( (string) ( isset( $dat['so_hop_dong'] ) ? $dat['so_hop_dong'] : '' ) ),
			'gia_tri'     => (int) ( isset( $dat['gia_tri'] ) ? $dat['gia_tri'] : 0 ),
			'chang'       => VHDA_Luong::HOP_DONG,
			'nguoi_tao'   => self::ten_cua( $u ),
			'ma_nv_tao'   => self::ma_nv_cua( $u ),
			'tao_luc'     => $luc,
			'sua_luc'     => $luc,
		) );
		$id = (int) $wpdb->insert_id;
		self::ghi_nhat_ky( $id, 'lap', array( 'den' => VHDA_Luong::HOP_DONG,
			'chi_tiet' => $ten, 'nguoi' => self::ten_cua( $u ), 'ma_nv' => self::ma_nv_cua( $u ) ) );
		return array( 'ok' => true, 'id' => $id, 'ma' => $ma );
	}

	/** Sửa mấy ô mô tả (tên · khách · số hợp đồng · giá trị · phương án). Không đụng chặng. */
	public static function sua( $u, $ma, $dat ) {
		global $wpdb;
		$loi = VHDA_Quyen::vi_sao_khong( $u, 'lap' );
		if ( '' !== $loi ) { return array( 'ok' => false, 'loi' => $loi ); }
		$d = self::mot( $ma );
		if ( ! $d ) { return array( 'ok' => false, 'loi' => 'Không tìm thấy dự án.' ); }

		$ghi = array( 'sua_luc' => self::bay_gio() );
		foreach ( array( 'ten', 'coso', 'khach', 'so_hop_dong', 'phuong_an' ) as $k ) {
			if ( isset( $dat[ $k ] ) ) { $ghi[ $k ] = trim( (string) $dat[ $k ] ); }
		}
		if ( isset( $dat['gia_tri'] ) ) { $ghi['gia_tri'] = (int) $dat['gia_tri']; }
		if ( isset( $ghi['ten'] ) && '' === $ghi['ten'] ) {
			return array( 'ok' => false, 'loi' => 'Tên dự án không được để trống.' );
		}
		$wpdb->update( VHDA_DB::t( 'du_an' ), $ghi, array( 'id' => (int) $d['id'] ) );
		self::ghi_nhat_ky( (int) $d['id'], 'sua', array( 'chi_tiet' => implode( ', ', array_keys( $ghi ) ),
			'nguoi' => self::ten_cua( $u ), 'ma_nv' => self::ma_nv_cua( $u ) ) );
		return array( 'ok' => true );
	}

	/**
	 * CHỐT NGÀY THI CÔNG VÀ NGÀY MỞ CỬA.
	 *
	 * 🔴 MỞ CỬA KHÔNG ĐƯỢC TRƯỚC THI CÔNG. Gõ ngược hai ngày là mọi thứ tính theo lịch sau đó
	 *    đều sai — và cái sai ấy im lặng cho tới lúc có người ra công trường.
	 */
	public static function chot_ngay( $u, $ma, $ngay_thi_cong, $ngay_mo_cua ) {
		global $wpdb;
		$loi = VHDA_Quyen::vi_sao_khong( $u, 'chuyen' );
		if ( '' !== $loi ) { return array( 'ok' => false, 'loi' => $loi ); }
		$d = self::mot( $ma );
		if ( ! $d ) { return array( 'ok' => false, 'loi' => 'Không tìm thấy dự án.' ); }

		$tc = self::ngay( $ngay_thi_cong );
		$mc = self::ngay( $ngay_mo_cua );
		if ( '' === $tc ) { return array( 'ok' => false, 'loi' => 'Ngày thi công chưa đúng khuôn (YYYY-MM-DD).' ); }
		if ( '' === $mc ) { return array( 'ok' => false, 'loi' => 'Ngày mở cửa chưa đúng khuôn (YYYY-MM-DD).' ); }
		if ( $mc < $tc ) {
			return array( 'ok' => false, 'loi' => 'Ngày mở cửa (' . $mc . ') không thể trước ngày thi công ('
				. $tc . ').' );
		}
		$wpdb->update( VHDA_DB::t( 'du_an' ),
			array( 'ngay_thi_cong' => $tc, 'ngay_mo_cua' => $mc, 'sua_luc' => self::bay_gio() ),
			array( 'id' => (int) $d['id'] ) );
		self::ghi_nhat_ky( (int) $d['id'], 'chot_ngay', array( 'chi_tiet' => 'Thi công ' . $tc . ' · mở cửa ' . $mc,
			'nguoi' => self::ten_cua( $u ), 'ma_nv' => self::ma_nv_cua( $u ) ) );
		return array( 'ok' => true, 'ngayThiCong' => $tc, 'ngayMoCua' => $mc );
	}

	/**
	 * CHUYỂN CHẶNG.
	 *
	 * Luật đi nằm ở `VHDA_Luong` (hàm thuần); ở đây chỉ gác quyền, ghi, và để lại vết.
	 *
	 * 🔴 KHÔNG SANG "BÀN GIAO BỘ PHẬN" KHI CHƯA CHỐT NGÀY. Bộ phận nhận việc mà không có ngày
	 *    thi công thì họ không xếp được người — và họ sẽ đi hỏi mồm, đúng thứ hệ này định bỏ.
	 */
	public static function chuyen( $u, $ma, $den, $ghi_chu = '' ) {
		global $wpdb;
		$loi = VHDA_Quyen::vi_sao_khong( $u, VHDA_Luong::HUY === $den ? 'huy' : 'chuyen' );
		if ( '' !== $loi ) { return array( 'ok' => false, 'loi' => $loi ); }
		$d = self::mot( $ma );
		if ( ! $d ) { return array( 'ok' => false, 'loi' => 'Không tìm thấy dự án.' ); }

		$tu  = (string) $d['chang'];
		$den = (string) $den;
		/* Mở lại từ trạng thái huỷ: quay về đúng chặng đang dở trước đó, không về đầu. */
		if ( VHDA_Luong::HUY === $tu && '' === $den ) {
			$den = (string) $d['chang_truoc'];
			if ( '' === $den ) { $den = VHDA_Luong::HOP_DONG; }
		}
		$loi = VHDA_Luong::vi_sao_khong_di( $tu, $den );
		if ( '' !== $loi ) { return array( 'ok' => false, 'loi' => $loi ); }

		if ( VHDA_Luong::BAN_GIAO === $den && ( empty( $d['ngay_thi_cong'] ) || empty( $d['ngay_mo_cua'] ) ) ) {
			return array( 'ok' => false, 'loi' => 'Chưa chốt ngày thi công và ngày mở cửa thì chưa bàn giao được — '
				. 'bộ phận nhận việc mà không có ngày thì không xếp được người.' );
		}

		$ghi = array( 'chang' => $den, 'sua_luc' => self::bay_gio() );
		/* Nhớ chặng đang dở TRƯỚC KHI huỷ, để mở lại còn biết quay về đâu. */
		if ( VHDA_Luong::HUY === $den ) { $ghi['chang_truoc'] = $tu; }
		$wpdb->update( VHDA_DB::t( 'du_an' ), $ghi, array( 'id' => (int) $d['id'] ) );
		self::ghi_nhat_ky( (int) $d['id'], 'chuyen', array( 'tu' => $tu, 'den' => $den,
			'chi_tiet' => (string) $ghi_chu, 'nguoi' => self::ten_cua( $u ), 'ma_nv' => self::ma_nv_cua( $u ) ) );
		return array( 'ok' => true, 'chang' => $den );
	}

	/**
	 * BÀN GIAO MỘT BỘ PHẬN.
	 *
	 * ⚠️ MỘT BỘ PHẬN MỘT DÒNG (UNIQUE du_an_id+bo_phan): giao lại là CẬP NHẬT nội dung, không
	 *    đẻ dòng thứ hai. Hai dòng cùng bộ phận thì tiến độ trung bình tính sai, và không ai
	 *    biết phải cập nhật dòng nào.
	 */
	public static function giao( $u, $ma, $bo_phan, $noi_dung = '', $han = '' ) {
		global $wpdb;
		$loi = VHDA_Quyen::vi_sao_khong( $u, 'ban_giao' );
		if ( '' !== $loi ) { return array( 'ok' => false, 'loi' => $loi ); }
		$d = self::mot( $ma );
		if ( ! $d ) { return array( 'ok' => false, 'loi' => 'Không tìm thấy dự án.' ); }

		$bp = trim( (string) $bo_phan );
		if ( '' === $bp ) { return array( 'ok' => false, 'loi' => 'Chưa chọn bộ phận.' ); }
		$h = self::ngay( $han );
		if ( '' !== trim( (string) $han ) && '' === $h ) {
			return array( 'ok' => false, 'loi' => 'Hạn chưa đúng khuôn (YYYY-MM-DD).' );
		}
		/* Hạn sau ngày mở cửa là hạn vô nghĩa — quán mở rồi thì việc ấy đã trễ, không phải "đúng hạn". */
		if ( '' !== $h && ! empty( $d['ngay_mo_cua'] ) && $h > (string) $d['ngay_mo_cua'] ) {
			return array( 'ok' => false, 'loi' => 'Hạn (' . $h . ') muộn hơn ngày mở cửa ('
				. $d['ngay_mo_cua'] . ') — sửa lại hạn hoặc dời ngày mở cửa.' );
		}

		$cu = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . VHDA_DB::t( 'viec' ) . ' WHERE du_an_id=%d AND bo_phan=%s',
			(int) $d['id'], $bp ), ARRAY_A );
		$luc = self::bay_gio();
		if ( $cu ) {
			$wpdb->update( VHDA_DB::t( 'viec' ), array(
				'noi_dung' => (string) $noi_dung, 'han' => ( '' === $h ? null : $h ),
				'nguoi_giao' => self::ten_cua( $u ), 'giao_luc' => $luc,
			), array( 'id' => (int) $cu['id'] ) );
			$id_viec = (int) $cu['id'];
		} else {
			$wpdb->insert( VHDA_DB::t( 'viec' ), array(
				'du_an_id' => (int) $d['id'], 'bo_phan' => $bp, 'noi_dung' => (string) $noi_dung,
				'han' => ( '' === $h ? null : $h ), 'phan_tram' => 0, 'xong' => 0,
				'nguoi_giao' => self::ten_cua( $u ), 'giao_luc' => $luc,
			) );
			$id_viec = (int) $wpdb->insert_id;
		}
		$wpdb->update( VHDA_DB::t( 'du_an' ), array( 'sua_luc' => $luc ), array( 'id' => (int) $d['id'] ) );
		self::ghi_nhat_ky( (int) $d['id'], 'giao', array( 'bo_phan' => $bp, 'chi_tiet' => (string) $noi_dung,
			'nguoi' => self::ten_cua( $u ), 'ma_nv' => self::ma_nv_cua( $u ) ) );
		return array( 'ok' => true, 'id' => $id_viec );
	}

	/**
	 * CẬP NHẬT TIẾN ĐỘ MỘT BỘ PHẬN.
	 *
	 * 🔴 GÁC THEO BỘ PHẬN CỦA NGƯỜI GỌI, không chỉ theo bậc vai. Bên Kỹ thuật sửa được tiến độ
	 *    của bên Marketing là con số ấy hết nghĩa — ai cũng vào được thì không ai chịu trách
	 *    nhiệm về nó nữa. Quản lý trở lên vẫn sửa được mọi bộ phận (xem `VHDA_Quyen`).
	 */
	public static function tien_do( $u, $viec_id, $phan_tram, $ghi_chu = '' ) {
		global $wpdb;
		$v = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . VHDA_DB::t( 'viec' ) . ' WHERE id=%d', (int) $viec_id ), ARRAY_A );
		if ( ! $v ) { return array( 'ok' => false, 'loi' => 'Không tìm thấy phần việc.' ); }

		$loi = VHDA_Quyen::vi_sao_khong_sua_tien_do( $u, (string) $v['bo_phan'] );
		if ( '' !== $loi ) { return array( 'ok' => false, 'loi' => $loi ); }

		$p = (int) $phan_tram;
		if ( $p < 0 || $p > 100 ) { return array( 'ok' => false, 'loi' => 'Tiến độ phải từ 0 đến 100.' ); }

		$luc = self::bay_gio();
		$wpdb->update( VHDA_DB::t( 'viec' ), array(
			'phan_tram' => $p, 'xong' => ( 100 === $p ? 1 : 0 ), 'cap_nhat_luc' => $luc,
		), array( 'id' => (int) $v['id'] ) );
		$wpdb->update( VHDA_DB::t( 'du_an' ), array( 'sua_luc' => $luc ),
			array( 'id' => (int) $v['du_an_id'] ) );
		self::ghi_nhat_ky( (int) $v['du_an_id'], 'tien_do', array( 'bo_phan' => (string) $v['bo_phan'],
			'chi_tiet' => $p . '% ' . trim( (string) $ghi_chu ),
			'nguoi' => self::ten_cua( $u ), 'ma_nv' => self::ma_nv_cua( $u ) ) );
		return array( 'ok' => true, 'phanTram' => $p );
	}
}
