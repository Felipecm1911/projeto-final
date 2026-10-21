<?php
require 'layout.php';
exigir_admin();

$result = $conn->query("SELECT * FROM fornecedores ORDER BY nome");
$linhas = $result->fetch_all(MYSQLI_ASSOC);

cabecalho_pagina('Fornecedores', 'listar_fornecedores.php');
titulo_pagina('Fornecedores', count($linhas) . ' fornecedores cadastrados.', '<a class="botao" href="cadastrar_fornecedor.php">Cadastrar fornecedor</a>');
?>
<section class="painel">
    <div class="filtros">
        <div class="campo busca">
            <label for="busca">Buscar</label>
            <input type="search" id="busca" data-filtrar="tabela" placeholder="Nome, CNPJ ou telefone">
        </div>
    </div>
    <?php if ($linhas): ?>
    <div class="tabela-wrap">
        <table id="tabela">
            <thead><tr><th>Nome</th><th>CNPJ</th><th>Telefone</th><th class="ocultar-movel">E-mail</th></tr></thead>
            <tbody>
            <?php foreach ($linhas as $row): ?>
                <tr>
                    <td><strong><?= h($row['nome']) ?></strong></td>
                    <td class="num" style="text-align:left"><?= h($row['cnpj']) ?></td>
                    <td class="num" style="text-align:left"><?= h($row['telefone']) ?></td>
                    <td class="ocultar-movel"><?= h($row['email']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <div class="vazio"><strong>Nenhum fornecedor cadastrado</strong><a href="cadastrar_fornecedor.php">Cadastrar o primeiro fornecedor</a></div>
    <?php endif; ?>
</section>
<?php rodape_pagina(); ?>
