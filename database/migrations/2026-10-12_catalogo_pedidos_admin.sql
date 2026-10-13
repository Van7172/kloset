-- Ejecutar con copia de seguridad y después de verificar que no hay pedidos
-- pendiente_pago. Conserva todos los IDs y relaciones existentes.
-- Los pagos históricos del simulador se registraban al crear el pedido:
-- pagos.fecha_creacion es la única evidencia disponible de aprobación.
-- La instalación base asigna id_rol=1 a Administrador. Comprobarlo antes
-- de aplicar la restricción siguiente; nunca se desactivan cuentas en bloque.

ALTER TABLE sistema_usuarios
  ADD COLUMN admin_activo_unico TINYINT GENERATED ALWAYS AS
    (CASE WHEN id_rol=1 AND estado_usuario_sistema='activo' THEN 1 ELSE NULL END) VIRTUAL,
  ADD UNIQUE KEY uq_admin_activo_unico (admin_activo_unico);

CREATE TABLE IF NOT EXISTS productos_colores (
  id_color INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_producto INT NOT NULL,
  nombre_color VARCHAR(100) NOT NULL,
  nombre_clave VARCHAR(100) NOT NULL,
  fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_color_producto_nombre (id_producto, nombre_clave),
  UNIQUE KEY uq_color_producto_id (id_producto, id_color),
  CONSTRAINT fk_color_producto FOREIGN KEY (id_producto) REFERENCES productos(id_producto) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO productos_colores (id_producto, nombre_color, nombre_clave)
SELECT p.id_producto, 'Original', 'original'
FROM productos p
WHERE NOT EXISTS (SELECT 1 FROM productos_colores c WHERE c.id_producto = p.id_producto);

ALTER TABLE productos_variantes
  ADD COLUMN id_color INT NULL AFTER id_producto,
  MODIFY corte_variante ENUM('No aplica','Slim','Regular','Oversize') NOT NULL DEFAULT 'No aplica';
UPDATE productos_variantes v
JOIN productos_colores c ON c.id_producto = v.id_producto AND c.nombre_clave = 'original'
SET v.id_color = c.id_color WHERE v.id_color IS NULL;
ALTER TABLE productos_variantes
  MODIFY id_color INT NOT NULL,
  DROP INDEX uq_producto_talla_corte,
  ADD UNIQUE KEY uq_variante_combinacion (id_producto, id_color, talla_variante, corte_variante),
  ADD CONSTRAINT fk_variante_color_producto FOREIGN KEY (id_producto, id_color)
    REFERENCES productos_colores(id_producto, id_color) ON DELETE RESTRICT;

ALTER TABLE productos_imagenes
  ADD COLUMN id_color INT NULL AFTER id_producto,
  ADD COLUMN principal_imagen TINYINT(1) NOT NULL DEFAULT 0;
UPDATE productos_imagenes i
JOIN productos_colores c ON c.id_producto = i.id_producto AND c.nombre_clave = 'original'
SET i.id_color = c.id_color WHERE i.id_color IS NULL;
UPDATE productos_imagenes i
JOIN (SELECT id_color, MIN(id_imagen) AS primera FROM productos_imagenes GROUP BY id_color) x
  ON x.id_color = i.id_color AND x.primera = i.id_imagen
SET i.principal_imagen = 1;
ALTER TABLE productos_imagenes
  MODIFY id_color INT NOT NULL,
  ADD KEY idx_imagen_color_orden (id_color, principal_imagen, orden_imagen),
  ADD CONSTRAINT fk_imagen_color_producto FOREIGN KEY (id_producto, id_color)
    REFERENCES productos_colores(id_producto, id_color) ON DELETE CASCADE;

ALTER TABLE pedidos
  ADD COLUMN fecha_pago_aprobado DATETIME NULL AFTER fecha_creacion,
  ADD COLUMN codigo_entrega_hash VARCHAR(255) NULL,
  ADD COLUMN codigo_entrega_cifrado VARCHAR(255) NULL,
  ADD COLUMN codigo_entrega_intentos TINYINT UNSIGNED NOT NULL DEFAULT 0,
  ADD COLUMN codigo_entrega_usado_en DATETIME NULL;
UPDATE pedidos p JOIN pagos pg ON pg.id_pedido = p.id_pedido
SET p.fecha_pago_aprobado = pg.fecha_creacion
WHERE pg.estado_pago = 'aprobado' AND p.fecha_pago_aprobado IS NULL;
UPDATE pedidos SET entrega_estimada_pedido = DATE_ADD(DATE(fecha_pago_aprobado), INTERVAL 5 DAY)
WHERE fecha_pago_aprobado IS NOT NULL;
UPDATE pedidos SET entrega_estimada_pedido = NULL WHERE fecha_pago_aprobado IS NULL;

ALTER TABLE pedidos_items
  ADD COLUMN nombre_producto_compra VARCHAR(150) NULL,
  ADD COLUMN nombre_color_compra VARCHAR(100) NULL,
  ADD COLUMN talla_compra VARCHAR(10) NULL,
  ADD COLUMN corte_compra VARCHAR(20) NULL,
  ADD COLUMN sku_compra VARCHAR(50) NULL;
-- El catálogo actual es la mejor evidencia disponible para registros anteriores.
UPDATE pedidos_items pi
JOIN productos_variantes v ON v.id_variante = pi.id_variante
JOIN productos p ON p.id_producto = v.id_producto
JOIN productos_colores c ON c.id_color = v.id_color
SET pi.nombre_producto_compra = p.nombre_producto,
    pi.nombre_color_compra = c.nombre_color,
    pi.talla_compra = v.talla_variante,
    pi.corte_compra = v.corte_variante,
    pi.sku_compra = v.sku_variante
WHERE pi.nombre_producto_compra IS NULL;

-- No se convierten pedidos pendientes a pagados: requiere conciliación previa.
-- Aplicar la migración de estados separada al confirmar cero pendientes.
