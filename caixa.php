<?php
require 'layout.php';
exigir_login();

$result = $conn->query("SELECT * FROM caixa ORDER BY data_movimentacao DESC");
$linhas = $result->fetch_all(MYSQLI_ASSOC);

$entradas = 0; $saidas = 0;
foreach ($linhas as $l) {
    if ($l['tipo'] == 'entrada') $entradas += $l['valor']; else $saidas += $l['valor'];
}

cabecalho_pagina('Caixa', 'caixa.php');
titulo_pagina(
    'Movimentações de caixa',
    'Vendas entram aqui automaticamente quando um pedido é criado.',
    '<a class="botao secundario" href="registrar_saida_caixa.php">Registrar saída</a><a class="botao" href="registrar_entrada_caixa.php">Registrar entrada</a>'
);
?>
<section class="indicadores tres" aria-label="Resumo do caixa">
    <div class="indicador destaque"><span>Saldo</span><strong><?= brl($entradas - $saidas) ?></strong></div>
    <div class="indicador"><span>Entradas</span><strong class="valor-entrada"><?= brl($entradas) ?></strong></div>
    <div class="indicador"><span>Saídas</span><strong class="valor-saida"><?= brl($saidas) ?></strong></div>
</section>

<section class="painel">
    <div class="filtros">
        <div class="campo busca">
            <label for="busca">Buscar</label>
            <input type="search" id="busca" data-filtrar="tabela" placeholder="Descrição">
        </div>
    </div>
    <?php if ($linhas): ?>
    <div class="tabela-wrap">
        <table id="tabela">
            <thead><tr><th>Data</th><th>Descrição</th><th class="ocultar-movel">Tipo</th><th class="num">Valor</th></tr></thead>
            <tbody>
            <?php foreach ($linhas as $row): $entrada = $row['tipo'] == 'entrada'; ?>
                <tr>
                    <td class="num" style="text-align:left"><?= data_tabela($row['data_movimentacao']) ?></td>
                    <td><?= h($row['descricao']) ?></td>
                    <td class="ocultar-movel"><span class="etiqueta <?= $entrada ? 'ok' : 'zerado' ?>"><?= $entrada ? 'Entrada' : 'Saída' ?></span></td>
                    <td class="num <?= $entrada ? 'valor-entrada' : 'valor-saida' ?>"><?= ($entrada ? '+ ' : '− ') . brl($row['valor']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <div class="vazio"><strong>Nenhuma movimentação</strong>Registre uma entrada para abrir o caixa.</div>
    <?php endif; ?>
</section>
<?php rodape_pagina(); ?>
