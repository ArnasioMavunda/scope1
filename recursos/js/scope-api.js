// ============================================================
//  SCOPE — scope-api.js  (VERSÃO CORRIGIDA v2)
//  Ficheiro central de comunicação com a API PHP.
//  Incluir em TODOS os dashboards:
//    <script src="recursos/js/scope-api.js"></script>
//
//  CORRECÇÕES v2:
//  [FIX 1] Hora do relógio sincronizada com o servidor PHP.
//          scopeRelogio() agora ajusta o offset entre a hora
//          do browser e a do servidor, exibindo sempre a hora
//          correcta no painel mesmo que o computador do professor
//          tenha o relógio desajustado.
//
//  [FIX 2] Polling mais robusto: o listener 'presencas_atualizadas'
//          agora é chamado em CADA poll (não só quando há nova
//          leitura RFID), garantindo que a lista de alunos
//          actualiza mesmo que o aluno já tenha passado o cartão
//          antes do professor abrir o painel.
//
//  [FIX 3] SCOPE.deviceOnline() — verifica se o ESP32 está
//          online. O dashboard do professor usa isto para mostrar
//          ou esconder o botão de edição manual.
//          Regra: professor só pode editar manualmente quando
//          o dispositivo está offline.
//
//  [FIX 4] SCOPE.adicionarAluno() — novo método para o admin
//          adicionar alunos pelo painel web.
// ============================================================

