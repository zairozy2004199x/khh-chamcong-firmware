/* ============================================================================
 *  mdb.h — GIẢI MÃ KHUNG MDB 9 BIT (Multi-Drop Bus) TỪ MẪU LẤY THÔ TRÊN DÂY
 * ----------------------------------------------------------------------------
 *  MDB khác UART thường ở ba chỗ, chỗ nào bỏ qua cũng ra rác mà trông như thật:
 *
 *    • 9600 baud CỐ ĐỊNH (một bit rộng ~104 micro giây).
 *    • 9 BIT dữ liệu, KHÔNG chẵn lẻ. Bit thứ 9 là MODE BIT:
 *          mode = 1  -> byte ĐỊA CHỈ (chủ gọi tên một thiết bị tớ)
 *          mode = 0  -> byte DỮ LIỆU
 *      Bộ thu 8 bit thường nuốt mất bit này -> mọi khung sai một chỗ, và không
 *      cách nào biết đâu là đầu một lệnh.
 *    • Khung UART: 1 bit start (mức 0), 9 bit dữ liệu GỬI BIT THẤP TRƯỚC, 1 bit
 *      stop (mức 1). Tổng 11 bit-time. Không có bit chẵn lẻ.
 *
 *  Ở 9600 baud một bit rộng tới 104us nên ESP32 lấy mẫu bằng phần mềm thoải mái.
 *  Cách làm: đọc mức dây liên tục vào một mảng mẫu (mỗi bit lấy nhiều mẫu), rồi
 *  đưa cả mảng cho hàm dưới đây giải ra byte + mode bit.
 *
 *  ⚠️ File này CỐ Ý chỉ dùng kiểu C thuần, không đụng phần cứng — để bài test
 *     ci/kiem-mdb.sh biên dịch thẳng bằng g++ và chốt phần giải mã, thứ dễ sai
 *     nhất, trước khi tin vào nó ngoài hiện trường.
 * ========================================================================== */
#pragma once
#include <stdint.h>

struct MdbByte {
  uint8_t  giaTri;   // 8 bit dữ liệu
  uint8_t  mode;     // bit thứ 9: 1 = địa chỉ, 0 = dữ liệu
  bool     khungLoi; // true = sai bit stop (lấy mẫu lệch, hoặc không phải MDB)
};

/**
 * Giải mã một chuỗi mẫu mức dây (mỗi phần tử 0 hoặc 1, lấy đều nhau theo thời
 * gian) thành các byte MDB. Dây MDB lúc nghỉ ở mức 1; một khung bắt đầu bằng
 * cạnh xuống (start bit = 0).
 *
 * @param mau        mảng mẫu mức dây, 0/1
 * @param soMau      số mẫu
 * @param mauMoiBit  số mẫu ứng với MỘT bit (= tần số lấy mẫu / 9600). Phải ≥ 3
 *                   thì mới lấy được điểm giữa bit cho chắc.
 * @param ra, toiDa  nơi chứa kết quả
 * @return số byte giải được, hoặc -1 nếu tham số vô lý
 */
inline int mdbGiaiMa(const uint8_t* mau, int soMau, int mauMoiBit,
                      MdbByte* ra, int toiDa) {
  if (!mau || mauMoiBit < 3 || toiDa <= 0) return -1;
  int i = 0, n = 0;
  while (i < soMau && n < toiDa) {
    // Tìm cạnh xuống: đang ở mức 1 rồi gặp mức 0 = start bit.
    if (mau[i] != 0) { i++; continue; }
    // Bảo đảm mẫu trước đó là 1 (không cắt giữa một khung). Mẫu đầu tiên thì bỏ qua kiểm.
    if (i > 0 && mau[i-1] == 0) { i++; continue; }

    int start = i;
    // Lấy giữa mỗi bit: điểm giữa của bit thứ k nằm ở start + (k + 0.5)*mauMoiBit
    auto layBit = [&](int k) -> int {
      int viTri = start + (k * mauMoiBit) + mauMoiBit / 2;
      if (viTri >= soMau) return -1;
      return mau[viTri];
    };
    // bit 0 = start, phải là 0
    if (layBit(0) != 0) { i++; continue; }

    uint16_t v = 0; bool loi = false;
    for (int b = 0; b < 9; b++) {
      int m = layBit(1 + b);
      if (m < 0) { loi = true; break; }
      if (m) v |= (uint16_t)(1u << b);        // BIT THẤP TRƯỚC
    }
    int stop = layBit(10);
    if (loi || stop != 1) {
      // Khung hỏng: nhích một mẫu rồi thử lại, đừng nhảy cả khung (có thể lệch nhịp).
      ra[n].giaTri = (uint8_t)(v & 0xFF);
      ra[n].mode   = (uint8_t)((v >> 8) & 1);
      ra[n].khungLoi = true;
      n++;
      i++;
      continue;
    }
    ra[n].giaTri = (uint8_t)(v & 0xFF);
    ra[n].mode   = (uint8_t)((v >> 8) & 1);
    ra[n].khungLoi = false;
    n++;
    // Nhảy qua trọn khung 11 bit rồi tìm khung sau.
    i = start + 11 * mauMoiBit;
  }
  return n;
}

/* Địa chỉ MDB hay gặp — để in cho dễ đọc, không dùng để quyết định gì.
   Bill validator = 0x30, coin changer = 0x08. Byte địa chỉ = <địa chỉ> | lệnh con. */
inline const char* mdbTenDiaChi(uint8_t byteDiaChi) {
  uint8_t g = byteDiaChi & 0xF8;
  if (g == 0x30) return "BILL VALIDATOR (may nhan tien giay)";
  if (g == 0x08) return "COIN CHANGER";
  if (g == 0x40) return "CASHLESS #1";
  return "?";
}
