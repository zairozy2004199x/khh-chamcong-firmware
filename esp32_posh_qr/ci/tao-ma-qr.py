#!/usr/bin/env python3
"""Sinh mã QR cho hộp POSH QR — ký bằng CHÍNH khoá mà hộp đang giữ.

Đây là bản mẫu để đối chiếu: máy chủ bán vé thật (Apps Script / backend) phải ký
Y HỆT cách này, nếu không thì hộp từ chối mọi mã mà không ai hiểu vì sao.

    ký = 16 ký tự hex đầu của HMAC-SHA256(khoá, phần thân)   [8 byte]
    thân mở ghế  = "POSH1|<máy>|<phút>|<hết hạn>|<mã lượt>"
    thân đặt giờ = "POSHT|<epoch>"

Ví dụ:
    python3 tao-ma-qr.py --khoa "$KHOA" --may GHE-01 --phut 15 --han 900
    python3 tao-ma-qr.py --khoa "$KHOA" --may '*' --phut 30 --han 0 --png ve.png
    python3 tao-ma-qr.py --khoa "$KHOA" --dat-gio now

⚠️ KHOÁ KÝ là bí mật. Đừng gõ thẳng vào dòng lệnh trên máy dùng chung (lệnh còn
   nằm trong lịch sử shell) — dùng biến môi trường POSH_QR_KHOA.
"""
import argparse, hashlib, hmac, os, secrets, sys, time

NHAN_MO_GHE  = "POSH1"
NHAN_DAT_GIO = "POSHT"
PHUT_TOI_DA  = 240
MA_TOI_DA    = 24


def ky(khoa: str, than: str) -> str:
    return hmac.new(khoa.encode(), than.encode(), hashlib.sha256).hexdigest()[:16]


def ma_mo_ghe(khoa, may, phut, het_han, ma_luot):
    than = f"{NHAN_MO_GHE}|{may}|{phut}|{het_han}|{ma_luot}"
    return f"{than}|{ky(khoa, than)}"


def ma_dat_gio(khoa, epoch):
    than = f"{NHAN_DAT_GIO}|{epoch}"
    return f"{than}|{ky(khoa, than)}"


def main():
    p = argparse.ArgumentParser(description="Sinh mã QR cho hộp POSH QR")
    p.add_argument("--khoa", default=os.environ.get("POSH_QR_KHOA", ""),
                   help="khoá ký (mặc định lấy biến môi trường POSH_QR_KHOA)")
    p.add_argument("--may", default="*", help='mã ghế, hoặc "*" = mọi ghế (mặc định "*")')
    p.add_argument("--phut", type=int, default=15, help="số phút cho ghế chạy (1..240)")
    p.add_argument("--han", type=int, default=900,
                   help="mã sống được bao nhiêu GIÂY kể từ bây giờ; 0 = không hết hạn")
    p.add_argument("--ma", default=None, help="mã lượt (mặc định: sinh ngẫu nhiên)")
    p.add_argument("--dat-gio", default=None,
                   help='sinh mã ĐẶT GIỜ cho hộp: "now" hoặc một số epoch')
    p.add_argument("--png", default=None, help="ghi mã QR ra file PNG (cần: pip install qrcode)")
    p.add_argument("--im", action="store_true", help="chỉ in đúng chuỗi mã, không in gì thêm")
    a = p.parse_args()

    if not a.khoa:
        sys.exit("Thiếu khoá ký. Dùng --khoa hoặc đặt biến môi trường POSH_QR_KHOA.")

    if a.dat_gio is not None:
        epoch = int(time.time()) if a.dat_gio == "now" else int(a.dat_gio)
        chuoi = ma_dat_gio(a.khoa, epoch)
        mo_ta = f"đặt giờ hộp = {epoch} ({time.strftime('%Y-%m-%d %H:%M:%S', time.localtime(epoch))})"
    else:
        if not 1 <= a.phut <= PHUT_TOI_DA:
            sys.exit(f"--phut phải từ 1 tới {PHUT_TOI_DA}")
        ma_luot = a.ma or secrets.token_hex(6)
        if len(ma_luot) > MA_TOI_DA or not all(c.isalnum() or c in "-_" for c in ma_luot):
            sys.exit(f"--ma chỉ được gồm chữ/số/-/_ và tối đa {MA_TOI_DA} ký tự")
        het_han = 0 if a.han == 0 else int(time.time()) + a.han
        chuoi = ma_mo_ghe(a.khoa, a.may, a.phut, het_han, ma_luot)
        mo_ta = (f"ghế {a.may} · {a.phut} phút · mã lượt {ma_luot} · "
                 + ("không hết hạn" if het_han == 0
                    else "hết hạn " + time.strftime('%H:%M:%S', time.localtime(het_han))))

    if a.im:
        print(chuoi)
    else:
        print(mo_ta)
        print(chuoi)
        print(f"({len(chuoi)} ký tự)")

    if a.png:
        try:
            import qrcode
        except ImportError:
            sys.exit("Cần thư viện qrcode:  pip install qrcode[pil]")
        qrcode.make(chuoi).save(a.png)
        if not a.im:
            print(f"đã ghi {a.png}")


if __name__ == "__main__":
    main()
