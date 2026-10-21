<?php
// Monta o relatório geral em Excel (.xlsx) do sistema inteiro.
// Usado por relatorio_excel.php (download) e por testes/teste_relatorio.php.
// Os totais são fórmulas: se alguém mudar um valor na planilha, o resumo recalcula.
require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Table;
use PhpOffice\PhpSpreadsheet\Worksheet\Table\TableStyle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;

// Cores do sistema (as mesmas do estilo.css)
const COR_FOLHA = '18270F';
const COR_ERVA = 'B7C93B';
const COR_ERVA_CLARA = 'EDF2D2';
const COR_VERDE_TEXTO = '3D5A14';
const COR_CUIA = '8A4A14';
const COR_CUIA_CLARA = 'F5E8DA';
const COR_PERIGO = 'A3261B';
const COR_PERIGO_CLARA = 'FAE6E2';
const COR_CINZA = '4B5642';
const COR_FOLHA_TINTA = 'C4CFB8';

const FMT_BRL = '"R$" #,##0.00;[Red]-"R$" #,##0.00;"-"';
const FMT_DATA_HORA = 'dd/mm/yyyy hh:mm';
const FMT_PCT = '0.0%;[Red]-0.0%;"-"';
const FMT_INT = '#,##0;-#,##0;"-"';

const LINHA_CABECALHO = 4; // linha do cabeçalho das tabelas nas abas de dados (abaixo da faixa do topo)

