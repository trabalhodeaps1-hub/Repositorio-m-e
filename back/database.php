<?php
declare(strict_types=1);

/**
 * ====================================================================
 * CONEXÃO CENTRALIZADA COM O BANCO DE DADOS LOCAL
 * Prioridade: POSTGRESQL (Localhost:5432)
 * Fallback: SQLITE LOCAL (database/app.sqlite)
 * ====================================================================
 */

// 1. Garante os diretórios essenciais
$baseDir = dirname(__DIR__);
$databaseDir = $baseDir . '/database';
if (!is_dir($databaseDir)) {
    mkdir($databaseDir, 0777, true);
}

$uploadsDir = $baseDir . '/uploads';
if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0777, true);
}

// 2. Carrega configurações
$configFile = $baseDir . '/config/database.php';
$config = file_exists($configFile) ? require $configFile : [
    'driver' => 'pgsql',
    'pgsql' => [
        'host' => '127.0.0.1',
        'port' => 5432,
        'database' => 'autorelatorio',
        'user' => 'postgres',
        'password' => 'postgres',
        'alternative_passwords' => ['postgres', '123456', 'admin', 'root', '']
    ],
    'sqlite' => [
        'path' => $databaseDir . '/app.sqlite'
    ]
];

$pdo = null;
$dbDriver = 'pgsql';
$dbStatusMessage = '';
$pgConnectionError = null;

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// 3. TENTATIVA DE CONEXÃO PRIMÁRIA: POSTGRESQL LOCAL
if (extension_loaded('pdo_pgsql') && ($config['driver'] ?? 'pgsql') === 'pgsql') {
    $pgConfig = $config['pgsql'];
    $host = $pgConfig['host'] ?? '127.0.0.1';
    $port = (int) ($pgConfig['port'] ?? 5432);
    $database = $pgConfig['database'] ?? 'autorelatorio';
    $user = $pgConfig['user'] ?? 'postgres';
    
    $passwordsToTest = array_unique(array_merge(
        [(string) ($pgConfig['password'] ?? 'postgres')],
        $pgConfig['alternative_passwords'] ?? []
    ));

    $connectedPass = null;
    $pdoAdmin = null;

    // A. Conecta inicialmente para verificar ou criar o banco 'autorelatorio'
    foreach ($passwordsToTest as $pwd) {
        try {
            // Tenta conectar no banco de manutenção padrão 'postgres'
            $dsnAdmin = "pgsql:host={$host};port={$port};dbname=postgres;options='--client_encoding=UTF8'";
            $testPdo = new PDO($dsnAdmin, $user, $pwd, [
                PDO::ATTR_TIMEOUT => 2,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            $pdoAdmin = $testPdo;
            $connectedPass = $pwd;
            break;
        } catch (PDOException $e) {
            $pgConnectionError = $e->getMessage();
        }
    }

    if ($pdoAdmin !== null && $connectedPass !== null) {
        try {
            // Verifica se o banco da aplicação existe; se não, cria automaticamente
            $checkDbStmt = $pdoAdmin->prepare("SELECT 1 FROM pg_database WHERE datname = :dbname");
            $checkDbStmt->execute(['dbname' => $database]);
            if (!$checkDbStmt->fetchColumn()) {
                $pdoAdmin->exec("CREATE DATABASE \"{$database}\" WITH ENCODING 'UTF8'");
            }
            unset($pdoAdmin);

            // Conecta diretamente ao banco 'autorelatorio'
            $dsnApp = "pgsql:host={$host};port={$port};dbname={$database};options='--client_encoding=UTF8'";
            $pdo = new PDO($dsnApp, $user, $connectedPass, $options);
            $dbDriver = 'pgsql';
            $dbStatusMessage = "Conectado ao PostgreSQL Local (host: {$host}:{$port}, db: {$database}, user: {$user})";

            // B. Criação automática de tabelas PostgreSQL
            $pdo->exec("
                -- 1. TABELA DE USUÁRIOS
                CREATE TABLE IF NOT EXISTS usuarios (
                    id BIGSERIAL PRIMARY KEY,
                    nome VARCHAR(150) NOT NULL,
                    email VARCHAR(180) NOT NULL UNIQUE,
                    idade INT,
                    data_nascimento VARCHAR(20),
                    ano_nascimento INT,
                    cargo VARCHAR(80),
                    senha_hash TEXT NOT NULL,
                    matricula VARCHAR(40) UNIQUE,
                    telefone VARCHAR(30),
                    papel VARCHAR(20) DEFAULT 'funcionario'
                        CHECK (papel IN ('funcionario', 'gerente', 'supervisor', 'usuario')),
                    informacao_problema TEXT,
                    foto_perfil TEXT,
                    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                );

                -- 2. TABELA DE ÁREAS INDUSTRIAIS
                CREATE TABLE IF NOT EXISTS areas (
                    id BIGSERIAL PRIMARY KEY,
                    nome VARCHAR(120) NOT NULL UNIQUE,
                    descricao TEXT DEFAULT '',
                    icone VARCHAR(80) DEFAULT 'factory',
                    criado_por BIGINT REFERENCES usuarios(id) ON DELETE SET NULL,
                    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                );

                -- 3. TABELA DE EQUIPAMENTOS
                CREATE TABLE IF NOT EXISTS equipamentos (
                    id BIGSERIAL PRIMARY KEY,
                    numero_patrimonio VARCHAR(80) NOT NULL UNIQUE,
                    nome VARCHAR(160) NOT NULL,
                    area_id BIGINT REFERENCES areas(id) ON DELETE SET NULL,
                    localizacao VARCHAR(250) NOT NULL,
                    status_operacional BOOLEAN NOT NULL DEFAULT TRUE,
                    criado_por BIGINT REFERENCES usuarios(id) ON DELETE SET NULL,
                    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                );

                -- 4. TABELA DE INSPEÇÕES
                CREATE TABLE IF NOT EXISTS inspecoes (
                    id BIGSERIAL PRIMARY KEY,
                    realizada_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    area_id BIGINT REFERENCES areas(id) ON DELETE SET NULL,
                    equipamento_id BIGINT REFERENCES equipamentos(id) ON DELETE SET NULL,
                    numero_perimetro VARCHAR(100) NOT NULL DEFAULT 'Não informado',
                    localizacao VARCHAR(250) NOT NULL,
                    objetivo TEXT NOT NULL,
                    observacoes TEXT DEFAULT '',
                    status VARCHAR(30) DEFAULT 'concluida',
                    realizada_por BIGINT REFERENCES usuarios(id) ON DELETE SET NULL,
                    criada_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                );

                -- 5. TABELA DE RELATÓRIOS TÉCNICOS
                CREATE TABLE IF NOT EXISTS relatorios (
                    id BIGSERIAL PRIMARY KEY,
                    inspecao_id BIGINT REFERENCES inspecoes(id) ON DELETE CASCADE,
                    titulo_item VARCHAR(180) NOT NULL,
                    local_exato VARCHAR(250) NOT NULL,
                    descricao_tecnica TEXT NOT NULL,
                    criticidade VARCHAR(30) NOT NULL DEFAULT 'media'
                        CHECK (criticidade IN ('baixa', 'media', 'alta', 'critica')),
                    prioridade VARCHAR(30) NOT NULL DEFAULT 'media'
                        CHECK (prioridade IN ('baixa', 'media', 'alta', 'urgente')),
                    recomendacoes TEXT NOT NULL,
                    fotografia TEXT,
                    status VARCHAR(30) NOT NULL DEFAULT 'aguardando_aprovacao'
                        CHECK (status IN ('aguardando_aprovacao', 'aprovado', 'reprovado')),
                    criado_por BIGINT REFERENCES usuarios(id) ON DELETE SET NULL,
                    o_que_foi_feito TEXT,
                    como_foi_feito TEXT,
                    foto_comprovante TEXT,
                    aprovado_por BIGINT REFERENCES usuarios(id) ON DELETE SET NULL,
                    aprovado_em TIMESTAMP,
                    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                );

                -- 6. TABELA DE PROBLEMAS
                CREATE TABLE IF NOT EXISTS problemas (
                    id BIGSERIAL PRIMARY KEY,
                    usuario_id BIGINT REFERENCES usuarios(id) ON DELETE SET NULL,
                    nome VARCHAR(150),
                    email VARCHAR(180),
                    idade INT,
                    descricao_problema TEXT NOT NULL,
                    status VARCHAR(30) DEFAULT 'aberto',
                    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                );

                -- 7. TABELA DE NOTIFICAÇÕES
                CREATE TABLE IF NOT EXISTS notificacoes (
                    id BIGSERIAL PRIMARY KEY,
                    usuario_id BIGINT NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
                    mensagem TEXT NOT NULL,
                    lida BOOLEAN DEFAULT FALSE,
                    link VARCHAR(250) DEFAULT '',
                    criada_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                );
            ");

        } catch (PDOException $e) {
            $pdo = null;
            $pgConnectionError = $e->getMessage();
        }
    }
}

// 4. FALLBACK RESILIENTE: SQLITE LOCAL
// Se o serviço do PostgreSQL estiver parado no Windows ou sem extensão CLI, ativa o SQLite
if ($pdo === null) {
    try {
        $sqlitePath = $config['sqlite']['path'] ?? ($databaseDir . '/app.sqlite');
        $pdo = new PDO('sqlite:' . $sqlitePath, null, null, $options);
        $pdo->exec('PRAGMA foreign_keys = ON;');
        $dbDriver = 'sqlite';
        $dbStatusMessage = "PostgreSQL indisponível (" . ($pgConnectionError ?: 'Serviço offline ou pdo_pgsql ausente') . "). Fallback SQLite ativo.";

        // Cria tabelas no SQLite
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

            CREATE TABLE IF NOT EXISTS areas (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nome TEXT UNIQUE NOT NULL,
                descricao TEXT,
                icone TEXT DEFAULT 'factory',
                criado_por INTEGER,
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
                criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (area_id) REFERENCES areas(id) ON DELETE SET NULL
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
                criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (area_id) REFERENCES areas(id),
                FOREIGN KEY (equipamento_id) REFERENCES equipamentos(id)
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
                o_que_foi_feito TEXT,
                como_foi_feito TEXT,
                foto_comprovante TEXT,
                aprovado_em DATETIME,
                aprovado_por INTEGER,
                criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (inspecao_id) REFERENCES inspecoes(id) ON DELETE CASCADE
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

            CREATE TABLE IF NOT EXISTS notificacoes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                usuario_id INTEGER NOT NULL,
                mensagem TEXT NOT NULL,
                lida INTEGER DEFAULT 0,
                link TEXT,
                criada_em DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");

    } catch (PDOException $e) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode([
            'success' => false,
            'message' => 'Erro crítico ao conectar ao banco de dados: ' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// 5. SEED INICIAL (Gera dados padrão se o banco estiver vazio)
try {
    $totalAreas = (int) $pdo->query('SELECT COUNT(*) FROM areas')->fetchColumn();
    if ($totalAreas === 0) {
        $stmtArea = $pdo->prepare('INSERT INTO areas (nome, descricao, icone) VALUES (:nome, :desc, :icone)');
        $areasPadrao = [
            ['nome' => 'Alto-Forno', 'desc' => 'Setor de fusão e redução primária do minério', 'icone' => 'flame'],
            ['nome' => 'Laminação a Quente', 'desc' => 'Conformação e redução mecânica de chapas de aço', 'icone' => 'cog'],
            ['nome' => 'Aciaria', 'desc' => 'Refino secundário e lingotamento contínuo', 'icone' => 'factory'],
            ['nome' => 'Pátio de Equipamentos', 'desc' => 'Armazenamento de peças pesadas e manutenção externa', 'icone' => 'truck']
        ];
        foreach ($areasPadrao as $a) {
            $stmtArea->execute(['nome' => $a['nome'], 'desc' => $a['desc'], 'icone' => $a['icone']]);
        }
    }

    $totalUsers = (int) $pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
    if ($totalUsers === 0) {
        $stmtUser = $pdo->prepare('
            INSERT INTO usuarios (nome, email, matricula, papel, cargo, senha_hash)
            VALUES (:nome, :email, :matricula, :papel, :cargo, :senha)
        ');
        $senhaPadraoHash = password_hash('123456', PASSWORD_DEFAULT);
        
        $usuariosIniciais = [
            [
                'nome' => 'Carlos Gerente',
                'email' => 'gerente@arcelormittal.com.br',
                'matricula' => 'g1001',
                'papel' => 'gerente',
                'cargo' => 'Gerente'
            ],
            [
                'nome' => 'Marcos Supervisor',
                'email' => 'supervisor@arcelormittal.com.br',
                'matricula' => 's1001',
                'papel' => 'supervisor',
                'cargo' => 'Supervisor'
            ],
            [
                'nome' => 'Brenda Operadora',
                'email' => 'operador@arcelormittal.com.br',
                'matricula' => 'f1001',
                'papel' => 'funcionario',
                'cargo' => 'Técnico de Manutenção'
            ]
        ];

        foreach ($usuariosIniciais as $u) {
            $stmtUser->execute([
                'nome'      => $u['nome'],
                'email'     => $u['email'],
                'matricula' => $u['matricula'],
                'papel'     => $u['papel'],
                'cargo'     => $u['cargo'],
                'senha'     => $senhaPadraoHash
            ]);
        }
    }

    $totalEquip = (int) $pdo->query('SELECT COUNT(*) FROM equipamentos')->fetchColumn();
    if ($totalEquip === 0) {
        $primeiraAreaId = (int) $pdo->query('SELECT id FROM areas LIMIT 1')->fetchColumn() ?: 1;
        $statusOp = ($dbDriver === 'pgsql') ? 'true' : 1;
        $pdo->prepare("
            INSERT INTO equipamentos (numero_patrimonio, nome, area_id, localizacao, status_operacional)
            VALUES (:patrimonio, :nome, :area, :local, {$statusOp})
        ")->execute([
            'patrimonio' => 'EQ-4089',
            'nome'       => 'Ponte Rolante 01 - Laminação',
            'area'       => $primeiraAreaId,
            'local'      => 'Baia A - Setor 03'
        ]);
    }
} catch (Exception $ignored) {}
