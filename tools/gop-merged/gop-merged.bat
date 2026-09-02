@echo off
REM ====================================================================
REM  GOP merged.bin cho GHE (ESP32) - chay TRONG thu muc build co *.ino.bin
REM  (Arduino: Sketch -> Export Compiled Binary -> Show Sketch Folder -> build\...)
REM  boot_app0.bin KHONG bat buoc: thieu thi van gop duoc (vung OTA-data de trong,
REM  bootloader tu chay app0). Muon chac: chep boot_app0.bin canh script nay.
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

REM ---- Tim boot_app0.bin (nhieu noi). Khong bat buoc. ----
set "BAPP="
if exist "%~dp0boot_app0.bin" set "BAPP=%~dp0boot_app0.bin"
if not defined BAPP if exist "boot_app0.bin" set "BAPP=%CD%\boot_app0.bin"
if not defined BAPP for /f "delims=" %%d in ('dir /b /s "%LOCALAPPDATA%\Arduino15\packages\esp32\*boot_app0.bin" 2^>nul') do set "BAPP=%%d"
if not defined BAPP for /f "delims=" %%d in ('dir /b /s "%USERPROFILE%\AppData\Local\Arduino15\packages\esp32\*boot_app0.bin" 2^>nul') do set "BAPP=%%d"
if not defined BAPP for /f "delims=" %%d in ('dir /b /s "%APPDATA%\arduino-ide\*boot_app0.bin" 2^>nul') do set "BAPP=%%d"

REM ---- Tim esptool (trong core, khong can pip). ----
set "ESPTOOL="
for /f "delims=" %%e in ('dir /b /s "%LOCALAPPDATA%\Arduino15\packages\esp32\tools\esptool_py\*\esptool.exe" 2^>nul') do set "ESPTOOL=%%e"

echo App        : %APP%
echo Bootloader : %BOOT%
echo Partitions : %PART%
if defined BAPP ( echo boot_app0  : %BAPP% ) else ( echo boot_app0  : (khong thay - gop KHONG kem, van chay^) )
echo.

REM ---- Chon lenh esptool ----
set "RUN="
if defined ESPTOOL ( set "RUN=\"%ESPTOOL%\"" ) else ( set "RUN=python -m esptool" )

if defined BAPP (
  %RUN% --chip esp32 merge_bin -o ghe-merged.bin --flash_mode keep --flash_freq keep --flash_size 4MB 0x1000 "%BOOT%" 0x8000 "%PART%" 0xe000 "%BAPP%" 0x10000 "%APP%"
) else (
  %RUN% --chip esp32 merge_bin -o ghe-merged.bin --flash_mode keep --flash_freq keep --flash_size 4MB 0x1000 "%BOOT%" 0x8000 "%PART%" 0x10000 "%APP%"
)

echo.
if exist ghe-merged.bin ( echo [OK] Da tao: ghe-merged.bin - up vao o "Merged .bin" tren web. ) else ( echo [X] That bai - neu bao thieu esptool: mo CMD chay:  pip install esptool  roi chay lai. )
pause
