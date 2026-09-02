@echo off
REM ====================================================================
REM  GOP merged.bin cho GHE (ESP32) - chay TRONG thu muc build co *.ino.bin
REM  (Arduino: Sketch -> Export Compiled Binary -> vao thu muc build)
REM ====================================================================
setlocal enabledelayedexpansion
set "APP="
for %%f in (*.ino.bin) do set "APP=%%f"
if not defined APP ( echo [X] Khong thay *.ino.bin - hay chay script TRONG thu muc build. & pause & exit /b 1 )
set "NAME=%APP:.ino.bin=%"
set "BOOT=%NAME%.ino.bootloader.bin"
set "PART=%NAME%.ino.partitions.bin"
if not exist "%BOOT%" ( echo [X] Thieu %BOOT% & pause & exit /b 1 )
if not exist "%PART%" ( echo [X] Thieu %PART% & pause & exit /b 1 )
REM Tim boot_app0.bin + esptool trong core ESP32
set "BAPP="
for /f "delims=" %%d in ('dir /b /s "%LOCALAPPDATA%\Arduino15\packages\esp32\hardware\esp32\*\tools\partitions\boot_app0.bin" 2^>nul') do set "BAPP=%%d"
if not defined BAPP ( echo [X] Khong thay boot_app0.bin trong core ESP32. & pause & exit /b 1 )
set "ESPTOOL="
for /f "delims=" %%e in ('dir /b /s "%LOCALAPPDATA%\Arduino15\packages\esp32\tools\esptool_py\*\esptool.exe" 2^>nul') do set "ESPTOOL=%%e"
echo App        : %APP%
echo Bootloader : %BOOT%
echo Partitions : %PART%
echo boot_app0  : %BAPP%
echo.
if defined ESPTOOL (
  "%ESPTOOL%" --chip esp32 merge_bin -o ghe-merged.bin --flash_mode keep --flash_freq keep --flash_size 4MB 0x1000 "%BOOT%" 0x8000 "%PART%" 0xe000 "%BAPP%" 0x10000 "%APP%"
) else (
  python -m esptool --chip esp32 merge_bin -o ghe-merged.bin --flash_mode keep --flash_freq keep --flash_size 4MB 0x1000 "%BOOT%" 0x8000 "%PART%" 0xe000 "%BAPP%" 0x10000 "%APP%"
)
echo.
if exist ghe-merged.bin ( echo [OK] Da tao: ghe-merged.bin - up vao o "Merged .bin" tren web. ) else ( echo [X] That bai - xem loi ben tren. )
pause
