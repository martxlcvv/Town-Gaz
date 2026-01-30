<?php
// Simple test to simulate delete request
session_start();
require_once '../config/database.php';

// Log request details
error_log('[TEST_DELETE] REQUEST METHOD: ' . $_SERVER['REQUEST_METHOD']);
error_log('[TEST_DELETE] POST keys: ' . implode(',', array_keys($_POST)));
error_log('[TEST_DELETE] has delete_product: ' . (isset($_POST['delete_product']) ? 'YES' : 'NO'));
error_log('[TEST_DELETE] has session_token: ' . (isset($_POST['session_token']) ? 'YES' : 'NO'));

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    error_log('[TEST_DELETE] Processing POST');
    
    if (isset($_POST['delete_product'])) {
        error_log('[TEST_DELETE] delete_product is set: ' . $_POST['delete_product']);
        
        if (isset($_POST['session_token'])) {
            $token = $_POST['session_token'];
            error_log('[TEST_DELETE] Token provided: ' . substr($token, 0, 10) . '...');
            error_log('[TEST_DELETE] Session token stored: ' . (empty($_SESSION['session_token']) ? 'MISSING' : substr($_SESSION['session_token'], 0, 10) . '...'));
        }
    }
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Test response',
        'post_keys' => array_keys($_POST),
        'session_token_set' => !empty($_SESSION['session_token'])
    ]);
    die();
}

// If GET, show form for testing
?>
<form method="POST" id="testForm">
    <input type="hidden" name="delete_product" value="1">
    <input type="hidden" name="product_id" value="123">
    <input type="hidden" name="delete_pin" value="000000">
    <input type="hidden" name="session_token" value="<?php echo htmlspecialchars(get_session_token()); ?>">
    <button type="submit">Test Delete</button>
</form>

<script>
// Also test with fetch
async function testFetch() {
    const formData = new FormData();
    formData.append('delete_product', '1');
    formData.append('product_id', '456');
    formData.append('delete_pin', '000000');
    formData.append('session_token', document.querySelector('input[name="session_token"]').value);
    
    console.log('[FETCH_TEST] Sending:', {
        delete_product: formData.get('delete_product'),
        product_id: formData.get('product_id'),
        session_token: formData.get('session_token').substring(0, 10) + '...'
    });
    
    const response = await fetch('test-delete.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    });
    
    const result = await response.json();
    console.log('[FETCH_TEST] Response:', result);
}
</script>

<p><button onclick="testFetch()">Test with Fetch</button></p>
