export function validarCaducidad(valor: string): string {
  const caducidad = valor.trim();
  if (!caducidad) return 'Ingresa la fecha de caducidad';

  const coincidencia = /^(\d{2})\/(\d{2})$/.exec(caducidad);
  if (!coincidencia) return 'La caducidad debe tener el formato MM/AA';

  const mes = Number(coincidencia[1]);
  const año = 2000 + Number(coincidencia[2]);
  if (mes < 1 || mes > 12) return 'El mes de caducidad no es válido';

  const ahora = new Date();
  const mesActual = ahora.getMonth() + 1;
  const añoActual = ahora.getFullYear();
  if (año < añoActual || (año === añoActual && mes < mesActual)) {
    return 'La tarjeta está caducada';
  }

  return '';
}
