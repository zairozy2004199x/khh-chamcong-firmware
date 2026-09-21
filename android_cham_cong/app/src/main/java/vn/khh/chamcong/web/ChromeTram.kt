package vn.khh.chamcong.web

import android.Manifest
import android.app.Activity
import android.content.Intent
import android.content.pm.PackageManager
import android.net.Uri
import android.webkit.GeolocationPermissions
import android.webkit.PermissionRequest
import android.webkit.ValueCallback
import android.webkit.WebChromeClient
import android.webkit.WebView
import androidx.core.content.ContextCompat

/**
 * CẦU QUYỀN GIỮA TRANG WEB VÀ ANDROID — chỗ hỏng kinh điển của mọi app bọc WebView.
 *
 * =================================================================================================
 * 🔴 CÓ HAI LỚP QUYỀN, VÀ PHẢI QUA CẢ HAI
 * =================================================================================================
 * Nhân viên bấm "Cho phép" trong hộp thoại của Android, rồi camera vẫn đen. Lý do: đó mới là lớp
 * thứ nhất — quyền của ỨNG DỤNG. Lớp thứ hai là quyền của TRANG WEB bên trong WebView, và WebView
 * KHÔNG tự cấp: nó gọi `onPermissionRequest` rồi đứng chờ mã của mình trả lời. Không viết hàm
 * này thì `getUserMedia()` bị từ chối im lặng, trang trạm báo "máy ảnh chưa sẵn sàng", và không
 * có gì trên đời chỉ ra rằng chỗ thiếu nằm ở đây.
 *
 * Định vị cũng y hệt, bằng một hàm khác (`onGeolocationPermissionsShowPrompt`). Quên một trong
 * hai là mất đúng một nửa tính năng chấm công — và mất theo kiểu trông như lỗi phần cứng.
 *
 * ⚠️ THỨ TỰ: hỏi Android TRƯỚC, rồi mới trả lời WebView. Trả lời `grant()` khi app chưa có quyền
 *    thì WebView tưởng được phép, gọi xuống tầng dưới và nhận về một luồng rỗng — lại là camera
 *    đen, lần này còn khó lần ra hơn vì không ai hỏi gì cả.
 */
