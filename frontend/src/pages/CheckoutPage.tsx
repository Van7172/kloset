import { Seo } from '../components/Seo';
import { nombreCortoDepartamento } from '../lib/locations';
import { useEffect, useRef, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useKloset } from '../store/KlosetContext';
import { money } from '../lib/fit';
import { api, type ApiDireccion } from '../services/api';
import { AddressForm } from '../components/AddressForm';

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
  const { bolsa, usuario, crearPedido, cargandoSesion, cargandoBolsa } = useKloset();
  const subtotal = bolsa.reduce((a, b) => a + b.price * b.cantidad, 0);

  const [estado, setEstado] = useState<Estado>('idle');
  const [progreso, setProgreso] = useState('0%');
  const [error, setError] = useState('');
  const [errorPedido, setErrorPedido] = useState('');
  const [otp, setOtp] = useState('');
  const [pago, setPago] = useState({ holder: '', num: '', exp: '', cvc: '' });
  const [envio, setEnvio] = useState({ direccion: '', ciudad: '', referencia: '' });
  const [direcciones, setDirecciones] = useState<ApiDireccion[]>([]);
  const [direccionId, setDireccionId] = useState<number | null>(null);
  const [elegirDireccion, setElegirDireccion] = useState(false);
  const [editarDireccion, setEditarDireccion] = useState(false);
  const [usarManual, setUsarManual] = useState(false);
  const [aceptaTerminos, setAceptaTerminos] = useState(false);
  const timers = useRef<number[]>([]);

  useEffect(() => {
    return () => timers.current.forEach((t) => window.clearTimeout(t));
  }, []);

  useEffect(() => {
    if (!usuario) return;
    void api.direcciones.listar().then((res) => {
      if (res.status !== 'success') return;
      setDirecciones(res.direcciones);
      setDireccionId(res.direcciones.find((d) => Boolean(d.principal))?.id ?? res.direcciones[0]?.id ?? null);
    }).catch(() => undefined);
  }, [usuario]);

  useEffect(() => {
    if (!cargandoSesion && !cargandoBolsa && bolsa.length === 0 && estado === 'idle') navigate('/bolsa');
  }, [bolsa.length, estado, navigate, cargandoSesion, cargandoBolsa]);

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
      !usarManual && direccionId ? { id_direccion: direccionId } : { direccion: envio.direccion, ciudad: envio.ciudad, referencia: envio.referencia },
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
    if ((usarManual || !direccionId) && (!envio.direccion.trim() || !envio.ciudad.trim())) return setError('La dirección y el distrito son obligatorios');
    const d = soloDigitos(pago.num);
    if (d.length < 15) return setError('Número de tarjeta incompleto');
    if (!/^\d{2}\/\d{2}$/.test(pago.exp)) return setError('Caducidad en formato MM/AA');
    if (soloDigitos(pago.cvc).length < 3) return setError('CVC incompleto');
    if (!pago.holder.trim()) return setError('Falta el titular de la tarjeta');
    if (!aceptaTerminos) return setError('Acepta los términos y condiciones para continuar');

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
  const entregaEstimada = new Intl.DateTimeFormat('es-PE', { weekday: 'long', day: 'numeric', month: 'long' }).format(new Date(Date.now() + 3 * 86400000));

  return (
    <div className="kl-rise mx-auto max-w-[1000px]">
      <Seo title="Pago de demostración" description="Prueba el flujo de compra de Kloset con tarjetas de demostración." path="/pago" noindex />
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
              <h2 className="mb-1 font-display text-[30px] font-normal leading-tight tracking-[-0.025em]">Pago seguro</h2>
              <p className="mb-5 text-sm text-body">Completa los datos para finalizar tu compra.</p>
              <div className="mb-3 border-t border-rule pt-3 font-narrow text-[12px] font-semibold uppercase tracking-[0.08em]">1. Dirección de entrega</div>
              {!usarManual && direccionId && <div className="mb-5 flex flex-wrap items-center justify-between gap-3 border border-rule p-4"><div><div className="font-display text-lg">{direcciones.find((d) => d.id === direccionId)?.nombre}</div><p className="mt-1 text-sm text-body">{direcciones.find((d) => d.id === direccionId)?.direccion}<br />{direcciones.find((d) => d.id === direccionId)?.ciudad}, {nombreCortoDepartamento(direcciones.find((d) => d.id === direccionId)?.departamento ?? 'Lima Metropolitana')}</p></div><button type="button" onClick={() => setElegirDireccion(true)} className="min-h-11 cursor-pointer border border-ink bg-transparent px-4 font-narrow text-xs font-semibold uppercase">Cambiar dirección</button></div>}
              {(!direccionId || usarManual) && <div className="mb-6 border-t border-ink">
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
              </div>}
              <div className="mb-6 flex flex-wrap gap-4 text-sm"><button type="button" onClick={() => setEditarDireccion(true)} className="cursor-pointer border-none bg-transparent p-0 text-ink underline">+ Guardar nueva dirección</button>{direccionId && <button type="button" onClick={() => setUsarManual(!usarManual)} className="cursor-pointer border-none bg-transparent p-0 text-soft underline">{usarManual ? 'Usar dirección guardada' : 'Usar otra dirección solo para este pedido'}</button>}</div>
              <div className="mb-6 border-t border-rule pt-3"><div className="mb-3 font-narrow text-[12px] font-semibold uppercase tracking-[0.08em]">2. Entrega</div><div className="flex gap-4 border-b border-rule pb-4"><span className="text-xl" aria-hidden="true">▣</span><div><strong className="block text-sm">Envío: Gratis</strong><span className="block text-sm text-body">Cobertura: Lima Metropolitana y Callao</span><span className="block text-sm text-body">Entrega estimada: {entregaEstimada}</span><span className="block text-sm text-body">Horario de entrega: 9:00 a 18:00</span></div></div><div className="mt-3 border border-rule bg-surface px-4 py-3 text-sm text-body"><span className="mr-2 font-semibold">●</span>Podrás consultar el estado de tu pedido desde tu cuenta.</div></div>

              <div className="mb-3 border-t border-rule pt-3 font-narrow text-[12px] font-semibold uppercase tracking-[0.08em]">3. Datos de pago</div>
              <div className="grid gap-3 border-t border-ink pt-3 sm:grid-cols-2">
                {campos.map((f) => (
                  <div key={f.k} className={`border-b border-rule py-[10px] ${f.k === 'holder' || f.k === 'num' ? 'sm:col-span-2' : ''}`}>
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

              <label className="mt-4 flex items-start gap-2 text-[13px] text-body"><input type="checkbox" checked={aceptaTerminos} onChange={(e) => { setAceptaTerminos(e.target.checked); setError(''); }} className="mt-0.5 h-4 w-4 accent-[var(--ink)]" />Acepto los <Link to="/legal/terminos" className="underline">Términos y Condiciones</Link> y la <Link to="/legal/privacidad" className="underline">Política de Privacidad</Link> de KLOSET.</label>

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
                Confirmar pago
              </button>
              <div className="mt-3 text-center text-xs text-soft">▣ Tu pago está protegido con encriptación SSL.</div>
            </div>
          )}

          {estado === 'processing' && (
            <div className="py-5">
              <div className="mb-[14px] font-narrow text-xs uppercase tracking-[0.14em] text-soft">Simulando autorización</div>
              <h2 className="mb-5 font-display text-[30px] font-normal leading-tight tracking-[-0.025em]">
                Preparando tu pedido…
              </h2>
              <div className="h-1 overflow-hidden bg-surface-2">
                <div className="h-full bg-red transition-[width] duration-500 ease-linear" style={{ width: progreso }} />
              </div>
              <div className="mt-[18px] border-t border-rule">
                {[
                  { t: 'Validando tarjeta de prueba', s: 'ok' },
                  { t: 'Simulando aprobación', s: progreso === '12%' ? '…' : 'ok' },
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
                Verificación de prueba
              </div>
              <h2 className="mb-[10px] font-display text-[26px] font-normal leading-tight tracking-[-0.02em]">
                Confirma con tu código
              </h2>
              <p className="mb-5 text-[14.5px] leading-[1.6] text-body">
                Ingresa cualquier código de 6 dígitos para continuar esta demostración. No se envía ningún mensaje ni se realiza un cargo.
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
                La tarjeta de prueba fue rechazada.
              </h2>
              <p className="mb-[22px] max-w-[44ch] text-[15px] leading-[1.65] text-body">
                No se ha cobrado nada. Prueba con otra tarjeta de demostración; tu bolsa queda
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
          <div className="mb-[14px] font-narrow text-[11.5px] font-semibold uppercase tracking-[0.14em] text-soft">Resumen del pedido</div>
          {bolsa.map((it) => (
            <div key={it.id_carrito_item ?? `${it.id_producto}-${it.size}-${it.fit}`} className="flex justify-between gap-3 border-b border-rule py-[11px]"><img src={it.imagen ?? ''} alt="" className="h-16 w-14 shrink-0 bg-paper object-cover" />
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
          <div className="flex justify-between border-b border-rule py-3 text-sm text-body"><span>Subtotal</span><span>{money(subtotal)}</span></div>
          <div className="flex justify-between border-b border-rule py-3 text-sm text-body"><span>Envío</span><span>Gratis</span></div>
          <div className="flex items-baseline justify-between pt-4">
            <span className="font-narrow text-xs uppercase tracking-[0.12em]">Total</span>
            <span className="font-display text-[30px] tracking-[-0.02em]">{money(subtotal)}</span>
          </div>
          <div className="mt-4 border border-red/20 bg-paper px-3 py-3 text-[13px] leading-[1.5] text-body"><strong className="block text-ink">Entrega estimada: {entregaEstimada}</strong>Lima Metropolitana y Callao.</div>
        </div>
      </div>
      {elegirDireccion && <div className="fixed inset-0 z-[100] flex items-center justify-center overflow-y-auto p-4" role="presentation" onMouseDown={(e) => { if (e.target === e.currentTarget) setElegirDireccion(false); }}><div role="dialog" aria-modal="true" aria-label="Cambiar dirección" className="my-auto max-h-[calc(100vh-2rem)] w-full max-w-[540px] overflow-y-auto border border-rule bg-paper p-6 shadow-2xl"><div className="mb-4 flex justify-between"><h2 className="font-display text-[30px]">Cambiar dirección</h2><button type="button" aria-label="Cerrar" onClick={() => setElegirDireccion(false)} className="min-h-10 min-w-10 border-none bg-transparent text-2xl">×</button></div><p className="mb-4 text-sm text-body">Selecciona una dirección guardada.</p>{direcciones.map((d) => <button key={d.id} type="button" onClick={() => { setDireccionId(d.id); setUsarManual(false); setElegirDireccion(false); }} className="mb-2 flex w-full items-start gap-3 border border-rule bg-transparent p-4 text-left"><span className="text-red">{direccionId === d.id ? '◉' : '○'}</span><span><strong>{d.nombre}</strong><span className="mt-1 block text-sm text-body">{d.direccion}<br />{d.ciudad}, {nombreCortoDepartamento(d.departamento)}</span></span></button>)}<button type="button" onClick={() => { setElegirDireccion(false); setEditarDireccion(true); }} className="mt-3 min-h-11 w-full cursor-pointer border border-ink bg-transparent font-narrow text-xs font-semibold uppercase">+ Añadir nueva dirección</button></div></div>}
      {editarDireccion && <AddressForm onClose={() => setEditarDireccion(false)} onSaved={(lista, id) => { setDirecciones(lista); setDireccionId(id); setUsarManual(false); setEditarDireccion(false); }} />}
    </div>
  );
}
