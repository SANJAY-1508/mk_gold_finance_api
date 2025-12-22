<?php
include 'headers.php';
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit();
}
header('Content-Type: application/json; charset=utf-8');

$json = file_get_contents('php://input');
$obj = json_decode($json);
$output = array();
date_default_timezone_set('Asia/Calcutta');
$timestamp = date('Y-m-d H:i:s');
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$domain = $_SERVER['HTTP_HOST'];
$base_url = $protocol . $domain;

function calculateInterestPeriod($startDate) {
    $start = new DateTime($startDate);
    $end = new DateTime(); // Current date

    // DAYS360 logic - treat each month as 30 days
    $start_day = min((int)$start->format('d'), 30);
    $end_day = min((int)$end->format('d'), 30);

    $days360 = ($end->format('Y') - $start->format('Y')) * 360
             + ($end->format('m') - $start->format('m')) * 30
             + ($end_day - $start_day);

    // If loan age < 15 days, charge minimum 15 days interest
    if ($days360 < 15) {
        $days360 = 15;
    }

    $fullMonths = floor($days360 / 30);
    $remainingDays = $days360 % 30;

    return [
        'months' => $fullMonths,
        'days' => $remainingDays,
        'total_days360' => $days360
    ];
}




// <<<<<<<<<<===================== List Pawn Jewelry =====================>>>>>>>>>>

if (isset($obj->search_text)) {
    $search_text = $conn->real_escape_string($obj->search_text);
    $sql = "SELECT * FROM `pawnjewelry` 
            WHERE `delete_at` = 0 
            AND (`receipt_no` LIKE '%$search_text%' OR `mobile_number` LIKE '%$search_text%' OR `name` LIKE '%$search_text%'  OR `customer_no` LIKE '%$search_text%')
            ORDER BY `id` DESC";

    $result = $conn->query($sql);
    if ($result === false) {
        error_log("Pawnjewelry query failed: " . $conn->error);
        $output["head"]["code"] = 500;
        $output["head"]["msg"] = "Database error";
        echo json_encode($output, JSON_NUMERIC_CHECK);
        exit();
    }

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $interest_payment_period_db = $row['interest_payment_period'];
            $total_months = floor($interest_payment_period_db);
            $row['interest_payment_period'] = $total_months . " days";
            $row['proof'] = json_decode($row['proof'], true) ?? [];
            $row['proof_base64code'] =  [];
            // json_decode($row['proof_base64code'], true) ??
            $row['aadharproof'] = json_decode($row['aadharproof'], true) ?? [];
            $row['aadharprood_base64code'] =  [];
         $row['interest_rate'] = (string)number_format((float)$row['interest_rate'], 2, '.', '');


            //json_decode($row['aadharprood_base64code'], true) ??
 $full_proof_urls = [];
foreach ($row['proof'] as $proof_path) {
    // Normalize and remove leading "../"
    $cleaned_path = ltrim($proof_path, '../');
    $full_url = $base_url . '/' . $cleaned_path;
    $full_proof_urls[] = $full_url;
}
$row['proof'] = $full_proof_urls;
$full_proof_urls1 = [];
foreach ($row['aadharproof'] as $proof_path) {
    // Normalize and remove leading "../"
    $cleaned_path = ltrim($proof_path, '../');
    $full_url = $base_url . '/' . $cleaned_path;
    $full_proof_urls1[] = $full_url;
}
$row['aadharproof'] = $full_proof_urls1;
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Success";
            $output["body"]["pawnjewelry"][] = $row;
        }
    } else {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "No records found";
        $output["body"]["pawnjewelry"] = [];
    }
}
// Create Pawn Jewelry
else
// Assuming $conn, $output, getBalance(), calculateInterestPeriod(), uniqueID(), and addTransaction() are defined elsewhere

