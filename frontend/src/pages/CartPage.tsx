import { Seo } from '../components/Seo';
import { useNavigate } from 'react-router-dom';
import { useKloset } from '../store/KlosetContext';
import { money } from '../lib/fit';
import { Marco } from '../components/Marco';

export function CartPage() {
  const navigate = useNavigate();
  const { bolsa, quitarDeBolsa, usuario, setIntencion } = useKloset();
  const totalPrendas = bolsa.reduce((a, b) => a + b.cantidad, 0);
  const subtotal = bolsa.reduce((a, b) => a + b.price * b.cantidad, 0);

  const pagar = () => {
    if (!usuario) {
      setIntencion({ then: 'checkout' });
      navigate('/entrar');
      return;
    }
    navigate('/pago');
  };

  return (
    <div className="kl-rise mx-auto max-w-[760px]">
      <Seo title="Tu bolsa" description="Revisa las prendas, tallas y cortes de tu bolsa antes de pasar por caja." path="/bolsa" noindex />
      <h2 className="mb-[6px] mt-[6px] font-display text-[34px] font-normal tracking-[-0.025em]">Tu bolsa</h2>
      <p className="mb-[22px] text-[13.5px] text-soft">
        {totalPrendas} {totalPrendas === 1 ? 'prenda' : 'prendas'}, todas verificadas sobre tu avatar.
      </p>

      {bolsa.length === 0 ? (
        <div className="border-b border-t-2 border-ink px-5 py-11 text-center">
          <div className="mb-[10px] font-display text-[22px]">Todavía no hay nada aquí</div>
          <p className="mb-5 text-sm text-soft">Elige una prenda y pruébala en tu avatar antes de decidir.</p>
          <button
            type="button"
            onClick={() => navigate('/')}
            className="min-h-[50px] cursor-pointer border-none bg-ink px-6 font-narrow text-sm font-semibold uppercase tracking-[0.08em] text-paper hover:bg-red hover:text-[#F2F2F0]"
          >
            Ir al catálogo
          </button>
        </div>
      ) : (
        <>
          <div className="border-t border-ink">
            {bolsa.map((it) => (
              <div key={it.id_carrito_item ?? `${it.id_producto}-${it.size}-${it.fit}`} className="flex gap-4 border-b border-rule py-4">
                <Marco
                  src={it.imagen}
                  alt={it.name}
                  ratio="78/96"
                  className="h-24 w-[78px] shrink-0"
                />
                <div className="min-w-0 flex-1">
                  <div className="font-display text-[19px]">{it.name}</div>
                  <div className="mt-1 text-[12.5px] text-soft">
                    Talla {it.size} · corte {it.fit}
                    {it.cantidad > 1 && ` · ×${it.cantidad}`}
                  </div>
                  <div className="mt-[10px] inline-block border-b-2 border-red pb-[3px] font-narrow text-[11px] uppercase tracking-[0.08em] text-ink">
                    Probada en avatar
                  </div>
                </div>
                <div className="flex flex-col items-end justify-between">
                  <span className="font-display text-lg">{money(it.price * it.cantidad)}</span>
                  <button
                    type="button"
                    onClick={() => it.id_carrito_item && quitarDeBolsa(it.id_carrito_item)}
                    className="min-h-[44px] cursor-pointer border-none bg-transparent text-[12.5px] text-soft underline"
                  >
                    Quitar
                  </button>
                </div>
              </div>
            ))}
          </div>

          <div className="mt-6 border-t-2 border-ink pt-[6px]">
            <div className="flex justify-between border-b border-rule py-[11px] text-sm text-body">
              <span>Subtotal</span>
              <span className="text-ink">{money(subtotal)}</span>
            </div>
            <div className="flex justify-between border-b border-rule py-[11px] text-sm text-body">
              <span>Envío</span>
              <span className="text-ink">Gratis</span>
            </div>
            <div className="flex justify-between border-b border-ink py-[11px] text-sm text-body">
              <span>Devolución por talla</span>
              <span className="text-red">No la necesitarás</span>
            </div>
            <div className="flex items-baseline justify-between pb-5 pt-4">
              <span className="font-narrow text-[13px] uppercase tracking-[0.12em]">Total</span>
              <span className="font-display text-[30px] tracking-[-0.02em]">{money(subtotal)}</span>
            </div>
            <button
              type="button"
              onClick={pagar}
              className="min-h-[58px] w-full cursor-pointer border-none bg-ink font-narrow text-base font-semibold uppercase tracking-[0.08em] text-paper hover:bg-red hover:text-[#F2F2F0]"
            >
              Pagar {money(subtotal)}
            </button>
          </div>
        </>
      )}
    </div>
  );
}
