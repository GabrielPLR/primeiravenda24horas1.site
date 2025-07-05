<?php
session_start();

// Configurações iniciais
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Content-Type: application/json");
header("X-Content-Type-Options: nosniff");

// Função para respostas padronizadas
function jsonResponse($status, $message, $data = []) {
    http_response_code($status);
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

// Função para requisições HTTP com cURL
function makeApiRequest($url) {
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_FAILONERROR => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'User-Agent: Mozilla/5.0'
        ]
    ]);

    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        error_log('API Error: ' . curl_error($ch));
        return false;
    }
    
    curl_close($ch);
    return $response;
}

// Validação dos parâmetros
if (!isset($_GET['cpf'])) {
    jsonResponse(400, "CPF é obrigatório.");
}

$cpf = preg_replace('/\D/', '', $_GET['cpf']);
$cep = isset($_GET['cep']) ? preg_replace('/\D/', '', $_GET['cep']) : null;

// Validação do CPF
if (strlen($cpf) != 11 || !is_numeric($cpf)) {
    jsonResponse(400, "CPF inválido. Deve conter 11 dígitos numéricos.");
}

// Consulta API de CPF
$apiCpfUrl = "https://apela-api.tech?user=e141b8b3-5fad-47e4-b518-29c642ac1ce9&cpf=$cpf;
$cpfResponse = makeApiRequest($apiCpfUrl);

if ($cpfResponse === false) {
    jsonResponse(502, "Serviço de consulta de CPF temporariamente indisponível.");
}

$cpfData = json_decode($cpfResponse, true);

if (!isset($cpfData['status']) || $cpfData['status'] !== 200) {
    jsonResponse(404, $cpfData['message'] ?? "CPF não encontrado no sistema.");
}

// Armazena dados básicos na sessão
$_SESSION['dadosBasicos'] = [
    "nome" => $cpfData['nome'] ?? "Não informado",
    "cpf" => $cpfData['cpf'] ?? $cpf,
    "nascimento" => $cpfData['nascimento'] ?? "Não informado",
    "sexo" => $cpfData['sexo'] ?? "Não informado",
    "consulta_em" => date('Y-m-d H:i:s')
];

// Consulta CEP se fornecido e válido
if ($cep && strlen($cep) == 8) {
    $apiCepUrl = "https://viacep.com.br/ws/{$cep}/json/";
    $cepResponse = makeApiRequest($apiCepUrl);

    if ($cepResponse !== false) {
        $cepData = json_decode($cepResponse, true);
        
        if (!isset($cepData['erro'])) {
            $_SESSION['dadosBasicos']['endereco'] = [
                "cep" => $cepData['cep'] ?? $cep,
                "logradouro" => $cepData['logradouro'] ?? "Não informado",
                "bairro" => $cepData['bairro'] ?? "Não informado",
                "cidade" => $cepData['localidade'] ?? "Não informado",
                "uf" => $cepData['uf'] ?? "Não informado",
                "complemento" => $cepData['complemento'] ?? ""
            ];
        }
    }
}

// Registro de sucesso
jsonResponse(200, "Consulta realizada com sucesso.", [
    'nome' => $_SESSION['dadosBasicos']['nome'],
    'cpf' => $_SESSION['dadosBasicos']['cpf']
]);