if (isset($obj->receipt_no) && !isset($obj->edit_pawnjewelry_id)) {
    $pawnjewelry_date = isset($obj->pawnjewelry_date) ? $conn->real_escape_string($obj->pawnjewelry_date) : '';
    $customer_no = isset($obj->customer_no) ? $conn->real_escape_string($obj->customer_no) : '';
    $receipt_no = isset($obj->receipt_no) ? $conn->real_escape_string($obj->receipt_no) : '';
    $name = isset($obj->name) ? $conn->real_escape_string($obj->name) : '';
     $raw_address = $obj->customer_details;
    $cleaned_address = str_replace(['/', '\\n', '\n', "\n", "\r"], ' ', $raw_address);
    $cleaned_address = preg_replace('/\s+/', ' ', $cleaned_address);
    $cleaned_address = trim($cleaned_address);
    $customer_details = $conn->real_escape_string($cleaned_address);
    $place = isset($obj->place) ? $conn->real_escape_string($obj->place) : '';
    $mobile_number = isset($obj->mobile_number) ? $conn->real_escape_string($obj->mobile_number) : '';
    $original_amount = isset($obj->original_amount) ? $conn->real_escape_string($obj->original_amount) : 0;
    $jewel_product = isset($obj->jewel_product) ? $obj->jewel_product : [];
    $Jewelry_recovery_agreed_period = isset($obj->Jewelry_recovery_agreed_period) ? $conn->real_escape_string($obj->Jewelry_recovery_agreed_period) : '';
    $interest_rate = isset($obj->interest_rate) ? $conn->real_escape_string($obj->interest_rate) : 0;
    $group_type = isset($obj->group_type) ? $conn->real_escape_string($obj->group_type) : '';
    $proof = isset($obj->proof) ? $obj->proof : [];
    $aadharproof = isset($obj->aadharproof) ? $obj->aadharproof : [];
    $dateofbirth = isset($obj->dateofbirth) ? $obj->dateofbirth : "";
    $dateofbirth = !empty($dateofbirth) ? $dateofbirth : null;
    $proof_number = isset($obj->proof_number) ? $obj->proof_number : "";
    $upload_type = isset($obj->upload_type) ? $obj->upload_type : "";
    // New bank pledge fields
    $bank_pledge_date = isset($obj->bank_pledge_date) ? $conn->real_escape_string($obj->bank_pledge_date) : '';
    $bank_assessor_name = isset($obj->bank_assessor_name) ? $conn->real_escape_string($obj->bank_assessor_name) : '';
    $bank_name = isset($obj->bank_name) ? $conn->real_escape_string($obj->bank_name) : '';
     $raw_pawnvalue = isset($obj->bank_pawn_value) ? trim($obj->bank_pawn_value) : '';
$bank_pawn_value_clean = ($raw_pawnvalue === '' || !is_numeric($raw_pawnvalue)) ? 0 : intval($raw_pawnvalue);
$bank_pawn_value = $conn->real_escape_string($bank_pawn_value_clean);
   $bank_interest_raw = isset($obj->bank_interest) ? trim($obj->bank_interest) : '';
$bank_interest_clean = ($bank_interest_raw === '' || !is_numeric($bank_interest_raw)) ? 0.00 : floatval($bank_interest_raw);
$bank_interest = $conn->real_escape_string($bank_interest_clean);
  $bank_duration = isset($obj->bank_duration) ? $conn->real_escape_string($obj->bank_duration) : '';
  $bank_duration = !empty($bank_duration) ? $bank_duration : null;
  

    $bank_additional_charges = isset($obj->bank_additional_charges) ? $conn->real_escape_string($obj->bank_additional_charges) : 0;
     $location = $conn->real_escape_string($obj->location);
    $type1 = "patru";
    $current_balance = getBalance($conn);

    if ($current_balance < $original_amount) {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Insufficient balance!";
        echo json_encode($output, JSON_NUMERIC_CHECK);
        exit();
    }

    // Updated validation to include bank pledge fields
    if (
        !isset($pawnjewelry_date) || trim($pawnjewelry_date) === '' ||
        !isset($customer_no) || trim($customer_no) === '' ||
        !isset($receipt_no) || trim($receipt_no) === '' ||
        !isset($name) || trim($name) === '' ||
        !isset($customer_details) || trim($customer_details) === '' ||
        !isset($place) || trim($place) === '' ||
        !isset($original_amount) || !is_numeric($original_amount) || $original_amount <= 0 ||
        !isset($Jewelry_recovery_agreed_period) || trim($Jewelry_recovery_agreed_period) === '' ||
        !isset($interest_rate) || trim($interest_rate) === '' ||
        !isset($group_type) || trim($group_type) === ''
    ) {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Please provide all required fields";
        echo json_encode($output, JSON_NUMERIC_CHECK);
        exit();
    }

    if (!is_array($jewel_product) || count($jewel_product) === 0) {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "jewel_product is required and must be a non-empty array";
        echo json_encode($output, JSON_NUMERIC_CHECK);
        exit();
    }

    if (!is_numeric($original_amount) || $original_amount <= 0) {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Original amount must be a positive number.";
        echo json_encode($output, JSON_NUMERIC_CHECK);
        exit();
    }

    try {
        $datetime1 = new DateTime($pawnjewelry_date);
        $bank_pledge_datetime = new DateTime($bank_pledge_date);
    } catch (Exception $e) {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Invalid date format for pawn jewelry or bank pledge date.";
        echo json_encode($output, JSON_NUMERIC_CHECK);
        exit();
    }

    $current_date = date('Y-m-d');
    $datetime2 = new DateTime($current_date);

    if ($datetime1 > $datetime2 || $bank_pledge_datetime > $datetime2) {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Pawn jewelry date or bank pledge date cannot be in the future.";
        echo json_encode($output, JSON_NUMERIC_CHECK);
        exit();
    }

    $period = calculateInterestPeriod($pawnjewelry_date);
$actualMonths = $period['months'];
$actualDays = $period['days'];
$totalDays360 = $period['total_days360'];
$agreedMonths = (int)$Jewelry_recovery_agreed_period;

// Determine interest rate
$interest_rate_numeric = floatval(str_replace('%', '', $interest_rate));
$effectiveInterestRate = $interest_rate;

if (
    ($actualMonths > $agreedMonths || 
    ($actualMonths == $agreedMonths && $actualDays > 0)) 
    && $interest_rate_numeric < 20
) {
    $effectiveInterestRate = '1.67';
}


$interest_rate_value = floatval(str_replace('%', '', $effectiveInterestRate)) / 100;

// Interest calculations
$monthly_interest = $original_amount * $interest_rate_value;
$daily_interest = $monthly_interest / 30;
$interest_payment_amount = round($daily_interest * $totalDays360, 2);

$interest_payment_period_db = $totalDays360;


    // Process Proof Files
    $proofPaths = [];
    $proofBase64Codes = [];
    if (is_string($proof)) {
        $base64File = (object)['data' => $proof];
        $proofArray = [$base64File];
    } elseif (is_array($proof)) {
        $proofArray = $proof;
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Proof must be a Base64 string or an array of Base64 strings.";
        echo json_encode($output, JSON_NUMERIC_CHECK);
        exit();
    }

    foreach ($proofArray as $base64File) {
        if (!isset($base64File->data) || !is_string($base64File->data)) {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Invalid file format. Expected Base64 encoded string.";
            echo json_encode($output, JSON_NUMERIC_CHECK);
            exit();
        }

        $proofBase64Codes[] = $base64File->data;
        $fileData = $base64File->data;
        $fileName = uniqid("file_");
        $filePath = "";

        if (preg_match('/^data:application\/pdf;base64,/', $fileData)) {
            $fileName .= ".pdf";
            $filePath = "../Uploads/pdfs/" . $fileName;
        } elseif (preg_match('/^data:image\/(\w+);base64,/', $fileData, $type)) {
            $fileName .= "." . strtolower($type[1]);
            $filePath = "../Uploads/images/" . $fileName;
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Unsupported file type.";
            echo json_encode($output, JSON_NUMERIC_CHECK);
            exit();
        }

        $fileData = preg_replace('/^data:.*;base64,/', '', $fileData);
        $decodedFile = base64_decode($fileData);
        if ($decodedFile === false) {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Base64 decoding failed.";
            echo json_encode($output, JSON_NUMERIC_CHECK);
            exit();
        }

        $directory = dirname($filePath);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        if (file_put_contents($filePath, $decodedFile) === false) {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Failed to save the file.";
            echo json_encode($output, JSON_NUMERIC_CHECK);
            exit();
        }

        $publicPath = str_replace("../", "", $filePath);
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
        $domain = $protocol . "://" . $_SERVER['HTTP_HOST'];
        $networkPath = $domain . "/" . $publicPath;
        $proofPaths[] = $filePath;
    }

    // Process Aadhaar Proof Files
    $aadharProofPaths = [];
    $aadharProofBase64Codes = [];
    if (is_string($aadharproof)) {
        $base64File = (object)['data' => $aadharproof];
        $aadharproof = [$base64File];
    } elseif (is_array($aadharproof)) {
        $aadharproof = $aadharproof;
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Proof must be a Base64 string or an array of Base64 strings.";
        echo json_encode($output, JSON_NUMERIC_CHECK);
        exit();
    }
    foreach ($aadharproof as $base64File) {
        if (!isset($base64File->data) || !is_string($base64File->data)) {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Invalid Aadhaar proof file format. Expected Base64 encoded string.";
            echo json_encode($output, JSON_NUMERIC_CHECK);
            exit();
        }

        $aadharProofBase64Codes[] = $base64File->data;
        $fileData = $base64File->data;
        $fileName = uniqid("aadhar_");
        $filePath = "";

        if (preg_match('/^data:application\/pdf;base64,/', $fileData)) {
            $fileName .= ".pdf";
            $filePath = "../Uploads/aadhar/" . $fileName;
        } elseif (preg_match('/^data:image\/(\w+);base64,/', $fileData, $type)) {
            $fileName .= "." . strtolower($type[1]);
            $filePath = "../Uploads/aadhar/" . $fileName;
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Unsupported Aadhaar proof file type.";
            echo json_encode($output, JSON_NUMERIC_CHECK);
            exit();
        }

        $fileData = preg_replace('/^data:.*;base64,/', '', $fileData);
        $decodedFile = base64_decode($fileData);
        if ($decodedFile === false) {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Aadhaar proof Base64 decoding failed.";
            echo json_encode($output, JSON_NUMERIC_CHECK);
            exit();
        }

        $directory = dirname($filePath);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        if (file_put_contents($filePath, $decodedFile) === false) {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Failed to save Aadhaar proof file.";
            echo json_encode($output, JSON_NUMERIC_CHECK);
            exit();
        }

        $aadharProofPaths[] = $filePath;
    }

    $proofJson = json_encode($proofPaths, JSON_UNESCAPED_SLASHES);
    $proofBase64CodeJson = json_encode($proofBase64Codes, JSON_UNESCAPED_SLASHES);
    $aadharProofJson = json_encode($aadharProofPaths, JSON_UNESCAPED_SLASHES);
    $aadharProofBase64CodeJson = json_encode($aadharProofBase64Codes, JSON_UNESCAPED_SLASHES);
    $products_json = json_encode($jewel_product, JSON_UNESCAPED_UNICODE);

    $stmt = $conn->prepare("SELECT id FROM pawnjewelry WHERE receipt_no = ? AND delete_at = 0");
    $stmt->bind_param("s", $receipt_no);
    $stmt->execute();
    $pawnjewelryCheck = $stmt->get_result();
    $stmt->close();

    if ($pawnjewelryCheck->num_rows == 0) {
        $stmt = $conn->prepare("INSERT INTO pawnjewelry (
            pawnjewelry_date, customer_no, receipt_no, name, customer_details, place, mobile_number, 
            original_amount, jewel_product, Jewelry_recovery_agreed_period, interest_rate, interest_payment_period, 
            interest_payment_amount, group_type, proof, proof_base64code, aadharproof, aadharprood_base64code, 
            create_at, dateofbirth, proof_number, upload_type, bank_pledge_date, bank_assessor_name, 
            bank_name, bank_pawn_value, bank_interest, bank_duration, bank_additional_charges,location
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param(
            "sssssssssssdssssssssssssssdsds",
            $pawnjewelry_date, $customer_no, $receipt_no, $name, $customer_details, $place, $mobile_number,
            $original_amount, $products_json, $Jewelry_recovery_agreed_period, $effectiveInterestRate, $interest_payment_period_db,
            $interest_payment_amount, $group_type, $proofJson, $proofBase64CodeJson, $aadharProofJson, $aadharProofBase64CodeJson,
            $timestamp, $dateofbirth, $proof_number, $upload_type, $bank_pledge_date, $bank_assessor_name,
            $bank_name, $bank_pawn_value, $bank_interest, $bank_duration, $bank_additional_charges,$location
        );

        if ($stmt->execute()) {
            $id = $conn->insert_id;
            $uniquePawnJewelryID = uniqueID('pawnjewelry', $id);
            $updateStmt = $conn->prepare("UPDATE pawnjewelry SET pawnjewelry_id = ? WHERE id = ?");
            $updateStmt->bind_param("si", $uniquePawnJewelryID, $id);
            $updateStmt->execute();
            $updateStmt->close();

            $result = addTransaction($conn, $name, $original_amount, $type1, $pawnjewelry_date);
            
            if ($result) {
                $output["head"]["code"] = 200;
                $output["head"]["msg"] = "Pawn jewelry created successfully";
                $output["body"]["pawnjewelry_id"] = $uniquePawnJewelryID; 
            } else {
                $output["head"]["code"] = 500;
                $output["head"]["msg"] = "Pawn jewelry created but transaction not saved";
            }
        } else {
            error_log("Pawnjewelry insert failed: " . $stmt->error);
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Failed to create pawn jewelry. Please try again.";
        }
        $stmt->close();
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Receipt number already exists.";
    }
}

// Update Pawn Jewelry
elseif (isset($obj->edit_pawnjewelry_id)) {
    $edit_id = $conn->real_escape_string($obj->edit_pawnjewelry_id);
    $customer_no = $conn->real_escape_string($obj->customer_no);
    $receipt_no = $conn->real_escape_string($obj->receipt_no);
    $name = $conn->real_escape_string($obj->name);
    $raw_address = $obj->customer_details;
    $cleaned_address = str_replace(['/', '\\n', '\n', "\n", "\r"], ' ', $raw_address);
    $cleaned_address = preg_replace('/\s+/', ' ', $cleaned_address);
    $cleaned_address = trim($cleaned_address);
    $customer_details = $conn->real_escape_string($cleaned_address);

    $place = $conn->real_escape_string($obj->place);
    $mobile_number = $conn->real_escape_string($obj->mobile_number);
    $original_amount = $conn->real_escape_string($obj->original_amount);
    $jewel_product = isset($obj->jewel_product) ? $obj->jewel_product : [];
    $Jewelry_recovery_agreed_period = $conn->real_escape_string($obj->Jewelry_recovery_agreed_period);
    $interest_rate = $conn->real_escape_string($obj->interest_rate);
    $group_type = $conn->real_escape_string($obj->group_type);
    $proof = isset($obj->proof) ? $obj->proof : [];
    $aadharproof = isset($obj->aadharproof) ? $obj->aadharproof : [];
    // New bank pledge fields
    $bank_pledge_date = isset($obj->bank_pledge_date) ? $conn->real_escape_string($obj->bank_pledge_date) : '';
    $bank_assessor_name = isset($obj->bank_assessor_name) ? $conn->real_escape_string($obj->bank_assessor_name) : '';
    $bank_name = isset($obj->bank_name) ? $conn->real_escape_string($obj->bank_name) : '';
    $bank_pawn_value = isset($obj->bank_pawn_value) ? $conn->real_escape_string($obj->bank_pawn_value) : 0;
   $bank_interest_raw = isset($obj->bank_interest) ? trim($obj->bank_interest) : '';
$bank_interest_clean = ($bank_interest_raw === '' || !is_numeric($bank_interest_raw)) ? 0.00 : floatval($bank_interest_raw);
$bank_interest = $conn->real_escape_string($bank_interest_clean);

   $bank_duration = isset($obj->bank_duration) ? $conn->real_escape_string($obj->bank_duration) : '';
   $bank_duration = !empty($bank_duration) ? $bank_duration : null;
$location = $conn->real_escape_string($obj->location);

   $bank_additional_charges = (isset($obj->bank_additional_charges) && is_numeric($obj->bank_additional_charges)) 
    ? (float) $obj->bank_additional_charges 
    : 0.0;


    // Fetch existing pawn jewelry details
    $stmt = $conn->prepare("SELECT interest_payment_period, pawnjewelry_date, interest_rate, original_amount 
                            FROM pawnjewelry 
                            WHERE pawnjewelry_id = ? AND delete_at = 0");
    $stmt->bind_param("s", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows === 0) {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Pawn jewelry record not found.";
        echo json_encode($output, JSON_NUMERIC_CHECK);
        exit();
    }

    $pawn_data = $result->fetch_assoc();
    $interest_payment_period_db = $pawn_data['interest_payment_period'];
    $pawnjewelry_date = $pawn_data['pawnjewelry_date'];
    $old_interest_rate = $pawn_data['interest_rate'];
    $original_amount_db = $pawn_data['original_amount'];

    // Validate bank pledge fields
    if (
        !isset($customer_no) || trim($customer_no) === '' ||
        !isset($receipt_no) || trim($receipt_no) === '' ||
        !isset($name) || trim($name) === '' ||
        !isset($customer_details) || trim($customer_details) === '' ||
        !isset($place) || trim($place) === '' ||
        !isset($original_amount) || !is_numeric($original_amount) || $original_amount <= 0 ||
        !isset($Jewelry_recovery_agreed_period) || trim($Jewelry_recovery_agreed_period) === '' ||
        !isset($interest_rate) || trim($interest_rate) === '' ||
        !isset($group_type) || trim($group_type) === '' 
    ) {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Please provide all required fields";
        echo json_encode($output, JSON_NUMERIC_CHECK);
        exit();
    }

    // Check if receipt is already recovered
    $recoveryStmt = $conn->prepare("SELECT id FROM `pawnjewelry_recovery` WHERE `receipt_no` = ? AND `delete_at` = 0");
    $recoveryStmt->bind_param("s", $receipt_no);
    $recoveryStmt->execute();
    $recoveryCheck = $recoveryStmt->get_result();
    $recoveryStmt->close();

    if ($recoveryCheck->num_rows > 0) {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "This receipt number is already recovered.";
        echo json_encode($output, JSON_NUMERIC_CHECK);
        exit();
    }

    // Check if receipt number exists in another record
    $stmt = $conn->prepare("SELECT `id` FROM `pawnjewelry` WHERE `receipt_no` = ? AND delete_at = 0 AND `pawnjewelry_id` != ?");
    $stmt->bind_param("ss", $receipt_no, $edit_id);
    $stmt->execute();
    $pawnjewelryCheck = $stmt->get_result();
    $stmt->close();

    if ($pawnjewelryCheck->num_rows > 0) {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Receipt number already exists.";
        echo json_encode($output, JSON_NUMERIC_CHECK);
        exit();
    }

    $interestPaid = false;
    $stmtCheck = $conn->prepare("SELECT COUNT(*) as total FROM interest WHERE receipt_no = ?");
    $stmtCheck->bind_param("s", $receipt_no);
    $stmtCheck->execute();
    $resultCheck = $stmtCheck->get_result();
    $rowCheck = $resultCheck->fetch_assoc();
    $stmtCheck->close();

    if ($rowCheck && $rowCheck['total'] > 0) {
        $interestPaid = true;
    }

    if ($old_interest_rate !== $interest_rate) {
        if ($interestPaid) {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Cannot update interest rate. Interest payments already exist for this receipt.";
            echo json_encode($output, JSON_NUMERIC_CHECK);
            exit();
        }

        $interestPeriod = calculateInterestPeriod($pawnjewelry_date);
        $months = $interestPeriod['months'];
        $days = $interestPeriod['days'];

        if ($months < 3) {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Interest rate cannot be updated. Interest payment period must be 3 months or more.";
            echo json_encode($output, JSON_NUMERIC_CHECK);
            exit();
        }

        $new_interest_rate_value = floatval(str_replace('%', '', $interest_rate)) / 100;
        $monthly_interest = $original_amount_db * $new_interest_rate_value;
        $daily_interest = $monthly_interest / 30;
        $interest_payment_amount = round(($monthly_interest * $months) + ($daily_interest * $days), 2);
    } else {
        $stmt = $conn->prepare("SELECT interest_payment_amount FROM pawnjewelry WHERE pawnjewelry_id = ?");
        $stmt->bind_param("s", $edit_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        $row = $result->fetch_assoc();
        $interest_payment_amount = $row['interest_payment_amount'];
    }

    // Process Proof Files
    $proofPaths = [];
    $proofBase64Codes = [];
    foreach ($proof as $base64File) {
        if (!isset($base64File->data) || !is_string($base64File->data)) {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Invalid proof file format. Expected Base64 encoded string.";
            echo json_encode($output, JSON_NUMERIC_CHECK);
            exit();
        }

        $proofBase64Codes[] = $base64File->data;
        $fileData = $base64File->data;
        $fileName = uniqid("proof_");
        $filePath = "";

        if (preg_match('/^data:application\/pdf;base64,/', $fileData)) {
            $fileName .= ".pdf";
            $filePath = "../Uploads/pdfs/" . $fileName;
        } elseif (preg_match('/^data:image\/(\w+);base64,/', $fileData, $type)) {
            $fileName .= "." . strtolower($type[1]);
            $filePath = "../Uploads/images/" . $fileName;
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Unsupported proof file type.";
            echo json_encode($output, JSON_NUMERIC_CHECK);
            exit();
        }

        $fileData = preg_replace('/^data:.*;base64,/', '', $fileData);
        $decodedFile = base64_decode($fileData);
        if ($decodedFile === false) {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Proof Base64 decoding failed.";
            echo json_encode($output, JSON_NUMERIC_CHECK);
            exit();
        }

        $directory = dirname($filePath);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        if (file_put_contents($filePath, $decodedFile) === false) {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Failed to save proof file.";
            echo json_encode($output, JSON_NUMERIC_CHECK);
            exit();
        }

        $proofPaths[] = $filePath;
    }

    // Process Aadhaar Proof Files
    $aadharProofPaths = [];
    $aadharProofBase64Codes = [];
    foreach ($aadharproof as $base64File) {
        if (!isset($base64File->data) || !is_string($base64File->data)) {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Invalid Aadhaar proof file format. Expected Base64 encoded string.";
            echo json_encode($output, JSON_NUMERIC_CHECK);
            exit();
        }

        $aadharProofBase64Codes[] = $base64File->data;
        $fileData = $base64File->data;
        $fileName = uniqid("aadhar_");
        $filePath = "";

        if (preg_match('/^data:application\/pdf;base64,/', $fileData)) {
            $fileName .= ".pdf";
            $filePath = "../Uploads/aadhar/" . $fileName;
        } elseif (preg_match('/^data:image\/(\w+);base64,/', $fileData, $type)) {
            $fileName .= "." . strtolower($type[1]);
            $filePath = "../Uploads/aadhar/" . $fileName;
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Unsupported Aadhaar proof file type.";
            echo json_encode($output, JSON_NUMERIC_CHECK);
            exit();
        }

        $fileData = preg_replace('/^data:.*;base64,/', '', $fileData);
        $decodedFile = base64_decode($fileData);
        if ($decodedFile === false) {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Aadhaar proof Base64 decoding failed.";
            echo json_encode($output, JSON_NUMERIC_CHECK);
            exit();
        }

        $directory = dirname($filePath);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        if (file_put_contents($filePath, $decodedFile) === false) {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Failed to save Aadhaar proof file.";
            echo json_encode($output, JSON_NUMERIC_CHECK);
            exit();
        }

        $aadharProofPaths[] = $filePath;
    }

    $proofJson = json_encode($proofPaths, JSON_UNESCAPED_SLASHES);
    $proofBase64CodeJson = json_encode($proofBase64Codes, JSON_UNESCAPED_SLASHES);
    $aadharProofJson = json_encode($aadharProofPaths, JSON_UNESCAPED_SLASHES);
    $aadharProofBase64CodeJson = json_encode($aadharProofBase64Codes, JSON_UNESCAPED_SLASHES);
    $products_json = json_encode($jewel_product, JSON_UNESCAPED_UNICODE);

    // Update the record with bank pledge fields
    $stmt = $conn->prepare("UPDATE `pawnjewelry` SET  
        `customer_no`=?, `receipt_no`=?, `name`=?, `customer_details`=?, `place`=?, `mobile_number`=?, 
        `original_amount`=?, `jewel_product`=?, `Jewelry_recovery_agreed_period`=?, `interest_rate`=?, 
        `interest_payment_amount`=?, `group_type`=?, `proof`=?, `proof_base64code`=?, `aadharproof`=?, 
        `aadharprood_base64code`=?, `bank_pledge_date`=?, `bank_assessor_name`=?, `bank_name`=?, 
        `bank_pawn_value`=?, `bank_interest`=?, `bank_duration`=?, `bank_additional_charges`=?,`location`=?
        WHERE `pawnjewelry_id`=?");
    $stmt->bind_param(
        "ssssssssssdssssssssdsssss",
        $customer_no, $receipt_no, $name, $customer_details, $place, $mobile_number,
        $original_amount, $products_json, $Jewelry_recovery_agreed_period, $interest_rate, 
        $interest_payment_amount, $group_type, $proofJson, $proofBase64CodeJson, $aadharProofJson, 
        $aadharProofBase64CodeJson, $bank_pledge_date, $bank_assessor_name, $bank_name,
        $bank_pawn_value, $bank_interest, $bank_duration, $bank_additional_charges,$location, $edit_id
    );

    if ($stmt->execute()) {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Pawn jewelry updated successfully";
        $output["head"]["data"] = $obj;
    } else {
        error_log("Pawnjewelry update failed: " . $stmt->error);
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Failed to update. Please try again.";
    }
    $stmt->close();
}

// <<<<<<<<<<===================== Delete Pawn Jewelry =====================>>>>>>>>>>  
else if (isset($obj->delete_pawnjewelry_id)) {
    $delete_pawnjewelry_id = $conn->real_escape_string($obj->delete_pawnjewelry_id);

    if (!empty($delete_pawnjewelry_id)) {
        $stmt = $conn->prepare("UPDATE `pawnjewelry` SET `delete_at` = 1 WHERE `pawnjewelry_id` = ?");
        $stmt->bind_param("s", $delete_pawnjewelry_id);
        if ($stmt->execute()) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Pawn jewelry deleted successfully";
        } else {
            error_log("Pawnjewelry deletion failed: " . $stmt->error);
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Failed to delete. Please try again.";
        }
        $stmt->close();
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Please provide all required details.";
    }
} else {
    $output["head"]["code"] = 400;
    $output["head"]["msg"] = "Parameter mismatch";
}

echo json_encode($output);

?>


