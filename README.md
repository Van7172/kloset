# Kloset

Sistema de venta de ropa deportiva con recomendación de tallas y avatar 3D.

## Requisitos

- XAMPP (Apache + MySQL + PHP 8+)
- Node.js 18+

## Instalación

### 1. Base de datos

Inicia MySQL en XAMPP y ejecuta:

```bat
database\import.bat
```

Credenciales admin por defecto: `admin@kloset.local` / `password`

### 2. Backend PHP

- URL raíz: http://localhost/kloset/
- Panel admin: http://localhost/kloset/dw-panel/
- API: http://localhost/kloset/api/v1/health

Config local: `app/inc.config.php`

### 3. Frontend React

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

Rutas: catálogo, detalle de producto, medidas, resultado de talla, avatar,
bolsa, pago, pedidos, acceso y cuenta. El catálogo, el detalle y la
autenticación consumen la API; medidas, bolsa y pedidos viven en el cliente
hasta que existan sus endpoints (Sprints 4-6). El pago es el simulador del
diseño: `4242 4242 4242 4242` aprueba, `4000 0000 0000 0002` rechaza y
`4000 0000 0000 3220` pide 3-D Secure.

### Panel

Aside con módulos y contadores, cabecera con buscador y acción primaria, y tres
patrones de contenido: panel general con KPIs, tabla con panel de detalle
(categorías, productos, usuarios) y editor llave-valor para la configuración.

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
   ```

5. Importa la base de datos y cambia la contraseña de `admin@kloset.local`.

Comprobaciones tras publicar: `https://kloset.shop/api/v1/health` responde
`ok`; una ficha de producto recargada no da 404; y las imágenes del catálogo
salen con URL `https://kloset.shop/app/public_root/imgs/productos/…`. Si la
sesión del cliente se pierde al recargar, el hosting está ignorando el
`.htaccess` de `api/` (hace falta `AllowOverride All`).

Falta por hacer al publicar: regenerar `frontend/public/sitemap.xml` con una
entrada `/producto/:url` por prenda.

## Documentación

- `docs/superpowers/specs/2026-08-28-kloset-design.md`
- `docs/superpowers/plans/2026-08-28-kloset-implementation.md`
