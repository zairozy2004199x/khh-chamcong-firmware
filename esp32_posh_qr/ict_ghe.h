/* ============================================================================
 *  ict_ghe.h — NÓI CHUYỆN VỚI BO ICT CỦA GHẾ MASSAGE QUA UART (RX/TX)
 * ----------------------------------------------------------------------------
 *  ESP32 đóng vai "cái máy nhận tiền": khách quét QR hợp lệ thì ESP32 bảo bo ghế
 *  "cho chạy N phút", y như lúc bo nhận được tiền xu/tiền giấy.
 *
 *  ⚠️ ĐỌC KỸ PHẦN ĐẤU DÂY TRƯỚC KHI CẮM. Sai một chỗ là cháy bo, mà cháy thì
 *     không có thông báo nào cả.
 *
 *  ─── ĐẤU DÂY ───────────────────────────────────────────────────────────────
 *   ESP32 TX  ──►  RX của bo ghế        (CHÉO nhau, không phải TX-TX)
 *   ESP32 RX  ◄──  TX của bo ghế
 *   GND       ───  GND của bo ghế       ◄── BẮT BUỘC
 *
 *   1) MASS CHUNG. Quên nối GND là triệu chứng kinh điển: nhận toàn byte rác,
 *      hoặc lúc được lúc không tuỳ tay có chạm vào vỏ hay không. Nối GND trước
 *      khi ngồi soi baud.
 *
 *   2) MỨC ĐIỆN ÁP. ESP32 chạy 3,3V và chân của nó KHÔNG chịu được 5V.
 *        • Bo TTL 5V  -> chân RX của ESP32 phải qua chia áp (1kΩ nối tiếp +
 *          2kΩ xuống mass) hoặc mạch level shifter. Chiều ESP32 TX -> bo thì
 *          thường chạy thẳng được, vì 3,3V đã vượt ngưỡng HIGH của TTL 5V.
 *        • Bo RS232 (cổng DB9, mức ±12V) -> BẮT BUỘC qua MAX3232. Cắm thẳng
 *          RS232 vào ESP32 là CHÁY CHÂN NGAY, không cứu được.
 *      Không chắc bo loại gì thì đo: chân TX của bo lúc nghỉ, so với mass —
 *      khoảng 3,3V hoặc 5V là TTL; âm (-5V..-12V) là RS232.
 *
 *   3) CHỌN CHÂN. Trên ESP32 cổ điển:
 *        • GPIO 6..11 dính flash — dùng là chip không boot.
 *        • GPIO 34..39 chỉ vào được, KHÔNG làm chân TX được.
 *        • GPIO 0, 2, 12, 15 là chân quyết định kiểu boot. Bo ghế kéo mấy chân
 *          đó lên/xuống lúc bật nguồn là ESP32 không khởi động. Riêng GPIO12 bị
 *          kéo lên còn làm chip chuyển flash sang 1,8V — coi như hỏng.
 *      => Mặc định file này dùng GPIO 17 (TX) và GPIO 16 (RX): an toàn, không
 *         dính boot, không dính flash.
 *      ⚠️ Bo ESP32 loại WROVER thì GPIO16/17 đã bị PSRAM chiếm. Dùng WROVER thì
 *         đổi sang cặp khác (ví dụ 25/26) trong file .ino.
 *
 *  ─── BO CÓ CHIP ĐỆM HT245 TRÊN ĐƯỜNG UART ─────────────────────────────────
 *  Bo ICT đưa tín hiệu sang ghế qua HT245 (chính là con 74HC245 — đệm bus 8 kênh,
 *  hai chiều). Biết điều này thì phải xử 4 việc, cái nào bỏ qua cũng ra đúng một
 *  triệu chứng: "gửi mà ghế không nhúc nhích".
 *
 *  1) ⚠️ CON ĐỆM TRÊN BO NÀY CHẠY VCC 5V (đã đo chân 20 so với chân 10), mà ESP32
 *     chạy 3,3V. Hai bên KHÔNG nói chuyện thẳng với nhau được.
 *     ĐÃ CHỌN CÁCH XỬ: bộ CÁCH LY SỐ **ADuM1201** — hai kênh, một kênh mỗi chiều,
 *     vừa khít một cặp UART, hai bên nguồn riêng.
 *
 *     ─── ĐẤU DÂY ───────────────────────────────────────────────────────────────
 *
 *       ESP32                    ADuM1201                    Bo ghế
 *       ─────                    ────────                    ──────
 *       3V3  ──────────────────  VDD1          VDD2  ──────  5V  (chân 20 HT245)
 *       GND  ──────────────────  GND1          GND2  ──────  GND (chân 10 HT245)
 *       GPIO33 (TX) ──────────►  VIA  ─kênh A─► VOA   ──────► đầu VÀO của HT245
 *       GPIO32 (RX) ◄──────────  VOB  ◄─kênh B─ VIB   ◄────── đầu RA  của HT245
 *
 *       Tụ 0,1µF sát chân VDD1 và sát chân VDD2. Thêm 10µF làm tụ đệm.
 *
 *     ⚠️⚠️ GND1 VÀ GND2 KHÔNG ĐƯỢC NỐI VỚI NHAU.
 *        Đây là chỗ NGƯỢC HẲN với mọi cách đấu thường, nên rất dễ làm theo quán
 *        tính rồi hỏng mà không biết: nối hai mass lại thì mạch VẪN CHẠY — nên
 *        chẳng ai phát hiện ra — chỉ là đã vứt sạch phần cách ly vừa bỏ tiền mua.
 *        Ghế có mô-tơ, mà mô-tơ đá nhiễu ngược về đường nguồn; cách ly là thứ chặn
 *        đường đó. Kéo theo: ESP32 phải ăn nguồn RIÊNG, không lấy 5V của ghế.
 *
 *     ⚠️ PHẢI LÀ ADuM1201, KHÔNG PHẢI ADuM1200. Nhìn giống hệt, cùng vỏ 8 chân,
 *        tên lệch một số. Con 1200 hai kênh CÙNG chiều: UART không chạy được, mà
 *        cắm vào cũng chẳng cháy gì — chỉ là một chiều im lặng mãi.
 *
 *     ⚠️ TỤ LỌC KHÔNG PHẢI TUỲ CHỌN. Thiếu tụ thì chip chạy chập chờn và bắn nhiễu
 *        ra ngoài, mà triệu chứng trông y hệt sai baud — ngồi mò rất lâu mới ra.
 *
 *     ─── ĐƯỢC GÌ SO VỚI MẠCH CHUYỂN MỨC THƯỜNG ─────────────────────────────────
 *       • KHỎI PHẢI TRẢ LỜI CÂU "HC HAY HCT". Đầu ra phía 2 đánh đúng 5V đẩy đối
 *         xứng, vượt xa ngưỡng 3,5V của loại 74HC khó tính nhất. Xem "VÌ SAO KHÔNG
 *         ĐI ĐƯỜNG KHÁC" bên dưới để biết vì sao câu đó không tra ra được.
 *       • KHỎI CẦN CHIA ÁP chiều ngược lại: phía 1 đã ra sẵn 3,3V.
 *       • CHIỀU CỐ ĐỊNH từng kênh, không có kiểu lỗi "tự đoán chiều".
 *       • KHÔNG CÓ CHÂN OE để mà quên.
 *       • Chạy tới hàng Mbps — không còn trần baud 38400.
 *
 *     ─── LẮP XONG PHẢI KIỂM, ĐỪNG TIN LUÔN ─────────────────────────────────────
 *       1. GIU 1  -> đo VOA (phía 5V) so với GND2: phải ~5V.
 *          GIU 0  -> phải ~0V.  Không đổi gì = sai kênh (lấy nhầm chiều), thiếu
 *          nguồn một phía, hoặc lỡ mua con 1200.
 *          GIU    -> thả chân ra.
 *       2. DAY    -> mức nghỉ chân RX phải ~100% ở mức CAO.
 *       3. Khép kín đường đi rồi TUKIEM 200. Phải đúng 200/200. Một lần đúng KHÔNG
 *          nói lên gì — lỗi mức điện áp và lỗi sườn xung chỉ lộ ra khi chạy nhiều.
 *
 *     ─── NẾU DÙNG MẠCH CHUYỂN MỨC THƯỜNG THAY VÌ CÁCH LY ───────────────────────
 *       Loại 4 kênh BSS138: A/LV ← ESP32, B/HV ← bo ghế, đủ CẢ HAI nguồn, kênh khớp
 *       số (LV1 chỉ thông với HV1), và MASS CHUNG HẾT (loại này không cách ly).
 *       ⚠️ Giữ baud ≤ 38400 — nó kéo mức cao bằng trở 10k nên sườn xung ì.
 *       ⚠️ Đừng dùng TXS0108E cho UART: con đó sinh ra cho I2C, ghép với UART đẩy
 *          đối xứng thì hay dở chứng (bit dính, rác lác đác, có khi tự dao động).
 *
 *
 *  2) CHIỀU (chân DIR, số 1) VÀ CHO PHÉP (chân OE, số 19, tích cực MỨC THẤP).
 *     Một con 245 chỉ có MỘT chân DIR cho cả 8 kênh — nên hai đường đi qua nó đều bị
 *     ép CÙNG một chiều. Vì vậy "2 đường qua HT245" gần như chắc chắn là hai đường
 *     CÙNG chiều ICT → ghế, chứ không phải một cặp TX/RX đối nhau. Nếu đúng vậy thì:
 *       • ghế chỉ NGHE, bo ICT chỉ NÓI — đừng ngồi chờ ACK, sẽ không bao giờ có;
 *       • đặt ICT_SO_LAN_GUI = 1 cho khỏi gửi lặp vô ích, và coi "không trả lời" là
 *         BÌNH THƯỜNG (bên .ino đã cố ý KHÔNG huỷ lượt khi thiếu ACK, xem ghi chú ở đó).
 *     Còn OE bị treo mức cao là cả con 245 thả nổi hết đầu ra — đo chân nào cũng lửng
 *     lơ, y hệt như đứt dây.
 *
 *  3) CẮM VÀO ĐÂU. Hai lựa chọn, đừng làm cả hai:
 *       • CẮM PHÍA ĐẦU VÀO của HT245 (phía bo ICT): ghế nhận đúng tín hiệu đã qua đệm
 *         y như cũ. Nhưng phải RÚT/vô hiệu bo ICT, không thì hai bên cùng đẩy một dây
 *         -> chập mức, cháy chân. Cách này sạch nhất.
 *       • CẮM PHÍA ĐẦU RA của HT245 (phía ghế): bắt buộc phải VÔ HIỆU con 245 trước
 *         (kéo OE lên mức cao), không thì đầu ra của nó đánh nhau với chân ESP32.
 *         Firmware có sẵn chân điều khiển OE cho việc này — xem CHAN_HT245_OE.
 *
 *  4) MASS. Đã nói ở trên nhưng nhắc lại vì có thêm con đệm ở giữa: GND của ESP32
 *     phải chung với GND của HT245 (chân 10), không phải chung ở đâu đó xa xa.
 *
 *  ⚠️ CHƯA BIẾT HAI ĐƯỜNG ĐÓ LÀ GÌ (TX+RX? hay dữ liệu + xung chốt?) thì đừng đoán:
 *     gõ lệnh DAY để đo mức nghỉ từng đường (UART nghỉ luôn ở mức CAO), rồi gõ NGHE
 *     trong lúc bấm nút trên ghế. Đường nào có nhịp đổi mức là đường dữ liệu.
 *
 *  ─── KHUNG LỆNH ────────────────────────────────────────────────────────────
 *  Mỗi hãng một kiểu, nên file này làm sẵn HAI kiểu, đổi được lúc chạy (portal
 *  hoặc lệnh gõ qua cổng USB), KHÔNG phải biên dịch lại:
 *
 *   CHẾ ĐỘ 1 — KHUNG NHỊ PHÂN (mặc định)
 *      gửi:     02  LEN  CMD  [DATA…]  CHK  03
 *               LEN = số byte của (CMD + DATA)
 *               CHK = XOR của tất cả byte tính từ LEN cho tới hết DATA
 *      CMD 0x31 mở ghế   DATA = 2 byte số phút (byte cao trước)
 *      CMD 0x32 dừng ghế
 *      CMD 0x33 hỏi bo còn sống không
 *      bo trả:  02  LEN  CMD  KQ  CHK  03      KQ: 0x06 = nhận, 0x15 = từ chối
 *
 *   CHẾ ĐỘ 2 — DÒNG CHỮ (ASCII)
 *      gửi:     "RUN 15\r\n"   "STOP\r\n"   "PING\r\n"
 *      bo trả:  dòng nào có "OK" / "ACK" là nhận; có "ERR" / "NAK" là từ chối.
 *
 *  ⚠️ CHƯA BIẾT BO NÓI KIỂU NÀO thì ĐỪNG ĐOÁN. Dùng bộ công cụ ở cuối file:
 *     doBaud() dò tốc độ, nghe() nghe lén bo nói gì, banHex()/banChu() bắn thử,
 *     cauNoi() nối thẳng bo với máy tính. Bắt được đúng khung rồi thì sửa lại
 *     mấy hằng số ngay bên dưới — chỉ sửa ở ĐÂY, chỗ khác không phải đụng.
 * ========================================================================== */
