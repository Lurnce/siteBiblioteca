<?php
// Espera: $dados (array), $erros (array), $generos (array), $botao (string)
?>
<?php if ($erros): ?>
  <div class="alerta alerta-erro" role="alert">
    <strong>Corrija os campos abaixo para salvar:</strong>
    <ul>
      <?php foreach ($erros as $erro): ?><li><?= e($erro) ?></li><?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<form method="post" class="card form-grid">
  <?= csrf_field() ?>
  <label class="campo span-2">Título
    <input name="titulo" value="<?= e($dados['titulo']) ?>" required maxlength="150" autofocus>
  </label>
  <label class="campo">Autor
    <input name="autor" value="<?= e($dados['autor']) ?>" required maxlength="100">
  </label>
  <label class="campo">Gênero
    <input name="genero" value="<?= e($dados['genero']) ?>" maxlength="50" list="lista-generos" placeholder="Ex.: Romance">
  </label>
  <label class="campo">Ano de publicação
    <input name="ano" type="number" value="<?= e($dados['ano']) ?>" min="1000" max="<?= (int)date('Y') + 1 ?>">
  </label>
  <label class="campo">Quantidade de exemplares
    <input name="quantidade" type="number" min="1" max="999" value="<?= e($dados['quantidade']) ?>" required>
  </label>
  <datalist id="lista-generos">
    <?php foreach ($generos as $g): ?><option value="<?= e($g) ?>"><?php endforeach; ?>
  </datalist>
  <div class="form-acoes span-2">
    <button class="btn btn-primario"><?= e($botao) ?></button>
    <a class="btn btn-secundario" href="index.php">Cancelar</a>
  </div>
</form>
