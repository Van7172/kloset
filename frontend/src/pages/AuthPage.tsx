import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useKloset } from '../store/KlosetContext';

const VENTAJAS = [
  { t: 'Tu talla, recordada', d: 'Guardamos tus cuatro medidas y las aplicamos a cada prenda del catálogo.' },
  { t: 'Avatar propio', d: 'Pruebas la prenda sobre tu silueta antes de pagar, en los tres cortes.' },
  { t: 'Cambios sin fricción', d: '30 días para cambiar de talla, con la ficha de ajuste de tu pedido.' },
];

export function AuthPage() {
  const navigate = useNavigate();
  const { entrar, registrarse, intencion, setIntencion, añadirABolsa } = useKloset();

  const [modo, setModo] = useState<'login' | 'signup'>('login');
  const [nombre, setNombre] = useState('');
  const [correo, setCorreo] = useState('');
  const [pass, setPass] = useState('');
  const [error, setError] = useState('');
  const [enviando, setEnviando] = useState(false);

  const signup = modo === 'signup';

  const enviar = async () => {
    setError('');
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo)) return setError('Introduce un correo válido');
    if (pass.length < 8) return setError('La contraseña necesita 8 caracteres o más');
    if (signup && !nombre.trim()) return setError('Dinos cómo te llamas');

    setEnviando(true);
    const fallo = signup ? await registrarse(nombre.trim(), correo, pass) : await entrar(correo, pass);
    setEnviando(false);
    if (fallo) return setError(fallo);

    const destino = intencion;
    if (destino?.item) añadirABolsa(destino.item);
    setIntencion(null);
    navigate(destino?.then === 'checkout' ? '/pago' : destino?.then === 'cart' ? '/bolsa' : '/cuenta');
  };

  const campos = signup
    ? [
        { k: 'nombre', label: 'Nombre', type: 'text', value: nombre, set: setNombre, ph: 'Como quieres que te llamemos', auto: 'name' },
        { k: 'correo', label: 'Correo', type: 'email', value: correo, set: setCorreo, ph: 'tu@correo.com', auto: 'email' },
        { k: 'pass', label: 'Contraseña', type: 'password', value: pass, set: setPass, ph: 'Mínimo 8 caracteres', auto: 'new-password' },
      ]
    : [
        { k: 'correo', label: 'Correo', type: 'email', value: correo, set: setCorreo, ph: 'tu@correo.com', auto: 'email' },
        { k: 'pass', label: 'Contraseña', type: 'password', value: pass, set: setPass, ph: 'Tu contraseña', auto: 'current-password' },
      ];

  return (
    <div className="kl-rise mx-auto flex max-w-[620px] flex-col items-stretch gap-8 lg:max-w-[1000px] lg:flex-row lg:gap-14">
      <div className="min-w-0 flex-1">
        <div className="mb-[14px] mt-[6px] font-narrow text-xs uppercase tracking-[0.14em] text-soft">
          {signup ? 'Crear cuenta' : 'Acceder'}
        </div>
        <h2 className="mb-3 font-display text-[34px] font-normal leading-[1.08] tracking-[-0.025em]">
          {signup ? 'Empieza por tus medidas.' : 'Vuelve a tu talla.'}
        </h2>
        <p className="mb-6 max-w-[44ch] text-[15px] leading-[1.65] text-body">
          {signup
            ? 'Con una cuenta guardamos tu perfil corporal y tu avatar, y cada prenda se muestra ya en tu talla.'
            : 'Entra para recuperar tu perfil de medidas, tu avatar y el historial de pedidos.'}
        </p>

        <div className="mb-[22px] flex border border-ink">
          {(['login', 'signup'] as const).map((m, i) => (
            <button
              key={m}
              type="button"
              onClick={() => {
                setModo(m);
                setError('');
              }}
              className="min-h-[46px] flex-1 cursor-pointer border-none font-narrow text-[13.5px] font-semibold uppercase tracking-[0.06em] transition-colors"
              style={{
                background: modo === m ? 'var(--ink)' : 'transparent',
                color: modo === m ? 'var(--paper)' : 'var(--soft)',
                borderRight: i === 0 ? '1px solid var(--ink)' : 'none',
              }}
            >
              {m === 'login' ? 'Entrar' : 'Crear cuenta'}
            </button>
          ))}
        </div>

        <div className="border-t border-ink">
          {campos.map((f) => (
            <div key={f.k} className="border-b border-rule py-[14px]">
              <label
                htmlFor={`auth-${f.k}`}
                className="mb-[6px] block font-narrow text-[11.5px] uppercase tracking-[0.12em] text-soft"
              >
                {f.label}
              </label>
              <input
                id={`auth-${f.k}`}
                type={f.type}
                value={f.value}
                onChange={(e) => f.set(e.target.value)}
                placeholder={f.ph}
                autoComplete={f.auto}
                className="min-h-10 w-full border-none bg-transparent py-2 text-[17px] text-ink outline-none"
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
          onClick={enviar}
          disabled={enviando}
          className="mt-[22px] min-h-[58px] w-full cursor-pointer border-none bg-ink font-narrow text-base font-semibold uppercase tracking-[0.08em] text-paper hover:bg-red hover:text-[#F2F2F0] disabled:opacity-50"
        >
          {enviando ? 'Un momento…' : signup ? 'Crear mi cuenta' : 'Entrar'}
        </button>
        <p className="mt-[14px] text-[12.5px] leading-[1.55] text-soft">
          Guardamos tu perfil de medidas en esta cuenta. Recibirás la confirmación de cada pedido en tu correo.
        </p>
      </div>

      <div className="border-t-[3px] border-red bg-surface p-6 lg:flex-[0_0_320px]">
        <div className="mb-4 font-narrow text-[11.5px] uppercase tracking-[0.14em] text-soft">Con tu cuenta</div>
        {VENTAJAS.map((p) => (
          <div key={p.t} className="border-b border-rule py-[13px]">
            <div className="mb-1 font-display text-lg">{p.t}</div>
            <div className="text-[13px] leading-[1.55] text-body">{p.d}</div>
          </div>
        ))}
      </div>
    </div>
  );
}
