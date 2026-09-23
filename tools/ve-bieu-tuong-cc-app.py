#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
VẼ BỘ BIỂU TƯỢNG CHO APP CHẤM CÔNG (vhcp-cc-app).

    python3 tools/ve-bieu-tuong-cc-app.py

Sinh ra wordpress/vhcp-cc-app/assets/bieu-tuong-*.png. Chạy lại cho ra ĐÚNG những byte cũ
(không có dấu thời gian trong tệp), nên `git status` sạch nếu không đổi gì ở đây.

🔴 VÌ SAO ĐỂ TRÌNH SINH TRONG REPO CHỨ KHÔNG CHỈ ĐỂ MẤY TẤM PNG.
   Mấy tấm ảnh nhị phân không ai đọc được bằng `git diff`. Đổi màu thương hiệu sáu tháng nữa mà
   chỉ có PNG thì phải mở Photoshop dò lại mã màu; có tệp này thì sửa một dòng rồi chạy lại.

🔴 VÌ SAO KHÔNG SINH LÚC CHẠY BẰNG GD.
   Biểu tượng được iOS đọc ĐÚNG MỘT LẦN, lúc người ta bấm "Thêm vào MH chính". Lỡ lượt ấy trúng
   lúc hosting thiếu GD (hoặc thiếu quyền ghi thư mục đệm) thì iOS lấy đại một ảnh chụp màn hình
   làm biểu tượng, và cách duy nhất chữa là XOÁ app rồi thêm lại — không có nút làm mới nào cả.
   Tệp tĩnh thì không có lượt nào để hỏng.

⚠️ 180px LÀ CỦA iOS và nó KHÔNG đọc `icons` trong manifest.json cho màn hình chính — chỉ đọc thẻ
   <link rel="apple-touch-icon">. Bỏ cỡ này thì Android đẹp còn iPhone xấu, mà máy anh em nhân
   viên dùng phần lớn là iPhone.

⚠️ Bản `maskable` chừa lề rộng hơn hẳn. Android cắt biểu tượng theo hình của máy (tròn, vuông
   bo, giọt nước); vẽ sát mép là cụt mất chữ. Vùng an toàn là hình tròn đường kính 80% cạnh.
"""

import os
from PIL import Image, ImageDraw

NEN   = (11, 31, 58)     # #0B1F3A — navy của nhà
VANG  = (201, 168, 76)   # #C9A84C
TRANG = (255, 255, 255)

GOC = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..')
RA  = os.path.join(GOC, 'wordpress', 'vhcp-cc-app', 'assets')

# Vẽ ở cỡ lớn rồi thu nhỏ: nét cong mới mịn, không răng cưa.
PHONG = 8


def ve(canh, ti_le_noi_dung, bo_goc):
    """Một tấm biểu tượng.

    canh            cạnh ảnh cuối cùng (px)
    ti_le_noi_dung  phần cạnh mà hình đồng hồ chiếm — bản maskable để nhỏ hơn
    bo_goc          bo góc nền (0 = vuông chằn chặn, cho maskable vì máy tự cắt)
    """
    c = canh * PHONG
    im = Image.new('RGBA', (c, c), (0, 0, 0, 0))
    d = ImageDraw.Draw(im)

    # Nền. iOS tự bo góc tấm apple-touch-icon nên nền phải ĐẶC, không trong suốt:
    # ảnh trong suốt bị iOS dán lên nền đen, và biểu tượng thành một vệt tối.
    if bo_goc > 0:
        d.rounded_rectangle([0, 0, c - 1, c - 1], radius=int(c * bo_goc), fill=NEN)
    else:
        d.rectangle([0, 0, c - 1, c - 1], fill=NEN)

    # Vòng đồng hồ
    r = c * ti_le_noi_dung / 2.0
    gi = c / 2.0
    net = max(1, int(c * 0.055))
    d.ellipse([gi - r, gi - r, gi + r, gi + r], outline=VANG, width=net)

    # Hai kim, chỉ 10 giờ 10 — tư thế kim quen mắt nhất, và nó cân hai bên.
    kim = max(1, int(c * 0.05))
    d.line([gi, gi, gi, gi - r * 0.55], fill=TRANG, width=kim)          # kim phút, hướng lên
    d.line([gi, gi, gi + r * 0.42, gi + r * 0.20], fill=TRANG, width=kim)  # kim giờ
    d.ellipse([gi - kim, gi - kim, gi + kim, gi + kim], fill=TRANG)

    return im.resize((canh, canh), Image.LANCZOS)


def luu(im, ten):
    duong = os.path.join(RA, ten)
    # optimize=True + không ghi dấu thời gian -> chạy lại ra đúng byte cũ.
    im.save(duong, 'PNG', optimize=True)
    print('đã vẽ', os.path.relpath(duong, GOC), im.size)


def main():
    os.makedirs(RA, exist_ok=True)
    # Cỡ của iOS. Nền đặc, góc vuông — iOS TỰ bo, mình bo nữa là bo hai lần, viền bị gặm.
    luu(ve(180, 0.62, 0.0), 'bieu-tuong-180.png')
    # Cỡ của manifest (Android / Chrome trên máy tính).
    luu(ve(192, 0.62, 0.22), 'bieu-tuong-192.png')
    luu(ve(512, 0.62, 0.22), 'bieu-tuong-512.png')
    # Bản để máy cắt tuỳ hình: nội dung co vào trong vùng an toàn 80%.
    luu(ve(512, 0.44, 0.0), 'bieu-tuong-512-maskable.png')


if __name__ == '__main__':
    main()
