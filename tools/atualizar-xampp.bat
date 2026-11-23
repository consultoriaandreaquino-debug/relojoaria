@echo off
chcp 65001 >nul
title AQUINO - atualizar o site no XAMPP
rem Baixa a versao mais nova do GitHub para C:\xampp\htdocs\relojoaria,
rem mantem o seu .env e reimporta o banco (database\schema.sql).
rem Antes de rodar: abra o XAMPP Control Panel e ligue Apache e MySQL.

set "XAMPP=C:\xampp"
set "DEST=%XAMPP%\htdocs\relojoaria"
set "TMPD=%TEMP%\aquino-atualizacao"
set "ZIP=https://github.com/consultoriaandreaquino-debug/relojoaria/archive/refs/heads/main.zip"

if not exist "%XAMPP%\mysql\bin\mysql.exe" (
    echo Nao encontrei o XAMPP em %XAMPP%. Instale o XAMPP ou ajuste a linha "set XAMPP" deste arquivo.
    goto fim
)

"%XAMPP%\mysql\bin\mysqladmin.exe" -u root ping >nul 2>&1
if errorlevel 1 (
    echo O MySQL esta desligado. Abra o XAMPP Control Panel, clique em Start no MySQL e no Apache e rode este arquivo de novo.
    goto fim
)

echo [1/4] Baixando a versao nova do GitHub...
if exist "%TMPD%" rmdir /s /q "%TMPD%"
mkdir "%TMPD%"
curl -L -s -o "%TMPD%\site.zip" "%ZIP%"
if errorlevel 1 goto erro

echo [2/4] Descompactando...
tar -xf "%TMPD%\site.zip" -C "%TMPD%"
if errorlevel 1 goto erro

echo [3/4] Copiando para %DEST% (o seu .env e as fotos enviadas pelo painel sao mantidos)...
robocopy "%TMPD%\relojoaria-main" "%DEST%" /E /NFL /NDL /NJH /NJS /NP /XD "%TMPD%\relojoaria-main\assets\uploads" >nul
if errorlevel 8 goto erro
if not exist "%DEST%\.env" copy "%DEST%\.env.example" "%DEST%\.env" >nul

echo [4/4] Importando o banco de dados...
"%XAMPP%\mysql\bin\mysql.exe" -u root --default-character-set=utf8mb4 < "%DEST%\database\schema.sql"
if errorlevel 1 (
    echo Nao consegui importar o banco. Se o seu MySQL tem senha no usuario root,
    echo importe pelo phpMyAdmin: http://localhost/phpmyadmin - Importar - database\schema.sql
    goto fim
)

rmdir /s /q "%TMPD%"
echo.
echo Pronto! O site foi atualizado. Abrindo http://localhost/relojoaria ...
echo Admin:   admin@aquino.com.br   / Aquino@Admin26
echo Cliente: cliente@aquino.com.br / Aquino@Cliente26
start "" "http://localhost/relojoaria"
goto fim

:erro
echo.
echo Algo deu errado no download ou na copia. Verifique a internet e tente de novo.

:fim
echo.
pause