#pragma once
#include <Arduino.h>

/* ---- Sửa ở đây nếu bo dùng khung khác ---- */
#define ICT_STX        0x02
#define ICT_ETX        0x03
#define ICT_ACK        0x06
#define ICT_NAK        0x15
#define ICT_CMD_MO     0x31
#define ICT_CMD_DUNG   0x32
#define ICT_CMD_PING   0x33

#define ICT_CHE_NHI_PHAN  1
#define ICT_CHE_DONG_CHU  2

#define ICT_CHO_TRA_LOI_MS  600   // đợi bo trả lời bao lâu trước khi gửi lại
#define ICT_SO_LAN_GUI      3     // gửi lại mấy lần rồi mới chịu thua
#define ICT_DEM_TOI_DA      64
#define ICT_NGHI_MS         20    // dây im ngần này = hết một khung (ở 9600 một byte mất ~1ms)
#define ICT_SO_KHUNG_NHO    6     // nhớ mấy khung gần nhất để hiện lên portal

/* Một "khung" = một chùm byte bo ghế gửi tới, cắt theo khoảng lặng trên dây.
   ⚠️ CỐ Ý cắt theo khoảng lặng chứ không theo cấu trúc khung: lúc mới đấu dây thì
      chưa ai biết bo dùng khung kiểu gì, mà nếu bộ thu chỉ nhận đúng một dạng thì
      mọi thứ khác nó nuốt mất — thành ra bo CÓ nói mà màn hình vẫn trắng trơn. */
