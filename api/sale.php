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
$obj = json_decode($json, true);
$output = array();
date_default_timezone_set('Asia/Calcutta');
$timestamp = date('Y-m-d H:i:s');
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$domain = $_SERVER['HTTP_HOST'];
$base_url = $protocol . $domain;
// <<<<<<<<<<===================== List / Search Sales =====================>>>>>>>>>>
// <<<<<<<<<<===================== List / Search Sales =====================>>>>>>>>>>
if (isset($obj['search_text'])) {
    if (!$conn || !($conn instanceof mysqli)) {
        $output["head"]["code"] = 500;
        $output["head"]["msg"] = "Database connection not established";
        echo json_encode($output, JSON_NUMERIC_CHECK);
        exit;
    }
    // Clean search text and wrap in wildcards
    $search_text = $conn->real_escape_string(trim($obj['search_text']));
    $search_param = "%$search_text%";
    // CORRECTED SQL:
    // 1. Removed the stray single quote before total_jewel_weight
    // 2. Added `tharam` to the SELECT list so it is returned to the frontend
    $sql = "SELECT `id`, `sale_id`, `sale_date`, `name`, `place`, `mobile_number`, `bank_name`,
                   `bank_loan_amount`, `customer_receive_amount`, `total_jewel_weight`,
                   `total_loan_amount`, `tharam`, `staff_name`,
                   `jewel_pic`, `jewel_pic_base64code`, `customer_pic`, `customer_pic_base64code`,
                   `id_pic`, `id_pic_base64code`, `delete_at`, `create_at`
            FROM `sale`
            WHERE `delete_at` = 0
            AND (`name` LIKE ? OR `mobile_number` LIKE ? OR `sale_id` LIKE ? OR `staff_name` LIKE ?)
            ORDER BY `id` DESC";
    $stmt = $conn->prepare($sql);
   
    // Bind the 4 search parameters for the 4 '?' in the query
    $stmt->bind_param("ssss", $search_param, $search_param, $search_param, $search_param);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Success";
        $output["body"]["sales"] = [];
 $convertToFullUrl = function($paths) use ($protocol, $domain) {
    $urls = [];
    if (!is_array($paths)) return [];
   
    foreach ($paths as $path) {
        // Skip processing if it's a raw Base64 data string
        if (strpos($path, 'data:image') === 0) {
            $urls[] = $path;
            continue;
        }
        // Clean the path to prevent double "http://" or double project folders
        $cleaned = str_replace([$protocol . $domain . '/', '../', './'], '', $path);
        $cleaned = ltrim($cleaned, '/');
       
        // Construct the exact URL structure requested (without project folder)
        $urls[] = $protocol . $domain . '/' . $cleaned;
    }
    return $urls;
};
   while ($row = $result->fetch_assoc()) {
    // 1. Decode the stored file paths
    $row['jewel_pic'] = json_decode($row['jewel_pic'] ?? '[]', true) ?? [];
    $row['customer_pic'] = json_decode($row['customer_pic'] ?? '[]', true) ?? [];
    $row['id_pic'] = json_decode($row['id_pic'] ?? '[]', true) ?? [];
    // 2. Convert file paths to full URLs
    $jewelUrls = $convertToFullUrl($row['jewel_pic']);
    $customerUrls = $convertToFullUrl($row['customer_pic']);
    $idUrls = $convertToFullUrl($row['id_pic']);
    // 3. Assign the generated URLs to BOTH fields
    // This ensures your base64code fields also return the server URL
    $row['jewel_pic'] = $jewelUrls;
    $row['jewel_pic_base64code'] = $jewelUrls;
   
    $row['customer_pic'] = $customerUrls;
    $row['customer_pic_base64code'] = $customerUrls;
   
    $row['id_pic'] = $idUrls;
    $row['id_pic_base64code'] = $idUrls;
    // 4. Maintain numeric/string casting
    $row['bank_loan_amount'] = (string)($row['bank_loan_amount'] ?? '0');
    $row['customer_receive_amount'] = (string)($row['customer_receive_amount'] ?? '0');
    $row['total_jewel_weight'] = (string)($row['total_jewel_weight'] ?? '0');
    $row['total_loan_amount'] = (string)($row['total_loan_amount'] ?? '0');
    $row['tharam'] = (string)($row['tharam'] ?? '');
    $output["body"]["sales"][] = $row;
}
     
    } else {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "No sales found";
        $output["body"]["sales"] = [];
    }
    $stmt->close();
   
    // Output final JSON and exit to prevent following blocks from running
    echo json_encode($output, JSON_NUMERIC_CHECK);
    exit;
}
// <<<<<<<<<<===================== Create or Update Sale =====================>>>>>>>>>>
elseif (isset($obj['edit_sale_id'])) {
    // UPDATE SALE
    if (!$conn || !($conn instanceof mysqli)) {
        $output["head"]["code"] = 500;
        $output["head"]["msg"] = "Database connection not established";
        echo json_encode($output, JSON_NUMERIC_CHECK);
        exit;
    }

    $edit_sale_id = $conn->real_escape_string(trim($obj['edit_sale_id']));
    $sale_date = $conn->real_escape_string(trim($obj['sale_date'] ?? $timestamp));
    $name = $conn->real_escape_string(trim($obj['name'] ?? ''));
    $place = $conn->real_escape_string(trim($obj['place'] ?? ''));
    $mobile_number = $conn->real_escape_string(trim($obj['mobile_number'] ?? ''));
    $bank_name = $conn->real_escape_string(trim($obj['bank_name'] ?? ''));
    $bank_loan_amount = $conn->real_escape_string(trim($obj['bank_loan_amount'] ?? '0'));
    $customer_receive_amount = $conn->real_escape_string(trim($obj['customer_receive_amount'] ?? '0'));
       $total_jewel_weight = $conn->real_escape_string(trim($obj['total_jewel_weight'] ?? '0'));
          $total_loan_amount = $conn->real_escape_string(trim($obj['total_loan_amount'] ?? '0'));
             $tharam = $conn->real_escape_string(trim($obj['tharam'] ?? '0'));
    $staff_name = $conn->real_escape_string(trim($obj['staff_name'] ?? ''));

    $jewel_pic = $obj['jewel_pic'] ?? [];
    $customer_pic = $obj['customer_pic'] ?? [];
    $id_pic = $obj['id_pic'] ?? [];

    // Arrays to store file paths and base64 codes
    $jewelPaths = []; $jewelBase64 = [];
    $customerPaths = []; $customerBase64 = [];
    $idPaths = []; $idBase64 = [];

  $processBase64Images = function($files, &$paths, &$base64Codes, $folder) {
    if (empty($files)) return;

    // Normalize input: accept string, array of strings, or array of ['data'=>..., 'isExisting'=>...]
    if (is_string($files)) {
        $files = [['data' => $files, 'isExisting' => true]];
    } elseif (is_array($files) && !empty($files) && !isset($files[0]['data'])) {
        // If it's a simple array of strings (unlikely in update, but safe)
        $files = array_map(function($f) { return ['data' => $f, 'isExisting' => true]; }, $files);
    }

    foreach ($files as $file) {
        $data = $file['data'] ?? ($file ?? '');
        $isExisting = !empty($file['isExisting']);

        if (empty($data)) continue;

        // Always store the original value in base64code field (as per your logic)
        $base64Codes[] = $data;

        // If it's an existing file (URL), just extract the relative path and skip saving
        if ($isExisting || strpos($data, 'data:image') !== 0) {
            // Convert full URL back to relative DB path
            $relativePath = str_replace(
                [ 'http://' . $_SERVER['HTTP_HOST'] . '/', 'https://' . $_SERVER['HTTP_HOST'] . '/' ],
                '',
                $data
            );
            // Remove project folder if duplicated
            $relativePath = preg_replace('#^mk_gold_finance_api/#', '', $relativePath);
            $paths[] = trim($relativePath, '/');
            continue;
        }

        // Only reach here if it's NEW base64 data
        $ext = 'jpg';
        if (preg_match('/^data:image\/(\w+);base64,/', $data, $matches)) {
            $ext = strtolower($matches[1]);
        }

        $fileName = uniqid('img_') . '.' . $ext;
        $savePath = "../Uploads/sales/{$folder}/" . $fileName;  // Physical path
        $dbPath = "Uploads/sales/{$folder}/" . $fileName;       // Path to store in DB

        // Clean base64
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

    $processBase64Images($jewel_pic, $jewelPaths, $jewelBase64, "jewel");
    $processBase64Images($customer_pic, $customerPaths, $customerBase64, "customer");
    $processBase64Images($id_pic, $idPaths, $idBase64, "id");

    $jewelJson = json_encode($jewelPaths, JSON_UNESCAPED_SLASHES);
    $jewelBase64Json = json_encode($jewelBase64, JSON_UNESCAPED_SLASHES);
    $customerJson = json_encode($customerPaths, JSON_UNESCAPED_SLASHES);
    $customerBase64Json = json_encode($customerBase64, JSON_UNESCAPED_SLASHES);
    $idJson = json_encode($idPaths, JSON_UNESCAPED_SLASHES);
    $idBase64Json = json_encode($idBase64, JSON_UNESCAPED_SLASHES);

   $sql = "UPDATE `sale` SET 
           `sale_date`=?, `name`=?, `place`=?, `mobile_number`=?, `bank_name`=?, 
            `bank_loan_amount`=?, `customer_receive_amount`=?, `total_jewel_weight`=?, `total_loan_amount`=?, `tharam`=?, `staff_name`=?, 
            `jewel_pic`=?, `jewel_pic_base64code`=?, 
            `customer_pic`=?, `customer_pic_base64code`=?, 
            `id_pic`=?, `id_pic_base64code`=?
            WHERE `sale_id`=? AND `delete_at`=0";

    $stmt = $conn->prepare($sql);

    // FIXED: Changed from 14 "s" to 17 "s" to match the 17 variables below
    $stmt->bind_param("ssssssssssssssssss",
    $sale_date,
        $name, 
        $place, 
        $mobile_number, 
        $bank_name,
        $bank_loan_amount, 
        $customer_receive_amount, 
        $total_jewel_weight, 
        $total_loan_amount, 
        $tharam, 
        $staff_name,
        $jewelJson, 
        $jewelBase64Json,
        $customerJson, 
        $customerBase64Json,
        $idJson, 
        $idBase64Json,
        $edit_sale_id
    );

    if ($stmt->execute()) {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Sale updated successfully";
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Failed to update sale";
    }
    $stmt->close();
}

elseif (isset($obj['name']) && isset($obj['mobile_number'])) {
    // CREATE NEW SALE
    if (!$conn || !($conn instanceof mysqli)) {
        $output["head"]["code"] = 500;
        $output["head"]["msg"] = "Database connection not established";
        echo json_encode($output, JSON_NUMERIC_CHECK);
        exit;
    }
$sale_date = $conn->real_escape_string(trim($obj['sale_date'] ?? date('Y-m-d')));
    $name = $conn->real_escape_string(trim($obj['name']));
    $place = $conn->real_escape_string(trim($obj['place'] ?? ''));
    $mobile_number = $conn->real_escape_string(trim($obj['mobile_number']));
    $bank_name = $conn->real_escape_string(trim($obj['bank_name'] ?? ''));
    $bank_loan_amount = $conn->real_escape_string(trim($obj['bank_loan_amount'] ?? '0'));
    $customer_receive_amount = $conn->real_escape_string(trim($obj['customer_receive_amount'] ?? '0'));
         $total_jewel_weight = $conn->real_escape_string(trim($obj['total_jewel_weight'] ?? '0'));
             $total_loan_amount = $conn->real_escape_string(trim($obj['total_loan_amount'] ?? '0'));
                 $tharam = $conn->real_escape_string(trim($obj['tharam'] ?? '0'));
    $staff_name = $conn->real_escape_string(trim($obj['staff_name'] ?? ''));

    $jewel_pic = $obj['jewel_pic'] ?? [];
    $customer_pic = $obj['customer_pic'] ?? [];
    $id_pic = $obj['id_pic'] ?? [];

    $jewelPaths = []; $jewelBase64 = [];
    $customerPaths = []; $customerBase64 = [];
    $idPaths = []; $idBase64 = [];

  $processBase64Images = function($files, &$paths, &$base64Codes, $folder) {
    if (empty($files)) return;

    // Normalize input to always be array of ['data' => ..., 'isExisting' => ...]
    if (is_string($files)) {
        $files = [['data' => $files, 'isExisting' => true]];
    } elseif (is_array($files) && !empty($files) && isset($files[0]) && !is_array($files[0])) {
        $files = array_map(function($f) { return ['data' => $f, 'isExisting' => true]; }, $files);
    }

    foreach ($files as $file) {
        $data = $file['data'] ?? '';
        $isExisting = !empty($file['isExisting']);

        if (empty($data)) continue;

        // Always keep original for base64code field
        $base64Codes[] = $data;

        // Handle existing files (URLs)
        if ($isExisting || strpos($data, 'data:image') !== 0) {
            // Remove protocol and domain (http://localhost or https://yourdomain.com)
            $relativePath = preg_replace('#^https?://[^/]+/#i', '', $data);
            
            // Remove project folder if duplicated at start
            $relativePath = preg_replace('#^mk_gold_finance_api/#i', '', $relativePath);
            
            // Clean leading/trailing slashes
            $relativePath = trim($relativePath, '/');
            
            // Final safety: ensure it starts with Uploads/sales/...
            if (stripos($relativePath, 'Uploads/sales/') !== 0) {
                // Extract just the relevant part as fallback
                preg_match('#Uploads/sales/(jewel|customer|id)/[^/]+$#i', $data, $matches);
                $relativePath = !empty($matches[0]) ? ltrim($matches[0], '/') : $relativePath;
            }

            $paths[] = $relativePath;
            continue;
        }

        // New base64 image - save as before
        $ext = 'jpg';
        if (preg_match('/^data:image\/(\w+);base64,/', $data, $matches)) {
            $ext = strtolower($matches[1]);
        }

        $fileName = uniqid('img_') . '.' . $ext;
        $savePath = "../Uploads/sales/{$folder}/" . $fileName;
        $dbPath = "Uploads/sales/{$folder}/" . $fileName;

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

    $processBase64Images($jewel_pic, $jewelPaths, $jewelBase64, "jewel");
    $processBase64Images($customer_pic, $customerPaths, $customerBase64, "customer");
    $processBase64Images($id_pic, $idPaths, $idBase64, "id");

    $jewelJson = json_encode($jewelPaths, JSON_UNESCAPED_SLASHES);
    $jewelBase64Json = json_encode($jewelBase64, JSON_UNESCAPED_SLASHES);
    $customerJson = json_encode($customerPaths, JSON_UNESCAPED_SLASHES);
    $customerBase64Json = json_encode($customerBase64, JSON_UNESCAPED_SLASHES);
    $idJson = json_encode($idPaths, JSON_UNESCAPED_SLASHES);
    $idBase64Json = json_encode($idBase64, JSON_UNESCAPED_SLASHES);

   $sql = "INSERT INTO `sale` (
        `sale_date`,`name`, `place`, `mobile_number`, `bank_name`, `bank_loan_amount`, 
        `customer_receive_amount`, `total_jewel_weight`, `total_loan_amount`, `tharam`, `staff_name`, 
        `jewel_pic`, `jewel_pic_base64code`, 
        `customer_pic`, `customer_pic_base64code`, 
        `id_pic`, `id_pic_base64code`, 
        `create_at`, `delete_at`
    ) VALUES (?,?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)";

    $stmt = $conn->prepare($sql);
    
    // There are 17 placeholders (?), so we need 17 "s" and 17 variables
    $stmt->bind_param("ssssssssssssssssss",
    $sale_date,
        $name, 
        $place, 
        $mobile_number, 
        $bank_name, 
        $bank_loan_amount, 
        $customer_receive_amount,
        $total_jewel_weight,
        $total_loan_amount,
        $tharam, 
        $staff_name,
        $jewelJson, 
        $jewelBase64Json,
        $customerJson, 
        $customerBase64Json,
        $idJson, 
        $idBase64Json,
        $timestamp
    );

    if ($stmt->execute()) {
        $id = $conn->insert_id;
        $uniqueSaleID = uniqueID('SALE', $id); // assuming you have the same uniqueID function as in customer

        $updateSql = "UPDATE `sale` SET `sale_id`=? WHERE `id`=?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("si", $uniqueSaleID, $id);
        $updateStmt->execute();
        $updateStmt->close();

        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Sale created successfully";
        $output["body"]["sale_id"] = $uniqueSaleID;
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Failed to create sale";
    }
    $stmt->close();
}

// <<<<<<<<<<===================== Delete Sale =====================>>>>>>>>>>
elseif (isset($obj['delete_sale_id'])) {
    if (!$conn || !($conn instanceof mysqli)) {
        $output["head"]["code"] = 500;
        $output["head"]["msg"] = "Database connection not established";
        echo json_encode($output, JSON_NUMERIC_CHECK);
        exit;
    }

    $delete_sale_id = $conn->real_escape_string(trim($obj['delete_sale_id']));

    $sql = "UPDATE `sale` SET `delete_at`=1 WHERE `sale_id`=? AND `delete_at`=0";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $delete_sale_id);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Sale deleted successfully";
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Failed to delete sale or sale not found";
    }
    $stmt->close();
}

else {
    $output["head"]["code"] = 400;
    $output["head"]["msg"] = "Invalid or missing parameters";
}

// <<< CLEAN ANY ACCIDENTAL OUTPUT (warnings, xdebug HTML, whitespace) >>>
ob_end_clean();

// Send only pure JSON
echo json_encode($output, JSON_NUMERIC_CHECK);

// Close connection cleanly
$conn->close();
exit;
?>