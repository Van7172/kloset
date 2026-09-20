import { Link, NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom';
import { useKloset } from '../store/KlosetContext';
import logo from '../assets/kloset-logo-transparente.png';
import logoSquare from '../assets/kloset-logo-square.png';

const TABS = [
  { key: 'catalogo', label: 'Catálogo', to: '/', rutas: ['/', '/producto'], soloMovil: false, ocultaConSesion: false, enBarra: true },
  { key: 'ajuste', label: 'Ajuste', to: '/medidas', rutas: ['/medidas', '/resultado', '/avatar'], soloMovil: false, ocultaConSesion: true, enBarra: true },
  { key: 'cobertura', label: 'Cobertura', to: '/cobertura', rutas: ['/cobertura'], soloMovil: false, ocultaConSesion: false, enBarra: false },
  { key: 'nosotros', label: 'Nosotros', to: '/nosotros', rutas: ['/nosotros'], soloMovil: false, ocultaConSesion: false, enBarra: false },
  { key: 'contacto', label: 'Contáctanos', to: '/contacto', rutas: ['/contacto'], soloMovil: false, ocultaConSesion: false, enBarra: false },
  { key: 'bolsa', label: 'Bolsa', to: '/bolsa', rutas: ['/bolsa'], soloMovil: true, ocultaConSesion: false, enBarra: true },
  { key: 'cuenta', label: 'Cuenta', to: '/cuenta', rutas: ['/cuenta', '/pedidos', '/entrar'], soloMovil: true, ocultaConSesion: false, enBarra: true },
];

const COLUMNAS_PIE = [
  {
    title: 'Tienda',
    links: [
      { label: 'Catálogo', to: '/' },
      { label: 'Ajuste de talla', to: '/medidas' },
      { label: 'Cobertura en Lima', to: '/cobertura' },
    ],
  },
  {
    title: 'Kloset',
    links: [
      { label: 'Nosotros', to: '/nosotros' },
      { label: 'Contáctanos', to: '/contacto' },
    ],
  },
  {
    title: 'Legal',
    links: [
      { label: 'Política de Privacidad', to: '/legal/privacidad' },
      { label: 'Términos y Condiciones', to: '/legal/terminos' },
      { label: 'Libro de Reclamaciones', to: '/legal/reclamaciones' },
    ],
  },
];

function IconoSol() {
  return (
    <svg width="17" height="17" viewBox="0 0 18 18" fill="none" stroke="currentColor" strokeWidth="1.4" aria-hidden="true">
      <circle cx="9" cy="9" r="3.4" />
      <path d="M9 1.4V3M9 15v1.6M1.4 9H3M15 9h1.6M3.6 3.6l1.2 1.2M13.2 13.2l1.2 1.2M14.4 3.6l-1.2 1.2M4.8 13.2l-1.2 1.2" />
    </svg>
  );
}

function IconoLuna() {
  return (
    <svg width="16" height="16" viewBox="0 0 18 18" fill="none" stroke="currentColor" strokeWidth="1.4" aria-hidden="true">
      <path d="M15.2 11.4A6.8 6.8 0 0 1 6.6 2.8a6.8 6.8 0 1 0 8.6 8.6Z" />
    </svg>
  );
}

export function AppLayout() {
  const { tema, alternarTema, bolsa, usuario, tienePerfil } = useKloset();
  const { pathname } = useLocation();
  const navigate = useNavigate();
  const oscuro = tema === 'dark';

  const activa = (rutas: string[]) =>
    rutas.some((r) => (r === '/' ? pathname === '/' : pathname.startsWith(r)));

  return (
    <div className="flex min-h-screen flex-col bg-paper text-ink">
      <header className="sticky top-0 z-40 border-b border-ink backdrop-blur-md" style={{ background: 'var(--header-bg)' }}>
        <div className="mx-auto flex max-w-[1320px] items-center gap-[18px] px-[18px] py-[13px]">
          <Link to="/" className="flex select-none items-center">
            {oscuro ? (
              <span
                role="img"
                aria-label="Kloset"
                className="block h-[30px] w-[162px] bg-no-repeat"
                style={{
                  backgroundImage: `url(${logoSquare})`,
                  backgroundSize: '196px 196px',
                  backgroundPosition: '-17px -83px',
                }}
              />
            ) : (
              <img src={logo} alt="Kloset" width={128} height={32} decoding="async" className="h-8 w-auto" />
            )}
          </Link>

          <nav className="ml-3 hidden items-stretch gap-[2px] md:flex">
            {TABS.filter((t) => !t.soloMovil && !(t.ocultaConSesion && usuario)).map((t) => (
              <NavLink
                key={t.key}
                to={t.to}
                className="relative px-[13px] py-[10px] font-narrow text-sm font-semibold uppercase tracking-[0.04em]"
                style={{ color: activa(t.rutas) ? 'var(--ink)' : 'var(--soft)' }}
              >
                {t.label}
                <span
                  className="absolute inset-x-[13px] bottom-[2px] h-[2px]"
                  style={{ background: activa(t.rutas) ? 'var(--red)' : 'transparent' }}
                />
              </NavLink>
            ))}
          </nav>

          <div className="flex-1" />

          <div className="hidden whitespace-nowrap font-narrow text-xs uppercase tracking-[0.1em] text-soft lg:block">
            Medidas · {tienePerfil ? 'guardadas' : 'sin registrar'}
          </div>

          <button
            type="button"
            onClick={alternarTema}
            title={oscuro ? 'Cambiar a modo claro' : 'Cambiar a modo noche'}
            aria-label={oscuro ? 'Cambiar a modo claro' : 'Cambiar a modo noche'}
            aria-pressed={oscuro}
            className="relative h-[42px] w-[76px] shrink-0 cursor-pointer overflow-hidden border border-ink bg-transparent p-0"
          >
            <span
              className="absolute top-[3px] h-[34px] w-[35px] bg-ink transition-[left] duration-300"
              style={{ left: oscuro ? '38px' : '3px', transitionTimingFunction: 'cubic-bezier(.2,.85,.2,1)' }}
            />
            <span
              className="absolute left-0 top-0 flex h-10 w-[38px] items-center justify-center transition-colors"
              style={{ color: oscuro ? 'var(--soft)' : 'var(--paper)' }}
            >
              <IconoSol />
            </span>
            <span
              className="absolute right-0 top-0 flex h-10 w-[38px] items-center justify-center transition-colors"
              style={{ color: oscuro ? 'var(--paper)' : 'var(--soft)' }}
            >
              <IconoLuna />
            </span>
          </button>

          <button
            type="button"
            onClick={() => navigate('/bolsa')}
            title="Tu bolsa"
            aria-label="Bolsa"
            className="relative flex h-[42px] w-[42px] cursor-pointer items-center justify-center border border-ink bg-transparent text-ink hover:bg-ink hover:text-paper"
          >
            <svg width="17" height="18" viewBox="0 0 17 18" fill="none" stroke="currentColor" strokeWidth="1.4" aria-hidden="true">
              <path d="M1.6 5.4h13.8L14.3 17H2.7L1.6 5.4Z" />
              <path d="M5.7 5.4V4a2.8 2.8 0 0 1 5.6 0v1.4" />
            </svg>
            {bolsa.length > 0 && (
              <span className="absolute -right-[6px] -top-[6px] flex h-[18px] min-w-[18px] items-center justify-center bg-red px-1 font-narrow text-[11px] font-semibold leading-none text-[#F2F2F0]">
                {bolsa.length}
              </span>
            )}
          </button>

          <button
            type="button"
            onClick={() => navigate(usuario ? '/cuenta' : '/entrar')}
            title={usuario ? `Tu cuenta · ${usuario.nombre}` : 'Iniciar sesión'}
            aria-label={usuario ? 'Tu cuenta' : 'Iniciar sesión'}
            className="relative flex h-[42px] w-[42px] cursor-pointer items-center justify-center border border-ink bg-transparent text-ink hover:bg-ink hover:text-paper"
          >
            <svg width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor" strokeWidth="1.4" aria-hidden="true">
              <circle cx="9" cy="6" r="3.3" />
              <path d="M2.4 16.6c0-3.3 2.9-5.3 6.6-5.3s6.6 2 6.6 5.3" />
            </svg>
            <span
              className="absolute -bottom-px -left-px -right-px h-[3px]"
              style={{ background: usuario ? 'var(--red)' : 'transparent' }}
            />
          </button>
        </div>
      </header>

      <main className="mx-auto w-full max-w-[1320px] flex-1 px-4 py-[18px] pb-[30px] md:px-[22px] md:py-[26px] md:pb-11 xl:px-7 xl:py-8 xl:pb-14">
        <Outlet />
      </main>

      <footer className="mt-[60px] border-t-[3px] border-[#C4211F] bg-[#161615] text-[#F0EFEC]">
        <div className="mx-auto flex max-w-[1320px] flex-col gap-[30px] px-4 pb-[26px] pt-[30px] md:flex-row md:gap-14 md:px-7 md:pb-9 md:pt-11">
          <div className="shrink-0">
            <img
              src={logoSquare}
              alt="Kloset"
              width={132}
              height={132}
              loading="lazy"
              decoding="async"
              className="block h-[132px] w-[132px] object-cover"
            />
            <p className="mt-4 max-w-[26ch] text-[13px] leading-relaxed text-[#95948E]">
              Ropa deportiva medida sobre tu cuerpo, no sobre un maniquí estándar.
            </p>
          </div>
          <div className="grid flex-1 grid-cols-2 gap-[26px] md:grid-cols-3">
            {COLUMNAS_PIE.map((col) => (
              <div key={col.title}>
                <div className="mb-3 border-b border-[#C4211F] pb-[9px] font-narrow text-[11.5px] uppercase tracking-[0.14em] text-[#F0EFEC]">
                  {col.title}
                </div>
                {col.links.map((l) => (
                  <div key={l.label} className="py-[7px]">
                    <Link to={l.to} className="text-[13.5px] text-[#B6B5AF] hover:text-[#F0EFEC]">
                      {l.label}
                    </Link>
                  </div>
                ))}
              </div>
            ))}
          </div>
        </div>
        <div className="border-t border-[#2E2E2B]">
          <div className="mx-auto flex max-w-[1320px] flex-wrap items-center justify-between gap-3 px-[18px] py-4 font-narrow text-[11.5px] uppercase tracking-[0.1em] text-[#95948E]">
            <span>© 2026 Kloset · Lima</span>
            <span className="flex flex-wrap gap-[18px]">
              <Link to="/legal/privacidad" className="hover:text-[#F0EFEC]">Política de Privacidad</Link>
              <Link to="/legal/terminos" className="hover:text-[#F0EFEC]">Términos y Condiciones</Link>
              <Link to="/legal/reclamaciones" className="hover:text-[#F0EFEC]">Libro de Reclamaciones</Link>
            </span>
          </div>
        </div>
      </footer>

      <nav
        className="sticky bottom-0 z-40 border-t border-ink backdrop-blur-md md:hidden"
        style={{ background: 'var(--header-bg)', paddingBottom: 'env(safe-area-inset-bottom)' }}
      >
        <div className="grid grid-cols-4">
          {TABS.filter((t) => t.enBarra).map((t) => {
            const on = activa(t.rutas);
            const destino = t.key === 'cuenta' ? (usuario ? '/cuenta' : '/entrar') : t.to;
            return (
              <button
                key={t.key}
                type="button"
                onClick={() => navigate(destino)}
                className="flex min-h-[58px] cursor-pointer flex-col items-center justify-center gap-[7px] border-none bg-transparent font-narrow text-xs font-semibold uppercase tracking-[0.08em]"
                style={{ color: on ? 'var(--ink)' : 'var(--soft)' }}
              >
                <span className="h-[3px] w-[26px]" style={{ background: on ? 'var(--red)' : 'transparent' }} />
                {t.label}
              </button>
            );
          })}
        </div>
      </nav>
    </div>
  );
}
