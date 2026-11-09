<?php
declare(strict_types=1);

/**
 * ====================================================================
 * CONFIGURAÇÃO ESTRITAMENTE LOCAL DO BANCO DE DADOS (SEM NUVEM / SEM ONLINE)
 * ====================================================================
 * Todos os dados, relatórios e fotos são armazenados 100% no computador local:
 * 1. Primário: PostgreSQL Local (localhost:5432 - Banco: autorelatorio)
 * 2. Fallback de Contingência: SQLite Local (database/app.sqlite)
 * 3. Fotos e Anexos: Pasta física local (uploads/)
 * ====================================================================
 */

return [
    // Driver padrão local: PostgreSQL local (com fallback automático para SQLite se o serviço local estiver inativo)
    'driver' => 'pgsql',

    // Configurações exclusivas do PostgreSQL instalado neste computador
    'pgsql' => [
        'host'     => '127.0.0.1',
        'port'     => 5432,
        'database' => 'autorelatorio',
        'user'     => 'postgres',
        'password' => 'jesus',
        
        // Senhas locais comuns testadas automaticamente caso a senha padrão precise de ajuste
        'alternative_passwords' => [
            'postgres',
            '123456',
            'admin',
            'root',
            'password',
            ''
        ],
    ],

    // Armazenamento local em arquivo SQLite no disco rígido
    'sqlite' => [
        'path' => __DIR__ . '/../database/app.sqlite',
    ]
];
