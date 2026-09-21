<?php
/**
 * TIN NHẮN RIÊNG (chat mini) — 1-1, không phải nhóm.
 *
 * Anh Thắng 30/08/2026: *"bổ sung tab chat mini bên dưới để chat với thành viên"*.
 *
 * =============================================================================================
 * 🔴 GIỐNG `VHNB_Bai`: HÀM Ở ĐÂY KHÔNG IN GÌ, KHÔNG ĐỌC `$_POST`.
 * =============================================================================================
 * Nhận tham số, trả mảng — thử được bằng con số, không phải dựng cả trang. Cửa đọc `$_POST`/
 * `$_GET` duy nhất là `VHNB_Trang::ajax_tin()`.
 *
 * =============================================================================================
 * ⚠️ `$ma_toi` PHẢI LUÔN LẤY TỪ PHIÊN ĐĂNG NHẬP, KHÔNG BAO GIỜ TỪ CLIENT.
 * =============================================================================================
 * Mọi hàm đọc ở đây (`tin_gan_day`, `tin_moi`, `danh_dau_doc`) nhận HAI mã — của "tôi" và của
 * "người kia" — rồi lọc đúng cặp đó. An toàn của cả tệp này đứng trên đúng MỘT giả định: mã "tôi"
 * là mã THẬT của người đang gõ yêu cầu, không phải một chuỗi họ tự gõ vào form. Khách gọi hàm với
 * `$ma_toi` giả thì đọc được tin nhắn của người khác — lỗi này không lộ ra ở đây, mà lộ ở chỗ gọi
 * (`VHNB_Trang::ajax_tin()`) nếu chỗ đó lỡ lấy "tôi" từ `$_POST` thay vì từ `self::toi()`.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHNB_Tin {

	/** Tin dài hơn ngần này thì cắt. */
	const DAI_TOI_DA = 2000;

	/** Tải lần đầu lấy bấy nhiêu tin gần nhất; mỗi lượt hỏi tin mới cũng trần bấy nhiêu. */
	const MOI_LAN = 60;

	/** Bao nhiêu cuộc trò chuyện hiện trong danh sách bên trái khung chat. */
	const SO_CUOC_TOI_DA = 40;

	private static function ma( $x ) {
		return strtoupper( trim( (string) $x ) );
	}

	/* ==================================================================== gửi */

	/**
	 * Gửi một tin.
	 *
	 * 🔴 Y HỆT `VHNB_Bai::dang()`: `$u` là người đăng nhập, mã và tên NGƯỜI GỬI lấy TỪ ĐÓ chứ
	 *    không nhận từ biểu mẫu — nhận từ biểu mẫu là ai cũng gửi được tin mang tên người khác.
	 */
	public static function gui( $u, $den_ma, $noi_dung ) {
		global $wpdb;
		$ten_tu = trim( (string) ( isset( $u['name'] ) ? $u['name'] : '' ) );
		if ( '' === $ten_tu ) { return array( 'ok' => false, 'error' => 'Chưa đăng nhập.' ); }
		$tu = self::ma( isset( $u['ma_nv'] ) ? $u['ma_nv'] : '' );
		if ( '' === $tu ) {
			return array( 'ok' => false,
				'error' => 'Tài khoản này chưa có Mã NV nên chưa nhắn tin được — nhờ Admin khai giúp ở hồ sơ.' );
		}
		/* 🔴 GÁC QUYỀN CÙNG VIỆC VỚI ĐĂNG BÀI/BÌNH LUẬN/THẢ TIM — xem VHNB_Quyen. Nhắn tin riêng
		   cũng là một cách trao đổi trong công ty, không phải việc khác hẳn cần bậc riêng. */
		$_q = VHNB_Quyen::vi_sao_khong( $u, 'dang' );
		if ( '' !== $_q ) { return array( 'ok' => false, 'error' => $_q ); }

		$den = self::ma( $den_ma );
		if ( '' === $den ) { return array( 'ok' => false, 'error' => 'Chưa chọn người nhận.' ); }
		if ( 0 === strcasecmp( $tu, $den ) ) {
			return array( 'ok' => false, 'error' => 'Không tự nhắn tin cho chính mình.' );
		}

		$ten_den = '';
		/* ⚠️ Gác `method_exists` CÙNG HÀM với lời gọi — luật `tools/test/kiem-goi-cheo.php`. */
		if ( class_exists( 'VHCC_NhanSu' ) && method_exists( 'VHCC_NhanSu', 'ho_so' ) ) {
			$hs = VHCC_NhanSu::ho_so( $den );
			if ( ! $hs ) {
				return array( 'ok' => false,
					'error' => 'Không thấy hồ sơ của mã "' . $den . '". Gõ đúng mã nhân viên (không phải tên).' );
			}
			$ten_den = (string) $hs['ho_ten'];
		}

		$nd = VHNB_Bai::gon( $noi_dung, self::DAI_TOI_DA );
		if ( '' === $nd ) { return array( 'ok' => false, 'error' => 'Tin nhắn rỗng.' ); }

		$ok = $wpdb->insert( VHNB_DB::t( 'tin_nhan' ), array(
			'tu'       => $tu,
			'tu_ten'   => $ten_tu,
			'den'      => $den,
			'den_ten'  => $ten_den,
			'noi_dung' => $nd,
			'da_doc'   => 0,
			'tao_luc'  => current_time( 'mysql' ),
		) );
		if ( false === $ok ) { return array( 'ok' => false, 'error' => 'MySQL: ' . $wpdb->last_error ); }
		return array( 'ok' => true, 'id' => (int) $wpdb->insert_id, 'denTen' => $ten_den );
	}

	/* ==================================================================== đọc một cuộc trò chuyện */

	/** Tin GẦN ĐÂY NHẤT giữa hai người, cũ -> mới (đúng thứ tự vẽ lên màn). */
	public static function tin_gan_day( $ma_toi, $ma_kia, $gioi_han = 0 ) {
		global $wpdb;
		$a = self::ma( $ma_toi );
		$b = self::ma( $ma_kia );
		if ( '' === $a || '' === $b ) { return array(); }
		$n = (int) $gioi_han;
		if ( $n <= 0 || $n > 200 ) { $n = self::MOI_LAN; }
		$t  = VHNB_DB::t( 'tin_nhan' );
		$ds = VHNB_DB::rows( $wpdb->prepare(
			"SELECT * FROM $t WHERE (tu=%s AND den=%s) OR (tu=%s AND den=%s) ORDER BY id DESC LIMIT %d",
			$a, $b, $b, $a, $n ) );
		return array_reverse( $ds );
	}

	/**
	 * Tin MỚI HƠN một mốc `id` — dùng cho lượt hỏi lặp lại (polling) từ trình duyệt.
	 *
	 * ⚠️ `$sau_id = 0` KHÔNG PHẢI "lấy hết". Muốn tải lần đầu thì gọi `tin_gan_day()` — hàm này
	 *    chỉ để hỏi "có gì MỚI kể từ lần trước", nên `sau_id=0` trả về TỐI ĐA `MOI_LAN` tin CŨ
	 *    NHẤT (ASC) chứ không phải mới nhất; gọi nhầm hàm ở bước tải lần đầu sẽ vẽ tin ngược.
	 */
	public static function tin_moi( $ma_toi, $ma_kia, $sau_id ) {
		global $wpdb;
		$a = self::ma( $ma_toi );
		$b = self::ma( $ma_kia );
		if ( '' === $a || '' === $b ) { return array(); }
		$t = VHNB_DB::t( 'tin_nhan' );
		return VHNB_DB::rows( $wpdb->prepare(
			"SELECT * FROM $t WHERE ((tu=%s AND den=%s) OR (tu=%s AND den=%s)) AND id > %d "
			. 'ORDER BY id ASC LIMIT %d',
			$a, $b, $b, $a, (int) $sau_id, self::MOI_LAN ) );
	}

	/** Đánh dấu đã đọc: mọi tin NGƯỜI KIA gửi cho TÔI, tính tới giờ. */
	public static function danh_dau_doc( $ma_toi, $ma_kia ) {
		global $wpdb;
		$a = self::ma( $ma_toi );
		$b = self::ma( $ma_kia );
		if ( '' === $a || '' === $b ) { return 0; }
		$t = VHNB_DB::t( 'tin_nhan' );
		return (int) $wpdb->query( $wpdb->prepare(
			"UPDATE $t SET da_doc=1 WHERE tu=%s AND den=%s AND da_doc=0", $b, $a ) );
	}

	/* ==================================================================== danh sách cuộc trò chuyện */

	/** Tổng số tin CHƯA ĐỌC, gộp mọi cuộc trò chuyện — số hiện trên tab chat. */
	public static function dem_chua_doc( $ma_toi ) {
		global $wpdb;
		$a = self::ma( $ma_toi );
		if ( '' === $a ) { return 0; }
		return (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . VHNB_DB::t( 'tin_nhan' ) . ' WHERE den=%s AND da_doc=0', $a ) );
	}

	/**
	 * Danh sách người đã từng nhắn qua lại, mới nhất trước — hiện ở cột trái khung chat.
	 *
	 * 🔴 GOM THEO "NGƯỜI KIA", KHÔNG PHẢI THEO TỪNG TIN. Một người mà 500 dòng thì danh sách
	 *    phải chỉ có MỘT dòng "người ấy", mang tin cuối cùng — không phải 500 dòng trùng tên.
	 */
	public static function ds_cuoc_tro_chuyen( $ma_toi ) {
		global $wpdb;
		$a = self::ma( $ma_toi );
		if ( '' === $a ) { return array(); }
		$t = VHNB_DB::t( 'tin_nhan' );
		$doi_tac = VHNB_DB::rows( $wpdb->prepare(
			"SELECT CASE WHEN tu=%s THEN den ELSE tu END AS ma, MAX(id) AS id_moi_nhat "
			. "FROM $t WHERE tu=%s OR den=%s GROUP BY ma ORDER BY id_moi_nhat DESC LIMIT %d",
			$a, $a, $a, self::SO_CUOC_TOI_DA ) );

		$ra = array();
		foreach ( $doi_tac as $d ) {
			$ma  = self::ma( $d['ma'] );
			if ( '' === $ma ) { continue; }
			$tin = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id=%d", (int) $d['id_moi_nhat'] ), ARRAY_A );
			if ( ! $tin ) { continue; }
			$tu_toi = ( 0 === strcasecmp( (string) $tin['tu'], $a ) );
			$chua_doc = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM $t WHERE tu=%s AND den=%s AND da_doc=0", $ma, $a ) );
			$ra[] = array(
				'ma'          => $ma,
				'ten'         => $tu_toi ? (string) $tin['den_ten'] : (string) $tin['tu_ten'],
				'tinCuoi'     => (string) $tin['noi_dung'],
				'tinCuoiToi'  => $tu_toi,
				'luc'         => (string) $tin['tao_luc'],
				'chuaDoc'     => $chua_doc,
			);
		}
		return $ra;
	}
}
