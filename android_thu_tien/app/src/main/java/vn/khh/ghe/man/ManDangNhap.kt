package vn.khh.ghe.man

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Button
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import kotlinx.coroutines.launch
import vn.khh.ghe.CHU
import vn.khh.ghe.Kho
import vn.khh.ghe.VANG

/**
 * ĐĂNG NHẬP: địa chỉ máy chủ + PIN.
 *
 * 🔴 HỎI ĐỊA CHỈ MÁY CHỦ, KHÔNG NHÉT CỨNG. Cùng bộ mã này chạy cho nhiều nơi, và có cả máy chủ
 *    thử. Nhét cứng "khmatrix.com" là mỗi lần đổi phải dựng lại APK rồi mang đi cài từng máy.
 *
 * ⚠️ Chỉ hỏi MỘT LẦN — lần sau app nhớ. Bắt gõ lại địa chỉ mỗi ca là một ô để gõ sai, mà gõ sai
 *    thì câu báo lỗi chỉ là "không nối được máy chủ".
 */
@Composable
fun ManDangNhap() {
    val pv = rememberCoroutineScope()
    var diaChi by remember { mutableStateOf(Kho.luu.diaChi.ifEmpty { "khmatrix.com" }) }
    var pin by remember { mutableStateOf("") }

    Column(
        Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(top = 60.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Top
    ) {
        Text("K&H — Thu tiền", color = VANG, fontSize = 24.sp, fontWeight = FontWeight.ExtraBold)
        Text("Chốt ca ghế massage", color = CHU, fontSize = 14.sp,
            modifier = Modifier.padding(top = 4.dp, bottom = 18.dp))

        The {
            TieuDe("Đăng nhập")
            OutlinedTextField(
                value = diaChi, onValueChange = { diaChi = it },
                label = { Text("Địa chỉ máy chủ") },
                singleLine = true, modifier = Modifier.fillMaxWidth(),
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Uri)
            )
            Cach()
            OutlinedTextField(
                value = pin,
                /* Chỉ nhận chữ số, và cắt ở 8: bàn phím số trên vài máy vẫn gõ được dấu chấm,
                   và một dấu chấm lọt vào PIN là câu báo lỗi "PIN không đúng" cho một PIN đúng. */
                onValueChange = { pin = it.filter { c -> c.isDigit() }.take(8) },
                label = { Text("PIN (4–8 số)") },
                singleLine = true, modifier = Modifier.fillMaxWidth(),
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.NumberPassword)
            )
            Cach(16)
            Button(
                onClick = { pv.launch { Kho.dangNhap(diaChi, pin) } },
                enabled = !Kho.dangBan,
                modifier = Modifier.fillMaxWidth()
            ) { Text(if (Kho.dangBan) "Đang vào…" else "Vào", fontSize = 17.sp,
                fontWeight = FontWeight.Bold) }
            Mut("Dùng đúng PIN của trang /ghe. Chưa có PIN thì nhờ quản lý cấp ở tab Cấu hình.")
        }
        Canh(Kho.loi)
    }
}
