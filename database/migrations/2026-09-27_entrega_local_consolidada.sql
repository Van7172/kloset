-- KLOSET: preparación de primera entrega local, revisión 1.
-- Ejecutar con la base existente seleccionada (kloset_bd local).
-- Reejecutable en MariaDB/MySQL: comprueba columnas y constraints existentes.
-- No borra registros. DDL hace commit implícito: guardar respaldo antes de producción.
SET NAMES utf8mb4;
CREATE TABLE IF NOT EXISTS contactos (
  id_contacto INT NOT NULL AUTO_INCREMENT,
  tema_contacto ENUM('talla', 'pedido', 'otro') NOT NULL,
  nombre_contacto VARCHAR(120) NOT NULL,
  correo_contacto VARCHAR(150) NOT NULL,
  telefono_contacto VARCHAR(30) DEFAULT NULL,
  mensaje_contacto TEXT NOT NULL,
  fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_contacto),
  KEY idx_contactos_fecha (fecha_creacion),
  KEY idx_contactos_correo (correo_contacto)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS favoritos_clientes (
  id_usuario_sistema INT NOT NULL,
  id_producto INT NOT NULL,
  fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_usuario_sistema, id_producto),
  KEY idx_favoritos_producto (id_producto),
  CONSTRAINT fk_favoritos_usuario FOREIGN KEY (id_usuario_sistema)
    REFERENCES sistema_usuarios (id_usuario_sistema) ON DELETE CASCADE,
  CONSTRAINT fk_favoritos_producto FOREIGN KEY (id_producto)
    REFERENCES productos (id_producto) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS prendas_guardadas_clientes (
  id_usuario_sistema INT NOT NULL,
  id_variante INT NOT NULL,
  cantidad INT NOT NULL DEFAULT 1,
  fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_usuario_sistema, id_variante),
  CONSTRAINT fk_guardados_usuario FOREIGN KEY (id_usuario_sistema)
    REFERENCES sistema_usuarios (id_usuario_sistema) ON DELETE CASCADE,
  CONSTRAINT fk_guardados_variante FOREIGN KEY (id_variante)
    REFERENCES productos_variantes (id_variante) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @kloset_ddl = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pagos' AND COLUMN_NAME = 'marca_pago') = 0, 'ALTER TABLE `pagos` ADD COLUMN `marca_pago` VARCHAR(20) NULL', 'DO 0');
PREPARE kloset_stmt FROM @kloset_ddl;
EXECUTE kloset_stmt;
DEALLOCATE PREPARE kloset_stmt;

SET @kloset_ddl = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pagos' AND COLUMN_NAME = 'ultimos_digitos_pago') = 0, 'ALTER TABLE `pagos` ADD COLUMN `ultimos_digitos_pago` VARCHAR(4) NULL', 'DO 0');
PREPARE kloset_stmt FROM @kloset_ddl;
EXECUTE kloset_stmt;
DEALLOCATE PREPARE kloset_stmt;

SET @kloset_ddl = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notificaciones' AND COLUMN_NAME = 'id_contacto') = 0, 'ALTER TABLE `notificaciones` ADD COLUMN `id_contacto` INT NULL', 'DO 0');
PREPARE kloset_stmt FROM @kloset_ddl;
EXECUTE kloset_stmt;
DEALLOCATE PREPARE kloset_stmt;

SET @kloset_ddl = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sistema_usuarios' AND COLUMN_NAME = 'telefono_usuario_sistema') = 0, 'ALTER TABLE `sistema_usuarios` ADD COLUMN `telefono_usuario_sistema` VARCHAR(30) NULL', 'DO 0');
PREPARE kloset_stmt FROM @kloset_ddl;
EXECUTE kloset_stmt;
DEALLOCATE PREPARE kloset_stmt;

SET @kloset_ddl = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sistema_usuarios' AND COLUMN_NAME = 'avisos_pedidos_usuario') = 0, 'ALTER TABLE `sistema_usuarios` ADD COLUMN `avisos_pedidos_usuario` TINYINT(1) NOT NULL DEFAULT 1', 'DO 0');
PREPARE kloset_stmt FROM @kloset_ddl;
EXECUTE kloset_stmt;
DEALLOCATE PREPARE kloset_stmt;

