<?php
// Teste do relatório em Excel. Rode no terminal:  php testes/teste_relatorio.php [arquivo.xlsx]
// Gera o arquivo, abre de novo, calcula todas as fórmulas e compara os totais com o banco.
if (PHP_SAPI !== 'cli') exit('Rode pelo terminal.');

require __DIR__ . '/../conexao.php';
require __DIR__ . '/../relatorio_excel_lib.php';
$conn->set_charset('utf8mb4');
date_default_timezone_set('America/Sao_Paulo');

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$caminho = $argv[1] ?? sys_get_temp_dir() . '/teste-relatorio.xlsx';
$falhas = 0;
function confere($ok, $texto) {
    global $falhas;
    echo ($ok ? 'OK    ' : 'FALHOU') . "  $texto\n";
    if (!$ok) $falhas++;
}
$valor = fn($sql) => (float) $conn->query($sql)->fetch_row()[0];

$escritor = new Xlsx(montar_relatorio($conn, 'teste', 10));
$escritor->setIncludeCharts(true);
$escritor->save($caminho);
confere(filesize($caminho) > 10000, 'arquivo gerado (' . round(filesize($caminho) / 1024) . ' KB)');

$leitor = IOFactory::createReader('Xlsx');
$leitor->setIncludeCharts(true);
$p = $leitor->load($caminho);

confere($p->getSheetNames() === ['Resumo', 'Vendas', 'Pedidos', 'Produtos', 'Caixa', 'Clientes', 'Entradas', 'Fornecedores'],
    'abas: ' . implode(', ', $p->getSheetNames()));

// Toda fórmula precisa dar um valor, nunca #REF!, #NAME?, #VALUE!...
$formulas = 0; $erros = [];
foreach ($p->getAllSheets() as $ws) {
    foreach ($ws->getCellCollection()->getCoordinates() as $coord) {
        $cel = $ws->getCell($coord);
        if ($cel->isFormula()) {
            $formulas++;
            $v = $cel->getCalculatedValue();
            if (is_string($v) && preg_match('/^#[A-Z\/0!?]+/', $v)) $erros[] = $ws->getTitle() . "!$coord=$v";
        }
    }
}
confere(!$erros, "$formulas fórmulas calculadas, " . count($erros) . ' com erro' . ($erros ? ': ' . implode(' ', array_slice($erros, 0, 5)) : ''));

$r = $p->getSheetByName('Resumo');
$calc = fn($cel) => round((float) $r->getCell($cel)->getCalculatedValue(), 2);
confere($calc('B7') == round($valor("SELECT SUM(CASE WHEN tipo='entrada' THEN valor ELSE -valor END) FROM caixa"), 2), 'saldo do caixa bate com o banco: ' . $calc('B7'));
confere($calc('D7') == round($valor('SELECT SUM(valor_total) FROM saidas'), 2), 'total vendido bate com o banco: ' . $calc('D7'));
confere($calc('F7') == round($valor('SELECT SUM(s.valor_total - COALESCE(s.preco_compra, p.preco_compra) * s.quantidade) FROM saidas s JOIN produtos p ON p.id = s.produto_id'), 2), 'lucro bate com o banco: ' . $calc('F7'));
confere($calc('B10') == $valor('SELECT COUNT(*) FROM pedidos'), 'número de pedidos bate: ' . $calc('B10'));
confere($calc('J10') == $valor('SELECT COUNT(*) FROM produtos WHERE estoque < 10'), 'produtos para repor batem: ' . $calc('J10'));
confere($calc('J7') == $valor('SELECT COUNT(*) FROM clientes'), 'clientes batem: ' . $calc('J7'));

// Soma dos 12 meses = vendas dos últimos 12 meses no banco
$ultimoMes = 18 + 12;
confere($calc('C' . ($ultimoMes + 1)) == round($valor("SELECT COALESCE(SUM(valor_total),0) FROM saidas WHERE data_saida >= DATE_FORMAT(CURDATE() - INTERVAL 11 MONTH, '%Y-%m-01')"), 2),
    'vendas dos últimos 12 meses batem: ' . $calc('C' . ($ultimoMes + 1)));

$graficos = 0;
foreach ($p->getAllSheets() as $ws) $graficos += count($ws->getChartCollection());
confere($graficos === 4, "$graficos gráficos (mês, produto, caixa, estoque)");
confere(count($r->getDrawingCollection()) === 1, 'logo no Resumo');
confere($r->getCell('B1')->getValue() === 'Relatório geral' && $r->getCell('A1')->getValue() === null, 'no Resumo o título fica na coluna B e a coluna A é só do logo');

// Gráficos têm que ser gráficos do Excel (chart XML), não imagens
$zip = new ZipArchive();
$zip->open($caminho);
$xmls = $imagens = 0;
for ($i = 0; $i < $zip->numFiles; $i++) {
    $nome = $zip->getNameIndex($i);
    if (preg_match('#^xl/charts/chart\d+\.xml$#', $nome)) $xmls++;
    if (str_starts_with($nome, 'xl/media/')) $imagens++;
}
$zip->close();
confere($xmls === 4 && $imagens === 1, "$xmls gráficos nativos do Excel e $imagens imagem (só o logo)");
$fundo = imagem_tem_transparencia(__DIR__ . '/../logo-sem-fundo.png');
confere($fundo, 'logo é PNG com fundo transparente');

function imagem_tem_transparencia(string $arquivo): bool {
    $d = file_get_contents($arquivo);
    return substr($d, 1, 3) === 'PNG' && ord($d[25]) === 6; // tipo de cor 6 = RGBA (com canal alfa)
}

echo $falhas ? "\n$falhas teste(s) falharam.\n" : "\nTudo certo. Arquivo: $caminho\n";
exit($falhas ? 1 : 0);
