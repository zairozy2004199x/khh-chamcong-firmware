# Vận Hành cơ sở — plugin `vhcp-van-hanh`

*Bản 1.3.0. Dựng lại app "Vận Hành Nhà Ma" (trước chạy trên Firebase) thành plugin WordPress.*

> **Đây là một trang ĐỨNG RIÊNG**, không phải một ô nhỏ trong Cổng K&H: thanh dọc bên trái chia
> nhóm, đủ 16 mục, đa cơ sở — giống bố cục app cũ.
>
> ✅ **Bản 1.3.0 đã dựng xong cả 12 màn riêng của trang này.** Bốn mục còn lại trên thanh dọc
> (Chấm công · Đăng ký lịch làm · Đổi ca · Báo cáo đi muộn) là **liên kết sang plugin Chấm Công** —
> cố ý không dựng lại, vì bên ấy đã có sổ thật đang chạy.

| Địa chỉ | Ai dùng |
|---|---|
| `khmatrix.com/van-hanh` | Nhân viên · Thu ngân · Cửa hàng trưởng · Quản lý — đăng nhập bằng **PIN chấm công** |

---

## 1. Cài

1. wp-admin → Plugin → Cài mới → Tải plugin lên → `dist/vhcp-van-hanh.zip` → Kích hoạt.
2. Cài đè `dist/vhcp-trang-chu.zip` (1.7.0) để nút **🏪 Vận Hành Cơ Sở** hiện trên Cổng K&H.

> ⚠️ **Bắt buộc có plugin Chấm Công.** Trang này **không có cửa đăng nhập riêng** — nó mượn PIN,
> sổ nhân sự, vai trò, cơ sở và bảng phiên của `vhcp-cham-cong`. Thiếu nó thì trang nói thẳng ra
> chứ không hiện màn trắng.

Không phải khai gì thêm. Bảng tự dựng lúc kích hoạt.

---

## 2. 🔴 Vì sao dựng lại, chứ không phải vì thích WordPress hơn

Bản Firebase (`index.html`, 6.451 dòng) khoá cửa **bằng JavaScript**:

| Chỗ | Bản Firebase làm gì | Hậu quả |
|---|---|---|
| Danh tính | Đọc thẳng từ `localStorage`, không kiểm lại | Gõ một dòng vào Console là **thành Quản lý**, không cần mật khẩu |
| Mật khẩu | Tải chuỗi băm về máy khách, băm **SHA-256 không muối**, so **trong trình duyệt** | Chuỗi băm ai cũng đọc được; mật khẩu kiểu `mailinh1234.` dò ra trong vài giây |
| Firebase Auth | `signInAnonymously()` | Ai mở trang cũng là "người dùng hợp lệ" |
| Tiền | Cộng ở máy khách rồi **ghi thẳng con số ấy** | Gọi thẳng API khai 500 vé mà tổng thu 0đ — sổ kế toán nói dối đúng con số mang đi đối chiếu ngân hàng |
| Dự phòng khi không có `crypto.subtle` (chạy qua `http://`) | Rơi xuống một hàm băm 32 bit tự chế | Trùng nhau như cơm bữa |

Ở bản này **mọi câu hỏi "anh là ai, được làm gì" đều hỏi máy chủ**. Trình duyệt chỉ giữ một chuỗi
thẻ phiên; sửa biến trong trình duyệt không đổi được gì.

### Không dựng lại sổ nhân sự

`vhcp-cham-cong` đã giữ nhân sự, PIN, vai trò, cơ sở, phiên, chấm công, đơn xin đi muộn — và đang
chạy thật. Plugin này mượn hết. Hai sổ song song thì:

- nghỉ việc một người phải nhớ xoá hai nơi — quên một nơi là người đã nghỉ vẫn vào được;
- đổi PIN một nơi không đổi nơi kia, nhân viên gõ PIN mới vào trang cũ bị chối mà không hiểu;
- hai danh sách cơ sở lệch tên nhau, báo cáo cộng theo cơ sở ra hai kết quả khác nhau.

---

## 3. Vai trò

Vai trò bên chấm công tự đổi sang vai ở đây:

