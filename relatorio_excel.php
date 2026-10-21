<?php
// Baixa o relatório geral do sistema em Excel
require 'layout.php';
require 'relatorio_excel_lib.php';
exigir_admin();

$planilha = montar_relatorio($conn, $_SESSION['usuario'], ESTOQUE_MINIMO);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="relatorio-erva-mate-' . date('Y-m-d') . '.xlsx"');
header('Cache-Control: max-age=0');

$arquivo = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($planilha);
$arquivo->setIncludeCharts(true);
$arquivo->save('php://output');
