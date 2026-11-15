/* Lógica de talla y ajuste — portada de "Kloset.dc.html" */

export const TALLAS = ['XS', 'S', 'M', 'L', 'XL', 'XXL'] as const;
export const CORTES = ['Slim', 'Regular', 'Oversize'] as const;

export type Talla = (typeof TALLAS)[number];
export type Corte = (typeof CORTES)[number];

export type Medidas = {
  h: number;
  chest: number;
  waist: number;
  hip: number;
};

/**
 * Una prenda superior se elige por pecho; una inferior, por cadera.
 * 'top' es una prenda corta (top deportivo): solo importa el pecho.
 */
export type TipoPrenda = 'superior' | 'top' | 'inferior';

export const MEDIDAS_INICIALES: Medidas = { h: 174, chest: 98, waist: 84, hip: 99 };
let toleranciaCm = 2.5;
export function configurarTolerancia(valor: number) { if (Number.isFinite(valor) && valor >= 0 && valor <= 10) toleranciaCm = valor; }

export const RANGOS_MEDIDAS = [
  { key: 'h' as const, label: 'Estatura', hint: 'Sin zapatos, contra la pared', min: 140, max: 210 },
  { key: 'chest' as const, label: 'Pecho', hint: 'Por la parte más ancha, brazos abajo', min: 70, max: 140 },
  { key: 'waist' as const, label: 'Cintura', hint: 'A la altura del ombligo, sin apretar', min: 55, max: 130 },
  { key: 'hip' as const, label: 'Cadera', hint: 'Por la parte más ancha de la cadera', min: 70, max: 145 },
];

type Rango = readonly [number, number];

/** Medidas de cuerpo (cm) para las que está pensada cada talla. Única guía de la tienda. */
export const GUIA_TALLAS: Record<Talla, { pecho: Rango; cintura: Rango; cadera: Rango; estatura: Rango }> = {
  XS: { pecho: [82, 88], cintura: [68, 74], cadera: [84, 90], estatura: [160, 168] },
  S: { pecho: [88, 96], cintura: [74, 82], cadera: [90, 98], estatura: [166, 174] },
  M: { pecho: [96, 104], cintura: [82, 90], cadera: [98, 106], estatura: [172, 180] },
  L: { pecho: [104, 112], cintura: [90, 98], cadera: [106, 114], estatura: [178, 186] },
  XL: { pecho: [112, 120], cintura: [98, 106], cadera: [114, 122], estatura: [182, 192] },
  XXL: { pecho: [120, 128], cintura: [106, 114], cadera: [122, 130], estatura: [186, 198] },
};

/**
 * Talla más pequeña cuyo rango cubre la medida que manda (pecho o cadera), con la
 * tolerancia configurada en el panel. Debe coincidir con `PerfilesCorporales::tallaRecomendada`.
 */
export function indiceTalla(medidas: Medidas, _corte: Corte, tipo: TipoPrenda = 'superior'): number {
  const clave = tipo === 'inferior' ? 'cadera' : 'pecho';
  const cuerpo = tipo === 'inferior' ? medidas.hip : medidas.chest;
  const i = TALLAS.findIndex((t) => cuerpo < GUIA_TALLAS[t][clave][1] + toleranciaCm);
  return i === -1 ? TALLAS.length - 1 : i;
}

export function tallaRecomendada(medidas: Medidas, corte: Corte, tipo: TipoPrenda = 'superior'): Talla {
  return TALLAS[indiceTalla(medidas, corte, tipo)];
}

export const COPY_CORTE: Record<Corte, string> = {
  Slim: 'Segunda piel. El tejido sigue el torso sin arrugas en el costado; pensado para correr y entrenar en sala.',
  Regular: 'Holgura de 5 cm en pecho. Cae recto y admite una capa base debajo sin tirar.',
  Oversize: 'Hombro caído y 11 cm extra de cuerpo. Se mueve contigo en lugar de acompañarte al cuerpo.',
};

export const VEREDICTO_CORTE: Record<Corte, string> = {
  Slim: 'ceñido en pecho, largo justo',
  Regular: 'cae limpio, hombro alineado',
  Oversize: 'amplio, hombro por debajo',
};

export const ANCHO_FIGURA: Record<Corte, string> = { Slim: '38%', Regular: '44%', Oversize: '52%' };
export const APRIETE_FIGURA: Record<Corte, number> = { Slim: 0.9, Regular: 1, Oversize: 1.12 };

/* ------------------------------------------------------------------ */
/* Análisis de ajuste: cuerpo del usuario contra la prenda de una talla */
/* ------------------------------------------------------------------ */

/** Holgura de diseño (cm de contorno que la prenda añade al cuerpo) por corte y zona. */
const HOLGURA_DISENO: Record<Corte, { pecho: number; cintura: number; cadera: number }> = {
  Slim: { pecho: 2, cintura: 4, cadera: 3 },
  Regular: { pecho: 5, cintura: 7, cadera: 6 },
  Oversize: { pecho: 11, cintura: 13, cadera: 12 },
};

