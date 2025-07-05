<?php
session_start();
header('Content-Type: application/json');

if (!isset($_GET['cpf'])) {
    echo json_encode(["status" => 400, "message" => "CPF é obrigatório."]);
    exit;
}

$cpf = preg_replace('/\D/', '', $_GET['cpf']);
$cep = isset($_GET['cep']) ? preg_replace('/\D/', '', $_GET['cep']) : null;


$api_cpf_url = "https://apela-api.tech?user=e141b8b3-5fad-47e4-b518-29c642ac1ce9&cpf=NUMERO_DO_CPF";
$cpf_response = file_get_contents($api_cpf_url);

if ($cpf_response === false) {
    echo json_encode(["status" => 500, "message" => "Erro ao buscar dados do CPF."]);
    exit;
}

$cpf_data = json_decode($cpf_response, true);

if (!isset($cpf_data['status']) || $cpf_data['status'] !== 200) {
    echo json_encode(["status" => 404, "message" => "CPF não encontrado."]);
    exit;
}


$_SESSION['dadosBasicos'] = [
    "nome" => $cpf_data['nome'] ?? "Não informado",
    "cpf" => $cpf_data['cpf'] ?? "Não informado",
    "nascimento" => $cpf_data['nascimento'] ?? "Não informado",
    "sexo" => $cpf_data['sexo'] ?? "Não informado",
];

if ($cep) {
    $api_cep_url = "https://viacep.com.br/ws/$cep/json/";
    $cep_response = file_get_contents($api_cep_url);

    if ($cep_response !== false) {
        $cep_data = json_decode($cep_response, true);
        if (!isset($cep_data['erro'])) {
            $_SESSION['dadosBasicos'] += [
                "cep" => $cep_data['cep'] ?? "Não informado",
                "logradouro" => $cep_data['logradouro'] ?? "Não informado",
                "bairro" => $cep_data['bairro'] ?? "Não informado",
                "municipio" => $cep_data['localidade'] ?? "Não informado",
                "uf" => $cep_data['uf'] ?? "Não informado"
            ];
        }
    }
}

echo json_encode(["status" => 200, "message" => "Dados salvos."]);
