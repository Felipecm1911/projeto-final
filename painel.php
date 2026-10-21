<?php
require 'layout.php';
exigir_login();

// Números do dia
$entradas    = consultar_valor($conn, "SELECT COALESCE(SUM(valor), 0) FROM caixa WHERE tipo = 'entrada'");
$saidas      = consultar_valor($conn, "SELECT COALESCE(SUM(valor), 0) FROM caixa WHERE tipo = 'saida'");
$saldo_caixa = $entradas - $saidas;
$vendas_hoje  = consultar_valor($conn, "SELECT COALESCE(SUM(valor_total), 0) FROM pedidos WHERE DATE(data_pedido) = CURDATE()");
$pedidos_hoje = consultar_valor($conn, "SELECT COUNT(*) FROM pedidos WHERE DATE(data_pedido) = CURDATE()");
$pedidos_mes  = consultar_valor($conn, "SELECT COUNT(*) FROM pedidos WHERE YEAR(data_pedido) = YEAR(CURDATE()) AND MONTH(data_pedido) = MONTH(CURDATE())");
$vendas_mes   = consultar_valor($conn, "SELECT COALESCE(SUM(valor_total), 0) FROM pedidos WHERE YEAR(data_pedido) = YEAR(CURDATE()) AND MONTH(data_pedido) = MONTH(CURDATE())");
$qtd_baixo    = consultar_valor($conn, "SELECT COUNT(*) FROM produtos WHERE estoque < " . ESTOQUE_MINIMO);

// Vendas dos últimos 7 dias
$por_dia = [];
foreach (consultar_linhas($conn, "SELECT DATE(data_pedido) AS dia, SUM(valor_total) AS total FROM pedidos
                                  WHERE data_pedido >= CURDATE() - INTERVAL 6 DAY GROUP BY DATE(data_pedido)") as $l) {
    $por_dia[$l['dia']] = (float) $l['total'];
}
$dias_semana = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
$hoje_banco = consultar_valor($conn, "SELECT CURDATE()");
$semana = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("$hoje_banco -$i day"));
    $semana[] = ['dia' => $d, 'rotulo' => $dias_semana[date('w', strtotime($d))], 'total' => $por_dia[$d] ?? 0];
}
$maior = max(array_column($semana, 'total')) ?: 1;
$total_semana = array_sum(array_column($semana, 'total'));
$melhor = array_reduce($semana, fn($a, $b) => $b['total'] > ($a['total'] ?? -1) ? $b : $a);

