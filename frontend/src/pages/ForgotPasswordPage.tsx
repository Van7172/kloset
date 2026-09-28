import { useEffect, useRef, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Seo } from '../components/Seo';
import { cumplePoliticaContrasena, passwordRequirements } from '../lib/password';
import { api } from '../services/api';

function CampoContrasena({
  id,
  etiqueta,
  valor,
  onChange,
  visible,
  alternar,
}: {
  id: string;
  etiqueta: string;
  valor: string;
  onChange: (valor: string) => void;
  visible: boolean;
  alternar: () => void;
}) {
  return <div>
    <label htmlFor={id} className="mb-2 block text-sm font-semibold">{etiqueta}</label>
    <div className="flex items-center border border-rule bg-transparent px-3">
      <input id={id} type={visible ? 'text' : 'password'} value={valor} onChange={(event) => onChange(event.target.value)} autoComplete="new-password" minLength={8} maxLength={128} className="min-h-11 min-w-0 flex-1 border-none bg-transparent text-sm text-ink outline-none" placeholder="Mínimo 8 caracteres" />
      <button type="button" onClick={alternar} aria-label={visible ? 'Ocultar contraseña' : 'Mostrar contraseña'} aria-pressed={visible} title={visible ? 'Ocultar contraseña' : 'Mostrar contraseña'} className="flex h-10 w-10 shrink-0 cursor-pointer items-center justify-center border-none bg-transparent text-soft hover:text-ink">
        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
          {visible ? <><path d="M3 3l18 18" /><path d="M10.6 10.6a2 2 0 0 0 2.8 2.8" /><path d="M9.9 5.2A11 11 0 0 1 12 5c6.5 0 10 7 10 7a15.6 15.6 0 0 1-4 4.8" /><path d="M6.6 6.6C3.6 8.3 2 12 2 12s3.5 7 10 7a10 10 0 0 0 3-.5" /></> : <><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" /><circle cx="12" cy="12" r="3" /></>}
        </svg>
      </button>
    </div>
  </div>;
}

export function ForgotPasswordPage() {
  const navigate = useNavigate();
  const [paso, setPaso] = useState<'correo' | 'verificacion' | 'completado'>('correo');
  const [correo, setCorreo] = useState('');
  const [codigo, setCodigo] = useState(['', '', '', '', '', '']);
  const camposCodigo = useRef<Array<HTMLInputElement | null>>([]);
  const [nuevaContrasena, setNuevaContrasena] = useState('');
  const [confirmarContrasena, setConfirmarContrasena] = useState('');
  const [mostrarNueva, setMostrarNueva] = useState(false);
  const [mostrarConfirmacion, setMostrarConfirmacion] = useState(false);
  const [contador, setContador] = useState(0);
  const [error, setError] = useState('');
  const [mensaje, setMensaje] = useState('');
  const [procesando, setProcesando] = useState(false);

  useEffect(() => {
    if (contador <= 0) return;
    const timer = window.setTimeout(() => setContador((actual) => Math.max(0, actual - 1)), 1000);
    return () => window.clearTimeout(timer);
  }, [contador]);

  async function enviarCodigo() {
    setError('');
    setMensaje('');
    setProcesando(true);
    try {
      const respuesta = await api.recuperacion.solicitar(correo.trim());
      if (respuesta.status !== 'success') {
        setError(respuesta.message || 'No pudimos enviar el código. Inténtalo nuevamente.');
        return;
      }
      setPaso('verificacion');
      setContador(respuesta.reenviar_en ?? 60);
      setMensaje(respuesta.message || 'Si el correo está registrado, recibirás un código para restablecer tu contraseña.');
    } catch {
      setError('No pudimos procesar la solicitud. Inténtalo nuevamente.');
    } finally {
      setProcesando(false);
    }
  }

  async function solicitar(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo.trim())) {
      setError('Ingresa un correo electrónico válido.');
      return;
    }
    await enviarCodigo();
  }

  function cambiarDigito(indice: number, valor: string) {
    const digitos = valor.replace(/\D/g, '');
    if (digitos.length > 1) {
      const nuevos = [...digitos.slice(0, 6)];
      while (nuevos.length < 6) nuevos.push('');
      setCodigo(nuevos);
      camposCodigo.current[Math.min(digitos.length, 6) - 1]?.focus();
      return;
    }
    const nuevos = [...codigo];
    nuevos[indice] = digitos;
    setCodigo(nuevos);
    if (digitos && indice < 5) camposCodigo.current[indice + 1]?.focus();
  }

  function pegarCodigo(event: React.ClipboardEvent<HTMLInputElement>) {
    const digitos = event.clipboardData.getData('text').replace(/\D/g, '').slice(0, 6);
    if (!digitos) return;
    event.preventDefault();
    const nuevos = [...digitos];
    while (nuevos.length < 6) nuevos.push('');
    setCodigo(nuevos);
    camposCodigo.current[Math.min(digitos.length, 6) - 1]?.focus();
  }

  async function confirmar(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError('');
    if (codigo.join('').length !== 6) return setError('Ingresa el código de 6 dígitos.');
    if (!cumplePoliticaContrasena(nuevaContrasena)) return setError(`La contraseña debe tener ${passwordRequirements}`);
    if (nuevaContrasena !== confirmarContrasena) return setError('Las contraseñas no coinciden.');

    setProcesando(true);
    try {
      const respuesta = await api.recuperacion.restablecer(correo.trim(), codigo.join(''), nuevaContrasena, confirmarContrasena);
      if (respuesta.status !== 'success') {
        setError(respuesta.message || 'El código no es válido o ha vencido.');
        return;
      }
      setMensaje(respuesta.message || 'Contraseña actualizada. Ya puedes iniciar sesión.');
      setPaso('completado');
    } catch {
      setError('No pudimos actualizar la contraseña. Inténtalo nuevamente.');
    } finally {
      setProcesando(false);
    }
  }

  return <div className="kl-rise mx-auto flex max-w-[960px] flex-col gap-10 lg:flex-row lg:gap-16">
    <Seo title="Recuperar contraseña" description="Restablece tu contraseña de Kloset de forma segura." path="/recuperar" noindex />
    <section className="min-w-0 flex-1">
      <Link to="/entrar" className="mb-10 inline-flex min-h-8 items-center gap-3 font-narrow text-xs uppercase text-soft hover:text-ink"><span aria-hidden="true">←</span> Volver al inicio de sesión</Link>
      <p className="mb-3 font-narrow text-xs uppercase tracking-[0.14em] text-soft">Recuperar contraseña</p>
      <h1 className="mb-4 font-display text-[36px] leading-[1.08]">{paso === 'correo' ? '¿Olvidaste tu contraseña?' : paso === 'verificacion' ? 'Verifica tu correo' : 'Contraseña actualizada'}</h1>

      {paso === 'correo' && <>
        <p className="mb-7 max-w-[45ch] text-[15px] leading-[1.65] text-body">Ingresa tu correo y te enviaremos un código para restablecer tu contraseña.</p>
        <form onSubmit={solicitar}>
          <label htmlFor="recuperar-correo" className="mb-2 block text-sm font-semibold">Correo electrónico</label>
          <input id="recuperar-correo" type="email" value={correo} onChange={(event) => setCorreo(event.target.value)} autoComplete="email" required className="min-h-11 w-full border border-rule bg-transparent px-3 text-sm text-ink outline-none focus:border-ink" placeholder="tu@correo.com" />
          {error && <p role="alert" className="mt-4 border-l-[3px] border-red pl-3 text-sm text-red">{error}</p>}
          <button type="submit" disabled={procesando} className="mt-6 min-h-[56px] w-full cursor-pointer border-none bg-ink font-narrow text-sm font-semibold uppercase text-paper hover:bg-red disabled:opacity-50">{procesando ? 'Enviando…' : 'Enviar código'}</button>
        </form>
      </>}

      {paso === 'verificacion' && <>
        <p className="mb-6 max-w-[45ch] text-[15px] leading-[1.65] text-body">Si el correo está registrado, enviamos el código a <strong className="break-all text-ink">{correo.trim()}</strong>.</p>
        {mensaje && <p role="status" className="mb-5 text-sm text-body">{mensaje}</p>}
        <form onSubmit={confirmar}>
          <fieldset className="mb-4 border-0 p-0">
            <legend className="mb-2 text-sm font-semibold">Código de verificación</legend>
            <div className="grid max-w-[420px] grid-cols-6 gap-2">
              {codigo.map((digito, indice) => <input
                key={indice}
                ref={(elemento) => { camposCodigo.current[indice] = elemento; }}
                type="text"
                inputMode="numeric"
                pattern="[0-9]*"
                maxLength={6}
                autoComplete={indice === 0 ? 'one-time-code' : 'off'}
                aria-label={`Dígito ${indice + 1} del código`}
                value={digito}
                onChange={(event) => cambiarDigito(indice, event.target.value)}
                onPaste={pegarCodigo}
                onKeyDown={(event) => {
                  if (event.key === 'Backspace' && !digito && indice > 0) camposCodigo.current[indice - 1]?.focus();
                }}
                className="h-14 w-full border border-rule bg-transparent text-center text-xl text-ink outline-none focus:border-ink"
              />)}
            </div>
          </fieldset>
          <button type="button" disabled={contador > 0 || procesando} onClick={() => void enviarCodigo()} className="mb-6 cursor-pointer border-none bg-transparent p-0 text-sm text-body underline disabled:cursor-default disabled:no-underline disabled:opacity-70">{contador > 0 ? `Reenviar código (${String(Math.floor(contador / 60)).padStart(2, '0')}:${String(contador % 60).padStart(2, '0')})` : 'Reenviar código'}</button>
          <div className="space-y-4">
            <CampoContrasena id="nueva-contrasena" etiqueta="Nueva contraseña" valor={nuevaContrasena} onChange={setNuevaContrasena} visible={mostrarNueva} alternar={() => setMostrarNueva(!mostrarNueva)} />
            <p className="text-xs text-soft">Requisitos: {passwordRequirements}</p>
            <CampoContrasena id="confirmar-contrasena" etiqueta="Confirmar nueva contraseña" valor={confirmarContrasena} onChange={setConfirmarContrasena} visible={mostrarConfirmacion} alternar={() => setMostrarConfirmacion(!mostrarConfirmacion)} />
          </div>
          {error && <p role="alert" className="mt-4 border-l-[3px] border-red pl-3 text-sm text-red">{error}</p>}
          <button type="submit" disabled={procesando} className="mt-6 min-h-[56px] w-full cursor-pointer border-none bg-ink font-narrow text-sm font-semibold uppercase text-paper hover:bg-red disabled:opacity-50">{procesando ? 'Confirmando…' : 'Confirmar'}</button>
        </form>
      </>}

      {paso === 'completado' && <div>
        <p role="status" className="mb-7 text-[15px] leading-[1.65] text-body">{mensaje}</p>
        <button type="button" onClick={() => navigate('/entrar', { replace: true })} className="min-h-[56px] w-full cursor-pointer border-none bg-ink font-narrow text-sm font-semibold uppercase text-paper hover:bg-red">Volver al inicio de sesión</button>
      </div>}

      {paso !== 'completado' && <p className="mt-6 text-sm text-body"><Link to="/entrar" className="underline">Volver al inicio de sesión</Link></p>}
    </section>
    <aside className="h-fit border-t-[3px] border-red bg-surface p-6 lg:w-[320px]">
      <p className="mb-5 font-narrow text-xs uppercase tracking-[0.14em] text-soft">Tu cuenta Kloset</p>
      {(paso === 'correo'
        ? [
            ['Cuenta segura', 'Te enviaremos un código para proteger tu cuenta.'],
            ['Recupera el acceso', 'Sigue unos pasos sencillos para volver a Kloset.'],
            ['Datos protegidos', 'Tu información permanece segura con nosotros.'],
          ]
        : [
            ['Código enviado', 'Revisa tu bandeja de entrada y spam.'],
            ['Nueva contraseña', 'Crea una contraseña segura para seguir comprando.'],
            ['Cuenta protegida', 'Tu información permanece segura con nosotros.'],
          ]
      ).map(([titulo, texto], indice) => <div key={titulo} className="flex gap-4 border-b border-rule py-4 last:border-b-0">
        <span className="flex h-10 w-10 shrink-0 items-center justify-center border border-rule text-red" aria-hidden="true">{['✉', '▣', '◇'][indice]}</span>
        <p className="text-sm text-body"><strong className="mb-1 block text-ink">{titulo}</strong>{texto}</p>
      </div>)}
    </aside>
  </div>;
}