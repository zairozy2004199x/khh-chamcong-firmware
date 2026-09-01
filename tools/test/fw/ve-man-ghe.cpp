/* =============================================================================================
 * BỘ GIẢ TFT_eSPI ĐỦ ĐỂ DỰNG MÀN CHỌN GÓI CỦA GHẾ RA ẢNH.
 *
 * Chỉ có những phương thức mà `drawIdle()` / `veTheGoi()` thật sự gọi. Thêm phương thức chỉ vì
 * "biết đâu cần" là mở đường cho bản giả trôi xa khỏi bản thật.
 *
 * ⚠️ `textWidth()` ở đây là ƯỚC LƯỢNG, không phải bảng font thật của TFT_eSPI. Nó quyết định
 *    firmware chọn font nào và chuỗi tiêu đề nào, nên ước lượng RỘNG HƠN thực tế một chút —
 *    lệch về phía "tưởng chật hơn thật" thì cùng lắm ảnh cho ra bản chữ ngắn hơn màn thật, còn
 *    lệch về phía kia là ảnh khoe một bố cục vừa vặn trong khi ghế thật thì tràn.
 * ============================================================================================= */
#include <cstdio>
#include <cstdint>
#include <cstring>
#include <string>
#include <vector>
#include <algorithm>

struct String : std::string {
  String() {}
  String(const char* s) : std::string(s ? s : "") {}
  String(const std::string& s) : std::string(s) {}
  String(int v) : std::string(std::to_string(v)) {}
  String(long v) : std::string(std::to_string(v)) {}
  int length() const { return (int) size(); }
  bool endsWith(const char* n) const { size_t l = strlen(n); return size() >= l && compare(size()-l, l, n) == 0; }
  void remove(int i) { if (i >= 0 && i < (int) size()) erase(i); }
  const char* c_str_() const { return c_str(); }
};
static String operator+(const String& a, const String& b) { return String(std::string(a) + std::string(b)); }
static String operator+(const char* a, const String& b)   { return String(std::string(a) + std::string(b)); }
static String operator+(const String& a, const char* b)   { return String(std::string(a) + std::string(b)); }

/* Datum của TFT_eSPI — chỉ những cái được dùng. */
enum { TL_DATUM = 0, TC_DATUM = 1, TR_DATUM = 2, MC_DATUM = 4 };
#define TFT_WHITE  0xFFFF
#define TFT_YELLOW 0xFFE0
#define TFT_GREEN  0x07E0
#define TFT_RED    0xF800
#define COL_ACC    COL_VANG

static const int W = 320, H = 240;
static uint16_t FB[W * H];

/* Font 5×7 rút gọn: chữ HOA, số, và mấy dấu firmware dùng. Ký tự lạ vẽ thành ô đặc — thấy ngay
   là bộ giả thiếu chữ chứ không lặng lẽ bỏ qua. */
