import { useEffect, useMemo, useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { api, type ApiProducto } from '../services/api';
import { useKloset } from '../store/KlosetContext';
import { CORTES, money, tallaRecomendada, type Corte } from '../lib/fit';
import { Marco } from '../components/Marco';
import { Seo } from '../components/Seo';

const ASSETS = (window as Window & { __KLOSET_ASSETS__?: string }).__KLOSET_ASSETS__ ?? '';
const categorias = [
  { nombre: 'Camisetas', imagen: ASSETS + '/imagenes/camisetas.webp' },
  { nombre: 'Mallas', imagen: ASSETS + '/imagenes/mallas.webp' },
  { nombre: 'Shorts', imagen: ASSETS + '/imagenes/shorts.webp' },
  { nombre: 'Capas', imagen: ASSETS + '/imagenes/capas.webp' },
  { nombre: 'Tops', imagen: ASSETS + '/imagenes/tops.webp' },
  { nombre: 'Pantalones', imagen: ASSETS + '/imagenes/pantalones.webp' },
];

function Icono({ nombre }: { nombre: 'escudo' | 'cambio' | 'ajuste' | 'soporte' | 'reloj' | 'envio' }) {
  const trazos = {
    escudo: <><path d="M12 2 3 6v5c0 6 4 9.5 9 11 5-1.5 9-5 9-11V6l-9-4Z" /><path d="m8 12 2.5 2.5L16 9" /></>,
    cambio: <><path d="M20 7v5h-5M4 17v-5h5" /><path d="M5.7 9A7 7 0 0 1 18 7l2 5M4 12l2 5a7 7 0 0 0 12.3-2" /></>,
    ajuste: <><path d="m3 8 5-4 4 2 4-2 5 4-3 4-2-2v11H8V10l-2 2-3-4Z" /><path d="M10 6v3h4V6" /></>,
    soporte: <><path d="M4 14v-3a8 8 0 0 1 16 0v3M4 14H2v5h4v-5H4Zm16 0h2v5h-4v-5h2ZM18 20c-1 2-3 2-6 2" /></>,
    reloj: <><circle cx="12" cy="12" r="9" /><path d="M12 6v6l4 2" /></>,
    envio: <><path d="M2 6h12v12H2zM14 10h4l4 4v4h-8z" /><circle cx="6" cy="19" r="2" /><circle cx="18" cy="19" r="2" /></>,
  };
  return <svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">{trazos[nombre]}</svg>;
}

function MapaCobertura() {
  return <div className="home-map" role="img" aria-label="Mapa referencial de cobertura en Lima Metropolitana y Callao">
    <svg viewBox="0 0 420 280" aria-hidden="true">
      <path className="home-map-sea" d="M0 0h150c-12 34-9 47-23 71-14 25-9 45-18 66-12 27-27 43-38 73-8 21-19 46-33 70H0Z" />
      <path className="home-map-land" d="M149 0h271v280H39c14-24 25-49 33-70 11-30 26-46 38-73 9-21 4-41 18-66 14-24 11-37 21-71Z" />
      <path className="home-map-area" d="m161 18 39 6 13 19 38-3 15 24-12 25 30 27-15 24 27 32-9 24 13 28-29 27-32-10-22 20-32-19-32 3-27-24-21-4 17-32-10-26 19-29-8-24 21-23Z" />
      <path className="home-map-callao" d="m129 95 25-13 19 9 4 26-17 26-29-4-16-15Z" />
      <path className="home-map-lines" d="m155 82 65 149M177 118l92 78M134 139l153 33M190 35l48 188M112 186l170-70" />
      <path className="home-map-coast" d="M149 0c-12 34-9 47-23 71-14 25-9 45-18 66-12 27-27 43-38 73-8 21-19 46-33 70" />
      <circle className="home-map-pin" cx="145" cy="112" r="7" /><circle className="home-map-pin" cx="224" cy="158" r="7" />
      <text x="105" y="99">Callao</text><text x="238" y="160">Lima</text><text x="238" y="176">Metropolitana</text>
    </svg>
    <span className="home-map-note">Mapa referencial · Lima y Callao</span>
  </div>;
}

export function CatalogPage({ irAlCatalogo = false }: { irAlCatalogo?: boolean }) {
  const { medidas, corte, tienePerfil, setCorte } = useKloset();
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const categoriaUrl = searchParams.get('categoria') ?? 'Todo';
  const [productos, setProductos] = useState<ApiProducto[]>([]);
  const [q, setQ] = useState('');
  const [cat, setCat] = useState(categoriaUrl);
  const [error, setError] = useState('');
  const [cargando, setCargando] = useState(true);

  useEffect(() => {
    api.productos().then((res) => {
      if (res.status === 'success') setProductos(res.productos ?? []);
      else setError('No se pudo cargar el catálogo.');
    }).catch(() => setError('No pudimos cargar el catálogo. Vuelve a intentarlo en un momento.'))
      .finally(() => setCargando(false));
  }, []);

  const mostrados = useMemo(() => productos.filter((p) =>
    (cat === 'Todo' || p.nombre_categoria === cat) &&
    (!q || (p.nombre_producto + ' ' + (p.nombre_categoria ?? '')).toLowerCase().includes(q.toLowerCase()))
  ), [productos, cat, q]);
  useEffect(() => {
    if (irAlCatalogo) document.getElementById('catalogo')?.scrollIntoView({ behavior: 'smooth' });
  }, [irAlCatalogo]);

  useEffect(() => {
    const categoriasValidas = ['Todo', ...categorias.map((item) => item.nombre)];
    setCat(categoriasValidas.includes(categoriaUrl) ? categoriaUrl : 'Todo');
    setQ('');
  }, [categoriaUrl]);

  const talla = tallaRecomendada(medidas, corte);

  function elegirCategoria(nombre: string) {
    navigate('/catalogo?categoria=' + encodeURIComponent(nombre));
  }

  const listadoCatalogo = (
    <section id="catalogo" className="home-products" aria-labelledby="catalogo-title">
      <div className="home-section-heading">
        <div><p className="home-eyebrow">PRENDAS PARA MOVERTE</p><h2 id="catalogo-title">El catálogo Kloset</h2><p>Elige tu corte y descubre las prendas hechas para tu ritmo.</p></div>
        <Link to="/medidas" className="home-text-link">Descubre tu talla <span aria-hidden="true">→</span></Link>
      </div>
      <div className="home-catalog-tools">
        <label className="home-search"><span aria-hidden="true">⌕</span><span className="sr-only">Buscar productos</span><input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Buscar camiseta, malla, cortavientos…" /></label>
        <div className="home-filter-row" aria-label="Filtrar por categoría">
          {['Todo', ...categorias.map((c) => c.nombre)].map((nombre) => <button key={nombre} type="button" className={cat === nombre ? 'is-active' : ''} onClick={() => setCat(nombre)}>{nombre}</button>)}
        </div>
        <div className="home-fit-row"><span>CORTE</span><div className="home-cut-options">
          {CORTES.map((opcion) => <button key={opcion} type="button" className={corte === opcion ? 'is-active' : ''} onClick={() => setCorte(opcion as Corte)}>{opcion}</button>)}
        </div><small>{mostrados.length} prendas</small></div>
      </div>
      {error && <div className="home-message" role="alert">{error}</div>}
      {!error && !cargando && mostrados.length === 0 && <p className="home-message">{productos.length === 0 ? 'Todavía no hay productos publicados.' : 'Ninguna prenda coincide con tu búsqueda.'}</p>}
      <div className="home-product-grid">
        {mostrados.map((p, i) => <Link key={p.id_producto} to={'/producto/' + p.url_producto} className="home-product">
          <Marco src={p.url_imagen} alt={p.nombre_producto} etiqueta="foto de producto" className="p-3" prioridad={i < 4}>{tienePerfil && <div className="home-size-tag">TU TALLA · {talla}</div>}</Marco>
          <div className="home-product-details"><div><strong>{p.nombre_producto}</strong><small>{corte} · {p.nombre_categoria ?? 'Sin categoría'}</small></div><span>{money(Number(p.precio_producto))}</span></div>
        </Link>)}
      </div>
    </section>
  );

  if (irAlCatalogo) {
    return <div className="kl-rise home">
      <Seo title="Catálogo deportivo" description="Explora el catálogo de ropa deportiva Kloset y encuentra prendas para tu estilo y tu talla." path="/catalogo" />
      {listadoCatalogo}
    </div>;
  }

  return <div className="kl-rise home">
    <Seo title="Kloset | Ropa que ya sabe cómo te queda" description="Ropa deportiva que combina comodidad, estilo y tu talla ideal. Explora camisetas, mallas, shorts, capas, tops y pantalones en Kloset." path={irAlCatalogo ? '/catalogo' : '/'} />

    <section className="home-hero" aria-labelledby="home-title">
      <img src={ASSETS + '/imagenes/hero.webp'} alt="Modelos con ropa deportiva negra de Kloset" fetchPriority="high" />
      <div className="home-hero-shade" />
      <div className="home-hero-copy">
        <p className="home-eyebrow">TU ESTILO, SIN LÍMITES</p>
        <h1 id="home-title">Ropa que ya sabe <em>cómo te queda.</em></h1>
        <p className="home-lede">Prendas deportivas que combinan comodidad, estilo y rendimiento. Encuentra tu talla ideal con KLOSET.</p>
        <div className="home-actions">
          <Link className="home-button home-button-red" to="/catalogo">EXPLORAR CATÁLOGO <span aria-hidden="true">→</span></Link>
          <Link className="home-button home-button-outline" to="/nosotros">CONOCE MÁS</Link>
        </div>
      </div>
      <p className="home-hero-note">Tu estilo.<br />Tu talla.<br />Sin dudas.</p>
    </section>

    <section className="home-benefits" aria-label="Beneficios de comprar en Kloset">
      <div><Icono nombre="escudo" /><p><strong>Compra segura</strong><span>Proceso claro de principio a fin</span></p></div>
      <div><Icono nombre="cambio" /><p><strong>Devoluciones fáciles</strong><span>Hasta 30 días</span></p></div>
      <div><Icono nombre="ajuste" /><p><strong>Ajuste personalizado</strong><span>Guía de tallas a tu medida</span></p></div>
      <div><Icono nombre="soporte" /><p><strong>Siempre contigo</strong><span>Te ayudamos a elegir</span></p></div>
    </section>

    <section className="home-categories" aria-labelledby="categorias-title">
      <div className="home-section-heading">
        <div><p className="home-eyebrow">NUESTRO CATÁLOGO</p><h2 id="categorias-title">Encuentra tu próximo look</h2><p>Explora prendas que se adaptan a tu estilo y a tus metas.</p></div>
        <Link to="/catalogo" className="home-text-link">Ver catálogo completo <span aria-hidden="true">→</span></Link>
      </div>
      <div className="home-category-grid">
        {categorias.map((item) => <button key={item.nombre} type="button" className="home-category" onClick={() => elegirCategoria(item.nombre)}>
          <span className="home-category-photo"><img src={item.imagen} alt="" loading="lazy" /></span>
          <strong>{item.nombre}</strong><small>Ver más <span aria-hidden="true">→</span></small>
        </button>)}
      </div>
    </section>

    <section className="home-service" aria-label="Cobertura y contacto">
      <div className="home-coverage">
        <div className="home-coverage-copy">
          <p className="home-eyebrow">COBERTURA</p><h2>Lima y Callao,<br /><em>más cerca de ti.</em></h2>
          <p>Repartimos en Lima Metropolitana y la Provincia Constitucional del Callao. Tu pedido llega en 24 a 72 horas hábiles sin coste de envío.</p>
          <div className="home-coverage-facts">
            <div><span>◎</span><strong>51 distritos</strong><small>en Lima y Callao</small></div>
            <div><Icono nombre="reloj" /><strong>24 – 72 h</strong><small>según tu distrito</small></div>
            <div><Icono nombre="envio" /><strong>Envío gratis</strong><small>en zona de cobertura</small></div>
          </div>
          <Link to="/cobertura" className="home-button home-button-outline">VER COBERTURA COMPLETA <span aria-hidden="true">→</span></Link>
        </div>
        <MapaCobertura />
      </div>
      <div className="home-contact">
        <p className="home-eyebrow">CONTÁCTANOS</p><h2>¿Tienes alguna duda?</h2>
        <p>Estamos aquí para ayudarte. Escríbenos y te responderemos lo antes posible.</p>
        <Link to="/contacto" className="home-button home-button-dark">IR A CONTACTO <span aria-hidden="true">→</span></Link>
        <div className="home-contact-note">Tu experiencia también nos inspira.</div>
      </div>
    </section>

    <section className="home-story" aria-labelledby="story-title">
      <img src={ASSETS + '/imagenes/historia.webp'} alt="Modelo vistiendo una sudadera deportiva negra" loading="lazy" />
      <div className="home-story-copy"><p className="home-eyebrow">SOBRE KLOSET</p><h2 id="story-title">Más que una tienda,<br /><em>una mejor forma de comprar.</em></h2>
        <p>Creemos que la moda deportiva debe adaptarse a ti, no tú a la ropa. Unimos tecnología, estilo y tus medidas para recomendarte la talla ideal.</p>
        <Link to="/nosotros" className="home-button home-button-outline">CONOCE NUESTRA HISTORIA <span aria-hidden="true">→</span></Link>
      </div>
      <p className="home-story-note">Moda que<br />te acompaña<br />en cada paso.</p>
    </section>

    <section className="home-promo" aria-labelledby="promo-title">
      <img src={ASSETS + '/imagenes/banner.webp'} alt="Zapatillas deportivas blancas con mallas negras" loading="lazy" />
      <div className="home-promo-copy"><h2 id="promo-title">Vístete con confianza<br />todos los días.</h2><p>Ropa deportiva que se adapta a ti, para que solo te preocupes por llegar más lejos.</p>
        <Link to="/catalogo" className="home-button home-button-red">IR AL CATÁLOGO <span aria-hidden="true">→</span></Link>
      </div>
      <p className="home-promo-mark">TU CUERPO.<br />TU RITMO.<br />TU KLOSET.</p>
    </section>
  </div>;
}
