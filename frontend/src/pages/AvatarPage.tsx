import { useEffect, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { api, type ApiDetalle } from '../services/api';
import { useKloset, type ItemBolsa } from '../store/KlosetContext';
import {
  ANCHO_FIGURA,
  APRIETE_FIGURA,
  COPY_CORTE,
  CORTES,
  VEREDICTO_CORTE,
  confianza,
  money,
  tallaRecomendada,
  type Corte,
} from '../lib/fit';

const VISTAS = [
  { label: 'Frente', v: 0 },
  { label: '3/4', v: 45 },
  { label: 'Perfil', v: 90 },
  { label: 'Espalda', v: 180 },
];

/**
 * Escenario del avatar. Renderiza la silueta paramétrica del diseño; el modelo
 * GLB con React Three Fiber entra en el Sprint 5 y sustituirá a esta figura.
 */
function Escenario({
  corte,
  talla,
  rot,
  comparando,
}: {
  corte: Corte;
  talla: string;
  rot: number;
  comparando: boolean;
}) {
  return (
    <div
      className="relative flex min-h-[420px] items-end justify-center overflow-hidden border border-rule lg:min-h-[560px]"
      style={{ background: 'linear-gradient(180deg,var(--surface),var(--surface-2))' }}
    >
      <div className="kl-stripes absolute inset-0" />
      <div
        className="absolute inset-x-0 top-0 flex items-center justify-between border-b border-rule px-[14px] py-3"
        style={{ background: 'var(--stage-veil)' }}
      >
        <span className="font-display text-[17px] text-ink">{corte}</span>
        <span className="whitespace-nowrap pl-2 font-narrow text-[11px] uppercase tracking-[0.08em] text-soft">
          {comparando ? `talla ${talla}` : `corte ${corte.toLowerCase()} · talla ${talla}`}
        </span>
      </div>

      <div
        className="relative mb-14 h-[74%] border"
        style={{
          width: ANCHO_FIGURA[corte],
          borderRadius: '46% 46% 30% 30% / 22% 22% 12% 12%',
          background: 'repeating-linear-gradient(105deg, var(--fig-a) 0 2px, var(--fig-b) 2px 9px)',
          borderColor: 'var(--fig-bd)',
          transform: `rotateY(${rot}deg) scaleX(${APRIETE_FIGURA[corte]})`,
          transition: 'transform .5s cubic-bezier(.2,.8,.2,1), width .5s cubic-bezier(.2,.8,.2,1)',
          animation: 'kl-settle .55s ease both',
        }}
      />

      <div className="absolute left-[10%] top-1/2 w-[80%] -translate-y-1/2 text-center font-narrow text-[11px] uppercase leading-[1.5] tracking-[0.04em] text-soft">
        {comparando ? `render 3D · ${rot}°` : `render avatar 3D · talla ${talla} · ${rot}°`}
      </div>

      <div className="absolute inset-x-0 bottom-0 bg-ink px-3 py-[11px] text-center font-narrow text-[11.5px] uppercase tracking-[0.08em] text-paper">
        {VEREDICTO_CORTE[corte]}
      </div>
    </div>
  );
}

export function AvatarPage() {
  const navigate = useNavigate();
  const [params] = useSearchParams();
  const url = params.get('producto');
  const { medidas, corte, setCorte, usuario, añadirABolsa, setIntencion } = useKloset();

  const [detalle, setDetalle] = useState<ApiDetalle | null>(null);
  const [rot, setRot] = useState(0);
  const [comparar, setComparar] = useState(false);

  useEffect(() => {
    if (!url) return;
    api
      .producto(url)
      .then((res) => {
        if (res.status === 'success') setDetalle(res);
      })
      .catch(() => undefined);
  }, [url]);

  const talla = tallaRecomendada(medidas, corte);
  const producto = detalle?.producto;
  const precio = producto ? Number(producto.precio_producto) : 0;
  const cortesEnEscena: Corte[] = comparar ? [...CORTES] : [corte];

  const añadir = () => {
    if (!producto) {
      navigate('/');
      return;
    }
    const item: ItemBolsa = {
      id_producto: producto.id_producto,
      url_producto: producto.url_producto,
      name: producto.nombre_producto,
      size: talla,
      fit: corte,
      price: precio,
      imagen: detalle?.imagenes[0]?.url_imagen ?? null,
    };
    if (!usuario) {
      setIntencion({ then: 'cart', item });
      navigate('/entrar');
      return;
    }
    añadirABolsa(item);
    navigate('/bolsa');
  };

  return (
    <div className="kl-rise">
      <div className="mb-[18px] flex items-center gap-[14px] border-b border-ink pb-[14px] pt-[2px]">
        <button
          type="button"
          onClick={() => navigate(url ? `/producto/${url}` : '/')}
          className="min-h-[44px] cursor-pointer border-none bg-transparent p-0 font-narrow text-[13px] uppercase tracking-[0.08em] text-soft"
        >
          ← {producto ? producto.nombre_producto : 'Catálogo'}
        </button>
        <div className="flex-1" />
        <button
          type="button"
          onClick={() => setComparar((c) => !c)}
          className="hidden min-h-[42px] cursor-pointer border border-ink px-[14px] font-narrow text-[13px] font-semibold uppercase tracking-[0.06em] md:block"
          style={{
            background: comparar ? 'var(--ink)' : 'transparent',
            color: comparar ? 'var(--paper)' : 'var(--ink)',
          }}
        >
          {comparar ? 'Ver un solo corte' : 'Comparar los tres cortes'}
        </button>
      </div>

      <div className="flex flex-col items-stretch gap-8 lg:flex-row lg:gap-14">
        <div
          className="grid flex-[1.15] gap-2"
          style={{ gridTemplateColumns: `repeat(${cortesEnEscena.length}, minmax(0,1fr))` }}
        >
          {cortesEnEscena.map((f) => (
            <Escenario
              key={f}
              corte={f}
              talla={tallaRecomendada(medidas, f)}
              rot={rot}
              comparando={comparar}
            />
          ))}
        </div>

        <div className="flex min-w-0 flex-1 flex-col gap-6">
          <div>
            <div className="border-b border-ink pb-[10px] font-narrow text-xs uppercase tracking-[0.12em] text-soft">
              Corte
            </div>
            <div className="grid grid-cols-3 border border-t-0 border-ink">
              {CORTES.map((f, i) => (
                <button
                  key={f}
                  type="button"
                  onClick={() => setCorte(f)}
                  className="min-h-[46px] cursor-pointer border-none font-narrow text-[13.5px] font-semibold uppercase tracking-[0.06em] transition-colors"
                  style={{
                    background: corte === f ? 'var(--ink)' : 'transparent',
                    color: corte === f ? 'var(--paper)' : 'var(--soft)',
                    borderRight: i === CORTES.length - 1 ? 'none' : '1px solid var(--ink)',
                  }}
                >
                  {f}
                </button>
              ))}
            </div>
            <p className="mt-[14px] text-sm leading-[1.6] text-body">{COPY_CORTE[corte]}</p>
          </div>

          <div>
            <div className="flex items-baseline justify-between border-b border-ink pb-[10px]">
              <span className="font-narrow text-xs uppercase tracking-[0.12em] text-soft">Girar avatar</span>
              <span className="font-display text-[19px]">{rot}°</span>
            </div>
            <input
              type="range"
              min={-180}
              max={180}
              value={rot}
              onChange={(e) => setRot(Number(e.target.value))}
              aria-label="Girar avatar"
              className="mt-[10px] h-[26px] w-full"
            />
            <div className="mt-2 grid grid-cols-4 border border-ink">
              {VISTAS.map((v, i) => (
                <button
                  key={v.label}
                  type="button"
                  onClick={() => setRot(v.v)}
                  className="min-h-[42px] cursor-pointer border-none font-narrow text-[12.5px] uppercase tracking-[0.06em]"
                  style={{
                    background: rot === v.v ? 'var(--ink)' : 'transparent',
                    color: rot === v.v ? 'var(--paper)' : 'var(--soft)',
                    borderRight: i === VISTAS.length - 1 ? 'none' : '1px solid var(--ink)',
                  }}
                >
                  {v.label}
                </button>
              ))}
            </div>
          </div>

          <div className="border-t-2 border-ink pt-4">
            <div className="flex items-baseline justify-between gap-3">
              <div>
                <div className="font-display text-xl">
                  Talla {talla} · {corte}
                </div>
                <div className="mt-1 text-[12.5px] text-soft">
                  {producto ? `${producto.nombre_producto} · ${money(precio)}` : 'Elige una prenda del catálogo'}
                </div>
              </div>
              <span className="whitespace-nowrap font-narrow text-[11.5px] uppercase tracking-[0.08em] text-red">
                Ajuste {confianza(corte)}%
              </span>
            </div>
            <button
              type="button"
              onClick={añadir}
              className="mt-4 min-h-[54px] w-full cursor-pointer border-none bg-ink font-narrow text-[15px] font-semibold uppercase tracking-[0.08em] text-paper hover:bg-red hover:text-[#F2F2F0]"
            >
              {producto ? 'Añadir a la bolsa' : 'Ir al catálogo'}
            </button>
            <button
              type="button"
              onClick={() => navigate('/medidas')}
              className="mt-[6px] min-h-[44px] w-full cursor-pointer border-none bg-transparent text-[13px] text-soft underline"
            >
              Ajustar mis medidas
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
