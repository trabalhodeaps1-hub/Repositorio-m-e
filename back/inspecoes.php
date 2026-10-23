<?php
// Salva e consulta as inspeções realizadas
require_once __DIR__ . '/../config/bootstrap.php';

$userId = require_login();

// 1. Consulta das inspeções (GET)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare("
        SELECT i.*, 
               COALESCE(a.nome, 'Área não identificada') AS area_nome,
               COALESCE(e.nome, 'Equipamento não identificado') AS equipamento_nome,
               COALESCE(e.numero_patrimonio, 'S/N') AS numero_patrimonio
        FROM inspecoes i
        LEFT JOIN areas a ON a.id = i.area_id
        LEFT JOIN equipamentos e ON e.id = i.equipamento_id
        WHERE i.realizada_por = :id
        ORDER BY i.id DESC
    ");

    $stmt->execute(['id' => $userId]);

    json_response([
        'success' => true,
        'inspecoes' => $stmt->fetchAll()
    ]);
}

require_method('POST');

$data = request_data();

$realizadaEm = text_value($data, 'realizada_em', 50);
$areaId = filter_var($data['area_id'] ?? null, FILTER_VALIDATE_INT);
$equipamentoId = filter_var($data['equipamento_id'] ?? null, FILTER_VALIDATE_INT);
$perimetro = text_value($data, 'numero_perimetro', 100);
$localizacao = text_value($data, 'localizacao', 250);
$objetivo = text_value($data, 'objetivo', 3000);
$observacoes = text_value($data, 'observacoes', 5000);

// Tratamento flexível de data/hora (suporta datetime-local com ou sem segundos, ISO ou padrão 'now')
$dataHora = null;
if ($realizadaEm !== '') {
    $formatos = ['Y-m-d\TH:i', 'Y-m-d\TH:i:s', 'Y-m-d H:i:s', 'Y-m-d'];
    foreach ($formatos as $formato) {
        $dt = DateTime::createFromFormat($formato, $realizadaEm);
        if ($dt) {
            $dataHora = $dt;
            break;
        }
    }
    if (!$dataHora) {
        $ts = strtotime($realizadaEm);
        if ($ts !== false) {
            $dataHora = (new DateTime())->setTimestamp($ts);
        }
    }
}
if (!$dataHora) {
    $dataHora = new DateTime(); // Assume a data e hora atual caso não enviada
}

// Validação dos campos essenciais
if (!$areaId || !$equipamentoId || $perimetro === '' || $localizacao === '' || $objetivo === '') {
    json_response([
        'success' => false,
        'message' => 'Por favor, confira os campos obrigatórios da inspeção (Área, Equipamento, Perímetro, Localização e Objetivo).'
    ], 422);
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO inspecoes
        (realizada_em, area_id, equipamento_id, numero_perimetro, localizacao, objetivo, observacoes, realizada_por, status)
        VALUES
        (:data, :area, :equipamento, :perimetro, :local, :objetivo, :observacoes, :user, 'concluida')
    ");

    $stmt->execute([
        'data'        => $dataHora->format('Y-m-d H:i:s'),
        'area'        => $areaId,
        'equipamento' => $equipamentoId,
        'perimetro'   => $perimetro,
        'local'       => $localizacao,
        'objetivo'    => $objetivo,
        'observacoes' => $observacoes,
        'user'        => $userId
    ]);

    $inspecaoId = (int) $pdo->lastInsertId();

    json_response([
        'success'     => true,
        'message'     => 'Inspeção salva com sucesso! Agora você pode criar o relatório.',
        'inspecao_id' => $inspecaoId
    ], 201);

} catch (PDOException $e) {
    json_response([
        'success' => false,
        'message' => 'Não foi possível salvar a inspeção: ' . $e->getMessage()
    ], 500);
}
