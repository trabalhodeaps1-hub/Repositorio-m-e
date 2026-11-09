<?php
// Cadastro de Contas Vinculado aos Funcionários da Empresa
require_once __DIR__ . '/../config/bootstrap.php';

// Permite GET para listar registros cadastrados (útil para consultas ou depuração)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmtUsers = $pdo->query('
        SELECT id, funcionario_id, nome, email, matricula, cargo, papel, data_nascimento, criado_em 
        FROM usuarios 
        ORDER BY id DESC
    ');

    $stmtFuncs = $pdo->query('
        SELECT id, matricula, nome_completo, data_nascimento, cargo, conta_criada, criado_em
        FROM funcionarios
        ORDER BY id ASC
    ');
    
    json_response([
        'success'      => true,
        'usuarios'     => $stmtUsers->fetchAll(),
        'funcionarios' => $stmtFuncs->fetchAll()
    ]);
}

require_method('POST');

$data = request_data();

// 1. Captura e sanitização dos campos enviados
$nome = text_value($data, 'nome', 150);
$dataNascimentoRaw = text_value($data, 'data_nascimento', 20);
if ($dataNascimentoRaw === '') {
    $dataNascimentoRaw = text_value($data, 'dataNascimento', 20);
}

$codigoAtivacao = strtoupper(trim((string)($data['codigo_ativacao'] ?? ($data['codigoAtivacao'] ?? ''))));
$senha = (string) ($data['senha'] ?? '');
$confirmaSenha = (string) ($data['confirmar_senha'] ?? ($data['confirma_senha'] ?? ($data['confirmarSenha'] ?? '')));
$matriculaInformada = strtoupper(trim(text_value($data, 'matricula', 40)));
$telefone = text_value($data, 'telefone', 30);
$email = filter_var(text_value($data, 'email', 180), FILTER_VALIDATE_EMAIL);

// 2. Validações básicas de preenchimento
if ($nome === '') {
    json_response([
        'success' => false,
        'message' => 'O campo Nome Completo é obrigatório.'
    ], 422);
}

if ($dataNascimentoRaw === '') {
    json_response([
        'success' => false,
        'message' => 'O campo Data de Nascimento é obrigatório.'
    ], 422);
}

if ($codigoAtivacao === '') {
    json_response([
        'success' => false,
        'message' => 'Por favor, informe o código de uso único entregue pela empresa.'
    ], 422);
}

if (strlen($senha) < 6) {
    json_response([
        'success' => false,
        'message' => 'A senha deve ter no mínimo 6 caracteres.'
    ], 422);
}

if ($confirmaSenha !== '' && $senha !== $confirmaSenha) {
    json_response([
        'success' => false,
        'message' => 'As senhas informadas não coincidem.'
    ], 422);
}

// 3. Normalização flexível da Data de Nascimento (suporta DD/MM/YYYY e YYYY-MM-DD)
$nascIso = '';
$nascBr = '';

if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $dataNascimentoRaw, $m)) {
    $nascIso = "{$m[3]}-{$m[2]}-{$m[1]}";
    $nascBr = $dataNascimentoRaw;
} elseif (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dataNascimentoRaw, $m)) {
    $nascIso = $dataNascimentoRaw;
    $nascBr = "{$m[3]}/{$m[2]}/{$m[1]}";
} else {
    try {
        $dt = new DateTime($dataNascimentoRaw);
        $nascIso = $dt->format('Y-m-d');
        $nascBr = $dt->format('d/m/Y');
    } catch (Exception $e) {
        json_response([
            'success' => false,
            'message' => 'Formato de data de nascimento inválido. Utilize DD/MM/AAAA.'
        ], 422);
    }
}

