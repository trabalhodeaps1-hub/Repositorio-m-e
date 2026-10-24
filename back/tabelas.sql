-- ====================================================================
-- ESTRUTURA DO BANCO DE DADOS (PostgreSQL / SQLite / MySQL)
-- ====================================================================

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

-- 2. TABELA DE PROBLEMAS / CHAMADOS RELATADOS
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

-- 3. TABELA DE ÁREAS INDUSTRIAIS
CREATE TABLE IF NOT EXISTS areas (
    id BIGSERIAL PRIMARY KEY,
    nome VARCHAR(120) NOT NULL UNIQUE,
    descricao TEXT DEFAULT '',
    icone VARCHAR(80) DEFAULT 'factory',
    criado_por BIGINT REFERENCES usuarios(id) ON DELETE SET NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 4. TABELA DE EQUIPAMENTOS
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

-- 5. TABELA DE INSPEÇÕES
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

-- 6. TABELA DE RELATÓRIOS TÉCNICOS
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

-- 7. TABELA DE NOTIFICAÇÕES
CREATE TABLE IF NOT EXISTS notificacoes (
    id BIGSERIAL PRIMARY KEY,
    usuario_id BIGINT NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
    mensagem TEXT NOT NULL,
    lida BOOLEAN NOT NULL DEFAULT FALSE,
    link VARCHAR(255) DEFAULT 'dashboard.html',
    criada_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ====================================================================
-- ÍNDICES PARA OTIMIZAÇÃO DE BUSCA E PERFORMANCE
-- ====================================================================
CREATE INDEX IF NOT EXISTS idx_usuarios_email ON usuarios(email);
CREATE INDEX IF NOT EXISTS idx_problemas_usuario ON problemas(usuario_id);
CREATE INDEX IF NOT EXISTS idx_equipamentos_patrimonio ON equipamentos(numero_patrimonio);
CREATE INDEX IF NOT EXISTS idx_inspecoes_usuario ON inspecoes(realizada_por);
CREATE INDEX IF NOT EXISTS idx_relatorios_status ON relatorios(status);
CREATE INDEX IF NOT EXISTS idx_notificacoes_usuario ON notificacoes(usuario_id, lida);
