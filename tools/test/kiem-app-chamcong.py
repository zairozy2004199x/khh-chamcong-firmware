#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
SOI MÃ KOTLIN CỦA APP CHẤM CÔNG (android_cham_cong).

🔴 VÌ SAO CÓ TỆP NÀY RIÊNG, không gộp vào `kiem-app-android.py`.
   Bài kia canh app THU TIỀN, và mấy chốt của nó là chốt về TIỀN (hàng đợi, mã lần, nộp tiền
   không vào hàng đợi). App này không đụng tiền; chốt của nó nằm ở chỗ khác hẳn: quyền camera,
   quyền vị trí, và mấy cái cửa của WebView. Nhét chung một tệp là mỗi lần sửa một app phải đọc
   cả chốt của app kia để biết dòng nào của mình.

🔴 CHỖ HỎNG CỦA MỘT APP BỌC WEBVIEW KHÔNG NẰM Ở LOGIC — NÓ NẰM Ở MẤY DÒNG CẤU HÌNH.
   App này gần như không có logic: nó mở một trang web. Nhưng bỏ sót đúng MỘT dòng cấu hình thì
   mất hẳn một tính năng, và mất theo kiểu trông như lỗi phần cứng của điện thoại:

     · thiếu `domStorageEnabled`  -> `localStorage` ném lỗi giữa hàm, trang hỏng ở chỗ chẳng
                                     liên quan gì tới lưu trữ. Bẫy số một của mọi app WebView.
     · thiếu `onPermissionRequest` -> camera đen, trang báo "máy ảnh chưa sẵn sàng", không có gì
                                     trên đời chỉ ra chỗ thiếu.
     · thiếu `onGeolocation...`   -> ô vị trí trống mãi, người ta đi đổ lỗi cho GPS.
     · thiếu `onShowFileChooser`  -> bấm "Chọn tệp" không có gì xảy ra (trên web thì chạy).

   Không dòng nào trong số đó làm trình biên dịch kêu. Nên chúng phải có người canh.

⚠️ Bài này KHÔNG thay trình biên dịch. Nó bắt ngoặc lệch, tên gọi không khai, và mấy chốt trên.

Chạy: python3 tools/test/kiem-app-chamcong.py
"""
import os
import re
import sys

GOC = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', '..', 'android_cham_cong')
GOC = os.path.normpath(GOC)
NGUON = os.path.join(GOC, 'app', 'src', 'main', 'java')

loi = []
dat = 0


def t(ten, dung, them=''):
    global dat
    if dung:
        dat += 1
    else:
        loi.append(ten + (' → ' + them if them else ''))


def bo_chu_thich(s):
    """Gỡ chú thích và chuỗi, để phép đếm ngoặc không vấp vào một dấu ngoặc trong câu chữ."""
    ra = []
    i, n = 0, len(s)
    while i < n:
        c = s[i]
        if c == '"' and s[i:i + 3] == '"""':
            k = s.find('"""', i + 3)
            i = n if k < 0 else k + 3
            ra.append(' ')
            continue
        if c == '"':
            i += 1
            while i < n:
                if s[i] == '\\':
                    i += 2
                    continue
                if s[i] == '"':
                    i += 1
                    break
                i += 1
            ra.append(' ')
            continue
        if c == "'":
            i += 1
            while i < n and s[i] != "'":
                i += 2 if s[i] == '\\' else 1
            i += 1
            ra.append(' ')
            continue
        if s[i:i + 2] == '/*':
            k = s.find('*/', i + 2)
            i = n if k < 0 else k + 2
            ra.append(' ')
            continue
        if s[i:i + 2] == '//':
            k = s.find('\n', i)
            i = n if k < 0 else k
            continue
        ra.append(c)
        i += 1
    return ''.join(ra)


tep = []
for goc, _, ds in os.walk(NGUON):
    for x in ds:
        if x.endswith('.kt'):
            tep.append(os.path.join(goc, x))
tep.sort()

t('tìm được mã nguồn Kotlin', len(tep) >= 4, str(len(tep)))

