<?php
// ============================================================
//  SCOPE — Funções de Negócio  (VERSÃO CORRIGIDA v2)
//  Ficheiro: includes/funcoes.php
//
//  CORRECÇÕES v2:
//  [BUG PRINCIPAL] inicializarPresencasDia() usava
//    registado_por = 'rfid' mas a tabela presencas tem
//    enum('rfid','professor') — NÃO tem o valor 'sistema'.
//    Isso fazia o INSERT IGNORE falhar silenciosamente ou
//    criar registos com valor inválido, e a função
//    registarPresenca() ao verificar `registado_por !== 'sistema'`
//    encontrava 'rfid' e bloqueava a actualização.
//
//  SOLUÇÃO: Adicionada a coluna 'sistema' ao enum da tabela
//  (ver scope_fix.sql) e inicializarPresencasDia() passa agora
//  'sistema' correctamente.
//
//  [FIX HORA] O dashboard mostrava hora desfasada porque
//  scopeRelogio() usa JavaScript local (correcto), mas a
//  comparação com a BD usava UTC. Adicionada função
//  getHoraServidor() que devolve a hora do servidor PHP
//  para o dashboard sincronizar.
// ============================================================

require_once __DIR__ . '/db.php';


// ============================================================
//  UTILITÁRIOS
// ============================================================

function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function limpar(string $valor): string {
    return trim(strip_tags($valor));
}

function extrairHora(string $datetime): string {
    $datetime = str_replace('T', ' ', trim($datetime));
    $partes   = explode(' ', $datetime);
    if (count($partes) === 1) {
        return $partes[0];
    }
    return $partes[1];
}

// ============================================================
//  HORA DO SERVIDOR (para sincronização com o painel web)
//  → Chamado por api/hora.php
// ============================================================
function getHoraServidor(): array {
    return [
        'hora'      => date('H:i:s'),
        'data'      => date('Y-m-d'),
        'timestamp' => time(),
        'timezone'  => date_default_timezone_get(),
    ];
}


// ============================================================
//  LÓGICA DE BLOCOS E ESTADOS
// ============================================================

function getBlocos(): array {
    return [
        1 => [
            'inicio'       => '13:00:00',
            'presente_ate' => '13:05:00',
            'atraso_ate'   => '13:15:00',
            'ausente_ate'  => '13:45:00',
            'fim_bloco'    => '14:30:00',
            'tempos'       => [1, 2],
        ],
        2 => [
            'inicio'       => '14:45:00',
            'presente_ate' => '14:50:00',
            'atraso_ate'   => '15:00:00',
            'ausente_ate'  => '15:30:00',
            'fim_bloco'    => '16:15:00',
            'tempos'       => [3, 4],
        ],
        3 => [
            'inicio'       => '16:30:00',
            'presente_ate' => '16:35:00',
            'atraso_ate'   => '16:45:00',
            'ausente_ate'  => '17:15:00',
            'fim_bloco'    => '18:00:00',
            'tempos'       => [5, 6],
        ],
    ];
}

function determinarBlocoEstado(string $hora): array {
    $blocos = getBlocos();

    if ($hora >= '18:00:00') {
        return ['bloco' => null, 'estado' => 'saida'];
    }

    foreach ($blocos as $num => $b) {
        if ($hora >= $b['inicio'] && $hora <= $b['fim_bloco']) {
            if ($hora <= $b['presente_ate']) {
                $estado = 'presente';
            } elseif ($hora <= $b['atraso_ate']) {
                $estado = 'atraso';
            } else {
                $estado = 'ausente';
            }
            return ['bloco' => $num, 'estado' => $estado];
        }
    }

    return ['bloco' => null, 'estado' => null];
}


// ============================================================
//  BUSCAR ALUNO PELO RFID
// ============================================================
function buscarAlunoPorRFID(string $rfidId): ?array {
    $db  = getDB();
    $sql = 'SELECT a.id, a.nome, a.num_processo, a.rfid_id, a.turma_id,
                   t.nome AS turma, t.turno
            FROM   alunos a
            JOIN   turmas t ON t.id = a.turma_id
            WHERE  a.rfid_id = :rfid
              AND  a.ativo   = 1
            LIMIT  1';
    $st  = $db->prepare($sql);
    $st->execute([':rfid' => $rfidId]);
    $row = $st->fetch();
    return $row ?: null;
}


