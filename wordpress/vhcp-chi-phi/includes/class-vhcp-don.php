<?php
/**
 * ĐƠN VẬN HÀNH — đơn tạm ứng theo tuần, hạng mục xin / phát sinh, đối chiếu thừa thiếu.
 *
 * Dịch nguyên logic từ Code.gs (sheet DonHang / TamUng / ChiPhi). Quy ước giữ nguyên:
 *   - Tạm ứng "1 cục" cho cả đơn = 'Tạm ứng duyệt' (nếu có) hoặc tổng hạng mục xin + dự phòng + bù trừ.
 *   - Thực chi mỗi dòng = Thực mua (nếu đã nhập) ngược lại = Thành tiền.
 *   - Dòng NCC hiệu lực = phân loại 'Nhà cung cấp' HOẶC dòng cá nhân bị bỏ tích "CN xử lý".
 *   - Trạng thái: Nháp → Chờ duyệt tạm ứng → Chờ cấp tạm ứng → Đã cấp tạm ứng → Chờ quyết toán → Đã quyết toán → Đã xuất MISA.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCP_Don {

	/**
	 * ════════════════════════════════════════════════════════════════════════════════════════
	 * RANH GIỚI SỬA ĐƯỢC / KHÔNG SỬA ĐƯỢC — MỘT CHỖ, KHÔNG TÁM CHỖ.
	 *
	 * Anh Thắng 26/08/2026: *"giờ sẽ cho nhân viên được phép sửa đơn. trừ khi ở trạng thái đã
	 * quyết toán mới không được sửa, còn lại nhân viên đều được sửa và bổ sung đơn."*
	 *
	 * 🔴 TRƯỚC ĐÂY LUẬT NẰM RẢI RÁC TÁM CHỖ, mỗi chỗ một danh sách trạng thái gõ tay:
	 *      · thêm dòng    -> 'Nháp' | 'Đã cấp tạm ứng'
	 *      · sửa dòng     -> 'Nháp' | 'Đã cấp tạm ứng'
	 *      · sửa ngày     -> 'Nháp' | 'Đã cấp tạm ứng' | 'Chờ quyết toán'
	 *      · tách dòng    -> 'Nháp' | 'Đã cấp tạm ứng' | 'Chờ quyết toán'
	 *      · gắn ảnh      -> 'Nháp' | 'Đã cấp tạm ứng' | 'Chờ quyết toán'
	 *      · sửa tạm ứng  -> 'Nháp'
	 *    Ba danh sách khác nhau cho cùng một câu hỏi. Người dùng không đọc được mã, họ chỉ thấy
	 *    chỗ này sửa được chỗ kia không, cùng một đơn, cùng một lúc — và kết luận là phần mềm
	 *    hỏng. Nay đúng MỘT ranh giới: đã chốt sổ thì thôi, chưa chốt thì sửa.
	 *
	 * ⚠️ 'Đã xuất MISA' nằm SAU 'Đã quyết toán' trong luồng, nên nó cũng là đã chốt. Anh Thắng
	 *    chỉ nêu tên trạng thái đầu tiên; hiểu theo nghĩa đen mà mở lại đơn đã xuất MISA thì hai
	 *    bên sổ lệch nhau — số bên MISA đã gửi đi rồi, không rút về được.
	 */
	const TT_CHOT = array( 'Đã quyết toán', 'Đã xuất MISA' );

	/** Toàn bộ luồng, đúng thứ tự — để màn hình vẽ được thanh bước và biết mình đang ở đâu. */
	const TT_LUONG = array( 'Nháp', 'Chờ duyệt tạm ứng', 'Chờ cấp tạm ứng', 'Đã cấp tạm ứng',
		'Chờ quyết toán', 'Đã quyết toán', 'Đã xuất MISA' );

	public static function da_chot( $st ) {
		return in_array( trim( (string) $st ), self::TT_CHOT, true );
	}

	/**
	 * '' nếu còn sửa được, hoặc câu từ chối nói RÕ vì sao.
	 *
	 * ⚠️ Câu từ chối phải nói ra ĐANG Ở TRẠNG THÁI NÀO. "Không sửa được" trần trụi thì người
	 *    dùng không biết là do đơn đã chốt, do vai trò mình, hay do phần mềm lỗi.
	 */
	public static function vi_sao_khong_sua( $ma_don ) {
		$st = self::state( $ma_don );
		if ( '' === $st ) { return 'Không tìm thấy đơn.'; }
		if ( self::da_chot( $st ) ) {
			return 'Đơn đã ở trạng thái "' . $st . '" — số đã vào sổ nên không sửa được nữa. '
				. 'Cần sửa thì báo kế toán.';
		}
		return '';
	}

	/**
	 * ════════════════════════════════════════════════════════════════════════════════════════
	 * GHI VẾT MỌI LƯỢT ĐỘNG VÀO ĐƠN.
	 *
	 * Anh Thắng 26/08/2026: *"Phía bên phải là lịch sử chỉnh đơn. để biết ai chỉnh gì trong
	 * này."*
	 *
	 * 🔴 ĐÂY LÀ CÁI GIÁ CỦA VIỆC VỪA MỞ QUYỀN SỬA.
	 *    Sáng nay đơn chỉ sửa được lúc "Nháp" và "Đã cấp tạm ứng". Nay sửa được suốt tới lúc
	 *    chốt sổ — tức là một con số có thể đổi SAU khi quản lý đã duyệt và SAU khi kế toán đã
	 *    nhìn. Mở cửa mà không có sổ ghi thì không ai dựng lại được chuyện gì đã xảy ra: người
	 *    duyệt nhớ mình duyệt 15 triệu, đơn ghi 12 triệu, và không có gì phân xử.
	 *
	 * ⚠️ Ghi SỐ TIỀN CŨ, không chỉ ghi "đã sửa". Câu "Nguyễn A sửa dòng Thịt heo" không nói được
	 *    gì; "Thịt heo: 1.200.000 -> 600.000" thì nói đủ. Sau khi sửa thì giá trị cũ không còn
	 *    ở đâu nữa — nhật ký là bản sao duy nhất.
	 *
	 * ⚠️ KHÔNG bao giờ để việc ghi vết làm hỏng việc chính. `log_action` trượt (bảng nhật ký
	 *    chưa dựng trên một bản cài cũ) thì dòng chi phí vẫn phải được ghi.
	 */
	private static function ghi_vet( $ma_don, $hanh_dong, $chi_tiet ) {
		if ( ! class_exists( 'VHCP_Log' ) || ! method_exists( 'VHCP_Log', 'log_action' ) ) { return; }
		VHCP_Log::log_action( array(
			'actor'  => VHCP_Auth::nguoi(),
			'role'   => VHCP_Auth::vai_tro(),
			'action' => (string) $hanh_dong,
			'target' => (string) $ma_don,
			'detail' => (string) $chi_tiet,
		) );
	}

	/** Mô tả gọn một dòng chi: nội dung + số tiền. Dùng chung cho mọi câu nhật ký. */
	private static function ta_dong( $r ) {
		$r = (array) $r;
		$nd = trim( (string) ( isset( $r['noi_dung'] ) ? $r['noi_dung'] : '' ) );
		if ( '' === $nd ) { $nd = '(chưa có nội dung)'; }
		$tt = VHCP_Util::num( isset( $r['thanh_tien'] ) ? $r['thanh_tien'] : 0 );
		$tm = ( isset( $r['thuc_mua'] ) && '' !== $r['thuc_mua'] && null !== $r['thuc_mua'] )
			? VHCP_Util::num( $r['thuc_mua'] ) : null;
		return $nd . ' · ' . number_format( $tt, 0, ',', '.' ) . 'đ'
			. ( null === $tm ? '' : ' · thực chi ' . number_format( $tm, 0, ',', '.' ) . 'đ' );
	}

	/**
	 * NHẬT KÝ CỦA MỘT ĐƠN — mới nhất trước.
	 *
	 * ⚠️ Lọc theo `doi_tuong` = mã đơn. Đọc cả sổ rồi lọc ở giao diện là kéo về 800 dòng của
	 *    mọi đơn để hiện 5 dòng — chậm, và trên điện thoại thì tốn dữ liệu của người ta.
	 */
	public static function nhat_ky_don( $ma_don, $limit = 50 ) {
		global $wpdb;
		$ma_don = trim( (string) $ma_don );
		if ( '' === $ma_don ) { return VHCP_Util::ok( array( 'items' => array() ) ); }
		$limit = (int) $limit;
		if ( $limit <= 0 || $limit > 300 ) { $limit = 50; }
		$t = VHCP_DB::t( 'log' );
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $t WHERE doi_tuong=%s ORDER BY id DESC LIMIT %d", $ma_don, $limit ), ARRAY_A );
		$items = array();
		foreach ( (array) $rows as $r ) {
			$tg = VHCP_Util::fmt_dt( $r['tg'] );
			/* Thời điểm vô lý thì nói là không biết, đừng hiện ngày bịa — xem chú thích cùng
			   kiểu ở `VHCP_Log::get_log()`. */
			if ( '' !== $tg && VHCP_Util::ngay_vo_ly( substr( $tg, 0, 10 ) ) ) { $tg = ''; }
			$items[] = array(
				'tg'      => $tg,
				'nguoi'   => (string) $r['nguoi'],
				'vaiTro'  => (string) $r['vai_tro'],
				'hanhDong' => (string) $r['hanh_dong'],
				'chiTiet' => (string) $r['chi_tiet'],
			);
		}
		return VHCP_Util::ok( array( 'items' => $items ) );
	}

	/**
	 * TÌM ĐƠN — theo LOẠI CHI PHÍ, theo CƠ SỞ, hoặc theo chữ bất kỳ.
	 *
	 * Anh Thắng 26/08/2026: *"đầu trang bổ sung tìm kiếm đơn ( tìm kiếm loại chi phí và đơn đó
	 * thuộc cơ sở nào )"*.
	 *
	 * 🔴 PHẢI TÌM Ở MÁY CHỦ, KHÔNG LỌC Ô XỔ XUỐNG Ở GIAO DIỆN.
	 *    Ô xổ đơn chỉ mang kỳ · cơ sở · người lập · trạng thái. Câu anh Thắng hỏi là *"đơn nào
	 *    có chi phí loại này"* — mà loại chi phí nằm ở TỪNG DÒNG CHI, không nằm trên đơn. Lọc ở
	 *    giao diện thì gõ "Chi phí nuôi thú" ra rỗng, trong khi có mấy chục đơn chứa nó.
	 *
	 * ⚠️ Trả kèm ĐÚNG NHỮNG LOẠI ĐÃ KHỚP của từng đơn. Chỉ trả danh sách mã đơn thì người tìm
	 *    phải mở từng đơn ra xem vì sao nó lọt vào kết quả.
	 *
	 * @param string $q     chữ tìm: khớp mã đơn · kỳ · người lập · nhóm (loại chi phí) · nội dung.
	 * @param string $coso  lọc thêm theo cơ sở của đơn ('' = mọi cơ sở).
	 * @param string $nhom  lọc thêm theo ĐÚNG một loại chi phí ('' = mọi loại).
	 */
	public static function tim_don( $q = '', $coso = '', $nhom = '', $limit = 60 ) {
		global $wpdb;
		$q     = trim( (string) $q );
		$coso  = trim( (string) $coso );
		$nhom  = trim( (string) $nhom );
		$limit = (int) $limit;
		if ( $limit <= 0 || $limit > 200 ) { $limit = 60; }
		if ( '' === $q && '' === $coso && '' === $nhom ) {
			return VHCP_Util::ok( array( 'items' => array(), 'chuaNhap' => true ) );
		}

		$td = VHCP_DB::t( 'don' );
		$tc = VHCP_DB::t( 'chiphi' );

		/* Gom theo ĐƠN chứ không theo dòng: một đơn có 40 dòng cùng loại thì kết quả vẫn là một
		   dòng, kèm mấy loại đã khớp. Trả 40 dòng giống nhau là bắt người tìm tự gom bằng mắt. */
		$dk = array( '1=1' );
		$tv = array();
		if ( '' !== $coso ) { $dk[] = 'd.coso = %s'; $tv[] = $coso; }
		if ( '' !== $nhom ) { $dk[] = 'c.nhom = %s'; $tv[] = $nhom; }
		if ( '' !== $q ) {
			$like = '%' . $wpdb->esc_like( $q ) . '%';
			$dk[] = '( d.ma_don LIKE %s OR d.ky LIKE %s OR d.nguoi_lap LIKE %s'
				. ' OR c.nhom LIKE %s OR c.noi_dung LIKE %s OR c.doi_tuong LIKE %s )';
			array_push( $tv, $like, $like, $like, $like, $like, $like );
		}
		$sql = "SELECT d.ma_don, d.ky, d.coso, d.nguoi_lap, d.trang_thai,
				GROUP_CONCAT(DISTINCT c.nhom) AS cac_nhom, COUNT(c.id) AS so_dong,
				SUM(c.thanh_tien) AS tong_tien
			FROM $td d LEFT JOIN $tc c ON c.ma_don = d.ma_don
			WHERE " . implode( ' AND ', $dk ) . "
			GROUP BY d.ma_don, d.ky, d.coso, d.nguoi_lap, d.trang_thai
			ORDER BY d.stt DESC LIMIT %d";
		$tv[] = $limit;
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $tv ), ARRAY_A );

		$items = array();
		foreach ( (array) $rows as $r ) {
			$loai = array();
			foreach ( explode( ',', (string) $r['cac_nhom'] ) as $x ) {
				$x = trim( $x );
				if ( '' !== $x && ! in_array( $x, $loai, true ) ) { $loai[] = $x; }
			}
			$items[] = array(
				'maDon'     => (string) $r['ma_don'],
				'ky'        => (string) $r['ky'],
				'coso'      => (string) $r['coso'],
				'nguoiLap'  => (string) $r['nguoi_lap'],
				'trangThai' => (string) $r['trang_thai'],
				'loai'      => $loai,
				'soDong'    => (int) $r['so_dong'],
				'tongTien'  => VHCP_Util::num( $r['tong_tien'] ),
			);
		}
		return VHCP_Util::ok( array( 'items' => $items, 'chuaNhap' => false ) );
	}

	/** Mọi LOẠI CHI PHÍ đang có thật trong sổ — để ô lọc chỉ liệt kê thứ tìm ra được. */
	public static function ds_loai_chi_phi() {
		global $wpdb;
		$tc = VHCP_DB::t( 'chiphi' );
		$rows = $wpdb->get_col( "SELECT DISTINCT nhom FROM $tc WHERE nhom<>'' ORDER BY nhom ASC" );
		return VHCP_Util::ok( array( 'items' => array_values( array_filter( (array) $rows ) ) ) );
	}

	// ---------------------------------------------------------------- đọc bảng

	/** Mọi dòng chi phí, theo đúng thứ tự nhập (như đọc sheet ChiPhi). */
	public static function cp_rows() {
		global $wpdb;
		$t = VHCP_DB::t( 'chiphi' );
		return VHCP_DB::rows( "SELECT * FROM $t ORDER BY stt ASC" );
	}

	public static function don_rows() {
		global $wpdb;
		$t = VHCP_DB::t( 'don' );
		return VHCP_DB::rows( "SELECT * FROM $t ORDER BY stt ASC" );
	}

	public static function tu_rows() {
		global $wpdb;
		$t = VHCP_DB::t( 'tamung' );
		return VHCP_DB::rows( "SELECT * FROM $t ORDER BY id ASC" );
	}

	public static function don_row( $ma_don ) {
		global $wpdb;
		$t = VHCP_DB::t( 'don' );
		return VHCP_DB::row( $wpdb->prepare( "SELECT * FROM $t WHERE ma_don=%s", (string) $ma_don ) );
	}

	public static function line_row( $id ) {
		global $wpdb;
		$t = VHCP_DB::t( 'chiphi' );
		return VHCP_DB::row( $wpdb->prepare( "SELECT * FROM $t WHERE id=%s", (string) $id ) );
	}

	private static function upd_don( $ma_don, $data ) {
		global $wpdb;
		return $wpdb->update( VHCP_DB::t( 'don' ), $data, array( 'ma_don' => (string) $ma_don ) );
	}

	private static function state( $ma_don ) {
		$d = self::don_row( $ma_don );
		return $d ? (string) $d['trang_thai'] : '';
	}

	/** _donInfo(): trạng thái + đơn có đang bị trả lại không. */
	private static function info( $ma_don ) {
		$d = self::don_row( $ma_don );
		if ( ! $d ) { return array( 'st' => '', 'returned' => false ); }
		return array( 'st' => (string) $d['trang_thai'], 'returned' => ( strpos( (string) $d['ghi_chu'], '[Trả lại]' ) !== false ) );
	}

	private static function mark_kt_sua( $ma_don, $actor ) {
		$d = self::don_row( $ma_don );
		if ( ! $d ) { return; }
		$g = (string) $d['ghi_chu'];
		if ( strpos( $g, '[KT sửa]' ) !== false ) { return; }
		self::upd_don( $ma_don, array( 'ghi_chu' => '[KT sửa]' . ( $actor ? ' ' . $actor : '' ) . ( $g !== '' ? ' | ' . $g : '' ) ) );
	}

	private static function clear_tra_marker( $ma_don ) {
		$d = self::don_row( $ma_don );
		if ( ! $d ) { return; }
		$g = (string) $d['ghi_chu'];
		if ( preg_match( '/\[Trả lại\]/u', $g ) ) {
			$g = trim( preg_replace( '/\[Trả lại\][^|]*\|?\s*/u', '', $g ) );
			self::upd_don( $ma_don, array( 'ghi_chu' => $g ) );
		}
	}

	// ---------------------------------------------------------------- khởi động

	/** getBootstrap(): đọc ChiPhi 1 lần rồi dùng chung cho cấu hình + đơn + gợi ý sản phẩm. */
	public static function get_bootstrap() {
		$cp  = self::cp_rows();
		$cfg = VHCP_Cfg::get_config( $cp );

		$coso = array(); $coso_dong = array();
		foreach ( $cfg['coso'] as $x ) {
			$coso[] = $x['ten'];
			// Gian đã đóng: gửi kèm để giao diện bỏ khỏi ô chọn lúc nhập
			if ( trim( (string) ( isset( $x['dongCua'] ) ? $x['dongCua'] : '' ) ) !== '' ) { $coso_dong[] = $x['ten']; }
		}
		$nhom = array();
		foreach ( $cfg['nhom'] as $x ) { $nhom[] = array( 'ten' => $x['ten'], 'loai' => $x['loai'], 'boPhan' => isset( $x['boPhan'] ) ? $x['boPhan'] : '' ); }
		$pl = array();
		foreach ( $cfg['phanloai'] as $x ) { $pl[] = $x['ten']; }

		$loai = array();
		foreach ( (array) ( isset( $cfg['loaiChiPhi'] ) ? $cfg['loaiChiPhi'] : array() ) as $x ) {
			$loai[] = array( 'ten' => $x['ten'], 'tkNo' => $x['tkNo'], 'tkCo' => $x['tkCo'], 'boPhan' => $x['boPhan'], 'loaiTt' => isset( $x['loaiTt'] ) ? $x['loaiTt'] : '' );
		}

		// Cơ sở -> mảng kinh doanh, và ma trận [loại][mảng] -> TK Nợ: để ô "Loại chi phí"
		// chỉ hiện những loại mà cơ sở đang chọn thật sự dùng (tránh chọn lộn mảng).
		$s_all   = VHCP_Cfg::cfg_static();
		$coso_ml = isset( $s_all['cosoPll'] ) ? $s_all['cosoPll'] : array();
		$mx      = isset( $s_all['tkNoMx'] ) ? $s_all['tkNoMx'] : array();

		// Tên tài khoản của các mã ĐANG dùng: ô nào khai 2 mã thì người nhập phân biệt bằng
		// tên ("64196 Chi phí khác Event" / "64197 Chi phí hoa hồng Event"), nên phải có tên.
		$can = array();
		foreach ( $mx as $per ) {
			foreach ( (array) $per as $ds ) {
				foreach ( (array) $ds as $m ) { $can[ (string) $m ] = 1; }
			}
		}
		foreach ( $loai as $x ) { if ( trim( (string) $x['tkNo'] ) !== '' ) { $can[ trim( (string) $x['tkNo'] ) ] = 1; } }
		$ten_tk = array();
		if ( count( $can ) ) {
			foreach ( VHCP_Cfg::tai_khoan() as $x ) {
				if ( isset( $can[ (string) $x['ma'] ] ) ) { $ten_tk[ (string) $x['ma'] ] = $x['ten']; }
			}
		}

		return array(
			'coso'       => $coso,
			'cosoDong'   => $coso_dong,
			'cosoPll'    => $coso_ml,
			'tkNoMx'     => $mx,
			'tenTk'      => $ten_tk,
			'nhom'       => $nhom,
			'loaiChiPhi' => $loai,
			'phanloai'   => $pl,
			'doiTuong'   => $cfg['doiTuong'],
			'qr'         => $cfg['qr'],
			'quyen'      => VHCP_Cfg::get_quyen(),
			'soDuDauKy'  => self::get_so_du_dau_ky(),
			'dons'       => self::list_dons( $cp ),
			'products'   => self::product_suggestions( $cp ),
		);
	}

	/** _productSuggestions(): mỗi tên hàng → giá/ĐVT/nhóm của lần nhập gần nhất. */
	public static function product_suggestions( $cp = null ) {
		if ( $cp === null ) { $cp = self::cp_rows(); }
		$map = array();
		foreach ( $cp as $r ) {
			$ten = trim( (string) $r['noi_dung'] );
			if ( $ten === '' ) { continue; }
			$k = mb_strtolower( $ten );
			$t = $r['tao_luc'] ? strtotime( $r['tao_luc'] ) : 0;
			if ( ! isset( $map[ $k ] ) || $t >= $map[ $k ]['_t'] ) {
				$map[ $k ] = array( 'ten' => $ten, 'gia' => VHCP_Util::num( $r['don_gia'] ), 'dvt' => (string) $r['dvt'], 'nhom' => (string) $r['nhom'], '_t' => $t );
			}
		}
		$out = array();
		foreach ( $map as $o ) { $out[] = array( 'ten' => $o['ten'], 'gia' => $o['gia'], 'dvt' => $o['dvt'], 'nhom' => $o['nhom'] ); }
		return $out;
	}

	/** listDons(): danh sách đơn + số liệu đối chiếu (mới nhất trước). */
	public static function list_dons( $cp = null ) {
		if ( $cp === null ) { $cp = self::cp_rows(); }
		$dons = self::don_rows();
		$tu   = self::tu_rows();

		$tu_sum = array(); $tu_has = array();
		foreach ( $tu as $r ) {
			$m = (string) $r['ma_don'];
			if ( $m === '' ) { continue; }
			$tu_sum[ $m ] = ( isset( $tu_sum[ $m ] ) ? $tu_sum[ $m ] : 0 ) + VHCP_Util::num( $r['so'] );
			$tu_has[ $m ] = true;
		}

		$xin = array(); $tt_cn = array(); $tt_ncc = array(); $coso_by = array();
		foreach ( $cp as $r ) {
			$m = (string) $r['ma_don'];
			if ( $m === '' ) { continue; }
			$cs = trim( (string) $r['coso'] );
			if ( $cs !== '' ) {
				if ( ! isset( $coso_by[ $m ] ) ) { $coso_by[ $m ] = array(); }
				$coso_by[ $m ][ $cs ] = ( isset( $coso_by[ $m ][ $cs ] ) ? $coso_by[ $m ][ $cs ] : 0 ) + 1;
			}
			$tt  = VHCP_Util::num( $r['thanh_tien'] );
			$tm  = VHCP_Util::blank_or_num( $r['thuc_mua'] );
			$eff = ( $tm === null ) ? $tt : $tm;
			if ( ! VHCP_Util::is_phat_sinh( $r['phat_sinh'] ) ) { $xin[ $m ] = ( isset( $xin[ $m ] ) ? $xin[ $m ] : 0 ) + $tt; }
			if ( VHCP_Util::is_ncc( $r['phan_loai_tt'], $r['cn_xu_ly'] ) ) { $tt_ncc[ $m ] = ( isset( $tt_ncc[ $m ] ) ? $tt_ncc[ $m ] : 0 ) + $eff; }
			else { $tt_cn[ $m ] = ( isset( $tt_cn[ $m ] ) ? $tt_cn[ $m ] : 0 ) + $eff; }
		}
		foreach ( $xin as $m => $v ) { if ( empty( $tu_has[ $m ] ) ) { $tu_sum[ $m ] = $v; } }

		$out = array();
		foreach ( $dons as $r ) {
			$m = (string) $r['ma_don'];
			if ( $m === '' ) { continue; }
			$du_phong = VHCP_Util::num( $r['du_phong'] );
			$bu_tru   = VHCP_Util::num( $r['bu_tru'] );
			$tu_d     = VHCP_Util::num( $r['tam_ung_duyet'] );
			$tu_tay   = ! empty( $tu_has[ $m ] ) ? ( isset( $tu_sum[ $m ] ) ? $tu_sum[ $m ] : 0 ) : ( ( isset( $xin[ $m ] ) ? $xin[ $m ] : 0 ) + $du_phong + $bu_tru );
			$ad_total = ( $tu_d > 0 ) ? $tu_d : $tu_tay;
			$has_tu   = ( $ad_total > 0 );
			$mua_cn   = isset( $tt_cn[ $m ] ) ? $tt_cn[ $m ] : 0;
			$tc_ncc   = isset( $tt_ncc[ $m ] ) ? $tt_ncc[ $m ] : 0;
			$tc       = $mua_cn + $tc_ncc;
			$tam_ung  = $has_tu ? $ad_total : $mua_cn;

			$mp = isset( $coso_by[ $m ] ) ? $coso_by[ $m ] : array();
			arsort( $mp );
			$coso = implode( ', ', array_keys( $mp ) );

			$out[] = array(
				'maDon'       => $m,
				'ky'          => VHCP_Util::fmt( $r['ky'] ),
				'nguoiLap'    => (string) $r['nguoi_lap'],
				'coso'        => $coso,
				'ngayTao'     => VHCP_Util::fmt( $r['ngay_tao'] ),
				'trangThai'   => ( $r['trang_thai'] !== '' ? $r['trang_thai'] : 'Nháp' ),
				'ghiChu'      => (string) $r['ghi_chu'],
				'nguoiDuyet'  => (string) $r['nguoi_duyet'],
				'ngayDuyet'   => VHCP_Util::fmt( $r['ngay_duyet'] ),
				'nguoiQT'     => (string) $r['nguoi_qt'],
				'ngayQT'      => VHCP_Util::fmt( $r['ngay_qt'] ),
				'chenhLechQT' => VHCP_Util::num( $r['chenh_lech_qt'] ),
				'xuLy'        => (string) $r['xu_ly'],
				'soThucMua'   => $mua_cn,
				'httt'        => (string) $r['hinh_thuc_tt'],
				'anhHoaDon'   => (string) $r['hoa_don_qt'],
				/* Gác isset: cột này thêm ở bản 1.31.0. Site nâng cấp plugin xong mà bảng chưa kịp
				   nới (dbDelta chạy ở lượt tải trang sau) thì đọc thẳng là cảnh báo tràn nhật ký
				   lỗi, và trang trắng nếu WP_DEBUG bật. */
				'anhHoaDon2'  => isset( $r['hoa_don_qt2'] ) ? (string) $r['hoa_don_qt2'] : '',
				'nguoiQTNCC'  => (string) $r['nguoi_qt_ncc'],
				'ngayQTNCC'   => VHCP_Util::fmt( $r['ngay_qt_ncc'] ),
				'qtCN'        => ( (string) $r['nguoi_qt'] !== '' ),
				'qtNCC'       => ( (string) $r['nguoi_qt_ncc'] !== '' ),
				'xuatCN'      => VHCP_Util::fmt( $r['ngay_xuat_cn'] ),
				'xuatNCC'     => VHCP_Util::fmt( $r['ngay_xuat_ncc'] ),
				'nguoiCap'    => (string) $r['nguoi_cap'],
				'ngayCap'     => VHCP_Util::fmt( $r['ngay_cap'] ),
				'htCap'       => (string) $r['ht_cap'],
				'anhCap'      => (string) $r['anh_cap'],
				'tatToan'     => ( trim( (string) $r['tat_toan'] ) !== '' ),
				'nguoiTatToan'=> (string) $r['tat_toan'],
				'ngayTatToan' => VHCP_Util::fmt( $r['ngay_tat_toan'] ),
				'tamUng'      => $tam_ung,
				'thucChi'     => $tc,
				'thucChiCN'   => $mua_cn,
				'thucChiNCC'  => $tc_ncc,
				'chenhLech'   => $tam_ung - $mua_cn,
			);
		}
		// NHÂN VIÊN CHỈ THẤY ĐƠN CỦA CHÍNH MÌNH. Chặn ở đây, tức ở NGUỒN: mọi màn hình
		// (danh sách đơn, duyệt tạm ứng, quyết toán, thừa/thiếu, báo cáo) đều lấy từ đây,
		// nên không màn nào lỡ để lộ đơn của người khác. Lọc trên giao diện thì dữ liệu
		// vẫn đã gửi xuống máy người ta rồi.
		if ( VHCP_Auth::la_nhan_vien() ) {
			$toi = mb_strtolower( trim( VHCP_Auth::nguoi() ) );
			$out = array_values( array_filter( $out, function ( $x ) use ( $toi ) {
				return mb_strtolower( trim( (string) $x['nguoiLap'] ) ) === $toi;
			} ) );
		}
		return array_reverse( $out );
	}

	/**
	 * Đơn này có phải của người đang gọi? Trả câu lỗi, '' là được phép.
	 * Nhân viên chỉ mở / sửa đơn do chính mình lập; vai trò khác thì không giới hạn.
	 */
	private static function loi_khong_phai_don_minh( $ma_don ) {
		if ( ! VHCP_Auth::la_nhan_vien() ) { return ''; }
		$d = self::don_row( $ma_don );
		if ( ! $d ) { return 'Không tìm thấy đơn'; }
		$cua = mb_strtolower( trim( (string) $d['nguoi_lap'] ) );
		$toi = mb_strtolower( trim( VHCP_Auth::nguoi() ) );
		if ( $cua === '' || $cua === $toi ) { return ''; }
		return 'Đơn này của người khác — bạn chỉ làm việc trên đơn do mình lập.';
	}

	/** Như trên nhưng tra theo ID DÒNG (dòng nào cũng thuộc một đơn). */
	private static function loi_khong_phai_dong_minh( $id ) {
		if ( ! VHCP_Auth::la_nhan_vien() ) { return ''; }
		$l = self::line_row( $id );
		if ( ! $l ) { return 'Không tìm thấy dòng'; }
		return self::loi_khong_phai_don_minh( (string) $l['ma_don'] );
	}

	// ---------------------------------------------------------------- 1 đơn

	public static function create_don( $ky, $nguoi_lap ) {
		global $wpdb;
		$m = VHCP_Util::uid( 'D' );
		$wpdb->insert( VHCP_DB::t( 'don' ), array(
			'ma_don'     => $m,
			'ky'         => (string) $ky,
			'nguoi_lap'  => (string) $nguoi_lap,
			'ngay_tao'   => VHCP_Util::now_sql(),
			'trang_thai' => 'Nháp',
			'ghi_chu'    => '',
		) );
		return VHCP_Util::ok( array( 'maDon' => $m ) );
	}

	/**
	 * getDon(): đơn + tạm ứng theo cơ sở + dòng chi + đối chiếu CN/NCC.
	 *
	 * $with_products = false: bỏ phần gợi ý sản phẩm (cần đọc cả bảng ChiPhi) —
	 * dùng khi gọi hàng loạt trong nội bộ, vd duyệt quyết toán theo lô.
	 */
	public static function get_don( $ma_don, $with_products = true ) {
		// Nhân viên mở đơn người khác qua mã đơn: chặn ở máy chủ, không tin giao diện.
		$_loi = self::loi_khong_phai_don_minh( $ma_don );
		if ( $_loi !== '' ) { return VHCP_Util::err( $_loi ); }

		$r = self::don_row( $ma_don );
		if ( ! $r ) { return VHCP_Util::err( 'Không tìm thấy đơn' ); }

		$don = array(
			'maDon'       => (string) $r['ma_don'],
			'ky'          => VHCP_Util::fmt( $r['ky'] ),
			'nguoiLap'    => (string) $r['nguoi_lap'],
			'ngayTao'     => VHCP_Util::fmt( $r['ngay_tao'] ),
			'trangThai'   => ( $r['trang_thai'] !== '' ? $r['trang_thai'] : 'Nháp' ),
			'ghiChu'      => (string) $r['ghi_chu'],
			'nguoiDuyet'  => (string) $r['nguoi_duyet'],
			'ngayDuyet'   => VHCP_Util::fmt( $r['ngay_duyet'] ),
			'nguoiQT'     => (string) $r['nguoi_qt'],
			'ngayQT'      => VHCP_Util::fmt( $r['ngay_qt'] ),
			'chenhLechQT' => VHCP_Util::num( $r['chenh_lech_qt'] ),
			'xuLy'        => (string) $r['xu_ly'],
			'soThucMua'   => VHCP_Util::out_num( $r['so_tien_thuc_mua'] ),
			'httt'        => (string) $r['hinh_thuc_tt'],
			'anhHoaDon'   => (string) $r['hoa_don_qt'],
				/* Gác isset: cột này thêm ở bản 1.31.0. Site nâng cấp plugin xong mà bảng chưa kịp
				   nới (dbDelta chạy ở lượt tải trang sau) thì đọc thẳng là cảnh báo tràn nhật ký
				   lỗi, và trang trắng nếu WP_DEBUG bật. */
				'anhHoaDon2'  => isset( $r['hoa_don_qt2'] ) ? (string) $r['hoa_don_qt2'] : '',
			'nguoiQTNCC'  => (string) $r['nguoi_qt_ncc'],
			'ngayQTNCC'   => VHCP_Util::fmt( $r['ngay_qt_ncc'] ),
			'tamUngDuyet' => VHCP_Util::out_num( $r['tam_ung_duyet'] ),
			'nguoiCap'    => (string) $r['nguoi_cap'],
			'ngayCap'     => VHCP_Util::fmt( $r['ngay_cap'] ),
			'htCap'       => (string) $r['ht_cap'],
			'anhCap'      => (string) $r['anh_cap'],
			'duPhong'     => VHCP_Util::num( $r['du_phong'] ),
			'buTru'       => VHCP_Util::num( $r['bu_tru'] ),
		);

		// Bù trừ luân chuyển: tính lại từ kỳ trước mỗi lần mở đơn (còn "Nháp" thì ghi lại),
		// và trả kèm lý do để giao diện nói rõ số ở đâu ra — ô nhập nay chỉ để xem.
		// Cơ sở đã chốt của đơn (mỗi đơn 1 cơ sở) — giao diện khóa ô chọn theo cái này
		$don['cosoDon'] = self::coso_cua_don( $ma_don );

		$bt_auto = self::chot_bu_tru( $ma_don );
		if ( (string) $don['trangThai'] === 'Nháp' ) { $don['buTru'] = VHCP_Util::num( $bt_auto['so'] ); }
		$don['buTruAuto'] = $bt_auto;

		global $wpdb;
		$tt = VHCP_DB::t( 'tamung' );
		$tam_ung = array(); $has_tu_rows = false;
		foreach ( VHCP_DB::rows( $wpdb->prepare( "SELECT * FROM $tt WHERE ma_don=%s ORDER BY id ASC", (string) $ma_don ) ) as $x ) {
			$tam_ung[ (string) $x['coso'] ] = VHCP_Util::num( $x['so'] );
			$has_tu_rows = true;
		}

		$tcp = VHCP_DB::t( 'chiphi' );
		if ( $with_products ) {
			// Gợi ý sản phẩm cần lịch sử toàn bảng -> đọc 1 lần rồi lọc trong PHP,
			// thay vì 1 lệnh lọc theo đơn + 1 lệnh đọc cả bảng như trước.
			$cp_all = self::cp_rows();
			$cp     = array();
			foreach ( $cp_all as $x ) { if ( (string) $x['ma_don'] === (string) $ma_don ) { $cp[] = $x; } }
		} else {
			$cp_all = null;
			$cp     = VHCP_DB::rows( $wpdb->prepare( "SELECT * FROM $tcp WHERE ma_don=%s ORDER BY stt ASC", (string) $ma_don ) );
		}
		$lines = array();
		foreach ( $cp as $x ) {
			$lines[] = array(
				'id'         => (string) $x['id'],
				'coso'       => (string) $x['coso'],
				'ngay'       => VHCP_Util::fmt( $x['ngay'] ),
				'phanLoaiTT' => (string) $x['phan_loai_tt'],
				'doiTuong'   => (string) $x['doi_tuong'],
				'nhom'       => (string) $x['nhom'],
				'noiDung'    => (string) $x['noi_dung'],
				'dvt'        => (string) $x['dvt'],
				'soLuong'    => VHCP_Util::num( $x['so_luong'] ),
				'donGia'     => VHCP_Util::num( $x['don_gia'] ),
				'thanhTien'  => VHCP_Util::num( $x['thanh_tien'] ),
				'ghiChu'     => (string) $x['ghi_chu'],
				'anh'        => (string) $x['anh'],
				'thueSuat'   => VHCP_Util::out_num( $x['thue_suat'] ),
				'tienThue'   => VHCP_Util::num( $x['tien_thue'] ),
				'thucMua'    => VHCP_Util::out_num( $x['thuc_mua'] ),
				'cnXuLy'     => VHCP_Util::cn_flag( $x['cn_xu_ly'] ),
				'phatSinh'   => VHCP_Util::is_phat_sinh( $x['phat_sinh'] ),
				'tkNo'       => (string) $x['tk_no'],
				'tkCo'       => (string) $x['tk_co'],
			);
		}

		// Chưa nhập tạm ứng tay -> tạm ứng xin = tổng hạng mục theo cơ sở
		// (ở "Nháp" gộp cả dòng phát sinh vì khi đó mọi dòng đều là hạng mục xin).
		if ( ! $has_tu_rows ) {
			foreach ( $lines as $l ) {
				if ( ! $l['phatSinh'] || $don['trangThai'] === 'Nháp' ) {
					$cs = $l['coso'];
					$tam_ung[ $cs ] = ( isset( $tam_ung[ $cs ] ) ? $tam_ung[ $cs ] : 0 ) + VHCP_Util::num( $l['thanhTien'] );
				}
			}
		}

		$tu_tay_sum = 0;
		foreach ( $tam_ung as $v ) { $tu_tay_sum += VHCP_Util::num( $v ); }
		if ( ! $has_tu_rows ) { $tu_tay_sum += VHCP_Util::num( $don['duPhong'] ) + VHCP_Util::num( $don['buTru'] ); }
		$tu_duyet = ( $don['tamUngDuyet'] !== '' && VHCP_Util::num( $don['tamUngDuyet'] ) > 0 ) ? VHCP_Util::num( $don['tamUngDuyet'] ) : 0;
		$ad_total = $tu_duyet > 0 ? $tu_duyet : $tu_tay_sum;
		$has_tu   = $ad_total > 0;

		$cn_by = array(); $ncc_by = array();
		foreach ( $lines as $l ) {
			$tt_  = VHCP_Util::num( $l['thanhTien'] );
			$tm   = ( $l['thucMua'] === '' || $l['thucMua'] === null ) ? null : VHCP_Util::num( $l['thucMua'] );
			$eff  = ( $tm === null ) ? $tt_ : $tm;
			if ( $l['phanLoaiTT'] === 'Nhà cung cấp' || $l['cnXuLy'] === false ) {
				$ncc_by[ $l['coso'] ] = ( isset( $ncc_by[ $l['coso'] ] ) ? $ncc_by[ $l['coso'] ] : 0 ) + $eff;
			} else {
				$cn_by[ $l['coso'] ] = ( isset( $cn_by[ $l['coso'] ] ) ? $cn_by[ $l['coso'] ] : 0 ) + $eff;
			}
		}
		ksort( $cn_by ); ksort( $ncc_by );
		$recon_cn = array(); $cn_tc = 0;
		foreach ( $cn_by as $cs => $v ) { $recon_cn[] = array( 'coso' => $cs, 'thucChi' => $v ); $cn_tc += $v; }
		$recon_ncc = array(); $ncc_tc = 0;
		foreach ( $ncc_by as $cs => $v ) { $recon_ncc[] = array( 'coso' => $cs, 'thucChi' => $v ); $ncc_tc += $v; }
		$cn_tu = $has_tu ? $ad_total : $cn_tc;

		return VHCP_Util::ok( array(
			'don'       => $don,
			'tamUng'    => $tam_ung,
			'lines'     => $lines,
			'tuMode'    => ( $has_tu ? 'new' : 'old' ),
			'reconCN'   => $recon_cn,
			'tongCN'    => array( 'tamUng' => $cn_tu, 'thucChi' => $cn_tc, 'chenhLech' => $cn_tu - $cn_tc ),
			'reconNCC'  => $recon_ncc,
			'tongNCC'   => array( 'thucChi' => $ncc_tc ),
			'products'  => $with_products ? self::product_suggestions( $cp_all ) : array(),
		) );
	}

	// ---------------------------------------------------------------- tạm ứng

	public static function set_tam_ung( $ma_don, $coso, $so ) {
		$_loi = self::loi_khong_phai_don_minh( $ma_don );
		if ( $_loi !== '' ) { return VHCP_Util::err( $_loi ); }

		global $wpdb;
		if ( ! $coso ) { return VHCP_Util::err( 'Thiếu cơ sở' ); }
		$d = self::don_row( $ma_don );
		if ( $d && (string) $d['trang_thai'] !== 'Nháp' ) { return VHCP_Util::err( 'Tạm ứng đã khóa (chỉ sửa khi đơn ở "Nháp")' ); }
		$t  = VHCP_DB::t( 'tamung' );
		$so = VHCP_Util::num( $so );
		$wpdb->query( $wpdb->prepare( "INSERT INTO $t (ma_don,coso,so) VALUES (%s,%s,%f) ON DUPLICATE KEY UPDATE so=VALUES(so)", (string) $ma_don, (string) $coso, $so ) );
		return VHCP_Util::ok();
	}

	public static function set_du_phong( $ma_don, $so ) {
		$_loi = self::loi_khong_phai_don_minh( $ma_don );
		if ( $_loi !== '' ) { return VHCP_Util::err( $_loi ); }

		$d = self::don_row( $ma_don );
		if ( ! $d ) { return VHCP_Util::err( 'Không tìm thấy đơn' ); }
		if ( (string) $d['trang_thai'] !== 'Nháp' ) { return VHCP_Util::err( 'Chỉ sửa dự phòng khi đơn ở "Nháp"' ); }
		self::upd_don( $ma_don, array( 'du_phong' => VHCP_Util::num( $so ) ) );
		return VHCP_Util::ok();
	}

	/**
	 * BÙ TRỪ LUÂN CHUYỂN KỲ TRƯỚC — HỆ THỐNG TỰ TÍNH, nhân viên không nhập.
	 *
	 * TRỤC LÀ QUẢN LÝ DUYỆT, KHÔNG PHẢI NGƯỜI LẬP ĐƠN (đổi 24/08/2026).
	 * Cơ sở chỉ là nơi TẠO đơn. Người cầm sổ chi và mang số dư tạm ứng trên TK 141 là QUẢN LÝ
	 * DUYỆT. Tiền thừa của từng gian có trả về kế toán, nhưng quản lý vẫn đang cần tiền nên
	 * khoản đó được ghi nhận lại và chuyển sang tuần sau cho chính quản lý ấy. Vì vậy con số
	 * tạm ứng và chi ra chốt THEO TUẦN CỦA QUẢN LÝ, gộp mọi đơn trong tuần đó — không phải
	 * lấy chênh lệch của riêng một đơn hay riêng một người lập.
	 *
	 * Bản cũ dò theo nguoi_lap và chỉ lấy MỘT đơn gần nhất. Sai hai đường: mỗi cơ sở một người
	 * lập nên số dư của quản lý bị xé nhỏ theo từng người, và tuần nào quản lý duyệt nhiều đơn
	 * thì chỉ một đơn được mang sang, phần còn lại rơi mất.
	 *
	 * chenhLech của đơn = tạm ứng − thực chi cá nhân: DƯƠNG là DƯ, ÂM là THIẾU. Bù trừ mang
	 * dấu ngược lại: tuần trước dư thì tuần này trừ đi, thiếu thì tuần này bù thêm.
	 *
	 * Đơn đã đánh dấu TẤT TOÁN, hoặc thuộc cơ sở ĐÃ ĐÓNG CỬA, thì không góp vào tổng — đã
	 * thu/bù xong bằng tiền, cộng tiếp là cộng hai lần.
	 *
	 * Đơn CHƯA DUYỆT thì chưa biết quản lý nào; lúc đó đoán theo quản lý gần nhất của cùng cơ
	 * sở và ghi rõ là DỰ KIẾN. Số chốt lại khi đơn được duyệt thật.
	 *
	 * @return array [ 'so', 'quanLy', 'duKien', 'kyTruoc', 'soDon', 'chenhTruoc', 'boQua', 'lyDo' ]
	 */
	public static function bu_tru_tu_dong( $ma_don, $ds = null ) {
		$d = self::don_row( $ma_don );
		if ( ! $d ) { return array( 'so' => 0, 'lyDo' => 'không thấy đơn' ); }

		if ( $ds === null ) { $ds = self::list_dons(); }
		// list_dons() trả MỚI NHẤT TRƯỚC, nên phần tử phía sau là đơn cũ hơn.
		$vi = -1; $toi = null;
		foreach ( $ds as $i => $x ) {
			if ( (string) $x['maDon'] === (string) $ma_don ) { $vi = $i; $toi = $x; break; }
		}
		if ( $vi < 0 ) { return array( 'so' => 0, 'lyDo' => 'không thấy đơn trong danh sách' ); }

		// ---- 1. QUẢN LÝ cầm TK 141 của đơn này ----
		$ql      = trim( (string) $d['nguoi_duyet'] );
		$du_kien = false;
		if ( $ql === '' ) {
			/* Chưa duyệt thì chưa có quản lý. Đoán theo quản lý gần nhất của CÙNG CƠ SỞ để
			   người lập còn thấy con số dự kiến, nhưng phải nói rõ là dự kiến — số thật chốt
			   lúc duyệt, và quản lý duyệt có thể là người khác. */
			$cs_toi = self::tach_coso( (string) $toi['coso'] );
			$n = count( $ds );
			for ( $i = $vi + 1; $i < $n; $i++ ) {
				$nd = trim( (string) $ds[ $i ]['nguoiDuyet'] );
				if ( $nd === '' ) { continue; }
				if ( array_intersect( $cs_toi, self::tach_coso( (string) $ds[ $i ]['coso'] ) ) ) {
					$ql = $nd; $du_kien = true; break;
				}
			}
			/* Đơn VỪA TẠO chưa có dòng nào nên chưa có cơ sở — đường trên không dò được gì.
			   Lùi về người lập: lấy quản lý đã duyệt đơn gần nhất của chính người này. Người lập
			   gắn với một cơ sở, cơ sở gắn với một quản lý, nên suy ra được. Vẫn là DỰ KIẾN. */
			if ( $ql === '' ) {
				$nl = trim( (string) $d['nguoi_lap'] );
				if ( $nl !== '' ) {
					for ( $i = $vi + 1; $i < $n; $i++ ) {
						if ( trim( (string) $ds[ $i ]['nguoiLap'] ) !== $nl ) { continue; }
						$nd = trim( (string) $ds[ $i ]['nguoiDuyet'] );
						if ( $nd !== '' ) { $ql = $nd; $du_kien = true; break; }
					}
				}
			}
		}
		if ( $ql === '' ) {
			return array( 'so' => 0, 'duKien' => false, 'lyDo' => 'đơn chưa duyệt và chưa có kỳ nào trước của cơ sở này — chưa dò được quản lý giữ tạm ứng' );
		}

		// ---- 2. KỲ TRƯỚC của chính quản lý đó ----
		$ky_nay   = (string) $toi['ky'];
		$ky_truoc = '';
		$n = count( $ds );
		for ( $i = $vi + 1; $i < $n; $i++ ) {
			$x = $ds[ $i ];
			if ( trim( (string) $x['nguoiDuyet'] ) !== $ql ) { continue; }
			if ( (string) $x['ky'] === $ky_nay ) { continue; }   // cùng tuần thì không phải "kỳ trước"
			$ky_truoc = (string) $x['ky'];
			break;
		}
		if ( $ky_truoc === '' ) {
			return array(
				'so'     => 0,
				'quanLy' => $ql,
				'duKien' => $du_kien,
				'lyDo'   => 'chưa có tuần nào trước đó do ' . $ql . ' duyệt' . ( $du_kien ? ' (quản lý dự kiến)' : '' ),
			);
		}

		// ---- 3. GỘP CẢ TUẦN của quản lý đó ----
		$tong = 0; $so_don = 0; $bo_qua = array();
		foreach ( $ds as $x ) {
			if ( (string) $x['ky'] !== $ky_truoc ) { continue; }
			if ( trim( (string) $x['nguoiDuyet'] ) !== $ql ) { continue; }

			if ( ! empty( $x['tatToan'] ) ) {
				$bo_qua[] = (string) $x['maDon'] . ' (đã tất toán)';
				continue;
			}
			$dong = '';
			foreach ( self::tach_coso( (string) $x['coso'] ) as $cs ) {
				$nd = VHCP_Cfg::coso_dong_cua( $cs );
				if ( $nd !== '' ) { $dong = $cs . ' đóng ' . $nd; break; }
			}
			if ( $dong !== '' ) {
				$bo_qua[] = (string) $x['maDon'] . ' (' . $dong . ')';
				continue;
			}

			$tong += VHCP_Util::num( $x['chenhLech'] );
			$so_don++;
		}

		if ( $so_don === 0 ) {
			return array(
				'so'      => 0,
				'quanLy'  => $ql,
				'duKien'  => $du_kien,
				'kyTruoc' => $ky_truoc,
				'soDon'   => 0,
				'boQua'   => $bo_qua,
				'lyDo'    => 'tuần trước (' . $ky_truoc . ') của ' . $ql . ' đã tất toán hết — không còn gì luân chuyển'
					. ( $bo_qua ? ' [' . implode( ' · ', $bo_qua ) . ']' : '' ),
			);
		}

		$so  = -$tong;   // tuần trước DƯ (chênh dương) → tuần này TRỪ; THIẾU → tuần này BÙ
		$goi = $so_don . ' đơn';
		if ( $so > 0 )     { $ly = 'tuần trước (' . $ky_truoc . ') của ' . $ql . ' — ' . $goi . ' — THIẾU ' . VHCP_Util::tien( $so ) . ' → tuần này bù thêm'; }
		elseif ( $so < 0 ) { $ly = 'tuần trước (' . $ky_truoc . ') của ' . $ql . ' — ' . $goi . ' — còn DƯ ' . VHCP_Util::tien( -$so ) . ' → tuần này trừ đi'; }
		else               { $ly = 'tuần trước (' . $ky_truoc . ') của ' . $ql . ' — ' . $goi . ' — vừa khớp, không phải bù trừ'; }
		if ( $du_kien ) { $ly .= ' · QUẢN LÝ DỰ KIẾN — chốt lại khi duyệt'; }
		if ( $bo_qua )  { $ly .= ' · bỏ qua: ' . implode( ' · ', $bo_qua ); }

		return array(
			'so'         => $so,
			'quanLy'     => $ql,
			'duKien'     => $du_kien,
			'kyTruoc'    => $ky_truoc,
			'soDon'      => $so_don,
			'chenhTruoc' => $tong,
			'boQua'      => $bo_qua,
			'lyDo'       => $ly,
		);
	}

	/** Cột coso của list_dons() có thể ghép nhiều cơ sở ("A, B") — tách ra mới tra đóng cửa được. */
	private static function tach_coso( $chuoi ) {
		$ra = array();
		foreach ( explode( ',', (string) $chuoi ) as $c ) {
			$c = trim( $c );
			if ( $c !== '' ) { $ra[] = $c; }
		}
		return $ra;
	}

	/** Ghi lại bù trừ tự tính (chỉ khi đơn còn ở "Nháp" — gửi duyệt rồi thì chốt số). */
	private static function chot_bu_tru( $ma_don, $ds = null ) {
		$bt = self::bu_tru_tu_dong( $ma_don, $ds );
		$d  = self::don_row( $ma_don );
		if ( ! $d ) { return $bt; }
		if ( (string) $d['trang_thai'] !== 'Nháp' ) { return $bt; }
		if ( VHCP_Util::num( $d['bu_tru'] ) !== VHCP_Util::num( $bt['so'] ) ) {
			self::upd_don( $ma_don, array( 'bu_tru' => VHCP_Util::num( $bt['so'] ) ) );
		}
		return $bt;
	}

	public static function set_tu_extra( $ma_don, $du_phong, $bu_tru ) {
		$_loi = self::loi_khong_phai_don_minh( $ma_don );
		if ( $_loi !== '' ) { return VHCP_Util::err( $_loi ); }

		$d = self::don_row( $ma_don );
		if ( ! $d ) { return VHCP_Util::err( 'Không tìm thấy đơn' ); }
		if ( (string) $d['trang_thai'] !== 'Nháp' ) { return VHCP_Util::err( 'Chỉ sửa khi đơn ở "Nháp"' ); }
		// $bu_tru gửi lên bị BỎ QUA: số này do hệ thống tính từ kỳ trước, không nhận từ
		// giao diện. Chặn ở máy chủ chứ không chỉ khoá ô nhập — khoá ô chỉ là lớp sơn.
		self::upd_don( $ma_don, array( 'du_phong' => VHCP_Util::num( $du_phong ) ) );
		self::chot_bu_tru( $ma_don );
		return VHCP_Util::ok( array( 'buTru' => VHCP_Util::num( self::don_row( $ma_don )['bu_tru'] ) ) );
	}

	// ---------------------------------------------------------------- dòng chi phí

	/** _lineArr(): dựng bản ghi 1 dòng chi từ dữ liệu giao diện gửi lên. */
	private static function line_data( $id, $ma_don, $rec ) {
		$rec = (array) $rec;
		$get = function ( $k ) use ( $rec ) { return isset( $rec[ $k ] ) ? $rec[ $k ] : null; };

		$sl = VHCP_Util::num( $get( 'soLuong' ) );
		$dg = VHCP_Util::num( $get( 'donGia' ) );
		$tt = $sl * $dg;
		$tt_in = $get( 'thanhTien' );
		if ( $tt_in !== null && $tt_in !== '' && is_numeric( str_replace( array( ',', ' ' ), '', (string) $tt_in ) ) ) {
			$tt = VHCP_Util::num( $tt_in );
		}
		$ts    = VHCP_Util::blank_or_num( $get( 'thueSuat' ) );
		$tthue = ( $ts === null ) ? null : round( $tt * $ts / 100 );
		$tm    = VHCP_Util::blank_or_num( $get( 'thucMua' ) );
		$cnv   = $get( 'cnXuLy' );
		$cn    = ( $cnv === 0 || $cnv === false || $cnv === '0' ) ? 0 : 1;
		$ps    = VHCP_Util::is_phat_sinh( $get( 'phatSinh' ) ) ? 1 : 0;
		$ngay  = VHCP_Util::parse_date( $get( 'ngay' ) );
		if ( ! $ngay ) { $ngay = VHCP_Util::today_sql(); }   // không nhập ngày -> lấy ngày nhập

		// GẮN MÃ TÀI KHOẢN NGAY LÚC NHẬP: TK Nợ lấy theo LOẠI CHI PHÍ (danh mục), TK Có theo
		// phân loại thanh toán. Nhờ vậy dò lại một dòng chỉ cần đọc cột mã, không phải chạy
		// lại hàm dò ma trận. (Xuất MISA vẫn ưu tiên TK Có của người duyệt tạm ứng như cũ.)
		$tk = self::tk_of_line( $get( 'nhom' ), $get( 'phanLoaiTT' ), $get( 'coso' ) );

		return array(
			'id'           => (string) $id,
			'ma_don'       => (string) $ma_don,
			'coso'         => VHCP_Util::st( $get( 'coso' ) ),
			'ngay'         => $ngay,
			'phan_loai_tt' => VHCP_Util::st( $get( 'phanLoaiTT' ) ),
			'doi_tuong'    => VHCP_Util::st( $get( 'doiTuong' ) ),
			'nhom'         => VHCP_Util::st( $get( 'nhom' ) ),
			'noi_dung'     => VHCP_Util::st( $get( 'noiDung' ) ),
			'dvt'          => VHCP_Util::st( $get( 'dvt' ) ),
			'so_luong'     => VHCP_Util::blank_or_num( $get( 'soLuong' ) ),
			'don_gia'      => VHCP_Util::blank_or_num( $get( 'donGia' ) ),
			'thanh_tien'   => $tt,
			'ghi_chu'      => VHCP_Util::st( $get( 'ghiChu' ) ),
			'anh'          => VHCP_Util::st( $get( 'anh' ) ),
			'tao_luc'      => VHCP_Util::now_sql(),
			'thue_suat'    => $ts,
			'tien_thue'    => $tthue,
			'thuc_mua'     => $tm,
			'cn_xu_ly'     => $cn,
			'phat_sinh'    => $ps,
			'tk_no'        => $tk['tk_no'],
			'tk_co'        => $tk['tk_co'],
		);
	}

	/** Mã tài khoản của 1 dòng chi: TK Nợ theo loại chi phí, TK Có theo phân loại thanh toán. */
	public static function tk_of_line( $nhom, $phan_loai_tt, $coso = '' ) {
		$cat   = VHCP_Cfg::loai_tk( $nhom );
		// Nợ tra qua tkno_loai(): có ma trận [loại × mảng của cơ sở] và chịu được tên nhóm
		// còn đuôi "- NCC" / "- Mua lẻ", nên dòng nhập theo tên nhóm vẫn ra đúng mã chi phí.
		$tk_no = VHCP_Cfg::tkno_loai( $nhom, $coso );
		if ( $tk_no === '' ) { $tk_no = $cat['tkNo']; }
		$tk_co = $cat['tkCo'];
		if ( $tk_co === '' ) {
			$pl  = ( trim( (string) $phan_loai_tt ) === 'Nhà cung cấp' ) ? 'Nhà cung cấp' : 'Thanh toán cá nhân';
			$cfg = VHCP_Cfg::cfg_static();
			foreach ( (array) $cfg['phanloai'] as $x ) {
				if ( trim( (string) $x['ten'] ) === $pl ) { $tk_co = (string) $x['tkCo']; break; }
			}
		}
		return array( 'tk_no' => $tk_no, 'tk_co' => $tk_co );
	}

	/**
	 * Gán mã tài khoản cho các dòng chi CŨ (nhập trước khi có danh mục loại chi phí).
	 * $all = true thì áp lại cho mọi dòng; mặc định chỉ điền chỗ còn trống.
	 */
	public static function gan_ma_tai_khoan( $all = false ) {
		global $wpdb;
		$t = VHCP_DB::t( 'chiphi' );
		$n = 0; $thieu = array();
		foreach ( self::cp_rows() as $r ) {
			$thieu_ma = ( trim( (string) $r['tk_no'] ) === '' || trim( (string) $r['tk_co'] ) === '' );
			if ( ! $all && ! $thieu_ma ) { continue; }
			$tk = self::tk_of_line( $r['nhom'], $r['phan_loai_tt'], isset( $r['coso'] ) ? $r['coso'] : '' );
			if ( $tk['tk_no'] === '' && trim( (string) $r['nhom'] ) !== '' ) { $thieu[ (string) $r['nhom'] ] = 1; }
			if ( $tk['tk_no'] === (string) $r['tk_no'] && $tk['tk_co'] === (string) $r['tk_co'] ) { continue; }
			$wpdb->update( $t, array( 'tk_no' => $tk['tk_no'], 'tk_co' => $tk['tk_co'] ), array( 'id' => (string) $r['id'] ) );
			$n++;
		}
		return VHCP_Util::ok( array( 'updated' => $n, 'thieuMa' => array_keys( $thieu ) ) );
	}

	/**
	 * MỘT ĐƠN = MỘT CƠ SỞ.
	 *
	 * Đơn tạm ứng là tiền giao cho một người ở MỘT cơ sở, rồi đối chiếu thừa/thiếu theo
	 * cơ sở đó. Trộn hai cơ sở vào một đơn thì phần đối chiếu và mã đơn vị xuất MISA đều
	 * không còn quy được về đâu. Đơn đã có dòng thì chốt luôn cơ sở của đơn.
	 *
	 * @return string cơ sở của đơn, '' nếu đơn chưa có dòng nào (còn tự do chọn)
	 */
	public static function coso_cua_don( $ma_don ) {
		global $wpdb;
		$t = VHCP_DB::t( 'chiphi' );
		$v = $wpdb->get_var( $wpdb->prepare(
			"SELECT coso FROM $t WHERE ma_don=%s AND coso<>'' ORDER BY id ASC LIMIT 1",
			(string) $ma_don
		) );
		return trim( (string) $v );
	}

	/** Cơ sở gửi lên có khớp cơ sở đã chốt của đơn? Trả câu lỗi, '' là hợp lệ. */
	private static function loi_khac_coso( $ma_don, $coso ) {
		$moi = trim( (string) $coso );
		if ( $moi === '' ) { return ''; }
		$cu = self::coso_cua_don( $ma_don );
		if ( $cu === '' || mb_strtolower( $cu ) === mb_strtolower( $moi ) ) { return ''; }
		return 'Đơn này là của cơ sở "' . $cu . '" — mỗi đơn chỉ nhập 1 cơ sở.'
			. ' Muốn nhập cho "' . $moi . '" thì tạo đơn mới.';
	}

	/**
	 * SỐ LƯỢNG LÀ BẮT BUỘC KHI NHẬP HẠNG MỤC.
	 *
	 * Thành tiền = số lượng × đơn giá. Bỏ trống số lượng thì dòng có đơn giá 81.000 vẫn
	 * ghi xuống THÀNH TIỀN 0: tạm ứng xin thiếu đúng bằng số đó, mà nhìn bảng không thấy
	 * sai chỗ nào — số 0 trông y như một dòng chưa điền. Chặn ở máy chủ chứ không chỉ ở
	 * giao diện: app trên máy nào cũng gọi được cổng này.
	 *
	 * Dòng gõ thẳng THÀNH TIỀN (không qua đơn giá) vẫn cho qua — đó là cách nhập hợp lệ.
	 */
	private static function loi_thieu_so_luong( $rec ) {
		$rec = (array) $rec;
		$g   = function ( $k ) use ( $rec ) { return isset( $rec[ $k ] ) ? trim( (string) $rec[ $k ] ) : ''; };
		$sl  = VHCP_Util::blank_or_num( $g( 'soLuong' ) );
		if ( $sl !== null && $sl > 0 ) { return ''; }
		$dg  = VHCP_Util::blank_or_num( $g( 'donGia' ) );
		$tt  = VHCP_Util::blank_or_num( $g( 'thanhTien' ) );
		// Không có đơn giá lẫn thành tiền -> dòng trống, để chỗ khác báo; ở đây chỉ lo
		// đúng cảnh "có tiền mà không có số lượng".
		if ( ( $dg === null || $dg <= 0 ) && ( $tt === null || $tt <= 0 ) ) { return ''; }
		if ( $dg !== null && $dg > 0 ) {
			return 'Thiếu SỐ LƯỢNG: đơn giá ' . number_format( $dg, 0, ',', '.' )
				. 'đ mà số lượng trống thì thành tiền ra 0 — tạm ứng sẽ xin thiếu đúng số đó.';
		}
		return '';
	}

	public static function add_line( $ma_don, $rec ) {
		$_loi = self::loi_khong_phai_don_minh( $ma_don );
		if ( $_loi !== '' ) { return VHCP_Util::err( $_loi ); }

		global $wpdb;
		$rec = (array) $rec;
		$st  = self::state( $ma_don );
		$_c  = self::vi_sao_khong_sua( $ma_don );
		if ( $_c !== '' ) { return VHCP_Util::err( $_c ); }
		/* Đơn còn ở "Nháp" thì dòng thêm vào là HẠNG MỤC XIN (nằm trong số tạm ứng xin); gửi
		   duyệt rồi thì mọi dòng thêm sau đó là PHÁT SINH — mua thêm ngoài dự kiến. Phân biệt
		   theo trạng thái LÚC THÊM chứ không hỏi giao diện: hỏi giao diện là ai cũng khai được
		   một khoản mua thêm thành "đã xin từ đầu", và bảng đối chiếu thừa/thiếu mất nghĩa. */
		$ps = ( $st === 'Nháp' ) ? 0 : 1;
		$loi = self::loi_khac_coso( $ma_don, isset( $rec['coso'] ) ? $rec['coso'] : '' );
		if ( $loi !== '' ) { return VHCP_Util::err( $loi ); }
		// Gian đã đóng thì không nhận chi mới — đóng gian là đã chốt sổ với cơ sở đó.
		$cs_moi = trim( (string) ( isset( $rec['coso'] ) ? $rec['coso'] : '' ) );
		if ( $cs_moi !== '' ) {
			$nd = VHCP_Cfg::coso_dong_cua( $cs_moi );
			if ( $nd !== '' ) {
				return VHCP_Util::err( 'Cơ sở "' . $cs_moi . '" đã đóng cửa ngày ' . $nd . ' — không nhập chi phí mới cho gian đã đóng.' );
			}
		}
		$loi_sl = self::loi_thieu_so_luong( $rec );
		if ( $loi_sl !== '' ) { return VHCP_Util::err( $loi_sl ); }
		$rec['phatSinh'] = $ps;
		$id   = VHCP_Util::uid( 'L' );
		$data = self::line_data( $id, $ma_don, $rec );
		$wpdb->insert( VHCP_DB::t( 'chiphi' ), $data );
		self::ghi_vet( $ma_don, ( $ps ? 'Thêm dòng phát sinh' : 'Thêm hạng mục xin' ),
			self::ta_dong( $data ) . ' · lúc đơn ở "' . $st . '"' );
		return VHCP_Util::ok( array( 'id' => $id, 'phatSinh' => $ps ) );
	}

	public static function update_line( $id, $rec ) {
		$_loi = self::loi_khong_phai_dong_minh( $id );
		if ( $_loi !== '' ) { return VHCP_Util::err( $_loi ); }

		global $wpdb;
		$cur = self::line_row( $id );
		if ( ! $cur ) { return VHCP_Util::err( 'Không tìm thấy dòng' ); }
		$ma_don = (string) $cur['ma_don'];
		$ps     = VHCP_Util::is_phat_sinh( $cur['phat_sinh'] );
		$info   = self::info( $ma_don );
		$st     = $info['st'];
		/* 🔴 MỘT RANH GIỚI: chưa chốt sổ thì sửa được, chốt rồi thì thôi. Trước đây hạng mục xin
		   và dòng phát sinh có hai luật khác nhau, và cả hai đều khoá ở "Chờ duyệt tạm ứng" —
		   tức là đúng lúc quản lý trả lời "sửa lại đi rồi anh duyệt" thì nhân viên không sửa
		   được, phải nhờ người khác trả đơn về Nháp. */
		$_c = self::vi_sao_khong_sua( $ma_don );
		if ( $_c !== '' ) { return VHCP_Util::err( $_c ); }

		$rec = (array) $rec;
		// Sửa dòng cũng không được đổi sang cơ sở khác — trừ khi đây là dòng duy nhất
		// đang giữ cơ sở của đơn (lúc đó đổi là đổi cả đơn, hợp lý).
		if ( isset( $rec['coso'] ) && trim( (string) $rec['coso'] ) !== '' ) {
			global $wpdb;
			$tc  = VHCP_DB::t( 'chiphi' );
			$khac = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM $tc WHERE ma_don=%s AND coso<>'' AND id<>%s",
				$ma_don, (string) $id
			) );
			if ( $khac > 0 ) {
				$loi2 = self::loi_khac_coso( $ma_don, $rec['coso'] );
				if ( $loi2 !== '' ) { return VHCP_Util::err( $loi2 ); }
			}
		}
		if ( ! array_key_exists( 'thucMua', $rec ) ) { $rec['thucMua'] = VHCP_Util::out_num( $cur['thuc_mua'] ); }
		if ( ! array_key_exists( 'cnXuLy', $rec ) )  { $rec['cnXuLy']  = (int) $cur['cn_xu_ly']; }
		$rec['phatSinh'] = $ps ? 1 : 0;

		$loi_sl = self::loi_thieu_so_luong( $rec );
		if ( $loi_sl !== '' ) { return VHCP_Util::err( $loi_sl ); }

		$data = self::line_data( $id, $ma_don, $rec );
		$data['tao_luc'] = $cur['tao_luc'] ? $cur['tao_luc'] : VHCP_Util::now_sql();
		// Danh mục chưa khai mã -> giữ mã cũ của dòng, không ghi rỗng lên. Trừ mã của BÊN TRẢ
		// TIỀN (141/331): đó là TK Có, giữ lại ở cột Nợ là bê nguyên lỗi cũ đi tiếp.
		if ( $data['tk_no'] === '' && trim( (string) $cur['tk_no'] ) !== ''
			&& ! VHCP_Cfg::la_tk_ben_tra( $cur['tk_no'] ) ) { $data['tk_no'] = $cur['tk_no']; }
		if ( $data['tk_co'] === '' && trim( (string) $cur['tk_co'] ) !== '' ) { $data['tk_co'] = $cur['tk_co']; }
		unset( $data['id'] );
		$wpdb->update( VHCP_DB::t( 'chiphi' ), $data, array( 'id' => (string) $id ) );
		/* 🔴 Ghi CŨ -> MỚI, không chỉ ghi "đã sửa". Sau khi sửa thì giá trị cũ không còn ở đâu
		   nữa — nhật ký là bản sao duy nhất. Chỉ ghi khi có gì đó THẬT SỰ đổi: mở màn sửa rồi
		   bấm Lưu mà không đụng gì cũng ghi thì sổ đầy dòng vô nghĩa, và dòng đáng đọc chìm mất. */
		$cu_ta  = self::ta_dong( $cur );
		$moi_ta = self::ta_dong( array_merge( (array) $cur, $data ) );
		if ( $cu_ta !== $moi_ta ) {
			self::ghi_vet( $ma_don, ( $ps ? 'Sửa dòng phát sinh' : 'Sửa hạng mục xin' ),
				$cu_ta . '  →  ' . $moi_ta . ' · lúc đơn ở "' . $st . '"' );
		}
		return VHCP_Util::ok();
	}

	public static function set_line_thuc_mua( $id, $val, $actor = '' ) {
		global $wpdb;
		$cur = self::line_row( $id );
		if ( ! $cur ) { return VHCP_Util::err( 'Không tìm thấy dòng' ); }
		$ma_don = (string) $cur['ma_don'];
		$st     = self::state( $ma_don );
		if ( $st !== 'Đã cấp tạm ứng' && $st !== 'Chờ quyết toán' ) {
			return VHCP_Util::err( 'Chỉ nhập Thực chi khi "Đã cấp tạm ứng" (hoặc kế toán sửa khi "Chờ quyết toán")' );
		}
		$v = VHCP_Util::blank_or_num( $val );
		$cu_tm = ( '' !== $cur['thuc_mua'] && null !== $cur['thuc_mua'] )
			? VHCP_Util::num( $cur['thuc_mua'] ) : null;
		$wpdb->update( VHCP_DB::t( 'chiphi' ), array( 'thuc_mua' => $v ), array( 'id' => (string) $id ) );
		if ( $cu_tm !== ( null === $v ? null : VHCP_Util::num( $v ) ) ) {
			$_ts = function ( $x ) { return ( null === $x ) ? '(trống)' : number_format( $x, 0, ',', '.' ) . 'đ'; };
			self::ghi_vet( $ma_don, 'Nhập thực chi',
				trim( (string) $cur['noi_dung'] ) . ': ' . $_ts( $cu_tm ) . '  →  '
					. $_ts( null === $v ? null : VHCP_Util::num( $v ) ) );
		}
		$kt_sua = false;
		if ( $st === 'Chờ quyết toán' ) { self::mark_kt_sua( $ma_don, $actor ); $kt_sua = true; }
		return VHCP_Util::ok( array( 'ktSua' => $kt_sua ) );
	}

	public static function set_line_cn( $id, $on ) {
		global $wpdb;
		if ( ! self::line_row( $id ) ) { return VHCP_Util::err( 'Không tìm thấy dòng' ); }
		$wpdb->update( VHCP_DB::t( 'chiphi' ), array( 'cn_xu_ly' => $on ? 1 : 0 ), array( 'id' => (string) $id ) );
		return VHCP_Util::ok();
	}

	/**
	 * SỬA NGÀY CỦA MỘT DÒNG NGAY TẠI CHỖ.
	 *
	 * Cùng luật trạng thái với set_line_anh: đang nhập / đã cấp tạm ứng / chờ quyết toán.
	 * Đơn đã quyết toán hay đã xuất MISA thì KHÔNG cho sửa — số đã đi vào sổ.
	 */
	public static function set_line_ngay( $id, $ngay ) {
		$_loi = self::loi_khong_phai_dong_minh( $id );
		if ( $_loi !== '' ) { return VHCP_Util::err( $_loi ); }

		global $wpdb;
		$cur = self::line_row( $id );
		if ( ! $cur ) { return VHCP_Util::err( 'Không tìm thấy dòng' ); }
		// ADMIN SỬA ĐƯỢC NGÀY Ở MỌI TRẠNG THÁI.
		//
		// Bình thường đơn đã quyết toán / đã xuất MISA thì khoá, vì số đã đi vào sổ. Nhưng
		// đang có 580 dòng mang ngày hỏng ("22/08/4621") NẰM ĐÚNG trong đám đơn đã khoá đó —
		// khoá luôn thì không đường nào sửa, mà tệp gửi MISA thì đang sai kỳ hạch toán.
		// Người khác vẫn theo luật cũ.
		$st = self::state( (string) $cur['ma_don'] );
		$la_admin = ( VHCP_Auth::vai_tro() === 'Admin' );
		if ( ! $la_admin ) {
			$_c = self::vi_sao_khong_sua( (string) $cur['ma_don'] );
			if ( $_c !== '' ) { return VHCP_Util::err( $_c . ' (nhờ Admin sửa)' ); }
		}
		$moi = VHCP_Util::parse_date( $ngay );
		if ( ! $moi ) { return VHCP_Util::err( 'Ngày không đọc được: ' . $ngay ); }
		if ( VHCP_Util::ngay_vo_ly( VHCP_Util::fmt( $moi ) ) ) {
			return VHCP_Util::err( 'Ngày vô lý: ' . VHCP_Util::fmt( $moi ) );
		}
		$wpdb->update( VHCP_DB::t( 'chiphi' ), array( 'ngay' => $moi ), array( 'id' => (string) $id ) );
		return VHCP_Util::ok( array( 'ngay' => VHCP_Util::fmt( $moi ) ) );
	}

	/**
	 * DÒ / SỬA HÀNG LOẠT NGÀY CÓ NĂM VÔ LÝ (VD "22/08/4625").
	 *
	 * $nam = 0 -> chỉ DÒ, trả danh sách để xem trước, không đụng dữ liệu.
	 * $nam = 2026 -> giữ nguyên NGÀY và THÁNG, chỉ thay năm.
	 *
	 * ⚠️ Đây là sửa SỐ LIỆU KẾ TOÁN nên KHÔNG bao giờ tự chạy: phải người có quyền bấm,
	 *    xem trước danh sách, rồi mới xác nhận. Giữ nguyên ngày/tháng vì đó là phần duy
	 *    nhất còn tin được — đoán luôn cả ngày thì thành bịa.
	 */
	/**
	 * NGÀY BẮT ĐẦU CỦA MỘT KỲ, đọc từ nhãn kỳ: "T8/2026 (10/8-16/8/2026)" -> 2026-08-10.
	 * Không đọc được thì trả '' — thà bỏ qua còn hơn đoán bừa một ngày hạch toán.
	 */
	public static function ngay_dau_ky( $ky ) {
		$s = trim( (string) $ky );
		if ( preg_match( '#\((\d{1,2})/(\d{1,2})\s*-\s*(\d{1,2})/(\d{1,2})/(\d{4})\)#', $s, $m ) ) {
			$d = (int) $m[1]; $mo = (int) $m[2]; $y = (int) $m[5];
			if ( $mo > (int) $m[4] ) { $y--; }   // khoảng vắt qua năm mới
			if ( checkdate( $mo, $d, $y ) ) { return sprintf( '%04d-%02d-%02d', $y, $mo, $d ); }
		}
		if ( preg_match( '#^T\s*(\d{1,2})\s*/\s*(\d{4})#iu', $s, $m ) ) {
			$mo = (int) $m[1]; $y = (int) $m[2];
			if ( $mo >= 1 && $mo <= 12 ) { return sprintf( '%04d-%02d-01', $y, $mo ); }
		}
		return '';
	}

	/**
	 * KHÔI PHỤC NGÀY THẬT TỪ NĂM BỊ HỎNG.
	 *
	 * Nguồn gốc: ô ngày trong bảng tính xuất ra SỐ SÊ-RI ("46213.0" = số ngày kể từ
	 * 30/12/1899). parse_date() cũ rơi xuống strtotime(), nó đọc "4621" thành NĂM rồi lấy
	 * ngày/tháng của hôm nhập -> "23/08/4621". Tức là 4 chữ số đầu của sê-ri VẪN CÒN nằm
	 * trong cột năm; chỉ mất chữ số cuối.
	 *
	 * Nên năm 4621 => sê-ri thuộc [46210 … 46219] => 10 ngày ứng viên: 07/07 … 16/07/2026.
	 *
	 * ⚠️ CHỮ SỐ CUỐI MẤT HẲN, KHÔNG DỰNG LẠI ĐƯỢC. Giao với KHOẢNG KỲ của đơn (một tuần)
	 *    thì vẫn còn tới 6–7 ứng viên, nên đây KHÔNG phải phép khôi phục chính xác — nó chỉ
	 *    thu hẹp về đúng TUẦN. Chỉ khi nào giao lại còn ĐÚNG MỘT ngày (kỳ nằm lọt trong
	 *    khoảng sê-ri) thì mới là ngày thật.
	 *
	 *    MUỐN ĐÚNG TỪNG NGÀY thì NẠP LẠI từ bảng tính gốc — parse_date() nay đã đọc đúng
	 *    số sê-ri, nạp lại là ra ngày thật.
	 *
	 * @return array [ngay, soUngVien, dsUngVien] — ngay chỉ khác '' khi CHỈ CÓ MỘT ứng viên
	 */
	public static function ngay_tu_nam_hong( $nam_hong, $ky ) {
		$nam = (int) $nam_hong;
		if ( $nam < 4000 || $nam > 6000 ) { return array( '', 0, array() ); }

		list( $dau, $cuoi ) = self::khoang_ky( $ky );
		$ung = array();
		for ( $i = 0; $i <= 9; $i++ ) {
			$seri = $nam * 10 + $i;
			if ( $seri < 20000 || $seri > 60000 ) { continue; }
			$d = gmdate( 'Y-m-d', ( $seri - 25569 ) * 86400 );
			if ( $dau !== '' && ( $d < $dau || $d > $cuoi ) ) { continue; }
			$ung[] = $d;
		}
		if ( count( $ung ) === 1 ) { return array( $ung[0], 1, $ung ); }
		return array( '', count( $ung ), $ung );
	}

	/**
	 * NHÃN KỲ CHUẨN của tuần chứa một ngày: "T7/2026 (6/7-12/7/2026)" (Thứ 2 → Chủ nhật).
	 * Dùng để dựng lại cột KỲ cho đơn bị ghi bằng số sê-ri bảng tính ("46204.0").
	 */
	public static function nhan_ky( $ngay_iso ) {
		$ts = strtotime( (string) $ngay_iso . ' 00:00:00 UTC' );
		if ( ! $ts ) { return ''; }
		$thu = (int) gmdate( 'N', $ts );                 // 1 = thứ 2
		$d1  = $ts - ( $thu - 1 ) * 86400;
		$d2  = $d1 + 6 * 86400;
		return 'T' . (int) gmdate( 'n', $d2 ) . '/' . gmdate( 'Y', $d2 )
			. ' (' . (int) gmdate( 'j', $d1 ) . '/' . (int) gmdate( 'n', $d1 )
			. '-' . (int) gmdate( 'j', $d2 ) . '/' . (int) gmdate( 'n', $d2 ) . '/' . gmdate( 'Y', $d2 ) . ')';
	}

	/**
	 * DỰNG LẠI CỘT KỲ CỦA ĐƠN BỊ GHI BẰNG SỐ SÊ-RI BẢNG TÍNH.
	 *
	 * Kỳ hỏng ("46204.0") kéo theo: đơn không lọc được theo tháng/tuần ở bất kỳ màn nào, và
	 * cũng không suy ra được ngày cho các dòng chi của nó. Kỳ thì KHÔI PHỤC ĐƯỢC CHÍNH XÁC
	 * vì sê-ri còn nguyên vẹn trong ô (chưa bị strtotime nghiền như cột ngày).
	 *
	 * $chot = false -> chỉ DÒ, trả danh sách xem trước.
	 */
	public static function sua_ky_hong( $chot = false ) {
		global $wpdb;
		$t = VHCP_DB::t( 'don' );
		$ds = array(); $sua = 0;
		foreach ( self::don_rows() as $d ) {
			$ky = trim( (string) $d['ky'] );
			if ( ! preg_match( '#^\d{5}(?:\.0+)?$#', $ky ) ) { continue; }
			$iso = VHCP_Util::parse_date( $ky );
			$moi = $iso ? self::nhan_ky( $iso ) : '';
			$ds[] = array( 'maDon' => (string) $d['ma_don'], 'cu' => $ky,
				'ngay' => $iso ? VHCP_Util::fmt( $iso ) : '', 'moi' => $moi );
			if ( $chot && $moi !== '' ) {
				$wpdb->update( $t, array( 'ky' => $moi ), array( 'ma_don' => (string) $d['ma_don'] ) );
				$sua++;
			}
		}
		return VHCP_Util::ok( array( 'items' => array_slice( $ds, 0, 400 ), 'tong' => count( $ds ), 'daSua' => $sua ) );
	}

	/** Khoảng ngày của một kỳ: "T8/2026 (10/8-16/8/2026)" -> ['2026-08-10','2026-08-16']. */
	public static function khoang_ky( $ky ) {
		$s = trim( (string) $ky );
		if ( preg_match( '#\((\d{1,2})/(\d{1,2})\s*-\s*(\d{1,2})/(\d{1,2})/(\d{4})\)#', $s, $m ) ) {
			$y2 = (int) $m[5]; $m1 = (int) $m[2]; $m2 = (int) $m[4];
			$y1 = ( $m1 > $m2 ) ? $y2 - 1 : $y2;
			if ( checkdate( $m1, (int) $m[1], $y1 ) && checkdate( $m2, (int) $m[3], $y2 ) ) {
				return array( sprintf( '%04d-%02d-%02d', $y1, $m1, (int) $m[1] ),
					sprintf( '%04d-%02d-%02d', $y2, $m2, (int) $m[3] ) );
			}
		}
		if ( preg_match( '#^T\s*(\d{1,2})\s*/\s*(\d{4})#iu', $s, $m ) ) {
			$mo = (int) $m[1]; $y = (int) $m[2];
			if ( $mo >= 1 && $mo <= 12 ) {
				return array( sprintf( '%04d-%02d-01', $y, $mo ),
					sprintf( '%04d-%02d-%02d', $y, $mo, (int) gmdate( 't', gmmktime( 0, 0, 0, $mo, 1, $y ) ) ) );
			}
		}
		return array( '', '' );
	}

	/**
	 * DÒ / SỬA HÀNG LOẠT NGÀY HỎNG (VD "22/08/4621" — 580 dòng cùng một ngày).
	 *
	 * $cach:
	 *   'ky'  — lấy NGÀY ĐẦU KỲ CỦA ĐƠN. Dùng khi cả ngày lẫn tháng đều không tin được:
	 *           580 dòng mà cùng một ngày 22/08 trong khi đơn trải từ tháng 7 sang tháng 8
	 *           thì ngày/tháng đó rõ ràng không phải ngày mua thật. Kỳ mới là thứ đúng.
	 *   'nam' — GIỮ ngày và tháng, chỉ thay năm. Dùng khi chỉ mỗi năm sai.
	 *
	 * $chot = false -> chỉ DÒ và trả danh sách xem trước, KHÔNG đụng dữ liệu.
	 *
	 * ⚠️ Sửa số liệu kế toán nên không bao giờ tự chạy: phải Admin bấm, xem trước, xác nhận.
	 */
	public static function sua_ngay_hong( $cach = 'ky', $nam = 0, $chot = false, $ma_don = '' ) {
		global $wpdb;
		// Kỳ hỏng thì không suy ra được ngày cho dòng của đơn đó -> vá kỳ trước.
		if ( $chot ) { self::sua_ky_hong( true ); }
		$cach = in_array( $cach, array( 'nam', 'ky', 'seri' ), true ) ? $cach : 'seri';
		$nam  = (int) $nam;
		if ( $cach === 'nam' && $chot && ( $nam < 2000 || $nam > 2100 ) ) { return VHCP_Util::err( 'Năm phải trong khoảng 2000–2100' ); }

		$ky_by = array();
		foreach ( self::don_rows() as $d ) { $ky_by[ (string) $d['ma_don'] ] = (string) $d['ky']; }

		$t   = VHCP_DB::t( 'chiphi' );
		$sql = "SELECT id, ma_don, ngay, noi_dung FROM $t";
		if ( trim( (string) $ma_don ) !== '' ) { $sql = $wpdb->prepare( "SELECT id, ma_don, ngay, noi_dung FROM $t WHERE ma_don=%s", $ma_don ); }

		$ds = array(); $sua = 0; $bo = 0; $uoc_n = 0;
		foreach ( VHCP_DB::rows( $sql ) as $r ) {
			$dmy = VHCP_Util::fmt( $r['ngay'] );
			if ( ! VHCP_Util::ngay_vo_ly( $dmy ) ) { continue; }
			$m_don = (string) $r['ma_don'];
			$ky    = isset( $ky_by[ $m_don ] ) ? $ky_by[ $m_don ] : '';
			$moi   = '';
			$ung = 0; $uoc = 0;
			if ( $cach === 'seri' ) {
				// Sê-ri thu hẹp về đúng TUẦN. Còn đúng 1 ứng viên -> đó là ngày thật. Còn
				// nhiều -> lùi về ĐẦU KỲ và ĐÁNH DẤU là ước lượng, để bảng xem trước nói
				// thẳng chỗ nào chắc chỗ nào không, chứ không trộn lẫn hai loại.
				if ( preg_match( '#^(\d{4})-#', (string) $r['ngay'], $mm ) ) {
					$kq  = self::ngay_tu_nam_hong( (int) $mm[1], $ky );
					$moi = $kq[0]; $ung = $kq[1];
					if ( $moi === '' && $ung > 1 ) { $moi = self::ngay_dau_ky( $ky ); $uoc = 1; }
				}
			} elseif ( $cach === 'ky' ) {
				$moi = self::ngay_dau_ky( $ky );
			} elseif ( preg_match( '#^(\d{4})-(\d{2})-(\d{2})#', (string) $r['ngay'], $m )
				&& checkdate( (int) $m[2], (int) $m[3], $nam ) ) {
				$moi = sprintf( '%04d-%02d-%02d', $nam, (int) $m[2], (int) $m[3] );
			}
			if ( $moi === '' ) { $bo++; } elseif ( $uoc ) { $uoc_n++; }
			$ds[] = array(
				'id'      => (string) $r['id'],
				'maDon'   => $m_don,
				'ky'      => $ky,
				'noiDung' => (string) $r['noi_dung'],
				'cu'      => $dmy,
				'tho'     => (string) $r['ngay'],
				'moi'     => $moi !== '' ? VHCP_Util::fmt( $moi ) : '',
				'ungVien' => $ung,
				'uocLuong' => $uoc,
			);
			if ( $chot && $moi !== '' ) {
				$wpdb->update( $t, array( 'ngay' => $moi ), array( 'id' => (string) $r['id'] ) );
				$sua++;
			}
		}
		return VHCP_Util::ok( array(
			'items' => array_slice( $ds, 0, 400 ), 'tong' => count( $ds ),
			'daSua' => $sua, 'boQua' => $bo, 'uocLuong' => $uoc_n, 'cach' => $cach, 'nam' => $nam, 'chot' => $chot ? 1 : 0,
		) );
	}

	/** Giữ tên cũ cho nơi nào còn gọi: sửa theo NĂM. */
	public static function sua_nam_vo_ly( $nam = 0, $ma_don = '' ) {
		return self::sua_ngay_hong( 'nam', $nam, $nam > 0, $ma_don );
	}

	/**
	 * SỬA NGÀY CỦA CHÍNH ĐƠN (ngày tạo / duyệt / cấp tiền / quyết toán) — CHỈ ADMIN.
	 *
	 * Mấy cột này là DATETIME, nên số sê-ri bảng tính bị MySQL loại thẳng lúc nạp: giá trị
	 * MẤT HẲN, không khôi phục được như cột kỳ. Chỉ còn cách để người biết việc điền lại —
	 * mà ngày quyết toán còn là ngày dự phòng khi xuất MISA, để trống là dòng chi không có
	 * ngày nào cả.
	 */
	public static function set_don_ngay( $ma_don, $truong, $ngay ) {
		if ( VHCP_Auth::vai_tro() !== 'Admin' ) { return VHCP_Util::err( 'Chỉ Admin sửa được ngày của đơn' ); }
		$cot = array(
			'ngayTao'   => 'ngay_tao',
			'ngayDuyet' => 'ngay_duyet',
			'ngayCap'   => 'ngay_cap',
			'ngayQT'    => 'ngay_qt',
		);
		$k = (string) $truong;
		if ( ! isset( $cot[ $k ] ) ) { return VHCP_Util::err( 'Không rõ cột ngày: ' . $k ); }
		$d = self::don_row( $ma_don );
		if ( ! $d ) { return VHCP_Util::err( 'Không tìm thấy đơn' ); }

		global $wpdb;
		$s = trim( (string) $ngay );
		if ( $s === '' ) {                      // xoá cho về trống là hợp lệ
			$wpdb->update( VHCP_DB::t( 'don' ), array( $cot[ $k ] => null ), array( 'ma_don' => (string) $ma_don ) );
			return VHCP_Util::ok( array( 'ngay' => '' ) );
		}
		$iso = VHCP_Util::parse_date( $s );
		if ( ! $iso ) { return VHCP_Util::err( 'Ngày không đọc được: ' . $s ); }
		if ( VHCP_Util::ngay_vo_ly( VHCP_Util::fmt( $iso ) ) ) { return VHCP_Util::err( 'Ngày vô lý: ' . VHCP_Util::fmt( $iso ) ); }
		$wpdb->update( VHCP_DB::t( 'don' ), array( $cot[ $k ] => $iso . ' 00:00:00' ), array( 'ma_don' => (string) $ma_don ) );
		return VHCP_Util::ok( array( 'ngay' => VHCP_Util::fmt( $iso ) ) );
	}

	public static function set_line_anh( $id, $url ) {
		$_loi = self::loi_khong_phai_dong_minh( $id );
		if ( $_loi !== '' ) { return VHCP_Util::err( $_loi ); }

		global $wpdb;
		$cur = self::line_row( $id );
		if ( ! $cur ) { return VHCP_Util::err( 'Không tìm thấy dòng' ); }
		$st = self::state( (string) $cur['ma_don'] );
		$chua_chot = ! self::da_chot( $st );

		/* 🔴 ĐƠN ĐÃ CHỐT VẪN PHẢI BỔ SUNG ĐƯỢC HÓA ĐƠN — nhưng CHỈ kế toán.
		   Anh Thắng: *"đối với đơn đã xuất MISA hoặc đã quyết toán, kế toán được phép bổ sung
		   thêm hóa đơn nếu thiếu hoặc sai"*.

		   Hóa đơn giấy về sau ngày chốt sổ là chuyện thường, và hóa đơn SAI thì phải thay được
		   — không thì bộ chứng từ vĩnh viễn thiếu, mà số tiền thì đã đúng rồi. Khóa cả ảnh lẫn
		   số là khóa quá tay.

		   ⚠️ CHỈ ẢNH. Đường này không đụng một con số nào — thành tiền, thực mua, mã tài khoản
		      đều nằm ngoài. Đơn đã xuất MISA mà số đổi được là hai bên sổ lệch nhau mà không ai
		      biết.
		   ⚠️ CHỈ KẾ TOÁN (và Admin). Nhân viên nhập đơn không được sờ vào đơn đã chốt sổ. */
		$vt = VHCP_Auth::vai_tro();
		$la_ke_toan = in_array( $vt, array( 'Kế toán cá nhân', 'Kế toán NCC', 'Admin' ), true );
		$da_chot    = in_array( $st, array( 'Đã quyết toán', 'Đã xuất MISA' ), true );

		if ( ! $chua_chot && ! ( $la_ke_toan && $da_chot ) ) {
			return VHCP_Util::err( $da_chot
				? 'Đơn đã chốt sổ — chỉ KẾ TOÁN mới bổ sung/sửa được hóa đơn của dòng.'
				: 'Chỉ đính hóa đơn khi đơn đang nhập / đã cấp tạm ứng / chờ quyết toán' );
		}
		$wpdb->update( VHCP_DB::t( 'chiphi' ), array( 'anh' => (string) $url ), array( 'id' => (string) $id ) );

		/* Đụng vào đơn đã chốt sổ thì PHẢI để lại vết. Số không đổi, nhưng bộ chứng từ đã khác
		   với lúc xuất MISA — người đối chiếu sau này cần biết ai đổi, lúc nào. */
		if ( $da_chot ) {
			VHCP_Log::log_action( array(
				'actor'  => $vt,
				'action' => ( trim( (string) $url ) === '' ? 'Bỏ hóa đơn dòng (đơn đã chốt)' : 'Bổ sung hóa đơn dòng (đơn đã chốt)' ),
				'target' => (string) $cur['ma_don'],
				'detail' => 'dòng ' . (string) $id . ' · trạng thái đơn: ' . $st,
			) );
		}
		return VHCP_Util::ok();
	}

	public static function delete_line( $id ) {
		$_loi = self::loi_khong_phai_dong_minh( $id );
		if ( $_loi !== '' ) { return VHCP_Util::err( $_loi ); }

		global $wpdb;
		$cur = self::line_row( $id );
		if ( ! $cur ) { return VHCP_Util::err( 'Không tìm thấy dòng' ); }
		$ps   = VHCP_Util::is_phat_sinh( $cur['phat_sinh'] );
		$info = self::info( (string) $cur['ma_don'] );
		$st   = $info['st'];
		$xin_edit = ( $st === 'Nháp' ) || ( $st === 'Đã cấp tạm ứng' && $info['returned'] );

		/* ADMIN XÓA ĐƯỢC DÒNG Ở MỌI TRẠNG THÁI.
		   Dòng nhập lỗi — sai cơ sở, nhân đôi do đơn cũ đè vào, gõ nhầm nội dung — có thể lọt
		   qua tới lúc đơn đã chốt. Khóa cứng theo trạng thái thì cách duy nhất còn lại là sửa
		   thẳng trong cơ sở dữ liệu, việc đó không để lại vết và không ai kiểm được.
		   Mở cho Admin, nhưng BẮT BUỘC ghi nhật ký kèm số tiền — xóa dòng là đổi số, khác hẳn
		   chuyện đính thêm hóa đơn. */
		$vt       = VHCP_Auth::vai_tro();
		$la_admin = ( $vt === 'Admin' );

		if ( ! $la_admin ) {
			if ( ! $ps && ! $xin_edit ) { return VHCP_Util::err( 'Hạng mục đã xin không được xóa (để Thực chi = 0 nếu không mua; đơn bị trả lại mới mở khóa)' ); }
			if ( $ps ) {
				$_c = self::vi_sao_khong_sua( (string) $cur['ma_don'] );
				if ( $_c !== '' ) { return VHCP_Util::err( $_c ); }
			}
		}

		$binh_thuong = $ps ? ! self::da_chot( $st ) : $xin_edit;

		$wpdb->delete( VHCP_DB::t( 'chiphi' ), array( 'id' => (string) $id ) );
		/* Xoá dòng thì ghi vết cho MỌI VAI, không riêng Admin. Nhật ký riêng của Admin ở dưới
		   là để đánh dấu chuyện phá khoá; còn đây là câu trả lời cho "ai xoá mất dòng này". */
		self::ghi_vet( (string) $cur['ma_don'], ( $ps ? 'Xoá dòng phát sinh' : 'Xoá hạng mục xin' ),
			self::ta_dong( $cur ) . ' · lúc đơn ở "' . $st . '"' );

		/* Ghi vết khi Admin xóa ở trạng thái mà người khác không xóa được. Ghi cả nội dung và
		   số tiền: sau khi xóa thì dòng không còn để tra lại, nhật ký là bản sao duy nhất. */
		if ( $la_admin && ! $binh_thuong ) {
			VHCP_Log::log_action( array(
				'actor'  => $vt,
				'action' => 'Admin xóa dòng (trạng thái đã khóa)',
				'target' => (string) $cur['ma_don'],
				'detail' => 'dòng ' . (string) $id . ' · trạng thái đơn: ' . $st
					. ' · ' . ( $ps ? 'phát sinh' : 'hạng mục xin' )
					. ' · ' . (string) $cur['noi_dung']
					. ' · thành tiền ' . VHCP_Util::out_num( $cur['thanh_tien'] )
					. ' · thực mua ' . VHCP_Util::out_num( $cur['thuc_mua'] ),
			) );
		}
		return VHCP_Util::ok();
	}

	/** duplicateLine(): tách 1 dòng sang cơ sở khác (đơn 1 phiếu nhiều cơ sở). */
	public static function duplicate_line( $id, $coso, $actor = '' ) {
		$_loi = self::loi_khong_phai_dong_minh( $id );
		if ( $_loi !== '' ) { return VHCP_Util::err( $_loi ); }

		global $wpdb;
		$v = self::line_row( $id );
		if ( ! $v ) { return VHCP_Util::err( 'Không tìm thấy dòng' ); }
		$ma_don = (string) $v['ma_don'];
		$st     = self::state( $ma_don );
		$_c = self::vi_sao_khong_sua( $ma_don );
		if ( $_c !== '' ) { return VHCP_Util::err( $_c ); }
		$rec = array(
			'coso'       => ( $coso ? $coso : $v['coso'] ),
			'ngay'       => $v['ngay'],
			'phanLoaiTT' => $v['phan_loai_tt'],
			'doiTuong'   => $v['doi_tuong'],
			'nhom'       => $v['nhom'],
			'noiDung'    => $v['noi_dung'],
			'dvt'        => $v['dvt'],
			'soLuong'    => VHCP_Util::out_num( $v['so_luong'] ),
			'donGia'     => VHCP_Util::out_num( $v['don_gia'] ),
			'thanhTien'  => $v['thanh_tien'],
			'ghiChu'     => $v['ghi_chu'],
			'anh'        => $v['anh'],
			'thueSuat'   => VHCP_Util::out_num( $v['thue_suat'] ),
			'thucMua'    => VHCP_Util::out_num( $v['thuc_mua'] ),
			'cnXuLy'     => (int) $v['cn_xu_ly'],
			'phatSinh'   => (int) $v['phat_sinh'],
		);
		$nid = VHCP_Util::uid( 'L' );
		$wpdb->insert( VHCP_DB::t( 'chiphi' ), self::line_data( $nid, $ma_don, $rec ) );
		if ( $st === 'Chờ quyết toán' ) { self::mark_kt_sua( $ma_don, $actor ); }
		return VHCP_Util::ok( array( 'id' => $nid ) );
	}

	// ---------------------------------------------------------------- NV gửi đơn qua các bước

	public static function gui_duyet_tam_ung( $ma_don ) {
		$_loi = self::loi_khong_phai_don_minh( $ma_don );
		if ( $_loi !== '' ) { return VHCP_Util::err( $_loi ); }

		global $wpdb;
		$d = self::don_row( $ma_don );
		if ( ! $d ) { return VHCP_Util::err( 'Không tìm thấy đơn' ); }
		if ( (string) $d['trang_thai'] !== 'Nháp' ) { return VHCP_Util::err( 'Chỉ gửi xin tạm ứng khi đơn ở "Nháp"' ); }
		$t = VHCP_DB::t( 'chiphi' );
		$n = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE ma_don=%s", (string) $ma_don ) );
		// Ở "Nháp" mọi dòng là hạng mục XIN: gộp cả dòng phát sinh (nếu có do đơn bị trả về).
		$wpdb->query( $wpdb->prepare( "UPDATE $t SET phat_sinh=0 WHERE ma_don=%s AND phat_sinh=1", (string) $ma_don ) );
		$dp = VHCP_Util::num( $d['du_phong'] );
		if ( ! $n && ! ( $dp > 0 ) ) { return VHCP_Util::err( 'Chưa nhập hạng mục nào và cũng chưa nhập tạm ứng dự phòng' ); }
		self::clear_tra_marker( $ma_don );
		// Chốt bù trừ theo đúng thời điểm gửi xin, trước khi đơn rời trạng thái "Nháp"
		self::chot_bu_tru( $ma_don );
		self::upd_don( $ma_don, array( 'trang_thai' => 'Chờ duyệt tạm ứng' ) );
		return VHCP_Util::ok();
	}

	/**
	 * NV CHỐT CHI PHÍ -> GỬI THẲNG CHO KẾ TOÁN.
	 *
	 * Không còn chặng "Chờ quản lý gom" ở giữa: cấp tạm ứng xong thì NV bổ sung hóa đơn,
	 * chốt chi phí và gửi luôn. Khâu gom chỉ là chỗ đơn nằm chờ, không thêm được gì.
	 */
	public static function gui_quyet_toan( $ma_don ) {
		$_loi = self::loi_khong_phai_don_minh( $ma_don );
		if ( $_loi !== '' ) { return VHCP_Util::err( $_loi ); }

		$d = self::don_row( $ma_don );
		if ( ! $d ) { return VHCP_Util::err( 'Không tìm thấy đơn' ); }
		if ( (string) $d['trang_thai'] !== 'Đã cấp tạm ứng' ) { return VHCP_Util::err( 'Chỉ gửi khi đơn "Đã cấp tạm ứng"' ); }
		self::clear_tra_marker( $ma_don );
		self::upd_don( $ma_don, array( 'trang_thai' => 'Chờ quyết toán' ) );
		return VHCP_Util::ok();
	}

	public static function save_quyet_toan( $ma_don, $obj ) {
		$_loi = self::loi_khong_phai_don_minh( $ma_don );
		if ( $_loi !== '' ) { return VHCP_Util::err( $_loi ); }

		$d = self::don_row( $ma_don );
		if ( ! $d ) { return VHCP_Util::err( 'Không tìm thấy đơn' ); }
		if ( (string) $d['trang_thai'] === 'Đã quyết toán' ) { return VHCP_Util::err( 'Đơn đã quyết toán — không sửa' ); }
		$obj = (array) $obj;
		self::upd_don( $ma_don, array(
			'so_tien_thuc_mua' => VHCP_Util::blank_or_num( isset( $obj['soThucMua'] ) ? $obj['soThucMua'] : '' ),
			'hinh_thuc_tt'     => isset( $obj['httt'] ) ? (string) $obj['httt'] : '',
			'hoa_don_qt'       => isset( $obj['anhHoaDon'] ) ? (string) $obj['anhHoaDon'] : '',
			'hoa_don_qt2'      => isset( $obj['anhHoaDon2'] ) ? (string) $obj['anhHoaDon2'] : '',
		) );
		return VHCP_Util::ok();
	}

	/**
	 * ĐÍNH HÓA ĐƠN TỔNG — làm được cả SAU khi đã quyết toán.
	 *
	 * Hóa đơn giấy về sau ngày chi tiền là chuyện thường: kế toán đã chi bù rồi, hôm sau
	 * mới có hóa đơn đỏ. save_quyet_toan() khóa hẳn khi "Đã quyết toán" (đúng, vì nó ghi
	 * cả SỐ TIỀN), nên tách riêng đường chỉ ghi ẢNH: không đụng số nào, mọi trạng thái
	 * đều đính được, và ghi nhật ký để còn truy ai đính lúc nào.
	 */
	public static function set_hoa_don_qt( $ma_don, $url, $nguoi = '', $o = 1 ) {
		$_loi = self::loi_khong_phai_don_minh( $ma_don );
		if ( $_loi !== '' ) { return VHCP_Util::err( $_loi ); }

		$d = self::don_row( $ma_don );
		if ( ! $d ) { return VHCP_Util::err( 'Không tìm thấy đơn' ); }
		/* HAI Ô HÓA ĐƠN, không phải một danh sách dài. Anh Thắng: *"với 1 đơn cho phép upload
		   2 hóa đơn"*. Hai ô CÓ TÊN — "Hóa đơn 1" và "Hóa đơn 2 (bổ sung)" — nói đúng nghiệp
		   vụ hơn một danh sách vô danh: cái thứ hai sinh ra vì cái thứ nhất THIẾU hoặc SAI, và
		   người xem sau này cần biết cái nào là bản bổ sung. */
		$o   = ( (int) $o === 2 ) ? 2 : 1;
		$cot = ( 2 === $o ) ? 'hoa_don_qt2' : 'hoa_don_qt';
		$u   = trim( (string) $url );
		self::upd_don( $ma_don, array( $cot => $u ) );
		VHCP_Log::log_action( array(
			'actor'  => (string) $nguoi,
			'action' => ( $u === '' ? 'Bỏ hóa đơn tổng ' . $o : 'Đính hóa đơn tổng ' . $o ),
			'target' => (string) $ma_don,
			'detail' => 'trạng thái đơn: ' . (string) $d['trang_thai'],
		) );
		return VHCP_Util::ok( array( 'o' => $o, 'anhHoaDon' => $u ) );
	}

	// ---------------------------------------------------------------- kế toán / quản lý

	public static function duyet_tam_ung( $ma_don, $nguoi, $so_tam_ung = '' ) {
		$d = self::don_row( $ma_don );
		if ( ! $d ) { return VHCP_Util::err( 'Không tìm thấy đơn' ); }
		if ( (string) $d['trang_thai'] !== 'Chờ duyệt tạm ứng' ) { return VHCP_Util::err( 'Đơn không ở "Chờ duyệt tạm ứng"' ); }
		self::upd_don( $ma_don, array(
			'trang_thai'    => 'Chờ cấp tạm ứng',
			'nguoi_duyet'   => (string) $nguoi,
			'ngay_duyet'    => VHCP_Util::now_sql(),
			'tam_ung_duyet' => VHCP_Util::blank_or_num( $so_tam_ung ),
		) );
		return VHCP_Util::ok();
	}

	public static function cap_tam_ung( $ma_don, $nguoi, $ht_cap = 'Tiền mặt', $anh_cap = '' ) {
		$d = self::don_row( $ma_don );
		if ( ! $d ) { return VHCP_Util::err( 'Không tìm thấy đơn' ); }
		if ( (string) $d['trang_thai'] !== 'Chờ cấp tạm ứng' ) { return VHCP_Util::err( 'Đơn chưa được duyệt tạm ứng' ); }
		$ht_cap = $ht_cap ? $ht_cap : 'Tiền mặt';
		if ( $ht_cap === 'Chuyển khoản' && ! $anh_cap ) { return VHCP_Util::err( 'Chuyển khoản phải đính ảnh chứng từ' ); }
		self::upd_don( $ma_don, array(
			'trang_thai' => 'Đã cấp tạm ứng',
			'nguoi_cap'  => (string) $nguoi,
			'ngay_cap'   => VHCP_Util::now_sql(),
			'ht_cap'     => (string) $ht_cap,
			'anh_cap'    => (string) $anh_cap,
		) );
		return VHCP_Util::ok();
	}

	/** _donLoai(): đơn có dòng cá nhân / dòng NCC hay không. */
	public static function don_loai( $ma_don ) {
		global $wpdb;
		$t   = VHCP_DB::t( 'chiphi' );
		$cn  = false; $ncc = false;
		foreach ( VHCP_DB::rows( $wpdb->prepare( "SELECT phan_loai_tt, cn_xu_ly FROM $t WHERE ma_don=%s", (string) $ma_don ) ) as $r ) {
			if ( VHCP_Util::is_ncc( $r['phan_loai_tt'], $r['cn_xu_ly'] ) ) { $ncc = true; } else { $cn = true; }
		}
		return array( 'cn' => $cn, 'ncc' => $ncc );
	}

	public static function xac_nhan_quyet_toan_cn( $ma_don, $nguoi, $xu_ly, $chenh_lech ) {
		$d = self::don_row( $ma_don );
		if ( ! $d ) { return VHCP_Util::err( 'Không tìm thấy đơn' ); }
		if ( (string) $d['trang_thai'] !== 'Chờ quyết toán' ) { return VHCP_Util::err( 'Đơn không ở "Chờ quyết toán"' ); }
		self::upd_don( $ma_don, array(
			'nguoi_qt'      => (string) $nguoi,
			'ngay_qt'       => VHCP_Util::now_sql(),
			'chenh_lech_qt' => VHCP_Util::num( $chenh_lech ),
			'xu_ly'         => (string) $xu_ly,
			'trang_thai'    => 'Đã quyết toán',
		) );
		return VHCP_Util::ok();
	}

	public static function xac_nhan_quyet_toan_ncc( $ma_don, $nguoi ) {
		$d = self::don_row( $ma_don );
		if ( ! $d ) { return VHCP_Util::err( 'Không tìm thấy đơn' ); }
		$st = (string) $d['trang_thai'];
		if ( $st === 'Nháp' || $st === 'Đã xuất MISA' ) { return VHCP_Util::err( 'Đơn chưa gửi hoặc đã xuất' ); }
		$data = array( 'nguoi_qt_ncc' => (string) $nguoi, 'ngay_qt_ncc' => VHCP_Util::now_sql() );
		$loai = self::don_loai( $ma_don );
		if ( ! $loai['cn'] ) { $data['trang_thai'] = 'Đã quyết toán'; }
		self::upd_don( $ma_don, $data );
		return VHCP_Util::ok();
	}

	public static function tra_lai_don( $ma_don, $ly_do = '' ) {
		$d = self::don_row( $ma_don );
		if ( ! $d ) { return VHCP_Util::err( 'Không tìm thấy đơn' ); }
		$st     = (string) $d['trang_thai'];
		$target = ( $st === 'Chờ quyết toán' ) ? 'Đã cấp tạm ứng' : 'Nháp';
		$data   = array( 'trang_thai' => $target );
		if ( $ly_do ) {
			$old = (string) $d['ghi_chu'];
			$data['ghi_chu'] = '[Trả lại] ' . $ly_do . ( $old !== '' ? ' | ' . $old : '' );
		}
		self::upd_don( $ma_don, $data );
		return VHCP_Util::ok( array( 'target' => $target ) );
	}

	public static function delete_don( $ma_don ) {
		$_loi = self::loi_khong_phai_don_minh( $ma_don );
		if ( $_loi !== '' ) { return VHCP_Util::err( $_loi ); }

		global $wpdb;
		$d = self::don_row( $ma_don );
		if ( ! $d ) { return VHCP_Util::err( 'Không tìm thấy đơn' ); }
		$st = (string) $d['trang_thai'];
		if ( ! in_array( $st, array( 'Nháp', 'Chờ duyệt tạm ứng', 'Chờ cấp tạm ứng' ), true ) ) {
			return VHCP_Util::err( 'Chỉ xóa được đơn CHƯA cấp tạm ứng. Đơn đã cấp tiền: dùng "🚫 Không dùng" hoặc Trả lại.' );
		}
		return self::purge_don( $ma_don );
	}

	/** deleteDonAdmin(): xóa vĩnh viễn, không xét trạng thái. */
	public static function delete_don_admin( $ma_don ) {
		$d = self::don_row( $ma_don );
		if ( ! $d ) { return VHCP_Util::err( 'Không tìm thấy đơn' ); }

		/* GHI VẾT TRƯỚC KHI XÓA. Xóa một dòng lẻ bằng quyền Admin thì có nhật ký, mà xóa NGUYÊN
		   ĐƠN — kể cả đơn đã xuất MISA — lại không, là ngược. Sau khi purge thì đơn, dòng chi
		   phí, tạm ứng đều biến mất; nhật ký là bản sao duy nhất còn lại để đối chiếu về sau,
		   nên phải chốt số ngay lúc này. */
		global $wpdb;
		$tcp  = VHCP_DB::t( 'chiphi' );
		$sum  = $wpdb->get_row( $wpdb->prepare(
			"SELECT COUNT(*) AS so, COALESCE(SUM(thanh_tien),0) AS tt, COALESCE(SUM(thuc_mua),0) AS tm FROM $tcp WHERE ma_don=%s",
			(string) $ma_don
		), ARRAY_A );
		$so_dong = isset( $sum['so'] ) ? (int) $sum['so'] : 0;

		VHCP_Log::log_action( array(
			'actor'  => VHCP_Auth::vai_tro(),
			'action' => 'Admin XÓA VĨNH VIỄN cả đơn',
			'target' => (string) $ma_don,
			'detail' => 'trạng thái: ' . (string) $d['trang_thai']
				. ' · kỳ: ' . (string) $d['ky']
				. ' · người lập: ' . (string) $d['nguoi_lap']
				. ' · ' . $so_dong . ' dòng'
				. ' · tổng thành tiền ' . VHCP_Util::out_num( isset( $sum['tt'] ) ? $sum['tt'] : 0 )
				. ' · tổng thực mua ' . VHCP_Util::out_num( isset( $sum['tm'] ) ? $sum['tm'] : 0 )
				. ' · tạm ứng duyệt ' . VHCP_Util::out_num( $d['tam_ung_duyet'] ),
		) );

		return self::purge_don( $ma_don );
	}

	private static function purge_don( $ma_don ) {
		global $wpdb;
		$wpdb->delete( VHCP_DB::t( 'chiphi' ), array( 'ma_don' => (string) $ma_don ) );
		$wpdb->delete( VHCP_DB::t( 'tamung' ), array( 'ma_don' => (string) $ma_don ) );
		$wpdb->delete( VHCP_DB::t( 'don' ), array( 'ma_don' => (string) $ma_don ) );
		return VHCP_Util::ok();
	}

	public static function set_tat_toan_tuan( $ma_don, $on, $actor = '' ) {
		$d = self::don_row( $ma_don );
		if ( ! $d ) { return VHCP_Util::err( 'Không tìm thấy đơn' ); }
		self::upd_don( $ma_don, array(
			'tat_toan'      => $on ? ( $actor ? $actor : '✓' ) : '',
			'ngay_tat_toan' => $on ? VHCP_Util::now_sql() : null,
		) );
		return VHCP_Util::ok( array( 'tatToan' => (bool) $on ) );
	}

	// ---------------------------------------------------------------- xử lý theo lô

	public static function duyet_tam_ung_nhieu( $ma_dons, $nguoi ) {
		$ok = 0; $errs = array();
		foreach ( (array) $ma_dons as $m ) {
			$r = self::duyet_tam_ung( $m, $nguoi, '' );
			if ( ! empty( $r['success'] ) ) { $ok++; } else { $errs[] = $m . ': ' . ( isset( $r['error'] ) ? $r['error'] : '?' ); }
		}
		return array( 'success' => count( $errs ) === 0, 'approved' => $ok, 'errors' => $errs );
	}

	public static function cap_tam_ung_nhieu( $ma_dons, $nguoi, $ht_cap = 'Tiền mặt', $anh_cap = '' ) {
		$ht_cap = $ht_cap ? $ht_cap : 'Tiền mặt';
		if ( $ht_cap === 'Chuyển khoản' && ! $anh_cap ) { return VHCP_Util::err( 'Chuyển khoản phải đính ảnh chứng từ' ); }
		$ok = 0; $errs = array();
		foreach ( (array) $ma_dons as $m ) {
			$r = self::cap_tam_ung( $m, $nguoi, $ht_cap, $anh_cap );
			if ( ! empty( $r['success'] ) ) { $ok++; } else { $errs[] = $m . ': ' . ( isset( $r['error'] ) ? $r['error'] : '?' ); }
		}
		return array( 'success' => count( $errs ) === 0, 'capped' => $ok, 'errors' => $errs );
	}

	public static function tra_lai_don_nhieu( $ma_dons, $ly_do = '' ) {
		$ok = 0; $errs = array();
		foreach ( (array) $ma_dons as $m ) {
			$r = self::tra_lai_don( $m, $ly_do );
			if ( ! empty( $r['success'] ) ) { $ok++; } else { $errs[] = (string) $m; }
		}
		return array( 'success' => count( $errs ) === 0, 'returned' => $ok, 'errors' => $errs );
	}

	public static function khong_dung_tam_ung( $ma_don ) {
		$_loi = self::loi_khong_phai_don_minh( $ma_don );
		if ( $_loi !== '' ) { return VHCP_Util::err( $_loi ); }

		$d = self::don_row( $ma_don );
		if ( ! $d ) { return VHCP_Util::err( 'Không tìm thấy đơn' ); }
		if ( (string) $d['trang_thai'] !== 'Đã cấp tạm ứng' ) { return VHCP_Util::err( 'Chỉ đánh dấu khi đơn "Đã cấp tạm ứng"' ); }
		$old  = (string) $d['ghi_chu'];
		$data = array( 'trang_thai' => 'Chờ quyết toán' );
		if ( strpos( $old, '[Không dùng]' ) === false ) { $data['ghi_chu'] = '[Không dùng] ' . $old; }
		self::upd_don( $ma_don, $data );
		return VHCP_Util::ok();
	}

	public static function xac_nhan_qt_cn_nhieu( $ma_dons, $nguoi ) {
		$ok = 0; $errs = array();
		// Chênh lệch của mọi đơn tính từ 3 lệnh DB dùng chung (listDons), thay vì
		// gọi getDon cho từng đơn — duyệt 50 đơn trước đây là 50 lượt đọc cả bảng.
		$cl_by = array();
		foreach ( self::list_dons() as $x ) { $cl_by[ $x['maDon'] ] = $x['chenhLech']; }
		foreach ( (array) $ma_dons as $m ) {
			if ( ! array_key_exists( (string) $m, $cl_by ) ) { $errs[] = $m . ': Không tìm thấy đơn'; continue; }
			$cl    = $cl_by[ (string) $m ];
			$xu_ly = $cl > 0 ? 'NV trả lại' : ( $cl < 0 ? 'Kế toán bù' : 'Khớp' );
			$r     = self::xac_nhan_quyet_toan_cn( $m, $nguoi, $xu_ly, $cl );
			if ( ! empty( $r['success'] ) ) { $ok++; } else { $errs[] = $m . ': ' . ( isset( $r['error'] ) ? $r['error'] : '?' ); }
		}
		return array( 'success' => count( $errs ) === 0, 'done' => $ok, 'errors' => $errs );
	}

	// ---------------------------------------------------------------- số dư đầu kỳ

	public static function get_so_du_dau_ky() {
		return VHCP_Util::num( VHCP_Meta::get( 'soDuDauKy', 0 ) );
	}

	public static function set_so_du_dau_ky( $v ) {
		VHCP_Meta::set( 'soDuDauKy', (string) VHCP_Util::num( $v ) );
		return VHCP_Util::ok( array( 'value' => VHCP_Util::num( $v ) ) );
	}

	/** Cơ sở + người lập của đơn (dùng để phân thư mục ảnh). */
	public static function don_folder_meta( $ma_don ) {
		global $wpdb;
		$nguoi_lap = ''; $coso = '';
		if ( $ma_don ) {
			$d = self::don_row( $ma_don );
			if ( $d ) { $nguoi_lap = trim( (string) $d['nguoi_lap'] ); }
			$t    = VHCP_DB::t( 'chiphi' );
			$cnt  = array();
			foreach ( VHCP_DB::rows( $wpdb->prepare( "SELECT coso FROM $t WHERE ma_don=%s ORDER BY stt ASC", (string) $ma_don ) ) as $r ) {
				$c = trim( (string) $r['coso'] );
				if ( $c === '' ) { continue; }
				$cnt[ $c ] = ( isset( $cnt[ $c ] ) ? $cnt[ $c ] : 0 ) + 1;
			}
			foreach ( $cnt as $c => $n ) {
				if ( $coso === '' || $n > $cnt[ $coso ] ) { $coso = $c; }
			}
		}
		return array( 'coso' => $coso, 'nguoiLap' => $nguoi_lap );
	}
}
