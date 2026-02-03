<?php
define('SOCKET_ADDRESS', '127.0.0.1');
define('SOCKET_PORT', 8888);
require_once 'database.php';
set_time_limit(0);

$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
socket_bind($socket, SOCKET_ADDRESS, SOCKET_PORT);
socket_listen($socket);

echo "Server started on " . SOCKET_ADDRESS . ":" . SOCKET_PORT . "\n";

$clients = [$socket];

while (true) {
    $read = $clients;
    $write = null;
    $except = null;

    if (socket_select($read, $write, $except, null) === false) break;

    if (in_array($socket, $read)) {
        $new_client = socket_accept($socket);
        $clients[] = $new_client;
        $msg = "Connected\n";
        socket_write($new_client, $msg, strlen($msg));
        $key = array_search($socket, $read);
        unset($read[$key]);
    }

    foreach ($read as $client) {
        $message = @socket_read($client, 2048);

        if ($message === false || $message === '') {
            $key = array_search($client, $clients);
            unset($clients[$key]);
            socket_close($client);
            continue;
        }

        $response = processRequest($message, $pdo);
        socket_write($client, $response, strlen($response));
    }
}

socket_close($socket);

function processRequest($message, $pdo) {
    $data = json_decode($message, true);
    if (!isset($data['action'])) return "Error: No action";

    switch ($data['action']) {
        case 'create': return createMenuItem($data, $pdo);
        case 'read': return getMenuItems($pdo);
        case 'update': return updateMenuItem($data, $pdo);
        case 'delete': return deleteMenuItem($data, $pdo);
        default: return "Error: Unknown action";
    }
}

function createMenuItem($data, $pdo) {
    try {
        $stmt = $pdo->prepare("INSERT INTO menu_items (name, category, description, price, stock_quantity, image_path) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['name'],
            $data['category'] ?? 'General',
            $data['description'] ?? '',
            $data['price'],
            $data['stock_quantity'] ?? 0,
            $data['image_path'] ?? null
        ]);
        return "Item created";
    } catch (PDOException $e) { return "Error: " . $e->getMessage(); }
}

function updateMenuItem($data, $pdo) {
    try {
        $fields = [];
        $values = [];
        // Dynamic update builder
        if (isset($data['name'])) { $fields[] = "name=?"; $values[] = $data['name']; }
        if (isset($data['category'])) { $fields[] = "category=?"; $values[] = $data['category']; }
        if (isset($data['description'])) { $fields[] = "description=?"; $values[] = $data['description']; }
        if (isset($data['price'])) { $fields[] = "price=?"; $values[] = $data['price']; }
        if (isset($data['stock_quantity'])) { $fields[] = "stock_quantity=?"; $values[] = $data['stock_quantity']; }
        if (isset($data['image_path'])) { $fields[] = "image_path=?"; $values[] = $data['image_path']; }

        if (empty($fields)) return "No changes";
        $values[] = $data['id'];

        $sql = "UPDATE menu_items SET " . implode(", ", $fields) . " WHERE id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        return $stmt->rowCount() > 0 ? "Item updated" : "No changes made";
    } catch (PDOException $e) { return "Error: " . $e->getMessage(); }
}

function deleteMenuItem($data, $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT image_path FROM menu_items WHERE id=?");
        $stmt->execute([$data['id']]);
        $item = $stmt->fetch();
        if ($item && $item['image_path'] && file_exists($item['image_path'])) unlink($item['image_path']);

        $stmt = $pdo->prepare("DELETE FROM menu_items WHERE id=?");
        $stmt->execute([$data['id']]);
        return "Item deleted";
    } catch (PDOException $e) { return "Error: " . $e->getMessage(); }
}

function getMenuItems($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM menu_items ORDER BY created_at DESC");
        return json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
    } catch (PDOException $e) { return json_encode(['status' => 'error', 'message' => $e->getMessage()]); }
}
?>