/** Cuánto más larga o corta cae la prenda según el corte (cm). */
const LARGO_EXTRA: Record<Corte, number> = { Slim: -1, Regular: 0, Oversize: 4 };

export type EstadoAjuste = 'tira' | 'ceñido' | 'ideal' | 'holgado' | 'sobra';
export type ClaveZona = 'pecho' | 'cintura' | 'cadera' | 'largo';

export type ZonaAnalisis = {
  clave: ClaveZona;
  zona: string;
  /** Medida del cuerpo en esa zona (cm). */
  cuerpoCm: number;
  /** Medida de la prenda en esa zona para la talla y corte elegidos (cm). */
  prendaCm: number;
  /** prendaCm − cuerpoCm. Negativo: la prenda es más pequeña que el cuerpo. */
  holguraCm: number;
  estado: EstadoAjuste;
};

export type AnalisisAjuste = {
  talla: Talla;
  corte: Corte;
  tipo: TipoPrenda;
  zonas: ZonaAnalisis[];
  /** Peor estado entre los contornos (el largo no decide la talla). */
  veredicto: EstadoAjuste;
};

/** Convención de mapa de ajuste: cálido = aprieta, verde = bien, frío = sobra. */
export const COLOR_ESTADO: Record<EstadoAjuste, string> = {
  tira: '#C4211F',
  ceñido: '#DB7A1F',
  ideal: '#1F7A4C',
  holgado: '#4F86B8',
  sobra: '#2B4F8C',
};

export const ETIQUETA_ESTADO: Record<EstadoAjuste, string> = {
  tira: 'Tira',
  ceñido: 'Ceñido',
  ideal: 'Ideal',
  holgado: 'Holgado',
  sobra: 'Sobra',
};

const ETIQUETA_LARGO: Record<EstadoAjuste, string> = {
  tira: 'Queda corta',
  ceñido: 'Largo justo',
  ideal: 'Cae en la cadera',
  holgado: 'Algo larga',
  sobra: 'Queda larga',
};

export function etiquetaZona(z: ZonaAnalisis): string {
  return z.clave === 'largo' ? ETIQUETA_LARGO[z.estado] : ETIQUETA_ESTADO[z.estado];
}

/** "+5 cm", "−1,5 cm", "0 cm". */
export function formatoHolgura(cm: number): string {
  const r = Math.round(cm * 2) / 2;
  if (r === 0) return '0 cm';
  const texto = Math.abs(r).toLocaleString('es-PE', { maximumFractionDigits: 1 });
  return `${r > 0 ? '+' : '−'}${texto} cm`;
}

function estadoContorno(holgura: number, diseno: number): EstadoAjuste {
  const minimoIdeal = diseno - toleranciaCm;
  if (holgura < minimoIdeal - 3) return 'tira';
  if (holgura < minimoIdeal) return 'ceñido';
  if (holgura <= diseno + 8) return 'ideal';
  if (holgura <= diseno + 14) return 'holgado';
  return 'sobra';
}

function estadoLargo(holgura: number): EstadoAjuste {
  if (holgura < -3) return 'tira';
  if (holgura < 0) return 'ceñido';
  if (holgura <= 6) return 'ideal';
  if (holgura <= 10) return 'holgado';
  return 'sobra';
}

const GRAVEDAD: Record<EstadoAjuste, number> = { ideal: 0, ceñido: 1, holgado: 1, tira: 2, sobra: 2 };

/**
 * Compara el cuerpo con la prenda de una talla y corte. La prenda se dimensiona para
 * el extremo alto del rango de la talla más la holgura de diseño del corte.
 */
export function analizarAjuste(
  medidas: Medidas,
  talla: Talla,
  corte: Corte,
  tipo: TipoPrenda = 'superior',
): AnalisisAjuste {
  const guia = GUIA_TALLAS[talla];
  const diseno = HOLGURA_DISENO[corte];

  const contorno = (clave: 'pecho' | 'cintura' | 'cadera', zona: string, cuerpoCm: number): ZonaAnalisis => {
    const prendaCm = guia[clave][1] + diseno[clave];
    const holguraCm = prendaCm - cuerpoCm;
    return { clave, zona, cuerpoCm, prendaCm, holguraCm, estado: estadoContorno(holguraCm, diseno[clave]) };
  };

  const zonas: ZonaAnalisis[] = [];
  if (tipo !== 'inferior') zonas.push(contorno('pecho', 'Pecho', medidas.chest));
  if (tipo !== 'top') {
    zonas.push(contorno('cintura', 'Cintura', medidas.waist));
    zonas.push(contorno('cadera', 'Cadera', medidas.hip));
  }

  if (tipo === 'superior') {
    // Largo de espalda: del hombro a la cadera es ~38 % de la estatura.
    const estaturaTalla = (guia.estatura[0] + guia.estatura[1]) / 2;
    const prendaCm = Math.round(estaturaTalla * 0.4 + LARGO_EXTRA[corte]);
    const cuerpoCm = Math.round(medidas.h * 0.38);
    const holguraCm = prendaCm - cuerpoCm;
    zonas.push({ clave: 'largo', zona: 'Largo', cuerpoCm, prendaCm, holguraCm, estado: estadoLargo(holguraCm) });
  }

  return { talla, corte, tipo, zonas, veredicto: peorEstado(zonas) };
}

