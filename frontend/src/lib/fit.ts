/* Lógica de talla y ajuste — portada de "Kloset.dc.html" */

export const TALLAS = ['XS', 'S', 'M', 'L', 'XL'] as const;
export const CORTES = ['Slim', 'Regular', 'Oversize'] as const;

export type Talla = (typeof TALLAS)[number];
export type Corte = (typeof CORTES)[number];

export type Medidas = {
  h: number;
  chest: number;
  waist: number;
  hip: number;
};

export const MEDIDAS_INICIALES: Medidas = { h: 174, chest: 98, waist: 84, hip: 99 };

export const RANGOS_MEDIDAS = [
  { key: 'h' as const, label: 'Estatura', hint: 'Sin zapatos, contra la pared', min: 140, max: 210 },
  { key: 'chest' as const, label: 'Pecho', hint: 'Por la parte más ancha, brazos abajo', min: 70, max: 140 },
  { key: 'waist' as const, label: 'Cintura', hint: 'A la altura del ombligo, sin apretar', min: 55, max: 130 },
  { key: 'hip' as const, label: 'Cadera', hint: 'Por la parte más ancha de la cadera', min: 70, max: 145 },
];

export function indiceTalla(medidas: Medidas, corte: Corte): number {
  const c = medidas.chest;
  let i = c < 88 ? 0 : c < 96 ? 1 : c < 104 ? 2 : c < 112 ? 3 : 4;
  if (corte === 'Slim' && c % 8 > 5) i = Math.min(4, i + 1);
  if (corte === 'Oversize' && c % 8 < 2) i = Math.max(0, i - 1);
  return i;
}

export function tallaRecomendada(medidas: Medidas, corte: Corte): Talla {
  return TALLAS[indiceTalla(medidas, corte)];
}

export function confianza(corte: Corte): number {
  return 94 - (corte === 'Oversize' ? 3 : 0);
}

export const COPY_CORTE: Record<Corte, string> = {
  Slim: 'Segunda piel. El tejido sigue el torso sin arrugas en el costado; pensado para correr y entrenar en sala.',
  Regular: 'Holgura de 4 cm en pecho y cintura. Cae recto y admite una capa base debajo sin tirar.',
  Oversize: 'Hombro caído y 12 cm extra de cuerpo. Se mueve contigo en lugar de acompañarte al cuerpo.',
};

export const VEREDICTO_CORTE: Record<Corte, string> = {
  Slim: 'ceñido en pecho, largo justo',
  Regular: 'cae limpio, hombro alineado',
  Oversize: 'amplio, hombro por debajo',
};

export const ANCHO_FIGURA: Record<Corte, string> = { Slim: '38%', Regular: '44%', Oversize: '52%' };
export const APRIETE_FIGURA: Record<Corte, number> = { Slim: 0.9, Regular: 1, Oversize: 1.12 };
const HOLGURA_PECHO: Record<Corte, number> = { Slim: 2, Regular: 5, Oversize: 11 };

export type ZonaAjuste = { zone: string; note: string; pct: string; color: string };

export function mapaAjuste(corte: Corte): ZonaAjuste[] {
  const base = HOLGURA_PECHO[corte];
  const zona = (zone: string, cm: number, note: string): ZonaAjuste => ({
    zone,
    note,
    pct: `${Math.min(100, Math.round((cm / 16) * 100))}%`,
    color: cm / 16 > 0.6 ? 'var(--soft)' : 'var(--red)',
  });

  return [
    zona('Pecho', base, `+${base} cm de holgura`),
    zona('Cintura', base + 2, `+${base + 2} cm`),
    zona('Cadera', base + 1, `+${base + 1} cm`),
    zona('Largo de cuerpo', 7, 'cae en la cadera'),
  ];
}

export function porQueEstaTalla(medidas: Medidas, corte: Corte, talla: Talla): string {
  return (
    `Tu pecho de ${medidas.chest} cm cae en el centro del rango ${talla} para el corte ${corte.toLowerCase()}, ` +
    `y con ${medidas.h} cm de estatura el largo termina justo en la cadera. ` +
    'Una talla menos tiraría al levantar los brazos.'
  );
}

/** Formato de precio del proyecto: soles peruanos. */
export function money(n: number): string {
  return n.toLocaleString('es-PE', {
    style: 'currency',
    currency: 'PEN',
    minimumFractionDigits: 2,
  });
}
