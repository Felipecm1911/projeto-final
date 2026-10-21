<?php
require 'layout.php';
exigir_admin();

// Filtros
$where = "1";
$params = [];
$types = "";

if (!empty($_GET['cliente'])) {
    $where .= " AND c.nome LIKE ?";
    $params[] = "%" . $_GET['cliente'] . "%";
    $types .= "s";
}
filtro_datas('s.data_saida', $where, $params, $types);

$sql = "SELECT s.id, c.nome AS cliente, p.nome AS produto, s.quantidade, s.valor_total, s.data_saida
        FROM saidas s
        JOIN produtos p ON s.produto_id = p.id
        JOIN clientes c ON s.cliente_id = c.id
        WHERE $where ORDER BY s.data_saida DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$linhas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$total = array_sum(array_column($linhas, 'valor_total'));
$filtrado = !empty($params);

cabecalho_pagina('Histórico de venda', 'historico_venda.php');
titulo_pagina('Histórico de venda', count($linhas) . ' vendas' . ($filtrado ? ' no filtro' : '') . ', ' . brl($total) . '.');
?>
<section class="painel">
    <form method="get" class="filtros">
        <div class="campo">
            <label for="cliente">Cliente</label>
            <input type="text" id="cliente" name="cliente" value="<?= h($_GET['cliente'] ?? '') ?>" placeholder="Nome do cliente">
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
        <?php if ($filtrado): ?><a class="botao secundario" href="historico_venda.php">Limpar</a><?php endif; ?>
    </form>
    <?php if ($linhas): ?>
    <div class="tabela-wrap">
        <table>
            <thead><tr><th>Data</th><th>Cliente</th><th>Produto</th><th class="num ocultar-movel">Qtd.</th><th class="num">Valor</th></tr></thead>
            <tbody>
            <?php foreach ($linhas as $row): ?>
                <tr>
                    <td class="num" style="text-align:left"><?= data_tabela($row['data_saida']) ?></td>
                    <td><?= h($row['cliente']) ?></td>
                    <td><?= h($row['produto']) ?></td>
                    <td class="num ocultar-movel"><?= (int) $row["quantidade"] ?></td>
                    <td class="num"><?= brl($row['valor_total']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr><td colspan="3">Total</td><td class="ocultar-movel"></td><td class="num"><?= brl($total) ?></td></tr></tfoot>
        </table>
    </div>
    <?php else: ?>
        <div class="vazio"><strong>Nenhuma venda encontrada</strong><?= $filtrado ? 'Tente outro cliente ou período.' : 'As vendas aparecem aqui quando um pedido é criado.' ?></div>
    <?php endif; ?>
</section>
<?php rodape_pagina(); ?>
