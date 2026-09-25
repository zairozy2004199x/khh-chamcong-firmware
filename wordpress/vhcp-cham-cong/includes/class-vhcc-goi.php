<?php
/**
 * GỌI THOẠI TRONG APP — giữa hai người cùng một cơ sở.
 *
 * =================================================================================================
 * Anh Thắng 20/09/2026: *"Gọi trong app đi em — tự dựng"*.
 * =================================================================================================
 *
 * 🔴 WORDPRESS KHÔNG TRUYỀN TIẾNG NÓI. NÓ CHỈ LÀM NGƯỜI MAI MỐI.
 * Âm thanh đi THẲNG giữa hai điện thoại bằng WebRTC. Máy chủ này chỉ chuyển giúp mấy mẩu giấy
 * để hai bên tìm được nhau: một bản mô tả (SDP) và một mớ đường đi khả dĩ (ICE). Xong việc là
 * nó đứng ngoài — không nghe, không ghi âm, không tốn băng thông theo phút gọi.
 *
 * Đó cũng là lý do làm được bằng PHP trên hosting chia sẻ: mai mối là vài chục lượt HTTP cho cả
 * cuộc gọi, không phải một kết nối mở suốt.
 *
 * =================================================================================================
 * 🔴 NHƯNG PHẢI CÓ MÁY TURN, VÀ ĐÂY LÀ LÝ DO
 * =================================================================================================
 * Hai điện thoại 4G ở Việt Nam gần như luôn nằm sau NAT của nhà mạng (CGNAT): cả hai đều không có
 * địa chỉ để bên kia gọi tới. STUN chỉ giúp mỗi máy biết "địa chỉ ngoài của tôi là gì" — đủ cho
 * mạng nhà, KHÔNG đủ cho CGNAT. Lúc ấy phải có một máy đứng giữa chuyển gói tin: đó là TURN.
 *
 * Không có TURN thì cuộc gọi vẫn "bấm được", vẫn đổ chuông, rồi im lặng và tự tắt — hỏng đúng
 * kiểu tệ nhất: trông như máy yếu chứ không ai nghĩ là thiếu hạ tầng. Nên `ice()` NÓI THẲNG khi
 * chưa khai TURN, và màn gọi CHỐI mở cuộc gọi thay vì thử rồi thất bại.
 *
 * Cách dựng: xem `docs/dung-turn.md`.
 *
 * =================================================================================================
 * 🔴 BÍ MẬT TURN KHÔNG BAO GIỜ XUỐNG MÁY NGƯỜI DÙNG
 * =================================================================================================
 * coturn có kiểu xác thực `use-auth-secret`: máy chủ và coturn cùng biết MỘT chuỗi bí mật, và máy
 * chủ phát cho mỗi người một cặp tên/mật khẩu TẠM, tự hết hạn. Tên là `<hết hạn>:<mã NV>`, mật
 * khẩu là HMAC-SHA1 của tên bằng chuỗi bí mật ấy.
 *
 * Nhờ vậy: chuỗi bí mật nằm trong `wp-config.php`, không có trong kho mã (kho này CÔNG KHAI), và
 * một cặp tên/mật khẩu lỡ lọt ra ngoài thì cũng chết sau `VE_SONG` giây.
 *
 * ⚠️ CHỖ NÀY KHÔNG ĐƯỢC "TIỆN TAY" GỬI THẲNG CHUỖI BÍ MẬT XUỐNG TRÌNH DUYỆT cho nhanh. Gửi một
 *    lần là ai mở màn gọi cũng có nó, và nó dùng được mãi — tức là cả thế giới mượn được máy
 *    TURN của mình làm nơi chuyển tiếp.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_Goi {

	/** Vé TURN sống bao lâu (giây). Đủ cho một cuộc gọi dài, đủ ngắn để lọt ra cũng vô dụng. */
	const VE_SONG = 3600;

	/**
	 * Chuông đổ tối đa bấy nhiêu giây rồi tính là NHỠ.
	 *
	 * 🔴 PHẢI CÓ HẠN. Không có thì một máy tắt nguồn lúc 9 giờ tối, sáng mai mở lên là đổ chuông
	 *    một cuộc gọi của đêm qua — và người nhận bấm nghe vào một cuộc không còn ai ở đầu kia.
	 */
	const CHUONG_GIAY = 45;

	/** Mẩu tin mai mối dài nhất. SDP thật cỡ 4–8 KB; 64 KB là rộng rãi mà vẫn chặn được rác. */
	const TIN_TOI_DA = 65536;

	/** Dọn tin mai mối của cuộc đã xong sau bấy nhiêu phút. */
	const DON_PHUT = 30;

	/* ========================================================================= cấu hình */

	/** Địa chỉ máy TURN, ví dụ `turn.khmatrix.com:3478`. Rỗng = chưa dựng. */
	public static function turn_may() {
		if ( defined( 'VHCC_TURN_MAY' ) && '' !== (string) VHCC_TURN_MAY ) {
			return (string) VHCC_TURN_MAY;
		}
		return (string) get_option( 'vhcc_turn_may', '' );
	}

	/**
	 * Chuỗi bí mật dùng chung với coturn.
	 *
	 * ⚠️ HÀM NÀY KHÔNG ĐƯỢC GỌI TỪ BẤT KỲ CHỖ NÀO TRẢ DỮ LIỆU RA NGOÀI. Chỉ `ve()` dùng nó, và
	 *    `ve()` chỉ trả về kết quả đã băm. Có phép thử canh chuyện này.
	 */
	private static function turn_bi_mat() {
		if ( defined( 'VHCC_TURN_BI_MAT' ) && '' !== (string) VHCC_TURN_BI_MAT ) {
			return (string) VHCC_TURN_BI_MAT;
		}
		return (string) get_option( 'vhcc_turn_bi_mat', '' );
	}

	/** Đã dựng TURN chưa. Chưa thì không cho gọi — xem khối chú thích đầu tệp. */
	public static function san_sang() {
		return '' !== self::turn_may() && '' !== self::turn_bi_mat();
	}

	/**
	 * VÉ TURN TẠM cho một người. Trả về danh sách máy chủ cho `RTCPeerConnection`.
	 *
	 * 🔴 STUN ĐỂ TRƯỚC, TURN ĐỂ SAU, và giữ CẢ HAI. Bỏ STUN đi thì mọi cuộc gọi — kể cả hai máy
	 *    cùng một mạng wifi cửa hàng — đều chạy vòng qua TURN: tốn băng thông máy chủ và thêm
	 *    độ trễ, cho một việc mà hai máy tự làm được. Bỏ TURN thì xem khối chú thích đầu tệp.
	 */
	public static function ve( $u ) {
		$ma = isset( $u['ma_nv'] ) ? trim( (string) $u['ma_nv'] ) : '';
		if ( '' === $ma ) { return array( 'ok' => false, 'error' => 'Thiếu mã nhân viên.' ); }
		if ( ! self::san_sang() ) {
			return array( 'ok' => false, 'co' => false,
				'error' => 'Chưa dựng máy TURN nên chưa gọi trong app được. '
					. 'Quản trị khai VHCC_TURN_MAY và VHCC_TURN_BI_MAT trong wp-config.php '
					. '— xem docs/dung-turn.md.' );
		}
		$may = self::turn_may();
		$het = time() + self::VE_SONG;
		$ten = $het . ':' . $ma;
		$mk  = base64_encode( hash_hmac( 'sha1', $ten, self::turn_bi_mat(), true ) );

		return array(
			'ok' => true,
			'co' => true,
			'may' => array(
				array( 'urls' => array( 'stun:' . $may ) ),
				array(
					'urls' => array( 'turn:' . $may . '?transport=udp',
						'turn:' . $may . '?transport=tcp' ),
					'username'   => $ten,
					'credential' => $mk,
				),
			),
			'hetLuc' => $het,
		);
	}

	/* ============================================================================ cuộc gọi */

	/**
	 * GỌI MỘT NGƯỜI. Trả về cuộc gọi vừa tạo.
	 *
	 * 🔴 DÙNG LẠI PHÉP GÁC CỦA CHAT RIÊNG. `VHCC_Chat::phong_rieng()` + `duoc_vao()` đã trả lời
	 *    đúng câu cần hỏi ở đây: "hai người này có cùng một cơ sở không". Viết lại một phép gác
	 *    riêng cho cuộc gọi là dựng bản thứ hai của cùng một luật, và hai bản ấy sẽ lệch.
	 */
	public static function goi( $u, $ma_kia, $coso = '' ) {
		global $wpdb;
		if ( ! self::san_sang() ) {
			return array( 'ok' => false, 'error' => 'Chưa dựng máy TURN nên chưa gọi được. '
				. 'Xem docs/dung-turn.md.' );
		}
		$ma  = trim( (string) $u['ma_nv'] );
		$kia = trim( (string) $ma_kia );
		$cs  = ( '' !== $coso ) ? $coso : VHCC_NhanSu::chuan_coso( (string) $u['coso'] );

		$phong = VHCC_Chat::phong_rieng( $cs, $ma, $kia );
		if ( '' === $phong || ! VHCC_Chat::duoc_vao( $u, $phong ) ) {
			return array( 'ok' => false,
				'error' => 'Không gọi được người này. Hai người phải cùng một cơ sở.' );
		}

		self::het_han();

		/* 🔴 MỘT NGƯỜI MỘT CUỘC. Hai người cùng gọi một người thứ ba thì máy kia đổ hai chuông,
		   nghe một cuộc, và cuộc còn lại treo mãi ở trạng thái "đang đổ chuông" — đầu bên kia
		   ngồi nghe tiếng chuông giả cho tới khi hết hạn. */
		$b = VHCC_DB::t( 'cuoc_goi' );
		$dang = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $b WHERE trang_thai IN ('moi','nghe')"
			. ' AND (ma_goi=%s OR ma_nhan=%s OR ma_goi=%s OR ma_nhan=%s)',
			$ma, $ma, $kia, $kia ) );
		if ( $dang > 0 ) {
			return array( 'ok' => false, 'error' => 'Một trong hai người đang có cuộc gọi khác.' );
		}

		$ten_kia = (string) $wpdb->get_var( $wpdb->prepare(
			'SELECT ho_ten FROM ' . VHCC_DB::t( 'nhan_vien' ) . ' WHERE ma_nv=%s', $kia ) );

		$ok = $wpdb->insert( $b, array(
			'phong'      => $phong,
			'ma_goi'     => $ma,
			'ten_goi'    => (string) ( isset( $u['ho_ten'] ) ? $u['ho_ten'] : $u['name'] ),
			'ma_nhan'    => $kia,
			'ten_nhan'   => $ten_kia,
			'trang_thai' => 'moi',
			'tao_luc'    => current_time( 'mysql' ),
		) );
		if ( false === $ok ) { return array( 'ok' => false, 'error' => 'MySQL: ' . $wpdb->last_error ); }
		return array( 'ok' => true, 'id' => (int) $wpdb->insert_id, 'phong' => $phong,
			'tenKia' => $ten_kia );
	}

	/** Có ai đang gọi tôi không. Trả về cuộc gọi đang đổ chuông, hoặc null. */
	public static function cho( $u ) {
		global $wpdb;
		self::het_han();
		$ma = trim( (string) $u['ma_nv'] );
		if ( '' === $ma ) { return null; }
		$r = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . VHCC_DB::t( 'cuoc_goi' )
			. " WHERE ma_nhan=%s AND trang_thai='moi' ORDER BY id DESC LIMIT 1", $ma ), ARRAY_A );
		if ( ! $r ) { return null; }
		return array( 'id' => (int) $r['id'], 'maGoi' => (string) $r['ma_goi'],
			'tenGoi' => (string) $r['ten_goi'], 'luc' => (string) $r['tao_luc'] );
	}

	/** Trạng thái một cuộc — cả hai bên hỏi để biết bên kia đã bấm gì. */
	public static function trang_thai( $u, $id ) {
		$c = self::cua_toi( $u, $id );
		if ( ! $c ) { return array( 'ok' => false, 'error' => 'Không thấy cuộc gọi này.' ); }
		return array( 'ok' => true, 'id' => (int) $c['id'], 'trangThai' => (string) $c['trang_thai'],
			'lyDo' => (string) $c['ly_do_ket'] );
	}

	/** Nghe máy, hoặc từ chối. */
	public static function tra_loi( $u, $id, $dong_y ) {
		global $wpdb;
		$c = self::cua_toi( $u, $id );
		if ( ! $c ) { return array( 'ok' => false, 'error' => 'Không thấy cuộc gọi này.' ); }
		/* ⚠️ CHỈ NGƯỜI NHẬN mới trả lời được. Người gọi tự "nghe máy" hộ là nối máy với một
		   người chưa bấm gì — micro bên kia mở ra mà chủ nhân không biết. */
		if ( 0 !== strcmp( (string) $c['ma_nhan'], trim( (string) $u['ma_nv'] ) ) ) {
			return array( 'ok' => false, 'error' => 'Chỉ người được gọi mới trả lời được.' );
		}
		if ( 'moi' !== (string) $c['trang_thai'] ) {
			return array( 'ok' => false, 'error' => 'Cuộc gọi này không còn đổ chuông.' );
		}
		$wpdb->update( VHCC_DB::t( 'cuoc_goi' ),
			$dong_y
				? array( 'trang_thai' => 'nghe', 'tra_loi_luc' => current_time( 'mysql' ) )
				: array( 'trang_thai' => 'xong', 'ly_do_ket' => 'tu_choi',
					'ket_luc' => current_time( 'mysql' ) ),
			array( 'id' => (int) $c['id'] ) );
		return array( 'ok' => true, 'trangThai' => $dong_y ? 'nghe' : 'xong' );
	}

	/** Cúp máy. Bên nào bấm cũng được. */
	public static function ket( $u, $id, $ly_do = 'cup' ) {
		global $wpdb;
		$c = self::cua_toi( $u, $id );
		if ( ! $c ) { return array( 'ok' => false, 'error' => 'Không thấy cuộc gọi này.' ); }
		if ( 'xong' === (string) $c['trang_thai'] ) { return array( 'ok' => true ); }
		$wpdb->update( VHCC_DB::t( 'cuoc_goi' ), array(
			'trang_thai' => 'xong',
			'ly_do_ket'  => preg_replace( '/[^a-z_]/', '', (string) $ly_do ),
			'ket_luc'    => current_time( 'mysql' ),
		), array( 'id' => (int) $c['id'] ) );
		return array( 'ok' => true );
	}

	/* ========================================================================== mai mối */

	/**
	 * Gửi một mẩu mai mối (SDP hoặc ICE) cho bên kia.
	 *
	 * ⚠️ MÁY CHỦ KHÔNG ĐỌC NỘI DUNG, chỉ chuyển. Nhưng vẫn phải chặn kích thước và chặn người
	 *    lạ: đây là một bảng ai cũng ghi vào được, và không chặn thì nó thành chỗ chứa rác.
	 */
	public static function gui_tin( $u, $id, $loai, $noi_dung ) {
		global $wpdb;
		$c = self::cua_toi( $u, $id );
		if ( ! $c ) { return array( 'ok' => false, 'error' => 'Không thấy cuộc gọi này.' ); }
		if ( 'xong' === (string) $c['trang_thai'] ) {
			return array( 'ok' => false, 'error' => 'Cuộc gọi đã kết thúc.' );
		}
		$l = preg_replace( '/[^a-z]/', '', (string) $loai );
		if ( ! in_array( $l, array( 'offer', 'answer', 'ice' ), true ) ) {
			return array( 'ok' => false, 'error' => 'Loại tin không hợp lệ.' );
		}
		$nd = (string) $noi_dung;
		if ( strlen( $nd ) > self::TIN_TOI_DA ) {
			return array( 'ok' => false, 'error' => 'Mẩu tin quá lớn.' );
		}
		$wpdb->insert( VHCC_DB::t( 'goi_tin' ), array(
			'cuoc_id' => (int) $c['id'],
			'tu_ma'   => trim( (string) $u['ma_nv'] ),
			'loai'    => $l,
			'noi_dung' => $nd,
			'tao_luc' => current_time( 'mysql' ),
		) );
		return array( 'ok' => true, 'id' => (int) $wpdb->insert_id );
	}

	/**
	 * Đọc mấy mẩu BÊN KIA gửi, mới hơn `tu_id`.
	 *
	 * 🔴 CHỈ LẤY TIN CỦA BÊN KIA (`tu_ma <> mình`). Lấy cả tin của mình thì trình duyệt tự nạp
	 *    lại chính lời mời của nó vào `setRemoteDescription` — WebRTC ném lỗi trạng thái và
	 *    cuộc gọi chết ngay ở nhịp đầu.
	 */
	public static function doc_tin( $u, $id, $tu_id = 0 ) {
		global $wpdb;
		$c = self::cua_toi( $u, $id );
		if ( ! $c ) { return array( 'ok' => false, 'error' => 'Không thấy cuộc gọi này.' ); }
		$ma = trim( (string) $u['ma_nv'] );
		$r = VHCC_DB::rows( $wpdb->prepare(
			'SELECT id, loai, noi_dung FROM ' . VHCC_DB::t( 'goi_tin' )
			. ' WHERE cuoc_id=%d AND id>%d AND tu_ma<>%s ORDER BY id ASC LIMIT 200',
			(int) $c['id'], max( 0, (int) $tu_id ), $ma ) );
		$ds = array();
		foreach ( (array) $r as $x ) {
			$ds[] = array( 'id' => (int) $x['id'], 'loai' => (string) $x['loai'],
				'noiDung' => (string) $x['noi_dung'] );
		}
		return array( 'ok' => true, 'ds' => $ds, 'trangThai' => (string) $c['trang_thai'] );
	}

	/* ============================================================================== phụ */

	/**
	 * Cuộc gọi này có phải của tôi không — hàng, hoặc null.
	 *
	 * 🔴 MỌI HÀM TRÊN ĐỀU ĐI QUA ĐÂY. Đây là chỗ duy nhất trả lời "người này có dính vào cuộc
	 *    gọi ấy không"; thiếu nó ở một hàm là người thứ ba đọc được mai mối của cuộc gọi người
	 *    khác — và mai mối thì đủ để nối vào cuộc gọi ấy.
	 */
	private static function cua_toi( $u, $id ) {
		global $wpdb;
		$ma = isset( $u['ma_nv'] ) ? trim( (string) $u['ma_nv'] ) : '';
		if ( '' === $ma || (int) $id <= 0 ) { return null; }
		$r = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . VHCC_DB::t( 'cuoc_goi' ) . ' WHERE id=%d', (int) $id ), ARRAY_A );
		if ( ! $r ) { return null; }
		if ( 0 !== strcmp( (string) $r['ma_goi'], $ma ) && 0 !== strcmp( (string) $r['ma_nhan'], $ma ) ) {
			return null;
		}
		return $r;
	}

	/**
	 * Đánh dấu NHỠ cho cuộc đổ chuông quá lâu, và dọn mai mối cũ.
	 *
	 * ⚠️ Gọi ở đầu `goi()` và `cho()` chứ không xếp cron: cron của WordPress chỉ chạy khi có
	 *    người mở trang, nên nó KHÔNG chạy đúng lúc cần — mà lúc cần là ngay khi người nhận mở
	 *    app ra. Hai câu lệnh này rẻ và chạy đúng lúc.
	 */
	private static function het_han() {
		global $wpdb;
		$bc = VHCC_DB::t( 'cuoc_goi' );
		$han = gmdate( 'Y-m-d H:i:s',
			strtotime( current_time( 'mysql' ) ) - self::CHUONG_GIAY );
		$wpdb->query( $wpdb->prepare(
			"UPDATE $bc SET trang_thai='xong', ly_do_ket='nho', ket_luc=%s"
			. " WHERE trang_thai='moi' AND tao_luc < %s", current_time( 'mysql' ), $han ) );

		/* Dọn mai mối của cuộc đã xong. ICE sinh ra hàng chục hàng mỗi cuộc; không dọn thì bảng
		   này phình nhanh hơn mọi bảng khác trong hệ, mà không hàng nào còn dùng được. */
		$hen = gmdate( 'Y-m-d H:i:s',
			strtotime( current_time( 'mysql' ) ) - self::DON_PHUT * 60 );
		$wpdb->query( $wpdb->prepare(
			'DELETE FROM ' . VHCC_DB::t( 'goi_tin' ) . ' WHERE cuoc_id IN'
			. " ( SELECT id FROM $bc WHERE trang_thai='xong' AND ket_luc < %s )", $hen ) );
	}
}
