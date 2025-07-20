-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 20/07/2025 às 06:48
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
-- Estrutura para tabela `cantores`
--

CREATE TABLE `cantores` (
  `id` int(11) NOT NULL,
  `nome_cantor` varchar(255) NOT NULL,
  `id_mesa` int(11) DEFAULT NULL,
  `proximo_ordem_musica` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `cantores`
--

INSERT INTO `cantores` (`id`, `nome_cantor`, `id_mesa`, `proximo_ordem_musica`) VALUES
(1, 'Weder', 1, 1),
(2, 'Dani', 1, 1),
(3, 'Maxwell', 1, 1),
(4, 'Julia', 1, 1),
(5, 'Ana', 1, 1),
(6, 'Daniel', 1, 1),
(7, 'Humberto', 1, 1),
(8, 'Judith', 1, 1),
(9, 'Carlos', 1, 1),
(10, 'Raquel', 1, 1),
(11, 'Hercules', 1, 1),
(12, 'Xandão', 2, 1),
(13, 'Kamilla', 6, 1),
(14, 'Dante', 7, 1),
(15, 'Inferno de Dante', 7, 1),
(16, 'Último', 7, 1),
(17, 'Federer', 4, 1);

-- --------------------------------------------------------

--
-- Estrutura para tabela `controle_rodada`
--

CREATE TABLE `controle_rodada` (
  `id` int(11) NOT NULL,
  `rodada_atual` int(11) DEFAULT 1,
  `ultima_atualizacao` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `controle_rodada`
--

INSERT INTO `controle_rodada` (`id`, `rodada_atual`, `ultima_atualizacao`) VALUES
(1, 1, '2025-07-20 01:35:47');

-- --------------------------------------------------------

--
-- Estrutura para tabela `fila_rodadas`
--

CREATE TABLE `fila_rodadas` (
  `id` int(11) NOT NULL,
  `id_cantor` int(11) NOT NULL,
  `id_musica` int(11) NOT NULL,
  `ordem_na_rodada` int(11) NOT NULL,
  `rodada` int(11) NOT NULL,
  `status` enum('aguardando','em_execucao','cantou','pulou') DEFAULT 'aguardando',
  `timestamp_adicao` datetime DEFAULT current_timestamp(),
  `timestamp_inicio_canto` datetime DEFAULT NULL,
  `timestamp_fim_canto` datetime DEFAULT NULL,
  `musica_cantor_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `mesas`
--

CREATE TABLE `mesas` (
  `id` int(11) NOT NULL,
  `nome_mesa` varchar(255) NOT NULL,
  `tamanho_mesa` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `mesas`
--

INSERT INTO `mesas` (`id`, `nome_mesa`, `tamanho_mesa`) VALUES
(1, 'Mesa 01', 11),
(2, 'Mesa 02', 3),
(3, 'Mesa 03', 3),
(4, 'Mesa 04', 3),
(5, 'Mesa 05', 1),
(6, 'Mesa 06', 1),
(7, 'Allighieri', 3);

-- --------------------------------------------------------

--
-- Estrutura para tabela `musicas`
--

CREATE TABLE `musicas` (
  `id` int(11) NOT NULL,
  `codigo` int(5) NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `artista` varchar(255) DEFAULT NULL,
  `trecho` text NOT NULL,
  `duracao_segundos` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `musicas`
--

INSERT INTO `musicas` (`id`, `codigo`, `titulo`, `artista`, `trecho`, `duracao_segundos`) VALUES
(1, 1036, 'Bohemian Rhapsody', 'Queen', 'Mama, aaah', 354),
(2, 3094, 'Evidências', 'Chitãozinho & Xororó', 'Há uma nuvem de lágrimas sobre os meus...', 270),
(3, 6030, 'Billie Jean', 'Michael Jackson', 'Billie Jean, stap my away...', 294),
(4, 3640, 'Garota de Ipanema', 'Tom Jobim & Vinicius de Moraes', 'Olha que coisa mais linda, mais...', 180),
(5, 25638, 'Anunciação', 'Alceu Valença', 'A bruma leve das paixões que vem de...', 190),
(6, 3333, 'Vai que dá', 'Weder Monteiro Araujo', 'Vai que dá, o máximo que pode acontecer é...', 3),
(7, 7845, 'Musica teste', 'Google', 'Google vai testar uma música, tá ligado mesmo ou não?', 6);

-- --------------------------------------------------------

--
-- Estrutura para tabela `musicas_cantor`
--

CREATE TABLE `musicas_cantor` (
  `id` int(11) NOT NULL,
  `id_cantor` int(11) NOT NULL,
  `id_musica` int(11) NOT NULL,
  `ordem_na_lista` int(11) NOT NULL DEFAULT 1,
  `status` enum('em_execucao','selecionada_para_rodada','cantou','pulou','aguardando') NOT NULL DEFAULT 'aguardando',
  `timestamp_ultima_execucao` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `musicas_cantor`
--

INSERT INTO `musicas_cantor` (`id`, `id_cantor`, `id_musica`, `ordem_na_lista`, `status`, `timestamp_ultima_execucao`) VALUES
(79, 1, 5, 2, 'aguardando', NULL),
(80, 1, 3, 4, 'aguardando', NULL),
(81, 1, 5, 3, 'aguardando', NULL),
(82, 1, 2, 5, 'aguardando', NULL),
(83, 2, 5, 1, 'aguardando', NULL),
(84, 2, 4, 2, 'aguardando', NULL),
(85, 13, 7, 1, 'aguardando', NULL),
(86, 16, 2, 1, 'aguardando', NULL),
(87, 16, 7, 2, 'aguardando', NULL),
(88, 1, 1, 1, 'aguardando', NULL);

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `cantores`
--
ALTER TABLE `cantores`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_cantor_mesa` (`id_mesa`);

--
-- Índices de tabela `controle_rodada`
--
ALTER TABLE `controle_rodada`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `fila_rodadas`
--
ALTER TABLE `fila_rodadas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_fila_cantor` (`id_cantor`),
  ADD KEY `fk_fila_musica` (`id_musica`);

--
-- Índices de tabela `mesas`
--
ALTER TABLE `mesas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nome_mesa` (`nome_mesa`);

--
-- Índices de tabela `musicas`
--
ALTER TABLE `musicas`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `musicas_cantor`
--
ALTER TABLE `musicas_cantor`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_musica` (`id_musica`),
  ADD KEY `idx_id_cantor` (`id_cantor`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `cantores`
--
ALTER TABLE `cantores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT de tabela `fila_rodadas`
--
ALTER TABLE `fila_rodadas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `mesas`
--
ALTER TABLE `mesas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de tabela `musicas`
--
ALTER TABLE `musicas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de tabela `musicas_cantor`
--
ALTER TABLE `musicas_cantor`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=89;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `cantores`
--
ALTER TABLE `cantores`
  ADD CONSTRAINT `fk_cantor_mesa` FOREIGN KEY (`id_mesa`) REFERENCES `mesas` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `fila_rodadas`
--
ALTER TABLE `fila_rodadas`
  ADD CONSTRAINT `fk_fila_cantor` FOREIGN KEY (`id_cantor`) REFERENCES `cantores` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_fila_musica` FOREIGN KEY (`id_musica`) REFERENCES `musicas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `musicas_cantor`
--
ALTER TABLE `musicas_cantor`
  ADD CONSTRAINT `musicas_cantor_ibfk_1` FOREIGN KEY (`id_cantor`) REFERENCES `cantores` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `musicas_cantor_ibfk_2` FOREIGN KEY (`id_musica`) REFERENCES `musicas` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
