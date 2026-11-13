<?php
declare(strict_types=1);

/**
 * ====================================================================
 * CONFIGURAÇÃO DO BANCO DE DADOS LOCAL (POSTGRESQL PRIMÁRIO)
 * ====================================================================
 * Configurações para salvar todas as alterações diretamente no PostgreSQL
 * instalado no computador local (localhost:5432).
 */

return [
    // Driver preferencial: 'pgsql' (PostgreSQL) com fallback para 'sqlite' se o serviço estiver offline
    'driver' => getenv('DB_DRIVER') ?: 'pgsql',

    // Configurações do PostgreSQL Local
    'pgsql' => [
        'host'     => getenv('DB_HOST') ?: '127.0.0.1',
        'port'     => (int) (getenv('DB_PORT') ?: 5432),
        'database' => getenv('DB_NAME') ?: 'autorelatorio',
        'user'     => getenv('DB_USER') ?: 'postgres',
        'password' => getenv('DB_PASS') !== false ? getenv('DB_PASS') : 'jesus',
        
        // Senhas testadas automaticamente caso a padrão precise de ajuste
        'alternative_passwords' => [
            'postgres',
            '123456',
            'admin',
            'root',
            'password',
            ''
        ],
    ],

    // Armazenamento de contingência em arquivo SQLite local
    'sqlite' => [
        'path' => __DIR__ . '/../database/app.sqlite',
    ]
];
