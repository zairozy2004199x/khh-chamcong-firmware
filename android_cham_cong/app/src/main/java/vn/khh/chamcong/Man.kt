package vn.khh.chamcong

import android.content.Context
import android.graphics.Color
import android.text.InputType
import android.util.TypedValue
import android.view.Gravity
import android.view.ViewGroup
import android.widget.Button
import android.widget.EditText
import android.widget.LinearLayout
import android.widget.TextView

/**
 * HAI MÀN PHỤ, DỰNG BẰNG MÃ — không có tệp bố cục XML nào.
 *
 * Cả app chỉ có đúng hai màn không phải WebView: nhập địa chỉ máy chủ, và báo mất mạng. Dựng
 * chúng bằng mã thì cả phần giao diện nằm gọn trong một tệp đọc một lượt là hết; tách ra XML là
 * thêm hai tệp nữa phải mở song song mỗi lần sửa một dòng chữ.
 */
object Man {

    private const val NEN = 0xFF0B0D16.toInt()
    private const val CHINH = 0xFF2F6BFF.toInt()
    private const val CHU = 0xFFE8ECF8.toInt()
    private const val CHU_MO = 0xFF9AA4C0.toInt()

    private fun dp(ct: Context, v: Int) = (v * ct.resources.displayMetrics.density).toInt()

    private fun khung(ct: Context): LinearLayout {
        val l = LinearLayout(ct)
        l.orientation = LinearLayout.VERTICAL
        l.gravity = Gravity.CENTER_VERTICAL
        l.setBackgroundColor(NEN)
        l.setPadding(dp(ct, 24), dp(ct, 24), dp(ct, 24), dp(ct, 24))
        l.layoutParams = ViewGroup.LayoutParams(
            ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.MATCH_PARENT
        )
        return l
    }

    private fun chu(ct: Context, s: String, co: Float, mau: Int, dam: Boolean = false): TextView {
        val t = TextView(ct)
        t.text = s
        t.setTextSize(TypedValue.COMPLEX_UNIT_SP, co)
        t.setTextColor(mau)
        if (dam) t.setTypeface(t.typeface, android.graphics.Typeface.BOLD)
        t.setPadding(0, 0, 0, dp(ct, 10))
        return t
    }

    private fun nut(ct: Context, s: String, bam: () -> Unit): Button {
        val b = Button(ct)
        b.text = s
        b.setAllCaps(false)
        b.setTextColor(Color.WHITE)
        b.setBackgroundColor(CHINH)
        b.setTextSize(TypedValue.COMPLEX_UNIT_SP, 16f)
        b.setOnClickListener { bam() }
        return b
    }

    /**
     * Màn nhập địa chỉ máy chủ — chỉ hiện ở lần mở đầu tiên.
     *
     * ⚠️ KHÔNG nhét sẵn `khmatrix.com` vào mã. Địa chỉ nằm trong mã là địa chỉ không đổi được
     *    khi chuyển máy chủ, và app thì đã nằm trên mấy chục điện thoại. Gợi ý bằng `hint` thì
     *    người ta vẫn gõ nhanh mà vẫn sửa được.
     */
    fun cai(ct: Context, luuVaMo: (String) -> Unit): LinearLayout {
        val l = khung(ct)
        l.addView(chu(ct, "K&H Chấm công", 26f, CHU, dam = true))
        l.addView(
            chu(
                ct,
                "Lần đầu mở app: nhập địa chỉ trang chấm công của công ty. " +
                    "Hỏi quản lý nếu chưa biết.",
                15f, CHU_MO
            )
        )
        val o = EditText(ct)
        o.hint = "khmatrix.com"
        o.setHintTextColor(CHU_MO)
        o.setTextColor(CHU)
        o.setTextSize(TypedValue.COMPLEX_UNIT_SP, 18f)
        o.inputType = InputType.TYPE_TEXT_VARIATION_URI
        o.setSingleLine(true)
        l.addView(o)
        val loi = chu(ct, "", 14f, 0xFFFF6B6B.toInt())
        l.addView(loi)
        l.addView(
            nut(ct, "Lưu và mở") {
                val d = Luu.chuanHoa(o.text.toString())
                if (d.isEmpty()) {
                    loi.text = "Chưa nhập địa chỉ."
                } else {
                    luuVaMo(d)
                }
            }
        )
        l.addView(
            chu(ct, "\nApp luôn kết nối bằng https. Đăng nhập bằng PIN như trên web.", 13f, CHU_MO)
        )
        return l
    }

    /** Màn báo hỏng — thay cho trang lỗi của trình duyệt, vốn không nói được gì có ích. */
    fun hong(ct: Context, loi: String, taiLai: () -> Unit, doiMayChu: () -> Unit): LinearLayout {
        val l = khung(ct)
        l.addView(chu(ct, "Chưa mở được", 22f, CHU, dam = true))
        l.addView(chu(ct, loi, 15f, CHU_MO))
        l.addView(nut(ct, "Tải lại") { taiLai() })
        l.addView(chu(ct, "", 8f, CHU_MO))
        l.addView(nut(ct, "Đổi địa chỉ máy chủ") { doiMayChu() })
        return l
    }
}
