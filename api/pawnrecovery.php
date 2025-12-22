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

// <<<<<<<<<<===================== List Recovery Records =====================>>>>>>>>>>
if (isset($obj->search_text)) {
    $search_text = $conn->real_escape_string($obj->search_text);
    $sql = "SELECT r.id AS id,p.customer_no AS customer_no, r.pawnjewelry_recovery_id AS pawnjewelry_recovery_id, r.receipt_no AS receipt_no, r.pawnjewelry_date AS pawnjewelry_date, r.name AS name, r.customer_details AS customer_details, r.place AS place, r.mobile_number AS mobile_number, r.original_amount AS original_amount, r.interest_rate AS interest_rate, r.jewel_product AS jewel_product, r.interest_income AS interest_income, r.refund_amount AS refund_amount, r.other_amount AS other_amount, r.pawnjewelry_recovery_date AS pawnjewelry_recovery_date, r.status AS status, r.interest_payment_periods AS interest_payment_periods, r.proof_base64code AS proof_base64code FROM  pawnjewelry_recovery AS r
LEFT JOIN 
    pawnjewelry AS p ON p.receipt_no = r.receipt_no

WHERE 
    r.delete_at = 0
    AND (r.receipt_no LIKE '%$search_text%' OR p.customer_details LIKE '%$search_text%' OR r.mobile_number LIKE '%$search_text%' )

ORDER BY 
    r.id DESC;
;
";

    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Success";
            $output["body"]["pawn_recovery"][] = $row;
        }
    } else {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "No records found";
        $output["body"]["pawn_recovery"] = [];
    }
}

// <<<<<<<<<<===================== Create or Update Recovery Record =====================>>>>>>>>>>

