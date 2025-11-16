<?php 
include 'conexao.php';
include 'verifica_login.php';

// -------------------- Configurações --------------------
$limite  = 10;
$pagina  = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$offset  = ($pagina - 1) * $limite;

// -------------------- Filtros --------------------
$ordem_coluna  = $_GET['ordem_coluna'] ?? 'nome';
$ordem_direcao = $_GET['ordem_direcao'] ?? 'asc';
$ordem_direcao = strtolower($ordem_direcao) === 'desc' ? 'DESC' : 'ASC';

// Mapeia campos permitidos (whitelist) -> colunas reais
$ordem_map = [
    'id'        => 'r.id',
    'nome'      => 'r.nome',
    'data_hora' => 'r.data_hora',
];
$ordem_sql = $ordem_map[$ordem_coluna] ?? $ordem_map['nome'];

$filtro_secretaria = $_GET['filtro_secretaria'] ?? '';
$filtro_tipo       = $_GET['filtro_tipo'] ?? '';
$buscar            = $_GET['buscar'] ?? '';

// -------------------- Função para normalizar strings --------------------
function normalizar($str) {
    $str = mb_strtolower($str, 'UTF-8');
    $acentos = ['á','à','ã','â','ä','é','è','ê','ë','í','ì','î','ï','ó','ò','õ','ô','ö','ú','ù','û','ü','ç'];
    $sem_acentos = ['a','a','a','a','a','e','e','e','e','i','i','i','i','o','o','o','o','o','u','u','u','u','c'];
    return str_replace($acentos, $sem_acentos, $str);
}

// -------------------- Monta WHERE básico --------------------
$where = "WHERE 1=1";
if ($filtro_secretaria !== '') {
    $where .= " AND r.id_secretaria = " . intval($filtro_secretaria);
}
if ($filtro_tipo !== '') {
    $where .= " AND r.tipo_requisicao = " . intval($filtro_tipo);
}

// -------------------- Buscar registros --------------------
$sql = "SELECT r.*, t.nome AS tipo_nome, s.nome_secretaria
        FROM reclamacoes r
        LEFT JOIN tipos_requisicao t ON r.tipo_requisicao = t.id
        LEFT JOIN secretaria s ON r.id_secretaria = s.id_secretaria
        $where
        ORDER BY $ordem_sql $ordem_direcao";

$result = $conn->query($sql);

// -------------------- Aplica filtro de busca (inclui ID; parcial p/ nome e endereço; exata p/ tel e data) --------------------
$dados = [];
if ($result) {
    $tem_busca = ($buscar !== '');
    if ($tem_busca) {
        $busca_trim        = trim($buscar);

        // tenta extrair ID numérico (ex.: "176" ou "#176")
        $busca_id = null;
        if (preg_match('/^#?\s*(\d+)$/', $busca_trim, $m)) {
            $busca_id = (int)$m[1];
        }

        $busca_normalizada = normalizar($busca_trim);
        $busca_numerica    = preg_replace('/[^0-9]/', '', $busca_trim);
    }

    while ($row = $result->fetch_assoc()) {
        if ($tem_busca) {
            // ID: igualdade exata
            $ok_id = ($busca_id !== null && (int)$row['id'] === $busca_id);

            // Nome: parcial, insensível a acentos/maiúsculas
            $nome_norm = normalizar((string)$row['nome']);
            $ok_nome   = (strpos($nome_norm, $busca_normalizada) !== false);

            // Endereço + número: parcial, insensível
            $endereco_fmt  = formatEndereco($row);
            $endereco_norm = normalizar($endereco_fmt);
            $ok_endereco   = (strpos($endereco_norm, $busca_normalizada) !== false);

            // Telefone: só dígitos, igualdade exata
            $tel_digits  = preg_replace('/[^0-9]/', '', (string)$row['telefone']);
            $ok_telefone = ($busca_numerica !== '' && $tel_digits === $busca_numerica);

            // Data/hora: igualdade exata (valor bruto do banco)
            $ok_data = ((string)$row['data_hora'] === $busca_trim);

            if (!($ok_id || $ok_nome || $ok_endereco || $ok_telefone || $ok_data)) {
                continue;
            }
        }
        $dados[] = $row;
    }
}

