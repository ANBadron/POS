<?php
// scanner_test.php - Standalone barcode testing tool
?>
<!DOCTYPE html>
<html>
<head>
    <title>Barcode Scanner Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        #results { margin-top: 20px; padding: 10px; border: 1px solid #ddd; }
        .success { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
    <h1>Barcode Scanner Test</h1>
    
    <div>
        <h3>Test Scanner Input:</h3>
        <input type="text" id="scanner-input" placeholder="Scan a barcode here" style="width: 300px; padding: 10px; font-size: 16px;">
        
        <h3>Test API Endpoint:</h3>
        <form id="api-test-form">
            <input type="text" name="barcode" placeholder="Enter barcode" required style="width: 300px; padding: 10px; font-size: 16px;">
            <button type="submit" style="padding: 10px 15px;">Test API</button>
        </form>
    </div>
    
    <div id="results"></div>
    
    <script>
    // Test scanner input
    document.getElementById('scanner-input').addEventListener('input', function(e) {
        const value = e.target.value;
        const results = document.getElementById('results');
        
        results.innerHTML = `
            <h3>Scanner Input Results:</h3>
            <p><strong>Raw Input:</strong> ${value}</p>
            <p><strong>Length:</strong> ${value.length} characters</p>
            <p><strong>Character Codes:</strong> ${Array.from(value).map(c => c.charCodeAt(0)).join(', ')}</p>
            <p><strong>Cleaned (Numeric Only):</strong> ${value.replace(/\D/g, '')}</p>
        `;
    });
    
    // Test API endpoint
    document.getElementById('api-test-form').addEventListener('submit', function(e) {
        e.preventDefault();
        const barcode = e.target.barcode.value;
        const results = document.getElementById('results');
        
        results.innerHTML = '<p>Testing API...</p>';
        
        fetch(`barcode_lookup.php?barcode=${encodeURIComponent(barcode)}`)
            .then(response => response.json())
            .then(data => {
                results.innerHTML = `
                    <h3>API Test Results:</h3>
                    <pre>${JSON.stringify(data, null, 2)}</pre>
                    <p class="${data.status === 'success' ? 'success' : 'error'}">
                        Status: ${data.status}
                    </p>
                `;
            })
            .catch(error => {
                results.innerHTML = `
                    <h3>API Test Failed</h3>
                    <p class="error">${error.message}</p>
                `;
            });
    });
    </script>
</body>
</html>
