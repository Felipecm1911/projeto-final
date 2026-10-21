<?php
require 'layout.php';
exigir_somente_admin();

$result = $conn->query("SELECT * FROM usuarios ORDER BY nome");
$linhas = $result->fetch_all(MYSQLI_ASSOC);

cabecalho_pagina('Usuários', 'listar_usuarios.php');
titulo_pagina('Usuários do sistema', 'Quem pode entrar e o que cada um acessa. O nível Caixa vê apenas pedidos, produtos e caixa.', '<a class="botao" href="cadastrar_usuario.php">Cadastrar usuário</a>');
?>
<section class="painel">
    <?php if ($linhas): ?>
    <div class="tabela-wrap">
        <table id="tabela">
            <thead><tr><th>Nome</th><th>Usuário</th><th>Nível</th></tr></thead>
            <tbody>
            <?php foreach ($linhas as $row): ?>
                <tr>
                    <td><strong><?= h($row['nome']) ?></strong></td>
                    <td><?= h($row['usuario']) ?></td>
                    <td><span class="etiqueta <?= $row['nivel'] == 'admin' ? 'ok' : 'neutra' ?>"><?= h(ucfirst($row['nivel'])) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <div class="vazio"><strong>Nenhum usuário</strong><a href="cadastrar_usuario.php">Cadastrar usuário</a></div>
    <?php endif; ?>
</section>
<?php rodape_pagina(); ?>
