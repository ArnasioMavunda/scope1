<?php
// ============================================================
//  SCOPE — Autenticação e Sessões
//  Ficheiro: includes/auth.php
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/funcoes.php';

session_start();

// ============================================================
//  LOGIN
// ============================================================
function login(string $email, string $senha): array {
    $db  = getDB();
    $sql = 'SELECT * FROM utilizadores
            WHERE email = :email AND ativo = 1
            LIMIT 1';
    $st  = $db->prepare($sql);
    $st->execute([':email' => $email]);
    $user = $st->fetch();

    if (!$user || !password_verify($senha, $user['senha'])) {
        return ['sucesso' => false, 'mensagem' => 'Email ou senha incorrectos.'];
    }

    // Actualizar último login
    $db->prepare('UPDATE utilizadores SET ultimo_login = NOW() WHERE id = :id')
       ->execute([':id' => $user['id']]);

    // Guardar sessão
    $_SESSION['scope_user'] = [
        'id'            => $user['id'],
        'nome'          => $user['nome'],
        'email'         => $user['email'],
        'perfil'        => $user['perfil'],
        'referencia_id' => $user['referencia_id'],
    ];

    return [
        'sucesso'       => true,
        'perfil'        => $user['perfil'],
        'nome'          => $user['nome'],
        'referencia_id' => $user['referencia_id'],
    ];
}

// ============================================================
//  LOGOUT
// ============================================================
function logout(): void {
    session_destroy();
}

// ============================================================
//  VERIFICAR SE ESTÁ AUTENTICADO
// ============================================================
function autenticado(): bool {
    return isset($_SESSION['scope_user']);
}

// ============================================================
//  OBTER UTILIZADOR DA SESSÃO
// ============================================================
function utilizadorAtual(): ?array {
    return $_SESSION['scope_user'] ?? null;
}

// ============================================================
//  VERIFICAR PERFIL EXIGIDO (proteger páginas)
//  Uso: exigirPerfil('professor')
//       exigirPerfil(['coordenador','administrador'])
// ============================================================
function exigirPerfil($perfis): void {
    if (!autenticado()) {
        header('Location: /scope/index.html');
        exit;
    }
    $user = utilizadorAtual();
    $perfisArray = is_array($perfis) ? $perfis : [$perfis];
    if (!in_array($user['perfil'], $perfisArray)) {
        http_response_code(403);
        die(json_encode([
            'status'   => 'erro',
            'mensagem' => 'Acesso não autorizado para este perfil.',
        ]));
    }
}
