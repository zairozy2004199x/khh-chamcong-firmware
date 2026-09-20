package vn.khh.chamcong

import android.annotation.SuppressLint
import android.app.Activity
import android.content.ActivityNotFoundException
import android.content.Intent
import android.content.pm.ApplicationInfo
import android.net.Uri
import android.os.Bundle
import android.webkit.CookieManager
import android.webkit.WebSettings
import android.webkit.WebView
import android.widget.FrameLayout
import android.widget.Toast
import androidx.activity.OnBackPressedCallback
import androidx.activity.result.contract.ActivityResultContracts
import androidx.appcompat.app.AppCompatActivity
import vn.khh.chamcong.web.ChromeTram
import vn.khh.chamcong.web.KhachTram

/**
 * =================================================================================================
 * APP CHẤM CÔNG K&H — MỘT CÁI VỎ MỎNG QUANH TRANG TRẠM, VÀ ĐÓ LÀ CHỦ Ý
 * =================================================================================================
 * Anh Thắng 20/09/2026: *"Giờ viết áp apk full tính năng anh đã làm"*.
 *
 * 🔴 VÌ SAO KHÔNG VIẾT LẠI BẰNG KOTLIN.
 *    Toàn bộ nghiệp vụ — chấm công, ảnh đóng dấu giờ MÁY CHỦ, gác vị trí theo mốc cơ sở, truy
 *    vết cơ sở, bảng công, phiếu lương, đơn từ, BHXH, tab cửa hàng — đã nằm trong `tram.php` và
 *    đã có bộ thử canh. Viết lại bằng Kotlin là dựng BẢN THỨ HAI của cùng những luật ấy, trên
 *    một ngôn ngữ khác, không dùng chung một dòng mã nào.
 *
 *    Hai bản ấy sẽ lệch. Không phải "có thể" — chắc chắn, vào đúng ngày sửa một luật tính công
 *    mà chỉ sửa được một bên. Và cái lệch ấy hiện ra dưới dạng hai con số lương khác nhau cho
 *    cùng một người, tuỳ họ mở bằng app hay bằng web. Đó là hỏng tệ nhất mà hệ này có thể hỏng.
 *
 *    Nên app mang đúng những thứ chỉ APK mới cho được, và không mang gì khác:
 *      · biểu tượng riêng trên màn hình chính, mở ra là vào thẳng, không thanh địa chỉ;
 *      · quyền camera và vị trí do APP giữ, không phải xin lại mỗi lần Safari quên;
 *      · cài từ một tệp .apk, không cần ai biết gõ địa chỉ web;
 *      · link ra ngoài (Google Maps) bật sang trình duyệt thật, không kẹt trong khung.
 *
 * ⚠️ MỘT THỨ APP NÀY CHƯA CÓ: NHẮC CHẤM CÔNG.
 *    Lời nhắc hiện chạy bằng Web Push, mà Web Push KHÔNG hoạt động trong WebView (giới hạn của
 *    Android, không phải của mã này). Nên bản 1.0 không có nhắc; ai cần nhắc thì vẫn mở bằng
 *    Chrome như cũ. Làm được, nhưng phải là thông báo Android thật (WorkManager hỏi máy chủ) —
 *    một việc riêng, không nhét kèm vào đây cho xong.
 *
 * 🔴 KHÔNG CÓ `addJavascriptInterface` Ở BẤT KỲ ĐÂU TRONG APP NÀY.
 *    Nó là cách thông thường để trang web gọi xuống mã Android — và cũng là cách thông thường
 *    để một trang web bất kỳ chạy được mã trên máy nhân viên. App không cần nó: trang trạm chỉ
 *    cần camera và vị trí, cả hai đã có cửa chuẩn của WebView (xem `ChromeTram`). Có phép thử
 *    canh riêng điều này.
 */
class MainActivity : AppCompatActivity() {

    private lateinit var boc: FrameLayout
    private var web: WebView? = null
    private var chrome: ChromeTram? = null