| Bên chấm công | Ở đây | Làm được gì |
|---|---|---|
| Admin · Quản lý | `quan_ly` | Mọi cơ sở · duyệt · **mở lại báo cáo đã chốt** |
| Cửa hàng trưởng | `cua_hang_truong` | Cơ sở của mình · ghi và duyệt doanh thu |
| Kế toán cá nhân · Kế toán NCC | `thu_ngan` | Cơ sở của mình · ghi doanh thu (phải chờ duyệt) |
| Nhân viên | `nhan_vien` | Xem tổng quan · báo sự cố |

> 🔴 **Vai lạ rơi xuống `nhan_vien`, không phải `quan_ly`.** Gõ sai chính tả một vai trò bên chấm
> công mà lại rơi vào nhánh quyền cao là cả doanh thu mở toang, và không có câu báo nào.

> 🔴 **Chỉ Quản lý xem được mọi cơ sở.** Cửa hàng trưởng cơ sở A mở sổ tiền cơ sở B là chuyện
> không ai muốn giải thích. Chặn này nằm **trong câu SQL**, không phải ở trình duyệt — ẩn cái ô
> chọn cơ sở đi thì gọi thẳng API là qua.

---

## 4. Doanh thu & Chi phí

Một cơ sở · một ngày · **một** bản ghi. Khoá duy nhất `(coso, ngay)` nằm ở **CSDL**: hai người
cùng bấm Lưu lúc giao ca thì người sau ghi đè người trước, ra đúng một dòng. Chặn bằng PHP thôi
("đã có chưa rồi mới ghi") thì hai lượt song song đều thấy "chưa có" rồi cùng chèn — hai dòng cho
một ngày, và mọi báo cáo tháng từ đó **gấp đôi**.

**Trang chỉ gửi SỐ LƯỢNG vé.** Đơn giá và mọi phép cộng làm ở máy chủ. Gửi kèm `tong_thu` là bị
bỏ qua — có phép thử canh đúng chuyện đó.

Hai luật đếm dễ sai:

- **Vé online không vào doanh thu cơ sở** — tiền ấy khách trả cho sàn, cộng vào là cuối tháng đối
  soát thiếu tiền mặt mà không ai hiểu vì sao. Nhưng **vẫn tính là lượt khách**.
- **Bùa chú và trích cam không tính là lượt khách** — món bán thêm cho người đã vào, đếm nữa là
  số khách nhân đôi.

Vòng đời:

```
Thu ngân nộp        -> Chờ duyệt      (người ghi tiền ≠ người duyệt tiền)
CHT/Quản lý ghi     -> Đã duyệt luôn  (họ chính là người duyệt)
CHT bấm DUYỆT       -> Đã duyệt       -> KHOÁ, không sửa được nữa
Quản lý bấm Mở lại  -> Chờ duyệt      (và XOÁ tên người duyệt cũ)
```

> 🔴 **Mở lại thì xoá tên người duyệt cũ.** Giữ lại là màn hình nói "đã duyệt bởi X" trong khi bản
> ghi đang mở cho người khác sửa — X chịu trách nhiệm cho con số họ không ký.

Ba ô *tiền mặt / chuyển khoản / momo* lệch doanh thu thì màn hình **nói ra chứ không chặn**: cuối
ca lệch vài nghìn là chuyện thật, chặn cứng thì người ta bịa số cho khớp, và lúc ấy sổ sạch mà
tiền thì không.

---

## 5. Sự cố

Bắt buộc có **người phụ trách** và **hạn xử lý**. Việc không gắn tên ai thì thành cái danh sách ai
cũng đọc và không ai làm; việc không có hạn thì không bao giờ trễ, nên cũng không bao giờ được
nhắc.

Trễ hạn tính ở **máy chủ** — máy khách có thể sai ngày, và một cái đồng hồ lệch là cả màn hình đỏ
hoặc cả màn hình xanh sai. Hạn **hôm nay** thì chưa tính là trễ.

Ai cũng đóng được sự cố **của cơ sở mình** — người sửa xong cái đèn là người biết nó đã xong; bắt
chờ quản lý bấm thì danh sách lúc nào cũng đỏ và không ai tin nó nữa. Người cơ sở khác thì không,
kể cả khi biết id.

---

## 6. Phép thử

```bash
php tools/test/kiem-van-hanh.php      # 376 — quyền, tiền, điểm, mọi màn, khuôn dbDelta
node tools/test/bam-thu-van-hanh.js   # 76  — BẤM THẬT trong Chromium
```

