# Bitácora — panel y base local

Fecha: 27 de septiembre de 2026. Entorno: XAMPP, PHP 8.2, base
`kloset_bd`, servidor `10.4.32-MariaDB`, puerto 3307.

## SQL ejecutado y conservado

Archivo: `database/migrations/2026-09-27_entrega_local_consolidada.sql`.
Se ejecutó dos veces correctamente para comprobar su repetición.

SHA-256:
`9b744f2dcc351bf96e0d5186289dd79b80a7673702af048a28e1bca9be9d31b8`.

Reúne las migraciones anteriores de esta entrega: campos de pagos, contactos,
teléfono/preferencias, direcciones activas, favoritos, prendas guardadas y
seguimiento de pedidos. Consulta `information_schema` antes de añadir columnas
y la relación de contacto. Conserva los datos existentes. Añade
`sistema_migraciones` para registrar archivo, revisión, huella y fechas de ejecución.

El archivo trabaja **sobre el esquema base existente**; no sustituye un respaldo
completo ni crea cuentas de administración. Las migraciones individuales siguen
conservadas por historial, pero para preparar otra copia del esquema base se
usa el consolidado, sin ejecutar además los ALTER originales.

Ejecutar desde la raíz del proyecto:

```powershell
& 'C:\xampp\php\php.exe' database/preparar_entrega.php
```

También se puede abrir el SQL y ejecutarlo directamente en phpMyAdmin, con la
base seleccionada. El ejecutor PHP registra además el SHA-256 del archivo.
MariaDB/MySQL hace commit implícito en DDL; la preparación no es una única
transacción reversible. La repetición comprueba presencia de columnas/tablas,
pero no corrige automáticamente un esquema ajeno con tipos incompatibles.

Consultar la bitácora en la base:

```sql
SELECT archivo_migracion, revision_migracion, sha256_archivo,
       primera_ejecucion, ultima_ejecucion, descripcion_migracion
FROM sistema_migraciones;
```

## Issues corregidos

1. **AJAX sin lista de acciones:** se limitaron clases y métodos a los usados por
   KLOSET, con POST, sesión, ACL y CSRF. Los errores devuelven JSON sin detalles SQL.
2. **Permisos antiguos en sesión:** usuario, estado, rol y secciones se consultan
   de nuevo en cada petición. Una revocación o desactivación tiene efecto inmediato.
   Las cuentas sin secciones no entran al panel y el login renueva la sesión.
3. **JavaScript/CSS en caché:** se agrega una versión derivada de su contenido;
   el navegador ya carga el código actualizado después de cambios.
4. **Filtros con filas todavía visibles:** `[hidden]` prevalece sobre el grid.
   Búsqueda y filtro por categoría coinciden con el contador visible.
5. **Borrado de producto con ventas/bolsas:** primero se comprueban dependencias
   y se elimina en transacción; las imágenes se conservan si no puede eliminarse.
6. **Variante usada por clientes:** no se permite cambiar producto, talla o corte
   de una variante presente en pedidos, bolsas o prendas guardadas.
7. **Entradas inválidas:** producto/categoría/rol inexistentes, SKU duplicado,
   stock negativo o no entero, tamaños de campos, precio no numérico e imágenes
   falsas reciben errores controlados. Los registros inexistentes no se actualizan
   aparentando éxito.
8. **HTML de datos en detalles:** se escapan valores de clientes, usuarios, pagos,
   permisos y URLs de imágenes; pedidos y notificaciones también conservan escape.
9. **Estados de pedido:** no retroceden, los cerrados no se reabren, cancelar
   devuelve stock una sola vez. Cambiar sin enviar metadata conserva el seguimiento;
   repetir los mismos datos no duplica historial/avisos.
10. **Roles/configuración:** roles base protegidos, secciones válidas y protección
    del acceso propio; configuración guardada en transacción, con IDs válidos y
    umbral de stock entero positivo.

## Pruebas y resultados

| Área | Resultado |
| --- | --- |
| Dashboard y diez módulos visibles | Consultas PHP/MySQL y navegación correctas |
| Categorías / productos / inventario | Crear, consultar, editar y borrar registros QA |
| Imágenes | Subir archivo real, rechazar archivo falso, retirar DB/disco |
| Pedidos / pagos / clientes | Pedido desde API, detalles, estados y seguimiento |
| Cancelación administrativa | Stock restituido una vez, variante vendida protegida |
| Notificaciones / contacto | Lectura persistida, contacto genera aviso; lectura global probada solo sin avisos originales pendientes |
| Usuarios / roles / ACL | Altas, asignación, rol en uso protegido, revocación inmediata |
| Configuración | Cambio persistido, ID inexistente rechazado |
| Sesión / CSRF | Sin sesión 401, sin token 403, métodos legacy 400, GET 405 |
| Tienda | Regresión de cuenta, direcciones, bolsa, checkout y cancelación pasada |
| Navegador | Todos los módulos abiertos, ficha cargada por AJAX, buscador y categoría con una fila visible, sin errores de consola |
| Sintaxis / diff | PHP sin errores; `git diff --check` correcto |

Pruebas reproducibles:

```powershell
& 'C:\xampp\php\php.exe' tests/admin_flows.php
& 'C:\xampp\php\php.exe' tests/customer_flows.php
```

Evidencia de las ejecuciones finales:
`docs/pruebas-panel-2026-09-27.txt` y `docs/pruebas-tienda-2026-09-27.txt`.
Los scripts crean y retiran únicamente sus registros QA. Se conservaron los
clientes, pedidos, catálogo y permisos originales de la instalación.

## Alcance

No quedaron fallos abiertos en los flujos locales comprobados. El pago continúa
como simulador; correo/SMS y courier no están integrados. Los documentos legales
siguen como borradores visibles. No se efectuó un despliegue a producción.
