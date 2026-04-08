<?php
// ============================================================
//  SCOPE — API de Sessão
//  Ficheiro: api/sessao.php
//  GET  → devolve dados do utilizador actual
//  POST → logout
// ============================================================

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/funcoes.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// ── POST → Logout ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dados = json_decode(file_get_contents('php://input'), true);
    if (($dados['acao'] ?? '') === 'logout') {
        session_destroy();
        jsonResponse(['status' => 'ok', 'mensagem' => 'Sessão terminada.']);
    }
}

// ── GET → Dados da sessão actual ─────────────────────────────
if (empty($_SESSION['scope_user'])) {
    jsonResponse(['status' => 'erro', 'mensagem' => 'Não autenticado.'], 401);
}

jsonResponse(['status' => 'ok', 'utilizador' => $_SESSION['scope_user']]);
