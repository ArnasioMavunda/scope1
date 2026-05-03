// ============================================================
//  SCOPE — admin_patch.js
//  Ficheiro: recursos/js/admin_patch.js
//
//  COMO USAR: incluir DEPOIS do script principal do admin:
//    <script src="recursos/js/scope-api.js"></script>
//    <script>  ... código do admin ... </script>
//    <script src="recursos/js/admin_patch.js"></script>
//
//  Este ficheiro:
//  1. Sobrescreve saveAluno() para guardar na BD via API
//  2. Adiciona saveCartaoRFID() que usa a nova API
//  3. Sincroniza a hora exibida com o servidor
//  4. Carrega a lista de turmas da BD para o select
// ============================================================

(async function adminPatch() {

  // ── 1. Sincronizar hora com o servidor ─────────────────────
  if (typeof SCOPE !== 'undefined') {
    await SCOPE.sincronizarHora();
  }

  // ── 2. Carregar turmas da BD para o select do modal aluno ──
  try {
    const resp = await fetch('/scope/api/alunos_admin.php?acao=listar_turmas')
      .then(r => r.json());
    if (resp.status === 'ok' && resp.turmas) {
      const sel = document.getElementById('aTurma');
      if (sel) {
        sel.innerHTML = resp.turmas.map(t =>
          `<option value="${t.id}">${t.nome}</option>`
        ).join('');
      }
    }
  } catch(e) {
    console.warn('[SCOPE Admin] Não foi possível carregar turmas:', e);
  }

  // ── 3. Carregar alunos reais da BD ─────────────────────────
  await carregarAlunosBD();

  // ── 4. Sobrescrever saveAluno() ─────────────────────────────
  // A função original apenas guardava em memória local (array ALUNOS).
  // Esta versão corrigida envia os dados para a API PHP que guarda
  // na base de dados MySQL.

  window.saveAluno = async function() {
    const nome    = (document.getElementById('aNome')?.value || '').trim();
    const proc    = (document.getElementById('aProc')?.value || '').trim();
    const rfid    = (document.getElementById('aRfid')?.value || '').trim();
    const turmaEl = document.getElementById('aTurma');
    const turmaId = turmaEl ? parseInt(turmaEl.value) : 1;

    if (!nome) {
      showAdminToast('Nome do aluno é obrigatório.', 'ty');
      return;
    }

    // Desactivar botão durante o pedido
    const btnSave = document.querySelector('#modalAluno .btn.primary');
    if (btnSave) { btnSave.disabled = true; btnSave.textContent = 'A guardar…'; }

    try {
      const resp = await fetch('/scope/api/alunos_admin.php?acao=adicionar', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({
          nome,
          num_processo: proc,
          rfid_id:      rfid,
          turma_id:     turmaId,
        }),
      }).then(r => r.json());

      if (resp.status === 'ok') {
        showAdminToast('Aluno ' + nome + ' adicionado com sucesso!', 'tg');
        closeModal('modalAluno');
        // Recarregar a lista de alunos da BD
        await carregarAlunosBD();
      } else {
        showAdminToast(resp.mensagem || 'Erro ao guardar aluno.', 'tr');
      }
    } catch(e) {
      showAdminToast('Sem ligação ao servidor.', 'tr');
      console.error('[SCOPE Admin] saveAluno error:', e);
    } finally {
      if (btnSave) { btnSave.disabled = false; btnSave.innerHTML = '<i class="fa fa-floppy-disk"></i>Guardar'; }
    }
  };

  // ── 5. Sobrescrever saveCartao() para usar a API real ───────
  window.saveCartao = async function() {
    const alunoNome = document.getElementById('cAluno')?.value || '';
    const uid       = (document.getElementById('cUID')?.value || '').trim();
    const acao      = document.getElementById('cAcao')?.value || 'associar';

    if (!alunoNome) { showAdminToast('Seleciona o aluno.', 'ty'); return; }

    // Encontrar o ID do aluno (pelo nome — fallback para a lógica antiga)
    // Ideal: o select devia ter value=id. Mantemos compatibilidade com o HTML existente.
    const alunoId = resolverAlunoIdPorNome(alunoNome);

    if (!alunoId) {
      showAdminToast('Aluno não encontrado na base de dados.', 'ty');
      return;
    }

    if (acao !== 'desativar' && !uid) {
      showAdminToast('Introduz o UID do cartão.', 'ty');
      return;
    }

    try {
      const resp = await fetch('/scope/api/alunos_admin.php?acao=associar_rfid', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({
          aluno_id: alunoId,
          rfid_id:  acao === 'desativar' ? '' : uid,
        }),
      }).then(r => r.json());

      if (resp.status === 'ok') {
        showAdminToast(resp.mensagem, 'tg');
        closeModal('modalCartao');
        await carregarAlunosBD();
      } else {
        showAdminToast(resp.mensagem || 'Erro ao associar cartão.', 'tr');
      }
    } catch(e) {
      showAdminToast('Sem ligação ao servidor.', 'tr');
    }
  };

  // ── 6. Botão de toggle de aluno usa API real ────────────────
  window.toggleAlunoStatusReal = async function(alunoId, nomeAluno) {
    if (!confirm('Alterar estado do aluno ' + nomeAluno + '?')) return;
    try {
      const resp = await fetch('/scope/api/alunos_admin.php?acao=toggle_ativo', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ id: alunoId }),
      }).then(r => r.json());

      if (resp.status === 'ok') {
        showAdminToast(resp.mensagem, 'tg');
        await carregarAlunosBD();
      } else {
        showAdminToast(resp.mensagem || 'Erro.', 'tr');
      }
    } catch(e) {
      showAdminToast('Sem ligação ao servidor.', 'tr');
    }
  };

})(); // fim da IIFE

