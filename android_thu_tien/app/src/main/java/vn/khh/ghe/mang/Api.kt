package vn.khh.ghe.mang

import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody
import org.json.JSONObject
import java.util.concurrent.TimeUnit

/**
 * NÓI CHUYỆN VỚI CỔNG /ghe.
 *
 * Cùng một cổng trang web đang dùng: `POST https://<máy chủ>/ghe?api=<việc>`, thân là JSON và
 * mang `token`. Không có cổng riêng cho app.
 *
 * 🔴 VÌ SAO KHÔNG DỰNG CỔNG RIÊNG CHO APP.
 *    Một cổng thứ hai là một bộ luật thứ hai cho cùng những việc đụng tới tiền — rồi một hôm
 *    sửa chốt phân quyền ở cổng web mà quên cổng app. Dùng chung thì mọi chốt (phân quyền theo
 *    vai trò, cơ sở lấy từ phiên, chống ghi hai lần bằng `ma_lan`) áp cho app y hệt, không phải
 *    viết lại dòng nào.
 */
class Api(private val diaChi: String) {

    private val may: OkHttpClient = OkHttpClient.Builder()
        /* Chờ lâu hơn mặc định: nhân viên đứng trong kho, cạnh ghế, sóng 4G một vạch.
           Nhưng KHÔNG chờ vô hạn — quá 25 giây thì thà bỏ vào hàng đợi rồi đẩy sau, còn hơn
           để người ta cầm điện thoại nhìn vòng xoay. */
        .connectTimeout(12, TimeUnit.SECONDS)
        .readTimeout(25, TimeUnit.SECONDS)
        .writeTimeout(25, TimeUnit.SECONDS)
        .retryOnConnectionFailure(true)
        .build()

    /**
     * Gọi một việc. Trả về JSON máy chủ trả lời.
     *
     * @throws MangHong khi KHÔNG chạm được tới máy chủ (mất mạng, quá hạn chờ, máy chủ trả mã
     *         lỗi HTTP). Phân biệt hẳn với "máy chủ trả lời ok=false": cái đầu thì gửi lại được,
     *         cái sau thì gửi lại bao nhiêu lần cũng vậy. Gộp hai thứ đó làm một là hàng đợi
     *         ngoại tuyến đẩy đi đẩy lại mãi một lượt mà máy chủ đã từ chối có lý do.
     */
    fun goi(viec: String, than: JSONObject, token: String): JSONObject {
        than.put("token", token)
        val yc = Request.Builder()
            .url("$diaChi/ghe?api=$viec")
            .post(than.toString().toRequestBody(KIEU_JSON))
            .header("Accept", "application/json")
            .build()
        try {
            may.newCall(yc).execute().use { tl ->
                val chu = tl.body?.string().orEmpty()
                if (!tl.isSuccessful) {
                    throw MangHong("Máy chủ trả lỗi ${tl.code}.")
                }
                if (chu.isBlank()) throw MangHong("Máy chủ trả lời rỗng.")
                return try {
                    JSONObject(chu)
                } catch (e: Exception) {
                    /* 🔴 KHÔNG PHẢI JSON = KHÔNG PHẢI MÁY CHỦ MÌNH ĐANG TRẢ LỜI.
                       Tường lửa hosting (Imunify360/ModSecurity) chèn một trang HTML chặn, hoặc
                       WiFi công cộng chèn trang đăng nhập. Cả hai đều trả HTTP 200 với thân là
                       HTML — nuốt im lặng là app báo "chốt xong" cho một lượt chưa tới đâu. */
                    throw MangHong("Máy chủ trả về thứ không đọc được — có thể đang bị tường lửa "
                        + "hosting chặn, hoặc WiFi đang bắt đăng nhập.")
                }
            }
        } catch (e: MangHong) {
            throw e
        } catch (e: Exception) {
            throw MangHong(e.message ?: "Không nối được máy chủ.")
        }
    }

    companion object {
        private val KIEU_JSON = "application/json; charset=utf-8".toMediaType()
    }
}

/** Không chạm được tới máy chủ — lượt này GỬI LẠI ĐƯỢC. */
class MangHong(val loi: String) : Exception(loi)
