import { useState } from 'react';
import { Seo } from '../components/Seo';
import { api } from '../services/api';

const TEMAS = [
  { key: 'talla', label: 'Duda de talla' },
  { key: 'pedido', label: 'Mi pedido' },
  { key: 'otro', label: 'Otro tema' },
] as const;

const CANALES = [
  { k: 'Atención personalizada', v: 'Hablemos de tu talla', note: 'Cuéntanos qué prenda estás viendo y tus medidas si necesitas ayuda con el ajuste.' },
  { k: 'Tu consulta', v: 'Directo a nuestro equipo', note: 'El mensaje queda registrado en el panel de atención de Kloset.' },
];

type Tema = (typeof TEMAS)[number]['key'];

export function ContactPage() {
  const [tema, setTema] = useState<Tema>('talla');
  const [nombre, setNombre] = useState('');
  const [correo, setCorreo] = useState('');
  const [telefono, setTelefono] = useState('');
  const [mensaje, setMensaje] = useState('');
  const [enviado, setEnviado] = useState('');
  const [error, setError] = useState('');
  const [enviando, setEnviando] = useState(false);

  const campos = [
    { k: 'nombre', label: 'Nombre', ph: 'Tu nombre', value: nombre, set: setNombre },
    { k: 'correo', label: 'Correo', ph: 'tucorreo@correo.pe', value: correo, set: setCorreo },
    { k: 'tel', label: 'Teléfono (opcional)', ph: '9XX XXX XXX', value: telefono, set: setTelefono },
  ];

  const enviar = async () => {
    setError('');
    setEnviado('');
    if (nombre.trim().length < 2 || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo.trim()) || mensaje.trim().length < 10) {
      setError('Completa tu nombre, un correo válido y un mensaje de al menos 10 caracteres.');
      return;
    }
    setEnviando(true);
    try {
      const res = await api.contacto({ tema, nombre: nombre.trim(), correo: correo.trim(), telefono: telefono.trim(), mensaje: mensaje.trim(), sitio: '' });
      if (res.status !== 'success') {
        setError(res.message || 'No pudimos guardar tu consulta. Inténtalo de nuevo.');
        return;
      }
      setEnviado('Consulta recibida. Te responderemos al correo que indicaste.');
      setMensaje('');
    } catch {
      setError('No pudimos guardar tu consulta. Comprueba tu conexión e inténtalo de nuevo.');
    } finally {
      setEnviando(false);
    }
  };

  return (
    <div className="kl-rise">
      <Seo
        title="Contáctanos"
        description="Escríbenos desde el formulario de Kloset. Si tienes una duda de ajuste, cuéntanos tus medidas y la prenda que estás viendo."
        path="/contacto"
      />
      <div className="mb-7 max-w-[760px] border-b border-ink pb-6">
        <div className="mb-[18px] inline-block border-b-[3px] border-red pb-[6px] font-narrow text-xs uppercase tracking-[0.14em] text-ink">
          Contáctanos
        </div>
        <h1 className="mb-[14px] font-display text-[34px] font-normal leading-[1.04] tracking-[-0.025em] md:text-[54px]">
          Hablamos de <em className="italic">tu talla.</em>
        </h1>
        <p className="m-0 max-w-[54ch] text-[15.5px] leading-[1.65] text-body">
          Escríbenos desde el formulario. Si es una duda de ajuste, cuéntanos tus medidas y qué prenda
          estás viendo para poder orientarte mejor.
        </p>
      </div>

      <div className="flex flex-col items-start gap-6 lg:flex-row lg:gap-11">
        <div className="min-w-0 w-full flex-[1.15]">
          <div className="mb-[22px] flex border border-ink">
            {TEMAS.map((t, i) => (
              <button
                key={t.key}
                type="button"
                onClick={() => setTema(t.key)}
                className="min-h-[46px] flex-1 cursor-pointer border-none font-narrow text-[12.5px] font-semibold uppercase tracking-[0.06em] transition-colors"
                style={{
                  background: tema === t.key ? 'var(--ink)' : 'transparent',
                  color: tema === t.key ? 'var(--paper)' : 'var(--soft)',
                  borderRight: i === TEMAS.length - 1 ? 'none' : '1px solid var(--ink)',
                }}
              >
                {t.label}
              </button>
            ))}
          </div>

          <div className="border-t border-ink">
            {campos.map((f) => (
              <div key={f.k} className="border-b border-rule py-[14px]">
                <label className="mb-[6px] block font-narrow text-[11.5px] uppercase tracking-[0.12em] text-soft">
                  {f.label}
                </label>
                <input
                  type={f.k === 'correo' ? 'email' : f.k === 'tel' ? 'tel' : 'text'}
                  value={f.value}
                  onChange={(e) => {
                    f.set(e.target.value);
                    setEnviado('');
                  }}
                  placeholder={f.ph}
                  maxLength={f.k === 'tel' ? 30 : f.k === 'correo' ? 150 : 120}
                  className="min-h-10 w-full border-none bg-transparent py-2 text-base text-ink outline-none"
                />
              </div>
            ))}
            <div className="border-b border-rule py-[14px]">
              <label className="mb-[6px] block font-narrow text-[11.5px] uppercase tracking-[0.12em] text-soft">
                Mensaje
              </label>
              <textarea
                value={mensaje}
                onChange={(e) => {
                  setMensaje(e.target.value);
                  setEnviado('');
                }}
                rows={5}
                maxLength={3000}
                placeholder="Cuéntanos qué necesitas"
                className="w-full resize-y border-none bg-transparent py-2 text-base leading-[1.55] text-ink outline-none"
              />
            </div>
          </div>

          {enviado && (
            <div role="status" className="mt-4 border-l-[3px] border-red bg-surface px-[14px] py-3 font-narrow text-xs uppercase tracking-[0.06em]">
              {enviado}
            </div>
          )}
          {error && <div role="alert" className="mt-4 border-l-[3px] border-red bg-surface px-[14px] py-3 text-sm text-red">{error}</div>}

          <button
            type="button"
            onClick={enviar}
            disabled={enviando}
            className="mt-[22px] min-h-[58px] w-full cursor-pointer border-none bg-ink font-narrow text-base font-semibold uppercase tracking-[0.08em] text-paper hover:bg-red hover:text-[#F2F2F0]"
          >
            {enviando ? 'Enviando…' : 'Enviar mensaje'}
          </button>
          <p className="mt-[14px] text-[12.5px] leading-[1.55] text-soft">
            Usaremos tu correo únicamente para responder a esta consulta.
          </p>
        </div>

        <div className="w-full flex-none border-t-[3px] border-red bg-surface p-6 lg:w-[320px]">
          {CANALES.map((c) => (
            <div key={c.k} className="border-b border-rule py-[14px]">
              <div className="mb-[6px] font-narrow text-[11px] uppercase tracking-[0.14em] text-soft">{c.k}</div>
              <div className="font-display text-[19px] leading-[1.3]">{c.v}</div>
              <div className="mt-[5px] text-[12.5px] leading-[1.5] text-body">{c.note}</div>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
