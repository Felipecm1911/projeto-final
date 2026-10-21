<?php
// Cria o pedido inteiro ou nada: baixa o estoque, grava pedido, item, saída e caixa.
// Devolve [id do pedido, valor total]. Dado inválido: InvalidArgumentException com a
// mensagem para o usuário. Falha no banco: a exceção do mysqli, sem nada gravado.
function criar_pedido($conn, $cliente_id, $produto_id, $quantidade) {
    // Buscar preço, custo e estoque do produto
    $stmt = $conn->prepare("SELECT preco_venda, preco_compra, estoque FROM produtos WHERE id = ?");
    $stmt->bind_param("i", $produto_id);
    $stmt->execute();
    $produto = $stmt->get_result()->fetch_assoc();

    $stmt = $conn->prepare("SELECT id FROM clientes WHERE id = ?");
    $stmt->bind_param("i", $cliente_id);
    $stmt->execute();
    $cliente_existe = (bool) $stmt->get_result()->fetch_row();

    if ($quantidade < 1 || !$produto || !$cliente_existe) {
        throw new InvalidArgumentException('Escolha o cliente, o produto e uma quantidade de pelo menos 1.');
    }
    if ($quantidade > $produto['estoque']) {
        throw new InvalidArgumentException('Estoque insuficiente: há ' . (int) $produto['estoque'] . ' unidade(s) deste produto.');
    }

    $preco_unitario = $produto['preco_venda'];
    $preco_compra = $produto['preco_compra'];
    $valor_total = $preco_unitario * $quantidade;

    // Tudo ou nada: se uma gravação falhar, nenhuma fica no banco
    $conn->begin_transaction();
    try {
        // Baixar o estoque (a condição impede vender o que já acabou entre a tela e o envio)
        $stmt = $conn->prepare("UPDATE produtos SET estoque = estoque - ? WHERE id = ? AND estoque >= ?");
        $stmt->bind_param("iii", $quantidade, $produto_id, $quantidade);
        $stmt->execute();
        if ($stmt->affected_rows !== 1) {
            throw new InvalidArgumentException('O estoque acabou enquanto o pedido era feito. Confira e tente de novo.');
        }

        // Inserir pedido
        $stmt = $conn->prepare("INSERT INTO pedidos (cliente_id, valor_total) VALUES (?, ?)");
        $stmt->bind_param("id", $cliente_id, $valor_total);
        $stmt->execute();
        $pedido_id = $conn->insert_id;

        // Inserir item do pedido
        $stmt = $conn->prepare("INSERT INTO pedido_itens (pedido_id, produto_id, quantidade, preco_unitario) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiid", $pedido_id, $produto_id, $quantidade, $preco_unitario);
        $stmt->execute();

        // Registrar saída no estoque, com o custo da época (para o lucro não mudar depois)
        $stmt = $conn->prepare("INSERT INTO saidas (produto_id, cliente_id, quantidade, valor_total, preco_compra) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iiidd", $produto_id, $cliente_id, $quantidade, $valor_total, $preco_compra);
        $stmt->execute();

        // Registrar entrada no caixa
        $descricao = "Venda gerada automaticamente (Pedido #$pedido_id)";
        $stmt = $conn->prepare("INSERT INTO caixa (tipo, valor, descricao) VALUES ('entrada', ?, ?)");
        $stmt->bind_param("ds", $valor_total, $descricao);
        $stmt->execute();

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }

    return [$pedido_id, $valor_total];
}
