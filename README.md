# Trata — Biblioteca da Escola

Sistema web de biblioteca escolar em **PHP + MariaDB/MySQL** (XAMPP).

## Funcionalidades

- **Painel**: totais do acervo, exemplares livres, empréstimos em aberto, atrasos, gráfico dos últimos 6 meses e livros mais emprestados.
- **Livros**: cadastro, edição, exclusão, busca por título/autor, filtros (gênero e disponibilidade), ordenação, paginação e histórico de empréstimos de cada título.
- **Alunos**: cadastro, edição, exclusão e busca, com contagem de livros em aberto.
- **Empréstimos**: prazo de devolução, destaque de atrasos, filtros (todos, em aberto, atrasados, devolvidos), busca e paginação.
- **Regras**: não empresta livro sem exemplar livre, não repete o mesmo livro para o mesmo aluno e respeita o limite de livros por aluno.
- **Segurança**: proteção contra XSS e CSRF, consultas preparadas e exclusão bloqueada quando há empréstimos.

## Como instalar

1. Copie esta pasta para `htdocs` do XAMPP e inicie Apache e MySQL.
2. Crie o banco `biblioteca` (veja o esquema abaixo, caso ainda não exista).
3. Ajuste usuário e senha em `conexao.php`, se necessário.
4. Acesse `http://localhost/Trata/`.

Prazo e limite por aluno ficam em `conexao.php`: `PRAZO_DIAS` (14) e `LIMITE_EMPRESTIMOS` (3).

## Esquema esperado do banco

```sql
CREATE TABLE livros (
  id INT AUTO_INCREMENT PRIMARY KEY,
  titulo VARCHAR(150) NOT NULL,
  autor VARCHAR(100) NOT NULL,
  ano SMALLINT NULL,
  genero VARCHAR(50) NULL,
  quantidade INT NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE alunos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(100) NOT NULL,
  turma VARCHAR(30) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE emprestimos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  livro_id INT NOT NULL,
  aluno_id INT NOT NULL,
  data_emprestimo DATE NOT NULL,
  data_devolucao DATE NULL,
  FOREIGN KEY (livro_id) REFERENCES livros(id),
  FOREIGN KEY (aluno_id) REFERENCES alunos(id)
) ENGINE=InnoDB;
```