// -------------------- Paginação (backend) --------------------
$total         = count($dados);
$total_paginas = max(1, ceil($total / $limite));
$dados         = array_slice($dados, $offset, $limite);

// -------------------- Buscar secretarias --------------------
$secretarias_res = $conn->query("SELECT id_secretaria, nome_secretaria FROM secretaria ORDER BY nome_secretaria");
$secretarias = [];
while($row = $secretarias_res->fetch_assoc()) {
    $secretarias[$row['id_secretaria']] = $row['nome_secretaria'];
}

// -------------------- Buscar tipos --------------------
$tipos_res = $conn->query("SELECT id, nome FROM tipos_requisicao ORDER BY nome");
$tipos = [];
while($row = $tipos_res->fetch_assoc()) {
    $tipos[$row['id']] = $row['nome'];
}

// -------------------- Helper de badge de status --------------------
function badgeStatus($statusBruto) {
    $status = trim((string)$statusBruto);
    if ($status === '') $status = 'Pendente';
    $cls = 'secondary';
    switch (mb_strtolower($status, 'UTF-8')) {
        case 'pendente':      $cls = 'warning'; break;
        case 'em andamento':  $cls = 'info';    break;
        case 'concluido':     $cls = 'success'; break;
    }
    return '<span class="badge text-bg-' . $cls . '">' . htmlspecialchars($status) . '</span>';
}

// -------------------- Helper de endereço --------------------
function formatEndereco(array $row): string {
    $e = trim((string)($row['endereco'] ?? ''));
    $n = trim((string)($row['numero_endereco'] ?? ''));

    if ($n !== '' && strtolower($n) !== 'null') {
        $e = preg_replace('/\s*,\s*$/', '', $e);
        if ($n !== '' && stripos($e, $n) === false) {
            $e .= ($e !== '' ? ', ' : '') . $n;
        }
    }
    return $e;
}

// -------------------- Render das linhas (para página inicial e AJAX) --------------------
function render_rows($dados) {
    foreach ($dados as $row) { 
        $statusAtual = $row['status_reclamacao'] ?? 'Pendente';
        $statusClass = strtolower(str_replace(' ', '-', $statusAtual));
        ?>
        <tr id="row-<?= (int)$row['id'] ?>">
            <td><input type="checkbox" class="chk-reclamacao" value="<?= (int)$row['id'] ?>"></td>
            <td><span class="text-muted"><?= (int)$row['id'] ?></span></td>
            <td><?= htmlspecialchars($row['nome']) ?></td>
            <td><?= htmlspecialchars($row['tipo_nome']) ?></td>
            <td><?= htmlspecialchars($row['telefone']) ?></td>
            <td><?= htmlspecialchars(formatEndereco($row)) ?></td>
            <td><?= date('d/m/Y H:i', strtotime($row['data_hora'])) ?></td>
            <td><?= htmlspecialchars($row['nome_secretaria'] ?? '—') ?></td>
            <td>
                <button 
                    class="btn-status <?= $statusClass ?> btn btn-sm"
                    data-id="<?= (int)$row['id'] ?>">
                    <?= htmlspecialchars($statusAtual) ?>
                </button>
            </td>
            <td class="text-nowrap pe-1 ps-1">
                <a class="btn btn-warning btn-sm py-1 px-2 me-1"
                   href="cadastrar_requisicao.php?id=<?= (int)$row['id'] ?>">
                   Alterar
                </a>
                <button type="button"
                        class="btn btn-danger btn-sm py-1 px-2 btn-deletar"
                        data-id="<?= (int)$row['id'] ?>"
                        data-nome="<?= htmlspecialchars($row['nome']) ?>"
                        data-telefone="<?= htmlspecialchars($row['telefone']) ?>"
                        data-endereco="<?= htmlspecialchars(formatEndereco($row)) ?>">
                    Deletar
                </button>
            </td>
        </tr>
    <?php }
}

