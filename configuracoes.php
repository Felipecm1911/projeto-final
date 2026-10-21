<?php
require 'layout.php';
exigir_admin();

$result = $conn->query("SELECT * FROM configuracoes");
$linhas = $result->fetch_all(MYSQLI_ASSOC);

cabecalho_pagina('Configurações', 'configuracoes.php');
titulo_pagina('Configurações do sistema', 'Valores gravados na tabela de configurações.');
?>
<section class="painel" style="max-width: 820px">
    <?php if ($linhas): ?>
        <dl class="lista-chaves">
        <?php foreach ($linhas as $row): ?>
            <div><dt><?= h($row['chave']) ?></dt><dd><?= h($row['valor']) ?></dd></div>
        <?php endforeach; ?>
        </dl>
    <?php else: ?>
        <div class="vazio"><strong>Nenhuma configuração</strong>A tabela de configurações está vazia.</div>
    <?php endif; ?>
</section>
<?php rodape_pagina(); ?>
