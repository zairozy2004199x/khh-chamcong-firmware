/*
 * Giàn giáo chạy ba hàm kiểm dạng link của firmware trên máy tính thường.
 * `trich.inc` do kiem-link.sh trích TỪ CHÍNH .ino ra — không chép lại luật ở đây.
 */
#include <string>
#include <cstdio>
#include <cstring>

/* Đủ phần String của Arduino mà ba hàm kia dùng. Chỉ những phương thức chúng thật sự gọi. */
struct String : std::string {
  String() {}
  String(const char* s) : std::string(s ? s : "") {}
  String(const std::string& s) : std::string(s) {}
  int indexOf(const char* n) const { auto p = find(n); return p == npos ? -1 : (int) p; }
  int indexOf(char c, int from) const { auto p = find(c, from); return p == npos ? -1 : (int) p; }
  bool startsWith(const char* n) const { return rfind(n, 0) == 0; }
  bool endsWith(const char* n) const {
    size_t l = strlen(n);
    return size() >= l && compare(size() - l, l, n) == 0;
  }
};
#include "trich.inc"

static int dat = 0, hong = 0;
static void t(const char* ten, bool dk) {
  if (dk) { dat++; return; }
  hong++;
  printf("  X %s\n", ten);
}

int main() {
  /* ---- Ô WORDPRESS ---- */
  t("nhan link WordPress dung dang", wpUrlHopLe("https://vidu.test/cham-cong-may"));
  /* Máy KHÔNG đi theo chuyển hướng: WordPress chuyển hướng để thêm/bỏ dấu gạch cuối là máy gọi
     lại bằng GET, mất trọn thân POST — mà thân POST chính là lượt chấm công. */
  t("TU CHOI dau / o cuoi (may khong di theo chuyen huong -> mat than POST)",
    !wpUrlHopLe("https://vidu.test/cham-cong-may/"));
  t("TU CHOI http thuong", !wpUrlHopLe("http://vidu.test/cham-cong-may"));
  t("TU CHOI link Apps Script bi dan lan o",
    !wpUrlHopLe("https://script.google.com/macros/s/ABC/exec"));
  t("TU CHOI chuoi rong", !wpUrlHopLe(""));
  t("TU CHOI khong co ten mien", !wpUrlHopLe("https://localhost"));
  t("TU CHOI link qua ngan", !wpUrlHopLe("https://a.b"));

  /* ---- Ô /exec: thêm đường thứ hai KHÔNG được nới lỏng ô cũ ---- */
  t("van nhan /macros/s/<id>/exec", execUrlHopLe("https://script.google.com/macros/s/ABC/exec"));
  t("van TU CHOI dang /a/macros/ (link Workspace, thiet bi an danh bi chan)",
    !execUrlHopLe("https://script.google.com/a/macros/vidu.test/s/ABC/exec"));
  t("van TU CHOI thieu /exec o cuoi", !execUrlHopLe("https://script.google.com/macros/s/ABC/"));
  t("van TU CHOI link WordPress dan lan o exec", !execUrlHopLe("https://vidu.test/cham-cong-may"));
  /* Ca này CHỈ chốt "/macros/s/" bắt được: kết thúc đúng /exec, không phải /a/macros/, nhưng
     không phải link Apps Script. Ca trên bị chốt endsWith("/exec") bắt trước nên bỏ chốt
     "/macros/s/" đi mà phép thử vẫn xanh — đúng một lỗ đã phát hiện khi thử phá. */
  t("TU CHOI link ket thuc /exec ma khong phai Apps Script",
    !execUrlHopLe("https://vidu.test/exec"));

  /* ---- Ô Firebase: không bị ảnh hưởng ---- */
  t("fbHost van doi .firebasedatabase.app",
    fbHostHopLe("https://x-y.asia-southeast1.firebasedatabase.app"));
  t("fbHost tu choi dau / o cuoi", !fbHostHopLe("https://x.firebasedatabase.app/"));

  /* ---- ĐIỀU QUAN TRỌNG NHẤT: không link nào hợp lệ cho CẢ HAI ô ---- */
  t("mot link khong the vua hop le cho ca hai o",
    !(execUrlHopLe("https://script.google.com/macros/s/ABC/exec")
      && wpUrlHopLe("https://script.google.com/macros/s/ABC/exec")));
  t("va nguoc lai",
    !(wpUrlHopLe("https://vidu.test/cham-cong-may")
      && execUrlHopLe("https://vidu.test/cham-cong-may")));

  if (hong) { printf("HONG: %d | DAT: %d\n", hong, dat); return 1; }
  printf("DAT: %d phep thu — hai duong tach bach, khong o nao nhan link cua o kia.\n", dat);
  return 0;
}