elseif (isset($obj->receipt_no)) {
    // Sanitize inputs
    $edit_id = $conn->real_escape_string($obj->edit_pawnrecovery_id ?? '');
    $receipt_no = $conn->real_escape_string($obj->receipt_no);
    $pawnjewelry_date = $conn->real_escape_string($obj->pawnjewelry_date);
    $name = $conn->real_escape_string($obj->name);
     $raw_address = $obj->customer_details;
    $cleaned_address = str_replace(['/', '\\n', '\n', "\n", "\r"], ' ', $raw_address);
    $cleaned_address = preg_replace('/\s+/', ' ', $cleaned_address);
    $cleaned_address = trim($cleaned_address);
    $customer_details = $conn->real_escape_string($cleaned_address);
    $place = $conn->real_escape_string($obj->place);
    $mobile_number = $conn->real_escape_string($obj->mobile_number);
    $original_amount = floatval($obj->original_amount); // Ensure float
    $interest_rate = $conn->real_escape_string($obj->interest_rate ?? '0%'); // Default to '0%' if not provided
    $jewel_product = isset($obj->jewel_product) ? $obj->jewel_product : [];
    $interest_income = floatval($obj->interest_income); // Ensure float
    $refund_amount = floatval($obj->refund_amount); // Ensure float
    $other_amount = floatval($obj->other_amount);
    $pawnjewelry_recovery_date = $conn->real_escape_string($obj->pawnjewelry_recovery_date);
    $interest_payment_periods = $conn->real_escape_string($obj->interest_payment_periods);
    $type = "varavu";

    if ($edit_id === "") {
        
         $checkInterestStmt = $conn->prepare("SELECT `interest_payment_amount` FROM `pawnjewelry` WHERE `receipt_no` = ? AND `delete_at` = 0");
    $checkInterestStmt->bind_param("s", $receipt_no);
    $checkInterestStmt->execute();
    $resultInterest = $checkInterestStmt->get_result();
    $checkInterestStmt->close();

    if ($resultInterest->num_rows === 0) {
        $output["head"]["code"] = 404;
        $output["head"]["msg"] = "No pawn jewelry record found for this receipt number.";
        echo json_encode($output, JSON_NUMERIC_CHECK);
        exit();
    }

    $row = $resultInterest->fetch_assoc();
    // if (floatval($row["interest_payment_amount"]) > 0) {
    //     $output["head"]["code"] = 403;
    //     $output["head"]["msg"] = "Please pay full interest before closing the loan.";
    //     echo json_encode($output, JSON_NUMERIC_CHECK);
    //     exit();
    // }
        // Check if recovery record exists
        $checkStmt = $conn->prepare("SELECT `id` FROM `pawnjewelry_recovery` WHERE `receipt_no` = ? AND delete_at = 0");
        $checkStmt->bind_param("s", $receipt_no);
        $checkStmt->execute();
        $recoveryCheck = $checkStmt->get_result();
        $checkStmt->close();
 $products_json = json_encode($jewel_product,JSON_UNESCAPED_UNICODE);
        if ($recoveryCheck->num_rows == 0) {
            // Insert recovery record using prepared statement
            $insertStmt = $conn->prepare("INSERT INTO `pawnjewelry_recovery` (
                `pawnjewelry_date`, `receipt_no`,`name`, `customer_details`,`place`, `mobile_number`, 
                `original_amount`, `interest_rate`, `jewel_product`, `interest_income`, `refund_amount`, `pawnjewelry_recovery_date`, `interest_payment_periods`,
                `create_at`, `delete_at`,`other_amount`
            ) VALUES (?, ?, ?, ?, ?, ?,?,?, ?, ?, ?, ?,?, ?, 0,?)");
            $insertStmt->bind_param("ssssssdssdssssd",
                $pawnjewelry_date, $receipt_no,$name, $customer_details,$place, $mobile_number,
                $original_amount, $interest_rate, $products_json, $interest_income, $refund_amount, $pawnjewelry_recovery_date,$interest_payment_periods,
                $timestamp,$other_amount
            );

            if ($insertStmt->execute()) {
                $id = $conn->insert_id;
                $uniqueRecoveryID = uniqueID('recovery', $id);
               
              $updateStmt = $conn->prepare("UPDATE `pawnjewelry_recovery` SET `pawnjewelry_recovery_id` = ? WHERE `id` = ?");
                $updateStmt->bind_param("si", $uniqueRecoveryID, $id);
                $updateStmt->execute();
                $updateStmt->close();

                // Update pawnjewelry status to "நகை மீட்கபட்டது"
                $statusStmt = $conn->prepare("UPDATE `pawnjewelry` SET `status` = 'நகை மீட்கபட்டது' WHERE `receipt_no` = ? AND `delete_at` = 0");
                $statusStmt->bind_param("s", $receipt_no);
                if (!$statusStmt->execute()) {
                    error_log("Failed to update pawnjewelry status: " . $statusStmt->error);
                    $output["head"]["code"] = 400;
                    $output["head"]["msg"] = "Recovery record added, but failed to update pawn status.";
                    echo json_encode($output, JSON_NUMERIC_CHECK);
                    exit();
                }
                $statusStmt->close();

                addTransaction($conn, $name, $refund_amount, $type, $pawnjewelry_recovery_date);

                $output["head"]["code"] = 200;
                $output["head"]["msg"] = "Recovery record added successfully and pawn status updated";
            } else {
                error_log("Insert failed: " . $insertStmt->error);
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "Failed to add. Error: " . $insertStmt->error;
                echo json_encode($output, JSON_NUMERIC_CHECK);
                exit();
            }
            $insertStmt->close();
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Receipt number already Jewel Recovered exists.";
            echo json_encode($output, JSON_NUMERIC_CHECK);
            exit();
        }
    } else {
        // Update recovery record using prepared statement
        $updateStmt = $conn->prepare("UPDATE `pawnjewelry_recovery` SET 
            `pawnjewelry_date` = ?, 
            `receipt_no` = ?, 
            `name` = ?,
            `customer_details` = ?, 
            `place`=?,
            `mobile_number` = ?,
            `original_amount` = ?, 
            `interest_rate` = ?, 
            `jewel_product` = ?, 
            `interest_income` = ?, 
            `refund_amount` = ?, 
            `other_amount` = ?,
            `pawnjewelry_recovery_date` = ?,
            `interest_payment_periods` = ?
            WHERE `pawnjewelry_recovery_id` = ?");
        $updateStmt->bind_param("ssssssdssddss",
            $pawnjewelry_date, $receipt_no,$name, $customer_details,$place, $mobile_number,
            $original_amount, $interest_rate, $products_json, $interest_income, $refund_amount,$other_amount, $pawnjewelry_recovery_date,$interest_payment_periods,
            $edit_id
        );

        if ($updateStmt->execute()) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Recovery record updated successfully";
        } else {
            error_log("Update failed: " . $updateStmt->error);
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Failed to update. Error: " . $updateStmt->error;
            echo json_encode($output, JSON_NUMERIC_CHECK);
            exit();
        }
        $updateStmt->close();
    }
}

// <<<<<<<<<<===================== Delete Recovery Record =====================>>>>>>>>>>  
else if (isset($obj->delete_pawn_recovery_id)) {
    $delete_pawn_recovery_id = $obj->delete_pawn_recovery_id;

    if (!empty($delete_pawn_recovery_id)) {
        $deleteRecovery = "UPDATE `pawnjewelry_recovery` SET `delete_at` = 1 WHERE `pawnjewelry_recovery_id` = '$delete_pawn_recovery_id'";
        if ($conn->query($deleteRecovery)) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Recovery record deleted successfully";
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Failed to delete. Please try again.";
        }
    }
} else {
    $output["head"]["code"] = 400;
    $output["head"]["msg"] = "Parameter mismatch";
}

echo json_encode($output, JSON_NUMERIC_CHECK);
