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
  children,
  className = '',
  style,
}: {
  src?: string | null;
  alt?: string;
  etiqueta?: string;
  ratio?: string;
  fondo?: string;
  children?: ReactNode;
  className?: string;
  style?: CSSProperties;
}) {
  return (
    <div
      className={`relative flex items-end overflow-hidden ${className}`}
      style={{ aspectRatio: ratio, background: fondo, ...style }}
    >
      {src ? (
        <img src={src} alt={alt ?? ''} className="absolute inset-0 h-full w-full object-cover" />
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
