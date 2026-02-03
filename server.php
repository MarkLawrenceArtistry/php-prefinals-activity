<?php
// server.php

// Define socket details
define('SOCKET_ADDRESS', '127.0.0.1');
define('SOCKET_PORT', 8888);

require_once 'database.php';

// No time limit so the server runs forever
set_time_limit(0);

// Enable implicit flushing so we see output in the console immediately
ob_implicit_flush();

$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);

if (!socket_bind($socket, SOCKET_ADDRESS, SOCKET_PORT)) {
    die("Bind failed: " . socket_strerror(socket_last_error($socket)));
}

if (!socket_listen($socket)) {
    die("Listen failed: " . socket_strerror(socket_last_error($socket)));
}

echo "Server started on " . SOCKET_ADDRESS . ":" . SOCKET_PORT . "\n";
echo "Waiting for connections...\n";

$clients = [$socket];

while (true) {
    $read = $clients;
    $write = null;
    $except = null;

    // Monitor all sockets for changes
    if (socket_select($read, $write, $except, 0) < 1) {
        continue;
    }

    // Check if there's a new client connection
    if (in_array($socket, $read)) {
        $new_client = socket_accept($socket);
        $clients[] = $new_client;

        // Send welcome message
        $msg = "Connected to Server successfully!";
        socket_write($new_client, $msg, strlen($msg));
        
        echo "New client connected.\n";

        // Remove the listening socket from the list of sockets to read from
        $key = array_search($socket, $read);
        unset($read[$key]);
    }

    // Check each client for data
    foreach ($read as $client_sock) {
        // Read data from client
        $data = @socket_read($client_sock, 2048);

        // If data is empty, client disconnected
        if ($data === false || $data === '') {
            $key = array_search($client_sock, $clients);
            unset($clients[$key]);
            echo "Client disconnected.\n";
            continue;
        }

        $data = trim($data);
        if (!empty($data)) {
            echo "Received request: $data\n";
            
            // Default response
            $response = ['status' => 'error', 'message' => 'Invalid request'];
            $request = json_decode($data, true);

            if ($request && isset($request['action'])) {
                // HANDLE READ
                if ($request['action'] === 'read') {
                    try {
                        $stmt = $pdo->query("SELECT * FROM menu_items ORDER BY created_at DESC");
                        $items = $stmt->fetchAll();
                        $response = ['status' => 'success', 'data' => $items];
                    } catch (Exception $e) {
                        $response = ['status' => 'error', 'message' => $e->getMessage()];
                    }
                }
                // HANDLE CREATE
                elseif ($request['action'] === 'create') {
                    $name = $request['name'] ?? '';
                    $desc = $request['description'] ?? '';
                    $price = $request['price'] ?? 0;
                    
                    try {
                        $stmt = $pdo->prepare("INSERT INTO menu_items (name, description, price) VALUES (?, ?, ?)");
                        $stmt->execute([$name, $desc, $price]);
                        $response = ['status' => 'success', 'message' => 'Item created'];
                    } catch (Exception $e) {
                        $response = ['status' => 'error', 'message' => $e->getMessage()];
                    }
                }
            }

            // Send JSON response back to client
            $output = json_encode($response);
            socket_write($client_sock, $output, strlen($output));
        }
    }
}
?>