struct KhungIct {
  uint8_t  d[ICT_DEM_TOI_DA];
  uint8_t  n   = 0;
  uint32_t luc = 0;      // millis lúc nhận xong
  bool     tran = false; // dài quá cỡ, đã bị cắt
};

class IctGhe {
public:
  bool amThanh = true;            // true = in mọi byte gửi/nhận ra cổng USB (bật khi đang dò)

  void batDau(HardwareSerial* cong, int chanRx, int chanTx, long baud, uint8_t che) {
    _cong = cong; _rx = chanRx; _tx = chanTx; _baud = baud; _che = che;
    _moCong();
    Serial.printf("[ICT] bo ghe @ %ld baud, RX=%d TX=%d, khung=%s\n",
                  _baud, _rx, _tx, _che == ICT_CHE_DONG_CHU ? "dong chu" : "nhi phan");
  }

  void doiBaud(long baud) { if (baud == _baud) return; _baud = baud; _moCong();
                            Serial.printf("[ICT] doi baud -> %ld\n", _baud); }
  void doiChe(uint8_t che) { _che = che;
                            Serial.printf("[ICT] doi khung -> %s\n",
                              _che == ICT_CHE_DONG_CHU ? "dong chu" : "nhi phan"); }
  long  baud() const { return _baud; }
  uint8_t che() const { return _che; }
  /** Dòng mô tả lần trao đổi gần nhất — để in ra portal cho thợ xem, khỏi cắm USB. */
  String lanCuoi() const { return _lanCuoi; }

  /* ======================= LỆNH THẬT =======================
     Cả 3 hàm đều trả về: bo có TRẢ LỜI NHẬN hay không.
     ⚠️ Trả false KHÔNG chắc chắn nghĩa là ghế không chạy — có bo nhận lệnh xong
        im luôn, không trả lời gì. Bên .ino phải xử theo kiểu "gửi rồi thì coi như
        đã bán", đừng trừ tiền/huỷ lượt chỉ vì không thấy ACK. Xem ghi chú ở .ino. */
  bool moGhe(uint16_t phut) {
    if (phut == 0) return false;
    if (_che == ICT_CHE_DONG_CHU) return _guiDong("RUN " + String(phut));
    uint8_t d[2] = { (uint8_t)(phut >> 8), (uint8_t)(phut & 0xFF) };
    return _guiKhung(ICT_CMD_MO, d, 2);
  }
  bool dungGhe() {
    if (_che == ICT_CHE_DONG_CHU) return _guiDong("STOP");
    return _guiKhung(ICT_CMD_DUNG, nullptr, 0);
  }
  bool pingBo() {
    if (_che == ICT_CHE_DONG_CHU) return _guiDong("PING");
    return _guiKhung(ICT_CMD_PING, nullptr, 0);
  }

