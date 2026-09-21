import { Seo } from '../components/Seo';
import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useKloset } from '../store/KlosetContext';
import { RANGOS_MEDIDAS, type Medidas } from '../lib/fit';

export function MeasurePage() {
  const navigate = useNavigate();
  const { medidas, guardarPerfil } = useKloset();
  const [valores, setValores] = useState<Medidas>(medidas);
  const [guardando, setGuardando] = useState(false);

  const calcular = async () => {
    setGuardando(true);
    await guardarPerfil(valores);
    setGuardando(false);
    navigate('/resultado');
  };

  return (
    <div className="kl-rise mx-auto max-w-[560px]">
      <Seo title="Cómo medirte" description="Registra tus cuatro medidas —estatura, pecho, cintura y cadera— y Kloset calcula tu talla exacta para cada corte. Solo se hace una vez." path="/medidas" />
      <div className="my-[6px] mb-6 flex gap-[5px]">
        <div className="h-[3px] flex-1 origin-left bg-red" style={{ animation: 'kl-rule .5s ease both' }} />
        <div className="h-[3px] flex-1 bg-rule" />
        <div className="h-[3px] flex-1 bg-rule" />
      </div>

      <div className="mb-3 font-narrow text-xs uppercase tracking-[0.14em] text-soft">Paso 1 · Cuatro datos</div>
      <h2 className="mb-3 font-display text-[34px] font-normal leading-[1.08] tracking-[-0.025em]">
        Cuéntanos tu cuerpo <em className="italic">una sola vez.</em>
      </h2>
      <p className="mb-7 text-[15px] leading-[1.65] text-body">
        Con una cinta métrica tardas dos minutos. Si no tienes una, mueve los controles hasta que la silueta se
        parezca a ti.
      </p>

      <div className="border-t border-ink">
        {RANGOS_MEDIDAS.map((f) => (
          <div key={f.key} className="border-b border-rule py-[18px]">
            <div className="flex items-baseline justify-between gap-3">
              <div>
                <div className="font-display text-[19px] font-medium">{f.label}</div>
                <div className="mt-[3px] text-[12.5px] text-soft">{f.hint}</div>
              </div>
              <div className="flex items-baseline gap-1">
                <span className="font-display text-[30px] tracking-[-0.02em]">{valores[f.key]}</span>
                <span className="text-[13px] text-soft">cm</span>
              </div>
            </div>
            <input
              type="range"
              min={f.min}
              max={f.max}
              value={valores[f.key]}
              onChange={(e) => setValores({ ...valores, [f.key]: Number(e.target.value) })}
              aria-label={f.label}
              className="mt-3 h-[26px] w-full"
            />
          </div>
        ))}
      </div>

      <p className="mb-6 mt-5 text-[12.5px] leading-[1.55] text-soft">
        Tus medidas se guardan solo en tu cuenta. No se comparten ni se usan para publicidad.
      </p>

      <button
        type="button"
        onClick={calcular}
        disabled={guardando}
        className="min-h-[58px] w-full cursor-pointer border-none bg-ink font-narrow text-base font-semibold uppercase tracking-[0.08em] text-paper hover:bg-red hover:text-[#F2F2F0] disabled:opacity-50"
      >
        {guardando ? 'Guardando…' : 'Calcular mi talla'}
      </button>
    </div>
  );
}
