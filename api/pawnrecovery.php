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
$obj = json_decode($json, true); // Use true to get associative array
$output = array();

date_default_timezone_set('Asia/Calcutta');
$timestamp = date('Y-m-d H:i:s');

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$domain = $_SERVER['HTTP_HOST'];

// <<<<<<<<<<===================== List / Search Recovery Records =====================>>>>>>>>>>
if (isset($obj['search_text'])) {
    if (!$conn || !($conn instanceof mysqli)) {
        $output["head"]["code"] = 500;
        $output["head"]["msg"] = "Database connection not established";
        echo json_encode($output, JSON_NUMERIC_CHECK);
        exit;
    }

    $search_text = $conn->real_escape_string(trim($obj['search_text']));
    $search_param = "%$search_text%";

    $sql = "SELECT 
                r.id AS id,
                p.customer_no AS customer_no,
                r.pawnjewelry_recovery_id AS pawnjewelry_recovery_id,
                r.receipt_no AS receipt_no,
                r.pawnjewelry_date AS pawnjewelry_date,
                r.name AS name,
                r.customer_details AS customer_details,
                r.place AS place,
                r.mobile_number AS mobile_number,
                r.original_amount AS original_amount,
                r.interest_rate AS interest_rate,
                r.jewel_product AS jewel_product,
                r.interest_income AS interest_income,
                r.refund_amount AS refund_amount,
                r.other_amount AS other_amount,
                r.pawnjewelry_recovery_date AS pawnjewelry_recovery_date,
                r.status AS status,
                r.interest_payment_periods AS interest_payment_periods,
                r.customer_pic AS customer_pic,
                r.customer_pic_base64code AS customer_pic_base64code
            FROM pawnjewelry_recovery AS r
            LEFT JOIN pawnjewelry AS p ON p.receipt_no = r.receipt_no
            WHERE r.delete_at = 0
            AND (r.receipt_no LIKE ? OR r.customer_details LIKE ? OR r.mobile_number LIKE ?)
            ORDER BY r.id DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $search_param, $search_param, $search_param);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Success";
        $output["body"]["pawn_recovery"] = [];

        $convertToFullUrl = function ($paths) use ($protocol, $domain) {
            $urls = [];
            if (!is_array($paths)) return [];

            foreach ($paths as $path) {
                if (strpos($path, 'data:image') === 0) {
                    $urls[] = $path;
                    continue;
                }
                $cleaned = str_replace([$protocol . $domain . '/', '../', './'], '', $path);
                $cleaned = ltrim($cleaned, '/');
                $urls[] = $protocol . $domain . '/mk_gold_finance_api/' . $cleaned;
            }
            return $urls;
        };

        while ($row = $result->fetch_assoc()) {
            // Decode stored JSON arrays
            $row['customer_pic'] = json_decode($row['customer_pic'] ?? '[]', true) ?? [];
            $row['customer_pic_base64code'] = json_decode($row['customer_pic_base64code'] ?? '[]', true) ?? [];

            // Convert paths to full URLs
            $customerUrls = $convertToFullUrl($row['customer_pic']);

            // Assign same URLs to both fields (like in sale API)
            $row['customer_pic'] = $customerUrls;
            $row['customer_pic_base64code'] = $customerUrls;

            // Ensure numeric fields are strings if needed
            $row['original_amount'] = (string)($row['original_amount'] ?? '0');
            $row['interest_income'] = (string)($row['interest_income'] ?? '0');
            $row['refund_amount'] = (string)($row['refund_amount'] ?? '0');
            $row['other_amount'] = (string)($row['other_amount'] ?? '0');

            $output["body"]["pawn_recovery"][] = $row;
        }
    } else {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "No records found";
        $output["body"]["pawn_recovery"] = [];
    }
    $stmt->close();
    echo json_encode($output, JSON_NUMERIC_CHECK);
    exit;
}

