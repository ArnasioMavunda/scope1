<?php
// ============================================================
//  SCOPE — Funções de Negócio
//  Ficheiro: includes/funcoes.php
// ============================================================

require_once __DIR__ . '/db.php';

// ============================================================
//  LÓGICA DE BLOCOS E ESTADOS
// ============================================================

/**
 * Define os 3 blocos de presença do turno da tarde.
 * Cada bloco cobre 2 tempos (90 min).
 * O aluno só precisa passar o cartão UMA vez por bloco.
 *
 * Bloco 1 → Tempos 1+2  → início oficial: 13:00
 * Bloco 2 → Tempos 3+4  → início oficial: 14:45
 * Bloco 3 → Tempos 5+6  → início oficial: 16:30
 * Saída   →              → a partir de:   18:00
 */
function getBlocos(): array {
    return [
        1 => [
            'inicio'         => '13:00:00',
            'presente_ate'   => '13:05:00',   // ≤ 5 min → Presente
            'atraso_ate'     => '13:15:00',   // 6–15 min → Atraso
            'ausente_ate'    => '13:45:00',   // 16–45 min → Ausente (pode passar mas conta falta)
            'fim_bloco'      => '14:30:00',
            'tempos'         => [1, 2]
        ],
        2 => [
            'inicio'         => '14:45:00',
            'presente_ate'   => '14:50:00',
            'atraso_ate'     => '15:00:00',
            'ausente_ate'    => '15:30:00',
            'fim_bloco'      => '16:15:00',
            'tempos'         => [3, 4]
        ],
        3 => [
            'inicio'         => '16:30:00',
            'presente_ate'   => '16:35:00',
            'atraso_ate'     => '16:45:00',
            'ausente_ate'    => '17:15:00',
            'fim_bloco'      => '18:00:00',
            'tempos'         => [5, 6]
        ],
    ];
}

/**
 * Dado um horário HH:MM:SS, devolve qual bloco está ativo
 * e qual o estado a atribuir ao aluno.
 *
 * Retorna array com:
 *   bloco  → 1, 2, 3 ou null
 *   estado → 'presente' | 'atraso' | 'ausente' | 'saida' | null
 */
