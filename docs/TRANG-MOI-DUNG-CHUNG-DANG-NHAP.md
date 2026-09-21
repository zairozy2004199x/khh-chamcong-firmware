# Trang mới dùng chung một lần đăng nhập

Anh Thắng 30/08/2026: *"Đăng nhập 1 trang nội bộ là dùng được tất cả các trang"*, rồi
*"nhưng sau trang mới thì dùng chung hết, thiết lập sẵn luôn"*.

Tài liệu này dành cho lần dựng **trang tiếp theo** của hệ. Đọc xong là cắm được, không phải
đọc lại mã của trang Nội bộ để chép.

---

## Nguyên tắc, trước khi xem mã

**Một cửa PIN nghĩa là một BỘ CHỐT, không phải một chỗ đặt ô nhập.**
Mỗi trang có ô gõ PIN riêng cũng được — miễn là ô ấy đi qua đúng `VHCC_Auth::login()`. Trang
nào tự đọc bảng người dùng rồi tự so PIN là hệ có **hai bộ đếm nhập sai rời nhau**: kẻ dò PIN
bị khoá ở trang này cứ sang trang kia dò tiếp, mà nhìn vào mã trang nào cũng thấy "có khoá".

**Lõi phiên nằm ở `VHCC_Phien`** (plugin Chấm công, `includes/class-vhcc-phien.php`) — một bản
cho cả hệ. Trang mới chỉ gọi, không chép.

**Không hàm nào của lõi `exit` hay `wp_safe_redirect`.** Nơi gọi quyết định đi đâu. Hàm có
`exit` thì bài kiểm gọi nó là bài kiểm tự chết giữa đường — mà phần đáng thử nhất của một cửa
đăng nhập chính là phần quyết định cho vào hay không.

---

## Bảy hàm cần biết

| Hàm | Trả về | Dùng khi |
|---|---|---|
| `VHCC_Phien::co()` | bool | Hệ đã sẵn sàng chưa (đã cài plugin Chấm công đủ bản) |
| `VHCC_Phien::toi()` | array \| null | Người đang đăng nhập |
| `VHCC_Phien::the()` | string | Thẻ phiên — **bí mật**, chỉ để buộc chữ ký, không in ra |
| `VHCC_Phien::xu_post( $ns )` | array | Xử một lượt POST đăng nhập / thoát |
| `VHCC_Phien::o_pin( $dat )` | HTML | Ô gõ PIN sẵn dùng |
| `VHCC_Phien::nut_thoat( $ns )` | HTML | Nút Thoát sẵn dùng |
| `VHCC_Phien::o_ky( $ns )` / `ky_dung( $ns )` | HTML / bool | Chữ ký cho **mọi** biểu mẫu POST khác của trang |

`$ns` là **không gian chữ ký** của trang: một chuỗi ngắn, ví dụ `'vhnb'`, `'vhxx'`. Đặt một lần
rồi **đừng đổi** — đổi là mọi biểu mẫu đang mở trên máy người dùng hỏng hết. Hai trang khác `$ns`
thì chữ ký khác nhau, nên biểu mẫu cắt từ trang này dán sang trang kia không dùng được.

---

## Mã mẫu — một trang mới hoàn chỉnh

```php
class VHXX_Trang {

    const NS = 'vhxx';   // không gian chữ ký — đặt một lần, đừng đổi

    public static function phuc_vu() {
        /* ⚠️ Gác class_exists + method_exists CÙNG THÂN HÀM với lời gọi.
           Luật của tools/test/kiem-goi-cheo.php: các plugin cài độc lập, bản có thể lệch
           nhau; class_exists chỉ nói CÓ PLUGIN, không nói CÓ HÀM — gọi hụt một hàm tĩnh là
           Fatal error, trắng cả trang. */
        if ( ! class_exists( 'VHCC_Phien' ) || ! method_exists( 'VHCC_Phien', 'xu_post' ) ) {
            echo '<p>Chưa cài plugin <b>Chấm công</b>, nên chưa đăng nhập được.</p>';
            return;
        }

        $viec = VHCC_Phien::xu_post( self::NS );

        /* Vào được, hoặc vừa thoát -> chuyển hướng về chính trang này.
           POST -> chuyển hướng -> GET: F5 không gửi lại biểu mẫu. */
        if ( ( 'vao' === $viec['viec'] && $viec['ok'] ) || 'ra' === $viec['viec'] ) {
            wp_safe_redirect( self::url() );
            return;
        }

        $toi = VHCC_Phien::toi();
        if ( ! $toi ) {
            /* 🔴 PIN sai thì VẼ THẲNG lại trang kèm câu báo, KHÔNG chuyển hướng và KHÔNG cất
               câu báo vào transient: khoá transient thường đặt tên theo thẻ phiên, mà chưa
               đăng nhập nghĩa là thẻ rỗng — mọi khách trên internet dùng chung một ô nhớ, và
               câu báo của người này hiện lên màn hình người kia. */
            echo '<h2>Trang XX</h2>';
            echo VHCC_Phien::o_pin( array( 'loi' => $viec['loi'] ) );
            return;
        }

        echo '<header>' . esc_html( $toi['name'] )
            . VHCC_Phien::nut_thoat( self::NS ) . '</header>';

        /* Mọi biểu mẫu POST khác của trang phải mang chữ ký. */
        echo '<form method="post">' . VHCC_Phien::o_ky( self::NS )
            . '<input type="hidden" name="viec" value="lam_gi_do">'
            . '<button>Làm gì đó</button></form>';

        if ( isset( $_POST['viec'] ) && 'lam_gi_do' === $_POST['viec'] ) {
            if ( ! VHCC_Phien::ky_dung( self::NS ) ) { return; }   // chối lượt giả mạo
            // ... làm việc ...
        }
    }
}
```