// -------------------- Handler AJAX (scroll infinito) --------------------
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: application/json; charset=UTF-8');
    ob_start();
    if (count($dados) > 0) {
        render_rows($dados);
    }
    $html = ob_get_clean();

    echo json_encode([
        'success'        => true,
        'html'           => $html,
        'temMais'        => ($pagina < $total_paginas),
        'proximaPagina'  => $pagina + 1
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<link rel="icon"  href="images/icone.ico">
<title>Visualizar Requisições</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="estilos.css">
<style>
/* Estilo básico para os botões de status */
.btn-status.pendente      { background:#ffc107; color:#212529; }
.btn-status.em-andamento  { background:#0dcaf0; color:#212529; }
.btn-status.concluido     { background:#198754; color:#fff; }
.btn-status { border:0; padding:.25rem .5rem; border-radius:.25rem; }
</style>
</head>
<body class="bg-light">
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-building"></i> Ouvidoria
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="cadastro_usuario.php"><i class="bi bi-person-plus"></i> User</a></li>
                    <li class="nav-item"><a class="nav-link" href="cadastro_secretaria.php"><i class="bi bi-diagram-3"></i> Secretaria</a></li>
                    <li class="nav-item"><a class="nav-link" href="cadastro_setor.php"><i class="bi bi-geo-alt"></i> Setor</a></li>
                    <li class="nav-item"><a class="nav-link" href="cadastrar_tipo_requisicao.php"><i class="bi bi-card-list"></i> Tipo Requisição</a></li>
                    <li class="nav-item"><a class="nav-link" href="visualizar_reclamacoes.php"><i class="bi bi-eye"></i> Ver Requisição</a></li>
                    <li class="nav-item"><a class="nav-link" href="cadastrar_requisicao.php"><i class="bi bi-plus-circle"></i> Cad. Requisições</a></li>
                    <li class="nav-item"><a class="nav-link" href="mapa_pontos.php"><i class="bi bi-map"></i> Mapa de Reclamações</a></li>
                    <li class="nav-item"><a class="nav-link active" href="visualizar_ordem_distancia.php"><i class="bi bi-map"></i> Ver por Distância</a></li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle"></i>
                            <?= $logado ? htmlspecialchars($nome_usuario) : 'Usuário'; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <?php if (!$logado): ?>
                                <li><a class="dropdown-item" href="login.php">Entrar</a></li>
                            <?php else: ?>
                                <li><a class="dropdown-item" href="logout.php">Sair</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

<div class="container mt-5">
<h2 class="text-center mb-3">Reclamações</h2>

<!-- BOTÕES: Atualizar Lat/Long | Recalcular Ordem | Marcar Concluído (selecionados) | Salvar Status -->
<div class="d-flex flex-wrap justify-content-end gap-2 mb-3">
    <button id="btn-atualizar-latlon" class="btn btn-warning">
        Atualizar Lat/Long
    </button>
    <button id="btn-recalcular" class="btn btn-primary">
        Recalcular Ordem
    </button>
    <button id="btn-marcar-concluido" class="btn btn-success">
        Marcar selecionados como Concluído
    </button>
    <button id="btn-alterar" class="btn btn-success">
        Alterar Status
    </button>
</div>

<!-- FILTROS -->
<form method="GET" class="row g-3 mb-3">
    <div class="col-auto">
        <select name="ordem_coluna" class="form-select">
            <option value="id" <?= $ordem_coluna=='id'?'selected':'' ?>>ID</option>
            <option value="nome" <?= $ordem_coluna=='nome'?'selected':'' ?>>Nome</option>
            <option value="data_hora" <?= $ordem_coluna=='data_hora'?'selected':'' ?>>Data/Hora</option>
        </select>
    </div>
    <div class="col-auto">
        <select name="ordem_direcao" class="form-select">
            <option value="asc" <?= $ordem_direcao=='ASC'?'selected':'' ?>>Crescente</option>
            <option value="desc" <?= $ordem_direcao=='DESC'?'selected':'' ?>>Decrescente</option>
        </select>
    </div>
    <div class="col-auto">
        <select name="filtro_secretaria" class="form-select">
            <option value="">Todas Secretarias</option>
            <?php foreach($secretarias as $id => $nome_sec): ?>
                <option value="<?= $id ?>" <?= ($filtro_secretaria == strval($id)) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($nome_sec) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto">
        <select name="filtro_tipo" class="form-select">
            <option value="">Todos os Tipos</option>
            <?php foreach($tipos as $id => $nome_tp): ?>
                <option value="<?= $id ?>" <?= ($filtro_tipo == strval($id)) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($nome_tp) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-primary">Filtrar</button>
    </div>
</form>

<!-- BUSCA -->
<form method="GET" class="row g-3 mb-3">
    <input type="hidden" name="ordem_coluna" value="<?= $ordem_coluna ?>">
    <input type="hidden" name="ordem_direcao" value="<?= $ordem_direcao ?>">
    <input type="hidden" name="filtro_secretaria" value="<?= htmlspecialchars($filtro_secretaria) ?>">
    <input type="hidden" name="filtro_tipo" value="<?= $filtro_tipo ?>">

    <div class="col-auto">
        <input type="text" name="buscar" value="<?= htmlspecialchars($buscar) ?>" class="form-control" placeholder="Buscar por id, nome, telefone, data ou endereço">
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-primary">Buscar</button>
    </div>
</form>

<!-- SELEÇÃO POR INTERVALO DE ID -->
<div class="row g-3 mb-3 align-items-end">
    <div class="col-auto">
        <input type="number" id="idInicio" class="form-control" placeholder="De ID">
    </div>
    <div class="col-auto">
        <input type="number" id="idFim" class="form-control" placeholder="Até ID">
    </div>
    <div class="col-auto">
        <button type="button" class="btn btn-primary" id="btnSelecionarIntervalo">Selecionar por ID</button>
    </div>
</div>

<!-- TABELA -->
<table class="table table-bordered table-striped bg-white">
<thead>
<tr>
    <th>Selec.</th>
    <th>ID</th>
    <th>Nome</th>
    <th>Requisição</th>
    <th>Telefone</th>
    <th>Endereço</th>
    <th>Data/Hora</th>
    <th>Secretaria</th>
    <th>Status</th>
    <th style="width:260px;">Ações</th>
</tr>
</thead>
<tbody id="lista-reclamacoes">
<?php if(count($dados)>0): ?>
    <?php render_rows($dados); ?>
<?php else: ?>
<tr><td colspan="10" class="text-center">Nenhuma reclamação encontrada</td></tr>
<?php endif; ?>
</tbody>
</table>

<!-- Sentinela do scroll infinito -->
<div id="sentinela" class="py-3 text-center text-muted">
    Carregando mais...
</div>

<!-- Modal Confirmar Deleção -->
<div class="modal fade" id="modalConfirmDelete" tabindex="-1" aria-labelledby="modalDeleteLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="modalDeleteLabel"><i class="bi bi-exclamation-triangle"></i> Confirmar exclusão</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <p>Tem certeza de que deseja <strong>deletar</strong> esta requisição?</p>
        <ul class="list-group">
          <li class="list-group-item"><strong>ID:</strong> <span id="del-id"></span></li>
          <li class="list-group-item"><strong>Nome:</strong> <span id="del-nome"></span></li>
          <li class="list-group-item"><strong>Telefone:</strong> <span id="del-telefone"></span></li>
          <li class="list-group-item"><strong>Endereço:</strong> <span id="del-endereco"></span></li>
        </ul>
        <div class="alert alert-warning mt-3 mb-0"><i class="bi bi-info-circle"></i> Esta ação não poderá ser desfeita.</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-danger" id="btn-confirm-delete">Deletar</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal ID -->
<div class="modal fade" id="modalPdfId" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
<div class="modal-dialog">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title" id="modalLabel">Digite o ID da Reclamação</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
</div>
<div class="modal-body">
<input type="number" id="pdfIdInput" class="form-control" placeholder="ID da reclamação">
</div>
<div class="modal-footer">
<button type="button" class="btn btn-primary" id="pdfIdOk">OK</button>
<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
</div>
</div>
</div>
</div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// -------------------- Seleção persistida + overrides de status --------------------
let selecionados = JSON.parse(localStorage.getItem('reclamacoesSelecionadas')) || [];
let statusOverrides = JSON.parse(localStorage.getItem('statusOverrides')) || {};
function saveSelecionados(){ localStorage.setItem('reclamacoesSelecionadas', JSON.stringify(selecionados)); }
function saveOverrides(){ localStorage.setItem('statusOverrides', JSON.stringify(statusOverrides)); }

// aplica seleção e overrides nos itens já renderizados (inicial)
(function applyInitialSelectionsAndOverrides(){
  document.querySelectorAll('.chk-reclamacao').forEach(chk => {
    if (selecionados.includes(chk.value)) chk.checked = true;
  });
  document.querySelectorAll('tr[id^="row-"]').forEach(tr => {
    const id = tr.id.replace('row-','');
    const btn = tr.querySelector('.btn-status');
    if (btn && statusOverrides[id]) {
      applyStatus(btn, statusOverrides[id]);
    }
  });
})();

// checkbox -> manter lista de selecionados
document.querySelectorAll('.chk-reclamacao').forEach(chk => {
  chk.addEventListener('change', function() {
    if (this.checked) {
      if (!selecionados.includes(this.value)) selecionados.push(this.value);
    } else {
      selecionados = selecionados.filter(id => id !== this.value);
      // opcional: remover override ao desmarcar
      // delete statusOverrides[this.value]; saveOverrides();
    }
    saveSelecionados();
  });
});

// Selecionar por intervalo de ID (marca visíveis e registra seleção para futuros)
document.getElementById('btnSelecionarIntervalo')?.addEventListener('click', () => {
  let inicio = parseInt(document.getElementById('idInicio').value);
  let fim    = parseInt(document.getElementById('idFim').value);

  if (isNaN(inicio) || isNaN(fim)) {
    alert('Preencha os dois campos de ID.');
    return;
  }
  if (inicio > fim) [inicio, fim] = [fim, inicio];

  selecionados = [];
  for (let i = inicio; i <= fim; i++) selecionados.push(String(i));
  saveSelecionados();

  document.querySelectorAll('.chk-reclamacao').forEach(chk => {
    chk.checked = selecionados.includes(chk.value);
  });

  alert('Seleção aplicada.');
});

// -------------------- PDF utilidades (se usar) --------------------
document.getElementById('btn-pdf-selecionados')?.addEventListener('click', (e) => {
  e.preventDefault();
  if(selecionados.length===0){alert("Selecione ao menos uma reclamação."); return;}
  window.open('gerar_pdf.php?tipo=selecionados&ids=' + selecionados.join(','),'_blank');
});
document.getElementById('btn-pdf-id')?.addEventListener('click', (e) => {
  e.preventDefault();
  var myModal = new bootstrap.Modal(document.getElementById('modalPdfId'));
  myModal.show();
});
document.getElementById('pdfIdOk')?.addEventListener('click', () => {
  var id = document.getElementById('pdfIdInput').value;
  if(id){
    window.open('gerar_pdf.php?tipo=id&id='+id,'_blank');
    bootstrap.Modal.getInstance(document.getElementById('modalPdfId')).hide();
  } else {
    alert('Digite um ID válido.');
  }
});

// -------------------- Deletar (modal + POST) --------------------
function bindDeleteButtons(scope=document) {
  scope.querySelectorAll('.btn-deletar').forEach(btn => {
    btn.addEventListener('click', () => {
      const id = btn.dataset.id;
      document.getElementById('del-id').textContent       = btn.dataset.id || '';
      document.getElementById('del-nome').textContent     = btn.dataset.nome || '';
      document.getElementById('del-telefone').textContent = btn.dataset.telefone || '';
      document.getElementById('del-endereco').textContent = btn.dataset.endereco || '';

      const modal = new bootstrap.Modal(document.getElementById('modalConfirmDelete'));
      modal.show();

      const confirmBtn = document.getElementById('btn-confirm-delete');
      confirmBtn.onclick = () => {
        fetch('deletar_requisicao.php', {
          method: 'POST',
          headers: {'Content-Type':'application/json'},
          body: JSON.stringify({ id })
        })
        .then(r => r.json())
        .then(res => {
          if(res && res.success){
            const tr = document.getElementById('row-' + id);
            if (tr) tr.remove();
            bootstrap.Modal.getInstance(document.getElementById('modalConfirmDelete')).hide();
          } else {
            alert((res && (res.error || res.msg)) || 'Erro ao deletar requisição.');
          }
        })
        .catch(() => alert('Falha na comunicação com o servidor.'));
      };
    });
  });
}
bindDeleteButtons(document);

// -------------------- STATUS: fluxo e bind --------------------
const statusFlow = ['Pendente', 'Em Andamento', 'Concluido'];

function applyStatus(btn, newStatus){
  btn.textContent = newStatus;
  btn.classList.remove('pendente','em-andamento','concluido');
  btn.classList.add(newStatus.toLowerCase().replace(' ', '-'));
}
function bindStatusButtons(scope=document){
  scope.querySelectorAll('.btn-status').forEach(btn => {
    btn.addEventListener('click', () => {
      const current = btn.textContent.trim();
      const idx = statusFlow.indexOf(current);
      const next = statusFlow[(idx + 1) % statusFlow.length];
      applyStatus(btn, next);
      // atualiza override do item clicado
      const id = String(btn.dataset.id);
      statusOverrides[id] = next;
      saveOverrides();
    });
  });
}
bindStatusButtons(document);

// -------------------- Marcar selecionados como Concluído (frontend + override persistido) --------------------
document.getElementById('btn-marcar-concluido')?.addEventListener('click', () => {
  const checks = Array.from(document.querySelectorAll('.chk-reclamacao:checked'));

  // se não houver check visível, usa a lista de selecionados (que pode conter itens não renderizados)
  const idsParaConcluir = checks.length > 0 ? checks.map(chk => chk.value) : selecionados.slice();
  if (idsParaConcluir.length === 0) return alert('Selecione ao menos uma reclamação.');

  // Atualiza visuais dos itens já renderizados
  idsParaConcluir.forEach(id => {
    const btn = document.querySelector(`tr#row-${id} .btn-status`);
    if (btn) applyStatus(btn, 'Concluido');
  });

  // Registra override para TODOS os ids (mesmo não renderizados)
  idsParaConcluir.forEach(id => { statusOverrides[id] = 'Concluido'; });
  saveOverrides();

  alert('Itens selecionados marcados como Concluído.\nClique em "Alterar Status" para salvar no banco.');
});

// -------------------- Salvar status em lote (união DOM + overrides) --------------------
document.getElementById('btn-alterar')?.addEventListener('click', () => {
  const alteradosMap = {};

  // A) status atuais dos itens visíveis
  document.querySelectorAll('.btn-status').forEach(btn => {
    const id = String(btn.dataset.id);
    alteradosMap[id] = btn.textContent.trim();
  });

  // B) aplica overrides (itens visíveis ou não)
  Object.keys(statusOverrides).forEach(id => {
    alteradosMap[id] = statusOverrides[id];
  });

  const alterados = Object.keys(alteradosMap).map(id => ({ id, status: alteradosMap[id] }));
  if (alterados.length === 0) return alert('Nenhum registro encontrado!');

  $.post('atualizar_status.php', { alterados }, function(response) {
    if (response && response.success) {
      // limpeza dos overrides após salvar no banco
      statusOverrides = {};
      saveOverrides();
      alert('Status atualizados com sucesso!');
      location.reload();
    } else {
      alert((response && (response.error || response.msg)) || 'Erro ao atualizar status!');
    }
  }, 'json');
});

// -------------------- Atualizar Lat/Long --------------------
document.getElementById('btn-atualizar-latlon')?.addEventListener('click', () => {
  $.post('atualizar_lat_long.php', {}, function(response) {
    try {
      if (response && response.success) {
        alert('Lat/Long atualizadas com sucesso!');
        location.reload();
      } else {
        alert((response && (response.error || response.msg)) || 'Erro ao atualizar Lat/Long!');
      }
    } catch(e) {
      alert('Resposta inválida do servidor ao atualizar Lat/Long.');
    }
  }, 'json').fail(function() {
    window.location.href = 'atualizar_lat_long.php';
  });
});

// -------------------- Recalcular ordem --------------------
document.getElementById('btn-recalcular')?.addEventListener('click', () => {
  $.post('recalcular_ordem.php', {}, function(response) {
    if(response && response.success){
      location.reload();
    } else {
      alert((response && (response.error || response.msg)) || 'Erro ao recalcular ordem!');
    }
  }, 'json');
});

// -------------------- Scroll infinito --------------------
(() => {
  const tbody = document.getElementById('lista-reclamacoes');
  const sentinela = document.getElementById('sentinela');

  let pagina      = <?= (int)$pagina ?>;                   
  const totalPags = <?= (int)$total_paginas ?>;
  let temMais     = (totalPags > 1);
  let carregando  = false;

  const baseUrl = window.location.pathname;
  const params  = new URLSearchParams(window.location.search);

  function urlProximaPagina(p) {
    const cp = new URLSearchParams(params.toString());
    cp.set('pagina', p);
    cp.set('ajax', '1');
    return `${baseUrl}?${cp.toString()}`;
  }

  async function carregarMais() {
    if (!temMais || carregando) return;
    carregando = true;

    try {
      const prox = pagina + 1;
      const resp = await fetch(urlProximaPagina(prox), { headers: { 'X-Requested-With': 'fetch' } });
      const data = await resp.json();

      if (data && data.success) {
        const temp = document.createElement('tbody');
        temp.innerHTML = data.html || '';
        [...temp.children].forEach(tr => tbody.appendChild(tr));

        // Rebind nos novos botões (delete + status)
        bindDeleteButtons(tbody);
        bindStatusButtons(tbody);

        // Reaplica seleção e overrides aos novos itens
        temp.querySelectorAll('tr[id^="row-"]').forEach(tr => {
          const id = tr.id.replace('row-','');
          const chk = tr.querySelector('.chk-reclamacao');
          if (chk && selecionados.includes(chk.value)) chk.checked = true;

          const btn = tr.querySelector('.btn-status');
          if (btn && statusOverrides[id]) {
            applyStatus(btn, statusOverrides[id]);
          }
        });

        pagina = prox;
        temMais = !!data.temMais;

        if (!temMais) {
          sentinela.textContent = 'Fim da lista';
          observer.unobserve(sentinela);
        }
      } else {
        temMais = false;
        sentinela.textContent = 'Erro ao carregar.';
      }
    } catch(e) {
      temMais = false;
      sentinela.textContent = 'Erro de rede.';
    } finally {
      carregando = false;
    }
  }

  const observer = new IntersectionObserver(entries => {
    entries.forEach(entry => entry.isIntersecting && carregarMais());
  }, { root: null, rootMargin: '600px 0px', threshold: 0 });

  function ligarObserver() {
    observer.disconnect();
    const alvo = document.getElementById('sentinela');
    if (alvo && temMais) observer.observe(alvo);
  }

  if (temMais) ligarObserver();
  else {
    const s = document.getElementById('sentinela');
    if (s) s.textContent = 'Fim da lista';
  }

  window.addEventListener('resize', ligarObserver);
  window.addEventListener('load', ligarObserver);
})();
</script>
</body>
</html>
