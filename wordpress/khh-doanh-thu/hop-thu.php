<?php
/**
 * NHẬN BÁO CÁO QUA HỘP THƯ — FABi gửi tệp đính kèm, web tự vào lấy theo giờ.
 *
 * 19/09/2026, anh Thắng: *"vấn đề đẩy dữ liệu trên fabi hơi khó, vậy anh sẽ áp dụng bằng
 * phương pháp đẩy dữ liệu fabi về mail hosting, xong web sẽ đọc mail lấy file đó, định kì 2
 * tiếng lần (có thể chỉnh được)"*, rồi *"Fabi gửi tệp đính kèm"* và *"anh sẽ tạo mail
 * bao.cao.noi.bo@…"*.
 *
 * Tệp lấy về đi qua ĐÚNG `khh_dt_nap_tep()` mà đường tải lên bằng tay vẫn dùng. Chép luật đọc
 * file ra bản thứ hai là sớm muộn hai bên lệch nhau, mà lệch kiểu ấy im thin thít: file nạp
 * vào, không câu báo nào, chỉ là đọc sai kiểu.
 *
 * ═══════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 KHÔNG DÙNG PHẦN MỞ RỘNG `imap` CỦA PHP — CỐ Ý.
 * ═══════════════════════════════════════════════════════════════════════════════════════════
 * Từ PHP 8.4 `imap` đã bị tách khỏi bản gốc, nên host đời mới thường không có, và có thể biến
 * mất sau một lượt nâng cấp PHP của hosting mà không ai báo. Dựa vào nó là dựng một thứ chạy
 * hôm nay rồi chết câm vào một ngày không ai đụng tới mã. Ở đây nói IMAP thẳng bằng socket —
 * `openssl` thì host nào cũng có.
 *
 * 🔴 WP-CRON KHÔNG PHẢI CRON THẬT. Nó chỉ chạy khi CÓ NGƯỜI MỞ TRANG. Trang doanh thu ban đêm
 *    không ai vào là cả đêm không kéo, mà màn hình vẫn trông bình thường. Nên phải mở Cron Jobs
 *    trong bảng điều khiển hosting cho gọi `wp-cron.php` mỗi 5–10 phút — và vì cái đó nằm ngoài
 *    plugin, `khh_dt_thu_qua_han()` ở dưới BÁO ĐỎ khi quá hạn mà chưa chạy. Im lặng là mất mấy
 *    ngày số liệu rồi mới có người nhận ra.
 *
 * 🔴 MẬT KHẨU HỘP THƯ KHÔNG BAO GIỜ ĐI NGƯỢC RA TRÌNH DUYỆT. Màn cấu hình chỉ cho biết ĐÃ ĐẶT
 *    hay CHƯA. Đặt trong `wp-config.php` (`KHH_DT_MAIL_PASS`) thì hơn — lúc ấy nó không nằm
 *    trong cơ sở dữ liệu, nên một bản sao lưu lọt ra ngoài cũng không kèm mật khẩu hộp thư.
 */

defined( 'ABSPATH' ) || exit;

const KHH_DT_THU_OPT   = 'khh_dt_hop_thu';
const KHH_DT_THU_NHAT  = 'khh_dt_hop_thu_nhat_ky';
const KHH_DT_THU_DA    = 'khh_dt_hop_thu_da_nap';
const KHH_DT_THU_MOC   = 'khh_dt_cron_hop_thu';
const KHH_DT_THU_NHIP  = 'khh_dt_nhip_hop_thu';

/** Cỡ tối đa một tệp đính kèm chịu nhận — trên mức này gần như chắc chắn không phải báo cáo. */
const KHH_DT_THU_CO_MAX = 41943040;   // 40 MB
/** Mỗi lượt chạy xử tối đa ngần này thư, để không ôm trọn một hộp thư dồn ứ trong một nhịp. */
const KHH_DT_THU_MOI_LUOT = 20;

/* ================================================================== *
 * Cấu hình
 * ================================================================== */

function khh_dt_thu_mac_dinh() {
	return array(
		'bat'       => false,
		'may'       => '',            // ví dụ imap.hostinger.com
		'cong'      => 993,
		'bao_mat'   => 'ssl',         // 'ssl' | 'starttls' | 'khong'
		'nguoi'     => '',            // địa chỉ hộp thư
		'mat_khau'  => '',            // để rỗng nếu đã đặt hằng KHH_DT_MAIL_PASS
		'thu_muc'   => 'INBOX',
		'nguoi_gui' => '',            // danh sách người gửi được phép, cách nhau bằng dấu phẩy
		'mau_ten'   => '*.xlsx, *.csv',
		'loai'      => 'pos',
		'gio'       => 2,
		'co_so'     => '',            // chỉ dùng khi loại = bes
		'tai_khoan' => '',            // chỉ dùng khi loại = momo_sk
	);
}

