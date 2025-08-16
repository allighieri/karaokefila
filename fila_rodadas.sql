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
-- Estrutura para tabela `fila_rodadas`
--

CREATE TABLE `fila_rodadas` (
  `id` int(11) NOT NULL,
  `id_tenants` int(11) NOT NULL,
  `id_eventos` int(11) NOT NULL,
  `id_cantor` int(11) NOT NULL,
  `id_musica` int(11) NOT NULL,
  `ordem_na_rodada` int(11) NOT NULL,
  `rodada` int(11) NOT NULL,
  `status` enum('aguardando','em_execucao','cantou','pulou','selecionada_para_rodada') DEFAULT 'aguardando',
  `timestamp_adicao` datetime DEFAULT current_timestamp(),
  `timestamp_inicio_canto` datetime DEFAULT NULL,
  `timestamp_fim_canto` datetime DEFAULT NULL,
  `musica_cantor_id` int(11) DEFAULT NULL,
  `id_mesa` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `fila_rodadas`
--

INSERT INTO `fila_rodadas` (`id`, `id_tenants`, `id_eventos`, `id_cantor`, `id_musica`, `ordem_na_rodada`, `rodada`, `status`, `timestamp_adicao`, `timestamp_inicio_canto`, `timestamp_fim_canto`, `musica_cantor_id`, `id_mesa`) VALUES
(4, 1, 1, 1, 25884, 3, 1, 'em_execucao', '2025-08-15 19:08:41', '2025-08-15 19:08:41', NULL, NULL, 71),
(9, 1, 2, 2, 222, 2, 1, 'em_execucao', '2025-08-15 19:14:52', '2025-08-15 19:14:52', NULL, 7, 71),
(10, 1, 2, 3, 10606, 6, 1, 'aguardando', '2025-08-15 19:14:52', NULL, NULL, 4, 73),
(34, 10, 5, 4, 32762, 1, 1, 'em_execucao', '2025-08-15 20:17:31', '2025-08-15 20:17:31', NULL, 8, 77),
(35, 10, 5, 5, 30658, 4, 1, 'aguardando', '2025-08-15 20:17:31', NULL, NULL, 11, 78),
(36, 10, 5, 6, 29036, 5, 1, 'aguardando', '2025-08-15 20:17:31', NULL, NULL, 12, 79);

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `fila_rodadas`
--
ALTER TABLE `fila_rodadas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_fila_cantor` (`id_cantor`),
  ADD KEY `fk_fila_musica` (`id_musica`),
  ADD KEY `fk_fila_mesa` (`id_mesa`),
  ADD KEY `id_tenants` (`id_tenants`),
  ADD KEY `musica_cantor_id` (`musica_cantor_id`),
  ADD KEY `idx_fila_rodadas_id_eventos` (`id_eventos`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `fila_rodadas`
--
ALTER TABLE `fila_rodadas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `fila_rodadas`
--
ALTER TABLE `fila_rodadas`
  ADD CONSTRAINT `fila_rodadas_ibfk_1` FOREIGN KEY (`id_tenants`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fila_rodadas_ibfk_2` FOREIGN KEY (`musica_cantor_id`) REFERENCES `musicas_cantor` (`id`),
  ADD CONSTRAINT `fk_fila_cantor` FOREIGN KEY (`id_cantor`) REFERENCES `cantores` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_fila_mesa` FOREIGN KEY (`id_mesa`) REFERENCES `mesas` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_fila_musica` FOREIGN KEY (`id_musica`) REFERENCES `musicas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_fila_rodadas_eventos` FOREIGN KEY (`id_eventos`) REFERENCES `eventos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
