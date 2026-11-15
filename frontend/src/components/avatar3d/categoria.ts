import type { TipoPrenda } from '../../lib/fit';

export type CategoriaPrenda3D = 'camisetas' | 'capas' | 'tops' | 'shorts' | 'mallas' | 'pantalones';

export function detectarCategoria3D(categoriaRaw: string | null, nombreProducto: string): CategoriaPrenda3D {
  const texto = `${categoriaRaw ?? ''} ${nombreProducto}`.toLowerCase();
  if (texto.includes('malla') || texto.includes('legging')) return 'mallas';
  if (texto.includes('short')) return 'shorts';
  if (texto.includes('pantal') || texto.includes('jog')) return 'pantalones';
  if (texto.includes('top') || texto.includes('bra')) return 'tops';
  if (
    texto.includes('capa') ||
    texto.includes('sudadera') ||
    texto.includes('cortaviento') ||
    texto.includes('casaca') ||
    texto.includes('hoodie')
  ) {
    return 'capas';
  }
  return 'camisetas';
}

export function tipoDeCategoria(categoria: CategoriaPrenda3D): TipoPrenda {
  if (categoria === 'tops') return 'top';
  return categoria === 'shorts' || categoria === 'mallas' || categoria === 'pantalones' ? 'inferior' : 'superior';
}

const COLORES: Record<string, string> = {
  negro: '#1d1e21',
  carbón: '#2a2c30',
  carbon: '#2a2c30',
  grafito: '#383b40',
  gris: '#74787e',
  plata: '#a3a8af',
  blanco: '#ecebe6',
  hueso: '#dedad0',
  crudo: '#dfd9cc',
  arena: '#c2b59b',
  beige: '#b8a98e',
  oliva: '#4f5a45',
  salvia: '#6e7f71',
  verde: '#375746',
  marino: '#22324a',
  petróleo: '#23424d',
  petroleo: '#23424d',
  celeste: '#7fa6c9',
  azul: '#2d4a70',
  terracota: '#9e4736',
  vino: '#5c242b',
  burdeos: '#5c242b',
  rojo: '#b3261f',
  cobre: '#8d5138',
  rosa: '#d48a99',
  morado: '#56406e',
  lila: '#9a87b8',
  amarillo: '#d8b23a',
  naranja: '#cf6a2a',
};

const COLOR_POR_DEFECTO: Record<CategoriaPrenda3D, string> = {
  camisetas: '#2b2e33',
  capas: '#3d453b',
  tops: '#2e2932',
  shorts: '#242c35',
  mallas: '#232429',
  pantalones: '#383633',
};

export function resolverColorPrendaHex(colorNombre: string, categoria: CategoriaPrenda3D): string {
  const limpio = colorNombre.trim().toLowerCase();
  for (const [clave, hex] of Object.entries(COLORES)) {
    if (limpio.includes(clave)) return hex;
  }
  return COLOR_POR_DEFECTO[categoria];
}
