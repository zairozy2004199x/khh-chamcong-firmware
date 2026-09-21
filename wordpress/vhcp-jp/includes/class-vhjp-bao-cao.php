<?php
/**
 * ĐỌC BÁO CÁO — phần ĐỌC của `JP2_05_BaoCao.gs`.
 *
 * =============================================================================================
 * 🔴 ĐÂY LÀ MÀN NHÂN VIÊN MỞ NHIỀU NHẤT TRONG CA.
 * =============================================================================================
 * `jpMyReports` (danh sách báo cáo của tôi) và `jpGetReport` (mở một báo cáo) là hai lệnh gọi
 * đầu tiên của mọi ca làm. Chưa có hai cái này thì màn nhân viên chỉ là một cái khung rỗng.
 *
 * Lát này chuyển ĐÚNG ĐƯỜNG ĐỌC, chưa đụng đường ghi (`jpSaveReport` · `jpSubmitReport` ·
 * `jpOpenReport`) — mở báo cáo ĐÃ CÓ thì được, tạo mới thì chưa.
 *
 * ---------------------------------------------------------------------------------------------
 * 🔴 BA Ô TRỐNG PHẢI GIỮ NGUYÊN LÀ TRỐNG.
 * ---------------------------------------------------------------------------------------------
 * `amount` · `cashReal` · `cash` ở mẫu TÁCH, và mọi chỉ số đồng hồ, đi qua `num_hoac_trong()`
 * chứ KHÔNG qua `num()`. Bản gốc ghi rõ vì sao, và cái giá thì rất cụ thể: trả `0` thay vì ô
 * trống là mở lại báo cáo thấy số `0`, trạng thái *"chưa nhập"* mất luôn sau một lần lưu nháp
 * ⇒ danh sách kiểm báo đủ điều kiện nộp ⇒ `lech_tm()` coi như đếm được 0 ⇒ lệch ra
 * −(tiền mặt app) ⇒ TỔNG PHẢI NỘP về 0. **Sổ vẫn cân, không ai báo.**
 *
 * ---------------------------------------------------------------------------------------------
 * 🔴 MẢNG RỖNG CỦA PHP KHÔNG PHẢI OBJECT RỖNG CỦA JS.
 * ---------------------------------------------------------------------------------------------
 * `ton_ky_truoc()` trả một BẢNG TRA (khoá là mã máy / `ITEM:<mã hàng>`). Rỗng thì PHP để
 * `array()`, mà `json_encode` in ra `[]` — giao diện nhận một MẢNG rồi `prev[r.machineId]`
 * ra `undefined` ở mọi dòng. Ép `(object)` trước khi trả. Cùng cái mìn đã nổ một lần ở
 * `VHJP_Tinh::bao_cao()`.
 *
 * ---------------------------------------------------------------------------------------------
 * ⚠️ CÁI CHƯA CÓ: ẢNH.
 * ---------------------------------------------------------------------------------------------
 * `anhTienDo` trả `null`. Bản gốc cũng bọc `try/catch` quanh đúng chỗ ấy và để `null` khi
 * hỏng — *"ảnh là phần phụ, hỏng thì báo cáo vẫn phải mở được"* — nên giao diện đã biết đường
 * lùi: thấy rỗng thì tự gọi `jpPhotoProgress`. Ta chưa chuyển hàm đó, nên nó sẽ nhận câu
 * "chưa chuyển" và bỏ qua; báo cáo vẫn mở.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHJP_BaoCao {

	/* Trạng thái báo cáo — y `JP2_00_Config.gs`. */
	const TT_NHAP      = 'NHAP';        // nhân viên đang làm
	const TT_CHO_DUYET = 'CHO_DUYET';   // đã nộp
	const TT_CAN_SUA   = 'CAN_SUA';     // kế toán trả về
	const TT_HOAN_TAT  = 'HOAN_TAT';    // đủ hai chữ ký

	/** Cỡ ảnh — bản nhỏ cho ô 74px, bản to để đọc chỉ số khi bấm vào xem. */
	const ANH_SZ_NHO = 'w200';
	const ANH_SZ_TO  = 'w1600';

	/** Bao nhiêu báo cáo trả về khi người gọi không nói rõ. */
	const GIOI_HAN_MAC_DINH = 30;

	/* ═══════════════════════ ĐÓNG GÓI ĐỂ TRẢ VỀ GIAO DIỆN ═══════════════════════ */

	/**
	 * Đầu báo cáo.
	 *
	 * ⚠️ `submittedAt` PHẢI qua `ngay_gio()`. Bên Sheets cột này giữ đối tượng Date và trả
	 *    thẳng thì giao diện nhận `null`; bên MySQL nó là chuỗi nên không nổ, nhưng giữ đúng
	 *    đường đi để hai bản còn đối chiếu được bằng máy.
	 *
	 * ⚠️ `refundTotal` và `revMeterRong` là số SUY RA, không phải cột. Mở lại báo cáo thì
	 *    trường tạm của `VHJP_Tinh::bao_cao()` không còn — thiếu hai cái này là ô "TIỀN RÒNG"
	 *    in 0đ trong khi sổ có số.
	 */
	public static function pub_head( $h ) {
		$o = self::o( $h );
		return array(
			'id'             => (string) $o( 'id' ),
			'locationId'     => (string) $o( 'locationId' ),
			'locationName'   => VHJP_Doc::str( $o( 'locationName' ) ),
			'maKH'           => VHJP_Doc::str( $o( 'maKH' ) ),
			'machineType'    => VHJP_Doc::str( $o( 'machineType' ) ),
			'bcMau'          => VHJP_CauHinh::bc_mau( $o( 'bcMau' ) ),
			'fromDate'       => VHJP_Doc::ngay( $o( 'fromDate' ) ),
			'toDate'         => VHJP_Doc::ngay( $o( 'toDate' ) ),
			'userName'       => VHJP_Doc::str( $o( 'userName' ) ),
			'status'         => VHJP_Doc::str( $o( 'status' ) ),
			'revMeter'       => VHJP_Doc::num( $o( 'revMeter' ) ),
			'revBank'        => VHJP_Doc::num( $o( 'revBank' ) ),
			'revCashMeter'   => VHJP_Doc::num( $o( 'revCashMeter' ) ),
			'revHang'        => VHJP_Doc::num( $o( 'revHang' ) ),
			'lechTienHang'   => VHJP_Doc::num( $o( 'lechTienHang' ) ),
			'adjMachine'     => VHJP_Doc::num( $o( 'adjMachine' ) ),
			'adjMachineNote' => VHJP_Doc::str( $o( 'adjMachineNote' ) ),
			'refundCustomer' => VHJP_Doc::num( $o( 'refundCustomer' ) ),
			'refundNote'     => VHJP_Doc::str( $o( 'refundNote' ) ),
			'refundRows'     => VHJP_Doc::num( $o( 'refundRows' ) ),
			/* Cờ MỞ Ô TỒN ĐẦU — `mo_ton_dau()` là chỗ DUY NHẤT quyết định. Giao diện chỉ đọc
			   cờ, không tự suy: hai chỗ tự suy là hai luật rồi lệch nhau. */
			'moTonDau'       => self::mo_ton_dau( $h ),
			'refundTotal'    => VHJP_Tinh::hoan_tong( $h ),
			'revMeterRong'   => VHJP_Doc::num( $o( 'revMeter' ) ) - VHJP_Tinh::hoan_tong( $h ),
			'cashActual'     => VHJP_Doc::num( $o( 'cashActual' ) ),
			'totalSubmit'    => VHJP_Doc::num( $o( 'totalSubmit' ) ),
			'submittedAt'    => VHJP_Doc::ngay_gio( $o( 'submittedAt' ) ),
			'chuKy'          => self::chu_ky( $h ),
			'rejectPart'     => VHJP_Doc::str( $o( 'rejectPart' ) ),
			'rejectReason'   => VHJP_Doc::str( $o( 'rejectReason' ) ),
			'payStatus'      => VHJP_Doc::str( $o( 'payStatus' ) ),
			'paid'           => VHJP_Doc::num( $o( 'paid' ) ),
			'paidDate'       => VHJP_Doc::ngay( $o( 'paidDate' ) ),
			'warnCount'      => VHJP_Doc::num( $o( 'warnCount' ) ),
			'remark'         => VHJP_Doc::str( $o( 'remark' ) ),
		);
	}

	public static function pub_khu( $z ) {
		$o = self::o( $z );
		return array(
			'id'        => (string) $o( 'id' ),
			'seq'       => VHJP_Doc::num( $o( 'seq' ) ),
			'name'      => VHJP_Doc::str( $o( 'name' ) ),
			'clusterId' => VHJP_Doc::str( $o( 'clusterId' ) ),
			'note'      => VHJP_Doc::str( $o( 'note' ) ),
		);
	}

	/**
	 * Một dòng báo cáo. `$prev` là bảng tra tồn cuối kỳ trước (xem `ton_ky_truoc`).
	 *
	 * ⚠️ `giaXung` PHẢI trả về. Không thì mở lại báo cáo là ô chọn về mặc định 5.000 và lượt
	 *    lưu kế tiếp GHI ĐÈ MẤT lựa chọn cũ — dòng đó âm thầm về nửa tiền.
	 *
	 * ⚠️ `lechTM` SUY RA mỗi lượt, không lưu cột nào. Lưu là hai nguồn sự thật.
	 */
	public static function pub_dong( $r, $prev = array() ) {
		$o = self::o( $r );
		$ra = array(
			'id'          => (string) $o( 'id' ),
			'zoneId'      => (string) $o( 'zoneId' ),
			'seq'         => VHJP_Doc::num( $o( 'seq' ) ),
			'machineId'   => VHJP_Doc::str( $o( 'machineId' ) ),
			'machineCode' => VHJP_Doc::str( $o( 'machineCode' ) ),
			'itemCode'    => VHJP_Doc::str( $o( 'itemCode' ) ),
			'itemMisa'    => VHJP_Doc::str( $o( 'itemMisa' ) ),
			'itemName'    => VHJP_Doc::str( $o( 'itemName' ) ),
			'price'       => VHJP_Doc::num( $o( 'price' ) ),
			'giaXung'     => VHJP_Tinh::gia_xung( $o( 'giaXung' ) ),
			/* Ô TRỐNG LÀ CHƯA GÕ — xem khối 🔴 thứ nhất ở đầu tệp. */
			'mBefore'     => VHJP_Doc::num_hoac_trong( $o( 'mBefore' ) ),
			'mAfter'      => VHJP_Doc::num_hoac_trong( $o( 'mAfter' ) ),
			'mActual'     => VHJP_Doc::num( $o( 'mActual' ) ),
			'cBefore'     => VHJP_Doc::num_hoac_trong( $o( 'cBefore' ) ),
			'cAfter'      => VHJP_Doc::num_hoac_trong( $o( 'cAfter' ) ),
			'cActual'     => VHJP_Doc::num( $o( 'cActual' ) ),
			'amount'      => VHJP_Doc::num_hoac_trong( $o( 'amount' ) ),
			'cashReal'    => VHJP_Doc::num_hoac_trong( $o( 'cashReal' ) ),
			'cash'        => VHJP_Doc::num_hoac_trong( $o( 'cash' ) ),
			'bank'        => VHJP_Doc::num( $o( 'bank' ) ),
			'soldQty'     => VHJP_Doc::num( $o( 'soldQty' ) ),
			'lechTM'      => VHJP_Tinh::lech_tm( $r ),
			'rowKind'     => VHJP_Doc::str( $o( 'rowKind' ) ) ? VHJP_Doc::str( $o( 'rowKind' ) ) : VHJP_Tinh::DONG_MONEY,
			'collection'  => VHJP_Doc::num( $o( 'collection' ) ),
			'hOpen'       => VHJP_Doc::num( $o( 'hOpen' ) ),
			'hBefore'     => VHJP_Doc::num_hoac_trong( $o( 'hBefore' ) ),
			'hAfter'      => VHJP_Doc::num_hoac_trong( $o( 'hAfter' ) ),
			'hLeft'       => VHJP_Doc::num( $o( 'hLeft' ) ),
			'stockOut'    => VHJP_Doc::num( $o( 'stockOut' ) ),
			'topupNote'   => VHJP_Doc::str( $o( 'topupNote' ) ),
			'giaXu'       => VHJP_Doc::num( $o( 'giaXu' ) ),
			'xuTong'      => VHJP_Doc::num( $o( 'xuTong' ) ),
			'xuLa'        => VHJP_Doc::num( $o( 'xuLa' ) ),
			'xuDays'      => self::json_mang( $o( 'xuDaysJson' ) ),
			'stockOpen'   => VHJP_Doc::num( $o( 'stockOpen' ) ),
			'addQty1'     => VHJP_Doc::num( $o( 'addQty1' ) ),
			'addQty2'     => VHJP_Doc::num( $o( 'addQty2' ) ),
			'stockLeftCalc' => VHJP_Doc::num( $o( 'stockLeftCalc' ) ),
			'stockActual' => VHJP_Doc::num_hoac_trong( $o( 'stockActual' ) ),
			'defectQty'   => VHJP_Doc::num( $o( 'defectQty' ) ),
			'returnQty'   => VHJP_Doc::num( $o( 'returnQty' ) ),
			'refundAmt'   => VHJP_Doc::num( $o( 'refundAmt' ) ),
			'refundQty'   => VHJP_Doc::num( $o( 'refundQty' ) ),
			'refundRowNote' => VHJP_Doc::str( $o( 'refundRowNote' ) ),
			'note'        => VHJP_Doc::str( $o( 'note' ) ),
			/* Một ô `warnJson` hỏng không được làm cả báo cáo không mở được. */
			'warns'       => self::json_mang( $o( 'warnJson' ) ),
		);
		/* Dòng máy nối kỳ theo `machineId`; dòng hàng theo MÃ HÀNG — cùng khoá `ton_ky_truoc()`
		   dựng. Đừng tra bằng `machineId` cho dòng hàng, vì ở dòng hàng nó rỗng. */
		$prev = (array) $prev;
		$loai = $ra['rowKind'];
		$k = ( VHJP_Tinh::DONG_HANG === $loai || VHJP_Tinh::DONG_STOCK === $loai
			|| VHJP_Tinh::DONG_NGOAI === $loai )
			? 'ITEM:' . $ra['itemCode']
			: $ra['machineId'];
		$ra['carried'] = isset( $prev[ $k ] ) && $prev[ $k ];
		return $ra;
	}

	/**
	 * Một ảnh.
	 *
	 * ⚠️ `url` DỰNG LẠI theo cỡ, không dùng lại cột đã lưu — cột đó ghi cứng cỡ lúc tải lên.
	 *    Dựng lại thì đổi một hằng số là ăn cho cả ảnh cũ. Chỉ bản ghi đời đầu (không có
	 *    `fileId`) mới rơi về cột cũ, không thì mất ảnh.
	 */
	public static function pub_anh( $p ) {
		$o   = self::o( $p );
		$fid = VHJP_Doc::str( $o( 'fileId' ) );
		return array(
			'id'      => (string) $o( 'id' ),
			'scope'   => VHJP_Doc::str( $o( 'scope' ) ),
			'refId'   => (string) $o( 'refId' ),
			'kind'    => VHJP_Doc::str( $o( 'kind' ) ),
			'fileId'  => $fid,
			'url'     => $fid ? self::anh_url( $fid, self::ANH_SZ_NHO ) : VHJP_Doc::str( $o( 'url' ) ),
			'urlTo'   => $fid ? self::anh_url( $fid, self::ANH_SZ_TO ) : VHJP_Doc::str( $o( 'url' ) ),
			'takenAt' => VHJP_Doc::ngay_gio( $o( 'takenAt' ) ),
		);
	}

	/**
	 * Đường dẫn ảnh theo cỡ.
	 *
	 * ⚠️ Ảnh cũ nằm trên Google Drive nên giữ nguyên cách dựng của bản gốc — bản ghi đã có
	 *    vẫn xem được. Lát chuyển `jpUploadPhoto` sẽ cho ảnh MỚI lên thư viện của WordPress
	 *    và lúc ấy chúng đi đường `url` đã lưu (không có `fileId`), đúng nhánh lùi ở trên.
	 */
	public static function anh_url( $file_id, $sz = self::ANH_SZ_NHO ) {
		$id = VHJP_Doc::str( $file_id );
		if ( '' === $id ) { return ''; }
		return 'https://drive.google.com/thumbnail?id=' . rawurlencode( $id )
			. '&sz=' . ( VHJP_Doc::str( $sz ) ? VHJP_Doc::str( $sz ) : self::ANH_SZ_NHO );
	}

	/* ═══════════════════════ CHỮ KÝ ═══════════════════════ */

	/**
	 * HAI chữ ký trên MỘT báo cáo, do cùng một tài khoản kế toán ký: ký phần doanh thu và ký
	 * phần kho là hai lần bấm riêng, nhưng cả hai lần đều xem bố cục đầy đủ.
	 *
	 * Đọc được cả hai đời dữ liệu cũ: luồng hai kế toán (`apprRevBy` / `apprStockBy`) và
	 * luồng một chữ ký gộp (`apprBy` — coi như đã ký cả hai phần).
	 */
	public static function chu_ky( $head ) {
		if ( ! $head ) {
			return array(
				'rev'     => array( 'by' => '', 'at' => '' ),
				'stock'   => array( 'by' => '', 'at' => '' ),
				'duCaHai' => false,
			);
		}
		$o   = self::o( $head );
		$gop = VHJP_Doc::str( $o( 'apprBy' ) );
		$rev = VHJP_Doc::str( $o( 'apprRevBy' ) );
		$sto = VHJP_Doc::str( $o( 'apprStockBy' ) );

		$o_rev = '' !== $rev
			? array( 'by' => $rev, 'at' => VHJP_Doc::ngay_gio( $o( 'apprRevAt' ) ) )
			: ( '' !== $gop
				? array( 'by' => $gop, 'at' => VHJP_Doc::ngay_gio( $o( 'apprAt' ) ), 'gop' => true )
				: array( 'by' => '', 'at' => '' ) );
		$o_sto = '' !== $sto
			? array( 'by' => $sto, 'at' => VHJP_Doc::ngay_gio( $o( 'apprStockAt' ) ) )
			: ( '' !== $gop
				? array( 'by' => $gop, 'at' => VHJP_Doc::ngay_gio( $o( 'apprAt' ) ), 'gop' => true )
				: array( 'by' => '', 'at' => '' ) );

		return array( 'rev' => $o_rev, 'stock' => $o_sto,
			'duCaHai' => ( '' !== $o_rev['by'] && '' !== $o_sto['by'] ) );
	}

	/* ═══════════════════════ QUYỀN TRÊN MỘT BÁO CÁO ═══════════════════════ */

	/** Người này sửa được báo cáo này không. */
	public static function sua_duoc( $u, $head ) {
		if ( ! VHJP_Auth::la_nv( $u ) ) { return false; }
		$o = self::o( $head );
		$ai = isset( $u['id'] ) ? $u['id'] : '';
		if ( (string) $o( 'userId' ) !== (string) $ai ) { return false; }   // báo cáo của người khác
		$s = VHJP_Doc::str( $o( 'status' ) );
		return self::TT_NHAP === $s || self::TT_CAN_SUA === $s;
	}

	/** Chặn ở MÁY CHỦ, không chỉ ẩn nút. */
	public static function can_chu( $u, $head ) {
		$o  = self::o( $head );
		$ai = isset( $u['id'] ) ? $u['id'] : '';
		if ( (string) $o( 'userId' ) !== (string) $ai ) {
			$ten = VHJP_Doc::str( $o( 'userName' ) );
			throw new Exception( 'Báo cáo này của ' . ( '' !== $ten ? $ten : 'nhân viên khác' )
				. ' — bạn không sửa được' );
		}
	}

	/* ═══════════════════════ SOI SANG CÁC KỲ KHÁC ═══════════════════════ */

	/**
	 * Kỳ của báo cáo này có phải kỳ ĐẦU THÁNG không (⇒ tồn đầu mở cho sửa)?
	 *
	 * Đúng khi trong tháng của `fromDate` CHƯA CÓ báo cáo `HOAN_TAT` nào của cùng cơ sở.
	 * Dùng `HOAN_TAT` chứ không dùng "có báo cáo nào" — báo cáo nháp của chính mình không
	 * được tính, không thì mở báo cáo lên là tự khoá luôn ô của mình.
	 */
	public static function mo_ton_dau( $head ) {
		$o = self::o( $head );
		$f = VHJP_Doc::ngay( $o( 'fromDate' ) );
		if ( '' === $f ) { return false; }
		$thang = substr( (string) $f, 0, 7 );                 // YYYY-MM
		$loc   = VHJP_Doc::str( $o( 'locationId' ) );

		foreach ( VHJP_Nguon::tim( 'JP_Reports', 'locationId', $loc ) as $r ) {
			if ( self::TT_HOAN_TAT !== VHJP_Doc::str( $r['status'] ) ) { continue; }
			if ( (string) $r['id'] === (string) $o( 'id' ) ) { continue; }
			$d = VHJP_Doc::ngay( $r['fromDate'] );
			if ( '' === $d ) { $d = VHJP_Doc::ngay( $r['toDate'] ); }
			if ( '' !== $d && substr( (string) $d, 0, 7 ) === $thang ) { return false; }
		}
		return true;
	}

	/**
	 * TỒN CUỐI KỲ TRƯỚC — bảng tra để điền số đầu kỳ và KHOÁ ô lại.
	 *
	 * ⚠️⚠️ CÓ KỲ MỚI HƠN CHƯA DUYỆT thì KHÔNG chốt gì cả. `carried` là cờ KHOÁ Ô — nó nói
	 *      "số này của kỳ đã chốt, đừng sửa". Nếu giữa kỳ `HOAN_TAT` gần nhất và kỳ này còn
	 *      một kỳ đã nộp mà chưa duyệt, thì số của kỳ đã duyệt kia đã CŨ (nhảy qua cả một kỳ
	 *      phát sinh). Khoá nó lại là ép nhân viên giữ một con số vừa cũ vừa không sửa được.
	 *
	 * ⚠️ PHẢI lọc theo loại máy: một cơ sở làm cả báo cáo TIỀN và XU, không lọc thì số cuối kỳ
	 *    của báo cáo xu chảy sang báo cáo tiền qua khoá `ITEM:<mã hàng>`.
	 */
	public static function ton_ky_truoc( $ma_coso, $f, $tru_ma, $loai_may = '' ) {
		$m_type = VHJP_Doc::str( $loai_may );

		$truoc_do = array();
		foreach ( VHJP_Nguon::tim( 'JP_Reports', 'locationId', $ma_coso ) as $r ) {
			if ( (string) $r['id'] === (string) $tru_ma ) { continue; }
			if ( '' !== $m_type ) {
				$t = VHJP_Doc::str( $r['machineType'] );
				if ( ( '' !== $t ? $t : VHJP_CauHinh::LOAI_TIEN ) !== $m_type ) { continue; }
			}
			if ( ! ( VHJP_Doc::ngay( $r['toDate'] ) < $f ) ) { continue; }
			$truoc_do[] = $r;
		}
		usort( $truoc_do, function ( $a, $b ) {           // mới nhất trước
			return VHJP_Doc::ngay( $a['toDate'] ) < VHJP_Doc::ngay( $b['toDate'] ) ? 1 : -1;
		} );

		$moi_nhat = null;
		foreach ( $truoc_do as $r ) {
			$s = VHJP_Doc::str( $r['status'] );
			if ( self::TT_HOAN_TAT === $s || self::TT_CHO_DUYET === $s || self::TT_CAN_SUA === $s ) {
				$moi_nhat = $r;
				break;
			}
		}
		if ( $moi_nhat && self::TT_HOAN_TAT !== VHJP_Doc::str( $moi_nhat['status'] ) ) {
			return array();
		}

		$heads = array();
		foreach ( $truoc_do as $r ) {
			if ( self::TT_HOAN_TAT === VHJP_Doc::str( $r['status'] ) ) { $heads[] = $r; }
		}
		if ( ! $heads ) { return array(); }

		$theo_bc = array();
		foreach ( VHJP_Nguon::doc( 'JP_Rows' ) as $r ) {
			$theo_bc[ (string) $r['reportId'] ][] = $r;
		}

		$map = array();
		foreach ( $heads as $h ) {                        // duyệt từ mới → cũ
			$ds = isset( $theo_bc[ (string) $h['id'] ] ) ? $theo_bc[ (string) $h['id'] ] : array();
			foreach ( $ds as $r ) {
				$k = VHJP_Doc::str( $r['machineId'] );
				if ( '' === $k ) { $k = 'ITEM:' . VHJP_Doc::str( $r['itemCode'] ); }
				if ( '' === $k || isset( $map[ $k ] ) ) { continue; }   // đã có từ kỳ mới hơn thì giữ
				$map[ $k ] = array(
					'mAfter'      => VHJP_Doc::num( $r['mAfter'] ),
					'cAfter'      => VHJP_Doc::num( $r['cAfter'] ),
					'hAfter'      => VHJP_Doc::num( $r['hAfter'] ),
					'stockActual' => VHJP_Doc::num( $r['stockActual'] ),
					/* Mang theo CẢ `stockLeftCalc` và `rowKind` để chỗ dùng áp được đúng luật:
					   bảng nào có ô đếm là TUỲ CHỌN thì tồn cuối phải lấy số WEB TÍNH, không
					   lấy số đếm. Chỉ mang `stockActual` là mã nào nhân viên không đếm bị gieo
					   tồn 0 — mất hàng thật khỏi kỳ sau. */
					'stockLeftCalc' => VHJP_Doc::num( $r['stockLeftCalc'] ),
					'rowKind'     => VHJP_Doc::str( $r['rowKind'] ),
					'itemMisa'    => VHJP_Doc::str( $r['itemMisa'] ),
					'fromReport'  => (string) $h['id'],
					'toDate'      => VHJP_Doc::ngay( $h['toDate'] ),
				);
			}
		}
		return $map;
	}

	/**
	 * BÁO CÁO CÙNG KỲ, CÙNG CƠ SỞ, CỦA NGƯỜI KHÁC — để nhân viên biết mình không phải người
	 * duy nhất đang gõ cho ca ấy.
	 */
	public static function trung_nguoi_khac( $head, $ma_nguoi ) {
		$o = self::o( $head );
		$f = VHJP_Doc::ngay( $o( 'fromDate' ) );
		$t = VHJP_Doc::ngay( $o( 'toDate' ) );
		$mt = VHJP_Doc::str( $o( 'machineType' ) );
		$m_type = '' !== $mt ? $mt : VHJP_CauHinh::LOAI_TIEN;

		$ra = array();
		foreach ( VHJP_Nguon::tim( 'JP_Reports', 'locationId', $o( 'locationId' ) ) as $r ) {
			if ( (string) $r['userId'] === (string) $ma_nguoi ) { continue; }
			if ( VHJP_Doc::ngay( $r['fromDate'] ) !== $f ) { continue; }
			if ( VHJP_Doc::ngay( $r['toDate'] ) !== $t ) { continue; }
			$rt = VHJP_Doc::str( $r['machineType'] );
			if ( ( '' !== $rt ? $rt : VHJP_CauHinh::LOAI_TIEN ) !== $m_type ) { continue; }
			$ra[] = array(
				'userName' => VHJP_Doc::str( $r['userName'] ),
				'status'   => VHJP_Doc::str( $r['status'] ),
			);
		}
		return $ra;
	}

	/**
	 * KỲ CHỒNG VỚI BÁO CÁO ĐÃ NỘP KHÁC — cảnh báo NGAY LÚC MỞ, đừng đợi tới màn quét.
	 *
	 * ⚠️ CẢNH BÁO, KHÔNG CHẶN. Cùng luật với *"thiếu ảnh thì cảnh báo, không chặn nộp"*: có
	 *    thể có tình huống thật cần kỳ chồng mà ta chưa biết, chặn cứng là khoá cơ sở giữa ca.
	 *
	 * ⚠️ Chỉ so với báo cáo ĐÃ NỘP ít nhất một lần. Hai bản nháp chồng nhau là chuyện thường
	 *    (mở thử rồi bỏ); báo ở đó là cảnh báo giả — mà báo giả vài lần thì không ai đọc nữa.
	 */
	public static function ky_chong_nhau( $head ) {
		$o  = self::o( $head );
		$f  = VHJP_Doc::ngay( $o( 'fromDate' ) );
		$t  = VHJP_Doc::ngay( $o( 'toDate' ) );
		if ( '' === $t ) { $t = $f; }
		$mt = VHJP_Doc::str( $o( 'machineType' ) );
		$m_type = '' !== $mt ? $mt : VHJP_CauHinh::LOAI_TIEN;
		if ( '' === $f ) { return array(); }

		$ra = array();
		foreach ( VHJP_Nguon::tim( 'JP_Reports', 'locationId', $o( 'locationId' ) ) as $r ) {
			if ( (string) $r['id'] === (string) $o( 'id' ) ) { continue; }
			$rt = VHJP_Doc::str( $r['machineType'] );
			if ( ( '' !== $rt ? $rt : VHJP_CauHinh::LOAI_TIEN ) !== $m_type ) { continue; }
			$st = VHJP_Doc::str( $r['status'] );
			if ( self::TT_HOAN_TAT !== $st && self::TT_CHO_DUYET !== $st && self::TT_CAN_SUA !== $st ) {
				continue;
			}
			$rf = VHJP_Doc::ngay( $r['fromDate'] );
			$rt2 = VHJP_Doc::ngay( $r['toDate'] );
			if ( '' === $rt2 ) { $rt2 = $rf; }
			if ( $rf <= $t && $f <= $rt2 ) { $ra[] = $r; }   // hai khoảng ngày giao nhau
		}
		usort( $ra, function ( $a, $b ) {
			return VHJP_Doc::ngay( $a['fromDate'] ) < VHJP_Doc::ngay( $b['fromDate'] ) ? -1 : 1;
		} );
		return $ra;
	}

	/* ═══════════════════════ HAI LỆNH GỌI CỦA GIAO DIỆN ═══════════════════════ */

	/**
	 * DANH SÁCH BÁO CÁO CỦA TÔI — màn đầu tiên của nhân viên.
	 *
	 * ⚠️ Chỉ báo cáo của CHÍNH người đăng nhập. Báo cáo cùng cơ sở của người khác không phải
	 *    việc của mình (Andy chốt 03/08/2026) — và lọc ở máy chủ, không lọc ở giao diện.
	 */
	public static function cua_toi( $u, $gioi_han = 0 ) {
		$n = VHJP_Doc::num( $gioi_han );
		if ( ! $n ) { $n = self::GIOI_HAN_MAC_DINH; }
		$ai = isset( $u['id'] ) ? $u['id'] : '';

		$ds = VHJP_Nguon::tim( 'JP_Reports', 'userId', $ai );
		usort( $ds, function ( $a, $b ) {
			return VHJP_Doc::ngay( $a['fromDate'] ) < VHJP_Doc::ngay( $b['fromDate'] ) ? 1 : -1;
		} );
		$ds = array_slice( $ds, 0, (int) $n );

		$ra = array();
		foreach ( $ds as $r ) {
			$mt = VHJP_Doc::str( $r['machineType'] );
			$ra[] = array(
				'id'           => (string) $r['id'],
				'locationName' => VHJP_Doc::str( $r['locationName'] ),
				'fromDate'     => VHJP_Doc::ngay( $r['fromDate'] ),
				'toDate'       => VHJP_Doc::ngay( $r['toDate'] ),
				'machineType'  => '' !== $mt ? $mt : VHJP_CauHinh::LOAI_TIEN,
				'status'       => VHJP_Doc::str( $r['status'] ),
				'totalSubmit'  => VHJP_Doc::num( $r['totalSubmit'] ),
				'warnCount'    => VHJP_Doc::num( $r['warnCount'] ),
				'chuKy'        => self::chu_ky( $r ),
				'rejectReason' => VHJP_Doc::str( $r['rejectReason'] ),
			);
		}
		return $ra;
	}

	/**
	 * MỞ MỘT BÁO CÁO ĐÃ CÓ.
	 *
	 * ⚠️ Hai cờ `coDhTrung` và `chonGiaXung` ĐỌC LẠI TỪ CƠ SỞ mỗi lần mở, CỐ Ý không chốt vào
	 *    báo cáo. Ngược hẳn `bcMau` (chốt lúc tạo): hai cờ này chỉ ẩn/hiện vài ô, không xáo
	 *    lại bố cục bảng nên số đã gõ không rơi sang cột khác. `bcMau` mà đọc lại từ cơ sở thì
	 *    đúng là hỏng — bảng CHUNG và bảng TÁCH khác nhau cả chục cột.
	 *
	 * ⚠️ Cơ sở bị xoá khỏi danh mục thì rơi về CÓ đồng hồ, tức hiện đủ cột. Mất một báo cáo cũ
	 *    vì thiếu một dòng danh mục thì tệ hơn nhiều so với hiện thừa hai ô.
	 */
	public static function lay( $u, $ma_bc ) {
		$head = VHJP_Nguon::tim_mot( 'JP_Reports', 'id', $ma_bc );
		if ( ! $head ) { throw new Exception( 'Không tìm thấy báo cáo' ); }
		if ( ! VHJP_Auth::la_kt( $u ) && ! VHJP_Auth::xem_duoc_coso( $u, $head['locationId'] ) ) {
			throw new Exception( 'Không có quyền với cơ sở này' );
		}

		$khu = VHJP_Nguon::tim( 'JP_Zones', 'reportId', $ma_bc );
		usort( $khu, function ( $a, $b ) {
			return VHJP_Doc::num( $a['seq'] ) - VHJP_Doc::num( $b['seq'] );
		} );
		$dong = VHJP_Nguon::tim( 'JP_Rows', 'reportId', $ma_bc );
		usort( $dong, function ( $a, $b ) {
			return VHJP_Doc::num( $a['seq'] ) - VHJP_Doc::num( $b['seq'] );
		} );
		$anh = VHJP_Nguon::tim( 'JP_Photos', 'reportId', $ma_bc );

		$prev = self::ton_ky_truoc( $head['locationId'], VHJP_Doc::ngay( $head['fromDate'] ),
			$ma_bc, $head['machineType'] );

		$loc = VHJP_Nguon::tim_mot( 'JP_Locations', 'id', $head['locationId'] );

		$ds_khu = array();
		foreach ( $khu as $z ) { $ds_khu[] = self::pub_khu( $z ); }
		$ds_dong = array();
		foreach ( $dong as $r ) { $ds_dong[] = self::pub_dong( $r, $prev ); }
		$ds_anh = array();
		foreach ( $anh as $p ) { $ds_anh[] = self::pub_anh( $p ); }

		/* Cảnh báo cấp báo cáo KHÔNG lưu vào sổ, nên phải dựng lại lúc mở — dùng đúng hàm mà
		   lúc lưu dùng, không viết lại câu chữ ở đây.
		   ⚠️ Cảnh báo KỲ CHỒNG nối thêm Ở ĐÂY, cố ý KHÔNG đưa vào `canh_bao_dau()`: hàm đó là
		   hàm THUẦN (chỉ đọc đầu báo cáo) và đường lưu gọi nó trong khoá. Cho nó đọc bảng báo
		   cáo là đổi bản chất hàm. Kỳ báo cáo không đổi lúc lưu, nên dựng ở lúc MỞ là đủ. */
		$canh_bao = VHJP_Tinh::canh_bao_dau( $head );
		foreach ( self::ky_chong_nhau( $head ) as $r ) {
			$rf = VHJP_Doc::ngay( $r['fromDate'] );
			$rt = VHJP_Doc::ngay( $r['toDate'] );
			$hf = VHJP_Doc::ngay( $head['fromDate'] );
			$ht = VHJP_Doc::ngay( $head['toDate'] );
			$canh_bao[] = VHJP_Tinh::warn( 'KY_CHONG',
				'Kỳ ' . $hf . ' → ' . ( '' !== $ht ? $ht : $hf )
				. ' chồng với ' . $r['id'] . ' (' . $rf . ' → '
				. ( '' !== $rt ? $rt : $rf ) . ', ' . VHJP_Doc::str( $r['status'] ) . '). '
				. 'Doanh thu đi từ chỉ số đồng hồ nên CHƯA chắc tính hai lần, nhưng tồn đầu kỳ '
				. 'sau sẽ không tự điền được và sổ công nợ chia theo kỳ sẽ lệch. Sửa bằng nút '
				. '"Đổi kỳ", hoặc soi ô ⑥ ở màn Quét dây chuyền bên web kế toán.' );
		}

		return array(
			'ok'           => true,
			'head'         => self::pub_head( $head ),
			'coDhTrung'    => VHJP_CauHinh::co_dh_trung( $loc ? $loc['coDhTrung'] : '' ),
			'chonGiaXung'  => VHJP_CauHinh::chon_gia_xung( $loc ? $loc['chonGiaXung'] : '' ),
			'zones'        => $ds_khu,
			'rows'         => $ds_dong,
			'photos'       => $ds_anh,
			'canEdit'      => self::sua_duoc( $u, $head ),
			'headWarns'    => $canh_bao,
			/* 🔴 (object) — bảng tra RỖNG mà để `array()` thì `json_encode` in `[]`, giao diện
			   nhận một MẢNG và `prev[r.machineId]` ra `undefined` ở mọi dòng. */
			'prevClosing'  => (object) $prev,
			'trungNguoiKhac' => VHJP_Auth::la_nv( $u ) ? self::trung_nguoi_khac( $head, isset( $u['id'] ) ? $u['id'] : '' ) : array(),
			/*
			 * TIẾN ĐỘ ẢNH ĐI CÙNG LƯỢT NÀY — bản gốc gộp vào đây vì *"bấm cái nào cũng load
			 * lâu quá"*: tách ra là HAI đợt chờ nối tiếp, mà mở báo cáo là việc nhân viên làm
			 * nhiều nhất trong ca.
			 *
			 * ⚠️ `try/catch` là CÓ CHỦ Ý và có tiền lệ (giao diện vốn đã `.catch()` chỗ này):
			 *    ảnh là phần phụ, hỏng thì báo cáo VẪN PHẢI MỞ ĐƯỢC — cùng luật với "thiếu ảnh
			 *    thì cảnh báo, KHÔNG chặn nộp". Không có lớp bọc này thì một bảng ảnh lỗi là
			 *    nhân viên không mở nổi báo cáo. Giao diện thấy rỗng thì tự gọi lại.
			 */
			'anhTienDo'    => ( function () use ( $u, $ma_bc ) {
				try { return VHJP_Anh::tien_do( $u, $ma_bc ); } catch ( Throwable $e ) { return null; }
			} )(),
		);
	}

	/* ═══════════════════════ MỞ / TẠO BÁO CÁO ═══════════════════════ */

	/**
	 * MỞ BÁO CÁO CỦA MỘT KỲ — có rồi thì mở ra, chưa có thì TẠO.
	 *
	 * =========================================================================================
	 * 🔴 BẤT BIẾN: 1 nhân viên · 1 cơ sở · 1 kỳ · 1 loại máy ⇒ ĐÚNG MỘT BÁO CÁO.
	 * =========================================================================================
	 * Báo cáo thuộc về NGƯỜI TẠO: người khác cùng cơ sở không sửa, không nộp được. Riêng số
	 * ĐẦU KỲ vẫn lấy chung theo cơ sở, vì chỉ số đồng hồ là số VẬT LÝ của máy, không phụ thuộc
	 * ai đi thu.
	 *
	 * ⚠️ `bcMau` và `machineType` CHỐT LÚC TẠO, không đọc lại từ cơ sở mỗi lần mở. Kế toán đổi
	 *    mẫu của cơ sở sau đó thì báo cáo này giữ đúng bố cục nhân viên đã nhập — bảng CHUNG và
	 *    bảng TÁCH khác nhau cả chục cột, đọc lại là số đã gõ rơi sang cột khác.
	 *
	 * ⚠️ Mẫu TÁCH chỉ có ở báo cáo máy TIỀN: máy xu vốn đã tách hai bảng rồi.
	 */
	public static function mo( $u, $ma_coso, $tu_ngay, $den_ngay, $loai_may = '' ) {
		VHJP_Auth::can_coso( $u, $ma_coso );

		$f = VHJP_Doc::ngay( $tu_ngay );
		$t = VHJP_Doc::ngay( $den_ngay );
		if ( '' === $t ) { $t = $f; }
		if ( '' === $f ) { throw new Exception( 'Chọn ngày báo cáo' ); }
		if ( $t < $f ) { throw new Exception( 'Ngày kết thúc phải sau ngày bắt đầu' ); }

		$loc = VHJP_Nguon::tim_mot( 'JP_Locations', 'id', $ma_coso );
		if ( ! $loc ) { throw new Exception( 'Không tìm thấy cơ sở' ); }

		/* Loại báo cáo TÁCH BẠCH: máy tiền và máy xu là hai báo cáo riêng. Nhân viên được gán
		   loại nào thì mặc định mở đúng loại đó — KHÔNG lấy loại của cơ sở nữa, vì một cơ sở
		   có thể có cả hai loại máy. */
		$duoc  = VHJP_Auth::may_type_cua( $u );
		$m_type = VHJP_Doc::str( $loai_may );
		if ( '' === $m_type ) { $m_type = $duoc; }
		if ( '' === $m_type ) { $m_type = VHJP_Doc::str( $loc['machineType'] ); }
		if ( '' === $m_type ) { $m_type = VHJP_CauHinh::LOAI_TIEN; }
		if ( VHJP_CauHinh::LOAI_XU !== $m_type ) { $m_type = VHJP_CauHinh::LOAI_TIEN; }
		VHJP_Auth::can_may_type( $u, $m_type );

		/* Lọc cả theo người dùng: báo cáo của người khác không phải việc của mình. */
		$ai   = isset( $u['id'] ) ? $u['id'] : '';
		$cua_toi = array();
		foreach ( VHJP_Nguon::tim( 'JP_Reports', 'locationId', $ma_coso ) as $r ) {
			if ( (string) $r['userId'] !== (string) $ai ) { continue; }
			if ( VHJP_Doc::ngay( $r['fromDate'] ) !== $f ) { continue; }
			if ( VHJP_Doc::ngay( $r['toDate'] ) !== $t ) { continue; }
			$rt = VHJP_Doc::str( $r['machineType'] );
			if ( ( '' !== $rt ? $rt : VHJP_CauHinh::LOAI_TIEN ) !== $m_type ) { continue; }
			$cua_toi[] = $r;
		}

		foreach ( $cua_toi as $r ) {
			if ( self::TT_HOAN_TAT === VHJP_Doc::str( $r['status'] ) ) {
				return array( 'ok' => false, 'locked' => true,
					'msg' => 'Báo cáo ' . ( VHJP_CauHinh::LOAI_XU === $m_type ? 'MÁY XU' : 'MÁY TIỀN' )
						. ' kỳ ' . VHJP_Doc::dmy( $f ) . ' – ' . VHJP_Doc::dmy( $t )
						. ' đã duyệt xong, không sửa được' );
			}
		}
		foreach ( $cua_toi as $r ) {
			$st = VHJP_Doc::str( $r['status'] );
			if ( self::TT_NHAP === $st || self::TT_CAN_SUA === $st || self::TT_CHO_DUYET === $st ) {
				return self::lay( $u, $r['id'] );
			}
		}

		$mau = ( VHJP_CauHinh::LOAI_XU === $m_type )
			? VHJP_CauHinh::MAU_CHUNG
			: VHJP_CauHinh::bc_mau( $loc['bcMau'] );

		$head = array(
			'createdAt'      => gmdate( 'Y-m-d H:i:s', time() + 7 * 3600 ),   // giờ Việt Nam
			'locationId'     => (string) $ma_coso,
			'locationName'   => VHJP_Doc::str( $loc['name'] ),
			'maKH'           => VHJP_Doc::str( $loc['maKH'] ),
			'machineType'    => $m_type,
			'bcMau'          => $mau,
			'fromDate'       => $f,
			'toDate'         => $t,
			'userId'         => (string) $ai,
			'userName'       => VHJP_Doc::str( isset( $u['hoTen'] ) ? $u['hoTen'] : '' ),
			'status'         => self::TT_NHAP,
			'revMeter'       => 0, 'revBank' => 0, 'revCashMeter' => 0,
			'revHang'        => 0, 'lechTienHang' => 0,
			'adjMachine'     => 0, 'adjMachineNote' => '',
			'refundCustomer' => 0, 'refundNote' => '', 'refundRows' => 0,
			'cashActual'     => 0, 'totalSubmit' => 0,
			'paid'           => 0, 'paidDate' => null, 'payStatus' => 'CHUA_NOP',
			'warnCount'      => 0, 'remark' => '',
		);
		$head = VHJP_Ma::them( 'JP_Reports', 'RP', $head );
		/* 🔴 GHI HỎNG THÌ NÓI RA. Trả về như thật là nhân viên gõ cả ca vào một báo cáo không
		   tồn tại, và lượt lưu sau mới báo lỗi — lúc ấy số đã gõ mất sạch. Bộ Ghế đã mất một
		   giao dịch vì đúng nước đi ngược lại (`VHG_Thu::ghi`, sửa 16/09/2026). */
		if ( false === $head ) {
			throw new Exception( 'Không ghi được báo cáo mới — thử lại, hoặc báo người quản trị' );
		}

		/* TỰ NHẢY Y NHƯ BÁO CÁO TRƯỚC. Gieo NGAY vào sổ chứ không để giao diện tự dựng: nhân
		   viên mở là thấy đủ dòng, và bản nháp CÓ THẬT nên đổi máy / mất mạng giữa buổi vẫn còn. */
		$gieo = self::gieo_dong_tu_ky_truoc( $head );

		VHJP_NhatKy::ghi( $u, 'REPORT_CREATE', $head['id'], VHJP_Doc::str( $loc['name'] ), array(
			'from' => $f, 'to' => $t, 'type' => $m_type, 'mau' => $mau,
			'gieoDong' => $gieo ? $gieo['soDong'] : 0,
			'tuBaoCao' => $gieo ? $gieo['tuBaoCao'] : '',
			'trungNguoiKhac' => count( self::trung_nguoi_khac( $head, $ai ) ),
		) );

		/* ⚠️ Trả kèm `gieo` để giao diện NÓI RA đã gieo bao nhiêu dòng và gieo từ báo cáo nào —
		   nhất là khi nguồn CHƯA DUYỆT thì tồn đầu còn có thể đổi. Gieo im lặng là nhân viên
		   thấy sẵn số rồi tin luôn, không soát lại. */
		$ra = self::lay( $u, $head['id'] );
		if ( $gieo ) { $ra['gieo'] = $gieo; }
		return $ra;
	}

	/**
	 * KỲ LIỀN TRƯỚC của một cơ sở — NGUỒN DUY NHẤT của câu hỏi *"số đầu kỳ lấy từ đâu"*.
	 *
	 * ⚠️⚠️ Lấy kỳ GẦN NHẤT, KHÔNG ưu tiên `HOAN_TAT` toàn cục. Bản đầu của mã gốc viết
	 *      *"HOAN_TAT trước, không có thì mới lấy bản đã nộp"* — SAI: một bản duyệt từ 05/08
	 *      thắng bản vừa nộp 03/09, nên tồn đầu kỳ 04/09 NHẢY QUA cả kỳ 01–03/09. `HOAN_TAT`
	 *      chỉ dùng để PHÁ THẾ BẰNG khi hai bản cùng ngày kết thúc.
	 *
	 * ⚠️ `toDate < fromDate`, KHÔNG phải `<=`. Cho phép `<=` là hai kỳ cùng chứa một ngày ⇒
	 *    tồn cuối ngày đó thành tồn đầu của CHÍNH ngày đó, và doanh thu ngày đó nằm ở cả hai
	 *    báo cáo — sai tiền mà sổ vẫn cân.
	 *
	 * ⚠️ KHÔNG lấy `NHAP` — nháp có thể đang gõ nửa vời. "Đã nộp một lần" mới là mốc chắc
	 *    chắn rằng danh sách dòng đã đủ.
	 *
	 * Trả về cả `cungCoSo` lẫn `ung` vì `vi_sao_khong_gieo()` cần phân biệt "cơ sở chưa có kỳ
	 * nào" với "có kỳ nhưng chồng ngày" — hai tình huống cần hai câu trả lời khác hẳn.
	 */
	public static function ky_lien_truoc( $ma_coso, $f, $tru_ma, $loai_may, $mau ) {
		$mt = VHJP_Doc::str( $loai_may );
		$m_type = '' !== $mt ? $mt : VHJP_CauHinh::LOAI_TIEN;

		$cung_coso = array();
		foreach ( VHJP_Nguon::tim( 'JP_Reports', 'locationId', $ma_coso ) as $r ) {
			if ( (string) $r['id'] === (string) $tru_ma ) { continue; }
			$rt = VHJP_Doc::str( $r['machineType'] );
			if ( ( '' !== $rt ? $rt : VHJP_CauHinh::LOAI_TIEN ) !== $m_type ) { continue; }
			if ( VHJP_CauHinh::bc_mau( $r['bcMau'] ) !== $mau ) { continue; }
			$cung_coso[] = $r;
		}

		$ung = array();
		foreach ( $cung_coso as $r ) {
			if ( VHJP_Doc::ngay( $r['toDate'] ) < $f ) { $ung[] = $r; }
		}
		usort( $ung, function ( $a, $b ) {
			return VHJP_Doc::ngay( $a['toDate'] ) < VHJP_Doc::ngay( $b['toDate'] ) ? 1 : -1;
		} );

		/* Bậc thang trạng thái. `NHAP` KHÔNG có mặt — đó là cả ý nghĩa của bảng này. */
		$ut = array( self::TT_HOAN_TAT => 3, self::TT_CHO_DUYET => 2, self::TT_CAN_SUA => 1 );
		$co_nop = array();
		foreach ( $ung as $r ) {
			if ( isset( $ut[ VHJP_Doc::str( $r['status'] ) ] ) ) { $co_nop[] = $r; }
		}
		usort( $co_nop, function ( $a, $b ) use ( $ut ) {
			$da = VHJP_Doc::ngay( $a['toDate'] );
			$db = VHJP_Doc::ngay( $b['toDate'] );
			if ( $da !== $db ) { return $da < $db ? 1 : -1; }      // kỳ gần nhất trước
			return $ut[ VHJP_Doc::str( $b['status'] ) ] - $ut[ VHJP_Doc::str( $a['status'] ) ];
		} );

		return array(
			'truoc'     => $co_nop ? $co_nop[0] : null,
			'cungCoSo'  => $cung_coso,
			'ung'       => $ung,
		);
	}

	/**
	 * VÌ SAO KHÔNG GIEO ĐƯỢC DÒNG NÀO — trả về câu NÓI CHO NHÂN VIÊN.
	 *
	 * 🔴 Bảng trắng mà không nói gì trông y hệt lúc app hỏng. Máy chủ làm đúng luật, còn người
	 *    dùng thì không có đường nào biết vì sao và phải làm gì tiếp — đó là lỗi của APP.
	 *
	 * ⚠️ ĐỪNG "chữa" tình huống ② bằng cách nới thành `toDate <= fromDate`. Chỗ phải sửa là KỲ
	 *    BÁO CÁO, và nhân viên sửa được, nên câu trả lời phải chỉ thẳng vào đó.
	 *
	 * Ba tình huống, BA CÂU KHÁC NHAU — gộp lại một câu chung là người đọc không biết làm gì.
	 */
	public static function vi_sao_khong_gieo( $head, $f, $cung_coso, $ung ) {
		$ra = array( 'soDong' => 0, 'tuBaoCao' => '', 'denNgay' => '',
			'boQuaTraKho' => array(), 'daDuyet' => false );

		/* ① Cơ sở chưa từng có báo cáo nào cùng loại máy + cùng mẫu. */
		if ( ! $cung_coso ) {
			$ra['ma'] = 'KY_DAU';
			$ra['lyDo'] = 'Đây là kỳ ĐẦU TIÊN của cơ sở này, chưa có kỳ trước để ghi lại. '
				. 'Lần này nhập tay; từ kỳ sau web sẽ tự điền mã hàng và tồn đầu.';
			return $ra;
		}

		/* ② Có báo cáo cũ, nhưng KHÔNG cái nào kết thúc trước ngày kỳ này bắt đầu.
		   Đây là tình huống DUY NHẤT người dùng tự sửa được. */
		if ( ! $ung ) {
			$ds = $cung_coso;
			usort( $ds, function ( $a, $b ) {
				return VHJP_Doc::ngay( $a['toDate'] ) < VHJP_Doc::ngay( $b['toDate'] ) ? 1 : -1;
			} );
			$gan = $ds[0];
			$den = VHJP_Doc::ngay( $gan['toDate'] );
			$ra['ma'] = 'CHONG_KY';
			$ra['tuBaoCao'] = (string) $gan['id'];
			$ra['denNgay'] = $den;
			$ra['lyDo'] = 'Kỳ này bắt đầu ' . $f . ', nhưng báo cáo gần nhất của cơ sở ('
				. $gan['id'] . ') kéo tới ' . $den . ' — HAI KỲ CHỒNG NHAU nên không ghi '
				. 'lại được: tồn cuối của một ngày không thể là tồn đầu của chính ngày đó, '
				. 'và doanh thu ngày đó sẽ nằm ở cả hai báo cáo. Sửa kỳ này cho bắt đầu SAU '
				. $den . ' (nút "Đổi kỳ" ở đầu báo cáo) rồi mở lại.';
			return $ra;
		}

		/* ③ Có kỳ trước hợp lệ về ngày, nhưng chưa cái nào từng được NỘP. */
		$cu = $ung[0];
		$ra['ma'] = 'CHUA_NOP';
		$ra['tuBaoCao'] = (string) $cu['id'];
		$ra['denNgay'] = VHJP_Doc::ngay( $cu['toDate'] );
		$ra['lyDo'] = 'Kỳ trước (' . $cu['id'] . ', đến ' . VHJP_Doc::ngay( $cu['toDate'] )
			. ') còn là NHÁP, chưa nộp lần nào — nháp có thể đang gõ dở nên web không lấy '
			. 'làm chuẩn. Nộp kỳ đó rồi mở lại kỳ này là có ngay.';
		return $ra;
	}

	/**
	 * GIEO DÒNG cho báo cáo vừa tạo — *"báo cáo sau web sẽ tự nhảy y như báo cáo trước nhưng
	 * tồn cuối là tồn đầu bc sau"*.
	 *
	 * =========================================================================================
	 * 🔴 KHÔNG MANG THEO SỐ PHÁT SINH TRONG KỲ.
	 * =========================================================================================
	 * Chỉ số sau đồng hồ, nhập thêm, trả kho, hàng lỗi, tồn cuối, hoàn khách — để TRỐNG hết.
	 * Sao chép sang là nhân viên bấm Nộp mà không nhập gì cũng ra một báo cáo TRÔNG ĐẦY ĐỦ với
	 * số của kỳ trước ⇒ doanh thu kỳ này bằng kỳ trước mà không ai biết. Chỉ số ĐẦU kỳ thì có,
	 * vì nó là số vật lý đã chốt.
	 *
	 * ⚠️ Khớp CÙNG MẪU với báo cáo mới, đừng gán cứng TÁCH: gieo dòng mẫu này sang mẫu kia là
	 *    bố cục lệch hẳn (dòng `MAY` chỉ có tiền, dòng `MONEY` có cả hàng).
	 *
	 * ⚠️ Gieo từ bản CHƯA DUYỆT thì ô tồn đầu VẪN GÕ ĐƯỢC — `carried` do `ton_ky_truoc()` quyết
	 *    định và hàm đó chỉ nhận `HOAN_TAT`, nên tự khắc không khoá. Đúng: số đó còn có thể đổi
	 *    nếu kế toán trả về.
	 */
	public static function gieo_dong_tu_ky_truoc( $head ) {
		$o = self::o( $head );
		$f = VHJP_Doc::ngay( $o( 'fromDate' ) );
		$mt = VHJP_Doc::str( $o( 'machineType' ) );
		$m_type = '' !== $mt ? $mt : VHJP_CauHinh::LOAI_TIEN;
		$mau = VHJP_CauHinh::bc_mau( $o( 'bcMau' ) );
		$ma_bc = (string) $o( 'id' );

		$ky = self::ky_lien_truoc( $o( 'locationId' ), $f, $ma_bc, $m_type, $mau );
		if ( ! $ky['truoc'] ) {
			return self::vi_sao_khong_gieo( $head, $f, $ky['cungCoSo'], $ky['ung'] );
		}
		$truoc = $ky['truoc'];
		$da_duyet = self::TT_HOAN_TAT === VHJP_Doc::str( $truoc['status'] );
		$den_ngay = VHJP_Doc::ngay( $truoc['toDate'] );

		$khu_cu = VHJP_Nguon::tim( 'JP_Zones', 'reportId', $truoc['id'] );
		usort( $khu_cu, function ( $a, $b ) {
			return VHJP_Doc::num( $a['seq'] ) - VHJP_Doc::num( $b['seq'] );
		} );
		$dong_cu = VHJP_Nguon::tim( 'JP_Rows', 'reportId', $truoc['id'] );
		usort( $dong_cu, function ( $a, $b ) {
			return VHJP_Doc::num( $a['seq'] ) - VHJP_Doc::num( $b['seq'] );
		} );
		if ( ! $dong_cu ) {
			return array( 'soDong' => 0, 'tuBaoCao' => (string) $truoc['id'], 'denNgay' => $den_ngay,
				'boQuaTraKho' => array(), 'daDuyet' => $da_duyet, 'ma' => 'KY_TRUOC_RONG',
				'lyDo' => 'Kỳ trước (' . $truoc['id'] . ', đến ' . $den_ngay
					. ') không có dòng nào để ghi lại. Kỳ này gõ tay.' );
		}

		$ban_do_khu = array();
		foreach ( $khu_cu as $i => $z ) {
			$id = $ma_bc . '-Z' . ( $i + 1 );
			$ban_do_khu[ (string) $z['id'] ] = $id;
			VHJP_Nguon::them( 'JP_Zones', array(
				'id' => $id, 'reportId' => $ma_bc, 'seq' => $i + 1,
				'name' => VHJP_Doc::str( $z['name'] ) ? VHJP_Doc::str( $z['name'] ) : ( 'Khu vực ' . ( $i + 1 ) ),
				'clusterId' => VHJP_Doc::str( $z['clusterId'] ), 'note' => '',
			) );
		}

		$bo_qua = array();
		$moi = array();
		$seq = 0;
		foreach ( $dong_cu as $r ) {
			if ( self::bo_dong_tra_kho( $r ) ) {
				$ten = VHJP_Doc::str( $r['itemCode'] );
				if ( '' === $ten ) { $ten = VHJP_Doc::str( $r['itemMisa'] ); }
				if ( '' === $ten ) { $ten = VHJP_Doc::str( $r['itemName'] ); }
				$bo_qua[] = $ten;
				continue;
			}
			$i = $seq++;
			$kind = VHJP_Doc::str( $r['rowKind'] );
			if ( '' === $kind ) { $kind = VHJP_Tinh::DONG_MAY; }
			$d = array(
				'id'          => $ma_bc . '-R' . ( $i + 1 ),
				'reportId'    => $ma_bc,
				'zoneId'      => isset( $ban_do_khu[ (string) $r['zoneId'] ] ) ? $ban_do_khu[ (string) $r['zoneId'] ] : '',
				'seq'         => $i + 1,
				'rowKind'     => $kind,
				'machineId'   => VHJP_Doc::str( $r['machineId'] ),
				'machineCode' => VHJP_Doc::str( $r['machineCode'] ),
				'itemCode'    => VHJP_Doc::str( $r['itemCode'] ),
				'itemMisa'    => VHJP_Doc::str( $r['itemMisa'] ),
				'itemName'    => VHJP_Doc::str( $r['itemName'] ),
				'price'       => VHJP_Doc::num( $r['price'] ),
				/* ⚠️ GIEO giá 1 xung theo DÒNG — loại máy của một ô không đổi giữa hai kỳ, nên
				   nhân viên chọn MỘT LẦN rồi nó tự theo. Bắt chọn lại mỗi kỳ là chỗ họ sẽ
				   quên, mà quên thì dòng đó về nửa tiền (hoặc gấp đôi) và sổ vẫn cân. */
				'giaXung'     => VHJP_Tinh::gia_xung( $r['giaXung'] ),
				/* Đầu kỳ = cuối kỳ trước. Phát sinh trong kỳ để TRỐNG. */
				'mBefore'     => VHJP_Doc::num_hoac_trong( $r['mAfter'] ), 'mAfter' => null,
				'cBefore'     => VHJP_Doc::num_hoac_trong( $r['cAfter'] ), 'cAfter' => null,
				'hOpen'       => VHJP_Doc::num( $r['stockActual'] ),
				'hBefore'     => VHJP_Doc::num_hoac_trong( $r['hAfter'] ), 'hAfter' => null,
				'stockOpen'   => self::ton_cuoi_gieo( $r, $kind ),
				'stockActual' => null,
				'soldQty'     => null,          // ĐÃ BÁN là phát sinh trong kỳ — để TRỐNG
				'addQty1'     => 0, 'addQty2' => 0, 'defectQty' => 0, 'returnQty' => 0,
				/* Hoàn khách là PHÁT SINH TRONG KỲ — để 0, đừng mang theo kỳ trước. Mang theo
				   là nhân viên bấm Nộp mà không sửa gì cũng ra một khoản hoàn y kỳ trước, tức
				   TRỪ TIỀN THẬT mà không ai gõ số đó. */
				'refundAmt'   => 0, 'refundQty' => 0, 'refundRowNote' => '',
				'stockOut'    => 0, 'topupNote' => '',
				'bank'        => 0, 'cash' => 0,
				'giaXu'       => VHJP_Doc::num( $r['giaXu'] ), 'xuDaysJson' => '', 'xuLa' => 0,
				'note'        => '',
			);
			$moi[] = VHJP_Tinh::dong( $d, $m_type );
		}

		/* Ô trống (`''`) thành `NULL` ở `VHJP_Nguon::loc()` — một chỗ, cho mọi đường ghi. */
		if ( $moi ) { VHJP_Nguon::them_nhieu( 'JP_Rows', $moi ); }
		return array( 'soDong' => count( $moi ), 'tuBaoCao' => (string) $truoc['id'],
			'denNgay' => $den_ngay, 'daDuyet' => $da_duyet,
			'trangThaiNguon' => VHJP_Doc::str( $truoc['status'] ),
			'boQuaTraKho' => $bo_qua );
	}

	/**
	 * Dòng kỳ trước có ĐƯỢC BỎ khi gieo hay không — *"hàng nào bấm trả kho về 0 thì bc sau tự
	 * xoá dòng đó nhưng vẫn xem lại trong lịch sử báo cáo được"*.
	 *
	 * ⚠️ Điều kiện CHẶT — BA vế cùng lúc:
	 *     ① là dòng chỉ giữ HÀNG (`HANG` · `STOCK` · `NGOAI`)
	 *     ② tồn cuối kỳ trước = 0
	 *     ③ kỳ trước CÓ trả kho (`returnQty > 0`)
	 *
	 * ⚠️ Bỏ vế ① là MẤT LUÔN CÁI MÁY khỏi báo cáo kỳ sau: dòng `MONEY` mang cả tiền lẫn hàng
	 *    nên tồn về 0 + trả kho là chuyện thường, mà bỏ nó đi thì kỳ sau không còn ô máy đó để
	 *    nhập chỉ số đồng hồ.
	 * ⚠️ Bỏ vế ③ là BÁN HẾT CŨNG BỊ BỎ — mà bán hết là chuyện thường và cơ sở sẽ nhập lại đúng
	 *    mã đó, nên kỳ sau nhân viên phải gõ lại từ đầu, đúng thứ việc này sinh ra để khỏi phải làm.
	 *
	 * ⚠️ CHỈ KHÔNG GIEO, KHÔNG XOÁ GÌ. Dòng cũ nằm nguyên trong báo cáo cũ nên lịch sử vẫn xem
	 *    lại được, kho vẫn có dòng xuất, giá vốn vẫn nguyên.
	 */
	public static function bo_dong_tra_kho( $r ) {
		if ( ! self::dong_theo_ma( isset( $r['rowKind'] ) ? $r['rowKind'] : '' ) ) { return false; }
		return 0 === VHJP_Doc::num( isset( $r['stockLeftCalc'] ) ? $r['stockLeftCalc'] : 0 )
			&& VHJP_Doc::num( isset( $r['returnQty'] ) ? $r['returnQty'] : 0 ) > 0;
	}

	/**
	 * Dòng được định danh bằng MÃ HÀNG (`HANG` · `STOCK` · `NGOAI`), khác dòng định danh bằng
	 * Ô MÁY (`MONEY` · `MAY` · `COIN`).
	 *
	 * ⚠️ KHÁC hẳn câu hỏi *"dòng này có sinh giá vốn 632 không"* — câu đó LOẠI `NGOAI` (kho
	 *    ngoài không sinh giá vốn) và NHẬN `MONEY` (máy tiền mang cả hàng). Dùng lẫn hai khái
	 *    niệm là sai cả hai chiều: `NGOAI` sẽ không bao giờ được bỏ dòng dù đã trả kho hết, và
	 *    `MONEY` thì bị bỏ ⇒ mất luôn ô máy. Bản gốc đã mắc đúng thế.
	 */
	public static function dong_theo_ma( $kind ) {
		$k = VHJP_Doc::str( $kind );
		if ( '' === $k ) { $k = VHJP_Tinh::DONG_MONEY; }
		return VHJP_Tinh::DONG_HANG === $k || VHJP_Tinh::DONG_STOCK === $k
			|| VHJP_Tinh::DONG_NGOAI === $k;
	}

	/**
	 * Tồn cuối kỳ trước dùng làm tồn đầu kỳ này — NGUỒN DUY NHẤT của luật này.
	 *
	 * ⚠️ Ba bảng có ô đếm TUỲ CHỌN thì phải lấy số WEB TÍNH, không lấy ô đếm. Viết lại lẻ ở
	 *    chỗ thứ hai là có ngày một chỗ hiểu khác, và hậu quả là GIEO TỒN 0 cho mã nhân viên
	 *    không đếm — mất hàng thật khỏi kỳ sau mà bảng trông sạch sẽ.
	 */
	public static function ton_cuoi_gieo( $r, $kind ) {
		return self::dong_theo_ma( $kind )
			? VHJP_Doc::num( isset( $r['stockLeftCalc'] ) ? $r['stockLeftCalc'] : 0 )
			: VHJP_Doc::num( isset( $r['stockActual'] ) ? $r['stockActual'] : 0 );
	}

	/* ═══════════════════════ LƯU NHÁP ═══════════════════════ */

	/** Hai phần của một báo cáo, theo NGƯỜI SOÁT chứ không theo cột. */
	const PHAN_TIEN = 'REV';
	const PHAN_HANG = 'STOCK';

	/**
	 * LƯU BÁO CÁO.
	 *
	 * =========================================================================================
	 * 🔴 GIAO DIỆN KHÔNG GỬI TIỀN LÊN. MÁY CHỦ TÍNH LẠI TOÀN BỘ TRƯỚC KHI GHI.
	 * =========================================================================================
	 * Mọi con số tiền trong bảng này đều do `VHJP_Tinh` tính từ chỉ số đồng hồ và số lượng —
	 * giao diện chỉ gửi thứ NGƯỜI GÕ. Nhận tiền từ trình duyệt là nhận một con số ai cũng sửa
	 * được bằng công cụ dành cho người phát triển, và không có cách nào biết nó đã bị sửa.
	 *
	 * ⚠️ XOÁ SẠCH RỒI GHI LẠI, trong KHOÁ. Đây là cách bản gốc làm, và nó đúng vì nhân viên
	 *    thêm/bớt dòng giữa chừng. Nhưng nó cũng là chỗ hai lượt lưu xen nhau đẻ ra báo cáo
	 *    thiếu dòng hoặc trùng dòng — nên phải có khoá, xem `VHJP_Nguon::lay_khoa()`.
	 *
	 * ⚠️ BA Ô TIỀN CỦA MẪU TÁCH đi qua `num_hoac_trong()`, KHÔNG qua `num()`. Ở mẫu ấy chúng là
	 *    ô NHÂN VIÊN GÕ và ô trống là CHƯA GÕ. Dùng `num()` là chúng thành 0 ngay tại đây,
	 *    phép chặn nộp đọc lại chỉ thấy 0 nên KHÔNG chặn, và `lech_tm()` coi như đếm được 0 ⇒
	 *    lệch = −(tiền mặt app) ⇒ tiền phải nộp của cả kỳ về 0. Sổ vẫn cân.
	 *    Riêng `bank` (QR) thì `num()` mới ĐÚNG: trống nghĩa là không có khách quét mã, đa số
	 *    dòng như vậy — đòi gõ 0 là bắt gõ thừa 20 ô mỗi kỳ.
	 */
	public static function luu( $u, $payload ) {
		$payload = (array) $payload;
		$ma_bc   = isset( $payload['reportId'] ) ? VHJP_Doc::str( $payload['reportId'] ) : '';

		$head = VHJP_Nguon::tim_mot( 'JP_Reports', 'id', $ma_bc );
		if ( ! $head ) { throw new Exception( 'Không tìm thấy báo cáo' ); }
		VHJP_Auth::can_coso( $u, $head['locationId'] );
		self::can_chu( $u, $head );
		if ( ! self::sua_duoc( $u, $head ) ) {
			throw new Exception( 'Báo cáo đang ở trạng thái ' . VHJP_Doc::str( $head['status'] )
				. ', không sửa được' );
		}

		if ( ! VHJP_Nguon::lay_khoa( $ma_bc ) ) {
			throw new Exception( 'Báo cáo này đang được lưu ở một cửa sổ khác — thử lại sau vài giây' );
		}
		try {
			return self::luu_trong_khoa( $u, $payload, $head );
		} finally {
			/* ⚠️ `finally`: ném giữa chừng mà không trả khoá là khoá TREO tới hết phiên MySQL,
			   và lượt lưu sau của chính người ấy bị chối với câu "cửa sổ khác đang lưu". */
			VHJP_Nguon::tra_khoa( $ma_bc );
		}
	}

	private static function luu_trong_khoa( $u, $payload, $head ) {
		$ma_bc  = (string) $head['id'];
		$loai   = VHJP_Doc::str( $head['machineType'] );
		if ( '' === $loai ) { $loai = VHJP_CauHinh::LOAI_TIEN; }
		$mau    = VHJP_CauHinh::bc_mau( $head['bcMau'] );
		$ban_do_hang = VHJP_CauHinh::ban_do_hang();

		/* --- Giữ bản cũ để so sánh, phục vụ RESET THÔNG MINH chữ ký --- */
		$head_cu = array(
			'revMeter' => $head['revMeter'], 'revBank' => $head['revBank'],
			'adjMachine' => $head['adjMachine'], 'refundCustomer' => $head['refundCustomer'],
			'refundRows' => $head['refundRows'],
		);
		$dong_cu = array();
		foreach ( VHJP_Nguon::tim( 'JP_Rows', 'reportId', $ma_bc ) as $r ) {
			$dong_cu[] = array(
				'id' => $r['id'], 'mAfter' => $r['mAfter'], 'cAfter' => $r['cAfter'],
				'bank' => $r['bank'],
				/* Bảng tiền mẫu TÁCH: ba ô này là ô NHÂN VIÊN GÕ, nên sửa chúng phải huỷ chữ
				   ký doanh thu. Đầu báo cáo đã bắt được hầu hết, nhưng đổi `cash` và
				   `cashReal` CÙNG MỘT LƯỢNG thì đầu báo cáo y nguyên trong khi số kế toán
				   vừa ký đã khác. */
				'amount' => $r['amount'], 'cash' => $r['cash'], 'cashReal' => $r['cashReal'],
				'stockActual' => $r['stockActual'], 'addQty1' => $r['addQty1'],
				'addQty2' => $r['addQty2'], 'defectQty' => $r['defectQty'],
				'returnQty' => $r['returnQty'], 'refundAmt' => $r['refundAmt'],
			);
		}

		/* --- Ghi lại khu vực --- */
		VHJP_Nguon::xoa_theo( 'JP_Zones', 'reportId', $ma_bc );
		$loc = VHJP_Nguon::tim_mot( 'JP_Locations', 'id', $head['locationId'] );
		$cho_phep_xung = VHJP_CauHinh::chon_gia_xung( $loc ? $loc['chonGiaXung'] : '' );

		$ds_khu = isset( $payload['zones'] ) && is_array( $payload['zones'] ) ? $payload['zones'] : array();
		$ma_khu = array();
		$da_dung_khu = self::ids_dang_dung( $ds_khu );
		foreach ( array_values( $ds_khu ) as $i => $z ) {
			$z  = (array) $z;
			$id = self::id_moi( isset( $z['id'] ) ? VHJP_Doc::str( $z['id'] ) : '',
				$ma_bc . '-Z', $da_dung_khu );
			$ma_khu[] = $id;
			$ten = isset( $z['name'] ) ? VHJP_Doc::str( $z['name'] ) : '';
			VHJP_Nguon::them( 'JP_Zones', array(
				'id' => $id, 'reportId' => $ma_bc, 'seq' => $i + 1,
				'name' => '' !== $ten ? $ten : ( 'Khu vực ' . ( $i + 1 ) ),
				'clusterId' => isset( $z['clusterId'] ) ? VHJP_Doc::str( $z['clusterId'] ) : '',
				'note' => isset( $z['note'] ) ? VHJP_Doc::str( $z['note'] ) : '',
			) );
		}

		/* --- Tính lại từng dòng Ở MÁY CHỦ --- */
		VHJP_Nguon::xoa_theo( 'JP_Rows', 'reportId', $ma_bc );
		$ds_dong = isset( $payload['rows'] ) && is_array( $payload['rows'] ) ? $payload['rows'] : array();
		$da_dung_dong = self::ids_dang_dung( $ds_dong );
		$dong_moi = array();
		foreach ( array_values( $ds_dong ) as $i => $r ) {
			$r = (array) $r;
			$g = function ( $k, $md = '' ) use ( $r ) { return isset( $r[ $k ] ) ? $r[ $k ] : $md; };
			/* Tra qua `tra_hang()` chứ KHÔNG tra thẳng — xem khối 🔴 ở hàm ấy. */
			$hang = VHJP_CauHinh::tra_hang( $ban_do_hang, $g( 'itemCode' ) );
			$hang = is_array( $hang ) ? $hang : array();
			$h = function ( $k ) use ( $hang ) { return isset( $hang[ $k ] ) ? $hang[ $k ] : ''; };

			$loai_dong = VHJP_Doc::str( $g( 'rowKind' ) );
			if ( '' === $loai_dong ) {
				/* Mặc định theo MẪU của báo cáo, KHÔNG theo loại máy: ở mẫu TÁCH thì dòng
				   không ghi rõ loại phải là `MAY` (chỉ tiền); rơi về `MONEY` là dòng đó sinh
				   cả số bán ⇒ xuất kho HAI LẦN cùng với dòng `HANG`. */
				$loai_dong = ( VHJP_CauHinh::LOAI_XU === $loai ) ? VHJP_Tinh::DONG_COIN
					: ( ( VHJP_CauHinh::MAU_TACH === $mau ) ? VHJP_Tinh::DONG_MAY : VHJP_Tinh::DONG_MONEY );
			}

			$khoa_khu = VHJP_Doc::num( $g( 'zoneKey' ) );
			$zone = isset( $ma_khu[ $khoa_khu ] ) ? $ma_khu[ $khoa_khu ]
				: ( isset( $ma_khu[0] ) ? $ma_khu[0] : '' );

			$ma_hang = VHJP_Doc::str( $h( 'code' ) );
			if ( '' === $ma_hang ) { $ma_hang = VHJP_Doc::str( $g( 'itemCode' ) ); }
			/* Nhân viên chỉ gõ TÊN HÀNG + MÃ MISA ở bảng hàng, nên `itemCode` — thứ KHO tra
			   theo — rơi về Misa khi để trống. Thiếu bước này là dòng hàng không có mã ⇒ duyệt
			   xong kho không tìm được lớp tồn ⇒ giá vốn rơi về giá mua gần nhất. */
			if ( '' === $ma_hang ) { $ma_hang = VHJP_Doc::str( $g( 'itemMisa' ) ); }

			$d = array(
				'id'       => self::id_moi( VHJP_Doc::str( $g( 'id' ) ), $ma_bc . '-R', $da_dung_dong ),
				'reportId' => $ma_bc,
				'zoneId'   => $zone,
				/* ⚠️⚠️ Cơ sở KHÔNG bật cờ thì ÉP về mặc định — không phải chỉ ẩn ô chọn. Cổng
				   nhận lệnh từ trình duyệt, ai cũng gọi thẳng được, nên ẩn giao diện mà không
				   chặn ở máy chủ là KHÔNG CHẶN GÌ CẢ. */
				'giaXung'  => $cho_phep_xung ? VHJP_Tinh::gia_xung( $g( 'giaXung' ) )
					: VHJP_Tinh::GIA_XUNG_MAC_DINH,
				'seq'      => $i + 1,
				'rowKind'  => $loai_dong,
				'machineId'   => VHJP_Doc::str( $g( 'machineId' ) ),
				'machineCode' => VHJP_Doc::str( $g( 'machineCode' ) ),
				/* ⚠️ SNAP về cách viết CHUẨN của danh mục — đây là chỗ DUY NHẤT làm việc đó, và
				   phải làm ở đường LƯU chứ không ở mười mấy chỗ đọc. Mã KHÔNG có trong danh mục
				   thì GIỮ NGUYÊN nguyên văn nhân viên gõ: viết hoa nó lên là sinh thêm một biến
				   thể mới trong khi kế toán đang đối chiếu. */
				'itemCode' => $ma_hang,
				'itemMisa' => VHJP_Doc::str( $g( 'itemMisa' ) ) ? VHJP_Doc::str( $g( 'itemMisa' ) ) : VHJP_Doc::str( $h( 'misa' ) ),
				'itemName' => VHJP_Doc::str( $g( 'itemName' ) ) ? VHJP_Doc::str( $g( 'itemName' ) ) : VHJP_Doc::str( $h( 'name' ) ),
				/* Để phép tính dòng tự suy giá (Misa trước) khi danh mục không có giá — đừng
				   chốt cứng giá-từ-mã ở đây, mã nội bộ kiểu A-077 không có tiền tố giá. */
				'price'    => VHJP_Doc::num( $h( 'price' ) ),
				/* Ô trống PHẢI ở lại là ô trống, không thì phép tính không phân biệt được
				   "chưa nhập" với "nhập số 0". */
				'mBefore'  => VHJP_Doc::num_hoac_trong( $g( 'mBefore', '' ) ),
				'mAfter'   => VHJP_Doc::num_hoac_trong( $g( 'mAfter', '' ) ),
				'cBefore'  => VHJP_Doc::num_hoac_trong( $g( 'cBefore', '' ) ),
				'cAfter'   => VHJP_Doc::num_hoac_trong( $g( 'cAfter', '' ) ),
				'bank'     => VHJP_Doc::num( $g( 'bank' ) ),
				'cash'     => VHJP_Doc::num_hoac_trong( $g( 'cash', '' ) ),
				'amount'   => VHJP_Doc::num_hoac_trong( $g( 'amount', '' ) ),
				'cashReal' => VHJP_Doc::num_hoac_trong( $g( 'cashReal', '' ) ),
				'hOpen'    => VHJP_Doc::num( $g( 'hOpen' ) ),
				'hBefore'  => VHJP_Doc::num_hoac_trong( $g( 'hBefore', '' ) ),
				'hAfter'   => VHJP_Doc::num_hoac_trong( $g( 'hAfter', '' ) ),
				'stockOut' => VHJP_Doc::num( $g( 'stockOut' ) ),
				'topupNote' => VHJP_Doc::str( $g( 'topupNote' ) ),
				'giaXu'    => VHJP_Doc::num( $g( 'giaXu' ) ),
				'xuDaysJson' => isset( $r['xuDays'] ) && $r['xuDays']
					? wp_json_encode( $r['xuDays'] ) : VHJP_Doc::str( $g( 'xuDaysJson' ) ),
				'xuLa'     => VHJP_Doc::num( $g( 'xuLa' ) ),
				'stockOpen' => VHJP_Doc::num( $g( 'stockOpen' ) ),
				'addQty1'  => VHJP_Doc::num( $g( 'addQty1' ) ),
				'addQty2'  => VHJP_Doc::num( $g( 'addQty2' ) ),
				'stockActual' => VHJP_Doc::num_hoac_trong( $g( 'stockActual', '' ) ),
				'defectQty' => VHJP_Doc::num( $g( 'defectQty' ) ),
				'returnQty' => VHJP_Doc::num( $g( 'returnQty' ) ),
				/* Hoàn khách theo mã — chỉ `refundAmt` là ô nhân viên gõ; số lượng hoàn do phép
				   tính dòng suy ra ngay sau đây, ĐỪNG nhận từ giao diện. */
				'refundAmt' => abs( VHJP_Doc::num( $g( 'refundAmt' ) ) ),
				'refundRowNote' => VHJP_Doc::str( $g( 'refundRowNote' ) ),
				/* ĐÃ BÁN — dòng HÀNG của mẫu tách thì đây là ô NHÂN VIÊN GÕ. Mọi loại dòng khác
				   thì phép tính ghi đè ngay sau đây, nên nhận vào không ảnh hưởng gì. */
				'soldQty'  => VHJP_Doc::num_hoac_trong( $g( 'soldQty', '' ) ),
				'note'     => VHJP_Doc::str( $g( 'note' ) ),
			);
			$dong_moi[] = VHJP_Tinh::dong( $d, $loai );
		}
		if ( $dong_moi ) { VHJP_Nguon::them_nhieu( 'JP_Rows', $dong_moi ); }

		/* --- Tổng kết --- */
		$hp = isset( $payload['head'] ) && is_array( $payload['head'] ) ? $payload['head'] : array();
		$head['adjMachine']     = VHJP_Doc::num( isset( $hp['adjMachine'] ) ? $hp['adjMachine'] : 0 );
		$head['adjMachineNote'] = VHJP_Doc::str( isset( $hp['adjMachineNote'] ) ? $hp['adjMachineNote'] : '' );
		$head['refundCustomer'] = abs( VHJP_Doc::num( isset( $hp['refundCustomer'] ) ? $hp['refundCustomer'] : 0 ) );
		$head['refundNote']     = VHJP_Doc::str( isset( $hp['refundNote'] ) ? $hp['refundNote'] : '' );
		$head['remark']         = VHJP_Doc::str( isset( $hp['remark'] ) ? $hp['remark'] : '' );
		$head = VHJP_Tinh::bao_cao( $head, $dong_moi );

		VHJP_Nguon::sua( 'JP_Reports', $ma_bc, array(
			'revMeter' => $head['revMeter'], 'revBank' => $head['revBank'],
			'revCashMeter' => $head['revCashMeter'],
			'revHang' => $head['revHang'], 'lechTienHang' => $head['lechTienHang'],
			'adjMachine' => $head['adjMachine'], 'adjMachineNote' => $head['adjMachineNote'],
			'refundCustomer' => $head['refundCustomer'], 'refundNote' => $head['refundNote'],
			'refundRows' => $head['refundRows'],
			'cashActual' => $head['cashActual'], 'totalSubmit' => $head['totalSubmit'],
			'warnCount' => $head['warnCount'], 'remark' => $head['remark'],
		) );

		/* --- Chữ ký: chỉ huỷ đúng phần nhân viên vừa sửa --- */
		$doi = self::diff_phan( $head_cu, $dong_cu, $head, $dong_moi );
		if ( $doi ) { self::reset_chu_ky( $head, $doi, $u ); }

		/*
		 * ⚠️ CHỈ TRẢ VỀ KHOÁ CÓ THẬT TRÊN DÒNG.
		 *
		 * Mỗi loại dòng chỉ sinh ra một phần trong số các cột này — dòng máy tiền không có
		 * `cActual` hay `xuTong`. Bên kia đọc một khoá không có ra `undefined`, và `JSON`
		 * BỎ HẲN khoá ấy khi đóng gói; giao diện dựa vào chuyện đó (`if (r.xuTong)`). Bên này
		 * mà điền `0` vào là thêm một cột "có số 0" ở chỗ vốn không có cột — vừa lệch khỏi mã
		 * gốc, vừa làm giao diện tưởng dòng máy tiền có bảng xu.
		 */
		$ra_dong = array();
		foreach ( $dong_moi as $r ) {
			$mot = array( 'id' => (string) $r['id'], 'rowKind' => VHJP_Doc::str( $r['rowKind'] ) );
			foreach ( array( 'mActual', 'cActual', 'collection', 'amount', 'soldQty', 'hLeft',
				'xuTong', 'stockLeftCalc', 'refundAmt', 'refundQty' ) as $c ) {
				if ( array_key_exists( $c, $r ) ) { $mot[ $c ] = $r[ $c ]; }
			}
			$mot['warns'] = isset( $r['warns'] ) ? $r['warns'] : array();
			$ra_dong[] = $mot;
		}

		return array(
			'ok' => true, 'reportId' => $ma_bc,
			'resetSign' => $doi,
			'totals' => array(
				'revMeter' => $head['revMeter'], 'revBank' => $head['revBank'],
				'revCashMeter' => $head['revCashMeter'], 'adjMachine' => $head['adjMachine'],
				'refundCustomer' => $head['refundCustomer'],
				'refundRows' => $head['refundRows'], 'refundTotal' => $head['refundTotal'],
				'cashActual' => $head['cashActual'], 'totalSubmit' => $head['totalSubmit'],
				'revHang' => $head['revHang'], 'revMeterRong' => $head['revMeterRong'],
				'lechTienHang' => $head['lechTienHang'],
				'coBangTong' => ! empty( $head['coBangTong'] ),
			),
			'warnCount' => $head['warnCount'],
			'headWarns' => isset( $head['warns'] ) ? $head['warns'] : array(),
			'rows' => $ra_dong,
		);
	}

	/**
	 * CẤP MÃ KHÔNG TRÙNG cho khu vực / dòng.
	 *
	 * 🔴 Trước đây mã dòng mới là `<báo cáo>-R<vị trí>`, mà dòng cũ giữ mã cũ. Có -R1 -R2 -R3,
	 *    xoá -R1 rồi thêm một dòng ⇒ dòng mới nằm ở vị trí 3 ⇒ mã -R3 TRÙNG dòng cũ. Hệ quả:
	 *    ảnh gắn sai dòng, phép so để huỷ chữ ký so nhầm dòng.
	 */
	public static function ids_dang_dung( $ds ) {
		$da = array();
		foreach ( (array) $ds as $x ) {
			$id = VHJP_Doc::str( isset( $x['id'] ) ? $x['id'] : '' );
			if ( '' !== $id ) { $da[ $id ] = 1; }
		}
		return $da;
	}

	/** Giữ mã cũ nếu có và chưa bị chiếm; không thì cấp số nhỏ nhất còn trống. */
	public static function id_moi( $id_cu, $tien_to, &$da_dung ) {
		if ( '' !== $id_cu && ( ! isset( $da_dung[ $id_cu ] ) || 2 !== $da_dung[ $id_cu ] ) ) {
			$da_dung[ $id_cu ] = 2;
			return $id_cu;
		}
		$n = 1;
		do {
			$id = $tien_to . $n;
			$n++;
		} while ( isset( $da_dung[ $id ] ) );
		$da_dung[ $id ] = 2;
		return $id;
	}

	/* ═══════════════════════ NỘP BÁO CÁO ═══════════════════════ */

	/**
	 * NỘP BÁO CÁO.
	 *
	 * =========================================================================================
	 * 🔴 BỐN PHÉP CHẶN, THEO ĐÚNG THỨ TỰ NÀY. Thứ tự không phải ngẫu nhiên.
	 * =========================================================================================
	 *   ① chưa có dòng nào          — không có gì để nộp
	 *   ② còn ô chỉ số sau bỏ trống — số đó là SỐ ĐẦU KỲ của kỳ sau, mà ô đầu kỳ bị khoá
	 *   ③ QR lớn hơn Thành tiền     — số lệch tính ra từ chính mấy ô này
	 *   ④ chưa ghi lý do lệch máy / hoàn khách
	 *
	 * ⚠️ ③ đặt SAU ② vì thiếu số thì phải nhắc thiếu trước; đặt TRƯỚC ④ vì số lệch tính ra từ
	 *    chính mấy ô ấy — bắt người ta giải thích một con số sai là vô nghĩa.
	 *
	 * ⚠️ THIẾU ẢNH THÌ KHÔNG CHẶN, chỉ chốt cảnh báo vào báo cáo. Mạng ở trung tâm thương mại
	 *    hay hỏng; chặn là nhân viên mắc kẹt cả buổi.
	 */
	public static function nop( $u, $ma_bc ) {
		$head = VHJP_Nguon::tim_mot( 'JP_Reports', 'id', $ma_bc );
		if ( ! $head ) { throw new Exception( 'Không tìm thấy báo cáo' ); }
		VHJP_Auth::can_coso( $u, $head['locationId'] );
		self::can_chu( $u, $head );
		if ( ! self::sua_duoc( $u, $head ) ) {
			throw new Exception( 'Báo cáo không ở trạng thái nộp được' );
		}

		if ( ! VHJP_Nguon::lay_khoa( $ma_bc ) ) {
			throw new Exception( 'Báo cáo này đang được lưu ở một cửa sổ khác — thử lại sau vài giây' );
		}
		try {
			return self::nop_trong_khoa( $u, $head, $ma_bc );
		} finally {
			VHJP_Nguon::tra_khoa( $ma_bc );
		}
	}

	private static function nop_trong_khoa( $u, $head, $ma_bc ) {
		$rows = VHJP_Nguon::tim( 'JP_Rows', 'reportId', $ma_bc );
		if ( ! $rows ) { throw new Exception( 'Chưa có dòng nào để nộp' ); }

		$thieu = self::thieu_chi_so( $rows );
		if ( $thieu ) {
			throw new Exception( 'Còn ' . count( $thieu ) . ' ô chưa nhập chỉ số sau / tồn cuối kỳ: '
				. implode( ', ', array_slice( $thieu, 0, 5 ) ) . ( count( $thieu ) > 5 ? '…' : '' )
				. '. Nhập đủ rồi nộp — để trống sẽ làm sai số đầu kỳ của kỳ sau.' );
		}

		$qr_sai = VHJP_Tinh::qr_vuot_tien( $rows );
		if ( $qr_sai ) {
			throw new Exception( 'QR lớn hơn Thành tiền — app của máy báo "Thành tiền" ĐÃ GỒM cả '
				. 'tiền mặt lẫn QR, nên QR không thể lớn hơn. Sửa lại: '
				. implode( ' · ', array_slice( $qr_sai, 0, 4 ) ) . ( count( $qr_sai ) > 4 ? '…' : '' )
				. '. Để nguyên thì tiền phải nộp tính ra ÂM và cơ sở coi như không phải nộp gì.' );
		}

		if ( 0 !== VHJP_Doc::num( $head['adjMachine'] )
			&& '' === VHJP_Doc::str( $head['adjMachineNote'] ) ) {
			throw new Exception( 'Nhập lý do cho khoản lệch máy' );
		}
		if ( 0 !== VHJP_Doc::num( $head['refundCustomer'] )
			&& '' === VHJP_Doc::str( $head['refundNote'] ) ) {
			throw new Exception( 'Nhập lý do cho khoản hoàn khách' );
		}

		$pw = VHJP_Anh::canh_bao( $ma_bc, $head['locationId'] )['warns'];

		$ok = VHJP_Nguon::sua( 'JP_Reports', $ma_bc, array(
			'status'        => self::TT_CHO_DUYET,
			'submittedAt'   => gmdate( 'Y-m-d H:i:s', time() + 7 * 3600 ),
			'photoWarnJson' => $pw ? wp_json_encode( $pw, JSON_UNESCAPED_UNICODE ) : '',
			/* Nộp lại sau khi kế toán trả về thì phải XOÁ dấu vết lần trả về — để nguyên là
			   kế toán mở ra thấy một báo cáo vừa nộp mà vẫn mang lý do bị từ chối cũ. */
			'rejectPart' => '', 'rejectReason' => '', 'rejectBy' => '', 'rejectAt' => null,
		) );
		/* 🔴 Ghi hỏng thì NÓI RA. Trả "đã nộp" cho một báo cáo vẫn đang là nháp là nhân viên
		   về nhà, kế toán không thấy gì, và không ai biết cho tới cuối kỳ. */
		if ( ! $ok ) {
			throw new Exception( 'Không ghi được lượt nộp — thử lại, hoặc báo người quản trị' );
		}

		VHJP_NhatKy::ghi( $u, 'REPORT_SUBMIT', $ma_bc,
			VHJP_Doc::str( $head['locationName'] ),
			array( 'rows' => count( $rows ), 'total' => VHJP_Doc::num( $head['totalSubmit'] ),
				'thieuAnh' => count( $pw ) ) );

		return array( 'ok' => true, 'status' => self::TT_CHO_DUYET, 'thieuAnh' => count( $pw ),
			'msg' => 'Đã nộp báo cáo ' . VHJP_Doc::dmy( $head['fromDate'] ) . ' – '
				. VHJP_Doc::dmy( $head['toDate'] )
				. ( $pw ? ' · còn thiếu ' . count( $pw ) . ' chỗ ảnh, kế toán sẽ thấy' : '' ) );
	}

	/**
	 * CÁC Ô CÒN ĐỂ TRỐNG "CHỈ SỐ SAU" — phép chặn nộp.
	 *
	 * 🔴 Không phải chuyện hình thức: chỉ số sau của kỳ này là chỉ số ĐẦU KỲ của kỳ sau, và ô
	 *    đầu kỳ BỊ KHOÁ nên nhân viên kỳ sau không sửa được. Để trống ⇒ kỳ sau nối ra 0 ⇒ cả
	 *    con số trên mặt đồng hồ thành doanh thu của một kỳ.
	 *
	 * ⚠️ Đồng hồ ĐẾM TRỨNG không chặn — nhiều điểm chưa dùng tới nó, chỉ cảnh báo W8.
	 *
	 * ⚠️ DÒNG MA không chặn nộp. Ca thật 26/08/2026: một dòng thứ 16 để trống làm Phú Quốc
	 *    KHÔNG NỘP ĐƯỢC BÁO CÁO NÀO, và câu chặn còn chỉ vào cột "ô máy" mà mẫu TÁCH không có.
	 *
	 * ⚠️ HAI CÂU RIÊNG cho "chưa chọn ô máy" và "thiếu chỉ số", KHÔNG gộp. Gộp thì dòng máy xu
	 *    có chỉ số MONEY thật, chỉ thiếu chỉ số COIN, lại ra câu *"chưa chọn ô máy"* — nói SAI
	 *    thứ đang thiếu, nhân viên đi chọn ô máy rồi vẫn bị chặn.
	 *
	 * ⚠️ "Chọn gì" phải theo ĐÚNG MẪU: mẫu TÁCH không có cột ô máy, nên dòng gõ tay ở đó phải
	 *    nói MÃ HÀNG. Chỉ vào một cột không có trên màn hình là câu vô dụng.
	 */
	public static function thieu_chi_so( $rows ) {
		$thieu = array();
		foreach ( (array) $rows as $r ) {
			$kind = VHJP_Doc::str( isset( $r['rowKind'] ) ? $r['rowKind'] : '' );
			if ( '' === $kind ) { $kind = VHJP_Tinh::DONG_MONEY; }
			/* Bảng tồn kho và kho ngoài không có đồng hồ. */
			if ( VHJP_Tinh::DONG_STOCK === $kind || VHJP_Tinh::DONG_NGOAI === $kind ) { continue; }
			if ( VHJP_Tinh::dong_trong( $r ) ) { continue; }

			$o = function ( $k ) use ( $r ) { return isset( $r[ $k ] ) ? $r[ $k ] : ''; };
			$co_danh_tinh = false;
			foreach ( array( 'machineId', 'machineCode', 'itemCode', 'itemMisa', 'itemName' ) as $c ) {
				if ( '' !== VHJP_Doc::str( $o( $c ) ) ) { $co_danh_tinh = true; }
			}
			$ten = VHJP_Doc::str( $o( 'machineCode' ) );
			if ( '' === $ten ) { $ten = VHJP_Doc::str( $o( 'itemCode' ) ); }
			if ( '' === $ten ) { $ten = 'dòng ' . VHJP_Doc::str( $o( 'seq' ) ); }

			if ( ! $co_danh_tinh ) {
				$can = ( VHJP_Tinh::DONG_HANG === $kind
					|| ( VHJP_Tinh::DONG_MAY === $kind && VHJP_Tinh::may_go_tay( $r ) ) )
					? 'mã hàng' : 'ô máy';
				$thieu[] = 'dòng ' . VHJP_Doc::str( $o( 'seq' ) ) . ' — chưa chọn ' . $can
					. ', chọn hoặc xoá dòng này';
			}

			/* Dòng HÀNG của mẫu TÁCH không có đồng hồ, nhưng ĐÃ BÁN chặn nộp y hệt: tồn cuối
			   kỳ này là tồn đầu kỳ sau và ô đầu kỳ bị khoá. Ô chặn là `soldQty` (nhân viên
			   gõ), KHÔNG phải `stockActual` — ô đếm ấy là TUỲ CHỌN nên chặn theo nó là chặn
			   oan mọi mã không đếm. */
			if ( VHJP_Tinh::DONG_HANG === $kind ) {
				if ( VHJP_Doc::blank( $o( 'soldQty' ) ) ) { $thieu[] = $ten . ' (số đã bán)'; }
				continue;
			}

			/*
			 * Dòng MÁY của mẫu TÁCH — cơ sở ấy KHÔNG CÒN ĐỒNG HỒ. Chặn theo `mAfter` là chặn
			 * nộp MỌI báo cáo của nó mãi mãi, vì ô đó không còn trên bảng.
			 * Ba ô phải có, mỗi ô một lý do riêng:
			 *   `amount`   thiếu ⇒ doanh thu của ô máy đó VỀ 0 mà không ai báo
			 *   `cash`     thiếu ⇒ lệch tiền mặt trả 0 ⇒ thừa/thiếu quỹ MẤT DẤU
			 *   `cashReal` thiếu ⇒ chính là TIỀN PHẢI NỘP, không có nó thì không biết cơ sở
			 *                      phải giao về bao nhiêu
			 * Ô QR để trống là 0 — hợp lệ, đa số dòng không có khách quét mã.
			 * ⚠️ Báo cáo đời cũ CÓ đồng hồ thì vẫn chặn theo đồng hồ: tiền của chúng suy ra từ
			 *    chỉ số, đòi thêm ba ô kia là chặn oan một báo cáo đã đủ số.
			 */
			if ( VHJP_Tinh::DONG_MAY === $kind && VHJP_Tinh::may_go_tay( $r ) ) {
				if ( VHJP_Doc::blank( $o( 'amount' ) ) )   { $thieu[] = $ten . ' (thành tiền)'; }
				if ( VHJP_Doc::blank( $o( 'cash' ) ) )     { $thieu[] = $ten . ' (tiền mặt app)'; }
				if ( VHJP_Doc::blank( $o( 'cashReal' ) ) ) { $thieu[] = $ten . ' (tiền mặt thực tế)'; }
				continue;
			}

			if ( VHJP_Doc::blank( $o( 'mAfter' ) ) ) {
				$thieu[] = $ten . ( VHJP_Tinh::DONG_COIN === $kind ? ' (MONEY)' : '' );
			}
			if ( VHJP_Tinh::DONG_COIN === $kind && VHJP_Doc::blank( $o( 'cAfter' ) ) ) {
				$thieu[] = $ten . ' (COIN)';
			}
		}
		return $thieu;
	}

	/* ═══════════════════════ RESET THÔNG MINH CHỮ KÝ ═══════════════════════ */

	/**
	 * PHẦN NÀO CÓ SỐ ĐỔI. Trả mảng rỗng nếu không đổi gì — để không huỷ chữ ký oan khi nhân
	 * viên chỉ bấm Lưu mà không sửa gì.
	 *
	 * Phân loại theo NGƯỜI SOÁT chứ không theo cột: chỉ số đồng hồ và chuyển khoản là việc của
	 * phần doanh thu; tồn kho, bổ sung, lỗi/mẫu, trả kho là phần hàng hoá.
	 */
	public static function diff_phan( $head_cu, $dong_cu, $head_moi, $dong_moi ) {
		$phan = array();
		$n = function ( $a, $k ) { return VHJP_Doc::num( isset( $a[ $k ] ) ? $a[ $k ] : 0 ); };

		foreach ( array( 'revMeter', 'revBank', 'adjMachine', 'refundCustomer', 'refundRows' ) as $c ) {
			if ( $n( $head_cu, $c ) !== $n( $head_moi, $c ) ) { $phan[ self::PHAN_TIEN ] = 1; }
		}

		$cu = array(); $moi = array();
		foreach ( (array) $dong_cu as $r )  { $cu[ (string) $r['id'] ] = $r; }
		foreach ( (array) $dong_moi as $r ) { $moi[ (string) $r['id'] ] = $r; }

		foreach ( array_unique( array_merge( array_keys( $cu ), array_keys( $moi ) ) ) as $k ) {
			if ( ! isset( $cu[ $k ] ) || ! isset( $moi[ $k ] ) ) {
				/* Thêm hoặc xoá dòng: đụng CẢ HAI phần. */
				$phan[ self::PHAN_TIEN ] = 1;
				$phan[ self::PHAN_HANG ] = 1;
				continue;
			}
			$a = $cu[ $k ]; $b = $moi[ $k ];
			foreach ( array( 'mAfter', 'cAfter', 'bank', 'amount', 'cash', 'cashReal' ) as $c ) {
				if ( $n( $a, $c ) !== $n( $b, $c ) ) { $phan[ self::PHAN_TIEN ] = 1; }
			}
			foreach ( array( 'stockActual', 'addQty1', 'addQty2', 'defectQty', 'returnQty' ) as $c ) {
				if ( $n( $a, $c ) !== $n( $b, $c ) ) { $phan[ self::PHAN_HANG ] = 1; }
			}
		}
		return array_keys( $phan );
	}

	/**
	 * NHÂN VIÊN SỬA PHẦN NÀO THÌ CHỈ CHỮ KÝ PHẦN ĐÓ BỊ HUỶ.
	 *
	 * Sửa một con số tiền thì không có lý gì bắt kế toán soát lại toàn bộ bảng hàng hoá.
	 *
	 * ⚠️ Chữ ký GỘP (đời một-chữ-ký) tính là ký cả hai phần, nên sửa phần nào cũng phải xoá
	 *    nó — không thì nó HỒI SINH chữ ký vừa bị huỷ. Nhưng phải cấp lại chữ ký riêng cho
	 *    phần KHÔNG bị sửa, không thì xoá gộp là mất luôn cả chữ ký của phần người ta không
	 *    đụng tới.
	 *
	 * ⚠️ Huỷ chữ ký thì báo cáo KHÔNG CÒN HOÀN TẤT — trả nó về "chờ duyệt". Để nguyên
	 *    `HOAN_TAT` là một báo cáo hoàn tất mà thiếu chữ ký, và mọi phép đếm sau đó tin nó.
	 */
	public static function reset_chu_ky( $head, $phan_doi, $nguoi ) {
		if ( ! $phan_doi ) { return array(); }
		$k = self::chu_ky( $head );
		$f = array(); $msg = array();

		$sua_tien = in_array( self::PHAN_TIEN, $phan_doi, true );
		$sua_hang = in_array( self::PHAN_HANG, $phan_doi, true );

		if ( $sua_tien && '' !== $k['rev']['by'] ) {
			$f['apprRevBy'] = ''; $f['apprRevAt'] = null; $msg[] = 'doanh thu';
		}
		if ( $sua_hang && '' !== $k['stock']['by'] ) {
			$f['apprStockBy'] = ''; $f['apprStockAt'] = null; $msg[] = 'hàng hoá';
		}
		if ( ! $msg ) { return array(); }

		$gop = VHJP_Doc::str( isset( $head['apprBy'] ) ? $head['apprBy'] : '' );
		if ( '' !== $gop ) {
			$luc = VHJP_Doc::str( isset( $head['apprAt'] ) ? $head['apprAt'] : '' );
			if ( '' === $luc ) { $luc = gmdate( 'Y-m-d H:i:s', time() + 7 * 3600 ); }
			$f['apprBy'] = ''; $f['apprAt'] = null;
			if ( ! $sua_tien ) { $f['apprRevBy'] = $gop;   $f['apprRevAt'] = $luc; }
			if ( ! $sua_hang ) { $f['apprStockBy'] = $gop; $f['apprStockAt'] = $luc; }
		}

		if ( self::TT_HOAN_TAT === VHJP_Doc::str( $head['status'] ) ) {
			$f['status'] = self::TT_CHO_DUYET;
		}

		VHJP_Nguon::sua( 'JP_Reports', $head['id'], $f );
		VHJP_NhatKy::ghi( $nguoi, 'RESET_SIGN', $head['id'],
			VHJP_Doc::str( isset( $head['locationName'] ) ? $head['locationName'] : '' ),
			'Huỷ chữ ký: ' . implode( ', ', $msg ) );
		return $msg;
	}

	/* ═══════════════════════ TIỆN ═══════════════════════ */

	/**
	 * Đọc một ô của bản ghi, thiếu thì ra chuỗi rỗng.
	 *
	 * ⚠️ Bên kia `h.cotLa` là `undefined` rồi `jpStr_`/`jpNum_` nuốt gọn. Bên này thiếu khoá
	 *    là một CẢNH BÁO PHP, mà cảnh báo in giữa JSON là giao diện nhận về rác. Dòng đời cũ
	 *    thiếu cột là chuyện thường, nên đọc qua cửa này.
	 */
	private static function o( $hang ) {
		$h = (array) $hang;
		return function ( $k ) use ( $h ) {
			return isset( $h[ $k ] ) ? $h[ $k ] : '';
		};
	}

	/** Chuỗi JSON thành mảng. Hỏng thì ra mảng rỗng — không được làm cả báo cáo không mở được. */
	private static function json_mang( $s ) {
		$s = VHJP_Doc::str( $s );
		if ( '' === $s ) { return array(); }
		$v = json_decode( $s, true );
		return is_array( $v ) ? $v : array();
	}
}
