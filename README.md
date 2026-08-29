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

## Documentación

- `docs/superpowers/specs/2026-08-28-kloset-design.md`
- `docs/superpowers/plans/2026-08-28-kloset-implementation.md`
