/* ============================================================================
 *  ma_qr.h — ĐỌC VÀ KIỂM MÃ QR CỦA POSH (kiểm ngay trên chip, KHÔNG cần mạng)
 * ----------------------------------------------------------------------------
 *  VÌ SAO KIỂM OFFLINE
 *    Hộp QR gắn dưới ghế massage, đặt ở chỗ nhiều khi không có WiFi. Nếu phải hỏi
 *    server mới mở ghế thì mất mạng = khách trả tiền rồi mà ghế không chạy. Nên mã
 *    QR phải TỰ CHỨNG MINH nó hợp lệ: bên trong mã đã có sẵn chữ ký.
 *
 *  DẠNG MÃ — MỞ GHẾ
 *      POSH1|<máy>|<phút>|<hết hạn>|<mã lượt>|<chữ ký>
 *      ví dụ:  POSH1|GHE-01|15|1786000000|a7f3k9x2|3f9c1d0a8b774e21
 *
 *      máy      mã ghế được phép mở, hoặc "*" = mọi ghế
 *      phút     số phút cho ghế chạy (1..240)
 *      hết hạn  giây epoch (UTC); 0 = không đặt hạn
 *      mã lượt  chuỗi DUY NHẤT mỗi lần bán (chống quét lại cùng một ảnh chụp màn hình)
 *      chữ ký   16 ký tự hex = 8 byte ĐẦU của HMAC-SHA256(khoá, phần trước dấu | cuối)
 *
 *  DẠNG MÃ — ĐẶT GIỜ CHO HỘP (mã của thợ, không phải của khách)
 *      POSHT|<giây epoch>|<chữ ký>
 *      Hộp không có pin đồng hồ. Chỗ không WiFi thì quét mã này để nạp giờ, nhờ đó
 *      ô "hết hạn" mới có nghĩa. Xem thêm ghi chú đồng hồ trong file .ino.
 *
 *  ⚠️ KHOÁ KÝ (SEC_QR_KHOA) LÀ BÍ MẬT NẶNG NHẤT CỦA HỘP NÀY.
 *     Ai có khoá là tự in được mã mở ghế vô hạn. Khoá KHÔNG nằm trong repo — nó ở
 *     bộ nhớ trong (NVS), khai qua portal 192.168.4.1 hoặc qua secrets.h khi nạp USB.
 *
 *  ⚠️ CHỮ KÝ CẮT CÒN 8 BYTE là cố ý: mã QR càng ngắn thì in càng nhỏ, máy quét càng
 *     nhanh bắt. 64 bit vẫn quá đủ — muốn đoán mò phải thử ~2^63 lần, mà mỗi lần thử
 *     là một lần giơ mã trước máy quét.
 * ========================================================================== */
#pragma once
#include <Arduino.h>
#include "mbedtls/md.h"
#include "mbedtls/sha256.h"

#define QR_NHAN_MO_GHE  "POSH1"
#define QR_NHAN_DAT_GIO "POSHT"
#define QR_PHUT_TOI_DA  240      // 4 tiếng. Cao hơn nữa gần như chắc chắn là gõ nhầm số.
#define QR_MA_TOI_DA    24       // độ dài tối đa của "mã lượt"

enum LoaiMa { MA_HONG = 0, MA_MO_GHE = 1, MA_DAT_GIO = 2 };

struct MaQR {
  LoaiMa   loai    = MA_HONG;
  String   may     = "";
  uint16_t phut    = 0;
  uint32_t hetHan  = 0;
  String   maLuot  = "";
  uint32_t gioDat  = 0;     // chỉ dùng cho POSHT
  String   loi     = "";    // vì sao hỏng — câu tiếng Việt để in thẳng ra log/portal
};

/* --------------------------------------------------------------------------
 *  Mấy hàm băm nhỏ dùng chung
 * ------------------------------------------------------------------------ */
inline void qrHmacSha256(const String& khoa, const String& noiDung, uint8_t out[32]) {
  const mbedtls_md_info_t* info = mbedtls_md_info_from_type(MBEDTLS_MD_SHA256);
  mbedtls_md_context_t ctx;
  mbedtls_md_init(&ctx);
  mbedtls_md_setup(&ctx, info, 1);                       // 1 = bật chế độ HMAC
  mbedtls_md_hmac_starts(&ctx, (const uint8_t*)khoa.c_str(), khoa.length());
  mbedtls_md_hmac_update(&ctx, (const uint8_t*)noiDung.c_str(), noiDung.length());
  mbedtls_md_hmac_finish(&ctx, out);
  mbedtls_md_free(&ctx);
}

