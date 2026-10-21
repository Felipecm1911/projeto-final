<?php
// ==========================================================
// Layout compartilhado: sessão, permissões, menu e ajudantes
// ==========================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Erro inesperado: registra no log do PHP e mostra uma mensagem simples, sem detalhes do banco
set_exception_handler(function ($e) {
    error_log($e);
    http_response_code(500);
    echo '<p style="font-family:sans-serif;padding:24px">Algo deu errado. Tente de novo ou <a href="painel.php">volte ao painel</a>.</p>';
});

include_once 'conexao.php';

// Acentos corretos e horário de Brasília
$conn->set_charset('utf8mb4');
date_default_timezone_set('America/Sao_Paulo');

// Abaixo disso o produto conta como estoque baixo (tela Configurações)
define('ESTOQUE_MINIMO', (int) consultar_valor($conn, "SELECT valor FROM configuracoes WHERE chave = 'estoque_minimo'") ?: 10);

/* ---------- Proteção dos formulários (CSRF) ---------- */

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

// Todo POST das páginas do sistema precisa trazer o token da sessão
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
    http_response_code(400);
    exit('<p style="font-family:sans-serif;padding:24px">Formulário expirado. <a href="javascript:history.back()">Volte</a> e envie de novo.</p>');
}

function campo_csrf() {
    return '<input type="hidden" name="csrf" value="' . h($_SESSION['csrf']) . '">';
}

/* ---------- Permissões ---------- */

function exigir_login() {
    if (!isset($_SESSION['usuario'])) {
        header('Location: login.html');
        exit;
    }
}

// Páginas que o nível "caixa" não pode acessar
function exigir_admin() {
    exigir_login();
    if (eh_caixa()) {
        header('Location: painel.php');
        exit;
    }
}

// Páginas só do administrador (usuários do sistema)
function exigir_somente_admin() {
    exigir_login();
    if (!eh_admin()) {
        header('Location: painel.php');
        exit;
    }
}

// Quem não é admin nem suporte fica com o acesso do caixa, até um nível com erro de digitação
function eh_caixa() {
    return !in_array($_SESSION['nivel'] ?? '', ['admin', 'suporte'], true);
}

function eh_admin() {
    return ($_SESSION['nivel'] ?? '') == 'admin';
}

/* ---------- Formatação ---------- */

