package vn.khh.ghe

import android.content.Context
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateListOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONObject
import vn.khh.ghe.kho.DayHangDoi
import vn.khh.ghe.kho.HangDoi
import vn.khh.ghe.mang.Api
import vn.khh.ghe.mang.MangHong
import java.util.UUID

/**
 * TRẠNG THÁI CỦA CẢ ỨNG DỤNG, VÀ MỌI VIỆC ĐỤNG TỚI MÁY CHỦ.
 *
 * 🔴 MỘT NƠI DUY NHẤT. App này có bốn màn và chúng cùng nhìn vào một sự thật: đang đăng nhập
 *    bằng ai, đang cầm bao nhiêu tiền, hàng đợi còn mấy lượt chưa đẩy. Để mỗi màn tự giữ một
 *    bản là bốn bản trôi khỏi nhau — màn Quỹ nói còn 0đ trong khi màn Chốt vừa ghi thêm 200.000đ.
 *
 * ⚠️ Mọi lượt gọi mạng đều `withContext(Dispatchers.IO)`. Gọi trên luồng giao diện là Android
 *    ném `NetworkOnMainThreadException` — và ném đúng lúc người ta vừa bấm.
 */
object Kho {

    private lateinit var ctx: Context
    lateinit var luu: Luu
        private set
    private lateinit var hangDoi: HangDoi

    fun gan(c: Context) {
        ctx = c.applicationContext
        luu = Luu(ctx)
        hangDoi = HangDoi(ctx)
        demHangDoi = hangDoi.soLuong()
        /* 🔴 ĐẶT MÀN ĐẦU Ở ĐÂY, KHÔNG ĐẶT LÚC KHAI BIẾN.
           Biến của `object` được khởi tạo ở lượt chạm đầu tiên vào `Kho` — mà lượt đó chính là
           dòng `Kho.gan(...)` này, tức là TRƯỚC khi `luu` có giá trị. Khai
           `mutableStateOf(if (luu.daVao()) …)` thì `luu` chưa tồn tại, kết quả luôn là màn PIN,
           và người đã đăng nhập vẫn bị hỏi PIN mỗi lần mở app. */
        manDangMo = if (luu.daVao()) Man.QUY else Man.DANG_NHAP
    }

    // ─────────────────────────────────────────────────────────────── trạng thái màn hình

    var manDangMo by mutableStateOf(Man.DANG_NHAP)
    var dangBan by mutableStateOf(false)
    var loi by mutableStateOf("")
    var bao by mutableStateOf("")
    var demHangDoi by mutableStateOf(0)

    /** Ghế đang chốt: số liệu `chot_xem` vừa lấy về. */
    var gheDangChot by mutableStateOf<GheChot?>(null)

    /** Tiền đang cầm + báo cáo ca. */
    var tienDangCam by mutableStateOf(0)
    var tuNganGhe by mutableStateOf(0)
    var tuQuay by mutableStateOf(0)
    val caDaChot = mutableStateListOf<LuotChot>()

    enum class Man { DANG_NHAP, QUY, QUET, CHOT }

    data class GheChot(
        val maMay: String,
        val coSo: String,
        val song: Boolean,
        val lanDau: Boolean,
        val chiSoTruoc: Int,
        val donVi: Int,
        val theoHeThong: Int,
        val chotTruocLuc: String,
        val chotTruocAi: String
    )

    data class LuotChot(
        val maMay: String, val chiSo: Int, val chiSoTruoc: Int,
        val tienDem: Int, val lechDem: Int, val lechMay: Int,
        val luc: String, val choDay: Boolean
    )

    // ─────────────────────────────────────────────────────────────── việc

    private fun api() = Api(luu.diaChi)

    fun xoaBao() { loi = ""; bao = "" }

    suspend fun dangNhap(diaChi: String, pin: String): Boolean {
        val dc = Luu.chuanDiaChi(diaChi)
        if (dc.isEmpty()) { loi = "Chưa nhập địa chỉ máy chủ."; return false }
        if (!Regex("^\\d{4,8}$").matches(pin)) { loi = "PIN phải gồm 4–8 chữ số."; return false }
        dangBan = true; loi = ""
        try {
            val tl = withContext(Dispatchers.IO) {
                Api(dc).goi("login", JSONObject().put("pin", pin), "")
            }
            if (!tl.optBoolean("ok")) {
                loi = tl.optString("error", "Không đăng nhập được."); return false
            }
            luu.diaChi = dc
            luu.token = tl.optString("token")
            luu.ten = tl.optString("name")
            luu.vaiTro = tl.optString("role")
            manDangMo = Man.QUY
            taiQuy()
            return true
        } catch (e: MangHong) {
            loi = e.loi; return false
        } finally { dangBan = false }
    }

    fun raKhoi() {
        luu.raKhoi()
        gheDangChot = null
        caDaChot.clear()
        tienDangCam = 0; tuNganGhe = 0; tuQuay = 0
        manDangMo = Man.DANG_NHAP
    }

