# Sistema de gestão · Erva Mate

Projeto final.

Sistema web para uma loja de erva-mate controlar **pedidos, estoque, caixa e clientes**, feito em PHP e MySQL.

## O que o sistema faz

- **Painel** com saldo do caixa, vendas do dia e do mês, gráfico dos últimos 7 dias e produtos para repor
- **Pedidos**: escolhe cliente, produto e quantidade; o pedido baixa o estoque e lança a venda no caixa, tudo de uma vez (se algo falhar, nada é gravado)
- **Estoque**: produtos, entradas de mercadoria (somam ao estoque), saídas e relatório com custo, valor de venda e lucro estocado
- **Caixa**: entradas e saídas com saldo
- **Lucro por venda**, calculado com o custo do produto no dia da venda
- **Relatório em Excel** (.xlsx) com várias abas, fórmulas e gráficos
- **Usuários com níveis**: admin (tudo), suporte e caixa (só pedidos, produtos e caixa)

## Segurança

- Senhas guardadas com `password_hash`
- Consultas com *prepared statements* (protege contra SQL injection)
- Tudo que vai para a tela passa por `htmlspecialchars` (protege contra XSS)
- Token CSRF em todos os formulários e sessão nova a cada login
- Validação no servidor: quantidade, estoque, valores e nível de acesso

## Como rodar (XAMPP)

1. Copie a pasta do projeto para `htdocs` do XAMPP (por exemplo `htdocs/final`).
2. Ligue o **Apache** e o **MySQL** no painel do XAMPP.
3. No phpMyAdmin, vá em **Importar** e escolha `banco_completo.sql`. Ele cria o banco `erva_mate_db`, as tabelas, as views e alguns dados de exemplo, sem apagar nada que já exista.
4. Instale as bibliotecas do relatório em Excel, na pasta do projeto:
   ```
   composer install
   ```
5. Abra `http://localhost/final/login.html` e entre com:
   - usuário: `admin`
   - senha: `123456`

   Troque a senha depois de entrar (link **Senha**, no menu).

A conexão com o banco fica em `conexao.php` (padrão do XAMPP: usuário `root`, sem senha).

## Testes

Pelo terminal, com o banco importado:

```
php testes/teste_pedido.php
php testes/teste_relatorio.php
```

## Tecnologias

PHP 8, MySQL/MariaDB, HTML, CSS, JavaScript, [GSAP](https://gsap.com/) para as animações e [PhpSpreadsheet](https://github.com/PHPOffice/PhpSpreadsheet) para o Excel.

## Créditos

Fotos da tela de login: [Pexels](https://www.pexels.com/) (botanphotography, Cristian Rojas, davincidelasfotos, Palu Malerba, Tamara Mangold), sob a licença do Pexels.
