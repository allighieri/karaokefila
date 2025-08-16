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
-- Estrutura para tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `id_tenants` int(11) DEFAULT NULL,
  `nome` varchar(100) NOT NULL,
  `email` varchar(50) NOT NULL,
  `password` varchar(100) NOT NULL,
  `telefone` varchar(15) NOT NULL,
  `cidade` varchar(50) NOT NULL,
  `uf` varchar(2) NOT NULL,
  `status` int(1) NOT NULL DEFAULT 1,
  `nivel` enum('mc','user','admin','super_admin') NOT NULL DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `usuarios`
--

INSERT INTO `usuarios` (`id`, `id_tenants`, `nome`, `email`, `password`, `telefone`, `cidade`, `uf`, `status`, `nivel`, `created_at`, `updated_at`) VALUES
(1, 1, 'Weder Monteiro', 'agenciaolhardigital@gmail.com', '$2y$10$JPXMAntQt1C.o.7ayJYDve.ctGW44COcRaCuMTxN6F82qsA5Z/aUm', '(61) 99253-0902', 'Brasília', 'DF', 1, 'super_admin', '2025-08-04 19:52:40', '2025-08-14 21:28:19'),
(2, 4, 'Rosana', 'dnovaescastro@gmail.com', '$2y$10$M50WEhmPL8mMlvpW9zN5eO1DDfrV64f5umwS866/yxKLzeBrpP9yK', '(61) 99253-0902', 'Morrinhos', 'GO', 1, 'super_admin', '2025-08-04 22:14:20', '2025-08-14 01:17:25'),
(7, 1, 'Dani MC', 'dnovaescastro@gmail.com', '$2y$10$7eMFm9OTWGBz8WEG2TXghOJWs1AuQlsAVu3swfQwsRnwOT7PRMLTC', '(61) 99253-0902', 'Planaltina', 'RO', 1, 'mc', '2025-08-13 10:54:58', '2025-08-15 05:00:59'),
(8, 10, 'Maxwell', 'maxwell@gmail.com', '$2y$10$9GXh9F4IvGdgzni7iJo00OqtE4ILSIOyvPPdF.GE6ZdWvbIckznbK', '(61) 99253-0902', 'Rio Grande do Norte', 'RN', 1, 'mc', '2025-08-14 21:04:40', '2025-08-14 21:05:28'),
(9, 1, 'Cantor 1', 'user1@gmail.com', '$2y$10$Zvi2ZgFIM3os2ec8ZfZEAuPshOHS.IUh39d.f.462VHZ1Fg/qBEnq', '(99) 99999-9999', 'Cidade 1', 'C1', 1, 'user', '2025-08-14 22:09:11', '2025-08-15 22:11:13'),
(10, 1, 'Cantor 2', 'user2@gmail.com', '123456', '(99) 99999-9999', 'Cidade 1', 'C1', 1, 'user', '2025-08-14 22:10:46', '2025-08-14 22:10:46'),
(11, 1, 'Cantor 3', 'user3@gmail.com', '$2y$10$mVJRAy7MQFR0AZQxPrKz..Fj0IEgGCCSX.SqlFs4L/4mDIqDVPIKi', '(99) 99999-9999', 'Cidade 1', 'C1', 1, 'user', '2025-08-14 22:10:46', '2025-08-15 22:11:20'),
(12, 1, 'Cantor 4', 'user4@gmail.com', '$2y$10$xTergj9B20e.UNjN6BLeOe95iRZw/FJhNAs.E05X7UZxK09pJzfau', '(99) 99999-9999', 'Cidade 1', 'C1', 1, 'user', '2025-08-14 22:10:46', '2025-08-15 22:11:38'),
(13, 1, 'Claudio MC', 'claudio@gmail.com', '$2y$10$ma5Vsv70KJTPX4YTBN9mBeXferQv7BFrEy4djqfwoGvrfFh24Mxpy', '(99) 99999-9999', 'Cidade 1', 'C1', 1, 'mc', '2025-08-14 22:10:46', '2025-08-15 22:10:59'),
(15, 1, 'Admin Clube', 'clube@gmail.com', '$2y$10$/AHfP28.Vgl3e6msCfGIOeHqWg4umA8icYuChrY/wp/KDE0GDsF0a', '(99) 99999-9999', 'User 6', 'U6', 1, 'admin', '2025-08-15 00:11:41', '2025-08-15 00:11:41'),
(16, 4, 'Julia', 'julia@gmail.com', '$2y$10$ds9ksFE/R.GFYsuPZh8QvuWWGaK.IpOwAh.YlBcIYS2s46Yr/1Q8K', '(99) 99999-9999', 'Julia', 'JU', 1, 'mc', '2025-08-15 00:27:14', '2025-08-15 00:27:14'),
(17, 4, 'Vou cantar', 'user10@gmail.com', '$2y$10$YJRe9UhpB58r8H7jgE21DuN6hJ4J8gxU4zd/hTTsFnvcgMFxAJ4sq', '(99) 99999-9999', 'user', 'us', 1, 'user', '2025-08-15 00:29:05', '2025-08-15 00:29:05'),
(18, 4, 'Admin Cemter', 'center@gmail.com', '$2y$10$5edzoPFx6wrrWfJ3.bjFxuV2qektKpHk4G1BiViOevXRasweRtoRu', '(11) 11111-1111', 'dfasdfa fdasfa', 'df', 1, 'admin', '2025-08-15 00:31:09', '2025-08-15 00:31:09'),
(19, 10, 'Cantor Dani 01', 'dani1@gmail.com', '$2y$10$PbuxSg5FS6/jsY/mxziLR.r5tZ0R3lqnfEAUIP1sq6wN.vgS6VPje', '(61) 99253-0902', 'DAni', 'DF', 1, 'user', '2025-08-15 22:03:54', '2025-08-15 22:03:54'),
(20, 10, 'Cantor Dani 02', 'dani2@gmail.com', '$2y$10$KuKY8bWhtuek3VBhyxz0yuw/inetI7rlE9hcxaaP70QXBQFGky1kO', '(55) 55555-5555', 'Cdiade Dani', 'DF', 1, 'user', '2025-08-15 22:04:41', '2025-08-15 22:04:41');

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_tenants` (`id_tenants`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`id_tenants`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