function khh_dt_thu_cf() {
	$c = get_option( KHH_DT_THU_OPT, array() );
	$c = is_array( $c ) ? $c : array();
	return array_merge( khh_dt_thu_mac_dinh(), $c );
}

/**
 * Mật khẩu hộp thư.
 *
 * Hằng trong `wp-config.php` thắng, luôn luôn: đặt được ở đó thì mật khẩu không nằm trong cơ
 * sở dữ liệu, nên một bản sao lưu lọt ra ngoài cũng không kèm theo nó.
 */
function khh_dt_thu_mat_khau() {
	if ( defined( 'KHH_DT_MAIL_PASS' ) && '' !== (string) constant( 'KHH_DT_MAIL_PASS' ) ) {
		return (string) constant( 'KHH_DT_MAIL_PASS' );
	}
	$c = khh_dt_thu_cf();
	return (string) $c['mat_khau'];
}

/** Đủ thứ để nối chưa? */
function khh_dt_thu_du_cau_hinh() {
	$c = khh_dt_thu_cf();
	return '' !== trim( $c['may'] ) && '' !== trim( $c['nguoi'] ) && '' !== khh_dt_thu_mat_khau();
}

/* ================================================================== *
 * Lịch chạy
 * ================================================================== */

add_filter( 'cron_schedules', 'khh_dt_thu_nhip' );   // phpcs:ignore WordPress.WP.CronInterval
function khh_dt_thu_nhip( $ds ) {
	$c   = khh_dt_thu_cf();
	$gio = max( 1, min( 24, (int) $c['gio'] ) );
	$ds[ KHH_DT_THU_NHIP ] = array(
		'interval' => $gio * HOUR_IN_SECONDS,
		'display'  => sprintf( 'K&H — mỗi %d giờ (lấy báo cáo từ hộp thư)', $gio ),
	);
	return $ds;
}

function khh_dt_thu_dat_lich() {
	$cu = wp_next_scheduled( KHH_DT_THU_MOC );
	if ( $cu ) {
		wp_unschedule_event( $cu, KHH_DT_THU_MOC );
	}
	$c = khh_dt_thu_cf();
	if ( ! empty( $c['bat'] ) ) {
		wp_schedule_event( time() + 120, KHH_DT_THU_NHIP, KHH_DT_THU_MOC );
	}
}

/**
 * Quá hạn mà chưa chạy?
 *
 * 🔴 PHẢI CÓ. WP-Cron chỉ chạy khi có người mở trang; không ai vào là lịch nằm im mà màn hình
 *    vẫn trông bình thường. Cho hai nhịp trượt rồi mới kêu, để một lượt lỡ không làm hoảng.
 */
function khh_dt_thu_qua_han() {
	$c = khh_dt_thu_cf();
	if ( empty( $c['bat'] ) ) {
		return false;
	}
	$nk = khh_dt_thu_nhat_ky();
	if ( ! $nk ) {
		return false;   // chưa chạy lần nào thì chưa kết luận được gì
	}
	$gio  = max( 1, min( 24, (int) $c['gio'] ) );
	$lan  = strtotime( (string) $nk[0]['luc'] );
	return $lan && ( time() - $lan ) > 2 * $gio * HOUR_IN_SECONDS;
}

add_action( KHH_DT_THU_MOC, 'khh_dt_thu_cron' );
function khh_dt_thu_cron() {
	khh_dt_thu_lay();
}

/* ================================================================== *
 * Nhật ký
 * ================================================================== */

function khh_dt_thu_nhat_ky() {
	$nk = get_option( KHH_DT_THU_NHAT, array() );
	return is_array( $nk ) ? $nk : array();
}

function khh_dt_thu_ghi_nhat_ky( $dong ) {
	$nk = khh_dt_thu_nhat_ky();
	array_unshift( $nk, array_merge( array( 'luc' => current_time( 'mysql' ) ), $dong ) );
	update_option( KHH_DT_THU_NHAT, array_slice( $nk, 0, 50 ), false );
}

/** Mấy thư đã nạp rồi — để chạy lại không nạp trùng. */
function khh_dt_thu_da_nap() {
	$d = get_option( KHH_DT_THU_DA, array() );
	return is_array( $d ) ? $d : array();
}

function khh_dt_thu_danh_dau( $mid ) {
	$d = khh_dt_thu_da_nap();
	array_unshift( $d, (string) $mid );
	update_option( KHH_DT_THU_DA, array_slice( array_unique( $d ), 0, 300 ), false );
}

/* ================================================================== *
 * Khớp người gửi và tên tệp
 * ================================================================== */

/** Lấy phần `ai@đâu` trong một dòng From kiểu `Tên <ai@đâu>`. */
function khh_dt_thu_dia_chi( $from ) {
	if ( preg_match( '~<([^>]+)>~', (string) $from, $m ) ) {
		return strtolower( trim( $m[1] ) );
	}
	return strtolower( trim( (string) $from ) );
}

