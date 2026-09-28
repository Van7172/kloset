<script src="<?= ADMIN_JS ?>admin.js?v=<?= substr(hash_file('sha256', ADMIN_TEMPLATE_HOST . 'assets/js/admin.js'), 0, 16) ?>"></script>
<?php if (!empty($view_js)): ?>
<script src="<?= ADMIN_JS ?>views/<?= htmlspecialchars($view_js) ?>?v=<?= substr(hash_file('sha256', ADMIN_TEMPLATE_HOST . 'assets/js/views/' . $view_js), 0, 16) ?>"></script>
<?php endif; ?>
