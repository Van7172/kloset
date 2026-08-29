<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="<?= IMGS ?>kloset-icon-acento.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Newsreader:ital,opsz,wght@0,6..72,400;0,6..72,500;1,6..72,400&family=Archivo:wght@400;500;600&family=Archivo+Narrow:wght@500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= ADMIN_CSS ?>kloset-admin.css">
<script>
  const URL_ADMIN = '<?= DW_PANEL ?>';
  // Tema antes del primer pintado para evitar el parpadeo
  (function () {
    var saved = null;
    try { saved = localStorage.getItem('kloset-theme'); } catch (e) {}
    var prefers = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    document.documentElement.setAttribute('data-theme', saved || (prefers ? 'dark' : 'light'));
  })();
</script>
