/* ============================================================================
 *  GHẾ QR — Waveshare ESP32-S3-Touch-LCD-2.8B (480×640, ST7701 RGB)
 *  ⚠️ BƯỚC 2 — STAGE B: LỚP VẼ + CHỌN GÓI BẰNG CẢM ỨNG (chưa QR/tiền/mạng).
 * ----------------------------------------------------------------------------
 *  Nền: driver ST7701 RGB gốc Waveshare (Display_ST7701 + TCA9554 + I2C).
 *  Lớp vẽ (rect/text/bo góc/tiền) bê từ bản _p4 (vẽ thẳng framebuffer), đổi sang
 *  đẩy buffer qua LCD_addWindow. Font 5×7 khử răng cưa (font_ascii.h).
 *  Cảm ứng CST328 (touch_cst328.h) — poll I2C, lấy điểm đầu, dò trúng ô.
 *
 *  KẾT QUẢ: chạm 1 gói ở lưới 2×2 -> sang màn "ĐÃ CHỌN GÓI" (thẻ to + nút
 *  QUAY LAI). Chứng minh cả vẽ lẫn cảm ứng (dò trúng ô) chạy trên panel thật.
 *  Bước sau: hiện QR thanh toán -> cổng tiền ICT/4G/NVS/chốt offline (bê _p4).
 * ========================================================================== */
#include <Arduino.h>
#include <math.h>
#include "I2C_Driver.h"
#include "TCA9554PWR.h"
#include "Display_ST7701.h"
#include "font_ascii.h"
#include "touch_cst328.h"

// ───────────────────────────── KHUNG MÀN + FRAMEBUFFER ──────────────────────
#define LW 480
#define LH 640
#define RGB565(r,g,b) ((uint16_t)((((r)&0xF8)<<8)|(((g)&0xFC)<<3)|((b)>>3)))

static uint16_t* g_fb = nullptr;      // framebuffer của mình (PSRAM), đẩy qua LCD_addWindow

bool veInit(){
  g_fb = (uint16_t*)heap_caps_malloc((size_t)LW * LH * 2, MALLOC_CAP_SPIRAM);
  return g_fb != nullptr;
}
void veFlush(){ if(g_fb) LCD_addWindow(0, 0, LW - 1, LH - 1, (uint8_t*)g_fb); }

static inline void lpx(int x, int y, uint16_t c){
  if(x < 0 || x >= LW || y < 0 || y >= LH || !g_fb) return;
  g_fb[y * LW + x] = c;              // dọc, không xoay
}
static void lRect(int x, int y, int w, int h, uint16_t c){
  for(int j = 0; j < h; j++) for(int i = 0; i < w; i++) lpx(x + i, y + j, c);
}
static void lFill(uint16_t c){ lRect(0, 0, LW, LH, c); }

// ───────────────────────────── KHỬ RĂNG CƯA + BO GÓC ────────────────────────
static inline uint16_t blend565(uint16_t fg, uint16_t bg, uint8_t a){
  uint16_t fr=(fg>>11)&0x1F, fgc=(fg>>5)&0x3F, fbl=fg&0x1F;
  uint16_t br=(bg>>11)&0x1F, bgc=(bg>>5)&0x3F, bbl=bg&0x1F;
  uint16_t r=(fr*a+br*(255-a)+127)/255, g=(fgc*a+bgc*(255-a)+127)/255, b=(fbl*a+bbl*(255-a)+127)/255;
  return (uint16_t)((r<<11)|(g<<5)|b);
}
static void lRoundRectA(int x, int y, int w, int h, int r, uint16_t c, uint16_t bg){
  if(r*2>w) r=w/2; if(r*2>h) r=h/2;
  if(r<1){ lRect(x,y,w,h,c); return; }
  lRect(x+r, y, w-2*r, h, c);
  lRect(x, y+r, r, h-2*r, c);
  lRect(x+w-r, y+r, r, h-2*r, c);
  for(int cn=0; cn<4; cn++){
    int ox=(cn&1)?(x+w-r):x, oy=(cn&2)?(y+h-r):y;
    float ccx=(cn&1)?(float)(x+w-r):(float)(x+r), ccy=(cn&2)?(float)(y+h-r):(float)(y+r);
    for(int j=0;j<r;j++) for(int i=0;i<r;i++){
      float dx=(ox+i+0.5f)-ccx, dy=(oy+j+0.5f)-ccy;
      float cov=(float)r-sqrtf(dx*dx+dy*dy)+0.5f;
      if(cov<=0) continue; if(cov>1) cov=1;
      uint8_t a=(uint8_t)(cov*255);
      lpx(ox+i, oy+j, a>=255?c:blend565(c,bg,a));
    }
  }
}

