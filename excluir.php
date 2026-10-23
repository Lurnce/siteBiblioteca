<?php
require_once __DIR__ . '/conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar('index.php');
}
csrf_check();

$id = (int)($_POST['id'] ?? 0);

try {
    $st = $pdo->prepare("SELECT COUNT(*) FROM emprestimos WHERE livro_id = :id");
    $st->execute([':id' => $id]);

    if ((int)$st->fetchColumn() > 0) {
        flash('Este livro tem empréstimos registrados e não pode ser excluído.', 'erro');
    } else {
        $del = $pdo->prepare("DELETE FROM livros WHERE id = :id");
        $del->execute([':id' => $id]);
        flash($del->rowCount() ? 'Livro excluído.' : 'Livro não encontrado.', $del->rowCount() ? 'ok' : 'erro');
    }
} catch (PDOException $erro) {
    flash('Não foi possível excluir o livro.', 'erro');
}

redirecionar('index.php');
