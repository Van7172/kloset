-- Precondición: SELECT COUNT(*) FROM pedidos WHERE estado_pedido='pendiente_pago' = 0.
-- Si existen, conciliarlos sin atribuirles un pago ficticio antes de ejecutar.
DELIMITER $$
CREATE PROCEDURE kloset_quitar_pendientes()
BEGIN
  IF EXISTS (SELECT 1 FROM pedidos WHERE estado_pedido='pendiente_pago') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Hay pedidos pendientes que requieren conciliación';
  END IF;
END$$
DELIMITER ;
CALL kloset_quitar_pendientes();
DROP PROCEDURE kloset_quitar_pendientes;
ALTER TABLE pedidos MODIFY estado_pedido
  ENUM('pagado','en_preparacion','enviado','en_reparto','entregado','cancelado') NOT NULL DEFAULT 'pagado';
