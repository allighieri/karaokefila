-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 16/08/2025 às 01:21
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `karaokefila`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `eventos`
--

CREATE TABLE `eventos` (
  `id` int(11) NOT NULL,
  `id_tenants` int(11) NOT NULL,
  `id_usuario_mc` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `status` enum('ativo','inativo') DEFAULT 'ativo',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Tabela de eventos de karaok?? - cada MC pode ter um evento ativo por vez';

--
-- Despejando dados para a tabela `eventos`
--

INSERT INTO `eventos` (`id`, `id_tenants`, `id_usuario_mc`, `nome`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 7, 'evento1 dani', 'ativo', '2025-08-15 05:01:17', '2025-08-15 05:01:17'),
(2, 1, 13, 'Evento1 claudio', 'ativo', '2025-08-15 05:01:45', '2025-08-15 05:01:45'),
(3, 1, 7, 'evento2 dani', 'inativo', '2025-08-15 05:01:55', '2025-08-15 05:01:55'),
(4, 10, 8, 'Oke Dani', 'inativo', '2025-08-15 22:05:27', '2025-08-15 22:05:27'),
(5, 10, 8, 'Oke Dani 2', 'ativo', '2025-08-15 22:05:36', '2025-08-15 22:05:36');

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `eventos`
--
ALTER TABLE `eventos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_eventos_nome_tenant` (`nome`,`id_tenants`),
  ADD KEY `eventos_ibfk_1` (`id_tenants`),
  ADD KEY `idx_eventos_mc_status` (`id_usuario_mc`,`status`),
  ADD KEY `idx_eventos_tenant_status` (`id_tenants`,`status`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `eventos`
--
ALTER TABLE `eventos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `eventos`
--
ALTER TABLE `eventos`
  ADD CONSTRAINT `eventos_ibfk_1` FOREIGN KEY (`id_tenants`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_eventos_usuario_mc` FOREIGN KEY (`id_usuario_mc`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
