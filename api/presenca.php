<?php
// ============================================================
//  SCOPE — API de Presença
//  Ficheiro: api/presenca.php
//  Recebe POST JSON do ESP32:
//    {"rfid":"4682816","timestamp":"2026-03-10 13:03:22"}
//  Devolve JSON com resultado da operação.
// ============================================================

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Responder a preflight CORS
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
$timestamp = limpar($dados['timestamp']);   // "2026-03-10 13:03:22"

// ============================================================
//  2. EXTRAIR DATA E HORA DO TIMESTAMP
// ============================================================
$dataLeitura = substr($timestamp, 0, 10);   // "2026-03-10"
$horaLeitura = extrairHora($timestamp);     // "13:03:22"

// ============================================================
//  3. VERIFICAR SE É DIA DE SEMANA (Seg–Sex)
// ============================================================
$diaSemana = (int) date('N', strtotime($dataLeitura)); // 1=Seg … 7=Dom
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
// ============================================================
$aluno = buscarAlunoPorRFID($rfidId);

if (!$aluno) {
    registarLogRFID($rfidId, $timestamp, 'rfid_desconhecido');
    jsonResponse([
        'status'   => 'erro',
        'mensagem' => 'Cartão RFID não reconhecido.',
        'rfid'     => $rfidId,
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
        'status'  => 'ok',
        'acao'    => 'saida',
        'aluno'   => $aluno['nome'],
        'hora'    => $horaLeitura,
        'mensagem'=> 'Hora de saída registada. Bom resto de dia!',
    ]);
}

// ── 5b. Fora de qualquer bloco (intervalo) ───────────────
if ($bloco === null) {
    registarLogRFID($rfidId, $timestamp, 'fora_de_bloco');
    jsonResponse([
        'status'  => 'aviso',
        'acao'    => 'ignorado',
        'aluno'   => $aluno['nome'],
        'mensagem'=> 'Leitura fora do horário de bloco (intervalo?).',
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
//  7. INICIALIZAR PRESENÇAS DO BLOCO (se ainda não feito)
//     Garante que todos os alunos têm registo 'ausente' base
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
//  10. MONTAR RESPOSTA PARA O ESP32
// ============================================================
$mensagens = [
    'presente' => 'Bem-vindo! Presença registada.',
    'atraso'   => 'Entrada com atraso registada.',
    'ausente'  => 'Entrada registada — marcado como ausente (fora do prazo).',
];

$icones = [
    'presente' => '✅',
    'atraso'   => '⏱',
    'ausente'  => '❌',
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
    'icone'       => $icones[$estadoFinal]  ?? '📋',
    'mensagem'    => $mensagens[$estadoFinal] ?? 'Leitura processada.',
]);
