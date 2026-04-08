<?php
// ============================================================
//  SCOPE — API de Login
//  Ficheiro: api/login.php
//  POST JSON: {"email":"...","senha":"..."}
// ============================================================

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/funcoes.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['status' => 'erro', 'mensagem' => 'Método não permitido.'], 405);
}

$dados = json_decode(file_get_contents('php://input'), true);

if (empty($dados['email']) || empty($dados['senha'])) {
    jsonResponse(['status' => 'erro', 'mensagem' => 'Email e senha são obrigatórios.'], 400);
}

$resultado = login(limpar($dados['email']), $dados['senha']);

if ($resultado['sucesso']) {
    // Definir redirecionamento conforme perfil
    $redirects = [
        'professor'     => 'dashboard_professor.html',
        'coordenador'   => 'dashboard_coordenador.html',
        'administrador' => 'dashboard_admin.html',
        'encarregado'   => 'portal_encarregado.html',
    ];
    jsonResponse([
        'status'        => 'ok',
        'perfil'        => $resultado['perfil'],
        'nome'          => $resultado['nome'],
        'referencia_id' => $resultado['referencia_id'] ?? null,
        'redirect'      => $redirects[$resultado['perfil']] ?? 'index.html',
    ]);
} else {
    jsonResponse(['status' => 'erro', 'mensagem' => $resultado['mensagem']], 401);
}
