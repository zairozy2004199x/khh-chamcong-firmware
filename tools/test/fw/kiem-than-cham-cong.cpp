/*
 * Giàn giáo chạy hàm dựng thân chấm công + luật tên cơ sở trên máy tính thường.
 * `trich.inc` do kiem-than-cham-cong.sh trích TỪ CHÍNH .ino ra — không chép lại luật ở đây.
 */
#include <string>
#include <cstdio>
#include <cstring>

/* Đủ phần String của Arduino mà mấy hàm kia dùng. Chỉ những phương thức chúng thật sự gọi.
   `replace` của Arduino thay MỌI lần gặp và trả về void — khác hẳn std::string::replace, nên
   phải tự viết. Khai trong lớp con là nó che hết các nạp chồng của lớp cha, đúng ý muốn. */
struct String : std::string {
  String() {}
  String(const char* s) : std::string(s ? s : "") {}
  String(const std::string& s) : std::string(s) {}
  char charAt(size_t i) const { return i < size() ? (*this)[i] : 0; }
  int indexOf(char c) const { auto p = find(c); return p == npos ? -1 : (int) p; }
  String substring(size_t a) const { return a <= size() ? String(substr(a)) : String(); }
  String substring(size_t a, size_t b) const { return String(substr(a, b - a)); }
  void replace(const char* tu, const char* thanh) {
    std::string t(tu), h(thanh);
    if (t.empty()) return;
    size_t i = 0;
    while ((i = find(t, i)) != npos) { std::string::replace(i, t.size(), h); i += h.size(); }
  }
};
#include "trich.inc"

static int dat = 0, hong = 0;
static void t(const char* ten, bool dk) {
  if (dk) { dat++; return; }
  hong++;
  printf("  X %s\n", ten);
}

/* Đếm dấu " KHÔNG bị escape. Đây là phép soát CẤU TRÚC thật, không phải so chuỗi: thân đúng
   phải có chẵn 4 dấu nháy mỗi trường (mở/đóng tên khoá, mở/đóng giá trị) × 8 trường = 32.
   Lẫn một dấu " thô từ tên nhân viên vào là ra 33 -> JSON hỏng. Còn một dấu \ thô ở CUỐI giá
   trị thì ăn mất dấu nháy đóng -> ra 31, cũng hỏng. Hai kiểu hỏng, một phép đếm bắt được cả. */
static int demNhayTho(const std::string& s) {
  int n = 0;
  for (size_t i = 0; i < s.size(); i++) {
    if (s[i] == '\\') { i++; continue; }   // bỏ qua ký tự bị escape
    if (s[i] == '"') n++;
  }
  return n;
}
static const int NHAY_DUNG = 32;          // 8 trường × 4

