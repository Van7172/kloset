import { Seo } from '../components/Seo';
import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useKloset } from '../store/KlosetContext';
import { RANGOS_MEDIDAS, type Medidas } from '../lib/fit';

function FiguraMedidas({ medidas }: { medidas: Medidas }) {
  return <div className="relative mx-auto w-full max-w-[420px] overflow-hidden bg-surface px-3 py-4 sm:px-5">
    <svg viewBox="0 0 420 430" role="img" aria-label={`Silueta de referencia con ${medidas.h} centímetros de estatura, ${medidas.chest} de pecho, ${medidas.waist} de cintura y ${medidas.hip} de cadera`} className="block h-auto w-full">
      <defs>
        <linearGradient id="piel-figura" x1="0" x2="1" y1="0" y2="1">
          <stop offset="0" stopColor="#f1c9a8" />
          <stop offset="1" stopColor="#dca985" />
        </linearGradient>
      </defs>
      <g fill="none" stroke="#aaa9a3" strokeDasharray="3 4" strokeWidth="1.2">
        <path d="M62 28v365M56 28h12M56 393h12" />
        <path d="M236 151h68M229 216h75M233 276h71" />
      </g>
      <g fill="#696963" fontFamily="Archivo, Arial, sans-serif" fontSize="12">
        <text x="12" y="48">Estatura</text>
        <text x="12" y="65" fill="#242422" fontSize="13" fontWeight="700">{medidas.h} cm</text>
        <text x="310" y="145">Pecho</text>
        <text x="310" y="162" fill="#242422" fontSize="13" fontWeight="700">{medidas.chest} cm</text>
        <text x="310" y="210">Cintura</text>
        <text x="310" y="227" fill="#242422" fontSize="13" fontWeight="700">{medidas.waist} cm</text>
        <text x="310" y="270">Cadera</text>
        <text x="310" y="287" fill="#242422" fontSize="13" fontWeight="700">{medidas.hip} cm</text>
      </g>
      <ellipse cx="190" cy="399" rx="75" ry="7" fill="#deddd8" />
      <g fill="url(#piel-figura)" stroke="#d1a080" strokeWidth="1">
        <ellipse cx="190" cy="53" rx="17" ry="21" />
        <path d="M182 71h16v20h-16z" />
        <path d="M145 104c-11 6-17 18-21 38l-11 69c-2 12 15 15 18 3l17-55 4-41z" />
        <path d="M235 104c11 6 17 18 21 38l11 69c2 12-15 15-18 3l-17-55-4-41z" />
        <path d="M168 260l-4 77-4 46c-1 8 17 10 19 1l12-81 12 81c2 9 20 7 19-1l-4-46-4-77z" />
      </g>
      <path d="M174 29c1-13 31-16 34 1l-2 12c-3-8-9-11-17-11-6 0-11 3-15 8z" fill="#292927" />
      <path d="M178 86h24l13 9 23 11-8 54-5 68 7 39c-28 13-57 13-84 0l7-39-5-68-8-54 23-11z" fill="#252524" />
      <path d="M141 104l17-10 10 15-8 54-20-2-8-15zM239 94l17 10 9 42-8 15-20 2-8-54z" fill="#252524" />
      <path d="M148 260c12 8 27 11 42 11s31-3 42-11l-4 49c-14 9-25 11-38 11s-24-2-38-11z" fill="#171716" />
      <path d="M164 379l18 3-2 12-26 2c-7 0-8-5-3-9zM200 382l18-3 13 8c5 4 4 9-3 8l-26-2z" fill="#bfc0bd" />
      <path d="M164 270v37M190 272v59M216 270v37" fill="none" stroke="#454542" strokeWidth="1" />
    </svg>
  </div>;
}

export function MeasurePage() {
  const navigate = useNavigate();
  const { medidas, guardarPerfil, usuario } = useKloset();
  const [valores, setValores] = useState<Medidas>(medidas);
  const [guardando, setGuardando] = useState(false);
  const [error, setError] = useState('');

  const calcular = async () => {
    setError('');
    setGuardando(true);
    const fallo = await guardarPerfil(valores);
    setGuardando(false);
    if (fallo) {
      setError(fallo);
      return;
    }
    navigate('/cuenta');
  };

  return (
    <div className="kl-rise mx-auto max-w-[1080px]">
      <Seo title="Ajuste de mis medidas" description="Guarda tus medidas corporales para recibir recomendaciones de talla más precisas en todos nuestros productos." path="/medidas" />
      <div className="grid gap-8 lg:grid-cols-[1.25fr_0.9fr] lg:items-start lg:gap-10">
        <section>
          <div className="mb-6 h-[3px] max-w-[380px] bg-rule"><div className="h-full w-1/4 bg-red" /></div>
          <p className="mb-2 font-narrow text-xs font-semibold uppercase tracking-[0.14em] text-soft">Mis medidas</p>
          <h1 className="mb-2 font-display text-[34px] leading-[1.08]">Ajuste de mis medidas</h1>
          <p className="mb-6 max-w-[54ch] text-[14px] leading-[1.6] text-body">Guarda tus medidas corporales para recibir recomendaciones de talla más precisas en todos nuestros productos.</p>

          <div className="border-t border-ink">
            {RANGOS_MEDIDAS.map((f) => (
              <label key={f.key} className="block border-b border-rule py-4">
                <span className="flex items-baseline justify-between gap-3">
                  <span className="font-display text-[18px]">{f.label}</span>
                  <span className="flex items-baseline gap-1"><span className="font-display text-[27px]">{valores[f.key]}</span><span className="text-xs text-soft">cm</span></span>
                </span>
                <span className="sr-only">{f.hint}</span>
                <input
                  type="range"
                  min={f.min}
                  max={f.max}
                  value={valores[f.key]}
                  onChange={(e) => setValores({ ...valores, [f.key]: Number(e.target.value) })}
                  aria-label={`${f.label}, ${valores[f.key]} centímetros`}
                  className="mt-2 h-7 w-full cursor-pointer"
                />
              </label>
            ))}
          </div>
        </section>

        <aside className="pt-2 lg:pt-[70px]">
          <FiguraMedidas medidas={valores} />
          <div className="mt-3 flex gap-3 border-b border-rule bg-surface px-4 py-3 text-xs leading-[1.5] text-body">
            <svg className="mt-0.5 shrink-0" width="19" height="19" viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="1.5" aria-hidden="true">
              <circle cx="10" cy="10" r="8" /><path d="M10 9v5M10 6.5v.2" />
            </svg>
            <span>{usuario ? 'Estas medidas se guardarán solo en tu cuenta. No se comparten ni se usan para publicidad.' : 'Tus medidas quedan guardadas en este navegador. Al entrar a tu cuenta también podrás guardarlas en tu perfil.'}</span>
          </div>
        </aside>
      </div>

      {error && <p role="alert" className="mx-auto mt-5 max-w-[460px] border-l-[3px] border-red pl-3 text-sm text-red">{error}</p>}
      <div className="mt-6 flex justify-center">
        <button
          type="button"
          onClick={calcular}
          disabled={guardando}
          className="min-h-[54px] w-full max-w-[346px] cursor-pointer border-none bg-ink px-6 font-narrow text-sm font-semibold uppercase tracking-[0.06em] text-paper hover:bg-red disabled:opacity-50"
        >
          {guardando ? 'Guardando…' : 'Guardar medidas'}
        </button>
      </div>
    </div>
  );
}
