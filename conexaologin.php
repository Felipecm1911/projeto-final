<?php
session_start();
include 'conexao.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario = $_POST['usuario'] ?? '';
    $senha = $_POST['senha'] ?? '';

    if (empty($usuario) || empty($senha)) {
        header("Location: login.html?erro=vazio");
        exit;
    }

    $sql = "SELECT * FROM usuarios WHERE usuario = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $result = $stmt->get_result();

    $user = $result->fetch_assoc();
    // Mesma resposta para usuário e senha errados: não entrega quais usuários existem
    if ($user && password_verify($senha, $user['senha'])) {
        session_regenerate_id(true); // sessão nova a cada login
        $_SESSION['usuario'] = $user['usuario'];
        $_SESSION['nivel'] = $user['nivel'];
        header("Location: painel.php");
        exit;
    }
    header("Location: login.html?erro=login");
    exit;
} else {
    header("Location: login.html");
    exit;
}
?>