def bo_chu_thich_giu_chuoi(s):
    """
    Gỡ chú thích NHƯNG GIỮ chuỗi.

    🔴 VÌ SAO PHẢI CÓ HÀM THỨ HAI. Bản đầu của bài kiểm này soi trên văn bản thô, và nó báo đỏ
       bốn chỗ — cả bốn đều là chính khối chú thích GIẢI THÍCH rằng mã không làm điều đó. Ví dụ
       chú thích "KHÔNG có addJavascriptInterface ở bất kỳ đâu" khiến phép thử kết luận là có.
       Một bộ soi đọc cả chú thích là một bộ soi phạt người viết chú thích tử tế — và người ta
       sẽ xoá chú thích đi cho nó im, tức là mất đúng thứ đáng giá nhất trong tệp.

       Mà cũng không dùng được `bo_chu_thich()` (bản gỡ cả chuỗi): nhiều chốt ở dưới tìm đúng
       một chuỗi trong mã, như `"https://$t"`. Nên cần một bản gỡ chú thích mà giữ chuỗi.
    """
    ra = []
    i, n = 0, len(s)
    while i < n:
        if s[i] == '"' and s[i:i + 3] == '"""':
            k = s.find('"""', i + 3)
            k = n if k < 0 else k + 3
            ra.append(s[i:k])
            i = k
            continue
        if s[i] == '"':
            j = i + 1
            while j < n:
                if s[j] == '\\':
                    j += 2
                    continue
                if s[j] == '"':
                    j += 1
                    break
                j += 1
            ra.append(s[i:j])
            i = j
            continue
        if s[i:i + 2] == '/*':
            k = s.find('*/', i + 2)
            i = n if k < 0 else k + 2
            ra.append(' ')
            continue
        if s[i:i + 2] == '//':
            k = s.find('\n', i)
            i = n if k < 0 else k
            continue
        ra.append(s[i])
        i += 1
    return ''.join(ra)


def bo_chu_thich_xml(s):
    """Gỡ <!-- ... --> — manifest cũng có chú thích nhắc tới quyền mà nó cố ý KHÔNG xin."""
    return re.sub(r'<!--.*?-->', ' ', s, flags=re.S)


than, tho, ma = {}, {}, {}
khai_ham, khai_lop = {}, {}

for f in tep:
    s = open(f, encoding='utf-8').read()
    sach = bo_chu_thich(s)
    than[f], tho[f] = sach, s
    ma[f] = bo_chu_thich_giu_chuoi(s)
    ten_tep = os.path.basename(f)

    for mo, dong, nhan in (('{', '}', 'nhọn'), ('(', ')', 'tròn'), ('[', ']', 'vuông')):
        t('%s: ngoặc %s cân' % (ten_tep, nhan),
          sach.count(mo) == sach.count(dong),
          '%d mở / %d đóng' % (sach.count(mo), sach.count(dong)))

    t('%s: có khai package' % ten_tep,
      re.search(r'^package\s+vn\.khh\.chamcong', s, re.M) is not None)

    for m in re.finditer(
        r'^\s*(?:@\w+(?:\([^)]*\))?\s*)*(?:private\s+|internal\s+|public\s+)*'
        r'(override\s+)?fun\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(', sach, re.M
    ):
        # Hàm `override` là điểm vào do khung Android gọi — không có lời gọi nào trong mã app,
        # và đòi phải có là ép người ta viết mã thừa.
        if m.group(1):
            continue
        khai_ham.setdefault(m.group(2), []).append(ten_tep)
    for m in re.finditer(
        r'^\s*(?:private\s+|internal\s+|public\s+|abstract\s+|open\s+|data\s+|sealed\s+)*'
        r'(?:class|object|interface)\s+([A-Za-z_][A-Za-z0-9_]*)', sach, re.M
    ):
        khai_lop.setdefault(m.group(1), []).append(ten_tep)

# 🔴 `class Ung` và `fun Ung()` cùng gói: Kotlin nhận cả hai rồi gọi nhầm, không kêu một tiếng.
trung = sorted(set(khai_ham) & set(khai_lop))
t('🔴 không có tên nào vừa là lớp vừa là hàm', not trung, ', '.join(trung))

