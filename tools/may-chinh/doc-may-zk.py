#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
MÁY CHÍNH TẠI CƠ SỞ — đọc log từ các đầu đọc ZKTeco rồi đẩy về website.

===============================================================================================
Anh Thắng 27/08/2026: *"máy chính tại cơ sở gửi dữ liệu công về"*, rồi *"bắt đầu kết nối máy
chấm công để tránh mất dữ liệu"*.
===============================================================================================
🔴 VÌ SAO PHẢI CÓ CON NÀY, THAY VÌ WEB TỰ GỌI VÀO MÁY.
   Mấy đầu đọc nằm ở 192.168.0.20-24 — địa chỉ MẠNG NỘI BỘ của xưởng. Website chạy ngoài
   internet không có đường nào gọi vào đó, và cũng KHÔNG NÊN có: mở một lối từ internet vào
   mạng nội bộ để đọc chấm công là mở luôn cho mọi thứ khác.
   Nên giữ nguyên chiều "máy tự gọi ra": một máy tính đứng SẴN TRONG mạng ấy (chính cái máy
   đang chạy HR V5.2) đọc log qua LAN rồi POST về đúng cổng của web.

🔴 KHÔNG XOÁ LOG TRÊN MÁY. Đầu đọc giữ được hàng chục nghìn lượt; xoá đi là mất đường đối chiếu
   khi web và máy lệch nhau, mà lệch thì sớm muộn sẽ có. Con này chỉ ĐỌC.

🔴 MỐC CHỈ DỜI KHI WEB NÓI ĐÃ NHẬN. Web trả lỗi (mạng đứt, cổng đóng, khoá sai) mà vẫn dời mốc
   là mất trắng phần vừa đọc, im lặng, và không ai biết cho tới lúc tính lương. Tệp `moc.json`
   giữ mốc theo TỪNG máy, ghi lại sau mỗi lô gửi thành công.

⚠️ ĐẨY LẠI BAO NHIÊU LẦN CŨNG KHÔNG SAO. Cổng bên web chỉ NỚI giờ vào/giờ ra, gặp lượt trùng
   thì bỏ, và từ 2.71.0 nó còn không đè lên ô người ta đã sửa hoặc bù. Nên khi nghi ngờ thì cứ
   chạy lại với `--tu` lùi vài ngày — thà gửi thừa còn hơn thiếu.

===============================================================================================
CÀI ĐẶT (máy Windows ở cơ sở, một lần)
===============================================================================================
    py -m pip install pyzk requests
    copy cau-hinh.mau.json cau-hinh.json     rồi sửa IP máy và KHOÁ

CHẠY THỬ MỘT LẦN — chưa gửi gì, chỉ đọc và đếm:
    py doc-may-zk.py --thu

CHẠY THẬT:
    py doc-may-zk.py

ĐỌC LẠI TỪ MỘT NGÀY CỤ THỂ (khi nghi mất dữ liệu):
    py doc-may-zk.py --tu 2026-08-01

