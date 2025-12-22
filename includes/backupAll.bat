@echo off
REM ============================================================
REM Script avanzado de respaldo y restauración PostgreSQL en Windows 10
REM Migración de PostgreSQL 8.14 (carpeta bin en v15) a 9.11
REM Autor: Carlos (adaptado por Copilot)
REM ============================================================

REM === CONFIGURACIÓN INICIAL ===
SET OLD_PG_PATH=C:\Program Files\PostgreSQL\15\bin
SET NEW_PG_PATH=C:\Program Files\PostgreSQL\9.11\bin
SET BACKUP_PATH=C:\respaldos
SET PGUSER=postgres
SET PGPASSWORD=Orellana

REM Crear carpeta de respaldo si no existe
if not exist "%BACKUP_PATH%" mkdir "%BACKUP_PATH%"

:MENU
cls
echo ================================================
echo   MENU DE OPCIONES - MIGRACION POSTGRESQL
echo ================================================
echo [1] Respaldar todas las bases de datos (archivos separados)
echo [2] Respaldar todas las bases de datos en un solo archivo
echo [3] Respaldar una base de datos especifica
echo [4] Vista previa de bases de datos y restaurar una seleccionada
echo [5] Restaurar todas las bases de datos
echo [6] Salir
echo ================================================
set /p opcion=Elige una opcion (1-6): 

if "%opcion%"=="1" goto RESPALDO_SEPARADO
if "%opcion%"=="2" goto RESPALDO_UNICO
if "%opcion%"=="3" goto RESPALDO_UNA
if "%opcion%"=="4" goto RESTAURAR_UNA
if "%opcion%"=="5" goto RESTAURAR_TODAS
if "%opcion%"=="6" goto SALIR
goto MENU

:RESPALDO_SEPARADO
echo Respaldando cada base de datos en archivos separados...
for /f %%i in ('"%OLD_PG_PATH%\psql.exe" -U %PGUSER% -t -c "SELECT datname FROM pg_database WHERE datistemplate = false;"') do (
    echo Respaldando base de datos: %%i
    "%OLD_PG_PATH%\pg_dump.exe" -U %PGUSER% -d %%i > "%BACKUP_PATH%\%%i.sql"
)
echo Respaldos creados en "%BACKUP_PATH%"
pause
goto MENU

:RESPALDO_UNICO
echo Respaldando todas las bases de datos en un solo archivo...
"%OLD_PG_PATH%\pg_dumpall.exe" -U %PGUSER% > "%BACKUP_PATH%\respaldo_completo.sql"
echo Respaldo unico creado en "%BACKUP_PATH%\respaldo_completo.sql"
pause
goto MENU

:RESPALDO_UNA
echo ================================================
echo Lista de bases de datos disponibles:
"%OLD_PG_PATH%\psql.exe" -U %PGUSER% -t -c "SELECT datname FROM pg_database WHERE datistemplate = false;"
echo ================================================
set /p dbname=Escribe el nombre de la base de datos que deseas respaldar: 
echo Respaldando base de datos: %dbname%
"%OLD_PG_PATH%\pg_dump.exe" -U %PGUSER% -d %dbname% > "%BACKUP_PATH%\%dbname%.sql"
echo Respaldo creado en "%BACKUP_PATH%\%dbname%.sql"
pause
goto MENU

:RESTAURAR_UNA
echo ================================================
echo Vista previa de las bases de datos disponibles:
"%OLD_PG_PATH%\psql.exe" -U %PGUSER% -t -c "SELECT datname FROM pg_database WHERE datistemplate = false;"
echo ================================================
set /p dbname=Escribe el nombre de la base de datos a restaurar: 
echo Restaurando base de datos: %dbname%
"%NEW_PG_PATH%\createdb.exe" -U %PGUSER% %dbname%
"%NEW_PG_PATH%\psql.exe" -U %PGUSER% -d %dbname% < "%BACKUP_PATH%\%dbname%.sql"
echo Restauracion completada para %dbname%
pause
goto MENU

:RESTAURAR_TODAS
echo Restaurando todas las bases de datos...
for %%i in ("%BACKUP_PATH%\*.sql") do (
    echo Restaurando base de datos: %%~ni
    "%NEW_PG_PATH%\createdb.exe" -U %PGUSER% %%~ni
    "%NEW_PG_PATH%\psql.exe" -U %PGUSER% -d %%~ni < "%%i"
)
echo Restauracion completada para todas las bases de datos.
pause
goto MENU

:SALIR
echo Saliendo del script...
exit