/** Peor estado entre los contornos de una lista de zonas (el largo no decide la talla). */
export function peorEstado(zonas: ZonaAnalisis[]): EstadoAjuste {
  return zonas
    .filter((z) => z.clave !== 'largo')
    .reduce<EstadoAjuste>((peor, z) => (GRAVEDAD[z.estado] > GRAVEDAD[peor] ? z.estado : peor), 'ideal');
}

/**
 * Qué tan segura es la talla para este cuerpo: baja cuando alguna zona queda fuera
 * de lo ideal (por ejemplo, pecho de una talla y cintura de otra).
 */
export function confianza(medidas: Medidas, talla: Talla, corte: Corte, tipo: TipoPrenda = 'superior'): number {
  let puntos = 96;
  for (const z of analizarAjuste(medidas, talla, corte, tipo).zonas) {
    const peso = z.clave === 'largo' ? 0.5 : 1;
    puntos -= GRAVEDAD[z.estado] === 2 ? 16 * peso : GRAVEDAD[z.estado] === 1 ? 7 * peso : 0;
  }
  return Math.max(35, Math.min(96, Math.round(puntos)));
}

export type ZonaAjuste = { zone: string; note: string; pct: string; color: string };

/** Barras de holgura por zona (pantalla de resultado). */
export function mapaAjuste(medidas: Medidas, talla: Talla, corte: Corte, tipo: TipoPrenda = 'superior'): ZonaAjuste[] {
  return analizarAjuste(medidas, talla, corte, tipo).zonas.map((z) => ({
    zone: z.zona,
    note: `${formatoHolgura(z.holguraCm)} · ${etiquetaZona(z).toLowerCase()}`,
    pct: `${Math.max(4, Math.min(100, Math.round((z.holguraCm / (z.clave === 'largo' ? 12 : 24)) * 100)))}%`,
    color: COLOR_ESTADO[z.estado],
  }));
}

/** Frase corta sobre cómo quedaría una talla vecina; '' si esa talla no existe. */
export function resumenTalla(medidas: Medidas, talla: Talla, corte: Corte, tipo: TipoPrenda = 'superior'): string {
  const a = analizarAjuste(medidas, talla, corte, tipo);
  const contornos = a.zonas.filter((z) => z.clave !== 'largo');
  const peor = contornos.reduce((p, z) => (GRAVEDAD[z.estado] > GRAVEDAD[p.estado] ? z : p), contornos[0]);
  if (peor.estado === 'ideal') return 'Ajuste ideal';
  return `${ETIQUETA_ESTADO[peor.estado]} en ${peor.zona.toLowerCase()}`;
}

export function porQueEstaTalla(medidas: Medidas, corte: Corte, talla: Talla, tipo: TipoPrenda = 'superior'): string {
  const a = analizarAjuste(medidas, talla, corte, tipo);
  const principal = a.zonas[tipo === 'inferior' ? 1 : 0];
  const resto = a.zonas.filter((z) => z !== principal && z.clave !== 'largo');
  const largo = a.zonas.find((z) => z.clave === 'largo');
  const i = TALLAS.indexOf(talla);

  const frases = [
    `Con ${principal.cuerpoCm} cm de ${principal.zona.toLowerCase()}, la ${talla} de corte ${corte.toLowerCase()} ` +
      `te deja ${formatoHolgura(principal.holguraCm)} (${ETIQUETA_ESTADO[principal.estado].toLowerCase()}).`,
  ];
  if (resto.length > 0) frases.push(resto.map((z) => `${z.zona} ${formatoHolgura(z.holguraCm)}`).join(' y ') + '.');
  if (largo) frases.push(`Con ${medidas.h} cm de estatura: ${ETIQUETA_LARGO[largo.estado].toLowerCase()}.`);
  if (i > 0) {
    const menor = TALLAS[i - 1];
    frases.push(`La ${menor}: ${resumenTalla(medidas, menor, corte, tipo).toLowerCase()}.`);
  }
  if (i < TALLAS.length - 1) {
    const mayor = TALLAS[i + 1];
    frases.push(`La ${mayor}: ${resumenTalla(medidas, mayor, corte, tipo).toLowerCase()}.`);
  }
  return frases.join(' ');
}

/** Formato de precio del proyecto: soles peruanos. */
export function money(n: number): string {
  return n.toLocaleString('es-PE', {
    style: 'currency',
    currency: 'PEN',
    minimumFractionDigits: 2,
  });
}
