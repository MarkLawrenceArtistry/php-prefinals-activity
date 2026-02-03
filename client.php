<?php
// client.php

set_time_limit(0);

// Use 127.0.0.1 because the script runs ON the server machine
define('SOCKET_ADDRESS', '127.0.0.1');
define('SOCKET_PORT', 8888);

// Handle Form Submission (POST request)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if ($socket && socket_connect($socket, SOCKET_ADDRESS, SOCKET_PORT)) {
        // Read welcome message to clear buffer
        socket_read($socket, 1024);
        
        // Prepare data
        $data = [
            'action' => 'create',
            'name' => $_POST['name'],
            'description' => $_POST['description'],
            'price' => $_POST['price']
        ];
        
        // Send to server
        $msg = json_encode($data);
        socket_write($socket, $msg, strlen($msg));
        
        // Close
        socket_close($socket);
        
        // Refresh page
        header("Location: client.php");
        exit;
    }
}

// ------------------------------------------
// Standard Page Load: Get Items
// ------------------------------------------

$menuData = ['status' => 'error', 'message' => 'Not connected'];

$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
if ($socket === false) {
    $error = socket_strerror(socket_last_error());
} else {
    // Suppress warning with @ to handle connection errors gracefully
    $result = @socket_connect($socket, SOCKET_ADDRESS, SOCKET_PORT);
    
    if ($result) {
        // 1. Read Welcome Message
        $welcome = socket_read($socket, 1024);
        
        // 2. Send "read" request
        $request = json_encode(['action' => 'read']);
        socket_write($socket, $request, strlen($request));
        
        // 3. Read Response
        $response = socket_read($socket, 8192); // Increased buffer size
        $menuData = json_decode($response, true);
        
        socket_close($socket);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Food Menu Manager</title>
    <style>
        body { font-family: sans-serif; padding: 20px; background: #f4f4f4; }
        .container { max-width: 900px; margin: 0 auto; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, textarea { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        .btn-primary { background: #007bff; color: white; border: none; padding: 10px 20px; cursor: pointer; border-radius: 4px; width: 100%; }
        .btn-primary:hover { background: #0056b3; }
        .menu-item { background: white; padding: 15px; margin-bottom: 10px; border-left: 5px solid #28a745; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .alert { padding: 10px; border-radius: 4px; margin-bottom: 10px; }
        .alert-info { background: #d1ecf1; color: #0c5460; }
        .alert-danger { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>

<div class="container">
    <h1>Food Menu Manager</h1>
    
    <?php if(isset($welcome)): ?>
        <div class="alert alert-info"><?php echo $welcome; ?></div>
    <?php endif; ?>

    <?php if(isset($error)): ?>
        <div class="alert alert-danger">Connection Error: <?php echo $error; ?>. Is server.php running?</div>
    <?php endif; ?>

    <div class="grid">
        <!-- FORM COLUMN -->
        <div>
            <div class="card">
                <h3>Add New Item</h3>
                <form method="POST" action="client.php">
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="name" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Price</label>
                        <input type="number" step="0.01" name="price" required>
                    </div>
                    <button type="submit" class="btn-primary">Save Item</button>
                </form>
            </div>
        </div>

        <!-- LIST COLUMN -->
        <div>
            <h3>Current Menu</h3>
            <div id="menu-container">
                <?php if (isset($menuData) && isset($menuData['status']) && $menuData['status'] === 'success'): ?>
                    <?php if (empty($menuData['data'])): ?>
                        <p>No items found.</p>
                    <?php else: ?>
                        <?php foreach ($menuData['data'] as $item): ?>
                            <div class="menu-item">
                                <h4><?php echo htmlspecialchars($item['name']); ?> 
                                    <span style="float:right">P<?php echo number_format($item['price'], 2); ?></span>
                                </h4>
                                <p><?php echo htmlspecialchars($item['description']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-danger">
                        Failed to load data. 
                        <?php echo isset($menuData['message']) ? $menuData['message'] : ''; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

</body>
</html>