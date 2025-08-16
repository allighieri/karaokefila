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
-- Estrutura para tabela `musicas_cantor`
--

CREATE TABLE `musicas_cantor` (
  `id` int(11) NOT NULL,
  `id_eventos` int(11) NOT NULL,
  `id_cantor` int(11) NOT NULL,
  `id_musica` int(11) NOT NULL,
  `ordem_na_lista` int(11) NOT NULL DEFAULT 1,
  `status` enum('em_execucao','selecionada_para_rodada','cantou','pulou','aguardando') NOT NULL DEFAULT 'aguardando',
  `timestamp_ultima_execucao` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `musicas_cantor`
--

INSERT INTO `musicas_cantor` (`id`, `id_eventos`, `id_cantor`, `id_musica`, `ordem_na_lista`, `status`, `timestamp_ultima_execucao`) VALUES
(1, 1, 1, 222, 1, 'aguardando', NULL),
(2, 1, 1, 2268, 2, 'aguardando', '2025-08-15 19:08:41'),
(3, 1, 2, 640, 1, 'aguardando', NULL),
(4, 2, 3, 10606, 1, 'selecionada_para_rodada', NULL),
(5, 2, 3, 6147, 2, 'aguardando', NULL),
(6, 1, 3, 10604, 1, 'aguardando', NULL),
(7, 2, 2, 222, 1, 'em_execucao', '2025-08-15 19:14:52'),
(8, 5, 4, 32762, 1, 'em_execucao', '2025-08-15 20:17:31'),
(9, 5, 4, 37646, 3, 'aguardando', NULL),
(10, 5, 4, 37653, 2, 'aguardando', NULL),
(11, 5, 5, 30658, 1, 'selecionada_para_rodada', NULL),
(12, 5, 6, 29036, 1, 'selecionada_para_rodada', NULL);

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `musicas_cantor`
--
ALTER TABLE `musicas_cantor`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_musica` (`id_musica`),
  ADD KEY `idx_id_cantor` (`id_cantor`),
  ADD KEY `id_eventos` (`id_eventos`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `musicas_cantor`
--
ALTER TABLE `musicas_cantor`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `musicas_cantor`
--
ALTER TABLE `musicas_cantor`
  ADD CONSTRAINT `musicas_cantor_ibfk_2` FOREIGN KEY (`id_musica`) REFERENCES `musicas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `musicas_cantor_ibfk_3` FOREIGN KEY (`id_cantor`) REFERENCES `cantores` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `musicas_cantor_ibfk_4` FOREIGN KEY (`id_eventos`) REFERENCES `eventos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
