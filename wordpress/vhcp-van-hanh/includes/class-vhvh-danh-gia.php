<?php
/**
 * ĐÁNH GIÁ NHÂN VIÊN — vi phạm & khen, theo BẢNG PHẠT.
 *
 * =================================================================================================
 * 🔴 LẦN THỨ MẤY TÍNH LÚC GHI, KHÔNG TÍNH LÚC ĐỌC
 * =================================================================================================
 * Mức phạt theo bảng tăng dần theo lần 1 / 2 / 3. Nếu tính "lần thứ mấy" lúc ĐỌC thì xoá một bản
 * ghi cũ là mọi bản sau nó tụt một bậc phạt — trong khi biên bản đã ký, và người ta đã nộp tiền
 * theo mức cũ. Nên `lan_thu`, `diem`, `phat` chốt ngay lúc ghi và không đổi nữa.
 *
 * =================================================================================================
 * 🔴 ĐIỂM TRỪ LÀ VIỆC CỦA MÁY CHỦ
 * =================================================================================================
 * Trang chỉ gửi *ai · lỗi gì · ghi chú*. Mức độ, lần thứ mấy, điểm trừ, mức phạt đều tra từ bảng
 * phạt ở máy chủ. Nhận điểm do trang gửi thì ai cũng tự ghi cho mình một vi phạm 0 điểm.
 *
 * ⚠️ `fines` chỉ để HIỆN CHO QUẢN LÝ THAM KHẢO. Plugin này không đụng gì tới lương — trừ tiền là
 *    việc của người làm lương, và một phần mềm tự trừ lương thì sai một lần là mất lòng tin vĩnh
 *    viễn.
 *
 * @package VHCP_VanHanh
 */

defined( 'ABSPATH' ) || exit;

class VHVH_DanhGia {

	const MUC_NHAN = array( 'nhe' => 'Nhẹ', 'trung_binh' => 'Trung bình',
		'nang' => 'Nặng', 'nghiem_trong' => 'Nghiêm trọng' );

	/** Điểm trừ theo mức độ, nhân dần theo lần vi phạm trong kỳ. */
	const DIEM_TRU = array( 'nhe' => 2, 'trung_binh' => 5, 'nang' => 10, 'nghiem_trong' => 25 );

	/** Điểm khởi đầu của một kỳ. Trừ dần, cộng lại khi có khen. */
	const DIEM_DAU = 100;

