import { useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import {
  APRIETE_FIGURA,
  type Corte,
} from '../lib/fit';
import { useKloset } from '../store/KlosetContext';

const ASSETS = (window as Window & { __KLOSET_ASSETS__?: string }).__KLOSET_ASSETS__ ?? '';

const SEQUENCE_COUNT = 90;
const SEQUENCE_DESKTOP_PREFIX = `${ASSETS}/imagenes/secuencia/frame-`;
const SEQUENCE_MOBILE_PREFIX = `${ASSETS}/imagenes/secuencia-movil/frame-`;
const FALLBACK_GARMENT = `${ASSETS}/imagenes/camiseta-shell.webp`;
const HERO_POSTER = `${ASSETS}/imagenes/hero.webp`;

type StageMode = 'probing' | 'sequence' | 'studio';

type Chapter = {
  id: string;
  eyebrow: string;
  title: string;
  copy: string;
  progress: number;
};

const CHAPTERS: Chapter[] = [
  {
    id: 'entrada',
    eyebrow: 'TU ESTILO, SIN LÍMITES',
    title: 'Ropa que ya sabe cómo te queda.',
    copy: 'Prendas deportivas que combinan comodidad, estructura y tu talla ideal.',
    progress: 0.08,
  },
  {
    id: 'caida',
    eyebrow: '01 · CAÍDA Y MOVIMIENTO',
    title: 'Se adapta a cada giro.',
    copy: 'Tejido con peso equilibrado y recuperación elástica natural que acompaña hombros y torso en movimiento.',
    progress: 0.38,
  },
  {
    id: 'ajuste',
    eyebrow: '02 · AJUSTE VIVO',
    title: 'Elige cómo quieres que caiga.',
    copy: 'Cambia entre Slim, Regular u Oversize y comprueba la holgura exacta sobre pecho, cintura y largo.',
    progress: 0.68,
  },
  {
    id: 'detalle',
    eyebrow: '03 · ACABADO DE ESTUDIO',
    title: 'Pensada al milímetro.',
    copy: 'Tejido de alto gramaje, costuras limpias y caída consistente lavado tras lavado.',
    progress: 0.92,
  },
];

function clamp01(v: number) {
  return Math.max(0, Math.min(1, v));
}

function easeInOut(t: number) {
  const x = clamp01(t);
  return x < 0.5 ? 2 * x * x : 1 - Math.pow(-2 * x + 2, 2) / 2;
}

function band(p: number, start: number, end: number) {
  return clamp01((p - start) / (end - start));
}

function frameUrl(prefix: string, index: number) {
  return `${prefix}${String(index + 1).padStart(3, '0')}.webp`;
}

export function HomeScrollShowcase() {
  const { tema, corte } = useKloset();
  const trackRef = useRef<HTMLDivElement | null>(null);
  const canvasRef = useRef<HTMLCanvasElement | null>(null);

  const [mode, setMode] = useState<StageMode>('probing');
  const [isMobileViewport, setIsMobileViewport] = useState(() =>
    typeof window !== 'undefined' ? window.matchMedia('(max-width: 860px)').matches : false,
  );
  const [reducedMotion, setReducedMotion] = useState(false);
  const [staticMode, setStaticMode] = useState(false);
  const [progress, setProgress] = useState(0);
  const [loadedFrames, setLoadedFrames] = useState(0);

  const targetProgressRef = useRef(0);
  const currentProgressRef = useRef(0);
  const framesRef = useRef<(HTMLImageElement | null)[]>(Array(SEQUENCE_COUNT).fill(null));
  const activeSequenceIsMobileRef = useRef(false);
  const fallbackImgRef = useRef<HTMLImageElement | null>(null);
  const isVisibleRef = useRef(true);
  const isTabVisibleRef = useRef(true);

  const corteRef = useRef<Corte>(corte);
  const temaRef = useRef(tema);

  useEffect(() => {
    corteRef.current = corte;
  }, [corte]);

  useEffect(() => {
    temaRef.current = tema;
  }, [tema]);

  // Respeta prefers-reduced-motion, ahorro de datos y cambio de orientación escritorio/móvil.
  useEffect(() => {
    const mqMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const mqMobile = window.matchMedia('(max-width: 860px)');
    const nav = navigator as Navigator & { connection?: { saveData?: boolean } };
    const syncMotion = () => setReducedMotion(mqMotion.matches || Boolean(nav.connection?.saveData));
    const syncMobile = () => setIsMobileViewport(mqMobile.matches);
    syncMotion();
    syncMobile();
    mqMotion.addEventListener('change', syncMotion);
    mqMobile.addEventListener('change', syncMobile);
    return () => {
      mqMotion.removeEventListener('change', syncMotion);
      mqMobile.removeEventListener('change', syncMobile);
    };
  }, []);

  // Carga imagen base de estudio + sondea secuencia WebP móvil o escritorio.
  useEffect(() => {
    let cancelled = false;
    framesRef.current = Array(SEQUENCE_COUNT).fill(null);
    setLoadedFrames(0);

    const studioImg = new Image();
    studioImg.decoding = 'async';
    studioImg.src = FALLBACK_GARMENT;
    studioImg.onload = () => {
      if (!cancelled) fallbackImgRef.current = studioImg;
    };

    if (reducedMotion || staticMode) {
      setMode('studio');
      return () => {
        cancelled = true;
      };
    }

    const startSequence = (prefix: string, firstImg: HTMLImageElement, mobileSeq: boolean) => {
      framesRef.current[0] = firstImg;
      activeSequenceIsMobileRef.current = mobileSeq;
      setLoadedFrames(1);
      setMode('sequence');

      // Precarga progresiva: primero frames clave cada 6 pasos, luego el resto.
      const order: number[] = [];
      for (let i = 6; i < SEQUENCE_COUNT; i += 6) order.push(i);
      for (let i = 1; i < SEQUENCE_COUNT; i += 1) {
        if (i % 6 !== 0) order.push(i);
      }

      let cursor = 0;
      const concurrency = 4;
      function loadNext() {
        if (cancelled || cursor >= order.length) return;
        const idx = order[cursor++];
        const img = new Image();
        img.decoding = 'async';
        img.src = frameUrl(prefix, idx);
        img.onload = () => {
          if (cancelled) return;
          framesRef.current[idx] = img;
          setLoadedFrames((n) => n + 1);
          loadNext();
        };
        img.onerror = () => {
          if (!cancelled) loadNext();
        };
      }
      for (let c = 0; c < concurrency; c += 1) loadNext();
    };

    const probePrefix = (prefix: string, mobileSeq: boolean, onFail: () => void) => {
      const first = new Image();
      first.decoding = 'async';
      first.src = frameUrl(prefix, 0);
      first.onload = () => {
        if (!cancelled) startSequence(prefix, first, mobileSeq);
      };
      first.onerror = () => {
        if (!cancelled) onFail();
      };
    };

    if (isMobileViewport) {
      probePrefix(SEQUENCE_MOBILE_PREFIX, true, () =>
        probePrefix(SEQUENCE_DESKTOP_PREFIX, false, () => setMode('studio')),
      );
    } else {
      probePrefix(SEQUENCE_DESKTOP_PREFIX, false, () => setMode('studio'));
    }

    return () => {
      cancelled = true;
    };
  }, [isMobileViewport, reducedMotion, staticMode]);

  // Observa visibilidad del escenario y de la pestaña para pausar trabajo fuera de pantalla.
  useEffect(() => {
    const el = trackRef.current;
    if (!el) return;
    const observer = new IntersectionObserver(
      ([entry]) => {
        isVisibleRef.current = entry.isIntersecting;
      },
      { threshold: 0.01 },
    );
    observer.observe(el);

    const onVisibility = () => {
      isTabVisibleRef.current = !document.hidden;
    };
    document.addEventListener('visibilitychange', onVisibility);
    return () => {
      observer.disconnect();
      document.removeEventListener('visibilitychange', onVisibility);
    };
  }, []);

  // Calcula progreso del scroll dentro de la pista sticky y renderiza el <canvas>.
  useEffect(() => {
    if (reducedMotion || staticMode) return;

    const updateTargetFromScroll = () => {
      const track = trackRef.current;
      if (!track) return;
      const rect = track.getBoundingClientRect();
      const headerOffset = 67;
      const scrollable = Math.max(1, track.offsetHeight - (window.innerHeight - headerOffset));
      const traveled = headerOffset - rect.top;
      targetProgressRef.current = clamp01(traveled / scrollable);
    };

    updateTargetFromScroll();
    window.addEventListener('scroll', updateTargetFromScroll, { passive: true });
    window.addEventListener('resize', updateTargetFromScroll, { passive: true });

    let rafId = 0;
    let lastReported = -1;

    const drawStudioFrame = (
      ctx: CanvasRenderingContext2D,
      w: number,
      h: number,
      p: number,
    ) => {
      const dark = temaRef.current === 'dark';
      const grad = ctx.createRadialGradient(
        w * (w > 760 ? 0.64 : 0.5),
        h * 0.48,
        Math.min(w, h) * 0.08,
        w * 0.5,
        h * 0.5,
        Math.max(w, h) * 0.78,
      );
      if (dark) {
        grad.addColorStop(0, '#363632');
        grad.addColorStop(1, '#20201E');
      } else {
        grad.addColorStop(0, '#ECEAE4');
        grad.addColorStop(1, '#DCD9D2');
      }
      ctx.fillStyle = grad;
      ctx.fillRect(0, 0, w, h);

      // Retícula editorial muy sutil de estudio
      ctx.strokeStyle = dark ? 'rgba(240,239,236,0.045)' : 'rgba(28,28,26,0.045)';
      ctx.lineWidth = 1;
      const step = 64;
      for (let x = (w % step) * 0.5; x < w; x += step) {
        ctx.beginPath();
        ctx.moveTo(x, 0);
        ctx.lineTo(x, h);
        ctx.stroke();
      }

      const img = fallbackImgRef.current;
      if (!img || !img.naturalWidth) return;

      const isDesktop = w >= 860;
      const baseSize = Math.min(w * (isDesktop ? 0.48 : 0.78), h * (isDesktop ? 0.78 : 0.56));

      // Curvas por tramo: entrada -> órbita + caída en movimiento -> zoom ajuste -> macro
      const intro = easeInOut(band(p, 0, 0.24));
      const orbitPhase =
        p < 0.52
          ? easeInOut(band(p, 0.18, 0.42))
          : 1 - easeInOut(band(p, 0.46, 0.62));
      const fitZoom = easeInOut(band(p, 0.5, 0.72));
      const macroZoom = easeInOut(band(p, 0.74, 0.96));

      const fitScaleX = APRIETE_FIGURA[corteRef.current] ?? 1;
      const scale =
        (0.92 + intro * 0.08) *
        (1 + fitZoom * 0.24 + macroZoom * 0.78);
      // Compresión horizontal sutil para sugerir giro orbital 3/4 -> perfil -> regreso
      const orbitScaleX = 1 - orbitPhase * 0.18;

      const centerX = isDesktop
        ? w * (0.68 - intro * 0.05 + orbitPhase * 0.03 - macroZoom * 0.06)
        : w * 0.5;
      const centerY = isDesktop
        ? h * (0.5 - orbitPhase * 0.02 + macroZoom * 0.14)
        : h * (0.64 - intro * 0.04 - orbitPhase * 0.015 + macroZoom * 0.08);

      const drawW =
        baseSize *
        scale *
        orbitScaleX *
        (1 + (fitScaleX - 1) * (0.35 + fitZoom * 0.55));
      const drawH = baseSize * scale;

      // Sombra de piso sensible al giro y flotación
      ctx.save();
      ctx.fillStyle = dark ? 'rgba(0,0,0,0.36)' : 'rgba(28,28,26,0.12)';
      ctx.beginPath();
      ctx.ellipse(
        centerX,
        centerY + drawH * 0.46,
        drawW * (0.34 - orbitPhase * 0.05),
        Math.max(8, drawH * 0.032),
        0,
        0,
        Math.PI * 2,
      );
      ctx.fill();
      ctx.restore();

      // Para que el fondo claro de camiseta-shell.webp se funda en el lienzo claro
      ctx.save();
      if (!dark) {
        ctx.globalCompositeOperation = 'multiply';
      }

      const sw = img.naturalWidth;
      const sh = img.naturalHeight;

      if (orbitPhase > 0.01) {
        // Simulación de caída viva (Kinetic Drape): bandas horizontales suaves con onda en el bajo
        const strips = 24;
        const dx = centerX - drawW * 0.5;
        const dy = centerY - drawH * 0.5;
        const wavePhase = p * Math.PI * 6;

        ctx.save();
        ctx.translate(centerX, centerY);
        ctx.rotate(-0.045 * orbitPhase);
        ctx.translate(-centerX, -centerY);

        for (let s = 0; s < strips; s += 1) {
          const t0 = s / strips;
          const t1 = (s + 1) / strips;
          const sy = sh * t0;
          const sHeight = sh * (t1 - t0);
          // La onda de aire crece gradualmente desde los hombros hacia el dobladillo
          const drapeAmp = Math.pow(t0, 1.45) * drawW * 0.028 * orbitPhase;
          const shiftX = Math.sin(wavePhase + t0 * 3.4) * drapeAmp;
          const shiftY = Math.cos(wavePhase + t0 * 2.8) * drapeAmp * 0.25;

          ctx.drawImage(
            img,
            0,
            sy,
            sw,
            sHeight,
            dx + shiftX,
            dy + drawH * t0 + shiftY,
            drawW,
            drawH * (t1 - t0) + 0.8,
          );
        }
        ctx.restore();
      } else {
        // Prenda entera con ligera rotación visual según scroll
        ctx.translate(centerX, centerY);
        ctx.rotate((intro - 0.5) * 0.03 * (1 - macroZoom));
        ctx.drawImage(img, -drawW * 0.5, -drawH * 0.5, drawW, drawH);
      }
      ctx.restore();

      // Arco sutil de órbita y flujo de aire (Acto 2)
      if (orbitPhase > 0.12) {
        ctx.save();
        ctx.strokeStyle = `rgba(196, 33, 31, ${0.42 * orbitPhase})`;
        ctx.setLineDash([5, 5]);
        ctx.lineWidth = 1.25;
        ctx.beginPath();
        ctx.ellipse(
          centerX,
          centerY + drawH * 0.32,
          drawW * 0.44,
          drawH * 0.08,
          0,
          0.15,
          Math.PI - 0.15,
        );
        ctx.stroke();
        ctx.restore();
      }

      if (fitZoom > 0.15 && macroZoom < 0.7) {
        const alpha = fitZoom * (1 - macroZoom * 1.2);
        ctx.save();
        ctx.strokeStyle = `rgba(196, 33, 31, ${0.78 * alpha})`;
        ctx.lineWidth = 1.5;

        // Cota de pecho
        const chestY = centerY - drawH * 0.08;
        const chestHalf = drawW * 0.27;
        ctx.beginPath();
        ctx.moveTo(centerX - chestHalf, chestY);
        ctx.lineTo(centerX + chestHalf, chestY);
        ctx.moveTo(centerX - chestHalf, chestY - 6);
        ctx.lineTo(centerX - chestHalf, chestY + 6);
        ctx.moveTo(centerX + chestHalf, chestY - 6);
        ctx.lineTo(centerX + chestHalf, chestY + 6);
        ctx.stroke();

        // Cota de cintura
        const waistY = centerY + drawH * 0.14;
        const waistHalf = drawW * 0.25;
        ctx.strokeStyle = dark
          ? `rgba(240, 239, 236, ${0.65 * alpha})`
          : `rgba(28, 28, 26, ${0.65 * alpha})`;
        ctx.beginPath();
        ctx.moveTo(centerX - waistHalf, waistY);
        ctx.lineTo(centerX + waistHalf, waistY);
        ctx.stroke();
        ctx.restore();
      }
    };

    const drawSequenceFrame = (
      ctx: CanvasRenderingContext2D,
      w: number,
      h: number,
      p: number,
    ) => {
      const targetIdx = Math.round(p * (SEQUENCE_COUNT - 1));
      let img = framesRef.current[targetIdx];
      if (!img) {
        // Busca el frame ya descargado más cercano para evitar parpadeos
        for (let offset = 1; offset < SEQUENCE_COUNT; offset += 1) {
          if (targetIdx - offset >= 0 && framesRef.current[targetIdx - offset]) {
            img = framesRef.current[targetIdx - offset];
            break;
          }
          if (targetIdx + offset < SEQUENCE_COUNT && framesRef.current[targetIdx + offset]) {
            img = framesRef.current[targetIdx + offset];
            break;
          }
        }
      }
      if (!img) {
        drawStudioFrame(ctx, w, h, p);
        return;
      }

      ctx.clearRect(0, 0, w, h);

      if (w < h && !activeSequenceIsMobileRef.current) {
        // En móvil con secuencia horizontal: pinta fondo estudio y encuadra la prenda en la zona inferior
        const studioBg = '#E6E3DC';
        ctx.fillStyle = studioBg;
        ctx.fillRect(0, 0, w, h);

        const sx = img.naturalWidth * 0.25;
        const sWidth = img.naturalWidth * 0.75;
        const scale = w / sWidth;
        const dw = w;
        const dh = img.naturalHeight * scale;
        const dy = Math.max(h * 0.34, h - dh - 52);

        ctx.drawImage(img, sx, 0, sWidth, img.naturalHeight, 0, dy, dw, dh);

        // Suaviza el borde superior del recorte para integrarlo al estudio
        const topFade = ctx.createLinearGradient(0, dy, 0, dy + 36);
        topFade.addColorStop(0, studioBg);
        topFade.addColorStop(1, 'rgba(230, 227, 220, 0)');
        ctx.fillStyle = topFade;
        ctx.fillRect(0, dy, w, 36);
        return;
      }

      const scale = Math.max(w / img.naturalWidth, h / img.naturalHeight);
      const dw = img.naturalWidth * scale;
      const dh = img.naturalHeight * scale;
      ctx.drawImage(img, (w - dw) * 0.5, (h - dh) * 0.5, dw, dh);
    };

    const render = () => {
      rafId = window.requestAnimationFrame(render);
      if (!isVisibleRef.current || !isTabVisibleRef.current) return;

      const canvas = canvasRef.current;
      if (!canvas) return;

      const rect = canvas.getBoundingClientRect();
      if (rect.width === 0 || rect.height === 0) return;

      const dpr = Math.min(window.devicePixelRatio || 1, 2);
      const targetW = Math.round(rect.width * dpr);
      const targetH = Math.round(rect.height * dpr);
      if (canvas.width !== targetW || canvas.height !== targetH) {
        canvas.width = targetW;
        canvas.height = targetH;
      }

      const next =
        currentProgressRef.current +
        (targetProgressRef.current - currentProgressRef.current) * 0.14;
      currentProgressRef.current =
        Math.abs(targetProgressRef.current - next) < 0.0005 ? targetProgressRef.current : next;

      if (Math.abs(currentProgressRef.current - lastReported) > 0.004) {
        lastReported = currentProgressRef.current;
        setProgress(currentProgressRef.current);
      }

      const ctx = canvas.getContext('2d');
      if (!ctx) return;

      ctx.save();
      ctx.scale(dpr, dpr);
      if (mode === 'sequence') {
        drawSequenceFrame(ctx, rect.width, rect.height, currentProgressRef.current);
      } else {
        drawStudioFrame(ctx, rect.width, rect.height, currentProgressRef.current);
      }
      ctx.restore();
    };

    rafId = window.requestAnimationFrame(render);
    return () => {
      window.cancelAnimationFrame(rafId);
      window.removeEventListener('scroll', updateTargetFromScroll);
      window.removeEventListener('resize', updateTargetFromScroll);
    };
  }, [mode, reducedMotion, staticMode]);

  const activeIndex =
    progress < 0.22 ? 0 : progress < 0.58 ? 1 : progress < 0.8 ? 2 : 3;
  const activeChapter = CHAPTERS[activeIndex];

  function scrollToChapter(targetP: number) {
    const track = trackRef.current;
    if (!track) return;
    const headerOffset = 67;
    const scrollable = Math.max(1, track.offsetHeight - (window.innerHeight - headerOffset));
    const top = window.scrollY + track.getBoundingClientRect().top - headerOffset + scrollable * targetP;
    window.scrollTo({ top, behavior: 'smooth' });
  }

  function skipShowcase() {
    const track = trackRef.current;
    if (!track) return;
    const end = window.scrollY + track.getBoundingClientRect().bottom - 67;
    window.scrollTo({ top: end, behavior: 'smooth' });
  }

  // Modo estático accesible (prefers-reduced-motion, save-data o pausa manual)
  if (reducedMotion || staticMode) {
    return (
      <section className="home-hero" aria-labelledby="home-title">
        <img
          src={HERO_POSTER}
          alt="Modelos con ropa deportiva negra de Kloset"
          fetchPriority="high"
        />
        <div className="home-hero-shade" />
        <div className="home-hero-copy">
          <p className="home-eyebrow">TU ESTILO, SIN LÍMITES</p>
          <h1 id="home-title">
            Ropa que ya sabe <em>cómo te queda.</em>
          </h1>
          <p className="home-lede">
            Prendas deportivas que combinan comodidad, estilo y rendimiento. Encuentra tu talla ideal con KLOSET.
          </p>
          <div className="home-actions">
            <Link className="home-button home-button-red" to="/catalogo">
              EXPLORAR CATÁLOGO <span aria-hidden="true">→</span>
            </Link>
            <Link className="home-button home-button-outline" to="/medidas">
              CALCULA TU TALLA
            </Link>
            {!reducedMotion && (
              <button
                type="button"
                onClick={() => setStaticMode(false)}
                className="home-button home-button-outline cursor-pointer"
              >
                ACTIVAR RECORRIDO
              </button>
            )}
          </div>
        </div>
        <p className="home-hero-note">
          Tu estilo.
          <br />
          Tu talla.
          <br />
          Sin dudas.
        </p>
      </section>
    );
  }

  const showIntroHero = activeIndex === 0;
  const showFitControls = activeIndex === 2;

  return (
    <section
      ref={trackRef}
      className="home-scroll-track"
      aria-labelledby="home-title"
    >
      <div className="home-scroll-stage">
        <canvas
          ref={canvasRef}
          className="home-scroll-canvas"
          aria-hidden="true"
        />

        {/* Velo lateral para proteger legibilidad del texto HTML en ambos temas */}
        <div className="home-scroll-veil" aria-hidden="true" />

        {/* Barra superior de progreso del recorrido */}
        <div className="home-scroll-progress" aria-hidden="true">
          <span style={{ transform: `scaleX(${Math.max(0.03, progress)})` }} />
        </div>

        {/* Contenido editorial sincronizado con el scroll */}
        <div className="home-scroll-hud">
          <div
            key={activeChapter.id}
            className="home-scroll-copy kl-rise"
          >
            <p className="home-eyebrow">{activeChapter.eyebrow}</p>
            {showIntroHero ? (
              <h1 id="home-title">
                Ropa que ya sabe <em>cómo te queda.</em>
              </h1>
            ) : (
              <>
                <h1 id="home-title" className="sr-only">
                  Ropa que ya sabe cómo te queda.
                </h1>
                <h2 className="home-scroll-chapter-title">{activeChapter.title}</h2>
              </>
            )}

            <p className="home-lede">{activeChapter.copy}</p>

            <div className="home-actions">
              <Link className="home-button home-button-red" to="/catalogo">
                EXPLORAR CATÁLOGO <span aria-hidden="true">→</span>
              </Link>
              <Link
                className="home-button home-button-outline"
                to={showFitControls ? '/medidas' : '/nosotros'}
              >
                {showFitControls ? 'MEDIR MI TALLA' : 'CONOCE MÁS'}
              </Link>
            </div>
          </div>

          {/* Nota editorial derecha solo en Acto 1 */}
          {showIntroHero && (
            <p className="home-hero-note">
              Tu estilo.
              <br />
              Tu talla.
              <br />
              Sin dudas.
            </p>
          )}

          {/* Navegación por actos + controles accesibles */}
          <div className="home-scroll-footer">
            <div className="home-scroll-steps" role="tablist" aria-label="Etapas del recorrido visual">
              {CHAPTERS.map((cap, idx) => (
                <button
                  key={cap.id}
                  type="button"
                  role="tab"
                  aria-selected={activeIndex === idx}
                  onClick={() => scrollToChapter(cap.progress)}
                  className={activeIndex === idx ? 'is-active' : ''}
                >
                  <span>0{idx + 1}</span>
                </button>
              ))}
            </div>

            <div className="home-scroll-hint">
              {activeIndex === 0 ? 'DESLIZA PARA VER LA CAÍDA ↓' : activeChapter.eyebrow}
              {mode === 'sequence' && loadedFrames < SEQUENCE_COUNT && (
                <span className="ml-2 opacity-60">
                  ({Math.round((loadedFrames / SEQUENCE_COUNT) * 100)}%)
                </span>
              )}
            </div>

            <div className="home-scroll-controls">
              <button
                type="button"
                onClick={() => setStaticMode(true)}
                className="home-scroll-ctrl"
              >
                Vista estática
              </button>
              <button
                type="button"
                onClick={skipShowcase}
                className="home-scroll-ctrl"
              >
                Ir a categorías ↓
              </button>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}
