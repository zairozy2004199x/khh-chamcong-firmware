#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Soi CẤU TRÚC tệp .ino mà không cần trình biên dịch — chạy trong vài mili giây, trước khi đẩy.

VÌ SAO CÓ TỆP NÀY
-----------------
22/08/2026, khi gỡ Apps Script + Firebase khỏi firmware, em cắt nhầm hai lần liền:

  lần 1 — lát cắt lấy quá tay sang bốn hàm Digest của Hikvision. Mất chúng là máy KHÔNG đăng
          nhập được đầu đọc, tức không có lượt chấm công nào.
  lần 2 — đặt lại bốn hàm đó, nhưng phía trên còn sót một dấu MỞ chú thích `/* ===…` không có
          dấu đóng. Cả bốn hàm nằm gọn trong chú thích: trình biên dịch coi như không tồn tại.

Cả hai lần, phép thử PHP (1418 phép) đều XANH — chúng chạy hoàn toàn trong máy chấm công, không
qua mạng, nên không có phép thử nào chạm tới. Chỉ CI biên dịch mới thấy, mà CI thì mất 6 phút và
phải đẩy lên mới chạy.

Ba phép dưới đây bắt đúng hai ca đó trong vài mili giây.

⚠️ Đây KHÔNG thay trình biên dịch. Nó chỉ bắt loại lỗi "cắt/dán làm mất code" — loại đã xảy ra
   thật. Vẫn phải để CI compile.

