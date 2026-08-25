/* ============================================================================
 *  icsp_dspic.h — NẠP dsPIC33F / PIC24H BẰNG ICSP CHUẨN (ESP32 tự lắc chân)
 * ----------------------------------------------------------------------------
 *  ⚠️⚠️ ĐỌC PHẦN NÀY TRƯỚC KHI CHO PHÉP GHI ⚠️⚠️
 *
 *  Các chuỗi lệnh bên dưới viết theo tài liệu Microchip DS70152
 *  (dsPIC33F/PIC24H Flash Programming Specification). Máy viết firmware này
 *  KHÔNG tải được tài liệu đó về để đối chiếu từng dòng — mạng ở đó chỉ cho ra
 *  GitHub, microchip.com bị chặn. Nên phải coi mọi hằng số ở đây là CHƯA KIỂM
 *  CHỨNG TRÊN BÀN cho tới khi bước ĐỌC MÃ CHIP chạy đúng.
 *
 *  VÌ VẬY BỘ NẠP NÀY CÓ CHỐT AN TOÀN, và đừng gỡ nó ra:
 *      1. Mặc định CHỈ ĐỌC. Không xoá, không ghi.
 *      2. Việc đầu tiên phải làm là ĐỌC MÃ CHIP (Device ID). Đọc thôi, không
 *         đụng gì vào flash, nên sai cỡ nào cũng không hỏng chip.
 *      3. Mã chip đọc ra đúng = đã chứng minh CÙNG LÚC cả bốn thứ: chìa khoá vào
 *         chế độ nạp, thứ tự bit, nhịp xung, và đường dây. Đó là lý do nó là
 *         bước một — một phép đo không rủi ro mà loại được gần hết ẩn số.
 *      4. Đọc ra rác thì SỬA Ở ĐÂY rồi đọc lại. Chừng nào chưa đọc đúng mã chip
 *         thì tuyệt đối đừng bật ghi: ghi bằng chuỗi lệnh sai là hỏng bo thật.
 *
 *  ⚠️ Ghi sai thanh ghi cấu hình FICD (chọn nhầm cặp chân ICD) là MẤT LUÔN đường
 *     ICSP — sau đó PICkit cũng không vào được nữa. Nên bộ nạp này ghi vùng cấu
 *     hình SAU CÙNG, và bắt buộc đọc ngược kiểm tra trước khi đụng tới nó.
 *
 *  ─── ĐÃ XÁC ĐỊNH CHIP NÀY NẰM Ở ĐÂU (25/08/2026) ──────────────────────────
 *  Soi hai file firmware thật thì ra kết luận NGƯỢC với giả định ban đầu:
 *
 *    ICTL70RT6900_new_1.2.hex  ->  chính ĐẦU BÁN TIỀN ICT L70 là con dsPIC33F.
 *        Ba dấu hiệu độc lập, khớp cả ba:
 *          • 87.564 lệnh, byte thứ tư CỦA MỌI LỆNH đều bằng 0, không một ngoại lệ
 *            -> đúng khuôn 24 bit + byte ma của dsPIC/PIC24;
 *          • 12 từ cấu hình ở 0xF80000..0xF80016 -> đúng bộ thanh ghi cấu hình
 *            của dsPIC33F, không họ nào khác đặt ở đó;
 *          • 87.552 lệnh chương trình = ĐÚNG dung lượng 256 KB của dsPIC33FJ256;
 *          • từ đầu tiên 0x04AB60 = lệnh GOTO — vector reset của dsPIC.
 *
 *    TimerControler20181214V0213.hex  ->  bo ghế KHÔNG PHẢI PIC, mà là ARM
 *        Cortex-M (gần như chắc chắn STM32):
 *          • dữ liệu nằm ở 0x08000000 — vùng flash của STM32;
 *          • từ đầu tiên 0x20000720 = con trỏ ngăn xếp ban đầu, trỏ vào SRAM
 *            0x20000000 — đúng khuôn bảng vector của Cortex-M;
 *          • từ thứ hai 0x080089F1 = hàm xử lý reset, số LẺ = chế độ Thumb;
 *          • bản ghi kiểu 05 khai điểm vào 0x08008A01, cũng lẻ.
 *
 *  => BỘ NẠP NÀY NẠP CHO ĐẦU BÁN TIỀN L70, KHÔNG PHẢI CHO BO GHẾ.
 *     Muốn nạp bo ghế thì là việc khác hẳn: STM32 nạp qua SWD (2 dây), hoặc dễ
 *     hơn nữa là qua BỘ NẠP UART CÓ SẴN TRONG CHIP (tài liệu ST AN3155) — chỉ cần
 *     kéo chân BOOT0 lên rồi reset, sau đó nói chuyện UART bằng giao thức công
 *     khai. Cách đó đơn giản hơn ICSP của dsPIC nhiều, và dùng lại được đúng bộ
 *     UART + mạch cách ly đã có.
 *
 *  ─── ĐẤU DÂY ───────────────────────────────────────────────────────────────
 *  dsPIC33F chạy 3,3V y như ESP32, và vào chế độ nạp chỉ cần MCLR ở mức VDD
 *  (KHÔNG cần Vpp 9-13V như PIC16/18). Nên nối thẳng, không mạch phụ:
 *
 *      ESP32 GPIO ──► MCLR   của dsPIC
 *      ESP32 GPIO ──► PGEC   (xung nhịp)
 *      ESP32 GPIO ◄─► PGED   (dữ liệu, HAI CHIỀU)
 *      GND        ───  VSS
 *
 *  ⚠️ ĐO VDD NGAY TẠI CHÂN CHIP trước khi cắm. dsPIC33F chịu tối đa 4,0V ở chân
 *     VDD — bo ăn 5V thường có ổn áp 3,3V riêng cho con dsPIC. Nếu đo ngay chân
 *     VDD của chip mà ra 5V thì con đó KHÔNG phải dsPIC33F, dừng lại xem lại mã.
 *  ⚠️ dsPIC33F có NHIỀU cặp PGEC/PGED (1, 2, 3). Phải dùng đúng cặp mà bo đi dây.
 *  ⚠️ PGED hai chiều nên nếu có phải chuyển mức thì KHÔNG dùng được loại một chiều
 *     (ADuM1201). Nhưng 3,3V thì khỏi cần chuyển mức gì cả.
 * ========================================================================== */