CHẠY MỖI 10 PHÚT: Task Scheduler của Windows, trỏ vào chính lệnh trên. KHÔNG cần chạy nền —
mỗi lượt tự đọc, tự gửi, tự thoát.
"""

import argparse
import datetime as dt
import json
import os
import sys

THU_MUC = os.path.dirname(os.path.abspath(__file__))
TEP_CAU_HINH = os.path.join(THU_MUC, 'cau-hinh.json')
TEP_MOC = os.path.join(THU_MUC, 'moc.json')

# Trần một lô. Cổng bên web cắt ở 2000 dòng và NÓI RA phần còn lại, nhưng gửi vừa tầm thì mỗi
# lô nhẹ và lỗi mạng chỉ làm hỏng một lô nhỏ.
LO_TOI_DA = 500


def doc_cau_hinh():
    if not os.path.exists(TEP_CAU_HINH):
        sys.exit('Chưa có cau-hinh.json — chép từ cau-hinh.mau.json rồi sửa IP và KHOÁ.')
    with open(TEP_CAU_HINH, 'r', encoding='utf-8') as f:
        c = json.load(f)
    for k in ('duong_dan_web', 'khoa', 'may'):
        if not c.get(k):
            sys.exit('cau-hinh.json thiếu "%s".' % k)
    return c


def doc_moc():
    if not os.path.exists(TEP_MOC):
        return {}
    try:
        with open(TEP_MOC, 'r', encoding='utf-8') as f:
            return json.load(f)
    except (ValueError, OSError):
        # Tệp mốc hỏng -> coi như chưa có mốc. Đọc thừa thì cổng bỏ lượt trùng; đoán bừa một mốc
        # mới là bỏ sót, và bỏ sót thì im lặng.
        return {}


def ghi_moc(m):
    with open(TEP_MOC, 'w', encoding='utf-8') as f:
        json.dump(m, f, ensure_ascii=False, indent=1)


def doc_mot_may(may, tu_luc):
    """Trả (danh sách lượt, lỗi). Lượt: dict theo đúng khuôn cổng web đợi."""
    try:
        from zk import ZK
    except ImportError:
        sys.exit('Chưa cài thư viện: py -m pip install pyzk requests')

    zk = ZK(may['ip'], port=int(may.get('port', 4370)),
            timeout=int(may.get('cho_giay', 15)), password=int(may.get('mat_khau', 0)),
            force_udp=bool(may.get('udp', False)), ommit_ping=True)
    conn = None
    try:
        conn = zk.connect()
        # 🔴 KHÔNG `disable_device()`. Nó khoá màn hình đầu đọc trong lúc đọc — người tới chấm
        #    công đúng lúc ấy sẽ bấm không ăn, và họ không biết vì sao. Đọc chậm hơn một chút
        #    còn hơn chặn mất lượt bấm của người thật.
        ra = []
        for x in (conn.get_attendance() or []):
            luc = x.timestamp
            if tu_luc and luc < tu_luc:
                continue
            ra.append({
                # `hikSerial` là tên khoá cổng web đang dùng để tra ra CƠ SỞ. Mỗi đầu đọc một
                # mã riêng: lấy mã của máy chính cho cả năm đầu đọc là dồn công của năm phòng
                # vào một chỗ — bảng vẫn đầy số, chỉ là số của sai nơi.
                'hikSerial': may['ma'],
                'employeeNo': str(x.user_id).strip(),
                'name': '',
                'time': luc.strftime('%Y-%m-%d %H:%M:%S'),
            })
        return ra, ''
    except Exception as e:                                  # noqa: BLE001
        return [], '%s' % e
    finally:
        if conn:
            try:
                conn.disconnect()
            except Exception:                               # noqa: BLE001
                pass


def gui(cfg, logs):
    import requests
    r = requests.post(
        cfg['duong_dan_web'],
        headers={'X-VHCC-KEY': cfg['khoa'], 'Content-Type': 'application/json'},
        data=json.dumps({'logs': logs}, ensure_ascii=False).encode('utf-8'),
        timeout=int(cfg.get('cho_giay', 60)),
        # 🔴 KHÔNG đi theo chuyển hướng. Cổng web nói rõ: gặp chuyển hướng thì lượt POST bị gọi
        #    lại bằng GET và MẤT TRỌN thân gói. Thà đỏ ngay ở đây còn hơn báo xong mà không ghi.
        allow_redirects=False,
    )
    if r.status_code != 200:
        return False, 'HTTP %s: %s' % (r.status_code, r.text[:300])
    try:
        d = r.json()
    except ValueError:
        return False, 'web trả về không phải JSON: %s' % r.text[:300]
    if d.get('status') != 'SUCCESS':
        return False, 'web báo lỗi: %s' % json.dumps(d, ensure_ascii=False)[:300]
    return True, d


def main():
    p = argparse.ArgumentParser(description='Đọc log ZKTeco rồi đẩy về website chấm công.')
    p.add_argument('--thu', action='store_true', help='chỉ đọc và đếm, KHÔNG gửi, KHÔNG dời mốc')
    p.add_argument('--tu', default='', help='đọc lại từ ngày này (yyyy-mm-dd), bỏ qua mốc đã lưu')
    a = p.parse_args()

    cfg = doc_cau_hinh()
    moc = doc_moc()
    moc_moi = dict(moc)
    tong_doc = tong_gui = 0
    hong = []

    for may in cfg['may']:
        ten = may.get('ten') or may['ma']
        if a.tu:
            tu_luc = dt.datetime.strptime(a.tu, '%Y-%m-%d')
        elif moc.get(may['ma']):
            # Lùi lại một chút quanh mốc: đầu đọc trả trang không hẳn theo thứ tự, và gửi thừa
            # thì cổng bỏ lượt trùng. Thà thừa còn hơn thiếu.
            tu_luc = dt.datetime.strptime(moc[may['ma']], '%Y-%m-%d %H:%M:%S') - dt.timedelta(hours=6)
        else:
            tu_luc = None

        logs, loi = doc_mot_may(may, tu_luc)
        if loi:
            hong.append('%s (%s): %s' % (ten, may['ip'], loi))
            print('  ✗ %-14s %s' % (ten, loi))
            continue
        tong_doc += len(logs)
        print('  · %-14s đọc %d lượt%s' % (ten, len(logs),
                                           '' if not tu_luc else ' (từ %s)' % tu_luc))
        if not logs or a.thu:
            continue

        logs.sort(key=lambda x: x['time'])
        xong = 0
        for i in range(0, len(logs), LO_TOI_DA):
            lo = logs[i:i + LO_TOI_DA]
            ok, kq = gui(cfg, lo)
            if not ok:
                # 🔴 DỪNG NGAY, GIỮ NGUYÊN MỐC. Gửi tiếp là bỏ sót đúng cái lô vừa hỏng.
                hong.append('%s: %s' % (ten, kq))
                print('  ✗ %-14s gửi hỏng: %s' % (ten, kq))
                break
            xong += len(lo)
            print('    → gửi %d lượt · web: ghi %s · trùng %s · giữ tay %s · chờ gán %s'
                  % (len(lo), kq.get('ghi', '?'), kq.get('trung', '?'),
                     kq.get('giuTay', 0), kq.get('choGan', 0)))
        tong_gui += xong
        if xong == len(logs):
            moc_moi[may['ma']] = logs[-1]['time']

    if not a.thu:
        ghi_moc(moc_moi)

    print('\nĐọc %d lượt · gửi %d lượt%s' % (tong_doc, tong_gui, ' (CHẠY THỬ)' if a.thu else ''))
    if hong:
        print('HỎNG %d máy/lô:' % len(hong))
        for h in hong:
            print('  - ' + h)
        # Mã thoát khác 0 để Task Scheduler của Windows thấy được là lượt này hỏng.
        sys.exit(1)


if __name__ == '__main__':
    main()
