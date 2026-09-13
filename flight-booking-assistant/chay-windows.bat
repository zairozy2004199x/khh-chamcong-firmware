@echo off
chcp 65001 >nul
title Do Ve Re
cd /d "%~dp0"

where node >nul 2>nul
if errorlevel 1 (
  echo Chua cai Node.js. Tai ban LTS o https://nodejs.org roi chay lai file nay.
  pause
  exit /b 1
)

if not exist ".env" (
  copy ".env.example" ".env" >nul
  echo Da tao file .env tu mau. Mo .env bang Notepad de khai so tai khoan va khoa,
  echo roi chay lai file nay.
  notepad .env
  exit /b 0
)

echo Dang mo hai may chu...
start "Do Ve Re - gia" cmd /k node server\proxy.mjs
start "Do Ve Re - don hang" cmd /k node booking\server.mjs
timeout /t 2 >nul
start "" "index.html"
start "" "http://localhost:8788/quan-tri.html"
echo.
echo   Bang gia      : mo bang trinh duyet (index.html)
echo   Khach dat ve  : http://localhost:8788/dat-ve.html
echo   Minh xu ly don: http://localhost:8788/quan-tri.html
echo.
echo Dong hai cua so den de tat may chu.
pause
