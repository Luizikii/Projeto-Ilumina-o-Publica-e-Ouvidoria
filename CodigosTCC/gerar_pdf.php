<?php
include 'conexao.php';
require('tfpdf/tfpdf.php'); // Biblioteca tFPDF
include 'verifica_login.php';
$tipo = $_GET['tipo'] ?? '';
$ids = $_GET['ids'] ?? '';
$id = $_GET['id'] ?? '';

// Instancia o PDF
$pdf = new tFPDF('L', 'mm', 'A4');
$pdf->AddPage();

// Adiciona fonte UTF-8
$pdf->AddFont('Arial','','arial.ttf', true);
$pdf->SetFont('Arial','',12);

// Função para normalizar strings (acentos)
function normalizar($str) {
    $str = mb_strtolower($str,'UTF-8');
    $acentos = ['á','à','ã','â','ä','é','è','ê','ë','í','ì','î','ï','ó','ò','õ','ô','ö','ú','ù','û','ü','ç'];
    $sem_acentos = ['a','a','a','a','a','e','e','e','e','i','i','i','i','o','o','o','o','o','u','u','u','u','c'];
    return str_replace($acentos,$sem_acentos,$str);
}

if ($tipo === '20recentes') {
    // 21 reclamações mais recentes
    $sql = "SELECT r.*, t.nome AS tipo_nome FROM reclamacoes r
            LEFT JOIN tipos_requisicao t ON r.tipo_requisicao = t.id
            ORDER BY r.data_hora DESC
            LIMIT 21";
    $result = $conn->query($sql);

    // Cabeçalho tabela
    $pdf->Cell(10,8,'ID',1);
    $pdf->Cell(40,8,'Nome',1);
    $pdf->Cell(39,8,'Tipo',1);
    $pdf->Cell(35,8,'Telefone',1);
    $pdf->Cell(80,8,'Endereço',1);
    $pdf->Cell(43,8,'Data/Hora',1);
    $pdf->Cell(35,8,'Secretaria',1);
    $pdf->Ln();

    if ($result && $result->num_rows>0) {
        foreach($result as $row){
            $pdf->Cell(10,8,$row['id'],1);
            $pdf->Cell(40,8,$row['nome'],1);
            $pdf->Cell(39,8,$row['tipo_nome'],1);
            $pdf->Cell(35,8,$row['telefone'],1);
            $pdf->Cell(80,8,$row['endereco'],1);
            $pdf->Cell(43,8,$row['data_hora'],1);
            $pdf->Cell(35,8,$row['secretaria'],1);
            $pdf->Ln();
        }
    } else {
        $pdf->Cell(0,10,'Nenhuma reclamação encontrada.',0,1);
    }
    $pdf->Output();
    exit;
}

elseif ($tipo === 'id' && $id) {
    // Reclamação individual
    $stmt = $conn->prepare("SELECT r.*, t.nome AS tipo_nome FROM reclamacoes r
                            LEFT JOIN tipos_requisicao t ON r.tipo_requisicao = t.id
                            WHERE r.id=?");
    $stmt->bind_param("i",$id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if($row){
        $pdf->SetFont('Arial','',14);
        $pdf->Cell(0,10,'Reclamação ID '.$id,0,1,'C');
        $pdf->Ln(5);
        $pdf->SetFont('Arial','',12);

        foreach($row as $key=>$val){
            if($key=='id' || $key=='tipo_requisicao') continue;
            $pdf->Cell(50,8,ucfirst(str_replace('_',' ',$key)).':',1);
            $pdf->Cell(0,8,$val,1,1);
        }
    } else {
        $pdf->Cell(0,10,'Erro: Nenhuma reclamação encontrada com ID '.$id,0,1);
    }
    $pdf->Output();
    exit;
}

elseif ($tipo === 'selecionados' && $ids) {
    // IDs selecionados pelo usuário
    $idArray = array_filter(array_map('intval', explode(',', $ids)));
    if(empty($idArray)){
        echo "Nenhuma reclamação selecionada.";
        exit;
    }
    $idList = implode(',',$idArray);

    $sql = "SELECT r.*, t.nome AS tipo_nome FROM reclamacoes r
            LEFT JOIN tipos_requisicao t ON r.tipo_requisicao = t.id
            WHERE r.id IN ($idList)
            ORDER BY r.id ASC";
    $result = $conn->query($sql);

    // Cabeçalho tabela
    $pdf->Cell(10,8,'ID',1);
    $pdf->Cell(40,8,'Nome',1);
    $pdf->Cell(39,8,'Tipo',1);
    $pdf->Cell(35,8,'Telefone',1);
    $pdf->Cell(80,8,'Endereço',1);
    $pdf->Cell(43,8,'Data/Hora',1);
    $pdf->Cell(35,8,'Secretaria',1);
    $pdf->Ln();

    if($result && $result->num_rows>0){
        foreach($result as $row){
            $pdf->Cell(10,8,$row['id'],1);
            $pdf->Cell(40,8,$row['nome'],1);
            $pdf->Cell(39,8,$row['tipo_nome'],1);
            $pdf->Cell(35,8,$row['telefone'],1);
            $pdf->Cell(80,8,$row['endereco'],1);
            $pdf->Cell(43,8,$row['data_hora'],1);
            $pdf->Cell(35,8,$row['secretaria'],1);
            $pdf->Ln();
        }
    } else {
        $pdf->Cell(0,10,'Nenhuma reclamação encontrada.',0,1);
    }
    $pdf->Output();
    exit;
}

else {
    echo "Parâmetro inválido.";
}
?>
