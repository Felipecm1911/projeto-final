<?php
require 'layout.php';
exigir_admin();

$where = "1";
$params = [];
$types = "";

if (!empty($_GET['produto'])) {
    $where .= " AND p.nome LIKE ?";
    $params[] = "%" . $_GET['produto'] . "%";
    $types .= "s";
}
filtro_datas('s.data_saida', $where, $params, $types);

$sql = "SELECT s.id, p.nome AS produto, s.quantidade, s.valor_total,
               (s.valor_total - (COALESCE(s.preco_compra, p.preco_compra) * s.quantidade)) AS lucro,
               s.data_saida
        FROM saidas s
        JOIN produtos p ON s.produto_id = p.id
        WHERE $where ORDER BY s.data_saida DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$linhas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$lucro_total = array_sum(array_column($linhas, 'lucro'));
$vendido = array_sum(array_column($linhas, 'valor_total'));
$margem = $vendido > 0 ? $lucro_total / $vendido * 100 : 0;
$filtrado = !empty($params);

cabecalho_pagina('Lucro', 'lucro.php');
titulo_pagina('Lucro por venda', 'Valor da venda menos o preço de compra de cada unidade.');
?>
<section class="indicadores tres" aria-label="Totais">
    <div class="indicador destaque"><span>Lucro<?= $filtrado ? ' no filtro' : '' ?></span><strong><?= brl($lucro_total) ?></strong></div>
    <div class="indicador"><span>Vendido</span><strong><?= brl($vendido) ?></strong></div>
    <div class="indicador"><span>Margem média</span><strong><?= number_format($margem, 1, ',', '.') ?>%</strong></div>
</section>

<section class="painel">
    <form method="get" class="filtros">
        <div class="campo">
            <label for="produto">Produto</label>
            <input type="text" id="produto" name="produto" value="<?= h($_GET['produto'] ?? '') ?>" placeholder="Nome do produto">
        </div>
        <div class="campo">
            <label for="data_inicio">De</label>
            <input type="date" id="data_inicio" name="data_inicio" value="<?= h($_GET['data_inicio'] ?? '') ?>">
        </div>
        <div class="campo">
            <label for="data_fim">Até</label>
            <input type="date" id="data_fim" name="data_fim" value="<?= h($_GET['data_fim'] ?? '') ?>">
        </div>
        <button class="botao" type="submit">Filtrar</button>
        <?php if ($filtrado): ?><a class="botao secundario" href="lucro.php">Limpar</a><?php endif; ?>
    </form>
    <?php if ($linhas): ?>
    <div class="tabela-wrap">
        <table>
            <thead><tr><th>Data</th><th>Produto</th><th class="num">Qtd.</th><th class="num ocultar-movel">Venda</th><th class="num">Lucro</th></tr></thead>
            <tbody>
            <?php foreach ($linhas as $row): ?>
                <tr>
                    <td class="num" style="text-align:left"><?= data_tabela($row['data_saida']) ?></td>
                    <td><?= h($row['produto']) ?></td>
                    <td class="num"><?= (int) $row['quantidade'] ?></td>
                    <td class="num ocultar-movel"><?= brl($row['valor_total']) ?></td>
                    <td class="num <?= $row['lucro'] < 0 ? 'valor-saida' : 'valor-entrada' ?>"><?= brl($row['lucro']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr><td colspan="3">Total</td><td class="num ocultar-movel"><?= brl($vendido) ?></td><td class="num"><?= brl($lucro_total) ?></td></tr></tfoot>
        </table>
    </div>
    <?php else: ?>
        <div class="vazio"><strong>Nenhuma venda encontrada</strong><?= $filtrado ? 'Tente outro produto ou período.' : '' ?></div>
    <?php endif; ?>
</section>
<?php rodape_pagina(); ?>
