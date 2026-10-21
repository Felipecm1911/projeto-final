<?php
require 'layout.php';
exigir_admin();

$erro = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $produto_id = (int) ($_POST['produto_id'] ?? 0);
    $fornecedor_id = (int) ($_POST['fornecedor_id'] ?? 0) ?: null;
    $quantidade = (int) ($_POST['quantidade'] ?? 0);

    if ($produto_id < 1 || $quantidade < 1) {
        $erro = 'Escolha o produto e uma quantidade de pelo menos 1.';
    } else {
        // Tudo ou nada: registra a entrada e soma no estoque juntos
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("UPDATE produtos SET estoque = estoque + ? WHERE id = ?");
            $stmt->bind_param("ii", $quantidade, $produto_id);
            $stmt->execute();
            if ($stmt->affected_rows !== 1) {
                throw new RuntimeException('produto inexistente');
            }

            $stmt = $conn->prepare("INSERT INTO entradas (produto_id, fornecedor_id, quantidade) VALUES (?, ?, ?)");
            $stmt->bind_param("iii", $produto_id, $fornecedor_id, $quantidade);
            $stmt->execute();

            $conn->commit();
            avisar('sucesso', "Entrada de $quantidade unidade(s) registrada e somada ao estoque.", 'registrar_entrada.php', 'Registrar outra');
            header('Location: entradas.php');
            exit;
        } catch (Throwable $e) {
            $conn->rollback();
            error_log($e);
            $erro = 'Não foi possível registrar a entrada. Nada foi gravado.';
        }
    }
}

$produtos = $conn->query("SELECT id, nome, estoque FROM produtos ORDER BY nome")->fetch_all(MYSQLI_ASSOC);
$fornecedores = $conn->query("SELECT id, nome FROM fornecedores ORDER BY nome")->fetch_all(MYSQLI_ASSOC);

cabecalho_pagina('Registrar entrada', 'entradas.php');
titulo_pagina('Registrar entrada de mercadoria', 'A quantidade é somada ao estoque do produto.', '', ['entradas.php', 'Entradas de produtos']);
if ($erro) echo '<div class="mensagem erro" role="alert">' . h($erro) . '</div>';
?>
<form method="post" class="painel formulario">
    <?= campo_csrf() ?>
    <div class="painel-corpo campos">
        <div class="campo inteiro">
            <label for="produto_id">Produto</label>
            <select id="produto_id" name="produto_id" required>
                <option value="" disabled selected>Escolha o produto</option>
                <?php foreach ($produtos as $p): ?>
                    <option value="<?= (int) $p['id'] ?>"><?= h($p['nome']) ?> (<?= (int) $p['estoque'] ?> em estoque)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="campo">
            <label for="quantidade">Quantidade recebida</label>
            <input type="number" id="quantidade" name="quantidade" min="1" required>
        </div>
        <div class="campo">
            <label for="fornecedor_id">Fornecedor <span class="dica">(opcional)</span></label>
            <select id="fornecedor_id" name="fornecedor_id">
                <option value="">Sem fornecedor</option>
                <?php foreach ($fornecedores as $f): ?>
                    <option value="<?= (int) $f['id'] ?>"><?= h($f['nome']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="rodape-form">
        <a class="botao secundario" href="entradas.php">Cancelar</a>
        <button class="botao" type="submit">Registrar entrada</button>
    </div>
</form>
<?php rodape_pagina(); ?>
