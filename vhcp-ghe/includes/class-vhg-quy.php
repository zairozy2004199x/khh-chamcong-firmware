<?php
/**
 * QUỸ TIỀN MẶT — CHỐT CA THEO GHẾ, VÀ NỘP TIỀN VỀ QUẦY
 *
 * Anh Thắng 23/08/2026:
 *   *"giờ đến phần thu tiền của nhân viên"*
 *   *"Nhân viên sẽ có 1 ứng dụng apk trên điện thoại. Mở ứng dụng tới quét QR tại máy. Bấm thu
 *   tiền (chốt ca, dữ liệu chốt ca). Nhập số tiền mặt, chỉ số máy tiền mặt — trên máy có 1 màn
 *   hình đếm tiền mặt nữa, nên nhập vào để trừ chỉ số cho ngày hôm sau."*
 *
 * ════════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 VÌ SAO PHẢI ĐỌC CHỈ SỐ, TRONG KHI GHẾ ĐÃ TỰ BÁO TIỀN MẶT VỀ MÁY CHỦ RỒI.
 *
 *    Vì hai con số đó đi qua hai con đường khác nhau, và đúng chỗ chúng lệch nhau mới là chỗ
 *    có chuyện:
 *
 *      · Máy đếm tiền đếm bằng PHẦN CỨNG. Nó nuốt tờ tiền là chỉ số nhảy, không cần điện của
 *        ESP32, không cần WiFi, không cần máy chủ còn sống.
 *      · Con số trong sổ đi qua ESP32 -> WiFi/4G -> máy chủ. Mỗi chặng đều rơi được: mất điện
 *        giữa lượt, xung bị nhiễu nuốt mất, gói tin không đẩy đi nổi rồi ghế khởi động lại.
 *
 *    Khi hai con số bằng nhau, mình biết chắc sổ đúng. Khi lệch, `lech_may` nói thẳng ra là
 *    DOANH THU ĐANG THIẾU TRONG SỔ bao nhiêu — thứ mà trước bản này không ai phát hiện được,
 *    vì tiền vẫn nằm trong ngăn còn sổ thì im lặng.
 *
 * 🔴 VÀ HAI KIỂU LỆCH KHÔNG ĐƯỢC GỘP LÀM MỘT.
 *      · `lech_dem` = tiền đếm được − máy nói đã nuốt.  -> chuyện của NGƯỜI (thiếu/thừa ngăn).
 *      · `lech_may` = máy nói đã nuốt − sổ ghi nhận.    -> chuyện của MÁY (sót lượt).
 *    Gộp thành một "chênh lệch" là mất đúng thông tin để biết đi hỏi ai.
 *
 * ════════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 CHỐT CA KHÔNG PHẢI DOANH THU.
 *
 *    Tiền trong ngăn ghế ĐÃ vào sổ doanh thu từ lúc ghế nuốt nó. Lượt chốt ca chỉ nói *"tôi vừa
 *    lấy xấp tiền đó ra khỏi ngăn"* — một lần chuyển tay, không phải một lần bán hàng. Ghi nó
 *    thành doanh thu là cộng đôi, đúng cái lỗi tab Thu tiền đang phải kêu lên mỗi kỳ.
 *
 *    Nên bảng `chot` KHÔNG đụng vào bảng `thu`. Nó chỉ làm hai việc: ghi lại ba con số để đối
 *    chiếu, và cộng `tien_dem` vào TIỀN TRÊN TAY người vừa chốt.
 * ════════════════════════════════════════════════════════════════════════════════════════════
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHG_Quy {

	/**
	 * Một đơn vị trên màn đếm của máy tiền mặt bằng bao nhiêu đồng.
	 *
	 * Firmware đang để `CASH_VND_PER_PULSE 5000` (1 xung = 5.000đ, theo DIP của cục nhận tiền),
	 * nên mặc định lấy đúng con số đó. Nhưng màn đếm của mỗi hãng hiển thị một kiểu — có cái
	 * đếm SỐ TỜ, có cái đếm SỐ XUNG, có cái hiện thẳng số tiền. Khai sai là mọi con số `theo_may`
	 * sai theo cùng một hệ số, và nó sai một cách RẤT GIỐNG THẬT.
	 */
	public static function don_vi() {
		$v = (int) get_option( 'vhg_chot_don_vi', 5000 );
		return $v > 0 ? $v : 5000;
	}

	public static function luu_don_vi( $v ) {
		$v = (int) $v;
		if ( $v <= 0 ) {
			return array( 'ok' => false, 'error' => 'Mỗi đơn vị chỉ số phải lớn hơn 0 đồng.' );
		}
		update_option( 'vhg_chot_don_vi', $v );
		return array( 'ok' => true, 'thong_bao' => 'Đã lưu: mỗi đơn vị trên màn đếm = '
			. number_format( $v, 0, ',', '.' ) . 'đ.' );
	}

	// ═════════════════════════════════════════════════════════════════════ chốt ca

	/** Lượt chốt gần nhất của một ghế, hoặc null nếu ghế này chưa chốt bao giờ. */
	public static function chot_truoc( $ma_may ) {
		global $wpdb;
		$m = strtoupper( trim( (string) $ma_may ) );
		if ( '' === $m ) { return null; }
		return $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . VHG_DB::t( 'chot' ) . ' WHERE ma_may=%s ORDER BY id DESC LIMIT 1',
			$m ), ARRAY_A );
	}

	/**
	 * MỐC CHỈ SỐ TRƯỚC DÙNG CHUNG — MỘT MÁY ĐẾM cho cả chốt ca LẪN báo cáo doanh thu.
	 *
	 * Anh Thắng 27/08/2026: *"nối hai chiều luôn"*. Trước bản này mốc chốt ca chỉ lấy từ bảng
	 * `chot`; nay lấy chỉ số sau gần nhất từ CẢ `chot` LẪN `bc_dong` (nhân viên nhập báo cáo).
	 * Nhờ vậy: nhân viên nhập báo cáo trước → máy trạm chốt sau lấy đúng số đó làm mốc, và ngược
	 * lại — không đếm đôi một quãng.
	 *
	 * $chot_truoc = dòng chốt gần nhất (đã đọc sẵn ở nơi gọi để khỏi truy vấn hai lần).
	 * Trả [ 'cs'=>int, 'nguon'=>''|'chot'|'bao_cao', 'ngay'=>'Y-m-d', 'lan_dau'=>0/1 ].
	 *
	 * ⚠️ MÁY ĐẾM KHÔNG CHẠY LÙI: chỉ nhận mốc báo cáo mới hơn khi nó KHÔNG nhỏ hơn mốc chốt —
	 *    tránh một chỉ số báo cáo cũ kéo mốc tụt xuống rồi `theo_may` phình lên giả.
	 * ⚠️ Bằng ngày thì ưu tiên CHỐT (đếm phần cứng thật), để không đảo hành vi chốt cùng ngày.
	 */
	public static function moc_chi_so( $ma_may, $chot_truoc ) {
		global $wpdb;
		$m = strtoupper( trim( (string) $ma_may ) );
		$cs_chot   = $chot_truoc ? (int) $chot_truoc['chi_so'] : null;
		$ngay_chot = $chot_truoc ? substr( (string) $chot_truoc['tao_luc'], 0, 10 ) : '';
		$bc = '' === $m ? null : $wpdb->get_row( $wpdb->prepare(
			'SELECT chi_so_sau cs, ngay FROM ' . VHG_DB::t( 'bc_dong' )
			. ' WHERE ma_may=%s AND chi_so_sau IS NOT NULL ORDER BY ngay DESC, id DESC LIMIT 1', $m ), ARRAY_A );
		$cs_bc   = $bc ? (int) $bc['cs'] : null;
		$ngay_bc = $bc ? substr( (string) $bc['ngay'], 0, 10 ) : '';

		if ( null === $cs_chot && null === $cs_bc ) {
			return array( 'cs' => 0, 'nguon' => '', 'ngay' => '', 'lan_dau' => 1 );
		}
		if ( null === $cs_bc )   { return array( 'cs' => $cs_chot, 'nguon' => 'chot', 'ngay' => $ngay_chot, 'lan_dau' => 0 ); }
		if ( null === $cs_chot ) { return array( 'cs' => $cs_bc, 'nguon' => 'bao_cao', 'ngay' => $ngay_bc, 'lan_dau' => 0 ); }
		if ( $ngay_bc > $ngay_chot && $cs_bc >= $cs_chot ) {
			return array( 'cs' => $cs_bc, 'nguon' => 'bao_cao', 'ngay' => $ngay_bc, 'lan_dau' => 0 );
		}
		return array( 'cs' => $cs_chot, 'nguon' => 'chot', 'ngay' => $ngay_chot, 'lan_dau' => 0 );
	}

	/**
	 * Tổng tiền mặt GHẾ TỰ BÁO VỀ trong một quãng thời gian.
	 *
	 * ⚠️ Chỉ đường "ghế nuốt" (`ND_GHE_NUOT`). Tiền người thu bấm tay ở quầy KHÔNG nằm trong
	 *    ngăn ghế — cộng nó vào đây là `lech_may` lúc nào cũng đỏ mà chẳng có gì hỏng cả.
	 * ⚠️ Bỏ dòng đã huỷ: huỷ nghĩa là lượt đó không có thật.
	 *
	 * 🔴 CẮT QUÃNG BẰNG SỐ DÒNG (`id`), KHÔNG BẰNG ĐỒNG HỒ.
	 *    Bản đầu cắt bằng `luc > <giờ chốt trước>` và nó SAI ngay ở phép thử đầu tiên: ghế nuốt
	 *    tiền và người thu bấm chốt trong CÙNG MỘT GIÂY thì `>` bỏ mất cả quãng, còn `>=` thì
	 *    đếm nó hai lần. Ngoài đời chuyện đó xảy ra thật — người thu đứng ngay cạnh ghế.
	 *    Thêm nữa, giờ máy chủ hệ này đang lệch múi 7 tiếng, nên mọi phép cắt theo đồng hồ đều
	 *    đi theo cái lệch đó. Số dòng thì không có hai chuyện ấy.
	 */
	public static function may_bao( $ma_may, $tu_id, $den_id = 0 ) {
		global $wpdb;
		$m = strtoupper( trim( (string) $ma_may ) );
		if ( '' === $m ) { return 0; }
		$t    = VHG_DB::t( 'thu' );
		$sql  = "SELECT COALESCE(SUM(so_tien),0) FROM $t
			WHERE ma_may=%s AND nguon=%s AND noi_dung=%s AND huy=0 AND id > %d";
		$tham = array( $m, VHG_Thu::TIEN_MAT, VHG_Thu::ND_GHE_NUOT, (int) $tu_id );
		if ( (int) $den_id > 0 ) { $sql .= ' AND id <= %d'; $tham[] = (int) $den_id; }
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $tham ) );
	}

	/** Dòng mới nhất của sổ thu ngay lúc này — mốc đóng của quãng đang chốt. */
	public static function thu_moi_nhat() {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COALESCE(MAX(id),0) FROM ' . VHG_DB::t( 'thu' ) );
	}

	/**
	 * Màn hình TRƯỚC KHI CHỐT: người đứng đó cần thấy gì để gõ cho đúng.
	 *
	 * 🔴 HIỆN CHỈ SỐ LẦN TRƯỚC RA MÀN. Không hiện thì người ta gõ chỉ số mới mà không có gì để
	 *    đối chiếu, và một con số gõ nhầm một chữ số sẽ đi thẳng vào sổ rồi nằm đó mãi mãi —
	 *    kỳ sau lại lấy chính nó làm mốc trừ.
	 */
	public static function truoc_khi_chot( $ma_may, $coso_cua_toi = null ) {
		$m = strtoupper( trim( (string) $ma_may ) );
		if ( '' === $m ) {
			return array( 'ok' => false, 'error' => 'Chưa biết ghế nào — quét mã QR trên ghế giúp em.' );
		}
		/* `ds_may_theo_ma()` chứ không `may()`: chỉ bản này mới kèm tên cơ sở (có JOIN). Màn chốt
		   ca phải nói rõ đang đếm tiền của ghế nào Ở ĐÂU — người đi thu đi nhiều cơ sở một buổi,
		   và hai cơ sở có thể đặt tên ghế na ná nhau. */
		$bd = VHG_May::ds_may_theo_ma();
		if ( ! isset( $bd[ $m ] ) ) {
			return array( 'ok' => false, 'error' => 'Không thấy ghế ' . $m . ' trong hệ thống.' );
		}
		$may_ = $bd[ $m ];

		/* ══════════════════════════════════════════════════════════════════════════════════
		 * 🔴 NGƯỜI GẮN CƠ SỞ THÌ CHỈ CHỐT ĐƯỢC GHẾ Ở CƠ SỞ ĐÓ.
		 *
		 * Anh Thắng 23/08/2026: *"Để gán nhân viên theo cơ sở"*.
		 *
		 * Không có chốt này thì bất kỳ ai đăng nhập cũng chốt được ghế ở cơ sở khác — và một
		 * lượt chốt nhầm không chỉ ghi sai sổ: nó ĐÓNG MỐC CHỈ SỐ của ghế đó. Người thu thật ở
		 * cơ sở kia hôm sau chốt sẽ thấy quãng bị cắt mất, và số tiền của họ tự nhiên hụt đi
		 * đúng phần người kia đã chốt hộ.
		 *
		 * ⚠️ Cơ sở RỖNG = đi được cả chuỗi, đúng như `VHG_Auth` vẫn hiểu. Quản lý vùng và Admin
		 *    không khai cơ sở, và họ phải chốt được ở mọi nơi.
		 * ⚠️ `null` nghĩa là NƠI GỌI KHÔNG QUAN TÂM (màn quản trị, phép thử). Khác hẳn chuỗi
		 *    rỗng — gộp hai thứ đó làm một thì mọi lượt gọi cũ vô tình thành "đi cả chuỗi".
		 * ═════════════════════════════════════════════════════════════════════════════════ */
		if ( null !== $coso_cua_toi && '' !== trim( (string) $coso_cua_toi ) ) {
			$cs_ghe = trim( (string) ( isset( $may_['coso_ten'] ) ? $may_['coso_ten'] : '' ) );
			if ( $cs_ghe !== trim( (string) $coso_cua_toi ) ) {
				return array( 'ok' => false, 'error' => 'Ghế ' . $m . ' thuộc cơ sở '
					. ( '' !== $cs_ghe ? $cs_ghe : '(chưa gán)' ) . ', không phải cơ sở của anh/chị ('
					. trim( (string) $coso_cua_toi ) . '). Báo quản lý nếu ghế này vừa chuyển về đây.' );
			}
		}

		$tr = self::chot_truoc( $m );
		/* Mốc chỉ số DÙNG CHUNG (chốt ca + báo cáo doanh thu) — xem `moc_chi_so`. */
		$moc = self::moc_chi_so( $m, $tr );
		/* 🔴 ĐÓNG MỐC TRƯỚC, RỒI MỚI CỘNG. Lấy `den_id` xong mới cộng trong khoảng
		   (tu_id, den_id] thì một dòng rơi vào đúng lúc này sẽ nằm trọn trong kỳ SAU — không
		   mất, không lặp. Cộng trước rồi mới đóng mốc thì dòng đó lọt ra ngoài cả hai kỳ.
		   `tu_id`/`den_id` là quãng của bảng `thu` (đo bằng số dòng), độc lập với mốc chỉ số —
		   nên mốc dùng chung KHÔNG đụng tới hai con số này. */
		$den = self::thu_moi_nhat();
		$tu  = $tr ? (int) $tr['den_id'] : 0;
		return array(
			'ok'            => true,
			'ma_may'        => $m,
			/* Gửi kèm cơ sở và tình trạng kết nối: màn chốt ca của người thu KHÔNG được gọi
			   `so_may` nữa (việc đó nay chỉ quản trị làm được), nên nó phải tự đủ. */
			'coso'          => (string) ( isset( $may_['coso_ten'] ) ? $may_['coso_ten'] : '' ),
			'song'          => ! empty( $may_['con_song'] ) ? 1 : 0,
			'lan_dau'       => $moc['lan_dau'],
			'chi_so_truoc'  => (int) $moc['cs'],
			'chi_so_truoc_nguon' => $moc['nguon'],   // 'chot' | 'bao_cao' — mốc lấy từ đâu
			'chot_truoc_luc' => $tr ? (string) $tr['tao_luc'] : '',
			'chot_truoc_ai' => $tr ? (string) $tr['nguoi'] : '',
			'don_vi'        => self::don_vi(),
			'tu_id'         => $tu,
			'den_id'        => $den,
			'theo_he_thong' => self::may_bao( $m, $tu, $den ),
		);
	}

	/**
	 * PREFETCH CẢ CƠ SỞ — số liệu chốt của MỌI ghế trong một cơ sở, trong MỘT lượt gọi.
	 *
	 * Anh Thắng 26/08/2026: *"khi lấy máy đầu tiên thì máy trạm sẽ phải lấy sẵn thông tin của cơ
	 * sở đó trước. Để máy sau chỉ bấm phát ăn ngay."*
	 *
	 * 🔴 VÌ SAO GỘP MỘT LƯỢT. Máy trạm chạy 4G, mỗi lượt AT-HTTP mất 3–6 giây. Hỏi `chot_xem` cho
	 *    từng ghế là nhân viên đứng chờ chừng ấy giây MỖI GHẾ. Gọi một lần lúc chốt ghế đầu, tải
	 *    sẵn cả cơ sở vào bộ nhớ máy trạm, thì ghế sau chỉ dò AP lấy mã rồi hiện ngay.
	 *
	 * 🔴 VẪN GIỮ RÀO CƠ SỞ. Trả về đúng những ghế người này được chốt: người gắn cơ sở chỉ thấy
	 *    ghế cơ sở mình. Đây là cùng luật với `truoc_khi_chot`, chỉ khác là làm hàng loạt.
	 *    `den_id` (mốc đóng quãng) lấy MỘT LẦN cho cả cơ sở — cùng một thời điểm, nên mọi ghế cùng
	 *    một mốc, không lệch nhau vì gọi trước gọi sau.
	 *
	 * ⚠️ `den_id`/`theo_he_thong` ở đây là ẢNH CHỤP lúc prefetch. Lúc chốt thật, `chot()` tự đóng
	 *    mốc lại theo `thu_moi_nhat()` mới nhất — nên tiền vào ngăn giữa lúc prefetch và lúc chốt
	 *    KHÔNG bị bỏ sót. Con số prefetch chỉ để HIỆN cho nhân viên đối chiếu, không phải để ghi.
	 *
	 * @param string|null $coso_cua_toi Cơ sở của người gọi (từ phiên). null = không giới hạn.
	 * @param string      $chi_coso     Lọc thêm về đúng một cơ sở (tên). Rỗng = mọi cơ sở được phép.
	 */
	public static function chot_coso( $coso_cua_toi = null, $chi_coso = '' ) {
		$cs_toi = null !== $coso_cua_toi ? trim( (string) $coso_cua_toi ) : null;
		$loc    = trim( (string) $chi_coso );
		$den    = self::thu_moi_nhat();
		$dv     = self::don_vi();
		$ds     = array();
		foreach ( VHG_May::ds_may() as $may_ ) {
			$cs_ghe = trim( (string) ( isset( $may_['coso_ten'] ) ? $may_['coso_ten'] : '' ) );
			/* Rào cơ sở của người gọi: cơ sở RỖNG (Admin/Quản lý vùng) = đi cả chuỗi. */
			if ( null !== $cs_toi && '' !== $cs_toi && $cs_ghe !== $cs_toi ) { continue; }
			/* Lọc thêm nếu nơi gọi chỉ định một cơ sở. */
			if ( '' !== $loc && $cs_ghe !== $loc ) { continue; }
			$m  = (string) $may_['ma'];
			$tr = self::chot_truoc( $m );
			$moc = self::moc_chi_so( $m, $tr );   // mốc dùng chung chốt ca + báo cáo
			$tu = $tr ? (int) $tr['den_id'] : 0;
			$ds[] = array(
				'ma_may'        => $m,
				'coso'          => $cs_ghe,
				'song'          => ! empty( $may_['con_song'] ) ? 1 : 0,
				'lan_dau'       => $moc['lan_dau'],
				'chi_so_truoc'  => (int) $moc['cs'],
				'chi_so_truoc_nguon' => $moc['nguon'],
				'chot_truoc_luc' => $tr ? (string) $tr['tao_luc'] : '',
				'chot_truoc_ai' => $tr ? (string) $tr['nguoi'] : '',
				'don_vi'        => $dv,
				'tu_id'         => $tu,
				'den_id'        => $den,
				'theo_he_thong' => self::may_bao( $m, $tu, $den ),
			);
		}
		return array( 'ok' => true, 'den_id' => $den, 'don_vi' => $dv,
			'so_ghe' => count( $ds ), 'ghe' => $ds );
	}

	/**
	 * GHI MỘT LƯỢT CHỐT CA.
	 *
	 * @param string $ma_may  Ghế — đến từ mã QR dán trên ghế, không phải từ một ô chọn.
	 * @param int    $chi_so  Chỉ số đọc trên màn đếm của máy tiền mặt, NGAY LÚC NÀY.
	 * @param int    $tien_dem Tiền mặt đếm được thật trong ngăn.
	 * @param string $nguoi   Ai chốt — lấy từ phiên đăng nhập, KHÔNG nhận từ gói tin.
	 */
	public static function chot( $ma_may, $chi_so, $tien_dem, $nguoi, $ghi_chu = '', $ma_lan = '',
		$coso_cua_toi = null ) {
		global $wpdb;

		/* ══════════════════════════════════════════════════════════════════════════════════
		 * 🔴 GỬI LẠI THÌ TRẢ VỀ LƯỢT CŨ, KHÔNG GHI LƯỢT MỚI.
		 *
		 * App Android chốt ca ở chỗ sóng yếu. Nó gửi đi, chờ mãi không thấy trả lời (mà máy chủ
		 * thì đã ghi xong rồi — chỉ có gói tin trả về là rơi), rồi gửi lại. Không có chốt này
		 * thì lượt gửi lại thành một lần chốt thứ hai: chỉ số nhảy hai lần, tiền trên tay cộng
		 * đôi, và người thu bỗng nợ gấp đôi số họ đang cầm.
		 *
		 * ⚠️ TRA TRƯỚC KHI GHI, và CHỐT THẬT nằm ở khoá UNIQUE dưới tầng SQL — tra rồi ghi là
		 *    hai lượt gửi lại cùng lúc vẫn lọt qua khe giữa hai câu lệnh.
		 * ═════════════════════════════════════════════════════════════════════════════════ */
		$ml = mb_substr( trim( (string) $ma_lan ), 0, 40 );
		if ( '' !== $ml ) {
			$cu = $wpdb->get_row( $wpdb->prepare(
				'SELECT * FROM ' . VHG_DB::t( 'chot' ) . ' WHERE ma_lan=%s LIMIT 1', $ml ), ARRAY_A );
			if ( $cu ) {
				$r_ = self::doc_chot( $cu );
				$r_['ok'] = true;
				$r_['lap_lai'] = 1;
				$r_['thong_bao'] = 'Lượt chốt này đã ghi rồi — không ghi thêm lần nữa.';
				$r_['dang_cam']  = self::dang_cam( (string) $cu['nguoi'] );
				return $r_;
			}
		}

		/* 🔴 TÊN NGƯỜI CHỐT LÀ BẮT BUỘC. Một lượt chuyển tay tiền mặt không tên là thứ không ai
		   giải thích được khi kiểm quỹ thiếu — và người thu thì luôn biết mình là ai. */
		$ai = trim( (string) $nguoi );
		if ( '' === $ai ) {
			return array( 'ok' => false, 'error' => 'Chưa biết ai đang chốt — không ghi sổ được.' );
		}

		$t_ = self::truoc_khi_chot( $ma_may, $coso_cua_toi );
		if ( empty( $t_['ok'] ) ) { return $t_; }
		$m = $t_['ma_may'];

		$cs  = (int) $chi_so;
		$dem = (int) $tien_dem;
		if ( $cs < 0 )  { return array( 'ok' => false, 'error' => 'Chỉ số không âm được.' ); }
		if ( $dem < 0 ) { return array( 'ok' => false, 'error' => 'Số tiền đếm được không âm được.' ); }

		$truoc = (int) $t_['chi_so_truoc'];
		/* 🔴 MÁY ĐẾM KHÔNG CHẠY LÙI. Chỉ số nhỏ hơn lần trước nghĩa là một trong hai: cục nhận
		   tiền vừa bị thay/reset, hoặc người gõ nhầm. Cả hai đều phải NÓI RA rồi mới ghi — ghi
		   lặng lẽ thì `theo_may` ra số âm và mọi bảng đối chiếu sau đó vô nghĩa.
		   Cho qua khi có ghi chú: thay cục nhận tiền là chuyện có thật, và người đứng đó là
		   người duy nhất biết chuyện gì vừa xảy ra. */
		if ( ! $t_['lan_dau'] && $cs < $truoc ) {
			if ( '' === trim( (string) $ghi_chu ) ) {
				return array( 'ok' => false, 'error' => 'Chỉ số ' . number_format( $cs, 0, ',', '.' )
					. ' NHỎ HƠN lần chốt trước (' . number_format( $truoc, 0, ',', '.' ) . '). '
					. 'Máy đếm không chạy lùi — kiểm lại con số. Nếu vừa thay cục nhận tiền thì '
					. 'ghi rõ vào ô ghi chú rồi bấm lại.' );
			}
		}

		$dv  = self::don_vi();
		$hth = (int) $t_['theo_he_thong'];
		/* Lần đầu của một ghế thì KHÔNG có mốc để trừ. Lấy 0 làm mốc là ra một con số khổng lồ
		   trông như doanh thu thất thoát cả năm — thà nói thẳng "lần đầu, chưa tính lệch được". */
		$lan_dau  = (int) $t_['lan_dau'];
		$theo_may = $lan_dau ? 0 : max( 0, $cs - $truoc ) * $dv;

		$luc = current_time( 'mysql' );
		$wpdb->insert( VHG_DB::t( 'chot' ), array(
			'ma_may' => $m, 'nguoi' => $ai,
			'chi_so' => $cs, 'chi_so_truoc' => $lan_dau ? 0 : $truoc,
			/* Chép lại đơn vị ĐANG DÙNG. Khai lại đơn vị sau này không được làm đổi con số của
			   những lượt đã chốt — sổ phải giữ nguyên cái đã ghi. */
			'don_vi' => $dv,
			'theo_may' => $theo_may, 'theo_he_thong' => $hth, 'tien_dem' => $dem,
			'lech_dem' => $lan_dau ? 0 : ( $dem - $theo_may ),
			'lech_may' => $lan_dau ? 0 : ( $theo_may - $hth ),
			'lan_dau' => $lan_dau,
			'tu_id' => (int) $t_['tu_id'], 'den_id' => (int) $t_['den_id'],
			'tu_luc' => '' !== (string) $t_['chot_truoc_luc'] ? $t_['chot_truoc_luc'] : null,
			'tao_luc' => $luc,
			'ghi_chu' => mb_substr( trim( (string) $ghi_chu ), 0, 250 ),
			'nop_id' => 0,
			'ma_lan' => '' !== $ml ? $ml : null,
		) );
		$id = (int) $wpdb->insert_id;
		if ( ! $id ) {
			/* Ghi trượt mà có mã lượt thì gần như chắc chắn là khoá UNIQUE vừa chặn một lượt gửi
			   lại chạy song song — tra lại và trả về đúng lượt kia, đừng báo lỗi cho người đang
			   đứng ở ghế. */
			if ( '' !== $ml ) {
				$cu2 = $wpdb->get_row( $wpdb->prepare(
					'SELECT * FROM ' . VHG_DB::t( 'chot' ) . ' WHERE ma_lan=%s LIMIT 1', $ml ), ARRAY_A );
				if ( $cu2 ) {
					$r2 = self::doc_chot( $cu2 );
					$r2['ok'] = true; $r2['lap_lai'] = 1;
					$r2['thong_bao'] = 'Lượt chốt này đã ghi rồi — không ghi thêm lần nữa.';
					$r2['dang_cam']  = self::dang_cam( (string) $cu2['nguoi'] );
					return $r2;
				}
			}
			return array( 'ok' => false, 'error' => 'Không ghi được lượt chốt, thử lại.' );
		}

		$r = self::mot_chot( $id );
		$r['ok'] = true;
		$r['thong_bao'] = 'Đã chốt ghế ' . $m . ' — nhận ' . number_format( $dem, 0, ',', '.' ) . 'đ.'
			. ( $lan_dau
				? ' Đây là lần chốt ĐẦU TIÊN của ghế này, nên chưa có mốc để trừ; từ kỳ sau mới đối chiếu được.'
				: '' );
		$r['dang_cam'] = self::dang_cam( $ai );
		return $r;
	}

	public static function mot_chot( $id ) {
		global $wpdb;
		$r = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . VHG_DB::t( 'chot' ) . ' WHERE id=%d LIMIT 1', (int) $id ), ARRAY_A );
		return $r ? self::doc_chot( $r ) : array();
	}

	/** Một dòng chốt, đã đổi sang số nguyên và gắn thêm lời đọc cho người. */
	protected static function doc_chot( $r ) {
		$ld = ! empty( $r['lan_dau'] );
		return array(
			'id' => (int) $r['id'], 'ma_may' => (string) $r['ma_may'], 'nguoi' => (string) $r['nguoi'],
			'chi_so' => (int) $r['chi_so'], 'chi_so_truoc' => (int) $r['chi_so_truoc'],
			'don_vi' => (int) $r['don_vi'],
			'theo_may' => (int) $r['theo_may'], 'theo_he_thong' => (int) $r['theo_he_thong'],
			'tien_dem' => (int) $r['tien_dem'],
			'lech_dem' => (int) $r['lech_dem'], 'lech_may' => (int) $r['lech_may'],
			'lan_dau' => $ld ? 1 : 0,
			'tu_id' => (int) $r['tu_id'], 'den_id' => (int) $r['den_id'],
			'tu_luc' => (string) $r['tu_luc'], 'tao_luc' => (string) $r['tao_luc'],
			'ghi_chu' => (string) $r['ghi_chu'], 'nop_id' => (int) $r['nop_id'],
			'lap_lai' => 0,
			'canh_bao' => $ld ? '' : self::doc_lech( (int) $r['lech_dem'], (int) $r['lech_may'] ),
		);
	}

	/**
	 * Đọc hai con lệch ra tiếng người.
	 *
	 * ⚠️ Nói RIÊNG từng cái, và nói rõ đi hỏi ai. "Chênh lệch 40.000đ" là câu không hành động
	 *    được; "ngăn thiếu 40.000đ so với máy đếm" thì biết ngay phải đi tìm ai.
	 */
	public static function doc_lech( $lech_dem, $lech_may ) {
		$c = array();
		$ld = (int) $lech_dem;
		$lm = (int) $lech_may;
		if ( 0 !== $ld ) {
			$c[] = ( $ld < 0 ? 'Ngăn THIẾU ' : 'Ngăn THỪA ' )
				. number_format( abs( $ld ), 0, ',', '.' ) . 'đ so với máy đếm.';
		}
		if ( 0 !== $lm ) {
			$c[] = ( $lm > 0
				? 'Máy đếm nói đã nuốt nhiều hơn sổ ' . number_format( $lm, 0, ',', '.' )
					. 'đ — doanh thu đang THIẾU trong sổ (ghế mất mạng hoặc sót xung).'
				: 'Sổ ghi nhiều hơn máy đếm ' . number_format( abs( $lm ), 0, ',', '.' )
					. 'đ — soi lại xem có lượt nào vào sổ hai lần.' );
		}
		return implode( ' ', $c );
	}

	/** Danh sách lượt chốt, mới nhất trước. */
	public static function ds_chot( $ky = 'month', $gioi_han = 200 ) {
		global $wpdb;
		$t   = VHG_DB::t( 'chot' );
		$dau = VHG_Thu::dau_ky( $ky );
		$gh  = max( 1, min( 500, (int) $gioi_han ) );
		$sql = '' !== $dau
			? $wpdb->prepare( "SELECT * FROM $t WHERE tao_luc>=%s ORDER BY id DESC LIMIT %d", $dau, $gh )
			: $wpdb->prepare( "SELECT * FROM $t ORDER BY id DESC LIMIT %d", $gh );
		$ra = array();
		foreach ( VHG_DB::rows( $sql ) as $r ) { $ra[] = self::doc_chot( $r ); }
		return $ra;
	}

	/**
	 * LƯỢT CHỐT GẦN NHẤT CỦA TỪNG GHẾ — cho thẻ ghế ở màn điều khiển.
	 *
	 * Anh Thắng 23/08/2026: *"Hiện chỉ số máy — cũ và mới"*.
	 *
	 * 🔴 ĐỨNG CẠNH GHẾ MÀ SO ĐƯỢC NGAY. Người đi thu cầm điện thoại, nhìn màn máy đếm trên ghế,
	 *    rồi nhìn thẻ ghế trên app: chỉ số hệ thống ghi lần trước là bao nhiêu, và chỉ số thật
	 *    bây giờ là bao nhiêu. Chênh lệch giữa hai con số đó CHÍNH LÀ số tiền đang nằm trong
	 *    ngăn — biết trước khi mở ngăn thì đếm xong là biết đủ hay thiếu ngay, không phải đợi
	 *    tới lúc chốt mới thấy con số đỏ.
	 *
	 * ⚠️ MỘT LƯỢT HỎI CHO TẤT CẢ GHẾ, không hỏi từng ghế một. Màn điều khiển vẽ hàng chục thẻ;
	 *    hỏi từng thẻ là hàng chục lượt truy vấn cho một màn hình tải lại mỗi mười giây.
	 */
	public static function chot_cuoi_theo_may() {
		global $wpdb;
		$t = VHG_DB::t( 'chot' );
		/* Lấy id lớn nhất mỗi ghế rồi nối lại lấy cả dòng — `GROUP BY` trần trả về cột của một
		   dòng bất kỳ trong nhóm, và "bất kỳ" ở đây là một chỉ số cũ hơn đang nằm im. */
		$sql = "SELECT c.* FROM $t c
			INNER JOIN ( SELECT ma_may, MAX(id) AS id_moi FROM $t GROUP BY ma_may ) m
			ON c.id = m.id_moi";
		$ra = array();
		foreach ( VHG_DB::rows( $sql ) as $r ) {
			$ra[ (string) $r['ma_may'] ] = array(
				'chi_so'       => (int) $r['chi_so'],
				'chi_so_truoc' => (int) $r['chi_so_truoc'],
				'don_vi'       => (int) $r['don_vi'],
				'tien_dem'     => (int) $r['tien_dem'],
				'nguoi'        => (string) $r['nguoi'],
				'tao_luc'      => (string) $r['tao_luc'],
				'lan_dau'      => (int) $r['lan_dau'],
			);
		}
		return $ra;
	}

	// ═════════════════════════════════════════════════════════════════ tiền trên tay

	/**
	 * TIỀN MẶT ĐANG NẰM TRONG TAY MỘT NGƯỜI.
	 *
	 * BA nguồn, và cả ba đều là tiền thật đang ở ngoài két:
	 *   · `chot`     — xấp tiền vừa lấy ra khỏi ngăn ghế. KHÔNG phải doanh thu mới (ghế đã ghi sổ
	 *                  từ lúc nuốt), chỉ là một lần chuyển tay.
	 *   · `thu`      — khách trả tiền mặt tại quầy, người thu bấm cho ghế chạy. Cái này VỪA là
	 *                  doanh thu VỪA là tiền trên tay.
	 *   · `bc`/`bc_dong` (thêm 29/08/2026) — tiền mặt nhân viên KHAI qua màn "Báo cáo doanh thu"
	 *     (chỉ số/QR nhập tay, không quét QR ghế). Anh Thắng: *"Sau khi nhân viên chốt báo cáo
	 *     doanh thu, thì nó sẽ hiển ở đây là doanh thu nhân viên đang cầm"* — báo cáo online
	 *     KHÔNG đồng nghĩa tiền đã về tay quản lý, nên vẫn phải tính là "đang cầm" cho tới khi
	 *     nhân viên bấm Nộp (`nop_id` khác 0, xem `nop()`). Xem chú thích cột `bc.nop_id` ở
	 *     class-vhg-db.php để phân biệt với `bc.nop_so_tien` (con số TỰ KHAI, khác khái niệm).
	 *
	 * ⚠️ Bỏ dòng `thu` đã HUỶ. Huỷ nghĩa là lượt đó không có thật, mà tiền không có thật thì
	 *    không ai phải nộp.
	 */
	public static function dang_cam( $nguoi ) {
		global $wpdb;
		$ai = trim( (string) $nguoi );
		if ( '' === $ai ) {
			return array( 'nguoi' => '', 'tong' => 0, 'tu_ghe' => 0, 'tu_quay' => 0, 'tu_bao_cao' => 0, 'so_dong' => 0 );
		}

		$tc = VHG_DB::t( 'chot' );
		$ghe = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(SUM(tien_dem),0) FROM $tc WHERE nguoi=%s AND nop_id=0", $ai ) );
		$n_ghe = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $tc WHERE nguoi=%s AND nop_id=0", $ai ) );

		$tt = VHG_DB::t( 'thu' );
		$nd = VHG_Thu::ND_THU_TAY . $ai;
		$quay = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(SUM(so_tien),0) FROM $tt
			 WHERE nguon=%s AND noi_dung=%s AND huy=0 AND nop_id=0", VHG_Thu::TIEN_MAT, $nd ) );
		$n_quay = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $tt
			 WHERE nguon=%s AND noi_dung=%s AND huy=0 AND nop_id=0", VHG_Thu::TIEN_MAT, $nd ) );

		$tb  = VHG_DB::t( 'bc' );
		$tbd = VHG_DB::t( 'bc_dong' );
		$bao_cao = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(SUM(d.tien_mat),0) FROM $tbd d JOIN $tb h ON h.report_id=d.report_id
			 WHERE h.nhan_vien=%s AND h.nop_id=0", $ai ) );
		$n_bao_cao = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $tb WHERE nhan_vien=%s AND nop_id=0", $ai ) );

		return array( 'nguoi' => $ai, 'tong' => $ghe + $quay + $bao_cao,
			'tu_ghe' => $ghe, 'tu_quay' => $quay, 'tu_bao_cao' => $bao_cao,
			'so_dong' => $n_ghe + $n_quay + $n_bao_cao,
			'theo_coso' => self::dang_cam_theo_coso( $ai ) );
	}

	/** Nhãn cho tiền không tra ra được cơ sở nào — dùng CHUNG cho cả đường đọc lẫn đường nộp. */
	const CS_CHUA_GAN = '(chưa gán)';

	/**
	 * TIỀN MỘT NGƯỜI ĐANG CẦM, TÁCH RA TỪNG CƠ SỞ.
	 *
	 * Anh Thắng 05/09/2026: *"chỗ phần tôi đang cầm tiền: hiện cơ sở nào chưa nộp · khi nhân
	 * viên nộp hoặc gửi bill thì tích vào sẽ nộp cơ sở nào"*.
	 *
	 * 🔴 MỘT NGƯỜI ĐI NHIỀU CƠ SỞ TRONG MỘT VÒNG, NHƯNG KHÔNG NỘP HẾT MỘT LƯỢT. Ghé ba nơi, nộp
	 *    tiền hai nơi đầu ở quầy rồi mới đi nốt nơi thứ ba — chuyện thường ngày. Bản trước chỉ
	 *    có MỘT con số tổng, nên bấm Nộp là nộp tất, không có cách nào nói "tôi mới nộp tiền của
	 *    hai cơ sở này thôi".
	 *
	 * 🔴 BA NGUỒN TIỀN, BA CÁCH TRA RA CƠ SỞ, nhưng phải gom về CÙNG MỘT TÊN:
	 *      · `chot` (ngăn ghế)    -> `ma_may` -> `may.coso_id` -> `coso.ten`
	 *      · `thu`  (khách trả quầy) -> cùng đường ấy
	 *      · `bc`   (báo cáo doanh thu) -> `bc.coso` đã là TÊN sẵn
	 *    Hai đường đầu đi qua bảng `may`, nên ghế chưa gán cơ sở (hoặc mã ghế không còn trong
	 *    danh mục) sẽ tra ra rỗng. Những đồng ấy KHÔNG ĐƯỢC RƠI MẤT — gom vào một nhóm mang tên
	 *    `(chưa gán)`, vì tổng của các nhóm phải bằng đúng tổng đang cầm; lệch một đồng là người
	 *    ta thôi tin cả cái bảng.
	 */
	public static function dang_cam_theo_coso( $nguoi ) {
		global $wpdb;
		$ai = trim( (string) $nguoi );
		if ( '' === $ai ) { return array(); }
		$tc  = VHG_DB::t( 'chot' );
		$tt  = VHG_DB::t( 'thu' );
		$tb  = VHG_DB::t( 'bc' );
		$tbd = VHG_DB::t( 'bc_dong' );
		$tm  = VHG_DB::t( 'may' );
		$tcs = VHG_DB::t( 'coso' );
		$ra  = array();

		$vao = function ( $ten, $khoa, $tien, $dong ) use ( &$ra ) {
			$ten = '' !== trim( (string) $ten ) ? (string) $ten : self::CS_CHUA_GAN;
			if ( ! isset( $ra[ $ten ] ) ) {
				$ra[ $ten ] = array( 'coso' => $ten, 'tu_ghe' => 0, 'tu_quay' => 0,
					'tu_bao_cao' => 0, 'tong' => 0, 'so_dong' => 0 );
			}
			$ra[ $ten ][ $khoa ] += (int) $tien;
			$ra[ $ten ]['tong']  += (int) $tien;
			$ra[ $ten ]['so_dong'] += (int) $dong;
		};

		/* LEFT JOIN chứ không JOIN: ghế đã xoá khỏi danh mục thì tiền của nó vẫn đang trên tay
		   ai đó, và JOIN thường sẽ nuốt mất đúng những đồng ấy. */
		foreach ( VHG_DB::rows( $wpdb->prepare(
			"SELECT COALESCE(cs.ten,'') AS ten, SUM(c.tien_dem) AS t, COUNT(*) AS n
			 FROM $tc c LEFT JOIN $tm m ON m.ma=c.ma_may LEFT JOIN $tcs cs ON cs.id=m.coso_id
			 WHERE c.nguoi=%s AND c.nop_id=0 GROUP BY cs.ten", $ai ) ) as $r ) {
			$vao( $r['ten'], 'tu_ghe', $r['t'], $r['n'] );
		}

		foreach ( VHG_DB::rows( $wpdb->prepare(
			"SELECT COALESCE(cs.ten,'') AS ten, SUM(x.so_tien) AS t, COUNT(*) AS n
			 FROM $tt x LEFT JOIN $tm m ON m.ma=x.ma_may LEFT JOIN $tcs cs ON cs.id=m.coso_id
			 WHERE x.nguon=%s AND x.noi_dung=%s AND x.huy=0 AND x.nop_id=0 GROUP BY cs.ten",
			VHG_Thu::TIEN_MAT, VHG_Thu::ND_THU_TAY . $ai ) ) as $r ) {
			$vao( $r['ten'], 'tu_quay', $r['t'], $r['n'] );
		}

		foreach ( VHG_DB::rows( $wpdb->prepare(
			"SELECT h.coso AS ten, COALESCE(SUM(d.tien_mat),0) AS t, COUNT(DISTINCT h.report_id) AS n
			 FROM $tb h LEFT JOIN $tbd d ON d.report_id=h.report_id
			 WHERE h.nhan_vien=%s AND h.nop_id=0 GROUP BY h.coso", $ai ) ) as $r ) {
			$vao( $r['ten'], 'tu_bao_cao', $r['t'], $r['n'] );
		}

		/* ⚠️ BỎ NHÓM 0 ĐỒNG. Báo cáo toàn QR không có đồng tiền mặt nào phải nộp, nhưng vẫn đếm
		   ra một dòng — bày một cơ sở "0đ" kèm ô tích là mời người ta tích vào rồi bấm Nộp và
		   nhận về câu "đang không cầm đồng nào". */
		$ra = array_filter( $ra, function ( $x ) { return (int) $x['tong'] > 0; } );
		usort( $ra, function ( $a, $b ) { return strcmp( $a['coso'], $b['coso'] ); } );
		return array_values( $ra );
	}

	/**
	 * AI ĐANG CẦM BAO NHIÊU — bảng quản lý nhìn mỗi sáng.
	 *
	 * ⚠️ Gom từ CẢ HAI bảng rồi cộng lại, chứ không hỏi từng người một: danh sách người thu
	 *    không nằm ở đâu cả (người nghỉ việc vẫn có thể đang cầm tiền), nên phải đi từ chính
	 *    những dòng chưa nộp mà tìm ngược ra tên.
	 */
	public static function ai_dang_cam() {
		global $wpdb;
		$ra = array();

		$tc = VHG_DB::t( 'chot' );
		foreach ( VHG_DB::rows( "SELECT nguoi, SUM(tien_dem) AS t, COUNT(*) AS n
			FROM $tc WHERE nop_id=0 AND nguoi<>'' GROUP BY nguoi" ) as $r ) {
			$k = (string) $r['nguoi'];
			$ra[ $k ] = array( 'nguoi' => $k, 'tu_ghe' => (int) $r['t'], 'tu_quay' => 0, 'tu_bao_cao' => 0,
				'so_dong' => (int) $r['n'], 'tong' => (int) $r['t'] );
		}

		$tt  = VHG_DB::t( 'thu' );
		$sql = $wpdb->prepare( "SELECT noi_dung, SUM(so_tien) AS t, COUNT(*) AS n
			FROM $tt WHERE nguon=%s AND huy=0 AND nop_id=0 AND noi_dung LIKE %s
			GROUP BY noi_dung", VHG_Thu::TIEN_MAT, $wpdb->esc_like( VHG_Thu::ND_THU_TAY ) . '%' );
		foreach ( VHG_DB::rows( $sql ) as $r ) {
			$k = VHG_Thu::nguoi_thu( $r['noi_dung'] );
			if ( '' === $k ) { continue; }
			if ( ! isset( $ra[ $k ] ) ) {
				$ra[ $k ] = array( 'nguoi' => $k, 'tu_ghe' => 0, 'tu_quay' => 0, 'tu_bao_cao' => 0, 'so_dong' => 0, 'tong' => 0 );
			}
			$ra[ $k ]['tu_quay'] += (int) $r['t'];
			$ra[ $k ]['so_dong'] += (int) $r['n'];
			$ra[ $k ]['tong']    += (int) $r['t'];
		}

		/* Nguồn thứ ba (29/08/2026): tiền mặt khai qua "Báo cáo doanh thu" — xem chú thích đầy đủ
		   ở dang_cam() phía trên và ở cột `bc.nop_id` (class-vhg-db.php). */
		$tb  = VHG_DB::t( 'bc' );
		$tbd = VHG_DB::t( 'bc_dong' );
		$sqlBc = "SELECT h.nhan_vien AS nguoi, SUM(d.tien_mat) AS t, COUNT(DISTINCT h.report_id) AS n
			FROM $tbd d JOIN $tb h ON h.report_id=d.report_id
			WHERE h.nop_id=0 AND h.nhan_vien<>'' GROUP BY h.nhan_vien";
		foreach ( VHG_DB::rows( $sqlBc ) as $r ) {
			$k = (string) $r['nguoi'];
			if ( ! isset( $ra[ $k ] ) ) {
				$ra[ $k ] = array( 'nguoi' => $k, 'tu_ghe' => 0, 'tu_quay' => 0, 'tu_bao_cao' => 0, 'so_dong' => 0, 'tong' => 0 );
			}
			$ra[ $k ]['tu_bao_cao'] += (int) $r['t'];
			$ra[ $k ]['so_dong']    += (int) $r['n'];
			$ra[ $k ]['tong']       += (int) $r['t'];
		}

		$ds = array_values( $ra );
		usort( $ds, function ( $a, $b ) { return $b['tong'] - $a['tong']; } );
		return $ds;
	}

	// ═════════════════════════════════════════════════════════════════════ nộp tiền

	/**
	 * NỘP TIỀN VỀ QUẦY.
	 *
	 * 🔴 GẮN DÒNG TRƯỚC, CỘNG TIỀN SAU — và cộng từ CHÍNH NHỮNG DÒNG GẮN ĐƯỢC.
	 *
	 *    Cộng trước rồi gắn sau thì hai người bấm nộp cùng lúc (hoặc cùng một người bấm hai lần
	 *    vì mạng chậm) sẽ cho ra hai lượt nộp cùng mang một số tiền, trong khi chỉ có một xấp
	 *    tiền. `UPDATE ... WHERE nop_id=0` là chốt: lượt thứ hai gắn được 0 dòng, và một lượt
	 *    nộp 0 đồng thì bị huỷ ngay chứ không được nằm lại trong sổ.
	 *
	 * ⚠️ TÊN NGƯỜI NỘP LẤY TỪ PHIÊN ĐĂNG NHẬP, không nhận từ gói tin. Nhận từ gói tin là ai
	 *    cũng nộp hộ được người khác, tức là ai cũng xoá được nợ tiền mặt của người khác.
	 */
	public static function nop( $nguoi, $ghi_chu = '', $ma_lan = '', $coso_ds = null ) {
		global $wpdb;
		$ai = trim( (string) $nguoi );
		if ( '' === $ai ) {
			return array( 'ok' => false, 'error' => 'Chưa biết ai đang nộp — không ghi sổ được.' );
		}

		/* 🔴 NỘP THEO CƠ SỞ ĐƯỢC TÍCH — anh Thắng 05/09/2026: *"khi nhân viên nộp hoặc gửi bill
		   thì tích vào sẽ nộp cơ sở nào"*. Một người đi ba nơi trong một vòng nhưng nộp tiền hai
		   nơi đầu ở quầy rồi mới đi nốt nơi thứ ba — chuyện thường ngày, mà bản trước bấm Nộp là
		   nộp tất, không có cách nào nói "tôi mới nộp hai cơ sở này thôi".

		   ⚠️ `null` HOẶC MẢNG RỖNG = NỘP TẤT, y như trước. Đây là đường mọi nơi khác trong hệ vẫn
		      gọi (app, nộp thay, nộp qua màn quỹ cũ); đổi nghĩa của nó thành "nộp 0 đồng" là làm
		      hỏng những chỗ chưa hề biết tới tính năng này. */
		$loc_cs = array();
		if ( is_array( $coso_ds ) ) {
			foreach ( $coso_ds as $c ) {
				$c = trim( (string) $c );
				if ( '' !== $c && ! in_array( $c, $loc_cs, true ) ) { $loc_cs[] = $c; }
			}
		}
		/* Câu WHERE thêm vào ba lệnh gắn dòng bên dưới. Ghế chưa gán cơ sở (hoặc mã ghế không
		   còn trong danh mục) tra ra NULL — nhóm `(chưa gán)` phải bắt được đúng những dòng ấy,
		   không thì tiền của chúng nằm lại vĩnh viễn: không nhóm nào nộp được. */
		$dk_may = ''; $dk_bc = '';
		if ( count( $loc_cs ) ) {
			$tm  = VHG_DB::t( 'may' );
			$tcs = VHG_DB::t( 'coso' );
			$ten = array(); $co_chua_gan = false;
			foreach ( $loc_cs as $c ) {
				if ( self::CS_CHUA_GAN === $c ) { $co_chua_gan = true; } else { $ten[] = $c; }
			}
			$in_ten = count( $ten )
				? "SELECT m.ma FROM $tm m JOIN $tcs cs ON cs.id=m.coso_id WHERE cs.ten IN ("
					. implode( ',', array_fill( 0, count( $ten ), '%s' ) ) . ')'
				: '';
			$ve_may = array();
			if ( '' !== $in_ten ) { $ve_may[] = 'ma_may IN (' . $wpdb->prepare( $in_ten, ...$ten ) . ')'; }
			if ( $co_chua_gan ) {
				$ve_may[] = "ma_may NOT IN (SELECT m2.ma FROM $tm m2 JOIN $tcs cs2 ON cs2.id=m2.coso_id)";
			}
			/* 🔴 CÂU NÀY KHÔNG BAO GIỜ ĐƯỢC RỖNG KHI ĐÃ TÍCH — rỗng là lặng lẽ thành NỘP TẤT,
			   đúng thứ người ta vừa cố tránh. Bất biến giữ điều đó là ở ngay trên: mỗi tên trong
			   `$loc_cs` rơi vào đúng một trong hai rổ (`$ten` hoặc `$co_chua_gan`), và mỗi rổ có
			   hàng thì sinh đúng một vế — nên `$loc_cs` không rỗng kéo theo `$ve_may` không rỗng.
			   ⚠️ Đừng thêm một nhánh `count($ve_may) ? … : '1=0'` cho "chắc ăn": nhánh ấy KHÔNG
			      ĐẠT TỚI ĐƯỢC, mà mã chết thì không ai chạy qua để biết nó còn đúng — nó chỉ nằm
			      đó tạo cảm giác an toàn và làm mấy phép thử dò chuỗi tự xanh. Muốn chắc thì giữ
			      bất biến trên, đừng vá ở đây.
			   Tích một cơ sở KHÔNG CÒN TỒN TẠI vẫn an toàn mà không cần nhánh nào: vế `IN
			   (SELECT …)` tra ra rỗng, gắn được 0 dòng, và lượt nộp bị huỷ ngay bên dưới. */
			$dk_may = ' AND (' . implode( ' OR ', $ve_may ) . ')';
			/* Đường báo cáo dựng ĐỐI XỨNG với đường trên: cùng hai rổ, cùng nối OR, cùng bất
			   biến "có tích thì có vế". `bc.coso` đã là TÊN sẵn nên không phải đi qua bảng ghế;
			   báo cáo không có tên cơ sở thì `coso` rỗng — đúng nhóm `(chưa gán)` mà bảng đọc
			   gom chúng vào (xem dang_cam_theo_coso()). */
			$ve_bc = array();
			if ( count( $ten ) ) {
				$ve_bc[] = $wpdb->prepare(
					'coso IN (' . implode( ',', array_fill( 0, count( $ten ), '%s' ) ) . ')', ...$ten );
			}
			if ( $co_chua_gan ) { $ve_bc[] = "coso=''"; }
			$dk_bc = ' AND (' . implode( ' OR ', $ve_bc ) . ')';
		}

		/* 🔴 GỬI LẠI THÌ TRẢ VỀ LƯỢT CŨ — cùng lý do với `chot()`, xem chú thích ở đó.
		   Ở đây còn nặng hơn: một lượt nộp gửi lại mà ghi thành hai lượt thì lượt thứ hai gắn
		   được 0 dòng và bị xoá, nhưng app thì nhận về một câu lỗi "đang không cầm đồng nào" —
		   người nộp đọc câu đó ngay sau khi vừa nộp xong sẽ tưởng tiền của mình bốc hơi. */
		$ml = mb_substr( trim( (string) $ma_lan ), 0, 40 );
		if ( '' !== $ml ) {
			$cu = $wpdb->get_row( $wpdb->prepare(
				'SELECT * FROM ' . VHG_DB::t( 'nop' ) . ' WHERE ma_lan=%s LIMIT 1', $ml ), ARRAY_A );
			if ( $cu ) {
				return array( 'ok' => true, 'id' => (int) $cu['id'], 'lap_lai' => 1,
					'so_tien' => (int) $cu['so_tien'], 'so_dong' => (int) $cu['so_dong'],
					'thong_bao' => 'Lượt nộp này đã ghi rồi — không ghi thêm lần nữa.' );
			}
		}

		$luc = current_time( 'mysql' );
		$wpdb->insert( VHG_DB::t( 'nop' ), array(
			'nguoi' => $ai, 'so_tien' => 0, 'so_tien_nhan' => 0, 'so_dong' => 0,
			'trang_thai' => 'cho', 'tao_luc' => $luc, 'nhan_luc' => null, 'nhan_ai' => '',
			'ghi_chu' => mb_substr( trim( (string) $ghi_chu ), 0, 250 ),
			'ma_lan' => '' !== $ml ? $ml : null ) );
		$id = (int) $wpdb->insert_id;
		if ( ! $id ) {
			if ( '' !== $ml ) {
				$cu2 = $wpdb->get_row( $wpdb->prepare(
					'SELECT * FROM ' . VHG_DB::t( 'nop' ) . ' WHERE ma_lan=%s LIMIT 1', $ml ), ARRAY_A );
				if ( $cu2 ) {
					return array( 'ok' => true, 'id' => (int) $cu2['id'], 'lap_lai' => 1,
						'so_tien' => (int) $cu2['so_tien'], 'so_dong' => (int) $cu2['so_dong'],
						'thong_bao' => 'Lượt nộp này đã ghi rồi — không ghi thêm lần nữa.' );
				}
			}
			return array( 'ok' => false, 'error' => 'Không mở được lượt nộp, thử lại.' );
		}

		$tc = VHG_DB::t( 'chot' );
		$tt = VHG_DB::t( 'thu' );
		$tb = VHG_DB::t( 'bc' );
		$wpdb->query( $wpdb->prepare(
			"UPDATE $tc SET nop_id=%d WHERE nguoi=%s AND nop_id=0", $id, $ai ) . $dk_may );
		$wpdb->query( $wpdb->prepare(
			"UPDATE $tt SET nop_id=%d WHERE nguon=%s AND noi_dung=%s AND huy=0 AND nop_id=0",
			$id, VHG_Thu::TIEN_MAT, VHG_Thu::ND_THU_TAY . $ai ) . $dk_may );
		/* Nguồn thứ ba (29/08/2026) — báo cáo doanh thu. Gắn theo HEADER (`bc.nop_id`), không phải
		   theo từng dòng ghế: một báo cáo là một lần "nộp cả cụm", không tách lẻ từng ghế trong
		   đó — khớp đúng cách chot/thu vẫn gắn theo TỪNG DÒNG hoàn chỉnh của chúng. */
		$wpdb->query( $wpdb->prepare(
			"UPDATE $tb SET nop_id=%d WHERE nhan_vien=%s AND nop_id=0", $id, $ai ) . $dk_bc );

		/* Cộng lại từ đúng những dòng vừa gắn được — không tin con số đã tính trước đó. */
		$tbd = VHG_DB::t( 'bc_dong' );
		$tong = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(SUM(tien_dem),0) FROM $tc WHERE nop_id=%d", $id ) )
			+ (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COALESCE(SUM(so_tien),0) FROM $tt WHERE nop_id=%d", $id ) )
			+ (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COALESCE(SUM(d.tien_mat),0) FROM $tbd d JOIN $tb h ON h.report_id=d.report_id
				 WHERE h.nop_id=%d", $id ) );
		$dong = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $tc WHERE nop_id=%d", $id ) )
			+ (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $tt WHERE nop_id=%d", $id ) )
			+ (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $tb WHERE nop_id=%d", $id ) );

		if ( $tong <= 0 ) {
			/* Không gắn được đồng nào -> xoá luôn lượt nộp. Để lại một dòng 0 đồng là bảng chờ
			   xác nhận đầy những lượt rỗng, và người ta thôi không nhìn nó nữa. */
			$wpdb->delete( VHG_DB::t( 'nop' ), array( 'id' => $id ) );
			return array( 'ok' => false, 'error' => count( $loc_cs )
				? ( 'Không có đồng nào chưa nộp ở cơ sở đã tích (' . implode( ', ', $loc_cs ) . ').' )
				: 'Anh/chị đang không cầm đồng nào chưa nộp.' );
		}

		$wpdb->update( VHG_DB::t( 'nop' ), array( 'so_tien' => $tong, 'so_dong' => $dong ),
			array( 'id' => $id ) );
		return array( 'ok' => true, 'id' => $id, 'so_tien' => $tong, 'so_dong' => $dong, 'lap_lai' => 0,
			'coso' => $loc_cs,
			'thong_bao' => 'Đã nộp ' . number_format( $tong, 0, ',', '.' ) . 'đ (' . $dong . ' lượt'
				. ( count( $loc_cs ) ? ( ', cơ sở: ' . implode( ', ', $loc_cs ) ) : '' )
				. ') — chờ quản lý xác nhận đã nhận đủ.' );
	}

	/**
	 * NỘP RIÊNG MỘT BÁO CÁO DOANH THU — đường của nút "Xác nhận đã nộp" kèm bill chuyển khoản
	 * ở màn nhân viên (VHG_BaoCao::nop_bill).
	 *
	 * 🔴 KHÁC `nop()` Ở TRÊN MỘT CHỖ DUY NHẤT NHƯNG QUAN TRỌNG: `nop()` gom TẤT CẢ những gì người
	 *    ấy đang cầm — chốt ca, thu tay, và MỌI báo cáo chưa nộp — vào một lượt. Đúng cho cảnh
	 *    ôm cả xấp tiền mặt ra quầy. Sai hoàn toàn cho cảnh này: nhân viên chuyển khoản tiền của
	 *    ĐÚNG MỘT báo cáo và đính đúng một cái bill. Gom cả những khoản khác vào đó là gắn một
	 *    tờ bill làm bằng chứng cho số tiền nó không hề chi trả.
	 *
	 * 🔴 GẮN TRƯỚC, CỘNG SAU — cùng luật với `nop()`. `WHERE report_id=%s AND nop_id=0` là chốt
	 *    chống bấm hai lần: lượt thứ hai gắn được 0 dòng, và lượt nộp rỗng ấy bị xoá ngay chứ
	 *    không nằm lại trong bảng chờ của kế toán.
	 *
	 * ⚠️ TÊN NGƯỜI NỘP DO NƠI GỌI XÁC ĐỊNH TỪ PHIÊN (PIN), không nhận từ gói tin — và còn phải
	 *    KHỚP `bc.nhan_vien`, không thì người này bấm nộp hộ (tức là xoá nợ hộ) người khác.
	 */
	public static function nop_bao_cao( $rid, $nguoi, $ghi_chu = '' ) {
		global $wpdb;
		$ai  = trim( (string) $nguoi );
		$rid = trim( (string) $rid );
		if ( '' === $ai )  { return array( 'ok' => false, 'error' => 'Chưa biết ai đang nộp — không ghi sổ được.' ); }
		if ( '' === $rid ) { return array( 'ok' => false, 'error' => 'Thiếu mã báo cáo.' ); }

		$tb  = VHG_DB::t( 'bc' );
		$tbd = VHG_DB::t( 'bc_dong' );
		$h   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $tb WHERE report_id=%s LIMIT 1", $rid ), ARRAY_A );
		if ( ! $h ) { return array( 'ok' => false, 'error' => 'Không thấy báo cáo ' . $rid . '.' ); }
		if ( (string) $h['nhan_vien'] !== $ai ) {
			return array( 'ok' => false, 'error' => 'Báo cáo này do ' . $h['nhan_vien'] . ' gửi — chỉ người ấy nộp được.' );
		}
		if ( (int) $h['nop_id'] > 0 ) {
			return array( 'ok' => false, 'error' => 'Báo cáo này đã nộp rồi.' );
		}

		$luc = current_time( 'mysql' );
		$wpdb->insert( VHG_DB::t( 'nop' ), array(
			'nguoi' => $ai, 'so_tien' => 0, 'so_tien_nhan' => 0, 'so_dong' => 0,
			'trang_thai' => 'cho', 'tao_luc' => $luc, 'nhan_luc' => null, 'nhan_ai' => '',
			'ghi_chu' => mb_substr( trim( (string) $ghi_chu ), 0, 250 ), 'ma_lan' => null ) );
		$id = (int) $wpdb->insert_id;
		if ( ! $id ) { return array( 'ok' => false, 'error' => 'Không mở được lượt nộp, thử lại.' ); }

		$gan = (int) $wpdb->query( $wpdb->prepare(
			"UPDATE $tb SET nop_id=%d WHERE report_id=%s AND nop_id=0 AND nhan_vien=%s", $id, $rid, $ai ) );
		if ( $gan < 1 ) {
			/* Ai đó vừa nộp báo cáo này ở lượt khác (bấm hai lần, hai máy). Xoá lượt rỗng vừa mở. */
			$wpdb->delete( VHG_DB::t( 'nop' ), array( 'id' => $id ) );
			return array( 'ok' => false, 'error' => 'Báo cáo này vừa được nộp ở một lượt khác.' );
		}

		/* Cộng từ chính những dòng của báo cáo vừa gắn được — không tin con số nào tính trước đó. */
		$tong = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(SUM(d.tien_mat),0) FROM $tbd d JOIN $tb hh ON hh.report_id=d.report_id
			 WHERE hh.nop_id=%d", $id ) );
		if ( $tong <= 0 ) {
			/* Báo cáo toàn QR (không đồng tiền mặt nào phải nộp) — không có gì để nộp về quầy.
			   Nhả `nop_id` ra và xoá lượt: một dòng 0 đồng nằm trong bảng chờ của kế toán là thứ
			   người ta học cách bỏ qua, rồi bỏ qua luôn dòng thật nằm cạnh nó. */
			$wpdb->query( $wpdb->prepare( "UPDATE $tb SET nop_id=0 WHERE report_id=%s AND nop_id=%d", $rid, $id ) );
			$wpdb->delete( VHG_DB::t( 'nop' ), array( 'id' => $id ) );
			return array( 'ok' => false, 'khong_co_tien_mat' => 1,
				'error' => 'Báo cáo này không có đồng tiền mặt nào phải nộp (toàn QR) — không cần nộp.' );
		}

		$wpdb->update( VHG_DB::t( 'nop' ), array( 'so_tien' => $tong, 'so_dong' => 1 ), array( 'id' => $id ) );
		return array( 'ok' => true, 'id' => $id, 'so_tien' => $tong, 'so_dong' => 1 );
	}

	/**
	 * GỠ MỘT BÁO CÁO KHỎI LƯỢT NỘP — dùng khi kế toán mở khoá bill (VHG_BaoCao::mo_khoa_bill).
	 *
	 * 🔴 CHỈ GỠ ĐƯỢC LƯỢT CÒN ĐANG CHỜ. Kế toán đã bấm "Đã nhận" là tiền đã đếm, đã vào quầy —
	 *    gỡ báo cáo ra khỏi lượt ấy sau đó là làm lệch đúng con số vừa đếm xong. Ca đó phải đi
	 *    đường điều chỉnh quỹ, không phải đường mở khoá một báo cáo.
	 */
	public static function go_bao_cao_khoi_nop( $rid ) {
		global $wpdb;
		$rid = trim( (string) $rid );
		if ( '' === $rid ) { return array( 'ok' => false, 'error' => 'Thiếu mã báo cáo.' ); }
		$tb = VHG_DB::t( 'bc' );
		$tn = VHG_DB::t( 'nop' );
		$h  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $tb WHERE report_id=%s LIMIT 1", $rid ), ARRAY_A );
		if ( ! $h ) { return array( 'ok' => false, 'error' => 'Không thấy báo cáo.' ); }
		$nid = (int) $h['nop_id'];
		if ( $nid <= 0 ) { return array( 'ok' => true, 'da_go' => 0 ); }

		$n = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $tn WHERE id=%d LIMIT 1", $nid ), ARRAY_A );
		if ( $n && 'cho' !== (string) $n['trang_thai'] ) {
			return array( 'ok' => false, 'da_nhan' => 1,
				'error' => 'Lượt nộp của báo cáo này kế toán đã bấm "Đã nhận" — tiền đã vào quầy. '
					. 'Mở khoá ở đây sẽ làm lệch quỹ; sửa số thì đi đường điều chỉnh của kế toán.' );
		}

		$wpdb->query( $wpdb->prepare( "UPDATE $tb SET nop_id=0 WHERE report_id=%s AND nop_id=%d", $rid, $nid ) );
		/* Lượt nộp nay không còn dòng nào -> xoá, khỏi để lại một dòng 0 đồng trong bảng chờ. */
		$con = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $tb WHERE nop_id=%d", $nid ) )
			+ (int) $wpdb->get_var( $wpdb->prepare(
				'SELECT COUNT(*) FROM ' . VHG_DB::t( 'chot' ) . ' WHERE nop_id=%d', $nid ) )
			+ (int) $wpdb->get_var( $wpdb->prepare(
				'SELECT COUNT(*) FROM ' . VHG_DB::t( 'thu' ) . ' WHERE nop_id=%d', $nid ) );
		if ( $con < 1 ) { $wpdb->delete( $tn, array( 'id' => $nid ) ); }
		return array( 'ok' => true, 'da_go' => 1, 'nop_id' => $nid );
	}

	/**
	 * QUẢN LÝ XÁC NHẬN ĐÃ NHẬN.
	 *
	 * 🔴 GIỮ CẢ HAI CON SỐ. `so_tien` là máy cộng từ các dòng; `so_tien_nhan` là người nhận đếm
	 *    lại. Ghi đè con số này lên con số kia là xoá mất đúng bằng chứng của một lần lệch quỹ.
	 *
	 * 🔴 CHỈ MỘT NGƯỜI XÁC NHẬN ĐƯỢC. `WHERE trang_thai='cho'` rồi đọc số dòng đụng được — hai
	 *    quản lý cùng bấm thì người thứ hai bị chặn, chứ không phải cùng ghi đè lên nhau.
	 */
	public static function nhan( $id, $so_tien_nhan, $ai, $ghi_chu = '' ) {
		global $wpdb;
		$id = (int) $id;
		$ng = trim( (string) $ai );
		if ( '' === $ng ) {
			return array( 'ok' => false, 'error' => 'Chưa biết ai đang nhận — không ghi sổ được.' );
		}
		$t = VHG_DB::t( 'nop' );
		$r = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id=%d LIMIT 1", $id ), ARRAY_A );
		if ( ! $r ) { return array( 'ok' => false, 'error' => 'Không thấy lượt nộp này.' ); }

		$nhan = (int) $so_tien_nhan;
		if ( $nhan < 0 ) { return array( 'ok' => false, 'error' => 'Số tiền nhận không âm được.' ); }

		$n = $wpdb->query( $wpdb->prepare(
			"UPDATE $t SET trang_thai='da_nhan', so_tien_nhan=%d, nhan_luc=%s, nhan_ai=%s, ghi_chu=%s
			 WHERE id=%d AND trang_thai='cho'",
			$nhan, current_time( 'mysql' ), $ng,
			mb_substr( trim( (string) $r['ghi_chu'] . ( '' !== trim( (string) $ghi_chu ) ? ' · ' . $ghi_chu : '' ) ), 0, 250 ),
			$id ) );
		if ( ! $n ) {
			return array( 'ok' => false, 'error' => 'Lượt nộp này vừa được người khác xác nhận.' );
		}

		$lech = $nhan - (int) $r['so_tien'];
		return array( 'ok' => true, 'id' => $id, 'so_tien' => (int) $r['so_tien'],
			'so_tien_nhan' => $nhan, 'lech' => $lech,
			'thong_bao' => 0 === $lech
				? 'Đã nhận đủ ' . number_format( $nhan, 0, ',', '.' ) . 'đ của ' . $r['nguoi'] . '.'
				: 'Đã ghi nhận. ' . ( $lech < 0 ? 'THIẾU ' : 'THỪA ' )
					. number_format( abs( $lech ), 0, ',', '.' ) . 'đ so với sổ ('
					. number_format( (int) $r['so_tien'], 0, ',', '.' ) . 'đ).' );
	}

	/**
	 * HUỶ MỘT LƯỢT NỘP CHƯA XÁC NHẬN — trả các dòng về lại tay người nộp.
	 *
	 * ⚠️ Đã xác nhận rồi thì KHÔNG huỷ được. Tiền đã chuyển tay thật; gỡ nó ra khỏi sổ bằng một
	 *    cú bấm là mở đường cho việc không ai muốn nghĩ tới.
	 */
	public static function huy_nop( $id ) {
		global $wpdb;
		$id = (int) $id;
		$t  = VHG_DB::t( 'nop' );
		$n  = $wpdb->query( $wpdb->prepare(
			"DELETE FROM $t WHERE id=%d AND trang_thai='cho'", $id ) );
		if ( ! $n ) {
			return array( 'ok' => false,
				'error' => 'Không huỷ được — lượt nộp này đã được xác nhận, hoặc không còn.' );
		}
		$wpdb->query( $wpdb->prepare(
			'UPDATE ' . VHG_DB::t( 'chot' ) . ' SET nop_id=0 WHERE nop_id=%d', $id ) );
		$wpdb->query( $wpdb->prepare(
			'UPDATE ' . VHG_DB::t( 'thu' ) . ' SET nop_id=0 WHERE nop_id=%d', $id ) );
		$wpdb->query( $wpdb->prepare(
			'UPDATE ' . VHG_DB::t( 'bc' ) . ' SET nop_id=0 WHERE nop_id=%d', $id ) );
		return array( 'ok' => true, 'thong_bao' => 'Đã huỷ lượt nộp — tiền quay lại tay người nộp.' );
	}

	/**
	 * XÁC NHẬN THAY — cho dữ liệu CŨ/ĐÃ NHẬP mà tiền thật đã về tay ngoài đời rồi.
	 *
	 * Anh Thắng 29/08/2026, sau khi thấy hàng chục người hiện "đang cầm" hàng trăm triệu ở "Ai
	 * đang cầm tiền": *"một số lệnh nộp tiền cũ, thực ra mọi người đã nộp rồi. Làm sao để duyệt
	 * nộp (dữ liệu import nên bên nhân viên không thấy)"*.
	 *
	 * 🔴 VÌ SAO KHÔNG TỰ ĐỘNG COI DỮ LIỆU IMPORT LÀ ĐÃ NỘP. Cột `bc.nop_id` (29/08/2026) áp dụng
	 *    cho MỌI báo cáo đã có từ trước, không riêng dữ liệu import — mặc định 0 (đang cầm) là
	 *    đúng luật cho báo cáo THẬT SỰ chưa nộp. Coi TẤT CẢ báo cáo cũ là "đã nộp" thì một khoản
	 *    thật sự còn treo (nhân viên nghỉ việc, quên nộp) cũng biến mất theo — im lặng xoá đúng
	 *    khoản nợ cần được thấy. Phải là một NGƯỜI đọc số rồi quyết định là đúng ngoài đời.
	 *
	 * 🔴 DÙNG LẠI NGUYÊN VẸN nop()+nhan() — không viết lại luật gộp 3 nguồn/chốt mốc UNIQUE ở một
	 *    chỗ thứ hai. Khác `nop()` bình thường ở chỗ người XÁC NHẬN ($ai_xac_nhan, kế toán/quản
	 *    lý đang bấm) không phải người NỘP ($nguoi, tên hiện trên "Ai đang cầm tiền") — vốn dĩ
	 *    đúng ý nghĩa "nộp THAY", vì $nguoi thường không còn phiên nào để tự bấm (dữ liệu import
	 *    không gắn với tài khoản đăng nhập nào — "(import)" thậm chí không phải tên người thật).
	 */
	public static function nop_va_nhan_thay( $nguoi, $ai_xac_nhan, $ghi_chu = '' ) {
		$r = self::nop( $nguoi, trim( 'Kế toán xác nhận thay (dữ liệu cũ) · ' . trim( (string) $ghi_chu ), ' ·' ) );
		if ( empty( $r['ok'] ) || ! empty( $r['lap_lai'] ) ) { return $r; }
		return self::nhan( $r['id'], (int) $r['so_tien'], $ai_xac_nhan, $ghi_chu );
	}

	/** Các lượt nộp đang chờ xác nhận. */
	public static function nop_cho( $gioi_han = 50 ) {
		global $wpdb;
		$t  = VHG_DB::t( 'nop' );
		$ds = VHG_DB::rows( $wpdb->prepare(
			"SELECT * FROM $t WHERE trang_thai='cho' ORDER BY id ASC LIMIT %d",
			max( 1, min( 200, (int) $gioi_han ) ) ) );

		/* 🔴 KÉO BILL RA ĐÚNG CHỖ NGƯỜI TA QUYẾT ĐỊNH — anh Thắng 05/09/2026 cho nhân viên đính
		   bill chuyển khoản rồi bấm nộp. Kế toán ngồi trước đúng cái bảng này để bấm "Đã nhận";
		   bắt họ đi mở màn khác tìm tờ bill rồi quay lại đây bấm là chuyện sẽ không ai làm — họ
		   sẽ bấm "Đã nhận" mà không xem bill, và cái bill ấy thành ra vô nghĩa.
		   Đây cũng là nơi bấm MỞ KHOÁ khi bill sai (xem VHG_BaoCao::mo_khoa_bill). */
		$tb = VHG_DB::t( 'bc' );
		foreach ( $ds as $i => $n ) {
			$ds[ $i ]['bill'] = array();
			$hs = $wpdb->get_results( $wpdb->prepare(
				"SELECT report_id, coso, ngay, bill_anh, bill_luc, bill_ghichu
				 FROM $tb WHERE nop_id=%d AND bill_luc IS NOT NULL ORDER BY id ASC", (int) $n['id'] ), ARRAY_A );
			foreach ( (array) $hs as $h ) {
				$anh = array();
				$raw = (string) $h['bill_anh'];
				if ( '' !== $raw ) { $tmp = json_decode( $raw, true ); if ( is_array( $tmp ) ) { $anh = array_values( array_filter( $tmp ) ); } }
				$ds[ $i ]['bill'][] = array(
					'reportId' => (string) $h['report_id'], 'coso' => (string) $h['coso'],
					'ngay' => (string) $h['ngay'], 'anh' => $anh,
					'luc' => (string) $h['bill_luc'], 'ghiChu' => (string) $h['bill_ghichu'] );
			}
		}
		return $ds;
	}

	/** Lịch sử nộp trong kỳ. */
	public static function ds_nop( $ky = 'month', $gioi_han = 200 ) {
		global $wpdb;
		$t   = VHG_DB::t( 'nop' );
		$dau = VHG_Thu::dau_ky( $ky );
		$gh  = max( 1, min( 500, (int) $gioi_han ) );
		return VHG_DB::rows( '' !== $dau
			? $wpdb->prepare( "SELECT * FROM $t WHERE tao_luc>=%s ORDER BY id DESC LIMIT %d", $dau, $gh )
			: $wpdb->prepare( "SELECT * FROM $t ORDER BY id DESC LIMIT %d", $gh ) );
	}

	/**
	 * BÁO CÁO CA CỦA MỘT NGƯỜI — từ lần nộp gần nhất tới giờ.
	 *
	 * Anh Thắng 23/08/2026: *"chưa thấy nhân viên chốt báo cáo ca"*.
	 *
	 * 🔴 "CA" Ở ĐÂY LÀ QUÃNG CHƯA NỘP, KHÔNG PHẢI "HÔM NAY".
	 *    Người thu đi một vòng nhiều ghế rồi nộp một lần. Quãng đó mới là cái họ phải giải trình,
	 *    và nó có thể vắt qua nửa đêm (ca tối đóng cửa lúc 1 giờ sáng). Cắt theo ngày là ca đêm
	 *    bị chẻ đôi, và cả hai nửa đều không khớp với xấp tiền trong tay.
	 *
	 * ⚠️ Trả về CẢ danh sách ghế đã chốt, không chỉ con số tổng. Lệch quỹ thì câu hỏi đầu tiên
	 *    luôn là *"ghế nào"* — một con số tổng không trả lời được câu đó.
	 */
	public static function bao_cao_ca( $nguoi ) {
		global $wpdb;
		$ai = trim( (string) $nguoi );
		$ra = array( 'nguoi' => $ai, 'so_ghe' => 0, 'tien_dem' => 0, 'theo_may' => 0,
			'theo_he_thong' => 0, 'lech_dem' => 0, 'lech_may' => 0, 'tu_quay' => 0, 'tu_bao_cao' => 0,
			'tu_luc' => '', 'ds' => array() );
		if ( '' === $ai ) { return $ra; }

		$tc = VHG_DB::t( 'chot' );
		foreach ( VHG_DB::rows( $wpdb->prepare(
			"SELECT * FROM $tc WHERE nguoi=%s AND nop_id=0 ORDER BY id ASC", $ai ) ) as $r ) {
			$c = self::doc_chot( $r );
			$ra['ds'][]           = $c;
			$ra['so_ghe']        += 1;
			$ra['tien_dem']      += (int) $c['tien_dem'];
			$ra['theo_may']      += (int) $c['theo_may'];
			$ra['theo_he_thong'] += (int) $c['theo_he_thong'];
			$ra['lech_dem']      += (int) $c['lech_dem'];
			$ra['lech_may']      += (int) $c['lech_may'];
			if ( '' === $ra['tu_luc'] ) { $ra['tu_luc'] = (string) $c['tao_luc']; }
		}

		$tt = VHG_DB::t( 'thu' );
		$ra['tu_quay'] = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(SUM(so_tien),0) FROM $tt
			 WHERE nguon=%s AND noi_dung=%s AND huy=0 AND nop_id=0",
			VHG_Thu::TIEN_MAT, VHG_Thu::ND_THU_TAY . $ai ) );

		/* Nguồn thứ ba (29/08/2026) — báo cáo doanh thu, cộng vào cho khớp với dang_cam()/tổng
		   "Tôi đang cầm" (xem chú thích ở đó). */
		$tb  = VHG_DB::t( 'bc' );
		$tbd = VHG_DB::t( 'bc_dong' );
		$ra['tu_bao_cao'] = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(SUM(d.tien_mat),0) FROM $tbd d JOIN $tb h ON h.report_id=d.report_id
			 WHERE h.nhan_vien=%s AND h.nop_id=0", $ai ) );

		$ra['tong'] = $ra['tien_dem'] + $ra['tu_quay'] + $ra['tu_bao_cao'];
		return $ra;
	}

	// ═════════════════════════════════════════════════════════════════ báo cáo theo người

	/**
	 * BÁO CÁO THEO NGƯỜI THU.
	 *
	 * Trả lời đúng bốn câu hỏi người ta hỏi ở quầy:
	 *   1. Kỳ này ai thu được bao nhiêu?          -> `tu_ghe` + `tu_quay`
	 *   2. Đã nộp về bao nhiêu, còn cầm bao nhiêu? -> `da_nop` / `dang_cam`
	 *   3. Có ai nộp lệch không?                   -> `lech_nop`
	 *   4. Ngăn ghế người đó chốt có thiếu không?  -> `lech_dem`
	 *
	 * ⚠️ `dang_cam` KHÔNG lọc theo kỳ. Tiền trên tay là tiền trên tay, dù nó đến từ tháng trước
	 *    — lọc nó theo kỳ là một người cầm tiền quá một tháng thì tự nhiên biến mất khỏi bảng.
	 */
	public static function theo_nguoi( $ky = 'month' ) {
		global $wpdb;
		$dau = VHG_Thu::dau_ky( $ky );
		$ra  = array();

		$moi = function ( &$ra, $k ) {
			if ( ! isset( $ra[ $k ] ) ) {
				$ra[ $k ] = array( 'nguoi' => $k, 'tu_ghe' => 0, 'tu_quay' => 0, 'so_lan_chot' => 0,
					'lech_dem' => 0, 'lech_may' => 0, 'da_nop' => 0, 'lech_nop' => 0,
					'so_lan_nop' => 0, 'dang_cam' => 0 );
			}
		};

		$tc  = VHG_DB::t( 'chot' );
		$sql = '' !== $dau
			? $wpdb->prepare( "SELECT nguoi, SUM(tien_dem) t, SUM(lech_dem) ld, SUM(lech_may) lm,
				COUNT(*) n FROM $tc WHERE tao_luc>=%s AND nguoi<>'' GROUP BY nguoi", $dau )
			: "SELECT nguoi, SUM(tien_dem) t, SUM(lech_dem) ld, SUM(lech_may) lm,
				COUNT(*) n FROM $tc WHERE nguoi<>'' GROUP BY nguoi";
		foreach ( VHG_DB::rows( $sql ) as $r ) {
			$k = (string) $r['nguoi'];
			$moi( $ra, $k );
			$ra[ $k ]['tu_ghe']      = (int) $r['t'];
			$ra[ $k ]['lech_dem']    = (int) $r['ld'];
			$ra[ $k ]['lech_may']    = (int) $r['lm'];
			$ra[ $k ]['so_lan_chot'] = (int) $r['n'];
		}

		foreach ( VHG_Thu::ds_tien_mat( $ky, 100000 ) as $r ) {
			if ( 'nguoi' !== $r['kieu'] || '' === $r['nguoi'] ) { continue; }
			$moi( $ra, $r['nguoi'] );
			$ra[ $r['nguoi'] ]['tu_quay'] += (int) $r['so_tien'];
		}

		$tn  = VHG_DB::t( 'nop' );
		$sq  = '' !== $dau
			? $wpdb->prepare( "SELECT nguoi, SUM(so_tien_nhan) t, SUM(so_tien_nhan-so_tien) l, COUNT(*) n
				FROM $tn WHERE trang_thai='da_nhan' AND tao_luc>=%s GROUP BY nguoi", $dau )
			: "SELECT nguoi, SUM(so_tien_nhan) t, SUM(so_tien_nhan-so_tien) l, COUNT(*) n
				FROM $tn WHERE trang_thai='da_nhan' GROUP BY nguoi";
		foreach ( VHG_DB::rows( $sq ) as $r ) {
			$k = (string) $r['nguoi'];
			$moi( $ra, $k );
			$ra[ $k ]['da_nop']     = (int) $r['t'];
			$ra[ $k ]['lech_nop']   = (int) $r['l'];
			$ra[ $k ]['so_lan_nop'] = (int) $r['n'];
		}

		foreach ( self::ai_dang_cam() as $c ) {
			$moi( $ra, $c['nguoi'] );
			$ra[ $c['nguoi'] ]['dang_cam'] = (int) $c['tong'];
		}

		$ds = array_values( $ra );
		usort( $ds, function ( $a, $b ) {
			return ( $b['tu_ghe'] + $b['tu_quay'] ) - ( $a['tu_ghe'] + $a['tu_quay'] );
		} );
		return $ds;
	}

	/** Vài con số tổng cho đầu tab. */
	public static function tong( $ky = 'month' ) {
		$tren_tay = 0;
		foreach ( self::ai_dang_cam() as $c ) { $tren_tay += (int) $c['tong']; }
		$cho = self::nop_cho( 200 );
		$t_cho = 0;
		foreach ( $cho as $c ) { $t_cho += (int) $c['so_tien']; }
		$lech_may = 0; $lech_dem = 0; $chot_ky = 0;
		foreach ( self::ds_chot( $ky, 500 ) as $c ) {
			$chot_ky  += (int) $c['tien_dem'];
			$lech_may += (int) $c['lech_may'];
			$lech_dem += (int) $c['lech_dem'];
		}
		return array( 'tren_tay' => $tren_tay, 'cho_xac_nhan' => $t_cho, 'so_cho' => count( $cho ),
			'chot_ky' => $chot_ky, 'lech_may' => $lech_may, 'lech_dem' => $lech_dem );
	}

	/* ══════════════════════════════════════════════════════════════════════════════════════════
	 * CHỐT TIỀN THEO CHỈ SỐ ĐỌC TỪ GHẾ (bảng `chot_tien`).
	 *
	 * Máy trạm nối AP ghế, đọc GET /chotso -> hai chỉ số CỘNG DỒN: tiền mặt (tm) + QR (qr).
	 * Rồi gọi `chot_tien_luu`. Web trừ kỳ trước như công-tơ. Khác `chot()` (chốt ca máy đếm).
	 * ═════════════════════════════════════════════════════════════════════════════════════════ */

	/** Lượt chốt tiền GẦN NHẤT của một ghế (hoặc null). */
	public static function chot_tien_truoc( $ma_may ) {
		global $wpdb;
		$m = strtoupper( trim( (string) $ma_may ) );
		if ( '' === $m ) { return null; }
		$r = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . VHG_DB::t( 'chot_tien' ) . ' WHERE ma_may=%s ORDER BY id DESC LIMIT 1',
			$m ), ARRAY_A );
		return $r ? $r : null;
	}

	/** Xem trước khi chốt tiền: mốc tm/qr lần trước (để máy trạm hiện "kỳ này"). */
	public static function chot_tien_xem( $ma_may, $coso_cua_toi = null ) {
		$t = self::truoc_khi_chot( $ma_may, $coso_cua_toi );   // dùng lại rào cơ sở + kiểm ghế có thật
		if ( empty( $t['ok'] ) ) { return $t; }
		$tr = self::chot_tien_truoc( $t['ma_may'] );
		return array(
			'ok'       => true,
			'ma_may'   => $t['ma_may'],
			'coso'     => (string) $t['coso'],
			'song'     => (int) $t['song'],
			'lan_dau'  => $tr ? 0 : 1,
			'tm_truoc' => $tr ? (int) $tr['tm'] : 0,
			'qr_truoc' => $tr ? (int) $tr['qr'] : 0,
			'chot_truoc_luc' => $tr ? (string) $tr['tao_luc'] : '',
			'chot_truoc_ai'  => $tr ? (string) $tr['nguoi'] : '',
		);
	}

	/**
	 * GHI MỘT LƯỢT CHỐT TIỀN.
	 *
	 * @param string $ma_may Ghế (từ mã QR / AP).
	 * @param int    $tm     Chỉ số TIỀN MẶT cộng dồn ĐỌC TỪ GHẾ, ngay lúc này.
	 * @param int    $qr     Chỉ số QR cộng dồn đọc từ ghế.
	 * @param string $nguoi  Ai chốt — LẤY TỪ PHIÊN, không nhận từ gói tin.
	 */
	public static function chot_tien_luu( $ma_may, $tm, $qr, $nguoi, $ghi_chu = '', $ma_lan = '',
		$coso_cua_toi = null, $tmc_ghe = null, $qrc_ghe = null, $tmky_ghe = null, $qrky_ghe = null ) {
		global $wpdb;

		/* Gửi lại (sóng yếu) -> trả lượt cũ, không ghi thêm. Chốt thật ở UNIQUE ma_lan tầng SQL. */
		$ml = mb_substr( trim( (string) $ma_lan ), 0, 40 );
		if ( '' !== $ml ) {
			$cu = $wpdb->get_row( $wpdb->prepare(
				'SELECT * FROM ' . VHG_DB::t( 'chot_tien' ) . ' WHERE ma_lan=%s LIMIT 1', $ml ), ARRAY_A );
			if ( $cu ) {
				return array( 'ok' => true, 'lap_lai' => 1,
					'thong_bao' => 'Lượt chốt tiền này đã ghi rồi — không ghi thêm.',
					'tm' => (int) $cu['tm'], 'qr' => (int) $cu['qr'],
					'tm_ky' => (int) $cu['tm_ky'], 'qr_ky' => (int) $cu['qr_ky'],
					'tm_truoc' => (int) $cu['tm_truoc'], 'qr_truoc' => (int) $cu['qr_truoc'] );
			}
		}

		$ai = trim( (string) $nguoi );
		if ( '' === $ai ) {
			return array( 'ok' => false, 'error' => 'Chưa biết ai đang chốt — không ghi sổ được.' );
		}

		$t = self::truoc_khi_chot( $ma_may, $coso_cua_toi );   // rào cơ sở + kiểm ghế có thật
		if ( empty( $t['ok'] ) ) { return $t; }
		$m = $t['ma_may'];

		$tm = (int) $tm; $qr = (int) $qr;
		if ( $tm < 0 || $qr < 0 ) {
			return array( 'ok' => false, 'error' => 'Chỉ số tiền không âm được.' );
		}

		/* NGUỒN CỦA "KỲ NÀY":
		   - MỚI (mốc nằm trên ghế): máy trạm gửi kèm tmc/qrc (mốc trước) + tm_ky/qr_ky do GHẾ tính.
		     Web LƯU Y SỐ GHẾ ĐƯA — mỗi bản ghi tự đủ (mốc trước + mốc sau + kỳ), nên chốt offline tới
		     web TRỄ hay LỆCH THỨ TỰ vẫn đúng, không phụ thuộc "lượt trước" của web.
		   - CŨ (máy trạm đời trước, không gửi kèm): web tự trừ lượt chốt trước như công-tơ. */
		$ghe_tinh = ( null !== $tmc_ghe && null !== $qrc_ghe && null !== $tmky_ghe && null !== $qrky_ghe );
		if ( $ghe_tinh ) {
			$tm_truoc = max( 0, (int) $tmc_ghe );
			$qr_truoc = max( 0, (int) $qrc_ghe );
			$tm_ky    = max( 0, (int) $tmky_ghe );
			$qr_ky    = max( 0, (int) $qrky_ghe );
			/* Đường ghế: LƯU Y số ghế đưa, KHÔNG ép kỳ 0. Chốt đầu tiên (mốc gốc 0) thì kỳ = toàn bộ
			   tiền dồn từ đầu — đó là tiền thật chưa chốt, đếm đủ. lan_dau=0 để không zero-hoá tm_truoc. */
			$lan_dau  = 0;
		} else {
			$tr = self::chot_tien_truoc( $m );
			$lan_dau = $tr ? 0 : 1;
			$tm_truoc = $tr ? (int) $tr['tm'] : 0;
			$qr_truoc = $tr ? (int) $tr['qr'] : 0;

			/* 🔴 CHỈ SỐ CỘNG DỒN KHÔNG CHẠY LÙI. Nhỏ hơn lần trước = ghế vừa thay/xoá NVS, hoặc đọc
			   nhầm. Bắt ghi chú rồi mới cho qua (giống chốt ca) — ghi lặng thì "kỳ này" ra số âm.
			   (Chỉ áp cho đường CŨ: đường mới đã có ghế bảo đảm không chạy lùi.) */
			if ( ! $lan_dau && ( $tm < $tm_truoc || $qr < $qr_truoc ) ) {
				if ( '' === trim( (string) $ghi_chu ) ) {
					return array( 'ok' => false, 'error' => 'Chỉ số nhỏ hơn lần chốt trước (TM ' . $tm_truoc
						. ' / QR ' . $qr_truoc . '). Ghế không chạy lùi — kiểm lại. Nếu vừa thay ghế/xoá bộ nhớ '
						. 'thì ghi rõ vào ô ghi chú rồi bấm lại.' );
				}
			}

			$tm_ky = $lan_dau ? 0 : max( 0, $tm - $tm_truoc );
			$qr_ky = $lan_dau ? 0 : max( 0, $qr - $qr_truoc );
		}

		$luc = current_time( 'mysql' );
		$ok = $wpdb->insert( VHG_DB::t( 'chot_tien' ), array(
			'ma_may' => $m, 'coso' => (string) $t['coso'], 'nguoi' => $ai,
			'tm' => $tm, 'qr' => $qr, 'tm_truoc' => $lan_dau ? 0 : $tm_truoc,
			'qr_truoc' => $lan_dau ? 0 : $qr_truoc, 'tm_ky' => $tm_ky, 'qr_ky' => $qr_ky,
			'lan_dau' => $lan_dau, 'ghi_chu' => mb_substr( trim( (string) $ghi_chu ), 0, 255 ),
			'ma_lan' => '' !== $ml ? $ml : null, 'tao_luc' => $luc,
		) );
		if ( false === $ok ) {
			/* Trùng ma_lan do hai lượt gửi lại chen nhau -> đọc lại lượt đã ghi. */
			if ( '' !== $ml ) {
				$cu = $wpdb->get_row( $wpdb->prepare(
					'SELECT * FROM ' . VHG_DB::t( 'chot_tien' ) . ' WHERE ma_lan=%s LIMIT 1', $ml ), ARRAY_A );
				if ( $cu ) {
					return array( 'ok' => true, 'lap_lai' => 1, 'thong_bao' => 'Đã ghi rồi.',
						'tm' => (int) $cu['tm'], 'qr' => (int) $cu['qr'],
						'tm_ky' => (int) $cu['tm_ky'], 'qr_ky' => (int) $cu['qr_ky'],
						'tm_truoc' => (int) $cu['tm_truoc'], 'qr_truoc' => (int) $cu['qr_truoc'] );
				}
			}
			return array( 'ok' => false, 'error' => 'Ghi sổ chốt tiền thất bại.' );
		}

		return array( 'ok' => true, 'ma_may' => $m, 'coso' => (string) $t['coso'],
			'lan_dau' => $lan_dau, 'tm' => $tm, 'qr' => $qr,
			'tm_truoc' => $lan_dau ? 0 : $tm_truoc, 'qr_truoc' => $lan_dau ? 0 : $qr_truoc,
			'tm_ky' => $tm_ky, 'qr_ky' => $qr_ky,
			'thong_bao' => $lan_dau ? 'Đã chốt tiền (lần đầu — mốc gốc).' : 'Đã chốt tiền.' );
	}

	/** Lịch sử chốt tiền cho tab admin. */
	public static function chot_tien_ds( $ky = 'month', $limit = 500 ) {
		global $wpdb;
		$t   = VHG_DB::t( 'chot_tien' );
		$dau = VHG_Thu::dau_ky( $ky );          // '' = tất cả (giống ds_chot)
		$gh  = max( 1, min( 2000, (int) $limit ) );
		$sql = '' !== $dau
			? $wpdb->prepare( "SELECT * FROM $t WHERE tao_luc>=%s ORDER BY id DESC LIMIT %d", $dau, $gh )
			: $wpdb->prepare( "SELECT * FROM $t ORDER BY id DESC LIMIT %d", $gh );
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}
}
