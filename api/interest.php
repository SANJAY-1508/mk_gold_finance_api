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

// <<<<<<<<<<===================== List Interest Records =====================>>>>>>>>>>
if (isset($obj->search_text)) {
    $search_text = $conn->real_escape_string($obj->search_text);
    $sql = "SELECT i.*, p.original_amount AS pawn_original_amount, p.interest_rate AS pawn_interest_rate,p.customer_no AS customer_no 
            FROM `interest` i 
            LEFT JOIN `pawnjewelry` p ON i.receipt_no = p.receipt_no AND p.delete_at = 0
            WHERE i.delete_at = 0 
            AND (i.receipt_no LIKE '%$search_text%' OR i.mobile_number LIKE '%$search_text%' OR i.customer_details LIKE '%$search_text%') 
            ORDER BY i.id DESC";

    $result = $conn->query($sql);
    if ($result === false) {
        error_log("Interest query failed: " . $conn->error);
        $output["head"]["code"] = 500;
        $output["head"]["msg"] = "Database error";
        echo json_encode($output, JSON_NUMERIC_CHECK);
        exit();
    }

    $records = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Calculate interest_payment_periods for each record
            $original_amount = floatval($row['original_amount']); // Use interest table's original_amount
            $interest_rate = $row['interest_rate']; // Use interest table's interest_rate
            $interest_income = floatval($row['interest_income']);

            // Parse interest rate (e.g., "2%" -> 0.02)
            // Parse interest rate (e.g., "2%" -> 0.02)
$interest_rate_value = floatval(str_replace('%', '', $interest_rate)) / 100;

// Calculate monthly and daily interest
$monthly_interest = $original_amount * $interest_rate_value;
$daily_interest = $monthly_interest / 30;

// Calculate days paid
$total_days_paid = ($daily_interest > 0) ? round($interest_income / $daily_interest) : 0;

// Split into months and days
$months = floor($total_days_paid / 30);
$days = $total_days_paid % 30;

// Format interest_payment_periods
$period_string = '';
if ($months > 0) {
    $period_string .= "$months month" . ($months > 1 ? 's' : '');
}
if ($days > 0) {
    $period_string .= ($months > 0 ? ' ' : '') . "$days day" . ($days > 1 ? 's' : '');
}
$period_string = $period_string ?: '0 days';

// Store interest_payment_periods for this record
$row['interest_payment_periods'] = $period_string;


            // Add to records array
            $records[] = $row;
        }

        // Format output
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Success";
        $output["body"]["interest"] = array_map(function ($record) {
            // Remove temporary fields
            unset($record['pawn_original_amount']);
            unset($record['pawn_interest_rate']);
            return $record;
        }, $records);
    } else {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "No records found";
        $output["body"]["interest"] = [];
    }
}
// <<<<<<<<<<===================== Create Interest Record =====================>>>>>>>>>>
elseif (isset($obj->receipt_no) && empty($obj->edit_interest_id)) {
    $receipt_no = $conn->real_escape_string($obj->receipt_no);
    $interest_receive_date = $conn->real_escape_string($obj->interest_receive_date);
    $name = $conn->real_escape_string($obj->name);
     $raw_address = $obj->customer_details;
    $cleaned_address = str_replace(['/', '\\n', '\n', "\n", "\r"], ' ', $raw_address);
    $cleaned_address = preg_replace('/\s+/', ' ', $cleaned_address);
    $cleaned_address = trim($cleaned_address);
    $customer_details = $conn->real_escape_string($cleaned_address);
    $place = $conn->real_escape_string($obj->place);
    $mobile_number = $conn->real_escape_string($obj->mobile_number);
   $original_amount = $conn->real_escape_string($obj->original_amount);
    $interest_rate = isset($obj->interest_rate) ? $conn->real_escape_string($obj->interest_rate) : '0';
    $jewel_product = isset($obj->jewel_product) ? $obj->jewel_product : [];
    $interest_income = isset($obj->interest_income) && is_numeric($obj->interest_income) ? floatval($obj->interest_income) : 0.0;
    $outstanding_period = $conn->real_escape_string($obj->outstanding_period);
    $outstanding_amount = isset($obj->outstanding_amount) && is_numeric($obj->outstanding_amount) ? floatval($obj->outstanding_amount) : 0.0;
    $topup_amount = isset($obj->topup_amount) ? (int)$obj->topup_amount : 0;
    $deduction_amount = isset($obj->deduction_amount) ? (int)$obj->deduction_amount : 0;
    $type = "varavu";
    $timestamp = date('Y-m-d H:i:s');
    
//   if ($outstanding_amount <= $interest_income) {
//     $output["head"]["code"] = 400;
//     $output["head"]["msg"] = "நிலுவைத் தொகை வட்டி வருமானத்தை விடக் குறைவாக இருக்கக்கூடாது.";
//     echo json_encode($output);
//     exit; // Stop script immediately after sending error
// }

    // Check if receipt is already recovered
    $recoveryStmt = $conn->prepare("SELECT id FROM `pawnjewelry_recovery` WHERE `receipt_no` = ? AND `delete_at` = 0");
    $recoveryStmt->bind_param("s", $receipt_no);
    $recoveryStmt->execute();
    $recoveryCheck = $recoveryStmt->get_result();
    $recoveryStmt->close();

    if ($recoveryCheck->num_rows > 0) {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "This receipt number is already recovered.";
         echo json_encode($output);
    exit; // Stop script immediately after sending error
    }
    

    // Required field validation
    if (empty($receipt_no) || empty($interest_receive_date) || empty($customer_details)  || empty($original_amount)) {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Please provide all required fields.";
         echo json_encode($output);
    exit; // Stop script immediately after sending error
       
    }

 

    // Fetch pawnjewelry record
    $stmt = $conn->prepare("SELECT original_amount, interest_rate, interest_payment_period, interest_payment_amount 
                            FROM pawnjewelry WHERE receipt_no = ? AND delete_at = 0");
    $stmt->bind_param("s", $receipt_no);
    $stmt->execute();
    $pawnResult = $stmt->get_result();
    $stmt->close();

    if ($pawnResult->num_rows == 0) {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "கொடுக்கப்பட்ட ரசீது எண்ணுக்கு அடகு நகைகள் எதுவும் கிடைக்கவில்லை.";
       
    }

    $pawnData = $pawnResult->fetch_assoc();
    $pawn_original_amount = $pawnData['original_amount'];
$interest_rate = $pawnData['interest_rate'];
$current_interest_payment_period = $pawnData['interest_payment_period'];
$current_interest_payment_amount = $pawnData['interest_payment_amount'];

$interest_rate_value = floatval(str_replace('%', '', $interest_rate)) / 100;
$monthly_interest = $pawn_original_amount * $interest_rate_value;

// Assume 30 days per month for pawn finance logic
$daily_interest = $monthly_interest / 30;

$days_paid_total = $daily_interest > 0 ? floor($interest_income / $daily_interest) : 0;
$months_paid = floor($days_paid_total / 30);
$days_only = $days_paid_total % 30;

// Format for display/logging (optional)
$paid_period_display = '';
if ($months_paid > 0) {
    $paid_period_display .= "{$months_paid} மாதம்" . ($months_paid > 1 ? 'கள்' : '');
}
if ($days_only > 0) {
    $paid_period_display .= ($months_paid > 0 ? ' ' : '') . "{$days_only} நாள்" . ($days_only > 1 ? 'கள்' : '');
}
$paid_period_display = $paid_period_display ?: '0 நாள்';


// Update remaining period and outstanding
$new_interest_payment_period = max(0, $current_interest_payment_period - $days_only);
$new_interest_payment_amount = max(0, floatval($current_interest_payment_amount) - floatval($interest_income));

// Guard clause for overpayment
if ($new_interest_payment_period <= 0 && $new_interest_payment_amount <= 0) {
    $output["head"]["code"] = 400;
    $output["head"]["msg"] = "Interest payment exceeds outstanding amount.";
}

 $products_json = json_encode($jewel_product,JSON_UNESCAPED_UNICODE);
    // Insert interest record
    $stmt = $conn->prepare("INSERT INTO `interest` (
        `interest_receive_date`, `receipt_no`, `name`,`customer_details`,`place`, `mobile_number`, 
        `original_amount`, `interest_rate`, `jewel_product`,`interest_income`, `outstanding_period`, `outstanding_amount`, `topup_amount`,`deduction_amount`, `create_at`, `delete_at`
    ) VALUES (?, ?, ?, ?,?, ?, ?,?,?, ?, ?, ?, ?,?, ?, 0)");
    $stmt->bind_param("ssssssdsssssiss",
        $interest_receive_date, $receipt_no, $name,$customer_details, $place,$mobile_number,
        $original_amount, $interest_rate, $products_json,
        $interest_income, $outstanding_period, $outstanding_amount, $topup_amount,$deduction_amount, $timestamp
    );

    if ($stmt->execute()) {
        $id = $conn->insert_id;
        $uniqueInterestID = uniqueID('interest', $id);
        $stmt = $conn->prepare("UPDATE `interest` SET `interest_id` = ? WHERE `id` = ?");
        $stmt->bind_param("si", $uniqueInterestID, $id);
        $stmt->execute();
        $stmt->close();

        // Top-up logic (update original amount if top-up)
        if ($topup_amount > 0) {
            $pawn_original_amount += $topup_amount;
            $stmt = $conn->prepare("UPDATE pawnjewelry SET original_amount = ? WHERE receipt_no = ? AND delete_at = 0");
            $stmt->bind_param("ds", $pawn_original_amount, $receipt_no);
            $stmt->execute();
            $stmt->close();
            
             // Insert into topup table
    $topupInsert = $conn->prepare("INSERT INTO topup (receipt_no, topup_amount, topup_date, created_by) VALUES (?, ?, ?, ?)");
    $created_by = "admin"; // or get from session if available
    $topupInsert->bind_param("sdss", $receipt_no, $topup_amount, $interest_receive_date, $created_by);
    $topupInsert->execute();
    $topupInsert->close();
        }
        
        if($deduction_amount > 0){
             $pawn_original_amount -= $deduction_amount;
            $stmt = $conn->prepare("UPDATE pawnjewelry SET original_amount = ? WHERE receipt_no = ? AND delete_at = 0");
            $stmt->bind_param("ds", $pawn_original_amount, $receipt_no);
            $stmt->execute();
            $stmt->close();
            
             // Insert into topup table
            $deductionInsert = $conn->prepare("INSERT INTO deduction (receipt_no, deduction_amount, deduction_date, created_by) VALUES (?, ?, ?, ?)");
            $created_by = "admin"; // or get from session if available
            $deductionInsert->bind_param("sdss", $receipt_no, $deduction_amount, $interest_receive_date, $created_by);
            $deductionInsert->execute();
            $deductionInsert->close();
        }

        // Always update interest payment status
        $stmt = $conn->prepare("UPDATE pawnjewelry SET 
            interest_payment_period = ?, 
            interest_payment_amount = ?
            WHERE receipt_no = ? AND delete_at = 0");
        $stmt->bind_param("ids", $new_interest_payment_period, $new_interest_payment_amount, $receipt_no);
        if (!$stmt->execute()) {
            error_log("Pawnjewelry update failed after interest creation: " . $stmt->error);
        }
        $stmt->close();


        // Add transactions
        addTransaction($conn, $name, $interest_income, $type, $interest_receive_date);
        if ($topup_amount > 0) {
            addTransaction($conn, $name, $topup_amount, "patru", $interest_receive_date);
        }
        if ($deduction_amount > 0) {
            addTransaction($conn, $name, $deduction_amount, "varavu", $interest_receive_date);
        }

        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Interest record added successfully";
    } else {
        error_log("Interest creation failed: " . $stmt->error);
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Failed to add. Please try again.";
    }

  
}



// <<<<<<<<<<===================== Update Interest Record =====================>>>>>>>>>>
elseif (isset($obj->edit_interest_id) && !empty($obj->edit_interest_id)) {
    $edit_id = $conn->real_escape_string($obj->edit_interest_id);
    $receipt_no = $conn->real_escape_string($obj->receipt_no);
    $interest_receive_date = $conn->real_escape_string($obj->interest_receive_date);
    $name = $conn->real_escape_string($obj->name);
    $raw_address = $obj->customer_details;
    $cleaned_address = str_replace(['/', '\\n', '\n', "\n", "\r"], ' ', $raw_address);
    $cleaned_address = preg_replace('/\s+/', ' ', $cleaned_address);
    $cleaned_address = trim($cleaned_address);
    $customer_details = $conn->real_escape_string($cleaned_address);
    $place = $conn->real_escape_string($obj->place);
    $mobile_number = $conn->real_escape_string($obj->mobile_number);
    $original_amount = $conn->real_escape_string($obj->original_amount);
    $interest_rate = $conn->real_escape_string($obj->interest_rate);
    $jewel_product = isset($obj->jewel_product) ? $obj->jewel_product : [];
    $interest_income = $conn->real_escape_string($obj->interest_income);
    $topup_amount = isset($obj->topup_amount) ? (int)$obj->topup_amount : 0; 
    $deduction_amount = isset($obj->deduction_amount) ? (int)$obj->deduction_amount : 0;



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
    // Get previous interest_income to adjust pawnjewelry
    $stmt = $conn->prepare("SELECT interest_income, receipt_no FROM interest WHERE interest_id = ? AND delete_at = 0");
    $stmt->bind_param("s", $edit_id);
    $stmt->execute();
    $prevResult = $stmt->get_result();
    $stmt->close();

    if ($prevResult->num_rows == 0) {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Interest record not found.";
        echo json_encode($output, JSON_NUMERIC_CHECK);
        exit();
    }

    $prevData = $prevResult->fetch_assoc();
    $prev_interest_income = $prevData['interest_income'];
    $receipt_no = $prevData['receipt_no'];
 $products_json = json_encode($jewel_product,JSON_UNESCAPED_UNICODE);
    // Update interest record
    $stmt = $conn->prepare("UPDATE `interest` SET 
        `receipt_no`=?, 
        `name` =?,
        `customer_details`=?, 
        `place` =?,
        `mobile_number`=?, 
        `original_amount`=?,
        `interest_rate` =?,
        `jewel_product`=?, 
        `interest_income`=?, 
        `interest_receive_date`=?, 
        `topup_amount`=?
        WHERE `interest_id`=?");
    $stmt->bind_param("sssssdssssis",
        $receipt_no,$name, $customer_details,$place, $mobile_number, $original_amount,$interest_rate,
        $products_json, $interest_income, 
        $interest_receive_date, $topup_amount, $edit_id
    );

    if ($stmt->execute()) {
        // Update pawnjewelry
        $pawnStmt = $conn->prepare("SELECT original_amount, interest_rate, interest_payment_period, interest_payment_amount 
                                   FROM pawnjewelry WHERE receipt_no = ? AND delete_at = 0");
        $pawnStmt->bind_param("s", $receipt_no);
        $pawnStmt->execute();
        $pawnResult = $pawnStmt->get_result();
        $pawnStmt->close();

        if ($pawnResult->num_rows > 0) {
            $pawnData = $pawnResult->fetch_assoc();
            $pawn_original_amount = $pawnData['original_amount'];
            $interest_rate = $pawnData['interest_rate'];
            $current_interest_payment_period = $pawnData['interest_payment_period'];
            $current_interest_payment_amount = $pawnData['interest_payment_amount'];

            $interest_rate_value = floatval(str_replace('%', '', $interest_rate)) / 100;
            $monthly_interest = $pawn_original_amount * $interest_rate_value;
            $daily_interest = $monthly_interest / 30;
            $prev_days_paid = round($prev_interest_income / $daily_interest);
            $new_days_paid = round($interest_income / $daily_interest);

            // Adjust period and amount
            $new_interest_payment_period = $current_interest_payment_period + $prev_days_paid - $new_days_paid;
            $new_interest_payment_amount = $current_interest_payment_amount + $prev_interest_income - $interest_income;

            if ($new_interest_payment_period >= 0 && $new_interest_payment_amount >= 0) {
                $updatePawn = $conn->prepare("UPDATE pawnjewelry SET 
                    interest_payment_period = ?, 
                    interest_payment_amount = ? 
                    WHERE receipt_no = ? AND delete_at = 0");
                $updatePawn->bind_param("ids", $new_interest_payment_period, $new_interest_payment_amount, $receipt_no);
                if (!$updatePawn->execute()) {
                    error_log("Pawnjewelry update failed after interest update: " . $updatePawn->error);
                }
                $updatePawn->close();
            } else {
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "Interest update would result in negative period or amount.";
                echo json_encode($output, JSON_NUMERIC_CHECK);
                exit();
            }
        }

        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Interest record updated successfully";
    } else {
        error_log("Interest update failed: " . $stmt->error);
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Failed to update. Please try again.";
    }
    $stmt->close();
}
// <<<<<<<<<<===================== Delete Interest Record =====================>>>>>>>>>>  
else if (isset($obj->delete_interest_id)) {
    $delete_interest_id = $conn->real_escape_string($obj->delete_interest_id);

    if (!empty($delete_interest_id)) {
        // Get receipt_no and interest_income before deletion
        $stmt = $conn->prepare("SELECT receipt_no, interest_income FROM interest WHERE interest_id = ? AND delete_at = 0");
        $stmt->bind_param("s", $delete_interest_id);
        $stmt->execute();
        $interestResult = $stmt->get_result();
        $stmt->close();

        if ($interestResult->num_rows == 0) {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Interest record not found.";
            echo json_encode($output, JSON_NUMERIC_CHECK);
            exit();
        }

        $interestData = $interestResult->fetch_assoc();
        $receipt_no = $interestData['receipt_no'];
        $deleted_interest_income = $interestData['interest_income'];

        // Delete interest record
        $stmt = $conn->prepare("UPDATE `interest` SET `delete_at` = 1 WHERE `interest_id` = ?");
        $stmt->bind_param("s", $delete_interest_id);
        if ($stmt->execute()) {
            // Update pawnjewelry
            $pawnStmt = $conn->prepare("SELECT original_amount, interest_rate, interest_payment_period, interest_payment_amount, pawnjewelry_date 
                                       FROM pawnjewelry WHERE receipt_no = ? AND delete_at = 0");
            $pawnStmt->bind_param("s", $receipt_no);
            $pawnStmt->execute();
            $pawnResult = $pawnStmt->get_result();
            $pawnStmt->close();

            if ($pawnResult->num_rows > 0) {
                $pawnData = $pawnResult->fetch_assoc();
                $pawn_original_amount = $pawnData['original_amount'];
                $interest_rate = $pawnData['interest_rate'];
                $current_interest_payment_period = $pawnData['interest_payment_period'];
                $current_interest_payment_amount = $pawnData['interest_payment_amount'];

                $interest_rate_value = floatval(str_replace('%', '', $interest_rate)) / 100;
                $monthly_interest = $pawn_original_amount * $interest_rate_value;
                $daily_interest = $monthly_interest / 30;
                $days_paid = round($deleted_interest_income / $daily_interest);

                // Adjust period and amount
                $new_interest_payment_period = $current_interest_payment_period + $days_paid;
                $new_interest_payment_amount = $current_interest_payment_amount + $deleted_interest_income;

                $updatePawn = $conn->prepare("UPDATE pawnjewelry SET 
                    interest_payment_period = ?, 
                    interest_payment_amount = ? 
                    WHERE receipt_no = ? AND delete_at = 0");
                $updatePawn->bind_param("ids", $new_interest_payment_period, $new_interest_payment_amount, $receipt_no);
                if (!$updatePawn->execute()) {
                    error_log("Pawnjewelry update failed after interest deletion: " . $updatePawn->error);
                }
                $updatePawn->close();
            }

            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Interest record deleted successfully";
        } else {
            error_log("Interest deletion failed: " . $stmt->error);
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

echo json_encode($output, JSON_NUMERIC_CHECK);
?>