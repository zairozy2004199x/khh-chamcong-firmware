package vn.khh.chamcong.web

import android.net.Uri
import android.webkit.WebResourceError
import android.webkit.WebResourceRequest
import android.webkit.WebView
import android.webkit.WebViewClient

/**
 * ĐIỀU HƯỚNG: cái gì ở lại trong app, cái gì bật ra trình duyệt, và mất mạng thì hiện gì.
 */
class KhachTram(
    /** Tên miền máy chủ. Mọi thứ khác tên miền này là "đi ra ngoài". */
    private val tenMien: String,
    private val moNgoai: (Uri) -> Unit,
    private val bao: (String) -> Unit,
    /** Mở hộp In của Android cho trang đang hiện — xem `shouldOverrideUrlLoading`. */
    private val inTrang: () -> Unit,
) : WebViewClient() {

    /**
     * 🔴 CHỈ GIỮ LẠI TRONG APP NHỮNG GÌ THUỘC MÁY CHỦ MÌNH.
     *
     * Trang trạm có link "Mở Google Maps ↗", và lưới Ứng dụng có thể trỏ đi bất cứ đâu. Nuốt
     * hết vào WebView thì Google Maps mở ra trong một cái khung không có thanh địa chỉ, không
     * nút back — người dùng kẹt, và tệ hơn: một trang lạ chạy trong cái WebView vừa được cấp
     * quyền camera với định vị. Đẩy ra trình duyệt thật là vừa thoát được cả hai.
     *
     * ⚠️ So bằng ĐUÔI TÊN MIỀN có dấu chấm (`.khmatrix.com`), không bằng `contains`. `contains`
     *    thì `khmatrix.com.ke-gian.vn` cũng lọt — một tên miền của người khác chạy bên trong app
     *    đã có quyền camera.
     */
    private fun trongNha(u: Uri): Boolean = trongNha(u, tenMien)

    companion object {
        /**
         * Để ở `companion` vì `MainActivity` cũng phải hỏi đúng câu này khi xử lý
         * `window.open(url)`. Hai chỗ tự viết lấy một phép so tên miền là sớm muộn một chỗ
         * viết thành `contains` — và đó đúng là chỗ hở này sinh ra để bịt.
         */
        fun trongNha(u: Uri, tenMien: String): Boolean {
            val h = u.host?.lowercase() ?: return false
            if (tenMien.isEmpty()) return false
            return h == tenMien || h.endsWith(".$tenMien")
        }
    }

    override fun shouldOverrideUrlLoading(web: WebView?, yc: WebResourceRequest?): Boolean {
        val u = yc?.url ?: return false
        val giao = u.scheme?.lowercase()
        /* `tel:` / `mailto:` / `intent:` — WebView không mở được, và nếu để nó thử thì hiện một
           trang lỗi trắng. Giao cho hệ điều hành. */
        if (giao != null && giao != "http" && giao != "https") {
            moNgoai(u)
            return true
        }
        /* 🖨 NÚT "IN / LƯU THÀNH PDF" (phiếu lương, bộ hồ sơ nhận việc). Trong WebView
           `window.print()` không làm gì cả — không lỗi, không báo. Nên trong app, trang in đổi nút
           sang một lượt mở chính nó kèm `vhcc_in=1` (xem `VHCC_Pdf::nut_in`); tới đây thì KHÔNG
           nạp gì, mà mở hộp In của Android — có sẵn "Lưu dưới dạng PDF".
           ⚠️ Chỉ nhận trong nhà: một trang lạ không được bật hộp in của app. */
        if (trongNha(u) && u.getQueryParameter("vhcc_in") == "1") {
            inTrang()
            return true
        }
        if (trongNha(u)) return false
        moNgoai(u)
        return true
    }

    /**
     * ⚠️ CHỈ KÊU KHI CHÍNH TRANG HỎNG, không kêu khi một ô ảnh nào đó hỏng.
     *
     * `onReceivedError` bắn cho MỌI tài nguyên — một ô ảnh bản đồ tải trượt cũng gọi vào đây.
     * Không lọc `isForMainFrame` thì trang chấm công đang chạy ngon lành bỗng bị thay bằng màn
     * "mất mạng", chỉ vì một ô ảnh trang trí.
     */
    override fun onReceivedError(web: WebView?, yc: WebResourceRequest?, loi: WebResourceError?) {
        super.onReceivedError(web, yc, loi)
        if (yc?.isForMainFrame != true) return
        bao("Không mở được trang chấm công. Kiểm tra mạng rồi bấm Tải lại.")
    }
}
