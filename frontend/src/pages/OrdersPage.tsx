import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { Seo } from '../components/Seo';
import { ShipmentInfo } from '../components/ShipmentInfo';
import { api, type ApiPedido } from '../services/api';
import { useKloset } from '../store/KlosetContext';
import { money } from '../lib/fit';

const etapas = [
  { estado: 'pagado', label: 'Pedido registrado' },
  { estado: 'enviado', label: 'En camino' },
  { estado: 'en_reparto', label: 'En reparto' },
  { estado: 'entregado', label: 'Entregado' },
];

function fecha(valor?: string) {
  if (!valor) return '';
  const date = new Date(valor.replace(' ', 'T'));
  return Number.isNaN(date.getTime()) ? valor : date.toLocaleString('es-PE', { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function fechaEntrega(valor?: string | null, fechaBase?: string) {
  const date = valor
    ? new Date(`${valor}T12:00:00`)
    : new Date(new Date((fechaBase ?? '').replace(' ', 'T')).getTime() + 3 * 86400000);
  return Number.isNaN(date.getTime()) ? valor : date.toLocaleDateString('es-PE', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
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
  const etapaActual = ({ pagado: 0, en_preparacion: 0, enviado: 1, en_reparto: 2, entregado: 3 } as Record<string, number>)[pedido.estado_pedido ?? ''] ?? -1;
  const items = pedido.items ?? [];
  const historial = pedido.historial ?? [];

  return <div className="kl-rise mx-auto max-w-[1100px]">
    <Seo title={`${nuevo ? 'Pedido registrado' : 'Detalle del pedido'} ${pedido.ref}`} description="Consulta el detalle y el progreso de tu pedido Kloset." path={id ? `/pedidos/${id}` : '/pedidos'} noindex />
    {!nuevo && <><div className="mb-6 flex flex-wrap items-center justify-between gap-3"><Link to="/cuenta?tab=pedidos" className="font-narrow text-xs font-semibold uppercase tracking-[0.08em] text-soft">← Volver a mis pedidos</Link><span className="border border-red px-3 py-2 font-narrow text-xs uppercase text-red">Mi cuenta</span></div>
    </>}
    {nuevo && <div className="mb-7 text-center"><span className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-green-100 text-3xl text-green-700">✓</span><h1 className="font-display text-[38px]">¡Pago confirmado!</h1><p className="mx-auto mt-2 max-w-[54ch] text-[15px] leading-[1.6] text-body">Tu pedido ha sido procesado correctamente.<br />El detalle de tu compra quedó registrado en tu cuenta.</p><div className="mx-auto mt-4 flex max-w-[620px] items-center gap-3 border border-green-200 bg-green-50 px-5 py-3 text-left text-sm text-green-950"><span className="text-2xl" aria-hidden="true">✉</span><span><strong className="block">Detalle de compra para {usuario.correo}</strong><span>Consulta la confirmación y el detalle desde la sección Mis pedidos.</span></span></div></div>}
    {nuevo && <div className="mb-5 grid gap-3 border border-rule p-4 sm:grid-cols-2"><div className="flex items-center gap-3"><span className="text-2xl" aria-hidden="true">▤</span><span className="text-sm">Código de confirmación<strong className="block text-base">{pedido.ref}</strong></span></div><div className="flex items-center gap-3 border-t border-rule pt-3 sm:border-l sm:border-t-0 sm:pl-4 sm:pt-0"><span className="text-2xl" aria-hidden="true">⊗</span><span className="text-sm">Código de anulación<strong className="block text-base">ANUL-{String(pedido.id_pedido).padStart(6, '0')}</strong></span></div></div>}
    {!nuevo && <><div className="mb-7"><h1 className="font-display text-[32px]">Seguimiento del pedido</h1><p className="mt-1 text-sm text-soft">{pedido.ref} · {fecha(pedido.fecha_creacion)}</p></div>
    <div className="mb-7 grid gap-3 border border-rule p-4 sm:grid-cols-3">
      <div className="flex items-center gap-3 text-sm"><span className="text-2xl leading-none" aria-hidden="true">▤</span><span><span className="font-narrow text-[10px] font-semibold uppercase tracking-[0.12em] text-soft">Código de confirmación</span><strong className="mt-1 block text-base">{pedido.ref}</strong></span></div>
      <div className="flex items-center gap-3 border-t border-rule pt-3 text-sm sm:border-l sm:border-t-0 sm:pl-4 sm:pt-0"><span className="text-2xl leading-none" aria-hidden="true">⊗</span><span><span className="font-narrow text-[10px] font-semibold uppercase tracking-[0.12em] text-soft">Código de anulación</span><strong className="mt-1 block text-base">ANUL-{String(pedido.id_pedido).padStart(6, '0')}</strong></span></div>
      <div className="flex items-center gap-3 border-t border-rule pt-3 text-sm sm:border-l sm:border-t-0 sm:pl-4 sm:pt-0"><span className="text-2xl leading-none" aria-hidden="true">◷</span><span><span className="font-narrow text-[10px] font-semibold uppercase tracking-[0.12em] text-soft">Entrega estimada</span><strong className="mt-1 block text-base">{fechaEntrega(pedido.entrega_estimada_pedido, pedido.fecha_creacion)}</strong></span></div>
    </div>
    {pedido.estado_pedido === 'cancelado' ? <p className="mb-7 border-l-[3px] border-red bg-surface p-4 text-sm text-body">Este pedido figura como cancelado en el sistema. Consulta el historial o contáctanos si necesitas ayuda.</p> : <div className="mb-7 grid grid-cols-2 gap-3 border-b border-ink pb-6 sm:grid-cols-5">{etapas.map((etapa, i) => {
      const evento = historial.find((h) => h.estado === etapa.estado);
      const completo = i <= etapaActual;
      return <div key={etapa.estado} className="border-t-[3px] pt-4" style={{ borderColor: completo ? 'var(--red)' : 'var(--rule)' }}><span className="flex h-9 w-9 items-center justify-center rounded-full text-sm" style={{ background: completo ? 'var(--ink)' : 'var(--surface)', color: completo ? 'var(--paper)' : 'var(--soft)' }}>{completo ? '✓' : i + 1}</span><div className="mt-3 font-narrow text-xs font-semibold uppercase">{etapa.label}</div><div className="mt-1 text-xs text-soft">{evento ? fecha(evento.fecha) : completo ? 'Sin fecha registrada' : 'Pendiente'}</div></div>;
    })}</div>}
    </>}
    <div className="grid gap-8 lg:grid-cols-[1.35fr_0.65fr]"><div><h2 className="border-b border-ink pb-3 font-narrow text-xs font-semibold uppercase tracking-[0.1em]">Productos del pedido ({items.reduce((n, it) => n + (it.cantidad_pedido_item ?? 1), 0)})</h2>{items.map((it, i) => <div key={i} className="flex items-center gap-4 border-b border-rule py-4"><div className="h-20 w-16 shrink-0 bg-surface"><img src={it.url_imagen ?? ''} alt="" className="h-full w-full object-cover" /></div><div className="min-w-0 flex-1"><div className="font-display text-lg">{it.nombre_producto}</div><p className="text-sm text-soft">Talla {it.talla_variante} · corte {it.corte_variante} · ×{it.cantidad_pedido_item}</p></div><div className="font-display text-lg">{money(Number(it.precio_unitario_pedido_item) * Number(it.cantidad_pedido_item))}</div></div>)}<h2 className="mt-7 border-b border-ink pb-3 font-narrow text-xs font-semibold uppercase tracking-[0.1em]">Resumen</h2><div className="flex justify-between border-b border-rule py-3 text-sm"><span>Subtotal</span><span>{money(Number(pedido.subtotal_pedido ?? pedido.total_pedido))}</span></div><div className="flex justify-between border-b border-rule py-3 text-sm"><span>Envío</span><span>Gratis</span></div><div className="flex justify-between border-b border-ink py-4 font-display text-xl"><span>Total</span><span>{money(Number(pedido.total_pedido))}</span></div></div><aside><ShipmentInfo pedido={pedido} /><div className="border-t-[3px] border-red bg-surface p-5"><h2 className="border-b border-rule pb-3 font-narrow text-xs font-semibold uppercase tracking-[0.1em]">Información de entrega</h2><p className="mt-4 text-sm font-semibold">Dirección de entrega</p><p className="mt-1 text-sm leading-[1.6] text-body">{pedido.direccion_envio_cliente}<br />{pedido.ciudad_envio_cliente}, Lima</p>{pedido.referencia_envio_cliente && <p className="mt-1 text-xs text-soft">Referencia: {pedido.referencia_envio_cliente}</p>}</div><div className="mt-4 border border-rule p-5"><h2 className="font-display text-xl">¿Necesitas ayuda?</h2><p className="mt-2 text-sm leading-[1.5] text-body">Escríbenos e indica la referencia {pedido.ref}.</p><Link to="/contacto" className="mt-4 inline-flex min-h-11 items-center border border-ink px-4 font-narrow text-xs font-semibold uppercase">Contactar soporte</Link></div></aside></div>
    <div className="mt-8 flex flex-wrap justify-center gap-3"><Link to="/cuenta?tab=pedidos" className={`inline-flex min-h-12 items-center justify-center px-8 font-narrow text-xs font-semibold uppercase ${nuevo ? 'bg-ink text-paper' : 'border border-ink'}`}>Ver mis pedidos</Link><Link to="/catalogo" className={`inline-flex min-h-12 items-center justify-center px-8 font-narrow text-xs font-semibold uppercase ${nuevo ? 'border border-ink' : 'bg-ink text-paper'}`}>Seguir comprando</Link></div>
  </div>;
}