  /* ==================================================================
   *  HT245 — chân điều khiển con đệm trên đường UART (nếu có đấu tới)
   * ================================================================== */

  /** Khai chân ESP32 đang nối tới OE (chân 19) và DIR (chân 1) của HT245. -1 = không đấu. */
  void datChanHT245(int chanOE, int chanDIR) {
    _oe = chanOE; _dir = chanDIR;
    if (_oe  >= 0) { pinMode(_oe,  OUTPUT); digitalWrite(_oe,  LOW);  }   // LOW = cho 245 dẫn
    if (_dir >= 0) { pinMode(_dir, OUTPUT); digitalWrite(_dir, HIGH); }
    Serial.printf("[HT245] OE=%d DIR=%d\n", _oe, _dir);
  }
  /** true = cho HT245 dẫn (OE kéo xuống thấp). false = thả nổi đầu ra để ESP32 tự đẩy dây. */
  void chodan(bool cho) {
    if (_oe < 0) { Serial.println("[HT245] chua khai chan OE — bo qua"); return; }
    digitalWrite(_oe, cho ? LOW : HIGH);
    Serial.printf("[HT245] OE = %s -> con dem %s\n", cho ? "THAP" : "CAO",
                  cho ? "DAN (bo ICT dieu khien day)" : "THA NOI (ESP32 tu day)");
  }
  void datChieu(bool aSangB) {
    if (_dir < 0) { Serial.println("[HT245] chua khai chan DIR — bo qua"); return; }
    digitalWrite(_dir, aSangB ? HIGH : LOW);
    Serial.printf("[HT245] DIR = %s\n", aSangB ? "A->B" : "B->A");
  }

  /**
   * GIỮ CHÂN TX Ở MỘT MỨC CỐ ĐỊNH để đo bằng đồng hồ vạn năng.
   *   muc = 1  giữ mức cao (chân ESP32 ra 3,3V)
   *   muc = 0  giữ mức thấp (0V)
   *   muc = -1 thả ra, trả chân về cho UART
   *
   * DÙNG ĐỂ LÀM GÌ: đây là cách nhanh nhất trả lời câu "con HT245 5V này có chịu
   * ăn mức 3,3V của ESP32 không", mà chỉ cần cái đồng hồ, không cần máy hiện sóng.
   *
   *   1. Nối chân TX của ESP32 vào ĐẦU VÀO của HT245 (nhớ GND chung).
   *   2. Gõ  GIU 1  rồi đo ĐẦU RA tương ứng của HT245 so với mass:
   *        ~5V   -> con chip ĐÃ hiểu 3,3V là mức cao. Loại ngưỡng thấp (HCT/LVC).
   *        ~0V   -> nó KHÔNG hiểu. Đây là loại ngưỡng 3,5V — phải nâng mức, hết cãi.
   *        lửng lơ (1–4V) -> đang ở vùng chập chờn, cũng phải nâng mức.
   *   3. Gõ  GIU 0  rồi đo lại: phải ra ~0V. Không đổi gì cả nghĩa là chưa nối đúng
   *      chân, hoặc con 245 đang bị vô hiệu (OE ở mức cao), hoặc sai chiều DIR.
   *   4. Gõ  GIU  (bỏ trống) để thả chân ra rồi làm tiếp việc khác.
   *
   * ⚠️ Ra ~5V ở bước 2 mới chỉ là "qua được ở trạng thái đứng yên". Ngưỡng sát nút
   *    vẫn đủ sức làm sai lúc đang truyền nhanh và lúc mạch ấm lên. Đo xong vẫn phải
   *    chạy TUKIEM 200 rồi mới tin.
   */
  void giuMuc(int muc) {
    if (!_cong) return;
    if (muc < 0) { _moCong(); Serial.println("[GIU] da tha chan TX, tra ve cho UART"); return; }
    _cong->end();
    pinMode(_tx, OUTPUT);
    digitalWrite(_tx, muc ? HIGH : LOW);
    Serial.printf("[GIU] chan TX (GPIO %d) dang giu muc %s (%s).\n", _tx,
                  muc ? "CAO" : "THAP", muc ? "~3,3V" : "0V");
    Serial.println("      Do dau ra tuong ung cua HT245 so voi mass, roi go 'GIU' de tha.");
  }

  /**
   * ĐO MỨC NGHỈ CỦA CHÂN RX. Đường UART lúc không truyền gì phải nằm ở mức CAO.
   * Đây là phép đo rẻ nhất mà lại loại được phân nửa số nguyên nhân, nên làm TRƯỚC
   * khi ngồi dò baud hay đoán khung lệnh.
   *
   *   ~100% cao  -> dây tốt, bo đang nghỉ. Sang bước dò baud.
   *   ~0% cao    -> KHÔNG phải chuyện phần mềm. Hoặc chưa nối GND chung, hoặc HT245
   *                 đang bị vô hiệu (OE ở mức cao), hoặc bo chưa có điện, hoặc cắm
   *                 nhầm vào dây khác.
   *   lưng chừng -> hoặc bo đang truyền dữ liệu thật (tốt — chạy NGHE để xem), hoặc
   *                 chân đang thả nổi, chẳng nối vào đâu cả.
   *
   * ⚠️ Chỉ đo được điều gì đó có nghĩa khi mức trên dây nằm trong 0..3,3V. Dây 5V
   *    cắm thẳng vào chân ESP32 là quá áp — hỏng chân, mà lại hỏng âm thầm.
   */
  bool doMucNghi() {
    if (!_cong) return false;
    _cong->end();
    pinMode(_rx, INPUT);
    int cao = 0;
    const int LAN = 300;
    for (int i = 0; i < LAN; i++) { if (digitalRead(_rx) == HIGH) cao++; delay(1); }
    int pct = cao * 100 / LAN;
    Serial.printf("[DAY] chan RX (GPIO %d): %d%% thoi gian o muc CAO\n", _rx, pct);
    if (pct >= 95) Serial.println("      -> dung nhu mong doi (UART nghi o muc cao). Sang buoc DO baud.");
    else if (pct <= 5) Serial.println("      -> ⚠️ day nam o muc THAP suot. KHONG phai loi phan mem:\n"
                                      "         GND da chung chua? HT245 co bi vo hieu (OE muc cao) khong?\n"
                                      "         Bo da co dien chua? Co cam nham day khac khong?");
    else Serial.println("      -> day dang doi muc lien tuc: hoac bo DANG truyen that (chay NGHE de xem),\n"
                        "         hoac chan dang tha noi khong noi vao dau.");
    _moCong();
    return pct >= 95;
  }

