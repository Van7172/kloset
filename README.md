# Kloset

Sistema de venta de ropa deportiva con recomendación de tallas y vista de silueta paramétrica.

## Requisitos

- XAMPP (Apache + MySQL + PHP 8+)
- Node.js 18+

## Instalación

### 1. Base de datos

Inicia MySQL en XAMPP con la base `kloset_bd` ya configurada. El repositorio no
incluye un volcado completo del esquema para crearla desde cero. En la base
existente aplica `database/migrations/2026-09-27_contactos.sql` y luego
`database/seeds/2026-09-27_imagenes_catalogo.sql` para asociar las fotografías
al catálogo. Si las ocho prendas demo aún no existen, carga antes
`database/seeds/2026-09-21_catalogo_demo.sql`. Copia también
`app/public_root/imgs/productos/` al servidor.

Para preparar una base existente usa el consolidado reejecutable:

```powershell
& 'C:\xampp\php\php.exe' database/preparar_entrega.php
```

Incluye los flujos de cuenta, direcciones con cobertura de Lima y Callao,
seguimiento, pagos y contactos. `database/migrations/2026-09-28_detalles_direccion.sql`
queda disponible como migración independiente registrada en la bitácora.

Si la base demo fue cargada antes de esta revisión, ejecuta una vez
`database/seeds/2026-09-28_restock_catalogo_demo.sql`: repone a 25 unidades
las seis variantes iniciales de `Short Split 5"` y deja todo el catálogo demo
comprable.
Los códigos de variantes nuevos son únicos y combinan letras y números. Para
convertir los SKU del catálogo demo anterior, aplica
`database/migrations/2026-10-05_codigos_alfanumericos_variantes.sql` antes de
editarlos en el panel. La migración conserva los IDs y los pedidos existentes.
En una base local vacía, carga `database/seeds/2026-10-05_base_demo_local.sql`,
el seed de catálogo y el de imágenes en ese orden. Luego ejecuta por CLI
`database/seeds/2026-10-05_datos_demo_local.php` indicando una ruta nueva para
las credenciales fuera del directorio público; genera contraseñas aleatorias.
Si ya se aplicó `database/migrations/2026-09-28_dni_cliente.sql`, ejecuta
`database/migrations/2026-09-28_eliminar_dni_cliente.sql` para quitar la columna.
Para habilitar la recuperación de contraseña y la verificación del registro, aplica también
`database/migrations/2026-09-28_recuperacion_contrasena.sql`.
Para guardar tipo y departamento de las direcciones, aplica
`database/migrations/2026-09-28_detalles_direccion.sql`.

Credenciales admin por defecto: `admin@kloset.local` / `password`

### 2. Backend PHP

- URL raíz: http://localhost/kloset/ (sirve la compilación React)
- Panel admin: http://localhost/kloset/dw-panel/
- API: http://localhost/kloset/api/v1/health

Config local: `app/inc.config.php`

### 3. Frontend React

Para la entrega local en XAMPP:

```bash
cd frontend
npm install
npm run build:local
```

Abre http://localhost/kloset/. Tras modificar el frontend, vuelve a ejecutar
`npm run build:local`. El panel está en http://localhost/kloset/dw-panel/.

Para desarrollo con recarga automática:

```bash
cd frontend
npm install
npm run dev
```

Tienda (dev): http://localhost:5173/

## Estructura

- `app/` — Modelos y utilidades PHP
- `dw-panel/` — Panel administrativo (sesión PHP)
- `api/` — REST JWT para el SPA
- `frontend/` — Tienda React + Tailwind
- `.cursor/rules/global.mdc` — Reglas del proyecto

## Diseño

Ambas interfaces son una implementación del proyecto de Claude Design
`08ff5c48-369d-471e-9b1a-eda07d6e3852`:

- `Kloset.dc.html` → tienda React (`frontend/`)
- `Kloset Admin.dc.html` → panel PHP (`app/view/back_end/kloset/`)

Tokens compartidos (`--paper`, `--ink`, `--red`, `--soft`, `--rule`…) con modo
claro y noche; el tema se guarda en `localStorage` bajo la llave `kloset-theme`.
Tipografías: Newsreader (títulos), Archivo y Archivo Narrow (interfaz).

### Tienda

Rutas: portada y catálogo, detalle de producto, medidas, resultado de talla, avatar,
bolsa, pago, pedidos, acceso y cuenta. Catálogo, autenticación, medidas
(`perfiles_corporales`), bolsa (`carritos`/`carritos_items`) y pedidos
(`pedidos`/`pedidos_items`/`pagos`) consumen la API contra `kloset_bd`; solo
el corte preferido y el perfil de un visitante sin cuenta se quedan en
`localStorage`. Las consultas del formulario de contacto se guardan en
`contactos` y aparecen en las notificaciones del panel. El pago es el
simulador del diseño: `4242 4242 4242 4242`
aprueba, `4000 0000 0000 0002` rechaza y `4000 0000 0000 3220` pide 3-D
Secure; al aprobarse, el checkout crea el pedido real (con la dirección de
envío del formulario), descuenta stock de `productos_variantes` y vacía la
bolsa. El avatar sigue siendo la silueta paramétrica del diseño: el modelo
GLB con React Three Fiber queda para el Sprint 5.

