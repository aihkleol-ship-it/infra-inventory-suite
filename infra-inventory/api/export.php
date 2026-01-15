<?php
// api/export.php
include_once 'config.php';
if (!isset($_SESSION['user_id'])) { http_response_code(401); die("Unauthorized"); }

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=inventory_export_' . date('Y-m-d') . '.csv');

$output = fopen('php://output', 'w');
// Updated Headers
fputcsv($output, ['Hostname', 'IP Address', 'Serial Number', 'Type', 'Brand', 'Model', 'EOS Date', 'Location', 'Sub Location', 'Rack', 'Rack Position', 'Status', 'Asset ID', 'Firmware', 'Notes']);

$sql = "SELECT 
            i.hostname, 
            i.ip_address, 
            i.serial_number, 
            dt.name AS type_name, 
            b.name AS brand_name, 
            m.name AS model_name, 
            m.eos_date, 
            i.location, 
            i.sub_location,
            i.rack,
            i.rack_position,
            i.status, 
            i.asset_id, 
            i.firmware_version, 
            i.notes
        FROM inventory i
        LEFT JOIN device_types dt ON i.type_id = dt.id
        LEFT JOIN brands b ON i.brand_id = b.id
        LEFT JOIN models m ON i.model_id = m.id
        ORDER BY i.hostname ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute();
while ($row = $stmt->fetch(PDO::FETCH_NUM)) { fputcsv($output, $row); }
fclose($output); exit();
?>