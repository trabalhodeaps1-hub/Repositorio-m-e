<?php
// Encerramento de Sessão (Logout)
require_once __DIR__ . '/../config/bootstrap.php';

// 1. Limpa todas as variáveis da sessão caso esteja ativa
if (session_status() === PHP_SESSION_ACTIVE) {
    $_SESSION = [];

    // Invalida o cookie de sessão no navegador do usuário
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        if (PHP_VERSION_ID >= 70300) {
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $params['path'] ?? '/',
                'domain'   => $params['domain'] ?? '',
                'secure'   => $params['secure'] ?? false,
                'httponly' => $params['httponly'] ?? true,
                'samesite' => $params['samesite'] ?? 'Lax'
            ]);
        } else {
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'] ?? '/',
                $params['domain'] ?? '',
                $params['secure'] ?? false,
                $params['httponly'] ?? true
            );
        }
    }

    session_destroy();
}

// 2. Se a requisição veio de um link comum no navegador (GET direto sem AJAX), redireciona para a tela inicial
$wantsJson = (!empty($_SERVER['HTTP_ACCEPT']) && stripos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
             || (!empty($_SERVER['CONTENT_TYPE']) && stripos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)
             || (isset($_GET['format']) && $_GET['format'] === 'json');

if ($_SERVER['REQUEST_METHOD'] === 'GET' && !$wantsJson) {
    // Redireciona para index.html ou página de login
    $redirect = file_exists(__DIR__ . '/../index.html') ? '../index.html' : '../pagina_inicial.html';
    header('Location: ' . $redirect);
    exit;
}

// 3. Se for uma requisição via JavaScript (fetch/AJAX), responde com JSON
json_response([
    'success' => true,
    'message' => 'Sessão encerrada com sucesso.'
]);
