<?php
/**
 * NHẬP MÔN NHÂN VIÊN MỚI — bảng "Bắt đầu cùng K&H" + NỘI QUY CÔNG TY + hướng dẫn nhanh.
 *
 * Anh Thắng 26/09/2026: *"Với nhân viên mới, tài khoản lần đầu kích hoạt sẽ hiện phía dưới về
 * Nội Quy Công Ty, và 1 số hướng dẫn khác"* → xem mẫu (artifact "Nhập môn nhân viên mới") →
 * *"làm theo mẫu này luôn đi em"*.
 *
 * =================================================================================================
 * BA CUỐN SỔ (bảng `cai_dat`), KHÔNG TỰ XOÁ
 * =================================================================================================
 *   · `O_NQ`   — NỘI QUY: phiên bản, ngày áp dụng, các mục (tiêu đề + dòng). Công ty soạn ở màn
 *                Tiếp nhận nhân sự. Đổi nội dung là lên phiên bản mới → mọi người đồng ý lại.
 *   · `O_DY`   — AI ĐỒNG Ý NỘI QUY BẢN NÀO, LÚC NÀO (kèm IP, thiết bị) — như ký nhận nội quy.
 *   · `O_TICH` — việc nhân viên tự tích (cài app) và cờ "ẩn bảng".
 *
 * 🔴 SÁU VIỆC, PHẦN LỚN MÁY TỰ BIẾT: kích hoạt (đã đăng nhập) · nội quy (đã đồng ý bản hiện
 *    hành) · đổi PIN (khác PIN hệ thống cấp lúc tiếp nhận) · cài app (tự tích) · chấm lượt đầu
 *    (có lượt chấm vào) · ký hợp đồng (đã ký điện tử bộ hồ sơ). Việc không áp dụng (người không
 *    đi qua Tiếp nhận thì không có PIN cấp sẵn, không có hợp đồng điện tử) thì không bày ra.
 * 🔴 AI THẤY BẢNG: người vào làm chưa quá `NGAY_MOI` ngày và chưa ẩn bảng. Ngoài ra, NỘI QUY
 *    ĐỔI BẢN MỚI thì MỌI người (kể cả người cũ) thấy một dòng nhắc đọc & đồng ý lại.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_NhapMon {

	const O_NQ   = 'NOI_QUY';
	const O_DY   = 'NOI_QUY_DONG_Y';
	const O_TICH = 'NHAP_MON_TICH';

	/** Vào làm trong bấy nhiêu ngày thì còn là "người mới". */
	const NGAY_MOI = 30;

	/** Soạn nội quy = việc nhân sự → bậc Kế toán (cùng cửa màn Tiếp nhận). */
	const QUYEN = 'ho_so';

	/** Bản nháp minh hoạ — công ty thay bằng nội quy thật. */
	public static function mac_dinh() {
		return array(
			'ban' => '1.0', 'apDung' => '', 'luc' => '', 'boi' => '',
			'muc' => array(
				array( 'tieu' => 'Giờ giấc & chấm công', 'dong' => array(
					'Có mặt trước giờ ca 5 phút, chấm vào khi bắt đầu, chấm ra khi kết thúc — tại đúng cơ sở.',
					'Không chấm hộ, không nhờ người khác chấm — vi phạm xử lý kỷ luật.',
					'Đi trễ, về sớm, quên chấm: gửi đơn trên app trong ngày.' ) ),
				array( 'tieu' => 'Đồng phục & tác phong', 'dong' => array(
					'Mặc đồng phục, đeo bảng tên trong suốt ca.',
					'Niềm nở với khách; không dùng điện thoại việc riêng khi đang phục vụ.' ) ),
				array( 'tieu' => 'An toàn khu vui chơi', 'dong' => array(
					'Kiểm tra trò chơi đầu ca theo danh sách; thấy hỏng báo quản lý ngay, dừng vận hành.',
					'Luôn để mắt tới trẻ em; biết vị trí bình chữa cháy và lối thoát hiểm.' ) ),
				array( 'tieu' => 'Tài sản & tiền quỹ', 'dong' => array(
					'Kiểm đếm quỹ đầu và cuối ca có chứng kiến; chênh lệch ghi biên bản.',
					'Không mang tài sản công ty ra ngoài khi chưa được phép.' ) ),
				array( 'tieu' => 'Bảo mật thông tin', 'dong' => array(
					'Giữ kín PIN, không chia sẻ tài khoản.',
					'Không đưa thông tin khách hàng, doanh thu ra ngoài.' ) ),
				array( 'tieu' => 'Nghỉ phép & đơn từ', 'dong' => array(
					'Xin nghỉ trước ít nhất 1 ngày qua app (trừ ốm đau đột xuất).',
					'Đổi ca: gửi yêu cầu đổi lịch, chờ quản lý duyệt.' ) ),
				array( 'tieu' => 'Lương, thưởng & kỷ luật', 'dong' => array(
					'Lương trả hằng tháng; phiếu lương gửi qua app và email.',
					'Kỷ luật theo mức: nhắc nhở → khiển trách → kéo dài thời hạn nâng lương → sa thải (Điều 124 BLLĐ 2019).' ) ),
			),
		);
	}

	public static function noi_quy() {
		$d = VHCC_Luong::cai_dat( self::O_NQ, null );
		return is_array( $d ) && ! empty( $d['muc'] ) ? array_merge( self::mac_dinh(), $d ) : self::mac_dinh();
	}

	/** Nội quy ra chữ để sửa: "## Tiêu đề" rồi mỗi dòng "- nội dung". */
	public static function ra_chu( $nq = null ) {
		$nq = null === $nq ? self::noi_quy() : $nq;
		$o = array();
		foreach ( (array) $nq['muc'] as $m ) {
			$o[] = '## ' . $m['tieu'];
			foreach ( (array) $m['dong'] as $d ) { $o[] = '- ' . $d; }
			$o[] = '';
		}
		return trim( implode( "\n", $o ) );
	}

	/** Đọc chữ ngược lại thành các mục. */
	public static function doc_chu( $chu ) {
		$muc = array(); $cur = null;
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $chu ) as $l ) {
			$l = trim( $l );
			if ( '' === $l ) { continue; }
			if ( 0 === strpos( $l, '##' ) ) {
				if ( $cur ) { $muc[] = $cur; }
				$cur = array( 'tieu' => trim( ltrim( $l, '#' ) ), 'dong' => array() );
				continue;
			}
			if ( ! $cur ) { $cur = array( 'tieu' => 'Quy định chung', 'dong' => array() ); }
			$cur['dong'][] = trim( preg_replace( '/^[-*•]\s*/u', '', $l ) );
		}
		if ( $cur ) { $muc[] = $cur; }
		return array_values( array_filter( $muc, function ( $m ) { return '' !== $m['tieu'] && $m['dong']; } ) );
	}

	/**
	 * Lưu nội quy. Nội dung đổi mà đã có người đồng ý bản đang có thì LÊN BẢN (1.0 → 1.1) — mọi
	 * người phải đồng ý lại — trừ khi `giu_ban` (sửa chính tả, không đổi ý).
	 */
	public static function dat_noi_quy( $u, $chu, $ap_dung = '', $giu_ban = false ) {
		if ( ! VHCC_Vai::duoc( $u, self::QUYEN ) ) {
			return array( 'ok' => false, 'error' => VHCC_Vai::loi( $u, self::QUYEN, 'Soạn nội quy' ) );
		}
		$muc = self::doc_chu( $chu );
		if ( ! $muc ) { return array( 'ok' => false, 'error' => 'Nội quy trống — mỗi mục bắt đầu bằng "## Tiêu đề", mỗi dòng bắt đầu bằng "- ".' ); }
		$cu = self::noi_quy();
		$doi = wp_json_encode( $muc ) !== wp_json_encode( $cu['muc'] );
		$ban = (string) $cu['ban'];
		/* Chưa ai đồng ý bản đang có thì không cần lên bản — sửa thẳng (VD thay bản nháp minh hoạ). */
		$co_nguoi = false;
		foreach ( self::so( self::O_DY ) as $ds ) {
			foreach ( (array) $ds as $x ) { if ( (string) $x['ban'] === $ban ) { $co_nguoi = true; break 2; } }
		}
		if ( $doi && ! $giu_ban && $co_nguoi ) {
			$p = explode( '.', $ban );
			$ban = (int) $p[0] . '.' . ( ( isset( $p[1] ) ? (int) $p[1] : 0 ) + 1 );
		}
		$nq = array( 'ban' => $ban, 'apDung' => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $ap_dung ) ? (string) $ap_dung : (string) $cu['apDung'],
			'muc' => $muc, 'luc' => current_time( 'mysql' ), 'boi' => isset( $u['name'] ) ? (string) $u['name'] : '' );
		VHCC_Luong::dat_cai_dat( self::O_NQ, $nq, $u );
		return array( 'ok' => true, 'ban' => $ban, 'lenBan' => $ban !== (string) $cu['ban'] );
	}

	/* ============================================================== đồng ý */

	private static function so( $o ) {
		$d = VHCC_Luong::cai_dat( $o, null );
		return is_array( $d ) ? $d : array();
	}

	/** Lần đồng ý gần nhất của một người (hoặc null). */
	public static function dong_y_cua( $ma ) {
		$so = self::so( self::O_DY );
		$k = strtolower( trim( (string) $ma ) );
		if ( empty( $so[ $k ] ) ) { return null; }
		$ds = (array) $so[ $k ];
		return end( $ds );
	}

	public static function da_dong_y_ban( $ma, $ban ) {
		$so = self::so( self::O_DY );
		$k = strtolower( trim( (string) $ma ) );
		foreach ( (array) ( isset( $so[ $k ] ) ? $so[ $k ] : array() ) as $x ) {
			if ( (string) $x['ban'] === (string) $ban ) { return $x; }
		}
		return null;
	}

	/** Nhân viên bấm "Đồng ý nội quy". `$ban` phải đúng bản đang hiệu lực (không đồng ý bản cũ). */
	public static function dong_y( $u, $ban, $ip = '', $ua = '' ) {
		$ma = isset( $u['ma_nv'] ) ? trim( (string) $u['ma_nv'] ) : '';
		if ( '' === $ma ) { return array( 'ok' => false, 'error' => 'Tài khoản chưa gắn mã nhân viên.' ); }
		$nq = self::noi_quy();
		if ( (string) $ban !== (string) $nq['ban'] ) {
			return array( 'ok' => false, 'error' => 'Nội quy vừa được cập nhật lên bản ' . $nq['ban'] . ' — tải lại để đọc bản mới.' );
		}
		if ( $x = self::da_dong_y_ban( $ma, $nq['ban'] ) ) { return array( 'ok' => true, 'luc' => $x['luc'], 'ban' => $nq['ban'] ); }
		$so = self::so( self::O_DY );
		$k = strtolower( $ma );
		$so[ $k ][] = array( 'ban' => (string) $nq['ban'], 'luc' => current_time( 'mysql' ),
			'ten' => isset( $u['name'] ) ? (string) $u['name'] : '', 'ip' => substr( (string) $ip, 0, 64 ), 'ua' => substr( (string) $ua, 0, 200 ) );
		VHCC_Luong::dat_cai_dat( self::O_DY, $so, array( 'name' => 'NV đồng ý nội quy: ' . $ma ) );
		return array( 'ok' => true, 'luc' => end( $so[ $k ] )['luc'], 'ban' => $nq['ban'] );
	}

	/** Nhân viên tự tích (chỉ "cai" = đã cài app) hoặc ẩn bảng ("an"). */
	public static function tich( $u, $k, $co = true ) {
		$ma = isset( $u['ma_nv'] ) ? strtolower( trim( (string) $u['ma_nv'] ) ) : '';
		if ( '' === $ma || ! in_array( $k, array( 'cai', 'an' ), true ) ) { return array( 'ok' => false, 'error' => 'Không tích được việc này.' ); }
		$so = self::so( self::O_TICH );
		if ( $co ) { $so[ $ma ][ $k ] = current_time( 'mysql' ); } else { unset( $so[ $ma ][ $k ] ); }
		VHCC_Luong::dat_cai_dat( self::O_TICH, $so, array( 'name' => 'NV nhập môn: ' . $ma ) );
		return array( 'ok' => true );
	}

	/* ============================================================== trạng thái cho trạm */

	/**
	 * Mọi thứ trạm cần để vẽ bảng nhập môn + màn nội quy.
	 * @return array { hien, moi, xong, tong, viec:[{k,ten,mo,xong,tuTich}], noiQuy:{ban,apDung,muc,dongY}, nhacLai }
	 */
	public static function trang_thai( $u ) {
		global $wpdb;
		$ma = isset( $u['ma_nv'] ) ? trim( (string) $u['ma_nv'] ) : '';
		$nq = self::noi_quy();
		$dy = '' !== $ma ? self::da_dong_y_ban( $ma, $nq['ban'] ) : null;
		$ra = array( 'hien' => false, 'moi' => false, 'xong' => 0, 'tong' => 0, 'viec' => array(),
			'noiQuy' => array( 'ban' => $nq['ban'], 'apDung' => $nq['apDung'], 'muc' => $nq['muc'], 'dongY' => $dy ? $dy['luc'] : '',
				'cty' => class_exists( 'VHCC_Pdf' ) ? VHCC_Pdf::ten_cong_ty() : '' ),
			'nhacLai' => false );
		if ( '' === $ma ) { return $ra; }
		$hs = VHCC_NhanSu::ho_so( $ma );
		$tn = class_exists( 'VHCC_TiepNhan' ) ? VHCC_TiepNhan::ban_ghi( $ma ) : null;
		$vao = $hs && ! empty( $hs['ngay_vao_lam'] ) ? (string) $hs['ngay_vao_lam'] : ( $tn ? substr( (string) $tn['luc'], 0, 10 ) : '' );
		$hn = (string) current_time( 'Y-m-d' );
		$moi = '' !== $vao && ( strtotime( $hn ) - strtotime( $vao ) ) <= self::NGAY_MOI * 86400;
		$tich = self::so( self::O_TICH );
		$tich = isset( $tich[ strtolower( $ma ) ] ) ? (array) $tich[ strtolower( $ma ) ] : array();

		$viec = array();
		$viec[] = array( 'k' => 'kich', 'ten' => 'Kích hoạt tài khoản', 'mo' => 'Đăng nhập lần đầu bằng PIN', 'xong' => true );
		$viec[] = array( 'k' => 'nq', 'ten' => 'Đọc & cam kết Nội quy công ty', 'mo' => count( (array) $nq['muc'] ) . ' mục · khoảng 5 phút', 'xong' => (bool) $dy );
		if ( $tn && method_exists( 'VHCC_TiepNhan', 'pin_da_doi' ) ) {
			$viec[] = array( 'k' => 'pin', 'ten' => 'Đổi PIN của riêng bạn', 'mo' => 'Tab Tôi → Đổi mật khẩu', 'xong' => VHCC_TiepNhan::pin_da_doi( $ma ) );
		}
		$viec[] = array( 'k' => 'cai', 'ten' => 'Cài app lên màn hình chính', 'mo' => 'Chia sẻ → Thêm vào màn hình chính', 'xong' => ! empty( $tich['cai'] ), 'tuTich' => true );
		$co_cham = (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM ' . VHCC_DB::t( 'cham_cong' )
			. ' WHERE ma_nv=%s AND gio_vao_giay IS NOT NULL LIMIT 1', $ma ) );
		$viec[] = array( 'k' => 'cham', 'ten' => 'Chấm vào lượt đầu tiên', 'mo' => 'Tự tích khi bạn bấm Chấm vào', 'xong' => $co_cham );
		if ( $tn ) {
			$viec[] = array( 'k' => 'hd', 'ten' => 'Ký hợp đồng lao động', 'mo' => 'Mở bộ hồ sơ nhận việc → Ký điện tử', 'xong' => ! empty( $tn['nvKy'] ),
				'link' => VHCC_TiepNhan::link_bo( $ma ) );
		}
		$xong = 0;
		foreach ( $viec as $v ) { if ( $v['xong'] ) { $xong++; } }
		$ra['viec'] = $viec; $ra['xong'] = $xong; $ra['tong'] = count( $viec ); $ra['moi'] = $moi;
		$ra['hien'] = $moi && empty( $tich['an'] );
		/* Không thấy bảng mà chưa đồng ý bản hiện hành → một dòng nhắc. Chỉ nhắc khi nội quy đã được
		   công ty lưu thật (bản nháp minh hoạ không làm phiền người cũ) hoặc người ấy từng đồng ý bản cũ. */
		$ra['nhacLai'] = ! $ra['hien'] && ! $dy && ( '' !== (string) $nq['luc'] || null !== self::dong_y_cua( $ma ) );
		return $ra;
	}

	/** Danh sách đồng ý cho màn quản trị (mới nhất trước). */
	public static function ds_dong_y( $so_luong = 50 ) {
		$ra = array();
		foreach ( self::so( self::O_DY ) as $ma => $ds ) {
			foreach ( (array) $ds as $x ) { $x['ma'] = strtoupper( $ma ); $ra[] = $x; }
		}
		usort( $ra, function ( $a, $b ) { return strcmp( (string) $b['luc'], (string) $a['luc'] ); } );
		return array_slice( $ra, 0, $so_luong );
	}
}
