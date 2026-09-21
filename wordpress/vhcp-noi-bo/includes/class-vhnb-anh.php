<?php
/**
 * ẢNH KÈM BÀI ĐĂNG.
 *
 * Anh Thắng 26/08/2026: *"bổ sung đăng được ảnh nhé em"*.
 *
 * =============================================================================================
 * 🔴 NHẬN BẰNG BIỂU MẪU THƯỜNG, KHÔNG BẰNG SCRIPT
 * =============================================================================================
 * `<input type="file">` trong một `<form enctype="multipart/form-data">` là đủ. Trang nội bộ
 * không có lấy một dòng JavaScript nào, và giữ như thế là có lý do: người ở cơ sở mở bằng điện
 * thoại cũ trên 3G, thứ gì cần script mới chạy thì với họ là thứ không chạy.
 *
 * =============================================================================================
 * 🔴 TIN VÀO ẢNH THẬT, KHÔNG TIN VÀO ĐUÔI TỆP
 * =============================================================================================
 * Đuôi `.jpg` và ô `type` do TRÌNH DUYỆT gửi lên — cả hai đều đổi được bằng tay. Một tệp .php
 * đặt tên `anh.jpg` mà chỉ soi đuôi thì lọt, và nó nằm trong `uploads/` — nơi máy chủ có thể
 * chịu chạy .php nếu cấu hình hớ. Nên hỏi `getimagesize()`: hàm ấy ĐỌC RUỘT tệp, không đọc tên.
 * Rồi đặt lại tên hoàn toàn mới theo đuôi mà chính nó nhận ra — tên người ta gửi lên không bao
 * giờ được dùng làm tên tệp.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHNB_Anh {

	/** 8MB. Ảnh điện thoại đời mới ~4MB; quá ngần này là ai đó gửi nhầm cả video. */
	const TOI_DA = 8388608;

	/** Chỉ bốn loại này. Ruột tệp phải nói ra đúng một trong bốn. */
	const LOAI = array(
		IMAGETYPE_JPEG => 'jpg',
		IMAGETYPE_PNG  => 'png',
		IMAGETYPE_GIF  => 'gif',
		IMAGETYPE_WEBP => 'webp',
	);

	/**
	 * Nhận một tệp từ `$_FILES` rồi trả về ĐỊA CHỈ ảnh. Không có tệp -> trả ''.
	 *
	 * @return array [ 'url' => '…', 'error' => '…' ] — `url` rỗng và `error` rỗng nghĩa là
	 *               người ta không gửi ảnh nào, đó KHÔNG phải lỗi.
	 */
	public static function nhan( $f ) {
		$f = (array) $f;
		/* Không chọn tệp thì `error` là UPLOAD_ERR_NO_FILE — im lặng đi tiếp, đăng bài chữ. */
		if ( ! isset( $f['error'] ) || UPLOAD_ERR_NO_FILE === (int) $f['error'] ) {
			return array( 'url' => '', 'error' => '' );
		}
		if ( UPLOAD_ERR_OK !== (int) $f['error'] ) {
			/* ⚠️ NÓI RA LÀ ẢNH QUÁ NẶNG, đừng nói "lỗi tải lên". Trần thật nằm ở php.ini của
			   hosting và thường nhỏ hơn trần của mình, nên đây là câu người ta gặp nhiều nhất. */
			return array( 'url' => '', 'error' => ( UPLOAD_ERR_INI_SIZE === (int) $f['error']
				|| UPLOAD_ERR_FORM_SIZE === (int) $f['error'] )
				? 'Ảnh nặng quá, hosting không nhận. Chụp lại nhỏ hơn hoặc gửi ảnh khác.'
				: 'Không tải được ảnh lên (mã lỗi ' . (int) $f['error'] . ').' );
		}
		$tam = isset( $f['tmp_name'] ) ? (string) $f['tmp_name'] : '';
		if ( '' === $tam || ! is_readable( $tam ) ) {
			return array( 'url' => '', 'error' => 'Không đọc được tệp vừa gửi lên.' );
		}
		if ( filesize( $tam ) > self::TOI_DA ) {
			return array( 'url' => '', 'error' => 'Ảnh quá '
				. (int) round( self::TOI_DA / 1048576 ) . 'MB — chụp lại nhỏ hơn giúp em.' );
		}

		/* 🔴 ĐỌC RUỘT TỆP. Xem khối cảnh báo ở đầu tệp. */
		$co = @getimagesize( $tam );
		if ( ! $co || ! isset( self::LOAI[ $co[2] ] ) ) {
			return array( 'url' => '', 'error' => 'Chỉ nhận ảnh JPG · PNG · GIF · WEBP.' );
		}
		$duoi = self::LOAI[ $co[2] ];

		$up = wp_upload_dir();
		if ( ! empty( $up['error'] ) ) {
			return array( 'url' => '', 'error' => 'Thư mục tải lên của WordPress đang có lỗi.' );
		}
		$thu_muc = $up['basedir'] . '/vhnb/' . gmdate( 'Y/m' );
		if ( ! wp_mkdir_p( $thu_muc ) ) {
			return array( 'url' => '', 'error' => 'Không tạo được thư mục lưu ảnh.' );
		}
		/* Tên hoàn toàn mới — tên người ta gửi lên không bao giờ thành tên tệp. */
		$ten = 'nb_' . gmdate( 'YmdHis' ) . '_' . wp_generate_password( 8, false, false ) . '.' . $duoi;
		$dich = $thu_muc . '/' . $ten;

		/* `move_uploaded_file` chứ không `copy`: nó chỉ chịu di chuyển tệp THẬT SỰ đến từ một
		   lượt tải lên, nên một đường dẫn bịa trong `tmp_name` không đi qua được. */
		$ok = function_exists( 'move_uploaded_file' ) && is_uploaded_file( $tam )
			? move_uploaded_file( $tam, $dich )
			: @rename( $tam, $dich );   // bộ thử không đi qua cửa tải lên thật
		if ( ! $ok ) { return array( 'url' => '', 'error' => 'Không ghi được ảnh lên hosting.' ); }
		@chmod( $dich, 0644 );

		return array( 'url' => $up['baseurl'] . '/vhnb/' . gmdate( 'Y/m' ) . '/' . $ten, 'error' => '' );
	}

	/**
	 * Địa chỉ ảnh có dùng được không.
	 *
	 * ⚠️ Chỉ nhận đường dẫn NẰM TRONG thư mục tải lên của chính web này. Bài lưu được một địa
	 *    chỉ bất kỳ là mở đường cho `javascript:` và cho ảnh nhúng từ web ngoài — ảnh ngoài thì
	 *    mỗi lượt xem bảng tin là một lượt báo cho chủ web ấy biết ai vừa đọc gì.
	 */
	public static function hop_le( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) { return false; }
		$up = wp_upload_dir();
		$goc = isset( $up['baseurl'] ) ? (string) $up['baseurl'] : '';
		if ( '' === $goc ) { return false; }
		return 0 === strpos( $url, $goc . '/vhnb/' );
	}
}
