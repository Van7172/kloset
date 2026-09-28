# Revisión de cuenta y compra — 27 de septiembre de 2026

## Encaje de las referencias

Se mantiene la tipografía, paleta, separadores, modo noche y composición editorial
existentes de KLOSET. Los ejemplos de personas, precios, fechas y pedidos de las
imágenes se sustituyen por datos de la API.

| Referencia | Template / ruta | Flujo integrado |
| --- | --- | --- |
| 1 | OrdersPage, `/pedidos?nuevo=1` | Confirmación del pedido persistido y enlaces a pedidos/tienda |
| 2–3 | CheckoutPage, `/pago` | Selección de dirección guardada, nueva dirección, resumen y pago de prueba |
| 4 | CartPage, `/bolsa` | Cantidades, quitar, stock, total y paso al pago |
| 5–6 | AccountPage y AddressForm, `/cuenta?tab=datos` y `tab=direcciones` | Datos, preferencias, direcciones, contraseña, descarga y borrado de medidas |
| 7–8 | OrdersPage y ShipmentInfo, `/pedidos/:id` | Estados, fechas reales del historial, entrega, seguimiento y soporte |
| 9 | AccountPage, `/cuenta?tab=pedidos` | Lista de pedidos con imágenes, estado y detalle privado |
| 10 | AccountPage/CartPage, `/cuenta?tab=bolsa` | Bolsa persistida y prendas guardadas para después |
| 11 | AccountPage, `/cuenta?tab=favoritos` | Favoritos persistidos, quitar y abrir producto |
| 12 | AccountPage, `/cuenta` | Resumen, contadores, último pedido, medidas y avisos reales |

El panel de pedidos permite registrar reparto, transportista, seguimiento y fecha
estimada. Los avisos internos respetan la preferencia de pedidos del cliente.

## Verificación y correcciones

- Compilación TypeScript/Vite local completada.
- PHP sin errores de sintaxis y `git diff --check` sin errores de espacios.
- Pruebas API en `tests/customer_flows.php`: registro, dos cuentas aisladas,
  datos, direcciones, favoritos, cantidades, guardados, checkout, historial,
  contraseña, medidas y cancelación. La prueba retira sus propios datos.
- Una cuenta ajena recibe 404 al modificar una dirección o cantidad de otra
  cuenta, o al consultar su pedido. Sin sesión, el detalle devuelve 401.
- El checkout descuenta stock, la cancelación lo restituye una vez y la segunda
  cancelación se rechaza. Se bloquean compras de productos desactivados.
- Las direcciones usadas por pedidos conservan su versión histórica al editar.
- Se corrigió la restauración de bolsa al recargar directamente `/pago`.
- La sugerencia personalizada solo aparece cuando hay un perfil de medidas;
  se retiró la etiqueta de talla calculada con valores predeterminados para visitantes.
- Se corrigieron respuestas tardías que podían sobrescribir bolsa/pedidos.
- Las cadenas de cliente/dirección del panel se escapan para evitar HTML inyectado.
- Navegador: cuenta, favoritos, dirección, cantidades, guardar/restaurar,
  checkout de prueba, confirmación, actualización desde el panel y seguimiento.
- Cuenta y seguimiento comprobados a 390 px, sin desbordamiento horizontal.

Ejecutar en XAMPP local:

```powershell
& 'C:\xampp\php\php.exe' tests/customer_flows.php
cd frontend
npm run build:local
```

## Alcance de la primera entrega

El pago sigue siendo un simulador explícito, sin cargos ni reembolsos reales.
No hay envío conectado de correo/SMS, campañas o recuperación de contraseña.
La recuperación dirige a soporte; se retiraron los códigos ficticios de registro.
Las páginas legales permanecen como borradores visibles por indicación del usuario.
El seguimiento muestra lo registrado por el equipo, sin integración con un courier.
Las migraciones nuevas de cuenta/favoritos y seguimiento/guardados están aplicadas
en la base local; deben aplicarse una vez en otro entorno.
