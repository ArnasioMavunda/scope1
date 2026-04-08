<?php
// SCOPE — Gerador de Relatórios
error_reporting(E_ALL);
ini_set('display_errors', 0);  // não mostrar no output HTML
ini_set('log_errors', 1);       // registar em error.log do XAMPP

header('Access-Control-Allow-Origin: *');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/funcoes.php';

$tipo    = $_GET['tipo']    ?? 'mensal';
$turmaId = (int)($_GET['turma'] ?? 1);
$formato = $_GET['formato'] ?? 'download';
$hoje    = date('Y-m-d');
$ano     = date('Y'); $mes = date('m');

switch ($tipo) {
    case 'semanal':
        $dow    = date('N');
        $inicio = $_GET['inicio'] ?? date('Y-m-d', strtotime("-".($dow-1)." days"));
        $fim    = $_GET['fim']    ?? date('Y-m-d', strtotime("+".(5-$dow)." days"));
        $titulo = "Relatório Semanal de Presenças";
        break;
    case 'trimestral':
        $trim   = ceil((int)$mes / 3);
        $mIni   = str_pad(($trim-1)*3+1, 2, '0', STR_PAD_LEFT);
        $inicio = $_GET['inicio'] ?? "{$ano}-{$mIni}-01";
        $fim    = $_GET['fim']    ?? $hoje;
        $titulo = "Relatório Trimestral — {$trim}º Trimestre";
        break;
    default:
        $inicio = $_GET['inicio'] ?? "{$ano}-{$mes}-01";
        $fim    = $_GET['fim']    ?? $hoje;
        $titulo = "Relatório Mensal de Presenças";
        break;
}

try {
$db = getDB();
} catch(Exception $e) {
    http_response_code(500);
    echo '<h2>Erro de ligação à base de dados</h2><p>'.$e->getMessage().'</p>';
    exit;
}

$stTurma = $db->prepare('SELECT * FROM turmas WHERE id = :id');
$stTurma->execute([':id' => $turmaId]);
$turma = $stTurma->fetch();
if (!$turma) { header('Content-Type: application/json'); echo json_encode(['status'=>'erro','mensagem'=>'Turma não encontrada.']); exit; }

