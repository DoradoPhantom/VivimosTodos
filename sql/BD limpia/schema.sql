-- Vivimos Todos — esquema completo con mejoras de lógica
-- Importar archivo completo en phpMyAdmin → Importar, o pegar TODO el contenido en SQL.

CREATE DATABASE IF NOT EXISTS vivimos_todos
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE vivimos_todos;


CREATE TABLE IF NOT EXISTS usuarios (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre_completo VARCHAR(120) NOT NULL,
  usuario VARCHAR(80) NOT NULL COMMENT 'Identificador de acceso (ej. apto101, arrendatario actual)',
  password_hash VARCHAR(255) NOT NULL,
  rol ENUM('administrador', 'residente', 'supervisor') NOT NULL DEFAULT 'residente',
  activo TINYINT(1) NOT NULL DEFAULT 1,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_usuarios_usuario (usuario),
  KEY idx_usuarios_rol (rol),
  KEY idx_usuarios_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS insumos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  codigo VARCHAR(50) NULL COMMENT 'Código interno del ítem',
  nombre VARCHAR(200) NOT NULL,
  descripcion TEXT NULL,
  categoria VARCHAR(100) NULL COMMENT 'Ej: catering, sonido, decoración',
  unidad_medida VARCHAR(30) NOT NULL DEFAULT 'unidad' COMMENT 'Interno; nuevos registros usan servicio',
  cantidad_stock DECIMAL(12, 3) NOT NULL DEFAULT 0,
  stock_minimo DECIMAL(12, 3) NOT NULL DEFAULT 0,
  precio_unitario DECIMAL(14, 4) NULL COMMENT 'Precio de referencia',
  ubicacion VARCHAR(120) NULL,
  estado_operativo ENUM('disponible','danado','reparacion') NOT NULL DEFAULT 'disponible' COMMENT 'Disponible / Dañado / En reparación',
  activo TINYINT(1) NOT NULL DEFAULT 1,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_insumos_codigo (codigo),
  KEY idx_insumos_categoria (categoria),
  KEY idx_insumos_nombre (nombre),
  KEY idx_insumos_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS reservas (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id INT UNSIGNED NOT NULL COMMENT 'Quien solicita la reserva',
  fecha_evento DATETIME NOT NULL COMMENT 'Inicio del evento',
  fecha_fin DATETIME NULL COMMENT 'Fin del evento (por defecto 23:59 del mismo día)',
  descripcion TEXT NULL COMMENT 'Notas y detalle del evento',
  estado ENUM('pendiente', 'aprobada', 'rechazada') NOT NULL DEFAULT 'pendiente',
  comentario_revision VARCHAR(500) NULL COMMENT 'Motivo del rechazo u observación del revisor',
  revisado_por_id INT UNSIGNED NULL,
  revisado_en DATETIME NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_reservas_usuario (usuario_id),
  KEY idx_reservas_estado (estado),
  KEY idx_reservas_fecha (fecha_evento),
  KEY idx_reservas_fecha_fin (fecha_fin),
  CONSTRAINT fk_reservas_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE RESTRICT,
  CONSTRAINT fk_reservas_revisor FOREIGN KEY (revisado_por_id) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS reservas_insumos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  reserva_id INT UNSIGNED NOT NULL,
  insumo_id INT UNSIGNED NOT NULL,
  cantidad_solicitada DECIMAL(12, 3) NOT NULL DEFAULT 1,
  cantidad_entregada DECIMAL(12, 3) NULL COMMENT 'Se llena al aprobar la reserva; NULL = pendiente, valor = entregado',
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_reserva_insumo (reserva_id, insumo_id),
  KEY idx_ri_insumo (insumo_id),
  KEY idx_ri_entregada (cantidad_entregada),
  CONSTRAINT fk_ri_reserva FOREIGN KEY (reserva_id) REFERENCES reservas (id) ON DELETE CASCADE,
  CONSTRAINT fk_ri_insumo FOREIGN KEY (insumo_id) REFERENCES insumos (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Administrador inicial (contraseña: admin123). Cambiar tras el primer acceso.
INSERT INTO usuarios (nombre_completo, usuario, password_hash, rol, activo)
SELECT 'Administrador del sistema', 'admin', '$2y$10$EcIuf.1A/mO19B0e9vfp6.SD8pDGVanO7OOOZ6w6HfaMRMpc23Wwu', 'administrador', 1
FROM (SELECT 1) AS _seed
WHERE NOT EXISTS (SELECT 1 FROM usuarios WHERE usuario = 'admin' LIMIT 1);

-- Supervisor inicial (contraseña: supervisor123). Cambiar tras el primer acceso.
INSERT INTO usuarios (nombre_completo, usuario, password_hash, rol, activo)
SELECT 'Supervisor general', 'supervisor', '$2y$10$PFfuX6uJoYwFwvw5bIXNOuie0WxowMugiO9jGhWyfZe7AozF.Pwo2', 'supervisor', 1
FROM (SELECT 1) AS _seed
WHERE NOT EXISTS (SELECT 1 FROM usuarios WHERE usuario = 'supervisor' LIMIT 1);