// ============================================================
//  BUSCAR HORÁRIO ATIVO
// ============================================================
function buscarHorarioAtivo(int $turmaId, int $bloco, string $data): ?array {
    $db = getDB();

    $diaN  = (int) date('N', strtotime($data));
    $diaBD = ($diaN === 7) ? 1 : $diaN + 1;

    if ($diaBD === 1 || $diaBD === 7) {
        return null;
    }

    $sql = 'SELECT h.id, h.bloco, h.tempo, h.hora_inicio, h.hora_fim,
                   d.nome AS disciplina,
                   p.nome AS professor
            FROM   horario h
            JOIN   disciplinas d ON d.id = h.disciplina_id
            JOIN   professores p ON p.id = h.professor_id
            WHERE  h.turma_id   = :turma
              AND  h.bloco      = :bloco
              AND  h.dia_semana = :dia
              AND  h.tempo      = (
                       SELECT MIN(h2.tempo)
                       FROM   horario h2
                       WHERE  h2.turma_id   = h.turma_id
                         AND  h2.bloco      = h.bloco
                         AND  h2.dia_semana = h.dia_semana
                   )
            LIMIT  1';

    $st = $db->prepare($sql);
    $st->execute([
        ':turma' => $turmaId,
        ':bloco' => $bloco,
        ':dia'   => $diaBD,
    ]);
    $row = $st->fetch();
    return $row ?: null;
}


// ============================================================
//  INICIALIZAR PRESENÇAS DO DIA
//
//  ✅ FIX CRÍTICO: registado_por = 'sistema'
//  O enum da tabela presencas foi alargado para incluir
//  'sistema' (ver scope_fix.sql). Sem isto, o INSERT falhava
//  silenciosamente e registarPresenca() nunca actualizava
//  o registo porque encontrava registado_por='rfid' (inválido)
//  e bloqueava.
// ============================================================
function inicializarPresencasDia(int $turmaId, int $horarioId, string $data): void {
    $db = getDB();

    $stAlunos = $db->prepare(
        'SELECT id FROM alunos WHERE turma_id = :turma AND ativo = 1'
    );
    $stAlunos->execute([':turma' => $turmaId]);
    $alunos = $stAlunos->fetchAll();

    // INSERT IGNORE respeita a UNIQUE KEY (aluno_id, horario_id, data)
    // registado_por = 'sistema' → registarPresenca() vai sobrescrever
    $stIns = $db->prepare(
        'INSERT IGNORE INTO presencas
             (aluno_id, horario_id, data, estado, registado_por)
         VALUES
             (:aluno, :horario, :data, "ausente", "sistema")'
    );

    foreach ($alunos as $aluno) {
        $stIns->execute([
            ':aluno'   => $aluno['id'],
            ':horario' => $horarioId,
            ':data'    => $data,
        ]);
    }
}


// ============================================================
//  REGISTAR PRESENÇA DO ALUNO (via RFID)
//
//  Lógica:
//  - Se não existe registo → INSERT com estado correcto
//  - Se existe com registado_por='sistema' (ausente base)
//    → UPDATE com o estado do RFID ✅
//  - Se já existe com registado_por='rfid' ou 'professor'
//    → NÃO sobrescreve (preserva o primeiro registo real)
// ============================================================
function registarPresenca(
    int    $alunoId,
    int    $horarioId,
    string $data,
    string $estado,
    string $horaEntrada,
    string $registadoPor = 'rfid'
): array {
    $db = getDB();

    $stCheck = $db->prepare(
        'SELECT id, estado, registado_por
         FROM   presencas
         WHERE  aluno_id   = :aluno
           AND  horario_id = :horario
           AND  data       = :data
         LIMIT  1'
    );
    $stCheck->execute([
        ':aluno'   => $alunoId,
        ':horario' => $horarioId,
        ':data'    => $data,
    ]);
    $existente = $stCheck->fetch();

    // Se já existe registo real (rfid ou professor) → não sobrescreve
    if ($existente && $existente['registado_por'] !== 'sistema') {
        return [
            'acao'   => 'ignorado',
            'motivo' => 'Presença já registada por ' . $existente['registado_por'],
            'estado' => $existente['estado'],
        ];
    }

    // INSERT ou UPDATE do registo 'ausente' criado pelo sistema
    $sql = 'INSERT INTO presencas
                (aluno_id, horario_id, data, estado, hora_entrada, registado_por)
            VALUES
                (:aluno, :horario, :data, :estado, :entrada, :por)
            ON DUPLICATE KEY UPDATE
                estado        = VALUES(estado),
                hora_entrada  = VALUES(hora_entrada),
                registado_por = VALUES(registado_por),
                atualizado_em = NOW()';

    $st = $db->prepare($sql);
    $st->execute([
        ':aluno'   => $alunoId,
        ':horario' => $horarioId,
        ':data'    => $data,
        ':estado'  => $estado,
        ':entrada' => $horaEntrada,
        ':por'     => $registadoPor,
    ]);

    return [
        'acao'   => 'inserido',
        'estado' => $estado,
    ];
}


