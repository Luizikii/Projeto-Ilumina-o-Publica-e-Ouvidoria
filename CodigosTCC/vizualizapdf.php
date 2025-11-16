<?php 
include 'conexao.php';
include 'verifica_login.php';

// -------------------- Configurações --------------------
$limite = 10; // Reclamações por página
$pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$offset = ($pagina - 1) * $limite;

// -------------------- Filtros --------------------
$ordem_coluna = $_GET['ordem_coluna'] ?? 'id';
$ordem_coluna = in_array($ordem_coluna, ['id','nome','data_hora']) ? $ordem_coluna : 'id';

$ordem_direcao = $_GET['ordem_direcao'] ?? 'asc';
$ordem_direcao = strtolower($ordem_direcao) === 'desc' ? 'DESC' : 'ASC';

$filtro_secretaria = $_GET['filtro_secretaria'] ?? '';
$filtro_tipo = $_GET['filtro_tipo'] ?? '';

// -------------------- Monta WHERE --------------------
$where = "WHERE 1=1";
if ($filtro_secretaria !== '') {
    $where .= " AND r.secretaria = '". $conn->real_escape_string($filtro_secretaria) ."'";
}
if ($filtro_tipo !== '') {
    $where .= " AND r.tipo_requisicao = '". intval($filtro_tipo) ."'";
}

// -------------------- Contar total --------------------
$total_sql = "SELECT COUNT(*) as total FROM reclamacoes r $where";
$total_result = $conn->query($total_sql);
$total = $total_result->fetch_assoc()['total'];
$total_paginas = ceil($total / $limite);

// -------------------- Buscar registros --------------------
$sql = "SELECT r.*, t.nome AS tipo_nome
        FROM reclamacoes r
        LEFT JOIN tipos_requisicao t ON r.tipo_requisicao = t.id
        $where
        ORDER BY $ordem_coluna $ordem_direcao
        LIMIT $limite OFFSET $offset";
$result = $conn->query($sql);

// -------------------- Buscar secretarias --------------------
$secretarias_res = $conn->query("SELECT DISTINCT secretaria FROM reclamacoes WHERE secretaria IS NOT NULL AND secretaria <> '' ORDER BY secretaria");
$secretarias = [];
while($row = $secretarias_res->fetch_assoc()) {
    $secretarias[] = $row['secretaria'];
}

