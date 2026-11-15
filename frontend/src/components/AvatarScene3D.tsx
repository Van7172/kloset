import { Canvas, invalidate } from '@react-three/fiber';
import { Component, type ReactNode, useMemo, useRef, useState } from 'react';
import {
  COLOR_ESTADO,
  ETIQUETA_ESTADO,
  type AnalisisAjuste,
  type Corte,
  type EstadoAjuste,
  type Medidas,
} from '../lib/fit';
import { resolverColorPrendaHex, type CategoriaPrenda3D } from './avatar3d/categoria';
import type { GeneroAvatar } from './avatar3d/cuerpo';
import { Escena, type Vista } from './avatar3d/Escena';

export { detectarCategoria3D, tipoDeCategoria } from './avatar3d/categoria';
export type { CategoriaPrenda3D } from './avatar3d/categoria';
export type { GeneroAvatar } from './avatar3d/cuerpo';

export type AvatarScene3DProps = {
  genero: GeneroAvatar;
  medidas: Medidas;
  corte: Corte;
  /** Ajuste de la talla que se está probando (de `analizarAjuste`). */
  analisis: AnalisisAjuste;
  /** Tallas de diferencia respecto a la recomendada. */
  deltaTalla: number;
  categoria: CategoriaPrenda3D;
  nombreProducto: string;
  colorNombre: string;
  tema: 'light' | 'dark';
};

const VISTAS = [
  { label: 'Frente', grados: 0 },
  { label: '3/4', grados: 35 },
  { label: 'Perfil', grados: 90 },
  { label: 'Espalda', grados: 180 },
];
const VISTA_INICIAL: Vista = { azimut: 24, zoom: 1 };
const ZOOM_MIN = 1;
const ZOOM_MAX = 2.6;
const ESTADOS: EstadoAjuste[] = ['tira', 'ceñido', 'ideal', 'holgado', 'sobra'];

class CanvasErrorBoundary extends Component<{ fallback: ReactNode; children: ReactNode }, { hasError: boolean }> {
  state = { hasError: false };
  static getDerivedStateFromError() {
    return { hasError: true };
  }
  render() {
    return this.state.hasError ? this.props.fallback : this.props.children;
  }
}

const botonBarra =
  'min-h-[36px] cursor-pointer border border-rule bg-paper px-3 font-narrow text-[11px] font-semibold uppercase tracking-[0.08em] text-ink transition-colors hover:border-ink';

