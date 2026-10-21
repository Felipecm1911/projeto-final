<?php
require 'layout.php';
exigir_admin();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nome = $_POST['nome'];
    $cnpj = $_POST['cnpj'];
    $telefone = $_POST['telefone'];
    $email = $_POST['email'];

    $stmt = $conn->prepare("INSERT INTO fornecedores (nome, cnpj, telefone, email) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $nome, $cnpj, $telefone, $email);
    $stmt->execute();
    avisar('sucesso', "Fornecedor $nome cadastrado.", 'cadastrar_fornecedor.php', 'Cadastrar outro');
    header('Location: listar_fornecedores.php');
    exit;
}
$v = fn($c) => h($_POST[$c] ?? '');

cabecalho_pagina('Cadastrar fornecedor', 'listar_fornecedores.php');
titulo_pagina('Cadastrar fornecedor', '', '', ['listar_fornecedores.php', 'Fornecedores']);
?>
<form method="post" class="painel formulario">
    <?= campo_csrf() ?>
    <div class="painel-corpo campos">
        <div class="campo inteiro">
            <label for="nome">Razão social ou nome</label>
            <input type="text" id="nome" name="nome" value="<?= $v('nome') ?>" required autofocus>
        </div>
        <div class="campo">
            <label for="cnpj">CNPJ</label>
            <input type="text" id="cnpj" name="cnpj" value="<?= $v('cnpj') ?>" placeholder="00.000.000/0000-00" inputmode="numeric" maxlength="18" required>
        </div>
        <div class="campo">
            <label for="telefone">Telefone</label>
            <input type="tel" id="telefone" name="telefone" value="<?= $v('telefone') ?>" required>
        </div>
        <div class="campo inteiro">
            <label for="email">E-mail <span class="dica">(opcional)</span></label>
            <input type="email" id="email" name="email" value="<?= $v('email') ?>">
        </div>
    </div>
    <div class="rodape-form">
        <a class="botao secundario" href="listar_fornecedores.php">Cancelar</a>
        <button class="botao" type="submit">Salvar fornecedor</button>
    </div>
</form>
<?php rodape_pagina(); ?>
