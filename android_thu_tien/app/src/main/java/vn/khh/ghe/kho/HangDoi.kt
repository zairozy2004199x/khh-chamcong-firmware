package vn.khh.ghe.kho

import android.content.Context
import org.json.JSONArray
import org.json.JSONObject
import java.io.File
import java.util.UUID

/**
 * HÀNG ĐỢI NGOẠI TUYẾN — LƯỢT CHỐT CA GHI XONG MÀ CHƯA ĐẨY ĐƯỢC LÊN MÁY CHỦ.
 *
 * ════════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 VÌ SAO PHẢI CÓ.
 *
 *    Nhân viên mở ngăn ghế, đọc chỉ số, đếm tiền — rồi bấm chốt, và điện thoại báo "không có
 *    mạng". Ngăn đã đóng, tiền đã cầm trong tay, chỉ số trên màn máy đếm thì đã đi tiếp vì ghế
 *    vẫn đang chạy. KHÔNG CÓ CÁCH NÀO ĐỌC LẠI con số vừa nhìn thấy.
 *
 *    Nên: ghi vào máy TRƯỚC, đẩy lên máy chủ SAU. Cái phải giữ bằng mọi giá là con số người ta
 *    vừa đọc bằng mắt, không phải lượt gọi mạng.
 *
 * 🔴 MỖI LƯỢT MANG MỘT `ma_lan` DUY NHẤT, SINH NGAY LÚC BẤM.
 *    Đẩy lên rồi mất sóng giữa chừng thì app không biết máy chủ đã ghi hay chưa, và nó sẽ đẩy
 *    lại. Máy chủ dùng `ma_lan` để nhận ra "lượt này ghi rồi" và trả về đúng lượt cũ thay vì ghi
 *    thêm — xem `VHG_Quy::chot()`. Không có nó thì mỗi lần đẩy lại là một lần chốt mới: chỉ số
 *    nhảy hai lần, tiền trên tay cộng đôi, và người thu bỗng nợ gấp đôi số họ đang cầm.
 *
 * ════════════════════════════════════════════════════════════════════════════════════════════
 * ⚠️ MỘT TỆP JSON, KHÔNG PHẢI CƠ SỞ DỮ LIỆU.
 *    Hàng đợi này dài nhất là vài chục dòng, sống nhiều nhất vài giờ. Room/SQLite kéo theo trình
 *    sinh mã, phiên bản lược đồ và phép chuyển đổi — ba thứ hỏng được, cho một bài toán mà một
 *    tệp ghi đè cả tệp là đủ. Ghi qua tệp tạm rồi đổi tên: mất điện giữa chừng thì tệp cũ còn
 *    nguyên, không ra một tệp cụt đọc không nổi.
 */
class HangDoi(ctx: Context) {

    private val tep = File(ctx.filesDir, "hang_doi.json")

    @Synchronized
    fun doc(): List<Lenh> {
        if (!tep.exists()) return emptyList()
        return try {
            val m = JSONArray(tep.readText())
            (0 until m.length()).mapNotNull { Lenh.tu(m.optJSONObject(it)) }
        } catch (e: Exception) {
            /* Tệp hỏng thì trả rỗng chứ KHÔNG xoá: một lượt chốt ca không đẩy được vẫn còn hơn
               một lượt bị chính app xoá đi. Người sửa máy đọc được tệp đó ra. */
            emptyList()
        }
    }

    @Synchronized
    fun them(viec: String, than: JSONObject): Lenh {
        val l = Lenh(UUID.randomUUID().toString(), viec, than.toString(), System.currentTimeMillis(), 0, "")
        ghi(doc() + l)
        return l
    }

    @Synchronized
    fun xoa(id: String) {
        ghi(doc().filterNot { it.id == id })
    }

    /** Đánh dấu một lượt vừa đẩy hỏng — đếm số lần và giữ lại câu lỗi cuối cùng. */
    @Synchronized
    fun ghiHong(id: String, loi: String) {
        ghi(doc().map { if (it.id == id) it.copy(soLanHong = it.soLanHong + 1, loiCuoi = loi) else it })
    }

    @Synchronized
    fun soLuong(): Int = doc().size

    private fun ghi(ds: List<Lenh>) {
        val m = JSONArray()
        ds.forEach { m.put(it.raJson()) }
        val tam = File(tep.parentFile, tep.name + ".tam")
        tam.writeText(m.toString())
        /* Đổi tên là thao tác NGUYÊN TỬ trên cùng một phân vùng: hoặc tệp cũ, hoặc tệp mới, không
           bao giờ có tệp viết dở. Ghi thẳng đè lên tệp thật mà mất điện là mất sạch hàng đợi. */
        if (!tam.renameTo(tep)) {
            tep.writeText(m.toString())
            tam.delete()
        }
    }

    data class Lenh(
        val id: String,
        val viec: String,
        val than: String,
        val luc: Long,
        val soLanHong: Int,
        val loiCuoi: String
    ) {
        fun thanJson(): JSONObject = try { JSONObject(than) } catch (e: Exception) { JSONObject() }

        fun raJson(): JSONObject = JSONObject().apply {
            put("id", id); put("viec", viec); put("than", than)
            put("luc", luc); put("so_lan_hong", soLanHong); put("loi_cuoi", loiCuoi)
        }

        /** Mô tả ngắn để hiện trong danh sách chờ — người ta cần biết lượt nào đang kẹt. */
        fun moTa(): String {
            val t = thanJson()
            return when (viec) {
                "chot_luu" -> "Chốt ghế ${t.optString("ma_may")} · " +
                    "${dinhDang(t.optInt("tien_dem"))}đ"
                "nop_tao" -> "Nộp tiền về quầy"
                else -> viec
            }
        }

        companion object {
            fun tu(o: JSONObject?): Lenh? {
                if (o == null) return null
                val id = o.optString("id")
                val viec = o.optString("viec")
                if (id.isEmpty() || viec.isEmpty()) return null
                return Lenh(id, viec, o.optString("than", "{}"), o.optLong("luc"),
                    o.optInt("so_lan_hong"), o.optString("loi_cuoi"))
            }

            fun dinhDang(v: Int): String =
                v.toString().reversed().chunked(3).joinToString(".").reversed()
        }
    }
}