const SCOPE = (() => {

  // ── Configuração ────────────────────────────────────────
  const BASE          = '/scope/api';
  const TURMA_ID      = 1;
  const POLL_INTERVAL = 8000;   // 8 segundos

  // ── Estado global ────────────────────────────────────────
  let _pollTimer      = null;
  let _ultimoRegisto  = null;
  let _callbacks      = {};
  let _serverOffset   = 0;      // diferença em ms entre browser e servidor
  let _deviceOnline   = false;  // estado do ESP32

  // ──────────────────────────────────────────────────────────
  //  UTILITÁRIOS
  // ──────────────────────────────────────────────────────────

  async function api(endpoint, opcoes = {}) {
    try {
      const resp = await fetch(`${BASE}/${endpoint}`, {
        headers: { 'Content-Type': 'application/json' },
        ...opcoes,
      });
      const data = await resp.json();
      return data;
    } catch (err) {
      console.error('[SCOPE API] Erro:', err);
      return { status: 'erro', mensagem: 'Sem ligação ao servidor.' };
    }
  }

  function get(endpoint, params = {}) {
    const qs = new URLSearchParams(params).toString();
    return api(qs ? `${endpoint}?${qs}` : endpoint);
  }

  function post(endpoint, corpo = {}) {
    return api(endpoint, { method: 'POST', body: JSON.stringify(corpo) });
  }

  function fmtHora(h) {
    if (!h) return '--:--';
    return h.substring(0, 5);
  }

  function hoje() {
    return new Date().toISOString().split('T')[0];
  }

  function blocoAtual() {
    // Usa a hora ajustada com o offset do servidor
    const h = horaActualAjustada();
    const t = h.getHours() * 60 + h.getMinutes();
    if (t >= 13*60     && t < 14*60+30) return 1;
    if (t >= 14*60+45  && t < 16*60+15) return 2;
    if (t >= 16*60+30  && t < 18*60)    return 3;
    return null;
  }

  // Hora actual ajustada com o offset calculado face ao servidor
  function horaActualAjustada() {
    return new Date(Date.now() + _serverOffset);
  }

  function emit(evento, dados) {
    (_callbacks[evento] || []).forEach(fn => fn(dados));
  }

  // ──────────────────────────────────────────────────────────
  //  SINCRONIZAÇÃO DE HORA COM O SERVIDOR
  //  [FIX 1] Chama api/turma.php?acao=hora_servidor e calcula
  //  o offset entre a hora do browser e a do servidor.
  //  O relógio no painel mostra sempre a hora do servidor.
  // ──────────────────────────────────────────────────────────

  async function sincronizarHora() {
    try {
      const t0   = Date.now();
      const resp = await get('turma.php', { acao: 'hora_servidor' });
      const t1   = Date.now();

      if (resp.status === 'ok') {
        // Hora do servidor em ms (usando o timestamp Unix devolvido)
        const serverMs  = resp.timestamp * 1000;
        // Latência estimada (metade do round-trip)
        const latencia  = (t1 - t0) / 2;
        // Offset: quanto o browser está adiantado/atrasado face ao servidor
        _serverOffset   = serverMs + latencia - Date.now();

        console.log('[SCOPE] Hora sincronizada. Offset:', Math.round(_serverOffset), 'ms');
        console.log('[SCOPE] Hora servidor:', resp.hora, '| Bloco activo:', resp.bloco_ativo);
      }
    } catch (e) {
      console.warn('[SCOPE] Não foi possível sincronizar hora com o servidor.');
    }
  }

  // ──────────────────────────────────────────────────────────
  //  ESTADO DO DISPOSITIVO RFID (ESP32)
  //  [FIX 3] Verifica se o ESP32 está online.
  //  Resultado guardado em _deviceOnline para os dashboards.
  // ──────────────────────────────────────────────────────────

  async function verificarDispositivoOnline() {
    try {
      const resp = await get('turma.php', { acao: 'device_status' });
      if (resp.status === 'ok') {
        _deviceOnline = resp.device_online === true;
        emit('device_status', { online: _deviceOnline, resp });
        return _deviceOnline;
      }
    } catch (e) {
      _deviceOnline = false;
    }
    return false;
  }

  function deviceOnline() {
    return _deviceOnline;
  }

  // ──────────────────────────────────────────────────────────
  //  AUTH
  // ──────────────────────────────────────────────────────────

  async function login(email, senha) {
    return post('login.php', { email, senha });
  }

  async function logout() {
    try {
      await post('sessao.php', { acao: 'logout' });
    } catch(e) {}
    localStorage.removeItem('scope_user');
    window.location.href = '/scope/index.html';
  }

  async function verificarSessao(perfisPermitidos = null) {
    const local = localStorage.getItem('scope_user');
    if (!local) {
      window.location.href = '/scope/index.html';
      return null;
    }

    const user = JSON.parse(local);

    if (perfisPermitidos) {
      const permitidos = Array.isArray(perfisPermitidos) ? perfisPermitidos : [perfisPermitidos];
      if (!permitidos.includes(user.perfil)) {
        const destinos = {
          professor:     '/scope/dashboard_professor.html',
          coordenador:   '/scope/dashboard_coordenador.html',
          administrador: '/scope/dashboard_admin.html',
          encarregado:   '/scope/portal_encarregado.html',
        };
        window.location.href = destinos[user.perfil] || '/scope/index.html';
        return null;
      }
    }

    try {
      const resp = await get('sessao.php');
      if (resp.status !== 'ok') {
        localStorage.removeItem('scope_user');
        window.location.href = '/scope/index.html';
        return null;
      }
    } catch(e) {
      console.warn('[SCOPE] Servidor indisponível — sessão local mantida.');
    }

    return user;
  }

  function preencherUI(user) {
    if (!user) return;
    const perfis = {
      professor:     'Professor',
      coordenador:   'Coordenador',
      administrador: 'Administrador',
      encarregado:   'Encarregado',
    };
    const els = {
      '.sb-uname':   user.nome,
      '.sb-urole':   perfis[user.perfil] || user.perfil,
      '#topbarName': user.nome,
      '#topbarRole': perfis[user.perfil] || user.perfil,
      '#topbarAvatar': scopeIniciais(user.nome),
    };
    Object.entries(els).forEach(([sel, val]) => {
      document.querySelectorAll(sel).forEach(el => { el.textContent = val; });
    });
    document.querySelectorAll('.sb-av').forEach(el => { el.textContent = scopeIniciais(user.nome); });
  }

  // ──────────────────────────────────────────────────────────
  //  PRESENÇAS DO DIA — Dashboard Professor
  // ──────────────────────────────────────────────────────────

  async function carregarPresencasDia(bloco = null, data = null) {
    const b = bloco ?? blocoAtual() ?? 1;
    const d = data  ?? hoje();
    return get('turma.php', { acao: 'presencas_dia', turma: TURMA_ID, data: d, bloco: b });
  }

  async function editarEstado(alunoId, horarioId, estado, observacao = '', data = null, perfil = 'professor') {
    return post('turma.php?acao=editar_estado', {
      aluno_id:   alunoId,
      horario_id: horarioId,
      data:       data ?? hoje(),
      estado,
      observacao,
      perfil,     // ← enviado para o servidor verificar se pode editar
    });
  }

  async function guardarOcorrencia(professorId, descricao, tempo = null, data = null) {
    return post('turma.php?acao=ocorrencia', {
      turma_id:     TURMA_ID,
      professor_id: professorId,
      data:         data ?? hoje(),
      tempo:        tempo ?? blocoAtual() ?? 1,
      descricao,
    });
  }

  // ──────────────────────────────────────────────────────────
  //  AULA ATUAL
  // ──────────────────────────────────────────────────────────

  async function aulaAtual() {
    return get('turma.php', { acao: 'aula_atual', turma: TURMA_ID });
  }

  // ──────────────────────────────────────────────────────────
  //  ESTATÍSTICAS
  // ──────────────────────────────────────────────────────────

  async function estatisticas(inicio = null, fim = null) {
    const mesInicio = hoje().substring(0, 8) + '01';
    return get('turma.php', {
      acao:   'estatisticas',
      turma:  TURMA_ID,
      inicio: inicio ?? mesInicio,
      fim:    fim ?? hoje(),
    });
  }

  async function listarAlunos() {
    return get('turma.php', { acao: 'alunos', turma: TURMA_ID });
  }

  async function listarHorario() {
    return get('turma.php', { acao: 'horario', turma: TURMA_ID });
  }

  // ──────────────────────────────────────────────────────────
  //  PRESENCAS ALUNO — Portal Encarregado
  // ──────────────────────────────────────────────────────────

  async function presencasAluno(alunoId, inicio = null, fim = null) {
    const mesInicio = hoje().substring(0, 8) + '01';
    return get('turma.php', {
      acao:   'presencas_aluno',
      aluno:  alunoId,
      inicio: inicio ?? mesInicio,
      fim:    fim ?? hoje(),
    });
  }

  // ──────────────────────────────────────────────────────────
  //  GESTÃO DE ALUNOS (Admin)
  //  [FIX 4] Novo método para adicionar aluno pelo painel
  // ──────────────────────────────────────────────────────────

  async function listarAlunosAdmin(turmaId = TURMA_ID) {
    return get('alunos_admin.php', { acao: 'listar', turma: turmaId });
  }

  async function adicionarAluno(dados) {
    return post('alunos_admin.php?acao=adicionar', dados);
  }

  async function editarAluno(dados) {
    return post('alunos_admin.php?acao=editar', dados);
  }

  async function toggleAlunoAtivo(alunoId) {
    return post('alunos_admin.php?acao=toggle_ativo', { id: alunoId });
  }

  async function associarRFID(alunoId, rfidId) {
    return post('alunos_admin.php?acao=associar_rfid', {
      aluno_id: alunoId,
      rfid_id:  rfidId,
    });
  }

  // ──────────────────────────────────────────────────────────
  //  POLLING RFID
  //  [FIX 2] Agora emite 'presencas_atualizadas' em TODOS
  //  os polls, não só quando detecta nova leitura.
  //  Assim o painel actualiza mesmo que o professor abra
  //  o dashboard depois do aluno já ter passado o cartão.
  // ──────────────────────────────────────────────────────────

  function iniciarPolling(callbackNovaLeitura) {
    if (_pollTimer) clearInterval(_pollTimer);

    async function verificar() {
      const bloco = blocoAtual();
      if (!bloco) return;

      const resp = await carregarPresencasDia(bloco);
      if (resp.status !== 'ok') return;

      // ✅ Emitir SEMPRE — garante actualização ao abrir o painel
      emit('presencas_atualizadas', resp);

      // Detectar nova leitura RFID
      if (callbackNovaLeitura && resp.alunos) {
        resp.alunos.forEach(a => {
          if (a.registado_por === 'rfid' && a.hora_entrada) {
            const chave = `${a.id}-${a.hora_entrada}`;
            if (chave !== _ultimoRegisto) {
              _ultimoRegisto = chave;
              callbackNovaLeitura(a);
            }
          }
        });
      }

      // Verificar estado do dispositivo a cada poll
      await verificarDispositivoOnline();
    }

    verificar();
    _pollTimer = setInterval(verificar, POLL_INTERVAL);
    console.log('[SCOPE] Polling RFID iniciado — intervalo:', POLL_INTERVAL, 'ms');
  }

  function pararPolling() {
    if (_pollTimer) { clearInterval(_pollTimer); _pollTimer = null; }
  }

  // ──────────────────────────────────────────────────────────
  //  LISTENERS
  // ──────────────────────────────────────────────────────────

  function on(evento, fn) {
    if (!_callbacks[evento]) _callbacks[evento] = [];
    _callbacks[evento].push(fn);
  }

  // ──────────────────────────────────────────────────────────
  //  EXPORTAR API PÚBLICA
  // ──────────────────────────────────────────────────────────

  return {
    // Auth
    login, logout, verificarSessao, preencherUI,

    // Dados turma
    carregarPresencasDia, editarEstado, guardarOcorrencia,
    aulaAtual, estatisticas, listarAlunos, listarHorario,
    presencasAluno,

    // Gestão alunos (admin)
    listarAlunosAdmin, adicionarAluno, editarAluno,
    toggleAlunoAtivo, associarRFID,

    // Dispositivo
    verificarDispositivoOnline, deviceOnline,

    // Sincronização hora
    sincronizarHora, horaActualAjustada,

    // Polling
    iniciarPolling, pararPolling,

    // Listeners
    on,

    // Utilitários
    fmtHora, hoje, blocoAtual,
    TURMA_ID,
  };

})();


