import * as THREE from 'three';
import type { Medidas } from '../../lib/fit';
import { construirLoft, crearPerfil, semiejes, type Anillo } from './geometria';

export type GeneroAvatar = 'masculino' | 'femenino';

type SeccionTorso = { rx: number; rz: number; cz: number; lobF: number; lobB: number };
type SeccionPierna = { cx: number; rx: number; rz: number; cz: number };
type SeccionBrazo = { rx: number; rz: number; cz: number };

/**
 * Maniquí paramétrico en metros, con los pies en y = 0. Las alturas salen de la estatura
 * y los contornos de pecho, cintura y cadera, de modo que un contorno de 98 cm mide 98 cm.
 */
export type ModeloCuerpo = {
  H: number;
  genero: GeneroAvatar;
  /** Alturas de referencia (m). */
  y: {
    tobillo: number;
    rodilla: number;
    entrepierna: number;
    cadera: number;
    cintura: number;
    pecho: number;
    hombro: number;
    cuello: number;
    menton: number;
  };
  anchoHombro: number;
  radioCuello: number;
  radioBrazo: number;
  /** Articulación del hombro derecho; el izquierdo es el espejo en x. */
  hombro: { x: number; y: number; z: number; angulo: number };
  /** Distancia hombro → muñeca a lo largo del brazo. */
  largoBrazo: number;
  torso: (y: number) => SeccionTorso;
  /** Pierna derecha; la izquierda es el espejo en x. */
  pierna: (y: number) => SeccionPierna;
  /** Brazo en su eje local: `s` es la distancia bajando desde el hombro. */
  brazo: (s: number) => SeccionBrazo;
  geometrias: {
    torso: THREE.BufferGeometry;
    cabeza: THREE.BufferGeometry;
    brazo: THREE.BufferGeometry;
    piernas: [THREE.BufferGeometry, THREE.BufferGeometry];
    pie: THREE.BufferGeometry;
    posicionPie: [number, number, number];
  };
};

const CAMPOS_TORSO = ['rx', 'rz', 'cz', 'lobF', 'lobB'] as const;
const CAMPOS_PIERNA = ['cx', 'rx', 'rz', 'cz'] as const;
const CAMPOS_BRAZO = ['rx', 'rz', 'cz'] as const;

function muestrear(desde: number, hasta: number, pasos: number): number[] {
  return Array.from({ length: pasos + 1 }, (_, i) => THREE.MathUtils.lerp(desde, hasta, i / pasos));
}

