@echo off
@REM clone source từ folder gốc
set SOURCE_FOLDER=D:\Laragon\www\flatsome\wp-content\plugins\administrator-z\vendor\quyle91\wp-database-helper-v2

:: Lưu tên file script để tránh bị xóa
set SCRIPT_NAME=%~nx0

:: Xóa toàn bộ nội dung trong thư mục hiện tại trừ file .bat này
for /d %%D in (*) do (
    if /I not "%%D"=="%SCRIPT_NAME%" rd /s /q "%%D"
)
for %%F in (*) do (
    if /I not "%%F"=="%SCRIPT_NAME%" del /f /q "%%F"
)

:: Copy toàn bộ file từ SOURCE_FOLDER vào thư mục hiện tại, trừ file .bat này
xcopy /E /I /Y "%SOURCE_FOLDER%" "%CD%" /EXCLUDE:%SCRIPT_NAME%

@REM echo Đã xoá và copy toàn bộ file từ %SOURCE_FOLDER% vào %CD%, trừ %SCRIPT_NAME%
