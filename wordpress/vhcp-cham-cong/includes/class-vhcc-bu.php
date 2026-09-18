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

	/* ═══════════════════════════════════════════════════════════════════════════════════════
	 * HẾT NGÀY LÀ KHOÁ — cửa hàng trưởng chỉ sửa được giờ của CHÍNH HÔM NAY
	 * ═══════════════════════════════════════════════════════════════════════════════════════
	 * Anh Thắng 17/09/2026: *"Hiện quản lý không cho cửa hàng trưởng sửa nữa… hết 24h hôm nay
	 * không cho phép sửa giờ công. Vui lòng liên hệ kế toán"*.
	 *
	 * 🔴 KHOÁ THEO NGÀY, KHÔNG PHẢI THU QUYỀN. Hai cách làm ra hai kết quả rất khác:
	 *    · Hạ `sua_gio` khỏi bậc Cửa hàng trưởng thì họ mất luôn cả đường sửa cái vừa gõ nhầm
	 *      năm phút trước — mỗi lỗi vặt thành một cuộc gọi cho kế toán.
	 *    · Khoá theo ngày thì hôm nay họ tự dọn, còn hôm qua trở về trước đã đóng — đúng cái
	 *      anh Thắng nói, và đúng chỗ rủi ro thật: sửa ngược quá khứ là thứ không ai nhìn thấy.
	 *
	 * 🔴 AI KHÔNG BỊ KHOÁ: người có `cong_tat_ca` (Quản lý · Kế toán · Admin). Chốt bằng QUYỀN
	 *    chứ không bằng tên vai — thêm một vai mới mai sau thì nó tự rơi đúng phía.
	 *
	 * ⚠️ CHỈ KHOÁ **SỬA** VÀ **XOÁ**, KHÔNG KHOÁ **BÙ**. Bù là điền vào ô TRỐNG — đó chính là
	 *    việc màn trạm đang giục làm ("4 lượt thiếu một đầu giờ, bổ sung trước khi kế toán chốt
	 *    lương"), và mấy lượt ấy gần như luôn là của ngày hôm trước. Khoá bù theo ngày là vừa
	 *    giục người ta làm vừa chặn không cho làm. Sửa và xoá thì ĐÈ LÊN thứ đã có — khác hẳn.
	 *
	 * ⚠️ SO BẰNG NGÀY CỦA MÁY CHỦ (`current_time`), không bằng giờ trình duyệt. Điện thoại lệch
	 *    múi giờ hoặc để sai ngày là tự mở thêm cho mình một ngày.
	 */
	public static function bi_khoa_ngay_cu( $u ) {
		return ! VHCC_Vai::duoc( $u, 'cong_tat_ca' );
	}

	/** '' = qua được; khác rỗng = câu chối, nói đúng phải liên hệ ai. */
	public static function han_ngay( $u, $ngay, $viec = 'sửa' ) {
		if ( ! self::bi_khoa_ngay_cu( $u ) ) { return ''; }
		$hom_nay = (string) current_time( 'Y-m-d' );
		if ( (string) $ngay === $hom_nay ) { return ''; }
		return 'Hết 24h ngày ' . $ngay . ' thì không ' . $viec . ' giờ công của ngày ấy nữa. '
			. 'Vui lòng liên hệ kế toán.';
	}

	/** Câu nhắc bày sẵn ở đầu màn Cửa hàng — '' nếu người này không bị khoá. */
	public static function nhac_han_ngay( $u ) {
		if ( ! self::bi_khoa_ngay_cu( $u ) ) { return ''; }
		/* ⚠️ 18/09/2026 — KHÔNG NHẮC HẠN CHO NGƯỜI VỐN KHÔNG SỬA ĐƯỢC. Từ hôm nay `sua_gio` là
		   bậc Admin + chỉ định từng người, nên phần lớn cửa hàng trưởng không sửa được ngày
		   NÀO cả. Bày câu "hết 24h hôm nay thì không sửa nữa" cho họ là nói sai theo hướng tệ
		   nhất: nó ngụ ý hôm nay thì sửa được, và họ đi tìm cái nút không tồn tại. */
		if ( ! VHCC_Vai::duoc( $u, 'sua_gio' ) ) { return ''; }
		return 'Hết 24h hôm nay thì không cho phép sửa giờ công nữa. Vui lòng liên hệ kế toán.';
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

		/* 🔴 GÕ SAI DẠNG PHẢI BÁO LỖI, KHÔNG ĐƯỢC IM LẶNG THÀNH "không bù ô đó".
		   `giay()` trả `null` cho cả ô TRỐNG lẫn gõ BẬY. Trước 3.65.0 hai ô giờ là
		   `type="time"` nên trình duyệt chặn sẵn; nay là ô gõ thường, nên gõ nhầm "8h3o" mà
		   không chốt ở đây là bù MỖI giờ ra, màn hình báo "Đã bù giờ ra 17:00" — và người bù
		   tưởng xong cả hai. `sua()` đã phải vá đúng chỗ này một lần rồi. */
		foreach ( array( 'vao' => 'Giờ vào', 'ra' => 'Giờ ra' ) as $o_g => $ten_g ) {
			$tho = isset( $dat[ $o_g ] ) ? (string) $dat[ $o_g ] : '';
			if ( '' === trim( $tho ) ) { continue; }
			if ( false === VHCC_DB::gio_24( $tho ) ) {
				return array( 'ok' => false, 'error' => $ten_g . ' không đúng dạng: "' . trim( $tho )
					. '". Gõ theo 24 giờ — 08:30 hoặc gõ liền 0830; 1 giờ 37 chiều là 13:37.' );
			}
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

		if ( $da_ghi ) { self::bao_nguoi_bi_dong( $u, $ma_nv, $ngay, 'bù giờ công', $da_ghi, $coso ); }
		return array( 'ok' => true, 'coSo' => $coso, 'ngay' => $ngay, 'maNV' => $ma_nv,
			'daGhi' => $da_ghi, 'boQua' => $bo_qua );
	}

	/**
	 * BÁO CHO CHÍNH NGƯỜI BỊ ĐỘNG VÀO GIỜ CÔNG — đẩy sang chuông của trang Nội bộ.
	 *
	 * Anh Thắng 26/08/2026: *"Ví dụ như có chấm công, có chi phí nó sẽ hiện lên nội bộ này."*
	 *
	 * 🔴 NGƯỜI BỊ SỬA GIỜ PHẢI LÀ NGƯỜI BIẾT ĐẦU TIÊN. Sổ nhật ký đã ghi đủ ai-sửa-gì, nhưng
	 *    nó nằm ở màn quản trị mà nhân viên không vào. Không có tin báo thì họ chỉ phát hiện ra
	 *    vào cuối tháng, lúc nhìn số tiền — và lúc đó cãi lại thì đã muộn.
	 *
	 * ⚠️ Gác `class_exists` + `method_exists` CÙNG HÀM với lời gọi — luật của
	 *    `tools/test/kiem-goi-cheo.php`. Chưa cài plugin nội bộ thì lời gọi im lặng trôi qua,
	 *    KHÔNG được làm hỏng việc bù: bù giờ là việc chính, báo tin là việc phụ.
	 */
	private static function bao_nguoi_bi_dong( $u, $ma_nv, $ngay, $viec, $da_ghi, $coso = '' ) {
		if ( ! class_exists( 'VHNB_Bao' ) ) { return; }
		$o = array();
		foreach ( (array) $da_ghi as $k => $v ) {
			$o[] = ( 'vao' === $k ? 'giờ vào' : 'giờ ra' ) . ' ' . $v;
		}
		$chu_rieng = trim( (string) ( isset( $u['name'] ) ? $u['name'] : '' ) ) . ' ' . $viec
			. ' ngày ' . (string) $ngay . ( $o ? ' — ' . implode( ' · ', $o ) : '' );
		$tu = trim( (string) ( isset( $u['ma_nv'] ) ? $u['ma_nv'] : '' ) );

		/* 🔴 DÒNG BẢNG TIN: TRUNG TÍNH, KHÔNG TÊN KHÔNG GIỜ — anh Thắng chốt 08/09/2026.
		   Câu ở trên mang TÊN người sửa và GIỜ CÔNG cụ thể, và nó đi vào chuông RIÊNG của đúng
		   người bị động vào giờ — họ phải biết đủ để cãi lại được. Bảng tin thì cả công ty đọc:
		   ai bị sửa giờ ngày nào là chuyện giữa họ với quản lý, không phải tin cho 240 người.
		   Nên bảng tin chỉ nói CÓ VIỆC, gộp theo ngày.
		   ⚠️ ĐỪNG truyền `$chu_rieng` vào chỗ này cho gọn — xem cảnh báo ở `VHNB_Bao::viec()`.
		   ⚠️ Khoá gộp KHÔNG cần tiền tố bảng như bên chi phí: chấm công chỉ có MỘT bản, không
		      có bản riêng theo vùng nên không ai đụng khoá của ai. */
		/* Gộp theo NGÀY **và** CƠ SỞ: mỗi cửa hàng trưởng chỉ đọc được dòng của cơ sở mình
		   (anh Thắng 09/09/2026), nên hai cơ sở phải là hai dòng — gộp chung một dòng thì nó
		   mang được đúng một cơ sở và cơ sở còn lại mất tin. */
		$cs       = trim( (string) $coso );
		$tin      = 'Chấm công — giờ công ngày ' . (string) $ngay . ' có cập nhật';
		$khoa_tin = 'tin_cc:' . (string) $ngay . ( '' !== $cs ? ':' . $cs : '' );

		/* ⚠️ Bản nội bộ trên máy có thể CŨ HƠN và chưa có `viec()` — hai plugin cài độc lập.
		   Lùi về `gui()`: mất dòng bảng tin, chuông vẫn chạy y như trước. */
		if ( method_exists( 'VHNB_Bao', 'viec' ) ) {
			VHNB_Bao::viec( (string) $ma_nv, 'cham_cong', $chu_rieng, '', 'cc_gio:' . (string) $ngay,
				$tu, $tin, $khoa_tin, $cs );
			return;
		}
		if ( method_exists( 'VHNB_Bao', 'gui' ) ) {
			VHNB_Bao::gui( (string) $ma_nv, 'cham_cong', $chu_rieng, '',
				'cc_gio:' . (string) $ngay, $tu );
		}
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

		/* 🔴 Gác quyền RIÊNG, gác TRƯỚC — và vẫn giữ nguyên dù ngưỡng đã hạ.
		   `sua_gio` nay ở bậc Cửa hàng trưởng (anh Thắng 28/08/2026: *"Cửa hàng trưởng được
		   phép sửa cả giờ công đã chấm"*), nhưng nó vẫn là một đầu việc RIÊNG, tách khỏi
		   `vi_sao_khong_duoc()`. Giữ tách vì hai lý do: khoá lẻ được cho từng người ở màn Quản
		   lý nhân sự, và nếu mai anh Thắng muốn siết lại thì sửa MỘT dòng trong bảng vai. */
		if ( ! VHCC_Vai::duoc( $u, 'sua_gio' ) ) {
			return array( 'ok' => false,
				'error' => VHCC_Vai::loi( $u, 'sua_gio', 'Sửa giờ đã có' )
					. ' Trong lúc chờ mở, thấy giờ sai thì gắn cờ để cấp trên sửa.' );
		}
		$chan = self::vi_sao_khong_duoc( $u, $coso, $ma_nv );
		if ( '' !== $chan ) { return array( 'ok' => false, 'error' => $chan ); }

		$ngay = trim( (string) ( isset( $dat['ngay'] ) ? $dat['ngay'] : '' ) );
		$loi  = self::ngay_hop_le( $ngay );
		if ( '' !== $loi ) { return array( 'ok' => false, 'error' => $loi ); }

		/* Hết ngày là khoá — xem khối chú thích `han_ngay()`. */
		$han = self::han_ngay( $u, $ngay, 'sửa' );
		if ( '' !== $han ) { return array( 'ok' => false, 'error' => $han, 'quaHan' => true ); }

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
				return array( 'ok' => false, 'error' => 'Giờ vào không đúng dạng: "'
					. trim( (string) $dat['vao'] ) . '". Gõ theo 24 giờ — 08:30 hoặc gõ liền 0830.' );
			}
		}
		$ra_moi = $ra_cu;
		if ( ! empty( $dat['xoa_ra'] ) ) {
			$ra_moi = null;
		} elseif ( '' !== trim( (string) ( isset( $dat['ra'] ) ? $dat['ra'] : '' ) ) ) {
			$ra_moi = self::giay( $dat['ra'] );
			if ( null === $ra_moi ) {
				return array( 'ok' => false, 'error' => 'Giờ ra không đúng dạng: "'
					. trim( (string) $dat['ra'] ) . '". Gõ theo 24 giờ — 17:00 hoặc gõ liền 1700.' );
			}
		}

		/* ═══════════════════════════════════════════════════════════════════════════════════
		 * CA GÃY — khoảng NGHỈ GIỮA CA, không tính tiền.
		 *
		 * `$dat['gay']` là ô TÍCH: không tích thì XOÁ khoảng nghỉ (người ta vừa bỏ tích), tích
		 * thì phải có đủ hai đầu giờ. `false` = biểu mẫu không gửi ô ấy lên (dòng nhiều ca, hoặc
		 * một đường gọi khác) -> KHÔNG ĐỤNG TỚI.
		 * ═══════════════════════════════════════════════════════════════════════════════════ */
		$ng_tu  = false;
		$ng_den = false;
		if ( array_key_exists( 'gay', $dat ) ) {
			if ( empty( $dat['gay'] ) ) {
				$ng_tu  = null;
				$ng_den = null;
			} else {
				$ng_tu  = self::giay( isset( $dat['nghi_tu'] ) ? $dat['nghi_tu'] : '' );
				$ng_den = self::giay( isset( $dat['nghi_den'] ) ? $dat['nghi_den'] : '' );
				if ( null === $ng_tu || null === $ng_den ) {
					return array( 'ok' => false, 'error' => 'Tích "Ca gãy" thì phải gõ đủ cả '
						. '"Ra ca 1" lẫn "Vào ca 2" — theo 24 giờ, VD 14:00 và 17:00.' );
				}
				if ( $ng_den <= $ng_tu ) {
					return array( 'ok' => false, 'error' => '"Vào ca 2" phải muộn hơn "Ra ca 1".' );
				}
				/* 🔴 KHÚC NGHỈ PHẢI NẰM TRONG CHÍNH LƯỢT CHẤM ẤY. Nghỉ thò ra ngoài [vào, ra] là
				   trừ nhiều hơn số giờ người ta có mặt — ra số âm, hoặc ăn sang ngày khác. */
				if ( null === $vao_moi || null === $ra_moi ) {
					return array( 'ok' => false,
						'error' => 'Ngày này còn thiếu giờ vào hoặc giờ ra — điền đủ rồi mới khai ca gãy được.' );
				}
				if ( $ng_tu < $vao_moi || $ng_den > $ra_moi ) {
					return array( 'ok' => false, 'error' => 'Khúc nghỉ phải nằm TRONG giờ vào và '
						. 'giờ ra của ngày ấy (' . self::hhmm_hoac_trong( $vao_moi ) . ' → '
						. self::hhmm_hoac_trong( $ra_moi ) . ').' );
				}
			}
		}

		$doi_gay = ( false !== $ng_tu || false !== $ng_den );
		if ( $vao_moi === $vao_cu && $ra_moi === $ra_cu && ! $doi_gay ) {
			return array( 'ok' => false, 'error' => 'Không có gì thay đổi — giờ mới trùng giờ cũ.' );
		}
		/* 🔴 HÀNG CA ĐÊM: GIỜ RA SAU NỬA ĐÊM KHÔNG PHẢI LÀ "SỚM HƠN GIỜ VÀO".
		   Ca đêm lưu giờ ra ở dạng TRẢI PHẲNG (05:30 hôm sau = 29 giờ 30), nên người sửa gõ
		   `05:30` vào ô giờ ra là ra một con số nhỏ hơn giờ vào — và chốt bên dưới đá thẳng lượt
		   sửa ra với câu "Ca đêm thì sửa ở hàng ca đêm", trong khi họ ĐANG sửa đúng hàng ca đêm.
		   Chốt ấy sinh ra để chặn chuyện khác: gõ giờ ca đêm vào hàng CHÍNH. Với hàng chính nó
		   vẫn đúng và giữ nguyên; với hàng ca đêm thì trải phẳng, y như cổng online vẫn làm.
		   ⚠️ Chỉ trải phẳng ĐÚNG MỘT NGÀY. Ca dài hơn 24 tiếng không phải ca đêm, nó là dấu hiệu
		      gõ nhầm — và cộng bừa thêm ngày nữa là bịa ra giờ làm. */
		list( , $ht_sua ) = VHCC_Nhan::tach_hau_to( $ma_nv );
		if ( null !== $vao_moi && null !== $ra_moi && $ra_moi <= $vao_moi
			&& in_array( $ht_sua, array( 'CD', 'CT', 'TC' ), true ) ) {
			$ra_moi += VHCC_DB::NGAY_GIAY;
		}
		if ( null !== $vao_moi && null !== $ra_moi && $ra_moi <= $vao_moi ) {
			return array( 'ok' => false,
				'error' => 'Giờ ra phải muộn hơn giờ vào. Ca đêm thì sửa ở hàng ca đêm (mã kèm -CD).' );
		}

		$kq = VHCC_Nhan::dat_gio( $coso, $ngay, $ma_nv, (string) $cu['ho_ten'],
			$vao_moi, $ra_moi, 'Sửa: ' . $ly_do, $ng_tu, $ng_den );
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
	 * XOÁ HẲN MỘT DÒNG CHẤM CÔNG.
	 *
	 * =============================================================================================
	 * Anh Thắng 17/09/2026: *"làm nút xóa hẳn dòng công"*. Trước bản này chỉ có cách xoá TRẮNG hai
	 * ô giờ (`sua` + `xoa_vao` + `xoa_ra`): dòng ở lại, không còn giờ. Anh muốn dòng biến mất khỏi
	 * lưới, và đó là một việc khác — nên là một hàm khác, không nhét thêm một ô tích vào `sua()`.
	 *
	 * =============================================================================================
	 * 🔴 ĐÂY LÀ VIỆC PHÁ NHIỀU NHẤT TRONG CẢ LỚP — GÁC Y HỆT `sua()`, KHÔNG BỚT MỘT CHỐT
	 * =============================================================================================
	 * Xoá trắng giờ còn để lại dấu "hôm ấy có một dòng". Xoá hẳn thì lưới trông y như người ta
	 * KHÔNG ĐI LÀM hôm đó — và không còn gì trên màn hình mâu thuẫn với chuyện ấy. Nên nó dùng
	 * đúng bộ gác của `sua()`, không rẻ hơn một li:
	 *   · quyền `sua_gio` (Cửa hàng trưởng trở lên);
	 *   · `vi_sao_khong_duoc()` — đúng cơ sở mình, mã có hồ sơ, và KHÔNG TỰ XOÁ CỦA CHÍNH MÌNH;
	 *   · bắt ghi VÌ SAO, tối thiểu 5 ký tự.
	 *
	 * 🔴 GHI NHẬT KÝ TRƯỚC, XOÁ SAU — thứ tự này là cố ý.
	 * Hai thứ tự đều có một nhánh hỏng, và phải chọn nhánh hỏng NÀO chịu được:
	 *   · Xoá trước, ghi sổ sau: sổ hỏng thì dòng đã mất mà KHÔNG CÒN GÌ nói nó từng tồn tại.
	 *     Không ai lần lại được, và cũng không ai biết là có chuyện để lần.
	 *   · Ghi sổ trước, xoá sau: xoá hỏng thì sổ có một dòng "đã xoá" trong khi dòng vẫn còn —
	 *     đọc lên thấy mâu thuẫn ngay, và hàm trả về câu lỗi nói đúng chuyện đó.
	 * Cái thứ hai sai một cách NHÌN THẤY ĐƯỢC. Bằng chứng không bao giờ được là thứ thiếu.
	 *
	 * ⚠️ GHI CẢ HAI Ô vào sổ, kể cả ô vốn đã trống. Ở `sua()` thì chỉ ghi ô THẬT SỰ đổi, vì ghi
	 *    cả ô không đổi là sổ đầy dòng vô nghĩa. Ở đây ngược lại: cả dòng biến mất, nên "ô giờ ra
	 *    vốn đã trống" cũng là một sự thật cần giữ — thiếu nó thì sau này đọc sổ không biết được
	 *    lúc xoá dòng ấy đang thiếu giờ ra hay đã đủ.
	 */
	public static function xoa( $u, $dat ) {
		global $wpdb;
		$coso  = VHCC_NhanSu::chuan_coso( isset( $dat['coso'] ) ? $dat['coso'] : '' );
		$ma_nv = trim( (string) ( isset( $dat['ma_nv'] ) ? $dat['ma_nv'] : '' ) );

		if ( ! VHCC_Vai::duoc( $u, 'sua_gio' ) ) {
			return array( 'ok' => false,
				'error' => VHCC_Vai::loi( $u, 'sua_gio', 'Xoá dòng chấm công' )
					. ' Trong lúc chờ mở, thấy dòng sai thì gắn cờ để cấp trên xử.' );
		}
		$chan = self::vi_sao_khong_duoc( $u, $coso, $ma_nv );
		if ( '' !== $chan ) { return array( 'ok' => false, 'error' => $chan ); }

		$ngay = trim( (string) ( isset( $dat['ngay'] ) ? $dat['ngay'] : '' ) );
		$loi  = self::ngay_hop_le( $ngay );
		if ( '' !== $loi ) { return array( 'ok' => false, 'error' => $loi ); }

		/* Xoá cũng là đè lên thứ đã có, nên chịu cùng cái khoá với sửa. Mở một trong hai mà
		   khoá cái kia là để hở đúng đường phá nhiều hơn. */
		$han = self::han_ngay( $u, $ngay, 'xoá' );
		if ( '' !== $han ) { return array( 'ok' => false, 'error' => $han, 'quaHan' => true ); }

		$ly_do = trim( (string) ( isset( $dat['ly_do'] ) ? $dat['ly_do'] : '' ) );
		if ( mb_strlen( $ly_do, 'UTF-8' ) < 5 ) {
			return array( 'ok' => false,
				'error' => 'Ghi rõ vì sao xoá dòng này (ít nhất 5 ký tự) — VD: "máy chấm nhầm '
					. 'sang mã người khác, đã đối chiếu camera".' );
		}

		$cu = self::hang( $coso, $ngay, $ma_nv );
		if ( ! $cu ) {
			return array( 'ok' => false, 'error' => 'Ngày này không có dòng chấm công nào để xoá.' );
		}
		$vao_cu = ( null !== $cu['gio_vao_giay'] && '' !== $cu['gio_vao_giay'] ) ? (int) $cu['gio_vao_giay'] : null;
		$ra_cu  = ( null !== $cu['gio_ra_giay'] && '' !== $cu['gio_ra_giay'] ) ? (int) $cu['gio_ra_giay'] : null;

		/* Bằng chứng trước — xem khối chú thích trên. */
		self::nhat_ky( $u, $coso, $ngay, $ma_nv, 'vao', null, $ly_do, 'xoa', $vao_cu );
		self::nhat_ky( $u, $coso, $ngay, $ma_nv, 'ra',  null, $ly_do, 'xoa', $ra_cu );

		$bo = $wpdb->delete( VHCC_DB::t( 'cham_cong' ), array( 'id' => (int) $cu['id'] ) );
		if ( false === $bo || 0 === (int) $bo ) {
			return array( 'ok' => false, 'error' => 'Không xoá được dòng (MySQL: '
				. ( $wpdb->last_error ? $wpdb->last_error : 'không rõ' ) . '). '
				. 'Sổ "Đã động vào giờ công" đã ghi một dòng XOÁ cho lượt này — nếu dòng chấm '
				. 'công vẫn còn thì hai chỗ đang nói khác nhau, báo quản trị soát lại.' );
		}
		return array( 'ok' => true, 'coSo' => $coso, 'ngay' => $ngay, 'maNV' => $ma_nv,
			'daXoa' => array( 'vao' => self::hhmm_hoac_trong( $vao_cu ),
				'ra' => self::hhmm_hoac_trong( $ra_cu ) ) );
	}

	/**
	 * Giờ đang có của một dòng, đã dạng 'HH:mm' — để màn hình HIỆN RA trước khi người ta sửa.
	 *
	 * 🔴 Không hiện thì người sửa phải NHỚ giờ cũ. Nhớ sai một chữ số là ghi đè mất một giờ công
	 *    thật, và không có gì trên màn hình mâu thuẫn với con số vừa gõ.
	 */
	public static function gio_hien_tai( $coso, $ngay, $ma_nv ) {
		$cu = self::hang( VHCC_NhanSu::chuan_coso( $coso ), $ngay, $ma_nv );
		if ( ! $cu ) {
			return array( 'co' => false, 'vao' => '—', 'ra' => '—',
				'vaoGiay' => null, 'raGiay' => null, 'nghiTu' => null, 'nghiDen' => null );
		}
		$v = ( null !== $cu['gio_vao_giay'] && '' !== $cu['gio_vao_giay'] ) ? (int) $cu['gio_vao_giay'] : null;
		$r = ( null !== $cu['gio_ra_giay'] && '' !== $cu['gio_ra_giay'] ) ? (int) $cu['gio_ra_giay'] : null;
		/* CA GÃY — hai đầu khoảng nghỉ, trả về dạng GIÂY THÔ (không phải 'HH:mm') vì nơi gọi
		   còn phải so với giờ vào/ra và với phép đề xuất của `VHCC_Ca`. */
		$ntu  = ( null !== $cu['nghi_tu_giay'] && '' !== $cu['nghi_tu_giay'] ) ? (int) $cu['nghi_tu_giay'] : null;
		$nden = ( null !== $cu['nghi_den_giay'] && '' !== $cu['nghi_den_giay'] ) ? (int) $cu['nghi_den_giay'] : null;
		return array( 'co' => true, 'vao' => self::hhmm_hoac_trong( $v ),
			'ra' => self::hhmm_hoac_trong( $r ), 'nguon' => (string) $cu['nguon'],
			'vaoGiay' => $v, 'raGiay' => $r, 'nghiTu' => $ntu, 'nghiDen' => $nden );
	}

	/**
	 * MỌI DÒNG CHẤM CÔNG CỦA MỘT NGƯỜI, MỘT NGÀY, TRÊN CẢ CHÙM CƠ SỞ ĐÃ GHÉP.
	 *
	 * Anh Thắng 27/08/2026: *"nếu cơ sở được ghép từ 2 cơ sở, thì khi sửa sẽ sửa luôn được cả 2
	 * là 4 giờ vào ra"*.
	 *
	 * 🔴 KHÔNG CÓ HÀM NÀY THÌ HÀNG SỬA CHỈ VỚI TỚI MỘT NỬA BẢNG.
	 *    Lưới cả tháng nay gộp cả chùm (VP_KH-HCM + SETUP_VP) vào một hàng — nhìn thì liền một
	 *    mạch, nhưng dòng ca đêm nằm ở cơ sở PHỤ với hậu tố riêng. Hàng sửa cũ chỉ dựng đúng một
	 *    cặp ô cho cơ sở ĐANG XEM, nên bấm sửa một ngày có ca đêm thì máy chủ đáp "Ngày này chưa
	 *    có dòng chấm công nào để sửa" — trong khi trên màn hình ô ấy đang có số. Người ta thấy
	 *    số, bấm sửa, và bị bảo là không có gì.
	 *
	 * ⚠️ TRẢ VỀ DÒNG THẬT, KHÔNG ĐOÁN. Mỗi dòng là một (cơ sở · hậu tố) có thật trong kho, nên
	 *    hàng sửa dựng đúng bấy nhiêu cặp ô — không thừa ô cho ca không tồn tại, không thiếu ô
	 *    cho ca đang có giờ.
	 *
	 * `$ds_coso` là CẢ CHÙM (`VHCC_Luong::chum_cua`). Trả:
	 *   array( array( 'coso','hauTo','ma','vao','ra' ) ) — giờ đã dạng 'HH:mm' hoặc '—'.
	 */
	public static function cac_o( $ds_coso, $ngay, $ma_nv ) {
		global $wpdb;
		$sach = array();
		foreach ( (array) $ds_coso as $x ) {
			$x = VHCC_NhanSu::chuan_coso( $x );
			if ( '' !== $x && ! in_array( $x, $sach, true ) ) { $sach[] = $x; }
		}
		if ( ! $sach ) { return array(); }
		list( $ma_goc, ) = VHCC_Nhan::tach_hau_to( $ma_nv );
		if ( '' === trim( (string) $ma_goc ) ) { return array(); }

		$cho = implode( ',', array_fill( 0, count( $sach ), '%s' ) );
		$ds  = $wpdb->get_results( $wpdb->prepare(
			'SELECT coso, hau_to, gio_vao_giay, gio_ra_giay FROM ' . VHCC_DB::t( 'cham_cong' )
			. ' WHERE coso IN (' . $cho . ') AND ngay=%s AND ma_nv=%s ORDER BY coso, hau_to',
			array_merge( $sach, array( $ngay, $ma_goc ) ) ), ARRAY_A );
		if ( ! is_array( $ds ) ) { return array(); }

		$out = array();
		foreach ( $ds as $d ) {
			$ht = strtoupper( trim( (string) $d['hau_to'] ) );
			$v  = ( null !== $d['gio_vao_giay'] && '' !== $d['gio_vao_giay'] ) ? (int) $d['gio_vao_giay'] : null;
			$r  = ( null !== $d['gio_ra_giay'] && '' !== $d['gio_ra_giay'] ) ? (int) $d['gio_ra_giay'] : null;
			$out[] = array(
				'coso'  => (string) $d['coso'],
				'hauTo' => $ht,
				'ma'    => $ma_goc . ( '' !== $ht ? '-' . $ht : '' ),
				'vao'   => self::hhmm_hoac_trong( $v ),
				'ra'    => self::hhmm_hoac_trong( $r ),
			);
		}
		return $out;
	}

	/** 'HH:mm' hoặc '—'. Dùng cho câu báo và cho sổ nhật ký, để hai nơi nói giống nhau. */
	public static function hhmm_hoac_trong( $giay ) {
		return ( null === $giay ) ? '—' : VHCC_DB::hhmm( (int) $giay );
	}

	/**
	 * Ô GIỜ NÀO CỦA NGÀY NÀY ĐÃ CÓ NGƯỜI ĐỘNG TAY — bù hoặc sửa.
	 *
	 * =============================================================================================
	 * Anh Thắng 27/08/2026: *"Nhớ bổ sung dữ liệu lên, chỉ đè dữ liệu khi nó trống, tránh đè lần 2"*.
	 * =============================================================================================
	 * 🔴 SỔ NHẬT KÝ LÀ NGUỒN THẬT, KHÔNG PHẢI CỘT `nguon`.
	 *    Cột `nguon` của hàng chấm công bị NỚI thành `hon-hop` ngay khi một lượt máy chạm vào —
	 *    tức là dấu vết "ô này người ta đã sửa" tan mất sau đúng một lượt, và lượt thứ hai đè
	 *    thoải mái. Bảng `cham_bu` thì ngược lại: mỗi lượt bù và mỗi lượt sửa đều ghi vào đó MỘT
	 *    DÒNG CHO MỖI Ô, và bảng ấy KHÔNG có đường xoá. Hỏi đúng nơi giữ sự thật.
	 *
	 * ⚠️ Trả về mảng theo Ô (`vao` / `ra`), không phải một cờ chung cho cả ngày. Bù giờ vào rồi
	 *    máy gửi giờ ra thật thì giờ ra ấy VẪN PHẢI VÀO — ô đó còn trống, và chặn nó là bắt
	 *    người ta bù tay cả cặp trong khi máy đã có sẵn con số đúng.
	 *
	 * @return array( 'vao' => bool, 'ra' => bool )
	 */
	public static function o_da_dong_tay( $coso, $ngay, $ma_nv ) {
		global $wpdb;
		$out = array( 'vao' => false, 'ra' => false );
		$coso = VHCC_NhanSu::chuan_coso( $coso );
		$ma   = trim( (string) $ma_nv );
		if ( '' === $coso || '' === $ma ) { return $out; }
		$ds = $wpdb->get_col( $wpdb->prepare(
			'SELECT DISTINCT o_gio FROM ' . VHCC_DB::t( 'cham_bu' )
			. ' WHERE coso=%s AND ngay=%s AND ma_nv=%s', $coso, $ngay, $ma ) );
		foreach ( (array) $ds as $o ) {
			$o = trim( (string) $o );
			if ( 'vao' === $o || 'ra' === $o ) { $out[ $o ] = true; }
		}
		return $out;
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
	/**
	 * Chuỗi giờ -> giây trong ngày. `null` cho CẢ ô trống lẫn gõ sai — nơi gọi phải tự tách hai
	 * chuyện ấy ra (xem `VHCC_DB::gio_24()` và hai chốt trong `ghi()`/`sua()`).
	 *
	 * ⚠️ Đi qua `VHCC_DB::gio_24()` từ 3.65.0, nên nhận luôn kiểu gõ nhanh `1337` / `13h37`.
	 *    Hai ô giờ trên màn nay là ô gõ thường (không còn `type="time"`), và người gõ nhanh
	 *    nhất là người gõ bốn số liền.
	 */
	public static function giay( $chu ) {
		/* GIỮ GIÂY: ô "Giờ vào" của sổ cũ có giây, cắt xuống phút là mỗi lượt chấm mất tới
		   59 giây mà không dòng đỏ nào — xem chú thích cờ `$giu_giay` ở `VHCC_DB::gio_24()`. */
		$c = VHCC_DB::gio_24( $chu, true );
		if ( '' === $c || false === $c ) { return null; }
		return VHCC_DB::giay( $c );
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
