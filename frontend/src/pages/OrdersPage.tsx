import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { Seo } from '../components/Seo';
import { ShipmentInfo } from '../components/ShipmentInfo';
import { api, type ApiPedido } from '../services/api';
import { useKloset } from '../store/KlosetContext';
import { money } from '../lib/fit';

type IconoEtapa = 'check' | 'camion' | 'paquete' | 'cancelado';
type EtapaPedido = { estado: string; label: string; icono: IconoEtapa };

const etapas: EtapaPedido[] = [
  { estado: 'pagado', label: 'Pagado', icono: 'check' },
  { estado: 'en_preparacion', label: 'En preparación', icono: 'paquete' },
  { estado: 'enviado', label: 'Enviado', icono: 'camion' },
  { estado: 'en_reparto', label: 'En reparto', icono: 'camion' },
  { estado: 'entregado', label: 'Entregado', icono: 'paquete' },
];

function IconoEstado({ nombre }: { nombre: IconoEtapa }) {
  if (nombre === 'check') return <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" aria-hidden="true"><path d="m5 12 4 4L19 6" /></svg>;
  if (nombre === 'paquete') return <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" aria-hidden="true"><path d="m12 3 8 4.5v9L12 21l-8-4.5v-9L12 3Z" /><path d="m4.5 7.8 7.5 4.4 7.5-4.4M12 12.2V21M8 5.2l8 4.6" /></svg>;
  if (nombre === 'cancelado') return <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="m9 9 6 6m0-6-6 6" /></svg>;
  return <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" aria-hidden="true"><path d="M3 6h11v11H3zM14 10h4l3 3v4h-7z" /><circle cx="7.5" cy="18" r="1.8" /><circle cx="17.5" cy="18" r="1.8" /></svg>;
}

