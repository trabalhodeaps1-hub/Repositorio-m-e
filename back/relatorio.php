<?php
// Consulta, Criação, Aprovação de Resolução e Exclusão de Relatórios Técnicos
require_once __DIR__ . '/../config/bootstrap.php';

$userId = require_login();

// 1. CONSULTA DE RELATÓRIOS (GET)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $sql = '
            SELECT r.*, 
                   COALESCE(i.realizada_em, r.criado_em) AS realizada_em,
                   COALESCE(i.numero_perimetro, "N/A") AS numero_perimetro,
                   COALESCE(e.nome, "Equipamento Geral") AS equipamento_nome,
                   COALESCE(a.nome, "Área Geral") AS area_nome,
                   COALESCE(u.nome, "Usuário do Sistema") AS autor_nome
            FROM relatorios r
            LEFT JOIN inspecoes i ON i.id = r.inspecao_id
            LEFT JOIN equipamentos e ON e.id = i.equipamento_id
            LEFT JOIN areas a ON a.id = i.area_id
            LEFT JOIN usuarios u ON u.id = r.criado_por
            ORDER BY r.id DESC
        ';

        $stmt = $pdo->query($sql);

        json_response([
            'success'    => true,
            'relatorios' => $stmt->fetchAll()
        ]);
    } catch (PDOException $e) {
        json_response([
            'success' => false,
            'message' => 'Erro ao carregar relatórios: ' . $e->getMessage()
        ], 500);
    }
}

$data = request_data();
$action = strtolower(trim((string)($data['action'] ?? $_GET['action'] ?? '')));

// 2. EXCLUSÃO DE REGISTRO (DELETE ou POST com action=excluir)
if ($_SERVER['REQUEST_METHOD'] === 'DELETE' || $action === 'excluir' || $action === 'deletar') {
    $idRaw = $data['id'] ?? $data['relatorio_id'] ?? $_GET['id'] ?? null;
    $id = is_numeric($idRaw) ? (int)$idRaw : null;
    $inspecaoIdRaw = $data['inspecao_id'] ?? null;
    $inspecaoIdParam = is_numeric($inspecaoIdRaw) ? (int)$inspecaoIdRaw : null;

    if (!$id && !$inspecaoIdParam) {
        json_response([
            'success' => false,
            'message' => 'ID não informado para exclusão.'
        ], 422);
    }

    try {
        if ($id) {
            $stmtBusca = $pdo->prepare('SELECT status, inspecao_id FROM relatorios WHERE id = :id');
            $stmtBusca->execute(['id' => $id]);
            $relatorioExistente = $stmtBusca->fetch();

            if ($relatorioExistente && strtolower((string)$relatorioExistente['status']) === 'aprovado') {
                json_response([
                    'success' => false,
                    'message' => 'Não é permitido excluir relatórios que já foram aprovados. Apenas pendentes ou em elaboração podem ser excluídos.'
                ], 403);
            }

            $inspecaoId = ($relatorioExistente['inspecao_id'] ?? null) ?: $inspecaoIdParam;

            $stmtDel = $pdo->prepare('DELETE FROM relatorios WHERE id = :id');
            $stmtDel->execute(['id' => $id]);

            if ($inspecaoId) {
                $stmtDelInsp = $pdo->prepare('DELETE FROM inspecoes WHERE id = :id');
                $stmtDelInsp->execute(['id' => $inspecaoId]);
            }
        } elseif ($inspecaoIdParam) {
            $stmtCheca = $pdo->prepare('SELECT status FROM relatorios WHERE inspecao_id = :id');
            $stmtCheca->execute(['id' => $inspecaoIdParam]);
            while ($row = $stmtCheca->fetch()) {
                if (strtolower((string)$row['status']) === 'aprovado') {
                    json_response([
                        'success' => false,
                        'message' => 'Não é permitido excluir relatórios que já foram aprovados.'
                    ], 403);
                }
            }

            $stmtDel = $pdo->prepare('DELETE FROM relatorios WHERE inspecao_id = :id');
            $stmtDel->execute(['id' => $inspecaoIdParam]);

            $stmtDelInsp = $pdo->prepare('DELETE FROM inspecoes WHERE id = :id');
            $stmtDelInsp->execute(['id' => $inspecaoIdParam]);
        }

        json_response([
            'success' => true,
            'message' => 'Registro excluído com sucesso!'
        ]);
    } catch (PDOException $e) {
        json_response([
            'success' => false,
            'message' => 'Erro ao excluir o registro: ' . $e->getMessage()
        ], 500);
    }
}

