<?php

require 'vendor/autoload.php';

// Load environment variables from .env file
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$dbFile = $_ENV['DB_FILE'];

echo "Begin initializing Database... ($dbFile)" . PHP_EOL;

// Database connection
try {
    $pdo = new PDO("sqlite:$dbFile");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $sql = file_get_contents('./init.sql');
    $pdo->exec($sql);
} catch (PDOException $e) {
    die("Could not create the database: " . $e->getMessage());
}

echo "Database initialized." . PHP_EOL;
