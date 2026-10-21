<?php
require 'layout.php';
exigir_login();

$result = $conn->query("SELECT * FROM produtos ORDER BY nome");
$linhas = $result->fetch_all(MYSQLI_ASSOC);

cabecalho_pagina('Produtos', 'listar_produtos.php');
titulo_pagina('Produtos', count($linhas) . ' produtos no catálogo.', '<a class="botao" href="cadastrar_produto.php">Cadastrar produto</a>');
?>
<section class="painel">
    <div class="filtros">
        <div class="campo busca">
            <label for="busca">Buscar</label>
            <input type="search" id="busca" data-filtrar="tabela" placeholder="Nome do produto">
        </div>
    </div>
    <?php if ($linhas): ?>
    <div class="tabela-wrap">
        <table id="tabela">
            <thead><tr><th>Produto</th><th class="num">Preço de venda</th><th class="num">Estoque</th><th>Situação</th></tr></thead>
            <tbody>
            <?php foreach ($linhas as $row): ?>
                <tr>
                    <td><strong><?= h($row['nome']) ?></strong>
                        <?php if (!empty($row['descricao'])): ?><span class="sub"><?= h($row['descricao']) ?></span><?php endif; ?></td>
                    <td class="num"><?= brl($row['preco_venda']) ?></td>
                    <td class="num"><?= (int) $row['estoque'] ?></td>
                    <td><?= situacao_estoque($row['estoque']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <div class="vazio"><strong>Nenhum produto cadastrado</strong><a href="cadastrar_produto.php">Cadastrar o primeiro produto</a></div>
    <?php endif; ?>
</section>
<?php rodape_pagina(); ?>
