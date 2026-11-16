<?php
require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/admin_functions.php';

requireAdminLogin();

$pageTitle = 'Cadastros e Ediçao de Clientes | Painel Admin.';
$message = $_SESSION['messagem'] ?? null;
unset($_SESSION['message']);

$customer = [
    'id' => null,
    'name' => '',
    'email' => '',
    'phone' => '', 
    'cpf' => '',
    'date_of_birth' => '',
    'gender' => '',
    'address' => '',
    'neighborhood' =>'',
    'city' => '',
    'state' => '',
    'zip_code' => '',
    'country' => 'Brasil',
    'is_active' => 1,
];

//Se um ID de clientes for passado, tentamos  carregar  os dados  para ediçao
if (isset($_GET['id'])) {
    $customerId = (int)$_GET['id'];
    $existingCustomer = getCustumerById($customerId);
    if ($existingCustomer) {
        $customer = $existingCustomer;
        $pageTitle = "Editar Cliente - Painel Admin.";
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Cliente nao encontrado.'];
        redirect(getSiteUrl('admin/pages/customer.php'));
        exit;
    }
}

//Logica para salvar e atualizar o cliente.
if( $_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log ("============== Inicio do Processamento do Formulario de Cliente (DEGUB) ==============");

    $customerId = isset($_POST['customer_id']) && is_numeric($_POST['customer_id']) ? (int)$_POST['customer_id)'] : 0;
    $customerData = [
    'id' => $customerId> 0 ? $customerId : null,
    'name' => sanitizeInput($_POST['name'] ??  ''),
    'email' => sanitizeInput($_POST['email'] ??  ''),
    'phone' =>sanitizeInput($_POST['phone'] ?? ''), 
    'cpf' => sanitizeInput($_POST['cpf'] ?? ''),
    'date_of_birth' => sanitizeInput($_POST['date_of_birth'] ?? null),
    'gender' => sanitizeInput($_POST['gender'] ?? ''),
    'address' => sanitizeInput($_POST['address'] ?? '' ),
    'neighborhood' => sanitizeInput($_POST['neighborhood'] ?? ''),
    'city' => sanitizeInput($_POST['city'] ?? ''),
    'state' => sanitizeInput($_POST['state'] ?? ''),
    'zip_code' => sanitizeInput($_POST['zip_code'] ?? ''),
    'country' => sanitizeInput($_POST['contry'] ?? 'Brasil'),
    'is_active' => isset($_POST['is_active']) ? 1 : 0,
];

error_log("Dados recebidos do formulario do Cliente:");
error_log("Codigo do Cliente: " . ($customerData['id'] ? : 'NOVO'));
error_log("Nome: " . $customerData['name']);
error_log("E-Mail: " . $customerData['email']);

$savedCustomerId = saveCustomer($customerData);

if  ($savedCustomerId) {
    error_log("Cliente salvo com sucesso! Seu Codigo: " . $savedCustomerId);
    $_SESSION['message'] = ['type' => 'success', 'text' => 'Cliente salvo com sucesso.'];
    redirect(getSiteUrl('admin/pages/customers.php'));
    exit;
}else {
    error_log("Falha ao Salvar Cliente.");
    redirect(getSiteUrl('admin/pages/customer_form.php' . ($customerId > 0 ? '?id=' . $customerId : '')));
    exit;
}
error_log("============= Fimm do Processamento do Formulario de Cliente (DEGUB) ==============");
}

//Array contendo os Estados do Brasil

$brazilianStates = [
    'AC' => 'Acre', 'AL' => 'Alagoas', 'AP' => 'Amapá', 'AM' => 'Amazonas', 'BA' => 'Bahia',
    'CE' => 'Ceará', 'DF' => 'Distrito Federal', 'ES' => 'Espírito Santo', 'GO' => 'Goiás',
    'MA' => 'Maranhão', 'MT' => 'Mato Grosso', 'MS' => 'Mato Grosso do Sul', 'MG' => 'Minas Gerais',
    'PA' => 'Pará', 'PB' => 'Paraíba', 'PR' => 'Paraná', 'PE' => 'Pernambuco', 'PI' => 'Piauí',
    'RJ' => 'Rio de Janeiro', 'RN' => 'Rio Grande do Norte', 'RS' => 'Rio Grande do Sul',
    'RO' => 'Rondônia', 'RR' => 'Roraima', 'SC' => 'Santa Catarina', 'SP' => 'São Paulo',
    'SE' => 'Sergipe', 'TO' => 'Tocantins'
];

