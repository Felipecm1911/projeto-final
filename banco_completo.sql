-- ==========================================================
-- Erva Mate — banco completo do sistema
-- Pode importar no phpMyAdmin com segurança: não apaga nada.
-- Tabelas que já existem são mantidas; dados de exemplo só
-- entram se o id ainda não existir (INSERT IGNORE).
-- ==========================================================

SET NAMES utf8mb4;
CREATE DATABASE IF NOT EXISTS `erva_mate_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `erva_mate_db`;

-- ---------- Tabelas ----------

CREATE TABLE IF NOT EXISTS `clientes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) DEFAULT NULL,
  `cpf` varchar(14) DEFAULT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `fornecedores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) DEFAULT NULL,
  `cnpj` varchar(18) DEFAULT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) DEFAULT NULL,
  `usuario` varchar(50) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `nivel` varchar(20) NOT NULL DEFAULT 'caixa',
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario` (`usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `produtos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `preco_compra` decimal(10,2) NOT NULL DEFAULT 0.00,
  `preco_venda` decimal(10,2) NOT NULL DEFAULT 0.00,
  `estoque` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `pedidos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cliente_id` int(11) NOT NULL,
  `valor_total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `data_pedido` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `cliente_id` (`cliente_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `pedido_itens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pedido_id` int(11) NOT NULL,
  `produto_id` int(11) NOT NULL,
  `quantidade` int(11) NOT NULL,
  `preco_unitario` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `pedido_id` (`pedido_id`),
  KEY `produto_id` (`produto_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `saidas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `produto_id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `quantidade` int(11) NOT NULL,
  `valor_total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `data_saida` datetime NOT NULL DEFAULT current_timestamp(),
  `preco_compra` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `produto_id` (`produto_id`),
  KEY `cliente_id` (`cliente_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `entradas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `produto_id` int(11) NOT NULL,
  `fornecedor_id` int(11) DEFAULT NULL,
  `quantidade` int(11) NOT NULL,
  `data_entrada` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `produto_id` (`produto_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `caixa` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo` enum('entrada','saida') NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `data_movimentacao` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `configuracoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `chave` varchar(50) NOT NULL,
  `valor` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `chave` (`chave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Custo do produto no momento da venda (para o lucro antigo não mudar
-- quando o preço de compra for alterado). Seguro em bancos já existentes.
ALTER TABLE `saidas` ADD COLUMN IF NOT EXISTS `preco_compra` decimal(10,2) DEFAULT NULL;

-- Chaves estrangeiras: o banco recusa apagar um cliente ou produto que tem
-- pedidos, e recusa gravar ids que não existem. Seguro em bancos já existentes.
ALTER TABLE `pedidos`
  ADD CONSTRAINT `fk_pedidos_cliente` FOREIGN KEY IF NOT EXISTS (`cliente_id`) REFERENCES `clientes` (`id`);
ALTER TABLE `pedido_itens`
  ADD CONSTRAINT `fk_itens_pedido` FOREIGN KEY IF NOT EXISTS (`pedido_id`) REFERENCES `pedidos` (`id`),
  ADD CONSTRAINT `fk_itens_produto` FOREIGN KEY IF NOT EXISTS (`produto_id`) REFERENCES `produtos` (`id`);
ALTER TABLE `saidas`
  ADD CONSTRAINT `fk_saidas_produto` FOREIGN KEY IF NOT EXISTS (`produto_id`) REFERENCES `produtos` (`id`),
  ADD CONSTRAINT `fk_saidas_cliente` FOREIGN KEY IF NOT EXISTS (`cliente_id`) REFERENCES `clientes` (`id`);
ALTER TABLE `entradas`
  ADD CONSTRAINT `fk_entradas_produto` FOREIGN KEY IF NOT EXISTS (`produto_id`) REFERENCES `produtos` (`id`),
  ADD CONSTRAINT `fk_entradas_fornecedor` FOREIGN KEY IF NOT EXISTS (`fornecedor_id`) REFERENCES `fornecedores` (`id`);

-- As telas fazem as consultas direto nas tabelas (sem views), porque
-- hospedagens grátis costumam não permitir CREATE VIEW.

-- ---------- Dados iniciais ----------

-- Login: admin / 123456. Troque no primeiro acesso, no link "Senha" do menu.
INSERT IGNORE INTO `usuarios` (`nome`, `usuario`, `senha`, `nivel`) VALUES
('Administrador', 'admin', '$2y$10$hFGkpulKzYko6O/QtM07cOV8SdLSf1KVCcThbc7hacSfxoEUl02ou', 'admin');

INSERT IGNORE INTO `clientes` (`id`, `nome`, `cpf`, `telefone`, `email`) VALUES
(1, 'João da Silva', '123.456.789-00', '54999999999', 'joao@email.com'),
(2, 'Maria Oliveira', '234.567.890-11', '54888888888', 'maria@email.com'),
(3, 'Pedro Mendes', '345.678.901-22', '54777777777', 'pedro@email.com'),
(4, 'Luciana Costa', '456.789.012-33', '54666666666', 'luciana@email.com');

-- Exemplos (pode apagar ou trocar)
INSERT IGNORE INTO `produtos` (`id`, `nome`, `descricao`, `preco_compra`, `preco_venda`, `estoque`) VALUES
(1, 'Erva-mate tradicional 1kg', 'Moagem grossa, embalada a vácuo', 14.50, 24.90, 80),
(2, 'Erva-mate moída fina 1kg', 'Para chimarrão', 15.20, 26.50, 40),
(3, 'Erva-mate nativa 500g', 'Sombreada', 11.00, 19.90, 7),
(4, 'Erva para tereré 500g', 'Com limão', 8.90, 16.00, 0),
(5, 'Cuia de porongo', 'Natural', 18.00, 39.90, 15);

INSERT IGNORE INTO `fornecedores` (`id`, `nome`, `cnpj`, `telefone`, `email`) VALUES
(1, 'Ervateira Exemplo Ltda', '00.000.000/0001-00', '5433330000', 'contato@exemplo.com');

-- estoque_minimo: abaixo disso o produto aparece como "Baixo" e no "Precisa repor"
INSERT IGNORE INTO `configuracoes` (`chave`, `valor`) VALUES
('estoque_minimo', '10');
DELETE FROM `configuracoes` WHERE `chave` IN ('nome_empresa', 'moeda');

-- ---------- Opcional: usuário próprio do sistema ----------
-- Para o PHP não conectar como root. Tire o "-- " das linhas abaixo, rode no
-- phpMyAdmin e depois troque $user/$pass no conexao.php por erva_app e a senha.
-- Troque TROQUE_ESTA_SENHA por uma senha sua antes de rodar.
-- (Precisa que as tabelas internas do MySQL estejam OK.)
-- CREATE USER IF NOT EXISTS 'erva_app'@'localhost' IDENTIFIED BY 'TROQUE_ESTA_SENHA';
-- CREATE USER IF NOT EXISTS 'erva_app'@'127.0.0.1' IDENTIFIED BY 'TROQUE_ESTA_SENHA';
-- CREATE USER IF NOT EXISTS 'erva_app'@'::1' IDENTIFIED BY 'TROQUE_ESTA_SENHA';
-- ALTER USER 'erva_app'@'localhost' IDENTIFIED BY 'TROQUE_ESTA_SENHA';
-- ALTER USER 'erva_app'@'127.0.0.1' IDENTIFIED BY 'TROQUE_ESTA_SENHA';
-- ALTER USER 'erva_app'@'::1' IDENTIFIED BY 'TROQUE_ESTA_SENHA';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON `erva_mate_db`.* TO 'erva_app'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON `erva_mate_db`.* TO 'erva_app'@'127.0.0.1';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON `erva_mate_db`.* TO 'erva_app'@'::1';
-- FLUSH PRIVILEGES;
