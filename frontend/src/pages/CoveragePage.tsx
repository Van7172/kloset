import { useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Seo } from '../components/Seo';
import { ZONAS_ENTREGA } from '../lib/locations';

const STATS = [
  { label: 'Distritos', value: '51', note: 'Lima Metropolitana y Callao con reparto propio' },
  { label: 'Plazo', value: '24–72 h', note: 'Horas hábiles desde la confirmación del pago' },
  { label: 'Envío', value: 'Gratis', note: 'En toda la zona de cobertura, sin mínimo de compra' },
];

export function CoveragePage() {
  const navigate = useNavigate();
  const [q, setQ] = useState('');

  const zonas = useMemo(
    () =>
      ZONAS_ENTREGA.map((z) => ({
        ...z,
        distritos: z.distritos.filter((d) => !q || d.toLowerCase().includes(q.toLowerCase())),
      })).filter((z) => z.distritos.length > 0),
    [q],
  );

  return (
    <div className="kl-rise">
      <Seo
        title="Cobertura de entrega"
        description="Kloset reparte con flota propia en Lima Metropolitana y el Callao, con entrega gratuita en 24 a 72 horas hábiles."
        path="/cobertura"
      />
      <div className="mb-8 border-b border-ink pb-7">
        <div>
          <div className="mb-[18px] inline-block border-b-[3px] border-red pb-[6px] font-narrow text-xs uppercase tracking-[0.14em] text-ink">
            Lima y Callao
          </div>
          <h1 className="mb-[14px] font-display text-[34px] font-normal leading-[1.04] tracking-[-0.025em] md:text-[54px]">
            Cobertura <em className="italic">de entrega.</em>
          </h1>
          <p className="m-0 max-w-[58ch] text-[15.5px] leading-[1.65] text-body">
            Repartimos con nuestra propia flota en Lima Metropolitana y la Provincia Constitucional del Callao. Si tu
            distrito está en la lista, tu pedido llega en 24 a 72 horas hábiles sin coste de envío.
          </p>
        </div>
      </div>

      <div className="mb-10 grid gap-3 md:grid-cols-3">
        {STATS.map((s, index) => (
          <article key={s.label} className={`border border-rule border-t-4 p-5 ${index === 0 ? 'border-t-red bg-red/5' : index === 1 ? 'border-t-ink bg-surface' : 'border-t-soft bg-surface-2'}`}>
            <div className="font-narrow text-[11px] font-semibold uppercase tracking-[0.14em] text-soft">{s.label}</div>
            <div className="my-2 font-display text-[32px] leading-none tracking-[-0.02em]">{s.value}</div>
            <div className="text-[13px] leading-[1.5] text-body">{s.note}</div>
          </article>
        ))}
      </div>

      <section aria-labelledby="distritos-title" className="mb-9">
        <div className="mb-5 flex flex-col gap-4 border-b border-ink pb-4 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <p className="font-narrow text-[11px] font-semibold uppercase tracking-[0.14em] text-red">Consulta la cobertura</p>
            <h2 id="distritos-title" className="mt-1 font-display text-[28px]">¿Llegamos a tu distrito?</h2>
          </div>
          <label className="flex w-full items-center gap-3 border border-rule bg-surface px-3 sm:max-w-[340px]">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" className="h-5 w-5 shrink-0 text-soft" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8" /><path d="m16 16 4.5 4.5" /></svg>
            <input
              value={q}
              onChange={(e) => setQ(e.target.value)}
              placeholder="Busca tu distrito"
              aria-label="Buscar distrito"
              className="min-h-12 min-w-0 flex-1 border-none bg-transparent text-sm text-ink outline-none"
            />
            {q && <button type="button" onClick={() => setQ('')} className="cursor-pointer border-0 bg-transparent px-1 font-narrow text-[10px] font-semibold uppercase text-red">Limpiar</button>}
          </label>
        </div>

        <p className="mb-4 text-xs text-soft" aria-live="polite">
          {zonas.reduce((total, zona) => total + zona.distritos.length, 0)} distritos disponibles
        </p>
        {zonas.length ? zonas.map((z) => (
          <article key={z.nombre} className="mb-5 border border-rule bg-surface p-4 sm:p-5">
            <div className="mb-3 flex flex-wrap items-baseline justify-between gap-2 border-b border-rule pb-3">
              <h3 className="font-narrow text-xs font-semibold uppercase tracking-[0.14em]">{z.nombre}</h3>
              <span className="text-xs text-soft">{z.distritos.length} {z.distritos.length === 1 ? 'distrito' : 'distritos'}</span>
            </div>
            <div className="grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-4">
              {z.distritos.map((d) => (
                <div key={d} className="flex min-h-10 items-center gap-2 border border-rule bg-paper px-3 py-2 text-[13px]">
                  <span className="h-1.5 w-1.5 shrink-0 bg-red" />
                  <span className="min-w-0">{d}</span>
                </div>
              ))}
            </div>
          </article>
        )) : <div className="border border-rule bg-surface px-5 py-8 text-center">
          <p className="font-display text-xl">No encontramos ese distrito</p>
          <p className="mt-1 text-sm text-soft">Prueba con otro nombre o consulta con nuestro equipo.</p>
          <button type="button" onClick={() => setQ('')} className="mt-4 cursor-pointer border border-ink bg-transparent px-4 py-2 font-narrow text-xs font-semibold uppercase">Ver todos los distritos</button>
        </div>}
      </section>

      <div className="flex flex-col items-start gap-[18px] border-l-[3px] border-red bg-surface p-5 md:flex-row md:items-center md:px-6">
        <p className="m-0 max-w-[56ch] flex-1 text-sm leading-[1.6] text-body">
          Por ahora entregamos solo en los distritos listados de Lima Metropolitana y Callao. Si tu zona no aparece,
          escríbenos para consultar futuras opciones.
        </p>
        <button
          type="button"
          onClick={() => navigate('/catalogo')}
          className="min-h-[52px] cursor-pointer whitespace-nowrap border-none bg-ink px-[22px] font-narrow text-sm font-semibold uppercase tracking-[0.08em] text-paper hover:bg-red hover:text-[#F2F2F0]"
        >
          Ver catálogo
        </button>
      </div>
    </div>
  );
}