// ── Carregar alunos da BD e actualizar a tabela ────────────────
async function carregarAlunosBD() {
  try {
    const resp = await fetch('/scope/api/alunos_admin.php?acao=listar&turma=1')
      .then(r => r.json());

    if (resp.status !== 'ok') return;

    // Actualizar a tabela no painel de alunos
    const tbody = document.getElementById('tblAlunosBody');
    if (!tbody) return;

    const CORS_ADMIN = [
      '#3B82F6','#8B5CF6','#EC4899','#14B8A6',
      '#F97316','#06B6D4','#84CC16','#EF4444',
    ];
    function iniA(n){ const p=(n||'?').trim().split(' '); return (p[0][0]+(p[p.length-1]?.[0]||'')).toUpperCase(); }

    tbody.innerHTML = resp.alunos.map((a, i) => `
      <tr>
        <td><span class="id-chip">${a.num_processo || '—'}</span></td>
        <td>
          <div style="display:flex;align-items:center;gap:8px">
            <div style="width:28px;height:28px;border-radius:50%;background:${CORS_ADMIN[i%CORS_ADMIN.length]};display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:white;flex-shrink:0">${iniA(a.nome)}</div>
            <span style="font-weight:600;font-size:12px">${a.nome}</span>
          </div>
        </td>
        <td>${a.turma_nome || '—'}</td>
        <td>—</td>
        <td>
          <span style="font-family:monospace;font-size:11px;background:var(--s50);border:1px solid var(--s200);padding:2px 8px;border-radius:5px">
            ${a.rfid_id || '—'}
          </span>
        </td>
        <td>
          <span class="badge ${a.ativo ? 'g' : 's'}">
            <span class="dot ${a.ativo ? 'g' : 's'}"></span>
            ${a.ativo ? 'Ativo' : 'Inativo'}
          </span>
        </td>
        <td class="ops">
          <button class="btn outline-b xs" onclick="editAlunoAdmin(${a.id})">
            <i class="fa fa-pen"></i>Editar
          </button>
          <button class="btn ${a.ativo ? 'danger' : 'success'} xs"
            onclick="toggleAlunoStatusReal(${a.id}, '${a.nome.replace(/'/g,'&apos;')}')">
            <i class="fa fa-${a.ativo ? 'ban' : 'check'}"></i>
          </button>
        </td>
      </tr>
    `).join('');

    // Actualizar select de alunos (para cartões RFID)
    const selCartao = document.getElementById('cAluno');
    if (selCartao) {
      selCartao.innerHTML = '<option value="">Selecionar aluno…</option>'
        + resp.alunos.map(a => `<option value="${a.nome}" data-id="${a.id}">${a.nome}</option>`).join('');
    }

    // Actualizar tabela RFID
    const tbodyRFID = document.getElementById('tblRFIDBody');
    if (tbodyRFID) {
      tbodyRFID.innerHTML = resp.alunos.map((a, i) => `
        <tr>
          <td><span class="id-chip">${a.num_processo || '—'}</span></td>
          <td>
            <div style="display:flex;align-items:center;gap:7px">
              <div style="width:26px;height:26px;border-radius:50%;background:${CORS_ADMIN[i%CORS_ADMIN.length]};display:flex;align-items:center;justify-content:center;font-size:9px;font-weight:700;color:white">${iniA(a.nome)}</div>
              <span style="font-size:12px;font-weight:600">${a.nome}</span>
            </div>
          </td>
          <td>
            <span style="font-family:monospace;font-size:11px;background:var(--s50);border:1px solid var(--s200);padding:2px 8px;border-radius:5px">
              ${a.rfid_id || '—'}
            </span>
          </td>
          <td>${a.turma_nome || '—'}</td>
          <td>
            <span class="badge ${a.rfid_id ? 'g' : 's'}">
              <span class="dot ${a.rfid_id ? 'g' : 's'}"></span>
              ${a.rfid_id ? 'Ativo' : 'Sem cartão'}
            </span>
          </td>
          <td class="ops">
            <button class="btn outline-b xs" onclick="abrirModalCartao(${a.id}, '${a.nome.replace(/'/g,'&apos;')}')">
              <i class="fa fa-arrows-rotate"></i>${a.rfid_id ? 'Substituir' : 'Associar'}
            </button>
            ${a.rfid_id ? `<button class="btn danger xs" onclick="toggleAlunoStatusReal(${a.id}, '${a.nome.replace(/'/g,'&apos;')}')"><i class="fa fa-ban"></i></button>` : ''}
          </td>
        </tr>
      `).join('');
    }

    // Guardar mapa nome→id para saveCartao
    window._alunosBD = resp.alunos;

  } catch(e) {
    console.warn('[SCOPE Admin] carregarAlunosBD error:', e);
  }
}

