<?php
require 'layout.php';
exigir_admin();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nome = $_POST['nome'];
    $cpf = $_POST['cpf'];
    $telefone = $_POST['telefone'];
    $email = $_POST['email'];

    $stmt = $conn->prepare("INSERT INTO clientes (nome, cpf, telefone, email) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $nome, $cpf, $telefone, $email);
    $stmt->execute();
    avisar('sucesso', "Cliente $nome cadastrado.", 'cadastrar_cliente.php', 'Cadastrar outro');
    header('Location: listar_clientes.php');
    exit;
}
$v = fn($c) => h($_POST[$c] ?? '');

cabecalho_pagina('Cadastrar cliente', 'listar_clientes.php');
titulo_pagina('Cadastrar cliente', '', '', ['listar_clientes.php', 'Clientes']);
?>
<form method="post" class="painel formulario">
    <?= campo_csrf() ?>
    <div class="painel-corpo campos">
        <div class="campo inteiro">
            <label for="nome">Nome completo</label>
            <input type="text" id="nome" name="nome" value="<?= $v('nome') ?>" required autofocus>
        </div>
        <div class="campo">
            <label for="cpf">CPF</label>
            <input type="text" id="cpf" name="cpf" value="<?= $v('cpf') ?>" placeholder="000.000.000-00" inputmode="numeric" maxlength="14" required>
        </div>
        <div class="campo">
            <label for="telefone">Telefone</label>
            <input type="tel" id="telefone" name="telefone" value="<?= $v('telefone') ?>" placeholder="(54) 99999-9999" required>
        </div>
        <div class="campo inteiro">
            <label for="email">E-mail <span class="dica">(opcional)</span></label>
            <input type="email" id="email" name="email" value="<?= $v('email') ?>">
        </div>
    </div>
    <div class="rodape-form">
        <a class="botao secundario" href="listar_clientes.php">Cancelar</a>
        <button class="botao" type="submit">Salvar cliente</button>
    </div>
</form>
<?php rodape_pagina(); ?>
