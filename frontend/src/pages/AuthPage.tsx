import { useEffect, useRef, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Seo } from '../components/Seo';
import { cumplePoliticaContrasena, passwordRequirements } from '../lib/password';
import { api } from '../services/api';
import { useKloset } from '../store/KlosetContext';

function CampoContrasena({ id, etiqueta, valor, onChange, visible, alternar, autocomplete, placeholder }: {
  id: string;
  etiqueta: string;
  valor: string;
  onChange: (valor: string) => void;
  visible: boolean;
  alternar: () => void;
  autocomplete: string;
  placeholder: string;
}) {
  return <div className="border-b border-rule py-3">
    <label htmlFor={id} className="block font-narrow text-xs uppercase tracking-[0.12em] text-soft">{etiqueta}</label>
    <div className="flex items-center">
      <input id={id} type={visible ? 'text' : 'password'} value={valor} onChange={(event) => onChange(event.target.value)} autoComplete={autocomplete} minLength={8} maxLength={128} className="min-h-10 w-full border-none bg-transparent text-base text-ink outline-none" placeholder={placeholder} />
      <button type="button" onClick={alternar} aria-label={visible ? 'Ocultar contraseña' : 'Mostrar contraseña'} aria-pressed={visible} title={visible ? 'Ocultar contraseña' : 'Mostrar contraseña'} className="flex h-10 w-10 shrink-0 cursor-pointer items-center justify-center border-none bg-transparent text-soft hover:text-ink">
        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
          {visible ? <><path d="M3 3l18 18" /><path d="M10.6 10.6a2 2 0 0 0 2.8 2.8" /><path d="M9.9 5.2A11 11 0 0 1 12 5c6.5 0 10 7 10 7a15.6 15.6 0 0 1-4 4.8" /><path d="M6.6 6.6C3.6 8.3 2 12 2 12s3.5 7 10 7a10 10 0 0 0 3-.5" /></> : <><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" /><circle cx="12" cy="12" r="3" /></>}
        </svg>
      </button>
    </div>
  </div>;
}

export function AuthPage() {
  const navigate = useNavigate();
  const { entrar, intencion, setIntencion, añadirABolsa } = useKloset();
  const [modo, setModo] = useState<'login' | 'signup'>('login');
  const [nombre, setNombre] = useState('');
  const [correo, setCorreo] = useState('');
  const [password, setPassword] = useState('');
  const [confirmarPassword, setConfirmarPassword] = useState('');
  const [mostrarPassword, setMostrarPassword] = useState(false);
  const [mostrarConfirmar, setMostrarConfirmar] = useState(false);
  const [verificandoRegistro, setVerificandoRegistro] = useState(false);
  const [codigo, setCodigo] = useState(['', '', '', '', '', '']);
  const [contador, setContador] = useState(0);
  const camposCodigo = useRef<Array<HTMLInputElement | null>>([]);
  const [mensaje, setMensaje] = useState('');
  const [error, setError] = useState('');
  const [enviando, setEnviando] = useState(false);

  useEffect(() => {
    if (contador <= 0) return;
    const timer = window.setTimeout(() => setContador((actual) => Math.max(0, actual - 1)), 1000);
    return () => window.clearTimeout(timer);
  }, [contador]);

  async function continuarDespuesDeEntrar() {
    if (intencion?.item) {
      const errorBolsa = await añadirABolsa(intencion.item);
      if (errorBolsa) {
        setError(errorBolsa);
        setEnviando(false);
        return;
      }
    }
    const destino = intencion?.then === 'checkout' ? '/pago' : intencion?.then === 'cart' ? '/bolsa' : '/cuenta';
    setIntencion(null);
    navigate(destino, { replace: true });
  }

  const enviar = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setError('');
    setMensaje('');
    if (modo === 'signup' && nombre.trim().length < 2) return setError('Escribe tu nombre.');
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo.trim())) return setError('Escribe un correo válido.');
    if (!password) return setError('Escribe tu contraseña.');
    if (!cumplePoliticaContrasena(password)) return setError(`La contraseña debe tener ${passwordRequirements}`);
    if (modo === 'signup' && password !== confirmarPassword) return setError('Las contraseñas no coinciden.');

    if (modo === 'signup') {
      setEnviando(true);
      try {
        const respuesta = await api.register(nombre.trim(), correo.trim(), password, confirmarPassword);
        if (respuesta.status !== 'success') {
          setError(respuesta.message || 'No pudimos iniciar el registro.');
          return;
        }
        setVerificandoRegistro(true);
        setCodigo(['', '', '', '', '', '']);
        setContador(respuesta.reenviar_en ?? 60);
        setMensaje('');
      } catch {
        setError('No hay conexión con la API.');
      } finally {
        setEnviando(false);
      }
      return;
    }

    setEnviando(true);
    const fallo = await entrar(correo.trim(), password);
    if (fallo) {
      setEnviando(false);
      return setError(fallo);
    }
    await continuarDespuesDeEntrar();
  };

  function cambiarDigito(indice: number, valor: string) {
    const digitos = valor.replace(/\D/g, '');
    const nuevos = [...codigo];
    if (digitos.length > 1) {
      const pegados = [...digitos.slice(0, 6)];
      while (pegados.length < 6) pegados.push('');
      setCodigo(pegados);
      camposCodigo.current[Math.min(digitos.length, 6) - 1]?.focus();
      return;
    }
    nuevos[indice] = digitos;
    setCodigo(nuevos);
    if (digitos && indice < 5) camposCodigo.current[indice + 1]?.focus();
  }

  async function reenviarCodigo() {
    setError('');
    setMensaje('');
    setEnviando(true);
    try {
      const respuesta = await api.registro.reenviar(correo.trim());
      if (respuesta.status !== 'success') {
        setError(respuesta.message || 'No pudimos reenviar el código.');
        return;
      }
      setContador(respuesta.reenviar_en ?? 60);
      setMensaje(respuesta.message === 'Código reenviado.' ? `Enviamos un nuevo código de 6 dígitos a ${correo.trim()}.` : respuesta.message || 'Espera antes de solicitar otro código.');
    } catch {
      setError('No pudimos reenviar el código. Inténtalo nuevamente.');
    } finally {
      setEnviando(false);
    }
  }

  async function verificarCodigo(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError('');
    const codigoCompleto = codigo.join('');
    if (codigoCompleto.length !== 6) return setError('Ingresa el código de 6 dígitos.');
    setEnviando(true);
    try {
      const respuesta = await api.registro.verificar(correo.trim(), codigoCompleto);
      if (respuesta.status !== 'success') {
        setError(respuesta.message || 'El código no es válido o ha vencido.');
        return;
      }
      const fallo = await entrar(correo.trim(), password);
      if (fallo) {
        setModo('login');
        setVerificandoRegistro(false);
        setError('Correo verificado. Inicia sesión con tu nueva cuenta.');
        return;
      }
      await continuarDespuesDeEntrar();
    } catch {
      setError('No pudimos verificar el correo. Inténtalo nuevamente.');
    } finally {
      setEnviando(false);
    }
  }

  return <div className="kl-rise mx-auto flex max-w-[960px] flex-col gap-10 lg:flex-row lg:gap-16">
    <Seo title="Entrar o crear cuenta" description="Accede a tu cuenta Kloset para guardar tus medidas, tu bolsa y tus pedidos." path="/entrar" noindex />
    <div className="min-w-0 flex-1">
      <p className="mb-3 font-narrow text-xs uppercase tracking-[0.14em] text-soft">{verificandoRegistro ? 'Paso 2 de 2' : 'Tu cuenta Kloset'}</p>
      <h1 className="mb-3 font-display text-[36px] leading-[1.08] tracking-[-0.025em]">{verificandoRegistro ? 'Verifica tu correo electrónico' : modo === 'signup' ? 'Encuentra tu talla y guárdala.' : 'Vuelve a tu talla.'}</h1>
      <p className="mb-6 max-w-[45ch] text-[15px] leading-[1.65] text-body">{verificandoRegistro ? <>Se envió un código de verificación de 6 dígitos al <strong className="break-all text-ink">{correo.trim()}</strong>.</> : modo === 'signup' ? 'Crea una cuenta para conservar tu perfil, favoritos, direcciones y pedidos.' : 'Entra para continuar con tu bolsa y consultar tus pedidos.'}</p>
      {!verificandoRegistro && <div className="mb-5 flex border border-ink">
        {(['login', 'signup'] as const).map((opcion) => <button key={opcion} type="button" onClick={() => { setModo(opcion); setError(''); }} className="min-h-12 flex-1 cursor-pointer border-none font-narrow text-sm font-semibold uppercase tracking-[0.08em]" style={{ background: modo === opcion ? 'var(--ink)' : 'transparent', color: modo === opcion ? 'var(--paper)' : 'var(--ink)' }}>{opcion === 'login' ? 'Entrar' : 'Crear cuenta'}</button>)}
      </div>}
      <form onSubmit={verificandoRegistro ? verificarCodigo : enviar}>
        <div className="border-t border-ink">
          {verificandoRegistro ? <>
            <fieldset className="mb-4 border-0 p-0">
              <legend className="mb-2 text-sm font-semibold">Código de verificación</legend>
              <div className="grid max-w-[420px] grid-cols-6 gap-2">
                {codigo.map((digito, indice) => <input key={indice} ref={(elemento) => { camposCodigo.current[indice] = elemento; }} type="text" inputMode="numeric" pattern="[0-9]*" maxLength={6} autoComplete={indice === 0 ? 'one-time-code' : 'off'} aria-label={`Dígito ${indice + 1} del código`} value={digito} onChange={(event) => cambiarDigito(indice, event.target.value)} onPaste={(event) => { const pegado = event.clipboardData.getData('text').replace(/\D/g, '').slice(0, 6); if (!pegado) return; event.preventDefault(); cambiarDigito(indice, pegado); }} onKeyDown={(event) => { if (event.key === 'Backspace' && !digito && indice > 0) camposCodigo.current[indice - 1]?.focus(); }} className="h-14 min-w-0 border border-rule bg-transparent text-center text-xl text-ink outline-none focus:border-ink" />)}
              </div>
            </fieldset>
            <button type="button" onClick={() => void reenviarCodigo()} disabled={contador > 0 || enviando} className="mb-5 cursor-pointer border-none bg-transparent p-0 text-sm text-body underline disabled:cursor-default disabled:no-underline disabled:opacity-70">{contador > 0 ? `Reenviar código (${String(Math.floor(contador / 60)).padStart(2, '0')}:${String(contador % 60).padStart(2, '0')})` : 'Reenviar código'}</button>
          </> : <>
            {modo === 'signup' && <label className="block border-b border-rule py-3"><span className="block font-narrow text-xs uppercase tracking-[0.12em] text-soft">Nombre</span><input value={nombre} onChange={(e) => setNombre(e.target.value)} autoComplete="name" maxLength={120} className="min-h-10 w-full border-none bg-transparent text-base text-ink outline-none" placeholder="Tu nombre" /></label>}
            <label className="block border-b border-rule py-3"><span className="block font-narrow text-xs uppercase tracking-[0.12em] text-soft">Correo electrónico</span><input type="email" value={correo} onChange={(e) => setCorreo(e.target.value)} autoComplete="email" className="min-h-10 w-full border-none bg-transparent text-base text-ink outline-none" placeholder="tu@correo.com" /></label>
            <CampoContrasena id="auth-password" etiqueta="Contraseña" valor={password} onChange={setPassword} visible={mostrarPassword} alternar={() => setMostrarPassword(!mostrarPassword)} autocomplete={modo === 'signup' ? 'new-password' : 'current-password'} placeholder={modo === 'signup' ? 'Mínimo 8 caracteres' : 'Tu contraseña'} />
            {modo === 'signup' && <p className="pt-2 text-xs text-soft">Requisitos: {passwordRequirements}</p>}
            {modo === 'signup' && <CampoContrasena id="auth-confirm-password" etiqueta="Confirmar contraseña" valor={confirmarPassword} onChange={setConfirmarPassword} visible={mostrarConfirmar} alternar={() => setMostrarConfirmar(!mostrarConfirmar)} autocomplete="new-password" placeholder="Repite tu contraseña" />}
          </>}
        </div>
        {error && <p role="alert" className="mt-4 border-l-[3px] border-red pl-3 text-sm text-red">{error}</p>}
        {mensaje && verificandoRegistro && <p role="status" className="mt-4 text-sm text-body">{mensaje}</p>}
        <button type="submit" disabled={enviando} className="mt-5 min-h-[56px] w-full cursor-pointer border-none bg-ink font-narrow text-sm font-semibold uppercase tracking-[0.08em] text-paper hover:bg-red disabled:opacity-50">{enviando ? 'Un momento…' : verificandoRegistro ? 'Verificar código' : modo === 'signup' ? 'Crear mi cuenta' : 'Entrar'}</button>
      </form>
      {!verificandoRegistro && modo === 'login' && <p className="mt-5 text-sm text-body"><Link to="/recuperar" className="underline">¿Olvidaste tu contraseña?</Link></p>}
      {verificandoRegistro && <p className="mt-5 text-sm text-body"><Link to="/entrar" className="underline" onClick={() => { setVerificandoRegistro(false); setModo('login'); setPassword(''); setConfirmarPassword(''); setError(''); }}>Volver al inicio de sesión</Link></p>}
    </div>
    <aside className="border-t-[3px] border-red bg-surface p-6 lg:w-[320px]">
      <p className="mb-3 font-narrow text-xs uppercase tracking-[0.14em] text-soft">Con tu cuenta</p>
      {['Tu perfil de medidas y talla recomendada', 'Tus prendas favoritas y bolsa guardada', 'Direcciones e historial de pedidos'].map((texto, i) => <div key={texto} className="border-b border-rule py-4"><span className="mr-3 font-narrow text-xs text-red">0{i + 1}</span><span className="text-sm text-body">{texto}</span></div>)}
      <p className="mt-5 text-[12px] leading-[1.55] text-soft">Verificamos el correo antes de activar la cuenta. Los pagos son simulados.</p>
    </aside>
  </div>;
}
