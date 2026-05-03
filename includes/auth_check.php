<?php
// ============================================================
//  SCOPE — Verificação de Sessão para Dashboards
//  Ficheiro: includes/auth_check.php
//
//  Como usar no topo de cada dashboard PHP (futuramente):
//    require_once 'includes/auth_check.php';
//    verificarAcesso('professor');
//
//  Nos dashboards HTML actuais (sem PHP) a verificação é
//  feita no cliente via JavaScript (ver abaixo).
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/funcoes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Verifica se o utilizador tem sessão activa com o perfil exigido.
 * Se não → redireciona para o login.
 *
 * @param string|array $perfis  perfil(is) permitidos
 */
function verificarAcesso($perfis = null): array {
    if (empty($_SESSION['scope_user'])) {
        header('Location: /scope/index.html');
        exit;
    }

    $user = $_SESSION['scope_user'];

    if ($perfis !== null) {
        $permitidos = is_array($perfis) ? $perfis : [$perfis];
        if (!in_array($user['perfil'], $permitidos)) {
            // Redirecionar para o dashboard correcto do perfil
            $destinos = [
                'professor'     => '/scope/dashboard_professor.html',
                'coordenador'   => '/scope/dashboard_coordenador.html',
                'administrador' => '/scope/dashboard_admin.html',
                
            ];
            header('Location: ' . ($destinos[$user['perfil']] ?? '/scope/index.html'));
            exit;
        }
    }

    return $user;
}

/**
 * API endpoint: GET /scope/api/sessao.php
 * Devolve dados do utilizador actual (usado pelos dashboards via fetch).
 */
if (basename($_SERVER['PHP_SELF']) === 'sessao.php') {
    header('Content-Type: application/json; charset=utf-8');
    if (empty($_SESSION['scope_user'])) {
        http_response_code(401);
        echo json_encode(['status' => 'erro', 'mensagem' => 'Sessão expirada.']);
    } else {
        echo json_encode(['status' => 'ok', 'utilizador' => $_SESSION['scope_user']]);
    }
    exit;
}
