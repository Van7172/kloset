CREATE TABLE IF NOT EXISTS codigos_verificacion (
  id_codigo_verificacion BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tipo_codigo ENUM('recuperacion', 'registro') NOT NULL,
  id_usuario_sistema INT NULL,
  nombre_registro VARCHAR(120) NULL,
  correo_codigo VARCHAR(150) NOT NULL,
  contrasena_hash VARCHAR(255) NULL,
  codigo_hash VARCHAR(255) NOT NULL,
  expira_en DATETIME NOT NULL,
  intentos TINYINT UNSIGNED NOT NULL DEFAULT 0,
  solicitado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  usado_en DATETIME NULL,
  PRIMARY KEY (id_codigo_verificacion),
  UNIQUE KEY uq_codigo_tipo_correo (tipo_codigo, correo_codigo),
  KEY idx_codigo_usuario (id_usuario_sistema, tipo_codigo, solicitado_en),
  CONSTRAINT fk_codigo_usuario FOREIGN KEY (id_usuario_sistema)
    REFERENCES sistema_usuarios (id_usuario_sistema) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS recuperaciones_contrasena;
DROP TABLE IF EXISTS registros_pendientes;