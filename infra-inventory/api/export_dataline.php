<?php
// api/export_dataline.php
include_once 'config.php';
if (!isset($_SESSION['user_id'])) { http_response_code(401); die("Unauthorized"); }

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=dataline_export_' . date('Y-m-d') . '.csv');

$output = fopen('php://output', 'w');

// Dataline headers from the `dataline` table schema
$headers = [
    'site', 'usage_type', 'bandwidth', 'end_device', 'end_device_mgmt_ip', 
    'wan_ip', 'wan_ip_count', 'gateway', 'service_provider', 'circuit_id', 
    'circuit_description', 'installation_address', 'account_number', 'account_name', 
    'monthly_charge_hkd', 'contract_end_date', 'contract_status'
];
fputcsv($output, $headers);

$sql = "SELECT 
            site, usage_type, bandwidth, end_device, end_device_mgmt_ip, 
            wan_ip, wan_ip_count, gateway, service_provider, circuit_id, 
            circuit_description, installation_address, account_number, account_name, 
            monthly_charge_hkd, contract_end_date, contract_status
        FROM dataline
        ORDER BY site ASC, circuit_id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute();
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // Ensure the order of columns matches the headers
    $line = [];
    foreach ($headers as $header) {
        $line[] = $row[$header];
    }
    fputcsv($output, $line);
}
fclose($output);
exit();
?>