<?php
declare(strict_types=1);

/** Strict validation of every field in the existing four-step form. */
function validateEnquiry(array $data): array
{
    $errors = [];
    $clean = [];
    // UTF-8 byte limits also bound the generated email; no mbstring dependency.
    $strings = [
        'fullName' => [120, true], 'designation' => [120, true],
        'mobile' => [18, true], 'whatsapp' => [18, true], 'email' => [254, true],
        'businessName' => [180, true], 'address' => [500, true],
        'town' => [120, true], 'district' => [120, true], 'gstin' => [15, false],
        'mattressBrands' => [500, false], 'spaceAddress' => [500, false],
        'floor' => [120, true], 'leaseYears' => [120, false],
        'territory' => [200, true], 'comments' => [3000, false],
    ];
    $choices = [
        'constitution' => ['Proprietorship', 'Partnership', 'LLP', 'Private Ltd', 'Other'],
        'annualTurnover' => ['Below ₹1 Cr', '₹1–3 Cr', '₹3–10 Cr', 'Above ₹10 Cr'],
        'deliveryVehicle' => ['Yes', 'No'], 'deliveryTeam' => ['Yes', 'No'],
        'locationType' => ['Within my showroom', 'Adjacent unit', 'New premises', 'Not yet identified'],
        'premises' => ['Owned', 'Leased'],
        'capitalAvailable' => ['Below ₹15 L', '₹15–20 L', '₹20–30 L', 'Above ₹30 L'],
        'sourceOfFunds' => ['Own funds', 'Bank finance', 'Both'],
        'storeManager' => ['Myself, full-time', 'Myself, part-time', 'A family member', 'A manager'],
        'targetOpening' => ['Within 3 months', '3–6 months', '6–12 months', 'Exploring'],
        'hearAboutUs' => ['SleepSync team', 'Website', 'Social media', 'Referral', 'Other'],
    ];
    foreach ($strings as $field => [$max, $required]) {
        $value = $data[$field] ?? '';
        if (!is_string($value) || strlen($value) > $max ||
            preg_match($field === 'comments' ? '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/' : '/[\x00-\x1F\x7F]/', $value)) {
            $errors[$field] = "Enter text of at most {$max} UTF-8 bytes, without control characters.";
            continue;
        }
        $clean[$field] = trim($value);
        if ($required && $clean[$field] === '') {
            $errors[$field] = 'Please complete this required field.';
        }
    }
    foreach ($choices as $field => $options) {
        if (!isset($data[$field]) || !is_string($data[$field]) || !in_array($data[$field], $options, true)) {
            $errors[$field] = 'Please select one of the available options.';
        } else {
            $clean[$field] = $data[$field];
        }
    }
    foreach (['yearsInBusiness' => 100, 'showroomArea' => 10000000, 'staffCount' => 1000000, 'proposedArea' => 10000000] as $field => $max) {
        $value = $data[$field] ?? null;
        if ((!is_string($value) && !is_int($value)) ||
            !preg_match('/\A[0-9]{1,8}\z/', (string)$value) || (int)$value > $max) {
            $errors[$field] = "Enter a whole number from 0 to {$max}.";
        } else {
            $clean[$field] = (int)$value;
        }
    }
    foreach (['mobile', 'whatsapp'] as $field) {
        if (isset($clean[$field]) && (!preg_match('/\A[+0-9() -]{8,18}\z/', $clean[$field]) ||
            strlen(preg_replace('/\D/', '', $clean[$field])) < 8)) {
            $errors[$field] = 'Enter a valid phone number with at least 8 digits.';
        }
    }
    if (isset($clean['email']) && !filter_var($clean['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }
    if (isset($clean['gstin'])) {
        $clean['gstin'] = strtoupper($clean['gstin']);
        if ($clean['gstin'] !== '' && !preg_match('/\A[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][A-Z0-9]Z[A-Z0-9]\z/', $clean['gstin'])) {
            $errors['gstin'] = 'Enter a valid 15-character GSTIN or leave it empty.';
        }
    }
    $business = $data['currentBusiness'] ?? null;
    $allowedBusiness = ['Furniture', 'Furnishings', 'Mattresses', 'Home appliances', 'Other / none'];
    if (!is_array($business) || !array_is_list($business) || count($business) < 1 || count($business) > 5) {
        $errors['currentBusiness'] = 'Select at least one current business category.';
    } else {
        foreach ($business as $item) {
            if (!is_string($item) || !in_array($item, $allowedBusiness, true)) {
                $errors['currentBusiness'] = 'Select only the available business categories.';
            }
        }
        if (!isset($errors['currentBusiness'])) {
            $clean['currentBusiness'] = array_values(array_unique($business));
            sort($clean['currentBusiness']);
        }
    }
    if (($data['consent'] ?? null) !== true) {
        $errors['consent'] = 'Please confirm the declaration and consent.';
    }
    $clean['consent'] = true;
    $known = array_merge(array_keys($strings), array_keys($choices), ['yearsInBusiness', 'showroomArea', 'staffCount', 'proposedArea', 'currentBusiness', 'consent', 'website']);
    if (array_diff(array_keys($data), $known)) {
        $errors['_form'] = 'The request contains unsupported fields. Reload the page and try again.';
    }
    return [$clean, $errors];
}