static const uint8_t F57[][5] = {
 {0x00,0x00,0x00,0x00,0x00}, // ' '
 {0x7E,0x11,0x11,0x11,0x7E}, // A
 {0x7F,0x49,0x49,0x49,0x36}, // B
 {0x3E,0x41,0x41,0x41,0x22}, // C
 {0x7F,0x41,0x41,0x22,0x1C}, // D
 {0x7F,0x49,0x49,0x49,0x41}, // E
 {0x7F,0x09,0x09,0x09,0x01}, // F
 {0x3E,0x41,0x49,0x49,0x7A}, // G
 {0x7F,0x08,0x08,0x08,0x7F}, // H
 {0x00,0x41,0x7F,0x41,0x00}, // I
 {0x20,0x40,0x41,0x3F,0x01}, // J
 {0x7F,0x08,0x14,0x22,0x41}, // K
 {0x7F,0x40,0x40,0x40,0x40}, // L
 {0x7F,0x02,0x0C,0x02,0x7F}, // M
 {0x7F,0x04,0x08,0x10,0x7F}, // N
 {0x3E,0x41,0x41,0x41,0x3E}, // O
 {0x7F,0x09,0x09,0x09,0x06}, // P
 {0x3E,0x41,0x51,0x21,0x5E}, // Q
 {0x7F,0x09,0x19,0x29,0x46}, // R
 {0x46,0x49,0x49,0x49,0x31}, // S
 {0x01,0x01,0x7F,0x01,0x01}, // T
 {0x3F,0x40,0x40,0x40,0x3F}, // U
 {0x1F,0x20,0x40,0x20,0x1F}, // V
 {0x3F,0x40,0x38,0x40,0x3F}, // W
 {0x63,0x14,0x08,0x14,0x63}, // X
 {0x07,0x08,0x70,0x08,0x07}, // Y
 {0x61,0x51,0x49,0x45,0x43}, // Z
 {0x3E,0x51,0x49,0x45,0x3E}, // 0
 {0x00,0x42,0x7F,0x40,0x00}, // 1
 {0x42,0x61,0x51,0x49,0x46}, // 2
 {0x21,0x41,0x45,0x4B,0x31}, // 3
 {0x18,0x14,0x12,0x7F,0x10}, // 4
 {0x27,0x45,0x45,0x45,0x39}, // 5
 {0x3C,0x4A,0x49,0x49,0x30}, // 6
 {0x01,0x71,0x09,0x05,0x03}, // 7
 {0x36,0x49,0x49,0x49,0x36}, // 8
 {0x06,0x49,0x49,0x29,0x1E}, // 9
 {0x00,0x60,0x60,0x00,0x00}, // .
 {0x08,0x08,0x08,0x08,0x08}, // -
 {0x00,0x36,0x36,0x00,0x00}, // :
 {0x00,0x00,0x00,0x00,0x00}, // (  vẽ rỗng, chỉ chiếm chỗ
 {0x00,0x00,0x00,0x00,0x00}, // )
 {0x20,0x10,0x08,0x04,0x02}, // /
 {0x14,0x7F,0x14,0x7F,0x14}, // %
 {0x08,0x2A,0x1C,0x2A,0x08}, // *
};
static int chiSo(char c){
  if (c == ' ') return 0;
  if (c >= 'A' && c <= 'Z') return 1 + (c - 'A');
  if (c >= 'a' && c <= 'z') return 1 + (c - 'a');
  if (c >= '0' && c <= '9') return 27 + (c - '0');
  switch (c) { case '.': return 37; case '-': return 38; case ':': return 39;
               case '(': return 40; case ')': return 41; case '/': return 42;
               case '%': return 43; case '*': return 44; }
  return -1;
}

struct Tft {
  int datum = TL_DATUM; uint16_t fg = 0xFFFF, bg = 0; int tsize = 1;

