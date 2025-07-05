<?php
session_start();

// Headers para CORS e JSON
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header('Content-Type: application/json');

// Função para fazer requisições API com cURL
function fetchAPI($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Apenas para desenvolvimento
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        error_log('cURL Error: ' . curl_error($ch));
        return false;
    }
    
    curl_close($ch);
    return $response;
}

// Validação do CPF
if (!isset($_GET['cpf'])) {
    http_response_code(400);
    echo json_encode(["status" => 400, "message" => "CPF é obrigatório."]);
    exit;
}

$cpf = preg_replace('/\D/', '', $_GET['cpf']);
$cep = isset($_GET['cep']) ? preg_replace('/\D/', '', $_GET['cep']) : null;

// Validação básica do CPF
if (strlen($cpf) != 11 || !is_numeric($cpf)) {
    http_response_code(400);
    echo json_encode(["status" => 400, "message" => "CPF inválido. Deve conter 11 dígitos."]);
    exit;
}

// Consulta API de CPF
$api_cpf_url = "https://apela-api.tech?user=e141b8b3-5fad-47e4-b518-29c642ac1ce9&cpf=" . $cpf;
$cpf_response = fetchAPI($api_cpf_url);

if ($cpf_response === false) {
    http_response_code(502);
    echo json_encode([
        "status" => 502,
        "message" => "Serviço de consulta de CPF indisponível no momento."
    ]);
    exit;
}

$cpf_data = json_decode($cpf_response, true);

if (!isset($cpf_data['status']) || $cpf_data['status'] !== 200) {
    http_response_code(404);
    echo json_encode([
        "status" => 404,
        "message" => $cpf_data['message'] ?? "CPF não encontrado no sistema."
    ]);
    exit;
}

// Armazena dados na sessão
$_SESSION['dadosBasicos'] = [
    "nome" => $cpf_data['nome'] ?? "Não informado",
    "cpf" => $cpf_data['cpf'] ?? $cpf,
    "nascimento" => $cpf_data['nascimento'] ?? "Não informado",
    "sexo" => $cpf_data['sexo'] ?? "Não informado",
];

// Consulta CEP se fornecido
if ($cep && strlen($cep) == 8) {
    $api_cep_url = "https://viacep.com.br/ws/$cep/json/";
    $cep_response = fetchAPI($api_cep_url);

    if ($cep_response !== false) {
        $cep_data = json_decode($cep_response, true);
        if (!isset($cep_data['erro'])) {
            $_SESSION['dadosBasicos'] += [
                "cep" => $cep_data['cep'] ?? $cep,
                "logradouro" => $cep_data['logradouro'] ?? "Não informado",
                "bairro" => $cpf_data['bairro'] ?? "Não informado",
                "municipio" => $cep_data['localidade'] ?? "Não informado",
                "uf" => $cep_data['uf'] ?? "Não informado"
            ];
        }
    }
}

// Resposta de sucesso
http_response_code(200);
echo json_encode([
    "status" => 200,
    "message" => "Dados salvos com sucesso.",
    "data" => [
        "nome" => $_SESSION['dadosBasicos']['nome'],
        "cpf" => $_SESSION['dadosBasicos']['cpf']
    ]
]);
