import { Seo } from '../components/Seo';
import { useEffect, useState } from 'react';
import { api, type ApiCarritoItem } from '../services/api';
import { useNavigate } from 'react-router-dom';
import { useKloset } from '../store/KlosetContext';
import { money } from '../lib/fit';
import { Marco } from '../components/Marco';

export function CartPage({ compact = false }: { compact?: boolean }) {
  const navigate = useNavigate();
  const { bolsa, quitarDeBolsa, cambiarCantidad, guardarParaDespues, recuperarGuardado, usuario, setIntencion } = useKloset();
  const [error, setError] = useState('');
  const [guardados, setGuardados] = useState<ApiCarritoItem[]>([]);
  useEffect(() => {
    if (!usuario) { setGuardados([]); return; }
    void api.carrito.guardados().then((r) => { if (r.status === 'success') setGuardados(r.items); }).catch(() => undefined);
  }, [usuario, bolsa]);
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
    <div className={compact ? 'kl-rise' : 'kl-rise mx-auto max-w-[900px]'}>
      {!compact && <Seo title="Tu bolsa" description="Revisa las prendas, tallas y cortes de tu bolsa antes de pasar por caja." path="/bolsa" noindex />}
      <h2 className="mb-[6px] mt-[6px] font-display text-[34px] font-normal tracking-[-0.025em]">Tu bolsa</h2>
      <p className="mb-[22px] text-[13.5px] text-soft">
        {totalPrendas} {totalPrendas === 1 ? 'prenda' : 'prendas'} con talla y corte elegidos.
      </p>

      {bolsa.length === 0 ? (
        <div className="border-b border-t-2 border-ink px-5 py-11 text-center">
          <div className="mb-[10px] font-display text-[22px]">Todavía no hay nada aquí</div>
          <p className="mb-5 text-sm text-soft">Elige una prenda y revisa su talla antes de decidir.</p>
          <button
            type="button"
            onClick={() => navigate('/catalogo')}
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
                  {it.id_carrito_item && <div className="mt-3 flex items-center gap-3" aria-label={`Cantidad de ${it.name}`}><button type="button" disabled={it.cantidad <= 1} onClick={async () => { const fallo = await cambiarCantidad(it.id_carrito_item!, it.cantidad - 1); setError(fallo ?? ''); }} className="h-9 w-9 cursor-pointer border border-rule bg-transparent disabled:opacity-40" aria-label="Reducir cantidad">−</button><span className="min-w-4 text-center text-sm">{it.cantidad}</span><button type="button" onClick={async () => { const fallo = await cambiarCantidad(it.id_carrito_item!, it.cantidad + 1); setError(fallo ?? ''); }} className="h-9 w-9 cursor-pointer border border-rule bg-transparent" aria-label="Aumentar cantidad">+</button></div>}
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
                  {it.id_carrito_item && <button type="button" onClick={async () => { const fallo = await guardarParaDespues(it.id_carrito_item!); setError(fallo ?? ''); }} className="min-h-10 cursor-pointer border-none bg-transparent text-xs text-soft underline">Guardar para después</button>}
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
            <div className="flex items-baseline justify-between pb-5 pt-4">
              <span className="font-narrow text-[13px] uppercase tracking-[0.12em]">Total</span>
              <span className="font-display text-[30px] tracking-[-0.02em]">{money(subtotal)}</span>
            </div>
            <button
              type="button"
              onClick={pagar}
              className="min-h-[58px] w-full cursor-pointer border-none bg-ink font-narrow text-base font-semibold uppercase tracking-[0.08em] text-paper hover:bg-red hover:text-[#F2F2F0]"
            >
              Ir al pago de prueba · {money(subtotal)}
            </button>
          </div>
        </>
      )}
      {error && <p role="alert" className="mt-4 border-l-[3px] border-red pl-3 text-sm text-red">{error}</p>}
      {guardados.length > 0 && <section className="mt-8"><h3 className="border-b border-ink pb-3 font-narrow text-xs font-semibold uppercase tracking-[0.1em] text-soft">Guardado para después</h3>{guardados.map((it) => <div key={it.id_variante} className="flex items-center gap-4 border-b border-rule py-4"><img src={it.url_imagen ?? ''} alt="" className="h-16 w-14 shrink-0 bg-surface object-cover" /><div className="min-w-0 flex-1"><div className="font-display text-lg">{it.nombre_producto}</div><div className="text-xs text-soft">Talla {it.talla_variante} · {it.corte_variante} · ×{it.cantidad_carrito_item}</div></div><button type="button" onClick={async () => { const fallo = await recuperarGuardado(it.id_variante); setError(fallo ?? ''); }} className="min-h-11 cursor-pointer border border-ink bg-transparent px-4 font-narrow text-xs font-semibold uppercase">A la bolsa</button></div>)}</section>}
    </div>
  );
}
