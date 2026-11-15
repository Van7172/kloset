import * as THREE from 'three';
import { COLOR_ESTADO, type AnalisisAjuste, type ClaveZona, type Corte } from '../../lib/fit';
import type { CategoriaPrenda3D } from './categoria';
import type { ModeloCuerpo } from './cuerpo';
import { construirLoft, crearPerfil, smoothstep, type Anillo } from './geometria';

export type EtiquetaZona3D = { clave: ClaveZona; y: number; radio: number };

export type PrendaConstruida = {
  /** Cuerpo de la prenda, en coordenadas del maniquí. */
  cuerpo: THREE.BufferGeometry;
  /** Manga en el eje local del brazo (se usa en ambos lados). */
  manga: THREE.BufferGeometry | null;
  /** Perneras derecha e izquierda, en coordenadas del maniquí. */
  perneras: [THREE.BufferGeometry, THREE.BufferGeometry] | null;
  etiquetas: EtiquetaZona3D[];
  /** Altura media de la prenda, para encuadrar el acercamiento. */
  centroY: number;
};

const DOS_PI = Math.PI * 2;

function muestrear(desde: number, hasta: number, pasos: number): number[] {
  return Array.from({ length: pasos + 1 }, (_, i) => THREE.MathUtils.lerp(desde, hasta, i / pasos));
}

/** Contorno exterior del cuerpo a una altura: torso arriba, las dos piernas juntas abajo. */
function envolvente(m: ModeloCuerpo, y: number) {
  const baseTorso = 0.445 * m.H;
  if (y >= m.y.cadera) return m.torso(y);
  const t = m.torso(Math.max(y, baseTorso));
  const p = m.pierna(Math.min(y, 0.515 * m.H));
  const hayTorso = y >= baseTorso;
  // Elipse que contiene los dos muslos: apenas más ancha que ellos y con la profundidad
  // necesaria para cubrir sus esquinas (así el bajo de la prenda no hace escalón).
  const rxPiernas = (p.cx + p.rx) * 1.02;
  let rzPiernas = p.rz;
  for (let i = 1; i < 12; i++) {
    const fi = (i / 12) * (Math.PI / 2);
    const fx = (p.cx + p.rx * Math.sin(fi)) / rxPiernas;
    rzPiernas = Math.max(rzPiernas, (p.rz * Math.cos(fi)) / Math.sqrt(1 - fx * fx));
  }
  return {
    rx: Math.max(hayTorso ? t.rx : 0, rxPiernas),
    rz: Math.max(hayTorso ? t.rz : 0, rzPiernas),
    cz: hayTorso ? t.cz : p.cz,
    lobF: 0,
    lobB: hayTorso ? t.lobB : 0,
  };
}

/** Color del mapa de ajuste a cada altura, interpolando entre zonas vecinas. */
function crearColorPorAltura(claves: Array<{ y: number; color: string }>): (y: number) => THREE.Color {
  const pts = claves.map((c) => ({ y: c.y, color: new THREE.Color(c.color) })).sort((a, b) => a.y - b.y);
  return (y: number) => {
    if (y <= pts[0].y) return pts[0].color.clone();
    for (let i = 0; i < pts.length - 1; i++) {
      if (y <= pts[i + 1].y) {
        const t = smoothstep(pts[i].y, pts[i + 1].y, y);
        return pts[i].color.clone().lerp(pts[i + 1].color, t);
      }
    }
    return pts[pts.length - 1].color.clone();
  };
}

/** Holgura de contorno (cm) convertida en separación radial entre tela y cuerpo (m). */
function separacion(holguraCm: number, grosor: number): number {
  return Math.max(grosor, holguraCm / 100 / DOS_PI);
}

