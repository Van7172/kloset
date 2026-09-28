import { useState } from 'react';
import type { ApiPedido } from '../services/api';

export function ShipmentInfo({ pedido }: { pedido: ApiPedido }) {
  const [copiado, setCopiado] = useState(false);
  if (!pedido.transportista_pedido && !pedido.seguimiento_pedido && !pedido.entrega_estimada_pedido) return null;
  return <div className="mb-4 border border-rule p-5">
    <h2 className="mb-3 font-narrow text-xs font-semibold uppercase tracking-[0.1em]">Seguimiento de entrega</h2>
    {pedido.transportista_pedido && <p className="text-sm text-body">Transportista · <strong>{pedido.transportista_pedido}</strong></p>}
    {pedido.seguimiento_pedido && <div className="mt-3 flex items-center justify-between gap-2 text-sm"><span className="min-w-0 break-all">{pedido.seguimiento_pedido}</span><button type="button" className="min-h-10 cursor-pointer border border-rule bg-transparent px-3 font-narrow text-xs uppercase" onClick={async () => { try { await navigator.clipboard.writeText(pedido.seguimiento_pedido!); setCopiado(true); } catch { setCopiado(false); } }}>{copiado ? 'Copiado' : 'Copiar'}</button></div>}
    {pedido.entrega_estimada_pedido && <p className="mt-3 border-t border-rule pt-3 text-sm text-body">Entrega estimada · <strong>{new Date(pedido.entrega_estimada_pedido + 'T12:00:00').toLocaleDateString('es-PE', { day: 'numeric', month: 'long', year: 'numeric' })}</strong></p>}
    <p className="mt-3 text-xs text-soft">Información registrada por el equipo de Kloset.</p>
  </div>;
}
