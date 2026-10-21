<?php
// Padrão do XAMPP. Para não usar root, crie o usuário erva_app (bloco opcional no
// fim do banco_completo.sql) e troque $user e $pass abaixo.
$host = "localhost";
$user = "root";
$pass = "";
$db = getenv('ERVA_DB') ?: "erva_mate_db"; // ERVA_DB troca o banco (usado para testes)

// Erro de banco vira exceção (padrão só a partir do PHP 8.1; o XAMPP daqui é 8.0).
// Sem isso, uma gravação que falha no meio do pedido passa em silêncio.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($host, $user, $pass, $db);
} catch (mysqli_sql_exception $e) {
    error_log($e);
    http_response_code(500);
    die('<p style="font-family:sans-serif;padding:24px">Não foi possível conectar ao banco de dados. Confira se o MySQL do XAMPP está ligado e se o banco_completo.sql foi importado.</p>');
}
?>
