CREATE TABLE IF NOT EXISTS solicitudes_postventa (
  id_solicitud_postventa INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_usuario_sistema INT NOT NULL,
  id_pedido INT NOT NULL,
  tipo_solicitud ENUM('reembolso','cambio') NOT NULL,
  estado_solicitud ENUM('pendiente_revision','en_revision','aprobada','en_proceso','completada','rechazada','reembolso_en_proceso','reembolso_realizado','cambio_en_preparacion','cambio_enviado','cambio_en_reparto') NOT NULL DEFAULT 'pendiente_revision',
  motivo_solicitud VARCHAR(120) NOT NULL,
  descripcion_solicitud TEXT NOT NULL,
  importe_aprobado DECIMAL(10,2) NULL,
  motivo_rechazo VARCHAR(500) NULL,
  id_admin_resolucion INT NULL,
  fecha_resolucion DATETIME NULL,
  fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_postventa_usuario_fecha (id_usuario_sistema, fecha_creacion),
  KEY idx_postventa_estado_fecha (estado_solicitud, fecha_creacion),
  KEY idx_postventa_pedido (id_pedido),
  CONSTRAINT fk_postventa_usuario FOREIGN KEY (id_usuario_sistema) REFERENCES sistema_usuarios(id_usuario_sistema) ON DELETE RESTRICT,
  CONSTRAINT fk_postventa_pedido FOREIGN KEY (id_pedido) REFERENCES pedidos(id_pedido) ON DELETE RESTRICT,
  CONSTRAINT fk_postventa_admin FOREIGN KEY (id_admin_resolucion) REFERENCES sistema_usuarios(id_usuario_sistema) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS solicitudes_postventa_items (
  id_item_postventa INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_solicitud_postventa INT UNSIGNED NOT NULL,
  id_pedido_item INT NOT NULL,
  id_variante INT NOT NULL,
  cantidad INT UNSIGNED NOT NULL,
  importe_item DECIMAL(10,2) NOT NULL,
  nombre_producto_compra VARCHAR(150) NOT NULL,
  nombre_color_compra VARCHAR(100) NOT NULL,
  talla_compra VARCHAR(10) NOT NULL,
  corte_compra VARCHAR(20) NOT NULL,
  sku_compra VARCHAR(50) NOT NULL,
  KEY idx_postventa_item_solicitud (id_solicitud_postventa),
  KEY idx_postventa_item_pedido (id_pedido_item),
  CONSTRAINT fk_postventa_item_solicitud FOREIGN KEY (id_solicitud_postventa) REFERENCES solicitudes_postventa(id_solicitud_postventa) ON DELETE CASCADE,
  CONSTRAINT fk_postventa_item_pedido FOREIGN KEY (id_pedido_item) REFERENCES pedidos_items(id_pedido_item) ON DELETE RESTRICT,
  CONSTRAINT fk_postventa_item_variante FOREIGN KEY (id_variante) REFERENCES productos_variantes(id_variante) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS solicitudes_postventa_evidencias (
  id_evidencia_postventa INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_solicitud_postventa INT UNSIGNED NOT NULL,
  ruta_evidencia VARCHAR(255) NOT NULL,
  tipo_mime_evidencia VARCHAR(100) NOT NULL,
  fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_postventa_evidencia (id_solicitud_postventa, ruta_evidencia),
  CONSTRAINT fk_postventa_evidencia_solicitud FOREIGN KEY (id_solicitud_postventa) REFERENCES solicitudes_postventa(id_solicitud_postventa) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS historial_solicitudes_postventa (
  id_historial_postventa INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_solicitud_postventa INT UNSIGNED NOT NULL,
  id_usuario_sistema INT NULL,
  estado_anterior VARCHAR(30) NULL,
  estado_nuevo VARCHAR(30) NOT NULL,
  comentario TEXT NULL,
  fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_historial_postventa_solicitud (id_solicitud_postventa, fecha_creacion),
  CONSTRAINT fk_historial_postventa_solicitud FOREIGN KEY (id_solicitud_postventa) REFERENCES solicitudes_postventa(id_solicitud_postventa) ON DELETE CASCADE,
  CONSTRAINT fk_historial_postventa_usuario FOREIGN KEY (id_usuario_sistema) REFERENCES sistema_usuarios(id_usuario_sistema) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reembolsos_postventa (
  id_reembolso_postventa INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_solicitud_postventa INT UNSIGNED NOT NULL,
  importe_reembolso DECIMAL(10,2) NOT NULL,
  estado_reembolso ENUM('aprobado','en_proceso','completado') NOT NULL DEFAULT 'aprobado',
  referencia_reembolso VARCHAR(100) NULL,
  fecha_completado DATETIME NULL,
  UNIQUE KEY uq_reembolso_solicitud (id_solicitud_postventa),
  CONSTRAINT fk_reembolso_postventa_solicitud FOREIGN KEY (id_solicitud_postventa) REFERENCES solicitudes_postventa(id_solicitud_postventa) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS cambios_postventa (
  id_cambio_postventa INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_solicitud_postventa INT UNSIGNED NOT NULL,
  id_item_postventa INT UNSIGNED NOT NULL,
  id_variante_original INT NOT NULL,
  id_variante_reemplazo INT NOT NULL,
  cantidad INT UNSIGNED NOT NULL,
  estado_cambio ENUM('aprobado','en_proceso','completado') NOT NULL DEFAULT 'aprobado',
  fecha_completado DATETIME NULL,
  UNIQUE KEY uq_cambio_item (id_item_postventa),
  CONSTRAINT fk_cambio_postventa_solicitud FOREIGN KEY (id_solicitud_postventa) REFERENCES solicitudes_postventa(id_solicitud_postventa) ON DELETE RESTRICT,
  CONSTRAINT fk_cambio_postventa_item FOREIGN KEY (id_item_postventa) REFERENCES solicitudes_postventa_items(id_item_postventa) ON DELETE RESTRICT,
  CONSTRAINT fk_cambio_postventa_original FOREIGN KEY (id_variante_original) REFERENCES productos_variantes(id_variante) ON DELETE RESTRICT,
  CONSTRAINT fk_cambio_postventa_reemplazo FOREIGN KEY (id_variante_reemplazo) REFERENCES productos_variantes(id_variante) ON DELETE RESTRICT
) ENGINE=InnoDB;

INSERT INTO sistema_secciones (id_seccion, id_modulo, nombre_seccion, url_seccion, orden_seccion)
SELECT 13, 2, 'Devoluciones e incidencias', 'postventa', 5
WHERE NOT EXISTS (SELECT 1 FROM sistema_secciones WHERE url_seccion = 'postventa');

INSERT INTO usuarios_secciones (id_usuario_sistema, id_seccion)
SELECT u.id_usuario_sistema, s.id_seccion
FROM sistema_usuarios u
INNER JOIN sistema_secciones s ON s.url_seccion = 'postventa'
WHERE u.id_rol = 1
  AND NOT EXISTS (
    SELECT 1 FROM usuarios_secciones us
    WHERE us.id_usuario_sistema = u.id_usuario_sistema AND us.id_seccion = s.id_seccion
  );
