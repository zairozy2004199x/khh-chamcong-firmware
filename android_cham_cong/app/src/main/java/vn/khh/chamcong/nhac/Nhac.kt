package vn.khh.chamcong.nhac

import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.os.Build
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat

/**
 * THÔNG BÁO CỦA ANDROID — thay cho Web Push, thứ KHÔNG chạy trong WebView.
 *
 * =================================================================================================
 * 🔴 LUẬT "KHI NÀO THÌ NHẮC" KHÔNG NẰM Ở ĐÂY. NÓ NẰM Ở MÁY CHỦ.
 * =================================================================================================
 * Cách dễ nhất là để app tự tính: có giờ vào, chưa có giờ ra, quá N giờ — ba dòng Kotlin, chạy
 * ngay. Nhưng đó là dựng BẢN THỨ HAI của một luật đã có. Ngày nào anh Thắng đổi ngưỡng từ 10 giờ
 * xuống 8, người mở bằng Chrome được nhắc lúc 8 giờ còn người cài app vẫn 10 — và không ai nghĩ
 * tới việc đi sửa cái app.
 *
 * Nên app chỉ hỏi `?viec=nhac` rồi hiện ra đúng những gì máy chủ trả về. Xem
 * `VHCC_Push::nhac_cua()`.
 */
object Nhac {

    const val KENH = "khh_cham_cong_nhac"

    /** Khoá thông báo. Một khoá = một lời nhắc; hiện lại cùng khoá thì nó thay chứ không chồng. */
    private const val ID_CHUONG = 1001

    fun taoKenh(ct: Context) {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) return
        val k = NotificationChannel(
            KENH, "Nhắc chấm công", NotificationManager.IMPORTANCE_DEFAULT
        )
        k.description = "Nhắc chấm công ra, và lệnh mới trong chuông."
        val nm = ct.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        nm.createNotificationChannel(k)
    }

    /**
     * Hiện một thông báo.
     *
     * ⚠️ `NotificationManagerCompat.notify` NÉM `SecurityException` khi người dùng chưa cho quyền
     *    thông báo (Android 13+). Ném từ trong một `Worker` thì WorkManager coi lượt chạy đó là
     *    hỏng và đặt lịch thử lại — mỗi 15 phút một lần hỏng, mãi mãi, cho một quyền mà người ta
     *    cố ý từ chối. Nuốt đúng ngoại lệ này, không nuốt thứ khác.
     */
    fun hien(ct: Context, id: Int, tieuDe: String, chu: String) {
        val mo = Intent(ct, Class.forName("vn.khh.chamcong.MainActivity")).apply {
            flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
        }
        val bam = PendingIntent.getActivity(
            ct, id, mo, PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )
        val tb = NotificationCompat.Builder(ct, KENH)
            .setSmallIcon(android.R.drawable.ic_popup_reminder)
            .setContentTitle(tieuDe)
            .setContentText(chu)
            .setStyle(NotificationCompat.BigTextStyle().bigText(chu))
            .setPriority(NotificationCompat.PRIORITY_DEFAULT)
            .setAutoCancel(true)
            .setContentIntent(bam)
            .build()
        try {
            NotificationManagerCompat.from(ct).notify(id, tb)
        } catch (e: SecurityException) {
            /* Chưa cho quyền thông báo. Không có gì để làm, và KHÔNG được để nó thành lỗi. */
        }
    }

    fun hienChuong(ct: Context, dem: String) {
        hien(ct, ID_CHUONG, "Có lệnh mới", "Bạn có $dem thông báo chưa đọc. Mở app để xem.")
    }
}
