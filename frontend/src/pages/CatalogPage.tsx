import { useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { api, type ApiProducto } from '../services/api';
import { useKloset } from '../store/KlosetContext';
import { CORTES, money, tallaRecomendada, type Corte } from '../lib/fit';
import { Marco } from '../components/Marco';

export function CatalogPage() {
  const navigate = useNavigate();
  const { medidas, corte, setCorte } = useKloset();
  const [productos, setProductos] = useState<ApiProducto[]>([]);
  const [q, setQ] = useState('');
  const [cat, setCat] = useState('Todo');
  const [error, setError] = useState('');
  const [cargando, setCargando] = useState(true);

  useEffect(() => {
    api
      .productos()
      .then((res) => {
        if (res.status === 'success') setProductos(res.productos ?? []);
        else setError('No se pudo cargar el catálogo');
      })
      .catch(() => setError('API no disponible. Inicia Apache y MySQL en XAMPP.'))
      .finally(() => setCargando(false));
  }, []);

  const cats = useMemo(() => {
    const nombres = productos.map((p) => p.nombre_categoria).filter((c): c is string => !!c);
    return ['Todo', ...Array.from(new Set(nombres))];
  }, [productos]);

  const mostrados = useMemo(
    () =>
      productos.filter(
        (p) =>
          (cat === 'Todo' || p.nombre_categoria === cat) &&
          (!q ||
            `${p.nombre_producto} ${p.nombre_categoria ?? ''}`.toLowerCase().includes(q.toLowerCase())),
      ),
    [productos, cat, q],
  );

  const talla = tallaRecomendada(medidas, corte);

  return (
    <div className="kl-rise">
      <div className="flex flex-col items-start gap-[26px] border-b border-ink pb-[26px] pt-2 lg:flex-row lg:items-end">
        <div className="max-w-[660px]">
          <div className="mb-[18px] inline-block border-b-[3px] border-red pb-[6px] font-narrow text-xs uppercase tracking-[0.14em] text-ink">
            Compra con certeza, no con adivinanza
          </div>
          <h1 className="mb-[14px] font-display text-[44px] font-normal leading-none tracking-[-0.025em] md:text-[56px]">
            Ropa que ya sabe
            <br />
            <em className="italic">cómo te queda.</em>
          </h1>
          <p className="m-0 max-w-[47ch] text-[15.5px] leading-[1.6] text-body">
            Registra tus medidas una vez. Cada prenda se prueba sobre tu avatar antes de que pagues.
          </p>
        </div>
      </div>

      <div className="flex items-center gap-3 border-b border-rule py-[2px]">
        <span className="font-display text-[19px] text-soft">/</span>
        <input
          value={q}
          onChange={(e) => setQ(e.target.value)}
          placeholder="Buscar camiseta, malla, cortavientos…"
          className="flex-1 border-none bg-transparent py-4 text-base text-ink outline-none"
        />
      </div>

      <div className="no-scrollbar flex gap-[22px] overflow-x-auto pb-3 pt-[14px]">
        {cats.map((c) => (
          <button
            key={c}
            type="button"
            onClick={() => setCat(c)}
            className="relative min-h-10 shrink-0 cursor-pointer border-none bg-transparent px-0 pb-[10px] pt-2 font-narrow text-sm font-semibold uppercase tracking-[0.06em]"
            style={{ color: cat === c ? 'var(--ink)' : 'var(--soft)' }}
          >
            {c}
            <span
              className="absolute inset-x-0 bottom-[2px] h-[2px]"
              style={{ background: cat === c ? 'var(--red)' : 'transparent' }}
            />
          </button>
        ))}
      </div>

      <div className="mb-[26px] flex items-center gap-[14px] border-b border-t border-b-ink border-t-rule pb-[22px] pt-3">
        <span className="font-narrow text-xs uppercase tracking-[0.12em] text-soft">Corte</span>
        <div className="flex border border-ink">
          {CORTES.map((f, i) => (
            <button
              key={f}
              type="button"
              onClick={() => setCorte(f as Corte)}
              className="min-h-10 cursor-pointer border-none px-[15px] font-narrow text-[13.5px] font-semibold uppercase tracking-[0.06em] transition-colors"
              style={{
                background: corte === f ? 'var(--ink)' : 'transparent',
                color: corte === f ? 'var(--paper)' : 'var(--soft)',
                borderRight: i === CORTES.length - 1 ? 'none' : '1px solid var(--ink)',
              }}
            >
              {f}
            </button>
          ))}
        </div>
        <div className="flex-1" />
        <span className="font-display text-base text-soft">{mostrados.length} prendas</span>
      </div>

      {error && <div className="mb-6 border border-red p-4 text-red">{error}</div>}

      {!error && !cargando && mostrados.length === 0 && (
        <p className="py-10 text-center text-soft">
          {productos.length === 0
            ? 'Todavía no hay productos publicados. Crea el primero desde el panel administrativo.'
            : 'Ninguna prenda coincide con tu búsqueda.'}
        </p>
      )}

      <div className="grid grid-cols-2 gap-x-4 gap-y-9 md:grid-cols-3 md:gap-x-6 xl:grid-cols-4">
        {mostrados.map((p) => (
          <div
            key={p.id_producto}
            onClick={() => navigate(`/producto/${p.url_producto}`)}
            className="kl-rise cursor-pointer"
          >
            <Marco src={p.url_imagen} alt={p.nombre_producto} etiqueta="foto de producto" className="p-3">
              <div className="relative border-b-2 border-red bg-ink px-[10px] py-[6px] font-narrow text-[11.5px] uppercase tracking-[0.08em] text-paper">
                Tu talla · {talla}
              </div>
            </Marco>
            <div className="mt-[10px] flex items-start gap-3 border-t border-ink pt-3">
              <div className="flex-1">
                <div className="font-display text-lg font-medium leading-tight tracking-[-0.01em]">
                  {p.nombre_producto}
                </div>
                <div className="mt-1 text-[12.5px] text-soft">
                  {corte} · {p.nombre_categoria ?? 'sin categoría'}
                </div>
              </div>
              <div className="font-display text-[17px]">{money(Number(p.precio_producto))}</div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
