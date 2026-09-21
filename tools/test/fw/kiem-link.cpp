/*
 * Giàn giáo chạy hàm kiểm dạng link của firmware trên máy tính thường.
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

  /* ---- Link Apps Script / Firebase cũ: PHẢI bị từ chối ----
     22/08/2026 gỡ hẳn Apps Script và Firebase, nên `execUrlHopLe`/`fbHostHopLe` không còn nữa
     và mấy phép thử của chúng cũng vậy. Nhưng cái NGUY vẫn còn: người quen tay dán link cũ vào
     ô website. Nay chỉ còn MỘT ô nên không có đường thứ hai đỡ cho — dán nhầm là máy không đẩy
     được lượt nào. Nên phần này đổi từ "hai ô tách bạch" thành "ô duy nhất từ chối link cũ". */
  t("TU CHOI link Firebase RTDB dan nham vao o website",
    !wpUrlHopLe("https://x-y.asia-southeast1.firebasedatabase.app"));
  t("TU CHOI link /a/macros/ cua Workspace",
    !wpUrlHopLe("https://script.google.com/a/macros/vidu.test/s/ABC/exec"));
  /* Link kết thúc /exec mà KHÔNG chứa /macros/ thì `wpUrlHopLe` nhận — đúng, vì nó chỉ là một
     đường dẫn bình thường trên tên miền của mình. Chốt ở đây để ai đọc khỏi tưởng là lọt. */
  t("nhan duong dan binh thuong tren ten mien cua minh", wpUrlHopLe("https://vidu.test/cham-cong-may"));

  if (hong) { printf("HONG: %d | DAT: %d\n", hong, dat); return 1; }
  printf("DAT: %d phep thu — o link website tu choi moi dang link cu.\n", dat);
  return 0;
}
