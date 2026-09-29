export const ZONAS_ENTREGA = [
  { nombre: 'Provincia Constitucional del Callao', plazo: 'entrega en 24 h', distritos: ['Bellavista', 'Callao', 'Carmen de la Legua-Reynoso', 'La Perla', 'La Punta', 'Ventanilla'] },
  { nombre: 'Lima Metropolitana', plazo: 'entrega en 24–48 h', distritos: ['Ancón', 'Ate', 'Barranco', 'Breña', 'Carabayllo', 'Chaclacayo', 'Chorrillos', 'Chosica', 'Cieneguilla', 'Comas', 'El Agustino', 'Independencia', 'Jesús María', 'La Molina', 'La Victoria', 'Lima - Cercado', 'Lince', 'Los Olivos', 'Lurigancho-Chosica', 'Lurigancho-Zárate', 'Lurín', 'Magdalena del Mar', 'Miraflores', 'Pachacámac', 'Pucusana', 'Pueblo Libre', 'Puente Piedra', 'Punta Hermosa', 'Punta Negra', 'Rímac', 'San Bartolo', 'San Borja', 'San Isidro', 'San Juan de Lurigancho', 'San Juan de Miraflores', 'San Luis', 'San Martín de Porres', 'San Miguel', 'Santa Anita', 'Santa María del Mar', 'Santa Rosa', 'Santiago de Surco', 'Surquillo', 'Villa El Salvador', 'Villa María del Triunfo'] },
] as const;

export const TIPOS_DIRECCION = ['Casa', 'Trabajo', 'Departamento', 'Otro'] as const;

export function nombreCortoDepartamento(nombre: string): string {
  return nombre === 'Lima Metropolitana' ? 'Lima' : nombre === 'Provincia Constitucional del Callao' ? 'Callao' : nombre;
}
