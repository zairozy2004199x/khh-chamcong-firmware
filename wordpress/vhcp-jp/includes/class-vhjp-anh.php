<?php
/**
 * ẢNH — phần ĐẾM và CẢNH BÁO của `JP2_07_Anh.gs`.
 *
 * =============================================================================================
 * ĐÃ CÓ CẢ ĐẾM LẪN TẢI LÊN.
 * =============================================================================================
 * Bản gốc cất ảnh trên Google Drive. Bản này cất vào thư mục tải lên của WordPress: ảnh MỚI
 * không có `fileId`, chỉ có `url` — đúng nhánh lùi mà `VHJP_BaoCao::pub_anh()` đã chừa sẵn.
 * Ảnh CŨ trên Drive vẫn xem được, không phải chuyển gì.
 *
 * Phần ĐẾM thì không đụng chỗ cất ảnh: nó chỉ đọc bảng dòng · khu vực · ảnh · máy · cụm rồi so
 * với cấu hình. Có nó thì:
 *   · lúc NỘP mới chốt được cảnh báo thiếu ảnh vào báo cáo để kế toán còn thấy;
 *   · lúc MỞ báo cáo mới trả được `anhTienDo` — thứ bản gốc cố ý gộp vào cùng một lượt gọi
 *     vì *"bấm cái nào cũng load lâu quá"*.
 *
 * ---------------------------------------------------------------------------------------------
 * 🔴 THIẾU ẢNH THÌ CẢNH BÁO, KHÔNG CHẶN NỘP.
 * ---------------------------------------------------------------------------------------------
 * Mạng ở trung tâm thương mại hay hỏng; chặn là nhân viên mắc kẹt cả buổi. Nhưng phải LƯU LẠI
 * vào báo cáo, chứ không chỉ hiện thoáng trên máy nhân viên lúc bấm nộp — không lưu thì kế toán
 * mở báo cáo ra không thấy gì và không có căn cứ trả về.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHJP_Anh {

	/**
	 * Cảnh báo thiếu ảnh của MỘT báo cáo. Dùng chung cho màn tiến độ ảnh (nhân viên) và cho
	 * lúc nộp.
	 *
	 * ⚠️ `$ma_coso` để phép kiểm biết cơ sở này CÓ cụm nhận QR hay không (W17). Người gọi
	 *    không đưa thì TỰ ĐỌC, đừng bỏ qua im lặng — bỏ qua là thêm một người gọi mới ở đâu
	 *    đó là W17 tắt mà không ai biết.
	 */
	public static function canh_bao( $ma_bc, $ma_coso = '' ) {
		$rows   = VHJP_Nguon::tim( 'JP_Rows', 'reportId', $ma_bc );
		$zones  = VHJP_Nguon::tim( 'JP_Zones', 'reportId', $ma_bc );
		$photos = VHJP_Nguon::tim( 'JP_Photos', 'reportId', $ma_bc );

		$ban_do_may = array();
		foreach ( VHJP_Nguon::doc( 'JP_Machines' ) as $m ) { $ban_do_may[ (string) $m['id'] ] = $m; }
		$ban_do_cum = array();
		foreach ( VHJP_Nguon::doc( 'JP_Clusters' ) as $c ) { $ban_do_cum[ (string) $c['id'] ] = $c; }

		$loc = VHJP_Doc::str( $ma_coso );
		if ( '' === $loc ) {
			$h = VHJP_Nguon::tim_mot( 'JP_Reports', 'id', $ma_bc );
			$loc = $h ? VHJP_Doc::str( $h['locationId'] ) : '';
		}

		return array(
			'warns' => VHJP_Tinh::kiem_anh( $rows, $zones, $photos, $ban_do_may, $ban_do_cum, $loc ),
			'rows' => $rows, 'zones' => $zones, 'photos' => $photos,
			'banDoMay' => $ban_do_may, 'banDoCum' => $ban_do_cum,
		);
	}

	/**
	 * TIẾN ĐỘ ẢNH — cần bao nhiêu, có bao nhiêu, còn thiếu mấy chỗ.
	 *
	 * ⚠️ `missing` là SỐ CHỖ THIẾU ẢNH, nên CHỈ đếm cảnh báo W7 — đừng đếm cả W17. W17 không
	 *    phải một chỗ thiếu ảnh: nó nói *"chưa chọn cụm nên không có chỗ nào để gắn"*. Đếm cả
	 *    vào là dòng trên màn in "còn thiếu N+1 chỗ" trong khi chỉ có N ô ảnh trống — một con
	 *    số đúng một chút mà sai, tệ hơn là không có.
	 *
	 * ⚠️ Vẫn trả ĐỦ `warns` — W17 phải hiện ra ở khối cảnh báo, chỉ là không đếm vào con số.
	 */
	/* ═════════════════════════════════════════════════════════════════════ TẢI LÊN ══════
	 *
	 * 🔴 KHÔNG TIN CÁI NHÃN TRONG `dataUrl`. Chuỗi `data:image/jpeg;base64,` là do MÁY KHÁCH
	 *    viết ra — một tệp PHP mã hoá base64 kèm đúng cái nhãn ấy vẫn là tệp PHP. Nếu lấy đuôi
	 *    tệp từ nhãn thì nó nằm trong thư mục tải lên dưới tên `.php`, và mở bằng trình duyệt
	 *    là CHẠY. Nên ở đây: giải mã ra bytes, SOI BỐN BYTE ĐẦU, và đuôi tệp lấy từ thứ soi
	 *    được — nhãn chỉ dùng để cắt chuỗi, không dùng để quyết định gì.
	 *
	 * 🔴 TÊN TỆP NGẪU NHIÊN. Thư mục tải lên của WordPress mở công khai (giao diện gắn thẳng
	 *    vào thẻ <img>, không đi qua cổng có kiểm quyền được). Đặt tên đoán được — kiểu
	 *    `BC0001-may3.jpg` — là ai cũng dò ra ảnh chỉ số của mọi cơ sở. Tên ngẫu nhiên 32 ký
	 *    tự thì phải có đường dẫn mới xem được, đúng mức bản gốc làm với Drive.
	 * ══════════════════════════════════════════════════════════════════════════════════════ */

	/** 12 MB. Điện thoại ở mall chụp ảnh nặng thật, nhưng quá mức này là gửi nhầm video. */
	const TOI_DA_BYTE = 12582912;

	/** Chữ ký bốn byte đầu => đuôi tệp. Chỉ ba loại này, không mở thêm. */
	public static function soi_kieu( $bytes ) {
		if ( strlen( $bytes ) < 12 ) { return ''; }
		if ( "\xFF\xD8\xFF" === substr( $bytes, 0, 3 ) ) { return 'jpg'; }
		if ( "\x89PNG\r\n\x1a\n" === substr( $bytes, 0, 8 ) ) { return 'png'; }
		if ( 'RIFF' === substr( $bytes, 0, 4 ) && 'WEBP' === substr( $bytes, 8, 4 ) ) { return 'webp'; }
		return '';
	}

	/** Bóc phần base64 ra khỏi `data:…;base64,…`. Trả chuỗi rỗng nếu không đúng khuôn. */
	public static function boc_data_url( $s ) {
		$s = (string) $s;
		$i = strpos( $s, ',' );
		if ( false === $i || 0 !== strpos( $s, 'data:' ) ) { return ''; }
		if ( false === strpos( substr( $s, 0, $i ), ';base64' ) ) { return ''; }
		$raw = base64_decode( substr( $s, $i + 1 ), true );
		return false === $raw ? '' : $raw;
	}

	/**
	 * `jpUploadPhoto` — nhận một ảnh, trả về bản ghi ảnh y khuôn `VHJP_BaoCao::pub_anh()`.
	 *
	 * ⚠️ QUYỀN KIỂM THEO BÁO CÁO, không theo thứ máy khách gửi. Máy khách gửi `reportId`;
	 *    cơ sở thì đọc từ chính báo cáo ấy rồi mới so quyền — tin `locationId` do máy khách
	 *    gửi kèm là gắn ảnh vào cơ sở mình không được phép.
	 */
	public static function tai_len( $u, $d ) {
		$d = is_array( $d ) ? $d : array();
		$ma_bc = VHJP_Doc::str( isset( $d['reportId'] ) ? $d['reportId'] : '' );
		if ( '' === $ma_bc ) { throw new Exception( 'Thiếu mã báo cáo' ); }

		$head = VHJP_Nguon::tim_mot( 'JP_Reports', 'id', $ma_bc );
		if ( ! $head ) { throw new Exception( 'Không tìm thấy báo cáo' ); }
		if ( ! VHJP_Auth::la_kt( $u ) ) { VHJP_Auth::can_coso( $u, $head['locationId'] ); }

		$raw = self::boc_data_url( isset( $d['dataUrl'] ) ? $d['dataUrl'] : '' );
		if ( '' === $raw ) { throw new Exception( 'Ảnh gửi lên không đọc được' ); }
		if ( strlen( $raw ) > self::TOI_DA_BYTE ) {
			throw new Exception( 'Ảnh nặng quá ' . round( self::TOI_DA_BYTE / 1048576 )
				. ' MB — chụp lại hoặc để máy nén trước khi gửi' );
		}
		$duoi = self::soi_kieu( $raw );
		if ( '' === $duoi ) {
			throw new Exception( 'Tệp gửi lên không phải ảnh JPG/PNG/WEBP' );
		}

		$thu_muc = wp_upload_dir();
		if ( ! empty( $thu_muc['error'] ) ) { throw new Exception( 'Không mở được thư mục tải lên' ); }
		$con  = 'jp-anh/' . gmdate( 'Y/m' );
		$dich = trailingslashit( $thu_muc['basedir'] ) . $con;
		if ( ! wp_mkdir_p( $dich ) ) { throw new Exception( 'Không tạo được thư mục ảnh' ); }

		$ten = wp_generate_password( 32, false, false ) . '.' . $duoi;
		if ( false === file_put_contents( trailingslashit( $dich ) . $ten, $raw ) ) {
			throw new Exception( 'Không ghi được tệp ảnh' );
		}
		$url = trailingslashit( $thu_muc['baseurl'] ) . $con . '/' . $ten;

		$hang = array(
			'reportId'   => $ma_bc,
			'scope'      => VHJP_Doc::str( isset( $d['scope'] ) ? $d['scope'] : '' ),
			'refId'      => VHJP_Doc::str( isset( $d['refId'] ) ? $d['refId'] : '' ),
			'kind'       => VHJP_Doc::str( isset( $d['kind'] ) ? $d['kind'] : '' ),
			'fileId'     => '',
			'url'        => $url,
			'takenAt'    => VHJP_Doc::ngay_gio( isset( $d['takenAt'] ) ? $d['takenAt'] : '' ),
			'uploadedAt' => VHJP_Ma::hom_nay() . ' ' . gmdate( 'H:i:s' ),
			'bytes'      => strlen( $raw ),
		);
		/* ⚠️ `VHJP_Ma::them()` trả về CẢ HÀNG đã ghi (kèm mã nó vừa sinh), không trả về mã.
		   Gán thẳng vào `$hang['id']` là nhét một MẢNG vào ô id — PHP đổi mảng thành chuỗi
		   "Array" kèm một cảnh báo, và ảnh mang mã "Array" thì bấm xoá không trúng gì cả. */
		$da = VHJP_Ma::them( 'JP_Photos', 'ANH', $hang );
		if ( false === $da || ! is_array( $da ) || '' === VHJP_Doc::str( $da['id'] ) ) {
			/* Ghi sổ hỏng thì XOÁ luôn tệp vừa ghi — không thì thư mục đầy ảnh mồ côi mà
			   không bản ghi nào trỏ tới, và không ai biết để dọn. */
			@unlink( trailingslashit( $dich ) . $ten );
			throw new Exception( 'Không ghi được sổ ảnh' );
		}
		$hang['id'] = VHJP_Doc::str( $da['id'] );
		$ma = $hang['id'];
		VHJP_NhatKy::ghi( $u, 'UPLOAD_PHOTO', $ma_bc, $ma, $hang['scope'] . '/' . $hang['kind'] );
		return array( 'ok' => true, 'photo' => VHJP_BaoCao::pub_anh( $hang ) );
	}

	/**
	 * `jpDeletePhoto` — xoá một ảnh.
	 *
	 * 🔴 KIỂM QUYỀN DÙ CHỈ CÓ MỖI `photoId`. Mã ảnh chạy tuần tự nên đoán được; không kiểm là
	 *    ai đăng nhập cũng xoá sạch ảnh của mọi cơ sở, mà xoá ảnh chỉ số thì không dựng lại
	 *    được — máy đã quay tiếp rồi.
	 */
	public static function xoa( $u, $ma_anh ) {
		$ma_anh = VHJP_Doc::str( $ma_anh );
		if ( '' === $ma_anh ) { throw new Exception( 'Thiếu mã ảnh' ); }
		$p = VHJP_Nguon::tim_mot( 'JP_Photos', 'id', $ma_anh );
		if ( ! $p ) { throw new Exception( 'Không tìm thấy ảnh' ); }

		$head = VHJP_Nguon::tim_mot( 'JP_Reports', 'id', $p['reportId'] );
		if ( $head && ! VHJP_Auth::la_kt( $u ) ) { VHJP_Auth::can_coso( $u, $head['locationId'] ); }

		self::xoa_tep( VHJP_Doc::str( $p['url'] ) );
		VHJP_Nguon::xoa( 'JP_Photos', $ma_anh );
		VHJP_NhatKy::ghi( $u, 'DELETE_PHOTO', VHJP_Doc::str( $p['reportId'] ), $ma_anh, '' );
		return array( 'ok' => true );
	}

	/**
	 * Xoá tệp trên đĩa theo `url`.
	 *
	 * 🔴 CHỈ XOÁ TRONG THƯ MỤC ẢNH CỦA MÌNH. `url` nằm trong CSDL nên coi như có thể bị sửa;
	 *    dựng đường dẫn thẳng từ nó rồi `unlink` là một đường xoá tệp bất kỳ trên máy chủ.
	 *    Ở đây: bắt buộc phải bắt đầu bằng đúng `baseurl` + `jp-anh/`, và tên tệp phải sạch.
	 */
	public static function xoa_tep( $url ) {
		$url = (string) $url;
		if ( '' === $url ) { return false; }
		$tm   = wp_upload_dir();
		$dau  = trailingslashit( $tm['baseurl'] ) . 'jp-anh/';
		if ( 0 !== strpos( $url, $dau ) ) { return false; }
		$con = substr( $url, strlen( $dau ) );
		if ( ! preg_match( '#^\d{4}/\d{2}/[A-Za-z0-9]{8,64}\.(jpg|png|webp)$#', $con ) ) { return false; }
		$tep = trailingslashit( $tm['basedir'] ) . 'jp-anh/' . $con;
		if ( file_exists( $tep ) ) { return @unlink( $tep ); }
		return false;
	}

	/**
	 * `jpAnhXem` — đọc bytes của một ảnh, trả về `dataUrl`.
	 *
	 * Bản gốc dùng đường này khi Drive tải không nổi. Ở bản này ảnh mới nằm ngay trên host nên
	 * hiếm khi cần, nhưng giao diện kế toán vẫn gọi — và nó phải KIỂM QUYỀN y như mọi đường
	 * khác, không thì đây là một cửa đọc tệp không khoá.
	 */
	public static function xem( $u, $can ) {
		$can = VHJP_Doc::str( $can );
		if ( '' === $can ) { throw new Exception( 'Thiếu mã ảnh' ); }
		/* Giao diện gửi `fileId` (ảnh Drive cũ) hoặc mã ảnh — nhận cả hai. */
		$p = VHJP_Nguon::tim_mot( 'JP_Photos', 'fileId', $can );
		if ( ! $p ) { $p = VHJP_Nguon::tim_mot( 'JP_Photos', 'id', $can ); }
		if ( ! $p ) { throw new Exception( 'Không tìm thấy ảnh' ); }

		$head = VHJP_Nguon::tim_mot( 'JP_Reports', 'id', $p['reportId'] );
		if ( $head && ! VHJP_Auth::la_kt( $u ) ) { VHJP_Auth::can_coso( $u, $head['locationId'] ); }

		$url = VHJP_Doc::str( $p['url'] );
		$tm  = wp_upload_dir();
		$dau = trailingslashit( $tm['baseurl'] ) . 'jp-anh/';
		if ( 0 !== strpos( $url, $dau ) ) {
			/* Ảnh cũ trên Drive: máy chủ này không đọc hộ được, nói thẳng thay vì trả rỗng. */
			throw new Exception( 'Ảnh này nằm trên Google Drive, mở bằng đường dẫn Drive' );
		}
		$tep = trailingslashit( $tm['basedir'] ) . 'jp-anh/' . substr( $url, strlen( $dau ) );
		if ( ! file_exists( $tep ) ) { throw new Exception( 'Tệp ảnh không còn trên máy chủ' ); }
		$raw  = (string) file_get_contents( $tep );
		$duoi = self::soi_kieu( $raw );
		$mime = array( 'jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp' );
		return array( 'ok' => true, 'dataUrl' => 'data:'
			. ( isset( $mime[ $duoi ] ) ? $mime[ $duoi ] : 'application/octet-stream' )
			. ';base64,' . base64_encode( $raw ) );
	}

	/** Một lượt xem tối đa ngần này ảnh — trình duyệt không vẽ nổi vài nghìn thẻ <img>. */
	const TRAN_ANH = 600;

	/**
	 * `jpAnhTheoCoSo` — ảnh của một cơ sở trong khoảng ngày, gom theo kỳ báo cáo.
	 *
	 * 🔴 TRẢ CẢ KỲ KHÔNG CÓ ẢNH. Đó là thứ quan trọng nhất màn này nói được — một màn chỉ in
	 *    ảnh CÓ thì không bao giờ cho biết kỳ nào cơ sở không gửi gì.
	 * 🔴 CẮT BỚT THÌ NÓI RA (`boQua`). Im lặng cắt là màn trông như "cơ sở chỉ gửi có thế".
	 */
	public static function theo_coso( $u, $d ) {
		$d   = is_array( $d ) ? $d : array();
		$loc = VHJP_Doc::str( isset( $d['locationId'] ) ? $d['locationId'] : '' );
		$kind = VHJP_Doc::str( isset( $d['kind'] ) ? $d['kind'] : '' );
		$tu  = VHJP_Doc::ngay( isset( $d['tuNgay'] ) ? $d['tuNgay'] : '' );
		$den = VHJP_Doc::ngay( isset( $d['denNgay'] ) ? $d['denNgay'] : '' );
		if ( '' !== $loc && ! VHJP_Auth::la_kt( $u ) ) { VHJP_Auth::can_coso( $u, $loc ); }

		$bc = array();
		foreach ( VHJP_Nguon::doc( 'JP_Reports' ) as $h ) {
			if ( '' !== $loc && VHJP_Doc::str( $h['locationId'] ) !== $loc ) { continue; }
			if ( ! VHJP_Auth::la_kt( $u ) && ! VHJP_Auth::xem_duoc_coso( $u, $h['locationId'] ) ) { continue; }
			$t = VHJP_Doc::ngay( $h['fromDate'] );
			if ( '' !== $tu && '' !== $t && $t < $tu ) { continue; }
			if ( '' !== $den && '' !== $t && $t > $den ) { continue; }
			$bc[ (string) $h['id'] ] = $h;
		}

		$theo_bc = array();
		foreach ( VHJP_Nguon::doc( 'JP_Photos' ) as $p ) {
			$r = (string) $p['reportId'];
			if ( ! isset( $bc[ $r ] ) ) { continue; }
			if ( '' !== $kind && VHJP_Doc::str( $p['kind'] ) !== $kind ) { continue; }
			$theo_bc[ $r ][] = $p;
		}

		$nhom = array();
		$khong = array();
		$tong = 0;
		$bo_qua = 0;
		$da_lay = 0;
		foreach ( $bc as $ma => $h ) {
			$ds = isset( $theo_bc[ $ma ] ) ? $theo_bc[ $ma ] : array();
			$tong += count( $ds );
			if ( ! $ds ) {
				$khong[] = array(
					'reportId' => $ma, 'fromDate' => VHJP_Doc::ngay( $h['fromDate'] ),
					'toDate' => VHJP_Doc::ngay( $h['toDate'] ),
					'locationName' => VHJP_Doc::str( $h['locationName'] ),
					'userName' => VHJP_Doc::str( $h['userName'] ),
					'status' => VHJP_Doc::str( $h['status'] ),
				);
				continue;
			}
			$anh = array();
			foreach ( $ds as $p ) {
				/* Đếm chạy, không đếm lại cả mảng mỗi vòng: một cơ sở có thể có vài nghìn ảnh,
				   và đếm lại mỗi tấm là phép bình phương ngay trên đường người dùng đứng chờ. */
				if ( $da_lay >= self::TRAN_ANH ) { $bo_qua++; continue; }
				$anh[] = VHJP_BaoCao::pub_anh( $p );
				$da_lay++;
			}
			if ( ! $anh ) { continue; }
			$nhom[] = array(
				'reportId' => $ma, 'fromDate' => VHJP_Doc::ngay( $h['fromDate'] ),
				'toDate' => VHJP_Doc::ngay( $h['toDate'] ),
				'locationName' => VHJP_Doc::str( $h['locationName'] ),
				'userName' => VHJP_Doc::str( $h['userName'] ),
				'status' => VHJP_Doc::str( $h['status'] ),
				'anh' => $anh,
			);
		}

		$ten = '';
		if ( '' !== $loc ) {
			$l = VHJP_Nguon::tim_mot( 'JP_Locations', 'id', $loc );
			$ten = $l ? VHJP_Doc::str( $l['name'] ) : $loc;
		}
		return array(
			'ok' => true, 'locationId' => $loc, 'tenCoSo' => $ten, 'kind' => $kind,
			'soAnh' => $tong, 'soBaoCao' => count( $bc ), 'nhom' => $nhom,
			'khongCoAnh' => array_values( $khong ), 'boQua' => $bo_qua, 'tran' => self::TRAN_ANH,
		);
	}

	public static function tien_do( $u, $ma_bc ) {
		$head = VHJP_Nguon::tim_mot( 'JP_Reports', 'id', $ma_bc );
		if ( ! $head ) { throw new Exception( 'Không tìm thấy báo cáo' ); }
		if ( ! VHJP_Auth::la_kt( $u ) ) { VHJP_Auth::can_coso( $u, $head['locationId'] ); }

		$d = self::canh_bao( $ma_bc, $head['locationId'] );

		$can = 0;
		foreach ( $d['rows'] as $r ) {
			$k = VHJP_Doc::str( $r['rowKind'] );
			/* Bảng tồn kho và kho ngoài không có đồng hồ ⇒ không cần ảnh chỉ số. */
			if ( VHJP_Tinh::DONG_STOCK === $k || VHJP_Tinh::DONG_NGOAI === $k ) { continue; }
			$m = isset( $d['banDoMay'][ (string) $r['machineId'] ] )
				? $d['banDoMay'][ (string) $r['machineId'] ] : null;
			$can += $m ? VHJP_Doc::so_anh( $m['photoCount'] ) : 1;
		}
		foreach ( $d['zones'] as $z ) {
			$c = isset( $d['banDoCum'][ (string) $z['clusterId'] ] )
				? $d['banDoCum'][ (string) $z['clusterId'] ] : null;
			$can += $c ? VHJP_Doc::so_anh( $c['photoCount'],
				'Y' === VHJP_Doc::str( $c['hasQR'] ) ? 1 : 0 ) : 0;
		}

		$ma_w7 = VHJP_Tinh::warn_def()['MISSING_PHOTO']['code'];
		$thieu = 0;
		foreach ( $d['warns'] as $w ) {
			if ( VHJP_Doc::str( $w['code'] ) === $ma_w7 ) { $thieu++; }
		}

		return array( 'ok' => true, 'need' => $can, 'got' => count( $d['photos'] ),
			'missing' => $thieu, 'warns' => $d['warns'] );
	}
}
