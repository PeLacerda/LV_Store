<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

// === CONFIGURAÇÕES DB ===
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'lv_store'; // importe o SQL fornecido (arquivo db.sql) para criar este DB

$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn->connect_error) {
    echo json_encode(['success'=>false,'message'=>'DB connection error: '.$conn->connect_error]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// Helper para ler JSON do corpo
function read_json_body() {
    $txt = file_get_contents('php://input');
    $data = json_decode($txt, true);
    if (!is_array($data)) {
        // fallback para parse_str (PUT/DELETE via form-encoded)
        parse_str($txt, $data2);
        if (is_array($data2)) return $data2;
        return [];
    }
    return $data;
}

if ($method === 'GET') {
    // Se id for fornecido => retornar um item
    if (isset($_GET['id'])) {
        $id = intval($_GET['id']);
        $stmt = $conn->prepare("SELECT id, name, description, in_stock FROM products WHERE id = ?");
        $stmt->bind_param("i",$id);
        $stmt->execute();
        $res = $stmt->get_result();
        $item = $res->fetch_assoc();
        if ($item) {
            echo json_encode(['success'=>true,'item'=>$item]);
        } else {
            echo json_encode(['success'=>false,'message'=>'Item não encontrado']);
        }
        exit;
    }

    // Lista / pesquisa
    $q = isset($_GET['q']) ? "%".$conn->real_escape_string($_GET['q'])."%" : null;
    $filterStock = isset($_GET['in_stock']) && $_GET['in_stock'] !== '' ? intval($_GET['in_stock']) : null;

    $sql = "SELECT id, name, description, in_stock FROM products";
    $where = [];
    if ($q !== null) $where[] = "(name LIKE ? OR description LIKE ?)";
    if ($filterStock !== null) $where[] = "in_stock = ?";
    if ($where) $sql .= " WHERE " . implode(" AND ", $where);
    $sql .= " ORDER BY id DESC LIMIT 100";

    $stmt = $conn->prepare($sql);
    if ($q !== null && $filterStock !== null) {
        $stmt->bind_param("ssi", $q, $q, $filterStock);
    } else if ($q !== null) {
        $stmt->bind_param("ss", $q, $q);
    } else if ($filterStock !== null) {
        $stmt->bind_param("i", $filterStock);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $items = [];
    while ($row = $res->fetch_assoc()) $items[] = $row;
    echo json_encode(['success'=>true,'items'=>$items]);
    exit;
}

if ($method === 'POST') {
    $data = read_json_body();
    $name = isset($data['name']) ? trim($data['name']) : '';
    $description = isset($data['description']) ? trim($data['description']) : '';
    $in_stock = isset($data['in_stock']) ? intval($data['in_stock']) : 0;

    if ($name === '' || $description === '') {
        echo json_encode(['success'=>false,'message'=>'Nome e descrição são obrigatórios']);
        exit;
    }
    $stmt = $conn->prepare("INSERT INTO products (name, description, in_stock) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $name, $description, $in_stock);
    if ($stmt->execute()) {
        echo json_encode(['success'=>true,'id'=>$stmt->insert_id]);
    } else {
        echo json_encode(['success'=>false,'message'=>$stmt->error]);
    }
    exit;
}

if ($method === 'PUT') {
    $data = read_json_body();
    $id = isset($_GET['id']) ? intval($_GET['id']) : (isset($data['id']) ? intval($data['id']) : 0);
    if (!$id) { echo json_encode(['success'=>false,'message'=>'ID ausente']); exit; }

    $name = isset($data['name']) ? trim($data['name']) : '';
    $description = isset($data['description']) ? trim($data['description']) : '';
    $in_stock = isset($data['in_stock']) ? intval($data['in_stock']) : 0;

    if ($name === '' || $description === '') {
        echo json_encode(['success'=>false,'message'=>'Nome e descrição são obrigatórios']);
        exit;
    }

    $stmt = $conn->prepare("UPDATE products SET name=?, description=?, in_stock=? WHERE id=?");
    $stmt->bind_param("ssii", $name, $description, $in_stock, $id);
    if ($stmt->execute()) {
        echo json_encode(['success'=>true,'affected'=>$stmt->affected_rows]);
    } else {
        echo json_encode(['success'=>false,'message'=>$stmt->error]);
    }
    exit;
}

if ($method === 'DELETE') {
    // id pode vir por query string ou corpo
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if (!$id) {
        // tentar ler do corpo
        $data = read_json_body();
        if (isset($data['id'])) $id = intval($data['id']);
    }
    if (!$id) { echo json_encode(['success'=>false,'message'=>'ID ausente']); exit; }

    $stmt = $conn->prepare("DELETE FROM products WHERE id=?");
    $stmt->bind_param("i",$id);
    if ($stmt->execute()) {
        echo json_encode(['success'=>true,'affected'=>$stmt->affected_rows]);
    } else {
        echo json_encode(['success'=>false,'message'=>$stmt->error]);
    }
    exit;
}

// Método não suportado
http_response_code(405);
echo json_encode(['success'=>false,'message'=>'Método não suportado']);
