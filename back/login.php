<?php
// Autenticação e Login de Usuários
require_once __DIR__ . '/../config/bootstrap.php';

// 1. Verificação de status da sessão (GET)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!empty($_SESSION['user_id'])) {
        try {
            $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $_SESSION['user_id']]);
            $user = $stmt->fetch();

            if ($user) {
                unset($user['senha_hash'], $user['senha']);
                json_response([
                    'logged_in' => true,
                    'usuario'   => $user
                ]);
            }
        } catch (PDOException $ignored) {}
    }

    json_response([
        'logged_in' => false
    ]);
}

require_method('POST');

$data = request_data();

// 2. Aceita login tanto por e-mail quanto por matrícula ou usuário
$identificador = text_value($data, 'email', 180);
if ($identificador === '') {
    $identificador = text_value($data, 'matricula', 180);
    if ($identificador === '') {
        $identificador = text_value($data, 'usuario', 180);
    }
}

$senha = (string) ($data['senha'] ?? '');

if ($identificador === '' || $senha === '') {
    json_response([
        'success' => false,
        'message' => 'Por favor, informe seu e-mail (ou matrícula) e sua senha.'
    ], 422);
}

try {
    // 3. Busca o usuário por e-mail ou matrícula de forma segura
    $stmt = $pdo->prepare('
        SELECT * FROM usuarios 
        WHERE email = :login 
           OR (nome = :login)
        LIMIT 1
    ');
    $stmt->execute(['login' => $identificador]);
    $user = $stmt->fetch();

    // Se não encontrou, tenta buscar por matrícula caso a coluna exista
    if (!$user) {
        try {
            $stmtMatricula = $pdo->prepare('SELECT * FROM usuarios WHERE matricula = :login LIMIT 1');
            $stmtMatricula->execute(['login' => $identificador]);
            $user = $stmtMatricula->fetch();
        } catch (PDOException $ignored) {}
    }

    // 4. Verificação da senha criptografada
    if (!$user || empty($user['senha_hash']) || !password_verify($senha, $user['senha_hash'])) {
        json_response([
            'success' => false,
            'message' => 'E-mail/matrícula ou senha incorretos.'
        ], 401);
    }

    // 5. Inicia e regenera ID da sessão com segurança contra session fixation
    if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
        session_regenerate_id(true);
    }

    $_SESSION['user_id']    = (int) $user['id'];
    $_SESSION['user_nome']  = $user['nome'] ?? '';
    $_SESSION['user_email'] = $user['email'] ?? '';
    $_SESSION['user_papel'] = $user['papel'] ?? 'usuario';

    // Remove a hash da senha antes de retornar os dados para o frontend
    unset($user['senha_hash'], $user['senha']);

    json_response([
        'success' => true,
        'message' => 'Login realizado com sucesso!',
        'usuario' => $user
    ]);

} catch (PDOException $e) {
    json_response([
        'success' => false,
        'message' => 'Erro ao realizar login no banco de dados: ' . $e->getMessage()
    ], 500);
}