#pragma once
#include <Arduino.h>
#include "doc_hex.h"

/* ─── HẰNG SỐ CẦN ĐỐI CHIẾU DS70152 ────────────────────────────────────────
   Sửa ở ĐÂY, không chỗ nào khác. Nhịp để rộng tay: chậm quá chỉ tốn thời gian,
   nhanh quá thì hỏng — mà "hỏng" ở đây là đọc ra rác chứ không báo lỗi gì. */
#define ICSP_KHOA_VAO      0x4D434851UL  // "MCHQ" — dsPIC33F/PIC24H. PIC24F dùng ...50, khác một số
#define ICSP_NHIP_US       2             // nửa chu kỳ PGEC (2us ≈ 250kHz) — hạ xuống nếu dây dài
#define ICSP_P6_US         100           // giữ MCLR thấp trước khi gửi chìa khoá
#define ICSP_P18_US        100           // từ lúc hết chìa khoá tới lúc nâng MCLR
#define ICSP_P7_MS         30            // chờ sau khi nâng MCLR, trước xung đầu tiên
#define ICSP_P9A_XUNG      5             // số xung PGEC "mồi" trước lệnh SIX đầu tiên
#define ICSP_TPINT_MS      3             // chờ ghi xong một hàng (P13). Để dư một chút
#define ICSP_TXOA_MS       400           // chờ xoá sạch chip (P11). Để dư nhiều

#define ICSP_LENH_SIX      0x0           // 4 bit: cho chip THI HÀNH một lệnh 24 bit
#define ICSP_LENH_REGOUT   0x1           // 4 bit: đọc ngược thanh ghi VISI về

/* dsPIC33F: xoá theo TRANG 512 lệnh, ghi theo HÀNG 64 lệnh. */
#define ICSP_LENH_MOI_HANG 64
#define ICSP_VISI          0x0784        // địa chỉ thanh ghi VISI
#define ICSP_DEVID         0x00FF0000UL  // địa chỉ mã chip
#define ICSP_CONFIG_DAU    0x00F80000UL  // vùng cấu hình bắt đầu từ đây

