<?php
include 'conexao.php';
require 'vendor/autoload.php';
include 'verifica_login.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Função para calcular distância entre coordenadas
function haversine($lat1, $lon1, $lat2, $lon2) {
    $R = 6371; // Raio da Terra em km
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $R * $c; // distância em km
}

// 1. Pega todas as reclamações
$sqlReclamacoes = "SELECT * FROM reclamacoes WHERE lat IS NOT NULL AND lon IS NOT NULL ORDER BY data_hora ASC";
$result = $conn->query($sqlReclamacoes);
if ($result->num_rows == 0) die("Nenhuma reclamação encontrada.");

// Transformar em array
$reclamacoes = [];
while($row = $result->fetch_assoc()) {
    $reclamacoes[$row['id']] = $row;
}

// Arrays para controle
$visitadas = [];
$ordemReclamacoes = [];

// Começa pela primeira reclamação (ordenada por data_hora)
$idsReclamacoes = array_keys($reclamacoes);
$atual = $reclamacoes[$idsReclamacoes[0]];

// 2. Percorre por proximidade
while ($atual) {
    $visitadas[] = $atual['id'];
    $ordemReclamacoes[] = $atual;

    $maisProxima = null;
    $distanciaMin = PHP_FLOAT_MAX;

    foreach ($reclamacoes as $r) {
        if (in_array($r['id'], $visitadas)) continue;
        $dist = haversine($atual['lat'], $atual['lon'], $r['lat'], $r['lon']);
        if ($dist < $distanciaMin) {
            $distanciaMin = $dist;
            $maisProxima = $r;
        }
    }

    $atual = $maisProxima;
}

// 3. Gera planilha Excel
$arquivo = "reclamacoes_proximidade_" . date("Y-m-d_H-i-s") . ".xlsx";
header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
header("Content-Disposition: attachment; filename=\"$arquivo\"");
header("Cache-Control: max-age=0");

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Cabeçalho
$sheet->setCellValue('A1', 'ID')
      ->setCellValue('B1', 'Nome')
      ->setCellValue('C1', 'Endereço')
      ->setCellValue('D1', 'Latitude')
      ->setCellValue('E1', 'Longitude')
      ->setCellValue('F1', 'Telefone')
      ->setCellValue('G1', 'Tipo Requisição')
      ->setCellValue('H1', 'Data/Hora')
      ->setCellValue('I1', 'Secretaria');

$row = 2;
foreach ($ordemReclamacoes as $rec) {
    $sheet->setCellValue('A'.$row, $rec['id'])
          ->setCellValue('B'.$row, $rec['nome'])
          ->setCellValue('C'.$row, $rec['endereco'])
          ->setCellValue('D'.$row, $rec['lat'])
          ->setCellValue('E'.$row, $rec['lon'])
          ->setCellValue('F'.$row, $rec['telefone'])
          ->setCellValue('G'.$row, $rec['tipo_requisicao'])
          ->setCellValue('H'.$row, $rec['data_hora'])
          ->setCellValue('I'.$row, $rec['secretaria']);
    $row++;
}

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
