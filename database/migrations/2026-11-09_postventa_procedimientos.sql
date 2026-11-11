ALTER TABLE solicitudes_postventa
  MODIFY estado_solicitud ENUM(
    'pendiente_revision',
    'en_revision',
    'aprobada',
    'en_proceso',
    'completada',
    'rechazada',
    'reembolso_en_proceso',
    'reembolso_realizado',
    'cambio_en_preparacion',
    'cambio_enviado',
    'cambio_en_reparto'
  ) NOT NULL DEFAULT 'pendiente_revision';

ALTER TABLE cambios_postventa
  ADD COLUMN IF NOT EXISTS codigo_confirmacion_hash VARCHAR(255) NULL AFTER cantidad,
  ADD COLUMN IF NOT EXISTS codigo_confirmacion_cifrado TEXT NULL AFTER codigo_confirmacion_hash,
  ADD COLUMN IF NOT EXISTS codigo_confirmacion_intentos TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER codigo_confirmacion_cifrado,
  ADD COLUMN IF NOT EXISTS codigo_confirmacion_usado_en DATETIME NULL AFTER codigo_confirmacion_intentos,
  ADD COLUMN IF NOT EXISTS fecha_preparacion DATETIME NULL AFTER estado_cambio,
  ADD COLUMN IF NOT EXISTS fecha_reparto DATETIME NULL AFTER fecha_preparacion;

ALTER TABLE solicitudes_postventa
  ADD COLUMN IF NOT EXISTS codigo_confirmacion_hash VARCHAR(255) NULL AFTER fecha_actualizacion,
  ADD COLUMN IF NOT EXISTS codigo_confirmacion_cifrado TEXT NULL AFTER codigo_confirmacion_hash,
  ADD COLUMN IF NOT EXISTS codigo_confirmacion_intentos TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER codigo_confirmacion_cifrado,
  ADD COLUMN IF NOT EXISTS codigo_confirmacion_usado_en DATETIME NULL AFTER codigo_confirmacion_intentos;

CREATE TABLE IF NOT EXISTS pedidos_retorno_fisico (
  id_retorno INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_pedido INT NOT NULL,
  estado_retorno ENUM('pendiente','completado') NOT NULL DEFAULT 'pendiente',
  id_usuario_registro INT NULL,
  id_usuario_verificacion INT NULL,
  fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_completado DATETIME NULL,
  UNIQUE KEY uq_retorno_pedido (id_pedido),
  CONSTRAINT fk_retorno_pedido FOREIGN KEY (id_pedido) REFERENCES pedidos(id_pedido) ON DELETE RESTRICT,
  CONSTRAINT fk_retorno_registro FOREIGN KEY (id_usuario_registro) REFERENCES sistema_usuarios(id_usuario_sistema) ON DELETE SET NULL,
  CONSTRAINT fk_retorno_verificacion FOREIGN KEY (id_usuario_verificacion) REFERENCES sistema_usuarios(id_usuario_sistema) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pedidos_retorno_fisico_items (
  id_retorno_item INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_retorno INT UNSIGNED NOT NULL,
  id_pedido_item INT NOT NULL,
  id_variante INT NOT NULL,
  cantidad_esperada INT UNSIGNED NOT NULL,
  cantidad_apta INT UNSIGNED NULL,
  cantidad_no_apta INT UNSIGNED NULL,
  fecha_verificacion DATETIME NULL,
  UNIQUE KEY uq_retorno_item (id_retorno,id_pedido_item),
  CONSTRAINT fk_retorno_item_retorno FOREIGN KEY (id_retorno) REFERENCES pedidos_retorno_fisico(id_retorno) ON DELETE CASCADE,
  CONSTRAINT fk_retorno_item_pedido FOREIGN KEY (id_pedido_item) REFERENCES pedidos_items(id_pedido_item) ON DELETE RESTRICT,
  CONSTRAINT fk_retorno_item_variante FOREIGN KEY (id_variante) REFERENCES productos_variantes(id_variante) ON DELETE RESTRICT
) ENGINE=InnoDB;
