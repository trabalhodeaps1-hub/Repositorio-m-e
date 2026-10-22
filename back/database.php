<?php
declare(strict_types=1);

/**
 * ====================================================================
 * CONFIGURAÇÃO E CONEXÃO COM O BANCO DE DADOS
 * ====================================================================
 * Este arquivo cria e disponibiliza a variável global $pdo para todo o sistema.
 */

// 1. Configurações de acesso ao Banco de Dados (PostgreSQL / Aiven Cloud / SQLite)
$dbConfig = [
    'driver'  => 'pgsql',          // 'pgsql' para PostgreSQL / Aiven, 'sqlite' para SQLite
    'host'    => '127.0.0.1',      // Endereço do host (ex: pg-xxxx.aivencloud.com para Aiven)
    'port'    => '5050',           // Porta configurada (ex: 25060 no Aiven, 5432 padrão)
    'dbname'  => 'autorelatorio',  // Nome do banco de dados (ex: defaultdb no Aiven)
    'user'    => 'postgres',       // Usuário do banco (ex: avnadmin no Aiven)
    'pass'    => 'jesus',          // Senha do banco
    'sslmode' => 'prefer',         // Use 'require' para Aiven / Nuvem
    'url'     => '',               // Ou cole aqui a Service URI completa do Aiven: postgres://...
];

// Suporte opcional à Service URI do Aiven (DATABASE_URL)
$databaseUrl = getenv('DATABASE_URL') ?: ($dbConfig['url'] ?? '');
if (!empty($databaseUrl)) {
    $parsed = parse_url($databaseUrl);
    if (!empty($parsed['host'])) {
        $dbConfig['host'] = $parsed['host'];
        $dbConfig['port'] = (string)($parsed['port'] ?? 5432);
        $dbConfig['user'] = urldecode($parsed['user'] ?? '');
        $dbConfig['pass'] = urldecode($parsed['pass'] ?? '');
        $dbConfig['dbname'] = ltrim($parsed['path'] ?? '', '/');
        $dbConfig['sslmode'] = 'require';
        if (!empty($parsed['query'])) {
            parse_str($parsed['query'], $queryParts);
            if (!empty($queryParts['sslmode'])) {
                $dbConfig['sslmode'] = $queryParts['sslmode'];
            }
        }
    }
}

// Opções recomendadas do PDO
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

$pdo = null;

// 2. Tentativa de conexão com PostgreSQL / Aiven Cloud
if (extension_loaded('pdo_pgsql')) {
    try {
        $sslParam = !empty($dbConfig['sslmode']) ? ";sslmode={$dbConfig['sslmode']}" : "";
        $dsn = "pgsql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['dbname']}{$sslParam}";
        $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], $options);
    } catch (PDOException $e) {
        // Se a porta 5050 falhar no localhost, tenta a porta padrão 5432
        if (in_array($dbConfig['host'], ['127.0.0.1', 'localhost'], true)) {
            try {
                $dsnPadrao = "pgsql:host={$dbConfig['host']};port=5432;dbname={$dbConfig['dbname']}";
                $pdo = new PDO($dsnPadrao, $dbConfig['user'], $dbConfig['pass'], $options);
            } catch (PDOException $ignored) {
                // PostgreSQL indisponível no momento
            }
        }
    }
}

// 3. Fallback Automático: Se o PostgreSQL não estiver ativo, usa SQLite local
if (!$pdo) {
    $databaseDir = __DIR__ . '/../database';
    if (!is_dir($databaseDir)) {
        mkdir($databaseDir, 0777, true);
    }

    $databasePath = $databaseDir . '/app.sqlite';
    $pdo = new PDO('sqlite:' . $databasePath, null, null, $options);
}

// 4. Criação automática e migração de tabelas conforme o banco (PostgreSQL ou SQLite)
$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

