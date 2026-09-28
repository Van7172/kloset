ALTER TABLE pedidos
  MODIFY estado_pedido ENUM('pendiente_pago','pagado','en_preparacion','enviado','en_reparto','entregado','cancelado') NOT NULL DEFAULT 'pendiente_pago',
  ADD COLUMN transportista_pedido VARCHAR(120) NULL,
  ADD COLUMN seguimiento_pedido VARCHAR(100) NULL,
  ADD COLUMN entrega_estimada_pedido DATE NULL;

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