function fecha(valor?: string) {
  if (!valor) return '';
  const date = new Date(valor.replace(' ', 'T'));
  return Number.isNaN(date.getTime()) ? valor : date.toLocaleString('es-PE', { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function fechaEntrega(valor?: string | null) {
  if (!valor) return 'Por confirmar';
  const date = new Date(`${valor}T12:00:00`);
  return Number.isNaN(date.getTime()) ? 'Por confirmar' : date.toLocaleDateString('es-PE', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
}

export function OrdersPage() {
  const navigate = useNavigate();
  const { id } = useParams();
  const [params] = useSearchParams();
  const { usuario, cargandoSesion, pedidos, recargarPedidos } = useKloset();
  const nuevo = params.get('nuevo') === '1';
  const correoFallo = nuevo && params.get('correo') === 'fallo';
  const pedidoId = Number(id || (nuevo ? pedidos[0]?.id : 0));
  const [pedido, setPedido] = useState<ApiPedido | null>(null);
  const [error, setError] = useState('');
  const [cancelando, setCancelando] = useState(false);
  const [copiado, setCopiado] = useState('');
  const [errorCopia, setErrorCopia] = useState(false);

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
  const etapaActual = ({ pagado: 0, en_preparacion: 1, enviado: 2, en_reparto: 3, entregado: 4 } as Record<string, number>)[pedido.estado_pedido ?? ''] ?? -1;
  const items = pedido.items ?? [];
  const historial = pedido.historial ?? [];
  const codigos = [
    { titulo: 'Código de entrega', valor: pedido.codigo_entrega, tipo: 'entrega', textoVacio: 'No disponible' },
    ...(['pagado', 'en_preparacion', 'cancelado'].includes(pedido.estado_pedido || '')
      ? [{ titulo: 'Código de anulación', valor: pedido.codigo_anulacion, tipo: 'anulacion', textoVacio: 'No disponible' }]
      : []),
  ];
  const tarjetasCodigos = codigos.map((codigo) => <div key={codigo.tipo} className="flex min-w-0 items-center gap-3 border border-rule p-3">
    <span className="h-6 w-6 shrink-0 text-ink"><IconoEstado nombre="check" /></span>
    <span className="min-w-0 flex-1"><span className="block text-xs text-soft">{codigo.titulo}</span><strong className="block break-all text-sm">{codigo.valor || codigo.textoVacio}</strong></span>
    {codigo.valor && <button type="button" aria-label={`Copiar ${codigo.titulo.toLowerCase()}`} onClick={() => void copiarCodigo(codigo.tipo, codigo.valor!)} className="shrink-0 cursor-pointer text-soft hover:text-ink"><svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" aria-hidden="true"><rect x="8" y="8" width="12" height="12" rx="1" /><path d="M16 8V4H4v12h4" /></svg></button>}
    {copiado === codigo.tipo && <span className="text-xs text-soft">Copiado</span>}
  </div>);
  const cancelar = async () => {
    if (!window.confirm('¿Cancelar este pedido? Las unidades volverán al inventario.')) return;
    setCancelando(true);
    const res = await api.pedidos.cancelar(pedido.id_pedido).catch(() => ({ status: 'error', message: 'No se pudo conectar' }));
    setCancelando(false);
    if (res.status !== 'success') return setError(res.message || 'No se pudo cancelar');
    setPedido({ ...pedido, estado_pedido: 'cancelado', codigo_anulacion: 'codigo_anulacion' in res ? res.codigo_anulacion ?? null : null }); void recargarPedidos();
  };
  const copiarCodigo = async (tipo: string, valor: string) => {
    try {
      await navigator.clipboard.writeText(valor);
      setCopiado(tipo);
      setErrorCopia(false);
    } catch {
      setCopiado('');
      setErrorCopia(true);
    }
  };
  const nombreEstado = pedido.estado_pedido === 'en_preparacion' ? 'Tu pedido está en preparación'
    : pedido.estado_pedido === 'enviado' ? 'Tu pedido está en camino'
      : pedido.estado_pedido === 'en_reparto' ? 'Tu pedido está en reparto'
        : pedido.estado_pedido === 'entregado' ? 'Tu pedido fue entregado'
          : pedido.estado_pedido === 'cancelado' ? 'Este pedido fue cancelado'
            : 'Tu pedido está confirmado';

  return <div className="kl-rise mx-auto max-w-[1100px]">
    <Seo title={`${nuevo ? 'Pedido registrado' : 'Detalle del pedido'} ${pedido.ref}`} description="Consulta el detalle y el progreso de tu pedido Kloset." path={id ? `/pedidos/${id}` : '/pedidos'} noindex />
    {!nuevo && <><div className="mb-6 flex flex-wrap items-center justify-between gap-3"><Link to="/cuenta?tab=pedidos" className="font-narrow text-xs font-semibold uppercase tracking-[0.08em] text-soft">← Volver a mis pedidos</Link><span className="border border-red px-3 py-2 font-narrow text-xs uppercase text-red">Mi cuenta</span></div>
    </>}
    {nuevo && <div className="mb-7 text-center"><span className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-green-100 text-3xl text-green-700">✓</span><h1 className="font-display text-[38px]">¡Pago confirmado!</h1><p className="mx-auto mt-2 max-w-[54ch] text-[15px] leading-[1.6] text-body">Tu pedido ha sido procesado correctamente.<br />El detalle de tu compra quedó registrado en tu cuenta.</p><div className={`mx-auto mt-4 flex max-w-[620px] items-center gap-3 border px-5 py-3 text-left text-sm ${correoFallo ? 'border-amber-200 bg-amber-50 text-amber-950' : 'border-green-200 bg-green-50 text-green-950'}`}><span className="text-2xl" aria-hidden="true">{correoFallo ? '!' : '✉'}</span><span><strong className="block">{correoFallo ? 'Pedido confirmado, correo pendiente' : `Detalle de compra enviado a ${usuario.correo}`}</strong><span>{correoFallo ? 'Puedes consultar el detalle desde la sección Mis pedidos. El pedido no se duplicó.' : 'También puedes consultar la confirmación desde la sección Mis pedidos.'}</span></span></div></div>}
    {nuevo && <><p className="mb-5 border border-rule p-4 text-sm">Código del pedido: <strong>{pedido.ref}</strong>. Guarda estos códigos para consultar y confirmar tu pedido.</p><div className="mb-5 grid gap-2 sm:grid-cols-2">{tarjetasCodigos}</div></>}
    {!nuevo && <><div className="mb-5 grid gap-5 lg:grid-cols-[1fr_1fr] lg:items-center">
      <div><h1 className="font-display text-[32px]">Detalle del pedido</h1><p className="mt-1 text-sm text-soft">Pedido <strong className="text-ink">{pedido.ref}</strong> · Realizado el {fecha(pedido.fecha_creacion)}</p></div>
      <div className="grid gap-2 sm:grid-cols-2">{tarjetasCodigos}</div>
    </div>
    <section aria-label="Progreso del pedido" className="mb-5">
      <div className="overflow-x-auto pb-2">
        <div className="relative grid min-w-[500px] grid-cols-5 gap-1 pb-4 pt-1 sm:min-w-0">
          <div className="absolute left-[10%] right-[10%] top-[18px] h-px bg-rule" />
          {pedido.estado_pedido !== 'cancelado' && <div className="absolute left-[10%] top-[18px] h-px bg-red transition-all" style={{ width: `${Math.max(0, etapaActual) * 20}%` }} />}
          {etapas.map((etapa, i) => {
            const cancelado = pedido.estado_pedido === 'cancelado';
            const ultimaEtapa = i === etapas.length - 1;
            const estadoVisual = cancelado && ultimaEtapa ? 'cancelado' : etapa.estado;
            const evento = historial.filter((h) => h.estado === estadoVisual).at(-1);
            const actual = estadoVisual === pedido.estado_pedido;
            const completo = !cancelado && i <= etapaActual || Boolean(evento);
            return <div key={etapa.estado} className="relative flex min-w-0 flex-col items-center text-center">
              <span className="flex h-9 w-9 items-center justify-center rounded-full border" style={{ borderColor: actual || completo ? 'var(--red)' : 'var(--rule)', background: actual ? 'var(--red)' : completo ? 'var(--ink)' : 'var(--paper)', color: actual || completo ? 'var(--paper)' : 'var(--soft)' }}>
                <span className="h-5 w-5"><IconoEstado nombre={cancelado && ultimaEtapa ? 'cancelado' : etapa.icono} /></span>
              </span>
              <span className="mt-2 font-narrow text-xs font-semibold">{cancelado && ultimaEtapa ? 'Cancelado' : etapa.label}</span>
              <span className="mt-1 whitespace-pre-line text-[11px] leading-[1.35] text-soft">{evento ? fecha(evento.fecha).replace(', ', '\n') : actual ? 'Estado actual' : 'Pendiente'}</span>
            </div>;
          })}
        </div>
      </div>
      {pedido.estado_pedido === 'cancelado' && <p className="mt-2 border-l-[3px] border-red bg-surface p-4 text-sm text-body">Este pedido fue cancelado. El pago simulado permanece registrado como aprobado; no se ha registrado un reembolso.</p>}
    </section>
    {pedido.estado_pedido !== 'cancelado' && <div className="mb-5 flex items-center gap-3 bg-red/5 px-4 py-3">
      <span className="flex h-10 w-10 shrink-0 items-center justify-center bg-red/10 text-red"><span className="h-6 w-6"><IconoEstado nombre={etapas[Math.max(0, etapaActual)]?.icono ?? 'check'} /></span></span>
      <span><strong className="block font-display text-base">{nombreEstado}</strong><span className="text-xs text-soft">{pedido.estado_pedido === 'entregado' ? 'Gracias por elegir Kloset.' : `Entrega estimada: ${fechaEntrega(pedido.entrega_estimada_pedido)}.`}</span></span>
    </div>}
    {errorCopia && <p role="status" className="mb-4 text-xs text-red">No se pudo copiar el código. Puedes seleccionarlo y copiarlo manualmente.</p>}
    {['pagado','en_preparacion'].includes(pedido.estado_pedido ?? '') && <button type="button" onClick={() => void cancelar()} disabled={cancelando} className="mb-5 min-h-10 text-xs text-soft underline hover:text-red">{cancelando ? 'Cancelando…' : 'Cancelar pedido'}</button>}
    </>}
    <div className="grid gap-4 lg:grid-cols-[1.4fr_0.75fr]">
      <ShipmentInfo pedido={pedido} />
      <aside className="bg-surface p-5">
        <h2 className="font-display text-xl">¿Necesitas ayuda?</h2>
        <p className="mt-2 text-sm leading-[1.5] text-body">Si tienes alguna consulta sobre tu pedido, puedes contactar con nuestro equipo de soporte.</p>
        <Link to="/contacto" className="mt-4 inline-flex min-h-11 w-full items-center justify-center border border-ink px-4 font-narrow text-xs font-semibold uppercase hover:bg-ink hover:text-paper">Contactar soporte</Link>
      </aside>
    </div>
    <section className="mt-5">
      <h2 className="border-b border-ink pb-2 font-narrow text-xs font-semibold uppercase tracking-[0.1em]">Productos del pedido ({items.reduce((n, it) => n + (it.cantidad_pedido_item ?? 1), 0)})</h2>
      {items.map((it, i) => <div key={i} className="flex items-center gap-4 border-b border-rule py-3">
        <div className="h-16 w-16 shrink-0 bg-surface"><img src={it.url_imagen ?? ''} alt={it.nombre_producto ?? ''} className="h-full w-full object-cover" /></div>
        <div className="min-w-0 flex-1"><div className="font-display text-base">{it.nombre_producto}</div><p className="text-xs text-soft">Talla {it.talla_variante} · corte {it.corte_variante} · x{it.cantidad_pedido_item}</p></div>
        <div className="shrink-0 text-right"><div className="font-display text-base">{money(Number(it.precio_unitario_pedido_item) * Number(it.cantidad_pedido_item))}</div><span className="text-xs text-soft">x{it.cantidad_pedido_item}</span></div>
      </div>)}
    </section>
    <section className="mt-5">
      <h2 className="border-b border-ink pb-2 font-narrow text-xs font-semibold uppercase tracking-[0.1em]">Resumen de pago</h2>
      <div className="flex justify-between border-b border-rule py-2 text-sm"><span>Subtotal ({items.reduce((n, it) => n + (it.cantidad_pedido_item ?? 1), 0)} productos)</span><span>{money(Number(pedido.subtotal_pedido ?? pedido.total_pedido))}</span></div>
      <div className="flex justify-between border-b border-rule py-2 text-sm"><span>Costo de envío</span><span className="text-green-700">Gratis</span></div>
      <div className="flex justify-between py-3 font-display text-lg font-semibold"><span>Total</span><span>{money(Number(pedido.total_pedido))}</span></div>
    </section>
    <div className="mt-8 flex flex-wrap justify-center gap-3"><Link to="/cuenta?tab=pedidos" className={`inline-flex min-h-12 items-center justify-center px-8 font-narrow text-xs font-semibold uppercase ${nuevo ? 'bg-ink text-paper' : 'border border-ink'}`}>Ver mis pedidos</Link><Link to="/catalogo" className={`inline-flex min-h-12 items-center justify-center px-8 font-narrow text-xs font-semibold uppercase ${nuevo ? 'border border-ink' : 'bg-ink text-paper'}`}>Seguir comprando</Link></div>
  </div>;
}
