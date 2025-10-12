<?php
require_once(__DIR__ . '/../../config.php'); // Adjust as needed for your plugin path
require_login();

$viewdata = optional_param('data', '', PARAM_RAW); // optional, or load your data however you want

// Example: if $viewdata is stored in session or generated dynamically
if (empty($viewdata) && isset($_SESSION['viewdata'])) {
    $viewdata = $_SESSION['viewdata'];
}

if (empty($viewdata)) {
    // Or however you normally build it
    $viewdata = ['error' => 'No data available'];
}

// Send JSON headers
header('Content-Type: application/json');
header('Content-Disposition: attachment; filename="viewdata.json"');

// Output the JSON
echo json_encode($viewdata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
exit;
