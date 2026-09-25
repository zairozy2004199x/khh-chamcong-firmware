<?php
/**
 * DANH MỤC — cơ sở → cụm máy → ô máy → mã hàng. Thay `JP2_03_CauHinh.gs`.
 *
 * =============================================================================================
 * 🔴 MỌI QUY TẮC "Ô TRỐNG THÌ RƠI VỀ ĐÂU" ĐỀU CHÉP Y NGUYÊN — VÀ CHÚNG KHÔNG CÙNG MỘT CHIỀU.
 * =============================================================================================
 * Nhìn qua thì ba cờ dưới đây giống nhau, nhưng chiều an toàn của mỗi cái nằm một phía, và bản
 * gốc chọn chiều theo HẬU QUẢ của việc quên khai, không theo cho đẹp mã:
 *
 *   · `co_dh_trung()`    mặc định **CÓ**.    Quên khai -> thừa một cửa kiểm (thấy ngay).
 *                                             Mặc định ngược lại -> thiếu một cửa kiểm (im lặng).
 *   · `chon_gia_xung()`  mặc định **KHÔNG**. Chỉ một cơ sở có hai loại máy lẫn nhau; 12 cơ sở
 *                                             kia thấy ô chọn mà không cần dùng là *"có ngày ai
 *                                             đó chọn nhầm 10.000đ và doanh thu cơ sở đó gấp
 *                                             đôi, mà sổ vẫn cân"* — không phép kiểm nào bắt.
 *   · `bc_mau()`         mặc định **CHUNG**. Mẫu lạ rơi về mẫu chung, không nổ.
 *
 * Đảo chiều một trong ba là đổi hành vi của 13 cơ sở đang chạy. `kiem-jp-cau-hinh.php` chạy
 * chính mã JavaScript gốc bằng node rồi đối chiếu, nên đảo chiều là bài kiểm đỏ.
 *
 * ---------------------------------------------------------------------------------------------
 * ⚠️ BA CHỖ ĐẾM ẢNH, BA CÁCH RƠI VỀ KHÁC NHAU — VÀ ĐÓ LÀ CỐ Ý, KHÔNG PHẢI SƠ SUẤT.
 * ---------------------------------------------------------------------------------------------
 *   · cơ sở  `photoDefault`  ô trống VÀ số 0 đều rơi về **1** (`?: 1`)
 *   · cụm    `photoCount`    ô trống rơi về **1 nếu có QR, 0 nếu chưa lắp**; số 0 gõ vào giữ 0
 *   · ô máy  `photoCount`    ô trống rơi về **1**; số 0 gõ vào giữ 0
 *
 * Chỗ cơ sở khác hai chỗ kia: nó KHÔNG phân biệt "chưa đặt" với "đặt số 0". Bản gốc từng có
 * đúng bệnh ấy ở cả ba chỗ và đã sửa hai — *"kế toán đặt 0 rồi mở lại thấy 1, tưởng không lưu
 * được"*. Chỗ cơ sở còn lại như cũ, và bản chuyển giữ nguyên: đây là bản chuyển, không phải
 * bản sửa. Ghim lại trong bài kiểm để ai muốn thống nhất thì phải làm CÓ CHỦ Ý.
 *
 * ---------------------------------------------------------------------------------------------
 * ⚠️ `=== false` VÀ `=== true` LÀ CHẶT, KHÔNG PHẢI "CHO GỌN".
 * ---------------------------------------------------------------------------------------------
 * `active: p.active === false ? 'N' : 'Y'` — chỉ đúng giá trị luận lý `false` mới thành `'N'`.
 * Chuỗi `'N'`, số `0`, chuỗi rỗng đều ra `'Y'`. Nới thành "giả thì tắt" là màn hình gửi lên một
 * ô trống cũng TẮT cơ sở, và cơ sở biến mất khỏi mọi danh sách mà không ai bấm gì.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHJP_CauHinh {

	const LOAI_TIEN  = 'TIEN';
	const LOAI_XU    = 'XU';
	const MAU_CHUNG  = '';        // tiền + hàng cùng một dòng
	const MAU_TACH   = 'TACH';    // tách tiền / hàng
	const DVT_MAC_DINH = 'Quả';

	/* ═══════════════════════ QUY TẮC RƠI VỀ ═══════════════════════ */

	/** Mẫu báo cáo. Chỉ đúng `TACH` (không phân biệt hoa thường) mới là mẫu tách. */
	public static function bc_mau( $v ) {
		return strtoupper( VHJP_Doc::str( $v ) ) === self::MAU_TACH ? self::MAU_TACH : self::MAU_CHUNG;
	}

	/** Cơ sở có đồng hồ đếm trứng không. 🔴 MẶC ĐỊNH **CÓ** — chỉ đúng `'N'` mới là không. */
	public static function co_dh_trung( $v ) {
		return strtoupper( VHJP_Doc::str( $v ) ) !== 'N';
	}

	/** Cơ sở có hai loại máy tiền lẫn nhau không. 🔴 MẶC ĐỊNH **KHÔNG** — ngược chiều cái trên. */
	public static function chon_gia_xung( $v ) {
		return strtoupper( VHJP_Doc::str( $v ) ) === 'Y';
	}

	/**
	 * Giá một lượt suy từ MÃ HÀNG: `20JP` -> 20.000đ.
	 *
	 * ⚠️ Chỉ nhận khi số nằm trong khoảng 1..1000; ngoài khoảng trả 0 chứ không đoán bừa. Và
	 *    `JP20` (chữ trước số) KHÔNG nhận — đó là mã khác, không phải giá.
	 */
	public static function gia_tu_ma( $ma ) {
		if ( ! preg_match( '/^(\d+)\s*JP/i', trim( (string) $ma ), $m ) ) { return 0; }
		$n = (int) $m[1];
		return ( $n > 0 && $n <= 1000 ) ? $n * 1000 : 0;
	}

	/** Đơn vị tính của một mã hàng. Mã đời cũ chưa khai thì rơi về `Quả`. */
	public static function dvt( $hang ) {
		$v = is_array( $hang ) && isset( $hang['dvt'] ) ? VHJP_Doc::str( $hang['dvt'] ) : '';
		return '' !== $v ? $v : self::DVT_MAC_DINH;
	}

	/* ═══════════════════════ HÌNH DẠNG ĐƯA RA MÀN HÌNH ═══════════════════════ */

	public static function pub_coso( $l ) {
		return array(
			'id'          => (string) $l['id'],
			'code'        => VHJP_Doc::str( $l['code'] ),
			'name'        => VHJP_Doc::str( $l['name'] ),
			'maKH'        => VHJP_Doc::str( $l['maKH'] ),
			'machineType' => VHJP_Doc::str( $l['machineType'] ) ?: self::LOAI_TIEN,
			/* ⚠️ `?: 1` — ô trống VÀ số 0 đều ra 1. Khác hai chỗ đếm ảnh kia; xem khối ⚠️ đầu tệp. */
			'photoDefault' => VHJP_Doc::num( $l['photoDefault'] ) ?: 1,
			'bcMau'        => self::bc_mau( $l['bcMau'] ),
			'coDhTrung'    => self::co_dh_trung( $l['coDhTrung'] ),
			'chonGiaXung'  => self::chon_gia_xung( $l['chonGiaXung'] ),
			'maDinhDanh'   => VHJP_Doc::str( $l['maDinhDanh'] ),
		);
	}

	public static function pub_cum( $c ) {
		$co_qr = VHJP_Doc::str( $c['hasQR'] ) === 'Y';
		return array(
			'id'           => (string) $c['id'],
			'locationId'   => (string) $c['locationId'],
			'name'         => VHJP_Doc::str( $c['name'] ),
			'payboxSerial' => VHJP_Doc::str( $c['payboxSerial'] ),
			'hasQR'        => $co_qr,
			/* Ô trống: có QR thì 1, chưa lắp thì 0 — khớp đúng lúc lưu. Số 0 gõ vào thì giữ 0. */
			'photoCount'   => VHJP_Doc::so_anh( $c['photoCount'], $co_qr ? 1 : 0 ),
		);
	}

	public static function pub_may( $m ) {
		return array(
			'id'         => (string) $m['id'],
			'locationId' => (string) $m['locationId'],
			'clusterId'  => (string) $m['clusterId'],
			'code'       => VHJP_Doc::str( $m['code'] ),
			'itemCode'   => VHJP_Doc::str( $m['itemCode'] ),
			'itemMisa'   => VHJP_Doc::str( $m['itemMisa'] ),
			'photoCount' => VHJP_Doc::so_anh( $m['photoCount'] ),
		);
	}

	public static function pub_hang( $i ) {
		$ma = VHJP_Doc::str( $i['code'] );
		return array(
			'code'  => $ma,
			'misa'  => VHJP_Doc::str( $i['misa'] ),
			'name'  => VHJP_Doc::str( $i['name'] ),
			/* Giá 0 (hoặc chưa khai) thì suy từ chính mã hàng — `20JP` ra 20.000đ. */
			'price' => VHJP_Doc::num( $i['price'] ) ?: self::gia_tu_ma( $ma ),
			'dvt'   => self::dvt( $i ),
		);
	}

	/* ═══════════════════════ TRA MÃ HÀNG ═══════════════════════ */

	/**
	 * BẢNG TRA MÃ HÀNG — dựng MỘT LẦN rồi tra nhiều lượt.
	 *
	 * Đường lưu báo cáo tra mã cho từng dòng; đọc lại danh mục mỗi dòng là đọc cả bảng hàng
	 * vài chục lượt cho một lượt lưu.
	 *
	 * ⚠️ Gài thêm khoá viết HOA để `tra_hang()` dung được HOA/thường — xem chú thích ở đó.
	 *    CHỐT CHỐNG NHẬP NHẰNG: danh mục có HAI mã chỉ khác chữ hoa thì khoá HOA trỏ vào đâu
	 *    cũng là đoán, nên BỎ HẲN khoá đó. Không mã nào được đè lên một mã thật đang có.
	 */
	public static function ban_do_hang() {
		$m = array(); $hoa = array(); $doi = array();
		foreach ( VHJP_Nguon::doc( 'JP_Items' ) as $i ) {
			$ma = VHJP_Doc::str( $i['code'] );
			$m[ $ma ] = array(
				/* `code` = cách viết CHUẨN trong danh mục. Có nó thì đường lưu mới snap được
				   mã nhân viên gõ về đúng cách viết này. */
				'code'  => $ma,
				'misa'  => VHJP_Doc::str( $i['misa'] ),
				'name'  => VHJP_Doc::str( $i['name'] ),
				'price' => VHJP_Doc::num( $i['price'] ) ? VHJP_Doc::num( $i['price'] ) : self::gia_tu_ma( $ma ),
				'dvt'   => self::dvt( $i ),
			);
			$U = mb_strtoupper( $ma, 'UTF-8' );
			if ( $U !== $ma ) {
				if ( isset( $hoa[ $U ] ) ) { $doi[ $U ] = true; } else { $hoa[ $U ] = $ma; }
			} else {
				if ( isset( $hoa[ $U ] ) && $hoa[ $U ] !== $ma ) { $doi[ $U ] = true; } else { $hoa[ $U ] = $ma; }
			}
		}
		foreach ( $hoa as $U => $ma ) {
			if ( isset( $doi[ $U ] ) ) { continue; }
			if ( ! isset( $m[ $U ] ) ) { $m[ $U ] = $m[ $ma ]; }
		}
		return $m;
	}

	/**
	 * TRA MỘT MÃ HÀNG, DUNG THỨ HOA/thường.
	 *
	 * 🔴 VÌ SAO PHẢI DUNG THỨ. Nhân viên gõ `100jp031` chữ thường là mã HOÀN TOÀN ĐÚNG, nhưng
	 *    tra thẳng thì trượt. Trượt ở đây không chỉ mất tên hàng: `itemCode` lưu xuống giữ
	 *    nguyên chữ thường, nên duyệt xong kho KHÔNG tìm được lớp tồn ⇒ giá vốn về 0đ, SỔ 632
	 *    THIẾU trong khi sổ vẫn CÂN. Đo trên dữ liệu thật 22/08/2026: bốn mã đúng y nguyên
	 *    chỉ khác chữ hoa.
	 *
	 * ⚠️ CHỈ dung thứ HOA/thường và dấu cách hai đầu — KHÔNG bỏ dấu, KHÔNG dò gần giống. Gõ
	 *    tắt kiểu `B chuột nước` → `100JP122` là quyết định NGHIỆP VỤ của kế toán; đoán hộ là
	 *    gán giá vốn của mặt hàng này sang mặt hàng khác, mà sổ vẫn cân nên không phép kiểm
	 *    nào bắt được.
	 *
	 * ⚠️ Trả `null` khi không thấy — đừng trả mảng rỗng, vì nơi gọi cần phân biệt "có trong
	 *    danh mục" với "không có" để còn cảnh báo được.
	 */
	public static function tra_hang( $ban_do, $ma ) {
		if ( ! is_array( $ban_do ) ) { return null; }
		$m = VHJP_Doc::str( $ma );
		if ( '' === $m ) { return null; }
		if ( isset( $ban_do[ $m ] ) ) { return $ban_do[ $m ]; }
		$U = mb_strtoupper( $m, 'UTF-8' );
		return isset( $ban_do[ $U ] ) ? $ban_do[ $U ] : null;
	}

	/* ═══════════════════════ ĐỌC DANH MỤC ═══════════════════════ */

	/** Chỉ lấy dòng đang bật. `'N'` là tắt; mọi giá trị khác (kể cả ô trống) là bật. */
	private static function dang_bat( $r ) {
		return 'N' !== VHJP_Doc::str( isset( $r['active'] ) ? $r['active'] : '' );
	}

	public static function ds_coso( $ca = false ) {
		$ra = array();
		foreach ( VHJP_Nguon::doc( 'JP_Locations' ) as $r ) {
			if ( ! $ca && ! self::dang_bat( $r ) ) { continue; }
			$ra[] = self::pub_coso( $r );
		}
		return $ra;
	}

	public static function ds_cum( $ma_coso = '', $ca = false ) {
		$ds = '' === (string) $ma_coso
			? VHJP_Nguon::doc( 'JP_Clusters' )
			: VHJP_Nguon::tim( 'JP_Clusters', 'locationId', $ma_coso );
		$ra = array();
		foreach ( $ds as $r ) {
			if ( ! $ca && ! self::dang_bat( $r ) ) { continue; }
			$ra[] = self::pub_cum( $r );
		}
		return $ra;
	}

	public static function ds_may( $ma_coso = '', $ca = false ) {
		$ds = '' === (string) $ma_coso
			? VHJP_Nguon::doc( 'JP_Machines' )
			: VHJP_Nguon::tim( 'JP_Machines', 'locationId', $ma_coso );
		$ra = array();
		foreach ( $ds as $r ) {
			if ( ! $ca && ! self::dang_bat( $r ) ) { continue; }
			$ra[] = self::pub_may( $r );
		}
		return $ra;
	}

	public static function ds_hang( $ca = false ) {
		$ra = array();
		foreach ( VHJP_Nguon::doc( 'JP_Items' ) as $r ) {
			if ( ! $ca && ! self::dang_bat( $r ) ) { continue; }
			$ra[] = self::pub_hang( $r );
		}
		return $ra;
	}

	/* ═══════════════════════ GHI DANH MỤC ═══════════════════════ */

	/**
	 * `'Y'`/`'N'` từ một giá trị luận lý — CHẶT.
	 *
	 * 🔴 Chỉ đúng `false` (hoặc `true`) mới đổi chiều. Xem khối ⚠️ cuối phần đầu tệp: nới ra
	 *    là màn hình gửi lên ô trống cũng tắt mất cơ sở.
	 */
	private static function co_khong( $v, $mac_dinh_bat = true ) {
		if ( $mac_dinh_bat ) { return false === $v ? 'N' : 'Y'; }
		return true === $v ? 'Y' : 'N';
	}

	public static function luu_coso( $nguoi, $p ) {
		$p = (array) $p;
		if ( '' === VHJP_Doc::str( isset( $p['name'] ) ? $p['name'] : '' ) ) {
			return array( 'ok' => false, 'msg' => 'Thiếu tên cơ sở' );
		}
		$o = array(
			'code'         => VHJP_Doc::str( self::lay( $p, 'code' ) ),
			'name'         => VHJP_Doc::str( self::lay( $p, 'name' ) ),
			'maKH'         => VHJP_Doc::str( self::lay( $p, 'maKH' ) ),
			'machineType'  => VHJP_Doc::str( self::lay( $p, 'machineType' ) ) ?: self::LOAI_TIEN,
			'photoDefault' => VHJP_Doc::num( self::lay( $p, 'photoDefault' ) ) ?: 1,
			'bcMau'        => self::bc_mau( self::lay( $p, 'bcMau' ) ),
			'coDhTrung'    => self::co_khong( self::lay( $p, 'coDhTrung', null ), true ),
			'chonGiaXung'  => self::co_khong( self::lay( $p, 'chonGiaXung', null ), false ),
			/* Bỏ dấu cách cho khớp nội dung chuyển khoản đã chuẩn hoá. */
			'maDinhDanh'   => strtoupper( preg_replace( '/\s+/', '',
				VHJP_Doc::str( self::lay( $p, 'maDinhDanh' ) ) ) ),
			'active'       => self::co_khong( self::lay( $p, 'active', null ), true ),
			'note'         => VHJP_Doc::str( self::lay( $p, 'note' ) ),
		);
		return self::luu( 'JP_Locations', 'L', $nguoi, $p, $o, 'CFG_LOC', $o['name'] );
	}

	public static function luu_cum( $nguoi, $p ) {
		$p = (array) $p;
		if ( '' === VHJP_Doc::str( self::lay( $p, 'locationId' ) ) ) {
			return array( 'ok' => false, 'msg' => 'Thiếu cơ sở' );
		}
		$co_qr = true === self::lay( $p, 'hasQR', null );
		$o = array(
			'locationId'   => VHJP_Doc::str( self::lay( $p, 'locationId' ) ),
			'name'         => VHJP_Doc::str( self::lay( $p, 'name' ) ),
			'payboxSerial' => VHJP_Doc::str( self::lay( $p, 'payboxSerial' ) ),
			'hasQR'        => $co_qr ? 'Y' : 'N',
			'photoCount'   => VHJP_Doc::so_anh( self::lay( $p, 'photoCount' ), $co_qr ? 1 : 0 ),
			'active'       => self::co_khong( self::lay( $p, 'active', null ), true ),
			'note'         => VHJP_Doc::str( self::lay( $p, 'note' ) ),
		);
		return self::luu( 'JP_Clusters', 'C', $nguoi, $p, $o, 'CFG_CLUSTER', $o['name'] );
	}

	public static function luu_may( $nguoi, $p ) {
		$p = (array) $p;
		if ( '' === VHJP_Doc::str( self::lay( $p, 'locationId' ) ) ) {
			return array( 'ok' => false, 'msg' => 'Thiếu cơ sở' );
		}
		$o = array(
			'locationId' => VHJP_Doc::str( self::lay( $p, 'locationId' ) ),
			'clusterId'  => VHJP_Doc::str( self::lay( $p, 'clusterId' ) ),
			'code'       => VHJP_Doc::str( self::lay( $p, 'code' ) ),
			'itemCode'   => VHJP_Doc::str( self::lay( $p, 'itemCode' ) ),
			'itemMisa'   => VHJP_Doc::str( self::lay( $p, 'itemMisa' ) ),
			'photoCount' => VHJP_Doc::so_anh( self::lay( $p, 'photoCount' ) ),
			'active'     => self::co_khong( self::lay( $p, 'active', null ), true ),
			'note'       => VHJP_Doc::str( self::lay( $p, 'note' ) ),
		);
		return self::luu( 'JP_Machines', 'M', $nguoi, $p, $o, 'CFG_MACHINE', $o['code'] );
	}

	/**
	 * Lưu một mã hàng.
	 *
	 * ⚠️ Bảng hàng lấy `code` làm khoá chính, không có cột `id` — nên không sinh mã, mà dùng
	 *    chính mã hàng người ta gõ. Thiếu mã thì chối.
	 */
	public static function luu_hang( $nguoi, $p ) {
		$p = (array) $p;
		$ma = VHJP_Doc::str( self::lay( $p, 'code' ) );
		if ( '' === $ma ) { return array( 'ok' => false, 'msg' => 'Thiếu mã hàng' ); }
		$o = array(
			'code'   => $ma,
			'misa'   => VHJP_Doc::str( self::lay( $p, 'misa' ) ),
			'name'   => VHJP_Doc::str( self::lay( $p, 'name' ) ),
			'price'  => VHJP_Doc::num( self::lay( $p, 'price' ) ),
			'dvt'    => VHJP_Doc::str( self::lay( $p, 'dvt' ) ),
			'active' => self::co_khong( self::lay( $p, 'active', null ), true ),
			'note'   => VHJP_Doc::str( self::lay( $p, 'note' ) ),
		);
		$co = VHJP_Nguon::tim_mot( 'JP_Items', 'code', $ma );
		if ( $co ) {
			$ok = VHJP_Nguon::sua( 'JP_Items', $ma, $o );
			VHJP_NhatKy::ghi( $nguoi, 'CFG_ITEM_UPDATE', '', $ma, $o );
			return $ok ? array( 'ok' => true, 'id' => $ma )
				: array( 'ok' => false, 'msg' => 'Không lưu được mã hàng ' . $ma );
		}
		$ok = VHJP_Nguon::them( 'JP_Items', $o );
		VHJP_NhatKy::ghi( $nguoi, 'CFG_ITEM_ADD', '', $ma, $o );
		return false !== $ok ? array( 'ok' => true, 'id' => $ma )
			: array( 'ok' => false, 'msg' => 'Không thêm được mã hàng ' . $ma );
	}

	/** Đọc một khoá khỏi mảng, không có thì trả mặc định. */
	private static function lay( $p, $k, $mac_dinh = '' ) {
		return array_key_exists( $k, $p ) ? $p[ $k ] : $mac_dinh;
	}

	/**
	 * Có `id` thì SỬA, không thì THÊM. Dùng chung cho cơ sở / cụm / ô máy.
	 *
	 * 🔴 LƯỢT GHI HỎNG PHẢI NÓI RA. Trả `ok = true` cho một dòng chưa hề ghi được là kế toán
	 *    bấm Lưu, thấy báo xong, đóng màn hình — rồi hôm sau mở lại thấy trống.
	 */
	private static function luu( $tab, $tien_to, $nguoi, $p, $o, $viec, $ten ) {
		$id = VHJP_Doc::str( self::lay( $p, 'id' ) );
		$co = '' !== $id ? VHJP_Nguon::tim_mot( $tab, 'id', $id ) : null;
		if ( $co ) {
			$ok = VHJP_Nguon::sua( $tab, $id, $o );
			VHJP_NhatKy::ghi( $nguoi, $viec . '_UPDATE', '', $ten, $o );
			return $ok ? array( 'ok' => true, 'id' => $id )
				: array( 'ok' => false, 'msg' => 'Không lưu được thay đổi' );
		}
		$kq = VHJP_Ma::them( $tab, $tien_to, $o );
		if ( false === $kq ) { return array( 'ok' => false, 'msg' => 'Không thêm được bản ghi' ); }
		VHJP_NhatKy::ghi( $nguoi, $viec . '_ADD', '', $ten, $o );
		return array( 'ok' => true, 'id' => $kq['id'] );
	}
}
