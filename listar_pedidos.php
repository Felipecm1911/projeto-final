<?php
require 'layout.php';
exigir_login();

$result = $conn->query("SELECT p.id, c.nome as cliente, p.valor_total, p.data_pedido FROM pedidos p JOIN clientes c ON p.cliente_id = c.id ORDER BY p.id DESC");
$linhas = $result->fetch_all(MYSQLI_ASSOC);
$soma = array_sum(array_column($linhas, 'valor_total'));

cabecalho_pagina('Pedidos', 'listar_pedidos.php');
titulo_pagina('Pedidos', count($linhas) . ' pedidos, ' . brl($soma) . ' no total.', '<a class="botao" href="novo_pedido.php">Novo pedido</a>');
?>
<section class="painel">
    <div class="filtros">
        <div class="campo busca">
            <label for="busca">Buscar</label>
            <input type="search" id="busca" data-filtrar="tabela" placeholder="Número ou cliente">
        </div>
    </div>
    <?php if ($linhas): ?>
    <div class="tabela-wrap">
        <table id="tabela">
            <thead><tr><th>Pedido</th><th>Cliente</th><th>Data</th><th class="num">Total</th></tr></thead>
            <tbody>
            <?php foreach ($linhas as $row): ?>
                <tr>
                    <td class="num" style="text-align:left"><strong>#<?= (int) $row['id'] ?></strong></td>
                    <td><?= h($row['cliente']) ?></td>
                    <td class="num" style="text-align:left"><?= data_tabela($row['data_pedido']) ?></td>
                    <td class="num"><?= brl($row['valor_total']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <div class="vazio"><strong>Nenhum pedido ainda</strong><a href="novo_pedido.php">Criar o primeiro pedido</a></div>
    <?php endif; ?>
</section>
<?php rodape_pagina(); ?>
