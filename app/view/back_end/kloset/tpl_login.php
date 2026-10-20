<!DOCTYPE html>
<html lang="es">
<head>
    <?php include ADMIN_INCLUDE . 'inc_head.php'; ?>
    <title>Acceso | <?= htmlspecialchars(NOMBRE_SITIO) ?></title>
</head>
<body>
<div class="kl-login">
    <div class="kl-login-box">
        <img src="<?= IMGS ?>kloset-logo-transparente.png" alt="Kloset">
        <div class="kl-kicker">Panel administrativo</div>
        <h1>Iniciar sesión</h1>
        <p>Ingresa con tu correo y contraseña.</p>

        <?php if (!empty($login_error)): ?>
            <div class="kl-error" role="alert"><?= htmlspecialchars(is_string($login_error) ? $login_error : 'Error de acceso') ?></div>
        <?php endif; ?>

        <form method="post" action="<?= DW_PANEL ?>sign-in">
            <label class="kl-label" for="login-correo">correo electrónico</label>
            <input class="kl-field" type="email" id="login-correo" name="username" required autocomplete="username" autofocus>

            <label class="kl-label" for="login-pass">contraseña</label>
            <input class="kl-field" type="password" id="login-pass" name="password" required autocomplete="current-password">

            <button class="kl-btn" type="submit">Entrar</button>
        </form>
    </div>
</div>
<?php include ADMIN_INCLUDE . 'inc_feedback.php'; ?>
<?php include ADMIN_INCLUDE . 'inc_bottom.php'; ?>
</body>
</html>
