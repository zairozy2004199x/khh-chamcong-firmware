<?php
/**
 * MÀN THƯ VIỆN HỢP ĐỒNG — chạy thẳng trên host, không gọi app gốc lúc đọc.
 *
 * Anh Thắng 02/09/2026: *"có thể đẩy thư viện hợp đồng lên và chạy nội dung trên đó được không"*
 * — nguồn Drive + Sheet, và web cần **kho + tìm + xem/tải**.
 *
 * 🔴 MÀN NÀY KHÔNG GỌI APP GỐC MỘT LẦN NÀO KHI ĐỌC. Trang `/hop-dong/` cũ mỗi lần mở là một
 *    chuyến ra Apps Script, và Apps Script thì chậm (một lệnh 2–10 giây, bóc PDF thì tính bằng
 *    phút) lại hay đòi đăng nhập Google khi deploy lệch. Kho nằm trên MySQL của chính host nên
 *    mở là có ngay, và mở được cả khi Google hỏng.
 *
 * 🔴 KHÔNG CÓ MỘT Ô SỬA NÀO, VÀ ĐÓ LÀ THIẾT KẾ. Sheet vẫn là nguồn; đây là bản sao ĐỌC (xem ba
 *    luật cứng ở đầu `VHD_Kho`). Mở đường sửa ở đây là sinh ra nguồn thứ hai, rồi sớm muộn hai
 *    bên lệch nhau mà không ai nói được bên nào đúng.
 *
 * ⚠️ TRANG NÀY KHÔNG CÓ MỘT DÒNG SCRIPT NÀO. Toàn bộ lọc/phân trang đi bằng biểu mẫu GET. Chậm
 *    hơn một chút, nhưng chạy được trên mọi máy trong cửa hàng, in ra được, và không có chỗ nào
 *    để một lỗi JavaScript làm trắng màn hình.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHD_ManKho {

	const COOKIE = 'vhd_kho_tok';

	/* ==================================================================== cổng */

	private static function toi() {
		$tok = isset( $_COOKIE[ self::COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) : '';
		if ( '' === $tok ) { return null; }
		return VHD_Auth::user_by_token( $tok );
	}

	private static function url( $them = array() ) {
		$u = VHD_Trang::url();
		$u = add_query_arg( 'vhd_kho', '1', $u );
		return $them ? add_query_arg( $them, $u ) : $u;
	}

	public static function chay() {
		$bao = array();

		/* ---- Đăng nhập / thoát: xử TRƯỚC khi vẽ gì, vì cả hai đều đặt cookie ---- */
		if ( isset( $_POST['vhd_kho_viec'] ) && 'vao' === $_POST['vhd_kho_viec'] ) {
			$pin = isset( $_POST['pin'] ) ? trim( (string) wp_unslash( $_POST['pin'] ) ) : '';
			$r   = VHD_Auth::login( $pin );
			if ( ! empty( $r['ok'] ) ) {
				/* 🔴 KHÔNG BAO GIỜ IN PIN RA MÀN HÌNH hay nhét vào địa chỉ. Trang chạy ngoài
				   internet; một ảnh chụp màn hình là mất mật khẩu của cả chuỗi. */
				setcookie( self::COOKIE, (string) $r['token'], time() + VHD_Auth::TTL, '/', '', is_ssl(), true );
				wp_safe_redirect( self::url() );
				exit;
			}
			$bao[] = array( 'loi' => isset( $r['error'] ) ? $r['error'] : 'PIN không đúng.' );
		}
		if ( isset( $_POST['vhd_kho_viec'] ) && 'thoat' === $_POST['vhd_kho_viec'] ) {
			$tok = isset( $_COOKIE[ self::COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) : '';
			if ( '' !== $tok ) { VHD_Auth::logout( $tok ); }
			setcookie( self::COOKIE, '', time() - 3600, '/', '', is_ssl(), true );
			wp_safe_redirect( self::url() );
			exit;
		}

		$toi = self::toi();
		if ( ! $toi ) { self::trang_vao( $bao ); return; }

		/* ---- Việc của người quản trị ---- */
		if ( isset( $_POST['vhd_kho_viec'] ) ) {
			$viec = sanitize_text_field( wp_unslash( $_POST['vhd_kho_viec'] ) );
			if ( 'khai_cot' === $viec ) {
				if ( ! VHD_Kho::duoc_quan( $toi ) ) {
					$bao[] = array( 'loi' => 'Khai cột cần vai Admin hoặc Quản lý.' );
				} else {
					$o = isset( $_POST['ax'] ) ? (array) wp_unslash( $_POST['ax'] ) : array();
					VHD_Kho::luu_anh_xa( $o );
					$bao[] = array( 'xong' => 'Đã lưu khai cột. Bấm "Xem trước" để đối chiếu rồi mới kéo thật.' );
				}
			}
			if ( 'keo' === $viec || 'keo_that' === $viec ) {
				$fn = isset( $_POST['ham'] ) ? sanitize_text_field( wp_unslash( $_POST['ham'] ) ) : 'getData';
				if ( '' === $fn ) { $fn = 'getData'; }
				update_option( 'vhd_ham_lay', $fn );
				$r = VHD_Kho::keo( $toi, ( 'keo' === $viec ), $fn );
				$bao[] = self::ke_keo( $r );
			}
		}

		self::trang_kho( $toi, $bao );
	}

	private static function ke_keo( $r ) {
		if ( empty( $r['ok'] ) ) { return array( 'loi' => isset( $r['error'] ) ? $r['error'] : 'Không kéo được.' ); }
		$c = array();
		$c[] = 'Đọc được <b>' . (int) $r['so_dong'] . ' dòng</b> · ' . (int) $r['so_cot'] . ' cột.';
		if ( $r['thieu_ma'] )  { $c[] = '<b>' . (int) $r['thieu_ma'] . '</b> dòng KHÔNG có mã hợp đồng.'; }
		if ( $r['thieu_het'] ) { $c[] = '<b>' . (int) $r['thieu_het'] . '</b> dòng không đọc được ngày hết hạn.'; }
		if ( ! empty( $r['ma_trung'] ) ) {
			$c[] = 'Mã TRÙNG: ' . esc_html( implode( ' · ', array_slice( $r['ma_trung'], 0, 10 ) ) )
				. ( count( $r['ma_trung'] ) > 10 ? ' …' : '' );
		}
		if ( ! empty( $r['chi_xem'] ) ) {
			$c[] = '<b>Chưa ghi gì cả</b> — đây là bản xem trước. Đối chiếu năm dòng mẫu bên dưới, '
				. 'thấy đúng thì bấm <b>Kéo thật</b>.';
			return array( 'xem' => implode( ' ', $c ), 'r' => $r );
		}
		$c[] = 'Đã ghi <b>' . (int) $r['da_ghi'] . '</b> hợp đồng vào kho, thay toàn bộ bản cũ.';
		return array( 'xong' => implode( ' ', $c ) );
	}

	/* ================================================================== vẽ trang */

	private static function dau( $tieu ) {
		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		echo '<!DOCTYPE html><html lang="vi"><head><meta charset="UTF-8">';
		echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
		echo '<meta name="robots" content="noindex, nofollow">';
		echo '<title>' . esc_html( $tieu ) . '</title>';
		echo '<style>'
			. ':root{--nen:#f1f5f9;--the:#fff;--vien:#e2e8f0;--chu:#0f172a;--mo:#64748b;--xanh:#2563eb;--do:#dc2626;--vang:#f59e0b;--luc:#16a34a}'
			. '*{box-sizing:border-box}body{margin:0;font:15px/1.6 -apple-system,"Segoe UI",Roboto,Arial,sans-serif;background:var(--nen);color:var(--chu)}'
			. '.bo{max-width:1400px;margin:0 auto;padding:16px 20px}'
			. 'header{background:var(--the);border-bottom:1px solid var(--vien)}'
			. 'header .bo{display:flex;align-items:center;gap:12px;flex-wrap:wrap;padding:12px 20px}'
			. 'h1{font-size:18px;margin:0;flex:1}'
			. '.the{background:var(--the);border:1px solid var(--vien);border-radius:10px;padding:16px;margin:0 0 16px}'
			. 'table{border-collapse:collapse;width:100%;font-size:13.5px}'
			. 'th,td{border:1px solid var(--vien);padding:6px 8px;text-align:left;vertical-align:top}'
			. 'th{background:#f8fafc;font-size:12px;text-transform:uppercase;letter-spacing:.4px}'
			. '.cuon{overflow-x:auto;max-width:100%;border:1px solid var(--vien);border-radius:8px}'
			. '.cuon table{border:0}.cuon th,.cuon td{border-left:0;border-top:0}'
			. '.mo{color:var(--mo)}.r{text-align:right}'
			. '.k{display:inline-block;padding:1px 7px;border-radius:99px;font-size:11.5px;font-weight:700}'
			. '.k-do{background:#fee2e2;color:#b91c1c}.k-vang{background:#fef3c7;color:#92400e}.k-luc{background:#dcfce7;color:#166534}'
			. '.bao{border:1px solid var(--vien);border-left:4px solid var(--xanh);background:#f8fafc;padding:10px 12px;border-radius:8px;margin:0 0 12px}'
			. '.bao.loi{border-left-color:var(--do);background:#fef2f2}'
			. '.bao.xong{border-left-color:var(--luc);background:#f0fdf4}'
			. '.bao.xem{border-left-color:var(--vang);background:#fffbeb}'
			. 'input,select,button{font:inherit;padding:7px 10px;border:1px solid var(--vien);border-radius:8px;background:#fff}'
			. 'button{cursor:pointer}button.chinh{background:var(--xanh);color:#fff;border-color:var(--xanh);font-weight:700}'
			. '.hang{display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end}'
			. '.hang label{display:block;font-size:12px;color:var(--mo);margin-bottom:3px}'
			. 'a{color:var(--xanh)}details>summary{cursor:pointer}'
			. '</style></head><body>';
	}

	private static function cuoi() {
		echo '<div class="bo mo" style="font-size:12px;padding-top:0">Thư viện hợp đồng · bản '
			. esc_html( VHD_VERSION ) . ' · dữ liệu là <b>bản sao đọc</b> của Google Sheet, '
			. 'sửa thì sửa bên app gốc rồi kéo lại.</div></body></html>';
	}

	private static function ve_bao( $bao ) {
		foreach ( (array) $bao as $b ) {
			if ( isset( $b['loi'] ) )  { echo '<div class="bao loi">' . wp_kses_post( $b['loi'] ) . '</div>'; }
			if ( isset( $b['xong'] ) ) { echo '<div class="bao xong">' . wp_kses_post( $b['xong'] ) . '</div>'; }
			if ( isset( $b['xem'] ) )  {
				echo '<div class="bao xem">' . wp_kses_post( $b['xem'] ) . '</div>';
				if ( ! empty( $b['r']['xem_thu'] ) ) { self::ve_xem_thu( $b['r'] ); }
			}
		}
	}

	/**
	 * NĂM DÒNG MẪU — thứ quyết định người ta có bấm "Kéo thật" hay không.
	 *
	 * ⚠️ Bày cả Ô GỐC lẫn Ô ĐÃ HIỂU cạnh nhau. Chỉ bày ô đã hiểu thì đọc nhầm ngày (03/09 thành
	 *    09/03) trông vẫn hợp lệ; đặt cạnh chuỗi gốc thì sai lộ ra ngay.
	 */
	private static function ve_xem_thu( $r ) {
		echo '<div class="cuon" style="margin:0 0 12px"><table><thead><tr>'
			. '<th>#</th><th>Mã</th><th>Tên</th><th>Cơ sở</th><th>Ngày ký</th><th>Ngày hết hạn</th>'
			. '<th class="r">Tiền</th><th>Tệp</th></tr></thead><tbody>';
		foreach ( $r['xem_thu'] as $h ) {
			echo '<tr><td>' . (int) $h['hang'] . '</td>';
			echo '<td><b>' . esc_html( $h['ma'] ) . '</b></td>';
			echo '<td>' . esc_html( mb_substr( (string) $h['ten'], 0, 80, 'UTF-8' ) ) . '</td>';
			echo '<td>' . esc_html( $h['coso'] ) . '</td>';
			echo '<td>' . ( $h['ngay_ky'] ? esc_html( $h['ngay_ky'] ) : '<span class="mo">—</span>' ) . '</td>';
			echo '<td>' . ( $h['ngay_het'] ? esc_html( $h['ngay_het'] ) : '<span class="mo">—</span>' ) . '</td>';
			echo '<td class="r">' . esc_html( number_format_i18n( (int) $h['tien'] ) ) . '</td>';
			echo '<td>' . ( '' !== $h['link'] ? 'có' : '<span class="mo">—</span>' ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
	}

	/* =================================================================== cổng PIN */

	private static function trang_vao( $bao ) {
		self::dau( 'Thư viện hợp đồng' );
		echo '<header><div class="bo"><h1>📚 Thư viện hợp đồng</h1></div></header>';
		echo '<div class="bo" style="max-width:460px">';
		self::ve_bao( $bao );
		echo '<div class="the"><form method="post">'
			. '<input type="hidden" name="vhd_kho_viec" value="vao">'
			. '<label for="pin" style="display:block;font-size:12px;color:var(--mo);margin-bottom:4px">Mã PIN</label>'
			/* Ô PIN KHÔNG bao giờ điền sẵn, và `autocomplete="off"` để máy dùng chung không nhớ hộ. */
			. '<input id="pin" name="pin" type="password" inputmode="numeric" autocomplete="off" '
			. 'style="width:100%" required>'
			. '<p><button class="chinh" style="width:100%">Vào</button></p>'
			. '<p class="mo" style="font-size:12.5px;margin:0">Dùng chung PIN với app Vận hành chi phí. '
			. 'Quên PIN thì nhờ kế toán hoặc quản lý cấp lại.</p>'
			. '</form></div></div>';
		self::cuoi();
	}

	/* ================================================================= màn chính */

	private static function trang_kho( $toi, $bao ) {
		$id = isset( $_GET['hd'] ) ? (int) $_GET['hd'] : 0;
		self::dau( 'Thư viện hợp đồng' );

		echo '<header><div class="bo"><h1>📚 Thư viện hợp đồng</h1>';
		echo '<span class="mo">' . esc_html( isset( $toi['name'] ) ? $toi['name'] : '' )
			. ' · ' . esc_html( isset( $toi['role'] ) ? $toi['role'] : '' ) . '</span>';
		echo '<a href="' . esc_url( VHD_Trang::url() ) . '">App gốc ↗</a>';
		echo '<form method="post" style="margin:0"><input type="hidden" name="vhd_kho_viec" value="thoat">'
			. '<button>Thoát</button></form>';
		echo '</div></header><div class="bo">';

		self::ve_bao( $bao );

		if ( $id > 0 ) { self::the_chi_tiet( $id ); }
		else           { self::the_danh_sach(); }

		if ( VHD_Kho::duoc_quan( $toi ) ) { self::the_quan_tri(); }

		echo '</div>';
		self::cuoi();
	}

	private static function the_danh_sach() {
		$loc = array(
			'q'         => isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '',
			'coso'      => isset( $_GET['cs'] ) ? sanitize_text_field( wp_unslash( $_GET['cs'] ) ) : '',
			'het_truoc' => isset( $_GET['ht'] ) ? sanitize_text_field( wp_unslash( $_GET['ht'] ) ) : '',
			'trang'     => isset( $_GET['tr'] ) ? (int) $_GET['tr'] : 1,
		);
		$kq  = VHD_Kho::tim( $loc );
		$lan = VHD_Kho::lan_keo();

		echo '<div class="the"><form method="get" class="hang">';
		if ( ! get_option( 'permalink_structure' ) ) { echo '<input type="hidden" name="vhd" value="app">'; }
		echo '<input type="hidden" name="vhd_kho" value="1">';
		echo '<div><label for="q">Tìm (mã · tên · bên ký · cả nội dung cột khác)</label>'
			. '<input id="q" name="q" style="min-width:280px" value="' . esc_attr( $loc['q'] ) . '"></div>';
		echo '<div><label for="cs">Cơ sở</label><select id="cs" name="cs"><option value="">— tất cả —</option>';
		foreach ( VHD_Kho::ds_coso() as $c ) {
			echo '<option value="' . esc_attr( $c ) . '"' . selected( $c, $loc['coso'], false ) . '>'
				. esc_html( $c ) . '</option>';
		}
		echo '</select></div>';
		echo '<div><label for="ht">Hết hạn trước ngày</label>'
			. '<input id="ht" name="ht" type="date" value="' . esc_attr( $loc['het_truoc'] ) . '"></div>';
		echo '<div><button class="chinh">Tìm</button></div>';
		echo '<div><a href="' . esc_url( self::url() ) . '">Bỏ lọc</a></div>';
		echo '</form>';

		echo '<p class="mo" style="margin:10px 0 0">Kho đang giữ <b>' . (int) VHD_Kho::dem()
			. '</b> hợp đồng';
		if ( ! empty( $lan['luc'] ) ) {
			echo ' · kéo về lần cuối <b>' . esc_html( $lan['luc'] ) . '</b>'
				. ( ! empty( $lan['boi'] ) ? ' bởi ' . esc_html( $lan['boi'] ) : '' );
		}
		echo '. Tìm được <b>' . (int) $kq['tong'] . '</b>.</p></div>';

		if ( ! $kq['ds'] ) {
			echo '<div class="the"><p class="mo">Không có hợp đồng nào khớp. '
				. ( VHD_Kho::dem() ? 'Thử bỏ bớt bộ lọc.' : 'Kho còn trống — kéo về ở khối cuối màn.' )
				. '</p></div>';
			return;
		}

		$nay = current_time( 'Y-m-d' );
		echo '<div class="the"><div class="cuon"><table><thead><tr>'
			. '<th>Mã</th><th>Tên hợp đồng</th><th>Cơ sở</th><th>Bên ký</th>'
			. '<th>Ngày ký</th><th>Hết hạn</th><th class="r">Tiền</th><th></th>'
			. '</tr></thead><tbody>';
		foreach ( $kq['ds'] as $h ) {
			echo '<tr>';
			echo '<td><b>' . esc_html( $h['ma'] ) . '</b></td>';
			echo '<td>' . esc_html( mb_substr( (string) $h['ten'], 0, 120, 'UTF-8' ) ) . '</td>';
			echo '<td>' . esc_html( $h['coso'] ) . '</td>';
			$ben = trim( (string) $h['ben_a'] . ( '' !== $h['ben_b'] ? ' — ' . $h['ben_b'] : '' ), ' —' );
			echo '<td>' . esc_html( $ben ) . '</td>';
			echo '<td>' . ( $h['ngay_ky'] ? esc_html( $h['ngay_ky'] ) : '<span class="mo">—</span>' ) . '</td>';
			echo '<td>' . self::nhan_het( $h['ngay_het'], $nay ) . '</td>';
			echo '<td class="r">' . ( (int) $h['tien'] ? esc_html( number_format_i18n( (int) $h['tien'] ) )
				: '<span class="mo">—</span>' ) . '</td>';
			echo '<td><a href="' . esc_url( self::url( array( 'hd' => (int) $h['id'] ) ) ) . '">Chi tiết</a>';
			if ( '' !== trim( (string) $h['link'] ) ) {
				echo ' · <a href="' . esc_url( $h['link'] ) . '" target="_blank" rel="noopener">Tệp ↗</a>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table></div>';

		if ( $kq['so_trang'] > 1 ) {
			echo '<p style="margin:12px 0 0">Trang ' . (int) $kq['trang'] . '/' . (int) $kq['so_trang'] . ' ';
			$giu = array( 'q' => $loc['q'], 'cs' => $loc['coso'], 'ht' => $loc['het_truoc'] );
			if ( $kq['trang'] > 1 ) {
				echo '<a href="' . esc_url( self::url( array_merge( $giu, array( 'tr' => $kq['trang'] - 1 ) ) ) ) . '">← Trước</a> ';
			}
			if ( $kq['trang'] < $kq['so_trang'] ) {
				echo '<a href="' . esc_url( self::url( array_merge( $giu, array( 'tr' => $kq['trang'] + 1 ) ) ) ) . '">Sau →</a>';
			}
			echo '</p>';
		}
		echo '</div>';
	}

	/**
	 * NHÃN NGÀY HẾT HẠN — đỏ khi đã hết, vàng khi còn dưới 60 ngày.
	 *
	 * 🔴 Đây là lý do người ta mở kho. Một cột ngày trần thì phải tự trừ trong đầu cho từng dòng;
	 *    tô màu thì liếc một cái là thấy cái nào cần gọi điện tuần này.
	 */
	private static function nhan_het( $ngay, $nay ) {
		$ngay = trim( (string) $ngay );
		if ( '' === $ngay || '0000-00-00' === $ngay ) { return '<span class="mo">—</span>'; }
		$con = (int) floor( ( strtotime( $ngay ) - strtotime( $nay ) ) / 86400 );
		if ( $con < 0 )  { return '<span class="k k-do">' . esc_html( $ngay ) . ' · đã hết</span>'; }
		if ( $con <= 60 ) { return '<span class="k k-vang">' . esc_html( $ngay ) . ' · còn ' . $con . ' ngày</span>'; }
		return esc_html( $ngay ) . ' <span class="mo">· còn ' . $con . ' ngày</span>';
	}

	/* ================================================================== chi tiết */

	private static function the_chi_tiet( $id ) {
		$h = VHD_Kho::mot( $id );
		if ( ! $h ) {
			echo '<div class="the"><p>Không có hợp đồng số ' . (int) $id . ' trong kho. '
				. '<a href="' . esc_url( self::url() ) . '">← Về danh sách</a></p></div>';
			return;
		}
		echo '<div class="the"><p style="margin:0 0 10px"><a href="' . esc_url( self::url() ) . '">← Về danh sách</a></p>';
		echo '<h2 style="margin:0 0 4px">' . esc_html( '' !== $h['ma'] ? $h['ma'] : ( '#' . (int) $h['id'] ) ) . '</h2>';
		echo '<p class="mo" style="margin:0 0 12px">' . esc_html( (string) $h['ten'] ) . '</p>';
		if ( '' !== trim( (string) $h['link'] ) ) {
			echo '<p><a class="k k-luc" style="padding:6px 12px;text-decoration:none" href="'
				. esc_url( $h['link'] ) . '" target="_blank" rel="noopener">📄 Mở tệp hợp đồng ↗</a></p>';
		} else {
			echo '<p class="mo">Dòng này chưa có đường dẫn tệp — khai cột "Đường dẫn tệp (Drive)" '
				. 'rồi kéo lại thì nút mở tệp sẽ có.</p>';
		}

		echo '<div class="cuon"><table><tbody>';
		$nay = current_time( 'Y-m-d' );
		$cap = array(
			'Cơ sở'    => esc_html( $h['coso'] ),
			'Bên A'    => esc_html( $h['ben_a'] ),
			'Bên B'    => esc_html( $h['ben_b'] ),
			'Ngày ký'  => $h['ngay_ky'] ? esc_html( $h['ngay_ky'] ) : '<span class="mo">—</span>',
			'Hết hạn'  => self::nhan_het( $h['ngay_het'], $nay ),
			'Tiền'     => esc_html( number_format_i18n( (int) $h['tien'] ) ),
		);
		foreach ( $cap as $k => $v ) {
			echo '<tr><th style="width:180px">' . esc_html( $k ) . '</th><td>' . wp_kses_post( $v ) . '</td></tr>';
		}
		echo '</tbody></table></div>';

		/* 🔴 BÀY TRỌN DÒNG GỐC. Ánh xạ chỉ lấy chín trường để tìm/lọc; mọi cột còn lại của Sheet
		   vẫn ở đây, nguyên văn — nhờ vậy kho không bao giờ "mất" thông tin so với bản gốc, và
		   đối chiếu được từng ô khi nghi ngờ. */
		$goc = json_decode( (string) $h['du_lieu'], true );
		if ( is_array( $goc ) && $goc ) {
			echo '<details style="margin-top:14px" open><summary><b>Toàn bộ dòng gốc từ Sheet ('
				. count( $goc ) . ' cột)</b></summary>';
			echo '<div class="cuon" style="margin-top:8px"><table><tbody>';
			foreach ( $goc as $k => $v ) {
				$v = is_array( $v ) ? wp_json_encode( $v, JSON_UNESCAPED_UNICODE ) : (string) $v;
				if ( '' === trim( $v ) ) { continue; }
				echo '<tr><th style="width:240px">' . esc_html( (string) $k ) . '</th>';
				if ( preg_match( '#^https?://#i', trim( $v ) ) ) {
					echo '<td><a href="' . esc_url( trim( $v ) ) . '" target="_blank" rel="noopener">'
						. esc_html( mb_substr( trim( $v ), 0, 120, 'UTF-8' ) ) . ' ↗</a></td>';
				} else {
					echo '<td>' . nl2br( esc_html( $v ) ) . '</td>';
				}
				echo '</tr>';
			}
			echo '</tbody></table></div></details>';
		}
		echo '</div>';
	}

	/* ================================================================ quản trị */

	private static function the_quan_tri() {
		$ax  = VHD_Kho::anh_xa();
		$fn  = (string) get_option( 'vhd_ham_lay', 'getData' );
		$chua = 0;
		foreach ( $ax as $v ) { if ( '' === $v ) { $chua++; } }

		echo '<div class="the"><details' . ( VHD_Kho::dem() ? '' : ' open' ) . '><summary>'
			. '<b>⚙️ Khai cột &amp; kéo thư viện về host</b> <span class="mo">('
			. ( $chua ? $chua . ' trường chưa khai' : 'đã khai đủ' ) . ' · bấm để mở)</span></summary>';

		echo '<p class="mo" style="margin:10px 0">Kho trên host là <b>bản sao đọc</b> của Google Sheet. '
			. 'Mỗi lần kéo là <b>chép lại toàn bộ</b> — không có đồng bộ từng phần, vì đồng bộ từng phần '
			. 'phải trả lời được "dòng bị xoá bên Sheet thì bên này xử sao", và trả lời sai một lần là '
			. 'kho giữ lại hợp đồng đã bỏ.</p>';
		echo '<div class="bao">Khai cột là <b>chỉ tên cột trong Sheet</b>, gõ đúng y như tiêu đề bên ấy. '
			. 'Để trống thì trường đó bỏ qua (vẫn xem được trong <i>dòng gốc</i>, chỉ là không tìm/lọc theo được). '
			. 'Khai một tên KHÔNG có trong Sheet thì lượt kéo bị <b>chối hẳn</b> — chứ không lặng lẽ để trống.</div>';

		echo '<form method="post"><input type="hidden" name="vhd_kho_viec" value="khai_cot">';
		echo '<div class="cuon"><table><thead><tr><th style="width:230px">Trường của kho</th>'
			. '<th>Tên cột trong Sheet</th></tr></thead><tbody>';
		foreach ( VHD_Kho::TRUONG as $k => $nhan ) {
			echo '<tr><th>' . esc_html( $nhan ) . '</th>';
			echo '<td><label class="mo" style="display:none" for="ax_' . esc_attr( $k ) . '">'
				. esc_html( $nhan ) . '</label>'
				. '<input id="ax_' . esc_attr( $k ) . '" name="ax[' . esc_attr( $k ) . ']" style="width:100%" '
				. 'value="' . esc_attr( $ax[ $k ] ) . '"></td></tr>';
		}
		echo '</tbody></table></div>';
		echo '<p><button class="chinh">Lưu khai cột</button></p></form>';

		echo '<form method="post" class="hang" style="margin-top:6px">';
		echo '<div><label for="ham">Hàm lấy dữ liệu của app gốc</label>'
			. '<input id="ham" name="ham" value="' . esc_attr( $fn ) . '" style="min-width:200px"></div>';
		echo '<div><button name="vhd_kho_viec" value="keo" class="chinh">Xem trước</button></div>';
		echo '<div><button name="vhd_kho_viec" value="keo_that">Kéo thật (thay toàn bộ kho)</button></div>';
		echo '</form>';
		echo '<p class="mo" style="margin:8px 0 0">Hàm phải nằm trong <code>CN_CHO_PHEP</code> của '
			. '<code>CauNoi.gs</code> bên app gốc, không thì cầu nối chối. Mặc định <code>getData</code>.</p>';
		echo '</details></div>';
	}
}