  /**
   * TỰ KIỂM: nối tạm chân TX với chân RX của ESP32 bằng một đoạn dây, rồi chạy lệnh này.
   * Đọc lại đúng cái vừa gửi = phần UART của ESP32 và chân cắm trong code đều ĐÚNG,
   * lỗi nằm ngoài con chip. Đọc không ra = sai chân trong code, hoặc chân đã hỏng.
   * Nhờ vậy khỏi phải đoán "tại chip hay tại bo".
   */
  bool tuKiem(int soLan) {
    if (!_cong || soLan < 1) return false;
    static const uint8_t MAU[] = { 0x55, 0xAA, 0x00, 0xFF, 0x02, 0x03, 0x31, 0x7E };
    Serial.printf("[TUKIEM] chay %d lan @ %ld baud. Duong di phai khep kin: TX -> (day dang do) -> RX.\n",
                  soLan, _baud);
    int hong = 0, sai = 0; long tongByte = 0;
    uint8_t nhan[sizeof(MAU)];
    unsigned nCuoi = 0;
    for (int lan = 0; lan < soLan; lan++) {
      _xaCong();
      _cong->write(MAU, sizeof(MAU)); _cong->flush();
      unsigned n = 0;
      uint32_t het = millis() + 300;
      while ((int32_t)(het - millis()) > 0 && n < sizeof(MAU)) {
        while (_cong->available() && n < sizeof(MAU)) nhan[n++] = (uint8_t)_cong->read();
        delay(1);
      }
      nCuoi = n;
      if (n != sizeof(MAU)) hong++;
      else {
        int lech = 0;
        for (unsigned i = 0; i < sizeof(MAU); i++) if (nhan[i] != MAU[i]) lech++;
        if (lech) { hong++; sai += lech; }
      }
      tongByte += sizeof(MAU);
      if (soLan > 20 && (lan + 1) % 50 == 0)
        Serial.printf("      … %d/%d lan, hong %d\n", lan + 1, soLan, hong);
      delay(3);
    }
    Serial.print("[TUKIEM] gui  "); _inHang(MAU, sizeof(MAU));
    Serial.print("[TUKIEM] nhan "); if (nCuoi) _inHang(nhan, nCuoi); else Serial.println("(khong co gi)");
    Serial.printf("[TUKIEM] %d/%d lan hong, %d byte sai / %ld byte\n", hong, soLan, sai, tongByte);

    /* ⚠️ ĐỌC KẾT QUẢ — chỗ này mới là phần đáng tiền.
       Mức vào KHÔNG ĐỦ NGƯỠNG (đẩy 3,3V vào con 74HC chạy 5V) KHÔNG làm hỏng hẳn.
       Nó làm SAI THỈNH THOẢNG. Chạy một lần thấy đúng rồi kết luận "ổn" là đúng cái
       bẫy đó: lắp lên ghế xong, ấm máy lên vài độ là bắt đầu rớt lượt, mà lúc ấy
       không ai còn nghĩ tới chuyện điện áp nữa.
       Nên phép đo này phải chạy NHIỀU LẦN, và chỉ 100% mới được tính là đạt. */
    if (hong == 0) {
      Serial.printf("[TUKIEM] ✅ %d/%d lan dung tuyet doi.\n", soLan, soLan);
      if (soLan < 100) Serial.println("         Chay lai 'TUKIEM 200' truoc khi tin han — loi muc dien ap"
                                      " chi lo ra khi chay nhieu.");
      else Serial.println("         Duong tin hieu nay dung duoc.");
    } else if (hong == soLan && sai == 0) {
      Serial.println("[TUKIEM] ❌ KHONG nhan lai duoc gi ca. Day chua khep kin, sai so chan trong code,\n"
                     "         HT245 dang bi vo hieu (OE muc cao), hoac chua noi GND chung.");
    } else {
      Serial.printf("[TUKIEM] ⚠️ LUC DUOC LUC KHONG (%d%% so lan hong). Day KHONG phai loi phan mem.\n",
                    hong * 100 / soLan);
      Serial.println("         Gan nhu chac chan la MUC DIEN AP KHONG DU NGUONG:\n"
                     "         HT245 chay VCC 5V ma la loai 74HC thi doi muc vao >= 3,5V, chan ESP32\n"
                     "         chi xuat 3,3V. Phai nang muc 3,3V -> 5V cho chan TX (module level shifter,\n"
                     "         hoac chen mot con 74HCT245 lam dem trung gian).\n"
                     "         Loai HCT/LVC thi nguong chi 2,0V — neu dung loai do ma van hong thi soi\n"
                     "         lai GND chung va baud truoc.");
    }
    return hong == 0;
  }

  /* ==================================================================
   *  PHÒNG THÍ NGHIỆM — dùng khi CHƯA biết bo nói kiểu gì
   * ================================================================== */

