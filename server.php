<?php

// Define socket server details - these should match client.php
define('SOCKET_ADDRESS', '127.0.0.1');
define('SOCKET_PORT', 8888);

// Include database connection
require_once 'database.php';

// Set time limit to infinity
set_time_limit(0);

// Create a socket
$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
if ($socket === false) {
    echo "Socket creation failed: " . socket_strerror(socket_last_error()) . "\n";
    exit(1);
}

// Set socket options
socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);

// Bind the socket to an address and port
if (!socket_bind($socket, SOCKET_ADDRESS, SOCKET_PORT)) {
    echo "Socket binding failed: " . socket_strerror(socket_last_error($socket)) . "\n";
    exit(1);
}

// Start listening for connections
if (!socket_listen($socket)) {
    echo "Socket listen failed: " . socket_strerror(socket_last_error($socket)) . "\n";
    exit(1);
}

echo "Server started on " . SOCKET_ADDRESS . ":" . SOCKET_PORT . "\n";
echo "Waiting for connections...\n";

// Array to hold client sockets
$clients = [$socket];

while (true) {
    // Create a copy of the clients array
    $read = $clients;
    $write = null;
    $except = null;

    // Check for socket activity
    if (socket_select($read, $write, $except, null) === false) {
        echo "Socket select failed: " . socket_strerror(socket_last_error()) . "\n";
        break;
    }

    // Check if there is a new connection request
    if (in_array($socket, $read)) {
        // Accept the new connection
        $new_client = socket_accept($socket);
        $clients[] = $new_client;

        // Send welcome message to the new client
        $welcome_message = "Welcome to the Food Menu Server!\n";

        socket_write($new_client, $welcome_message, strlen($welcome_message));

        echo "New client connected\n";

        // Remove the server socket from the read array
        $key = array_search($socket, $read);
        unset($read[$key]);
    }

    // Handle client messages
    foreach ($read as $client) {
        // Read client message
        $message = socket_read($client, 1024);

        // Check if client disconnected
        if ($message === false || $message === '') {
            // Remove client from array
            $key = array_search($client, $clients);
            unset($clients[$key]);
            socket_close($client);
            continue;
        }

        // Process message
        $response = processRequest($message, $pdo);

        // Send response back to the client
        socket_write($client, $response, strlen($response));

        // Broadcast updates to all other clients
        foreach ($clients as $send_client) {
            if ($send_client !== $socket && $send_client !== $client) {
                socket_write($send_client, "REFRESH", strlen("REFRESH"));
            }
        }
    }
}

// Close the socket
socket_close($socket);

function processRequest($message, $pdo)
{
    $data = json_decode($message, true);

    if (!isset($data['action'])) {
        return "Error: No action specified";
    }

    switch ($data['action']) {
        case 'create':
            return createMenuItem($data, $pdo);

        case 'read':
            return getMenuItems($pdo);

        case 'update':
            return updateMenuItem($data, $pdo);

        case 'delete':
            return deleteMenuItem($data, $pdo);

        default:
            return "Error: Unknown action '{$data['action']}'";
    }
}

/**
 * Create a new menu item
 */
function createMenuItem($data, $pdo)
{
    if (!isset($data['name']) || !isset($data['price'])) {
        return "Error: Name and price are required";
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO menu_items (name, description, price) VALUES (?, ?, ?)");
        $stmt->execute([
            $data['name'],
            $data['description'] ?? '',
            $data['price']
        ]);

        return "Menu item '{$data['name']}' created successfully";
    } catch (PDOException $e) {
        return "Error creating menu item: " . $e->getMessage();
    }
}

/**
 * Update a menu item
 */
function updateMenuItem($data, $pdo)
{
    if (!isset($data['id'])) {
        return "Error: Item ID is required";
    }

    try {
        $fields = [];
        $values = [];

        if (isset($data['name'])) {
            $fields[] = "name = ?";
            $values[] = $data['name'];
        }

        if (isset($data['description'])) {
            $fields[] = "description = ?";
            $values[] = $data['description'];
        }

        if (isset($data['price'])) {
            $fields[] = "price = ?";
            $values[] = $data['price'];
        }

        if (empty($fields)) {
            return "Error: No fields to update";
        }

        $values[] = $data['id'];

        $sql = "UPDATE menu_items SET " . implode(", ", $fields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);

        if ($stmt->rowCount() > 0) {
            return "Menu item updated successfully";
        } else {
            return "No changes made or item not found";
        }
    } catch (PDOException $e) {
        return "Error updating menu item: " . $e->getMessage();
    }
}

/**
 * Delete a menu item
 */
function deleteMenuItem($data, $pdo)
{
    if (!isset($data['id'])) {
        return "Error: Item ID is required";
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM menu_items WHERE id = ?");
        $stmt->execute([$data['id']]);

        if ($stmt->rowCount() > 0) {
            return "Menu item deleted successfully";
        } else {
            return "Item not found";
        }
    } catch (PDOException $e) {
        return "Error deleting menu item: " . $e->getMessage();
    }
}

function getMenuItems($pdo)
{
    try {
        $stmt = $pdo->query("SELECT id, name, description, price, created_at FROM menu_items ORDER BY created_at DESC");
        $items = $stmt->fetchAll();

        return json_encode([
            'status' => 'success',
            'data' => $items
        ]);
    } catch (PDOException $e) {
        return json_encode([
            'status' => 'error',
            'message' => "Error retrieving menu items: " . $e->getMessage()
        ]);
    }
}
?>