# Hàm khai ra rồi bỏ đó — thường là dấu vết của một lời gọi bị xoá nhầm.
DIEM_VAO = {'onCreate', 'onResume', 'onPause', 'onDestroy', 'main'}
tat_ca = '\n'.join(tho.values())
khong_dung = [
    ten for ten in khai_ham
    if ten not in DIEM_VAO and not ten.startswith('on')
    and len(re.findall(r'\b%s\b' % re.escape(ten), tat_ca)) <= 1
]
t('⚠️ không có hàm nào khai ra rồi bỏ đó', not khong_dung, ', '.join(sorted(khong_dung)))

# 🔴 SOI TRÊN BẢN ĐÃ GỠ CHÚ THÍCH (vẫn giữ chuỗi) — `ma`, KHÔNG PHẢI `tho`.
#    Đã phá thử: đổi tên `onGeolocationPermissionsShowPrompt` đi cho hỏng hẳn, mà bài kiểm vẫn
#    xanh — vì khối chú thích ngay phía trên có nhắc tới cái tên ấy. Một phép thử xanh nhờ chú
#    thích là một phép thử KHÔNG canh gì cả, và nó còn tệ hơn không có: nó làm người ta yên tâm.
mn = ma[os.path.join(NGUON, 'vn', 'khh', 'chamcong', 'MainActivity.kt')]
ch = ma[os.path.join(NGUON, 'vn', 'khh', 'chamcong', 'web', 'ChromeTram.kt')]
kt = ma[os.path.join(NGUON, 'vn', 'khh', 'chamcong', 'web', 'KhachTram.kt')]
lu = ma[os.path.join(NGUON, 'vn', 'khh', 'chamcong', 'Luu.kt')]

# ── 🔴 MẤY DÒNG CẤU HÌNH MÀ THIẾU MỘT LÀ MẤT MỘT TÍNH NĂNG
t('🔴 bật localStorage (trang trạm dùng thật)', 'domStorageEnabled = true' in mn)
t('🔴 bật JavaScript', 'javaScriptEnabled = true' in mn)
t('🔴 bật định vị cho WebView', 'setGeolocationEnabled(true)' in mn)
t('🔴 không đòi chạm trước khi phát luồng camera',
  'mediaPlaybackRequiresUserGesture = false' in mn)

# ── 🔴 HAI CỬA QUYỀN. Thiếu một là mất đúng một nửa việc chấm công.
t('🔴 có cửa quyền camera cho trang', 'onPermissionRequest' in ch)
t('🔴 có cửa quyền vị trí cho trang', 'onGeolocationPermissionsShowPrompt' in ch)
t('🔴 hỏi Android xong thì TRẢ LỜI trang đang chờ', 'fun traLoiQuyen' in ch)
t('   và trả lời cả khi bị từ chối (không bỏ lửng)', '.deny()' in ch)
t('🔴 chỉ cấp camera, không cấp bừa cả danh sách trang xin',
  'RESOURCE_VIDEO_CAPTURE' in ch and 'yc.resources)' not in ch.replace('yc.resources.contains', ''))
t('🔴 có cửa chọn tệp (đơn chi phí có ô đính kèm)', 'onShowFileChooser' in ch)
t('   lời chọn tệp cũ chưa trả lời thì đóng lại, không bỏ treo',
  'nhanTep?.onReceiveValue(null)' in ch)

# ── 🔴 KHÔNG MỞ CẦU JAVASCRIPT XUỐNG ANDROID.
#    Đây là cửa để một trang web chạy mã trên máy nhân viên. App không cần nó; có nó là một
#    quyết định phải được bàn, không phải một dòng ai đó thêm vào cho tiện.
for f in tep:
    t('%s: KHÔNG có addJavascriptInterface' % os.path.basename(f),
      'addJavascriptInterface' not in ma[f])

# ── 🔴 CHỈ GIỮ TRONG APP NHỮNG GÌ THUỘC MÁY CHỦ MÌNH.
t('🔴 so tên miền bằng đuôi có dấu chấm, không bằng contains',
  'endsWith(".$tenMien")' in kt and '.contains(' not in kt)
t('🔴 giao thức lạ (tel:, intent:) đẩy ra ngoài, không nuốt vào WebView',
  'moNgoai(u)' in kt)
