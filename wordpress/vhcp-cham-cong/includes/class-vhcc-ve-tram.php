<?php
/**
 * NÚT "← VỀ TRẠM" cho mấy trang mở ra từ lưới Ứng dụng.
 *
 * =============================================================================================
 * 🔴 VÌ SAO CẦN — Ở CHẾ ĐỘ STANDALONE, iOS KHÔNG CÓ NÚT BACK
 * =============================================================================================
 * Từ 17/09/2026, `scope` của manifest mở ra cả site để mấy ô trong tab Ứng dụng ở lại TRONG
 * app thay vì bật Safari. Được cái liền mạch, mất cái thanh địa chỉ — mà thanh địa chỉ chính
 * là chỗ chứa nút back.
 *
 * Vuốt từ mép trái vẫn lùi được, nhưng không ai tự đoán ra, và người đang đứng ở cơ sở thì
 * không có ai để hỏi. Không có đường về thì họ đóng hẳn app rồi mở lại — mất chỗ đang làm.
 *
 * =============================================================================================
 * ⚠️ CHỈ HIỆN KHI ĐẾN TỪ TRẠM (`?ve=tram`), KHÔNG HIỆN LUÔN
 * =============================================================================================
 * Mấy trang này còn được mở thẳng trên máy tính bàn, nơi trình duyệt đã có sẵn nút back và
 * người dùng chưa chắc biết "trạm" là gì. Một nút thừa ở góc màn hình kế toán không hỏng gì
 * nhưng cũng chẳng để làm gì — mà thứ chẳng để làm gì thì lần sau ai đó sẽ gỡ, kéo theo cả
 * trường hợp nó CẦN.
 *
 * Nên gác bằng chính tham số mà `VHCC_Ung` gắn vào lúc mở.
 *
 * =============================================================================================
 * ⚠️ KHÔNG DÙNG `history.back()`
 * =============================================================================================
 * Người dùng có thể đã đi qua năm màn bên trong trang đích trước khi muốn về. `history.back()`
 * lúc ấy lùi về màn trước đó chứ không về trạm — bấm năm lần mới ra, và mỗi lần bấm nhìn như
 * nút bị hỏng. Một đường dẫn thẳng thì luôn về đúng chỗ.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_VeTram {

	/** Tham số `VHCC_Ung` gắn vào địa chỉ khi mở một ô. */
	const CO = 've';
	const GIA_TRI = 'tram';

	/** Đến từ trạm hay không. */
	public static function den_tu_tram() {
		return isset( $_GET[ self::CO ] )
			&& self::GIA_TRI === sanitize_key( wp_unslash( $_GET[ self::CO ] ) );
	}

	/** Gắn `?ve=tram` vào một địa chỉ. Dùng ở `VHCC_Ung::ds()`. */
	public static function danh_dau( $url ) {
		return add_query_arg( self::CO, self::GIA_TRI, $url );
	}

	/**
	 * In nút. Gọi ngay trước `</body>` của trang đích.
	 *
	 * ⚠️ LỚP CÓ TIỀN TỐ RIÊNG, KHÔNG PHẢI `style="` DÁN THẲNG.
	 *
	 *    Bản đầu dán hết vào thuộc tính `style=` với lý lẽ "tự chứa, khỏi đụng lớp cùng tên" —
	 *    bốn trang đích có bốn bộ áo và mười một lớp trùng tên mà khác nghĩa (xem đầu
	 *    `templates/tram.php`). Lý lẽ ấy đúng phần lo, sai phần chữa:
	 *      · `tools/test/kiem-bo-ao-tron.php` mục 9 CẤM gõ cứng mã màu trong `style="` của
	 *        plugin này — và cấm có lý: mã màu dán tay là chỗ đầu tiên lệch khi đổi bảng màu.
	 *      · Tiền tố `vhcc-vt-` giải quyết đúng nỗi lo đụng tên, mà không phải trả giá ấy.
	 *
	 *    Bốn trang đích đều đi CÙNG bộ áo chung, nên `var(--chu-dam)` ở đây ra đúng một màu
	 *    navy trên cả bốn. Vẫn để giá trị dự phòng: trang nào chưa khai token thì nút vẫn đọc
	 *    được, thay vì thành chữ đen trên nền trong suốt.
	 *
	 * ⚠️ `z-index` 2147483000 — cao hơn mọi lớp phủ của bốn trang kia (cao nhất đang là 9999 ở
	 *    `#billZoom` bên chi phí). Nút này mà nằm dưới một lớp phủ thì đúng lúc cần nó nhất
	 *    (đang lạc trong một màn con) lại không bấm được.
	 */
	public static function nut() {
		if ( ! self::den_tu_tram() ) { return; }
		if ( ! class_exists( 'VHCC_Tram' ) || ! method_exists( 'VHCC_Tram', 'url' ) ) { return; }

		$url = VHCC_Tram::url();
		?>
<style>
.vhcc-vt{position:fixed;z-index:2147483000;
	left:12px;bottom:calc(12px + env(safe-area-inset-bottom));
	display:inline-flex;align-items:center;gap:7px;
	padding:11px 16px;border-radius:22px;
	background:var(--chu-dam,#0c1754);color:#fff;text-decoration:none;
	font:600 13.5px/1 -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;
	box-shadow:0 6px 20px rgba(12,23,84,.28);-webkit-tap-highlight-color:transparent}
.vhcc-vt b{font-weight:700;font-size:15px}
</style>
<a class="vhcc-vt" href="<?php echo esc_url( $url ); ?>"><b>←</b> Về trạm chấm công</a>
<?php
	}
}
