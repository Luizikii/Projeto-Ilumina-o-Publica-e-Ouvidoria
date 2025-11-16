<?php
include 'conexao.php';
include 'verifica_login.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inicializa variáveis de mensagem
$mensagem = '';
$mensagem_tipo = ''; // 'success' ou 'danger'

// Consulta os tipos de requisição do banco
$sql = "SELECT id, nome FROM tipos_requisicao ORDER BY nome";
$result = $conn->query($sql);

// Consulta as secretarias do banco
$sql_secretarias = "SELECT id_secretaria, nome_secretaria FROM secretaria ORDER BY nome_secretaria";
$result_secretarias = $conn->query($sql_secretarias);

$secretarias = [];
if ($result_secretarias && $result_secretarias->num_rows > 0) {
    while ($row = $result_secretarias->fetch_assoc()) {
        $secretarias[] = $row;
    }
}

$tipos = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $tipos[] = $row;
    }
}
$id_secretaria = isset($_POST['secretaria']) && $_POST['secretaria'] !== '' 
                 ? (int) $_POST['secretaria'] 
                 : null;

// Processa envio do formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'] ?? '';
    $cpf = $_POST['cpf'] ?? '';
    $tipo_requisicao = $_POST['tipo_requisicao'] ?? '';
    $telefone = $_POST['telefone'] ?? '';
    $endereco = $_POST['endereco'] ?? '';
    $numero_endereco = $_POST['numero_endereco'] ?? '';
    $lat = $_POST['lat'] ?? null;
    $lon = $_POST['lon'] ?? null;
    $data_hora = $_POST['data_hora'] ?? '';
    $secretaria = $_POST['secretaria'] ?? '';

    // Valida lat/lon
    if ($lat === null || $lon === null) {
        $mensagem = "Erro: Latitude e longitude não foram fornecidas.";
        $mensagem_tipo = "danger";
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO reclamacoes (nome, cpf, tipo_requisicao, telefone, endereco, numero_endereco, lat, lon, data_hora, id_secretaria)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        if ($stmt) {
            $stmt->bind_param(
                "ssisssddsi",
                $nome,
                $cpf,
                $tipo_requisicao,
                $telefone,
                $endereco,
                $numero_endereco,
                $lat,
                $lon,
                $data_hora,
                $id_secretaria
            );


            if ($stmt->execute()) {
                $mensagem = "Reclamação enviada com sucesso!";
                $mensagem_tipo = "success";
            } else {
                $mensagem = "Erro ao enviar a reclamação: " . $stmt->error;
                $mensagem_tipo = "danger";
            }

            $stmt->close();
        } else {
            $mensagem = "Erro ao preparar a requisição: " . $conn->error;
            $mensagem_tipo = "danger";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <link rel="icon"  href="images/icone.ico">
    <title>Formulário de Reclamação</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <link rel="stylesheet" href="estilos.css">

</head>
<body class="bg-light">
<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <!-- Logo -->
        <a class="navbar-brand" href="index.php">
            <i class="bi bi-building"></i> Ouvidoria
        </a>

        <!-- Botão hamburguer -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Menu -->
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="cadastro_usuario.php"><i class="bi bi-person-plus"></i> User</a></li>
                <li class="nav-item"><a class="nav-link" href="cadastro_secretaria.php"><i class="bi bi-diagram-3"></i> Secretaria</a></li>
                <li class="nav-item"><a class="nav-link" href="cadastro_setor.php"><i class="bi bi-geo-alt"></i> Setor</a></li>
                <li class="nav-item"><a class="nav-link" href="cadastrar_tipo_requisicao.php"><i class="bi bi-card-list"></i> Tipo Requisição</a></li>
                <li class="nav-item"><a class="nav-link" href="visualizar_reclamacoes.php"><i class="bi bi-eye"></i> Ver Requisição</a></li>
                <li class="nav-item"><a class="nav-link active" href="cadastrar_requisicao.php"><i class="bi bi-plus-circle"></i> Cad. Requisições</a></li>
                <li class="nav-item"><a class="nav-link" href="mapa_pontos.php"><i class="bi bi-map"></i> Mapa de Reclamações</a></li>
                <li class="nav-item"><a class="nav-link" href="visualizar_ordem_distancia.php"><i class="bi bi-map"></i> Ver por Distância</a></li>
            </ul>

            <!-- Usuário com dropdown -->
            <ul class="navbar-nav ms-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle"></i>
                        <?php echo $logado ? htmlspecialchars($nome_usuario) : 'Usuário'; ?>
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
    <h2 class=" text-center mb-4">Registrar Reclamação</h2>

    
    <?php if ($mensagem): ?>
        <div class="alert alert-<?= $mensagem_tipo ?>"><?= htmlspecialchars($mensagem) ?></div>
    <?php endif; ?>

    <form method="POST" class="bg-white p-4 shadow-sm rounded">
        <div class="mb-3">
            <label for="nome" class="form-label">Nome</label>
            <input type="text" class="form-control" id="nome" name="nome" required>
        </div>
        
        <div class="mb-3">
            <label for="cpf" class="form-label">CPF</label>
            <input type="text" class="form-control" id="cpf" name="cpf" required>
        </div>

        <div class="mb-3">
            <label for="tipo_requisicao" class="form-label">Tipo de Requisição</label>
            <select class="form-select" id="tipo_requisicao" name="tipo_requisicao" required>
                <option value="">Selecione...</option>
                <?php foreach ($tipos as $tipo): ?>
                    <option value="<?= $tipo['id'] ?>"><?= htmlspecialchars($tipo['nome']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="telefone" class="form-label">Telefone</label>
            <input type="text" class="form-control" id="telefone" name="telefone" required>
        </div>

        <div class="mb-3">
          <div class="row">
            <!-- Linha dos labels -->
            <div class="col-8">
              <label for="endereco" class="form-label">Endereço da Requisição</label>
            </div>
            <div class="col-4">
              <label for="numero_endereco" class="form-label">Número do Endereço</label>
            </div>
            
          </div>
          <div class="row">
            <!-- Linha dos inputs -->
            <div class="col-8">
              <input type="text" class="form-control" id="endereco" name="endereco" readonly required>
              <input type="hidden" id="lat" name="lat">
              <input type="hidden" id="lon" name="lon">
            </div>
            <div class="col-4">
              <input type="text" class="form-control" id="numero_endereco" name="numero_endereco" required>
            </div>
          </div>
        </div>

        <div class="mb-3">
            <button type="button"
                    class="btn btn-outline-secondary"
                    data-bs-toggle="modal"
                    data-bs-target="#mapModal"
                    data-target-input="#endereco"
                    data-lat-input="#lat"
                    data-lon-input="#lon">
                Selecionar rua no mapa
            </button>
        </div>

        <!-- Modal do mapa -->
        <div class="modal fade" id="mapModal" tabindex="-1" aria-labelledby="mapModalLabel" aria-hidden="true">
          <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="mapModalLabel">Escolha o endereço</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
              </div>
              <div class="modal-body">
                <div id="map"></div>
              </div>
            </div>
          </div>
        </div>

        <div class="mb-3">
            <label for="data_hora" class="form-label">Data e Hora</label>
            <input type="datetime-local" class="form-control" id="data_hora" name="data_hora" required>
        </div>

        <div class="mb-3">
            <label for="secretaria" class="form-label">Secretaria Responsável</label>
            <select class="form-select" id="secretaria" name="secretaria" required>
                <option value="">Selecione...</option>
                <?php foreach ($secretarias as $sec): ?>
                    <option value="<?= $sec['id_secretaria'] ?>"><?= htmlspecialchars($sec['nome_secretaria']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>


        <div id="debug" style="background:#eee; padding:8px; margin:10px 0; font-size:12px; border:1px solid #ccc;">
            Nenhuma consulta ainda...
        </div>

        <button type="submit" class="btn btn-primary">Enviar Reclamação</button>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

<script>
$(document).ready(function() {
    $('#cpf').mask('000.000.000-00');
    $('#tipo_requisicao').select2({ placeholder: "Selecione ou pesquise", width: '100%' });
    $('#telefone').mask('(00) 00000-0000');
});

// globals
var map = null;
var marker = null;
var addressTargetSelector = '#endereco';
var latTargetSelector = '#lat';
var lonTargetSelector = '#lon';

function setInputValue(selector, value) {
  if (!selector) return;
  const el = document.querySelector(selector);
  if (!el) return;
  if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA' || el.isContentEditable) {
    el.value = value;
  } else {
    el.textContent = value;
  }
  if (window.jQuery) {
    try { window.jQuery(selector).val(value).trigger('change'); } catch (e) {}
  }
}

function pickStreet(data) {
  const a = (data && data.address) ? data.address : {};
  const streetCandidates = ['road','street','pedestrian','residential','footway','cycleway','path','addr:street','house','hamlet'];
  for (const k of streetCandidates) {
    if (a[k]) {
      if (a.house_number) return `${a[k]}, ${a.house_number}`;
      return a[k];
    }
  }
  const locality = a.suburb || a.neighbourhood || a.village || a.town || a.city || a.county;
  if (locality) return locality;
  if (data && data.name) return data.name;
  if (data && data.display_name) return data.display_name.split(',')[0];
  return null;
}

// Modal aberto
$('#mapModal').on('show.bs.modal', function (e) {
  const trigger = e.relatedTarget;
  if (trigger && trigger.dataset) {
    addressTargetSelector = trigger.dataset.targetInput || '#endereco';
    latTargetSelector = trigger.dataset.latInput || '#lat';
    lonTargetSelector = trigger.dataset.lonInput || '#lon';
  }
});

// Ao mostrar modal: cria mapa uma vez
$('#mapModal').on('shown.bs.modal', function () {
  setTimeout(function () {
    if (!map) {
      map = L.map('map').setView([-21.2338573, -45.236662], 15);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap contributors' }).addTo(map);
    } else {
      map.invalidateSize();
    }

    map.off('click');
    map.on('click', async function (e) {
        const lat = parseFloat(e.latlng.lat.toFixed(7));
        const lon = parseFloat(e.latlng.lng.toFixed(7));

        if (marker) map.removeLayer(marker);
        marker = L.marker([lat, lon]).addTo(map);

        setInputValue(latTargetSelector, lat);
        setInputValue(lonTargetSelector, lon);

        const proxyUrl = `nominatim_proxy.php?lat=${lat}&lon=${lon}`;
        document.getElementById('debug').innerText = "Chamando URL via proxy:\n" + proxyUrl;

        try {
            console.log("Iniciando fetch para Nominatim via proxy...");
            const response = await fetch(proxyUrl);
            console.log("Fetch completado:", response);

            if (!response.ok) {
                throw new Error(`HTTP ${response.status} ${response.statusText}`);
            }

            const data = await response.json();
            console.log("JSON recebido:", data);

            if (data.error) {
                throw new Error(data.error);
            }

            // Pega a rua
            const rua = pickStreet(data) || data.display_name || "Endereço não identificado";
            console.log("Rua extraída:", rua);

            setInputValue(addressTargetSelector, rua);

            // Pega o número da casa (se existir)
            if (data.address && data.address.house_number) {
                setInputValue('#numero_endereco', data.address.house_number);
            } else {
                setInputValue('#numero_endereco', ''); // vazio se não existir
            }
            
            // Fecha o modal
            const modalEl = document.getElementById('mapModal');
            const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
            modal.hide();

        } catch (err) {
            console.error('Erro ao consultar o endereço no mapa:', err);
            document.getElementById('debug').innerText += "\n\nErro ao consultar o endereço. Veja console para detalhes.";
            alert('Erro ao consultar o endereço no mapa. Veja o console para mais detalhes.');
        }
    }); // fecha map.on('click')
  }, 200); // fecha setTimeout
}); // fecha $('#mapModal').on('shown.bs.modal')

// Antes do envio, concatena número ao endereço se não estiver presente
$('form').on('submit', function(e) {
    const enderecoInput = $('#endereco');
    const numeroInput = $('#numero_endereco');
    
    let endereco = enderecoInput.val().trim();
    const numero = numeroInput.val().trim();
    
    if (numero && !endereco.endsWith(numero)) {
        endereco += ', ' + numero;
        enderecoInput.val(endereco);
    }
});

</script>
</body>
</html>
