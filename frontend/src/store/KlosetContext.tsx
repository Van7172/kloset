import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from 'react';
import { api, getToken, setToken, type ApiUsuario } from '../services/api';
import { MEDIDAS_INICIALES, type Corte, type Medidas, type Talla } from '../lib/fit';

export type ItemBolsa = {
  id_producto: number;
  url_producto: string;
  name: string;
  size: Talla;
  fit: Corte;
  price: number;
  imagen: string | null;
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
  setMedidas: (m: Medidas) => void;
  guardarPerfil: () => void;
  setCorte: (c: Corte) => void;
  añadirABolsa: (item: ItemBolsa) => void;
  quitarDeBolsa: (index: number) => void;
  vaciarBolsa: () => void;
  registrarPedido: (pedido: Pedido) => void;
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

export function KlosetProvider({ children }: { children: ReactNode }) {
  const [usuario, setUsuario] = useState<ApiUsuario | null>(null);
  const [cargandoSesion, setCargandoSesion] = useState(true);
  const [tema, setTema] = useState<'light' | 'dark'>(temaInicial);
  const [medidas, setMedidasState] = useState<Medidas>(() => leer('kloset-medidas', MEDIDAS_INICIALES));
  const [tienePerfil, setTienePerfil] = useState(() => leer('kloset-perfil', false));
  const [corte, setCorteState] = useState<Corte>(() => leer<Corte>('kloset-corte', 'Regular'));
  const [bolsa, setBolsa] = useState<ItemBolsa[]>(() => leer<ItemBolsa[]>('kloset-bolsa', []));
  const [pedidos, setPedidos] = useState<Pedido[]>(() => leer<Pedido[]>('kloset-pedidos', []));
  const [intencion, setIntencion] = useState<Intencion>(null);

  useEffect(() => {
    document.documentElement.setAttribute('data-theme', tema);
    try {
      localStorage.setItem('kloset-theme', tema);
    } catch {
      /* ignorar */
    }
  }, [tema]);

  useEffect(() => guardar('kloset-medidas', medidas), [medidas]);
  useEffect(() => guardar('kloset-perfil', tienePerfil), [tienePerfil]);
  useEffect(() => guardar('kloset-corte', corte), [corte]);
  useEffect(() => guardar('kloset-bolsa', bolsa), [bolsa]);
  useEffect(() => guardar('kloset-pedidos', pedidos), [pedidos]);

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
      setMedidas: (m: Medidas) => setMedidasState(m),
      guardarPerfil: () => setTienePerfil(true),
      setCorte: (c: Corte) => setCorteState(c),
      añadirABolsa: (item: ItemBolsa) => setBolsa((b) => [...b, item]),
      quitarDeBolsa: (index: number) => setBolsa((b) => b.filter((_, i) => i !== index)),
      vaciarBolsa: () => setBolsa([]),
      registrarPedido: (pedido: Pedido) => setPedidos((p) => [pedido, ...p]),
      setIntencion,
      entrar,
      registrarse,
      salir: () => {
        setToken(null);
        setUsuario(null);
        setBolsa([]);
      },
    }),
    [usuario, tema, medidas, tienePerfil, corte, bolsa, pedidos, intencion, cargandoSesion, entrar, registrarse],
  );

  return <KlosetContext.Provider value={valor}>{children}</KlosetContext.Provider>;
}

export function useKloset() {
  const ctx = useContext(KlosetContext);
  if (!ctx) throw new Error('useKloset debe usarse dentro de KlosetProvider');
  return ctx;
}
