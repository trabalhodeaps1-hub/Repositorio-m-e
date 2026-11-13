<?php
declare(strict_types=1);

// =============================================================================
// Diagnóstico do Banco de Dados - AutoRelatório Industrial
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
    echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Status do Banco de Dados - AutoRelatório</title>";
    echo "<style>
        body{font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f8fafc; padding: 30px; color: #1e293b;}
        .card{background: #fff; padding: 24px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); max-width: 760px; margin: 0 auto;}
        h2{margin-top:0; color:#0f172a;}
        ul{list-style: none; padding-left: 0;}
        .badge{display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 13px; font-weight: 600;}
        .badge-pg{background: #dbeafe; color: #1e40af;}
        .badge-sql{background: #fef3c7; color: #92400e;}
        .dica{background:#eff6ff; border-left:4px solid #3b82f6; padding:14px; margin-top:20px; border-radius:0 8px 8px 0; font-size: 14px; line-height: 1.5;}
        .btn{display: inline-block; background: #2563eb; color: #fff; padding: 10px 16px; border-radius: 6px; text-decoration: none; font-weight: 500; margin-top: 14px;}
    </style></head><body><div class='card'>";
    
    $badgeClass = ($dbDriver === 'pgsql') ? 'badge-pg' : 'badge-sql';
    $driverNome = ($dbDriver === 'pgsql') ? '🐘 PostgreSQL Local (Ativo)' : '📁 SQLite Local (Fallback Ativo)';
    echo "<h2>Status do Banco de Dados <span class='badge {$badgeClass}'>{$driverNome}</span></h2>";
    echo "<p>Diagnóstico da conexão e persistência de dados do sistema:</p>";
    echo "<ul>";
}

// 1. Informações do Driver Ativo
linha('Driver em Uso', ($dbDriver === 'pgsql' ? 'PostgreSQL' : 'SQLite Local'), 'ok');
linha('Status da Conexão', $dbStatusMessage, ($dbDriver === 'pgsql' ? 'ok' : 'alerta'));

// 2. Extensão PHP pdo_pgsql
$temExtPgsql = extension_loaded('pdo_pgsql');
linha('Extensão pdo_pgsql no PHP', $temExtPgsql ? 'Habilitada no XAMPP' : 'Não detectada', $temExtPgsql ? 'ok' : 'alerta');

// 3. Contagem de Registros nas Tabelas Principais
$tabelas = ['usuarios', 'areas', 'equipamentos', 'inspecoes', 'relatorios', 'problemas', 'notificacoes'];
foreach ($tabelas as $tab) {
    try {
        $count = (int) $pdo->query("SELECT COUNT(*) FROM {$tab}")->fetchColumn();
        linha("Tabela '{$tab}'", "{$count} registro(s) encontrado(s)", 'ok');
    } catch (PDOException $e) {
        linha("Tabela '{$tab}'", "Erro ao consultar: " . $e->getMessage(), 'erro');
    }
}

// 4. Pasta de Uploads
$uploadsDir = realpath(__DIR__ . '/../uploads');
if ($uploadsDir && is_dir($uploadsDir)) {
    linha('Pasta de Fotos (/uploads)', $uploadsDir, 'ok');
} else {
    linha('Pasta de Fotos (/uploads)', 'Pasta ausente (será criada no primeiro envio)', 'alerta');
}

if (!$isCli) {
    echo "</ul>";
    
    if ($dbDriver !== 'pgsql') {
        echo "<div class='dica'>";
        echo "<strong>💡 Como ativar o PostgreSQL 18 local:</strong><br>";
        echo "1. No Windows, abra o menu Iniciar e pesquise por <code>Serviços</code> (ou execute <code>services.msc</code>).<br>";
        echo "2. Procure o serviço <strong>postgresql-x64-18</strong>, clique com o botão direito e selecione <strong>Iniciar</strong>.<br>";
        echo "3. Você também pode rodar o arquivo <code>iniciar_postgresql.bat</code> na raiz do projeto como Administrador.<br>";
        echo "4. Recarregue esta página. O sistema se conectará automaticamente e criará o banco <code>autorelatorio</code>!";
        echo "</div>";
    } else {
        echo "<div class='dica' style='background: #f0fdf4; border-left-color: #22c55e;'>";
        echo "<strong>✅ Tudo certo com o PostgreSQL!</strong><br>";
        echo "Todas as alterações, cadastros de inspeções, laudos e fotos agora são gravados diretamente no banco de dados PostgreSQL no seu computador.";
        echo "</div>";
    }

    echo "<p><a href='../pagina_inicial.html' class='btn'>Voltar para o Sistema</a></p>";
    echo "</div></body></html>";
}