function montar_relatorio(mysqli $conn, string $usuario, int $estoque_minimo): Spreadsheet {
    $q = fn($sql) => $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

    $vendas = $q("SELECT s.data_saida, c.nome AS cliente, p.nome AS produto, s.quantidade, s.valor_total,
                         COALESCE(s.preco_compra, p.preco_compra) AS custo
                  FROM saidas s JOIN produtos p ON p.id = s.produto_id JOIN clientes c ON c.id = s.cliente_id
                  ORDER BY s.data_saida, s.id");
    $pedidos = $q("SELECT p.id, p.data_pedido, p.cliente_id, c.nome AS cliente, p.valor_total,
                          (SELECT GROUP_CONCAT(CONCAT(pr.nome, ' × ', i.quantidade) SEPARATOR ', ')
                           FROM pedido_itens i JOIN produtos pr ON pr.id = i.produto_id WHERE i.pedido_id = p.id) AS itens
                   FROM pedidos p JOIN clientes c ON c.id = p.cliente_id ORDER BY p.id");
    $produtos = $q("SELECT p.nome, p.descricao, p.preco_compra, p.preco_venda, p.estoque,
                           COALESCE((SELECT SUM(s.valor_total) FROM saidas s WHERE s.produto_id = p.id), 0) AS vendido
                    FROM produtos p ORDER BY p.nome");
    $caixa = $q("SELECT data_movimentacao, tipo, descricao, valor FROM caixa ORDER BY data_movimentacao, id");
    $clientes = $q("SELECT id, nome, cpf, telefone, email FROM clientes ORDER BY nome");
    $entradas = $q("SELECT e.data_entrada, p.nome AS produto, f.nome AS fornecedor, e.quantidade
                    FROM entradas e JOIN produtos p ON p.id = e.produto_id
                    LEFT JOIN fornecedores f ON f.id = e.fornecedor_id ORDER BY e.data_entrada, e.id");
    $fornecedores = $q("SELECT nome, cnpj, telefone, email FROM fornecedores ORDER BY nome");

    $planilha = new Spreadsheet();
    $planilha->getDefaultStyle()->getFont()->setName('Arial')->setSize(10)->getColor()->setRGB('16210E');
    $planilha->getProperties()->setCreator('Erva Mate')->setTitle('Relatório geral · Erva Mate')
        ->setSubject('Vendas, estoque e caixa');

    $resumo = $planilha->getActiveSheet()->setTitle('Resumo');

    // ---------- Abas de dados ----------

    // Nas abas de dados a coluna A é só do logo; as tabelas começam na coluna B.

    $ws = nova_aba($planilha, 'Vendas', 'Vendas', 'Cada produto vendido, com o custo da época e o lucro.');
    $fim_v = tabela($ws, 'tVendas', [
        ['Data', 17, FMT_DATA_HORA], ['Cliente', 26], ['Produto', 30], ['Qtd.', 8, FMT_INT],
        ['Valor da venda', 16, FMT_BRL], ['Custo unitário', 15, FMT_BRL], ['Custo total', 15, FMT_BRL],
        ['Lucro', 15, FMT_BRL], ['Margem', 10, FMT_PCT],
    ], array_map(fn($v) => [data_excel($v['data_saida']), $v['cliente'], $v['produto'], (int) $v['quantidade'],
        (float) $v['valor_total'], (float) $v['custo'], '=E{r}*G{r}', '=F{r}-H{r}', '=IFERROR(I{r}/F{r},0)'], $vendas));
    linha_total($ws, $fim_v, ['E' => FMT_INT, 'F' => FMT_BRL, 'H' => FMT_BRL, 'I' => FMT_BRL], 'J', '=IFERROR(I{t}/F{t},0)');

    $ws = nova_aba($planilha, 'Pedidos', 'Pedidos', 'Todos os pedidos com os itens de cada um.');
    $fim_p = tabela($ws, 'tPedidos', [
        ['Pedido', 9, '0'], ['Data', 17, FMT_DATA_HORA], ['Cód. cliente', 12, '0'], ['Cliente', 26],
        ['Itens', 44], ['Total', 15, FMT_BRL],
    ], array_map(fn($p) => [(int) $p['id'], data_excel($p['data_pedido']), (int) $p['cliente_id'], $p['cliente'],
        $p['itens'], (float) $p['valor_total']], $pedidos));
    linha_total($ws, $fim_p, ['G' => FMT_BRL]);

    $ws = nova_aba($planilha, 'Produtos', 'Produtos e estoque', 'Preços, margem e quanto o estoque vale hoje.');
    $fim_pr = tabela($ws, 'tProdutos', [
        ['Produto', 30], ['Descrição', 30], ['Preço de compra', 15, FMT_BRL], ['Preço de venda', 15, FMT_BRL],
        ['Estoque', 10, FMT_INT], ['Margem', 10, FMT_PCT], ['Estoque a custo', 16, FMT_BRL],
        ['Estoque a preço de venda', 18, FMT_BRL], ['Lucro estocado', 16, FMT_BRL], ['Situação', 14],
    ], array_map(fn($p) => [$p['nome'], $p['descricao'], (float) $p['preco_compra'], (float) $p['preco_venda'],
        (int) $p['estoque'], '=IFERROR((E{r}-D{r})/E{r},0)', '=D{r}*F{r}', '=E{r}*F{r}', '=I{r}-H{r}',
        '=IF(F{r}<=0,"Sem estoque",IF(F{r}<Resumo!$C$13,"Baixo","Normal"))'], $produtos));
    linha_total($ws, $fim_pr, ['F' => FMT_INT, 'H' => FMT_BRL, 'I' => FMT_BRL, 'J' => FMT_BRL]);
    cores_situacao($ws, 'K' . (LINHA_CABECALHO + 1) . ':K' . $fim_pr);
    if ($produtos) {
        grafico_barras($ws, 'grEstoque', 'Estoque por produto (unidades)', 'Produtos',
            'B', 'F', LINHA_CABECALHO + 1, $fim_pr, [COR_ERVA], 'M' . LINHA_CABECALHO, 'T' . max(LINHA_CABECALHO + 18, $fim_pr + 2), true);
    }

    $ws = nova_aba($planilha, 'Caixa', 'Caixa', 'Entradas e saídas em ordem de data, com o saldo depois de cada uma.');
    $fim_c = tabela($ws, 'tCaixa', [
        ['Data', 17, FMT_DATA_HORA], ['Tipo', 10], ['Descrição', 44], ['Valor', 15, FMT_BRL],
        ['Valor com sinal', 16, FMT_BRL], ['Saldo', 16, FMT_BRL],
    ], array_map(fn($c) => [data_excel($c['data_movimentacao']), $c['tipo'] === 'entrada' ? 'Entrada' : 'Saída',
        $c['descricao'], (float) $c['valor'], '=IF(C{r}="Entrada",E{r},-E{r})',
        '=SUM($F$' . (LINHA_CABECALHO + 1) . ':F{r})'], $caixa));
    linha_total($ws, $fim_c, ['F' => FMT_BRL]);
    cores_tipo($ws, 'C' . (LINHA_CABECALHO + 1) . ':C' . $fim_c);

    $ws = nova_aba($planilha, 'Clientes', 'Clientes', 'Contato de cada cliente e quanto ele já comprou.');
    $ped = fn($col) => "Pedidos!\${$col}\$" . (LINHA_CABECALHO + 1) . ":\${$col}\$" . $fim_p;
    $fim_cl = tabela($ws, 'tClientes', [
        ['Cód.', 7, '0'], ['Nome', 28], ['CPF', 16], ['Telefone', 16], ['E-mail', 28],
        ['Pedidos', 10, FMT_INT], ['Total comprado', 16, FMT_BRL], ['Ticket médio', 15, FMT_BRL],
    ], array_map(fn($c) => [(int) $c['id'], $c['nome'], $c['cpf'], $c['telefone'], $c['email'],
        '=COUNTIF(' . $ped('D') . ',B{r})', '=SUMIF(' . $ped('D') . ',B{r},' . $ped('G') . ')',
        '=IFERROR(H{r}/G{r},0)'], $clientes));
    linha_total($ws, $fim_cl, ['G' => FMT_INT, 'H' => FMT_BRL]);

    $ws = nova_aba($planilha, 'Entradas', 'Entradas de estoque', 'Mercadoria recebida dos fornecedores.');
    $fim_e = tabela($ws, 'tEntradas', [
        ['Data', 17, FMT_DATA_HORA], ['Produto', 30], ['Fornecedor', 30], ['Quantidade', 12, FMT_INT],
    ], array_map(fn($e) => [data_excel($e['data_entrada']), $e['produto'], $e['fornecedor'] ?? 'Sem fornecedor',
        (int) $e['quantidade']], $entradas));
    linha_total($ws, $fim_e, ['E' => FMT_INT]);

    $ws = nova_aba($planilha, 'Fornecedores', 'Fornecedores', 'Contato e mercadoria recebida de cada fornecedor.');
    $ent = fn($col) => "Entradas!\${$col}\$" . (LINHA_CABECALHO + 1) . ":\${$col}\$" . $fim_e;
    tabela($ws, 'tFornecedores', [
        ['Nome', 32], ['CNPJ', 20], ['Telefone', 16], ['E-mail', 28],
        ['Entradas', 10, FMT_INT], ['Unidades recebidas', 18, FMT_INT],
    ], array_map(fn($f) => [$f['nome'], $f['cnpj'], $f['telefone'], $f['email'],
        '=COUNTIF(' . $ent('D') . ',B{r})', '=SUMIF(' . $ent('D') . ',B{r},' . $ent('E') . ')'], $fornecedores));

    // ---------- Resumo ----------

    montar_resumo($resumo, $usuario, $estoque_minimo, $produtos, [
        'v' => $fim_v, 'p' => $fim_p, 'pr' => $fim_pr, 'c' => $fim_c, 'cl' => $fim_cl,
    ]);
    $planilha->setActiveSheetIndex(0);
    return $planilha;
}

function montar_resumo(Worksheet $ws, string $usuario, int $estoque_minimo, array $produtos, array $fim): void {
    $ws->setShowGridlines(false);
    $ws->getTabColor()->setRGB(COR_FOLHA);
    $ws->getColumnDimension('A')->setWidth(11); // coluna só do logo, à esquerda do título
    foreach (range('B', 'K') as $c) $ws->getColumnDimension($c)->setWidth(15);
    faixa_topo($ws, 'Relatório geral', 'Erva Mate · gestão da loja', 'K');

    $ws->setCellValue('B4', 'Gerado em ' . date('d/m/Y \à\s H:i') . ' por ' . $usuario
        . '. Os números abaixo são fórmulas que leem as outras abas.');
    $ws->getStyle('B4')->getFont()->setColor(cor(COR_CINZA));

    $ini = LINHA_CABECALHO + 1;
    // Faixa de uma coluna de outra aba. $col é a letra dentro da tabela (A = 1ª coluna);
    // soma 1 porque nas abas de dados a coluna A é a do logo e a tabela começa em B.
    $r = function ($aba, $col, $ult) use ($ini) {
        $c = chr(ord($col) + 1);
        return "$aba!\${$c}\${$ini}:\${$c}\${$ult}";
    };

    // Números principais: rótulo na linha de cima, valor embaixo; cada cartão ocupa 2 colunas
    $cartoes = [
        ['B6', 'Saldo do caixa', '=SUM(' . $r('Caixa', 'E', $fim['c']) . ')', FMT_BRL, true],
        ['D6', 'Total vendido', '=SUM(' . $r('Vendas', 'E', $fim['v']) . ')', FMT_BRL],
        ['F6', 'Lucro bruto', '=SUM(' . $r('Vendas', 'H', $fim['v']) . ')', FMT_BRL],
        ['H6', 'Margem média', '=IFERROR(F7/D7,0)', FMT_PCT],
        ['J6', 'Clientes', '=COUNT(' . $r('Clientes', 'A', $fim['cl']) . ')', FMT_INT],
        ['B9', 'Pedidos', '=COUNT(' . $r('Pedidos', 'A', $fim['p']) . ')', FMT_INT],
        ['D9', 'Ticket médio', '=IFERROR(SUM(' . $r('Pedidos', 'F', $fim['p']) . ')/B10,0)', FMT_BRL],
        ['F9', 'Estoque a custo', '=SUM(' . $r('Produtos', 'G', $fim['pr']) . ')', FMT_BRL],
        ['H9', 'Estoque a preço de venda', '=SUM(' . $r('Produtos', 'H', $fim['pr']) . ')', FMT_BRL],
        ['J9', 'Produtos para repor', '=COUNTIF(' . $r('Produtos', 'J', $fim['pr']) . ',"Baixo")+COUNTIF('
            . $r('Produtos', 'J', $fim['pr']) . ',"Sem estoque")', FMT_INT],
    ];
    foreach ($cartoes as [$cel, $rotulo, $formula, $fmt]) {
        $col = preg_replace('/\d/', '', $cel);
        $lin = (int) preg_replace('/\D/', '', $cel);
        $col2 = chr(ord($col) + 1);
        $destaque = $cel === 'B6';
        $ws->mergeCells("$col$lin:$col2$lin");
        $ws->mergeCells($col . ($lin + 1) . ':' . $col2 . ($lin + 1));
        $ws->setCellValue("$col$lin", $rotulo);
        $ws->setCellValue($col . ($lin + 1), $formula);
        $bloco = "$col$lin:$col2" . ($lin + 1);
        $ws->getStyle($bloco)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($destaque ? COR_FOLHA : COR_ERVA_CLARA);
        $ws->getStyle($bloco)->getAlignment()->setIndent(1);
        $ws->getStyle($bloco)->getBorders()->getRight()->setBorderStyle('thick')->getColor()->setRGB('FFFFFF');
        $ws->getStyle("$col$lin")->getFont()->setColor(cor($destaque ? COR_FOLHA_TINTA : COR_CINZA));
        $ws->getStyle("$col$lin")->getAlignment()->setVertical(Alignment::VERTICAL_BOTTOM);
        $valor = $ws->getStyle($col . ($lin + 1));
        $valor->getFont()->setSize(18)->setBold(true)->setColor(cor($destaque ? COR_ERVA : COR_FOLHA));
        $valor->getNumberFormat()->setFormatCode($fmt);
        $valor->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_TOP);
        $ws->getRowDimension($lin)->setRowHeight(22);
        $ws->getRowDimension($lin + 1)->setRowHeight(32);
    }
    $ws->getRowDimension(8)->setRowHeight(8); // espaço entre as duas fileiras de cartões

    // Premissas usadas pelas fórmulas
    $ws->setCellValue('B12', 'Premissas');
    $ws->getStyle('B12')->getFont()->setBold(true)->setColor(cor(COR_FOLHA));
    $ws->setCellValue('B13', 'Estoque mínimo');
    $ws->setCellValue('C13', $estoque_minimo);
    $ws->getStyle('C13')->getFont()->setBold(true)->setColor(cor('0000FF'));
    $ws->getStyle('C13')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    $ws->getComment('C13')->getText()->createText('Vem da tela Configurações do sistema. Abaixo deste número o produto aparece como "Baixo" na aba Produtos. Pode mudar aqui para simular.');
    $ws->setCellValue('D13', 'Abaixo disso o produto entra em "Produtos para repor". Pode alterar para simular.');
    $ws->setCellValue('B14', 'Lucro');
    $ws->setCellValue('D14', 'Valor da venda menos o custo gravado no dia da venda; vendas antigas sem esse registro usam o custo atual.');
    $ws->getStyle('B13:B14')->getFont()->setColor(cor(COR_CINZA));
    $ws->getStyle('D13:D14')->getFont()->setColor(cor(COR_CINZA))->setItalic(true);

    // ---------- Vendas por mês (últimos 12 meses) ----------
    $lin = 17;
    titulo_secao($ws, "B$lin", 'Vendas por mês (últimos 12 meses)');
    $cab = $lin + 1;
    cabecalho($ws, $cab, ['B' => 'Mês', 'C' => 'Vendas', 'D' => 'Lucro', 'E' => 'Pedidos']);
    $meses = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
    $vData = $r('Vendas', 'A', $fim['v']);
    $pData = $r('Pedidos', 'B', $fim['p']);
    for ($i = 11; $i >= 0; $i--) {
        $linha = $cab + 12 - $i;
        $t = strtotime(date('Y-m-01') . " -$i month");
        $ano = (int) date('Y', $t); $mes = (int) date('n', $t);
        $de = "\">=\"&DATE($ano,$mes,1)";
        $ate = "\"<\"&DATE($ano," . ($mes + 1) . ",1)"; // DATE aceita mês 13 (vira janeiro do ano seguinte)
        $ws->setCellValue("B$linha", $meses[$mes - 1] . '/' . $ano);
        $ws->setCellValue("C$linha", '=SUMIFS(' . $r('Vendas', 'E', $fim['v']) . ",$vData,$de,$vData,$ate)");
        $ws->setCellValue("D$linha", '=SUMIFS(' . $r('Vendas', 'H', $fim['v']) . ",$vData,$de,$vData,$ate)");
        $ws->setCellValue("E$linha", "=COUNTIFS($pData,$de,$pData,$ate)");
    }
    $ultMes = $cab + 12;
    $tot = $ultMes + 1;
    $ws->setCellValue("B$tot", 'Total');
    foreach (['C', 'D', 'E'] as $c) $ws->setCellValue("$c$tot", "=SUM({$c}" . ($cab + 1) . ":{$c}$ultMes)");
    estilo_bloco($ws, $cab + 1, $tot, ['C' => FMT_BRL, 'D' => FMT_BRL, 'E' => FMT_INT], 'B', 'E');
    grafico_colunas($ws, 'grMes', 'Vendas e lucro por mês', 'Resumo', $cab, $cab + 1, $ultMes,
        ['C' => COR_ERVA, 'D' => COR_FOLHA], 'G' . $lin, 'K' . ($tot + 1));

    // ---------- Vendas por produto ----------
    $lin = $tot + 3;
    titulo_secao($ws, "B$lin", 'Vendas por produto');
    $cab = $lin + 1;
    cabecalho($ws, $cab, ['B' => 'Produto', 'C' => '', 'D' => 'Qtd. vendida', 'E' => 'Valor vendido', 'F' => 'Lucro']);
    usort($produtos, fn($a, $b) => $b['vendido'] <=> $a['vendido']);
    $vProd = $r('Vendas', 'C', $fim['v']);
    foreach ($produtos as $i => $p) {
        $linha = $cab + 1 + $i;
        $ws->mergeCells("B$linha:C$linha");
        $ws->setCellValue("B$linha", $p['nome']);
        $ws->setCellValue("D$linha", "=SUMIF($vProd,B$linha," . $r('Vendas', 'D', $fim['v']) . ')');
        $ws->setCellValue("E$linha", "=SUMIF($vProd,B$linha," . $r('Vendas', 'E', $fim['v']) . ')');
        $ws->setCellValue("F$linha", "=SUMIF($vProd,B$linha," . $r('Vendas', 'H', $fim['v']) . ')');
    }
    $ultProd = $cab + max(1, count($produtos));
    $ws->mergeCells("B$cab:C$cab");
    $tot = $ultProd + 1;
    $ws->mergeCells("B$tot:C$tot");
    $ws->setCellValue("B$tot", 'Total');
    foreach (['D', 'E', 'F'] as $c) $ws->setCellValue("$c$tot", "=SUM({$c}" . ($cab + 1) . ":{$c}$ultProd)");
    estilo_bloco($ws, $cab + 1, $tot, ['D' => FMT_INT, 'E' => FMT_BRL, 'F' => FMT_BRL], 'B', 'F');
    if ($produtos) {
        grafico_barras($ws, 'grProduto', 'Valor vendido por produto', 'Resumo', 'B', 'E', $cab + 1, $ultProd,
            [COR_ERVA], 'G' . $lin, 'K' . max($tot + 1, $lin + 16), false);
    }

    // ---------- Caixa ----------
    $lin = max($tot, $lin + 16) + 3;
    titulo_secao($ws, "B$lin", 'Entradas e saídas do caixa');
    $cab = $lin + 1;
    cabecalho($ws, $cab, ['B' => 'Tipo', 'C' => 'Total']);
    $cTipo = $r('Caixa', 'B', $fim['c']);
    $cValor = $r('Caixa', 'D', $fim['c']);
    $ws->setCellValue('B' . ($cab + 1), 'Entradas');
    $ws->setCellValue('C' . ($cab + 1), "=SUMIF($cTipo,\"Entrada\",$cValor)");
    $ws->setCellValue('B' . ($cab + 2), 'Saídas');
    $ws->setCellValue('C' . ($cab + 2), "=SUMIF($cTipo,\"Saída\",$cValor)");
    $ws->setCellValue('B' . ($cab + 3), 'Saldo');
    $ws->setCellValue('C' . ($cab + 3), '=C' . ($cab + 1) . '-C' . ($cab + 2));
    estilo_bloco($ws, $cab + 1, $cab + 3, ['C' => FMT_BRL], 'B', 'C');
    grafico_pizza($ws, 'grCaixa', 'Entradas x saídas', 'B' . ($cab + 1) . ':B' . ($cab + 2),
        'C' . ($cab + 1) . ':C' . ($cab + 2), [COR_ERVA, COR_CUIA], 'E' . $lin, 'I' . ($lin + 15));

    configurar_impressao($ws, false);
}

// ---------- Peças reutilizadas ----------

function nova_aba(Spreadsheet $planilha, string $nome, string $titulo, string $sub): Worksheet {
    $ws = $planilha->createSheet()->setTitle($nome);
    $ws->getTabColor()->setRGB(COR_ERVA);
    $ws->setShowGridlines(false);
    $ws->getColumnDimension('A')->setWidth(11); // coluna só do logo; a tabela começa em B
    faixa_topo($ws, $titulo, $sub, null);
    configurar_impressao($ws, true);
    return $ws;
}

// Faixa verde-escura no topo: o logo na coluna A, à esquerda, e o título ao lado dele na coluna B
function faixa_topo(Worksheet $ws, string $titulo, string $sub, ?string $ultima_col): void {
    $ult = $ultima_col ?? 'L';
    $ws->getStyle("A1:{$ult}2")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(COR_FOLHA);
    $ws->getRowDimension(1)->setRowHeight(34);
    $ws->getRowDimension(2)->setRowHeight(20);
    $ws->getRowDimension(3)->setRowHeight(10);

    $ws->setCellValue('B1', $titulo);
    $ws->setCellValue('B2', $sub);
    $ws->getStyle('B1')->getFont()->setSize(18)->setBold(true)->setColor(cor('FFFFFF'));
    $ws->getStyle('B2')->getFont()->setSize(10)->setColor(cor(COR_ERVA));
    $ws->getStyle('B1:B2')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(false);

    $logo = new Drawing();
    $logo->setName('Logo Erva Mate')->setPath(__DIR__ . '/logo-sem-fundo.png')->setHeight(50);
    $logo->setCoordinates('A1')->setOffsetX(14)->setOffsetY(4)->setWorksheet($ws);
}

// Escreve uma tabela a partir da LINHA_CABECALHO, começando na coluna B (a A é a do logo).
// Valores que começam com "=" são fórmulas; {r} vira o número da linha. Devolve a última linha com dados.
function tabela(Worksheet $ws, string $nome, array $colunas, array $linhas, ): int {
    $cab = LINHA_CABECALHO;
    $letra = fn($i) => Coordinate::stringFromColumnIndex($i + 2); // índice 0 → coluna B
    $ultCol = $letra(count($colunas) - 1);
    foreach ($colunas as $i => $c) {
        $ws->setCellValue($letra($i) . $cab, $c[0]);
        $ws->getColumnDimension($letra($i))->setWidth($c[1]);
    }
    estilo_cabecalho($ws, "B$cab:$ultCol$cab");

    foreach ($linhas as $n => $valores) {
        $r = $cab + 1 + $n;
        foreach ($valores as $i => $v) {
            if (is_string($v) && str_starts_with($v, '=')) $v = str_replace('{r}', (string) $r, $v);
            $ws->setCellValue($letra($i) . $r, $v);
        }
    }
    $fim = $cab + max(1, count($linhas));
    foreach ($colunas as $i => $c) {
        if (!empty($c[2])) {
            $ws->getStyle($letra($i) . ($cab + 1) . ':' . $letra($i) . $fim)->getNumberFormat()->setFormatCode($c[2]);
        }
    }
    $ws->getStyle('B' . ($cab + 1) . ":$ultCol$fim")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

    if ($linhas) {
        // Tabela do Excel: filtro em cada coluna e linhas listradas
        $t = new Table("B$cab:$ultCol$fim", $nome);
        $t->setStyle((new TableStyle())->setTheme(TableStyle::TABLE_STYLE_LIGHT15)->setShowRowStripes(true));
        $ws->addTable($t);
    } else {
        $ws->setCellValue('B' . ($cab + 1), 'Nenhum registro.');
        $ws->getStyle('B' . ($cab + 1))->getFont()->setItalic(true)->setColor(cor(COR_CINZA));
    }
    $ws->freezePane('A' . ($cab + 1));
    $ws->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd($cab, $cab);
    return $fim;
}

// Linha de totais logo abaixo da tabela (fora dela, para não atrapalhar os filtros)
// $somas: coluna => formato do total
function linha_total(Worksheet $ws, int $fim, array $somas, ?string $col_extra = null, ?string $formula_extra = null): void {
    $t = $fim + 1;
    $ini = LINHA_CABECALHO + 1;
    $ws->setCellValue("B$t", 'Total');
    foreach ($somas as $col => $formato) {
        $ws->setCellValue("$col$t", "=SUM($col$ini:$col$fim)");
        $ws->getStyle("$col$t")->getNumberFormat()->setFormatCode($formato);
    }
    if ($col_extra) {
        $ws->setCellValue("$col_extra$t", str_replace('{t}', (string) $t, $formula_extra));
        $ws->getStyle("$col_extra$t")->getNumberFormat()->setFormatCode(FMT_PCT);
    }
    $ultCol = $ws->getHighestColumn(LINHA_CABECALHO);
    $faixa = $ws->getStyle("B$t:$ultCol$t");
    $faixa->getFont()->setBold(true)->setColor(cor(COR_FOLHA));
    $faixa->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(COR_ERVA_CLARA);
    $faixa->getBorders()->getTop()->setBorderStyle('medium')->getColor()->setRGB(COR_FOLHA);
    $ws->getRowDimension($t)->setRowHeight(20);
}

function estilo_cabecalho(Worksheet $ws, string $faixa): void {
    $s = $ws->getStyle($faixa);
    $s->getFont()->setBold(true)->setColor(cor('FFFFFF'));
    $s->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(COR_FOLHA);
    $s->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
    $ws->getRowDimension((int) preg_replace('/\D/', '', explode(':', $faixa)[0]))->setRowHeight(28);
}

function cabecalho(Worksheet $ws, int $linha, array $cols): void {
    foreach ($cols as $c => $txt) $ws->setCellValue("$c$linha", $txt);
    $cs = array_keys($cols);
    estilo_cabecalho($ws, $cs[0] . $linha . ':' . end($cs) . $linha);
}

// Linhas listradas, formatos e a linha de total em destaque (blocos do Resumo)
function estilo_bloco(Worksheet $ws, int $de, int $total, array $formatos, string $c1, string $c2): void {
    for ($l = $de; $l < $total; $l++) {
        if (($l - $de) % 2 === 1) {
            $ws->getStyle("$c1$l:$c2$l")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F7F8F3');
        }
    }
    foreach ($formatos as $c => $f) $ws->getStyle("$c$de:$c$total")->getNumberFormat()->setFormatCode($f);
    $t = $ws->getStyle("$c1$total:$c2$total");
    $t->getFont()->setBold(true)->setColor(cor(COR_FOLHA));
    $t->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(COR_ERVA_CLARA);
    $t->getBorders()->getTop()->setBorderStyle('medium')->getColor()->setRGB(COR_FOLHA);
}

function titulo_secao(Worksheet $ws, string $cel, string $txt): void {
    $ws->setCellValue($cel, $txt);
    $ws->getStyle($cel)->getFont()->setSize(13)->setBold(true)->setColor(cor(COR_FOLHA));
    $ws->getRowDimension((int) preg_replace('/\D/', '', $cel))->setRowHeight(22);
}

function cores_situacao(Worksheet $ws, string $faixa): void {
    $regras = [];
    foreach ([['Sem estoque', COR_PERIGO, COR_PERIGO_CLARA], ['Baixo', COR_CUIA, COR_CUIA_CLARA], ['Normal', COR_VERDE_TEXTO, COR_ERVA_CLARA]] as [$txt, $fonte, $fundo]) {
        $c = new Conditional();
        $c->setConditionType(Conditional::CONDITION_CELLIS)->setOperatorType(Conditional::OPERATOR_EQUAL)->addCondition("\"$txt\"");
        $c->getStyle()->getFont()->setBold(true)->getColor()->setRGB($fonte);
        $c->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getEndColor()->setRGB($fundo);
        $regras[] = $c;
    }
    $ws->getStyle($faixa)->setConditionalStyles($regras);
}

function cores_tipo(Worksheet $ws, string $faixa): void {
    $regras = [];
    foreach ([['Entrada', COR_VERDE_TEXTO], ['Saída', COR_PERIGO]] as [$txt, $fonte]) {
        $c = new Conditional();
        $c->setConditionType(Conditional::CONDITION_CELLIS)->setOperatorType(Conditional::OPERATOR_EQUAL)->addCondition("\"$txt\"");
        $c->getStyle()->getFont()->setBold(true)->getColor()->setRGB($fonte);
        $regras[] = $c;
    }
    $ws->getStyle($faixa)->setConditionalStyles($regras);
}

// ---------- Gráficos ----------

function grafico_colunas(Worksheet $ws, string $nome, string $titulo, string $aba, int $cab, int $de, int $ate, array $series, string $tl, string $br): void {
    $rotulos = []; $valores = [];
    foreach ($series as $col => $corSerie) {
        $rotulos[] = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'$aba'!\$$col\$$cab", null, 1);
        $v = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "'$aba'!\$$col\$$de:\$$col\$$ate", FMT_BRL, $ate - $de + 1);
        $v->setFillColor($corSerie);
        $valores[] = $v;
    }
    $cats = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'$aba'!\$B\$$de:\$B\$$ate", null, $ate - $de + 1)];
    $serie = new DataSeries(DataSeries::TYPE_BARCHART, DataSeries::GROUPING_CLUSTERED, range(0, count($valores) - 1), $rotulos, $cats, $valores);
    $serie->setPlotDirection(DataSeries::DIRECTION_COL);
    adicionar_grafico($ws, $nome, $titulo, $serie, $tl, $br, true);
}

function grafico_barras(Worksheet $ws, string $nome, string $titulo, string $aba, string $colCat, string $colVal, int $de, int $ate, array $cores, string $tl, string $br, bool $inteiro): void {
    $n = $ate - $de + 1;
    $v = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "'$aba'!\$$colVal\$$de:\$$colVal\$$ate", $inteiro ? '0' : FMT_BRL, $n);
    $v->setFillColor($cores[0]);
    $cats = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'$aba'!\$$colCat\$$de:\$$colCat\$$ate", null, $n)];
    $serie = new DataSeries(DataSeries::TYPE_BARCHART, DataSeries::GROUPING_CLUSTERED, [0], [], $cats, [$v]);
    $serie->setPlotDirection(DataSeries::DIRECTION_BAR);
    adicionar_grafico($ws, $nome, $titulo, $serie, $tl, $br, false);
}

function grafico_pizza(Worksheet $ws, string $nome, string $titulo, string $faixaCat, string $faixaVal, array $cores, string $tl, string $br): void {
    $aba = $ws->getTitle();
    $abs = fn($f) => "'$aba'!" . preg_replace('/([A-Z]+)(\d+)/', '\$$1\$$2', $f);
    $v = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, $abs($faixaVal), FMT_BRL, 2);
    $v->setFillColor($cores);
    $cats = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, $abs($faixaCat), null, 2)];
    $serie = new DataSeries(DataSeries::TYPE_DONUTCHART, null, [0], [], $cats, [$v]);
    adicionar_grafico($ws, $nome, $titulo, $serie, $tl, $br, true, true);
}

