<?php
/**
 * SỔ ĐƠN GIÁ GIỜ CỦA CƠ SỞ — ba tầng: cả chuỗi · từng cơ sở · từng người.
 *
 * =============================================================================================
 * 🔴 VÌ SAO PHẢI CÓ SỔ NÀY
 * =============================================================================================
 * Anh Thắng 15/09/2026 gửi file `LƯƠNG CƠ SỞ - T08.2026`: *"mỗi cơ sở sẽ xuất bảng công giờ ra
 * theo mẫu file như này"*. Trong file có cột **Tiền/h** — 21.000 · 22.000 · 23.000 · 24.000 ·
 * 25.000 · 26.000 · 27.000 — mà hệ thống KHÔNG có lấy một chỗ nào chứa số ấy.
 *
 * Trước bản này, cơ sở thường (không phải Máy tự động, không phải Văn phòng) chạy qua
 * `VHCC_Luong::bang_cong_tho()` và trả về `coLuong => false`: chỉ có GIỜ, không có một đồng nào.
 * Muốn ra tiền thì phải có đơn giá, và đơn giá phải do người khai — không có cách nào suy ra.
 *
 * =============================================================================================
 * 🔴 BA TẦNG, VÀ VÌ SAO KHÔNG PHẢI MỘT
 * =============================================================================================
 * Đọc chính file của anh Thắng thì thấy cả ba tầng đều cần thật:
 *
 *   · Cùng CHỨC VỤ mà khác CƠ SỞ là khác giá — "NV" ở Aeon Tân Phú 22.000, ở Lotte Gò Vấp
 *     23.000. Nên không thể có một bảng duy nhất cho cả chuỗi.
 *   · Cùng CƠ SỞ mà khác CHỨC VỤ là khác giá — Aeon Bình Tân: Lái Tàu 23.000, Lơ Tàu 21.000.
 *     Nên khoá phải là (cơ sở × chức vụ), không phải chỉ cơ sở.
 *   · Vẫn có người lệch khỏi mức chung — Lotte Gò Vấp: ba người "NV" ăn 23.000, riêng Bùi Xuân
 *     Thuận 25.000. Bắt khai tay 240 người là không ai làm; bỏ hẳn đường đè riêng là tháng nào
 *     cũng phải sửa tay file Excel sau khi xuất, tức là xuất ra để đó.
 *
 * Thứ tự tra: **người → cơ sở**. Tầng dưới chỉ đỡ khi tầng trên chưa khai.
 *
 * 🔴 TẦNG "CẢ CHUỖI" ĐÃ BỎ (16/09/2026). Anh Thắng: *"mỗi cơ sở 1 mức giá lương khác nhau"*.
 *    Một bảng chung nghe thì tiện, nhưng nó âm thầm trả lời hộ một câu hỏi mà mỗi cửa hàng có
 *    một đáp án: cơ sở chưa ai ngồi khai giá vẫn ra tiền — ra bằng giá của cửa hàng khác — và
 *    bảng lương trông vẫn đầy đủ nên không ai đi kiểm. Nay chưa khai là TRỐNG và kêu lên.
 *
 * =============================================================================================
 * 🔴 CHƯA KHAI THÌ TRẢ 0 VÀ NÓI RA, TUYỆT ĐỐI KHÔNG ĐOÁN
 * =============================================================================================
 * Cùng một luật với `ngayCongThang` của khối Văn phòng (xem `the_thieu_khai()`): **hệ thống
 * KHÔNG đoán đơn giá**. Đoán là sai tiền của cả một nhóm người cùng lúc, mà bảng vẫn đầy số nên
 * chẳng ai nghi — và tới lúc phát hiện thì lương đã trả rồi.
 *
 * Nên `tra()` trả về cả `tu` (giá này đến từ tầng nào), và `khong` nghĩa là CHƯA KHAI. Chỗ dựng
 * bảng phải đếm số dòng `khong` rồi nói thẳng ra màn, chứ không lặng lẽ nhân với 0.
 *
 * ⚠️ KHOÁ CHỨC VỤ BỎ DẤU, BỎ HOA THƯỜNG (`VHCC_Luong::bo_chu`). Hồ sơ do người gõ tay nên cùng
 *    một việc có đủ kiểu viết — "Lái Tàu", "lái tàu", "LAI TAU", "Lái tàu ". Khoá theo chuỗi
 *    thô là khai một kiểu rồi tra kiểu khác không thấy, và người khai đinh ninh mình khai rồi.
 *    Tên hiển thị giữ nguyên bản người gõ trong nhánh `ten` — khoá để TRA, tên để ĐỌC.
 *    Xem `ten_cua()`; trước 16/09/2026 nhánh ấy chưa có nên bảng đọc bằng khoá.
 *
 * =============================================================================================
 * 🔴 XEM VÀ SỬA LÀ HAI CỬA
 * =============================================================================================
 * `QUYEN` (sửa) ở bậc Quản lý; `QUYEN_XEM` ở bậc Cửa hàng trưởng. Anh Thắng 16/09/2026:
 * *"Kế toán, quản lý chỉnh sửa được, còn cửa hàng trưởng chỉ xem được"*.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_GiaGio {

	/** Khoá trong bảng `cai_dat`. */
	const O = 'GIA_GIO_COSO';

	/**
	 * SỬA đơn giá — bậc Quản lý trở lên.
	 *
	 * 🔴 KHÔNG ĐỨNG CHUNG CỬA VỚI `luong` (bậc Kế toán) NỮA. Anh Thắng 16/09/2026: *"Kế toán,
	 *    quản lý chỉnh sửa được, còn cửa hàng trưởng chỉ xem được"*. Quản lý là người đi chốt
	 *    giá với từng cửa hàng; bắt họ nhờ kế toán gõ hộ là thêm một người trung gian vào đúng
	 *    chỗ dễ gõ nhầm nhất. Kế toán (bậc 4) và Admin (5) vẫn qua bằng bậc.
	 */
	const QUYEN = 'gia_gio';

	/**
	 * XEM đơn giá — bậc Cửa hàng trưởng.
	 *
	 * 🔴 XEM VÀ SỬA LÀ HAI CỬA KHÁC NHAU. Cửa hàng trưởng phải đối chiếu được lương của người
	 *    mình quản, mà không thấy đơn giá thì bảng lương chỉ là một cột tiền không giải thích
	 *    được. Cho xem thì họ soát hộ — giá khai nhầm một nghìn đồng là cả cơ sở sai cả tháng,
	 *    và người phát hiện sớm nhất chính là người đứng ở cửa hàng.
	 *
	 * ⚠️ XEM Ở ĐÂY VẪN CÒN MỘT CHỐT NỮA: phạm vi cơ sở (`co_quyen_coso`). Khối vẽ ra phải tự
	 *    hỏi, vì hằng số này không biết người xem đang mở cơ sở nào.
	 */
	const QUYEN_XEM = 'cong_coso';

	/** Trần vô lý: trên mức này gần như chắc chắn là gõ dư số 0. */
	const TRAN = 2000000;

	/* ==================================================================== đọc sổ */

	/**
	 * Cả sổ, đã chuẩn hoá về đúng ba nhánh.
	 *
	 * ⚠️ ĐỌC KHÔNG ĐƯỢC GHI. Bản đầu của mấy sổ khác từng vừa đọc vừa `update_option` để "gieo
	 *    mặc định" — làm mọi bài soát chỉ-đọc thành nói dối, và một lượt xem bảng lương lại sửa
	 *    cấu hình. Ở đây chỉ đọc, thiếu thì trả nhánh rỗng.
	 */
	public static function so() {
		$d = VHCC_Luong::cai_dat( self::O, null );
		/* Nhánh `chung` vẫn đọc vào — dữ liệu cũ của người ta nằm im ở đó, `tra()` không dùng
		   tới nữa nhưng `chung_cu()` còn kể ra để khai lại. Xem chú thích đầu tệp. */
		$o = array( 'chung' => array(), 'coso' => array(), 'nguoi' => array(), 'ten' => array() );
		if ( ! is_array( $d ) ) { return $o; }
		foreach ( array( 'chung', 'coso', 'nguoi', 'ten' ) as $nhanh ) {
			if ( isset( $d[ $nhanh ] ) && is_array( $d[ $nhanh ] ) ) { $o[ $nhanh ] = $d[ $nhanh ]; }
		}
		return $o;
	}

	/** Khoá tra của một chức vụ: bỏ dấu, bỏ hoa thường, bỏ khoảng trắng. */
	public static function khoa_cv( $cv ) {
		return VHCC_Luong::bo_chu( (string) $cv );
	}

	/**
	 * TÊN ĐỌC ĐƯỢC CỦA MỘT KHOÁ CHỨC VỤ.
	 *
	 * 🔴 BẢN TRƯỚC VẼ THẲNG CÁI KHOÁ RA MÀN. Chú thích đầu tệp hứa *"tên hiển thị vẫn giữ nguyên
	 *    bản người gõ — khoá để TRA, tên để ĐỌC"*, nhưng `sach_bang()` chỉ giữ lại khoá rồi vứt
	 *    cách viết gốc đi. Anh Thắng 16/09/2026 gõ "Lái tàu · Lơ tàu · CHT", lưu xong mở lại thì
	 *    bảng đọc là "laitau · lotau · cht". Một lời hứa trong chú thích mà mã không giữ thì còn
	 *    tệ hơn không hứa: người đọc mã sau tin là đã có, không ai đi kiểm lại.
	 *
	 * Khoá cũ lưu trước bản này thì chưa có tên — trả lại chính khoá, đừng trả rỗng: một ô tên
	 * trống thì người ta tưởng mất dòng và gõ đè lên bằng một dòng mới.
	 */
	public static function ten_cua( $khoa, $so = null ) {
		$so = ( null === $so ) ? self::so() : $so;
		$k  = (string) $khoa;
		return ( isset( $so['ten'][ $k ] ) && '' !== trim( (string) $so['ten'][ $k ] ) )
			? (string) $so['ten'][ $k ] : $k;
	}

	/** Khoá tra của một cơ sở — cùng phép chuẩn hoá, để `CS_` và hoa thường không đẻ ra hai sổ. */
	public static function khoa_cs( $coso ) {
		return VHCC_Luong::bo_chu( preg_replace( '/^CS_/i', '', trim( (string) $coso ) ) );
	}

	/** Khoá tra của một mã NV. */
	public static function khoa_ma( $ma ) {
		return strtolower( trim( (string) $ma ) );
	}

	/**
	 * Đơn giá giờ của (cơ sở, chức vụ, người).
	 *
	 * @return array('gia' => float, 'tu' => 'nguoi'|'coso'|'chung'|'khong')
	 */
	public static function tra( $coso, $chuc_vu, $ma_nv = '', $so = null ) {
		$so = ( null === $so ) ? self::so() : $so;
		$kcv = self::khoa_cv( $chuc_vu );
		$kcs = self::khoa_cs( $coso );
		$kma = self::khoa_ma( $ma_nv );

		/* Tầng 1 — riêng người. `*` = mọi chức vụ của người ấy; khoá chức vụ cụ thể thắng `*`. */
		if ( '' !== $kma && isset( $so['nguoi'][ $kma ] ) && is_array( $so['nguoi'][ $kma ] ) ) {
			$n = $so['nguoi'][ $kma ];
			if ( '' !== $kcv && isset( $n[ $kcv ] ) && (float) $n[ $kcv ] > 0 ) {
				return array( 'gia' => (float) $n[ $kcv ], 'tu' => 'nguoi' );
			}
			if ( isset( $n['*'] ) && (float) $n['*'] > 0 ) {
				return array( 'gia' => (float) $n['*'], 'tu' => 'nguoi' );
			}
		}
		/* Tầng 2 — cơ sở × chức vụ. */
		if ( '' !== $kcs && '' !== $kcv
			&& isset( $so['coso'][ $kcs ][ $kcv ] ) && (float) $so['coso'][ $kcs ][ $kcv ] > 0 ) {
			return array( 'gia' => (float) $so['coso'][ $kcs ][ $kcv ], 'tu' => 'coso' );
		}
		/* 🔴 KHÔNG CÒN TẦNG "BẢNG CHUNG CẢ CHUỖI".
		   Anh Thắng 16/09/2026, trước ô chọn việc bày ba dòng đều mang nhãn `· bảng chung`:
		   *"mỗi cơ sở 1 mức giá lương khác nhau"*, và chốt: bỏ hẳn bảng chung, chưa khai là để
		   trống.
		   Tầng ấy nghe thì tiện — khai một lần cho cả chuỗi — nhưng nó âm thầm trả lời hộ một
		   câu hỏi mà mỗi cửa hàng có một đáp án. Cơ sở chưa ai ngồi khai giá vẫn ra tiền, ra
		   bằng giá của cửa hàng khác, và bảng lương trông vẫn đầy đủ nên không ai đi kiểm.
		   Nay chưa khai thì `khong` — bảng lương để TRỐNG và kêu lên. Xem chú thích đầu tệp. */
		return array( 'gia' => 0.0, 'tu' => 'khong' );
	}

	/**
	 * DANH SÁCH CHỨC VỤ ĐÃ KHAI ĐƠN GIÁ Ở MỘT CƠ SỞ — để ô "tên việc" chọn sẵn, khỏi gõ tay.
	 *
	 * =========================================================================================
	 * 🔴 VÌ SAO Ô "TÊN VIỆC" PHẢI LẤY TỪ ĐÂY
	 * =========================================================================================
	 * Anh Thắng 16/09/2026: *"Tên việc giờ chọn sẵn từ đơn giá"*.
	 *
	 * Ô ấy trước là ô gõ tay tự do. Mà tên việc gõ vào đó KHÔNG phải một cái nhãn — nó là KHOÁ
	 * TRA đơn giá. Gõ "Lơ tàu" trong khi sổ khai "Lơ Tàu " thì vẫn khớp (khoá bỏ dấu, bỏ hoa
	 * thường), nhưng gõ "Lơ" hay "Lơ tau " thiếu một chữ là dòng giờ ấy tra không ra giá và lặng
	 * lẽ thành 0đ — người gõ thì đinh ninh đã xong vì màn báo "đã lưu".
	 *
	 * Bày sẵn đúng những cái tên CÓ GIÁ thì cái bẫy ấy biến mất: không còn chỗ để gõ lệch.
	 *
	 * Gộp hai tầng — bảng riêng của cơ sở và bảng chung cả chuỗi — vì cả hai đều tra ra giá
	 * thật. Trùng khoá thì tầng cơ sở thắng, đúng thứ tự `tra()`.
	 *
	 * ⚠️ KHÔNG gộp tầng "khai riêng người": giá của một người không phải một LOẠI VIỆC của cửa
	 *    hàng, bày nó vào danh sách chung là mời người ta gán giờ của người này theo tên người kia.
	 */
	public static function ten_khai_cho( $coso, $so = null ) {
		$so  = ( null === $so ) ? self::so() : $so;
		$kcs = self::khoa_cs( $coso );
		$ra  = array();
		/* CHỈ bảng của chính cơ sở này — không còn tầng chung để gộp vào (16/09/2026). */
		$bang = ( isset( $so['coso'][ $kcs ] ) && is_array( $so['coso'][ $kcs ] ) )
			? $so['coso'][ $kcs ] : array();
		foreach ( $bang as $k => $v ) {
			if ( '*' === $k || (float) $v <= 0 ) { continue; }
			$ra[ $k ] = self::ten_cua( $k, $so );
		}
		natcasesort( $ra );
		return array_values( $ra );
	}

	/* ==================================================================== ghi sổ */

	/**
	 * Làm sạch một bảng {chức vụ => đơn giá}.
	 *
	 * ⚠️ Ô ĐỂ TRỐNG = XOÁ khỏi sổ, KHÔNG phải ghi 0. Ghi 0 thì tầng dưới bị chặn lại (0 không
	 *    phải "chưa khai" theo phép tra ở trên nếu mình nhận số 0), nên mức chung không đỡ được
	 *    nữa mà màn thì trông như đã xoá. Xoá hẳn khoá mới đúng nghĩa "thôi không khai riêng".
	 *
	 * @return array( 'ds' => array, 'ten' => array, 'loi' => array )  loi = những chức vụ gõ sai
	 */
	public static function sach_bang( $vao ) {
		$ds  = array();
		$ten = array();
		$loi = array();
		foreach ( (array) $vao as $cv => $gia ) {
			$k = ( '*' === $cv ) ? '*' : self::khoa_cv( $cv );
			if ( '' === $k ) { continue; }
			$s = trim( (string) $gia );
			if ( '' === $s ) { continue; }                       // trống = xoá
			$n = VHCC_NhanSu::so_tien( $s );
			if ( $n <= 0 || $n > self::TRAN ) { $loi[] = (string) $cv; continue; }
			$ds[ $k ] = (float) $n;
			/* Giữ CÁCH VIẾT GỐC để còn đọc được — xem `ten_cua()`. Khoá `*` không phải tên người
			   gõ mà là một quy ước của sổ, nên không ghi tên cho nó.

			   ⚠️ TÊN TRÙNG Y HỆT KHOÁ THÌ KHÔNG GHI. Có biểu mẫu gửi lên chính cái khoá làm khoá
			      mảng (bảng chung ở màn Cấu hình), nên nhận bừa là ghi đè "Lái tàu" thành
			      "laitau" — đúng cái lỗi bản này đang đi sửa, chỉ là lần này do mình tự gây.
			      Ghi tên trùng khoá cũng chẳng thêm gì: `ten_cua()` đã trả lại khoá khi thiếu. */
			$tho = trim( (string) $cv );
			if ( '*' !== $k && '' !== $tho && $tho !== $k ) { $ten[ $k ] = $tho; }
		}
		return array( 'ds' => $ds, 'ten' => $ten, 'loi' => $loi );
	}

	/**
	 * Gộp tên mới vào sổ tên, KHÔNG ghi đè bằng mảng rỗng.
	 *
	 * ⚠️ Sổ tên dùng CHUNG cho cả ba tầng. Lưu bảng của một cơ sở mà thay sạch sổ tên là xoá tên
	 *    những chức vụ chỉ có ở cơ sở khác — bảng của họ lại quay về đọc bằng khoá.
	 */
	private static function gop_ten( $so, $ten ) {
		if ( ! isset( $so['ten'] ) || ! is_array( $so['ten'] ) ) { $so['ten'] = array(); }
		foreach ( (array) $ten as $k => $v ) { $so['ten'][ $k ] = (string) $v; }
		return $so;
	}

	private static function ghi( $u, $so ) {
		return VHCC_Luong::dat_cai_dat( self::O, $so, $u );
	}

	private static function gac( $u ) {
		if ( ! VHCC_Vai::duoc( $u, self::QUYEN ) ) {
			return VHCC_Vai::loi( $u, self::QUYEN, 'Khai đơn giá giờ' )
				. ' Đơn giá quyết định tiền của cả cơ sở nên chỉ Quản lý và Kế toán sửa được.'
				. ' Cửa hàng trưởng vẫn XEM được bảng giá của cơ sở mình ngay dưới bảng lương —'
				. ' thấy số sai thì báo lên, đừng sửa lén ở chỗ khác.';
		}
		return '';
	}

	/**
	 * BẢNG CHUNG CẢ CHUỖI ĐÃ BỎ — 16/09/2026.
	 *
	 * 🔴 GIỮ HÀM ĐỂ CHỐI THẲNG, KHÔNG XOÁ HẲN. Xoá thì mọi lối gọi còn sót lại thành lỗi chí
	 *    mạng giữa lúc người ta đang chốt lương; còn để nó ghi tiếp thì tầng vừa bỏ lại sống
	 *    ngầm trong sổ và không ai biết. Chối thẳng là cách duy nhất vừa không gãy vừa không
	 *    âm thầm.
	 */
	public static function dat_chung( $u, $bang ) {
		return array( 'ok' => false, 'error' => 'Bảng đơn giá chung cả chuỗi đã bỏ — mỗi cơ sở '
			. 'khai đơn giá của riêng mình. Khai ở khối Đơn giá giờ ngay dưới bảng lương của '
			. 'cơ sở ấy.' );
	}

	/**
	 * Mấy dòng còn sót trong nhánh `chung` của sổ cũ — CHỈ để màn kể ra cho người ta khai lại.
	 *
	 * ⚠️ KHÔNG XOÁ DỮ LIỆU CỦA NGƯỜI TA. Bỏ một tầng là việc của mã; xoá con số người ta đã gõ
	 *    là việc khác hẳn. Nhánh ấy nằm im trong sổ, `tra()` không đọc tới nữa, và màn bày ra
	 *    để biết mà gõ lại cho từng cơ sở.
	 */
	public static function chung_cu( $so = null ) {
		$so = ( null === $so ) ? self::so() : $so;
		$ra = array();
		foreach ( (array) ( isset( $so['chung'] ) ? $so['chung'] : array() ) as $k => $v ) {
			if ( '*' === $k || (float) $v <= 0 ) { continue; }
			$ra[ self::ten_cua( $k, $so ) ] = (float) $v;
		}
		return $ra;
	}

	public static function dat_coso( $u, $coso, $bang ) {
		$chan = self::gac( $u );
		if ( '' !== $chan ) { return array( 'ok' => false, 'error' => $chan ); }
		$kcs = self::khoa_cs( $coso );
		if ( '' === $kcs ) { return array( 'ok' => false, 'error' => 'Thiếu cơ sở.' ); }
		$r = self::sach_bang( $bang );
		if ( $r['loi'] ) {
			return array( 'ok' => false, 'error' => 'Đơn giá phải là số dương và dưới '
				. number_format( self::TRAN, 0, ',', '.' ) . 'đ/giờ — sai ở: '
				. implode( ', ', $r['loi'] ) . '.' );
		}
		$so = self::so();
		if ( $r['ds'] ) { $so['coso'][ $kcs ] = $r['ds']; }
		else            { unset( $so['coso'][ $kcs ] ); }
		$so = self::gop_ten( $so, $r['ten'] );
		self::ghi( $u, $so );
		return array( 'ok' => true, 'so' => count( $r['ds'] ) );
	}

	public static function dat_nguoi( $u, $ma_nv, $bang ) {
		$chan = self::gac( $u );
		if ( '' !== $chan ) { return array( 'ok' => false, 'error' => $chan ); }
		$kma = self::khoa_ma( $ma_nv );
		if ( '' === $kma ) { return array( 'ok' => false, 'error' => 'Thiếu mã nhân viên.' ); }
		$r = self::sach_bang( $bang );
		if ( $r['loi'] ) {
			return array( 'ok' => false, 'error' => 'Đơn giá phải là số dương và dưới '
				. number_format( self::TRAN, 0, ',', '.' ) . 'đ/giờ — sai ở: '
				. implode( ', ', $r['loi'] ) . '.' );
		}
		$so = self::so();
		if ( $r['ds'] ) { $so['nguoi'][ $kma ] = $r['ds']; }
		else            { unset( $so['nguoi'][ $kma ] ); }
		$so = self::gop_ten( $so, $r['ten'] );
		self::ghi( $u, $so );
		return array( 'ok' => true, 'so' => count( $r['ds'] ) );
	}
}
