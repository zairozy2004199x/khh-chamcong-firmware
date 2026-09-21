# App Chấm Công — cài lên màn hình chính iPhone / Android

Plugin **`vhcp-cc-app`** (App Chấm Công K&H). Nó **không** viết lại phần chấm công — phần đó vẫn
là trang `/cham-cong-online` của plugin **Chấm Công**. Bộ này chỉ khoác lên trang ấy lớp vỏ để
điện thoại coi nó là một app: có biểu tượng riêng, mở toàn màn hình (không thanh địa chỉ), và mở
được cả khi mạng chập chờn.

Không phải lên App Store, không phải cài gì từ kho ứng dụng.

---

## 1. Cài (một lần, phía quản trị)

1. Lấy bản cài từ **Releases** của repo — tag `vhcp-cc-app-v<số bản>` (workflow `phat-hanh.yml`
   tự dựng khi số bản đổi). `wp-admin` → Plugin → Cài mới → Tải plugin lên → **Kích hoạt**.
   Từ lần sau plugin tự thấy bản mới và bày nút *Cập nhật* như mọi plugin khác — với điều kiện đã
   khai khoá GitHub chỉ-đọc (dùng chung một ô với các plugin kia, xem `docs/CAP-NHAT-TU-GITHUB.md`).
   Cần dựng tay: `bash tools/build-plugin-zip.sh cc-app` → `dist/vhcp-cc-app.zip`.
2. Vào **Cài đặt → App Chấm Công**. Đọc khối **Tự soát** — ba dòng phải xanh hết:

   | Dòng | Đỏ thì sao |
   |---|---|
   | **HTTPS** | 🔴 Điện thoại **không cài được app**. Đây là luật của trình duyệt, không có cách đi vòng. Bật SSL trước. |
   | **Đường dẫn tĩnh** | App vẫn chạy, chỉ là địa chỉ xấu (`?ccapp=app`). |
   | **Plugin Chấm Công** | App mở ra một trang báo lỗi. Cài/bật **Chấm Công** trước. |

3. Địa chỉ app mặc định là `https://<trang>/cc/`. Đổi được ở ngay màn ấy.

> ⚠️ Nếu `/cc/` ra **404**: vào **Cài đặt → Đường dẫn tĩnh** bấm **Lưu** một lượt (không cần đổi
> gì). WordPress nạp lại bảng đường dẫn ở đó.

---

## 2. Cài (nhân viên, trên điện thoại)

Màn Cài đặt có sẵn một ô chữ chép thẳng vào nhóm Zalo. Nội dung:

* **iPhone** — mở địa chỉ bằng **Safari** → bấm nút **Chia sẻ** ở thanh dưới → **Thêm vào MH chính**.
* **Android** — mở bằng **Chrome** → bấm nút **Cài app** hiện ở cuối màn hình (hoặc menu ⋮ →
  *Cài ứng dụng*).

Lần đầu mở từ biểu tượng phải **đăng nhập lại bằng PIN**. Đây là chuyện bình thường, không phải
lỗi: iOS giữ phiên của app tách hẳn khỏi phiên của Safari.

---

## 3. Nó làm gì khi mất mạng

| Việc | Mất mạng |
|---|---|
| Mở app | **Được** — giao diện hiện ra từ bộ nhớ máy |
| Xem ô bản đồ đã xem | Được |
| **Chấm công** | **Không** — báo hỏng ngay, không ghi gì |
| Xem giờ máy chủ, bảng công tháng, lịch sử | Không |

🔴 **Cố ý không cho chấm công lúc mất mạng.** Giờ công phải do máy chủ ghi. Cho điện thoại "nhớ
tạm rồi gửi sau" thì ảnh chấm công bị đóng dấu sai giờ, mà tấm ảnh ấy là thứ duy nhất dùng để
đối chiếu khi có tranh cãi.

---

## 4. Khi cần dò lỗi

* `https://<trang>/cc/manifest.json` — mở ra phải thấy một khối JSON. Ra 404 → xem mục 1, ý ⚠️.
* `https://<trang>/cc/sw.js` — mở ra phải thấy mã JavaScript.
* `https://<trang>/cham-cong-online/?viec=chan_doan` — đường chẩn đoán có sẵn của plugin Chấm Công
  (bản plugin, sơ đồ bảng, giờ máy chủ). Không in gì bí mật, chụp màn hình gửi đi được.

Gỡ app khỏi máy: giữ biểu tượng → Xoá. Cài lại thì theo mục 2.

---

## 5. Ranh giới với plugin Chấm Công

Bộ này **không** đụng vào dữ liệu: không bảng nào, không hàm ghi công nào. Nó gọi đúng một hàm
`VHCC_Tram::render()` rồi chèn mấy thẻ vào phần `<head>` của kết quả. Tắt plugin này thì
`/cham-cong-online` vẫn nguyên như cũ.

Tách riêng vì hai lẽ: số bản đi đường riêng nên không tranh số với các nhánh đang vá Chấm Công,
và lớp vỏ hỏng thì không bao giờ kéo theo chỗ ghi giờ công.

Bộ thử: `tools/test/kiem-cc-app.php` (63 phép).
Biểu tượng sinh lại bằng: `python3 tools/ve-bieu-tuong-cc-app.py`.