// 3. APROVAÇÃO E RESOLUÇÃO DA PENDÊNCIA
if ($action === 'aprovar_resolucao' || $action === 'aprovar') {
    // Permissão: apenas Supervisor e Gerente podem aprovar relatórios
    $currentUser = current_user($pdo);
    $cargoUser = strtolower(trim((string)($currentUser['cargo'] ?? $currentUser['papel'] ?? $_SESSION['user_cargo'] ?? $_SESSION['user_papel'] ?? '')));
    $podeAprovar = (strpos($cargoUser, 'supervisor') !== false || strpos($cargoUser, 'gerente') !== false);

    if (!$podeAprovar) {
        json_response([
            'success' => false,
            'message' => 'Permissão negada. Apenas Supervisores e Gerentes podem aprovar relatórios e resolver pendências.'
        ], 403);
    }

    $id = filter_var($data['id'] ?? $data['relatorio_id'] ?? null, FILTER_VALIDATE_INT);
    $oQueFoiFeito = text_value($data, 'o_que_foi_feito', 5000);
    if ($oQueFoiFeito === '') {
        $oQueFoiFeito = text_value($data, 'oque_fez', 5000);
    }
    $comoFoiFeito = text_value($data, 'como_foi_feito', 5000);
    if ($comoFoiFeito === '') {
        $comoFoiFeito = text_value($data, 'como_fez', 5000);
    }
    $fotoComprovante = text_value($data, 'foto_comprovante', 15000000) ?: text_value($data, 'foto', 15000000);

    if (!$id) {
        json_response([
            'success' => false,
            'message' => 'Identificador do relatório não informado.'
        ], 422);
    }

    if ($oQueFoiFeito === '' || $comoFoiFeito === '') {
        json_response([
            'success' => false,
            'message' => 'Por favor, descreva o que foi feito e como foi realizado o procedimento para aprovar a resolução.'
        ], 422);
    }

    try {
        $stmt = $pdo->prepare('
            UPDATE relatorios
            SET status = "aprovado",
                o_que_foi_feito = :oque,
                como_foi_feito = :como,
                foto_comprovante = :foto,
                aprovado_em = CURRENT_TIMESTAMP,
                aprovado_por = :user
            WHERE id = :id
        ');

        $stmt->execute([
            'oque' => $oQueFoiFeito,
            'como' => $comoFoiFeito,
            'foto' => $fotoComprovante ?: null,
            'user' => $userId,
            'id'   => $id
        ]);

        // Atualiza a inspeção correspondente para concluída
        $stmtInsp = $pdo->prepare('
            UPDATE inspecoes 
            SET status = "concluida" 
            WHERE id = (SELECT inspecao_id FROM relatorios WHERE id = :id)
        ');
        $stmtInsp->execute(['id' => $id]);

        json_response([
            'success' => true,
            'message' => 'Pendência resolvida e relatório aprovado com sucesso!'
        ]);
    } catch (PDOException $e) {
        json_response([
            'success' => false,
            'message' => 'Erro ao aprovar resolução no banco de dados: ' . $e->getMessage()
        ], 500);
    }
}

// 3.5. EDIÇÃO DE RELATÓRIO TÉCNICO (Permitido antes de ser aprovado)
if ($action === 'editar' || $action === 'atualizar') {
    $id = filter_var($data['id'] ?? $data['relatorio_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$id) {
        json_response([
            'success' => false,
            'message' => 'Identificador do relatório não informado.'
        ], 422);
    }

    try {
        $stmtCheca = $pdo->prepare('SELECT status FROM relatorios WHERE id = :id');
        $stmtCheca->execute(['id' => $id]);
        $relExistente = $stmtCheca->fetch();

        if ($relExistente && strtolower((string)$relExistente['status']) === 'aprovado') {
            json_response([
                'success' => false,
                'message' => 'Relatórios já aprovados não podem ser editados.'
            ], 403);
        }

        $resumo = text_value($data, 'resumo', 5000) ?: text_value($data, 'descricao_tecnica', 5000);
        $recomendacoes = text_value($data, 'recomendacoes', 5000);

        $stmtUp = $pdo->prepare('
            UPDATE relatorios 
            SET descricao_tecnica = COALESCE(:descricao, descricao_tecnica),
                recomendacoes = COALESCE(:rec, recomendacoes)
            WHERE id = :id AND status != "aprovado"
        ');
        $stmtUp->execute([
            'descricao' => $resumo !== '' ? $resumo : null,
            'rec'       => $recomendacoes !== '' ? $recomendacoes : null,
            'id'        => $id
        ]);

        json_response([
            'success' => true,
            'message' => 'Relatório atualizado com sucesso!'
        ]);
    } catch (PDOException $e) {
        json_response([
            'success' => false,
            'message' => 'Erro ao atualizar relatório: ' . $e->getMessage()
        ], 500);
    }
}

// 4. CRIAÇÃO DE NOVO RELATÓRIO TÉCNICO
require_method('POST');

$inspecaoId = filter_var($data['inspecao_id'] ?? null, FILTER_VALIDATE_INT);
$titulo = text_value($data, 'titulo_item', 180) ?: text_value($data, 'titulo', 180);
$local = text_value($data, 'local_exato', 250) ?: (text_value($data, 'local', 250) ?: text_value($data, 'localizacao', 250));
$descricao = text_value($data, 'descricao_tecnica', 5000) ?: (text_value($data, 'descricao', 5000) ?: text_value($data, 'informacao_problema', 5000));
$criticidade = strtolower(text_value($data, 'criticidade', 30)) ?: 'media';
$prioridade  = strtolower(text_value($data, 'prioridade', 30)) ?: 'media';
$recomendacoes = text_value($data, 'recomendacoes', 5000) ?: 'Avaliação técnica recomendada.';
$foto = text_value($data, 'fotografia', 500) ?: text_value($data, 'foto', 500);

if ($titulo === '' || $descricao === '') {
    json_response([
        'success' => false,
        'message' => 'Por favor, informe pelo menos o título do item e a descrição técnica.'
    ], 422);
}

if (!in_array($criticidade, ['baixa', 'media', 'alta', 'critica'], true)) {
    $criticidade = 'media';
}

if (!in_array($prioridade, ['baixa', 'media', 'alta', 'urgente'], true)) {
    $prioridade = 'media';
}

try {
    if (!$inspecaoId) {
        $stmtUltima = $pdo->prepare('SELECT id FROM inspecoes WHERE realizada_por = :user ORDER BY id DESC LIMIT 1');
        $stmtUltima->execute(['user' => $userId]);
        $inspecaoId = (int) $stmtUltima->fetchColumn() ?: null;
    }

    $stmt = $pdo->prepare('
        INSERT INTO relatorios
        (inspecao_id, titulo_item, local_exato, descricao_tecnica,
         criticidade, prioridade, recomendacoes, fotografia, status, criado_por)
        VALUES
        (:inspecao, :titulo, :local, :descricao,
         :criticidade, :prioridade, :recomendacoes, :foto, "aguardando_aprovacao", :user)
    ');

    $stmt->execute([
        'inspecao'      => $inspecaoId,
        'titulo'        => $titulo,
        'local'         => $local ?: 'Local padrão',
        'descricao'     => $descricao,
        'criticidade'   => $criticidade,
        'prioridade'    => $prioridade,
        'recomendacoes' => $recomendacoes,
        'foto'          => $foto ?: null,
        'user'          => $userId
    ]);

    $relatorioId = (int) $pdo->lastInsertId();

    json_response([
        'success'      => true,
        'message'      => 'Relatório criado e enviado para aprovação com sucesso!',
        'relatorio_id' => $relatorioId,
        'id'           => $relatorioId
    ], 201);

} catch (PDOException $e) {
    json_response([
        'success' => false,
        'message' => 'Erro ao salvar o relatório no banco de dados: ' . $e->getMessage()
    ], 500);
}