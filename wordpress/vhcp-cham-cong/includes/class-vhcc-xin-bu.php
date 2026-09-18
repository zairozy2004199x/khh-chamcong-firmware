<?php
/**
 * ĐƠN XIN BÙ GIỜ — nhân viên gửi, cửa hàng trưởng duyệt, kế toán duyệt lần cuối.
 *
 * Anh Thắng 18/09/2026:
 *   · *"Cửa hàng trưởng không được bù giờ công, nếu thiếu thì chỗ file excel"*;
 *   · *"lệnh bù giờ từ nhân viên gửi lên, CHT sẽ nhận và duyệt và đẩy tiếp lên cho kế toán,
 *     kế toán duyệt mới đẩy vào bảng công"*;
 *   · *"nhân viên thêm bù giờ thì trên bảng công cũng sẽ hiện luôn giờ bạn xin, nhưng chữ màu
 *     nhạt hơn (vàng) để biết mình đã xin nhưng chờ duyệt, khi duyệt thì nó nhập vào và đổi về
 *     màu chuẩn"*.
 *
 * =============================================================================================
 * 🔴 HAI CẤP, VÀ CẢ HAI ĐỀU CẦN
 * =============================================================================================
 * `cho_cht` → `cho_kt` → `duyet`. Bỏ cấp cửa hàng trưởng thì kế toán ngập trong đơn lẻ của 26
 * cửa hàng và sẽ duyệt hàng loạt cho xong — lúc ấy cấp duyệt chỉ còn là một cái nút. Bỏ cấp kế
 * toán thì cửa hàng trưởng lại tự bù được, đúng cái vừa bị thu ở trên.
 *
 * Cửa hàng trưởng là người BIẾT hôm ấy ai có đi làm thật; kế toán là người chịu trách nhiệm về
 * con số cuối. Hai câu hỏi khác nhau, nên hai cấp.
 *
 * =============================================================================================
 * 🔴 GIỜ XIN KHÔNG BAO GIỜ NẰM TẠM TRONG BẢNG CHẤM CÔNG
 * =============================================================================================
 * Cách dễ hơn là ghi thẳng vào `cham_cong` rồi gắn một cờ "chờ duyệt". Làm thế thì MỌI phép
 * cộng lương, mọi lượt xuất, mọi báo cáo phải nhớ loại nó ra — và một chỗ quên là trả tiền cho
 * giờ chưa ai duyệt. Nên đơn nằm ở bảng riêng cho tới lúc duyệt xong; lưới bảng công đọc thêm
 * bảng này CHỈ ĐỂ VẼ, và vẽ bằng màu khác.
 *
 * ⚠️ LÚC DUYỆT ĐI QUA `VHCC_Bu::ghi()`, KHÔNG GHI THẲNG. Giữ nhật ký, giữ chốt cơ sở, giữ chốt
 *    "bù chỉ điền vào ô trống" — cái cuối quan trọng nhất: đơn nằm chờ mấy ngày, trong lúc ấy
 *    máy có thể đã ghi được giờ thật cho ngày đó.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_XinBu {

	/** Ai gửi đơn: bậc thấp nhất — đây là cửa của nhân viên. */
	const QUYEN_GUI = 'cong_minh';

	/** Ai duyệt cấp một: cửa hàng trưởng, trong cơ sở mình. */
	const QUYEN_CHT = 'cong_coso';

	/** Ai duyệt cấp hai: cùng bậc với sửa giờ — duyệt chính là ghi vào bảng công. */
	const QUYEN_KT = 'sua_gio';

	const CHO_CHT = 'cho_cht';
	const CHO_KT  = 'cho_kt';
	const DUYET   = 'duyet';
	const TU_CHOI = 'tu_choi';

	const TEN_TT = array(
		self::CHO_CHT => 'Chờ cửa hàng trưởng',
		self::CHO_KT  => 'Chờ kế toán',
		self::DUYET   => 'Đã vào bảng công',
		self::TU_CHOI => 'Không duyệt',
	);

	/** Lùi xa nhất được xin. Xa hơn thì không ai đối chiếu camera được nữa. */
	const NGAY_LUI_TOI_DA = 31;

	/** Trần đơn đang treo của một người — chặn gửi tràn. */
	const TREO_TOI_DA = 20;

	/* ====================================================================== gửi */

	/**
	 * Nhân viên xin bù giờ cho MỘT ngày của chính mình.
	 *
	 * ⚠️ MÃ NV VÀ CƠ SỞ LẤY TỪ THẺ PHIÊN + HỒ SƠ, không nhận từ thân yêu cầu. Đây là cửa mở cho
	 *    bậc thấp nhất nên chốt phải chặt nhất: nhận mã từ thân là xin bù hộ người khác.
	 */
	public static function gui( $u, $ngay, $vao, $ra, $ly_do ) {
		global $wpdb;

		$ma = trim( (string) ( isset( $u['ma_nv'] ) ? $u['ma_nv'] : '' ) );
		if ( '' === $ma ) {
			return array( 'ok' => false, 'error' => 'Tài khoản này chưa gắn Mã NV nên chưa gửi đơn được.' );
		}
		if ( ! VHCC_Vai::duoc( $u, self::QUYEN_GUI ) ) {
			return array( 'ok' => false, 'error' => VHCC_Vai::loi( $u, self::QUYEN_GUI, 'Xin bù giờ' ) );
		}

		$ng = trim( (string) $ngay );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $ng ) ) {
			return array( 'ok' => false, 'error' => 'Ngày không hợp lệ.' );
		}
		$hom_nay = (string) current_time( 'Y-m-d' );
		if ( $ng > $hom_nay ) {
			return array( 'ok' => false, 'error' => 'Chưa xin bù cho ngày mai được.' );
		}
		$lui = (int) round( ( strtotime( $hom_nay . ' 00:00:00 UTC' ) - strtotime( $ng . ' 00:00:00 UTC' ) ) / 86400 );
		if ( $lui > self::NGAY_LUI_TOI_DA ) {
			return array( 'ok' => false, 'error' => 'Chỉ xin bù được trong vòng '
				. self::NGAY_LUI_TOI_DA . ' ngày gần đây. Xa hơn thì báo kế toán.' );
		}

		$v = VHCC_TuanCong::doc_gio( $vao );
		$r = VHCC_TuanCong::doc_gio( $ra );
		if ( null === $v || null === $r || '' === $v || '' === $r ) {
			return array( 'ok' => false, 'error' => 'Gõ cả giờ vào và giờ ra, kiểu 24 giờ: 08:00, 17:30.' );
		}
		if ( $r <= $v ) {
			return array( 'ok' => false, 'error' => 'Giờ ra phải sau giờ vào.' );
		}

		/* Lý do là thứ duy nhất còn lại sau ba tháng — cùng luật với `VHCC_Bu::ghi()`. */
		$ly = trim( (string) $ly_do );
		if ( mb_strlen( $ly, 'UTF-8' ) < 5 ) {
			return array( 'ok' => false, 'error' => 'Ghi rõ vì sao thiếu giờ hôm ấy, ít nhất 5 chữ. '
				. 'Cửa hàng trưởng và kế toán đọc câu này để quyết.' );
		}

		$cs = self::coso_cua( $u );
		if ( '' === $cs ) {
			return array( 'ok' => false, 'error' => 'Hồ sơ của anh/chị chưa tích cơ sở chấm công nào — '
				. 'nhờ quản lý cửa hàng mở giúp rồi gửi lại.' );
		}

		/* 🔴 ĐÃ CÓ GIỜ RỒI THÌ KHÔNG XIN BÙ. Bù là điền vào ô TRỐNG; ô đã có giờ mà muốn đổi thì
		   đó là SỬA, và sửa đi đường tệp tuần của cửa hàng trưởng. Không nói rõ chỗ này thì
		   người ta gửi đơn bù để xin sửa, hai cấp duyệt xong mới bị `VHCC_Bu::ghi()` chối. */
		$da_co = VHCC_Bu::gio_hien_tai( $cs, $ng, $ma );
		if ( ! empty( $da_co['co'] ) && ( '' !== $da_co['vao'] && '—' !== $da_co['vao'] ) ) {
			return array( 'ok' => false, 'error' => 'Ngày ' . $ng . ' đã có giờ chấm ('
				. $da_co['vao'] . '–' . $da_co['ra'] . ') nên không bù được. Giờ sai thì báo cửa '
				. 'hàng trưởng sửa qua bảng công tuần.' );
		}

		$cu = self::dang_treo( $cs, $ng, $ma );
		if ( $cu ) {
			return array( 'ok' => false, 'error' => 'Ngày ' . $ng . ' đã có một đơn đang chờ ('
				. ( isset( self::TEN_TT[ $cu['trang_thai'] ] ) ? self::TEN_TT[ $cu['trang_thai'] ] : '' )
				. '). Chờ xử xong rồi gửi lại nếu cần.' );
		}

		$treo = (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'xin_bu' )
			. ' WHERE ma_nv=%s AND trang_thai IN (%s,%s)', $ma, self::CHO_CHT, self::CHO_KT ) );
		if ( $treo >= self::TREO_TOI_DA ) {
			return array( 'ok' => false, 'error' => 'Anh/chị đang có ' . $treo
				. ' đơn chờ duyệt rồi. Chờ xử bớt đã.' );
		}

		$wpdb->insert( VHCC_DB::t( 'xin_bu' ), array(
			'coso'       => $cs,
			'ngay'       => $ng,
			'ma_nv'      => $ma,
			'hau_to'     => '',
			'ho_ten'     => trim( (string) ( isset( $u['name'] ) ? $u['name'] : '' ) ),
			'vao'        => $v,
			'ra'         => $r,
			'ly_do'      => mb_substr( $ly, 0, 250 ),
			'trang_thai' => self::CHO_CHT,
			'tao_luc'    => current_time( 'mysql' ),
		) );
		$id = (int) $wpdb->insert_id;

		self::bao_cht( $cs, $ng, $id, $u );

		return array( 'ok' => true, 'id' => $id, 'ngay' => $ng, 'vao' => $v, 'ra' => $r,
			'trangThai' => self::CHO_CHT );
	}

	/** Cơ sở CHẤM CÔNG của người ấy — cùng luật với `VHCC_GioKhai::coso_cua()`. */
	private static function coso_cua( $u ) {
		$hs = VHCC_NhanSu::ho_so( isset( $u['ma_nv'] ) ? $u['ma_nv'] : '' );
		if ( ! $hs ) { return ''; }
		$ds = (array) VHCC_NhanSu::ds_coso_cham( $hs );
		if ( ! $ds ) { return ''; }
		$dang = VHCC_NhanSu::chuan_coso( isset( $u['coso'] ) ? $u['coso'] : '' );
		foreach ( $ds as $x ) {
			if ( 0 === strcasecmp( (string) $x, $dang ) ) { return (string) $x; }
		}
		return (string) $ds[0];
	}

	public static function dang_treo( $coso, $ngay, $ma_nv ) {
		global $wpdb;
		$r = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . VHCC_DB::t( 'xin_bu' )
			. ' WHERE LOWER(coso)=LOWER(%s) AND ngay=%s AND ma_nv=%s AND trang_thai IN (%s,%s)'
			. ' ORDER BY id DESC LIMIT 1',
			VHCC_NhanSu::chuan_coso( $coso ), (string) $ngay, trim( (string) $ma_nv ),
			self::CHO_CHT, self::CHO_KT ), ARRAY_A );
		return $r ? $r : null;
	}

	public static function mot( $id ) {
		global $wpdb;
		$id = (int) $id;
		if ( $id <= 0 ) { return null; }
		$r = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . VHCC_DB::t( 'xin_bu' ) . ' WHERE id=%d', $id ), ARRAY_A );
		return $r ? $r : null;
	}

	/* ====================================================================== đọc */

	/** Đơn của chính mình, mới nhất trước. */
	public static function cua_toi( $u, $so = 30 ) {
		global $wpdb;
		$ma = trim( (string) ( isset( $u['ma_nv'] ) ? $u['ma_nv'] : '' ) );
		if ( '' === $ma ) { return array(); }
		$r = $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . VHCC_DB::t( 'xin_bu' )
			. ' WHERE ma_nv=%s ORDER BY ngay DESC, id DESC LIMIT %d',
			$ma, max( 1, min( 100, (int) $so ) ) ), ARRAY_A );
		return is_array( $r ) ? $r : array();
	}

	/** Đơn đang chờ MỘT CẤP cụ thể. `$coso` rỗng = mọi cơ sở (dùng cho màn kế toán). */
	public static function cho_duyet( $trang_thai, $coso = '', $so = 100 ) {
		global $wpdb;
		$tt = (string) $trang_thai;
		if ( ! in_array( $tt, array( self::CHO_CHT, self::CHO_KT ), true ) ) { return array(); }
		$cs = VHCC_NhanSu::chuan_coso( $coso );
		$n  = max( 1, min( 300, (int) $so ) );
		$sql = ( '' !== $cs )
			? $wpdb->prepare( 'SELECT * FROM ' . VHCC_DB::t( 'xin_bu' )
				. ' WHERE trang_thai=%s AND LOWER(coso)=LOWER(%s) ORDER BY ngay, id LIMIT %d', $tt, $cs, $n )
			: $wpdb->prepare( 'SELECT * FROM ' . VHCC_DB::t( 'xin_bu' )
				. ' WHERE trang_thai=%s ORDER BY ngay, id LIMIT %d', $tt, $n );
		$r = $wpdb->get_results( $sql, ARRAY_A );
		return is_array( $r ) ? $r : array();
	}

	/**
	 * Đơn ĐANG TREO của một cơ sở trong một tháng — để lưới bảng công vẽ giờ xin bằng màu vàng.
	 *
	 * Anh Thắng: *"trên bảng công cũng sẽ hiện luôn giờ bạn xin, nhưng chữ màu nhạt hơn (vàng)
	 * để biết mình đã xin nhưng chờ duyệt"*.
	 *
	 * @return array [ MÃ NV (hoa) ][ số ngày ] => array( vao, ra, gio, trangThai )
	 */
	public static function treo_thang( $coso, $thang ) {
		global $wpdb;
		$cs = VHCC_NhanSu::chuan_coso( $coso );
		$th = trim( (string) $thang );
		if ( '' === $cs || ! preg_match( '/^\d{4}-\d{2}$/', $th ) ) { return array(); }
		$r = $wpdb->get_results( $wpdb->prepare(
			'SELECT ma_nv, ngay, vao, ra, trang_thai FROM ' . VHCC_DB::t( 'xin_bu' )
			. ' WHERE LOWER(coso)=LOWER(%s) AND ngay LIKE %s AND trang_thai IN (%s,%s)',
			$cs, $th . '-%', self::CHO_CHT, self::CHO_KT ), ARRAY_A );
		$ra = array();
		foreach ( (array) $r as $x ) {
			$ma = strtoupper( trim( (string) $x['ma_nv'] ) );
			$d  = (int) substr( (string) $x['ngay'], 8, 2 );
			$g  = null;
			$pv = self::phut( $x['vao'] );
			$pr = self::phut( $x['ra'] );
			if ( null !== $pv && null !== $pr && $pr > $pv ) { $g = round( ( $pr - $pv ) / 60, 2 ); }
			$ra[ $ma ][ $d ] = array(
				'vao' => (string) $x['vao'], 'ra' => (string) $x['ra'], 'gio' => $g,
				'trangThai' => (string) $x['trang_thai'],
			);
		}
		return $ra;
	}

	private static function phut( $hhmm ) {
		if ( ! preg_match( '/^(\d{2}):(\d{2})$/', trim( (string) $hhmm ), $m ) ) { return null; }
		return (int) $m[1] * 60 + (int) $m[2];
	}

	/* ====================================================================== duyệt */

	/**
	 * CẤP MỘT — cửa hàng trưởng nhận đơn của cơ sở mình, đẩy tiếp lên kế toán hoặc chối.
	 *
	 * ⚠️ DUYỆT Ở ĐÂY KHÔNG GHI MỘT Ô NÀO VÀO BẢNG CÔNG. Nó chỉ chuyển đơn sang `cho_kt`. Ghi là
	 *    việc của cấp hai. Trộn hai việc là cửa hàng trưởng lấy lại được đúng cái quyền bù vừa
	 *    bị thu — bằng đường vòng qua một cái đơn do chính nhân viên của họ gửi.
	 */
	public static function duyet_cht( $u, $id, $dong_y = true, $ly_do_choi = '' ) {
		global $wpdb;

		$don = self::mot( $id );
		if ( ! $don ) { return array( 'ok' => false, 'error' => 'Không thấy đơn này.' ); }
		if ( ! VHCC_Vai::duoc( $u, self::QUYEN_CHT ) ) {
			return array( 'ok' => false, 'error' => VHCC_Vai::loi( $u, self::QUYEN_CHT, 'Duyệt đơn bù giờ' ) );
		}
		if ( ! VHCC_NhanSu::co_quyen_coso( $u, (string) $don['coso'] ) ) {
			return array( 'ok' => false, 'error' => 'Đơn này thuộc cửa hàng khác.' );
		}
		if ( self::CHO_CHT !== (string) $don['trang_thai'] ) {
			return array( 'ok' => false, 'error' => 'Đơn này đã qua bước của cửa hàng trưởng rồi ('
				. self::ten_tt( $don['trang_thai'] ) . ').' );
		}
		/* 🔴 KHÔNG TỰ DUYỆT ĐƠN CỦA CHÍNH MÌNH. Cửa hàng trưởng cũng chấm công như mọi người;
		   tự gửi rồi tự duyệt là bỏ hẳn cấp một. Cùng tinh thần với chốt "không ai tự sửa giờ
		   của chính mình" ở `VHCC_Bu::vi_sao_khong_duoc()`. */
		if ( 0 === strcasecmp( trim( (string) $don['ma_nv'] ),
			trim( (string) ( isset( $u['ma_nv'] ) ? $u['ma_nv'] : '' ) ) ) ) {
			return array( 'ok' => false, 'error' => 'Đơn của chính anh/chị thì người khác duyệt. '
				. 'Nhờ quản lý hoặc kế toán xử giúp.' );
		}

		$ai = array(
			'cht_ma'  => isset( $u['ma_nv'] ) ? (string) $u['ma_nv'] : '',
			'cht_ten' => isset( $u['name'] ) ? (string) $u['name'] : '',
			'cht_luc' => current_time( 'mysql' ),
		);

		if ( ! $dong_y ) {
			$ly = trim( (string) $ly_do_choi );
			if ( mb_strlen( $ly, 'UTF-8' ) < 5 ) {
				return array( 'ok' => false, 'error' => 'Không duyệt thì phải nói vì sao — người gửi cần biết.' );
			}
			$wpdb->update( VHCC_DB::t( 'xin_bu' ),
				array_merge( $ai, array( 'trang_thai' => self::TU_CHOI,
					'ly_do_choi' => mb_substr( $ly, 0, 250 ) ) ),
				array( 'id' => (int) $don['id'] ) );
			self::bao_nguoi_gui( $don, self::TU_CHOI, $ly, $u );
			return array( 'ok' => true, 'quyet' => self::TU_CHOI );
		}

		$wpdb->update( VHCC_DB::t( 'xin_bu' ),
			array_merge( $ai, array( 'trang_thai' => self::CHO_KT ) ),
			array( 'id' => (int) $don['id'] ) );
		self::bao_ke_toan( $don, $u );
		self::bao_nguoi_gui( $don, self::CHO_KT, '', $u );
		return array( 'ok' => true, 'quyet' => self::CHO_KT );
	}

	/**
	 * CẤP HAI — kế toán duyệt lần cuối. ĐÂY là chỗ giờ vào bảng công.
	 *
	 * ⚠️ ĐI QUA `VHCC_Bu::ghi()`, KHÔNG GHI THẲNG. Ngoài nhật ký và chốt cơ sở, nó còn giữ luật
	 *    "bù chỉ điền vào ô TRỐNG" — và luật ấy quan trọng đúng ở đây: đơn nằm chờ mấy ngày,
	 *    trong lúc ấy máy có thể đã ghi được giờ thật cho ngày đó. Ghi thẳng là đè lên giờ máy
	 *    bằng một con số người ta tự khai từ tuần trước.
	 */
	public static function duyet_kt( $u, $id, $dong_y = true, $ly_do_choi = '' ) {
		global $wpdb;

		$don = self::mot( $id );
		if ( ! $don ) { return array( 'ok' => false, 'error' => 'Không thấy đơn này.' ); }
		if ( ! VHCC_Vai::duoc( $u, self::QUYEN_KT ) ) {
			return array( 'ok' => false, 'error' => VHCC_Vai::loi( $u, self::QUYEN_KT, 'Duyệt bù giờ vào bảng công' ) );
		}
		if ( self::CHO_KT !== (string) $don['trang_thai'] ) {
			return array( 'ok' => false, 'error' => 'Đơn này chưa tới bước của kế toán, hoặc đã xử rồi ('
				. self::ten_tt( $don['trang_thai'] ) . ').' );
		}

		$ai = array(
			'kt_ma'  => isset( $u['ma_nv'] ) ? (string) $u['ma_nv'] : '',
			'kt_ten' => isset( $u['name'] ) ? (string) $u['name'] : '',
			'kt_luc' => current_time( 'mysql' ),
		);

		if ( ! $dong_y ) {
			$ly = trim( (string) $ly_do_choi );
			if ( mb_strlen( $ly, 'UTF-8' ) < 5 ) {
				return array( 'ok' => false, 'error' => 'Không duyệt thì phải nói vì sao.' );
			}
			$wpdb->update( VHCC_DB::t( 'xin_bu' ),
				array_merge( $ai, array( 'trang_thai' => self::TU_CHOI,
					'ly_do_choi' => mb_substr( $ly, 0, 250 ) ) ),
				array( 'id' => (int) $don['id'] ) );
			self::bao_nguoi_gui( $don, self::TU_CHOI, $ly, $u );
			return array( 'ok' => true, 'quyet' => self::TU_CHOI );
		}

		$r = VHCC_Bu::ghi( $u, array(
			'coso'  => (string) $don['coso'],
			'ngay'  => (string) $don['ngay'],
			'ma_nv' => (string) $don['ma_nv']
				. ( '' !== trim( (string) $don['hau_to'] ) ? ( '-' . $don['hau_to'] ) : '' ),
			'vao'   => (string) $don['vao'],
			'ra'    => (string) $don['ra'],
			'ly_do' => 'Đơn bù #' . (int) $don['id'] . ': ' . (string) $don['ly_do'],
		) );
		if ( empty( $r['ok'] ) ) {
			/* ⚠️ GHI TRƯỢT THÌ ĐƠN Ở NGUYÊN CHỖ CŨ. Đánh dấu duyệt rồi mới biết không ghi được
			   là đơn biến mất khỏi hàng chờ mà bảng công không có gì — không ai đi tìm nữa. */
			return array( 'ok' => false, 'error' => 'Không ghi vào bảng công được: '
				. ( isset( $r['error'] ) ? $r['error'] : 'lý do không rõ' )
				. ' Đơn vẫn nằm chờ ở đây.' );
		}

		$wpdb->update( VHCC_DB::t( 'xin_bu' ),
			array_merge( $ai, array( 'trang_thai' => self::DUYET ) ),
			array( 'id' => (int) $don['id'] ) );
		self::bao_nguoi_gui( $don, self::DUYET, '', $u );
		return array( 'ok' => true, 'quyet' => self::DUYET, 'daGhi' => isset( $r['da_ghi'] ) ? $r['da_ghi'] : null );
	}

	public static function ten_tt( $tt ) {
		return isset( self::TEN_TT[ (string) $tt ] ) ? self::TEN_TT[ (string) $tt ] : (string) $tt;
	}

	/* ====================================================================== chuông */

	private static function bao_cht( $cs, $ng, $id, $u ) {
		if ( ! class_exists( 'VHCC_Chuong' ) || ! method_exists( 'VHCC_Chuong', 'bao' ) ) { return; }
		$chu = ( isset( $u['name'] ) ? $u['name'] : 'Nhân viên' ) . ' xin bù giờ ngày ' . $ng
			. ' — chờ anh/chị duyệt.';
		foreach ( self::ai_cht( $cs ) as $ma ) {
			VHCC_Chuong::bao( $ma, $chu, 'cc_xinbu:' . (int) $id,
				isset( $u['ma_nv'] ) ? (string) $u['ma_nv'] : '' );
		}
	}

	private static function bao_ke_toan( $don, $u ) {
		if ( ! class_exists( 'VHCC_Chuong' ) || ! method_exists( 'VHCC_Chuong', 'bao' ) ) { return; }
		if ( ! class_exists( 'VHCC_TuanCong' ) || ! method_exists( 'VHCC_TuanCong', 'ai_duyet' ) ) { return; }
		$chu = 'Cửa hàng ' . $don['coso'] . ' đã duyệt đơn bù giờ của ' . $don['ho_ten']
			. ' ngày ' . $don['ngay'] . ' — chờ kế toán duyệt lần cuối.';
		foreach ( VHCC_TuanCong::ai_duyet() as $ma ) {
			VHCC_Chuong::bao( $ma, $chu, 'cc_xinbu:' . (int) $don['id'],
				isset( $u['ma_nv'] ) ? (string) $u['ma_nv'] : '' );
		}
	}

	private static function bao_nguoi_gui( $don, $tt, $ly_do, $u ) {
		if ( ! class_exists( 'VHCC_Chuong' ) || ! method_exists( 'VHCC_Chuong', 'bao' ) ) { return; }
		$ng = (string) $don['ngay'];
		if ( self::DUYET === $tt ) {
			$chu = 'Đơn bù giờ ngày ' . $ng . ' đã được duyệt — giờ đã vào bảng công.';
		} elseif ( self::CHO_KT === $tt ) {
			$chu = 'Cửa hàng trưởng đã duyệt đơn bù giờ ngày ' . $ng . ' — đang chờ kế toán.';
		} else {
			$chu = 'Đơn bù giờ ngày ' . $ng . ' không được duyệt. Lý do: ' . $ly_do;
		}
		VHCC_Chuong::bao( (string) $don['ma_nv'], $chu, 'cc_xinbu:' . (int) $don['id'],
			isset( $u['ma_nv'] ) ? (string) $u['ma_nv'] : '' );
	}

	/** Cửa hàng trưởng của một cơ sở — hỏi bằng chính `VHCC_Vai::duoc()`, không chép lại luật. */
	private static function ai_cht( $coso ) {
		global $wpdb;
		$cs = VHCC_NhanSu::chuan_coso( $coso );
		$ra = array();
		foreach ( (array) $wpdb->get_results( $wpdb->prepare(
			'SELECT ma_nv, ho_ten, vai_tro, cua_hang FROM ' . VHCC_DB::t( 'nhan_vien' )
			. " WHERE ma_nv <> '' AND LOWER(cua_hang)=LOWER(%s)"
			. " AND trang_thai_lam_viec NOT IN ('Nghỉ việc','Nghỉ hẳn')", $cs ), ARRAY_A ) as $r ) {
			$nguoi = array( 'ma_nv' => (string) $r['ma_nv'], 'name' => (string) $r['ho_ten'],
				'role' => (string) $r['vai_tro'], 'coso' => (string) $r['cua_hang'] );
			if ( VHCC_Vai::duoc( $nguoi, self::QUYEN_CHT ) ) { $ra[] = (string) $r['ma_nv']; }
		}
		return array_values( array_unique( $ra ) );
	}
}
