const API_BASE = import.meta.env.VITE_API_URL ?? '/api/v1';

export type ApiProducto = {
  id_producto: number;
  nombre_producto: string;
  url_producto: string;
  descripcion_producto: string | null;
  precio_producto: string;
  nombre_categoria: string | null;
  url_categoria: string | null;
  url_imagen: string | null;
};

export type ApiImagen = {
  id_imagen: number;
  url_imagen: string;
  orden_imagen: number;
};

export type ApiVariante = {
  id_variante: number;
  talla_variante: string;
  corte_variante: string;
  sku_variante: string;
  stock_variante: number;
};

export type ApiUsuario = {
  id: number;
  nombre: string;
  correo: string;
  rol: string;
};

export type ApiDetalle = {
  status: string;
  message?: string;
  producto: ApiProducto & { estado_producto: string };
  imagenes: ApiImagen[];
  variantes: ApiVariante[];
};

const TOKEN_KEY = 'kloset_token';

export function getToken(): string | null {
  try {
    return localStorage.getItem(TOKEN_KEY);
  } catch {
    return null;
  }
}

export function setToken(token: string | null) {
  try {
    if (token) localStorage.setItem(TOKEN_KEY, token);
    else localStorage.removeItem(TOKEN_KEY);
  } catch {
    /* almacenamiento no disponible */
  }
}

async function request<T>(path: string, options: RequestInit = {}): Promise<T> {
  const token = getToken();
  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    ...(options.headers as Record<string, string>),
  };
  if (token) headers.Authorization = `Bearer ${token}`;

  const res = await fetch(`${API_BASE}${path}`, { ...options, headers });
  return (await res.json()) as T;
}

export const api = {
  health: () => request<{ status: string }>('/health'),

  productos: () => request<{ status: string; productos: ApiProducto[] }>('/productos'),

  producto: (url: string) => request<ApiDetalle>(`/producto?url=${encodeURIComponent(url)}`),

  login: (correo: string, password: string) =>
    request<{ status: string; token?: string; usuario?: ApiUsuario; message?: string }>('/auth/login', {
      method: 'POST',
      body: JSON.stringify({ correo, password }),
    }),

  register: (nombre: string, correo: string, password: string) =>
    request<{ status: string; token?: string; usuario?: ApiUsuario; message?: string }>('/auth/register', {
      method: 'POST',
      body: JSON.stringify({ nombre, correo, password }),
    }),

  me: () => request<{ status: string; usuario?: ApiUsuario }>('/auth/me'),
};
