<?php

if ($url = getenv('STACKHERO_MYSQL_DATABASE_URL')) {
    $parsed = parse_url($url);
    $host   = $parsed['host'];
    $port   = $parsed['port'] ?? 3306;
    $user   = $parsed['user'] ?? 'root';
    $pass   = isset($parsed['pass']) ? urldecode($parsed['pass']) : '';
    $dbName = getenv('DB_NAME') ?: ltrim($parsed['path'] ?? '', '/') ?: 'samba_idosell';

    parse_str($parsed['query'] ?? '', $query);
    $useSSL = !empty($query['useSSL']) && $query['useSSL'] === 'true';

    $config = [
        'class'    => 'yii\db\Connection',
        'dsn'      => "mysql:host={$host};port={$port};dbname={$dbName}",
        'username' => $user,
        'password' => $pass,
        'charset'  => 'utf8mb4',
    ];

    if ($useSSL) {
        $config['attributes'] = [
            PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
        ];
    }

    return $config;
}

return [
    'class'    => 'yii\db\Connection',
    'dsn'      => 'mysql:host=localhost;dbname=samba_idosell',
    'username' => 'root',
    'password' => 'ABCabc123',
    'charset'  => 'utf8',
];
