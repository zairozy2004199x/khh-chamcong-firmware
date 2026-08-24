#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Đọc bản ghi .sr của PulseView/sigrok, tự dò baud rồi giải mã UART.

Dùng để nhận dạng giao thức bo ghế mà không phải mò tốc độ bằng mắt.

    python3 tools/doc-sr.py bo-ghe.sr
    python3 tools/doc-sr.py bo-ghe.sr --kenh 1 3

Không cần cài sigrok. Chỉ cần Python + numpy.
"""

import argparse
import io
import re
import sys
import zipfile

try:
    import numpy as np
except ImportError:
    sys.exit('Thiếu numpy. Chạy: pip install numpy')

# Các tốc độ đáng thử, kể cả mấy tốc độ lẻ của thiết bị công nghiệp.
TOC_DO = (1200, 2400, 4800, 9600, 14400, 19200, 28800, 38400, 57600,
          76800, 115200, 230400)


def doc_sr(duong):
    """Trả về (mảng mẫu uint8, tốc độ lấy mẫu, tên từng kênh)."""
    with zipfile.ZipFile(duong) as z:
        meta = z.read('metadata').decode('utf-8', 'replace')
        khoi = [n for n in sorted(z.namelist()) if re.match(r'logic-\d+-\d+$', n)]
        # sắp theo số thứ tự chứ không theo chữ, không thì logic-1-10 chen trước logic-1-2
        khoi.sort(key=lambda n: int(n.rsplit('-', 1)[1]))
        tho = b''.join(z.read(n) for n in khoi)

    m = re.search(r'samplerate\s*=\s*([\d.]+)\s*(\w*)', meta)
    if not m:
        raise SystemExit('Không đọc được samplerate trong metadata')
    so, dv = float(m.group(1)), m.group(2).lower()
    sr = so * {'': 1, 'hz': 1, 'khz': 1e3, 'mhz': 1e6, 'ghz': 1e9}[dv]

    ten = {}
    for k, v in re.findall(r'probe(\d+)\s*=\s*(\S+)', meta):
        ten[int(k) - 1] = v

    return np.frombuffer(tho, dtype=np.uint8), sr, ten


def bit_hep_nhat(bits, sr):
    """Bề rộng (giây) của quãng giữ mức ngắn nhất — xấp xỉ một bit."""
    suon = np.flatnonzero(np.diff(bits.astype(np.int8)))
    if len(suon) < 2:
        return None
    quang = np.diff(suon)
    # bỏ 1% ngắn nhất để nhiễu gai không kéo kết quả xuống
    return float(np.percentile(quang, 1)) / sr


def giai_ma(bits, sr, baud, bit_du_lieu=8, chan_le=None, stop=1):
    """Giải mã UART. Trả về (danh sách byte, số khung lỗi)."""
    mau_moi_bit = sr / baud
    if mau_moi_bit < 3:
        return [], 10**9          # lấy mẫu quá thưa, không tin được

    n = len(bits)
    khung = 1 + bit_du_lieu + (1 if chan_le else 0) + stop
    ra, loi = [], 0
    i = 0
    while i < n - 1:
        # tìm sườn xuống = bit start
        if not (bits[i] == 1 and bits[i + 1] == 0):
            i += 1
            continue
        goc = i + 1
        if goc + mau_moi_bit * khung >= n:
            break
        lay = lambda k: bits[int(goc + mau_moi_bit * (k + 0.5))]

        gia_tri = 0
        for b in range(bit_du_lieu):
            if lay(1 + b):
                gia_tri |= 1 << b            # UART gửi bit thấp trước

        vi_tri = 1 + bit_du_lieu
        if chan_le:
            p = lay(vi_tri)
            vi_tri += 1
            so_mot = bin(gia_tri).count('1') + p
            if (chan_le == 'even') != (so_mot % 2 == 0):
                loi += 1

        if lay(vi_tri) != 1:                 # bit stop phải là mức cao
            loi += 1
            i = goc + 1
            continue

        ra.append((goc / sr, gia_tri))
        i = int(goc + mau_moi_bit * khung)
    return ra, loi


def do_kenh(bits, sr, ten):
    suon = np.flatnonzero(np.diff(bits.astype(np.int8)))
    print(f'\n=== {ten} ===')
    if len(suon) == 0:
        print('  phẳng lì, không có sườn nào — kênh không đấu hoặc bo im')
        return
    print(f'  số sườn: {len(suon)}   '
          f'vùng động: {suon[0]/sr*1e3:.1f} ms → {suon[-1]/sr*1e3:.1f} ms')

    uoc = bit_hep_nhat(bits, sr)
    if uoc:
        print(f'  quãng ngắn nhất: {uoc*1e6:.2f} µs  → baud ước chừng {1/uoc:,.0f}')

    if len(suon) < 20:
        print('  quá ít sườn để là UART — nhiều khả năng là chân báo trạng thái')
        return

    bang = []
    for baud in TOC_DO:
        for bd, cl in ((8, None), (8, 'even'), (8, 'odd'), (7, 'even'), (9, None)):
            byte, loi = giai_ma(bits, sr, baud, bd, cl)
            if byte:
                bang.append((loi / max(len(byte), 1), -len(byte), baud, bd, cl, byte, loi))
    if not bang:
        print('  không giải mã được ở tốc độ nào')
        return

    bang.sort()
    print('\n  Bảng xếp hạng (ít lỗi nhất lên trước):')
    print('   baud    bit  chẵn/lẻ   số byte   lỗi khung')
    for ti_le, am_so, baud, bd, cl, byte, loi in bang[:6]:
        print(f'   {baud:>7}  {bd}    {str(cl):<7}  {-am_so:>6}   {loi:>6}')

    _, _, baud, bd, cl, byte, loi = bang[0]
    print(f'\n  → Chọn: {baud} baud, {bd} bit dữ liệu, chẵn/lẻ = {cl}, '
          f'{len(byte)} byte, {loi} lỗi khung')
    print('\n  Byte đọc được (thời điểm ms · hex):')
    dong = []
    for t, v in byte[:200]:
        dong.append(f'{t*1e3:9.3f} {v:02X}')
    for i in range(0, len(dong), 6):
        print('   ' + '   '.join(dong[i:i + 6]))
    if len(byte) > 200:
        print(f'   … còn {len(byte)-200} byte nữa')


def main():
    p = argparse.ArgumentParser(description=__doc__,
                                formatter_class=argparse.RawDescriptionHelpFormatter)
    p.add_argument('file', help='bản ghi .sr xuất từ PulseView')
    p.add_argument('--kenh', type=int, nargs='*', help='chỉ xét mấy kênh này, ví dụ --kenh 1 3')
    tv = p.parse_args()

    mau, sr, ten = doc_sr(tv.file)
    print(f'{tv.file}: {len(mau):,} mẫu @ {sr/1e6:g} MHz = {len(mau)/sr:.3f} giây')

    danh_sach = tv.kenh if tv.kenh else range(8)
    for ch in danh_sach:
        do_kenh((mau >> ch) & 1, sr, ten.get(ch, f'D{ch}'))


if __name__ == '__main__':
    main()