// ============================================================
//  REGISTAR HORA DE SAÍDA
// ============================================================
function registarSaida(int $alunoId, string $data, string $horaSaida): bool {
    $db = getDB();

    $stGet = $db->prepare(
        'SELECT p.id
         FROM   presencas p
         JOIN   horario h ON h.id = p.horario_id
         WHERE  p.aluno_id = :aluno
           AND  p.data     = :data
           AND  h.bloco    = 3
         ORDER BY p.id DESC
         LIMIT  1'
    );
    $stGet->execute([':aluno' => $alunoId, ':data' => $data]);
    $row = $stGet->fetch();

    if (!$row) {
        return false;
    }

    $stUpd = $db->prepare(
        'UPDATE presencas SET hora_saida = :saida WHERE id = :id'
    );
    $stUpd->execute([':saida' => $horaSaida, ':id' => $row['id']]);
    return $stUpd->rowCount() > 0;
}


// ============================================================
//  ALTERAR ESTADO MANUALMENTE (professor no dashboard)
//
//  NOVA LÓGICA: Só permite edição manual quando:
//  1. O dispositivo está offline (device_online_at > 2 minutos)
//  2. OU o utilizador é administrador/coordenador
//
//  O dashboard verifica o estado online antes de mostrar
//  o botão de edição ao professor.
// ============================================================
function alterarEstadoManual(
    int    $alunoId,
    int    $horarioId,
    string $data,
    string $novoEstado,
    string $observacao = ''
): bool {
    $db  = getDB();
    $sql = 'INSERT INTO presencas
                (aluno_id, horario_id, data, estado, registado_por, observacao)
            VALUES
                (:aluno, :horario, :data, :estado, "professor", :obs)
            ON DUPLICATE KEY UPDATE
                estado        = VALUES(estado),
                registado_por = "professor",
                observacao    = VALUES(observacao),
                atualizado_em = NOW()';
    $st = $db->prepare($sql);
    $st->execute([
        ':aluno'   => $alunoId,
        ':horario' => $horarioId,
        ':data'    => $data,
        ':estado'  => $novoEstado,
        ':obs'     => $observacao,
    ]);
    return $st->rowCount() > 0;
}

// ============================================================
//  VERIFICAR SE DISPOSITIVO ESTÁ ONLINE
//  → Considera offline se não houve leitura nos últimos 2 min
// ============================================================
function dispositivoOnline(): bool {
    try {
        $db  = getDB();
        $st  = $db->prepare(
            "SELECT valor FROM configuracoes WHERE chave = 'device_online_at' LIMIT 1"
        );
        $st->execute();
        $row = $st->fetch();
        if (!$row) return false;

        $diff = time() - strtotime($row['valor']);
        return $diff < 120; // online se última leitura < 2 minutos
    } catch (Exception $e) {
        return false;
    }
}


// ============================================================
//  PRESENÇAS DO DIA — para o professor (dashboard)
// ============================================================
function presencasDia(int $turmaId, string $data, int $bloco): array {
    $db = getDB();

    $diaN     = (int) date('N', strtotime($data));
    $diaMysql = ($diaN === 7) ? 1 : $diaN + 1;

    $stH = $db->prepare(
        'SELECT id FROM horario
         WHERE  turma_id   = :turma
           AND  bloco      = :bloco
           AND  dia_semana = :dia
         LIMIT  1'
    );
    $stH->execute([':turma' => $turmaId, ':bloco' => $bloco, ':dia' => $diaMysql]);
    $hRow      = $stH->fetch();
    $horarioId = $hRow ? (int) $hRow['id'] : 0;

    $sql = 'SELECT
                a.id,
                a.nome,
                a.num_processo,
                COALESCE(p.estado, "ausente") AS estado,
                p.hora_entrada,
                p.registado_por,
                :horario_id                   AS horario_id
            FROM  alunos a
            LEFT  JOIN presencas p
                ON  p.aluno_id   = a.id
                AND p.data       = :data
                AND p.horario_id IN (
                        SELECT id FROM horario
                        WHERE turma_id = :turma AND bloco = :bloco
                    )
            WHERE a.turma_id = :turma2
              AND a.ativo    = 1
            ORDER BY a.nome ASC';

    $st = $db->prepare($sql);
    $st->execute([
        ':data'       => $data,
        ':turma'      => $turmaId,
        ':bloco'      => $bloco,
        ':turma2'     => $turmaId,
        ':horario_id' => $horarioId,
    ]);
    return $st->fetchAll();
}


