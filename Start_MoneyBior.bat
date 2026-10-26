@echo off
title Money Bior - Start
set "SRC=%~dp0"
set "DEST=C:\xampp\htdocs\MoneyBior_Database_Project"

rem No XAMPP installed? Open the system offline (data saved on this computer).
if not exist "C:\xampp\htdocs" (
  start "" "%SRC%index.html"
  exit /b
)

rem XAMPP found: copy the latest files, start Apache and MySQL, open the project.
xcopy "%SRC%*" "%DEST%\" /E /I /Y >nul
tasklist /FI "IMAGENAME eq httpd.exe" | find /I "httpd.exe" >nul || start "" /min "C:\xampp\apache\bin\httpd.exe"
tasklist /FI "IMAGENAME eq mysqld.exe" | find /I "mysqld.exe" >nul || start "" /min "C:\xampp\mysql\bin\mysqld.exe" --defaults-file=C:\xampp\mysql\bin\my.ini --standalone
timeout /t 5 /nobreak >nul
start "" "http://localhost/MoneyBior_Database_Project/index.html"