t('🔴 chỉ báo hỏng khi CHÍNH trang hỏng, không phải khi một ô ảnh hỏng',
  'isForMainFrame' in kt)

# ── 🔴 HTTPS, KHÔNG CÓ ĐƯỜNG NÀO KHÁC.
t('🔴 địa chỉ máy chủ luôn ép về https', '"https://$t"' in lu)
t('   và cắt luôn tiền tố http người ta lỡ gõ', 'removePrefix("http://")' in lu)
t('🔴 không nhận nội dung lẫn lộn http trong trang https',
  'MIXED_CONTENT_NEVER_ALLOW' in mn)
t('🔴 khoá cửa đọc tệp trên máy', 'allowFileAccess = false' in mn)
t('   và cửa content://', 'allowContentAccess = false' in mn)

# ── ⚠️ DỪNG CAMERA KHI XUỐNG NỀN. Đèn camera còn sáng sau khi chuyển sang Zalo là lý do
#    hoàn toàn chính đáng để nhân viên gỡ app.
t('⚠️ dừng WebView khi app xuống nền', 'web?.onPause()' in mn)
t('⚠️ và huỷ WebView đúng cách khi thoát (tránh rò bộ nhớ)',
  'boc.removeView(w)' in mn and 'w.destroy()' in mn)

# ── ⚠️ NÚT BACK CỦA ANDROID PHẢI LÀ NÚT BACK CỦA TRANG TRƯỚC ĐÃ.
t('⚠️ nút back lùi trong trang trước khi thoát app', 'w.canGoBack()' in mn and 'w.goBack()' in mn)

# ── 🔴 CỬA SỔ MỚI (`window.open`). Thiếu là mất mấy nút, và mất im lặng.
#    Đụng hai chỗ có thật: bấm một tin trong CHUÔNG để sang trang Nội bộ, và IN ĐƠN / XEM CHỨNG
#    TỪ bên Chi phí cơ sở. Trên trình duyệt thì chạy, nên lỗi chỉ lộ ra trong app.
t('🔴 cho phép window.open (nhiều cửa sổ)', 'setSupportMultipleWindows(true)' in mn)
t('   và cho JavaScript mở được cửa sổ', 'javaScriptCanOpenWindowsAutomatically = true' in mn)
t('🔴 có xử lý cửa sổ mới', 'onCreateWindow' in ch)
t('   gửi lại `resultMsg`, không thì window.open trả null',
  'ketQua.sendToTarget()' in ch)
t('🔴 cửa sổ con được huỷ khi đóng (không rò WebView)',
  'con.destroy()' in mn)
# ⚠️ `window.open(url)` phải đi qua ĐÚNG phép so tên miền của điều hướng thường, không viết lại.
t('🔴 window.open(url) dùng lại phép so tên miền chung, không viết lại',
  'KhachTram.trongNha(' in mn)

# ── 🔴 LUẬT "KHI NÀO THÌ NHẮC" PHẢI Ở MÁY CHỦ, KHÔNG CHÉP SANG KOTLIN.
#    Đây là chốt quan trọng nhất của phần thông báo. Tự tính trong app là ba dòng và chạy ngay —
#    rồi ngày nào đổi ngưỡng bên máy chủ, người mở bằng Chrome được nhắc theo ngưỡng mới còn
#    người cài app vẫn theo ngưỡng cũ, và không ai nghĩ tới việc đi sửa cái app.
nh_tep = os.path.join(NGUON, 'vn', 'khh', 'chamcong', 'nhac', 'ViecHoiNhac.kt')
t('có bộ hỏi lời nhắc', os.path.exists(nh_tep))
nh = ma.get(nh_tep, '')
t('🔴 app HỎI máy chủ `viec=nhac`, không tự tính', 'viec=nhac' in nh)
for cam in ('gio_ra', 'gioToiDa', '10 * 3600', 'nguong'):
    t('🔴 app KHÔNG mang ngưỡng giờ của riêng nó (%s)' % cam, cam not in nh)

t('🔴 dùng lại cookie của WebView, không cất bản sao thẻ phiên',
  'CookieManager.getInstance().getCookie(' in nh)
