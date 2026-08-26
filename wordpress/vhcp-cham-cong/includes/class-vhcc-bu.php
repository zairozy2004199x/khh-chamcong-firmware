<?php
/**
 * CHẤM CÔNG BÙ — cửa ghi giờ THỨ BA, và là cửa duy nhất có người đứng sau.
 *
 * Anh Thắng chốt mô hình năm bậc (25/08/2026): *"Cửa hàng trưởng (chấm công bù nhân viên, check
 * công, chấm công online của mình và lên lịch làm cho cửa hàng...)"*.
 *
 * ⚠️ ĐÂY LÀ MỘT NGOẠI LỆ CÓ CHỦ Ý, KHÔNG PHẢI NỚI LUẬT.
 *    `VHCC_Cham` mở đầu bằng câu: *"Sửa giờ chấm công chỉ có đúng hai đường: cổng nhận từ máy và
 *    chấm công online. Mở thêm đường thứ ba để 'sửa cho nhanh' là mở đường sửa lương bằng tay mà
 *    không có dấu vết."* Câu ấy vẫn đúng, và lớp này chính là đường thứ ba đó — nên nó phải trả
 *    đủ cái giá mà câu ấy đòi: **dấu vết**. Mỗi lượt bù đều ghi vào bảng `cham_bu` (ai bù · cho
 *    ai · ngày nào · giờ gì · vì sao · lúc nào), và bảng đó KHÔNG có đường xoá.
 *
 * 🔴 BÙ CHỈ ĐIỀN Ô TRỐNG, KHÔNG BAO GIỜ ĐÈ LÊN GIỜ ĐÃ CÓ.
 *    Ô đã có giờ là giờ máy hoặc trạm online ghi — tức là có người thật đứng trước máy thật vào
 *    lúc thật. Cho bù đè lên là biến "sổ ghi máy" thành "sổ ghi tay", và từ đó không câu nào
 *    trong hệ thống còn phân biệt được hai thứ nữa. Cần sửa một giờ ĐÃ CÓ thì gắn cờ để cấp trên
 *    tra, chứ không sửa đè.
 *
 * 🔴 KHÔNG AI BÙ ĐƯỢC CHO CHÍNH MÌNH, kể cả Admin.
 *    Bù công là việc đổi thẳng ra tiền. Tự bù cho mình là tự ký duyệt tiền của mình — chốt này
 *    không phải vì nghi ai, mà vì một hệ thống để hở chỗ đó thì người ngay thẳng cũng không có gì
 *    chứng minh là mình ngay thẳng. Ai quên bấm thì nhờ người khác bù, mất mười giây.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_Bu {

	/**
	 * Bù xa nhất bao nhiêu ngày về trước.
	 *
	 * Không phải con số tuỳ tiện: lương chốt theo tháng, nên cửa sổ phải phủ trọn tháng trước
	 * (để ngày 1 vẫn bù được cho ngày 1 tháng trước) mà không mở rộng thành "bù ngày nào cũng
	 * được". Bù vào một tháng đã trả lương xong là sổ công và sổ tiền lệch nhau, mà không có gì
	 * báo — chỗ lệch ấy chỉ lộ ra khi có người khiếu nại.
	 */
	const NGAY_TOI_DA = 62;

	/** Nhãn `nguon` của lượt do người bù. Đứng cạnh 'may' và 'online' trong cùng một cột. */
	const NGUON = 'bu';

	/* ===================================================================== gác cửa */

	/**
	 * Người này có được bù cho mã kia, ở cơ sở kia không? Trả '' nếu được, hoặc câu từ chối.
	 *
	 * Tách riêng khỏi `ghi()` vì màn hình cần hỏi TRƯỚC (để ẩn ô nhập) còn `ghi()` phải hỏi LẠI
	 * lúc ghi — ẩn cái ô không phải là gác cửa, người ta dựng form ở đâu cũng gửi lên được.
	 */
	public static function vi_sao_khong_duoc( $u, $coso, $ma_nv ) {
		if ( ! VHCC_Vai::duoc( $u, 'cham_bu' ) ) {
			return 'Chấm công bù cần quyền Cửa hàng trưởng trở lên.';
		}
		$coso = VHCC_NhanSu::chuan_coso( $coso );
		if ( '' === $coso ) { return 'Chưa chọn cơ sở.'; }
		if ( ! VHCC_NhanSu::co_quyen_coso( $u, $coso ) ) {
			return 'Không có quyền cơ sở này.';
		}
		$ma_nv = trim( (string) $ma_nv );
		if ( '' === $ma_nv ) { return 'Chưa nhập mã nhân viên.'; }

		/* Bù cho chính mình — xem lý do ở đầu tệp. So bằng mã nhân viên chứ không bằng tên: tên
		   trùng nhau đầy, còn mã thì không. */
		$ma_toi = trim( (string) ( isset( $u['ma_nv'] ) ? $u['ma_nv'] : '' ) );
		if ( '' !== $ma_toi && 0 === strcasecmp( $ma_toi, self::ma_goc( $ma_nv ) ) ) {
			return 'Không tự bù công cho mình được — kể cả Admin. Nhờ người khác bù giúp.';
		}

		/* Mã phải có hồ sơ thật. Bù cho một mã không tồn tại là tạo ra công của một người không
		   có, và cái công ấy sẽ đi thẳng vào bảng lương. */
		$hs = VHCC_NhanSu::ho_so( self::ma_goc( $ma_nv ) );
		if ( ! $hs ) {
			return 'Không thấy hồ sơ của mã "' . $ma_nv . '". Khai hồ sơ trước rồi mới bù được.';
		}
		return '';
	}

	/** Mã gốc (bỏ hậu tố -CD/-TC/…) — hồ sơ nhân sự chỉ lưu mã gốc. */
	private static function ma_goc( $ma ) {
		list( $goc, ) = VHCC_Nhan::tach_hau_to( $ma );
		return $goc;
	}

	/* ===================================================================== ghi */

	/**
	 * Bù giờ cho một ngày.
	 *
	 * @param array  $u      người đang bù.
	 * @param array  $dat    coso · ngay · ma_nv (có thể kèm hậu tố) · vao 'HH:mm' · ra 'HH:mm' · ly_do
	 * @return array ok/error, kèm `da_ghi` liệt kê ô nào thật sự được điền.
	 */
	public static function ghi( $u, $dat ) {
		$coso  = VHCC_NhanSu::chuan_coso( isset( $dat['coso'] ) ? $dat['coso'] : '' );
		$ma_nv = trim( (string) ( isset( $dat['ma_nv'] ) ? $dat['ma_nv'] : '' ) );

		$chan = self::vi_sao_khong_duoc( $u, $coso, $ma_nv );
		if ( '' !== $chan ) { return array( 'ok' => false, 'error' => $chan ); }

		$ngay = trim( (string) ( isset( $dat['ngay'] ) ? $dat['ngay'] : '' ) );
		$loi  = self::ngay_hop_le( $ngay );
		if ( '' !== $loi ) { return array( 'ok' => false, 'error' => $loi ); }

		/* Lý do là thứ duy nhất còn lại sau ba tháng. Không có nó thì bảng nhật ký chỉ nói "có
		   người bù giờ này", mà câu đó thì nhìn cột `nguon` cũng biết. */
		$ly_do = trim( (string) ( isset( $dat['ly_do'] ) ? $dat['ly_do'] : '' ) );
		if ( mb_strlen( $ly_do, 'UTF-8' ) < 5 ) {
			return array( 'ok' => false,
				'error' => 'Ghi rõ vì sao phải bù (ít nhất 5 ký tự) — VD: "máy hỏng sáng 12/8", '
					. '"quên bấm lúc về, có camera".' );
		}

		$vao = self::giay( isset( $dat['vao'] ) ? $dat['vao'] : '' );
		$ra  = self::giay( isset( $dat['ra'] ) ? $dat['ra'] : '' );
		if ( null === $vao && null === $ra ) {
			return array( 'ok' => false, 'error' => 'Chưa nhập giờ nào để bù.' );
		}
		if ( null !== $vao && null !== $ra && $ra <= $vao ) {
			return array( 'ok' => false,
				'error' => 'Giờ ra phải muộn hơn giờ vào. Ca đêm thì bù vào hàng ca đêm (mã kèm -CD).' );
		}

		/* Ô nào ĐÃ có giờ thì bỏ qua ô đó — không đè. Đọc trước khi ghi để còn nói cho người bù
		   biết ô nào bị bỏ qua và vì sao; ghi rồi mới báo thì họ tưởng đã bù xong cả hai. */
		$cu = self::hang( $coso, $ngay, $ma_nv );
		$da_co_vao = ( $cu && null !== $cu['gio_vao_giay'] && '' !== $cu['gio_vao_giay'] );
		$da_co_ra  = ( $cu && null !== $cu['gio_ra_giay'] && '' !== $cu['gio_ra_giay'] );

		$bo_qua = array();
		if ( null !== $vao && $da_co_vao ) {
			$bo_qua[] = 'giờ vào (đã có ' . VHCC_DB::hhmm( (int) $cu['gio_vao_giay'] ) . ')';
			$vao = null;
		}
		if ( null !== $ra && $da_co_ra ) {
			$bo_qua[] = 'giờ ra (đã có ' . VHCC_DB::hhmm( (int) $cu['gio_ra_giay'] ) . ')';
			$ra = null;
		}
		if ( null === $vao && null === $ra ) {
			return array( 'ok' => false,
				'error' => 'Ngày này đã có đủ giờ rồi — bù không đè lên giờ máy đã ghi. '
					. 'Thấy giờ sai thì gắn cờ để cấp trên tra.' );
		}

		/* Điền giờ vào TRƯỚC rồi mới giờ ra: `quyet_dinh_gio` xét theo cặp đang có, nên nạp giờ
		   ra vào một hàng còn trống giờ vào thì nó thành GIỜ VÀO, không phải giờ ra. */
		$ho_ten = self::ho_ten( $ma_nv );
		$da_ghi = array();
		foreach ( array( 'vao' => $vao, 'ra' => $ra ) as $o => $giay ) {
			if ( null === $giay ) { continue; }
			$kq = VHCC_Nhan::ghi_gio( $coso, $ngay, $ma_nv, $ho_ten, $giay, '', self::NGUON,
				'Bù: ' . $ly_do );
			if ( isset( $kq['loi'] ) ) { return array( 'ok' => false, 'error' => $kq['loi'] ); }
			$da_ghi[ $o ] = VHCC_DB::hhmm( $giay );
			self::nhat_ky( $u, $coso, $ngay, $ma_nv, $o, $giay, $ly_do );
		}

		return array( 'ok' => true, 'coSo' => $coso, 'ngay' => $ngay, 'maNV' => $ma_nv,
			'daGhi' => $da_ghi, 'boQua' => $bo_qua );
	}

	/* ===================================================================== sửa đè */

	/**
	 * SỬA ĐÈ giờ đã có — cửa thứ tư, và là cửa duy nhất xoá được thứ máy đã ghi.
	 *
	 * ════════════════════════════════════════════════════════════════════════════════════════
	 * 🔴 ĐÂY LÀ VIỆC MÀ CẢ TỆP NÀY VIẾT RA ĐỂ NGĂN.
	 *
	 *    Đầu tệp ghi: *"Bù chỉ điền ô trống, không bao giờ đè lên giờ đã có… Cần sửa một giờ ĐÃ
	 *    CÓ thì gắn cờ để cấp trên tra, chứ không sửa đè."* Câu ấy vẫn đúng cho **bù**. Nhưng
	 *    "cấp trên tra" xong thì phải có đường sửa, không thì cái cờ treo đó mãi — anh Thắng
	 *    26/08/2026: *"admin có quyền chỉnh sửa lại giờ công cho nhân viên"*. Đây là đường đó.
	 *
	 *    Giá phải trả, và trả đủ:
	 *      · quyền `sua_gio` — bậc **Admin**, cao hơn cả `nap_cong` (Quản lý). Bù và nạp chỉ
	 *        THÊM vào ô trống; việc này XOÁ MẤT bằng chứng gốc.
	 *      · lý do bắt buộc, y như bù.
	 *      · nhật ký ghi **CŨ -> MỚI** cho từng ô, vào cùng bảng `cham_bu` không có đường xoá.
	 *      · hàng bị sửa đổi `nguon` thành `'sua'`, nên phép đối chiếu thôi đếm nó là lượt máy.
	 *
	 * ⚠️ VẪN KHÔNG AI SỬA ĐƯỢC CHO CHÍNH MÌNH, kể cả Admin — dùng chung `vi_sao_khong_duoc()`
	 *    với bù. Sửa giờ công của mình là tự ký duyệt tiền của mình, và ở đây còn nặng hơn bù:
	 *    bù thì chỉ thêm được vào ô trống, sửa thì viết lại được cả ngày.
	 *
	 * @param array $dat coso · ngay · ma_nv · vao 'HH:mm' (rỗng = xoá ô) · ra · ly_do
	 *                   Kèm `xoa_vao` / `xoa_ra` = '1' để nói rõ "cố ý xoá trắng ô này".
	 */
	public static function sua( $u, $dat ) {
		$coso  = VHCC_NhanSu::chuan_coso( isset( $dat['coso'] ) ? $dat['coso'] : '' );
		$ma_nv = trim( (string) ( isset( $dat['ma_nv'] ) ? $dat['ma_nv'] : '' ) );

		/* 🔴 Gác quyền RIÊNG, gác TRƯỚC. `vi_sao_khong_duoc()` chỉ đòi bậc Cửa hàng trưởng —
		   gọi mỗi nó là mở việc sửa đè cho cả Cửa hàng trưởng, tức là mỗi cửa hàng có một người
		   viết lại được bảng công của chính cửa hàng mình. */
		if ( ! VHCC_Vai::duoc( $u, 'sua_gio' ) ) {
			return array( 'ok' => false,
				'error' => 'Sửa giờ đã có cần quyền Admin. Cửa hàng trưởng chỉ bù được vào ô còn '
					. 'trống; thấy giờ sai thì gắn cờ để Admin sửa.' );
		}
		$chan = self::vi_sao_khong_duoc( $u, $coso, $ma_nv );
		if ( '' !== $chan ) { return array( 'ok' => false, 'error' => $chan ); }

		$ngay = trim( (string) ( isset( $dat['ngay'] ) ? $dat['ngay'] : '' ) );
		$loi  = self::ngay_hop_le( $ngay );
		if ( '' !== $loi ) { return array( 'ok' => false, 'error' => $loi ); }

		$ly_do = trim( (string) ( isset( $dat['ly_do'] ) ? $dat['ly_do'] : '' ) );
		if ( mb_strlen( $ly_do, 'UTF-8' ) < 5 ) {
			return array( 'ok' => false,
				'error' => 'Ghi rõ vì sao phải sửa (ít nhất 5 ký tự) — VD: "máy lệch giờ 2 tiếng '
					. 'ngày 12/8, đối chiếu camera".' );
		}

		$cu = self::hang( $coso, $ngay, $ma_nv );
		if ( ! $cu ) {
			return array( 'ok' => false,
				'error' => 'Ngày này chưa có dòng chấm công nào để sửa. Chưa có giờ thì dùng '
					. '"Chấm công bù" ở khối trên.' );
		}
		$vao_cu = ( null !== $cu['gio_vao_giay'] && '' !== $cu['gio_vao_giay'] ) ? (int) $cu['gio_vao_giay'] : null;
		$ra_cu  = ( null !== $cu['gio_ra_giay'] && '' !== $cu['gio_ra_giay'] ) ? (int) $cu['gio_ra_giay'] : null;

		/* 🔴 Ô ĐỂ TRỐNG NGHĨA LÀ "GIỮ NGUYÊN", KHÔNG PHẢI "XOÁ".
		   Người sửa giờ ra mà không gõ lại giờ vào là chuyện thường. Hiểu ô trống thành xoá là
		   mỗi lượt sửa một ô lại âm thầm xoá ô kia — mất giờ công mà không ai bấm nút xoá nào.
		   Muốn xoá thì phải TÍCH Ô "xoá trắng", tức là một hành động riêng, cố ý. */
		/* ⚠️ Gõ SAI dạng cũng phải báo lỗi, KHÔNG được lặng lẽ thành xoá trắng. `giay()` trả
		   `null` cho cả "ô trống" lẫn "gõ bậy", nên hai chuyện ấy phải tách ra ở đây — không
		   tách thì gõ nhầm "8h30" là mất trắng giờ vào của người ta, mà màn hình vẫn báo Đã lưu. */
		$vao_moi = $vao_cu;
		if ( ! empty( $dat['xoa_vao'] ) ) {
			$vao_moi = null;
		} elseif ( '' !== trim( (string) ( isset( $dat['vao'] ) ? $dat['vao'] : '' ) ) ) {
			$vao_moi = self::giay( $dat['vao'] );
			if ( null === $vao_moi ) {
				return array( 'ok' => false, 'error' => 'Giờ vào không đúng dạng — gõ kiểu 08:30.' );
			}
		}
		$ra_moi = $ra_cu;
		if ( ! empty( $dat['xoa_ra'] ) ) {
			$ra_moi = null;
		} elseif ( '' !== trim( (string) ( isset( $dat['ra'] ) ? $dat['ra'] : '' ) ) ) {
			$ra_moi = self::giay( $dat['ra'] );
			if ( null === $ra_moi ) {
				return array( 'ok' => false, 'error' => 'Giờ ra không đúng dạng — gõ kiểu 17:00.' );
			}
		}

		if ( $vao_moi === $vao_cu && $ra_moi === $ra_cu ) {
			return array( 'ok' => false, 'error' => 'Không có gì thay đổi — giờ mới trùng giờ cũ.' );
		}
		if ( null !== $vao_moi && null !== $ra_moi && $ra_moi <= $vao_moi ) {
			return array( 'ok' => false,
				'error' => 'Giờ ra phải muộn hơn giờ vào. Ca đêm thì sửa ở hàng ca đêm (mã kèm -CD).' );
		}

		$kq = VHCC_Nhan::dat_gio( $coso, $ngay, $ma_nv, (string) $cu['ho_ten'],
			$vao_moi, $ra_moi, 'Sửa: ' . $ly_do );
		if ( isset( $kq['loi'] ) ) { return array( 'ok' => false, 'error' => $kq['loi'] ); }

		/* Một dòng nhật ký cho MỖI Ô THẬT SỰ ĐỔI. Ghi cả ô không đổi là sổ đầy dòng vô nghĩa,
		   và người đọc sổ phải tự đoán ô nào mới là ô bị động vào. */
		$doi = array();
		if ( $vao_moi !== $vao_cu ) {
			self::nhat_ky( $u, $coso, $ngay, $ma_nv, 'vao', $vao_moi, $ly_do, 'sua', $vao_cu );
			$doi['vao'] = array( 'cu' => self::hhmm_hoac_trong( $vao_cu ),
				'moi' => self::hhmm_hoac_trong( $vao_moi ) );
		}
		if ( $ra_moi !== $ra_cu ) {
			self::nhat_ky( $u, $coso, $ngay, $ma_nv, 'ra', $ra_moi, $ly_do, 'sua', $ra_cu );
			$doi['ra'] = array( 'cu' => self::hhmm_hoac_trong( $ra_cu ),
				'moi' => self::hhmm_hoac_trong( $ra_moi ) );
		}

		return array( 'ok' => true, 'coSo' => $coso, 'ngay' => $ngay, 'maNV' => $ma_nv, 'doi' => $doi );
	}

	/**
	 * Giờ đang có của một dòng, đã dạng 'HH:mm' — để màn hình HIỆN RA trước khi người ta sửa.
	 *
	 * 🔴 Không hiện thì người sửa phải NHỚ giờ cũ. Nhớ sai một chữ số là ghi đè mất một giờ công
	 *    thật, và không có gì trên màn hình mâu thuẫn với con số vừa gõ.
	 */
	public static function gio_hien_tai( $coso, $ngay, $ma_nv ) {
		$cu = self::hang( VHCC_NhanSu::chuan_coso( $coso ), $ngay, $ma_nv );
		if ( ! $cu ) { return array( 'co' => false, 'vao' => '—', 'ra' => '—' ); }
		$v = ( null !== $cu['gio_vao_giay'] && '' !== $cu['gio_vao_giay'] ) ? (int) $cu['gio_vao_giay'] : null;
		$r = ( null !== $cu['gio_ra_giay'] && '' !== $cu['gio_ra_giay'] ) ? (int) $cu['gio_ra_giay'] : null;
		return array( 'co' => true, 'vao' => self::hhmm_hoac_trong( $v ),
			'ra' => self::hhmm_hoac_trong( $r ), 'nguon' => (string) $cu['nguon'] );
	}

	/** 'HH:mm' hoặc '—'. Dùng cho câu báo và cho sổ nhật ký, để hai nơi nói giống nhau. */
	public static function hhmm_hoac_trong( $giay ) {
		return ( null === $giay ) ? '—' : VHCC_DB::hhmm( (int) $giay );
	}

	/* ===================================================================== nhật ký */

	/** Một dòng nhật ký cho MỘT ô giờ. Bảng này không có đường xoá — xem chú thích đầu tệp. */
	private static function nhat_ky( $u, $coso, $ngay, $ma_nv, $o, $giay, $ly_do, $viec = 'bu', $giay_cu = null ) {
		global $wpdb;
		$wpdb->insert( VHCC_DB::t( 'cham_bu' ), array(
			'coso'       => $coso,
			'ngay'       => $ngay,
			'ma_nv'      => $ma_nv,
			'o_gio'      => $o,
			/* ⚠️ KHÔNG ép `(int)` ở đây. Lượt sửa có thể XOÁ TRẮNG một ô, và `(int) null` là 0 —
			   tức là sổ ghi "sửa thành 00:00" trong khi thật ra là "xoá trắng". Hai chuyện khác
			   hẳn nhau, mà chỉ khác nhau ở một dấu ngoặc. */
			'gio_giay'   => ( null === $giay ) ? null : (int) $giay,
			'gio_cu_giay' => ( null === $giay_cu ) ? null : (int) $giay_cu,
			'viec'       => $viec,
			'ly_do'      => $ly_do,
			'nguoi_bu'   => (string) ( isset( $u['name'] ) ? $u['name'] : '' ),
			'ma_nguoi_bu' => (string) ( isset( $u['ma_nv'] ) ? $u['ma_nv'] : '' ),
			/* `cua()` nhận cả MẢNG người; `ma()` chỉ nhận CHUỖI vai. Truyền mảng vào `ma()` thì
			   PHP ép thành chuỗi "Array", rơi xuống nhánh "vai lạ -> đáy thang", và nhật ký ghi
			   MỌI người bù đều là NHAN_VIEN. Sai kiểu đó không kêu tiếng nào — chỉ có một dòng
			   cảnh báo "Array to string conversion" mà trên host thì tắt hiện lỗi. */
			'vai_nguoi_bu' => VHCC_Vai::cua( $u ),
			'tao_luc'    => current_time( 'mysql' ),
		) );
	}

	/** Nhật ký bù của một cơ sở / một tháng — để màn quản trị soi lại ai đã bù gì. */
	public static function ds_nhat_ky( $u, $coso = '', $thang = '' ) {
		global $wpdb;
		$dk = array( '1=1' );
		$tv = array();
		$coso = VHCC_NhanSu::chuan_coso( $coso );
		if ( '' !== $coso ) { $dk[] = 'LOWER(coso)=LOWER(%s)'; $tv[] = $coso; }
		if ( '' !== $thang ) {
			$tt = VHCC_Luong::tien_to_thang( $thang );
			if ( '' !== $tt ) { $dk[] = 'ngay LIKE %s'; $tv[] = $tt . '-%'; }
		}
		$sql = 'SELECT * FROM ' . VHCC_DB::t( 'cham_bu' ) . ' WHERE ' . implode( ' AND ', $dk )
			. ' ORDER BY tao_luc DESC';
		$out = array();
		foreach ( VHCC_DB::rows( $tv ? $wpdb->prepare( $sql, $tv ) : $sql ) as $r ) {
			if ( ! VHCC_NhanSu::co_quyen_coso( $u, $r['coso'] ) ) { continue; }
			$out[] = $r;
		}
		return $out;
	}

	/* ===================================================================== phụ */

	/** '' nếu ngày dùng được, hoặc câu từ chối. */
	public static function ngay_hop_le( $ngay ) {
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $ngay ) ) {
			return 'Ngày không hợp lệ.';
		}
		$hom_nay = (string) current_time( 'Y-m-d' );
		if ( $ngay > $hom_nay ) {
			return 'Không bù được cho ngày chưa tới.';
		}
		$cach = (int) round( ( strtotime( $hom_nay ) - strtotime( $ngay ) ) / 86400 );
		if ( $cach > self::NGAY_TOI_DA ) {
			return 'Ngày này đã quá ' . self::NGAY_TOI_DA . ' ngày — lương tháng đó chốt rồi. '
				. 'Trường hợp này báo Kế toán xử lý, đừng bù thẳng vào sổ công.';
		}
		return '';
	}

	/** 'HH:mm' hoặc 'HH:mm:ss' -> số giây. Rỗng/sai -> null (KHÔNG phải 0: 0 là 00:00:00). */
	public static function giay( $chu ) {
		$chu = trim( (string) $chu );
		if ( '' === $chu ) { return null; }
		if ( ! preg_match( '/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $chu, $m ) ) { return null; }
		$h = (int) $m[1];
		$p = (int) $m[2];
		$g = isset( $m[3] ) ? (int) $m[3] : 0;
		if ( $h > 23 || $p > 59 || $g > 59 ) { return null; }
		return $h * 3600 + $p * 60 + $g;
	}

	private static function hang( $coso, $ngay, $ma_nv ) {
		global $wpdb;
		list( $ma_goc, $hau_to ) = VHCC_Nhan::tach_hau_to( $ma_nv );
		return $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . VHCC_DB::t( 'cham_cong' )
			. ' WHERE coso=%s AND ngay=%s AND ma_nv=%s AND hau_to=%s',
			$coso, $ngay, $ma_goc, $hau_to ), ARRAY_A );
	}

	/** Tên lấy từ hồ sơ — không cho người bù tự gõ tên, kẻo mã một đằng tên một nẻo. */
	private static function ho_ten( $ma_nv ) {
		$hs = VHCC_NhanSu::ho_so( self::ma_goc( $ma_nv ) );
		return $hs ? (string) $hs['ho_ten'] : '';
	}
}
