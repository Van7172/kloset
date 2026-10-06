<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/model/Msgbox.php';
require dirname(__DIR__) . '/app/model/Usuario.php';

use Develoweb\App\Model\Msgbox;
use Develoweb\App\Model\Usuario;

function legacyObject(string $class, array $fields): string
{
    $properties = '';
    foreach ($fields as $key => $value) {
        $properties .= serialize($key) . serialize($value);
    }
    return 'O:' . strlen($class) . ':"' . $class . '":' . count($fields) . ':{' . $properties . '}';
}

set_error_handler(static function (int $severity, string $message): never {
    throw new ErrorException($message, 0, $severity);
});

try {
    $legacyUser = unserialize(legacyObject(Usuario::class, [
        'id' => 7, 'nombre' => 'Anterior', 'apellidos' => '', 'email' => 'anterior@test.invalid',
        'rol' => 1, 'rol_nombre' => 'Administrador', 'logueado' => true, 'secciones' => [],
    ]));
    if (!$legacyUser instanceof Usuario || $legacyUser->getId() !== 7 || get_object_vars($legacyUser) !== []) {
        throw new RuntimeException('No se restauró correctamente el usuario de una sesión antigua.');
    }

    $privateId = "\0" . Usuario::class . "\0_id";
    $currentUser = unserialize(legacyObject(Usuario::class, [$privateId => 8]));
    if (!$currentUser instanceof Usuario || $currentUser->getId() !== 8) {
        throw new RuntimeException('No se restauró el ID privado del usuario.');
    }
    if (unserialize(serialize($currentUser))->getId() !== 8) {
        throw new RuntimeException('Falló la serialización nueva del usuario.');
    }

    $legacyMessage = unserialize(legacyObject(Msgbox::class, [
        'tipo' => 2, 'mensaje' => 'Mensaje anterior',
    ]));
    if (!$legacyMessage instanceof Msgbox || !str_contains($legacyMessage->getMsgbox(), 'Mensaje anterior')
        || get_object_vars($legacyMessage) !== []) {
        throw new RuntimeException('No se restauró correctamente el mensaje de una sesión antigua.');
    }

    $message = new Msgbox();
    $message->setMsgbox('Mensaje actual', 2);
    if (!str_contains(unserialize(serialize($message))->getMsgbox(), 'Mensaje actual')) {
        throw new RuntimeException('Falló la serialización nueva del mensaje.');
    }
} finally {
    restore_error_handler();
}

echo "Sesiones antiguas y nuevas restauradas sin avisos.\n";
