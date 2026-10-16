<?php
// Dashboard e Métricas do Sistema
require_once __DIR__ . '/../config/bootstrap.php';

$userId = require_login();

$counts = [
    'problemas_identificados' => 0,
    'inspecoes_realizadas' => 0,
    'relatorios_aguardando_aprovacao' => 0,
    'relatorios_aprovados' => 0,
    'total_usuarios' => 0,
    'total_equipamentos' => 0,
    'total_areas' => 0
];

try {
    // 1. Problemas identificados (soma problemas relatados + relatórios cadastrados)
    $stmtProblemas = $pdo->query('SELECT COUNT(*) FROM problemas');
    $totalProblemas = (int) $stmtProblemas->fetchColumn();

    $stmtRelatorios = $pdo->query('SELECT COUNT(*) FROM relatorios');
    $totalRelatorios = (int) $stmtRelatorios->fetchColumn();

    $counts['problemas_identificados'] = $totalProblemas > 0 ? $totalProblemas : $totalRelatorios;

    // 2. Inspeções realizadas pelo usuário atual
    $stmtInspecoes = $pdo->prepare('SELECT COUNT(*) FROM inspecoes WHERE realizada_por = :id');
    $stmtInspecoes->execute(['id' => $userId]);
    $counts['inspecoes_realizadas'] = (int) $stmtInspecoes->fetchColumn();

    // 3. Relatórios aguardando aprovação
    $stmtAguardando = $pdo->query("SELECT COUNT(*) FROM relatorios WHERE status = 'aguardando_aprovacao'");
    $counts['relatorios_aguardando_aprovacao'] = (int) $stmtAguardando->fetchColumn();

    // 4. Relatórios aprovados
    $stmtAprovados = $pdo->query("SELECT COUNT(*) FROM relatorios WHERE status = 'aprovado'");
    $counts['relatorios_aprovados'] = (int) $stmtAprovados->fetchColumn();

    // 5. Métricas gerais adicionais para enriquecer o painel
    $counts['total_usuarios'] = (int) $pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
    $counts['total_equipamentos'] = (int) $pdo->query('SELECT COUNT(*) FROM equipamentos')->fetchColumn();
    $counts['total_areas'] = (int) $pdo->query('SELECT COUNT(*) FROM areas')->fetchColumn();

    json_response([
        'success' => true,
        'counts' => $counts
    ]);

} catch (PDOException $e) {
    json_response([
        'success' => false,
        'message' => 'Erro ao carregar dados do dashboard: ' . $e->getMessage(),
        'counts' => $counts
    ], 500);
}

    // 5. Métricas gerais adicionais para enriquecer o painel
    $counts['total_usuarios'] = (int) $pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
    $counts['total_equipamentos'] = (int) $pdo->query('SELECT COUNT(*) FROM equipamentos')->fetchColumn();
    $counts['total_areas'] = (int) $pdo->query('SELECT COUNT(*) FROM areas')->fetchColumn();

    json_response([
        'success' => true,
        'counts' => $counts
    ]);

} catch (PDOException $e) {
    json_response([
        'success' => false,
        'message' => 'Erro ao carregar dados do dashboard: ' . $e->getMessage(),
        'counts' => $counts
    ], 500);
}

