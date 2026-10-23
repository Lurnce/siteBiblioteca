<?php
$arquivo = basename($_SERVER['SCRIPT_NAME']);
$menu = [
    ['dashboard.php',   'Painel',      ['dashboard.php']],
    ['index.php',       'Livros',      ['index.php', 'novo.php', 'editar.php']],
    ['alunos.php',      'Alunos',      ['alunos.php']],
    ['emprestimos.php', 'Empréstimos', ['emprestimos.php']],
];
$flash = flash_get();
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(($titulo_pagina ?? 'Biblioteca') . ' · Biblioteca da Escola') ?></title>
  <link rel="stylesheet" href="estilo.css">
</head>
<body>
<header class="topo">
  <div class="topo-int">
    <a class="marca" href="dashboard.php">
      <span class="marca-lombada" aria-hidden="true"></span>
      <span class="marca-texto">Biblioteca <em>da Escola</em></span>
    </a>
    <nav class="menu" aria-label="Principal">
      <?php foreach ($menu as [$href, $rotulo, $arquivos]): ?>
        <a href="<?= $href ?>"<?= in_array($arquivo, $arquivos, true) ? ' class="ativo" aria-current="page"' : '' ?>><?= $rotulo ?></a>
      <?php endforeach; ?>
    </nav>
  </div>
</header>

<main class="conteudo">
<?php if ($flash): ?>
  <div class="toast toast-<?= e($flash['tipo']) ?>" role="status">
    <span><?= e($flash['msg']) ?></span>
    <button type="button" class="toast-fechar" aria-label="Fechar aviso">×</button>
  </div>
<?php endif; ?>
