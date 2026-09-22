<?php
/**
 * CẤU HÌNH & TIỆN ÍCH — tài khoản, PIN theo cơ sở, nạp danh mục, mấy nút dọn dẹp.
 *
 * =================================================================================================
 * 🔴 MÀN NÀY PHÁT QUYỀN VÀ PHÁT PIN — MỌI HÀM ĐỀU CHỈ KẾ TOÁN
 * =================================================================================================
 * Gác ở bảng `chi_ke_toan()` của cổng, cạnh bảng hàm, chứ không rải `la_kt()` vào từng hàm: rải
 * thì sót một chỗ là hở đúng ngần ấy cửa mà không ai đếm được.
 *
 * =================================================================================================
 * 🔴 PIN BẢN RÕ CHỈ HIỆN ĐÚNG MỘT LẦN
 * =================================================================================================
 * Sổ chỉ giữ bản băm, nên cấp xong mà không ghi lại là phải cấp LẠI — giữa lúc cơ sở đang bán.
 * Vì thế lượt cấp trả về bản rõ đúng một lần, và câu cảnh báo đi kèm nói rõ điều đó.
 *
 * ⚠️ PIN CHỈ CÓ 3 CHỮ SỐ nên nhiều nhất 1000 tài khoản, và đăng nhập CHỈ BẰNG PIN nên PIN phải
 *    DUY NHẤT toàn hệ. Hết số thì nói ra tên cơ sở không cấp được, đừng lặng lẽ bỏ qua.
 *
 * @package VHCP_JP
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHJP_CauHinh2 {

	/** Tiền tố tên đăng nhập của tài khoản CƠ SỞ. Quy ước này chỉ được có MỘT bản. */
	const TIEN_TO_CS = 'cs';

	private static function so( $v ) { return VHJP_Doc::num( $v ); }

	private static function can_kt( $u ) {
		if ( ! VHJP_Auth::la_kt( $u ) ) { throw new Exception( 'Việc này cần tài khoản kế toán' ); }
	}

	/**
	 * Tên đăng nhập nội bộ của một cơ sở — suy từ MÃ cơ sở.
	 *
	 * 🔴 CHỈ MỘT BẢN CỦA QUY ƯỚC NÀY. Ghép lại ở chỗ khác là có ngày lệch với lúc cấp, rồi bảng
	 *    báo "chưa có PIN" cho cơ sở đang chạy ngon, kế toán bấm Cấp, và sinh ra tài khoản THỨ
	 *    HAI cho cùng một cơ sở.
	 */
	public static function user_coso( $ma_code ) {
		$c = strtolower( preg_replace( '/[^A-Za-z0-9]/', '', VHJP_Doc::str( $ma_code ) ) );
		return '' === $c ? '' : self::TIEN_TO_CS . $c;
	}

	/* ══════════════════════════════════════════════════════════════════ TÀI KHOẢN ════════ */

	/** `jpCfgListUsers` — KHÔNG bao giờ trả PIN, kể cả bản băm. */
	public static function ds_user( $u ) {
		self::can_kt( $u );
		$ra = array();
		foreach ( VHJP_Nguon::doc( 'JP_Users' ) as $x ) {
			/* 🔴 Dựng theo lối CHO PHÉP, không theo lối loại trừ: thêm một cột nhạy cảm vào
			   bảng sau này là nó tự chảy ra ngoài nếu viết `unset($x['pin'])`. */
			$ra[] = array(
				'id' => VHJP_Doc::str( $x['id'] ),
				'username' => VHJP_Doc::str( $x['username'] ),
				'hoTen' => VHJP_Doc::str( $x['hoTen'] ),
				'role' => VHJP_Doc::str( $x['role'] ),
				'machineType' => VHJP_Doc::str( $x['machineType'] ),
				'locationIds' => VHJP_Auth::tach_ids( $x['locationIds'] ),
				'maNV' => VHJP_Doc::str( isset( $x['maNV'] ) ? $x['maNV'] : '' ),
				'active' => self::so( $x['active'] ) ? true : false,
				'coPin' => '' !== VHJP_Doc::str( $x['pin'] ),
				'pinMacDinh' => VHJP_Auth::con_dung_pin_mac_dinh( $x ),
			);
		}
		usort( $ra, function ( $a, $b ) { return strcmp( $a['hoTen'], $b['hoTen'] ); } );
		return $ra;
	}

	/**
	 * `jpCfgSaveUser` — thêm hoặc sửa một tài khoản.
	 *
	 * 🔴 PIN TRỐNG = GIỮ NGUYÊN, không phải xoá. Kế toán sửa họ tên rồi bấm Lưu mà ô PIN trống
	 *    (nó luôn trống, vì không tra lại được) — coi đó là "xoá PIN" là người ấy mất đường đăng
	 *    nhập ngay giữa ca, và không ai hiểu vì sao.
	 *
	 * 🔴 PIN PHẢI DUY NHẤT TOÀN HỆ. Đăng nhập chỉ bằng PIN, nên hai người cùng PIN là người gõ
	 *    vào rơi vào tài khoản của ai đó — `jpLoginPin` lấy người gặp trước.
	 */
	public static function luu_user( $u, $d ) {
		self::can_kt( $u );
		$d  = is_array( $d ) ? $d : array();
		$id = VHJP_Doc::str( isset( $d['id'] ) ? $d['id'] : '' );
		$ten = VHJP_Doc::str( isset( $d['hoTen'] ) ? $d['hoTen'] : '' );
		if ( '' === $ten ) { throw new Exception( 'Phải có họ tên' ); }
		$vai = VHJP_Doc::str( isset( $d['role'] ) ? $d['role'] : '' );
		if ( '' === $vai ) { $vai = VHJP_Auth::VAI_NV; }

		$o = array(
			'username' => VHJP_Doc::str( isset( $d['username'] ) ? $d['username'] : '' ),
			'hoTen' => $ten, 'role' => $vai,
			'machineType' => VHJP_Doc::str( isset( $d['machineType'] ) ? $d['machineType'] : '' ),
			'locationIds' => implode( ',', VHJP_Auth::tach_ids(
				isset( $d['locationIds'] ) ? $d['locationIds'] : '' ) ),
			'active' => empty( $d['active'] ) ? 0 : 1,
		);
		if ( array_key_exists( 'maNV', $d ) ) {
			$o['maNV'] = VHJP_Doc::str( $d['maNV'] );
		}

		$pin = VHJP_Doc::str( isset( $d['pin'] ) ? $d['pin'] : '' );
		if ( '' !== $pin ) {
			$p = VHJP_Auth::chuan_pin( $pin );
			if ( '' === $p ) {
				throw new Exception( 'PIN phải đúng ' . VHJP_Auth::PIN_LEN . ' chữ số' );
			}
			if ( VHJP_Auth::pin_da_dung( $p, $id ) ) {
				throw new Exception( 'PIN này đã có người dùng. Đăng nhập chỉ bằng PIN nên '
					. 'hai người cùng số là người gõ vào rơi vào tài khoản của ai đó — chọn số khác.' );
			}
			$o['pin'] = VHJP_Auth::bam( $p );
		}

		if ( '' !== $id ) {
			if ( ! VHJP_Nguon::tim_mot( 'JP_Users', 'id', $id ) ) {
				throw new Exception( 'Không tìm thấy tài khoản' );
			}
			VHJP_Nguon::sua( 'JP_Users', $id, $o );
		} else {
			$o['createdAt'] = VHJP_Ma::hom_nay();
			$x = VHJP_Ma::them( 'JP_Users', 'U', $o );
			if ( ! is_array( $x ) ) { throw new Exception( 'Không ghi được tài khoản' ); }
			$id = VHJP_Doc::str( $x['id'] );
		}
		VHJP_NhatKy::ghi( $u, 'CFG_SAVE_USER', '', $id, $ten . ' · ' . $vai );
		return array( 'ok' => true, 'id' => $id,
			'msg' => 'Đã lưu tài khoản ' . $ten . '.'
				. ( '' === $pin ? ' (PIN giữ nguyên)' : ' PIN đã đổi.' ) );
	}

	/* ════════════════════════════════════════════════════════════ PIN THEO CƠ SỞ ═════════ */

	/**
	 * `jpPinTheoCoSo` — bảng trạng thái: cơ sở nào đã có tài khoản, đã cấp PIN chưa.
	 *
	 * ⚠️ ĐI TỪ DANH MỤC CƠ SỞ, không đi từ danh sách tài khoản. Cơ sở CHƯA CÓ tài khoản nào vẫn
	 *    phải có một dòng — đó đúng là dòng cần thấy nhất, mà đi từ tài khoản thì nó vô hình.
	 */
	public static function pin_theo_coso( $u ) {
		self::can_kt( $u );
		$theo_user = array();
		foreach ( VHJP_Nguon::doc( 'JP_Users' ) as $x ) {
			$theo_user[ strtolower( VHJP_Doc::str( $x['username'] ) ) ] = $x;
		}
		$rows = array(); $thieu = array(); $ngung_mo = array(); $theo_ten = array();
		foreach ( VHJP_Nguon::doc( 'JP_Locations' ) as $l ) {
			$code = VHJP_Doc::str( $l['code'] );
			$un   = self::user_coso( $code );
			$cs_active = self::so( $l['active'] ) ? true : false;
			$tk  = ( '' !== $un && isset( $theo_user[ $un ] ) ) ? $theo_user[ $un ] : null;
			$ten = VHJP_Doc::str( $l['name'] );

			if ( '' !== $un ) {
				if ( ! isset( $theo_ten[ $un ] ) ) { $theo_ten[ $un ] = array(); }
				$theo_ten[ $un ][] = '' !== $code ? $code : $ten;
			}
			$rows[] = array(
				'locationId' => VHJP_Doc::str( $l['id'] ), 'code' => $code, 'tenCoSo' => $ten,
				'loaiMay' => VHJP_Doc::str( $l['machineType'] ),
				'coSoActive' => $cs_active,
				'coTaiKhoan' => (bool) $tk,
				'coPin' => $tk ? ( '' !== VHJP_Doc::str( $tk['pin'] ) ) : false,
				'pinMacDinh' => $tk ? VHJP_Auth::con_dung_pin_mac_dinh( $tk ) : false,
				'taiKhoanActive' => $tk ? ( self::so( $tk['active'] ) ? true : false ) : false,
			);
			if ( $cs_active && ( ! $tk || '' === VHJP_Doc::str( $tk['pin'] ) ) ) { $thieu[] = $ten; }
			if ( ! $cs_active && $tk && self::so( $tk['active'] ) ) { $ngung_mo[] = $ten; }
		}
		/* Hai cơ sở ra CÙNG một tên đăng nhập = dùng chung một PIN, và cơ sở cấp sau ghi đè
		   quyền của cơ sở trước. Hỏng im lặng nhất trong cả màn này. */
		$trung = array();
		foreach ( $theo_ten as $un => $ds ) {
			if ( count( $ds ) > 1 ) { $trung[] = array( 'username' => $un, 'codes' => $ds ); }
		}
		return array( 'ok' => true, 'rows' => $rows, 'thieuPin' => $thieu,
			'ngungConMo' => $ngung_mo, 'trungTen' => $trung );
	}

	/**
	 * `jpTaoPinCoSo` — cấp PIN cho cơ sở.
	 *
	 * @param bool  $cap_lai  true = đổi PIN của cơ sở ĐÃ CÓ tài khoản.
	 * @param array $chi_cs   rỗng = mọi cơ sở; có thì chỉ mấy cơ sở ấy.
	 *
	 * ⚠️ MẶC ĐỊNH KHÔNG ĐỤNG TÀI KHOẢN ĐÃ CÓ. Cấp lại là PIN cũ hết hiệu lực ngay, và người
	 *    đang trực bị đá ra ở lần đăng nhập sau — phải là một lượt bấm có chủ ý.
	 */
	public static function tao_pin_coso( $u, $d = array() ) {
		self::can_kt( $u );
		$d       = is_array( $d ) ? $d : array();
		$cap_lai = ! empty( $d['capLai'] );
		$chi     = array();
		foreach ( (array) ( isset( $d['locationIds'] ) ? $d['locationIds'] : array() ) as $x ) {
			$chi[] = VHJP_Doc::str( $x );
		}

		$rows = array(); $het_so = array();
		foreach ( VHJP_Nguon::doc( 'JP_Locations' ) as $l ) {
			$id = VHJP_Doc::str( $l['id'] );
			if ( $chi && ! in_array( $id, $chi, true ) ) { continue; }
			if ( ! $chi && ! self::so( $l['active'] ) ) { continue; }
			$code = VHJP_Doc::str( $l['code'] );
			$un   = self::user_coso( $code );
			if ( '' === $un ) { continue; }

			$tk = null;
			foreach ( VHJP_Nguon::doc( 'JP_Users' ) as $x ) {
				if ( strtolower( VHJP_Doc::str( $x['username'] ) ) === $un ) { $tk = $x; break; }
			}
			if ( $tk && ! $cap_lai ) { continue; }

			$pin = self::pin_trong();
			if ( '' === $pin ) { $het_so[] = VHJP_Doc::str( $l['name'] ); continue; }

			$o = array( 'username' => $un, 'hoTen' => VHJP_Doc::str( $l['name'] ),
				'role' => VHJP_Auth::VAI_NV,
				'machineType' => VHJP_Doc::str( $l['machineType'] ),
				'locationIds' => $id, 'active' => 1, 'pin' => VHJP_Auth::bam( $pin ) );
			if ( $tk ) {
				VHJP_Nguon::sua( 'JP_Users', VHJP_Doc::str( $tk['id'] ), $o );
			} else {
				$o['createdAt'] = VHJP_Ma::hom_nay();
				VHJP_Ma::them( 'JP_Users', 'U', $o );
			}
			$rows[] = array( 'locationId' => $id, 'code' => $code,
				'tenCoSo' => VHJP_Doc::str( $l['name'] ),
				'loaiMay' => VHJP_Doc::str( $l['machineType'] ), 'pin' => $pin );
		}

		VHJP_NhatKy::ghi( $u, 'TAO_PIN_COSO', '', '', count( $rows ) . ' cơ sở'
			. ( $cap_lai ? ' (cấp lại)' : '' ) );
		return array( 'ok' => true, 'rows' => $rows, 'hetSo' => $het_so,
			/* 🔴 Câu này phải đi kèm MỌI lượt cấp. Sổ chỉ giữ bản băm nên không tra lại số
			   được; không ghi ngay là phải cấp lại giữa lúc cơ sở đang bán. */
			'canhBao' => 'Bảng PIN bản rõ chỉ hiện ĐÚNG MỘT LẦN — sổ chỉ lưu bản băm nên không '
				. 'tra lại số được. Ghi ra giấy hoặc tải CSV TRƯỚC khi rời màn này.',
			'msg' => $rows
				? 'Đã cấp PIN cho ' . count( $rows ) . ' cơ sở.'
				: 'Không cơ sở nào cần cấp mới. Muốn đổi PIN của cơ sở đã có thì bấm Cấp LẠI.' );
	}

	/**
	 * Một số PIN chưa ai dùng.
	 *
	 * ⚠️ DÒ TỪ MỘT ĐIỂM NGẪU NHIÊN rồi chạy vòng, không dò từ 000: dò từ 000 thì mấy cơ sở đầu
	 *    luôn nhận 000, 001, 002 — đoán được.
	 */
	private static function pin_trong() {
		$max = (int) pow( 10, VHJP_Auth::PIN_LEN );
		$dau = random_int( 0, $max - 1 );
		for ( $i = 0; $i < $max; $i++ ) {
			$p = str_pad( (string) ( ( $dau + $i ) % $max ), VHJP_Auth::PIN_LEN, '0', STR_PAD_LEFT );
			if ( in_array( $p, VHJP_Auth::pin_mac_dinh(), true ) ) { continue; }
			if ( ! VHJP_Auth::pin_da_dung( $p ) ) { return $p; }
		}
		return '';
	}

	/* ═════════════════════════════════════════════════════════════ NẠP DANH MỤC ══════════ */

	/**
	 * Nạp một bộ danh mục từ tệp JSON trong plugin.
	 *
	 * 🔴 KHÔNG BỊA SỐ. Bộ số gốc nằm trong `JP2_13_DuLieuDauKy` của bản Apps Script và CHƯA được
	 *    mang sang. Đẻ ra một danh mục "hợp lý" ở đây là gieo dữ liệu giả vào một hệ kế toán —
	 *    và tồn đầu kỳ giả thì mọi giá vốn sau đó đều sai mà không phép kiểm nào bắt được.
	 *    Nên: đọc tệp nếu có, không có thì NÓI RÕ là chưa có và chỉ đường làm tay.
	 */
	private static function doc_bo_du_lieu( $ten ) {
		$f = VHJP_DIR . 'du-lieu/' . $ten . '.json';
		if ( ! is_file( $f ) ) { return null; }
		$d = json_decode( (string) file_get_contents( $f ), true );
		return is_array( $d ) ? $d : null;
	}

	private static function chua_co_bo( $ten, $man ) {
		return array( 'ok' => false, 'soDong' => 0, 'thieuBo' => true,
			'msg' => 'Bộ số "' . $ten . '" chưa có trong bản này — nó nằm ở tệp '
				. '`JP2_13_DuLieuDauKy` của bản Apps Script cũ và chưa được mang sang. '
				. 'KHÔNG tự đẻ ra số thay thế: dữ liệu gốc giả thì mọi con số sau đó đều sai mà '
				. 'không phép kiểm nào bắt được. Đường làm được ngay: ' . $man );
	}

	/** `jpNapCoSo` — nạp danh mục cơ sở từ bộ số kèm plugin. */
	public static function nap_coso( $u ) {
		self::can_kt( $u );
		$bo = self::doc_bo_du_lieu( 'co-so' );
		if ( null === $bo ) {
			return self::chua_co_bo( 'danh mục cơ sở',
				'thêm từng cơ sở ở tab Cơ sở — mỗi cơ sở một dòng, mất vài phút.' );
		}
		$them = 0; $sua = 0;
		foreach ( $bo as $x ) {
			if ( ! is_array( $x ) ) { continue; }
			$code = VHJP_Doc::str( isset( $x['code'] ) ? $x['code'] : '' );
			if ( '' === $code ) { continue; }
			$cu = VHJP_Nguon::tim_mot( 'JP_Locations', 'code', $code );
			if ( $cu ) { VHJP_Nguon::sua( 'JP_Locations', $cu['id'], $x ); $sua++; }
			else { VHJP_Ma::them( 'JP_Locations', 'CS', $x ); $them++; }
		}
		VHJP_NhatKy::ghi( $u, 'NAP_CO_SO', '', '', $them . ' mới · ' . $sua . ' sửa' );
		return array( 'ok' => true, 'soDong' => $them + $sua, 'them' => $them, 'sua' => $sua,
			'msg' => 'Đã nạp danh mục cơ sở: ' . $them . ' mới, ' . $sua . ' cập nhật.' );
	}

	/** `jpNapDanhMucHangJP` — nạp danh mục hàng từ bộ số kèm plugin. */
	public static function nap_hang( $u ) {
		self::can_kt( $u );
		$bo = self::doc_bo_du_lieu( 'danh-muc-hang' );
		if ( null === $bo ) {
			return self::chua_co_bo( 'danh mục hàng JP',
				'dùng nút Nhập Excel ngay bên cạnh — nó nhận file .xlsx của danh mục hàng.' );
		}
		return self::nhap_hang( $u, $bo );
	}

	/**
	 * `jpCfgImportItems` — nhập danh mục hàng từ tệp Excel.
	 *
	 * ⚠️ PHẢI NÓI RA SỐ MÃ "ĐÃ ĐÚNG, KHÔNG GHI LẠI". Nhập lại đúng tệp cũ thì thêm = sửa = 0;
	 *    không in gì thêm là kế toán tưởng lượt nhập không chạy rồi bấm lại vài lần.
	 */
	public static function nhap_hang( $u, $rows ) {
		self::can_kt( $u );
		$rows = is_array( $rows ) ? $rows : array();
		if ( ! $rows ) { throw new Exception( 'Tệp không có dòng nào' ); }

		$them = 0; $sua = 0; $khong_doi = 0; $bo_qua = 0;
		foreach ( $rows as $x ) {
			if ( ! is_array( $x ) ) { $bo_qua++; continue; }
			$code = VHJP_Doc::str( isset( $x['code'] ) ? $x['code'] : '' );
			if ( '' === $code ) {
				$code = VHJP_Doc::str( isset( $x['misa'] ) ? $x['misa'] : '' );
			}
			if ( '' === $code ) { $bo_qua++; continue; }
			$o = array(
				'code' => $code,
				'misa' => VHJP_Doc::str( isset( $x['misa'] ) ? $x['misa'] : $code ),
				'name' => VHJP_Doc::str( isset( $x['name'] ) ? $x['name'] : '' ),
				'price' => self::so( isset( $x['price'] ) ? $x['price'] : 0 ),
				'dvt' => VHJP_Doc::str( isset( $x['dvt'] ) ? $x['dvt'] : '' ),
				'active' => array_key_exists( 'active', $x ) ? ( empty( $x['active'] ) ? 0 : 1 ) : 1,
				'note' => VHJP_Doc::str( isset( $x['note'] ) ? $x['note'] : '' ),
			);
			$cu = VHJP_Nguon::tim_mot( 'JP_Items', 'code', $code );
			if ( ! $cu ) { VHJP_Nguon::them( 'JP_Items', $o ); $them++; continue; }

			$doi = false;
			foreach ( $o as $k => $v ) {
				if ( VHJP_Doc::str( isset( $cu[ $k ] ) ? $cu[ $k ] : '' ) !== VHJP_Doc::str( $v ) ) {
					$doi = true; break;
				}
			}
			if ( ! $doi ) { $khong_doi++; continue; }
			VHJP_Nguon::sua( 'JP_Items', $code, $o );
			$sua++;
		}
		VHJP_NhatKy::ghi( $u, 'IMPORT_ITEMS', '', '', $them . '/' . $sua . '/' . $khong_doi );
		return array( 'ok' => true, 'added' => $them, 'updated' => $sua,
			'khongDoi' => $khong_doi, 'boQua' => $bo_qua,
			'msg' => 'Thêm mới ' . $them . ' · cập nhật ' . $sua
				. ( $khong_doi ? ' · ' . $khong_doi . ' mã đã đúng, không ghi lại' : '' )
				. ( $bo_qua ? ' · bỏ ' . $bo_qua . ' dòng thiếu mã' : '' ) );
	}

	/** `jpNapTonDauKy31_7` — gieo tồn mở sổ 31/07 cho mọi kho từ bộ số kèm plugin. */
	public static function nap_ton_31_7( $u ) {
		self::can_kt( $u );
		$bo = self::doc_bo_du_lieu( 'ton-dau-ky-31-07' );
		if ( null === $bo ) {
			$k = self::chua_co_bo( 'tồn đầu kỳ 31/07/2026',
				'mở Kho → Tồn đầu kỳ, chọn từng kho rồi dán bảng Excel vào — màn ấy đã có nút '
					. 'nhập tệp, và nó CHẶN CỨNG kho đã khai nên bấm hai lần không nhân đôi.' );
			$k['xong'] = array(); $k['tongSL'] = 0; $k['tongTien'] = 0;
			return $k;
		}
		$xong = array(); $t_sl = 0; $t_tien = 0;
		foreach ( $bo as $kho => $dong ) {
			$kq = VHJP_Kho::so_du_dau_ky( $u, array( 'khoId' => $kho, 'ngay' => '2026-07-31',
				'ghiChu' => 'Nạp tồn đầu kỳ 31/07/2026', 'rows' => $dong ) );
			/* Kho đã khai rồi thì `so_du_dau_ky` trả `daCoTruoc` — BỎ QUA, không ném lỗi:
			   bấm hai lần không được nhân đôi, và cũng không được dừng giữa chừng. */
			if ( empty( $kq['ok'] ) ) { continue; }
			$xong[] = array( 'kho' => VHJP_Kho::ten_kho( $kho ), 'soChungTu' => $kq['soChungTu'],
				'soDong' => $kq['soDong'], 'soLuong' => $kq['soLuong'], 'tongTien' => $kq['tongTien'] );
			$t_sl   += $kq['soLuong'];
			$t_tien += $kq['tongTien'];
		}
		return array( 'ok' => true, 'xong' => $xong, 'tongSL' => $t_sl, 'tongTien' => $t_tien,
			'msg' => $xong
				? 'Đã khai tồn đầu kỳ cho ' . count( $xong ) . ' kho · ' . $t_sl . ' cái.'
				: 'Mọi kho trong bộ số đều đã khai tồn đầu kỳ rồi — không ghi thêm gì.' );
	}

	/** `jpNapBuTonDauKy31_7` — so bộ số với phần đã khai, chỉ ghi PHẦN THIẾU. */
	public static function nap_bu_31_7( $u, $ghi = false ) {
		self::can_kt( $u );
		$bo = self::doc_bo_du_lieu( 'ton-dau-ky-31-07' );
		if ( null === $bo ) {
			$k = self::chua_co_bo( 'tồn đầu kỳ 31/07/2026',
				'so tay bằng màn Kho → Tồn kho theo lớp, rồi bù bằng Kiểm kê.' );
			$k['thieu'] = array(); $k['ghi'] = false; $k['soMa'] = 0; $k['soCai'] = 0;
			return $k;
		}
		$ghi    = (bool) $ghi;
		$thieu  = array(); $so_cai = 0;
		foreach ( $bo as $kho => $dong ) {
			foreach ( (array) $dong as $x ) {
				$ma  = VHJP_Doc::str( isset( $x['itemCode'] ) ? $x['itemCode'] : '' );
				$can = self::so( isset( $x['qty'] ) ? $x['qty'] : 0 );
				if ( '' === $ma || $can <= 0 ) { continue; }
				$dang = VHJP_Kho::ton_mot( $kho, $ma );
				$bu   = $can - $dang['tonQty'];
				if ( $bu <= 0 ) { continue; }
				$gia  = self::so( isset( $x['unitCost'] ) ? $x['unitCost'] : 0 );
				$thieu[] = array( 'kho' => VHJP_Kho::ten_kho( $kho ), 'khoId' => $kho, 'ma' => $ma,
					'bang' => $can, 'daKhai' => $dang['tonQty'], 'bu' => $bu,
					'donGia' => $gia, 'tien' => $bu * $gia );
				$so_cai += $bu;
			}
		}
		if ( $ghi && $thieu ) {
			$theo_kho = array();
			foreach ( $thieu as $x ) {
				$theo_kho[ $x['khoId'] ][] = array( 'itemCode' => $x['ma'], 'qty' => $x['bu'],
					'unitCost' => $x['donGia'] );
			}
			foreach ( $theo_kho as $kho => $dong ) {
				/* Đi đường KIỂM KÊ THỪA, không gieo thêm một phiếu đầu kỳ thứ hai: mỗi kho chỉ
				   được một phiếu đầu kỳ, và bù bằng kiểm kê thì có biên bản đọc lại được. */
				$rows = array();
				foreach ( $dong as $x ) {
					$rows[] = array( 'itemCode' => $x['itemCode'],
						'tonThuc' => VHJP_Kho::ton_mot( $kho, $x['itemCode'] )['tonQty'] + $x['qty'],
						'note' => 'Bù theo bảng tồn 31/07' );
				}
				VHJP_Kho::kiem_ke( $u, array( 'khoId' => $kho, 'ngay' => VHJP_Ma::hom_nay(),
					'cheDoThua' => 'GHI_TANG', 'ghiChu' => 'Nạp bù tồn 31/07', 'rows' => $rows ) );
			}
			VHJP_NhatKy::ghi( $u, 'NAP_BU_31_7', '', '', count( $thieu ) . ' mã · ' . $so_cai );
		}
		return array( 'ok' => true, 'ghi' => $ghi, 'thieu' => $thieu,
			'soMa' => count( $thieu ), 'soCai' => $so_cai,
			'msg' => $thieu
				? ( $ghi ? 'Đã ghi bù ' . count( $thieu ) . ' mã · ' . $so_cai . ' cái (qua biên bản kiểm kê).'
					: 'Còn thiếu ' . count( $thieu ) . ' mã · ' . $so_cai . ' cái so với bảng 31/07. '
						. 'Chưa ghi gì — bấm "Ghi thật" mới ghi.' )
				: 'Không thiếu mã nào so với bảng 31/07.' );
	}

	/* ════════════════════════════════════════════════════════════════ TIỆN ÍCH ═══════════ */

	/** `jpXoaBaoCaoNhap` — nhân viên xoá bản nháp của chính mình. */
	public static function xoa_nhap( $u, $ma_bc ) {
		$ma_bc = VHJP_Doc::str( $ma_bc );
		$r     = VHJP_Nguon::tim_mot( 'JP_Reports', 'id', $ma_bc );
		if ( ! $r ) { throw new Exception( 'Không tìm thấy báo cáo' ); }
		if ( ! VHJP_Auth::la_kt( $u ) && VHJP_Doc::str( $r['userId'] ) !== VHJP_Doc::str( $u['id'] ) ) {
			throw new Exception( 'Báo cáo này không phải của bạn' );
		}
		/* 🔴 CHỈ XOÁ ĐƯỢC BẢN CÒN NHÁP. `CAN_SUA` là báo cáo kế toán đã xem và trả về — xoá nó
		   là xoá luôn cả lý do trả về và dấu vết của lượt soát ấy. */
		if ( VHJP_BaoCao::TT_NHAP !== VHJP_Doc::str( $r['status'] ) ) {
			throw new Exception( 'Chỉ xoá được báo cáo CÒN NHÁP. Báo cáo này đang: '
				. VHJP_Doc::str( $r['status'] ) );
		}
		$n = 0;
		foreach ( VHJP_Nguon::tim( 'JP_Rows', 'reportId', $ma_bc ) as $x ) {
			VHJP_Nguon::xoa( 'JP_Rows', $x['id'] ); $n++;
		}
		foreach ( VHJP_Nguon::tim( 'JP_Zones', 'reportId', $ma_bc ) as $x ) {
			VHJP_Nguon::xoa( 'JP_Zones', $x['id'] );
		}
		foreach ( VHJP_Nguon::tim( 'JP_Photos', 'reportId', $ma_bc ) as $x ) {
			try { VHJP_Anh::xoa( $u, $x['id'] ); } catch ( Throwable $e ) { /* mất rồi thì thôi */ }
		}
		VHJP_Nguon::xoa( 'JP_Reports', $ma_bc );
		VHJP_NhatKy::ghi( $u, 'XOA_NHAP', $ma_bc, '', $n . ' dòng' );
		return array( 'ok' => true, 'msg' => 'Đã xoá bản nháp và ' . $n . ' dòng của nó.' );
	}

	/**
	 * `jpTinhLaiCanhBao` — dựng lại danh sách cảnh báo của một báo cáo.
	 *
	 * ⚠️ CHỈ ĐỔI SỐ ĐẾM CẢNH BÁO. Không đụng tiền, không đụng hàng, không đụng chữ ký hay
	 *    trạng thái duyệt — giao diện đã hứa với kế toán đúng câu ấy trước khi họ bấm.
	 */
	public static function tinh_lai_canh_bao( $u, $ma_bc ) {
		self::can_kt( $u );
		$ma_bc = VHJP_Doc::str( $ma_bc );
		$head  = VHJP_Nguon::tim_mot( 'JP_Reports', 'id', $ma_bc );
		if ( ! $head ) { throw new Exception( 'Không tìm thấy báo cáo' ); }

		$loai = VHJP_Doc::str( $head['machineType'] );
		$dong = array();
		foreach ( VHJP_Nguon::tim( 'JP_Rows', 'reportId', $ma_bc ) as $r ) {
			$dong[] = VHJP_Tinh::dong( $r, $loai );
		}
		$moi = VHJP_Tinh::bao_cao( $head, $dong );
		$cu  = self::so( $head['warnCount'] );
		VHJP_Nguon::sua( 'JP_Reports', $ma_bc, array( 'warnCount' => $moi['warnCount'] ) );
		VHJP_NhatKy::ghi( $u, 'TINH_LAI_CANH_BAO', $ma_bc, '', $cu . ' → ' . $moi['warnCount'] );
		return array( 'ok' => true, 'cu' => $cu, 'moi' => self::so( $moi['warnCount'] ),
			'msg' => 'Đã tính lại cảnh báo: ' . $cu . ' → ' . self::so( $moi['warnCount'] )
				. '. Không đụng tiền, hàng, chữ ký hay trạng thái duyệt.' );
	}

	/**
	 * `jpSapXepLaiKy` — sắp lại kỳ của một cơ sở theo THỨ TỰ CHỈ SỐ ĐỒNG HỒ.
	 *
	 * 🔴 SẮP THEO CHỈ SỐ, KHÔNG THEO NGÀY TẠO. Đồng hồ chỉ chạy tiến, nên chỉ số đầu kỳ là thứ
	 *    tự THẬT của các kỳ; ngày tạo thì phụ thuộc lúc nhân viên ngồi gõ.
	 *
	 * ⚠️ HAI BƯỚC: `$ghi = false` chỉ xem trước. Đây là sửa ngày của báo cáo đã vào sổ theo ngày
	 *    cuối kỳ — một nút bấm nhầm là cả loạt kỳ nhảy chỗ.
	 */
	public static function sap_xep_ky( $u, $ma_coso, $loai_may = '', $ghi = false ) {
		self::can_kt( $u );
		$cs   = VHJP_Doc::str( $ma_coso );
		if ( '' === $cs ) { throw new Exception( 'Chọn cơ sở' ); }
		$loai = VHJP_Doc::str( $loai_may );
		$ghi  = (bool) $ghi;

		$ds = array();
		foreach ( VHJP_Nguon::doc( 'JP_Reports' ) as $r ) {
			if ( VHJP_Doc::str( $r['locationId'] ) !== $cs ) { continue; }
			if ( '' !== $loai && VHJP_Doc::str( $r['machineType'] ) !== $loai ) { continue; }
			if ( VHJP_BaoCao::TT_NHAP === VHJP_Doc::str( $r['status'] ) ) { continue; }
			$ds[] = $r;
		}
		if ( count( $ds ) < 2 ) {
			return array( 'ok' => true, 'dong' => array(),
				'msg' => 'Cơ sở này có ít hơn hai báo cáo — không có gì để sắp.' );
		}

		/* Chỉ số đầu kỳ = tổng `mBefore` của mọi dòng máy tiền trong báo cáo. */
		$chi_so = array();
		foreach ( $ds as $r ) {
			$t = 0;
			foreach ( VHJP_Nguon::tim( 'JP_Rows', 'reportId', VHJP_Doc::str( $r['id'] ) ) as $x ) {
				$t += self::so( $x['mBefore'] );
			}
			$chi_so[ VHJP_Doc::str( $r['id'] ) ] = $t;
		}
		usort( $ds, function ( $a, $b ) use ( $chi_so ) {
			$x = $chi_so[ VHJP_Doc::str( $a['id'] ) ] - $chi_so[ VHJP_Doc::str( $b['id'] ) ];
			if ( 0 !== $x ) { return $x > 0 ? 1 : -1; }
			return strcmp( VHJP_Doc::ngay( $a['fromDate'] ), VHJP_Doc::ngay( $b['fromDate'] ) );
		} );

		/* Giữ nguyên TẬP ngày đang có, chỉ gán lại cho đúng thứ tự chỉ số — không bịa ngày mới. */
		$ky = array();
		foreach ( $ds as $r ) {
			$ky[] = array( VHJP_Doc::ngay( $r['fromDate'] ), VHJP_Doc::ngay( $r['toDate'] ) );
		}
		usort( $ky, function ( $a, $b ) { return strcmp( $a[0], $b[0] ); } );

		$dong = array(); $doi = 0;
		foreach ( $ds as $i => $r ) {
			$id  = VHJP_Doc::str( $r['id'] );
			$cu  = array( VHJP_Doc::ngay( $r['fromDate'] ), VHJP_Doc::ngay( $r['toDate'] ) );
			$moi = $ky[ $i ];
			$khac = ( $cu[0] !== $moi[0] || $cu[1] !== $moi[1] );
			if ( $khac ) { $doi++; }
			if ( $ghi && $khac ) {
				VHJP_Nguon::sua( 'JP_Reports', $id,
					array( 'fromDate' => $moi[0], 'toDate' => $moi[1] ) );
			}
			$dong[] = array( 'id' => $id, 'chiSoDau' => $chi_so[ $id ],
				'cuTu' => VHJP_Doc::dmy( $cu[0] ), 'cuDen' => VHJP_Doc::dmy( $cu[1] ),
				'moiTu' => VHJP_Doc::dmy( $moi[0] ), 'moiDen' => VHJP_Doc::dmy( $moi[1] ),
				'doi' => $khac, 'rev' => self::so( $r['revMeter'] ) );
		}
		if ( $ghi && $doi ) { VHJP_NhatKy::ghi( $u, 'SAP_XEP_KY', '', $cs, $doi . ' báo cáo' ); }
		return array( 'ok' => true, 'dong' => $dong, 'soDoi' => $doi,
			'msg' => 0 === $doi
				? 'Các kỳ đã đúng thứ tự chỉ số đồng hồ — không cần đổi gì.'
				: ( $ghi ? 'Đã sắp lại ' . $doi . ' báo cáo theo thứ tự chỉ số đồng hồ.'
					: 'Xem trước: ' . $doi . ' báo cáo sẽ đổi kỳ. Chưa ghi gì.' ) );
	}

	/**
	 * `jpKiemTraNhanh` — chạy lại công thức tiền bằng số thật, trả về VĂN BẢN THUẦN.
	 *
	 * ⚠️ Trả chuỗi, không trả mảng: giao diện in thẳng vào một `<pre>` và bắt chữ "SAI/LỆCH" để
	 *    tô màu. Đổi sang JSON là phải sửa giao diện, mà giao diện thì cố ý giữ nguyên văn.
	 */
	/**
	 * Tính một báo cáo GIẢ đúng hai bước như đường thật: từng dòng qua `dong()`, rồi `bao_cao()`.
	 *
	 * ⚠️ Một hàm dùng chung cho mọi phép của bài tự kiểm, để không có chỗ nào lỡ bỏ bước một.
	 */
	private static function tinh_thu( $head, $rows ) {
		$loai = VHJP_Doc::str( isset( $head['machineType'] ) ? $head['machineType'] : '' );
		$dong = array();
		foreach ( $rows as $r ) { $dong[] = VHJP_Tinh::dong( $r, $loai ); }
		return VHJP_Tinh::bao_cao( $head, $dong );
	}

	public static function kiem_tra_nhanh( $u ) {
		self::can_kt( $u );
		$out = array( 'KIỂM TRA NHANH CÔNG THỨC TIỀN — ' . VHJP_Ma::hom_nay(), '' );
		$dat = 0; $truot = 0;
		$k = function ( $ten, $co, $phai ) use ( &$out, &$dat, &$truot ) {
			if ( $co === $phai ) { $dat++; $out[] = '  ✓ ' . $ten; return; }
			$truot++;
			$out[] = '  ✗ SAI — ' . $ten . ': ra ' . $co . ', phải là ' . $phai;
		};

		/* 🔴 PHẢI QUA `dong()` TRƯỚC RỒI MỚI `bao_cao()` — đúng hai bước mà `VHJP_BaoCao::luu()`
		   đi. `bao_cao()` chỉ CỘNG ô `amount` của từng dòng chứ không tính nó; đưa thẳng dòng thô
		   vào là mọi phép ở đây cộng một đống số 0, và bài tự kiểm báo ĐỎ trên một hệ hoàn toàn
		   lành — đúng lúc kế toán bấm nó để yên tâm. */
		$h = self::tinh_thu(
			array( 'adjMachine' => 20000, 'refundCustomer' => 30000, 'machineType' => 'TIEN' ),
			array( array( 'rowKind' => 'MONEY', 'mBefore' => 0, 'mAfter' => 50,
				'giaXung' => 10000, 'bank' => 100000, 'soldQty' => 0, 'price' => 0 ) ) );
		$k( 'Doanh thu đồng hồ = 50 xung × 10.000', self::so( $h['revMeter'] ), 500000 );
		$k( 'Tiền mặt = đồng hồ − chuyển khoản + lệch máy − hoàn khách',
			self::so( $h['cashActual'] ), 500000 - 100000 + 20000 - 30000 );
		/* ⚠️ Phép này hai vế cùng suy từ `cashActual` nên nó ĐÚNG THEO CẤU TẠO — giữ lại vì nó
		   canh chỗ ghép hai ô, nhưng đừng đọc nó như một bằng chứng: lúc ba phép kia đỏ vì dòng
		   chưa được tính, một mình nó vẫn xanh. */
		$k( 'Tổng phải nộp = tiền mặt + chuyển khoản (phép tự đúng)',
			self::so( $h['totalSubmit'] ), self::so( $h['cashActual'] ) + 100000 );
		$k( 'Tổng phải nộp = đồng hồ + lệch máy − hoàn khách',
			self::so( $h['totalSubmit'] ), 500000 + 20000 - 30000 );

		$h2 = self::tinh_thu( array( 'machineType' => 'TIEN' ),
			array( array( 'rowKind' => 'COIN', 'cBefore' => 0, 'cAfter' => 10,
				'mBefore' => 0, 'mAfter' => 0, 'cash' => 0, 'bank' => 0 ) ) );
		$k( 'Dòng máy xu KHÔNG giữ hàng (soldQty ép về 0)',
			self::so( $h2['revHang'] ), 0 );

		$d = VHJP_Tinh::dong( array( 'rowKind' => 'NGOAI', 'stockOpen' => 10, 'addQty1' => 5,
			'stockOut' => 3, 'returnQty' => 2 ), 'TIEN' );
		$k( 'Hàng ngoài máy: còn lại = 10 + 5 − 3 − 2', self::so( $d['stockLeftCalc'] ), 10 );
		$k( 'Hàng ngoài máy KHÔNG sinh tiền', self::so( $d['amount'] ), 0 );

		$out[] = '';
		$out[] = $truot
			? 'KẾT QUẢ: ĐỎ — ' . $truot . ' phép LỆCH / ' . ( $dat + $truot ) . ' phép.'
			: 'KẾT QUẢ: ĐẠT — cả ' . $dat . ' phép đều đúng.';
		return implode( "\n", $out );
	}

	/* ══════════════════════════════════════════════════════════ DỰNG HỆ MỘT LẦN BẤM ══════ */

	/** `jpTinhTrangDungHeThong` — còn thiếu bước nào để hệ chạy được. */
	public static function tinh_trang_dung( $u ) {
		self::can_kt( $u );
		$so_cs   = count( VHJP_Nguon::doc( 'JP_Locations' ) );
		$so_hang = count( VHJP_Nguon::doc( 'JP_Items' ) );
		$pin     = self::pin_theo_coso( $u );
		$so_lop  = count( VHJP_Nguon::doc( 'JP_KhoLop' ) );

		$buoc = array(
			array( 'ma' => 'coso', 'ten' => 'Danh mục cơ sở', 'xong' => $so_cs > 0,
				'chiTiet' => $so_cs . ' cơ sở' ),
			array( 'ma' => 'hang', 'ten' => 'Danh mục hàng', 'xong' => $so_hang > 0,
				'chiTiet' => $so_hang . ' mã hàng' ),
			array( 'ma' => 'pin', 'ten' => 'PIN cho cơ sở', 'xong' => ! $pin['thieuPin'],
				'chiTiet' => $pin['thieuPin']
					? count( $pin['thieuPin'] ) . ' cơ sở chưa dùng được PIN'
					: 'đủ cho mọi cơ sở đang chạy' ),
			array( 'ma' => 'ton', 'ten' => 'Tồn đầu kỳ', 'xong' => $so_lop > 0,
				'chiTiet' => $so_lop . ' lớp tồn' ),
		);
		$chua = array();
		foreach ( $buoc as $b ) { if ( ! $b['xong'] ) { $chua[] = $b['ten']; } }

		/* Mấy việc KHÔNG tự làm hộ được — phải nhắc bằng tên, đừng nuốt. */
		$nhac = array();
		if ( $pin['ngungConMo'] ) {
			$nhac[] = count( $pin['ngungConMo'] ) . ' cơ sở ĐÃ NGƯNG mà tài khoản còn mở — '
				. 'PIN của họ vẫn đăng nhập được.';
		}
		foreach ( $pin['trungTen'] as $t ) {
			$nhac[] = 'Trùng mã cơ sở ' . implode( ' và ', $t['codes'] )
				. ' — hai cơ sở sẽ dùng chung một PIN.';
		}
		if ( null === self::doc_bo_du_lieu( 'ton-dau-ky-31-07' ) ) {
			$nhac[] = 'Bộ số tồn 31/07 chưa có trong bản này — khai tồn đầu kỳ bằng màn '
				. 'Kho → Tồn đầu kỳ (nhập được từ Excel).';
		}
		return array( 'ok' => true, 'sanSang' => ! $chua, 'buoc' => $buoc,
			'chuaXong' => $chua, 'nhacThem' => $nhac );
	}

	/**
	 * `jpDungHeThongMotPhat` — chạy mấy bước dựng hệ trong một lượt bấm.
	 *
	 * ⚠️ BẤM HAI LẦN KHÔNG ĐƯỢC NHÂN ĐÔI GÌ. Mỗi bước bên dưới đều tự chặn: nạp danh mục thì so
	 *    theo mã, cấp PIN thì bỏ qua cơ sở đã có tài khoản, tồn đầu kỳ thì `so_du_dau_ky` chối
	 *    kho đã khai.
	 *
	 * ⚠️ MỘT BƯỚC HỎNG KHÔNG ĐƯỢC LÀM ĐỔ CẢ LƯỢT. Mỗi bước kể kết quả của riêng nó — dừng giữa
	 *    chừng thì kế toán không biết đã tới đâu và phải đoán.
	 */
	public static function dung_he_thong( $u, $d = array() ) {
		self::can_kt( $u );
		$d       = is_array( $d ) ? $d : array();
		$cap_lai = ! empty( $d['capLai'] );

		$buoc = array();
		$chay = function ( $ten, $f ) use ( &$buoc ) {
			try { $buoc[] = array( 'ten' => $ten, 'chiTiet' => (string) $f(), 'ok' => true ); }
			catch ( Throwable $e ) {
				$buoc[] = array( 'ten' => $ten, 'chiTiet' => '⚠ ' . $e->getMessage(), 'ok' => false );
			}
		};

		$chay( 'Danh mục cơ sở', function () use ( $u ) {
			return self::nap_coso( $u )['msg']; } );
		$chay( 'Danh mục hàng', function () use ( $u ) {
			return self::nap_hang( $u )['msg']; } );
		$pin = array( 'rows' => array() );
		$chay( 'PIN cho cơ sở', function () use ( $u, $cap_lai, &$pin ) {
			$pin = self::tao_pin_coso( $u, array( 'capLai' => $cap_lai ) );
			return $pin['msg']; } );
		$chay( 'Tồn đầu kỳ 31/07', function () use ( $u ) {
			return self::nap_ton_31_7( $u )['msg']; } );

		VHJP_NhatKy::ghi( $u, 'DUNG_HE_THONG', '', '', count( $buoc ) . ' bước' );
		return array( 'ok' => true, 'buoc' => $buoc, 'pin' => $pin,
			'canhBao' => $pin['rows']
				? 'Bảng PIN bản rõ bên dưới chỉ hiện ĐÚNG MỘT LẦN — ghi ra giấy hoặc tải CSV '
					. 'trước khi rời màn này.'
				: '' );
	}
}
