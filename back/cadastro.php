<?php
// Cadastro de Usuário e Registro de Problemas
require_once __DIR__ . '/../config/bootstrap.php';

// Permite GET para listar registros cadastrados (útil para consultas ou depuração)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->query('
        SELECT id, nome, email, idade, informacao_problema, criado_em 
        FROM usuarios 
        ORDER BY id DESC
    ');
    
    json_response([
        'success' => true,
        'usuarios' => $stmt->fetchAll()
    ]);
}

require_method('POST');

$data = request_data();

// Captura e sanitização dos campos solicitados
$nome = text_value($data, 'nome', 150);
$email = filter_var(text_value($data, 'email', 180), FILTER_VALIDATE_EMAIL);
$cargo = text_value($data, 'cargo', 80);
$dataNascimento = text_value($data, 'data_nascimento', 20);
if ($dataNascimento === '') {
    $dataNascimento = text_value($data, 'dataNascimento', 20);
}

$idade = null;
$anoNascimento = null;

if ($dataNascimento !== '') {
    try {
        $nascObj = new DateTime($dataNascimento);
        $hoje = new DateTime('today');
        if ($nascObj <= $hoje) {
            $idade = $nascObj->diff($hoje)->y;
            $anoNascimento = (int) $nascObj->format('Y');
        }
    } catch (Exception $e) {
        $idade = null;
    }
} elseif (!empty($data['ano_nascimento']) || !empty($data['anoNascimento'])) {
    $anoNascimento = (int) ($data['ano_nascimento'] ?? $data['anoNascimento']);
    $idade = (int) date('Y') - $anoNascimento;
    $dataNascimento = "{$anoNascimento}-01-01";
} else {
    $idade = filter_var($data['idade'] ?? null, FILTER_VALIDATE_INT);
}

$senha = (string) ($data['senha'] ?? '');
$confirmaSenha = (string) ($data['confirmar_senha'] ?? ($data['confirma_senha'] ?? ($data['confirmarSenha'] ?? '')));
$telefone = text_value($data, 'telefone', 30);

// Captura a descrição do problema aceitando múltiplos nomes possíveis de input
$problema = text_value($data, 'informacao_problema', 3000);
if ($problema === '') {
    $problema = text_value($data, 'problema', 3000);
    if ($problema === '') {
        $problema = text_value($data, 'descricao', 3000);
    }
}

// 1. Validação de campos obrigatórios
if ($nome === '') {
    json_response([
        'success' => false,
        'message' => 'O campo Nome é obrigatório.'
    ], 422);
}

if (!$email) {
    json_response([
        'success' => false,
        'message' => 'Por favor, informe um endereço de e-mail válido.'
    ], 422);
}

if (strlen($senha) < 6) {
    json_response([
        'success' => false,
        'message' => 'A senha deve ter no mínimo 6 caracteres.'
    ], 422);
}

// 2. Se o formulário tiver confirmação de senha, valida se coincidem
if ($confirmaSenha !== '' && $senha !== $confirmaSenha) {
    json_response([
        'success' => false,
        'message' => 'As senhas informadas não coincidem.'
    ], 422);
}

// 3. Validação de idade e cargo (obrigatório caso não seja formulário por matrícula)
$matricula = text_value($data, 'matricula', 40);
$isFormMatricula = ($matricula !== '');
$isRelatoAvulso = ($problema !== '' && ($nome === 'Relato Técnico' || strpos($email, 'relato@') !== false));

if (!$isRelatoAvulso && !$isFormMatricula) {
    if ($dataNascimento === '' || $idade === null) {
        json_response([
            'success' => false,
            'message' => 'Por favor, informe uma data de nascimento válida.'
        ], 422);
    }

    if ($idade < 18 || $idade > 99) {
        json_response([
            'success' => false,
            'message' => "Cadastro permitido apenas para pessoas entre 18 e 99 anos (idade calculada: {$idade} anos)."
        ], 422);
    }

    $cargosPermitidos = ['Operador de Máquina', 'Técnico de Manutenção', 'Supervisor', 'Gerente'];
    if ($cargo === '' || !in_array($cargo, $cargosPermitidos, true)) {
        json_response([
            'success' => false,
            'message' => 'Por favor, selecione um cargo válido: Operador de Máquina, Técnico de Manutenção, Supervisor ou Gerente.'
        ], 422);
    }
}

