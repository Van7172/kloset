const APP_ROOT = (window as Window & { __KLOSET_ROOT__?: string }).__KLOSET_ROOT__ ?? '';
const API_BASE = APP_ROOT ? APP_ROOT + '/api/v1' : (import.meta.env.VITE_API_URL ?? '/api/v1');

export type ApiProducto = {
  id_producto: number;
  codigo_producto?: string;
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
  id_color: number;
  url_imagen: string;
  orden_imagen: number;
  principal_imagen: number;
};

export type ApiColor = { id_color: number; id_producto: number; nombre_color: string };

export type ApiVariante = {
  id_variante: number;
  id_color: number;
  nombre_color: string;
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
  nombre_color: string;
  sku_variante: string;
  stock_variante: number;
  id_producto: number;
  nombre_producto: string;
  url_producto: string;
  precio_producto: string;
  url_imagen: string | null;
};

export type ApiPedidoItem = {
  id_pedido_item?: number;
  id_variante?: number;
  nombre_color?: string;
  sku_variante?: string;
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
  entrega_estimada_pedido?: string | null;
  codigo_entrega?: string | null;
  codigo_anulacion?: string | null;
  marca_pago?: string;
  marca_tarjeta?: string;
  ultimos_digitos_pago?: string | null;
  ultimos_digitos?: string;
  items: ApiPedidoItem[];
};

export type ApiSolicitudPostventa = {
  id_solicitud_postventa: number;
  id_pedido: number;
  codigo_pedido?: string;
  tipo_solicitud: 'reembolso' | 'cambio';
  estado_solicitud: string;
  motivo_solicitud: string;
  descripcion_solicitud?: string;
  importe_aprobado?: string | number | null;
  motivo_rechazo?: string | null;
  codigo_confirmacion?: string | null;
  fecha_creacion: string;
  fecha_actualizacion?: string;
  cliente?: string;
  correo?: string;
  metodo_pago?: string | null;
  marca_pago?: string | null;
  items?: ApiItemPostventa[];
  cambios?: ApiCambioPostventa[];
  historial?: ApiHistorialPostventa[];
  evidencias?: ApiEvidenciaPostventa[];
};

export type ApiCambioPostventa = {
  id_cambio_postventa: number;
  id_item_postventa: number;
  estado_cambio: string;
  codigo_confirmacion?: string | null;
  fecha_preparacion?: string | null;
  fecha_reparto?: string | null;
  fecha_completado?: string | null;
};

export type ApiItemPostventa = {
  id_item_postventa: number;
  id_pedido_item: number;
  id_variante: number;
  cantidad: number;
  importe_item: string | number;
  nombre_producto_compra: string;
  nombre_color_compra: string;
  talla_compra: string;
  corte_compra: string;
  sku_compra: string;
};

export type ApiHistorialPostventa = {
  estado_anterior?: string | null;
  estado_nuevo: string;
  comentario?: string | null;
  fecha_creacion: string;
  autor?: string;
};

export type ApiEvidenciaPostventa = {
  ruta_evidencia: string;
  tipo_mime_evidencia: string;
  fecha_creacion: string;
};

export type ApiDireccion = {
  id: number;
  nombre: string;
  departamento: string;
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
  colores: ApiColor[];
};

const TOKEN_KEY = 'kloset_token';
const READ_TIMEOUT_MS = 30_000;
type RequestOptions = Pick<RequestInit, 'signal'>;
const pendingGets = new Map<string, Promise<unknown>>();

export class ApiRequestError extends Error {
  constructor(message: string, readonly status?: number) {
    super(message);
    this.name = 'ApiRequestError';
  }
}

