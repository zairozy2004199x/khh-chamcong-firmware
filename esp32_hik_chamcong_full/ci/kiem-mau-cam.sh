#!/usr/bin/env bash
# Quét MÃ NGUỒN firmware tìm những mẫu mà CI sẽ quét trong file .bin.
#
# Vì sao cần: bước "Kiem .bin" của CI chỉ chạy SAU khi biên dịch ~3 phút, nên viết một
# chuỗi trùng mẫu là mất cả vòng CI mới biết. Đã bị 1 lần: câu báo lỗi của portal có
# "-default-rtdb." và fbHostHopLe() có ".firebaseio.com" -> CI đỏ dù không lộ gì thật.
#
# Danh sách mẫu phải KHỚP với .github/workflows/firmware.yml (bước "Kiem .bin").
# Chạy: bash esp32_hik_chamcong_full/ci/kiem-mau-cam.sh
set -uo pipefail
cd "$(dirname "$0")/../.."

MAU=('AKfycb' 'default-rtdb' 'khhcm-chamcong' 'firebaseio' 'databaseio')
# ⚠️ 31/07/2026 — PHẢI liệt kê MỌI file được đồng bộ sang repo CÔNG KHAI, không chỉ 2 file .ino.
#    Đã hớ một lần: điền link RTDB thật vào secrets.example.h. File đó đi nguyên văn sang repo
#    công khai nên là lộ thật, mà bước "Kiem .bin" của CI không thấy (bin build từ ci/secrets.ci.h
#    toàn placeholder) và script này lúc đó chỉ quét .ino. Phiên khác bắt được, không phải CI.
FILE=(esp32_hik_chamcong_full/esp32_hik_chamcong_full.ino
      esp32_ota_updater/esp32_ota_updater.ino
      esp32_hik_chamcong_full/secrets.example.h
      esp32_ota_updater/secrets.example.h
      esp32_hik_chamcong_full/ci/secrets.ci.h
      esp32_ota_updater/ci/secrets.ci.h
      esp32_hik_chamcong_full/ci/User_Setup.h)

# Chỉ xét thứ LỌT VÀO .bin: chuỗi trong dấu ngoặc kép. Ghi chú thì vô hại.
# Cách bóc: xoá ghi chú // và /* */ trước, rồi mới trích chuỗi.
loi=0
for f in "${FILE[@]}"; do
  [ -f "$f" ] || continue
  chuoi="$(python3 - "$f" <<'PY'
import sys,re
s=open(sys.argv[1],encoding='utf-8').read()
out=[];i=0;n=len(s)
while i<n:
    c=s[i]
    if c=='/' and i+1<n and s[i+1]=='/':
        j=s.find('\n',i); i=(n if j<0 else j); continue
    if c=='/' and i+1<n and s[i+1]=='*':
        j=s.find('*/',i+2); i=(n if j<0 else j+2); continue
    if c=='"':
        i+=1; b=[]
        while i<n:
            if s[i]=='\\': b.append(s[i:i+2]); i+=2; continue
            if s[i]=='"': i+=1; break
            b.append(s[i]); i+=1
        out.append(''.join(b)); continue
    i+=1
print('\n'.join(out))
PY
)"
  for m in "${MAU[@]}"; do
    if printf '%s' "$chuoi" | grep -qF "$m"; then
      echo "🔴 $f: chuỗi có '$m'"
      printf '%s' "$chuoi" | grep -nF "$m" | head -3 | sed 's/^/     /'
      loi=1
    fi
  done
done

# secrets.h thật không được lọt vào repo (đã có .gitignore, kiểm lại cho chắc)
for f in esp32_hik_chamcong_full/secrets.h esp32_ota_updater/secrets.h; do
  if git ls-files --error-unmatch "$f" >/dev/null 2>&1; then
    echo "🔴 $f ĐANG được git theo dõi — đây là file bí mật, phải bỏ ra."; loi=1
  fi
done

# ci/secrets.ci.h phải toàn placeholder — điền giá trị thật vào đó là lộ lên .bin công khai
for f in esp32_hik_chamcong_full/ci/secrets.ci.h esp32_ota_updater/ci/secrets.ci.h; do
  [ -f "$f" ] || continue
  if grep -E '^#define +SEC_' "$f" | grep -qv '__CHUA_CAU_HINH__'; then
    echo "🔴 $f có giá trị KHÁC placeholder:"; grep -E '^#define +SEC_' "$f" | grep -v '__CHUA_CAU_HINH__' | sed 's/^/     /'
    loi=1
  fi
done

[ "$loi" = 0 ] && echo "✅ Mã nguồn firmware sạch mẫu CI quét."
exit "$loi"
