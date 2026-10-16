<?php
declare(strict_types=1);
function enquiryEmail(array $clean, string $reference, string $submittedUtc): array
{
$esc = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$currentBizStr = implode(', ', $clean['currentBusiness']);

$htmlContent = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>SleepSync Franchise Enquiry</title>
</head>
<body style="margin:0;padding:24px;background-color:#f4f6fa;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#18243b;">
  <div style="max-width:680px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.06);border:1px solid #e1e7f0;">
    
    <!-- Header -->
    <div style="background:#173c8f;padding:28px 32px;color:#ffffff;">
      <h1 style="margin:0 0 6px 0;font-size:24px;font-weight:700;letter-spacing:-0.02em;">SleepSync</h1>
      <p style="margin:0;font-size:15px;color:#d5e2ff;">Franchise Expression of Interest</p>
    </div>

    <!-- Reference Metadata Box -->
    <div style="background:#f8faff;padding:16px 32px;border-bottom:1px solid #e1e7f0;font-size:13px;color:#4f5b72;">
      <p style="margin:0 0 4px 0;"><strong>Enquiry Reference:</strong> <span style="font-family:monospace;color:#173c8f;font-weight:700;font-size:14px;">{$esc($reference)}</span></p>
      <p style="margin:0;"><strong>Submitted:</strong> {$esc($submittedUtc)}</p>
    </div>

    <div style="padding:28px 32px;">
      
      <!-- SECTION A -->
      <h2 style="font-size:16px;text-transform:uppercase;letter-spacing:0.05em;color:#173c8f;margin:0 0 12px 0;padding-bottom:6px;border-bottom:2px solid #e8eef8;">
        Section A &middot; About the Applicant
      </h2>
      <table style="width:100%;border-collapse:collapse;margin-bottom:24px;font-size:14px;">
        <tr><td style="padding:7px 0;color:#626d80;width:38%;">Full Name</td><td style="padding:7px 0;font-weight:600;">{$esc($clean['fullName'])}</td></tr>
        <tr><td style="padding:7px 0;color:#626d80;">Designation</td><td style="padding:7px 0;">{$esc($clean['designation'])}</td></tr>
        <tr><td style="padding:7px 0;color:#626d80;">Mobile Number</td><td style="padding:7px 0;"><a href="tel:{$esc($clean['mobile'])}" style="color:#173c8f;text-decoration:none;">{$esc($clean['mobile'])}</a></td></tr>
        <tr><td style="padding:7px 0;color:#626d80;">WhatsApp Number</td><td style="padding:7px 0;"><a href="https://wa.me/{$esc(preg_replace('/\D/','',$clean['whatsapp']))}" style="color:#25d366;text-decoration:none;font-weight:600;">{$esc($clean['whatsapp'])} ↗</a></td></tr>
        <tr><td style="padding:7px 0;color:#626d80;">Email Address</td><td style="padding:7px 0;"><a href="mailto:{$esc($clean['email'])}" style="color:#173c8f;text-decoration:none;">{$esc($clean['email'])}</a></td></tr>
        <tr><td style="padding:7px 0;color:#626d80;">Business Name</td><td style="padding:7px 0;font-weight:600;">{$esc($clean['businessName'])}</td></tr>
        <tr><td style="padding:7px 0;color:#626d80;">Constitution</td><td style="padding:7px 0;">{$esc($clean['constitution'])}</td></tr>
        <tr><td style="padding:7px 0;color:#626d80;">Business Address</td><td style="padding:7px 0;">{$esc($clean['address'])}</td></tr>
        <tr><td style="padding:7px 0;color:#626d80;">Town / City</td><td style="padding:7px 0;">{$esc($clean['town'])}</td></tr>
        <tr><td style="padding:7px 0;color:#626d80;">District</td><td style="padding:7px 0;">{$esc($clean['district'])}</td></tr>
        <tr><td style="padding:7px 0;color:#626d80;">GSTIN</td><td style="padding:7px 0;">{$esc($clean['gstin'] ?: 'Not provided')}</td></tr>
        <tr><td style="padding:7px 0;color:#626d80;">Years in Business</td><td style="padding:7px 0;">{$esc($clean['yearsInBusiness'])} years</td></tr>
      </table>

      <!-- SECTION B -->
      <h2 style="font-size:16px;text-transform:uppercase;letter-spacing:0.05em;color:#173c8f;margin:0 0 12px 0;padding-bottom:6px;border-bottom:2px solid #e8eef8;">
        Section B &middot; Current Business Footprint
      </h2>
      <table style="width:100%;border-collapse:collapse;margin-bottom:24px;font-size:14px;">
        <tr><td style="padding:7px 0;color:#626d80;width:38%;">Current Category</td><td style="padding:7px 0;font-weight:600;">{$esc($currentBizStr)}</td></tr>
        <tr><td style="padding:7px 0;color:#626d80;">Showroom Area</td><td style="padding:7px 0;">{$esc($clean['showroomArea'])} sq ft</td></tr>
        <tr><td style="padding:7px 0;color:#626d80;">Number of Staff</td><td style="padding:7px 0;">{$esc($clean['staffCount'])}</td></tr>
        <tr><td style="padding:7px 0;color:#626d80;">Annual Turnover</td><td style="padding:7px 0;font-weight:600;">{$esc($clean['annualTurnover'])}</td></tr>
        <tr><td style="padding:7px 0;color:#626d80;">Mattress Brands Sold</td><td style="padding:7px 0;">{$esc($clean['mattressBrands'] ?: 'Not provided')}</td></tr>
        <tr><td style="padding:7px 0;color:#626d80;">Own Delivery Vehicle</td><td style="padding:7px 0;">{$esc($clean['deliveryVehicle'])}</td></tr>
        <tr><td style="padding:7px 0;color:#626d80;">Dedicated Delivery Team</td><td style="padding:7px 0;">{$esc($clean['deliveryTeam'])}</td></tr>
      </table>

      <!-- SECTION C -->
      <h2 style="font-size:16px;text-transform:uppercase;letter-spacing:0.05em;color:#173c8f;margin:0 0 12px 0;padding-bottom:6px;border-bottom:2px solid #e8eef8;">
        Section C &middot; Proposed Signature Store
      </h2>
      <table style="width:100%;border-collapse:collapse;margin-bottom:24px;font-size:14px;">
        <tr><td style="padding:7px 0;color:#626d80;width:38%;">Proposed Location</td><td style="padding:7px 0;font-weight:600;">{$esc($clean['locationType'])}</td></tr>
        <tr><td style="padding:7px 0;color:#626d80;">Address of Space</td><td style="padding:7px 0;">{$esc($clean['spaceAddress'] ?: 'Not provided')}</td></tr>
        <tr><td style="padding:7px 0;color:#626d80;">Area Available</td><td style="padding:7px 0;font-weight:600;">{$esc($clean['proposedArea'])} sq ft</td></tr>
        <tr><td style="padding:7px 0;color:#626d80;">Floor</td><td style="padding:7px 0;">{$esc($clean['floor'])}</td></tr>
        <tr><td style="padding:7px 0;color:#626d80;">Premises</td><td style="padding:7px 0;">{$esc($clean['premises'])}</td></tr>
        <tr><td style="padding:7px 0;color:#626d80;">Lease Remaining</td><td style="padding:7px 0;">{$esc($clean['leaseYears'] ?: 'Not provided')}</td></tr>
        <tr><td style="padding:7px 0;color:#626d80;">Territory of Interest</td><td style="padding:7px 0;font-weight:600;color:#173c8f;">{$esc($clean['territory'])}</td></tr>
      </table>

      <!-- SECTION D & E -->
      <h2 style="font-size:16px;text-transform:uppercase;letter-spacing:0.05em;color:#173c8f;margin:0 0 12px 0;padding-bottom:6px;border-bottom:2px solid #e8eef8;">
        Section D &amp; E &middot; Investment, Timing &amp; Declaration
      </h2>
      <table style="width:100%;border-collapse:collapse;margin-bottom:24px;font-size:14px;">
        <tr><td style="padding:7px 0;color:#626d80;width:38%;">Capital Available</td><td style="padding:7px 0;font-weight:600;">{$esc($clean['capitalAvailable'])}</td></tr>
        <tr><td style="padding:7px 0;color:#626d80;">Source of Funds</td><td style="padding:7px 0;">{$esc($clean['sourceOfFunds'])}</td></tr>
        <tr><td style="padding:7px 0;color:#626d80;">Who will run store</td><td style="padding:7px 0;">{$esc($clean['storeManager'])}</td></tr>
        <tr><td style="padding:7px 0;color:#626d80;">Target Opening</td><td style="padding:7px 0;font-weight:600;">{$esc($clean['targetOpening'])}</td></tr>
        <tr><td style="padding:7px 0;color:#626d80;">Heard of SleepSync via</td><td style="padding:7px 0;">{$esc($clean['hearAboutUs'])}</td></tr>
        <tr><td style="padding:7px 0;color:#626d80;">Applicant Comments</td><td style="padding:7px 0;white-space:pre-wrap;">{$esc($clean['comments'] ?: 'Not provided')}</td></tr>
        <tr><td style="padding:7px 0;color:#626d80;">Declaration &amp; Consent</td><td style="padding:7px 0;color:#1a7f37;font-weight:600;">&#10003; Declaration and consent confirmed</td></tr>
      </table>

      <!-- Footer Note -->
      <div style="border-top:1px solid #e1e7f0;padding-top:16px;font-size:12px;color:#8a94a6;line-height:1.5;">
        <p style="margin:0 0 4px 0;">Reply directly to this email to contact <strong>{$esc($clean['fullName'])}</strong> at <a href="mailto:{$esc($clean['email'])}" style="color:#173c8f;">{$esc($clean['email'])}</a>.</p>
        <p style="margin:0;">This expression of interest creates no binding obligation on either party.</p>
      </div>

    </div>
  </div>
</body>
</html>
HTML;

$plainContent = <<<TEXT
SLEEPSYNC FRANCHISE ENQUIRY
Reference: {$reference}
Submitted: {$submittedUtc}

SECTION A · ABOUT THE APPLICANT
- Full Name: {$clean['fullName']}
- Designation: {$clean['designation']}
- Mobile Number: {$clean['mobile']}
- WhatsApp Number: {$clean['whatsapp']}
- Email Address: {$clean['email']}
- Business Name: {$clean['businessName']}
- Constitution: {$clean['constitution']}
- Business Address: {$clean['address']}
- Town / City: {$clean['town']}
- District: {$clean['district']}
- GSTIN: {$clean['gstin']}
- Years in Business: {$clean['yearsInBusiness']}

SECTION B · CURRENT BUSINESS
- Current Category: {$currentBizStr}
- Showroom Area: {$clean['showroomArea']} sq ft
- Staff Count: {$clean['staffCount']}
- Annual Turnover: {$clean['annualTurnover']}
- Mattress Brands Sold: {$clean['mattressBrands']}
- Own Delivery Vehicle: {$clean['deliveryVehicle']}
- Delivery Team: {$clean['deliveryTeam']}

SECTION C · PROPOSED SIGNATURE STORE
- Proposed Location: {$clean['locationType']}
- Address of Space: {$clean['spaceAddress']}
- Area Available: {$clean['proposedArea']} sq ft
- Floor: {$clean['floor']}
- Premises: {$clean['premises']}
- Lease Years Remaining: {$clean['leaseYears']}
- Territory of Interest: {$clean['territory']}

SECTION D & E · INVESTMENT, TIMING & DECLARATION
- Capital Available: {$clean['capitalAvailable']}
- Source of Funds: {$clean['sourceOfFunds']}
- Who will run store: {$clean['storeManager']}
- Target Opening: {$clean['targetOpening']}
- How heard of us: {$clean['hearAboutUs']}
- Comments: {$clean['comments']}
- Consent: Confirmed

---
Reply directly to this email to contact the applicant at {$clean['email']}.
TEXT;


return [$htmlContent, $plainContent];
}
