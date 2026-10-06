import { Seo } from '../components/Seo';
import { useEffect, useRef, useState, type PointerEvent, type WheelEvent } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { api, type ApiDetalle } from '../services/api';
import { useKloset } from '../store/KlosetContext';
import {
  APRIETE_FIGURA,
  RANGOS_MEDIDAS,
  TALLAS,
  VEREDICTO_CORTE,
  tallaRecomendada,
  type Corte,
  type Medidas,
  type Talla,
} from '../lib/fit';

const GUIA_TALLAS: Record<Talla, { pecho: string; cintura: string; cadera: string }> = {
  XS: { pecho: '82–87 cm', cintura: '68–73 cm', cadera: '84–89 cm' },
  S: { pecho: '88–93 cm', cintura: '74–79 cm', cadera: '90–95 cm' },
  M: { pecho: '94–99 cm', cintura: '80–85 cm', cadera: '96–101 cm' },
  L: { pecho: '100–105 cm', cintura: '86–91 cm', cadera: '102–107 cm' },
  XL: { pecho: '106–112 cm', cintura: '92–98 cm', cadera: '108–114 cm' },
};

/**
 * Escenario del avatar. Renderiza la silueta paramétrica del diseño; el modelo
 * GLB con React Three Fiber entra en el Sprint 5 y sustituirá a esta figura.
 */
function Escenario({
  corte,
  talla,
  rot,
  zoom,
  medidas,
  imagenPrenda,
  onGirar,
  onAcercar,
}: {
  corte: Corte;
  talla: Talla;
  rot: number;
  zoom: number;
  medidas: Medidas;
  imagenPrenda: string | null;
  onGirar: (rot: number) => void;
  onAcercar: (zoom: number) => void;
}) {
  const arrastre = useRef<{ x: number; rot: number } | null>(null);
  const anchoTorso = Math.min(1.2, Math.max(0.86, medidas.chest / 98));
  const iniciarGiro = (event: PointerEvent<HTMLDivElement>) => {
    arrastre.current = { x: event.clientX, rot };
    event.currentTarget.setPointerCapture(event.pointerId);
  };
  const moverGiro = (event: PointerEvent<HTMLDivElement>) => {
    if (!arrastre.current) return;
    const angulo = arrastre.current.rot + (event.clientX - arrastre.current.x) * 0.8;
    onGirar(Math.max(-180, Math.min(180, Math.round(angulo))));
  };
  const terminarGiro = () => { arrastre.current = null; };
  const controlarZoom = (event: WheelEvent<HTMLDivElement>) => {
    event.preventDefault();
    onAcercar(Math.max(80, Math.min(140, zoom + (event.deltaY < 0 ? 5 : -5))));
  };

  return (
    <div>
      <div
        className="relative flex h-[330px] items-center justify-center overflow-hidden border border-rule bg-surface sm:h-[390px]"
        style={{ perspective: '900px', touchAction: 'none', cursor: arrastre.current ? 'grabbing' : 'grab' }}
        onPointerDown={iniciarGiro}
        onPointerMove={moverGiro}
        onPointerUp={terminarGiro}
        onPointerCancel={terminarGiro}
        onWheel={controlarZoom}
        aria-label="Avatar para previsualizar la prenda"
      >
        <div className="absolute inset-[7%_15%] border border-rule" />
        <div className="absolute bottom-[8%] left-1/2 h-px w-[58%] -translate-x-1/2 bg-rule" />
        <div
          className="relative h-[92%] w-[230px] transition-transform duration-200"
          style={{ transform: `rotateY(${rot}deg) scale(${zoom / 100})` }}
        >
          <svg viewBox="0 0 280 500" role="img" aria-label={`Avatar con corte ${corte} y talla ${talla}`} className="h-full w-full overflow-visible">
            <defs>
              <linearGradient id="piel-avatar" x1="0" x2="1" y1="0" y2="1">
                <stop offset="0" stopColor="#dedfe0" />
                <stop offset="1" stopColor="#9299a3" />
              </linearGradient>
              <linearGradient id="ropa-avatar" x1="0" x2="1" y1="0" y2="1">
                <stop offset="0" stopColor="#343536" />
                <stop offset="1" stopColor="#101112" />
              </linearGradient>
              <clipPath id="camiseta-avatar">
                <path d="M107 126 82 137 58 165l22 22 16-17-7 74q51 19 102 0l-7-74 16 17 22-22-24-28-25-11-15 13h-36z" />
              </clipPath>
            </defs>
            <ellipse cx="140" cy="476" rx="82" ry="8" fill="#d9d9d7" />
            <g fill="url(#piel-avatar)" stroke="#9da0a4" strokeWidth="1.2">
              <circle cx="140" cy="53" r="24" />
              <path d="M132 77h16v28h-16z" />
              <path d="M88 143q-12 6-17 29l-13 91q-2 15 11 16 9 1 12-13l20-71 8-43z" />
              <path d="M192 143q12 6 17 29l13 91q2 15-11 16-9 1-12-13l-20-71-8-43z" />
              <path d="M108 266q-4 52-2 103l-8 88q-1 14 14 14 10 0 13-13l15-77 15 77q3 13 13 13 15 0 14-14l-8-88q2-51-2-103z" />
              <path d="M98 453q-9 8-14 14-6 8 7 10l31-3 3-17zM182 453l-27 4 3 17 31 3q13-2 7-10-5-6-14-14z" />
            </g>
            <g transform={`translate(140 0) scale(${anchoTorso * APRIETE_FIGURA[corte]},1) translate(-140 0)`}>
              <path d="M107 126 82 137 58 165l22 22 16-17-7 74q51 19 102 0l-7-74 16 17 22-22-24-28-25-11-15 13h-36z" fill="url(#ropa-avatar)" />
              {imagenPrenda && <image href={imagenPrenda} x="55" y="125" width="170" height="128" preserveAspectRatio="xMidYMid slice" clipPath="url(#camiseta-avatar)" style={{ mixBlendMode: 'multiply' }} />}
              <path d="M107 126q33 14 66 0" fill="none" stroke="#5b5d5f" strokeWidth="2" />
            </g>
            <path d="M126 100q14 9 28 0" fill="none" stroke="#858b93" strokeWidth="2" />
          </svg>
        </div>
        <span className="absolute bottom-2 left-2 right-2 text-center text-[11px] text-soft">Arrastra para girar · usa la rueda o el control para acercar</span>
      </div>
      <div className="mt-3 flex items-center gap-3 border-b border-rule pb-3">
        <label htmlFor="avatar-zoom" className="shrink-0 font-narrow text-[11px] font-semibold uppercase tracking-[0.1em] text-soft">Zoom</label>
        <input id="avatar-zoom" type="range" min="80" max="140" value={zoom} onChange={(event) => onAcercar(Number(event.target.value))} aria-label="Acercar avatar" className="h-6 min-w-0 flex-1" />
        <span className="w-10 text-right text-xs tabular-nums text-soft">{zoom}%</span>
      </div>
      <label className="mt-3 flex items-center gap-3 text-xs text-soft">
        <span className="shrink-0 font-narrow font-semibold uppercase tracking-[0.1em]">Girar avatar</span>
        <input type="range" min={-180} max={180} value={rot} onChange={(event) => onGirar(Number(event.target.value))} aria-label="Girar avatar" className="h-6 flex-1" />
        <span className="w-9 text-right tabular-nums">{rot}°</span>
      </label>
    </div>
  );
}

