<?php
require 'layout.php';
exigir_admin();

$res = $conn->query("SELECT s.id, c.nome AS cliente, p.nome AS produto, s.quantidade, s.valor_total, s.data_saida
                      FROM saidas s JOIN produtos p ON p.id = s.produto_id JOIN clientes c ON c.id = s.cliente_id
                      ORDER BY s.data_saida DESC");
$linhas = $res->fetch_all(MYSQLI_ASSOC);
$qtd = array_sum(array_column($linhas, 'quantidade'));
$soma = array_sum(array_column($linhas, 'valor_total'));

cabecalho_pagina('Saídas de produtos', 'saidas.php');
titulo_pagina('Saídas de produtos', number_format($qtd, 0, ',', '.') . ' unidades vendidas, ' . brl($soma) . ' no total.');
?>
<section class="painel">
    <div class="filtros">
        <div class="campo busca">
            <label for="busca">Buscar</label>
            <input type="search" id="busca" data-filtrar="tabela" placeholder="Cliente ou produto">
        </div>
    </div>
    <?php if ($linhas): ?>
    <div class="tabela-wrap">
        <table id="tabela">
            <thead><tr><th>Data</th><th>Cliente</th><th>Produto</th><th class="num">Qtd.</th><th class="num">Total</th></tr></thead>
            <tbody>
            <?php foreach ($linhas as $row): ?>
                <tr>
                    <td class="num" style="text-align:left"><?= data_tabela($row['data_saida']) ?></td>
                    <td><?= h($row['cliente']) ?></td>
                    <td><strong><?= h($row['produto']) ?></strong></td>
                    <td class="num valor-saida">− <?= (int) $row['quantidade'] ?></td>
                    <td class="num"><?= brl($row['valor_total']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <div class="vazio"><strong>Nenhuma saída registrada</strong></div>
    <?php endif; ?>
</section>
<?php rodape_pagina(); ?>