Cả hai đã **đột biến ngược** để chắc chúng thật sự cắn:

| Phá cái gì | Trượt |
|---|---|
| Nhận `tong_thu` do trang gửi | 1 |
| Vai lạ → `quan_ly` | 3 |
| Bỏ chặn cơ sở | 14 |
| Thu ngân tự duyệt | 6 |
| Bỏ khoá báo cáo đã duyệt | 1 |
| Bỏ bắt buộc người phụ trách | 3 |
| Trang tự gửi kèm tổng tiền | 1 |
| Bỏ thẻ khỏi header | 1 |
| Vé online cộng vào doanh thu trên màn hình | 3 |
| Chỉ tiêu 0 trả 0 thay vì null | 1 |
| Chưa đủ dữ liệu bị dán nhãn "có vấn đề" | 1 |
| Checklist chưa có thì trả 0% | 1 |
| Ai cũng đặt được chỉ tiêu | 1 |
| Gộp hai cột một dòng trong CREATE TABLE | 2 |
| Checklist nhận cả khoá bịa | 1 |
| Checklist tin số `xong` do trang gửi | 2 |
| Kho không quy ngày về thứ Hai | 4 |
| Kho không kẹp % tình trạng | 2 |
| Kho không báo lệch so tuần trước | 2 |
| Trang gửi `0` cho món chưa đếm | 1 |
| Trang tự gửi số `xong` của checklist | 1 |
| Mức phạt không tăng theo lần 1/2/3 | 3 |
| Lỗi nghiêm trọng vẫn xếp Tốt | 1 |
| Cho xoá dòng vi phạm ở giữa dãy | 2 |
| Bậc thưởng TikTok tra từ thấp lên | 3 |
| Không chặn hoá đơn trích cam trùng | 2 |
| CSV không chặn công thức Excel | 2 |
| CSV thiếu BOM | 1 |
| CHT gửi được thông báo toàn hệ | 1 |
| Trang tự gửi điểm & mức phạt | 1 |
| Chọn "Khen" mà danh sách nội dung không đổi | 2 |
| Trang tự gửi tiền thưởng TikTok | 1 |
| CHT thấy ô gửi thông báo toàn hệ | 1 |

---

## 6b. Điểm sức khoẻ cơ sở

Trung bình của những mảnh **có dữ liệu**: doanh thu/chỉ tiêu · checklist · sự cố.
**90–100 Tốt · 70–89 Cần chú ý · dưới 70 Có vấn đề.**

> 🔴 **Mảnh chưa có dữ liệu thì bỏ qua khi tính, không tính là 0 điểm.** Cơ sở chưa được Quản lý
> đặt chỉ tiêu mà bị chấm 0 cho mảnh ấy thì điểm tụt xuống vùng đỏ vì **một việc người khác chưa
> làm** — và cửa hàng trưởng ở đó không có cách nào sửa. Cùng lý do: cơ sở chưa nhập gì thì ghi
> *"Chưa đủ dữ liệu"*, không dán nhãn đỏ; checklist chưa ai báo cáo thì hiện `—`, không hiện `0%`
> (0% đọc ra là "làm tệ", còn sự thật là "chưa ai báo cáo").

Chỉ tiêu doanh thu tháng: màn Tổng quan → nút **Chỉ tiêu** ở cuối mỗi dòng (**chỉ Quản lý**).
Đặt `0` là **gỡ** chỉ tiêu, và mảnh ấy rơi khỏi phép tính điểm.

---

## 6c. Checklist đầu / cuối ngày

Danh mục mặc định lấy nguyên từ bản đang chạy: **4 khu · 32 mục** (quầy thu ngân · phòng kĩ thuật ·
phòng kho–phòng hù · các phòng diễn + hành lang). Mỗi cơ sở khai được danh mục riêng; chưa khai thì
dùng bản chung.

- **Đầu ngày và cuối ngày là hai bản ghi riêng.** Xong đầu ngày không có nghĩa cuối ngày cũng xong.
- **Không khoá sau khi lưu** — khác doanh thu. Người ta tích dần trong ca rồi bổ sung nốt mục quên;
  khoá lại là lần sau họ chờ xong hết mới tích một lượt, và cái danh sách mất tác dụng nhắc việc.