/** Băm "mã lượt" xuống 8 byte để nhớ vào danh sách đã dùng — nhớ nguyên chuỗi thì tốn NVS. */
inline uint64_t qrBamMaLuot(const String& maLuot) {
  uint8_t h[32];
  mbedtls_sha256((const uint8_t*)maLuot.c_str(), maLuot.length(), h, 0);
  uint64_t v = 0;
  for (int i = 0; i < 8; i++) v = (v << 8) | h[i];
  return v;
}

/* ⚠️ So chữ ký PHẢI so hết mọi byte, không được thấy lệch là thoát sớm.
   Thoát sớm làm thời gian so lệ thuộc vào việc đoán đúng bao nhiêu byte đầu — kẻ đo
   thời gian có thể mò ra chữ ký từng byte một. Ở đây kẻ tấn công đứng ngay trước máy
   nên khó đo thật, nhưng viết đúng chẳng tốn gì. */
inline bool qrSoBangNhau(const uint8_t* a, const uint8_t* b, size_t n) {
  uint8_t khac = 0;
  for (size_t i = 0; i < n; i++) khac |= (uint8_t)(a[i] ^ b[i]);
  return khac == 0;
}

inline String qrRaHex(const uint8_t* d, size_t n) {
  static const char* B = "0123456789abcdef";
  String s; s.reserve(n * 2);
  for (size_t i = 0; i < n; i++) { s += B[d[i] >> 4]; s += B[d[i] & 0x0F]; }
  return s;
}

/** Cắt chuỗi theo dấu '|'. Trả về số phần cắt được (tối đa maxPhan). */
inline int qrCat(const String& s, String* phan, int maxPhan) {
  int n = 0, batDau = 0;
  while (n < maxPhan) {
    int v = s.indexOf('|', batDau);
    if (v < 0) { phan[n++] = s.substring(batDau); break; }
    phan[n++] = s.substring(batDau, v);
    batDau = v + 1;
  }
  return n;
}

/** Chuỗi chỉ gồm chữ/số/-/_ — dùng cho mã máy và mã lượt. */
inline bool qrChuSoAnToan(const String& s) {
  if (s.length() == 0) return false;
  for (unsigned i = 0; i < s.length(); i++) {
    char c = s.charAt(i);
    bool ok = (c >= '0' && c <= '9') || (c >= 'a' && c <= 'z') ||
              (c >= 'A' && c <= 'Z') || c == '-' || c == '_';
    if (!ok) return false;
  }
  return true;
}

/** Chuỗi toàn chữ số, và ép trong khoảng — trả false nếu rỗng, có ký tự lạ, hoặc tràn. */
inline bool qrSoNguyen(const String& s, uint32_t toiDa, uint32_t* ra) {
  if (s.length() == 0 || s.length() > 10) return false;
  uint64_t v = 0;
  for (unsigned i = 0; i < s.length(); i++) {
    char c = s.charAt(i);
    if (c < '0' || c > '9') return false;
    v = v * 10 + (uint32_t)(c - '0');
    if (v > (uint64_t)toiDa) return false;
  }
  *ra = (uint32_t)v;
  return true;
}

/* ============================================================================
 *  ĐỌC + KIỂM CHỮ KÝ
 *  Trả về MaQR; loai == MA_HONG thì .loi nói rõ hỏng chỗ nào.
 *
 *  ⚠️ Hàm này CHỈ kiểm những gì nằm trong chính mã QR: đúng dạng, chữ ký khớp khoá.
 *     Hạn dùng, đúng ghế, đã xài chưa — .ino kiểm, vì mấy thứ đó cần đồng hồ và
 *     cần bộ nhớ của hộp.
 * ========================================================================== */
