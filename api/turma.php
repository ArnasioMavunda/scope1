<?php
// ============================================================
//  SCOPE — API de Dados da Turma  (VERSÃO CORRIGIDA v2)
//  Ficheiro: api/turma.php
//
//  NOVIDADES v2:
//  [NOVA] GET ?acao=device_status → estado online/offline do ESP32
//  [NOVA] POST ?acao=editar_estado agora verifica se o dispositivo
//         está offline antes de permitir edição pelo professor.
//         Admin e coordenador podem sempre editar.
//  [NOVA] GET ?acao=hora_servidor → hora PHP sincronizada
// ============================================================

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/funcoes.php';

$acao   = $_GET['acao']   ?? '';
$metodo = $_SERVER['REQUEST_METHOD'];

// ── GET ─────────────────────────────────────────────────────

if ($metodo === 'GET') {

    // ── presencas_dia ────────────────────────────────────────
    if ($acao === 'presencas_dia') {
        $turmaId = (int)   ($_GET['turma'] ?? 1);
        $data    = limpar( $_GET['data']   ?? date('Y-m-d'));
        $bloco   = (int)   ($_GET['bloco'] ?? 1);

        $lista = presencasDia($turmaId, $data, $bloco);

        $presentes = 0; $atrasos = 0; $ausentes = 0;
        foreach ($lista as $a) {
            if ($a['estado'] === 'presente') $presentes++;
            elseif ($a['estado'] === 'atraso') $atrasos++;
            else $ausentes++;
        }
        $total = count($lista);
        $taxa  = $total > 0 ? round(($presentes + $atrasos) / $total * 100, 1) : 0;

        jsonResponse([
            'status'    => 'ok',
            'data'      => $data,
            'bloco'     => $bloco,
            'alunos'    => $lista,
            'totais'    => [
                'total'     => $total,
                'presentes' => $presentes,
                'atrasos'   => $atrasos,
                'ausentes'  => $ausentes,
                'taxa'      => $taxa,
            ],
        ]);
    }

    // ── aula_atual ───────────────────────────────────────────
    elseif ($acao === 'aula_atual') {
        $horaAgora = date('H:i:s');
        $blocoInfo = determinarBlocoEstado($horaAgora);
        $turmaId   = (int) ($_GET['turma'] ?? 1);

        if ($blocoInfo['bloco']) {
            $horario = buscarHorarioAtivo($turmaId, $blocoInfo['bloco'], date('Y-m-d'));
        } else {
            $horario = null;
        }

        jsonResponse([
            'status'     => 'ok',
            'hora_atual' => $horaAgora,
            'bloco'      => $blocoInfo['bloco'],
            'em_aula'    => $horario !== null,
            'horario'    => $horario,
        ]);
    }

    // ── estatisticas ─────────────────────────────────────────
    elseif ($acao === 'estatisticas') {
        $turmaId = (int) ($_GET['turma'] ?? 1);
        $inicio  = limpar($_GET['inicio'] ?? date('Y-m-01'));
        $fim     = limpar($_GET['fim']    ?? date('Y-m-d'));

        $stats = estatisticasTurma($turmaId, $inicio, $fim);
        jsonResponse(['status' => 'ok', 'dados' => $stats, 'periodo' => ['inicio'=>$inicio,'fim'=>$fim]]);
    }

    // ── alunos ───────────────────────────────────────────────
    elseif ($acao === 'alunos') {
        $turmaId = (int) ($_GET['turma'] ?? 1);
        $db      = getDB();
        $st      = $db->prepare(
            'SELECT id, nome, num_processo, rfid_id
             FROM alunos WHERE turma_id = :turma AND ativo = 1
             ORDER BY nome'
        );
        $st->execute([':turma' => $turmaId]);
        jsonResponse(['status' => 'ok', 'alunos' => $st->fetchAll()]);
    }

    // ── horario ──────────────────────────────────────────────
    elseif ($acao === 'horario') {
        $turmaId = (int) ($_GET['turma'] ?? 1);
        $db      = getDB();
        $st      = $db->prepare(
            'SELECT h.dia_semana, h.tempo, h.bloco, h.hora_inicio, h.hora_fim,
                    d.nome AS disciplina, p.nome AS professor, t.sala
             FROM horario h
             JOIN disciplinas d ON d.id = h.disciplina_id
             JOIN professores p ON p.id = h.professor_id
             JOIN turmas      t ON t.id = h.turma_id
             WHERE h.turma_id = :turma
             ORDER BY h.dia_semana, h.tempo'
        );
        $st->execute([':turma' => $turmaId]);
        jsonResponse(['status' => 'ok', 'horario' => $st->fetchAll()]);
    }

    // ── presencas_aluno (encarregado) ────────────────────────
    elseif ($acao === 'presencas_aluno') {
        $alunoId = (int)   ($_GET['aluno'] ?? 0);
        $inicio  = limpar( $_GET['inicio'] ?? date('Y-m-01'));
        $fim     = limpar( $_GET['fim']    ?? date('Y-m-d'));

        if (!$alunoId) {
            jsonResponse(['status' => 'erro', 'mensagem' => 'ID de aluno inválido.'], 400);
        }
        $lista = presencasAluno($alunoId, $inicio, $fim);
        jsonResponse(['status' => 'ok', 'presencas' => $lista]);
    }

    // ── device_status (NOVO) — estado online/offline ESP32 ───
    elseif ($acao === 'device_status') {
        try {
            $db = getDB();
            $st = $db->prepare(
                "SELECT valor FROM configuracoes WHERE chave = 'device_online_at' LIMIT 1"
            );
            $st->execute();
            $row = $st->fetch();
            $online = false;
            $ultimaLeitura = null;
            $segundosDesde = null;

            if ($row) {
                $ultimaLeitura = $row['valor'];
                $segundosDesde = time() - strtotime($row['valor']);
                $online = ($segundosDesde < 120);
            }

            jsonResponse([
                'status'          => 'ok',
                'device_online'   => $online,
                'ultima_leitura'  => $ultimaLeitura,
                'segundos_desde'  => $segundosDesde,
                'hora_servidor'   => date('H:i:s'),
                'data_servidor'   => date('Y-m-d'),
            ]);
        } catch (Exception $e) {
            jsonResponse([
                'status'        => 'ok',
                'device_online' => false,
                'hora_servidor' => date('H:i:s'),
                'data_servidor' => date('Y-m-d'),
            ]);
        }
    }

    // ── hora_servidor (NOVO) — sincronização de hora ─────────
    elseif ($acao === 'hora_servidor') {
        $horaServidor = date('H:i:s');
        $blocoInfo    = determinarBlocoEstado($horaServidor);
        jsonResponse([
            'status'      => 'ok',
            'hora'        => $horaServidor,
            'data'        => date('Y-m-d'),
            'timestamp'   => time(),
            'bloco_ativo' => $blocoInfo['bloco'],
        ]);
    }

    else {
        jsonResponse(['status' => 'erro', 'mensagem' => 'Ação GET desconhecida: ' . $acao], 400);
    }
}