/** Một dòng trong bảng chip — thêm chip mới thì thêm một dòng, không sửa code. */
struct ChipBiet {
  uint16_t    ma;          // giá trị DEVID đọc được
  const char* ten;
  uint32_t    soLenh;      // số lệnh của vùng chương trình (không kể cấu hình)
};

/* ⚠️ Mã chip dưới đây LẤY TỪ TRÍ NHỚ, PHẢI ĐỐI CHIẾU. Cách đối chiếu chắc nhất mà
   không cần tài liệu: cắm PICkit vào chính con chip đó, MPLAB IPE báo mã ra bao
   nhiêu thì điền vào đây. Bảng sai thì bộ nạp TỪ CHỐI GHI (đúng như thiết kế) —
   khó chịu nhưng không hỏng gì. */
static const ChipBiet ICSP_BANG_CHIP[] = {
  { 0x0061, "dsPIC33FJ256GP710", 0x0155FE },
  { 0x00C1, "dsPIC33FJ256GP710A", 0x0155FE },
  { 0x005D, "dsPIC33FJ256MC710", 0x0155FE },
  { 0x0067, "dsPIC33FJ256GP506", 0x0155FE },
};

enum KetQuaNap { NAP_OK = 0, NAP_KHONG_VAO, NAP_SAI_CHIP, NAP_LOI_HEX, NAP_LECH, NAP_CHUA_CHO_GHI };

class IcspDsPic {
public:
  /* ⚠️ CHỐT AN TOÀN. Để false thì mọi hàm xoá/ghi đều từ chối ngay. Chỉ bật sau
     khi docMaChip() đã trả về đúng tên chip trên bàn thật. */
  bool choPhepGhi = false;
  bool amThanh    = true;

  void batDau(int chanMclr, int chanPgec, int chanPged) {
    _mclr = chanMclr; _pgec = chanPgec; _pged = chanPged;
    pinMode(_mclr, OUTPUT); digitalWrite(_mclr, HIGH);   // để chip chạy bình thường
    pinMode(_pgec, OUTPUT); digitalWrite(_pgec, LOW);
    _pgedRa();              digitalWrite(_pged, LOW);
    Serial.printf("[ICSP] MCLR=%d PGEC=%d PGED=%d\n", _mclr, _pgec, _pged);
  }

  /** Thả chip ra cho nó chạy chương trình. Gọi sau khi xong việc. */
  void thaChip() {
    _pgedVao();
    digitalWrite(_pgec, LOW);
    digitalWrite(_mclr, LOW);  delay(10);
    digitalWrite(_mclr, HIGH);
    if (amThanh) Serial.println("[ICSP] da tha chip, no chay lai binh thuong");
  }

  /* ==================================================================
   *  BƯỚC MỘT — ĐỌC MÃ CHIP. CHỈ ĐỌC, KHÔNG ĐỤNG FLASH.
   *  Đọc ra đúng tên chip = chìa khoá, thứ tự bit, nhịp xung, đường dây đều ĐÚNG.
   * ================================================================== */
  /** Mở một phiên nạp. Vào chế độ nạp TỐN ~30ms nên đừng vào/ra cho từng từ —
      đọc cả vùng flash mà làm vậy thì mất hàng giờ thay vì vài phút. */
  bool moPhien() {
    if (_dangMo) return true;
    if (!_vaoCheDoNap()) return false;
    _thoatReset();
    _dangMo = true;
    return true;
  }
  void dongPhien() { if (_dangMo) { _dangMo = false; thaChip(); } }

