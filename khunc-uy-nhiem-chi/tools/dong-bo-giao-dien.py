#!/usr/bin/env python3
"""
Đồng bộ giao diện từ web_uynhiemchi/ (bản tĩnh, GitHub Pages) vào plugin WordPress.

MỘT NGUỒN DUY NHẤT: sửa JS/CSS/HTML ở web_uynhiemchi/, rồi chạy script này.
    python3 khunc-uy-nhiem-chi/tools/dong-bo-giao-dien.py           # chép
    python3 khunc-uy-nhiem-chi/tools/dong-bo-giao-dien.py --check   # chỉ kiểm, lệch là thoát mã 1 (dùng ở CI)

Template: index.html → templates/app.html, thay <title>+<link css> bằng <!--KHUNC_HEAD--> và
cụm <script src> cuối trang bằng <!--KHUNC_SCRIPTS--> để PHP chèn ?ver= và CFG.
"""
import os, re, sys, shutil, filecmp

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
SRC = os.path.join(ROOT, 'web_uynhiemchi')
DST = os.path.join(ROOT, 'khunc-uy-nhiem-chi')
JS = ['engine.js', 'importer.js', 'exporter.js', 'mau.js', 'api.js', 'wp-ui.js', 'app.js', 'sample-data.js']


def template(html):
    html = re.sub(r'<title>.*?</title>\s*', '', html, count=1, flags=re.S)
    html = re.sub(r'<link rel="stylesheet" href="style\.css">', '<!--KHUNC_HEAD-->', html, count=1)
    html, n = re.subn(r'(?:<script src="[^"]+"></script>\s*)+(?=</body>)', '<!--KHUNC_SCRIPTS-->\n', html, count=1, flags=re.S)
    assert n == 1, 'không tìm thấy cụm <script src> trước </body>'
    assert '<!--KHUNC_HEAD-->' in html, 'không tìm thấy <link rel="stylesheet" href="style.css">'
    return html


def pairs():
    out = []
    for f in JS:
        out.append((os.path.join(SRC, f), os.path.join(DST, 'assets', 'js', f), None))
    out.append((os.path.join(SRC, 'vendor', 'xlsx.full.min.js'), os.path.join(DST, 'assets', 'js', 'vendor', 'xlsx.full.min.js'), None))
    out.append((os.path.join(SRC, 'vendor', 'LICENSE-xlsx'), os.path.join(DST, 'assets', 'js', 'vendor', 'LICENSE-xlsx'), None))
    out.append((os.path.join(SRC, 'style.css'), os.path.join(DST, 'assets', 'css', 'app.css'), None))
    out.append((os.path.join(SRC, 'index.html'), os.path.join(DST, 'templates', 'app.html'), template))
    return out


def main():
    check = '--check' in sys.argv
    lech = []
    for src, dst, fn in pairs():
        if fn:
            with open(src, encoding='utf-8') as f:
                want = fn(f.read())
            have = open(dst, encoding='utf-8').read() if os.path.exists(dst) else None
            if have != want:
                lech.append(dst)
                if not check:
                    os.makedirs(os.path.dirname(dst), exist_ok=True)
                    with open(dst, 'w', encoding='utf-8') as f:
                        f.write(want)
        else:
            if not os.path.exists(dst) or not filecmp.cmp(src, dst, shallow=False):
                lech.append(dst)
                if not check:
                    os.makedirs(os.path.dirname(dst), exist_ok=True)
                    shutil.copyfile(src, dst)
    rel = [os.path.relpath(p, ROOT) for p in lech]
    if check:
        if rel:
            print('LỆCH — chạy lại tools/dong-bo-giao-dien.py rồi commit:\n  ' + '\n  '.join(rel))
            sys.exit(1)
        print('Plugin đã đồng bộ với web_uynhiemchi/.')
    else:
        print(('Đã chép %d tệp:\n  ' % len(rel)) + '\n  '.join(rel) if rel else 'Không có gì thay đổi.')


if __name__ == '__main__':
    main()
