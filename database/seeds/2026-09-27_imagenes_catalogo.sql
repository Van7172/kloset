-- Fotografías editoriales cargadas desde el panel el 27-09-2026.
-- Ejecutar después de 2026-09-21_catalogo_demo.sql. Los archivos están
-- en app/public_root/imgs/productos/ y se referencian por nombre relativo.
-- Idempotente para instalaciones que ya recibieron las imágenes por el panel.
INSERT INTO productos_imagenes (id_producto, url_imagen, orden_imagen)
SELECT p.id_producto, x.archivo, 1
FROM productos p
JOIN (
  SELECT 'camiseta-shell-oversize' slug, '6ab9a0af9586f_camiseta-shell.webp' archivo
  UNION ALL SELECT 'camiseta-tempo-dry', '6ab9a0a57e695_camisetas.webp'
  UNION ALL SELECT 'cortavientos-draft-02', '6ab9a0af9bb0d_capas.webp'
  UNION ALL SELECT 'malla-vector-long', '6ab9a0af9dfa2_mallas.webp'
  UNION ALL SELECT 'pantalon-cadence-jog', '6ab9a0afa08c1_pantalones.webp'
  UNION ALL SELECT 'short-split-5', '6ab9a0afa296b_shorts.webp'
  UNION ALL SELECT 'sudadera-base-loop', '6ab9a0afa4bd3_sudadera.webp'
  UNION ALL SELECT 'top-anchor-medium', '6ab9a0afa78de_tops.webp'
) x ON x.slug = p.url_producto
WHERE NOT EXISTS (
  SELECT 1 FROM productos_imagenes i
  WHERE i.id_producto = p.id_producto AND i.url_imagen = x.archivo
);
