#!/usr/bin/env bash
# Dò Vé Rẻ — mở cả hai máy chủ rồi bật trình duyệt.
set -u
cd "$(dirname "$0")"

command -v node >/dev/null 2>&1 || { echo "Chưa cài Node.js. Cài bản LTS ở https://nodejs.org rồi chạy lại."; exit 1; }

if [ ! -f .env ]; then
  cp .env.example .env
  echo "Đã tạo .env từ mẫu. Mở .env khai số tài khoản và khoá, rồi chạy lại file này."
  exit 0
fi

pids=()
cleanup(){ echo; echo "Đang tắt máy chủ…"; for p in "${pids[@]:-}"; do kill "$p" 2>/dev/null || true; done; }
trap cleanup EXIT INT TERM

node server/proxy.mjs &   pids+=($!)
node booking/server.mjs & pids+=($!)
sleep 2

mo(){ command -v open >/dev/null && open "$1" || { command -v xdg-open >/dev/null && xdg-open "$1"; }; }
mo "index.html"
mo "http://localhost:8788/quan-tri.html"

cat <<TXT

  Bảng giá       : index.html (đã mở bằng trình duyệt)
  Khách đặt vé   : http://localhost:8788/dat-ve.html
  Mình xử lý đơn : http://localhost:8788/quan-tri.html

  Ctrl+C để tắt cả hai máy chủ.
TXT
wait
