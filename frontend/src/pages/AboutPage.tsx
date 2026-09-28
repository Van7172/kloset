import { Seo } from '../components/Seo';

const BLOQUES = [
  {
    t: 'De dónde venimos',
    d: 'Empezamos en 2024 como un taller pequeño en Lince que vendía dos modelos de camiseta. La talla era el motivo del 70 % de los cambios, y ninguna guía de tallas lo resolvía. Dejamos de escribir tablas y empezamos a medir cuerpos.',
  },
  {
    t: 'Qué hacemos distinto',
    d: 'Cada prenda del catálogo tiene sus variantes reales por talla y corte, con las medidas del patrón cargadas en el sistema. Cuando registras estatura, pecho, cintura y cadera, comparamos tu cuerpo con el patrón y te decimos cuánta holgura vas a tener en cada zona.',
  },
  {
    t: 'El avatar',
    d: 'No es un adorno. Es la forma más honesta que encontramos de mostrar una prenda: sobre tu proporción, no sobre un modelo de 1,80 m. Puedes girarlo y comparar Slim, Regular y Oversize antes de decidir.',
  },
  {
    t: 'Nuestro compromiso',
    d: 'Si la talla que recomendamos no te queda, el cambio es gratuito. Nos interesa acertar a la primera, no vender dos veces.',
  },
];

const CIFRAS = [
  { v: '51', k: 'Distritos con reparto propio en Lima y Callao' },
  { v: '12.400', k: 'Perfiles corporales usados para calibrar las tallas' },
  { v: '3,1 %', k: 'Devoluciones por talla, frente al 24 % del sector' },
  { v: '2024', k: 'Año de fundación, en Lince, Lima' },
];

const VALORES = [
  { n: '01', t: 'Datos que no se venden', d: 'Tus medidas viven en tu cuenta. No las compartimos ni las usamos para publicidad.' },
  { n: '02', t: 'Producción corta', d: 'Series pequeñas por talla y corte. Preferimos agotar un SKU que rematarlo.' },
  { n: '03', t: 'Precio sin teatro', d: 'Un precio por prenda todo el año. Sin tachados inflados ni cuentas atrás.' },
];

export function AboutPage() {
  return (
    <div className="kl-rise">
      <Seo
        title="Nosotros"
        description="Kloset nació en Lima para resolver las devoluciones por talla: registras tus medidas una vez y ves cómo te queda cada prenda antes de pagar."
        path="/nosotros"
      />
      <div className="mb-[30px] max-w-[800px] border-b border-ink pb-[26px]">
        <div className="mb-[18px] inline-block border-b-[3px] border-red pb-[6px] font-narrow text-xs uppercase tracking-[0.14em] text-ink">
          Nosotros
        </div>
        <h1 className="mb-4 font-display text-[34px] font-normal leading-[1.04] tracking-[-0.025em] md:text-[54px]">
          Una tienda que mide
          <br />
          <em className="italic">antes de vender.</em>
        </h1>
        <p className="m-0 max-w-[60ch] text-base leading-[1.7] text-body">
          Kloset nació en Lima con una idea simple: la mayoría de las devoluciones de ropa deportiva no ocurren por
          la prenda, sino por la talla. Construimos una tienda donde el cliente registra sus medidas una vez y ve
          cómo le queda cada prenda sobre su propio avatar antes de pagar.
        </p>
      </div>

      <div className="mb-[34px] flex flex-col items-start gap-6 lg:flex-row lg:gap-11">
        <div className="min-w-0 flex-[1.1]">
          {BLOQUES.map((b) => (
            <div key={b.t} className="border-b border-rule py-5">
              <div className="mb-2 font-display text-[21px] tracking-[-0.015em]">{b.t}</div>
              <p className="m-0 max-w-[58ch] text-[14.5px] leading-[1.65] text-body">{b.d}</p>
            </div>
          ))}
        </div>
        <div className="w-full flex-none border-t-[3px] border-red bg-surface p-6 lg:w-[320px]">
          <div className="mb-4 font-narrow text-[11.5px] uppercase tracking-[0.14em] text-soft">En cifras</div>
          {CIFRAS.map((f) => (
            <div key={f.k} className="border-b border-rule py-[13px]">
              <div className="font-display text-[26px] leading-none tracking-[-0.02em]">{f.v}</div>
              <div className="mt-[6px] text-[13px] leading-[1.5] text-body">{f.k}</div>
            </div>
          ))}
        </div>
      </div>

      <div className="border-t-2 border-ink pt-[22px]">
        <div className="mb-[6px] font-narrow text-xs uppercase tracking-[0.14em] text-soft">Cómo trabajamos</div>
        <div className="grid grid-cols-1 gap-x-[26px] md:grid-cols-3">
          {VALORES.map((v) => (
            <div key={v.n} className="border-b border-rule py-[18px]">
              <div className="mb-2 font-narrow text-xs uppercase tracking-[0.1em] text-red">{v.n}</div>
              <div className="mb-[7px] font-display text-[19px]">{v.t}</div>
              <p className="m-0 text-[13.5px] leading-[1.6] text-body">{v.d}</p>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