export function AvatarPage() {
  const navigate = useNavigate();
  const [params] = useSearchParams();
  const url = params.get('producto');
  const { medidas, corte } = useKloset();

  const [detalle, setDetalle] = useState<ApiDetalle | null>(null);
  const [medidasPrueba, setMedidasPrueba] = useState<Medidas>(() => ({ ...medidas }));
  const [rot, setRot] = useState(0);
  const [zoom, setZoom] = useState(100);
  const [tallaElegida, setTallaElegida] = useState<Talla>(() => tallaRecomendada(medidas, corte));
  const [tallaCalculada, setTallaCalculada] = useState<Talla>(() => tallaRecomendada(medidas, corte));

  useEffect(() => {
    if (!url) return;
    api
      .producto(url)
      .then((res) => {
        if (res.status === 'success') setDetalle(res);
      })
      .catch(() => undefined);
  }, [url]);

  const talla = tallaCalculada;
  const recomendada = tallaRecomendada(medidasPrueba, corte);
  const producto = detalle?.producto;

  const calcularTalla = () => {
    setTallaCalculada(recomendada);
    setTallaElegida(recomendada);
  };

  return (
    <div className="kl-rise mx-auto max-w-[1240px]">
      <Seo title="Pruébalo en tu avatar" description="Prueba la prenda sobre tu silueta en los tres cortes —Slim, Regular y Oversize— y comprueba cómo cae antes de pagar." path="/avatar" />
      <div className="mb-6 flex items-center gap-[14px] border-b border-ink pb-[14px] pt-[2px]">
        <button
          type="button"
          onClick={() => navigate(url ? `/producto/${url}` : '/catalogo')}
          className="min-h-[44px] cursor-pointer border-none bg-transparent p-0 font-narrow text-[13px] uppercase tracking-[0.08em] text-soft"
        >
          ← {producto ? producto.nombre_producto : 'Catálogo'}
        </button>
        <span className="flex-1" />
      </div>

      <div className="grid gap-8 lg:grid-cols-[1.05fr_0.95fr] lg:gap-10">
        <section>
          <p className="mb-2 font-narrow text-xs font-semibold uppercase tracking-[0.14em] text-soft">Asistente de talla</p>
          <h1 className="mb-5 font-display text-[30px] leading-tight sm:text-[34px]">Tus medidas corporales</h1>
          <div className="space-y-1">
            {RANGOS_MEDIDAS.map((campo) => (
              <label key={campo.key} className="block border-b border-rule py-3">
                <span className="flex items-baseline justify-between gap-3">
                  <span className="text-sm font-semibold">{campo.label}</span>
                  <span className="flex items-baseline gap-1 text-sm"><strong className="text-base">{medidasPrueba[campo.key]}</strong><span className="text-soft">cm</span></span>
                </span>
                <input
                  type="range"
                  min={campo.min}
                  max={campo.max}
                  value={medidasPrueba[campo.key]}
                  onChange={(event) => setMedidasPrueba({ ...medidasPrueba, [campo.key]: Number(event.target.value) })}
                  aria-label={`${campo.label}, ${medidasPrueba[campo.key]} centímetros`}
                  className="mt-1 h-7 w-full cursor-pointer"
                />
              </label>
            ))}
          </div>
          <button type="button" onClick={calcularTalla} className="mt-5 min-h-[50px] w-full cursor-pointer border-none bg-ink font-narrow text-sm font-semibold uppercase tracking-[0.06em] text-paper hover:bg-red">Calcular mi talla</button>

          <div className="mt-4 border border-rule bg-surface p-4">
            <p className="text-sm text-soft">Talla recomendada: <strong className="ml-1 text-lg text-ink">{talla}</strong></p>
            <p className="mt-1 text-sm text-body">Tus medidas coinciden con esta talla en la guía de la marca.</p>
          </div>

          <fieldset className="mt-5 border-0 p-0">
            <legend className="mb-3 text-sm font-semibold">Probar otra talla</legend>
            <div className="grid max-w-[420px] grid-cols-5 gap-2">
              {TALLAS.map((opcion) => <button
                key={opcion}
                type="button"
                aria-pressed={tallaElegida === opcion}
                onClick={() => setTallaElegida(opcion)}
                className="min-h-12 cursor-pointer border font-narrow text-sm font-semibold"
                style={{ background: tallaElegida === opcion ? 'var(--ink)' : 'transparent', color: tallaElegida === opcion ? 'var(--paper)' : 'var(--ink)', borderColor: 'var(--rule)' }}
              >{opcion}</button>)}
            </div>
          </fieldset>
        </section>

        <section className="min-w-0">
          <Escenario corte={corte} talla={tallaElegida} rot={rot} zoom={zoom} medidas={medidasPrueba} imagenPrenda={detalle?.imagenes[0]?.url_imagen ?? null} onGirar={setRot} onAcercar={setZoom} />
          <div className="mt-3 grid grid-cols-3 border border-rule text-center text-xs text-body">
            <div className="border-r border-rule px-2 py-3">Talla <strong className="text-ink">{tallaElegida}</strong></div>
            <div className="border-r border-rule px-2 py-3">Corte <strong className="text-ink">{corte}</strong></div>
            <div className="px-2 py-3">Ajuste <strong className="text-ink">{VEREDICTO_CORTE[corte]}</strong></div>
          </div>

          <div className="mt-5 overflow-x-auto border border-rule">
            <table className="w-full min-w-[440px] border-collapse text-left text-xs">
              <caption className="border-b border-rule px-3 py-3 text-left font-narrow font-semibold uppercase tracking-[0.1em] text-soft">Guía de tallas (cm)</caption>
              <thead className="bg-surface text-ink">
                <tr><th scope="col" className="px-3 py-3">Talla</th><th scope="col" className="px-3 py-3">Pecho</th><th scope="col" className="px-3 py-3">Cintura</th><th scope="col" className="px-3 py-3">Cadera</th></tr>
              </thead>
              <tbody>
                {TALLAS.map((opcion) => <tr key={opcion} className="border-t border-rule" style={{ background: tallaElegida === opcion ? 'var(--surface)' : 'transparent', fontWeight: tallaElegida === opcion ? 700 : 400 }}>
                  <th scope="row" className="px-3 py-3">{opcion}</th><td className="px-3 py-3">{GUIA_TALLAS[opcion].pecho}</td><td className="px-3 py-3">{GUIA_TALLAS[opcion].cintura}</td><td className="px-3 py-3">{GUIA_TALLAS[opcion].cadera}</td>
                </tr>)}
              </tbody>
            </table>
          </div>

        </section>
      </div>
    </div>
  );
}