    /**
     * Tải lại tiền đang cầm + báo cáo ca.
     *
     * ⚠️ MẤT MẠNG KHÔNG PHẢI LÀ LỖI Ở ĐÂY. Người thu vẫn chốt ca được khi mất sóng (lượt chốt
     *    vào hàng đợi); chỉ là con số trên màn là con số cũ. Nói ra bằng một dòng nhỏ, đừng dựng
     *    một màn báo lỗi chắn đường họ làm việc.
     */
    suspend fun taiQuy() {
        if (!luu.daVao()) return
        try {
            val tl = withContext(Dispatchers.IO) {
                api().goi("quy_toi", JSONObject(), luu.token)
            }
            if (tl.optString("ma") == "het_phien") { raKhoi(); loi = "Phiên đã hết — đăng nhập lại."; return }
            if (!tl.optBoolean("ok")) return
            val c = tl.optJSONObject("cam") ?: return
            tienDangCam = c.optInt("tong")
            tuNganGhe = c.optInt("tu_ghe")
            tuQuay = c.optInt("tu_quay")
        } catch (e: MangHong) {
            /* Im lặng có chủ ý — xem chú thích trên. Số hàng đợi bên dưới đã nói giúp. */
        } finally {
            demHangDoi = hangDoi.soLuong()
        }
    }

    /**
     * Hỏi mốc chốt ca của một ghế.
     *
     * 🔴 BẮT BUỘC CÓ MẠNG. Không có mốc chỉ số lần trước thì người thu gõ một con số mới mà
     *    không có gì để đối chiếu — và một chữ số gõ nhầm sẽ đi thẳng vào sổ rồi nằm đó mãi,
     *    vì kỳ sau lấy chính nó làm mốc trừ. Thà bảo họ ra chỗ có sóng.
     */
    suspend fun moChot(maMay: String): Boolean {
        val ma = maMay.trim().uppercase()
        if (ma.isEmpty()) { loi = "Chưa biết ghế nào — quét mã QR trên ghế giúp em."; return false }
        dangBan = true; loi = ""
        try {
            val tl = withContext(Dispatchers.IO) {
                api().goi("chot_xem", JSONObject().put("ma_may", ma), luu.token)
            }
            if (tl.optString("ma") == "het_phien") { raKhoi(); loi = "Phiên đã hết — đăng nhập lại."; return false }
            if (!tl.optBoolean("ok")) { loi = tl.optString("error", "Không mở được chốt ca."); return false }
            gheDangChot = GheChot(
                maMay = tl.optString("ma_may", ma),
                coSo = tl.optString("coso"),
                song = tl.optInt("song") == 1,
                lanDau = tl.optInt("lan_dau") == 1,
                chiSoTruoc = tl.optInt("chi_so_truoc"),
                donVi = tl.optInt("don_vi", 5000),
                theoHeThong = tl.optInt("theo_he_thong"),
                chotTruocLuc = tl.optString("chot_truoc_luc"),
                chotTruocAi = tl.optString("chot_truoc_ai")
            )
            manDangMo = Man.CHOT
            return true
        } catch (e: MangHong) {
            loi = "Không hỏi được mốc chốt ca: ${e.loi}\n\n" +
                "Chốt ca CẦN chỉ số lần trước để đối chiếu — không có nó thì con số gõ vào " +
                "không kiểm được. Ra chỗ có sóng rồi bấm lại giúp em."
            return false
        } finally { dangBan = false }
    }

