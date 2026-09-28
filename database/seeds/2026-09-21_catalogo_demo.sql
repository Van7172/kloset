-- Kloset · datos de ejemplo
-- El catálogo (categorías, productos, variantes) llega vacío en una instalación
-- nueva. Este seed carga las 8 prendas de referencia del diseño
-- (proyecto Claude Design 08ff5c48-369d-471e-9b1a-eda07d6e3852), cada una con
-- variantes para las 3 tallas de corte × 5 tallas, para poder probar catálogo,
-- carrito y checkout de punta a punta. No incluye imágenes: el front-end ya
-- muestra el patrón de reemplazo cuando `url_imagen` es null.

INSERT INTO categorias (nombre_categoria, url_categoria, descripcion_categoria) VALUES
('Camisetas', 'camisetas', 'Camisetas técnicas de entrenamiento'),
('Mallas', 'mallas', 'Mallas de compresión'),
('Shorts', 'shorts', 'Shorts ligeros de running'),
('Capas', 'capas', 'Cortavientos y sudaderas'),
('Tops', 'tops', 'Tops deportivos'),
('Pantalones', 'pantalones', 'Pantalones de jogging técnico');

INSERT INTO productos (id_categoria, nombre_producto, url_producto, descripcion_producto, precio_producto, estado_producto) VALUES
((SELECT id_categoria FROM categorias WHERE url_categoria='camisetas'), 'Camiseta Tempo Dry', 'camiseta-tempo-dry', 'Tejido de secado rápido con costuras planas en los hombros. Pensada para series de intervalos donde la prenda no debe moverse contigo.', 139.00, 'activo'),
((SELECT id_categoria FROM categorias WHERE url_categoria='mallas'), 'Malla Vector Long', 'malla-vector-long', 'Compresión graduada en pantorrilla y cintura alta sin banda rígida. La costura posterior sigue la línea de la pierna.', 229.00, 'activo'),
((SELECT id_categoria FROM categorias WHERE url_categoria='capas'), 'Cortavientos Draft 02', 'cortavientos-draft-02', 'Capa exterior plegable con ventilación en la espalda. Corte recto que admite una capa media debajo.', 429.00, 'activo'),
((SELECT id_categoria FROM categorias WHERE url_categoria='capas'), 'Sudadera Base Loop', 'sudadera-base-loop', 'Hombro caído y cuerpo amplio. La misma sudadera que usarás para calentar y para el camino de vuelta.', 309.00, 'activo'),
((SELECT id_categoria FROM categorias WHERE url_categoria='shorts'), 'Short Split 5"', 'short-split-5', 'Apertura lateral con malla interior y bolsillo trasero con cierre. Peso mínimo, cero rebote.', 165.00, 'activo'),
((SELECT id_categoria FROM categorias WHERE url_categoria='tops'), 'Top Anchor Medium', 'top-anchor-medium', 'Soporte medio con banda inferior ancha y espalda abierta. Se ajusta sin comprimir la caja torácica.', 189.00, 'activo'),
((SELECT id_categoria FROM categorias WHERE url_categoria='pantalones'), 'Pantalón Cadence Jog', 'pantalon-cadence-jog', 'Tiro medio con tobillo entallado. La rodilla está preformada para no tirar al subir escaleras.', 279.00, 'activo'),
((SELECT id_categoria FROM categorias WHERE url_categoria='camisetas'), 'Camiseta Shell Oversize', 'camiseta-shell-oversize', 'Cuerpo ancho con caída pesada y cuello reforzado. Pensada para llevar suelta sobre el top.', 159.00, 'activo');

-- 3 cortes × 5 tallas por producto: el front-end no deja elegir corte por
-- prenda (es una preferencia global del usuario), así que cada producto debe
-- tener las 15 combinaciones o "Añadir a la bolsa" fallará según el corte
-- activo del visitante.
INSERT INTO productos_variantes (id_producto, talla_variante, corte_variante, sku_variante, stock_variante)
SELECT p.id_producto, t.talla, c.corte,
       CONCAT('KL-', p.id_producto, '-', t.talla, '-', LEFT(c.corte, 3)),
       25
FROM productos p
CROSS JOIN (SELECT 'XS' talla UNION SELECT 'S' UNION SELECT 'M' UNION SELECT 'L' UNION SELECT 'XL') t
CROSS JOIN (SELECT 'Slim' corte UNION SELECT 'Regular' UNION SELECT 'Oversize') c
WHERE p.url_producto IN (
  'camiseta-tempo-dry', 'malla-vector-long', 'cortavientos-draft-02', 'sudadera-base-loop',
  'short-split-5', 'top-anchor-medium', 'pantalon-cadence-jog', 'camiseta-shell-oversize'
);

-- Talla agotada de ejemplo, para probar el mensaje de "sin stock" del carrito.
UPDATE productos_variantes SET stock_variante = 0
WHERE id_producto = (SELECT id_producto FROM productos WHERE url_producto = 'short-split-5')
  AND talla_variante IN ('XS', 'S');
