<?php
/**
 * ẢNH CHỨNG TỪ & HỒ SƠ — thay Google Drive bằng thư mục uploads của WordPress.
 *
 * Cây thư mục giữ đúng kiểu cũ:  uploads/vhcp/<Cơ sở>/<Người lập>/CP_<mã đơn>_<ts>.jpg
 *                                uploads/vhcp/HoSo_DuAn/<mã dự án>_<tên file>
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCP_Upload {

	const ROOT     = 'vhcp';
	const MAX_SIZE = 15728640;   // 15 MB

	private static function img_ext( $mime ) {
		$map = array(
			'image/jpeg' => 'jpg',
			'image/jpg'  => 'jpg',
			'image/png'  => 'png',
			'image/gif'  => 'gif',
			'image/webp' => 'webp',
			'image/heic' => 'heic',
			'image/heif' => 'heif',
		);
		$m = strtolower( trim( (string) $mime ) );
		return isset( $map[ $m ] ) ? $map[ $m ] : '';
	}

	/** Đuôi file hồ sơ được phép (chặn mọi thứ chạy được trên server). */
	private static function doc_ext_ok( $ext ) {
		return in_array( strtolower( $ext ), array( 'pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'heif', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'zip', 'rar', '7z', 'ppt', 'pptx' ), true );
	}

	private static function safe_name( $s ) {
		$s = trim( (string) $s );
		if ( $s === '' ) { return ''; }
		$s = str_replace( array( '/', '\\' ), '-', $s );
		$s = preg_replace( '/\s+/u', ' ', $s );
		return trim( mb_substr( $s, 0, 80 ) );
	}

	/** Tạo (nếu chưa có) thư mục con và trả về [đường dẫn, url]. */
	private static function dir( $parts ) {
		$up   = wp_upload_dir();
		$path = rtrim( $up['basedir'], '/\\' );
		$url  = rtrim( $up['baseurl'], '/' );
		foreach ( array_merge( array( self::ROOT ), (array) $parts ) as $p ) {
			$p = self::safe_name( $p );
			if ( $p === '' ) { continue; }
			$path .= '/' . $p;
			$url  .= '/' . rawurlencode( $p );
			if ( ! is_dir( $path ) ) { wp_mkdir_p( $path ); }
		}
		return array( $path, $url );
	}

	private static function decode( $b64 ) {
		$b64 = (string) $b64;
		if ( strpos( $b64, ',' ) !== false ) {
			$parts = explode( ',', $b64 );
			$b64   = end( $parts );
		}
		$b64 = preg_replace( '/\s+/', '', $b64 );
		if ( $b64 === '' ) { return null; }
		$bin = base64_decode( $b64, true );
		return ( $bin === false || $bin === '' ) ? null : $bin;
	}

	/** uploadImage(dataObj, maDon, coso) */
	public static function upload_image( $data, $ma_don = '', $coso = null ) {
		$data = (array) $data;
		$bin  = self::decode( isset( $data['base64'] ) ? $data['base64'] : '' );
		if ( $bin === null ) { return VHCP_Util::err( 'Ảnh trống' ); }
		if ( strlen( $bin ) > self::MAX_SIZE ) { return VHCP_Util::err( 'Ảnh quá lớn (tối đa 15MB)' ); }
		$mime = isset( $data['type'] ) ? $data['type'] : 'image/jpeg';
		$ext  = self::img_ext( $mime );
		if ( $ext === '' ) { return VHCP_Util::err( 'Chỉ nhận ảnh (JPG/PNG/GIF/WEBP/HEIC)' ); }

		$meta      = VHCP_Don::don_folder_meta( $ma_don );
		$use_coso  = ( $coso !== null && trim( (string) $coso ) !== '' ) ? (string) $coso : $meta['coso'];
		list( $dir, $url ) = self::dir( array( $use_coso, $meta['nguoiLap'] ) );

		$name = 'CP_' . self::safe_name( $ma_don ) . '_' . time() . '_' . wp_generate_password( 6, false, false ) . '.' . $ext;
		$file = $dir . '/' . $name;
		if ( false === file_put_contents( $file, $bin ) ) { return VHCP_Util::err( 'Không ghi được file lên hosting (kiểm tra quyền thư mục uploads)' ); }
		@chmod( $file, 0644 );

		return VHCP_Util::ok( array( 'url' => $url . '/' . rawurlencode( $name ), 'fileId' => $name ) );
	}

	/** uploadDuAnDoc(dataObj, maDA) — hồ sơ nhà thầu, giữ nguyên loại file. */
	public static function upload_doc( $data, $ma_da = '' ) {
		$data = (array) $data;
		$bin  = self::decode( isset( $data['base64'] ) ? $data['base64'] : '' );
		if ( $bin === null ) { return VHCP_Util::err( 'File trống' ); }
		if ( strlen( $bin ) > self::MAX_SIZE ) { return VHCP_Util::err( 'File quá lớn (tối đa 15MB)' ); }

		$raw = isset( $data['name'] ) ? (string) $data['name'] : ( 'HoSo_' . time() );
		$raw = preg_replace( '/[\\\\\/:*?"<>|]/u', '_', $raw );
		$raw = mb_substr( trim( $raw ), 0, 120 );
		$ext = strtolower( pathinfo( $raw, PATHINFO_EXTENSION ) );
		if ( ! self::doc_ext_ok( $ext ) ) { return VHCP_Util::err( 'Loại file không được phép: .' . $ext ); }
		$base = sanitize_file_name( pathinfo( $raw, PATHINFO_FILENAME ) );
		if ( $base === '' ) { $base = 'hoso'; }

		list( $dir, $url ) = self::dir( array( 'HoSo_DuAn' ) );
		$name = ( $ma_da ? self::safe_name( $ma_da ) . '_' : '' ) . $base . '_' . wp_generate_password( 6, false, false ) . '.' . $ext;
		$file = $dir . '/' . $name;
		if ( false === file_put_contents( $file, $bin ) ) { return VHCP_Util::err( 'Không ghi được file lên hosting (kiểm tra quyền thư mục uploads)' ); }
		@chmod( $file, 0644 );

		return VHCP_Util::ok( array( 'url' => $url . '/' . rawurlencode( $name ), 'name' => $name ) );
	}

	/**
	 * migrateOldImages(): ở bản Google Drive đây là việc dời ảnh cũ về cây <Cơ sở>/<Người lập>.
	 * Bản WordPress đã lưu đúng cây ngay từ đầu nên không còn việc gì để chạy.
	 */
	public static function migrate_old_images( $max = 400 ) {
		return VHCP_Util::ok( array(
			'moved'     => 0,
			'skipped'   => 0,
			'remaining' => 0,
			'msg'       => 'Bản WordPress lưu ảnh đúng cây thư mục ngay khi tải lên — không cần chạy dọn ảnh cũ.',
		) );
	}
}
