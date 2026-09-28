export const passwordRequirements = '8 caracteres, una mayúscula, una minúscula, un número y un carácter especial.';

export function cumplePoliticaContrasena(password: string): boolean {
  return password.length >= 8
    && password.length <= 128
    && /\p{Lu}/u.test(password)
    && /\p{Ll}/u.test(password)
    && /\p{N}/u.test(password)
    && /[^\p{L}\p{N}\s]/u.test(password);
}
