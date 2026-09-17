<?php
/**
 * CỬA HÀNG TRƯỞNG LÀM VIỆC NGAY TRÊN TRẠM — duyệt đơn, xem công cơ sở, thêm người mới.
 *
 * =================================================================================================
 * VIỆC NÓ GIẢI QUYẾT
 * =================================================================================================
 * Anh Thắng 17/09/2026: *"đối với cửa hàng trưởng khi đăng nhập sẽ thấy thêm: Thêm nhân sự mới ·
 * Bảng công cơ sở quản lý · Đơn từ từ nhân viên — cơ sở mình quản lý"*.
 *
 * Cả ba việc ĐÃ CÓ ở trang quản trị, và cửa hàng trưởng đã có quyền. Thiếu là cái ĐƯỜNG: trang
 * quản trị là bảng 31 cột làm trên máy tính, còn cửa hàng trưởng thì đứng ở quầy với cái điện
 * thoại. Nên đơn xin nghỉ nộp lúc 8 giờ tối nằm đó tới sáng hôm sau — không phải vì ai lười, mà
 * vì người duyệt không có cách nào mở nó ra.
 *
 * =================================================================================================
 * 🔴 BỐN CHỐT
 * =================================================================================================
 * 1. LỚP NÀY KHÔNG TỰ CHẾ MỘT PHÉP GÁC NÀO. Mọi việc đều gọi lại đúng hàm nghiệp vụ đã có
 *    (`VHCC_XinTre::duyet`, `VHCC_XinNghi::duyet`, `VHCC_Lich::duyet`, `VHCC_Cham::bang_cham_cong`,
 *    `VHCC_NhanSu::them_nv_cua_hang`) — và mỗi hàm ấy tự chốt cơ sở từ CHÍNH BẢN GHI. Dựng một
 *    phép gác thứ hai ở đây là hai bộ luật quyền trong một plugin, và bộ thứ hai bao giờ cũng
 *    lệch trước. Cửa này chỉ làm đúng ba việc: hỏi người đang đăng nhập là ai, gom kết quả,
 *    và trả về.
 *
 * 2. 🔴 CƠ SỞ LẤY TỪ PHIÊN, KHÔNG TỪ BIỂU MẪU. Danh sách cơ sở gửi xuống màn hình chỉ là danh
 *    sách để CHỌN; lượt gửi lên vẫn phải đối chiếu lại. Tên cơ sở thì ai cũng biết, nên nhận
 *    thẳng ô người ta gõ là mở bảng công của cửa hàng khác cho bất kỳ ai gõ đúng tên.
 *
 * 3. TRẠM KHÔNG DỰNG LẠI LƯỚI 31 CỘT. Bảng công ở đây là TỔNG THEO NGƯỜI: mấy ngày, mấy giờ,
 *    mấy lượt thiếu đầu giờ. Đó là thứ đọc được trên màn hình rộng 380px, và cũng đúng là thứ
 *    cửa hàng trưởng cần biết giữa ca. Ai cần từng ô từng ngày thì mở trang quản trị — nhét
 *    một lưới cuộn ngang vào điện thoại là bày ra thứ không ai đọc nổi rồi gọi là xong việc.
 *
 * 4. ⚠️ THÊM NGƯỜI MỚI LÀ CỬA HẸP `them_nv_cua_hang`, KHÔNG PHẢI `luu_ho_so`. Nó mở một hồ sơ
 *    TẠM (mã `TAM-…`) cho người vừa vào làm, và KHÔNG đụng tới lương, số tài khoản, vai trò hay
 *    mã chuẩn — mấy thứ đổi một cái là ra tiền hoặc ra quyền. Anh Thắng chọn đúng mức này
 *    17/09/2026 khi em hỏi cho gõ tới đâu.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_CuaHang {

	/** Bậc tối thiểu để thấy tab — Cửa hàng trưởng. Bậc trên cũng qua, đúng thang vai trò. */
	const QUYEN = 'cong_coso';

	/** Nhiều nhất bấy nhiêu đơn mỗi loại trong một lượt trả — màn điện thoại, không phải sổ. */
	const DON_TOI_DA = 60;

	public static function duoc( $u ) {
		return VHCC_Vai::duoc( $u, self::QUYEN );
	}

	/**
	 * Cơ sở người này phụ trách.
	 *
	 * ⚠️ DÙNG `ds_coso_cua()` — CÙNG MỘT NGUỒN với `co_quyen_coso()`. Dựng danh sách riêng ở đây
	 *    là hai danh sách, và cái lệch sẽ là một cơ sở hiện trong ô chọn nhưng mọi lượt gửi lên
	 *    đều bị chối: người dùng thấy tên cửa hàng mình mà bấm vào thì báo "không có quyền".
	 */
	public static function ds_coso( $u ) {
		return VHCC_NhanSu::ds_coso_cua( $u );
	}

	/** Cơ sở gửi lên có thật là của người này không. Trả tên đã chuẩn hoá, hoặc '' nếu không. */
	private static function chot_coso( $u, $coso ) {
		$x = VHCC_NhanSu::chuan_coso( $coso );
		if ( '' === $x ) {
			$ds = self::ds_coso( $u );
			$x  = $ds ? $ds[0] : '';
		}
		if ( '' === $x || ! VHCC_NhanSu::co_quyen_coso( $u, $x ) ) { return ''; }
		return $x;
	}

	/* ====================================================================== đơn từ */

	/** Ba loại đơn nhân viên nộp trên trạm, gom về một hộp. */
	const T_TRE  = 'tre';
	const T_NGHI = 'nghi';
	const T_LICH = 'lich';

	/**
	 * Đơn ĐANG CHỜ của một cơ sở, cả ba loại, xếp chung một danh sách.
	 *
	 * 🔴 GỘP BA LOẠI VÀO MỘT HỘP, KHÔNG BA TAB. Người duyệt không nghĩ theo "loại đơn" — họ nghĩ
	 *    theo "hôm nay còn ai chờ mình". Ba tab là ba chỗ phải nhớ mở, và cái quên mở là cái
	 *    nằm đó cả tuần.
	 */
	public static function don_cho( $u, $coso ) {
		$cs = self::chot_coso( $u, $coso );
		if ( '' === $cs ) { return array( 'ok' => false, 'error' => 'Không có quyền cơ sở này.' ); }

		$ds = array();
		foreach ( VHCC_XinTre::cho_duyet( $cs, self::DON_TOI_DA ) as $d ) {
			$ds[] = array(
				'loai'   => self::T_TRE,
				'tenLoai' => 'Xin đi trễ',
				'id'     => (string) $d['id'],
				'maNV'   => (string) $d['ma_nv'],
				'hoTen'  => (string) $d['ho_ten'],
				'ngay'   => (string) $d['ngay'],
				'chiTiet' => 'Trễ ' . (int) $d['so_phut'] . ' phút',
				'lyDo'   => (string) $d['ly_do'],
			);
		}
		foreach ( VHCC_XinNghi::cho_duyet( $cs, self::DON_TOI_DA ) as $d ) {
			$ten_l = isset( VHCC_XinNghi::TEN_LOAI[ $d['loai'] ] )
				? VHCC_XinNghi::TEN_LOAI[ $d['loai'] ] : (string) $d['loai'];
			$ds[] = array(
				'loai'   => self::T_NGHI,
				'tenLoai' => 'Xin nghỉ',
				'id'     => (string) $d['id'],
				'maNV'   => (string) $d['ma_nv'],
				'hoTen'  => (string) $d['ho_ten'],
				'ngay'   => (string) $d['tu_ngay'],
				'chiTiet' => $ten_l . ' · ' . (float) $d['so_ngay'] . ' ngày'
					. ( $d['den_ngay'] !== $d['tu_ngay'] ? ' (tới ' . $d['den_ngay'] . ')' : '' ),
				'lyDo'   => (string) $d['ly_do'],
			);
		}
		/* ⚠️ ĐỔI LỊCH DÙNG `ma_yc` LÀM KHOÁ, KHÔNG PHẢI `id` SỐ. Ép nó về số là duyệt nhầm đơn
		   khác — `VHCC_Lich::duyet()` tra theo `ma_yc`. Nên khoá ở đây là CHUỖI cho cả ba loại,
		   và nơi nào cần số thì tự ép, chứ không ép ngược lại. */
		foreach ( VHCC_Lich::ds_doi_lich( $u, true ) as $d ) {
			if ( 0 !== strcasecmp( VHCC_NhanSu::chuan_coso( $d['coso'] ), $cs ) ) { continue; }
			$ds[] = array(
				'loai'   => self::T_LICH,
				'tenLoai' => 'Đổi lịch',
				'id'     => (string) $d['ma_yc'],
				'maNV'   => (string) $d['ma_nv'],
				'hoTen'  => (string) $d['ho_ten'],
				'ngay'   => (string) $d['ngay'],
				'chiTiet' => trim( (string) $d['ca'] . ' → ' . (string) $d['viec_moi']
					. ( ! empty( $d['doi_sang_ngay'] ) ? ' · dời sang ' . $d['doi_sang_ngay'] : '' ) ),
				'lyDo'   => (string) $d['ly_do'],
			);
		}

		/* Xếp theo NGÀY XIN, gần nhất trước — đơn cho ngày mai cần quyết trước đơn cho tháng sau. */
		usort( $ds, function ( $a, $b ) {
			$c = strcmp( (string) $a['ngay'], (string) $b['ngay'] );
			return ( 0 !== $c ) ? $c : strcmp( (string) $a['hoTen'], (string) $b['hoTen'] );
		} );
		return array( 'ok' => true, 'coSo' => $cs, 'don' => $ds, 'so' => count( $ds ) );
	}

	/**
	 * Duyệt / không duyệt MỘT đơn.
	 *
	 * 🔴 CHỈ CHỌN HÀM THEO LOẠI, KHÔNG TỰ QUYẾT GÌ. Chốt cơ sở nằm trong từng hàm kia và đọc từ
	 *    CHÍNH BẢN GHI — xem chốt 1 đầu lớp.
	 */
	public static function duyet( $u, $loai, $id, $dong_y, $ly_do = '' ) {
		if ( ! self::duoc( $u ) ) {
			return array( 'ok' => false, 'error' => VHCC_Vai::loi( $u, self::QUYEN, 'Duyệt đơn' ) );
		}
		$dong_y = (bool) $dong_y;
		if ( self::T_TRE === $loai ) {
			return VHCC_XinTre::duyet( $u, (int) $id,
				$dong_y ? VHCC_XinTre::DUYET : VHCC_XinTre::TU_CHOI, $ly_do );
		}
		if ( self::T_NGHI === $loai ) {
			return VHCC_XinNghi::duyet( $u, (int) $id,
				$dong_y ? VHCC_XinNghi::DUYET : VHCC_XinNghi::TU_CHOI, $ly_do );
		}
		if ( self::T_LICH === $loai ) {
			return VHCC_Lich::duyet( $u, (string) $id, $dong_y );
		}
		return array( 'ok' => false, 'error' => 'Loại đơn không rõ.' );
	}

	/* ====================================================================== bảng công */

	/**
	 * TỔNG CÔNG THEO NGƯỜI của một cơ sở, một tháng — xem chốt 3 đầu lớp.
	 *
	 * ⚠️ PHÚT CỘNG TỪ `bang_cham_cong()`, KHÔNG TỰ TRỪ NGHỈ LẠI. Hàm ấy đã trừ khoảng nghỉ giữa
	 *    ca và đã xử ca vắt nửa đêm (`phut_lam`). Tính lại ở đây là engine thứ hai, và hai engine
	 *    thì một ngày nào đó nói hai con số khác nhau cho cùng một ngày.
	 */
	public static function cong_coso( $u, $coso, $thang ) {
		$cs = self::chot_coso( $u, $coso );
		if ( '' === $cs ) { return array( 'ok' => false, 'error' => 'Không có quyền cơ sở này.' ); }
		$b = VHCC_Cham::bang_cham_cong( $u, $cs, $thang );
		if ( empty( $b['ok'] ) ) { return $b; }

		$gom = array();
		foreach ( $b['hang'] as $h ) {
			$ma = trim( (string) $h['maNV'] );
			if ( '' === $ma ) { continue; }
			$k = strtolower( $ma );
			if ( ! isset( $gom[ $k ] ) ) {
				$gom[ $k ] = array( 'maNV' => $ma, 'hoTen' => (string) $h['hoTen'],
					'phut' => 0.0, 'ngay' => array(), 'thieu' => 0 );
			}
			/* Hàng nào có tên thì lấy — hàng cũ trong sổ có thể để trống `ho_ten`. */
			if ( '' !== trim( (string) $h['hoTen'] ) ) { $gom[ $k ]['hoTen'] = (string) $h['hoTen']; }
			$gom[ $k ]['ngay'][ (string) $h['ngay'] ] = true;
			/* 🔴 THIẾU MỘT ĐẦU GIỜ THÌ KHÔNG CỘNG PHÚT NÀO, VÀ ĐẾM RIÊNG. Coi như 0 là trừ công
			   một người vì cái máy lỗi; đoán một ca chuẩn là cấp giờ không ai làm. Cùng luật với
			   `VHCC_BangLuong::dung()`. */
			if ( null === $h['vaoGiay'] || '' === $h['vaoGiay']
				|| null === $h['raGiay'] || '' === $h['raGiay'] ) {
				$gom[ $k ]['thieu']++;
				continue;
			}
			$gom[ $k ]['phut'] += (float) $h['phut'];
		}

		$dong = array();
		foreach ( $gom as $g ) {
			$dong[] = array(
				'maNV'  => $g['maNV'],
				'hoTen' => ( '' !== trim( $g['hoTen'] ) ) ? $g['hoTen'] : $g['maNV'],
				'soNgay' => count( $g['ngay'] ),
				'gio'   => round( $g['phut'] / 60, 2 ),
				'thieu' => (int) $g['thieu'],
			);
		}
		usort( $dong, function ( $a, $b2 ) { return strcmp( $a['hoTen'], $b2['hoTen'] ); } );

		$t_gio = 0.0; $t_thieu = 0;
		foreach ( $dong as $d ) { $t_gio += $d['gio']; $t_thieu += $d['thieu']; }
		return array( 'ok' => true, 'coSo' => $cs, 'thang' => $b['thang'], 'dong' => $dong,
			'soNguoi' => count( $dong ), 'tongGio' => round( $t_gio, 2 ), 'tongThieu' => $t_thieu );
	}

	/* ====================================================================== thêm người */

	/**
	 * Thêm người mới vào cơ sở mình — cửa hẹp, hồ sơ TẠM. Xem chốt 4 đầu lớp.
	 *
	 * ⚠️ KHÔNG NHẬN `chuc_vu` TỪ MÀN TRẠM ở bản này. Chức vụ là thứ tra ra ĐƠN GIÁ GIỜ
	 *    (`VHCC_GiaGio::tra`), nên một chữ gõ vội trên điện thoại ("nv" thay vì "Nhân viên") là
	 *    một dòng lương ra 0đ mà bảng vẫn đầy số. Để trống thì bảng lương nói thẳng "chưa khai
	 *    đơn giá" và có người sửa; gõ sai thì không ai thấy.
	 */
	public static function them_nguoi( $u, $dat ) {
		$cs = self::chot_coso( $u, isset( $dat['coSo'] ) ? $dat['coSo'] : '' );
		if ( '' === $cs ) { return array( 'ok' => false, 'error' => 'Không có quyền cơ sở này.' ); }
		return VHCC_NhanSu::them_nv_cua_hang( $u, array(
			'ho_ten'   => isset( $dat['hoTen'] ) ? $dat['hoTen'] : '',
			'cccd'     => isset( $dat['cccd'] ) ? $dat['cccd'] : '',
			'sdt'      => isset( $dat['sdt'] ) ? $dat['sdt'] : '',
			'gioi_tinh' => isset( $dat['gioiTinh'] ) ? $dat['gioiTinh'] : '',
			'cua_hang' => $cs,
		) );
	}
}