class ChromeTram(
    private val hd: Activity,
    /** Gọi khi cần xin quyền Android; trả lời về `traLoiQuyen`. */
    private val xinQuyen: (Array<String>) -> Unit,
    /** Gọi khi WebView cần chọn tệp (đơn chi phí có ô đính kèm). */
    private val moChonTep: (Intent) -> Unit,
) : WebChromeClient() {

    /** Lời xin của trang, đang chờ Android trả lời. Chỉ một lời tại một thời điểm. */
    private var choWeb: PermissionRequest? = null
    private var choViTri: Pair<String, GeolocationPermissions.Callback>? = null

    /** Nơi nhận tệp người dùng vừa chọn. WebView đứng chờ nó, kể cả khi người ta bấm Huỷ. */
    var nhanTep: ValueCallback<Array<Uri>>? = null
        private set

    private fun coQuyen(q: String) =
        ContextCompat.checkSelfPermission(hd, q) == PackageManager.PERMISSION_GRANTED

    /* ─────────────────────────────────────────────────────────────────── camera cho trang */

    override fun onPermissionRequest(yc: PermissionRequest) {
        /* 🔴 CẤP ĐÚNG THỨ TRANG XIN, VÀ CHỈ TRONG HAI THỨ MÌNH BIẾT.
           Trang trạm xin camera (ảnh chấm công) và micro (gọi thoại) — không gì khác. Cấp bừa
           cả `yc.resources` là ngày nào đó một trang khác trong cùng tên miền xin thứ ba và
           được cấp mà không ai duyệt. Nên lọc lại thành đúng danh sách của mình.

           ⚠️ Tới 21/09/2026 hàm này CHỐI THẲNG micro, và lúc ấy đúng: app chấm công không có
              việc gì với micro. Nay có gọi thoại thật (xem `VHCC_Goi`) nên nó thôi là quyền
              thừa — có phép thử canh CẢ HAI VẾ, để không ai thêm được quyền micro mà không có
              tính năng đi kèm. */
        val xin = yc.resources.filter {
            it == PermissionRequest.RESOURCE_VIDEO_CAPTURE ||
                it == PermissionRequest.RESOURCE_AUDIO_CAPTURE
        }
        if (xin.isEmpty()) {
            yc.deny()
            return
        }
        val canQuyen = mutableListOf<String>()
        if (xin.contains(PermissionRequest.RESOURCE_VIDEO_CAPTURE) &&
            !coQuyen(Manifest.permission.CAMERA)
        ) {
            canQuyen.add(Manifest.permission.CAMERA)
        }
        if (xin.contains(PermissionRequest.RESOURCE_AUDIO_CAPTURE) &&
            !coQuyen(Manifest.permission.RECORD_AUDIO)
        ) {
            canQuyen.add(Manifest.permission.RECORD_AUDIO)
        }
        if (canQuyen.isEmpty()) {
            yc.grant(xin.toTypedArray())
            return
        }
        choWeb = yc
        xinQuyen(canQuyen.toTypedArray())
    }

    override fun onPermissionRequestCanceled(yc: PermissionRequest) {
        if (choWeb == yc) choWeb = null
    }

    /* ────────────────────────────────────────────────────────────────── vị trí cho trang */

    override fun onGeolocationPermissionsShowPrompt(
        goc: String,
        traLoi: GeolocationPermissions.Callback,
    ) {
        if (coQuyen(Manifest.permission.ACCESS_FINE_LOCATION) ||
            coQuyen(Manifest.permission.ACCESS_COARSE_LOCATION)
        ) {
            traLoi.invoke(goc, true, false)
            return
        }
        choViTri = goc to traLoi
        xinQuyen(
            arrayOf(
                Manifest.permission.ACCESS_FINE_LOCATION,
                Manifest.permission.ACCESS_COARSE_LOCATION,
            )
        )
    }

    /**
     * Android vừa trả lời -> chuyển tiếp cho trang đang chờ.
     *
     * 🔴 PHẢI TRẢ LỜI TRONG MỌI TRƯỜNG HỢP, KỂ CẢ KHI BỊ TỪ CHỐI. Một `PermissionRequest` không
     *    được `grant` hay `deny` là một lời hứa bỏ lửng: trang đứng chờ mãi, nút chụp bấm không
     *    lên, và người dùng thấy app treo chứ không thấy "bạn đã từ chối quyền".
     */
    fun traLoiQuyen() {
        choWeb?.let { yc ->
            choWeb = null
            /* Cấp ĐÚNG những thứ nay đã có quyền. Cấp cả gói khi mới có một nửa là WebView
               tưởng được phép rồi gọi xuống tầng dưới và nhận về một luồng rỗng — camera đen
               hoặc micro câm, không ai hỏi gì cả. */
            val duoc = mutableListOf<String>()
            if (yc.resources.contains(PermissionRequest.RESOURCE_VIDEO_CAPTURE) &&
                coQuyen(Manifest.permission.CAMERA)
            ) {
                duoc.add(PermissionRequest.RESOURCE_VIDEO_CAPTURE)
            }
            if (yc.resources.contains(PermissionRequest.RESOURCE_AUDIO_CAPTURE) &&
                coQuyen(Manifest.permission.RECORD_AUDIO)
            ) {
                duoc.add(PermissionRequest.RESOURCE_AUDIO_CAPTURE)
            }
            if (duoc.isEmpty()) { yc.deny() } else { yc.grant(duoc.toTypedArray()) }
        }
        choViTri?.let { (goc, traLoi) ->
            choViTri = null
            val duoc = coQuyen(Manifest.permission.ACCESS_FINE_LOCATION) ||
                coQuyen(Manifest.permission.ACCESS_COARSE_LOCATION)
            /* Tham số thứ ba `false` = ĐỪNG NHỚ câu trả lời. Từ chối hôm nay mà nhớ mãi thì lần
               sau trang không hỏi lại được, và người muốn đổi ý phải vào Cài đặt Android tìm. */
            traLoi.invoke(goc, duoc, false)
        }
    }

    /* ───────────────────────────────────────────────────────────────────── chọn tệp */

    /**
     * Ô đính kèm tệp (đơn chi phí, ảnh duyệt của kế toán).
     *
     * ⚠️ KHÔNG bỏ qua hàm này. Mặc định của WebView là KHÔNG mở được bộ chọn tệp nào cả: người
     *    ta bấm "Chọn tệp" và không có gì xảy ra. Trên trình duyệt thì chạy, nên lỗi này chỉ lộ
     *    ra trong app — đúng kiểu khiến người ta bảo "app hỏng, thôi mở bằng Chrome".
     */
    override fun onShowFileChooser(
        web: WebView?,
        nhan: ValueCallback<Array<Uri>>?,
        thamSo: FileChooserParams?,
    ): Boolean {
        /* Lời gọi cũ chưa trả lời mà đã có lời mới -> đóng cái cũ bằng `null`, nếu không WebView
           giữ nó mãi và ô chọn tệp chết hẳn cho tới khi nạp lại trang. */
        nhanTep?.onReceiveValue(null)
        nhanTep = nhan
        return try {
            moChonTep(thamSo?.createIntent() ?: return false)
            true
        } catch (e: Exception) {
            nhanTep = null
            false
        }
    }

    /** Người dùng đã chọn xong (hoặc bấm Huỷ). */
    fun nhanKetQuaTep(ds: Array<Uri>?) {
        nhanTep?.onReceiveValue(ds)
        nhanTep = null
    }

    /* ─────────────────────────────────────────────────────────── cửa sổ mới (window.open) */

    /**
     * 🔴 THIẾU HÀM NÀY LÀ MẤT MẤY NÚT, VÀ MẤT IM LẶNG.
     *
     * Mặc định WebView KHÔNG cho `window.open()` chạy. Trên trình duyệt thì chạy, nên lỗi chỉ
     * lộ ra trong app — đúng kiểu khiến người ta bảo "app hỏng, thôi mở bằng Chrome". Đã dò
     * trong mã và nó đụng ít nhất hai chỗ có thật:
     *
     *   · chuông thông báo: bấm vào một tin để nhảy sang trang Nội bộ
     *     (`tram.php` -> `window.open(di, '_blank', 'noopener')`);
     *   · Chi phí cơ sở: in đơn và xem chứng từ
     *     (`app.html` -> `window.open('', '_blank')` rồi tự ghi nội dung vào).
     *
     * HAI CA KHÁC HẲN NHAU, và chỉ lo một ca là vẫn hỏng ca kia:
     *
     *   1. CÓ ĐỊA CHỈ (`window.open(url)`). Không cần cửa sổ thật — cho nó đi qua đúng luật
     *      điều hướng như mọi đường dẫn khác: trong nhà thì nạp ngay tại đây, ngoài nhà thì bật
     *      trình duyệt. Mở thêm một khung nổi cho một trang cùng tên miền chỉ tổ làm người ta
     *      lạc: khung ấy không có tab dưới cùng, không có nút Chấm công.
     *
     *   2. KHÔNG CÓ ĐỊA CHỈ (`window.open('', '_blank')`). Trang định TỰ GHI nội dung vào cửa
     *      sổ con — bản in, ảnh chứng từ. Ca này BẮT BUỘC phải có một WebView con thật, vì thứ
     *      sắp hiện ra chưa tồn tại ở bất kỳ địa chỉ nào để mà mở chỗ khác.
     *
     * ⚠️ `resultMsg` PHẢI ĐƯỢC GỬI ĐI Ở CA 2. Đó là sợi dây nối cửa sổ con về cho JavaScript;
     *    không gửi thì `window.open()` trả `null`, và trang hiện "Trình duyệt chặn cửa sổ in".
     */
    override fun onCreateWindow(
        cha: WebView?,
        hopThoai: Boolean,
        nguoiDungBam: Boolean,
        ketQua: android.os.Message?,
    ): Boolean {
        if (cha == null || ketQua == null) return false

        /* Ca 1: WebView đưa địa chỉ đích qua một WebView tạm — không nạp gì, chỉ để đọc ra URL. */
        val tam = WebView(cha.context)
        tam.webViewClient = object : android.webkit.WebViewClient() {
            override fun shouldOverrideUrlLoading(
                w: WebView?,
                yc: android.webkit.WebResourceRequest?,
            ): Boolean {
                yc?.url?.let { diTiep(it) }
                tam.destroy()
                return true
            }
        }
        (ketQua.obj as? WebView.WebViewTransport)?.webView = tam
        ketQua.sendToTarget()

        /* Ca 2: sau một nhịp mà chưa có địa chỉ nào tới, nghĩa là trang sắp tự ghi nội dung vào.
           Lúc ấy mới dựng khung con thật — dựng sẵn cho cả hai ca là ca 1 nháy một khung rồi
           đóng ngay, nhìn như app giật. */
        cha.postDelayed({
            if (!tam.isAttachedToWindow) { moKhungCon(tam) }
        }, 350)
        return true
    }

    /** Đưa địa chỉ của `window.open(url)` về đúng luật điều hướng chung. */
    private fun diTiep(u: Uri) { moCuaSoCoDiaChi(u) }

    private var moCuaSoCoDiaChi: (Uri) -> Unit = {}
    private var moKhungCon: (WebView) -> Unit = {}

    /**
     * Nơi gọi khai hai việc này — `ChromeTram` không tự biết đâu là "trong nhà", cũng không tự
     * dựng được hộp thoại. Khai rời thay vì nhét vào hàm dựng để hàm dựng khỏi dài thêm hai
     * tham số mà chín phần mười người đọc tệp này không cần biết.
     */
    fun datCuaSo(coDiaChi: (Uri) -> Unit, khungCon: (WebView) -> Unit) {
        moCuaSoCoDiaChi = coDiaChi
        moKhungCon = khungCon
    }
}