	/**
	 * Bảng phạt — trích từ "BẢNG PHẠT GHOST BRIDE" và "CHI TIẾT VẬN HÀNH GHOST BRIDE".
	 * `phat` là mức theo lần 1 / 2 / 3, lấy nguyên văn, CHỈ để hiện tham khảo.
	 */
	public static function mac_dinh() {
		return array(
			array( 'khoa' => 'thoi_gian', 'ten' => 'Vi phạm thời gian làm việc', 'muc' => array(
				array( 'khoa' => 'di_tre_duoi_15', 'ten' => 'Đi trễ <15 phút không báo trước', 'do' => 'nhe',
					'phat' => array( 'Nhắc nhở', '50.000đ', '100.000đ + biên bản' ) ),
				array( 'khoa' => 'di_tre_tren_15', 'ten' => 'Đi trễ >15 phút không báo trước', 'do' => 'trung_binh',
					'phat' => array( '100.000đ', '150.000đ + 0.5 ca', '200.000đ + 1 ca' ) ),
				array( 'khoa' => 've_som', 'ten' => 'Về sớm không phép', 'do' => 'trung_binh',
					'phat' => array( '100.000đ', '150.000đ', '200.000đ' ) ),
				array( 'khoa' => 'khong_checkin', 'ten' => 'Không check-in/check-out', 'do' => 'nang',
					'phat' => array( '0 lương ca', '0 lương + nhắc', '0 lương + biên bản' ) ),
				array( 'khoa' => 'tu_y_doi_ca', 'ten' => 'Tự ý đổi ca không xác nhận', 'do' => 'trung_binh',
					'phat' => array( 'Nhắc nhở', '100.000đ', 'Phạt cả 2 người' ) ),
				array( 'khoa' => 'bo_ca', 'ten' => 'Bỏ ca không phép', 'do' => 'nghiem_trong',
					'phat' => array( '500.000đ', 'Nghỉ việc không lương', 'Nghỉ việc không lương' ) ),
			) ),
			array( 'khoa' => 'tac_phong', 'ten' => 'Đồng phục & tác phong', 'muc' => array(
				array( 'khoa' => 'sai_dong_phuc', 'ten' => 'Sai đồng phục', 'do' => 'nhe',
					'phat' => array( 'Nhắc nhở', '50.000đ', '100.000đ' ) ),
				array( 'khoa' => 'ngoai_hinh', 'ten' => 'Ngoại hình không đúng chuẩn', 'do' => 'nhe',
					'phat' => array( 'Nhắc nhở', '50.000đ', '100.000đ' ) ),
				array( 'khoa' => 'ngoi_tua_lung', 'ten' => 'Ngồi/tựa lưng khi có khách', 'do' => 'trung_binh',
					'phat' => array( 'Nhắc nhở', '100.000đ', '200.000đ' ) ),
				array( 'khoa' => 'dien_thoai', 'ten' => 'Dùng điện thoại không đúng mục đích', 'do' => 'trung_binh',
					'phat' => array( 'Nhắc nhở', '100.000đ', '200.000đ' ) ),
				array( 'khoa' => 'an_uong_khu_khach', 'ten' => 'Ăn uống khu vực khách', 'do' => 'nhe',
					'phat' => array( 'Nhắc nhở', '50.000đ', '100.000đ' ) ),
				array( 'khoa' => 'roi_vi_tri', 'ten' => 'Tự ý rời vị trí >5 phút', 'do' => 'trung_binh',
					'phat' => array( 'Nhắc nhở', '100.000đ', '200.000đ' ) ),
				array( 'khoa' => 'khong_checklist', 'ten' => 'Không checklist đầu/cuối ca', 'do' => 'nhe',
					'phat' => array( 'Nhắc nhở', '50.000đ', '100.000đ' ) ),
				array( 'khoa' => 'checklist_sai', 'ten' => 'Checklist sai sự thật', 'do' => 'nang',
					'phat' => array( '100.000đ', '200.000đ', '400.000đ' ) ),
				array( 'khoa' => 'khong_ban_giao', 'ten' => 'Không bàn giao đầy đủ', 'do' => 'trung_binh',
					'phat' => array( 'Nhắc + bổ sung', '100.000đ', '200.000đ' ) ),
				array( 'khoa' => 'khu_vuc_ban', 'ten' => 'Khu vực làm việc bẩn, bỏ sót vệ sinh', 'do' => 'nhe',
					'phat' => array( 'Nhắc + dọn lại', '50.000đ', '100.000đ' ) ),
			) ),
			array( 'khoa' => 'gian_lan', 'ten' => 'Gian lận & chống đối', 'muc' => array(
				array( 'khoa' => 'free_ve_trai_phep', 'ten' => 'Tự ý free vé / huỷ bill / giảm giá trái phép', 'do' => 'nang',
					'phat' => array( '300.000đ + bồi thường', '500.000đ + bồi thường', 'Chấm dứt hợp tác' ) ),
				array( 'khoa' => 'gian_lan_doanh_thu', 'ten' => 'Gian lận doanh thu (ôm tiền, không xuất bill)', 'do' => 'nghiem_trong',
					'phat' => array( 'Chấm dứt hợp tác + bồi thường toàn bộ', 'Chấm dứt hợp tác + bồi thường toàn bộ', 'Chấm dứt hợp tác + bồi thường toàn bộ' ) ),
				array( 'khoa' => 'khong_tuan_thu', 'ten' => 'Không tuân thủ chỉ đạo của Quản lý/CHT', 'do' => 'nang',
					'phat' => array( '100.000đ', '200.000đ', 'Chấm dứt hợp tác' ) ),
				array( 'khoa' => 'thai_do', 'ten' => 'Thái độ thiếu tôn trọng quản lý/đồng nghiệp', 'do' => 'nang',
					'phat' => array( '100.000đ + nhắc nhở', '200.000đ', '400.000đ + đình chỉ' ) ),
				array( 'khoa' => 'bao_che', 'ten' => 'Bao che, không báo cáo vi phạm của đồng nghiệp', 'do' => 'nang',
					'phat' => array( '100.000đ', '200.000đ', 'Chấm dứt hợp tác' ) ),
				array( 'khoa' => 'hu_hong_tai_san', 'ten' => 'Làm hư hỏng tài sản do chủ quan', 'do' => 'nang',
					'phat' => array( 'Bồi thường 100%', 'Bồi thường + 200.000đ', 'Bồi thường + đình chỉ' ) ),
				array( 'khoa' => 'anh_huong_khach', 'ten' => 'Hành vi ảnh hưởng xấu tới trải nghiệm khách', 'do' => 'nang',
					'phat' => array( '200.000đ + nhắc nhở', '400.000đ + biên bản', 'Chấm dứt hợp tác' ) ),
				array( 'khoa' => 'khong_bao_su_co', 'ten' => 'Không báo cáo kịp thời sự cố trong ca', 'do' => 'trung_binh',
					'phat' => array( '100.000đ', '200.000đ', '300.000đ + biên bản' ) ),
			) ),
			array( 'khoa' => 'khu_vuc', 'ten' => 'Quy trình vận hành theo khu vực', 'muc' => array(
				array( 'khoa' => 'tn_khong_chup', 'ten' => 'Thu ngân: không chụp báo cáo gian hàng đúng khung giờ', 'do' => 'nhe', 'phat' => null ),
				array( 'khoa' => 'tn_khong_doi_chieu', 'ten' => 'Thu ngân: không đối chiếu/lập báo cáo cuối ca', 'do' => 'trung_binh', 'phat' => null ),
				array( 'khoa' => 'kt_khong_test', 'ten' => 'Kỹ thuật: không test hệ thống đầu ca', 'do' => 'trung_binh', 'phat' => null ),
				array( 'khoa' => 'kt_khong_bao_loi', 'ten' => 'Kỹ thuật: không báo quản lý khi có lỗi nặng', 'do' => 'nang', 'phat' => null ),
				array( 'khoa' => 'dv_cham_khach', 'ten' => 'Diễn viên: chạm vào khách hoặc cản lối thoát', 'do' => 'nang', 'phat' => null ),
				array( 'khoa' => 'dv_khong_dung', 'ten' => 'Diễn viên: không dừng diễn khi khách hoảng loạn quá mức', 'do' => 'nang', 'phat' => null ),
				array( 'khoa' => 'dv_bo_vi_tri', 'ten' => 'Diễn viên: bỏ vị trí diễn khi có khách', 'do' => 'trung_binh', 'phat' => null ),
			) ),
		);
	}

