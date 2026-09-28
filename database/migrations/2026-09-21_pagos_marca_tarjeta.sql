-- Kloset · migración
-- Añade marca y últimos dígitos de tarjeta a `pagos`.
-- Necesarios para la confirmación de pedido y el historial de "Mis pedidos"
-- del SPA (Sprint 4-6: medidas, carrito y pedidos contra la API).

ALTER TABLE pagos
  ADD COLUMN marca_pago VARCHAR(20) NULL AFTER metodo_pago,
  ADD COLUMN ultimos_digitos_pago VARCHAR(4) NULL AFTER marca_pago;
