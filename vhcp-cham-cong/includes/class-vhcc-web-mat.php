<?php
/**
 * MÀN KHUÔN MẶT TRÊN WEB — bản ngoài internet của màn wp-admin *Khuôn mặt*.
 *
 * =============================================================================================
 * VÌ SAO CÓ MÀN NÀY
 * =============================================================================================
 * Anh Thắng 08/09/2026, sau khi em thêm ảnh vào màn wp-admin: *"trên wed có chỗ duyệt đó chưa"*
 * — chưa. Rồi: *"QUản lý và admin duyệt"*.
 *
 * Việc duyệt mẫu nằm sau một cửa mà người duyệt không có: wp-admin đòi **tài khoản WordPress**.
 * Quản lý của chuỗi đăng nhập bằng PIN vào `/quan-tri-cham-cong/`, không ai phát cho họ tài
 * khoản WordPress cả. Nên hàng chờ duyệt cứ dài ra, mà mẫu chưa duyệt **vẫn được dùng để so** —
 * nếu chính tấm mẫu ấy bắt nhầm mặt người chấm hộ thì hệ thống gắn cờ **ngược**, người thật bị
 * coi là giả, suốt, và không ai biết.
 *
 * =============================================================================================
 * MẤY CHỐT KHÔNG ĐƯỢC NỚI
 * =============================================================================================
 * 🔴 CẢ MÀN NÀY LÀ `ngoai_coso` — bậc **Quản lý / Admin**, đúng câu anh Thắng chốt. Cửa hàng
 *    trưởng KHÔNG duyệt được người của cơ sở mình, dù họ nhận ra mặt nhanh hơn ai hết: thứ mẫu
 *    này canh là **chấm hộ**, mà người đứng gần chuyện chấm hộ nhất chính là người ở cửa hàng.
 *    Để họ tự xác nhận mẫu là bỏ luôn lớp gác — không phải vì nghi ai, mà vì một lớp gác do
 *    chính người bị gác dựng lên thì không còn là lớp gác.
 *
 * 🔴 GÁC Ở CẢ HAI CHỖ: lúc VẼ màn và lúc NHẬN việc POST. Chỉ gác lúc vẽ thì ai đoán ra tên
 *    `viec` là gửi thẳng POST được — mà `mat_duyet_het` duyệt sạch cả hàng chờ trong một lượt.
 *
 * 🔴 KHÔNG BAO GIỜ đưa `vector` ra màn hình. Đó là dữ liệu sinh trắc học, và màn này không dùng
 *    tới nó (`VHCC_Mat::ds()` đã tự gỡ). Thứ màn này cần là ẢNH cho người ta nhìn.
 *
 * ⚠️ KHÔNG có lấy một dòng script — cùng luật với cả màn quản trị.
 * ⚠️ Ảnh thẻ là data URI: **không đi qua `esc_url()`** (WordPress không có `data` trong danh
 *    sách giao thức nên nó trả CHUỖI RỖNG, ảnh biến mất im lặng). Xem `khoi_anh()`.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_WebMat {

	/** Tham số của màn này phải sống sót qua mỗi lượt bấm — xem `VHCC_Web::THAM_SO`. */
	const THAM_SO = array( 'mloc' );

	/** Việc POST của màn này. Danh sách trắng: tên nào không có ở đây thì không phải việc của màn. */
	const VIEC = array( 'mat_duyet', 'mat_xoa', 'mat_duyet_het' );

	/**
	 * Đầu việc gác cả màn — MƯỢN THẲNG hằng của `VHCC_Mat`, không chép lại chuỗi.
	 *
	 * 🔴 Chép một chuỗi `'ngoai_coso'` sang đây là dựng bộ luật thứ hai. Vừa trả giá đúng lỗi
	 *    ấy: màn này để `ngoai_coso` trong khi `VHCC_Mat::ds()/duyet()/xoa()` vẫn gác `ho_so`,
	 *    nên Quản lý MỞ ĐƯỢC màn mà bảng thì RỖNG và nút Duyệt thì bị chối — hỏng im lặng, và
	 *    câu chối lại nói về "hồ sơ nhân sự", một việc chẳng liên quan. Một hằng, một chỗ sửa.
	 */
	const QUYEN = VHCC_Mat::QUYEN;

	public static function la_viec( $viec ) {
		return in_array( (string) $viec, self::VIEC, true );
	}

	/** Câu chối dùng chung cho cả hai cửa — một câu, một chỗ sửa. */
	private static function loi_quyen( $toi ) {
		return 'Duyệt mẫu khuôn mặt là bậc <b>Quản lý / Admin</b>. Cửa hàng trưởng không duyệt '
			. 'người của cơ sở mình: thứ mẫu này canh là <b>chấm hộ</b>, mà một lớp gác do chính '
			. 'người bị gác dựng lên thì không còn là lớp gác. '
			. esc_html( VHCC_Vai::loi( $toi, self::QUYEN, 'Duyệt mẫu khuôn mặt' ) );
	}

	/**
	 * NHẬN VIỆC POST.
	 *
	 * 🔴 Gác quyền NGAY ĐÂY, đừng tin vào việc màn không vẽ nút cho người không đủ bậc.
	 *    Nút không vẽ chỉ là không mời; POST thì ai gửi cũng tới.
	 */
	public static function viec( $viec, $toi ) {
		if ( ! VHCC_Vai::duoc( $toi, self::QUYEN ) ) {
			return array( array( 'loi' => wp_strip_all_tags( self::loi_quyen( $toi ) ) ) );
		}
		$ma = isset( $_POST['mat_ma'] ) ? sanitize_text_field( wp_unslash( $_POST['mat_ma'] ) ) : '';

		if ( 'mat_duyet' === $viec ) { return array( VHCC_Mat::duyet( $toi, $ma ) ); }
		if ( 'mat_xoa' === $viec )   { return array( VHCC_Mat::xoa( $toi, $ma ) ); }

		if ( 'mat_duyet_het' === $viec ) {
			/* ⚠️ `false` = đừng nạp ảnh. Vòng này chỉ cần MÃ, mà mỗi ảnh thẻ là ~60 KB LONGTEXT —
			   kéo cả trăm cái về cho một vòng đếm là tự dựng một lượt truy vấn vài megabyte. */
			$so = 0;
			foreach ( VHCC_Mat::ds( $toi, 'cho', false ) as $m ) {
				if ( ! empty( VHCC_Mat::duyet( $toi, $m['ma_nv'] )['ok'] ) ) { $so++; }
			}
			return array( array( 'ok' => true, 'thong_bao' => 'Đã duyệt ' . $so . ' mẫu.' ) );
		}
		return array();
	}

	// ======================================================================== vẽ màn

	public static function man( $ky, $toi ) {
		echo '<div class="the"><h2>🙂 Khuôn mặt</h2>';
		echo '<p class="mo">Duyệt <b>mẫu đối chiếu khuôn mặt</b> của chấm công online, và đọc số '
			. 'để chọn ngưỡng lệch.</p></div>';

		if ( ! VHCC_Vai::duoc( $toi, self::QUYEN ) ) {
			echo '<div class="the"><div class="bao loi" style="margin:0">' . self::loi_quyen( $toi )
				. '</div></div>';
			return;
		}

		self::the_che_do();
		self::the_mau( $ky, $toi );
		self::the_thong_ke( $toi );
	}

	/** Chế độ đang chạy — thứ quyết định cả màn này có nghĩa gì. */
	private static function the_che_do() {
		$im = ( 'im' === VHCC_Mat::che_do() );
		echo '<div class="the"><h2>Đang chạy: ' . ( $im ? 'IM' : 'GẮN CỜ THẬT' ) . '</h2>';
		echo '<div class="bao ' . ( $im ? 'canh' : 'loi' ) . '" style="margin:0">';
		if ( $im ) {
			echo 'Hệ thống vẫn so và ghi số, nhưng <b>chưa gắn cờ nào</b>. Đọc bảng phân bố ở '
				. 'cuối trang sau vài tuần, chọn ngưỡng theo số đo được, rồi mới bật gắn cờ.';
		} else {
			echo 'Đang gắn cờ thật với ngưỡng <b>' . esc_html( (string) VHCC_Mat::nguong_lech() )
				. '</b>. Mỗi mẫu duyệt sai từ đây trở đi là một người bị gắn cờ oan mỗi ngày.';
		}
		echo '</div></div>';
	}

	// ------------------------------------------------------------------ bảng mẫu

	private static function the_mau( $ky, $toi ) {
		$loc = isset( $_GET['mloc'] ) ? sanitize_text_field( wp_unslash( $_GET['mloc'] ) ) : 'cho';
		if ( ! in_array( $loc, array( 'cho', 'duyet', 'het' ), true ) ) { $loc = 'cho'; }
		$dem = VHCC_Mat::dem();
		$ds  = VHCC_Mat::ds( $toi, 'het' === $loc ? '' : $loc );

		echo '<div class="the"><h2>Mẫu khuôn mặt</h2>';
		echo '<p class="mo"><b>' . (int) $dem['tong'] . '</b> người đã có mẫu'
			. ( $dem['cho'] ? ', <b>' . (int) $dem['cho'] . ' chờ duyệt</b>' : '' ) . '.</p>';

		echo '<div class="bao canh">⚠️ <b>Mẫu chưa duyệt vẫn được dùng để so.</b> Nếu chính ngày '
			. 'đầu tiên ấy có người chấm hộ thì mẫu ghi lại mặt người chấm hộ — và từ đó hệ thống '
			. 'gắn cờ <b>ngược</b>: người thật bị coi là giả. Duyệt nghĩa là "tôi đã xem ảnh và '
			. 'đúng là người này"; nghi ngờ thì <b>Xoá mẫu</b>, lượt chấm sau tự lấy lại.</div>';

		echo '<p class="mo">Mỗi dòng có <b>hai tấm</b>: <span style="color:#15803d;font-weight:600">'
			. 'tấm đã sinh ra mẫu</span> (lượt chấm công đầu tiên) và <span style="color:#1d4ed8;'
			. 'font-weight:600">ảnh thẻ trong hồ sơ</span> để đối chiếu. Cùng một người thì duyệt; '
			. 'khác người thì <b>Xoá mẫu</b>. Viền <span style="color:#b45309;font-weight:600">vàng'
			. '</span> nghĩa là ảnh gốc không còn, đang hiện tạm tấm gần nhất — <b>đừng duyệt theo '
			. 'tấm đó</b>.</p>';

		/* Bộ lọc là ĐƯỜNG DẪN, không phải nút POST: người ta cần chép được địa chỉ gửi cho nhau
		   ("mở giúp anh mục chờ duyệt"), và bấm Quay lại phải về đúng mục vừa xem. */
		echo '<p class="hang" style="gap:6px">';
		foreach ( array( 'cho' => 'Chờ duyệt', 'duyet' => 'Đã duyệt', 'het' => 'Tất cả' ) as $k => $ten ) {
			echo '<a class="nut' . ( $k === $loc ? ' chinh' : '' ) . '" href="'
				. esc_url( add_query_arg( array( 'man' => 'mat', 'mloc' => $k ), VHCC_Web::url() ) )
				. '">' . esc_html( $ten ) . '</a>';
		}
		echo '</p>';

		if ( ! $ds ) {
			echo '<p class="mo">Không có mẫu nào ở mục này.</p></div>';
			return;
		}

		echo '<div class="cuon"><table><thead><tr><th>Ảnh — xem rồi mới duyệt</th><th>Người</th>'
			. '<th>Lấy từ</th><th>Trạng thái</th><th></th></tr></thead><tbody>';
		foreach ( $ds as $m ) {
			echo '<tr><td>' . self::o_anh( $m ) . '</td>'
				. '<td><b>' . esc_html( (string) $m['ho_ten'] ) . '</b><br>'
				. '<span class="mo"><code>' . esc_html( $m['ma_nv'] ) . '</code> · '
				. esc_html( (string) $m['cua_hang'] ) . '</span></td>'
				. '<td class="mo">' . esc_html( (string) $m['nguon_ngay'] )
				. ( '' !== (string) $m['nguon_coso'] ? '<br>' . esc_html( $m['nguon_coso'] ) : '' )
				. '<br>gộp ' . (int) $m['so_lan'] . ' lần</td>'
				. '<td>' . ( 'duyet' === $m['trang_thai']
					? '<span style="color:#15803d;font-weight:600">✔ đã duyệt</span>'
						. ( '' !== (string) $m['nguoi_duyet']
							? '<br><span class="mo">' . esc_html( $m['nguoi_duyet'] ) . '</span>' : '' )
					: '<span style="color:#b45309;font-weight:600">chờ duyệt</span>' ) . '</td>'
				. '<td>' . self::nut_dong( $ky, $m ) . '</td></tr>';
		}
		echo '</tbody></table></div>';

		if ( 'cho' === $loc && count( $ds ) > 1 ) {
			echo '<form method="post" style="margin-top:12px">';
			echo '<input type="hidden" name="ky" value="' . esc_attr( $ky ) . '">';
			echo '<input type="hidden" name="viec" value="mat_duyet_het">';
			echo '<input type="hidden" name="mloc" value="cho">';
			echo '<button class="nut">Duyệt tất cả ' . count( $ds ) . ' mẫu đang chờ</button>';
			echo ' <span class="mo">chỉ bấm khi đã lướt hết cột ảnh ở trên — duyệt bừa là mất luôn '
				. 'tác dụng của việc duyệt.</span></form>';
		}
		echo '</div>';
	}

	/**
	 * Hai nút của một dòng — MỖI DÒNG MỘT `<form>` RIÊNG.
	 *
	 * 🔴 Gộp cả bảng vào một form thì mọi ô ẩn `mat_ma` cùng được gửi lên và máy chủ đọc cái
	 *    CUỐI — bấm "Xoá mẫu" ở dòng đầu lại xoá mẫu của dòng cuối. Đã trả giá đúng lỗi này ở
	 *    màn wp-admin.
	 */
	private static function nut_dong( $ky, $m ) {
		$o = '<input type="hidden" name="ky" value="' . esc_attr( $ky ) . '">'
			. '<input type="hidden" name="mat_ma" value="' . esc_attr( $m['ma_nv'] ) . '">'
			. '<input type="hidden" name="mloc" value="'
			. esc_attr( isset( $_GET['mloc'] ) ? sanitize_text_field( wp_unslash( $_GET['mloc'] ) ) : 'cho' )
			. '">';
		$ra = '';
		if ( 'duyet' !== $m['trang_thai'] ) {
			$ra .= '<form method="post" style="margin:0 0 6px">' . $o
				. '<button class="nut chinh" name="viec" value="mat_duyet">Duyệt</button></form>';
		}
		/* Xoá mẫu lùi được (lượt chấm sau tự lấy lại) nhưng vẫn hỏi lại: bấm nhầm dòng bên cạnh
		   là người ta phải chấm thêm một lượt mới có mẫu. */
		$ra .= '<form method="post" style="margin:0" onsubmit="return confirm('
			. esc_attr( "'Xoá mẫu của " . $m['ma_nv'] . "? Lượt chấm công sau sẽ tự lấy mẫu mới.'" )
			. ');">' . $o . '<button class="nut" name="viec" value="mat_xoa">Xoá mẫu</button></form>';
		return $ra;
	}

	// ------------------------------------------------------------------ ô ảnh

	/** Hai tấm cạnh nhau — xem chú thích ở `VHCC_Man::o_anh_mau()`, cùng một luật. */
	private static function o_anh( $m ) {
		$a   = isset( $m['anh'] ) && is_array( $m['anh'] ) ? $m['anh'] : array();
		$the = isset( $m['anh_the'] ) ? trim( (string) $m['anh_the'] ) : '';
		$o   = '<div class="hang" style="gap:8px;align-items:flex-start;margin:0">';

		$url = self::url_anh( isset( $a['duong'] ) ? $a['duong'] : '' );
		if ( '' !== $url ) {
			$goc = ! empty( $a['dung_goc'] );
			$o  .= self::khoi_anh( $url, $goc ? 'Tấm đã sinh ra mẫu' : '⚠️ KHÔNG phải tấm gốc',
				trim( (string) $a['ngay'] ) . ( '' !== (string) $a['coso'] ? ' · ' . $a['coso'] : '' ),
				$goc ? '#15803d' : '#b45309' );
		} else {
			$o .= '<div style="width:104px;font-size:11.5px;color:#b91c1c;line-height:1.35">'
				. '⚠️ <b>Không còn ảnh</b><br>Lượt chấm sinh ra mẫu này không kèm ảnh, hoặc ảnh đã '
				. 'bị dọn. <b>Đừng duyệt mò</b> — xoá mẫu đi, lượt chấm sau tự lấy lại.</div>';
		}

		if ( '' !== $the ) {
			$o .= self::khoi_anh( $the, 'Ảnh thẻ trong hồ sơ', 'bản đối chứng', '#1d4ed8' );
		} else {
			$o .= '<div style="width:104px;font-size:11.5px;color:#6b7280;line-height:1.35">'
				. 'Hồ sơ <b>chưa có ảnh thẻ</b> — không có gì để đối chiếu. Chỉ duyệt khi anh/chị '
				. 'nhận ra mặt người này.</div>';
		}
		return $o . '</div>';
	}

	/**
	 * Một ảnh + nhãn. `$src` nhận cả URL lẫn data URI.
	 *
	 * 🔴 DATA URI KHÔNG ĐƯỢC ĐI QUA `esc_url()`. WordPress không có `data` trong danh sách giao
	 *    thức cho phép, nên `esc_url` trả về CHUỖI RỖNG — ảnh thẻ biến mất, không một lời báo,
	 *    và người đọc mã sẽ tưởng hồ sơ chưa có ảnh.
	 * ⚠️ Cột `anh_the` do người dùng nạp lên: một `data:text/html,...` nhét vào `src` là một
	 *    đường chạy mã ngay trong trang quản trị. Soát khuôn trước, và chối thì NÓI RA.
	 * ⚠️ Data URI cũng không bọc trong `<a>`: trình duyệt chặn mở `data:` ở tab mới.
	 */
	private static function khoi_anh( $src, $nhan, $phu, $mau ) {
		$vien = 'width:104px;height:104px;object-fit:cover;border-radius:8px;display:block;'
			. 'border:2px solid ' . $mau;
		if ( 0 === strpos( (string) $src, 'data:' ) ) {
			if ( ! preg_match( '#^data:image/(jpeg|png|webp|gif);base64,[A-Za-z0-9+/=\s]+$#', (string) $src ) ) {
				return '<div style="width:104px;font-size:11.5px;color:#b91c1c">Ảnh thẻ hỏng khuôn.</div>';
			}
			$anh = '<img src="' . esc_attr( $src ) . '" alt="' . esc_attr( $nhan ) . '" style="'
				. esc_attr( $vien ) . '">';
		} else {
			$anh = '<a href="' . esc_url( $src ) . '" target="_blank" rel="noopener" '
				. 'title="Mở ảnh gốc ở tab mới"><img src="' . esc_url( $src ) . '" alt="'
				. esc_attr( $nhan ) . '" loading="lazy" style="' . esc_attr( $vien ) . '"></a>';
		}
		return '<div style="width:104px">' . $anh
			. '<div style="font-size:11.5px;color:' . esc_attr( $mau ) . ';font-weight:600;margin-top:3px">'
			. esc_html( $nhan ) . '</div>'
			. ( '' !== $phu ? '<div style="font-size:11.5px" class="mo">' . esc_html( $phu ) . '</div>' : '' )
			. '</div>';
	}

	/**
	 * Đường dẫn tương đối trong `cham_cong.anh_vao`/`anh_ra` -> URL xem được.
	 * ⚠️ Cùng luật với `VHCC_Man::url_anh_cham()` và `VHCC_Web::url_anh_cham()` — ba bản vì ba
	 *    lớp không gọi chéo được vào hàm private của nhau. Đổi cách lưu ảnh thì sửa CẢ BA.
	 */
	private static function url_anh( $duong ) {
		$duong = trim( (string) $duong );
		if ( '' === $duong ) { return ''; }
		if ( 0 === strpos( $duong, 'data:' ) ) { return $duong; }
		$u = wp_upload_dir();
		if ( ! empty( $u['error'] ) ) { return ''; }
		return trailingslashit( $u['baseurl'] ) . $duong;
	}

	// ------------------------------------------------------------------ số để chọn ngưỡng

	private static function the_thong_ke( $toi ) {
		$tk = VHCC_Mat::thong_ke( $toi );
		echo '<div class="the"><h2>Lệch bao nhiêu — phân bố</h2>';
		if ( empty( $tk['ok'] ) || ! $tk['tong'] ) {
			echo '<p class="mo">Chưa có lượt nào được đối chiếu. Cần nhân viên chấm công vài lượt '
				. 'đã — lượt đầu của mỗi người là để <i>lấy mẫu</i>, từ lượt thứ hai mới có số để '
				. 'so.</p></div>';
			return;
		}
		echo '<p class="mo">Đã đối chiếu <b>' . (int) $tk['tong'] . '</b> lượt. Xanh = gần như chắc '
			. 'chắn cùng một người · Vàng = vùng <i>không biết</i> · Đỏ = gần như chắc chắn hai '
			. 'người khác nhau. <b>Ngưỡng đúng nằm ở chỗ đám đông xanh tách khỏi cái đuôi thưa</b> '
			. '— không phải ở con số mặc định.</p>';

		$dinh = 1;
		foreach ( $tk['o'] as $so_o ) { $dinh = max( $dinh, (int) $so_o ); }
		echo '<div class="cuon"><table><thead><tr><th>Lệch</th><th>Số lượt</th><th></th></tr>'
			. '</thead><tbody>';
		foreach ( $tk['o'] as $khoang => $so_o ) {
			$tu  = (float) $khoang;
			$mau = ( $tu < VHCC_Mat::D_KHOP ) ? '#15803d' : ( $tu < VHCC_Mat::D_LECH ? '#b45309' : '#b91c1c' );
			echo '<tr><td>' . esc_html( number_format( $tu, 2 ) ) . ' – '
				. esc_html( number_format( $tu + 0.05, 2 ) ) . '</td>'
				. '<td><b>' . (int) $so_o . '</b></td>'
				/* Vẽ cột bằng một ô màu: nhìn hình thấy ngay hai đám tách nhau ở đâu, còn đọc
				   cột số thì phải tự dựng hình trong đầu. */
				. '<td><span style="display:inline-block;height:13px;background:' . esc_attr( $mau )
				. ';width:' . (int) round( 100 * $so_o / $dinh ) . '%"></span></td></tr>';
		}
		echo '</tbody></table></div>';

		if ( empty( $tk['dau'] ) ) { echo '</div>'; return; }

		/* 🔴 ẢNH HIỆN NGAY TẠI ĐÂY. Bảng này sinh ra để trả lời đúng một câu — "mấy lượt lệch
		   nhất là người thật hay không" — mà câu ấy chỉ trả lời được bằng MẮT. Bắt đi sang màn
		   khác dò đúng ngày đúng người ba mươi lần thì không ai làm, và ngưỡng cứ để nguyên số
		   mặc định. Ảnh CHỈ hiện khi đúng lượt ấy: đưa tấm gần nhất thay vào là cho người ta xem
		   nhầm ảnh rồi kết luận về một lượt khác. */
		echo '<h2 style="margin-top:18px">30 lượt lệch nhất</h2>';
		echo '<p class="mo">Nhìn ảnh: <b>người thật hay không</b>. Đám lệch cao mà vẫn là người '
			. 'thật thì nới ngưỡng lên. Bấm vào ảnh để xem lớn.</p>';
		echo '<div class="cuon"><table><thead><tr><th>Ảnh</th><th>Lệch</th><th>Mã NV</th>'
			. '<th>Ngày</th><th>Cơ sở</th><th>Kết luận</th></tr></thead><tbody>';
		foreach ( $tk['dau'] as $d ) {
			$a = VHCC_Mat::anh_cua_mau( $d['ma_nv'], (string) $d['ngay'], (string) $d['coso'] );
			$u = ! empty( $a['dung_goc'] ) ? self::url_anh( $a['duong'] ) : '';
			echo '<tr><td>' . ( '' !== $u
				? '<a href="' . esc_url( $u ) . '" target="_blank" rel="noopener">'
					. '<img src="' . esc_url( $u ) . '" alt="Ảnh lượt chấm" loading="lazy" '
					. 'style="width:60px;height:60px;object-fit:cover;border-radius:6px;display:block">'
					. '</a>'
				: '<span class="mo">không có ảnh</span>' ) . '</td>'
				. '<td><b>' . esc_html( number_format( (float) $d['d'], 3 ) ) . '</b></td>'
				. '<td><code>' . esc_html( $d['ma_nv'] ) . '</code></td>'
				. '<td>' . esc_html( (string) $d['ngay'] ) . '</td>'
				. '<td>' . esc_html( $d['coso'] ) . '</td>'
				. '<td class="mo">' . esc_html( $d['ket_qua'] )
				. ( ! empty( $d['co_gan'] ) ? ' · đã gắn cờ' : '' ) . '</td></tr>';
		}
		echo '</tbody></table></div></div>';
	}
}
