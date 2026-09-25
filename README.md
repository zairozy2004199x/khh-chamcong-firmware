Nhanh nay CHI chua ban nap cua GHE MASSAGE, de tai thang qua link raw.
Bi force-push moi lan build - dung dat gi khac vao day.

== BOARD DA CO FIRMWARE CUNG LOAI: chi can ghi app ==
  esptool.py --chip esp32 --port <CONG> --baud 921600 write_flash 0x10000 ghe-latest.bin

== BOARD CON TRONG: phai ghi DU BON manh, dung dia chi ==
  esptool.py --chip esp32 --port <CONG> --baud 921600 write_flash \
    0x1000  ghe-bootloader.bin \
    0x8000  ghe-partitions.bin \
    0xe000  ghe-boot_app0.bin \
    0x10000 ghe-latest.bin

Thieu mot manh la man den va KHONG co thong bao gi.
Windows: cai Python roi "pip install esptool"; <CONG> dang COM5.
Linux/macOS: <CONG> dang /dev/ttyUSB0 hoac /dev/cu.usbserial-xxx.
