package vn.khh.ghe.man

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import kotlinx.coroutines.launch
import vn.khh.ghe.CHU
import vn.khh.ghe.DO
import vn.khh.ghe.Kho
import vn.khh.ghe.MO
import vn.khh.ghe.VANG
import vn.khh.ghe.XANH

/**
 * MÀN CHÍNH — TIỀN ĐANG CẦM, BÁO CÁO CA, VÀ NÚT QUÉT.
 *
 * 🔴 NÚT QUÉT ĐỨNG TRÊN CÙNG VÀ TO NHẤT. Đó là việc người thu mở app ra để làm; mọi con số khác
 *    là thứ họ liếc qua. Đặt nút chính dưới ba bảng số liệu là bắt họ cuộn mỗi lần tới một ghế.
 */
@Composable
fun ManQuy() {
    val pv = rememberCoroutineScope()
    var ghiChu by remember { mutableStateOf("") }
    var hoiNop by remember { mutableStateOf(false) }

    LaunchedEffect(Unit) { Kho.taiQuy() }

    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(vertical = 10.dp)) {

        Row(
            Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 6.dp),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Column(Modifier.weight(1f)) {
                Text(Kho.luu.ten.ifEmpty { "—" }, color = CHU, fontSize = 17.sp,
                    fontWeight = FontWeight.Bold)
                Text(Kho.luu.vaiTro, color = MO, fontSize = 12.sp)
            }
            TextButton(onClick = { Kho.raKhoi() }) { Text("Thoát", color = MO) }
        }

        /* ---- 1. QUÉT QR ---- */
        The {
            TieuDe("Chốt ca — quét QR trên ghế")
            Mut("Mở ngăn ghế, đọc chỉ số trên màn máy đếm, đếm tiền — rồi quét mã QR dán trên "
                + "chính cái ghế đó.")
            Cach(12)
            Button(
                onClick = { Kho.xoaBao(); Kho.manDangMo = Kho.Man.QUET },
                enabled = !Kho.dangBan,
                modifier = Modifier.fillMaxWidth().padding(vertical = 4.dp)
            ) { Text("📷  Quét mã QR trên ghế", fontSize = 18.sp, fontWeight = FontWeight.Bold) }
            Cach(4)
            GoTayMaGhe(pv)
        }

        /* ---- 2. HÀNG ĐỢI CHƯA GỬI ĐƯỢC — đứng TRƯỚC mọi con số tiền ---- */
        if (Kho.demHangDoi > 0) {
            Canh(
                "⏳ Còn ${Kho.demHangDoi} lượt chốt đã lưu trong máy mà CHƯA gửi được lên máy chủ.\n"
                    + "Tiền vẫn tính là anh/chị đang cầm. App tự gửi khi có mạng — đừng chốt lại "
                    + "những ghế đó.", VANG
            )
            The {
                Kho.dsHangDoi().forEach { l ->
                    Hang(l.moTa(), if (l.soLanHong > 0) "hỏng ${l.soLanHong} lần" else "chờ gửi",
                        if (l.soLanHong > 0) DO else MO)
                    if (l.loiCuoi.isNotBlank()) Mut(l.loiCuoi, DO)
                }
                Cach(10)
                OutlinedButton(
                    onClick = { pv.launch { Kho.dayNgay() } },
                    enabled = !Kho.dangBan, modifier = Modifier.fillMaxWidth()
                ) { Text("Gửi ngay") }
            }
        }

        /* ---- 3. TÔI ĐANG CẦM ---- */
        The {
            TieuDe("Tôi đang cầm")
            if (Kho.tienDangCam > 0) {
                Hang("Tổng phải nộp", tien(Kho.tienDangCam), VANG, dam = true)
                Hang("Lấy từ ngăn ghế", tien(Kho.tuNganGhe))
                Hang("Khách trả tại quầy", tien(Kho.tuQuay))
                Cach(12)
                OutlinedTextField(
                    value = ghiChu, onValueChange = { ghiChu = it },
                    label = { Text("Ghi chú (không bắt buộc)") },
                    singleLine = true, modifier = Modifier.fillMaxWidth()
                )
                Cach(10)
                if (!hoiNop) {
                    Button(
                        onClick = { hoiNop = true }, enabled = !Kho.dangBan,
                        modifier = Modifier.fillMaxWidth()
                    ) { Text("Nộp về quầy", fontSize = 16.sp, fontWeight = FontWeight.Bold) }
                    /* ⚠️ Nói rõ nộp là nộp HẾT. Nộp một phần thì con số "đang cầm" thành thứ
                       người nộp tự chọn, và cái sổ này thôi không kiểm được gì nữa. */
                    Mut("Bấm Nộp là nộp TOÀN BỘ ${tien(Kho.tienDangCam)} đang cầm. Quản lý đếm "
                        + "lại rồi xác nhận — con số hai bên đều được giữ trong sổ.")
                } else {
                    Text("Nộp toàn bộ ${tien(Kho.tienDangCam)} về quầy?", color = CHU, fontSize = 15.sp)
                    Cach(8)
                    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        OutlinedButton(onClick = { hoiNop = false }, modifier = Modifier.weight(1f)) {
                            Text("Chưa")
                        }
                        Button(
                            onClick = {
                                hoiNop = false
                                pv.launch { if (Kho.nop(ghiChu)) ghiChu = "" }
                            },
                            enabled = !Kho.dangBan, modifier = Modifier.weight(1f)
                        ) { Text("Nộp") }
                    }
                }
            } else {
                Mut("Không cầm đồng nào chưa nộp.")
            }
        }

        /* ---- 4. BÁO CÁO CA (những lượt vừa chốt trong phiên này) ---- */
        if (Kho.caDaChot.isNotEmpty()) {
            The {
                TieuDe("Ca này vừa chốt")
                Kho.caDaChot.forEach { c ->
                    Hang("${c.maMay}  ${c.chiSoTruoc} → ${c.chiSo}", tien(c.tienDem),
                        if (c.lechDem != 0) DO else CHU)
                    if (c.lechDem != 0) {
                        Mut(if (c.lechDem < 0) "Ngăn thiếu ${tien(-c.lechDem)} so với máy đếm."
                            else "Ngăn thừa ${tien(c.lechDem)} so với máy đếm.", DO)
                    }
                    if (c.lechMay > 0) {
                        Mut("Sổ thiếu ${tien(c.lechMay)} so với máy đếm — ghế nuốt tiền mà không "
                            + "báo về được. Tiền vẫn trong ngăn; báo quản lý, đừng tự bù.", VANG)
                    }
                    if (c.choDay) Mut("⏳ chưa gửi được lên máy chủ", VANG)
                }
            }
        }

        Canh(Kho.loi)
        Canh(Kho.bao, XANH)
        Cach(20)
    }
}

/** Đường gõ tay — luôn phải có, cho lúc tem bong hoặc máy không mở được camera. */
@Composable
private fun GoTayMaGhe(pv: kotlinx.coroutines.CoroutineScope) {
    var ma by remember { mutableStateOf("") }
    Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
        OutlinedTextField(
            value = ma, onValueChange = { ma = it.uppercase().filter { c -> c.isLetterOrDigit() } },
            label = { Text("hoặc gõ mã ghế") },
            singleLine = true, modifier = Modifier.weight(1f)
        )
        Cach()
        OutlinedButton(
            onClick = { pv.launch { Kho.moChot(ma) } },
            enabled = !Kho.dangBan && ma.isNotBlank(),
            colors = ButtonDefaults.outlinedButtonColors(contentColor = VANG)
        ) { Text("Chốt") }
    }
}