// ───────────────────────────── FONT (5×7 khử răng cưa) ──────────────────────
static inline float _fbit(const uint8_t g[7], int cx, int cy){
  if(cx<0||cx>4||cy<0||cy>6) return 0.0f;
  return ((g[cy]>>(4-cx))&1)?1.0f:0.0f;
}
static void lBitmap(int x, int y, const uint8_t g[7], int sc, uint16_t c, uint16_t bg){
  int W=5*sc, H=7*sc;
  for(int j=0;j<H;j++) for(int i=0;i<W;i++){
    float u=(i+0.5f)/sc-0.5f, v=(j+0.5f)/sc-0.5f;
    int u0=(int)floorf(u), v0=(int)floorf(v);
    float fu=u-u0, fv=v-v0;
    float top=_fbit(g,u0,v0)*(1-fu)+_fbit(g,u0+1,v0)*fu;
    float bot=_fbit(g,u0,v0+1)*(1-fu)+_fbit(g,u0+1,v0+1)*fu;
    float cov=top*(1-fv)+bot*fv;
    cov=(cov-0.5f)*1.7f+0.5f;
    if(cov<=0.02f) continue; if(cov>1) cov=1;
    uint8_t a=(uint8_t)(cov*255);
    lpx(x+i, y+j, a>=250?c:blend565(c,bg,a));
  }
}
#define CHAR_W(sc) (6*(sc))
static int lTextW(const char* s, int sc){ return (int)strlen(s)*CHAR_W(sc); }
static void lText(int x, int y, const char* s, int sc, uint16_t c, uint16_t bg){
  int xx=x; for(const char* p=s; *p; p++){ lBitmap(xx,y,glyph7(*p),sc,c,bg); xx+=CHAR_W(sc); }
}
static void lTextC(int cx, int y, const char* s, int sc, uint16_t c, uint16_t bg){ lText(cx-lTextW(s,sc)/2, y, s, sc, c, bg); }
static void lTextR(int rx, int y, const char* s, int sc, uint16_t c, uint16_t bg){ lText(rx-lTextW(s,sc), y, s, sc, c, bg); }
static void _tienStr(long v, char* out, size_t cap){
  char s[16]; int n=snprintf(s,sizeof s,"%ld",v); int o=0;
  for(int i=0;i<n && o<(int)cap-2;i++){ out[o++]=s[i]; int rem=n-1-i; if(rem>0 && rem%3==0) out[o++]='.'; }
  out[o]=0;
}
static int lMoneyW(long v, int sc){ char t[24]; _tienStr(v,t,sizeof t); return lTextW(t,sc)+sc+CHAR_W(sc); }
static int lMoney(int x, int y, long v, int sc, uint16_t c, uint16_t bg){
  char t[24]; _tienStr(v,t,sizeof t); lText(x,y,t,sc,c,bg);
  int xx=x+lTextW(t,sc)+sc; lBitmap(xx,y,GLYPH_DD,sc,c,bg); return xx+CHAR_W(sc)-x;
}
static void lMoneyC(int cx, int y, long v, int sc, uint16_t c, uint16_t bg){ lMoney(cx-lMoneyW(v,sc)/2, y, v, sc, c, bg); }

// ───────────────────────────── MÀU ─────────────────────────────────────────
#define C_BG    RGB565(0x0C,0x22,0x3A)
#define C_BAR   RGB565(0x15,0x42,0x57)
#define C_TOP   RGB565(0x2C,0x7E,0x8E)
#define C_BOT   RGB565(0x16,0x4A,0x5E)
#define C_SHD   RGB565(0x05,0x12,0x20)
#define C_GLOW  RGB565(0x6C,0xE6,0xF2)
#define C_WHITE RGB565(0xFF,0xFF,0xFF)
#define C_PHU   RGB565(0xAE,0xD8,0xE8)
#define C_ID    RGB565(0x5A,0xD8,0x88)
#define C_YEL   RGB565(0xF4,0xC8,0x54)

// ───────────────────────────── GÓI DỊCH VỤ (tạm) ───────────────────────────
struct Goi { const char* ten; int phut; long tien; };
static const Goi GOI[4] = {
  { "THUONG",   15, 20000 },
  { "VIP",      30, 40000 },
  { "CAO CAP",  45, 60000 },
  { "DAC BIET", 60, 80000 },
};

