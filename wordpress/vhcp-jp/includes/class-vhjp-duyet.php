<?php
/**
 * KẾ TOÁN DUYỆT — `JP2_06_Duyet.gs`.
 *
 * =============================================================================================
 * 🔴 HOÀN TẤT LÀ MỐC DUY NHẤT ĐƯỢC RA SỔ KHO, VÀ NÓ CẦN ĐỦ HAI CHỮ KÝ.
 * =============================================================================================
 * Anh Andy ký phần HÀNG HOÁ cho một báo cáo lúc 23:30 rồi báo *"duyệt kho mà không thấy trừ
 * tồn"*. Dữ liệu đúng luật: chỉ báo cáo HOÀN TẤT mới xuất kho, mà HOÀN TẤT cần đủ hai chữ ký.
 *
 * ⚠️ Nhưng đó là LỖI CỦA APP, không phải của kế toán. Câu cũ chỉ nói TRẠNG THÁI (*"Đã duyệt
 *    phần hàng hoá · còn phần doanh thu"*) chứ không nói HẬU QUẢ, và nó là một dòng thông báo
 *    thoáng qua — đóng đi là KHÔNG CÒN DẤU VẾT Ở ĐÂU. Người đọc thấy chữ "Đã duyệt" thì tin là
 *    xong, hợp lý.
 *    Nên `ket_ky()` là NGUỒN DUY NHẤT của câu chữ ấy, máy chủ dựng chứ giao diện không tự ghép
 *    (ba chỗ dùng chung: thông báo sau khi ký · dải băng ở màn chi tiết · bộ lọc danh sách), và
 *    nó được trả kèm ở màn chi tiết dưới dạng DẢI BĂNG CỐ ĐỊNH — thông báo thì biến mất, dải
 *    băng thì không.
 *
 * ---------------------------------------------------------------------------------------------
 * 🔴🔴 HOÀN TẤT LÀ LÚC GIÁ VỐN BÁN RA SINH RA — ĐÂY LÀ CHỖ NỐI SANG SỔ KHO.
 * ---------------------------------------------------------------------------------------------
 * Báo cáo thành HOÀN TẤT -> `VHJP_Kho::xuat_bao_cao()` trừ lớp tồn ở kho CƠ SỞ và ghi giá vốn
 * vào 632. TRẢ VỀ một báo cáo đã hoàn tất -> `VHJP_Kho::hoan_bao_cao()` hoàn lại đúng ngần ấy.
 *
 * Duyệt xong mà không ghi sổ kho là đúng loại lỗi tệ nhất: BÁO CÁO TRÔNG NHƯ ĐÃ XONG, sổ vẫn
 * cân, mà giá vốn thì không có ở đâu cả — lãi gộp của cả hệ bằng đúng doanh thu. Từ bản 1.12.0
 * hai móc ấy đã nối thật.
 *
 * ⚠️ NHƯNG LỖI CỦA SỔ KHO KHÔNG ĐƯỢC NUỐT MẤT CHỮ KÝ. Chữ ký đã vào cơ sở dữ liệu TRƯỚC khi gọi
 *    sang kho. Để lỗi kho ném ngược ra là kế toán nhận một câu lỗi, tưởng chưa ký được, bấm lại.
 *    Nên `ra_so_kho()` bắt hết và trả về `kho` mang `loi` — đúng hình dạng mà `kho_msg()` đã
 *    dựng sẵn từ đầu cho tình huống này, và câu thông báo kèm luôn việc phải làm tiếp.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHJP_Duyet {

	/** Hai phần của một báo cáo — dùng chung định nghĩa với `VHJP_BaoCao`. */
	const PHAN_TIEN = VHJP_BaoCao::PHAN_TIEN;
	const PHAN_HANG = VHJP_BaoCao::PHAN_HANG;

	public static function ten_phan( $phan ) {
		return self::PHAN_HANG === $phan ? 'hàng hoá' : 'doanh thu';
	}

	/* ═══════════════════════ TÌNH TRẠNG CHỮ KÝ ═══════════════════════ */

	/**
	 * TÌNH TRẠNG CHỮ KÝ + HẬU QUẢ — nguồn DUY NHẤT của câu chữ. Xem khối 🔴 đầu tệp.
	 *
	 * `motPhan` = ký ĐÚNG MỘT phần và chưa hoàn tất — đó mới là trạng thái KẸT. Báo cáo chưa ai
	 * ký cũng "chưa trừ kho", nhưng đó là bình thường, không phải chỗ cần báo động.
	 */
	public static function ket_ky( $head ) {
		$k    = VHJP_BaoCao::chu_ky( $head );
		$xong = VHJP_BaoCao::TT_HOAN_TAT === VHJP_Doc::str(
			is_array( $head ) && isset( $head['status'] ) ? $head['status'] : '' );
		$co_tien = '' !== $k['rev']['by'];
		$co_hang = '' !== $k['stock']['by'];
		$mot_phan = ! $xong && ( $co_tien !== $co_hang );

		$ra = array(
			'duCaHai' => $k['duCaHai'], 'hoanTat' => $xong, 'motPhan' => $mot_phan,
			'daKy'  => $mot_phan ? ( $co_tien ? self::PHAN_TIEN : self::PHAN_HANG ) : '',
			'thieu' => $mot_phan ? ( $co_tien ? self::PHAN_HANG : self::PHAN_TIEN ) : '',
			'msg'   => '',
		);
		if ( ! $mot_phan ) { return $ra; }

		$ai = $co_tien ? $k['rev'] : $k['stock'];
		$ra['msg'] = 'Đã ký phần ' . self::ten_phan( $ra['daKy'] )
			. ( '' !== $ai['by'] ? ' (' . $ai['by'] . ( '' !== $ai['at'] ? ' · ' . $ai['at'] : '' ) . ')' : '' )
			. ' — nhưng báo cáo CHƯA HOÀN TẤT nên CHƯA trừ kho và CHƯA vào sổ công nợ. '
			. 'Còn thiếu chữ ký phần ' . mb_strtoupper( self::ten_phan( $ra['thieu'] ), 'UTF-8' ) . '.';
		return $ra;
	}

	/** Cảnh báo thiếu ảnh ĐÃ CHỐT trong báo cáo. Hỏng JSON thì coi như không có. */
	public static function anh_da_chot( $head ) {
		$s = is_array( $head ) && isset( $head['photoWarnJson'] )
			? VHJP_Doc::str( $head['photoWarnJson'] ) : '';
		if ( '' === $s ) { return array(); }
		$a = json_decode( $s, true );
		return ( is_array( $a ) && $a ) ? $a : array();
	}

	/**
	 * Phần này còn ký được không.
	 *
	 * ⚠️ CHỈ ký khi đang CHỜ DUYỆT. `CAN_SUA` là đang chờ nhân viên nộp lại — ký lên một báo
	 *    cáo họ đang sửa dở là ký vào một con số sắp đổi.
	 */
	public static function ky_duoc( $head, $phan ) {
		$st = VHJP_Doc::str( is_array( $head ) && isset( $head['status'] ) ? $head['status'] : '' );
		if ( VHJP_BaoCao::TT_CHO_DUYET !== $st ) { return false; }
		$k = VHJP_BaoCao::chu_ky( $head );
		return '' === ( self::PHAN_HANG === $phan ? $k['stock']['by'] : $k['rev']['by'] );
	}

	/* ═══════════════════════ DANH SÁCH CHỜ DUYỆT ═══════════════════════ */

	/**
	 * ⚠️ NHÁP thì kế toán KHÔNG THẤY — đó là bản nhân viên đang gõ dở.
	 *
	 * ⚠️ `motPhan` KHÁC `onlyMine`: cái kia gồm CẢ báo cáo chưa ai ký (bình thường, đang chờ).
	 *    Cái này chỉ lấy báo cáo ký ĐÚNG MỘT phần — trạng thái KẸT, mà nhìn ở danh sách không
	 *    phân biệt được vì nó vẫn mang `CHO_DUYET` y như báo cáo chưa ai đụng.
	 */
	public static function ds_bao_cao( $u, $f = array() ) {
		$f = (array) $f;
		$g = function ( $k ) use ( $f ) { return isset( $f[ $k ] ) ? $f[ $k ] : ''; };

		$ds = array();
		foreach ( VHJP_Nguon::doc( 'JP_Reports' ) as $r ) {
			$st = VHJP_Doc::str( $r['status'] );
			if ( VHJP_BaoCao::TT_NHAP === $st ) { continue; }
			if ( '' !== VHJP_Doc::str( $g( 'status' ) ) && $st !== VHJP_Doc::str( $g( 'status' ) ) ) { continue; }
			if ( '' !== VHJP_Doc::str( $g( 'locationId' ) )
				&& (string) $r['locationId'] !== (string) $g( 'locationId' ) ) { continue; }
			if ( '' !== VHJP_Doc::str( $g( 'fromDate' ) )
				&& VHJP_Doc::ngay( $r['toDate'] ) < VHJP_Doc::ngay( $g( 'fromDate' ) ) ) { continue; }
			if ( '' !== VHJP_Doc::str( $g( 'toDate' ) )
				&& VHJP_Doc::ngay( $r['fromDate'] ) > VHJP_Doc::ngay( $g( 'toDate' ) ) ) { continue; }
			if ( ! empty( $f['onlyMine'] ) ) {          // "chỉ báo cáo còn thiếu chữ ký"
				if ( VHJP_BaoCao::chu_ky( $r )['duCaHai'] ) { continue; }
				if ( VHJP_BaoCao::TT_HOAN_TAT === $st ) { continue; }
			}
			if ( ! empty( $f['motPhan'] ) && ! self::ket_ky( $r )['motPhan'] ) { continue; }
			$ds[] = $r;
		}

		usort( $ds, function ( $a, $b ) {
			return VHJP_Doc::ngay( $a['fromDate'] ) < VHJP_Doc::ngay( $b['fromDate'] ) ? 1 : -1;
		} );

		$ra = array();
		foreach ( $ds as $r ) {
			$ra[] = array(
				'id'           => (string) $r['id'],
				'locationName' => VHJP_Doc::str( $r['locationName'] ),
				'maKH'         => VHJP_Doc::str( $r['maKH'] ),
				'machineType'  => VHJP_Doc::str( $r['machineType'] ),
				'fromDate'     => VHJP_Doc::ngay( $r['fromDate'] ),
				'toDate'       => VHJP_Doc::ngay( $r['toDate'] ),
				'userName'     => VHJP_Doc::str( $r['userName'] ),
				'status'       => VHJP_Doc::str( $r['status'] ),
				'revMeter'     => VHJP_Doc::num( $r['revMeter'] ),
				'revBank'      => VHJP_Doc::num( $r['revBank'] ),
				'adjMachine'   => VHJP_Doc::num( $r['adjMachine'] ),
				'refundCustomer' => VHJP_Doc::num( $r['refundCustomer'] ),
				'refundRows'   => VHJP_Doc::num( $r['refundRows'] ),
				'refundTotal'  => VHJP_Tinh::hoan_tong( $r ),
				'cashActual'   => VHJP_Doc::num( $r['cashActual'] ),
				'totalSubmit'  => VHJP_Doc::num( $r['totalSubmit'] ),
				/* ⚠️ Cộng CẢ cảnh báo thiếu ảnh — con số trên thẻ phải là tổng THẬT, không thì
				   kế toán lọc theo nó rồi bỏ sót đúng những báo cáo thiếu bằng chứng. */
				'warnCount'    => VHJP_Doc::num( $r['warnCount'] ) + count( self::anh_da_chot( $r ) ),
				'chuKy'        => VHJP_BaoCao::chu_ky( $r ),
				'rejectPart'   => VHJP_Doc::str( $r['rejectPart'] ),
				'rejectReason' => VHJP_Doc::str( $r['rejectReason'] ),
				'payStatus'    => VHJP_Doc::str( $r['payStatus'] ),
				'paid'         => VHJP_Doc::num( $r['paid'] ),
				'canSignRev'   => self::ky_duoc( $r, self::PHAN_TIEN ),
				'canSignStock' => self::ky_duoc( $r, self::PHAN_HANG ),
				'ketKy'        => self::ket_ky( $r ),
			);
		}
		return $ra;
	}

	/* ═══════════════════════ XEM CHI TIẾT ═══════════════════════ */

	/**
	 * MÀN CHI TIẾT CỦA KẾ TOÁN — chính màn nhân viên, cộng thêm phần để KÝ.
	 *
	 * ⚠️ CẢ HAI PHẦN ĐỀU NHẬN TOÀN BỘ BÁO CÁO: cùng danh sách dòng, cùng danh sách cảnh báo.
	 *    `part` trên mỗi cảnh báo chỉ để tô màu / nhóm lại cho dễ nhìn, KHÔNG dùng để cắt bớt
	 *    cái kế toán được xem — người sắp ký phải nhìn được hết.
	 */
	public static function lay( $u, $ma_bc ) {
		$d = VHJP_BaoCao::lay( $u, $ma_bc );
		$head = VHJP_Nguon::tim_mot( 'JP_Reports', 'id', $ma_bc );

		$warns = array();
		foreach ( $d['rows'] as $r ) {
			foreach ( ( isset( $r['warns'] ) && is_array( $r['warns'] ) ? $r['warns'] : array() ) as $w ) {
				$warns[] = self::mot_canh_bao( $w, $r['id'], $r['machineCode'], $r['itemCode'] );
			}
		}
		/* Cảnh báo cấp BÁO CÁO (kỳ chồng, bảng tổng lệch, thiếu lý do) — người sắp KÝ mới là
		   người cần biết báo cáo này chồng kỳ với bản khác. Mã dòng để rỗng vì chúng thuộc cả
		   báo cáo, không thuộc dòng nào. */
		foreach ( $d['headWarns'] as $w ) { $warns[] = self::mot_canh_bao( $w, '', '', '' ); }
		/* Cảnh báo thiếu ảnh đã chốt lúc nộp. */
		foreach ( self::anh_da_chot( $head ) as $w ) { $warns[] = self::mot_canh_bao( $w, '', '', '' ); }

		$d['canSignRev']   = self::ky_duoc( $head, self::PHAN_TIEN );
		$d['canSignStock'] = self::ky_duoc( $head, self::PHAN_HANG );
		/* ⚠️ Gộp SAU CÙNG, khi danh sách đã đủ cả cảnh báo dòng + cảnh báo báo cáo + thiếu ảnh
		   — gộp giữa chừng thì thứ tự phụ thuộc chỗ gọi. */
		$d['warnings'] = VHJP_Tinh::gop_canh_bao( $warns );
		$d['warnGoc']  = count( $warns );          // số cảnh báo THẬT, để nói ra đã gộp bao nhiêu
		$d['signState'] = VHJP_BaoCao::chu_ky( $head );
		$d['tienVsHang'] = VHJP_Tinh::tien_vs_hang( $head, $d['rows'] );
		/* Dải băng CỐ ĐỊNH ở màn chi tiết — thông báo thoáng qua thì biến mất, dải băng thì không. */
		$d['ketKy'] = self::ket_ky( $head );
		return $d;
	}

	/** Một cảnh báo đã gắn chỗ nó thuộc về. Giữ `gop`/`so` để `gop_canh_bao()` còn cộng được. */
	private static function mot_canh_bao( $w, $row_id, $ma_may, $ma_hang ) {
		$w = (array) $w;
		$o = array(
			'rowId' => (string) $row_id, 'machineCode' => (string) $ma_may,
			'itemCode' => (string) $ma_hang,
			'code' => isset( $w['code'] ) ? $w['code'] : '',
			'part' => isset( $w['part'] ) ? $w['part'] : '',
			'msg'  => isset( $w['msg'] ) ? $w['msg'] : '',
			'detail' => isset( $w['detail'] ) ? $w['detail'] : '',
		);
		/* ⚠️ PHẢI mang theo `gop` và `so`. Bản gốc chép tay sáu trường và BỎ QUÊN hai cái này,
		   nên phép gộp ở dưới không bao giờ gộp được gì — mà nó vẫn chạy, vẫn xanh, chỉ là
		   danh sách dài y như cũ. Ở đây mang theo để phép gộp làm được đúng việc của nó. */
		if ( isset( $w['gop'] ) ) { $o['gop'] = $w['gop']; }
		if ( isset( $w['so'] ) )  { $o['so']  = $w['so']; }
		return $o;
	}

	/* ═══════════════════════ KÝ ═══════════════════════ */

	/**
	 * KÝ MỘT PHẦN. Đủ cả hai phần ⇒ HOÀN TẤT ⇒ mới khoá kỳ và mới cho nối kỳ sau.
	 *
	 * ⚠️ Ký lại phần đã ký, hoặc ký một báo cáo đã hoàn tất, thì KHÔNG NÉM — trả về câu kể
	 *    chuyện đã xong. Ném ở đây là kế toán bấm hai lần vì mạng chậm rồi thấy một câu lỗi đỏ
	 *    trong khi mọi thứ đều ổn.
	 */
	public static function ky( $u, $ma_bc, $phan, $ghi_chu = '' ) {
		$ph = ( self::PHAN_HANG === strtoupper( VHJP_Doc::str( $phan ) ) )
			? self::PHAN_HANG : self::PHAN_TIEN;

		if ( ! VHJP_Nguon::lay_khoa( $ma_bc ) ) {
			throw new Exception( 'Báo cáo này đang được xử lý ở một cửa sổ khác — thử lại sau vài giây' );
		}
		try {
			return self::ky_trong_khoa( $u, $ma_bc, $ph, $ghi_chu );
		} finally {
			VHJP_Nguon::tra_khoa( $ma_bc );
		}
	}

	private static function ky_trong_khoa( $u, $ma_bc, $ph, $ghi_chu ) {
		$head = VHJP_Nguon::tim_mot( 'JP_Reports', 'id', $ma_bc );
		if ( ! $head ) { throw new Exception( 'Không tìm thấy báo cáo' ); }
		$st = VHJP_Doc::str( $head['status'] );
		if ( VHJP_BaoCao::TT_NHAP === $st ) { throw new Exception( 'Báo cáo chưa nộp' ); }
		if ( VHJP_BaoCao::TT_CAN_SUA === $st ) {
			throw new Exception( 'Báo cáo đang chờ nhân viên sửa' );
		}
		if ( VHJP_BaoCao::TT_HOAN_TAT === $st ) {
			return array( 'ok' => true, 'status' => VHJP_BaoCao::TT_HOAN_TAT,
				'msg' => 'Đã hoàn tất trước đó' );
		}

		$k = VHJP_BaoCao::chu_ky( $head );
		$da_ky = ( self::PHAN_HANG === $ph ) ? $k['stock'] : $k['rev'];
		if ( '' !== $da_ky['by'] ) {
			return array( 'ok' => true, 'status' => $st,
				'msg' => 'Phần ' . self::ten_phan( $ph ) . ' đã được ' . $da_ky['by'] . ' duyệt' );
		}

		$luc = gmdate( 'Y-m-d H:i:s', time() + 7 * 3600 );      // giờ Việt Nam
		$ten = VHJP_Doc::str( isset( $u['hoTen'] ) ? $u['hoTen'] : '' );
		$f = ( self::PHAN_HANG === $ph )
			? array( 'apprStockBy' => $ten, 'apprStockAt' => $luc )
			: array( 'apprRevBy' => $ten, 'apprRevAt' => $luc );

		/* Phần còn lại đã ký chưa? Ký rồi thì báo cáo hoàn tất. */
		$con_lai = ( self::PHAN_HANG === $ph ) ? $k['rev'] : $k['stock'];
		$xong = '' !== $con_lai['by'];
		if ( $xong ) { $f['status'] = VHJP_BaoCao::TT_HOAN_TAT; }

		$ok = VHJP_Nguon::sua( 'JP_Reports', $ma_bc, $f );
		/* 🔴 Ghi hỏng thì NÓI RA. Trả "đã ký" cho một chữ ký chưa vào sổ là kế toán đi làm việc
		   khác, và báo cáo nằm lại mãi ở trạng thái chờ mà không ai biết. */
		if ( ! $ok ) { throw new Exception( 'Không ghi được chữ ký — thử lại, hoặc báo người quản trị' ); }

		VHJP_NhatKy::ghi( $u, self::PHAN_HANG === $ph ? 'APPROVE_STOCK' : 'APPROVE_REV',
			$ma_bc, VHJP_Doc::str( $head['locationName'] ),
			array( 'note' => VHJP_Doc::str( $ghi_chu ), 'xong' => $xong ) );

		/* HOÀN TẤT là mốc duy nhất được ra sổ kho — xem khối 🔴🔴 ở đầu tệp. */
		$kho = $xong ? self::ra_so_kho( $u, $ma_bc, true ) : null;

		/* ⚠️ `$head` là bản đọc TRƯỚC khi ghi chữ ký vừa rồi, nên phải chồng `$f` lên mới hỏi ra
		   tình trạng MỚI — hỏi trên bản thô là trả về tình trạng CŨ, tức câu thông báo nói sai
		   ngay lúc người dùng cần nó nhất. */
		$ket = null;
		if ( ! $xong ) { $ket = self::ket_ky( array_merge( $head, $f ) ); }

		return array(
			'ok' => true, 'part' => $ph, 'done' => $xong,
			'status' => $xong ? VHJP_BaoCao::TT_HOAN_TAT : $st,
			'kho' => $kho,
			'ketKy' => $ket,
			'msg' => $xong
				? 'Đã ký đủ hai phần — báo cáo HOÀN TẤT' . self::kho_msg( $kho )
				: $ket['msg'],
		);
	}

	/**
	 * Gọi sổ kho, và KHÔNG ĐỂ LỖI CỦA NÓ NUỐT MẤT CHỮ KÝ.
	 *
	 * =========================================================================================
	 * 🔴 CHỮ KÝ ĐÃ VÀO SỔ TRƯỚC KHI GỌI TỚI ĐÂY. Để một lỗi kho ném ngược ra ngoài thì kế toán
	 *    nhận về một câu lỗi, tưởng là chưa ký được, bấm ký lại — trong khi chữ ký đã nằm trong
	 *    cơ sở dữ liệu. Nên bắt hết, và trả lại `loi` theo đúng hình dạng mà `kho_msg()` đã
	 *    dựng sẵn cho tình huống "sổ kho không ghi được".
	 * =========================================================================================
	 *
	 * ⚠️ Câu lỗi gốc ĐƯỢC đưa ra cho kế toán ở đây, khác với cổng (cổng giấu vì câu lỗi hay kèm
	 *    đường dẫn tệp). Ở đây câu lỗi là *"Kho JP Bà Rịa đang có người thao tác"* — đúng thứ
	 *    người đọc cần để biết phải làm gì. Nuốt nó đi thành một câu chung là bắt họ đoán.
	 *
	 * @param bool $xuat true = báo cáo vừa hoàn tất (trừ tồn); false = vừa trả về (hoàn lại).
	 */
	private static function ra_so_kho( $u, $ma_bc, $xuat ) {
		try {
			return $xuat ? VHJP_Kho::xuat_bao_cao( $u, $ma_bc )
				: VHJP_Kho::hoan_bao_cao( $u, $ma_bc );
		} catch ( Throwable $e ) {
			VHJP_NhatKy::ghi( $u, 'KHO_LOI', $ma_bc, '', array( 'loi' => $e->getMessage() ) );
			return array( 'loi' => $e->getMessage() );
		}
	}

	/**
	 * Một câu kể kết quả sổ kho, gắn vào thông báo duyệt hoặc trả về.
	 *
	 * 🔴 NGUỒN DUY NHẤT của câu chữ ấy. Hai chỗ gọi (ký xong · trả về) mà mỗi chỗ tự ghép một
	 *    câu là hai chỗ sẽ kể hai chuyện khác nhau về cùng một việc — đúng cái bẫy `ket_ky()`
	 *    sinh ra để bịt, xem khối 🔴 đầu tệp.
	 *
	 * @param bool $hoan true = đang trả về (hoàn kho); false = vừa duyệt xong (xuất kho).
	 */
	public static function kho_msg( $kho, $hoan = false ) {
		if ( ! $kho ) { return ''; }
		if ( ! empty( $kho['loi'] ) ) {
			return $hoan
				? ' · ⚠ SỔ KHO CHƯA HOÀN ĐƯỢC (' . $kho['loi'] . '). Báo cáo đã trả về, nhưng '
					. 'báo cáo này từng hoàn tất nên sổ 632 CÒN dòng giá vốn của nó và lớp tồn '
					. 'CÒN THIẾU đúng số đã trừ — chạy lại "Xuất lại" cho báo cáo này.'
				: ' · ⚠ SỔ KHO CHƯA GHI ĐƯỢC (' . $kho['loi'] . '). Báo cáo vẫn hoàn tất, '
					. 'nhưng CHƯA trừ lớp tồn và CHƯA có giá vốn trong sổ 632 — xử lý xong '
					. 'nguyên nhân rồi bấm ký lại, sổ kho sẽ ghi bù.';
		}
		/* Ký lại một báo cáo đã ra sổ thì KHÔNG trừ tồn lần nữa — và phải nói ra, không thì
		   người đọc thấy "xuất kho 0 dòng" rồi tưởng hàng chưa được ghi. */
		if ( ! empty( $kho['daCoTruoc'] ) ) {
			return ' · sổ kho đã ghi từ lượt duyệt trước (' . VHJP_Doc::num( $kho['soDong'] )
				. ' dòng, giá vốn ' . VHJP_Doc::money( $kho['tongGiaVon'] ) . 'đ) — không trừ lại';
		}
		if ( isset( $kho['daGo'] ) ) {
			return ' · đã hoàn kho ' . VHJP_Doc::num( $kho['daGo'] ) . ' dòng, trả lại lớp tồn';
		}
		if ( 0 === (int) VHJP_Doc::num( $kho['soDong'] ) ) {
			return ' · sổ kho không có gì để ghi (báo cáo này không có dòng hàng nào bán ra)';
		}
		$s = ' · xuất kho ' . VHJP_Doc::num( $kho['soDong'] ) . ' dòng, giá vốn '
			. VHJP_Doc::money( isset( $kho['tongGiaVon'] ) ? $kho['tongGiaVon'] : 0 ) . 'đ';
		if ( ! empty( $kho['thieuLop'] ) ) {
			$s .= ' (⚠ ' . count( $kho['thieuLop'] ) . ' mã THIẾU LỚP TỒN nên giá vốn ghi 0 — '
				. 'cơ sở chưa được chuyển hàng xuống, hoặc phiếu nhập còn thiếu. Nhập bù rồi '
				. 'bấm Xuất lại)';
		}
		return $s;
	}

	/* ═══════════════════════ TRẢ VỀ SỬA ═══════════════════════ */

	/**
	 * TRẢ BÁO CÁO VỀ CHO NHÂN VIÊN SỬA.
	 *
	 * ⚠️ BẮT BUỘC CÓ LÝ DO. Không có lý do thì nhân viên mở ra không biết phải sửa gì, và sáu
	 *    tháng sau không ai dựng lại được vì sao báo cáo ấy quay đầu.
	 *
	 * ⚠️ XOÁ SẠCH MỌI CHỮ KÝ, kể cả chữ ký gộp đời cũ. Giữ lại một nửa là báo cáo quay về tay
	 *    nhân viên mà vẫn mang chữ ký của kế toán cho phần kia — họ sửa xong, nộp lại, và phần
	 *    ấy HOÀN TẤT mà không ai soát lần hai.
	 */
	public static function tra_ve( $u, $ma_bc, $ly_do ) {
		$r = VHJP_Doc::str( $ly_do );
		if ( '' === $r ) { throw new Exception( 'Nhập lý do trả về' ); }

		if ( ! VHJP_Nguon::lay_khoa( $ma_bc ) ) {
			throw new Exception( 'Báo cáo này đang được xử lý ở một cửa sổ khác — thử lại sau vài giây' );
		}
		try {
			$head = VHJP_Nguon::tim_mot( 'JP_Reports', 'id', $ma_bc );
			if ( ! $head ) { throw new Exception( 'Không tìm thấy báo cáo' ); }
			$st = VHJP_Doc::str( $head['status'] );
			if ( VHJP_BaoCao::TT_NHAP === $st ) { throw new Exception( 'Báo cáo chưa nộp' ); }

			$ok = VHJP_Nguon::sua( 'JP_Reports', $ma_bc, array(
				'status' => VHJP_BaoCao::TT_CAN_SUA,
				'rejectBy' => VHJP_Doc::str( isset( $u['hoTen'] ) ? $u['hoTen'] : '' ),
				'rejectAt' => gmdate( 'Y-m-d H:i:s', time() + 7 * 3600 ),
				'rejectPart' => 'ALL', 'rejectReason' => $r,
				'apprBy' => '', 'apprAt' => null,
				'apprRevBy' => '', 'apprRevAt' => null,
				'apprStockBy' => '', 'apprStockAt' => null,
			) );
			if ( ! $ok ) { throw new Exception( 'Không ghi được lượt trả về — thử lại' ); }
			VHJP_NhatKy::ghi( $u, 'REJECT', $ma_bc, VHJP_Doc::str( $head['locationName'] ), $r );

			/* Trả về một báo cáo ĐÃ HOÀN TẤT thì phải HOÀN KHO — không thì sổ 632 còn dòng của
			   một báo cáo đang chờ sửa, lớp tồn thiếu đúng số đã trừ, và lượt duyệt lần sau trừ
			   thêm một lần nữa. */
			$kho = ( VHJP_BaoCao::TT_HOAN_TAT === $st ) ? self::ra_so_kho( $u, $ma_bc, false ) : null;

			return array( 'ok' => true, 'status' => VHJP_BaoCao::TT_CAN_SUA, 'kho' => $kho,
				'msg' => 'Đã trả về cho nhân viên' . self::kho_msg( $kho, true ) );
		} finally {
			VHJP_Nguon::tra_khoa( $ma_bc );
		}
	}
}