try {
$stEstat = $db->prepare('
    SELECT a.nome, a.num_processo,
           COUNT(p.id) AS total,
           SUM(p.estado = "presente") AS presencas,
           SUM(p.estado = "atraso")   AS atrasos,
           SUM(p.estado IN ("ausente","falta_disciplinar")) AS ausencias,
           SUM(p.estado = "falta_disciplinar") AS faltas_disc,
           ROUND(SUM(p.estado IN ("presente","atraso")) / NULLIF(COUNT(p.id),0)*100,1) AS taxa
    FROM alunos a
    LEFT JOIN presencas p ON p.aluno_id = a.id AND p.data BETWEEN :inicio AND :fim
    WHERE a.turma_id = :turma AND a.ativo = 1
    GROUP BY a.id, a.nome, a.num_processo ORDER BY a.nome');
$stEstat->execute([':inicio'=>$inicio,':fim'=>$fim,':turma'=>$turmaId]);
$alunos = $stEstat->fetchAll();
} catch(Exception $e) {
    http_response_code(500);
    echo '<h2>Erro na consulta SQL</h2><p>'.htmlspecialchars($e->getMessage()).'</p>';
    exit;
}

$totP  = (int)array_sum(array_column($alunos,'presencas'))+(int)array_sum(array_column($alunos,'atrasos'));
$totAu = (int)array_sum(array_column($alunos,'ausencias'));
$totT  = (int)array_sum(array_column($alunos,'total'));
$taxaG = $totT > 0 ? round($totP/$totT*100,1) : 0;

// Registar exportação (opcional — não bloqueia se falhar)
try {
    // Verificar se utilizador id=1 existe antes de inserir
    $stU = $db->prepare('SELECT id FROM utilizadores LIMIT 1');
    $stU->execute();
    $uRow = $stU->fetch();
    if ($uRow) {
        // Usar tipo genérico se não for um dos ENUM válidos
        $tipoValido = in_array($tipo, ['semanal','mensal','trimestral']) ? $tipo : 'mensal';
        $db->prepare('INSERT INTO relatorios_exportados (gerado_por,turma_id,tipo,data_inicio,data_fim) VALUES (:u,:t,:ti,:i,:f)')
           ->execute([':u'=>$uRow['id'],':t'=>$turmaId,':ti'=>$tipoValido,':i'=>$inicio,':f'=>$fim]);
    }
} catch(Exception $e){ error_log('SCOPE relatorio INSERT: '.$e->getMessage()); }

function dtPT(string $d): string { $p=explode('-',$d); return count($p)===3?"{$p[2]}/{$p[1]}/{$p[0]}":$d; }
$inicioPT = dtPT($inicio); $fimPT = dtPT($fim); $geradoEm = date('d/m/Y H:i');

// Formato download → redireciona para inline (o browser imprime/guarda como PDF)
if ($formato !== 'inline') {
    $url = (isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']==='on'?'https':'http')
         . '://'.$_SERVER['HTTP_HOST']
         . strtok($_SERVER['REQUEST_URI'],'?')
         . '?tipo='.urlencode($tipo).'&inicio='.urlencode($inicio).'&fim='.urlencode($fim)
         . '&turma='.$turmaId.'&formato=inline';
    header('Location: '.$url); exit;
}

header('Content-Type: text/html; charset=utf-8');
?><!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<title><?=htmlspecialchars($titulo)?></title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:Arial,sans-serif;font-size:11px;color:#1e293b;background:#fff;padding:16px}
@media print{.no-print{display:none!important}@page{margin:12mm;size:A4}body{padding:0}}
.no-print{background:#1d4ed8;color:white;border:none;padding:9px 20px;border-radius:6px;font-size:12px;font-weight:700;cursor:pointer;margin-bottom:14px;display:block}
.header{display:flex;align-items:flex-start;justify-content:space-between;border-bottom:3px solid #1d4ed8;padding-bottom:10px;margin-bottom:14px}
.logo{width:38px;height:38px;border-radius:8px;background:#1d4ed8;display:inline-flex;align-items:center;justify-content:center;font-size:15px;font-weight:800;color:white;margin-right:10px;vertical-align:middle}
.school{font-size:13px;font-weight:800}.school-sub{font-size:9px;color:#64748b;margin-top:2px}
.rep-title{font-size:14px;font-weight:800;color:#1d4ed8;text-align:right}.rep-sub{font-size:9px;color:#64748b;text-align:right;margin-top:3px}
.stats{display:flex;gap:10px;margin-bottom:14px}
.stat{flex:1;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:8px;text-align:center}
.stat-n{font-size:20px;font-weight:800;line-height:1}.stat-l{font-size:9px;color:#64748b;text-transform:uppercase;margin-top:2px}
h2{font-size:11px;font-weight:800;color:#1e293b;margin:12px 0 6px;border-left:3px solid #1d4ed8;padding-left:7px;text-transform:uppercase;letter-spacing:.3px}
table{width:100%;border-collapse:collapse;margin-bottom:12px;font-size:10.5px}
th{background:#1d4ed8;color:white;font-size:9px;font-weight:700;padding:6px 7px;text-align:left;text-transform:uppercase}
td{padding:6px 7px;border-bottom:1px solid #f1f5f9}
tr:nth-child(even) td{background:#f8fafc}
.bar{display:flex;align-items:center;gap:4px}
.bar-bg{flex:1;height:7px;background:#e2e8f0;border-radius:4px;overflow:hidden}
.bar-fill{height:100%;border-radius:4px}
.g{background:#22c55e}.y{background:#eab308}.r{background:#ef4444}
.tg{color:#15803d;font-weight:700}.ty{color:#854d0e}.tr{color:#dc2626;font-weight:700}
.badge{display:inline-block;padding:2px 7px;border-radius:20px;font-size:9px;font-weight:700}
.bg{background:#dcfce7;color:#15803d}.by{background:#fef9c3;color:#854d0e}.br{background:#fee2e2;color:#dc2626}
.footer{font-size:9px;color:#94a3b8;border-top:1px solid #e2e8f0;padding-top:7px;display:flex;justify-content:space-between;margin-top:10px}
</style>
</head>
<body>
<button class="no-print" onclick="window.print()">🖨️ Guardar como PDF / Imprimir</button>
<div class="header">
  <div>
    <span class="logo">S</span>
    <span style="vertical-align:middle">
      <span class="school">CEPPH — Centro de Excelência</span><br>
      <span class="school-sub">SCOPE — Sistema de Controlo de Presenças Escolares</span>
    </span>
  </div>
  <div>
    <div class="rep-title"><?=htmlspecialchars($titulo)?></div>
    <div class="rep-sub">Turma: <?=htmlspecialchars($turma['nome']??'12ª CFB')?> &nbsp;|&nbsp; <?=$inicioPT?> a <?=$fimPT?></div>
    <div class="rep-sub">Gerado em <?=$geradoEm?></div>
  </div>
</div>

<div class="stats">
  <div class="stat"><div class="stat-n" style="color:#22c55e"><?=$totP?></div><div class="stat-l">Presenças</div></div>
  <div class="stat"><div class="stat-n" style="color:#ef4444"><?=$totAu?></div><div class="stat-l">Faltas</div></div>
  <div class="stat"><div class="stat-n" style="color:#1d4ed8"><?=$taxaG?>%</div><div class="stat-l">Taxa Média</div></div>
  <div class="stat"><div class="stat-n" style="color:#7c3aed"><?=count($alunos)?></div><div class="stat-l">Alunos</div></div>
</div>

<h2>Resumo por Aluno</h2>
<table>
<thead><tr><th>#</th><th>Nome</th><th>Nº Proc.</th><th>Pres.+Atrs.</th><th>Atrasos</th><th>Faltas</th><th>F. Disc.</th><th>Taxa Assiduidade</th><th>Estado</th></tr></thead>
<tbody>
<?php foreach($alunos as $i=>$a):
  $taxa=(float)($a['taxa']??0);
  $pA=(int)$a['presencas']+(int)$a['atrasos'];
  $cls=$taxa>=85?'g':($taxa>=70?'y':'r');
  $tcls=$taxa>=85?'tg':($taxa>=70?'ty':'tr');
  $est=$taxa>=85?'Regular':($taxa>=70?'Em Risco':'Crítico');
  $bcls=$taxa>=85?'bg':($taxa>=70?'by':'br');
?>
<tr>
  <td><?=$i+1?></td>
  <td style="font-weight:600"><?=htmlspecialchars($a['nome'])?></td>
  <td style="color:#64748b"><?=htmlspecialchars($a['num_processo']??'—')?></td>
  <td class="tg"><?=$pA?></td>
  <td class="ty"><?=(int)$a['atrasos']?></td>
  <td class="<?=(int)$a['ausencias']>3?'tr':''?>"><?=(int)$a['ausencias']?></td>
  <td style="color:#<?=(int)$a['faltas_disc']>0?'854d0e':'94a3b8'?>"><?=(int)$a['faltas_disc']?></td>
  <td>
    <div class="bar">
      <div class="bar-bg"><div class="bar-fill <?=$cls?>" style="width:<?=min($taxa,100)?>%"></div></div>
      <span class="<?=$tcls?>" style="min-width:34px;text-align:right"><?=$taxa?>%</span>
    </div>
  </td>
  <td><span class="badge <?=$bcls?>"><?=$est?></span></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<?php $risco=array_filter($alunos,fn($a)=>(float)($a['taxa']??0)<75); if(count($risco)): ?>
<h2>⚠️ Alunos em Risco de Reprovação</h2>
<table>
<thead><tr><th>Nome</th><th>Taxa</th><th>Faltas</th><th>Acção</th></tr></thead>
<tbody>
<?php foreach($risco as $a):$taxa=(float)($a['taxa']??0);?>
<tr>
  <td style="font-weight:700"><?=htmlspecialchars($a['nome'])?></td>
  <td class="tr"><?=$taxa?>%</td>
  <td class="tr"><?=(int)$a['ausencias']?> falta(s)</td>
  <td><span class="badge br">Contactar Encarregado</span></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>

<div class="footer">
  <span>SCOPE — <?=htmlspecialchars($turma['nome']??'12ª CFB')?> &nbsp;|&nbsp; CEPPH</span>
  <span>Gerado em <?=$geradoEm?></span>
</div>
</body>
</html>
