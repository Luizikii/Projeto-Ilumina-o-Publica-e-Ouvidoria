<?php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=UTF-8');
include 'conexao.php';

function reply($arr,$code=200){ http_response_code($code); echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }

$input = json_decode(file_get_contents('php://input'), true);
$id = isset($input['id']) ? (int)$input['id'] : 0;

if(!$id) reply(['success'=>false,'error'=>'ID inválido.'],400);

$stmt = $conn->prepare("DELETE FROM reclamacoes WHERE id = ?");
if(!$stmt) reply(['success'=>false,'error'=>'Erro prepare: '.$conn->error],500);

$stmt->bind_param('i', $id);
if($stmt->execute()){
    reply(['success'=>true]);
} else {
    reply(['success'=>false,'error'=>'Erro ao deletar: '.$stmt->error],500);
}
