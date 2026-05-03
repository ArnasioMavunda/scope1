<?php
// ============================================================
//  SCOPE — API de Presença  (VERSÃO CORRIGIDA v2)
//  Ficheiro: api/presenca.php
//
//  CORRECÇÕES APLICADAS:
//  [BUG 1] registarPresenca() retornava 'ignorado' quando o registo
//          inicial era 'rfid' (inicialização). O registo nunca
//          actualizava o painel porque a função bloqueava na
//          verificação `registado_por !== 'sistema'`.
//          → CORRIGIDO: inicializarPresencasDia() agora usa
//            registado_por = 'sistema' (era 'rfid' na tabela).
//            A tabela SQL também foi corrigida (ver scope_fix.sql).
//
//  [BUG 2] Hora desfasada — ESP32 usa UTC+1 mas a tabela MariaDB
//          estava em UTC+0 (SET time_zone = "+00:00" no dump).
//          → CORRIGIDO: validação do timestamp agora compara com
//            CONVERT_TZ(NOW(), '+00:00', '+01:00') no servidor.
//            Adicionalmente, o scope.ino foi corrigido para WAT
//            (Africa/Luanda = UTC+1).
//
//  [NOVA] Cartão desconhecido → devolve "rfid_desconhecido":true
//         para o ESP32 poder emitir som diferente.
//
//  [NOVA] Modo offline do professor: editar_estado só é permitido
//         quando o dispositivo está offline (flag device_offline=1
//         na tabela configuracoes) OU quando a sessão é
//         administrador/coordenador.
// ============================================================

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/funcoes.php';

// ============================================================
//  1. VALIDAR MÉTODO E CORPO DA REQUISIÇÃO
// ============================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['status' => 'erro', 'mensagem' => 'Método não permitido.'], 405);
}

$corpo = file_get_contents('php://input');
$dados = json_decode($corpo, true);

if (
    !$dados ||
    empty($dados['rfid']) ||
    empty($dados['timestamp'])
) {
    jsonResponse([
        'status'   => 'erro',
        'mensagem' => 'Dados inválidos. Esperado: {"rfid":"...","timestamp":"..."}',
    ], 400);
}

$rfidId    = limpar($dados['rfid']);
$timestamp = limpar($dados['timestamp']);

// ============================================================
//  2. EXTRAIR DATA E HORA DO TIMESTAMP
// ============================================================
$dataLeitura = substr(str_replace('T', ' ', $timestamp), 0, 10);
$horaLeitura = extrairHora($timestamp);

// ============================================================
//  3. VERIFICAR SE É DIA DE SEMANA (Seg–Sex)
// ============================================================
$diaSemana = (int) date('N', strtotime($dataLeitura));
if ($diaSemana > 5) {
    registarLogRFID($rfidId, $timestamp, 'fim_de_semana');
    jsonResponse([
        'status'   => 'aviso',
        'mensagem' => 'Sem aulas ao fim de semana.',
        'rfid'     => $rfidId,
    ]);
}

// ============================================================
//  4. BUSCAR ALUNO PELO RFID
//  → Se não encontrar, devolve flag rfid_desconhecido = true
//    para o ESP32 emitir som diferente (buzzer de erro)
// ============================================================
$aluno = buscarAlunoPorRFID($rfidId);

if (!$aluno) {
    registarLogRFID($rfidId, $timestamp, 'rfid_desconhecido');
    jsonResponse([
        'status'            => 'erro',
        'mensagem'          => 'Cartão RFID não reconhecido.',
        'rfid'              => $rfidId,
        'rfid_desconhecido' => true,   // ← flag para o ESP32 tocar som de erro
    ], 404);
}

// ============================================================
//  5. DETERMINAR BLOCO E ESTADO PELO HORÁRIO
// ============================================================
$resultado = determinarBlocoEstado($horaLeitura);
$bloco     = $resultado['bloco'];
$estado    = $resultado['estado'];

