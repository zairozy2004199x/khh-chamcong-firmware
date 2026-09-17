<?php
/**
 * ĐƠN XIN NGHỈ — nhân viên nộp trên trạm, cửa hàng trưởng duyệt.
 *
 * =================================================================================================
 * VÌ SAO KHÔNG DÙNG LẠI HAI CỬA ĐÃ CÓ
 * =================================================================================================
 * · `VHCC_Lich::xin_doi_lich` — chỉ có nghĩa ở cơ sở ĐÃ BẬT PHÂN LỊCH. Phần lớn cửa hàng không
 *   bật, nên với họ "xin nghỉ" không có đường nào cả.
 * · `VHCC_YeuCau::gui` — người duyệt là Admin/Quản lý (`co_quan_tri_nv`). Nghỉ một ngày thì
 *   người quyết phải là CỬA HÀNG TRƯỞNG, người biết hôm ấy còn ai đứng quầy. Đẩy lên Admin là
 *   đơn nằm chờ một người không có thông tin để quyết.
 *
 * Nên đây là bộ riêng, đi theo đúng khuôn `VHCC_XinTre`: nộp trên trạm, duyệt theo CƠ SỞ, hiện
 * ở khối của cửa hàng trưởng ngay cạnh "Lệnh đi trễ".
 *
 * =================================================================================================
 * 🔴 ĐƠN ĐƯỢC DUYỆT KHÔNG SINH RA CÔNG, VÀ KHÔNG TRỪ CÔNG
 * =================================================================================================
 * Nó chỉ trả lời câu *"hôm ấy người này vắng có phép hay không"*. Giờ công vẫn là thứ máy chấm
 * công ghi được, không hơn không kém. Cho đơn tự cộng công là mở một cửa cấp công không qua
 * chấm công nào — và cửa ấy không có ai gác. Cùng một luật với `VHCC_XinTre`: *"đơn chỉ bỏ cảnh
 * báo, không bỏ số"*.
 *
 * ⚠️ SỐ NGÀY PHÉP CÒN LẠI: hệ này KHÔNG có sổ phép năm của từng người. Thứ đếm được thật là số
 *    ngày phép năm ĐÃ DUYỆT trong năm, cộng từ chính bảng này. Trần thì lấy từ một ô cài đặt
 *    chung (`PHEP_NAM`) — một con số chính sách do công ty đặt, không phải con số bịa ra cho
 *    từng người. Đặt 0 = không theo dõi, và lúc ấy màn KHÔNG bày "còn lại" chứ không bày số 0.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_XinNghi {

	const CHO     = 'cho';
	const DUYET   = 'duyet';
	const TU_CHOI = 'tu_choi';

	const TEN_TT = array(
		self::CHO     => 'Chờ duyệt',
		self::DUYET   => 'Đã duyệt',
		self::TU_CHOI => 'Không duyệt',
	);

	/** Loại nghỉ. `phep` là loại DUY NHẤT bị trừ vào quỹ phép năm. */
	const PHEP    = 'phep';
	const KHONG_L = 'khong_luong';
	const OM      = 'om';
	const VIEC    = 'viec_rieng';

	const TEN_LOAI = array(
		self::PHEP    => 'Nghỉ phép năm',
		self::KHONG_L => 'Nghỉ không lương',
		self::OM      => 'Nghỉ ốm',
		self::VIEC    => 'Việc riêng',
	);

	/** Xin trước xa nhất, và nộp muộn xa nhất — cùng tinh thần với đơn đi trễ. */
	const TRUOC_TOI_DA = 180;
	const MUON_TOI_DA  = 14;
	/** Một đơn dài nhất bấy nhiêu ngày. Dài hơn là nghỉ dài hạn, việc của hợp đồng chứ không phải đơn. */
	const DAI_TOI_DA   = 60;

	/** Ô cài đặt: số ngày phép năm của công ty. 0 = không theo dõi. */
	const O_PHEP = 'PHEP_NAM';
	const PHEP_MD = 12;

	public static function phep_nam() {
		$v = VHCC_Luong::cai_dat( self::O_PHEP, null );
		if ( null === $v || '' === $v ) { return self::PHEP_MD; }
		$n = (int) $v;
		return ( $n >= 0 && $n <= 365 ) ? $n : self::PHEP_MD;
	}

	public static function dat_phep_nam( $u, $so ) {
		if ( ! VHCC_Vai::duoc( $u, 'ngoai_coso' ) ) {
			return array( 'ok' => false, 'error' => VHCC_Vai::loi( $u, 'ngoai_coso', 'Đặt số ngày phép năm' ) );
		}
		$n = (int) $so;
		if ( $n < 0 || $n > 365 ) { return array( 'ok' => false, 'error' => 'Số ngày phép phải từ 0 đến 365.' ); }
		VHCC_Luong::dat_cai_dat( self::O_PHEP, $n, $u );
		return array( 'ok' => true, 'so' => $n );
	}

	/* ====================================================================== nộp đơn */

	public static function ngay( $s ) {
		$s = trim( (string) $s );
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $s ) ? $s : '';
	}

	/** Số ngày của một khoảng, tính CẢ hai đầu. */
	public static function dem_ngay( $tu, $den ) {
		$a = strtotime( $tu . ' 00:00:00 UTC' );
		$b = strtotime( $den . ' 00:00:00 UTC' );
		if ( false === $a || false === $b || $b < $a ) { return 0; }
		return (int) round( ( $b - $a ) / 86400 ) + 1;
	}

	/**
	 * Nhân viên nộp đơn cho CHÍNH MÌNH.
	 *
	 * ⚠️ MÃ NV LẤY TỪ PHIÊN, KHÔNG LẤY TỪ BIỂU MẪU — cùng lý do với `VHCC_XinTre::nop`.
	 */
	public static function nop( $u, $dat ) {
		global $wpdb;
		$ma = trim( (string) ( isset( $u['ma_nv'] ) ? $u['ma_nv'] : '' ) );
		if ( '' === $ma ) {
			return array( 'ok' => false, 'error' => 'Tài khoản này chưa bật chấm công online.' );
		}

		$tu  = self::ngay( isset( $dat['tu'] ) ? $dat['tu'] : '' );
		$den = self::ngay( isset( $dat['den'] ) ? $dat['den'] : '' );
		if ( '' === $tu ) { return array( 'ok' => false, 'error' => 'Chọn ngày bắt đầu nghỉ.' ); }
		if ( '' === $den ) { $den = $tu; }          // nghỉ một ngày: để trống ô "đến"
		if ( $den < $tu ) {
			return array( 'ok' => false, 'error' => 'Ngày kết thúc sớm hơn ngày bắt đầu.' );
		}

		$so_ngay = self::dem_ngay( $tu, $den );
		if ( $so_ngay > self::DAI_TOI_DA ) {
			return array( 'ok' => false, 'error' => 'Một đơn dài nhất ' . self::DAI_TOI_DA
				. ' ngày. Nghỉ dài hơn là việc của hợp đồng, nhờ quản lý làm giúp.' );
		}

		$hom_nay = (string) current_time( 'Y-m-d' );
		$lech    = (int) round( ( strtotime( $tu . ' 00:00:00 UTC' )
			- strtotime( $hom_nay . ' 00:00:00 UTC' ) ) / 86400 );
		if ( $lech > self::TRUOC_TOI_DA ) {
			return array( 'ok' => false, 'error' => 'Chỉ xin trước tối đa ' . self::TRUOC_TOI_DA . ' ngày.' );
		}
		if ( $lech < -self::MUON_TOI_DA ) {
			return array( 'ok' => false, 'error' => 'Ngày ' . $tu . ' đã qua quá ' . self::MUON_TOI_DA
				. ' ngày — việc này thuộc về bảng lương, không phải đơn xin nghỉ.' );
		}

		$loai = isset( $dat['loai'] ) ? (string) $dat['loai'] : '';
		if ( ! isset( self::TEN_LOAI[ $loai ] ) ) {
			return array( 'ok' => false, 'error' => 'Chọn loại nghỉ.' );
		}
		$ly_do = trim( (string) ( isset( $dat['lyDo'] ) ? $dat['lyDo'] : '' ) );
		if ( '' === $ly_do ) {
			return array( 'ok' => false, 'error' => 'Điền lý do — người duyệt quyết theo lý do, '
				. 'không quyết theo số ngày.' );
		}

		$hs   = VHCC_NhanSu::ho_so( $ma );
		$coso = VHCC_NhanSu::chuan_coso( $hs && isset( $hs['cua_hang'] ) ? $hs['cua_hang'] : '' );
		if ( '' === $coso ) { $coso = VHCC_NhanSu::chuan_coso( isset( $u['coso'] ) ? $u['coso'] : '' ); }
		if ( '' === $coso ) {
			return array( 'ok' => false, 'error' => 'Hồ sơ của anh/chị chưa khai cửa hàng nên đơn '
				. 'không biết gửi cho ai duyệt.' );
		}
		$ho_ten = ( $hs && ! empty( $hs['ho_ten'] ) ) ? (string) $hs['ho_ten']
			: trim( (string) ( isset( $u['ho_ten'] ) ? $u['ho_ten'] : '' ) );

		/* 🔴 CHỐNG CHỒNG KHOẢNG, KHÔNG PHẢI CHỐNG TRÙNG KHÍT. Khoá duy nhất chỉ bắt được hai đơn
		   y hệt nhau; cái hay xảy ra là nộp 10–12 rồi nộp tiếp 11–14. Hai đơn ấy chồng một phần,
		   và người duyệt không có cách nào biết cái nào là ý thật của người xin. */
		$bang = VHCC_DB::t( 'xin_nghi' );
		$chong = $wpdb->get_row( $wpdb->prepare(
			"SELECT id, tu_ngay, den_ngay FROM $bang
			 WHERE ma_nv=%s AND trang_thai IN (%s,%s) AND tu_ngay <= %s AND den_ngay >= %s LIMIT 1",
			$ma, self::CHO, self::DUYET, $den, $tu ), ARRAY_A );
		if ( $chong ) {
			return array( 'ok' => false, 'error' => 'Anh/chị đã có đơn nghỉ từ ' . $chong['tu_ngay']
				. ' đến ' . $chong['den_ngay'] . ' trùng vào khoảng này. Nhờ quản lý xử đơn cũ trước, '
				. 'hoặc chọn khoảng khác.' );
		}

		$ok = $wpdb->insert( $bang, array(
			'coso'       => $coso,
			'ma_nv'      => $ma,
			'ho_ten'     => $ho_ten,
			'tu_ngay'    => $tu,
			'den_ngay'   => $den,
			'so_ngay'    => $so_ngay,
			'loai'       => $loai,
			'ly_do'      => mb_substr( $ly_do, 0, 250 ),
			'trang_thai' => self::CHO,
			'tao_luc'    => current_time( 'mysql' ),
		) );
		if ( false === $ok ) {
			return array( 'ok' => false, 'error' => 'MySQL: ' . $wpdb->last_error );
		}
		return array( 'ok' => true, 'id' => (int) $wpdb->insert_id, 'tu' => $tu, 'den' => $den,
			'soNgay' => $so_ngay, 'coSo' => $coso, 'muon' => ( $lech < 0 ) );
	}

	/* ====================================================================== duyệt */

	/**
	 * Cửa hàng trưởng duyệt / không duyệt một đơn của CƠ SỞ MÌNH.
	 *
	 * ⚠️ Chốt cơ sở đọc từ CHÍNH ĐƠN, không từ biểu mẫu — id đơn gõ tay được, và một cửa hàng
	 *    trưởng gõ đúng id là duyệt được đơn của cửa hàng khác. Cùng luật với `VHCC_XinTre::duyet`.
	 */
	public static function duyet( $u, $id, $dat = self::DUYET, $ly_do_choi = '' ) {
		global $wpdb;
		$bang = VHCC_DB::t( 'xin_nghi' );
		$d = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $bang WHERE id=%d", (int) $id ), ARRAY_A );
		if ( ! $d ) { return array( 'ok' => false, 'error' => 'Không thấy đơn này.' ); }
		if ( ! VHCC_NhanSu::co_quyen_coso( $u, $d['coso'] ) ) {
			return array( 'ok' => false, 'error' => 'Không có quyền cơ sở này.' );
		}
		if ( ! in_array( $dat, array( self::DUYET, self::TU_CHOI ), true ) ) {
			return array( 'ok' => false, 'error' => 'Quyết định không hợp lệ.' );
		}
		$wpdb->update( $bang, array(
			'trang_thai'  => $dat,
			'nguoi_duyet' => isset( $u['name'] ) ? (string) $u['name'] : '',
			'ly_do_choi'  => ( self::TU_CHOI === $dat ) ? mb_substr( trim( (string) $ly_do_choi ), 0, 250 ) : '',
			'duyet_luc'   => current_time( 'mysql' ),
		), array( 'id' => (int) $d['id'] ) );
		return array( 'ok' => true, 'id' => (int) $d['id'], 'quyet' => $dat );
	}

	/* ====================================================================== đọc */

	public static function cho_duyet( $coso, $so = 100 ) {
		global $wpdb;
		$cs = VHCC_NhanSu::chuan_coso( $coso );
		if ( '' === $cs ) { return array(); }
		$r = $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . VHCC_DB::t( 'xin_nghi' )
			. ' WHERE LOWER(coso)=LOWER(%s) AND trang_thai=%s ORDER BY tu_ngay, id',
			$cs, self::CHO ), ARRAY_A );
		return is_array( $r ) ? $r : array();
	}

	/** Đơn gần đây của MỘT người — để họ tự thấy đơn mình nộp và kết quả. */
	public static function cua_nguoi( $ma_nv, $so = 12 ) {
		global $wpdb;
		$ma = trim( (string) $ma_nv );
		if ( '' === $ma ) { return array(); }
		$r = $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . VHCC_DB::t( 'xin_nghi' )
			. ' WHERE ma_nv=%s ORDER BY tu_ngay DESC, id DESC LIMIT %d', $ma, max( 1, (int) $so ) ),
			ARRAY_A );
		return is_array( $r ) ? $r : array();
	}

	/**
	 * PHÉP NĂM ĐÃ DÙNG trong một năm — cộng từ chính bảng này, chỉ đơn ĐÃ DUYỆT, chỉ loại `phep`.
	 *
	 * ⚠️ CHỈ ĐẾM ĐƠN ĐÃ DUYỆT. Đếm cả đơn đang chờ thì con số "còn lại" tụt xuống ngay lúc nộp,
	 *    rồi nhảy lại lên nếu đơn bị từ chối — người nhìn không hiểu vì sao số của mình đổi.
	 * ⚠️ ĐẾM THEO `tu_ngay`. Đơn vắt qua giao thừa bị tính trọn vào năm bắt đầu; chia đôi cho
	 *    đúng thì phải cắt từng ngày, mà cái giá ấy không đáng cho một trường hợp một năm một lần
	 *    — và cách chia nào cũng cần một luật công ty chưa ai đặt.
	 */
	public static function phep_da_dung( $ma_nv, $nam = '' ) {
		global $wpdb;
		$ma = trim( (string) $ma_nv );
		if ( '' === $ma ) { return 0.0; }
		if ( '' === $nam ) { $nam = (string) current_time( 'Y' ); }
		if ( ! preg_match( '/^\d{4}$/', (string) $nam ) ) { return 0.0; }
		$v = $wpdb->get_var( $wpdb->prepare(
			'SELECT SUM(so_ngay) FROM ' . VHCC_DB::t( 'xin_nghi' )
			. ' WHERE ma_nv=%s AND loai=%s AND trang_thai=%s AND tu_ngay LIKE %s',
			$ma, self::PHEP, self::DUYET, $nam . '-%' ) );
		return (float) $v;
	}

	/** Gói số liệu phép cho màn trạm. `tran` = 0 nghĩa là công ty không theo dõi phép năm. */
	public static function quy_phep( $ma_nv ) {
		$tran = self::phep_nam();
		$dung = self::phep_da_dung( $ma_nv );
		return array(
			'tran'   => $tran,
			'daDung' => $dung,
			'conLai' => ( $tran > 0 ) ? max( 0, $tran - $dung ) : null,
			'nam'    => (string) current_time( 'Y' ),
		);
	}
}
