-- Permite que el administrador cree personal con acceso por secciones.
-- Ejecutar una sola vez en cada base; el alta de Administrador sigue cerrada.
INSERT INTO sistema_roles (nombre_rol, descripcion_rol, estado_rol)
SELECT 'Asesor', 'Personal con acceso al panel según secciones asignadas', 'activo'
WHERE NOT EXISTS (SELECT 1 FROM sistema_roles WHERE nombre_rol = 'Asesor');
