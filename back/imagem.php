<?php
// Upload e Processamento de Imagens
require_once __DIR__ . '/../config/bootstrap.php';

require_login();
require_method('POST');

// 1. Detecta o arquivo enviado aceitando múltiplos nomes de campos comuns (fotografia, foto, imagem, file, etc.)
$inputNames = ['fotografia', 'foto', 'imagem', 'file', 'arquivo', 'avatar'];
$file = null;

foreach ($inputNames as $inputName) {
    if (!empty($_FILES[$inputName]['name'])) {
        $file = $_FILES[$inputName];
        break;
    }
}

// Se nenhum dos nomes conhecidos foi usado, pega o primeiro arquivo enviado
if (!$file && !empty($_FILES)) {
    $firstKey = array_key_first($_FILES);
    if (!empty($_FILES[$firstKey]['name'])) {
        $file = $_FILES[$firstKey];
    }
}

if (!$file || empty($file['tmp_name'])) {
    json_response([
        'success' => false,
        'message' => 'Nenhuma imagem foi selecionada para envio.'
    ], 422);
}

// 2. Validação detalhada dos códigos de erro nativos do PHP
if ($file['error'] !== UPLOAD_ERR_OK) {
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE   => 'A imagem excede o tamanho máximo permitido pelo servidor (upload_max_filesize).',
        UPLOAD_ERR_FORM_SIZE  => 'A imagem excede o tamanho limite permitido pelo formulário.',
        UPLOAD_ERR_PARTIAL    => 'O envio da imagem foi interrompido antes de ser concluído. Tente novamente.',
        UPLOAD_ERR_NO_FILE    => 'Nenhum arquivo de imagem foi enviado.',
        UPLOAD_ERR_NO_TMP_DIR => 'Falha no servidor: diretório temporário ausente.',
        UPLOAD_ERR_CANT_WRITE => 'Falha no servidor: não foi possível gravar a imagem no disco.',
        UPLOAD_ERR_EXTENSION  => 'O envio do arquivo foi bloqueado por uma extensão do servidor.'
    ];

    $msg = $errorMessages[$file['error']] ?? 'Erro ao processar o arquivo de imagem (Código: ' . $file['error'] . ').';
    json_response(['success' => false, 'message' => $msg], 422);
}

// 3. Validação do tamanho do arquivo (máximo de 5 MB)
$maxSize = 5 * 1024 * 1024; // 5 MB
if ($file['size'] > $maxSize) {
    json_response([
        'success' => false,
        'message' => 'A imagem selecionada é muito grande. O tamanho máximo permitido é 5 MB.'
    ], 422);
}

// 4. Identificação segura do tipo MIME
$mime = '';
if (class_exists('finfo')) {
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']) ?: '';
} elseif (function_exists('mime_content_type')) {
    $mime = mime_content_type($file['tmp_name']) ?: '';
}

// Fallback de validação caso finfo não identifique
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
        'message' => 'Formato de imagem inválido. Por favor, envie uma imagem nos formatos JPG, PNG, WEBP ou GIF.'
    ], 422);
}

// 5. Preparação da pasta de destino
$dir = __DIR__ . '/../uploads';
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}

// 6. Geração de nome único e seguro para o arquivo
$fileName = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
$destination = $dir . '/' . $fileName;

// 7. Mover o arquivo para o destino final
if (!move_uploaded_file($file['tmp_name'], $destination)) {
    json_response([
        'success' => false,
        'message' => 'Não foi possível salvar o arquivo no servidor. Verifique as permissões da pasta uploads.'
    ], 500);
}

// Caminho web para uso no frontend (ex.: <img src="uploads/nome.jpg">)
$webPath = 'uploads/' . $fileName;

// 8. Opcional: Se informado usuario_id ou se o usuário logado quiser atualizar sua foto de perfil
if (!empty($_SESSION['user_id'])) {
    try {
        $stmtFoto = $pdo->prepare('UPDATE usuarios SET informacao_problema = COALESCE(informacao_problema, "") WHERE id = :id');
        $stmtFoto->execute(['id' => $_SESSION['user_id']]);
    } catch (PDOException $ignored) {}
}

json_response([
    'success' => true,
    'message' => 'Imagem enviada com sucesso!',
    'path' => $webPath,
    'url' => $webPath,
    'filename' => $fileName,
    'original_name' => $file['name']
], 201);
