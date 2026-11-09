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
      <div className="mb-9 border-b border-ink pb-8">
        <div>
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
      </div>

      <section aria-labelledby="cifras-title" className="mb-10">
        <div className="mb-4 flex items-end justify-between gap-3 border-b border-ink pb-3">
          <div><p className="font-narrow text-[11px] font-semibold uppercase tracking-[0.14em] text-red">Kloset en breve</p><h2 id="cifras-title" className="mt-1 font-display text-[28px]">En cifras</h2></div>
          <span className="hidden text-xs text-soft sm:block">Una mirada a lo que nos mueve</span>
        </div>
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
          {CIFRAS.map((f, index) => (
            <article key={f.k} className={`min-h-[142px] border border-rule border-t-4 p-5 ${index === 0 ? 'border-t-red bg-red/5' : index === 1 ? 'border-t-ink bg-surface' : index === 2 ? 'border-t-soft bg-surface-2' : 'border-t-red bg-surface'}`}>
              <div className="font-display text-[32px] leading-none tracking-[-0.02em]">{f.v}</div>
              <div className="mt-3 text-[13px] leading-[1.5] text-body">{f.k}</div>
            </article>
          ))}
        </div>
      </section>

      <section aria-labelledby="historia-title" className="mb-10">
        <div className="mb-4 border-b border-ink pb-3">
          <p className="font-narrow text-[11px] font-semibold uppercase tracking-[0.14em] text-red">Lo que hacemos</p>
          <h2 id="historia-title" className="mt-1 font-display text-[28px]">Una mejor experiencia, desde la talla</h2>
        </div>
        <div className="grid gap-3 md:grid-cols-2">
          {BLOQUES.map((b, index) => (
            <article key={b.t} className="border border-rule bg-surface p-5 sm:p-6">
              <div className="mb-4 flex items-center gap-3">
                <span className="flex h-9 w-9 shrink-0 items-center justify-center bg-red/10 font-narrow text-xs font-semibold text-red">0{index + 1}</span>
                <h3 className="font-display text-[21px] tracking-[-0.015em]">{b.t}</h3>
              </div>
              <p className="m-0 text-[14px] leading-[1.7] text-body">{b.d}</p>
            </article>
          ))}
        </div>
      </section>

      <section aria-labelledby="valores-title" className="border-t-2 border-ink pt-5">
        <div className="mb-4">
          <p className="font-narrow text-[11px] font-semibold uppercase tracking-[0.14em] text-red">Lo que nos guía</p>
          <h2 id="valores-title" className="mt-1 font-display text-[28px]">Cómo trabajamos</h2>
        </div>
        <div className="grid gap-3 md:grid-cols-3">
          {VALORES.map((v) => (
            <article key={v.n} className="border border-rule bg-paper p-5">
              <div className="mb-4 flex h-9 w-9 items-center justify-center bg-ink font-narrow text-xs font-semibold text-paper">{v.n}</div>
              <h3 className="mb-2 font-display text-[20px]">{v.t}</h3>
              <p className="m-0 text-[13.5px] leading-[1.65] text-body">{v.d}</p>
            </article>
          ))}
        </div>
      </section>
    </div>
  );
}
