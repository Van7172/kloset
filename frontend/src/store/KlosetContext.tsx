import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from 'react';
import {
  api,
  getToken,
  setToken,
  type ApiCarritoItem,
  type ApiPedido,
  type ApiPedidoItem,
  type ApiUsuario,
} from '../services/api';
import { MEDIDAS_INICIALES, type Corte, type Medidas, type Talla } from '../lib/fit';

export type ItemBolsa = {
  /** Ausente en el ítem "optimista" que se guarda como intención antes de iniciar sesión. */
  id_carrito_item?: number;
  id_producto: number;
  url_producto: string;
  name: string;
  size: Talla;
  fit: Corte;
  price: number;
  imagen: string | null;
  cantidad: number;
};

export type Pedido = {
  ref: string;
  fecha: string;
  total: number;
  items: ItemBolsa[];
  card: string;
  estado: string;
};

/** Intención guardada cuando el usuario debe autenticarse a mitad de un flujo. */
export type Intencion = { then: 'cart' | 'checkout'; item?: ItemBolsa } | null;

export type DireccionEnvio = { direccion: string; ciudad: string; referencia?: string };

type Estado = {
  usuario: ApiUsuario | null;
  tema: 'light' | 'dark';
  medidas: Medidas;
  tienePerfil: boolean;
  corte: Corte;
  bolsa: ItemBolsa[];
  pedidos: Pedido[];
  intencion: Intencion;
  cargandoSesion: boolean;
};

type Acciones = {
  alternarTema: () => void;
  guardarPerfil: (m: Medidas) => Promise<string | null>;
  setCorte: (c: Corte) => void;
  añadirABolsa: (item: ItemBolsa) => Promise<string | null>;
  quitarDeBolsa: (idCarritoItem: number) => Promise<void>;
  crearPedido: (direccion: DireccionEnvio, tarjeta: { marca: string; ultimos: string }) => Promise<string | null>;
  setIntencion: (i: Intencion) => void;
  entrar: (correo: string, password: string) => Promise<string | null>;
  registrarse: (nombre: string, correo: string, password: string) => Promise<string | null>;
  salir: () => void;
};

const KlosetContext = createContext<(Estado & Acciones) | null>(null);

function leer<T>(clave: string, porDefecto: T): T {
  try {
    const raw = localStorage.getItem(clave);
    return raw ? (JSON.parse(raw) as T) : porDefecto;
  } catch {
    return porDefecto;
  }
}

function guardar(clave: string, valor: unknown) {
  try {
    localStorage.setItem(clave, JSON.stringify(valor));
  } catch {
    /* almacenamiento no disponible */
  }
}

