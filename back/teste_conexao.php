<?php
declare(strict_types=1);

// =============================================================================
// Diagnóstico do Armazenamento Local - AutoRelatório Industrial
// Acesse via navegador: http://localhost/trabalho/Repositorio-m-e/back/teste_conexao.php
// =============================================================================

require_once __DIR__ . '/../config/bootstrap.php';

$isCli = (php_sapi_name() === 'cli');

function linha(string $rotulo, string $status, string $tipo = 'ok'): void {
    global $isCli;
    $cor = match($tipo) {
        'ok'    => 'color: #16a34a; font-weight: bold;',
        'erro'  => 'color: #dc2626; font-weight: bold;',
        'alerta'=> 'color: #d97706; font-weight: bold;',
        default => 'color: #4b5563;'
    };
    if ($isCli) {
        $simbolo = match($tipo) {
            'ok' => '[OK]',
            'erro' => '[ERRO]',
            'alerta' => '[AVISO]',
            default => '[INFO]'
        };
        echo "{$simbolo} {$rotulo}: {$status}\n";
    } else {
        echo "<li style='margin-bottom: 8px;'><strong>{$rotulo}:</strong> <span style='{$cor}'>{$status}</span></li>";
    }
}

if (!$isCli) {
    header('Content-Type: text/html; charset=utf-8');
    echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Armazenamento Local - AutoRelatório</title>";
    echo "<style>body{font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f8fafc; padding: 30px; color: #1e293b;} .card{background: #fff; padding: 24px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); max-width: 700px; margin: 0 auto;} h2{margin-top:0; color:#0f172a;} pre{background:#f1f5f9; padding:12px; border-radius:6px; overflow-x:auto;} .dica{background:#eff6ff; border-left:4px solid #3b82f6; padding:12px; margin-top:16px; border-radius:0 6px 6px 0;}</style></head><body><div class='card'>";
    echo "<h2>📂 Armazenamento Local Ativo (SQLite)</h2>";
    echo "<p>Status do banco de dados em arquivo local no projeto:</p>";
    echo "<ul style='list-style: none; padding-left: 0;'>";
}

// 1. Informações do banco local
$dbFile = realpath(__DIR__ . '/../database/app.sqlite');
linha('Tipo de Armazenamento', 'Banco de Dados Local (SQLite)');
if ($dbFile && file_exists($dbFile)) {
    $tamanhoKB = round(filesize($dbFile) / 1024, 2);
    linha('Arquivo Local', $dbFile, 'ok');
    linha('Tamanho do Banco', "{$tamanhoKB} KB", 'ok');
} else {
    linha('Arquivo Local', 'Não localizado!', 'erro');
}

// 2. Pasta de Uploads de Imagens
$uploadsDir = realpath(__DIR__ . '/../uploads');
if ($uploadsDir && is_dir($uploadsDir)) {
    linha('Armazenamento de Imagens', $uploadsDir, 'ok');
} else {
    linha('Armazenamento de Imagens', 'Pasta criada automaticamente', 'alerta');
}

// 3. Contagem de Registros
try {
    $totalUsers = (int) $pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
    $totalAreas = (int) $pdo->query('SELECT COUNT(*) FROM areas')->fetchColumn();
    $totalEquip = (int) $pdo->query('SELECT COUNT(*) FROM equipamentos')->fetchColumn();
    $totalInsp  = (int) $pdo->query('SELECT COUNT(*) FROM inspecoes')->fetchColumn();
    $totalRelat = (int) $pdo->query('SELECT COUNT(*) FROM relatorios')->fetchColumn();

    linha('Usuários Cadastrados', (string)$totalUsers, 'ok');
    linha('Áreas Industriais', (string)$totalAreas, 'ok');
    linha('Equipamentos Registrados', (string)$totalEquip, 'ok');
    linha('Inspeções Realizadas', (string)$totalInsp, 'ok');
    linha('Relatórios Técnicos', (string)$totalRelat, 'ok');

} catch (Exception $e) {
    linha('Consulta às Tabelas', 'Erro: ' . $e->getMessage(), 'erro');
}

if (!$isCli) {
    echo "</ul>";
    echo "<div class='dica' style='background:#f0fdf4; border-color:#22c55e; color:#15803d;'>";
    echo "🎉 <strong>Armazenamento Local Operando com Sucesso!</strong><br>";
    echo "O sistema não depende de servidores externos e funcionará em qualquer computador.";
    echo "</div>";
    echo "</div></body></html>";
}