  /**
   * NGHE LÉN: im lặng, chỉ in ra mọi byte bo gửi tới, dạng hex kèm chữ.
   * Dùng để xem bo có tự nói gì khi bật nguồn / khi bấm nút trên ghế không.
   */
  void nghe(uint32_t ms) {
    Serial.printf("[ICT] nghe %lu ms @ %ld baud — bam nut tren ghe / bat tat nguon bo de xem no noi gi\n",
                  (unsigned long)ms, _baud);
    uint32_t het = millis() + ms;
    uint8_t  hang[16]; int n = 0; uint32_t tong = 0;
    while ((int32_t)(het - millis()) > 0) {
      while (_cong->available()) {
        hang[n++] = (uint8_t)_cong->read(); tong++;
        if (n == 16) { _inHang(hang, n); n = 0; }
      }
      delay(2);
    }
    if (n) _inHang(hang, n);
    if (tong == 0) Serial.println("[ICT] … khong nhan duoc byte nao. Kiem GND, kiem TX/RX co bi dau thang hang khong, kiem baud.");
    else           Serial.printf("[ICT] nhan tong cong %lu byte\n", (unsigned long)tong);
  }

  /** BẮN THỬ chuỗi hex do thợ gõ, ví dụ "02 03 31 00 0F 3D 03". Rồi in cái bo trả về. */
  void banHex(const String& hex) {
    uint8_t d[ICT_DEM_TOI_DA]; int n = 0;
    /* ⚠️ PHẢI đòi HAI ký tự hex ĐỨNG LIỀN NHAU mới tính là một byte.
       Trước đây chỉ tìm ký tự hex đầu tiên rồi cắt 2 ký tự đưa cho strtol — mà a..f
       cũng là chữ cái thường, nên gõ nhầm một chữ tiếng Việt không dấu là hộp bắn
       byte rác sang bo ghế. Bài test ci/kiem-ict.sh bắt được: "khong-co-hex-nao"
       đẩy ra "0C 0E 0A". Bắn rác vào bo lúc đang dò tín hiệu là kiểu đánh lạc hướng
       tệ nhất — tưởng bo trả lời bậy, hoá ra mình gửi bậy. */
    int i = 0, dai = (int)hex.length();
    while (i < dai && n < ICT_DEM_TOI_DA) {
      if (!isHexadecimalDigit(hex[i]))                     { i++; continue; }
      if (i + 1 >= dai || !isHexadecimalDigit(hex[i + 1])) { i++; continue; }   // mẩu lẻ: bỏ
      d[n++] = (uint8_t)((_nibble(hex[i]) << 4) | _nibble(hex[i + 1]));
      i += 2;
    }
    if (n == 0) { Serial.println("[ICT] khong doc duoc byte hex nao"); return; }
    _xaCong();
    _cong->write(d, n); _cong->flush();
    Serial.print("[ICT] GUI  "); _inHang(d, n);
    _inTraLoi(ICT_CHO_TRA_LOI_MS);
  }

  /** BẮN THỬ một dòng chữ, tự thêm CR+LF. */
  void banChu(const String& s) {
    _xaCong();
    _cong->print(s); _cong->print("\r\n"); _cong->flush();
    Serial.println("[ICT] GUI  \"" + s + "\\r\\n\"");
    _inTraLoi(ICT_CHO_TRA_LOI_MS);
  }

  /**
   * DÒ BAUD. Thử lần lượt các tốc độ thông dụng: ở mỗi tốc độ vừa nghe (phòng khi
   * bo tự nói), vừa bắn thử một lệnh hỏi rồi chấm điểm cái nhận về.
   *
   * CHẤM ĐIỂM — sai baud thì vẫn nhận được byte, nhưng là byte RÁC. Phân biệt bằng:
   *   +3 mỗi byte khung (02 03 06 15)      -> đúng khung nhị phân
   *   +2 mỗi ký tự in được / CR / LF       -> đúng khung dòng chữ
   *   -2 mỗi byte 0x00 hoặc 0xFF           -> dấu hiệu kinh điển của sai baud
   * Trả về baud điểm cao nhất, hoặc 0 nếu chẳng tốc độ nào nghe ra gì.
   *
   * ⚠️ Dò xong ĐỪNG tin ngay. Đặt baud đó rồi chạy nghe() lần nữa: đọc ra chuỗi
   *    có nghĩa mới là đúng. Sai baud gấp/chia đôi (9600 và 19200) rất hay cho ra
   *    byte trông "gần đúng".
   */
  long doBaud(uint32_t msMoiBaud = 900) {
    static const long DS[] = { 9600, 19200, 38400, 115200, 4800, 57600, 2400, 1200 };
    long baudCu = _baud, tot = 0; int diemTot = 0;
    Serial.println("[ICT] === DO BAUD === (bam nut tren ghe trong luc do de bo chiu noi)");
    for (unsigned k = 0; k < sizeof(DS) / sizeof(DS[0]); k++) {
      _baud = DS[k]; _moCong(); delay(60); _xaCong();
      // Vừa nghe vừa chọc: bắn cả 2 kiểu hỏi, bo hiểu kiểu nào thì trả kiểu đó.
      uint8_t p[5] = { ICT_STX, 0x01, ICT_CMD_PING, 0x00, ICT_ETX };
      p[3] = (uint8_t)(p[1] ^ p[2]);          // CHK = XOR(LEN, CMD)
      _cong->write(p, 5); _cong->flush();
      _cong->print("PING\r\n"); _cong->flush();

      uint32_t het = millis() + msMoiBaud;
      uint8_t  dem[ICT_DEM_TOI_DA]; int n = 0; int diem = 0;
      while ((int32_t)(het - millis()) > 0) {
        while (_cong->available()) {
          uint8_t b = (uint8_t)_cong->read();
          if (n < ICT_DEM_TOI_DA) dem[n++] = b;
          if (b == ICT_STX || b == ICT_ETX || b == ICT_ACK || b == ICT_NAK) diem += 3;
          else if (b == '\r' || b == '\n' || (b >= 0x20 && b <= 0x7E))      diem += 2;
          else if (b == 0x00 || b == 0xFF)                                  diem -= 2;
        }
        delay(2);
      }
      Serial.printf("  %6ld baud: %2d byte, diem %3d  ", DS[k], n, diem);
      if (n) _inHang(dem, n > 16 ? 16 : n); else Serial.println("(im lang)");
      if (n > 0 && diem > diemTot) { diemTot = diem; tot = DS[k]; }
    }
    if (tot) Serial.printf("[ICT] => co ve la %ld baud (diem %d). Dat baud do roi chay 'NGHE' kiem lai.\n", tot, diemTot);
    else     Serial.println("[ICT] => KHONG tocdo nao nghe ra gi. Gan nhu chac la phan cung:\n"
                            "        - GND da noi chung chua?\n"
                            "        - TX cua ESP32 co dang cam vao RX cua bo khong (phai CHEO)?\n"
                            "        - Bo la RS232 (+-12V) ma cam thang? Phai co MAX3232.\n"
                            "        - Bo da co dien chua?");
    _baud = baudCu; _moCong();
    return tot;
  }

