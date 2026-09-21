package vn.khh.ghe

import android.app.Application

/**
 * Ứng dụng. Giữ hai thứ sống suốt vòng đời: nơi nhớ phiên, và hàng đợi ngoại tuyến.
 *
 * ⚠️ KHÔNG giữ trạng thái màn hình ở đây. Android dựng lại Activity mỗi lần xoay máy hay đổi cỡ
 *    chữ hệ thống; thứ gì cần sống qua đó thì để ở `Kho`, thứ gì chỉ của một màn thì để trong
 *    chính màn đó.
 */
class Ung : Application() {
    override fun onCreate() {
        super.onCreate()
        /* ⚠️ Một nơi dựng duy nhất. Dựng thêm một `Luu`/`HangDoi` ở đây là hai bản đọc cùng một
           tệp — và hai bản ghi đè lên nhau thì mất lượt chốt ca. `Kho.gan()` dựng đủ rồi. */
        Kho.gan(this)
    }
}