function determinarBlocoEstado(string $hora): array {
    $blocos = getBlocos();

    // Saída: após as 18:00
    if ($hora >= '18:00:00') {
        return ['bloco' => null, 'estado' => 'saida'];
    }

    foreach ($blocos as $num => $b) {
        // Dentro da janela do bloco (do início até ao fim do bloco)
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

    // Fora de qualquer bloco (ex: intervalo 14:30–14:45 ou 16:15–16:30)
    return ['bloco' => null, 'estado' => null];
}

// ============================================================
//  BUSCAR ALUNO PELO RFID
// ============================================================
function buscarAlunoPorRFID(string $rfidId): ?array {
    $db  = getDB();
    $sql = 'SELECT a.*, t.nome AS turma, t.turno
            FROM alunos a
            JOIN turmas t ON t.id = a.turma_id
            WHERE a.rfid_id = :rfid AND a.ativo = 1
            LIMIT 1';
    $st  = $db->prepare($sql);
    $st->execute([':rfid' => $rfidId]);
    $row = $st->fetch();
    return $row ?: null;
}

// ============================================================
//  BUSCAR HORÁRIO ATIVO PARA O ALUNO NESTE MOMENTO
// ============================================================
function buscarHorarioAtivo(int $turmaId, int $bloco, string $data): ?array {
    $db  = getDB();
    // date('N') → 1=Seg...7=Dom (ISO)
    // Tabela dia_semana usa MySQL DAYOFWEEK: 1=Dom,2=Seg,...,7=Sab
    $diaN  = (int) date('N', strtotime($data));
    $diaBD = $diaN === 7 ? 1 : $diaN + 1;  // Seg→2, Ter→3, ..., Sab→7, Dom→1
    if ($diaBD == 1 || $diaBD == 7) {
        return null; // Domingo ou Sábado — sem aulas
    }

    $sql = 'SELECT h.*, d.nome AS disciplina, p.nome AS professor
            FROM horario h
            JOIN disciplinas d ON d.id = h.disciplina_id
            JOIN professores p ON p.id = h.professor_id
            WHERE h.turma_id   = :turma
              AND h.bloco      = :bloco
              AND h.dia_semana = :dia
              AND h.tempo      = (SELECT MIN(tempo) FROM horario
                                  WHERE turma_id=:turma2
                                    AND bloco=:bloco2
                                    AND dia_semana=:dia2)
            LIMIT 1';
    $st = $db->prepare($sql);
    $st->execute([
        ':turma'  => $turmaId,
        ':bloco'  => $bloco,
        ':dia'    => $diaBD,
        ':turma2' => $turmaId,
        ':bloco2' => $bloco,
        ':dia2'   => $diaBD,
    ]);
    $row = $st->fetch();
    return $row ?: null;
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
//  REGISTAR OU ATUALIZAR PRESENÇA
// ============================================================
/**
 * Insere ou actualiza a presença de um aluno num dado horário/data.
 * Se já existe registo para esse aluno/horário/data:
 *   - Se a passagem é de saída → actualiza hora_saida
 *   - Se não → ignora (não substitui registo anterior mais favorável)
 */
function registarPresenca(
    int    $alunoId,
    int    $horarioId,
    string $data,
    string $estado,
    string $horaEntrada,
    string $registadoPor = 'rfid'
): array {
    $db = getDB();

    // Verifica se já existe registo
    $sqlCheck = 'SELECT id, estado FROM presencas
                 WHERE aluno_id = :aluno
                   AND horario_id = :horario
                   AND data = :data
                 LIMIT 1';
    $st = $db->prepare($sqlCheck);
    $st->execute([
        ':aluno'   => $alunoId,
        ':horario' => $horarioId,
        ':data'    => $data,
    ]);
    $existente = $st->fetch();

    if ($existente) {
        // Já existe — não sobrescreve (ex: presente não vira atraso)
        return [
            'acao'   => 'ignorado',
            'motivo' => 'Presença já registada para este bloco.',
            'estado' => $existente['estado'],
        ];
    }

    // Inserir novo registo
    $sqlIns = 'INSERT INTO presencas
                 (aluno_id, horario_id, data, estado, hora_entrada, registado_por)
               VALUES
                 (:aluno, :horario, :data, :estado, :entrada, :por)';
    $st = $db->prepare($sqlIns);
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
    $db  = getDB();
    // Actualiza o registo do último bloco do dia (bloco 3 / tempos 5+6)
    $sql = 'UPDATE presencas p
            JOIN horario h ON h.id = p.horario_id
            SET p.hora_saida = :saida
            WHERE p.aluno_id = :aluno
              AND p.data     = :data
              AND h.bloco    = 3
            ORDER BY p.id DESC
            LIMIT 1';
    // PDO não suporta UPDATE com JOIN + LIMIT directamente em algumas versões
    // Usar subquery em alternativa:
    $sql2 = 'UPDATE presencas
             SET hora_saida = :saida
             WHERE aluno_id = :aluno
               AND data     = :data
               AND horario_id IN (
                   SELECT id FROM horario WHERE bloco = 3
               )
             ORDER BY id DESC
             LIMIT 1';
    $st = $db->prepare($sql2);
    $st->execute([
        ':saida' => $horaSaida,
        ':aluno' => $alunoId,
        ':data'  => $data,
    ]);
    return $st->rowCount() > 0;
}

// ============================================================
//  INICIALIZAR PRESENÇAS DO DIA (marcar todos como ausente)
// ============================================================
/**
 * Deve ser chamado no início de cada bloco (cron ou primeira
 * leitura RFID do dia) para garantir que todos os alunos
 * têm registo — os que não passarem ficam como 'ausente'.
 */
function inicializarPresencasDia(int $turmaId, int $horarioId, string $data): void {
    $db = getDB();

    // Buscar todos os alunos activos da turma
    $sqlAlunos = 'SELECT id FROM alunos WHERE turma_id = :turma AND ativo = 1';
    $st = $db->prepare($sqlAlunos);
    $st->execute([':turma' => $turmaId]);
    $alunos = $st->fetchAll();

    $sqlIns = 'INSERT IGNORE INTO presencas
                 (aluno_id, horario_id, data, estado, registado_por)
               VALUES (:aluno, :horario, :data, "ausente", "rfid")';
    $stIns = $db->prepare($sqlIns);

    foreach ($alunos as $aluno) {
        $stIns->execute([
            ':aluno'   => $aluno['id'],
            ':horario' => $horarioId,
            ':data'    => $data,
        ]);
    }
}

// ============================================================
//  ESTATÍSTICAS DA TURMA (para o dashboard coordenador/admin)
// ============================================================
function estatisticasTurma(int $turmaId, string $dataInicio, string $dataFim): array {
    $db = getDB();

    $sql = 'SELECT
              a.id,
              a.nome,
              COUNT(p.id)                                     AS total,
              SUM(p.estado IN ("presente","atraso"))          AS presencas,
              SUM(p.estado = "ausente")                       AS ausencias,
              SUM(p.estado = "falta_disciplinar")             AS faltas_disc,
              ROUND(SUM(p.estado IN ("presente","atraso"))
                    / NULLIF(COUNT(p.id),0) * 100, 1)        AS taxa
            FROM alunos a
            LEFT JOIN presencas p ON p.aluno_id = a.id
              AND p.data BETWEEN :inicio AND :fim
            WHERE a.turma_id = :turma
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
//  PRESENÇAS DE UM ALUNO (para portal do encarregado)
// ============================================================
function presencasAluno(int $alunoId, string $dataInicio, string $dataFim): array {
    $db = getDB();

    $sql = 'SELECT
              p.data,
              h.bloco,
              h.hora_inicio,
              h.hora_fim,
              d.nome   AS disciplina,
              pr.nome  AS professor,
              p.estado,
              p.hora_entrada,
              p.hora_saida
            FROM presencas p
            JOIN horario     h  ON h.id  = p.horario_id
            JOIN disciplinas d  ON d.id  = h.disciplina_id
            JOIN professores pr ON pr.id = h.professor_id
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
//  PRESENÇAS DO DIA — para o professor ver a sua turma
// ============================================================
function presencasDia(int $turmaId, string $data, int $bloco): array {
    $db = getDB();

    // Buscar o horario_id do bloco no dia da semana correcto
    // date('N') → 1=Seg...7=Dom; tabela usa MySQL DAYOFWEEK: 1=Dom,2=Seg,...,7=Sab
    $diaN     = (int) date('N', strtotime($data));
    $diaMysql = $diaN === 7 ? 1 : $diaN + 1;

    $stH = $db->prepare(
        'SELECT id FROM horario
         WHERE turma_id = :turma AND bloco = :bloco AND dia_semana = :dia
         LIMIT 1'
    );
    $stH->execute([':turma' => $turmaId, ':bloco' => $bloco, ':dia' => $diaMysql]);
    $horarioRow = $stH->fetch();
    $horarioId  = $horarioRow ? (int)$horarioRow['id'] : null;

    $sql = 'SELECT
              a.id,
              a.nome,
              a.num_processo,
              COALESCE(p.estado, "ausente")   AS estado,
              p.hora_entrada,
              p.registado_por,
              :horario_id                     AS horario_id
            FROM alunos a
            LEFT JOIN presencas p ON p.aluno_id = a.id
              AND p.data = :data
              AND p.horario_id IN (
                  SELECT id FROM horario
                  WHERE turma_id = :turma AND bloco = :bloco
              )
            WHERE a.turma_id = :turma2
              AND a.ativo = 1
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
//  ALTERAR ESTADO MANUALMENTE (professor no dashboard)
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
//  UTILITÁRIOS
// ============================================================

/** Devolve JSON e termina a execução */
function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Valida e sanitiza uma string */
function limpar(string $valor): string {
    return trim(htmlspecialchars($valor, ENT_QUOTES, 'UTF-8'));
}

/** Extrai apenas HH:MM:SS de um datetime ou time string */
function extrairHora(string $datetime): string {
    if (strlen($datetime) > 8) {
        return substr($datetime, 11, 8); // "2026-03-10 13:03:22" → "13:03:22"
    }
    return $datetime;
}
