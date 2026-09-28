ALTER TABLE direcciones_envio_clientes
  ADD COLUMN tipo_direccion_cliente VARCHAR(30) NOT NULL DEFAULT 'Casa' AFTER nombre_direccion_cliente,
  ADD COLUMN departamento_envio_cliente VARCHAR(80) NOT NULL DEFAULT 'Lima Metropolitana' AFTER ciudad_envio_cliente;
