<?php
// ============================================================
//  SCOPE — API de Gestão de Alunos (Administrador)
//  Ficheiro: api/alunos_admin.php
//
//  Endpoints:
//  GET  ?acao=listar          → lista todos os alunos
//  POST ?acao=adicionar       → adiciona novo aluno
//  POST ?acao=editar          → edita aluno existente
//  POST ?acao=toggle_ativo    → activa/desactiva aluno
//  POST ?acao=associar_rfid   → associa cartão RFID ao aluno
// ============================================================

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/funcoes.php';

$acao   = $_GET['acao'] ?? '';
$metodo = $_SERVER['REQUEST_METHOD'];

// ── GET ─────────────────────────────────────────────────────
if ($metodo === 'GET') {

    if ($acao === 'listar') {
        $turmaId = (int) ($_GET['turma'] ?? 1);
        $db = getDB();
        $st = $db->prepare(
            'SELECT a.id, a.nome, a.num_processo, a.rfid_id,
                    a.ativo, a.criado_em,
                    t.nome AS turma_nome,
                    a.contacto_encarregado, a.email_encarregado
             FROM alunos a
             JOIN turmas t ON t.id = a.turma_id
             WHERE a.turma_id = :turma
             ORDER BY a.nome ASC'
        );
        $st->execute([':turma' => $turmaId]);
        $alunos = $st->fetchAll();
        jsonResponse(['status' => 'ok', 'alunos' => $alunos, 'total' => count($alunos)]);
    }

    if ($acao === 'listar_turmas') {
        $db = getDB();
        $st = $db->query('SELECT id, nome FROM turmas ORDER BY nome');
        jsonResponse(['status' => 'ok', 'turmas' => $st->fetchAll()]);
    }

    jsonResponse(['status' => 'erro', 'mensagem' => 'Ação desconhecida.'], 400);
}

