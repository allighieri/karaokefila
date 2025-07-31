-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 31/07/2025 às 22:46
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
  `id_tenants` int(11) NOT NULL,
  `nome_cantor` varchar(255) NOT NULL,
  `id_mesa` int(11) DEFAULT NULL,
  `proximo_ordem_musica` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `cantores`
--

INSERT INTO `cantores` (`id`, `id_tenants`, `nome_cantor`, `id_mesa`, `proximo_ordem_musica`) VALUES
(55, 1, 'Weder', 58, 2),
(56, 1, 'Daniele', 58, 2),
(57, 1, 'Sandra', 58, 2),
(58, 1, 'Olívia', 58, 1),
(59, 1, 'Madalena', 58, 1),
(60, 1, 'Juca', 58, 1),
(61, 1, 'Kifuri', 58, 1),
(62, 1, 'Arnaldo', 59, 2),
(63, 1, 'Jabor', 59, 1),
(64, 1, 'Ruth', 60, 2);

-- --------------------------------------------------------

--
-- Estrutura para tabela `configuracao_regras_mesa`
--

CREATE TABLE `configuracao_regras_mesa` (
  `id` int(11) NOT NULL,
  `id_tenants` int(11) NOT NULL,
  `min_pessoas` int(11) NOT NULL,
  `max_pessoas` int(11) DEFAULT NULL,
  `max_musicas_por_rodada` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `configuracao_regras_mesa`
--

INSERT INTO `configuracao_regras_mesa` (`id`, `id_tenants`, `min_pessoas`, `max_pessoas`, `max_musicas_por_rodada`) VALUES
(1, 1, 1, 2, 1),
(2, 1, 3, 4, 2),
(3, 1, 5, NULL, 3);

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
(1, 1, 1, '2025-07-31 16:59:04');

-- --------------------------------------------------------

--
-- Estrutura para tabela `eventos`
--

CREATE TABLE `eventos` (
  `id` int(11) NOT NULL,
  `id_tenants` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` int(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `eventos`
--

INSERT INTO `eventos` (`id`, `id_tenants`, `nome`, `created_at`, `updated_at`, `status`) VALUES
(1, 1, 'Terça do Karaokê', '2025-07-31 19:36:55', '2025-07-31 19:36:55', 1);

-- --------------------------------------------------------

--
-- Estrutura para tabela `fila_rodadas`
--

CREATE TABLE `fila_rodadas` (
  `id` int(11) NOT NULL,
  `id_tenants` int(11) NOT NULL,
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

INSERT INTO `fila_rodadas` (`id`, `id_tenants`, `id_cantor`, `id_musica`, `ordem_na_rodada`, `rodada`, `status`, `timestamp_adicao`, `timestamp_inicio_canto`, `timestamp_fim_canto`, `musica_cantor_id`, `id_mesa`) VALUES
(1, 1, 55, 5, 1, 1, 'em_execucao', '2025-07-31 16:11:40', '2025-07-31 16:11:40', NULL, 72, 58),
(2, 1, 62, 5, 2, 1, 'aguardando', '2025-07-31 16:11:40', NULL, NULL, 68, 59),
(3, 1, 64, 6, 3, 1, 'aguardando', '2025-07-31 16:11:40', NULL, NULL, 82, 60),
(4, 1, 56, 5, 4, 1, 'aguardando', '2025-07-31 16:11:40', NULL, NULL, 77, 58),
(5, 1, 57, 4, 5, 1, 'aguardando', '2025-07-31 16:11:40', NULL, NULL, 80, 58);

-- --------------------------------------------------------

--
-- Estrutura para tabela `mesas`
--

CREATE TABLE `mesas` (
  `id` int(11) NOT NULL,
  `id_tenants` int(11) NOT NULL,
  `nome_mesa` varchar(255) NOT NULL,
  `tamanho_mesa` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `mesas`
--

INSERT INTO `mesas` (`id`, `id_tenants`, `nome_mesa`, `tamanho_mesa`) VALUES
(58, 1, 'Mesa 01', 7),
(59, 1, 'Mesa 02', 2),
(60, 1, 'Mesa 03', 1);

-- --------------------------------------------------------

--
-- Estrutura para tabela `musicas`
--

CREATE TABLE `musicas` (
  `id` int(11) NOT NULL,
  `id_tenants` int(11) NOT NULL,
  `codigo` int(5) NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `artista` varchar(255) DEFAULT NULL,
  `trecho` text NOT NULL,
  `duracao_segundos` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `musicas`
--

INSERT INTO `musicas` (`id`, `id_tenants`, `codigo`, `titulo`, `artista`, `trecho`, `duracao_segundos`) VALUES
(1, 1, 1036, 'Bohemian Rhapsody', 'Queen', 'Mama, aaah', 354),
(2, 1, 3094, 'Evidências', 'Chitãozinho & Xororó', 'Há uma nuvem de lágrimas sobre os meus...', 270),
(3, 1, 6030, 'Billie Jean', 'Michael Jackson', 'Billie Jean, stap my away...', 294),
(4, 1, 3640, 'Garota de Ipanema', 'Tom Jobim & Vinicius de Moraes', 'Olha que coisa mais linda, mais...', 180),
(5, 1, 25638, 'Anunciação', 'Alceu Valença', 'A bruma leve das paixões que vem de...', 190),
(6, 1, 3333, 'Vai que dá', 'Weder Monteiro Araujo', 'Vai que dá, o máximo que pode acontecer é...', 3),
(7, 1, 7845, 'Musica teste', 'Google', 'Google vai testar uma música, tá ligado mesmo ou não?', 6);

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
(68, 1, 62, 5, 1, 'selecionada_para_rodada', NULL),
(69, 1, 62, 3, 2, 'aguardando', NULL),
(70, 1, 63, 2, 1, 'aguardando', NULL),
(71, 1, 63, 7, 2, 'aguardando', NULL),
(72, 1, 55, 5, 1, 'em_execucao', '2025-07-31 16:11:40'),
(73, 1, 55, 3, 2, 'aguardando', NULL),
(74, 1, 55, 1, 3, 'aguardando', NULL),
(75, 1, 55, 1, 4, 'aguardando', NULL),
(76, 1, 55, 6, 5, 'aguardando', NULL),
(77, 1, 56, 5, 1, 'selecionada_para_rodada', NULL),
(78, 1, 60, 4, 1, 'aguardando', NULL),
(79, 1, 58, 2, 1, 'aguardando', NULL),
(80, 1, 57, 4, 1, 'selecionada_para_rodada', NULL),
(81, 1, 61, 7, 1, 'aguardando', NULL),
(82, 1, 64, 6, 1, 'selecionada_para_rodada', NULL),
(83, 1, 55, 2, 6, 'aguardando', NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `tenants`
--

CREATE TABLE `tenants` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `telefone` varchar(15) NOT NULL,
  `email` varchar(50) NOT NULL,
  `endereco` varchar(100) NOT NULL,
  `cidade` varchar(50) NOT NULL,
  `uf` varchar(2) NOT NULL,
  `status` int(1) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `tenants`
--

INSERT INTO `tenants` (`id`, `nome`, `telefone`, `email`, `endereco`, `cidade`, `uf`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Karaokê Clube', '(61) 99253-0902', 'agenciaolhardigital@gmail.com', 'QMSW 2 CONJ D LOJA 13 A, SUDOESTE', 'Brasília', 'DF', 1, '2025-07-31 19:31:36', '2025-07-31 19:31:36');

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `id_tenants` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `email` varchar(50) NOT NULL,
  `telefone` varchar(15) NOT NULL,
  `endereco` varchar(100) NOT NULL,
  `cidade` varchar(50) NOT NULL,
  `uf` varchar(2) NOT NULL,
  `status` int(1) NOT NULL DEFAULT 1,
  `nivel` enum('mc','user','admin','') NOT NULL DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `usuarios`
--

INSERT INTO `usuarios` (`id`, `id_tenants`, `nome`, `email`, `telefone`, `endereco`, `cidade`, `uf`, `status`, `nivel`, `created_at`, `updated_at`) VALUES
(1, 1, 'Weder Monteiro', 'agenciaolhardigital@gmail.com', '(61) 99253-0902', 'Q 7 Mr 9 Casa 33 Setor Leste', 'Planaltina', 'GO', 1, 'user', '2025-07-31 20:45:36', '2025-07-31 20:45:36');

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `cantores`
--
ALTER TABLE `cantores`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_cantor_mesa` (`id_mesa`),
  ADD KEY `id_tenants` (`id_tenants`);

--
-- Índices de tabela `configuracao_regras_mesa`
--
ALTER TABLE `configuracao_regras_mesa`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `min_pessoas` (`min_pessoas`),
  ADD KEY `id_tenants` (`id_tenants`);

--
-- Índices de tabela `controle_rodada`
--
ALTER TABLE `controle_rodada`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_tenants` (`id_tenants`);

--
-- Índices de tabela `eventos`
--
ALTER TABLE `eventos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `eventos_ibfk_1` (`id_tenants`);

--
-- Índices de tabela `fila_rodadas`
--
ALTER TABLE `fila_rodadas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_fila_cantor` (`id_cantor`),
  ADD KEY `fk_fila_musica` (`id_musica`),
  ADD KEY `fk_fila_mesa` (`id_mesa`),
  ADD KEY `id_tenants` (`id_tenants`);

--
-- Índices de tabela `mesas`
--
ALTER TABLE `mesas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nome_mesa` (`nome_mesa`),
  ADD KEY `id_tenants` (`id_tenants`);

--
-- Índices de tabela `musicas`
--
ALTER TABLE `musicas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_tenants` (`id_tenants`);

--
-- Índices de tabela `musicas_cantor`
--
ALTER TABLE `musicas_cantor`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_musica` (`id_musica`),
  ADD KEY `idx_id_cantor` (`id_cantor`),
  ADD KEY `id_eventos` (`id_eventos`);

--
-- Índices de tabela `tenants`
--
ALTER TABLE `tenants`
  ADD PRIMARY KEY (`id`);

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
-- AUTO_INCREMENT de tabela `cantores`
--
ALTER TABLE `cantores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- AUTO_INCREMENT de tabela `configuracao_regras_mesa`
--
ALTER TABLE `configuracao_regras_mesa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `eventos`
--
ALTER TABLE `eventos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `fila_rodadas`
--
ALTER TABLE `fila_rodadas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `mesas`
--
ALTER TABLE `mesas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

--
-- AUTO_INCREMENT de tabela `musicas`
--
ALTER TABLE `musicas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de tabela `musicas_cantor`
--
ALTER TABLE `musicas_cantor`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=84;

--
-- AUTO_INCREMENT de tabela `tenants`
--
ALTER TABLE `tenants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `cantores`
--
ALTER TABLE `cantores`
  ADD CONSTRAINT `cantores_ibfk_1` FOREIGN KEY (`id_tenants`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cantor_mesa` FOREIGN KEY (`id_mesa`) REFERENCES `mesas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Restrições para tabelas `configuracao_regras_mesa`
--
ALTER TABLE `configuracao_regras_mesa`
  ADD CONSTRAINT `configuracao_regras_mesa_ibfk_1` FOREIGN KEY (`id_tenants`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Restrições para tabelas `controle_rodada`
--
ALTER TABLE `controle_rodada`
  ADD CONSTRAINT `controle_rodada_ibfk_1` FOREIGN KEY (`id_tenants`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Restrições para tabelas `eventos`
--
ALTER TABLE `eventos`
  ADD CONSTRAINT `eventos_ibfk_1` FOREIGN KEY (`id_tenants`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Restrições para tabelas `fila_rodadas`
--
ALTER TABLE `fila_rodadas`
  ADD CONSTRAINT `fila_rodadas_ibfk_1` FOREIGN KEY (`id_tenants`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_fila_cantor` FOREIGN KEY (`id_cantor`) REFERENCES `cantores` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_fila_mesa` FOREIGN KEY (`id_mesa`) REFERENCES `mesas` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_fila_musica` FOREIGN KEY (`id_musica`) REFERENCES `musicas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `mesas`
--
ALTER TABLE `mesas`
  ADD CONSTRAINT `mesas_ibfk_1` FOREIGN KEY (`id_tenants`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Restrições para tabelas `musicas`
--
ALTER TABLE `musicas`
  ADD CONSTRAINT `musicas_ibfk_1` FOREIGN KEY (`id_tenants`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Restrições para tabelas `musicas_cantor`
--
ALTER TABLE `musicas_cantor`
  ADD CONSTRAINT `musicas_cantor_ibfk_2` FOREIGN KEY (`id_musica`) REFERENCES `musicas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `musicas_cantor_ibfk_3` FOREIGN KEY (`id_cantor`) REFERENCES `cantores` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `musicas_cantor_ibfk_4` FOREIGN KEY (`id_eventos`) REFERENCES `eventos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Restrições para tabelas `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`id_tenants`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `usuarios_ibfk_2` FOREIGN KEY (`id_tenants`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
