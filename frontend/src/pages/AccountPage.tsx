import { useEffect, useState, type FormEvent } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { Seo } from '../components/Seo';
import { AddressForm } from '../components/AddressForm';
import { api, type ApiDatosCuenta, type ApiDireccion, type ApiFavorito } from '../services/api';
import { useKloset, type Pedido } from '../store/KlosetContext';
import { money, tallaRecomendada } from '../lib/fit';
import { cumplePoliticaContrasena, passwordRequirements } from '../lib/password';
import { nombreCortoDepartamento } from '../lib/locations';
import { CartPage } from './CartPage';

const tabs = [
  { id: 'resumen', texto: 'Resumen' }, { id: 'favoritos', texto: 'Favoritos' },
  { id: 'bolsa', texto: 'Bolsa' }, { id: 'pedidos', texto: 'Pedidos' },
  { id: 'datos', texto: 'Datos' },
] as const;
type Tab = (typeof tabs)[number]['id'];
const nombresEstado: Record<string, string> = { pendiente_pago: 'Pendiente', pagado: 'Registrado', en_preparacion: 'En preparación', enviado: 'En camino', en_reparto: 'En reparto', entregado: 'Entregado', cancelado: 'Cancelado' };

function fechaEntrega(pedido: Pedido): string {
  const base = new Date((pedido.fechaRaw || '').replace(' ', 'T'));
  const estimada = pedido.entregaEstimada
    ? new Date(`${pedido.entregaEstimada}T12:00:00`)
    : new Date(base.getTime() + 3 * 86400000);
  return Number.isNaN(estimada.getTime())
    ? 'fecha por confirmar'
    : estimada.toLocaleDateString('es-PE', { weekday: 'long', day: 'numeric', month: 'long' });
}