t('   và chưa đăng nhập thì thôi, không gọi thừa', 'banh.isNullOrBlank()' in nh)
# ⚠️ Việc chạy LẶP: hỏng mạng thì lượt sau tự tới. `retry()` là xếp thêm lượt chồng lên lịch lặp.
t('⚠️ hỏng mạng KHÔNG trả retry() (tránh dồn chùm)', 'Result.retry()' not in nh)
t('🔴 nhớ lời nhắc đã hiện, không rung lại mỗi 15 phút', 'daHienNhac' in nh)
# ⚠️ REPLACE đặt lại đồng hồ mỗi lần mở app -> người mở app nhiều thì lượt hỏi không bao giờ tới.
t('🔴 xếp lịch bằng KEEP, không phải REPLACE',
  'ExistingPeriodicWorkPolicy.KEEP' in nh and 'REPLACE' not in nh)

nhac_tep = os.path.join(NGUON, 'vn', 'khh', 'chamcong', 'nhac', 'Nhac.kt')
nk = ma.get(nhac_tep, '')
# ⚠️ notify() ném SecurityException khi chưa có quyền; ném trong Worker là hỏng + thử lại mãi.
t('⚠️ chưa có quyền thông báo thì nuốt đúng SecurityException',
  'catch (e: SecurityException)' in nk)
t('   PendingIntent khai IMMUTABLE (bắt buộc từ Android 12)',
  'FLAG_IMMUTABLE' in nk)

# ── 🖨 IN / LƯU PDF TRONG APP (1.3.0). WebView bỏ qua `window.print()` — không lỗi, không báo.
#    Hai đầu phải khớp nhau: trang in (PHP) đổi nút sang `vhcc_in=1` khi thấy đuôi User-Agent của
#    app, và app nhận đúng tham số ấy để mở hộp In của Android. Sửa một đầu mà quên đầu kia là
#    nút in lại chết im lặng — nên canh cả hai trong cùng một bài.
WP = os.path.normpath(os.path.join(GOC, '..', 'wordpress', 'vhcp-cham-cong'))
pdf = open(os.path.join(WP, 'includes', 'class-vhcc-pdf.php'), encoding='utf-8').read()
t('🔴 app gắn đuôi KHChamCongApp/ vào User-Agent', '" KHChamCongApp/"' in mn)
t('🔴 trang in nhận ra app qua đúng đuôi ấy và đổi sang vhcc_in=1',
  'KHChamCongApp\\//.test(navigator.userAgent)' in pdf and "vhcc_in=1" in pdf)
for tep in ('class-vhcc-phieu-luong.php', 'class-vhcc-tiep-nhan.php'):
    nd = open(os.path.join(WP, 'includes', tep), encoding='utf-8').read()
    t('   %s dùng nút in chung (không tự viết window.print())' % tep,
      'VHCC_Pdf::nut_in()' in nd and 'onclick="window.print()"' not in nd)
t('🔴 app chặn vhcc_in=1 và mở hộp In', 'getQueryParameter("vhcc_in") == "1"' in kt and 'inTrang()' in kt)
t('⚠️ chỉ trang trong nhà mới bật được hộp in', 'trongNha(u) && u.getQueryParameter("vhcc_in")' in kt)
t('   hộp In của Android in chính WebView đang mở, khổ A4',
  'createPrintDocumentAdapter' in mn and 'MediaSize.ISO_A4' in mn)
tram = open(os.path.join(WP, 'templates', 'tram.php'), encoding='utf-8').read()
t('   bảng nhập môn: mở trong app = đã cài app', "if(/KHChamCongApp\\//.test(navigator.userAgent || '')){ return true; }" in tram)

# ── Manifest: xin đúng quyền, và KHÔNG xin quyền nền.
mf = bo_chu_thich_xml(
    open(os.path.join(GOC, 'app', 'src', 'main', 'AndroidManifest.xml'), encoding='utf-8').read())
for q in ('INTERNET', 'CAMERA', 'ACCESS_FINE_LOCATION', 'POST_NOTIFICATIONS'):
    t('manifest xin quyền ' + q, q in mf)