  const ChipBiet* docMaChip(uint16_t* maRa = nullptr, uint16_t* banRa = nullptr) {
    if (!moPhien()) { Serial.println("[ICSP] khong vao duoc che do nap"); return nullptr; }

    // TBLPAG = 0xFF ; W6 = 0x0000 ; W7 = VISI
    _six(0x200FF0);  // MOV #0xFF, W0
    _six(0x8802A0);  // MOV W0, TBLPAG
    _six(0x200006);  // MOV #0x0000, W6
    _six(0x207847);  // MOV #VISI, W7
    _six(0x000000);  // NOP
    _six(0xBA0BB6);  // TBLRDL [W6], [W7]
    _six(0x000000); _six(0x000000);
    uint16_t ma = _regout();

    _six(0x200016);  // MOV #0x0002, W6   -> DEVREV
    _six(0x000000);
    _six(0xBA0BB6);
    _six(0x000000); _six(0x000000);
    uint16_t ban = _regout();

    dongPhien();
    if (maRa)  *maRa  = ma;
    if (banRa) *banRa = ban;

    Serial.printf("[ICSP] DEVID = 0x%04X   DEVREV = 0x%04X\n", ma, ban);
    if (ma == 0x0000 || ma == 0xFFFF) {
      Serial.println("[ICSP] ⚠️ doc ra toan 0 hoac toan F -> chua noi chuyen duoc voi chip.");
      Serial.println("       Kiem theo thu tu: GND chung chua; dung cap PGEC/PGED ma bo di day chua;");
      Serial.println("       chip da co dien chua; roi moi tinh toi chuyen sua hang so trong file nay.");
      Serial.println("       Neu day cam chac chan roi ma van vay: thu lat ICSP_MSB_TRUOC.");
      return nullptr;
    }
    for (unsigned i = 0; i < sizeof(ICSP_BANG_CHIP)/sizeof(ICSP_BANG_CHIP[0]); i++)
      if (ICSP_BANG_CHIP[i].ma == ma) {
        Serial.printf("[ICSP] ✅ nhan ra chip: %s\n", ICSP_BANG_CHIP[i].ten);
        return &ICSP_BANG_CHIP[i];
      }
    Serial.println("[ICSP] ⚠️ doc duoc ma chip nhung KHONG co trong bang ICSP_BANG_CHIP.");
    Serial.println("       Doc duoc so co nghia = tang ICSP da CHAY DUNG, chi thieu mot dong trong bang.");
    Serial.printf ("       Them dong nay vao ICSP_BANG_CHIP roi nap lai:  { 0x%04X, \"<ten chip>\", 0x0155FE },\n", ma);
    return nullptr;
  }

  /* ==================================================================
   *  ĐỌC FLASH — vẫn CHỈ ĐỌC, không đụng gì. Dùng để:
   *    - kiểm xem chip đang chạy đúng bản mình nghĩ không;
   *    - và quan trọng hơn: chứng minh tầng ICSP chạy đúng tới mức đọc được
   *      dữ liệu có nghĩa, TRƯỚC KHI cho phép ghi.
   *  Đọc sai thì chỉ ra số rác, không hỏng chip — nên cứ thử thoải mái.
   * ================================================================== */

  /** Đọc MỘT từ lệnh 24 bit tại địa chỉ chương trình. Phải moPhien() trước. */
  uint32_t docTu(uint32_t diaChi) {
    if (!_dangMo) return 0xFFFFFFFF;
    uint16_t cao = (uint16_t)((diaChi >> 16) & 0xFF);
    uint16_t thap = (uint16_t)(diaChi & 0xFFFF);

    _six(0x200000 | ((uint32_t)cao << 4));      // MOV #<cao>, W0
    _six(0x8802A0);                             // MOV W0, TBLPAG
    _six(0x200006 | ((uint32_t)thap << 4));     // MOV #<thap>, W6
    _six(0x207847);                             // MOV #VISI, W7
    _six(0x000000);

    _six(0xBA0BB6);                             // TBLRDL [W6], [W7]
    _six(0x000000); _six(0x000000);
    uint16_t thapRa = _regout();

    _six(0xBA8BB6);                             // TBLRDH [W6], [W7]
    _six(0x000000); _six(0x000000);
    uint16_t caoRa = _regout();

    return ((uint32_t)(caoRa & 0x00FF) << 16) | thapRa;
  }

  /** Đổ một vùng flash ra cổng USB, dạng hex — để nhìn bằng mắt lúc dò. */
  void doVung(uint32_t diaChi, uint32_t soLenh) {
    if (!moPhien()) { Serial.println("[ICSP] khong vao duoc che do nap"); return; }
    for (uint32_t i = 0; i < soLenh; i++) {
      uint32_t dc = diaChi + i * 2;
      if (i % 4 == 0) Serial.printf("\n%06lX: ", (unsigned long)dc);
      Serial.printf("%06lX ", (unsigned long)docTu(dc));
    }
    Serial.println();
    dongPhien();
  }

private:
  bool _dangMo = false;
  int  _mclr = -1, _pgec = -1, _pged = -1;

