import { useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Seo } from '../components/Seo';

const ZONAS = [
  {
    nombre: 'Provincia Constitucional del Callao',
    plazo: 'entrega en 24 h',
    distritos: ['Bellavista', 'Callao', 'Carmen de la Legua-Reynoso', 'La Perla', 'La Punta', 'Ventanilla'],
  },
  {
    nombre: 'Lima Metropolitana',
    plazo: 'entrega en 24–48 h',
    distritos: [
      'Ancón', 'Ate', 'Barranco', 'Breña', 'Carabayllo', 'Chaclacayo', 'Chorrillos', 'Chosica',
      'Cieneguilla', 'Comas', 'El Agustino', 'Independencia', 'Jesús María', 'La Molina', 'La Victoria',
      'Lima - Cercado', 'Lince', 'Los Olivos', 'Lurigancho-Chosica', 'Lurigancho-Zárate', 'Lurín',
      'Magdalena del Mar', 'Miraflores', 'Pachacámac', 'Pucusana', 'Pueblo Libre', 'Puente Piedra',
      'Punta Hermosa', 'Punta Negra', 'Rímac', 'San Bartolo', 'San Borja', 'San Isidro',
      'San Juan de Lurigancho', 'San Juan de Miraflores', 'San Luis', 'San Martín de Porres',
      'San Miguel', 'Santa Anita', 'Santa María del Mar', 'Santa Rosa', 'Santiago de Surco',
      'Surquillo', 'Villa El Salvador', 'Villa María del Triunfo',
    ],
  },
];

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
      ZONAS.map((z) => ({
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
      <div className="mb-[26px] max-w-[760px] border-b border-ink pb-6">
        <div className="mb-[18px] inline-block border-b-[3px] border-red pb-[6px] font-narrow text-xs uppercase tracking-[0.14em] text-ink">
          Lima y Callao
        </div>
        <h1 className="mb-[14px] font-display text-[34px] font-normal leading-[1.04] tracking-[-0.025em] md:text-[54px]">
          Cobertura <em className="italic">de entrega.</em>
        </h1>
        <p className="m-0 max-w-[52ch] text-[15.5px] leading-[1.65] text-body">
          Repartimos con nuestra propia flota en Lima Metropolitana y la Provincia Constitucional del Callao. Si tu
          distrito está en la lista, tu pedido llega en 24 a 72 horas hábiles sin coste de envío.
        </p>
      </div>

      <div className="mb-[30px] grid grid-cols-1 gap-px border border-rule bg-rule md:grid-cols-3">
        {STATS.map((s) => (
          <div key={s.label} className="bg-paper p-[18px]">
            <div className="font-narrow text-[11px] uppercase tracking-[0.14em] text-soft">{s.label}</div>
            <div className="my-2 font-display text-[30px] leading-none tracking-[-0.02em]">{s.value}</div>
            <div className="text-[13px] leading-[1.5] text-body">{s.note}</div>
          </div>
        ))}
      </div>

      <div className="mb-[22px] flex max-w-[520px] items-center gap-3 border-b border-rule py-[2px]">
        <span className="font-display text-[19px] text-soft">/</span>
        <input
          value={q}
          onChange={(e) => setQ(e.target.value)}
          placeholder="Busca tu distrito"
          className="flex-1 border-none bg-transparent py-[14px] text-base text-ink outline-none"
        />
      </div>

      {zonas.map((z) => (
        <div key={z.nombre} className="mb-8">
          <div className="flex items-baseline justify-between gap-3 border-b border-ink pb-[10px]">
            <span className="font-narrow text-xs uppercase tracking-[0.14em]">{z.nombre}</span>
            <span className="font-display text-base text-soft">
              {z.distritos.length} {z.distritos.length === 1 ? 'distrito' : 'distritos'} · {z.plazo}
            </span>
          </div>
          <div className="grid grid-cols-1 gap-x-[22px] md:grid-cols-2 xl:grid-cols-4">
            {z.distritos.map((d) => (
              <div key={d} className="flex items-center gap-[9px] border-b border-rule py-[11px] text-[13.5px]">
                <span className="h-[5px] w-[5px] shrink-0 bg-red" />
                <span className="min-w-0 truncate">{d}</span>
              </div>
            ))}
          </div>
        </div>
      ))}

      <div className="flex flex-col items-start gap-[18px] border-t-2 border-ink pt-5 md:flex-row">
        <p className="m-0 max-w-[56ch] flex-1 text-sm leading-[1.6] text-body">
          ¿No ves tu distrito? Enviamos al resto del Perú por agencia con cargo aparte, y el cambio de talla sigue
          siendo gratuito.
        </p>
        <button
          type="button"
          onClick={() => navigate('/')}
          className="min-h-[52px] cursor-pointer whitespace-nowrap border-none bg-ink px-[22px] font-narrow text-sm font-semibold uppercase tracking-[0.08em] text-paper hover:bg-red hover:text-[#F2F2F0]"
        >
          Ver catálogo
        </button>
      </div>
    </div>
  );
}
