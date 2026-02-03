<?php

// Set time limit to infinity
set_time_limit(0);

// Define socket server details - these need to be accessible globally
define('SOCKET_ADDRESS', '127.0.0.1');
define('SOCKET_PORT', 8888);

// Check if request is to handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    handleFormSubmission();
    exit;
}

/**
 * Handle form submissions via AJAX
 */
function handleFormSubmission()
{
    // Use the constants instead of global variables
    $address = SOCKET_ADDRESS;
    $port = SOCKET_PORT;

    // Create a socket
    $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if ($socket === false) {
        echo json_encode(['status' => 'error', 'message' => 'Socket creation failed']);
        return;
    }

    // Connect to the server
    $result = socket_connect($socket, $address, $port);
    if ($result === false) {
        echo json_encode(['status' => 'error', 'message' => 'Socket connection failed: ' . socket_strerror(socket_last_error($socket))]);
        return;
    }

    // Skip welcome message
    socket_read($socket, 1024);

    // Prepare data for server
    $data = [
        'action' => $_POST['action']
    ];

    // Add additional data based on action
    switch ($_POST['action']) {
        case 'create':
            $data['name'] = $_POST['name'];
            $data['description'] = $_POST['description'] ?? '';
            $data['price'] = $_POST['price'];
            break;

        case 'update':
            $data['id'] = $_POST['id'];
            $data['name'] = $_POST['name'];
            $data['description'] = $_POST['description'] ?? '';
            $data['price'] = $_POST['price'];
            break;

        case 'delete':
            $data['id'] = $_POST['id'];
            break;
    }

    // Send request to server
    $request = json_encode($data);
    socket_write($socket, $request, strlen($request));

    // Get response
    $response = socket_read($socket, 4096);

    // Close the socket
    socket_close($socket);

    // Get updated menu items
    $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    $connectResult = socket_connect($socket, $address, $port);

    if ($connectResult === false) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to connect for menu refresh']);
        return;
    }

    socket_read($socket, 1024); // Skip welcome message

    $request = json_encode(['action' => 'read']);
    socket_write($socket, $request, strlen($request));
    $menuResponse = socket_read($socket, 4096);
    socket_close($socket);

    // Return response and updated menu
    echo json_encode([
        'status' => 'success',
        'message' => $response,
        'menu' => json_decode($menuResponse, true)
    ]);
}
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

        <div class="grid">
            <div>
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
                <div id="menu-container"></div>
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
        
        // Initial fetch
        fetchUpdates();

        function handleSubmit(e) {
            e.preventDefault();
            statusContainer.innerHTML = '<div class="alert alert-info">Processing...</div>';
            sendRequest(new FormData(this));
        }

        function handleButtonClick(e) {
            if (e.target.classList.contains('edit-btn')) {
                handleEdit(e.target);
            } else if (e.target.classList.contains('delete-btn')) {
                if (confirm('Delete this item?')) handleDelete(e.target);
            }
        }

        function handleEdit(btn) {
            const item = btn.closest('.menu-item');
            const id = item.getAttribute('data-id');
            const heading = item.querySelector('h4').textContent;
            const name = heading.split(' - ')[0].trim();
            const price = heading.split('P')[1].trim();
            const desc = item.querySelector('p').textContent;

            document.getElementById('item-id').value = id;
            document.getElementById('name').value = name;
            document.getElementById('description').value = desc;
            document.getElementById('price').value = price;
            document.getElementById('action').value = 'update';
            document.getElementById('form-title').textContent = 'Edit Menu Item';
            cancelBtn.classList.remove('hidden');
        }

        function handleDelete(btn) {
            const id = btn.closest('.menu-item').getAttribute('data-id');
            statusContainer.innerHTML = '<div class="alert alert-info">Deleting...</div>';

            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('id', id);
            sendRequest(formData);
        }

        function sendRequest(formData) {
            fetch('client.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                statusContainer.innerHTML = data.status === 'success' ?
                    `<div class="alert alert-success">${data.message}</div>` :
                    `<div class="alert alert-danger">${data.message}</div>`;

                if (data.status === 'success') {
                    resetForm();
                    updateMenuItems(data.menu);
                }
            })
            .catch(err => {
                statusContainer.innerHTML = '<div class="alert alert-danger">Connection failed</div>';
                console.error(err);
            });
        }

        function resetForm() {
            menuForm.reset();
            document.getElementById('item-id').value = '';
            document.getElementById('action').value = 'create';
            document.getElementById('form-title').textContent = 'Add New Menu Item';
            cancelBtn.classList.add('hidden');
        }

        function updateMenuItems(menuData) {
            const container = document.getElementById('menu-container');
            let html = '';

            if (menuData.status === 'success') {
                if (menuData.data.length === 0) {
                    html = '<div class="alert alert-info">No menu items found. Add your first item!</div>';
                } else {
                    menuData.data.forEach(item => {
                        html += `
                            <div class="menu-item" data-id="${item.id}">
                                <h4>${item.name} - P${parseFloat(item.price).toFixed(2)}</h4>
                                <p>${item.description}</p>
                                <div class="action-buttons">
                                    <button class="btn-warning btn-sm edit-btn">Edit</button>
                                    <button class="btn-danger btn-sm delete-btn">Delete</button>
                                </div>
                            </div>
                        `;
                    });
                }
            } else {
                html = '<div class="alert alert-danger">Failed to load menu items</div>';
            }

            container.innerHTML = html;
        }

        function fetchUpdates() {
            const formData = new FormData();
            formData.append('action', 'read');

            fetch('client.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') updateMenuItems(data.menu);
            })
            .catch(err => console.error('Update error:', err));
        }
    });
    </script>
</body>
</html>