  void cham(int x, int y, uint16_t c){ if (x >= 0 && y >= 0 && x < W && y < H) FB[y*W + x] = c; }
  void fillScreen(uint16_t c){ for (int i = 0; i < W*H; i++) FB[i] = c; }
  void fillRect(int x, int y, int w, int h, uint16_t c){
    for (int j = y; j < y+h; j++) for (int i = x; i < x+w; i++) cham(i, j, c);
  }
  void drawFastHLine(int x, int y, int w, uint16_t c){ for (int i = x; i < x+w; i++) cham(i, y, c); }
  void drawRect(int x, int y, int w, int h, uint16_t c){
    drawFastHLine(x, y, w, c); drawFastHLine(x, y+h-1, w, c);
    for (int j = y; j < y+h; j++) { cham(x, j, c); cham(x+w-1, j, c); }
  }
  /* Góc bo: đủ để nhìn ra dáng bo, không cần đúng thuật toán của thư viện. */
  void fillRoundRect(int x, int y, int w, int h, int r, uint16_t c){
    fillRect(x, y, w, h, c);
    for (int i = 0; i < r; i++) for (int j = 0; j < r; j++)
      if ((r-i)*(r-i) + (r-j)*(r-j) > r*r + r) {
        cham(x+i, y+j, FB[0]); cham(x+w-1-i, y+j, FB[0]);
        cham(x+i, y+h-1-j, FB[0]); cham(x+w-1-i, y+h-1-j, FB[0]);
      }
  }
  void drawRoundRect(int x, int y, int w, int h, int r, uint16_t c){
    drawFastHLine(x+r, y, w-2*r, c); drawFastHLine(x+r, y+h-1, w-2*r, c);
    for (int j = y+r; j < y+h-r; j++) { cham(x, j, c); cham(x+w-1, j, c); }
    for (int i = 0; i < r; i++) for (int j = 0; j < r; j++) {
      int d = (r-i)*(r-i) + (r-j)*(r-j);
      if (d <= r*r + r && d >= (r-1)*(r-1)) {
        cham(x+i, y+j, c); cham(x+w-1-i, y+j, c);
        cham(x+i, y+h-1-j, c); cham(x+w-1-i, y+h-1-j, c);
      }
    }
  }
  void fillTriangle(int x0,int y0,int x1,int y1,int x2,int y2,uint16_t c){
    int miny = std::min({y0,y1,y2}), maxy = std::max({y0,y1,y2});
    int minx = std::min({x0,x1,x2}), maxx = std::max({x0,x1,x2});
    for (int y = miny; y <= maxy; y++) for (int x = minx; x <= maxx; x++) {
      auto sg = [](int ax,int ay,int bx,int by,int cx,int cy){ return (ax-cx)*(by-cy)-(bx-cx)*(ay-cy); };
      int d1 = sg(x,y,x0,y0,x1,y1), d2 = sg(x,y,x1,y1,x2,y2), d3 = sg(x,y,x2,y2,x0,y0);
      bool am = (d1<0)||(d2<0)||(d3<0), duong = (d1>0)||(d2>0)||(d3>0);
      if (!(am && duong)) cham(x, y, c);
    }
  }
  void setTextDatum(int d){ datum = d; }
  void setTextColor(uint16_t f, uint16_t b){ fg = f; bg = b; }
  void setTextSize(int s){ tsize = s; }

  /* Chiều cao / chiều rộng mỗi ký tự theo số hiệu font của TFT_eSPI. */
  static int caoFont(int f){ return f == 1 ? 8 : f == 2 ? 16 : f == 4 ? 26 : 8; }
  static int rongKy(int f){ return f == 1 ? 6 : f == 2 ? 12 : f == 4 ? 19 : 6; }

  int textWidth(const String& s, int f){ return (int) s.size() * rongKy(f) * tsize; }
  int textWidth(const char* s, int f){ return (int) strlen(s) * rongKy(f) * tsize; }

  void drawString(const String& s, int x, int y, int f){ ve(s, x, y, f); }
  void drawString(const char* s, int x, int y, int f){ ve(String(s), x, y, f); }

  void ve(const String& s, int x, int y, int f){
    int rong = textWidth(s, f), cao = caoFont(f) * tsize;
    int x0 = x, y0 = y;
    if (datum == TC_DATUM) x0 = x - rong/2;
    else if (datum == TR_DATUM) x0 = x - rong;
    else if (datum == MC_DATUM) { x0 = x - rong/2; y0 = y - cao/2; }
    int px = rongKy(f) * tsize, sc = std::max(1, cao / 8);
    for (size_t k = 0; k < s.size(); k++) {
      int ci = chiSo(s[k]);
      int bx = x0 + (int) k * px;
      if (ci < 0) { fillRect(bx, y0, px-1, cao, fg); continue; }
      for (int col = 0; col < 5; col++) for (int row = 0; row < 7; row++)
        if (F57[ci][col] & (1 << row))
          for (int a = 0; a < sc; a++) for (int b2 = 0; b2 < sc; b2++)
            cham(bx + col*sc + a, y0 + row*sc + b2, fg);
    }
  }
};
static Tft tft;

