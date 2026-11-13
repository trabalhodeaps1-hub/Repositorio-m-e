<?php
// Consulta e Atualização do Perfil do Usuário
require_once __DIR__ . '/../config/bootstrap.php';

$userId = require_login();

// CONSULTA DO PERFIL (GET)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    json_response([
        'success' => true,
        'usuario' => current_user($pdo)
    ]);
}

require_method('POST');

// CAPTURA DE DADOS (Suporta JSON e Formulário Tradicional/FormData)
$data = request_data();

$nome = text_value($data, 'nome', 150);
$email = filter_var(text_value($data, 'email', 180), FILTER_VALIDATE_EMAIL);
$idade = filter_var($data['idade'] ?? null, FILTER_VALIDATE_INT);
$novaSenha = (string) ($data['senha'] ?? $data['nova_senha'] ?? '');

if (!$nome || !$email) {
    json_response([
        'success' => false,
        'message' => 'Por favor, informe um nome e um e-mail válidos.'
    ], 422);
}

if ($idade !== null && $idade !== false && ($idade < 1 || $idade > 125)) {
    json_response([
        'success' => false,
        'message' => 'Por favor, informe uma idade válida (entre 1 e 125 anos).'
    ], 422);
}

// UPLOAD DE FOTO DE PERFIL (se enviada)
$fotoPath = null;
$inputFiles = ['foto', 'fotografia', 'imagem', 'avatar', 'file'];
$file = null;

foreach ($inputFiles as $inputName) {
    if (!empty($_FILES[$inputName]['name'])) {
        $file = $_FILES[$inputName];
        break;
    }
}

if ($file && !empty($file['tmp_name'])) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        json_response([
            'success' => false,
            'message' => 'Erro ao transferir a imagem. Código: ' . $file['error']
        ], 422);
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        json_response([
            'success' => false,
            'message' => 'A foto de perfil deve ter no máximo 5 MB.'
        ], 422);
    }

    // Detecção segura do tipo de imagem
    $mime = '';
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']) ?: '';
    } elseif (function_exists('mime_content_type')) {
        $mime = mime_content_type($file['tmp_name']) ?: '';
    }
    if ($mime === '' && function_exists('getimagesize')) {
        $imgInfo = @getimagesize($file['tmp_name']);
        if ($imgInfo && isset($imgInfo['mime'])) {
            $mime = $imgInfo['mime'];
        }
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif'
    ];

    if (!isset($allowed[$mime])) {
        json_response([
            'success' => false,
            'message' => 'Formato de imagem não suportado. Use JPG, PNG ou WEBP.'
        ], 422);
    }

    $dir = __DIR__ . '/../uploads';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];

    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $filename)) {
        json_response([
            'success' => false,
            'message' => 'Não foi possível salvar o arquivo da foto no servidor.'
        ], 500);
    }

    // Caminho limpo para exibição na tag <img src="uploads/...">
    $fotoPath = 'uploads/' . $filename;
}

// ATUALIZAÇÃO NO BANCO DE DADOS
try {
    // Monta a query dinâmica de acordo com os campos preenchidos
    $camposUpdate = ['nome = :nome', 'email = :email'];
    $parametros = [
        'nome'  => $nome,
        'email' => $email,
        'id'    => $userId
    ];

    if ($idade !== null && $idade !== false) {
        $camposUpdate[] = 'idade = :idade';
        $parametros['idade'] = $idade;
    }

    if ($fotoPath !== null) {
        $camposUpdate[] = 'foto_perfil = :foto';
        $parametros['foto'] = $fotoPath;
    }

    if (strlen($novaSenha) >= 6) {
        $camposUpdate[] = 'senha_hash = :senha';
        $parametros['senha'] = password_hash($novaSenha, PASSWORD_DEFAULT);
    }

    $sql = 'UPDATE usuarios SET ' . implode(', ', $camposUpdate) . ' WHERE id = :id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($parametros);

    // Atualiza os dados na sessão
    $_SESSION['user_nome'] = $nome;
    $_SESSION['user_email'] = $email;

    json_response([
        'success' => true,
        'message' => 'Perfil atualizado com sucesso!',
        'usuario' => current_user($pdo)
    ]);

} catch (PDOException $e) {
    // Tratamento universal de e-mail duplicado (PostgreSQL: 23505, MySQL: 23000, SQLite: 19)
    if (in_array($e->getCode(), ['23000', '23505', '19'], true) || strpos($e->getMessage(), 'UNIQUE') !== false) {
        json_response([
            'success' => false,
            'message' => 'Esse endereço de e-mail já está sendo utilizado por outra conta.'
        ], 409);
    }

    json_response([
        'success' => false,
        'message' => 'Erro ao atualizar o perfil: ' . $e->getMessage()
    ], 500);
}
