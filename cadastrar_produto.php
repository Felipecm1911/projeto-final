<?php
require 'layout.php';
exigir_login();

$erro = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nome = trim($_POST['nome'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $preco_compra = (float) ($_POST['preco_compra'] ?? 0);
    $preco_venda = (float) ($_POST['preco_venda'] ?? 0);
    $estoque = (int) ($_POST['estoque'] ?? 0);

    if ($nome === '' || $preco_compra < 0 || $preco_venda <= 0 || $estoque < 0) {
        $erro = 'Preencha o nome, preços maiores que zero e estoque de 0 ou mais.';
    } else {
        $stmt = $conn->prepare("INSERT INTO produtos (nome, descricao, preco_compra, preco_venda, estoque) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssddi", $nome, $descricao, $preco_compra, $preco_venda, $estoque);
        $stmt->execute();
        avisar('sucesso', "Produto $nome cadastrado.", 'cadastrar_produto.php', 'Cadastrar outro');
        header('Location: listar_produtos.php');
        exit;
    }
}
$v = fn($c) => h($_POST[$c] ?? '');

cabecalho_pagina('Cadastrar produto', 'listar_produtos.php');
titulo_pagina('Cadastrar produto', '', '', ['listar_produtos.php', 'Produtos']);
if ($erro) echo '<div class="mensagem erro" role="alert">' . h($erro) . '</div>';
?>
<form method="post" class="painel formulario">
    <?= campo_csrf() ?>
    <div class="painel-corpo campos">
        <div class="campo inteiro">
            <label for="nome">Nome do produto</label>
            <input type="text" id="nome" name="nome" value="<?= $v('nome') ?>" placeholder="Ex.: Erva-mate tradicional 1kg" required autofocus>
        </div>
        <div class="campo inteiro">
            <label for="descricao">Descrição <span class="dica">(opcional)</span></label>
            <input type="text" id="descricao" name="descricao" value="<?= $v('descricao') ?>">
        </div>
        <div class="campo">
            <label for="preco_compra">Preço de compra (R$)</label>
            <input type="number" step="0.01" min="0" id="preco_compra" name="preco_compra" value="<?= $v('preco_compra') ?>" required>
        </div>
        <div class="campo">
            <label for="preco_venda">Preço de venda (R$)</label>
            <input type="number" step="0.01" min="0" id="preco_venda" name="preco_venda" value="<?= $v('preco_venda') ?>" required>
            <span class="dica" id="margem" aria-live="polite"></span>
        </div>
        <div class="campo">
            <label for="estoque">Estoque inicial</label>
            <input type="number" min="0" id="estoque" name="estoque" value="<?= $v('estoque') ?>" required>
        </div>
    </div>
    <div class="rodape-form">
        <a class="botao secundario" href="listar_produtos.php">Cancelar</a>
        <button class="botao" type="submit">Salvar produto</button>
    </div>
</form>
<script>
    // Mostra a margem enquanto os preços são digitados
    (function () {
        var compra = document.getElementById('preco_compra');
        var venda = document.getElementById('preco_venda');
        var saida = document.getElementById('margem');
        function atualizar() {
            var c = parseFloat(compra.value), v = parseFloat(venda.value);
            if (!(c >= 0) || !(v > 0)) { saida.textContent = ''; return; }
            var lucro = v - c;
            saida.textContent = 'Lucro de ' + lucro.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }) +
                ' por unidade (margem de ' + Math.round(lucro / v * 100) + '%)';
            saida.style.color = lucro < 0 ? 'var(--perigo)' : '';
        }
        compra.addEventListener('input', atualizar);
        venda.addEventListener('input', atualizar);
        atualizar();
    })();
</script>
<?php rodape_pagina(); ?>
