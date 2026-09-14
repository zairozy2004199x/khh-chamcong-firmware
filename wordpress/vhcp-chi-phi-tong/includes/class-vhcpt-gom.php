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

	/** Số đơn tối đa đọc về một lượt. */
	const GIOI_HAN = 200;

	/** Các nhóm trạng thái bày thành tab trên màn. */
	public static function nhom() {
		return array(
			'duyet' => array( 'ten' => 'Chờ duyệt tạm ứng', 'tt' => array( self::CHO_DUYET ) ),
			'cap'   => array( 'ten' => 'Chờ cấp tạm ứng',   'tt' => array( self::CHO_CAP ) ),
			'qt'    => array( 'ten' => 'Chờ quyết toán',    'tt' => array( self::CHO_QT ) ),
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

		$lop_don = VHCPT_Ban::lop( $khoa, 'Don' );
		/* ⚠️ Gác CÙNG HÀM với lời gọi — luật `tools/test/kiem-goi-cheo.php`. */
		$hoi_tien = ( $lop_don && class_exists( $lop_don )
			&& method_exists( $lop_don, 'tong_xin_hien_tai' ) );

		$ra = array();
		foreach ( $rows as $r ) {
			$ma = trim( (string) $r['ma_don'] );
			if ( '' === $ma ) { continue; }
			$tien = null;
			if ( $hoi_tien ) {
				$tien = call_user_func( array( $lop_don, 'tong_xin_hien_tai' ), $ma );
			}
			$ra[] = array(
				'ban'      => $khoa,
				'tenBan'   => VHCPT_Ban::ten( $khoa ),
				'urlBan'   => (string) ( isset( VHCPT_Ban::ds()[ $khoa ]['url'] ) ? VHCPT_Ban::ds()[ $khoa ]['url'] : '' ),
				'maDon'    => $ma,
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
		/* ⚠️ BẢNG TÊN LÀ `chiphi`, KHÔNG PHẢI `cp`. Đọc nhầm tên bảng thì `$wpdb` trả mảng rỗng
		   và màn hiện "chưa có dòng chi nào" — đúng câu mà một đơn xin ứng trước cũng hiện, nên
		   nhìn không ra là hỏng.
		   ⚠️ XẾP THEO `tao_luc`, KHÔNG XẾP THEO `id`. Cột id ở bảng này là VARCHAR (mã sinh
		   chuỗi), nên `ORDER BY id` là xếp theo bảng chữ cái của một cái mã — tức xếp bừa. */
		$bang = $tien_to . 'chiphi';
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT coso, ngay, nhom, noi_dung, so_luong, don_gia, thanh_tien
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
			"SELECT ma_don, ky, nguoi_lap, don_vi, ngay_tao, trang_thai, ghi_chu,
			        nguoi_duyet, ngay_duyet, tam_ung_duyet
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
			foreach ( VHCPT_Auth::ban_doc_duoc() as $khoa ) {
				$so += self::dem_cua_ban( $khoa, $n['tt'] );
			}
			$ra[ $k ] = $so;
		}
		return $ra;
	}
}