function prendaSuperior(
  m: ModeloCuerpo,
  analisis: AnalisisAjuste,
  categoria: 'camisetas' | 'capas' | 'tops',
  corte: Corte,
  deltaTalla: number,
): PrendaConstruida {
  const H = m.H;
  // Un top solo trae la zona de pecho: el resto de zonas la toman como referencia.
  const zPecho = analisis.zonas.find((z) => z.clave === 'pecho')!;
  const zona = (c: ClaveZona) => analisis.zonas.find((z) => z.clave === c) ?? zPecho;
  const zCintura = zona('cintura');
  const zCadera = zona('cadera');
  const zLargo = zona('largo');

  const grosor = categoria === 'capas' ? 0.007 : 0.004;
  const holgura = crearPerfil<'h'>(
    [
      { y: m.y.cadera, h: zCadera.holguraCm },
      { y: m.y.cintura, h: zCintura.holguraCm },
      { y: m.y.pecho, h: zPecho.holguraCm },
      { y: m.y.hombro, h: zPecho.holguraCm },
    ],
    ['h'],
  );
  const dr = (y: number) => separacion(holgura(y).h, grosor);

  const esTop = categoria === 'tops';
  const yBajo = esTop
    ? 0.665 * H
    : THREE.MathUtils.clamp(0.845 * H - zLargo.prendaCm / 100 - (categoria === 'capas' ? 0.01 * H : 0), 0.34 * H, 0.7 * H);
  // El top se modela como banda de pecho: termina bajo la clavícula, sin tirantes.
  const yCuello = categoria === 'capas' ? 0.86 * H : esTop ? 0.79 * H : 0.841 * H;
  const caidaHombro = (corte === 'Oversize' ? 0.016 : corte === 'Regular' ? 0.005 : 0) + Math.max(0, dr(m.y.pecho) - 0.01) * 0.6;

  const colorEn = crearColorPorAltura(
    esTop
      ? [{ y: m.y.pecho, color: COLOR_ESTADO[zPecho.estado] }]
      : [
          { y: yBajo, color: COLOR_ESTADO[zLargo.estado] },
          { y: yBajo + 0.022 * H, color: COLOR_ESTADO[zLargo.estado] },
          { y: Math.max(yBajo + 0.05 * H, m.y.cadera), color: COLOR_ESTADO[zCadera.estado] },
          { y: Math.max(yBajo + 0.1 * H, m.y.cintura), color: COLOR_ESTADO[zCintura.estado] },
          { y: m.y.pecho, color: COLOR_ESTADO[zPecho.estado] },
        ],
  );

  const alturas = muestrear(yBajo, yCuello, 60);
  const anillos: Anillo[] = alturas.map((y) => {
    const e = envolvente(m, y);
    // Sobre los hombros la tela descansa en el cuerpo: la holgura queda por debajo.
    const d = THREE.MathUtils.lerp(dr(y), grosor + 0.002, smoothstep(0.765 * H, 0.8 * H, y));
    let rx = e.rx + d;
    rx += caidaHombro * smoothstep(0.74 * H, 0.8 * H, y) * (1 - smoothstep(0.805 * H, 0.835 * H, y));
    return {
      y,
      rx,
      rz: e.rz + d,
      cz: e.cz,
      lobF: e.lobF * 0.8,
      lobB: e.lobB,
      color: colorEn(y),
    };
  });

  // Caída: por debajo del pecho la tela cuelga y no puede volver hacia el cuerpo más rápido
  // de lo que permite su holgura. Una prenda ceñida sigue la cintura; una amplia cae recta.
  const iPecho = alturas.findIndex((y) => y >= m.y.pecho);
  for (let i = iPecho - 1; i >= 0; i--) {
    const sup = anillos[i + 1];
    const a = anillos[i];
    const dy = alturas[i + 1] - alturas[i];
    const e = envolvente(m, a.y);
    const sueltoArriba = sup.rx - envolvente(m, sup.y).rx;
    const cierre = THREE.MathUtils.lerp(1.1, 0.08, smoothstep(0.0042, 0.009, sueltoArriba));
    a.rx = Math.max(a.rx, sup.rx - cierre * dy);
    a.rz = Math.max(a.rz, sup.rz - cierre * dy);
    a.lobF = Math.max(a.lobF ?? 0, (sup.lobF ?? 0) - 0.5 * dy);
    const suelto = a.rx - e.rx;
    a.onda = THREE.MathUtils.clamp((suelto - 0.022) * 0.16, 0, 0.011) * (1 - smoothstep(m.y.cintura, m.y.pecho, a.y));
  }

  const bajo = anillos[0];
  const alto = anillos[anillos.length - 1];
  const cuerpo = construirLoft(
    [
      { ...bajo, y: bajo.y + 0.012, rx: bajo.rx - 0.005, rz: bajo.rz - 0.005 },
      ...anillos,
      { ...alto, y: alto.y + 0.007, rx: alto.rx - 0.004, rz: alto.rz - 0.004 },
    ],
    { segmentos: 72 },
  );

  /* ---------- Manga ---------- */
  let manga: THREE.BufferGeometry | null = null;
  if (!esTop) {
    const rU = m.radioBrazo;
    const larga = categoria === 'capas';
    const dm = Math.max(grosor + 0.002, dr(m.y.pecho) * 0.9 + 0.003);
    const sFin = larga
      ? Math.min(m.largoBrazo + 0.004 + deltaTalla * 0.006 * H, m.largoBrazo + 0.035 * H)
      : THREE.MathUtils.clamp(
          (corte === 'Oversize' ? 0.15 : corte === 'Regular' ? 0.12 : 0.1) * H + deltaTalla * 0.007 * H,
          0.07 * H,
          0.2 * H,
        );
    const vuelo = larga ? 0 : corte === 'Oversize' ? 0.018 : corte === 'Regular' ? 0.009 : 0.003;
    const colorManga = new THREE.Color(COLOR_ESTADO[zPecho.estado]);

    const anillosManga: Anillo[] = muestrear(-rU, sFin, 34).map((s) => {
      const b = m.brazo(s);
      // En lo alto del hombro la manga apoya; la holgura aparece al bajar por el brazo.
      let d = THREE.MathUtils.lerp(grosor + 0.002, dm, smoothstep(-rU, 0.04 * H, s)) + vuelo * smoothstep(sFin * 0.35, sFin, s);
      if (larga) d = THREE.MathUtils.lerp(d, 0.005, smoothstep(sFin - 0.03 * H, sFin - 0.008 * H, s));
      return {
        y: -s + (grosor + 0.002) * (1 - smoothstep(-rU, 0, s)),
        rx: b.rx + d,
        rz: b.rz + d,
        cz: b.cz,
        color: colorManga,
      };
    });
    const boca = anillosManga[anillosManga.length - 1];
    manga = construirLoft(
      [...anillosManga, { ...boca, y: boca.y + 0.01, rx: boca.rx - 0.004, rz: boca.rz - 0.004 }],
      { segmentos: 32, tapaInicio: true },
    );
  }

  const radioEn = (y: number) => {
    const i = alturas.findIndex((v) => v >= y);
    return anillos[Math.max(0, i)].rx;
  };
  const etiquetas: EtiquetaZona3D[] = [{ clave: 'pecho', y: m.y.pecho, radio: radioEn(m.y.pecho) + m.radioBrazo * 2 }];
  if (!esTop) {
    if (yBajo < m.y.cintura - 0.02 * H) etiquetas.push({ clave: 'cintura', y: m.y.cintura, radio: radioEn(m.y.cintura) });
    if (yBajo < m.y.cadera - 0.02 * H) etiquetas.push({ clave: 'cadera', y: m.y.cadera, radio: radioEn(m.y.cadera) });
    etiquetas.push({ clave: 'largo', y: yBajo + 0.005, radio: bajo.rx });
  }

  return { cuerpo, manga, perneras: null, etiquetas, centroY: (yBajo + yCuello) / 2 };
}

