<?php
/**
 * GOM KHO ĐƠN CỦA CÁC BẢN — ĐỌC THẲNG, KHÔNG CHÉP.
 *
 * ══════════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 DANH SÁCH LẤY BẰNG SQL, SỐ TIỀN HỎI CHÍNH BẢN ẤY. Hai việc khác nhau, cố ý làm hai đường:
 *
 *    · Danh sách: một câu `SELECT` trên bảng `*_don` của từng bản. Lọc theo trạng thái ngay
 *      trong câu lệnh, nên trang tổng không kéo về 4.000 đơn rồi bỏ đi 3.990.
 *
 *    · Số tiền: gọi `<Bản>_Don::tong_xin_hien_tai()`. Luật gom hạng mục có chỗ tinh — `Nháp`
 *      thì gộp cả dòng phát sinh, sau đó thì không; có hàng tạm ứng tay thì lấy hàng ấy. Chép
 *      luật ấy sang đây là dựng bản thứ hai cho cùng một câu hỏi, rồi hai bản lệch nhau và
 *      người duyệt thấy một con số, người lập đơn thấy con số khác.
 *
 * 🔴 TRẠNG THÁI LÀ CHUỖI TIẾNG VIỆT CÓ DẤU, và nó là giao kèo giữa bốn plugin. Đổi một chữ ở
 *    một bản là đơn của bản ấy biến mất khỏi trang tổng — không câu lỗi nào, chỉ là bảng ngắn
 *    đi. `kiem-trang-tong.php` canh đúng chỗ đó: mọi bản phải còn dùng đúng những chuỗi này.
 * ══════════════════════════════════════════════════════════════════════════════════════════════
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCPT_Gom {

	/** Trạng thái mà trang tổng quan tâm — đơn đang CHỜ một quyết định. */
	const CHO_DUYET = 'Chờ duyệt tạm ứng';
	const CHO_CAP   = 'Chờ cấp tạm ứng';
	const CHO_QT    = 'Chờ quyết toán';
	/* Đã xong phần quyết toán, còn chờ xuất MISA — xem `nhom()`. */
	const DA_QT     = 'Đã quyết toán';
	/* NHÁP — nhân viên đang soạn, CHƯA gửi lên. Anh Thắng 14/09/2026: *"chỗ chờ duyệt hiện các
	   đơn nháp nhân viên đang lên chờ mà chưa gửi"*: quản lý muốn thấy cái sắp tới, không chỉ
	   cái đã tới. Nhưng nó KHÔNG phải việc của quản lý — không đếm vào ô tròn, xem `nhom()`. */
	const NHAP      = 'Nháp';
	const DA_CAP    = 'Đã cấp tạm ứng';
	const DA_MISA   = 'Đã xuất MISA';

	/* ══════════════════════════════════════════════════════════════════════════════════════════
	 * CÁC BƯỚC MỘT ĐƠN ĐI QUA — dùng cho màn Tổng quan.
	 * ══════════════════════════════════════════════════════════════════════════════════════════
	 * Anh Thắng 14/09/2026: *"chỗ này cũng tách ra các bảng: đã tạm ứng, chưa tạm ứng, chờ tạm
	 * ứng — tức các bước để kế toán theo dõi"*.
	 *
	 * 🔴 THỨ TỰ NÀY LÀ THỨ TỰ THẬT CỦA QUY TRÌNH, không phải bảng chữ cái. Bày lộn thứ tự là
	 *    người đọc mất luôn cảm giác "đơn đang đi tới đâu" — thứ duy nhất cái màn này để làm.
	 * ══════════════════════════════════════════════════════════════════════════════════════════ */
	const BUOC = array(
		array( 'tt' => 'Nháp',              'ten' => 'Nháp — chưa gửi',        'mau' => '#64748b' ),
		array( 'tt' => 'Chờ duyệt tạm ứng', 'ten' => 'Chờ duyệt tạm ứng',      'mau' => '#b45309' ),
		array( 'tt' => 'Chờ cấp tạm ứng',   'ten' => 'Chờ cấp tạm ứng',        'mau' => '#b45309' ),
		array( 'tt' => 'Đã cấp tạm ứng',    'ten' => 'Đã cấp tạm ứng',         'mau' => '#0369a1' ),
		array( 'tt' => 'Chờ quyết toán',    'ten' => 'Chờ quyết toán',         'mau' => '#b45309' ),
		array( 'tt' => 'Đã quyết toán',     'ten' => 'Đã quyết toán',          'mau' => '#166534' ),
		array( 'tt' => 'Đã xuất MISA',      'ten' => 'Đã xuất MISA',           'mau' => '#166534' ),
	);

	/** Số đơn tối đa đọc về một lượt. */
	const GIOI_HAN = 200;

	/**
	 * Các nhóm trạng thái bày thành tab trên màn.
	 *
	 * ══════════════════════════════════════════════════════════════════════════════════════════
	 * 🔴 `tt` LÀ THỨ ĐỌC VỀ, `demTt` LÀ THỨ ĐẾM LÊN Ô TRÒN — hai câu hỏi khác nhau.
	 * ══════════════════════════════════════════════════════════════════════════════════════════
	 * Anh Thắng 14/09/2026: *"Tách 2 bảng, đã quyết toán và chưa quyết toán, mỗi mảng 2 bảng như
	 * vậy"*. Nên tab Quyết toán nay đọc về CẢ đơn đã quyết toán, để bày bảng thứ hai.
	 *
	 * Nhưng con số trên tab phải vẫn là VIỆC CÒN PHẢI LÀM. Đếm cả đơn đã xong là ô tròn không
	 * bao giờ về 0 — mà một con số không bao giờ về 0 thì người ta thôi nhìn nó, và hôm có việc
	 * thật cũng không ai để ý. Đó là cách hỏng một cái đồng hồ báo.
	 *
	 * ⚠️ BẢNG "ĐÃ QUYẾT TOÁN" DỪNG Ở TRƯỚC MISA. Không gom `Đã xuất MISA`: những đơn ấy xong
	 *    hẳn rồi, gom vào thì bảng phình theo từng tháng cho tới khi không mở nổi — trong khi
	 *    thứ kế toán cần thấy ở đây là phần việc CÒN LẠI của mình.
	 */
	public static function nhom() {
		return array(
			/* Nháp bày kèm để quản lý thấy cái SẮP tới, nhưng không đếm: đơn nhân viên chưa
			   gửi thì chưa phải việc của ai cả, đếm vào là ô tròn nói sai số việc phải làm. */
			'duyet' => array(
				'ten'   => 'Chờ duyệt tạm ứng',
				'tt'    => array( self::CHO_DUYET, self::NHAP ),
				'demTt' => array( self::CHO_DUYET ),
			),
			'cap'   => array( 'ten' => 'Chờ cấp tạm ứng',   'tt' => array( self::CHO_CAP ) ),
			'qt'    => array(
				'ten'   => 'Quyết toán',
				'tt'    => array( self::CHO_QT, self::DA_QT ),
				'demTt' => array( self::CHO_QT ),
			),
		);
	}

	/**
	 * Đơn của MỘT bản theo danh sách trạng thái.
	 *
	 * ⚠️ `$wpdb->prepare` không nhận được tên bảng, nên tên bảng phải dựng từ `VHCPT_Ban` chứ
	 *    KHÔNG bao giờ từ thứ người dùng gửi lên. `tien_to_bang()` chỉ trả tiền tố cho bản có
	 *    thật trong sổ; bản không có trả chuỗi rỗng và hàm này dừng ngay.
	 */
	public static function don_cua_ban( $khoa, $ds_tt ) {
		global $wpdb;
		$tien_to = VHCPT_Ban::tien_to_bang( $khoa );
		if ( '' === $tien_to ) { return array(); }
		$ds_tt = array_values( array_filter( array_map( 'strval', (array) $ds_tt ) ) );
		if ( ! $ds_tt ) { return array(); }
		$bang = $tien_to . 'don';
		$cho  = implode( ',', array_fill( 0, count( $ds_tt ), '%s' ) );
		$sql  = $wpdb->prepare(
			"SELECT ma_don, ky, nguoi_lap, don_vi, ngay_tao, trang_thai, tam_ung_duyet
			   FROM $bang
			  WHERE trang_thai IN ($cho)
			  ORDER BY ngay_tao DESC
			  LIMIT %d",
			array_merge( $ds_tt, array( self::GIOI_HAN ) )
		);
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		if ( ! is_array( $rows ) ) { return array(); }

		/* ══════════════════════════════════════════════════════════════════════════════════════
		 * LOẠI CHI PHÍ CỦA TỪNG ĐƠN — anh Thắng 14/09/2026: *"Thể hiện loại chi phí luôn nhé"*.
		 * ══════════════════════════════════════════════════════════════════════════════════════
		 * 🔴 MỘT ĐƠN CÓ NHIỀU LOẠI. Loại chi phí nằm ở từng DÒNG CHI, không nằm ở đơn — một đơn
		 *    tuần có thể vừa "NVL đồ uống" vừa "Chi phí cơ sở". Nên cột này là DANH SÁCH, và khi
		 *    dài thì cắt bớt kèm số còn lại; rút gọn thành một loại là nói sai về đơn.
		 *
		 * ⚠️ MỘT CÂU HỎI CHO CẢ LÁT CẮT, KHÔNG HỎI TỪNG ĐƠN. Hỏi từng đơn là 200 lượt đọc cho
		 *    một màn — cùng cái bẫy đã mắc ở `dem()` (xem chú thích ở đó).
		 * ══════════════════════════════════════════════════════════════════════════════════════ */
		$loai_cua = array();
		$coso_cua = array();
		$ma_ds = array();
		foreach ( $rows as $r0 ) {
			$m0 = trim( (string) $r0['ma_don'] );
			if ( '' !== $m0 ) { $ma_ds[] = $m0; }
		}
		if ( $ma_ds ) {
			$cho_ma = implode( ',', array_fill( 0, count( $ma_ds ), '%s' ) );
			$t_cp   = $tien_to . 'chiphi';
			$r_loai = $wpdb->get_results( $wpdb->prepare(
				"SELECT ma_don, nhom, COUNT(*) AS so, SUM(thanh_tien) AS tien
				   FROM $t_cp WHERE ma_don IN ($cho_ma) AND nhom <> ''
			   GROUP BY ma_don, nhom ORDER BY tien DESC",
				$ma_ds
			), ARRAY_A );
			/* ══════════════════════════════════════════════════════════════════════════════════
			 * CƠ SỞ CỦA TỪNG ĐƠN — anh Thắng 14/09/2026: *"thêm cột cơ sở, thay cột mã đơn bằng
			 * cột cơ sở"*.
			 *
			 * 🔴 HỎI CẢ HAI BẢNG. Cơ sở nằm ở DÒNG CHI, nhưng đơn xin ứng trước chưa có dòng chi
			 *    nào mà vẫn thuộc một gian — gian ấy ghi ở dòng TẠM ỨNG. Chỉ hỏi bảng chi phí là
			 *    mọi đơn ứng trước hiện ra "—", đúng những đơn đang chờ duyệt nhiều nhất.
			 *
			 * ⚠️ MỘT CÂU CHO CẢ LÁT CẮT, không hỏi từng đơn — cùng cái bẫy đã tránh ở loại chi phí
			 *    ngay trên: hỏi từng đơn là 200 lượt đọc cho một màn.
			 * ══════════════════════════════════════════════════════════════════════════════════ */
			$t_tu    = $tien_to . 'tamung';
			$r_coso  = $wpdb->get_results( $wpdb->prepare(
				"SELECT ma_don, coso FROM $t_cp WHERE ma_don IN ($cho_ma) AND coso <> ''
				 UNION
				 SELECT ma_don, coso FROM $t_tu WHERE ma_don IN ($cho_ma) AND coso <> ''",
				array_merge( $ma_ds, $ma_ds )
			), ARRAY_A );
			foreach ( (array) $r_coso as $r2 ) {
				$m2 = (string) $r2['ma_don'];
				$c2 = trim( (string) $r2['coso'] );
				if ( '' === $c2 ) { continue; }
				if ( ! isset( $coso_cua[ $m2 ] ) ) { $coso_cua[ $m2 ] = array(); }
				if ( ! in_array( $c2, $coso_cua[ $m2 ], true ) ) { $coso_cua[ $m2 ][] = $c2; }
			}

			foreach ( (array) $r_loai as $r1 ) {
				$m1 = (string) $r1['ma_don'];
				if ( ! isset( $loai_cua[ $m1 ] ) ) { $loai_cua[ $m1 ] = array(); }
				$loai_cua[ $m1 ][] = array(
					'ten'  => (string) $r1['nhom'],
					'so'   => (int) $r1['so'],
					'tien' => (float) $r1['tien'],
				);
			}
		}

		/* ══════════════════════════════════════════════════════════════════════════════════════
		 * SỐ TIỀN: HỎI `tong_de_duyet()` TRƯỚC, LUI VỀ `tong_xin_hien_tai()`.
		 * ══════════════════════════════════════════════════════════════════════════════════════
		 * Anh Thắng 14/09/2026: *"nếu không nhập tạm ứng thì hiểu là đơn thường nhiều cơ sở thì
		 * số tiền sẽ lấy theo số thực tế trên đơn"* — kèm ảnh một đơn ba triệu mà cột SỐ XIN ghi
		 * 0đ, vì `tong_xin_hien_tai()` chỉ cộng tạm ứng, mà đơn ấy không xin ứng đồng nào.
		 *
		 * `tong_de_duyet()` trả lời đúng câu người duyệt cần: *"bấm duyệt cái này là duyệt bao
		 * nhiêu"* — số xin nếu có tạm ứng, số thực tế trên đơn nếu không.
		 *
		 * ⚠️ LUI VỀ HÀM CŨ CHỨ KHÔNG BỎ TRỐNG. Bốn plugin nâng cấp lệch nhau; bản mảng chưa lên
		 *    bản có `tong_de_duyet()` vẫn phải ra một con số, chứ không phải một dấu gạch.
		 * ══════════════════════════════════════════════════════════════════════════════════════ */
		$lop_don = VHCPT_Ban::lop( $khoa, 'Don' );
		/* ⚠️ Gác CÙNG HÀM với lời gọi — luật `tools/test/kiem-goi-cheo.php`. */
		$ham_tien = '';
		if ( $lop_don && class_exists( $lop_don ) ) {
			if ( method_exists( $lop_don, 'tong_de_duyet' ) )           { $ham_tien = 'tong_de_duyet'; }
			elseif ( method_exists( $lop_don, 'tong_xin_hien_tai' ) )   { $ham_tien = 'tong_xin_hien_tai'; }
		}

		$ra = array();
		foreach ( $rows as $r ) {
			$ma = trim( (string) $r['ma_don'] );
			if ( '' === $ma ) { continue; }
			$tien = null;
			if ( '' !== $ham_tien ) {
				$tien = call_user_func( array( $lop_don, $ham_tien ), $ma );
			}
			$ra[] = array(
				'ban'      => $khoa,
				'tenBan'   => VHCPT_Ban::ten( $khoa ),
				'urlBan'   => (string) ( isset( VHCPT_Ban::ds()[ $khoa ]['url'] ) ? VHCPT_Ban::ds()[ $khoa ]['url'] : '' ),
				'maDon'    => $ma,
				/* Danh sách gian của đơn — màn bày một tên, nhiều gian thì nói "N cơ sở". */
				'coso'     => isset( $coso_cua[ $ma ] ) ? $coso_cua[ $ma ] : array(),
				'ky'       => (string) $r['ky'],
				'nguoiLap' => (string) $r['nguoi_lap'],
				'donVi'    => (string) $r['don_vi'],
				'ngayTao'  => (string) $r['ngay_tao'],
				'trangThai'=> (string) $r['trang_thai'],
				/* 🔴 `null` KHÁC 0. null = không hỏi được số (bản quá cũ, thiếu hàm); 0 = đơn
				   thật sự chưa xin đồng nào. Bày cả hai thành "0đ" là người duyệt bấm duyệt một
				   đơn mà không biết mình đang duyệt bao nhiêu. */
				'tien'     => ( null === $tien ) ? null : (float) $tien,
				/* 🔴 KÈM THEO TỪNG DÒNG "LÀM ĐƯỢC VIỆC GÌ", không chỉ một cờ duyệt. Cùng một người
				   có thể duyệt được ở mảng này mà chỉ cấp tiền được ở mảng kia — bảng gộp ba
				   mảng thì mỗi dòng một câu trả lời khác nhau. Một cờ chung là vẽ ra nút họ bấm
				   vào sẽ bị chối, hoặc giấu mất nút họ có quyền bấm. */
				'lam'      => self::lam_duoc( $khoa ),
				/* Xếp theo tiền giảm dần (đã sắp trong câu lệnh) — loại tốn nhiều nhất đứng
				   trước, vì đó là loại người duyệt cần nhìn đầu tiên. */
				'loai'     => isset( $loai_cua[ $ma ] ) ? $loai_cua[ $ma ] : array(),
			);
		}
		return $ra;
	}

	/**
	 * Thực chi của một dòng — hỏi lõi bản ấy, xem chú thích ở `dong_chi()`.
	 *
	 * ⚠️ Bản mảng đời cũ thiếu `thuc_chi()` thì trả null, và màn bày dấu gạch. Đoán hộ bằng
	 *    thành tiền là nói "đã chi" cho một đơn có thể chưa ai đưa đồng nào.
	 */
	public static function thuc_chi_dong( $khoa, $r, $tt_don ) {
		$lop = VHCPT_Ban::lop( $khoa, 'Don' );
		/* ⚠️ Gác CÙNG HÀM với lời gọi — luật `tools/test/kiem-goi-cheo.php`. */
		if ( ! $lop || ! class_exists( $lop ) || ! method_exists( $lop, 'thuc_chi' ) ) { return null; }
		return (float) call_user_func( array( $lop, 'thuc_chi' ),
			isset( $r['thanh_tien'] ) ? $r['thanh_tien'] : 0,
			isset( $r['thuc_mua'] ) ? $r['thuc_mua'] : null,
			(string) $tt_don );
	}

	/* ══════════════════════════════════════════════════════════════════════════════════════════
	 * BẢNG TỔNG QUAN — MỌI ĐƠN TỪ TRƯỚC TỚI GIỜ, CÓ BỘ LỌC
	 * ══════════════════════════════════════════════════════════════════════════════════════════
	 * Anh Thắng 14/09/2026: *"thêm giúp anh 1 cái tab đầu tiên (Dashboard) hiện tất cả đơn từ
	 * trước đến giờ, kèm các bộ lọc: lọc theo cơ sở, lọc theo mảng, lọc theo ngày, lọc theo chi
	 * phí, lọc theo người nhập"*.
	 *
	 * 🔴 KHÁC HẲN BA TAB KIA: chúng hỏi *"đơn nào đang chờ TÔI quyết định"* — một lát cắt hẹp,
	 *    lọc theo trạng thái. Tab này hỏi *"đơn nào đã từng có"* — không lọc trạng thái gì cả,
	 *    nên nó có thể chạm vào vài nghìn đơn.
	 *
	 * 🔴 LỌC TRONG SQL, KHÔNG LỌC SAU KHI ĐÃ LẤY. Có `LIMIT`: lấy 300 dòng rồi mới bỏ đơn không
	 *    khớp là người ta lọc "cơ sở Aeon Tân Phú" và nhận về 4 đơn, trong khi cơ sở ấy có 60 —
	 *    56 chỗ kia đã bị đơn của cơ sở khác chiếm mất. Đây đúng cái bẫy `VHCP_DonVi::dieu_kien_sql()`
	 *    đã ghi lại cho màn tìm đơn.
	 *
	 * ⚠️ CƠ SỞ VÀ LOẠI CHI PHÍ NẰM Ở DÒNG CHI, KHÔNG NẰM Ở ĐƠN. Nên hai bộ lọc ấy phải đi qua
	 *    câu con trên bảng chi phí — và với cơ sở thì CẢ bảng tạm ứng nữa, vì đơn xin ứng trước
	 *    chưa có dòng chi nào mà vẫn thuộc một gian.
	 * ══════════════════════════════════════════════════════════════════════════════════════════ */

	/** Trần số đơn đọc về MỖI BẢN cho màn tổng quan. */
	const GIOI_HAN_TQ = 300;

	public static function tat_ca_don( $khoa, $loc ) {
		global $wpdb;
		$tien_to = VHCPT_Ban::tien_to_bang( $khoa );
		if ( '' === $tien_to ) { return array( 'rows' => array(), 'tong' => 0 ); }
		$bang = $tien_to . 'don';
		$b_cp = $tien_to . 'chiphi';
		$b_tu = $tien_to . 'tamung';

		$loc = (array) $loc;
		$g   = function ( $k ) use ( $loc ) {
			$v = isset( $loc[ $k ] ) ? trim( (string) $loc[ $k ] ) : '';
			return ( '' === $v || 'all' === $v ) ? '' : $v;
		};
		$dk = array( '1=1' ); $tv = array();

		$cs = $g( 'coso' );
		if ( '' !== $cs ) {
			/* Cơ sở của đơn = cơ sở ở DÒNG CHI, hoặc ở dòng TẠM ỨNG với đơn xin ứng trước. */
			$dk[] = "( ma_don IN ( SELECT ma_don FROM $b_cp WHERE coso = %s )"
				. " OR ma_don IN ( SELECT ma_don FROM $b_tu WHERE coso = %s ) )";
			$tv[] = $cs; $tv[] = $cs;
		}
		$lo = $g( 'loai' );
		if ( '' !== $lo ) {
			$dk[] = "ma_don IN ( SELECT ma_don FROM $b_cp WHERE nhom = %s )";
			$tv[] = $lo;
		}
		$ng = $g( 'nguoi' );
		if ( '' !== $ng ) {
			/* Tìm THEO MẢNH TÊN: sổ người dùng gõ tay nên "Thảo" phải ra "Huỳnh Thị Thu Thảo".
			   Bắt khớp cả tên là người ta phải gõ đủ họ tên có dấu, và thường là gõ sai. */
			$dk[] = 'nguoi_lap LIKE %s';
			$tv[] = '%' . $wpdb->esc_like( $ng ) . '%';
		}
		$tt = $g( 'trangThai' );
		if ( '' !== $tt ) { $dk[] = 'trang_thai = %s'; $tv[] = $tt; }

		/* NGÀY: chặn hai đầu độc lập — người ta hay chỉ điền một đầu ("từ đầu tháng tới giờ"). */
		$tu_ngay = $g( 'tuNgay' );
		if ( '' !== $tu_ngay && preg_match( '#^\d{4}-\d{2}-\d{2}$#', $tu_ngay ) ) {
			$dk[] = 'ngay_tao >= %s'; $tv[] = $tu_ngay . ' 00:00:00';
		}
		$den_ngay = $g( 'denNgay' );
		if ( '' !== $den_ngay && preg_match( '#^\d{4}-\d{2}-\d{2}$#', $den_ngay ) ) {
			/* ⚠️ TỚI HẾT NGÀY ẤY, không phải tới 00:00. Cắt ở nửa đêm là mất sạch đơn lập
			   trong chính ngày người ta vừa chọn — và họ sẽ tưởng hôm ấy không ai lập đơn. */
			$dk[] = 'ngay_tao <= %s'; $tv[] = $den_ngay . ' 23:59:59';
		}
		$where = implode( ' AND ', $dk );

		/* Đếm TRƯỚC, trên cả lát cắt — để nói được "đang xem 300 trong 812 đơn". */
		$sql_dem = "SELECT COUNT(*) FROM $bang WHERE $where";
		$tong    = (int) ( $tv ? $wpdb->get_var( $wpdb->prepare( $sql_dem, $tv ) ) : $wpdb->get_var( $sql_dem ) );

		$sql = "SELECT ma_don, ky, nguoi_lap, don_vi, ngay_tao, trang_thai, tam_ung_duyet
		          FROM $bang WHERE $where ORDER BY ngay_tao DESC LIMIT %d";
		$tv2  = array_merge( $tv, array( self::GIOI_HAN_TQ ) );
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $tv2 ), ARRAY_A );
		if ( ! is_array( $rows ) ) { $rows = array(); }

		$ra = array();
		foreach ( $rows as $r ) {
			$ma = trim( (string) $r['ma_don'] );
			if ( '' === $ma ) { continue; }
			$ra[] = array(
				'ban'       => $khoa,
				'tenBan'    => VHCPT_Ban::ten( $khoa ),
				'maDon'     => $ma,
				'ky'        => (string) $r['ky'],
				'nguoiLap'  => (string) $r['nguoi_lap'],
				'ngayTao'   => (string) $r['ngay_tao'],
				'trangThai' => (string) $r['trang_thai'],
				'tien'      => self::tien_cua_don( $khoa, $ma ),
			);
		}
		return array( 'rows' => $ra, 'tong' => $tong );
	}

	/** Số tiền của một đơn — hỏi lõi bản ấy, xem khối ở `don_cua_ban()`. */
	public static function tien_cua_don( $khoa, $ma ) {
		$lop = VHCPT_Ban::lop( $khoa, 'Don' );
		/* ⚠️ Gác CÙNG HÀM với lời gọi — luật `tools/test/kiem-goi-cheo.php`. */
		if ( ! $lop || ! class_exists( $lop ) ) { return null; }
		if ( method_exists( $lop, 'tong_de_duyet' ) )     { return call_user_func( array( $lop, 'tong_de_duyet' ), $ma ); }
		if ( method_exists( $lop, 'tong_xin_hien_tai' ) ) { return call_user_func( array( $lop, 'tong_xin_hien_tai' ), $ma ); }
		return null;
	}

	/** Danh mục cho ba hộp chọn của màn tổng quan — gom từ chính sổ của bản. */
	public static function danh_muc( $khoa ) {
		global $wpdb;
		$tien_to = VHCPT_Ban::tien_to_bang( $khoa );
		if ( '' === $tien_to ) { return array( 'coso' => array(), 'loai' => array(), 'nguoi' => array() ); }
		$b_cp = $tien_to . 'chiphi';
		$b_dn = $tien_to . 'don';
		$doc  = function ( $sql ) use ( $wpdb ) {
			$v = $wpdb->get_col( $sql );
			$ra = array();
			foreach ( (array) $v as $x ) {
				$x = trim( (string) $x );
				if ( '' !== $x && ! in_array( $x, $ra, true ) ) { $ra[] = $x; }
			}
			sort( $ra );
			return $ra;
		};
		return array(
			'coso'  => $doc( "SELECT DISTINCT coso FROM $b_cp WHERE coso <> '' LIMIT 400" ),
			'loai'  => $doc( "SELECT DISTINCT nhom FROM $b_cp WHERE nhom <> '' LIMIT 400" ),
			'nguoi' => $doc( "SELECT DISTINCT nguoi_lap FROM $b_dn WHERE nguoi_lap <> '' LIMIT 400" ),
		);
	}

	/* ══════════════════════════════════════════════════════════════════════════════════════════
	 * CHUÔNG — VIỆC MỚI TỚI TỪ LẦN XEM TRƯỚC
	 * ══════════════════════════════════════════════════════════════════════════════════════════
	 * Anh Thắng 14/09/2026: *"trang tổng này thêm 1 cái chuông thông báo khi có đơn mới cho kế
	 * toán biết: đơn mới, đơn gửi cấp, duyệt"*.
	 *
	 * 🔴 "MỚI" LÀ MỚI Ở BƯỚC NÀO, KHÔNG PHẢI ĐƠN MỚI LẬP. Một đơn lập từ tuần trước, hôm nay
	 *    quản lý vừa duyệt xong, thì với KẾ TOÁN nó là việc mới toanh — dù `ngay_tao` đã cũ.
	 *    Đo bằng `ngay_tao` cho cả ba loại là chuông im đúng lúc cần kêu nhất. Nên mỗi loại đo
	 *    bằng MỐC CỦA CHÍNH BƯỚC ẤY:
	 *      · đơn vừa gửi lên   -> `ngay_tao`
	 *      · vừa duyệt, chờ cấp -> `ngay_duyet`
	 *      · vừa gửi quyết toán -> `ngay_gui_qt` (mốc NHÂN VIÊN BẤM GỬI, khác `ngay_qt` là mốc
	 *        kế toán chốt — xem `VHCP_DB` 1.10.0)
	 *
	 * ⚠️ CHỈ ĐẾM VIỆC NGƯỜI NÀY LÀM ĐƯỢC. Chuông kêu cho một việc mình không có quyền bấm là
	 *    tiếng kêu vô nghĩa, và vài lần như thế là người ta thôi nhìn chuông.
	 * ══════════════════════════════════════════════════════════════════════════════════════════ */

	/** Trần số mục chuông đọc về mỗi bản. */
	const GIOI_HAN_CHUONG = 50;

	public static function chuong_cua_ban( $khoa, $tu ) {
		global $wpdb;
		$tien_to = VHCPT_Ban::tien_to_bang( $khoa );
		if ( '' === $tien_to ) { return array(); }
		$bang = $tien_to . 'don';
		$ten  = VHCPT_Ban::ten( $khoa );

		$loai = array(
			array( 'viec' => 'duyet', 'tt' => self::CHO_DUYET, 'cot' => 'ngay_tao',
			       'nhan' => 'đơn mới gửi duyệt' ),
			array( 'viec' => 'cap',   'tt' => self::CHO_CAP,   'cot' => 'ngay_duyet',
			       'nhan' => 'đã duyệt — chờ cấp tiền' ),
			array( 'viec' => 'qtCn',  'tt' => self::CHO_QT,    'cot' => 'ngay_gui_qt',
			       'nhan' => 'đã gửi quyết toán' ),
		);

		$ra = array();
		foreach ( $loai as $l ) {
			/* ⚠️ Việc mình không làm được thì không kêu — xem khối trên. */
			if ( ! VHCPT_Auth::duoc( $khoa, $l['viec'] ) ) { continue; }
			$sql = $wpdb->prepare(
				"SELECT ma_don, ky, nguoi_lap, {$l['cot']} AS luc
				   FROM $bang
				  WHERE trang_thai = %s AND {$l['cot']} IS NOT NULL AND {$l['cot']} > %s
				  ORDER BY {$l['cot']} DESC LIMIT %d",
				$l['tt'], (string) $tu, self::GIOI_HAN_CHUONG
			);
			foreach ( (array) $wpdb->get_results( $sql, ARRAY_A ) as $r ) {
				$ra[] = array(
					'ban'    => $khoa,
					'tenBan' => $ten,
					'viec'   => $l['viec'],
					'nhan'   => $l['nhan'],
					'maDon'  => (string) $r['ma_don'],
					'ky'     => (string) $r['ky'],
					'nguoi'  => (string) $r['nguoi_lap'],
					'luc'    => (string) $r['luc'],
				);
			}
		}
		return $ra;
	}

	/**
	 * DÒNG CHI CỦA MỘT ĐƠN — đọc thẳng bảng, KHÔNG gọi `<Bản>_Don::get_don()`.
	 *
	 * 🔴 `get_don()` GÁC THEO PHIÊN ĐĂNG NHẬP CỦA CHÍNH BẢN ẤY. Nó đi qua
	 *    `loi_khong_phai_don_minh()` → `VHCP_DonVi::vi_sao_khong_dung()`, mà hàm ấy đọc
	 *    `VHCP_Auth` — phiên bên bản kia. Trang tổng không đăng nhập vào bản nào cả (nó mượn sổ
	 *    người dùng, không mượn phiên), nên gọi vào đấy là hỏi một câu mà bên kia không có ngữ
	 *    cảnh để trả lời: lúc chối oan, lúc cho qua, và cả hai đều không phải câu trả lời đúng.
	 *
	 * ⚠️ QUYỀN VẪN ĐƯỢC GÁC — ở tầng trang tổng, trước khi gọi tới đây: người hỏi phải có tài
	 *    khoản ở bản ấy (xem `VHCPT_Api::chi_tiet()`). Đọc thẳng không có nghĩa là đọc không
	 *    chốt; nó có nghĩa là chốt nằm ở nơi có đủ ngữ cảnh để chốt.
	 */
	public static function dong_chi( $khoa, $ma_don ) {
		global $wpdb;
		$tien_to = VHCPT_Ban::tien_to_bang( $khoa );
		if ( '' === $tien_to ) { return array(); }
		$ma = trim( (string) $ma_don );
		if ( '' === $ma ) { return array(); }
		/* Trạng thái đơn — đọc MỘT lượt cho cả bảng, vì `thuc_chi()` cần nó cho từng dòng. */
		$tt_don = (string) $wpdb->get_var( $wpdb->prepare(
			"SELECT trang_thai FROM {$tien_to}don WHERE ma_don = %s LIMIT 1", $ma ) );
		/* ⚠️ BẢNG TÊN LÀ `chiphi`, KHÔNG PHẢI `cp`. Đọc nhầm tên bảng thì `$wpdb` trả mảng rỗng
		   và màn hiện "chưa có dòng chi nào" — đúng câu mà một đơn xin ứng trước cũng hiện, nên
		   nhìn không ra là hỏng.
		   ⚠️ XẾP THEO `tao_luc`, KHÔNG XẾP THEO `id`. Cột id ở bảng này là VARCHAR (mã sinh
		   chuỗi), nên `ORDER BY id` là xếp theo bảng chữ cái của một cái mã — tức xếp bừa. */
		$bang = $tien_to . 'chiphi';
		$rows = $wpdb->get_results( $wpdb->prepare(
			/* ẢNH CHỨNG TỪ — anh Thắng 14/09/2026: *"thêm cột hình ảnh để dò nữa nhé"*. Người
			   duyệt đối chiếu số tiền với tờ hoá đơn; thiếu cột này là phải mở trang mảng cho
			   từng dòng, mà một đơn tuần có tới hai chục dòng. */
			"SELECT coso, ngay, nhom, noi_dung, so_luong, don_gia, thanh_tien, thuc_mua, anh
			   FROM $bang WHERE ma_don = %s ORDER BY tao_luc ASC, ngay ASC LIMIT %d",
			$ma, self::GIOI_HAN
		), ARRAY_A );
		if ( ! is_array( $rows ) ) { return array(); }
		$ra = array();
		foreach ( $rows as $r ) {
			$ra[] = array(
				'coso'      => (string) $r['coso'],
				'ngay'      => (string) $r['ngay'],
				'nhom'      => (string) $r['nhom'],
				'noiDung'   => (string) $r['noi_dung'],
				'soLuong'   => (float) $r['so_luong'],
				'donGia'    => (float) $r['don_gia'],
				'thanhTien' => (float) $r['thanh_tien'],
				/* ⚠️ Ô rỗng là CHƯA ĐÍNH ẢNH, không phải lỗi — đơn hợp lệ vẫn có dòng không ảnh
				   (mua lẻ không lấy hoá đơn). Trả chuỗi rỗng và để màn nói bằng dấu gạch. */
				'anh'       => trim( (string) ( isset( $r['anh'] ) ? $r['anh'] : '' ) ),
				/* ══════════════════════════════════════════════════════════════════════════════
				 * THỰC CHI — anh Thắng 14/09/2026: *"chi thực tế nữa"*.
				 * ══════════════════════════════════════════════════════════════════════════════
				 * 🔴 GỌI LÕI `thuc_chi()` CỦA BẢN ẤY, KHÔNG TỰ CHỌN GIỮA HAI Ô. Luật nghe đơn
				 *    giản — "có gõ thực mua thì lấy, không thì lấy thành tiền" — nhưng còn một
				 *    vế nữa dễ quên: CHƯA CẤP TIỀN thì thực chi là 0, không phải bằng thành
				 *    tiền. Quên vế ấy là đơn mới lập đã hiện "đã chi" đúng bằng số xin, và
				 *    người duyệt đọc thành tiền đã ra khỏi két.
				 * ⚠️ Hàm ấy là hàm THUẦN (`$thanh_tien, $thuc_mua, $trang_thai_don`) nên gọi
				 *    được thẳng, không cần mượn phiên.
				 * ══════════════════════════════════════════════════════════════════════════════ */
				'thucChi'   => self::thuc_chi_dong( $khoa, $r, $tt_don ),
			);
		}
		return $ra;
	}

	/** Một hàng đơn, đọc thẳng — cùng lý do với `dong_chi()`. */
	public static function mot_don( $khoa, $ma_don ) {
		global $wpdb;
		$tien_to = VHCPT_Ban::tien_to_bang( $khoa );
		if ( '' === $tien_to ) { return null; }
		$ma = trim( (string) $ma_don );
		if ( '' === $ma ) { return null; }
		$bang = $tien_to . 'don';
		$r = $wpdb->get_row( $wpdb->prepare(
			/* Mốc AI LÀM GÌ LÚC NÀO — anh Thắng 14/09/2026 muốn bấm Xem là thấy đủ, khỏi mở
			   trang mảng. Lấy luôn trong câu này chứ không hỏi thêm lượt nữa. */
			"SELECT ma_don, ky, nguoi_lap, don_vi, ngay_tao, trang_thai, ghi_chu,
			        nguoi_duyet, ngay_duyet, tam_ung_duyet,
			        nguoi_cap, ngay_cap, nguoi_qt, ngay_qt, nguoi_qt_ncc, ngay_qt_ncc
			   FROM $bang WHERE ma_don = %s LIMIT 1", $ma
		), ARRAY_A );
		return is_array( $r ) ? $r : null;
	}

	/** Người đang đăng nhập làm được việc gì ở bản này. Nhớ theo bản trong một lượt tải. */
	private static $lam_memo = array();

	private static function lam_duoc( $khoa ) {
		if ( isset( self::$lam_memo[ $khoa ] ) ) { return self::$lam_memo[ $khoa ]; }
		$ra = array();
		foreach ( array_keys( VHCPT_Auth::VIEC ) as $viec ) {
			$ra[ $viec ] = VHCPT_Auth::duoc( $khoa, $viec );
		}
		self::$lam_memo[ $khoa ] = $ra;
		return $ra;
	}

	/**
	 * Gom đơn của MỌI bản người này đọc được.
	 *
	 * 🔴 CHỈ GOM BẢN NGƯỜI ẤY CÓ MẶT. Gom hết rồi lọc khi vẽ là con số tổng ở đầu màn đã kể cả
	 *    những mảng họ không được nhìn — mà con số ấy chính là thứ người ta đọc trước tiên.
	 */
	public static function gom( $nhom = 'duyet' ) {
		$cac = self::nhom();
		$k   = isset( $cac[ $nhom ] ) ? $nhom : 'duyet';
		$tt  = $cac[ $k ]['tt'];
		$ra  = array();
		foreach ( VHCPT_Auth::ban_doc_duoc() as $khoa ) {
			foreach ( self::don_cua_ban( $khoa, $tt ) as $d ) { $ra[] = $d; }
		}
		/* Mới nhất lên đầu, gộp chung ba mảng — người duyệt đọc theo thời gian, không đọc theo
		   mảng; muốn theo mảng thì đã vào thẳng trang của mảng ấy rồi. */
		usort( $ra, function ( $a, $b ) {
			return strcmp( (string) $b['ngayTao'], (string) $a['ngayTao'] );
		} );
		return $ra;
	}

	/**
	 * Đếm đơn đang chờ ở từng nhóm — để bày con số lên tab.
	 *
	 * 🔴 ĐẾM BẰNG `COUNT(*)`, KHÔNG ĐẾM BẰNG `count( don_cua_ban() )`. Bản nháp của hàm này gọi
	 *    lại `don_cua_ban()` cho cả ba nhóm, mà hàm ấy hỏi `tong_xin_hien_tai()` cho TỪNG đơn —
	 *    mỗi lượt hỏi là một `get_don()` đọc cả dòng chi. Ba nhóm × ba bản × 200 đơn là hơn
	 *    nghìn lượt đọc chỉ để in ba con số lên tab. Trang mở ra chậm rồi hết giờ, và cái chậm
	 *    ấy tăng dần theo số đơn nên lúc mới cài không ai thấy.
	 */
	public static function dem_cua_ban( $khoa, $ds_tt ) {
		global $wpdb;
		$tien_to = VHCPT_Ban::tien_to_bang( $khoa );
		if ( '' === $tien_to ) { return 0; }
		$ds_tt = array_values( array_filter( array_map( 'strval', (array) $ds_tt ) ) );
		if ( ! $ds_tt ) { return 0; }
		$bang = $tien_to . 'don';
		$cho  = implode( ',', array_fill( 0, count( $ds_tt ), '%s' ) );
		$sql  = $wpdb->prepare( "SELECT COUNT(*) FROM $bang WHERE trang_thai IN ($cho)", $ds_tt );
		return (int) $wpdb->get_var( $sql );
	}

	public static function dem() {
		$ra = array();
		foreach ( self::nhom() as $k => $n ) {
			$so = 0;
			/* Đếm VIỆC CÒN PHẢI LÀM, không đếm cả đơn đã xong — xem khối ở `nhom()`. */
			$dem_tt = isset( $n['demTt'] ) ? $n['demTt'] : $n['tt'];
			foreach ( VHCPT_Auth::ban_doc_duoc() as $khoa ) {
				$so += self::dem_cua_ban( $khoa, $dem_tt );
			}
			$ra[ $k ] = $so;
		}
		return $ra;
	}
}