export function AccountPage() {
  const navigate = useNavigate();
  const [params, setParams] = useSearchParams();
  const { usuario, cargandoSesion, bolsa, pedidos, medidas, tienePerfil, corte, borrarPerfil, actualizarNombre, salir } = useKloset();
  const active: Tab = tabs.find((t) => t.id === params.get('tab'))?.id ?? 'resumen';
  const [favoritos, setFavoritos] = useState<ApiFavorito[]>([]);
  const [direcciones, setDirecciones] = useState<ApiDireccion[]>([]);
  const [datos, setDatos] = useState<ApiDatosCuenta | null>(null);
  const [avisos, setAvisos] = useState<{ id_notificacion: number; id_pedido: number | null; mensaje_notificacion: string; fecha_creacion: string }[]>([]);
  const [nombre, setNombre] = useState('');
  const [telefono, setTelefono] = useState('');
  const [editandoTelefono, setEditandoTelefono] = useState(false);
  const [avisosPedidos, setAvisosPedidos] = useState(true);
  const [avisosNovedades, setAvisosNovedades] = useState(false);
  const [editorDireccion, setEditorDireccion] = useState<ApiDireccion | 'nuevo' | null>(null);
  const [claveAbierta, setClaveAbierta] = useState(false);
  const [claveActual, setClaveActual] = useState('');
  const [claveNueva, setClaveNueva] = useState('');
  const [error, setError] = useState('');
  const [mensaje, setMensaje] = useState('');
  const [ocupado, setOcupado] = useState(false);

  useEffect(() => { if (!cargandoSesion && !usuario) navigate('/entrar', { replace: true }); }, [cargandoSesion, usuario, navigate]);
  useEffect(() => {
    if (!usuario) return;
    void api.favoritos.listar().then((r) => { if (r.status === 'success') setFavoritos(r.productos); }).catch(() => undefined);
    void api.direcciones.listar().then((r) => { if (r.status === 'success') setDirecciones(r.direcciones); }).catch(() => undefined);
    void api.cuenta.datos().then((r) => {
      if (r.status !== 'success' || !r.datos) return;
      setDatos(r.datos); setNombre(r.datos.nombre); setTelefono(r.datos.telefono ?? '');
      setAvisosPedidos(Boolean(r.datos.avisos_pedidos)); setAvisosNovedades(Boolean(r.datos.avisos_novedades));
    }).catch(() => undefined);
    void api.cuenta.avisos().then((r) => { if (r.status === 'success') setAvisos(r.avisos); }).catch(() => undefined);
  }, [usuario]);

  if (!usuario) return null;
  const tab = (value: Tab) => { setError(''); setMensaje(''); setParams(value === 'resumen' ? {} : { tab: value }); };
  const totalBolsa = bolsa.reduce((sum, item) => sum + item.price * item.cantidad, 0);
  const totalPrendas = bolsa.reduce((sum, item) => sum + item.cantidad, 0);
  const actual = pedidos[0];
  const etapaPedido = actual ? ({ pendiente_pago: 'Pendiente de pago', pagado: 'Pagado', en_preparacion: 'Preparación', enviado: 'En camino', en_reparto: 'En reparto', entregado: 'Entregado', cancelado: 'Cancelado' } as Record<string, string>)[actual.estado] ?? actual.estado : 'Pagado';

  const guardarDatos = async (event: FormEvent) => {
    event.preventDefault(); setError(''); setMensaje(''); setOcupado(true);
    try {
      const res = await api.cuenta.guardar({ nombre: nombre.trim(), telefono: telefono.trim(), avisos_pedidos: avisosPedidos, avisos_novedades: avisosNovedades });
      if (res.status !== 'success' || !res.datos) return setError(res.message || 'No se pudieron guardar los datos.');
      setDatos(res.datos); actualizarNombre(res.datos.nombre); setEditandoTelefono(false); setMensaje('Cambios guardados.');
    } catch { setError('No hay conexión con la API.'); } finally { setOcupado(false); }
  };
  const guardarClave = async (event: FormEvent) => {
    event.preventDefault(); setError(''); setMensaje('');
    if (!cumplePoliticaContrasena(claveNueva)) {
      return setError(`La contraseña debe tener ${passwordRequirements}`);
    }
    setOcupado(true);
    try {
      const res = await api.cuenta.clave(claveActual, claveNueva);
      if (res.status !== 'success') return setError(res.message || 'No se pudo actualizar la contraseña.');
      setClaveActual(''); setClaveNueva(''); setClaveAbierta(false); setMensaje('Contraseña actualizada.');
    } catch { setError('No hay conexión con la API.'); } finally { setOcupado(false); }
  };
  const quitarFavorito = async (id: number) => {
    setError('');
    try {
      const res = await api.favoritos.alternar(id);
      if (res.status !== 'success') return setError(res.message || 'No se pudo cambiar el favorito.');
      setFavoritos((lista) => lista.filter((p) => p.id_producto !== id));
    } catch { setError('No hay conexión con la API.'); }
  };
  const eliminarDireccion = async (id: number) => {
    if (!window.confirm('¿Quitar esta dirección de tu cuenta? Los pedidos anteriores conservarán su dirección de entrega.')) return;
    setError('');
    try {
      const res = await api.direcciones.eliminar(id);
      if (res.status !== 'success' || !res.direcciones) return setError(res.message || 'No se pudo quitar la dirección.');
      setDirecciones(res.direcciones);
    } catch { setError('No hay conexión con la API.'); }
  };
  const descargarMedidas = () => {
    const blob = new Blob([JSON.stringify({ estatura_cm: medidas.h, pecho_cm: medidas.chest, cintura_cm: medidas.waist, cadera_cm: medidas.hip, corte, talla_recomendada: tallaRecomendada(medidas, corte) }, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob); const a = document.createElement('a'); a.href = url; a.download = 'kloset-mis-medidas.json'; a.click(); URL.revokeObjectURL(url);
  };
  const eliminarMedidas = async () => {
    if (!window.confirm('¿Borrar tus medidas guardadas? Se perderá tu recomendación de talla hasta que vuelvas a registrarlas.')) return;
    const fallo = await borrarPerfil();
    if (fallo) setError(fallo); else setMensaje('Tus medidas se borraron de la cuenta y de este navegador.');
  };

  return <div className="kl-rise mx-auto max-w-[1200px]">
    <Seo title="Mi cuenta" description="Gestiona tus favoritos, direcciones, bolsa, medidas y pedidos en Kloset." path="/cuenta" noindex />
    <div className="mb-6 flex flex-wrap items-center justify-between gap-4 border-b border-ink pb-6">
      <div className="flex items-center gap-4"><div className="flex h-16 w-16 items-center justify-center bg-ink font-display text-4xl text-paper">{usuario.nombre.charAt(0).toUpperCase()}</div><div><p className="mb-1 font-narrow text-xs uppercase tracking-[0.1em] text-soft">Mi cuenta</p><h1 className="font-display text-[34px] leading-none">{usuario.nombre}</h1><p className="mt-2 text-sm text-soft">{usuario.correo}</p></div></div>
      <Link to="/medidas" className="inline-flex min-h-12 items-center bg-ink px-5 font-narrow text-xs font-semibold uppercase tracking-[0.08em] text-paper">{tienePerfil ? 'Actualizar mis medidas' : 'Crear perfil de medidas'}</Link>
    </div>
    <nav aria-label="Secciones de mi cuenta" className="no-scrollbar mb-7 flex gap-6 overflow-x-auto border-b border-ink">
      {tabs.map((t) => <button key={t.id} type="button" onClick={() => tab(t.id)} className="min-h-12 shrink-0 cursor-pointer border-0 border-b-[3px] bg-transparent px-0 font-narrow text-xs font-semibold uppercase tracking-[0.08em]" style={{ borderColor: active === t.id ? 'var(--red)' : 'transparent', color: active === t.id ? 'var(--ink)' : 'var(--soft)' }}>{t.texto}{t.id === 'favoritos' ? ` ${favoritos.length}` : t.id === 'bolsa' ? ` ${totalPrendas}` : t.id === 'pedidos' ? ` ${pedidos.length}` : ''}</button>)}
    </nav>
    {error && <p role="alert" className="mb-5 border-l-[3px] border-red bg-surface px-4 py-3 text-sm text-red">{error}</p>}
    {mensaje && <p role="status" className="mb-5 border-l-[3px] border-red bg-surface px-4 py-3 text-sm text-body">{mensaje}</p>}

    {active === 'resumen' && <div className="space-y-7">
      <div className="mb-7 grid border border-rule sm:grid-cols-3">{[{ k: 'En la bolsa', v: totalPrendas, d: `${money(totalBolsa)} guardados` }, { k: 'Favoritos', v: favoritos.length, d: 'Prendas que te gustan' }, { k: 'Pedidos', v: pedidos.length, d: 'En tu historial' }].map((s) => <div key={s.k} className="border-b border-rule p-5 last:border-b-0 sm:border-b-0 sm:border-r sm:last:border-r-0"><div className="font-narrow text-xs uppercase tracking-[0.1em] text-soft">{s.k}</div><div className="font-display text-[34px]">{s.v}</div><div className="text-xs text-soft">{s.d}</div></div>)}</div>

      <div className="grid gap-8 lg:grid-cols-[1.35fr_0.95fr]">
        <section>
          <div className="border-b border-ink pb-3"><p className="font-narrow text-[11px] font-semibold uppercase tracking-[0.14em] text-soft">Tu pedido en curso</p></div>

          {actual ? <div className="border-b border-rule pb-5">
            <div className="mt-4 flex items-center justify-between gap-3">
              <Link to={`/pedidos/${actual.id}`} className="font-display text-[30px] leading-none tracking-[-0.04em] text-ink">{actual.ref}</Link>
              <span className="font-narrow text-[10px] font-semibold uppercase tracking-[0.12em] text-red">{etapaPedido}</span>
            </div>

            <div className="mt-6 grid grid-cols-4 gap-2 border-b border-rule pb-3">
              {['Pagado', 'Preparación', 'En camino', 'Entregado'].map((label, index) => {
                const etapaActual = ({ pendiente_pago: -1, pagado: 0, en_preparacion: 1, enviado: 2, en_reparto: 2, entregado: 3, cancelado: -1 } as Record<string, number>)[actual.estado] ?? -1;
                const ok = index <= etapaActual;
                const isCurrent = index === etapaActual;
                return <div key={label} className="text-center">
                  <div className="mb-2 h-[2px] w-full bg-rule" style={{ background: ok ? 'var(--red)' : 'var(--rule)' }} />
                  <span className="block font-narrow text-[10px] font-semibold uppercase tracking-[0.12em]" style={{ color: isCurrent ? 'var(--red)' : 'var(--soft)' }}>{label}</span>
                </div>;
              })}
            </div>

            <p className="mt-4 text-sm text-body">Entrega estimada el {fechaEntrega(actual)} entre las 9:00 y las 18:00.</p>

          </div> : <p className="py-6 text-sm text-soft">Aún no tienes pedidos. <Link to="/catalogo" className="underline">Explora el catálogo</Link>.</p>}

          <div className="mt-6">
            <div className="border-b border-ink pb-3"><p className="font-narrow text-[11px] font-semibold uppercase tracking-[0.14em] text-soft">Avisos</p></div>
            {avisos.length ? avisos.slice(0, 4).map((aviso) => <div key={aviso.id_notificacion} className="border-b border-rule py-3 text-sm text-body"><div className="flex items-start gap-3"><span className="mt-1 h-2 w-2 shrink-0 bg-red" />{aviso.id_pedido ? <Link to={`/pedidos/${aviso.id_pedido}`} className="underline">{aviso.mensaje_notificacion}</Link> : <span>{aviso.mensaje_notificacion}</span>}</div><small className="mt-2 ml-5 block text-soft">{aviso.fecha_creacion}</small></div>) : <p className="py-5 text-sm text-soft">Sin avisos por ahora.</p>}
          </div>
        </section>

        <aside className="border-t-[3px] border-red bg-surface p-5">
          <div className="border-b border-ink pb-3"><p className="font-narrow text-[11px] font-semibold uppercase tracking-[0.14em] text-soft">Tu perfil de ajuste</p></div>

          {tienePerfil ? <>
            <div className="mt-5 flex items-center justify-center border border-rule bg-paper p-3">
              <div className="h-24 w-20 bg-[repeating-linear-gradient(135deg,var(--paper)_0,var(--paper)_4px,var(--rule)_4px,var(--rule)_8px)]" />
            </div>
            <div className="mt-5 space-y-3 text-sm text-body">
              {[['Estatura', medidas.h], ['Pecho', medidas.chest], ['Cintura', medidas.waist], ['Cadera', medidas.hip]].map(([label, value]) => (
                <div key={label as string} className="flex items-center justify-between gap-3 border-b border-rule pb-2 last:border-b-0 last:pb-0">
                  <span className="font-narrow text-[10px] font-semibold uppercase tracking-[0.12em] text-soft">{label as string}</span>
                  <strong className="font-display text-[20px] leading-none">{value as number} cm</strong>
                </div>
              ))}
            </div>
            <div className="mt-5 flex items-start gap-3 border border-rule bg-paper px-3 py-3 text-[12px] text-soft">
              <span className="mt-0.5 flex h-5 w-5 items-center justify-center rounded-full border border-red text-[10px] font-bold text-red">i</span>
              <span>Las medidas se utilizan para recomendar la talla más adecuada en nuestros productos.</span>
            </div>
          </> : <p className="mt-4 text-sm text-soft">Todavía no tienes un perfil de medidas. <Link to="/medidas" className="underline">Crear perfil</Link>.</p>}
        </aside>
      </div>
    </div>}

    {active === 'favoritos' && <section><div className="mb-4 flex justify-between gap-3"><div><h2 className="font-display text-[30px]">Mis favoritos</h2><p className="text-sm text-soft">Prendas que te encantan. Revisa la talla antes de añadirlas a la bolsa.</p></div><span className="text-sm text-soft">{favoritos.length} {favoritos.length === 1 ? 'prenda' : 'prendas'}</span></div>{favoritos.length ? favoritos.map((p) => <div key={p.id_producto} className="flex flex-wrap items-center gap-4 border-b border-rule py-4"><Link to={`/producto/${p.url_producto}`} className="h-24 w-24 shrink-0 bg-surface"><img src={p.url_imagen ?? ''} alt="" className="h-full w-full object-cover" /></Link><div className="min-w-0 flex-1"><Link to={`/producto/${p.url_producto}`} className="font-display text-xl">{p.nombre_producto}</Link><p className="text-sm text-soft">{p.nombre_categoria}{Number(p.stock) <= 0 ? ' · Sin stock' : ''}</p></div><span className="font-display text-xl">{money(Number(p.precio_producto))}</span><Link to={`/producto/${p.url_producto}`} className="inline-flex min-h-11 items-center bg-ink px-4 font-narrow text-xs font-semibold uppercase text-paper">Ver producto</Link><button type="button" onClick={() => void quitarFavorito(p.id_producto)} aria-label={`Quitar ${p.nombre_producto} de favoritos`} className="min-h-11 min-w-11 cursor-pointer border border-rule bg-transparent text-xl text-red">♥</button></div>) : <p className="border-t border-rule py-10 text-center text-sm text-soft">Aún no tienes favoritos. <Link to="/catalogo" className="underline">Explora el catálogo</Link>.</p>}</section>}

    {active === 'bolsa' && <CartPage compact />}

    {active === 'pedidos' && <section>
      <h2 className="mb-1 font-display text-[30px]">Mis pedidos</h2>
      <p className="mb-5 text-sm text-soft">Aquí puedes consultar el estado de tus pedidos.</p>

      {pedidos.length ? pedidos.map((p) => {
        const totalUnidades = p.items.reduce((n, it) => n + it.cantidad, 0);
        const estadoLabel = (nombresEstado[p.estado] ?? p.estado).toUpperCase();
        const estadoColor = p.estado === 'entregado' ? 'text-green-700' : 'text-red';
        const estadoAction = ['pagado', 'enviado', 'en_reparto'].includes(p.estado) ? 'SEGUIR ENVÍO' : 'VER DETALLE';
        const entregaTexto = p.estado === 'entregado'
          ? 'Pedido entregado'
          : `Entrega estimada el ${fechaEntrega(p)}`;

        return <div key={p.id} className="flex flex-wrap items-center gap-5 border-t border-rule py-5">
          <div className="flex shrink-0 gap-2">
            {p.items.slice(0, 2).map((it, i) => (
              <div key={`${p.id}-${i}`} className="h-20 w-16 overflow-hidden border border-rule bg-surface">
                <img src={it.imagen ?? ''} alt="" className="h-full w-full object-cover" />
              </div>
            ))}
            {p.items.length > 2 && <div className="flex h-20 w-16 items-center justify-center border border-rule bg-surface font-display text-2xl text-ink">+{p.items.length - 2}</div>}
          </div>

          <div className="min-w-0 flex-1 text-left">
            <Link to={`/pedidos/${p.id}`} className="font-display text-[30px] leading-none tracking-[-0.04em] text-ink">{p.ref}</Link>
            <div className="mt-2 flex items-center gap-4 text-sm text-soft">
              <span>{p.fecha}</span>
              <span>·</span>
              <span>{totalUnidades} {totalUnidades === 1 ? 'prenda' : 'prendas'}</span>
            </div>
            <p className="mt-3 text-sm text-soft">{entregaTexto}</p>
          </div>

          <div className="min-w-[115px] text-right">
            <div className={`border-b-2 pb-2 font-narrow text-[10px] font-semibold uppercase tracking-[0.12em] ${estadoColor}`}>
              {estadoLabel}
            </div>
          </div>

          <div className="min-w-[92px] text-right font-display text-[24px] leading-none text-ink">
            {money(p.total)}
          </div>

          <Link to={`/pedidos/${p.id}`} className="inline-flex min-h-11 min-w-[120px] items-center justify-center border border-ink bg-transparent px-4 font-narrow text-[10px] font-semibold uppercase tracking-[0.1em] text-ink">{estadoAction}</Link>
        </div>;
      }) : <p className="border-t border-rule py-10 text-center text-sm text-soft">Todavía no hay pedidos.</p>}
    </section>}



    {active === 'datos' && <section className="max-w-[850px]">
      <h2 className="mb-5 font-display text-[30px]">Datos de la cuenta</h2>
      <form onSubmit={guardarDatos}>
        <div className="border-y border-ink">
          <label className="grid gap-2 border-b border-rule py-4 sm:grid-cols-[180px_1fr]">
            <span className="font-narrow text-xs uppercase text-soft">Nombre</span>
            <input value={nombre} onChange={(event) => setNombre(event.target.value)} maxLength={120} className="min-h-9 border-0 bg-transparent p-0 text-sm text-ink outline-none" />
          </label>
          <div className="grid gap-2 border-b border-rule py-4 sm:grid-cols-[180px_1fr]">
            <span className="font-narrow text-xs uppercase text-soft">Correo</span>
            <span className="text-sm">{datos?.correo ?? usuario.correo}</span>
          </div>
          <div className="grid gap-2 border-b border-rule py-4 sm:grid-cols-[180px_1fr_auto]">
            <span className="font-narrow text-xs uppercase text-soft">Teléfono</span>
            {editandoTelefono
              ? <input value={telefono} onChange={(event) => setTelefono(event.target.value)} maxLength={30} placeholder="Opcional" className="min-h-9 border-0 bg-transparent p-0 text-sm text-ink outline-none" />
              : <span className="text-sm">{telefono || 'No registrado'}</span>}
            <button type="button" onClick={() => setEditandoTelefono(!editandoTelefono)} className="min-h-9 cursor-pointer border-0 bg-transparent px-0 text-left font-narrow text-xs font-semibold uppercase underline sm:px-3">{editandoTelefono ? 'Listo' : 'Editar'}</button>
          </div>
        </div>

        <section className="mt-8" aria-labelledby="direcciones-title">
          <div className="mb-3 flex flex-wrap items-center justify-between gap-3 border-b border-ink pb-3">
            <h3 id="direcciones-title" className="font-narrow text-xs font-semibold uppercase tracking-[0.1em] text-soft">Direcciones de entrega</h3>
            <button type="button" onClick={() => setEditorDireccion('nuevo')} className="min-h-10 cursor-pointer border-none bg-ink px-4 font-narrow text-xs font-semibold uppercase text-paper">+ Añadir dirección</button>
          </div>
          {direcciones.length ? <div className="space-y-2">
            {direcciones.map((direccion) => <article key={direccion.id} className="flex flex-wrap items-center gap-4 border border-rule p-4">
              <span aria-hidden="true" className="text-xl">⌂</span>
              <div className="min-w-0 flex-1">
                <p className="text-sm font-semibold">{direccion.nombre} {Boolean(direccion.principal) && <span className="ml-2 bg-red/10 px-2 py-1 font-narrow text-[10px] font-normal uppercase text-red">Principal</span>}</p>
                <p className="mt-1 text-sm text-body">{direccion.direccion}</p>
                <p className="text-sm text-body">{direccion.ciudad}, {nombreCortoDepartamento(direccion.departamento)}, Perú</p>
                {direccion.referencia && <p className="text-xs text-soft">{direccion.referencia}</p>}
              </div>
              <div className="flex gap-2">
                <button type="button" onClick={() => setEditorDireccion(direccion)} className="min-h-10 cursor-pointer border border-ink bg-transparent px-3 font-narrow text-xs font-semibold uppercase">Editar</button>
                <button type="button" onClick={() => void eliminarDireccion(direccion.id)} className="min-h-10 cursor-pointer border border-red bg-transparent px-3 font-narrow text-xs font-semibold uppercase text-red">Eliminar</button>
              </div>
            </article>)}
          </div> : <p className="border border-rule px-4 py-5 text-sm text-soft">Todavía no tienes direcciones guardadas.</p>}
        </section>

        <div className="mt-8 border-b border-ink pb-3 font-narrow text-xs font-semibold uppercase tracking-[0.1em] text-soft">Preferencias</div>
        <label className="flex justify-between gap-4 border-b border-rule py-4 text-sm"><span>Avisos de pedido <small className="block text-soft">Se muestran en tu cuenta cuando cambia el estado.</small></span><input type="checkbox" checked={avisosPedidos} onChange={(event) => setAvisosPedidos(event.target.checked)} className="h-5 w-5 accent-red" /></label>
        <label className="flex justify-between gap-4 border-b border-rule py-4 text-sm"><span>Novedades y lanzamientos <small className="block text-soft">Preferencia guardada; el envío de campañas aún no está activo.</small></span><input type="checkbox" checked={avisosNovedades} onChange={(event) => setAvisosNovedades(event.target.checked)} className="h-5 w-5 accent-red" /></label>
        <div className="mt-6 flex flex-wrap gap-3">
          <button type="submit" disabled={ocupado} className="min-h-12 cursor-pointer border-none bg-ink px-5 font-narrow text-xs font-semibold uppercase text-paper disabled:opacity-50">Guardar cambios</button>
          <button type="button" onClick={() => { setClaveAbierta(!claveAbierta); setError(''); }} className="min-h-12 cursor-pointer border border-ink bg-transparent px-5 font-narrow text-xs font-semibold uppercase">Cambiar contraseña</button>
        </div>
      </form>
      {claveAbierta && <form onSubmit={guardarClave} className="mt-6 grid gap-4 border border-rule bg-surface p-5"><h3 className="font-display text-xl">Cambiar contraseña</h3><input type="password" value={claveActual} onChange={(event) => setClaveActual(event.target.value)} placeholder="Contraseña actual" className="min-h-11 border border-rule bg-paper px-3" /><input type="password" value={claveNueva} onChange={(event) => setClaveNueva(event.target.value)} placeholder="Nueva contraseña, mínimo 8 caracteres" className="min-h-11 border border-rule bg-paper px-3" /><button type="submit" disabled={ocupado} className="min-h-11 bg-ink font-narrow text-xs uppercase text-paper">Actualizar contraseña</button></form>}
      <div className="mt-8 border-t border-ink pt-5"><h3 className="font-display text-2xl">Tus datos corporales</h3><p className="mt-2 text-sm text-body">Puedes descargar o borrar tus medidas guardadas. Al borrarlas se pierde la recomendación personalizada hasta que vuelvas a registrarlas.</p><div className="mt-5 flex flex-wrap gap-3"><button type="button" onClick={descargarMedidas} disabled={!tienePerfil} className="min-h-11 cursor-pointer border border-ink bg-transparent px-4 font-narrow text-xs font-semibold uppercase disabled:opacity-50">Descargar mis medidas</button><button type="button" onClick={() => void eliminarMedidas()} disabled={!tienePerfil} className="min-h-11 cursor-pointer border border-red bg-transparent px-4 font-narrow text-xs font-semibold uppercase text-red disabled:opacity-50">Borrar mis medidas</button></div></div>
      <button type="button" onClick={() => { salir(); navigate('/'); }} className="mt-8 min-h-10 cursor-pointer border-none bg-transparent p-0 text-sm text-soft underline">Cerrar sesión</button>
    </section>}

    {editorDireccion && <AddressForm key={editorDireccion === 'nuevo' ? 'nueva' : editorDireccion.id} actual={editorDireccion === 'nuevo' ? null : editorDireccion} onClose={() => setEditorDireccion(null)} onSaved={(lista) => { setDirecciones(lista); setEditorDireccion(null); setMensaje('Dirección guardada.'); }} />}
  </div>;
}
