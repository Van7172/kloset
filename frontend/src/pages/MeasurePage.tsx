import { Seo } from '../components/Seo';
import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth, useProfile } from '../store/KlosetContext';
import { RANGOS_MEDIDAS, type Medidas } from '../lib/fit';

export function MeasurePage() {
  const navigate = useNavigate();
  const { usuario } = useAuth();
  const { medidas, guardarPerfil } = useProfile();
  const [valores, setValores] = useState<Medidas>(medidas);
  const [guardando, setGuardando] = useState(false);
  const [error, setError] = useState('');

  const guardar = async () => {
    setError('');
    setGuardando(true);
    const fallo = await guardarPerfil(valores);
    setGuardando(false);
    if (fallo) {
      setError(fallo);
      return;
    }
    navigate(usuario ? '/cuenta' : '/catalogo');
  };

  return (
    <div className="kl-rise mx-auto max-w-[1080px]">
      <Seo
        title="Ajuste de mis medidas"
        description="Guarda tus medidas corporales para recibir recomendaciones de talla más precisas en todos nuestros productos."
        path="/medidas"
      />
      <div className="grid gap-8 lg:grid-cols-2 lg:items-start lg:gap-10">
        <section className="min-w-0">
          <div className="mb-6 h-[3px] max-w-[380px] bg-rule"><div className="h-full w-1/4 bg-red" /></div>
          <p className="mb-2 font-narrow text-xs font-semibold uppercase tracking-[0.14em] text-soft">Mis medidas</p>
          <h1 className="mb-2 font-display text-[34px] leading-[1.08]">Ajuste de mis medidas</h1>
          <p className="mb-6 max-w-[54ch] text-[14px] leading-[1.6] text-body">
            Guarda tus medidas corporales para recibir recomendaciones de talla más precisas en todos nuestros productos.
          </p>

          <div className="border-t border-ink">
            {RANGOS_MEDIDAS.map((campo) => (
              <label key={campo.key} className="block border-b border-rule py-4">
                <span className="flex items-baseline justify-between gap-3">
                  <span className="font-display text-[18px]">{campo.label}</span>
                  <span className="flex items-baseline gap-1">
                    <span className="font-display text-[27px]">{valores[campo.key]}</span>
                    <span className="text-xs text-soft">cm</span>
                  </span>
                </span>
                <span className="sr-only">{campo.hint}</span>
                <input
                  type="range"
                  min={campo.min}
                  max={campo.max}
                  value={valores[campo.key]}
                  onChange={(event) => setValores({ ...valores, [campo.key]: Number(event.target.value) })}
                  aria-label={`${campo.label}, ${valores[campo.key]} centímetros`}
                  className="mt-2 h-7 w-full cursor-pointer"
                />
              </label>
            ))}
          </div>
        </section>

        <aside className="min-w-0 pt-2 lg:pt-[70px]">
          <div className="flex min-h-[320px] items-center justify-center border border-rule bg-surface p-6 text-center text-sm text-soft">
            Guarda tus medidas para probarlas en el probador 3D.
          </div>
        </aside>
      </div>

      {error && <p role="alert" className="mx-auto mt-5 max-w-[460px] border-l-[3px] border-red pl-3 text-sm text-red">{error}</p>}
      <div className="mt-6 flex justify-center">
        <button
          type="button"
          onClick={() => void guardar()}
          disabled={guardando}
          className="min-h-[54px] w-full max-w-[346px] cursor-pointer border-none bg-ink px-6 font-narrow text-sm font-semibold uppercase tracking-[0.06em] text-paper hover:bg-red disabled:opacity-50"
        >
          {guardando ? 'Guardando…' : 'Guardar mi talla'}
        </button>
      </div>
    </div>
  );
}
