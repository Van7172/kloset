import { Link, useNavigate, useParams } from 'react-router-dom';
import { Seo } from '../components/Seo';

type DocKey = 'privacidad' | 'terminos' | 'reclamaciones';

const TABS: { key: DocKey; label: string }[] = [
  { key: 'privacidad', label: 'Política de Privacidad' },
  { key: 'terminos', label: 'Términos y Condiciones' },
  { key: 'reclamaciones', label: 'Libro de Reclamaciones' },
];

const DOCS: Record<DocKey, { title: string; intro: string; sections: { title: string; text: string }[] }> = {
  privacidad: {
    title: 'Política de Privacidad',
    intro: 'Borrador visible para la primera entrega. Este texto describe los datos que maneja la versión actual de Kloset y requiere revisión con la identidad legal y los canales oficiales de la tienda antes de su publicación definitiva.',
    sections: [
      { title: 'Datos de cuenta y pedidos', text: 'La tienda registra el nombre, el correo, el teléfono opcional, la contraseña protegida, los favoritos, las preferencias de avisos, los pedidos y las direcciones de entrega.' },
      { title: 'Medidas y recomendación', text: 'La guía usa estatura, pecho, cintura y cadera para calcular una talla sugerida. Antes de iniciar sesión, las medidas quedan en este navegador. Si el cliente entra a su cuenta, puede guardarlas en su perfil.' },
      { title: 'Consultas de contacto', text: 'El formulario guarda el nombre, correo, teléfono opcional y mensaje para que el equipo de Kloset pueda responder. La consulta queda visible en el panel administrativo.' },
      { title: 'Información pendiente', text: 'Faltan la identificación del titular, el domicilio, el canal para ejercer derechos sobre los datos y la revisión de plazos de conservación.' },
    ],
  },
  terminos: {
    title: 'Términos y Condiciones',
    intro: 'Borrador visible para la primera entrega. Las condiciones de venta definitivas deberán identificar al titular de Kloset y confirmar los procesos de pago, entrega, cambios y devoluciones.',
    sections: [
      { title: 'Catálogo y precios', text: 'La tienda muestra prendas deportivas, variantes por talla y corte, disponibilidad y precios en soles. El catálogo se administra desde el panel de Kloset.' },
      { title: 'Recomendación de talla', text: 'La talla sugerida es una estimación basada en las medidas indicadas por el cliente. Puede variar según la prenda y el corte elegido.' },
      { title: 'Pago y entrega', text: 'La versión de esta primera entrega incluye un simulador de pago. Antes de recibir pagos reales se deben conectar una pasarela y las condiciones comerciales definitivas.' },
      { title: 'Información pendiente', text: 'Faltan la razón social, RUC, domicilio, medios oficiales de atención y la aprobación final de las políticas de entrega y cambios.' },
    ],
  },
  reclamaciones: {
    title: 'Libro de Reclamaciones',
    intro: 'Borrador visible. El registro formal de reclamos y quejas aún no está habilitado; esta pantalla no emite constancias ni números de reclamación.',
    sections: [
      { title: 'Registro pendiente', text: 'Antes de la publicación definitiva se debe implementar el libro con la identidad legal del proveedor, los campos requeridos, la constancia para el cliente y el flujo de atención.' },
      { title: 'Consultas generales', text: 'Para dudas sobre productos, tallas o pedidos puedes usar el formulario de contacto. Ese formulario recibe consultas y no sustituye al Libro de Reclamaciones.' },
    ],
  },
};

export function LegalPage() {
  const params = useParams<{ doc?: string }>();
  const navigate = useNavigate();
  const doc: DocKey = params.doc === 'terminos' || params.doc === 'reclamaciones' ? params.doc : 'privacidad';
  const legal = DOCS[doc];

  return <div className="kl-rise mx-auto max-w-[760px]">
    <Seo title={legal.title} description={legal.intro} path={`/legal/${doc}`} noindex />
    <div className="no-scrollbar mb-[26px] flex gap-[18px] overflow-x-auto border-b border-ink pb-[14px]">
      {TABS.map((tab) => <button key={tab.key} type="button" onClick={() => navigate(`/legal/${tab.key}`)}
        className="relative min-h-[42px] shrink-0 cursor-pointer border-none bg-transparent py-[6px] pb-3 font-narrow text-[13px] font-semibold uppercase tracking-[0.06em]"
        style={{ color: doc === tab.key ? 'var(--ink)' : 'var(--soft)' }}>
        {tab.label}
        <span className="absolute inset-x-0 bottom-0 h-[2px]" style={{ background: doc === tab.key ? 'var(--red)' : 'transparent' }} />
      </button>)}
    </div>
    <div className="mb-[18px] inline-block border-b-[3px] border-red pb-[6px] font-narrow text-xs uppercase tracking-[0.14em]">Borrador · primera entrega</div>
    <h1 className="mb-[14px] font-display text-[34px] font-normal leading-[1.06] tracking-[-0.025em] md:text-[50px]">{legal.title}</h1>
    <p className="mb-[28px] max-w-[60ch] text-[15px] leading-[1.7] text-body">{legal.intro}</p>
    {legal.sections.map((section, index) => <div key={section.title} className="flex gap-4 border-t border-rule py-[20px]">
      <span className="font-narrow text-xs text-red">{String(index + 1).padStart(2, '0')}</span>
      <div><h2 className="mb-[7px] font-display text-[22px]">{section.title}</h2><p className="m-0 text-[14px] leading-[1.65] text-body">{section.text}</p></div>
    </div>)}
    <div className="border-t-2 border-ink pt-[20px]">
      <Link to="/contacto" className="inline-flex min-h-12 items-center border border-ink px-5 font-narrow text-xs font-semibold uppercase tracking-[0.08em] text-ink hover:bg-ink hover:text-paper">Ir a contacto →</Link>
    </div>
  </div>;
}