/**
 * Người gửi này có được phép không.
 *
 * 🔴 DANH SÁCH RỖNG LÀ CHỐI TẤT, KHÔNG PHẢI NHẬN TẤT. Hộp thư nào cũng nhận được thư rác, mà
 *    một tệp .csv đính kèm từ người lạ đi thẳng vào kho doanh thu thì không ai nhìn ra ngay.
 *    Mặc định phải là chối.
 */
function khh_dt_thu_nguoi_gui_hop_le( $from, $cho_phep ) {
	$dc = khh_dt_thu_dia_chi( $from );
	if ( '' === $dc ) {
		return false;
	}
	$ds = array_filter( array_map( 'trim', explode( ',', strtolower( (string) $cho_phep ) ) ) );
	if ( ! $ds ) {
		return false;
	}
	foreach ( $ds as $x ) {
		if ( 0 === strpos( $x, '@' ) ) {          // cả một tên miền: "@fabi.vn"
			if ( substr( $dc, -strlen( $x ) ) === $x ) {
				return true;
			}
			continue;
		}
		if ( $dc === $x ) {
			return true;
		}
	}
	return false;
}

/** Tên tệp có khớp mẫu không — mẫu kiểu `*.xlsx, bao-cao-*.csv`. */
function khh_dt_thu_ten_hop_le( $ten, $mau ) {
	$ten = strtolower( (string) $ten );
	$ds  = array_filter( array_map( 'trim', explode( ',', strtolower( (string) $mau ) ) ) );
	if ( ! $ds ) {
		return true;   // không đặt mẫu thì mọi tên đều qua — phần đuôi tệp vẫn còn gác ở dưới
	}
	foreach ( $ds as $x ) {
		$re = '~^' . str_replace( array( '\*', '\?' ), array( '.*', '.' ), preg_quote( $x, '~' ) ) . '$~';
		if ( preg_match( $re, $ten ) ) {
			return true;
		}
	}
	return false;
}

/** Đuôi tệp hệ đọc được — y hệt đường tải lên bằng tay. */
function khh_dt_thu_duoi_hop_le( $ten ) {
	$duoi = strtolower( pathinfo( (string) $ten, PATHINFO_EXTENSION ) );
	return in_array( $duoi, array( 'xlsx', 'xlsm', 'csv', 'tsv', 'txt' ), true );
}

/* ================================================================== *
 * Bóc tệp đính kèm khỏi một lá thư thô
 * ================================================================== */

/** Cắt một lá thư (hoặc một phần MIME) thành [đầu thư, thân]. */
function khh_dt_thu_cat( $tho ) {
	$i = strpos( $tho, "\r\n\r\n" );
	$n = 4;
	if ( false === $i ) {
		$i = strpos( $tho, "\n\n" );
		$n = 2;
	}
	if ( false === $i ) {
		return array( $tho, '' );
	}
	return array( substr( $tho, 0, $i ), substr( $tho, $i + $n ) );
}

/**
 * Đọc đầu thư thành mảng `tên thường => giá trị`.
 *
 * ⚠️ PHẢI NỐI DÒNG GẤP. Đầu thư dài được gấp xuống dòng sau rồi thụt vào bằng dấu cách hoặc
 *    tab (RFC 5322). Không nối lại thì một `Content-Disposition: attachment;` có `filename=`
 *    nằm ở dòng sau sẽ thành tệp KHÔNG TÊN — rồi bị bỏ qua, im lặng.
 */
function khh_dt_thu_doc_dau( $dau ) {
	$dau = str_replace( array( "\r\n\t", "\r\n ", "\n\t", "\n " ), ' ', (string) $dau );
	$ra  = array();
	foreach ( preg_split( '~\r?\n~', $dau ) as $d ) {
		$i = strpos( $d, ':' );
		if ( false === $i ) {
			continue;
		}
		$k = strtolower( trim( substr( $d, 0, $i ) ) );
		$v = trim( substr( $d, $i + 1 ) );
		$ra[ $k ] = isset( $ra[ $k ] ) ? $ra[ $k ] . ' ' . $v : $v;
	}
	return $ra;
}

/** Lấy một tham số trong dòng kiểu `…; name="a.xlsx"; charset=utf-8`. */
function khh_dt_thu_tham_so( $dong, $ten ) {
	$dong = (string) $dong;
	/* RFC 2231 trước: `filename*=UTF-8''b%C3%A1o.xlsx` — tên có dấu tiếng Việt đi lối này. */
	if ( preg_match( '~;\s*' . preg_quote( $ten, '~' ) . '\*\s*=\s*([^\';]*)\'[^\']*\'([^;]+)~i', $dong, $m ) ) {
		return rawurldecode( trim( $m[2] ) );
	}
	if ( preg_match( '~;\s*' . preg_quote( $ten, '~' ) . '\s*=\s*"([^"]*)"~i', $dong, $m ) ) {
		return khh_dt_thu_go_2047( $m[1] );
	}
	if ( preg_match( '~;\s*' . preg_quote( $ten, '~' ) . '\s*=\s*([^;\s]+)~i', $dong, $m ) ) {
		return khh_dt_thu_go_2047( $m[1] );
	}
	return '';
}

