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
        val canCam = yc.resources.contains(PermissionRequest.RESOURCE_VIDEO_CAPTURE)
        if (!canCam) {
            /* 🔴 CHỈ CẤP CAMERA, CHỐI MỌI THỨ KHÁC — kể cả micro.
               Trang trạm chỉ cần hình. Cấp bừa cả danh sách `yc.resources` là ngày nào đó một
               trang khác trong cùng tên miền xin micro và được cấp mà không ai duyệt. */
            yc.deny()
            return
        }
        if (coQuyen(Manifest.permission.CAMERA)) {
            yc.grant(arrayOf(PermissionRequest.RESOURCE_VIDEO_CAPTURE))
            return
        }
        choWeb = yc
        xinQuyen(arrayOf(Manifest.permission.CAMERA))
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
            if (coQuyen(Manifest.permission.CAMERA)) {
                yc.grant(arrayOf(PermissionRequest.RESOURCE_VIDEO_CAPTURE))
            } else {
                yc.deny()
            }
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
}
