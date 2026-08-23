package vn.khh.ghe.man

import android.Manifest
import android.content.pm.PackageManager
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.camera.core.CameraSelector
import androidx.camera.core.ExperimentalGetImage
import androidx.camera.core.ImageAnalysis
import androidx.camera.core.Preview
import androidx.camera.lifecycle.ProcessCameraProvider
import androidx.camera.view.PreviewView
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Button
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalLifecycleOwner
import androidx.compose.ui.unit.dp
import androidx.compose.ui.viewinterop.AndroidView
import androidx.core.content.ContextCompat
import com.google.mlkit.vision.barcode.BarcodeScanning
import com.google.mlkit.vision.barcode.common.Barcode
import com.google.mlkit.vision.common.InputImage
import kotlinx.coroutines.launch
import vn.khh.ghe.Kho
import vn.khh.ghe.VANG
import java.util.concurrent.Executors

/**
 * QUÉT MÃ QR DÁN TRÊN GHẾ.
 *
 * 🔴 DÙNG KÉ ĐÚNG CÁI TEM KHÁCH VẪN QUÉT. Tem mang ĐỊA CHỈ trang khách (`https://…/mua-ma/AMTP01`)
 *    chứ không mang mã trần, nên phải bóc mã ghế ra khỏi đường dẫn. Dán thêm một tem thứ hai chỉ
 *    cho nhân viên là thêm một thứ bong ra được, và thêm một thứ dán nhầm ghế được.
 *
 * 🔴 MÔ HÌNH ĐỌC MÃ GÓI SẴN TRONG APK (`barcode-scanning`, không phải bản `-common`). Bản kia
 *    tải mô hình về ở lần chạy đầu — mà lần đầu chính là lúc nhân viên đứng trong kho sóng yếu.
 *
 * ⚠️ QUÉT XONG LÀ NGỪNG NGAY. Không chốt lại thì một khung hình sau lại đọc ra cùng mã đó và
 *    mở màn chốt ca lần nữa, đè lên màn đang mở.
 */
@androidx.annotation.OptIn(ExperimentalGetImage::class)
@Composable
fun ManQuet() {
    val ctx = LocalContext.current
    val chuSoHuu = LocalLifecycleOwner.current
    val pv = rememberCoroutineScope()

    var coQuyen by remember {
        mutableStateOf(
            ContextCompat.checkSelfPermission(ctx, Manifest.permission.CAMERA)
                == PackageManager.PERMISSION_GRANTED
        )
    }
    var daBat by remember { mutableStateOf(false) }
    var loiCam by remember { mutableStateOf("") }

    val xinQuyen = rememberLauncherForActivityResult(
        ActivityResultContracts.RequestPermission()
    ) { cho -> coQuyen = cho }

    LaunchedEffect(Unit) { if (!coQuyen) xinQuyen.launch(Manifest.permission.CAMERA) }

    val mayChay = remember { Executors.newSingleThreadExecutor() }
    DisposableEffect(Unit) { onDispose { mayChay.shutdown() } }

    Column(Modifier.fillMaxSize().padding(vertical = 10.dp)) {
        The {
            TieuDe("Quét mã QR trên ghế")
            Mut("Đưa camera vào tem QR dán trên chính cái ghế vừa mở ngăn.")
        }

        if (coQuyen) {
            Box(Modifier.fillMaxWidth().padding(horizontal = 14.dp).height(340.dp)) {
                AndroidView(
                    modifier = Modifier.fillMaxSize(),
                    factory = { c ->
                        val xem = PreviewView(c)
                        val tuongLai = ProcessCameraProvider.getInstance(c)
                        tuongLai.addListener({
                            try {
                                val nha = tuongLai.get()
                                val truoc = Preview.Builder().build()
                                    .also { it.setSurfaceProvider(xem.surfaceProvider) }
                                val doc = BarcodeScanning.getClient()
                                val phanTich = ImageAnalysis.Builder()
                                    /* Bỏ khung cũ: đọc mã không cần đủ khung hình, mà xếp hàng
                                       khung cũ lại thì màn hình trễ và máy nóng. */
                                    .setBackpressureStrategy(ImageAnalysis.STRATEGY_KEEP_ONLY_LATEST)
                                    .build()
                                phanTich.setAnalyzer(mayChay) { anh ->
                                    val m = anh.image
                                    if (m == null || daBat) { anh.close(); return@setAnalyzer }
                                    doc.process(
                                        InputImage.fromMediaImage(m, anh.imageInfo.rotationDegrees)
                                    ).addOnSuccessListener { ds ->
                                        val ma = ds.firstOrNull { it.format == Barcode.FORMAT_QR_CODE }
                                            ?.rawValue
                                        val ghe = maGheTuQR(ma)
                                        if (ghe.isNotEmpty() && !daBat) {
                                            daBat = true
                                            pv.launch { if (!Kho.moChot(ghe)) daBat = false }
                                        } else if (ma != null && ghe.isEmpty()) {
                                            loiCam = "Mã vừa quét không phải tem ghế."
                                        }
                                    }.addOnCompleteListener { anh.close() }
                                }
                                nha.unbindAll()
                                nha.bindToLifecycle(
                                    chuSoHuu, CameraSelector.DEFAULT_BACK_CAMERA, truoc, phanTich
                                )
                            } catch (e: Exception) {
                                loiCam = "Không mở được camera: ${e.message}. Gõ mã ghế bằng tay giúp em."
                            }
                        }, ContextCompat.getMainExecutor(c))
                        xem
                    }
                )
            }
        } else {
            Canh("Chưa được phép dùng camera. Cho phép trong cài đặt máy, hoặc quay lại rồi gõ "
                + "mã ghế bằng tay — luôn còn đường đó.", VANG)
        }

        Canh(loiCam)
        Canh(Kho.loi)

        Column(Modifier.fillMaxWidth().padding(14.dp)) {
            OutlinedButton(
                onClick = { Kho.xoaBao(); Kho.manDangMo = Kho.Man.QUY },
                modifier = Modifier.fillMaxWidth()
            ) { Text("Quay lại") }
        }
    }
}

/**
 * Bóc mã ghế ra khỏi thứ vừa quét được.
 *
 * Tem mang địa chỉ trang khách, ba dạng đều phải ra cùng một mã:
 *   `https://khmatrix.com/mua-ma/AMTP01`  ·  `…?ghe=AMTP01`  ·  và chính chuỗi `AMTP01`.
 */
fun maGheTuQR(txt: String?): String {
    var s = (txt ?: "").trim()
    if (s.isEmpty()) return ""
    Regex("[?&]ghe=([A-Za-z0-9]+)").find(s)?.let { return it.groupValues[1].uppercase() }
    s = s.substringBefore('?').substringBefore('#').trimEnd('/')
    val cuoi = s.substringAfterLast('/')
    return if (Regex("^[A-Za-z0-9]{1,20}$").matches(cuoi)) cuoi.uppercase() else ""
}
