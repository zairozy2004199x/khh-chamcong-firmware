package vn.khh.ghe.man

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Button
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import kotlinx.coroutines.launch
import vn.khh.ghe.CHU
import vn.khh.ghe.DO
import vn.khh.ghe.Kho
import vn.khh.ghe.VANG

/**
 * MÀN CHỐT CA — HAI Ô SỐ, VÀ Ô CHỈ SỐ ĐỨNG TRƯỚC.
 *
 * 🔴 THỨ TỰ TRÊN MÀN LÀ THỨ TỰ TAY LÀM. Chỉ số đọc trên màn máy đếm phải nhìn TRƯỚC khi thò tay
 *    vào ngăn: mở ngăn ra rồi, đếm xong rồi, thì không ai quay lại đọc màn nữa — và cũng không
 *    đọc lại được, vì ghế vẫn chạy và chỉ số vẫn đi tiếp.
 *
 * 🔴 GÕ CHỈ SỐ TỚI ĐÂU, HIỆN NGAY "MÁY ĐẾM NÓI ĐÃ NUỐT BAO NHIÊU". Đó là thứ cho người đứng đó
 *    biết mình vừa gõ nhầm một chữ số — TRƯỚC khi bấm, chứ không phải sau khi đã ghi vào sổ.
 */
@Composable
fun ManChot() {
    /* ⚠️ CHỈ THOÁT RA, KHÔNG ĐỔI TRẠNG THÁI Ở ĐÂY. Gán `manDangMo` giữa lúc Compose đang dựng
       màn là bắt nó dựng lại, rồi lại gán, rồi lại dựng — vòng không dứt và app treo. Ai đưa
       màn này lên thì người đó đã đặt `gheDangChot`; rỗng nghĩa là vừa chốt xong và màn đang
       được gỡ đi. */
    val g = Kho.gheDangChot ?: return
    val pv = rememberCoroutineScope()
    var chiSo by remember { mutableStateOf("") }
    var tienDem by remember { mutableStateOf("") }
    var ghiChu by remember { mutableStateOf("") }

    val cs = chiSo.toIntOrNull() ?: 0
    val duKien = if (g.lanDau) 0 else maxOf(0, cs - g.chiSoTruoc) * g.donVi
    val dem = tienDem.toIntOrNull() ?: 0
    val lech = if (g.lanDau || cs <= 0) 0 else dem - duKien

    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(vertical = 10.dp)) {

        The {
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                Text("Chốt ca — ${g.maMay}", color = VANG, fontSize = 19.sp,
                    fontWeight = FontWeight.ExtraBold)
            }
            Text(g.coSo.ifEmpty { "(chưa gán cơ sở)" } + if (g.song) "" else "  ·  ⚠️ ghế đang mất kết nối",
                color = CHU, fontSize = 13.sp, modifier = Modifier.padding(top = 2.dp, bottom = 10.dp))

            if (g.lanDau) {
                Canh("Ghế này CHƯA CHỐT LẦN NÀO. Lần này chỉ ghi lại chỉ số làm mốc; từ kỳ sau "
                    + "mới trừ ra được số tiền máy đếm đã nuốt.", VANG)
            } else {
                Hang("Chỉ số lần chốt trước", g.chiSoTruoc.toString())
                if (g.chotTruocLuc.isNotBlank()) {
                    Mut(g.chotTruocLuc.take(16) + (if (g.chotTruocAi.isNotBlank()) " · ${g.chotTruocAi}" else ""))
                }
            }
            Hang("Sổ ghi nhận từ lần chốt trước", tien(g.theoHeThong))
        }

        /* ---- Ô ① CHỈ SỐ ---- */
        The {
            TieuDe("① Chỉ số trên màn máy đếm tiền")
            OutlinedTextField(
                value = chiSo,
                onValueChange = { chiSo = it.filter { c -> c.isDigit() }.take(12) },
                placeholder = { Text("0") },
                singleLine = true, modifier = Modifier.fillMaxWidth(),
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                textStyle = androidx.compose.ui.text.TextStyle(
                    fontSize = 26.sp, fontWeight = FontWeight.Bold, color = CHU)
            )
            if (duKien > 0) {
                Cach(8)
                Hang("→ Máy đếm nói đã nuốt", tien(duKien), VANG, dam = true)
            }
            Mut("Mỗi 1 đơn vị trên màn đếm = ${tien(g.donVi)}. Đọc con số này TRƯỚC khi mở ngăn.")
        }

        /* ---- Ô ② TIỀN ĐẾM ĐƯỢC ---- */
        The {
            TieuDe("② Tiền mặt đếm được trong ngăn")
            OutlinedTextField(
                value = tienDem,
                onValueChange = { tienDem = it.filter { c -> c.isDigit() }.take(12) },
                placeholder = { Text("0") },
                singleLine = true, modifier = Modifier.fillMaxWidth(),
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                textStyle = androidx.compose.ui.text.TextStyle(
                    fontSize = 26.sp, fontWeight = FontWeight.Bold, color = CHU)
            )
            if (cs > 0 && dem > 0 && lech != 0) {
                Cach(8)
                Hang(if (lech < 0) "Ngăn THIẾU so với máy đếm" else "Ngăn THỪA so với máy đếm",
                    tien(kotlin.math.abs(lech)), DO, dam = true)
                Mut("Đếm lại một lần nữa cho chắc. Vẫn lệch thì cứ ghi đúng số đếm được — sổ giữ "
                    + "cả hai con số, và đó mới là thứ đối chiếu được.", DO)
            }
            Cach(10)
            OutlinedTextField(
                value = ghiChu, onValueChange = { ghiChu = it },
                label = { Text("Ghi chú — bắt buộc nếu vừa thay cục nhận tiền") },
                singleLine = true, modifier = Modifier.fillMaxWidth()
            )
        }

        Canh("Nút này GHI SỔ CHỐT CA: nó không mở ngăn tiền, và KHÔNG cộng doanh thu — tiền trong "
            + "ngăn đã vào sổ từ lúc ghế nuốt. Số tiền đếm được sẽ tính vào phần anh/chị đang cầm "
            + "cho tới khi nộp về quầy.", VANG)

        Canh(Kho.loi)

        Row(
            Modifier.fillMaxWidth().padding(horizontal = 14.dp, vertical = 10.dp),
            horizontalArrangement = Arrangement.spacedBy(10.dp)
        ) {
            OutlinedButton(
                onClick = { Kho.gheDangChot = null; Kho.xoaBao(); Kho.manDangMo = Kho.Man.QUY },
                modifier = Modifier.weight(1f)
            ) { Text("Thoát") }
            Button(
                onClick = { pv.launch { Kho.chot(cs, dem, ghiChu) } },
                enabled = !Kho.dangBan && cs > 0,
                modifier = Modifier.weight(2f)
            ) { Text(if (Kho.dangBan) "Đang ghi…" else "Chốt ca", fontSize = 17.sp,
                fontWeight = FontWeight.Bold) }
        }
        Cach(20)
    }
}
