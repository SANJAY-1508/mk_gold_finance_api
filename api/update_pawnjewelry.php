<?php
include 'db/config.php';

if (!$conn) {
    file_put_contents('/var/log/pawnjewelry_update.log', "[ERROR] DB connection failed | " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    exit();
}

date_default_timezone_set('Asia/Calcutta');

// Centralized logger
function logDebug($message) {
    file_put_contents('/var/log/pawnjewelry_update.log', "[" . date('Y-m-d H:i:s') . "] $message\n", FILE_APPEND);
}

// function calculateActualDays($startDateStr, $endDateStr = null, $inclusive = true) {
//     if (!$startDateStr) return 0;
//   $start = DateTime::createFromFormat('d-m-Y', $startDateStr);
//     $end = $endDateStr ? new DateTime($endDateStr) : new DateTime();
//     $interval = $start->diff($end);
//     $days = (int)$interval->format('%a');
//     return $inclusive ? $days + 1 : $days;
// }
function calculateActualDays($startDateStr, $endDateStr = null, $inclusive = true) {
    if (empty($startDateStr)) return 0;

    // Try to detect and parse supported date formats
    $start = false;
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDateStr)) { // YYYY-MM-DD
        $start = DateTime::createFromFormat('Y-m-d', $startDateStr);
    } elseif (preg_match('/^\d{2}-\d{2}-\d{4}$/', $startDateStr)) { // DD-MM-YYYY
        $start = DateTime::createFromFormat('d-m-Y', $startDateStr);
    }

    // Fallback if all else fails
    if (!$start) {
        try {
            $start = new DateTime($startDateStr);
        } catch (Exception $e) {
            logDebug("[ERROR] Invalid start date: $startDateStr (" . $e->getMessage() . ")");
            return 0;
        }
    }

    // Parse end date or use current date
    if ($endDateStr) {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDateStr)) {
            $end = DateTime::createFromFormat('Y-m-d', $endDateStr);
        } elseif (preg_match('/^\d{2}-\d{2}-\d{4}$/', $endDateStr)) {
            $end = DateTime::createFromFormat('d-m-Y', $endDateStr);
        } else {
            try {
                $end = new DateTime($endDateStr);
            } catch (Exception $e) {
                logDebug("[ERROR] Invalid end date: $endDateStr (" . $e->getMessage() . ")");
                return 0;
            }
        }
    } else {
        $end = new DateTime();
    }

    if (!$start || !$end) {
        logDebug("[ERROR] Invalid date(s) detected: start=$startDateStr, end=$endDateStr");
        return 0;
    }

    $interval = $start->diff($end);
    $days = (int)$interval->format('%a');
    return $inclusive ? $days + 1 : $days;
}


$sql = "SELECT * FROM pawnjewelry WHERE delete_at = 0";
$result = $conn->query($sql);

if ($result === false) {
    logDebug("[ERROR] Query failed: " . $conn->error);
    exit();
}

