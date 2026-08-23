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
		/* 🔴 ĐÓNG MỐC TRƯỚC, RỒI MỚI CỘNG. Lấy `den_id` xong mới cộng trong khoảng
		   (tu_id, den_id] thì một dòng rơi vào đúng lúc này sẽ nằm trọn trong kỳ SAU — không
		   mất, không lặp. Cộng trước rồi mới đóng mốc thì dòng đó lọt ra ngoài cả hai kỳ. */
		$den = self::thu_moi_nhat();
		$tu  = $tr ? (int) $tr['den_id'] : 0;
		return array(
			'ok'            => true,
			'ma_may'        => $m,
			/* Gửi kèm cơ sở và tình trạng kết nối: màn chốt ca của người thu KHÔNG được gọi
			   `so_may` nữa (việc đó nay chỉ quản trị làm được), nên nó phải tự đủ. */
			'coso'          => (string) ( isset( $may_['coso_ten'] ) ? $may_['coso_ten'] : '' ),
			'song'          => ! empty( $may_['con_song'] ) ? 1 : 0,
			'lan_dau'       => $tr ? 0 : 1,
			'chi_so_truoc'  => $tr ? (int) $tr['chi_so'] : 0,
			'chot_truoc_luc' => $tr ? (string) $tr['tao_luc'] : '',
			'chot_truoc_ai' => $tr ? (string) $tr['nguoi'] : '',
			'don_vi'        => self::don_vi(),
			'tu_id'         => $tu,
			'den_id'        => $den,
			'theo_he_thong' => self::may_bao( $m, $tu, $den ),
		);
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

	// ═════════════════════════════════════════════════════════════════ tiền trên tay

	/**
	 * TIỀN MẶT ĐANG NẰM TRONG TAY MỘT NGƯỜI.
	 *
	 * Hai nguồn, và cả hai đều là tiền thật đang ở ngoài két:
	 *   · `chot`  — xấp tiền vừa lấy ra khỏi ngăn ghế. KHÔNG phải doanh thu mới (ghế đã ghi sổ
	 *               từ lúc nuốt), chỉ là một lần chuyển tay.
	 *   · `thu`   — khách trả tiền mặt tại quầy, người thu bấm cho ghế chạy. Cái này VỪA là
	 *               doanh thu VỪA là tiền trên tay.
	 *
	 * ⚠️ Bỏ dòng `thu` đã HUỶ. Huỷ nghĩa là lượt đó không có thật, mà tiền không có thật thì
	 *    không ai phải nộp.
	 */
	public static function dang_cam( $nguoi ) {
		global $wpdb;
		$ai = trim( (string) $nguoi );
		if ( '' === $ai ) { return array( 'nguoi' => '', 'tong' => 0, 'tu_ghe' => 0, 'tu_quay' => 0, 'so_dong' => 0 ); }

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

		return array( 'nguoi' => $ai, 'tong' => $ghe + $quay, 'tu_ghe' => $ghe, 'tu_quay' => $quay,
			'so_dong' => $n_ghe + $n_quay );
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
			$ra[ $k ] = array( 'nguoi' => $k, 'tu_ghe' => (int) $r['t'], 'tu_quay' => 0,
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
				$ra[ $k ] = array( 'nguoi' => $k, 'tu_ghe' => 0, 'tu_quay' => 0, 'so_dong' => 0, 'tong' => 0 );
			}
			$ra[ $k ]['tu_quay'] += (int) $r['t'];
			$ra[ $k ]['so_dong'] += (int) $r['n'];
			$ra[ $k ]['tong']    += (int) $r['t'];
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
	public static function nop( $nguoi, $ghi_chu = '', $ma_lan = '' ) {
		global $wpdb;
		$ai = trim( (string) $nguoi );
		if ( '' === $ai ) {
			return array( 'ok' => false, 'error' => 'Chưa biết ai đang nộp — không ghi sổ được.' );
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
		$wpdb->query( $wpdb->prepare(
			"UPDATE $tc SET nop_id=%d WHERE nguoi=%s AND nop_id=0", $id, $ai ) );
		$wpdb->query( $wpdb->prepare(
			"UPDATE $tt SET nop_id=%d WHERE nguon=%s AND noi_dung=%s AND huy=0 AND nop_id=0",
			$id, VHG_Thu::TIEN_MAT, VHG_Thu::ND_THU_TAY . $ai ) );

		/* Cộng lại từ đúng những dòng vừa gắn được — không tin con số đã tính trước đó. */
		$tong = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(SUM(tien_dem),0) FROM $tc WHERE nop_id=%d", $id ) )
			+ (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COALESCE(SUM(so_tien),0) FROM $tt WHERE nop_id=%d", $id ) );
		$dong = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $tc WHERE nop_id=%d", $id ) )
			+ (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $tt WHERE nop_id=%d", $id ) );

		if ( $tong <= 0 ) {
			/* Không gắn được đồng nào -> xoá luôn lượt nộp. Để lại một dòng 0 đồng là bảng chờ
			   xác nhận đầy những lượt rỗng, và người ta thôi không nhìn nó nữa. */
			$wpdb->delete( VHG_DB::t( 'nop' ), array( 'id' => $id ) );
			return array( 'ok' => false, 'error' => 'Anh/chị đang không cầm đồng nào chưa nộp.' );
		}

		$wpdb->update( VHG_DB::t( 'nop' ), array( 'so_tien' => $tong, 'so_dong' => $dong ),
			array( 'id' => $id ) );
		return array( 'ok' => true, 'id' => $id, 'so_tien' => $tong, 'so_dong' => $dong, 'lap_lai' => 0,
			'thong_bao' => 'Đã nộp ' . number_format( $tong, 0, ',', '.' ) . 'đ (' . $dong
				. ' lượt) — chờ quản lý xác nhận đã nhận đủ.' );
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
		return array( 'ok' => true, 'thong_bao' => 'Đã huỷ lượt nộp — tiền quay lại tay người nộp.' );
	}

	/** Các lượt nộp đang chờ xác nhận. */
	public static function nop_cho( $gioi_han = 50 ) {
		global $wpdb;
		$t = VHG_DB::t( 'nop' );
		return VHG_DB::rows( $wpdb->prepare(
			"SELECT * FROM $t WHERE trang_thai='cho' ORDER BY id ASC LIMIT %d",
			max( 1, min( 200, (int) $gioi_han ) ) ) );
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
}
