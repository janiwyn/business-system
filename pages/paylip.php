<?php
session_start();
include '../includes/db.php';
include '../includes/auth.php';
require_role(["admin", "manager", "staff"]);
include '../pages/sidebar.php';
include '../includes/header.php';

$message = "";

// Get logged-in user info
$user_role   = $_SESSION['role'];
$user_branch = $_SESSION['branch_id'] ?? null;
require(__DIR__ . '/../fpdf/fpdf.php');


$id = isset($_GET['id']) ? intval($_GET['id']) : 0; // Fix: avoid undefined array key

// Fix SQL: use correct column names for payroll and employees/user join
$sql = "SELECT p.*, u.username AS name 
        FROM payroll p 
        JOIN employees e ON p.`user-id` = e.id 
        JOIN users u ON e.`user-id` = u.id
        WHERE p.id='$id'";

$result = mysqli_query($conn, $sql);
$record = mysqli_fetch_assoc($result);

if (!$record) {
    die("No payroll record found for ID $id");
}

//$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial','B',16);
$pdf->Cell(190,10,'Employee Payslip',0,1,'C');
$pdf->Ln(10);

$pdf->SetFont('Arial','',12);
$pdf->Cell(100,10,'Employee: '.$record['name'],0,1);
$pdf->Cell(100,10,'Month: '.$record['month'],0,1);
$pdf->Ln(5);

$pdf->Cell(90,10,'Base Salary: '.$record['base_salary'],0,1);
$pdf->Cell(90,10,'Transport: '.$record['transport'],0,1);
$pdf->Cell(90,10,'Housing: '.$record['housing'],0,1);
$pdf->Cell(90,10,'Medical: '.$record['medical'],0,1);
$pdf->Cell(90,10,'Overtime: '.$record['overtime'],0,1);
$pdf->Ln(5);

$pdf->Cell(90,10,'Gross Salary: '.$record['gross_salary'],0,1);
$pdf->Cell(90,10,'NSSF: '.$record['nssf'],0,1);
$pdf->Cell(90,10,'Tax: '.$record['tax'],0,1);
$pdf->Cell(90,10,'Loan: '.$record['loan'],0,1);
$pdf->Cell(90,10,'Other Deductions: '.$record['other_deductions'],0,1);
$pdf->Ln(5);

$pdf->SetFont('Arial','B',12);
$pdf->Cell(90,10,'Net Salary: '.$record['net_salary'],0,1);

$pdf->Output();
?>
