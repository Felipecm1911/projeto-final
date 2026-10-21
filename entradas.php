<?php
require 'layout.php';
exigir_admin();

$res = $conn->query("SELECT * FROM entradas_detalhes ORDER BY data_entrada DESC");
$linhas = $res->fetch_all(MYSQLI_ASSOC);
$total = array_sum(array_column($linhas, 'quantidade'));

cabecalho_pagina('Entradas de produtos', 'entradas.php');
titulo_pagina('Entradas de produtos', number_format($total, 0, ',', '.') . ' unidades recebidas em ' . count($linhas) . ' entradas.', '<a class="botao" href="registrar_entrada.php">Registrar entrada</a>');
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
            <thead><tr><th>Data</th><th>Produto</th><th class="num">Quantidade</th></tr></thead>
            <tbody>
            <?php foreach ($linhas as $row): ?>
                <tr>
                    <td class="num" style="text-align:left"><?= data_tabela($row['data_entrada']) ?></td>
                    <td><strong><?= h($row['produto']) ?></strong></td>
                    <td class="num valor-entrada">+ <?= (int) $row['quantidade'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <div class="vazio"><strong>Nenhuma entrada registrada</strong></div>
    <?php endif; ?>
</section>
<?php rodape_pagina(); ?>
