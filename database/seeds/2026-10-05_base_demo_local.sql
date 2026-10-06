-- Datos de referencia para la base local vacía de kloset_bd.sql.
-- Ejecutar una sola vez, antes de los seeds de catálogo y de datos demo.
-- Las direcciones de correo .test son ficticias y no reciben mensajes.

INSERT INTO sistema_roles (id_rol, nombre_rol, descripcion_rol) VALUES
(1, 'Administrador', 'Acceso al panel local de demostración'),
(2, 'Cliente', 'Usuario de la tienda de demostración');

INSERT INTO sistema_modulos (id_modulo, nombre_modulo, icono_modulo, orden_modulo) VALUES
(1, 'Catálogo', 'box', 1),
(2, 'Ventas', 'cart', 2),
(3, 'Sistema', 'settings', 4),
(4, 'Resumen', 'home', 0),
(5, 'Clientes', NULL, 3);

INSERT INTO sistema_secciones (id_seccion, id_modulo, nombre_seccion, url_seccion, orden_seccion) VALUES
(1, 4, 'Panel', 'dashboard', 0),
(2, 1, 'Categorías', 'categorias', 1),
(3, 1, 'Productos', 'productos', 2),
(4, 2, 'Pedidos', 'pedidos', 1),
(6, 3, 'Usuarios', 'usuarios', 1),
(7, 3, 'Configuración', 'configuracion', 3),
(8, 1, 'Inventario', 'variantes', 3),
(9, 2, 'Pagos', 'pagos', 3),
(10, 2, 'Notificaciones', 'notificaciones', 4),
(11, 5, 'Clientes', 'clientes', 1),
(12, 3, 'Roles', 'roles', 2);

INSERT INTO sistema_configuraciones (llave_configuracion, valor_configuracion, descripcion_configuracion) VALUES
('NOMBRE_SITIO', 'Kloset Demo', 'Nombre comercial de la instalación local.'),
('CORREO_CONTACTO', 'contacto@kloset.test', 'Correo ficticio visible en el entorno local.'),
('TELEFONO_CONTACTO', '+51 999 000 000', 'Teléfono ficticio de demostración.'),
('inventario.alerta_stock', '6', 'Umbral para mostrar stock crítico.'),
('tallas.tolerancia_cm', '2.5', 'Holgura al recomendar talla.'),
('envio.umbral_gratis', '0.00', 'Envío gratis en el entorno local.'),
('avatar.modelo_base', 'avatar_base_v3.glb', 'Modelo base del avatar.'),
('pagos.pasarela', 'simulador', 'Los pagos son siempre simulados.');