### Panel

Aside con módulos y contadores, cabecera con buscador y acción primaria, y tres
patrones de contenido: panel general con KPIs, tabla con panel de detalle
(categorías, productos, usuarios) y editor llave-valor para la configuración.

### Flujos integrados desde las referencias de cuenta

- `/cuenta`: resumen, favoritos, bolsa, pedidos, direcciones y datos con
  contadores reales. El correo de la cuenta no se edita; no se solicita DNI.
- `/producto/:url`: guardar/quitar favorito autenticado.
- `/bolsa`: cantidades con comprobación de stock, quitar y guardar para después.
- `/pago`: seleccionar una dirección guardada o añadir otra, con distrito validado
  dentro de Lima Metropolitana o Callao, y simular el pago.
- `/pedidos?nuevo=1`: confirmación del pedido persistido; `/pedidos/:id`:
  detalle privado, imágenes, resumen y estados registrados.
- El panel permite registrar preparación, envío, reparto, entrega, transportista,
  número de seguimiento y fecha estimada. Cancelar devuelve stock y cierra el
  pedido; no emite reembolsos reales, pues los pagos siguen siendo simulados.
- Las medidas se pueden descargar en JSON o borrar; la contraseña se puede
  cambiar verificando la actual. Los avisos de pedido respetan la preferencia
  guardada y aparecen en la cuenta. El envío de campañas/correos no está conectado.
- La recuperación y el registro verifican el correo con códigos de un solo uso
  de 6 dígitos, válidos durante 10 minutos y almacenados en `codigos_verificacion`.
  Ambos flujos requieren SMTP configurado.
- Las contraseñas requieren 8–128 caracteres, mayúscula, minúscula, número y
  carácter especial; registro y recuperación también exigen confirmación. El
  restablecimiento no permite reutilizar la contraseña actual.

## Publicar en kloset.shop

La URL pública ya no está cableada: `app/inc.config.php` la deduce de la
petición, y el dominio del SEO sale de `VITE_SITE_URL`
(`frontend/.env.production`). El despliegue asume **tienda y API en el mismo
dominio**, con el document root apuntando a la carpeta del proyecto.

1. Compila la tienda: `cd frontend && npm run build`.
2. Sube el proyecto al hosting y **vuelca el contenido de `frontend/dist/`**
   (no la carpeta) en la raíz, junto a `api/`, `dw-panel/` y `app/`.
3. Sustituye el `.htaccess` de la raíz por `deploy/.htaccess`: fuerza HTTPS,
   redirige `www` al dominio desnudo, manda `/api`, `/dw-panel` y `/app` a PHP
   y todo lo demás a `index.html` para que el enrutado del SPA funcione al
   recargar o entrar directo a `/producto/loquesea`.
4. Define las variables de entorno del hosting (o `SetEnv` en el `.htaccess`):

   ```
   KLOSET_JWT_SECRET   cadena larga y aleatoria — obligatorio
   KLOSET_DB_HOST      KLOSET_DB_NAME  KLOSET_DB_USER  KLOSET_DB_PASS  KLOSET_DB_PORT
   KLOSET_URL          solo si la detección automática falla (con barra final)
  KLOSET_SMTP_HOST    KLOSET_SMTP_USER  KLOSET_SMTP_PASS
  KLOSET_SMTP_PORT    587 por defecto  |  KLOSET_SMTP_SECURITY: tls, ssl o none
   ```

5. Importa la base de datos y cambia la contraseña de `admin@kloset.local`.

Comprobaciones tras publicar: `https://kloset.shop/api/v1/health` responde
`ok`; una ficha de producto recargada no da 404; y las imágenes del catálogo
salen con URL `https://kloset.shop/app/public_root/imgs/productos/…`. Si la
sesión del cliente se pierde al recargar, el hosting está ignorando el
`.htaccess` de `api/` (hace falta `AllowOverride All`).

Antes de publicar: sustituir los borradores legales por documentos revisados
con la razón social, RUC, domicilio y canales oficiales; conectar una pasarela
de pago real; cambiar la contraseña de administración; y regenerar
`frontend/public/sitemap.xml` con una entrada `/producto/:url` por prenda.

## Documentación

- `docs/bitacora-panel-2026-09-27.md` — revisión administrativa, SQL ejecutado y pruebas
- `database/migrations/2026-09-27_entrega_local_consolidada.sql` — preparación reejecutable del esquema existente
- `database/preparar_entrega.php` — ejecutor CLI con registro SHA-256 en `sistema_migraciones`
- `docs/revision-flujos-cuenta.md` — encaje de las 12 referencias y revisión funcional
- `docs/superpowers/specs/2026-08-28-kloset-design.md`
- `docs/superpowers/plans/2026-08-28-kloset-implementation.md`
