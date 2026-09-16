<?php
/**
 * ĐI TRỄ: NGƯỠNG CHO PHÉP CỦA TỪNG CỬA HÀNG, VÀ CHẤM MẤY PHÚT THÌ KÊU.
 *
 * =================================================================================================
 * 🔴 VÌ SAO NGƯỠNG LÀ CỦA TỪNG CỬA HÀNG, VÀ DO CỬA HÀNG TRƯỞNG ĐẶT.
 * =================================================================================================
 * Anh Thắng 28/08/2026: *"Mỗi cửa hàng sẽ có những giờ vào ra làm khác nhau. Vậy cho qua bên Tài
 * khoản cửa hàng trưởng tự set giờ ca vào ra của cửa hàng… nếu bạn nào chấm thiếu giờ thì hiện
 * cảnh báo ô vàng cho cửa hàng trưởng biết"*, và khi được hỏi trễ bao nhiêu phút thì kêu:
 * *"trễ tầm bao nhiêu phút (do cửa hàng trưởng set)"*.
 *
 * Một con số gõ cứng trong mã là sai với chuỗi này: quán trong trung tâm thương mại mở cửa theo
 * giờ của trung tâm, kho thì theo giờ xe tới. Mà cũng không thể để Admin ngồi khai cho 26 cửa
 * hàng — người biết giờ thật của cửa hàng là người đứng ở đó.
 *
 * =================================================================================================
 * ⚠️ CẢNH BÁO, KHÔNG PHẢI TRỪ TIỀN.
 * =================================================================================================
 * Lớp này KHÔNG đụng vào giờ công, không đụng vào tiền. Nó chỉ trả lời một câu: "lượt chấm này
 * trễ mấy phút so với giờ vào ca". Ô vàng là để cửa hàng trưởng NHÌN THẤY, rồi hỏi lại nhân
 * viên — chứ không phải một cái máy tự trừ lương.
 *
 * ⚠️ KHÔNG THUỘC CA NÀO THÌ KHÔNG KÊU. Người làm giờ hành chính ở một cơ sở chưa khai ca, hay ca
 *    gãy chưa khai đủ, mà bị tô vàng cả tháng thì cái màu ấy mất nghĩa ngay tuần đầu — và người
 *    ta thôi nhìn nó.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_Tre {

	/** Khoá lưu trong bảng `cai_dat`. Bản đồ { 'CO_SO' => số phút }. */
	const O = 'TRE_COSO';

	/** Chưa khai thì lấy mức này. 15 phút — đủ rộng cho kẹt xe, đủ hẹp để còn nghĩa. */
	const MAC_DINH = 15;

	/** Trần: quá mức này thì cái ngưỡng không còn là ngưỡng nữa. */
	const TOI_DA = 120;

	/**
	 * Lượt chấm chỉ được đem so với ca bắt đầu trong khoảng này quanh giờ vào.
	 *
	 * ⚠️ KHÔNG SO VỚI CA GẦN NHẤT VÔ ĐIỀU KIỆN. Người vào lúc 13:50 cho ca chiều 14:00 mà đem so
	 *    với ca sáng 06:00 thì ra "trễ 470 phút" — một con số vô nghĩa, và nó tô vàng đúng người
	 *    đi sớm 10 phút.
	 */
	const CUA_SO = 240;

	/* ====================================================================== khai ngưỡng */

	public static function ban_do() {
		$m = VHCC_Luong::cai_dat( self::O, array() );
		return is_array( $m ) ? $m : array();
	}

	/** Ngưỡng của một cơ sở, dạng SỐ PHÚT. Khai riêng -> khai chung ('*') -> mặc định. */
	public static function cua( $coso ) {
		$cs = VHCC_NhanSu::chuan_coso( $coso );
		$m  = self::ban_do();
		foreach ( array( $cs, '*' ) as $k ) {
			if ( '' === $k || ! isset( $m[ $k ] ) ) { continue; }
			$v = self::sach( $m[ $k ] );
			if ( null !== $v ) { return $v; }
		}
		return self::MAC_DINH;
	}

	/** Cơ sở này đang dùng ngưỡng RIÊNG hay đang mượn ngưỡng chung / mặc định. */
	public static function nguon( $coso ) {
		$cs = VHCC_NhanSu::chuan_coso( $coso );
		$m  = self::ban_do();
		if ( isset( $m[ $cs ] ) && null !== self::sach( $m[ $cs ] ) )  { return 'rieng'; }
		if ( isset( $m['*'] ) && null !== self::sach( $m['*'] ) )      { return 'chung'; }
		return 'mac_dinh';
	}

	/**
	 * Đọc một ô người ta gõ. null = không dùng được.
	 *
	 * ⚠️ SỐ 0 LÀ MỘT LỰA CHỌN THẬT, không phải "chưa khai": cửa hàng nào muốn trễ một phút cũng
	 *    kêu thì khai 0. Nên phải phân biệt 0 với rỗng — `empty()` gộp hai thứ ấy làm một.
	 */
	public static function sach( $v ) {
		if ( is_array( $v ) || is_object( $v ) ) { return null; }
		$s = trim( (string) $v );
		if ( '' === $s || ! preg_match( '/^\d{1,3}$/', $s ) ) { return null; }
		$n = (int) $s;
		return ( $n >= 0 && $n <= self::TOI_DA ) ? $n : null;
	}

	/**
	 * Cửa hàng trưởng đặt ngưỡng cho cơ sở mình.
	 *
	 * ⚠️ Cùng cửa quyền với khai ca (`lich_lam`, bậc Cửa hàng trưởng) — hai thứ này luôn đi đôi:
	 *    đặt được giờ ca mà không đặt được ngưỡng trễ thì cái ngưỡng ấy nói về một khung giờ
	 *    người khác khai, và ngược lại.
	 */
	public static function dat( $u, $coso, $phut ) {
		if ( ! VHCC_Vai::duoc( $u, 'lich_lam' ) ) {
			return array( 'ok' => false,
				'error' => 'Đặt mức trễ cho phép cần vai Cửa hàng trưởng trở lên.' );
		}
		$cs = VHCC_NhanSu::chuan_coso( $coso );
		if ( '' === $cs ) { return array( 'ok' => false, 'error' => 'Chưa chọn cơ sở.' ); }
		if ( ! VHCC_NhanSu::co_quyen_coso( $u, $cs ) ) {
			return array( 'ok' => false, 'error' => 'Không có quyền cơ sở này.' );
		}
		$m = self::ban_do();
		$s = trim( (string) $phut );
		if ( '' === $s ) {
			/* Xoá khai riêng = quay về mức chung / mặc định, chứ không phải "cơ sở này không
			   bao giờ kêu". Hai thứ ấy khác nhau, và gộp lại là mất đường lùi. */
			unset( $m[ $cs ] );
			VHCC_Luong::dat_cai_dat( self::O, $m, $u );
			return array( 'ok' => true, 'phut' => self::cua( $cs ), 'bo_khai' => true );
		}
		$n = self::sach( $s );
		if ( null === $n ) {
			return array( 'ok' => false,
				'error' => 'Mức trễ phải là số phút từ 0 đến ' . self::TOI_DA . '.' );
		}
		$m[ $cs ] = $n;
		VHCC_Luong::dat_cai_dat( self::O, $m, $u );
		return array( 'ok' => true, 'phut' => $n, 'coSo' => $cs );
	}

	/* ====================================================================== tính trễ */

	/**
	 * LƯỢT CHẤM NÀY TRỄ MẤY PHÚT SO VỚI GIỜ VÀO CA.
	 *
	 * Hàm THUẦN: vào là danh sách ca + giây trong ngày, ra là một con số. Không đọc cơ sở dữ
	 * liệu, không biết cơ sở nào — nên thử được bằng con số trần, và đó là chỗ phải chắc nhất
	 * của cả tính năng.
	 *
	 * @return int số phút trễ; 0 = đúng giờ, đi sớm, hoặc không thuộc ca nào.
	 */
	public static function tre_phut( $ds_ca, $vao_giay, $cuoi_tuan = false ) {
		if ( null === $vao_giay || '' === $vao_giay ) { return 0; }
		$vao = intdiv( (int) $vao_giay, 60 );
		$gan = null;   // độ lệch tuyệt đối nhỏ nhất
		$tre = 0;
		foreach ( (array) $ds_ca as $s ) {
			$tu = ( $cuoi_tuan && '' !== $s['tuW'] ) ? $s['tuW'] : $s['tu'];
			$b1 = VHCC_Ca::phut( $tu );
			if ( null === $b1 ) { continue; }
			/* Thử ca ở cả ba vị trí — hôm qua · hôm nay · ngày mai. Lượt vào lúc 00:30 thuộc ca
			   đêm bắt đầu 22:00 của HÔM QUA; không xét đủ ba thì nó rơi ra ngoài. */
			foreach ( array( -1440, 0, 1440 ) as $dich ) {
				$mo    = $b1 + $dich;
				$lech  = $vao - $mo;
				$tuyet = abs( $lech );
				if ( $tuyet > self::CUA_SO ) { continue; }
				if ( null !== $gan && $tuyet >= $gan ) { continue; }
				$gan = $tuyet;
				$tre = ( $lech > 0 ) ? $lech : 0;
			}
		}
		return (int) $tre;
	}

	/**
	 * MỘT LƯỢT CHẤM CÓ ĐÁNG KÊU KHÔNG — và kêu thì nói gì.
	 *
	 * @return array [ 'tre' => phút trễ, 'keu' => bool, 'muc' => ngưỡng đang dùng, 'chu' => câu ]
	 */
	public static function soi( $coso, $vao_giay, $ngay = '' ) {
		$cs  = VHCC_NhanSu::chuan_coso( $coso );
		$muc = self::cua( $cs );
		$ds  = VHCC_Ca::cua( $cs );
		$ct  = ( '' !== $ngay ) ? VHCC_Ca::la_cuoi_tuan( $ngay ) : false;
		$tre = self::tre_phut( $ds, $vao_giay, $ct );
		$keu = ( $tre > $muc );
		return array(
			'tre' => $tre,
			'keu' => $keu,
			'muc' => $muc,
			'chu' => $keu
				? ( 'Vào trễ ' . $tre . ' phút so với giờ ca (mức cho phép ' . $muc . ' phút).' )
				: '',
		);
	}
}
