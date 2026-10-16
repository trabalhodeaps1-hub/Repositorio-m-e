<?php
// Arquivo central de configurações e funções utilitárias (Core / Bootstrap)

// 1. Inicia sessão de forma segura (sem gerar warning se já estiver ativa)
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
}

// 2. Conexão com o banco de dados (reutiliza config/bootstrap.php ou conecta diretamente)
if (file_exists(__DIR__ . '/../config/bootstrap.php')) {
    require_once __DIR__ . '/../config/bootstrap.php';
} elseif (file_exists(__DIR__ . '/database.php')) {
    require_once __DIR__ . '/database.php';
} else {
    // Fallback: conecta ao SQLite padrão da aplicação
    $databaseDir = __DIR__ . '/../database';
    if (!is_dir($databaseDir)) {
        mkdir($databaseDir, 0777, true);
    }
    try {
        $pdo = new PDO('sqlite:' . $databaseDir . '/app.sqlite');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'Erro na conexão com o banco de dados: ' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// 3. Resposta em formato JSON padronizada
if (!function_exists('json_response')) {
    function json_response(array $data, int $status = 200): void {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
        }

        echo json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
        );
        exit;
    }
}

// 4. Captura de dados da requisição (JSON ou formulário POST tradicional)
if (!function_exists('request_data')) {
    function request_data(): array {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'application/json') !== false) {
            $raw = file_get_contents('php://input');
            $json = json_decode($raw ?: '', true);
            if (is_array($json)) {
                return $json;
            }
        }

        return $_POST;
    }
}

// 5. Exigir autenticação do usuário
if (!function_exists('require_login')) {
    function require_login(): int {
        if (empty($_SESSION['user_id'])) {
            json_response([
                'success' => false,
                'message' => 'Faça login para continuar.'
            ], 401);
        }

        return (int) $_SESSION['user_id'];
    }
}

// 6. Exigir método HTTP específico
if (!function_exists('require_method')) {
    function require_method(string $method): void {
        $current = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if ($current !== strtoupper($method)) {
            if (!headers_sent()) {
                header('Allow: ' . strtoupper($method));
            }

            json_response([
                'success' => false,
                'message' => 'Método não permitido: ' . $current
            ], 405);
        }
    }
}

// 7. Sanitização de texto com fallback caso mbstring não esteja instalado
if (!function_exists('text_value')) {
    function text_value(array $data, string $key, int $max = 5000): string {
        $value = trim((string) ($data[$key] ?? ''));
        $length = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);

        if ($length > $max) {
            json_response([
                'success' => false,
                'message' => "O campo {$key} é muito longo (máximo de {$max} caracteres)."
            ], 422);
        }

        return $value;
    }
}

// 8. Obter dados do usuário logado atualmente (sem estourar erro se colunas opcionais faltarem)
if (!function_exists('current_user')) {
    function current_user(PDO $pdo): array {
        $id = require_login();

        $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();

        if (!$user) {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_destroy();
            }

            json_response([
                'success' => false,
                'message' => 'Usuário não encontrado.'
            ], 401);
        }

        // Remove a hash da senha antes de retornar os dados
        unset($user['senha_hash'], $user['senha']);

        return $user;
    }
}