/**
 * Gỡ `=?UTF-8?B?…?=` trong đầu thư (RFC 2047).
 *
 * Tự gỡ chứ không gọi `mb_decode_mimeheader`/`iconv_mime_decode`: hai hàm ấy thuộc `mbstring`
 * và `iconv`, host nào thiếu là tên tệp tiếng Việt về nguyên cục `=?UTF-8?B?…?=` rồi trượt
 * phép khớp mẫu — mà không có gì báo, chỉ là "hệ không thấy thư nào".
 */
function khh_dt_thu_go_2047( $s ) {
	$s = (string) $s;
	if ( false === strpos( $s, '=?' ) ) {
		return $s;
	}
	return preg_replace_callback(
		'~=\?([^?]+)\?([bBqQ])\?([^?]*)\?=~',
		function ( $m ) {
			$noi = ( 'b' === strtolower( $m[2] ) )
				? (string) base64_decode( $m[3], true )   // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
				: quoted_printable_decode( str_replace( '_', ' ', $m[3] ) );
			$bo = strtoupper( $m[1] );
			if ( 'UTF-8' !== $bo && function_exists( 'iconv' ) ) {
				$thu = @iconv( $bo, 'UTF-8//IGNORE', $noi );   // phpcs:ignore WordPress.PHP.NoSilencedErrors
				if ( false !== $thu ) {
					$noi = $thu;
				}
			}
			return $noi;
		},
		$s
	);
}

/** Giải mã thân một phần MIME theo Content-Transfer-Encoding. */
function khh_dt_thu_giai( $than, $ma ) {
	$ma = strtolower( trim( (string) $ma ) );
	if ( 'base64' === $ma ) {
		return (string) base64_decode( preg_replace( '~\s+~', '', $than ) );   // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
	}
	if ( 'quoted-printable' === $ma ) {
		return quoted_printable_decode( $than );
	}
	return $than;
}

/**
 * Lấy mọi tệp đính kèm trong một lá thư thô.
 *
 * @return array [ [ 'ten' => …, 'noi' => nội dung nhị phân ], … ]
 */
function khh_dt_thu_dinh_kem( $tho, $sau = 0 ) {
	if ( $sau > 8 ) {
		return array();   // thư lồng thư lồng thư — dừng, đừng để nó tự ăn mình
	}
	list( $dau_tho, $than ) = khh_dt_thu_cat( $tho );
	$dau  = khh_dt_thu_doc_dau( $dau_tho );
	$ct   = isset( $dau['content-type'] ) ? $dau['content-type'] : 'text/plain';
	$cd   = isset( $dau['content-disposition'] ) ? $dau['content-disposition'] : '';
	$cte  = isset( $dau['content-transfer-encoding'] ) ? $dau['content-transfer-encoding'] : '';

	if ( 0 === stripos( trim( $ct ), 'multipart/' ) ) {
		$bien = khh_dt_thu_tham_so( $ct, 'boundary' );
		if ( '' === $bien ) {
			return array();
		}
		$ra = array();
		/* Tách theo vạch `--<boundary>`. Mẩu đầu là lời dẫn, mẩu cuối sau `--<boundary>--` là
		   lời bạt — cả hai đều không phải phần MIME, nên bỏ. */
		$manh = preg_split( '~\r?\n--' . preg_quote( $bien, '~' ) . '(--)?[^\n]*\r?\n?~', "\r\n" . $than );
		foreach ( array_slice( (array) $manh, 1 ) as $m ) {
			if ( '' === trim( $m ) ) {
				continue;
			}
			foreach ( khh_dt_thu_dinh_kem( $m, $sau + 1 ) as $x ) {
				$ra[] = $x;
			}
		}
		return $ra;
	}

	/* Tên tệp nằm ở `Content-Disposition: …; filename=` hoặc `Content-Type: …; name=`. Có bộ
	   gửi chỉ điền một trong hai, nên phải xem cả hai — thiếu một là rơi mất tệp, im lặng. */
	$ten = khh_dt_thu_tham_so( $cd, 'filename' );
	if ( '' === $ten ) {
		$ten = khh_dt_thu_tham_so( $ct, 'name' );
	}
	if ( '' === $ten ) {
		return array();   // không tên thì không phải tệp đính kèm
	}
	$noi = khh_dt_thu_giai( $than, $cte );
	if ( '' === $noi || strlen( $noi ) > KHH_DT_THU_CO_MAX ) {
		return array();
	}
	return array( array( 'ten' => $ten, 'noi' => $noi ) );
}

/* ================================================================== *
 * IMAP bằng socket
 * ================================================================== */