$ultimos = consultar_linhas($conn, "SELECT p.id, c.nome AS cliente, p.valor_total, p.data_pedido
                                    FROM pedidos p JOIN clientes c ON p.cliente_id = c.id
                                    ORDER BY p.id DESC LIMIT 6");
$baixo = consultar_linhas($conn, "SELECT nome, estoque FROM produtos WHERE estoque < " . ESTOQUE_MINIMO . " ORDER BY estoque ASC LIMIT 6");

$hora = (int) date('H');
$saudacao = $hora < 12 ? 'Bom dia' : ($hora < 18 ? 'Boa tarde' : 'Boa noite');

cabecalho_pagina('Painel', 'painel.php');

titulo_pagina(
    $saudacao . ', ' . ($_SESSION['usuario'] ?? ''),
    'Resumo da loja em ' . date('d/m/Y') . '.',
    (eh_caixa() ? '' : '<a class="botao secundario" href="relatorio_excel.php">Exportar Excel</a>')
        . '<a class="botao" href="novo_pedido.php">Novo pedido</a>'
);
?>

<section class="caixa-destaque" aria-label="Saldo do caixa e vendas da semana">
    <div class="saldo">
        <h2>Saldo do caixa</h2>
        <strong class="saldo-valor"><small>R$</small><span data-contar="<?= (float) $saldo_caixa ?>" data-moeda><?= substr(brl($saldo_caixa), 3) ?></span></strong>
        <dl class="saldo-partes">
            <div><dt>Entradas</dt><dd data-contar="<?= (float) $entradas ?>" data-moeda="R$ "><?= brl($entradas) ?></dd></div>
            <div><dt>Saídas</dt><dd data-contar="<?= (float) $saidas ?>" data-moeda="R$ "><?= brl($saidas) ?></dd></div>
        </dl>
        <p><a href="caixa.php">Abrir o caixa</a></p>
    </div>
    <div class="semana">
        <h2>Vendas dos últimos 7 dias</h2>
        <p class="grafico-resumo">
            <strong><?= brl($total_semana) ?></strong> na semana<?php if ($total_semana > 0): ?>,
            melhor dia <?= h(strtolower($melhor['rotulo'])) ?> (<?= data_br($melhor['dia'], false) ?>)<?php endif; ?>.
            <?php if (!eh_caixa()): ?><a href="historico_venda.php">Ver histórico</a><?php endif; ?>
        </p>
        <div class="grafico" role="img" aria-label="Gráfico de vendas por dia">
            <?php foreach ($semana as $i => $d): ?>
                <div class="barra-dia<?= $i == 6 ? ' hoje' : '' ?>">
                    <b><?= $d['total'] > 0 ? number_format($d['total'], 0, ',', '.') : '' ?></b>
                    <i style="height: <?= round($d['total'] / $maior * 130) ?>px; animation-delay: <?= 200 + $i * 60 ?>ms"></i>
                    <span><?= $i == 6 ? 'Hoje' : $d['rotulo'] ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="numeros" aria-label="Vendas e estoque">
    <div>
        <h2>Vendas hoje</h2>
        <strong data-contar="<?= (float) $vendas_hoje ?>" data-moeda="R$ "><?= brl($vendas_hoje) ?></strong>
        <span><?= (int) $pedidos_hoje ?> <?= $pedidos_hoje == 1 ? 'pedido' : 'pedidos' ?></span>
    </div>
    <div>
        <h2>Vendas no mês</h2>
        <strong data-contar="<?= (float) $vendas_mes ?>" data-moeda="R$ "><?= brl($vendas_mes) ?></strong>
        <span><?= (int) $pedidos_mes ?> <?= $pedidos_mes == 1 ? 'pedido' : 'pedidos' ?></span>
    </div>
    <div<?= $qtd_baixo > 0 ? ' class="alerta"' : '' ?>>
        <h2>Estoque baixo</h2>
        <strong data-contar="<?= (int) $qtd_baixo ?>"><?= (int) $qtd_baixo ?></strong>
        <span><?= $qtd_baixo == 1 ? 'produto' : 'produtos' ?> com menos de <?= ESTOQUE_MINIMO ?> unidades</span>
    </div>
</section>

<div class="grade grade-2">
    <section class="painel">
        <div class="painel-topo">
            <h2>Últimos pedidos</h2>
            <a href="listar_pedidos.php">Ver todos</a>
        </div>
        <?php if ($ultimos): ?>
            <div class="tabela-wrap">
                <table>
                    <thead><tr><th>Pedido</th><th>Cliente</th><th class="ocultar-movel">Data</th><th class="num">Total</th></tr></thead>
                    <tbody>
                    <?php foreach ($ultimos as $p): ?>
                        <tr>
                            <td class="num" style="text-align:left">#<?= (int) $p['id'] ?></td>
                            <td><?= h($p['cliente']) ?></td>
                            <td class="ocultar-movel"><?= data_tabela($p["data_pedido"]) ?></td>
                            <td class="num"><?= brl($p['valor_total']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="vazio"><strong>Nenhum pedido ainda</strong><a href="novo_pedido.php">Criar o primeiro pedido</a></div>
        <?php endif; ?>
    </section>

    <section class="painel">
        <div class="painel-topo">
            <h2>Precisa repor</h2>
            <a href="listar_produtos.php">Produtos</a>
        </div>
        <?php if ($baixo): ?>
            <div class="tabela-wrap">
                <table>
                    <thead><tr><th>Produto</th><th class="num">Qtd.</th><th>Situação</th></tr></thead>
                    <tbody>
                    <?php foreach ($baixo as $p): ?>
                        <tr>
                            <td><?= h($p['nome']) ?></td>
                            <td class="num"><?= (int) $p['estoque'] ?></td>
                            <td><?= situacao_estoque($p['estoque']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="vazio"><strong>Estoque em dia</strong>Nenhum produto abaixo de <?= ESTOQUE_MINIMO ?> unidades.</div>
        <?php endif; ?>
    </section>
</div>

<?php rodape_pagina(); ?>
