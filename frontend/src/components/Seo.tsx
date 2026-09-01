import { useEffect } from 'react';

const SITIO = 'Kloset';
/** Dominio canónico: se define en `.env.production` (VITE_SITE_URL). */
const BASE_URL = (import.meta.env.VITE_SITE_URL ?? 'https://kloset.shop').replace(/\/$/, '');

type SeoProps = {
  /** Título de la pestaña; se le añade « · Kloset » salvo en la portada. */
  title: string;
  description: string;
  /** Ruta canónica de la vista, empezando por «/». */
  path?: string;
  /** `noindex` para vistas de sesión o de flujo de compra. */
  noindex?: boolean;
  image?: string;
};

function fijarMeta(clave: 'name' | 'property', valor: string, contenido: string) {
  let el = document.head.querySelector<HTMLMetaElement>(`meta[${clave}="${valor}"]`);
  if (!el) {
    el = document.createElement('meta');
    el.setAttribute(clave, valor);
    document.head.appendChild(el);
  }
  el.setAttribute('content', contenido);
}

function fijarCanonical(href: string) {
  let el = document.head.querySelector<HTMLLinkElement>('link[rel="canonical"]');
  if (!el) {
    el = document.createElement('link');
    el.setAttribute('rel', 'canonical');
    document.head.appendChild(el);
  }
  el.setAttribute('href', href);
}

/**
 * Sincroniza `<title>` y las meta de SEO/redes con la vista activa del SPA.
 * Actualiza las etiquetas que ya vienen en `index.html` en lugar de duplicarlas.
 */
export function Seo({ title, description, path = '/', noindex = false, image }: SeoProps) {
  useEffect(() => {
    const tituloCompleto = path === '/' || title === SITIO ? title : `${title} · ${SITIO}`;
    const url = `${BASE_URL}${path}`;
    const img = image ?? `${BASE_URL}/kloset-icon.png`;

    document.title = tituloCompleto;
    fijarMeta('name', 'description', description);
    fijarMeta('name', 'robots', noindex ? 'noindex, follow' : 'index, follow, max-image-preview:large');
    fijarCanonical(url);

    fijarMeta('property', 'og:title', tituloCompleto);
    fijarMeta('property', 'og:description', description);
    fijarMeta('property', 'og:url', url);
    fijarMeta('property', 'og:image', img);
    fijarMeta('name', 'twitter:title', tituloCompleto);
    fijarMeta('name', 'twitter:description', description);
    fijarMeta('name', 'twitter:image', img);
  }, [title, description, path, noindex, image]);

  return null;
}
