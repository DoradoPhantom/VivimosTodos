-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 27-05-2026 a las 01:34:37
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `vivimos_todos`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_reserva`
--

CREATE TABLE `detalle_reserva` (
  `id` int(10) UNSIGNED NOT NULL,
  `id_reserva` int(10) UNSIGNED NOT NULL,
  `id_insumo` int(10) UNSIGNED NOT NULL,
  `cantidad` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `insumos`
--

CREATE TABLE `insumos` (
  `id` int(10) UNSIGNED NOT NULL,
  `codigo` varchar(50) DEFAULT NULL COMMENT 'Código interno del ítem',
  `nombre` varchar(200) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `categoria` varchar(100) DEFAULT NULL COMMENT 'Ej: catering, sonido, decoración',
  `unidad_medida` varchar(30) NOT NULL DEFAULT 'unidad' COMMENT 'Interno; nuevos registros usan servicio',
  `cantidad_stock` decimal(12,3) NOT NULL DEFAULT 0.000 COMMENT 'No usado en UI de salón',
  `stock_minimo` decimal(12,3) NOT NULL DEFAULT 0.000 COMMENT 'No usado en UI de salón',
  `precio_unitario` decimal(14,4) DEFAULT NULL COMMENT 'Precio de referencia',
  `ubicacion` varchar(120) DEFAULT NULL COMMENT 'No usado en UI de salón',
  `estado_operativo` enum('disponible','danado','reparacion') NOT NULL DEFAULT 'disponible' COMMENT 'Disponible / Dañado / En reparación',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `insumos`
--

INSERT INTO `insumos` (`id`, `codigo`, `nombre`, `descripcion`, `categoria`, `unidad_medida`, `cantidad_stock`, `stock_minimo`, `precio_unitario`, `ubicacion`, `estado_operativo`, `activo`, `creado_en`, `actualizado_en`) VALUES
(1, '001', 'Laptop', 'Asus vivobook 15', 'Equipo Tecnologico', 'servicio', 14.000, 0.000, NULL, NULL, 'danado', 1, '2026-04-29 01:33:49', '2026-05-26 16:37:20'),
(2, '002', 'Mesa', NULL, 'Material', 'servicio', 10.000, 0.000, NULL, NULL, 'disponible', 1, '2026-05-06 17:00:57', '2026-05-26 14:57:50'),
(3, '003', 'Memoria USB', 'Memoria USB 8gb', 'Equipo Tecnologico', 'servicio', 9.000, 0.000, NULL, NULL, 'disponible', 1, '2026-05-06 17:36:26', '2026-05-26 14:04:24');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reservas`
--

