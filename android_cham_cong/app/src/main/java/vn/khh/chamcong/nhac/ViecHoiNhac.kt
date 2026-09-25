package vn.khh.chamcong.nhac

import android.content.Context
import android.webkit.CookieManager
import androidx.work.Constraints
import androidx.work.ExistingPeriodicWorkPolicy
import androidx.work.NetworkType
import androidx.work.PeriodicWorkRequestBuilder
import androidx.work.WorkManager
import androidx.work.Worker
import androidx.work.WorkerParameters
import org.json.JSONObject
import vn.khh.chamcong.Luu
import java.net.HttpURLConnection
import java.net.URL
import java.util.concurrent.TimeUnit

/**
 * HỎI MÁY CHỦ MỖI 15 PHÚT: "tôi có lời nhắc nào không, chuông có gì mới không".
 *
 * =================================================================================================
 * 🔴 DÙNG LẠI ĐÚNG PHIÊN CỦA WEBVIEW, KHÔNG CẤT MỘT BẢN SAO NÀO
 * =================================================================================================
 * Cách dễ nhất là lúc đăng nhập thì chép thẻ phiên ra `SharedPreferences` cho tiện gọi. Nhưng
 * như thế là dựng BẢN SAO THỨ HAI của trạng thái đăng nhập — rồi tới lúc máy chủ huỷ phiên, hoặc
 * người ta đăng xuất trong trang, bản sao ấy vẫn nằm đó và vẫn hỏi được. Trên máy DÙNG CHUNG ở cơ
 * sở thì đó là điện thoại vẫn nhắc tên người ca trước.
 *
 * Nên lấy cookie thẳng từ `CookieManager` — chính cái kho mà WebView đang dùng. Đăng xuất trong
 * trang là cookie mất, là lượt hỏi sau trượt, là hết nhắc. Một nguồn sự thật, tự đồng bộ.
 *
 * ⚠️ 15 PHÚT LÀ NGƯỠNG SÀN CỦA `PeriodicWorkRequest`, không phải con số chọn cho đẹp. Khai nhỏ
 *    hơn thì Android lặng lẽ nâng lên 15 — một lời hứa "5 phút" trong mã mà thực tế 15 còn tệ
 *    hơn viết thẳng 15, vì người đọc sau tin vào con số đó.
 *
 * ⚠️ HỎNG MẠNG THÌ `Result.success()`, KHÔNG PHẢI `retry()`. Đây là việc chạy LẶP mỗi 15 phút:
 *    lượt sau tự tới. Trả `retry()` là xếp thêm một lượt thử chồng lên lịch lặp, và mất mạng cả
 *    buổi sáng thì chúng dồn thành một chùm cùng bắn một lúc khi có mạng lại.
 */
class ViecHoiNhac(ct: Context, ts: WorkerParameters) : Worker(ct, ts) {

    override fun doWork(): Result {
        val ct = applicationContext
        val goc = Luu.mayChu(ct)
        if (goc.isEmpty()) return Result.success()

        val tram = Luu.urlTram(ct)
        val banh = CookieManager.getInstance().getCookie(tram)
        /* Chưa đăng nhập -> không hỏi. Hỏi không kèm phiên thì máy chủ chối, và lượt chối ấy
           chẳng nói lên điều gì ngoài việc mình vừa gọi thừa một lượt. */
        if (banh.isNullOrBlank()) return Result.success()

        val tl = doc("$tram?viec=nhac", banh) ?: return Result.success()

        try {
            val j = JSONObject(tl)
            if (!j.optBoolean("ok", false)) return Result.success()

            val daHien = Luu.daHienNhac(ct).toMutableSet()
            val ds = j.optJSONArray("nhac")
            val conSong = HashSet<String>()
            if (ds != null) {
                for (i in 0 until ds.length()) {
                    val x = ds.optJSONObject(i) ?: continue
                    val khoa = x.optString("khoa")
                    if (khoa.isEmpty()) continue
                    conSong.add(khoa)
                    /* 🔴 ĐÃ HIỆN RỒI THÌ THÔI. Máy chủ trả lời theo TRẠNG THÁI ("người này đang
                       chưa chấm ra"), nên nó trả về y hệt ở mọi lượt hỏi. Không nhớ thì cứ 15
                       phút một lần rung, và người ta tắt thông báo của app — mất luôn cả mấy
                       tin cần thiết. */
                    if (daHien.contains(khoa)) continue
                    Nhac.hien(ct, khoa.hashCode(), x.optString("tieuDe"), x.optString("chu"))
                    daHien.add(khoa)
                }
            }
            /* Dọn khoá của những lời nhắc đã hết (người ta chấm ra rồi). Không dọn thì sổ phình
               mãi, và quan trọng hơn: hôm sau cùng một cơ sở lại ra cùng một khoá nếu ngày
               không nằm trong khoá — nên khoá CÓ ngày, và dọn ở đây là dọn cho gọn. */
            Luu.datDaHienNhac(ct, daHien.intersect(conSong))

            val dem = j.optString("demChuong", "")
            if (dem.isNotEmpty() && dem != Luu.demChuongCu(ct)) {
                Nhac.hienChuong(ct, dem)
            }
            Luu.datDemChuongCu(ct, dem)
        } catch (e: Exception) {
            /* Trả lời không phải JSON — thường là trang đăng nhập của WordPress, hoặc một trang
               chặn của nhà mạng. Không có gì để hiện, và cũng không có gì để thử lại. */
            return Result.success()
        }
        return Result.success()
    }

    private fun doc(diaChi: String, banh: String): String? = try {
        val k = URL(diaChi).openConnection() as HttpURLConnection
        k.requestMethod = "GET"
        k.connectTimeout = 10000
        k.readTimeout = 10000
        k.setRequestProperty("Cookie", banh)
        k.setRequestProperty("Accept", "application/json")
        if (k.responseCode == 200) k.inputStream.bufferedReader().use { it.readText() } else null
    } catch (e: Exception) {
        null
    }

    companion object {
        private const val TEN = "khh_hoi_nhac"

        /**
         * Xếp lịch. Gọi mỗi lần mở app — `KEEP` nên nó không dựng lại lịch đã có.
         *
         * ⚠️ `KEEP` chứ không `REPLACE`: `REPLACE` đặt lại đồng hồ từ đầu mỗi lần mở app, nên
         *    người mở app mười lần một ngày thì lượt hỏi KHÔNG BAO GIỜ tới — nó bị đẩy lùi mãi.
         */
        fun xepLich(ct: Context) {
            val rb = Constraints.Builder()
                .setRequiredNetworkType(NetworkType.CONNECTED)
                .build()
            val v = PeriodicWorkRequestBuilder<ViecHoiNhac>(15, TimeUnit.MINUTES)
                .setConstraints(rb)
                .build()
            WorkManager.getInstance(ct)
                .enqueueUniquePeriodicWork(TEN, ExistingPeriodicWorkPolicy.KEEP, v)
        }
    }
}