// ── Resolver ID do aluno pelo nome (para saveCartao) ──────────
function resolverAlunoIdPorNome(nome) {
  if (!window._alunosBD) return null;
  const aluno = window._alunosBD.find(a => a.nome === nome);
  return aluno ? aluno.id : null;
}

// ── Abrir modal de cartão para um aluno específico ────────────
function abrirModalCartao(alunoId, nomeAluno) {
  const sel = document.getElementById('cAluno');
  if (sel) {
    // Seleccionar o aluno no dropdown
    for (let opt of sel.options) {
      if (opt.dataset.id == alunoId) {
        opt.selected = true;
        break;
      }
    }
  }
  openModal('modalCartao');
}

// ── Editar aluno (abre modal com dados da BD) ─────────────────
window.editAlunoAdmin = async function(alunoId) {
  if (!window._alunosBD) return;
  const a = window._alunosBD.find(x => x.id === alunoId);
  if (!a) return;

  document.getElementById('mAlunoTitle').textContent = 'Editar Aluno';
  const procEl = document.getElementById('aProc');
  const nomeEl = document.getElementById('aNome');
  const rfidEl = document.getElementById('aRfid');
  if (procEl) procEl.value = a.num_processo || '';
  if (nomeEl) nomeEl.value = a.nome || '';
  if (rfidEl) rfidEl.value = a.rfid_id || '';

  // Guardar ID para o save saber que é edição
  const modal = document.getElementById('modalAluno');
  if (modal) modal.dataset.editId = alunoId;

  // Substituir botão de guardar para chamar edição
  const btnSave = document.querySelector('#modalAluno .btn.primary');
  if (btnSave) {
    btnSave.onclick = () => guardarEdicaoAluno(alunoId);
  }

  openModal('modalAluno');
};

async function guardarEdicaoAluno(alunoId) {
  const nome = (document.getElementById('aNome')?.value || '').trim();
  const proc = (document.getElementById('aProc')?.value || '').trim();
  const rfid = (document.getElementById('aRfid')?.value || '').trim();

  if (!nome) { showAdminToast('Nome obrigatório.', 'ty'); return; }

  try {
    const resp = await fetch('/scope/api/alunos_admin.php?acao=editar', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ id: alunoId, nome, num_processo: proc, rfid_id: rfid }),
    }).then(r => r.json());

    if (resp.status === 'ok') {
      showAdminToast('Aluno actualizado!', 'tg');
      closeModal('modalAluno');
      await carregarAlunosBD();
      // Restaurar botão original
      const btnSave = document.querySelector('#modalAluno .btn.primary');
      if (btnSave) { btnSave.onclick = () => saveAluno(); }
    } else {
      showAdminToast(resp.mensagem || 'Erro ao actualizar.', 'tr');
    }
  } catch(e) {
    showAdminToast('Sem ligação ao servidor.', 'tr');
  }
}

// ── Toast helper (usa a função do admin se disponível) ────────
function showAdminToast(msg, cls) {
  if (typeof showToast === 'function') {
    showToast(msg, cls);
  } else if (typeof scopeToast === 'function') {
    scopeToast(msg, cls);
  } else {
    console.log('[SCOPE Toast]', msg);
  }
}
