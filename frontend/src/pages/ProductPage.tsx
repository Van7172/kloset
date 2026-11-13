import { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { api, isAbortError, type ApiDetalle } from '../services/api';
import { useAuth, useCartActions, useProfile, type ItemBolsa } from '../store/KlosetContext';
import { TALLAS, money, tallaRecomendada, type Talla } from '../lib/fit';
import { Marco } from '../components/Marco';
import { Seo } from '../components/Seo';

export function ProductPage() {
  const { url = '' } = useParams();
  const navigate = useNavigate();
  const { usuario, setIntencion, sesionVersion } = useAuth();
  const { añadirABolsa } = useCartActions();
  const { medidas, corte, tienePerfil } = useProfile();

  const [detalle, setDetalle] = useState<ApiDetalle | null>(null);
  const [error, setError] = useState('');
  const [foto, setFoto] = useState(0);
  const [talla, setTalla] = useState<Talla | null>(null);
  const [idColor, setIdColor] = useState<number | null>(null);
  const [corteElegido, setCorteElegido] = useState<string | null>(null);
  const [cantidad, setCantidad] = useState(1);
  const [errorBolsa, setErrorBolsa] = useState('');
  const [añadiendo, setAñadiendo] = useState(false);
  const [favorito, setFavorito] = useState(false);
  const [favoritoOcupado, setFavoritoOcupado] = useState(false);

  useEffect(() => {
    const controller = new AbortController();
    setDetalle(null);
    setError('');
    setTalla(null);
    setIdColor(null);
    setCorteElegido(null);
    setFoto(0);
    api
      .producto(url, { signal: controller.signal })
      .then((res) => {
        if (controller.signal.aborted) return;
        if (res.status === 'success') { setDetalle(res); setIdColor(res.colores?.find((c) => res.variantes.some((v) => v.id_color === c.id_color && Number(v.stock_variante) > 0))?.id_color ?? null); }
        else setError(res.message || 'Producto no encontrado');
      })
      .catch((error: unknown) => {
        if (!controller.signal.aborted && !isAbortError(error)) setError('API no disponible');
      });
    return () => controller.abort();
  }, [url]);

  useEffect(() => {
    if (!usuario || !detalle?.producto) { setFavorito(false); return; }
    const controller = new AbortController();
    void api.favoritos.listar({ signal: controller.signal }).then((res) => {
      if (!controller.signal.aborted && res.status === 'success') setFavorito(res.productos.some((p) => p.id_producto === detalle.producto.id_producto));
    }).catch(() => undefined);
    return () => controller.abort();
  }, [usuario?.id, usuario?.rol, sesionVersion, detalle]);

  if (error) {
    return (
      <div className="kl-rise mx-auto max-w-[560px] py-10 text-center">
        <p className="mb-6 text-red">{error}</p>
        <button
          type="button"
          onClick={() => navigate('/catalogo')}
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
  const colores = (detalle.colores ?? []).filter((c) => detalle.variantes.some((v) => Number(v.id_color) === Number(c.id_color) && Number(v.stock_variante) > 0));
  const colorActual = colores.find((c) => Number(c.id_color) === Number(idColor)) ?? colores[0];
  const disponibles = detalle.variantes.filter((v) => Number(v.id_color) === Number(colorActual?.id_color) && Number(v.stock_variante) > 0);
  const tallasDisponibles = TALLAS.filter((t) => disponibles.some((v) => v.talla_variante === t));
  const elegida = talla && tallasDisponibles.includes(talla) ? talla : tallasDisponibles.includes(recomendada) ? recomendada : tallasDisponibles[0];
  const cortesDisponibles = [...new Set(disponibles.filter((v) => v.talla_variante === elegida).map((v) => v.corte_variante))];
  const corteActual = corteElegido && cortesDisponibles.includes(corteElegido) ? corteElegido : cortesDisponibles.includes(corte) ? corte : cortesDisponibles[0];
  const variante = disponibles.find((v) => v.talla_variante === elegida && v.corte_variante === corteActual);
  const imagenes = detalle.imagenes.filter((i) => Number(i.id_color) === Number(colorActual?.id_color));
  const stockDisponible = variante
    ? Number(variante.stock_variante)
    : disponibles.reduce((total, v) => total + Number(v.stock_variante), 0);
  const cantidadDisponible = Math.max(0, Math.floor(stockDisponible));
  const cantidadSeleccionada = Math.min(cantidad, cantidadDisponible);

  const item: ItemBolsa = {
    id_variante: variante?.id_variante ?? 0,
    color: colorActual?.nombre_color ?? '',
    sku: variante?.sku_variante ?? '',
    id_producto: p.id_producto,
    url_producto: p.url_producto,
    name: p.nombre_producto,
    size: elegida ?? 'M',
    fit: corteActual ?? 'No aplica',
    price: precio,
    imagen: imagenes[0]?.url_imagen ?? null,
    cantidad: cantidadSeleccionada,
  };

  const añadir = async () => {
    if (!variante) { setErrorBolsa('Selecciona una combinación disponible'); return; }
    if (cantidadSeleccionada < 1 || cantidadSeleccionada > cantidadDisponible) {
      setErrorBolsa('La cantidad debe estar dentro del stock disponible');
      return;
    }
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
  };

  const alternarFavorito = async () => {
    if (!usuario) { navigate('/entrar'); return; }
    setFavoritoOcupado(true);
    try {
      const res = await api.favoritos.alternar(p.id_producto);
      if (res.status === 'success') setFavorito(Boolean(res.favorito));
      else setErrorBolsa(res.message || 'No se pudo guardar el favorito');
    } catch { setErrorBolsa('No hay conexión con la API'); }
    finally { setFavoritoOcupado(false); }
  };

  const specs = [
    { k: 'Código del producto', v: p.codigo_producto ?? '—' },
    { k: 'Categoría', v: p.nombre_categoria ?? '—' },
    { k: 'Color seleccionado', v: colorActual?.nombre_color ?? '—' },
    { k: 'Corte seleccionado', v: corteActual ?? '—' },
    { k: 'Cambio de talla', v: 'Sin coste durante 30 días' },
  ];

  const resumen = p.descripcion_producto
    ? p.descripcion_producto.slice(0, 155)
    : `${p.nombre_producto}. Talla sugerida para tus medidas: ${recomendada}.`;

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
        onClick={() => navigate('/catalogo')}
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
          <div className="mb-[10px] mt-4 flex items-start justify-between gap-3"><h2 className="font-display text-4xl font-normal leading-[1.06] tracking-[-0.025em]">{p.nombre_producto}</h2><button type="button" onClick={() => void alternarFavorito()} disabled={favoritoOcupado} aria-label={favorito ? 'Quitar de favoritos' : 'Guardar en favoritos'} title={favorito ? 'Quitar de favoritos' : 'Guardar en favoritos'} className="min-h-11 min-w-11 cursor-pointer border border-rule bg-transparent text-2xl text-red disabled:opacity-50">{favorito ? '♥' : '♡'}</button></div>
          <div className="mb-[18px] flex items-baseline gap-[14px]">
            <span className="font-display text-2xl">{money(precio)}</span>
            <span className="text-[13px] text-soft">{stockDisponible} {stockDisponible === 1 ? 'unidad disponible' : 'unidades disponibles'}</span>
          </div>
          {p.descripcion_producto && (
            <p className="mb-6 max-w-[48ch] text-[15px] leading-[1.65] text-body">{p.descripcion_producto}</p>
          )}

          {colores.length > 1 && <div className="mb-5"><div className="mb-2 font-narrow text-xs uppercase tracking-[0.12em] text-soft">Color</div><div className="flex flex-wrap gap-2">{colores.map((c) => <button key={c.id_color} type="button" onClick={() => { setIdColor(c.id_color); setTalla(null); setCorteElegido(null); setCantidad(1); setFoto(0); }} className="min-h-11 border border-ink px-4 text-sm" style={{ background: colorActual?.id_color === c.id_color ? 'var(--ink)' : 'transparent', color: colorActual?.id_color === c.id_color ? 'var(--paper)' : 'var(--ink)' }}>{c.nombre_color}</button>)}</div></div>}
          {colores.length === 1 && <p className="mb-5 text-sm text-soft">Color: {colores[0].nombre_color}</p>}

          <div className="flex items-baseline justify-between border-b border-ink pb-[10px]">
            <span className="font-narrow text-xs uppercase tracking-[0.12em] text-soft">Talla</span>
            <span className="text-[13px] text-body">
              {tienePerfil ? <>Sugerida para ti: <span className="font-semibold text-red">{recomendada}</span></> : <button type="button" onClick={() => navigate('/medidas')} className="cursor-pointer border-none bg-transparent text-red underline">Encuentra tu talla con tus medidas</button>}
            </span>
          </div>
          <div className="mb-[22px] grid grid-cols-6 border border-t-0 border-ink">
            {TALLAS.map((s, i) => (
              <button
                key={s}
                type="button"
                onClick={() => { setTalla(s); setCantidad(1); }}
                disabled={!tallasDisponibles.includes(s)}
                className="relative min-h-[52px] cursor-pointer border-none font-narrow text-[14.5px] font-semibold tracking-[0.06em] transition-colors"
                style={{
                  background: elegida === s ? 'var(--ink)' : 'transparent',
                  color: elegida === s ? 'var(--paper)' : 'var(--soft)',
                  opacity: tallasDisponibles.includes(s) ? 1 : 0.3,
                  borderRight: i === TALLAS.length - 1 ? 'none' : '1px solid var(--ink)',
                }}
              >
                {s}
                <span
                  className="absolute bottom-[5px] left-1/2 h-[3px] -translate-x-1/2 bg-red"
                  style={{ width: tienePerfil && s === recomendada ? '16px' : '0px' }}
                />
              </button>
            ))}
          </div>

          {cortesDisponibles.length > 1 && <div className="mb-5"><div className="mb-2 font-narrow text-xs uppercase tracking-[0.12em] text-soft">Corte</div><div className="flex flex-wrap gap-2">{cortesDisponibles.map((c) => <button key={c} type="button" onClick={() => { setCorteElegido(c); setCantidad(1); }} className="min-h-11 border border-ink px-4 text-sm" style={{ background: corteActual === c ? 'var(--ink)' : 'transparent', color: corteActual === c ? 'var(--paper)' : 'var(--ink)' }}>{c}</button>)}</div></div>}

          <div className="flex flex-col gap-2">
            <div className="flex items-center justify-between border-y border-rule py-3">
              <label htmlFor="cantidad-producto" className="font-narrow text-xs font-semibold uppercase tracking-[0.12em] text-soft">
                Cantidad
              </label>
              <div className="flex items-center border border-rule">
                <button
                  type="button"
                  onClick={() => setCantidad((actual) => Math.max(1, actual - 1))}
                  disabled={!variante || cantidadSeleccionada <= 1}
                  aria-label="Disminuir cantidad"
                  className="h-10 w-10 cursor-pointer border-none bg-transparent text-lg disabled:cursor-not-allowed disabled:opacity-40"
                >
                  −
                </button>
                <input
                  id="cantidad-producto"
                  type="number"
                  min={1}
                  max={cantidadDisponible}
                  value={cantidadSeleccionada}
                  disabled={!variante}
                  onChange={(event) => {
                    const value = Number(event.target.value);
                    if (Number.isInteger(value) && value >= 1 && value <= cantidadDisponible) {
                      setCantidad(value);
                    }
                  }}
                  aria-label={`Cantidad de ${p.nombre_producto}`}
                  className="h-10 w-12 border-x border-rule bg-transparent text-center text-sm disabled:opacity-50"
                />
                <button
                  type="button"
                  onClick={() => setCantidad((actual) => Math.min(cantidadDisponible, actual + 1))}
                  disabled={!variante || cantidadSeleccionada >= cantidadDisponible}
                  aria-label="Aumentar cantidad"
                  className="h-10 w-10 cursor-pointer border-none bg-transparent text-lg disabled:cursor-not-allowed disabled:opacity-40"
                >
                  +
                </button>
              </div>
            </div>
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
              disabled={añadiendo || !variante || cantidadSeleccionada < 1}
              className="min-h-[56px] cursor-pointer border border-ink bg-transparent font-narrow text-[15px] font-semibold uppercase tracking-[0.08em] text-ink hover:bg-hover disabled:opacity-50"
            >
              {añadiendo ? 'Añadiendo…' : 'Añadir a la bolsa'}
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
