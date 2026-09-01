import type { CSSProperties, ReactNode } from 'react';

/**
 * Marco de imagen del diseño: muestra la foto del producto cuando existe y,
 * si no, la trama diagonal que el diseño usa como marcador de posición.
 */
export function Marco({
  src,
  alt,
  etiqueta,
  ratio = '4/5',
  fondo = 'var(--surface)',
  prioridad = false,
  children,
  className = '',
  style,
}: {
  src?: string | null;
  alt?: string;
  etiqueta?: string;
  ratio?: string;
  fondo?: string;
  /** true para imágenes visibles al cargar (evita `loading=lazy` y prioriza la descarga). */
  prioridad?: boolean;
  children?: ReactNode;
  className?: string;
  style?: CSSProperties;
}) {
  // width/height explícitos a partir del ratio para que el navegador (y Lighthouse)
  // reserven el hueco y no haya salto de layout.
  const [rw, rh] = ratio.split('/').map((n) => Number(n.trim()) || 1);
  const width = Math.round(rw * 240);
  const height = Math.round(rh * 240);

  return (
    <div
      className={`relative flex items-end overflow-hidden ${className}`}
      style={{ aspectRatio: ratio, background: fondo, ...style }}
    >
      {src ? (
        <img
          src={src}
          alt={alt ?? ''}
          width={width}
          height={height}
          loading={prioridad ? 'eager' : 'lazy'}
          decoding="async"
          fetchPriority={prioridad ? 'high' : 'auto'}
          className="absolute inset-0 h-full w-full object-cover"
        />
      ) : (
        <div className="kl-stripes absolute inset-0" />
      )}
      {etiqueta && !src && (
        <div className="absolute left-3 top-[11px] font-narrow text-[10.5px] uppercase tracking-[0.1em] text-soft">
          {etiqueta}
        </div>
      )}
      {children}
    </div>
  );
}
