<?php
$config = [
    'host' => 'localhost',
    'banco' => 'lanzy_reservas',
    'usuario' => 'root',
    'senha' => '',
    'mostrar_erros' => false,
];

// A senha do MySQL de cada computador fica em config/local.php (veja local.exemplo.php)
if (file_exists(__DIR__ . '/local.php')) {
    $config = array_merge($config, require __DIR__ . '/local.php');
}

ini_set('display_errors', $config['mostrar_erros'] ? '1' : '0');
ini_set('log_errors', '1');

try {
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['banco']};charset=utf8mb4",
        $config['usuario'],
        $config['senha'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Modo estrito: dado inválido vira erro em vez de ser cortado em silêncio
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'",
        ]
    );
} catch (PDOException $e) {
    error_log('Lanzy: falha ao conectar ao banco: ' . $e->getMessage());
    http_response_code(500);
    paginaDeAviso('Sistema fora do ar', 'Não conseguimos acessar o banco de dados. Tente novamente em alguns minutos.');
    exit;
}