try {
    // 4. Verifica se o e-mail já existe
    $checkStmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = :email LIMIT 1');
    $checkStmt->execute(['email' => $email]);
    if ($checkStmt->fetch()) {
        json_response([
            'success' => false,
            'message' => 'Este e-mail já está cadastrado no sistema.'
        ], 409);
    }

    // 5. Configuração da matrícula e do papel (papel por prefixo ou por cargo)
    if ($matricula === '') {
        $matricula = 'AM-' . str_pad((string) rand(1000, 99999), 5, '0', STR_PAD_LEFT);
    }

    $prefixo = strtolower(substr($matricula, 0, 1));
    $papel = 'funcionario';
    if ($cargo === 'Supervisor' || $prefixo === 's') {
        $papel = 'supervisor';
        if ($cargo === '') $cargo = 'Supervisor';
    } elseif ($cargo === 'Gerente' || $prefixo === 'g') {
        $papel = 'gerente';
        if ($cargo === '') $cargo = 'Gerente';
    } elseif ($cargo === '') {
        $cargo = 'Técnico de Manutenção';
    }

    $stmtUser = $pdo->prepare('
        INSERT INTO usuarios (nome, email, idade, data_nascimento, ano_nascimento, cargo, papel, telefone, matricula, senha_hash, informacao_problema)
        VALUES (:nome, :email, :idade, :data_nasc, :ano_nasc, :cargo, :papel, :telefone, :matricula, :senha_hash, :problema)
    ');

    $stmtUser->execute([
        'nome' => $nome,
        'email' => $email,
        'idade' => $idade ?: null,
        'data_nasc' => $dataNascimento ?: null,
        'ano_nasc' => $anoNascimento ?: null,
        'cargo' => $cargo ?: null,
        'papel' => $papel,
        'telefone' => $telefone ?: null,
        'matricula' => $matricula,
        'senha_hash' => password_hash($senha, PASSWORD_DEFAULT),
        'problema' => $problema !== '' ? $problema : null
    ]);

    $usuarioId = (int) $pdo->lastInsertId();

    // 6. Se houver descrição do problema, registra também na tabela de problemas
    if ($problema !== '') {
        $stmtProblema = $pdo->prepare("
            INSERT INTO problemas (usuario_id, nome, email, idade, descricao_problema, status)
            VALUES (:usuario_id, :nome, :email, :idade, :descricao, 'aberto')
        ");
        $stmtProblema->execute([
            'usuario_id' => $usuarioId,
            'nome' => $nome,
            'email' => $email,
            'idade' => $idade ?: null,
            'descricao' => $problema
        ]);
    }

    // 7. Salva os dados na sessão
    $_SESSION['user_id'] = $usuarioId;
    $_SESSION['user_nome'] = $nome;
    $_SESSION['user_email'] = $email;
    $_SESSION['user_cargo'] = $cargo;
    $_SESSION['user_papel'] = $papel;

    json_response([
        'success' => true,
        'message' => 'Cadastro e informações salvos com sucesso!',
        'id' => $usuarioId,
        'dados' => [
            'nome' => $nome,
            'email' => $email,
            'idade' => $idade,
            'problema' => $problema
        ]
    ], 201);

} catch (PDOException $e) {
    if (in_array($e->getCode(), ['23000', '23505', '19'], true) || strpos($e->getMessage(), 'UNIQUE') !== false) {
        json_response([
            'success' => false,
            'message' => 'Este e-mail ou matrícula já está cadastrado no sistema.'
        ], 409);
    }

    json_response([
        'success' => false,
        'message' => 'Erro ao salvar os dados: ' . $e->getMessage()
    ], 500);
}
