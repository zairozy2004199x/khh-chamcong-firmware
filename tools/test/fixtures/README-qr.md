# Ma tran QR chuan — moc NGOAI cho bo ve trong repo

## Vi sao co thu muc nay

Ngay 23/08/2026, `VHG_QRVe` dat 15 bit thong tin dinh dang **soi guong** so voi ban dac ta:
nua bit thap le ra chay DOC cot 8 thi chay NGANG hang 8, va nguoc lai.

Hau qua: **moi ma QR trang web tung sinh ra deu khong quet duoc** — ma tra tien cua don mua ma,
va ca tem in dan tren ghe. May quet doc ra mot muc sua loi va mot mat na SAI, go mat na sai,
nhan ve toan rac, roi lang le khong nhan.

Bo thu luc do bao **sach 100%**. No co ba lop, ma lop manh nhat la "ve ra roi doc nguoc" —
`doc()` doc thong tin dinh dang tu **dung nhung o sai** ma `ma_tran()` ghi vao. Hai ben khop
nhau hoan hao. Vong tron khep kin.

> Mot bo thu tu noi chuyen voi chinh no thi khong bao gio phat hien duoc minh dang noi sai
> ngon ngu. Phai co mot moc NGOAI.

Do la ly do ton tai cua thu muc nay.

## Cach sinh lai

```bash
pip install segno zxing-cpp pillow
python3 - <<'PY'
import segno, zxingcpp
from PIL import Image
t = "00020101021238560010A0000007270126000697041501121088785839510208QRIBFTTA5303704540490005802VN62190815SEVQR MUA8C4GEM6304A1B4"
q = segno.make_qr(t, error='L', boost_error=False)
rows = [''.join('1' if c else '0' for c in r) for r in q.matrix]

# TU KIEM truoc khi dem lam chuan: mot bo GIAI MA THAT phai doc lai dung chuoi goc.
n, px, lang = len(rows), 6, 4
tt = (n + lang*2) * px
im = Image.new('L', (tt, tt), 255)
for y in range(n):
    for x in range(n):
        if rows[y][x] == '1':
            for dy in range(px):
                for dx in range(px):
                    im.putpixel(((x+lang)*px+dx, (y+lang)*px+dy), 0)
kq = zxingcpp.read_barcodes(im)
assert kq and kq[0].text == t, 'ma chuan tu no khong doc duoc!'
print('\n'.join(rows))
PY
```

`segno` la bo **ma hoa** doc lap. `zxing-cpp` la bo **giai ma** that — chinh thu vien nhieu app
ngan hang dung. Ma tran chi duoc cat vao repo sau khi zxing doc lai dung chuoi goc.

## Luu y khi doi chieu

**Dung so sanh tung o voi moi chuoi.** Viec chon mat na la mot phep cham diem theo bon luat
phat; hai bo ma hoa dung dan van co the chon mat na khac nhau, va **mat na khac van la ma hop
le** (may quet doc mat na tu thong tin dinh dang). So tung o chi dung cho dung chuoi trong tep
moc nay, noi hai ben da duoc xac nhan la chon cung mat na.

Muon canh rong hon thi canh **thong tin dinh dang doc theo toa do ban dac ta** — xem lop 5
trong `test-ghe.php`. Lop do bat dung kieu loi nay ma khong phu thuoc mat na.
