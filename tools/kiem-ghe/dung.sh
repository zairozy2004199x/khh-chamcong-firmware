#!/usr/bin/env bash
# Dựng sân kiểm ở /tmp/ghetest từ NGUỒN THẬT của class-vhg-trang.php.
#
# 🔴 Tách bằng SỐ DÒNG của dấu <<<'JS' / JS; chứ không chép tay: chép tay thì bài kiểm xanh cho
#    một bản JS không còn tồn tại trong plugin — đúng loại phép kiểm tệ hơn không có.
set -euo pipefail
G="$(cd "$(dirname "$0")/../.." && pwd)"
F="$G/vhcp-ghe/includes/class-vhg-trang.php"
OUT=/tmp/ghetest
mkdir -p "$OUT"

# Khối JS CHÍNH của trang (khối <<<'JS' thứ hai) — mốc: dòng 'return <<<'JS'' cuối cùng.
B=$(grep -n "return <<<'JS'" "$F" | tail -1 | cut -d: -f1)
E=$(awk -v b="$B" 'NR>b && $0=="JS;"{print NR; exit}' "$F")
sed -n "$((B+1)),$((E-1))p" "$F" > "$OUT/k5t.js"

# Khối CSS, để bảng trông gần với thật khi chụp màn.
CB=$(grep -n "return <<<'CSS'" "$F" | tail -1 | cut -d: -f1)
CE=$(awk -v b="$CB" 'NR>b && $0=="CSS;"{print NR; exit}' "$F")
sed -n "$((CB+1)),$((CE-1))p" "$F" > "$OUT/spa.css"

# Cửa sổ __T: khối thật là một IIFE nên không biến nào lọt ra ngoài.
# ⚠️ Phải chèn TRƯỚC dòng đóng `})();`, không phải nối vào cuối file: nối vào cuối thì `TOK`,
#    `D`, `QL_SUA` ở đó là biến toàn cục KHÁC, gán vào không ảnh hưởng gì tới bên trong — bài
#    kiểm sẽ xanh trong khi không chạm được vào trang thật.
python3 - "$OUT/k5t.js" <<'PY'
import io, sys
p = sys.argv[1]
s = io.open(p, encoding='utf-8').read()
cua = '''window.__T = {
  setTOK: function(t){ TOK = t; },
  setTAB: function(t){ TAB = t; },
  setD:   function(d){ D = d; },
  getD:   function(){ return D; },
  setBCP: function(ds, ns){ BCP = ds; BCP_NS = ns || []; },
  getSua: function(){ return QL_SUA; },
  setBao: function(b){ QL_BAO = b; },
  setPG:  function(n){ QL_PG = n; },
  qlGheRender: qlGheRender,
  veQuanLy: veQuanLy,
  veBcPin: veBcPin,
  noiBcPin: noiBcPin,
  misaNap: misaNap,
  csKhoangCach_: csKhoangCach_,
  ktdRow: ktdRow,
  noi: noi,
  ve: ve
};
})();'''
i = s.rstrip().rfind('})();')
assert i > 0, 'khong thay dong dong IIFE'
s = s[:i] + cua + '\n'
io.open(p, 'w', encoding='utf-8').write(s)
PY
cp "$G/tools/kiem-ghe/trang.html" "$OUT/trang.html"
# Vài phép kiểm mở trang NGAY TRONG tools/kiem-ghe (file://__dirname/trang.html). Đặt bản dựng
# cạnh nó luôn, và .gitignore ở đó lo phần không cho hai file sinh ra lọt vào repo.
ln -sf "$OUT/k5t.js"  "$G/tools/kiem-ghe/k5t.js"
ln -sf "$OUT/spa.css" "$G/tools/kiem-ghe/spa.css"
echo "san kiem: $OUT (k5t.js $(wc -l < "$OUT/k5t.js") dong)"
