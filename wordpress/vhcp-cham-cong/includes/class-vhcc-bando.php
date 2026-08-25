<?php
/**
 * Ô ẢNH BẢN ĐỒ — máy chủ tải hộ và nhớ lại.
 *
 * =============================================================================================
 * 🔴 VÌ SAO PHẢI ĐI VÒNG QUA MÁY CHỦ
 * =============================================================================================
 * Bản đồ trên trang chấm công đã hỏng HAI LẦN, cả hai đều vì trình duyệt của nhân viên nói
 * chuyện thẳng với máy chủ của người khác:
 *
 *   1. Nhúng <iframe> openstreetmap.org  ->  "đã từ chối kết nối" (họ chặn nhúng bằng tiêu đề).
 *   2. Tải thẳng ô ảnh tile.openstreetmap.org  ->  ô trắng, một ô hiện dấu hỏi ảnh vỡ.
 *
 * Lần thứ hai có lý do chính đáng về phía họ: chính sách dùng ô ảnh của OpenStreetMap KHÔNG
 * cho phép một trang bất kỳ móc thẳng vào máy chủ ô ảnh của họ. Họ chặn, và họ đúng.
 *
 * Cách làm phải phép — cũng chính là cách chạy được — là máy chủ mình tải hộ MỘT LẦN, nhớ lại,
 * rồi phục vụ cho nhân viên. Được ba thứ cùng lúc:
 *   · Đúng chính sách: một máy chủ khai danh tính rõ ràng, có nhớ đệm, không dội hàng nghìn
 *     lượt lẻ từ hàng trăm điện thoại.
 *   · Chạy nhanh hơn: ô ảnh của cơ sở mình lấy từ hosting mình, lần thứ hai trở đi không ra
 *     internet nữa.
 *   · Không rò: toạ độ nhân viên không rời khỏi khmatrix.com.
 *
 * =============================================================================================
 * ⚠️ ĐÂY KHÔNG PHẢI CỔNG CHUYỂN TIẾP TỰ DO
 * =============================================================================================
 * Một đường mà ai gõ cũng khiến máy chủ mình đi tải hộ một địa chỉ ngoài là món quà cho kẻ
 * muốn mượn tay. Nên khoá ba lớp: chỉ đúng mức phóng đang dùng, chỉ vùng Việt Nam, và địa chỉ
 * đích do CHÍNH mã này dựng từ ba con số nguyên — không nhận một mẩu URL nào từ bên ngoài.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_BanDo {

	/** Mức phóng cho phép. Trang chỉ dùng 15–17; nới rộng hơn là mời người ta tải cả nước. */
	const Z_MIN = 13;
	const Z_MAX = 18;

	/** Khung Việt Nam, nới rộng tay. Ngoài khung này thì trang chấm công không có việc gì. */
	const LAT_MIN = 7.0;
	const LAT_MAX = 24.5;
	const LNG_MIN = 101.0;
	const LNG_MAX = 110.5;

	/** Ô ảnh nhớ được bao lâu. Đường phố không đổi hằng tuần. */
	const SONG_NGAY = 60;

	public static function thu_muc() {
		$up = wp_upload_dir();
		return trailingslashit( $up['basedir'] ) . 'vhcc-bando/';
	}

	/** (z,x,y) -> góc trên-trái của ô, để biết ô có nằm trong khung Việt Nam không. */
	public static function trong_vung( $z, $x, $y ) {
		$n = pow( 2, $z );
		if ( $x < 0 || $y < 0 || $x >= $n || $y >= $n ) { return false; }
		$lng = $x / $n * 360.0 - 180.0;
		$lat = rad2deg( atan( sinh( M_PI * ( 1 - 2 * $y / $n ) ) ) );
		/* Cộng trừ một ô để không cắt mất ô rìa của khung. */
		$bien = 360.0 / $n;
		return ( $lat >= self::LAT_MIN - $bien && $lat <= self::LAT_MAX + $bien
			&& $lng >= self::LNG_MIN - $bien && $lng <= self::LNG_MAX + $bien );
	}

	/**
	 * Phục vụ một ô ảnh. Tự kết thúc lượt gọi.
	 *
	 * ⚠️ HỎNG THÌ TRẢ 404, ĐỪNG TRẢ ẢNH TRẮNG. Ảnh trắng trông y như bản đồ vùng biển, và
	 *    trang sẽ hiện một khung xám mà không ai biết là hỏng. 404 thì `onerror` bắt được và
	 *    cả khối bản đồ tự ẩn — còn lại toạ độ với link, sạch sẽ.
	 */
	public static function phuc_vu( $z, $x, $y ) {
		$z = (int) $z; $x = (int) $x; $y = (int) $y;
		if ( $z < self::Z_MIN || $z > self::Z_MAX || ! self::trong_vung( $z, $x, $y ) ) {
			self::chet( 400 );
		}

		$thu_muc = self::thu_muc() . $z . '/' . $x . '/';
		$tep     = $thu_muc . $y . '.png';

		if ( is_readable( $tep ) && ( time() - filemtime( $tep ) ) < self::SONG_NGAY * DAY_IN_SECONDS ) {
			self::tra( file_get_contents( $tep ) );
		}

		/* Địa chỉ dựng từ ba SỐ NGUYÊN đã kiểm, không ghép mẩu nào của người gọi. */
		$url = 'https://tile.openstreetmap.org/' . $z . '/' . $x . '/' . $y . '.png';
		$kq  = wp_remote_get( $url, array(
			'timeout' => 8,
			/* Khai danh tính đàng hoàng — chính sách của OpenStreetMap đòi thế, và một
			   User-Agent trống là lý do phổ biến nhất khiến họ chặn. */
			'headers' => array(
				'User-Agent' => 'VHCC-ChamCong/' . VHCC_VERSION . ' (+' . home_url( '/' ) . ')',
				'Referer'    => home_url( '/' ),
			),
		) );

		if ( is_wp_error( $kq ) || 200 !== (int) wp_remote_retrieve_response_code( $kq ) ) {
			/* Còn bản cũ quá hạn thì DÙNG TẠM còn hơn không có gì: đường phố không đổi trong
			   một tháng, mà mất mạng thì bản đồ trắng ngay lúc người ta cần nhìn. */
			if ( is_readable( $tep ) ) { self::tra( file_get_contents( $tep ) ); }
			self::chet( 502 );
		}

		$anh = wp_remote_retrieve_body( $kq );
		/* Nhận đúng PNG. Nhà mạng chèn trang quảng cáo thay cho ảnh là chuyện có thật ở VN, và
		   nhớ lại một trang HTML dưới tên .png thì lần sau vẫn hỏng mà không ai hiểu vì sao. */
		if ( strlen( $anh ) < 100 || "\x89PNG" !== substr( $anh, 0, 4 ) ) { self::chet( 502 ); }

		wp_mkdir_p( $thu_muc );
		file_put_contents( $tep, $anh );
		self::tra( $anh );
	}

	private static function tra( $anh ) {
		header( 'Content-Type: image/png' );
		header( 'Content-Length: ' . strlen( $anh ) );
		/* Trình duyệt nhớ một tuần: mở trang chấm công mười lần một ngày thì chín lần không
		   phải hỏi lại máy chủ. */
		header( 'Cache-Control: public, max-age=604800' );
		echo $anh; // phpcs:ignore WordPress.Security.EscapeOutput -- dữ liệu ảnh nhị phân
		exit;
	}

	private static function chet( $ma ) {
		status_header( (int) $ma );
		header( 'Content-Type: text/plain; charset=utf-8' );
		echo 'khong co o anh';
		exit;
	}

	/** Dọn ô ảnh quá hạn. Gọi thưa thôi — quét thư mục là việc nặng. */
	public static function don() {
		$goc = self::thu_muc();
		if ( ! is_dir( $goc ) ) { return 0; }
		$han = time() - self::SONG_NGAY * DAY_IN_SECONDS;
		$so  = 0;
		$it  = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $goc, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $it as $f ) {
			if ( $f->isFile() && $f->getMTime() < $han ) { @unlink( $f->getPathname() ); $so++; }
		}
		return $so;
	}
}