// ── POST ────────────────────────────────────────────────────

elseif ($metodo === 'POST') {
    $dados = json_decode(file_get_contents('php://input'), true);

    // ── editar_estado (professor) ─────────────────────────────
    if ($acao === 'editar_estado') {
        $alunoId    = (int)   ($dados['aluno_id']   ?? 0);
        $horarioId  = (int)   ($dados['horario_id'] ?? 0);
        $data       = limpar( $dados['data']        ?? date('Y-m-d'));
        $estado     = limpar( $dados['estado']      ?? '');
        $obs        = limpar( $dados['observacao']  ?? '');
        $perfilUser = limpar( $dados['perfil']      ?? 'professor');

        $estadosValidos = ['presente','atraso','ausente','falta_disciplinar'];
        if (!$alunoId || !$horarioId || !in_array($estado, $estadosValidos)) {
            jsonResponse(['status' => 'erro', 'mensagem' => 'Dados inválidos.'], 400);
        }

        // ── NOVA LÓGICA: professor só pode editar quando offline ──
        // Admin e coordenador podem sempre editar
        if ($perfilUser === 'professor') {
            $deviceOnline = dispositivoOnline();
            if ($deviceOnline) {
                jsonResponse([
                    'status'   => 'bloqueado',
                    'mensagem' => 'O dispositivo RFID está online. A edição manual pelo professor só é permitida quando o leitor está offline. Aguarda a leitura automática do cartão.',
                    'device_online' => true,
                ], 403);
            }
        }

        $ok = alterarEstadoManual($alunoId, $horarioId, $data, $estado, $obs);
        jsonResponse([
            'status'  => $ok ? 'ok' : 'erro',
            'mensagem' => $ok ? 'Estado actualizado.' : 'Erro ao actualizar.',
        ]);
    }

    // ── listar_ocorrencias ───────────────────────────────────
    elseif ($acao === 'listar_ocorrencias') {
        $turmaId = (int) ($_GET['turma'] ?? 1);
        $db      = getDB();
        $st      = $db->prepare(
            'SELECT o.id, o.data, o.tempo, o.descricao,
                    p.nome AS professor, t.nome AS turma
             FROM ocorrencias o
             JOIN professores p ON p.id = o.professor_id
             JOIN turmas      t ON t.id = o.turma_id
             WHERE o.turma_id = :turma
             ORDER BY o.data DESC, o.tempo DESC
             LIMIT 50'
        );
        $st->execute([':turma' => $turmaId]);
        jsonResponse(['status' => 'ok', 'ocorrencias' => $st->fetchAll()]);
    }

    // ── ocorrencia ───────────────────────────────────────────
    elseif ($acao === 'ocorrencia') {
        $turmaId     = (int)   ($dados['turma_id']     ?? 1);
        $professorId = (int)   ($dados['professor_id'] ?? 0);
        $data        = limpar( $dados['data']          ?? date('Y-m-d'));
        $tempo       = (int)   ($dados['tempo']        ?? 1);
        $descricao   = limpar( $dados['descricao']     ?? '');

        if (!$descricao) {
            jsonResponse(['status' => 'erro', 'mensagem' => 'Descrição obrigatória.'], 400);
        }

        $id = guardarOcorrencia($turmaId, $professorId, $data, $tempo, $descricao);
        jsonResponse(['status' => 'ok', 'id' => $id, 'mensagem' => 'Ocorrência guardada.']);
    }

    else {
        jsonResponse(['status' => 'erro', 'mensagem' => 'Ação POST desconhecida: ' . $acao], 400);
    }
}

else {
    jsonResponse(['status' => 'erro', 'mensagem' => 'Método não suportado.'], 405);
}
