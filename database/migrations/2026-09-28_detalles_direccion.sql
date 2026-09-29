-- KLOSET: detalle de dirección y zona de cobertura.
-- Reejecutable en MySQL/MariaDB. Ejecutar con la base de datos seleccionada.
SET NAMES utf8mb4;

SET @kloset_ddl = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'direcciones_envio_clientes' AND COLUMN_NAME = 'tipo_direccion_cliente') = 0, 'ALTER TABLE `direcciones_envio_clientes` ADD COLUMN `tipo_direccion_cliente` VARCHAR(30) NOT NULL DEFAULT ''Casa'' AFTER `nombre_direccion_cliente`', 'DO 0');
PREPARE kloset_stmt FROM @kloset_ddl;
EXECUTE kloset_stmt;
DEALLOCATE PREPARE kloset_stmt;

SET @kloset_ddl = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'direcciones_envio_clientes' AND COLUMN_NAME = 'departamento_envio_cliente') = 0, 'ALTER TABLE `direcciones_envio_clientes` ADD COLUMN `departamento_envio_cliente` VARCHAR(80) NOT NULL DEFAULT ''Lima Metropolitana'' AFTER `ciudad_envio_cliente`', 'DO 0');
PREPARE kloset_stmt FROM @kloset_ddl;
EXECUTE kloset_stmt;
DEALLOCATE PREPARE kloset_stmt;
