<?php
// ============================================================
//  SCOPE — API de Hora do Servidor
//  Ficheiro: api/hora.php
//
//  Propósito: O dashboard chama este endpoint para sincronizar
//  a hora exibida no painel com a hora real do servidor PHP,
//  evitando desfasamentos entre o relógio do browser do
//  professor e o servidor.
//
//  Também devolve o estado online/offline do dispositivo
//  RFID (ESP32) para o painel do professor saber se deve
//  permitir edição manual de presenças.
//
//  GET /scope/api/hora.php
//  Resposta:
//  {
//    "status": "ok",
//    "hora": "13:45:22",
//    "data": "2026-04-28",
//    "timestamp": 1745840722,
//    "bloco_ativo": 1,           // null se fora de horário
//    "device_online": true,      // false se ESP32 offline >2min
//    "device_ultima_leitura": "2026-04-28 13:44:10"
//  }
// ============================================================

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/funcoes.php';

// Hora actual do servidor
$horaServidor = date('H:i:s');
$dataServidor = date('Y-m-d');

// Determinar bloco activo
$blocoInfo = determinarBlocoEstado($horaServidor);
$blocoAtivo = $blocoInfo['bloco'];

// Verificar estado do dispositivo RFID
$deviceOnline = false;
$ultimaLeitura = null;

try {
    $db = getDB();
    $st = $db->prepare(
        "SELECT valor FROM configuracoes WHERE chave = 'device_online_at' LIMIT 1"
    );
    $st->execute();
    $row = $st->fetch();
    if ($row) {
        $ultimaLeitura = $row['valor'];
        $diff = time() - strtotime($row['valor']);
        $deviceOnline = ($diff < 120); // online se última leitura < 2 minutos
    }
} catch (Exception $e) {
    // tabela configuracoes pode não existir ainda
    $deviceOnline = false;
}

jsonResponse([
    'status'                 => 'ok',
    'hora'                   => $horaServidor,
    'data'                   => $dataServidor,
    'timestamp'              => time(),
    'bloco_ativo'            => $blocoAtivo,
    'device_online'          => $deviceOnline,
    'device_ultima_leitura'  => $ultimaLeitura,
]);
