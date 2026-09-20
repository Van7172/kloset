import { Seo } from '../components/Seo';
import { useEffect, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useKloset } from '../store/KlosetContext';

const VENTAJAS = [
  { t: 'Tu talla, recordada', d: 'Guardamos tus cuatro medidas y las aplicamos a cada prenda del catálogo.' },
  { t: 'Avatar propio', d: 'Pruebas la prenda sobre tu silueta antes de pagar, en los tres cortes.' },
  { t: 'Cambios sin fricción', d: '30 días para cambiar de talla, con la ficha de ajuste de tu pedido.' },
];

const VERIFY_PERKS = [
  { t: 'Cuenta segura', d: 'Verificamos tu correo para proteger tu cuenta.' },
  { t: 'Tus datos guardados', d: 'Tu perfil de medidas y preferencias quedará listo.' },
  { t: 'Listo para comprar', d: 'Una vez verificado, podrás explorar y comprar en Kloset.' },
];

const FORGOT1_PERKS = [
  { t: 'Cuenta segura', d: 'Te enviamos un código a tu correo para proteger tu cuenta.' },
  { t: 'Recupera el acceso', d: 'Sigue unos simples pasos y vuelve a disfrutar de Kloset.' },
  { t: 'Tus datos protegidos', d: 'Tu información permanece segura con nosotros.' },
];

const FORGOT2_PERKS = [
  { t: 'Código enviado', d: 'Revisa tu bandeja de entrada (y spam) para encontrar el código.' },
  { t: 'Nueva contraseña', d: 'Crea una contraseña segura para seguir comprando en Kloset.' },
  { t: 'Tu cuenta protegida', d: 'Mantenemos tus datos siempre seguros.' },
];

type Paso = 'form' | 'verify' | 'forgot1' | 'forgot2';

function fmtReloj(s: number) {
  return `${String(Math.floor(s / 60)).padStart(2, '0')}:${String(s % 60).padStart(2, '0')}`;
}

