package vn.khh.ghe.kho

import android.content.Context
import androidx.work.Constraints
import androidx.work.CoroutineWorker
import androidx.work.ExistingWorkPolicy
import androidx.work.NetworkType
import androidx.work.OneTimeWorkRequestBuilder
import androidx.work.WorkManager
import androidx.work.WorkerParameters
import vn.khh.ghe.Luu
import vn.khh.ghe.mang.Api
import vn.khh.ghe.mang.MangHong

/**
 * ĐẨY HÀNG ĐỢI LÊN MÁY CHỦ KHI CÓ MẠNG TRỞ LẠI.
 *
 * 🔴 CHẠY BẰNG WorkManager, KHÔNG BẰNG MỘT VÒNG LẶP TRONG APP.
 *    Nhân viên chốt xong là bỏ điện thoại vào túi và đi tiếp. App bị hệ điều hành dừng, và một
 *    vòng lặp trong app chết theo. WorkManager thì hệ thống tự gọi dậy khi có mạng — kể cả sau
 *    khi khởi động lại máy.
 *
 * 🔴 ĐẨY TUẦN TỰ, VÀ DỪNG LẠI Ở LƯỢT ĐẦU TIÊN HỎNG VÌ MẠNG.
 *    Các lượt chốt của cùng một ghế nối đuôi nhau: lượt sau lấy chỉ số của lượt trước làm mốc.
 *    Đẩy song song hoặc bỏ qua lượt kẹt để đẩy lượt sau là hai quãng chồng lên nhau, và số tiền
 *    đối chiếu sai mà không ai thấy.
 *
 * ⚠️ Máy chủ trả `ok=false` là chuyện KHÁC HẲN mất mạng: gửi lại bao nhiêu lần cũng vậy. Bỏ lượt
 *    đó ra khỏi hàng đợi, giữ lại câu lỗi để hiện cho người dùng — chứ không đẩy mãi.
 */
class DayHangDoi(ctx: Context, params: WorkerParameters) : CoroutineWorker(ctx, params) {

    override suspend fun doWork(): Result =
        if (dayMotLuot(applicationContext)) Result.success() else Result.retry()

    companion object {
        private const val TEN = "day_hang_doi"

        /**
         * Đẩy hết hàng đợi. Trả `true` nếu không còn gì kẹt vì mạng.
         *
         * ⚠️ TÁCH RA KHỎI `doWork()` để nút "Gửi ngay" trên màn Quỹ dùng CHUNG. Chép ra hai bản
         *    là hai bộ luật cho cùng một việc đụng tới tiền — rồi một hôm sửa chỗ này quên chỗ
         *    kia, và nút bấm tay ghi hai lần trong khi bộ tự động thì không.
         */
        suspend fun dayMotLuot(ctx: Context): Boolean {
            val luu = Luu(ctx)
            if (!luu.daVao()) return true

            val hd = HangDoi(ctx)
            val api = Api(luu.diaChi)
            for (l in hd.doc()) {
                try {
                    val tl = api.goi(l.viec, l.thanJson(), luu.token)
                    if (tl.optBoolean("ok", false)) {
                        hd.xoa(l.id)
                        continue
                    }
                    /* Hết phiên: KHÔNG bỏ lượt đi. Người dùng đăng nhập lại là đẩy được ngay, mà
                       bỏ đi là mất luôn một lượt chốt ca có thật. Dừng cả vòng — mọi lượt sau
                       cũng sẽ vấp đúng chỗ này. */
                    if (tl.optString("ma") == "het_phien") {
                        hd.ghiHong(l.id, "Phiên đã hết — đăng nhập lại rồi lượt này tự gửi đi.")
                        return false
                    }
                    /* Máy chủ từ chối CÓ LÝ DO (ghế khác cơ sở, chỉ số chạy lùi…). Gửi lại không
                       đổi được gì, nên bỏ khỏi hàng đợi — nhưng ghi lại câu lỗi trước đã, để còn
                       hiện cho người dùng biết lượt đó đã mất vì sao. */
                    hd.ghiHong(l.id, tl.optString("error", "Máy chủ từ chối lượt này."))
                    hd.xoa(l.id)
                } catch (e: MangHong) {
                    hd.ghiHong(l.id, e.loi)
                    return false
                }
            }
            return true
        }

        /**
         * Hẹn đẩy. Gọi được nhiều lần — `KEEP` giữ lượt đang chờ thay vì xếp thêm lượt mới.
         *
         * ⚠️ `REPLACE` thì mỗi lần bấm chốt lại huỷ lượt đang chờ và hẹn lại từ đầu, nên một
         *    người chốt liên tục năm ghế sẽ đẩy hàng đợi TRỄ hơn là chốt một ghế rồi ngồi yên.
         */
        fun hen(ctx: Context) {
            val yc = OneTimeWorkRequestBuilder<DayHangDoi>()
                .setConstraints(
                    Constraints.Builder().setRequiredNetworkType(NetworkType.CONNECTED).build()
                )
                .build()
            WorkManager.getInstance(ctx).enqueueUniqueWork(TEN, ExistingWorkPolicy.KEEP, yc)
        }
    }
}