CREATE TABLE `reservas` (
  `id` int(10) UNSIGNED NOT NULL,
  `usuario_id` int(10) UNSIGNED NOT NULL COMMENT 'Quien solicita la reserva',
  `fecha_evento` datetime NOT NULL COMMENT 'Inicio del evento (zona horaria del servidor)',
  `fecha_fin` datetime DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `estado` enum('pendiente','aprobada','rechazada','cancelada') NOT NULL DEFAULT 'pendiente',
  `comentario_revision` varchar(500) DEFAULT NULL COMMENT 'Motivo del rechazo u observación del revisor',
  `revisado_por_id` int(10) UNSIGNED DEFAULT NULL,
  `revisado_en` datetime DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `reservas`
--

INSERT INTO `reservas` (`id`, `usuario_id`, `fecha_evento`, `fecha_fin`, `descripcion`, `estado`, `comentario_revision`, `revisado_por_id`, `revisado_en`, `creado_en`, `actualizado_en`) VALUES
(17, 4, '2026-05-29 13:00:00', '2026-05-29 23:59:59', 'Asistentes solicitados: 1 (capacidad máxima: 120).\nInsumos solicitados: ninguno.', 'cancelada', NULL, NULL, NULL, '2026-05-26 13:32:22', '2026-05-26 13:56:31'),
(18, 4, '2026-05-30 14:00:00', '2026-05-30 23:59:59', 'Asistentes solicitados: 10 (capacidad máxima: 120).\nInsumos solicitados:\n- 1 x Laptop (stock actual: 15)\n- 1 x Memoria USB (stock actual: 10)\n- 2 x Mesa (stock actual: 15)\n\nNotas del solicitante:\nHola', 'aprobada', NULL, 1, '2026-05-26 14:04:24', '2026-05-26 13:33:55', '2026-05-26 14:04:24'),
(19, 4, '2026-05-29 13:00:00', '2026-05-29 23:59:59', 'Asistentes solicitados: 120 (capacidad máxima: 120).\nInsumos solicitados:\n- 2 x Memoria USB (stock actual: 10)\n\nNotas del solicitante:\nReal', 'rechazada', 'No', 1, '2026-05-26 15:43:10', '2026-05-26 14:00:21', '2026-05-26 15:43:10'),
(20, 3, '2026-05-28 19:30:00', '2026-05-28 23:59:59', 'Asistentes solicitados: 5 (capacidad máxima: 120).\nInsumos solicitados:\n- 3 x Mesa (stock actual: 13)\n\nNotas del solicitante:\nR', 'aprobada', NULL, 1, '2026-05-26 14:57:50', '2026-05-26 14:52:24', '2026-05-26 14:57:50');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reservas_insumos`
--

CREATE TABLE `reservas_insumos` (
  `id` int(10) UNSIGNED NOT NULL,
  `reserva_id` int(10) UNSIGNED NOT NULL,
  `insumo_id` int(10) UNSIGNED NOT NULL,
  `cantidad_solicitada` decimal(12,3) NOT NULL DEFAULT 1.000,
  `cantidad_entregada` decimal(12,3) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `reservas_insumos`
--

INSERT INTO `reservas_insumos` (`id`, `reserva_id`, `insumo_id`, `cantidad_solicitada`, `cantidad_entregada`, `creado_en`) VALUES
(1, 18, 1, 1.000, 1.000, '2026-05-26 13:33:55'),
(2, 18, 3, 1.000, 1.000, '2026-05-26 13:33:55'),
(3, 18, 2, 2.000, 2.000, '2026-05-26 13:33:55'),
(4, 19, 3, 2.000, NULL, '2026-05-26 14:00:21'),
(5, 20, 2, 3.000, 3.000, '2026-05-26 14:52:24');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(10) UNSIGNED NOT NULL,
  `nombre_completo` varchar(120) NOT NULL,
  `usuario` varchar(80) NOT NULL COMMENT 'Identificador de acceso (ej. apto101, arrendatario actual)',
  `password_hash` varchar(255) NOT NULL,
  `rol` enum('administrador','residente','supervisor') NOT NULL DEFAULT 'residente',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `nombre_completo`, `usuario`, `password_hash`, `rol`, `activo`, `creado_en`, `actualizado_en`) VALUES
(1, 'Administrador del sistema', 'admin', '$2y$10$EcIuf.1A/mO19B0e9vfp6.SD8pDGVanO7OOOZ6w6HfaMRMpc23Wwu', 'administrador', 1, '2026-04-29 01:32:16', NULL),
(2, 'Supervisor general', 'supervisor', '$2y$10$PFfuX6uJoYwFwvw5bIXNOuie0WxowMugiO9jGhWyfZe7AozF.Pwo2', 'supervisor', 1, '2026-04-29 01:32:16', NULL),
(3, 'Cristian Molina', '102', '$2y$10$vBldHZMQDI4wsa7ytJvs7.zSkSI647RQ/f1/H42XriScW2Bs6S6kO', 'residente', 1, '2026-04-29 01:34:05', NULL),
(4, 'Brayan Yair Molina', '101', '$2y$10$.ZfyoCOM2E9WC7iPShl96uSsW767/zIiXp.4PvdI9ScR8eyn1wEPG', 'residente', 1, '2026-04-29 01:34:17', NULL),
(5, 'Juan Jose', '004', '$2y$10$HUDfQuVgr2dJcyF548MjjOVkwtzg7TeQ.F4qJrORr57Jcm0FUGREO', 'residente', 1, '2026-05-06 13:29:38', NULL);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `detalle_reserva`
--
ALTER TABLE `detalle_reserva`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_detalle_reserva_insumo` (`id_reserva`,`id_insumo`),
  ADD KEY `idx_detalle_insumo` (`id_insumo`);

--
-- Indices de la tabla `insumos`
--
ALTER TABLE `insumos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_insumos_codigo` (`codigo`),
  ADD KEY `idx_insumos_categoria` (`categoria`),
  ADD KEY `idx_insumos_nombre` (`nombre`),
  ADD KEY `idx_insumos_activo` (`activo`);

--
-- Indices de la tabla `reservas`
--
ALTER TABLE `reservas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_reservas_usuario` (`usuario_id`),
  ADD KEY `idx_reservas_estado` (`estado`),
  ADD KEY `idx_reservas_fecha` (`fecha_evento`),
  ADD KEY `fk_reservas_revisor` (`revisado_por_id`),
  ADD KEY `idx_reservas_fecha_fin` (`fecha_fin`);

--
-- Indices de la tabla `reservas_insumos`
--
ALTER TABLE `reservas_insumos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_reserva_insumo` (`reserva_id`,`insumo_id`),
  ADD KEY `idx_ri_insumo` (`insumo_id`),
  ADD KEY `idx_ri_entregada` (`cantidad_entregada`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_usuarios_usuario` (`usuario`),
  ADD KEY `idx_usuarios_rol` (`rol`),
  ADD KEY `idx_usuarios_activo` (`activo`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `detalle_reserva`
--
ALTER TABLE `detalle_reserva`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `insumos`
--
ALTER TABLE `insumos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `reservas`
--
ALTER TABLE `reservas`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT de la tabla `reservas_insumos`
--
ALTER TABLE `reservas_insumos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `detalle_reserva`
--
ALTER TABLE `detalle_reserva`
  ADD CONSTRAINT `fk_detalle_insumo` FOREIGN KEY (`id_insumo`) REFERENCES `insumos` (`id`),
  ADD CONSTRAINT `fk_detalle_reserva` FOREIGN KEY (`id_reserva`) REFERENCES `reservas` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `reservas`
--
ALTER TABLE `reservas`
  ADD CONSTRAINT `fk_reservas_revisor` FOREIGN KEY (`revisado_por_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_reservas_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `reservas_insumos`
--
ALTER TABLE `reservas_insumos`
  ADD CONSTRAINT `fk_ri_insumo` FOREIGN KEY (`insumo_id`) REFERENCES `insumos` (`id`),
  ADD CONSTRAINT `fk_ri_reserva` FOREIGN KEY (`reserva_id`) REFERENCES `reservas` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
