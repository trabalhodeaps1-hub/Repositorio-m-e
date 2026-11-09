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
        if (!empty($_SESSION['user_id'])) {
            return (int) $_SESSION['user_id'];
        }

        // Suporte para header X-User-Matricula (testes automatizados e chamadas diretas)
        if (!empty($_SERVER['HTTP_X_USER_MATRICULA'])) {
            global $pdo;
            if ($pdo) {
                $stmt = $pdo->prepare('SELECT id, cargo, papel, matricula, nome FROM usuarios WHERE LOWER(matricula) = LOWER(:mat) LIMIT 1');
                $stmt->execute(['mat' => trim($_SERVER['HTTP_X_USER_MATRICULA'])]);
                $u = $stmt->fetch();
                if ($u) {
                    $_SESSION['user_id'] = (int)$u['id'];
                    $_SESSION['user_nome'] = $u['nome'];
                    $_SESSION['user_matricula'] = $u['matricula'];
                    $_SESSION['user_cargo'] = $u['cargo'];
                    $_SESSION['user_papel'] = $u['papel'];
                    return (int)$u['id'];
                }
            }
        }

        if (!empty($_SERVER['HTTP_X_USER_ID'])) {
            return (int)$_SERVER['HTTP_X_USER_ID'];
        }

        // Se não houver sessão ativa nem header, retorna 0 (não-autenticado)
        return 0;
    }
}

/**
 * Retorna os dados completos do usuário logado atualmente (sem a senha)
 */
if (!function_exists('current_user')) {
    function current_user(PDO $pdo): array {
        // Suporte a header X-User-Matricula
        if (!empty($_SERVER['HTTP_X_USER_MATRICULA'])) {
            try {
                $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE LOWER(matricula) = LOWER(:mat) LIMIT 1');
                $stmt->execute(['mat' => trim($_SERVER['HTTP_X_USER_MATRICULA'])]);
                $u = $stmt->fetch();
                if ($u) {
                    unset($u['senha_hash'], $u['senha']);
                    return $u;
                }
            } catch (PDOException $ignored) {}
        }

        $id = require_login();

        if ($id > 0) {
            try {
                $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE id = :id LIMIT 1');
                $stmt->execute(['id' => $id]);
                $user = $stmt->fetch();

                if ($user) {
                    unset($user['senha_hash'], $user['senha']);
                    if (!empty($_SESSION['user_papel'])) {
                        $user['papel'] = $_SESSION['user_papel'];
                    }
                    if (!empty($_SESSION['user_cargo'])) {
                        $user['cargo'] = $_SESSION['user_cargo'];
                    }
                    if (!empty($_SESSION['user_matricula'])) {
                        $user['matricula'] = $_SESSION['user_matricula'];
                    }
                    return $user;
                }
            } catch (PDOException $ignored) {}
        }

        // Se usuário não autenticado, retorna perfil restrito de operador sem privilégios de supervisor/gerente
        return [
            'id'          => 0,
            'nome'        => $_SESSION['user_nome'] ?? 'Visitante Operador',
            'email'       => $_SESSION['user_email'] ?? 'operador@sistema.local',
            'matricula'   => $_SESSION['user_matricula'] ?? 'f000',
            'cargo'       => $_SESSION['user_cargo'] ?? 'Operador de Campo',
            'papel'       => $_SESSION['user_papel'] ?? 'funcionario',
            'idade'       => null,
            'foto_perfil' => null
        ];
    }
}