// ── POST ────────────────────────────────────────────────────
if ($metodo === 'POST') {
    $dados = json_decode(file_get_contents('php://input'), true);

    // ── ADICIONAR NOVO ALUNO ──────────────────────────────
    if ($acao === 'adicionar') {
        $nome     = limpar($dados['nome']        ?? '');
        $proc     = limpar($dados['num_processo'] ?? '');
        $rfid     = limpar($dados['rfid_id']     ?? '');
        $turmaId  = (int) ($dados['turma_id']    ?? 1);
        $contacto = limpar($dados['contacto_encarregado'] ?? '');
        $email    = limpar($dados['email_encarregado']    ?? '');

        if (empty($nome)) {
            jsonResponse(['status' => 'erro', 'mensagem' => 'Nome do aluno é obrigatório.'], 400);
        }
        if (strlen($nome) < 3) {
            jsonResponse(['status' => 'erro', 'mensagem' => 'Nome demasiado curto.'], 400);
        }

        // Verificar RFID duplicado
        if (!empty($rfid)) {
            $db = getDB();
            $stChk = $db->prepare(
                'SELECT id, nome FROM alunos WHERE rfid_id = :rfid LIMIT 1'
            );
            $stChk->execute([':rfid' => $rfid]);
            $dup = $stChk->fetch();
            if ($dup) {
                jsonResponse([
                    'status'   => 'erro',
                    'mensagem' => 'O cartão RFID ' . $rfid . ' já está associado a ' . $dup['nome'] . '.',
                ], 409);
            }
        }

        // Verificar número de processo duplicado
        if (!empty($proc)) {
            $db = getDB();
            $stChk2 = $db->prepare(
                'SELECT id FROM alunos WHERE num_processo = :proc AND turma_id = :turma LIMIT 1'
            );
            $stChk2->execute([':proc' => $proc, ':turma' => $turmaId]);
            if ($stChk2->fetch()) {
                jsonResponse([
                    'status'   => 'erro',
                    'mensagem' => 'Já existe um aluno com o número de processo ' . $proc . ' nesta turma.',
                ], 409);
            }
        }

        $db = getDB();
        $st = $db->prepare(
            'INSERT INTO alunos
                 (nome, num_processo, rfid_id, turma_id,
                  contacto_encarregado, email_encarregado, ativo)
             VALUES
                 (:nome, :proc, :rfid, :turma, :contacto, :email, 1)'
        );
        $st->execute([
            ':nome'     => $nome,
            ':proc'     => !empty($proc) ? $proc : null,
            ':rfid'     => !empty($rfid) ? $rfid : null,
            ':turma'    => $turmaId,
            ':contacto' => !empty($contacto) ? $contacto : null,
            ':email'    => !empty($email)    ? $email    : null,
        ]);

        $novoId = (int) $db->lastInsertId();
        jsonResponse([
            'status'   => 'ok',
            'mensagem' => 'Aluno ' . $nome . ' adicionado com sucesso.',
            'id'       => $novoId,
            'nome'     => $nome,
        ]);
    }

    // ── EDITAR ALUNO ──────────────────────────────────────
    if ($acao === 'editar') {
        $id       = (int)   ($dados['id']            ?? 0);
        $nome     = limpar( $dados['nome']            ?? '');
        $proc     = limpar( $dados['num_processo']    ?? '');
        $rfid     = limpar( $dados['rfid_id']         ?? '');
        $contacto = limpar( $dados['contacto_encarregado'] ?? '');
        $email    = limpar( $dados['email_encarregado']    ?? '');

        if (!$id || empty($nome)) {
            jsonResponse(['status' => 'erro', 'mensagem' => 'ID e nome são obrigatórios.'], 400);
        }

        // Verificar RFID duplicado (excluindo o próprio aluno)
        if (!empty($rfid)) {
            $db = getDB();
            $stChk = $db->prepare(
                'SELECT id, nome FROM alunos WHERE rfid_id = :rfid AND id != :id LIMIT 1'
            );
            $stChk->execute([':rfid' => $rfid, ':id' => $id]);
            $dup = $stChk->fetch();
            if ($dup) {
                jsonResponse([
                    'status'   => 'erro',
                    'mensagem' => 'O cartão RFID ' . $rfid . ' já está associado a ' . $dup['nome'] . '.',
                ], 409);
            }
        }

        $db = getDB();
        $st = $db->prepare(
            'UPDATE alunos
             SET nome                  = :nome,
                 num_processo          = :proc,
                 rfid_id               = :rfid,
                 contacto_encarregado  = :contacto,
                 email_encarregado     = :email
             WHERE id = :id'
        );
        $st->execute([
            ':nome'     => $nome,
            ':proc'     => !empty($proc) ? $proc : null,
            ':rfid'     => !empty($rfid) ? $rfid : null,
            ':contacto' => !empty($contacto) ? $contacto : null,
            ':email'    => !empty($email)    ? $email    : null,
            ':id'       => $id,
        ]);

        jsonResponse([
            'status'   => 'ok',
            'mensagem' => 'Aluno actualizado com sucesso.',
            'alterados' => $st->rowCount(),
        ]);
    }

    // ── ACTIVAR / DESACTIVAR ALUNO ───────────────────────
    if ($acao === 'toggle_ativo') {
        $id = (int) ($dados['id'] ?? 0);
        if (!$id) {
            jsonResponse(['status' => 'erro', 'mensagem' => 'ID inválido.'], 400);
        }

        $db = getDB();
        $st = $db->prepare(
            'UPDATE alunos SET ativo = CASE WHEN ativo = 1 THEN 0 ELSE 1 END WHERE id = :id'
        );
        $st->execute([':id' => $id]);

        // Devolver estado novo
        $stGet = $db->prepare('SELECT ativo, nome FROM alunos WHERE id = :id');
        $stGet->execute([':id' => $id]);
        $aluno = $stGet->fetch();

        jsonResponse([
            'status'   => 'ok',
            'mensagem' => $aluno['nome'] . ' foi ' . ($aluno['ativo'] ? 'activado' : 'desactivado') . '.',
            'ativo'    => (bool) $aluno['ativo'],
        ]);
    }

    // ── ASSOCIAR RFID A ALUNO ────────────────────────────
    if ($acao === 'associar_rfid') {
        $alunoId = (int)   ($dados['aluno_id'] ?? 0);
        $rfid    = limpar( $dados['rfid_id']   ?? '');

        if (!$alunoId) {
            jsonResponse(['status' => 'erro', 'mensagem' => 'ID do aluno inválido.'], 400);
        }

        $db = getDB();

        // Verificar duplicado
        if (!empty($rfid)) {
            $stChk = $db->prepare(
                'SELECT id, nome FROM alunos WHERE rfid_id = :rfid AND id != :id LIMIT 1'
            );
            $stChk->execute([':rfid' => $rfid, ':id' => $alunoId]);
            $dup = $stChk->fetch();
            if ($dup) {
                jsonResponse([
                    'status'   => 'erro',
                    'mensagem' => 'Cartão já associado a ' . $dup['nome'] . '.',
                ], 409);
            }
        }

        $st = $db->prepare(
            'UPDATE alunos SET rfid_id = :rfid WHERE id = :id'
        );
        $st->execute([
            ':rfid' => !empty($rfid) ? $rfid : null,
            ':id'   => $alunoId,
        ]);

        jsonResponse([
            'status'   => 'ok',
            'mensagem' => empty($rfid)
                ? 'Cartão RFID removido.'
                : 'Cartão RFID ' . $rfid . ' associado com sucesso.',
        ]);
    }

    jsonResponse(['status' => 'erro', 'mensagem' => 'Ação POST desconhecida: ' . $acao], 400);
}

jsonResponse(['status' => 'erro', 'mensagem' => 'Método não suportado.'], 405);
