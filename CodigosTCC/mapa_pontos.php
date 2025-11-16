<?php
include 'verifica_login.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'conexao.php'; // conexão com o banco


$sql = "
    SELECT r.id, r.lat, r.lon
    FROM reclamacoes r
    WHERE r.lat IS NOT NULL
      AND r.lon IS NOT NULL
      AND (
            r.status_reclamacao IS NULL
         OR r.status_reclamacao = ''
         OR UPPER(r.status_reclamacao) IN ('PENDENTE', 'EM ANDAMENTO')
      )
";

$result = $conn->query($sql);

$coordenadas = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $coordenadas[] = [
            'id'  => (int)$row['id'],
            'lat' => (float)$row['lat'],
            'lng' => (float)$row['lon'],
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <link rel="icon" href="images/icone.ico">
    <title>Mapa de Reclamações</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
    <link rel="stylesheet" href="estilos.css">
    <style>
        /* Garante altura para o mapa caso não esteja no estilos.css */
        #map { height: 75vh; width: 100%; }
        body { background: #f8f9fa; }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php"><i class="bi bi-building"></i> Ouvidoria</a>
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
                <li class="nav-item"><a class="nav-link active" href="mapa_pontos.php"><i class="bi bi-map"></i> Mapa de Reclamações</a></li>
                <li class="nav-item"><a class="nav-link" href="visualizar_ordem_distancia.php"><i class="bi bi-map"></i> Ver por Distância</a></li>
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

<!-- Mapa -->
<div id="map"></div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script>
    const pontos = <?= json_encode($coordenadas, JSON_NUMERIC_CHECK) ?> || [];

    const map = L.map('map').setView([-14.235, -51.9253], 4);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    pontos.forEach(p => {
        L.marker([p.lat, p.lng])
         .addTo(map)
         .bindPopup(`ID: ${p.id}`);
        
        // .bindPopup(`<strong>ID:</strong> ${p.id}<br><a href="cadastrar_requisicao.php?id=${p.id}">Abrir</a>`);
    });

    if (pontos.length > 0) {
        const bounds = pontos.map(p => [p.lat, p.lng]);
        map.fitBounds(bounds, {padding: [20,20]});
    }
</script>
</body>
</html>
