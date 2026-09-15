#!/usr/bin/env bash
# "Version:" ở đầu tệp và define('KHBC_VERSION') phải bằng nhau; readme Stable tag cũng vậy.
set -e
cd "$(dirname "$0")/.."
H=$(grep -m1 -oP '^\s*\*\s*Version:\s*\K[0-9.]+' khbc-bao-cao-chi-phi.php)
D=$(grep -m1 -oP "define\(\s*'KHBC_VERSION',\s*'\K[0-9.]+" khbc-bao-cao-chi-phi.php)
R=$(grep -m1 -oP '^Stable tag:\s*\K[0-9.]+' readme.txt)
if [ "$H" != "$D" ] || [ "$H" != "$R" ]; then
  echo "LỆCH PHIÊN BẢN: header=$H define=$D readme=$R" >&2
  exit 1
fi
echo "$H"