# 🔴 Hệ này CỐ Ý không theo dõi định vị — chỉ đọc toạ độ lúc người ta bấm nút. Quyền nền là
#    mở đường cho một tính năng chưa ai quyết định làm.
for q in ('ACCESS_BACKGROUND_LOCATION', 'READ_CONTACTS',
          'READ_EXTERNAL_STORAGE', 'READ_SMS'):
    t('🔴 manifest KHÔNG xin quyền thừa ' + q, q not in mf)

# ── 🔴 MICRO: XIN ĐƯỢC, NHƯNG CHỈ KHI THẬT SỰ CÓ TÍNH NĂNG GỌI.
#    Tới 21/09/2026 phép thử này đòi manifest KHÔNG có `RECORD_AUDIO`, và lúc ấy nó đúng: một
#    app chấm công không có việc gì với micro, và quyền thừa là lý do chính đáng để người ta
#    ngại cài. Nay có gọi thoại thật nên nó thôi là quyền thừa.
#
#    Nhưng ĐỔI LUẬT chứ không XOÁ LUẬT: canh cả hai vế, để không ai thêm được quyền micro mà
#    không có tính năng đi kèm. Xoá hẳn dòng này là mở cửa cho đúng thứ nó sinh ra để chặn.
co_goi = 'RESOURCE_AUDIO_CAPTURE' in ch
t('🔴 xin micro thì PHẢI có tính năng gọi đi kèm',
  ('RECORD_AUDIO' in mf) == co_goi,
  'manifest=%s, mã=%s' % ('RECORD_AUDIO' in mf, co_goi))
t('   và app cấp micro cho trang qua đúng cửa của WebView', co_goi)
# ⚠️ Cấp bừa cả `yc.resources` là ngày nào đó một trang khác trong cùng tên miền xin thứ ba và
#    được cấp mà không ai duyệt.
t('🔴 chỉ cấp đúng camera + micro, không cấp cả gói trang xin',
  'yc.resources.filter' in ch and 'yc.grant(yc.resources)' not in ch)
t('🔴 chặn http trần ở tầng hệ điều hành', 'android:usesCleartextTraffic="false"' in mf)
t('⚠️ máy không camera / không GPS vẫn cài được', mf.count('android:required="false"') >= 2)

# ── Gradle: cùng bản trình cắm với app kia, nếu không CI tải hai bộ công cụ.
bg = open(os.path.join(GOC, 'build.gradle.kts'), encoding='utf-8').read()
bg_kia = open(os.path.join(GOC, '..', 'android_thu_tien', 'build.gradle.kts'), encoding='utf-8').read()
def ban(s, ten):
    m = re.search(r'id\("%s"\)\s+version\s+"([^"]+)"' % re.escape(ten), s)
    return m.group(1) if m else None
for tc in ('com.android.application', 'org.jetbrains.kotlin.android'):
    t('cùng bản trình cắm %s với app thu tiền' % tc,
      ban(bg, tc) == ban(bg_kia, tc), '%s vs %s' % (ban(bg, tc), ban(bg_kia, tc)))

ag = open(os.path.join(GOC, 'app', 'build.gradle.kts'), encoding='utf-8').read()
t('mã gói khác app thu tiền (hai app cài song song được)',
  'vn.khh.chamcong' in ag and 'vn.khh.ghe' not in ag)
t('có khai versionName để CI đặt tên tệp .apk',
  re.search(r'versionName = "[^"]+"', ag) is not None)
# ⚠️ Compose kéo vài MB cho hai màn vài nút — xem chú thích ở app/build.gradle.kts.
t('⚠️ không kéo Compose vào một app chỉ có WebView',
  'compose' not in bo_chu_thich_giu_chuoi(ag).lower())

# ── Có gradle wrapper thì CI mới dựng được.
for x in ('gradlew', os.path.join('gradle', 'wrapper', 'gradle-wrapper.jar'),
          os.path.join('gradle', 'wrapper', 'gradle-wrapper.properties')):
    t('có %s' % x, os.path.exists(os.path.join(GOC, x)))

print()
if loi:
    print('HỎNG: %d' % len(loi))
    for x in loi:
        print('  ✗ ' + x)
    print('ĐẠT: %d' % dat)
    sys.exit(1)
print('✓ SẠCH — %d phép trên app chấm công' % dat)
