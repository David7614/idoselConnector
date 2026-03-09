<?php

if ($url = getenv('STACKHERO_MYSQL_DATABASE_URL')) {
    $parsed = parse_url($url);
    $host   = $parsed['host'];
    $port   = $parsed['port'] ?? 3306;
    $user   = $parsed['user'] ?? 'root';
    $pass   = isset($parsed['pass']) ? urldecode($parsed['pass']) : '';
    $dbName = getenv('DB_NAME') ?: 'samba_idosell';

    return [
        'class'    => 'yii\db\Connection',
        'dsn'      => "mysql:host={$host};port={$port};dbname={$dbName}",
        'username' => $user,
        'password' => $pass,
        'charset'  => 'utf8mb4',
    ];
}

return [
    'class'    => 'yii\db\Connection',
    'dsn'      => 'mysql:host=localhost;dbname=samba_idosell',
    'username' => 'root',
    'password' => 'ABCabc123',
    'charset'  => 'utf8',
];
