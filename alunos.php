<?php
require_once __DIR__ . '/conexao.php';

$erros = [];
$form  = ['id' => 0, 'nome' => '', 'turma' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'salvar') {
        $form = [
            'id'    => (int)($_POST['id'] ?? 0),
            'nome'  => trim($_POST['nome'] ?? ''),
            'turma' => trim($_POST['turma'] ?? ''),
        ];
        if ($form['nome'] === '') {
            $erros[] = 'Informe o nome do aluno.';
        } elseif (mb_strlen($form['nome']) > 100) {
            $erros[] = 'O nome pode ter no máximo 100 caracteres.';
        }
        if ($form['turma'] === '') {
            $erros[] = 'Informe a turma.';
        } elseif (mb_strlen($form['turma']) > 30) {
            $erros[] = 'A turma pode ter no máximo 30 caracteres.';
        }

        if (!$erros) {
            try {
                if ($form['id'] > 0) {
                    $st = $pdo->prepare("UPDATE alunos SET nome = :nome, turma = :turma WHERE id = :id");
                    $st->execute([':nome' => $form['nome'], ':turma' => $form['turma'], ':id' => $form['id']]);
                    flash('Dados do aluno atualizados.');
                } else {
                    $st = $pdo->prepare("INSERT INTO alunos (nome, turma) VALUES (:nome, :turma)");
                    $st->execute([':nome' => $form['nome'], ':turma' => $form['turma']]);
                    flash('Aluno cadastrado.');
                }
                redirecionar('alunos.php');
            } catch (PDOException $ex) {
                $erros[] = 'Não foi possível salvar o aluno. Verifique os dados e tente novamente.';
            }
        }
    } elseif ($acao === 'excluir') {
        $id = (int)($_POST['id'] ?? 0);
        try {
            $st = $pdo->prepare("SELECT COUNT(*) FROM emprestimos WHERE aluno_id = :id");
            $st->execute([':id' => $id]);
            if ((int)$st->fetchColumn() > 0) {
                flash('Este aluno tem empréstimos registrados e não pode ser excluído.', 'erro');
            } else {
                $del = $pdo->prepare("DELETE FROM alunos WHERE id = :id");
                $del->execute([':id' => $id]);
                flash('Aluno excluído.');
            }
        } catch (PDOException $ex) {
            flash('Não foi possível excluir o aluno.', 'erro');
        }
        redirecionar('alunos.php');
    }
} elseif (isset($_GET['editar'])) {
    $st = $pdo->prepare("SELECT id, nome, turma FROM alunos WHERE id = :id");
    $st->execute([':id' => (int)$_GET['editar']]);
    $achado = $st->fetch();
    if ($achado) {
        $form = $achado;
    }
}

// ---------- Lista ----------
$busca     = trim($_GET['busca'] ?? '');
$porPagina = 12;
$where     = '';
$params    = [];
if ($busca !== '') {
    $where = ' WHERE (a.nome LIKE :b1 OR a.turma LIKE :b2)';
    $params[':b1'] = $params[':b2'] = "%$busca%";
}

$st = $pdo->prepare("SELECT COUNT(*) FROM alunos a" . $where);
$st->execute($params);
$total = (int)$st->fetchColumn();

$totalPaginas = max(1, (int)ceil($total / $porPagina));
$pagina = min(max(1, (int)($_GET['p'] ?? 1)), $totalPaginas);
$offset = ($pagina - 1) * $porPagina;

