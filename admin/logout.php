Copy

<?php
require_once '../includes/config.php';
require_once 'includes/admin_functions.php';

adminLogout();
redirect(getSiteUrl('admin/login.php'));
?>