export function AvatarScene3D({
  genero,
  medidas,
  corte,
  analisis,
  deltaTalla,
  categoria,
  nombreProducto,
  colorNombre,
  tema,
}: AvatarScene3DProps) {
  const vista = useRef<Vista>({ ...VISTA_INICIAL });
  const arrastre = useRef<{ x: number; azimut: number } | null>(null);
  const [arrastrando, setArrastrando] = useState(false);
  const [mostrarMapa, setMostrarMapa] = useState(false);

  const veredicto = analisis.veredicto;
  const colorHex = useMemo(() => resolverColorPrendaHex(colorNombre, categoria), [colorNombre, categoria]);

  const mover = (cambio: Partial<Vista>) => {
    Object.assign(vista.current, cambio);
    invalidate();
  };
  const girarA = (grados: number) => {
    // Gira por el camino corto desde el ángulo actual.
    const actual = vista.current.azimut;
    const vueltas = Math.round((actual - grados) / 360);
    mover({ azimut: grados + vueltas * 360 });
  };
  const zoom = (factor: number) =>
    mover({ zoom: Math.max(ZOOM_MIN, Math.min(ZOOM_MAX, vista.current.zoom * factor)) });

  const fallback = (
    <div className="flex h-full w-full flex-col items-center justify-center p-6 text-center">
      <p className="font-narrow text-xs uppercase tracking-[0.12em] text-soft">Vista 3D no disponible en este dispositivo</p>
      <p className="mt-2 text-sm text-body">
        {nombreProducto} · Talla {analisis.talla} · {ETIQUETA_ESTADO[veredicto]}
      </p>
    </div>
  );

  return (
    <div>
      <div
        role="application"
        tabIndex={0}
        aria-label={`Maniquí 3D ${genero} de ${medidas.h} cm con ${nombreProducto} en talla ${analisis.talla}, corte ${corte}. Usa las flechas para girar y más o menos para acercar.`}
        onPointerDown={(e) => {
          if ((e.target as HTMLElement).closest('button')) return;
          arrastre.current = { x: e.clientX, azimut: vista.current.azimut };
          e.currentTarget.setPointerCapture(e.pointerId);
          setArrastrando(true);
        }}
        onPointerMove={(e) => {
          if (!arrastre.current) return;
          mover({ azimut: arrastre.current.azimut - (e.clientX - arrastre.current.x) * 0.45 });
        }}
        onPointerUp={() => {
          arrastre.current = null;
          setArrastrando(false);
        }}
        onPointerCancel={() => {
          arrastre.current = null;
          setArrastrando(false);
        }}
        onKeyDown={(e) => {
          if (e.key === 'ArrowLeft') mover({ azimut: vista.current.azimut + 15 });
          else if (e.key === 'ArrowRight') mover({ azimut: vista.current.azimut - 15 });
          else if (e.key === '+' || e.key === '=') zoom(1.25);
          else if (e.key === '-') zoom(0.8);
          else return;
          e.preventDefault();
        }}
        className="relative h-[min(74vh,660px)] min-h-[440px] w-full select-none overflow-hidden border border-rule outline-none focus-visible:border-ink"
        style={{
          cursor: arrastrando ? 'grabbing' : 'grab',
          touchAction: 'pan-y',
          background:
            'radial-gradient(ellipse 90% 70% at 50% 38%, var(--paper) 0%, var(--surface) 62%, var(--surface-2) 100%)',
        }}
      >
        <CanvasErrorBoundary fallback={fallback}>
          <Canvas
            frameloop="demand"
            shadows
            dpr={[1, 1.75]}
            gl={{ antialias: true, alpha: true }}
            camera={{ fov: 30, near: 0.1, far: 30, position: [1.6, 1, 3.6] }}
          >
            <Escena
              genero={genero}
              medidas={medidas}
              corte={corte}
              analisis={analisis}
              deltaTalla={deltaTalla}
              categoria={categoria}
              colorHex={colorHex}
              mostrarMapa={mostrarMapa}
              tema={tema}
              vista={vista}
            />
          </Canvas>
        </CanvasErrorBoundary>

        {/* Talla y veredicto */}
        <div className="pointer-events-none absolute left-3 top-3 z-10 flex items-center gap-2.5 border border-rule bg-paper/95 py-1.5 pl-2 pr-3">
          <span className="flex h-9 min-w-9 items-center justify-center bg-ink px-1.5 font-display text-lg text-paper">
            {analisis.talla}
          </span>
          <span>
            <span className="block font-narrow text-[10px] uppercase leading-none tracking-[0.12em] text-soft">
              Corte {corte}
            </span>
            <span className="mt-1 flex items-center gap-1.5 text-[13px] font-semibold leading-none text-ink">
              <span className="inline-block h-2 w-2 rounded-full" style={{ background: COLOR_ESTADO[veredicto] }} />
              {veredicto === 'ideal' ? 'Ajuste ideal' : ETIQUETA_ESTADO[veredicto]}
            </span>
          </span>
        </div>

        {/* Prenda / mapa de ajuste */}
        <div className="absolute right-3 top-3 z-10 inline-flex border border-ink bg-paper">
          {[
            { activo: !mostrarMapa, texto: 'Prenda', valor: false },
            { activo: mostrarMapa, texto: 'Mapa de ajuste', valor: true },
          ].map((op) => (
            <button
              key={op.texto}
              type="button"
              aria-pressed={op.activo}
              onClick={() => setMostrarMapa(op.valor)}
              className="min-h-[36px] cursor-pointer border-none px-3 font-narrow text-[11px] font-semibold uppercase tracking-[0.08em] transition-colors"
              style={{
                background: op.activo ? 'var(--ink)' : 'transparent',
                color: op.activo ? 'var(--paper)' : 'var(--ink)',
              }}
            >
              {op.texto}
            </button>
          ))}
        </div>

        {mostrarMapa ? (
          <div className="pointer-events-none absolute inset-x-3 bottom-3 z-10 flex flex-wrap items-center justify-center gap-x-4 gap-y-1 border border-rule bg-paper/95 px-3 py-2">
            {ESTADOS.map((estado) => (
              <span key={estado} className="flex items-center gap-1.5 font-narrow text-[11px] uppercase tracking-[0.08em] text-ink">
                <span className="inline-block h-2.5 w-2.5" style={{ background: COLOR_ESTADO[estado] }} />
                {ETIQUETA_ESTADO[estado]}
              </span>
            ))}
          </div>
        ) : (
          <div className="pointer-events-none absolute bottom-3 left-1/2 z-10 -translate-x-1/2 font-narrow text-[11px] uppercase tracking-[0.12em] text-soft">
            Arrastra para girar
          </div>
        )}
      </div>

      {/* Vistas y zoom */}
      <div className="mt-2 flex flex-wrap items-center justify-between gap-2">
        <div className="flex flex-wrap gap-1.5">
          {VISTAS.map((v) => (
            <button key={v.label} type="button" onClick={() => girarA(v.grados)} className={botonBarra}>
              {v.label}
            </button>
          ))}
        </div>
        <div className="flex gap-1.5">
          <button type="button" onClick={() => zoom(0.8)} aria-label="Alejar" className={`${botonBarra} min-w-[38px] text-sm`}>
            −
          </button>
          <button type="button" onClick={() => zoom(1.25)} aria-label="Acercar a la prenda" className={`${botonBarra} min-w-[38px] text-sm`}>
            +
          </button>
          <button type="button" onClick={() => mover({ ...VISTA_INICIAL })} className={botonBarra}>
            Restablecer
          </button>
        </div>
      </div>
    </div>
  );
}
