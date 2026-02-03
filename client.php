<?php

set_time_limit(seconds: 0);

define('SOCKET_ADDRESS', '127.0.0.1');
define('SOCKET_PORT', 8888);

// Create a socket
$socket = socket_create(domain: AF_INET, type: SOCK_STREAM, protocol: SOL_TCP);
if ($socket === false) {
    die("Socket creation failed: " . socket_strerror(error_code: socket_last_error()) . "\n");
}

// Connect to the server
$result = socket_connect(socket: $socket, address: SOCKET_ADDRESS, port: SOCKET_PORT);
if ($result === false) {
    die("Socket connection failed: " . socket_strerror(error_code: socket_last_error(socket: $socket)) . "\n");
}


// Receive welcome message
$welcome = socket_read(socket: $socket, length: 1024);

// Request menu items
$request = json_encode(value: ['action' => 'read']);
socket_write(socket: $socket, data: $request, length: strlen(string: $request));

// Get response
$response = socket_read(socket: $socket, length: 4096);
$menuData = json_decode(json: $response, associative: true);

// Close the socket
socket_close(socket: $socket);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Food Menu Manager</title>
    <style>
        :root {
            --p: #3498db;
            --s: #2c3e50;
            --d: #e74c3c;
            --w: #f39c12;
            --l: #f8f9fa;
            --b: #ddd;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font: 16px/1.5 sans-serif;
            padding: 20px;
            color: #333;
        }

        h1, h3, h4, h5 {
            margin-bottom: 15px;
            color: var(--s);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .grid {
            display: grid;
            gap: 20px;
            grid-template-columns: 1fr;
        }

        @media (min-width: 768px) {
            .grid {
                grid-template-columns: 1fr 2fr;
            }
        }

        .card {
            border: 1px solid var(--b);
            border-radius: 5px;
        }

        .card-header {
            background: var(--l);
            padding: 15px;
            border-bottom: 1px solid var(--b);
        }

        .card-body, .form-group {
            padding: 15px;
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        input, textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid var(--b);
            border-radius: 4px;
        }

        input:focus, textarea:focus {
            outline: none;
            border-color: var(--p);
        }

        .btn-row {
            display: flex;
            justify-content: space-between;
        }

        button {
            cursor: pointer;
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
        }

        button:hover {
            opacity: 0.9;
        }

        .btn-primary {
            background: var(--p);
            color: white;
        }

        .btn-secondary {
            background: var(--s);
            color: white;
        }

        .btn-warning {
            background: var(--w);
            color: white;
        }

        .btn-danger {
            background: var(--d);
            color: white;
        }

        .btn-sm {
            font-size: 14px;
            padding: 4px 10px;
        }

        .hidden {
            display: none;
        }

        .menu-item {
            border: 1px solid var(--b);
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 5px;
        }

        .menu-item:hover {
            background: var(--l);
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .alert {
            padding: 12px;
            margin-bottom: 15px;
            border-radius: 4px;
        }

        .alert-success {
            background: rgba(46, 204, 113, 0.2);
            border: 1px solid #2ecc71;
        }

        .alert-danger {
            background: rgba(231, 76, 60, 0.2);
            border: 1px solid #e74c3c;
        }

        .alert-info {
            background: rgba(52, 152, 219, 0.2);
            border: 1px solid #3498db;
        }

        .status-container {
            margin-top: 20px;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>Food Menu Manager</h1>
        
        <!-- Display the welcome message from Part 2 -->
        <?php echo "<div class='alert alert-info'>$welcome</div>"; ?>

        <div class="grid">
            <div class="card">
                <div class="card-header">
                    <h5 id="form-title">Add New Menu Item</h5>
                </div>
                <div class="card-body">
                    <form id="menu-form">
                        <input type="hidden" id="item-id" name="id">
                        <input type="hidden" id="action" name="action" value="create">

                        <div class="form-group">
                            <label for="name">Name</label>
                            <input type="text" id="name" name="name" required>
                        </div>

                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" rows="3"></textarea>
                        </div>

                        <div class="form-group">
                            <label for="price">Price (P)</label>
                            <input type="number" id="price" name="price" step="0.01" required>
                        </div>

                        <div class="btn-row">
                            <button type="submit" class="btn-primary">Save</button>
                            <button type="button" id="cancel-btn" class="btn-secondary hidden">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="status-container" id="status-container"></div>
        </div>

        <div>
            <h3>Menu Items</h3>
            <div id="menu-container">
                <?php if (isset($menuData) && $menuData['status'] === 'success'): ?>
                    <?php if (empty($menuData['data'])): ?>
                        <div class="alert alert-info">No menu items found. Add your first item!</div>
                    <?php else: ?>
                        <?php foreach ($menuData['data'] as $item): ?>
                            <div class="menu-item" data-id="<?php echo $item['id']; ?>">
                                <h4><?php echo htmlspecialchars(string: $item['name']); ?> - 
                                    P<?php echo number_format(num: $item['price'], decimals: 2); ?></h4>
                                <p><?php echo htmlspecialchars(string: $item['description']); ?></p>
                                <div class="action-buttons">
                                    <button class="btn-warning btn-sm edit-btn">Edit</button>
                                    <button class="btn-danger btn-sm delete-btn">Delete</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-danger">Failed to load menu items</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const menuForm = document.getElementById('menu-form');
            const statusContainer = document.getElementById('status-container');
            const cancelBtn = document.getElementById('cancel-btn');

            // Handle form submission
            menuForm.addEventListener('submit', handleSubmit);

            // Handle edit/delete buttons
            document.addEventListener('click', handleButtonClick);

            // Handle cancel button
            cancelBtn.addEventListener('click', resetForm);

            // Setup updates every 5 seconds
            setInterval(fetchUpdates, 5000);

            function handleSubmit(e) {
                
            }

            function handleButtonClick(e) {

            }

            function handleEdit(btn) {

            }

            function handleDelete(btn) {

            }

            function sendRequest(formData) {

            }

            function resetForm() {

            }

            function updateMenuItems(menuData) {

            }

            function fetchUpdates() {

            }
        });
    </script>
</body>
</html>