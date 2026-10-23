<?php
declare(strict_types=1);

/**
 * ====================================================================
 * ARMAZENAMENTO LOCAL DEFINITIVO (BANCO DE DADOS LOCAL SQLITE)
 * ====================================================================
 * Este arquivo conecta automaticamente ao banco de dados local em arquivo
 * localizado em: database/app.sqlite
 * 
 * Vantagens do Armazenamento Local:
 * 1. Não depende de PostgreSQL ou MySQL instalado no computador.
 * 2. Não precisa de senhas, portas ou configurações complexas.
 * 3. Funciona em qualquer computador (basta copiar a pasta do projeto).
 * 4. Cria todas as tabelas e dados iniciais automaticamente.
 */

// 1. Garante que os diretórios locais existam
$databaseDir = __DIR__ . '/../database';
if (!is_dir($databaseDir)) {
    mkdir($databaseDir, 0777, true);
}

$uploadsDir = __DIR__ . '/../uploads';
if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0777, true);
}

// 2. Caminho do arquivo de banco de dados local
$databasePath = $databaseDir . '/app.sqlite';

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    // Conecta diretamente ao arquivo local SQLite
    $pdo = new PDO('sqlite:' . $databasePath, null, null, $options);
    
    // Ativa suporte a chaves estrangeiras no SQLite
    $pdo->exec('PRAGMA foreign_keys = ON;');
    
} catch (PDOException $e) {
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao abrir banco de dados local: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 3. Criação automática de tabelas no Armazenamento Local
$pdo->exec("
    -- Tabela de Usuários
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

    -- Tabela de Áreas Industriais
    CREATE TABLE IF NOT EXISTS areas (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nome TEXT UNIQUE NOT NULL,
        descricao TEXT,
        icone TEXT DEFAULT 'factory',
        criado_por INTEGER,
        criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    -- Tabela de Equipamentos
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

    -- Tabela de Inspeções de Campo
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

    -- Tabela de Relatórios Técnicos / Registro de Problemas
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

    -- Tabela de Problemas Avulsos
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

    -- Tabela de Notificações
    CREATE TABLE IF NOT EXISTS notificacoes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        usuario_id INTEGER NOT NULL,
        mensagem TEXT NOT NULL,
        lida INTEGER DEFAULT 0,
        link TEXT,
        criada_em DATETIME DEFAULT CURRENT_TIMESTAMP
    );
");

// 4. Migração de colunas adicionais para compatibilidade
$colunasExtras = [
    'usuarios' => [
        'matricula' => 'TEXT',
        'telefone' => 'TEXT',
        'papel' => "TEXT DEFAULT 'funcionario'",
        'cargo' => 'TEXT',
        'foto_perfil' => 'TEXT'
    ],
    'relatorios' => [
        'o_que_foi_feito' => 'TEXT',
        'como_foi_feito' => 'TEXT',
        'foto_comprovante' => 'TEXT',
        'aprovado_em' => 'DATETIME',
        'aprovado_por' => 'INTEGER'
    ]
];

foreach ($colunasExtras as $tabela => $colunas) {
    foreach ($colunas as $coluna => $tipo) {
        try {
            $pdo->exec("ALTER TABLE {$tabela} ADD COLUMN {$coluna} {$tipo}");
        } catch (PDOException $ignored) {}
    }
}

// 5. Dados Iniciais de Teste (Seed) para o sistema não iniciar vazio
try {
    // Áreas padrão
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

    // Usuários padrão de teste (caso não existam)
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
                'nome' => $u['nome'],
                'email' => $u['email'],
                'matricula' => $u['matricula'],
                'papel' => $u['papel'],
                'cargo' => $u['cargo'],
                'senha' => $senhaPadraoHash
            ]);
        }
    }

    // Equipamento padrão de teste
    $totalEquip = (int) $pdo->query('SELECT COUNT(*) FROM equipamentos')->fetchColumn();
    if ($totalEquip === 0) {
        $primeiraAreaId = (int) $pdo->query('SELECT id FROM areas LIMIT 1')->fetchColumn() ?: 1;
        $pdo->prepare('
            INSERT INTO equipamentos (numero_patrimonio, nome, area_id, localizacao, status_operacional)
            VALUES (:patrimonio, :nome, :area, :local, 1)
        ')->execute([
            'patrimonio' => 'EQ-4089',
            'nome' => 'Ponte Rolante 01 - Laminação',
            'area' => $primeiraAreaId,
            'local' => 'Baia A - Setor 03'
        ]);
    }

} catch (Exception $ignored) {}
