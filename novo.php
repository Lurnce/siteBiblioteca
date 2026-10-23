<?php
require_once __DIR__ . '/conexao.php';

$dados = ['titulo' => '', 'autor' => '', 'ano' => null, 'genero' => '', 'quantidade' => 1];
$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    [$dados, $erros] = validar_livro($_POST);

    if (!$erros) {
        $st = $pdo->prepare("INSERT INTO livros (titulo, autor, ano, genero, quantidade)
                             VALUES (:titulo, :autor, :ano, :genero, :quantidade)");
        $st->execute([
            ':titulo'     => $dados['titulo'],
            ':autor'      => $dados['autor'],
            ':ano'        => $dados['ano'],
            ':genero'     => $dados['genero'],
            ':quantidade' => $dados['quantidade'],
        ]);
        flash('Livro cadastrado.');
        redirecionar('index.php');
    }
}

$generos = $pdo->query("SELECT DISTINCT genero FROM livros WHERE genero IS NOT NULL AND genero <> '' ORDER BY genero")
               ->fetchAll(PDO::FETCH_COLUMN);

$titulo_pagina = 'Cadastrar livro';
$botao = 'Cadastrar livro';
require_once __DIR__ . '/topo.php';
?>

<div class="pagina-topo">
  <div>
    <h1>Cadastrar livro</h1>
    <p>Preencha os dados do título e quantos exemplares a escola possui.</p>
  </div>
</div>

<?php require_once __DIR__ . '/form_livro.php'; ?>

<?php require_once __DIR__ . '/rodape.php'; ?>
