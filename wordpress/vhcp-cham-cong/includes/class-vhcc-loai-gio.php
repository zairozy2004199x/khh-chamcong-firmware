<?php
/**
 * LOẠI GIỜ LƯƠNG DO CHÍNH NHÂN VIÊN KHAI — chọn lúc kết ca, hoặc xin đổi ngày cũ.
 *
 * =============================================================================================
 * 🔴 VIỆC NÀY TRƯỚC ĐÂY LÀ VIỆC CỦA KẾ TOÁN, VÀ LÀM MỘT LẦN VÀO CUỐI THÁNG
 * =============================================================================================
 * `VHCC_ChotLuong` ra đời từ câu của anh Thắng 16/09/2026: *"kế toán sẽ làm 1 việc, nhập giờ
 * lương khác, nó sẽ trừ giờ tổng đi nếu có giờ khác"*. Kế toán ngồi cuối tháng, nhìn con số
 * tổng 126 giờ, rồi gõ "2 giờ MC, 6 giờ Hỗ Trợ".
 *
 * Chỗ hỏng của lối ấy không nằm ở mã, nằm ở chỗ **người gõ không phải người biết**. Kế toán
 * không có mặt ở cửa hàng hôm mùng 4; họ gõ theo lời kể, hoặc theo trí nhớ của cửa hàng trưởng
 * ba tuần sau. Còn người BIẾT chắc hôm ấy mình dẫn chương trình hay đứng quầy thì lại là người
 * duy nhất không được gõ.
 *
 * Anh Thắng 18/09/2026: *"Khi bấm check in giờ ra nó sẽ hỏi ca 1, 2, 3 bạn làm nhiệm vụ gì. Để
 * nhân viên tự set luôn"*. Hỏi đúng lúc, đúng người, khi trí nhớ còn nóng.
 *
 * =============================================================================================
 * 🔴 HAI CỬA, VÀ CHÚNG CỐ Ý KHÔNG GIỐNG NHAU
 * =============================================================================================
 *   · KẾT CA — ghi THẲNG vào `cham_cong.loai_gio`, không ai duyệt. Người ấy đang khai việc
 *     mình vừa làm xong, giờ ra vừa ghi xong, và cửa hàng trưởng còn ở đó. Bắt duyệt cả thứ
 *     này là mỗi ca một cái đơn, ngày ba chục đơn cho một cửa hàng — sẽ thành bấm duyệt hàng
 *     loạt không đọc, tức là tệ hơn không duyệt.
 *
 *   · SỬA NGÀY CŨ — phải qua `gui()` → cửa hàng trưởng `duyet()`. Loại giờ quyết định ĐƠN GIÁ,
 *     nên sửa được ngày cũ mà không ai duyệt thì cuối tháng ai cũng đổi hết ca của mình sang
 *     việc có giá cao nhất, và bảng lương vẫn trông bình thường.
 *
 * =============================================================================================
 * 🔴 BẬT TẮT THEO TỪNG CƠ SỞ — VÀ TẮT LÀ PHẢI TẮT HẲN
 * =============================================================================================
 * Anh Thắng: *"Tính năng này đang thử nghiệm cho từng cơ sở xem hiệu quả không. Nên cho phép
 * bật tắt theo từng cơ sở"*, và *"Nếu không ổn anh tắt"*.
 *
 * ⚠️ TẮT THÌ KHÔNG HỎI, NHƯNG DỮ LIỆU ĐÃ KHAI VẪN CÒN VÀ VẪN TÍNH. Xoá sạch lúc tắt là mất
 *    công khai của cả một tháng chỉ vì một cú bấm thử; còn lờ đi dữ liệu đã có thì bảng lương
 *    đổi số ngay khi ai đó gạt công tắc — hai kiểu đều tệ hơn là giữ nguyên.
 *    Muốn thật sự xoá thì xoá ở màn quản trị, có chủ ý.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_LoaiGio {

	/** Khoá cấu hình bật/tắt trong bảng `cai_dat`. */
	const O = 'LOAI_GIO_CFG';

	/** Ai gạt được công tắc — cùng bậc với sổ đơn giá, vì nó quyết định cách ra tiền. */
	const QUYEN_CFG = 'gia_gio';

	/** Ai duyệt đơn xin đổi loại giờ. Cửa hàng trưởng — người có mặt hôm ấy. */
	const QUYEN_DUYET = 'cong_coso';

	const CHO     = 'cho';
	const DUYET   = 'duyet';
	const TU_CHOI = 'tu_choi';

	const TEN_TT = array(
		self::CHO     => 'Chờ cửa hàng trưởng duyệt',
		self::DUYET   => 'Đã duyệt',
		self::TU_CHOI => 'Không duyệt',
	);

	/**
	 * Lùi xa nhất được xin đổi.
	 *
	 * 🔴 KHÔNG CHO SỬA NGƯỢC VÔ HẠN. Tháng đã chốt lương thì đổi loại giờ là đòi tính lại tiền
	 *    đã trả. 45 ngày đủ phủ hết tháng trước cộng thời gian kế toán còn đang chốt.
	 */
	const NGAY_LUI_TOI_DA = 45;

	/* ====================================================================== bật / tắt */

	public static function cfg() {
		$d = VHCC_Luong::cai_dat( self::O, null );
		return is_array( $d ) ? $d : array();
	}

	private static function khoa_cs( $coso ) {
		return VHCC_GiaGio::khoa_cs( $coso );
	}

	/** Cơ sở này có hỏi loại giờ lúc kết ca không. */
	public static function bat_ket_ca( $coso, $cfg = null ) {
		$c = ( null === $cfg ) ? self::cfg() : $cfg;
		$k = self::khoa_cs( $coso );
		return ( '' !== $k && ! empty( $c[ $k ]['ketCa'] ) );
	}

	/** Cơ sở này có bày tab "Giờ công lương" trên trạm không. */
	public static function bat_tab( $coso, $cfg = null ) {
		$c = ( null === $cfg ) ? self::cfg() : $cfg;
		$k = self::khoa_cs( $coso );
		return ( '' !== $k && ! empty( $c[ $k ]['tab'] ) );
	}

	/**
	 * NGƯỜI NÀY có thấy tab "Giờ công lương" không.
	 *
	 * 🔴 KHÔNG PHẢI CỨ BẬT LÀ CẢ CƠ SỞ THẤY. Cùng luật với `hoi_khi_ra()`: người chỉ có một loại
	 *    giờ thì cái tab ấy chẳng có gì để đổi, mà lại là đúng cái nút để họ thử đổi ca của
	 *    mình sang việc giá cao hơn — anh Thắng 18/09/2026: *"tránh cập nhật nhầm hoặc gian
	 *    lận"*. Bày một ô rồi chối ở trong còn tệ hơn: họ đi hỏi vòng quanh.
	 */
	public static function hien_tab( $coso, $ma_nv, $cfg = null, $so = null ) {
		if ( ! self::bat_tab( $coso, $cfg ) ) { return false; }
		return count( self::ds_viec( $coso, $ma_nv, $so ) ) >= 2;
	}

	/** Gạt công tắc. `null` = không đụng tới cái ấy. */
	public static function dat_cfg( $u, $coso, $ket_ca = null, $tab = null ) {
		if ( ! VHCC_Vai::duoc( $u, self::QUYEN_CFG ) ) {
			return array( 'ok' => false,
				'error' => VHCC_Vai::loi( $u, self::QUYEN_CFG, 'Bật/tắt loại giờ lương' ) );
		}
		$k = self::khoa_cs( $coso );
		if ( '' === $k ) { return array( 'ok' => false, 'error' => 'Thiếu cơ sở.' ); }
		$c = self::cfg();
		if ( null !== $ket_ca ) { $c[ $k ]['ketCa'] = $ket_ca ? 1 : 0; }
		if ( null !== $tab ) { $c[ $k ]['tab'] = $tab ? 1 : 0; }
		/* Cơ sở tắt cả hai thì bỏ hẳn khỏi sổ — để danh sách cơ sở đang bật đọc được bằng mắt. */
		if ( empty( $c[ $k ]['ketCa'] ) && empty( $c[ $k ]['tab'] ) ) { unset( $c[ $k ] ); }
		VHCC_Luong::dat_cai_dat( self::O, $c, $u );
		return array( 'ok' => true, 'ketCa' => self::bat_ket_ca( $coso, $c ),
			'tab' => self::bat_tab( $coso, $c ) );
	}

	/** Mấy cơ sở đang bật — cho màn quản trị và cho câu "đang thử ở đâu". */
	public static function coso_dang_bat() {
		$ra = array();
		foreach ( self::cfg() as $k => $v ) {
			if ( ! empty( $v['ketCa'] ) || ! empty( $v['tab'] ) ) {
				$ra[ $k ] = array( 'ketCa' => ! empty( $v['ketCa'] ), 'tab' => ! empty( $v['tab'] ) );
			}
		}
		return $ra;
	}

	/* ====================================================================== danh sách việc */

	/**
	 * MỌI LOẠI GIỜ CƠ SỞ CÓ KHAI GIÁ — danh sách để CỬA HÀNG TRƯỞNG chọn khi PHÂN việc cho một
	 * người. KHÔNG phải danh sách nhân viên được bấm; cái ấy là `ds_viec()` bên dưới.
	 *
	 * 🔴 LẤY TỪ CHÍNH SỔ ĐƠN GIÁ, KHÔNG DỰNG MỘT DANH SÁCH THỨ HAI. Anh Thắng: *"nhân viên đó
	 *    có 3 giờ lương"* — ba giờ lương ấy CHÍNH LÀ ba dòng đơn giá đã khai cho người ấy hoặc
	 *    cho cơ sở ấy. Dựng thêm một danh sách riêng là hai sổ phải khớp nhau bằng tay, và tới
	 *    ngày lệch thì người ta chọn được một việc không có giá — ra 0đ.
	 *
	 * ⚠️ Đơn giá RIÊNG của người thắng đơn giá của cơ sở, đúng thứ tự `VHCC_GiaGio::tra()`. Nên
	 *    danh sách gộp cả hai tầng, và tầng người đè lên tầng cơ sở khi trùng khoá việc.
	 *
	 * @return array [ [ 'khoa' => 'mc', 'ten' => 'MC', 'gia' => 30000.0 ], … ] — xếp theo tên.
	 */
	public static function ds_gia( $coso, $ma_nv, $so = null ) {
		$so  = ( null === $so ) ? VHCC_GiaGio::so() : $so;
		$kcs = VHCC_GiaGio::khoa_cs( $coso );
		$kma = VHCC_GiaGio::khoa_ma( $ma_nv );

		$gom = array();
		if ( '' !== $kcs && isset( $so['coso'][ $kcs ] ) && is_array( $so['coso'][ $kcs ] ) ) {
			foreach ( $so['coso'][ $kcs ] as $kv => $gia ) {
				if ( (float) $gia > 0 ) { $gom[ $kv ] = (float) $gia; }
			}
		}
		if ( '' !== $kma && isset( $so['nguoi'][ $kma ] ) && is_array( $so['nguoi'][ $kma ] ) ) {
			foreach ( $so['nguoi'][ $kma ] as $kv => $gia ) {
				/* `*` = mọi chức vụ của người ấy. Nó không phải một VIỆC để chọn, nên bỏ khỏi
				   danh sách — nhưng nó vẫn là giá sẽ ăn, và `VHCC_GiaGio::tra()` lo phần ấy. */
				if ( '*' === $kv ) { continue; }
				if ( (float) $gia > 0 ) { $gom[ $kv ] = (float) $gia; }
			}
		}

		$ra = array();
		foreach ( $gom as $kv => $gia ) {
			$ra[] = array( 'khoa' => $kv, 'ten' => VHCC_GiaGio::ten_cua( $kv, $so ), 'gia' => $gia );
		}
		usort( $ra, function ( $a, $b ) { return strcasecmp( $a['ten'], $b['ten'] ); } );
		return $ra;
	}

	/* ---------------------------------------------------------------- phân việc cho một người */

	/**
	 * MẤY LOẠI GIỜ CỬA HÀNG TRƯỞNG ĐÃ PHÂN CHO MỘT NGƯỜI. Mảng tên việc; rỗng = chưa phân.
	 *
	 * Anh Thắng 18/09/2026: *"Trừ khi bạn đó mới được phân thì cht sẽ set. Thì hệ thống sẽ hiểu
	 * bạn này đang có thể làm 2 giờ lương thì sẽ hỏi lần sau chấm công"*.
	 */
	public static function phan( $coso, $ma_nv, $cfg = null ) {
		$c   = ( null === $cfg ) ? self::cfg() : $cfg;
		$kcs = self::khoa_cs( $coso );
		$kma = VHCC_GiaGio::khoa_ma( $ma_nv );
		if ( '' === $kcs || '' === $kma || ! isset( $c[ $kcs ]['phan'][ $kma ] ) ) { return array(); }
		$ds = (array) $c[ $kcs ]['phan'][ $kma ];
		$ra = array();
		foreach ( $ds as $t ) {
			$t = trim( (string) $t );
			if ( '' !== $t && ! in_array( $t, $ra, true ) ) { $ra[] = $t; }
		}
		return $ra;
	}

	/** Cửa hàng trưởng phân việc. Mảng rỗng = bỏ phân (người ấy thôi bị hỏi). */
	public static function dat_phan( $u, $coso, $ma_nv, $ds_viec ) {
		$cs = VHCC_NhanSu::chuan_coso( $coso );
		if ( ! VHCC_Vai::duoc( $u, self::QUYEN_DUYET ) ) {
			return array( 'ok' => false,
				'error' => VHCC_Vai::loi( $u, self::QUYEN_DUYET, 'Phân loại giờ lương' ) );
		}
		if ( '' === $cs || ! VHCC_NhanSu::co_quyen_coso( $u, $cs ) ) {
			return array( 'ok' => false, 'error' => 'Không có quyền trên cơ sở này.' );
		}
		$kma = VHCC_GiaGio::khoa_ma( $ma_nv );
		if ( '' === $kma ) { return array( 'ok' => false, 'error' => 'Thiếu mã nhân viên.' ); }

		/* 🔴 CHỈ NHẬN VIỆC CÓ TRONG BẢNG ĐƠN GIÁ. Phân một việc chưa khai giá là người ấy bấm
		   chọn xong rồi ăn 0đ — và màn thì vẫn xanh suốt từ đầu tới cuối. */
		$hop_le = array();
		foreach ( self::ds_gia( $cs, $ma_nv ) as $x ) { $hop_le[ $x['khoa'] ] = $x['ten']; }
		$sach = array();
		foreach ( (array) $ds_viec as $t ) {
			$k = VHCC_GiaGio::khoa_cv( (string) $t );
			if ( isset( $hop_le[ $k ] ) && ! in_array( $hop_le[ $k ], $sach, true ) ) {
				$sach[] = $hop_le[ $k ];
			}
		}

		$c   = self::cfg();
		$kcs = self::khoa_cs( $cs );
		if ( $sach ) {
			$c[ $kcs ]['phan'][ $kma ] = $sach;
		} else {
			unset( $c[ $kcs ]['phan'][ $kma ] );
			if ( empty( $c[ $kcs ]['phan'] ) ) { unset( $c[ $kcs ]['phan'] ); }
			if ( empty( $c[ $kcs ] ) ) { unset( $c[ $kcs ] ); }
		}
		VHCC_Luong::dat_cai_dat( self::O, $c, $u );
		return array( 'ok' => true, 'ds' => $sach );
	}

	/**
	 * Mấy loại giờ người ấy THỰC SỰ đã làm trong THÁNG TRƯỚC — bằng chứng thứ hai.
	 *
	 * Anh Thắng 18/09/2026: *"Theo kiểu tháng trước nhân viên đó có 2 loại giờ lương thì tháng
	 * sau nó sẽ hỏi"*.
	 *
	 * ⚠️ THÁNG DƯƠNG LỊCH TRƯỚC, không phải "30 ngày qua". Kỳ lương chốt theo tháng, nên một
	 *    người đổi việc giữa tháng thì đúng ngày mùng 1 hệ mới nên đổi cách hỏi họ — chứ không
	 *    phải trượt dần mỗi ngày một ít.
	 */
	public static function thang_truoc_da_lam( $coso, $ma_nv ) {
		global $wpdb;
		$cs = VHCC_NhanSu::chuan_coso( $coso );
		$ma = trim( (string) $ma_nv );
		if ( '' === $cs || '' === $ma ) { return array(); }
		$tt = gmdate( 'Y-m', strtotime( substr( (string) current_time( 'Y-m-d' ), 0, 7 )
			. '-01 00:00:00 UTC' ) - 86400 );
		$ra = array();
		foreach ( (array) VHCC_DB::rows( $wpdb->prepare(
			'SELECT DISTINCT loai_gio FROM ' . VHCC_DB::t( 'cham_cong' )
			. " WHERE LOWER(coso)=LOWER(%s) AND UPPER(ma_nv)=UPPER(%s) AND ngay LIKE %s"
			. " AND loai_gio<>''",
			$cs, $ma, $tt . '-%' ) ) as $r ) {
			$t = trim( (string) $r['loai_gio'] );
			if ( '' !== $t && ! in_array( $t, $ra, true ) ) { $ra[] = $t; }
		}
		return $ra;
	}

	/**
	 * MẤY LOẠI GIỜ MỘT NGƯỜI ĐƯỢC BẤM CHỌN.
	 *
	 * ═════════════════════════════════════════════════════════════════════════════════════════
	 * 🔴 KHÔNG PHẢI CẢ BẢNG ĐƠN GIÁ CỦA CƠ SỞ — ĐÓ LÀ BẢN CŨ, VÀ NÓ SAI
	 * ═════════════════════════════════════════════════════════════════════════════════════════
	 * Bản 4.60.0 hỏi mọi người ở cơ sở nào có từ hai dòng đơn giá. Anh Thắng 18/09/2026 chốt
	 * lại: *"những nhân viên 1 giờ nghĩ không nên hỏi tránh cập nhật nhầm hoặc gian lận"*.
	 *
	 * Câu ấy đúng theo cả hai nghĩa, và nghĩa thứ hai mới đắt:
	 *   · CẬP NHẬT NHẦM — người cả đời chỉ đứng quầy, mỗi ca lại bị hỏi "bạn làm việc gì", ba
	 *     lựa chọn xếp cạnh nhau. Bấm trượt một lần là một ca ăn giá của việc khác, và không có
	 *     gì kêu lên cả.
	 *   · GIAN LẬN — bày ra cho họ đúng cái nút để tự nâng đơn giá ca của mình. Trước đó việc
	 *     ấy cần cửa hàng trưởng; bản cũ phát cho cả cửa hàng.
	 *
	 * Nên phải có BẰNG CHỨNG rằng người này thật sự làm nhiều loại việc. Hai bằng chứng, và chỉ
	 * hai:
	 *   1. CỬA HÀNG TRƯỞNG PHÂN (`dat_phan`) — *"mới được phân thì cht sẽ set"*. Đây là đường
	 *      cho người mới: không có quá khứ nào để tra, phải có người chịu trách nhiệm nói ra.
	 *   2. THÁNG TRƯỚC HỌ ĐÃ LÀM ĐỦ HAI LOẠI — *"tháng trước nhân viên đó có 2 loại giờ lương
	 *      thì tháng sau nó sẽ hỏi"*. Việc đã diễn ra rồi thì không ai bịa ra được nữa.
	 *
	 * ⚠️ PHÂN THẮNG LỊCH SỬ, KHÔNG CỘNG VÀO. Cửa hàng trưởng rút một việc khỏi danh sách của ai
	 *    đó là họ đang nói "người này thôi làm việc ấy" — cộng thêm lịch sử tháng trước vào là
	 *    lệnh rút ấy không có tác dụng gì suốt cả tháng sau.
	 *
	 * ⚠️ LỌC LẠI QUA BẢNG ĐƠN GIÁ. Việc đã phân mà sau đó bị xoá khỏi bảng giá thì thôi bày ra:
	 *    chọn nó là ăn 0đ.
	 * ═════════════════════════════════════════════════════════════════════════════════════════
	 */
	public static function ds_viec( $coso, $ma_nv, $so = null ) {
		$co_gia = array();
		foreach ( self::ds_gia( $coso, $ma_nv, $so ) as $x ) { $co_gia[ $x['khoa'] ] = $x; }

		$ten = self::phan( $coso, $ma_nv );
		if ( ! $ten ) { $ten = self::thang_truoc_da_lam( $coso, $ma_nv ); }

		$ra = array();
		foreach ( $ten as $t ) {
			$k = VHCC_GiaGio::khoa_cv( $t );
			if ( isset( $co_gia[ $k ] ) && ! isset( $ra[ $k ] ) ) { $ra[ $k ] = $co_gia[ $k ]; }
		}
		$ra = array_values( $ra );
		usort( $ra, function ( $a, $b ) { return strcasecmp( $a['ten'], $b['ten'] ); } );
		return $ra;
	}

	/**
	 * Có hỏi người này lúc kết ca không.
	 *
	 * 🔴 CHỈ HỎI KHI CÓ TỪ HAI LỰA CHỌN TRỞ LÊN. Một lựa chọn thì câu hỏi không có nội dung —
	 *    mà mỗi ca lại chặn người ta thêm một cú bấm, và cú bấm vô nghĩa nào rồi cũng thành
	 *    phản xạ bấm bừa. Từ 4.61.0 "hai lựa chọn" là hai lựa chọn CỦA CHÍNH NGƯỜI ẤY — xem
	 *    khối chú thích ở `ds_viec()`.
	 */
	public static function hoi_khi_ra( $coso, $ma_nv, $cfg = null, $so = null ) {
		if ( ! self::bat_ket_ca( $coso, $cfg ) ) { return false; }
		return count( self::ds_viec( $coso, $ma_nv, $so ) ) >= 2;
	}

	/** Tên việc người ta gửi lên có nằm trong danh sách được chọn không. '' = bỏ khai. */
	public static function viec_hop_le( $coso, $ma_nv, $viec, $so = null ) {
		$v = trim( (string) $viec );
		if ( '' === $v ) { return ''; }
		$kv = VHCC_GiaGio::khoa_cv( $v );
		foreach ( self::ds_viec( $coso, $ma_nv, $so ) as $x ) {
			/* So bằng KHOÁ, trả về TÊN. Người ta gửi lên "lai tau" thì vẫn khớp dòng "Lái Tàu",
			   và thứ lưu xuống là cách viết chuẩn trong sổ — không phải cách họ gõ. */
			if ( $x['khoa'] === $kv ) { return $x['ten']; }
		}
		return null;
	}

	/* ====================================================================== ghi lúc kết ca */

	/**
	 * Ghi loại giờ cho một lượt chấm — đường KẾT CA, không qua duyệt.
	 *
	 * ⚠️ CHỈ GHI ĐÈ KHI Ô ĐANG TRỐNG. Đã khai rồi mà kết ca lần nữa vẫn ghi đè thì một cú bấm
	 *    nhầm ở trạm xoá mất lượt duyệt của cửa hàng trưởng. Đổi cái đã khai thì đi đường `gui()`.
	 */
	public static function dat_khi_ra( $coso, $ngay, $ma_nv, $hau_to, $viec ) {
		global $wpdb;
		$cs = VHCC_NhanSu::chuan_coso( $coso );
		$ng = trim( (string) $ngay );
		$ma = trim( (string) $ma_nv );
		/* Mã trạm gửi lên có thể kèm hậu tố ca (`MA-CD`) — cùng chuỗi mà `ghi_gio()` vừa ghi.
		   Tách đúng bằng hàm của nó chứ đừng cắt theo dấu gạch ở đây: MÃ NV CỦA CHUỖI NÀY CÓ
		   DẤU GẠCH (`TAM-FZLTVT-008`), và cắt sai là ghi loại giờ vào một hàng không tồn tại. */
		if ( class_exists( 'VHCC_Nhan' ) && method_exists( 'VHCC_Nhan', 'tach_hau_to' ) ) {
			list( $ma_g, $ht_g ) = VHCC_Nhan::tach_hau_to( $ma );
			$ma = $ma_g;
			if ( '' === trim( (string) $hau_to ) ) { $hau_to = $ht_g; }
		}
		if ( '' === $cs || '' === $ng || '' === $ma ) {
			return array( 'ok' => false, 'error' => 'Thiếu cơ sở, ngày hoặc mã nhân viên.' );
		}
		if ( ! self::bat_ket_ca( $cs ) ) {
			return array( 'ok' => false, 'error' => 'Cơ sở này chưa bật khai loại giờ lương.' );
		}
		$ten = self::viec_hop_le( $cs, $ma, $viec );
		if ( null === $ten ) {
			return array( 'ok' => false,
				'error' => 'Việc "' . trim( (string) $viec ) . '" không có trong danh sách đơn giá '
					. 'của cơ sở này. Tải lại trang rồi chọn lại.' );
		}
		if ( '' === $ten ) { return array( 'ok' => false, 'error' => 'Chưa chọn loại giờ.' ); }

		$bang = VHCC_DB::t( 'cham_cong' );
		$n = $wpdb->query( $wpdb->prepare(
			'UPDATE ' . $bang . ' SET loai_gio=%s'
			. " WHERE LOWER(coso)=LOWER(%s) AND ngay=%s AND UPPER(ma_nv)=UPPER(%s)"
			. " AND hau_to=%s AND loai_gio=''",
			$ten, $cs, $ng, $ma, (string) $hau_to ) );
		if ( ! $n ) {
			return array( 'ok' => false,
				'error' => 'Lượt chấm này đã khai loại giờ rồi (hoặc không tìm thấy). '
					. 'Muốn đổi thì vào tab Giờ công lương gửi cửa hàng trưởng duyệt.' );
		}
		return array( 'ok' => true, 'viec' => $ten );
	}

	/* ====================================================================== đọc của một người */

	/**
	 * Mấy ngày gần đây của một người, kèm loại giờ đã khai và đơn đang chờ — cho tab trên trạm.
	 *
	 * ⚠️ Đọc theo MÃ NV, không theo tên. Hai người trùng tên thì đọc theo tên là bày công của
	 *    người khác ra cho họ xem — và cho họ sửa.
	 */
	public static function ngay_cua_toi( $coso, $ma_nv, $so_ngay = 14 ) {
		global $wpdb;
		$cs = VHCC_NhanSu::chuan_coso( $coso );
		$ma = trim( (string) $ma_nv );
		if ( '' === $cs || '' === $ma ) { return array(); }
		$so_ngay = max( 1, min( self::NGAY_LUI_TOI_DA, (int) $so_ngay ) );
		$tu = gmdate( 'Y-m-d', strtotime( (string) current_time( 'Y-m-d' ) . ' 00:00:00 UTC' )
			- ( $so_ngay - 1 ) * 86400 );

		$ds = VHCC_DB::rows( $wpdb->prepare(
			'SELECT ngay, hau_to, gio_vao_giay, gio_ra_giay, loai_gio FROM '
			. VHCC_DB::t( 'cham_cong' )
			. ' WHERE LOWER(coso)=LOWER(%s) AND UPPER(ma_nv)=UPPER(%s) AND ngay>=%s'
			. ' ORDER BY ngay DESC, hau_to ASC',
			$cs, $ma, $tu ) );

		/* Đơn đang chờ, tra theo ngày+hậu tố — để màn không mời người ta gửi lần thứ hai. */
		$cho = array();
		foreach ( (array) VHCC_DB::rows( $wpdb->prepare(
			'SELECT ngay, hau_to, viec FROM ' . VHCC_DB::t( 'don_loai_gio' )
			. ' WHERE LOWER(coso)=LOWER(%s) AND UPPER(ma_nv)=UPPER(%s) AND trang_thai=%s',
			$cs, $ma, self::CHO ) ) as $d ) {
			$cho[ $d['ngay'] . '|' . (string) $d['hau_to'] ] = (string) $d['viec'];
		}

		$ra = array();
		foreach ( (array) $ds as $r ) {
			$v = ( null !== $r['gio_vao_giay'] && '' !== $r['gio_vao_giay'] ) ? (int) $r['gio_vao_giay'] : null;
			$o = ( null !== $r['gio_ra_giay'] && '' !== $r['gio_ra_giay'] ) ? (int) $r['gio_ra_giay'] : null;
			$k = $r['ngay'] . '|' . (string) $r['hau_to'];
			$ra[] = array(
				'ngay'   => (string) $r['ngay'],
				'hauTo'  => (string) $r['hau_to'],
				'vao'    => ( null !== $v ) ? VHCC_DB::hhmm( $v ) : '',
				'ra'     => ( null !== $o ) ? VHCC_DB::hhmm( $o ) : '',
				'gio'    => ( ( null !== $v && null !== $o && $o > $v ) ? round( ( $o - $v ) / 3600, 2 ) : null ),
				'viec'   => (string) $r['loai_gio'],
				'dangCho' => isset( $cho[ $k ] ) ? $cho[ $k ] : '',
			);
		}
		return $ra;
	}

	/* ====================================================================== đơn xin đổi */

	/** '' = gửi được; khác rỗng = câu chối. */
	public static function vi_sao_khong_gui( $coso, $ma_nv, $ngay ) {
		$cs = VHCC_NhanSu::chuan_coso( $coso );
		if ( '' === $cs ) { return 'Thiếu cơ sở.'; }
		if ( ! self::bat_tab( $cs ) ) {
			return 'Cơ sở này chưa bật tab Giờ công lương.';
		}
		/* Gác lại ở CỬA GHI, không chỉ ở chỗ vẽ ô. Giấu ô đi chỉ là không mời; một lượt POST
		   dựng tay vẫn tới đây. */
		if ( count( self::ds_viec( $cs, $ma_nv ) ) < 2 ) {
			return 'Anh/chị mới được phân một loại giờ lương nên không có gì để đổi. '
				. 'Làm thêm việc khác thì báo cửa hàng trưởng phân thêm.';
		}
		$ng = trim( (string) $ngay );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $ng ) ) { return 'Ngày không hợp lệ.'; }
		$hom_nay = (string) current_time( 'Y-m-d' );
		if ( $ng > $hom_nay ) { return 'Chưa tới ngày ' . $ng . '.'; }
		$lui = (int) round( ( strtotime( $hom_nay . ' 00:00:00 UTC' )
			- strtotime( $ng . ' 00:00:00 UTC' ) ) / 86400 );
		if ( $lui > self::NGAY_LUI_TOI_DA ) {
			return 'Ngày ' . $ng . ' đã quá ' . self::NGAY_LUI_TOI_DA . ' ngày — tháng ấy chốt '
				. 'lương rồi. Cần sửa thì báo cửa hàng trưởng.';
		}
		return '';
	}

	/**
	 * Nhân viên gửi xin đặt / đổi loại giờ cho mấy ngày cũ.
	 *
	 * @param array $ds [ [ 'ngay' => '2026-09-17', 'hauTo' => '', 'viec' => 'MC' ], … ]
	 */
	public static function gui( $toi, $coso, $ds, $ly_do = '' ) {
		global $wpdb;
		$cs = VHCC_NhanSu::chuan_coso( $coso );
		$ma = trim( (string) ( isset( $toi['ma_nv'] ) ? $toi['ma_nv'] : '' ) );
		if ( '' === $ma ) { return array( 'ok' => false, 'error' => 'Phiên này không mang mã nhân viên.' ); }

		$ds = array_values( (array) $ds );
		if ( ! $ds ) { return array( 'ok' => false, 'error' => 'Chưa chọn ngày nào.' ); }
		if ( count( $ds ) > 31 ) {
			return array( 'ok' => false, 'error' => 'Một lượt gửi tối đa 31 dòng.' );
		}

		/* Bảng công hiện tại của chính người ấy — để biết ô nào có thật và đang khai gì. */
		$dang = array();
		foreach ( self::ngay_cua_toi( $cs, $ma, self::NGAY_LUI_TOI_DA ) as $r ) {
			$dang[ $r['ngay'] . '|' . $r['hauTo'] ] = $r;
		}

		$them = array();
		$ten_toi = trim( (string) ( isset( $toi['name'] ) ? $toi['name'] : '' ) );
		foreach ( $ds as $d ) {
			$ng = trim( (string) ( isset( $d['ngay'] ) ? $d['ngay'] : '' ) );
			$ht = trim( (string) ( isset( $d['hauTo'] ) ? $d['hauTo'] : '' ) );
			$chan = self::vi_sao_khong_gui( $cs, $ma, $ng );
			if ( '' !== $chan ) { return array( 'ok' => false, 'error' => $chan ); }

			$k = $ng . '|' . $ht;
			if ( ! isset( $dang[ $k ] ) ) {
				return array( 'ok' => false,
					'error' => 'Ngày ' . $ng . ' không có lượt chấm công nào của anh/chị. '
						. 'Thiếu giờ thì làm đơn Xin bù giờ trước.' );
			}
			$ten_viec = self::viec_hop_le( $cs, $ma, isset( $d['viec'] ) ? $d['viec'] : '' );
			if ( null === $ten_viec || '' === $ten_viec ) {
				return array( 'ok' => false,
					'error' => 'Ngày ' . $ng . ' chưa chọn loại giờ, hoặc chọn một việc không có '
						. 'trong bảng đơn giá của cơ sở.' );
			}
			/* Đang khai đúng cái ấy rồi thì bỏ qua — gửi một đơn không đổi gì là bắt cửa hàng
			   trưởng đọc một dòng vô nghĩa, và mấy dòng vô nghĩa là thứ làm người ta duyệt bừa. */
			if ( $ten_viec === $dang[ $k ]['viec'] ) { continue; }
			$them[ $k ] = array( 'ngay' => $ng, 'hauTo' => $ht, 'viec' => $ten_viec,
				'cu' => $dang[ $k ]['viec'] );
		}

		if ( ! $them ) {
			return array( 'ok' => false,
				'error' => 'Mọi dòng đã khai đúng loại giờ ấy rồi — không có gì để duyệt.' );
		}

		$ly = mb_substr( trim( (string) $ly_do ), 0, 250 );
		$luc = current_time( 'mysql' );
		$so = 0;
		foreach ( $them as $x ) {
			/* Gửi lại cho cùng một ô thì lượt chờ cũ thành "không duyệt" — hai đơn cùng chờ cho
			   một ô là cửa hàng trưởng duyệt nhầm cái cũ. */
			$wpdb->update( VHCC_DB::t( 'don_loai_gio' ),
				array( 'trang_thai' => self::TU_CHOI,
					'ly_do_choi' => 'Người gửi thay bằng lượt mới.', 'duyet_luc' => $luc ),
				array( 'coso' => $cs, 'ngay' => $x['ngay'], 'ma_nv' => $ma,
					'hau_to' => $x['hauTo'], 'trang_thai' => self::CHO ) );
			$wpdb->insert( VHCC_DB::t( 'don_loai_gio' ), array(
				'coso' => $cs, 'ngay' => $x['ngay'], 'ma_nv' => $ma, 'hau_to' => $x['hauTo'],
				'ho_ten' => $ten_toi, 'viec_cu' => $x['cu'], 'viec' => $x['viec'],
				'ly_do' => $ly, 'trang_thai' => self::CHO, 'gui_luc' => $luc ) );
			$so++;
		}
		self::bao_cht( $cs, $ma, $ten_toi, $so );
		return array( 'ok' => true, 'so' => $so );
	}

	/** Đơn đang chờ của một cơ sở — màn của cửa hàng trưởng. */
	public static function ds_cho( $u, $coso, $so = 100 ) {
		global $wpdb;
		$cs = VHCC_NhanSu::chuan_coso( $coso );
		if ( '' === $cs || ! VHCC_Vai::duoc( $u, self::QUYEN_DUYET )
			|| ! VHCC_NhanSu::co_quyen_coso( $u, $cs ) ) {
			return array();
		}
		return (array) VHCC_DB::rows( $wpdb->prepare(
			'SELECT * FROM ' . VHCC_DB::t( 'don_loai_gio' )
			. ' WHERE LOWER(coso)=LOWER(%s) AND trang_thai=%s ORDER BY ngay DESC, id DESC LIMIT %d',
			$cs, self::CHO, max( 1, min( 300, (int) $so ) ) ) );
	}

	public static function mot( $id ) {
		global $wpdb;
		$id = (int) $id;
		if ( $id <= 0 ) { return null; }
		$r = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . VHCC_DB::t( 'don_loai_gio' ) . ' WHERE id=%d', $id ), ARRAY_A );
		return $r ? $r : null;
	}

	/**
	 * CỬA HÀNG TRƯỞNG DUYỆT HOẶC CHỐI.
	 *
	 * ⚠️ KHÔNG AI TỰ DUYỆT ĐƠN CỦA CHÍNH MÌNH, kể cả cửa hàng trưởng — cùng luật với
	 *    `VHCC_XinBu::duyet_cht()`. Cửa hàng trưởng cũng đi làm ca và cũng có loại giờ; tự duyệt
	 *    là tự chọn đơn giá cho mình.
	 */
	public static function duyet( $u, $id, $dong_y = true, $ly_do_choi = '' ) {
		global $wpdb;
		$don = self::mot( $id );
		if ( ! $don ) { return array( 'ok' => false, 'error' => 'Không thấy đơn này.' ); }
		if ( self::CHO !== (string) $don['trang_thai'] ) {
			return array( 'ok' => false, 'error' => 'Đơn này đã xử rồi. Tải lại trang.' );
		}
		if ( ! VHCC_Vai::duoc( $u, self::QUYEN_DUYET ) ) {
			return array( 'ok' => false,
				'error' => VHCC_Vai::loi( $u, self::QUYEN_DUYET, 'Duyệt loại giờ lương' ) );
		}
		if ( ! VHCC_NhanSu::co_quyen_coso( $u, $don['coso'] ) ) {
			return array( 'ok' => false, 'error' => 'Không có quyền trên cơ sở này.' );
		}
		$ma_u = strtoupper( trim( (string) ( isset( $u['ma_nv'] ) ? $u['ma_nv'] : '' ) ) );
		if ( '' !== $ma_u && $ma_u === strtoupper( trim( (string) $don['ma_nv'] ) ) ) {
			return array( 'ok' => false,
				'error' => 'Không tự duyệt đơn của chính mình — nhờ quản lý hoặc kế toán duyệt.' );
		}

		$ai = array(
			'ma_nv_duyet' => isset( $u['ma_nv'] ) ? (string) $u['ma_nv'] : '',
			'ten_duyet'   => isset( $u['name'] ) ? (string) $u['name'] : '',
			'duyet_luc'   => current_time( 'mysql' ),
		);

		if ( ! $dong_y ) {
			$ly = trim( (string) $ly_do_choi );
			if ( mb_strlen( $ly, 'UTF-8' ) < 5 ) {
				return array( 'ok' => false,
					'error' => 'Không duyệt thì phải nói vì sao — người gửi còn biết đường sửa.' );
			}
			$wpdb->update( VHCC_DB::t( 'don_loai_gio' ),
				array_merge( $ai, array( 'trang_thai' => self::TU_CHOI,
					'ly_do_choi' => mb_substr( $ly, 0, 250 ) ) ),
				array( 'id' => (int) $don['id'] ) );
			self::bao_nguoi_gui( $don, false, $ly );
			return array( 'ok' => true, 'quyet' => self::TU_CHOI );
		}

		/* 🔴 GHI THEO ĐÚNG Ô, KHÔNG THEO `ma_nv` TRẦN. Khoá của bảng chấm công là bộ bốn
		   (cơ sở, ngày, mã, hậu tố) — thiếu hậu tố thì đơn của ca ngày đổi luôn ca đêm. */
		$n = $wpdb->query( $wpdb->prepare(
			'UPDATE ' . VHCC_DB::t( 'cham_cong' ) . ' SET loai_gio=%s'
			. ' WHERE LOWER(coso)=LOWER(%s) AND ngay=%s AND UPPER(ma_nv)=UPPER(%s) AND hau_to=%s',
			(string) $don['viec'], (string) $don['coso'], (string) $don['ngay'],
			(string) $don['ma_nv'], (string) $don['hau_to'] ) );
		if ( ! $n ) {
			return array( 'ok' => false,
				'error' => 'Không còn lượt chấm công nào khớp đơn này — có thể đã bị xoá hoặc '
					. 'đổi cơ sở. Chối đơn đi cho gọn.' );
		}
		$wpdb->update( VHCC_DB::t( 'don_loai_gio' ),
			array_merge( $ai, array( 'trang_thai' => self::DUYET ) ),
			array( 'id' => (int) $don['id'] ) );
		self::bao_nguoi_gui( $don, true, '' );
		return array( 'ok' => true, 'quyet' => self::DUYET, 'viec' => (string) $don['viec'] );
	}

	/* ====================================================================== cộng vào lương */

	/**
	 * TỔNG GIỜ THEO LOẠI, của một cơ sở trong một tháng.
	 *
	 * 🔴 CHỈ KỂ LƯỢT ĐÃ KHAI. Lượt để trống thuộc về giờ CHÍNH, và giờ chính thì
	 *    `VHCC_BangLuong` tự tính bằng `giờ tổng − tổng giờ khác` — đúng luật anh Thắng chốt
	 *    16/09/2026. Kể cả lượt trống ở đây là đếm hai lần.
	 *
	 * @return array [ ma_nv_thường => [ 'Tên việc' => giờ ] ]
	 */
	public static function gio_theo_viec( $coso, $thang ) {
		global $wpdb;
		$cs = VHCC_NhanSu::chuan_coso( $coso );
		$tt = VHCC_Luong::tien_to_thang( $thang );
		if ( '' === $cs || '' === $tt ) { return array(); }

		$ds = VHCC_DB::rows( $wpdb->prepare(
			'SELECT ma_nv, loai_gio, gio_vao_giay, gio_ra_giay FROM ' . VHCC_DB::t( 'cham_cong' )
			. " WHERE LOWER(coso)=LOWER(%s) AND ngay LIKE %s AND loai_gio<>''",
			$cs, $tt . '-%' ) );

		$ra = array();
		foreach ( (array) $ds as $r ) {
			if ( null === $r['gio_vao_giay'] || '' === $r['gio_vao_giay']
				|| null === $r['gio_ra_giay'] || '' === $r['gio_ra_giay'] ) {
				continue;                      // thiếu một đầu thì không ra được số giờ nào
			}
			$g = (int) $r['gio_ra_giay'] - (int) $r['gio_vao_giay'];
			if ( $g <= 0 ) { continue; }
			$ma = strtolower( trim( (string) $r['ma_nv'] ) );
			$v  = trim( (string) $r['loai_gio'] );
			if ( '' === $ma || '' === $v ) { continue; }
			if ( ! isset( $ra[ $ma ][ $v ] ) ) { $ra[ $ma ][ $v ] = 0.0; }
			$ra[ $ma ][ $v ] += $g / 3600;
		}
		foreach ( $ra as $ma => $bang ) {
			foreach ( $bang as $v => $g ) { $ra[ $ma ][ $v ] = round( $g, 2 ); }
		}
		return $ra;
	}

	/* ====================================================================== chuông */

	/**
	 * ⚠️ Gác `class_exists` + `method_exists` CÙNG HÀM với lời gọi — luật của
	 *    `tools/test/kiem-goi-cheo.php`. Chưa cài plugin nội bộ thì lời gọi im lặng trôi qua.
	 */
	private static function bao_cht( $cs, $ma_gui, $ten_gui, $so ) {
		if ( ! class_exists( 'VHCC_Chuong' ) || ! method_exists( 'VHCC_Chuong', 'bao' ) ) { return; }
		$chu = $ten_gui . ' (' . $ma_gui . ') xin đổi loại giờ lương cho ' . (int) $so
			. ' ngày — chờ duyệt ở màn Đơn từ.';
		foreach ( self::ai_cht( $cs ) as $ma_cht ) {
			/* Người gửi mà cũng là cửa hàng trưởng thì đừng rung chuông của chính họ — họ vừa
			   bấm gửi xong, và `duyet()` đằng nào cũng không cho họ tự duyệt. */
			if ( strtoupper( (string) $ma_cht ) === strtoupper( (string) $ma_gui ) ) { continue; }
			VHCC_Chuong::bao( $ma_cht, $chu, 'cc_loaigio:' . $cs, (string) $ma_gui );
		}
	}

	/**
	 * Mã NV của những người duyệt được ở cơ sở này.
	 *
	 * ⚠️ HỎI TỪNG NGƯỜI BẰNG CHÍNH `VHCC_Vai::duoc()`, không tự dựng lại luật bậc ở đây — cùng
	 *    lý do đã ghi ở `VHCC_TuanCong::ai_duyet()`: chép lại luật là hai bản luật.
	 */
	private static function ai_cht( $coso ) {
		global $wpdb;
		$cs = VHCC_NhanSu::chuan_coso( $coso );
		$ra = array();
		foreach ( (array) $wpdb->get_results( $wpdb->prepare(
			'SELECT ma_nv, ho_ten, vai_tro, cua_hang FROM ' . VHCC_DB::t( 'nhan_vien' )
			. " WHERE ma_nv <> '' AND LOWER(cua_hang)=LOWER(%s)"
			. " AND trang_thai_lam_viec NOT IN ('Nghỉ việc','Nghỉ hẳn')", $cs ), ARRAY_A ) as $r ) {
			$nguoi = array( 'ma_nv' => (string) $r['ma_nv'], 'name' => (string) $r['ho_ten'],
				'role' => (string) $r['vai_tro'], 'coso' => (string) $r['cua_hang'] );
			if ( VHCC_Vai::duoc( $nguoi, self::QUYEN_DUYET ) ) { $ra[] = (string) $r['ma_nv']; }
		}
		return array_values( array_unique( $ra ) );
	}

	private static function bao_nguoi_gui( $don, $dong_y, $ly_do ) {
		if ( ! class_exists( 'VHCC_Chuong' ) || ! method_exists( 'VHCC_Chuong', 'bao' ) ) { return; }
		$ma = trim( (string) $don['ma_nv'] );
		if ( '' === $ma ) { return; }
		$chu = $dong_y
			? ( 'Loại giờ ngày ' . $don['ngay'] . ' đã được duyệt: ' . $don['viec'] . '.' )
			: ( 'Loại giờ ngày ' . $don['ngay'] . ' KHÔNG được duyệt. Lý do: ' . $ly_do );
		VHCC_Chuong::bao( $ma, $chu, 'cc_loaigio:' . (int) $don['id'], '' );
	}
}
