<?php
require_once __DIR__ . '/../config.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME, DB_PORT);
    $conn->set_charset(DB_CHARSET);
} catch (mysqli_sql_exception $e) {
    sendResponse(['error' => 'Помилка підключення до БД: ' . $e->getMessage()], 500);
}

function getRequest() {
    $input = json_decode(file_get_contents('php://input'), true);
    return $input ?? [];
}

function sendResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'OPTIONS') {
    sendResponse(['status' => 'ok'], 200);
}

$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$scriptName = $_SERVER['SCRIPT_NAME'];
if (strpos($requestUri, $scriptName) === 0) {
    $route = substr($requestUri, strlen($scriptName));
} else {
    $route = str_replace(dirname($scriptName), '', $requestUri);
}
$route = str_replace(['/index.php', '/api/api'], ['', '/api'], $route);
if (strpos($route, '/api') !== 0) {
    $route = '/api' . $route;
}
$route = preg_replace('#/+#', '/', $route);

if ($route === '/api/test-connection') {
    sendResponse(['status' => 'success', 'message' => 'Бекенд працює коректно!']);
} elseif ($route === '/api/products/search' && $method === 'GET') {
    searchProducts($conn);
} elseif ($route === '/api/auth/register' && $method === 'POST') {
    registerUser($conn);
} elseif ($route === '/api/auth/login' && $method === 'POST') {
    loginUser($conn);
} elseif ($route === '/api/orders' && $method === 'GET') {
    getOrders($conn);
} elseif ($route === '/api/orders' && $method === 'POST') {
    createOrder($conn);
} elseif (preg_match('#^/api/orders/(\d+)$#', $route, $matches) && $method === 'GET') {
    getOrderById($conn, $matches[1]);
} elseif ($route === '/api/products' && $method === 'GET') {
    getProducts($conn);
} elseif ($route === '/api/products' && $method === 'POST') {
    createProduct($conn);
} elseif (preg_match('#^/api/products/(\d+)$#', $route, $matches)) {
    if ($method === 'GET') {
        getProductById($conn, $matches[1]);
    } elseif ($method === 'PUT') {
        updateProduct($conn, $matches[1]);
    } elseif ($method === 'DELETE') {
        deleteProduct($conn, $matches[1]);
    }
} elseif ($route === '/api/categories' && $method === 'GET') {
    getCategories($conn);
} else {
    sendResponse(['error' => 'Маршрут не знайдено', 'debug_route' => $route], 404);
}

function searchProducts($conn) {
    $query = $_GET['q'] ?? '';
    if (mb_strlen($query) < 2) {
        sendResponse([]);
    }
    $stmt = $conn->prepare("SELECT p.*, c.name as category_name 
                            FROM products p 
                            LEFT JOIN categories c ON p.category_id = c.category_id 
                            WHERE p.name LIKE ? OR p.description LIKE ?");
    $searchTerm = "%" . $query . "%";
    $stmt->bind_param("ss", $searchTerm, $searchTerm);
    $stmt->execute();
    $result = $stmt->get_result();
    sendResponse($result->fetch_all(MYSQLI_ASSOC));
}

function registerUser($conn) {
    $data = getRequest();
    if (empty($data['username']) || empty($data['email']) || empty($data['password'])) {
        sendResponse(['error' => 'Всі поля обов\'язкові'], 400);
    }
    $password = password_hash($data['password'], PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, 'client')");
    $stmt->bind_param("sss", $data['username'], $data['email'], $password);
    try {
        if ($stmt->execute()) {
            sendResponse(['success' => true, 'id' => $conn->insert_id], 201);
        }
    } catch (Exception $e) {
        sendResponse(['error' => 'Користувач вже існує'], 400);
    }
}

function loginUser($conn) {
    $data = getRequest();
    $email = $data['email'] ?? '';
    $password = $data['password'] ?? '';
    $stmt = $conn->prepare("SELECT user_id as id, full_name as username, password_hash, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password_hash'])) {
            unset($user['password_hash']);
            sendResponse(['success' => true, 'user' => $user]);
        }
    }
    sendResponse(['error' => 'Невірний логін або пароль'], 401);
}

