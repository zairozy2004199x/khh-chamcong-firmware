package vn.khh.ghe.man

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import vn.khh.ghe.CHU
import vn.khh.ghe.DO
import vn.khh.ghe.MO
import vn.khh.ghe.THE
import vn.khh.ghe.VANG

/**
 * Mấy mảnh giao diện dùng chung.
 *
 * ⚠️ CỠ CHỮ TO HƠN MẶC ĐỊNH. Người dùng app này đứng trong kho, một tay giữ ngăn tiền, ánh sáng
 *    kém, và thường là người lớn tuổi hơn người viết app. Chữ 14sp đọc được ở bàn làm việc thì
 *    ở đó là đoán.
 */

/** Tiền kiểu Việt: 1.234.567đ. */
fun tien(v: Int): String {
    val s = kotlin.math.abs(v).toString().reversed().chunked(3).joinToString(".").reversed()
    return (if (v < 0) "-" else "") + s + "đ"
}

@Composable
fun The(modifier: Modifier = Modifier, noi: @Composable ColumnScope.() -> Unit) {
    Column(
        modifier = modifier
            .fillMaxWidth()
            .padding(horizontal = 14.dp, vertical = 6.dp)
            .background(THE, RoundedCornerShape(14.dp))
            .padding(16.dp),
        content = noi
    )
}

@Composable
fun TieuDe(chu: String) {
    Text(chu, color = VANG, fontSize = 17.sp, fontWeight = FontWeight.Bold,
        modifier = Modifier.padding(bottom = 8.dp))
}

@Composable
fun Hang(nhan: String, gt: String, mau: Color = CHU, dam: Boolean = false) {
    Row(
        Modifier.fillMaxWidth().padding(vertical = 5.dp),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically
    ) {
        Text(nhan, color = MO, fontSize = 14.sp, modifier = Modifier.weight(1f))
        Text(gt, color = mau, fontSize = if (dam) 20.sp else 16.sp,
            fontWeight = if (dam) FontWeight.ExtraBold else FontWeight.SemiBold)
    }
}

@Composable
fun Mut(chu: String, mau: Color = MO) {
    Text(chu, color = mau, fontSize = 13.sp, lineHeight = 19.sp,
        modifier = Modifier.padding(top = 6.dp))
}

/** Ô cảnh báo — chỉ hiện khi có chuyện, và nói rõ phải làm gì. */
@Composable
fun Canh(chu: String, mau: Color = DO) {
    if (chu.isBlank()) return
    Box(
        Modifier.fillMaxWidth().padding(horizontal = 14.dp, vertical = 6.dp)
            .background(mau.copy(alpha = 0.14f), RoundedCornerShape(10.dp))
            .padding(12.dp)
    ) { Text(chu, color = mau, fontSize = 14.sp, lineHeight = 20.sp) }
}

@Composable
fun Cach(cao: Int = 10) { Spacer(Modifier.height(cao.dp)) }
