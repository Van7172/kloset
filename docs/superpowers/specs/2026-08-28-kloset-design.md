# Kloset — Especificación de arquitectura (aprobada)

**Fecha:** 2026-08-28

## Resumen

E-commerce de ropa deportiva con recomendación de talla por medidas y visualización 3D aproximada.

## Stack

| Capa | Tecnología |
|------|------------|
| Admin | PHP + sesión + ACL |
| API cliente | PHP REST + JWT |
| Frontend tienda | React + TS + Tailwind + R3F |
| BD | MySQL 8 (`kloset_bd.sql`) |
| Assets 3D | GLB/GLTF en `app/public_root/modelos3d/` |

## Decisiones

- Monorepo en `c:\xampp\htdocs\kloset\`
- Sin plantillas PHP Travago para el sitio público
- Tema admin `app/view/back_end/kloset/` basado en design system Kloset Admin
- Unidad de inventario: `productos_variantes`

## Sprints

Ver `docs/superpowers/plans/2026-08-28-kloset-implementation.md`