	/** Danh mục khen — cộng điểm lại. */
	public static function khen_mac_dinh() {
		return array(
			array( 'khoa' => 'khach_khen', 'ten' => 'Khách khen trực tiếp / để lại đánh giá tốt', 'diem' => 5 ),
			array( 'khoa' => 'ho_tro_ca_khac', 'ten' => 'Hỗ trợ ca khác khi thiếu người', 'diem' => 5 ),
			array( 'khoa' => 'phat_hien_su_co', 'ten' => 'Phát hiện và báo sự cố sớm', 'diem' => 3 ),
			array( 'khoa' => 'sang_kien', 'ten' => 'Sáng kiến cải thiện vận hành', 'diem' => 10 ),
			array( 'khoa' => 'doanh_thu_vuot', 'ten' => 'Góp phần vượt chỉ tiêu doanh thu', 'diem' => 5 ),
		);
	}

	public static function danh_muc() {
		global $wpdb;
		$r = $wpdb->get_var( $wpdb->prepare(
			'SELECT noi_dung FROM ' . VHVH_DB::t( 'danh_muc' ) . ' WHERE loai=%s AND pham_vi=%s',
			'vi_pham', '' ) );
		if ( $r ) {
			$j = json_decode( (string) $r, true );
			if ( is_array( $j ) && $j ) { return $j; }
		}
		return self::mac_dinh();
	}

