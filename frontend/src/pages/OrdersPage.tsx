import { useNavigate, useSearchParams } from 'react-router-dom';
import { useKloset } from '../store/KlosetContext';
import { money } from '../lib/fit';

const COLOR_ESTADO: Record<string, string> = {
  entregado: 'var(--soft)',
  cancelado: 'var(--red)',
  pagado: 'var(--ink)',
};

export function OrdersPage() {
  const navigate = useNavigate();
  const [params] = useSearchParams();
  const { pedidos, usuario } = useKloset();
  const nuevo = params.get('nuevo') === '1';
  const ultimo = pedidos[0];

  const eta = new Date(Date.now() + 4 * 86400000).toLocaleDateString('es-PE', {
    day: 'numeric',
    month: 'long',
  });

  return (
    <div className="kl-rise mx-auto max-w-[700px]">
      {nuevo && ultimo && (
        <div className="mb-[30px] border-b border-t-[3px] border-b-ink border-t-red pb-[26px] pt-6">
          <div className="mb-3 font-narrow text-xs uppercase tracking-[0.14em] text-red">
            Pedido confirmado · {ultimo.ref}
          </div>
          <h2 className="mb-3 font-display text-[32px] font-normal leading-tight tracking-[-0.025em]">
            Listo. Sale <em className="italic">a tu medida.</em>
          </h2>
          <p className="m-0 max-w-[46ch] text-[15px] leading-[1.65] text-body">
            Cobrado a la tarjeta ···· {ultimo.card}. Enviamos la confirmación y la ficha de ajuste a{' '}
            {usuario?.correo ?? 'tu correo'}. Entrega estimada el {eta}.
          </p>
          <button
            type="button"
            onClick={() => navigate('/')}
            className="mt-5 min-h-[50px] cursor-pointer border border-ink bg-transparent px-[22px] font-narrow text-sm font-semibold uppercase tracking-[0.08em] text-ink hover:bg-hover"
          >
            Seguir explorando
          </button>
        </div>
      )}

      <div className="border-b border-ink pb-3 font-narrow text-xs uppercase tracking-[0.12em] text-soft">
        Historial
      </div>

      {pedidos.length === 0 ? (
        <p className="py-10 text-center text-soft">Todavía no has hecho ningún pedido.</p>
      ) : (
        pedidos.map((o) => (
          <div key={o.ref} className="border-b border-rule py-[18px]">
            <div className="flex items-baseline justify-between gap-3">
              <div>
                <div className="font-display text-[19px]">{o.ref}</div>
                <div className="mt-1 text-[12.5px] text-soft">
                  {o.fecha} · {o.items.length} prendas
                </div>
              </div>
              <span
                className="whitespace-nowrap border-b-2 pb-[3px] font-narrow text-[11px] uppercase tracking-[0.08em]"
                style={{ color: COLOR_ESTADO[o.estado] ?? 'var(--ink)', borderColor: COLOR_ESTADO[o.estado] ?? 'var(--ink)' }}
              >
                {o.estado}
              </span>
            </div>
            <div className="mt-3 flex items-center justify-between">
              <span className="text-[13.5px] text-body">
                {o.items.map((i) => `${i.name} · ${i.size}`).join(' / ')}
              </span>
              <span className="font-display text-lg">{money(o.total)}</span>
            </div>
          </div>
        ))
      )}
    </div>
  );
}
