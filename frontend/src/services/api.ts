const APP_ROOT = (window as Window & { __KLOSET_ROOT__?: string }).__KLOSET_ROOT__ ?? '';
const API_BASE = APP_ROOT ? APP_ROOT + '/api/v1' : (import.meta.env.VITE_API_URL ?? '/api/v1');

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
  url_producto?: string;
  url_imagen?: string | null;
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
  subtotal_pedido?: string;
  direccion_envio_cliente?: string;
  ciudad_envio_cliente?: string;
  referencia_envio_cliente?: string | null;
  historial?: { estado: string; comentario: string | null; fecha: string }[];
  transportista_pedido?: string | null;
  seguimiento_pedido?: string | null;
  entrega_estimada_pedido?: string | null;
  marca_pago?: string;
  marca_tarjeta?: string;
  ultimos_digitos_pago?: string | null;
  ultimos_digitos?: string;
  items: ApiPedidoItem[];
};

export type ApiDireccion = {
  id: number;
  nombre: string;
  direccion: string;
  ciudad: string;
  referencia: string | null;
  telefono: string | null;
  principal: number;
};

export type ApiDatosCuenta = {
  nombre: string;
  correo: string;
  telefono: string | null;
  avisos_pedidos: number;
  avisos_novedades: number;
  fecha_creacion: string;
};

export type ApiFavorito = ApiProducto & { estado_producto: string; stock: number };

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

  contacto: (datos: { tema: string; nombre: string; correo: string; telefono: string; mensaje: string; sitio: string }) =>
    request<{ status: string; message?: string }>('/contacto', {
      method: 'POST',
      body: JSON.stringify(datos),
    }),

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
    eliminar: () => request<{ status: string; message?: string }>('/medidas/eliminar', { method: 'POST' }),
  },

  carrito: {
    guardados: () => request<{ status: string; items: ApiCarritoItem[] }>('/carrito/guardados'),
    guardar: (id_carrito_item: number) => request<{ status: string; message?: string; items?: ApiCarritoItem[] }>('/carrito/guardar', { method: 'POST', body: JSON.stringify({ id_carrito_item }) }),
    restaurar: (id_variante: number) => request<{ status: string; message?: string; items?: ApiCarritoItem[] }>('/carrito/restaurar', { method: 'POST', body: JSON.stringify({ id_variante }) }),
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
    cantidad: (id_carrito_item: number, cantidad: number) =>
      request<{ status: string; message?: string; items?: ApiCarritoItem[] }>('/carrito/cantidad', {
        method: 'POST', body: JSON.stringify({ id_carrito_item, cantidad }),
      }),
  },

  pedidos: {
    cancelar: (id: number) => request<{ status: string; message?: string }>('/pedidos/cancelar', { method: 'POST', body: JSON.stringify({ id }) }),
    listar: () => request<{ status: string; pedidos: ApiPedido[] }>('/pedidos'),
    detalle: (id: number) => request<{ status: string; message?: string; pedido?: ApiPedido }>(`/pedido?id=${id}`),
    crear: (datos: { id_direccion?: number; direccion?: string; ciudad?: string; referencia?: string; marca_tarjeta?: string; ultimos_digitos?: string }) =>
      request<{ status: string; message?: string; pedido?: ApiPedido }>('/pedidos', {
        method: 'POST',
        body: JSON.stringify(datos),
      }),
  },

  direcciones: {
    listar: () => request<{ status: string; direcciones: ApiDireccion[] }>('/direcciones'),
    guardar: (datos: { id?: number; nombre: string; direccion: string; ciudad: string; referencia?: string; telefono?: string; principal?: boolean }) =>
      request<{ status: string; message?: string; id?: number; direcciones?: ApiDireccion[] }>('/direcciones', { method: 'POST', body: JSON.stringify(datos) }),
    eliminar: (id: number) => request<{ status: string; message?: string; direcciones?: ApiDireccion[] }>('/direcciones/eliminar', { method: 'POST', body: JSON.stringify({ id }) }),
    principal: (id: number) => request<{ status: string; message?: string; direcciones?: ApiDireccion[] }>('/direcciones/principal', { method: 'POST', body: JSON.stringify({ id }) }),
  },
  favoritos: {
    listar: () => request<{ status: string; productos: ApiFavorito[] }>('/favoritos'),
    alternar: (id_producto: number) => request<{ status: string; message?: string; favorito?: boolean }>('/favoritos/alternar', { method: 'POST', body: JSON.stringify({ id_producto }) }),
  },
  cuenta: {
    datos: () => request<{ status: string; datos: ApiDatosCuenta | null }>('/cuenta/datos'),
    guardar: (datos: { nombre: string; telefono: string; avisos_pedidos: boolean; avisos_novedades: boolean }) => request<{ status: string; message?: string; datos?: ApiDatosCuenta }>('/cuenta/datos', { method: 'POST', body: JSON.stringify(datos) }),
    clave: (actual: string, nueva: string) => request<{ status: string; message?: string }>('/cuenta/clave', { method: 'POST', body: JSON.stringify({ actual, nueva }) }),
    avisos: () => request<{ status: string; avisos: { id_notificacion: number; id_pedido: number | null; mensaje_notificacion: string; fecha_creacion: string }[] }>('/cuenta/avisos'),
  },
};