---

## Bốn chỗ dễ sai

**1. Đăng nhập phải đứng TRƯỚC chốt chữ ký.**
`ky_dung()` tính chữ ký theo thẻ phiên, mà lượt đăng nhập thì chưa có thẻ nào. Gác nhầm thứ tự
là ô PIN nằm đó nhìn thấy được nhưng không bao giờ vào nổi — và không câu báo nào giải thích.
`xu_post()` đã xếp đúng thứ tự; tự viết lấy thì nhớ.

**2. Ai vào được — cửa RỘNG, màn HẸP.**
`toi()` cho qua mọi vai mà `VHCC_Auth::vai_tro_vao()` cho vào hệ. Trang của anh muốn hẹp hơn thì
**tự gác ở từng màn**, đừng siết ở cửa. Siết ở cửa là người ta gõ đúng PIN rồi bị đá về màn đăng
nhập mà không hiểu vì sao.

Muốn chặn riêng **một người**, dùng `VHCC_Cong::duoc_vao( $toi, '<mã trang>' )` — khai ở màn
Quản lý nhân sự, khoá theo từng người. Đừng khoá theo bậc vai: chọn nhầm một lần là cả công ty
mất trang, và người bị chối không hiểu vì sao. Trang Nội bộ đã vấp đúng chỗ này hai lần.

**3. PIN không bao giờ hiện lên màn hình.**
`o_pin()` dùng `type="password"` và gõ sai thì ô về **trống**, không trả lại giá trị vừa gõ.
Trang chạy ngoài internet, ảnh chụp màn hình đi khắp nơi — dự án này đã mất một khoá cầu nối vì
một tấm ảnh.

**4. Nút Thoát là FORM POST, không phải đường dẫn.**
Một cái link đăng xuất thì chỉ cần ai đó dán địa chỉ ấy vào bảng tin — hoặc nhúng nó làm ảnh
trong một bài — là cả phòng bị đá ra.

---

## Thoát thì về đâu

`VHCC_Web::noi_ve_sau_thoat()` trả về địa chỉ nên quay lại sau khi thoát: trang Nội bộ nếu nó
đang được đặt làm trang chủ, không thì trang chấm công. Trang mới muốn về chính nó thì cứ tự
chuyển hướng — đây chỉ là mặc định của hai trang cũ.

Thoát là **thoát thật**: `VHCC_Phien::ra()` huỷ thẻ trong CSDL rồi mới xoá cookie. Mọi trang
dùng chung một thẻ, và trạm chấm công còn giữ chính thẻ ấy trong `localStorage` — xoá mỗi cookie
thì người vừa bấm Thoát trên máy chung ở cửa hàng tưởng mình đã ra.

---

## Bài kiểm

Phép thử cho lõi nằm ở `tools/test/kiem-noi-bo.php`, phần cuối — **cố ý đặt ở đó**: bài ấy là
bài duy nhất dựng được cảnh thật, một trang của **plugin khác** gọi sang lõi phiên của plugin
Chấm công. Lõi mà chỉ được thử trong chính plugin của nó thì không ai biết nó có dùng nổi từ
bên ngoài hay không.

Trang mới thì thêm phép thử của riêng nó, và ít nhất phải có:

- gõ sai 10 lượt ở trang mới thì cửa `VHCC_Auth::login()` khoá theo (chứng minh **một** bộ chốt);
- PIN sai không lọt vào câu báo lỗi;
- nút Thoát mang chữ ký, và chữ ký sai thì không thoát được ai;
- biểu mẫu POST của trang chối được lượt không có chữ ký.
