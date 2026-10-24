<?php
// Cadastro das áreas industriais
require_once __DIR__ . '/../config/bootstrap.php';

$userId = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->query(
        'SELECT id, nome, descricao, icone, criado_em
         FROM areas
         ORDER BY nome'
    );

    json_response([
        'success' => true,
        'areas' => $stmt->fetchAll()
    ]);
}

require_method('POST');

$data = request_data();

$nome = text_value($data, 'nome', 120);
$descricao = text_value($data, 'descricao', 3000);
$icone = text_value($data, 'icone', 80) ?: 'factory';

if ($nome === '') {
    json_response([
        'success' => false,
        'message' => 'O nome da área é obrigatório.'
    ], 422);
}

try {
    $stmt = $pdo->prepare(
        'INSERT INTO areas (nome, descricao, icone, criado_por)
         VALUES (:nome, :descricao, :icone, :user)'
    );

    $stmt->execute([
        'nome' => $nome,
        'descricao' => $descricao,
        'icone' => $icone,
        'user' => $userId
    ]);

    json_response([
        'success' => true,
        'message' => 'Área cadastrada com sucesso!',
        'id' => db_last_insert_id($pdo, 'areas')
    ], 201);

} catch (PDOException $e) {
    if (in_array($e->getCode(), ['23000', '23505', '19'], true) || strpos($e->getMessage(), 'UNIQUE') !== false) {
        json_response([
            'success' => false,
            'message' => 'Já existe uma área com esse nome.'
        ], 409);
    }

    json_response([
        'success' => false,
        'message' => 'Não foi possível cadastrar a área: ' . $e->getMessage()
    ], 500);
}