// ============================================================
//  ESTATÍSTICAS DA TURMA (coordenador / admin)
// ============================================================
function estatisticasTurma(int $turmaId, string $dataInicio, string $dataFim): array {
    $db  = getDB();
    $sql = 'SELECT
                a.id,
                a.nome,
                COUNT(p.id)                                          AS total,
                SUM(p.estado IN ("presente","atraso"))               AS presencas,
                SUM(p.estado = "ausente")                            AS ausencias,
                SUM(p.estado = "atraso")                             AS atrasos,
                SUM(p.estado = "falta_disciplinar")                  AS faltas_disc,
                ROUND(
                    SUM(p.estado IN ("presente","atraso"))
                    / NULLIF(COUNT(p.id), 0) * 100,
                1)                                                   AS taxa
            FROM  alunos a
            LEFT  JOIN presencas p
                ON  p.aluno_id = a.id
                AND p.data BETWEEN :inicio AND :fim
            WHERE a.turma_id = :turma
              AND a.ativo    = 1
            GROUP BY a.id, a.nome
            ORDER BY taxa ASC';

    $st = $db->prepare($sql);
    $st->execute([
        ':turma'  => $turmaId,
        ':inicio' => $dataInicio,
        ':fim'    => $dataFim,
    ]);
    return $st->fetchAll();
}


// ============================================================
//  PRESENÇAS DE UM ALUNO (portal do encarregado)
// ============================================================
function presencasAluno(int $alunoId, string $dataInicio, string $dataFim): array {
    $db  = getDB();
    $sql = 'SELECT
                p.data,
                h.bloco,
                h.hora_inicio,
                h.hora_fim,
                d.nome  AS disciplina,
                pr.nome AS professor,
                p.estado,
                p.hora_entrada,
                p.hora_saida
            FROM  presencas p
            JOIN  horario     h  ON h.id  = p.horario_id
            JOIN  disciplinas d  ON d.id  = h.disciplina_id
            JOIN  professores pr ON pr.id = h.professor_id
            WHERE p.aluno_id = :aluno
              AND p.data BETWEEN :inicio AND :fim
            ORDER BY p.data DESC, h.bloco ASC';

    $st = $db->prepare($sql);
    $st->execute([
        ':aluno'  => $alunoId,
        ':inicio' => $dataInicio,
        ':fim'    => $dataFim,
    ]);
    return $st->fetchAll();
}


// ============================================================
//  GUARDAR OCORRÊNCIA
// ============================================================
function guardarOcorrencia(
    int    $turmaId,
    int    $professorId,
    string $data,
    int    $tempo,
    string $descricao
): int {
    $db  = getDB();
    $sql = 'INSERT INTO ocorrencias (turma_id, professor_id, data, tempo, descricao)
            VALUES (:turma, :prof, :data, :tempo, :desc)';
    $st  = $db->prepare($sql);
    $st->execute([
        ':turma' => $turmaId,
        ':prof'  => $professorId,
        ':data'  => $data,
        ':tempo' => $tempo,
        ':desc'  => $descricao,
    ]);
    return (int) $db->lastInsertId();
}


// ============================================================
//  REGISTAR LOG RFID BRUTO
// ============================================================
function registarLogRFID(string $rfidId, string $timestamp, string $resposta = ''): int {
    $db  = getDB();
    $sql = 'INSERT INTO rfid_logs (rfid_id, timestamp, processado, resposta)
            VALUES (:rfid, :ts, 1, :resp)';
    $st  = $db->prepare($sql);
    $st->execute([
        ':rfid' => $rfidId,
        ':ts'   => $timestamp,
        ':resp' => $resposta,
    ]);
    return (int) $db->lastInsertId();
}


// ============================================================
//  ADICIONAR ALUNO (usado pelo painel admin)
// ============================================================
function adicionarAluno(
    string $nome,
    string $numProcesso,
    string $rfidId,
    int    $turmaId
): array {
    $db = getDB();

    // Verificar se RFID já está em uso
    if (!empty($rfidId)) {
        $stChk = $db->prepare(
            'SELECT id, nome FROM alunos WHERE rfid_id = :rfid AND ativo = 1 LIMIT 1'
        );
        $stChk->execute([':rfid' => $rfidId]);
        $duplicado = $stChk->fetch();
        if ($duplicado) {
            return [
                'sucesso'  => false,
                'mensagem' => 'Este cartão RFID já está associado a ' . $duplicado['nome'] . '.',
            ];
        }
    }

    $st = $db->prepare(
        'INSERT INTO alunos (nome, num_processo, rfid_id, turma_id, ativo)
         VALUES (:nome, :proc, :rfid, :turma, 1)'
    );
    $st->execute([
        ':nome'  => $nome,
        ':proc'  => $numProcesso ?: null,
        ':rfid'  => !empty($rfidId) ? $rfidId : null,
        ':turma' => $turmaId,
    ]);

    $novoId = (int) $db->lastInsertId();

    return [
        'sucesso' => true,
        'id'      => $novoId,
        'nome'    => $nome,
    ];
}