    private val xinQuyen = registerForActivityResult(
        ActivityResultContracts.RequestMultiplePermissions()
    ) {
        /* Cấp hay từ chối đều phải báo về cho trang đang chờ — xem `traLoiQuyen`. */
        chrome?.traLoiQuyen()
    }

    private val chonTep = registerForActivityResult(
        ActivityResultContracts.StartActivityForResult()
    ) { kq ->
        val ds: Array<Uri>? = if (kq.resultCode == Activity.RESULT_OK) {
            val d = kq.data
            val nhieu = d?.clipData
            when {
                nhieu != null -> Array(nhieu.itemCount) { nhieu.getItemAt(it).uri }
                d?.data != null -> arrayOf(d.data!!)
                else -> null
            }
        } else {
            null
        }
        chrome?.nhanKetQuaTep(ds)
    }

    override fun onCreate(trangThai: Bundle?) {
        super.onCreate(trangThai)
        if (banGoLoi()) { WebView.setWebContentsDebuggingEnabled(true) }
        boc = FrameLayout(this)
        setContentView(boc)

        onBackPressedDispatcher.addCallback(this, object : OnBackPressedCallback(true) {
            override fun handleOnBackPressed() {
                val w = web
                /* Nút back của Android phải là nút back của TRANG trước đã. Không nối vào thì
                   bấm back ở giữa một luồng đơn từ là thoát thẳng khỏi app, mất cả biểu mẫu
                   đang gõ dở — trên trình duyệt thì nó lùi lại một bước, nên người ta không ngờ. */
                if (w != null && w.canGoBack()) {
                    w.goBack()
                } else {
                    finish()
                }
            }
        })

        ve()
    }

    /** Chưa có địa chỉ máy chủ thì hỏi; có rồi thì mở thẳng trang trạm. */
    private fun ve() {
        boc.removeAllViews()
        if (Luu.mayChu(this).isEmpty()) {
            boc.addView(Man.cai(this) { d ->
                Luu.datMayChu(this, d)
                ve()
            })
            return
        }
        boc.addView(taoWeb())
        web?.loadUrl(Luu.urlTram(this))
    }

    private fun veHong(loi: String) {
        boc.removeAllViews()
        boc.addView(
            Man.hong(
                this, loi,
                taiLai = { ve() },
                doiMayChu = {
                    Luu.datMayChu(this, "")
                    ve()
                }
            )
        )
    }

    @SuppressLint("SetJavaScriptEnabled")
    private fun taoWeb(): WebView {
        val w = WebView(this)
        val c: WebSettings = w.settings
        c.javaScriptEnabled = true

        /* 🔴 `domStorageEnabled` KHÔNG PHẢI TUỲ CHỌN — TRANG TRẠM DÙNG `localStorage` THẬT.
           Mặc định của WebView là TẮT. Tắt thì `localStorage.setItem` ném lỗi giữa chừng một
           hàm, phần còn lại của hàm không chạy, và trang hỏng ở một chỗ chẳng liên quan gì tới
           lưu trữ. Đây là cái bẫy số một của mọi app bọc WebView. */
        c.domStorageEnabled = true

        /* Camera: `getUserMedia` mở luồng video và phát nó vào thẻ <video>. Để mặc định (true)
           thì Android đòi một cú chạm trước khi cho phát — nhưng trang trạm bật camera ngay khi
           mở màn Chụp ảnh, nên khung hình đứng đen cho tới khi người ta chạm bừa vào đâu đó. */
        c.mediaPlaybackRequiresUserGesture = false

        c.setGeolocationEnabled(true)
        c.loadWithOverviewMode = true
        c.useWideViewPort = true
        c.builtInZoomControls = false
        c.displayZoomControls = false

        /* 🔴 KHOÁ MẤY CỬA KHÔNG DÙNG TỚI. Trang trạm nạp mọi thứ qua https; không có gì cần đọc
           tệp trên máy hay `content://`. Mở sẵn mấy cửa ấy là cho một trang bất kỳ (nếu có ngày
           nào lọt được vào đây) đọc tệp riêng của app. */
        c.allowFileAccess = false
        c.allowContentAccess = false
        c.mixedContentMode = WebSettings.MIXED_CONTENT_NEVER_ALLOW

        /* Khai danh tính để máy chủ phân biệt được lượt mở từ app với lượt mở từ trình duyệt —
           cần cho việc ẩn lời mời "Thêm vào màn hình chính" (trong app thì lời mời ấy vô nghĩa). */
        c.userAgentString = c.userAgentString + " KHChamCongApp/" + banApp()

        CookieManager.getInstance().setAcceptCookie(true)
        CookieManager.getInstance().setAcceptThirdPartyCookies(w, false)

        val ct = ChromeTram(
            hd = this,
            xinQuyen = { ds -> xinQuyen.launch(ds) },
            moChonTep = { yd -> chonTep.launch(yd) },
        )
        chrome = ct
        w.webChromeClient = ct
        w.webViewClient = KhachTram(
            tenMien = Luu.tenMien(this),
            moNgoai = { u -> moNgoai(u) },
            bao = { s -> veHong(s) },
        )

        /* Tệp tải về (xuất bảng công .xlsx) giao cho trình duyệt: WebView không tự tải được, và
           tự viết bộ tải là tự nhận luôn phần xin quyền ghi bộ nhớ — cho một việc mà Chrome làm
           sẵn và làm tốt hơn. */
        w.setDownloadListener { url, _, _, _, _ ->
            moNgoai(Uri.parse(url))
        }

        w.layoutParams = FrameLayout.LayoutParams(
            FrameLayout.LayoutParams.MATCH_PARENT, FrameLayout.LayoutParams.MATCH_PARENT
        )
        web = w
        return w
    }

