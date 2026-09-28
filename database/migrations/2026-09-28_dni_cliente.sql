ALTER TABLE sistema_usuarios
  ADD COLUMN dni_usuario_sistema VARCHAR(8) NULL AFTER telefono_usuario_sistema,
  ADD UNIQUE KEY uq_sistema_usuarios_dni (dni_usuario_sistema);
