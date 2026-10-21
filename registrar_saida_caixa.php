<?php
require 'layout.php';
exigir_login();

$erro = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $valor = (float) ($_POST['valor'] ?? 0);
    $descricao = trim($_POST['descricao'] ?? '');

    if ($valor <= 0 || $descricao === '') {
        $erro = 'Informe um valor maior que zero e uma descrição.';
    } else {
        $stmt = $conn->prepare("INSERT INTO caixa (tipo, valor, descricao) VALUES ('saida', ?, ?)");
        $stmt->bind_param("ds", $valor, $descricao);
        $stmt->execute();
        avisar('sucesso', 'Saída de ' . brl($valor) . ' registrada.');
        header('Location: caixa.php');
        exit;
    }
}

cabecalho_pagina('Registrar saída', 'caixa.php');
titulo_pagina('Registrar saída do caixa', 'Pagamentos feitos com o dinheiro do caixa: compra de mercadoria, frete, despesas.', '', ['caixa.php', 'Caixa']);
if ($erro) echo '<div class="mensagem erro" role="alert">' . h($erro) . '</div>';
?>
<form method="post" class="painel formulario">
    <?= campo_csrf() ?>
    <div class="painel-corpo campos">
        <div class="campo">
            <label for="valor">Valor (R$)</label>
            <input type="number" step="0.01" min="0.01" id="valor" name="valor" required autofocus>
        </div>
        <div class="campo inteiro">
            <label for="descricao">Descrição</label>
            <input type="text" id="descricao" name="descricao" placeholder="Ex.: Frete da entrega" required>
        </div>
    </div>
    <div class="rodape-form">
        <a class="botao secundario" href="caixa.php">Cancelar</a>
        <button class="botao perigo" type="submit">Registrar saída</button>
    </div>
</form>
<?php rodape_pagina(); ?>
