import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { Seo } from '../components/Seo';
import { ShipmentInfo } from '../components/ShipmentInfo';
import { api, type ApiPedido } from '../services/api';
import { useKloset } from '../store/KlosetContext';
import { money } from '../lib/fit';

const etapas = [
  { estado: 'pagado', label: 'Pedido registrado' },
  { estado: 'en_preparacion', label: 'En preparación' },
  { estado: 'enviado', label: 'En camino' },
  { estado: 'en_reparto', label: 'En reparto' },
  { estado: 'entregado', label: 'Entregado' },
];

function fecha(valor?: string) {
  if (!valor) return '';
  const date = new Date(valor.replace(' ', 'T'));
  return Number.isNaN(date.getTime()) ? valor : date.toLocaleString('es-PE', { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

export function OrdersPage() {
  const navigate = useNavigate();
  const { id } = useParams();
  const [params] = useSearchParams();
  const { usuario, cargandoSesion, pedidos, recargarPedidos } = useKloset();
  const nuevo = params.get('nuevo') === '1';
  const pedidoId = Number(id || (nuevo ? pedidos[0]?.id : 0));
  const [pedido, setPedido] = useState<ApiPedido | null>(null);
  const [error, setError] = useState('');
  const [copiado, setCopiado] = useState(false);
  const [mensaje, setMensaje] = useState('');
  const [actualizando, setActualizando] = useState(false);

  useEffect(() => { if (!cargandoSesion && !usuario) navigate('/entrar', { replace: true }); }, [cargandoSesion, usuario, navigate]);
  useEffect(() => { if (!id && !nuevo && !cargandoSesion) navigate('/cuenta?tab=pedidos', { replace: true }); }, [id, nuevo, cargandoSesion, navigate]);
  useEffect(() => {
    if (!usuario || !pedidoId) return;
    void api.pedidos.detalle(pedidoId).then((res) => {
      if (res.status === 'success' && res.pedido) { setPedido(res.pedido); void recargarPedidos(); }
      else setError(res.message || 'Pedido no encontrado');
    }).catch(() => setError('No pudimos cargar el pedido.'));
  }, [usuario, pedidoId, recargarPedidos]);

  if (!usuario) return null;
  if (error) return <div className="mx-auto max-w-[800px] py-10 text-center"><p role="alert" className="mb-5 text-red">{error}</p><Link to="/cuenta?tab=pedidos" className="underline">Volver a mis pedidos</Link></div>;
  if (!pedido) return <p className="py-12 text-center text-soft">Cargando pedido…</p>;
  const etapaActual = pedido.estado_pedido === 'cancelado' ? -1 : etapas.findIndex((e) => e.estado === pedido.estado_pedido);
  const items = pedido.items ?? [];
  const historial = pedido.historial ?? [];
  const actualizar = async () => {
    setActualizando(true);
    try {
      const res = await api.pedidos.detalle(pedidoId);
      if (res.status === 'success' && res.pedido) { setPedido(res.pedido); await recargarPedidos(); }
      else setMensaje(res.message || 'No se pudo actualizar el pedido.');
    } catch { setMensaje('No hay conexión con la API.'); }
    finally { setActualizando(false); }
  };
  const cancelar = async () => {
    if (!window.confirm('¿Cancelar este pedido de demostración? Se devolverá el stock y el pedido quedará cerrado. No hay cargos reales.')) return;
    setActualizando(true);
    try {
      const res = await api.pedidos.cancelar(pedidoId);
      setMensaje(res.message || (res.status === 'success' ? 'Pedido cancelado.' : 'No se pudo cancelar el pedido.'));
      if (res.status === 'success') await actualizar();
    } catch { setMensaje('No hay conexión con la API.'); }
    finally { setActualizando(false); }
  };

  return <div className="kl-rise mx-auto max-w-[1100px]">
    <Seo title={`${nuevo ? 'Pedido registrado' : 'Detalle del pedido'} ${pedido.ref}`} description="Consulta el detalle y los estados registrados de tu pedido Kloset." path={id ? `/pedidos/${id}` : '/pedidos'} noindex />
    <div className="mb-6 flex flex-wrap items-center justify-between gap-3"><Link to="/cuenta?tab=pedidos" className="font-narrow text-xs font-semibold uppercase tracking-[0.08em] text-soft">← Volver a mis pedidos</Link><span className="border border-red px-3 py-2 font-narrow text-xs uppercase text-red">Mi cuenta</span></div>
    <div className="mb-5 flex flex-wrap items-center gap-3"><button type="button" onClick={() => void actualizar()} disabled={actualizando} className="min-h-10 cursor-pointer border border-rule bg-transparent px-3 font-narrow text-xs uppercase disabled:opacity-50">Actualizar estado</button><button type="button" onClick={async () => { try { await navigator.clipboard.writeText(pedido.ref); setCopiado(true); } catch { setMensaje(`Referencia del pedido: ${pedido.ref}`); } }} className="min-h-10 cursor-pointer border border-rule bg-transparent px-3 font-narrow text-xs uppercase">{copiado ? 'Referencia copiada' : 'Copiar referencia'}</button>{['pagado', 'pendiente_pago'].includes(pedido.estado_pedido ?? '') && <button type="button" onClick={() => void cancelar()} disabled={actualizando} className="min-h-10 cursor-pointer border border-red bg-transparent px-3 font-narrow text-xs uppercase text-red disabled:opacity-50">Cancelar pedido de prueba</button>}</div>
    {mensaje && <p role="status" className="mb-5 border-l-[3px] border-red bg-surface px-4 py-3 text-sm text-body">{mensaje}</p>}
    {nuevo && <div className="mb-7 border border-rule bg-surface p-6 text-center"><span className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-green-100 text-3xl text-green-700">✓</span><h1 className="font-display text-[36px]">¡Pedido registrado!</h1><p className="mx-auto mt-2 max-w-[54ch] text-sm leading-[1.6] text-body">Tu pedido de demostración quedó guardado en tu cuenta. El pago fue simulado; no se hizo ningún cargo ni se envió un correo de confirmación.</p><p className="mt-4 font-narrow text-xs uppercase tracking-[0.1em] text-soft">Referencia de confirmación · <strong className="text-ink">{pedido.ref}</strong></p></div>}
    <div className="mb-7 flex flex-wrap items-end justify-between gap-4"><div><h1 className="font-display text-[32px]">{nuevo ? 'Detalle de tu pedido' : 'Seguimiento del pedido'}</h1><p className="mt-1 text-sm text-soft">{pedido.ref} · {fecha(pedido.fecha_creacion)}</p></div><span className="border-b-2 border-red pb-1 font-narrow text-xs font-semibold uppercase tracking-[0.1em] text-red">{pedido.estado_pedido === 'cancelado' ? 'Cancelado' : etapas[etapaActual]?.label ?? pedido.estado_pedido}</span></div>
    {pedido.estado_pedido === 'cancelado' ? <p className="mb-7 border-l-[3px] border-red bg-surface p-4 text-sm text-body">Este pedido figura como cancelado en el sistema. Consulta el historial o contáctanos si necesitas ayuda.</p> : <div className="mb-7 grid grid-cols-2 gap-3 border-b border-ink pb-6 sm:grid-cols-5">{etapas.map((etapa, i) => {
      const evento = historial.find((h) => h.estado === etapa.estado);
      const completo = i <= etapaActual;
      return <div key={etapa.estado} className="border-t-[3px] pt-4" style={{ borderColor: completo ? 'var(--red)' : 'var(--rule)' }}><span className="flex h-9 w-9 items-center justify-center rounded-full text-sm" style={{ background: completo ? 'var(--ink)' : 'var(--surface)', color: completo ? 'var(--paper)' : 'var(--soft)' }}>{completo ? '✓' : i + 1}</span><div className="mt-3 font-narrow text-xs font-semibold uppercase">{etapa.label}</div><div className="mt-1 text-xs text-soft">{evento ? fecha(evento.fecha) : completo ? 'Sin fecha registrada' : 'Pendiente'}</div></div>;
    })}</div>}
    <div className="grid gap-8 lg:grid-cols-[1.35fr_0.65fr]"><div><h2 className="border-b border-ink pb-3 font-narrow text-xs font-semibold uppercase tracking-[0.1em]">Productos del pedido ({items.reduce((n, it) => n + (it.cantidad_pedido_item ?? 1), 0)})</h2>{items.map((it, i) => <div key={i} className="flex items-center gap-4 border-b border-rule py-4"><div className="h-20 w-16 shrink-0 bg-surface"><img src={it.url_imagen ?? ''} alt="" className="h-full w-full object-cover" /></div><div className="min-w-0 flex-1"><div className="font-display text-lg">{it.nombre_producto}</div><p className="text-sm text-soft">Talla {it.talla_variante} · corte {it.corte_variante} · ×{it.cantidad_pedido_item}</p></div><div className="font-display text-lg">{money(Number(it.precio_unitario_pedido_item) * Number(it.cantidad_pedido_item))}</div></div>)}<h2 className="mt-7 border-b border-ink pb-3 font-narrow text-xs font-semibold uppercase tracking-[0.1em]">Resumen</h2><div className="flex justify-between border-b border-rule py-3 text-sm"><span>Subtotal</span><span>{money(Number(pedido.subtotal_pedido ?? pedido.total_pedido))}</span></div><div className="flex justify-between border-b border-rule py-3 text-sm"><span>Envío</span><span>Gratis</span></div><div className="flex justify-between border-b border-ink py-4 font-display text-xl"><span>Total</span><span>{money(Number(pedido.total_pedido))}</span></div></div><aside><ShipmentInfo pedido={pedido} /><div className="border-t-[3px] border-red bg-surface p-5"><h2 className="border-b border-rule pb-3 font-narrow text-xs font-semibold uppercase tracking-[0.1em]">Información de entrega</h2><p className="mt-4 text-sm font-semibold">Dirección de entrega</p><p className="mt-1 text-sm leading-[1.6] text-body">{pedido.direccion_envio_cliente}<br />{pedido.ciudad_envio_cliente}, Lima</p>{pedido.referencia_envio_cliente && <p className="mt-1 text-xs text-soft">Referencia: {pedido.referencia_envio_cliente}</p>}<p className="mt-5 text-xs leading-[1.5] text-soft">Los cambios de estado aparecen aquí cuando se registran desde el panel. El seguimiento refleja los datos registrados por el equipo.</p></div><div className="mt-4 border border-rule p-5"><h2 className="font-display text-xl">¿Necesitas ayuda?</h2><p className="mt-2 text-sm leading-[1.5] text-body">Escríbenos e indica la referencia {pedido.ref}.</p><Link to="/contacto" className="mt-4 inline-flex min-h-11 items-center border border-ink px-4 font-narrow text-xs font-semibold uppercase">Contactar soporte</Link></div></aside></div>
    {historial.length > 0 && <div className="mt-8"><h2 className="border-b border-ink pb-3 font-narrow text-xs font-semibold uppercase tracking-[0.1em]">Historial de estados</h2>{historial.map((evento, i) => <div key={i} className="flex flex-wrap justify-between gap-2 border-b border-rule py-3 text-sm"><span>{etapas.find((e) => e.estado === evento.estado)?.label ?? evento.estado}{evento.comentario ? ` · ${evento.comentario}` : ''}</span><time className="text-soft">{fecha(evento.fecha)}</time></div>)}</div>}
    <div className="mt-8 flex flex-wrap gap-3"><Link to="/cuenta?tab=pedidos" className="inline-flex min-h-12 items-center border border-ink px-5 font-narrow text-xs font-semibold uppercase">Ver mis pedidos</Link><Link to="/" className="inline-flex min-h-12 items-center bg-ink px-5 font-narrow text-xs font-semibold uppercase text-paper">Seguir comprando</Link></div>
  </div>;
}
