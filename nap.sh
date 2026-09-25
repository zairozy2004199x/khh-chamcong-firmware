#!/usr/bin/env bash
# Nap firmware ghe massage. Dung: ./nap.sh [/dev/ttyUSB0]
set -e
CONG="${1:-/dev/ttyUSB0}"
PY=$(command -v python3 || command -v python) || {
  echo "[LOI] Khong tim thay Python."; exit 1; }
# Goi bang "-m esptool": pip doi moi cai thanh lenh "esptool", khong phai "esptool.py".
if ! "$PY" -m esptool version >/dev/null 2>&1; then
  echo "esptool chua co - dang cai..."
  "$PY" -m pip install --upgrade --quiet esptool
fi
echo "Nap vao $CONG ..."
"$PY" -m esptool --chip esp32 --port "$CONG" --baud 921600 write_flash \
  0x1000  ghe-bootloader.bin \
  0x8000  ghe-partitions.bin \
  0xe000  ghe-boot_app0.bin \
  0x10000 ghe-latest.bin
echo "XONG. Rut cap va cam lai."
