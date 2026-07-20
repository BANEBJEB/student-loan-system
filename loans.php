<?php
/**
 * CRUD API for managing a student's loans.
 *
 * Endpoints:
 * - GET    ?student_id=1        -> list all loans for that student
 * - POST   { student_id, amount, loan_type, status } -> create a loan
 * - PUT    { id, amount, loan_type, status }         -> update a loan
 * - DELETE { id }                                     -> delete a loan
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

$allowed_types = ['Tuition', 'Books', 'Living Expenses'];
$allowed_status = ['Pending', 'Approved', 'Disbursed'];

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // READ: all loans for a given student
        if (empty($_GET['student_id'])) {
            http_response_code(400);
            echo json_encode(["error" => "student_id is required"]);
            break;
        }
        $stmt = $pdo->prepare("SELECT * FROM loans WHERE student_id = ? ORDER BY id DESC");
        $stmt->execute([$_GET['student_id']]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'POST':
        // CREATE: add a new loan
        $data = json_decode(file_get_contents("php://input"), true);
        if (
            !empty($data['student_id']) &&
            isset($data['amount']) && is_numeric($data['amount']) &&
            in_array($data['loan_type'] ?? '', $allowed_types, true) &&
            in_array($data['status'] ?? 'Pending', $allowed_status, true)
        ) {
            // Confirm the student actually exists before inserting
            $check = $pdo->prepare("SELECT id FROM students WHERE id = ?");
            $check->execute([$data['student_id']]);
            if (!$check->fetch()) {
                http_response_code(404);
                echo json_encode(["error" => "Student not found"]);
                break;
            }

            $stmt = $pdo->prepare("INSERT INTO loans (student_id, amount, loan_type, status) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $data['student_id'],
                $data['amount'],
                $data['loan_type'],
                $data['status'] ?? 'Pending'
            ]);
            echo json_encode(["message" => "Loan added successfully!"]);
        } else {
            http_response_code(400);
            echo json_encode(["error" => "Valid student_id, amount, loan_type, and status are required"]);
        }
        break;

    case 'PUT':
        // UPDATE: modify an existing loan
        $data = json_decode(file_get_contents("php://input"), true);
        if (
            !empty($data['id']) &&
            isset($data['amount']) && is_numeric($data['amount']) &&
            in_array($data['loan_type'] ?? '', $allowed_types, true) &&
            in_array($data['status'] ?? '', $allowed_status, true)
        ) {
            $stmt = $pdo->prepare("UPDATE loans SET amount = ?, loan_type = ?, status = ? WHERE id = ?");
            $stmt->execute([
                $data['amount'],
                $data['loan_type'],
                $data['status'],
                $data['id']
            ]);
            echo json_encode(["message" => "Loan updated successfully!"]);
        } else {
            http_response_code(400);
            echo json_encode(["error" => "Invalid data provided"]);
        }
        break;

    case 'DELETE':
        // DELETE: remove a loan
        $data = json_decode(file_get_contents("php://input"), true);
        if (!empty($data['id'])) {
            $stmt = $pdo->prepare("DELETE FROM loans WHERE id = ?");
            $stmt->execute([$data['id']]);
            echo json_encode(["message" => "Loan deleted successfully!"]);
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