function temaInicial(): 'light' | 'dark' {
  try {
    const saved = localStorage.getItem('kloset-theme');
    if (saved === 'light' || saved === 'dark') return saved;
  } catch {
    /* ignorar */
  }
  return window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

function mapCarritoItem(it: ApiCarritoItem): ItemBolsa {
  return {
    id_carrito_item: it.id_carrito_item,
    id_producto: it.id_producto,
    url_producto: it.url_producto,
    name: it.nombre_producto,
    size: it.talla_variante as Talla,
    fit: it.corte_variante as Corte,
    price: Number(it.precio_producto),
    imagen: it.url_imagen,
    cantidad: it.cantidad_carrito_item,
  };
}

function mapPedidoItem(it: ApiPedidoItem): ItemBolsa {
  return {
    id_producto: it.id_producto ?? 0,
    url_producto: '',
    name: it.nombre_producto ?? '',
    size: (it.talla_variante ?? it.talla ?? 'M') as Talla,
    fit: (it.corte_variante ?? it.corte ?? 'Regular') as Corte,
    price: Number(it.precio_unitario_pedido_item ?? it.precio ?? 0),
    imagen: null,
    cantidad: it.cantidad_pedido_item ?? it.cantidad ?? 1,
  };
}

function mapPedido(p: ApiPedido): Pedido {
  const fechaIso = p.fecha ?? p.fecha_creacion ?? '';
  const fecha = fechaIso
    ? new Date(fechaIso.replace(' ', 'T')).toLocaleDateString('es-PE', { day: 'numeric', month: 'short', year: 'numeric' })
    : '';
  return {
    ref: p.ref,
    fecha,
    total: Number(p.total ?? p.total_pedido ?? 0),
    items: p.items.map(mapPedidoItem),
    card: p.ultimos_digitos_pago ?? p.ultimos_digitos ?? '',
    estado: p.estado ?? p.estado_pedido ?? 'pagado',
  };
}

export function KlosetProvider({ children }: { children: ReactNode }) {
  const [usuario, setUsuario] = useState<ApiUsuario | null>(null);
  const [cargandoSesion, setCargandoSesion] = useState(true);
  const [tema, setTema] = useState<'light' | 'dark'>(temaInicial);
  const [medidas, setMedidasState] = useState<Medidas>(() => leer('kloset-medidas', MEDIDAS_INICIALES));
  const [tienePerfil, setTienePerfil] = useState(() => leer('kloset-perfil', false));
  const [corte, setCorteState] = useState<Corte>(() => leer<Corte>('kloset-corte', 'Regular'));
  const [bolsa, setBolsa] = useState<ItemBolsa[]>([]);
  const [pedidos, setPedidos] = useState<Pedido[]>([]);
  const [intencion, setIntencion] = useState<Intencion>(null);

  useEffect(() => {
    document.documentElement.setAttribute('data-theme', tema);
    try {
      localStorage.setItem('kloset-theme', tema);
    } catch {
      /* ignorar */
    }
  }, [tema]);

  // Las medidas y el corte son la única parte del perfil que también tiene sentido
  // para un visitante sin cuenta (probar la recomendación de talla antes de registrarse).
  useEffect(() => guardar('kloset-medidas', medidas), [medidas]);
  useEffect(() => guardar('kloset-perfil', tienePerfil), [tienePerfil]);
  useEffect(() => guardar('kloset-corte', corte), [corte]);

  // Restaura la sesión si el token sigue siendo válido
  useEffect(() => {
    if (!getToken()) {
      setCargandoSesion(false);
      return;
    }
    api
      .me()
      .then((res) => {
        if (res.status === 'success' && res.usuario) setUsuario(res.usuario);
        else setToken(null);
      })
      .catch(() => setToken(null))
      .finally(() => setCargandoSesion(false));
  }, []);

  // La bolsa, el perfil corporal y los pedidos viven en la cuenta: se cargan al
  // iniciar sesión (o al restaurarla) y se limpian al cerrarla.
  useEffect(() => {
    if (!usuario) {
      setBolsa([]);
      setPedidos([]);
      return;
    }

    api
      .medidas.obtener()
      .then((res) => {
        if (res.status === 'success' && res.perfil) {
          setMedidasState({
            h: Number(res.perfil.estatura_perfil_corporal),
            chest: Number(res.perfil.pecho_perfil_corporal),
            waist: Number(res.perfil.cintura_perfil_corporal),
            hip: Number(res.perfil.cadera_perfil_corporal),
          });
          setTienePerfil(true);
        }
      })
      .catch(() => undefined);

    api
      .carrito.obtener()
      .then((res) => {
        if (res.status === 'success') setBolsa(res.items.map(mapCarritoItem));
      })
      .catch(() => undefined);

    api
      .pedidos.listar()
      .then((res) => {
        if (res.status === 'success') setPedidos(res.pedidos.map(mapPedido));
      })
      .catch(() => undefined);
  }, [usuario]);

  const entrar = useCallback(async (correo: string, password: string) => {
    try {
      const res = await api.login(correo, password);
      if (res.status !== 'success' || !res.token || !res.usuario) {
        return res.message || 'No pudimos iniciar tu sesión';
      }
      setToken(res.token);
      setUsuario(res.usuario);
      return null;
    } catch {
      return 'No hay conexión con la API';
    }
  }, []);

  const registrarse = useCallback(async (nombre: string, correo: string, password: string) => {
    try {
      const res = await api.register(nombre, correo, password);
      if (res.status !== 'success' || !res.token || !res.usuario) {
        return res.message || 'No pudimos crear tu cuenta';
      }
      setToken(res.token);
      setUsuario(res.usuario);
      return null;
    } catch {
      return 'No hay conexión con la API';
    }
  }, []);

  const guardarPerfil = useCallback(async (m: Medidas) => {
    setMedidasState(m);
    setTienePerfil(true);
    if (!usuario) return null; // invitado: se queda solo en este dispositivo

    try {
      const res = await api.medidas.guardar({ estatura: m.h, pecho: m.chest, cintura: m.waist, cadera: m.hip, corte });
      if (res.status !== 'success') return res.message || 'No pudimos guardar tus medidas';
      return null;
    } catch {
      return 'No hay conexión con la API';
    }
  }, [usuario, corte]);

  const añadirABolsa = useCallback(async (item: ItemBolsa) => {
    // `getToken()` (no el estado `usuario`) porque esto puede llamarse justo tras
    // `entrar()`/`registrarse()`, antes de que el contexto vuelva a renderizar.
    if (!getToken()) return 'Inicia sesión para guardar tu bolsa';
    try {
      const res = await api.carrito.agregar({
        id_producto: item.id_producto,
        talla: item.size,
        corte: item.fit,
        cantidad: item.cantidad,
      });
      if (res.status !== 'success' || !res.items) return res.message || 'No se pudo añadir a la bolsa';
      setBolsa(res.items.map(mapCarritoItem));
      return null;
    } catch {
      return 'No hay conexión con la API';
    }
  }, []);

  const quitarDeBolsa = useCallback(async (idCarritoItem: number) => {
    try {
      const res = await api.carrito.quitar(idCarritoItem);
      if (res.status === 'success' && res.items) setBolsa(res.items.map(mapCarritoItem));
    } catch {
      /* la próxima carga de la bolsa reconciliará el estado */
    }
  }, []);

  const crearPedido = useCallback(
    async (direccion: DireccionEnvio, tarjeta: { marca: string; ultimos: string }) => {
      try {
        const res = await api.pedidos.crear({
          direccion: direccion.direccion,
          ciudad: direccion.ciudad,
          referencia: direccion.referencia,
          marca_tarjeta: tarjeta.marca,
          ultimos_digitos: tarjeta.ultimos,
        });
        if (res.status !== 'success' || !res.pedido) return res.message || 'No se pudo registrar el pedido';
        setPedidos((p) => [mapPedido(res.pedido as ApiPedido), ...p]);
        setBolsa([]);
        return null;
      } catch {
        return 'No hay conexión con la API';
      }
    },
    [],
  );

  const valor = useMemo(
    () => ({
      usuario,
      tema,
      medidas,
      tienePerfil,
      corte,
      bolsa,
      pedidos,
      intencion,
      cargandoSesion,
      alternarTema: () => setTema((t) => (t === 'dark' ? 'light' : 'dark')),
      guardarPerfil,
      setCorte: (c: Corte) => setCorteState(c),
      añadirABolsa,
      quitarDeBolsa,
      crearPedido,
      setIntencion,
      entrar,
      registrarse,
      salir: () => {
        setToken(null);
        setUsuario(null);
      },
    }),
    [
      usuario,
      tema,
      medidas,
      tienePerfil,
      corte,
      bolsa,
      pedidos,
      intencion,
      cargandoSesion,
      guardarPerfil,
      añadirABolsa,
      quitarDeBolsa,
      crearPedido,
      entrar,
      registrarse,
    ],
  );

  return <KlosetContext.Provider value={valor}>{children}</KlosetContext.Provider>;
}

export function useKloset() {
  const ctx = useContext(KlosetContext);
  if (!ctx) throw new Error('useKloset debe usarse dentro de KlosetProvider');
  return ctx;
}
