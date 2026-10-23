<?php
require_once __DIR__ . '/conexao.php';

$busca     = trim($_GET['busca'] ?? '');
$genero    = trim($_GET['genero'] ?? '');
$disp      = $_GET['disp'] ?? '';
$ordem     = $_GET['ordem'] ?? 'titulo';
$porPagina = 10;

$ordens = [
    'titulo'   => 'l.titulo',
    'autor'    => 'l.autor, l.titulo',
    'ano'      => 'l.ano DESC, l.titulo',
    'recentes' => 'l.id DESC',
];
$orderBy = $ordens[$ordem] ?? $ordens['titulo'];

$where  = [];
$params = [];
if ($busca !== '') {
    $where[] = '(l.titulo LIKE :b1 OR l.autor LIKE :b2)';
    $params[':b1'] = $params[':b2'] = "%$busca%";
}
if ($genero !== '') {
    $where[] = 'l.genero = :g';
    $params[':g'] = $genero;
}

$base = "SELECT l.id, l.titulo, l.autor, l.ano, l.genero, l.quantidade, COUNT(e.id) AS emprestados
         FROM livros l
         LEFT JOIN emprestimos e ON e.livro_id = l.id AND e.data_devolucao IS NULL"
      . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
      . ' GROUP BY l.id';
if ($disp === 'disponiveis') {
    $base .= ' HAVING (quantidade - emprestados) > 0';
} elseif ($disp === 'esgotados') {
    $base .= ' HAVING (quantidade - emprestados) <= 0';
}

$st = $pdo->prepare("SELECT COUNT(*) FROM ($base) t");
$st->execute($params);
$total = (int)$st->fetchColumn();

$totalPaginas = max(1, (int)ceil($total / $porPagina));
$pagina = min(max(1, (int)($_GET['p'] ?? 1)), $totalPaginas);
$offset = ($pagina - 1) * $porPagina;

$st = $pdo->prepare("$base ORDER BY $orderBy LIMIT $porPagina OFFSET $offset");
$st->execute($params);
$livros = $st->fetchAll();

$generos = $pdo->query("SELECT DISTINCT genero FROM livros WHERE genero IS NOT NULL AND genero <> '' ORDER BY genero")
               ->fetchAll(PDO::FETCH_COLUMN);

$filtrando = $busca !== '' || $genero !== '' || $disp !== '' || $ordem !== 'titulo';

$titulo_pagina = 'Livros';
require_once __DIR__ . '/topo.php';
?>

<div class="pagina-topo">
  <div>
    <h1>Livros</h1>
    <p>Acervo da biblioteca e disponibilidade de cada título.</p>
  </div>
  <a class="btn btn-primario" href="novo.php">Cadastrar livro</a>
</div>

<form method="get" class="filtros">
  <input class="busca" type="search" name="busca" value="<?= e($busca) ?>" placeholder="Buscar por título ou autor" aria-label="Buscar livros">
  <select name="genero" aria-label="Gênero">
    <option value="">Todos os gêneros</option>
    <?php foreach ($generos as $g): ?>
      <option value="<?= e($g) ?>"<?= $g === $genero ? ' selected' : '' ?>><?= e($g) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="disp" aria-label="Disponibilidade">
    <option value="">Qualquer disponibilidade</option>
    <option value="disponiveis"<?= $disp === 'disponiveis' ? ' selected' : '' ?>>Com exemplares livres</option>
    <option value="esgotados"<?= $disp === 'esgotados' ? ' selected' : '' ?>>Todos emprestados</option>
  </select>
  <select name="ordem" aria-label="Ordenar por">
    <option value="titulo"<?= $ordem === 'titulo' ? ' selected' : '' ?>>Ordem: título</option>
    <option value="autor"<?= $ordem === 'autor' ? ' selected' : '' ?>>Ordem: autor</option>
    <option value="ano"<?= $ordem === 'ano' ? ' selected' : '' ?>>Ordem: mais recentes (ano)</option>
    <option value="recentes"<?= $ordem === 'recentes' ? ' selected' : '' ?>>Ordem: últimos cadastrados</option>
  </select>
  <button class="btn btn-primario">Filtrar</button>
  <?php if ($filtrando): ?><a class="btn btn-secundario" href="index.php">Limpar</a><?php endif; ?>
</form>

<div class="card card-sem-padding">
  <div class="tabela-wrap">
    <table>
      <thead>
        <tr>
          <th>Título</th>
          <th>Ano</th>
          <th>Gênero</th>
          <th>Disponibilidade</th>
          <th class="direita">Ações</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$livros): ?>
        <tr><td colspan="5" class="vazio">
          <strong><?= $filtrando ? 'Nenhum livro encontrado' : 'Nenhum livro cadastrado' ?></strong>
          <?= $filtrando ? 'Tente outros termos ou limpe os filtros.' : 'Cadastre o primeiro livro para começar.' ?>
        </td></tr>
      <?php endif; ?>
      <?php foreach ($livros as $l):
          $livres = max(0, (int)$l['quantidade'] - (int)$l['emprestados']); ?>
        <tr>
          <td><strong><?= e($l['titulo']) ?></strong><span class="sub"><?= e($l['autor']) ?></span></td>
          <td><?= e($l['ano']) ?: '—' ?></td>
          <td><?= $l['genero'] !== null && $l['genero'] !== '' ? '<span class="badge badge-neutro">' . e($l['genero']) . '</span>' : '—' ?></td>
          <td>
            <?php if ($livres > 0): ?>
              <span class="badge badge-ok"><?= $livres ?> de <?= (int)$l['quantidade'] ?> livres</span>
            <?php else: ?>
              <span class="badge badge-erro">Todos emprestados</span>
            <?php endif; ?>
          </td>
          <td>
            <div class="acoes">
              <a class="btn btn-secundario btn-sm" href="editar.php?id=<?= (int)$l['id'] ?>">Editar</a>
              <form method="post" action="excluir.php" data-confirm="<?= e('Excluir o livro "' . $l['titulo'] . '"?') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
                <button class="btn btn-perigo btn-sm">Excluir</button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="rodape-tabela">
    <span><?= $total ?> livro(s) encontrado(s)</span>
    <?= paginacao($total, $pagina, $porPagina) ?>
  </div>
</div>

<?php require_once __DIR__ . '/rodape.php'; ?>