/**
 * Bộ nói chuyện IMAP tối giản — vừa đủ: đăng nhập, tìm thư chưa đọc, lấy thư, đánh dấu đã đọc.
 *
 * 🔴 CHỖ DỄ SAI NHẤT LÀ ĐỌC PHẢN HỒI, KHÔNG PHẢI GỬI LỆNH.
 *    IMAP trả nội dung dài dưới dạng "literal": cuối dòng có `{12345}` rồi 12345 byte THÔ theo
 *    sau — trong đó có cả xuống dòng, cả chuỗi trông y như một dòng lệnh. Cứ `fgets` từng dòng
 *    mà đọc thì nội dung thư sẽ bị hiểu nhầm thành phản hồi, và mọi lệnh sau đó lệch nhau một
 *    nhịp. Nên: thấy `{N}` là đọc ĐÚNG N byte, không đoán.
 */
class KHHDT_Imap {

	/* `protected` chứ không `private`: bài thử `kiem-hop-thu.php` cắm một cặp socket giả vào
	   đây để diễn lại nguyên một lượt đối đáp IMAP — gồm cả phản hồi có literal, chỗ dễ sai
	   nhất. Không có seam này thì phần đọc phản hồi chỉ còn cách thử trên máy chủ thư thật,
	   tức là không ai thử. */
	protected $s   = null;
	private $dem   = 0;
	private $loi   = '';
	private $cho   = 25;

	public function loi() {
		return $this->loi;
	}

	public function noi( $may, $cong, $bao_mat, $cho = 25 ) {
		$this->cho = max( 5, (int) $cho );
		$duong     = ( 'ssl' === $bao_mat ? 'ssl://' : 'tcp://' ) . $may . ':' . (int) $cong;
		$ctx       = stream_context_create(
			array(
				'ssl' => array(
					'verify_peer'       => true,
					'verify_peer_name'  => true,
					'SNI_enabled'       => true,
					'allow_self_signed' => false,
				),
			)
		);
		$eno = 0;
		$est = '';
		// phpcs:ignore WordPress.PHP.NoSilencedErrors
		$this->s = @stream_socket_client( $duong, $eno, $est, $this->cho, STREAM_CLIENT_CONNECT, $ctx );
		if ( ! $this->s ) {
			$this->loi = 'Không nối được tới ' . $may . ':' . (int) $cong . ' — ' . ( $est ? $est : 'hết giờ chờ' ) . '.';
			return false;
		}
		stream_set_timeout( $this->s, $this->cho );
		$chao = $this->doc_dong();
		if ( 0 !== strpos( $chao, '* OK' ) && 0 !== strpos( $chao, '* PREAUTH' ) ) {
			$this->loi = 'Máy chủ thư không chào đúng kiểu IMAP: ' . trim( substr( $chao, 0, 120 ) );
			return false;
		}
		if ( 'starttls' === $bao_mat ) {
			$r = $this->lenh( 'STARTTLS' );
			if ( ! $this->xong( $r ) ) {
				$this->loi = 'Máy chủ từ chối STARTTLS.';
				return false;
			}
			// phpcs:ignore WordPress.PHP.NoSilencedErrors
			if ( ! @stream_socket_enable_crypto( $this->s, true, STREAM_CRYPTO_METHOD_TLS_CLIENT ) ) {
				$this->loi = 'Bật mã hoá TLS không xong.';
				return false;
			}
		}
		return true;
	}

	public function dang_nhap( $nguoi, $mat_khau ) {
		$r = $this->lenh( 'LOGIN ' . $this->chuoi( $nguoi ) . ' ' . $this->chuoi( $mat_khau ) );
		if ( ! $this->xong( $r ) ) {
			/* 🔴 KHÔNG in lại phản hồi của máy chủ ở đây: có máy chủ nhắc lại cả dòng lệnh, mà
			   dòng ấy có mật khẩu — rồi nó vào thẳng nhật ký và màn quản trị. */
			$this->loi = 'Đăng nhập hộp thư không được. Xem lại địa chỉ và mật khẩu.';
			return false;
		}
		return true;
	}

	public function chon( $thu_muc ) {
		$r = $this->lenh( 'SELECT ' . $this->chuoi( $thu_muc ) );
		if ( ! $this->xong( $r ) ) {
			$this->loi = 'Không mở được thư mục "' . $thu_muc . '".';
			return false;
		}
		return true;
	}

	/** UID của mấy thư chưa đọc. */
	public function chua_doc() {
		$r = $this->lenh( 'UID SEARCH UNSEEN' );
		if ( ! $this->xong( $r ) ) {
			$this->loi = 'Tìm thư chưa đọc không xong.';
			return array();
		}
		$ds = array();
		foreach ( $r as $d ) {
			if ( preg_match( '~^\*\s+SEARCH\s+(.*)$~i', trim( $d ), $m ) ) {
				foreach ( preg_split( '~\s+~', trim( $m[1] ) ) as $x ) {
					if ( ctype_digit( $x ) ) {
						$ds[] = (int) $x;
					}
				}
			}
		}
		sort( $ds );
		return $ds;
	}

