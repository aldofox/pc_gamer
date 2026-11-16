<?php
//Busca a funçao getSiteUrl()
require_once dirname(__DIR__, 2) . '/includes/functions.php';
?>

<link rel="stylesheet" href="<?php echo getSiteUrl('admin/assets/css/admin.css');?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<div class="sidebar">
    <h2><i class="fa-brands fa-whmcs"></i> Painel Admin</h2>
    <a href="<?php echo getSiteUrl('admin/dashboard.php'); ?>"><i class="fa-slab fa-regular fa-chart-bar"></i> Dashboard</a>
    <a href="<?php echo getSiteUrl('admin/pages/products.php'); ?>"><i class="fa-solid fa-gifts"></i> Produtos</a>
    <a href="<?php echo getSiteUrl('admin/pages/customers.php'); ?>"><i class="fa-solid fa-users"></i> Clientes</a>
    <a href="<?php echo getSiteUrl('admin/pages/users.php'); ?>"><i class="fas fa-user-plus"></i> Usuarios</a>
    <a href="<?php echo getSiteUrl('admin/pages/categories.php'); ?>"><i class="fa-solid fa-tag"></i> Categorias</a>
    <a href="<?php echo getSiteUrl('admin/pages/orders.php'); ?>"><i class="fa-solid fa-shop"></i> Pedidos</a>
    <a href="<?php echo getSiteUrl('admin/pages/settings.php'); ?>"><i class="fa-solid fa-id-card"></i> Configurações</a>
    <a href="<?php echo getSiteUrl('admin/logout.php');?>" style="background-color:#dc3545; margin-top:20px"><i class="fa-solid fa-arrow-right-from-bracket"></i> Sair</a>
    
</div>