/* ---- phần firmware cần mà không thuộc lớp vẽ ---- */
struct Btn { int x, y, w, h; };
static const int PKG_MAX = 4;
int    PKG_N = 4;
long   PKG_AMT[PKG_MAX]  = { 20000, 50000, 100000, 200000 };
String PKG_TEN[PKG_MAX]  = { "GOI CO BAN", "GOI PHO BIEN", "GOI CHUYEN SAU", "GOI THUONG HANG" };
int    PKG_PHUT[PKG_MAX] = { 0, 0, 0, 0 };
String PKG_MOTA[PKG_MAX] = { "", "", "", "" };
int    PKG_VIP[PKG_MAX]  = { 0, 0, 0, 1 };
int    QC_O = -1, QC_GIAM = 0;
bool   g_qcMat = false;
String CHAIR_ID = "AMTP01";
bool   CHUA_GAN = false;
int    MINUTES = 6;
long   PRICE_VND = 20000;
static bool G_NET = true;
bool netUp(){ return G_NET; }
bool duNhanTien(){ return true; }
String macBo(){ return String("A0:B7:65:12:34:56"); }
void veTheQuangCao(int){}
void veManChuaCoTk(){}

#include "trich.inc"

/* =============================================================================================
 * KIỂM TỰ ĐỘNG: KHÔNG CHỮ NÀO ĐƯỢC TRÀN RA KHỎI Ô CỦA NÓ.
 *
 * 🔴 Đây là lớp lỗi đã cắn BA LẦN trên ghế thật, và lần nào cũng phải chờ anh Thắng chụp ảnh
 *    mới biết: tiêu đề đè lên mã ghế; câu "(cham de mua goi nhu thuong)" tràn 9px mỗi bên; nhãn
 *    VVIP ngồi lên viền ô bên trên. Cả ba đều cùng một cơ chế — phần tràn nằm NGOÀI vùng
 *    `fillRoundRect`/`fillRect` của lượt vẽ sau, nên nó không bao giờ bị xoá: một vệt chữ cũ
 *    nằm lại trên màn cho tới khi ai đó rút điện.
 *
 * Cách kiểm: vẽ màn với dữ liệu XẤU NHẤT mà máy chủ có thể gửi xuống (tên gói dài, số tiền
 * nhiều chữ số), rồi soi hai dải KHE giữa các ô và dải mép màn. Ở đó chỉ được phép có màu nền
 * hoặc màu quầng sáng; thấy màu CHỮ là có cái gì đó đã tràn ra.
 * ============================================================================================= */
