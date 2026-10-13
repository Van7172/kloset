-- Para eventos anteriores queda NULL si el estado previo no fue almacenado.
ALTER TABLE historial_estados_pedidos
  ADD COLUMN estado_anterior_historial VARCHAR(30) NULL AFTER id_usuario_sistema;