inline MaQR qrDoc(const String& thoRaw, const String& khoaKy) {
  MaQR m;

  /* ⚠️ Dọn rác đầu/cuối TRƯỚC KHI làm gì khác.
     Đã trả giá ở firmware máy chấm công (commit 67a08e7): thẻ nhớ ghi kèm BOM
     "\xEF\xBB\xBF" nên token đúng vẫn bị coi là rỗng, soi mãi không ra vì nhìn log
     thấy chuỗi y hệt. Module quét QR cũng hay kèm CR/LF, đôi khi cả khoảng trắng. */
  String tho = thoRaw;
  if (tho.length() >= 3 && (uint8_t)tho.charAt(0) == 0xEF &&
      (uint8_t)tho.charAt(1) == 0xBB && (uint8_t)tho.charAt(2) == 0xBF) tho = tho.substring(3);
  tho.trim();

  if (tho.length() == 0)   { m.loi = "mã rỗng"; return m; }
  if (tho.length() > 160)  { m.loi = "mã dài bất thường (" + String(tho.length()) + " ký tự)"; return m; }
  if (khoaKy.length() == 0){ m.loi = "hộp CHƯA khai khoá ký — vào 192.168.4.1 khai"; return m; }

  String p[7];
  int n = qrCat(tho, p, 7);

  /* ---- POSHT: mã đặt giờ ---- */
  if (p[0] == QR_NHAN_DAT_GIO) {
    if (n != 3) { m.loi = "mã đặt giờ phải có đúng 3 phần"; return m; }
    uint32_t gio = 0;
    if (!qrSoNguyen(p[1], 4102444800UL, &gio) || gio < 1700000000UL) {
      m.loi = "giờ trong mã không hợp lệ"; return m;
    }
    uint8_t h[32];
    qrHmacSha256(khoaKy, String(QR_NHAN_DAT_GIO) + "|" + p[1], h);
    String mongDoi = qrRaHex(h, 8);
    String coThat  = p[2]; coThat.toLowerCase();
    if (coThat.length() != 16 ||
        !qrSoBangNhau((const uint8_t*)mongDoi.c_str(), (const uint8_t*)coThat.c_str(), 16)) {
      m.loi = "chữ ký KHÔNG khớp — mã không phải của hộp này"; return m;
    }
    m.loai = MA_DAT_GIO; m.gioDat = gio;
    return m;
  }

  /* ---- POSH1: mã mở ghế ---- */
  if (p[0] != QR_NHAN_MO_GHE) {
    /* Nói rõ "không phải mã POSH" thay vì "mã sai": khách hay giơ nhầm mã ngân hàng,
       mã Zalo, mã sản phẩm… Nhân viên đọc log phải phân biệt được nhầm mã với mã giả. */
    m.loi = "không phải mã POSH (bắt đầu bằng \"" + p[0].substring(0, 8) + "\")";
    return m;
  }
  if (n != 6) { m.loi = "mã mở ghế phải có đúng 6 phần, đang có " + String(n); return m; }

  if (!(p[1] == "*" || qrChuSoAnToan(p[1]))) { m.loi = "ô mã ghế có ký tự lạ"; return m; }
  uint32_t phut = 0;
  if (!qrSoNguyen(p[2], QR_PHUT_TOI_DA, &phut) || phut == 0) {
    m.loi = "số phút phải từ 1 tới " + String(QR_PHUT_TOI_DA); return m;
  }
  uint32_t hetHan = 0;
  if (!qrSoNguyen(p[3], 4102444800UL, &hetHan)) { m.loi = "ô hết hạn không hợp lệ"; return m; }
  if (!qrChuSoAnToan(p[4]) || p[4].length() > QR_MA_TOI_DA) {
    m.loi = "mã lượt trống hoặc có ký tự lạ"; return m;
  }

  uint8_t h[32];
  qrHmacSha256(khoaKy, p[0] + "|" + p[1] + "|" + p[2] + "|" + p[3] + "|" + p[4], h);
  String mongDoi = qrRaHex(h, 8);
  String coThat  = p[5]; coThat.toLowerCase();
  if (coThat.length() != 16 ||
      !qrSoBangNhau((const uint8_t*)mongDoi.c_str(), (const uint8_t*)coThat.c_str(), 16)) {
    /* ⚠️ TUYỆT ĐỐI không in chữ ký mong đợi ra log/portal. In ra là chỉ luôn cho người
       cầm log cách sửa mã giả thành mã thật. */
    m.loi = "chữ ký KHÔNG khớp — mã giả, hoặc hộp khai nhầm khoá ký";
    return m;
  }

  m.loai = MA_MO_GHE; m.may = p[1]; m.phut = (uint16_t)phut;
  m.hetHan = hetHan;  m.maLuot = p[4];
  return m;
}
