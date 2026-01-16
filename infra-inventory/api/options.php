<?php
// api/options.php
include_once 'config.php';

try {
    // 1. Fetch Device Types
    $stmt = $pdo->query("SELECT id, name FROM device_types ORDER BY name ASC");
    $types = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch Brands
    $stmt = $pdo->query("SELECT id, name FROM brands ORDER BY name ASC");
    $brands = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Fetch Models (Include brand_id for filtering on frontend)
    $stmt = $pdo->query("SELECT id, brand_id, name, rack_units, eos_date FROM models ORDER BY name ASC");
    $models = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "types" => $types,
        "brands" => $brands,
        "models" => $models
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
?>