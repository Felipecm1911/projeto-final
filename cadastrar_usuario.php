<?php
require 'layout.php';
exigir_somente_admin();

$erro = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nome = trim($_POST['nome'] ?? '');
    $usuario = trim($_POST['usuario'] ?? '');
    $senha_texto = $_POST['senha'] ?? '';
    $nivel = $_POST['nivel'] ?? '';

    $existe = $conn->prepare("SELECT id FROM usuarios WHERE usuario = ?");
    $existe->bind_param("s", $usuario);
    $existe->execute();

    if ($nome === '' || $usuario === '' || !in_array($nivel, ['admin', 'caixa', 'suporte'], true)) {
        $erro = 'Preencha nome, usuário e escolha um nível válido.';
    } elseif (strlen($senha_texto) < 6) {
        $erro = 'A senha precisa ter pelo menos 6 caracteres.';
    } elseif ($existe->get_result()->fetch_row()) {
        $erro = "O usuário $usuario já existe.";
    } else {
        $senha = password_hash($senha_texto, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO usuarios (nome, usuario, senha, nivel) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $nome, $usuario, $senha, $nivel);
        $stmt->execute();
        avisar('sucesso', "Usuário $usuario cadastrado.", 'cadastrar_usuario.php', 'Cadastrar outro');
        header('Location: listar_usuarios.php');
        exit;
    }
}
$v = fn($c) => h($_POST[$c] ?? '');
$nivel_atual = $_POST['nivel'] ?? 'caixa';

cabecalho_pagina('Cadastrar usuário', 'listar_usuarios.php');
titulo_pagina('Cadastrar usuário', '', '', ['listar_usuarios.php', 'Usuários']);
if ($erro) echo '<div class="mensagem erro" role="alert">' . h($erro) . '</div>';
?>
<form method="post" class="painel formulario">
    <?= campo_csrf() ?>
    <div class="painel-corpo campos">
        <div class="campo inteiro">
            <label for="nome">Nome</label>
            <input type="text" id="nome" name="nome" value="<?= $v('nome') ?>" required autofocus>
        </div>
        <div class="campo">
            <label for="usuario">Usuário</label>
            <input type="text" id="usuario" name="usuario" value="<?= $v('usuario') ?>" autocomplete="off" required>
        </div>
        <div class="campo">
            <label for="senha">Senha</label>
            <input type="password" id="senha" name="senha" autocomplete="new-password" minlength="6" required>
        </div>
        <div class="campo inteiro">
            <label for="nivel">Nível de acesso</label>
            <select id="nivel" name="nivel" required>
                <?php foreach (['admin' => 'Admin — acesso total', 'caixa' => 'Caixa — pedidos, produtos e caixa', 'suporte' => 'Suporte'] as $valor => $rotulo): ?>
                    <option value="<?= $valor ?>"<?= $nivel_atual == $valor ? ' selected' : '' ?>><?= $rotulo ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="rodape-form">
        <a class="botao secundario" href="listar_usuarios.php">Cancelar</a>
        <button class="botao" type="submit">Salvar usuário</button>
    </div>
</form>
<?php rodape_pagina(); ?>
