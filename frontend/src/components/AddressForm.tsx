import { useState, type FormEvent } from 'react';
import { api, type ApiDireccion } from '../services/api';
import { nombreCortoDepartamento, TIPOS_DIRECCION, ZONAS_ENTREGA } from '../lib/locations';

export function AddressForm({ actual, onClose, onSaved }: { actual?: ApiDireccion | null; onClose: () => void; onSaved: (direcciones: ApiDireccion[], id: number) => void }) {
  const [nombre, setNombre] = useState(actual?.nombre ?? '');
  const [tipo, setTipo] = useState(actual?.tipo ?? 'Casa');
  const [direccion, setDireccion] = useState(actual?.direccion ?? '');
  const [departamento, setDepartamento] = useState(actual?.departamento ?? 'Lima Metropolitana');
  const [distrito, setDistrito] = useState(actual?.ciudad ?? '');
  const [referencia, setReferencia] = useState(actual?.referencia ?? '');
  const [error, setError] = useState('');
  const [guardando, setGuardando] = useState(false);
  const distritos: readonly string[] = ZONAS_ENTREGA.find((zona) => zona.nombre === departamento)?.distritos ?? [];

  const guardar = async (event: FormEvent) => {
    event.preventDefault();
    setError('');
    if (nombre.trim().length < 2 || direccion.trim().length < 5 || !distritos.includes(distrito)) return setError('Completa nombre, dirección y selecciona un distrito válido.');
    setGuardando(true);
    try {
      const res = await api.direcciones.guardar({ id: actual?.id, nombre: nombre.trim(), tipo, direccion: direccion.trim(), ciudad: distrito, departamento, referencia: referencia.trim(), telefono: actual?.telefono ?? undefined });
      if (res.status !== 'success' || !res.direcciones) return setError(res.message || 'No se pudo guardar la dirección.');
      onSaved(res.direcciones, res.id ?? actual?.id ?? 0);
    } catch {
      setError('No hay conexión con la API.');
    } finally {
      setGuardando(false);
    }
  };

  const etiqueta = (texto: string, requerido = false) => <span className="mb-1 block font-narrow text-xs font-semibold uppercase tracking-[0.1em] text-soft">{texto}{requerido ? ' *' : ''}</span>;
  const claseSelect = 'min-h-12 w-full border border-rule bg-paper px-3 text-sm text-ink outline-none focus:border-ink';

  return <div className="fixed inset-0 z-[100] flex items-center justify-center overflow-y-auto bg-black/60 p-4" role="presentation" onMouseDown={(e) => { if (e.target === e.currentTarget) onClose(); }}>
    <div role="dialog" aria-modal="true" aria-label={actual ? 'Editar dirección' : 'Añadir dirección'} className="my-auto w-full max-w-[580px] border border-rule bg-paper p-6 shadow-2xl sm:p-9">
      <div className="mb-5 flex items-start justify-between gap-4"><div><h2 className="font-display text-[32px] leading-tight">{actual ? 'Editar dirección' : 'Añadir dirección'}</h2><p className="mt-1 text-sm text-body">Guarda un lugar de entrega para futuras compras.</p></div><button type="button" aria-label="Cerrar" onClick={onClose} className="min-h-10 min-w-10 cursor-pointer border-none bg-transparent text-2xl text-ink">×</button></div>
      <form onSubmit={guardar} className="space-y-4">
        <label className="block">{etiqueta('Nombre de la dirección', true)}<input value={nombre} onChange={(event) => setNombre(event.target.value)} placeholder="Ej. Casa, Oficina, Departamento" maxLength={80} className={claseSelect} /></label>
        <div className="grid gap-4 sm:grid-cols-[0.8fr_1.2fr]">
          <label className="block">{etiqueta('Tipo de dirección', true)}<select value={tipo} onChange={(event) => setTipo(event.target.value)} className={claseSelect}>{TIPOS_DIRECCION.map((opcion) => <option key={opcion} value={opcion}>{opcion}</option>)}</select></label>
          <label className="block">{etiqueta('Dirección', true)}<input value={direccion} onChange={(event) => setDireccion(event.target.value)} placeholder="Av., Jr., Calle, N.°, Dpto., Piso, Mz., Lt." maxLength={255} className={claseSelect} /></label>
        </div>
        <label className="block">{etiqueta('Referencia (opcional)')}<input value={referencia} onChange={(event) => setReferencia(event.target.value)} placeholder="Ej. A 2 cuadras del parque, frente a la iglesia" maxLength={255} className={claseSelect} /></label>
        <div className="grid gap-4 sm:grid-cols-2">
          <label className="block">{etiqueta('Departamento', true)}<select value={departamento} onChange={(event) => { setDepartamento(event.target.value); setDistrito(''); }} className={claseSelect}>{ZONAS_ENTREGA.map((zona) => <option key={zona.nombre} value={zona.nombre}>{nombreCortoDepartamento(zona.nombre)}</option>)}</select></label>
          <label className="block">{etiqueta('Distrito', true)}<select value={distrito} onChange={(event) => setDistrito(event.target.value)} className={claseSelect}><option value="">Selecciona un distrito</option>{distritos.map((opcion) => <option key={opcion} value={opcion}>{opcion}</option>)}</select></label>
        </div>
        {error && <p role="alert" className="border-l-[3px] border-red pl-3 text-sm text-red">{error}</p>}
        <div className="flex gap-3 pt-2"><button type="button" onClick={onClose} className="min-h-12 flex-1 cursor-pointer border border-ink bg-transparent font-narrow text-xs font-semibold uppercase tracking-[0.08em] text-ink">Cancelar</button><button type="submit" disabled={guardando} className="min-h-12 flex-1 cursor-pointer border-none bg-ink font-narrow text-xs font-semibold uppercase tracking-[0.08em] text-paper disabled:opacity-50">{guardando ? 'Guardando…' : 'Guardar dirección'}</button></div>
      </form>
    </div>
  </div>;
}
