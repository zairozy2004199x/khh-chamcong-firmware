<?php
/**
 * ĐỐI SOÁT NGÂN HÀNG — khớp tiền về với báo cáo phải nộp.
 *
 * =================================================================================================
 * 🔴 ĐỐI SOÁT CHỈ ĐỘNG VÀO BA Ô, KHÔNG ĐỤNG BẤT KỲ SỐ DOANH THU NÀO
 * =================================================================================================
 * Nó ghi: số kế toán đã nhận · ngày nhận · cách thu. Doanh thu, hàng hoá, giá vốn không đổi một
 * đồng. Giao diện đã hứa đúng câu ấy với kế toán trước khi họ bấm Áp, nên máy chủ phải giữ lời.
 *
 * =================================================================================================
 * 🔴 MỘT LÔ CHỈ ÁP ĐƯỢC MỘT LẦN
 * =================================================================================================
 * Mã lô sinh từ CHÍNH nội dung tệp (ngày + số tiền + mã tham chiếu của mọi dòng). Nhờ vậy đọc lại
 * đúng tệp ấy là ra đúng mã lô cũ, và hệ biết ngay là đã áp rồi — kể cả khi kế toán đổi tên tệp.
 * Không có chốt này thì bấm Áp hai lần là tiền nhân đôi trên sổ công nợ, mà sổ vẫn cân.
 *
 * =================================================================================================
 * 🔴 KHỚP MƠ HỒ THÌ KHÔNG TỰ CHỌN
 * =================================================================================================
 * Một dòng chuyển khoản khớp hai cơ sở thì hệ KHÔNG đoán. Đoán đúng 9/10 lần nghe có vẻ tốt, cho
 * tới lần thứ 10 — lúc ấy tiền của cơ sở A nằm trong sổ cơ sở B, và không ai đi tìm vì sổ vẫn cân.
 *
 * @package VHCP_JP
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHJP_DoiSoat {

	/** Ba cách tiền về. Để trống KHÔNG được coi là tiền mặt — xem `cach_thu()`. */
	const CACH = array( 'TM', 'CK', 'QR' );

	/** Cổng an toàn: chưa xác nhận app đọc đúng cột thì luồng tự động KHÔNG được ghi tiền. */
	const O_XAC_NHAN_COT = 'vhjp_bank_cot_ok';
	const O_LICH_PHUT    = 'vhjp_bank_lich_phut';

	private static function so( $v ) { return VHJP_Doc::num( $v ); }

	private static function can_kt( $u ) {
		if ( ! VHJP_Auth::la_kt( $u ) ) { throw new Exception( 'Việc này cần tài khoản kế toán' ); }
	}

	/**
	 * Chuẩn hoá cách thu. Rỗng thì CHỐI, không mặc định TM.
	 *
	 * 🔴 Mặc định TM là nói sai rằng đã nhận tiền mặt — và người đi đếm két sẽ không tìm thấy
	 *    khoản ấy, rồi đi tìm cả buổi.
	 */
	public static function cach_thu( $v ) {
		$c = strtoupper( VHJP_Doc::str( $v ) );
		if ( ! in_array( $c, self::CACH, true ) ) {
			throw new Exception( 'Phải chọn cách thu: tiền mặt (TM) · chuyển khoản (CK) · QR' );
		}
		return $c;
	}

	/* ══════════════════════════════════════════════════════════════ ĐỌC TỆP SAO KÊ ═══════ */

	/** Một dòng tệp đã chuẩn hoá: seq · date · amount · code · desc. */
	private static function doc_dong( $rows ) {
		$ra = array(); $i = 0;
		foreach ( (array) $rows as $r ) {
			$i++;
			if ( ! is_array( $r ) ) { continue; }
			$tien = self::so( isset( $r['amount'] ) ? $r['amount'] : 0 );
			if ( $tien <= 0 ) { continue; }
			$ra[] = array(
				'seq'    => $i,
				'date'   => VHJP_Doc::ngay( isset( $r['date'] ) ? $r['date'] : '' ),
				'amount' => $tien,
				'code'   => VHJP_Doc::str( isset( $r['code'] ) ? $r['code'] : '' ),
				'desc'   => VHJP_Doc::str( isset( $r['desc'] ) ? $r['desc'] : '' ),
			);
		}
		return $ra;
	}

	/**
	 * Mã lô sinh từ CHÍNH nội dung tệp.
	 *
	 * ⚠️ Không lấy tên tệp, không lấy thời điểm đọc: kế toán đổi tên tệp rồi đọc lại là mã khác,
	 *    và chốt "một lô một lần" mất hiệu lực đúng lúc cần nhất.
	 */
	private static function ma_lo( $kind, $dong ) {
		$s = strtoupper( VHJP_Doc::str( $kind ) );
		foreach ( $dong as $x ) {
			$s .= '|' . $x['date'] . '|' . $x['amount'] . '|' . $x['code'];
		}
		return 'LO' . strtoupper( substr( hash( 'sha256', $s ), 0, 12 ) );
	}

	/**
	 * Cơ sở nào được nhắc trong diễn giải.
	 *
	 * ⚠️ So bằng chuỗi ĐÃ BỎ DẤU và bỏ khoảng trắng — sao kê ngân hàng viết không dấu và hay
	 *    dính chữ. Trả về MỌI cơ sở khớp; ai chọn là việc của kế toán khi có nhiều hơn một.
	 */
	private static function doan_coso( $desc, $ds_cs ) {
		$n = VHJP_Doc::norm( $desc );
		$n = preg_replace( '/[^a-z0-9]/', '', $n );
		$ra = array();
		foreach ( $ds_cs as $l ) {
			foreach ( array( $l['maKH'], $l['code'], $l['name'] ) as $k ) {
				$k = preg_replace( '/[^a-z0-9]/', '', VHJP_Doc::norm( $k ) );
				/* Chuỗi quá ngắn thì bỏ: "A" khớp với mọi diễn giải. */
				if ( strlen( $k ) < 3 ) { continue; }
				if ( false !== strpos( $n, $k ) ) { $ra[] = $l['id']; break; }
			}
		}
		return array_values( array_unique( $ra ) );
	}

	private static function ds_coso() {
		$ra = array();
		foreach ( VHJP_Nguon::doc( 'JP_Locations' ) as $l ) {
			$ra[] = array( 'id' => VHJP_Doc::str( $l['id'] ), 'code' => VHJP_Doc::str( $l['code'] ),
				'name' => VHJP_Doc::str( $l['name'] ), 'maKH' => VHJP_Doc::str( $l['maKH'] ) );
		}
		return $ra;
	}

	/** Báo cáo còn thiếu tiền của một cơ sở, cũ trước — tiền về trả nợ cũ trước. */
	private static function bc_con_thieu( $ma_cs ) {
		$ra = array();
		foreach ( VHJP_Nguon::doc( 'JP_Reports' ) as $r ) {
			if ( VHJP_BaoCao::TT_HOAN_TAT !== VHJP_Doc::str( $r['status'] ) ) { continue; }
			if ( VHJP_Doc::str( $r['locationId'] ) !== VHJP_Doc::str( $ma_cs ) ) { continue; }
			$phai = self::so( $r['totalSubmit'] );
			$da   = self::so( isset( $r['ktXacNhan'] ) ? $r['ktXacNhan'] : 0 );
			if ( $phai - $da <= 0 ) { continue; }
			$ra[] = $r;
		}
		usort( $ra, function ( $a, $b ) {
			return strcmp( VHJP_Doc::ngay( $a['toDate'] ), VHJP_Doc::ngay( $b['toDate'] ) );
		} );
		return $ra;
	}

	/**
	 * `jpReconPreview` — đọc tệp, khớp cơ sở, dựng kế hoạch ghi. KHÔNG ghi gì.
	 *
	 * @param array $ghi_de seq => locationId, do kế toán chọn tay cho dòng mơ hồ.
	 */
	public static function xem_truoc( $u, $kind, $rows, $ghi_de = array() ) {
		self::can_kt( $u );
		$kind = strtoupper( VHJP_Doc::str( $kind ) );
		if ( '' === $kind ) { $kind = 'CK'; }
		$dong = self::doc_dong( $rows );
		if ( ! $dong ) { throw new Exception( 'Tệp không có dòng nào có số tiền lớn hơn 0' ); }

		$lo  = self::ma_lo( $kind, $dong );
		$cu  = self::lo_da_ap( $lo );
		$cs  = self::ds_coso();
		$ghi_de = is_array( $ghi_de ) ? $ghi_de : array();

		$khop = array(); $mo_ho = array(); $khong = array();
		$theo_cs = array();
		foreach ( $dong as $x ) {
			$ung = self::doan_coso( $x['desc'], $cs );
			$chon = VHJP_Doc::str( isset( $ghi_de[ $x['seq'] ] ) ? $ghi_de[ $x['seq'] ] : '' );
			if ( '' === $chon && 1 === count( $ung ) ) { $chon = $ung[0]; }

			if ( '' !== $chon ) {
				$x['locationId'] = $chon;
				$khop[] = $x;
				if ( ! isset( $theo_cs[ $chon ] ) ) { $theo_cs[ $chon ] = array(); }
				$theo_cs[ $chon ][] = $x;
				continue;
			}
			$x['candidates'] = $ung;
			/* 🔴 Nhiều hơn một ứng viên thì KHÔNG tự chọn — xem khối 🔴 thứ ba ở đầu tệp. */
			if ( count( $ung ) > 1 ) { $mo_ho[] = $x; } else { $khong[] = $x; }
		}

		/* Kế hoạch ghi: tiền của mỗi cơ sở rải vào báo cáo còn thiếu, CŨ TRƯỚC. */
		$plan = array(); $bang_cs = array();
		$tong = array( 'phaiNop' => 0, 'tienFile' => 0, 'daGhiNhan' => 0, 'lech' => 0,
			'seGhi' => 0, 'con' => 0 );
		foreach ( $theo_cs as $ma_cs => $ds_dong ) {
			$tien = 0; $khop_bang = array();
			foreach ( $ds_dong as $x ) {
				$tien += $x['amount'];
				$k = '' !== $x['code'] ? $x['code'] : ( '' !== $x['desc'] ? $x['desc'] : '#' . $x['seq'] );
				$khop_bang[] = $k;
			}
			$con   = $tien;
			$phai_cs = 0; $da_cs = 0;
			foreach ( self::bc_con_thieu( $ma_cs ) as $r ) {
				$phai = self::so( $r['totalSubmit'] );
				$da   = self::so( isset( $r['ktXacNhan'] ) ? $r['ktXacNhan'] : 0 );
				$phai_cs += $phai;
				$da_cs   += $da;
				if ( $con <= 0 ) { continue; }
				$them = min( $con, $phai - $da );
				if ( $them <= 0 ) { continue; }
				$plan[] = array(
					'reportId' => VHJP_Doc::str( $r['id'] ),
					'locationId' => $ma_cs, 'locationName' => VHJP_Doc::str( $r['locationName'] ),
					'period' => VHJP_Doc::dmy( $r['fromDate'] ) . ' – ' . VHJP_Doc::dmy( $r['toDate'] ),
					'total' => $phai, 'paidBefore' => $da, 'add' => $them,
					'paidAfter' => $da + $them );
				$con -= $them;
			}
			if ( $con > 0 ) {
				/* Tiền không báo cáo nào nhận hết được — in RIÊNG, và KHÔNG cộng vào "sẽ ghi". */
				$plan[] = array( 'reportId' => '', 'locationId' => $ma_cs,
					'locationName' => self::ten_cs( $ma_cs, $cs ), 'period' => '',
					'total' => 0, 'paidBefore' => 0, 'add' => $con, 'paidAfter' => 0 );
			}
			$bang_cs[] = array( 'locationId' => $ma_cs, 'locationName' => self::ten_cs( $ma_cs, $cs ),
				'khopBang' => implode( ' · ', array_slice( $khop_bang, 0, 6 ) ),
				'soDong' => count( $ds_dong ), 'phaiNop' => $phai_cs, 'tienFile' => $tien,
				'daGhiNhan' => $da_cs, 'lech' => $tien - ( $phai_cs - $da_cs ),
				'seGhi' => $tien - $con, 'con' => $con );
			$tong['phaiNop']   += $phai_cs;
			$tong['tienFile']  += $tien;
			$tong['daGhiNhan'] += $da_cs;
			$tong['lech']      += $tien - ( $phai_cs - $da_cs );
			$tong['seGhi']     += $tien - $con;
			$tong['con']       += $con;
		}
		usort( $bang_cs, function ( $a, $b ) { return strcmp( $a['locationName'], $b['locationName'] ); } );

		return array( 'ok' => true, 'kind' => $kind, 'batchId' => $lo,
			/* Dải thẻ số của giao diện đọc ở đây. Bốn số PHẢI cộng ra tổng: dòng nào cũng rơi
			   vào đúng một trong ba nhóm, nên `matched + ambiguous + unmatched = rows`. Lệch
			   một dòng là có dòng bị nuốt mất mà không ai biết. */
			'stat' => array( 'rows' => count( $dong ), 'matched' => count( $khop ),
				'ambiguous' => count( $mo_ho ), 'unmatched' => count( $khong ) ),
			'alreadyApplied' => (bool) $cu,
			'appliedAt' => $cu ? VHJP_Doc::str( $cu['at'] ) : '',
			'matched' => $khop, 'ambiguous' => $mo_ho, 'unmatched' => $khong,
			'plan' => $plan, 'theoCoSo' => $bang_cs, 'tongCoSo' => $tong );
	}

	private static function ten_cs( $id, $ds ) {
		foreach ( $ds as $l ) { if ( $l['id'] === $id ) { return '' !== $l['name'] ? $l['name'] : $id; } }
		return VHJP_Doc::str( $id );
	}

	/** Lô này đã áp chưa (và chưa bị huỷ). */
	private static function lo_da_ap( $lo ) {
		foreach ( VHJP_Nguon::doc( 'JP_ReconLog' ) as $x ) {
			if ( VHJP_Doc::str( $x['batchId'] ) !== VHJP_Doc::str( $lo ) ) { continue; }
			if ( '' !== VHJP_Doc::str( $x['undoneBy'] ) ) { continue; }
			return $x;
		}
		return null;
	}

	/**
	 * `jpReconApply` — ghi kế hoạch vào sổ.
	 *
	 * 🔴 DỰNG LẠI KẾ HOẠCH TỪ ĐẦU, không nhận kế hoạch do giao diện gửi lên. Giao diện giữ bản
	 *    dựng lúc xem trước; giữa hai lượt ấy có thể đã có người xác nhận tay một khoản, và ghi
	 *    theo bản cũ là ghi đè lên việc vừa làm của người khác.
	 */
	public static function ap( $u, $kind, $rows, $ghi_de = array() ) {
		self::can_kt( $u );
		$xt = self::xem_truoc( $u, $kind, $rows, $ghi_de );
		if ( ! empty( $xt['alreadyApplied'] ) ) {
			return array( 'ok' => false, 'batchId' => $xt['batchId'],
				'msg' => 'Lô này đã áp trước đó (' . $xt['batchId'] . ') — không áp lại được. '
					. 'Muốn làm lại thì huỷ lô cũ ở Lịch sử.' );
		}

		$n = 0; $tien = 0; $alloc = array();
		foreach ( $xt['plan'] as $p ) {
			if ( '' === $p['reportId'] ) { continue; }
			VHJP_NopTien::xac_nhan( $u, $p['reportId'], $p['paidAfter'] );
			VHJP_Nguon::sua( 'JP_Reports', $p['reportId'], array(
				'ktCachThu' => 'QR' === $xt['kind'] ? 'QR' : ( 'TM' === $xt['kind'] ? 'TM' : 'CK' ),
				'ktGhiChu' => 'Đối soát lô ' . $xt['batchId'] ) );
			$alloc[] = array( 'reportId' => $p['reportId'], 'add' => $p['add'],
				'truoc' => $p['paidBefore'] );
			$n++;
			$tien += $p['add'];
		}

		VHJP_Nguon::them( 'JP_ReconLog', array(
			'at' => gmdate( 'Y-m-d H:i:s', time() + 7 * 3600 ),
			'who' => VHJP_Doc::str( $u['hoTen'] ), 'batchId' => $xt['batchId'], 'kind' => $xt['kind'],
			'soDong' => count( $xt['matched'] ) + count( $xt['ambiguous'] ) + count( $xt['unmatched'] ),
			'matched' => count( $xt['matched'] ), 'ambiguous' => count( $xt['ambiguous'] ),
			'amount' => $tien, 'note' => '', 'allocJson' => wp_json_encode( $alloc ) ) );
		VHJP_NhatKy::ghi( $u, 'RECON_APPLY', '', $xt['batchId'], $n . ' báo cáo · ' . $tien );

		return array( 'ok' => true, 'batchId' => $xt['batchId'], 'soBaoCao' => $n, 'soTien' => $tien,
			'msg' => 'Đã áp lô ' . $xt['batchId'] . ': ghi ' . number_format( $tien, 0, ',', '.' )
				. 'đ vào ' . $n . ' báo cáo.'
				. ( $xt['tongCoSo']['con'] > 0
					? ' ⚠️ Còn ' . number_format( $xt['tongCoSo']['con'], 0, ',', '.' )
						. 'đ chưa báo cáo nào nhận được — xử lý tay.' : '' ) );
	}

	/** `jpReconHistory` */
	public static function lich_su( $u, $gioi_han = 30 ) {
		self::can_kt( $u );
		$ds = VHJP_Nguon::doc( 'JP_ReconLog' );
		usort( $ds, function ( $a, $b ) { return (int) $b['stt'] - (int) $a['stt']; } );
		$ds = array_slice( $ds, 0, max( 1, (int) $gioi_han ) );
		$ra = array();
		foreach ( $ds as $x ) {
			$al = json_decode( (string) $x['allocJson'], true );
			$x['undone'] = '' !== VHJP_Doc::str( $x['undoneBy'] );
			$x['rows']   = (int) VHJP_Doc::num( $x['soDong'] );   /* tên giao diện đang đọc */
			$x['amount'] = self::so( $x['amount'] );
			/* Lô áp trước khi có `allocJson` KHÔNG huỷ được: không biết nó đã cộng bao nhiêu vào
			   báo cáo nào thì trừ lại là đoán, mà đoán ở đây là xoá tiền của người khác. Nói
			   thẳng ra ở cột nút, đừng in một nút bấm vào thì báo lỗi. */
			$x['coTheHuy'] = ! $x['undone'] && is_array( $al ) && $al;
			unset( $x['allocJson'] );   /* không cần ở màn danh sách, và nó dài */
			$ra[] = $x;
		}
		return $ra;
	}

	/**
	 * `jpReconUndo` — huỷ một lô đã áp, trừ lại đúng phần nó đã ghi.
	 *
	 * ⚠️ TRỪ LẠI THEO `allocJson` của chính lô ấy, không đặt về 0: giữa hai lúc có thể đã có
	 *    thêm một lô khác hoặc một lượt xác nhận tay ghi vào cùng báo cáo, và đặt về 0 là xoá
	 *    luôn phần của người khác.
	 */
	public static function huy_lo( $u, $lo, $ly_do ) {
		self::can_kt( $u );
		$lo    = VHJP_Doc::str( $lo );
		$ly_do = VHJP_Doc::str( $ly_do );
		if ( '' === $ly_do ) { throw new Exception( 'Phải ghi lý do huỷ lô' ); }
		$x = self::lo_da_ap( $lo );
		if ( ! $x ) { throw new Exception( 'Không tìm thấy lô đang có hiệu lực với mã ' . $lo ); }

		$alloc = json_decode( (string) $x['allocJson'], true );
		$alloc = is_array( $alloc ) ? $alloc : array();
		if ( ! $alloc ) {
			throw new Exception( 'Lô ' . $lo . ' không lưu phân bổ nên không huỷ tự động được — '
				. 'trừ lại tay ở "Xác nhận nộp tay" rồi ghi lý do.' );
		}
		$n = 0; $thieu = array();
		foreach ( $alloc as $a ) {
			$id = VHJP_Doc::str( isset( $a['reportId'] ) ? $a['reportId'] : '' );
			$r  = '' !== $id ? VHJP_Nguon::tim_mot( 'JP_Reports', 'id', $id ) : null;
			if ( ! $r ) { $thieu[] = $id; continue; }
			$dang = self::so( isset( $r['ktXacNhan'] ) ? $r['ktXacNhan'] : 0 );
			VHJP_NopTien::xac_nhan( $u, $id, max( 0, $dang - self::so( $a['add'] ) ) );
			$n++;
		}
		VHJP_Nguon::sua( 'JP_ReconLog', $x['stt'], array(
			'undoneAt' => gmdate( 'Y-m-d H:i:s', time() + 7 * 3600 ),
			'undoneBy' => VHJP_Doc::str( $u['hoTen'] ),
			'undoneReason' => $ly_do ) );
		VHJP_NhatKy::ghi( $u, 'RECON_UNDO', '', $lo, $ly_do );
		return array( 'ok' => true, 'daTru' => $n, 'thieu' => $thieu,
			'msg' => 'Đã huỷ lô ' . $lo . ', trừ lại ở ' . $n . ' báo cáo.'
				. ( $thieu ? ' ⚠️ ' . count( $thieu ) . ' báo cáo không còn — số của chúng chưa trừ.' : '' ) );
	}

	/* ══════════════════════════════════════════════════════════════ XÁC NHẬN TAY ═════════ */

	/**
	 * `jpConfirmPaidManual` — kế toán ghi tay một khoản đã nhận.
	 *
	 * @param string $mode 'ADD' = cộng thêm · 'SET' = đặt lại TỔNG.
	 *
	 * 🔴 LÝ DO BẮT BUỘC. Một con số ghi tay không có lý do thì tháng sau không ai giải thích
	 *    được nó ở đâu ra — và nó nằm ngay trong sổ công nợ.
	 */
	public static function xac_nhan_tay( $u, $ma_bc, $tien, $ngay, $ly_do, $mode, $cach ) {
		self::can_kt( $u );
		$ma_bc = VHJP_Doc::str( $ma_bc );
		$r     = VHJP_Nguon::tim_mot( 'JP_Reports', 'id', $ma_bc );
		if ( ! $r ) { throw new Exception( 'Không tìm thấy báo cáo' ); }
		$ly_do = VHJP_Doc::str( $ly_do );
		if ( mb_strlen( $ly_do ) < 3 ) {
			throw new Exception( 'Phải ghi lý do xác nhận tay (ai nộp, nộp ở đâu) — '
				. 'một con số không có lý do thì tháng sau không ai giải thích được.' );
		}
		$c    = self::cach_thu( $cach );
		$tien = self::so( $tien );
		if ( $tien <= 0 ) { throw new Exception( 'Nhập số tiền lớn hơn 0' ); }

		$dang = self::so( isset( $r['ktXacNhan'] ) ? $r['ktXacNhan'] : 0 );
		$moi  = 'SET' === strtoupper( VHJP_Doc::str( $mode ) ) ? $tien : $dang + $tien;
		$phai = self::so( $r['totalSubmit'] );

		/* Nhận VƯỢT vẫn ghi được — tiền đã về thật thì sổ phải nói đúng — nhưng phải BÁO. */
		$vuot = $moi > $phai;
		if ( $vuot ) {
			VHJP_Nguon::sua( 'JP_Reports', $ma_bc, array( 'ktXacNhan' => $moi,
				'ktXacNhanBy' => VHJP_Doc::str( $u['hoTen'] ),
				'ktXacNhanAt' => VHJP_Ma::hom_nay() . ' ' . gmdate( 'H:i:s' ),
				'paid' => 1, 'paidDate' => VHJP_Doc::ngay( $ngay ) ?: VHJP_Ma::hom_nay(),
				'payStatus' => VHJP_NopTien::DA_NOP ) );
		} else {
			VHJP_NopTien::xac_nhan( $u, $ma_bc, $moi );
			if ( '' !== VHJP_Doc::ngay( $ngay ) ) {
				VHJP_Nguon::sua( 'JP_Reports', $ma_bc, array( 'paidDate' => VHJP_Doc::ngay( $ngay ) ) );
			}
		}
		VHJP_Nguon::sua( 'JP_Reports', $ma_bc, array( 'ktCachThu' => $c, 'ktGhiChu' => $ly_do ) );
		VHJP_NhatKy::ghi( $u, 'XAC_NHAN_TAY', $ma_bc, '', $moi . ' · ' . $c . ' · ' . $ly_do );

		return array( 'ok' => true, 'ktXacNhan' => $moi, 'phaiNop' => $phai, 'cachThu' => $c,
			'vuot' => $vuot,
			'msg' => ( 'SET' === strtoupper( VHJP_Doc::str( $mode ) ) ? 'Đã đặt lại tổng đã nhận thành '
					: 'Đã cộng thêm, tổng đã nhận là ' )
				. number_format( $moi, 0, ',', '.' ) . 'đ'
				. ( $vuot ? ' — VƯỢT số phải nộp ' . number_format( $phai, 0, ',', '.' ) . 'đ.' : '.' ) );
	}

	/* ══════════════════════════════════════════════════════════ TÁCH THEO CÁCH THU ═══════ */

	/**
	 * Gom tiền đã nộp của từng báo cáo theo cách thu, đọc thẳng `JP_Payments`.
	 *
	 * 🔴 CÁCH LẠ KHÔNG ĐƯỢC QUY VỀ CHUYỂN KHOẢN. Bảng chỉ từng ghi `TM`/`CK`, nhưng dữ liệu
	 *    nhập từ đời trước có thể mang chữ khác. Đoán nó là `CK` là NÓI SAI một câu không ai
	 *    kiểm được; ném vào `KHAC` thì sổ công nợ in ra đúng cái đang không biết.
	 */
	public static function gom_cach() {
		$ra = array();
		foreach ( VHJP_Nguon::doc( 'JP_Payments' ) as $p ) {
			$k = VHJP_Doc::str( $p['reportId'] );
			if ( '' === $k ) { continue; }
			if ( ! isset( $ra[ $k ] ) ) { $ra[ $k ] = array( 'TM' => 0, 'CK' => 0, 'QR' => 0, 'KHAC' => 0 ); }
			$c = strtoupper( VHJP_Doc::str( $p['method'] ) );
			if ( ! in_array( $c, self::CACH, true ) ) { $c = 'KHAC'; }
			$ra[ $k ][ $c ] += self::so( $p['amount'] );
		}
		return $ra;
	}

	/**
	 * Tách số KẾ TOÁN ĐÃ NHẬN của MỘT báo cáo ra bốn cách thu: TM · CK · QR · KHAC.
	 *
	 * =============================================================================================
	 * 🔴 CHỈ CÓ MỘT CHỖ TÍNH, VÌ CÓ HAI MÀN ĐỌC NÓ.
	 * =============================================================================================
	 * Sổ công nợ in cột "Chưa rõ cách thu" kèm NÚT đi thẳng sang màn khai bù. Hai màn tính theo
	 * hai luật là kế toán bấm nút rồi thấy danh sách rỗng — hoặc tệ hơn, thấy một danh sách có
	 * tổng khác con số vừa đọc, và không biết tin số nào.
	 *
	 * Luật:
	 *   · Ghép trước theo CÁC LẦN NỘP THẬT, chia theo tỉ lệ (kế toán xác nhận ít hơn tổng đã nộp
	 *     thì phần được xác nhận trải đều theo các đường tiền đã về, không dồn vào một đường).
	 *   · Phần xác nhận VƯỢT các lần nộp (đối soát ngân hàng, xác nhận tay) đi theo `ktCachThu`.
	 *   · Không khai `ktCachThu` thì vào `KHAC` — KHÔNG mặc định tiền mặt.
	 *
	 * ⚠️ Sai số làm tròn dồn hết vào cách CUỐI có tiền, nên tổng bốn ô LUÔN đúng bằng đã nhận.
	 *    Bốn ô cộng không ra tổng là kế toán mất buổi sáng đi tìm một đồng không tồn tại.
	 */
	public static function tach_cach_thu( $r, $gom = null ) {
		$ra   = array( 'TM' => 0, 'CK' => 0, 'QR' => 0, 'KHAC' => 0 );
		$nhan = self::so( isset( $r['ktXacNhan'] ) ? $r['ktXacNhan'] : 0 );
		if ( $nhan <= 0 ) { return $ra; }

		if ( null === $gom ) { $gom = self::gom_cach(); }
		$id = VHJP_Doc::str( $r['id'] );
		$p  = isset( $gom[ $id ] ) ? $gom[ $id ] : array( 'TM' => 0, 'CK' => 0, 'QR' => 0, 'KHAC' => 0 );
		$pt = $p['TM'] + $p['CK'] + $p['QR'] + $p['KHAC'];

		$khop = min( $nhan, $pt );
		if ( $khop > 0 && $pt > 0 ) {
			$cuoi = '';
			foreach ( $p as $c => $v ) { if ( $v > 0 ) { $cuoi = $c; } }
			$con_lai = $khop;
			foreach ( $p as $c => $v ) {
				if ( $v <= 0 ) { continue; }
				if ( $c === $cuoi ) { $ra[ $c ] += $con_lai; break; }
				$x = round( $v * $khop / $pt );
				$ra[ $c ] += $x;
				$con_lai  -= $x;
			}
		}

		$con = $nhan - $khop;
		if ( $con > 0 ) {
			$c = strtoupper( VHJP_Doc::str( isset( $r['ktCachThu'] ) ? $r['ktCachThu'] : '' ) );
			$ra[ in_array( $c, self::CACH, true ) ? $c : 'KHAC' ] += $con;
		}
		return $ra;
	}

	/**
	 * `jpChuaKhaiCachThu` — đã nhận tiền mà không biết vào bằng cách nào.
	 *
	 * ⚠️ Đo bằng Ô `KHAC` của `tach_cach_thu()`, KHÔNG bằng "ktCachThu rỗng". Báo cáo nhận đủ
	 *    qua các lần nộp có ghi cách thu thì chẳng còn gì để khai — bắt kế toán khai lại là
	 *    bắt họ làm một việc vô nghĩa, rồi lần sau họ bỏ qua cả danh sách này.
	 */
	public static function chua_khai_cach( $u ) {
		self::can_kt( $u );
		$gom  = self::gom_cach();
		$rows = array(); $tong = 0;
		foreach ( VHJP_Nguon::doc( 'JP_Reports' ) as $r ) {
			$t = self::tach_cach_thu( $r, $gom );
			if ( $t['KHAC'] <= 0 ) { continue; }
			$rows[] = array( 'id' => VHJP_Doc::str( $r['id'] ),
				'coSo' => VHJP_Doc::str( $r['locationName'] ),
				'kyTu' => VHJP_Doc::ngay( $r['fromDate'] ),
				'kyDen' => VHJP_Doc::ngay( $r['toDate'] ),
				'ngayNop' => VHJP_Doc::ngay( $r['paidDate'] ),
				'userName' => VHJP_Doc::str( $r['userName'] ),
				'daNhan' => self::so( isset( $r['ktXacNhan'] ) ? $r['ktXacNhan'] : 0 ),
				'chuaKhai' => $t['KHAC'] );
			$tong += $t['KHAC'];
		}
		usort( $rows, function ( $a, $b ) { return strcmp( $b['id'], $a['id'] ); } );
		return array( 'ok' => true, 'rows' => $rows, 'soBaoCao' => count( $rows ), 'tong' => $tong );
	}

	/** `jpKhaiCachThu` — khai bù cách thu cho một khoản đã nhận. */
	public static function khai_cach( $u, $ma_bc, $cach, $ly_do = '' ) {
		self::can_kt( $u );
		$ma_bc = VHJP_Doc::str( $ma_bc );
		$r     = VHJP_Nguon::tim_mot( 'JP_Reports', 'id', $ma_bc );
		if ( ! $r ) { throw new Exception( 'Không tìm thấy báo cáo' ); }
		if ( self::so( isset( $r['ktXacNhan'] ) ? $r['ktXacNhan'] : 0 ) <= 0 ) {
			throw new Exception( 'Báo cáo này chưa nhận đồng nào — chưa có gì để khai cách thu.' );
		}
		$c = self::cach_thu( $cach );
		VHJP_Nguon::sua( 'JP_Reports', $ma_bc, array( 'ktCachThu' => $c,
			'ktGhiChu' => VHJP_Doc::str( $ly_do ) ) );
		VHJP_NhatKy::ghi( $u, 'KHAI_CACH_THU', $ma_bc, '', $c );
		return array( 'ok' => true, 'cachThu' => $c,
			'msg' => 'Đã khai cách thu: ' . ( 'TM' === $c ? 'tiền mặt'
				: ( 'CK' === $c ? 'chuyển khoản' : 'QR' ) ) . '.' );
	}

	/* ═══════════════════════════════════════════════════ LUỒNG NGÂN HÀNG TỰ ĐỘNG ═════════ */

	/** Trạng thái của một dòng `JP_BankGD`. */
	const GD_CHO        = '';
	const GD_NHAN_TRUOC = 'NHAN_TRUOC';
	const GD_DA_AP      = 'DA_AP';

	/** Cơ sở kèm mã định danh — luồng ngân hàng dò bằng MÃ, không dò bằng tên. */
	private static function ds_coso_day() {
		$ra = array();
		foreach ( VHJP_Nguon::doc( 'JP_Locations' ) as $l ) {
			$ra[] = array( 'id' => VHJP_Doc::str( $l['id'] ), 'code' => VHJP_Doc::str( $l['code'] ),
				'name' => VHJP_Doc::str( $l['name'] ), 'maKH' => VHJP_Doc::str( $l['maKH'] ),
				'dinhDanh' => VHJP_Doc::str( $l['maDinhDanh'] ),
				'active' => (bool) VHJP_Doc::num( $l['active'] ) );
		}
		return $ra;
	}

	/**
	 * Cơ sở nào được nhắc trong nội dung chuyển khoản, DÒ THEO MÃ ĐỊNH DANH.
	 *
	 * 🔴 KHÁC HẲN `doan_coso()` CỦA ĐƯỜNG ĐỌC FILE — VÀ KHÁC CÓ CHỦ Ý.
	 * Đọc file là kế toán ngồi xem từng dòng rồi mới bấm, nên dò rộng theo tên/mã KH là tiện.
	 * Luồng này chạy NỀN, không ai nhìn: dò theo tên là một cơ sở tên "JP Mall" nuốt luôn tiền
	 * của "JP Mall 2", và không ai phát hiện vì sổ vẫn cân. Mã định danh là chuỗi cơ sở tự khai,
	 * kế toán kiểm được, nên nó là thứ duy nhất đủ chắc để máy tự ghi tiền.
	 */
	private static function dinh_danh_trong( $noi_dung, $ds_cs ) {
		$n  = preg_replace( '/[^a-z0-9]/', '', VHJP_Doc::norm( $noi_dung ) );
		$ra = array();
		foreach ( $ds_cs as $l ) {
			$k = preg_replace( '/[^a-z0-9]/', '', VHJP_Doc::norm( $l['dinhDanh'] ) );
			if ( strlen( $k ) < 3 ) { continue; }      /* mã quá ngắn khớp với mọi nội dung */
			if ( false !== strpos( $n, $k ) ) { $ra[] = $l['id']; }
		}
		return array_values( array_unique( $ra ) );
	}

	/**
	 * `jpDocGiaoDichNganHang` — đọc thử mấy dòng đầu của sổ giao dịch, để soát app hiểu đúng cột.
	 *
	 * ⚠️ ĐỌC THỬ KHÔNG ĐƯỢC GHI GÌ. Đây là bước người ta bấm để KIỂM, không phải để chạy.
	 *
	 * ⚠️ Thứ tự cột trả về là CỐ Ý: giao diện tô vai trò theo VỊ TRÍ cột 1·2·5·8 (nó chép bản đồ
	 *    cột của bảng tính gốc, không đọc được hằng số máy chủ). Xếp đúng thứ tự ấy thì bảng kiểm
	 *    nói đúng; xếp khác là màn hình dạy sai chính cái nó đi kiểm.
	 */
	public static function doc_giao_dich( $u, $n = 5 ) {
		self::can_kt( $u );
		$n  = max( 1, (int) $n );
		$ds = VHJP_Nguon::doc( 'JP_BankGD' );
		usort( $ds, function ( $a, $b ) { return strcmp( (string) $b['refId'], (string) $a['refId'] ); } );

		$cot = array( 'refId', 'ngay', 'locationId', 'trangThai', 'soTien', 'reportId', 'apLuc', 'noiDung' );
		$tieu_de = array( 'refId (mã tham chiếu)', 'ngay', 'locationId', 'trangThai',
			'soTien (tiền vào)', 'reportId', 'apLuc', 'noiDung' );

		$mau = array(); $rows = array();
		foreach ( array_slice( $ds, 0, $n ) as $x ) {
			$d = array();
			foreach ( $cot as $c ) { $d[] = VHJP_Doc::str( isset( $x[ $c ] ) ? $x[ $c ] : '' ); }
			$mau[]  = $d;
			$rows[] = array( 'refId' => VHJP_Doc::str( $x['refId'] ),
				'date' => VHJP_Doc::ngay( $x['ngay'] ), 'amount' => self::so( $x['soTien'] ),
				'desc' => VHJP_Doc::str( $x['noiDung'] ) );
		}

		$chua_khai = 0;
		foreach ( self::ds_coso_day() as $l ) {
			if ( $l['active'] && '' === $l['dinhDanh'] ) { $chua_khai++; }
		}
		$canh = '';
		if ( ! $ds ) {
			$canh = 'Sổ giao dịch ngân hàng đang TRỐNG — chưa nguồn nào đẩy dữ liệu vào bảng '
				. 'JP_BankGD. Trong lúc chờ, dùng đường ĐỌC FILE sao kê ở trên.';
		} elseif ( $chua_khai ) {
			$canh = $chua_khai . ' cơ sở đang hoạt động CHƯA khai mã định danh — tiền của chúng '
				. 'sẽ không tự vào sổ được. Khai ở Cấu hình → Cơ sở.';
		}

		return array( 'ok' => true,
			'nguon' => 'Bảng JP_BankGD trong cơ sở dữ liệu — cột đọc theo TÊN, không theo vị trí.',
			'canhBao' => $canh, 'tieuDe' => $tieu_de, 'mau' => $mau,
			'soDong' => count( $ds ), 'tong' => count( $ds ), 'rows' => $rows,
			'daXacNhanCot' => (bool) get_option( self::O_XAC_NHAN_COT, false ),
			'msg' => $ds ? 'Đọc được ' . count( $ds ) . ' giao dịch trong sổ.'
				: 'Sổ giao dịch ngân hàng đang TRỐNG.' );
	}

	/** `jpXacNhanCotNganHang` — cổng an toàn của luồng tự động. */
	public static function xac_nhan_cot( $u, $dong = true ) {
		self::can_kt( $u );
		$dong = (bool) $dong;
		update_option( self::O_XAC_NHAN_COT, $dong ? 1 : 0 );
		VHJP_NhatKy::ghi( $u, 'BANK_XAC_NHAN_COT', '', '', $dong ? 'BẬT' : 'TẮT' );
		return array( 'ok' => true, 'daXacNhan' => $dong,
			'msg' => $dong
				? 'Đã xác nhận app đọc đúng cột — luồng tự động được phép ghi tiền vào sổ.'
				: 'Đã TẮT cổng — luồng tự động vẫn chạy nhưng KHÔNG ghi gì vào sổ.' );
	}

	/** `jpLichDoiSoatNH` */
	public static function lich( $u ) {
		self::can_kt( $u );
		$phut = (int) get_option( self::O_LICH_PHUT, 0 );
		$ok   = (bool) get_option( self::O_XAC_NHAN_COT, false );
		return array( 'ok' => true, 'phut' => $phut, 'co' => $phut > 0, 'xacNhanCot' => $ok,
			'msg' => $phut > 0
				? 'Đang chạy mỗi ' . $phut . ' phút'
					. ( $ok ? ' và CÓ tự ghi tiền vào sổ.' : ' nhưng KHÔNG ghi gì — cổng xác nhận cột đang tắt.' )
				: 'Lịch đang TẮT — chỉ chạy khi có người bấm.' );
	}

	/**
	 * `jpDatLichDoiSoatNH` — đặt nhịp chạy nền, `0` là tắt.
	 *
	 * ⚠️ KHÔNG chặn nhịp dày. Nhịp 1 phút chẳng lợi gì (ô nguồn tự tính lại theo nhịp của chính
	 *    nó, app không ép được) nhưng cũng KHÔNG hại: giao dịch đã áp bị bỏ qua theo mã tham
	 *    chiếu. Mà ô chọn của giao diện có sẵn mức 1 phút — chặn ở máy chủ là để một nút bấm vào
	 *    thì báo lỗi, kiểu hỏng khó chịu nhất vì trông như app đang gãy.
	 */
	public static function dat_lich( $u, $phut ) {
		self::can_kt( $u );
		$p = (int) $phut;
		if ( $p < 0 ) { $p = 0; }
		if ( $p > 1440 ) { $p = 1440; }
		update_option( self::O_LICH_PHUT, $p );
		VHJP_NhatKy::ghi( $u, 'BANK_DAT_LICH', '', '', $p . ' phút' );
		$t = self::lich( $u );
		$t['msg'] = $p > 0
			? 'Đã đặt lịch chạy mỗi ' . $p . ' phút.'
				. ( $p < 5 ? ' (Nhịp dày hơn 5 phút hầu như không thấy tiền sớm hơn.)' : '' )
			: 'Đã tắt lịch.';
		return $t;
	}

	/**
	 * `jpDoiSoatNganHang` — dò sổ giao dịch, ghi tiền về vào báo cáo còn thiếu.
	 *
	 * =============================================================================================
	 * 🔴 CỔNG "XÁC NHẬN CỘT" CHẶN MỌI LƯỢT GHI
	 * =============================================================================================
	 * Chưa ai soát bảng đọc thử thì `$ghi` bị ép về `false`, kể cả khi người gọi truyền `true`.
	 * Đọc nhầm cột tiền là ghi nhận nộp sai số hàng loạt, mà sổ vẫn cân nên không phép kiểm nào
	 * báo. Một cổng phải bật bằng tay rẻ hơn nhiều so với đi dò lại một tháng công nợ.
	 *
	 * =============================================================================================
	 * 🔴 TIỀN VỀ TRƯỚC BÁO CÁO THÌ GIỮ RIÊNG, KHÔNG VỨT VÀO HÀNG CHỜ
	 * =============================================================================================
	 * Cơ sở chuyển tiền trước khi kế toán duyệt xong báo cáo là chuyện thường. Ném nó vào hàng chờ
	 * thì hàng chờ dài mãi và kế toán đi xác nhận tay — rồi lượt sau máy khớp thêm một lần nữa,
	 * TIỀN VÀO SỔ HAI LẦN. Nên đánh dấu `NHAN_TRUOC`: đã ghi nhận, đang giữ, và tự khớp vào đúng
	 * báo cáo ngay lượt chạy kế tiếp sau khi báo cáo được duyệt.
	 *
	 * ⚠️ Thẻ "Của thương hiệu khác" ở giao diện sẽ đếm 0: JP KHÔNG có cách nào biết một mã lạ là
	 *    của thương hiệu khác hay là cơ sở JP chưa khai mã. Đoán bừa là dán nhãn sai cho tiền thật.
	 *    Mọi mã không nhận ra đều nằm ở "Không nhận ra mã" — đúng những gì hệ thật sự biết.
	 */
	public static function doi_soat_nh( $u, $ghi = false ) {
		self::can_kt( $u );
		$ghi = (bool) $ghi;
		$ok  = (bool) get_option( self::O_XAC_NHAN_COT, false );
		if ( $ghi && ! $ok ) { $ghi = false; }

		$cs = self::ds_coso_day();
		$gd = VHJP_Nguon::doc( 'JP_BankGD' );

		/* Ảnh chụp công nợ TRƯỚC lượt này — `lech` chỉ có nghĩa khi so với mốc trước khi ghi. */
		$truoc = array();
		foreach ( $cs as $l ) { $truoc[ $l['id'] ] = array( 'phaiNop' => 0, 'daGhiNhan' => 0 ); }
		foreach ( VHJP_Nguon::doc( 'JP_Reports' ) as $r ) {
			if ( VHJP_BaoCao::TT_HOAN_TAT !== VHJP_Doc::str( $r['status'] ) ) { continue; }
			$ma = VHJP_Doc::str( $r['locationId'] );
			if ( ! isset( $truoc[ $ma ] ) ) { continue; }
			$phai = self::so( $r['totalSubmit'] );
			$da   = self::so( isset( $r['ktXacNhan'] ) ? $r['ktXacNhan'] : 0 );
			$truoc[ $ma ]['phaiNop']   += max( 0, $phai - $da );
			$truoc[ $ma ]['daGhiNhan'] += $da;
		}

		$theo = array();
		foreach ( $cs as $l ) {
			$theo[ $l['id'] ] = array( 'soGD' => 0, 'tienVaoNH' => 0, 'seGhi' => 0, 'nhanTruoc' => 0 );
		}
		$cho = array(); $tien_cho = 0; $ap = 0; $tien_ap = 0;
		$khop_truoc = array( 'gan' => 0, 'tien' => 0 );

		/* Khoản NHẬN TRƯỚC đi trước hàng mới: nợ cũ nhất được trả trước, và lượt này mới là lúc
		   báo cáo của nó vừa được duyệt. */
		usort( $gd, function ( $a, $b ) {
			$x = ( self::GD_NHAN_TRUOC === VHJP_Doc::str( $a['trangThai'] ) ) ? 0 : 1;
			$y = ( self::GD_NHAN_TRUOC === VHJP_Doc::str( $b['trangThai'] ) ) ? 0 : 1;
			if ( $x !== $y ) { return $x - $y; }
			return strcmp( VHJP_Doc::ngay( $a['ngay'] ), VHJP_Doc::ngay( $b['ngay'] ) );
		} );

		foreach ( $gd as $g ) {
			$tt   = VHJP_Doc::str( $g['trangThai'] );
			$tien = self::so( $g['soTien'] );
			$ref  = VHJP_Doc::str( $g['refId'] );

			/* Đã áp rồi thì chỉ CỘNG VÀO "tiền vào NH" — đó là tiền thật đã về, không phải việc
			   còn phải làm. Bỏ ra khỏi tổng là bảng nói cơ sở chưa chuyển đồng nào. */
			if ( self::GD_DA_AP === $tt ) {
				$ma = VHJP_Doc::str( $g['locationId'] );
				if ( isset( $theo[ $ma ] ) ) {
					$theo[ $ma ]['soGD']++;
					$theo[ $ma ]['tienVaoNH'] += $tien;
				}
				continue;
			}

			if ( $tien <= 0 ) {
				$cho[] = self::dong_cho( $g, 'Số tiền ≤ 0 — không phải khoản tiền vào.' );
				$tien_cho += $tien;
				continue;
			}

			$ung = self::GD_NHAN_TRUOC === $tt && '' !== VHJP_Doc::str( $g['locationId'] )
				? array( VHJP_Doc::str( $g['locationId'] ) )
				: self::dinh_danh_trong( $g['noiDung'], $cs );

			if ( ! $ung ) {
				$cho[] = self::dong_cho( $g, 'Nội dung KHÔNG có mã định danh nào đang khai — '
					. 'khai mã ở Cấu hình → Cơ sở rồi chạy lại.' );
				$tien_cho += $tien;
				continue;
			}
			if ( count( $ung ) > 1 ) {
				$cho[] = self::dong_cho( $g, 'Khớp nhiều mã định danh (' . count( $ung )
					. ') — hệ KHÔNG tự chọn. Xử lý tay ở màn Xác nhận nộp tay.' );
				$tien_cho += $tien;
				continue;
			}

			$ma_cs = $ung[0];
			if ( ! isset( $theo[ $ma_cs ] ) ) {
				$theo[ $ma_cs ] = array( 'soGD' => 0, 'tienVaoNH' => 0, 'seGhi' => 0, 'nhanTruoc' => 0 );
			}
			$theo[ $ma_cs ]['soGD']++;
			$theo[ $ma_cs ]['tienVaoNH'] += $tien;

			$bc = self::bc_con_thieu( $ma_cs );
			if ( ! $bc ) {
				/* Tiền về trước báo cáo — giữ riêng, xem khối 🔴 thứ hai. */
				$theo[ $ma_cs ]['nhanTruoc'] += $tien;
				if ( $ghi && self::GD_NHAN_TRUOC !== $tt ) {
					VHJP_Nguon::sua( 'JP_BankGD', $ref, array( 'trangThai' => self::GD_NHAN_TRUOC,
						'locationId' => $ma_cs,
						'lyDo' => 'Chưa có báo cáo hoàn tất nào để gắn — đang giữ chờ.' ) );
				}
				continue;
			}

			if ( ! $ghi ) {
				$con = $tien;
				foreach ( $bc as $r ) {
					if ( $con <= 0 ) { break; }
					$them = min( $con, self::so( $r['totalSubmit'] )
						- self::so( isset( $r['ktXacNhan'] ) ? $r['ktXacNhan'] : 0 ) );
					if ( $them <= 0 ) { continue; }
					$con -= $them;
				}
				$theo[ $ma_cs ]['seGhi'] += $tien - $con;
				$ap++;
				$tien_ap += $tien - $con;
				continue;
			}

			$con = $tien; $da_vao = '';
			foreach ( $bc as $r ) {
				if ( $con <= 0 ) { break; }
				$id   = VHJP_Doc::str( $r['id'] );
				$phai = self::so( $r['totalSubmit'] );
				$da   = self::so( isset( $r['ktXacNhan'] ) ? $r['ktXacNhan'] : 0 );
				$them = min( $con, $phai - $da );
				if ( $them <= 0 ) { continue; }
				VHJP_NopTien::xac_nhan( $u, $id, $da + $them );
				VHJP_Nguon::sua( 'JP_Reports', $id,
					array( 'ktCachThu' => 'CK', 'ktGhiChu' => 'Ngân hàng ' . $ref ) );
				$da_vao = $id;
				$con   -= $them;
			}
			$vao = $tien - $con;
			$theo[ $ma_cs ]['seGhi'] += $vao;

			if ( $con > 0 ) {
				/* Vào được một phần: phần còn lại tiếp tục giữ, KHÔNG đánh dấu đã áp — đánh dấu
				   là phần dư biến mất khỏi mọi bảng. */
				$theo[ $ma_cs ]['nhanTruoc'] += $con;
				VHJP_Nguon::sua( 'JP_BankGD', $ref, array( 'trangThai' => self::GD_NHAN_TRUOC,
					'locationId' => $ma_cs, 'reportId' => $da_vao,
					'lyDo' => 'Vào được ' . number_format( $vao, 0, ',', '.' ) . 'đ, còn '
						. number_format( $con, 0, ',', '.' ) . 'đ đang giữ chờ báo cáo sau.' ) );
			} else {
				VHJP_Nguon::sua( 'JP_BankGD', $ref, array( 'trangThai' => self::GD_DA_AP,
					'locationId' => $ma_cs, 'reportId' => $da_vao,
					'apLuc' => gmdate( 'Y-m-d H:i:s', time() + 7 * 3600 ),
					'apBoi' => VHJP_Doc::str( $u['hoTen'] ), 'lyDo' => '' ) );
			}
			if ( self::GD_NHAN_TRUOC === $tt && $vao > 0 ) {
				$khop_truoc['gan']++;
				$khop_truoc['tien'] += $vao;
			}
			$ap++;
			$tien_ap += $vao;
		}

		$rows = array();
		foreach ( $cs as $l ) {
			$t = isset( $theo[ $l['id'] ] ) ? $theo[ $l['id'] ]
				: array( 'soGD' => 0, 'tienVaoNH' => 0, 'seGhi' => 0, 'nhanTruoc' => 0 );
			$d = isset( $truoc[ $l['id'] ] ) ? $truoc[ $l['id'] ]
				: array( 'phaiNop' => 0, 'daGhiNhan' => 0 );
			/* Cơ sở ngưng hoạt động, không tiền về, không nợ thì KHÔNG in — bảng dài vì hàng 0
			   là bảng không ai đọc tới cuối. */
			if ( ! $l['active'] && ! $t['tienVaoNH'] && ! $d['phaiNop'] ) { continue; }
			$lech = $t['tienVaoNH'] - $d['daGhiNhan'] - $t['seGhi'];
			$rows[] = array( 'locationId' => $l['id'], 'code' => $l['code'], 'name' => $l['name'],
				'active' => $l['active'], 'dinhDanh' => $l['dinhDanh'],
				'phaiNop' => $d['phaiNop'], 'daGhiNhan' => $d['daGhiNhan'],
				'tienVaoNH' => $t['tienVaoNH'], 'soGD' => $t['soGD'],
				'seGhi' => $t['seGhi'], 'nhanTruoc' => $t['nhanTruoc'], 'lech' => $lech,
				'trangThai' => '' === $l['dinhDanh']
					? 'Chưa khai mã định danh — tiền không tự vào được'
					: ( $t['nhanTruoc'] > 0 ? 'Có tiền nhận trước, chờ báo cáo duyệt'
						: ( $lech > 0 ? 'Tiền về nhiều hơn phần ghi được'
							: ( $t['tienVaoNH'] > 0 ? 'Khớp' : 'Chưa có tiền về lượt này' ) ) ) );
		}
		usort( $rows, function ( $a, $b ) {
			if ( $a['lech'] !== $b['lech'] ) { return abs( $b['lech'] ) > abs( $a['lech'] ) ? 1 : -1; }
			return strcmp( $a['name'], $b['name'] );
		} );

		if ( $ghi && $ap ) {
			VHJP_NhatKy::ghi( $u, 'BANK_DOI_SOAT', '', '', $ap . ' GD · ' . $tien_ap );
		}

		return array( 'ok' => true, 'ghi' => $ghi, 'daXacNhan' => $ok,
			'soDocDuoc' => count( $gd ),
			'soAp' => $ap, 'tienAp' => $tien_ap, 'tienTuAp' => $tien_ap,
			'soCho' => count( $cho ), 'tienCho' => $tien_cho, 'cho' => $cho,
			'khopTruoc' => $khop_truoc, 'theoCoSo' => $rows, 'lich' => self::lich( $u ),
			'msg' => ! $ok
				? 'Cổng "xác nhận cột" đang TẮT nên chỉ xem, KHÔNG ghi gì. Soát bảng đọc thử rồi '
					. 'bật cổng mới áp được.'
				: ( $ghi
					? 'Đã áp ' . $ap . ' giao dịch · ' . number_format( $tien_ap, 0, ',', '.' ) . 'đ.'
					: 'Xem trước: ' . $ap . ' giao dịch khớp chắc chắn · '
						. number_format( $tien_ap, 0, ',', '.' ) . 'đ. Chưa ghi gì.' ) );
	}


	/** Một dòng hàng chờ, kèm LÝ DO — hàng chờ không nói vì sao là hàng chờ dài ra mãi. */
	private static function dong_cho( $g, $ly_do ) {
		return array( 'refId' => VHJP_Doc::str( $g['refId'] ),
			'ngay' => VHJP_Doc::ngay( $g['ngay'] ), 'soTien' => self::so( $g['soTien'] ),
			'noiDung' => VHJP_Doc::str( $g['noiDung'] ), 'lyDo' => $ly_do );
	}

}
