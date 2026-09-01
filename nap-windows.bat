@echo off
title Nap firmware ghe massage
echo ================================================
echo   NAP FIRMWARE GHE MASSAGE  (ESP32 / CYD)
echo ================================================
echo.

REM ---- Tim Python: pip doi moi cai esptool thanh lenh "esptool", KHONG phai "esptool.py" ----
set PY=
where py >nul 2>&1 && set PY=py
if not defined PY (
  where python >nul 2>&1 && set PY=python
)
if not defined PY (
  echo [LOI] Khong tim thay Python tren may nay.
  echo.
  echo   Tai tai: https://www.python.org/downloads/
  echo   Luc cai NHO TICH o "Add Python to PATH" - khong tich thi van bao loi nay.
  echo.
  pause
  exit /b 1
)
echo Python  : %PY%

REM ---- Bao dam co esptool. Goi bang "-m esptool" nen khong phu thuoc PATH ----
%PY% -m esptool version >nul 2>&1
if errorlevel 1 (
  echo esptool : chua co - dang cai...
  %PY% -m pip install --upgrade --quiet esptool
  %PY% -m esptool version >nul 2>&1
  if errorlevel 1 (
    echo.
    echo [LOI] Cai esptool that bai. Thu chay tay lenh nay roi mo lai file:
    echo       %PY% -m pip install esptool
    echo.
    pause
    exit /b 1
  )
)
echo esptool : san sang
echo.

echo Cac cong COM may dang thay:
mode | findstr /C:"COM"
echo.
echo (Khong thay cong nao? Rut cap USB ra cam lai, hoac thieu driver CH340/CP2102.)
echo.

set CONG=
set /p CONG=Nhap cong COM (vd COM5): 
if "%CONG%"=="" (
  echo [LOI] Chua nhap cong.
  pause
  exit /b 1
)

echo.
echo Dang nap vao %CONG% ... (giu nut BOOT neu may khong tu vao che do nap)
echo.
%PY% -m esptool --chip esp32 --port %CONG% --baud 921600 write_flash ^
  0x1000  ghe-bootloader.bin ^
  0x8000  ghe-partitions.bin ^
  0xe000  ghe-boot_app0.bin ^
  0x10000 ghe-latest.bin

if errorlevel 1 (
  echo.
  echo ================================================
  echo [LOI] Nap that bai. Xem lan luot may thu nay:
  echo   1. Dung cong COM chua - xem lai danh sach o tren.
  echo   2. Co chuong trinh khac dang giu cong khong
  echo      (Arduino IDE, Serial Monitor, PuTTY) - dong het roi thu lai.
  echo   3. Vai board phai GIU nut BOOT luc bat dau nap, tha ra khi thay "Connecting".
  echo   4. Cap USB chi co 2 day (sac) thi khong nap duoc - doi cap co truyen du lieu.
  echo   5. Ha toc do: sua 921600 trong file nay thanh 460800 roi chay lai.
  echo ================================================
) else (
  echo.
  echo ================================================
  echo XONG. Rut cap va cam lai de may khoi dong.
  echo ================================================
)
echo.
pause