	/** Tra một lỗi theo khoá. Không có thì null — và lúc ghi sẽ bị chối. */
	public static function tim( $khoa ) {
		foreach ( self::danh_muc() as $nhom ) {
			foreach ( $nhom['muc'] as $m ) {
				if ( $m['khoa'] === $khoa ) {
					$m['nhom'] = $nhom['khoa'];
					return $m;
				}
			}
		}
		return null;
	}

	public static function tim_khen( $khoa ) {
		foreach ( self::khen_mac_dinh() as $m ) {
			if ( $m['khoa'] === $khoa ) { return $m; }
		}
		return null;
	}

	/** Lần vi phạm thứ mấy của người này, với lỗi này, trong kỳ này. Đếm rồi + 1. */
	public static function lan_thu( $ma_nv, $ky, $khoa ) {
		global $wpdb;
		return 1 + (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . VHVH_DB::t( 'danh_gia' )
			. " WHERE ma_nv=%s AND ky=%s AND khoa=%s AND loai='vi_pham'", $ma_nv, $ky, $khoa ) );
	}

	/**
	 * Ghi một vi phạm hoặc một lời khen.
	 *
	 * 🔴 CHỈ CỬA HÀNG TRƯỞNG TRỞ LÊN. Nhân viên tự ghi vi phạm cho nhau là cái sổ này thành chỗ
	 *    đấu đá, và không ai tin con số cuối kỳ nữa.
	 */
	public static function ghi( $u, $d ) {
		global $wpdb;
		if ( ! VHVH_Auth::du_quyen( $u, 'cua_hang_truong' ) ) { return VHVH_Auth::choi(); }

		$coso = isset( $d['coso'] ) ? trim( sanitize_text_field( (string) $d['coso'] ) ) : '';
		if ( '' === $coso || ! VHVH_Auth::duoc_coso( $u, $coso ) ) { return VHVH_Auth::choi(); }

		$ten = isset( $d['ten'] ) ? mb_substr( sanitize_text_field( (string) $d['ten'] ), 0, 190 ) : '';
		if ( '' === trim( $ten ) ) { return array( 'ok' => false, 'error' => 'Chọn người.' ); }

		$ky = isset( $d['ky'] ) ? trim( (string) $d['ky'] ) : '';
		if ( ! preg_match( '/^\d{4}-\d{2}$/', $ky ) ) { $ky = current_time( 'Y-m' ); }

		$loai  = ( isset( $d['loai'] ) && 'khen' === $d['loai'] ) ? 'khen' : 'vi_pham';
		$khoa  = isset( $d['khoa'] ) ? preg_replace( '/[^a-z0-9_]/', '', strtolower( (string) $d['khoa'] ) ) : '';
		$ma_nv = isset( $d['ma_nv'] ) ? mb_substr( sanitize_text_field( (string) $d['ma_nv'] ), 0, 40 ) : '';
		/* Không có Mã NV thì khoá theo TÊN — sổ vẫn cộng đúng, chỉ là hai người trùng tên sẽ dính
		   chung. Nói ra ở màn hình, còn hơn chối không cho ghi. */
		$khoa_nguoi = '' !== $ma_nv ? $ma_nv : 'ten:' . $ten;

		if ( 'khen' === $loai ) {
			$m = self::tim_khen( $khoa );
			if ( ! $m ) { return array( 'ok' => false, 'error' => 'Chọn loại khen.' ); }
			$hang = array(
				'loai' => 'khen', 'khoa' => $khoa, 'nhan' => $m['ten'], 'nhom' => 'khen',
				'muc' => 'nhe', 'lan_thu' => 1, 'diem' => (int) $m['diem'], 'phat' => '',
			);
		} else {
			$m = self::tim( $khoa );
			if ( ! $m ) { return array( 'ok' => false, 'error' => 'Chọn loại vi phạm.' ); }
			$lan  = self::lan_thu( $khoa_nguoi, $ky, $khoa );
			$do   = isset( self::DIEM_TRU[ $m['do'] ] ) ? self::DIEM_TRU[ $m['do'] ] : 2;
			/* Lần 2 nặng gấp rưỡi, lần 3 trở đi gấp đôi — cùng nhịp với bảng phạt tiền. */
			$nhan = ( 1 === $lan ) ? 1 : ( ( 2 === $lan ) ? 1.5 : 2 );
			$phat = '';
			if ( ! empty( $m['phat'] ) && is_array( $m['phat'] ) ) {
				$i = min( $lan, count( $m['phat'] ) ) - 1;
				$phat = (string) $m['phat'][ $i ];
			}
			$hang = array(
				'loai' => 'vi_pham', 'khoa' => $khoa, 'nhan' => $m['ten'],
				'nhom' => $m['nhom'], 'muc' => $m['do'], 'lan_thu' => $lan,
				'diem' => (int) round( $do * $nhan ), 'phat' => mb_substr( $phat, 0, 250 ),
			);
		}

		$hang = array_merge( $hang, array(
			'coso'      => $coso,
			'ma_nv'     => $khoa_nguoi,
			'ten'       => $ten,
			'ky'        => $ky,
			'ghi'       => isset( $d['ghi'] ) ? mb_substr( sanitize_textarea_field( (string) $d['ghi'] ), 0, 2000 ) : '',
			'nguoi_ghi' => (string) $u['ten'],
			'tao'       => current_time( 'mysql' ),
		) );
		$wpdb->insert( VHVH_DB::t( 'danh_gia' ), $hang );
		$hang['id'] = (int) $wpdb->insert_id;
		return array( 'ok' => true, 'ban' => $hang );
	}

	/**
	 * Xoá một dòng — chỉ quản lý, và CHỈ dòng mới nhất của người+lỗi ấy trong kỳ.
	 *
	 * 🔴 Xoá dòng ở GIỮA thì mọi dòng sau nó mang `lan_thu` sai (lần 3 mà chỉ còn 2 dòng trước
	 *    nó), và mức phạt đã chốt không còn khớp với sổ. Chặn ở đây thay vì đi tính lại cả dãy:
	 *    tính lại là sửa một biên bản đã ký.
	 */
	public static function xoa( $u, $id ) {
		global $wpdb;
		if ( ! VHVH_Auth::du_quyen( $u, 'quan_ly' ) ) { return VHVH_Auth::choi(); }
		$t = VHVH_DB::t( 'danh_gia' );
		$r = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id=%d", (int) $id ), ARRAY_A );
		if ( ! $r ) { return array( 'ok' => false, 'error' => 'Không thấy dòng này.' ); }

		if ( 'vi_pham' === $r['loai'] ) {
			$sau = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM $t WHERE ma_nv=%s AND ky=%s AND khoa=%s AND loai='vi_pham' AND id>%d",
				$r['ma_nv'], $r['ky'], $r['khoa'], (int) $id ) );
			if ( $sau > 0 ) {
				return array( 'ok' => false, 'error' =>
					'Còn ' . $sau . ' lần vi phạm cùng lỗi ghi SAU dòng này. Xoá dòng ở giữa thì mấy '
					. 'dòng sau mang "lần thứ mấy" sai và mức phạt đã chốt không còn khớp. Xoá từ '
					. 'dòng mới nhất trở ngược lại.' );
			}
		}
		$wpdb->delete( $t, array( 'id' => (int) $id ) );
		return array( 'ok' => true );
	}

	/** Danh sách theo kỳ. */
	public static function ds( $u, $ky, $coso = '', $ma_nv = '' ) {
		global $wpdb;
		if ( ! preg_match( '/^\d{4}-\d{2}$/', (string) $ky ) ) { $ky = current_time( 'Y-m' ); }
		$dk  = array( 'ky=%s' );
		$gt  = array( $ky );
		$cho = VHVH_Auth::coso_duoc( $u );
		if ( true !== $cho ) {
			if ( ! $cho ) { return array(); }
			$dk[] = 'coso IN (' . implode( ',', array_fill( 0, count( $cho ), '%s' ) ) . ')';
			$gt   = array_merge( $gt, $cho );
		}
		if ( '' !== trim( (string) $coso ) ) {
			if ( ! VHVH_Auth::duoc_coso( $u, $coso ) ) { return array(); }
			$dk[] = 'coso=%s'; $gt[] = trim( (string) $coso );
		}
		if ( '' !== trim( (string) $ma_nv ) ) { $dk[] = 'ma_nv=%s'; $gt[] = trim( (string) $ma_nv ); }
		$r = $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . VHVH_DB::t( 'danh_gia' ) . ' WHERE ' . implode( ' AND ', $dk )
			. ' ORDER BY id DESC LIMIT 500', $gt ), ARRAY_A );
		return array_map( function ( $x ) {
			$x['id'] = (int) $x['id']; $x['diem'] = (int) $x['diem']; $x['lan_thu'] = (int) $x['lan_thu'];
			return $x;
		}, $r ? $r : array() );
	}

	/**
	 * Bảng xếp loại một kỳ: mỗi người một dòng, điểm còn lại và xếp loại.
	 *
	 * 🔴 CÓ MỘT LỖI MỨC NGHIÊM TRỌNG THÌ TỐI ĐA CHỈ ĐẠT "TRUNG BÌNH", dù điểm còn cao. Gian lận
	 *    doanh thu một lần mà vẫn xếp Tốt vì tháng ấy không vi phạm gì khác thì cái bảng xếp loại
	 *    này chẳng còn nghĩa gì.
	 */
	public static function xep_loai( $u, $ky, $coso = '' ) {
		$ds  = self::ds( $u, $ky, $coso );
		$gom = array();
		foreach ( $ds as $x ) {
			$k = $x['ma_nv'];
			if ( ! isset( $gom[ $k ] ) ) {
				$gom[ $k ] = array( 'ma_nv' => $k, 'ten' => $x['ten'], 'coso' => $x['coso'],
					'diem' => self::DIEM_DAU, 'vi_pham' => 0, 'khen' => 0, 'co_nghiem_trong' => 0 );
			}
			if ( 'khen' === $x['loai'] ) {
				$gom[ $k ]['diem'] += $x['diem'];
				$gom[ $k ]['khen']++;
			} else {
				$gom[ $k ]['diem'] -= $x['diem'];
				$gom[ $k ]['vi_pham']++;
				if ( 'nghiem_trong' === $x['muc'] ) { $gom[ $k ]['co_nghiem_trong'] = 1; }
			}
		}
		foreach ( $gom as $k => $g ) {
			$gom[ $k ]['diem'] = max( 0, min( 100, $g['diem'] ) );
			$gom[ $k ]['xep']  = self::xep( $gom[ $k ]['diem'], $g['co_nghiem_trong'] );
		}
		usort( $gom, function ( $a, $b ) { return $a['diem'] - $b['diem']; } );
		return array_values( $gom );
	}

	public static function xep( $diem, $co_nghiem_trong = 0 ) {
		if ( $co_nghiem_trong ) { return $diem >= 50 ? 'trung_binh' : 'kem'; }
		if ( $diem >= 90 ) { return 'xuat_sac'; }
		if ( $diem >= 75 ) { return 'tot'; }
		if ( $diem >= 50 ) { return 'trung_binh'; }
		return 'kem';
	}

	const XEP_NHAN = array( 'xuat_sac' => 'Xuất sắc', 'tot' => 'Tốt',
		'trung_binh' => 'Trung bình', 'kem' => 'Kém' );
}
