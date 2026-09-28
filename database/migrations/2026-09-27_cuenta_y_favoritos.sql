ALTER TABLE sistema_usuarios
  ADD COLUMN telefono_usuario_sistema VARCHAR(30) NULL AFTER correo_usuario_sistema,
  ADD COLUMN avisos_pedidos_usuario TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN avisos_novedades_usuario TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE direcciones_envio_clientes
  ADD COLUMN nombre_direccion_cliente VARCHAR(80) NOT NULL DEFAULT 'Dirección' AFTER id_usuario_sistema,
  ADD COLUMN telefono_envio_cliente VARCHAR(30) NULL AFTER referencia_envio_cliente,
  ADD COLUMN activa_envio_cliente TINYINT(1) NOT NULL DEFAULT 1;

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
