<?php
// Consulta e Gerenciamento de Notificações do Usuário
require_once __DIR__ . '/../config/bootstrap.php';

$userId = require_login();

try {
    // 1. CONSULTA DE NOTIFICAÇÕES (GET)
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $pdo->prepare('
            SELECT id, mensagem, lida, link, criada_em
            FROM notificacoes
            WHERE usuario_id = :id
            ORDER BY id DESC
            LIMIT 100
        ');
        $stmt->execute(['id' => $userId]);
        $notificacoes = $stmt->fetchAll();

        // Se o usuário não tiver notificações, insere uma de boas-vindas do sistema
        if (empty($notificacoes)) {
            $stmtBoasVindas = $pdo->prepare('
                INSERT INTO notificacoes (usuario_id, mensagem, lida, link)
                VALUES (:id, "Bem-vindo ao Sistema! Seu cadastro está ativo.", 0, "dashboard.html")
            ');
            $stmtBoasVindas->execute(['id' => $userId]);

            $stmt->execute(['id' => $userId]);
            $notificacoes = $stmt->fetchAll();
        }

        // Conta quantas notificações não foram lidas
        $stmtNaoLidas = $pdo->prepare('
            SELECT COUNT(*) FROM notificacoes 
            WHERE usuario_id = :id AND (lida = 0 OR lida IS FALSE)
        ');
        $stmtNaoLidas->execute(['id' => $userId]);
        $totalNaoLidas = (int) $stmtNaoLidas->fetchColumn();

        json_response([
            'success'          => true,
            'total_nao_lidas'  => $totalNaoLidas,
            'notificacoes'     => $notificacoes
        ]);
    }

    // 2. AÇÕES DE NOTIFICAÇÃO (POST - Marcar como lida)
    require_method('POST');
    $data = request_data();
    $acao = text_value($data, 'acao', 50);

    // Marcar uma notificação específica como lida
    if ($acao === 'marcar_lida' || !empty($data['notificacao_id'])) {
        $notificacaoId = filter_var($data['notificacao_id'] ?? null, FILTER_VALIDATE_INT);
        if ($notificacaoId) {
            $stmtUpdate = $pdo->prepare('
                UPDATE notificacoes SET lida = 1 
                WHERE id = :id AND usuario_id = :user_id
            ');
            $stmtUpdate->execute(['id' => $notificacaoId, 'user_id' => $userId]);
        }

        json_response([
            'success' => true,
            'message' => 'Notificação marcada como lida.'
        ]);
    }

    // Marcar todas as notificações como lidas
    if ($acao === 'marcar_todas' || $acao === 'marcar_todas_lidas') {
        $stmtUpdateTodas = $pdo->prepare('
            UPDATE notificacoes SET lida = 1 
            WHERE usuario_id = :user_id
        ');
        $stmtUpdateTodas->execute(['user_id' => $userId]);

        json_response([
            'success' => true,
            'message' => 'Todas as notificações foram marcadas como lidas.'
        ]);
    }

    json_response([
        'success' => false,
        'message' => 'Ação não informada ou desconhecida.'
    ], 422);

} catch (PDOException $e) {
    json_response([
        'success' => false,
        'message' => 'Erro ao processar notificações: ' . $e->getMessage()
    ], 500);
}
