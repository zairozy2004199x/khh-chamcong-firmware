<?php
/**
 * KHO HAI TẦNG — `JP2_09_Kho.gs`.
 *
 * =================================================================================================
 * HAI TẦNG LÀ GÌ
 * =================================================================================================
 * · **Kho tổng** (`TONG`) — nơi hàng về từ nhà cung cấp. Kho DUY NHẤT biết giá mua.
 * · **Kho cơ sở** (mã kho = mã cơ sở) — hàng đã chuyển xuống từng điểm bán.
 *
 * Chuyển từ tổng xuống cơ sở (`XUAT_CS`) là MỘT thao tác nhưng HAI vế: xuất ở kho tổng, rồi nhập
 * vào kho cơ sở **đúng giá vốn vừa xuất**. Làm một vế thôi là hàng bốc hơi giữa đường — tồn tổng
 * giảm mà tồn cơ sở không tăng, và không ai biết mất ở đâu. `TRA_KHO` là đúng cặp ấy quay ngược.
 *
 * =================================================================================================
 * 🔴 GIÁ VỐN TÍNH THEO LỚP (FIFO), KHÔNG THEO GIÁ BÌNH QUÂN
 * =================================================================================================
 * Mỗi lượt nhập đẻ một **lớp** (`JP_KhoLop`) mang số lượng còn lại và đơn giá của chính lượt nhập
 * ấy. Xuất thì ăn dần từ lớp cũ nhất.
 *
 * Dùng giá bình quân thì huỷ một phiếu nhập của tháng trước là **giá vốn của mọi phiếu xuất sau
 * đó đổi theo** — trong khi sổ 632 đã khoá và báo cáo đã ký. Theo lớp thì phiếu xuất ghi rõ nó ăn
 * lớp nào với giá nào; huỷ phiếu nhập mà lớp đã bị ăn thì bị CHẶN, chứ không lặng lẽ sửa lịch sử.
 *
 * =================================================================================================
 * 🔴 MỌI ĐƯỜNG HÀNG VÀO ĐỀU LÀ MỘT PHIẾU NHẬP
 * =================================================================================================
 * Mua · đầu kỳ · kiểm kê thừa · nhận điều chuyển · cơ sở trả về — năm đường, cùng một bảng
 * `JP_KhoNhap`, khác nhau ở `loaiNhap`. Và mọi lớp tồn mang `nguon` = MÃ PHIẾU đã đẻ ra nó.
 *
 * Vì sao không để mỗi đường một kiểu `nguon` riêng: bảng N-X-T phải tách nhập ra bốn cột vì bốn
 * nguồn **hạch toán khác hẳn nhau** (mua sinh công nợ 331, ba đường kia không nợ ai). Phân loại
 * bằng chuỗi tự do trong `nguon` thì thêm một đường là thêm một chỗ phải nhớ sửa; phân loại bằng
 * phiếu thì đọc `loaiNhap` là xong, và mỗi lớp truy ngược được về đúng chứng từ đã đẻ ra nó.
 *
 * =================================================================================================
 * 🔴 THIẾU LỚP THÌ VẪN XUẤT, NHƯNG ĐÁNH DẤU
 * =================================================================================================
 * Kho ngoài đời hay lệch sổ: hàng đã dùng mà phiếu nhập chưa ai gõ. Chặn xuất là nhân viên đứng
 * chờ kế toán giữa ca. Nên vẫn ghi phiếu xuất, đơn giá 0, và bật cờ `thieuLop` — giao diện in đỏ,
 * kế toán biết đường đi tìm phiếu nhập còn thiếu.
 *
 * Im lặng cho qua thì tệ hơn cả hai: giá vốn ra 0 mà sổ vẫn cân, không ai báo.
 *
 * =================================================================================================
 * 🔴 HAI ĐƯỜNG TÍNH TỒN, VÀ PHẢI GIỮ CẢ HAI
 * =================================================================================================
 * · **Suy ngược** — lấy `qtyRemaining` THẬT của lớp tồn lúc này, trừ ngược mọi chuyển động sau kỳ.
 * · **Suy xuôi** — cộng dồn số lượng trên CHỨNG TỪ từ đầu đến trước kỳ.
 *
 * Hai đường độc lập, nên so nhau mới có nghĩa. Lệch đúng bằng phần mà phiếu xuất đã ghi nhưng lớp
 * chưa bị trừ đủ (dòng `thieuLop`). Giữ một đường thôi thì bảng luôn "cân" và không kiểm được gì —
 * `tonCuoi` tính TỪ `tonDau` cộng đúng hai số vừa dùng thì bằng nhau là chuyện đương nhiên.
 *
 * @package VHCP_JP
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHJP_Kho {

	/** Mã kho tổng. Mọi mã kho khác là mã cơ sở. */
	const KHO_TONG = 'TONG';

	/* Năm `loaiNhap`. Đổi chuỗi ở đây là mọi phiếu cũ rơi khỏi cột của nó trong bảng N-X-T. */
	const N_MUA     = 'MUA';
	const N_DC      = 'DC';
	const N_DAU_KY  = 'DAU_KY';
	const N_KIEM_KE = 'KIEM_KE';
	const N_TRA_KHO = 'TRA_KHO';

	const XUAT_CS  = 'XUAT_CS';
	const TRA_KHO  = 'TRA_KHO';

	/**
	 * Năm loại xuất mà kế toán chọn được trên phiếu, cộng hai loại MÁY sinh.
	 *
	 * · `tk`      — tài khoản Nợ ghi NGAY khi lưu phiếu. Rỗng = điều chuyển nội bộ, CHƯA sinh
	 *               giá vốn; giá vốn ra `6321` lúc bán ở cơ sở và kế toán duyệt báo cáo.
	 * · `canCoSo` — phiếu phải chọn một cơ sở.
	 * · `nguoc`   — hàng đi NGƯỢC: kho nguồn là CƠ SỞ, kho đích là kho tổng.
	 * · `may`     — máy sinh, không cho chọn tay trên phiếu.
	 * · `o`       — rơi vào cột nào của bảng N-X-T.
	 *
	 * ⚠️ `BAN` và `KIEM_KE` KHÔNG được cho vào ô chọn: bán ra sinh từ lượt kế toán duyệt báo cáo
	 *    của nhân viên, kiểm kê thiếu sinh từ phiếu kiểm kê. Gõ tay được hai loại ấy là mở đường
	 *    ghi giá vốn 6321 không có báo cáo nào đứng sau.
	 */
	public static function bang_loai_xuat() {
		return array(
			self::XUAT_CS => array( 'ten' => 'Xuất xuống cơ sở', 'tk' => '',
				'canCoSo' => 1, 'nguoc' => 0, 'may' => 0, 'o' => 'xuatCoSo' ),
			self::TRA_KHO => array( 'ten' => 'Cơ sở trả về kho tổng', 'tk' => '',
				'canCoSo' => 1, 'nguoc' => 1, 'may' => 0, 'o' => 'traVe' ),
			'XE_MAU'      => array( 'ten' => 'Xé mẫu trưng bày', 'tk' => '64116',
				'canCoSo' => 0, 'nguoc' => 0, 'may' => 0, 'o' => 'xuatXeMau' ),
			'TANG_MALL'   => array( 'ten' => 'Tặng mall / khách', 'tk' => '64116',
				'canCoSo' => 0, 'nguoc' => 0, 'may' => 0, 'o' => 'xuatTang' ),
			'DIEU_CHUYEN' => array( 'ten' => 'Gửi đi tỉnh khác', 'tk' => '',
				'canCoSo' => 0, 'nguoc' => 0, 'may' => 0, 'o' => 'xuatTinh' ),
			'BAN'         => array( 'ten' => 'Bán ra (từ báo cáo đã duyệt)', 'tk' => '6321',
				'canCoSo' => 1, 'nguoc' => 0, 'may' => 1, 'o' => 'xuatBan' ),
			'KIEM_KE'     => array( 'ten' => 'Kiểm kê thiếu', 'tk' => '6321',
				'canCoSo' => 0, 'nguoc' => 0, 'may' => 1, 'o' => 'xuatKiemKe' ),
		);
	}

	/** `jpKhoDanhSachLoai` — chỉ những loại kế toán được chọn tay. */
	public static function danh_sach_loai( $u ) {
		self::can_kt( $u );
		$ra = array();
		foreach ( self::bang_loai_xuat() as $ma => $x ) {
			if ( $x['may'] ) { continue; }
			$ra[] = array( 'ma' => $ma, 'ten' => $x['ten'], 'tk' => $x['tk'],
				'canCoSo' => $x['canCoSo'] ? true : false, 'nguoc' => $x['nguoc'] ? true : false );
		}
		return $ra;
	}

	/* ═════════════════════════════════════════════════════════════════════ TIỆN ÍCH ══════ */

	public static function la_kho_tong( $kho ) {
		return self::KHO_TONG === VHJP_Doc::str( $kho );
	}

	public static function ten_kho( $kho ) {
		$kho = VHJP_Doc::str( $kho );
		if ( '' === $kho ) { return ''; }
		if ( self::la_kho_tong( $kho ) ) { return 'KHO TỔNG'; }
		$l = VHJP_Nguon::tim_mot( 'JP_Locations', 'id', $kho );
		return $l ? VHJP_Doc::str( $l['name'] ) : $kho;
	}

	/** Mọi thao tác sổ kho đều của kế toán. Nhân viên cơ sở không sờ vào sổ kho. */
	private static function can_kt( $u ) {
		if ( ! VHJP_Auth::la_kt( $u ) ) { throw new Exception( 'Chỉ kế toán mới thao tác sổ kho' ); }
	}

	private static function so( $v ) { return VHJP_Doc::num( $v ); }

	/** Danh mục hàng gom một lượt: `code => array( name, misa, dvt )`. */
	private static function ds_hang() {
		$ra = array();
		foreach ( VHJP_Nguon::doc( 'JP_Items' ) as $h ) {
			$ra[ VHJP_Doc::str( $h['code'] ) ] = array(
				'name' => VHJP_Doc::str( $h['name'] ),
				'misa' => VHJP_Doc::str( isset( $h['misa'] ) ? $h['misa'] : '' ),
				'dvt'  => VHJP_Doc::str( isset( $h['dvt'] ) ? $h['dvt'] : '' ) );
		}
		return $ra;
	}

	private static function ten_hang( $ma ) {
		$h = VHJP_Nguon::tim_mot( 'JP_Items', 'code', VHJP_Doc::str( $ma ) );
		return $h ? VHJP_Doc::str( $h['name'] ) : VHJP_Doc::str( $ma );
	}

	/* ═══════════════════════════════════════════════════════════════════════ TỒN ═════════ */

	/**
	 * Lớp còn hàng của một mã trong một kho, XẾP CŨ TRƯỚC.
	 *
	 * ⚠️ Xếp theo `ngay` rồi mới tới `id`. Chỉ xếp theo `id` thì một phiếu nhập gõ bù cho tháng
	 *    trước (mã sinh sau, ngày trước) lại nằm cuối hàng — và FIFO ăn nhầm lớp mới, giá vốn sai.
	 */
	public static function lop_con( $kho, $ma_hang ) {
		$ds = array();
		foreach ( VHJP_Nguon::doc( 'JP_KhoLop' ) as $l ) {
			if ( VHJP_Doc::str( $l['khoId'] ) !== VHJP_Doc::str( $kho ) ) { continue; }
			if ( VHJP_Doc::str( $l['itemCode'] ) !== VHJP_Doc::str( $ma_hang ) ) { continue; }
			if ( self::so( $l['qtyRemaining'] ) <= 0 ) { continue; }
			$ds[] = $l;
		}
		usort( $ds, function ( $a, $b ) {
			$x = strcmp( VHJP_Doc::ngay( $a['ngay'] ), VHJP_Doc::ngay( $b['ngay'] ) );
			return 0 !== $x ? $x : strcmp( (string) $a['id'], (string) $b['id'] );
		} );
		return $ds;
	}

	/** Tồn của một mã trong một kho: số lượng, giá trị, số lớp. */
	public static function ton_mot( $kho, $ma_hang ) {
		$sl = 0; $tien = 0; $so_lop = 0;
		foreach ( self::lop_con( $kho, $ma_hang ) as $l ) {
			$q     = self::so( $l['qtyRemaining'] );
			$sl   += $q;
			$tien += $q * self::so( $l['unitCost'] );
			$so_lop++;
		}
		return array( 'tonQty' => $sl, 'tonTien' => $tien, 'soLop' => $so_lop,
			'giaBinhQuan' => $sl > 0 ? round( $tien / $sl, 2 ) : 0 );
	}

	/** `jpKhoTonKho` — bảng tồn theo lớp. `$kho` rỗng = mọi kho. */
	public static function ton_kho( $u, $kho = '' ) {
		self::can_kt( $u );
		$kho  = VHJP_Doc::str( $kho );
		$hang = self::ds_hang();

		/* Gom theo (kho, mã) ngay trên vòng đọc lớp — đọc lại bảng lớp cho từng mã là phép bình
		   phương trên một bảng chỉ có lớn dần. */
		$gom = array(); $tq = 0; $tt = 0;
		foreach ( VHJP_Nguon::doc( 'JP_KhoLop' ) as $l ) {
			$k = VHJP_Doc::str( $l['khoId'] );
			if ( '' !== $kho && $k !== $kho ) { continue; }
			$q = self::so( $l['qtyRemaining'] );
			if ( $q <= 0 ) { continue; }
			$m   = VHJP_Doc::str( $l['itemCode'] );
			$key = $k . '|' . $m;
			if ( ! isset( $gom[ $key ] ) ) {
				$gom[ $key ] = array( 'khoId' => $k, 'laKhoTong' => self::la_kho_tong( $k ) ? 1 : 0,
					'locationName' => self::ten_kho( $k ), 'itemCode' => $m,
					'itemName' => isset( $hang[ $m ] ) ? $hang[ $m ]['name'] : $m,
					'soLop' => 0, 'tonQty' => 0, 'tonTien' => 0 );
			}
			$tien = $q * self::so( $l['unitCost'] );
			$gom[ $key ]['soLop']++;
			$gom[ $key ]['tonQty']  += $q;
			$gom[ $key ]['tonTien'] += $tien;
			$tq += $q;
			$tt += $tien;
		}
		foreach ( $gom as $k => $r ) {
			$gom[ $k ]['giaBinhQuan'] = $r['tonQty'] > 0 ? round( $r['tonTien'] / $r['tonQty'], 2 ) : 0;
		}
		$rows = array_values( $gom );
		usort( $rows, function ( $a, $b ) {
			/* Kho tổng lên đầu — nó là kho hạch toán, người xem tìm nó trước. */
			if ( $a['laKhoTong'] !== $b['laKhoTong'] ) { return $b['laKhoTong'] - $a['laKhoTong']; }
			$x = strcmp( $a['locationName'], $b['locationName'] );
			return 0 !== $x ? $x : strcmp( $a['itemCode'], $b['itemCode'] );
		} );
		return array( 'ok' => true, 'khoId' => $kho, 'rows' => $rows,
			'tong' => array( 'tonQty' => $tq, 'tonTien' => $tt,
				'giaBinhQuan' => $tq > 0 ? round( $tt / $tq, 2 ) : 0 ) );
	}

	/* ═══════════════════════════════════════════════════════════ ĐƯỜNG HÀNG VÀO ══════════ */

	/**
	 * Ghi MỘT phiếu nhập + chi tiết + lớp tồn. Đường vào DUY NHẤT của hàng, cả năm loại.
	 *
	 * ⚠️ Lớp mang `nguon` = mã phiếu. Nhờ đó huỷ phiếu tìm lại đúng lớp của nó, và bảng N-X-T
	 *    biết lớp ấy thuộc cột nhập nào mà không phải đoán theo chuỗi.
	 *
	 * @param array $dong Mỗi phần tử: itemCode, qty, unitCost, dvt, lotNo, note.
	 * @return array array( phiếu, số dòng, tổng tiền, tổng SL )
	 */
	private static function ghi_phieu_nhap( $u, $loai, $kho, $ngay, $dong, $meta = array() ) {
		$tong = 0; $tong_sl = 0;
		foreach ( $dong as $x ) {
			$tong    += $x['qty'] * $x['unitCost'];
			$tong_sl += $x['qty'];
		}
		$phieu = VHJP_Ma::them( 'JP_KhoNhap', 'NK', array(
			'soChungTu'    => VHJP_Doc::str( isset( $meta['soChungTu'] ) ? $meta['soChungTu'] : '' ),
			'ngay'         => $ngay,
			'loaiNhap'     => $loai,
			'khoId'        => $kho,
			'locationId'   => self::la_kho_tong( $kho ) ? '' : $kho,
			'locationName' => self::ten_kho( $kho ),
			'nccMa'        => VHJP_Doc::str( isset( $meta['nccMa'] ) ? $meta['nccMa'] : '' ),
			'nccTen'       => VHJP_Doc::str( isset( $meta['nccTen'] ) ? $meta['nccTen'] : '' ),
			'soDong'       => count( $dong ),
			'tongTien'     => $tong,
			'ghiChu'       => VHJP_Doc::str( isset( $meta['ghiChu'] ) ? $meta['ghiChu'] : '' ),
			'createdBy'    => VHJP_Doc::str( isset( $u['hoTen'] ) ? $u['hoTen'] : '' ),
			'createdAt'    => VHJP_Ma::hom_nay(),
		) );
		if ( ! is_array( $phieu ) ) { throw new Exception( 'Không ghi được phiếu nhập' ); }
		$ma_phieu = VHJP_Doc::str( $phieu['id'] );

		/* Số chứng từ để trống thì lấy CHÍNH mã phiếu — nhật ký in cột "Số CT", để trống là
		   người đọc không có gì gọi tên phiếu ấy khi đi hỏi nhau. */
		if ( '' === VHJP_Doc::str( $phieu['soChungTu'] ) ) {
			VHJP_Nguon::sua( 'JP_KhoNhap', $ma_phieu, array( 'soChungTu' => $ma_phieu ) );
			$phieu['soChungTu'] = $ma_phieu;
		}

		$i = 0;
		foreach ( $dong as $x ) {
			$i++;
			VHJP_Ma::them( 'JP_KhoNhapCT', 'NKCT', array(
				'nhapId'   => $ma_phieu, 'seq' => $i,
				'itemCode' => $x['itemCode'], 'itemName' => $x['itemName'],
				'dvt'      => $x['dvt'], 'qty' => $x['qty'], 'unitCost' => $x['unitCost'],
				'amount'   => $x['qty'] * $x['unitCost'],
				'lotNo'    => $x['lotNo'], 'note' => $x['note'] ) );
			self::tao_lop( $kho, $x['itemCode'], $ngay, $x['qty'], $x['unitCost'], $ma_phieu, $x['lotNo'] );
		}
		return array( 'phieu' => $phieu, 'id' => $ma_phieu, 'soDong' => count( $dong ),
			'tongTien' => $tong, 'tongSL' => $tong_sl );
	}

	/** Đọc mảng `rows` của giao diện thành các dòng đã chuẩn hoá. */
	private static function doc_dong( $rows, $co_gia ) {
		$dong = array();
		foreach ( (array) $rows as $r ) {
			if ( ! is_array( $r ) ) { continue; }
			$ma = VHJP_Doc::str( isset( $r['itemCode'] ) ? $r['itemCode'] : '' );
			$q  = self::so( isset( $r['qty'] ) ? $r['qty'] : 0 );
			if ( '' === $ma || $q <= 0 ) { continue; }
			$dong[] = array(
				'itemCode' => $ma,
				'itemName' => self::ten_hang( $ma ),
				'dvt'      => VHJP_Doc::str( isset( $r['dvt'] ) ? $r['dvt'] : '' ),
				'qty'      => $q,
				'unitCost' => $co_gia ? self::so( isset( $r['unitCost'] ) ? $r['unitCost'] : 0 ) : 0,
				'lotNo'    => VHJP_Doc::str( isset( $r['lotNo'] ) ? $r['lotNo'] : '' ),
				'note'     => VHJP_Doc::str( isset( $r['note'] ) ? $r['note'] : '' ),
			);
		}
		return $dong;
	}

	/* ══════════════════════════════════════════════════════════════════ ĐẦU KỲ ═══════════ */

	/**
	 * `jpKhoSoDuDauKy` — khai tồn mở sổ cho một kho.
	 *
	 * 🔴 CHỈ KHAI ĐƯỢC MỘT LẦN CHO MỖI KHO. Khai lần hai là cộng thêm một đống tồn ảo vào kho
	 *    đang chạy, và không ai nhìn ra vì tồn vẫn "có vẻ hợp lý". Nên: đã có phiếu `DAU_KY`
	 *    CHƯA HUỶ cho kho ấy thì chối, kèm cờ `daCoTruoc` để giao diện in đỏ.
	 *
	 * ⚠️ Chối chứ KHÔNG ném lỗi: giao diện đọc `d.daCoTruoc` để chọn màu câu trả lời, ném lỗi là
	 *    nó rơi vào nhánh `catch` và mất luôn câu giải thích.
	 */
	public static function so_du_dau_ky( $u, $d ) {
		self::can_kt( $u );
		$d   = is_array( $d ) ? $d : array();
		$kho = VHJP_Doc::str( isset( $d['khoId'] ) ? $d['khoId'] : '' );
		if ( '' === $kho ) { throw new Exception( 'Chọn kho' ); }
		$ngay = VHJP_Doc::ngay( isset( $d['ngay'] ) ? $d['ngay'] : '' );
		if ( '' === $ngay ) { throw new Exception( 'Chọn ngày mở sổ' ); }

		foreach ( VHJP_Nguon::doc( 'JP_KhoNhap' ) as $p ) {
			if ( self::N_DAU_KY !== VHJP_Doc::str( $p['loaiNhap'] ) ) { continue; }
			if ( VHJP_Doc::str( $p['khoId'] ) !== $kho ) { continue; }
			if ( '' !== VHJP_Doc::str( $p['huyBy'] ) ) { continue; }
			return array( 'ok' => false, 'daCoTruoc' => true, 'id' => VHJP_Doc::str( $p['id'] ),
				'msg' => 'Kho ' . self::ten_kho( $kho ) . ' đã khai tồn đầu kỳ ở phiếu '
					. VHJP_Doc::str( $p['soChungTu'] ) . ' rồi. Khai lần nữa là cộng thêm một '
					. 'đống tồn ảo vào kho đang chạy. Sai số thì huỷ phiếu ấy rồi khai lại, '
					. 'lệch thực tế thì đi đường Kiểm kê.' );
		}

		$dong = self::doc_dong( isset( $d['rows'] ) ? $d['rows'] : array(), true );
		if ( ! $dong ) { throw new Exception( 'Không có dòng nào hợp lệ' ); }

		$kq = self::ghi_phieu_nhap( $u, self::N_DAU_KY, $kho, $ngay, $dong,
			array( 'ghiChu' => VHJP_Doc::str( isset( $d['ghiChu'] ) ? $d['ghiChu'] : '' ) ) );

		VHJP_NhatKy::ghi( $u, 'KHO_DAU_KY', '', $kq['id'], $kq['soDong'] . ' mã · ' . $kq['tongTien'] );
		return array( 'ok' => true, 'daCoTruoc' => false, 'id' => $kq['id'],
			'soChungTu' => VHJP_Doc::str( $kq['phieu']['soChungTu'] ),
			'soDong' => $kq['soDong'], 'soLuong' => $kq['tongSL'], 'tongTien' => $kq['tongTien'],
			'msg' => 'Đã khai tồn đầu kỳ cho ' . self::ten_kho( $kho ) . ': ' . $kq['soDong']
				. ' mã · ' . $kq['tongSL'] . ' cái · ' . number_format( $kq['tongTien'], 0, ',', '.' ) . 'đ.' );
	}

	/** `jpKhoLichSuDauKy` — mọi phiếu `DAU_KY`, kể cả đã huỷ (giao diện tự lọc để cộng). */
	public static function lich_su_dau_ky( $u ) {
		self::can_kt( $u );
		return self::ds_phieu_nhap( array( self::N_DAU_KY ), 0 );
	}

	/* ════════════════════════════════════════════════════════════════════ NHẬP ═══════════ */

	/**
	 * `jpKhoNhap` — mua hàng từ nhà cung cấp, vào KHO TỔNG.
	 *
	 * ⚠️ Nhập mua luôn vào kho tổng. Hàng về cơ sở phải đi qua `XUAT_CS` để có vết chuyển —
	 *    nhập thẳng vào kho cơ sở là mất đường đối chiếu với hoá đơn nhà cung cấp, và cột
	 *    "Mua từ NCC" của một cơ sở thì không đối chiếu được với sổ 331 nào cả.
	 */
	public static function nhap( $u, $d ) {
		self::can_kt( $u );
		$d    = is_array( $d ) ? $d : array();
		$ngay = VHJP_Doc::ngay( isset( $d['ngay'] ) ? $d['ngay'] : '' );
		if ( '' === $ngay ) { throw new Exception( 'Chọn ngày nhập' ); }

		$dong = self::doc_dong( isset( $d['rows'] ) ? $d['rows'] : array(), true );
		if ( ! $dong ) { throw new Exception( 'Phiếu nhập không có dòng nào hợp lệ' ); }

		$kq = self::ghi_phieu_nhap( $u, self::N_MUA, self::KHO_TONG, $ngay, $dong, array(
			'soChungTu' => isset( $d['soChungTu'] ) ? $d['soChungTu'] : '',
			'nccMa'     => isset( $d['nccMa'] ) ? $d['nccMa'] : '',
			'nccTen'    => isset( $d['nccTen'] ) ? $d['nccTen'] : '',
			'ghiChu'    => isset( $d['ghiChu'] ) ? $d['ghiChu'] : '' ) );

		VHJP_NhatKy::ghi( $u, 'KHO_NHAP', '', $kq['id'], $kq['soDong'] . ' dòng · ' . $kq['tongTien'] );
		return array( 'ok' => true, 'id' => $kq['id'],
			'soChungTu' => VHJP_Doc::str( $kq['phieu']['soChungTu'] ),
			'soDong' => $kq['soDong'], 'tongSL' => $kq['tongSL'], 'tongTien' => $kq['tongTien'],
			'msg' => 'Đã nhập ' . $kq['soDong'] . ' dòng vào KHO TỔNG ('
				. VHJP_Doc::str( $kq['phieu']['soChungTu'] ) . ').' );
	}

	/**
	 * `jpKhoHuyNhap` — huỷ một phiếu nhập (mua, đầu kỳ, kiểm kê thừa, nhận ĐC đều được).
	 *
	 * 🔴 CHỈ HUỶ ĐƯỢC KHI LỚP CHƯA BỊ ĂN. Lớp đã xuất một phần nghĩa là giá vốn của nó đã đi vào
	 *    sổ 632 và vào báo cáo đã ký; huỷ phiếu nhập lúc ấy là rút nền móng khỏi một con số đã
	 *    chốt. Chối, và nói rõ mã nào đã bị ăn để kế toán đi đường kiểm kê.
	 */
	public static function huy_nhap( $u, $ma_phieu, $ly_do ) {
		self::can_kt( $u );
		$ma_phieu = VHJP_Doc::str( $ma_phieu );
		$p = VHJP_Nguon::tim_mot( 'JP_KhoNhap', 'id', $ma_phieu );
		if ( ! $p ) { throw new Exception( 'Không tìm thấy phiếu nhập' ); }
		if ( '' !== VHJP_Doc::str( $p['huyBy'] ) ) { throw new Exception( 'Phiếu này đã huỷ rồi' ); }
		$ly_do = VHJP_Doc::str( $ly_do );
		if ( '' === $ly_do ) { throw new Exception( 'Phải ghi lý do huỷ' ); }

		$lop = VHJP_Nguon::tim( 'JP_KhoLop', 'nguon', $ma_phieu );
		$da_an = array();
		foreach ( $lop as $l ) {
			if ( self::so( $l['qtyRemaining'] ) < self::so( $l['qtyInit'] ) ) {
				$da_an[] = VHJP_Doc::str( $l['itemCode'] );
			}
		}
		if ( $da_an ) {
			throw new Exception( 'Không huỷ được: hàng của phiếu này đã xuất một phần ('
				. implode( ', ', array_unique( $da_an ) ) . '). Giá vốn ấy đã vào sổ 632 rồi — '
				. 'muốn chỉnh thì đi đường Kiểm kê, đừng rút nền móng của một con số đã chốt.' );
		}

		foreach ( $lop as $l ) { VHJP_Nguon::xoa( 'JP_KhoLop', $l['id'] ); }
		VHJP_Nguon::sua( 'JP_KhoNhap', $ma_phieu, array(
			'huyBy' => VHJP_Doc::str( isset( $u['hoTen'] ) ? $u['hoTen'] : '' ),
			'huyAt' => VHJP_Ma::hom_nay(), 'huyReason' => $ly_do ) );
		VHJP_NhatKy::ghi( $u, 'KHO_HUY_NHAP', '', $ma_phieu, $ly_do );
		return array( 'ok' => true, 'soLop' => count( $lop ),
			'msg' => 'Đã huỷ phiếu ' . VHJP_Doc::str( $p['soChungTu'] ) . ' và gỡ '
				. count( $lop ) . ' lớp tồn.' );
	}

	/** `jpKhoLichSuNhap` — chỉ phiếu MUA; bốn đường vào kia có màn riêng của chúng. */
	public static function lich_su_nhap( $u, $gioi_han = 50 ) {
		self::can_kt( $u );
		return self::ds_phieu_nhap( array( self::N_MUA ), max( 1, (int) $gioi_han ) );
	}

	/**
	 * Danh sách phiếu nhập kèm `dong[]` và cờ `daHuy`, mới trước.
	 *
	 * ⚠️ Đọc chi tiết bằng MỘT vòng gom theo `nhapId`, không gọi `tim()` trong vòng lặp phiếu:
	 *    50 phiếu là 50 lượt quét cả bảng chi tiết, và bảng ấy chỉ có lớn dần.
	 */
	private static function ds_phieu_nhap( $loai, $gioi_han ) {
		$ds = array();
		foreach ( VHJP_Nguon::doc( 'JP_KhoNhap' ) as $p ) {
			if ( $loai && ! in_array( VHJP_Doc::str( $p['loaiNhap'] ), $loai, true ) ) { continue; }
			$ds[] = $p;
		}
		usort( $ds, function ( $a, $b ) { return strcmp( (string) $b['id'], (string) $a['id'] ); } );
		if ( $gioi_han > 0 ) { $ds = array_slice( $ds, 0, $gioi_han ); }

		$can = array();
		foreach ( $ds as $p ) { $can[ VHJP_Doc::str( $p['id'] ) ] = array(); }
		foreach ( VHJP_Nguon::doc( 'JP_KhoNhapCT' ) as $ct ) {
			$k = VHJP_Doc::str( $ct['nhapId'] );
			if ( ! isset( $can[ $k ] ) ) { continue; }
			$can[ $k ][] = array( 'itemCode' => VHJP_Doc::str( $ct['itemCode'] ),
				'itemName' => VHJP_Doc::str( $ct['itemName'] ), 'dvt' => VHJP_Doc::str( $ct['dvt'] ),
				'qty' => self::so( $ct['qty'] ), 'unitCost' => self::so( $ct['unitCost'] ),
				'amount' => self::so( $ct['amount'] ), 'seq' => self::so( $ct['seq'] ) );
		}
		$ra = array();
		foreach ( $ds as $p ) {
			$k = VHJP_Doc::str( $p['id'] );
			$dong = $can[ $k ];
			usort( $dong, function ( $a, $b ) { return $a['seq'] - $b['seq']; } );
			$p['dong']  = $dong;
			$p['daHuy'] = '' !== VHJP_Doc::str( $p['huyBy'] );
			$ra[] = $p;
		}
		return $ra;
	}

	/* ════════════════════════════════════════════════════════════════════ XUẤT ═══════════ */

	/**
	 * Ăn FIFO cho MỘT mã, trả về các miếng đã ăn.
	 *
	 * @return array array( 'mieng' => [ [qty, unitCost, layerId] ], 'thieu' => số lượng không đủ )
	 */
	public static function an_fifo( $kho, $ma_hang, $can ) {
		$can   = self::so( $can );
		$mieng = array();
		foreach ( self::lop_con( $kho, $ma_hang ) as $l ) {
			if ( $can <= 0 ) { break; }
			$co  = self::so( $l['qtyRemaining'] );
			$lay = min( $co, $can );
			if ( $lay <= 0 ) { continue; }
			VHJP_Nguon::sua( 'JP_KhoLop', $l['id'], array( 'qtyRemaining' => $co - $lay ) );
			$mieng[] = array( 'qty' => $lay, 'unitCost' => self::so( $l['unitCost'] ),
				'layerId' => VHJP_Doc::str( $l['id'] ) );
			$can -= $lay;
		}
		return array( 'mieng' => $mieng, 'thieu' => $can > 0 ? $can : 0 );
	}

	/**
	 * `jpKhoXuat` — một phiếu xuất.
	 *
	 * 🔴 KHOÁ TRƯỚC KHI ĂN LỚP. Hai lượt xuất chạy song song cùng đọc `qtyRemaining` rồi cùng trừ
	 *    là kho âm mà không câu nào báo. Khoá theo TỪNG KHO — hai kho khác nhau vẫn chạy song song
	 *    được, khoá chung một cái là cả hệ xếp hàng sau một phiếu.
	 *
	 * 🔴 `XUAT_CS` VÀ `TRA_KHO` LÀM ĐỦ HAI VẾ: xuất ở kho nguồn, rồi ghi một PHIẾU NHẬP ở kho
	 *    đích đúng giá vốn vừa ăn. Làm một vế là hàng bốc hơi giữa đường.
	 *
	 * ⚠️ `TRA_KHO` đi NGƯỢC: kho nguồn là cơ sở, kho đích là kho tổng. Lấy `locationId` làm kho
	 *    đích cho cả hai loại là hàng trả về lại bay xuống chính cơ sở vừa trả.
	 *
	 * ⚠️ KHÔNG ghi `JP_KhoDieuChuyenCT`. `JP_KhoXuat` đã giữ đúng một dòng cho mỗi miếng FIFO,
	 *    kèm `tkNo`/`tkCo` — hai bảng cùng giữ một bộ miếng là hai bảng sẽ lệch nhau, và lúc ấy
	 *    không ai biết bảng nào đúng. Giao diện đọc `dong[]` dựng từ `JP_KhoXuat`.
	 */
	public static function xuat( $u, $d ) {
		self::can_kt( $u );
		$d    = is_array( $d ) ? $d : array();
		$ngay = VHJP_Doc::ngay( isset( $d['ngay'] ) ? $d['ngay'] : '' );
		if ( '' === $ngay ) { throw new Exception( 'Chọn ngày xuất' ); }

		$loai  = VHJP_Doc::str( isset( $d['loai'] ) ? $d['loai'] : '' );
		$bang  = self::bang_loai_xuat();
		if ( ! isset( $bang[ $loai ] ) ) { throw new Exception( 'Loại xuất không hợp lệ: ' . $loai ); }
		$dinh  = $bang[ $loai ];
		$coso  = VHJP_Doc::str( isset( $d['locationId'] ) ? $d['locationId'] : '' );
		if ( $dinh['canCoSo'] && '' === $coso ) {
			throw new Exception( $dinh['ten'] . ' thì phải chọn cơ sở' );
		}

		if ( $dinh['nguoc'] ) {
			$kho_nguon = $coso;
			$kho_dich  = self::KHO_TONG;
		} else {
			$kho_nguon = VHJP_Doc::str( isset( $d['khoId'] ) ? $d['khoId'] : '' );
			if ( '' === $kho_nguon ) { $kho_nguon = self::KHO_TONG; }
			$kho_dich  = self::XUAT_CS === $loai ? $coso : '';
		}
		if ( $kho_dich === $kho_nguon && '' !== $kho_dich ) {
			throw new Exception( 'Kho nguồn và kho nhận là một — không chuyển vào chính nó được' );
		}

		$dong = self::doc_dong( isset( $d['rows'] ) ? $d['rows'] : array(), false );
		if ( ! $dong ) { throw new Exception( 'Phiếu xuất không có dòng nào hợp lệ' ); }

		if ( ! VHJP_Nguon::lay_khoa( 'kho_' . $kho_nguon, 10 ) ) {
			throw new Exception( 'Kho đang có người thao tác, thử lại sau vài giây' );
		}
		$mieng_ds = array(); $thieu_lop = array(); $tong = 0; $tong_sl = 0;
		try {
			foreach ( $dong as $x ) {
				$an = self::an_fifo( $kho_nguon, $x['itemCode'], $x['qty'] );

				/* Thiếu lớp thì VẪN ghi một dòng xuất cho phần thiếu, đơn giá 0 và bật cờ — im
				   lặng bỏ qua là số lượng xuất trên sổ ít hơn thực tế, và không ai thấy. */
				if ( $an['thieu'] > 0 ) {
					$an['mieng'][] = array( 'qty' => $an['thieu'], 'unitCost' => 0,
						'layerId' => '', 'thieu' => 1 );
					$thieu_lop[] = array( 'itemCode' => $x['itemCode'], 'qty' => $an['thieu'] );
				}
				foreach ( $an['mieng'] as $m ) {
					$m['itemCode'] = $x['itemCode'];
					$m['itemName'] = $x['itemName'];
					$m['dvt']      = $x['dvt'];
					$mieng_ds[]    = $m;
					$tong    += $m['qty'] * $m['unitCost'];
					$tong_sl += $m['qty'];
				}
			}
		} finally {
			VHJP_Nguon::tra_khoa( 'kho_' . $kho_nguon );
		}

		$phieu = VHJP_Ma::them( 'JP_KhoDieuChuyen', 'XP', array(
			'soChungTu' => VHJP_Doc::str( isset( $d['soChungTu'] ) ? $d['soChungTu'] : '' ),
			'ngay' => $ngay, 'loai' => $loai,
			'khoTu' => $kho_nguon, 'khoDen' => $kho_dich,
			'locationName' => self::ten_kho( '' !== $kho_dich ? $kho_dich : $kho_nguon ),
			'soDong' => count( $dong ), 'tongSL' => $tong_sl, 'tongTien' => $tong,
			'ghiChu' => VHJP_Doc::str( isset( $d['ghiChu'] ) ? $d['ghiChu'] : '' ),
			'createdBy' => VHJP_Doc::str( isset( $u['hoTen'] ) ? $u['hoTen'] : '' ),
			'createdAt' => VHJP_Ma::hom_nay(),
		) );
		if ( ! is_array( $phieu ) ) { throw new Exception( 'Không ghi được phiếu xuất' ); }
		$ma_phieu = VHJP_Doc::str( $phieu['id'] );
		$so_ct    = VHJP_Doc::str( $phieu['soChungTu'] );
		if ( '' === $so_ct ) {
			$so_ct = $ma_phieu;
			VHJP_Nguon::sua( 'JP_KhoDieuChuyen', $ma_phieu, array( 'soChungTu' => $so_ct ) );
		}

		/* Tài khoản: loại quyết định. Điều chuyển nội bộ (`tk` rỗng) vẫn ghi `156/156` — hàng
		   đổi kho chứ không rời tài sản, và để trống là dòng ấy không lên được sổ nào cả. */
		$tk_no = '' !== $dinh['tk'] ? $dinh['tk'] : '156';
		foreach ( $mieng_ds as $m ) {
			self::ghi_dong_xuat( $u, array(
				'soChungTu' => $so_ct, 'ngay' => $ngay, 'loai' => $loai,
				'reportId' => isset( $d['reportId'] ) ? $d['reportId'] : '',
				'dcId' => $ma_phieu, 'khoId' => $kho_nguon,
				'locationId' => $kho_dich, 'locationName' => self::ten_kho( $kho_dich ),
				'itemCode' => $m['itemCode'], 'itemName' => $m['itemName'],
				'qty' => $m['qty'], 'unitCost' => $m['unitCost'],
				'tkNo' => $tk_no, 'tkCo' => '156',
				'layerId' => $m['layerId'], 'thieuLop' => empty( $m['thieu'] ) ? 0 : 1 ) );
		}

		/* VẾ HAI: hàng phải xuất hiện ở kho đích, ĐÚNG giá vốn vừa ăn, dưới một phiếu nhập thật. */
		if ( '' !== $kho_dich ) {
			$vao = array();
			foreach ( $mieng_ds as $m ) {
				$vao[] = array( 'itemCode' => $m['itemCode'], 'itemName' => $m['itemName'],
					'dvt' => $m['dvt'], 'qty' => $m['qty'], 'unitCost' => $m['unitCost'],
					'lotNo' => $m['layerId'], 'note' => 'Từ ' . self::ten_kho( $kho_nguon ) );
			}
			self::ghi_phieu_nhap( $u, $dinh['nguoc'] ? self::N_TRA_KHO : self::N_DC,
				$kho_dich, $ngay, $vao,
				array( 'soChungTu' => $so_ct, 'ghiChu' => $dinh['ten'] . ' — phiếu ' . $so_ct ) );
		}

		VHJP_NhatKy::ghi( $u, 'KHO_XUAT', VHJP_Doc::str( isset( $d['reportId'] ) ? $d['reportId'] : '' ),
			$ma_phieu, $loai . ' · ' . count( $mieng_ds ) . ' dòng · ' . $tong );

		$msg = 'Đã xuất ' . count( $dong ) . ' mã · ' . $tong_sl . ' cái · giá vốn '
			. number_format( $tong, 0, ',', '.' ) . 'đ (' . $so_ct . ')';
		if ( $thieu_lop ) {
			$msg .= ' — ⚠️ ' . count( $thieu_lop ) . ' mã THIẾU LỚP TỒN, phần thiếu ghi giá vốn 0. '
				. 'Tìm phiếu nhập còn thiếu rồi bấm Xuất lại.';
		}
		return array( 'ok' => true, 'id' => $ma_phieu, 'soChungTu' => $so_ct,
			'soDong' => count( $mieng_ds ), 'tongTien' => $tong, 'tongSL' => $tong_sl,
			'thieuLop' => $thieu_lop, 'msg' => $msg );
	}

	/**
	 * `jpKhoXuatLai` — gỡ toàn bộ dòng xuất của một báo cáo để kế toán xuất lại.
	 *
	 * ⚠️ Gỡ thì phải TRẢ LẠI ĐÚNG LỚP ĐÃ ĂN, không phải tạo lớp mới: lớp mới nhảy xuống cuối hàng
	 *    FIFO, và lượt xuất sau ăn nhầm thứ tự — giá vốn đổi mà không ai đụng vào phiếu nào cả.
	 */
	public static function xuat_lai( $u, $ma_bc ) {
		self::can_kt( $u );
		$ma_bc = VHJP_Doc::str( $ma_bc );
		if ( '' === $ma_bc ) { throw new Exception( 'Thiếu mã báo cáo' ); }

		$cu = VHJP_Nguon::tim( 'JP_KhoXuat', 'reportId', $ma_bc );
		$n  = 0;
		foreach ( $cu as $x ) {
			$lop = VHJP_Doc::str( $x['layerId'] );
			if ( '' !== $lop ) {
				$l = VHJP_Nguon::tim_mot( 'JP_KhoLop', 'id', $lop );
				if ( $l ) {
					VHJP_Nguon::sua( 'JP_KhoLop', $lop, array(
						'qtyRemaining' => self::so( $l['qtyRemaining'] ) + self::so( $x['qty'] ) ) );
				}
			}
			VHJP_Nguon::xoa( 'JP_KhoXuat', $x['id'] );
			$n++;
		}
		VHJP_NhatKy::ghi( $u, 'KHO_XUAT_LAI', $ma_bc, '', $n . ' dòng gỡ' );
		return array( 'ok' => true, 'daGo' => $n,
			'msg' => 'Đã gỡ ' . $n . ' dòng xuất của ' . $ma_bc . ' và trả lại lớp tồn.' );
	}

	/** `jpKhoLichSuXuat` — PHIẾU xuất kèm `dong[]`, mới trước. `$loai` rỗng = mọi loại. */
	public static function lich_su_xuat( $u, $gioi_han = 50, $loai = '' ) {
		self::can_kt( $u );
		$loai = VHJP_Doc::str( $loai );
		$bang = self::bang_loai_xuat();

		$ds = array();
		foreach ( VHJP_Nguon::doc( 'JP_KhoDieuChuyen' ) as $p ) {
			if ( '' !== $loai && VHJP_Doc::str( $p['loai'] ) !== $loai ) { continue; }
			$ds[] = $p;
		}
		usort( $ds, function ( $a, $b ) { return strcmp( (string) $b['id'], (string) $a['id'] ); } );
		$ds = array_slice( $ds, 0, max( 1, (int) $gioi_han ) );

		$can = array();
		foreach ( $ds as $p ) { $can[ VHJP_Doc::str( $p['id'] ) ] = array(); }
		foreach ( VHJP_Nguon::doc( 'JP_KhoXuat' ) as $x ) {
			$k = VHJP_Doc::str( $x['dcId'] );
			if ( ! isset( $can[ $k ] ) ) { continue; }
			$can[ $k ][] = array( 'itemCode' => VHJP_Doc::str( $x['itemCode'] ),
				'itemName' => VHJP_Doc::str( $x['itemName'] ),
				'qty' => self::so( $x['qty'] ), 'unitCost' => self::so( $x['unitCost'] ),
				'amount' => self::so( $x['amount'] ),
				'thieuLop' => self::so( $x['thieuLop'] ) ? 1 : 0 );
		}
		$ra = array();
		foreach ( $ds as $p ) {
			$ma = VHJP_Doc::str( $p['loai'] );
			$p['dong']    = $can[ VHJP_Doc::str( $p['id'] ) ];
			$p['tenLoai'] = isset( $bang[ $ma ] ) ? $bang[ $ma ]['ten'] : $ma;
			$p['daHuy']   = '' !== VHJP_Doc::str( $p['huyBy'] );
			$ra[] = $p;
		}
		return $ra;
	}

	/* ══════════════════════════════════════════════════════════════ CHUYỂN ĐỘNG ══════════ */

	/**
	 * Mọi chuyển động kho, gom theo `kho|mã`. Nền chung của thẻ kho và bảng N-X-T.
	 *
	 * ⚠️ MỘT VÒNG ĐỌC CHO CẢ HAI MÀN. Hai màn tự đi tìm chuyển động theo hai lối riêng là chỗ hai
	 *    con số cùng tên trên cùng một web nói hai chuyện khác nhau — đúng cái bẫy màn đối soát đã
	 *    mắc một lần.
	 *
	 * Mỗi chuyển động: ngay · soChungTu · dienGiai · nhap · xuat · unitCost · ghiChu · o (cột
	 * N-X-T) · tien · daHuy.
	 */
	private static function chuyen_dong( $loc_kho = '' ) {
		$loc_kho = VHJP_Doc::str( $loc_kho );
		$bang    = self::bang_loai_xuat();

		$phieu_n = array();
		foreach ( VHJP_Nguon::doc( 'JP_KhoNhap' ) as $p ) {
			$phieu_n[ VHJP_Doc::str( $p['id'] ) ] = $p;
		}
		$ten_nhap = array(
			self::N_MUA => 'Mua từ NCC', self::N_DC => 'Nhận điều chuyển',
			self::N_DAU_KY => 'Tồn đầu kỳ', self::N_KIEM_KE => 'Kiểm kê thừa',
			self::N_TRA_KHO => 'Cơ sở trả về' );
		$o_nhap = array(
			self::N_MUA => 'nhapMua', self::N_DC => 'nhapDC', self::N_DAU_KY => 'nhapDauKy',
			self::N_KIEM_KE => 'nhapKiemKe', self::N_TRA_KHO => 'traVe' );

		$gom = array();
		foreach ( VHJP_Nguon::doc( 'JP_KhoLop' ) as $l ) {
			$k = VHJP_Doc::str( $l['khoId'] );
			if ( '' !== $loc_kho && $k !== $loc_kho ) { continue; }
			$m   = VHJP_Doc::str( $l['itemCode'] );
			$key = $k . '|' . $m;
			$p   = isset( $phieu_n[ VHJP_Doc::str( $l['nguon'] ) ] )
				? $phieu_n[ VHJP_Doc::str( $l['nguon'] ) ] : null;
			$lo  = $p ? VHJP_Doc::str( $p['loaiNhap'] ) : self::N_MUA;
			$q   = self::so( $l['qtyInit'] );
			$gia = self::so( $l['unitCost'] );
			if ( ! isset( $gom[ $key ] ) ) { $gom[ $key ] = array( 'khoId' => $k, 'itemCode' => $m,
				'dong' => array(), 'conLai' => 0 ); }
			$gom[ $key ]['conLai'] += self::so( $l['qtyRemaining'] );
			$gom[ $key ]['dong'][] = array(
				'ngay' => VHJP_Doc::ngay( $l['ngay'] ),
				'soChungTu' => $p ? VHJP_Doc::str( $p['soChungTu'] ) : VHJP_Doc::str( $l['nguon'] ),
				'dienGiai' => isset( $ten_nhap[ $lo ] ) ? $ten_nhap[ $lo ] : $lo,
				'nhap' => $q, 'xuat' => 0, 'unitCost' => $gia, 'tien' => $q * $gia,
				'ghiChu' => VHJP_Doc::str( $l['lotNo'] ),
				'o' => isset( $o_nhap[ $lo ] ) ? $o_nhap[ $lo ] : 'nhapMua',
				'dau' => 1, 'id' => VHJP_Doc::str( $l['id'] ) );
		}
		foreach ( VHJP_Nguon::doc( 'JP_KhoXuat' ) as $x ) {
			$k = VHJP_Doc::str( $x['khoId'] );
			if ( '' !== $loc_kho && $k !== $loc_kho ) { continue; }
			$m   = VHJP_Doc::str( $x['itemCode'] );
			$key = $k . '|' . $m;
			$ma  = VHJP_Doc::str( $x['loai'] );
			/* Loại lạ (dữ liệu đời cũ, hoặc một loại thêm sau mà quên khai) rơi vào `xuatBan`:
			   nó nằm TRONG `tongXuat`, nên hàng không biến mất khỏi đẳng thức. Đẩy nó ra ngoài
			   mới là chỗ tồn cuối tự lệch mà bảng vẫn in "cân". */
			$o = isset( $bang[ $ma ] ) ? $bang[ $ma ]['o'] : 'xuatBan';
			$q = self::so( $x['qty'] );
			if ( ! isset( $gom[ $key ] ) ) { $gom[ $key ] = array( 'khoId' => $k, 'itemCode' => $m,
				'dong' => array(), 'conLai' => 0 ); }
			$gom[ $key ]['dong'][] = array(
				'ngay' => VHJP_Doc::ngay( $x['ngay'] ),
				'soChungTu' => VHJP_Doc::str( $x['soChungTu'] ),
				'dienGiai' => isset( $bang[ $ma ] ) ? $bang[ $ma ]['ten'] : $ma,
				'nhap' => 0, 'xuat' => $q, 'unitCost' => self::so( $x['unitCost'] ),
				'tien' => self::so( $x['amount'] ),
				'ghiChu' => self::so( $x['thieuLop'] ) ? '⚠ thiếu lớp tồn' : '',
				'o' => $o, 'dau' => 0, 'id' => VHJP_Doc::str( $x['id'] ) );
		}
		foreach ( $gom as $k => $g ) {
			usort( $gom[ $k ]['dong'], function ( $a, $b ) {
				$x = strcmp( $a['ngay'], $b['ngay'] );
				if ( 0 !== $x ) { return $x; }
				/* Cùng ngày thì NHẬP trước XUẤT — xuất trước nhập là cột "Còn lại" thò xuống âm
				   rồi bật lên, và người đọc tưởng kho đã âm thật. */
				if ( $a['dau'] !== $b['dau'] ) { return $b['dau'] - $a['dau']; }
				return strcmp( $a['id'], $b['id'] );
			} );
		}
		return $gom;
	}

	/** Bộ đếm rỗng của một dòng N-X-T. */
	private static function o_trong() {
		return array( 'nhapMua' => 0, 'nhapDC' => 0, 'nhapDauKy' => 0, 'nhapKiemKe' => 0,
			'nhap' => 0, 'traVe' => 0, 'xuatCoSo' => 0, 'xuatBan' => 0, 'xuatXeMau' => 0,
			'xuatTang' => 0, 'xuatTinh' => 0, 'xuatKiemKe' => 0, 'tongXuat' => 0,
			'tonDau' => 0, 'tonCuoi' => 0, 'tonDauCT' => 0, 'tonCuoiCT' => 0,
			'gtNhap' => 0, 'gtTraVe' => 0, 'gtTongXuat' => 0, 'gtTonDau' => 0, 'gtTonCuoi' => 0 );
	}

	private static function cong_o( &$t, $r ) {
		foreach ( self::o_trong() as $k => $_ ) { $t[ $k ] += isset( $r[ $k ] ) ? $r[ $k ] : 0; }
	}

	/* ══════════════════════════════════════════════════════════════ NHẬP-XUẤT-TỒN ════════ */

	/**
	 * `jpKhoNhapXuatTon` — bảng 18 cột của một tháng, tách theo kho.
	 *
	 * 🔴 `traVe` CÓ DẤU: cộng ở kho nhận, TRỪ ở cơ sở trả. Đẳng thức mà giao diện in ra là
	 *    `tồn đầu + nhập + trả về − tổng xuất = tồn cuối`; ở phía cơ sở hàng đi RA, nên trả về
	 *    phải mang dấu âm thì đẳng thức mới đúng — và cộng cả hệ lại thì hai vế triệt tiêu, đúng
	 *    bản chất "hàng chỉ đổi kho". Nhét nó vào một cột xuất thì nhãn nói sai việc, mà bỏ ra
	 *    ngoài thì dòng cơ sở không cộng ra được tồn cuối.
	 */
	public static function nhap_xuat_ton( $u, $thang, $nam, $kho = '' ) {
		self::can_kt( $u );
		$thang = (int) $thang; $nam = (int) $nam;
		if ( $thang < 1 || $thang > 12 || $nam < 2000 ) { throw new Exception( 'Tháng/năm không hợp lệ' ); }
		$tu  = sprintf( '%04d-%02d-01', $nam, $thang );
		$den = gmdate( 'Y-m-t', strtotime( $tu . ' 00:00:00 UTC' ) );
		$kho = VHJP_Doc::str( $kho );

		$hang = self::ds_hang();
		$rows = array();
		foreach ( self::chuyen_dong( $kho ) as $key => $g ) {
			$r = self::o_trong();
			/* Suy NGƯỢC: tồn thật lúc này, trừ ngược mọi chuyển động SAU kỳ. */
			$sau = 0; $sau_tien = 0;
			$truoc = 0; $truoc_tien = 0;
			foreach ( $g['dong'] as $x ) {
				$net = $x['nhap'] - $x['xuat'];
				$tien = $x['dau'] ? $x['tien'] : -$x['tien'];
				if ( $x['ngay'] > $den ) { $sau += $net; $sau_tien += $tien; continue; }
				if ( $x['ngay'] < $tu )  { $truoc += $net; $truoc_tien += $tien; continue; }

				if ( 'traVe' === $x['o'] ) {
					/* Trả về: cộng ở kho nhận (dòng nhập), trừ ở cơ sở trả (dòng xuất). */
					$r['traVe']   += $net;
					$r['gtTraVe'] += $tien;
				} elseif ( $x['dau'] ) {
					$r[ $x['o'] ]  += $x['nhap'];
					$r['nhap']     += $x['nhap'];
					$r['gtNhap']   += $x['tien'];
				} else {
					$r[ $x['o'] ]     += $x['xuat'];
					$r['tongXuat']    += $x['xuat'];
					$r['gtTongXuat']  += $x['tien'];
				}
			}
			$r['tonCuoi']   = $g['conLai'] - $sau;
			$r['tonDau']    = $r['tonCuoi'] - $r['nhap'] - $r['traVe'] + $r['tongXuat'];
			/* Suy XUÔI: cộng dồn CHỨNG TỪ từ đầu đến trước kỳ. Đường hoàn toàn khác. */
			$r['tonDauCT']  = $truoc;
			$r['tonCuoiCT'] = $truoc + $r['nhap'] + $r['traVe'] - $r['tongXuat'];
			$r['gtTonCuoi'] = 0; /* đặt lại ngay dưới, sau khi biết giá trị tồn hiện tại */
			$r['gtTonDau']  = $truoc_tien;
			$r['gtTonCuoi'] = $truoc_tien + $r['gtNhap'] + $r['gtTraVe'] - $r['gtTongXuat'];

			$p   = explode( '|', $key );
			$k   = $p[0];
			$m   = isset( $p[1] ) ? $p[1] : '';
			$lech = $r['tonDau'] - $r['tonDauCT'];

			/* Dòng không phát sinh gì trong kỳ VÀ không còn tồn thì bỏ — in ra một bảng toàn
			   số 0 là che mất mấy dòng thật sự có việc. */
			if ( 0 === $r['nhap'] && 0 === $r['tongXuat'] && 0 === $r['traVe']
				&& 0 === $r['tonDau'] && 0 === $r['tonCuoi'] ) { continue; }

			$rows[] = array_merge( $r, array(
				'khoId' => $k, 'tenKho' => self::ten_kho( $k ),
				'laKhoTong' => self::la_kho_tong( $k ) ? 1 : 0,
				'itemCode' => $m,
				'itemName' => isset( $hang[ $m ] ) ? $hang[ $m ]['name'] : $m,
				'misa' => isset( $hang[ $m ] ) ? $hang[ $m ]['misa'] : '',
				'dvt' => isset( $hang[ $m ] ) ? $hang[ $m ]['dvt'] : '',
				'nhom' => 'JP_Hàng JP',
				'lech' => $lech, 'lechDauKy' => 0 !== $lech ? 1 : 0 ) );
		}

		usort( $rows, function ( $a, $b ) {
			if ( $a['laKhoTong'] !== $b['laKhoTong'] ) { return $b['laKhoTong'] - $a['laKhoTong']; }
			$x = strcmp( $a['tenKho'], $b['tenKho'] );
			return 0 !== $x ? $x : strcmp( $a['itemCode'], $b['itemCode'] );
		} );

		$khoi = array(); $tong = self::o_trong();
		$lech_tong = 0; $so_dong_lech = 0; $vi_du = array();
		foreach ( $rows as $r ) {
			$k = $r['khoId'];
			if ( ! isset( $khoi[ $k ] ) ) {
				$khoi[ $k ] = array( 'khoId' => $k, 'tenKho' => $r['tenKho'],
					'laKhoTong' => $r['laKhoTong'], 'rows' => array(), 'tong' => self::o_trong(),
					'lech' => 0, 'soDongLech' => 0 );
			}
			$khoi[ $k ]['rows'][] = $r;
			self::cong_o( $khoi[ $k ]['tong'], $r );
			self::cong_o( $tong, $r );
			if ( $r['lech'] ) {
				$khoi[ $k ]['lech'] += $r['lech'];
				$khoi[ $k ]['soDongLech']++;
				$lech_tong += $r['lech'];
				$so_dong_lech++;
				if ( count( $vi_du ) < 12 ) {
					$vi_du[] = array( 'kho' => $r['tenKho'], 'itemCode' => $r['itemCode'],
						'tonDau' => $r['tonDau'], 'tonDauCT' => $r['tonDauCT'], 'lech' => $r['lech'] );
				}
			}
		}

		return array( 'ok' => true, 'thang' => $thang, 'nam' => $nam, 'khoId' => $kho,
			'tuNgay' => $tu, 'denNgay' => $den, 'rows' => $rows,
			'khoi' => array_values( $khoi ), 'tong' => $tong,
			'canBang' => 0 === $so_dong_lech, 'lech' => $lech_tong,
			'soDongLech' => $so_dong_lech, 'viDuLech' => $vi_du );
	}

	/* ═══════════════════════════════════════════════════════════════════ THẺ KHO ═════════ */

	/**
	 * `jpKhoTheKho` — thẻ kho. Ô mã để TRỐNG là xem MỌI MÃ của kho, mỗi mã một khối.
	 *
	 * ⚠️ Phép kiểm là tồn đầu SUY NGƯỢC so với tồn đầu SUY XUÔI, không phải
	 *    `tonDau + nhập − xuất == tonCuoi`. Vế sau luôn đúng vì `tonCuoi` được tính TỪ `tonDau`
	 *    cộng đúng hai số ấy — in ra thì đẹp mà không kiểm được gì.
	 */
	public static function the_kho( $u, $d ) {
		self::can_kt( $u );
		$d   = is_array( $d ) ? $d : array();
		$kho = VHJP_Doc::str( isset( $d['khoId'] ) ? $d['khoId'] : '' );
		$ma  = VHJP_Doc::str( isset( $d['itemCode'] ) ? $d['itemCode'] : '' );
		$tu  = VHJP_Doc::ngay( isset( $d['tuNgay'] ) ? $d['tuNgay'] : '' );
		$den = VHJP_Doc::ngay( isset( $d['denNgay'] ) ? $d['denNgay'] : '' );
		if ( '' === $kho ) { throw new Exception( 'Chọn kho' ); }

		$hang  = self::ds_hang();
		$khoi  = array(); $bo_qua = 0;
		$tong  = array( 'tonDau' => 0, 'tonDauCT' => 0, 'tongNhap' => 0, 'tongXuat' => 0,
			'tonCuoi' => 0, 'soDong' => 0 );
		$lech_tong = 0; $so_ma_lech = 0; $ton_hien_tai = 0;

		foreach ( self::chuyen_dong( $kho ) as $key => $g ) {
			$p = explode( '|', $key );
			$m = isset( $p[1] ) ? $p[1] : '';
			if ( '' !== $ma && $m !== $ma ) { continue; }

			$trong = array(); $sau = 0; $truoc = 0;
			foreach ( $g['dong'] as $x ) {
				$net = $x['nhap'] - $x['xuat'];
				if ( '' !== $den && $x['ngay'] > $den ) { $sau += $net; continue; }
				if ( '' !== $tu && $x['ngay'] < $tu )   { $truoc += $net; continue; }
				$trong[] = $x;
			}
			$ton_cuoi_that = $g['conLai'] - $sau;
			$nhap = 0; $xuat = 0;
			foreach ( $trong as $x ) { $nhap += $x['nhap']; $xuat += $x['xuat']; }
			$ton_dau   = $ton_cuoi_that - $nhap + $xuat;
			$ton_dauct = '' === $tu ? 0 : $truoc;
			$lech      = $ton_dau - $ton_dauct;

			if ( '' === $ma && ! $trong && $g['conLai'] <= 0 ) { $bo_qua++; continue; }

			$chay = $ton_dau;
			foreach ( $trong as $i => $x ) {
				$chay += $x['nhap'] - $x['xuat'];
				$trong[ $i ]['conLai'] = $chay;
				unset( $trong[ $i ]['dau'], $trong[ $i ]['id'], $trong[ $i ]['o'], $trong[ $i ]['tien'] );
			}

			$khoi[] = array( 'itemCode' => $m,
				'itemName' => isset( $hang[ $m ] ) ? $hang[ $m ]['name'] : $m,
				'dvt' => isset( $hang[ $m ] ) ? $hang[ $m ]['dvt'] : '',
				'tonDau' => $ton_dau, 'tonDauCT' => $ton_dauct, 'lech' => $lech,
				'tongNhap' => $nhap, 'tongXuat' => $xuat, 'tonCuoi' => $chay,
				'tonHienTai' => $g['conLai'], 'rows' => $trong );

			$tong['tonDau']   += $ton_dau;
			$tong['tonDauCT'] += $ton_dauct;
			$tong['tongNhap'] += $nhap;
			$tong['tongXuat'] += $xuat;
			$tong['tonCuoi']  += $chay;
			$tong['soDong']   += count( $trong );
			$ton_hien_tai     += $g['conLai'];
			if ( $lech ) { $lech_tong += $lech; $so_ma_lech++; }
		}

		usort( $khoi, function ( $a, $b ) { return strcmp( $a['itemCode'], $b['itemCode'] ); } );

		$mot = ( '' !== $ma ) ? ( $khoi ? $khoi[0] : null ) : null;
		return array_merge(
			array(
				'ok' => true, 'khoId' => $kho, 'tenKho' => self::ten_kho( $kho ),
				'moiMa' => '' === $ma, 'soMa' => count( $khoi ), 'boQua' => $bo_qua,
				'tuNgay' => $tu, 'denNgay' => $den,
				'itemCode' => $ma,
				'itemName' => '' !== $ma && isset( $hang[ $ma ] ) ? $hang[ $ma ]['name'] : '',
				'khoi' => $khoi, 'tong' => $tong,
				'rows' => $mot ? $mot['rows'] : array(),
				'tonHienTai' => $mot ? $mot['tonHienTai'] : $ton_hien_tai,
				'canBang' => 0 === $so_ma_lech, 'lechTong' => $lech_tong, 'soMaLech' => $so_ma_lech,
			),
			$mot
				? array( 'tonDau' => $mot['tonDau'], 'tonDauCT' => $mot['tonDauCT'],
					'lech' => $mot['lech'], 'tongNhap' => $mot['tongNhap'],
					'tongXuat' => $mot['tongXuat'], 'tonCuoi' => $mot['tonCuoi'] )
				: array( 'tonDau' => $tong['tonDau'], 'tonDauCT' => $tong['tonDauCT'],
					'lech' => $lech_tong, 'tongNhap' => $tong['tongNhap'],
					'tongXuat' => $tong['tongXuat'], 'tonCuoi' => $tong['tonCuoi'] )
		);
	}

	/* ═════════════════════════════════════════════════════════════════ NHÀ CUNG CẤP ══════ */

	/**
	 * `jpKhoDanhSachNcc` — gom từ CHÍNH các phiếu MUA đã ghi.
	 *
	 * ⚠️ Không giữ một danh mục NCC riêng: giữ riêng là hai nơi lệch tên nhau, và phiếu nhập cũ
	 *    mang tên không còn trong danh mục thì biến mất khỏi ô chọn.
	 */
	public static function danh_sach_ncc( $u ) {
		self::can_kt( $u );
		$gom = array();
		foreach ( VHJP_Nguon::doc( 'JP_KhoNhap' ) as $p ) {
			if ( self::N_MUA !== VHJP_Doc::str( $p['loaiNhap'] ) ) { continue; }
			if ( '' !== VHJP_Doc::str( $p['huyBy'] ) ) { continue; }
			$ma  = VHJP_Doc::str( $p['nccMa'] );
			$ten = VHJP_Doc::str( $p['nccTen'] );
			if ( '' === $ma && '' === $ten ) { continue; }
			$k = $ma . '|' . $ten;
			/* ⚠️ Ô tên là `ma` / `ten`, KHÔNG phải `nccMa` / `nccTen`. Màn "Trả tiền NCC" dựng ô
			   chọn bằng `n.ma + '|' + n.ten`; trả về tên khác là mọi mục trong ô chọn thành
			   `undefined|undefined` — ô vẫn hiện, bấm vẫn được, và phiếu ghi ra không có NCC nào. */
			if ( ! isset( $gom[ $k ] ) ) {
				$gom[ $k ] = array( 'ma' => $ma, 'ten' => $ten, 'soPhieu' => 0,
					'tongTien' => 0, 'daTra' => 0, 'lanCuoi' => '' );
			}
			$gom[ $k ]['soPhieu']++;
			$gom[ $k ]['tongTien'] += self::so( $p['tongTien'] );
			$n = VHJP_Doc::ngay( $p['ngay'] );
			if ( $n > $gom[ $k ]['lanCuoi'] ) { $gom[ $k ]['lanCuoi'] = $n; }
		}
		foreach ( VHJP_Nguon::doc( 'JP_KhoTraNcc' ) as $t ) {
			if ( '' !== VHJP_Doc::str( $t['huyBy'] ) ) { continue; }
			$k = VHJP_Doc::str( $t['nccMa'] ) . '|' . VHJP_Doc::str( $t['nccTen'] );
			if ( ! isset( $gom[ $k ] ) ) { continue; }
			$gom[ $k ]['daTra'] += self::so( $t['soTien'] );
		}
		$ra = array_values( $gom );
		usort( $ra, function ( $a, $b ) { return strcmp( $a['ten'], $b['ten'] ); } );
		return $ra;
	}

	/* ═══════════════════════════════════════════════════════════════════ KIỂM KÊ ═════════ */

	/** `jpKhoKiemKeTon` — tồn SỔ SÁCH của một kho, để kế toán gõ số thực đếm vào cạnh. */
	public static function kiem_ke_ton( $u, $kho, $het_danh_muc = false ) {
		self::can_kt( $u );
		$kho = VHJP_Doc::str( $kho );
		if ( '' === $kho ) { throw new Exception( 'Chọn kho để kiểm kê' ); }
		$hang = self::ds_hang();

		$gom = array();
		foreach ( VHJP_Nguon::doc( 'JP_KhoLop' ) as $l ) {
			if ( VHJP_Doc::str( $l['khoId'] ) !== $kho ) { continue; }
			$q = self::so( $l['qtyRemaining'] );
			if ( $q <= 0 ) { continue; }
			$m = VHJP_Doc::str( $l['itemCode'] );
			if ( ! isset( $gom[ $m ] ) ) { $gom[ $m ] = array( 'q' => 0, 'tien' => 0 ); }
			$gom[ $m ]['q']    += $q;
			$gom[ $m ]['tien'] += $q * self::so( $l['unitCost'] );
		}
		/* "Hiện cả mã chưa có tồn" là đường DUY NHẤT phát hiện hàng thừa của một mã mà sổ nói
		   không còn cái nào — không có ô ấy thì mã đó không bao giờ xuất hiện để mà đếm. */
		if ( $het_danh_muc ) {
			foreach ( $hang as $m => $_ ) {
				if ( ! isset( $gom[ $m ] ) ) { $gom[ $m ] = array( 'q' => 0, 'tien' => 0 ); }
			}
		}

		$rows = array();
		foreach ( $gom as $m => $g ) {
			$rows[] = array( 'itemCode' => $m,
				'itemName' => isset( $hang[ $m ] ) ? $hang[ $m ]['name'] : $m,
				'misa' => isset( $hang[ $m ] ) ? $hang[ $m ]['misa'] : '',
				'dvt' => isset( $hang[ $m ] ) ? $hang[ $m ]['dvt'] : '',
				'tonSo' => $g['q'],
				'giaBinhQuan' => $g['q'] > 0 ? round( $g['tien'] / $g['q'], 2 ) : 0 );
		}
		usort( $rows, function ( $a, $b ) { return strcmp( $a['itemCode'], $b['itemCode'] ); } );
		return array( 'ok' => true, 'khoId' => $kho, 'tenKho' => self::ten_kho( $kho ),
			'laKhoTong' => self::la_kho_tong( $kho ) ? 1 : 0, 'rows' => $rows );
	}

	/**
	 * `jpKhoKiemKe` — ghi một biên bản kiểm kê.
	 *
	 * =============================================================================================
	 * THIẾU VÀ THỪA KHÔNG ĐỐI XỨNG, VÀ KHÔNG ĐƯỢC LÀM CHO CHÚNG ĐỐI XỨNG
	 * =============================================================================================
	 * · **Thiếu** — hàng đã ra khỏi kho mà không có phiếu. Ăn FIFO cho phần thiếu và ghi một dòng
	 *   xuất `KIEM_KE`, **Nợ 6321 / Có 1561**: hàng mất là chi phí, và có người phải đền.
	 * · **Thừa** — sổ nói ít hơn thực tế. Gần như luôn là một phiếu xuất gõ quá tay, chứ không
	 *   phải hàng tự sinh ra. Nên chế độ mặc định là **giảm chính phiếu xuất ấy** cho khớp thực
	 *   tế; phần không giảm được mới ghi tăng **Nợ 1561 / Có 1388**.
	 *
	 * 🔴 CHỈ GIẢM ĐƯỢC PHIẾU XUẤT TRONG KỲ ĐANG MỞ — cùng tháng với ngày kiểm kê, và không muộn
	 *    hơn ngày ấy. Thò tay sửa một phiếu của tháng trước là đổi một con số 632 đã khoá sổ, đã
	 *    xuất MISA và đã có người ký. Phần không giảm được thì NÓI RA (`khongGiamDuoc`) để kế toán
	 *    biết mà đi tìm, chứ không lặng lẽ ghi tăng cho đủ rồi để bảng trông có vẻ cân.
	 *
	 * ⚠️ Mã CHƯA ĐẾM thì không đụng tới. Coi ô trống là 0 là một lượt kiểm kê bỏ dở sẽ xoá sạch
	 *    tồn của mọi mã chưa kịp đếm — và nó ghi thẳng vào 6321.
	 */
	public static function kiem_ke( $u, $d ) {
		self::can_kt( $u );
		$d   = is_array( $d ) ? $d : array();
		$kho = VHJP_Doc::str( isset( $d['khoId'] ) ? $d['khoId'] : '' );
		if ( '' === $kho ) { throw new Exception( 'Chọn kho để kiểm kê' ); }
		$ngay = VHJP_Doc::ngay( isset( $d['ngay'] ) ? $d['ngay'] : '' );
		if ( '' === $ngay ) { throw new Exception( 'Chọn ngày kiểm kê' ); }
		$che_do = VHJP_Doc::str( isset( $d['cheDoThua'] ) ? $d['cheDoThua'] : '' );
		if ( 'GHI_TANG' !== $che_do ) { $che_do = 'GIAM_XUAT'; }

		/* Chỉ nhận dòng ĐÃ ĐẾM. Ô trống là "chưa đếm", không phải số 0 — xem khối ⚠️ ở trên. */
		$dem = array();
		foreach ( (array) ( isset( $d['rows'] ) ? $d['rows'] : array() ) as $r ) {
			if ( ! is_array( $r ) ) { continue; }
			$ma = VHJP_Doc::str( isset( $r['itemCode'] ) ? $r['itemCode'] : '' );
			$tt = isset( $r['tonThuc'] ) ? $r['tonThuc'] : '';
			if ( '' === $ma || VHJP_Doc::blank( $tt ) ) { continue; }
			$dem[] = array( 'itemCode' => $ma, 'tonThuc' => self::so( $tt ),
				'note' => VHJP_Doc::str( isset( $r['note'] ) ? $r['note'] : '' ) );
		}
		if ( ! $dem ) { throw new Exception( 'Chưa gõ số thực đếm cho mã nào' ); }

		/* Dựng phiếu TRƯỚC để mọi dòng sổ sinh ra mang đúng số chứng từ của nó; số tổng cập nhật
		   lại ở cuối. Ghi tổng trước rồi mới làm là lúc có lỗi giữa chừng, phiếu nói một đằng mà
		   sổ một nẻo. */
		$phieu = VHJP_Ma::them( 'JP_KhoKiemKe', 'KK', array(
			'ngay' => $ngay, 'khoId' => $kho, 'locationName' => self::ten_kho( $kho ),
			'cheDoThua' => 'GIAM_XUAT' === $che_do ? 1 : 0, 'soDong' => count( $dem ),
			'ghiChu' => VHJP_Doc::str( isset( $d['ghiChu'] ) ? $d['ghiChu'] : '' ),
			'createdBy' => VHJP_Doc::str( isset( $u['hoTen'] ) ? $u['hoTen'] : '' ),
			'createdAt' => VHJP_Ma::hom_nay(),
		) );
		if ( ! is_array( $phieu ) ) { throw new Exception( 'Không ghi được biên bản kiểm kê' ); }
		$ma_kk = VHJP_Doc::str( $phieu['id'] );
		VHJP_Nguon::sua( 'JP_KhoKiemKe', $ma_kk, array( 'soChungTu' => $ma_kk ) );

		$sl_thieu = 0; $tien_thieu = 0; $sl_thua = 0; $tien_thua = 0;
		$sl_giam = 0; $tien_giam = 0;
		$khong_giam = array(); $gia_0 = array(); $ct = array(); $ghi_tang = array();

		if ( ! VHJP_Nguon::lay_khoa( 'kho_' . $kho, 10 ) ) {
			throw new Exception( 'Kho đang có người thao tác, thử lại sau vài giây' );
		}
		try {
			foreach ( $dem as $x ) {
				$ma   = $x['itemCode'];
				$ton  = self::ton_mot( $kho, $ma );
				$so   = $ton['tonQty'];
				$gbq  = $ton['giaBinhQuan'] > 0 ? $ton['giaBinhQuan'] : self::gia_gan_nhat( $ma );
				$lech = $x['tonThuc'] - $so;
				$dong = array( 'kkId' => $ma_kk, 'seq' => count( $ct ) + 1, 'itemCode' => $ma,
					'itemName' => self::ten_hang( $ma ), 'tonSo' => $so, 'tonThuc' => $x['tonThuc'],
					'lech' => $lech, 'unitCost' => $gbq, 'amount' => 0,
					'cheDo' => '', 'ctGoc' => '', 'tkNo' => '', 'tkCo' => '', 'note' => $x['note'] );

				if ( 0 === $lech ) {
					$ct[] = $dong;
					continue;
				}

				if ( $lech < 0 ) {
					/* THIẾU — hàng mất. Ăn FIFO đúng phần thiếu, ghi một dòng xuất KIEM_KE. */
					$can = -$lech;
					$an  = self::an_fifo( $kho, $ma, $can );
					if ( $an['thieu'] > 0 ) {
						$an['mieng'][] = array( 'qty' => $an['thieu'], 'unitCost' => 0,
							'layerId' => '', 'thieu' => 1 );
					}
					$tien = 0;
					foreach ( $an['mieng'] as $m ) {
						$tien += $m['qty'] * $m['unitCost'];
						self::ghi_dong_xuat( $u, array(
							'soChungTu' => $ma_kk, 'ngay' => $ngay, 'loai' => 'KIEM_KE',
							'khoId' => $kho, 'locationId' => '', 'locationName' => '',
							'itemCode' => $ma, 'itemName' => $dong['itemName'],
							'qty' => $m['qty'], 'unitCost' => $m['unitCost'],
							'tkNo' => '6321', 'tkCo' => '1561',
							'layerId' => $m['layerId'], 'thieuLop' => empty( $m['thieu'] ) ? 0 : 1 ) );
					}
					$sl_thieu   += $can;
					$tien_thieu += $tien;
					$dong['amount'] = $tien;
					$dong['cheDo']  = 'THIEU';
					$dong['tkNo']   = '6321';
					$dong['tkCo']   = '1561';
					$ct[] = $dong;
					continue;
				}

				/* THỪA. */
				$con = $lech;
				$ct_goc = array();
				if ( 'GIAM_XUAT' === $che_do ) {
					$g = self::giam_xuat_trong_ky( $kho, $ma, $con, $ngay );
					$con        -= $g['daGiam'];
					$sl_giam    += $g['daGiam'];
					$tien_giam  += $g['tien'];
					$ct_goc      = $g['ctGoc'];
				}
				if ( $con > 0 ) {
					if ( $gbq <= 0 ) { $gia_0[] = $ma; }
					$ghi_tang[] = array( 'itemCode' => $ma, 'itemName' => $dong['itemName'],
						'dvt' => '', 'qty' => $con, 'unitCost' => $gbq,
						'lotNo' => $ma_kk, 'note' => 'Kiểm kê thừa' );
					$sl_thua   += $con;
					$tien_thua += $con * $gbq;
					if ( 'GIAM_XUAT' === $che_do ) {
						$khong_giam[] = array( 'itemCode' => $ma, 'qty' => $con,
							'daGiam' => $lech - $con );
					}
				}
				$dong['amount'] = $lech * $gbq;
				$dong['cheDo']  = $con >= $lech ? 'GHI_TANG' : ( $con > 0 ? 'GIAM_XUAT+GHI_TANG' : 'GIAM_XUAT' );
				$dong['ctGoc']  = implode( ', ', $ct_goc );
				$dong['tkNo']   = $con > 0 ? '1561' : '';
				$dong['tkCo']   = $con > 0 ? '1388' : '';
				$ct[] = $dong;
			}
		} finally {
			VHJP_Nguon::tra_khoa( 'kho_' . $kho );
		}

		$ma_nhap = '';
		if ( $ghi_tang ) {
			$kq = self::ghi_phieu_nhap( $u, self::N_KIEM_KE, $kho, $ngay, $ghi_tang,
				array( 'soChungTu' => $ma_kk, 'ghiChu' => 'Kiểm kê thừa — biên bản ' . $ma_kk ) );
			$ma_nhap = $kq['id'];
		}
		foreach ( $ct as $dg ) { VHJP_Ma::them( 'JP_KhoKiemKeCT', 'KKCT', $dg ); }
		VHJP_Nguon::sua( 'JP_KhoKiemKe', $ma_kk, array(
			'slThua' => $sl_thua, 'slThieu' => $sl_thieu,
			'tienThua' => $tien_thua, 'tienThieu' => $tien_thieu,
			'slGiamXuat' => $sl_giam, 'tienGiamXuat' => $tien_giam, 'nhapId' => $ma_nhap ) );

		VHJP_NhatKy::ghi( $u, 'KHO_KIEM_KE', '', $ma_kk,
			'thiếu ' . $sl_thieu . ' · thừa ' . $sl_thua . ' · giảm xuất ' . $sl_giam );

		$msg = 'Đã ghi biên bản ' . $ma_kk . ' cho ' . self::ten_kho( $kho ) . ': '
			. count( $dem ) . ' mã đã đếm · thiếu ' . $sl_thieu . ' cái ('
			. number_format( $tien_thieu, 0, ',', '.' ) . 'đ vào 6321)';
		if ( $sl_giam ) { $msg .= ' · giảm xuất ' . $sl_giam . ' cái'; }
		if ( $sl_thua ) { $msg .= ' · ghi tăng ' . $sl_thua . ' cái (1561/1388)'; }
		if ( $khong_giam ) {
			$msg .= ' — ⚠️ ' . count( $khong_giam ) . ' mã KHÔNG giảm xuất hết được (phiếu xuất '
				. 'nằm ngoài kỳ đang mở, không sửa được số đã khoá sổ); phần còn lại đã ghi tăng.';
		}
		if ( $gia_0 ) {
			$msg .= ' — ⚠️ ' . count( $gia_0 ) . ' mã ghi tăng với giá vốn 0 vì chưa từng có phiếu '
				. 'nhập nào: ' . implode( ', ', array_unique( $gia_0 ) ) . '. Bán ra sẽ ghi 632 = 0đ.';
		}
		return array( 'ok' => true, 'id' => $ma_kk, 'soChungTu' => $ma_kk, 'nhapId' => $ma_nhap,
			'soDong' => count( $dem ), 'slThieu' => $sl_thieu, 'tienThieu' => $tien_thieu,
			'slThua' => $sl_thua, 'tienThua' => $tien_thua,
			'slGiamXuat' => $sl_giam, 'tienGiamXuat' => $tien_giam,
			'khongGiamDuoc' => $khong_giam, 'giaVon0' => array_values( array_unique( $gia_0 ) ),
			'msg' => $msg );
	}

	/**
	 * Giảm bớt số đã xuất của một mã cho khớp thực đếm, và TRẢ LẠI ĐÚNG LỚP đã ăn.
	 *
	 * 🔴 CHỈ ĐỘNG VÀO PHIẾU TRONG KỲ ĐANG MỞ (cùng tháng với ngày kiểm kê, không muộn hơn ngày
	 *    ấy). Ra ngoài khoảng đó là sửa một con số đã khoá sổ.
	 *
	 * ⚠️ Gỡ từ phiếu MỚI NHẤT ngược về: phiếu mới nhất là phiếu dễ gõ nhầm nhất và ít khả năng
	 *    đã được đối chiếu nhất. Gỡ từ phiếu cũ nhất là đụng vào đúng thứ đã yên.
	 *
	 * ⚠️ Dòng `thieuLop` không có lớp để trả, nên gỡ nó KHÔNG làm tồn tăng lên — bỏ qua, không
	 *    thì hàm tưởng đã giảm đủ mà tồn vẫn y nguyên.
	 */
	private static function giam_xuat_trong_ky( $kho, $ma_hang, $can, $ngay_kk ) {
		$dau_thang = substr( $ngay_kk, 0, 8 ) . '01';
		$ds = array();
		foreach ( VHJP_Nguon::doc( 'JP_KhoXuat' ) as $x ) {
			if ( VHJP_Doc::str( $x['khoId'] ) !== $kho ) { continue; }
			if ( VHJP_Doc::str( $x['itemCode'] ) !== $ma_hang ) { continue; }
			if ( '' === VHJP_Doc::str( $x['layerId'] ) ) { continue; }
			$n = VHJP_Doc::ngay( $x['ngay'] );
			if ( $n < $dau_thang || $n > $ngay_kk ) { continue; }
			$ds[] = $x;
		}
		usort( $ds, function ( $a, $b ) {
			$x = strcmp( VHJP_Doc::ngay( $b['ngay'] ), VHJP_Doc::ngay( $a['ngay'] ) );
			return 0 !== $x ? $x : strcmp( (string) $b['id'], (string) $a['id'] );
		} );

		$da = 0; $tien = 0; $goc = array();
		foreach ( $ds as $x ) {
			if ( $can <= 0 ) { break; }
			$q   = self::so( $x['qty'] );
			$gia = self::so( $x['unitCost'] );
			$bot = min( $q, $can );
			$lop = VHJP_Nguon::tim_mot( 'JP_KhoLop', 'id', VHJP_Doc::str( $x['layerId'] ) );
			if ( ! $lop ) { continue; }
			VHJP_Nguon::sua( 'JP_KhoLop', $x['layerId'], array(
				'qtyRemaining' => self::so( $lop['qtyRemaining'] ) + $bot ) );
			if ( $bot >= $q ) {
				VHJP_Nguon::xoa( 'JP_KhoXuat', $x['id'] );
			} else {
				VHJP_Nguon::sua( 'JP_KhoXuat', $x['id'], array(
					'qty' => $q - $bot, 'amount' => ( $q - $bot ) * $gia ) );
			}
			$goc[] = VHJP_Doc::str( $x['soChungTu'] );
			$da   += $bot;
			$tien += $bot * $gia;
			$can  -= $bot;
		}
		return array( 'daGiam' => $da, 'tien' => $tien, 'ctGoc' => array_values( array_unique( $goc ) ) );
	}

	/** Đơn giá gần nhất của một mã, lấy từ phiếu nhập mới nhất còn hiệu lực. */
	private static function gia_gan_nhat( $ma_hang ) {
		$gia = 0; $moi = '';
		foreach ( VHJP_Nguon::doc( 'JP_KhoLop' ) as $l ) {
			if ( VHJP_Doc::str( $l['itemCode'] ) !== VHJP_Doc::str( $ma_hang ) ) { continue; }
			$n = VHJP_Doc::ngay( $l['ngay'] ) . '|' . VHJP_Doc::str( $l['id'] );
			if ( $n > $moi ) { $moi = $n; $gia = self::so( $l['unitCost'] ); }
		}
		return $gia;
	}

	/** `jpKhoLichSuKiemKe` */
	public static function lich_su_kiem_ke( $u, $gioi_han = 30 ) {
		self::can_kt( $u );
		$ds = VHJP_Nguon::doc( 'JP_KhoKiemKe' );
		usort( $ds, function ( $a, $b ) { return strcmp( (string) $b['id'], (string) $a['id'] ); } );
		$ds = array_slice( $ds, 0, max( 1, (int) $gioi_han ) );

		$can = array();
		foreach ( $ds as $p ) { $can[ VHJP_Doc::str( $p['id'] ) ] = array(); }
		foreach ( VHJP_Nguon::doc( 'JP_KhoKiemKeCT' ) as $c ) {
			$k = VHJP_Doc::str( $c['kkId'] );
			if ( ! isset( $can[ $k ] ) ) { continue; }
			$c['tonSo']    = self::so( $c['tonSo'] );
			$c['tonThuc']  = self::so( $c['tonThuc'] );
			$c['lech']     = self::so( $c['lech'] );
			$c['unitCost'] = self::so( $c['unitCost'] );
			$c['amount']   = self::so( $c['amount'] );
			$can[ $k ][]   = $c;
		}
		$ra = array();
		foreach ( $ds as $p ) {
			$dong = $can[ VHJP_Doc::str( $p['id'] ) ];
			usort( $dong, function ( $a, $b ) { return self::so( $a['seq'] ) - self::so( $b['seq'] ); } );
			$p['dong'] = $dong;
			$ra[] = $p;
		}
		return $ra;
	}

	/* ═════════════════════════════════════════════════════════════ TRẢ TIỀN NCC ══════════ */

	/** `jpKhoTraNcc` — một phiếu trả tiền nhà cung cấp. */
	public static function tra_ncc( $u, $d ) {
		self::can_kt( $u );
		return self::ghi_tra_ncc( $u, is_array( $d ) ? $d : array() );
	}

	/**
	 * `jpKhoTraNccLo` — ghi cả lô phiếu trả tiền từ một tệp Excel.
	 *
	 * ⚠️ MỘT LƯỢT GỌI CHO CẢ LÔ, không để giao diện gọi `jpKhoTraNcc` N lần: N lượt gọi là N vòng
	 *    mạng, và đứt giữa chừng thì không ai biết đã ghi tới dòng nào.
	 *
	 * ⚠️ Dòng hỏng KHÔNG làm đổ cả lô, nhưng phải được ĐẾM và NÓI RA kèm số dòng trong tệp —
	 *    file 40 dòng mà chỉ ghi 38 thì kế toán phải biết hai dòng nào rớt.
	 */
	public static function tra_ncc_lo( $u, $rows ) {
		self::can_kt( $u );
		$rows = is_array( $rows ) ? $rows : array();
		if ( ! $rows ) { throw new Exception( 'Không có dòng nào để ghi' ); }

		$xong = array(); $hong = array(); $tong = 0;
		foreach ( $rows as $i => $r ) {
			if ( ! is_array( $r ) ) { continue; }
			$so_dong = isset( $r['dong'] ) ? (int) $r['dong'] : ( $i + 1 );
			try {
				$kq      = self::ghi_tra_ncc( $u, $r );
				$xong[]  = array( 'dong' => $so_dong, 'id' => $kq['id'], 'soTien' => $kq['soTien'] );
				$tong   += $kq['soTien'];
			} catch ( Throwable $e ) {
				$hong[] = array( 'dong' => $so_dong, 'ly' => $e->getMessage() );
			}
		}
		VHJP_NhatKy::ghi( $u, 'KHO_TRA_NCC_LO', '', '', count( $xong ) . '/' . count( $rows )
			. ' phiếu · ' . $tong );

		$msg = 'Đã ghi ' . count( $xong ) . '/' . count( $rows ) . ' phiếu · tổng '
			. number_format( $tong, 0, ',', '.' ) . 'đ.';
		if ( $hong ) {
			$msg .= ' ⚠️ ' . count( $hong ) . ' dòng KHÔNG ghi được: ';
			$bo = array();
			foreach ( array_slice( $hong, 0, 8 ) as $h ) { $bo[] = 'dòng ' . $h['dong'] . ' (' . $h['ly'] . ')'; }
			$msg .= implode( ' · ', $bo ) . ( count( $hong ) > 8 ? ' …' : '' );
		}
		return array( 'ok' => ! $hong, 'soPhieu' => count( $xong ), 'tongTien' => $tong,
			'xong' => $xong, 'hong' => $hong, 'msg' => $msg );
	}

	private static function ghi_tra_ncc( $u, $d ) {
		$tien = self::so( isset( $d['soTien'] ) ? $d['soTien'] : 0 );
		if ( $tien <= 0 ) { throw new Exception( 'Số tiền trả phải lớn hơn 0' ); }
		$ma  = VHJP_Doc::str( isset( $d['nccMa'] ) ? $d['nccMa'] : '' );
		$ten = VHJP_Doc::str( isset( $d['nccTen'] ) ? $d['nccTen'] : '' );
		if ( '' === $ma && '' === $ten ) { throw new Exception( 'Chọn hoặc gõ nhà cung cấp' ); }
		$ngay = VHJP_Doc::ngay( isset( $d['ngay'] ) ? $d['ngay'] : '' );
		if ( '' === $ngay ) { $ngay = VHJP_Ma::hom_nay(); }
		/* Ô hình thức để trống thì mặc định CK — giao diện đã nói trước là sẽ ghi vậy, nên để
		   trống trong sổ là hai nơi nói hai chuyện khác nhau. */
		$ht = VHJP_Doc::str( isset( $d['hinhThuc'] ) ? $d['hinhThuc'] : '' );
		if ( 'TM' !== $ht ) { $ht = 'CK'; }

		$p = VHJP_Ma::them( 'JP_KhoTraNcc', 'TT', array(
			'ngay' => $ngay, 'nccMa' => $ma, 'nccTen' => $ten, 'soTien' => $tien,
			'hinhThuc' => $ht,
			'ghiChu' => VHJP_Doc::str( isset( $d['ghiChu'] ) ? $d['ghiChu'] : '' ),
			'createdBy' => VHJP_Doc::str( isset( $u['hoTen'] ) ? $u['hoTen'] : '' ),
			'createdAt' => VHJP_Ma::hom_nay(),
		) );
		if ( ! is_array( $p ) ) { throw new Exception( 'Không ghi được phiếu trả tiền' ); }
		$id = VHJP_Doc::str( $p['id'] );
		VHJP_Nguon::sua( 'JP_KhoTraNcc', $id, array( 'soChungTu' => $id ) );
		VHJP_NhatKy::ghi( $u, 'KHO_TRA_NCC', '', $id, $ten . ' · ' . $tien );
		return array( 'ok' => true, 'id' => $id, 'soChungTu' => $id, 'soTien' => $tien,
			'msg' => 'Đã ghi phiếu trả ' . number_format( $tien, 0, ',', '.' ) . 'đ cho '
				. ( '' !== $ten ? $ten : $ma ) . ' (' . $id . ').' );
	}

	/**
	 * `jpKhoHuyTraNcc` — huỷ một phiếu trả tiền.
	 *
	 * ⚠️ ĐÁNH DẤU HUỶ, không xoá dòng. Phiếu trả tiền là chứng từ tiền mặt / chuyển khoản; xoá
	 *    hẳn là mất luôn vết "đã từng ghi rồi rút lại", mà đó chính là thứ kiểm toán đi tìm.
	 */
	public static function huy_tra_ncc( $u, $id, $ly_do ) {
		self::can_kt( $u );
		$id = VHJP_Doc::str( $id );
		$p  = VHJP_Nguon::tim_mot( 'JP_KhoTraNcc', 'id', $id );
		if ( ! $p ) { throw new Exception( 'Không tìm thấy phiếu trả tiền' ); }
		if ( '' !== VHJP_Doc::str( $p['huyBy'] ) ) { throw new Exception( 'Phiếu này đã huỷ rồi' ); }
		$ly_do = VHJP_Doc::str( $ly_do );
		if ( '' === $ly_do ) { throw new Exception( 'Phải ghi lý do huỷ' ); }

		VHJP_Nguon::sua( 'JP_KhoTraNcc', $id, array(
			'huyBy' => VHJP_Doc::str( isset( $u['hoTen'] ) ? $u['hoTen'] : '' ),
			'huyAt' => VHJP_Ma::hom_nay(), 'huyReason' => $ly_do ) );
		VHJP_NhatKy::ghi( $u, 'KHO_HUY_TRA_NCC', '', $id, $ly_do );
		return array( 'ok' => true, 'msg' => 'Đã huỷ phiếu trả tiền ' . $id . '. '
			. 'Công nợ nhà cung cấp cộng lại phần này.' );
	}

	/** `jpKhoLichSuTraNcc` */
	public static function lich_su_tra_ncc( $u, $gioi_han = 50 ) {
		self::can_kt( $u );
		$ds = VHJP_Nguon::doc( 'JP_KhoTraNcc' );
		usort( $ds, function ( $a, $b ) { return strcmp( (string) $b['id'], (string) $a['id'] ); } );
		$ds = array_slice( $ds, 0, max( 1, (int) $gioi_han ) );
		$ra = array();
		foreach ( $ds as $p ) {
			$p['soTien'] = self::so( $p['soTien'] );
			$p['daHuy']  = '' !== VHJP_Doc::str( $p['huyBy'] );
			$ra[] = $p;
		}
		return $ra;
	}

	/* ════════════════════════════════════════════════════ BÁO CÁO ĐÃ DUYỆT → SỔ 632 ═════ */

	/**
	 * Loại dòng báo cáo THẬT SỰ có hàng bán ra.
	 *
	 * 🔴 LỌC THEO LOẠI DÒNG, KHÔNG DỰA VÀO `soldQty` TÌNH CỜ BẰNG 0. `VHJP_Tinh` đã ép `soldQty`
	 *    về 0 cho dòng `COIN` và `NGOAI` đúng vì lý do này, và chính nó ghi lại: *"cộng `soldQty`
	 *    mọi dòng là cộng cả dòng COIN"*. Còn dòng `MAY` (mẫu tách, CHỈ tiền) thì `VHJP_Tinh`
	 *    KHÔNG đụng vào ô `soldQty` — nên ô ấy giữ nguyên số cũ của đời trước, và cộng nó vào là
	 *    xuất kho một lượng không ai bán.
	 */
	const DONG_CO_HANG = array( 'MONEY', 'STOCK', 'HANG' );

	/**
	 * `VHJP_Duyet` gọi khi báo cáo HOÀN TẤT — trừ lớp tồn ở kho CƠ SỞ và ghi giá vốn vào 632.
	 *
	 * =============================================================================================
	 * 🔴 ĐÂY LÀ CHỖ GIÁ VỐN BÁN RA SINH RA. TRƯỚC BẢN NÀY NÓ KHÔNG SINH Ở ĐÂU CẢ.
	 * =============================================================================================
	 * Nhân viên báo cáo bán 40 quả trứng; sổ có doanh thu, có tiền, có ảnh — và không có một đồng
	 * giá vốn nào. Lãi gộp của cả hệ bằng đúng doanh thu. Đó là loại sai không có triệu chứng:
	 * mọi màn đều xanh, mọi số đều cộng ra, chỉ mỗi con số cuối cùng là sai.
	 *
	 * 🔴 CHẠY ĐÚNG MỘT LẦN CHO MỖI BÁO CÁO. Ký hai lần, hay ký rồi trả về rồi ký lại, mà lần nào
	 *    cũng trừ tồn thì kho âm dần mà không phiếu nào sai. Đã có dòng xuất mang `reportId` này
	 *    thì DỪNG, và nói ra là đã ghi trước đó.
	 *
	 * ⚠️ TRỪ Ở KHO CƠ SỞ, không phải kho tổng. Hàng đã đi xuống cơ sở bằng `XUAT_CS` và mang giá
	 *    vốn của chính lớp đã chuyển; trừ lại ở kho tổng là trừ hai lần một lô hàng.
	 *
	 * ⚠️ Cơ sở chưa được chuyển hàng xuống thì KHÔNG có lớp để ăn — dòng vẫn ghi, giá vốn 0, cờ
	 *    `thieuLop` bật, và `VHJP_Duyet` in cảnh báo ấy ra cạnh câu "đã hoàn tất".
	 */
	public static function xuat_bao_cao( $u, $ma_bc ) {
		$ma_bc = VHJP_Doc::str( $ma_bc );
		if ( '' === $ma_bc ) { throw new Exception( 'Thiếu mã báo cáo' ); }
		$bc = VHJP_Nguon::tim_mot( 'JP_Reports', 'id', $ma_bc );
		if ( ! $bc ) { throw new Exception( 'Không tìm thấy báo cáo ' . $ma_bc ); }

		$da = VHJP_Nguon::tim( 'JP_KhoXuat', 'reportId', $ma_bc );
		if ( $da ) {
			$tien = 0;
			foreach ( $da as $x ) { $tien += self::so( $x['amount'] ); }
			return array( 'ok' => true, 'daCoTruoc' => true, 'soDong' => count( $da ),
				'tongGiaVon' => $tien, 'thieuLop' => array() );
		}

		$kho = VHJP_Doc::str( $bc['locationId'] );
		if ( '' === $kho ) { throw new Exception( 'Báo cáo không gắn cơ sở nào' ); }
		$ngay = VHJP_Doc::ngay( $bc['toDate'] );
		if ( '' === $ngay ) { $ngay = VHJP_Ma::hom_nay(); }

		/* Gom theo mã: một báo cáo có nhiều ô máy cùng bán một mã, và một phiếu một dòng cho mỗi
		   mã thì FIFO ăn một lượt, chứ không ăn rời rạc rồi đẻ ra chục dòng sổ cho cùng một mã. */
		$gom = array();
		foreach ( VHJP_Nguon::tim( 'JP_Rows', 'reportId', $ma_bc ) as $r ) {
			if ( ! in_array( VHJP_Doc::str( $r['rowKind'] ), self::DONG_CO_HANG, true ) ) { continue; }
			$ma = VHJP_Doc::str( $r['itemCode'] );
			$q  = self::so( $r['soldQty'] );
			if ( '' === $ma || $q <= 0 ) { continue; }
			if ( ! isset( $gom[ $ma ] ) ) { $gom[ $ma ] = 0; }
			$gom[ $ma ] += $q;
		}
		if ( ! $gom ) {
			return array( 'ok' => true, 'daCoTruoc' => false, 'soDong' => 0,
				'tongGiaVon' => 0, 'thieuLop' => array() );
		}
		ksort( $gom );

		if ( ! VHJP_Nguon::lay_khoa( 'kho_' . $kho, 10 ) ) {
			throw new Exception( 'Kho ' . self::ten_kho( $kho )
				. ' đang có người thao tác, thử lại sau vài giây' );
		}
		$n = 0; $tong = 0; $thieu = array();
		try {
			foreach ( $gom as $ma => $can ) {
				$an = self::an_fifo( $kho, $ma, $can );
				if ( $an['thieu'] > 0 ) {
					$an['mieng'][] = array( 'qty' => $an['thieu'], 'unitCost' => 0,
						'layerId' => '', 'thieu' => 1 );
					$thieu[] = array( 'itemCode' => $ma, 'qty' => $an['thieu'] );
				}
				foreach ( $an['mieng'] as $m ) {
					self::ghi_dong_xuat( $u, array(
						'soChungTu' => $ma_bc, 'ngay' => $ngay, 'loai' => 'BAN',
						'reportId' => $ma_bc, 'khoId' => $kho,
						'locationId' => $kho, 'locationName' => self::ten_kho( $kho ),
						'itemCode' => $ma, 'itemName' => self::ten_hang( $ma ),
						'qty' => $m['qty'], 'unitCost' => $m['unitCost'],
						'tkNo' => '6321', 'tkCo' => '1561',
						'layerId' => $m['layerId'], 'thieuLop' => empty( $m['thieu'] ) ? 0 : 1 ) );
					$n++;
					$tong += $m['qty'] * $m['unitCost'];
				}
			}
		} finally {
			VHJP_Nguon::tra_khoa( 'kho_' . $kho );
		}

		VHJP_NhatKy::ghi( $u, 'KHO_XUAT_BAN', $ma_bc, $kho, $n . ' dòng · ' . $tong );
		return array( 'ok' => true, 'daCoTruoc' => false, 'soDong' => $n,
			'tongGiaVon' => $tong, 'thieuLop' => $thieu );
	}

	/**
	 * `VHJP_Duyet` gọi khi TRẢ VỀ một báo cáo đã từng hoàn tất — hoàn lại đúng những gì đã trừ.
	 *
	 * ⚠️ Không hoàn thì sổ 632 còn dòng giá vốn của một báo cáo đang chờ sửa, và lớp tồn thiếu
	 *    đúng số đã trừ. Sửa xong nộp lại, duyệt lần hai, trừ tiếp lần nữa.
	 */
	public static function hoan_bao_cao( $u, $ma_bc ) {
		return self::xuat_lai( $u, $ma_bc );
	}

	/* ══════════════════════════════════════════════════════════════════ BẢNG KÊ ══════════ */

	/**
	 * `jpKhoBangKeNhap` — mọi phiếu nhập trong một khoảng ngày.
	 *
	 * ⚠️ Phiếu ĐÃ HUỶ vẫn LIỆT KÊ nhưng KHÔNG cộng vào tổng. Giấu hẳn là kế toán tìm một số
	 *    chứng từ đã ghi mà không thấy đâu, rồi ghi lại lần nữa.
	 */
	public static function bang_ke_nhap( $u, $tu, $den, $loai = '' ) {
		self::can_kt( $u );
		$tu   = VHJP_Doc::ngay( $tu );
		$den  = VHJP_Doc::ngay( $den );
		$loai = VHJP_Doc::str( $loai );
		$ten  = array( self::N_MUA => 'Mua hàng nhà cung cấp', self::N_DC => 'Nhận điều chuyển',
			self::N_DAU_KY => 'Số dư đầu kỳ', self::N_KIEM_KE => 'Kiểm kê thừa',
			self::N_TRA_KHO => 'Cơ sở trả về kho' );

		$rows = array(); $so_phieu = 0; $so_dong = 0; $tong = 0; $so_huy = 0;
		foreach ( VHJP_Nguon::doc( 'JP_KhoNhap' ) as $p ) {
			$n = VHJP_Doc::ngay( $p['ngay'] );
			if ( '' !== $tu && $n < $tu ) { continue; }
			if ( '' !== $den && $n > $den ) { continue; }
			$lo = VHJP_Doc::str( $p['loaiNhap'] );
			if ( '' !== $loai && $lo !== $loai ) { continue; }
			$p['tenLoai'] = isset( $ten[ $lo ] ) ? $ten[ $lo ] : $lo;
			$p['daHuy']   = '' !== VHJP_Doc::str( $p['huyBy'] );
			$p['soDong']  = self::so( $p['soDong'] );
			$p['tongTien'] = self::so( $p['tongTien'] );
			if ( $p['daHuy'] ) {
				$so_huy++;
			} else {
				$so_phieu++;
				$so_dong += $p['soDong'];
				$tong    += $p['tongTien'];
			}
			$rows[] = $p;
		}
		usort( $rows, function ( $a, $b ) {
			$x = strcmp( VHJP_Doc::ngay( $a['ngay'] ), VHJP_Doc::ngay( $b['ngay'] ) );
			return 0 !== $x ? $x : strcmp( (string) $a['id'], (string) $b['id'] );
		} );
		return array( 'ok' => true, 'tuNgay' => $tu, 'denNgay' => $den, 'loai' => $loai,
			'rows' => $rows,
			'tong' => array( 'soPhieu' => $so_phieu, 'soDong' => $so_dong,
				'tongTien' => $tong, 'soHuy' => $so_huy ) );
	}

	/** `jpKhoBangKeXuat` — mọi phiếu xuất trong một khoảng ngày, kèm bảng gộp theo loại. */
	public static function bang_ke_xuat( $u, $tu, $den, $loai = '' ) {
		self::can_kt( $u );
		$tu   = VHJP_Doc::ngay( $tu );
		$den  = VHJP_Doc::ngay( $den );
		$loai = VHJP_Doc::str( $loai );
		$bang = self::bang_loai_xuat();

		$rows = array(); $so_phieu = 0; $tong_sl = 0; $tong = 0; $so_huy = 0; $gom = array();
		foreach ( VHJP_Nguon::doc( 'JP_KhoDieuChuyen' ) as $p ) {
			$n = VHJP_Doc::ngay( $p['ngay'] );
			if ( '' !== $tu && $n < $tu ) { continue; }
			if ( '' !== $den && $n > $den ) { continue; }
			$ma = VHJP_Doc::str( $p['loai'] );
			if ( '' !== $loai && $ma !== $loai ) { continue; }
			$p['tenLoai']  = isset( $bang[ $ma ] ) ? $bang[ $ma ]['ten'] : $ma;
			$p['tk']       = isset( $bang[ $ma ] ) ? $bang[ $ma ]['tk'] : '';
			$p['daHuy']    = '' !== VHJP_Doc::str( $p['huyBy'] );
			$p['soDong']   = self::so( $p['soDong'] );
			$p['tongSL']   = self::so( $p['tongSL'] );
			$p['tongTien'] = self::so( $p['tongTien'] );
			if ( $p['daHuy'] ) {
				$so_huy++;
			} else {
				$so_phieu++;
				$tong_sl += $p['tongSL'];
				$tong    += $p['tongTien'];
				if ( ! isset( $gom[ $ma ] ) ) {
					$gom[ $ma ] = array( 'ma' => $ma, 'tenLoai' => $p['tenLoai'], 'tk' => $p['tk'],
						'soPhieu' => 0, 'tongSL' => 0, 'tongTien' => 0 );
				}
				$gom[ $ma ]['soPhieu']++;
				$gom[ $ma ]['tongSL']   += $p['tongSL'];
				$gom[ $ma ]['tongTien'] += $p['tongTien'];
			}
			$rows[] = $p;
		}
		usort( $rows, function ( $a, $b ) {
			$x = strcmp( VHJP_Doc::ngay( $a['ngay'] ), VHJP_Doc::ngay( $b['ngay'] ) );
			return 0 !== $x ? $x : strcmp( (string) $a['id'], (string) $b['id'] );
		} );
		return array( 'ok' => true, 'tuNgay' => $tu, 'denNgay' => $den, 'loai' => $loai,
			'rows' => $rows, 'theoLoai' => array_values( $gom ),
			'tong' => array( 'soPhieu' => $so_phieu, 'tongSL' => $tong_sl,
				'tongTien' => $tong, 'soHuy' => $so_huy ) );
	}

	/* ══════════════════════════════════════════════════════════════════ NỘI BỘ ═══════════ */

	/**
	 * Một dòng sổ xuất. ĐƯỜNG DUY NHẤT ghi vào `JP_KhoXuat`.
	 *
	 * ⚠️ Phiếu xuất và kiểm kê thiếu đều đẻ ra dòng loại này. Hai nơi tự ghi lấy là hai nơi sẽ
	 *    quên hai ô khác nhau — và bảng N-X-T thì đọc đúng mấy ô ấy.
	 */
	private static function ghi_dong_xuat( $u, $x ) {
		return VHJP_Ma::them( 'JP_KhoXuat', 'XK', array(
			'soChungTu' => VHJP_Doc::str( isset( $x['soChungTu'] ) ? $x['soChungTu'] : '' ),
			'ngay' => VHJP_Doc::ngay( $x['ngay'] ), 'loai' => VHJP_Doc::str( $x['loai'] ),
			'reportId' => VHJP_Doc::str( isset( $x['reportId'] ) ? $x['reportId'] : '' ),
			'dcId' => VHJP_Doc::str( isset( $x['dcId'] ) ? $x['dcId'] : '' ),
			'khoId' => VHJP_Doc::str( $x['khoId'] ),
			'locationId' => VHJP_Doc::str( isset( $x['locationId'] ) ? $x['locationId'] : '' ),
			'locationName' => VHJP_Doc::str( isset( $x['locationName'] ) ? $x['locationName'] : '' ),
			'itemCode' => VHJP_Doc::str( $x['itemCode'] ),
			'itemName' => VHJP_Doc::str( isset( $x['itemName'] ) ? $x['itemName'] : '' ),
			'qty' => self::so( $x['qty'] ), 'unitCost' => self::so( $x['unitCost'] ),
			'amount' => self::so( $x['qty'] ) * self::so( $x['unitCost'] ),
			'tkNo' => VHJP_Doc::str( $x['tkNo'] ), 'tkCo' => VHJP_Doc::str( $x['tkCo'] ),
			'layerId' => VHJP_Doc::str( isset( $x['layerId'] ) ? $x['layerId'] : '' ),
			'thieuLop' => empty( $x['thieuLop'] ) ? 0 : 1,
			'createdBy' => VHJP_Doc::str( isset( $u['hoTen'] ) ? $u['hoTen'] : '' ),
			'createdAt' => VHJP_Ma::hom_nay(),
		) );
	}

	/** Một lớp tồn mới. `qtyInit` giữ nguyên vĩnh viễn — đó là thứ để soi lớp đã bị ăn bao nhiêu. */
	private static function tao_lop( $kho, $ma_hang, $ngay, $qty, $gia, $nguon, $lot ) {
		return VHJP_Ma::them( 'JP_KhoLop', 'LOP', array(
			'khoId'      => VHJP_Doc::str( $kho ),
			'locationId' => self::la_kho_tong( $kho ) ? '' : VHJP_Doc::str( $kho ),
			'itemCode'   => VHJP_Doc::str( $ma_hang ),
			'ngay'       => VHJP_Doc::ngay( $ngay ),
			'qtyInit'    => self::so( $qty ), 'qtyRemaining' => self::so( $qty ),
			'unitCost'   => self::so( $gia ),
			'nguon'      => VHJP_Doc::str( $nguon ), 'lotNo' => VHJP_Doc::str( $lot ),
			'createdAt'  => VHJP_Ma::hom_nay(),
		) );
	}
}
