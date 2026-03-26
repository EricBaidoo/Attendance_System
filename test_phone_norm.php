<?php
if (PHP_SAPI !== 'cli' || getenv('APP_ENABLE_MAINTENANCE_TOOLS') !== '1') {
    http_response_code(403);
    exit('Forbidden');
}

// Test phone normalization
function normalizePhoneForSms(string $phone): string {
    $digits = preg_replace('/[^0-9+]/', '', $phone);
    if ($digits === null) {
        return '';
    }

    $digits = trim($digits);
    if ($digits === '') {
        return '';
    }

    // Remove leading + and country code prefixes
    $digits = ltrim($digits, '+');
    
    // Remove 233 prefix if present (Ghana country code)
    if (strpos($digits, '233') === 0) {
        $digits = substr($digits, 3);
    }
    
    // Remove leading 0 if present
    if (strpos($digits, '0') === 0) {
        $digits = substr($digits, 1);
    }

    return $digits;
}

echo "Phone Normalization Test\n";
echo "========================\n\n";

$test_cases = [
    '0243838490',           // With leading 0 
    '243838490',            // Without leading 0
    '+233243838490',        // With +233
    '233243838490',         // With 233
    '+2342438384 90',       // With spaces
    '0(243) 838-490',       // With formatting
];

foreach ($test_cases as $phone) {
    $normalized = normalizePhoneForSms($phone);
    echo "Input:  '$phone'\n";
    echo "Output: '$normalized'\n";
    echo "\n";
}

echo "Expected output for all: 243838490\n";
?>