SET @kloset_ddl = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sistema_usuarios' AND COLUMN_NAME = 'avisos_novedades_usuario') = 0, 'ALTER TABLE `sistema_usuarios` ADD COLUMN `avisos_novedades_usuario` TINYINT(1) NOT NULL DEFAULT 0', 'DO 0');
PREPARE kloset_stmt FROM @kloset_ddl;
EXECUTE kloset_stmt;
DEALLOCATE PREPARE kloset_stmt;

SET @kloset_ddl = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'direcciones_envio_clientes' AND COLUMN_NAME = 'nombre_direccion_cliente') = 0, 'ALTER TABLE `direcciones_envio_clientes` ADD COLUMN `nombre_direccion_cliente` VARCHAR(80) NOT NULL DEFAULT ''Dirección''', 'DO 0');
PREPARE kloset_stmt FROM @kloset_ddl;
EXECUTE kloset_stmt;
DEALLOCATE PREPARE kloset_stmt;

SET @kloset_ddl = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'direcciones_envio_clientes' AND COLUMN_NAME = 'telefono_envio_cliente') = 0, 'ALTER TABLE `direcciones_envio_clientes` ADD COLUMN `telefono_envio_cliente` VARCHAR(30) NULL', 'DO 0');
PREPARE kloset_stmt FROM @kloset_ddl;
EXECUTE kloset_stmt;
DEALLOCATE PREPARE kloset_stmt;

SET @kloset_ddl = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'direcciones_envio_clientes' AND COLUMN_NAME = 'activa_envio_cliente') = 0, 'ALTER TABLE `direcciones_envio_clientes` ADD COLUMN `activa_envio_cliente` TINYINT(1) NOT NULL DEFAULT 1', 'DO 0');
PREPARE kloset_stmt FROM @kloset_ddl;
EXECUTE kloset_stmt;
DEALLOCATE PREPARE kloset_stmt;

SET @kloset_ddl = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'transportista_pedido') = 0, 'ALTER TABLE `pedidos` ADD COLUMN `transportista_pedido` VARCHAR(120) NULL', 'DO 0');
PREPARE kloset_stmt FROM @kloset_ddl;
EXECUTE kloset_stmt;
DEALLOCATE PREPARE kloset_stmt;

SET @kloset_ddl = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'seguimiento_pedido') = 0, 'ALTER TABLE `pedidos` ADD COLUMN `seguimiento_pedido` VARCHAR(100) NULL', 'DO 0');
PREPARE kloset_stmt FROM @kloset_ddl;
EXECUTE kloset_stmt;
DEALLOCATE PREPARE kloset_stmt;

SET @kloset_ddl = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'entrega_estimada_pedido') = 0, 'ALTER TABLE `pedidos` ADD COLUMN `entrega_estimada_pedido` DATE NULL', 'DO 0');
PREPARE kloset_stmt FROM @kloset_ddl;
EXECUTE kloset_stmt;
DEALLOCATE PREPARE kloset_stmt;

ALTER TABLE pedidos MODIFY estado_pedido ENUM('pendiente_pago','pagado','en_preparacion','enviado','en_reparto','entregado','cancelado') NOT NULL DEFAULT 'pendiente_pago';

SET @kloset_ddl = IF((SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'notificaciones' AND CONSTRAINT_NAME = 'fk_notificacion_contacto') = 0, 'ALTER TABLE notificaciones ADD CONSTRAINT fk_notificacion_contacto FOREIGN KEY (id_contacto) REFERENCES contactos(id_contacto) ON DELETE SET NULL', 'DO 0');
PREPARE kloset_stmt FROM @kloset_ddl;
EXECUTE kloset_stmt;
DEALLOCATE PREPARE kloset_stmt;

CREATE TABLE IF NOT EXISTS sistema_migraciones (
 archivo_migracion VARCHAR(190) NOT NULL PRIMARY KEY,
 revision_migracion INT NOT NULL DEFAULT 1,
 sha256_archivo CHAR(64) NULL,
 primera_ejecucion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 ultima_ejecucion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 descripcion_migracion VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO sistema_migraciones (archivo_migracion, descripcion_migracion) VALUES ('2026-09-27_entrega_local_consolidada.sql','Preparación de pagos, contacto, cuenta, direcciones, favoritos, guardados y seguimiento') ON DUPLICATE KEY UPDATE ultima_ejecucion = CURRENT_TIMESTAMP;
