package vn.khh.ghe

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.darkColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import vn.khh.ghe.kho.DayHangDoi
import vn.khh.ghe.man.ManChot
import vn.khh.ghe.man.ManDangNhap
import vn.khh.ghe.man.ManQuet
import vn.khh.ghe.man.ManQuy

/** Bảng màu: cùng tông với trang /ghe — nhân viên chuyển qua lại giữa hai thứ trong một ca. */
val NEN = Color(0xFF0B0D16)
val THE = Color(0xFF141827)
val VANG = Color(0xFFF0B429)
val CHU = Color(0xFFE8DCC4)
val MO = Color(0xFF9AA0C2)
val DO = Color(0xFFFF8087)
val XANH = Color(0xFF8FF0B0)

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()
        setContent {
            MaterialTheme(
                colorScheme = darkColorScheme(
                    primary = VANG, onPrimary = Color(0xFF221A00),
                    background = NEN, surface = THE, onSurface = CHU, error = DO
                )
            ) {
                Surface(modifier = Modifier.fillMaxSize().background(NEN), color = NEN) {
                    GocApp()
                }
            }
        }
    }

    override fun onResume() {
        super.onResume()
        /* Mở app lên là thử đẩy nốt hàng đợi. Người thu hay chốt ở chỗ sóng yếu rồi ra ngoài —
           lúc ra ngoài chính là lúc đẩy được, và cũng là lúc họ mở app ra xem. */
        DayHangDoi.hen(this)
    }
}

/* ⚠️ KHÔNG đặt tên hàm này là `Ung`. Lớp Application trong CÙNG GÓI cũng tên `Ung`, và
   `Ung()` khi đó là lời gọi HÀM DỰNG của lớp đó chứ không phải hàm @Composable — app dựng ra
   một Application thứ hai rồi hiện màn trắng. Kotlin không kêu lên: cả hai đều hợp lệ. */
@Composable
private fun GocApp() {
    LaunchedEffect(Unit) { Kho.taiQuy() }
    when (Kho.manDangMo) {
        Kho.Man.DANG_NHAP -> ManDangNhap()
        Kho.Man.QUET -> ManQuet()
        Kho.Man.CHOT -> ManChot()
        Kho.Man.QUY -> ManQuy()
    }
}
