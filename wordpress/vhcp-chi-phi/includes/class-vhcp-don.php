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

	/**
	 * ĐẨY MỘT TIN SANG CHUÔNG CỦA TRANG NỘI BỘ.
	 *
	 * Anh Thắng 26/08/2026: *"Ví dụ như có chấm công, có chi phí nó sẽ hiện lên nội bộ này."*
	 *
	 * 🔴 NGƯỜI LẬP ĐƠN PHẢI BIẾT ĐƠN CỦA MÌNH ĐI TỚI ĐÂU. Trước bản này, đơn được duyệt / bị
	 *    trả lại / đã cấp tiền đều im lặng: người gửi phải tự mở app ra dò từng ngày, và đơn bị
	 *    trả lại thì nằm đó cả tuần không ai đụng vào vì không ai biết nó đã bị trả.
	 *
	 * ⚠️ Gác `class_exists` + `method_exists` CÙNG HÀM với lời gọi — luật của
	 *    `tools/test/kiem-goi-cheo.php`. Chưa cài plugin nội bộ thì im lặng trôi qua: báo tin là
	 *    việc phụ, KHÔNG được làm hỏng việc duyệt đơn.
	 *
	 * ⚠️ Gửi theo MÃ NV, mà bảng đơn chỉ giữ TÊN người lập — nên phải tra ngược qua bảng người
	 *    dùng. Không tra ra thì thôi, đừng đoán: gửi nhầm hộp thư là báo chuyện tiền nong của
	 *    người này vào chuông người khác.
	 */
	private static function bao_noi_bo( $ma_don, $chu ) {
		if ( ! class_exists( 'VHNB_Bao' ) || ! method_exists( 'VHNB_Bao', 'gui' ) ) { return; }
		$d = self::don_row( $ma_don );
		if ( ! $d ) { return; }
		$ten = mb_strtolower( trim( (string) $d['nguoi_lap'] ) );
		if ( '' === $ten ) { return; }
		$ma_nv = '';
		foreach ( VHCP_Cfg::get_users() as $u ) {
			if ( mb_strtolower( trim( (string) $u['ten'] ) ) === $ten ) {
				$ma_nv = trim( (string) ( isset( $u['maDt'] ) ? $u['maDt'] : '' ) );
				break;
			}
		}
		if ( '' === $ma_nv ) { return; }
		VHNB_Bao::gui( $ma_nv, 'chi_phi',
			'Đơn ' . (string) $d['ky'] . ' — ' . (string) $chu, '',
			'cp_don:' . (string) $ma_don, '' );
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
		/* 🔴 CƠ SỞ NẰM Ở DÒNG CHI (`c.coso`), KHÔNG NẰM Ở ĐƠN.
		   Bảng `don` chưa từng có cột `coso` — cơ sở của một đơn là cơ sở của các dòng trong
		   nó, đúng như `coso_cua_don()` vẫn làm. Bản trước viết `d.coso` ở cả chỗ lọc lẫn chỗ
		   SELECT, nên câu SQL hỏng ở MỌI lượt gọi; wpdb nuốt lỗi và trả về rỗng, thành ra ô
		   tìm luôn báo "không tìm thấy đơn nào khớp" dù sổ có mấy trăm đơn. Không có gì đỏ,
		   không có gì kêu — chỉ là một ô tìm không bao giờ tìm ra. Anh Thắng 26/08 báo
		   *"Lọc tìm kiếm chung theo loại chi phí lẻ anh chưa thấy"* chính là chỗ này. */
		if ( '' !== $coso ) { $dk[] = 'c.coso = %s'; $tv[] = $coso; }
		if ( '' !== $nhom ) { $dk[] = 'c.nhom = %s'; $tv[] = $nhom; }
		if ( '' !== $q ) {
			$like = '%' . $wpdb->esc_like( $q ) . '%';
			$dk[] = '( d.ma_don LIKE %s OR d.ky LIKE %s OR d.nguoi_lap LIKE %s'
				. ' OR c.nhom LIKE %s OR c.noi_dung LIKE %s OR c.doi_tuong LIKE %s )';
			array_push( $tv, $like, $like, $like, $like, $like, $like );
		}
		/* Lọc đơn vị NGAY TRONG SQL, trước `LIMIT` — xem `VHCP_DonVi::dieu_kien_sql()`. */
		$dv = VHCP_DonVi::dieu_kien_sql( 'd.don_vi' );
		if ( $dv ) { $dk[] = $dv['sql']; $tv = array_merge( $tv, $dv['tv'] ); }

		$sql = "SELECT d.ma_don, d.ky, d.nguoi_lap, d.trang_thai, d.don_vi,
				GROUP_CONCAT(DISTINCT c.coso) AS cac_coso,
				GROUP_CONCAT(DISTINCT c.nhom) AS cac_nhom, COUNT(c.id) AS so_dong,
				SUM(c.thanh_tien) AS tong_tien
			FROM $td d LEFT JOIN $tc c ON c.ma_don = d.ma_don
			WHERE " . implode( ' AND ', $dk ) . "
			GROUP BY d.ma_don, d.ky, d.nguoi_lap, d.trang_thai, d.don_vi
			ORDER BY d.stt DESC LIMIT %d";
		$tv[] = $limit;
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $tv ), ARRAY_A );

		/* 🔴 LẤY CẢ DÒNG CHI KHỚP, KHÔNG CHỈ TÊN LOẠI.
		   Anh Thắng 26/08/2026: *"chỗ này phải hiện hàng con dưa leo ra chứ, hiện tên đơn thì
		   không biết được"*. Gõ "dưa leo" ra 9 đơn, mà cột kết quả chỉ hiện "Chi phí NVL đồ ăn -
		   Mua lẻ" — đúng cái tên ấy ở cả 9 dòng, nên nhìn xong vẫn phải mở từng đơn ra xem.
		   Thứ người tìm cần thấy là DÒNG họ vừa gõ: "dưa leo · 60.000đ".

		   ⚠️ HỎI BẰNG MỘT CÂU RIÊNG, KHÔNG GOM VÀO CÂU TRÊN. Gom được bằng
		      GROUP_CONCAT + CONCAT + SEPARATOR — nhưng đó là cú pháp RIÊNG CỦA MySQL, mà bộ thử
		      chạy trên SQLite. Viết thế là đúng chỗ này vĩnh viễn không có phép thử nào với tới,
		      mà nó lại chính là chỗ vừa hỏng. Một câu thêm trên tối đa 60 mã đơn rẻ hơn nhiều so
		      với một tính năng không kiểm được. */
		$dong_khop = array();
		if ( '' !== $q && $rows ) {
			$ma_ds = array();
			foreach ( $rows as $r0 ) { $ma_ds[] = (string) $r0['ma_don']; }
			$cho   = implode( ',', array_fill( 0, count( $ma_ds ), '%s' ) );
			$like2 = '%' . $wpdb->esc_like( $q ) . '%';
			$tv2   = array_merge( $ma_ds, array( $like2, $like2, $like2 ) );
			foreach ( VHCP_DB::rows( $wpdb->prepare(
				"SELECT ma_don, noi_dung, thanh_tien FROM $tc
					WHERE ma_don IN ($cho)
					  AND ( noi_dung LIKE %s OR nhom LIKE %s OR doi_tuong LIKE %s )
					ORDER BY stt ASC", $tv2 ) ) as $l ) {
				$m2 = (string) $l['ma_don'];
				if ( ! isset( $dong_khop[ $m2 ] ) ) { $dong_khop[ $m2 ] = array(); }
				$dong_khop[ $m2 ][] = array(
					'noiDung' => trim( (string) $l['noi_dung'] ),
					'tien'    => VHCP_Util::num( $l['thanh_tien'] ),
				);
			}
		}

		$items = array();
		foreach ( (array) $rows as $r ) {
			$loai = array();
			foreach ( explode( ',', (string) $r['cac_nhom'] ) as $x ) {
				$x = trim( $x );
				if ( '' !== $x && ! in_array( $x, $loai, true ) ) { $loai[] = $x; }
			}
			$cs = array();
			foreach ( explode( ',', (string) $r['cac_coso'] ) as $x ) {
				$x = trim( $x );
				if ( '' !== $x && ! in_array( $x, $cs, true ) ) { $cs[] = $x; }
			}
			/* Dòng chi KHỚP — trả tối đa 5, kèm SỐ THẬT còn bao nhiêu. Cắt im lặng thì "3 dòng"
			   trông y hệt "chỉ có 3 dòng", và người tìm tin vào một con số thiếu. */
			$m_don = (string) $r['ma_don'];
			$dong  = isset( $dong_khop[ $m_don ] ) ? $dong_khop[ $m_don ] : array();
			$items[] = array(
				'dong'      => array_slice( $dong, 0, 5 ),
				'soDongKhop'=> count( $dong ),
				'maDon'     => (string) $r['ma_don'],
				'ky'        => (string) $r['ky'],
				'coso'      => implode( ', ', $cs ),
				'donVi'     => VHCP_DonVi::chuan( isset( $r['don_vi'] ) ? $r['don_vi'] : '' ),
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
			/* Đơn vị (K&H · POSH) — giao diện cần để gợi ý ở Cấu hình và để biết có nên tách
			   khối trong các bảng. `donViCuaToi` là NHÀ, `xemDonVi` là tầm nhìn (null = cả hệ). */
			'donVi'      => VHCP_DonVi::ds(),
			'donViCuaToi'=> VHCP_DonVi::cua_toi(),
			'xemDonVi'   => VHCP_DonVi::xem_duoc(),
			'nhieuDonVi' => VHCP_DonVi::nhieu_don_vi(),
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
		/* 🔴 KHÔNG suy tạm ứng từ tổng hạng mục nữa (anh Thắng 01/09/2026: cửa hàng không xin thì
		   tạm ứng = 0). Tạm ứng CHỈ là số nhập tay ở ô "Số tiền tạm ứng" (bảng `tamung`) + dự phòng;
		   ô để trống = 0 = không xin. (Bỏ dòng cũ đổ `$xin` vào `$tu_sum` khi thiếu ô nhập.) */

		$out = array();
		foreach ( $dons as $r ) {
			$m = (string) $r['ma_don'];
			if ( $m === '' ) { continue; }
			$du_phong = VHCP_Util::num( $r['du_phong'] );
			$bu_tru   = VHCP_Util::num( $r['bu_tru'] );
			/* NULL = chưa ai chốt số; 0 = đã chốt và chốt là không đồng nào. Xem khối 🔴 ở
			   `get_don()` — `num()` nghiền hai thứ ấy thành một. */
			$tu_d     = VHCP_Util::blank_or_num( $r['tam_ung_duyet'] );
			/* 🔴 BÙ TRỪ TUẦN TRƯỚC KHÔNG CỘNG VÀO TIỀN — anh Thắng 31/08/2026: *"phần thiếu thừa
			   tạm ứng của tuần trước nó chỉ là con số báo cáo, và không được cộng hay trừ vào
			   tiền của tuần sau"*, *"Hiện nó đang trừ tiền tạm ứng của tuần này"*.

			   Ca thật anh gửi: đơn xin 15.000.032đ, bù trừ −5.595.000đ → tạm ứng duyệt chỉ còn
			   9.405.032đ. Nhân viên vẫn phải mua đủ 15.000.032đ, nên đến bước quyết toán màn
			   hình báo "Thiếu 5.595.000đ — kế toán chi bù cho NV". Tiền không mất đi đâu, nhưng
			   nó đẻ ra một khoản chênh MỚI ở chính tuần này, rồi khoản ấy lại bị bù sang tuần
			   sau — một vòng không bao giờ đóng, và mỗi vòng người đi mua lại thiếu tiền mặt.

			   Thừa/thiếu của tuần trước là việc giữa kế toán và người cầm TK 141, quyết toán
			   riêng. Tuần này xin bao nhiêu thì cấp bấy nhiêu.

			   ⚠️ VẪN TÍNH VÀ VẪN HIỆN con số ấy — chỉ thôi cộng vào tiền. Bỏ hẳn thì mất luôn
			      thứ đang giúp kế toán biết tuần trước còn treo bao nhiêu. */
			/* Bỏ "tạm ứng dự phòng" (anh Thắng 01/09/2026): nhân viên nhập thẳng số tạm ứng ở ô,
			   không cần khoản dự phòng riêng nữa. Tạm ứng = đúng số nhập tay ở bảng `tamung`. */
			$tu_tay   = ( isset( $tu_sum[ $m ] ) ? $tu_sum[ $m ] : 0 );
			$ad_total = ( null !== $tu_d ) ? $tu_d : $tu_tay;
			$has_tu   = ( $ad_total > 0 );
			$mua_cn   = isset( $tt_cn[ $m ] ) ? $tt_cn[ $m ] : 0;
			$tc_ncc   = isset( $tt_ncc[ $m ] ) ? $tt_ncc[ $m ] : 0;
			$tc       = $mua_cn + $tc_ncc;
			/* 🔴 KHÔNG LẤY THỰC CHI LẤP VÀO CHỖ TẠM ỨNG — cùng lý do với `get_don()`. Ở đây nó
			   còn đi xa hơn: `chenhLech` = `$tam_ung - $mua_cn` nuôi cả màn Thừa/thiếu tuần và
			   phép bù trừ luân chuyển, nên một đơn không xin tạm ứng đang báo chênh 0 ở KHẮP
			   NƠI, và khoản NV ứng ra không bao giờ nổi lên. */
			$tam_ung  = $ad_total;

			$mp = isset( $coso_by[ $m ] ) ? $coso_by[ $m ] : array();
			arsort( $mp );
			$coso = implode( ', ', array_keys( $mp ) );

			$out[] = array(
				'maDon'       => $m,
				'ky'          => VHCP_Util::fmt( $r['ky'] ),
				'nguoiLap'    => (string) $r['nguoi_lap'],
				/* Gác isset: cột `don_vi` thêm ở bản 1.43.0 — bảng nới ở lượt tải trang sau. */
				'donVi'       => VHCP_DonVi::chuan( isset( $r['don_vi'] ) ? $r['don_vi'] : '' ),
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
		/* ═══════════════════════════════════════════════════════════════════════════════════
		 * NHÂN VIÊN THẤY: ĐƠN CỦA MÌNH, CỘNG ĐƠN CỦA CƠ SỞ MÌNH PHỤ TRÁCH.
		 * ═══════════════════════════════════════════════════════════════════════════════════
		 * Chặn ở đây, tức ở NGUỒN: mọi màn (danh sách đơn · duyệt tạm ứng · quyết toán ·
		 * thừa/thiếu · báo cáo) đều lấy từ đây, nên không màn nào lỡ để lộ đơn ngoài phạm vi.
		 * Lọc trên giao diện thì dữ liệu vẫn đã gửi xuống máy người ta rồi.
		 *
		 * 🔴 CƠ SỞ PHỤ TRÁCH MỚI ĐƯỢC TÍNH VÀO PHẠM VI. Anh Thắng 30/08/2026: *"Nhân viên được
		 *    cấu hình 3 cơ sở, nhưng đơn chỉ hiện 1 cơ sở"*. Trước đây ô khai cơ sở ở màn Cấu
		 *    hình chỉ được nhét vào thẻ phiên rồi thôi — không chỗ nào ở máy chủ đọc tới, nên
		 *    khai ba cơ sở hay ba mươi cũng như nhau: danh sách vẫn chỉ lọc theo NGƯỜI LẬP.
		 *
		 * ⚠️ VẪN GIỮ VẾ "ĐƠN CỦA MÌNH". Bỏ nó đi là người chưa khai cơ sở nào (hoặc lập đơn cho
		 *    một cơ sở vừa bị gỡ khỏi danh sách phụ trách) mất luôn chính đơn mình đang làm dở.
		 *
		 * ⚠️ CHƯA KHAI CƠ SỞ NÀO thì `coso_ds()` rỗng, và mọi thứ rơi về đúng hành vi cũ — chỉ
		 *    đơn của mình. KHÔNG được hiểu "rỗng" thành "tất cả": người quên khai sẽ đọc được
		 *    đơn của cả công ty mà không ai nhận ra.
		 *
		 * ⚠️ MỘT ĐƠN CÓ THỂ MANG NHIỀU CƠ SỞ (`$x['coso']` là chuỗi "A, B" gom từ các dòng chi).
		 *    Chỉ cần MỘT trong số đó nằm trong phạm vi là thấy được — đơn ghép nhiều cơ sở thì
		 *    người phụ trách một trong các cơ sở ấy vẫn phải theo dõi được phần của mình.
		 */
		if ( VHCP_Auth::la_nhan_vien() ) {
			$toi = mb_strtolower( trim( VHCP_Auth::nguoi() ) );
			$out = array_values( array_filter( $out, function ( $x ) use ( $toi ) {
				if ( mb_strtolower( trim( (string) $x['nguoiLap'] ) ) === $toi ) { return true; }
				foreach ( explode( ',', (string) $x['coso'] ) as $cs ) {
					if ( VHCP_Auth::trong_coso( $cs ) ) { return true; }
				}
				return false;
			} ) );
		}
		/* 🔴 LỌC ĐƠN VỊ Ở ĐÚNG CHỖ NÀY, cạnh chốt trên, và vì đúng một lý do: mọi màn (danh
		   sách đơn · duyệt tạm ứng · quyết toán · thừa/thiếu · báo cáo · xuất MISA) đều múc
		   từ `list_dons()`. Chặn ở đây là chặn hết một lượt; chặn ở từng màn là sớm muộn sót
		   một màn, và cái sót ấy đưa số của POSH sang màn của K&H mà không ai biết.
		   Anh Thắng 26/08: *"Bộ phận khác nên sẽ tách biệt không xem được doanh thu của nhau."* */
		$dv_xem = VHCP_DonVi::xem_duoc();
		if ( null !== $dv_xem ) {
			$out = array_values( array_filter( $out, function ( $x ) {
				return VHCP_DonVi::duoc_xem( isset( $x['donVi'] ) ? $x['donVi'] : '' );
			} ) );
		}
		return array_reverse( $out );
	}

	/**
	 * Đơn này có phải của người đang gọi? Trả câu lỗi, '' là được phép.
	 * Nhân viên chỉ mở / sửa đơn do chính mình lập; vai trò khác thì không giới hạn.
	 */
	private static function loi_khong_phai_don_minh( $ma_don ) {
		/* 🔴 CHỐT ĐƠN VỊ ĐỨNG TRƯỚC, và áp cho MỌI VAI — kể cả Kế toán và Quản lý.
		   Chốt "đơn của mình" bên dưới chỉ áp cho Nhân viên, nên nếu để chốt đơn vị đi sau nó
		   thì kế toán POSH gõ mã đơn của K&H lên thanh địa chỉ là mở được — danh sách có lọc,
		   nhưng cửa mở thẳng thì không.
		   Lọc danh sách là để MẮT không thấy; chốt ở đây là để TAY không với tới. Thiếu cái
		   thứ hai thì cái thứ nhất chỉ là lớp sơn. */
		$loi_dv = VHCP_DonVi::vi_sao_khong_dung( $ma_don );
		if ( '' !== $loi_dv ) { return $loi_dv; }
		if ( ! VHCP_Auth::la_nhan_vien() ) { return ''; }
		$d = self::don_row( $ma_don );
		if ( ! $d ) { return 'Không tìm thấy đơn'; }
		$cua = mb_strtolower( trim( (string) $d['nguoi_lap'] ) );
		$toi = mb_strtolower( trim( VHCP_Auth::nguoi() ) );
		if ( $cua === '' || $cua === $toi ) { return ''; }

		/* 🔴 CỬA MỞ ĐƠN PHẢI NỚI THEO ĐÚNG BẰNG DANH SÁCH. Thấy đơn trong danh sách mà bấm vào
		   lại bị chối "đơn của người khác" thì tính năng coi như không có — và người dùng sẽ
		   tưởng hệ thống hỏng chứ không nghĩ là hai chốt khai khác nhau.
		   Phạm vi: đơn có ÍT NHẤT MỘT dòng chi thuộc cơ sở mình phụ trách. Dùng chính
		   `cac_coso_cua_don()` — lấy ĐỦ mọi cơ sở của đơn, không phải mỗi dòng đầu. */
		foreach ( self::cac_coso_cua_don( $ma_don ) as $cs ) {
			if ( VHCP_Auth::trong_coso( $cs ) ) { return ''; }
		}
		return 'Đơn này của người khác, và không thuộc cơ sở anh/chị phụ trách.';
	}

	/** Như trên nhưng tra theo ID DÒNG (dòng nào cũng thuộc một đơn). */
	private static function loi_khong_phai_dong_minh( $id ) {
		/* ⚠️ KHÔNG thoát sớm theo vai ở đây. Chốt đơn vị nằm trong `loi_khong_phai_don_minh()`
		   và áp cho mọi vai, nên phải đi tới đó — thoát sớm là kế toán POSH sửa được TỪNG DÒNG
		   của đơn K&H bằng id dòng, dù không mở nổi cái đơn chứa nó. */
		$l = self::line_row( $id );
		if ( ! $l ) { return 'Không tìm thấy dòng'; }
		return self::loi_khong_phai_don_minh( (string) $l['ma_don'] );
	}

	// ---------------------------------------------------------------- 1 đơn

	/**
	 * CHUẨN HOÁ CHUỖI KỲ CỦA ĐƠN MỚI — TUẦN LIÊN TỤC, KHÔNG CẮT THEO THÁNG.
	 *
	 * 🔴 Anh Thắng 01/09/2026: *"giờ luật tạo cho đơn mới theo tuần liên tục, không tạo theo
	 *    tháng nữa"*. Luật cũ cắt mỗi tuần tại ngày cuối tháng, nên sinh ra những kỳ dị dạng —
	 *    ảnh anh gửi có `T8/2026 (31/8-31/8/2026)`: một "tuần" đúng MỘT ngày, vì 31/8/2026 rơi
	 *    vào thứ hai. Tuần sau nó lại là `T9/2026 (1/9-6/9/2026)`, sáu ngày. Cùng một tuần làm
	 *    việc bị xé làm đôi, mỗi nửa một đơn, quyết toán hai lần.
	 *
	 * 🔴 NHÃN THÁNG LẤY THEO NGÀY CUỐI KHOẢNG, không theo ngày đầu. Đây KHÔNG phải lựa chọn tuỳ
	 *    ý mà là luật đã có sẵn ở máy chủ: `nhan_ky()` dựng tên bằng `gmdate('n',$d2)`, và
	 *    `khoang_ky()` / `ky_num()` suy ngược năm bằng `$m1 > $m2 ? $y2-1 : $y2` — tức chúng đọc
	 *    con số trong nhãn như là tháng của ngày CUỐI.
	 *
	 *    Giao diện thì lại dựng nhãn theo ngày ĐẦU. Chừng nào tuần còn bị cắt trong một tháng
	 *    thì hai luật ấy cho ra cùng một chuỗi nên không ai thấy; bỏ cắt tháng ra là chúng lệch
	 *    ngay ở tuần bắc qua tháng — `T8/2026 (31/8-6/9/2026)` từ ô tạo đơn, `T9/2026
	 *    (31/8-6/9/2026)` từ nút "Nhảy sang tuần khác" — hai kỳ khác nhau cho CÙNG một tuần,
	 *    và lọc theo tuần thì không bao giờ gom chúng lại được nữa.
	 *
	 *    Chốt ở đây chứ không chỉ sửa giao diện: trình duyệt còn giữ bản .html cũ trong bộ nhớ
	 *    đệm, mà một chuỗi kỳ ghi sai thì nằm luôn trong sổ.
	 *
	 * ⚠️ CHỈ SỬA NHÃN, KHÔNG ĐỘNG VÀO KHOẢNG NGÀY. Người lập được chọn khoảng ngày tự do (ô
	 *    "từ ngày – đến ngày"), nên nới một khoảng 3 ngày thành 7 ngày là sửa ý người dùng.
	 *    Việc "tuần phải đủ 7 ngày" thuộc về chỗ SINH ra danh sách tuần, không thuộc chỗ này.
	 *
	 * @param string $ky Chuỗi kỳ giao diện gửi lên.
	 * @return string Chuỗi kỳ đã chuẩn nhãn; trả nguyên văn nếu không nhận ra khuôn khoảng ngày.
	 */
	public static function chuan_ky_moi( $ky ) {
		$s = trim( (string) $ky );
		if ( '' === $s ) { return $s; }
		list( $tu, $den ) = self::khoang_ky( $s );
		if ( '' === $tu || '' === $den ) { return $s; }
		$ts_den = strtotime( $den . ' 00:00:00 UTC' );
		if ( ! $ts_den ) { return $s; }
		$nhan = 'T' . (int) gmdate( 'n', $ts_den ) . '/' . gmdate( 'Y', $ts_den );
		/* 🔴 THAY MỖI CÁI NHÃN Ở ĐẦU, phần trong ngoặc chép nguyên văn. Dựng lại chuỗi từ các
		   con số vừa bóc ra thì tiện hơn, nhưng nó lặng lẽ đổi luôn cách viết: kỳ ai đó ghi
		   `(06/10-12/10/2026)` sẽ thành `(6/10-...)`, và đơn cũ cùng tuần ấy lập tức khác chuỗi
		   với đơn mới — lọc theo tuần thấy hai kỳ. Bài thử "lấy đúng tuần trước" bắt đúng lỗi
		   này ngay lượt chạy đầu. */
		/* Khuôn "T8/2026" trần (cả tháng) tự lo được: `khoang_ky()` trả ngày cuối CÙNG THÁNG
		   nên nhãn dựng ra đúng bằng nhãn đang có, thay vào cũng là chính nó. Không cần chốt
		   riêng — một chốt không bao giờ đổi kết quả là mã không có vết, phá thử chỉ ra ngay. */
		$ra = preg_replace( '#^T\s*\d{1,2}\s*/\s*\d{4}#u', $nhan, $s, 1 );
		/* Không khớp khuôn nhãn thì `preg_replace` trả lại chính chuỗi cũ — đúng cái ta muốn,
		   nên không cần nhánh riêng cho ca ấy (bản đầu có một nhánh như thế, phá thử chỉ ra nó
		   không bao giờ đổi kết quả). `null` chỉ xảy ra khi PCRE hỏng; ngã về chuỗi cũ. */
		return ( null === $ra ) ? $s : $ra;
	}

	public static function create_don( $ky, $nguoi_lap ) {
		global $wpdb;
		$m = VHCP_Util::uid( 'D' );
		$wpdb->insert( VHCP_DB::t( 'don' ), array(
			'ma_don'     => $m,
			'ky'         => self::chuan_ky_moi( $ky ),
			'nguoi_lap'  => (string) $nguoi_lap,
			/* 🔴 ĐƠN VỊ LẤY TỪ NHÀ CỦA NGƯỜI LẬP, ghi một lần rồi thôi.
			   Anh Thắng chốt 26/08: người lập thuộc đơn vị nào thì đơn thuộc đơn vị đó — kể cả
			   khi đơn ấy chi cho một cơ sở bên kia. Không có ô cho người dùng chọn: một ô chọn
			   là một chỗ chọn nhầm, mà chọn nhầm ở đây là đơn rơi sang sổ của bên kia và biến
			   mất khỏi màn của chính người vừa lập nó. */
			'don_vi'     => VHCP_DonVi::cua_nguoi( $nguoi_lap ),
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

		/* 🔴 KHÔNG suy tạm ứng từ tổng hạng mục (anh Thắng 01/09/2026: cửa hàng không xin thì tạm
		   ứng = 0). `$tam_ung` CHỈ gồm số nhập tay ở ô "Số tiền tạm ứng" (bảng `tamung`, đọc ở trên);
		   ô để trống = không có dòng = 0 = không xin. Các dòng chi phí là kế hoạch mua / thực mua,
		   quyết toán riêng — không đổ vào tạm ứng. */
		$tu_tay_sum = 0;
		foreach ( $tam_ung as $v ) { $tu_tay_sum += VHCP_Util::num( $v ); }
		/* Bù trừ tuần trước KHÔNG vào tổng tạm ứng — xem khối 🔴 ở `list_dons()`. Nó vẫn được
		   tính và vẫn trả về (`buTru`, `buTruAuto`) để màn hình bày ra như một con số báo cáo.
		   Đã bỏ "tạm ứng dự phòng" (anh Thắng 01/09/2026): nhân viên nhập thẳng số tạm ứng ở ô. */
		/* 🔴 TẠM ỨNG BẰNG 0 LÀ MỘT CON SỐ THẬT, KHÔNG PHẢI "CHƯA BIẾT".
		   Anh Thắng 01/09/2026: *"Khi 1 cửa hàng không xin tạm ứng, tạm ứng = 0. Nhân viên sau
		   đó mua đồ và quyết toán, thì hệ thống ghi nhận cả tạm ứng và thực chi = nhau luôn.
		   Đáng lẽ tạm ứng phải = 0"*.

		   Bản cũ hỏi `> 0`, nên số duyệt 0đ bị coi như chưa có rồi rơi xuống suy từ hạng mục
		   xin — mà hạng mục xin lại suy tiếp từ chính các dòng chi. Cuối đường, "tạm ứng" hoá
		   ra là bản sao của thực chi, khối Quyết toán báo "khớp 0đ", trong khi người đi mua
		   đang bỏ tiền túi toàn bộ và chẳng có gì nói cho kế toán biết phải trả lại họ.

		   Phân biệt NULL (chưa ai chốt số — đơn cũ, đơn chưa duyệt) với 0 (đã chốt, và chốt là
		   không đồng nào). `blank_or_num()` giữ được sự khác nhau ấy; `num()` thì không. */
		$tu_duyet = VHCP_Util::blank_or_num( $don['tamUngDuyet'] );
		$ad_total = ( null !== $tu_duyet ) ? $tu_duyet : $tu_tay_sum;
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
		/* 🔴 KHÔNG LẤY THỰC CHI LẤP VÀO CHỖ TẠM ỨNG. Bản cũ: `$has_tu ? $ad_total : $cn_tc` —
		   không có tạm ứng thì lấy luôn tổng đã mua làm tạm ứng, nên chênh lệch ra 0 và màn
		   Quyết toán ghi "Khớp — không thừa thiếu". Đơn không xin tạm ứng phải ra "Thiếu N —
		   kế toán bù cho NV", vì đó mới là chuyện đang xảy ra ngoài đời. */
		$cn_tu = $ad_total;

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
		/* 🔒 MỘT ĐƠN = MỘT CƠ SỞ. Đơn đã chốt cơ sở (do tạm ứng/dòng chi trước) thì không nhận
		   tạm ứng cho cơ sở KHÁC — xin tạm ứng nơi này mà chi nơi khác là sai. Gác cả ở máy chủ,
		   không tin mỗi khóa trên giao diện. */
		$cs_don = self::coso_cua_don( $ma_don );
		if ( $cs_don !== '' && strcasecmp( $cs_don, (string) $coso ) !== 0 ) {
			return VHCP_Util::err( 'Đơn này của cơ sở "' . $cs_don . '" — muốn tạm ứng cơ sở khác thì tạo đơn mới.' );
		}
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
	 * chenhLech của đơn = tạm ứng − thực chi cá nhân: DƯƠNG là DƯ, ÂM là THIẾU.
	 *
	 * 🔴 TỪ BẢN 1.53.0 CON SỐ NÀY CHỈ ĐỂ BÁO CÁO, KHÔNG CỘNG/TRỪ VÀO TIỀN TUẦN SAU.
	 *    Anh Thắng 31/08/2026: *"phần thiếu thừa tạm ứng của tuần trước nó chỉ là con số báo
	 *    cáo, và không được cộng hay trừ vào tiền của tuần sau"*. Dấu vẫn giữ nguyên (âm = tuần
	 *    trước dư, dương = tuần trước thiếu) để màn hình đọc ra chiều, nhưng KHÔNG nơi nào cộng
	 *    nó vào tạm ứng nữa — xem khối 🔴 ở `list_dons()`.
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
		/* 🔴 CÂU NÀY LÀ TOOLTIP CỦA CHÍNH CÁI NHÃN "KHÔNG trừ vào tuần này" — hai câu không được
		   chọi nhau. Anh Thắng 31/08/2026 gửi ảnh đúng cảnh ấy: nhãn ghi "chỉ để biết, KHÔNG trừ
		   vào tuần này", rê chuột vào thì tooltip cũ vẫn nói "→ tuần này trừ đi". Người đọc tin
		   câu nào cũng sai một nửa, mà đây là câu giải thích một con số tiền.
		   ⚠️ Đổi luật ở một nơi thì phải đi hết những nơi ĐANG KỂ LẠI luật ấy — chuỗi chữ không
		      có trình biên dịch nào nhắc. */
		$duoi = ' · chỉ để BÁO CÁO, không trừ/cộng vào tạm ứng tuần này';
		if ( $so > 0 )     { $ly = 'tuần trước (' . $ky_truoc . ') của ' . $ql . ' — ' . $goi . ' — còn THIẾU ' . VHCP_Util::tien( $so ) . $duoi; }
		elseif ( $so < 0 ) { $ly = 'tuần trước (' . $ky_truoc . ') của ' . $ql . ' — ' . $goi . ' — còn DƯ ' . VHCP_Util::tien( -$so ) . $duoi; }
		else               { $ly = 'tuần trước (' . $ky_truoc . ') của ' . $ql . ' — ' . $goi . ' — vừa khớp, không còn treo gì'; }
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
		/* 🔴 MỘT ĐƠN = MỘT CƠ SỞ (anh Thắng 01/09/2026): tạm ứng nhập cho cơ sở nào thì đơn CHỐT
		   cơ sở đó, để ô nhập hạng mục khóa theo — không còn cảnh xin tạm ứng cơ sở này mà lên chi
		   phí cơ sở khác. Ưu tiên cơ sở của TẠM ỨNG (thường nhập trước), rồi mới tới dòng chi. */
		$tt = VHCP_DB::t( 'tamung' );
		$v  = $wpdb->get_var( $wpdb->prepare(
			"SELECT coso FROM $tt WHERE ma_don=%s AND coso<>'' ORDER BY id ASC LIMIT 1",
			(string) $ma_don
		) );
		if ( trim( (string) $v ) !== '' ) { return trim( (string) $v ); }
		$t = VHCP_DB::t( 'chiphi' );
		$v = $wpdb->get_var( $wpdb->prepare(
			"SELECT coso FROM $t WHERE ma_don=%s AND coso<>'' ORDER BY id ASC LIMIT 1",
			(string) $ma_don
		) );
		return trim( (string) $v );
	}

	/**
	 * TẤT CẢ cơ sở của một đơn — khác `coso_cua_don()` vốn chỉ trả cơ sở của DÒNG ĐẦU TIÊN.
	 *
	 * 🔴 MỘT ĐƠN CÓ THỂ GHÉP NHIỀU CƠ SỞ. Cơ sở nằm ở DÒNG CHI, không nằm ở đơn — một đơn tuần
	 *    có thể có dòng của hai ba gian. Chốt phạm vi mà chỉ nhìn dòng đầu là người phụ trách
	 *    gian thứ hai không mở nổi đơn có phần chi của chính gian mình.
	 *
	 * @return array tên cơ sở, đã bỏ trùng và bỏ rỗng.
	 */
	public static function cac_coso_cua_don( $ma_don ) {
		global $wpdb;
		$t  = VHCP_DB::t( 'chiphi' );
		$rs = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT coso FROM $t WHERE ma_don=%s AND coso<>''",
			(string) $ma_don
		) );
		$ra = array();
		foreach ( (array) $rs as $x ) {
			$x = trim( (string) $x );
			if ( '' !== $x ) { $ra[] = $x; }
		}
		return $ra;
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

	/**
	 * NHẬP THỰC CHI CHO MỘT DÒNG.
	 *
	 * 🔴 NHÂN VIÊN PHẢI NHẬP ĐƯỢC. Anh Thắng 01/09/2026, ảnh đơn FUNZONE VŨNG TÀU: *"nhân viên
	 *    được phép nhập và sửa lại đơn chính xác trước khi quyết toán, nhưng nhập vào ô thực mua
	 *    lại báo lỗi nhân viên không được chỉnh sửa"*.
	 *
	 *    Gốc nằm ở cổng API: `setLineThucMua` từng bị xếp vào nhóm "việc của người duyệt". Nhưng
	 *    nhập thực chi là việc của NGƯỜI ĐI MUA — chính màn hình cũng bảo nhân viên như vậy
	 *    ("Mua xong thì nhập Thực chi + ảnh chứng từ rồi bấm Gửi quyết toán"). Cổng chối trong
	 *    khi màn mời là kiểu hỏng làm người dùng tưởng mình khai sai vai trò.
	 *
	 * 🔴 CHỐT KHÔNG MẤT, CHỈ CHUYỂN VÀO ĐÂY:
	 *      · "Đã cấp tạm ứng" — người đi mua đang nhập. Nhân viên sửa được ĐƠN CỦA MÌNH.
	 *      · "Chờ quyết toán" — đã gửi, kế toán đang soát. Chỉ người duyệt/kế toán được đụng;
	 *        nhân viên muốn sửa thì nhờ trả đơn lại, và đường ấy để lại vết đầy đủ.
	 *    Gác ở lõi thì mọi đường vào đều đi qua, kể cả bản giao diện cũ trong bộ nhớ đệm.
	 */
	public static function set_line_thuc_mua( $id, $val, $actor = '' ) {
		global $wpdb;
		$cur = self::line_row( $id );
		if ( ! $cur ) { return VHCP_Util::err( 'Không tìm thấy dòng' ); }
		$ma_don = (string) $cur['ma_don'];
		$st     = self::state( $ma_don );
		if ( $st !== 'Đã cấp tạm ứng' && $st !== 'Chờ quyết toán' ) {
			return VHCP_Util::err( 'Chỉ nhập Thực chi khi "Đã cấp tạm ứng" (hoặc kế toán sửa khi "Chờ quyết toán")' );
		}
		/* Đơn của mình mới sửa được — chốt sẵn có, dùng lại nguyên vẹn. */
		$_loi = self::loi_khong_phai_don_minh( $ma_don );
		if ( '' !== $_loi ) { return VHCP_Util::err( $_loi ); }
		/* Sang "Chờ quyết toán" thì nhân viên thôi đụng: kế toán đang đối chiếu, sửa số lúc ấy
		   là làm hỏng chính lượt soát đang diễn ra. */
		/* ⚠️ HỎI `la_nhan_vien()`, ĐỪNG LIỆT KÊ BỐN VAI KIA. Bản đầu viết "không nằm trong
		   [Admin, Quản lý, Kế toán cá nhân, Kế toán NCC] thì chối" — nghe thì như nhau, nhưng nó
		   chối luôn cả vai RỖNG và mọi vai TỰ TẠO chưa kịp khai vào danh sách. Bộ thử dính ngay
		   (phiên thử không mang vai nào), và ngoài đời thì vai tự tạo "Kế toán vùng" sẽ bị chối
		   oan mà không ai hiểu vì sao. `la_nhan_vien()` đã lo phần vai kế thừa. */
		if ( 'Chờ quyết toán' === $st && VHCP_Auth::la_nhan_vien() ) {
			return VHCP_Util::err( 'Đơn đã gửi kế toán — nhân viên thôi sửa được Thực chi. '
				. 'Cần sửa thì nhờ kế toán trả đơn lại.' );
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

		/* Cất bản sao TRƯỚC khi xoá — xem khối THÙNG RÁC. Cất sau thì không còn gì để cất. */
		self::vao_thung_rac_dong( $cur );
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
		/* Gửi được khi CÓ HẠNG MỤC (dòng chi) HOẶC CÓ SỐ TẠM ỨNG (anh Thắng 01/09/2026: "1 là có
		   hạng mục, 2 là có số tạm ứng là được gửi đơn"). Đơn chỉ có tạm ứng = xin ứng trước chưa
		   liệt kê; đơn chỉ có hạng mục, tạm ứng 0 = cơ sở lên trễ, tự mua rồi kế toán bù. */
		$tt_bang = VHCP_DB::t( 'tamung' );
		$tu_sum  = VHCP_Util::num( $wpdb->get_var( $wpdb->prepare(
			"SELECT SUM(so) FROM $tt_bang WHERE ma_don=%s", (string) $ma_don ) ) );
		if ( ! $n && ! ( $tu_sum > 0 ) ) {
			return VHCP_Util::err( 'Chưa có gì để gửi — thêm ít nhất 1 hạng mục, hoặc nhập số tạm ứng.' );
		}
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
		self::bao_noi_bo( $ma_don, 'đã được duyệt tạm ứng — chờ kế toán chuyển tiền' );
		return VHCP_Util::ok();
	}

	/**
	 * TỔNG XIN HIỆN TẠI của một đơn — hạng mục + dự phòng, KHÔNG cộng bù trừ tuần trước.
	 *
	 * ⚠️ Đi qua `get_don()` chứ không tự cộng lại các dòng: luật gom hạng mục có chỗ tinh
	 *    (`Nháp` thì gộp cả dòng phát sinh, sau đó thì không; có hàng tạm ứng tay thì lấy hàng
	 *    ấy). Chép luật sang đây là dựng bản thứ hai cho cùng một câu hỏi, rồi hai bản lệch nhau.
	 *
	 * 🔴 Trừ thẳng `tamUngDuyet` ra khỏi phép tính bằng cách đọc `tamUng` theo cơ sở — `tongCN`
	 *    trả về số ĐÃ DUYỆT khi có, nên dùng nó ở đây là quay lại chính con số cần thay.
	 */
	public static function tong_xin_hien_tai( $ma_don ) {
		$g = self::get_don( $ma_don, false );
		if ( empty( $g['success'] ) ) { return null; }
		$t = 0;
		foreach ( (array) ( isset( $g['tamUng'] ) ? $g['tamUng'] : array() ) as $v ) {
			$t += VHCP_Util::num( $v );
		}
		return $t + VHCP_Util::num( $g['don']['duPhong'] );
	}

	/**
	 * DUYỆT LẠI SỐ TẠM ỨNG cho đơn đã duyệt nhưng CHƯA CẤP TIỀN.
	 *
	 * 🔴 Anh Thắng 31/08/2026, ảnh hai khối lệch nhau: *"Làm sao để điều chỉnh, 2 có số tổng tạm
	 *    ứng khác nhau."* Khối "Tạm ứng xin" nói 15.000.032đ, khối Quyết toán nói 9.405.032đ —
	 *    vì số duyệt là một con số CHỤP LẠI lúc bấm duyệt, còn tổng xin thì đổi được sau đó
	 *    (nhân viên sửa hạng mục, hoặc — như ca này — luật bù trừ vừa đổi).
	 *
	 *    Màn hình vốn ĐÃ kêu lên "Tổng xin đã đổi sau khi duyệt… Báo lại quản lý để duyệt lại số
	 *    mới", nhưng KHÔNG có đường nào làm việc ấy: `duyet_tam_ung()` chỉ nhận đơn còn ở "Chờ
	 *    duyệt tạm ứng". Một câu nhắc trỏ vào chỗ không có cửa thì tệ hơn là không nhắc.
	 *
	 * 🔴 CHỈ KHI CHƯA CẤP TIỀN. Cấp rồi thì số đã ra khỏi két và đã vào sổ quỹ; sửa con số duyệt
	 *    lúc ấy là làm sổ quỹ nói một đằng, đơn nói một nẻo. Cần đổi thì trả đơn lại cho nhân
	 *    viên sửa rồi đi lại quy trình — đường ấy có sẵn và để lại vết đầy đủ.
	 *
	 * @param string $so Rỗng = lấy đúng tổng xin hiện tại của đơn.
	 */
	public static function duyet_lai_tam_ung( $ma_don, $nguoi = '', $so = '' ) {
		$_loi = self::loi_khong_phai_don_minh( $ma_don );
		if ( '' !== $_loi ) { return VHCP_Util::err( $_loi ); }
		$d = self::don_row( $ma_don );
		if ( ! $d ) { return VHCP_Util::err( 'Không tìm thấy đơn' ); }
		$st = (string) $d['trang_thai'];
		if ( 'Chờ cấp tạm ứng' !== $st ) {
			return VHCP_Util::err( 'Chỉ duyệt lại được đơn ĐÃ duyệt mà CHƯA cấp tiền. Đơn này đang ở "'
				. $st . '"'
				. ( 'Chờ duyệt tạm ứng' === $st ? ' — bấm Duyệt tạm ứng như bình thường.'
					: ( self::da_chot( $st ) || 'Đã cấp tạm ứng' === $st || 'Chờ quyết toán' === $st
						? ' — tiền đã ra khỏi két, muốn đổi số thì Trả lại đơn cho nhân viên sửa rồi duyệt lại từ đầu.'
						: '.' ) ) );
		}

		$cu  = VHCP_Util::num( $d['tam_ung_duyet'] );
		$moi = ( '' === trim( (string) $so ) ) ? self::tong_xin_hien_tai( $ma_don ) : VHCP_Util::num( $so );
		if ( null === $moi ) { return VHCP_Util::err( 'Không đọc ra được tổng xin hiện tại của đơn.' ); }
		if ( $moi < 0 ) { return VHCP_Util::err( 'Số tạm ứng không âm được.' ); }
		if ( VHCP_Util::num( $moi ) === $cu ) {
			return VHCP_Util::err( 'Số duyệt đang đúng bằng ' . VHCP_Util::tien( $cu ) . ' rồi — không có gì để đổi.' );
		}

		$nguoi = trim( (string) $nguoi );
		if ( '' === $nguoi ) { $nguoi = VHCP_Auth::nguoi(); }

		self::upd_don( $ma_don, array(
			'tam_ung_duyet' => VHCP_Util::num( $moi ),
			'nguoi_duyet'   => $nguoi,
			'ngay_duyet'    => VHCP_Util::now_sql(),
		) );
		self::ghi_vet( $ma_don, 'Duyệt lại số tạm ứng',
			VHCP_Util::tien( $cu ) . '  →  ' . VHCP_Util::tien( $moi ) );
		self::bao_noi_bo( $ma_don, 'đã được duyệt LẠI số tạm ứng: ' . VHCP_Util::tien( $moi ) );
		return VHCP_Util::ok( array( 'cu' => $cu, 'moi' => VHCP_Util::num( $moi ) ) );
	}

	/**
	 * DỌN ĐƠN CÒN DÍNH BÙ TRỪ THEO LUẬT CŨ.
	 *
	 * 🔴 Anh Thắng 31/08/2026: *"tạm ứng 15tr, chi 15tr mà vẫn thiếu"*. Đơn duyệt TRƯỚC bản
	 *    1.53.0 giữ con số duyệt đã bị trừ bù trừ tuần trước; luật nay đã đổi nhưng con số ấy
	 *    nằm cứng trong sổ, nên khối đối chiếu vẫn so 15.000.032đ đã chi với 9.405.032đ đã duyệt
	 *    và ra "THIẾU 5.595.000đ — kế toán bù". Tiền không thiếu; chỉ con số duyệt là cũ.
	 *
	 *    Bấm "Duyệt lại" từng đơn thì đúng nhưng chậm khi có mấy chục đơn dính.
	 *
	 * 🔴 CHỈ NHẬN ĐƠN CÓ DẤU VẾT SỐ HỌC RÕ RÀNG: `tam_ung_duyet` đúng bằng
	 *    `tổng xin + dự phòng + bù trừ`. Quản lý CỐ Ý duyệt thấp hơn số xin là chuyện có thật và
	 *    hoàn toàn hợp lệ — không được đụng vào. Chênh phải đúng bằng con số bù trừ thì mới là
	 *    dấu tay của luật cũ, và lúc ấy sửa mới là khôi phục chứ không phải đoán.
	 *
	 * 🔴 NHÓM THỨ HAI — SỐ DUYỆT MỒ CÔI. Anh Thắng 01/09/2026: *"đơn này thì lại không có"*.
	 *    Chính cái đơn sinh ra tính năng này lại không lọt lưới, vì nó đã bị TRẢ LẠI về "Nháp" —
	 *    mà bản đầu chỉ nhặt đơn ở "Chờ cấp tạm ứng". Đơn về Nháp vẫn giữ nguyên `tam_ung_duyet`
	 *    của lần duyệt cũ, và con số ấy vẫn đè lên tổng xin ở khối Quyết toán. Xem thân hàm.
	 *
	 * ⚠️ CHƯA CẤP TIỀN. Cấp rồi thì số đã vào sổ quỹ — cùng lý do với `duyet_lai_tam_ung()`.
	 *
	 * ⚠️ DÒ TRƯỚC, CHỐT SAU. `$chot = false` chỉ trả danh sách xem trước, không đụng dữ liệu.
	 *    Sửa tiền hàng loạt mà chạy thẳng thì sai một phát là sai cả chồng đơn.
	 *
	 * 🔴 CHỐT THÌ PHẢI CHỈ ĐÍCH DANH TỪNG ĐƠN. Anh Thắng 01/09/2026: *"Cần tích vào dọn đơn chọn
	 *    thôi, để tránh phát lỗi cho đơn khác"*. Dò ra 5 đơn mà chỉ muốn sửa 1 thì trước đây
	 *    không có cách nào — bấm là cả 5 cùng đổi số.
	 *
	 *    Nay `$chi_ma` là DANH SÁCH BẮT BUỘC khi chốt: rỗng thì chối thẳng, không có đường
	 *    "sửa tất cả" ngầm. Bỏ sót một tham số mà hoá ra sửa cả sổ là kiểu hỏng không ai kịp
	 *    thấy trước khi tiền đã đổi.
	 *
	 * @param array $chi_ma Mã đơn được tích chọn. Chỉ có tác dụng khi `$chot`.
	 */
	public static function don_bu_tru_cu( $chot = false, $chi_ma = null ) {
		$chon = array();
		foreach ( (array) $chi_ma as $m ) {
			$m = trim( (string) $m );
			if ( '' !== $m ) { $chon[ $m ] = true; }
		}
		if ( $chot && ! $chon ) {
			return VHCP_Util::err( 'Chưa chọn đơn nào. Tích vào những đơn cần dọn rồi bấm lại — '
				. 'khối này cố ý KHÔNG có đường sửa tất cả.' );
		}

		$ds = array(); $da_sua = 0;
		foreach ( self::don_rows() as $d ) {
			$st  = (string) $d['trang_thai'];
			$ma  = (string) $d['ma_don'];
			$duy = VHCP_Util::num( $d['tam_ung_duyet'] );

			/* ── NHÓM 2: SỐ DUYỆT MỒ CÔI ────────────────────────────────────────────────────
			   "Nháp" và "Chờ duyệt tạm ứng" theo đúng định nghĩa là CHƯA AI DUYỆT — chỉ
			   `duyet_tam_ung()` và `duyet_lai_tam_ung()` mới đặt được `tam_ung_duyet`, và cả
			   hai đều đẩy đơn sang "Chờ cấp tạm ứng". Nên đơn ở hai trạng thái này mà còn mang
			   số duyệt là dữ liệu tự mâu thuẫn: dấu tích của một lần duyệt đã bị trả lại (hoặc
			   một bản nạp .csv cũ). Xoá nó đi là khôi phục sự thật, không phải đoán số.

			   🔴 KHÔNG dùng phép so "duyệt = xin + bù trừ" cho nhóm này được. `bu_tru` là con
			      số ĐỘNG: `chot_bu_tru()` tính lại và GHI ĐÈ mỗi lần mở đơn ở "Nháp". Đơn của
			      anh Thắng duyệt với bù trừ −5.595.000đ, nay ô ấy đã thành −419.500đ — con số
			      cũ không còn ở đâu trong sổ để mà đối chiếu. Cái chắc chắn duy nhất còn lại là
			      trạng thái: chưa duyệt thì không được mang số duyệt. */
			if ( 'Nháp' === $st || 'Chờ duyệt tạm ứng' === $st ) {
				if ( $duy <= 0 ) { continue; }
				$xin = self::tong_xin_hien_tai( $ma );
				$ds[] = array(
					'maDon'  => $ma,
					'ky'     => (string) $d['ky'],
					'nguoi'  => (string) $d['nguoi_lap'],
					'duyet'  => $duy,
					'buTru'  => 0,
					'moi'    => ( null === $xin ) ? 0 : $xin,
					'loai'   => 'mocoi',
					'trangThai' => $st,
					'daSua'  => ( $chot && isset( $chon[ $ma ] ) ),
				);
				if ( $chot && isset( $chon[ $ma ] ) ) {
					self::upd_don( $ma, array(
						'tam_ung_duyet' => null,
						'nguoi_duyet'   => '',
						'ngay_duyet'    => null,
					) );
					self::ghi_vet( $ma, 'Gỡ số duyệt mồ côi (đơn đang ở "' . $st . '")',
						VHCP_Util::tien( $duy ) . '  →  chưa duyệt' );
					$da_sua++;
				}
				continue;
			}

			/* ── NHÓM 1: ĐÃ DUYỆT, CHƯA CẤP TIỀN, CÒN DÍNH BÙ TRỪ ──────────────────────────── */
			if ( 'Chờ cấp tạm ứng' !== $st ) { continue; }
			$bt = VHCP_Util::num( $d['bu_tru'] );
			if ( 0.0 === (float) $bt ) { continue; }
			$xin = self::tong_xin_hien_tai( $ma );
			if ( null === $xin ) { continue; }
			/* Dấu tay của luật cũ: số duyệt = xin + bù trừ. Lệch một đồng là không phải, bỏ qua. */
			if ( abs( ( $xin + $bt ) - $duy ) > 0.5 ) { continue; }
			$ds[] = array(
				'maDon'  => $ma,
				'ky'     => (string) $d['ky'],
				'nguoi'  => (string) $d['nguoi_lap'],
				'duyet'  => $duy,
				'buTru'  => $bt,
				'moi'    => $xin,
				'loai'   => 'butru',
				'trangThai' => $st,
				'daSua'  => ( $chot && isset( $chon[ $ma ] ) ),
			);
			if ( $chot && isset( $chon[ $ma ] ) ) {
				self::upd_don( $ma, array( 'tam_ung_duyet' => VHCP_Util::num( $xin ) ) );
				self::ghi_vet( $ma, 'Dọn bù trừ cũ khỏi số duyệt',
					VHCP_Util::tien( $duy ) . '  →  ' . VHCP_Util::tien( $xin )
					. ' (gỡ phần bù trừ ' . VHCP_Util::tien( $bt ) . ' theo luật trước 1.53.0)' );
				$da_sua++;
			}
		}
		$so_mocoi = 0;
		foreach ( $ds as $x ) { if ( 'mocoi' === $x['loai'] ) { $so_mocoi++; } }
		return VHCP_Util::ok( array(
			'items'   => array_slice( $ds, 0, 400 ),
			'tong'    => count( $ds ),
			'soMoCoi' => $so_mocoi,
			'soBuTru' => count( $ds ) - $so_mocoi,
			'daSua'   => $da_sua,
		) );
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
		self::bao_noi_bo( $ma_don, 'đã ĐƯỢC CẤP TẠM ỨNG (' . (string) $ht_cap . ')' );
		return VHCP_Util::ok();
	}

	/**
	 * ADMIN TRẢ NGƯỢC "ĐÃ CẤP TẠM ỨNG" → "CHỜ CẤP TẠM ỨNG" (anh Thắng 01/09/2026) — gỡ đúng lượt
	 * CẤP để làm lại khi đơn/cấp sai, GIỮ nguyên số đã duyệt (khác `tra_lai_don` vốn kéo về "Nháp").
	 *
	 * ⚠️ CHỈ ADMIN (gác ở API) · chỉ khi đơn đang "Đã cấp tạm ứng" (chưa quyết toán/chốt sổ) ·
	 *    BẮT nêu lý do, ghi vết + báo nội bộ. Cấp = tiền đã ra khỏi két: gỡ lượt cấp nghĩa là tiền
	 *    đã được thu về / lượt cấp là nhầm — người bấm chịu trách nhiệm con số ấy khớp sổ quỹ.
	 */
	public static function go_cap_tam_ung( $ma_don, $ly_do = '' ) {
		$d = self::don_row( $ma_don );
		if ( ! $d ) { return VHCP_Util::err( 'Không tìm thấy đơn' ); }
		if ( (string) $d['trang_thai'] !== 'Đã cấp tạm ứng' ) {
			return VHCP_Util::err( 'Chỉ trả ngược được đơn đang "Đã cấp tạm ứng". Đơn này đang ở "' . (string) $d['trang_thai'] . '".' );
		}
		$ly_do = trim( (string) $ly_do );
		if ( '' === $ly_do ) { return VHCP_Util::err( 'Phải nêu lý do — đây là lượt gỡ cấp tiền của đơn đã đánh dấu cấp.' ); }
		self::upd_don( $ma_don, array(
			'trang_thai' => 'Chờ cấp tạm ứng',
			'nguoi_cap'  => '',
			'ngay_cap'   => null,
			'ht_cap'     => '',
			'anh_cap'    => '',
		) );
		self::ghi_vet( $ma_don, 'Admin trả ngược "Đã cấp" → "Chờ cấp tạm ứng"',
			'gỡ lượt cấp (' . (string) $d['ht_cap'] . ') · ' . $ly_do );
		self::bao_noi_bo( $ma_don, 'bị Admin TRẢ NGƯỢC về "Chờ cấp tạm ứng" để làm lại — ' . $ly_do );
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

	/**
	 * TRẢ ĐƠN VỀ CHO NGƯỜI LẬP SỬA.
	 *
	 * 🔴 VỀ "NHÁP" THÌ GỠ LUÔN SỐ ĐÃ DUYỆT. Anh Thắng 01/09/2026, ảnh đơn SNOW NHÀ TUYẾT BÌNH
	 *    DƯƠNG: *"đơn này thì lại không có"* — đơn xin 15.000.032đ, đầu đơn vẫn ghi
	 *    *"đã duyệt: 9.405.032đ"* dù đơn đang ở **Nháp**, tức CHƯA GỬI DUYỆT.
	 *
	 *    Con số ấy là của lần duyệt TRƯỚC, rồi quản lý trả đơn lại. Trả lại nghĩa là lần duyệt
	 *    ấy không còn giá trị — nhưng `tam_ung_duyet` vẫn nằm nguyên trong sổ. Nó gây hai chuyện
	 *    thật, không phải chuyện hình thức:
	 *
	 *      · `get_don()` lấy `$ad_total = $tu_duyet > 0 ? $tu_duyet : $tu_tay_sum` — số duyệt
	 *        MỒ CÔI đè lên tổng xin, nên khối Quyết toán so tiền đã chi với con số cũ rồi báo
	 *        "Thiếu 5.595.000đ" dù chẳng thiếu đồng nào.
	 *      · Nhân viên gửi lại, màn Duyệt tạm ứng điền sẵn con số cũ — quản lý bấm duyệt là
	 *        duyệt trúng số của lần trước.
	 *
	 * 🔴 CHỈ GỠ KHI VỀ "NHÁP". Đơn "Chờ quyết toán" bị trả về "Đã cấp tạm ứng" thì tiền ĐÃ ra
	 *    khỏi két và đã vào sổ quỹ — con số duyệt ở đó là chứng từ, đụng vào là làm sổ quỹ nói
	 *    một đằng đơn nói một nẻo. Giữ nguyên.
	 */
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
		$duyet_cu = VHCP_Util::num( $d['tam_ung_duyet'] );
		$go_duyet = ( 'Nháp' === $target && $duyet_cu > 0 );
		if ( $go_duyet ) {
			$data['tam_ung_duyet'] = null;
			$data['nguoi_duyet']   = '';
			$data['ngay_duyet']    = null;
		}
		self::upd_don( $ma_don, $data );
		if ( $go_duyet ) {
			self::ghi_vet( $ma_don, 'Gỡ số đã duyệt (đơn bị trả về Nháp)',
				VHCP_Util::tien( $duyet_cu ) . '  →  chưa duyệt' );
		}
		self::bao_noi_bo( $ma_don, 'bị TRẢ LẠI' . ( $ly_do ? ' — ' . $ly_do : '' ) . '. Sửa rồi gửi lại nhé.' );
		return VHCP_Util::ok( array( 'target' => $target, 'goDuyet' => $go_duyet ) );
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
		/* 🔴 CẤT BẢN SAO TRƯỚC KHI XOÁ — anh Thắng 31/08/2026: *"Bổ sung thêm nút hoàn tác vụ,
		   cho lỡ xóa nhầm đơn hoặc chi phí thì hoàn lại lệnh đó."* Cất SAU thì không còn gì để
		   mà cất; ba lượt DELETE dưới đây là điểm không quay lại. */
		self::vao_thung_rac_don( $ma_don );
		$wpdb->delete( VHCP_DB::t( 'chiphi' ), array( 'ma_don' => (string) $ma_don ) );
		$wpdb->delete( VHCP_DB::t( 'tamung' ), array( 'ma_don' => (string) $ma_don ) );
		$wpdb->delete( VHCP_DB::t( 'don' ), array( 'ma_don' => (string) $ma_don ) );
		return VHCP_Util::ok();
	}

	/* =========================================================================================
	 * THÙNG RÁC — XOÁ NHẦM THÌ HOÀN LẠI ĐƯỢC
	 * =========================================================================================
	 * Anh Thắng 31/08/2026: *"Bổ sung thêm nút hoàn tác vụ, cho lỡ xóa nhầm đơn hoặc chi phí
	 * thì hoàn lại lệnh đó."*
	 *
	 * 🔴 NHẬT KÝ KHÔNG THAY ĐƯỢC THÙNG RÁC. Nhật ký ghi "ai xoá, mấy dòng, tổng bao nhiêu tiền"
	 *    — đủ để biết MẤT BAO NHIÊU, không đủ để biết MẤT NHỮNG GÌ, và không dựng lại được một
	 *    dòng nào. Muốn hoàn tác thì phải giữ nguyên văn hàng đã xoá.
	 *
	 * ⚠️ KHÔNG TỰ DỌN THÙNG RÁC THEO NGÀY. Dọn tự động là xoá thật lần thứ hai, lần này không
	 *    ai bấm và không ai biết. Thùng đầy thì người ta dọn tay, và lúc dọn thì họ nhìn thấy
	 *    mình đang bỏ đi cái gì.
	 */

	/** Cất nguyên văn một đơn (kèm dòng chi + tạm ứng) vào thùng rác. */
	private static function vao_thung_rac_don( $ma_don ) {
		global $wpdb;
		$ma_don = (string) $ma_don;
		$d = self::don_row( $ma_don );
		if ( ! $d ) { return 0; }
		$chi = VHCP_DB::rows( $wpdb->prepare(
			'SELECT * FROM ' . VHCP_DB::t( 'chiphi' ) . ' WHERE ma_don=%s ORDER BY stt ASC', $ma_don ) );
		$tu  = VHCP_DB::rows( $wpdb->prepare(
			'SELECT * FROM ' . VHCP_DB::t( 'tamung' ) . ' WHERE ma_don=%s ORDER BY id ASC', $ma_don ) );
		$tien = 0;
		foreach ( $chi as $r ) { $tien += VHCP_Util::num( $r['thanh_tien'] ); }
		return self::vao_thung_rac( 'don', $ma_don,
			'Đơn ' . $ma_don . ' · ' . (string) $d['ky'] . ' · ' . (string) $d['nguoi_lap']
				. ' · ' . count( $chi ) . ' dòng · ' . VHCP_Util::out_num( $tien )
				. ' · trạng thái ' . (string) $d['trang_thai'],
			array( 'don' => $d, 'chiphi' => $chi, 'tamung' => $tu ),
			isset( $d['don_vi'] ) ? (string) $d['don_vi'] : '' );
	}

	/** Cất nguyên văn MỘT dòng chi vào thùng rác. */
	private static function vao_thung_rac_dong( $row ) {
		if ( ! $row ) { return 0; }
		return self::vao_thung_rac( 'dong', (string) $row['id'],
			'Dòng chi trong đơn ' . (string) $row['ma_don'] . ' · ' . self::ta_dong( $row ),
			array( 'chiphi' => $row ),
			VHCP_DonVi::cua_don( (string) $row['ma_don'] ) );
	}

	private static function vao_thung_rac( $loai, $khoa, $nhan, $du_lieu, $don_vi = '' ) {
		global $wpdb;
		$wpdb->insert( VHCP_DB::t( 'thungrac' ), array(
			'luc'     => current_time( 'mysql' ),
			'loai'    => (string) $loai,
			'khoa'    => (string) $khoa,
			'nhan'    => (string) $nhan,
			'du_lieu' => wp_json_encode( $du_lieu ),
			'nguoi'   => VHCP_Auth::nguoi(),
			'vai_tro' => VHCP_Auth::vai_tro(),
			'don_vi'  => (string) $don_vi,
			'da_hoan' => 0,
		) );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Thùng rác — những gì còn hoàn lại được.
	 *
	 * ⚠️ LỌC THEO ĐƠN VỊ y như mọi màn khác: kế toán POSH không được thấy đơn K&H trong thùng
	 *    rác, kẻo cửa này thành đường vòng đọc sổ bên kia.
	 */
	public static function ds_thung_rac( $limit = 50, $ca = false ) {
		global $wpdb;
		$limit = (int) $limit;
		if ( $limit <= 0 || $limit > 300 ) { $limit = 50; }
		$rows = VHCP_DB::rows( $wpdb->prepare(
			'SELECT * FROM ' . VHCP_DB::t( 'thungrac' ) . ' ORDER BY id DESC LIMIT %d', $limit * 3 ) );
		$dv_toi = VHCP_DonVi::cua_toi();
		$nv     = VHCP_Auth::la_nhan_vien();
		$toi    = mb_strtolower( trim( (string) VHCP_Auth::nguoi() ) );
		$out = array();
		foreach ( $rows as $r ) {
			if ( ! $ca && (int) $r['da_hoan'] ) { continue; }
			$dv = trim( (string) $r['don_vi'] );
			if ( '' !== $dv && '' !== $dv_toi && ! VHCP_DonVi::bang( $dv, $dv_toi ) ) { continue; }
			/* 🔴 NHÂN VIÊN CHỈ THẤY THAO TÁC CỦA CHÍNH MÌNH. Bày cả thùng rác của cửa hàng cho
			   người lập đơn là cho họ đọc nội dung đơn của người khác — thứ mà ở màn thường họ
			   không được thấy. Cửa hoàn tác không được rộng hơn cửa xem. */
			if ( $nv && mb_strtolower( trim( (string) $r['nguoi'] ) ) !== $toi ) { continue; }
			$out[] = array(
				'id'      => (int) $r['id'],
				'luc'     => VHCP_Util::fmt_dt( $r['luc'] ),
				'loai'    => (string) $r['loai'],
				'khoa'    => (string) $r['khoa'],
				'nhan'    => (string) $r['nhan'],
				'nguoi'   => (string) $r['nguoi'],
				'vaiTro'  => (string) $r['vai_tro'],
				'daHoan'  => (int) $r['da_hoan'] ? 1 : 0,
				'hoanLuc' => VHCP_Util::fmt_dt( $r['hoan_luc'] ),
			);
			if ( count( $out ) >= $limit ) { break; }
		}
		return VHCP_Util::ok( array( 'items' => $out ) );
	}

	/**
	 * HOÀN TÁC một lượt xoá.
	 *
	 * 🔴 KHÔNG HOÀN HAI LẦN. Hoàn lần thứ hai là dựng thêm một bản sao của cùng khoản chi —
	 *    tiền đếm đôi, mà bảng vẫn trông bình thường vì hai dòng giống hệt nhau nằm cạnh nhau.
	 *
	 * 🔴 DÒNG CHI THÌ ĐƠN CHA PHẢI CÒN. Dựng một dòng chi vào một mã đơn không tồn tại là đẻ ra
	 *    một khoản tiền mồ côi: nó có trong bảng `chiphi`, cộng vào mọi báo cáo theo cơ sở, mà
	 *    mở đơn ra thì không thấy đâu.
	 */
	public static function hoan_tac( $id ) {
		global $wpdb;
		$id = (int) $id;
		$r  = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . VHCP_DB::t( 'thungrac' ) . ' WHERE id=%d', $id ), ARRAY_A );
		if ( ! $r ) { return VHCP_Util::err( 'Không tìm thấy mục này trong thùng rác.' ); }
		if ( (int) $r['da_hoan'] ) {
			return VHCP_Util::err( 'Mục này đã được hoàn lúc ' . VHCP_Util::fmt_dt( $r['hoan_luc'] )
				. ( '' !== trim( (string) $r['hoan_nguoi'] ) ? ' bởi ' . $r['hoan_nguoi'] : '' )
				. ' — hoàn lần nữa là nhân đôi số.' );
		}
		$dv = trim( (string) $r['don_vi'] );
		$dv_toi = VHCP_DonVi::cua_toi();
		if ( '' !== $dv && '' !== $dv_toi && ! VHCP_DonVi::bang( $dv, $dv_toi ) ) {
			return VHCP_Util::err( 'Mục này thuộc đơn vị ' . $dv . ' — không phải sổ của bạn.' );
		}
		if ( VHCP_Auth::la_nhan_vien()
			&& mb_strtolower( trim( (string) $r['nguoi'] ) ) !== mb_strtolower( trim( (string) VHCP_Auth::nguoi() ) ) ) {
			return VHCP_Util::err( 'Chỉ hoàn lại được thao tác của chính mình. Nhờ kế toán hoàn giúp.' );
		}

		$d = json_decode( (string) $r['du_lieu'], true );
		if ( ! is_array( $d ) ) { return VHCP_Util::err( 'Bản sao trong thùng rác đọc không ra — không dựng lại được.' ); }

		if ( 'don' === (string) $r['loai'] ) {
			$don = isset( $d['don'] ) ? (array) $d['don'] : array();
			$ma  = isset( $don['ma_don'] ) ? (string) $don['ma_don'] : '';
			if ( '' === $ma ) { return VHCP_Util::err( 'Bản sao thiếu mã đơn.' ); }
			if ( self::don_row( $ma ) ) {
				return VHCP_Util::err( 'Mã đơn ' . $ma . ' nay đã có một đơn khác đang dùng — '
					. 'không dựng đè lên được.' );
			}
			$wpdb->insert( VHCP_DB::t( 'don' ), $don );
			$so = 0;
			foreach ( (array) ( isset( $d['chiphi'] ) ? $d['chiphi'] : array() ) as $x ) {
				$wpdb->insert( VHCP_DB::t( 'chiphi' ), (array) $x );
				$so++;
			}
			foreach ( (array) ( isset( $d['tamung'] ) ? $d['tamung'] : array() ) as $x ) {
				$wpdb->insert( VHCP_DB::t( 'tamung' ), (array) $x );
			}
			self::danh_dau_hoan( $id );
			self::ghi_vet( $ma, 'Hoàn tác xoá đơn', 'Dựng lại từ thùng rác #' . $id . ' · ' . $so . ' dòng chi' );
			return VHCP_Util::ok( array( 'loai' => 'don', 'maDon' => $ma, 'soDong' => $so ) );
		}

		$row = isset( $d['chiphi'] ) ? (array) $d['chiphi'] : array();
		$ma  = isset( $row['ma_don'] ) ? (string) $row['ma_don'] : '';
		if ( '' === $ma ) { return VHCP_Util::err( 'Bản sao thiếu mã đơn.' ); }
		if ( ! self::don_row( $ma ) ) {
			return VHCP_Util::err( 'Đơn ' . $ma . ' của dòng này đã bị xoá — '
				. 'hoàn lại ĐƠN trước, dòng chi nằm sẵn trong đó.' );
		}
		if ( isset( $row['id'] ) && self::line_row( (string) $row['id'] ) ) {
			return VHCP_Util::err( 'Dòng này đã có lại trong đơn rồi.' );
		}
		$wpdb->insert( VHCP_DB::t( 'chiphi' ), $row );
		self::danh_dau_hoan( $id );
		self::ghi_vet( $ma, 'Hoàn tác xoá dòng chi',
			'Dựng lại từ thùng rác #' . $id . ' · ' . self::ta_dong( $row ) );
		return VHCP_Util::ok( array( 'loai' => 'dong', 'maDon' => $ma, 'id' => (string) $row['id'] ) );
	}

	private static function danh_dau_hoan( $id ) {
		global $wpdb;
		$wpdb->update( VHCP_DB::t( 'thungrac' ), array(
			'da_hoan'    => 1,
			'hoan_luc'   => current_time( 'mysql' ),
			'hoan_nguoi' => VHCP_Auth::nguoi(),
		), array( 'id' => (int) $id ) );
	}

	/**
	 * CHUYỂN ĐƠN / DÒNG CHI SANG ĐƠN VỊ KHÁC — kế toán POSH đẩy sang kế toán cá nhân.
	 *
	 * Anh Thắng 26/08/2026: *"Kế toán Posh có thể sẽ đẩy đơn hoặc chi phí lẻ trong đơn cho kế
	 * toán cá nhân — thì khi chuyển qua nó sẽ tạo thành 1 đơn như nhân viên tạo bình thường."*
	 *
	 * 🔴 LẬP ĐƠN MỚI, KHÔNG ĐỔI `don_vi` CỦA ĐƠN CŨ.
	 *    Đổi ô đơn vị tại chỗ thì cả cái đơn — kể cả những dòng KHÔNG chuyển, kể cả tiền tạm
	 *    ứng đã cấp, kể cả chữ ký người duyệt bên POSH — nhảy nguyên sang sổ bên kia trong một
	 *    nhịp, và sổ tháng của POSH hụt đúng chừng ấy tiền mà không có dòng nào giải thích.
	 *    Lập đơn mới thì hai bên đều còn vết: bên đây ghi "đã đẩy đi", bên kia ghi "nhận về".
	 *
	 * 🔴 DÒNG ĐÃ CHUYỂN THÌ XOÁ KHỎI ĐƠN CŨ, không nhân đôi.
	 *    Để lại cả hai nơi là một khoản chi đếm hai lần — ở đây là tiền thật, không phải một ô
	 *    hiển thị. Đơn mới sinh ra ở trạng thái **Nháp**, đúng như anh Thắng nói: "như nhân viên
	 *    tạo bình thường". Nó phải đi lại từ đầu quy trình duyệt của bên nhận, chứ không thừa
	 *    hưởng chữ ký duyệt của bên gửi.
	 *
	 * ⚠️ CHỈ CHUYỂN ĐƯỢC KHI ĐƠN CŨ CÒN SỬA ĐƯỢC. Đơn đã quyết toán mà rút một dòng ra là số
	 *    quyết toán đã chốt không còn khớp với ruột đơn nữa — `vi_sao_khong_sua()` là ranh giới
	 *    duy nhất cho chuyện đó, hỏi nó chứ đừng gõ lại danh sách trạng thái.
	 *
	 * @param string $ma_don   đơn nguồn
	 * @param array  $ids      id các dòng cần chuyển · rỗng = chuyển CẢ ĐƠN
	 * @param string $don_vi   đơn vị đích
	 * @param string $nguoi    người đứng tên đơn mới (để trống = người đang thao tác)
	 */
	public static function chuyen_don_vi( $ma_don, $ids, $don_vi, $nguoi = '' ) {
		global $wpdb;
		$_loi = self::loi_khong_phai_don_minh( $ma_don );
		if ( '' !== $_loi ) { return VHCP_Util::err( $_loi ); }
		$d = self::don_row( $ma_don );
		if ( ! $d ) { return VHCP_Util::err( 'Không tìm thấy đơn' ); }

		$dv_moi = VHCP_DonVi::chuan( $don_vi );
		$dv_cu  = VHCP_DonVi::chuan( isset( $d['don_vi'] ) ? $d['don_vi'] : '' );
		if ( VHCP_DonVi::bang( $dv_moi, $dv_cu ) ) {
			return VHCP_Util::err( 'Đơn này đã thuộc đơn vị ' . $dv_cu . ' rồi.' );
		}
		/* Chuyển sang một đơn vị mình không đọc được thì chính người vừa chuyển cũng mất dấu
		   cái mình vừa gửi đi — và không ai giải thích được đơn ấy đi đâu. */
		if ( ! in_array( $dv_moi, VHCP_DonVi::ds(), true ) ) {
			return VHCP_Util::err( 'Chưa có đơn vị "' . $dv_moi . '" — khai ở Cấu hình → Người dùng trước.' );
		}
		$_c = self::vi_sao_khong_sua( $ma_don );
		if ( '' !== $_c ) { return VHCP_Util::err( $_c ); }

		$tat_ca = self::rows_of_don( $ma_don );
		if ( ! $tat_ca ) { return VHCP_Util::err( 'Đơn này chưa có dòng chi nào để chuyển.' ); }

		$ids  = array_values( array_filter( array_map( 'strval', (array) $ids ), 'strlen' ) );
		$chon = array();
		foreach ( $tat_ca as $r ) {
			if ( ! $ids || in_array( (string) $r['id'], $ids, true ) ) { $chon[] = $r; }
		}
		if ( ! $chon ) { return VHCP_Util::err( 'Không có dòng nào khớp để chuyển.' ); }

		$nguoi = trim( (string) $nguoi );
		if ( '' === $nguoi ) { $nguoi = VHCP_Auth::nguoi(); }

		/* Đơn mới mang KỲ của đơn cũ: một khoản chi ngày 12/8 mà rơi vào kỳ của ngày chuyển là
		   sổ hai bên lệch kỳ, và báo cáo tuần không bao giờ khớp lại được. */
		$moi = self::create_don( (string) $d['ky'], $nguoi );
		if ( empty( $moi['success'] ) ) { return $moi; }
		$ma_moi = (string) $moi['maDon'];
		/* `create_don()` lấy đơn vị theo NHÀ của người đứng tên; ở đây đích do người chuyển
		   chọn, nên đặt lại thẳng. */
		$wpdb->update( VHCP_DB::t( 'don' ), array( 'don_vi' => $dv_moi ), array( 'ma_don' => $ma_moi ) );

		$tien = 0;
		foreach ( $chon as $r ) {
			$wpdb->update( VHCP_DB::t( 'chiphi' ), array( 'ma_don' => $ma_moi ), array( 'id' => (string) $r['id'] ) );
			$tien += VHCP_Util::num( $r['thanh_tien'] );
		}

		$ta = count( $chon ) . ' dòng · ' . number_format( $tien, 0, ',', '.' ) . 'đ';
		self::ghi_vet( $ma_don, 'Chuyển sang đơn vị khác',
			'Đẩy ' . $ta . ' sang ' . $dv_moi . ' — đơn mới ' . $ma_moi );
		self::ghi_vet( $ma_moi, 'Nhận từ đơn vị khác',
			'Nhận ' . $ta . ' từ ' . $dv_cu . ' — đơn gốc ' . $ma_don );

		return VHCP_Util::ok( array(
			'maDonMoi' => $ma_moi,
			'soDong'   => count( $chon ),
			'soTien'   => $tien,
			'donVi'    => $dv_moi,
			/* Chuyển hết dòng đi thì đơn cũ thành cái vỏ rỗng — nói ra để màn mời xoá, chứ
			   đừng tự xoá: xoá thay người ta là mất luôn nhật ký của đơn ấy. */
			'goc_rong' => count( $chon ) >= count( $tat_ca ),
		) );
	}

	/**
	 * KỲ CỦA TUẦN KẾ TIẾP — hàm THUẦN, vào một chuỗi kỳ, ra một chuỗi kỳ.
	 *
	 * ⚠️ ĐI QUA `khoang_ky()` RỒI `nhan_ky()`, không tự cộng 7 vào con số trong tên. Tên kỳ mang
	 *    cả THÁNG của ngày cuối tuần (`T9/2026 (31/8-6/9/2026)`), nên cộng tay là sai ngay ở
	 *    tuần bắc qua tháng — đúng cái tuần hay phải nhảy đơn nhất.
	 */
	public static function ky_ke_tiep( $ky ) {
		list( , $den ) = self::khoang_ky( $ky );
		if ( '' === $den ) { return ''; }
		$ts = strtotime( $den . ' 00:00:00 UTC' );
		if ( ! $ts ) { return ''; }
		return self::nhan_ky( gmdate( 'Y-m-d', $ts + 86400 ) );
	}

	/**
	 * DANH SÁCH KỲ QUANH MỘT KỲ — để màn hình bày ra ô chọn "nhảy sang tuần nào".
	 *
	 * Bắt gõ tay chuỗi `T9/2026 (7/9-13/9/2026)` là mời người ta gõ sai một dấu gạch rồi đơn
	 * rơi vào một kỳ không tồn tại, mà lọc theo tuần thì không bao giờ thấy nó nữa.
	 *
	 * @param string $ky    Kỳ đang đứng.
	 * @param int    $truoc Bao nhiêu tuần TRƯỚC (nhảy lùi cũng có thật: đơn lập nhầm tuần).
	 * @param int    $sau   Bao nhiêu tuần SAU.
	 */
	public static function ds_ky_quanh( $ky, $truoc = 4, $sau = 8 ) {
		list( $tu ) = self::khoang_ky( $ky );
		if ( '' === $tu ) { return array(); }
		$ts = strtotime( $tu . ' 00:00:00 UTC' );
		if ( ! $ts ) { return array(); }
		$ds = array();
		for ( $i = -abs( (int) $truoc ); $i <= abs( (int) $sau ); $i++ ) {
			$k = self::nhan_ky( gmdate( 'Y-m-d', $ts + $i * 7 * 86400 ) );
			if ( '' !== $k && ! in_array( $k, $ds, true ) ) { $ds[] = $k; }
		}
		return $ds;
	}

	/**
	 * Cửa API của `ds_ky_quanh()`: nhận MÃ ĐƠN, trả danh sách kỳ quanh kỳ của chính đơn ấy.
	 *
	 * ⚠️ Nhận mã đơn chứ không nhận chuỗi kỳ: giao diện đang cầm sẵn mã đơn, còn chuỗi kỳ thì
	 *    nó phải tự dựng lại — mà dựng lại một chuỗi có khuôn chặt như thế ở phía trình duyệt
	 *    là chỗ sinh ra kỳ gõ sai.
	 */
	public static function ds_ky_quanh_api( $ma_don ) {
		$d = self::don_row( $ma_don );
		if ( ! $d ) { return VHCP_Util::err( 'Không tìm thấy đơn' ); }
		$ky = trim( (string) $d['ky'] );
		return VHCP_Util::ok( array(
			'ky'      => $ky,
			'keTiep'  => self::ky_ke_tiep( $ky ),
			'ds'      => self::ds_ky_quanh( $ky ),
		) );
	}

	/**
	 * NHẢY ĐƠN SANG TUẦN KHÁC — cả đơn, kèm tạm ứng, sang kỳ mới.
	 *
	 * 🔴 Anh Thắng 31/08/2026: *"Trường hợp 1 đơn không quyết toán kịp tuần đó thì không thể
	 *    [quyết toán] đơn đó, vì quyết toán theo tuần. Nên kế toán sẽ gửi lệnh nhảy đơn cho
	 *    tuần tiếp theo (hoặc tuần chỉ định), để chi phí tạm ứng của đơn đó đi theo."*
	 *
	 *    Trước bản này không có đường nào làm việc ấy: kỳ ghi một lần lúc lập đơn rồi đứng yên.
	 *    Đơn không kịp chứng từ trong tuần thì nằm lại kỳ cũ mãi — kế toán chốt tuần xong vẫn
	 *    còn một đơn treo, mà đóng tuần thì đơn ấy mất chỗ.
	 *
	 * 🔴 CHUYỂN CẢ ĐƠN, KHÔNG TÁCH DÒNG. Khác hẳn `chuyen_don_vi()` (đẩy vài dòng sang một đơn
	 *    MỚI ở sổ bên kia). Ở đây tạm ứng đã cấp phải đi theo nguyên vẹn — tách ra là số tạm
	 *    ứng nằm một tuần còn chứng từ nằm tuần khác, và không tuần nào khớp lại được.
	 *
	 * ⚠️ KHÔNG ĐỤNG NGÀY CỦA DÒNG CHI. Ngày mua là chuyện đã xảy ra; nắn nó theo kỳ mới là sửa
	 *    lịch sử để cho vừa một cái ngăn. Dòng chi ngày 3/9 nằm trong đơn kỳ tuần sau là đúng
	 *    và đọc ra được — đó chính là điều cần thấy khi soi lại.
	 *
	 * ⚠️ KHÔNG đổi trạng thái. Đơn đang "Chờ quyết toán" thì nhảy tuần xong vẫn "Chờ quyết
	 *    toán" — nhảy tuần là dời CHỖ CHỐT, không phải làm lại quy trình.
	 *
	 * @param string $ma_don
	 * @param string $ky_moi Kỳ đích. Rỗng = tuần kế tiếp của chính đơn ấy.
	 * @param string $ly_do  Vì sao nhảy — vào nhật ký.
	 */
	public static function chuyen_ky( $ma_don, $ky_moi = '', $ly_do = '', $nguoi = '' ) {
		$_loi = self::loi_khong_phai_don_minh( $ma_don );
		if ( '' !== $_loi ) { return VHCP_Util::err( $_loi ); }
		$d = self::don_row( $ma_don );
		if ( ! $d ) { return VHCP_Util::err( 'Không tìm thấy đơn' ); }

		$ky_cu = trim( (string) $d['ky'] );
		if ( '' === $ky_cu ) {
			return VHCP_Util::err( 'Đơn này chưa có kỳ nên không biết nhảy từ đâu. '
				. 'Sửa kỳ ở Cấu hình → Sửa kỳ hỏng trước.' );
		}

		/* 🔴 ĐÃ QUYẾT TOÁN / ĐÃ XUẤT MISA THÌ KHÔNG NHẢY. Số đã vào sổ, mà kỳ là cái ngăn nó
		   nằm; kéo sang tuần khác là báo cáo của HAI tuần cùng sai, và tuần đã chốt thì không
		   ai mở lại để đối chiếu nữa. Cần sửa thì mở lại đơn theo đường của nó. */
		$_c = self::vi_sao_khong_sua( $ma_don );
		if ( '' !== $_c ) { return VHCP_Util::err( $_c ); }

		$ky_moi = trim( (string) $ky_moi );
		if ( '' === $ky_moi ) { $ky_moi = self::ky_ke_tiep( $ky_cu ); }
		if ( '' === $ky_moi ) {
			return VHCP_Util::err( 'Không đọc ra được tuần kế tiếp của kỳ "' . $ky_cu . '".' );
		}
		/* Kỳ đích phải ĐỌC RA ĐƯỢC khoảng ngày. Nhận bừa một chuỗi tự do thì đơn rơi vào một
		   kỳ không tồn tại, và lọc theo tuần không bao giờ thấy nó nữa — mất đơn trong im lặng,
		   đúng kiểu hỏng mà cả bảng vẫn trông bình thường. */
		list( $tu_m, $den_m ) = self::khoang_ky( $ky_moi );
		if ( '' === $tu_m || '' === $den_m ) {
			return VHCP_Util::err( 'Kỳ "' . $ky_moi . '" không đọc ra được khoảng ngày — '
				. 'chọn tuần trong danh sách, đừng gõ tay.' );
		}
		if ( $ky_moi === $ky_cu ) {
			return VHCP_Util::err( 'Đơn này đang ở kỳ ' . $ky_cu . ' rồi.' );
		}

		$nguoi = trim( (string) $nguoi );
		if ( '' === $nguoi ) { $nguoi = VHCP_Auth::nguoi(); }

		self::upd_don( $ma_don, array( 'ky' => $ky_moi ) );

		$ly_do = trim( (string) $ly_do );
		self::ghi_vet( $ma_don, 'Nhảy đơn sang tuần khác',
			$ky_cu . '  →  ' . $ky_moi . ( '' !== $ly_do ? ' — ' . $ly_do : '' ) );

		return VHCP_Util::ok( array(
			'maDon' => (string) $ma_don,
			'kyCu'  => $ky_cu,
			'kyMoi' => $ky_moi,
			'tu'    => $tu_m,
			'den'   => $den_m,
		) );
	}

	/** Các dòng chi của một đơn (đọc thô, không qua lớp bày biện). */
	private static function rows_of_don( $ma_don ) {
		global $wpdb;
		$t = VHCP_DB::t( 'chiphi' );
		return VHCP_DB::rows( $wpdb->prepare( "SELECT * FROM $t WHERE ma_don=%s ORDER BY stt ASC", (string) $ma_don ) );
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

	/**
	 * HẠ TẠM ỨNG VỀ 0 CHO ĐƠN ĐÃ ĐÁNH DẤU CẤP TIỀN NHƯNG TIỀN CHƯA TỪNG RA KHỎI KÉT.
	 *
	 * 🔴 Anh Thắng 01/09/2026, đơn VR TÂN AN: *"Khi 1 cửa hàng không xin tạm ứng, tạm ứng = 0.
	 *    Nhân viên sau đó mua đồ và quyết toán, thì hệ thống ghi nhận cả tạm ứng và thực chi =
	 *    nhau luôn"* — rồi *"vẫn còn"*, *"đơn này là đơn đã cấp"*.
	 *
	 *    Bản 1.59.0 chữa được ca chưa ai chốt số / chốt là 0đ. Đơn này thì khác: sổ ghi hẳn
	 *    `tam_ung_duyet = 73.000`, có người duyệt và có người bấm cấp tiền. Con số ấy nằm cứng
	 *    trong sổ, không phép tính nào gỡ ra được.
	 *
	 *    Đường sẵn có không tới nơi: `duyet_lai_tam_ung()` chỉ nhận đơn còn ở "Chờ cấp tạm ứng";
	 *    nút "🚫 Không dùng — hoàn tạm ứng" thì chỉ gắn nhãn `[Không dùng]` và đẩy sang Chờ
	 *    quyết toán, KHÔNG hạ con số — nên khối đối chiếu vẫn so với 73.000 và báo THỪA, tức
	 *    đòi nhân viên trả lại một khoản họ chưa từng nhận.
	 *
	 * 🔴 HAI CA KHÁC HẲN NHAU, ĐỪNG GỘP:
	 *      · Tiền ĐÃ ra khỏi két rồi mới thôi dùng  → "Không dùng — hoàn tạm ứng": giữ con số,
	 *        đối chiếu báo THỪA, nhân viên trả lại. Sổ quỹ khớp với lượt chi đã ghi.
	 *      · Tiền CHƯA từng ra khỏi két (lượt cấp là bấm nhầm) → hàm này: hạ về 0, đối chiếu
	 *        báo THIẾU đúng số nhân viên đã tự ứng ra, kế toán trả lại họ.
	 *    Chọn nhầm ca là sổ quỹ nói một đằng đơn nói một nẻo — nên hàm này không tự chạy bao
	 *    giờ, và người bấm phải nêu lý do.
	 *
	 * ⚠️ CHỈ ADMIN (chốt ở `$admin_only` bên API — anh Thắng chọn 01/09/2026), và chỉ khi đơn
	 *    CHƯA chốt sổ. Đơn đã quyết toán / đã xuất MISA thì con số đã đi vào báo cáo và vào
	 *    MISA; sửa ở đây là để lại một chỗ lệch không ai dò ra.
	 */
	public static function ha_tam_ung_ve_0( $ma_don, $ly_do = '' ) {
		$d = self::don_row( $ma_don );
		if ( ! $d ) { return VHCP_Util::err( 'Không tìm thấy đơn' ); }
		$st = (string) $d['trang_thai'];
		if ( ! in_array( $st, array( 'Đã cấp tạm ứng', 'Chờ quyết toán' ), true ) ) {
			return VHCP_Util::err( 'Chỉ hạ được tạm ứng của đơn ĐÃ CẤP mà chưa chốt sổ. Đơn này đang ở "'
				. $st . '"'
				. ( 'Chờ cấp tạm ứng' === $st ? ' — chưa cấp tiền thì bấm "Duyệt lại theo số mới" và duyệt 0đ.'
					: ( self::da_chot( $st ) ? ' — đã chốt sổ, con số đã đi vào báo cáo và MISA.' : '.' ) ) );
		}
		$cu = VHCP_Util::num( $d['tam_ung_duyet'] );
		if ( 0.0 === (float) $cu && null === VHCP_Util::blank_or_num( $d['tam_ung_duyet'] ) ) {
			/* Chưa ai chốt số: hạ về 0 vẫn có nghĩa (0 khác "chưa chốt"), nên cứ chạy tiếp. */
			$cu = 0.0;
		} elseif ( 0.0 === (float) $cu ) {
			return VHCP_Util::err( 'Tạm ứng của đơn này đang là 0 rồi — không có gì để hạ.' );
		}

		$ly_do = trim( (string) $ly_do );
		if ( '' === $ly_do ) {
			return VHCP_Util::err( 'Phải nêu lý do — đây là lượt sửa tiền trên đơn đã đánh dấu cấp.' );
		}

		self::upd_don( $ma_don, array( 'tam_ung_duyet' => 0 ) );
		self::ghi_vet( $ma_don, 'Hạ tạm ứng về 0 (tiền chưa ra khỏi két)',
			VHCP_Util::tien( $cu ) . '  →  0đ · ' . $ly_do );
		self::bao_noi_bo( $ma_don, 'được hạ tạm ứng về 0đ (tiền chưa ra khỏi két) — ' . $ly_do );
		return VHCP_Util::ok( array( 'cu' => $cu ) );
	}

	/**
	 * ADMIN SỬA LẠI SỐ TẠM ỨNG KHI ĐƠN ĐÃ CẤP (anh Thắng 01/09/2026). Tổng quát hơn `ha_tam_ung_ve_0`
	 * (vốn chỉ hạ về 0): đặt tạm ứng về ĐÚNG con số admin gõ — dùng khi duyệt/cấp nhầm số.
	 *
	 * ⚠️ CHỈ ADMIN (gác ở `$admin_only` bên API) · chỉ khi đơn ĐÃ CẤP mà CHƯA chốt sổ (đã quyết
	 *    toán / xuất MISA thì số đã vào báo cáo + MISA, sửa đây để lại chỗ lệch không ai dò ra) ·
	 *    BẮT nêu lý do, và ghi vết cũ→mới + báo nội bộ, vì đây là sửa TIỀN trên đơn đã đánh dấu cấp.
	 */
	public static function sua_tam_ung_da_cap( $ma_don, $so_moi = '', $ly_do = '' ) {
		$d = self::don_row( $ma_don );
		if ( ! $d ) { return VHCP_Util::err( 'Không tìm thấy đơn' ); }
		$st = (string) $d['trang_thai'];
		if ( ! in_array( $st, array( 'Đã cấp tạm ứng', 'Chờ quyết toán' ), true ) ) {
			return VHCP_Util::err( 'Chỉ sửa được tạm ứng của đơn ĐÃ CẤP mà chưa chốt sổ. Đơn này đang ở "' . $st . '"'
				. ( 'Chờ cấp tạm ứng' === $st ? ' — chưa cấp thì bấm "Duyệt lại theo số mới".'
					: ( self::da_chot( $st ) ? ' — đã chốt sổ, số đã vào báo cáo và MISA.' : '.' ) ) );
		}
		$moi = VHCP_Util::blank_or_num( $so_moi );
		if ( null === $moi || $moi < 0 ) { return VHCP_Util::err( 'Số tạm ứng mới không hợp lệ.' ); }
		$ly_do = trim( (string) $ly_do );
		if ( '' === $ly_do ) { return VHCP_Util::err( 'Phải nêu lý do — đây là lượt sửa tiền trên đơn đã đánh dấu cấp.' ); }
		$cu = VHCP_Util::num( $d['tam_ung_duyet'] );
		if ( (float) $cu === (float) $moi ) { return VHCP_Util::err( 'Số mới trùng số cũ — không có gì thay đổi.' ); }
		self::upd_don( $ma_don, array( 'tam_ung_duyet' => $moi ) );
		self::ghi_vet( $ma_don, 'Admin sửa số tạm ứng (đơn đã cấp)',
			VHCP_Util::tien( $cu ) . '  →  ' . VHCP_Util::tien( $moi ) . 'đ · ' . $ly_do );
		self::bao_noi_bo( $ma_don, 'được Admin sửa tạm ứng ' . VHCP_Util::tien( $cu ) . 'đ → ' . VHCP_Util::tien( $moi ) . 'đ — ' . $ly_do );
		return VHCP_Util::ok( array( 'cu' => $cu, 'moi' => $moi ) );
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