- **Số mục xong do máy chủ đếm**, và **chỉ đếm khoá có thật trong danh mục**. Nhận khoá lạ thì gọi
  thẳng API nhét 50 khoá bịa là `xong` vọt lên 50 trong khi cơ sở chưa làm gì — mà điểm sức khoẻ
  lại ăn theo đúng con số ấy.
- Trạng thái lưu theo **khoá** mục, không theo vị trí trong mảng. Lưu theo vị trí thì thêm một mục
  vào giữa danh mục là mọi bản ghi tháng trước lệch hết một nấc.
- `tong` đếm theo danh mục **lúc ghi**. Thêm mục mới thì báo cáo hôm qua vẫn là "28/28 xong" chứ
  không tụt thành "28/31" — người làm hôm qua không bỏ sót gì, mục ấy lúc đó chưa tồn tại.

---

## 6d. Kiểm tra kho

Đếm **theo tuần**. Hai kiểu theo dõi, và đừng gộp làm một:

| Kiểu | Dùng cho | Vì sao tách |
|---|---|---|
| **Đếm số lượng** | tivi, loa, chổi | Mất là mất, không có "mất một nửa" |
| **Đếm + % còn dùng được** | tủ thờ, tủ trang điểm | Hao mòn dần: vẫn đủ 2 cái nhưng một cái sắp bung. Chỉ đếm số lượng thì đến lúc nó gãy giữa buổi diễn mới biết, mà sổ vẫn ghi "đủ 2" |

> 🔴 **Máy chủ quy mọi ngày về thứ Hai của tuần ấy.** Nhận thẳng ngày do trang gửi thì hai người
> kiểm cùng một tuần mà gửi hai ngày khác nhau là ra **hai bản ghi cho một tuần** — khoá duy nhất
> `(coso, tuan_tu)` không cứu được, vì hai giá trị `tuan_tu` khác nhau thật.

> 🔴 **Ô số lượng để trống thì không gửi món ấy lên.** Gửi `0` nghĩa là *"đếm rồi, còn 0 cái"* —
> khác hẳn *"chưa đếm tới"*. Nhập hai chuyện ấy làm một thì sổ kho báo mất sạch đồ.

Màn hình có bảng **Lệch so với tuần trước** — thứ người ta thật sự muốn biết. Chỉ nhìn con số tuần
này thì không ai phát hiện tuần trước 6 cái chổi giờ còn 2.

---

## 6e. Đánh giá nhân viên

Bảng phạt lấy từ *BẢNG PHẠT GHOST BRIDE* và *CHI TIẾT VẬN HÀNH GHOST BRIDE*: 4 nhóm, 31 lỗi, mỗi
lỗi một mức độ và mức phạt theo lần 1 / 2 / 3.

Bắt đầu **100 điểm**, trừ theo mức (nhẹ 2 · trung bình 5 · nặng 10 · nghiêm trọng 25), **lần 2
nặng gấp rưỡi, lần 3 trở đi gấp đôi**, cộng lại khi có khen.

> 🔴 **Lần thứ mấy chốt LÚC GHI, không tính lúc đọc.** Tính lúc đọc thì xoá một dòng cũ là mọi
> dòng sau nó tụt một bậc phạt — trong khi biên bản đã ký và người ta đã nộp tiền theo mức cũ.
> Cùng lý do, **không xoá được dòng ở giữa dãy**: xoá thì mấy dòng sau mang "lần thứ mấy" sai.

> 🔴 **Có một lỗi mức Nghiêm trọng thì tối đa chỉ đạt Trung bình**, dù điểm còn cao. Gian lận
> doanh thu một lần mà vẫn xếp Tốt vì tháng ấy không vi phạm gì khác thì bảng xếp loại này chẳng
> còn nghĩa gì.

> ⚠️ **Mức phạt tiền chỉ để hiện tham khảo.** Plugin không đụng gì tới lương — trừ tiền là việc
> của người làm lương, và một phần mềm tự trừ lương thì sai một lần là mất lòng tin vĩnh viễn.

Chỉ **Cửa hàng trưởng trở lên** ghi được. Nhân viên tự ghi vi phạm cho nhau là cái sổ này thành
chỗ đấu đá, và không ai tin con số cuối kỳ nữa.

---

## 6f. TikTok & Trích cam

