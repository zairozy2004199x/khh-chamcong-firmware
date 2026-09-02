#!/usr/bin/env bash
# GOP merged.bin cho GHE (ESP32) - chay TRONG thu muc build co *.ino.bin (macOS/Linux)
# boot_app0.bin KHONG bat buoc: thieu thi van gop duoc (bootloader tu chay app0).
set -e
APP=$(ls *.ino.bin 2>/dev/null | head -1)
[ -n "$APP" ] || { echo "[X] Khong thay *.ino.bin - chay trong thu muc build."; exit 1; }
NAME="${APP%.ino.bin}"; BOOT="$NAME.ino.bootloader.bin"; PART="$NAME.ino.partitions.bin"
[ -f "$BOOT" ] && [ -f "$PART" ] || { echo "[X] Thieu bootloader/partitions .bin"; exit 1; }

BAPP=""
[ -f "boot_app0.bin" ] && BAPP="boot_app0.bin"
[ -z "$BAPP" ] && BAPP=$(find "$HOME/Library/Arduino15" "$HOME/.arduino15" "$HOME/.arduino" -name boot_app0.bin 2>/dev/null | head -1)

ESPTOOL="python3 -m esptool"; command -v esptool.py >/dev/null 2>&1 && ESPTOOL="esptool.py"
echo "App=$APP  boot_app0=${BAPP:-(khong thay - gop khong kem)}"

if [ -n "$BAPP" ]; then
  $ESPTOOL --chip esp32 merge_bin -o ghe-merged.bin --flash_mode keep --flash_freq keep --flash_size 4MB \
    0x1000 "$BOOT" 0x8000 "$PART" 0xe000 "$BAPP" 0x10000 "$APP"
else
  $ESPTOOL --chip esp32 merge_bin -o ghe-merged.bin --flash_mode keep --flash_freq keep --flash_size 4MB \
    0x1000 "$BOOT" 0x8000 "$PART" 0x10000 "$APP"
fi
echo "[OK] Da tao ghe-merged.bin"