// -------------------- Buscar tipos --------------------
$tipos_res = $conn->query("SELECT id, nome FROM tipos_requisicao ORDER BY nome");
$tipos = [];
while($row = $tipos_res->fetch_assoc()) {
    $tipos[] = $row;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Visualizar Reclamações</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <h2 class="mb-4">Reclamações</h2>

    <!-- Filtros -->
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

    <!-- Filtro Secretaria -->
    <div class="col-auto">
        <select name="filtro_secretaria" class="form-select">
            <option value="">Todas Secretarias</option>
            <?php foreach($secretarias as $sec): ?>
                <option value="<?= htmlspecialchars($sec) ?>" <?= $filtro_secretaria==$sec?'selected':'' ?>><?= htmlspecialchars($sec) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- Filtro Tipo -->
    <div class="col-auto">
        <select name="filtro_tipo" class="form-select">
            <option value="">Todos os Tipos</option>
            <?php foreach($tipos as $tp): ?>
                <option value="<?= $tp['id'] ?>" <?= $filtro_tipo==$tp['id']?'selected':'' ?>><?= htmlspecialchars($tp['nome']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col-auto">
        <button type="submit" class="btn btn-primary">Filtrar</button>
    </div>

    <!-- Selecionar intervalo de IDs -->
    <div class="col-auto">
        <input type="number" id="idInicio" class="form-control" placeholder="De ID">
    </div>
    <div class="col-auto">
        <input type="number" id="idFim" class="form-control" placeholder="Até ID">
    </div>
    <div class="col-auto">
        <button type="button" class="btn btn-secondary" id="btnSelecionarIntervalo">Selecionar Intervalo</button>
    </div>
</form>

    <!-- Tabela -->
    <table class="table table-bordered table-striped bg-white">
        <thead>
            <tr>
                <th>Selecionar</th>
                <th>ID</th>
                <th>Nome</th>
                <th>Tipo de Requisição</th>
                <th>Telefone</th>
                <th>Endereço</th>
                <th>Data/Hora</th>
                <th>Secretaria</th>
            </tr>
        </thead>
        <tbody>
            <?php if($result && $result->num_rows>0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                    <td>
                        <input type="checkbox" class="chk-reclamacao" value="<?= $row['id'] ?>">
                    </td>
                    <td><?= $row['id'] ?></td>
                    <td><?= htmlspecialchars($row['nome']) ?></td>
                    <td><?= htmlspecialchars($row['tipo_nome']) ?></td>
                    <td><?= htmlspecialchars($row['telefone']) ?></td>
                    <td><?= htmlspecialchars($row['endereco']) ?></td>
                    <td><?= $row['data_hora'] ?></td>
                    <td><?= htmlspecialchars($row['secretaria']) ?></td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="8" class="text-center">Nenhuma reclamação encontrada</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Botões -->
    <div class="mb-3">
        <button class="btn btn-danger" id="btn-pdf-selecionados">Gerar PDF Selecionados</button>
        <a href="gerar_pdf.php?tipo=20recentes" class="btn btn-success me-2">Gerar PDF das 21 mais recentes</a>
        <button class="btn btn-info" id="btn-pdf-id">Gerar PDF por ID</button>
        <a href="exportar_excel.php" class="btn btn-warning me-2">Exportar Excel</a>
        <a href="exportar_excel_10_recentes.php" class="btn btn-warning me-2">Exportar 10 Mais Recentes</a>
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

    <!-- Paginação -->
    <nav>
        <ul class="pagination justify-content-center">
            <li class="page-item <?= $pagina <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="?pagina=<?= $pagina-1 ?>&ordem_coluna=<?= $ordem_coluna ?>&ordem_direcao=<?= $ordem_direcao ?>&filtro_secretaria=<?= urlencode($filtro_secretaria) ?>&filtro_tipo=<?= urlencode($filtro_tipo) ?>">&laquo; Anterior</a>
            </li>
            <li class="page-item">
                <form method="GET" class="d-flex" style="max-width:180px; margin:0 10px;">
                    <input type="hidden" name="ordem_coluna" value="<?= $ordem_coluna ?>">
                    <input type="hidden" name="ordem_direcao" value="<?= $ordem_direcao ?>">
                    <input type="hidden" name="filtro_secretaria" value="<?= htmlspecialchars($filtro_secretaria) ?>">
                    <input type="hidden" name="filtro_tipo" value="<?= htmlspecialchars($filtro_tipo) ?>">
                    <input type="number" name="pagina" min="1" max="<?= $total_paginas ?>" class="form-control form-control-sm text-center" value="<?= $pagina ?>">
                    <button type="submit" class="btn btn-sm btn-primary ms-2">Ir</button>
                </form>
            </li>
            <li class="page-item disabled">
                <span class="page-link">Página <?= $pagina ?> de <?= $total_paginas ?></span>
            </li>
            <li class="page-item <?= $pagina >= $total_paginas ? 'disabled' : '' ?>">
                <a class="page-link" href="?pagina=<?= $pagina+1 ?>&ordem_coluna=<?= $ordem_coluna ?>&ordem_direcao=<?= $ordem_direcao ?>&filtro_secretaria=<?= urlencode($filtro_secretaria) ?>&filtro_tipo=<?= urlencode($filtro_tipo) ?>">Próxima &raquo;</a>
            </li>
        </ul>
    </nav>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Recupera seleções salvas
let selecionados = JSON.parse(localStorage.getItem('reclamacoesSelecionadas')) || [];

// Marcar checkboxes já salvos
document.querySelectorAll('.chk-reclamacao').forEach(chk => {
    if (selecionados.includes(chk.value)) {
        chk.checked = true;
    }
    chk.addEventListener('change', function() {
        if (this.checked) {
            if (!selecionados.includes(this.value)) selecionados.push(this.value);
        } else {
            selecionados = selecionados.filter(id => id !== this.value);
        }
        localStorage.setItem('reclamacoesSelecionadas', JSON.stringify(selecionados));
    });
});

// Botão PDF selecionados
document.getElementById('btn-pdf-selecionados').addEventListener('click', function() {
    if (selecionados.length === 0) {
        alert("Selecione ao menos uma reclamação.");
        return;
    }
    window.open('gerar_pdf.php?tipo=selecionados&ids=' + selecionados.join(','), '_blank');
});

// Botão abrir modal por ID
document.getElementById('btn-pdf-id').addEventListener('click', function() {
    var myModal = new bootstrap.Modal(document.getElementById('modalPdfId'));
    myModal.show();
});

// PDF por ID
document.getElementById('pdfIdOk').addEventListener('click', function() {
    var id = document.getElementById('pdfIdInput').value;
    if(id) {
        window.open('gerar_pdf.php?tipo=id&id=' + id, '_blank');
        var myModal = bootstrap.Modal.getInstance(document.getElementById('modalPdfId'));
        myModal.hide();
    } else {
        alert('Digite um ID válido.');
    }
});

// Selecionar intervalo de IDs (todos, não apenas os carregados)
document.getElementById('btnSelecionarIntervalo').addEventListener('click', function() {
    let inicio = parseInt(document.getElementById('idInicio').value);
    let fim = parseInt(document.getElementById('idFim').value);

    if (isNaN(inicio) || isNaN(fim)) {
        alert("Preencha os dois campos de ID.");
        return;
    }
    if (inicio > fim) {
        [inicio, fim] = [fim, inicio];
    }

    selecionados = [];
    for (let i = inicio; i <= fim; i++) {
        selecionados.push(i.toString());
    }

    localStorage.setItem('reclamacoesSelecionadas', JSON.stringify(selecionados));

    document.querySelectorAll('.chk-reclamacao').forEach(chk => {
        chk.checked = selecionados.includes(chk.value);
    });
});

// Restaurar seleção ao carregar
window.addEventListener('DOMContentLoaded', () => {
    let salvos = JSON.parse(localStorage.getItem('reclamacoesSelecionadas') || '[]');
    selecionados = salvos;

    document.querySelectorAll('.chk-reclamacao').forEach(chk => {
        chk.checked = selecionados.includes(chk.value);
    });
});
</script>
</body>
</html>
