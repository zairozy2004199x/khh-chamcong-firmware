#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Soát số ô định dạng của Serial.printf có khớp số tham số không.

VÌ SAO CÓ TỆP NÀY
24/08/2026, khi thêm chế độ tự quét vào bản giả lập MDB, em sửa chuỗi định dạng nhưng để sót
một tham số thừa. printf không báo lỗi — nó lặng lẽ đẩy mọi con số sau đó lệch đi một bậc, nên
"nghe 12232" thật ra in ra giá trị của khung đáp. Không trình biên dịch nào bắt, không phép thử
nào chạm, mà người đọc thì tin vào con số sai.

Chạy: python3 tools/test/fw/kiem-printf.py <tệp.ino>...
"""
import re
import sys


def tach_chuoi(s):
    """Trả về (nội dung chuỗi định dạng, phần còn lại) từ đầu một lời gọi printf.

    Gộp các chuỗi nằm liền nhau — C nối chúng lại, ví dụ "a" "b" là một chuỗi.
    """
    i = 0
    noi_dung = []
    while i < len(s):
        while i < len(s) and s[i] in ' \n\t':
            i += 1
        if s.startswith('F(', i):
            i += 2
            continue
        if i >= len(s) or s[i] != '"':
            break
        i += 1
        while i < len(s) and s[i] != '"':
            if s[i] == '\\':
                i += 1
            noi_dung.append(s[i])
            i += 1
        i += 1
        j = i
        while j < len(s) and s[j] in ' \n\t)':
            if s[j] == ')':
                j += 1
                break
            j += 1
        if j < len(s) and s[j] == '"':
            i = j
            continue
        break
    return ''.join(noi_dung), s[i:]


def dem_tham_so(s):
    """Đếm tham số sau chuỗi định dạng. Bỏ qua dấu phẩy trong chuỗi, trong ngoặc."""
    muc = 0
    trong_chuoi = False
    so = 0
    co_gi_do = False
    for k, ch in enumerate(s):
        if trong_chuoi:
            if ch == '"' and s[k - 1] != '\\':
                trong_chuoi = False
            continue
        if ch == '"':
            trong_chuoi = True
            co_gi_do = True
        elif ch in '([':
            muc += 1
            co_gi_do = True
        elif ch in ')]':
            muc -= 1
        elif ch == ',' and muc == 0:
            so += 1
            co_gi_do = False
        elif ch not in ' \n\t':
            co_gi_do = True
    if co_gi_do:
        so += 1
    return so


O = re.compile(r'%(?!%)[-+ 0-9.#hl]*[diuoxXfFeEgGcspn]')


def soat(duong):
    s = open(duong, encoding='utf-8').read()
    hong = 0
    for m in re.finditer(r'Serial\.printf\(', s):
        # lấy trọn lời gọi, đếm ngoặc để không cắt giữa chừng
        i = m.end()
        muc, trong_chuoi, dau = 1, False, i
        while i < len(s) and muc:
            c = s[i]
            if trong_chuoi:
                if c == '\\':
                    i += 1
                elif c == '"':
                    trong_chuoi = False
            elif c == '"':
                trong_chuoi = True
            elif c == '(':
                muc += 1
            elif c == ')':
                muc -= 1
            i += 1
        khoi = s[dau:i - 1]

        fmt, con_lai = tach_chuoi(khoi)
        if not fmt:
            continue
        con_lai = con_lai.lstrip()
        if con_lai.startswith(','):
            con_lai = con_lai[1:]
            ts = dem_tham_so(con_lai)
        else:
            ts = 0
        o = len(O.findall(fmt))
        if o != ts:
            dong = s[:dau].count('\n') + 1
            print(f'  ⚠ {duong}:{dong}  {o} ô định dạng nhưng {ts} tham số')
            print(f'      "{fmt[:70]}"')
            hong += 1
    return hong


def main():
    if len(sys.argv) < 2:
        sys.exit(__doc__)
    tong = sum(soat(f) for f in sys.argv[1:])
    if tong:
        print(f'\nprintf: {tong} chỗ LỆCH — con số in ra sẽ sai bậc.')
        sys.exit(1)
    print('  printf: SẠCH — mọi lời gọi khớp số tham số.')


if __name__ == '__main__':
    main()