export function isAbortError(error: unknown): boolean {
  return error instanceof DOMException
    ? error.name === 'AbortError' || error.name === 'TimeoutError'
    : error instanceof Error && (error.name === 'AbortError' || error.name === 'TimeoutError');
}

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
  const method = (options.method ?? 'GET').toUpperCase();
  const requestUrl = `${API_BASE}${path}`;
  const deduplicationKey = JSON.stringify([requestUrl, token ?? 'anonymous']);
  const deduplicate = method === 'GET' && !options.signal;

  if (deduplicate) {
    const pending = pendingGets.get(deduplicationKey);
    if (pending) return pending as Promise<T>;
  }

  const execute = async (): Promise<T> => {
    const headers = new Headers(options.headers);
    if (!headers.has('Content-Type')) headers.set('Content-Type', 'application/json');
    if (token) headers.set('Authorization', `Bearer ${token}`);

    const controller = new AbortController();
    const externalSignal = options.signal;
    let timedOut = false;
    const abortFromExternalSignal = () => controller.abort(externalSignal?.reason);
    if (externalSignal?.aborted) abortFromExternalSignal();
    else externalSignal?.addEventListener('abort', abortFromExternalSignal, { once: true });

    const timeout = method === 'GET'
      ? window.setTimeout(() => {
          timedOut = true;
          controller.abort();
        }, READ_TIMEOUT_MS)
      : undefined;

    try {
      const response = await fetch(requestUrl, {
        ...options,
        method,
        headers,
        signal: controller.signal,
      });

      if (response.status === 204) return undefined as T;

      let payload: unknown;
      try {
        payload = await response.json();
      } catch {
        if (!response.ok) {
          throw new ApiRequestError('No pudimos completar la solicitud.', response.status);
        }
        throw new ApiRequestError('La API devolvió una respuesta inválida.', response.status);
      }

      if (!response.ok) {
        if (payload && typeof payload === 'object' && 'status' in payload && 'message' in payload) {
          return payload as T;
        }
        throw new ApiRequestError('No pudimos completar la solicitud.', response.status);
      }

      return payload as T;
    } catch (error) {
      if (timedOut) throw new ApiRequestError('La solicitud tardó demasiado. Inténtalo nuevamente.');
      throw error;
    } finally {
      if (timeout !== undefined) window.clearTimeout(timeout);
      externalSignal?.removeEventListener('abort', abortFromExternalSignal);
    }
  };

  const promise = execute();
  if (deduplicate) {
    pendingGets.set(deduplicationKey, promise);
    void promise.finally(() => {
      if (pendingGets.get(deduplicationKey) === promise) pendingGets.delete(deduplicationKey);
    }).catch(() => undefined);
  }
  return promise;
}

