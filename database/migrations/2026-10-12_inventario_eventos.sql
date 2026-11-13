CREATE TABLE IF NOT EXISTS inventario_eventos (
  id_evento BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_variante INT NOT NULL,
  tipo_evento VARCHAR(30) NOT NULL,
  id_usuario_sistema INT NULL,
  fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_inventario_evento_fecha (fecha_creacion),
  CONSTRAINT fk_inventario_evento_variante FOREIGN KEY (id_variante) REFERENCES productos_variantes(id_variante) ON DELETE CASCADE,
  CONSTRAINT fk_inventario_evento_usuario FOREIGN KEY (id_usuario_sistema) REFERENCES sistema_usuarios(id_usuario_sistema) ON DELETE SET NULL
) ENGINE=InnoDB;
