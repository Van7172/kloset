import { Seo } from '../components/Seo';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { useKloset } from '../store/KlosetContext';
import { confianza, mapaAjuste, porQueEstaTalla, tallaRecomendada } from '../lib/fit';

export function ResultPage() {
  const navigate = useNavigate();
  const [params] = useSearchParams();
  const producto = params.get('producto');
  const { medidas, corte } = useKloset();

  const talla = tallaRecomendada(medidas, corte);
  const zonas = mapaAjuste(corte);

  return (
    <div className="kl-rise mx-auto max-w-[640px]">
      <Seo title="Tu talla recomendada" description="Tu talla y el mapa de ajuste por zonas —pecho, cintura, cadera y largo— calculados a partir de tus medidas y del corte elegido." path="/resultado" noindex />
      <div className="mb-[18px] mt-[6px] font-narrow text-xs uppercase tracking-[0.14em] text-soft">
        Paso 2 · Recomendación
      </div>

      <div className="flex flex-wrap items-start gap-[26px] border-b border-t-2 border-ink py-[26px]">
        <div
          className="flex h-[116px] w-[116px] shrink-0 items-center justify-center bg-red font-display text-[52px] font-medium tracking-[-0.03em] text-[#F2F2F0]"
          style={{ animation: 'kl-settle .5s ease both' }}
        >
          {talla}
        </div>
        <div className="min-w-[230px] flex-1">
          <div className="mb-[10px] font-display text-[26px] font-normal leading-tight tracking-[-0.02em]">
            Tu talla para el corte {corte}
          </div>
          <p className="m-0 text-[15px] leading-[1.65] text-body">{porQueEstaTalla(medidas, corte, talla)}</p>
          <div className="mt-4 border-b-2 border-red pb-[6px] font-narrow text-xs uppercase leading-[1.5] tracking-[0.1em] text-ink">
            Confianza {confianza(corte)}% · basado en tus cuatro medidas
          </div>
        </div>
      </div>

      <div className="mt-7">
        <div className="border-b border-ink pb-3 font-narrow text-xs uppercase tracking-[0.12em] text-soft">
          Holgura por zona · corte {corte}
        </div>
        {zonas.map((z) => (
          <div key={z.zone} className="border-b border-rule py-4">
            <div className="mb-2 flex justify-between text-sm">
              <span className="font-display text-[17px]">{z.zone}</span>
              <span className="font-narrow text-[13px] tracking-[0.04em] text-soft">{z.note}</span>
            </div>
            <div className="h-[6px] overflow-hidden bg-surface-2">
              <div
                className="h-full transition-[width] duration-500"
                style={{ width: z.pct, background: z.color, transitionTimingFunction: 'cubic-bezier(.2,.8,.2,1)' }}
              />
            </div>
          </div>
        ))}
      </div>

      <div className="mt-[26px] flex flex-col gap-2 sm:flex-row">
        <button
          type="button"
          onClick={() => navigate(producto ? `/avatar?producto=${producto}` : '/avatar')}
          className="min-h-[56px] flex-1 cursor-pointer border-none bg-ink font-narrow text-[15px] font-semibold uppercase tracking-[0.08em] text-paper hover:bg-red hover:text-[#F2F2F0]"
        >
          Probarla en mi avatar
        </button>
        <button
          type="button"
          onClick={() => navigate(producto ? `/producto/${producto}` : '/')}
          className="min-h-[56px] flex-1 cursor-pointer border border-ink bg-transparent font-narrow text-[15px] font-semibold uppercase tracking-[0.08em] text-ink hover:bg-hover"
        >
          {producto ? `Ver la prenda en ${talla}` : 'Ir al catálogo'}
        </button>
      </div>
    </div>
  );
}