// ============================================================
//  SCOPE-UI.js — Componentes reutilizáveis
// ============================================================

function scopeBadge(estado) {
  const map = {
    presente:          ['presente',  'Presente'],
    atraso:            ['atraso',    'Atraso'],
    ausente:           ['ausente',   'Ausente'],
    falta_disciplinar: ['ausente',   'F. Disciplinar'],
  };
  const [cls, txt] = map[estado] || ['ausente', 'Ausente'];
  return `<span class="badge ${cls}">${txt}</span>`;
}

function scopeIniciais(nome) {
  const p = (nome || '?').trim().split(' ');
  return (p[0][0] + (p[p.length - 1]?.[0] || '')).toUpperCase();
}

const SCOPE_CORES = [
  '#3B82F6','#8B5CF6','#EC4899','#14B8A6',
  '#F97316','#06B6D4','#84CC16','#EF4444',
];

function scopeCor(idx) {
  return SCOPE_CORES[idx % SCOPE_CORES.length];
}

// ── Relógio sincronizado com o servidor ─────────────────────
// [FIX 1] Usa o offset calculado por SCOPE.sincronizarHora()
// para mostrar a hora do servidor, não a do browser local.
function scopeRelogio(elId, intervalo = 1000) {
  function tick() {
    const el = document.getElementById(elId);
    if (!el) return;
    const n = SCOPE.horaActualAjustada();
    el.textContent =
      String(n.getHours()).padStart(2, '0') + ':' +
      String(n.getMinutes()).padStart(2, '0');
  }
  tick();
  return setInterval(tick, intervalo);
}

function scopeDataExtenso(elId) {
  const el = document.getElementById(elId);
  if (!el) return;
  el.textContent = SCOPE.horaActualAjustada().toLocaleDateString('pt-PT', {
    weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
  });
}

let _toastTimer;
function scopeToast(msg, tipo = 'tg') {
  const t = document.getElementById('toast');
  if (!t) return;
  t.textContent = msg;
  t.className = `toast ${tipo} show`;
  clearTimeout(_toastTimer);
  _toastTimer = setTimeout(() => t.classList.remove('show'), 3200);
}
