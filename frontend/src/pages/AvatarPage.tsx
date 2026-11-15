import { useEffect, useMemo, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { AvatarScene3D, detectarCategoria3D, tipoDeCategoria, type GeneroAvatar } from '../components/AvatarScene3D';
import { Seo } from '../components/Seo';
import {
  COLOR_ESTADO,
  COPY_CORTE,
  CORTES,
  ETIQUETA_ESTADO,
  GUIA_TALLAS,
  RANGOS_MEDIDAS,
  TALLAS,
  analizarAjuste,
  confianza,
  etiquetaZona,
  formatoHolgura,
  money,
  porQueEstaTalla,
  tallaRecomendada,
  type Medidas,
  type Talla,
} from '../lib/fit';
import { api, isAbortError, type ApiDetalle, type ApiProducto } from '../services/api';
import { useAuth, useCartActions, useProfile, useTheme, type ItemBolsa } from '../store/KlosetContext';

export function AvatarPage() {
  const navigate = useNavigate();
  const [params, setSearchParams] = useSearchParams();
  const slugParam = params.get('producto') ?? '';

  const { usuario, setIntencion } = useAuth();
  const { añadirABolsa } = useCartActions();
  const { medidas, corte, setCorte, guardarPerfil } = useProfile();
  const { tema } = useTheme();

  const [catalogo, setCatalogo] = useState<ApiProducto[]>([]);
  const [slugActivo, setSlugActivo] = useState<string>(slugParam);
  const [detalle, setDetalle] = useState<ApiDetalle | null>(null);
  const [cargandoProducto, setCargandoProducto] = useState<boolean>(false);
  const [errorProducto, setErrorProducto] = useState<string>('');

  const [genero, setGeneroState] = useState<GeneroAvatar>(() => {
    try {
      const guardado = localStorage.getItem('kloset-genero-avatar');
      if (guardado === 'masculino' || guardado === 'femenino') return guardado;
    } catch {
      /* ignorar */
    }
    return 'masculino';
  });
  const [generoManual, setGeneroManual] = useState<boolean>(() => {
    try {
      const guardado = localStorage.getItem('kloset-genero-avatar');
      return guardado === 'masculino' || guardado === 'femenino';
    } catch {
      return false;
    }
  });

  const cambiarGenero = (nuevo: GeneroAvatar) => {
    setGeneroState(nuevo);
    setGeneroManual(true);
    try {
      localStorage.setItem('kloset-genero-avatar', nuevo);
    } catch {
      /* ignorar */
    }
  };

  const [idColor, setIdColor] = useState<number | null>(null);
  const [medidasPrueba, setMedidasPrueba] = useState<Medidas>(medidas);
  const [tallaElegida, setTallaElegida] = useState<Talla>(() => tallaRecomendada(medidas, corte));

  const [guardandoMedidas, setGuardandoMedidas] = useState<boolean>(false);
  const [mensajeMedidas, setMensajeMedidas] = useState<string>('');
  const [añadiendoBolsa, setAñadiendoBolsa] = useState<boolean>(false);
  const [mensajeBolsa, setMensajeBolsa] = useState<string>('');
  const [errorBolsa, setErrorBolsa] = useState<string>('');

  // Sincroniza cambios externos en el perfil corporal
  useEffect(() => {
    setMedidasPrueba(medidas);
  }, [medidas]);

  // Sincroniza query param ?producto=
  useEffect(() => {
    if (slugParam && slugParam !== slugActivo) {
      setSlugActivo(slugParam);
    }
  }, [slugParam, slugActivo]);

  // Carga el listado de productos para el selector rápido (y fallback si entran a /avatar sin ?producto=)
  useEffect(() => {
    const controller = new AbortController();
    api
      .productos({ signal: controller.signal })
      .then((res) => {
        if (controller.signal.aborted) return;
        if (res.status === 'success' && Array.isArray(res.productos)) {
          setCatalogo(res.productos);
          if (!slugParam && res.productos.length > 0) {
            const primero = res.productos[0].url_producto;
            setSlugActivo(primero);
            setSearchParams({ producto: primero }, { replace: true });
          }
        }
      })
      .catch(() => undefined);
    return () => controller.abort();
  }, [slugParam, setSearchParams]);

  // Carga el detalle completo del producto activo (colores, imágenes, variantes)
  useEffect(() => {
    if (!slugActivo) {
      setDetalle(null);
      return;
    }
    const controller = new AbortController();
    setCargandoProducto(true);
    setErrorProducto('');
    setMensajeBolsa('');
    setErrorBolsa('');

    api
      .producto(slugActivo, { signal: controller.signal })
      .then((res) => {
        if (controller.signal.aborted) return;
        if (res.status === 'success' && res.producto) {
          setDetalle(res);
          const colorConStock =
            res.colores?.find((c) =>
              res.variantes.some((v) => Number(v.id_color) === Number(c.id_color) && Number(v.stock_variante) > 0),
            )?.id_color ??
            res.colores?.[0]?.id_color ??
            null;
          setIdColor(colorConStock);

          // Si el usuario aún no fijó manualmente el cuerpo base, sugerimos silueta femenina en Tops y Mallas
          if (!generoManual) {
            const cat = detectarCategoria3D(res.producto.nombre_categoria, res.producto.nombre_producto);
            if (cat === 'tops' || cat === 'mallas') {
              setGeneroState('femenino');
            } else {
              setGeneroState('masculino');
            }
          }
        } else {
          setDetalle(null);
          setErrorProducto(res.message || 'No se encontró la prenda solicitada.');
        }
      })
      .catch((err: unknown) => {
        if (!controller.signal.aborted && !isAbortError(err)) {
          setErrorProducto('No pudimos cargar el detalle de la prenda.');
        }
      })
      .finally(() => {
        if (!controller.signal.aborted) setCargandoProducto(false);
      });

    return () => controller.abort();
  }, [slugActivo, generoManual]);

  const producto = detalle?.producto ?? null;
  const categoria = detectarCategoria3D(producto?.nombre_categoria ?? null, producto?.nombre_producto ?? '');
  const tipo = tipoDeCategoria(categoria);
  const tallaRec = tallaRecomendada(medidasPrueba, corte, tipo);

  // Actualiza automáticamente la talla probada cuando cambian las medidas o el corte
  useEffect(() => {
    setTallaElegida(tallaRec);
  }, [tallaRec]);

  const coloresDisponibles = (detalle?.colores ?? []).filter((c) =>
    detalle?.variantes.some((v) => Number(v.id_color) === Number(c.id_color) && Number(v.stock_variante) > 0),
  );
  const listaColores = coloresDisponibles.length > 0 ? coloresDisponibles : (detalle?.colores ?? []);
  const colorActual = listaColores.find((c) => Number(c.id_color) === Number(idColor)) ?? listaColores[0] ?? null;

  const variantesColor = (detalle?.variantes ?? []).filter(
    (v) => !colorActual || Number(v.id_color) === Number(colorActual.id_color),
  );
  const variantesConStock = variantesColor.filter((v) => Number(v.stock_variante) > 0);

  // Busca variante exacta para (colorActual, tallaElegida, corte) o la mejor coincidencia en esa talla
  const varianteActiva =
    variantesConStock.find((v) => v.talla_variante === tallaElegida && v.corte_variante === corte) ??
    variantesConStock.find((v) => v.talla_variante === tallaElegida) ??
    null;

  const imagenesColor = (detalle?.imagenes ?? []).filter(
    (img) => !colorActual || Number(img.id_color) === Number(colorActual.id_color),
  );
  const imagenPrincipal =
    imagenesColor[0]?.url_imagen ?? detalle?.imagenes?.[0]?.url_imagen ?? producto?.url_imagen ?? null;

  const diferencialTalla = TALLAS.indexOf(tallaElegida) - TALLAS.indexOf(tallaRec);
  const { h, chest, waist, hip } = medidasPrueba;
  const analisis = useMemo(
    () => analizarAjuste({ h, chest, waist, hip }, tallaElegida, corte, tipo),
    [h, chest, waist, hip, tallaElegida, corte, tipo],
  );
  const veredictoPorTalla = TALLAS.map((t) => analizarAjuste(medidasPrueba, t, corte, tipo).veredicto);

  const seleccionarProducto = (nuevoSlug: string) => {
    setSlugActivo(nuevoSlug);
    setSearchParams({ producto: nuevoSlug });
  };

  const handleGuardarMedidas = async () => {
    setGuardandoMedidas(true);
    setMensajeMedidas('');
    const fallo = await guardarPerfil(medidasPrueba);
    setGuardandoMedidas(false);
    const recomendadaAhora = tallaRecomendada(medidasPrueba, corte, tipo);
    setTallaElegida(recomendadaAhora);
    if (fallo) {
      setMensajeMedidas(fallo);
    } else {
      setMensajeMedidas(`Medidas actualizadas · Talla recomendada: ${recomendadaAhora}`);
    }
  };

  const handleAñadirABolsa = async () => {
    if (!producto) return;
    if (!varianteActiva) {
      setErrorBolsa(`La talla ${tallaElegida} en corte ${corte} no tiene stock disponible.`);
      return;
    }
    const item: ItemBolsa = {
      id_variante: varianteActiva.id_variante,
      color: colorActual?.nombre_color ?? varianteActiva.nombre_color ?? 'Original',
      sku: varianteActiva.sku_variante,
      id_producto: producto.id_producto,
      url_producto: producto.url_producto,
      name: producto.nombre_producto,
      size: tallaElegida,
      fit: varianteActiva.corte_variante || corte,
      price: Number(producto.precio_producto),
      imagen: imagenPrincipal,
      cantidad: 1,
    };

    if (!usuario) {
      setIntencion({ then: 'cart', item });
      navigate('/entrar');
      return;
    }

    setErrorBolsa('');
    setMensajeBolsa('');
    setAñadiendoBolsa(true);
    const fallo = await añadirABolsa(item);
    setAñadiendoBolsa(false);
    if (fallo) {
      setErrorBolsa(fallo);
      return;
    }
    setMensajeBolsa(`Añadido a tu bolsa: ${producto.nombre_producto} · Talla ${tallaElegida} (${corte})`);
  };

  const etiquetaCampo = 'font-narrow text-xs font-semibold uppercase tracking-[0.12em] text-soft';

  return (
    <div className="kl-rise mx-auto max-w-[1160px]">
      <Seo
        title={producto ? `Probador 3D · ${producto.nombre_producto}` : 'Probador y Avatar 3D'}
        description="Prueba cada prenda de Kloset sobre un maniquí 3D con tu estatura, pecho, cintura y cadera, y mira cuántos centímetros de holgura deja cada talla."
        path="/avatar"
        noindex
      />

      {/* Navegación y selector de prenda */}
      <div className="mb-5 flex flex-wrap items-center justify-between gap-3 border-b border-rule pb-3">
        <button
          type="button"
          onClick={() => navigate(producto ? `/producto/${producto.url_producto}` : '/catalogo')}
          className="min-h-[40px] cursor-pointer border-none bg-transparent font-narrow text-[13px] uppercase tracking-[0.08em] text-soft hover:text-ink"
        >
          ← {producto ? `Volver a ${producto.nombre_producto}` : 'Volver al catálogo'}
        </button>

        {catalogo.length > 0 && (
          <div className="flex flex-wrap items-center gap-2">
            <label htmlFor="selector-prenda-avatar" className="font-narrow text-[11px] font-semibold uppercase tracking-[0.12em] text-soft">
              Prenda en probador:
            </label>
            <select
              id="selector-prenda-avatar"
              value={slugActivo}
              onChange={(e) => seleccionarProducto(e.target.value)}
              className="min-h-[38px] max-w-full cursor-pointer border border-ink bg-paper px-3 font-narrow text-xs font-semibold uppercase tracking-[0.06em] text-ink"
            >
              {catalogo.map((item) => (
                <option key={item.id_producto} value={item.url_producto}>
                  {item.nombre_producto} ({item.nombre_categoria ?? 'Kloset'})
                </option>
              ))}
            </select>
          </div>
        )}
      </div>

      {/* Prenda activa */}
      <div className="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div className="flex min-w-0 items-center gap-4">
          {imagenPrincipal && (
            <img src={imagenPrincipal} alt="" width={64} height={80} className="h-20 w-16 shrink-0 border border-rule object-cover" />
          )}
          <div className="min-w-0">
            <p className="mb-1.5 font-narrow text-xs font-semibold uppercase tracking-[0.14em] text-red">
              Probador 3D · {producto?.nombre_categoria ?? 'Colección técnica'}
            </p>
            <h1 className="font-display text-3xl font-normal leading-[1.08] tracking-[-0.02em] sm:text-4xl">
              {producto ? producto.nombre_producto : 'Probador de talla'}
            </h1>
            <p className="mt-1.5 max-w-[60ch] text-sm text-body">
              El maniquí tiene tus medidas. Cambia de talla y mira dónde aprieta o sobra, en centímetros.
            </p>
          </div>
        </div>

        {producto && (
          <div className="text-left sm:text-right">
            <div className="font-display text-2xl text-ink">{money(Number(producto.precio_producto))}</div>
            <div className="font-narrow text-[11px] uppercase tracking-[0.08em] text-soft">
              Confianza en la talla {tallaElegida}:{' '}
              <strong className="text-ink">{confianza(medidasPrueba, tallaElegida, corte, tipo)}%</strong>
            </div>
          </div>
        )}
      </div>

      {errorProducto && (
        <div className="mb-5 border-l-[3px] border-red bg-surface px-4 py-3 text-sm text-red">{errorProducto}</div>
      )}

      <div className="grid gap-8 lg:grid-cols-12 lg:items-start">
        {/* ==================== MANIQUÍ 3D ==================== */}
        <section className="lg:sticky lg:top-[82px] lg:col-span-6">
          <AvatarScene3D
            genero={genero}
            medidas={medidasPrueba}
            corte={corte}
            analisis={analisis}
            deltaTalla={diferencialTalla}
            categoria={categoria}
            nombreProducto={producto?.nombre_producto ?? 'Prenda Kloset'}
            colorNombre={colorActual?.nombre_color ?? 'Original'}
            tema={tema}
          />
        </section>

        {/* ==================== AJUSTE Y COMPRA ==================== */}
        <section className="lg:col-span-6">
          {/* 1. Talla, ajuste por zona y compra */}
          <div className="border border-rule bg-paper p-5">
            <div className="mb-2 flex flex-wrap items-baseline justify-between gap-2">
              <span className={etiquetaCampo}>Talla en el maniquí</span>
              {tallaElegida !== tallaRec && (
                <button
                  type="button"
                  onClick={() => setTallaElegida(tallaRec)}
                  className="cursor-pointer border-none bg-transparent p-0 text-xs font-semibold text-red underline"
                >
                  Volver a tu talla ({tallaRec})
                </button>
              )}
            </div>

            <div className="grid grid-cols-6 border border-ink">
              {TALLAS.map((t, i) => {
                const tieneStock = !detalle || variantesConStock.some((v) => v.talla_variante === t);
                const activa = tallaElegida === t;
                const estado = veredictoPorTalla[i];
                return (
                  <button
                    key={t}
                    type="button"
                    aria-pressed={activa}
                    aria-label={`Talla ${t}: ${ETIQUETA_ESTADO[estado]}${tallaRec === t ? ', tu talla' : ''}${tieneStock ? '' : ', sin stock'}`}
                    onClick={() => setTallaElegida(t)}
                    className="flex min-h-[58px] cursor-pointer flex-col items-center justify-center gap-1 border-none transition-colors"
                    style={{
                      background: activa ? 'var(--ink)' : 'transparent',
                      color: activa ? 'var(--paper)' : 'var(--ink)',
                      opacity: tieneStock ? 1 : 0.45,
                      borderRight: i < TALLAS.length - 1 ? '1px solid var(--ink)' : 'none',
                    }}
                  >
                    <span className="font-narrow text-sm font-semibold tracking-[0.06em]">{t}</span>
                    <span className="flex items-center gap-1 font-narrow text-[9.5px] uppercase tracking-[0.04em]">
                      <span className="inline-block h-1.5 w-1.5 rounded-full" style={{ background: COLOR_ESTADO[estado] }} />
                      {tallaRec === t ? 'Tu talla' : ETIQUETA_ESTADO[estado]}
                    </span>
                  </button>
                );
              })}
            </div>

            {/* Ajuste por zona de la talla elegida */}
            <table className="mt-4 w-full border-collapse text-left text-[13px]">
              <caption className="sr-only">Holgura de la talla {tallaElegida} por zona, en centímetros</caption>
              <thead>
                <tr className="border-b border-ink font-narrow text-[10.5px] uppercase tracking-[0.1em] text-soft">
                  <th className="py-1.5 pr-2 font-semibold">Zona</th>
                  <th className="px-2 py-1.5 text-right font-semibold">Tú</th>
                  <th className="px-2 py-1.5 text-right font-semibold">Prenda</th>
                  <th className="px-2 py-1.5 text-right font-semibold">Holgura</th>
                  <th className="py-1.5 pl-2 font-semibold">Ajuste</th>
                </tr>
              </thead>
              <tbody>
                {analisis.zonas.map((z) => (
                  <tr key={z.clave} className="border-b border-rule">
                    <th scope="row" className="py-2.5 pr-2 font-display text-[15px] font-normal text-ink">
                      {z.zona}
                    </th>
                    <td className="px-2 py-2.5 text-right tabular-nums text-body">{z.cuerpoCm}</td>
                    <td className="px-2 py-2.5 text-right tabular-nums text-body">{z.prendaCm}</td>
                    <td className="px-2 py-2.5 text-right font-semibold tabular-nums text-ink">{formatoHolgura(z.holguraCm)}</td>
                    <td className="py-2.5 pl-2">
                      <span className="flex items-center gap-1.5 font-narrow text-[11px] font-semibold uppercase tracking-[0.06em] text-ink">
                        <span className="inline-block h-2.5 w-2.5 shrink-0" style={{ background: COLOR_ESTADO[z.estado] }} />
                        {etiquetaZona(z)}
                      </span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>

            <p className="mt-3 text-xs leading-relaxed text-body">{porQueEstaTalla(medidasPrueba, corte, tallaElegida, tipo)}</p>

            {producto && (
              <div className="mt-4 flex flex-col gap-2 sm:flex-row">
                <button
                  type="button"
                  onClick={() => void handleAñadirABolsa()}
                  disabled={añadiendoBolsa || cargandoProducto || !varianteActiva}
                  className="min-h-[52px] flex-1 cursor-pointer border-none bg-ink px-5 font-narrow text-sm font-semibold uppercase tracking-[0.08em] text-paper hover:bg-red hover:text-[#F2F2F0] disabled:cursor-not-allowed disabled:opacity-50"
                >
                  {añadiendoBolsa
                    ? 'Añadiendo…'
                    : varianteActiva
                      ? `Añadir a la bolsa · Talla ${tallaElegida}`
                      : `Sin stock en ${tallaElegida}`}
                </button>
                <button
                  type="button"
                  onClick={() => navigate(`/producto/${producto.url_producto}`)}
                  className="min-h-[52px] cursor-pointer border border-ink bg-transparent px-5 font-narrow text-sm font-semibold uppercase tracking-[0.08em] text-ink hover:bg-hover"
                >
                  Ver ficha completa
                </button>
              </div>
            )}

            {mensajeBolsa && (
              <div className="mt-3 border-l-[3px] border-ink bg-surface px-3 py-2 font-narrow text-xs uppercase tracking-[0.06em] text-ink">
                {mensajeBolsa} ·{' '}
                <button
                  type="button"
                  onClick={() => navigate('/bolsa')}
                  className="cursor-pointer border-none bg-transparent p-0 font-semibold text-red underline"
                >
                  Ver bolsa
                </button>
              </div>
            )}
            {errorBolsa && (
              <div className="mt-3 border-l-[3px] border-red bg-surface px-3 py-2 font-narrow text-xs uppercase tracking-[0.06em] text-red">
                {errorBolsa}
              </div>
            )}
          </div>

          {/* 2. Corte, color y silueta */}
          <div className="mt-5 border border-rule bg-paper p-5">
            <div className="mb-5">
              <div className={`mb-2 ${etiquetaCampo}`}>Corte</div>
              <div className="grid grid-cols-3 border border-ink">
                {CORTES.map((c, i) => (
                  <button
                    key={c}
                    type="button"
                    aria-pressed={corte === c}
                    onClick={() => setCorte(c)}
                    className="min-h-[44px] cursor-pointer border-none font-narrow text-xs font-semibold uppercase tracking-[0.08em] transition-colors"
                    style={{
                      background: corte === c ? 'var(--ink)' : 'transparent',
                      color: corte === c ? 'var(--paper)' : 'var(--ink)',
                      borderRight: i < CORTES.length - 1 ? '1px solid var(--ink)' : 'none',
                    }}
                  >
                    {c}
                  </button>
                ))}
              </div>
              <p className="mt-2.5 text-xs leading-relaxed text-body">{COPY_CORTE[corte]}</p>
            </div>

            {listaColores.length > 0 && (
              <div className="mb-5">
                <div className="mb-2 flex items-baseline justify-between">
                  <span className={etiquetaCampo}>Color</span>
                  <span className="text-xs text-ink">{colorActual?.nombre_color ?? 'Original'}</span>
                </div>
                <div className="flex flex-wrap gap-2">
                  {listaColores.map((c) => {
                    const activo = Number(colorActual?.id_color) === Number(c.id_color);
                    return (
                      <button
                        key={c.id_color}
                        type="button"
                        aria-pressed={activo}
                        onClick={() => setIdColor(c.id_color)}
                        className="min-h-[38px] cursor-pointer border px-3.5 font-narrow text-xs font-semibold uppercase tracking-[0.08em] transition-colors"
                        style={{
                          borderColor: activo ? 'var(--ink)' : 'var(--rule)',
                          background: activo ? 'var(--ink)' : 'transparent',
                          color: activo ? 'var(--paper)' : 'var(--ink)',
                        }}
                      >
                        {c.nombre_color}
                      </button>
                    );
                  })}
                </div>
              </div>
            )}

            <div>
              <div className={`mb-2 ${etiquetaCampo}`}>Silueta del maniquí</div>
              <div className="grid grid-cols-2 border border-ink">
                {(['masculino', 'femenino'] as const).map((g, idx) => (
                  <button
                    key={g}
                    type="button"
                    aria-pressed={genero === g}
                    onClick={() => cambiarGenero(g)}
                    className="min-h-[42px] cursor-pointer border-none font-narrow text-xs font-semibold uppercase tracking-[0.08em] transition-colors"
                    style={{
                      background: genero === g ? 'var(--ink)' : 'transparent',
                      color: genero === g ? 'var(--paper)' : 'var(--ink)',
                      borderRight: idx === 0 ? '1px solid var(--ink)' : 'none',
                    }}
                  >
                    {g === 'masculino' ? 'Hombre' : 'Mujer'}
                  </button>
                ))}
              </div>
            </div>
          </div>

          {/* 3. Medidas en vivo */}
          <div className="mt-5 border border-rule bg-paper p-5">
            <div className="mb-3 flex flex-wrap items-baseline justify-between gap-2 border-b border-ink pb-2.5">
              <span className="font-narrow text-xs font-semibold uppercase tracking-[0.12em] text-ink">Tus medidas</span>
              <span className="text-xs text-soft">El maniquí cambia mientras mueves cada control</span>
            </div>

            <div className="divide-y divide-rule">
              {RANGOS_MEDIDAS.map((campo) => (
                <label key={campo.key} className="block py-3">
                  <span className="flex items-baseline justify-between gap-2">
                    <span>
                      <span className="font-display text-base text-ink">{campo.label}</span>
                      <span className="ml-2 text-xs text-soft">{campo.hint}</span>
                    </span>
                    <span className="font-display text-xl text-ink">
                      {medidasPrueba[campo.key]} <small className="text-xs text-soft">cm</small>
                    </span>
                  </span>
                  <input
                    type="range"
                    min={campo.min}
                    max={campo.max}
                    value={medidasPrueba[campo.key]}
                    onChange={(e) => setMedidasPrueba({ ...medidasPrueba, [campo.key]: Number(e.target.value) })}
                    aria-label={`${campo.label}, ${medidasPrueba[campo.key]} centímetros`}
                    className="mt-1.5 h-6 w-full cursor-pointer"
                  />
                </label>
              ))}
            </div>

            <button
              type="button"
              onClick={() => void handleGuardarMedidas()}
              disabled={guardandoMedidas}
              className="mt-3 min-h-[48px] w-full cursor-pointer border border-ink bg-surface font-narrow text-xs font-semibold uppercase tracking-[0.08em] text-ink hover:bg-ink hover:text-paper disabled:opacity-50"
            >
              {guardandoMedidas ? 'Guardando medidas…' : 'Guardar mis medidas'}
            </button>

            {mensajeMedidas && (
              <p className="mt-2.5 text-center font-narrow text-xs uppercase tracking-[0.06em] text-red">{mensajeMedidas}</p>
            )}
          </div>

          {/* 4. Guía de tallas */}
          <details className="mt-5 border border-rule bg-paper">
            <summary className={`cursor-pointer px-5 py-4 ${etiquetaCampo}`}>Guía de tallas (medidas de cuerpo, cm)</summary>
            <div className="overflow-x-auto px-5 pb-5">
              <table className="w-full border-collapse text-left text-xs">
                <thead>
                  <tr className="border-b border-ink font-narrow uppercase tracking-[0.08em] text-soft">
                    <th className="py-2 pr-2">Talla</th>
                    <th className="p-2">Pecho</th>
                    <th className="p-2">Cintura</th>
                    <th className="p-2">Cadera</th>
                    <th className="py-2 pl-2">Estatura</th>
                  </tr>
                </thead>
                <tbody>
                  {TALLAS.map((t) => {
                    const fila = GUIA_TALLAS[t];
                    const activa = t === tallaElegida;
                    return (
                      <tr
                        key={t}
                        className="border-b border-rule"
                        style={{ background: activa ? 'var(--surface)' : 'transparent', fontWeight: activa ? 600 : 400 }}
                      >
                        <td className="py-2 pr-2 font-narrow">
                          <span className={activa ? 'text-red' : 'text-ink'}>{t}</span>
                          {t === tallaRec && (
                            <span className="ml-1.5 bg-red px-1.5 py-0.5 text-[9px] uppercase text-[#F2F2F0]">Tu talla</span>
                          )}
                        </td>
                        <td className="p-2">{fila.pecho.join(' – ')}</td>
                        <td className="p-2">{fila.cintura.join(' – ')}</td>
                        <td className="p-2">{fila.cadera.join(' – ')}</td>
                        <td className="py-2 pl-2">{fila.estatura.join(' – ')}</td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          </details>
        </section>
      </div>
    </div>
  );
}
