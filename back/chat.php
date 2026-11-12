<?php
// API de Comunicação Interna (Chat em Tempo Real Técnico <-> Supervisor)
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

// Garante headers JSON
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

// 1. Verificação de Autenticação
$currentUser = current_user($pdo);
$userId = (int) ($currentUser['id'] ?? 0);

if ($userId <= 0) {
    json_response([
        'success'   => false,
        'logged_in' => false,
        'message'   => 'Sessão expirada ou usuário não autenticado.'
    ], 401);
}

$meuPapel = strtolower((string) ($currentUser['papel'] ?? 'funcionario'));
$meuCargo = strtolower((string) ($currentUser['cargo'] ?? ''));
$minhaMatricula = strtolower((string) ($currentUser['matricula'] ?? ''));

$action = trim((string) ($_GET['action'] ?? ''));

// ====================================================================
// AÇÃO 1: Listar Contatos Disponíveis para Conversa
// ====================================================================
if ($action === 'contatos') {
    require_method('GET');

    try {
        // Busca todos os outros usuários do sistema
        $stmtUsers = $pdo->prepare('
            SELECT id, nome, email, matricula, papel, cargo, foto_perfil
            FROM usuarios
            WHERE id != :meu_id
            ORDER BY 
                CASE 
                    WHEN papel IN (\'supervisor\', \'gerente\') THEN 1 
                    ELSE 2 
                END,
                nome ASC
        ');
        $stmtUsers->execute(['meu_id' => $userId]);
        $todosUsuarios = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

        // Prepara queries para estatísticas de conversa por contato
        $stmtUltima = $pdo->prepare('
            SELECT mensagem, criado_em, remetente_id, lida
            FROM mensagens_chat
            WHERE (remetente_id = :meu_id AND destinatario_id = :outro_id)
               OR (remetente_id = :outro_id AND destinatario_id = :meu_id)
            ORDER BY id DESC
            LIMIT 1
        ');

        $stmtNaoLidas = $pdo->prepare('
            SELECT COUNT(*)
            FROM mensagens_chat
            WHERE remetente_id = :outro_id 
              AND destinatario_id = :meu_id 
              AND (lida = 0 OR lida = FALSE)
        ');

        $contatos = [];
        $totalNaoLidasGeral = 0;

        foreach ($todosUsuarios as $u) {
            $outroId = (int) $u['id'];

            // Busca última mensagem
            $stmtUltima->execute(['meu_id' => $userId, 'outro_id' => $outroId]);
            $ultima = $stmtUltima->fetch(PDO::FETCH_ASSOC);

            // Busca mensagens não lidas
            $stmtNaoLidas->execute(['outro_id' => $outroId, 'meu_id' => $userId]);
            $naoLidas = (int) $stmtNaoLidas->fetchColumn();
            $totalNaoLidasGeral += $naoLidas;

            $contatos[] = [
                'id'                   => $outroId,
                'nome'                 => $u['nome'] ?? 'Usuário',
                'matricula'            => $u['matricula'] ?? '',
                'papel'                => $u['papel'] ?? 'funcionario',
                'cargo'                => $u['cargo'] ?? ($u['papel'] === 'supervisor' ? 'Supervisor' : 'Técnico'),
                'foto_perfil'          => $u['foto_perfil'] ?? '',
                'ultima_mensagem'      => $ultima ? (string) $ultima['mensagem'] : '',
                'ultima_data'          => $ultima ? (string) $ultima['criado_em'] : '',
                'ultimo_remetente_id'  => $ultima ? (int) $ultima['remetente_id'] : 0,
                'nao_lidas'            => $naoLidas,
                'online'               => true
            ];
        }

        // Ordena contatos: quem tem mensagens mais recentes primeiro
        usort($contatos, function ($a, $b) {
            if ($a['nao_lidas'] > 0 && $b['nao_lidas'] === 0) return -1;
            if ($b['nao_lidas'] > 0 && $a['nao_lidas'] === 0) return 1;
            if (!empty($a['ultima_data']) && !empty($b['ultima_data'])) {
                return strcmp($b['ultima_data'], $a['ultima_data']);
            }
            if (!empty($a['ultima_data'])) return -1;
            if (!empty($b['ultima_data'])) return 1;
            return strcmp($a['nome'], $b['nome']);
        });

        json_response([
            'success'            => true,
            'usuario_atual'      => [
                'id'        => $userId,
                'nome'      => $currentUser['nome'],
                'matricula' => $currentUser['matricula'],
                'papel'     => $currentUser['papel'],
                'cargo'     => $currentUser['cargo']
            ],
            'total_nao_lidas'    => $totalNaoLidasGeral,
            'contatos'           => $contatos
        ]);

    } catch (PDOException $e) {
        json_response([
            'success' => false,
            'message' => 'Erro ao listar contatos: ' . $e->getMessage()
        ], 500);
    }
}

// ====================================================================
// AÇÃO 2: Histórico Completo de uma Conversa Individual
// ====================================================================
if ($action === 'historico') {
    require_method('GET');

    $contatoId = (int) ($_GET['contato_id'] ?? 0);
    if ($contatoId <= 0) {
        json_response([
            'success' => false,
            'message' => 'Identificador do contato não informado.'
        ], 422);
    }

    try {
        // Valida se o contato existe
        $stmtC = $pdo->prepare('SELECT id, nome, matricula, papel, cargo, foto_perfil FROM usuarios WHERE id = :id LIMIT 1');
        $stmtC->execute(['id' => $contatoId]);
        $contato = $stmtC->fetch(PDO::FETCH_ASSOC);

        if (!$contato) {
            json_response([
                'success' => false,
                'message' => 'Contato não encontrado.'
            ], 404);
        }

        // Marca como lidas todas as mensagens recebidas deste contato
        global $dbDriver;
        $lidaTrue = ($dbDriver === 'pgsql') ? 'TRUE' : '1';
        $lidaFalse = ($dbDriver === 'pgsql') ? 'FALSE' : '0';

        $stmtMarca = $pdo->prepare("
            UPDATE mensagens_chat
            SET lida = {$lidaTrue}
            WHERE remetente_id = :contato_id 
              AND destinatario_id = :meu_id 
              AND (lida = {$lidaFalse} OR lida IS NULL)
        ");
        $stmtMarca->execute([
            'contato_id' => $contatoId,
            'meu_id'     => $userId
        ]);

        // Carrega o histórico completo de mensagens entre ambos
        $stmtMsg = $pdo->prepare('
            SELECT m.id, m.remetente_id, m.destinatario_id, m.mensagem, m.lida, m.criado_em,
                   u.nome AS remetente_nome, u.matricula AS remetente_matricula
            FROM mensagens_chat m
            JOIN usuarios u ON u.id = m.remetente_id
            WHERE (m.remetente_id = :meu_id AND m.destinatario_id = :contato_id)
               OR (m.remetente_id = :contato_id AND m.destinatario_id = :meu_id)
            ORDER BY m.id ASC
        ');
        $stmtMsg->execute([
            'meu_id'     => $userId,
            'contato_id' => $contatoId
        ]);
        $mensagens = $stmtMsg->fetchAll(PDO::FETCH_ASSOC);

        // Formata mensagens para o frontend
        $listaFormatada = [];
        foreach ($mensagens as $m) {
            $ehMeu = ((int) $m['remetente_id'] === $userId);
            $listaFormatada[] = [
                'id'             => (int) $m['id'],
                'remetente_id'   => (int) $m['remetente_id'],
                'destinatario_id'=> (int) $m['destinatario_id'],
                'remetente_nome' => $m['remetente_nome'],
                'mensagem'       => $m['mensagem'],
                'lida'           => (bool) $m['lida'],
                'criado_em'      => $m['criado_em'],
                'hora'           => substr((string)$m['criado_em'], 11, 5) ?: date('H:i'),
                'data'           => substr((string)$m['criado_em'], 0, 10) ?: date('Y-m-d'),
                'eh_meu'         => $ehMeu
            ];
        }

        json_response([
            'success'   => true,
            'contato'   => $contato,
            'mensagens' => $listaFormatada
        ]);

    } catch (PDOException $e) {
        json_response([
            'success' => false,
            'message' => 'Erro ao carregar histórico: ' . $e->getMessage()
        ], 500);
    }
}

// ====================================================================
// AÇÃO 3: Enviar Nova Mensagem
// ====================================================================
if ($action === 'enviar') {
    require_method('POST');

    $data = request_data();
    $destinatarioId = (int) ($data['destinatario_id'] ?? 0);
    $mensagemTexto = trim((string) ($data['mensagem'] ?? ''));

    if ($destinatarioId <= 0) {
        json_response([
            'success' => false,
            'message' => 'Selecione o destinatário da mensagem.'
        ], 422);
    }

    if ($destinatarioId === $userId) {
        json_response([
            'success' => false,
            'message' => 'Você não pode enviar mensagem para si mesmo.'
        ], 422);
    }

    if ($mensagemTexto === '') {
        json_response([
            'success' => false,
            'message' => 'A mensagem não pode ser vazia.'
        ], 422);
    }

    // Limita tamanho para proteção
    if (mb_strlen($mensagemTexto) > 3000) {
        $mensagemTexto = mb_substr($mensagemTexto, 0, 3000);
    }

    try {
        // Valida se o destinatário existe
        $stmtVal = $pdo->prepare('SELECT id, nome, papel FROM usuarios WHERE id = :id LIMIT 1');
        $stmtVal->execute(['id' => $destinatarioId]);
        $destinatario = $stmtVal->fetch(PDO::FETCH_ASSOC);

        if (!$destinatario) {
            json_response([
                'success' => false,
                'message' => 'Destinatário não encontrado.'
            ], 404);
        }

        global $dbDriver;
        $lidaVal = ($dbDriver === 'pgsql') ? 'FALSE' : 0;

        // Insere a mensagem no banco
        $stmtIns = $pdo->prepare("
            INSERT INTO mensagens_chat (remetente_id, destinatario_id, mensagem, lida)
            VALUES (:remetente, :destinatario, :mensagem, {$lidaVal})
        ");
        $stmtIns->execute([
            'remetente'    => $userId,
            'destinatario' => $destinatarioId,
            'mensagem'     => $mensagemTexto
        ]);

        $novaMensagemId = db_last_insert_id($pdo, 'mensagens_chat', 'id');

        // Cria notificação interna para o sino do destinatário
        try {
            $meuNome = $currentUser['nome'] ?? 'Colega de Equipe';
            $trecho = (mb_strlen($mensagemTexto) > 40) ? (mb_substr($mensagemTexto, 0, 40) . '...') : $mensagemTexto;
            $msgNotif = "💬 Mensagem de {$meuNome}: \"{$trecho}\"";
            $linkDest = ($destinatario['papel'] === 'supervisor' || $destinatario['papel'] === 'gerente') 
                ? 'chat.html?contato=' . $userId 
                : 'chat.html?contato=' . $userId;

            $stmtNotif = $pdo->prepare("
                INSERT INTO notificacoes (usuario_id, mensagem, lida, link)
                VALUES (:uid, :msg, {$lidaVal}, :link)
            ");
            $stmtNotif->execute([
                'uid'  => $destinatarioId,
                'msg'  => $msgNotif,
                'link' => $linkDest
            ]);
        } catch (Exception $ignored) {}

        json_response([
            'success'     => true,
            'message'     => 'Mensagem enviada com sucesso!',
            'mensagem'    => [
                'id'              => $novaMensagemId,
                'remetente_id'    => $userId,
                'destinatario_id' => $destinatarioId,
                'remetente_nome'  => $currentUser['nome'] ?? '',
                'mensagem'        => $mensagemTexto,
                'lida'            => false,
                'criado_em'       => date('Y-m-d H:i:s'),
                'hora'            => date('H:i'),
                'data'            => date('Y-m-d'),
                'eh_meu'          => true
            ]
        ], 201);

    } catch (PDOException $e) {
        json_response([
            'success' => false,
            'message' => 'Erro ao enviar mensagem: ' . $e->getMessage()
        ], 500);
    }
}

// ====================================================================
// AÇÃO 4: Polling de Novas Mensagens em Tempo Real
// ====================================================================
if ($action === 'novas') {
    require_method('GET');

    $contatoId = (int) ($_GET['contato_id'] ?? 0);
    $ultimoId  = (int) ($_GET['ultimo_id'] ?? 0);

    try {
        global $dbDriver;
        $lidaTrue = ($dbDriver === 'pgsql') ? 'TRUE' : '1';

        $novasMensagens = [];

        if ($contatoId > 0) {
            // Busca novas mensagens entre este par após o último ID
            $stmtNovas = $pdo->prepare('
                SELECT m.id, m.remetente_id, m.destinatario_id, m.mensagem, m.lida, m.criado_em,
                       u.nome AS remetente_nome
                FROM mensagens_chat m
                JOIN usuarios u ON u.id = m.remetente_id
                WHERE m.id > :ultimo_id
                  AND (
                    (m.remetente_id = :meu_id AND m.destinatario_id = :contato_id)
                    OR 
                    (m.remetente_id = :contato_id AND m.destinatario_id = :meu_id)
                  )
                ORDER BY m.id ASC
            ');
            $stmtNovas->execute([
                'ultimo_id'  => $ultimoId,
                'meu_id'     => $userId,
                'contato_id' => $contatoId
            ]);
            $rows = $stmtNovas->fetchAll(PDO::FETCH_ASSOC);

            // Se recebeu mensagens do outro, marca imediatamente como lidas
            $idsParaMarcar = [];
            foreach ($rows as $r) {
                $ehMeu = ((int)$r['remetente_id'] === $userId);
                if (!$ehMeu) {
                    $idsParaMarcar[] = (int)$r['id'];
                }
                $novasMensagens[] = [
                    'id'             => (int) $r['id'],
                    'remetente_id'   => (int) $r['remetente_id'],
                    'destinatario_id'=> (int) $r['destinatario_id'],
                    'remetente_nome' => $r['remetente_nome'],
                    'mensagem'       => $r['mensagem'],
                    'lida'           => $ehMeu ? (bool)$r['lida'] : true,
                    'criado_em'      => $r['criado_em'],
                    'hora'           => substr((string)$r['criado_em'], 11, 5) ?: date('H:i'),
                    'eh_meu'         => $ehMeu
                ];
            }

            if (!empty($idsParaMarcar)) {
                $placeholders = implode(',', array_fill(0, count($idsParaMarcar), '?'));
                $stmtUpdate = $pdo->prepare("UPDATE mensagens_chat SET lida = {$lidaTrue} WHERE id IN ({$placeholders})");
                $stmtUpdate->execute($idsParaMarcar);
            }
        }

        // Total não lidas geral no sistema para atualizar badge do menu/sino
        $lidaFalse = ($dbDriver === 'pgsql') ? 'FALSE' : '0';
        $stmtGeral = $pdo->prepare("
            SELECT COUNT(*) 
            FROM mensagens_chat 
            WHERE destinatario_id = :meu_id 
              AND (lida = {$lidaFalse} OR lida IS NULL)
        ");
        $stmtGeral->execute(['meu_id' => $userId]);
        $totalNaoLidas = (int) $stmtGeral->fetchColumn();

        json_response([
            'success'          => true,
            'novas_mensagens'  => $novasMensagens,
            'total_nao_lidas'  => $totalNaoLidas
        ]);

    } catch (PDOException $e) {
        json_response([
            'success' => false,
            'message' => 'Erro ao verificar novas mensagens: ' . $e->getMessage()
        ], 500);
    }
}

// ====================================================================
// AÇÃO 5: Contagem Geral de Não Lidas (para Badge do Menu)
// ====================================================================
if ($action === 'unread_count') {
    require_method('GET');

    try {
        global $dbDriver;
        $lidaFalse = ($dbDriver === 'pgsql') ? 'FALSE' : '0';

        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM mensagens_chat 
            WHERE destinatario_id = :meu_id 
              AND (lida = {$lidaFalse} OR lida IS NULL)
        ");
        $stmt->execute(['meu_id' => $userId]);
        $total = (int) $stmt->fetchColumn();

        json_response([
            'success'         => true,
            'total_nao_lidas' => $total
        ]);
    } catch (PDOException $e) {
        json_response([
            'success' => false,
            'total_nao_lidas' => 0
        ]);
    }
}

// Ação desconhecida
json_response([
    'success' => false,
    'message' => 'Ação não informada ou inválida.'
], 400);
