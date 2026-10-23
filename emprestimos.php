<?php
require_once __DIR__ . '/conexao.php';

$vencimento = 'DATE_ADD(e.data_emprestimo, INTERVAL ' . (int)PRAZO_DIAS . ' DAY)';

// ---------- Ações (POST) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $acao = $_POST['acao'] ?? '';
    $destino = 'emprestimos.php';

    if ($acao === 'devolver') {
        $st = $pdo->prepare("UPDATE emprestimos SET data_devolucao = CURDATE()
                             WHERE id = :id AND data_devolucao IS NULL");
        $st->execute([':id' => (int)($_POST['id'] ?? 0)]);
        if ($st->rowCount()) {
            flash('Devolução registrada.');
        } else {
            flash('Este empréstimo já estava devolvido.', 'erro');
        }
        $volta = $_POST['volta'] ?? '';
        if (is_string($volta) && preg_match('/^\?[\w=&%.+\-]*$/', $volta)) {
            $destino .= $volta;
        }
    } elseif ($acao === 'emprestar') {
        $livroId = (int)($_POST['livro_id'] ?? 0);
        $alunoId = (int)($_POST['aluno_id'] ?? 0);

        try {
            $pdo->beginTransaction();

            $st = $pdo->prepare("SELECT quantidade FROM livros WHERE id = :id FOR UPDATE");
            $st->execute([':id' => $livroId]);
            $quantidade = $st->fetchColumn();
            if ($quantidade === false) {
                throw new RuntimeException('Livro não encontrado.');
            }

            $st = $pdo->prepare("SELECT COUNT(*) FROM alunos WHERE id = :id");
            $st->execute([':id' => $alunoId]);
            if ((int)$st->fetchColumn() === 0) {
                throw new RuntimeException('Aluno não encontrado.');
            }

            $st = $pdo->prepare("SELECT COUNT(*) FROM emprestimos WHERE livro_id = :id AND data_devolucao IS NULL");
            $st->execute([':id' => $livroId]);
            if ((int)$quantidade - (int)$st->fetchColumn() <= 0) {
                throw new RuntimeException('Não há exemplares livres deste livro no momento.');
            }

            $st = $pdo->prepare("SELECT COUNT(*) FROM emprestimos
                                 WHERE aluno_id = :a AND livro_id = :l AND data_devolucao IS NULL");
            $st->execute([':a' => $alunoId, ':l' => $livroId]);
            if ((int)$st->fetchColumn() > 0) {
                throw new RuntimeException('Este aluno já está com um exemplar deste livro.');
            }

            $st = $pdo->prepare("SELECT COUNT(*) FROM emprestimos WHERE aluno_id = :a AND data_devolucao IS NULL");
            $st->execute([':a' => $alunoId]);
            if ((int)$st->fetchColumn() >= LIMITE_EMPRESTIMOS) {
                throw new RuntimeException('O aluno já tem ' . LIMITE_EMPRESTIMOS . ' livros em aberto (limite da biblioteca).');
            }

            $st = $pdo->prepare("INSERT INTO emprestimos (livro_id, aluno_id, data_emprestimo)
                                 VALUES (:livro, :aluno, CURDATE())");
            $st->execute([':livro' => $livroId, ':aluno' => $alunoId]);

            $pdo->commit();
            flash('Empréstimo registrado. Prazo de devolução: ' . PRAZO_DIAS . ' dias.');
        } catch (RuntimeException $ex) {
            $pdo->rollBack();
            flash($ex->getMessage(), 'erro');
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('Não foi possível registrar o empréstimo. Tente novamente.', 'erro');
        }
    }

    redirecionar($destino);
}

// ---------- Filtros ----------
$status    = $_GET['status'] ?? '';
$busca     = trim($_GET['busca'] ?? '');
$porPagina = 15;

$where  = [];
$params = [];
if ($status === 'abertos') {
    $where[] = 'e.data_devolucao IS NULL';
} elseif ($status === 'atrasados') {
    $where[] = "e.data_devolucao IS NULL AND $vencimento < CURDATE()";
} elseif ($status === 'devolvidos') {
    $where[] = 'e.data_devolucao IS NOT NULL';
} else {
    $status = '';
}
if ($busca !== '') {
    $where[] = '(a.nome LIKE :b1 OR a.turma LIKE :b2 OR l.titulo LIKE :b3)';
    $params[':b1'] = $params[':b2'] = $params[':b3'] = "%$busca%";
}
$sqlWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$from = " FROM emprestimos e
          JOIN alunos a ON a.id = e.aluno_id
          JOIN livros l ON l.id = e.livro_id";

$st = $pdo->prepare("SELECT COUNT(*)" . $from . $sqlWhere);
$st->execute($params);
$total = (int)$st->fetchColumn();

$totalPaginas = max(1, (int)ceil($total / $porPagina));
$pagina = min(max(1, (int)($_GET['p'] ?? 1)), $totalPaginas);
$offset = ($pagina - 1) * $porPagina;

$st = $pdo->prepare("SELECT e.id, a.nome, a.turma, l.titulo, e.data_emprestimo, e.data_devolucao,
                            $vencimento AS vencimento,
                            DATEDIFF(CURDATE(), $vencimento) AS dias_atraso"
    . $from . $sqlWhere . "
    ORDER BY (e.data_devolucao IS NOT NULL),
             CASE WHEN e.data_devolucao IS NULL THEN e.data_emprestimo END ASC,
             e.data_emprestimo DESC, e.id DESC
    LIMIT $porPagina OFFSET $offset");
$st->execute($params);
$emprestimos = $st->fetchAll();

$c = $pdo->query("SELECT COUNT(*) AS todos,
        COALESCE(SUM(e.data_devolucao IS NULL), 0) AS abertos,
        COALESCE(SUM(e.data_devolucao IS NULL AND $vencimento < CURDATE()), 0) AS atrasados,
        COALESCE(SUM(e.data_devolucao IS NOT NULL), 0) AS devolvidos
        FROM emprestimos e")->fetch();

$alunos = $pdo->query("SELECT id, nome, turma FROM alunos ORDER BY nome")->fetchAll();
$livros = $pdo->query("SELECT l.id, l.titulo, l.quantidade - COUNT(e.id) AS livres
                       FROM livros l
                       LEFT JOIN emprestimos e ON e.livro_id = l.id AND e.data_devolucao IS NULL
                       GROUP BY l.id ORDER BY l.titulo")->fetchAll();

$abas = [
    ''           => ['Todos',      $c['todos']],
    'abertos'    => ['Em aberto',  $c['abertos']],
    'atrasados'  => ['Atrasados',  $c['atrasados']],
    'devolvidos' => ['Devolvidos', $c['devolvidos']],
];

$titulo_pagina = 'Empréstimos';
require_once __DIR__ . '/topo.php';
?>

<div class="pagina-topo">
  <div>
    <h1>Empréstimos</h1>
    <p>Prazo de <?= (int)PRAZO_DIAS ?> dias · até <?= (int)LIMITE_EMPRESTIMOS ?> livros em aberto por aluno.</p>
  </div>
</div>

<div class="card" style="margin-bottom:24px">
  <h2>Novo empréstimo</h2>
  <?php if (!$alunos): ?>
    <p>Cadastre ao menos um aluno em <a href="alunos.php">Alunos</a> para registrar empréstimos.</p>
  <?php elseif (!$livros): ?>
    <p>Cadastre ao menos um livro em <a href="novo.php">Cadastrar livro</a> para registrar empréstimos.</p>
  <?php else: ?>
    <form method="post" class="linha-form">
      <?= csrf_field() ?>
      <input type="hidden" name="acao" value="emprestar">
      <label class="campo">Aluno
        <select name="aluno_id" required>
          <option value="">Selecione o aluno</option>
          <?php foreach ($alunos as $a): ?>
            <option value="<?= (int)$a['id'] ?>"><?= e($a['nome']) ?> (<?= e($a['turma']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="campo">Livro
        <select name="livro_id" required>
          <option value="">Selecione o livro</option>
          <?php foreach ($livros as $l): $livres = (int)$l['livres']; ?>
            <option value="<?= (int)$l['id'] ?>"<?= $livres <= 0 ? ' disabled' : '' ?>>
              <?= e($l['titulo']) ?><?= $livres <= 0 ? ' — sem exemplares livres' : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <button class="btn btn-primario">Registrar empréstimo</button>
    </form>
  <?php endif; ?>
</div>

<div class="abas">
  <?php foreach ($abas as $chave => [$rotulo, $qtd]): ?>
    <a href="<?= e(url_com(['status' => $chave, 'p' => 1])) ?>"<?= $status === $chave ? ' class="ativa"' : '' ?>>
      <?= $rotulo ?><span><?= (int)$qtd ?></span>
    </a>
  <?php endforeach; ?>
</div>

<form method="get" class="filtros">
  <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
  <input class="busca" type="search" name="busca" value="<?= e($busca) ?>" placeholder="Buscar por aluno, turma ou livro" aria-label="Buscar empréstimos">
  <button class="btn btn-primario">Buscar</button>
  <?php if ($busca !== ''): ?><a class="btn btn-secundario" href="<?= e(url_com(['busca' => '', 'p' => 1])) ?>">Limpar busca</a><?php endif; ?>
</form>

<div class="card card-sem-padding">
  <div class="tabela-wrap">
    <table>
      <thead>
        <tr>
          <th>Aluno</th>
          <th>Livro</th>
          <th>Retirado em</th>
          <th>Prazo</th>
          <th>Situação</th>
          <th class="direita">Ação</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$emprestimos): ?>
        <tr><td colspan="6" class="vazio">
          <strong>Nenhum empréstimo encontrado</strong>
          Ajuste o filtro ou registre um novo empréstimo acima.
        </td></tr>
      <?php endif; ?>
      <?php foreach ($emprestimos as $emp): $aberto = $emp['data_devolucao'] === null; $dias = (int)$emp['dias_atraso']; ?>
        <tr>
          <td><strong><?= e($emp['nome']) ?></strong><span class="sub"><?= e($emp['turma']) ?></span></td>
          <td><?= e($emp['titulo']) ?></td>
          <td><?= data_br($emp['data_emprestimo']) ?></td>
          <td><?= $aberto ? data_br($emp['vencimento']) : '—' ?></td>
          <td>
            <?php if (!$aberto): ?>
              <span class="badge badge-ok">Devolvido em <?= data_br($emp['data_devolucao']) ?></span>
            <?php elseif ($dias > 0): ?>
              <span class="badge badge-erro">Atrasado há <?= $dias ?> dia<?= $dias > 1 ? 's' : '' ?></span>
            <?php else: ?>
              <span class="badge badge-aviso">No prazo</span>
            <?php endif; ?>
          </td>
          <td>
            <div class="acoes">
              <?php if ($aberto): ?>
                <form method="post" data-confirm="<?= e('Registrar a devolução de "' . $emp['titulo'] . '"?') ?>">
                  <?= csrf_field() ?>
                  <input type="hidden" name="acao" value="devolver">
                  <input type="hidden" name="id" value="<?= (int)$emp['id'] ?>">
                  <input type="hidden" name="volta" value="<?= e(url_com([])) ?>">
                  <button class="btn btn-secundario btn-sm">Registrar devolução</button>
                </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="rodape-tabela">
    <span><?= $total ?> empréstimo(s)</span>
    <?= paginacao($total, $pagina, $porPagina) ?>
  </div>
</div>

<?php require_once __DIR__ . '/rodape.php'; ?>