export function construirCuerpo(medidas: Medidas, genero: GeneroAvatar): ModeloCuerpo {
  const fem = genero === 'femenino';
  const H = medidas.h / 100;
  const { lerp, clamp } = THREE.MathUtils;

  const kCintura = clamp(0.74 + ((medidas.waist - 80) / 100) * 0.35, 0.7, 0.95);
  const pecho = semiejes(medidas.chest / 100, fem ? 0.74 : 0.7);
  const cintura = semiejes(medidas.waist / 100, kCintura);
  const cadera = semiejes(medidas.hip / 100, fem ? 0.76 : 0.74);

  const anchoHombro = lerp(H * (fem ? 0.106 : 0.117), pecho.a * (fem ? 0.98 : 1.06), 0.5);
  const radioCuello = H * (fem ? 0.0275 : 0.031) * (0.9 + (0.1 * medidas.chest) / 96);
  const radioBrazo = clamp((medidas.chest / 100) * (fem ? 0.045 : 0.05), 0.034, 0.068);

  const y = {
    tobillo: 0.05 * H,
    rodilla: 0.275 * H,
    entrepierna: 0.47 * H,
    cadera: 0.52 * H,
    cintura: 0.62 * H,
    pecho: 0.73 * H,
    hombro: 0.805 * H,
    cuello: 0.845 * H,
    menton: 0,
  };

  // Cabeza: crece con la estatura, pero menos que el cuerpo.
  const Hc = lerp(1.74, H, 0.5);
  const altoCabeza = 0.136 * Hc;
  const anchoCabeza = 0.0465 * Hc * (fem ? 0.96 : 1);
  y.menton = H - altoCabeza;

  const torso = crearPerfil<(typeof CAMPOS_TORSO)[number]>(
    [
      { y: 0.445 * H, rx: cadera.a * 0.3, rz: cadera.b * 0.28, cz: 0, lobF: 0, lobB: 0 },
      { y: 0.458 * H, rx: cadera.a * 0.62, rz: cadera.b * 0.5, cz: 0, lobF: 0, lobB: 0 },
      { y: 0.48 * H, rx: cadera.a * 0.93, rz: cadera.b * 0.8, cz: -0.002, lobF: 0, lobB: (fem ? 0.014 : 0.009) },
      { y: y.cadera, rx: cadera.a, rz: cadera.b, cz: -0.004, lobF: 0, lobB: fem ? 0.02 : 0.013 },
      {
        y: 0.57 * H,
        rx: lerp(cadera.a, cintura.a, 0.55),
        rz: lerp(cadera.b, cintura.b, 0.6),
        cz: 0,
        lobF: 0,
        lobB: 0.004,
      },
      { y: y.cintura, rx: cintura.a, rz: cintura.b, cz: 0.004, lobF: 0, lobB: 0 },
      {
        y: 0.675 * H,
        rx: lerp(cintura.a, pecho.a, 0.6),
        rz: lerp(cintura.b, pecho.b, 0.6),
        cz: 0.002,
        lobF: fem ? 0.008 : 0.004,
        lobB: 0,
      },
      { y: y.pecho, rx: pecho.a, rz: pecho.b, cz: 0, lobF: fem ? pecho.a * 0.16 : pecho.a * 0.06, lobB: 0 },
      {
        y: 0.775 * H,
        rx: lerp(pecho.a, anchoHombro, 0.6),
        rz: pecho.b * 0.9,
        cz: -0.003,
        lobF: fem ? 0.006 : 0.004,
        lobB: 0,
      },
      { y: y.hombro, rx: anchoHombro, rz: pecho.b * 0.72, cz: -0.006, lobF: 0, lobB: 0 },
      { y: 0.819 * H, rx: anchoHombro * 0.85, rz: pecho.b * 0.62, cz: -0.007, lobF: 0, lobB: 0 },
      { y: 0.833 * H, rx: anchoHombro * 0.6, rz: pecho.b * 0.54, cz: -0.006, lobF: 0, lobB: 0 },
      { y: y.cuello, rx: radioCuello * 1.36, rz: radioCuello * 1.22, cz: -0.004, lobF: 0, lobB: 0 },
      { y: 0.86 * H, rx: radioCuello * 1.04, rz: radioCuello * 1.04, cz: 0.003, lobF: 0, lobB: 0 },
      { y: Math.max(0.875 * H, y.menton + 0.03), rx: radioCuello * 0.94, rz: radioCuello * 0.96, cz: 0.008, lobF: 0, lobB: 0 },
    ],
    CAMPOS_TORSO,
  );

  // De la rodilla hacia abajo el grosor sigue menos a la cadera que el muslo.
  const baja = { a: lerp(0.18, cadera.a, 0.55), b: lerp(0.133, cadera.b, 0.55) };
  const pierna = crearPerfil<(typeof CAMPOS_PIERNA)[number]>(
    [
      { y: 0.03 * H, cx: cadera.a * 0.42, rx: baja.a * 0.19, rz: baja.b * 0.29, cz: 0 },
      { y: 0.06 * H, cx: cadera.a * 0.42, rx: baja.a * 0.185, rz: baja.b * 0.28, cz: -0.002 },
      { y: 0.12 * H, cx: cadera.a * 0.425, rx: baja.a * 0.23, rz: baja.b * 0.34, cz: -0.006 },
      { y: 0.19 * H, cx: cadera.a * 0.43, rx: baja.a * 0.31, rz: baja.b * 0.5, cz: -0.01 },
      { y: 0.24 * H, cx: cadera.a * 0.435, rx: baja.a * 0.29, rz: baja.b * 0.44, cz: -0.002 },
      { y: y.rodilla, cx: cadera.a * 0.44, rx: baja.a * 0.31, rz: baja.b * 0.47, cz: 0.004 },
      { y: 0.32 * H, cx: cadera.a * 0.45, rx: cadera.a * 0.36, rz: cadera.b * 0.56, cz: 0.003 },
      { y: 0.4 * H, cx: cadera.a * 0.47, rx: cadera.a * 0.44, rz: cadera.b * 0.7, cz: 0 },
      { y: y.entrepierna, cx: cadera.a * 0.49, rx: cadera.a * 0.5, rz: cadera.b * 0.8, cz: -0.004 },
      { y: 0.515 * H, cx: cadera.a * 0.47, rx: cadera.a * 0.46, rz: cadera.b * 0.8, cz: -0.004 },
    ],
    CAMPOS_PIERNA,
  );

  const rU = radioBrazo;
  const largoBrazo = 0.33 * H;
  const clavesBrazo = [
    { y: -1.0 * rU, rx: 0.15 * rU, rz: 0.15 * rU, cz: 0 },
    { y: -0.85 * rU, rx: 0.58 * rU, rz: 0.58 * rU, cz: 0 },
    { y: -0.5 * rU, rx: 0.92 * rU, rz: 0.94 * rU, cz: 0 },
    { y: 0, rx: 1.1 * rU, rz: 1.14 * rU, cz: 0 },
    { y: 0.035 * H, rx: 1.12 * rU, rz: 1.16 * rU, cz: 0 },
    { y: 0.09 * H, rx: 1.0 * rU, rz: 1.04 * rU, cz: 0.002 },
    { y: 0.16 * H, rx: 0.84 * rU, rz: 0.86 * rU, cz: 0 },
    { y: 0.19 * H, rx: 0.8 * rU, rz: 0.8 * rU, cz: -0.002 },
    { y: 0.225 * H, rx: 0.84 * rU, rz: 0.82 * rU, cz: 0.004 },
    { y: 0.29 * H, rx: 0.64 * rU, rz: 0.66 * rU, cz: 0.008 },
    { y: largoBrazo, rx: 0.46 * rU, rz: 0.56 * rU, cz: 0.01 },
    { y: 0.352 * H, rx: 0.34 * rU, rz: 0.8 * rU, cz: 0.014 },
    { y: 0.385 * H, rx: 0.3 * rU, rz: 0.86 * rU, cz: 0.018 },
    { y: 0.415 * H, rx: 0.24 * rU, rz: 0.64 * rU, cz: 0.022 },
    { y: 0.432 * H, rx: 0.1 * rU, rz: 0.26 * rU, cz: 0.024 },
  ];
  const brazo = crearPerfil<(typeof CAMPOS_BRAZO)[number]>(clavesBrazo, CAMPOS_BRAZO);

  const hombroX = anchoHombro - rU * 0.35;
  const hombroY = 0.805 * H - rU * 1.02;
  // El brazo se abre lo justo para no atravesar la cadera.
  const angulo = clamp(Math.atan((cadera.a + 0.062 + rU - hombroX) / (0.3 * H)), 0.1, 0.34);

  /* ---------- Mallas ---------- */

  const anillosTorso: Anillo[] = muestrear(0.445 * H, Math.max(0.875 * H, y.menton + 0.03), 64).map((yy) => ({
    y: yy,
    ...torso(yy),
  }));
  const geoTorso = construirLoft(anillosTorso, { segmentos: 64, tapaInicio: true, tapaFin: true });

  const geoPierna = (lado: 1 | -1) =>
    construirLoft(
      muestrear(0.03 * H, 0.515 * H, 44).map((yy) => {
        const p = pierna(yy);
        return { y: yy, rx: p.rx, rz: p.rz, cx: lado * p.cx, cz: p.cz };
      }),
      { segmentos: 32, tapaInicio: true, tapaFin: true },
    );

  const sInicio = clavesBrazo[0].y;
  const sFin = clavesBrazo[clavesBrazo.length - 1].y;
  const geoBrazo = construirLoft(
    muestrear(sInicio, sFin, 52).map((s) => {
      const b = brazo(s);
      return { y: -s, rx: b.rx, rz: b.rz, cz: b.cz };
    }),
    { segmentos: 28, tapaInicio: true, tapaFin: true },
  );

  // Cabeza ovoide, sin rasgos.
  const perfilCabeza = crearPerfil<'r' | 'cz'>(
    [
      { y: 0, r: 0.1, cz: 0.014 },
      { y: 0.05, r: 0.44, cz: 0.014 },
      { y: 0.2, r: 0.74, cz: 0.01 },
      { y: 0.42, r: 0.95, cz: 0.002 },
      { y: 0.62, r: 1, cz: -0.006 },
      { y: 0.82, r: 0.84, cz: -0.008 },
      { y: 0.94, r: 0.52, cz: -0.006 },
      { y: 1, r: 0.1, cz: -0.004 },
    ],
    ['r', 'cz'],
  );
  const geoCabeza = construirLoft(
    muestrear(0, 1, 36).map((t) => {
      const c = perfilCabeza(t);
      return { y: y.menton + t * altoCabeza, rx: anchoCabeza * c.r, rz: anchoCabeza * 1.2 * c.r, cz: c.cz + 0.008 };
    }),
    { segmentos: 40, tapaInicio: true, tapaFin: true },
  );

  // Pie: media cúpula alargada apoyada en el suelo.
  const escalaPie = H / 1.74;
  const geoPie = new THREE.SphereGeometry(1, 28, 18);
  const posPie = geoPie.getAttribute('position') as THREE.BufferAttribute;
  for (let i = 0; i < posPie.count; i++) {
    const py = posPie.getY(i);
    const pz = posPie.getZ(i);
    // Empeine alto junto al tobillo, puntera baja.
    const alto = py > 0 ? py * (0.078 - 0.04 * smoothFrente(pz)) : 0;
    posPie.setXYZ(i, posPie.getX(i) * 0.05 * escalaPie, alto * escalaPie, pz * 0.128 * escalaPie);
  }
  geoPie.computeVertexNormals();
  const tobillo = pierna(0.03 * H);

  return {
    H,
    genero,
    y,
    anchoHombro,
    radioCuello,
    radioBrazo,
    hombro: { x: hombroX, y: hombroY, z: -0.008, angulo },
    largoBrazo,
    torso,
    pierna,
    brazo,
    geometrias: {
      torso: geoTorso,
      cabeza: geoCabeza,
      brazo: geoBrazo,
      piernas: [geoPierna(1), geoPierna(-1)],
      pie: geoPie,
      posicionPie: [tobillo.cx, 0, 0.045 * escalaPie],
    },
  };
}

function smoothFrente(z: number): number {
  const t = THREE.MathUtils.clamp((z + 0.2) / 1.2, 0, 1);
  return t * t * (3 - 2 * t);
}

export function liberarCuerpo(modelo: ModeloCuerpo): void {
  const g = modelo.geometrias;
  [g.torso, g.cabeza, g.brazo, g.piernas[0], g.piernas[1], g.pie].forEach((geo) => geo.dispose());
}