	/** Chỉ mấy dòng đầu thư — để lọc người gửi TRƯỚC khi tải cả lá thư vài chục MB về. */
	public function dau_thu( $uid ) {
		$r = $this->lenh( 'UID FETCH ' . (int) $uid . ' (BODY.PEEK[HEADER.FIELDS (FROM SUBJECT MESSAGE-ID DATE)])' );
		return $this->xong( $r ) ? $this->lay_literal( $r ) : '';
	}

	/** Cả lá thư, KHÔNG đánh dấu đã đọc (PEEK) — đánh dấu là việc làm sau, khi đã nạp xong. */
	public function ca_thu( $uid ) {
		$r = $this->lenh( 'UID FETCH ' . (int) $uid . ' (BODY.PEEK[])' );
		return $this->xong( $r ) ? $this->lay_literal( $r ) : '';
	}

	public function danh_dau_da_doc( $uid ) {
		return $this->xong( $this->lenh( 'UID STORE ' . (int) $uid . ' +FLAGS (\\Seen)' ) );
	}

	public function dong() {
		if ( $this->s ) {
			$this->lenh( 'LOGOUT' );
			// phpcs:ignore WordPress.WP.AlternativeFunctions
			@fclose( $this->s );   // phpcs:ignore WordPress.PHP.NoSilencedErrors
			$this->s = null;
		}
	}

	/* ---------------- phần trong ruột ---------------- */

	private function chuoi( $x ) {
		return '"' . str_replace( array( '\\', '"' ), array( '\\\\', '\\"' ), (string) $x ) . '"';
	}

	private function xong( $r ) {
		$cuoi = $r ? end( $r ) : '';
		return (bool) preg_match( '~^a\d+\s+OK~i', trim( (string) $cuoi ) );
	}

	/** Nội dung literal dài nhất trong phản hồi — chính là thân thư. */
	private function lay_literal( $r ) {
		$dai = '';
		foreach ( $r as $d ) {
			if ( preg_match( '~\{(\d+)\}\r?\n~', $d, $m, PREG_OFFSET_CAPTURE ) ) {
				$bd  = $m[0][1] + strlen( $m[0][0] );
				$noi = substr( $d, $bd, (int) $m[1][0] );
				if ( strlen( $noi ) > strlen( $dai ) ) {
					$dai = $noi;
				}
			}
		}
		return $dai;
	}

