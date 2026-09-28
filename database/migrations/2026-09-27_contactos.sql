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

ALTER TABLE notificaciones
  ADD COLUMN id_contacto INT NULL,
  ADD KEY idx_notificacion_contacto (id_contacto),
  ADD CONSTRAINT fk_notificacion_contacto FOREIGN KEY (id_contacto)
    REFERENCES contactos (id_contacto) ON DELETE SET NULL;