try {
    // 4. Busca pelo registro prévio do funcionário na empresa
    $stmtBusca = $pdo->prepare("
        SELECT * FROM funcionarios 
        WHERE LOWER(TRIM(nome_completo)) = LOWER(TRIM(:nome))
          AND (data_nascimento = :nascIso OR data_nascimento = :nascBr)
    ");
    $stmtBusca->execute([
        'nome'    => $nome,
        'nascIso' => $nascIso,
        'nascBr'  => $nascBr
    ]);
    $funcionarios = $stmtBusca->fetchAll();

    // 4.1. Nenhum funcionário encontrado
    if (empty($funcionarios)) {
        json_response([
            'success' => false,
            'message' => 'Nenhum funcionário encontrado com o nome e data de nascimento informados. Verifique seus dados com o RH da empresa.'
        ], 404);
    }

    $func = null;

    // 4.2. Mais de uma correspondência (Homônimos com a mesma data de nascimento)
    if (count($funcionarios) > 1) {
        if ($matriculaInformada === '') {
            json_response([
                'success'          => false,
                'requer_matricula' => true,
                'message'          => 'Foram encontrados múltiplos funcionários com este nome e data de nascimento. Por favor, informe sua matrícula para distinguir seu registro.'
            ], 422);
        }

        foreach ($funcionarios as $f) {
            if (strtoupper(trim($f['matricula'])) === $matriculaInformada) {
                $func = $f;
                break;
            }
        }

        if (!$func) {
            json_response([
                'success'          => false,
                'requer_matricula' => true,
                'message'          => "A matrícula informada ({$matriculaInformada}) não confere com os registros encontrados para este nome e data de nascimento."
            ], 422);
        }
    } else {
        // Exatamente um funcionário encontrado -> matrícula identificada automaticamente
        $func = $funcionarios[0];

        // Se o usuário também digitou matrícula, valida se bate
        if ($matriculaInformada !== '' && strtoupper(trim($func['matricula'])) !== $matriculaInformada) {
            json_response([
                'success' => false,
                'message' => "A matrícula informada não corresponde ao seu registro cadastral ({$func['matricula']})."
            ], 422);
        }
    }

    $matriculaIdentificada = $func['matricula'];
    $funcionarioId = (int) $func['id'];

    // 5. Garantia no banco: cada matrícula só pode ter UMA conta
    $stmtChecaConta = $pdo->prepare("
        SELECT id FROM usuarios 
        WHERE matricula = :mat 
           OR (funcionario_id IS NOT NULL AND funcionario_id = :fid)
        LIMIT 1
    ");
    $stmtChecaConta->execute([
        'mat' => $matriculaIdentificada,
        'fid' => $funcionarioId
    ]);
    $contaExistente = $stmtChecaConta->fetch();

    $contaCriadaFlag = !empty($func['conta_criada']) && (
        $func['conta_criada'] === true || $func['conta_criada'] === 't' || (int)$func['conta_criada'] === 1
    );

    if ($contaCriadaFlag || $contaExistente) {
        json_response([
            'success' => false,
            'message' => "Esta matrícula ({$matriculaIdentificada}) já possui uma conta ativa no sistema. Não é permitido criar uma segunda conta."
        ], 409);
    }

    // 6. Validação do código de uso único entregue pela empresa
    $codigoEsperado = strtoupper(trim((string)($func['codigo_ativacao'] ?? '')));
    if ($codigoEsperado === '' || $codigoAtivacao !== $codigoEsperado) {
        json_response([
            'success' => false,
            'message' => 'Código de ativação de uso único inválido ou expirado.'
        ], 422);
    }

    // 7. Definição do perfil de acesso a partir do cargo registrado
    $cargoFunc = $func['cargo'];
    $cargoLower = strtolower($cargoFunc);
    $prefixoMatricula = strtolower(substr($matriculaIdentificada, 0, 1));

    $papel = 'funcionario';
    if (strpos($cargoLower, 'supervisor') !== false || $prefixoMatricula === 's') {
        $papel = 'supervisor';
    } elseif (strpos($cargoLower, 'gerente') !== false || $prefixoMatricula === 'g') {
        $papel = 'gerente';
    }

    if (!$email) {
        $email = strtolower($matriculaIdentificada) . '@arcelormittal.com.br';
    }

    // 8. Criação da conta e vinculação ao funcionário existente
    $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

    $stmtCriaUsuario = $pdo->prepare('
        INSERT INTO usuarios 
        (funcionario_id, nome, email, data_nascimento, cargo, papel, telefone, matricula, senha_hash)
        VALUES 
        (:func_id, :nome, :email, :nasc, :cargo, :papel, :telefone, :matricula, :senha_hash)
    ');

    $stmtCriaUsuario->execute([
        'func_id'    => $funcionarioId,
        'nome'       => $func['nome_completo'],
        'email'      => $email,
        'nasc'       => $nascIso,
        'cargo'      => $cargoFunc,
        'papel'      => $papel,
        'telefone'   => $telefone ?: null,
        'matricula'  => $matriculaIdentificada,
        'senha_hash' => $senhaHash
    ]);

    $usuarioId = db_last_insert_id($pdo, 'usuarios');

    // 9. Atualização no funcionário: marca conta_criada = true e anula o código de uso único
    $criadaVal = (($dbDriver ?? '') === 'pgsql') ? 'true' : 1;
    $stmtAtualizaFunc = $pdo->prepare("
        UPDATE funcionarios 
        SET conta_criada = {$criadaVal}, codigo_ativacao = NULL 
        WHERE id = :id
    ");
    $stmtAtualizaFunc->execute(['id' => $funcionarioId]);

    // 10. Inicia a sessão com as credenciais vinculadas
    $_SESSION['user_id']        = $usuarioId;
    $_SESSION['user_nome']      = $func['nome_completo'];
    $_SESSION['user_email']     = $email;
    $_SESSION['user_matricula'] = $matriculaIdentificada;
    $_SESSION['user_cargo']     = $cargoFunc;
    $_SESSION['user_papel']     = $papel;

    json_response([
        'success'   => true,
        'message'   => "Conta ativada com sucesso! Matrícula {$matriculaIdentificada} vinculada a {$func['nome_completo']}.",
        'id'        => $usuarioId,
        'usuario'   => [
            'id'        => $usuarioId,
            'nome'      => $func['nome_completo'],
            'email'     => $email,
            'matricula' => $matriculaIdentificada,
            'cargo'     => $cargoFunc,
            'papel'     => $papel
        ]
    ], 201);

} catch (PDOException $e) {
    if (in_array($e->getCode(), ['23000', '23505', '19'], true) || strpos($e->getMessage(), 'UNIQUE') !== false) {
        json_response([
            'success' => false,
            'message' => 'Esta matrícula já possui uma conta ativa cadastrada no sistema.'
        ], 409);
    }

    json_response([
        'success' => false,
        'message' => 'Erro ao ativar a conta: ' . $e->getMessage()
    ], 500);
}
