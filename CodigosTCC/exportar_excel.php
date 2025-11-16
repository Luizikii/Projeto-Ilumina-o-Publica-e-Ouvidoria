<?php
declare(strict_types=1);

/**
 * Exporta reclamações para XLSX, compatível com o schema informado:
 *
 * Tabelas/colunas usadas:
 * - reclamacoes: id, nome, tipo_requisicao, telefone, endereco, numero_endereco,
 *                lat, lon, data_hora, id_secretaria, status_reclamacao,
 *                numero_protocolo, numero_oficio, cpf
 * - tipos_requisicao: id, nome
 * - secretaria: id_secretaria, nome_secretaria
 */

ini_set('display_errors', '0');          // não exibir erros na resposta (evita corromper o XLSX)
ini_set('log_errors', '1');              // logar no error_log
ini_set('zlib.output_compression', '0'); // XLSX é ZIP; evite compressão dupla
date_default_timezone_set('America/Sao_Paulo');

// Limpa qualquer saída residual (BOM/whitespace/echo acidental)
while (ob_get_level() > 0) { ob_end_clean(); }

// --- Includes (use caminhos absolutos para evitar problemas de path) ---
require __DIR__ . '/conexao.php';
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/verifica_login.php'; // ATENÇÃO: não pode imprimir nada

if (isset($conn) && method_exists($conn, 'set_charset')) {
    @$conn->set_charset('utf8mb4');
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// -------------------- Monta a consulta --------------------
$baseSelect = "
    SELECT
        r.id,
        r.nome,
        t.nome              AS nome_tipo_requisicao,
        r.telefone,
        r.endereco,
        r.numero_endereco,
        r.lat,
        r.lon,
        r.data_hora,
        s.nome_secretaria   AS nome_secretaria,
        r.status_reclamacao,
        r.numero_protocolo,
        r.numero_oficio,
        r.cpf
    FROM reclamacoes r
    LEFT JOIN tipos_requisicao t ON r.tipo_requisicao = t.id
    LEFT JOIN secretaria       s ON r.id_secretaria   = s.id_secretaria
";

if (!empty($_GET['ids'])) {
    // IDs selecionados (sanitizados)
    $ids = array_filter(array_map('intval', explode(',', $_GET['ids'])));
    $ids_list = $ids ? implode(',', $ids) : '0';
    $sql = $baseSelect . " WHERE r.id IN ($ids_list) ORDER BY r.id ASC";
} elseif (!empty($_GET['recentes']) && intval($_GET['recentes']) > 0) {
    // Últimos N registros
    $limite = intval($_GET['recentes']);
    $sql = $baseSelect . " ORDER BY r.id DESC LIMIT $limite";
} else {
    // Todos os registros
    $sql = $baseSelect . " ORDER BY r.id DESC";
}

$result = $conn->query($sql);
if ($result === false) {
    http_response_code(500);
    error_log('[exportar_reclamacoes_xlsx][SQL ERROR] ' . $conn->error);
    exit;
}

// -------------------- Criação da planilha --------------------
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Cabeçalho
$sheet->setCellValue('A1', 'ID')
      ->setCellValue('B1', 'Nome')
      ->setCellValue('C1', 'Tipo de Requisição')
      ->setCellValue('D1', 'Telefone')
      ->setCellValue('E1', 'Endereço')
      ->setCellValue('F1', 'Número')
      ->setCellValue('G1', 'Lat')
      ->setCellValue('H1', 'Lon')
      ->setCellValue('I1', 'Data/Hora')
      ->setCellValue('J1', 'Secretaria')
      ->setCellValue('K1', 'Status')
      ->setCellValue('L1', 'Protocolo')
      ->setCellValue('M1', 'Ofício')
      ->setCellValue('N1', 'CPF');

// Linhas
$row = 2;
while ($dados = $result->fetch_assoc()) {
    $sheet->setCellValue('A' . $row, $dados['id'] ?? '')
          ->setCellValue('B' . $row, $dados['nome'] ?? '')
          ->setCellValue('C' . $row, $dados['nome_tipo_requisicao'] ?? '')
          ->setCellValue('D' . $row, $dados['telefone'] ?? '')
          ->setCellValue('E' . $row, $dados['endereco'] ?? '')
          ->setCellValue('F' . $row, $dados['numero_endereco'] ?? '')
          ->setCellValue('G' . $row, $dados['lat'] ?? '')
          ->setCellValue('H' . $row, $dados['lon'] ?? '')
          ->setCellValue('I' . $row, $dados['data_hora'] ?? '')
          ->setCellValue('J' . $row, $dados['nome_secretaria'] ?? '')
          ->setCellValue('K' . $row, $dados['status_reclamacao'] ?? '')
          ->setCellValue('L' . $row, $dados['numero_protocolo'] ?? '')
          ->setCellValue('M' . $row, $dados['numero_oficio'] ?? '')
          ->setCellValue('N' . $row, $dados['cpf'] ?? '');
    $row++;
}

// -------------------- Cabeçalhos para download --------------------
$arquivo = 'reclamacoes_' . date('Y-m-d_H-i-s') . '.xlsx';

// Garante que nada foi enviado antes dos headers
while (ob_get_level() > 0) { ob_end_clean(); }

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . basename($arquivo) . '"');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: public');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');

flush();

// -------------------- Gerar o arquivo Excel --------------------
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
