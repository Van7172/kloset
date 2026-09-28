import { useState, type FormEvent } from 'react';
import { api, type ApiDireccion } from '../services/api';

export function AddressForm({ actual, onClose, onSaved }: { actual?: ApiDireccion | null; onClose: () => void; onSaved: (direcciones: ApiDireccion[], id: number) => void }) {
  const [nombre, setNombre] = useState(actual?.nombre ?? 'Casa');
  const [direccion, setDireccion] = useState(actual?.direccion ?? '');
  const [ciudad, setCiudad] = useState(actual?.ciudad ?? '');
  const [referencia, setReferencia] = useState(actual?.referencia ?? '');
  const [telefono, setTelefono] = useState(actual?.telefono ?? '');
  const [principal, setPrincipal] = useState(Boolean(actual?.principal));
  const [error, setError] = useState('');
  const [guardando, setGuardando] = useState(false);

  const guardar = async (event: FormEvent) => {
    event.preventDefault();
    setError('');
    if (nombre.trim().length < 2 || direccion.trim().length < 5 || ciudad.trim().length < 2) return setError('Completa nombre, dirección y distrito.');
    setGuardando(true);
    try {
      const res = await api.direcciones.guardar({ id: actual?.id, nombre: nombre.trim(), direccion: direccion.trim(), ciudad: ciudad.trim(), referencia: referencia.trim(), telefono: telefono.trim(), principal });
      if (res.status !== 'success' || !res.direcciones) return setError(res.message || 'No se pudo guardar la dirección.');
      onSaved(res.direcciones, res.id ?? actual?.id ?? 0);
    } catch {
      setError('No hay conexión con la API.');
    } finally {
      setGuardando(false);
    }
  };

  const campo = (label: string, value: string, change: (value: string) => void, placeholder: string, required = false) => <label className="block"><span className="mb-1 block font-narrow text-xs font-semibold uppercase tracking-[0.1em] text-soft">{label}{required ? ' *' : ''}</span><input value={value} onChange={(e) => change(e.target.value)} placeholder={placeholder} className="min-h-12 w-full border border-rule bg-paper px-3 text-sm text-ink outline-none focus:border-ink" /></label>;

  return <div className="fixed inset-0 z-[100] flex items-center justify-center overflow-y-auto bg-black/60 p-4" role="presentation" onMouseDown={(e) => { if (e.target === e.currentTarget) onClose(); }}>
    <div role="dialog" aria-modal="true" aria-label={actual ? 'Editar dirección' : 'Añadir dirección'} className="my-auto w-full max-w-[580px] border border-rule bg-paper p-6 shadow-2xl sm:p-9">
      <div className="mb-5 flex items-start justify-between gap-4"><div><h2 className="font-display text-[32px] leading-tight">{actual ? 'Editar dirección' : 'Añadir dirección'}</h2><p className="mt-1 text-sm text-body">Guarda un lugar de entrega para futuras compras.</p></div><button type="button" aria-label="Cerrar" onClick={onClose} className="min-h-10 min-w-10 cursor-pointer border-none bg-transparent text-2xl text-ink">×</button></div>
      <form onSubmit={guardar} className="space-y-4">
        {campo('Nombre de la dirección', nombre, setNombre, 'Casa, oficina, departamento', true)}
        {campo('Dirección', direccion, setDireccion, 'Av., Jr., calle, número y departamento', true)}
        <div className="grid gap-4 sm:grid-cols-2">{campo('Distrito', ciudad, setCiudad, 'Miraflores', true)}{campo('Teléfono de entrega', telefono, setTelefono, 'Opcional')}</div>
        {campo('Referencia', referencia, setReferencia, 'Opcional')}
        <label className="flex cursor-pointer items-center gap-3 text-sm text-body"><input type="checkbox" checked={principal} onChange={(e) => setPrincipal(e.target.checked)} className="h-5 w-5 accent-red" /> Usar como dirección principal</label>
        {error && <p role="alert" className="border-l-[3px] border-red pl-3 text-sm text-red">{error}</p>}
        <div className="flex gap-3 pt-2"><button type="button" onClick={onClose} className="min-h-12 flex-1 cursor-pointer border border-ink bg-transparent font-narrow text-xs font-semibold uppercase tracking-[0.08em] text-ink">Cancelar</button><button type="submit" disabled={guardando} className="min-h-12 flex-1 cursor-pointer border-none bg-ink font-narrow text-xs font-semibold uppercase tracking-[0.08em] text-paper disabled:opacity-50">{guardando ? 'Guardando…' : 'Guardar dirección'}</button></div>
      </form>
    </div>
  </div>;
}