while ($row = $result->fetch_assoc()) {
    $receipt_no = $row['receipt_no'];
    $pawn_date = $row['pawnjewelry_date'];
    $original_amount = floatval($row['original_amount']);
    $interest_rate = floatval(str_replace('%', '', $row['interest_rate'])) / 100;

    logDebug("Processing Receipt: $receipt_no | Pawn Date: $pawn_date | Orig Amt: $original_amount | Int Rate: $interest_rate");

    // Fetch latest topup
    $stmt = $conn->prepare("SELECT topup_date, SUM(topup_amount) AS total_topup FROM topup WHERE receipt_no = ? AND delete_at = 0 GROUP BY receipt_no ORDER BY topup_date DESC LIMIT 1");
    $stmt->bind_param("s", $receipt_no);
    $stmt->execute();
    $topup_result = $stmt->get_result();
    $stmt->close();

    $topup_date = null;
    $topup_amount = 0;
    if ($topup_result->num_rows > 0) {
        $topup_row = $topup_result->fetch_assoc();
        $topup_date = $topup_row['topup_date'];
        $topup_amount = floatval($topup_row['total_topup']);
    }
    logDebug("Topup | Date: $topup_date | Amount: $topup_amount");

    // Fetch latest deduction
    $stmt = $conn->prepare("SELECT deduction_date, SUM(deduction_amount) AS total_deduction FROM deduction WHERE receipt_no = ? AND delete_at = 0 GROUP BY receipt_no ORDER BY deduction_date DESC LIMIT 1");
    $stmt->bind_param("s", $receipt_no);
    $stmt->execute();
    $deduction_result = $stmt->get_result();
    $stmt->close();

    $deduction_date = null;
    $deduction_amount = 0;
    if ($deduction_result->num_rows > 0) {
        $deduction_row = $deduction_result->fetch_assoc();
        $deduction_date = $deduction_row['deduction_date'];
        $deduction_amount = floatval($deduction_row['total_deduction']);
    }
    logDebug("Deduction | Date: $deduction_date | Amount: $deduction_amount");

    // Fetch total interest already paid
    $stmt = $conn->prepare("SELECT SUM(interest_income) AS total_interest_paid FROM interest WHERE receipt_no = ? AND delete_at = 0");
    $stmt->bind_param("s", $receipt_no);
    $stmt->execute();
    $interest_result = $stmt->get_result();
    $stmt->close();

    $total_interest_paid = floatval($interest_result->fetch_assoc()['total_interest_paid'] ?? 0);
    logDebug("Total Interest Paid: $total_interest_paid");

    // Step 1: Compute initial monthly and daily interest
    $monthly_interest = $original_amount * $interest_rate;
    $daily_interest = $monthly_interest / 30;
    $reference_date = $pawn_date;
    logDebug("Initial | Monthly Int: $monthly_interest | Daily Int: $daily_interest | Ref Date: $reference_date");

    // Step 2: Adjust for topup
    if ($topup_date) {
        $days_until_topup = calculateActualDays($reference_date, $topup_date);
        $interest_until_topup = $daily_interest * $days_until_topup;
        logDebug("Topup Adjust | Days: $days_until_topup | Int Until: $interest_until_topup");
        if ($total_interest_paid >= $interest_until_topup) {
            $original_amount += $topup_amount;
            $reference_date = $topup_date;
            logDebug("Topup Applied | New Orig Amt: $original_amount | Ref Date: $reference_date");
        }
    }

    // Step 3: Adjust for deduction
    if ($deduction_date) {
        $days_until_deduction = calculateActualDays($reference_date, $deduction_date);
        $interest_until_deduction = $daily_interest * $days_until_deduction;
        logDebug("Deduction Adjust | Days: $days_until_deduction | Int Until: $interest_until_deduction");
        if ($total_interest_paid >= $interest_until_deduction) {
            $original_amount -= $deduction_amount;
            $reference_date = $deduction_date;
            logDebug("Deduction Applied | New Orig Amt: $original_amount | Ref Date: $reference_date");
        }
    }

    // Step 4: Recalculate interest after adjustments
    $monthly_interest = $original_amount * $interest_rate;
    $daily_interest = $monthly_interest / 30;
    logDebug("Recalculated | Monthly Int: $monthly_interest | Daily Int: $daily_interest");

    // Step 5: Calculate days and balances
    $total_days = calculateActualDays($reference_date);
    $days_paid = $daily_interest > 0 ? floor($total_interest_paid / $daily_interest) : 0;
    
    // Enforce minimum 15 days charge
    if ($total_days < 15) {
        $remaining_days = max(0, 15 - $days_paid);
        $remaining_interest = round($daily_interest * $remaining_days);
        logDebug("Applied Minimum Interest Rule | Min Days: 15 | Days Paid: $days_paid | Rem Days: $remaining_days | Rem Int: $remaining_interest");
    } else {
        $remaining_days = max(0, $total_days - $days_paid);
        $remaining_interest = round($daily_interest * $remaining_days);
        logDebug("Normal Interest Rule | Total Days: $total_days | Days Paid: $days_paid | Rem Days: $remaining_days | Rem Int: $remaining_interest");
    }

    // // Step 5: Calculate days and balances
    // $total_days = calculateActualDays($reference_date);
    // $days_paid = $daily_interest > 0 ? floor($total_interest_paid / $daily_interest) : 0;
    // $remaining_days = max(0, $total_days - $days_paid);
    // $remaining_interest = round($daily_interest * $remaining_days);

    // logDebug("Final | Total Days: $total_days | Days Paid: $days_paid | Rem Days: $remaining_days | Rem Int: $remaining_interest");

    // Step 6: Update DB (uncomment when ready)
    
    $stmt = $conn->prepare("UPDATE pawnjewelry SET interest_payment_period = ?, interest_payment_amount = ? WHERE id = ? AND delete_at = 0");
    $stmt->bind_param("ddi", $remaining_days, $remaining_interest, $row['id']);
    if ($stmt->execute()) {
        logDebug("Updated DB | Receipt: $receipt_no | Rem Days: $remaining_days | Rem Int: $remaining_interest");
    } else {
        logDebug("[ERROR] Update failed for $receipt_no: " . $stmt->error);
    }
    $stmt->close();
    
}

logDebug("Pawnjewelry daily-based update completed.");
echo "Pawnjewelry daily-based update completed successfully.";
?>
