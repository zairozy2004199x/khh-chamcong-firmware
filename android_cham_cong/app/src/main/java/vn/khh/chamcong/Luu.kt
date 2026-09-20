package vn.khh.chamcong

import android.content.Context

/**
 * ĐỊA CHỈ MÁY CHỦ — thứ duy nhất app này nhớ trên máy.
 *
 * =================================================================================================
 * 🔴 APP KHÔNG GIỮ THẺ PHIÊN, KHÔNG GIỮ PIN, KHÔNG GIỮ GÌ CỦA NGƯỜI DÙNG
 * =================================================================================================
 * Đăng nhập nằm trong `WebView` và do trang trạm giữ, y như khi mở bằng trình duyệt. Chép thẻ
 * phiên ra đây là dựng thêm một bản sao thứ hai của trạng thái đăng nhập — rồi tới lúc máy chủ
 * huỷ phiên, bản sao ấy vẫn nằm đó và không ai nhớ là có nó.
 *
 * ⚠️ Một hệ quả có thật: máy dùng CHUNG ở cơ sở thì "đăng xuất" phải làm trong trang, không phải
 *    gỡ app. Đúng như khi dùng trình duyệt — và đó là điều mình muốn, vì nó chỉ có một cách làm.
 */
object Luu {

    private const val TEP = "khh_cham_cong"
    private const val KHOA_MAY_CHU = "may_chu"

    /** Địa chỉ đã lưu, hoặc chuỗi rỗng khi chưa cài đặt lần nào. */
    fun mayChu(ct: Context): String =
        ct.getSharedPreferences(TEP, Context.MODE_PRIVATE).getString(KHOA_MAY_CHU, "") ?: ""

    fun datMayChu(ct: Context, s: String) {
        ct.getSharedPreferences(TEP, Context.MODE_PRIVATE)
            .edit().putString(KHOA_MAY_CHU, chuanHoa(s)).apply()
    }

    /**
     * Gõ gì cũng ra `https://ten-mien` — KHÔNG có đường nào ra `http://`.
     *
     * 🔴 ÉP `https` LÀ MỘT CHỐT AN TOÀN, KHÔNG PHẢI MỘT TIỆN ÍCH GÕ NHANH.
     *    Qua `http`, PIN đăng nhập và ảnh khuôn mặt của nhân viên đi qua mạng dưới dạng đọc được.
     *    Tệ hơn: `getUserMedia` và định vị chỉ chạy trên nguồn an toàn, nên một địa chỉ `http`
     *    cho ra một app mở lên được mà camera câm và vị trí trống — hỏng theo kiểu khiến người
     *    ta đi đổ lỗi cho cái điện thoại.
     *    Manifest cũng đặt `usesCleartextTraffic="false"` để chốt này có hai lớp.
     */
    fun chuanHoa(s: String): String {
        var t = s.trim().trimEnd('/')
        if (t.isEmpty()) return ""
        t = t.removePrefix("https://").removePrefix("http://")
        t = t.trimEnd('/')
        if (t.isEmpty()) return ""
        return "https://$t"
    }

    /** Trang trạm nằm ở đây. Slug khớp `VHCC_Tram::SLUG_MD` bên plugin. */
    fun urlTram(ct: Context): String {
        val goc = mayChu(ct)
        return if (goc.isEmpty()) "" else "$goc/cham-cong-online/"
    }

    /**
     * Tên miền của máy chủ, để biết một đường dẫn là "trong nhà" hay đi ra ngoài.
     * Rỗng khi chưa cài đặt — lúc ấy chưa nạp trang nào nên cũng chưa ai hỏi.
     */
    fun tenMien(ct: Context): String =
        mayChu(ct).removePrefix("https://").substringBefore('/')

    /* ───────────────────────────────────────────────── sổ nhớ của lời nhắc */

    private const val KHOA_DA_HIEN = "nhac_da_hien"
    private const val KHOA_DEM_CHUONG = "chuong_dem_cu"

    /**
     * 🔴 NHỮNG LỜI NHẮC ĐÃ HIỆN RỒI.
     *
     * Máy chủ trả lời theo TRẠNG THÁI ("người này đang chưa chấm ra"), nên nó trả về y hệt ở mọi
     * lượt hỏi. Không nhớ thì cứ 15 phút một lần rung — và người ta tắt thông báo của app, mất
     * luôn cả mấy tin cần thiết. Đây là chỗ duy nhất biết "đã rung cho cái này rồi".
     *
     * ⚠️ Đây là việc của MÁY, không phải của người — nên nó nằm ở app chứ không ghi ngược lên
     *    máy chủ. Sổ `vhcc_push_da_nhac` bên kia là của đường Web Push; ghi chung vào đó là một
     *    lượt app hỏi làm tắt mất lời nhắc đẩy của chính người đó.
     */
    fun daHienNhac(ct: Context): Set<String> =
        ct.getSharedPreferences(TEP, Context.MODE_PRIVATE)
            .getStringSet(KHOA_DA_HIEN, emptySet()) ?: emptySet()

    fun datDaHienNhac(ct: Context, ds: Set<String>) {
        ct.getSharedPreferences(TEP, Context.MODE_PRIVATE)
            /* Chép sang một `HashSet` mới: `getStringSet` trả về đúng cái tập đang nằm trong bộ
               nhớ của SharedPreferences, và ghi lại chính nó thì Android có thể không thấy gì
               thay đổi — một cái bẫy có ghi rõ trong tài liệu mà vẫn cắn người ta suốt. */
            .edit().putStringSet(KHOA_DA_HIEN, HashSet(ds)).apply()
    }

    fun demChuongCu(ct: Context): String =
        ct.getSharedPreferences(TEP, Context.MODE_PRIVATE).getString(KHOA_DEM_CHUONG, "") ?: ""

    fun datDemChuongCu(ct: Context, s: String) {
        ct.getSharedPreferences(TEP, Context.MODE_PRIVATE)
            .edit().putString(KHOA_DEM_CHUONG, s).apply()
    }
}
