<?php
require 'layout.php';
exigir_admin();

$result = $conn->query("SELECT * FROM clientes ORDER BY nome");
$linhas = $result->fetch_all(MYSQLI_ASSOC);

cabecalho_pagina('Clientes', 'listar_clientes.php');
titulo_pagina('Clientes', count($linhas) . ' clientes cadastrados.', '<a class="botao" href="cadastrar_cliente.php">Cadastrar cliente</a>');
?>
<section class="painel">
    <div class="filtros">
        <div class="campo busca">
            <label for="busca">Buscar</label>
            <input type="search" id="busca" data-filtrar="tabela" placeholder="Nome, CPF ou telefone">
        </div>
    </div>
    <?php if ($linhas): ?>
    <div class="tabela-wrap">
        <table id="tabela">
            <thead><tr><th>Nome</th><th>CPF</th><th>Telefone</th><th class="ocultar-movel">E-mail</th></tr></thead>
            <tbody>
            <?php foreach ($linhas as $row): ?>
                <tr>
                    <td><strong><?= h($row['nome']) ?></strong></td>
                    <td class="num" style="text-align:left"><?= h($row['cpf']) ?></td>
                    <td class="num" style="text-align:left"><?= h($row['telefone']) ?></td>
                    <td class="ocultar-movel"><?= h($row['email']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <div class="vazio"><strong>Nenhum cliente cadastrado</strong><a href="cadastrar_cliente.php">Cadastrar o primeiro cliente</a></div>
    <?php endif; ?>
</section>
<?php rodape_pagina(); ?>
