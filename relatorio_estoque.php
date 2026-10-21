<?php
require 'layout.php';
exigir_admin();

$res = $conn->query("SELECT * FROM relatorio_estoque");
$linhas = $res->fetch_all(MYSQLI_ASSOC);

$valor_custo = 0; $valor_venda = 0; $lucro = 0;
foreach ($linhas as $l) {
    $valor_custo += $l['preco_compra'] * $l['estoque'];
    $valor_venda += $l['preco_venda'] * $l['estoque'];
    $lucro += $l['lucro_estocado'];
}

cabecalho_pagina('Relatório de estoque', 'relatorio_estoque.php');
titulo_pagina('Relatório detalhado do estoque', 'Quanto o estoque custou, quanto vale na venda e o lucro que ele guarda.');
?>
<section class="indicadores tres" aria-label="Totais do estoque">
    <div class="indicador"><span>Custo do estoque</span><strong><?= brl($valor_custo) ?></strong></div>
    <div class="indicador"><span>Valor de venda</span><strong><?= brl($valor_venda) ?></strong></div>
    <div class="indicador destaque"><span>Lucro estocado</span><strong><?= brl($lucro) ?></strong></div>
</section>

<section class="painel">
    <?php if ($linhas): ?>
    <div class="tabela-wrap">
        <table id="tabela">
            <thead><tr><th>Produto</th><th class="num">Qtd.</th><th class="num">Compra</th><th class="num">Venda</th><th class="num">Margem</th><th class="num">Lucro estocado</th></tr></thead>
            <tbody>
            <?php foreach ($linhas as $row):
                $margem = $row['preco_venda'] > 0 ? ($row['preco_venda'] - $row['preco_compra']) / $row['preco_venda'] * 100 : 0; ?>
                <tr>
                    <td><strong><?= h($row['nome']) ?></strong></td>
                    <td class="num"><?= (int) $row['estoque'] ?></td>
                    <td class="num"><?= brl($row['preco_compra']) ?></td>
                    <td class="num"><?= brl($row['preco_venda']) ?></td>
                    <td class="num"><?= number_format($margem, 0, ',', '.') ?>%</td>
                    <td class="num"><strong><?= brl($row['lucro_estocado']) ?></strong></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr><td colspan="5">Total</td><td class="num"><?= brl($lucro) ?></td></tr></tfoot>
        </table>
    </div>
    <?php else: ?>
        <div class="vazio"><strong>Sem dados de estoque</strong></div>
    <?php endif; ?>
</section>
<?php rodape_pagina(); ?>
