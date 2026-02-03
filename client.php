<?php
set_time_limit(0);
define('SOCKET_ADDRESS', '127.0.0.1');
define('SOCKET_PORT', 8888);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    handleFormSubmission();
    exit;
}

function handleFormSubmission() {
    $address = SOCKET_ADDRESS;
    $port = SOCKET_PORT;

    // Handle Image Upload
    $imagePath = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);
        $fileName = uniqid() . '-' . basename($_FILES['image']['name']);
        $targetFile = $uploadDir . $fileName;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
            $imagePath = $targetFile;
        }
    }

    $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if ($socket === false) { echo json_encode(['status'=>'error', 'message'=>'Socket fail']); return; }
    $result = socket_connect($socket, $address, $port);
    if ($result === false) { echo json_encode(['status'=>'error', 'message'=>'Connection fail']); return; }
    @socket_read($socket, 1024);

    $data = ['action' => $_POST['action']];
    if ($imagePath) $data['image_path'] = $imagePath;

    if ($_POST['action'] == 'create' || $_POST['action'] == 'update') {
        $data['name'] = $_POST['name'];
        $data['category'] = $_POST['category'];
        $data['description'] = $_POST['description'] ?? '';
        $data['price'] = $_POST['price'];
        $data['stock_quantity'] = $_POST['stock_quantity'];
        if ($_POST['action'] == 'update') $data['id'] = $_POST['id'];
    } elseif ($_POST['action'] == 'delete') {
        $data['id'] = $_POST['id'];
    }

    socket_write($socket, json_encode($data), strlen(json_encode($data)));
    $response = socket_read($socket, 4096);
    socket_close($socket);

    // Refresh Data
    $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    socket_connect($socket, $address, $port);
    @socket_read($socket, 1024);
    $req = json_encode(['action' => 'read']);
    socket_write($socket, $req, strlen($req));
    $menuResponse = socket_read($socket, 4096);
    socket_close($socket);

    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'message' => $response, 'menu' => json_decode($menuResponse, true)]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Food Menu</title>
    <style>
        /* --- Base & Fonts --- */
        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }

        :root {
            --primary-dark: #0d0d1a;
            --primary-purple: #6c5dd2;
            --primary-light: #fcfcfc;
            --primary-accent: #ff6b7a;
            --primary-success: #4ecdc4;
            --secondary-dark: #1a1a2e;
            --text-muted: #a8a8b3;
            --border-color: rgba(252, 252, 252, 0.1);
            --card-bg: rgba(252, 252, 252, 0.05);
            --gradient-primary: linear-gradient(135deg, #6c5dd2 0%, #8b7ae6 100%);
            --gradient-secondary: linear-gradient(135deg, var(--primary-dark) 0%, var(--secondary-dark) 100%);
            --gradient-danger: linear-gradient(135deg, #ff4757 0%, #ff6b7a 100%);
            --shadow-soft: 0 8px 32px rgba(108, 93, 210, 0.15);
            --shadow-medium: 0 12px 48px rgba(108, 93, 210, 0.25);
            --font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        body {
            font-family: var(--font-family);
            background: var(--gradient-secondary);
            color: var(--primary-light);
            min-height: 100vh;
            overflow-x: hidden;
            padding: 20px;
        }

        .container { max-width: 1200px; margin: 0 auto; width: 100%; }

        /* --- Header --- */
        .header {
            display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;
            flex-wrap: wrap; gap: 20px;
        }
        .logo {
            font-size: 2.5rem; font-weight: 800;
            background: var(--gradient-primary); -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            text-shadow: 0 4px 20px rgba(108, 93, 210, 0.3);
        }

        /* --- Buttons --- */
        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 10px; padding: 12px 24px;
            border: none; border-radius: 12px; font-size: 1rem; font-weight: 600; cursor: pointer;
            transition: all 0.2s ease; text-decoration: none; position: relative; overflow: hidden;
            backdrop-filter: blur(10px); color: white;
        }
        .btn-primary { background: var(--gradient-primary); box-shadow: var(--shadow-soft); }
        .btn-primary:hover { box-shadow: var(--shadow-medium); transform: translateY(-2px); }
        .btn-danger { background: var(--gradient-danger); }
        .btn-sm { padding: 6px 12px; font-size: 0.85rem; border-radius: 8px; }

        /* --- KPIs --- */
        .kpi-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 40px;
        }
        .stat-card {
            background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 16px; padding: 20px;
            text-align: center; transition: all 0.2s ease;
        }
        .stat-card:hover { transform: translateY(-4px); border-color: var(--primary-purple); }
        .stat-value { display: block; font-size: 2rem; font-weight: 700; color: var(--primary-purple); margin-bottom: 5px; }
        .stat-label { font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; }

        /* --- Menu Grid --- */
        .menu-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px;
        }
        .menu-card {
            background: var(--secondary-dark); border: 1px solid var(--border-color); border-radius: 16px;
            overflow: hidden; transition: all 0.3s ease; display: flex; flex-direction: column;
        }
        .menu-card:hover { transform: translateY(-5px); border-color: var(--primary-purple); box-shadow: var(--shadow-medium); }
        
        .card-img-container { height: 180px; background: #000; overflow: hidden; position: relative; }
        .card-img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s; }
        .menu-card:hover .card-img { transform: scale(1.05); }
        .category-badge {
            position: absolute; top: 10px; right: 10px; background: rgba(0,0,0,0.7);
            padding: 4px 8px; border-radius: 6px; font-size: 0.75rem; color: var(--primary-success);
            border: 1px solid var(--primary-success);
        }

        .card-body { padding: 15px; flex-grow: 1; display: flex; flex-direction: column; }
        .card-title { font-size: 1.2rem; font-weight: 600; margin-bottom: 5px; color: var(--primary-light); }
        .card-desc { font-size: 0.9rem; color: var(--text-muted); margin-bottom: 15px; flex-grow: 1; }
        
        .card-stats {
            display: flex; justify-content: space-between; margin-bottom: 15px;
            background: rgba(0,0,0,0.2); padding: 8px; border-radius: 8px;
        }
        .stat-mini { font-size: 0.85rem; color: var(--text-muted); }
        .stat-mini strong { color: var(--primary-light); }
        .stock-low { color: var(--primary-accent); }

        .card-footer { display: flex; justify-content: space-between; align-items: center; }
        .price-tag { font-size: 1.3rem; font-weight: 700; color: var(--primary-purple); }

        /* --- Modal --- */
        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(1, 4, 13, 0.85); backdrop-filter: blur(5px);
            display: none; align-items: center; justify-content: center; z-index: 1000;
            padding: 20px; animation: fadeIn 0.3s ease;
        }
        .modal-overlay.active { display: flex; }
        .modal {
            background: var(--secondary-dark); border: 1px solid var(--border-color);
            border-radius: 20px; padding: 30px; max-width: 500px; width: 100%;
            box-shadow: var(--shadow-strong);
        }
        .modal-header { display: flex; justify-content: space-between; margin-bottom: 20px; }
        .modal-title { font-size: 1.5rem; font-weight: 700; }
        .close-btn { background: none; border: none; color: var(--text-muted); font-size: 1.5rem; cursor: pointer; }
        
        /* Form Styling */
        .form-group { margin-bottom: 15px; }
        .form-label { display: block; margin-bottom: 8px; color: var(--text-muted); font-size: 0.9rem; }
        .form-input, .form-textarea, .form-select {
            width: 100%; background: rgba(0,0,0,0.2); border: 1px solid var(--border-color);
            color: white; padding: 12px; border-radius: 10px; font-family: inherit;
        }
        .form-input:focus { outline: none; border-color: var(--primary-purple); }
        
        .message-area { position: fixed; top: 20px; right: 20px; z-index: 2000; }
        .msg { padding: 15px 25px; border-radius: 10px; margin-bottom: 10px; background: var(--secondary-dark); border: 1px solid var(--border-color); animation: fadeIn 0.3s; }
        
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <div class="logo">Food Menu</div>
        <p>Made by Mark Lawrence Catubay</p>
        <button class="btn btn-primary" id="add-btn">
            <span>+ New Protocol</span>
        </button>
    </div>

    <!-- KPIs -->
    <div class="kpi-grid">
        <div class="stat-card">
            <span class="stat-value" id="kpi-total">0</span>
            <span class="stat-label">Total Foods</span>
        </div>
        <div class="stat-card">
            <span class="stat-value" id="kpi-types">0</span>
            <span class="stat-label">Food Types</span>
        </div>
        <div class="stat-card">
            <span class="stat-value" id="kpi-stock">0</span>
            <span class="stat-label">Total Stock</span>
        </div>
        <div class="stat-card">
            <span class="stat-value" id="kpi-low" style="color: var(--primary-accent)">0</span>
            <span class="stat-label">Low Stock (< 10)</span>
        </div>
    </div>

    <div id="status-container" class="message-area"></div>

    <div class="menu-grid" id="menu-container">
        <!-- JS Generates Cards Here -->
    </div>
