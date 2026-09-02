GOP merged.bin cho firmware GHE (nạp USB qua web / Web Serial)
=============================================================
Chip ESP32 MOI TOANH khong OTA duoc -> phai nap USB.
- Co Arduino: cam chip moi -> Upload thang la xong (KHONG can merged.bin).
- Muon nap qua TRINH DUYET (Web Serial): can 1 file GOP "ghe-merged.bin".

CACH DUNG:
1. Arduino IDE: Sketch -> Export Compiled Binary.
2. Sketch -> Show Sketch Folder -> vao thu muc build/esp32.esp32.esp32/
   (co cac file *.ino.bin, *.ino.bootloader.bin, *.ino.partitions.bin).
3. Chep gop-merged.bat (Windows) hoac gop-merged.sh (mac/linux) vao ĐO -> chay.
4. Ra file ghe-merged.bin -> up vao o "Merged .bin" tren web (hoac nap Web Serial).

Yeu cau: da cai core ESP32 trong Arduino (script tu tim esptool + boot_app0 trong core).
boot_app0.bin KHONG bat buoc (thieu van gop duoc). Neu bao thieu esptool: chay  pip install esptool