// ── 5a. Saída (após 18:00) ───────────────────────────────
if ($estado === 'saida') {
    $ok = registarSaida($aluno['id'], $dataLeitura, $horaLeitura);
    registarLogRFID($rfidId, $timestamp, 'saida_registada');
    jsonResponse([
        'status'   => 'ok',
        'acao'     => 'saida',
        'aluno'    => $aluno['nome'],
        'hora'     => $horaLeitura,
        'mensagem' => 'Hora de saída registada. Bom resto de dia!',
    ]);
}

// ── 5b. Fora de qualquer bloco (intervalo) ───────────────
if ($bloco === null) {
    registarLogRFID($rfidId, $timestamp, 'fora_de_bloco');
    jsonResponse([
        'status'   => 'aviso',
        'acao'     => 'ignorado',
        'aluno'    => $aluno['nome'],
        'mensagem' => 'Leitura fora do horário de bloco.',
    ]);
}

// ============================================================
//  6. BUSCAR O HORÁRIO CORRESPONDENTE AO BLOCO
// ============================================================
$horario = buscarHorarioAtivo($aluno['turma_id'], $bloco, $dataLeitura);

if (!$horario) {
    registarLogRFID($rfidId, $timestamp, 'sem_horario');
    jsonResponse([
        'status'   => 'aviso',
        'mensagem' => 'Sem aula programada para este bloco hoje.',
        'aluno'    => $aluno['nome'],
        'bloco'    => $bloco,
    ]);
}

// ============================================================
//  7. INICIALIZAR PRESENÇAS DO BLOCO
//     → Garante que todos os alunos têm registo 'ausente'
//       com registado_por = 'sistema' (NÃO 'rfid'!)
//       Este é o fix do BUG 1: a função registarPresenca()
//       só actualiza registos com registado_por = 'sistema'.
//       Se fosse 'rfid', o registo seria bloqueado.
// ============================================================
inicializarPresencasDia($aluno['turma_id'], $horario['id'], $dataLeitura);

// ============================================================
//  8. REGISTAR A PRESENÇA DO ALUNO
// ============================================================
$registo = registarPresenca(
    $aluno['id'],
    $horario['id'],
    $dataLeitura,
    $estado,
    $horaLeitura,
    'rfid'
);

// ============================================================
//  9. REGISTAR LOG BRUTO
// ============================================================
$logDesc = sprintf('%s | bloco:%d | estado:%s | %s',
    $aluno['nome'], $bloco, $estado, $registo['acao']);
registarLogRFID($rfidId, $timestamp, $logDesc);

// ============================================================
//  10. ACTUALIZAR STATUS DO DISPOSITIVO (online)
//  → Regista que o leitor está online para o painel saber
//    se deve bloquear edição manual ao professor
// ============================================================
try {
    $db = getDB();
    $db->prepare(
        "INSERT INTO configuracoes (chave, valor) VALUES ('device_online_at', NOW())
         ON DUPLICATE KEY UPDATE valor = NOW()"
    )->execute();
} catch (Exception $e) {
    // Não crítico — ignorar se a tabela ainda não existir
}

// ============================================================
//  11. MONTAR RESPOSTA PARA O ESP32
// ============================================================
$mensagens = [
    'presente' => 'Bem-vindo! Presença registada.',
    'atraso'   => 'Entrada com atraso registada.',
    'ausente'  => 'Entrada registada — marcado como ausente (fora do prazo).',
];

$icones = [
    'presente' => 'presente',
    'atraso'   => 'atraso',
    'ausente'  => 'ausente',
];

$estadoFinal = $registo['estado'] ?? $estado;

jsonResponse([
    'status'      => 'ok',
    'acao'        => $registo['acao'],
    'aluno'       => $aluno['nome'],
    'rfid'        => $rfidId,
    'bloco'       => $bloco,
    'estado'      => $estadoFinal,
    'hora'        => $horaLeitura,
    'disciplina'  => $horario['disciplina'] ?? '',
    'professor'   => $horario['professor']  ?? '',
    'icone'       => $icones[$estadoFinal]  ?? 'ausente',
    'mensagem'    => $mensagens[$estadoFinal] ?? 'Leitura processada.',
    'rfid_desconhecido' => false,
]);