</div>

<!-- Modal -->
<div class="modal-overlay" id="modal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title" id="modal-title">New Food Entry</h3>
            <button class="close-btn" id="close-modal">&times;</button>
        </div>
        <form id="menu-form">
            <input type="hidden" id="item-id" name="id">
            <input type="hidden" id="action" name="action" value="create">

            <div style="display:grid; grid-template-columns: 2fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label class="form-label">Name</label>
                    <input class="form-input" type="text" id="name" name="name" required placeholder="e.g. Cyber Burger">
                </div>
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <input class="form-input" type="text" id="category" name="category" list="cat-list" required placeholder="Main">
                    <datalist id="cat-list">
                        <option value="Main Dish">
                        <option value="Appetizer">
                        <option value="Drink">
                        <option value="Dessert">
                    </datalist>
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label class="form-label">Price (PHP)</label>
                    <input class="form-input" type="number" id="price" name="price" step="0.01" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Stock Qty</label>
                    <input class="form-input" type="number" id="stock_quantity" name="stock_quantity" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Image</label>
                <input class="form-input" type="file" id="image" name="image" accept="image/*">
            </div>

            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea class="form-textarea" id="description" name="description" rows="3"></textarea>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                <button type="submit" class="btn btn-primary">Execute Save</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('modal');
    const form = document.getElementById('menu-form');
    const container = document.getElementById('menu-container');
    
    // UI Helpers
    const toggleModal = (show) => modal.classList.toggle('active', show);
    document.getElementById('add-btn').onclick = () => { resetForm(); toggleModal(true); };
    document.getElementById('close-modal').onclick = () => toggleModal(false);
    
    // Initial Load
    fetchUpdates();
    setInterval(fetchUpdates, 5000);

    // Form Submit
    form.onsubmit = (e) => {
        e.preventDefault();
        const formData = new FormData(form);
        
        fetch('client.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                showMsg(data.status, data.message);
                if(data.status === 'success') {
                    toggleModal(false);
                    renderMenu(data.menu.data || []);
                }
            });
    };

    // Actions (Edit/Delete)
    document.addEventListener('click', (e) => {
        if(e.target.closest('.edit-btn')) {
            const card = e.target.closest('.menu-card');
            const data = card.dataset;
            
            document.getElementById('item-id').value = data.id;
            document.getElementById('action').value = 'update';
            document.getElementById('name').value = data.name;
            document.getElementById('category').value = data.category;
            document.getElementById('price').value = data.price;
            document.getElementById('stock_quantity').value = data.stock;
            document.getElementById('description').value = data.description;
            document.getElementById('modal-title').textContent = 'Edit Protocol';
            toggleModal(true);
        }
        
        if(e.target.closest('.delete-btn')) {
            if(!confirm('Terminate this protocol?')) return;
            const id = e.target.closest('.menu-card').dataset.id;
            const fd = new FormData();
            fd.append('action', 'delete');
            fd.append('id', id);
            
            fetch('client.php', { method: 'POST', body: fd })
                .then(res => res.json())
                .then(data => {
                    showMsg(data.status, data.message);
                    if(data.status === 'success') renderMenu(data.menu.data || []);
                });
        }
    });

    function fetchUpdates() {
        const fd = new FormData();
        fd.append('action', 'read');
        fetch('client.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success' && data.menu) renderMenu(data.menu.data);
            });
    }

    function calculateKPIs(items) {
        document.getElementById('kpi-total').textContent = items.length;
        
        // Sum Stocks
        const totalStock = items.reduce((sum, item) => sum + parseInt(item.stock_quantity || 0), 0);
        document.getElementById('kpi-stock').textContent = totalStock;

        // Unique Categories
        const categories = new Set(items.map(item => item.category)).size;
        document.getElementById('kpi-types').textContent = categories;

        // Low Stock (< 10)
        const lowStock = items.filter(item => (parseInt(item.stock_quantity) || 0) < 10).length;
        document.getElementById('kpi-low').textContent = lowStock;
    }

    function renderMenu(items) {
        calculateKPIs(items); // Update KPIs
        
        if(!items.length) {
            container.innerHTML = '<div style="grid-column: 1/-1; text-align:center; padding:40px; color:#666;">No data found within parameters.</div>';
            return;
        }

        container.innerHTML = items.map(item => {
            const img = item.image_path || 'https://via.placeholder.com/400x300/1a1a2e/6c5dd2?text=NO+IMG';
            const stockClass = parseInt(item.stock_quantity) < 10 ? 'stock-low' : '';
            
            return `
            <div class="menu-card" 
                data-id="${item.id}"
                data-name="${safe(item.name)}"
                data-category="${safe(item.category)}"
                data-price="${item.price}"
                data-stock="${item.stock_quantity}"
                data-description="${safe(item.description)}">
                
                <div class="card-img-container">
                    <span class="category-badge">${safe(item.category)}</span>
                    <img src="${img}" class="card-img" alt="Food">
                </div>
                
                <div class="card-body">
                    <div class="card-title">${safe(item.name)}</div>
                    <div class="card-stats">
                        <span class="stat-mini">Stock: <strong class="${stockClass}">${item.stock_quantity}</strong></span>
                        <span class="stat-mini">Status: <strong>${item.stock_quantity > 0 ? 'Active' : 'Depleted'}</strong></span>
                    </div>
                    <div class="card-desc">${safe(item.description)}</div>
                    
                    <div class="card-footer">
                        <div class="price-tag">₱${parseFloat(item.price).toFixed(2)}</div>
                        <div style="display:flex; gap:8px;">
                            <button class="btn btn-primary btn-sm edit-btn">Edit</button>
                            <button class="btn btn-danger btn-sm delete-btn">×</button>
                        </div>
                    </div>
                </div>
            </div>`;
        }).join('');
    }

    function resetForm() {
        form.reset();
        document.getElementById('item-id').value = '';
        document.getElementById('action').value = 'create';
        document.getElementById('modal-title').textContent = 'New Food Entry';
    }

    function showMsg(type, text) {
        const el = document.getElementById('status-container');
        el.innerHTML = `<div class="msg" style="border-color: ${type === 'success' ? '#4ecdc4' : '#ff4757'}">${text}</div>`;
        setTimeout(() => el.innerHTML = '', 3000);
    }

    function safe(str) {
        return (str || '').replace(/"/g, '&quot;');
    }
});
</script>
</body>
</html>