int main() {
  /* ---------- jsonEscMin_: hai ký tự phải escape ---------- */
  t("escape dau nhay kep", std::string(jsonEscMin_("a\"b")) == "a\\\"b");
  t("escape dau gach cheo nguoc", std::string(jsonEscMin_("a\\b")) == "a\\\\b");
  /* Thứ tự quan trọng: phải thay \ TRƯỚC rồi mới thay " — làm ngược thì dấu \ do escape sinh ra
     lại bị escape lần nữa, ra \\\" và giá trị lệch. */
  t("thu tu escape dung (\\ truoc \")", std::string(jsonEscMin_("\\\"")) == "\\\\\\\"");
  t("chuoi sach thi khong doi", std::string(jsonEscMin_("POSH_HCM")) == "POSH_HCM");

  /* ---------- Thân chấm công: trường hợp thường ---------- */
  String sach = thanChamCong("AA:BB:CC", "DS-K1T", "K1T671", "POSH_HCM_Q1",
                             "NV007", "Nguyen Van A", "2026-09-17T08:01:22");
  t("than binh thuong dung cau truc", demNhayTho(sach) == NHAY_DUNG);
  t("than co du 8 truong",
    sach.find("\"macAddress\"")  != std::string::npos &&
    sach.find("\"hikSerial\"")   != std::string::npos &&
    sach.find("\"hikModel\"")    != std::string::npos &&
    sach.find("\"stationName\"") != std::string::npos &&
    sach.find("\"employeeNo\"")  != std::string::npos &&
    sach.find("\"name\"")        != std::string::npos &&
    sach.find("\"time\"")        != std::string::npos &&
    sach.find("\"image\"")       != std::string::npos);
  t("anh de rong (khong nhoi base64 vao than)", sach.find("\"image\":\"\"}") != std::string::npos);

  /* ---------- CHÍNH LỖI ĐÃ VÁ 17/09/2026 ----------
     `name` là tên nhân viên LẤY TỪ MÁY HIKVISION — chuỗi do người khác gõ, không phải của mình.
     Ai gõ tên có dấu nháy (biệt danh, tên kèm chức danh trong ngoặc kép) là hỏng JSON. */
  String coNhay = thanChamCong("AA:BB:CC", "DS-K1T", "K1T671", "POSH_HCM_Q1",
                               "NV008", "Nguyen \"Bo\" Tran", "2026-09-17T08:02:00");
  t("ten co dau nhay KHONG lam hong cau truc JSON", demNhayTho(coNhay) == NHAY_DUNG);
  t("ten co dau nhay duoc escape trong than", coNhay.find("Nguyen \\\"Bo\\\" Tran") != std::string::npos);

  /* Dấu \ ở CUỐI tên là ca độc nhất: không escape thì nó ăn luôn dấu nháy đóng của trường,
     dồn giá trị trường sau vào trường này — cổng nhận đọc ra tên nhân viên là cả cụm giờ. */
  String cuoiGachCheo = thanChamCong("AA:BB:CC", "DS-K1T", "K1T671", "POSH_HCM_Q1",
                                     "NV009", "Tran Thi B\\", "2026-09-17T08:03:00");
  t("ten ket thuc bang \\ KHONG an mat dau nhay dong", demNhayTho(cuoiGachCheo) == NHAY_DUNG);

  /* Mọi trường đều phải escape, không chỉ `name`. Tên cơ sở đã có hàm lọc gác ở ba cửa, nhưng
     escape vẫn phải có: hàm lọc là tuyến một, escape là tuyến hai. Bỏ tuyến hai vì "tuyến một
     đã gác" là đúng hôm nay và sai vào ngày có người thêm cửa thứ tư mà quên gọi hàm lọc. */
  String moiTruongBan = thanChamCong("A\"A", "S\"S", "M\"M", "T\"T", "E\"E", "N\"N", "G\"G");
  t("CA BAY truong deu duoc escape", demNhayTho(moiTruongBan) == NHAY_DUNG);

  /* Tên tiếng Việt có dấu PHẢI đi qua nguyên vẹn. JSON cho phép UTF-8 thô, nên không được
     "sửa" bằng cách lọc bỏ ký tự ngoài ASCII — làm thế là mất tên thật của nhân viên. */
  String tiengViet = thanChamCong("AA:BB:CC", "DS-K1T", "K1T671", "POSH_HCM_Q1",
                                  "NV010", "Nguyễn Văn Ước", "2026-09-17T08:04:00");
  t("ten tieng Viet co dau di qua nguyen ven", tiengViet.find("Nguyễn Văn Ước") != std::string::npos);
  t("ten tieng Viet khong lam hong cau truc", demNhayTho(tiengViet) == NHAY_DUNG);

  /* ---------- fwMaNgay: cửa gác OTA đã chết trước 17/09/2026 ---------- */
  /* Ca thật: FW_VERSION kèm câu mô tả, còn máy chủ gửi về mã ngày gọn. So nguyên văn là KHÔNG
     BAO GIỜ khớp, nên máy vừa nạp USB (NVS chưa có otaVer) tải lại đúng bản đang chạy. */
  t("cat duoc ma ngay khoi FW_VERSION co cau mo ta",
    std::string(fwMaNgay("2026-09-17a (escape du truong o than cham cong)")) == "2026-09-17a");
  t("chuoi da gon thi tra ve chinh no", std::string(fwMaNgay("2026-09-17a")) == "2026-09-17a");
  t("HAI KIEU CHUOI MAY CHU GUI DEU KHOP",
    std::string(fwMaNgay("2026-09-17a")) ==
    std::string(fwMaNgay("2026-09-17a (escape du truong o than cham cong)")));
  /* Chốt ngược: bản KHÁC ngày thì phải KHÁC, kẻo "so mã ngày" biến thành "bản nào cũng như nhau"
     và máy không bao giờ nhận bản mới nữa — hỏng nặng hơn cái đang vá. */
  t("ban khac ngay thi KHAC (khong lam chet duong nhan ban moi)",
    std::string(fwMaNgay("2026-09-18a (ban moi)")) != std::string(fwMaNgay("2026-09-17a")));
  t("chuoi rong khong lam vo", std::string(fwMaNgay("")) == "");

  /* ---------- Tên cơ sở: MỘT luật cho cả ba cửa ---------- */
  t("nhan ma co so thuong", tenCoSoHopLe("POSH_HCM_Q1"));
  t("nhan gach ngang va so", tenCoSoHopLe("posh-705"));
  t("TU CHOI chuoi rong", !tenCoSoHopLe(""));
  /* Ba ca dưới là đúng những gì cửa portal nhận bừa trước 17/09/2026. */
  t("TU CHOI dau cach", !tenCoSoHopLe("POSH HCM"));
  t("TU CHOI dau tieng Viet", !tenCoSoHopLe("Quán"));
  t("TU CHOI dau nhay kep (ca lam hong JSON)", !tenCoSoHopLe("Quan \"Bo To\""));
  t("TU CHOI gach cheo nguoc", !tenCoSoHopLe("a\\b"));

  if (hong) { printf("HONG: %d | DAT: %d\n", hong, dat); return 1; }
  printf("DAT: %d phep thu — than cham cong escape du moi truong, ten co so mot luat ba cua,\n"
         "         cua gac OTA so bang ma ngay.\n", dat);
  return 0;
}