// Hình học lưới 2×2 (dùng chung cho vẽ + dò chạm).
static const int GX[2] = { 22, 250 };
static const int GY[2] = { 110, 350 };
static const int TW = 208, TH = 216;   // hàng dưới kết thúc y=566, tách thanh chân trang (y=584)

// Ô nào chứa điểm (tx,ty)? -1 nếu không trúng ô nào.
static int hitGoi(int tx, int ty){
  for(int r = 0; r < 2; r++) for(int c = 0; c < 2; c++){
    int x = GX[c], y = GY[r];
    if(tx >= x && tx < x + TW && ty >= y && ty < y + TH) return r * 2 + c;
  }
  return -1;
}

// Một thẻ gói (bo góc, tên trên, tiền vàng giữa, phút dưới).
static void veTheGoi(int i, int x, int y, int w, int h){
  lRoundRectA(x+3, y+6, w, h, 14, C_SHD, C_BG);         // bóng đổ
  lRoundRectA(x, y, w, h, 14, C_TOP, C_BG);             // thân thẻ
  lRoundRectA(x+2, y+h/2, w-4, h/2-2, 12, C_BOT, C_TOP);// nửa dưới tối hơn
  int cx = x + w/2;
  lTextC(cx, y+18, GOI[i].ten, 3, C_WHITE, C_TOP);       // tên gói
  lMoneyC(cx, y+h/2-18, GOI[i].tien, 4, C_YEL, C_BOT);   // tiền (vàng, to)
  char p[16]; snprintf(p, sizeof p, "%d PHUT", GOI[i].phut);
  lTextC(cx, y+h-40, p, 2, C_PHU, C_BOT);                // phút
}

// Thanh chẩn đoán cảm ứng — TO, RÕ, nguyên dải (dưới tiêu đề).
// XANH = thấy 0x1A (touch sống), ĐỎ = không thấy 1A.
static void drawTPStatus(){
  uint16_t bar = TP_OK ? RGB565(0x1E,0x8E,0x3E) : RGB565(0xC0,0x2A,0x2A);
  lRect(0, 92, LW, 18, bar);
  lTextC(LW/2, 95, TP_STATUS, 2, C_WHITE, bar);
}

static void veIdle(){
  lFill(C_BG);
  // Thanh tiêu đề
  lRect(0, 0, LW, 92, C_BAR);
  lTextC(LW/2, 20, "POSH", 5, C_YEL, C_BAR);
  lTextC(LW/2, 62, "GHE MASSAGE QR", 2, C_WHITE, C_BAR);
  drawTPStatus();
  // Lưới 2×2 chọn gói
  int k = 0;
  for(int r=0;r<2;r++) for(int c=0;c<2;c++) veTheGoi(k++, GX[c], GY[r], TW, TH);
  // Thanh dưới
  lRect(0, LH-56, LW, 56, C_BAR);
  lTextC(LW/2, LH-40, "CHON GOI - QUET QR THANH TOAN", 2, C_GLOW, C_BAR);
  veFlush();
}

// Vẽ dấu + toạ độ ngay chỗ chạm (đè lên màn hiện tại) rồi đẩy ra — để kiểm
// tra cảm ứng bằng MẮT, không cần Serial.
static void veMark(int x, int y){
  uint16_t c = RGB565(0xFF,0x40,0x40);
  for(int i=-16;i<=16;i++){ lpx(x+i,y,c); lpx(x,y+i,c); }   // dấu cộng
  lRect(x-3,y-3,6,6,c);
  char s[24]; snprintf(s, sizeof s, "x=%d y=%d", x, y);
  int ty = (y > LH-60) ? y-40 : y+18;
  lRect(x-70, ty-2, 140, 18, C_SHD);
  lTextC(x, ty, s, 2, C_WHITE, C_SHD);
  veFlush();
}

