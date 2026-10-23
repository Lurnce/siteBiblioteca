<?php
require_once __DIR__ . '/conexao.php';

$vencimento = 'DATE_ADD(e.data_emprestimo, INTERVAL ' . (int)PRAZO_DIAS . ' DAY)';

$acervo    = $pdo->query("SELECT COUNT(*) AS titulos, COALESCE(SUM(quantidade), 0) AS exemplares FROM livros")->fetch();
$abertos   = (int)$pdo->query("SELECT COUNT(*) FROM emprestimos WHERE data_devolucao IS NULL")->fetchColumn();
$atrasados = (int)$pdo->query("SELECT COUNT(*) FROM emprestimos e
                               WHERE e.data_devolucao IS NULL AND $vencimento < CURDATE()")->fetchColumn();
$qtdAlunos = (int)$pdo->query("SELECT COUNT(*) FROM alunos")->fetchColumn();
$livres    = max(0, (int)$acervo['exemplares'] - $abertos);

// Empréstimos por mês (últimos 6 meses)
$nomesMes = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
$meses = [];
$base  = new DateTime('first day of this month');
for ($i = 5; $i >= 0; $i--) {
    $d = (clone $base)->modify("-$i month");
    $meses[$d->format('Y-m')] = ['rotulo' => $nomesMes[(int)$d->format('n') - 1], 'total' => 0];
}
$inicio = array_key_first($meses) . '-01';
$st = $pdo->prepare("SELECT DATE_FORMAT(data_emprestimo, '%Y-%m') AS ym, COUNT(*) AS total
                     FROM emprestimos WHERE data_emprestimo >= :ini GROUP BY ym");
$st->execute([':ini' => $inicio]);
foreach ($st->fetchAll() as $linha) {
    if (isset($meses[$linha['ym']])) {
        $meses[$linha['ym']]['total'] = (int)$linha['total'];
    }
}
$maxMes = max(1, max(array_column($meses, 'total')));

// Livros mais emprestados
$top = $pdo->query("SELECT l.titulo, l.autor, COUNT(e.id) AS total
                    FROM livros l JOIN emprestimos e ON e.livro_id = l.id
                    GROUP BY l.id ORDER BY total DESC, l.titulo LIMIT 5")->fetchAll();

// Atrasos mais antigos
$atrasos = $pdo->query("SELECT a.nome, a.turma, l.titulo, $vencimento AS vencimento,
                               DATEDIFF(CURDATE(), $vencimento) AS dias
                        FROM emprestimos e
                        JOIN alunos a ON a.id = e.aluno_id
                        JOIN livros l ON l.id = e.livro_id
                        WHERE e.data_devolucao IS NULL AND $vencimento < CURDATE()
                        ORDER BY e.data_emprestimo ASC LIMIT 5")->fetchAll();

$titulo_pagina = 'Painel';
require_once __DIR__ . '/topo.php';
?>

<div class="pagina-topo">
  <div>
    <h1>Painel</h1>
    <p>Situação da biblioteca hoje, <?= date('d/m/Y') ?>.</p>
  </div>
  <a class="btn btn-primario" href="emprestimos.php">Registrar empréstimo</a>
</div>

<div class="numeros">
  <div class="numero">
    <strong><?= (int)$acervo['titulos'] ?></strong>
    <span>Títulos no acervo</span>
    <small><?= (int)$acervo['exemplares'] ?> exemplares no total</small>
  </div>
  <div class="numero">
    <strong><?= $livres ?></strong>
    <span>Exemplares livres</span>
    <small>Disponíveis para empréstimo</small>
  </div>
  <div class="numero">
    <strong><?= $abertos ?></strong>
    <span>Emprestados agora</span>
    <small><a href="emprestimos.php?status=abertos">Ver em aberto</a></small>
  </div>
  <div class="numero<?= $atrasados > 0 ? ' alerta-num' : '' ?>">
    <strong><?= $atrasados ?></strong>
    <span>Em atraso</span>
    <small><?= $atrasados > 0 ? '<a href="emprestimos.php?status=atrasados">Ver atrasados</a>' : 'Tudo em dia' ?></small>
  </div>
  <div class="numero">
    <strong><?= $qtdAlunos ?></strong>
    <span>Alunos cadastrados</span>
    <small><a href="alunos.php">Gerenciar alunos</a></small>
  </div>
</div>

<div class="grade-2">
  <section class="card">
    <div class="card-titulo"><h2>Empréstimos por mês</h2><span class="sub">Últimos 6 meses</span></div>
    <div class="grafico" role="img" aria-label="Gráfico de empréstimos por mês">
      <?php foreach ($meses as $m): ?>
        <div class="barra">
          <div class="barra-area">
            <div class="barra-col" style="height:<?= round($m['total'] / $maxMes * 100) ?>%"></div>
          </div>
          <div class="barra-rotulo"><strong><?= $m['total'] ?></strong><span><?= $m['rotulo'] ?></span></div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="card">
    <div class="card-titulo"><h2>Mais emprestados</h2></div>
    <?php if (!$top): ?>
      <p class="sub" style="color:var(--suave)">Os livros mais pedidos aparecem aqui depois dos primeiros empréstimos.</p>
    <?php else: ?>
      <ol class="ranking">
        <?php foreach ($top as $t): ?>
          <li>
            <span><strong><?= e($t['titulo']) ?></strong><span class="sub"><?= e($t['autor']) ?></span></span>
            <span class="qtd"><?= (int)$t['total'] ?>x</span>
          </li>
        <?php endforeach; ?>
      </ol>
    <?php endif; ?>
  </section>
</div>

<section class="card card-sem-padding">
  <div class="card-titulo" style="padding:20px 24px 0">
    <h2>Devoluções em atraso</h2>
    <?php if ($atrasados > 5): ?><a href="emprestimos.php?status=atrasados">Ver todos (<?= $atrasados ?>)</a><?php endif; ?>
  </div>
  <div class="tabela-wrap">
    <table>
      <thead><tr><th>Aluno</th><th>Livro</th><th>Venceu em</th><th>Atraso</th></tr></thead>
      <tbody>
      <?php if (!$atrasos): ?>
        <tr><td colspan="4" class="vazio"><strong>Nenhuma devolução em atraso</strong>Todos os livros emprestados estão dentro do prazo.</td></tr>
      <?php endif; ?>
      <?php foreach ($atrasos as $a): $dias = (int)$a['dias']; ?>
        <tr>
          <td><strong><?= e($a['nome']) ?></strong><span class="sub"><?= e($a['turma']) ?></span></td>
          <td><?= e($a['titulo']) ?></td>
          <td><?= data_br($a['vencimento']) ?></td>
          <td><span class="badge badge-erro"><?= $dias ?> dia<?= $dias > 1 ? 's' : '' ?></span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<?php require_once __DIR__ . '/rodape.php'; ?>