    /**
     * GHI MỘT LƯỢT CHỐT CA.
     *
     * 🔴 GHI VÀO MÁY TRƯỚC, ĐẨY LÊN MÁY CHỦ SAU — luôn luôn, kể cả khi đang có sóng.
     *
     *    Người thu đã mở ngăn, đọc chỉ số, đếm tiền. Đóng ngăn lại rồi thì KHÔNG CÓ CÁCH NÀO
     *    đọc lại con số vừa nhìn thấy: ghế vẫn chạy, chỉ số vẫn đi tiếp. Thứ phải giữ bằng mọi
     *    giá là con số đó, không phải lượt gọi mạng.
     *
     *    Nên đường đi luôn là: vào hàng đợi -> thử đẩy ngay -> đẩy được thì xoá khỏi hàng đợi.
     *    Không có nhánh "có mạng thì gửi thẳng": hai nhánh là hai đường, và đường ít chạy hơn
     *    là đường hỏng mà không ai biết.
     *
     * ⚠️ `ma_lan` sinh Ở ĐÂY, một lần, và đi theo lượt đó suốt. Đẩy lại bao nhiêu lần cũng cùng
     *    một mã, nên máy chủ nhận ra và không ghi hai lần — xem `VHG_Quy::chot()`.
     */
    suspend fun chot(chiSo: Int, tienDem: Int, ghiChu: String): Boolean {
        val g = gheDangChot ?: return false
        if (chiSo <= 0) { loi = "Chưa nhập chỉ số trên màn máy đếm tiền."; return false }
        if (tienDem < 0) { loi = "Số tiền đếm được không âm được."; return false }
        if (!g.lanDau && chiSo < g.chiSoTruoc && ghiChu.isBlank()) {
            loi = "Chỉ số $chiSo NHỎ HƠN lần chốt trước (${g.chiSoTruoc}). Máy đếm không chạy lùi — " +
                "kiểm lại con số. Nếu vừa thay cục nhận tiền thì ghi rõ vào ô ghi chú rồi bấm lại."
            return false
        }

        dangBan = true; loi = ""
        val than = JSONObject()
            .put("ma_may", g.maMay)
            .put("chi_so", chiSo)
            .put("tien_dem", tienDem)
            .put("ghi_chu", ghiChu)
            .put("ma_lan", UUID.randomUUID().toString())
        val lenh = hangDoi.them("chot_luu", than)
        demHangDoi = hangDoi.soLuong()

        var dayDuoc = false
        var loiDay = ""
        try {
            val tl = withContext(Dispatchers.IO) { api().goi("chot_luu", than, luu.token) }
            if (tl.optBoolean("ok")) {
                hangDoi.xoa(lenh.id)
                dayDuoc = true
                bao = tl.optString("thong_bao", "Đã chốt ghế ${g.maMay}.")
            } else if (tl.optString("ma") == "het_phien") {
                loiDay = "Phiên đã hết — lượt chốt đã lưu trong máy, đăng nhập lại là tự gửi đi."
            } else {
                /* Máy chủ TỪ CHỐI có lý do (ghế khác cơ sở, mệnh giá lạ…). Gửi lại không đổi được
                   gì, nên bỏ khỏi hàng đợi và nói thẳng cho người bấm. */
                hangDoi.xoa(lenh.id)
                loi = tl.optString("error", "Máy chủ từ chối lượt chốt này.")
                dangBan = false
                demHangDoi = hangDoi.soLuong()
                return false
            }
        } catch (e: MangHong) {
            loiDay = e.loi
        }

        demHangDoi = hangDoi.soLuong()
        if (!dayDuoc) {
            DayHangDoi.hen(ctx)
            bao = "Đã lưu lượt chốt ghế ${g.maMay} vào máy — CHƯA gửi được lên máy chủ " +
                "($loiDay). App tự gửi khi có mạng; đừng chốt lại lượt này."
        }

        caDaChot.add(
            LuotChot(
                maMay = g.maMay, chiSo = chiSo, chiSoTruoc = g.chiSoTruoc, tienDem = tienDem,
                lechDem = if (g.lanDau) 0 else tienDem - (chiSo - g.chiSoTruoc) * g.donVi,
                lechMay = if (g.lanDau) 0 else (chiSo - g.chiSoTruoc) * g.donVi - g.theoHeThong,
                luc = "", choDay = !dayDuoc
            )
        )
        gheDangChot = null
        manDangMo = Man.QUY
        dangBan = false
        taiQuy()
        return true
    }

    /** Nộp toàn bộ tiền đang cầm về quầy. */
    suspend fun nop(ghiChu: String): Boolean {
        if (tienDangCam <= 0) { loi = "Anh/chị đang không cầm đồng nào chưa nộp."; return false }
        dangBan = true; loi = ""
        val than = JSONObject().put("ghi_chu", ghiChu).put("ma_lan", UUID.randomUUID().toString())
        try {
            val tl = withContext(Dispatchers.IO) { api().goi("nop_tao", than, luu.token) }
            if (tl.optBoolean("ok")) {
                bao = tl.optString("thong_bao", "Đã nộp.")
                caDaChot.clear()
                taiQuy()
                return true
            }
            loi = tl.optString("error", "Không nộp được.")
            return false
        } catch (e: MangHong) {
            /* 🔴 NỘP TIỀN THÌ KHÔNG BỎ VÀO HÀNG ĐỢI. Lượt nộp là lúc tiền CHUYỂN TAY thật, và
               nó phải xảy ra khi hai người đang đứng trước mặt nhau. Đẩy sau là ghi một lần
               chuyển tay vào lúc không ai chứng kiến. Bảo họ chờ có sóng. */
            loi = "Chưa nộp được: ${e.loi}\n\nTiền vẫn tính là đang trên tay anh/chị. " +
                "Nộp lại khi có mạng, ngay trước mặt người nhận."
            return false
        } finally { dangBan = false }
    }

    /** Đẩy tay hàng đợi — nút cho người sốt ruột, và cho lúc WorkManager bị hệ thống hoãn. */
    suspend fun dayNgay() {
        dangBan = true
        try {
            withContext(Dispatchers.IO) { DayHangDoi.dayMotLuot(ctx) }
            demHangDoi = hangDoi.soLuong()
            bao = if (demHangDoi == 0) "Đã gửi hết lượt còn tồn." else "Còn $demHangDoi lượt chưa gửi được."
        } finally { dangBan = false }
        taiQuy()
    }

    fun dsHangDoi(): List<HangDoi.Lenh> = hangDoi.doc()
}