  /**
   * CẦU NỐI: nối thẳng cổng USB của ESP32 với bo ghế, ESP32 chỉ làm dây dẫn.
   * Nhờ vậy dùng được phần mềm soi cổng COM trên máy tính, hoặc chính phần mềm
   * của hãng, để bắt đúng khung lệnh mà bo hiểu.
   * ⚠️ Vào rồi thì KHÔNG RA — muốn thoát phải bấm nút reset trên bo ESP32.
   */
  void cauNoi() {
    Serial.println("\n[ICT] === CAU NOI USB <-> BO GHE ===");
    Serial.printf("      Bo ghe @ %ld baud. Moi thu go vao day di thang sang bo, va nguoc lai.\n", _baud);
    Serial.println("      Thoat: bam nut RESET tren bo ESP32.\n");
    for (;;) {
      while (Serial.available())  _cong->write((uint8_t)Serial.read());
      while (_cong->available())  Serial.write((uint8_t)_cong->read());
      delay(1);
    }
  }

private:
  HardwareSerial* _cong = nullptr;
  int      _rx = -1, _tx = -1;
  long     _baud = 9600;
  int      _oe = -1, _dir = -1;   // chân điều khiển HT245, -1 = không đấu tới
  uint8_t  _gom[ICT_DEM_TOI_DA];
  uint8_t  _gomN = 0;
  bool     _gomTran = false;
  uint32_t _byteCuoiMs = 0;
  KhungIct _hang[ICT_SO_KHUNG_NHO];
  uint8_t  _ghiTai = 0, _docTai = 0, _soChua = 0;
  uint8_t  _che  = ICT_CHE_NHI_PHAN;
  String   _lanCuoi = "(chua gui lenh nao)";

  void _moCong() {
    if (!_cong) return;
    _cong->end();
    delay(20);
    _cong->begin(_baud, SERIAL_8N1, _rx, _tx);
    _cong->setTimeout(50);
    _gomN = 0; _gomTran = false; _docTai = _ghiTai; _soChua = 0;
  }
  /* Vứt hết byte cũ CẢ trên dây LẪN trong bộ thu, trước khi gửi lệnh mới. Không xả
     thì cái "trả lời" đọc được rất có thể là đuôi của lần trước -> tưởng bo còn sống
     trong khi nó đã treo. */
  void _xaCong() {
    while (_cong && _cong->available()) _cong->read();
    _gomN = 0; _gomTran = false;
    _docTai = _ghiTai; _soChua = 0;
  }

  static uint8_t _nibble(char c) {
    if (c >= '0' && c <= '9') return (uint8_t)(c - '0');
    if (c >= 'a' && c <= 'f') return (uint8_t)(c - 'a' + 10);
    if (c >= 'A' && c <= 'F') return (uint8_t)(c - 'A' + 10);
    return 0;
  }

  void _inHang(const uint8_t* d, int n) {
    String h, c;
    for (int i = 0; i < n; i++) {
      char b[4]; snprintf(b, sizeof(b), "%02X ", d[i]); h += b;
      c += (d[i] >= 0x20 && d[i] <= 0x7E) ? (char)d[i] : '.';
    }
    Serial.println(h + " |" + c + "|");
  }

  void _inTraLoi(uint32_t choMs) {
    uint8_t dem[ICT_DEM_TOI_DA]; int n = 0;
    uint32_t het = millis() + choMs;
    while ((int32_t)(het - millis()) > 0) {
      while (_cong->available() && n < ICT_DEM_TOI_DA) { dem[n++] = (uint8_t)_cong->read(); het = millis() + 120; }
      delay(2);
    }
    if (n) { Serial.print("[ICT] NHAN "); _inHang(dem, n); }
    else     Serial.println("[ICT] NHAN (bo khong tra loi gi)");
  }

public:
  /* ---- BỘ THU CHẠY NỀN ----------------------------------------------------
     Gom byte liên tục, cắt khung theo khoảng lặng, xếp vào hàng đợi. Nhờ có nó,
     tin bo TỰ GỬI (không phải trả lời lệnh nào) cũng không bị rơi.

     ⚠️ TRƯỚC ĐÂY chỉ đọc dây ngay sau khi gửi lệnh, ngoài lúc đó thì không ai
        nghe. Bo có báo gì — hết giờ, kẹt cơ, có người bấm nút — cũng chỉ nằm
        trong bộ đệm rồi bị lệnh sau xả đi. Nhìn từ ngoài thì y như bo câm. */

