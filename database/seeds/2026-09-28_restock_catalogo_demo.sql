-- KLOSET: reposición inicial del catálogo demo para la primera entrega local.
-- Corrige las seis variantes de Short Split 5" que el seed original dejó en 0
-- como muestra de agotado. Es seguro repetirlo mientras se use este catálogo demo.
UPDATE productos_variantes AS v
INNER JOIN productos AS p ON p.id_producto = v.id_producto
SET v.stock_variante = 25
WHERE p.url_producto = 'short-split-5'
  AND v.talla_variante IN ('XS', 'S')
  AND v.stock_variante = 0;
