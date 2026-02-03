<?php

// Ctrl+I to chat, Ctrl+K to generate

// Define socket server details - these should match client.php
define('SOCKET_ADDRESS', '127.0.0.1');
define('SOCKET_PORT', 8888);

// Include database connection
require_once 'database.php';

// Set time limit to infinity
set_time_limit(seconds: 0);

// Create a socket
$socket = socket_create(domain: AF_INET, type: SOCK_STREAM, protocol: SOL_TCP);
if ($socket === false) {
    echo "Socket creation failed: " . socket_strerror(error_code: socket_last_error()) . "\n";
    exit(1);
}

// Set socket options
socket_set_option(socket: $socket, level: SOL_SOCKET, option: SO_REUSEADDR, value: 1);

// Bind the socket to an address and port
if (!socket_bind(socket: $socket, address: SOCKET_ADDRESS, port: SOCKET_PORT)) {
    echo "Socket binding failed: " . socket_strerror(error_code: socket_last_error(socket: $socket)) . "\n";
    exit(1);
}

// Start listening for connections
if (!socket_listen(socket: $socket)) {
    echo "Socket listen failed: " . socket_strerror(error_code: socket_last_error(socket: $socket)) . "\n";
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
    if (socket_select(read: $read, write: $write, except: $except, seconds: null) === false) {
        echo "Socket select failed: " . socket_strerror(error_code: socket_last_error()) . "\n";
        break;
    }

    // Check if there is a new connection request
    if (in_array(needle: $socket, haystack: $read)) {
        // Accept the new connection
        $new_client = socket_accept(socket: $socket);
        $clients[] = $new_client;

        // Send welcome message to the new client
        $welcome_message = "Welcome to the Food Menu Server!\n";
        socket_write(socket: $new_client, data: $welcome_message, length: strlen(string: $welcome_message));

        echo "New client connected\n";

        // Remove the server socket from the read array
        $key = array_search(needle: $socket, haystack: $read);
        unset($read[$key]);
    }
}