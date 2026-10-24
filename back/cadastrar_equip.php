<?php
// Cadastro de equipamentos
require_once __DIR__ . '/../config/bootstrap.php';

$userId = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->query(
        'SELECT e.id, e.numero_patrimonio, e.nome,
                e.area_id, a.nome AS area_nome,
                e.localizacao, e.status_operacional
         FROM equipamentos e
         LEFT JOIN areas a ON a.id = e.area_id
         ORDER BY e.nome'
    );

    json_response([
        'success' => true,
        'equipamentos' => $stmt->fetchAll()
    ]);
}

require_method('POST');

// Somente Supervisor e Gerente podem cadastrar equipamentos.
require_any_role($pdo, ['supervisor', 'gerente']);

$data = request_data();

$patrimonio = text_value($data, 'numero_patrimonio', 80);
$nome = text_value($data, 'nome', 160);
$localizacao = text_value($data, 'localizacao', 250);
$areaId = filter_var(
    $data['area_id'] ?? null,
    FILTER_VALIDATE_INT
);
$status = filter_var(
    $data['status_operacional'] ?? null,
    FILTER_VALIDATE_BOOLEAN,
    FILTER_NULL_ON_FAILURE
);

if (
    !$patrimonio ||
    !$nome ||
    !$localizacao ||
    !$areaId ||
    $status === null
) {
    json_response([
        'success' => false,
        'message' => 'Preencha todos os campos do equipamento.'
    ], 422);
}

try {
    $stmt = $pdo->prepare(
        'INSERT INTO equipamentos
        (numero_patrimonio, nome, area_id, localizacao, status_operacional, criado_por)
         VALUES
        (:patrimonio, :nome, :area, :local, :status, :user)'
    );

    $statusParam = (($dbDriver ?? '') === 'pgsql') ? ($status ? 'true' : 'false') : ($status ? 1 : 0);

    $stmt->execute([
        'patrimonio' => $patrimonio,
        'nome' => $nome,
        'area' => $areaId,
        'local' => $localizacao,
        'status' => $statusParam,
        'user' => $userId
    ]);

    json_response([
        'success' => true,
        'message' => 'Equipamento cadastrado!',
        'id' => db_last_insert_id($pdo, 'equipamentos')
    ], 201);

} catch (PDOException $e) {
    if (in_array($e->getCode(), ['23000', '23505', '19'], true) || strpos($e->getMessage(), 'UNIQUE') !== false) {
        json_response([
            'success' => false,
            'message' => 'Esse número de patrimônio já existe.'
        ], 409);
    }

    json_response([
        'success' => false,
        'message' => 'Não foi possível cadastrar o equipamento: ' . $e->getMessage()
    ], 500);
}
