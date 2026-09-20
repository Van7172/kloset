import { useState } from 'react';
import { Seo } from '../components/Seo';

const TEMAS = [
  { key: 'talla', label: 'Duda de talla' },
  { key: 'pedido', label: 'Mi pedido' },
  { key: 'otro', label: 'Otro tema' },
] as const;

const CANALES = [
  { k: 'WhatsApp', v: '+51 987 654 321', note: 'Lunes a sábado, 9:00 a 20:00' },
  { k: 'Correo', v: 'hola@kloset.pe', note: 'Respuesta en el mismo día hábil' },
  { k: 'Showroom', v: 'Av. Arequipa 2450, Lince', note: 'Cita previa · pruebas de ajuste' },
  { k: 'Atención al cliente', v: '(01) 480 2200', note: 'Lunes a viernes, 9:00 a 18:00' },
];

type Tema = (typeof TEMAS)[number]['key'];

export function ContactPage() {
  const [tema, setTema] = useState<Tema>('talla');
  const [nombre, setNombre] = useState('');
  const [correo, setCorreo] = useState('');
  const [telefono, setTelefono] = useState('');
  const [mensaje, setMensaje] = useState('');
  const [enviado, setEnviado] = useState('');

  const campos = [
    { k: 'nombre', label: 'Nombre', ph: 'Tu nombre', value: nombre, set: setNombre },
    { k: 'correo', label: 'Correo', ph: 'tucorreo@correo.pe', value: correo, set: setCorreo },
    { k: 'tel', label: 'Teléfono (opcional)', ph: '9XX XXX XXX', value: telefono, set: setTelefono },
  ];

  const enviar = () => {
    setEnviado('Mensaje enviado · te respondemos por correo');
  };

  return (
    <div className="kl-rise">
      <Seo
        title="Contáctanos"
        description="Escríbenos por formulario o WhatsApp. Si es una duda de ajuste, contestamos con la talla y la holgura exacta."
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
          Escríbenos por el formulario o por WhatsApp. Si es una duda de ajuste, cuéntanos tus medidas y qué prenda
          estás viendo: contestamos con la talla y la holgura exacta.
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
                  value={f.value}
                  onChange={(e) => {
                    f.set(e.target.value);
                    setEnviado('');
                  }}
                  placeholder={f.ph}
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
                placeholder="Cuéntanos qué necesitas"
                className="w-full resize-y border-none bg-transparent py-2 text-base leading-[1.55] text-ink outline-none"
              />
            </div>
          </div>

          {enviado && (
            <div className="mt-4 border-l-[3px] border-red bg-surface px-[14px] py-3 font-narrow text-xs uppercase tracking-[0.06em]">
              {enviado}
            </div>
          )}

          <button
            type="button"
            onClick={enviar}
            className="mt-[22px] min-h-[58px] w-full cursor-pointer border-none bg-ink font-narrow text-base font-semibold uppercase tracking-[0.08em] text-paper hover:bg-red hover:text-[#F2F2F0]"
          >
            Enviar mensaje
          </button>
          <p className="mt-[14px] text-[12.5px] leading-[1.55] text-soft">
            Respondemos en horario de atención, normalmente el mismo día hábil.
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