if ($driver === 'pgsql') {
    $sqlFile = __DIR__ . '/tabelas.sql';
    if (file_exists($sqlFile)) {
        try {
            $pdo->exec(file_get_contents($sqlFile));
        } catch (PDOException $ignored) {}
    }
} else {
    // SQLite DDL (usando aspas simples padrão SQL para valores literais default)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS usuarios (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nome TEXT NOT NULL,
            email TEXT UNIQUE NOT NULL,
            idade INTEGER,
            data_nascimento TEXT,
            ano_nascimento INTEGER,
            cargo TEXT,
            senha_hash TEXT NOT NULL,
            matricula TEXT UNIQUE,
            telefone TEXT,
            papel TEXT DEFAULT 'funcionario',
            informacao_problema TEXT,
            foto_perfil TEXT,
            criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS problemas (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            usuario_id INTEGER,
            nome TEXT,
            email TEXT,
            idade INTEGER,
            descricao_problema TEXT NOT NULL,
            status TEXT DEFAULT 'aberto',
            criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS equipamentos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            numero_patrimonio TEXT UNIQUE NOT NULL,
            nome TEXT NOT NULL,
            area_id INTEGER,
            localizacao TEXT,
            status_operacional INTEGER DEFAULT 1,
            criado_por INTEGER,
            criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS areas (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nome TEXT UNIQUE NOT NULL,
            descricao TEXT,
            icone TEXT DEFAULT 'factory',
            criado_por INTEGER,
            criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS relatorios (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            inspecao_id INTEGER,
            titulo_item TEXT,
            local_exato TEXT,
            descricao_tecnica TEXT,
            criticidade TEXT DEFAULT 'media',
            prioridade TEXT DEFAULT 'media',
            recomendacoes TEXT,
            fotografia TEXT,
            status TEXT DEFAULT 'aguardando_aprovacao',
            criado_por INTEGER,
            criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS inspecoes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            realizada_em DATETIME,
            area_id INTEGER,
            equipamento_id INTEGER,
            numero_perimetro TEXT,
            localizacao TEXT,
            objetivo TEXT,
            observacoes TEXT,
            realizada_por INTEGER,
            status TEXT DEFAULT 'concluida',
            criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS notificacoes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            usuario_id INTEGER NOT NULL,
            mensagem TEXT NOT NULL,
            lida INTEGER DEFAULT 0,
            link TEXT,
            criada_em DATETIME DEFAULT CURRENT_TIMESTAMP
        );
    ");

    // Garante colunas adicionais para compatibilidade entre versões de banco
    $colunasUsuarios = [
        'matricula' => 'TEXT',
        'telefone' => 'TEXT',
        'papel' => "TEXT DEFAULT 'funcionario'",
        'idade' => 'INTEGER',
        'data_nascimento' => 'TEXT',
        'ano_nascimento' => 'INTEGER',
        'cargo' => 'TEXT',
        'informacao_problema' => 'TEXT',
        'foto_perfil' => 'TEXT'
    ];
    foreach ($colunasUsuarios as $coluna => $tipo) {
        try {
            $pdo->exec("ALTER TABLE usuarios ADD COLUMN {$coluna} {$tipo}");
        } catch (PDOException $ignored) {}
    }

    $colunasRelatorios = [
        'inspecao_id' => 'INTEGER',
        'titulo_item' => 'TEXT',
        'local_exato' => 'TEXT',
        'descricao_tecnica' => 'TEXT',
        'criticidade' => "TEXT DEFAULT 'media'",
        'prioridade' => "TEXT DEFAULT 'media'",
        'recomendacoes' => 'TEXT',
        'fotografia' => 'TEXT',
        'status' => "TEXT DEFAULT 'aguardando_aprovacao'",
        'criado_por' => 'INTEGER',
        'o_que_foi_feito' => 'TEXT',
        'como_foi_feito' => 'TEXT',
        'foto_comprovante' => 'TEXT',
        'aprovado_em' => 'DATETIME',
        'aprovado_por' => 'INTEGER'
    ];
    foreach ($colunasRelatorios as $coluna => $tipo) {
        try {
            $pdo->exec("ALTER TABLE relatorios ADD COLUMN {$coluna} {$tipo}");
        } catch (PDOException $ignored) {}
    }

    $colunasInspecoes = [
        'realizada_em' => 'DATETIME',
        'area_id' => 'INTEGER',
        'equipamento_id' => 'INTEGER',
        'numero_perimetro' => 'TEXT',
        'localizacao' => 'TEXT',
        'objetivo' => 'TEXT',
        'observacoes' => 'TEXT',
        'realizada_por' => 'INTEGER',
        'status' => "TEXT DEFAULT 'concluida'"
    ];
    foreach ($colunasInspecoes as $coluna => $tipo) {
        try {
            $pdo->exec("ALTER TABLE inspecoes ADD COLUMN {$coluna} {$tipo}");
        } catch (PDOException $ignored) {}
    }
}