  void _pgedRa()  { pinMode(_pged, OUTPUT); }
  void _pgedVao() { pinMode(_pged, INPUT); }

  /* ⚠️ THỨ TỰ BIT — chỗ dễ sai nhất, và sai thì chỉ biểu hiện bằng "đọc ra rác".
     Theo DS70152: chìa khoá 32 bit đi BIT CAO TRƯỚC, còn lệnh 4 bit và dữ liệu
     24/16 bit đi BIT THẤP TRƯỚC. Đúng là nó lệch nhau như vậy, không phải nhầm.
     Đọc mã chip ra rác thì thử lật cờ này trước khi nghi ngờ chỗ khác. */
  static const bool ICSP_MSB_TRUOC = false;

  void _xung() { digitalWrite(_pgec, HIGH); delayMicroseconds(ICSP_NHIP_US);
                 digitalWrite(_pgec, LOW);  delayMicroseconds(ICSP_NHIP_US); }

  void _guiBit(uint8_t b) {
    digitalWrite(_pged, b ? HIGH : LOW);
    _xung();
  }
  void _gui(uint32_t v, int soBit, bool msbTruoc) {
    _pgedRa();
    for (int i = 0; i < soBit; i++) {
      int viTri = msbTruoc ? (soBit - 1 - i) : i;
      _guiBit((uint8_t)((v >> viTri) & 1));
    }
  }

  /** Cho chip thi hành một lệnh máy 24 bit. */
  void _six(uint32_t lenh) {
    _gui(ICSP_LENH_SIX, 4, ICSP_MSB_TRUOC);
    _gui(lenh, 24, ICSP_MSB_TRUOC);
  }

  /** Đọc ngược 16 bit trong thanh ghi VISI về. */
  uint16_t _regout() {
    _gui(ICSP_LENH_REGOUT, 4, ICSP_MSB_TRUOC);
    _pgedVao();
    for (int i = 0; i < 8; i++) _xung();          // 8 chu kỳ trống trước khi dữ liệu ra
    uint16_t v = 0;
    for (int i = 0; i < 16; i++) {
      digitalWrite(_pgec, HIGH); delayMicroseconds(ICSP_NHIP_US);
      uint8_t b = digitalRead(_pged) ? 1 : 0;
      digitalWrite(_pgec, LOW);  delayMicroseconds(ICSP_NHIP_US);
      if (ICSP_MSB_TRUOC) v = (uint16_t)((v << 1) | b);
      else                v = (uint16_t)(v | ((uint16_t)b << i));
    }
    _pgedRa();
    return v;
  }

  /** Nhảy ra khỏi vector reset — luôn phải làm trước mọi việc khác. */
  void _thoatReset() {
    _six(0x000000);   // NOP
    _six(0x040200);   // GOTO 0x200
    _six(0x000000);   // NOP
    _six(0x000000);
  }

  /**
   * Vào chế độ nạp. dsPIC33F chỉ cần MCLR ở mức VDD, không cần điện áp cao.
   *   MCLR xuống -> lên nháy -> xuống -> gửi chìa khoá -> MCLR lên và giữ.
   */
  bool _vaoCheDoNap() {
    _pgedRa();
    digitalWrite(_pgec, LOW);
    digitalWrite(_pged, LOW);
    digitalWrite(_mclr, HIGH); delayMicroseconds(ICSP_P6_US);
    digitalWrite(_mclr, LOW);  delayMicroseconds(ICSP_P6_US);
    digitalWrite(_mclr, HIGH); delayMicroseconds(ICSP_P6_US);   // nháy một cái
    digitalWrite(_mclr, LOW);  delayMicroseconds(ICSP_P6_US);

    _gui(ICSP_KHOA_VAO, 32, true);        // chìa khoá đi BIT CAO TRƯỚC
    digitalWrite(_pged, LOW);
    delayMicroseconds(ICSP_P18_US);

    digitalWrite(_mclr, HIGH);
    delay(ICSP_P7_MS);
    for (int i = 0; i < ICSP_P9A_XUNG; i++) _xung();
    return true;                          // không có cách nào biết chắc ngoài việc đọc mã chip
  }
};
