@echo off
REM Windows launcher, kept for the old machine. On macOS use ./serve.sh
REM (the code now lives under /Users/agtci/Documents/Project_Documents/Projects,
REM  not D:\Claude_development).
REM %~dp0 is this file's own folder, so moving the project no longer breaks it.
cd /d "%~dp0"
php artisan serve --host=127.0.0.1 --port=8000