function h($texto) {
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

function brl($valor) {
    return 'R$ ' . number_format((float) $valor, 2, ',', '.');
}

function data_br($data, $com_hora = true) {
    if (empty($data)) return '';
    return date($com_hora ? 'd/m/Y H:i' : 'd/m/Y', strtotime($data));
}

function situacao_estoque($qtd) {
    $qtd = (int) $qtd;
    if ($qtd <= 0)  return '<span class="etiqueta zerado">Sem estoque</span>';
    if ($qtd < ESTOQUE_MINIMO) return '<span class="etiqueta baixo">Baixo</span>';
    return '<span class="etiqueta ok">Normal</span>';
}

/* ---------- Mensagens entre páginas ---------- */

function avisar($tipo, $texto, $link = null, $rotulo = null) {
    $_SESSION['aviso'] = ['tipo' => $tipo, 'texto' => $texto, 'link' => $link, 'rotulo' => $rotulo];
}

function mostrar_aviso() {
    if (empty($_SESSION['aviso'])) return;
    $a = $_SESSION['aviso'];
    unset($_SESSION['aviso']);
    echo '<div class="mensagem ' . h($a['tipo']) . '" role="status"><span>' . h($a['texto']) . '</span>';
    if ($a['link']) echo '<a href="' . h($a['link']) . '">' . h($a['rotulo']) . '</a>';
    echo '</div>';
}

/* ---------- Consultas ---------- */

// Erro no banco não vira zero: cai no tratador de erros lá de cima
function consultar_valor($conn, $sql) {
    return $conn->query($sql)->fetch_row()[0] ?? 0;
}

function consultar_linhas($conn, $sql) {
    return $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
}

// Filtro "De" / "Até" das telas de vendas; cada data funciona sozinha
function filtro_datas($coluna, &$where, &$params, &$types) {
    foreach (['data_inicio' => ['>=', ' 00:00:00'], 'data_fim' => ['<=', ' 23:59:59']] as $campo => [$op, $hora]) {
        if (!empty($_GET[$campo])) {
            $where .= " AND $coluna $op ?";
            $params[] = $_GET[$campo] . $hora;
            $types .= 's';
        }
    }
}

/* ---------- Menu ---------- */

function itens_menu() {
    return [
        'Início' => [
            ['painel.php', 'Painel', false],
        ],
        'Vendas' => [
            ['novo_pedido.php', 'Novo pedido', false],
            ['listar_pedidos.php', 'Pedidos', false],
            ['historico_venda.php', 'Histórico de venda', true],
            ['listar_clientes.php', 'Clientes', true],
        ],
        'Estoque' => [
            ['listar_produtos.php', 'Produtos', false],
            ['relatorio_estoque.php', 'Relatório de estoque', true],
            ['entradas.php', 'Entradas de produtos', true],
            ['saidas.php', 'Saídas de produtos', true],
            ['listar_fornecedores.php', 'Fornecedores', true],
        ],
        'Financeiro' => [
            ['caixa.php', 'Caixa', false],
            ['lucro.php', 'Lucro', true],
            ['relatorio_excel.php', 'Relatório em Excel', true],
        ],
        'Sistema' => [
            ['listar_usuarios.php', 'Usuários', 'admin'],
            ['configuracoes.php', 'Configurações', true],
        ],
    ];
}

/* ---------- Início e fim da página ---------- */

// $ativo = arquivo do menu que fica destacado (ex.: 'listar_clientes.php')
function cabecalho_pagina($titulo, $ativo = '') {
    $nome = $_SESSION['usuario'] ?? '';
    $nivel = $_SESSION['nivel'] ?? '';
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($titulo) ?> · Erva Mate</title>
    <link rel="icon" href="logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@100..125,400..800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="estilo.css">
</head>
<body>
<div class="topo-movel">
    <a class="marca" href="painel.php">
        <img src="logo.png" alt="">
        <div><strong>Erva Mate</strong></div>
    </a>
    <button type="button" id="abrir-menu" aria-expanded="false" aria-controls="menu-lateral">Menu</button>
</div>
<div class="app">
    <aside class="lateral" id="menu-lateral">
        <a class="marca" href="painel.php">
            <img src="logo.png" alt="Logo da Erva Mate">
            <div><strong>Erva Mate</strong><span>Gestão da loja</span></div>
        </a>
        <nav aria-label="Menu principal">
        <?php foreach (itens_menu() as $grupo => $itens):
            // false = todos; true = todos menos caixa; 'admin' = só administrador
            $visiveis = array_filter($itens, fn($i) => $i[2] === 'admin' ? eh_admin() : !($i[2] && eh_caixa()));
            if (!$visiveis) continue; ?>
            <div class="menu-grupo">
                <h2><?= h($grupo) ?></h2>
                <?php foreach ($visiveis as $i): ?>
                    <a href="<?= $i[0] ?>"<?= $i[0] == $ativo ? ' aria-current="page"' : '' ?>><?= h($i[1]) ?></a>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
        </nav>
        <div class="usuario">
            <strong><?= h($nome) ?></strong>
            <?= h(ucfirst($nivel)) ?> · <a href="alterar_senha.php">Senha</a> · <a href="logout.php">Sair</a>
        </div>
    </aside>
    <main class="principal entrar">
    <?php
    mostrar_aviso();
}

function rodape_pagina() {
    ?>
    </main>
</div>
<script>
    // Menu no celular
    var botaoMenu = document.getElementById('abrir-menu');
    botaoMenu.addEventListener('click', function () {
        var aberto = document.body.classList.toggle('menu-aberto');
        botaoMenu.setAttribute('aria-expanded', aberto);
        botaoMenu.textContent = aberto ? 'Fechar' : 'Menu';
    });

    // Busca rápida nas tabelas: <input data-filtrar="id-da-tabela">
    document.querySelectorAll('[data-filtrar]').forEach(function (campo) {
        var tabela = document.getElementById(campo.dataset.filtrar);
        campo.addEventListener('input', function () {
            var termo = campo.value.toLowerCase().trim();
            tabela.querySelectorAll('tbody tr').forEach(function (linha) {
                linha.hidden = termo && !linha.textContent.toLowerCase().includes(termo);
            });
        });
    });
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
<script src="animacoes.js"></script>
</body>
</html>
    <?php
}

// Cabeçalho de seção dentro da página
function titulo_pagina($titulo, $descricao = '', $acoes = '', $voltar = null) {
    echo '<header class="cabecalho"><div>';
    if ($voltar) echo '<a class="voltar" href="' . h($voltar[0]) . '">← ' . h($voltar[1]) . '</a>';
    echo '<h1>' . h($titulo) . '</h1>';
    if ($descricao) echo '<p>' . $descricao . '</p>';
    echo '</div>';
    if ($acoes) echo '<div class="acoes">' . $acoes . '</div>';
    echo '</header>';
}

// Data para células de tabela: no celular a hora vai para a linha de baixo
function data_tabela($data) {
    if (empty($data)) return '';
    $t = strtotime($data);
    return date('d/m/Y', $t) . ' <span class="hora">' . date('H:i', $t) . '</span>';
}
