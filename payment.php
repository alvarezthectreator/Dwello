<?php
// Dwelro Payment Page
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$uploadDir = __DIR__ . '/uploads/payments';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

function h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function get_setting(string $key, string $default = ''): string {
    $value = db_fetch_value("SELECT setting_value FROM settings WHERE setting_key = :key", ['key' => $key]);
    return $value !== null ? $value : $default;
}

$bankDetails = get_setting('bank_transfer_details', 'Account: 12345678 | Bank: Example Bank | SWIFT: EXAMPGB2L');
$wireDetails = get_setting('wire_transfer_details', 'SWIFT/BIC: EXAMPGB2L | Account: 12345678 | Beneficiary: Dwelro Pte Ltd');
$zelleEmail = get_setting('zelle_email', 'payments@dwelro.com');
$cashAppHandle = get_setting('cash_app_handle', '$DwelroPay');

$errors = [];
$success = false;
$summary = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $transactionType = trim($_POST['transaction_type'] ?? '');
    $paymentMethod = trim($_POST['payment_method'] ?? '');
    $amount = trim($_POST['amount'] ?? '');
    $reference = trim($_POST['reference'] ?? '');
    $property = trim($_POST['property_address'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if ($fullName === '') {
        $errors[] = 'Full name is required.';
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email address is required.';
    }
    if ($transactionType === '') {
        $errors[] = 'Please choose a transaction type.';
    }
    if ($paymentMethod === '') {
        $errors[] = 'Please choose a payment method.';
    }
    if ($amount === '' || !preg_match('/^\d+(\.\d{1,2})?$/', $amount) || floatval($amount) <= 0) {
        $errors[] = 'Enter a valid payment amount.';
    }
    if (empty($_FILES['receipt']) || $_FILES['receipt']['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = 'Please upload a payment receipt or proof of payment.';
    }

    if (empty($errors)) {
        $allowedTypes = [
            'application/pdf' => '.pdf',
            'image/jpeg' => '.jpg',
            'image/png' => '.png',
            'image/jpg' => '.jpg',
        ];

        $receipt = $_FILES['receipt'];
        if ($receipt['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'There was a problem uploading the receipt. Please try again.';
        } elseif (!isset($allowedTypes[$receipt['type']])) {
            $errors[] = 'Receipt must be a PDF, JPG, or PNG file.';
        } elseif ($receipt['size'] > 5 * 1024 * 1024) {
            $errors[] = 'Receipt file size must be 5MB or less.';
        } else {
            $extension = $allowedTypes[$receipt['type']];
            $safeName = preg_replace('/[^a-zA-Z0-9-_\.]/', '-', pathinfo($receipt['name'], PATHINFO_FILENAME));
            $filename = date('YmdHis') . '-' . substr(md5($safeName . rand()), 0, 8) . $extension;
            $targetPath = $uploadDir . '/' . $filename;

            if (move_uploaded_file($receipt['tmp_name'], $targetPath)) {
                $success = true;
                $summary = [
                    'name' => $fullName,
                    'email' => $email,
                    'phone' => $phone,
                    'transaction_type' => $transactionType,
                    'payment_method' => $paymentMethod,
                    'amount' => number_format((float)$amount, 2),
                    'reference' => $reference ?: 'N/A',
                    'property' => $property ?: 'N/A',
                    'notes' => $notes ?: 'None',
                    'receipt_file' => $filename,
                    'received_at' => date('j F Y, H:i'),
                ];
            } else {
                $errors[] = 'Unable to save the receipt file. Please try again later.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Dwelro Payment Portal</title>
<meta name="description" content="Submit secure payment details for Dwelro property services, deposits, inspection fees, and rental payments.">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Dwelro">
<meta property="og:title" content="Dwelro Payment Portal">
<meta property="og:description" content="Submit secure payment details for Dwelro property services, deposits, inspection fees, and rental payments.">
<meta property="og:image" content="/img/modern-luxury-home-with-pool-contemporary-architecture.jpg">
<meta property="og:image:alt" content="Luxury property preview">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="Dwelro Payment Portal">
<meta name="twitter:description" content="Submit secure payment details for Dwelro property services, deposits, inspection fees, and rental payments.">
<meta name="twitter:image" content="/img/modern-luxury-home-with-pool-contemporary-architecture.jpg">
<meta name="theme-color" content="#1a3a5c">
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet" />
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#f6f8fb;--surface:#ffffff;--primary:#1a3a5c;--primary-light:#3b82f6;--muted:#6b7280;--success:#16a34a;--danger:#dc2626;--border:#d1d5db;--radius:16px;--shadow:0 24px 60px rgba(15,23,42,.08);--maxw:1120px;}
body{font-family:'DM Sans',sans-serif;background:linear-gradient(180deg,#eef4fb 0%,#f8fafc 100%);color:#111827;min-height:100vh;}
.page-wrap{max-width:var(--maxw);margin:0 auto;padding:28px 24px 40px;}
.page-header{display:flex;align-items:flex-start;justify-content:space-between;gap:20px;margin-bottom:28px;}
.hero-copy{max-width:720px;}
.hero-copy h1{font-size:clamp(2rem,3vw,3rem);line-height:1.05;margin-bottom:14px;color:var(--primary);}
.hero-copy p{font-size:1rem;line-height:1.75;color:var(--muted);}
.hero-badges{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin-top:20px;}
.badge{background:#fff;border:1px solid var(--border);border-radius:14px;padding:16px;box-shadow:var(--shadow);}
.badge strong{display:block;font-size:14px;color:var(--primary);margin-bottom:8px;}
.badge span{font-size:0.95rem;color:var(--muted);line-height:1.6;}
.form-panel{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;}
.form-panel header{padding:24px 24px 0;}
.form-panel h2{font-size:1.25rem;color:var(--primary);margin-bottom:10px;}
.form-panel p{font-size:.975rem;color:var(--muted);line-height:1.8;}
.form-body{padding:24px;display:grid;grid-template-columns:1fr 320px;gap:24px;}
.form-column{display:flex;flex-direction:column;gap:18px;}
.form-field{display:flex;flex-direction:column;gap:8px;}
.form-field label{font-size:.86rem;font-weight:700;color:#111827;}
.form-field input,
.form-field select,
.form-field textarea{width:100%;border:1px solid var(--border);border-radius:12px;padding:14px 16px;font-size:.98rem;color:#111827;background:#f9fafb;outline:none;transition:border .15s,box-shadow .15s;}
.form-field input:focus,
.form-field select:focus,
.form-field textarea:focus{border-color:var(--primary-light);box-shadow:0 0 0 4px rgba(59,130,246,.12);}
.form-field input:invalid,
.form-field textarea:invalid,
.form-field select:invalid{border-color:var(--danger);}
.form-field textarea{min-height:140px;resize:vertical;}
.radio-group{display:grid;gap:12px;}
.radio-card{display:flex;align-items:center;gap:12px;border:1px solid var(--border);border-radius:12px;padding:14px;cursor:pointer;background:#fff;transition:border .15s,box-shadow .15s;}
.radio-card input{accent-color:var(--primary);}
.radio-card:hover{border-color:var(--primary-light);box-shadow:0 10px 30px rgba(59,130,246,.08);}
.radio-card.selected{border-color:var(--primary);}
.upload-area{border:2px dashed var(--border);border-radius:14px;padding:22px;text-align:center;background:#f8fafc;}
.upload-area input{display:none;}
.upload-area label{display:inline-flex;align-items:center;justify-content:center;gap:10px;padding:12px 18px;border-radius:10px;background:var(--primary);color:#fff;font-weight:700;cursor:pointer;}
.btn-submit{width:100%;padding:16px 20px;border:none;border-radius:12px;background:var(--primary);color:#fff;font-size:1rem;font-weight:700;cursor:pointer;transition:background .2s;}
.btn-submit:hover{background:#0f4b84;}
.aside-card{background:#f8fafc;border:1px solid var(--border);border-radius:16px;padding:22px;display:flex;flex-direction:column;gap:18px;}
.aside-card h3{font-size:1rem;color:var(--primary);}
.aside-card p{font-size:.95rem;color:var(--muted);line-height:1.7;}
.payment-methods{display:grid;gap:14px;}
.method-item{border:1px solid var(--border);border-radius:14px;padding:16px;background:#fff;}
.method-item strong{display:block;font-size:.98rem;color:var(--primary);margin-bottom:8px;}
.method-item p{font-size:.95rem;color:var(--muted);line-height:1.65;}
.success-panel{background:#ecfdf5;border:1px solid #86efac;border-radius:16px;padding:24px;}
.success-panel h2{color:#166534;margin-bottom:14px;}
.success-panel p{color:#166534;line-height:1.7;}
.success-grid{display:grid;gap:12px;margin-top:18px;}
.success-item{background:#ffffff;border:1px solid #d1fae5;border-radius:12px;padding:14px;}
.success-item strong{display:block;color:#065f46;margin-bottom:4px;}
.success-item span{color:#334155;font-size:.95rem;}
.footer-note{margin-top:30px;font-size:.92rem;color:var(--muted);line-height:1.7;}
@media(max-width:960px){.form-body{grid-template-columns:1fr;}}
@media(max-width:720px){.page-header{flex-direction:column;align-items:flex-start;} .hero-badges{grid-template-columns:1fr;} .form-panel{border-radius:20px;}.upload-area{padding:18px;} }
</style>
</head>
<body>
<div class="page-wrap">
  <div class="page-header">
    <div class="hero-copy">
      <h1>Secure Payment Portal for Property Services</h1>
      <p>Submit payment details for inspection fees, purchase deposits or rental payments. Attach proof of payment and we will confirm your transaction quickly.</p>
      <div class="hero-badges">
        <div class="badge"><strong>Fast confirmation</strong><span>Our team reviews payments within 24 hours and confirms receipt.</span></div>
        <div class="badge"><strong>Multiple methods</strong><span>Bank transfer, wire, Zelle, Cash App or manual payment receipts accepted.</span></div>
      </div>
    </div>
    <div class="badge" style="border:none;box-shadow:none;background:transparent;padding:0;">
      <div class="method-item"><strong>How it works</strong><p>Choose the transaction type, select a payment method, enter the amount, and upload your receipt. A confirmation summary will appear after submission.</p></div>
    </div>
  </div>

  <?php if ($success): ?>
  <section class="success-panel">
    <h2>Payment Received</h2>
    <p>Thank you, <?= h($summary['name']) ?>. Your payment details have been submitted successfully. We will review the receipt and confirm the transaction shortly.</p>
    <div class="success-grid">
      <div class="success-item"><strong>Transaction type</strong><span><?= h($summary['transaction_type']) ?></span></div>
      <div class="success-item"><strong>Payment method</strong><span><?= h($summary['payment_method']) ?></span></div>
      <div class="success-item"><strong>Amount</strong><span>$<?= h($summary['amount']) ?></span></div>
      <div class="success-item"><strong>Reference</strong><span><?= h($summary['reference']) ?></span></div>
      <div class="success-item"><strong>Property / Service</strong><span><?= h($summary['property']) ?></span></div>
      <div class="success-item"><strong>Receipt file</strong><span><?= h($summary['receipt_file']) ?></span></div>
    </div>
    <p class="footer-note">If you need a formal invoice or receipt for accounting, please reply to the confirmation email or contact our support team with the transaction reference.</p>
  </section>
  <?php endif; ?>

  <?php if (!empty($errors)): ?>
  <div class="success-panel" style="background:#fdecea;border-color:#fca5a5;color:#991b1b;">
    <h2>Submission problem</h2>
    <ul style="margin-top:12px;list-style:disc inside;line-height:1.75;color:#7f1d1d;">
      <?php foreach ($errors as $error): ?>
      <li><?= h($error) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>

  <div class="form-panel">
    <header>
      <h2>Payment details</h2>
      <p>Send payment instructions to your customer by sharing this page link. They can complete the payment safely and attach their receipt here.</p>
    </header>
    <div class="form-body">
      <div class="form-column">
        <form method="POST" enctype="multipart/form-data" novalidate>
          <div class="form-field"><label for="full_name">Full Name *</label><input id="full_name" name="full_name" type="text" required value="<?= h($_POST['full_name'] ?? '') ?>"/></div>
          <div class="form-field"><label for="email">Email *</label><input id="email" name="email" type="email" required value="<?= h($_POST['email'] ?? '') ?>"/></div>
          <div class="form-field"><label for="phone">Phone Number</label><input id="phone" name="phone" type="tel" value="<?= h($_POST['phone'] ?? '') ?>"/></div>

          <div class="form-field"><label for="transaction_type">Transaction Type *</label><select id="transaction_type" name="transaction_type" required>
            <option value="">Select transaction</option>
            <option value="Inspection Fee" <?= (($_POST['transaction_type'] ?? '') === 'Inspection Fee') ? 'selected' : '' ?>>Inspection Fee</option>
            <option value="Purchase Deposit" <?= (($_POST['transaction_type'] ?? '') === 'Purchase Deposit') ? 'selected' : '' ?>>Purchase Deposit</option>
            <option value="Rental Deposit" <?= (($_POST['transaction_type'] ?? '') === 'Rental Deposit') ? 'selected' : '' ?>>Rental Deposit</option>
            <option value="Property Service" <?= (($_POST['transaction_type'] ?? '') === 'Property Service') ? 'selected' : '' ?>>Property Service</option>
          </select></div>

          <div class="form-field"><label>Payment Method *</label>
            <div class="radio-group">
              <?php
              $methods = [
                'Bank Transfer',
                'Wire Transfer',
                'Zelle',
                'Cash App',
                'Check / In-person',
              ];
              foreach ($methods as $method):
              ?>
              <label class="radio-card<?= (($_POST['payment_method'] ?? '') === $method) ? ' selected' : '' ?>">
                <input type="radio" name="payment_method" value="<?= h($method) ?>" required <?= (($_POST['payment_method'] ?? '') === $method) ? 'checked' : '' ?> />
                <span><?= h($method) ?></span>
              </label>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="form-field"><label for="amount">Amount (USD) *</label><input id="amount" name="amount" type="text" inputmode="decimal" placeholder="e.g. 2500.00" required value="<?= h($_POST['amount'] ?? '') ?>"/></div>
          <div class="form-field"><label for="reference">Payment Reference</label><input id="reference" name="reference" type="text" placeholder="e.g. INV-12345 or bank transfer memo" value="<?= h($_POST['reference'] ?? '') ?>"/></div>
          <div class="form-field"><label for="property_address">Property Details</label><input id="property_address" name="property_address" type="text" placeholder="Address, listing name, or unit" value="<?= h($_POST['property_address'] ?? '') ?>"/></div>
          <div class="form-field"><label for="notes">Notes</label><textarea id="notes" name="notes" placeholder="Any additional details for the payment or transaction."><?= h($_POST['notes'] ?? '') ?></textarea></div>

          <div class="form-field">
            <label>Upload payment receipt *</label>
            <div class="upload-area">
              <p>Accepted: PDF, JPG, PNG. Max size 5MB.</p>
              <label for="receipt">Choose receipt file</label>
              <input id="receipt" name="receipt" type="file" accept="application/pdf,image/*" required />
            </div>
          </div>

          <button type="submit" class="btn-submit">Submit Payment Proof</button>
        </form>
      </div>
      <aside class="aside-card">
        <h3>Accepted payment methods</h3>
        <div class="payment-methods">
          <div class="method-item"><strong>Bank Transfer</strong><p>Use our bank transfer details: <strong><?= h($bankDetails) ?></strong>. Include the payment reference and property address in your memo.</p></div>
          <div class="method-item"><strong>Wire Transfer</strong><p>Use our wire details: <strong><?= h($wireDetails) ?></strong>. Please include the beneficiary name and property reference in the payment note.</p></div>
          <div class="method-item"><strong>Zelle</strong><p>Pay to our secure Zelle email: <strong><?= h($zelleEmail) ?></strong>. Use the property address in the memo.</p></div>
          <div class="method-item"><strong>Cash App</strong><p>Send to <strong><?= h($cashAppHandle) ?></strong>. Upload the Cash App receipt or screenshot as proof.</p></div>
        </div>
        <div class="method-item" style="margin-top:16px"><strong>Receipt upload</strong><p>Upload one file with your payment confirmation. This helps us verify your transaction and issue a final receipt.</p></div>
      </aside>
    </div>
  </div>

  <p class="footer-note">After submission, our team will verify the receipt and send an official payment receipt or invoice to your email. Keep the payment reference ready for faster processing.</p>
</div>
</body>
</html>
