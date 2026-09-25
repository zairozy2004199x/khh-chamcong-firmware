<?php
/**
 * XUẤT MISA — 4 luồng (Đơn vận hành · Kỹ thuật · Marketing · Công tác/Setup),
 * cùng 10 cột theo mẫu import "Chứng từ nghiệp vụ khác" của MISA.
 * Giao diện tự đổ ra CSV/XLSX nên PHP chỉ trả cols + rows như app cũ.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCPMTD_Misa {

	/* ══════════════════════════════════════════════════════════════════════════════════════════
	 * HAI MẪU XUẤT, MỘT LƯỢT GOM.
	 *
	 * Anh Thắng 21/09/2026 gửi ảnh *"Mẫu xuất Misa MTĐ và VP"* — 13 cột, dạng SỔ CHI TIẾT TÀI
	 * KHOẢN: có `TK đối ứng`, `Phát sinh Nợ` / `Phát sinh Có` tách đôi, thêm `Dư Nợ` / `Dư Có`
	 * và `Tên đơn vị`. Khác hẳn mẫu 10 cột đang chạy, vốn là dạng NHẬT KÝ CHUNG (TK Nợ và TK Có
	 * nằm cạnh nhau trên cùng một dòng, số tiền một cột).
	 *
	 * 🔴 KHÔNG VIẾT HÀM XUẤT THỨ HAI. Cả hai mẫu dùng CHUNG toàn bộ phần khó: chốt TK Nợ (ma
	 *    trận loại × mảng), chốt TK đối ứng (`tkco_xuat`), mã đối tượng, mã đơn vị, cảnh báo
	 *    thiếu mã, gom theo mảng, sắp theo ngày. Chép đôi phần ấy là hai bản hạch toán sẽ trôi
	 *    lệch nhau — và cái lệch chỉ lộ ra khi hai tệp cùng nộp cho một kỳ.
	 *    Nên `$mau` chỉ đổi ĐÚNG hai thứ: danh sách cột, và cách xếp một dòng.
	 *
	 * ⚠️ MẶC ĐỊNH VẪN LÀ MẪU CŨ. Người gọi không truyền `$mau` thì nhận đúng tệp như trước —
	 *    thêm tham số mà đổi hình dạng tệp của người gọi cũ là kế toán nộp nhầm mẫu cho MISA.
	 * ══════════════════════════════════════════════════════════════════════════════════════════ */
	const MAU_CHUAN = 'chuan';   // nhật ký chung — 10 cột, mẫu đang chạy của Khu vui chơi
	const MAU_SOCT  = 'soct';    // sổ chi tiết tài khoản — 13 cột, mẫu MTĐ / VP

	/**
	 * `$gop_tk` = true khi bản xuất TRỘN nhiều tài khoản chi phí — lúc ấy thêm cột `TK Nợ` ở đầu.
	 *
	 * 🔴 SỔ CHI TIẾT LÀ SỔ **CỦA MỘT TÀI KHOẢN**, nên 13 cột mẫu không có chỗ nào ghi số hiệu tài
	 *    khoản: nó nằm ở tiêu đề sổ. Đúng với một lượt xuất đã lọc về một tài khoản. Nhưng nếu
	 *    lượt xuất ôm cả 64136 lẫn 6427 thì tệp ra TRỘN CHUNG mà không phân biệt được dòng nào
	 *    của tài khoản nào — người nhận đọc xong cộng nhầm, và không có gì trên tệp báo cho họ.
	 *
	 * ⚠️ HAI HÌNH DẠNG, MỖI CÁI ĐÚNG TRONG CẢNH CỦA NÓ — không chọn bừa một cái:
	 *      · lọc về MỘT tài khoản  -> đúng 13 cột, khớp nguyên văn mẫu MISA anh Thắng gửi;
	 *      · để "mọi TK Nợ"        -> 13 cột ấy + cột `TK Nợ` ở đầu, tệp tự nói nó gồm những gì.
	 *    Cột thêm đứng ĐẦU chứ không chèn giữa: người quen mẫu cũ vẫn đọc được 13 cột sau nó
	 *    theo đúng thứ tự cũ.
	 */
	public static function cols( $mau = self::MAU_CHUAN, $gop_tk = false ) {
		if ( self::MAU_SOCT === $mau ) {
			$c = array( 'Ngày hạch toán', 'Ngày chứng từ', 'Số chứng từ', 'Diễn giải chung', 'Diễn giải',
				'TK đối ứng', 'Phát sinh Nợ', 'Phát sinh Có', 'Dư Nợ', 'Dư Có', 'Mã đối tượng', 'Mã đơn vị', 'Tên đơn vị' );
			if ( $gop_tk ) { array_unshift( $c, 'TK Nợ' ); }
			return $c;
		}
		return array( 'Ngày chứng từ (*)', 'Ngày hạch toán (*)', 'Số chứng từ (*)', 'Diễn giải', 'Diễn giải (Hạch toán)', 'TK Nợ (*)', 'TK Có (*)', 'Số tiền', 'Mã đối tượng Có', 'Mã đơn vị' );
	}

	/**
	 * Chốt mã tài khoản cho 1 dòng của các mảng Kỹ thuật / Marketing / Công tác-Setup.
	 *
	 * Dòng ĐÃ gắn loại chi phí -> lấy mã theo danh mục (Nợ = tài khoản chi phí, Có = 141/331
	 * theo hình thức chi). Dòng CHƯA gắn -> giữ đúng cách hạch toán cũ (Nợ 141 · Có 331 nếu
	 * trực tiếp NCC / Có 64125 nếu tạm ứng NV) để số liệu cũ xuất ra không đổi.
	 *
	 * @return array [tk_no, tk_co, ma_dt, legacy(bool)]
	 */
	public static function tk_mang( $loai_cp, $is_tt, $line_tk_no = '', $line_tk_co = '', $line_ma_dt = '', $coso = '' ) {
		$loai_cp = trim( (string) $loai_cp );
		$line_tk_no = trim( (string) $line_tk_no );
		if ( $loai_cp === '' && $line_tk_no === '' ) {
			return array( 'tk_no' => '141', 'tk_co' => $is_tt ? '331' : '64125', 'ma_dt' => '', 'legacy' => true );
		}
		$tk = VHCPMTD_Cfg::resolve_tk( $loai_cp, $is_tt ? 'Trực tiếp' : 'Tạm ứng', array(
			'tkCo' => trim( (string) $line_tk_co ),
			'maDt' => trim( (string) $line_ma_dt ),
		), $coso );
		// TK Nợ đi theo LUẬT LÚC XUẤT (xem VHCPMTD_Cfg::tkno_xuat) — chung với đơn vận hành
		// và sổ chi phí, để 5 đường xuất không mỗi đường một kiểu.
		$tk['tk_no'] = VHCPMTD_Cfg::tkno_xuat( $loai_cp, $coso, $line_tk_no );
		$tk['legacy'] = false;
		return $tk;
	}

	/**
	 * GOM CẢNH BÁO NGÀY VÔ LÝ.
	 *
	 * 169 dòng cùng dính một ngày hỏng thì in 169 dòng cảnh báo là ô vàng dài hơn cả bảng
	 * xuất, không đọc được gì. Gom theo GIÁ TRỊ ngày, đếm số dòng, kể tên vài đơn đầu.
	 *
	 * In kèm GIÁ TRỊ THÔ đang nằm trong máy: "22/08/4622" là bản đã định dạng, nhìn nó
	 * không biết trong cột ngày đang chứa cái gì. Ngày hỏng đồng loạt là lỗi lúc GHI chứ
	 * không phải người nhập gõ sai từng dòng, nên phải nhìn được giá trị gốc mới truy ra.
	 */
	public static function gom_ngay_xau( &$xau, $dmy, $raw, $ma = '' ) {
		if ( ! VHCPMTD_Util::ngay_vo_ly( $dmy ) ) { return; }
		$k = $dmy . '|' . (string) $raw;
		if ( ! isset( $xau[ $k ] ) ) { $xau[ $k ] = array( 'dmy' => $dmy, 'raw' => (string) $raw, 'n' => 0, 'don' => array() ); }
		$xau[ $k ]['n']++;
		if ( trim( (string) $ma ) !== '' ) { $xau[ $k ]['don'][ (string) $ma ] = 1; }
	}

	/** Đổi bảng gom ở trên thành các câu cảnh báo. */
	public static function warn_ngay_xau( $xau ) {
		$out = array();
		foreach ( $xau as $x ) {
			$ds  = array_keys( $x['don'] );
			$cau = 'Ngày vô lý "' . $x['dmy'] . '" — ' . $x['n'] . ' dòng';
			if ( count( $ds ) ) {
				$cau .= ' ở ' . count( $ds ) . ' đơn (' . implode( ', ', array_slice( $ds, 0, 5 ) ) . ( count( $ds ) > 5 ? ', …' : '' ) . ')';
			}
			$cau .= '. Giá trị đang lưu trong máy: "' . $x['raw'] . '". Sửa ngày của dòng chi rồi xuất lại.';
			$out[] = $cau;
		}
		return $out;
	}

	/** _cleanNhom(): bỏ đuôi "- NCC" / "- Mua lẻ" khỏi tên nhóm. */
	private static function clean_nhom( $nhom ) {
		return VHCPMTD_Cfg::bo_duoi_nhom( $nhom );
	}

	/* ════════════════════════════════════════════════════════════════════════
	 * DIỄN GIẢI — anh Thắng 24/09/2026 (ảnh sổ 641 thật của kế toán + bảng Cơ sở + bảng Loại):
	 * *"Cấu trúc có linh động 1 chút: Loại Chi Phí_Phân Loại Lớn_Tên Cơ Sở_Tháng T9/2026 hoặc
	 * Ngày từ … đến …"*. Sổ thật của kế toán ghi "Chi phí khác POSH MN AMBD T9/2026_Phí gửi da ghế".
	 *
	 * Ba việc, mỗi việc một hàm nhỏ để bài kiểm gọi thẳng:
	 *   · `ky_dien_giai()`  — kỳ là HAI LOẠI, tự tách theo từng đơn (anh Thắng 24/09/2026, ảnh
	 *                          "T9/2026 (21/9-27/9/2026)": *"Cái này chia ra 2 loại, chứ không phải
	 *                          ghép lại"*): khoảng đúng một tuần 7 ngày → "T9/2026"; khoảng tự chọn
	 *                          (3 ngày, hay kỳ thuê 26/8-25/9) → "ngày 26/8-25/9/2026". Sổ thật của kế
	 *                          toán ghi đúng như vậy trong CÙNG một đơn: "…VHM T9/2026" cạnh "…Kubo BMT
	 *                          ngày 26/8-25/9/2026". Bản 1.317.0 từng để một ô chọn chung cho cả tệp —
	 *                          sai ý, đã bỏ.
	 *   · `ten_coso_gon()`  — tên MISA của gian thường đã mang sẵn mảng ("POSH MN AEON MALL…"), mà
	 *                          diễn giải đã có mảng đứng trước → bỏ phần lặp, khỏi ra "POSH MN POSH MN".
	 *   · tên loại theo MISA — cột "Tên theo MISA" của bảng Loại chi phí (`ten_misa_loai`), trước
	 *                          đây chỉ luồng Marketing dùng, luồng đơn tuần bỏ quên.
	 * ════════════════════════════════════════════════════════════════════════ */
	/** Kỳ của đơn viết cho diễn giải — tuần chuẩn → "T9/2026"; khoảng tự chọn → "ngày a-b/Y".
	 *  Kỳ không có khoảng ngày (đơn cũ, đơn dự án, nhãn lạ) → trả nguyên văn. */
	public static function ky_dien_giai( $ky ) {
		$ky = trim( (string) $ky );
		if ( ! preg_match( '/^(.*?)\s*\(\s*([^()\-–]+?)\s*[\-–]\s*([^()]+?)\s*\)\s*$/u', $ky, $m ) ) { return $ky; }
		$nhan = trim( $m[1] ); $tu = trim( $m[2] ); $den = trim( $m[3] );
		return self::la_tuan_chuan( $ky ) && '' !== $nhan ? $nhan : ( 'ngày ' . $tu . '-' . $den );
	}

	/** Khoảng ngày của kỳ là một TUẦN: dài tối đa 7 ngày và bắt đầu thứ Hai hoặc kết thúc Chủ nhật.
	 *  Bắt cả tuần chuẩn 7 ngày (`VHCPMTD_Don::nhan_ky`) lẫn tuần cũ bị cắt theo tháng ("1/9-6/9/2026",
	 *  thứ Ba → Chủ nhật) — ảnh anh Thắng 24/09/2026 còn nguyên các đơn kiểu ấy. Khoảng tự chọn giữa
	 *  tuần (2/9-4/9) hay dài hơn tuần (kỳ thuê 26/8-25/9) thì không phải. */
	public static function la_tuan_chuan( $ky ) {
		list( $tu, $den ) = VHCPMTD_Don::khoang_ky( $ky );
		if ( '' === $tu || '' === $den ) { return false; }
		$a = strtotime( $tu . ' 00:00:00 UTC' ); $z = strtotime( $den . ' 00:00:00 UTC' );
		if ( ! $a || ! $z || $z < $a || ( $z - $a ) > 6 * 86400 ) { return false; }
		return ( 1 === (int) gmdate( 'N', $a ) ) || ( 7 === (int) gmdate( 'N', $z ) );
	}

	/** Tên gian cho diễn giải: tên MISA (hay tên gian), bỏ phần mở đầu trùng với mảng đứng trước nó. */
	public static function ten_coso_gon( $ten_misa, $pll ) {
		$t = trim( (string) $ten_misa ); $p = trim( (string) $pll );
		if ( '' === $t || '' === $p ) { return $t; }
		if ( 0 === mb_stripos( $t, $p ) ) {
			$con = trim( mb_substr( $t, mb_strlen( $p ) ), " \t-_·" );
			return '' !== $con ? $con : $t;
		}
		return $t;
	}

	/** exportMisa(): đơn vận hành. mode = chuaxuat|daxuat ; plF = all|cn|ncc. */
	public static function export_misa( $ky = 'all', $mode = 'chuaxuat', $pl_f = 'all', $mau = self::MAU_CHUAN, $tk_f = 'all', $mang_f = 'all' ) {
		$mau  = ( self::MAU_SOCT === $mau ) ? self::MAU_SOCT : self::MAU_CHUAN;
		$tk_f = VHCPMTD_Util::ma_so( trim( (string) $tk_f ) );
		if ( 'ALL' === mb_strtoupper( (string) $tk_f ) ) { $tk_f = ''; }
		/* 🔴 MẢNG KINH DOANH — anh Thắng 25/09/2026 (ảnh bảng 🧮 Loại chi phí × Mảng kinh doanh cạnh
		   màn Xuất MISA): *"Mỗi chi phí sẽ xuất ra 1 bảng misa riêng"*. Chọn một mảng ("Chi Phí Vận
		   Hành" · "Chi Phí Cơ Sở KVC"…) thì tệp CHỈ còn dòng của mảng đó — kế toán xuất mỗi mảng một
		   lượt, mỗi lượt một tệp riêng, thay vì một tệp trộn hết rồi tự lọc lại bằng Excel. */
		$mang_f = trim( (string) $mang_f );
		if ( '' === $mang_f || 'all' === mb_strtolower( $mang_f ) ) { $mang_f = ''; }
		$mode = $mode ? $mode : 'chuaxuat';
		$pl_f = $pl_f ? $pl_f : 'all';
		$cp   = VHCPMTD_Don::cp_rows();              // đọc 1 lần, dùng cho cả cấu hình đối tượng lẫn vòng lặp dưới
		$cfg  = VHCPMTD_Cfg::get_config( $cp );

		$m_unit = array(); $m_pll = array(); $m_tm = array();
		foreach ( $cfg['coso'] as $x ) { $m_unit[ $x['ten'] ] = $x['maDonVi']; $m_pll[ $x['ten'] ] = $x['phanLoaiLon']; $m_tm[ $x['ten'] ] = $x['tenMisa']; }
		$m_no = array();
		foreach ( $cfg['nhom'] as $x ) { $m_no[ $x['ten'] ] = $x['tkNo']; }
		$m_co = array();
		foreach ( $cfg['phanloai'] as $x ) { $m_co[ $x['ten'] ] = $x['tkCo']; }
		$m_dt = array();
		foreach ( $cfg['doiTuong'] as $x ) { $m_dt[ mb_strtolower( (string) $x['ten'] ) ] = $x['ma']; }
		$m_no_mx = array();
		foreach ( (array) $cfg['tkNoMatrix'] as $x ) { $m_no_mx[ trim( (string) $x['nhom'] ) . '|' . trim( (string) $x['pll'] ) ] = $x['tkNo']; }
		$m_loai = array();   // danh mục LOẠI CHI PHÍ -> TK Nợ
		foreach ( (array) ( isset( $cfg['loaiChiPhi'] ) ? $cfg['loaiChiPhi'] : array() ) as $x ) {
			if ( trim( (string) $x['tkNo'] ) === '' ) { continue; }
			/* 141/331 gieo từ bảng Nhóm cũ không phải TK Nợ — xem `VHCPMTD_Cfg::bo_ma_ben_tra_`. */
			if ( VHCPMTD_Cfg::la_tk_ben_tra( $x['tkNo'] ) ) { continue; }
			$m_loai[ mb_strtolower( trim( (string) $x['ten'] ) ) ] = (string) $x['tkNo'];
		}

		/* 🔴 ĐÃ BỎ BẢNG TRA "TK CÓ THEO NGƯỜI DUYỆT" (12/09/2026) — anh Thắng: *"Bỏ cột tài
		   khoản có"*. Cột ấy không còn trên màn Người dùng nên không còn chỗ nào khai; giữ
		   bảng tra lại là một giá trị ẩn trong sổ cũ vẫn lặng lẽ chi phối bút toán MISA mà
		   không ai xem hay sửa được nữa — đúng kiểu hỏng tệ nhất.
		   TK Có nay tra theo HÌNH THỨC CHI (141 tạm ứng cá nhân / 331 trả thẳng NCC), là thứ
		   đi theo đồng tiền chứ không theo người ký. */
		$m_dt_user = array(); $role_by = array();
		foreach ( VHCPMTD_Cfg::get_users() as $u ) {
			if ( $u['ten'] === '' ) { continue; }
			$k = mb_strtolower( trim( $u['ten'] ) );
			$m_dt_user[ $k ] = $u['maDt'];
			$role_by[ $k ]   = $u['vaiTro'];
		}
		$cf = array( 'pll' => $m_pll, 'loai' => $m_loai, 'no_mx' => $m_no_mx, 'no' => $m_no, 'co' => $m_co );

		// Gom đơn theo bộ phận + trạng thái xuất
		$by_don = array();
		foreach ( VHCPMTD_Don::don_rows() as $r ) {
			$ky2 = VHCPMTD_Util::fmt( $r['ky'] );
			if ( $ky && $ky !== 'all' && $ky2 !== $ky ) { continue; }
			$qt_cn  = ( (string) $r['nguoi_qt'] !== '' );
			$qt_ncc = ( (string) $r['nguoi_qt_ncc'] !== '' );
			$x_cn   = ( VHCPMTD_Util::fmt( $r['ngay_xuat_cn'] ) !== '' );
			$x_ncc  = ( VHCPMTD_Util::fmt( $r['ngay_xuat_ncc'] ) !== '' );
			if ( $pl_f === 'cn' )        { $take = $qt_cn  && ( $mode === 'daxuat' ? $x_cn  : ! $x_cn ); }
			elseif ( $pl_f === 'ncc' )   { $take = $qt_ncc && ( $mode === 'daxuat' ? $x_ncc : ! $x_ncc ); }
			else {
				/* 🔴 "SẴN SÀNG ĐỂ XUẤT" LÀ BƯỚC NGAY TRƯỚC `Đã xuất MISA` — TUỲ KHỐI, không gõ cứng.
				   Bên KVC đó là `Đã quyết toán`. Bên MTĐ/VP còn một bước `Đã thanh toán` chen vào
				   giữa (anh Thắng 21/09/2026: thanh toán và xuất MISA là *"hai bước tách rời"*),
				   nên gõ cứng là đơn MTĐ vừa duyệt quyết toán đã rơi vào bản xuất — tức xuất MISA
				   cho một khoản chưa trả tiền. */
				/* ⚠️ VÀ THEO LUỒNG CỦA CHÍNH ĐƠN (22/09/2026) — luồng trực tiếp cũng có bước
				   `Đã thanh toán`, dù khối của nó là khối đi tạm ứng. Hỏi mỗi khối là đơn trực
				   tiếp vừa duyệt quyết toán đã rơi vào bản xuất, tức xuất MISA cho một khoản
				   chưa trả tiền — đúng cái bẫy khối chú thích trên dựng lên để tránh. */
				$san = VHCPMTD_Don::tt_truoc_misa( isset( $r['khoi'] ) ? $r['khoi'] : '',
					VHCPMTD_Don::luong_don( $r ) );
				$take = ( $mode === 'daxuat' ? ( $r['trang_thai'] === 'Đã xuất MISA' ) : ( $r['trang_thai'] === $san ) );
			}
			if ( ! $take ) { continue; }
			/* 🔴 XUẤT MISA CŨNG PHẢI THEO ĐƠN VỊ. Đây là chỗ tiền ĐI RA sổ kế toán, nên hở ở
			   đây nặng hơn hở ở một màn xem: kế toán POSH bấm Xuất là tệp mang luôn đơn của
			   K&H sang bên họ, và ngược lại — hai công ty nộp chồng số của nhau.

			   Anh Thắng 08/09/2026 nói *"misa anh sẽ set sau"*, nhưng ý đó là MÃ TÀI KHOẢN.
			   Lọc theo đơn vị là chuyện phải làm bất kể mã đã khai hay chưa. */
			if ( ! VHCPMTD_DonVi::duoc_xem( isset( $r['don_vi'] ) ? $r['don_vi'] : '' ) ) { continue; }
			$ngay = VHCPMTD_Util::fmt( $r['ngay_qt'] );
			if ( $ngay === '' ) { $ngay = VHCPMTD_Util::fmt( $r['ngay_tao'] ); }
			$by_don[ (string) $r['ma_don'] ] = array(
				'ky'         => $ky2,
				'nguoiLap'   => (string) $r['nguoi_lap'],
				'nguoiDuyet' => (string) $r['nguoi_duyet'],
				'nguoiQT'    => (string) $r['nguoi_qt'],
				'nguoiQTNCC' => (string) $r['nguoi_qt_ncc'],
				'ngay'       => $ngay,
				/* Mảng của đơn — để bản xuất nói được "trong đây có bao nhiêu đơn của bên kia
				   bàn giao sang". Xem chốt ở `$theo_khoi` dưới. */
				'khoi'       => mb_strtolower( trim( (string) ( isset( $r['khoi'] ) ? $r['khoi'] : '' ) ) ),
				/* 🔴 ĐƠN ĐÃ XUẤT MISA → mã trên dòng là mã ĐÃ ĐÓNG, xem `ma_cua_dong()`. */
				'daXuat'     => ( 'Đã xuất MISA' === (string) $r['trang_thai'] ),
			);
		}

		$rows_by_nhom = array(); $nhom_order = array(); $warn = array(); $ndon = 0; $seen_don = array(); $ngay_xau = array();
		$tk_co_mat = array();   // những TK Nợ CÓ MẶT trong kỳ — để màn đổ ô lọc, khỏi đoán
		$mang_mat  = array();   // những MẢNG CÓ MẶT trong kỳ — để màn đổ ô lọc, khỏi đoán
		$tk_trong_tep = array();   // mã thật sự ra tệp SAU lọc — quyết định có thêm cột TK Nợ ở mẫu sổ chi tiết
		$stt_chen = 0;
		foreach ( $cp as $r ) {
			$m = (string) $r['ma_don'];
			if ( ! isset( $by_don[ $m ] ) ) { continue; }
			$d       = $by_don[ $m ];
			$coso    = (string) $r['coso'];
			/* 🔴 LỌC THEO MẢNG PHẢI ĐỨNG TRƯỚC $ndon++ — anh Thắng 25/09/2026: *"Mỗi chi phí sẽ xuất
			   ra 1 bảng misa riêng"*. `$seen_don`/`$ndon` là con số "Số đơn" bày trên màn; đứng SAU
			   sẽ đếm cả đơn của MẢNG KHÁC vào ô "Số đơn" của một tệp đã lọc còn đúng mảng mình chọn —
			   kế toán nhìn "Số đơn: 4" mà tệp bên dưới chỉ có 1 dòng, tưởng máy đếm sai.
			   Đếm `$mang_mat` TRƯỚC khi lọc (ô chọn không rớt mất lựa chọn khác), lọc NGAY sau đó —
			   cùng nguyên tắc với lọc TK Nợ, chỉ khác là TK Nợ phải tính SAU khi chốt mã (ma_cua_dong)
			   nên đứng muộn hơn trong vòng lặp; mảng thì biết ngay từ cơ sở, không cần chờ. */
			$pll     = isset( $m_pll[ $coso ] ) ? $m_pll[ $coso ] : '';
			$mang_k  = trim( (string) $pll );
			$mang_mat[ '' !== $mang_k ? $mang_k : '(chưa khai mảng)' ] = 1;
			if ( '' !== $mang_f && mb_strtolower( $mang_k ) !== mb_strtolower( $mang_f )
				&& ! ( '(chưa khai mảng)' === $mang_f && '' === $mang_k ) ) { continue; }
			$pltt    = (string) $r['phan_loai_tt'];
			$dt      = (string) $r['doi_tuong'];
			$nhom    = (string) $r['nhom'];
			$nd      = (string) $r['noi_dung'];
			$eff_ncc = VHCPMTD_Util::is_ncc( $pltt, $r['cn_xu_ly'] );
			if ( $pl_f === 'cn' && $eff_ncc ) { continue; }
			if ( $pl_f === 'ncc' && ! $eff_ncc ) { continue; }
			if ( ! isset( $seen_don[ $m ] ) ) { $seen_don[ $m ] = 1; $ndon++; }

			$tt     = VHCPMTD_Util::num( $r['thanh_tien'] );
			$tm     = VHCPMTD_Util::blank_or_num( $r['thuc_mua'] );
			$sotien = ( $tm === null ) ? $tt : $tm;
			if ( ! $sotien ) { continue; }

			$co_key    = $eff_ncc ? 'Nhà cung cấp' : $pltt;
			/* 🔴 TÊN VÀ MÃ ĐỐI TƯỢNG ĐI THEO NGƯỜI TẠO ĐƠN — anh Thắng 25/09/2026: *"Lấy tên theo người tạo, 1
			   cơ sở 2 bạn quản lý, cứ ai tạo thì hiện tên người đó là được"*. Trước lấy theo NGƯỜI DUYỆT: cùng
			   một quản lý duyệt cho cả chục đơn thì tệp MISA ghi một tên cho tiền của nhiều người khác nhau. */
			$lap_key   = mb_strtolower( trim( (string) $d['nguoiLap'] ) );

			/* Mã TK Nợ / TK Có của dòng — MỘT hàm cho cả lượt xuất lẫn lượt đóng mã (`mark_exported`).
			   Đơn đã xuất MISA đọc mã đã đóng trên dòng, không tra lại bảng mã (xem `ma_cua_dong`). */
			$ma_    = self::ma_cua_dong( $r, isset( $d['khoi'] ) ? $d['khoi'] : '', $cf, ! empty( $d['daXuat'] ) );
			$tk_no  = $ma_['tk_no'];
			$tk_co  = $ma_['tk_co'];
			$ma_dv = isset( $m_unit[ $coso ] ) ? $m_unit[ $coso ] : '';
			/* Dòng NCC → mã của nhà cung cấp ghi trên dòng (danh mục Đối tượng); dòng cá nhân → mã NV của
			   NGƯỜI TẠO ĐƠN; không có thì mới lui về đối tượng ghi trên dòng. */
			$ma_dt = '';
			$dt_ma = ! empty( $m_dt[ mb_strtolower( $dt ) ] ) ? $m_dt[ mb_strtolower( $dt ) ] : '';
			if ( $eff_ncc ) {
				$ma_dt = '' !== $dt_ma ? $dt_ma : ( ! empty( $m_dt_user[ $lap_key ] ) ? $m_dt_user[ $lap_key ] : '' );
			} else {
				$ma_dt = ! empty( $m_dt_user[ $lap_key ] ) ? $m_dt_user[ $lap_key ] : $dt_ma;
			}

			/* Lọc theo TK Nợ — đứng SAU lượt chốt mã (phải biết mã rồi mới lọc được), và TRƯỚC
			   mọi phép cộng, để con số "số đơn / số dòng" khớp đúng tệp bên dưới. */
			$tk_no_ms = VHCPMTD_Util::ma_so( $tk_no );
			if ( '' !== $tk_no_ms ) { $tk_co_mat[ $tk_no_ms ] = 1; }
			/* 🔴 LỌC THEO CÂY TÀI KHOẢN — anh Thắng 24/09/2026: *"tk nợ 3 số là cha của 4 số, ví dụ 641
			   là cha của 6412 và cháu là 64122, lọc 641 nó sẽ ra cả con và cháu"*. Mã con bắt đầu bằng
			   mã cha, nên lọc = so tiền tố. */
			if ( '' !== $tk_f && ! self::thuoc_cay_tk( $tk_no_ms, $tk_f ) ) { continue; }
			$tk_trong_tep[ $tk_no_ms ] = 1;

			if ( ! $tk_no ) { $warn[ 'Thiếu TK Nợ cho loại chi phí: ' . VHCPMTD_Cfg::bo_duoi_nhom( $nhom ) . ( $pll !== '' ? ' (mảng ' . $pll . ')' : '' ) . ' — khai ở ⚙️ Cấu hình → Loại chi phí' ] = 1; }
			/* Câu báo phải chỉ đúng CHỖ KHAI. Trước đây nó nói "thiếu TK Có cho người duyệt X"
			   và người ta đi sửa bảng Người dùng — nay cột ấy không còn, nên chỉ thẳng sang
			   bảng Phân loại thanh toán, là nơi duy nhất còn khai được. */
			if ( ! $tk_co ) { $warn[ 'Thiếu TK Có cho hình thức chi: ' . ( '' !== trim( (string) $co_key ) ? $co_key : '(trống)' ) . ' — khai ở ⚙️ Cấu hình → Phân loại thanh toán, hoặc khai TK đối ứng riêng cho loại "' . VHCPMTD_Cfg::bo_duoi_nhom( $nhom ) . '"' ] = 1; }
			if ( ! $ma_dv ) { $warn[ 'Thiếu Mã đơn vị cho cơ sở: ' . $coso ] = 1; }

			$ngay = VHCPMTD_Util::fmt( $r['ngay'] );
			if ( $ngay === '' ) { $ngay = $d['ngay']; }
			VHCPMTD_Misa::gom_ngay_xau( $ngay_xau, $ngay, $r['ngay'], $m );
			$nhom_c   = self::clean_nhom( $nhom );
			/* Cột "Tên theo MISA" của loại (bảng Loại chi phí) — khai thì diễn giải dùng tên ấy. */
			$nhom_dg  = VHCPMTD_Cfg::ten_misa_loai( $nhom_c );
			$ten_misa = ! empty( $m_tm[ $coso ] ) ? $m_tm[ $coso ] : $coso;
			$ky_dg    = self::ky_dien_giai( $d['ky'] );
			$ten1     = $d['nguoiLap'];   /* người TẠO đơn, xem chốt ở `$lap_key` */
			/* Loại _ Mảng _ [Cơ sở] _ Kỳ, rồi "_" + phần riêng (người duyệt ở diễn giải chung, nội dung ở
			   diễn giải hạch toán) — đúng cấu trúc anh Thắng chốt 24/09/2026. */
			$dg1      = VHCPMTD_Util::j( array( $nhom_dg, $pll, $ky_dg ) ) . ( $ten1 !== '' ? '_' . $ten1 : '' );
			$dg2      = VHCPMTD_Util::j( array( $nhom_dg, $pll, self::ten_coso_gon( $ten_misa, $pll ), $ky_dg ) ) . ( trim( $nd ) !== '' ? '_' . $nd : '' );

			/* 🔴 GOM THEO MẢNG KINH DOANH TRƯỚC, RỒI MỚI TỚI LOẠI CHI PHÍ (anh Thắng 07/09/2026:
			   *"Chỗ phần xuất misa. Sắp xếp theo cùng phân loại lớn"*).

			   Bản trước gom mỗi theo LOẠI chi phí, mà một loại ("Chi phí cơ sở") trải khắp mọi
			   mảng — nên tệp xuất ra xen kẽ FZ · GHOST · TUTU · VR · FZ… theo đúng thứ tự ngày.
			   Kế toán vào MISA thì soát theo TỪNG MẢNG, nên đang phải lọc lại bằng tay ở Excel.

			   ⚠️ MẢNG RỖNG XẾP CUỐI, không lẫn vào mảng có tên. Cơ sở chưa khai `phanLoaiLon` là
			      chuyện cần thấy chứ không phải chuyện cần giấu; dồn nó xuống đáy thì nhìn phát
			      ra ngay có bao nhiêu dòng chưa khai. */
			/* Hạng 0 = mảng có tên · hạng 1 = chưa khai. Nói ý định bằng MỘT CON SỐ, không bằng
			   mẹo đặt chuỗi 'zzz' cho nó rơi xuống cuối theo bảng chữ cái: mẹo ấy chỉ đúng
			   chừng nào chưa có mảng nào đặt tên bắt đầu bằng ký tự đứng sau 'z'. */
			$pll_k = trim( (string) $pll );
			$hang  = ( $pll_k === '' ) ? '1' : '0';
			if ( $pll_k === '' ) { $pll_k = '(chưa khai mảng)'; }
			$gk = $hang . '||' . $pll_k . '||' . ( $nhom_c !== '' ? $nhom_c : '(khác)' );
			if ( ! isset( $rows_by_nhom[ $gk ] ) ) { $rows_by_nhom[ $gk ] = array(); $nhom_order[] = $gk; }
			/* Giữ kèm khoá ngày + số thứ tự chèn để sắp xếp trong nhóm — xem khối dưới. */
			/* ══════════════════════════════════════════════════════════════════════════════
			 * XẾP MỘT DÒNG — chỗ DUY NHẤT hai mẫu khác nhau.
			 *
			 * Mẫu sổ chi tiết là sổ CỦA TÀI KHOẢN CHI PHÍ (TK Nợ): mỗi dòng là một lượt ghi
			 * Nợ vào tài khoản ấy, nên `Phát sinh Nợ` mang số tiền, `Phát sinh Có` bằng 0, và
			 * `TK đối ứng` là bên kia của bút toán — đúng "Nợ 64136 / Có 331" trong ảnh anh gửi.
			 *
			 * ⚠️ `Dư Nợ` / `Dư Có` ĐỂ TRỐNG, cố ý. Đó là số DƯ LUỸ KẾ của tài khoản, mà số dư
			 *    ấy phụ thuộc cả những bút toán KHÔNG do app này sinh ra (tiền về, bù trừ, kết
			 *    chuyển cuối kỳ). Tự cộng lấy ở đây là bịa ra một con số dư chỉ đúng nếu app
			 *    này là nguồn duy nhất của tài khoản — mà nó không phải. MISA tự tính lại khi
			 *    nạp; để trống là nói thật "chỗ này không phải việc của tôi".
			 * ⚠️ SỐ CHỨNG TỪ mang MÃ ĐƠN. Mẫu cũ để trống ô này (kế toán tự đánh số khi nạp),
			 *    nhưng sổ chi tiết thì dò ngược theo chứng từ là việc hằng ngày — không có mã
			 *    đơn thì một dòng lệch không truy được về đơn nào.
			 * ══════════════════════════════════════════════════════════════════════════════ */
			if ( self::MAU_SOCT === $mau ) {
				$r_out = array( $ngay, $ngay, $m, $dg1, $dg2,
					VHCPMTD_Util::ma_so( $tk_co ), $sotien, 0, '', '',
					VHCPMTD_Util::ma_so( $ma_dt ), VHCPMTD_Util::ma_so( $ma_dv ), $ten_misa );
			} else {
				$r_out = array( $ngay, $ngay, '', $dg1, $dg2,
					VHCPMTD_Util::ma_so( $tk_no ), VHCPMTD_Util::ma_so( $tk_co ), $sotien,
					VHCPMTD_Util::ma_so( $ma_dt ), VHCPMTD_Util::ma_so( $ma_dv ) );
			}
			$rows_by_nhom[ $gk ][] = array(
				'k'  => ( $_dt = VHCPMTD_Util::vh_parse_dmy( $ngay ) ) ? VHCPMTD_Util::vh_ymd( $_dt ) : 0,
				'i'  => $stt_chen++,
				'r'  => $r_out,
				'tk' => $tk_no_ms,
			);
		}

		/* Xếp các nhóm: MẢNG theo bảng chữ cái, rồi LOẠI CHI PHÍ theo bảng chữ cái. Trong mỗi
		   nhóm giữ nguyên thứ tự gặp — vốn là thứ tự ngày, thứ kế toán cần khi đối chiếu.

		   ⚠️ KHÔNG dùng `sort()` trần: `zzz|` chỉ là mẹo đẩy mảng rỗng xuống cuối, còn tên mảng
		      tiếng Việt phải so bằng `strcoll`-kiểu bản địa thì "GHOST" mới đứng trước "TUTU"
		      như mắt người đọc. `strnatcasecmp` đủ cho tên mảng (chữ Latin, có số). */
		usort( $nhom_order, function ( $a, $b ) { return strnatcasecmp( $a, $b ); } );

		/* 🔴 SỔ CHI TIẾT LÀ SỔ CỦA MỘT TÀI KHOẢN — thêm cột TK Nợ ở đầu khi tệp trộn NHIỀU mã: không
		   lọc, hoặc lọc theo mã CHA (641) mà bên dưới có 6412 · 64122. Lọc về một mã lá → đúng 13 cột. */
		$gop_tk = ( self::MAU_SOCT === $mau ) && ( '' === $tk_f || count( $tk_trong_tep ) > 1 );
		$rows = array();
		foreach ( $nhom_order as $g ) {
			/* ⚠️ TRONG MỖI NHÓM, XẾP THEO NGÀY. Vòng gom ở trên chạy theo thứ tự CHÈN của bảng
			   chi phí (số thứ tự dòng), không phải theo ngày — nên một đơn nhập muộn mà mang
			   ngày cũ sẽ nằm sai chỗ. Kế toán đối chiếu MISA theo ngày, nên chỗ này phải xếp
			   thật chứ không dựa vào việc "thường thì người ta nhập theo thứ tự thời gian".

			   Cùng ngày thì giữ nguyên thứ tự nhập (`i`) — `usort` của PHP không ổn định, nên
			   phải nói rõ, nếu không hai lần xuất cùng một dữ liệu ra hai tệp khác nhau. */
			usort( $rows_by_nhom[ $g ], function ( $a, $b ) {
				if ( $a['k'] !== $b['k'] ) { return $a['k'] - $b['k']; }
				return $a['i'] - $b['i'];
			} );
			foreach ( $rows_by_nhom[ $g ] as $x ) {
				$r_ = $x['r'];
				if ( $gop_tk ) { array_unshift( $r_, $x['tk'] ); }
				$rows[] = $r_;
			}
		}

		/* ═══════════════════════════════════════════════════════════════════════════════════
		   ĐẾM ĐƠN THEO MẢNG — để kế toán KVC biết tệp mình sắp xuất có bao nhiêu đơn của Máy
		   tự động bàn giao sang.

		   Anh Thắng 21/09/2026: *"kế toán máy tự động duyệt xong sẽ đẩy qua kế toán KVC tổng
		   kết"*. Bàn giao mà im lặng thì y như không bàn giao: kế toán KVC bấm Xuất, ra một tệp
		   nhiều hơn mọi khi vài chục dòng, và không biết vì sao. Con số này là chỗ duy nhất nói
		   ra điều đó — nó KHÔNG lọc gì cả, chỉ đếm.

		   ⚠️ ĐẾM TRÊN `$seen_don`, KHÔNG PHẢI `$by_don`: `$by_don` là mọi đơn ĐỦ ĐIỀU KIỆN, còn
		      `$seen_don` mới là đơn THẬT SỰ có dòng trong tệp này (đơn không còn dòng nào sau
		      bộ lọc CN/NCC thì rơi ra). Đếm nhầm vế là con số không khớp với chính tệp bên dưới.
		   ═══════════════════════════════════════════════════════════════════════════════════ */
		$theo_khoi = array();
		foreach ( array_keys( $seen_don ) as $m ) {
			$k = isset( $by_don[ $m ]['khoi'] ) ? $by_don[ $m ]['khoi'] : '';
			if ( '' === $k ) { $k = '(chưa rõ)'; }
			$theo_khoi[ $k ] = ( isset( $theo_khoi[ $k ] ) ? $theo_khoi[ $k ] : 0 ) + 1;
		}

		/* ⚠️ ÉP VỀ CHUỖI. Khoá mảng PHP tự đổi chuỗi số canonical thành SỐ NGUYÊN ('6427' -> 6427),
		   nên `array_keys()` trả về một mảng lẫn kiểu. Hai hệ quả, cái sau nặng hơn:
		     · màn so `o.value` (chuỗi) với mã — lẫn kiểu là phép so hụt;
		     · mã kế toán hoàn toàn có thể mang số 0 đứng đầu, và một lượt ép số là mất nó.
		   Ép ở đây, một chỗ, thay vì bắt mọi người đọc phải nhớ. */
		$tk_ds = array_map( 'strval', array_keys( $tk_co_mat ) );
		sort( $tk_ds, SORT_NATURAL );
		/* '(chưa khai mảng)' luôn cuối danh sách — mảng có tên đứng trước, cùng luật hiển thị với
		   nhóm dòng ở trên (`$hang`). */
		$mang_ds = array_keys( $mang_mat );
		usort( $mang_ds, function ( $a, $b ) {
			if ( ( '(chưa khai mảng)' === $a ) !== ( '(chưa khai mảng)' === $b ) ) { return '(chưa khai mảng)' === $a ? 1 : -1; }
			return strnatcasecmp( $a, $b );
		} );
		return array( 'cols' => self::cols( $mau, $gop_tk ),
			'mau' => $mau, 'tkLoc' => $tk_f, 'tkDs' => $tk_ds, 'tkCay' => self::cay_tk( $tk_ds ),
			'mangLoc' => ( '' !== $mang_f ? $mang_f : 'all' ), 'mangDs' => $mang_ds,
			'rows' => $rows, 'count' => count( $rows ), 'sodon' => $ndon,
			'theoKhoi' => $theo_khoi,
			'warn' => array_merge( array_keys( $warn ), VHCPMTD_Misa::warn_ngay_xau( $ngay_xau ) ), 'maDons' => array_keys( $seen_don ) );
	}

	/** exportMisaKyThuat(): dự án Kỹ thuật đã duyệt / đã đóng + Chi phí cơ sở chung. */
	public static function export_ky_thuat() {
		$cfg = VHCPMTD_Cfg::cfg_static();   // chỉ cần danh mục cơ sở -> khỏi đọc bảng ChiPhi để gộp đối tượng
		$m_unit = array(); $m_pll = array(); $m_tm = array();
		foreach ( $cfg['coso'] as $x ) { $m_unit[ $x['ten'] ] = $x['maDonVi']; $m_pll[ $x['ten'] ] = $x['phanLoaiLon']; $m_tm[ $x['ten'] ] = $x['tenMisa']; }
		$user_dt = array();
		foreach ( VHCPMTD_Cfg::get_users() as $u ) {
			if ( $u['ten'] === '' ) { continue; }
			$user_dt[ mb_strtolower( trim( $u['ten'] ) ) ] = $u['maDt'];
		}

		$pay_map = VHCPMTD_DuAn::pay_map();
		$app_map = VHCPMTD_DuAn::approve_date_map();

		$rows = array(); $warn = array(); $nda = 0; $ngay_xau = array();
		foreach ( VHCPMTD_DuAn::all_with_lines() as $r ) {
			$st   = (string) $r['trang_thai'];
			$loai = (string) $r['loai'];
			if ( $st !== 'Đã duyệt' && $st !== 'Đã đóng' && $loai !== 'Chi phí cơ sở' ) { continue; }
			$ma_da  = (string) $r['ma_da'];
			$ten_da = (string) $r['ten'];
			$pay    = isset( $pay_map[ $ma_da ] ) ? $pay_map[ $ma_da ] : array();
			$ngay   = isset( $app_map[ $ma_da ] ) ? $app_map[ $ma_da ] : '';
			if ( $ngay === '' ) { $ngay = VHCPMTD_Util::fmt( $r['ngay_tao'] ); }
			VHCPMTD_Misa::gom_ngay_xau( $ngay_xau, $ngay, $r['ngay_tao'], $ma_da );
			$ma_dv    = isset( $m_unit[ $ten_da ] ) ? $m_unit[ $ten_da ] : '';
			$pll      = isset( $m_pll[ $ten_da ] ) ? $m_pll[ $ten_da ] : '';
			$ten_misa = ! empty( $m_tm[ $ten_da ] ) ? $m_tm[ $ten_da ] : $ten_da;

			$child = array(); $parent_ht = array();
			foreach ( $r['lines'] as $x ) {
				$cap = trim( (string) $x['cap_cha'] );
				if ( $cap !== '' && $cap !== '(Phát sinh)' ) { $child[ $cap ] = ( isset( $child[ $cap ] ) ? $child[ $cap ] : 0 ) + VHCPMTD_Util::num( $x['thuc_te'] ); }
				if ( $cap === '' ) { $parent_ht[ trim( (string) $x['noi_dung'] ) ] = trim( (string) $x['hinh_thuc'] ); }
			}

			$used = false;
			foreach ( $r['lines'] as $x ) {
				$nd0     = trim( (string) $x['noi_dung'] );
				$cap     = trim( (string) $x['cap_cha'] );
				$thuc_te = VHCPMTD_Util::num( $x['thuc_te'] );
				if ( $nd0 === '' && ! $thuc_te ) { continue; }
				if ( $cap === '' && isset( $child[ $nd0 ] ) && $child[ $nd0 ] > 0 ) { continue; }
				if ( ! $thuc_te ) { continue; }

				$ht = ( $cap !== '' && $cap !== '(Phát sinh)' )
					? ( ! empty( $parent_ht[ $cap ] ) ? $parent_ht[ $cap ] : trim( (string) $x['hinh_thuc'] ) )
					: trim( (string) $x['hinh_thuc'] );
				$is_tt   = ( $ht === 'Trực tiếp' );
				$hang_muc = ( $cap === '' || $cap === '(Phát sinh)' ) ? $nd0 : $cap;
				$noi_dung = ( $cap === '' || $cap === '(Phát sinh)' ) ? '' : $nd0;
				$ghichu   = trim( (string) $x['note'] );

				// Mã tài khoản theo LOẠI CHI PHÍ của dòng; dòng chưa gắn loại thì giữ đúng cách cũ.
				// (mục con thừa hưởng hình thức chi của hạng mục cha -> $is_tt đã tính ở trên)
				$tkm   = self::tk_mang( isset( $x['loai_cp'] ) ? $x['loai_cp'] : '', $is_tt, isset( $x['tk_no'] ) ? $x['tk_no'] : '', '', isset( $x['ma_dt'] ) ? $x['ma_dt'] : '', isset( $x['gian'] ) ? (string) $x['gian'] : '' );
				$tk_no = $tkm['tk_no'];
				$tk_co = $tkm['tk_co'];
				$ma_dt = $tkm['ma_dt'];
				if ( $tk_no === '' ) { $warn[ 'Thiếu TK Nợ cho loại chi phí: ' . trim( (string) $x['loai_cp'] ) . ' — khai ở ⚙️ Cấu hình → Loại chi phí' ] = 1; }
				if ( ! empty( $tkm['legacy'] ) && ! $is_tt ) {
					/* Tạm ứng nay là DANH SÁCH nhiều lần (10/09/2026) — đọc thẳng ['by'] thì với dự
					   án dùng tính năng mới ra rỗng, và tệp MISA ghi tên người tạo thay vì người
					   chi. Lấy lần ĐẦU: đó là người ứng đầu tiên, đúng thứ tệp xuất cần. */
					$_ung    = VHCPMTD_DuAn::pay_ds( $pay, 'tamUng', 'tu' );
					$by_name = ! empty( $_ung[0]['by'] ) ? $_ung[0]['by'] : (string) $r['nguoi_tao'];
					$bk      = mb_strtolower( trim( (string) $by_name ) );
					$ma_dt   = isset( $user_dt[ $bk ] ) ? $user_dt[ $bk ] : '';
				}
				if ( ! $ma_dv ) { $warn[ 'Thiếu Mã đơn vị cho cơ sở/dự án: ' . $ten_da . ' (thêm ở ⚙️ Cấu hình cơ sở)' ] = 1; }

				$dg1 = VHCPMTD_Util::j( array( $ten_da, $loai, $pll ) ) . ( $is_tt ? '_Trực tiếp NCC' : '_Tạm ứng NV' );
				$dg2 = VHCPMTD_Util::j( array( $hang_muc, $ten_misa ) );
				$tail = VHCPMTD_Util::j( array( $noi_dung, $ghichu ) );
				if ( $tail !== '' ) { $dg2 .= '_' . $tail; }

				$rows[] = array( $ngay, $ngay, '', $dg1, $dg2, VHCPMTD_Util::ma_so( $tk_no ), VHCPMTD_Util::ma_so( $tk_co ), $thuc_te, VHCPMTD_Util::ma_so( $ma_dt ), VHCPMTD_Util::ma_so( $ma_dv ) );
				$used   = true;
			}
			if ( $used ) { $nda++; }
		}
		return array( 'cols' => self::cols(), 'rows' => $rows, 'count' => count( $rows ), 'sodon' => $nda,
			'warn' => array_merge( array_keys( $warn ), VHCPMTD_Misa::warn_ngay_xau( $ngay_xau ) ), 'maDons' => array() );
	}

	/** exportMisaMarketing(): mỗi khoản có thực chi = 1 dòng hạch toán. */
	public static function export_marketing() {
		$cfg = VHCPMTD_Cfg::cfg_static();
		$m_unit = array(); $m_tm = array();
		foreach ( $cfg['coso'] as $x ) { $m_unit[ $x['ten'] ] = $x['maDonVi']; $m_tm[ $x['ten'] ] = $x['tenMisa']; }

		$don = array();
		foreach ( VHCPMTD_MK::all_dons() as $r ) {
			$don[ (string) $r['ma'] ] = array(
				'coso'   => (string) $r['coso'],
				'ten'    => (string) $r['ten'],
				'ky'     => VHCPMTD_Util::fmt( $r['ky'] ),
				'kenhCD' => (string) $r['kenh'],
				'ngay'   => VHCPMTD_Util::fmt( $r['ngay_tao'] ),
			);
		}
		$rows = array(); $warn = array(); $seen = array(); $ngay_xau = array();
		foreach ( VHCPMTD_MK::all_lines() as $r ) {
			$tt = VHCPMTD_Util::num( $r['thuc_te'] );
			if ( ! $tt ) { continue; }
			$d     = isset( $don[ (string) $r['ma_don'] ] ) ? $don[ (string) $r['ma_don'] ] : array( 'coso' => '', 'ten' => '', 'ky' => '', 'kenhCD' => '', 'ngay' => '' );
			$coso  = $d['coso'];
			$is_tt = ( trim( (string) $r['hinh_thuc'] ) === 'Trực tiếp' );
			$kenh  = (string) $r['kenh'] !== '' ? (string) $r['kenh'] : $d['kenhCD'];
			$nd    = (string) $r['noi_dung'];
			$gc    = (string) $r['note'];
			$ngay  = VHCPMTD_Util::fmt( $r['ngay'] );
			if ( $ngay === '' ) { $ngay = $d['ngay']; }
			VHCPMTD_Misa::gom_ngay_xau( $ngay_xau, $ngay, $r['ngay'], (string) $r['ma_don'] );
			$ma_dv    = isset( $m_unit[ $coso ] ) ? $m_unit[ $coso ] : '';
			$ten_misa = ! empty( $m_tm[ $coso ] ) ? $m_tm[ $coso ] : $coso;
			if ( $coso !== '' && ! $ma_dv ) { $warn[ 'Thiếu Mã đơn vị cho cơ sở: ' . $coso . ' (thêm ở ⚙️ Cấu hình cơ sở)' ] = 1; }
			$tkm = self::tk_mang( $r['loai_cp'], $is_tt, $r['tk_no'], $r['tk_co'], $r['ma_dt'], $coso );
			if ( $tkm['tk_no'] === '' ) { $warn[ 'Thiếu TK Nợ cho loại chi phí: ' . trim( (string) $r['loai_cp'] ) . ' — khai ở ⚙️ Cấu hình → Loại chi phí' ] = 1; }
			$lc  = trim( (string) $r['loai_cp'] );
			$dg1 = VHCPMTD_Util::j( array( 'MKT', $lc !== '' ? VHCPMTD_Cfg::ten_misa_loai( $lc ) : '', $d['ten'], $coso, $kenh, $d['ky'] ) ) . ( $is_tt ? '_Trực tiếp NCC' : '_Tạm ứng NV' );
			$dg2 = VHCPMTD_Util::j( array( $nd, $ten_misa ) ) . ( trim( $gc ) !== '' ? '_' . $gc : '' );
			$rows[] = array( $ngay, $ngay, '', $dg1, $dg2, VHCPMTD_Util::ma_so( $tkm['tk_no'] ), VHCPMTD_Util::ma_so( $tkm['tk_co'] ), $tt, VHCPMTD_Util::ma_so( $tkm['ma_dt'] ), VHCPMTD_Util::ma_so( $ma_dv ) );
			$seen[ (string) $r['ma_don'] ] = 1;
		}
		return array( 'cols' => self::cols(), 'rows' => $rows, 'count' => count( $rows ), 'sodon' => count( $seen ),
			'warn' => array_merge( array_keys( $warn ), VHCPMTD_Misa::warn_ngay_xau( $ngay_xau ) ), 'maDons' => array() );
	}

	/** exportMisaBP(): Công tác / Setup. */
	public static function export_bp( $loai = 'all' ) {
		$cfg = VHCPMTD_Cfg::cfg_static();
		$m_unit = array(); $m_tm = array();
		foreach ( $cfg['coso'] as $x ) { $m_unit[ $x['ten'] ] = $x['maDonVi']; $m_tm[ $x['ten'] ] = $x['tenMisa']; }

		$rows = array(); $warn = array(); $ndot = 0; $ngay_xau = array();
		foreach ( VHCPMTD_BP::all_with_lines() as $r ) {
			if ( $loai && $loai !== 'all' && (string) $r['loai'] !== $loai ) { continue; }
			$lo       = (string) $r['loai'];
			$ten      = (string) $r['ten'];
			$nguoi    = (string) $r['nguoi'];
			$dia_diem = (string) $r['dia_diem'];
			$ky       = VHCPMTD_Util::fmt( $r['ky'] );
			$ngay_dot = VHCPMTD_Util::fmt( $r['ngay_tao'] );
			$used     = false;
			foreach ( $r['lines'] as $x ) {
				$nd = trim( (string) $x['noi_dung'] );
				$tt = VHCPMTD_Util::num( $x['thuc_te'] );
				if ( $nd === '' && ! $tt ) { continue; }
				if ( ! $tt ) { continue; }
				$is_tt = ( trim( (string) $x['hinh_thuc'] ) === 'Trực tiếp' );
				$ngay  = VHCPMTD_Util::fmt( $x['ngay'] );
				if ( $ngay === '' ) { $ngay = $ngay_dot; }
				VHCPMTD_Misa::gom_ngay_xau( $ngay_xau, $ngay, $x['ngay'], (string) $r['ma'] );
				$ghichu   = trim( (string) $x['note'] );
				$ma_dv    = isset( $m_unit[ $dia_diem ] ) ? $m_unit[ $dia_diem ] : '';
				$ten_misa = ! empty( $m_tm[ $dia_diem ] ) ? $m_tm[ $dia_diem ] : $dia_diem;
				if ( $dia_diem !== '' && ! $ma_dv ) { $warn[ 'Thiếu Mã đơn vị cho: ' . $dia_diem . ' (thêm ở ⚙️ Cấu hình cơ sở nếu là cơ sở)' ] = 1; }
				$tkm = self::tk_mang( $x['loai_cp'], $is_tt, $x['tk_no'], $x['tk_co'], $x['ma_dt'], $dia_diem );
				if ( $tkm['tk_no'] === '' ) { $warn[ 'Thiếu TK Nợ cho loại chi phí: ' . trim( (string) $x['loai_cp'] ) . ' — khai ở ⚙️ Cấu hình → Loại chi phí' ] = 1; }
				$dg1 = VHCPMTD_Util::j( array( $lo, $ten, $nguoi, $ky ) ) . ( $is_tt ? '_Trực tiếp NCC' : '_Tạm ứng NV' );
				$dg2 = VHCPMTD_Util::j( array( $nd, $ten_misa ) ) . ( $ghichu !== '' ? '_' . $ghichu : '' );
				$rows[] = array( $ngay, $ngay, '', $dg1, $dg2, VHCPMTD_Util::ma_so( $tkm['tk_no'] ), VHCPMTD_Util::ma_so( $tkm['tk_co'] ), $tt, VHCPMTD_Util::ma_so( $tkm['ma_dt'] ), VHCPMTD_Util::ma_so( $ma_dv ) );
				$used   = true;
			}
			if ( $used ) { $ndot++; }
		}
		return array( 'cols' => self::cols(), 'rows' => $rows, 'count' => count( $rows ), 'sodon' => $ndot,
			'warn' => array_merge( array_keys( $warn ), VHCPMTD_Misa::warn_ngay_xau( $ngay_xau ) ), 'maDons' => array() );
	}

	/** markExported(): chốt "đã xuất" theo bộ phận; đủ cả 2 -> "Đã xuất MISA". */
	/**
	 * MÃ TK NỢ / TK CÓ CỦA MỘT DÒNG CHI — chỗ DUY NHẤT quyết định, dùng cho cả lượt xuất lẫn lượt đóng mã.
	 *
	 * 🔴 ĐƠN ĐÃ XUẤT MISA THÌ MÃ ĐÃ ĐÓNG. Anh Thắng 24/09/2026: *"Đơn đã duyệt, sau khi bấm xuất misa
	 *    thì nó sẽ khóa chi phí đó theo tk nợ được cài sẵn… việc đổi tk nợ cho loại chi phí thì chỉ thay
	 *    đổi chi phí đang diễn ra, chứ không được thay đổi chi phí cũ, như sáng bị lỗi 1 lần"*.
	 *    Trước bản này mọi lượt xuất (kể cả xuất lại đơn "Đã xuất MISA") tra lại bảng mã HIỆN TẠI, nên sửa
	 *    bảng mã là sổ cũ đổi theo — truy TK Nợ về sau không còn khớp tệp đã nộp. Nay `mark_exported()`
	 *    ghi mã vừa xuất vào từng dòng, và đơn đã xuất đọc mã ấy, không tra bảng nữa.
	 * ⚠️ Đường lui cho đơn xuất TRƯỚC bản này: dòng chưa có mã đóng (trống, hoặc mang 141/331 của di
	 *    sản) thì vẫn tra bảng như cũ — tốt hơn là để trống.
	 *
	 * @param array  $r       hàng bảng chiphi (nhom, coso, phan_loai_tt, cn_xu_ly, tk_no, tk_co).
	 * @param string $khoi    khối của ĐƠN chứa dòng.
	 * @param array  $cf      bản đồ mã dựng ở `ban_do_ma()` (pll · loai · no_mx · no · co).
	 * @param bool   $da_xuat đơn đã "Đã xuất MISA"?
	 * @return array [tk_no, tk_co] — chuỗi, rỗng khi không tra được.
	 */
	public static function ma_cua_dong( $r, $khoi, $cf, $da_xuat = false ) {
		$coso    = (string) $r['coso'];
		$pltt    = (string) $r['phan_loai_tt'];
		$nhom    = (string) $r['nhom'];
		$eff_ncc = VHCPMTD_Util::is_ncc( $pltt, isset( $r['cn_xu_ly'] ) ? $r['cn_xu_ly'] : null );
		$co_key  = $eff_ncc ? 'Nhà cung cấp' : $pltt;
		$pll     = isset( $cf['pll'][ $coso ] ) ? $cf['pll'][ $coso ] : '';
		$tk_dong = trim( (string) ( isset( $r['tk_no'] ) ? $r['tk_no'] : '' ) );
		$co_dong = trim( (string) ( isset( $r['tk_co'] ) ? $r['tk_co'] : '' ) );

		if ( $da_xuat && '' !== $tk_dong && ! VHCPMTD_Cfg::la_tk_ben_tra( $tk_dong ) ) {
			return array( 'tk_no' => $tk_dong, 'tk_co' => ( '' !== $co_dong ? $co_dong : self::tk_co_cua( $nhom, $khoi, $co_dong, $co_key, $cf ) ) );
		}

		// TK NỢ = TÀI KHOẢN CỦA LOẠI CHI PHÍ. Nợ trả lời "chi phí gì", nên nguồn thật là danh mục loại
		// chi phí (qua ma trận loại × mảng kinh doanh trước, vì cùng loại mà khác mảng thì khác mã).
		// Mã đã gắn trên dòng CHỈ là bản sao chụp lúc nhập -> lấy sau danh mục, và chỉ khi không phải
		// mã bên trả tiền. Bậc lùi cuối là hai bảng cấu hình CŨ (TK Nợ của CH_Nhom hầu hết là 141).
		$mx_k   = trim( $nhom ) . '|' . trim( (string) $pll );
		$tk_no  = VHCPMTD_Cfg::tkno_xuat( $nhom, $coso, $tk_dong );
		$nhom_k = mb_strtolower( VHCPMTD_Cfg::bo_duoi_nhom( $nhom ) );
		$k_goc  = mb_strtolower( trim( $nhom ) );
		if ( $tk_no === '' && ! empty( $cf['loai'][ $k_goc ] ) )  { $tk_no = $cf['loai'][ $k_goc ]; }
		if ( $tk_no === '' && ! empty( $cf['loai'][ $nhom_k ] ) ) { $tk_no = $cf['loai'][ $nhom_k ]; }
		if ( $tk_no === '' && ! empty( $cf['no_mx'][ $mx_k ] ) && ! VHCPMTD_Cfg::la_tk_ben_tra( $cf['no_mx'][ $mx_k ] ) ) { $tk_no = $cf['no_mx'][ $mx_k ]; }
		if ( $tk_no === '' && ! empty( $cf['no'][ $nhom ] ) && ! VHCPMTD_Cfg::la_tk_ben_tra( $cf['no'][ $nhom ] ) ) { $tk_no = $cf['no'][ $nhom ]; }
		return array( 'tk_no' => (string) $tk_no, 'tk_co' => self::tk_co_cua( $nhom, $khoi, $co_dong, $co_key, $cf ) );
	}

	/** TK Có = BÊN TRẢ TIỀN, ba bậc: TK đối ứng khai ở loại → mã trên dòng → TK Có của phân loại thanh toán. */
	private static function tk_co_cua( $nhom, $khoi, $co_dong, $co_key, $cf ) {
		return (string) VHCPMTD_Cfg::tkco_xuat( $nhom, $khoi, $co_dong,
			! empty( $cf['co'][ $co_key ] ) ? $cf['co'][ $co_key ] : '' );
	}

	/** Bản đồ mã từ cấu hình — dựng MỘT lần cho một lượt xuất / một lượt đóng mã. */
	public static function ban_do_ma( $cfg ) {
		$m_pll = array(); $m_no = array(); $m_co = array(); $m_no_mx = array(); $m_loai = array();
		foreach ( (array) $cfg['coso'] as $x ) { $m_pll[ $x['ten'] ] = $x['phanLoaiLon']; }
		foreach ( (array) $cfg['nhom'] as $x ) { $m_no[ $x['ten'] ] = $x['tkNo']; }
		foreach ( (array) $cfg['phanloai'] as $x ) { $m_co[ $x['ten'] ] = $x['tkCo']; }
		foreach ( (array) $cfg['tkNoMatrix'] as $x ) { $m_no_mx[ trim( (string) $x['nhom'] ) . '|' . trim( (string) $x['pll'] ) ] = $x['tkNo']; }
		foreach ( (array) ( isset( $cfg['loaiChiPhi'] ) ? $cfg['loaiChiPhi'] : array() ) as $x ) {
			if ( trim( (string) $x['tkNo'] ) === '' || VHCPMTD_Cfg::la_tk_ben_tra( $x['tkNo'] ) ) { continue; }
			$m_loai[ mb_strtolower( trim( (string) $x['ten'] ) ) ] = (string) $x['tkNo'];
		}
		return array( 'pll' => $m_pll, 'loai' => $m_loai, 'no_mx' => $m_no_mx, 'no' => $m_no, 'co' => $m_co );
	}

	/** Mã `$ma` có nằm dưới mã `$cha` trong cây tài khoản không (641 → 6412 → 64122): so tiền tố. */
	public static function thuoc_cay_tk( $ma, $cha ) {
		$ma = (string) $ma; $cha = (string) $cha;
		return '' !== $ma && '' !== $cha && 0 === strpos( $ma, $cha );
	}

	/** Cây tài khoản để đổ ô lọc: các mã có mặt cộng mọi mã cha (từ 3 chữ số), đánh dấu mã nào có dòng thật. */
	public static function cay_tk( $ds ) {
		$co = array(); $ra = array();
		foreach ( (array) $ds as $m ) { $co[ (string) $m ] = 1; }
		foreach ( array_keys( $co ) as $m ) {
			for ( $n = 3; $n <= strlen( $m ); $n++ ) { $ra[ substr( $m, 0, $n ) ] = 1; }
		}
		/* Xếp theo CHUỖI, không theo số: cha đứng ngay trên con (641 · 6412 · 64166 · 642 · 6421); xếp số thì
		   642 chen vào giữa 641 và 6412. Khoá mảng PHP đã ép "641" thành số nên ép về chuỗi trước khi xếp. */
		$ma_ds = array_map( 'strval', array_keys( $ra ) ); sort( $ma_ds, SORT_STRING );
		$out = array();
		foreach ( $ma_ds as $m ) { $out[] = array( 'ma' => (string) $m, 'thuc' => isset( $co[ (string) $m ] ) ); }
		return $out;
	}

	/**
	 * ĐÓNG MÃ TK NỢ / TK CÓ VÀO TỪNG DÒNG của một đơn — gọi đúng lúc "chốt đã xuất".
	 * Tra bằng chính `ma_cua_dong()` của lượt xuất, ghi vào `chiphi.tk_no` / `tk_co`; từ đó đơn đọc mã
	 * đã đóng. Chỉ ghi khi tra được mã và mã khác mã đang có. Trả số dòng có mã đóng (gồm dòng đã đúng sẵn).
	 */
	public static function dong_ma_dong( $ma_don, $pl_f, $cf, $khoi ) {
		global $wpdb;
		$t = VHCPMTD_DB::t( 'chiphi' ); $n = 0;
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE ma_don=%s", (string) $ma_don ), ARRAY_A );
		foreach ( (array) $rows as $r ) {
			$eff_ncc = VHCPMTD_Util::is_ncc( (string) $r['phan_loai_tt'], isset( $r['cn_xu_ly'] ) ? $r['cn_xu_ly'] : null );
			if ( $pl_f === 'cn' && $eff_ncc ) { continue; }
			if ( $pl_f === 'ncc' && ! $eff_ncc ) { continue; }
			$ma = self::ma_cua_dong( $r, $khoi, $cf, false );
			$data = array();
			if ( '' !== $ma['tk_no'] && $ma['tk_no'] !== trim( (string) $r['tk_no'] ) ) { $data['tk_no'] = $ma['tk_no']; }
			if ( '' !== $ma['tk_co'] && $ma['tk_co'] !== trim( (string) $r['tk_co'] ) ) { $data['tk_co'] = $ma['tk_co']; }
			if ( count( $data ) ) { $wpdb->update( $t, $data, array( 'id' => (string) $r['id'] ) ); }
			if ( '' !== $ma['tk_no'] ) { $n++; }
		}
		return $n;
	}

	public static function mark_exported( $ma_dons, $pl_f = 'all' ) {
		global $wpdb;
		$pl_f = $pl_f ? $pl_f : 'all';
		$ma_dons = (array) $ma_dons;
		if ( ! count( $ma_dons ) ) { return VHCPMTD_Util::err( 'Không có đơn để chốt' ); }
		$now = VHCPMTD_Util::now_sql();
		$n   = 0; $n_ma = 0;
		/* Bản đồ mã dựng một lần cho cả lô — đóng mã theo đúng bảng đang có ở GIÂY PHÚT chốt. */
		$cf = self::ban_do_ma( VHCPMTD_Cfg::get_config( VHCPMTD_Don::cp_rows() ) );
		foreach ( $ma_dons as $m ) {
			$d = VHCPMTD_Don::don_row( $m );
			if ( ! $d ) { continue; }
			/* 🔴 ĐÓNG MÃ TRƯỚC KHI ĐỔI TRẠNG THÁI — xem chốt ở `ma_cua_dong()`. */
			$n_ma += self::dong_ma_dong( $m, $pl_f, $cf, mb_strtolower( trim( (string) ( isset( $d['khoi'] ) ? $d['khoi'] : '' ) ) ) );
			$data  = array();
			$x_cn  = ( VHCPMTD_Util::fmt( $d['ngay_xuat_cn'] ) !== '' );
			$x_ncc = ( VHCPMTD_Util::fmt( $d['ngay_xuat_ncc'] ) !== '' );
			if ( ( $pl_f === 'cn' || $pl_f === 'all' ) && ! $x_cn )  { $data['ngay_xuat_cn'] = $now; $x_cn = true; }
			if ( ( $pl_f === 'ncc' || $pl_f === 'all' ) && ! $x_ncc ) { $data['ngay_xuat_ncc'] = $now; $x_ncc = true; }
			$loai = VHCPMTD_Don::don_loai( $m );
			if ( ( ! $loai['cn'] || $x_cn ) && ( ! $loai['ncc'] || $x_ncc ) ) { $data['trang_thai'] = 'Đã xuất MISA'; }
			if ( count( $data ) ) { $wpdb->update( VHCPMTD_DB::t( 'don' ), $data, array( 'ma_don' => (string) $m ) ); }
			$n++;
		}
		if ( $n_ma ) {
			VHCPMTD_Log::log_action( array( 'actor' => VHCPMTD_Auth::nguoi(), 'role' => VHCPMTD_Auth::vai_tro(), 'action' => 'Đóng mã TK khi xuất MISA',
				'target' => implode( ',', array_map( 'strval', $ma_dons ) ), 'detail' => $n_ma . ' dòng đóng mã TK Nợ/TK Có theo bảng lúc xuất' ) );
		}
		return VHCPMTD_Util::ok( array( 'count' => $n, 'dongMa' => $n_ma ) );
	}
}
