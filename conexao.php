<?php
session_start();
date_default_timezone_set('America/Sao_Paulo');

$host    = 'localhost';
$db      = 'biblioteca';
$user    = 'root';
$pass    = '';
$charset = 'utf8mb4';

// Regras da biblioteca (altere aqui)
define('PRAZO_DIAS', 14);          // dias para devolver o livro
define('LIMITE_EMPRESTIMOS', 3);   // máximo de livros em aberto por aluno

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die("Erro na conexão com o banco de dados: " . $e->getMessage());
}

require_once __DIR__ . '/funcoes.php';
