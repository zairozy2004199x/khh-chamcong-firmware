<?php
/**
 * AI ĐƯỢC LÀM GÌ TRÊN HỆ DỰ ÁN.
 *
 * =============================================================================================
 * 🔴 BA VIỆC, MỖI VIỆC MỘT BẬC — ĐI THEO ĐÚNG THANG VAI CỦA HỆ NHÂN SỰ
 * =============================================================================================
 * Không dựng thang bậc riêng: `VHCC_Vai` đã có năm bậc NV < CHT < QL < KE_TOAN < ADMIN, và mọi
 * plugin khác đều đo bằng nó. Dựng thang thứ hai là hai nơi phải đồng bộ với nhau mãi, và ngày
 * chúng lệch nhau thì không màn hình nào nói ra.
 *
 * =============================================================================================
 * ⚠️ CẬP NHẬT TIẾN ĐỘ LÀ VIỆC CỦA NGƯỜI LÀM, KHÔNG PHẢI CỦA NGƯỜI QUẢN
 * =============================================================================================
 * Bậc Nhân viên cập nhật được tiến độ — nhưng CHỈ của bộ phận mình. Siết lên bậc cao hơn thì
 * người thật sự làm việc không báo được, và tiến độ sẽ do quản lý gõ hộ theo trí nhớ; lúc ấy
 * cả hệ này chỉ còn là một bảng số cho đẹp.
 *
 * ⚠️ CHƯA CÀI PLUGIN CHẤM CÔNG THÌ CHO QUA, không chối. `VHCC_Vai` nằm ở plugin khác; gỡ nó ra
 *    mà ở đây trả false là cả hệ dự án đóng cửa với mọi người, kể cả Admin — không còn ai vào
 *    để bật lại. Thiếu bộ đo bậc thì thôi không đo.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHDA_Quyen {

	/* Việc → bậc tối thiểu, khai bằng MÃ BẬC của `VHCC_Vai`. */
	const VIEC = array(
		'xem'      => array( 'nhan' => 'Xem dự án',                      'bac' => 'NHAN_VIEN' ),
		'tien_do'  => array( 'nhan' => 'Cập nhật tiến độ bộ phận mình',  'bac' => 'NHAN_VIEN' ),
		'lap'      => array( 'nhan' => 'Lập dự án · sửa hợp đồng',       'bac' => 'QUAN_LY' ),
		'chuyen'   => array( 'nhan' => 'Chuyển chặng · chốt ngày',       'bac' => 'QUAN_LY' ),
		'ban_giao' => array( 'nhan' => 'Bàn giao xuống bộ phận',         'bac' => 'QUAN_LY' ),
		'gan_don'  => array( 'nhan' => 'Gán đơn chi phí vào dự án',      'bac' => 'QUAN_LY' ),
		'huy'      => array( 'nhan' => 'Huỷ dự án',                      'bac' => 'ADMIN' ),
	);

	public static function duoc( $u, $viec ) {
		if ( ! isset( self::VIEC[ $viec ] ) ) { return false; }
		/* ⚠️ Gác `method_exists` CÙNG HÀM với lời gọi — luật `tools/test/kiem-goi-cheo.php`. */
		if ( ! class_exists( 'VHCC_Vai' ) || ! method_exists( 'VHCC_Vai', 'bac' )
			|| ! defined( 'VHCC_Vai::BAC' ) ) { return true; }
		$bac_ds = constant( 'VHCC_Vai::BAC' );
		$can    = self::VIEC[ $viec ]['bac'];
		$can_so = isset( $bac_ds[ $can ] ) ? (int) $bac_ds[ $can ] : 5;
		return (int) VHCC_Vai::bac( $u ) >= $can_so;
	}

	/** Câu chối nói RÕ cần bậc nào — '' là được phép. */
	public static function vi_sao_khong( $u, $viec ) {
		if ( self::duoc( $u, $viec ) ) { return ''; }
		$v = isset( self::VIEC[ $viec ] ) ? self::VIEC[ $viec ] : null;
		if ( ! $v ) { return 'Việc không có thật.'; }
		/* Nói ra TÊN bậc, không phải mã. `VHCC_Vai::TEN` là bảng mã → tên hiện ra màn — người
		   đọc cần biết phải xin ai, chứ "cần vai từ QUAN_LY trở lên" thì không ai xin nổi. */
		$ten_bac = $v['bac'];
		if ( class_exists( 'VHCC_Vai' ) && defined( 'VHCC_Vai::TEN' ) ) {
			$m = constant( 'VHCC_Vai::TEN' );
			if ( isset( $m[ $v['bac'] ] ) ) { $ten_bac = $m[ $v['bac'] ]; }
		}
		return 'Việc "' . $v['nhan'] . '" cần vai từ ' . $ten_bac . ' trở lên.';
	}

	/**
	 * BỘ PHẬN CỦA NGƯỜI ĐANG ĐĂNG NHẬP — dùng để gác "chỉ cập nhật tiến độ bộ phận mình".
	 *
	 * ═════════════════════════════════════════════════════════════════════════════════════════
	 * 🔴 HỆ NÀY CÓ HAI KHÁI NIỆM "BỘ PHẬN" KHÁC HẲN NHAU — ĐỌC KỸ TRƯỚC KHI SỬA
	 * ═════════════════════════════════════════════════════════════════════════════════════════
	 *   1. BỘ PHẬN TÍNH LƯƠNG (`VHCC_Luong::BP_DS`) — đúng bốn cái: Máy tự động · Khu vui chơi ·
	 *      Văn phòng · Part time. Nó gắn với CƠ SỞ, không gắn với người, và sinh ra để chọn cách
	 *      tính công. "Kỹ thuật" hay "Marketing" KHÔNG có trong đó.
	 *   2. BỘ PHẬN CÔNG VIỆC — Kỹ thuật, Marketing, Vận hành… khai ở bảng người dùng của plugin
	 *      Chi phí, gắn với TỪNG NGƯỜI. Đây mới là thứ anh Thắng nói khi bảo *"bàn giao xuống
	 *      từng bộ phận"*.
	 *
	 * Bản đầu của hàm này hỏi nhầm cái thứ nhất. Nó chạy, không báo lỗi gì, và trả về "Chưa xếp"
	 * cho mọi người — tức là cả công ty mất quyền cập nhật tiến độ, mà câu chối thì đổ cho hồ sơ
	 * chưa khai. Đúng loại lỗi không ai lần ra.
	 *
	 * ⚠️ Nên: hỏi bảng người dùng bên Chi phí TRƯỚC. Chỉ khi không có ở đó mới rơi về bộ phận
	 *    theo cơ sở — để cơ sở đã xếp "Văn phòng"/"Khu vui chơi" vẫn dùng được, chứ không phải
	 *    để thay thế.
	 *
	 * ⚠️ Không tra được thì trả '' — '' nghĩa là "không biết thuộc bộ phận nào", tức KHÔNG khớp
	 *    bộ phận nào cả, chứ không phải khớp tất.
	 */
	public static function bo_phan_cua( $u ) {
		$ten = is_array( $u ) && isset( $u['name'] ) ? trim( (string) $u['name'] ) : '';

		/* NGUỒN CHÍNH: bảng người dùng của plugin Chi phí — nơi bộ phận khai theo TỪNG NGƯỜI.
		   ⚠️ Gác `method_exists` cùng thân hàm với lời gọi (luật `tools/test/kiem-goi-cheo.php`). */
		if ( '' !== $ten && class_exists( 'VHCP_Cfg' ) && method_exists( 'VHCP_Cfg', 'get_users' ) ) {
			foreach ( (array) VHCP_Cfg::get_users() as $x ) {
				$x = (array) $x;
				if ( empty( $x['ten'] ) ) { continue; }
				if ( mb_strtolower( trim( (string) $x['ten'] ) ) !== mb_strtolower( $ten ) ) { continue; }
				$bp = trim( (string) ( isset( $x['boPhan'] ) ? $x['boPhan'] : '' ) );
				if ( '' !== $bp ) { return $bp; }
				break;   // thấy đúng người rồi mà ô bộ phận trống -> rơi xuống nguồn dưới
			}
		}

		/* NGUỒN LUI: bộ phận theo CƠ SỞ (bốn bộ phận tính lương). Giữ để cơ sở đã xếp sẵn vẫn
		   dùng được ngay, không phải khai lại từng người. */
		$cs = is_array( $u ) && isset( $u['coso'] ) ? trim( (string) $u['coso'] ) : '';
		if ( '' === $cs ) {
			$ma = '';
			foreach ( array( 'maNV', 'ma_nv' ) as $k ) {
				if ( is_array( $u ) && ! empty( $u[ $k ] ) ) { $ma = trim( (string) $u[ $k ] ); break; }
			}
			if ( '' === $ma ) { return ''; }
			if ( ! class_exists( 'VHCC_NhanSu' ) || ! method_exists( 'VHCC_NhanSu', 'ho_so' ) ) { return ''; }
			$hs = VHCC_NhanSu::ho_so( $ma );
			if ( ! is_array( $hs ) || empty( $hs['cua_hang'] ) ) { return ''; }
			$cs = trim( (string) $hs['cua_hang'] );
		}
		if ( '' === $cs ) { return ''; }
		if ( ! class_exists( 'VHCC_Luong' ) || ! method_exists( 'VHCC_Luong', 'bo_phan_cua' ) ) { return ''; }
		$bp = trim( (string) VHCC_Luong::bo_phan_cua( $cs ) );

		/* 🔴 "Chưa xếp" KHÔNG phải một bộ phận — nó là câu trả lời "cơ sở này chưa ai xếp vào
		   đâu". Trả nó ra như một tên bộ phận thì mọi người ở các cơ sở chưa xếp lại khớp với
		   nhau, và họ sửa được tiến độ của nhau. */
		if ( defined( 'VHCC_Luong::BP_CHUA_XEP' ) && $bp === constant( 'VHCC_Luong::BP_CHUA_XEP' ) ) { return ''; }
		return $bp;
	}

	/**
	 * Người này có được cập nhật tiến độ của bộ phận ấy không — trả câu lỗi, '' là được.
	 *
	 * 🔴 BẬC QUẢN LÝ TRỞ LÊN THÌ CẬP NHẬT ĐƯỢC MỌI BỘ PHẬN. Không có ngoại lệ ấy thì một quản
	 *    lý không thuộc bộ phận nào (rất thường gặp) chẳng sửa nổi dòng nào, kể cả lúc người
	 *    trực tiếp làm đang nghỉ.
	 */
	public static function vi_sao_khong_sua_tien_do( $u, $bo_phan ) {
		$loi = self::vi_sao_khong( $u, 'tien_do' );
		if ( '' !== $loi ) { return $loi; }
		if ( self::duoc( $u, 'ban_giao' ) ) { return ''; }   // Quản lý trở lên: mọi bộ phận
		$cua_toi = self::bo_phan_cua( $u );
		if ( '' === $cua_toi ) {
			return 'Hồ sơ của anh/chị chưa khai bộ phận, nên chưa biết được phép cập nhật phần nào. '
				. 'Nhờ quản lý khai bộ phận ở màn Quản lý nhân sự.';
		}
		if ( mb_strtolower( trim( $cua_toi ) ) === mb_strtolower( trim( (string) $bo_phan ) ) ) { return ''; }
		return 'Phần này của bộ phận "' . $bo_phan . '", còn anh/chị thuộc "' . $cua_toi . '".';
	}
}
