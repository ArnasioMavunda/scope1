-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 12-Maio-2026 às 15:06
-- Versão do servidor: 10.4.32-MariaDB
-- versão do PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `scope`
--

-- --------------------------------------------------------

--
-- Estrutura da tabela `alunos`
--

CREATE TABLE `alunos` (
  `id` int(11) NOT NULL,
  `nome` varchar(150) NOT NULL,
  `num_processo` varchar(20) DEFAULT NULL,
  `rfid_id` varchar(20) DEFAULT NULL,
  `turma_id` int(11) NOT NULL,
  `foto` varchar(200) DEFAULT NULL,
  `contacto_encarregado` varchar(50) DEFAULT NULL,
  `email_encarregado` varchar(100) DEFAULT NULL,
  `ativo` tinyint(4) NOT NULL DEFAULT 1,
  `criado_em` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `alunos`
--

INSERT INTO `alunos` (`id`, `nome`, `num_processo`, `rfid_id`, `turma_id`, `foto`, `contacto_encarregado`, `email_encarregado`, `ativo`, `criado_em`) VALUES
(1, 'Arnásio Malundo Da Conceição Mavunda', '1650', '4682816', 1, NULL, NULL, NULL, 1, '2026-03-10 01:23:42'),
(2, 'Elsa Lalissa Júlio', '06', '4710756', 1, NULL, NULL, NULL, 1, '2026-03-10 01:23:42'),
(3, 'Domingos Caselo Kalwiji Samanjata', '2027', '4713470', 1, NULL, NULL, NULL, 1, '2026-03-10 01:23:42'),
(4, 'Haziel Simbovala Chitau Hamuyela Tchitawila', '2543', '4732271', 1, NULL, NULL, NULL, 1, '2026-03-10 01:23:42'),
(5, 'Larissa Fato Botelho', '260', '4737708', 1, NULL, NULL, NULL, 1, '2026-03-10 01:23:42'),
(6, 'Luís Caison Zango Caíca', '774', '4742167', 1, NULL, NULL, NULL, 1, '2026-03-10 01:23:42'),
(7, 'Maria Da Conceição José Pena', '2556', '5882869', 1, NULL, NULL, NULL, 1, '2026-03-10 01:23:42'),
(8, 'Jorgina Jorge', '05', '5885635', 1, NULL, NULL, NULL, 1, '2026-05-01 17:13:02'),
(9, 'Representante da Escola de Tutela', '10', '5968616', 1, NULL, NULL, NULL, 1, '2026-05-09 20:07:48');

-- --------------------------------------------------------

--
-- Estrutura da tabela `configuracoes`
--

CREATE TABLE `configuracoes` (
  `chave` varchar(100) NOT NULL,
  `valor` text NOT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `configuracoes`
--

INSERT INTO `configuracoes` (`chave`, `valor`, `updated_at`) VALUES
('device_offline_timeout_seconds', '7200', '2026-05-07 10:07:59'),
('device_online_at', '2026-05-07 13:37:27', '2026-05-07 13:37:27');

-- --------------------------------------------------------

--
-- Estrutura da tabela `disciplinas`
--

CREATE TABLE `disciplinas` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `disciplinas`
--

INSERT INTO `disciplinas` (`id`, `nome`) VALUES
(1, 'Matemática'),
(2, 'Física'),
(3, 'Química'),
(4, 'Biologia'),
(5, 'Geologia'),
(6, 'Língua Portuguesa'),
(7, 'Língua Inglesa'),
(8, 'Filosofia'),
(9, 'Geometria Descritiva'),
(10, 'Empreendedorismo');

-- --------------------------------------------------------

--
-- Estrutura da tabela `horario`
--

CREATE TABLE `horario` (
  `id` int(11) NOT NULL,
  `turma_id` int(11) NOT NULL,
  `disciplina_id` int(11) NOT NULL,
  `professor_id` int(11) NOT NULL,
  `dia_semana` tinyint(4) NOT NULL COMMENT '2=Seg 3=Ter 4=Qua 5=Qui 6=Sex',
  `tempo` tinyint(4) NOT NULL COMMENT '1 a 6',
  `bloco` tinyint(4) NOT NULL COMMENT '1=(T1T2) 2=(T3T4) 3=(T5T6)',
  `hora_inicio` time NOT NULL,
  `hora_fim` time NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `horario`
--

INSERT INTO `horario` (`id`, `turma_id`, `disciplina_id`, `professor_id`, `dia_semana`, `tempo`, `bloco`, `hora_inicio`, `hora_fim`) VALUES
(1, 1, 1, 1, 2, 1, 1, '07:30:00', '08:15:00'),
(2, 1, 1, 1, 2, 2, 1, '08:15:00', '09:00:00'),
(3, 1, 4, 4, 2, 3, 2, '09:15:00', '10:00:00'),
(4, 1, 4, 4, 2, 4, 2, '10:00:00', '10:45:00'),
(5, 1, 3, 3, 2, 5, 3, '11:00:00', '11:45:00'),
(6, 1, 3, 3, 2, 6, 3, '11:45:00', '12:30:00'),
(7, 1, 1, 1, 3, 1, 1, '13:00:00', '13:45:00'),
(8, 1, 1, 1, 3, 2, 1, '13:45:00', '14:30:00'),
(9, 1, 5, 5, 3, 3, 2, '14:45:00', '15:30:00'),
(10, 1, 5, 5, 3, 4, 2, '15:30:00', '16:15:00'),
(11, 1, 4, 4, 3, 5, 3, '16:30:00', '17:15:00'),
(12, 1, 4, 4, 3, 6, 3, '17:15:00', '18:00:00'),
(13, 1, 2, 2, 4, 1, 1, '13:00:00', '13:45:00'),
(14, 1, 2, 2, 4, 2, 1, '13:45:00', '14:30:00'),
(15, 1, 3, 3, 4, 3, 2, '14:45:00', '15:30:00'),
(16, 1, 3, 3, 4, 4, 2, '15:30:00', '16:15:00'),
(17, 1, 9, 6, 4, 5, 3, '16:30:00', '17:15:00'),
(18, 1, 9, 6, 4, 6, 3, '17:15:00', '18:00:00'),
(19, 1, 7, 8, 5, 1, 1, '07:30:00', '08:15:00'),
(20, 1, 7, 8, 5, 2, 1, '08:15:00', '09:00:00'),
(21, 1, 2, 2, 5, 3, 2, '09:15:00', '10:00:00'),
(22, 1, 2, 2, 5, 4, 2, '10:00:00', '10:45:00'),
(23, 1, 8, 7, 5, 5, 3, '11:00:00', '11:45:00'),
(24, 1, 8, 7, 5, 6, 3, '11:45:00', '12:30:00'),
(25, 1, 6, 9, 6, 1, 1, '13:00:00', '13:45:00'),
(26, 1, 6, 9, 6, 2, 1, '13:45:00', '14:30:00'),
(27, 1, 10, 10, 6, 3, 2, '14:45:00', '15:30:00'),
(28, 1, 10, 10, 6, 4, 2, '15:30:00', '16:15:00'),
(29, 1, 7, 8, 6, 5, 3, '16:30:00', '17:15:00'),
(30, 1, 7, 8, 6, 6, 3, '17:15:00', '18:00:00');

-- --------------------------------------------------------

--
-- Estrutura da tabela `ocorrencias`
--

CREATE TABLE `ocorrencias` (
  `id` int(11) NOT NULL,
  `turma_id` int(11) NOT NULL,
  `professor_id` int(11) NOT NULL,
  `data` date NOT NULL,
  `tempo` tinyint(4) NOT NULL,
  `descricao` text NOT NULL,
  `criado_em` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `ocorrencias`
--

INSERT INTO `ocorrencias` (`id`, `turma_id`, `professor_id`, `data`, `tempo`, `descricao`, `criado_em`) VALUES
(1, 1, 1, '2026-03-10', 1, 'Tiveram um bom comportamento', '2026-03-10 14:19:34'),
(2, 1, 1, '2026-03-10', 1, 'Tiveram um bom comportamento', '2026-03-10 14:19:37'),
(3, 1, 4, '2026-03-10', 2, 'bom', '2026-03-10 15:50:48'),
(4, 1, 1, '2026-03-11', 3, '[Sumário] Introdução ao Desenho Técnico', '2026-03-11 17:06:29'),
(5, 1, 1, '2026-05-05', 3, '[Sumário] fewfefewdfwe', '2026-05-05 17:30:09'),
(6, 1, 1, '2026-05-05', 3, 'Bom Aluno', '2026-05-05 17:30:37'),
(7, 1, 1, '2026-05-05', 3, 'NKKN', '2026-05-05 17:30:47'),
(8, 1, 1, '2026-05-09', 1, '[Sumário] sfgsf', '2026-05-09 19:49:18'),
(9, 1, 1, '2026-05-09', 1, 'good', '2026-05-09 19:49:45'),
(10, 1, 1, '2026-05-09', 1, 'fuyjyh', '2026-05-09 20:09:10'),
(11, 1, 1, '2026-05-09', 1, 'hbh', '2026-05-09 20:09:17');

-- --------------------------------------------------------

--
-- Estrutura da tabela `presencas`
--

CREATE TABLE `presencas` (
  `id` int(11) NOT NULL,
  `aluno_id` int(11) NOT NULL,
  `horario_id` int(11) NOT NULL,
  `data` date NOT NULL,
  `estado` enum('presente','atraso','ausente','falta_disciplinar') NOT NULL DEFAULT 'ausente',
  `hora_entrada` time DEFAULT NULL,
  `hora_saida` time DEFAULT NULL,
  `registado_por` enum('rfid','professor','sistema') NOT NULL DEFAULT 'sistema',
  `observacao` text DEFAULT NULL,
  `criado_em` datetime DEFAULT current_timestamp(),
  `atualizado_em` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `presencas`
--

INSERT INTO `presencas` (`id`, `aluno_id`, `horario_id`, `data`, `estado`, `hora_entrada`, `hora_saida`, `registado_por`, `observacao`, `criado_em`, `atualizado_em`) VALUES
(1, 1, 7, '2026-03-10', 'presente', NULL, NULL, 'professor', '', '2026-03-10 13:49:09', '2026-03-10 14:14:00'),
(2, 2, 7, '2026-03-10', 'presente', NULL, NULL, 'professor', '', '2026-03-10 13:49:10', '2026-03-10 14:16:03'),
(3, 3, 7, '2026-03-10', 'presente', NULL, NULL, 'professor', '', '2026-03-10 13:49:10', '2026-03-10 14:16:08'),
(4, 4, 7, '2026-03-10', 'presente', NULL, NULL, 'professor', '', '2026-03-10 13:49:10', '2026-03-10 14:16:13'),
(5, 5, 7, '2026-03-10', 'presente', NULL, NULL, 'professor', '', '2026-03-10 13:49:10', '2026-03-10 14:16:18'),
(6, 6, 7, '2026-03-10', 'presente', NULL, NULL, 'professor', '', '2026-03-10 13:49:10', '2026-03-10 14:16:25'),
(7, 7, 7, '2026-03-10', 'presente', NULL, NULL, 'professor', '', '2026-03-10 13:49:10', '2026-03-10 14:16:31'),
(36, 1, 9, '2026-03-10', 'presente', NULL, NULL, 'professor', '', '2026-03-10 14:47:04', '2026-03-10 15:47:51'),
(37, 2, 9, '2026-03-10', 'presente', NULL, NULL, 'professor', '', '2026-03-10 14:47:04', '2026-03-10 15:48:11'),
(38, 3, 9, '2026-03-10', 'presente', NULL, NULL, 'professor', '', '2026-03-10 14:47:04', '2026-03-10 15:48:33'),
(39, 4, 9, '2026-03-10', 'presente', NULL, NULL, 'professor', '', '2026-03-10 14:47:04', '2026-03-10 15:48:39'),
(40, 5, 9, '2026-03-10', 'presente', NULL, NULL, 'professor', '', '2026-03-10 14:47:04', '2026-03-10 15:48:47'),
(41, 6, 9, '2026-03-10', 'presente', NULL, NULL, 'professor', '', '2026-03-10 14:47:04', '2026-03-10 15:48:54'),
(42, 7, 9, '2026-03-10', 'presente', NULL, NULL, 'professor', '', '2026-03-10 14:47:04', '2026-03-10 15:49:02'),
(65, 1, 11, '2026-03-10', 'presente', NULL, NULL, 'professor', '', '2026-03-10 16:49:05', '2026-03-10 17:23:27'),
(66, 2, 11, '2026-03-10', 'presente', NULL, NULL, 'professor', '', '2026-03-10 16:49:05', '2026-03-10 17:23:34'),
(67, 3, 11, '2026-03-10', 'presente', NULL, NULL, 'professor', '', '2026-03-10 16:49:05', '2026-03-10 17:23:42'),
(68, 4, 11, '2026-03-10', 'presente', NULL, NULL, 'professor', '', '2026-03-10 16:49:05', '2026-03-10 17:23:50'),
(69, 5, 11, '2026-03-10', 'presente', NULL, NULL, 'professor', '', '2026-03-10 16:49:05', '2026-03-10 17:23:56'),
(70, 6, 11, '2026-03-10', 'presente', NULL, NULL, 'professor', '', '2026-03-10 16:49:05', '2026-03-10 17:24:02'),
(71, 7, 11, '2026-03-10', 'presente', NULL, NULL, 'professor', '', '2026-03-10 16:49:05', '2026-03-10 17:24:09'),
(149, 1, 13, '2026-03-11', 'ausente', NULL, NULL, 'rfid', NULL, '2026-03-11 13:07:21', '2026-03-11 13:07:21'),
(150, 2, 13, '2026-03-11', 'ausente', NULL, NULL, 'rfid', NULL, '2026-03-11 13:07:21', '2026-03-11 13:07:21'),
(151, 3, 13, '2026-03-11', 'presente', NULL, NULL, 'professor', '', '2026-03-11 13:07:21', '2026-03-11 13:16:15'),
(152, 4, 13, '2026-03-11', 'ausente', NULL, NULL, 'rfid', NULL, '2026-03-11 13:07:21', '2026-03-11 13:07:21'),
(153, 5, 13, '2026-03-11', 'presente', NULL, NULL, 'professor', '', '2026-03-11 13:07:21', '2026-03-11 13:16:27'),
(154, 6, 13, '2026-03-11', 'ausente', NULL, NULL, 'rfid', NULL, '2026-03-11 13:07:21', '2026-03-11 13:07:21'),
(155, 7, 13, '2026-03-11', 'ausente', NULL, NULL, 'rfid', NULL, '2026-03-11 13:07:21', '2026-03-11 13:07:21'),
(214, 1, 17, '2026-03-11', 'presente', NULL, NULL, 'professor', '', '2026-03-11 16:31:52', '2026-03-11 16:44:45'),
(215, 2, 17, '2026-03-11', 'presente', NULL, NULL, 'professor', '', '2026-03-11 16:31:52', '2026-03-11 16:44:51'),
(216, 3, 17, '2026-03-11', 'presente', NULL, NULL, 'professor', '', '2026-03-11 16:31:52', '2026-03-11 16:44:56'),
(217, 4, 17, '2026-03-11', 'presente', NULL, NULL, 'professor', '', '2026-03-11 16:31:52', '2026-03-11 16:44:59'),
(218, 5, 17, '2026-03-11', 'presente', NULL, NULL, 'professor', '', '2026-03-11 16:31:52', '2026-05-07 10:07:59'),
(219, 6, 17, '2026-03-11', 'presente', NULL, NULL, 'professor', '', '2026-03-11 16:31:52', '2026-03-11 16:45:07'),
(220, 7, 17, '2026-03-11', 'presente', NULL, NULL, 'professor', '', '2026-03-11 16:31:52', '2026-03-11 16:45:11'),
(280, 1, 27, '2026-03-13', 'presente', NULL, NULL, 'professor', '', '2026-03-13 15:02:01', '2026-03-13 15:34:04'),
(281, 2, 27, '2026-03-13', 'presente', NULL, NULL, 'professor', '', '2026-03-13 15:02:12', '2026-03-13 15:34:07'),
(282, 4, 27, '2026-03-13', 'presente', NULL, NULL, 'professor', '', '2026-03-13 15:02:22', '2026-03-13 15:34:11'),
(283, 6, 27, '2026-03-13', 'presente', NULL, NULL, 'professor', '', '2026-03-13 15:02:26', '2026-03-13 15:34:14'),
(284, 7, 27, '2026-03-13', 'presente', NULL, NULL, 'professor', '', '2026-03-13 15:02:29', '2026-03-13 15:34:17'),
(290, 1, 29, '2026-03-13', 'presente', NULL, NULL, 'professor', '', '2026-03-13 16:34:45', '2026-05-07 10:07:59'),
(291, 2, 29, '2026-03-13', 'presente', NULL, NULL, 'professor', '', '2026-03-13 16:34:50', '2026-03-13 16:35:44'),
(292, 4, 29, '2026-03-13', 'presente', NULL, NULL, 'professor', '', '2026-03-13 16:35:02', '2026-03-13 16:35:02'),
(293, 5, 29, '2026-03-13', 'ausente', NULL, NULL, 'professor', '', '2026-03-13 16:35:07', '2026-03-13 16:35:11'),
(295, 6, 29, '2026-03-13', 'presente', NULL, NULL, 'professor', '', '2026-03-13 16:35:15', '2026-03-13 16:35:15'),
(296, 7, 29, '2026-03-13', 'presente', NULL, NULL, 'professor', '', '2026-03-13 16:35:18', '2026-03-13 16:35:18'),
(300, 1, 19, '2026-04-09', 'presente', NULL, NULL, 'professor', '', '2026-04-09 11:32:15', '2026-04-09 11:32:15'),
(301, 1, 19, '2026-04-30', 'ausente', NULL, NULL, 'sistema', NULL, '2026-04-30 13:45:27', '2026-04-30 13:45:27'),
(302, 2, 19, '2026-04-30', 'ausente', '13:46:27', NULL, 'rfid', NULL, '2026-04-30 13:45:27', '2026-04-30 13:46:29'),
(303, 3, 19, '2026-04-30', 'ausente', NULL, NULL, 'sistema', NULL, '2026-04-30 13:45:27', '2026-04-30 13:45:27'),
(304, 4, 19, '2026-04-30', 'ausente', '13:45:34', NULL, 'rfid', NULL, '2026-04-30 13:45:27', '2026-04-30 13:45:35'),
(305, 5, 19, '2026-04-30', 'ausente', '13:45:27', NULL, 'rfid', NULL, '2026-04-30 13:45:27', '2026-04-30 13:45:27'),
(306, 6, 19, '2026-04-30', 'ausente', NULL, NULL, 'sistema', NULL, '2026-04-30 13:45:27', '2026-04-30 13:45:27'),
(307, 7, 19, '2026-04-30', 'ausente', NULL, NULL, 'sistema', NULL, '2026-04-30 13:45:27', '2026-04-30 13:45:27'),
(325, 1, 27, '2026-05-01', 'ausente', NULL, NULL, 'sistema', NULL, '2026-05-01 16:06:25', '2026-05-01 16:06:25'),
(326, 2, 27, '2026-05-01', 'ausente', '16:07:56', NULL, 'rfid', NULL, '2026-05-01 16:06:25', '2026-05-01 16:07:57'),
(327, 3, 27, '2026-05-01', 'ausente', '16:07:06', NULL, 'rfid', NULL, '2026-05-01 16:06:25', '2026-05-01 16:07:14'),
(328, 4, 27, '2026-05-01', 'ausente', '16:08:01', NULL, 'rfid', NULL, '2026-05-01 16:06:25', '2026-05-01 16:08:07'),
(329, 5, 27, '2026-05-01', 'ausente', '16:07:39', NULL, 'rfid', NULL, '2026-05-01 16:06:25', '2026-05-01 16:07:40'),
(330, 6, 27, '2026-05-01', 'ausente', '16:06:14', NULL, 'rfid', NULL, '2026-05-01 16:06:25', '2026-05-01 16:06:25'),
(331, 7, 27, '2026-05-01', 'ausente', '16:06:47', NULL, 'rfid', NULL, '2026-05-01 16:06:25', '2026-05-01 16:06:48'),
(408, 1, 29, '2026-05-01', 'presente', '16:30:04', NULL, 'rfid', NULL, '2026-05-01 16:30:12', '2026-05-01 16:30:12'),
(409, 2, 29, '2026-05-01', 'presente', '16:30:35', NULL, 'rfid', NULL, '2026-05-01 16:30:12', '2026-05-01 16:30:36'),
(410, 3, 29, '2026-05-01', 'presente', '16:30:46', NULL, 'rfid', NULL, '2026-05-01 16:30:12', '2026-05-01 16:30:46'),
(411, 4, 29, '2026-05-01', 'presente', '16:30:39', NULL, 'rfid', NULL, '2026-05-01 16:30:12', '2026-05-01 16:30:40'),
(412, 5, 29, '2026-05-01', 'presente', '16:30:28', NULL, 'rfid', NULL, '2026-05-01 16:30:12', '2026-05-01 16:30:31'),
(413, 6, 29, '2026-05-01', 'presente', '16:32:03', NULL, 'rfid', NULL, '2026-05-01 16:30:12', '2026-05-01 16:32:07'),
(414, 7, 29, '2026-05-01', 'presente', '16:30:22', NULL, 'rfid', NULL, '2026-05-01 16:30:12', '2026-05-01 16:30:22'),
(485, 8, 29, '2026-05-01', 'ausente', NULL, NULL, 'sistema', NULL, '2026-05-01 17:20:06', '2026-05-01 17:20:06'),
(494, 1, 19, '2026-05-07', 'presente', '13:32:31', NULL, 'rfid', NULL, '2026-05-07 13:31:36', '2026-05-07 13:32:30'),
(495, 2, 19, '2026-05-07', 'presente', '13:33:19', NULL, 'rfid', NULL, '2026-05-07 13:31:36', '2026-05-07 13:33:18'),
(496, 3, 19, '2026-05-07', 'presente', '13:31:19', NULL, 'rfid', NULL, '2026-05-07 13:31:36', '2026-05-07 13:31:36'),
(497, 4, 19, '2026-05-07', 'presente', '13:33:14', NULL, 'rfid', NULL, '2026-05-07 13:31:36', '2026-05-07 13:33:13'),
(498, 5, 19, '2026-05-07', 'presente', '13:33:09', NULL, 'rfid', NULL, '2026-05-07 13:31:36', '2026-05-07 13:33:08'),
(499, 6, 19, '2026-05-07', 'presente', '13:32:22', NULL, 'rfid', NULL, '2026-05-07 13:31:36', '2026-05-07 13:32:22'),
(500, 7, 19, '2026-05-07', 'presente', '13:32:11', NULL, 'rfid', NULL, '2026-05-07 13:31:36', '2026-05-07 13:32:46'),
(501, 8, 19, '2026-05-07', 'ausente', NULL, NULL, 'sistema', NULL, '2026-05-07 13:31:36', '2026-05-07 13:31:36');

-- --------------------------------------------------------

--
-- Estrutura da tabela `professores`
--

CREATE TABLE `professores` (
  `id` int(11) NOT NULL,
  `nome` varchar(150) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `ativo` tinyint(4) NOT NULL DEFAULT 1,
  `criado_em` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `professores`
--

INSERT INTO `professores` (`id`, `nome`, `email`, `ativo`, `criado_em`) VALUES
(1, 'Catarina', 'catarina@scope.ao', 1, '2026-03-10 01:23:42'),
(2, 'Euclides', 'euclides@scope.ao', 1, '2026-03-10 01:23:42'),
(3, 'Beatriz', 'beatriz@scope.ao', 1, '2026-03-10 01:23:42'),
(4, 'A. Gourgel', 'gourgel@scope.ao', 1, '2026-03-10 01:23:42'),
(5, 'Margarido', 'margarido@scope.ao', 1, '2026-03-10 01:23:42'),
(6, 'L. Magalhães', 'magalhaes@scope.ao', 1, '2026-03-10 01:23:42'),
(7, 'João Cassoma', 'cassoma@scope.ao', 1, '2026-03-10 01:23:42'),
(8, 'Jelson', 'jelson@scope.ao', 1, '2026-03-10 01:23:42'),
(9, 'Moisés Kandjeke', 'moises@scope.ao', 1, '2026-03-10 01:23:42'),
(10, 'Madaleno', 'madaleno@scope.ao', 1, '2026-03-10 01:23:42');

-- --------------------------------------------------------

--
-- Estrutura da tabela `relatorios_exportados`
--

CREATE TABLE `relatorios_exportados` (
  `id` int(11) NOT NULL,
  `gerado_por` int(11) NOT NULL COMMENT 'utilizadores.id',
  `turma_id` int(11) NOT NULL,
  `tipo` enum('semanal','mensal','trimestral') NOT NULL,
  `data_inicio` date NOT NULL,
  `data_fim` date NOT NULL,
  `ficheiro` varchar(200) DEFAULT NULL,
  `criado_em` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `relatorios_exportados`
--

INSERT INTO `relatorios_exportados` (`id`, `gerado_por`, `turma_id`, `tipo`, `data_inicio`, `data_fim`, `ficheiro`, `criado_em`) VALUES
(1, 1, 1, 'mensal', '2026-03-01', '2026-03-10', NULL, '2026-03-10 15:38:25'),
(2, 1, 1, 'semanal', '2026-03-09', '2026-03-13', NULL, '2026-03-10 15:40:40'),
(3, 1, 1, 'semanal', '2026-03-09', '2026-03-13', NULL, '2026-03-10 15:43:52'),
(4, 1, 1, 'mensal', '2026-03-01', '2026-03-10', NULL, '2026-03-10 15:52:05'),
(5, 1, 1, 'mensal', '2026-03-01', '2026-03-10', NULL, '2026-03-10 15:52:05'),
(6, 1, 1, 'mensal', '2026-03-01', '2026-03-13', NULL, '2026-03-13 14:32:24'),
(7, 1, 1, 'mensal', '2026-03-01', '2026-03-15', NULL, '2026-03-15 20:38:55'),
(8, 1, 1, 'semanal', '2026-04-06', '2026-04-10', NULL, '2026-04-09 12:13:30'),
(9, 1, 1, 'mensal', '2026-05-01', '2026-05-01', NULL, '2026-05-01 17:17:37'),
(10, 1, 1, 'mensal', '2026-05-01', '2026-05-05', NULL, '2026-05-05 17:27:27'),
(11, 1, 1, 'semanal', '2026-05-04', '2026-05-08', NULL, '2026-05-09 19:52:25'),
(12, 1, 1, 'trimestral', '2026-04-01', '2026-05-09', NULL, '2026-05-09 19:52:54'),
(13, 1, 1, 'mensal', '2026-05-01', '2026-05-12', NULL, '2026-05-12 13:43:20');

-- --------------------------------------------------------

--
-- Estrutura da tabela `rfid_logs`
--

CREATE TABLE `rfid_logs` (
  `id` int(11) NOT NULL,
  `rfid_id` varchar(20) NOT NULL,
  `timestamp` datetime NOT NULL,
  `processado` tinyint(4) NOT NULL DEFAULT 0,
  `resposta` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `rfid_logs`
--

INSERT INTO `rfid_logs` (`id`, `rfid_id`, `timestamp`, `processado`, `resposta`) VALUES
(1, '5885635', '2026-03-10 09:49:31', 1, 'rfid_desconhecido'),
(2, '4713470', '2026-03-10 13:49:00', 1, 'Domingos Caselo Kalwiji Samanjata | bloco:1 | estado:ausente | ignorado'),
(3, '4737708', '2026-03-10 13:49:10', 1, 'Larissa Fato Botelho | bloco:1 | estado:ausente | ignorado'),
(4, '4710756', '2026-03-10 13:49:19', 1, 'Alexandre Fernando Monteiro Pereira | bloco:1 | estado:ausente | ignorado'),
(5, '4742167', '2026-03-10 13:49:44', 1, 'Luís Caison Zango Caíca | bloco:1 | estado:ausente | ignorado'),
(6, '712839', '2026-03-10 13:50:12', 1, 'rfid_desconhecido'),
(7, '4682816', '2026-03-10 14:46:56', 1, 'Agnalda Kunjikisse Jelembi Vapor | bloco:2 | estado:presente | ignorado'),
(8, '4682816', '2026-03-10 14:47:00', 1, 'Agnalda Kunjikisse Jelembi Vapor | bloco:2 | estado:presente | ignorado'),
(9, '4737708', '2026-03-10 16:48:54', 1, 'Larissa Fato Botelho | bloco:3 | estado:ausente | ignorado'),
(10, '4737708', '2026-03-10 17:01:11', 1, 'Larissa Fato Botelho | bloco:3 | estado:ausente | ignorado'),
(11, '4737708', '2026-03-10 17:16:19', 1, 'Larissa Fato Botelho | bloco:3 | estado:ausente | ignorado'),
(12, '4737708', '2026-03-10 17:16:25', 1, 'Larissa Fato Botelho | bloco:3 | estado:ausente | ignorado'),
(13, '4737708', '2026-03-10 17:21:52', 1, 'Larissa Fato Botelho | bloco:3 | estado:ausente | ignorado'),
(14, '4737708', '2026-03-10 17:22:22', 1, 'Larissa Fato Botelho | bloco:3 | estado:ausente | ignorado'),
(15, '4737708', '2026-03-10 17:24:34', 1, 'Larissa Fato Botelho | bloco:3 | estado:ausente | ignorado'),
(16, '4737708', '2026-03-10 17:31:21', 1, 'Larissa Fato Botelho | bloco:3 | estado:ausente | ignorado'),
(17, '4737708', '2026-03-10 17:32:00', 1, 'Larissa Fato Botelho | bloco:3 | estado:ausente | ignorado'),
(18, '4737708', '2026-03-10 17:52:29', 1, 'Larissa Fato Botelho | bloco:3 | estado:ausente | ignorado'),
(19, '4737708', '2026-03-10 17:53:09', 1, 'Larissa Fato Botelho | bloco:3 | estado:ausente | ignorado'),
(20, '4737708', '2026-03-11 13:07:20', 1, 'Larissa Fato Botelho | bloco:1 | estado:atraso | ignorado'),
(21, '4713470', '2026-03-11 13:07:24', 1, 'Domingos Caselo Kalwiji Samanjata | bloco:1 | estado:atraso | ignorado'),
(22, '4742167', '2026-03-11 13:23:01', 1, 'Luís Caison Zango Caíca | bloco:1 | estado:ausente | ignorado'),
(23, '4732271', '2026-03-11 13:37:45', 1, 'Haziel Simbovala Chitau Hamuyela Tchitawila | bloco:1 | estado:ausente | ignorado'),
(24, '4710756', '2026-03-11 13:52:52', 1, 'Alexandre Fernando Monteiro Pereira | bloco:1 | estado:ausente | ignorado'),
(25, '4682816', '2026-03-11 13:52:57', 1, 'Agnalda Kunjikisse Jelembi Vapor | bloco:1 | estado:ausente | ignorado'),
(26, '4742167', '2026-03-11 13:53:00', 1, 'Luís Caison Zango Caíca | bloco:1 | estado:ausente | ignorado'),
(27, '4737708', '2026-03-11 13:53:06', 1, 'Larissa Fato Botelho | bloco:1 | estado:ausente | ignorado'),
(28, '4732271', '2026-03-11 13:53:09', 1, 'Haziel Simbovala Chitau Hamuyela Tchitawila | bloco:1 | estado:ausente | ignorado'),
(29, '4742167', '2026-03-11 16:31:45', 1, 'Luís Caison Zango Caíca | bloco:3 | estado:presente | ignorado'),
(30, '4737708', '2026-03-11 16:32:12', 1, 'Larissa Fato Botelho | bloco:3 | estado:presente | ignorado'),
(31, '4682816', '2026-03-11 16:32:39', 1, 'Agnalda Kunjikisse Jelembi Vapor | bloco:3 | estado:presente | ignorado'),
(32, '4713470', '2026-03-11 16:32:57', 1, 'Domingos Caselo Kalwiji Samanjata | bloco:3 | estado:presente | ignorado'),
(33, '4713470', '2026-03-11 16:33:03', 1, 'Domingos Caselo Kalwiji Samanjata | bloco:3 | estado:presente | ignorado'),
(34, '4710756', '2026-03-11 16:33:21', 1, 'Alexandre Fernando Monteiro Pereira | bloco:3 | estado:presente | ignorado'),
(35, '4710756', '2026-03-11 16:33:25', 1, 'Alexandre Fernando Monteiro Pereira | bloco:3 | estado:presente | ignorado'),
(36, '640600', '2026-03-13 13:25:40', 1, 'rfid_desconhecido'),
(37, '640600', '2026-03-13 13:25:43', 1, 'rfid_desconhecido'),
(38, '640600', '1970-01-01 01:02:09', 1, 'rfid_desconhecido'),
(39, '4732271', '2026-04-09 09:26:58', 1, 'fora_de_bloco'),
(40, '4710756', '2026-04-09 09:27:02', 1, 'fora_de_bloco'),
(41, '4737708', '2026-04-09 09:27:08', 1, 'fora_de_bloco'),
(42, '4732271', '2026-04-09 09:47:18', 1, 'fora_de_bloco'),
(43, '4710756', '2026-04-09 09:50:11', 1, 'fora_de_bloco'),
(44, '5882869', '2026-04-09 10:15:05', 1, 'fora_de_bloco'),
(45, '5882869', '2026-04-09 10:22:07', 1, 'fora_de_bloco'),
(46, '16744447', '2026-04-30 13:04:22', 1, 'rfid_desconhecido'),
(47, '5968616', '2026-04-30 13:43:23', 1, 'rfid_desconhecido'),
(48, '5968616', '2026-04-30 13:43:51', 1, 'rfid_desconhecido'),
(49, '4737708', '2026-04-30 13:45:27', 1, 'Larissa Fato Botelho | bloco:1 | estado:ausente | inserido'),
(50, '4732271', '2026-04-30 13:45:34', 1, 'Haziel Simbovala Chitau Hamuyela Tchitawila | bloco:1 | estado:ausente | inserido'),
(51, '4710756', '2026-04-30 13:46:27', 1, 'Alexandre Fernando Monteiro Pereira | bloco:1 | estado:ausente | inserido'),
(52, '4742167', '2026-05-01 16:06:14', 1, 'Luís Caison Zango Caíca | bloco:2 | estado:ausente | inserido'),
(53, '4742167', '2026-05-01 16:06:14', 1, 'Luís Caison Zango Caíca | bloco:2 | estado:ausente | ignorado'),
(54, '4742167', '2026-05-01 16:06:37', 1, 'Luís Caison Zango Caíca | bloco:2 | estado:ausente | ignorado'),
(55, '5882869', '2026-05-01 16:06:47', 1, 'Maria Da Conceição José Pena | bloco:2 | estado:ausente | inserido'),
(56, '5931327', '2026-05-01 16:06:54', 1, 'rfid_desconhecido'),
(57, '4713470', '2026-05-01 16:07:06', 1, 'Domingos Caselo Kalwiji Samanjata | bloco:2 | estado:ausente | inserido'),
(58, '4737708', '2026-05-01 16:07:39', 1, 'Larissa Fato Botelho | bloco:2 | estado:ausente | inserido'),
(59, '4737708', '2026-05-01 16:07:46', 1, 'Larissa Fato Botelho | bloco:2 | estado:ausente | ignorado'),
(60, '4710756', '2026-05-01 16:07:56', 1, 'Alexandre Fernando Monteiro Pereira | bloco:2 | estado:ausente | inserido'),
(61, '4732271', '2026-05-01 16:08:01', 1, 'Haziel Simbovala Chitau Hamuyela Tchitawila | bloco:2 | estado:ausente | inserido'),
(62, '4737708', '2026-05-01 16:12:35', 1, 'Larissa Fato Botelho | bloco:2 | estado:ausente | ignorado'),
(63, '4713470', '2026-05-01 16:12:44', 1, 'Domingos Caselo Kalwiji Samanjata | bloco:2 | estado:ausente | ignorado'),
(64, '4682816', '2026-05-01 16:30:04', 1, 'Agnalda Kunjikisse Jelembi Vapor | bloco:3 | estado:presente | inserido'),
(65, '5882869', '2026-05-01 16:30:22', 1, 'Maria Da Conceição José Pena | bloco:3 | estado:presente | inserido'),
(66, '4737708', '2026-05-01 16:30:28', 1, 'Larissa Fato Botelho | bloco:3 | estado:presente | inserido'),
(67, '4710756', '2026-05-01 16:30:35', 1, 'Alexandre Fernando Monteiro Pereira | bloco:3 | estado:presente | inserido'),
(68, '4732271', '2026-05-01 16:30:39', 1, 'Haziel Simbovala Chitau Hamuyela Tchitawila | bloco:3 | estado:presente | inserido'),
(69, '4713470', '2026-05-01 16:30:46', 1, 'Domingos Caselo Kalwiji Samanjata | bloco:3 | estado:presente | inserido'),
(70, '5882869', '2026-05-01 16:30:49', 1, 'Maria Da Conceição José Pena | bloco:3 | estado:presente | ignorado'),
(71, '4737708', '2026-05-01 16:30:57', 1, 'Larissa Fato Botelho | bloco:3 | estado:presente | ignorado'),
(72, '4742167', '2026-05-01 16:32:03', 1, 'Luís Caison Zango Caíca | bloco:3 | estado:presente | inserido'),
(73, '5885635', '2026-05-01 17:14:33', 1, 'rfid_desconhecido'),
(74, '5885635', '2026-05-01 17:14:59', 1, 'rfid_desconhecido'),
(75, '5885635', '2026-05-01 17:15:22', 1, 'rfid_desconhecido'),
(76, '4682816', '2026-05-01 17:16:06', 1, 'Agnalda Kunjikisse Jelembi Vapor | bloco:3 | estado:ausente | ignorado'),
(77, '5885635', '2026-05-01 17:14:59', 1, 'rfid_desconhecido'),
(78, '5885635', '2026-05-01 17:15:22', 1, 'rfid_desconhecido'),
(79, '5885635', '2026-05-01 17:15:30', 1, 'rfid_desconhecido'),
(80, '4682816', '2026-05-01 17:16:06', 1, 'Agnalda Kunjikisse Jelembi Vapor | bloco:3 | estado:ausente | ignorado'),
(81, '4713470', '2026-05-07 13:31:19', 1, 'Domingos Caselo Kalwiji Samanjata | bloco:1 | estado:presente | inserido'),
(82, '4713470', '2026-05-07 13:32:00', 1, 'Domingos Caselo Kalwiji Samanjata | bloco:1 | estado:presente | ignorado'),
(83, '4742167', '2026-05-07 13:32:22', 1, 'Luís Caison Zango Caíca | bloco:1 | estado:presente | inserido'),
(84, '4682816', '2026-05-07 13:32:31', 1, 'Arnásio Malundo Da Conceição Mavunda | bloco:1 | estado:presente | inserido'),
(85, '5882869', '2026-05-07 13:32:11', 1, 'Maria Da Conceição José Pena | bloco:1 | estado:presente | inserido'),
(86, '5885635', '2026-05-07 13:32:50', 1, 'rfid_desconhecido'),
(87, '5968616', '2026-05-07 13:33:01', 1, 'rfid_desconhecido'),
(88, '4737708', '2026-05-07 13:33:09', 1, 'Larissa Fato Botelho | bloco:1 | estado:presente | inserido'),
(89, '4732271', '2026-05-07 13:33:14', 1, 'Haziel Simbovala Chitau Hamuyela Tchitawila | bloco:1 | estado:presente | inserido'),
(90, '4710756', '2026-05-07 13:33:19', 1, 'Alexandre Fernando Monteiro Pereira | bloco:1 | estado:presente | inserido'),
(91, '4713470', '2026-05-07 13:33:33', 1, 'Domingos Caselo Kalwiji Samanjata | bloco:1 | estado:presente | ignorado'),
(92, '4742167', '2026-05-07 13:34:06', 1, 'Luís Caison Zango Caíca | bloco:1 | estado:presente | ignorado'),
(93, '5882869', '2026-05-07 13:34:12', 1, 'Maria Da Conceição José Pena | bloco:1 | estado:presente | ignorado'),
(94, '4713470', '2026-05-07 13:34:18', 1, 'Domingos Caselo Kalwiji Samanjata | bloco:1 | estado:presente | ignorado'),
(95, '4732271', '2026-05-07 13:34:21', 1, 'Haziel Simbovala Chitau Hamuyela Tchitawila | bloco:1 | estado:presente | ignorado'),
(96, '4737708', '2026-05-07 13:34:26', 1, 'Larissa Fato Botelho | bloco:1 | estado:presente | ignorado'),
(97, '5885635', '2026-05-07 13:34:30', 1, 'rfid_desconhecido'),
(98, '5931327', '2026-05-07 13:34:35', 1, 'rfid_desconhecido'),
(99, '5968616', '2026-05-07 13:34:39', 1, 'rfid_desconhecido'),
(100, '4710756', '2026-05-07 13:36:36', 1, 'Alexandre Fernando Monteiro Pereira | bloco:1 | estado:presente | ignorado'),
(101, '5968616', '2026-05-07 13:36:55', 1, 'rfid_desconhecido'),
(102, '5968616', '2026-05-07 13:36:59', 1, 'rfid_desconhecido'),
(103, '4710756', '2026-05-07 13:37:03', 1, 'Alexandre Fernando Monteiro Pereira | bloco:1 | estado:presente | ignorado'),
(104, '5931327', '2026-05-07 13:37:12', 1, 'rfid_desconhecido'),
(105, '4737708', '2026-05-07 13:37:19', 1, 'Larissa Fato Botelho | bloco:1 | estado:presente | ignorado'),
(106, '4732271', '2026-05-07 13:37:24', 1, 'Haziel Simbovala Chitau Hamuyela Tchitawila | bloco:1 | estado:presente | ignorado'),
(107, '4713470', '2026-05-07 13:37:29', 1, 'Domingos Caselo Kalwiji Samanjata | bloco:1 | estado:presente | ignorado');

-- --------------------------------------------------------

--
-- Estrutura da tabela `turmas`
--

CREATE TABLE `turmas` (
  `id` int(11) NOT NULL,
  `nome` varchar(50) NOT NULL,
  `ciclo` varchar(20) DEFAULT NULL,
  `turno` enum('manha','tarde') NOT NULL DEFAULT 'tarde',
  `ano_letivo` varchar(9) NOT NULL,
  `sala` varchar(20) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `classe` varchar(50) DEFAULT NULL,
  `curso` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `turmas`
--

INSERT INTO `turmas` (`id`, `nome`, `ciclo`, `turno`, `ano_letivo`, `sala`, `ativo`, `classe`, `curso`) VALUES
(1, 'Turma Demonstração', '2º Ciclo', 'tarde', '2025/2026', 'Sala 6', 1, '13ª Classe', 'Informática');

-- --------------------------------------------------------

--
-- Estrutura da tabela `utilizadores`
--

CREATE TABLE `utilizadores` (
  `id` int(11) NOT NULL,
  `nome` varchar(150) NOT NULL,
  `email` varchar(100) NOT NULL,
  `senha` varchar(255) NOT NULL COMMENT 'bcrypt hash',
  `perfil` enum('professor','coordenador','administrador','encarregado') NOT NULL,
  `referencia_id` int(11) DEFAULT NULL,
  `ativo` tinyint(4) NOT NULL DEFAULT 1,
  `ultimo_login` datetime DEFAULT NULL,
  `criado_em` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `utilizadores`
--

INSERT INTO `utilizadores` (`id`, `nome`, `email`, `senha`, `perfil`, `referencia_id`, `ativo`, `ultimo_login`, `criado_em`) VALUES
(1, 'Administrador SCOPE', 'admin@scope.ao', '$2y$10$yjyO9q8rUt8gR7CkHl977OIUuEwYLgS3ZGbZP2tLJDJwgaPEeCDYG', 'administrador', NULL, 1, '2026-05-09 19:53:57', '2026-03-10 01:23:42'),
(2, 'Coordenador 13ª Informática', 'coordenador@scope.ao', '$2y$10$yjyO9q8rUt8gR7CkHl977OIUuEwYLgS3ZGbZP2tLJDJwgaPEeCDYG', 'coordenador', NULL, 1, '2026-05-12 13:27:10', '2026-03-10 01:23:42'),
(3, 'Alberto', 'alberto@scope.ao', '$2y$10$yjyO9q8rUt8gR7CkHl977OIUuEwYLgS3ZGbZP2tLJDJwgaPEeCDYG', 'professor', 1, 1, '2026-05-09 20:16:15', '2026-03-10 01:23:42'),
(4, 'Euclides', 'euclides@scope.ao', '$2y$10$yjyO9q8rUt8gR7CkHl977OIUuEwYLgS3ZGbZP2tLJDJwgaPEeCDYG', 'professor', 2, 1, NULL, '2026-03-10 01:23:42'),
(5, 'Beatriz', 'beatriz@scope.ao', '$2y$10$yjyO9q8rUt8gR7CkHl977OIUuEwYLgS3ZGbZP2tLJDJwgaPEeCDYG', 'professor', 3, 1, '2026-03-11 15:09:18', '2026-03-10 01:23:42'),
(6, 'A. Gourgel', 'gourgel@scope.ao', '$2y$10$yjyO9q8rUt8gR7CkHl977OIUuEwYLgS3ZGbZP2tLJDJwgaPEeCDYG', 'professor', 4, 1, '2026-03-10 15:46:26', '2026-03-10 01:23:42'),
(7, 'Margarido', 'margarido@scope.ao', '$2y$10$yjyO9q8rUt8gR7CkHl977OIUuEwYLgS3ZGbZP2tLJDJwgaPEeCDYG', 'professor', 5, 1, '2026-03-10 14:51:08', '2026-03-10 01:23:42'),
(8, 'L. Magalhães', 'magalhaes@scope.ao', '$2y$10$yjyO9q8rUt8gR7CkHl977OIUuEwYLgS3ZGbZP2tLJDJwgaPEeCDYG', 'professor', 6, 1, '2026-03-11 16:39:35', '2026-03-10 01:23:42'),
(9, 'João Cassoma', 'cassoma@scope.ao', '$2y$10$yjyO9q8rUt8gR7CkHl977OIUuEwYLgS3ZGbZP2tLJDJwgaPEeCDYG', 'professor', 7, 1, NULL, '2026-03-10 01:23:42'),
(10, 'Jelson', 'jelson@scope.ao', '$2y$10$yjyO9q8rUt8gR7CkHl977OIUuEwYLgS3ZGbZP2tLJDJwgaPEeCDYG', 'professor', 8, 1, '2026-05-01 17:14:22', '2026-03-10 01:23:42'),
(11, 'Moisés Kandjeke', 'moises@scope.ao', '$2y$10$yjyO9q8rUt8gR7CkHl977OIUuEwYLgS3ZGbZP2tLJDJwgaPEeCDYG', 'professor', 9, 1, '2026-05-12 13:15:42', '2026-03-10 01:23:42'),
(12, 'Madaleno', 'madaleno@scope.ao', '$2y$10$yjyO9q8rUt8gR7CkHl977OIUuEwYLgS3ZGbZP2tLJDJwgaPEeCDYG', 'professor', 10, 1, '2026-03-13 15:30:08', '2026-03-10 01:23:42');

-- --------------------------------------------------------

--
-- Estrutura stand-in para vista `v_aula_atual`
-- (Veja abaixo para a view atual)
--
CREATE TABLE `v_aula_atual` (
`horario_id` int(11)
,`dia_semana` tinyint(4)
,`tempo` tinyint(4)
,`bloco` tinyint(4)
,`hora_inicio` time
,`hora_fim` time
,`turma` varchar(50)
,`sala` varchar(20)
,`disciplina` varchar(100)
,`professor` varchar(150)
,`professor_id` int(11)
);

-- --------------------------------------------------------

--
-- Estrutura stand-in para vista `v_resumo_presencas`
-- (Veja abaixo para a view atual)
--
CREATE TABLE `v_resumo_presencas` (
`aluno_id` int(11)
,`aluno` varchar(150)
,`num_processo` varchar(20)
,`data` date
,`dia_semana` tinyint(4)
,`disciplina` varchar(100)
,`professor` varchar(150)
,`tempo` tinyint(4)
,`bloco` tinyint(4)
,`hora_inicio` time
,`estado` enum('presente','atraso','ausente','falta_disciplinar')
,`hora_entrada` time
,`hora_saida` time
,`registado_por` enum('rfid','professor','sistema')
);

-- --------------------------------------------------------

--
-- Estrutura stand-in para vista `v_taxa_presenca`
-- (Veja abaixo para a view atual)
--
CREATE TABLE `v_taxa_presenca` (
`aluno_id` int(11)
,`aluno` varchar(150)
,`total_aulas` bigint(21)
,`presencas` decimal(23,0)
,`ausencias` decimal(23,0)
,`faltas_disciplinares` decimal(23,0)
,`taxa_pct` decimal(28,1)
);

-- --------------------------------------------------------

--
-- Estrutura para vista `v_aula_atual`
--
DROP TABLE IF EXISTS `v_aula_atual`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_aula_atual`  AS SELECT `h`.`id` AS `horario_id`, `h`.`dia_semana` AS `dia_semana`, `h`.`tempo` AS `tempo`, `h`.`bloco` AS `bloco`, `h`.`hora_inicio` AS `hora_inicio`, `h`.`hora_fim` AS `hora_fim`, `t`.`nome` AS `turma`, `t`.`sala` AS `sala`, `d`.`nome` AS `disciplina`, `p`.`nome` AS `professor`, `p`.`id` AS `professor_id` FROM (((`horario` `h` join `turmas` `t` on(`t`.`id` = `h`.`turma_id`)) join `disciplinas` `d` on(`d`.`id` = `h`.`disciplina_id`)) join `professores` `p` on(`p`.`id` = `h`.`professor_id`)) WHERE `h`.`dia_semana` = dayofweek(current_timestamp()) - 1 AND curtime() between `h`.`hora_inicio` and `h`.`hora_fim` ;

-- --------------------------------------------------------

--
-- Estrutura para vista `v_resumo_presencas`
--
DROP TABLE IF EXISTS `v_resumo_presencas`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_resumo_presencas`  AS SELECT `a`.`id` AS `aluno_id`, `a`.`nome` AS `aluno`, `a`.`num_processo` AS `num_processo`, `p`.`data` AS `data`, `h`.`dia_semana` AS `dia_semana`, `d`.`nome` AS `disciplina`, `pr`.`nome` AS `professor`, `h`.`tempo` AS `tempo`, `h`.`bloco` AS `bloco`, `h`.`hora_inicio` AS `hora_inicio`, `p`.`estado` AS `estado`, `p`.`hora_entrada` AS `hora_entrada`, `p`.`hora_saida` AS `hora_saida`, `p`.`registado_por` AS `registado_por` FROM ((((`presencas` `p` join `alunos` `a` on(`a`.`id` = `p`.`aluno_id`)) join `horario` `h` on(`h`.`id` = `p`.`horario_id`)) join `disciplinas` `d` on(`d`.`id` = `h`.`disciplina_id`)) join `professores` `pr` on(`pr`.`id` = `h`.`professor_id`)) ORDER BY `p`.`data` DESC, `h`.`tempo` ASC ;

-- --------------------------------------------------------

--
-- Estrutura para vista `v_taxa_presenca`
--
DROP TABLE IF EXISTS `v_taxa_presenca`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_taxa_presenca`  AS SELECT `a`.`id` AS `aluno_id`, `a`.`nome` AS `aluno`, count(0) AS `total_aulas`, sum(`p`.`estado` in ('presente','atraso')) AS `presencas`, sum(`p`.`estado` = 'ausente') AS `ausencias`, sum(`p`.`estado` = 'falta_disciplinar') AS `faltas_disciplinares`, round(sum(`p`.`estado` in ('presente','atraso')) / count(0) * 100,1) AS `taxa_pct` FROM (`presencas` `p` join `alunos` `a` on(`a`.`id` = `p`.`aluno_id`)) GROUP BY `a`.`id`, `a`.`nome` ;

--
-- Índices para tabelas despejadas
--

--
-- Índices para tabela `alunos`
--
ALTER TABLE `alunos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `rfid_id` (`rfid_id`),
  ADD KEY `turma_id` (`turma_id`);

--
-- Índices para tabela `configuracoes`
--
ALTER TABLE `configuracoes`
  ADD PRIMARY KEY (`chave`);

--
-- Índices para tabela `disciplinas`
--
ALTER TABLE `disciplinas`
  ADD PRIMARY KEY (`id`);

--
-- Índices para tabela `horario`
--
ALTER TABLE `horario`
  ADD PRIMARY KEY (`id`),
  ADD KEY `turma_id` (`turma_id`),
  ADD KEY `disciplina_id` (`disciplina_id`),
  ADD KEY `professor_id` (`professor_id`);

--
-- Índices para tabela `ocorrencias`
--
ALTER TABLE `ocorrencias`
  ADD PRIMARY KEY (`id`),
  ADD KEY `turma_id` (`turma_id`),
  ADD KEY `professor_id` (`professor_id`);

--
-- Índices para tabela `presencas`
--
ALTER TABLE `presencas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_aluno_horario_data` (`aluno_id`,`horario_id`,`data`),
  ADD UNIQUE KEY `uk_presenca` (`aluno_id`,`horario_id`,`data`),
  ADD KEY `horario_id` (`horario_id`);

--
-- Índices para tabela `professores`
--
ALTER TABLE `professores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Índices para tabela `relatorios_exportados`
--
ALTER TABLE `relatorios_exportados`
  ADD PRIMARY KEY (`id`),
  ADD KEY `gerado_por` (`gerado_por`),
  ADD KEY `turma_id` (`turma_id`);

--
-- Índices para tabela `rfid_logs`
--
ALTER TABLE `rfid_logs`
  ADD PRIMARY KEY (`id`);

--
-- Índices para tabela `turmas`
--
ALTER TABLE `turmas`
  ADD PRIMARY KEY (`id`);

--
-- Índices para tabela `utilizadores`
--
ALTER TABLE `utilizadores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT de tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `alunos`
--
ALTER TABLE `alunos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de tabela `disciplinas`
--
ALTER TABLE `disciplinas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de tabela `horario`
--
ALTER TABLE `horario`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT de tabela `ocorrencias`
--
ALTER TABLE `ocorrencias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de tabela `presencas`
--
ALTER TABLE `presencas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=653;

--
-- AUTO_INCREMENT de tabela `professores`
--
ALTER TABLE `professores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de tabela `relatorios_exportados`
--
ALTER TABLE `relatorios_exportados`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de tabela `rfid_logs`
--
ALTER TABLE `rfid_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=108;

--
-- AUTO_INCREMENT de tabela `turmas`
--
ALTER TABLE `turmas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `utilizadores`
--
ALTER TABLE `utilizadores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- Restrições para despejos de tabelas
--

--
-- Limitadores para a tabela `alunos`
--
ALTER TABLE `alunos`
  ADD CONSTRAINT `alunos_ibfk_1` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`);

--
-- Limitadores para a tabela `horario`
--
ALTER TABLE `horario`
  ADD CONSTRAINT `horario_ibfk_1` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`),
  ADD CONSTRAINT `horario_ibfk_2` FOREIGN KEY (`disciplina_id`) REFERENCES `disciplinas` (`id`),
  ADD CONSTRAINT `horario_ibfk_3` FOREIGN KEY (`professor_id`) REFERENCES `professores` (`id`);

--
-- Limitadores para a tabela `ocorrencias`
--
ALTER TABLE `ocorrencias`
  ADD CONSTRAINT `ocorrencias_ibfk_1` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`),
  ADD CONSTRAINT `ocorrencias_ibfk_2` FOREIGN KEY (`professor_id`) REFERENCES `professores` (`id`);

--
-- Limitadores para a tabela `presencas`
--
ALTER TABLE `presencas`
  ADD CONSTRAINT `presencas_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`),
  ADD CONSTRAINT `presencas_ibfk_2` FOREIGN KEY (`horario_id`) REFERENCES `horario` (`id`);

--
-- Limitadores para a tabela `relatorios_exportados`
--
ALTER TABLE `relatorios_exportados`
  ADD CONSTRAINT `relatorios_exportados_ibfk_1` FOREIGN KEY (`gerado_por`) REFERENCES `utilizadores` (`id`),
  ADD CONSTRAINT `relatorios_exportados_ibfk_2` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
