import type { ApiPedido } from '../services/api';

export function ShipmentInfo({ pedido }: { pedido: ApiPedido }) {
  const direccion = [pedido.direccion_envio_cliente, pedido.ciudad_envio_cliente, 'Lima'].filter(Boolean).join(', ');
  const mapa = `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(direccion)}`;
  const entrega = pedido.entrega_estimada_pedido
    ? new Date(`${pedido.entrega_estimada_pedido}T12:00:00`).toLocaleDateString('es-PE', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
    : 'Por confirmar';

  return <section className="bg-surface p-5">
    <h2 className="border-b border-rule pb-3 font-narrow text-xs font-semibold uppercase tracking-[0.1em]">Información del envío</h2>
    <dl className="mt-3 space-y-3 text-sm">
      <div className="grid grid-cols-[minmax(0,1fr)_minmax(0,1.5fr)] gap-3"><dt className="text-body">Transportista</dt><dd className="m-0">{pedido.transportista_pedido || 'Por confirmar'}</dd></div>
      <div className="grid grid-cols-[minmax(0,1fr)_minmax(0,1.5fr)] gap-3"><dt className="text-body">Entrega estimada</dt><dd className="m-0 capitalize">{entrega}</dd></div>
      <div className="grid grid-cols-[minmax(0,1fr)_minmax(0,1.5fr)] gap-3"><dt className="text-body">Dirección de entrega</dt><dd className="m-0 leading-[1.5]">{pedido.direccion_envio_cliente}<br />{pedido.ciudad_envio_cliente}, Lima{pedido.referencia_envio_cliente && <><br /><span className="text-xs text-soft">Referencia: {pedido.referencia_envio_cliente}</span></>}</dd></div>
    </dl>
    {direccion && <a href={mapa} target="_blank" rel="noreferrer" className="mt-3 inline-flex items-center gap-2 text-sm text-ink underline">
      Ver en el mapa <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" aria-hidden="true"><path d="M14 4h6v6M20 4l-9 9" /><path d="M18 13v6a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h6" /></svg>
    </a>}
  </section>;
}
