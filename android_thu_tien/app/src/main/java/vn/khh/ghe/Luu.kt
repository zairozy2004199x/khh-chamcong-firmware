package vn.khh.ghe

import android.content.Context
import android.content.SharedPreferences

/**
 * NHỚ ĐỊA CHỈ MÁY CHỦ VÀ PHIÊN ĐĂNG NHẬP.
 *
 * ⚠️ Dùng SharedPreferences THƯỜNG, không dùng EncryptedSharedPreferences.
 *    Thư mục riêng của app đã là vùng cách ly của Android: app khác không đọc được, và máy đã
 *    root thì lớp mã hoá kia cũng không cản nổi. Đổi lại, `EncryptedSharedPreferences` có tiền
 *    sử ném lỗi ở lần mở đầu tiên trên một số máy (khoá trong Keystore bị mất sau khi đổi vân
 *    tay chẳng hạn) — và lỗi đó sẽ nổ đúng lúc nhân viên mở app ra để chốt ca.
 *
 * ⚠️ Phiên hết hạn sau 30 ngày (xem `VHG_Auth::TTL` bên máy chủ). Nhân viên mở app mỗi ca nên
 *    thực tế không bao giờ chạm tới hạn đó; chạm rồi thì máy chủ trả mã `het_phien` và app đưa
 *    về màn PIN — không tự đăng nhập lại được, và không nên.
 */
class Luu(ctx: Context) {

    private val p: SharedPreferences =
        ctx.getSharedPreferences("khh_thu_tien", Context.MODE_PRIVATE)

    var diaChi: String
        get() = p.getString("dia_chi", "") ?: ""
        set(v) = p.edit().putString("dia_chi", chuanDiaChi(v)).apply()

    var token: String
        get() = p.getString("token", "") ?: ""
        set(v) = p.edit().putString("token", v).apply()

    var ten: String
        get() = p.getString("ten", "") ?: ""
        set(v) = p.edit().putString("ten", v).apply()

    var vaiTro: String
        get() = p.getString("vai_tro", "") ?: ""
        set(v) = p.edit().putString("vai_tro", v).apply()

    fun daVao(): Boolean = token.isNotEmpty() && diaChi.isNotEmpty()

    fun raKhoi() {
        p.edit().remove("token").remove("ten").remove("vai_tro").apply()
    }

    companion object {
        /**
         * Chuẩn hoá địa chỉ người ta gõ vào.
         *
         * 🔴 Người dùng gõ "khmatrix.com", "khmatrix.com/ghe", "https://khmatrix.com/ghe/" —
         *    ba thứ đó phải ra cùng một địa chỉ. Không chuẩn hoá thì mỗi kiểu gõ là một lỗi
         *    "không nối được máy chủ" khác nhau, và người đứng đó không biết mình gõ sai chỗ nào.
         *
         * ⚠️ Mặc định `https://`. Để `http://` là gói tin mang TOKEN đi qua mạng không mã hoá —
         *    ở quán cà phê cạnh cửa hàng thì ai cũng đọc được.
         */
        fun chuanDiaChi(v: String): String {
            var s = v.trim()
            if (s.isEmpty()) return ""
            s = s.removePrefix("http://").removePrefix("https://")
            s = s.trimEnd('/')
            /* Bỏ đuôi `/ghe` nếu người ta chép cả đường dẫn trang: mình tự nối lại ở `Api`. */
            if (s.endsWith("/ghe", ignoreCase = true)) s = s.dropLast(4).trimEnd('/')
            return if (s.isEmpty()) "" else "https://$s"
        }
    }
}
