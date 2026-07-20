<?php
/**
 * Reference implementation — CRUD API for a loan's payments.
 * Frontend/UI contract: use this to test locally; hand off to
 * the Backend Developer as the agreed endpoint shape.
 *
 * Endpoints:
 * - GET    ?loan_id=1                                         -> list all payments for that loan
 * - POST   { loan_id, amount, payment_date, payment_method }  -> create a payment
 * - PUT    { id, amount, payment_date, payment_method }       -> update a payment
 * - DELETE { id }                                             -> delete a payment
 */
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type");

$host = "db";
$user = "root";
$pass = "rootpassword";
$dbname = "school_db";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Connection failed: " . $e->getMessage()]);
    exit;
}

$allowed_methods = ['Cash', 'Bank Transfer', 'Online Payment'];

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // READ: all payments for a given loan
        if (empty($_GET['loan_id'])) {
            http_response_code(400);
            echo json_encode(["error" => "loan_id is required"]);
            break;
        }
        $stmt = $pdo->prepare("SELECT * FROM payments WHERE loan_id = ? ORDER BY payment_date DESC, id DESC");
        $stmt->execute([$_GET['loan_id']]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'POST':
        // CREATE: add a new payment
        $data = json_decode(file_get_contents("php://input"), true);
        if (
            !empty($data['loan_id']) &&
            isset($data['amount']) && is_numeric($data['amount']) &&
            !empty($data['payment_date']) &&
            in_array($data['payment_method'] ?? '', $allowed_methods, true)
        ) {
            $check = $pdo->prepare("SELECT id FROM loans WHERE id = ?");
            $check->execute([$data['loan_id']]);
            if (!$check->fetch()) {
                http_response_code(404);
                echo json_encode(["error" => "Loan not found"]);
                break;
            }

            $stmt = $pdo->prepare("INSERT INTO payments (loan_id, amount, payment_date, payment_method) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $data['loan_id'],
                $data['amount'],
                $data['payment_date'],
                $data['payment_method']
            ]);
            echo json_encode(["message" => "Payment added successfully!"]);
        } else {
            http_response_code(400);
            echo json_encode(["error" => "Valid loan_id, amount, payment_date, and payment_method are required"]);
        }
        break;

    case 'PUT':
        // UPDATE: modify an existing payment
        $data = json_decode(file_get_contents("php://input"), true);
        if (
            !empty($data['id']) &&
            isset($data['amount']) && is_numeric($data['amount']) &&
            !empty($data['payment_date']) &&
            in_array($data['payment_method'] ?? '', $allowed_methods, true)
        ) {
            $stmt = $pdo->prepare("UPDATE payments SET amount = ?, payment_date = ?, payment_method = ? WHERE id = ?");
            $stmt->execute([
                $data['amount'],
                $data['payment_date'],
                $data['payment_method'],
                $data['id']
            ]);
            echo json_encode(["message" => "Payment updated successfully!"]);
        } else {
            http_response_code(400);
            echo json_encode(["error" => "Invalid data provided"]);
        }
        break;

    case 'DELETE':
        // DELETE: remove a payment
        $data = json_decode(file_get_contents("php://input"), true);
        if (!empty($data['id'])) {
            $stmt = $pdo->prepare("DELETE FROM payments WHERE id = ?");
            $stmt->execute([$data['id']]);
            echo json_encode(["message" => "Payment deleted successfully!"]);
        } else {
            http_response_code(400);
            echo json_encode(["error" => "ID required"]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(["error" => "Method not allowed"]);
        break;
}