export const api = {
  health: (options?: RequestOptions) => request<{ status: string }>('/health', options),
  configuracion: (options?: RequestOptions) => request<{ status: string; tolerancia_cm: number }>('/config', options),

  contacto: (datos: { tema: string; nombre: string; correo: string; telefono: string; mensaje: string; sitio: string }) =>
    request<{ status: string; message?: string }>('/contacto', {
      method: 'POST',
      body: JSON.stringify(datos),
    }),

  productos: (options?: RequestOptions) => request<{ status: string; productos: ApiProducto[] }>('/productos', options),

  producto: (url: string, options?: RequestOptions) => request<ApiDetalle>(`/producto?url=${encodeURIComponent(url)}`, options),

  login: (correo: string, password: string) =>
    request<{ status: string; token?: string; usuario?: ApiUsuario; message?: string }>('/auth/login', {
      method: 'POST',
      body: JSON.stringify({ correo, password }),
    }),

  register: (nombre: string, correo: string, password: string, confirmar_password: string) =>
    request<{ status: string; message?: string; reenviar_en?: number }>('/auth/register', {
      method: 'POST',
      body: JSON.stringify({ nombre, correo, password, confirmar_password }),
    }),

  registro: {
    reenviar: (correo: string) =>
      request<{ status: string; message?: string; reenviar_en?: number }>('/auth/registro-reenviar', {
        method: 'POST',
        body: JSON.stringify({ correo }),
      }),
    verificar: (correo: string, codigo: string) =>
      request<{ status: string; message?: string }>('/auth/registro-verificar', {
        method: 'POST',
        body: JSON.stringify({ correo, codigo }),
      }),
  },

  recuperacion: {
    solicitar: (correo: string) =>
      request<{ status: string; message?: string; reenviar_en?: number }>('/auth/recuperacion', {
        method: 'POST',
        body: JSON.stringify({ correo }),
      }),
    restablecer: (correo: string, codigo: string, password: string, confirmar_password: string) =>
      request<{ status: string; message?: string }>('/auth/restablecer', {
        method: 'POST',
        body: JSON.stringify({ correo, codigo, password, confirmar_password }),
      }),
  },

  me: (options?: RequestOptions) => request<{ status: string; usuario?: ApiUsuario }>('/auth/me', options),

  medidas: {
    obtener: (options?: RequestOptions) => request<{ status: string; perfil: ApiPerfilCorporal | null }>('/medidas', options),
    guardar: (datos: { estatura: number; pecho: number; cintura: number; cadera: number; corte: string }) =>
      request<{ status: string; message?: string; perfil?: ApiPerfilCorporal }>('/medidas', {
        method: 'POST',
        body: JSON.stringify(datos),
      }),
    eliminar: () => request<{ status: string; message?: string }>('/medidas/eliminar', { method: 'POST' }),
  },

  carrito: {
    guardados: (options?: RequestOptions) => request<{ status: string; items: ApiCarritoItem[] }>('/carrito/guardados', options),
    guardar: (id_carrito_item: number) => request<{ status: string; message?: string; items?: ApiCarritoItem[] }>('/carrito/guardar', { method: 'POST', body: JSON.stringify({ id_carrito_item }) }),
    restaurar: (id_variante: number) => request<{ status: string; message?: string; items?: ApiCarritoItem[] }>('/carrito/restaurar', { method: 'POST', body: JSON.stringify({ id_variante }) }),
    obtener: (options?: RequestOptions) => request<{ status: string; message?: string; items: ApiCarritoItem[] }>('/carrito', options),
    agregar: (datos: { id_variante: number; cantidad?: number }) =>
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
    codigo: (id: number, options?: RequestOptions) => request<{ status: string; codigo?: string | null }>(`/pedido/codigo?id=${id}`, options),
    cancelar: (id: number) => request<{ status: string; message?: string; codigo_anulacion?: string | null }>('/pedidos/cancelar', { method: 'POST', body: JSON.stringify({ id }) }),
    listar: (options?: RequestOptions) => request<{ status: string; pedidos: ApiPedido[] }>('/pedidos', options),
    detalle: (id: number, options?: RequestOptions) => request<{ status: string; message?: string; pedido?: ApiPedido }>(`/pedido?id=${id}`, options),
    crear: (datos: { id_direccion?: number; direccion?: string; ciudad?: string; referencia?: string; marca_tarjeta?: string; ultimos_digitos?: string; numero_simulado: string; otp_simulado?: string }) =>
      request<{ status: string; message?: string; correo_enviado?: boolean; pedido?: ApiPedido }>('/pedidos', {
        method: 'POST',
        body: JSON.stringify(datos),
      }),
  },

  postventa: {
    listar: (options?: RequestOptions) => request<{ status: string; message?: string; solicitudes?: ApiSolicitudPostventa[] }>('/postventa', options),
    detalle: (id: number, options?: RequestOptions) => request<{ status: string; message?: string; solicitud?: ApiSolicitudPostventa; items?: ApiItemPostventa[]; cambios?: ApiCambioPostventa[]; historial?: ApiHistorialPostventa[]; evidencias?: ApiEvidenciaPostventa[] }>(`/postventa/detalle?id=${id}`, options),
  },

  direcciones: {
    listar: (options?: RequestOptions) => request<{ status: string; direcciones: ApiDireccion[] }>('/direcciones', options),
    guardar: (datos: { id?: number; nombre: string; direccion: string; ciudad: string; departamento: string; referencia?: string; telefono?: string; principal?: boolean }) =>
      request<{ status: string; message?: string; id?: number; direcciones?: ApiDireccion[] }>('/direcciones', { method: 'POST', body: JSON.stringify(datos) }),
    eliminar: (id: number) => request<{ status: string; message?: string; direcciones?: ApiDireccion[] }>('/direcciones/eliminar', { method: 'POST', body: JSON.stringify({ id }) }),
    principal: (id: number) => request<{ status: string; message?: string; direcciones?: ApiDireccion[] }>('/direcciones/principal', { method: 'POST', body: JSON.stringify({ id }) }),
  },
  favoritos: {
    listar: (options?: RequestOptions) => request<{ status: string; productos: ApiFavorito[] }>('/favoritos', options),
    alternar: (id_producto: number) => request<{ status: string; message?: string; favorito?: boolean }>('/favoritos/alternar', { method: 'POST', body: JSON.stringify({ id_producto }) }),
  },
  cuenta: {
    datos: (options?: RequestOptions) => request<{ status: string; datos: ApiDatosCuenta | null }>('/cuenta/datos', options),
    guardar: (datos: { nombre: string; telefono: string; avisos_pedidos: boolean; avisos_novedades: boolean }) => request<{ status: string; message?: string; datos?: ApiDatosCuenta }>('/cuenta/datos', { method: 'POST', body: JSON.stringify(datos) }),
    clave: (actual: string, nueva: string) => request<{ status: string; message?: string }>('/cuenta/clave', { method: 'POST', body: JSON.stringify({ actual, nueva }) }),
    avisos: (options?: RequestOptions) => request<{ status: string; avisos: { id_notificacion: number; id_pedido: number | null; mensaje_notificacion: string; fecha_creacion: string }[] }>('/cuenta/avisos', options),
  },
};
