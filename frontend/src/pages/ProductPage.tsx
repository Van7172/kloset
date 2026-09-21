import { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { api, type ApiDetalle } from '../services/api';
import { useKloset, type ItemBolsa } from '../store/KlosetContext';
import { TALLAS, money, tallaRecomendada, type Talla } from '../lib/fit';
import { Marco } from '../components/Marco';
import { Seo } from '../components/Seo';

export function ProductPage() {
  const { url = '' } = useParams();
  const navigate = useNavigate();
  const { medidas, corte, usuario, añadirABolsa, setIntencion } = useKloset();

  const [detalle, setDetalle] = useState<ApiDetalle | null>(null);
  const [error, setError] = useState('');
  const [foto, setFoto] = useState(0);
  const [talla, setTalla] = useState<Talla | null>(null);
  const [errorBolsa, setErrorBolsa] = useState('');
  const [añadiendo, setAñadiendo] = useState(false);

  useEffect(() => {
    setDetalle(null);
    setFoto(0);
    api
      .producto(url)
      .then((res) => {
        if (res.status === 'success') setDetalle(res);
        else setError(res.message || 'Producto no encontrado');
      })
      .catch(() => setError('API no disponible'));
  }, [url]);

  if (error) {
    return (
      <div className="kl-rise mx-auto max-w-[560px] py-10 text-center">
        <p className="mb-6 text-red">{error}</p>
        <button
          type="button"
          onClick={() => navigate('/')}
          className="min-h-[50px] cursor-pointer border-none bg-ink px-6 font-narrow text-sm font-semibold uppercase tracking-[0.08em] text-paper hover:bg-red hover:text-[#F2F2F0]"
        >
          Ir al catálogo
        </button>
      </div>
    );
  }

  if (!detalle) {
    return <p className="py-16 text-center text-soft">Cargando prenda…</p>;
  }

  const p = detalle.producto;
  const precio = Number(p.precio_producto);
  const recomendada = tallaRecomendada(medidas, corte);
  const elegida = talla ?? recomendada;
  const imagenes = detalle.imagenes;
  const stock = detalle.variantes.reduce((a, v) => a + v.stock_variante, 0);

  const item: ItemBolsa = {
    id_producto: p.id_producto,
    url_producto: p.url_producto,
    name: p.nombre_producto,
    size: elegida,
    fit: corte,
    price: precio,
    imagen: imagenes[0]?.url_imagen ?? null,
    cantidad: 1,
  };

  const añadir = async () => {
    if (!usuario) {
      setIntencion({ then: 'cart', item });
      navigate('/entrar');
      return;
    }
    setErrorBolsa('');
    setAñadiendo(true);
    const fallo = await añadirABolsa(item);
    setAñadiendo(false);
    if (fallo) return setErrorBolsa(fallo);
    navigate('/bolsa');
  };

  const specs = [
    { k: 'Categoría', v: p.nombre_categoria ?? '—' },
    { k: 'Corte seleccionado', v: corte },
    { k: 'SKU disponibles', v: `${detalle.variantes.length} variantes · ${stock} uds` },
    { k: 'Cambio de talla', v: 'Sin coste durante 30 días' },
  ];

  const resumen = p.descripcion_producto
    ? p.descripcion_producto.slice(0, 155)
    : `${p.nombre_producto} en corte ${corte.toLowerCase()}. Talla sugerida para tus medidas: ${recomendada}. Cambio de talla gratis 30 días.`;

  return (
    <div className="kl-rise">
      <Seo
        title={p.nombre_producto}
        description={resumen}
        path={`/producto/${p.url_producto}`}
        image={imagenes[0]?.url_imagen ?? undefined}
      />
      <button
        type="button"
        onClick={() => navigate('/')}
        className="min-h-[44px] cursor-pointer border-none bg-transparent py-[10px] font-narrow text-[13px] uppercase tracking-[0.08em] text-soft"
      >
        ← Catálogo
      </button>

      <div className="flex flex-col items-start gap-8 lg:flex-row lg:gap-14">
        <div className="w-full lg:flex-[1.15]">
          <Marco
            src={imagenes[foto]?.url_imagen ?? null}
            alt={p.nombre_producto}
            etiqueta="Foto de producto · frontal"
            prioridad
          />
          {imagenes.length > 1 && (
            <div className="mt-2 grid grid-cols-4 gap-2">
              {imagenes.slice(0, 8).map((img, i) => (
                <button
                  key={img.id_imagen}
                  type="button"
                  onClick={() => setFoto(i)}
                  className="relative aspect-square cursor-pointer overflow-hidden border p-0"
                  style={{ borderColor: i === foto ? 'var(--ink)' : 'transparent' }}
                >
                  <img
                    src={img.url_imagen}
                    alt=""
                    width={120}
                    height={120}
                    loading="lazy"
                    decoding="async"
                    className="h-full w-full object-cover"
                  />
                </button>
              ))}
            </div>
          )}
        </div>

        <div className="w-full lg:sticky lg:top-[82px] lg:flex-1">
          <div className="inline-block border-b-[3px] border-red pb-[5px] font-narrow text-xs uppercase tracking-[0.14em] text-ink">
            {p.nombre_categoria ?? 'Kloset'}
          </div>
          <h2 className="mb-[10px] mt-4 font-display text-4xl font-normal leading-[1.06] tracking-[-0.025em]">
            {p.nombre_producto}
          </h2>
          <div className="mb-[18px] flex items-baseline gap-[14px]">
            <span className="font-display text-2xl">{money(precio)}</span>
            <span className="text-[13px] text-soft">{corte} · {stock} uds en stock</span>
          </div>
          {p.descripcion_producto && (
            <p className="mb-6 max-w-[48ch] text-[15px] leading-[1.65] text-body">{p.descripcion_producto}</p>
          )}

          <div className="flex items-baseline justify-between border-b border-ink pb-[10px]">
            <span className="font-narrow text-xs uppercase tracking-[0.12em] text-soft">Talla</span>
            <span className="text-[13px] text-body">
              Sugerida para ti: <span className="font-semibold text-red">{recomendada}</span>
            </span>
          </div>
          <div className="mb-[22px] grid grid-cols-5 border border-t-0 border-ink">
            {TALLAS.map((s, i) => (
              <button
                key={s}
                type="button"
                onClick={() => setTalla(s)}
                className="relative min-h-[52px] cursor-pointer border-none font-narrow text-[14.5px] font-semibold tracking-[0.06em] transition-colors"
                style={{
                  background: elegida === s ? 'var(--ink)' : 'transparent',
                  color: elegida === s ? 'var(--paper)' : 'var(--soft)',
                  borderRight: i === TALLAS.length - 1 ? 'none' : '1px solid var(--ink)',
                }}
              >
                {s}
                <span
                  className="absolute bottom-[5px] left-1/2 h-[3px] -translate-x-1/2 bg-red"
                  style={{ width: s === recomendada ? '16px' : '0px' }}
                />
              </button>
            ))}
          </div>

          <div className="flex flex-col gap-2">
            <button
              type="button"
              onClick={() => navigate(`/avatar?producto=${p.url_producto}`)}
              className="min-h-[56px] cursor-pointer border-none bg-ink font-narrow text-[15px] font-semibold uppercase tracking-[0.08em] text-paper hover:bg-red hover:text-[#F2F2F0]"
            >
              Ver en mi avatar
            </button>
            <button
              type="button"
              onClick={añadir}
              disabled={añadiendo}
              className="min-h-[56px] cursor-pointer border border-ink bg-transparent font-narrow text-[15px] font-semibold uppercase tracking-[0.08em] text-ink hover:bg-hover disabled:opacity-50"
            >
              {añadiendo ? 'Añadiendo…' : `Añadir a la bolsa · ${elegida}`}
            </button>
          </div>

          {errorBolsa && (
            <div className="mt-3 border-l-[3px] border-red py-[6px] pl-[10px] font-narrow text-[12.5px] uppercase tracking-[0.06em] text-red">
              {errorBolsa}
            </div>
          )}

          <div className="mt-[26px] border-t border-ink pt-1">
            {specs.map((s) => (
              <div key={s.k} className="flex justify-between gap-4 border-b border-rule py-3 text-[13.5px]">
                <span className="text-soft">{s.k}</span>
                <span className="text-right text-ink">{s.v}</span>
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}