?>

<!DOCTYPE html>
<html lang="pt-BR">
<>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle;?></title>
    <link rel="stylesheet" href="<?php echo getSiteUrl('admin/assets/css/admin.css');?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</>
<body>
    <div class="admin-container">
        <?php include dirname(__DIR__) . '/includes/admin_siderbar.php'; ?>
        <div class="main-content">
            <div class="header">
                <h1><?php echo $pageTitle;?></h1>
                <form action="<?php echo getSiteUrl('admin/logout.php'); ?>" method="POST" style="display: inline;">
                    <button type="submit" class="logout-btn"><i class="fa-solid fa-arrow-right-from-bracket"></i>  Sair</button>
                </form>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo htmlspecialchars($message['type']); ?>">
                    <?php echo htmlspecialchars($message['text']); ?>
                </div>
            <?php endif; ?>

            <div class="product-form container">
                <form method="post">
                    <input type="hidden" name="customer_id" value="<?php echo htmlspecialchars($customer['id'] ?? ''); ?>">

                    <div class="form-group">
                        <label for="name">Nome Completo*</label>
                        <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($customer['name'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="email">E-Mail*</label>
                        <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($customer['email'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Celular</label>
                        <input type="text" name="phone" id="phone" value="<?php echo htmlspecialchars($customer['phone'] ?? ''); ?>" placeholder="(00) 0 0000-0000">
                    </div>
                    
                    <div class="form-group">
                        <label for="cpf">CPF</label>
                        <input type="text" name="cpf" id="cpf" value="<?php echo htmlspecialchars($customer['cpf'] ?? ''); ?>" placeholder="000.000.000-00" maxlength="14">
                    </div>
                
                    <div class="form-group">
                        <label for="date_of_birth">Data de Nascimento</label>
                        <input type="date" name="date_of_birth" id="date_of_birth" value="<?php echo htmlspecialchars($customer['date_of_birth'] ?? ''); ?>" placeholder="00/00/0000" maxlength="10">
                    </div>

                    <div class="form-group">
                        <label for="gender">Genero</label>
                        <select name="gender" id="gender">
                            <option value="">Selecione</option>
                            <option value="M" <?php echo (($customer['gender'] ?? '') === 'M') ? 'selected' : ''; ?>>Masculino</option>
                            <option value="F" <?php echo (($customer['gender'] ?? '') === 'F') ? 'selected' : ''; ?>>Feminino</option>
                            <option value="Outro" <?php echo (($customer['gender'] ?? '') === 'Outro') ? 'selected' : ''; ?>>Outro</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="zp_code">CEP</label>
                        <input type="text" name="zip_code" id="zip_code" value="<?php echo htmlspecialchars($customer['zip_code'] ?? ''); ?>" placeholder="00000-000" maxlength="9">
                    </div>

                    <div class="form-group">
                        <label for="address">Endereço</label>
                        <input type="text" name="address" id="addres" value="<?php echo htmlspecialchars($customer['address'] ?? ''); ?>" placeholder="Rau. Av...">
                    </div>

                    <div class="form-group">
                        <label for="city">Cidade</label>
                        <input type="text" name="city" id="city" value="<?php echo htmlspecialchars($customer['city'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="state">Estado</label>
                        <select name="state" id="state">
                            <option value="">Slecione</option>
                            <?php foreach ($brazilianStates as $uf => $stateName): ?>
                                <option value="<?php echo htmlspecialchars($uf); ?>" <?php echo (($customer['state'] ?? '') === $uf) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($stateName); ?>
                                </option>
                            <?php endforeach;?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="country">País</label>
                        <input type="text" id="country" name="country" value="<?php echo htmlspecialchars($customer['country'] ?? 'Brasil'); ?>">
                    </div>

                    <div class="form-group">
                        <input type="checkbox" id="is_active" name="is_active" value="1" <?php echo ($customer['is_active'] ?? 1) ? 'checked' : ''; ?>>
                        <label for="is_active">Cliente Ativo</label>
                    </div>

                    <div class="form-actions">
                        <button type="submit"><i class="fa-solid fa-save"></i> Salvar Cliente</button>
                        <a href="<?php echo getSiteUrl('admin/pages/customers.php'); ?>" class="cancel-btn"><i class="fa-solid fa-times"></i>  Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>