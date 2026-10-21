<?php
// Teste do pedido. Rode no terminal:  php testes/teste_pedido.php
// Cria um cliente e um produto de teste, faz os pedidos e apaga tudo no fim.
if (PHP_SAPI !== 'cli') exit('Rode pelo terminal.');

require __DIR__ . '/../conexao.php';
require __DIR__ . '/../pedido.php';
$conn->set_charset('utf8mb4');

$falhas = 0;
function confere($ok, $texto) {
    global $falhas;
    echo ($ok ? 'OK    ' : 'FALHOU') . "  $texto\n";
    if (!$ok) $falhas++;
}
function valor($conn, $sql) {
    return $conn->query($sql)->fetch_row()[0];
}

$conn->query("INSERT INTO clientes (nome) VALUES ('__teste__')");
$cliente = $conn->insert_id;
$conn->query("INSERT INTO produtos (nome, preco_compra, preco_venda, estoque) VALUES ('__teste__', 5, 12.50, 2)");
$produto = $conn->insert_id;
$pedidos = [];

try {
    // Mais do que tem: recusa e não grava nada
    try {
        criar_pedido($conn, $cliente, $produto, 3);
        confere(false, 'vender 3 com estoque 2 é recusado');
    } catch (InvalidArgumentException $e) {
        confere(true, 'vender 3 com estoque 2 é recusado');
    }
    confere(valor($conn, "SELECT estoque FROM produtos WHERE id = $produto") == 2, 'estoque continua 2');
    confere(valor($conn, "SELECT COUNT(*) FROM saidas WHERE produto_id = $produto") == 0, 'nenhuma saída gravada');

    // Quantidade zero: recusa
    try {
        criar_pedido($conn, $cliente, $produto, 0);
        confere(false, 'quantidade 0 é recusada');
    } catch (InvalidArgumentException $e) {
        confere(true, 'quantidade 0 é recusada');
    }

    // Venda certa: grava tudo junto
    [$id, $total] = criar_pedido($conn, $cliente, $produto, 2);
    $pedidos[] = $id;
    confere($total == 25.00, 'total = 2 x 12,50 = 25,00');
    confere(valor($conn, "SELECT estoque FROM produtos WHERE id = $produto") == 0, 'estoque baixou para 0');
    confere(valor($conn, "SELECT valor_total FROM pedidos WHERE id = $id") == 25.00, 'pedido gravado com 25,00');
    confere(valor($conn, "SELECT COUNT(*) FROM pedido_itens WHERE pedido_id = $id") == 1, 'item do pedido gravado');
    confere(valor($conn, "SELECT preco_compra FROM saidas WHERE produto_id = $produto") == 5.00, 'saída guarda o custo da época');
    confere(valor($conn, "SELECT valor FROM caixa WHERE descricao LIKE '%Pedido #$id)'") == 25.00, 'caixa recebeu 25,00');
} finally {
    // Limpa o que o teste criou (na ordem das chaves estrangeiras)
    foreach ($pedidos as $id) {
        $conn->query("DELETE FROM caixa WHERE descricao LIKE '%Pedido #$id)'");
        $conn->query("DELETE FROM pedido_itens WHERE pedido_id = $id");
        $conn->query("DELETE FROM pedidos WHERE id = $id");
    }
    $conn->query("DELETE FROM saidas WHERE produto_id = $produto");
    $conn->query("DELETE FROM produtos WHERE id = $produto");
    $conn->query("DELETE FROM clientes WHERE id = $cliente");
}

echo $falhas ? "\n$falhas teste(s) falharam.\n" : "\nTudo certo.\n";
exit($falhas ? 1 : 0);