  /** Gọi mỗi vòng loop(). Trả true khi vừa gom xong một khung mới. */
  bool chay() {
    if (!_cong) return false;
    while (_cong->available()) {
      uint8_t b = (uint8_t)_cong->read();
      if (_gomN < ICT_DEM_TOI_DA) _gom[_gomN++] = b;
      else                        _gomTran = true;
      _byteCuoiMs = millis();
    }
    if (_gomN > 0 && (uint32_t)(millis() - _byteCuoiMs) >= ICT_NGHI_MS) { _chotKhung(); return true; }
    return false;
  }
  bool coKhungMoi() const { return _soChua > 0; }
  /** Lấy khung cũ nhất chưa ai đọc. Gọi khi coKhungMoi() == true. */
  KhungIct layKhung() {
    KhungIct k = _hang[_docTai];
    _docTai = (uint8_t)((_docTai + 1) % ICT_SO_KHUNG_NHO);
    if (_soChua) _soChua--;
    return k;
  }
  /** Mô tả một khung thành "hex |chữ|" để in ra log hoặc portal. */
  static String moTaKhung(const KhungIct& k) {
    String hex, chu;
    for (uint8_t i = 0; i < k.n; i++) {
      char b[4]; snprintf(b, sizeof(b), "%02X ", k.d[i]); hex += b;
      chu += (k.d[i] >= 0x20 && k.d[i] <= 0x7E) ? (char)k.d[i] : '.';
    }
    if (!k.n) return String("(rong)");
    return hex + "|" + chu + "|" + (k.tran ? " (DAI QUA CO, da cat)" : "");
  }

  /** Khung này có phải lời NHẬN không: 1 = nhận, -1 = từ chối, 0 = không rõ. */
  int doanKetQua(const KhungIct& k) const {
    bool nhan = false, tuChoi = false;
    if (_che == ICT_CHE_DONG_CHU) {
      String t;
      for (uint8_t i = 0; i < k.n; i++) t += (k.d[i] >= 0x20 && k.d[i] <= 0x7E) ? (char)k.d[i] : ' ';
      t.toUpperCase();
      nhan   = (t.indexOf("OK")  >= 0 || t.indexOf("ACK") >= 0);
      tuChoi = (t.indexOf("ERR") >= 0 || t.indexOf("NAK") >= 0);
    } else {
      for (uint8_t i = 0; i < k.n; i++) { if (k.d[i] == ICT_ACK) nhan = true; if (k.d[i] == ICT_NAK) tuChoi = true; }
    }
    if (tuChoi) return -1;
    return nhan ? 1 : 0;
  }

private:
  void _chotKhung() {
    KhungIct& k = _hang[_ghiTai];
    k.n = _gomN; k.luc = millis(); k.tran = _gomTran;
    memcpy(k.d, _gom, _gomN);
    _ghiTai = (uint8_t)((_ghiTai + 1) % ICT_SO_KHUNG_NHO);
    if (_soChua < ICT_SO_KHUNG_NHO) _soChua++;
    else _docTai = _ghiTai;          // hàng đầy: khung cũ nhất bị đẩy ra
    _gomN = 0; _gomTran = false;
  }
  /** Chờ một khung trả lời trong choMs. Vẫn chạy bộ thu nên không bỏ sót byte nào. */
  bool _choKhung(uint32_t choMs, KhungIct* ra) {
    uint32_t het = millis() + choMs;
    while ((int32_t)(het - millis()) > 0) {
      chay();
      if (_soChua) { *ra = layKhung(); return true; }
      delay(1);
    }
    return false;
  }
  /** Gửi xong thì chờ trả lời. Ghi lại lần trao đổi để hiện lên portal. */
  bool _docTraLoi(const String& daGui) {
    KhungIct k;
    bool co = _choKhung(ICT_CHO_TRA_LOI_MS, &k);
    int  kq = co ? doanKetQua(k) : 0;
    _lanCuoi = daGui + " → " + (co ? moTaKhung(k) : String("(im lang)")) +
               (kq > 0 ? "  [BO NHAN]" : kq < 0 ? "  [BO TU CHOI]" : "  [KHONG TRA LOI]");
    if (amThanh) Serial.println("[ICT] " + _lanCuoi);
    return kq > 0;
  }

  bool _guiKhung(uint8_t cmd, const uint8_t* data, uint8_t len) {
    uint8_t k[ICT_DEM_TOI_DA]; int n = 0;
    k[n++] = ICT_STX;
    k[n++] = (uint8_t)(len + 1);                 // LEN đếm cả CMD
    k[n++] = cmd;
    for (uint8_t i = 0; i < len; i++) k[n++] = data[i];
    uint8_t chk = 0;
    for (int i = 1; i < n; i++) chk ^= k[i];     // XOR từ LEN tới hết DATA
    k[n++] = chk;
    k[n++] = ICT_ETX;

    String hex;
    for (int i = 0; i < n; i++) { char b[4]; snprintf(b, sizeof(b), "%02X ", k[i]); hex += b; }
    for (int lan = 1; lan <= ICT_SO_LAN_GUI; lan++) {
      _xaCong();
      _cong->write(k, n); _cong->flush();
      if (_docTraLoi("GUI " + hex)) return true;
      if (lan < ICT_SO_LAN_GUI) { Serial.printf("[ICT] lan %d khong xong -> gui lai\n", lan); delay(120); }
    }
    return false;
  }

  bool _guiDong(const String& s) {
    for (int lan = 1; lan <= ICT_SO_LAN_GUI; lan++) {
      _xaCong();
      _cong->print(s); _cong->print("\r\n"); _cong->flush();
      if (_docTraLoi("GUI \"" + s + "\"")) return true;
      if (lan < ICT_SO_LAN_GUI) { Serial.printf("[ICT] lan %d khong xong -> gui lai\n", lan); delay(120); }
    }
    return false;
  }
};