function prendaInferior(
  m: ModeloCuerpo,
  analisis: AnalisisAjuste,
  categoria: 'shorts' | 'mallas' | 'pantalones',
  deltaTalla: number,
): PrendaConstruida {
  const H = m.H;
  const zona = (c: ClaveZona) => analisis.zonas.find((z) => z.clave === c)!;
  const zCintura = zona('cintura');
  const zCadera = zona('cadera');
  const esMalla = categoria === 'mallas';
  const grosor = esMalla ? 0.003 : 0.005;

  // La malla es elástica: siempre va pegada, y el mapa de ajuste indica si aprieta.
  const dCintura = esMalla ? grosor : separacion(zCintura.holguraCm, grosor);
  const dCadera = esMalla ? grosor : separacion(zCadera.holguraCm, grosor);

  // La prenda inferior es el propio cuerpo desplazado hacia fuera: cadera y perneras
  // se funden igual que el torso y las piernas del maniquí.
  const yBase = 0.447 * H;
  const yCinturilla = 0.635 * H;
  const colorCintura = new THREE.Color(COLOR_ESTADO[zCintura.estado]);
  const colorCadera = new THREE.Color(COLOR_ESTADO[zCadera.estado]);

  const alturas = muestrear(yBase, yCinturilla, 30);
  const anillos: Anillo[] = alturas.map((y) => {
    const e = m.torso(y);
    const t = smoothstep(m.y.cadera, m.y.cintura, y);
    // La cinturilla elástica cierra sobre el cuerpo.
    const d = THREE.MathUtils.lerp(
      THREE.MathUtils.lerp(dCadera, dCintura, t),
      grosor,
      smoothstep(yCinturilla - 0.03 * H, yCinturilla - 0.012 * H, y),
    );
    return { y, rx: e.rx + d, rz: e.rz + d, cz: e.cz, lobB: e.lobB, color: colorCadera.clone().lerp(colorCintura, t) };
  });
  const alto = anillos[anillos.length - 1];
  const cuerpo = construirLoft(
    [...anillos, { ...alto, y: alto.y + 0.006, rx: alto.rx - 0.004, rz: alto.rz - 0.004 }],
    { segmentos: 64, tapaInicio: true },
  );

  const yBajo =
    categoria === 'shorts'
      ? THREE.MathUtils.clamp(0.345 * H - deltaTalla * 0.006 * H, 0.3 * H, 0.4 * H)
      : categoria === 'mallas'
        ? 0.07 * H
        : 0.055 * H;
  const yAlto = 0.515 * H;
  const dPierna = esMalla ? grosor : categoria === 'shorts' ? Math.max(0.01, dCadera + 0.008) : Math.max(0.008, dCadera * 0.8 + 0.004);
  const cierre = esMalla ? 9 : categoria === 'shorts' ? 0.06 : 0.3;

  const pernera = (lado: 1 | -1) => {
    const ys = muestrear(yAlto, yBajo, 44);
    const tubo: Anillo[] = [];
    ys.forEach((y, i) => {
      const p = m.pierna(y);
      // Arriba coincide con la cadera de la prenda; la holgura propia de la pernera aparece al bajar.
      let d = THREE.MathUtils.lerp(dCadera, dPierna, smoothstep(yAlto - 0.01 * H, yAlto - 0.07 * H, y));
      if (categoria === 'pantalones') d = THREE.MathUtils.lerp(d, 0.007, 1 - smoothstep(yBajo + 0.006 * H, yBajo + 0.03 * H, y));
      let rx = p.rx + d;
      let rz = p.rz + d;
      if (i > 0) {
        const dy = ys[i - 1] - y;
        const puno = categoria === 'pantalones' && y < yBajo + 0.03 * H;
        if (!puno) {
          rx = Math.max(rx, tubo[i - 1].rx - cierre * dy);
          rz = Math.max(rz, tubo[i - 1].rz - cierre * dy);
        }
      }
      tubo.push({ y, rx, rz, cx: lado * p.cx, cz: p.cz, color: colorCadera });
    });
    const boca = tubo[tubo.length - 1];
    return construirLoft(
      [...tubo, { ...boca, y: boca.y + 0.01, rx: boca.rx - 0.004, rz: boca.rz - 0.004 }],
      { segmentos: 32, tapaInicio: true },
    );
  };

  return {
    cuerpo,
    manga: null,
    perneras: [pernera(1), pernera(-1)],
    etiquetas: [
      { clave: 'cintura', y: m.y.cintura, radio: m.torso(m.y.cintura).rx + dCintura + m.radioBrazo * 2 },
      { clave: 'cadera', y: m.y.cadera, radio: m.torso(m.y.cadera).rx + dCadera + m.radioBrazo * 2 },
    ],
    centroY: (yBajo + yCinturilla) / 2,
  };
}

/**
 * Construye la prenda sobre el maniquí. La separación tela-cuerpo sale de la holgura
 * en cm que calcula `analizarAjuste`, así que cambiar de talla cambia el volumen real.
 * @param deltaTalla Tallas de diferencia respecto a la recomendada (afecta largos de manga y pernera).
 */
export function construirPrenda(
  m: ModeloCuerpo,
  analisis: AnalisisAjuste,
  categoria: CategoriaPrenda3D,
  corte: Corte,
  deltaTalla: number,
): PrendaConstruida {
  if (categoria === 'shorts' || categoria === 'mallas' || categoria === 'pantalones') {
    return prendaInferior(m, analisis, categoria, deltaTalla);
  }
  return prendaSuperior(m, analisis, categoria, corte, deltaTalla);
}

export function liberarPrenda(p: PrendaConstruida): void {
  p.cuerpo.dispose();
  p.manga?.dispose();
  p.perneras?.forEach((g) => g.dispose());
}
