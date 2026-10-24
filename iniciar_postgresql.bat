@echo off
chcp 65001 > nul
echo ====================================================================
echo   AutoRelatório Industrial - Iniciar PostgreSQL 18
echo ====================================================================
echo.
echo Tentando iniciar o servico postgresql-x64-18 no Windows...
net start postgresql-x64-18

if %ERRORLEVEL% EQU 0 (
    echo.
    echo ====================================================================
    echo [SUCESSO] Servico PostgreSQL 18 iniciado com sucesso!
    echo Acesse: http://localhost/trabalho/Repositorio-m-e/back/teste_conexao.php
    echo ====================================================================
) else (
    echo.
    echo ====================================================================
    echo [AVISO] Se o Windows exibiu "Acesso negado" (Erro 5):
    echo Clique com o botao direito neste arquivo (iniciar_postgresql.bat)
    echo e selecione "Executar como Administrador".
    echo ====================================================================
)

echo.
pause
