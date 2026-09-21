import { Seo } from '../components/Seo';
import { useEffect, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useKloset } from '../store/KlosetContext';
import { money } from '../lib/fit';

type Estado = 'idle' | 'processing' | '3ds' | 'declined' | 'error' | 'done';

const soloDigitos = (v: string) => v.replace(/\D/g, '');

function marca(num: string): string {
  const d = soloDigitos(num);
  if (!d) return '';
  if (/^4/.test(d)) return 'Visa';
  if (/^5[1-5]/.test(d)) return 'Mastercard';
  if (/^3[47]/.test(d)) return 'Amex';
  return 'Tarjeta';
}

export function CheckoutPage() {
  const navigate = useNavigate();
  const { bolsa, usuario, crearPedido } = useKloset();
  const subtotal = bolsa.reduce((a, b) => a + b.price * b.cantidad, 0);

  const [estado, setEstado] = useState<Estado>('idle');
  const [progreso, setProgreso] = useState('0%');
  const [error, setError] = useState('');
  const [errorPedido, setErrorPedido] = useState('');
  const [otp, setOtp] = useState('');
  const [pago, setPago] = useState({ holder: '', num: '', exp: '', cvc: '' });
  const [envio, setEnvio] = useState({ direccion: '', ciudad: '', referencia: '' });
  const timers = useRef<number[]>([]);

  useEffect(() => {
    return () => timers.current.forEach((t) => window.clearTimeout(t));
  }, []);

  useEffect(() => {
    if (bolsa.length === 0 && estado === 'idle') navigate('/bolsa');
  }, [bolsa.length, estado, navigate]);

  const programar = (fn: () => void, ms: number) => {
    timers.current.push(window.setTimeout(fn, ms));
  };

  const escribir = (k: keyof typeof pago) => (valor: string) => {
    let v = valor;
    if (k === 'num') v = soloDigitos(v).slice(0, 16).replace(/(.{4})/g, '$1 ').trim();
    if (k === 'exp') {
      const d = soloDigitos(v).slice(0, 4);
      v = d.length > 2 ? `${d.slice(0, 2)}/${d.slice(2)}` : d;
    }
    if (k === 'cvc') v = soloDigitos(v).slice(0, 4);
    setPago((p) => ({ ...p, [k]: v }));
    setError('');
  };

  const cerrarPedido = async () => {
    const fallo = await crearPedido(
      { direccion: envio.direccion, ciudad: envio.ciudad, referencia: envio.referencia },
      { marca: marca(pago.num) || 'Tarjeta', ultimos: soloDigitos(pago.num).slice(-4) },
    );
    if (fallo) {
      setErrorPedido(fallo);
      setEstado('error');
      return;
    }

    // 'done' evita que el efecto de bolsa vacía redirija a /bolsa antes de salir
    setEstado('done');
    setProgreso('100%');
    setOtp('');
    setPago({ holder: '', num: '', exp: '', cvc: '' });
    navigate('/pedidos?nuevo=1', { replace: true });
  };

  const pagar = () => {
    if (!envio.direccion.trim() || !envio.ciudad.trim()) return setError('La dirección y el distrito son obligatorios');
    const d = soloDigitos(pago.num);
    if (d.length < 15) return setError('Número de tarjeta incompleto');
    if (!/^\d{2}\/\d{2}$/.test(pago.exp)) return setError('Caducidad en formato MM/AA');
    if (soloDigitos(pago.cvc).length < 3) return setError('CVC incompleto');
    if (!pago.holder.trim()) return setError('Falta el titular de la tarjeta');

    setEstado('processing');
    setProgreso('12%');
    setError('');
    programar(() => setProgreso('58%'), 500);
    programar(() => setProgreso('92%'), 1100);
    programar(() => {
      if (d === '4000000000000002') return setEstado('declined');
      if (d === '4000000000003220') {
        setOtp('');
        return setEstado('3ds');
      }
      void cerrarPedido();
    }, 1700);
  };

  const confirmarOtp = () => {
    if (soloDigitos(otp).length !== 6) return setError('El código tiene 6 dígitos');
    setEstado('processing');
    setProgreso('70%');
    setError('');
    programar(() => setProgreso('96%'), 500);
    programar(() => void cerrarPedido(), 1100);
  };

  const pasos = [
    { label: 'Bolsa', activo: true },
    { label: 'Pago', activo: estado !== 'declined' && estado !== 'error' },
    { label: 'Confirmación', activo: false },
  ];

  const campos = [
    { k: 'holder' as const, label: 'Titular', ph: 'Como aparece en la tarjeta', ls: 'normal', mode: 'text' },
    { k: 'num' as const, label: 'Número', ph: '4242 4242 4242 4242', ls: '0.08em', mode: 'numeric' },
    { k: 'exp' as const, label: 'Caducidad', ph: 'MM/AA', ls: '0.08em', mode: 'numeric' },
    { k: 'cvc' as const, label: 'CVC', ph: '123', ls: '0.2em', mode: 'numeric' },
  ];

  return (
    <div className="kl-rise mx-auto max-w-[1000px]">
      <Seo title="Pago" description="Finaliza tu compra en Kloset de forma segura." path="/pago" noindex />
      <div className="mb-6 flex items-center gap-[14px] border-b border-ink pb-[14px]">
        <button
          type="button"
          onClick={() => navigate('/bolsa')}
          className="min-h-[44px] cursor-pointer border-none bg-transparent p-0 font-narrow text-[13px] uppercase tracking-[0.08em] text-soft"
        >
          ← Bolsa
        </button>
        <div className="flex-1" />
        <div className="flex gap-4">
          {pasos.map((st) => (
            <span
              key={st.label}
              className="border-b-2 pb-1 font-narrow text-[11.5px] uppercase tracking-[0.1em]"
              style={{
                color: st.activo ? 'var(--ink)' : 'var(--soft)',
                borderColor: st.activo ? 'var(--red)' : 'transparent',
              }}
            >
              {st.label}
            </span>
          ))}
        </div>
      </div>

      <div className="flex flex-col items-start gap-8 lg:flex-row lg:gap-14">
        <div className="w-full min-w-0 lg:flex-[1.25]">
          {estado === 'idle' && (
            <div>
              <h2 className="mb-3 font-display text-[30px] font-normal leading-tight tracking-[-0.025em]">
                Dirección de envío
              </h2>
              <div className="mb-6 border-t border-ink">
                <div className="border-b border-rule py-[14px]">
                  <label htmlFor="envio-direccion" className="font-narrow text-[11.5px] uppercase tracking-[0.12em] text-soft">
                    Dirección
                  </label>
                  <input
                    id="envio-direccion"
                    value={envio.direccion}
                    onChange={(e) => {
                      setEnvio((s) => ({ ...s, direccion: e.target.value }));
                      setError('');
                    }}
                    placeholder="Av. Larco 123, dpto. 4B"
                    className="min-h-10 w-full border-none bg-transparent py-2 text-[17px] text-ink outline-none"
                  />
                </div>
                <div className="border-b border-rule py-[14px]">
                  <label htmlFor="envio-ciudad" className="font-narrow text-[11.5px] uppercase tracking-[0.12em] text-soft">
                    Distrito
                  </label>
                  <input
                    id="envio-ciudad"
                    value={envio.ciudad}
                    onChange={(e) => {
                      setEnvio((s) => ({ ...s, ciudad: e.target.value }));
                      setError('');
                    }}
                    placeholder="Miraflores"
                    className="min-h-10 w-full border-none bg-transparent py-2 text-[17px] text-ink outline-none"
                  />
                </div>
                <div className="border-b border-rule py-[14px]">
                  <label htmlFor="envio-referencia" className="font-narrow text-[11.5px] uppercase tracking-[0.12em] text-soft">
                    Referencia (opcional)
                  </label>
                  <input
                    id="envio-referencia"
                    value={envio.referencia}
                    onChange={(e) => setEnvio((s) => ({ ...s, referencia: e.target.value }))}
                    placeholder="Frente al parque"
                    className="min-h-10 w-full border-none bg-transparent py-2 text-[17px] text-ink outline-none"
                  />
                </div>
              </div>

              <h2 className="mb-5 font-display text-[30px] font-normal leading-tight tracking-[-0.025em]">
                Pago seguro
              </h2>
              <div className="border-t border-ink">
                {campos.map((f) => (
                  <div key={f.k} className="border-b border-rule py-[14px]">
                    <div className="flex items-baseline justify-between gap-3">
                      <label htmlFor={`pago-${f.k}`} className="font-narrow text-[11.5px] uppercase tracking-[0.12em] text-soft">
                        {f.label}
                      </label>
                      {f.k === 'num' && (
                        <span className="font-narrow text-[11px] uppercase tracking-[0.08em] text-soft">
                          {marca(pago.num)}
                        </span>
                      )}
                    </div>
                    <input
                      id={`pago-${f.k}`}
                      inputMode={f.mode as 'text' | 'numeric'}
                      value={pago[f.k]}
                      onChange={(e) => escribir(f.k)(e.target.value)}
                      placeholder={f.ph}
                      className="min-h-10 w-full border-none bg-transparent py-2 text-[19px] text-ink outline-none"
                      style={{ letterSpacing: f.ls }}
                    />
                  </div>
                ))}
              </div>

              {error && (
                <div className="mt-[14px] border-l-[3px] border-red py-[6px] pl-[10px] font-narrow text-[12.5px] uppercase tracking-[0.06em] text-red">
                  {error}
                </div>
              )}

              <button
                type="button"
                onClick={pagar}
                className="mt-[22px] min-h-[58px] w-full cursor-pointer border-none bg-ink font-narrow text-base font-semibold uppercase tracking-[0.08em] text-paper hover:bg-red hover:text-[#F2F2F0]"
              >
                Pagar {money(subtotal)}
              </button>
              <div className="mt-4 font-narrow text-[11px] uppercase leading-[1.7] tracking-[0.1em] text-soft">
                Simulador · 4242 4242 4242 4242 aprueba · 4000 0000 0000 0002 rechaza · 4000 0000 0000 3220 pide
                3-D Secure
              </div>
            </div>
          )}

          {estado === 'processing' && (
            <div className="py-5">
              <div className="mb-[14px] font-narrow text-xs uppercase tracking-[0.14em] text-soft">Autorizando</div>
              <h2 className="mb-5 font-display text-[30px] font-normal leading-tight tracking-[-0.025em]">
                Hablando con tu banco…
              </h2>
              <div className="h-1 overflow-hidden bg-surface-2">
                <div className="h-full bg-red transition-[width] duration-500 ease-linear" style={{ width: progreso }} />
              </div>
              <div className="mt-[18px] border-t border-rule">
                {[
                  { t: 'Tokenizando tarjeta', s: 'ok' },
                  { t: 'Verificando fondos', s: progreso === '12%' ? '…' : 'ok' },
                  { t: 'Registrando pago', s: progreso === '92%' || progreso === '96%' ? 'ok' : '…' },
                ].map((l) => (
                  <div
                    key={l.t}
                    className="flex justify-between gap-3 border-b border-rule py-[11px] font-narrow text-xs uppercase tracking-[0.08em]"
                    style={{ color: l.s === 'ok' ? 'var(--ink)' : 'var(--soft)' }}
                  >
                    <span>{l.t}</span>
                    <span>{l.s}</span>
                  </div>
                ))}
              </div>
            </div>
          )}

          {estado === '3ds' && (
            <div className="border border-ink p-6">
              <div className="mb-3 font-narrow text-[11.5px] uppercase tracking-[0.14em] text-soft">
                Banco emisor · 3-D Secure
              </div>
              <h2 className="mb-[10px] font-display text-[26px] font-normal leading-tight tracking-[-0.02em]">
                Confirma con tu código
              </h2>
              <p className="mb-5 text-[14.5px] leading-[1.6] text-body">
                Enviamos un código de 6 dígitos al teléfono registrado. En esta demo, cualquier código de 6 dígitos
                es válido.
              </p>
              <input
                inputMode="numeric"
                value={otp}
                onChange={(e) => setOtp(soloDigitos(e.target.value).slice(0, 6))}
                placeholder="······"
                aria-label="Código de verificación"
                className="w-full border-none border-b border-b-ink bg-transparent pb-3 pt-[10px] text-center font-display text-[34px] tracking-[0.3em] text-ink outline-none"
              />
              {error && (
                <div className="mt-[14px] font-narrow text-[12.5px] uppercase tracking-[0.06em] text-red">{error}</div>
              )}
              <button
                type="button"
                onClick={confirmarOtp}
                className="mt-[22px] min-h-[56px] w-full cursor-pointer border-none bg-ink font-narrow text-[15px] font-semibold uppercase tracking-[0.08em] text-paper hover:bg-red hover:text-[#F2F2F0]"
              >
                Confirmar pago
              </button>
            </div>
          )}

          {estado === 'declined' && (
            <div className="border-t-[3px] border-red py-[22px]">
              <div className="mb-3 font-narrow text-xs uppercase tracking-[0.14em] text-red">
                Pago rechazado · código 51
              </div>
              <h2 className="mb-[10px] font-display text-[28px] font-normal leading-tight tracking-[-0.025em]">
                Tu banco no autorizó el cargo.
              </h2>
              <p className="mb-[22px] max-w-[44ch] text-[15px] leading-[1.65] text-body">
                No se ha cobrado nada. Prueba con otra tarjeta o vuelve a intentarlo en unos minutos; tu bolsa queda
                intacta.
              </p>
              <button
                type="button"
                onClick={() => {
                  setEstado('idle');
                  setPago({ holder: '', num: '', exp: '', cvc: '' });
                  setError('');
                }}
                className="min-h-[54px] cursor-pointer border-none bg-ink px-6 font-narrow text-[15px] font-semibold uppercase tracking-[0.08em] text-paper hover:bg-red hover:text-[#F2F2F0]"
              >
                Probar otra tarjeta
              </button>
            </div>
          )}

          {estado === 'error' && (
            <div className="border-t-[3px] border-red py-[22px]">
              <div className="mb-3 font-narrow text-xs uppercase tracking-[0.14em] text-red">
                No se pudo registrar el pedido
              </div>
              <h2 className="mb-[10px] font-display text-[28px] font-normal leading-tight tracking-[-0.025em]">
                {errorPedido}
              </h2>
              <p className="mb-[22px] max-w-[44ch] text-[15px] leading-[1.65] text-body">
                No se ha cobrado nada. Revisa tu bolsa y vuelve a intentarlo.
              </p>
              <button
                type="button"
                onClick={() => {
                  setEstado('idle');
                  setPago({ holder: '', num: '', exp: '', cvc: '' });
                  setError('');
                  setErrorPedido('');
                }}
                className="min-h-[54px] cursor-pointer border-none bg-ink px-6 font-narrow text-[15px] font-semibold uppercase tracking-[0.08em] text-paper hover:bg-red hover:text-[#F2F2F0]"
              >
                Volver a intentarlo
              </button>
            </div>
          )}
        </div>

        <div className="w-full border-t-[3px] border-red bg-surface p-[22px] lg:flex-1">
          <div className="mb-[14px] font-narrow text-[11.5px] uppercase tracking-[0.14em] text-soft">Resumen</div>
          {bolsa.map((it) => (
            <div key={it.id_carrito_item ?? `${it.id_producto}-${it.size}-${it.fit}`} className="flex justify-between gap-3 border-b border-rule py-[11px]">
              <div className="min-w-0">
                <div className="font-display text-[17px]">{it.name}</div>
                <div className="mt-[3px] text-xs text-soft">
                  Talla {it.size} · {it.fit}
                  {it.cantidad > 1 && ` · ×${it.cantidad}`}
                </div>
              </div>
              <span className="whitespace-nowrap font-display text-base">{money(it.price * it.cantidad)}</span>
            </div>
          ))}
          <div className="flex items-baseline justify-between pt-4">
            <span className="font-narrow text-xs uppercase tracking-[0.12em]">Total</span>
            <span className="font-display text-[26px] tracking-[-0.02em]">{money(subtotal)}</span>
          </div>
          <div className="mt-4 text-[13px] leading-[1.6] text-body">
            Envío gratis a {usuario?.correo ?? 'tu correo'}. Cambio de talla sin coste durante 30 días.
          </div>
        </div>
      </div>
    </div>
  );
}
