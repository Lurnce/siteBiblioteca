<?php
require_once __DIR__ . '/conexao.php';

$id = (int)($_GET['id'] ?? 0);

$st = $pdo->prepare("SELECT l.*,
        (SELECT COUNT(*) FROM emprestimos e WHERE e.livro_id = l.id AND e.data_devolucao IS NULL) AS emprestados
        FROM livros l WHERE l.id = :id");
$st->execute([':id' => $id]);
$livro = $st->fetch();

if (!$livro) {
    flash('Livro não encontrado.', 'erro');
    redirecionar('index.php');
}

$dados = [
    'titulo'     => $livro['titulo'],
    'autor'      => $livro['autor'],
    'ano'        => $livro['ano'],
    'genero'     => $livro['genero'],
    'quantidade' => $livro['quantidade'],
];
$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    [$dados, $erros] = validar_livro($_POST);

    if (!$erros && $dados['quantidade'] < (int)$livro['emprestados']) {
        $erros[] = 'Há ' . (int)$livro['emprestados'] . ' exemplar(es) emprestado(s) agora. A quantidade não pode ser menor que isso.';
    }

    if (!$erros) {
        $up = $pdo->prepare("UPDATE livros
                             SET titulo = :titulo, autor = :autor, ano = :ano, genero = :genero, quantidade = :quantidade
                             WHERE id = :id");
        $up->execute([
            ':titulo'     => $dados['titulo'],
            ':autor'      => $dados['autor'],
            ':ano'        => $dados['ano'],
            ':genero'     => $dados['genero'],
            ':quantidade' => $dados['quantidade'],
            ':id'         => $id,
        ]);
        flash('Alterações salvas.');
        redirecionar('index.php');
    }
}

$generos = $pdo->query("SELECT DISTINCT genero FROM livros WHERE genero IS NOT NULL AND genero <> '' ORDER BY genero")
               ->fetchAll(PDO::FETCH_COLUMN);

$hist = $pdo->prepare("SELECT a.nome, a.turma, e.data_emprestimo, e.data_devolucao
                       FROM emprestimos e JOIN alunos a ON a.id = e.aluno_id
                       WHERE e.livro_id = :id
                       ORDER BY e.data_emprestimo DESC, e.id DESC LIMIT 5");
$hist->execute([':id' => $id]);
$historico = $hist->fetchAll();

$titulo_pagina = 'Editar livro';
$botao = 'Salvar alterações';
require_once __DIR__ . '/topo.php';
?>

<div class="pagina-topo">
  <div>
    <h1>Editar livro</h1>
    <p><?= (int)$livro['emprestados'] ?> exemplar(es) emprestado(s) neste momento.</p>
  </div>
</div>

<?php require_once __DIR__ . '/form_livro.php'; ?>

<div class="card card-sem-padding" style="margin-top:24px;max-width:720px">
  <div style="padding:20px 24px 0"><h2>Últimos empréstimos</h2></div>
  <div class="tabela-wrap">
    <table>
      <thead><tr><th>Aluno</th><th>Retirado em</th><th>Situação</th></tr></thead>
      <tbody>
      <?php if (!$historico): ?>
        <tr><td colspan="3" class="vazio">Este livro ainda não foi emprestado.</td></tr>
      <?php endif; ?>
      <?php foreach ($historico as $h): ?>
        <tr>
          <td><?= e($h['nome']) ?><span class="sub"><?= e($h['turma']) ?></span></td>
          <td><?= data_br($h['data_emprestimo']) ?></td>
          <td>
            <?php if ($h['data_devolucao'] === null): ?>
              <span class="badge badge-aviso">Em aberto</span>
            <?php else: ?>
              <span class="badge badge-ok">Devolvido em <?= data_br($h['data_devolucao']) ?></span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/rodape.php'; ?>
