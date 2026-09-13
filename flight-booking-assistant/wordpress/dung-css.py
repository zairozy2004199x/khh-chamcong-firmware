# Dựng assets/dovere.css của plugin: lấy style của index.html, bọc mọi lớp vào .dvr,
# rồi ghép phần riêng của bản WordPress ở _extras.css.
import re, sys
goc = '/home/user/khh-chamcong-firmware/flight-booking-assistant/'
css = re.search(r'<style>(.*?)</style>', open(goc+'index.html',encoding='utf-8').read(), re.S).group(1)

def prefix(sel):
    m = re.match(r'^((?:\s*/\*.*?\*/)*\s*)(.*)$', sel, re.S)
    lead, rest = m.group(1), m.group(2).strip()
    ra = []
    for p in rest.split(','):
        p = p.strip()
        if not p: continue
        if p.startswith(':root') or p.startswith('@'): ra.append(p)
        elif p == 'body': ra.append('.dvr')
        elif p == '*': ra.append('.dvr *')
        elif p.startswith('body'): ra.append('.dvr' + p[4:])
        else: ra.append('.dvr ' + p)
    return lead + ', '.join(ra)

out, buf = '', ''
for ch in css:
    if ch == '{':
        out += (buf if buf.strip().lstrip().startswith('@') else prefix(buf)) + '{'; buf = ''
    elif ch == '}':
        out += buf + '}'; buf = ''
    else:
        buf += ch
out += buf

extras = open(goc+'wordpress/do-ve-re/assets/_extras.css',encoding='utf-8').read()
open(goc+'wordpress/do-ve-re/assets/dovere.css','w',encoding='utf-8').write(
    "/* Dò Vé Rẻ — mọi lớp nằm trong .dvr để không đụng giao diện theme của anh.\n"
    "   File này dựng từ <style> của index.html, đừng sửa tay: sửa index.html rồi dựng lại. */\n"
    + out + "\n" + extras)
print('dovere.css', round((len(out)+len(extras))/1024,1), 'KB')
