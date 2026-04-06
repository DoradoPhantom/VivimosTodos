-- VivimosTodos - Esquema MySQL (XAMPP)
-- Ejecutar desde phpMyAdmin o: mysql -u root < schema.sql
-- Acceso al sistema por NOMBRE DE USUARIO (no correo), típico en conjunto residencial.

CREATE DATABASE IF NOT EXISTS vivemos_todos
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE vivemos_todos;

-- Roles: administrador, residente, supervisor (cumplimiento requisito funcional)
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

-- Insumos de inventario (tabla definida con campos de negocio claros)
CREATE TABLE IF NOT EXISTS insumos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  codigo VARCHAR(50) NULL COMMENT 'SKU o código interno',
  nombre VARCHAR(200) NOT NULL,
  descripcion TEXT NULL,
  categoria VARCHAR(100) NULL COMMENT 'Ej: limpieza, papelería, herramientas',
  unidad_medida VARCHAR(30) NOT NULL DEFAULT 'unidad' COMMENT 'unidad, kg, L, m, etc.',
  cantidad_stock DECIMAL(12, 3) NOT NULL DEFAULT 0,
  stock_minimo DECIMAL(12, 3) NOT NULL DEFAULT 0 COMMENT 'Umbral de alerta',
  precio_unitario DECIMAL(14, 4) NULL COMMENT 'Opcional, para valorización',
  ubicacion VARCHAR(120) NULL COMMENT 'Estante, bodega, etc.',
  activo TINYINT(1) NOT NULL DEFAULT 1,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_insumos_codigo (codigo),
  KEY idx_insumos_categoria (categoria),
  KEY idx_insumos_nombre (nombre),
  KEY idx_insumos_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Administrador inicial (contraseña: admin123). Cambiar tras el primer acceso.
INSERT INTO usuarios (nombre_completo, usuario, password_hash, rol, activo)
SELECT 'Administrador del sistema', 'admin', '$2y$10$EcIuf.1A/mO19B0e9vfp6.SD8pDGVanO7OOOZ6w6HfaMRMpc23Wwu', 'administrador', 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM usuarios WHERE usuario = 'admin' LIMIT 1);

-- Supervisor inicial (contraseña: supervisor123). Cambiar tras el primer acceso.
INSERT INTO usuarios (nombre_completo, usuario, password_hash, rol, activo)
SELECT 'Supervisor general', 'supervisor', '$2y$10$PFfuX6uJoYwFwvw5bIXNOuie0WxowMugiO9jGhWyfZe7AozF.Pwo2', 'supervisor', 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM usuarios WHERE usuario = 'supervisor' LIMIT 1);