$st = $pdo->prepare("SELECT a.id, a.nome, a.turma,
        COUNT(e.id) AS total,
        COALESCE(SUM(e.data_devolucao IS NULL), 0) AS abertos
        FROM alunos a
        LEFT JOIN emprestimos e ON e.aluno_id = a.id"
    . $where . "
        GROUP BY a.id
        ORDER BY a.nome
        LIMIT $porPagina OFFSET $offset");
$st->execute($params);
$alunos = $st->fetchAll();

$editando = (int)$form['id'] > 0;
$titulo_pagina = 'Alunos';
require_once __DIR__ . '/topo.php';
?>

<div class="pagina-topo">
  <div>
    <h1>Alunos</h1>
    <p>Quem pode pegar livros emprestados e quantos já estão com cada um.</p>
  </div>
</div>

<div class="layout-lateral">
  <section class="card">
    <h2><?= $editando ? 'Editar aluno' : 'Novo aluno' ?></h2>

    <?php if ($erros): ?>
      <div class="alerta alerta-erro" role="alert">
        <ul style="margin:0"><?php foreach ($erros as $erro): ?><li><?= e($erro) ?></li><?php endforeach; ?></ul>
      </div>
    <?php endif; ?>

    <form method="post" class="form-pilha">
      <?= csrf_field() ?>
      <input type="hidden" name="acao" value="salvar">
      <input type="hidden" name="id" value="<?= (int)$form['id'] ?>">
      <label class="campo">Nome completo
        <input name="nome" value="<?= e($form['nome']) ?>" required maxlength="100">
      </label>
      <label class="campo">Turma
        <input name="turma" value="<?= e($form['turma']) ?>" required maxlength="30" placeholder="Ex.: 7º A">
      </label>
      <div class="form-acoes">
        <button class="btn btn-primario"><?= $editando ? 'Salvar alterações' : 'Cadastrar aluno' ?></button>
        <?php if ($editando): ?><a class="btn btn-secundario" href="alunos.php">Cancelar</a><?php endif; ?>
      </div>
    </form>
  </section>

  <section>
    <form method="get" class="filtros">
      <input class="busca" type="search" name="busca" value="<?= e($busca) ?>" placeholder="Buscar por nome ou turma" aria-label="Buscar alunos">
      <button class="btn btn-primario">Buscar</button>
      <?php if ($busca !== ''): ?><a class="btn btn-secundario" href="alunos.php">Limpar</a><?php endif; ?>
    </form>

    <div class="card card-sem-padding">
      <div class="tabela-wrap">
        <table>
          <thead>
            <tr>
              <th>Aluno</th>
              <th>Em aberto</th>
              <th>Total</th>
              <th class="direita">Ações</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$alunos): ?>
            <tr><td colspan="4" class="vazio">
              <strong><?= $busca !== '' ? 'Nenhum aluno encontrado' : 'Nenhum aluno cadastrado' ?></strong>
              <?= $busca !== '' ? 'Tente outro nome ou turma.' : 'Use o formulário ao lado para cadastrar o primeiro.' ?>
            </td></tr>
          <?php endif; ?>
          <?php foreach ($alunos as $a): ?>
            <tr>
              <td><strong><?= e($a['nome']) ?></strong><span class="sub"><?= e($a['turma']) ?></span></td>
              <td>
                <?php if ((int)$a['abertos'] > 0): ?>
                  <span class="badge badge-aviso"><?= (int)$a['abertos'] ?> livro(s)</span>
                <?php else: ?>
                  <span class="badge badge-neutro">Nenhum</span>
                <?php endif; ?>
              </td>
              <td><?= (int)$a['total'] ?></td>
              <td>
                <div class="acoes">
                  <a class="btn btn-secundario btn-sm" href="alunos.php?editar=<?= (int)$a['id'] ?>">Editar</a>
                  <form method="post" data-confirm="<?= e('Excluir o aluno "' . $a['nome'] . '"?') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="acao" value="excluir">
                    <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                    <button class="btn btn-perigo btn-sm"<?= (int)$a['total'] > 0 ? ' disabled title="Possui empréstimos registrados"' : '' ?>>Excluir</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="rodape-tabela">
        <span><?= $total ?> aluno(s)</span>
        <?= paginacao($total, $pagina, $porPagina) ?>
      </div>
    </div>
  </section>
</div>

<?php require_once __DIR__ . '/rodape.php'; ?>