// Màn "đã chọn gói" — tạm thời (chỗ QR sẽ ráp ở Stage sau). Có nút QUAY LAI.
static const int BACK_X = 40, BACK_Y = LH - 120, BACK_W = LW - 80, BACK_H = 76;
static void veChon(int idx){
  lFill(C_BG);
  lRect(0, 0, LW, 92, C_BAR);
  lTextC(LW/2, 20, "DA CHON GOI", 4, C_YEL, C_BAR);
  lTextC(LW/2, 64, "GHE MASSAGE QR", 2, C_WHITE, C_BAR);
  // Thẻ gói đã chọn (giữa màn)
  int cw = LW - 80, ch = 250, cx0 = 40, cy0 = 140;
  lRoundRectA(cx0, cy0, cw, ch, 16, C_TOP, C_BG);
  lRoundRectA(cx0+2, cy0+ch/2, cw-4, ch/2-2, 14, C_BOT, C_TOP);
  int cx = LW/2;
  lTextC(cx, cy0+26, GOI[idx].ten, 4, C_WHITE, C_TOP);
  lMoneyC(cx, cy0+ch/2-24, GOI[idx].tien, 5, C_YEL, C_BOT);
  char p[16]; snprintf(p, sizeof p, "%d PHUT", GOI[idx].phut);
  lTextC(cx, cy0+ch-46, p, 2, C_PHU, C_BOT);
  // Ghi chú bước sau
  lTextC(cx, cy0+ch+28, "QR THANH TOAN SE HIEN O DAY", 2, C_GLOW, C_BG);
  // Nút QUAY LAI
  lRoundRectA(BACK_X, BACK_Y, BACK_W, BACK_H, 14, C_BAR, C_BG);
  lTextC(cx, BACK_Y+26, "QUAY LAI", 3, C_WHITE, C_BAR);
  drawTPStatus();
  veFlush();
}
static bool inBack(int tx, int ty){
  return tx >= BACK_X && tx < BACK_X+BACK_W && ty >= BACK_Y && ty < BACK_Y+BACK_H;
}

// ───────────────────────────── TRẠNG THÁI APP ──────────────────────────────
enum { ST_IDLE, ST_CHON };
static int  g_state = ST_IDLE;
static int  g_goi   = -1;

void setup(){
  Serial.begin(115200);
  delay(300);
  Serial.println("\n\n=== GHE QR S3 - Stage B (lop ve + cham chon goi) ===");

  I2C_Init();
  TCA9554PWR_Init(0x00);         // demo Waveshare: tất cả EXIO = LOW
  Set_EXIO(EXIO_PIN8, Low);      // còi tắt
  // TCA9554 dùng HẾT 8 chân (không đưa ra ngoài). Init(0x00) kéo tất cả xuống
  // LOW -> giữ luôn TP_RST của CST328 => cảm ứng "chết". Kéo các chân còn lại
  // (2,4,5,6,7 — trừ LCD RST/CS = 1/3, trừ còi = 8) lên HIGH để NHẢ reset touch.
  Set_EXIO(EXIO_PIN2, High);
  Set_EXIO(EXIO_PIN4, High);
  Set_EXIO(EXIO_PIN5, High);
  Set_EXIO(EXIO_PIN6, High);
  Set_EXIO(EXIO_PIN7, High);
  delay(10);
  Backlight_Init();
  LCD_Init();                    // drives EXIO1 (RST) + EXIO3 (CS)
  Serial.println("[S3] LCD OK, cap phat framebuffer...");

  if(!veInit()){ Serial.println("[S3] THIEU PSRAM cho framebuffer!"); return; }
  delay(300);                    // chờ CST328 boot sau khi nhả reset
  TP_Init();                     // cảm ứng CST328
  Serial.println("[S3] ve man chon goi...");
  veIdle();
  Serial.println("[S3] xong. Cham 1 goi de chon; man 'da chon' co nut QUAY LAI.");
}

// Chạm 1 lần (sườn lên): trả true + toạ độ tại thời điểm nhả/chạm mới.
static bool chamMoi(int* px, int* py){
  static bool truoc = false;      // trạng thái chạm lần trước
  static int  lx = 0, ly = 0;
  int x, y;
  bool now = TP_Read(&x, &y);
  if(now){ lx = x; ly = y; }
  bool su_kien = (!truoc && now);  // vừa nhấn xuống
  truoc = now;
  if(su_kien){ *px = lx; *py = ly; }
  return su_kien;
}

void loop(){
  int tx, ty;
  if(chamMoi(&tx, &ty)){
    Serial.printf("[TP] cham x=%d y=%d (state=%d)\n", tx, ty, g_state);
    if(g_state == ST_IDLE){
      int k = hitGoi(tx, ty);
      if(k >= 0){ g_goi = k; g_state = ST_CHON; veChon(k); }
    } else if(g_state == ST_CHON){
      if(inBack(tx, ty)){ g_state = ST_IDLE; g_goi = -1; veIdle(); }
    }
    veMark(tx, ty);      // luôn vẽ dấu + toạ độ chỗ chạm (kiểm tra bằng mắt)
  }
  // Chẩn đoán: dump thanh ghi thô GT911 mỗi 500ms (xem byte that su tra ve)
  static uint32_t td = 0;
  if(millis() - td > 500){ td = millis(); TP_DumpRaw(); }
  delay(15);
}
