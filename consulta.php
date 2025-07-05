<?php
header('Content-Type: application/json');
session_start();

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

// Verifica se o CPF foi enviado
if (!isset($_GET['cpf'])) {
    jsonResponse(400, "CPF é obrigatório.");
}

// Limpa e valida o CPF
$cpf = preg_replace('/\D/', '', $_GET['cpf']);
if (strlen($cpf) !== 11 || !is_numeric($cpf)) {
    jsonResponse(400, "CPF inválido. Deve conter 11 dígitos numéricos.");
}

// Configuração da API
$apiUser = "e141b8b3-5fad-47e4-b518-29c642ac1ce9";
$apiUrl = "https://apela-api.tech?user={$apiUser}&cpf={$cpf}";

// Requisição com cURL
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $apiUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_HTTPHEADER => ['Accept: application/json']
]);

$response = curl_exec($ch);
$error = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Tratamento de erros
if ($error) {
    jsonResponse(500, "Erro ao conectar com a API: {$error}");
}

if ($httpCode !== 200) {
    jsonResponse(502, "API retornou erro HTTP {$httpCode}");
}

$apiData = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    jsonResponse(500, "Erro ao decodificar resposta da API");
}

if (!isset($apiData['status']) || $apiData['status'] !== 200) {
    jsonResponse(404, $apiData['message'] ?? "CPF não encontrado na base de dados");
}

// Armazena os dados na sessão
$_SESSION['dadosBasicos'] = [
    "nome" => $apiData['nome'] ?? "Não informado",
    "cpf" => $apiData['cpf'] ?? $cpf,
    "nascimento" => $apiData['nascimento'] ?? "Não informado",
    "sexo" => $apiData['sexo'] ?? "Não informado"
];

// Consulta CEP se fornecido
$cep = isset($_GET['cep']) ? preg_replace('/\D/', '', $_GET['cep']) : null;
if ($cep && strlen($cep) === 8) {
    $cepUrl = "https://viacep.com.br/ws/$cep/json/";
    $cepResponse = file_get_contents($cepUrl);
    
    if ($cepResponse !== false) {
        $cepData = json_decode($cepResponse, true);
        if (!isset($cepData['erro'])) {
            $_SESSION['dadosBasicos']['endereco'] = [
                "cep" => $cepData['cep'] ?? $cep,
                "logradouro" => $cepData['logradouro'] ?? "Não informado",
                "bairro" => $cepData['bairro'] ?? "Não informado",
                "cidade" => $cepData['localidade'] ?? "Não informado",
                "uf" => $cepData['uf'] ?? "Não informado"
            ];
        }
    }
}

// Resposta de sucesso
jsonResponse(200, "Consulta realizada com sucesso", [
    'nome' => $_SESSION['dadosBasicos']['nome']
]);
