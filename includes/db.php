<?php
// ============================================================
//  SCOPE — Conexão à Base de Dados  (VERSÃO CORRIGIDA v2)
//  Ficheiro: includes/db.php
//
//  CORRECÇÕES v2:
//  [FIX HORA] Define o fuso horário da sessão PHP para
//  Africa/Luanda (WAT = UTC+1) e envia SET time_zone='+01:00'
//  ao MariaDB logo após ligar. Sem isto, o servidor devolvia
//  horas em UTC+0 enquanto o ESP32 enviava em UTC+1, causando
//  desfasamento de 1 hora nos registos de presença e no
//  painel web.
// ============================================================

define('DB_HOST',    'localhost');
define('DB_NAME',    'scope');
define('DB_USER',    'root');
define('DB_PASS',    '');            // XAMPP padrão: sem senha
define('DB_CHARSET', 'utf8mb4');

// [FIX HORA] Fuso horário do PHP = WAT (Africa/Luanda = UTC+1)
// Deve ser definido aqui para garantir que date() devolve
// sempre a hora correcta em TODOS os ficheiros PHP.
date_default_timezone_set('Africa/Luanda');

function getDB(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST, DB_NAME, DB_CHARSET
        );
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

            // [FIX HORA] Forçar fuso horário WAT na sessão MariaDB.
            // Isto garante que NOW(), CURTIME() e comparações de
            // datas no SQL usam UTC+1 (Angola), não UTC+0.
            $pdo->exec("SET time_zone = '+01:00'");

        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode([
                'status'   => 'erro',
                'mensagem' => 'Falha na conexão à base de dados.',
                // Em produção: remover 'detalhe' para não expor credenciais
                'detalhe'  => $e->getMessage(),
            ]));
        }
    }

    return $pdo;
}
