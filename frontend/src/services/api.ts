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

export type ApiPerfilCorporal = {
  estatura_perfil_corporal: string;
  pecho_perfil_corporal: string;
  cintura_perfil_corporal: string;
  cadera_perfil_corporal: string;
  talla_recomendada_perfil_corporal: string;
};

export type ApiCarritoItem = {
  id_carrito_item: number;
  id_variante: number;
  cantidad_carrito_item: number;
  talla_variante: string;
  corte_variante: string;
  stock_variante: number;
  id_producto: number;
  nombre_producto: string;
  url_producto: string;
  precio_producto: string;
  url_imagen: string | null;
};

export type ApiPedidoItem = {
  cantidad_pedido_item?: number;
  cantidad?: number;
  precio_unitario_pedido_item?: string;
  precio?: number;
  talla_variante?: string;
  talla?: string;
  corte_variante?: string;
  corte?: string;
  nombre_producto?: string;
  id_producto?: number;
};

export type ApiPedido = {
  id_pedido: number;
  ref: string;
  estado?: string;
  estado_pedido?: string;
  total?: number;
  total_pedido?: string;
  fecha?: string;
  fecha_creacion?: string;
  marca_pago?: string;
  marca_tarjeta?: string;
  ultimos_digitos_pago?: string | null;
  ultimos_digitos?: string;
  items: ApiPedidoItem[];
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

  medidas: {
    obtener: () => request<{ status: string; perfil: ApiPerfilCorporal | null }>('/medidas'),
    guardar: (datos: { estatura: number; pecho: number; cintura: number; cadera: number; corte: string }) =>
      request<{ status: string; message?: string; perfil?: ApiPerfilCorporal }>('/medidas', {
        method: 'POST',
        body: JSON.stringify(datos),
      }),
  },

  carrito: {
    obtener: () => request<{ status: string; items: ApiCarritoItem[] }>('/carrito'),
    agregar: (datos: { id_producto: number; talla: string; corte: string; cantidad?: number }) =>
      request<{ status: string; message?: string; items?: ApiCarritoItem[] }>('/carrito/agregar', {
        method: 'POST',
        body: JSON.stringify(datos),
      }),
    quitar: (id_carrito_item: number) =>
      request<{ status: string; message?: string; items?: ApiCarritoItem[] }>('/carrito/eliminar', {
        method: 'POST',
        body: JSON.stringify({ id_carrito_item }),
      }),
  },

  pedidos: {
    listar: () => request<{ status: string; pedidos: ApiPedido[] }>('/pedidos'),
    crear: (datos: { direccion: string; ciudad: string; referencia?: string; marca_tarjeta?: string; ultimos_digitos?: string }) =>
      request<{ status: string; message?: string; pedido?: ApiPedido }>('/pedidos', {
        method: 'POST',
        body: JSON.stringify(datos),
      }),
  },
};