**TikTok** — thưởng theo bậc lượt xem, máy chủ tra bậc rồi tự tính tiền; trang chỉ gửi con số lượt
xem. Bắt buộc có **đường dẫn clip**: thưởng trả theo lượt xem tự khai, không có link thì không ai
kiểm được clip có thật không. Cửa hàng trưởng **chốt** clip lại thì nhân viên không sửa lượt xem
nữa.

> 🔴 Bậc tra từ **cao xuống thấp**. Tra từ thấp lên thì clip 1 triệu view cũng chỉ được bậc đáy.

**Trích cam** — một hoá đơn chỉ đăng ký **một lần trong cùng cơ sở**: đăng ký hai lần là một lượt
đếm đôi, mà khoản ấy tính vào thành tích của nhân viên. Hai cơ sở đánh số hoá đơn riêng nên cùng
số ở cơ sở khác thì vẫn cho.

---

## 6g. Thông báo & Giao việc

Hai thứ khác nhau, cố ý để cạnh nhau:

| | Thông báo | Giao việc |
|---|---|---|
| Gửi cho | nhiều người | **một** người |
| Có hạn | không | **bắt buộc** |
| Đánh dấu xong | không | có |

> 🔴 **Đừng dùng Thông báo để giao việc.** *"Nhờ mọi người làm X trước thứ Sáu"* gửi cho mười
> người là việc của không ai cả: không ai thấy tên mình, không có gì để đánh dấu xong, và đến thứ
> Sáu không có cách nào biết nó có được làm hay không.

Thông báo **toàn hệ** (mọi cơ sở) chỉ Quản lý gửi được — cửa hàng trưởng gửi toàn hệ thì bảng tin
đầy thông báo nội bộ của một cơ sở. Người **nhận** việc tự đánh dấu xong được; bắt chờ quản lý bấm
thì danh sách lúc nào cũng đỏ và không ai tin nó nữa.

---

## 6h. Xuất báo cáo

Sáu loại, ra tệp **CSV** mở được bằng Excel · Google Sheet · LibreOffice.

> 🔴 **Chỉ xuất phần người xuất được phép xem.** Cửa hàng trưởng xuất ra cũng chỉ có cơ sở của
> mình — lộ sổ tiền qua đường tệp là một cửa hậu mà chốt lọc trên màn hình không che được.

> 🔴 **Ô bắt đầu bằng `=`, `+`, `-`, `@` bị chặn bằng một dấu nháy đơn.** Excel hiểu mấy ô ấy là
> **công thức** — đó là đường để nhét công thức độc vào máy người mở tệp.

> 🔴 **Có dấu BOM ở đầu tệp.** Thiếu nó thì Excel trên Windows đọc UTF-8 thành ký tự rác
> (`GHOST HOUSE - GO BÃ€ Rá»ŠA`), và người nhận nghĩ dữ liệu hỏng chứ không nghĩ tại Excel.

*Sự cố* và *Giao việc* xuất **toàn bộ**, không cắt theo kỳ: một sự cố mở từ tháng trước mà chưa
đóng thì nó vẫn là việc của tháng này.

---

## 7. ✅ Đã dựng xong 12 màn

Tổng quan · Doanh thu & Chi phí · Sự cố · Checklist · Kiểm tra kho · Đánh giá nhân viên ·
Thống kê TikTok · Thống kê trích cam · Thông báo · Giao việc · Xuất báo cáo.

Bốn mục **Chấm công · Đăng ký lịch làm · Đổi ca · Báo cáo đi muộn** trên thanh dọc là **liên kết
sang plugin Chấm Công**, cố ý không dựng lại ở đây — bên ấy đã có sổ thật đang chạy.


## 8. App Firebase cũ — ngừng dùng

Quyết ngày 16/09/2026, anh Thắng: *"dữ liệu cũ chưa có gì đâu, dùng mới trên web này luôn"*.

- **Không chuyển dữ liệu** — app cũ chỉ có dữ liệu thử.
- **Ngừng nhập vào app Firebase.** Hai sổ doanh thu tách biệt thì nhập bên này bên kia không
  thấy, và cuối tháng phải ngồi ghép tay.
- **Gỡ hoặc khoá app cũ lại.** Nó vẫn còn lỗ đăng nhập nói ở mục 2; để đấy mà quên thì một ngày
  nào đó có người nhập số thật vào nhầm chỗ.

---

Chưa chạy thử trên hosting thật: bản này mới chạy trong bộ thử và trong Chromium ở máy dựng.