function getProducts($conn) {
    $catId = isset($_GET['category']) ? intval($_GET['category']) : null;
    $allowedSort = ['name', 'price', 'stock_quantity', 'product_id', 'category_id'];
    $sort = isset($_GET['sort']) && in_array($_GET['sort'], $allowedSort) ? $_GET['sort'] : 'name';
    $order = isset($_GET['order']) && strtolower($_GET['order']) === 'desc' ? 'DESC' : 'ASC';
    $sql = "SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.category_id";
    $params = [];
    $types = '';
    if ($catId) {
        $sql .= " WHERE p.category_id = ?";
        $params[] = $catId;
        $types .= 'i';
    }
    $sql .= " ORDER BY p.$sort $order";
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    sendResponse($result->fetch_all(MYSQLI_ASSOC));
}

function getProductById($conn, $id) {
    $id = intval($id);
    $stmt = $conn->prepare("SELECT * FROM products WHERE product_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        sendResponse($row);
    } else {
        sendResponse(['error' => 'Товар не знайдено'], 404);
    }
}

function getCategories($conn) {
    $result = $conn->query("SELECT c.*, COUNT(p.product_id) as product_count 
                            FROM categories c 
                            LEFT JOIN products p ON c.category_id = p.category_id 
                            GROUP BY c.category_id");
    sendResponse($result->fetch_all(MYSQLI_ASSOC));
}

function getOrders($conn) {
    $userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;
    if ($userId) {
        $stmt = $conn->prepare("SELECT * FROM orders WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
    } else {
        $stmt = $conn->prepare("SELECT * FROM orders");
    }
    $stmt->execute();
    $result = $stmt->get_result();
    sendResponse($result->fetch_all(MYSQLI_ASSOC));
}

function getOrderById($conn, $id) {
    $id = intval($id);
    $stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $order = $result->fetch_assoc();
    if ($order) {
        $stmtItems = $conn->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $stmtItems->bind_param("i", $id);
        $stmtItems->execute();
        $itemsResult = $stmtItems->get_result();
        $order['items'] = $itemsResult->fetch_all(MYSQLI_ASSOC);
        sendResponse($order);
    } else {
        sendResponse(['error' => 'Замовлення не знайдено'], 404);
    }
}

function createOrder($conn) {
    $data = getRequest();
    if (!isset($data['user_id']) || !isset($data['items'])) {
        sendResponse(['error' => 'Не вказано user_id або items'], 400);
    }
    $userId = intval($data['user_id']);
    $itemsJson = json_encode($data['items']);
    try {
        $stmt = $conn->prepare("CALL create_order(?, ?)");
        $stmt->bind_param("is", $userId, $itemsJson);
        if ($stmt->execute()) {
            sendResponse(['success' => true, 'message' => 'Замовлення створено через процедуру'], 201);
        } else {
            throw new Exception($stmt->error);
        }
    } catch (Exception $e) {
        sendResponse(['error' => 'Помилка SQL: ' . $e->getMessage()], 500);
    }
}

function createProduct($conn) {
    $data = getRequest();
    $stmt = $conn->prepare("INSERT INTO products (name, description, price, stock_quantity, category_id, image_url) VALUES (?, ?, ?, ?, ?, ?)");
    $name = $data['name'];
    $description = $data['description'] ?? '';
    $price = floatval($data['price']);
    $stockQuantity = intval($data['stock_quantity']);
    $categoryId = intval($data['category_id']);
    $imageUrl = $data['image_url'] ?? 'no_image.jpg';
    $stmt->bind_param("ssdiis", $name, $description, $price, $stockQuantity, $categoryId, $imageUrl);
    if ($stmt->execute()) {
        sendResponse(['success' => true, 'id' => $conn->insert_id], 201);
    } else {
        sendResponse(['error' => $conn->error], 500);
    }
}

function updateProduct($conn, $id) {
    $data = getRequest();
    $id = intval($id);
    $stmt = $conn->prepare("UPDATE products SET name=?, description=?, price=?, stock_quantity=?, category_id=?, image_url=? WHERE product_id=?");
    $name = $data['name'];
    $description = $data['description'] ?? '';
    $price = floatval($data['price']);
    $stockQuantity = intval($data['stock_quantity']);
    $categoryId = intval($data['category_id']);
    $imageUrl = $data['image_url'] ?? 'no_image.jpg';
    $stmt->bind_param("ssdiisi", $name, $description, $price, $stockQuantity, $categoryId, $imageUrl, $id);
    if ($stmt->execute()) {
        sendResponse(['success' => true]);
    } else {
        sendResponse(['error' => $conn->error], 500);
    }
}

function deleteProduct($conn, $id) {
    $id = intval($id);
    $stmt = $conn->prepare("DELETE FROM products WHERE product_id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        sendResponse(['success' => true]);
    } else {
        sendResponse(['error' => $conn->error], 500);
    }
}