// <<<<<<<<<<===================== Create or Update Recovery Record =====================>>>>>>>>>>
elseif (isset($obj['receipt_no'])) {
    if (!$conn || !($conn instanceof mysqli)) {
        $output["head"]["code"] = 500;
        $output["head"]["msg"] = "Database connection not established";
        echo json_encode($output, JSON_NUMERIC_CHECK);
        exit;
    }

    $edit_id = $conn->real_escape_string(trim($obj['edit_pawnrecovery_id'] ?? ''));
    $receipt_no = $conn->real_escape_string(trim($obj['receipt_no']));
    $pawnjewelry_date = $conn->real_escape_string(trim($obj['pawnjewelry_date']));
    $name = $conn->real_escape_string(trim($obj['name']));
    
    $raw_address = $obj['customer_details'] ?? '';
    $cleaned_address = str_replace(['/', '\\n', '\n', "\n", "\r"], ' ', $raw_address);
    $cleaned_address = preg_replace('/\s+/', ' ', $cleaned_address);
    $cleaned_address = trim($cleaned_address);
    $customer_details = $conn->real_escape_string($cleaned_address);

    $place = $conn->real_escape_string(trim($obj['place'] ?? ''));
    $mobile_number = $conn->real_escape_string(trim($obj['mobile_number']));
    $original_amount = floatval($obj['original_amount'] ?? 0);
    $interest_rate = $conn->real_escape_string(trim($obj['interest_rate'] ?? '0%'));
    $jewel_product = $obj['jewel_product'] ?? [];
    $interest_income = floatval($obj['interest_income'] ?? 0);
    $refund_amount = floatval($obj['refund_amount'] ?? 0);
    $other_amount = floatval($obj['other_amount'] ?? 0);
   $pawnjewelry_recovery_date = isset($obj['pawnjewelry_recovery_date']) && !empty($obj['pawnjewelry_recovery_date']) ? $conn->real_escape_string(trim($obj['pawnjewelry_recovery_date'])) : date('Y-m-d');
    $interest_payment_periods = $conn->real_escape_string(trim($obj['interest_payment_periods'] ?? ''));

    $customer_pic_input = $obj['customer_pic'] ?? [];

    // Image processing arrays
    $customerPaths = [];
    $customerBase64 = [];

    $processBase64Images = function ($files, &$paths, &$base64Codes, $folder) use ($protocol, $domain) {
        if (empty($files)) return;

        // Normalize to array of ['data' => ..., 'isExisting' => true/false]
        if (is_string($files)) {
            $files = [['data' => $files, 'isExisting' => true]];
        } elseif (is_array($files) && !empty($files) && isset($files[0]) && is_string($files[0])) {
            $files = array_map(function ($f) { return ['data' => $f, 'isExisting' => true]; }, $files);
        }

        foreach ($files as $file) {
            $data = $file['data'] ?? '';
            $isExisting = !empty($file['isExisting']);

            if (empty($data)) continue;

            // Always store original (base64 or URL) in base64code field
            $base64Codes[] = $data;

            // If existing URL or not base64 → treat as existing file
            if ($isExisting || strpos($data, 'data:image') !== 0) {
                $relativePath = preg_replace('#^https?://[^/]+/#i', '', $data);
                $relativePath = preg_replace('#^mk_gold_finance_api/#i', '', $relativePath);
                $relativePath = trim($relativePath, '/');
                $paths[] = $relativePath;
                continue;
            }

            // New base64 image
            $ext = 'jpg';
            if (preg_match('/^data:image\/(\w+);base64,/', $data, $matches)) {
                $ext = strtolower($matches[1]);
            }

            $fileName = uniqid('img_') . '.' . $ext;
            $savePath = "../Uploads/recovery/customer/" . $fileName;  // Same folder structure as sale
            $dbPath = "Uploads/recovery/customer/" . $fileName;

            $pureData = preg_replace('#^data:image/\w+;base64,#', '', $data);
            $decoded = base64_decode($pureData);

            if ($decoded !== false) {
                $dir = dirname($savePath);
                if (!is_dir($dir)) {
                    mkdir($dir, 0777, true);
                }
                if (file_put_contents($savePath, $decoded) !== false) {
                    $paths[] = $dbPath;
                }
            }
        }
    };

    $processBase64Images($customer_pic_input, $customerPaths, $customerBase64, "customer");

    $customerJson = json_encode($customerPaths, JSON_UNESCAPED_SLASHES);
    $customerBase64Json = json_encode($customerBase64, JSON_UNESCAPED_SLASHES);
    $products_json = json_encode($jewel_product, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if (empty($edit_id)) {
        // === CREATE NEW RECOVERY ===
        $checkStmt = $conn->prepare("SELECT id FROM pawnjewelry_recovery WHERE receipt_no = ? AND delete_at = 0");
        $checkStmt->bind_param("s", $receipt_no);
        $checkStmt->execute();
        $recoveryCheck = $checkStmt->get_result();
        $checkStmt->close();

        if ($recoveryCheck->num_rows > 0) {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Receipt number already has a recovery record.";
            echo json_encode($output, JSON_NUMERIC_CHECK);
            exit;
        }

        $insertStmt = $conn->prepare("INSERT INTO `pawnjewelry_recovery` (
            `pawnjewelry_date`, `receipt_no`, `name`, `customer_details`, `place`, `mobile_number`,
            `original_amount`, `interest_rate`, `jewel_product`, `interest_income`, `refund_amount`,
            `other_amount`, `pawnjewelry_recovery_date`, `interest_payment_periods`,
            `customer_pic`, `customer_pic_base64code`,
            `create_at`, `delete_at`
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)");

$insertStmt->bind_param(
  "ssssssdsdsddsssss",
  $pawnjewelry_date,          // s
  $receipt_no,                // s
  $name,                      // s
  $customer_details,          // s
  $place,                     // s
  $mobile_number,             // s
  $original_amount,           // d
  $interest_rate,             // s
  $products_json,             // s
  $interest_income,           // d
  $refund_amount,             // d
  $other_amount,              // d
  $pawnjewelry_recovery_date, // s  ✅ FIXED
  $interest_payment_periods,  // s
  $customerJson,              // s
  $customerBase64Json,        // s
  $timestamp                  // s
);

        if ($insertStmt->execute()) {
            $id = $conn->insert_id;
            $uniqueRecoveryID = uniqueID('recovery', $id);

            $updateStmt = $conn->prepare("UPDATE `pawnjewelry_recovery` SET `pawnjewelry_recovery_id` = ? WHERE `id` = ?");
            $updateStmt->bind_param("si", $uniqueRecoveryID, $id);
            $updateStmt->execute();
            $updateStmt->close();

            // Update pawnjewelry status
            $statusStmt = $conn->prepare("UPDATE `pawnjewelry` SET `status` = 'நகை மீட்கபட்டது' WHERE `receipt_no` = ? AND `delete_at` = 0");
            $statusStmt->bind_param("s", $receipt_no);
            $statusStmt->execute();
            $statusStmt->close();

            addTransaction($conn, $name, $refund_amount, "varavu", $pawnjewelry_recovery_date);

            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Recovery record added successfully";
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Failed to add recovery record";
        }
        $insertStmt->close();
    } else {
        // === UPDATE EXISTING RECOVERY ===
        $updateStmt = $conn->prepare("UPDATE `pawnjewelry_recovery` SET 
            `pawnjewelry_date` = ?, `receipt_no` = ?, `name` = ?, `customer_details` = ?, 
            `place` = ?, `mobile_number` = ?, `original_amount` = ?, `interest_rate` = ?, 
            `jewel_product` = ?, `interest_income` = ?, `refund_amount` = ?, `other_amount` = ?,
            `pawnjewelry_recovery_date` = ?, `interest_payment_periods` = ?,
            `customer_pic` = ?, `customer_pic_base64code` = ?
            WHERE `pawnjewelry_recovery_id` = ?");

   $updateStmt->bind_param(
  "ssssssdsdsddsssss",
  $pawnjewelry_date,
  $receipt_no,
  $name,
  $customer_details,
  $place,
  $mobile_number,
  $original_amount,
  $interest_rate,
  $products_json,
  $interest_income,
  $refund_amount,
  $other_amount,
  $pawnjewelry_recovery_date, // ✅ NOW SAVES
  $interest_payment_periods,
  $customerJson,
  $customerBase64Json,
  $edit_id
);


        if ($updateStmt->execute()) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Recovery record updated successfully";
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Failed to update recovery record";
        }
        $updateStmt->close();
    }
}

// <<<<<<<<<<===================== Delete Recovery Record =====================>>>>>>>>>>
elseif (isset($obj['delete_pawn_recovery_id'])) {
    $delete_pawn_recovery_id = $conn->real_escape_string(trim($obj['delete_pawn_recovery_id']));

    $sql = "UPDATE `pawnjewelry_recovery` SET `delete_at` = 1 WHERE `pawnjewelry_recovery_id` = ? AND `delete_at` = 0";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $delete_pawn_recovery_id);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Recovery record deleted successfully";
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Failed to delete or record not found";
    }
    $stmt->close();
} else {
    $output["head"]["code"] = 400;
    $output["head"]["msg"] = "Invalid or missing parameters";
}

ob_end_clean();
echo json_encode($output, JSON_NUMERIC_CHECK);
$conn->close();
exit;