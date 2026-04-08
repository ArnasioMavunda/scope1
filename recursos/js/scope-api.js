// ============================================================
//  SCOPE — scope-api.js
//  Ficheiro central de comunicação com a API PHP.
//  Incluir em TODOS os dashboards:
//    <script src="recursos/js/scope-api.js"></script>
// ============================================================

const SCOPE = (() => {

  // ── Configuração ────────────────────────────────────────
  const BASE = '/scope/api';           // Caminho base da API
  const TURMA_ID = 1;                  // 12ª CFB (única turma)
  const POLL_INTERVAL = 8000;          // Polling RFID: 8 segundos

  // ── Estado global ────────────────────────────────────────
  let _pollTimer     = null;
  let _ultimoRegisto = null;           // Último timestamp recebido
  let _callbacks     = {};             // Registo de listeners

  // ──────────────────────────────────────────────────────────
  //  UTILITÁRIOS
  // ──────────────────────────────────────────────────────────

  /** Fetch genérico com tratamento de erros */
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

  /** GET com query string */
  function get(endpoint, params = {}) {
    const qs = new URLSearchParams(params).toString();
    return api(qs ? `${endpoint}?${qs}` : endpoint);
  }

  /** POST com corpo JSON */
  function post(endpoint, corpo = {}) {
    return api(endpoint, { method: 'POST', body: JSON.stringify(corpo) });
  }

  /** Formata hora: "13:03:22" → "13:03" */
  function fmtHora(h) {
    if (!h) return '--:--';
    return h.substring(0, 5);
  }

  /** Data de hoje YYYY-MM-DD */
  function hoje() {
    return new Date().toISOString().split('T')[0];
  }

  /** Devolve bloco activo (1, 2, 3) ou null */
  function blocoAtual() {
    const h = new Date();
    const t = h.getHours() * 60 + h.getMinutes();
    if (t >= 13*60     && t < 14*60+30) return 1;   // 13:00–14:30
    if (t >= 14*60+45  && t < 16*60+15) return 2;   // 14:45–16:15
    if (t >= 16*60+30  && t < 18*60)    return 3;   // 16:30–18:00
    return null;
  }

  /** Emitir evento interno */
  function emit(evento, dados) {
    (_callbacks[evento] || []).forEach(fn => fn(dados));
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

  /**
   * Verifica se há sessão activa.
   * Chamar no topo de cada dashboard:
   *   await SCOPE.verificarSessao(['professor']);
   */
  async function verificarSessao(perfisPermitidos = null) {
    // 1. Verificação rápida via localStorage (evita flash de conteúdo)
    const local = localStorage.getItem('scope_user');
    if (!local) {
      window.location.href = '/scope/index.html';
      return null;
    }

    const user = JSON.parse(local);

    // 2. Verificar perfil se exigido
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

    // 3. Validar com o servidor (em segundo plano)
    try {
      const resp = await get('sessao.php');
      if (resp.status !== 'ok') {
        localStorage.removeItem('scope_user');
        window.location.href = '/scope/index.html';
        return null;
      }
    } catch(e) {
      // Sem servidor — manter sessão local para desenvolvimento offline
      console.warn('[SCOPE] Servidor indisponível — sessão local mantida.');
    }

    return user;
  }

  /** Preenche elementos do dashboard com dados do utilizador */
  function preencherUI(user) {
    if (!user) return;
    const perfis = { professor:'Professor', coordenador:'Coordenador', administrador:'Administrador', encarregado:'Encarregado' };
    // Elementos comuns nos dashboards
    const els = {
      '.sb-uname':      user.nome,
      '.sb-urole':      perfis[user.perfil] || user.perfil,
      '#topbarName':    user.nome,
      '#topbarRole':    perfis[user.perfil] || user.perfil,
      '#topbarAvatar':  scopeIniciais(user.nome),
    };
    Object.entries(els).forEach(([sel, val]) => {
      document.querySelectorAll(sel).forEach(el => { el.textContent = val; });
    });
    // Avatar da sidebar
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

  async function editarEstado(alunoId, horarioId, estado, observacao = '', data = null) {
    return post('turma.php?acao=editar_estado', {
      aluno_id: alunoId,
      horario_id: horarioId,
      data: data ?? hoje(),
      estado,
      observacao,
    });
  }

  async function guardarOcorrencia(professorId, descricao, tempo = null, data = null) {
    return post('turma.php?acao=ocorrencia', {
      turma_id: TURMA_ID,
      professor_id: professorId,
      data: data ?? hoje(),
      tempo: tempo ?? blocoAtual() ?? 1,
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
  //  ESTATÍSTICAS — Dashboard Coordenador / Admin
  // ──────────────────────────────────────────────────────────

  async function estatisticas(inicio = null, fim = null) {
    const mesInicio = hoje().substring(0, 8) + '01';
    return get('turma.php', {
      acao: 'estatisticas',
      turma: TURMA_ID,
      inicio: inicio ?? mesInicio,
      fim: fim ?? hoje(),
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
      acao: 'presencas_aluno',
      aluno: alunoId,
      inicio: inicio ?? mesInicio,
      fim: fim ?? hoje(),
    });
  }

  // ──────────────────────────────────────────────────────────
  //  POLLING RFID — actualiza a lista de presenças em tempo real
  // ──────────────────────────────────────────────────────────

  function iniciarPolling(callbackNovaLeitura) {
    if (_pollTimer) clearInterval(_pollTimer);

    async function verificar() {
      const bloco = blocoAtual();
      if (!bloco) return;                        // Fora do horário

      const resp = await carregarPresencasDia(bloco);
      if (resp.status !== 'ok') return;

      emit('presencas_atualizadas', resp);

      // Detectar nova leitura RFID comparando com último estado
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
    }

    verificar();                                 // Executar imediatamente
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
    login,
    logout,
    verificarSessao,
    preencherUI,

    // Dados
    carregarPresencasDia,
    editarEstado,
    guardarOcorrencia,
    aulaAtual,
    estatisticas,
    listarAlunos,
    listarHorario,
    presencasAluno,

    // Polling
    iniciarPolling,
    pararPolling,

    // Listeners
    on,

    // Utilitários
    fmtHora,
    hoje,
    blocoAtual,
    TURMA_ID,
  };

})();


// ============================================================
//  SCOPE-UI.js — Componentes de interface reutilizáveis
//  (badges, toasts, relógio, etc.)
// ============================================================

/** Badge de estado HTML */
function scopeBadge(estado) {
  const map = {
    presente: ['presente', 'Presente'],
    atraso:   ['atraso',   'Atraso'],
    ausente:  ['ausente',  'Ausente'],
    falta_disciplinar: ['ausente', 'F. Disciplinar'],
  };
  const [cls, txt] = map[estado] || ['ausente', 'Ausente'];
  return `<span class="badge ${cls}">${txt}</span>`;
}

/** Iniciais do nome */
function scopeIniciais(nome) {
  const p = nome.trim().split(' ');
  return (p[0][0] + (p[p.length - 1]?.[0] || '')).toUpperCase();
}

/** Paleta de cores para avatares */
const SCOPE_CORES = [
  '#3B82F6','#8B5CF6','#EC4899','#14B8A6',
  '#F97316','#06B6D4','#84CC16','#EF4444',
];

function scopeCor(idx) {
  return SCOPE_CORES[idx % SCOPE_CORES.length];
}

/** Relógio em tempo real — passa o id do elemento */
function scopeRelogio(elId, intervalo = 1000) {
  function tick() {
    const el = document.getElementById(elId);
    if (!el) return;
    const n = new Date();
    el.textContent =
      String(n.getHours()).padStart(2, '0') + ':' +
      String(n.getMinutes()).padStart(2, '0');
  }
  tick();
  return setInterval(tick, intervalo);
}

/** Data por extenso */
function scopeDataExtenso(elId) {
  const el = document.getElementById(elId);
  if (!el) return;
  el.textContent = new Date().toLocaleDateString('pt-PT', {
    weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
  });
}

/** Toast — reutilizável em qualquer dashboard */
let _toastTimer;
function scopeToast(msg, tipo = 'tg') {
  const t = document.getElementById('toast');
  if (!t) return;
  t.textContent = msg;
  t.className = `toast ${tipo} show`;
  clearTimeout(_toastTimer);
  _toastTimer = setTimeout(() => t.classList.remove('show'), 3200);
}
