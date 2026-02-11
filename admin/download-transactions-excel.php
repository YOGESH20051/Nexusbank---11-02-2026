<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

require_once __DIR__ . '/../vendor/autoload.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

/* ======================
   ADMIN PROTECTION
====================== */
redirectIfNotAdmin();

logAdminAction(
    $pdo,
    $_SESSION['user_id'],
    'Report Download',
    'Downloaded transaction report Excel'
);


/* ======================
   GET FILTERS
====================== */
$from    = $_GET['from_date'] ?? null;
$to      = $_GET['to_date'] ?? null;
$user_id = $_GET['user_id'] ?? null;

if (!$from || !$to) die("Invalid date range.");

/* ======================
   FETCH DATA
====================== */
$sql = "
SELECT 
    t.transaction_id   AS ID,
    u.full_name        AS User,
    u.email            AS Email,
    t.type             AS Type,
    t.amount           AS Amount,
    t.description      AS Description,
    t.created_at       AS Date
FROM transactions t
JOIN accounts a ON t.account_id = a.account_id
JOIN users u ON a.user_id = u.user_id
WHERE DATE(t.created_at) BETWEEN :from AND :to
";

$params = [':from'=>$from, ':to'=>$to];

if (!empty($user_id)) {
    $sql .= " AND u.user_id = :user_id";
    $params[':user_id'] = $user_id;
}

$sql .= " ORDER BY t.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ======================
   CREATE EXCEL
====================== */
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Transactions');

/* ======================
   LOGO (Top Right Header)
====================== */
$logoPath = realpath(__DIR__ . '/../assets/images/LOGO-RECEIPT.png');

$drawing = new Drawing();
$drawing->setPath($logoPath);
$drawing->setHeight(60);

/* Place logo aligned with title */
$drawing->setCoordinates('A2');

/* Small offset so it hugs the right neatly */
$drawing->setOffsetX(10);
$drawing->setOffsetY(5);

$drawing->setWorksheet($sheet);

/* Reserve space for logo */
$sheet->getColumnDimension('H')->setWidth(24);
$sheet->getRowDimension(1)->setRowHeight(55);


/* ======================
   HEADER
====================== */
$sheet->mergeCells('C2:G2');
$sheet->setCellValue('C2','NEXUS BANKING SYSTEM – TRANSACTION REPORT');
$sheet->getStyle('C2')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('C2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->mergeCells('C3:G3');
$sheet->setCellValue('C3',"From: $from   To: $to".($user_id?" | User ID: $user_id":" | All Users"));
$sheet->getStyle('C3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

/* ======================
   TABLE HEADER
====================== */
$startRow = 7;
$headers = ['ID','User','Email','Type','Amount','Description','Date'];
$sheet->fromArray($headers,NULL,"A$startRow");

$sheet->getStyle("A$startRow:G$startRow")->applyFromArray([
    'font'=>['bold'=>true,'color'=>['rgb'=>'FFFFFF']],
    'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'1F2937']],
    'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER]
]);

$sheet->setAutoFilter("A$startRow:G$startRow");

/* ======================
   DATA
====================== */
$row = $startRow+1;
foreach($data as $record){
    $sheet->fromArray(array_values($record),NULL,"A$row");

    if(in_array($record['Type'],['deposit','transfer_in'])){
        $sheet->getStyle("E$row")->getFont()->getColor()->setRGB('008000');
    } else {
        $sheet->getStyle("E$row")->getFont()->getColor()->setRGB('C00000');
    }
    $row++;
}

/* ======================
   FREEZE HEADER
====================== */
$sheet->freezePane("A".($startRow+1));

/* ======================
   FORMATTING
====================== */
$sheet->getStyle("E".($startRow+1).":E$row")->getNumberFormat()
      ->setFormatCode('₹ #,##,##0.00');

$sheet->getStyle("G".($startRow+1).":G$row")->getNumberFormat()
      ->setFormatCode('dd-mm-yyyy hh:mm');

/* ======================
   TOTALS
====================== */
$credit=$debit=0;
foreach($data as $r){
    if(in_array($r['Type'],['deposit','transfer_in'])) $credit+=$r['Amount'];
    else $debit+=$r['Amount'];
}

$sheet->setCellValue("D$row","TOTAL CREDIT");
$sheet->setCellValue("E$row",$credit);
$row++;

$sheet->setCellValue("D$row","TOTAL DEBIT");
$sheet->setCellValue("E$row",$debit);

/* Highlight totals */
$sheet->getStyle("D".($row-1).":E$row")->applyFromArray([
    'font'=>['bold'=>true],
    'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'E5F2FF']]
]);

/* ======================
   BORDERS
====================== */
$sheet->getStyle("A$startRow:G$row")->getBorders()->getAllBorders()
      ->setBorderStyle(Border::BORDER_THIN);

/* ======================
   AUTOSIZE
====================== */
foreach(range('A','G') as $c){
    $sheet->getColumnDimension($c)->setAutoSize(true);
}

/* ======================
   FOOTER
====================== */
$sheet->getHeaderFooter()->setOddFooter('&CPage &P of &N');

/* ======================
   DOWNLOAD
====================== */
$filename = "transactions_{$from}_to_{$to}".($user_id?"_user_$user_id":"").".xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"$filename\"");
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
