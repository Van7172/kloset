import { useEffect, useState, type FormEvent } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { Seo } from '../components/Seo';
import { AddressForm } from '../components/AddressForm';
import { api, type ApiDatosCuenta, type ApiDireccion, type ApiFavorito } from '../services/api';
import { useKloset } from '../store/KlosetContext';
import { money, tallaRecomendada } from '../lib/fit';
import { nombreCortoDepartamento } from '../lib/locations';
import { CartPage } from './CartPage';

const tabs = [
  { id: 'resumen', texto: 'Resumen' }, { id: 'favoritos', texto: 'Favoritos' },
  { id: 'bolsa', texto: 'Bolsa' }, { id: 'pedidos', texto: 'Pedidos' },
  { id: 'direcciones', texto: 'Direcciones' }, { id: 'datos', texto: 'Datos' },
] as const;
type Tab = (typeof tabs)[number]['id'];
const nombresEstado: Record<string, string> = { pendiente_pago: 'Pendiente', pagado: 'Registrado', en_preparacion: 'En preparación', enviado: 'En camino', entregado: 'Entregado', cancelado: 'Cancelado' };

export function AccountPage() {
  const navigate = useNavigate();
  const [params, setParams] = useSearchParams();
  const { usuario, cargandoSesion, bolsa, pedidos, medidas, tienePerfil, corte, borrarPerfil, actualizarNombre, salir } = useKloset();
  const active = tabs.find((t) => t.id === params.get('tab'))?.id ?? 'resumen';
  const [favoritos, setFavoritos] = useState<ApiFavorito[]>([]);
  const [direcciones, setDirecciones] = useState<ApiDireccion[]>([]);
  const [datos, setDatos] = useState<ApiDatosCuenta | null>(null);
  const [avisos, setAvisos] = useState<{ id_notificacion: number; id_pedido: number | null; mensaje_notificacion: string; fecha_creacion: string }[]>([]);
  const [nombre, setNombre] = useState('');
  const [telefono, setTelefono] = useState('');
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

  const guardarDatos = async (event: FormEvent) => {
    event.preventDefault(); setError(''); setMensaje(''); setOcupado(true);
    try {
      const res = await api.cuenta.guardar({ nombre: nombre.trim(), telefono: telefono.trim(), avisos_pedidos: avisosPedidos, avisos_novedades: avisosNovedades });
      if (res.status !== 'success' || !res.datos) return setError(res.message || 'No se pudieron guardar los datos.');
      setDatos(res.datos); actualizarNombre(res.datos.nombre); setMensaje('Cambios guardados.');
    } catch { setError('No hay conexión con la API.'); } finally { setOcupado(false); }
  };
  const guardarClave = async (event: FormEvent) => {
    event.preventDefault(); setError(''); setMensaje(''); setOcupado(true);
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
  const hacerPrincipal = async (id: number) => {
    setError('');
    try {
      const res = await api.direcciones.principal(id);
      if (res.status !== 'success' || !res.direcciones) return setError(res.message || 'No se pudo cambiar la dirección principal.');
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
      {tabs.map((t) => <button key={t.id} type="button" onClick={() => tab(t.id)} className="min-h-12 shrink-0 cursor-pointer border-0 border-b-[3px] bg-transparent px-0 font-narrow text-xs font-semibold uppercase tracking-[0.08em]" style={{ borderColor: active === t.id ? 'var(--red)' : 'transparent', color: active === t.id ? 'var(--ink)' : 'var(--soft)' }}>{t.texto}{t.id === 'favoritos' ? ` ${favoritos.length}` : t.id === 'bolsa' ? ` ${totalPrendas}` : t.id === 'pedidos' ? ` ${pedidos.length}` : t.id === 'direcciones' ? ` ${direcciones.length}` : ''}</button>)}
    </nav>
    {error && <p role="alert" className="mb-5 border-l-[3px] border-red bg-surface px-4 py-3 text-sm text-red">{error}</p>}
    {mensaje && <p role="status" className="mb-5 border-l-[3px] border-red bg-surface px-4 py-3 text-sm text-body">{mensaje}</p>}

    {active === 'resumen' && <div>
      <div className="mb-7 grid border border-rule sm:grid-cols-3">{[{ k: 'En la bolsa', v: totalPrendas, d: `${money(totalBolsa)} guardados` }, { k: 'Favoritos', v: favoritos.length, d: 'Prendas que te gustan' }, { k: 'Pedidos', v: pedidos.length, d: 'En tu historial' }].map((s) => <div key={s.k} className="border-b border-rule p-5 last:border-b-0 sm:border-b-0 sm:border-r sm:last:border-r-0"><div className="font-narrow text-xs uppercase tracking-[0.1em] text-soft">{s.k}</div><div className="font-display text-[34px]">{s.v}</div><div className="text-xs text-soft">{s.d}</div></div>)}</div>
      <div className="grid gap-8 lg:grid-cols-[1.2fr_0.8fr]"><div><div className="border-b border-ink pb-3 font-narrow text-xs uppercase tracking-[0.1em] text-soft">Tu actividad</div>{actual ? <div className="border-b border-rule py-5"><div className="flex items-center justify-between gap-3"><Link to={`/pedidos/${actual.id}`} className="font-display text-[26px]">{actual.ref}</Link><span className="font-narrow text-xs uppercase text-red">{nombresEstado[actual.estado] ?? actual.estado}</span></div><p className="mt-2 text-sm text-body">{actual.items.reduce((n, i) => n + i.cantidad, 0)} prendas · {money(actual.total)}</p><Link to={`/pedidos/${actual.id}`} className="mt-3 inline-block border-b border-ink font-narrow text-xs uppercase tracking-[0.08em]">Ver pedido →</Link></div> : <p className="py-6 text-sm text-soft">Aún no tienes pedidos. <Link to="/" className="underline">Explora el catálogo</Link>.</p>}<div className="mt-6 border-b border-ink pb-3 font-narrow text-xs uppercase tracking-[0.1em] text-soft">Avisos</div>{avisos.length ? avisos.slice(0, 4).map((aviso) => <div key={aviso.id_notificacion} className="border-b border-rule py-3 text-sm text-body">{aviso.id_pedido ? <Link to={`/pedidos/${aviso.id_pedido}`} className="underline">{aviso.mensaje_notificacion}</Link> : aviso.mensaje_notificacion}<small className="mt-1 block text-soft">{aviso.fecha_creacion}</small></div>) : <p className="py-5 text-sm text-soft">Sin avisos por ahora.</p>}</div><div className="border-t-[3px] border-red bg-surface p-6"><p className="font-narrow text-xs uppercase tracking-[0.1em] text-soft">Tu perfil de ajuste</p>{tienePerfil ? <><div className="mt-4 grid grid-cols-2 gap-y-3 text-sm text-body"><span>Estatura</span><strong className="text-right">{medidas.h} cm</strong><span>Pecho</span><strong className="text-right">{medidas.chest} cm</strong><span>Cintura</span><strong className="text-right">{medidas.waist} cm</strong><span>Cadera</span><strong className="text-right">{medidas.hip} cm</strong></div><p className="mt-5 border-t border-rule pt-4 text-sm">Talla recomendada · <strong>{tallaRecomendada(medidas, corte)}</strong></p></> : <p className="mt-4 text-sm text-body">Añade tus medidas para recibir una recomendación de talla.</p>}</div></div>
    </div>}

    {active === 'favoritos' && <section><div className="mb-4 flex justify-between gap-3"><div><h2 className="font-display text-[30px]">Mis favoritos</h2><p className="text-sm text-soft">Prendas que te encantan. Revisa la talla antes de añadirlas a la bolsa.</p></div><span className="text-sm text-soft">{favoritos.length} {favoritos.length === 1 ? 'prenda' : 'prendas'}</span></div>{favoritos.length ? favoritos.map((p) => <div key={p.id_producto} className="flex flex-wrap items-center gap-4 border-b border-rule py-4"><Link to={`/producto/${p.url_producto}`} className="h-24 w-24 shrink-0 bg-surface"><img src={p.url_imagen ?? ''} alt="" className="h-full w-full object-cover" /></Link><div className="min-w-0 flex-1"><Link to={`/producto/${p.url_producto}`} className="font-display text-xl">{p.nombre_producto}</Link><p className="text-sm text-soft">{p.nombre_categoria}{Number(p.stock) <= 0 ? ' · Sin stock' : ''}</p></div><span className="font-display text-xl">{money(Number(p.precio_producto))}</span><Link to={`/producto/${p.url_producto}`} className="inline-flex min-h-11 items-center bg-ink px-4 font-narrow text-xs font-semibold uppercase text-paper">Ver producto</Link><button type="button" onClick={() => void quitarFavorito(p.id_producto)} aria-label={`Quitar ${p.nombre_producto} de favoritos`} className="min-h-11 min-w-11 cursor-pointer border border-rule bg-transparent text-xl text-red">♥</button></div>) : <p className="border-t border-rule py-10 text-center text-sm text-soft">Aún no tienes favoritos. <Link to="/" className="underline">Explora el catálogo</Link>.</p>}</section>}

    {active === 'bolsa' && <CartPage compact />}

    {active === 'pedidos' && <section><h2 className="mb-1 font-display text-[30px]">Mis pedidos</h2><p className="mb-5 text-sm text-soft">Consulta los estados registrados de tus pedidos.</p>{pedidos.length ? pedidos.map((p) => <div key={p.id} className="flex flex-wrap items-center gap-5 border-t border-rule py-5"><div className="flex shrink-0">{p.items.slice(0, 3).map((it, i) => <div key={i} className="h-20 w-16 border-r border-paper bg-surface"><img src={it.imagen ?? ''} alt="" className="h-full w-full object-cover" /></div>)}</div><div className="min-w-0 flex-1"><Link to={`/pedidos/${p.id}`} className="font-display text-2xl">{p.ref}</Link><p className="text-sm text-soft">{p.fecha} · {p.items.reduce((n, it) => n + it.cantidad, 0)} prendas</p></div><div><div className="font-narrow text-xs font-semibold uppercase tracking-[0.1em] text-red">{nombresEstado[p.estado] ?? p.estado}</div><div className="mt-2 font-display text-xl">{money(p.total)}</div></div><Link to={`/pedidos/${p.id}`} className="inline-flex min-h-11 items-center border border-ink px-4 font-narrow text-xs font-semibold uppercase">Ver detalle</Link></div>) : <p className="border-t border-rule py-10 text-center text-sm text-soft">Todavía no hay pedidos.</p>}</section>}

    {active === 'direcciones' && <section><div className="mb-4 flex flex-wrap items-center justify-between gap-3"><h2 className="font-display text-[30px]">Direcciones de entrega</h2><button type="button" onClick={() => setEditorDireccion('nuevo')} className="min-h-11 cursor-pointer border-none bg-ink px-4 font-narrow text-xs font-semibold uppercase text-paper">+ Añadir dirección</button></div>{direcciones.length ? direcciones.map((d) => <div key={d.id} className="mb-3 flex flex-wrap items-center gap-4 border border-rule p-5"><span aria-hidden="true" className="text-2xl">⌂</span><div className="min-w-0 flex-1"><div className="font-display text-xl">{d.nombre} <span className="font-narrow text-[11px] uppercase text-soft">· {d.tipo}</span>{Boolean(d.principal) && <span className="ml-2 bg-red/10 px-2 py-1 font-narrow text-[10px] uppercase text-red">Principal</span>}</div><p className="mt-1 text-sm text-body">{d.direccion}<br />{d.ciudad}, {nombreCortoDepartamento(d.departamento)}{d.telefono ? ` · ${d.telefono}` : ''}</p>{d.referencia && <p className="text-xs text-soft">{d.referencia}</p>}</div><div className="flex flex-wrap gap-2">{!d.principal && <button type="button" onClick={() => void hacerPrincipal(d.id)} className="min-h-10 cursor-pointer border border-rule bg-transparent px-3 font-narrow text-xs uppercase">Hacer principal</button>}<button type="button" onClick={() => setEditorDireccion(d)} className="min-h-10 cursor-pointer border border-ink bg-transparent px-3 font-narrow text-xs uppercase">Editar</button><button type="button" onClick={() => void eliminarDireccion(d.id)} className="min-h-10 cursor-pointer border border-red bg-transparent px-3 font-narrow text-xs uppercase text-red">Eliminar</button></div></div>) : <p className="border-t border-rule py-8 text-sm text-soft">Todavía no guardas direcciones. Puedes añadir una aquí o durante el pago de demostración.</p>}</section>}

    {active === 'datos' && <section className="max-w-[850px]"><h2 className="mb-5 font-display text-[30px]">Datos de la cuenta</h2><form onSubmit={guardarDatos} className="border-t border-ink"><label className="grid gap-2 border-b border-rule py-4 sm:grid-cols-[180px_1fr]"><span className="font-narrow text-xs uppercase text-soft">Nombre</span><input value={nombre} onChange={(e) => setNombre(e.target.value)} maxLength={120} className="min-h-10 border border-rule bg-transparent px-3 text-sm" /></label><div className="grid gap-2 border-b border-rule py-4 sm:grid-cols-[180px_1fr]"><span className="font-narrow text-xs uppercase text-soft">Correo</span><span className="text-sm">{datos?.correo ?? usuario.correo}</span></div><label className="grid gap-2 border-b border-rule py-4 sm:grid-cols-[180px_1fr]"><span className="font-narrow text-xs uppercase text-soft">Teléfono</span><input value={telefono} onChange={(e) => setTelefono(e.target.value)} maxLength={30} placeholder="Opcional" className="min-h-10 border border-rule bg-transparent px-3 text-sm" /></label><div className="mt-8 border-b border-ink pb-3 font-narrow text-xs font-semibold uppercase tracking-[0.1em] text-soft">Preferencias</div><label className="flex justify-between gap-4 border-b border-rule py-4 text-sm"><span>Avisos de pedido <small className="block text-soft">Se muestran en tu cuenta cuando cambia el estado.</small></span><input type="checkbox" checked={avisosPedidos} onChange={(e) => setAvisosPedidos(e.target.checked)} className="h-5 w-5 accent-red" /></label><label className="flex justify-between gap-4 border-b border-rule py-4 text-sm"><span>Novedades y lanzamientos <small className="block text-soft">Preferencia guardada; el envío de campañas aún no está activo.</small></span><input type="checkbox" checked={avisosNovedades} onChange={(e) => setAvisosNovedades(e.target.checked)} className="h-5 w-5 accent-red" /></label><div className="mt-6 flex flex-wrap gap-3"><button type="submit" disabled={ocupado} className="min-h-12 cursor-pointer border-none bg-ink px-5 font-narrow text-xs font-semibold uppercase text-paper disabled:opacity-50">Guardar cambios</button><button type="button" onClick={() => { setClaveAbierta(!claveAbierta); setError(''); }} className="min-h-12 cursor-pointer border border-ink bg-transparent px-5 font-narrow text-xs font-semibold uppercase">Cambiar contraseña</button></div></form>{claveAbierta && <form onSubmit={guardarClave} className="mt-6 grid gap-4 border border-rule bg-surface p-5"><h3 className="font-display text-xl">Cambiar contraseña</h3><input type="password" value={claveActual} onChange={(e) => setClaveActual(e.target.value)} placeholder="Contraseña actual" className="min-h-11 border border-rule bg-paper px-3" /><input type="password" value={claveNueva} onChange={(e) => setClaveNueva(e.target.value)} placeholder="Nueva contraseña, mínimo 8 caracteres" className="min-h-11 border border-rule bg-paper px-3" /><button type="submit" disabled={ocupado} className="min-h-11 bg-ink font-narrow text-xs uppercase text-paper">Actualizar contraseña</button></form>}<div className="mt-8 border-t border-ink pt-5"><h3 className="font-display text-2xl">Tus datos corporales</h3><p className="mt-2 text-sm text-body">Puedes descargar o borrar tus medidas guardadas. Al borrarlas se pierde la recomendación personalizada hasta que vuelvas a registrarlas.</p><div className="mt-5 flex flex-wrap gap-3"><button type="button" onClick={descargarMedidas} disabled={!tienePerfil} className="min-h-11 cursor-pointer border border-ink bg-transparent px-4 font-narrow text-xs font-semibold uppercase disabled:opacity-50">Descargar mis medidas</button><button type="button" onClick={() => void eliminarMedidas()} disabled={!tienePerfil} className="min-h-11 cursor-pointer border border-red bg-transparent px-4 font-narrow text-xs font-semibold uppercase text-red disabled:opacity-50">Borrar mis medidas</button></div></div><button type="button" onClick={() => { salir(); navigate('/'); }} className="mt-8 min-h-10 cursor-pointer border-none bg-transparent p-0 text-sm text-soft underline">Cerrar sesión</button></section>}

    {editorDireccion && <AddressForm key={editorDireccion === 'nuevo' ? 'nueva' : editorDireccion.id} actual={editorDireccion === 'nuevo' ? null : editorDireccion} onClose={() => setEditorDireccion(null)} onSaved={(lista) => { setDirecciones(lista); setEditorDireccion(null); setMensaje('Dirección guardada.'); }} />}
  </div>;
}
