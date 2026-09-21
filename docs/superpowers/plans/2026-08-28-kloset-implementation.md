# Kloset Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or executing-plans.

**Goal:** Scaffold completo del proyecto Kloset (PHP admin + API JWT + React) listo para Sprint 1.

**Architecture:** Monorepo PHP framework + SPA React; BD MySQL normalizada.

**Tech Stack:** PHP 8, MySQL, React, Vite, Tailwind, JWT, Three.js/R3F.

---

### Task 0: Scaffold base ✅

- [x] `global.mdc`, docs spec/plan
- [x] Copiar kernel PHP desde `nuevo_proyecto - copia`
- [x] Config local XAMPP
- [x] Adaptar modelos a `sistema_*`
- [x] Import BD + seed
- [x] API JWT base
- [x] Frontend Vite + Tailwind + tokens
- [x] Tema admin kloset (login + layout + dashboard)

### Task 1: Sprint 1 — Admin catálogo ✅

- [x] CRUD categorías (`Categorias.php`, tpl, JS)
- [x] CRUD productos + imágenes (`Productos.php`, galería, filtros)
- [x] Menú ACL alineado con seed (`Acl.php` + guard de sesión en `dw-panel/ajax.php`)

### Task 1b: Implementación del diseño de Claude Design ✅

Proyecto `08ff5c48-369d-471e-9b1a-eda07d6e3852`.

- [x] `Kloset Admin.dc.html` → panel PHP: shell (aside con módulos y contadores,
      cabecera con buscador y acción primaria, pie), panel general con KPIs,
      patrón tabla + panel de detalle, editor de `sistema_configuraciones`,
      login y modo claro/noche persistido
- [x] `Kloset.dc.html` → tienda React: catálogo, producto, medidas, resultado,
      avatar comparado, bolsa, pago simulado (aprueba / rechaza / 3-D Secure),
      pedidos, acceso y cuenta; tabs móviles y modo claro/noche
- [x] Corrección: Apache no propagaba `Authorization`, así que `/auth/me`
      rechazaba tokens válidos y el SPA perdía la sesión al recargar

### Task 2: Sprint 2 — Variantes y 3D admin

- [ ] `productos_variantes` CRUD (sección Inventario del diseño)
- [ ] Subida GLB

### Task 3: Sprint 3 — API + frontend catálogo

- [x] Endpoints productos, auth registro/login cliente
- [x] Páginas React catálogo y detalle
- [ ] Filtros de catálogo y paginación en la API

### Task 4–6: Medidas, avatar, carrito, pedidos, reportes

- [x] Medidas, bolsa y pedidos persistidos contra la API
      (`PerfilesCorporales`, `Carritos`, `Pedidos` en `app/model/`, rutas en
      `api/index.php`): guardar/leer perfil corporal, agregar/quitar del
      carrito con validación de stock real, y checkout que crea
      `pedidos`/`pedidos_items`/`pagos`/`historial_estados_pedidos`,
      descuenta `productos_variantes.stock_variante` y vacía el carrito
      (`carritos.id_usuario_sistema` es `UNIQUE`: una sola fila de carrito
      por usuario, se vacía en vez de "cerrarse")
- [x] Columnas `marca_pago`/`ultimos_digitos_pago` en `pagos`
      (`database/migrations/2026-09-21_pagos_marca_tarjeta.sql`) para que el
      simulador de pago (aprueba/rechaza/3-D Secure, sin cambios) muestre la
      tarjeta en la confirmación e historial
- [x] Checkout con captura de dirección de envío (`direcciones_envio_clientes`);
      antes no existía ningún campo de dirección en el SPA
- [x] Seed de catálogo (`database/seeds/2026-09-21_catalogo_demo.sql`): 8
      prendas × 15 variantes (3 cortes × 5 tallas) — `kloset_bd` no tenía
      ningún producto cargado
- [ ] Avatar: sustituir la silueta paramétrica por el modelo GLB con R3F
      (`avatares_3d` sigue sin usarse; requiere pipeline de modelos 3D)
- [ ] Reportes admin (`dw-panel` → `case 'reportes'` sigue siendo un stub)

Ver plan de trabajo original (`plan_trabajo_sistema_ropa_deportiva.md`).
