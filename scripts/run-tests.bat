@echo off
REM Run Laravel tests with PHP 8.3+ (project requires ^8.3)
where php83 >nul 2>&1 && set PHP=php83 && goto :run
where php8.3 >nul 2>&1 && set PHP=php8.3 && goto :run
where php >nul 2>&1 && set PHP=php && goto :run
echo PHP not found. Install PHP 8.3+ or use GitHub Actions.
exit /b 1
:run
%PHP% -v
%PHP% artisan test %*