function useCuentaAtras(activo: boolean, segundos = 45) {
  const [restante, setRestante] = useState(segundos);
  useEffect(() => {
    if (!activo) return;
    setRestante(segundos);
    const id = setInterval(() => setRestante((r) => (r > 0 ? r - 1 : 0)), 1000);
    return () => clearInterval(id);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [activo]);
  return [restante, () => setRestante(segundos)] as const;
}

function CodigoDigitos({ cantidad, valores, onChange }: { cantidad: number; valores: string[]; onChange: (i: number, v: string) => void }) {
  const refs = useRef<(HTMLInputElement | null)[]>([]);
  return (
    <div className="mb-4 flex justify-center gap-[10px]">
      {Array.from({ length: cantidad }).map((_, i) => (
        <input
          key={i}
          ref={(el) => {
            refs.current[i] = el;
          }}
          value={valores[i] ?? ''}
          onChange={(e) => {
            const v = e.target.value.replace(/\D/g, '').slice(-1);
            onChange(i, v);
            if (v && refs.current[i + 1]) refs.current[i + 1]?.focus();
          }}
          onKeyDown={(e) => {
            if (e.key === 'Backspace' && !valores[i] && refs.current[i - 1]) refs.current[i - 1]?.focus();
          }}
          maxLength={1}
          inputMode="numeric"
          className="h-[56px] w-[52px] border border-ink bg-paper text-center font-display text-2xl text-ink outline-none"
        />
      ))}
    </div>
  );
}

export function AuthPage() {
  const navigate = useNavigate();
  const { entrar, registrarse, intencion, setIntencion, añadirABolsa } = useKloset();

  const [paso, setPaso] = useState<Paso>('form');
  const [modo, setModo] = useState<'login' | 'signup'>('login');
  const [nombre, setNombre] = useState('');
  const [correo, setCorreo] = useState('');
  const [pass, setPass] = useState('');
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [enviando, setEnviando] = useState(false);

  const [pendiente, setPendiente] = useState<{ nombre: string; correo: string; pass: string } | null>(null);
  const [verifyDigits, setVerifyDigits] = useState<string[]>(['', '', '', '', '', '']);
  const [verifyError, setVerifyError] = useState('');
  const [resendLeft, resetResend] = useCuentaAtras(paso === 'verify');

  const [forgotEmail, setForgotEmail] = useState('');
  const [forgotDigits, setForgotDigits] = useState<string[]>(['', '', '', '']);
  const [forgotPass, setForgotPass] = useState('');
  const [forgotPass2, setForgotPass2] = useState('');
  const [forgotError, setForgotError] = useState('');
  const [forgotResendLeft, resetForgotResend] = useCuentaAtras(paso === 'forgot2');

  const signup = modo === 'signup';

  const volverAlForm = () => {
    setPaso('form');
    setError('');
    setForgotError('');
    setVerifyError('');
  };

  const enviar = async () => {
    setError('');
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo)) return setError('Introduce un correo válido');
    if (pass.length < 8) return setError('La contraseña necesita 8 caracteres o más');
    if (signup && !nombre.trim()) return setError('Dinos cómo te llamas');

    if (signup) {
      setPendiente({ nombre: nombre.trim(), correo, pass });
      setVerifyDigits(['', '', '', '', '', '']);
      setVerifyError('');
      setPaso('verify');
      return;
    }

    setEnviando(true);
    const fallo = await entrar(correo, pass);
    setEnviando(false);
    if (fallo) return setError(fallo);
    completarIngreso();
  };

  const completarIngreso = () => {
    const destino = intencion;
    if (destino?.item) añadirABolsa(destino.item);
    setIntencion(null);
    navigate(destino?.then === 'checkout' ? '/pago' : destino?.then === 'cart' ? '/bolsa' : '/cuenta');
  };

  const verificarCodigo = async () => {
    if (verifyDigits.some((d) => !d)) return setVerifyError('Ingresa el código completo de 6 dígitos');
    if (!pendiente) return setPaso('form');
    setEnviando(true);
    const fallo = await registrarse(pendiente.nombre, pendiente.correo, pendiente.pass);
    setEnviando(false);
    if (fallo) return setVerifyError(fallo);
    completarIngreso();
  };

  const enviarCodigoRecuperacion = () => {
    setForgotError('');
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(forgotEmail)) return setForgotError('Introduce un correo válido');
    setForgotDigits(['', '', '', '']);
    setPaso('forgot2');
  };

  const confirmarNuevaContraseña = () => {
    setForgotError('');
    if (forgotDigits.some((d) => !d)) return setForgotError('Ingresa el código completo');
    if (forgotPass.length < 8) return setForgotError('La contraseña necesita 8 caracteres o más');
    if (forgotPass !== forgotPass2) return setForgotError('Las contraseñas no coinciden');
    setModo('login');
    setCorreo(forgotEmail);
    setPass('');
    setNotice('Contraseña actualizada · inicia sesión con tu nueva contraseña');
    setPaso('form');
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

  if (paso === 'forgot1') {
    return (
      <div className="kl-rise mx-auto flex max-w-[620px] flex-col items-stretch gap-8 lg:max-w-[1000px] lg:flex-row lg:gap-14">
        <Seo title="Recuperar contraseña" description="Recupera el acceso a tu cuenta Kloset." path="/entrar" noindex />
        <div className="min-w-0 flex-1">
          <button type="button" onClick={volverAlForm} className="mb-[18px] min-h-10 cursor-pointer border-none bg-transparent p-0 font-narrow text-[12.5px] font-semibold uppercase tracking-[0.08em] text-soft">
            ← Volver al inicio de sesión
          </button>
          <div className="mb-[14px] font-narrow text-xs uppercase tracking-[0.14em] text-soft">Recuperar contraseña</div>
          <h1 className="mb-[14px] font-display text-[34px] font-normal leading-[1.08] tracking-[-0.025em]">¿Olvidaste tu contraseña?</h1>
          <p className="mb-6 max-w-[44ch] text-[15px] leading-[1.65] text-body">
            Ingresa tu correo y te enviaremos un código para restablecer tu contraseña.
          </p>

          <div className="mb-[22px] border-t border-ink">
            <div className="border-b border-rule py-[14px]">
              <label className="mb-[6px] block font-narrow text-[11.5px] uppercase tracking-[0.12em] text-soft">Correo electrónico</label>
              <input
                type="email"
                value={forgotEmail}
                onChange={(e) => {
                  setForgotEmail(e.target.value);
                  setForgotError('');
                }}
                placeholder="tu@correo.com"
                className="min-h-10 w-full border-none bg-transparent py-2 text-[17px] text-ink outline-none"
              />
            </div>
          </div>

          {forgotError && (
            <div className="mb-4 border-l-[3px] border-red py-[6px] pl-[10px] font-narrow text-[12.5px] uppercase tracking-[0.06em] text-red">
              {forgotError}
            </div>
          )}

          <button
            type="button"
            onClick={enviarCodigoRecuperacion}
            className="min-h-[58px] w-full cursor-pointer border-none bg-ink font-narrow text-base font-semibold uppercase tracking-[0.08em] text-paper hover:bg-red hover:text-[#F2F2F0]"
          >
            Enviar código
          </button>
          <p className="mt-[18px]">
            <button type="button" onClick={volverAlForm} className="cursor-pointer border-none bg-transparent p-0 text-[13.5px] text-soft underline">
              Volver al inicio de sesión
            </button>
          </p>
        </div>

        <div className="border-t-[3px] border-red bg-surface p-6 lg:flex-[0_0_320px]">
          <div className="mb-4 font-narrow text-[11.5px] uppercase tracking-[0.14em] text-soft">Tu cuenta Kloset</div>
          {FORGOT1_PERKS.map((p) => (
            <div key={p.t} className="border-b border-rule py-[13px]">
              <div className="mb-1 font-display text-lg">{p.t}</div>
              <div className="text-[13px] leading-[1.55] text-body">{p.d}</div>
            </div>
          ))}
        </div>
      </div>
    );
  }

  if (paso === 'forgot2') {
    return (
      <div className="kl-rise mx-auto flex max-w-[620px] flex-col items-stretch gap-8 lg:max-w-[1000px] lg:flex-row lg:gap-14">
        <Seo title="Verifica tu correo" description="Confirma el código y crea una nueva contraseña." path="/entrar" noindex />
        <div className="min-w-0 flex-1">
          <button type="button" onClick={volverAlForm} className="mb-[18px] min-h-10 cursor-pointer border-none bg-transparent p-0 font-narrow text-[12.5px] font-semibold uppercase tracking-[0.08em] text-soft">
            ← Volver al inicio de sesión
          </button>
          <div className="mb-[14px] font-narrow text-xs uppercase tracking-[0.14em] text-soft">Recuperar contraseña</div>
          <h1 className="mb-4 font-display text-[34px] font-normal leading-[1.08] tracking-[-0.025em]">Verifica tu correo</h1>
          <p className="m-0 text-[15px] leading-[1.65] text-body">Ingresa el código de 4 dígitos que enviamos a</p>
          <p className="mb-[22px] mt-0 text-base font-semibold text-ink">{forgotEmail}</p>

          <div className="mb-2 font-narrow text-[11.5px] uppercase tracking-[0.12em] text-soft">Código de verificación</div>
          <CodigoDigitos
            cantidad={4}
            valores={forgotDigits}
            onChange={(i, v) => setForgotDigits((d) => d.map((x, idx) => (idx === i ? v : x)))}
          />
          <p className="mb-6 text-sm text-body">
            ¿No recibiste el código?{' '}
            <button
              type="button"
              disabled={forgotResendLeft > 0}
              onClick={resetForgotResend}
              className="cursor-pointer border-none bg-transparent p-0 font-semibold disabled:cursor-default"
              style={{ color: forgotResendLeft > 0 ? 'var(--soft)' : 'var(--ink)' }}
            >
              {forgotResendLeft > 0 ? `Reenviar código (${fmtReloj(forgotResendLeft)})` : 'Reenviar código'}
            </button>
          </p>

          <div className="border-t border-ink">
            <div className="border-b border-rule py-[14px]">
              <label className="mb-[6px] block font-narrow text-[11.5px] uppercase tracking-[0.12em] text-soft">Nueva contraseña</label>
              <input
                type="password"
                value={forgotPass}
                onChange={(e) => {
                  setForgotPass(e.target.value);
                  setForgotError('');
                }}
                placeholder="Mínimo 8 caracteres"
                className="min-h-10 w-full border-none bg-transparent py-2 text-[17px] text-ink outline-none"
              />
            </div>
            <div className="border-b border-rule py-[14px]">
              <label className="mb-[6px] block font-narrow text-[11.5px] uppercase tracking-[0.12em] text-soft">Confirmar nueva contraseña</label>
              <input
                type="password"
                value={forgotPass2}
                onChange={(e) => {
                  setForgotPass2(e.target.value);
                  setForgotError('');
                }}
                placeholder="Repite tu contraseña"
                className="min-h-10 w-full border-none bg-transparent py-2 text-[17px] text-ink outline-none"
              />
            </div>
          </div>

          {forgotError && (
            <div className="my-4 border-l-[3px] border-red py-[6px] pl-[10px] font-narrow text-[12.5px] uppercase tracking-[0.06em] text-red">
              {forgotError}
            </div>
          )}

          <button
            type="button"
            onClick={confirmarNuevaContraseña}
            className="mt-[22px] min-h-[58px] w-full cursor-pointer border-none bg-ink font-narrow text-base font-semibold uppercase tracking-[0.08em] text-paper hover:bg-red hover:text-[#F2F2F0]"
          >
            Confirmar
          </button>
          <p className="mt-[18px]">
            <button type="button" onClick={volverAlForm} className="cursor-pointer border-none bg-transparent p-0 text-[13.5px] text-soft underline">
              Volver al inicio de sesión
            </button>
          </p>
        </div>

        <div className="border-t-[3px] border-red bg-surface p-6 lg:flex-[0_0_320px]">
          <div className="mb-4 font-narrow text-[11.5px] uppercase tracking-[0.14em] text-soft">Tu cuenta Kloset</div>
          {FORGOT2_PERKS.map((p) => (
            <div key={p.t} className="border-b border-rule py-[13px]">
              <div className="mb-1 font-display text-lg">{p.t}</div>
              <div className="text-[13px] leading-[1.55] text-body">{p.d}</div>
            </div>
          ))}
        </div>
      </div>
    );
  }

  if (paso === 'verify') {
    return (
      <div className="kl-rise mx-auto flex max-w-[620px] flex-col items-stretch gap-8 lg:max-w-[1000px] lg:flex-row lg:gap-14">
        <Seo title="Verifica tu correo" description="Confirma tu cuenta Kloset con el código enviado a tu correo." path="/entrar" noindex />
        <div className="min-w-0 flex-1 text-center">
          <button type="button" onClick={volverAlForm} className="mb-[18px] min-h-10 cursor-pointer border-none bg-transparent p-0 font-narrow text-[12.5px] font-semibold uppercase tracking-[0.08em] text-soft">
            ← Crear cuenta
          </button>
          <div className="mb-[14px] font-narrow text-xs uppercase tracking-[0.14em] text-soft">Paso 2 de 2</div>
          <h1 className="mb-4 font-display text-[32px] font-normal leading-[1.1] tracking-[-0.025em]">Verifica tu correo electrónico</h1>
          <p className="m-0 text-[15.5px] leading-[1.6] text-body">Hemos enviado un código de verificación a</p>
          <p className="mb-[18px] mt-[6px] text-base font-semibold text-ink">{pendiente?.correo}</p>
          <p className="mb-[22px] text-sm text-body">Ingresa el código de 6 dígitos para confirmar tu cuenta.</p>

          <CodigoDigitos
            cantidad={6}
            valores={verifyDigits}
            onChange={(i, v) => setVerifyDigits((d) => d.map((x, idx) => (idx === i ? v : x)))}
          />

          {verifyError && (
            <div className="mb-[14px] font-narrow text-[12.5px] uppercase tracking-[0.06em] text-red">{verifyError}</div>
          )}

          <p className="mb-[22px] text-sm text-body">
            ¿No recibiste el código?{' '}
            <button
              type="button"
              disabled={resendLeft > 0}
              onClick={resetResend}
              className="cursor-pointer border-none bg-transparent p-0 font-semibold disabled:cursor-default"
              style={{ color: resendLeft > 0 ? 'var(--soft)' : 'var(--ink)' }}
            >
              {resendLeft > 0 ? `Reenviar código (${fmtReloj(resendLeft)})` : 'Reenviar código'}
            </button>
          </p>

          <button
            type="button"
            onClick={verificarCodigo}
            disabled={enviando}
            className="min-h-[58px] w-full cursor-pointer border-none bg-ink font-narrow text-base font-semibold uppercase tracking-[0.08em] text-paper hover:bg-red hover:text-[#F2F2F0] disabled:opacity-50"
          >
            {enviando ? 'Un momento…' : 'Verificar código'}
          </button>
          <p className="mt-[18px]">
            <button type="button" onClick={volverAlForm} className="cursor-pointer border-none bg-transparent p-0 text-[13.5px] text-soft underline">
              Volver
            </button>
          </p>
        </div>

        <div className="border-t-[3px] border-red bg-surface p-6 text-left lg:flex-[0_0_320px]">
          <div className="mb-4 font-narrow text-[11.5px] uppercase tracking-[0.14em] text-soft">Tu cuenta Kloset</div>
          {VERIFY_PERKS.map((p) => (
            <div key={p.t} className="border-b border-rule py-[13px]">
              <div className="mb-1 font-display text-lg">{p.t}</div>
              <div className="text-[13px] leading-[1.55] text-body">{p.d}</div>
            </div>
          ))}
        </div>
      </div>
    );
  }

  return (
    <div className="kl-rise mx-auto flex max-w-[620px] flex-col items-stretch gap-8 lg:max-w-[1000px] lg:flex-row lg:gap-14">
      <Seo title="Entrar o crear cuenta" description="Accede a tu cuenta de Kloset para guardar tus medidas, tu avatar y tus pedidos." path="/entrar" noindex />
      <div className="min-w-0 flex-1">
        <div className="mb-[14px] mt-[6px] font-narrow text-xs uppercase tracking-[0.14em] text-soft">
          {signup ? 'Crear cuenta' : 'Acceder'}
        </div>
        <h1 className="mb-3 font-display text-[34px] font-normal leading-[1.08] tracking-[-0.025em]">
          {signup ? 'Empieza por tus medidas.' : 'Vuelve a tu talla.'}
        </h1>
        <p className="mb-6 max-w-[44ch] text-[15px] leading-[1.65] text-body">
          {signup
            ? 'Con una cuenta guardamos tu perfil corporal y tu avatar, y cada prenda se muestra ya en tu talla.'
            : 'Entra para recuperar tu perfil de medidas, tu avatar y el historial de pedidos.'}
        </p>

        {notice && (
          <div className="mb-[18px] border-l-[3px] border-red py-[6px] pl-[10px] font-narrow text-[12.5px] uppercase tracking-[0.06em] text-ink">
            {notice}
          </div>
        )}

        <div className="mb-[22px] flex border border-ink">
          {(['login', 'signup'] as const).map((m, i) => (
            <button
              key={m}
              type="button"
              onClick={() => {
                setModo(m);
                setError('');
                setNotice('');
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
              <div className="flex items-baseline justify-between gap-3">
                <label htmlFor={`auth-${f.k}`} className="mb-[6px] block font-narrow text-[11.5px] uppercase tracking-[0.12em] text-soft">
                  {f.label}
                </label>
                {!signup && f.k === 'pass' && (
                  <button
                    type="button"
                    onClick={() => {
                      setForgotEmail(correo);
                      setForgotError('');
                      setPaso('forgot1');
                    }}
                    className="cursor-pointer whitespace-nowrap border-none bg-transparent p-0 text-[12.5px] text-ink underline"
                  >
                    ¿Olvidaste tu contraseña?
                  </button>
                )}
              </div>
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
