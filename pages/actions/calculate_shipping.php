<?php
// pages/actions/calculate_shipping.php

// Garante que nenhum output seja enviado antes do JSON
ob_start();

// Desativa a exibição de erros para o navegador, mas os registra no log
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();

// ====================================================================
// CORREÇÃO DEFINITIVA DO ABSPATH PARA A ESTRUTURA pages/actions/
// __FILE__ é o caminho completo para este arquivo (calculate_shipping.php)
// dirname(__FILE__) -> C:\Apache24\htdocs\pc_gamer\pages\actions
// dirname(dirname(__FILE__)) -> C:\Apache24\htdocs\pc_gamer\pages
// dirname(dirname(dirname(__FILE__))) -> C:\Apache24\htdocs\pc_gamer (RAIZ DO PROJETO)
// ====================================================================
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR);
}

// ====================================================================
// INCLUSÃO DE ARQUIVOS CRÍTICOS
// Usamos try-catch para garantir que erros de inclusão sejam tratados
// e retornem JSON, não HTML.
// ====================================================================
try {
    // Verifica se os arquivos existem antes de incluir
    // Usamos DIRECTORY_SEPARATOR para garantir as barras corretas no Windows
    $configPath = ABSPATH . 'includes' . DIRECTORY_SEPARATOR . 'config.php';
    $functionsPath = ABSPATH . 'includes' . DIRECTORY_SEPARATOR . 'functions.php';
    $conexaoPath = ABSPATH . 'includes' . DIRECTORY_SEPARATOR . 'conexao.php';

    if (!file_exists($configPath)) {
        throw new Exception("Erro fatal: Arquivo de configuração não encontrado em: " . $configPath);
    }
    if (!file_exists($functionsPath)) {
        throw new Exception("Erro fatal: Arquivo de funções não encontrado em: " . $functionsPath);
    }
    if (!file_exists($conexaoPath)) {
        throw new Exception("Erro fatal: Arquivo de conexão não encontrado em: " . $conexaoPath);
    }

    require_once $configPath;
    require_once $functionsPath;
    require_once $conexaoPath;

} catch (Exception $e) {
    // Se houver um erro na inclusão, limpa o buffer e retorna JSON de erro
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    error_log("Erro crítico na inclusão de arquivos em calculate_shipping.php: " . $e->getMessage());
    exit;
}

// Descarta qualquer saída gerada pelos includes (warnings, notices, etc.)
$lixo = ob_get_clean();
if (!empty(trim($lixo))) {
    error_log("calculate_shipping.php - Saída indesejada capturada antes do JSON: " . strip_tags($lixo));
}

// A partir daqui, SOMENTE JSON será enviado
header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false, 'message' => 'Erro desconhecido.'];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método de requisição inválido.');
    }

    $cepDestino = preg_replace('/\D/', '', $_POST['cep'] ?? '');

    if (strlen($cepDestino) !== 8) {
        throw new Exception('CEP inválido. Insira 8 dígitos.');
    }

    // ====================================================================
    // SIMULAÇÃO DE FRETE
    // ====================================================================
    $response['success'] = true;
    $response['message'] = 'Opções de frete calculadas com sucesso.';
    $response['shipping_options'] = [
        [
            'id'                   => 'standard_shipping',
            'name'                 => 'Frete Padrão',
            'company'              => 'Transportadora Simples',
            'price'                => 25.50,
            'delivery_time'        => 7,
            'custom_delivery_time' => ['min' => 5, 'max' => 7]
        ],
        [
            'id'                   => 'express_shipping',
            'name'                 => 'Frete Expresso',
            'company'              => 'Transportadora Rápida',
            'price'                => 45.00,
            'delivery_time'        => 3,
            'custom_delivery_time' => ['min' => 2, 'max' => 3]
        ]
    ];

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    error_log("Erro em calculate_shipping.php: " . $e->getMessage());
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;   