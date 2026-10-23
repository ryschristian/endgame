<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$config = config('database.connections.mysql');
$pdo = new PDO("mysql:host={$config['host']};port={$config['port']}", $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
echo 'Server: '.$pdo->query('SELECT VERSION()')->fetchColumn().PHP_EOL;
echo 'Application schema exists: '.($pdo->query("SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = 'endgame'")->fetchColumn() ? 'yes' : 'no').PHP_EOL;