function adicionar_grafico(Worksheet $ws, string $nome, string $titulo, DataSeries $serie, string $tl, string $br, bool $legenda, bool $percentual = false): void {
    $layout = new \PhpOffice\PhpSpreadsheet\Chart\Layout();
    if ($percentual) $layout->setShowPercent(true);
    $grafico = new Chart($nome, new Title($titulo), $legenda ? new Legend(Legend::POSITION_BOTTOM, null, false) : null,
        new PlotArea($layout, [$serie]), true, DataSeries::EMPTY_AS_GAP);
    $grafico->setTopLeftPosition($tl);
    $grafico->setBottomRightPosition($br);
    $ws->addChart($grafico);
}

// ---------- Utilidades ----------

function configurar_impressao(Worksheet $ws, bool $paisagem): void {
    $ws->getPageSetup()->setOrientation($paisagem ? 'landscape' : 'portrait')->setFitToWidth(1)->setFitToHeight(0);
    $ws->getPageMargins()->setTop(0.5)->setBottom(0.5)->setLeft(0.4)->setRight(0.4);
    $ws->getHeaderFooter()->setOddFooter('&L&8Erva Mate · relatório geral&R&8Página &P de &N');
}

function data_excel(?string $data) {
    return $data ? Date::PHPToExcel(new DateTime($data)) : null;
}

function cor(string $rgb): \PhpOffice\PhpSpreadsheet\Style\Color {
    return new \PhpOffice\PhpSpreadsheet\Style\Color('FF' . $rgb);
}