static bool laMauChu(uint16_t c){
  return c == COL_SO || c == 0xFFFF || c == COL_PHU || c == COL_VANG || c == COL_MO;
}
static int demChuTrongVung(int x0, int y0, int x1, int y1){
  int n = 0;
  for (int y = y0; y <= y1; y++) for (int x = x0; x <= x1; x++)
    if (laMauChu(FB[y*W + x])) n++;
  return n;
}
static int G_DAT = 0, G_HONG = 0;
static void kt(const char* ten, int demDuoc){
  if (demDuoc == 0) { G_DAT++; return; }
  G_HONG++;
  printf("  X %s — %d diem mau chu nam trong vung cam\n", ten, demDuoc);
}
static void chayKiem(){
  struct Canh { const char* ten; long amt[4]; const char* tenGoi; int phut; };
  Canh cs[] = {
    { "bang gia thuong",   {20000,50000,100000,200000}, "GOI CO BAN",                 6 },
    { "tien 7 chu so",     {1000000,2000000,5000000,9000000}, "GOI CO BAN",           6 },
    { "ten goi rat dai",   {20000,50000,100000,200000}, "GOI CHUYEN SAU THUONG HANG", 6 },
    { "phut 3 chu so",     {20000,50000,100000,200000}, "GOI CO BAN",                 120 },
  };
  for (auto& c : cs) {
    for (int i = 0; i < 4; i++) { PKG_AMT[i] = c.amt[i]; PKG_TEN[i] = String(c.tenGoi); PKG_PHUT[i] = c.phut; }
    drawIdle();
    char ten[128];
    /* Khe dọc giữa hai cột: x 158..161 (ô trái hết ở 156+quầng 2, ô phải bắt đầu 164-quầng 2). */
    snprintf(ten, sizeof ten, "[%s] khe doc giua hai cot", c.ten);
    kt(ten, demChuTrongVung(158, 36, 161, 224));
    /* Khe ngang giữa hai hàng: y 128..129 — hai hàng nền TRỐNG giữa quầng ô trên (hết ở 127)
       và quầng ô dưới (bắt đầu 130). Xem khối 🔴 ở khai báo PKG_BTN. */
    snprintf(ten, sizeof ten, "[%s] khe ngang giua hai hang", c.ten);
    kt(ten, demChuTrongVung(0, 128, 319, 129));
    /* Mép trái/phải màn ngoài lưới: x 0..3 và 316..319, trong vùng lưới. */
    snprintf(ten, sizeof ten, "[%s] mep trai man", c.ten);
    kt(ten, demChuTrongVung(0, 36, 3, 223));
    snprintf(ten, sizeof ten, "[%s] mep phai man", c.ten);
    kt(ten, demChuTrongVung(316, 36, 319, 223));
    /* Dải phụ đề: chữ vàng của phụ đề nằm ở giữa; hai đầu (x<14, x>306) phải sạch. */
    snprintf(ten, sizeof ten, "[%s] hai dau dai phu de", c.ten);
    kt(ten, demChuTrongVung(0, 20, 13, 34) + demChuTrongVung(307, 20, 319, 34));
    /* 🔴 GẠCH TRANG TRÍ KHÔNG ĐƯỢC CHẠM VÀO CHỮ PHỤ ĐỀ.
       Bản đầu của bài kiểm chỉ soi hai ĐẦU dải, nên một cái gạch bò vào giữa vẫn lọt — phá thử
       ca "gach lan sau vao chu phu de" ra XANH. Nay gạch mang màu riêng (COL_VANG2) nên đo
       được: tìm cột phải nhất của nét trang trí bên trái và cột trái nhất của chữ, đòi còn ít
       nhất 4 cột nền ở giữa. */
    {
      int metPhaiGach = -1, metTraiChu = W;
      for (int y = 20; y <= 34; y++) for (int x = 0; x < 160; x++) {
        uint16_t px = FB[y*W + x];
        if (px == COL_VANG2 && x > metPhaiGach) metPhaiGach = x;
        if (px == COL_SO && x < metTraiChu)     metTraiChu = x;
      }
      snprintf(ten, sizeof ten, "[%s] gach trang tri khong cham chu phu de", c.ten);
      kt(ten, (metPhaiGach >= 0 && metTraiChu < W && metTraiChu - metPhaiGach < 4) ? 1 : 0);
    }
  }
  if (G_HONG) printf("\n  HONG: %d | DAT: %d\n", G_HONG, G_DAT);
  else        printf("\n  SACH — %d phep dung man ghe\n", G_DAT);
}

int main(int argc, char** argv){
  if (argc > 1 && strcmp(argv[1], "--kiem") == 0) { chayKiem(); return G_HONG ? 1 : 0; }
  drawIdle();
  const char* ra = argc > 1 ? argv[1] : "man.ppm";
  FILE* f = fopen(ra, "wb");
  fprintf(f, "P6\n%d %d\n255\n", W, H);
  for (int i = 0; i < W*H; i++) {
    uint16_t c = FB[i];
    uint8_t r = ((c >> 11) & 0x1F) << 3, g = ((c >> 5) & 0x3F) << 2, b = (c & 0x1F) << 3;
    fputc(r, f); fputc(g, f); fputc(b, f);
  }
  fclose(f);
  return 0;
}
