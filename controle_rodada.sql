-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 05/08/2025 às 01:31
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
-- Estrutura para tabela `controle_rodada`
--

CREATE TABLE `controle_rodada` (
  `id` int(11) NOT NULL,
  `id_tenants` int(11) NOT NULL,
  `rodada_atual` int(11) DEFAULT 1,
  `ultima_atualizacao` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `controle_rodada`
--

INSERT INTO `controle_rodada` (`id`, `id_tenants`, `rodada_atual`, `ultima_atualizacao`) VALUES
(1, 1, 1, '2025-08-04 20:07:10');

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `controle_rodada`
--
ALTER TABLE `controle_rodada`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_tenants` (`id_tenants`);

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `controle_rodada`
--
ALTER TABLE `controle_rodada`
  ADD CONSTRAINT `controle_rodada_ibfk_1` FOREIGN KEY (`id_tenants`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
