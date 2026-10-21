<?php
require 'layout.php';
exigir_login();

$erro = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $atual = $_POST['senha_atual'] ?? '';
    $nova = $_POST['senha_nova'] ?? '';
    $usuario = $_SESSION['usuario'];

    $stmt = $conn->prepare("SELECT senha FROM usuarios WHERE usuario = ?");
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $hash = $stmt->get_result()->fetch_row()[0] ?? '';

    if (!password_verify($atual, $hash)) {
        $erro = 'A senha atual está errada.';
    } elseif (strlen($nova) < 6) {
        $erro = 'A nova senha precisa ter pelo menos 6 caracteres.';
    } else {
        $novo_hash = password_hash($nova, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE usuarios SET senha = ? WHERE usuario = ?");
        $stmt->bind_param("ss", $novo_hash, $usuario);
        $stmt->execute();
        avisar('sucesso', 'Senha alterada.');
        header('Location: painel.php');
        exit;
    }
}

cabecalho_pagina('Alterar senha');
titulo_pagina('Alterar senha', '', '', ['painel.php', 'Painel']);
if ($erro) echo '<div class="mensagem erro" role="alert">' . h($erro) . '</div>';
?>
<form method="post" class="painel formulario">
    <?= campo_csrf() ?>
    <div class="painel-corpo campos">
        <div class="campo">
            <label for="senha_atual">Senha atual</label>
            <input type="password" id="senha_atual" name="senha_atual" autocomplete="current-password" required autofocus>
        </div>
        <div class="campo">
            <label for="senha_nova">Nova senha</label>
            <input type="password" id="senha_nova" name="senha_nova" autocomplete="new-password" minlength="6" required>
        </div>
    </div>
    <div class="rodape-form">
        <a class="botao secundario" href="painel.php">Cancelar</a>
        <button class="botao" type="submit">Salvar senha</button>
    </div>
</form>
<?php rodape_pagina(); ?>
