<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

include '../../includes/db.php';
include '../../includes/auth.php';
require_role(["admin", "manager", "staff", "super"]);

header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? '';

try {
    if ($action === 'delete_supplier') {
        $id = intval($_POST['id']);
        $stmt = $conn->prepare("DELETE FROM suppliers WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'edit_supplier') {
        $id = intval($_POST['id']);
        $name = trim($_POST['name'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $products = trim($_POST['products'] ?? '');
        $unit_price = floatval($_POST['unit_price'] ?? 0);

        if ($id <= 0 || $name === '') {
            echo json_encode(['success' => false, 'error' => 'Missing fields']);
            exit;
        }

        $stmt = $conn->prepare("UPDATE suppliers SET name=?, location=?, products=?, unit_price=? WHERE id=?");
        $stmt->bind_param("sssdi", $name, $location, $products, $unit_price, $id);
        $stmt->execute();
        $stmt->close();

        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Invalid action']);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
