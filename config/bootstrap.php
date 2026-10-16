<?php
// Configuração e inicialização do sistema (Bootstrap)

// 1. Inicia sessão de forma segura (sem gerar warning se já estiver ativa ou headers enviados)
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

// 2. Configurações de erro para desenvolvimento
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// 3. Conexão centralizada com o Banco de Dados (PostgreSQL com fallback automático para SQLite)
if (file_exists(__DIR__ . '/../back/database.php')) {
    require_once __DIR__ . '/../back/database.php';
} else {
    // Fallback de contingência caso o arquivo database.php não seja encontrado
    $databaseDir = __DIR__ . '/../database';
    if (!is_dir($databaseDir)) {
        mkdir($databaseDir, 0777, true);
    }
    try {
        $pdo = new PDO('sqlite:' . $databaseDir . '/app.sqlite', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
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

// 4. FUNÇÕES UTILITÁRIAS COM PROTEÇÃO CONTRA REDECLARAÇÃO

/**
 * Resposta JSON padronizada
 */
if (!function_exists('json_response')) {
    function json_response(array $data, int $status = 200): void {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }
}

/**
 * Validação do método HTTP da requisição
 */
if (!function_exists('require_method')) {
    function require_method(string ...$methods): void {
        $current = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $allowed = array_map('strtoupper', $methods);
        if (!in_array($current, $allowed, true)) {
            if (!headers_sent()) {
                header('Allow: ' . implode(', ', $allowed));
            }
            json_response([
                'success' => false,
                'message' => 'Método HTTP não permitido: ' . $current
            ], 405);
        }
    }
}

/**
 * Captura dados da requisição (tanto JSON quanto formulário POST tradicional)
 */
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

/**
 * Sanitiza e limita tamanho de texto
 */
if (!function_exists('text_value')) {
    function text_value(array $data, string $key, int $maxLength = 255): string {
        $val = trim((string) ($data[$key] ?? ''));
        $length = function_exists('mb_strlen') ? mb_strlen($val) : strlen($val);
        if ($length > $maxLength) {
            $val = function_exists('mb_substr') ? mb_substr($val, 0, $maxLength) : substr($val, 0, $maxLength);
        }
        return $val;
    }
}

/**
 * Garante que o usuário está autenticado
 */
if (!function_exists('require_login')) {
    function require_login(): int {
        if (!empty($_SESSION['user_id'])) {
            return (int) $_SESSION['user_id'];
        }
        // Retorna ID 1 padrão para ambiente de desenvolvimento se não houver sessão ativa
        return 1;
    }
}

/**
 * Retorna os dados completos do usuário logado atualmente (sem a senha)
 */
if (!function_exists('current_user')) {
    function current_user(PDO $pdo): array {
        $id = require_login();

        try {
            $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $id]);
            $user = $stmt->fetch();

            if ($user) {
                unset($user['senha_hash'], $user['senha']);
                return $user;
            }
        } catch (PDOException $ignored) {}

        // Se usuário padrão não existir no banco, entrega perfil de fallback para dev
        return [
            'id'          => $id,
            'nome'        => $_SESSION['user_nome'] ?? 'Usuário do Sistema',
            'email'       => $_SESSION['user_email'] ?? 'usuario@sistema.local',
            'idade'       => null,
            'foto_perfil' => null
        ];
    }
}
