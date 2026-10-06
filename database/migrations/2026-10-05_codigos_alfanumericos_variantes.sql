-- Actualiza únicamente los SKU generados por el catálogo demo anterior.
-- Conserva id_variante y todas las relaciones con carrito, pedidos e inventario.
-- Puede ejecutarse más de una vez: los códigos nuevos ya no coinciden con KL-.
UPDATE productos_variantes
SET sku_variante = CONCAT('KL', LPAD(id_producto, 3, '0'), talla_variante,
    UPPER(LEFT(corte_variante, 3)),
    UPPER(LEFT(MD5(CONCAT(id_producto, ':', talla_variante, ':', corte_variante)), 6)))
WHERE sku_variante REGEXP '^KL-[0-9]+-(XS|S|M|L|XL)-(SLI|REG|OVE)$';
