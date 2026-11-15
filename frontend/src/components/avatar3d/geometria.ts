import * as THREE from 'three';

/** Semiejes (lateral, profundidad) de una elipse con contorno `contorno` y proporción `k` = profundidad / ancho. */
export function semiejes(contorno: number, k: number): { a: number; b: number } {
  const a = contorno / (2 * Math.PI * Math.sqrt((1 + k * k) / 2));
  return { a, b: a * k };
}

export function smoothstep(e0: number, e1: number, x: number): number {
  const t = THREE.MathUtils.clamp((x - e0) / (e1 - e0), 0, 1);
  return t * t * (3 - 2 * t);
}

/**
 * Interpolador cúbico monótono (PCHIP) de varios campos a lo largo de `y`.
 * No sobrepasa los valores clave, así que una cintura nunca queda más estrecha de lo indicado.
 */
export function crearPerfil<K extends string>(
  claves: Array<{ y: number } & Record<K, number>>,
  campos: readonly K[],
): (y: number) => Record<K, number> {
  const pts = [...claves].sort((p, q) => p.y - q.y);
  const n = pts.length;
  const h: number[] = [];
  for (let i = 0; i < n - 1; i++) h.push(pts[i + 1].y - pts[i].y);

  const tangentes = {} as Record<K, number[]>;
  for (const campo of campos) {
    const d: number[] = [];
    for (let i = 0; i < n - 1; i++) d.push((pts[i + 1][campo] - pts[i][campo]) / h[i]);
    const m: number[] = new Array<number>(n).fill(0);
    m[0] = d[0];
    m[n - 1] = d[n - 2];
    for (let i = 1; i < n - 1; i++) {
      if (d[i - 1] * d[i] <= 0) continue;
      const w1 = 2 * h[i] + h[i - 1];
      const w2 = h[i] + 2 * h[i - 1];
      m[i] = (w1 + w2) / (w1 / d[i - 1] + w2 / d[i]);
    }
    tangentes[campo] = m;
  }

  return (y: number) => {
    const yy = THREE.MathUtils.clamp(y, pts[0].y, pts[n - 1].y);
    let i = 0;
    while (i < n - 2 && yy > pts[i + 1].y) i++;
    const t = (yy - pts[i].y) / h[i];
    const t2 = t * t;
    const t3 = t2 * t;
    const salida = {} as Record<K, number>;
    for (const campo of campos) {
      const m = tangentes[campo];
      salida[campo] =
        (2 * t3 - 3 * t2 + 1) * pts[i][campo] +
        (t3 - 2 * t2 + t) * h[i] * m[i] +
        (-2 * t3 + 3 * t2) * pts[i + 1][campo] +
        (t3 - t2) * h[i] * m[i + 1];
    }
    return salida;
  };
}

/** Sección transversal de un loft. Todas las distancias en metros. */
export type Anillo = {
  y: number;
  /** Semieje lateral. */
  rx: number;
  /** Semieje de profundidad. */
  rz: number;
  cx?: number;
  cz?: number;
  /** Volumen frontal en dos lóbulos (pectoral o busto). */
  lobF?: number;
  /** Volumen posterior en dos lóbulos (glúteo). */
  lobB?: number;
  /** Amplitud de los pliegues verticales de la tela. */
  onda?: number;
  color?: THREE.Color;
};

/**
 * Une anillos elípticos en una malla continua. La costura de UV queda en la espalda
 * y sus normales se promedian para que no se vea.
 */
export function construirLoft(
  anillos: Anillo[],
  opciones: { segmentos?: number; tapaInicio?: boolean; tapaFin?: boolean } = {},
): THREE.BufferGeometry {
  const segmentos = opciones.segmentos ?? 48;
  const filas = anillos.length;
  const cols = segmentos + 1;
  const ascendente = anillos[filas - 1].y >= anillos[0].y;
  const conColor = anillos.some((a) => a.color);

  const posiciones: number[] = [];
  const uvs: number[] = [];
  const colores: number[] = [];
  const indices: number[] = [];
  const blanco = new THREE.Color(1, 1, 1);

  for (const a of anillos) {
    const cx = a.cx ?? 0;
    const cz = a.cz ?? 0;
    const color = a.color ?? blanco;
    for (let c = 0; c <= segmentos; c++) {
      const u = c / segmentos;
      const theta = u * Math.PI * 2 + Math.PI; // empieza y termina en la espalda
      const sinT = Math.sin(theta);
      const cosT = Math.cos(theta);

      let x = a.rx * sinT;
      let z = a.rz * cosT;

      if (a.lobF && cosT > 0) {
        const separacion = Math.sin(theta * 2) ** 2;
        z += a.lobF * (0.58 * cosT ** 1.5 + 0.42 * separacion * cosT);
      }
      if (a.lobB && cosT < 0) {
        const separacion = Math.sin(theta * 2) ** 2;
        z -= a.lobB * (0.65 * (-cosT) ** 1.3 + 0.35 * separacion * -cosT);
      }
      if (a.onda) {
        const w = a.onda * (Math.sin(theta * 7 + 0.6) + 0.45 * Math.sin(theta * 12 + 2.1));
        x += sinT * w;
        z += cosT * w;
      }

      posiciones.push(cx + x, a.y, cz + z);
      uvs.push(u, a.y);
      if (conColor) colores.push(color.r, color.g, color.b);
    }
  }

  for (let r = 0; r < filas - 1; r++) {
    for (let c = 0; c < segmentos; c++) {
      const a = r * cols + c;
      const b = (r + 1) * cols + c;
      const d = (r + 1) * cols + c + 1;
      const e = r * cols + c + 1;
      if (ascendente) indices.push(a, e, b, e, d, b);
      else indices.push(a, b, e, e, b, d);
    }
  }

  const tapa = (fila: number, miraArriba: boolean) => {
    const a = anillos[fila];
    const centro = posiciones.length / 3;
    posiciones.push(a.cx ?? 0, a.y, a.cz ?? 0);
    uvs.push(0.5, a.y);
    if (conColor) {
      const color = a.color ?? blanco;
      colores.push(color.r, color.g, color.b);
    }
    const base = fila * cols;
    for (let c = 0; c < segmentos; c++) {
      if (miraArriba) indices.push(centro, base + c, base + c + 1);
      else indices.push(centro, base + c + 1, base + c);
    }
  };
  if (opciones.tapaInicio) tapa(0, !ascendente);
  if (opciones.tapaFin) tapa(filas - 1, ascendente);

  const geo = new THREE.BufferGeometry();
  geo.setAttribute('position', new THREE.Float32BufferAttribute(posiciones, 3));
  geo.setAttribute('uv', new THREE.Float32BufferAttribute(uvs, 2));
  if (conColor) geo.setAttribute('color', new THREE.Float32BufferAttribute(colores, 3));
  geo.setIndex(indices);
  geo.computeVertexNormals();

  // La primera y la última columna comparten posición: se promedian sus normales.
  const normales = geo.getAttribute('normal') as THREE.BufferAttribute;
  const v = new THREE.Vector3();
  for (let r = 0; r < filas; r++) {
    const i0 = r * cols;
    const i1 = r * cols + segmentos;
    v.set(
      normales.getX(i0) + normales.getX(i1),
      normales.getY(i0) + normales.getY(i1),
      normales.getZ(i0) + normales.getZ(i1),
    ).normalize();
    normales.setXYZ(i0, v.x, v.y, v.z);
    normales.setXYZ(i1, v.x, v.y, v.z);
  }
  return geo;
}
