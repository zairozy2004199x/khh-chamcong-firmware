# App chấm công K&H (Android)

App cho nhân viên: chấm công có ảnh đóng dấu **giờ máy chủ** và đối chiếu vị trí, xem công của
mình, phiếu lương, đơn từ, và lưới Ứng dụng.

---

## 🔴 App này là một cái vỏ mỏng quanh trang trạm. Đó là chủ ý, không phải làm tắt.

Toàn bộ nghiệp vụ nằm ở `wordpress/vhcp-cham-cong/templates/tram.php` và các lớp PHP sau nó —
đã chạy thật, đã có bộ thử canh. App Android **không chép lại một luật nào** trong số đó.

Viết lại bằng Kotlin nghe thì "đàng hoàng" hơn, nhưng nó dựng ra **bản thứ hai** của cùng những
luật tính công, trên một ngôn ngữ khác, không dùng chung một dòng mã nào. Hai bản ấy **sẽ** lệch
— không phải "có thể", mà chắc chắn, vào đúng ngày sửa một luật mà chỉ sửa được một bên. Cái
lệch ấy hiện ra dưới dạng **hai con số lương khác nhau cho cùng một người**, tuỳ họ mở bằng app
hay bằng web. Đó là hỏng tệ nhất mà hệ này có thể hỏng.

Nên app mang đúng những thứ **chỉ APK mới cho được**, và không mang gì khác:

| Thứ APK cho được | Vì sao web không cho |
|---|---|
| Biểu tượng riêng, mở là vào thẳng | Lối tắt Safari vẫn còn thanh địa chỉ, vẫn là một tab trong đống tab |
| Quyền camera / vị trí do **app** giữ | Trình duyệt quên quyền, mỗi sáng hỏi lại |
| Cài từ một tệp `.apk` | Không cần ai biết gõ địa chỉ web |
| Link ra ngoài bật sang trình duyệt thật | Trong PWA thì Google Maps mở kẹt trong khung |

---

## Bản 1.3.0 (26/09/2026)

Mọi thứ mới trên trang trạm (lời chào ngày mới, đăng nhập mới, bảng "Bắt đầu cùng K&H" và nội
quy, chuyển tab kiểu giọt nước, phiếu lương) **tự có trong app** — app mở đúng trang ấy. Bản này chỉ
sửa hai chỗ mà WebView làm khác trình duyệt:

* **🖨 In / Lưu PDF phiếu lương và bộ hồ sơ nhận việc.** WebView bỏ qua `window.print()` — nút bấm
  không có gì xảy ra. Nay trang in nhận ra app (đuôi `KHChamCongApp/` trong User-Agent) và mở lại
  chính nó kèm `vhcc_in=1`; app chặn lượt ấy và mở **hộp In của Android** (có "Lưu dưới dạng PDF").
* **Bảng nhập môn:** mở bằng app là việc "Cài app" tự tích.

⚠️ Hai đầu (PHP + Kotlin) phải khớp — canh trong `tools/test/kiem-app-chamcong.py`. Máy còn app
1.2.0 thì nút in vẫn không chạy cho tới khi cài 1.3.0; mọi thứ khác vẫn chạy.

---

## ⚠️ Một thứ bản 1.0 CHƯA CÓ: nhắc chấm công

Lời nhắc hiện chạy bằng **Web Push**, mà Web Push **không hoạt động trong WebView** — giới hạn
của Android, không phải của mã này. Ai cần nhắc thì vẫn mở bằng Chrome như cũ.

Làm được, nhưng phải là **thông báo Android thật** (WorkManager hỏi máy chủ theo nhịp). Đó là
một việc riêng, có phần máy chủ đi kèm — không nhét kèm vào bản này cho xong.

---

## Mấy chốt dễ mất, và hậu quả khi mất

App gần như không có logic. Chỗ hỏng của một app bọc WebView nằm ở **mấy dòng cấu hình**, và
không dòng nào trong số đó làm trình biên dịch kêu:

| Thiếu | Hậu quả | Trông giống |
|---|---|---|
| `domStorageEnabled` | `localStorage` ném lỗi giữa hàm, phần còn lại không chạy | trang hỏng ở chỗ chẳng liên quan gì tới lưu trữ |
| `onPermissionRequest` | `getUserMedia` bị chối im lặng | "máy ảnh chưa sẵn sàng" — lỗi phần cứng |
| `onGeolocationPermissionsShowPrompt` | ô vị trí trống mãi | GPS của điện thoại hỏng |
| `mediaPlaybackRequiresUserGesture` | khung hình đen tới khi chạm bừa | camera chết |
| `onShowFileChooser` | bấm "Chọn tệp" không có gì xảy ra | app hỏng, thôi mở bằng Chrome |

Tất cả đều có phép thử canh: `python3 tools/test/kiem-app-chamcong.py`.

Hai chốt nữa đáng nhớ:

* **Không có `addJavascriptInterface` ở bất kỳ đâu.** Đó là cửa để trang web chạy mã trên máy
  nhân viên. App không cần nó. Có nó là một quyết định phải được bàn, không phải một dòng ai đó
  thêm vào cho tiện.
* **Chỉ giữ trong app những gì thuộc máy chủ mình**, so bằng đuôi tên miền có dấu chấm chứ không
  bằng `contains` — `contains` thì `khmatrix.com.ke-gian.vn` cũng lọt, tức là một tên miền của
  người khác chạy bên trong cái WebView vừa được cấp quyền camera.

---

## Quyền

Chỉ bốn: `INTERNET`, `ACCESS_NETWORK_STATE`, `CAMERA`, `ACCESS_FINE/COARSE_LOCATION`.

🔴 **Không xin `ACCESS_BACKGROUND_LOCATION`, và sẽ không bao giờ xin.** Hệ này cố ý không theo
dõi định vị: nó chỉ đọc toạ độ đúng lúc người ta bấm nút, khi trang đang mở. Xin quyền nền là mở
đường cho một tính năng chưa ai quyết định làm — và là thứ đầu tiên nhân viên nhìn thấy khi họ
xem app xin những gì. Có phép thử canh.

---

## Dựng

Không dựng trên máy viết mã (không có Android SDK). Đẩy lên nhánh là GitHub Actions dựng:
`.github/workflows/android-cham-cong.yml` → tệp `.apk` nằm ở mục **Releases**, bấm tải thẳng.

Dựng tại chỗ (nếu có Android Studio):

```
cd android_cham_cong && ./gradlew assembleDebug
```

🔴 Bản phát hành ký bằng **khoá gỡ lỗi**. Cài được ngay, chạy đầy đủ, nhưng không cập nhật đè lên
bản ký bằng khoá khác — đổi sang khoá thật thì phải gỡ app cũ rồi cài lại.

---

## Cài cho nhân viên

1. Tải tệp `.apk` từ Releases, mở ra, cho phép "cài từ nguồn không rõ" nếu máy hỏi.
2. Lần đầu mở: nhập địa chỉ máy chủ (`khmatrix.com`) — app tự ép về `https`.
3. Đăng nhập bằng PIN như trên web.
4. Lần đầu chấm công máy hỏi quyền **Máy ảnh** và **Vị trí**. Cả hai đều cần.
