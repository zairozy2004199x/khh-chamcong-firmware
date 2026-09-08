#!/usr/bin/env python3
"""Gói web/nha-ma-so-13.html thành một file HTML chạy độc lập, up lên host nào cũng được.

Bản gốc viết cho Artifact của Claude: nó nhận <!doctype>, <head>, <body> từ bên ngoài.
Script này bọc thêm phần đó vào rồi ghi ra web/dist/.

    python3 web/dung.py
"""
import io, os, sys

GOC = os.path.join(os.path.dirname(__file__), "nha-ma-so-13.html")
RA  = os.path.join(os.path.dirname(__file__), "dist", "nha-ma-so-13.html")

DAU = """<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="description" content="Nhà Ma Số 13 — 13 Hàng Lược, Hà Nội. Đặt vé ba đêm 30/10 – 01/11.">
<meta name="theme-color" content="#12100E">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Ctext y='26' font-size='26'%3E%F0%9F%95%AF%3C/text%3E%3C/svg%3E">
<style>
:root{color-scheme:dark}
*{-webkit-tap-highlight-color:transparent}
body{margin:0}
img{max-width:100%}
[hidden]{display:none!important}
</style>
"""
CUOI = """
</body>
</html>
"""

def main():
    if not os.path.exists(GOC):
        sys.exit("khong thay " + GOC)
    than = io.open(GOC, encoding="utf-8").read()
    # <title>/<style> của bản gốc thuộc <head>; phần thân bắt đầu ở <div class="boc"
    moc = '<div class="boc" id="trangBan">'
    if moc not in than:
        sys.exit("khong thay moc than trang trong " + GOC)
    dau_than = than.index(moc)
    os.makedirs(os.path.dirname(RA), exist_ok=True)
    io.open(RA, "w", encoding="utf-8").write(
        DAU + than[:dau_than] + "</head>\n<body>\n" + than[dau_than:] + CUOI)
    print("da ghi", RA, os.path.getsize(RA), "bytes")

if __name__ == "__main__":
    main()
