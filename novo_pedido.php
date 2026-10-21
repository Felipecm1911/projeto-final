<?php
require 'layout.php';
require 'pedido.php';
exigir_login();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        [$pedido_id, $valor_total] = criar_pedido($conn,
            (int) ($_POST['cliente_id'] ?? 0),
            (int) ($_POST['produto_id'] ?? 0),
            (int) ($_POST['quantidade'] ?? 0));
    } catch (InvalidArgumentException $e) {
        avisar('erro', $e->getMessage());
        header('Location: novo_pedido.php');
        exit;
    } catch (Throwable $e) {
        error_log($e);
        avisar('erro', 'Não foi possível criar o pedido. Nada foi gravado; confira o estoque e tente de novo.');
        header('Location: novo_pedido.php');
        exit;
    }

    avisar('sucesso', "Pedido #$pedido_id criado: " . brl($valor_total) . ' lançado no caixa.', 'novo_pedido.php', 'Novo pedido');
    header('Location: listar_pedidos.php');
    exit;
}

// Carregar clientes e produtos
$clientes = $conn->query("SELECT id, nome FROM clientes ORDER BY nome")->fetch_all(MYSQLI_ASSOC);
$produtos = $conn->query("SELECT id, nome, preco_venda, estoque FROM produtos ORDER BY nome")->fetch_all(MYSQLI_ASSOC);

cabecalho_pagina('Novo pedido', 'novo_pedido.php');
titulo_pagina('Novo pedido', 'O pedido baixa o estoque e lança a venda no caixa automaticamente.', '', ['listar_pedidos.php', 'Pedidos']);
?>
<form method="post" class="pedido-grade">
    <?= campo_csrf() ?>
    <div>
    <section class="painel">
        <div class="painel-corpo campos">
            <div class="campo inteiro">
                <label for="cliente_id">Cliente</label>
                <select id="cliente_id" name="cliente_id" required>
                    <option value="" disabled selected>Escolha o cliente</option>
                    <?php foreach ($clientes as $c): ?>
                        <option value="<?= (int) $c['id'] ?>"><?= h($c['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (!eh_caixa()): ?><span class="dica">Cliente novo? <a href="cadastrar_cliente.php">Cadastre aqui</a>.</span><?php endif; ?>
            </div>
            <div class="campo inteiro">
                <label for="produto_id">Produto</label>
                <select id="produto_id" name="produto_id" required>
                    <option value="" disabled selected>Escolha o produto</option>
                    <?php foreach ($produtos as $p): ?>
                        <option value="<?= (int) $p['id'] ?>" data-preco="<?= (float) $p['preco_venda'] ?>" data-estoque="<?= (int) $p['estoque'] ?>">
                            <?= h($p['nome']) ?> — <?= brl($p['preco_venda']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="campo">
                <label for="quantidade">Quantidade</label>
                <input type="number" id="quantidade" name="quantidade" min="1" value="1" required>
            </div>
        </div>
    </section>

    <section class="painel produtos-rapidos">
        <div class="painel-topo">
            <h2>Produtos</h2>
            <span class="dica">Clique em um produto para escolher</span>
        </div>
        <div class="tabela-wrap">
            <table>
                <thead><tr><th>Produto</th><th class="num">Preço</th><th class="num">Estoque</th></tr></thead>
                <tbody>
                <?php foreach ($produtos as $p): $esgotado = $p['estoque'] <= 0; ?>
                    <tr data-id="<?= (int) $p['id'] ?>"<?= $esgotado ? ' class="esgotado"' : '' ?>>
                        <td><button type="button"<?= $esgotado ? ' disabled' : '' ?>><?= h($p['nome']) ?></button></td>
                        <td class="num"><?= brl($p['preco_venda']) ?></td>
                        <td class="num"><?= $esgotado ? situacao_estoque(0) : (int) $p['estoque'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    </div>

    <aside class="painel resumo" aria-live="polite">
        <div class="painel-topo"><h2>Resumo do pedido</h2></div>
        <div class="painel-corpo">
            <dl>
                <div><dt>Produto</dt><dd id="r-nome">—</dd></div>
                <div><dt>Preço unitário</dt><dd id="r-preco">—</dd></div>
                <div><dt>Em estoque</dt><dd id="r-estoque">—</dd></div>
                <div><dt>Quantidade</dt><dd id="r-qtd">1</dd></div>
                <div class="total"><dt>Total</dt><dd id="r-total">R$ 0,00</dd></div>
            </dl>
            <p class="aviso-estoque" id="r-aviso" hidden></p>
        </div>
        <div class="rodape-form">
            <button class="botao" type="submit">Criar pedido</button>
        </div>
    </aside>
</form>
<script>
    // Calcula o total do pedido enquanto o produto e a quantidade são escolhidos
    (function () {
        var produto = document.getElementById('produto_id');
        var qtd = document.getElementById('quantidade');
        var moeda = function (n) { return n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }); };
        function atualizar() {
            var op = produto.selectedOptions[0];
            var preco = op && op.dataset.preco ? parseFloat(op.dataset.preco) : 0;
            var estoque = op && op.dataset.estoque !== undefined ? parseInt(op.dataset.estoque, 10) : null;
            var q = Math.max(parseInt(qtd.value, 10) || 0, 0);
            document.getElementById('r-nome').textContent = op && op.value ? op.textContent.split('—')[0].trim() : '—';
            document.getElementById('r-preco').textContent = preco ? moeda(preco) : '—';
            document.querySelectorAll('.produtos-rapidos tr[data-id]').forEach(function (tr) {
                tr.classList.toggle('escolhido', tr.dataset.id === produto.value);
            });
            document.getElementById('r-estoque').textContent = estoque === null || isNaN(estoque) ? '—' : estoque + ' un.';
            document.getElementById('r-qtd').textContent = q;
            var total = document.getElementById('r-total');
            if (window.animarValor) animarValor(total, preco * q, 'R$ ');
            else total.textContent = moeda(preco * q);
            var aviso = document.getElementById('r-aviso');
            if (estoque !== null && !isNaN(estoque) && q > estoque) {
                aviso.textContent = estoque <= 0 ? 'Este produto está sem estoque.' : 'A quantidade passa do estoque disponível (' + estoque + ' un.).';
                aviso.hidden = false;
            } else {
                aviso.hidden = true;
            }
        }
        produto.addEventListener('change', atualizar);
        qtd.addEventListener('input', atualizar);

        // Clicar numa linha da lista escolhe o produto no campo acima
        document.querySelectorAll('.produtos-rapidos tr[data-id]:not(.esgotado)').forEach(function (tr) {
            tr.addEventListener('click', function () {
                produto.value = tr.dataset.id;
                atualizar();
                qtd.focus();
                qtd.select();
            });
        });
        atualizar();
    })();
</script>
<?php rodape_pagina(); ?>
