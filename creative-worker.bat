@echo off
REM Creative kuyruk iscisi - cift tiklayarak calistir, pencereyi ACIK birak.
REM Gorsel uretimleri (GenerateCreativeJob) bu pencere acikken islenir.
title Creative Queue Worker
cd /d D:\laragon\www\proje
echo ================================================================
echo  Creative kuyruk iscisi calisiyor.
echo  Gorsel uretmek icin bu pencere ACIK kalmali.
echo  Durdurmak icin: Ctrl+C  ya da bu pencereyi kapat.
echo ================================================================
echo.
php artisan queue:work redis --tries=3
echo.
echo Kuyruk iscisi durdu. Cikmak icin bir tusa basin.
pause >nul
