<?php
// Proteção contra XSS
function e($valor) {
    return htmlspecialchars((string)($valor ?? ''), ENT_QUOTES, 'UTF-8');
}

// Proteção contra CSRF
function csrf_token() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check() {
    $enviado = $_POST['csrf'] ?? '';
    if (!is_string($enviado) || !hash_equals($_SESSION['csrf'] ?? '', $enviado)) {
        http_response_code(419);
        die('Sessão expirada. Volte à página anterior, recarregue e tente de novo.');
    }
}

// Mensagens que aparecem após um redirecionamento
function flash($msg, $tipo = 'ok') {
    $_SESSION['flash'] = ['msg' => $msg, 'tipo' => $tipo];
}

function flash_get() {
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function redirecionar($url) {
    header('Location: ' . $url);
    exit;
}

function data_br($data) {
    return $data ? date('d/m/Y', strtotime($data)) : '—';
}

// Monta uma URL mantendo os filtros atuais e trocando só o que mudou
function url_com(array $novos) {
    $q = array_merge($_GET, $novos);
    $q = array_filter($q, function ($v) { return $v !== '' && $v !== null; });
    return '?' . http_build_query($q);
}

function paginacao($total, $pagina, $porPagina) {
    $paginas = (int)ceil($total / $porPagina);
    if ($paginas <= 1) {
        return '';
    }
    $link = function ($p, $texto, $classe = '') {
        return '<a class="' . $classe . '" href="' . e(url_com(['p' => $p])) . '">' . $texto . '</a>';
    };
    $html = '<nav class="paginacao" aria-label="Paginação">';
    if ($pagina > 1) {
        $html .= $link($pagina - 1, 'Anterior');
    }
    for ($i = max(1, $pagina - 2); $i <= min($paginas, $pagina + 2); $i++) {
        $html .= $link($i, (string)$i, $i === $pagina ? 'atual' : '');
    }
    if ($pagina < $paginas) {
        $html .= $link($pagina + 1, 'Próxima');
    }
    return $html . '</nav>';
}

// Valida os dados do formulário de livro. Retorna [dados, erros]
function validar_livro(array $in) {
    $d = [
        'titulo'     => trim($in['titulo'] ?? ''),
        'autor'      => trim($in['autor'] ?? ''),
        'ano'        => ($in['ano'] ?? '') !== '' ? (int)$in['ano'] : null,
        'genero'     => trim($in['genero'] ?? ''),
        'quantidade' => (int)($in['quantidade'] ?? 0),
    ];
    $erros = [];

    if ($d['titulo'] === '') {
        $erros[] = 'Informe o título.';
    } elseif (mb_strlen($d['titulo']) > 150) {
        $erros[] = 'O título pode ter no máximo 150 caracteres.';
    }
    if ($d['autor'] === '') {
        $erros[] = 'Informe o autor.';
    } elseif (mb_strlen($d['autor']) > 100) {
        $erros[] = 'O autor pode ter no máximo 100 caracteres.';
    }
    if ($d['ano'] !== null && ($d['ano'] < 1000 || $d['ano'] > (int)date('Y') + 1)) {
        $erros[] = 'Informe um ano válido (entre 1000 e ' . ((int)date('Y') + 1) . ').';
    }
    if (mb_strlen($d['genero']) > 50) {
        $erros[] = 'O gênero pode ter no máximo 50 caracteres.';
    }
    if ($d['quantidade'] < 1 || $d['quantidade'] > 999) {
        $erros[] = 'A quantidade deve estar entre 1 e 999.';
    }
    return [$d, $erros];
}