    private fun banApp(): String = try {
        packageManager.getPackageInfo(packageName, 0).versionName ?: "?"
    } catch (e: Exception) {
        "?"
    }

    private fun moNgoai(u: Uri) {
        try {
            startActivity(Intent(Intent.ACTION_VIEW, u))
        } catch (e: ActivityNotFoundException) {
            /* Máy không có app nào mở được `tel:` hay một giao thức lạ. Nói ra, đừng im — im thì
               người ta bấm mãi một cái link không phản ứng gì. */
            Toast.makeText(this, "Máy không có ứng dụng mở được liên kết này.", Toast.LENGTH_SHORT)
                .show()
        }
    }

    override fun onPause() {
        super.onPause()
        /* Dừng hẳn camera và định vị khi app xuống nền. Không dừng thì đèn camera còn sáng sau
           khi người ta chuyển sang Zalo — và đó là thứ khiến nhân viên gỡ app, hoàn toàn chính
           đáng. `onPause()` của WebView dừng cả mã JavaScript đang chạy. */
        web?.onPause()
        web?.pauseTimers()
    }

    override fun onResume() {
        super.onResume()
        web?.resumeTimers()
        web?.onResume()
    }

    override fun onDestroy() {
        /* Gỡ WebView khỏi cây giao diện TRƯỚC khi huỷ, nếu không Android giữ lại cả Activity —
           một rò bộ nhớ kinh điển, và trên máy cũ thì nó thành đứng máy sau vài lần xoay màn. */
        web?.let { w ->
            boc.removeView(w)
            w.destroy()
        }
        web = null
        super.onDestroy()
    }

    /**
     * Cho phép soi WebView từ Chrome trên máy tính — CHỈ ở bản gỡ lỗi.
     *
     * ⚠️ ĐỌC CỜ `debuggable` CỦA GÓI, KHÔNG ĐỌC `BuildConfig.DEBUG`. Từ AGP 8 trở đi lớp
     *    `BuildConfig` KHÔNG còn được sinh ra mặc định — phải bật thêm một khoá trong Gradle.
     *    Dùng nó ở đây là đổi lấy một dòng cấu hình nữa, mà dòng ấy hỏng thì cả app không dịch
     *    được, vì một tính năng chỉ người viết mã dùng.
     */
    private fun banGoLoi(): Boolean =
        (applicationInfo.flags and ApplicationInfo.FLAG_DEBUGGABLE) != 0
}
