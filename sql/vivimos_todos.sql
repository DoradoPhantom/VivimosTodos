-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 06-04-2026 a las 17:41:53
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

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reservas`
--

CREATE TABLE `reservas` (
  `id` int(10) UNSIGNED NOT NULL,
  `usuario_id` int(10) UNSIGNED NOT NULL COMMENT 'Quien solicita la reserva',
  `fecha_evento` datetime NOT NULL COMMENT 'Inicio del evento (zona horaria del servidor)',
  `descripcion` varchar(500) DEFAULT NULL COMMENT 'Tipo de evento, notas',
  `estado` enum('pendiente','aprobada','rechazada','cancelada') NOT NULL DEFAULT 'pendiente',
  `comentario_revision` varchar(500) DEFAULT NULL COMMENT 'Motivo del rechazo u observación del revisor',
  `revisado_por_id` int(10) UNSIGNED DEFAULT NULL,
  `revisado_en` datetime DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
(1, 'Administrador del sistema', 'admin', '$2y$10$EcIuf.1A/mO19B0e9vfp6.SD8pDGVanO7OOOZ6w6HfaMRMpc23Wwu', 'administrador', 1, '2026-04-05 14:46:24', NULL),
(2, 'Supervisor general', 'supervisor', '$2y$10$PFfuX6uJoYwFwvw5bIXNOuie0WxowMugiO9jGhWyfZe7AozF.Pwo2', 'supervisor', 1, '2026-04-05 14:46:24', NULL),
(3, 'Brayan', '101', '$2y$10$gNCYU/P7VBMQiTgzIVCa3OQjFcByyLsinEM3Rok.L/MPB44SKnA8S', 'residente', 1, '2026-04-06 10:18:10', NULL);

--
-- Índices para tablas volcadas
--

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
  ADD KEY `fk_reservas_revisor` (`revisado_por_id`);

--
-- Indices de la tabla `detalle_reserva`
--
ALTER TABLE `detalle_reserva`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_detalle_reserva_insumo` (`id_reserva`,`id_insumo`),
  ADD KEY `idx_detalle_insumo` (`id_insumo`);

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
-- AUTO_INCREMENT de la tabla `insumos`
--
ALTER TABLE `insumos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `reservas`
--
ALTER TABLE `reservas`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `detalle_reserva`
--
ALTER TABLE `detalle_reserva`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `reservas`
--
ALTER TABLE `reservas`
  ADD CONSTRAINT `fk_reservas_revisor` FOREIGN KEY (`revisado_por_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_reservas_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `detalle_reserva`
--
ALTER TABLE `detalle_reserva`
  ADD CONSTRAINT `fk_detalle_insumo` FOREIGN KEY (`id_insumo`) REFERENCES `insumos` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_detalle_reserva` FOREIGN KEY (`id_reserva`) REFERENCES `reservas` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
