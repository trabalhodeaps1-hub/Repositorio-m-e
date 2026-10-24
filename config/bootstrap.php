<?php
// Configuração e inicialização do sistema (Bootstrap)

// 1. Inicia sessão de forma segura (sem gerar warning se já estiver ativa ou headers enviados)
if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

// 2. Configurações de erro para desenvolvimento
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// 3. Conexão centralizada com o Banco de Dados (PostgreSQL Primário com fallback SQLite)
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
        $dbDriver = 'sqlite';
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
 * Retorna o ID gerado na última inserção compatível com PostgreSQL (sequences) e SQLite
 */
if (!function_exists('db_last_insert_id')) {
    function db_last_insert_id(PDO $pdo, string $table = 'usuarios', string $column = 'id'): int {
        global $dbDriver;
        if (($dbDriver ?? '') === 'pgsql') {
            try {
                $seq = "{$table}_{$column}_seq";
                $id = $pdo->lastInsertId($seq);
                if ($id) {
                    return (int) $id;
                }
            } catch (Exception $e) {}
        }
        return (int) $pdo->lastInsertId();
    }
}

/**
 * Garante que o usuário está autenticado
 */
if (!function_exists('require_login')) {
    function require_login(): int {
        $id = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id || $id < 1) {
            json_response([
                'success' => false,
                'message' => 'Faça login para continuar.'
            ], 401);
        }
        return $id;
    }
}

/** Retorna os dados reais do usuário autenticado, sem informações de senha. */
if (!function_exists('current_user')) {
    function current_user(PDO $pdo): array {
        $id = require_login();
        $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $_SESSION = [];
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_destroy();
            }
            json_response([
                'success' => false,
                'message' => 'Sessão inválida. Faça login novamente.'
            ], 401);
        }

        unset($user['senha_hash'], $user['senha']);
        return $user;
    }
}

/** Restringe operações a cargos autorizados, conferindo os dados do banco. */
if (!function_exists('require_any_role')) {
    function require_any_role(PDO $pdo, array $allowedRoles): array {
        $user = current_user($pdo);
        $roles = [];
        foreach (['cargo', 'papel'] as $field) {
            $value = strtolower(trim((string) ($user[$field] ?? '')));
            if ($value !== '') {
                $roles[] = $value;
            }
        }

        $allowed = false;
        foreach ($roles as $role) {
            foreach ($allowedRoles as $allowedRole) {
                if (preg_match('/\b' . preg_quote(strtolower($allowedRole), '/') . '\b/u', $role)) {
                    $allowed = true;
                    break 2;
                }
            }
        }

        if (!$allowed) {
            json_response([
                'success' => false,
                'message' => 'Permissão negada. Apenas Supervisores e Gerentes podem cadastrar áreas e equipamentos.'
            ], 403);
        }
        return $user;
    }
}
