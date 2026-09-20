import { useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { Seo } from '../components/Seo';

type DocKey = 'privacidad' | 'terminos' | 'reclamaciones';

const TABS: { key: DocKey; label: string }[] = [
  { key: 'privacidad', label: 'Política de Privacidad' },
  { key: 'terminos', label: 'Términos y Condiciones' },
  { key: 'reclamaciones', label: 'Libro de Reclamaciones' },
];

const DOCS: Record<DocKey, { kicker: string; title: string; intro: string; isForm: boolean; sections: { n: string; t: string; d: string }[]; foot: string }> = {
  terminos: {
    kicker: 'Vigente desde el 1 de septiembre de 2026',
    title: 'Términos y Condiciones',
    intro:
      'Estas condiciones regulan la compra en kloset.pe, operada por Kloset S.A.C., con domicilio en Av. Arequipa 2450, Lince, Lima. Al realizar un pedido aceptas lo que sigue.',
    isForm: false,
    sections: [
      { n: '01', t: 'Identificación del vendedor', d: 'Kloset S.A.C., RUC 20612345678, domiciliada en Av. Arequipa 2450, Lince, Lima. Correo de contacto: hola@kloset.pe. Teléfono: (01) 480 2200.' },
      { n: '02', t: 'Precios y moneda', d: 'Todos los precios se expresan en soles (PEN) e incluyen IGV. El precio aplicable es el mostrado al momento de confirmar el pedido. Los costes de envío, cuando existan, se detallan antes del pago.' },
      { n: '03', t: 'Recomendación de talla', d: 'La talla sugerida y la visualización sobre el avatar son una estimación calculada a partir de las medidas que registras. Es una ayuda de decisión, no una garantía de ajuste; por eso el primer cambio de talla es gratuito.' },
      { n: '04', t: 'Pago', d: 'Aceptamos tarjetas de crédito y débito procesadas por nuestra pasarela. El pedido se confirma cuando la autorización es aprobada. Si el emisor rechaza el cargo, el pedido se cancela sin cobro alguno.' },
      { n: '05', t: 'Entrega', d: 'Repartimos con flota propia en Lima Metropolitana y Callao en un plazo de 24 a 72 horas hábiles. Fuera de la zona de cobertura enviamos por agencia con cargo aparte y plazo informado antes del pago.' },
      { n: '06', t: 'Cambios y devoluciones', d: 'Tienes 30 días calendario desde la recepción para cambiar la talla sin coste, siempre que la prenda esté sin uso, con etiquetas y en su empaque. La devolución del importe, cuando corresponda, se realiza por el mismo medio de pago.' },
      { n: '07', t: 'Derecho de desistimiento', d: 'Conforme al Código de Protección y Defensa del Consumidor, puedes desistir de la compra dentro de los plazos legales aplicables a la venta a distancia, salvo en productos de higiene personal abiertos.' },
      { n: '08', t: 'Disponibilidad y errores', d: 'Si una prenda queda sin stock tras la confirmación, te avisamos y puedes elegir entre otra talla, otra prenda o el reembolso íntegro. Los errores tipográficos evidentes de precio no obligan a la venta.' },
      { n: '09', t: 'Propiedad intelectual', d: 'Las marcas, textos, fotografías y el sistema de recomendación de talla son propiedad de Kloset S.A.C. y no pueden reproducirse sin autorización escrita.' },
      { n: '10', t: 'Ley aplicable', d: 'Estas condiciones se rigen por la legislación peruana. Cualquier controversia se somete a los jueces y tribunales de Lima, sin perjuicio de acudir a INDECOPI.' },
    ],
    foot: 'Kloset S.A.C. · RUC 20612345678 · Av. Arequipa 2450, Lince, Lima · hola@kloset.pe',
  },
  reclamaciones: {
    kicker: 'Libro de Reclamaciones virtual · D.S. N.º 011-2011-PCM',
    title: 'Libro de Reclamaciones',
    intro:
      'Conforme al Código de Protección y Defensa del Consumidor (Ley N.º 29571), Kloset S.A.C. pone a tu disposición este Libro de Reclamaciones virtual. Registra tu reclamo o queja y recibirás copia con número de registro.',
    isForm: true,
    sections: [
      { n: '01', t: 'Plazo de respuesta', d: 'Atendemos y respondemos en un plazo máximo de quince (15) días hábiles desde el registro. El plazo puede ampliarse por causa justificada, comunicándotelo previamente.' },
      { n: '02', t: 'Diferencia entre reclamo y queja', d: 'Un reclamo es una disconformidad con el producto o el servicio contratado. Una queja expresa malestar respecto a la atención recibida y no está referida al producto en sí.' },
      { n: '03', t: 'Constancia', d: 'Al registrar recibirás por correo la hoja de reclamación con su número. Conserva esa constancia: la necesitarás para cualquier seguimiento.' },
      { n: '04', t: 'Instancia administrativa', d: 'El registro no impide acudir a otras vías. Puedes presentar tu caso ante INDECOPI a través de sus canales oficiales o de la línea 224-7777 en Lima.' },
    ],
    foot: 'Kloset S.A.C. · RUC 20612345678 · Libro de Reclamaciones virtual conforme al D.S. N.º 011-2011-PCM',
  },
  privacidad: {
    kicker: 'Actualizada el 1 de septiembre de 2026',
    title: 'Política de Privacidad',
    intro:
      'Esta política explica qué datos recogemos, para qué los usamos y qué puedes exigirnos. Tratamos tus datos conforme a la Ley N.º 29733 de Protección de Datos Personales y su reglamento.',
    isForm: false,
    sections: [
      { n: '01', t: 'Responsable del tratamiento', d: 'Kloset S.A.C., RUC 20612345678, Av. Arequipa 2450, Lince, Lima. Puedes escribirnos a privacidad@kloset.pe para cualquier asunto relacionado con tus datos.' },
      { n: '02', t: 'Datos que recogemos', d: 'Datos de cuenta (nombre, correo, contraseña cifrada), datos de entrega (dirección, distrito, teléfono), historial de pedidos y tus medidas corporales: estatura, pecho, cintura y cadera.' },
      { n: '03', t: 'Para qué usamos tus medidas', d: 'Únicamente para calcular la talla recomendada, generar tu avatar y mejorar la precisión del sistema mediante estadísticas agregadas y anonimizadas. No se usan para segmentación publicitaria ni se ceden a terceros con fines comerciales.' },
      { n: '04', t: 'Datos de pago', d: 'No almacenamos números completos de tarjeta ni códigos de seguridad. El cobro lo procesa nuestra pasarela de pago; nosotros conservamos solo el identificador de la transacción y los cuatro últimos dígitos.' },
      { n: '05', t: 'Encargados de tratamiento', d: 'Compartimos lo mínimo necesario con la empresa de reparto (nombre, dirección, teléfono), la pasarela de pago y el proveedor de correo transaccional. Todos actúan bajo contrato y no pueden usar tus datos para otros fines.' },
      { n: '06', t: 'Plazo de conservación', d: 'Conservamos tu cuenta mientras esté activa. Los datos de facturación se guardan por el plazo legal contable. Si eliminas tu cuenta, tus medidas y tu avatar se borran de inmediato.' },
      { n: '07', t: 'Tus derechos', d: 'Puedes ejercer los derechos de información, acceso, actualización, rectificación, oposición y supresión escribiendo a privacidad@kloset.pe. Respondemos en los plazos que fija la ley y sin coste.' },
      { n: '08', t: 'Cookies', d: 'Usamos cookies propias para mantener tu sesión y tu bolsa, y cookies de medición agregada. Puedes rechazar las de medición sin perder funcionalidad de compra.' },
      { n: '09', t: 'Seguridad', d: 'Ciframos el tráfico y las contraseñas, limitamos el acceso interno a los datos corporales al personal que lo necesita y registramos cada consulta. Si ocurriera un incidente que te afecte, te lo comunicaremos.' },
    ],
    foot: 'Autoridad Nacional de Protección de Datos Personales · Ministerio de Justicia y Derechos Humanos · Ley N.º 29733',
  },
};

const CAMPOS_RECLAMO = [
  { k: 'nombre', label: 'Nombre completo', ph: 'Como figura en tu DNI' },
  { k: 'dni', label: 'DNI o CE', ph: '00000000' },
  { k: 'correo', label: 'Correo', ph: 'tucorreo@correo.pe' },
  { k: 'tel', label: 'Teléfono', ph: '9XX XXX XXX' },
  { k: 'pedido', label: 'N.º de pedido', ph: 'KL-40128' },
];

export function LegalPage() {
  const params = useParams<{ doc?: string }>();
  const navigate = useNavigate();
  const doc: DocKey = params.doc === 'terminos' || params.doc === 'reclamaciones' ? params.doc : 'privacidad';
  const legal = DOCS[doc];

  const [tipo, setTipo] = useState<'reclamo' | 'queja'>('reclamo');
  const [campos, setCampos] = useState<Record<string, string>>({});
  const [detalle, setDetalle] = useState('');
  const [enviado, setEnviado] = useState('');

  const registrar = () => setEnviado('Reclamación registrada · recibirás copia por correo');

  return (
    <div className="kl-rise mx-auto max-w-[760px]">
      <Seo title={legal.title} description={legal.intro} path={`/legal/${doc}`} />
      <div className="no-scrollbar mb-[26px] flex gap-[18px] overflow-x-auto border-b border-ink pb-[14px]">
        {TABS.map((t) => (
          <button
            key={t.key}
            type="button"
            onClick={() => navigate(`/legal/${t.key}`)}
            className="relative min-h-[42px] shrink-0 cursor-pointer border-none bg-transparent py-[6px] pb-3 font-narrow text-[13px] font-semibold uppercase tracking-[0.06em]"
            style={{ color: doc === t.key ? 'var(--ink)' : 'var(--soft)' }}
          >
            {t.label}
            <span
              className="absolute inset-x-0 bottom-0 h-[2px]"
              style={{ background: doc === t.key ? 'var(--red)' : 'transparent' }}
            />
          </button>
        ))}
      </div>

      <div className="mb-3 font-narrow text-[11.5px] uppercase tracking-[0.14em] text-soft">{legal.kicker}</div>
      <h1 className="mb-[14px] font-display text-[30px] font-normal leading-[1.08] tracking-[-0.025em] md:text-[42px]">
        {legal.title}
      </h1>
      <p className="m-0 mb-[30px] max-w-[60ch] text-[15.5px] leading-[1.7] text-body">{legal.intro}</p>

      {legal.isForm && (
        <div className="mb-[30px] border-t-[3px] border-red bg-surface p-[22px]">
          <div className="mb-[14px] font-narrow text-[11.5px] uppercase tracking-[0.14em] text-soft">
            Hoja de reclamación · Registro N.º KL-LR-2026004
          </div>
          <div className="mb-[18px] flex border border-ink">
            {(['reclamo', 'queja'] as const).map((t, i) => (
              <button
                key={t}
                type="button"
                onClick={() => setTipo(t)}
                className="min-h-[46px] flex-1 cursor-pointer border-none font-narrow text-[12.5px] font-semibold uppercase tracking-[0.06em]"
                style={{
                  background: tipo === t ? 'var(--ink)' : 'transparent',
                  color: tipo === t ? 'var(--paper)' : 'var(--soft)',
                  borderRight: i === 0 ? '1px solid var(--ink)' : 'none',
                }}
              >
                {t === 'reclamo' ? 'Reclamo' : 'Queja'}
              </button>
            ))}
          </div>
          <p className="m-0 mb-[18px] text-[13.5px] leading-[1.6] text-body">
            {tipo === 'reclamo'
              ? 'Reclamo: disconformidad con el producto o el servicio recibido. Respondemos en un plazo máximo de 15 días hábiles.'
              : 'Queja: malestar respecto a la atención recibida, sin relación directa con el producto. La registramos y respondemos igualmente.'}
          </p>
          <div className="border-t border-ink">
            {CAMPOS_RECLAMO.map((f) => (
              <div key={f.k} className="border-b border-rule py-[13px]">
                <label className="mb-[5px] block font-narrow text-[11px] uppercase tracking-[0.12em] text-soft">
                  {f.label}
                </label>
                <input
                  value={campos[f.k] ?? ''}
                  onChange={(e) => {
                    setCampos((c) => ({ ...c, [f.k]: e.target.value }));
                    setEnviado('');
                  }}
                  placeholder={f.ph}
                  className="min-h-[38px] w-full border-none bg-transparent py-[7px] text-[15.5px] text-ink outline-none"
                />
              </div>
            ))}
            <div className="border-b border-rule py-[13px]">
              <label className="mb-[5px] block font-narrow text-[11px] uppercase tracking-[0.12em] text-soft">
                Detalle de la reclamación
              </label>
              <textarea
                value={detalle}
                onChange={(e) => {
                  setDetalle(e.target.value);
                  setEnviado('');
                }}
                rows={4}
                placeholder="Describe el hecho y el pedido afectado"
                className="w-full resize-y border-none bg-transparent py-[7px] text-[15.5px] leading-[1.55] text-ink outline-none"
              />
            </div>
          </div>

          {enviado && (
            <div className="mt-4 border-l-[3px] border-red bg-paper px-[14px] py-3 font-narrow text-xs uppercase tracking-[0.06em]">
              {enviado}
            </div>
          )}

          <button
            type="button"
            onClick={registrar}
            className="mt-5 min-h-[56px] w-full cursor-pointer border-none bg-ink font-narrow text-[15px] font-semibold uppercase tracking-[0.08em] text-paper hover:bg-red hover:text-[#F2F2F0]"
          >
            Registrar reclamación
          </button>
        </div>
      )}

      {legal.sections.map((sec) => (
        <div key={sec.n} className="border-t border-rule py-[22px]">
          <div className="flex items-baseline gap-4">
            <span className="shrink-0 font-narrow text-xs tracking-[0.1em] text-red">{sec.n}</span>
            <div className="min-w-0">
              <div className="mb-[9px] font-display text-[21px] tracking-[-0.015em]">{sec.t}</div>
              <p className="m-0 max-w-[60ch] text-[14.5px] leading-[1.7] text-body">{sec.d}</p>
            </div>
          </div>
        </div>
      ))}

      <div className="mt-[10px] border-t-2 border-ink pt-[18px] font-narrow text-[11.5px] uppercase leading-[1.7] tracking-[0.1em] text-soft">
        {legal.foot}
      </div>
    </div>
  );
}