	private function doc_dong() {
		if ( ! $this->s ) {
			return '';
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions
		$d = fgets( $this->s, 8192 );
		if ( false === $d ) {
			return '';
		}
		/* `fgets` cắt ở 8192 byte, nên một dòng dài về thành nhiều mẩu — nối tới khi gặp xuống
		   dòng thật, không thì phép dò `{N}` ở cuối dòng sẽ trượt. */
		while ( '' !== $d && "\n" !== substr( $d, -1 ) ) {
			if ( $this->het_gio() ) {
				break;
			}
			// phpcs:ignore WordPress.WP.AlternativeFunctions
			$t = fgets( $this->s, 8192 );
			if ( false === $t ) {
				break;
			}
			$d .= $t;
		}
		return $d;
	}

	private function het_gio() {
		$m = stream_get_meta_data( $this->s );
		return ! empty( $m['timed_out'] ) || ! empty( $m['eof'] );
	}

	private function lenh( $lenh ) {
		if ( ! $this->s ) {
			return array();
		}
		$the = 'a' . ( ++$this->dem );
		// phpcs:ignore WordPress.WP.AlternativeFunctions
		fwrite( $this->s, $the . ' ' . $lenh . "\r\n" );
		$ra = array();
		while ( true ) {
			$d = $this->doc_dong();
			if ( '' === $d ) {
				break;
			}
			/* Literal: `{N}` ở cuối dòng thì N byte tiếp theo là nội dung THÔ, đọc đủ N rồi mới
			   đọc tiếp phần còn lại của dòng. Một dòng logic có thể có nhiều literal nối nhau. */
			while ( preg_match( '~\{(\d+)\}\r?\n$~', $d, $m ) ) {
				$n   = (int) $m[1];
				$noi = '';
				while ( strlen( $noi ) < $n ) {
					if ( $this->het_gio() ) {
						break 2;
					}
					// phpcs:ignore WordPress.WP.AlternativeFunctions
					$mau = fread( $this->s, min( 65536, $n - strlen( $noi ) ) );
					if ( false === $mau || '' === $mau ) {
						break 2;
					}
					$noi .= $mau;
				}
				$d .= $noi . $this->doc_dong();
			}
			$ra[] = $d;
			if ( 0 === strpos( $d, $the . ' ' ) ) {
				break;
			}
			if ( $this->het_gio() ) {
				break;
			}
		}
		return $ra;
	}
}

/* ================================================================== *
 * Vòng lấy thư
 * ================================================================== */

/**
 * Vào hộp thư, lấy tệp đính kèm hợp lệ, nạp vào kho.
 *
 * @return array Tóm tắt lượt chạy, và cũng là dòng ghi vào nhật ký.
 */
function khh_dt_thu_lay() {
	$c = khh_dt_thu_cf();
	if ( ! khh_dt_thu_du_cau_hinh() ) {
		$kq = array( 'xong' => false, 'loi' => 'Chưa đủ cấu hình hộp thư (máy chủ, địa chỉ, mật khẩu).' );
		khh_dt_thu_ghi_nhat_ky( $kq );
		return $kq;
	}

	$im = new KHHDT_Imap();
	if ( ! $im->noi( $c['may'], $c['cong'], $c['bao_mat'] )
		|| ! $im->dang_nhap( $c['nguoi'], khh_dt_thu_mat_khau() )
		|| ! $im->chon( $c['thu_muc'] ) ) {
		$kq = array( 'xong' => false, 'loi' => $im->loi() );
		$im->dong();
		khh_dt_thu_ghi_nhat_ky( $kq );
		return $kq;
	}

	$uids = array_slice( $im->chua_doc(), 0, KHH_DT_THU_MOI_LUOT );
	$nap  = array();
	$bo   = array();
	$da   = khh_dt_thu_da_nap();

	foreach ( $uids as $uid ) {
		$dau = khh_dt_thu_doc_dau( $im->dau_thu( $uid ) );
		$tu  = isset( $dau['from'] ) ? $dau['from'] : '';
		$mid = isset( $dau['message-id'] ) ? trim( $dau['message-id'] ) : '';

		if ( ! khh_dt_thu_nguoi_gui_hop_le( $tu, $c['nguoi_gui'] ) ) {
			/* 🔴 KHÔNG đánh dấu đã đọc. Thư của người lạ là việc của người, không phải của hệ —
			   đánh dấu đã đọc là hệ lặng lẽ giấu thư trong hộp thư của anh Thắng. */
			$bo[] = array( 'vi' => 'người gửi không nằm trong danh sách', 'tu' => khh_dt_thu_dia_chi( $tu ) );
			continue;
		}
		if ( '' !== $mid && in_array( $mid, $da, true ) ) {
			$im->danh_dau_da_doc( $uid );
			$bo[] = array( 'vi' => 'thư này đã nạp rồi', 'tu' => khh_dt_thu_dia_chi( $tu ) );
			continue;
		}

		$tho  = $im->ca_thu( $uid );
		$tep  = khh_dt_thu_dinh_kem( $tho );
		$xong = 0;
		foreach ( $tep as $t ) {
			$ten = sanitize_file_name( $t['ten'] );
			if ( ! khh_dt_thu_duoi_hop_le( $ten ) || ! khh_dt_thu_ten_hop_le( $ten, $c['mau_ten'] ) ) {
				continue;
			}
			$tam = trailingslashit( get_temp_dir() ) . 'khh-dt-thu-' . wp_generate_password( 12, false ) . '-' . $ten;
			if ( false === file_put_contents( $tam, $t['noi'] ) ) {   // phpcs:ignore WordPress.WP.AlternativeFunctions
				$bo[] = array( 'vi' => 'không ghi được file tạm', 'ten' => $ten );
				continue;
			}
			/* Đi qua ĐÚNG hàm mà đường tải lên bằng tay dùng — và chính nó xoá file tạm. */
			$r = khh_dt_nap_tep(
				$tam,
				$ten,
				$c['loai'],
				array( 'co_so' => $c['co_so'], 'tai_khoan' => $c['tai_khoan'] )
			);
			if ( file_exists( $tam ) ) {
				wp_delete_file( $tam );
			}
			if ( is_wp_error( $r ) ) {
				$bo[] = array( 'vi' => $r->get_error_message(), 'ten' => $ten );
				continue;
			}
			$nap[] = array(
				'ten'    => $ten,
				'tu'     => khh_dt_thu_dia_chi( $tu ),
				'da_ghi' => isset( $r['da_ghi'] ) ? (int) $r['da_ghi'] : 0,
			);
			$xong++;
		}

		if ( $xong ) {
			$im->danh_dau_da_doc( $uid );
			if ( '' !== $mid ) {
				khh_dt_thu_danh_dau( $mid );
			}
		} elseif ( ! $tep ) {
			$bo[] = array( 'vi' => 'thư không có tệp đính kèm', 'tu' => khh_dt_thu_dia_chi( $tu ) );
		}
	}

	$im->dong();
	$kq = array(
		'xong'   => true,
		'xem'    => count( $uids ),
		'nap'    => $nap,
		'bo'     => $bo,
		'so_nap' => count( $nap ),
	);
	khh_dt_thu_ghi_nhat_ky( $kq );
	return $kq;
}

/* ================================================================== *
 * REST
 * ================================================================== */

/** Cấu hình có mật khẩu hộp thư, nên chỉ quản trị viên — không mở theo quyền nạp file. */
function khh_dt_thu_duoc_chinh() {
	return current_user_can( 'manage_options' );
}

add_action( 'rest_api_init', 'khh_dt_thu_route' );
function khh_dt_thu_route() {
	register_rest_route(
		'khh-dt/v1',
		'/hop-thu',
		array(
			array(
				'methods'             => 'GET',
				'callback'            => 'khh_dt_rest_thu_xem',
				'permission_callback' => 'khh_dt_thu_duoc_chinh',
			),
			array(
				'methods'             => 'POST',
				'callback'            => 'khh_dt_rest_thu_luu',
				'permission_callback' => 'khh_dt_thu_duoc_chinh',
			),
		)
	);
	register_rest_route(
		'khh-dt/v1',
		'/hop-thu-chay',
		array(
			'methods'             => 'POST',
			'callback'            => 'khh_dt_rest_thu_chay',
			'permission_callback' => 'khh_dt_thu_duoc_chinh',
		)
	);
}

function khh_dt_rest_thu_xem() {
	$c = khh_dt_thu_cf();
	/* 🔴 MẬT KHẨU KHÔNG ĐI NGƯỢC RA TRÌNH DUYỆT — chỉ nói ĐÃ ĐẶT hay CHƯA, và đặt ở đâu. */
	unset( $c['mat_khau'] );
	$c['co_mat_khau']   = '' !== khh_dt_thu_mat_khau();
	$c['khoa_o_config'] = defined( 'KHH_DT_MAIL_PASS' ) && '' !== (string) constant( 'KHH_DT_MAIL_PASS' );
	$sau                = wp_next_scheduled( KHH_DT_THU_MOC );
	return array(
		'cf'       => $c,
		/* Đường dẫn wp-cron.php do MÁY CHỦ đưa ra, không để màn hình tự ghép từ địa chỉ trang:
		   site cài trong thư mục con, hay chạy sau proxy, là ghép ra đường sai — mà người ta
		   dán thẳng vào Cron Jobs bên hosting rồi tưởng đã xong. */
		'cron_url' => site_url( 'wp-cron.php' ),
		'lan_sau'  => $sau ? gmdate( 'c', $sau ) : '',
		'qua_han'  => khh_dt_thu_qua_han(),
		'nhat_ky'  => khh_dt_thu_nhat_ky(),
	);
}

function khh_dt_rest_thu_luu( $req ) {
	$cu  = khh_dt_thu_cf();
	$moi = $cu;
	foreach ( array( 'may', 'nguoi', 'thu_muc', 'nguoi_gui', 'mau_ten', 'co_so', 'tai_khoan' ) as $k ) {
		if ( null !== $req->get_param( $k ) ) {
			$moi[ $k ] = sanitize_text_field( (string) $req->get_param( $k ) );
		}
	}
	if ( null !== $req->get_param( 'cong' ) ) {
		$moi['cong'] = max( 1, min( 65535, (int) $req->get_param( 'cong' ) ) );
	}
	if ( null !== $req->get_param( 'bao_mat' ) ) {
		$bm             = (string) $req->get_param( 'bao_mat' );
		$moi['bao_mat'] = in_array( $bm, array( 'ssl', 'starttls', 'khong' ), true ) ? $bm : 'ssl';
	}
	if ( null !== $req->get_param( 'loai' ) ) {
		$l           = (string) $req->get_param( 'loai' );
		$moi['loai'] = in_array( $l, array( 'pos', 'sao_ke', 'momo_pos', 'momo_sk', 'bes' ), true ) ? $l : 'pos';
	}
	if ( null !== $req->get_param( 'gio' ) ) {
		/* Kẹp trong 1–24. Không kẹp thì gõ nhầm số 0 là lịch chạy liên tục, mỗi lượt lại nối
		   IMAP — máy chủ thư khoá địa chỉ vì nghi dò mật khẩu, rồi cả hệ tắc mà không rõ vì sao. */
		$moi['gio'] = max( 1, min( 24, (int) $req->get_param( 'gio' ) ) );
	}
	if ( null !== $req->get_param( 'bat' ) ) {
		$moi['bat'] = (bool) $req->get_param( 'bat' );
	}
	/* Mật khẩu: chỉ ghi khi có gõ MỚI. Gửi lên chuỗi rỗng là "giữ nguyên", không phải "xoá" —
	   màn hình không bao giờ nhận được mật khẩu cũ nên nó không có gì để gửi lại. */
	$mk = $req->get_param( 'mat_khau' );
	if ( null !== $mk && '' !== (string) $mk ) {
		$moi['mat_khau'] = (string) $mk;
	}

	update_option( KHH_DT_THU_OPT, $moi, false );
	khh_dt_thu_dat_lich();
	return khh_dt_rest_thu_xem();
}

function khh_dt_rest_thu_chay() {
	$kq = khh_dt_thu_lay();
	return array( 'chay' => $kq, 'xem' => khh_dt_rest_thu_xem() );
}