Chạy: python3 tools/test/fw/kiem-cau-truc.py
"""
import io, os, re, sys

GOC = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', '..', '..')
TEP = [
    'esp32_hik_chamcong_full/esp32_hik_chamcong_full.ino',
    'esp32_ota_updater/esp32_ota_updater.ino',
    'esp32_ghe_massage/esp32_ghe_massage.ino',
]

# Dòng trông như MỘT ĐỊNH NGHĨA HÀM ở mức tệp: bắt đầu từ cột 0, có ngoặc, kết thúc bằng '{'.
HAM = re.compile(r'^(?!\s)(?:[A-Za-z_][\w:<>*&\s]*?)\b(\w+)\s*\([^;{}]*\)\s*\{\s*$', re.M)
# Rộng hơn HAM: bắt cả hàm thành viên (thụt đầu dòng) và hàm viết gọn một dòng `bool f(x){ ... }`.
# Bắt luôn cả `while (…) {` nên mấy từ khoá phải nằm trong BO_QUA — chấp nhận, vì thà rộng ở đây
# còn hơn để lọt một hàm bị xoá mất.
DINH_NGHIA = re.compile(r'(?:^|\n)[^\n;{}]*?\b(\w+)\s*\([^;{}]*\)\s*(?:const\s*)?\{')

dat = hong = 0
def t(ten, dieu_kien, them=''):
    global dat, hong
    if dieu_kien:
        dat += 1
    else:
        hong += 1
        print('  ✗ %s%s' % (ten, ('\n      → ' + them) if them else ''))

def bo_chu_thich(s):
    """Bỏ chú thích và chuỗi. Trả (mã, số dấu mở chưa đóng)."""
    ra = []
    i, n, mo_thua = 0, len(s), 0
    while i < n:
        c = s[i]
        if c == '/' and i + 1 < n and s[i+1] == '/':
            j = s.find('\n', i); i = j if j > 0 else n; continue
        if c == '/' and i + 1 < n and s[i+1] == '*':
            j = s.find('*/', i + 2)
            if j < 0:
                mo_thua += 1; i = n; continue
            ra.append('\n' * s.count('\n', i, j))      # giữ số dòng cho khớp
            i = j + 2; continue
        if c == '"':
            i += 1
            while i < n and s[i] != '"':
                if s[i] == '\\': i += 1
                i += 1
            i += 1; ra.append('""'); continue
        if c == "'":
            i += 1
            while i < n and s[i] != "'":
                if s[i] == '\\': i += 1
                i += 1
            i += 1; ra.append("''"); continue
        ra.append(c); i += 1
    return ''.join(ra), mo_thua

for ten_tep in TEP:
    duong = os.path.join(GOC, ten_tep)
    tho = io.open(duong, encoding='utf-8').read()
    ma, mo_thua = bo_chu_thich(tho)
    nhan = os.path.basename(ten_tep)

    # 1) Không được có dấu mở chú thích nào thiếu dấu đóng.
    t('%s: mọi /* đều có */' % nhan, mo_thua == 0,
      '%d dấu mở chú thích không có dấu đóng — mọi thứ phía sau bị coi là chú thích' % mo_thua)

    # 2) Hàm khai ở mức tệp KHÔNG được lọt vào trong chú thích.
    #    So danh sách hàm thấy trong VĂN BẢN THÔ với danh sách thấy trong MÃ ĐÃ BỎ CHÚ THÍCH.
    #    Hàm nào biến mất = nó đang nằm trong chú thích. Đây đúng là ca 22/08.
    tho_ham = set(HAM.findall(tho))
    ma_ham  = set(HAM.findall(ma))
    chim = sorted(tho_ham - ma_ham)
    t('%s: không hàm nào bị chú thích nuốt mất' % nhan, not chim,
      'nằm trong chú thích: ' + ', '.join(chim))

    # 3) Mọi tên hàm ĐƯỢC GỌI mà trông như hàm của chính tệp này thì phải CÓ ĐỊNH NGHĨA.
    #    Chỉ xét tên viết kiểu camelCase/có gạch dưới do người trong nhà đặt, và bỏ qua tên của
    #    thư viện Arduino — nên danh sách bỏ qua dưới đây là CỐ Ý ngắn: mục tiêu là bắt hàm bị
    #    xoá mất, không phải dựng lại trình biên dịch.
    goi = set(re.findall(r'(?<![\w.>:])([a-z][A-Za-z0-9_]{3,})\s*\(', ma))
    # ⚠️ `khai` PHẢI kén: chỉ nhận ĐỊNH NGHĨA (kết thúc bằng '{') và KHAI BÁO TRƯỚC ở cột 0
    #    (kết thúc bằng ';'). Bản đầu nhận cả `tên(...);` ở bất kỳ đâu — mà một LỜI GỌI
    #    `hikSend_(http, method, payload);` trông y hệt một khai báo. Hậu quả: xoá hẳn định
    #    nghĩa `hikSend_` đi mà phép thử vẫn xanh, vì chính lời gọi đó tự khai cho nó. Đã thử
    #    phá và bắt được đúng lỗ này.
    khai = set(HAM.findall(ma)) | set(DINH_NGHIA.findall(ma)) | set(
        re.findall(r'^(?!\s)(?:[A-Za-z_][\w:<>*&\s]*?)\b(\w+)\s*\([^;{}]*\)\s*;', ma, re.M))
    BO_QUA = {
        'delay', 'millis', 'random', 'strlen', 'strcmp', 'malloc', 'free', 'sprintf', 'memcpy',
        'memset', 'atoi', 'sizeof', 'digitalWrite', 'digitalRead', 'pinMode', 'analogWrite',
        'ledcWrite', 'ledcAttach', 'ledcAttachPin', 'ledcSetup', 'esp_read_mac', 'strncmp',
        'snprintf', 'localtime', 'gmtime', 'mktime', 'time', 'setenv', 'tzset', 'configTime',
        'strftime', 'abs', 'min', 'max', 'floor', 'round', 'constrain', 'isnan', 'strstr',
        'deserializeJson', 'serializeJson', 'delayMicroseconds', 'yield', 'esp_task_wdt_reset',
        'esp_restart', 'nvs_flash_init', 'strtol', 'strtoul', 'toupper', 'tolower', 'isdigit',
        'setup', 'loop', 'exit', 'printf', 'putchar', 'puts', 'fflush', 'sscanf', 'strcpy',
        'getLocalTime', 'localtime_r', 'strptime', 'isalnum', 'isspace', 'isalpha',
        'settimeofday', 'strlcpy', 'mbedtls_base64_encode',
        # Từ khoá C++ lọt vào vì `DINH_NGHIA` cố ý rộng — xem ghi chú ở đó.
        'while', 'switch', 'return', 'catch', 'sizeof', 'static_assert',
        # Arduino / ESP-IDF / FreeRTOS — tên của thư viện, không phải hàm của tệp này.
        'attachInterrupt', 'digitalPinToInterrupt', 'detachInterrupt', 'esp_random',
        'noInterrupts', 'interrupts', 'strncpy', 'vTaskDelay', 'pdMS_TO_TICKS',
        'portENTER_CRITICAL', 'portEXIT_CRITICAL', 'xTaskCreatePinnedToCore',
        'esp_qrcode_generate', 'esp_qrcode_get_module', 'esp_qrcode_get_size',
    }
    thieu = sorted(x for x in goi if x not in khai and x not in BO_QUA)
    t('%s: không gọi hàm nào không có định nghĩa' % nhan, not thieu,
      'gọi mà không thấy khai: ' + ', '.join(thieu))

    # 4) Mọi KHAI BÁO TRƯỚC phải có ĐỊNH NGHĨA đi kèm.
    #    Phép 3 ở trên không bắt được ca này: hàm nào có khai báo trước thì xoá mất định nghĩa
    #    vẫn "có khai", nên nó im. Mà thiếu định nghĩa là lỗi LIÊN KẾT — trình biên dịch cũng
    #    chỉ kêu ở bước cuối, câu lỗi thì khó đọc hơn hẳn. Tệp này có hơn chục khai báo trước
    #    (cố ý, xem khối ⚠️ ở đầu .ino), nên đây là chỗ thật sự dễ trượt.
    # ⚠️ `WebServer server(80);` và `IPAddress TARGET_IP(192,168,4,1);` trông HỆT một khai báo
    #    trước — đây đúng là chỗ C++ tự nó cũng nhập nhằng. Phân biệt bằng THAM SỐ: khai báo
    #    hàm thì mỗi tham số là "kiểu + tên" nên CÓ khoảng trắng (hoặc rỗng hẳn), còn dựng đối
    #    tượng thì tham số là hằng số trơn. Đơn giản mà đủ cho tệp này.
    # 5) Kiểu TỰ ĐỊNH NGHĨA dùng trong chữ ký HÀM TỰ DO phải khai TRƯỚC hàm đầu tiên của tệp.
    #    Arduino sinh prototype cho mọi hàm tự do rồi chèn hết vào ngay trước hàm ĐẦU TIÊN nó
    #    thấy. Struct khai sau điểm đó thì prototype nằm trước định nghĩa struct -> build đỏ với
    #    câu "'X' was not declared in this scope", chỉ vào dòng chẳng liên quan gì.
    #    Đã vướng HAI LẦN: `AnhGiaiMa` (máy chấm công) và `Btn` (ghế) — lần sau bắt ở đây.
    ham_dau = None
    for m in re.finditer(r'^(?!\s)(?:[A-Za-z_][\w:<>*&\s]*?)\b(\w+)\s*\([^;{}]*\)\s*\{', ma, re.M):
        ham_dau = m.start(); break
    kieu_muon = []
    if ham_dau is not None:
        for m in re.finditer(r'^\s*(?:struct|class)\s+(\w+)\s*\{', ma, re.M):
            if m.start() <= ham_dau: continue                 # khai trước hàm đầu tiên -> an toàn
            ten_kieu = m.group(1)
            # có hàm TỰ DO nào nhận kiểu này làm tham số không?
            if re.search(r'^(?!\s)(?:[A-Za-z_][\w:<>*&\s]*?)\b\w+\s*\([^;{}]*\b' + ten_kieu + r'\b[^;{}]*\)\s*\{',
                         ma, re.M):
                kieu_muon.append(ten_kieu)
    t('%s: kiểu tự định nghĩa dùng trong hàm tự do được khai TRƯỚC hàm đầu tiên' % nhan,
      not kieu_muon,
      'khai muộn nên Arduino sinh prototype trước định nghĩa: ' + ', '.join(kieu_muon))

    truoc = set()
    for ten_ham, tham_so in re.findall(
            r'^(?!\s)(?:[A-Za-z_][\w:<>*&\s]*?)\b(\w+)\s*\(([^;{}]*)\)\s*;', ma, re.M):
        tham_so = tham_so.strip()
        if tham_so and not all(' ' in x.strip() for x in tham_so.split(',')):
            continue                                   # dựng đối tượng, không phải khai báo hàm
        truoc.add(ten_ham)
    dinh_nghia = set(HAM.findall(ma)) | set(DINH_NGHIA.findall(ma))
    treo = sorted(x for x in truoc if x not in dinh_nghia)
    t('%s: khai báo trước nào cũng có định nghĩa' % nhan, not treo,
      'khai rồi mà không thấy định nghĩa: ' + ', '.join(treo))

# =================================================================================================
# CHỮ ĐẬM: CÁC LƯỢT SAU PHẢI VẼ KHÔNG NỀN
# =================================================================================================
# `veChuDam()` chồng bốn lượt chữ lệch nhau 1px để nét dày lên gấp đôi. Chốt duy nhất làm nó chạy
# được là: lượt ĐẦU vẽ có nền (để xoá vệt chữ cũ), ba lượt SAU vẽ trong suốt. Ai đó "dọn cho nhất
# quán" thành `setTextColor(mau, nen)` cả bốn lượt là mỗi lượt lấy nền xoá nét của lượt trước —
# chữ không đậm hơn chút nào, chỉ lệch đi 1px, mà nhìn ảnh dựng thì gần như không nhận ra.
#
# Bản dựng màn KHÔNG bắt được ca này: font 5×7 phóng to của bộ giả vốn đã có nét 3px sẵn, nên
# đậm hay không đậm đều ra một hình. Nên chốt ở đây, soi thẳng mã.
for tep in TEP:
    ma = io.open(tep, encoding='utf-8').read()
    if 'void veChuDam(' not in ma:
        continue
    nhan = os.path.basename(tep)
    than = ma[ma.index('void veChuDam('):]
    than = than[:than.index('\n}\n') + 3]
    hai  = len(re.findall(r'setTextColor\s*\([^)]*,', than))
    mot  = len(re.findall(r'setTextColor\s*\([^),]*\)', than))
    t('%s: veChuDam vẽ lượt đầu CÓ nền' % nhan, hai == 1,
      'thấy %d lượt setTextColor có nền, phải đúng 1' % hai)
    t('%s: veChuDam vẽ các lượt sau KHÔNG nền' % nhan, mot >= 1,
      'không thấy lượt setTextColor một tham số — chữ sẽ không đậm lên')
    t('%s: veChuDam vẽ đủ bốn lượt chồng nhau' % nhan,
      len(re.findall(r'drawString', than)) == 4,
      'thấy %d lượt drawString, phải đúng 4' % len(re.findall(r'drawString', than)))

if hong:
    print('\n🔴 HỎNG: %d | ĐẠT: %d' % (hong, dat))
    sys.exit(1)
print('✓ SẠCH  %d phép — không hàm nào bị cắt mất hay bị chú thích nuốt.' % dat)
