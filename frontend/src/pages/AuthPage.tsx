import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Seo } from '../components/Seo';
import { useKloset } from '../store/KlosetContext';

export function AuthPage() {
  const navigate = useNavigate();
  const { entrar, registrarse, intencion, setIntencion, añadirABolsa } = useKloset();
  const [modo, setModo] = useState<'login' | 'signup'>('login');
  const [nombre, setNombre] = useState('');
  const [correo, setCorreo] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [enviando, setEnviando] = useState(false);

  const enviar = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setError('');
    if (modo === 'signup' && nombre.trim().length < 2) return setError('Escribe tu nombre.');
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo.trim())) return setError('Escribe un correo válido.');
    if (password.length < 8 && modo === 'signup') return setError('La contraseña necesita al menos 8 caracteres.');
    if (!password) return setError('Escribe tu contraseña.');
    setEnviando(true);
    const fallo = modo === 'signup'
      ? await registrarse(nombre.trim(), correo.trim(), password)
      : await entrar(correo.trim(), password);
    if (fallo) {
      setEnviando(false);
      return setError(fallo);
    }
    if (intencion?.item) {
      const errorBolsa = await añadirABolsa(intencion.item);
      if (errorBolsa) {
        setEnviando(false);
        return setError(errorBolsa);
      }
    }
    const destino = intencion?.then === 'checkout' ? '/pago' : intencion?.then === 'cart' ? '/bolsa' : '/cuenta';
    setIntencion(null);
    navigate(destino, { replace: true });
  };

  return <div className="kl-rise mx-auto flex max-w-[960px] flex-col gap-10 lg:flex-row lg:gap-16">
    <Seo title="Entrar o crear cuenta" description="Accede a tu cuenta Kloset para guardar tus medidas, tu bolsa y tus pedidos." path="/entrar" noindex />
    <div className="min-w-0 flex-1">
      <p className="mb-3 font-narrow text-xs uppercase tracking-[0.14em] text-soft">Tu cuenta Kloset</p>
      <h1 className="mb-3 font-display text-[36px] leading-[1.08] tracking-[-0.025em]">{modo === 'signup' ? 'Encuentra tu talla y guárdala.' : 'Vuelve a tu talla.'}</h1>
      <p className="mb-6 max-w-[45ch] text-[15px] leading-[1.65] text-body">{modo === 'signup' ? 'Crea una cuenta para conservar tu perfil, favoritos, direcciones y pedidos.' : 'Entra para continuar con tu bolsa y consultar tus pedidos.'}</p>
      <div className="mb-5 flex border border-ink">
        {(['login', 'signup'] as const).map((opcion) => <button key={opcion} type="button" onClick={() => { setModo(opcion); setError(''); }} className="min-h-12 flex-1 cursor-pointer border-none font-narrow text-sm font-semibold uppercase tracking-[0.08em]" style={{ background: modo === opcion ? 'var(--ink)' : 'transparent', color: modo === opcion ? 'var(--paper)' : 'var(--ink)' }}>{opcion === 'login' ? 'Entrar' : 'Crear cuenta'}</button>)}
      </div>
      <form onSubmit={enviar}>
        <div className="border-t border-ink">
          {modo === 'signup' && <label className="block border-b border-rule py-3"><span className="block font-narrow text-xs uppercase tracking-[0.12em] text-soft">Nombre</span><input value={nombre} onChange={(e) => setNombre(e.target.value)} autoComplete="name" maxLength={120} className="min-h-10 w-full border-none bg-transparent text-base text-ink outline-none" placeholder="Tu nombre" /></label>}
          <label className="block border-b border-rule py-3"><span className="block font-narrow text-xs uppercase tracking-[0.12em] text-soft">Correo electrónico</span><input type="email" value={correo} onChange={(e) => setCorreo(e.target.value)} autoComplete="email" className="min-h-10 w-full border-none bg-transparent text-base text-ink outline-none" placeholder="tu@correo.com" /></label>
          <label className="block border-b border-rule py-3"><span className="block font-narrow text-xs uppercase tracking-[0.12em] text-soft">Contraseña</span><input type="password" value={password} onChange={(e) => setPassword(e.target.value)} autoComplete={modo === 'signup' ? 'new-password' : 'current-password'} className="min-h-10 w-full border-none bg-transparent text-base text-ink outline-none" placeholder={modo === 'signup' ? 'Mínimo 8 caracteres' : 'Tu contraseña'} /></label>
        </div>
        {error && <p role="alert" className="mt-4 border-l-[3px] border-red pl-3 text-sm text-red">{error}</p>}
        <button type="submit" disabled={enviando} className="mt-5 min-h-[56px] w-full cursor-pointer border-none bg-ink font-narrow text-sm font-semibold uppercase tracking-[0.08em] text-paper hover:bg-red disabled:opacity-50">{enviando ? 'Un momento…' : modo === 'signup' ? 'Crear mi cuenta' : 'Entrar'}</button>
      </form>
      {modo === 'login' && <p className="mt-5 text-sm text-body">¿No recuerdas tu contraseña? <Link to="/contacto" className="underline">Solicita ayuda desde contacto</Link>.</p>}
    </div>
    <aside className="border-t-[3px] border-red bg-surface p-6 lg:w-[320px]">
      <p className="mb-3 font-narrow text-xs uppercase tracking-[0.14em] text-soft">Con tu cuenta</p>
      {['Tu perfil de medidas y talla recomendada', 'Tus prendas favoritas y bolsa guardada', 'Direcciones e historial de pedidos'].map((texto, i) => <div key={texto} className="border-b border-rule py-4"><span className="mr-3 font-narrow text-xs text-red">0{i + 1}</span><span className="text-sm text-body">{texto}</span></div>)}
      <p className="mt-5 text-[12px] leading-[1.55] text-soft">Esta entrega usa tarjetas de demostración. No se envían correos de verificación ni se realizan cargos reales.</p>
    </aside>
  </div>;
}
