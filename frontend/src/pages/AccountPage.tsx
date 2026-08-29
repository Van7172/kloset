import { useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useKloset } from '../store/KlosetContext';
import { tallaRecomendada } from '../lib/fit';

export function AccountPage() {
  const navigate = useNavigate();
  const { usuario, medidas, tienePerfil, corte, pedidos, salir, cargandoSesion } = useKloset();

  useEffect(() => {
    if (!cargandoSesion && !usuario) navigate('/entrar', { replace: true });
  }, [cargandoSesion, usuario, navigate]);

  if (!usuario) return null;

  const filas = [
    { k: 'Perfil corporal', v: tienePerfil ? 'Guardado' : 'Sin registrar' },
    { k: 'Estatura · pecho', v: `${medidas.h} cm · ${medidas.chest} cm` },
    { k: 'Cintura · cadera', v: `${medidas.waist} cm · ${medidas.hip} cm` },
    { k: 'Corte preferido', v: corte },
    { k: 'Talla recomendada', v: tallaRecomendada(medidas, corte) },
    { k: 'Pedidos', v: String(pedidos.length) },
    { k: 'Rol', v: usuario.rol },
  ];

  return (
    <div className="kl-rise mx-auto max-w-[680px]">
      <div className="mb-[14px] mt-[6px] font-narrow text-xs uppercase tracking-[0.14em] text-soft">Tu cuenta</div>
      <h2 className="mb-[6px] font-display text-[34px] font-normal leading-tight tracking-[-0.025em]">
        {usuario.nombre}
      </h2>
      <p className="mb-[26px] text-[15px] text-body">{usuario.correo}</p>

      <div className="border-t-2 border-ink">
        {filas.map((r) => (
          <div key={r.k} className="flex justify-between gap-4 border-b border-rule py-[14px] text-sm">
            <span className="text-soft">{r.k}</span>
            <span className="text-right text-ink">{r.v}</span>
          </div>
        ))}
      </div>

      <div className="mt-[26px] flex flex-col gap-2 sm:flex-row">
        <button
          type="button"
          onClick={() => navigate('/medidas')}
          className="min-h-[54px] flex-1 cursor-pointer border-none bg-ink font-narrow text-[15px] font-semibold uppercase tracking-[0.08em] text-paper hover:bg-red hover:text-[#F2F2F0]"
        >
          Editar mis medidas
        </button>
        <button
          type="button"
          onClick={() => navigate('/pedidos')}
          className="min-h-[54px] flex-1 cursor-pointer border border-ink bg-transparent font-narrow text-[15px] font-semibold uppercase tracking-[0.08em] text-ink hover:bg-hover"
        >
          Mis pedidos
        </button>
      </div>
      <button
        type="button"
        onClick={() => {
          salir();
          navigate('/');
        }}
        className="mt-[10px] min-h-[44px] w-full cursor-pointer border-none bg-transparent text-[13px] text-soft underline"
      >
        Cerrar sesión
      </button>
    </div>
  );
}
