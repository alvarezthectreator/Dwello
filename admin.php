<?php
/**
 * Dwelro — Admin Dashboard (admin.php)
 * Server-side rendered. All data pulled directly from DB on page load.
 * Mutations (status updates, add/edit/delete) post back to this same file.
 *
 * FIXES:
 *  - slug generated automatically on INSERT (no more Field 'slug' error)
 *  - cover image upload (file OR URL) added to Add/Edit Property modal
 */

session_start();

// Check authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// ── Upload directory ──────────────────────────────────────────────────────────
define('UPLOAD_DIR', __DIR__ . '/uploads/properties/');
define('UPLOAD_URL', 'uploads/properties/');
define('PAYMENT_RECEIPT_DIR', __DIR__ . '/uploads/payment_receipts/');
define('PAYMENT_RECEIPT_URL', 'uploads/payment_receipts/');
define('PAYMENTS_DIR', __DIR__ . '/data');
define('PAYMENTS_FILE', PAYMENTS_DIR . '/payments.json');
if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
if (!is_dir(PAYMENT_RECEIPT_DIR)) mkdir(PAYMENT_RECEIPT_DIR, 0755, true);
if (!is_dir(PAYMENTS_DIR)) mkdir(PAYMENTS_DIR, 0755, true);

/**
 * Handle a file upload from $_FILES[$field].
 * Returns the public URL string on success, or null if no file was chosen.
 * Throws RuntimeException on failure.
 */
function handle_upload(string $field): ?string {
    if (empty($_FILES[$field]['name'])) return null;
    $file = $_FILES[$field];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload error code ' . $file['error']);
    }
    $mime_ok = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $finfo   = new finfo(FILEINFO_MIME_TYPE);
    $mime    = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $mime_ok, true)) {
        throw new RuntimeException('Only JPG, PNG, GIF and WebP images are allowed.');
    }
    if ($file['size'] > 10 * 1024 * 1024) {
        throw new RuntimeException('File must be under 10 MB.');
    }
    $ext      = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'][$mime];
    $filename = uniqid('prop_', true) . '.' . $ext;
    $dest     = UPLOAD_DIR . $filename;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Could not save uploaded file.');
    }
    return UPLOAD_URL . $filename;
}

/**
 * Build a URL-safe slug from a string.
 */
function make_slug(string $str): string {
    $slug = strtolower(trim($str));
    $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
    $slug = preg_replace('/[\s-]+/', '-', $slug);
    return trim($slug, '-');
}

function handle_receipt_upload(string $field): ?string {
    if (empty($_FILES[$field]['name'])) {
        return null;
    }
    $file = $_FILES[$field];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Receipt upload error code ' . $file['error']);
    }
    $mime_ok = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/png' => 'png'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset($mime_ok[$mime])) {
        throw new RuntimeException('Receipt must be PDF, JPG or PNG.');
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('Receipt file must be 5MB or smaller.');
    }
    $ext = $mime_ok[$mime];
    $name = pathinfo($file['name'], PATHINFO_FILENAME);
    $name = preg_replace('/[^a-zA-Z0-9-_\.]/', '-', $name);
    $filename = uniqid('rcpt_', true) . '-' . $name . '.' . $ext;
    $dest = PAYMENT_RECEIPT_DIR . $filename;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Could not save receipt upload.');
    }
    return PAYMENT_RECEIPT_URL . $filename;
}

function load_payment_records(): array {
    if (!file_exists(PAYMENTS_FILE)) {
        return [];
    }
    $data = json_decode(file_get_contents(PAYMENTS_FILE), true);
    return is_array($data) ? $data : [];
}

function save_payment_records(array $records): bool {
    return file_put_contents(PAYMENTS_FILE, json_encode(array_values($records), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)) !== false;
}

function find_payment_record(array $records, int $id): ?array {
    foreach ($records as $record) {
        if ((int)$record['id'] === $id) {
            return $record;
        }
    }
    return null;
}

// ── Handle POST mutations ─────────────────────────────────────────────────────
$toast     = '';
$toast_err = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['_action'] ?? '';

    // ── Enquiry status ────────────────────────────────────────────────────────
    if ($act === 'update_enquiry_status') {
        $id     = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? '';
        if ($id && in_array($status, ['new', 'contacted', 'scheduled', 'closed', 'lost'], true)) {
            db_execute("UPDATE enquiries SET status = :s WHERE id = :id", ['s' => $status, 'id' => $id]);
            $toast = 'Enquiry status updated';
        }
    }

    // ── Save / add property ───────────────────────────────────────────────────
    if ($act === 'save_property') {
        $id    = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $slug  = trim($_POST['slug'] ?? '');

        $features = array_values(array_filter(array_map('trim', explode(',', $_POST['features'] ?? ''))));

        $data = [
            'title'           => $title,
            'category'        => trim($_POST['category']        ?? 'condo'),
            'listing_type'    => trim($_POST['listing_type']    ?? 'sale'),
            'status'          => trim($_POST['status']          ?? 'available'),
            'price_display'   => trim($_POST['price_display']   ?? ''),
            'price_sgd'       => !empty($_POST['price_sgd']) ? (int)$_POST['price_sgd'] : null,
            'price_psf'       => ($_POST['price_psf'] ?? '') !== '' ? (float)$_POST['price_psf'] : null,
            'rental_pcm'      => !empty($_POST['rental_pcm']) ? (int)$_POST['rental_pcm'] : null,
            'district'        => trim($_POST['district']        ?? ''),
            'area'            => trim($_POST['area']            ?? ''),
            'address'         => trim($_POST['address']         ?? ''),
            'country'         => trim($_POST['country']         ?? 'United Kingdom'),
            'property_type'   => trim($_POST['property_type']   ?? ''),
            'badge'           => trim($_POST['badge']           ?? ''),
            'floor_level'     => trim($_POST['floor_level']     ?? ''),
            'land_area_sqft'  => !empty($_POST['land_area_sqft']) ? (int)$_POST['land_area_sqft'] : null,
            'tenure'          => in_array($_POST['tenure'] ?? '', ['freehold','99_year','999_year','leasehold','other'], true) ? $_POST['tenure'] : 'other',
            'furnishing'      => in_array($_POST['furnishing'] ?? '', ['unfurnished','partial','fully_furnished'], true) ? $_POST['furnishing'] : 'unfurnished',
            'built_year'      => !empty($_POST['built_year']) ? (int)$_POST['built_year'] : null,
            'virtual_tour_url'=> trim($_POST['virtual_tour_url'] ?? ''),
            'floor_plan_url'  => trim($_POST['floor_plan_url'] ?? ''),
            'meta_title'      => trim($_POST['meta_title']      ?? ''),
            'meta_desc'       => trim($_POST['meta_desc']       ?? ''),
            'features'        => $features ? json_encode($features) : null,
            'bedrooms'        => (int)($_POST['bedrooms']       ?? 0),
            'bathrooms'       => (int)($_POST['bathrooms']      ?? 0),
            'floor_area'      => (int)($_POST['floor_area_sqft'] ?? 0),
            'agent_id'        => (int)($_POST['agent_id']       ?? 0) ?: null,
            'is_featured'     => isset($_POST['is_featured']) ? 1 : 0,
            'description'     => trim($_POST['description']     ?? ''),
        ];

        // Handle cover image: file upload takes priority over URL field
        try {
            $cover_uploaded = handle_upload('cover_image_file');
        } catch (RuntimeException $e) {
            $cover_uploaded = null;
            $toast     = $e->getMessage();
            $toast_err = true;
        }

        if (!$toast_err) {
            $cover_url = $cover_uploaded ?? trim($_POST['cover_image_url'] ?? '');

            if ($id) {
                // Build cover update clause only if a new image was supplied
                $cover_clause = $cover_url ? ', cover_image_url=:cover' : '';
                $params = array_merge($data, ['id' => $id]);
                if ($cover_url) $params['cover'] = $cover_url;

                // Update slug if provided
                if ($slug) {
                    $params['slug'] = make_slug($slug);
                    $cover_clause .= ', slug=:slug';
                }

                db_execute("UPDATE properties SET
                    title=:title, category=:category, listing_type=:listing_type,
                    status=:status, price_display=:price_display, price_sgd=:price_sgd,
                    price_psf=:price_psf, rental_pcm=:rental_pcm, district=:district,
                    area=:area, address=:address, country=:country, property_type=:property_type,
                    badge=:badge, floor_level=:floor_level, land_area_sqft=:land_area_sqft,
                    tenure=:tenure, furnishing=:furnishing, TOP_year=:built_year,
                    virtual_tour_url=:virtual_tour_url, floor_plan_url=:floor_plan_url,
                    meta_title=:meta_title, meta_desc=:meta_desc, features=:features,
                    bedrooms=:bedrooms, bathrooms=:bathrooms, floor_area_sqft=:floor_area,
                    agent_id=:agent_id, is_featured=:is_featured, description=:description{$cover_clause}
                    WHERE id=:id", $params);
                $toast = 'Property updated';
            } else {
                // Generate a unique slug: "title-slug-{timestamp}" or use provided
                $slug_base = $slug ? make_slug($slug) : make_slug($title);
                $slug_final = $slug_base ?: 'property';
                // Ensure unique
                $existing = db_fetch_value("SELECT COUNT(*) FROM properties WHERE slug = :s", ['s' => $slug_final]);
                if ($existing) $slug_final .= '-' . time();

                $params = array_merge($data, [
                    'slug'  => $slug_final,
                    'cover' => $cover_url ?: null,
                ]);

                db_execute("INSERT INTO properties
                    (title,category,listing_type,status,price_display,price_sgd,price_psf,rental_pcm,
                     district,area,address,country,property_type,badge,floor_level,land_area_sqft,
                     tenure,furnishing,TOP_year,virtual_tour_url,floor_plan_url,meta_title,meta_desc,
                     features,bedrooms,bathrooms,floor_area_sqft,agent_id,is_featured,description,
                     slug,cover_image_url,is_published,views,created_at)
                    VALUES
                    (:title,:category,:listing_type,:status,:price_display,:price_sgd,:price_psf,:rental_pcm,
                     :district,:area,:address,:country,:property_type,:badge,:floor_level,:land_area_sqft,
                     :tenure,:furnishing,:built_year,:virtual_tour_url,:floor_plan_url,:meta_title,:meta_desc,
                     :features,:bedrooms,:bathrooms,:floor_area,:agent_id,:is_featured,:description,
                     :slug,:cover,1,0,NOW())", $params);
                $toast = 'Property added';
            }
        }
    }

    // ── Bulk delete properties ─────────────────────────────────────────────────
    if ($act === 'bulk_delete_property') {
        $ids = $_POST['ids'] ?? [];
        if ($ids) {
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';
            db_execute("DELETE FROM properties WHERE id IN ($placeholders)", $ids);
            $toast = 'Deleted ' . count($ids) . ' properties';
        }
    }

    // ── Bulk publish/unpublish ────────────────────────────────────────────────
    if ($act === 'bulk_publish') {
        $ids = $_POST['ids'] ?? [];
        $pub = (int)($_POST['is_published'] ?? 1);
        if ($ids) {
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';
            db_execute("UPDATE properties SET is_published = ? WHERE id IN ($placeholders)", array_merge([$pub], $ids));
            $toast = ($pub ? 'Published' : 'Unpublished') . ' ' . count($ids) . ' properties';
        }
    }

    // ── Bulk assign agent ─────────────────────────────────────────────────────
    if ($act === 'bulk_assign_agent') {
        $ids = $_POST['ids'] ?? [];
        $agent_id = (int)($_POST['agent_id'] ?? 0);
        if ($ids && $agent_id) {
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';
            db_execute("UPDATE properties SET agent_id = ? WHERE id IN ($placeholders)", array_merge([$agent_id], $ids));
            $toast = 'Assigned agent to ' . count($ids) . ' properties';
        }
    }

    // ── Toggle published ──────────────────────────────────────────────────────
    if ($act === 'toggle_published') {
        $id  = (int)($_POST['id']           ?? 0);
        $pub = (int)($_POST['is_published'] ?? 0);
        if ($id) {
            db_execute("UPDATE properties SET is_published = :pub WHERE id = :id", ['pub' => $pub, 'id' => $id]);
            $toast = 'Property ' . ($pub ? 'published' : 'unpublished');
        }
    }

    // ── Save / add agent ──────────────────────────────────────────────────────
    if ($act === 'save_agent') {
        $id   = (int)($_POST['id'] ?? 0);
        $data = [
            'name'  => trim($_POST['full_name']      ?? ''),
            'title' => trim($_POST['title']          ?? ''),
            'spec'  => trim($_POST['specialisation'] ?? ''),
            'cea'   => trim($_POST['cea_number']     ?? ''),
            'email' => trim($_POST['email']          ?? ''),
            'phone' => trim($_POST['phone']          ?? ''),
            'photo' => trim($_POST['photo_url']      ?? ''),
            'exp'   => (int)($_POST['years_exp']     ?? 0),
            'deals' => (int)($_POST['deals_closed']  ?? 0),
            'vol'   => (int)($_POST['portfolio_sgd'] ?? 0),
        ];
        if ($id) {
            db_execute("UPDATE agents SET full_name=:name, title=:title, specialisation=:spec,
                cea_number=:cea, email=:email, phone=:phone, photo_url=:photo, years_exp=:exp, deals_closed=:deals,
                portfolio_sgd=:vol WHERE id=:id", array_merge($data, ['id' => $id]));
            $toast = 'Agent updated';
        } else {
            db_execute("INSERT INTO agents (full_name,title,specialisation,cea_number,email,phone,photo_url,
                years_exp,deals_closed,portfolio_sgd,is_active)
                VALUES (:name,:title,:spec,:cea,:email,:phone,:photo,:exp,:deals,:vol,1)", $data);
            $toast = 'Agent added';
        }
    }

    // ── Delete agent ──────────────────────────────────────────────────────────
    if ($act === 'delete_agent') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            db_execute("UPDATE agents SET is_active = 0 WHERE id = :id", ['id' => $id]);
            $toast = 'Agent removed';
        }
    }

    // ── Save / add testimonial ────────────────────────────────────────────────
    if ($act === 'save_testimonial') {
        $id   = (int)($_POST['id'] ?? 0);
        $data = [
            'author' => trim($_POST['author_name'] ?? 'Anonymous'),
            'loc'    => trim($_POST['location']    ?? ''),
            'quote'  => trim($_POST['quote']       ?? ''),
            'rating' => (int)($_POST['rating']     ?? 5),
            'panel'  => $_POST['panel']            ?? 'dark',
        ];
        if ($id) {
            db_execute("UPDATE testimonials SET author_name=:author, location=:loc,
                quote=:quote, rating=:rating, panel=:panel WHERE id=:id",
                array_merge($data, ['id' => $id]));
            $toast = 'Testimonial updated';
        } else {
            db_execute("INSERT INTO testimonials (author_name,location,quote,rating,panel,sort_order,is_active)
                VALUES (:author,:loc,:quote,:rating,:panel,99,1)", $data);
            $toast = 'Testimonial added';
        }
    }

    // ── Delete testimonial ────────────────────────────────────────────────────
    if ($act === 'delete_testimonial') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            db_execute("UPDATE testimonials SET is_active = 0 WHERE id = :id", ['id' => $id]);
            $toast = 'Testimonial deleted';
        }
    }

    // ── Save / add payment record ──────────────────────────────────────────────
    if ($act === 'save_payment_record') {
        $records = load_payment_records();
        $id = (int)($_POST['id'] ?? 0);
        $data = [
            'payer_name' => trim($_POST['payer_name'] ?? ''),
            'email'      => trim($_POST['email'] ?? ''),
            'phone'      => trim($_POST['phone'] ?? ''),
            'transaction_type' => trim($_POST['transaction_type'] ?? ''),
            'payment_method'   => trim($_POST['payment_method'] ?? ''),
            'amount'     => trim($_POST['amount'] ?? ''),
            'reference'  => trim($_POST['reference'] ?? ''),
            'property'   => trim($_POST['property'] ?? ''),
            'notes'      => trim($_POST['notes'] ?? ''),
            'status'     => in_array($_POST['status'] ?? '', ['pending','confirmed','rejected'], true) ? $_POST['status'] : 'pending',
            'active'     => isset($_POST['is_active']) ? 1 : 0,
            'updated_at' => date('c'),
        ];

        if ($id && ($existing = find_payment_record($records, $id))) {
            $data['id'] = $id;
            $data['created_at'] = $existing['created_at'];
            $data['receipt_url'] = $existing['receipt_url'] ?? null;
        } else {
            $data['id'] = $records ? max(array_column($records, 'id')) + 1 : 1;
            $data['created_at'] = date('c');
            $data['receipt_url'] = null;
        }

        try {
            $receipt = handle_receipt_upload('receipt');
            if ($receipt) {
                $data['receipt_url'] = $receipt;
            }
        } catch (RuntimeException $e) {
            $toast = $e->getMessage();
            $toast_err = true;
        }

        if (!$toast_err) {
            // Simple validation prior to save
            if ($data['payer_name'] === '' || $data['email'] === '' || !filter_var($data['email'], FILTER_VALIDATE_EMAIL) || $data['transaction_type'] === '' || $data['payment_method'] === '' || $data['amount'] === '' || !preg_match('/^\d+(\.\d{1,2})?$/', $data['amount'])) {
                $toast = 'Please provide valid payment record details.';
                $toast_err = true;
            }
        }

        if (!$toast_err) {
            if ($id && $existing) {
                foreach ($records as $index => $record) {
                    if ((int)$record['id'] === $id) {
                        $records[$index] = $data;
                        break;
                    }
                }
                $toast = 'Payment updated';
            } else {
                $records[] = $data;
                $toast = 'Payment record created';
            }
            save_payment_records($records);
        }
    }

    // ── Toggle payment active / inactive ───────────────────────────────────────
    if ($act === 'toggle_payment_active') {
        $records = load_payment_records();
        $id = (int)($_POST['id'] ?? 0);
        $active = isset($_POST['active']) && $_POST['active'] === '1' ? 1 : 0;
        foreach ($records as $index => $record) {
            if ((int)$record['id'] === $id) {
                $records[$index]['active'] = $active;
                $records[$index]['updated_at'] = date('c');
                save_payment_records($records);
                $toast = $active ? 'Payment record activated' : 'Payment record deactivated';
                break;
            }
        }
    }

    // ── Save image (upload or URL) ────────────────────────────────────────────
    if ($act === 'save_image') {
        $prop_id  = (int)($_POST['property_id'] ?? 0);
        $caption  = trim($_POST['caption']      ?? '');
        $is_cover = (int)($_POST['is_cover']    ?? 0);
        $sort     = (int)($_POST['sort_order']  ?? 99);
        $img_id   = (int)($_POST['id']          ?? 0);

        try {
            $uploaded = handle_upload('image_file');
            $url = $uploaded ?? trim($_POST['image_url'] ?? '');

            if (!$url)     throw new RuntimeException('Please provide an image file or URL.');
            if (!$prop_id) throw new RuntimeException('Please select a property.');

            if ($is_cover) {
                db_execute("UPDATE property_images SET is_cover = 0 WHERE property_id = :pid", ['pid' => $prop_id]);
            }

            if ($img_id) {
                db_execute(
                    "UPDATE property_images SET image_url=:url, caption=:cap, is_cover=:cov, sort_order=:s WHERE id=:id",
                    ['url' => $url, 'cap' => $caption, 'cov' => $is_cover, 's' => $sort, 'id' => $img_id]
                );
                if ($is_cover) {
                    db_execute("UPDATE properties SET cover_image_url = :url WHERE id = :pid", ['url' => $url, 'pid' => $prop_id]);
                }
                $toast = 'Image updated';
            } else {
                db_execute(
                    "INSERT INTO property_images (property_id, image_url, caption, is_cover, sort_order)
                     VALUES (:pid, :url, :cap, :cov, :s)",
                    ['pid' => $prop_id, 'url' => $url, 'cap' => $caption, 'cov' => $is_cover, 's' => $sort]
                );
                if ($is_cover) {
                    db_execute("UPDATE properties SET cover_image_url = :url WHERE id = :pid", ['url' => $url, 'pid' => $prop_id]);
                }
                $toast = 'Image added';
            }
        } catch (RuntimeException $e) {
            $toast     = $e->getMessage();
            $toast_err = true;
        }
    }

    // ── Delete image ──────────────────────────────────────────────────────────
    if ($act === 'delete_image') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $row = db_fetch_all("SELECT image_url FROM property_images WHERE id = :id", ['id' => $id]);
            if ($row && strpos($row[0]['image_url'], UPLOAD_URL) === 0) {
                $file = __DIR__ . '/' . $row[0]['image_url'];
                if (file_exists($file)) @unlink($file);
            }
            db_execute("DELETE FROM property_images WHERE id = :id", ['id' => $id]);
            $toast = 'Image deleted';
        }
    }

    // ── Update image sort orders ─────────────────────────────────────────────
    if ($act === 'update_image_sort') {
        $updates = json_decode($_POST['updates'] ?? '[]', true);
        if ($updates) {
            foreach ($updates as $update) {
                db_execute("UPDATE property_images SET sort_order = :s WHERE id = :id", [
                    's' => (int)$update['sort'],
                    'id' => (int)$update['id']
                ]);
            }
            $toast = 'Image order updated';
        }
    }

    // ── Save video URL ────────────────────────────────────────────────────────
    if ($act === 'save_video') {
        $id  = (int)($_POST['id']       ?? 0);
        $url = trim($_POST['video_url'] ?? '');
        if ($id) {
            db_execute("UPDATE properties SET video_url = :url WHERE id = :id", ['url' => $url ?: null, 'id' => $id]);
            $toast = 'Video URL saved';
        }
    }

    // ── Logout ────────────────────────────────────────────────────────────────
    if ($act === 'logout') {
        session_destroy();
        header('Location: login.php');
        exit;
    }

    // ── Save settings ─────────────────────────────────────────────────────────
    if ($act === 'save_settings') {
        $settings = [
            'site_name' => trim($_POST['site_name'] ?? 'Dwelro'),
            'admin_email' => trim($_POST['admin_email'] ?? 'admin@dwelro.sg'),
            'primary_markets' => trim($_POST['primary_markets'] ?? 'London, Paris, Amsterdam, Berlin, Madrid'),
            'currency' => trim($_POST['currency'] ?? 'SGD'),
            'bank_transfer_details' => trim($_POST['bank_transfer_details'] ?? 'Account: 12345678 | Bank: Example Bank | SWIFT: EXAMPGB2L'),
            'wire_transfer_details' => trim($_POST['wire_transfer_details'] ?? 'SWIFT/BIC: EXAMPGB2L | Account: 12345678 | Beneficiary: Dwelro Pte Ltd'),
            'zelle_email' => trim($_POST['zelle_email'] ?? 'payments@dwelro.com'),
            'cash_app_handle' => trim($_POST['cash_app_handle'] ?? '$DwelroPay'),
        ];

        // Store in database; if the row exists but nothing changed, no duplicate insert will occur.
        foreach ($settings as $key => $value) {
            db_execute(
                "INSERT INTO settings (setting_key, setting_value) VALUES (:key, :val)"
                . " ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
                ['key' => $key, 'val' => $value]
            );
        }
        $toast = 'Settings saved';
    }

    // ── Save admin user ───────────────────────────────────────────────────────
    if ($act === 'save_admin_user') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = trim($_POST['role'] ?? 'admin');
        $password = trim($_POST['password'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if (!$name || !$email) {
            $toast = 'Name and email are required';
            $toast_err = true;
        } else {
            if ($id) {
                // Update existing
                $params = ['name' => $name, 'email' => $email, 'role' => $role, 'active' => $is_active, 'id' => $id];
                $sql = "UPDATE admin_users SET name=:name, email=:email, role=:role, is_active=:active";
                if ($password) {
                    $params['hash'] = password_hash($password, PASSWORD_DEFAULT);
                    $sql .= ", password_hash=:hash";
                }
                $sql .= " WHERE id=:id";
                db_execute($sql, $params);
                $toast = 'Admin user updated';
            } else {
                // Add new
                if (!$password) {
                    $toast = 'Password is required for new users';
                    $toast_err = true;
                } else {
                    db_execute("INSERT INTO admin_users (name, email, role, password_hash, is_active, created_at)
                               VALUES (:name, :email, :role, :hash, :active, NOW())", [
                        'name' => $name,
                        'email' => $email,
                        'role' => $role,
                        'hash' => password_hash($password, PASSWORD_DEFAULT),
                        'active' => $is_active,
                    ]);
                    $toast = 'Admin user added';
                }
            }
        }
    }

    // ── Delete admin user ─────────────────────────────────────────────────────
    if ($act === 'delete_admin_user') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id && $id !== $_SESSION['admin_id']) { // Can't delete yourself
            db_execute("DELETE FROM admin_users WHERE id = :id", ['id' => $id]);
            $toast = 'Admin user deleted';
        } else {
            $toast = 'Cannot delete your own account';
            $toast_err = true;
        }
    }

    // ── Reset admin user password ─────────────────────────────────────────────
    if ($act === 'reset_admin_password') {
        $id = (int)($_POST['id'] ?? 0);
        $new_password = trim($_POST['new_password'] ?? '');
        if ($id && $new_password) {
            db_execute("UPDATE admin_users SET password_hash = :hash WHERE id = :id", [
                'hash' => password_hash($new_password, PASSWORD_DEFAULT),
                'id' => $id
            ]);
            $toast = 'Password reset successfully';
        } else {
            $toast = 'Invalid request';
            $toast_err = true;
        }
    }

    // ── Update profile ───────────────────────────────────────────────────────
    if ($act === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (!$name || !$email) {
            $toast = 'Name and email are required';
            $toast_err = true;
        } else {
            $params = ['name' => $name, 'email' => $email, 'id' => $_SESSION['admin_id']];
            $sql = "UPDATE admin_users SET name=:name, email=:email";
            if ($password) {
                $params['hash'] = password_hash($password, PASSWORD_DEFAULT);
                $sql .= ", password_hash=:hash";
            }
            $sql .= " WHERE id=:id";
            db_execute($sql, $params);

            // Update session
            $_SESSION['admin_name'] = $name;
            $_SESSION['admin_email'] = $email;

            $toast = 'Profile updated successfully';
        }
    }

    // Redirect to prevent re-POST on refresh
    $rpage = $_POST['_page'] ?? 'dashboard';
    $flag  = $toast_err ? '&toast_err=1' : '';
    header("Location: admin.php?page={$rpage}&toast=" . urlencode($toast) . $flag);
    exit;
}

// ── Active page ───────────────────────────────────────────────────────────────
$page      = $_GET['page']      ?? 'dashboard';
$toast     = $_GET['toast']     ?? '';
$toast_err = isset($_GET['toast_err']);

// ── CSV export for newsletter ─────────────────────────────────────────────────
if ($page === 'newsletter' && isset($_GET['export'])) {
    $rows = db_fetch_all("SELECT email, status, subscribed_at FROM newsletter_subscribers ORDER BY subscribed_at DESC");
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="subscribers_' . date('Y-m-d') . '.csv"');
    echo "Email,Status,Subscribed\n";
    foreach ($rows as $r) {
        echo $r['email'] . ',' . $r['status'] . ',' . ($r['subscribed_at'] ?? '') . "\n";
    }
    exit;
}

// ── Stats (always needed) ─────────────────────────────────────────────────────
$total_props   = (int) db_fetch_value("SELECT COUNT(*) FROM properties");
$published     = (int) db_fetch_value("SELECT COUNT(*) FROM properties WHERE is_published = 1");
$draft         = $total_props - $published;
$total_agents  = (int) db_fetch_value("SELECT COUNT(*) FROM agents WHERE is_active = 1");
$enq_open      = (int) db_fetch_value("SELECT COUNT(*) FROM enquiries WHERE status IN ('new','contacted')");
$total_views   = (int) db_fetch_value("SELECT COALESCE(SUM(views),0) FROM properties");
$featured      = (int) db_fetch_value("SELECT COUNT(*) FROM properties WHERE is_featured=1 AND is_published=1");
$available     = (int) db_fetch_value("SELECT COUNT(*) FROM properties WHERE status='available'");

// ── Settings ─────────────────────────────────────────────────────────────────
$settings_rows = db_fetch_all("SELECT setting_key, setting_value FROM settings");
$settings = [];
foreach ($settings_rows as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$settings = array_merge([
    'site_name' => 'Dwelro',
    'admin_email' => 'admin@dwelro.sg',
    'primary_markets' => 'London, Paris, Amsterdam, Berlin, Madrid',
    'currency' => 'SGD',
    'bank_transfer_details' => 'Account: 12345678 | Bank: Example Bank | SWIFT: EXAMPGB2L',
    'wire_transfer_details' => 'SWIFT/BIC: EXAMPGB2L | Account: 12345678 | Beneficiary: Dwelro Pte Ltd',
    'zelle_email' => 'payments@dwelro.com',
    'cash_app_handle' => '$DwelroPay',
], $settings);
$sold          = (int) db_fetch_value("SELECT COUNT(*) FROM properties WHERE status='sold'");
$under_offer   = (int) db_fetch_value("SELECT COUNT(*) FROM properties WHERE status='under_offer'");
$testi_live    = (int) db_fetch_value("SELECT COUNT(*) FROM testimonials WHERE is_active=1");
$nl_subs       = (int) db_fetch_value("SELECT COUNT(*) FROM newsletter_subscribers WHERE status='active'");
$enq_new       = (int) db_fetch_value("SELECT COUNT(*) FROM enquiries WHERE status='new'");
$enq_contacted = (int) db_fetch_value("SELECT COUNT(*) FROM enquiries WHERE status='contacted'");
$enq_scheduled = (int) db_fetch_value("SELECT COUNT(*) FROM enquiries WHERE status='scheduled'");
$enq_closed    = (int) db_fetch_value("SELECT COUNT(*) FROM enquiries WHERE status='closed'");
$enq_lost      = (int) db_fetch_value("SELECT COUNT(*) FROM enquiries WHERE status='lost'");

// ── Page-specific data ────────────────────────────────────────────────────────
$page_num = (int)($_GET['p'] ?? 1);
$limit = 50;
$offset = ($page_num - 1) * $limit;

$prop_status = $_GET['f_status'] ?? '';
$prop_pub = $_GET['f_pub'] ?? '';
$prop_cat = $_GET['f_cat'] ?? '';
$prop_search = $_GET['f_search'] ?? '';

$prop_where = "WHERE 1=1";
$prop_params = [];
if ($prop_status) {
  $prop_where .= " AND p.status = :status";
  $prop_params['status'] = $prop_status;
}
if ($prop_pub !== '') {
  $prop_where .= " AND p.is_published = :pub";
  $prop_params['pub'] = (int)$prop_pub;
}
if ($prop_cat) {
  $prop_where .= " AND p.category = :cat";
  $prop_params['cat'] = $prop_cat;
}
if ($prop_search) {
  $prop_where .= " AND (p.title LIKE :search OR p.area LIKE :search OR p.address LIKE :search OR p.badge LIKE :search)";
  $prop_params['search'] = '%' . $prop_search . '%';
}

$properties = ($page === 'properties' || $page === 'dashboard') ? db_fetch_all("
    SELECT p.id, p.title, p.category, p.listing_type, p.status,
           p.price_display, p.price_sgd, p.price_psf, p.rental_pcm,
           p.district, p.area, p.address, p.country, p.property_type,
           p.badge, p.floor_level, p.land_area_sqft, p.tenure, p.furnishing,
           p.TOP_year AS built_year, p.virtual_tour_url, p.floor_plan_url,
           p.meta_title, p.meta_desc, p.features, p.cover_image_url,
           p.is_featured, p.is_published, p.views,
           p.bedrooms, p.bathrooms, p.floor_area_sqft, p.created_at,
           p.video_url, p.description, p.agent_id, p.slug,
           a.full_name AS agent_name
    FROM properties p
    LEFT JOIN agents a ON a.id = p.agent_id
    $prop_where
    ORDER BY p.is_published DESC, p.created_at DESC
    LIMIT $limit OFFSET $offset
", $prop_params) : [];

$total_properties = ($page === 'properties') ? (int)db_fetch_value("SELECT COUNT(*) FROM properties p $prop_where", $prop_params) : 0;
$total_pages = ceil($total_properties / $limit);

// Agents list for property modal dropdown (always needed on properties page)
$agents_list = ($page === 'properties') ? db_fetch_all("
    SELECT id, full_name FROM agents WHERE is_active = 1 ORDER BY full_name ASC
") : [];

$agents = ($page === 'agents') ? db_fetch_all("
    SELECT id, full_name, title, specialisation, cea_number,
           photo_url, years_exp, deals_closed, portfolio_sgd, email, phone
    FROM agents WHERE is_active = 1 ORDER BY id ASC
") : [];

$enq_status = $_GET['eq_status'] ?? '';
$enq_search = $_GET['eq_search'] ?? '';

$enq_where = "WHERE 1=1";
$enq_params = [];
if ($enq_status) {
  $enq_where .= " AND e.status = :status";
  $enq_params['status'] = $enq_status;
}
if ($enq_search) {
  $enq_where .= " AND (e.first_name LIKE :search OR e.last_name LIKE :search OR e.email LIKE :search)";
  $enq_params['search'] = '%' . $enq_search . '%';
}

$enquiries = ($page === 'enquiries' || $page === 'dashboard') ? db_fetch_all("
    SELECT e.id, e.first_name, e.last_name, e.email, e.phone, e.message,
           e.enquiry_type, e.preferred_date, e.preferred_time,
           e.status, e.source, e.created_at,
           p.title AS property_title, a.full_name AS agent_name
    FROM enquiries e
    LEFT JOIN properties p ON p.id = e.property_id
    LEFT JOIN agents     a ON a.id = e.agent_id
    $enq_where
    ORDER BY FIELD(e.status,'new','contacted','scheduled','closed','lost'), e.created_at DESC
    LIMIT $limit OFFSET $offset
", $enq_params) : [];

$total_enquiries = ($page === 'enquiries') ? (int)db_fetch_value("SELECT COUNT(*) FROM enquiries e $enq_where", $enq_params) : 0;
$total_enq_pages = ceil($total_enquiries / $limit);

$testimonials = ($page === 'testimonials') ? db_fetch_all("
    SELECT id, author_name, location, quote, rating, panel, sort_order
    FROM testimonials WHERE is_active = 1
    ORDER BY sort_order ASC, id ASC
") : [];

$newsletter = ($page === 'newsletter') ? db_fetch_all("
    SELECT id, email, status, subscribed_at
    FROM newsletter_subscribers ORDER BY subscribed_at DESC
") : [];

// Images: include property_id for grouping
$images = ($page === 'images') ? db_fetch_all("
    SELECT pi.id, pi.property_id, pi.image_url, pi.caption,
           pi.sort_order, pi.is_cover,
           p.title AS property_title
    FROM property_images pi
    JOIN properties p ON p.id = pi.property_id
    ORDER BY pi.property_id ASC, pi.sort_order ASC
") : [];

$views_data = ($page === 'views') ? db_fetch_all("
    SELECT title, views, is_published FROM properties ORDER BY views DESC
") : [];

$admin_users = ($page === 'admin_users') ? db_fetch_all("
    SELECT id, name, email, role, last_login, is_active FROM admin_users ORDER BY id ASC
") : [];

$payment_records = ($page === 'payments') ? load_payment_records() : [];

// Properties list for image modal dropdown
$prop_list = ($page === 'images') ? db_fetch_all("
    SELECT id, title FROM properties ORDER BY title ASC
") : [];

// Recent activity for right panel
$recent_activity = db_fetch_all("
    SELECT e.first_name, e.last_name, e.status, e.created_at,
           p.title AS property_title
    FROM enquiries e
    LEFT JOIN properties p ON p.id = e.property_id
    ORDER BY e.created_at DESC LIMIT 6
");

// ── Helpers ───────────────────────────────────────────────────────────────────
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function fmt_date(?string $d): string { return $d ? date('d M Y', strtotime($d)) : '—'; }
function fmt_num($n): string { return number_format((int)$n); }
function vol_display(int $v): string {
    if ($v >= 1_000_000_000) return '$' . round($v / 1_000_000_000, 1) . 'B';
    if ($v >= 1_000_000)     return '$' . round($v / 1_000_000) . 'M';
    return '$' . number_format($v);
}
function stars(int $n): string { return str_repeat('★', $n) . str_repeat('☆', 5 - $n); }
function tag(string $cls, string $text): string {
    return '<span class="tag tag-' . h($cls) . '">' . h($text) . '</span>';
}

$now      = new DateTime();
$hour     = (int)$now->format('H');
$greeting = $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');
$date_str = $now->format('j F Y');

$tips = [
    "Stay ahead of your property management goals.",
    "Check your enquiry pipeline for new leads.",
    "Review your latest listings and keep them fresh.",
    "Your dashboard is up to date and ready to go.",
];
$tip = $tips[array_rand($tips)];

$nav = [
    'dashboard'    => ['label' => 'Dashboard',       'section' => 'Main'],
    'properties'   => ['label' => 'Properties',      'section' => 'Listings'],
    'images'       => ['label' => 'Property Images', 'section' => null],
    'views'        => ['label' => 'Property Views',  'section' => null],
    'agents'       => ['label' => 'Agents',          'section' => 'People'],
    'enquiries'    => ['label' => 'Enquiries',       'section' => null],
    'newsletter'   => ['label' => 'Newsletter',      'section' => null],
    'payments'     => ['label' => 'Payments',        'section' => 'Transactions'],
    'testimonials' => ['label' => 'Testimonials',    'section' => 'Content'],
    'admin_users'  => ['label' => 'Admin Users',     'section' => 'System'],
    'settings'     => ['label' => 'Settings',        'section' => null],
];

$nav_icons = [
    'dashboard'    => '<path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/>',
    'properties'   => '<path d="M12 7V3H2v18h20V7H12zM6 19H4v-2h2v2zm0-4H4v-2h2v2zm0-4H4V9h2v2zm0-4H4V5h2v2zm4 12H8v-2h2v2zm0-4H8v-2h2v2zm0-4H8V9h2v2zm0-4H8V5h2v2zm10 12h-8v-2h2v-2h-2v-2h2v-2h-2V9h8v10zm-2-8h-2v2h2v-2zm0 4h-2v2h2v-2z"/>',
    'images'       => '<path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/>',
    'views'        => '<path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>',
    'agents'       => '<path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>',
    'enquiries'    => '<path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>',
    'newsletter'   => '<path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm-1 14H5c-.55 0-1-.45-1-1V8l8 5 8-5v9c0 .55-.45 1-1 1z"/>',
    'payments'     => '<path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 5H4V6h16v3zm0 9H4v-4h16v4z"/>',
    'testimonials' => '<path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>',
    'admin_users'  => '<path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>',
    'settings'     => '<path d="M19.14,12.94c0.04-0.3,0.06-0.61,0.06-0.94c0-0.32-0.02-0.64-0.07-0.94l2.03-1.58c0.18-0.14,0.23-0.41,0.12-0.61l-1.92-3.32c-0.12-0.22-0.37-0.29-0.59-0.22l-2.39,0.96c-0.5-0.38-1.03-0.7-1.62-0.94L14.4,2.81c-0.04-0.24-0.24-0.41-0.48-0.41h-3.84c-0.24,0-0.43,0.17-0.47,0.41L9.25,5.35C8.66,5.59,8.12,5.92,7.63,6.29L5.24,5.33c-0.22-0.08-0.47,0-0.59,0.22L2.74,8.87C2.62,9.08,2.66,9.34,2.86,9.48l2.03,1.58C4.84,11.36,4.8,11.69,4.8,12s0.02,0.64,0.07,0.94l-2.03,1.58c-0.18,0.14-0.23,0.41-0.12,0.61l1.92,3.32c0.12,0.22,0.37,0.29,0.59,0.22l2.39-0.96c0.5,0.38,1.03,0.7,1.62,0.94l0.36,2.54c0.05,0.24,0.24,0.41,0.48,0.41h3.84c0.24,0,0.44-0.17,0.47-0.41l0.36-2.54c0.59-0.24,1.13-0.56,1.62-0.94l2.39,0.96c0.22,0.08,0.47,0,0.59-0.22l1.92-3.32c0.12-0.22,0.07-0.47-0.12-0.61L19.14,12.94z M12,15.6c-1.98,0-3.6-1.62-3.6-3.6s1.62-3.6,3.6-3.6s3.6,1.62,3.6,3.6S13.98,15.6,12,15.6z"/>',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Dwelro Admin</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --navy:#1a3a5c;--navy-dk:#0f2640;--navy-lt:#e8eef5;
  --gold:#c9a84c;--white:#fff;--bg:#f4f6fa;--border:#e5e9f0;
  --muted:#8a94a6;--ink:#1a1f2e;
  --green:#22c55e;--red:#ef4444;--amber:#f59e0b;--blue:#3b82f6;
  --sidebar-w:240px;--topbar-h:64px;
  --serif:'DM Serif Display',serif;--sans:'DM Sans',sans-serif;
}
body{font-family:var(--sans);background:var(--bg);color:var(--ink);display:flex;min-height:100vh;overflow-x:hidden}

/* SIDEBAR */
.sidebar{width:var(--sidebar-w);flex-shrink:0;background:var(--white);border-right:1px solid var(--border);display:flex;flex-direction:column;position:fixed;top:0;left:0;bottom:0;z-index:200;overflow-y:auto}
.sidebar::-webkit-scrollbar{width:3px}.sidebar::-webkit-scrollbar-thumb{background:var(--border)}
.sidebar-logo{padding:22px 20px 18px;display:flex;align-items:center;gap:10px;border-bottom:1px solid var(--border)}
.logo-icon{width:36px;height:36px;background:var(--navy);border-radius:8px;display:flex;align-items:center;justify-content:center}
.logo-icon svg{width:18px;height:18px;fill:#fff}
.logo-text{font-family:var(--serif);font-size:20px;color:var(--navy)}.logo-text span{color:var(--gold)}
.sidebar-nav{flex:1;padding:12px 0}
.nav-section-label{padding:14px 20px 6px;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;color:var(--gold)}
.nav-item{display:flex;align-items:center;gap:10px;padding:9px 20px;font-size:13px;font-weight:500;color:#4a5568;text-decoration:none;border-left:3px solid transparent;transition:all .15s}
.nav-item:hover{background:var(--navy-lt);color:var(--navy);border-left-color:var(--navy-lt)}
.nav-item.active{background:var(--navy-lt);color:var(--navy);border-left-color:var(--navy);font-weight:700}
.nav-icon{font-size:15px;width:20px;text-align:center;flex-shrink:0;display:flex;align-items:center;justify-content:center}
.nav-icon svg{width:16px;height:16px;fill:currentColor}
.nav-badge{margin-left:auto;background:var(--red);color:#fff;font-size:9px;font-weight:700;padding:2px 6px;border-radius:10px}
.sidebar-footer{padding:16px 20px;border-top:1px solid var(--border)}
.logout-btn{display:flex;align-items:center;gap:10px;padding:9px 14px;font-size:13px;font-weight:600;color:#ef4444;cursor:pointer;border-radius:8px;width:100%;border:none;background:none;font-family:var(--sans)}
.logout-btn:hover{background:#fff5f5}.logout-btn svg{width:16px;height:16px;fill:#ef4444}

/* MAIN */
.main{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;min-height:100vh}

/* TOPBAR */
.topbar{height:var(--topbar-h);background:var(--white);border-bottom:1px solid var(--border);display:flex;align-items:center;padding:0 28px;gap:16px;position:sticky;top:0;z-index:100}
.topbar-greeting{flex:1}
.topbar-greeting h2{font-size:20px;font-weight:700;color:var(--ink)}
.topbar-greeting h2 span{color:var(--navy)}
.topbar-greeting p{font-size:12px;color:var(--muted);margin-top:1px}
.topbar-right{display:flex;align-items:center;gap:14px}
.top-date{display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:var(--muted);background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:7px 14px}
.top-date svg{width:14px;height:14px;fill:var(--muted)}
.top-profile{display:flex;align-items:center;gap:10px}
.top-avatar{width:38px;height:38px;border-radius:50%;object-fit:cover;border:2px solid var(--navy-lt)}
.top-profile-info{text-align:right}
.top-profile-name{font-size:13px;font-weight:700;color:var(--ink)}
.top-profile-role{font-size:10px;color:var(--muted)}
.online-dot{display:inline-block;width:8px;height:8px;background:var(--green);border-radius:50%;margin-left:4px;vertical-align:middle}

/* PAGE */
.page-content{flex:1;padding:24px 28px;display:flex;gap:22px}
.content-left{flex:1;min-width:0;display:flex;flex-direction:column;gap:20px}
.content-right{width:300px;flex-shrink:0;display:flex;flex-direction:column;gap:16px}
.page-title-row{display:flex;align-items:center;justify-content:space-between;margin-bottom:4px}
.page-title{font-size:24px;font-weight:700;color:var(--ink)}

/* STAT CARDS */
.stat-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}
.stat-card{background:var(--white);border:1px solid var(--border);border-radius:12px;padding:18px 20px}
.stat-label{font-size:11px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px}
.stat-val{font-size:26px;font-weight:800;color:var(--ink);line-height:1}
.stat-sub{font-size:11px;color:var(--muted);display:flex;align-items:center;gap:6px;margin-top:8px}
.stat-trend{font-size:10px;font-weight:700;padding:2px 7px;border-radius:10px}
.trend-up{background:#dcfce7;color:#16a34a}
.stat-link{font-size:10px;font-weight:700;color:var(--navy);display:inline-flex;align-items:center;gap:3px;margin-top:6px;text-decoration:none}
.stat-link:hover{text-decoration:underline}

/* OVERVIEW CARD */
.overview-card{background:var(--white);border:1px solid var(--border);border-radius:12px;padding:20px;display:flex;gap:20px;align-items:stretch}
.overview-img{width:220px;flex-shrink:0;border-radius:8px;overflow:hidden}
.overview-img img{width:100%;height:100%;object-fit:cover;display:block}
.overview-details{flex:1}
.overview-title{font-size:16px;font-weight:700;color:var(--ink);margin-bottom:14px}
.overview-row{display:flex;align-items:center;gap:10px;margin-bottom:10px;font-size:13px;font-weight:500}
.overview-row svg{width:16px;height:16px;fill:var(--muted);flex-shrink:0}
.overview-row strong{font-weight:700;color:var(--navy)}

/* SECTION CARD */
.section-card{background:var(--white);border:1px solid var(--border);border-radius:12px;overflow:hidden}
.section-head{padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
.section-head-title{font-size:14px;font-weight:700;color:var(--ink)}
.section-head-sub{font-size:11px;color:var(--muted);margin-top:1px}
.overview-link{font-size:11px;font-weight:700;color:var(--navy);text-decoration:none}

/* STATUS GRID */
.status-grid{display:grid;grid-template-columns:repeat(5,1fr)}
.status-cell{padding:16px 14px;border-right:1px solid var(--border)}
.status-cell:last-child{border-right:none}
.status-cell-label{font-size:11px;font-weight:700;color:var(--ink);margin-bottom:8px}
.status-cell-meta{font-size:10px;color:var(--muted);margin-top:4px}
.status-badge{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:20px;font-size:10px;font-weight:700}
.badge-ok{background:#dcfce7;color:#16a34a}
.badge-warn{background:#fef3c7;color:#d97706}
.badge-err{background:#fee2e2;color:#dc2626}
.badge-na{background:#f1f5f9;color:#64748b}

/* TABLE */
.data-table{width:100%;border-collapse:collapse}
.data-table th{padding:10px 16px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);background:#fafbfc;text-align:left;border-bottom:1px solid var(--border)}
.data-table td{padding:12px 16px;font-size:12px;border-bottom:1px solid var(--border);vertical-align:middle}
.data-table tr:last-child td{border-bottom:none}
.data-table tr:hover td{background:#fafbfc}
.prop-thumb{width:36px;height:36px;border-radius:6px;object-fit:cover}
.prop-name{font-weight:600;font-size:13px;color:var(--ink)}
.prop-sub{font-size:11px;color:var(--muted)}
.tag{display:inline-block;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700}
.tag-condo{background:#dbeafe;color:#1d4ed8}.tag-landed{background:#fce7f3;color:#9d174d}
.tag-hdb{background:#d1fae5;color:#065f46}.tag-commercial{background:#ede9fe;color:#5b21b6}
.tag-overseas{background:#fef3c7;color:#92400e}.tag-new_dev{background:#dbeafe;color:#1e40af}
.tag-investment{background:#f3e8ff;color:#7e22ce}.tag-available{background:#dcfce7;color:#15803d}
.tag-sold{background:#fee2e2;color:#b91c1c}.tag-under_offer{background:#fef9c3;color:#854d0e}
.tag-rented{background:#e0f2fe;color:#0369a1}.tag-new{background:#fce7f3;color:#9d174d}
.tag-contacted{background:#dbeafe;color:#1d4ed8}.tag-scheduled{background:#d1fae5;color:#065f46}
.tag-closed{background:#f1f5f9;color:#475569}.tag-lost{background:#fee2e2;color:#b91c1c}
.tag-sale{background:#ede9fe;color:#5b21b6}.tag-rent{background:#fef3c7;color:#92400e}
.tag-active{background:#dcfce7;color:#15803d}.tag-inactive{background:#fee2e2;color:#b91c1c}
.tag-draft{background:#f1f5f9;color:#64748b}.tag-published{background:#dcfce7;color:#15803d}
.action-btn{font-size:10px;font-weight:700;padding:5px 12px;border-radius:6px;cursor:pointer;border:1px solid var(--border);background:none;color:var(--navy);font-family:var(--sans);transition:all .15s;text-decoration:none;display:inline-block}
.action-btn:hover{background:var(--navy);color:#fff;border-color:var(--navy)}
.add-btn{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;background:var(--navy);color:#fff;border:none;border-radius:8px;font-size:11px;font-weight:700;cursor:pointer;font-family:var(--sans);text-decoration:none}
.add-btn:hover{background:var(--navy-dk)}

/* FILTER BAR */
.filter-bar{display:flex;align-items:center;gap:10px;padding:12px 16px;background:#fafbfc;border-bottom:1px solid var(--border);flex-wrap:wrap}
.filter-select{height:32px;border:1px solid var(--border);border-radius:6px;padding:0 10px;font-family:var(--sans);font-size:12px;color:var(--ink);background:var(--white)}
.filter-input{height:32px;border:1px solid var(--border);border-radius:6px;padding:0 10px;font-family:var(--sans);font-size:12px;color:var(--ink);flex:1;min-width:160px;outline:none}
.filter-input:focus{border-color:var(--navy)}
.filter-label{font-size:11px;font-weight:600;color:var(--muted)}

/* RIGHT PANEL */
.balance-card{background:linear-gradient(135deg,var(--navy) 0%,#1e5799 100%);border-radius:12px;padding:20px;color:#fff}
.balance-title{font-size:13px;font-weight:700;margin-bottom:14px;opacity:.85}
.balance-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px}
.balance-item-label{font-size:10px;opacity:.6;text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px}
.balance-item-val{font-size:17px;font-weight:800}
.balance-item-val.gold{color:var(--gold)}
.activity-card{background:var(--white);border:1px solid var(--border);border-radius:12px;overflow:hidden}
.activity-head{padding:14px 16px;border-bottom:1px solid var(--border);font-size:13px;font-weight:700}
.activity-item{display:flex;align-items:center;gap:12px;padding:11px 16px;border-bottom:1px solid var(--border)}
.activity-item:last-child{border-bottom:none}
.activity-thumb{width:38px;height:38px;border-radius:6px;background:var(--navy-lt);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.activity-thumb svg{width:18px;height:18px;fill:var(--navy)}
.activity-info{flex:1;min-width:0}
.activity-type{font-size:9px;font-weight:700;text-transform:uppercase;color:var(--muted);margin-bottom:1px}
.activity-title{font-size:12px;font-weight:600;color:var(--ink);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.activity-time{font-size:10px;color:var(--muted);margin-top:1px}

/* AGENTS GRID */
.agents-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:14px;padding:16px}
.agent-card{border:1px solid var(--border);border-radius:10px;padding:14px;display:flex;gap:12px;align-items:flex-start}
.agent-photo{width:44px;height:44px;border-radius:50%;object-fit:cover;flex-shrink:0;border:2px solid var(--navy-lt)}
.agent-name{font-size:13px;font-weight:700;color:var(--ink)}
.agent-title{font-size:11px;color:var(--muted);margin-bottom:6px}
.agent-stats{display:flex;gap:10px;flex-wrap:wrap}
.agent-stat{font-size:10px;font-weight:600;color:#555}
.agent-stat strong{color:var(--navy)}

/* TESTIMONIALS */
.testi-list{padding:14px 16px;display:flex;flex-direction:column;gap:12px}
.testi-card{border:1px solid var(--border);border-radius:8px;padding:12px 14px}
.testi-stars{color:var(--gold);font-size:11px;margin-bottom:4px}
.testi-quote{font-size:12px;color:#444;line-height:1.6;margin-bottom:8px}
.testi-author{font-size:11px;font-weight:700;color:var(--ink)}
.testi-location{font-size:10px;color:var(--muted);font-weight:400}
.testi-panel-badge{font-size:9px;font-weight:700;padding:2px 7px;border-radius:10px;margin-left:8px}
.panel-dark{background:var(--navy-lt);color:var(--navy)}
.panel-light{background:#fef3c7;color:#92400e}

/* VIEWS */
.views-list{padding:14px 16px;display:flex;flex-direction:column;gap:8px}
.view-row{display:flex;align-items:center;gap:12px}
.view-prop{flex:1;font-size:12px;font-weight:600;color:var(--ink)}
.view-bar-wrap{flex:2;height:8px;background:#f1f5f9;border-radius:4px;overflow:hidden}
.view-bar{height:100%;border-radius:4px;background:linear-gradient(90deg,var(--navy),#3b82f6)}
.view-count{font-size:12px;font-weight:700;color:var(--navy);width:36px;text-align:right}

/* IMAGE CARDS */
.images-property-group{margin-bottom:20px}
.img-card-grid{display:flex;flex-wrap:wrap;gap:12px;padding:16px}
.img-card{width:160px;border:1px solid var(--border);border-radius:8px;overflow:hidden;position:relative;transition:.2s}
.img-card:hover{box-shadow:0 4px 16px rgba(0,0,0,.1);transform:translateY(-2px)}
.img-card img{width:100%;height:110px;object-fit:cover;display:block}
.img-card-cover-badge{position:absolute;top:6px;left:6px;background:var(--gold);color:var(--navy-dk);font-size:9px;font-weight:700;padding:2px 7px;border-radius:3px;text-transform:uppercase}
.img-card-body{padding:8px}
.img-card-caption{font-size:11px;font-weight:600;color:var(--ink);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.img-card-meta{font-size:10px;color:var(--muted);margin-top:2px}
.img-card-actions{display:flex;gap:6px;margin-top:8px}

/* UPLOAD AREA */
.upload-zone{border:2px dashed var(--border);border-radius:10px;padding:28px 20px;text-align:center;cursor:pointer;transition:all .2s;position:relative;background:#fafbfc}
.upload-zone:hover,.upload-zone.drag-over{border-color:var(--navy);background:var(--navy-lt)}
.upload-zone input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
.upload-zone-icon{font-size:28px;margin-bottom:8px;display:block}
.upload-zone-title{font-size:13px;font-weight:700;color:var(--ink);margin-bottom:4px}
.upload-zone-sub{font-size:11px;color:var(--muted)}
.upload-preview{width:100%;height:180px;object-fit:cover;border-radius:8px;border:1px solid var(--border);display:none;margin-bottom:8px}
.upload-method-tabs{display:flex;gap:0;border:1px solid var(--border);border-radius:8px;overflow:hidden;margin-bottom:14px}
.umt-btn{flex:1;padding:8px;font-size:12px;font-weight:700;border:none;cursor:pointer;font-family:var(--sans);background:var(--bg);color:var(--muted);transition:.15s}
.umt-btn.active{background:var(--navy);color:#fff}

/* Cover image section in property modal */
.cover-section{background:#fafbfc;border:1px solid var(--border);border-radius:8px;padding:14px;margin-bottom:4px}
.cover-section-title{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--ink);margin-bottom:12px}
.cover-current{display:flex;align-items:center;gap:12px;margin-bottom:12px;padding:8px;background:var(--white);border:1px solid var(--border);border-radius:6px}
.cover-current img{width:64px;height:48px;object-fit:cover;border-radius:4px;flex-shrink:0}
.cover-current-info{font-size:11px;color:var(--muted)}
.cover-current-info strong{color:var(--ink);display:block;margin-bottom:2px}

/* MODAL */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;display:none;align-items:center;justify-content:center}
.modal-overlay.open{display:flex}
.modal-box{background:#fff;border-radius:14px;width:560px;max-width:95vw;max-height:90vh;overflow-y:auto;box-shadow:0 24px 60px rgba(0,0,0,.18)}
.modal-head{padding:20px 24px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:#fff;z-index:10;border-radius:14px 14px 0 0}
.modal-title{font-size:16px;font-weight:700;color:var(--ink)}
.modal-close{width:30px;height:30px;border-radius:50%;border:1px solid var(--border);background:none;cursor:pointer;font-size:16px;color:var(--muted);display:flex;align-items:center;justify-content:center;line-height:1}
.modal-close:hover{background:var(--bg)}
.modal-body{padding:22px 24px}
.form-field{margin-bottom:16px}
.form-field label{display:block;font-size:11px;font-weight:700;margin-bottom:5px;color:var(--ink);text-transform:uppercase;letter-spacing:.4px}
.form-field input,.form-field textarea,.form-field select{width:100%;border:1px solid var(--border);border-radius:7px;padding:9px 12px;font-family:var(--sans);font-size:13px;color:var(--ink);outline:none}
.form-field input:focus,.form-field textarea:focus,.form-field select:focus{border-color:var(--navy)}
.form-field textarea{resize:vertical;min-height:80px;line-height:1.5}
.form-row{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px}
.form-row-2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.modal-footer{padding:16px 24px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:10px;align-items:center;position:sticky;bottom:0;background:#fff;border-radius:0 0 14px 14px}
.btn-cancel{padding:8px 16px;border:1px solid var(--border);border-radius:7px;background:none;cursor:pointer;font-family:var(--sans);font-size:12px;font-weight:600;color:var(--muted)}
.btn-save{padding:8px 18px;border:none;border-radius:7px;background:var(--navy);color:#fff;cursor:pointer;font-family:var(--sans);font-size:12px;font-weight:700}
.btn-save:hover{background:var(--navy-dk)}
.btn-delete{padding:8px 16px;border:1px solid #fee2e2;border-radius:7px;background:none;cursor:pointer;font-family:var(--sans);font-size:12px;font-weight:600;color:#ef4444;margin-right:auto}
.btn-delete:hover{background:#fff5f5}

/* SETTINGS */
.settings-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;padding:24px}
.settings-field label{font-size:13px;font-weight:700;display:block;margin-bottom:6px}
.settings-field input{width:100%;height:40px;border:1px solid var(--border);border-radius:6px;padding:0 12px;font-family:var(--sans);font-size:13px;color:var(--ink)}

/* TOAST */
.toast-bar{position:fixed;bottom:24px;right:24px;background:var(--navy);color:#fff;padding:12px 20px;border-radius:10px;font-size:12px;font-weight:600;z-index:9999;animation:slideUp .3s ease;box-shadow:0 4px 20px rgba(0,0,0,.15);display:flex;align-items:center;gap:8px}
.toast-bar.err{background:#ef4444}
@keyframes slideUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}

.status-select{font-size:10px;padding:3px 6px;border:1px solid var(--border);border-radius:6px;font-family:var(--sans)}
.hamburger-btn{display:none}

.form-field input:invalid,.form-field textarea:invalid,.form-field select:invalid{border-color:#ef4444}
.form-invalid input:invalid,.form-invalid textarea:invalid,.form-invalid select:invalid{box-shadow:0 0 0 3px rgba(239,68,68,.18)}

@media(max-width:900px){
  .hamburger-btn{display:flex;align-items:center;justify-content:center;width:38px;height:38px;border:1px solid var(--border);border-radius:10px;background:var(--white);cursor:pointer;flex-shrink:0}
  .hamburger-btn svg{width:18px;height:18px;fill:var(--navy)}
  .sidebar{transform:translateX(-110%);transition:transform .3s;position:fixed;top:0;left:0;bottom:0;z-index:300;width:260px;box-shadow:8px 0 24px rgba(0,0,0,.12)}
  .sidebar.open{transform:translateX(0)}
  .sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:250;opacity:0;pointer-events:none;transition:opacity .3s}
  .sidebar-overlay.open{display:block;opacity:1;pointer-events:auto}
  .main{margin-left:0}
  .topbar{padding:0 18px;gap:12px}
  .topbar-greeting{flex:1;min-width:0}
  .topbar-greeting h2{font-size:18px}
  .topbar-right{gap:10px}
  .page-content{padding:16px;gap:18px}
  .content-left{width:100%}
  .content-right{display:none}
  .page-title-row{flex-direction:column;align-items:flex-start;gap:12px}
  .page-title-row > div{width:100%}
  .stat-grid,.status-grid,.agents-grid{grid-template-columns:1fr}
  .overview-card{flex-direction:column}
  .overview-img{width:100%}
  .overview-row{flex-wrap:wrap}
  .filter-bar{padding:12px;gap:10px}
  .table-scroll{overflow-x:auto}
  .data-table{min-width:760px}
  .form-row,.form-row-2{grid-template-columns:1fr}
  .modal-box{width:100%;max-width:100vw;border-radius:0}
  .modal-footer{padding:14px}
}

@media(max-width:600px){
  .topbar{padding:0 12px}
  .page-content{padding:14px;gap:14px}
  .filter-input{min-width:0;width:100%}
  .section-card,.stat-card,.overview-card{padding:16px}
  .hamburger-btn{width:36px;height:36px}
}
</style>
</head>
<body>
<div class="sidebar-overlay" onclick="closeSidebar()"></div>

<?php if ($toast): ?>
<div class="toast-bar <?= $toast_err ? 'err' : '' ?>">
  <?= $toast_err ? '✕' : '✓' ?> <?= h($toast) ?>
</div>
<script>setTimeout(()=>document.querySelector('.toast-bar')?.remove(),3500)</script>
<?php endif; ?>

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="logo-icon"><svg viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg></div>
    <div class="logo-text">dwelro</div>
  </div>
  <nav class="sidebar-nav">
    <?php
    $current_section = '';
    foreach ($nav as $key => $item):
        if ($item['section'] && $item['section'] !== $current_section):
            $current_section = $item['section'];
    ?>
    <div class="nav-section-label"><?= h($current_section) ?></div>
    <?php endif; ?>
    <a class="nav-item <?= $page === $key ? 'active' : '' ?>" href="admin.php?page=<?= $key ?>">
      <span class="nav-icon"><svg viewBox="0 0 24 24"><?= $nav_icons[$key] ?? '' ?></svg></span>
      <?= h($item['label']) ?>
      <?php if ($key === 'enquiries'): ?>
        <span class="nav-badge"><?= $enq_open ?></span>
      <?php endif; ?>
    </a>
    <?php endforeach; ?>
  </nav>
  <div class="sidebar-footer">
    <form method="POST" style="margin:0;">
      <input type="hidden" name="_action" value="logout"/>
      <input type="hidden" name="_page" value="<?= $page ?>"/>
      <button type="submit" class="logout-btn">
        <svg viewBox="0 0 24 24"><path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/></svg>
        Logout
      </button>
    </form>
  </div>
</aside>

<!-- MAIN -->
<div class="main">

  <!-- TOPBAR -->
  <div class="topbar">
    <button type="button" class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle menu">
      <svg viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
    </button>
    <div class="topbar-greeting">
      <h2><?= h($greeting) ?>, <span>Dwelro Admin!</span></h2>
      <p><?= h($tip) ?></p>
    </div>
    <div class="topbar-right">
      <div class="top-date">
        <svg viewBox="0 0 24 24"><path d="M20 3h-1V1h-2v2H7V1H5v2H4c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 18H4V8h16v13z"/></svg>
        <?= h($date_str) ?>
      </div>
      <div class="top-profile">
        <div class="top-profile-info">
          <div class="top-profile-name"><?= h($_SESSION['admin_name']) ?> <span class="online-dot"></span></div>
          <div class="top-profile-role"><?= h(ucfirst($_SESSION['admin_role'])) ?></div>
        </div>
        <div class="top-avatar" onclick="openProfileModal()" style="cursor:pointer;">
          <div style="width:40px;height:40px;border-radius:50%;background:#1a3a5c;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:16px;">
            <?= strtoupper(substr($_SESSION['admin_name'], 0, 1)) ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="page-content">
    <div class="content-left">

    <?php if ($page === 'dashboard'): ?>
    <!-- ══════════════════════════════════════════════════════════════════════
         DASHBOARD
    ══════════════════════════════════════════════════════════════════════ -->
    <div class="page-title-row">
      <div class="page-title">Overview</div>
      <div style="display:flex;gap:10px">
        <input class="filter-input" id="global-search" placeholder="Search properties, enquiries…" oninput="globalSearch()" style="width:250px"/>
        <button class="add-btn" onclick="clearGlobalSearch()">Clear</button>
      </div>
    </div>

    <div id="search-results" style="display:none;margin-bottom:20px">
      <div class="section-card">
        <div class="section-head">
          <div class="section-head-title">Search Results</div>
          <div class="section-head-sub" id="search-count">0 results</div>
        </div>
        <div id="search-list"></div>
      </div>
    </div>

    <div class="stat-grid">
      <div class="stat-card">
        <div class="stat-label">Total Properties</div>
        <div class="stat-val"><?= $total_props ?></div>
        <div class="stat-sub"><span class="stat-trend trend-up"><?= $published ?> published, <?= $draft ?> draft</span></div>
        <a class="stat-link" href="admin.php?page=properties">View More &rarr;</a>
      </div>
      <div class="stat-card">
        <div class="stat-label">Total Agents</div>
        <div class="stat-val"><?= $total_agents ?></div>
        <div class="stat-sub"><span class="stat-trend trend-up">Active agents</span></div>
        <a class="stat-link" href="admin.php?page=agents">View More &rarr;</a>
      </div>
      <div class="stat-card">
        <div class="stat-label">Enquiries Open</div>
        <div class="stat-val"><?= $enq_open ?></div>
        <div class="stat-sub"><span class="stat-trend trend-up">New &amp; contacted</span></div>
        <a class="stat-link" href="admin.php?page=enquiries">View More &rarr;</a>
      </div>
    </div>

    <div class="overview-card">
      <div class="overview-img">
        <img src="https://images.unsplash.com/photo-1512917774080-9991f1c4c750?w=600&auto=format&fit=crop" alt=""/>
      </div>
      <div class="overview-details">
        <div class="overview-title">General Overview</div>
        <div class="overview-row"><svg viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg> Active Listings &mdash; <strong><?= $available ?></strong></div>
        <div class="overview-row"><svg viewBox="0 0 24 24"><path d="M20 3h-1V1h-2v2H7V1H5v2H4c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 18H4V8h16v13z"/></svg> Last Updated &mdash; <strong><?= h($date_str) ?></strong></div>
        <div class="overview-row"><svg viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg> Published / Total &mdash; <strong><?= $published ?> / <?= $total_props ?></strong></div>
        <div class="overview-row"><svg viewBox="0 0 24 24"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5z"/></svg> Total Views &mdash; <strong><?= fmt_num($total_views) ?></strong></div>
        <div class="overview-row"><svg viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg> Newsletter Subscribers &mdash; <strong><?= $nl_subs ?></strong></div>
        <div class="overview-row"><svg viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg> Testimonials Live &mdash; <strong><?= $testi_live ?></strong></div>
      </div>
    </div>

    <div class="section-card">
      <div class="section-head">
        <div><div class="section-head-title">Listing Status Breakdown</div>
        <div class="section-head-sub">All properties including drafts</div></div>
        <a class="overview-link" href="admin.php?page=properties">View All</a>
      </div>
      <div class="status-grid">
        <div class="status-cell"><div class="status-cell-label">Available</div><span class="status-badge badge-ok"><?= $available ?> Active</span><div class="status-cell-meta">Live listings</div></div>
        <div class="status-cell"><div class="status-cell-label">Featured</div><span class="status-badge badge-ok"><?= $featured ?> Pinned</span><div class="status-cell-meta">Homepage pinned</div></div>
        <div class="status-cell"><div class="status-cell-label">Published</div><span class="status-badge badge-ok"><?= $published ?> Live</span><div class="status-cell-meta">Live on site</div></div>
        <div class="status-cell"><div class="status-cell-label">Draft / Unpublished</div><span class="status-badge badge-warn"><?= $draft ?> Drafts</span><div class="status-cell-meta">Not on site yet</div></div>
        <div class="status-cell"><div class="status-cell-label">Sold / Under Offer</div><span class="status-badge badge-na"><?= $sold ?> / <?= $under_offer ?></span><div class="status-cell-meta">Closed / pending</div></div>
      </div>
    </div>

    <div class="section-card">
      <div class="section-head">
        <div><div class="section-head-title">Enquiry Pipeline</div>
        <div class="section-head-sub">All open enquiry stages</div></div>
        <a class="overview-link" href="admin.php?page=enquiries">View All</a>
      </div>
      <div class="status-grid">
        <div class="status-cell"><div class="status-cell-label">New</div><span class="status-badge badge-err"><?= $enq_new ?></span><div class="status-cell-meta">Needs action</div></div>
        <div class="status-cell"><div class="status-cell-label">Contacted</div><span class="status-badge badge-warn"><?= $enq_contacted ?></span><div class="status-cell-meta">Follow up</div></div>
        <div class="status-cell"><div class="status-cell-label">Scheduled</div><span class="status-badge badge-ok"><?= $enq_scheduled ?></span><div class="status-cell-meta">Viewings booked</div></div>
        <div class="status-cell"><div class="status-cell-label">Closed</div><span class="status-badge badge-na"><?= $enq_closed ?></span><div class="status-cell-meta">Deals closed</div></div>
        <div class="status-cell"><div class="status-cell-label">Lost</div><span class="status-badge badge-na"><?= $enq_lost ?></span><div class="status-cell-meta">No further action</div></div>
      </div>
    </div>

    <?php elseif ($page === 'properties'): ?>
    <!-- ══════════════════════════════════════════════════════════════════════
         PROPERTIES
    ══════════════════════════════════════════════════════════════════════ -->
    <div class="page-title-row">
      <div class="page-title">Properties</div>
      <button class="add-btn" onclick="openPropertyModal(null)">+ Add Property</button>
    </div>

    <div class="section-card">
      <div class="section-head">
        <div>
          <div class="section-head-title">All Listings</div>
          <div class="section-head-sub">
            <?= count($properties) ?> total &mdash;
            <span style="color:#16a34a;font-weight:700"><?= $published ?> published</span> &mdash;
            <span style="color:#d97706;font-weight:700"><?= $draft ?> draft</span>
          </div>
        </div>
      </div>
      <!-- Bulk Actions Bar -->
      <div class="filter-bar" id="bulk-bar" style="display:none;background:#fff5f5;border-color:#fca5a5">
        <span class="filter-label" style="color:#dc2626">Bulk Actions:</span>
        <button class="action-btn" style="color:#dc2626;border-color:#fca5a5" onclick="bulkAction('delete')">Delete Selected</button>
        <button class="action-btn" onclick="bulkAction('publish', 1)">Publish Selected</button>
        <button class="action-btn" onclick="bulkAction('publish', 0)">Unpublish Selected</button>
        <button class="action-btn" onclick="bulkAction('assign')">Assign Agent</button>
        <button class="action-btn" style="margin-left:auto;color:#6b7280" onclick="clearSelection()">Clear Selection</button>
      </div>
      <div class="filter-bar">
        <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap">
          <input type="hidden" name="page" value="properties"/>
          <span class="filter-label">Filter:</span>
          <select name="f_status" class="filter-select" onchange="this.form.submit()">
            <option value="">All statuses</option>
            <option value="available" <?= $prop_status === 'available' ? 'selected' : '' ?>>Available</option>
            <option value="sold" <?= $prop_status === 'sold' ? 'selected' : '' ?>>Sold</option>
            <option value="under_offer" <?= $prop_status === 'under_offer' ? 'selected' : '' ?>>Under Offer</option>
            <option value="rented" <?= $prop_status === 'rented' ? 'selected' : '' ?>>Rented</option>
          </select>
          <select name="f_pub" class="filter-select" onchange="this.form.submit()">
            <option value="">Published + Draft</option>
            <option value="1" <?= $prop_pub === '1' ? 'selected' : '' ?>>Published only</option>
            <option value="0" <?= $prop_pub === '0' ? 'selected' : '' ?>>Drafts only</option>
          </select>
          <select name="f_cat" class="filter-select" onchange="this.form.submit()">
            <option value="">All categories</option>
            <option value="condo" <?= $prop_cat === 'condo' ? 'selected' : '' ?>>Condo</option>
            <option value="landed" <?= $prop_cat === 'landed' ? 'selected' : '' ?>>Landed</option>
            <option value="hdb" <?= $prop_cat === 'hdb' ? 'selected' : '' ?>>HDB</option>
            <option value="commercial" <?= $prop_cat === 'commercial' ? 'selected' : '' ?>>Commercial</option>
            <option value="overseas" <?= $prop_cat === 'overseas' ? 'selected' : '' ?>>Overseas</option>
            <option value="new_dev" <?= $prop_cat === 'new_dev' ? 'selected' : '' ?>>New Dev</option>
            <option value="investment" <?= $prop_cat === 'investment' ? 'selected' : '' ?>>Investment</option>
          </select>
          <input name="f_search" class="filter-input" placeholder="Search title or area…" value="<?= h($prop_search) ?>" onkeydown="if(event.key==='Enter')this.form.submit()"/>
        </form>
      </div>
      <div class="table-scroll">
      <table class="data-table">
        <thead><tr>
          <th style="width:40px"><input type="checkbox" id="select-all" onchange="toggleAll()"/></th>
          <th>Property</th><th>Category</th><th>Type</th><th>Status</th>
          <th>Price</th><th>Agent</th><th>Featured</th><th>Published</th><th>Actions</th>
        </tr></thead>
        <tbody id="prop-tbody">
        <?php foreach ($properties as $p): ?>
        <tr>
          <td><input type="checkbox" class="prop-checkbox" value="<?= (int)$p['id'] ?>" onchange="updateBulkBar()"/></td>
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              <img class="prop-thumb"
                src="<?= h($p['cover_image_url'] ?: 'https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?w=80&auto=format&fit=crop') ?>"
                onerror="this.src='https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?w=80&auto=format&fit=crop'" alt=""/>
              <div>
                <div class="prop-name"><?= h($p['title']) ?></div>
                <div class="prop-sub"><?= h(($p['district'] ? $p['district'] . ' · ' : '') . $p['area']) ?></div>
              </div>
            </div>
          </td>
          <td><?= tag($p['category'], str_replace('_', ' ', $p['category'])) ?></td>
          <td><?= tag($p['listing_type'], $p['listing_type']) ?></td>
          <td><?= tag($p['status'], str_replace('_', ' ', $p['status'])) ?></td>
          <td><strong><?= h($p['price_display']) ?></strong></td>
          <td><?= h($p['agent_name'] ?? '—') ?></td>
          <td style="color:var(--gold)"><?= $p['is_featured'] == 1 ? '★' : '—' ?></td>
          <td>
            <form method="POST" style="display:inline">
              <input type="hidden" name="_action" value="toggle_published"/>
              <input type="hidden" name="_page" value="properties"/>
              <input type="hidden" name="id" value="<?= (int)$p['id'] ?>"/>
              <input type="hidden" name="is_published" value="<?= $p['is_published'] == 1 ? 0 : 1 ?>"/>
              <button type="submit" class="tag <?= $p['is_published'] == 1 ? 'tag-published' : 'tag-draft' ?>"
                style="border:none;cursor:pointer;font-family:var(--sans)">
                <?= $p['is_published'] == 1 ? 'Published' : 'Draft' ?>
              </button>
            </form>
          </td>
          <td>
            <div style="display:flex;gap:6px;flex-wrap:nowrap">
              <button class="action-btn"
                onclick="openPropertyModal(<?= htmlspecialchars(json_encode($p), ENT_QUOTES) ?>)">
                Edit
              </button>
              <button class="action-btn" style="color:#0369a1;border-color:#bae6fd;white-space:nowrap"
                onclick="openVideoModal(<?= (int)$p['id'] ?>, '<?= h(addslashes($p['title'])) ?>', '<?= h(addslashes($p['video_url'] ?? '')) ?>')">
                ▶ Video
              </button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php if ($total_pages > 1): ?>
      <div style="margin-top:20px;text-align:center">
        <?php if ($page_num > 1): ?>
        <a href="admin.php?page=properties&p=<?= $page_num - 1 ?>" class="action-btn">← Previous</a>
        <?php endif; ?>
        Page <?= $page_num ?> of <?= $total_pages ?>
        <?php if ($page_num < $total_pages): ?>
        <a href="admin.php?page=properties&p=<?= $page_num + 1 ?>" class="action-btn">Next →</a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- ── PROPERTY MODAL ────────────────────────────────────────────────── -->
    <div class="modal-overlay" id="prop-modal" onclick="if(event.target===this)closeModal('prop-modal')">
      <div class="modal-box" style="width:640px">
        <div class="modal-head">
          <div class="modal-title" id="prop-modal-title">Property</div>
          <button class="modal-close" onclick="closeModal('prop-modal')">&times;</button>
        </div>
        <!-- multipart/form-data is required for file uploads -->
        <form method="POST" enctype="multipart/form-data" id="propForm">
          <input type="hidden" name="_action" value="save_property"/>
          <input type="hidden" name="_page" value="properties"/>
          <input type="hidden" name="id" id="p-id"/>
          <div class="modal-body">

            <div class="form-field">
              <label>Title *</label>
              <input name="title" id="p-title" type="text" placeholder="e.g. Luxury Condo at Orchard" required/>
            </div>

            <div class="form-field">
              <label>Slug</label>
              <input name="slug" id="p-slug" type="text" placeholder="e.g. luxury-condo-at-orchard" oninput="updateSlugPreview()"/>
              <div style="font-size:11px;color:var(--muted);margin-top:2px">Leave blank to auto-generate from title</div>
            </div>

            <div class="form-row">
              <div class="form-field">
                <label>Category</label>
                <select name="category" id="p-category">
                  <option value="condo">Condo</option>
                  <option value="landed">Landed</option>
                  <option value="hdb">HDB</option>
                  <option value="commercial">Commercial</option>
                  <option value="overseas">Overseas</option>
                  <option value="new_dev">New Dev</option>
                  <option value="investment">Investment</option>
                </select>
              </div>
              <div class="form-field">
                <label>Listing Type</label>
                <select name="listing_type" id="p-listing-type">
                  <option value="sale">Sale</option>
                  <option value="rent">Rent</option>
                  <option value="both">Sale & Rent</option>
                </select>
              </div>
              <div class="form-field">
                <label>Status</label>
                <select name="status" id="p-status">
                  <option value="available">Available</option>
                  <option value="under_offer">Under Offer</option>
                  <option value="sold">Sold</option>
                  <option value="rented">Rented</option>
                </select>
              </div>
            </div>

            <div class="form-field">
              <label>Address</label>
              <input name="address" id="p-address" type="text" placeholder="e.g. 221B Baker Street"/>
            </div>

            <div class="form-row">
              <div class="form-field">
                <label>Property Type</label>
                <input name="property_type" id="p-property-type" type="text" placeholder="e.g. Townhouse, Apartment"/>
              </div>
              <div class="form-field">
                <label>Badge</label>
                <input name="badge" id="p-badge" type="text" placeholder="e.g. Premium, New Listing"/>
              </div>
              <div class="form-field">
                <label>Price Display</label>
                <input name="price_display" id="p-price" type="text" placeholder="e.g. $1,200,000"/>
              </div>
            </div>

            <div class="form-row">
              <div class="form-field">
                <label>Price SGD</label>
                <input name="price_sgd" id="p-price-sgd" type="number" min="0" placeholder="0"/>
              </div>
              <div class="form-field">
                <label>Price PSF</label>
                <input name="price_psf" id="p-price-psf" type="text" placeholder="e.g. 1,800"/>
              </div>
              <div class="form-field">
                <label>Rental PCM</label>
                <input name="rental_pcm" id="p-rental-pcm" type="number" min="0" placeholder="0"/>
              </div>
            </div>

            <div class="form-row">
              <div class="form-field">
                <label>District</label>
                <input name="district" id="p-district" type="text" placeholder="e.g. D10"/>
              </div>
              <div class="form-field">
                <label>Area / Neighbourhood</label>
                <input name="area" id="p-area" type="text" placeholder="e.g. Orchard"/>
              </div>
              <div class="form-field">
                <label>Land Area (sqft)</label>
                <input name="land_area_sqft" id="p-land-area" type="number" min="0" placeholder="0"/>
              </div>
            </div>

            <div class="form-row">
              <div class="form-field">
                <label>Floor Level</label>
                <input name="floor_level" id="p-floor-level" type="text" placeholder="e.g. 12th floor"/>
              </div>
              <div class="form-field">
                <label>Built Year</label>
                <input name="built_year" id="p-built-year" type="number" min="1800" max="2100" placeholder="2024"/>
              </div>
              <div class="form-field">
                <label>Tenure</label>
                <select name="tenure" id="p-tenure">
                  <option value="freehold">Freehold</option>
                  <option value="99_year">99 Year</option>
                  <option value="999_year">999 Year</option>
                  <option value="leasehold">Leasehold</option>
                  <option value="other">Other</option>
                </select>
              </div>
            </div>

            <div class="form-row">
              <div class="form-field">
                <label>Furnishing</label>
                <select name="furnishing" id="p-furnishing">
                  <option value="unfurnished">Unfurnished</option>
                  <option value="partial">Partial</option>
                  <option value="fully_furnished">Fully Furnished</option>
                </select>
              </div>
              <div class="form-field">
                <label>Virtual Tour URL</label>
                <input name="virtual_tour_url" id="p-virtual-tour-url" type="url" placeholder="https://"/>
              </div>
              <div class="form-field">
                <label>Floor Plan URL</label>
                <input name="floor_plan_url" id="p-floor-plan-url" type="url" placeholder="https://"/>
              </div>
            </div>

            <div class="form-field">
              <label>Features</label>
              <input name="features" id="p-features" type="text" placeholder="Pool, Concierge, Parking"/>
            </div>

            <div class="form-field">
              <label>Meta Title</label>
              <input name="meta_title" id="p-meta-title" type="text" placeholder="Page title for SEO"/>
            </div>

            <div class="form-field">
              <label>Meta Description</label>
              <textarea name="meta_desc" id="p-meta-desc" placeholder="SEO description…" style="min-height:72px"></textarea>
            </div>

            <div class="form-row">
              <div class="form-field">
                <label>Bedrooms</label>
                <input name="bedrooms" id="p-beds" type="number" min="0" placeholder="0"/>
              </div>
              <div class="form-field">
                <label>Bathrooms</label>
                <input name="bathrooms" id="p-baths" type="number" min="0" placeholder="0"/>
              </div>
              <div class="form-field">
                <label>Floor Area (sqft)</label>
                <input name="floor_area_sqft" id="p-sqft" type="number" min="0" placeholder="0"/>
              </div>
            </div>

            <div class="form-row-2">
              <div class="form-field">
                <label>Country</label>
                <input name="country" id="p-country" type="text" placeholder="United Kingdom"/>
              </div>
              <div class="form-field">
                <label>Agent</label>
                <select name="agent_id" id="p-agent">
                  <option value="">— No agent assigned —</option>
                  <?php foreach ($agents_list as $ag): ?>
                  <option value="<?= (int)$ag['id'] ?>"><?= h($ag['full_name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div class="form-field">
              <label>Description</label>
              <textarea name="description" id="p-desc" placeholder="Property description…" style="min-height:100px"></textarea>
            </div>

            <!-- ── COVER IMAGE ─────────────────────────────────────────── -->
            <div class="cover-section">
              <div class="cover-section-title">Cover Image</div>

              <!-- Current image preview (shown when editing) -->
              <div class="cover-current" id="p-cover-current" style="display:none">
                <img id="p-cover-current-img" src="" alt="Current cover"/>
                <div class="cover-current-info">
                  <strong>Current Cover</strong>
                  Leave both fields blank to keep the existing cover.
                </div>
              </div>

              <!-- Upload method tabs -->
              <div class="upload-method-tabs">
                <button type="button" class="umt-btn active" id="p-tab-upload" onclick="switchCoverTab('upload')">📁 Upload File</button>
                <button type="button" class="umt-btn" id="p-tab-url" onclick="switchCoverTab('url')">🔗 Paste URL</button>
              </div>

              <!-- File upload panel -->
              <div id="p-panel-upload">
                <img id="p-cover-upload-preview" class="upload-preview" src="" alt="Preview"/>
                <div class="upload-zone" id="p-uploadZone">
                  <input type="file" name="cover_image_file" id="p-cover-file"
                    accept="image/jpeg,image/png,image/gif,image/webp"
                    onchange="previewCoverFile(this)"/>
                  <span class="upload-zone-icon">🖼️</span>
                  <div class="upload-zone-title">Click to choose or drag &amp; drop</div>
                  <div class="upload-zone-sub">JPG, PNG, GIF or WebP · Max 10 MB · Optional</div>
                </div>
                <div id="p-cover-filename" style="font-size:11px;color:var(--navy);font-weight:600;margin-top:8px;display:none"></div>
              </div>

              <!-- URL panel -->
              <div id="p-panel-url" style="display:none">
                <div class="form-field" style="margin-bottom:8px">
                  <label>Image URL</label>
                  <input type="text" name="cover_image_url" id="p-cover-url"
                    placeholder="https://example.com/cover.jpg"
                    oninput="previewCoverUrl(this.value)"/>
                </div>
                <img id="p-cover-url-preview" class="upload-preview" src="" alt="Preview"/>
              </div>
            </div>
            <!-- ── /COVER IMAGE ────────────────────────────────────────── -->

            <div style="display:flex;align-items:center;gap:10px;margin-top:12px">
              <input type="checkbox" name="is_featured" id="p-featured" value="1"
                style="width:auto;height:auto;accent-color:var(--navy)"/>
              <label for="p-featured" style="font-size:13px;font-weight:600;cursor:pointer;text-transform:none;letter-spacing:0">
                Feature this property on homepage
              </label>
            </div>

          </div>
          <div class="modal-footer">
            <button type="button" class="btn-delete" id="p-delete-btn" style="display:none"
              onclick="submitDelete('property', document.getElementById('p-id').value)">
              Delete Property
            </button>
            <button type="button" class="btn-cancel" onclick="closeModal('prop-modal')">Cancel</button>
            <button type="submit" class="btn-save">Save Property</button>
          </div>
        </form>
      </div>
    </div>

    <!-- ── VIDEO MODAL ───────────────────────────────────────────────────── -->
    <div class="modal-overlay" id="video-modal" onclick="if(event.target===this)closeModal('video-modal')">
      <div class="modal-box">
        <div class="modal-head">
          <div class="modal-title">Video — <span id="vid-prop-name" style="color:var(--navy);font-size:13px;font-family:var(--sans)"></span></div>
          <button class="modal-close" onclick="closeModal('video-modal')">&times;</button>
        </div>
        <form method="POST">
          <input type="hidden" name="_action" value="save_video"/>
          <input type="hidden" name="_page" value="properties"/>
          <input type="hidden" name="id" id="vid-id"/>
          <div class="modal-body">
            <div class="form-field">
              <label>YouTube or Vimeo URL</label>
              <input type="text" name="video_url" id="vid-url" placeholder="https://www.youtube.com/watch?v=…"/>
            </div>
            <p style="font-size:11px;color:var(--muted)">Supports YouTube and Vimeo. Leave blank to remove the video.</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn-cancel" onclick="closeModal('video-modal')">Cancel</button>
            <button type="submit" class="btn-save">Save Video</button>
          </div>
        </form>
      </div>
    </div>

    <script>
    // ── Bulk actions ─────────────────────────────────────────────────────
    function toggleAll() {
      const checked = document.getElementById('select-all').checked;
      document.querySelectorAll('.prop-checkbox').forEach(cb => cb.checked = checked);
      updateBulkBar();
    }

    function updateBulkBar() {
      const checked = document.querySelectorAll('.prop-checkbox:checked');
      document.getElementById('bulk-bar').style.display = checked.length ? 'flex' : 'none';
    }

    function clearSelection() {
      document.querySelectorAll('.prop-checkbox').forEach(cb => cb.checked = false);
      document.getElementById('select-all').checked = false;
      updateBulkBar();
    }

    function bulkAction(action, param) {
      const ids = Array.from(document.querySelectorAll('.prop-checkbox:checked')).map(cb => cb.value);
      if (!ids.length) return;

      if (action === 'delete' && !confirm(`Delete ${ids.length} properties? This cannot be undone.`)) return;

      if (action === 'assign') {
        openBulkAssignModal(ids);
        return;
      }

      const form = document.createElement('form');
      form.method = 'POST';
      form.innerHTML = `
        <input name="_action" value="bulk_${action}_property"/>
        <input name="_page" value="properties"/>
        ${ids.map(id => `<input name="ids[]" value="${id}"/>`).join('')}
        ${param !== undefined ? `<input name="is_published" value="${param}"/>` : ''}
      `;
      document.body.appendChild(form);
      form.submit();
    }

    function openBulkAssignModal(ids) {
      // Create modal for agent selection
      const modal = document.createElement('div');
      modal.className = 'modal-overlay open';
      modal.innerHTML = `
        <div class="modal-box">
          <div class="modal-head">
            <div class="modal-title">Assign Agent to ${ids.length} Properties</div>
            <button class="modal-close" onclick="this.closest('.modal-overlay').remove()">&times;</button>
          </div>
          <form method="POST">
            <input type="hidden" name="_action" value="bulk_assign_agent"/>
            <input type="hidden" name="_page" value="properties"/>
            ${ids.map(id => `<input name="ids[]" value="${id}"/>`).join('')}
            <div class="modal-body">
              <div class="form-field">
                <label>Select Agent</label>
                <select name="agent_id" required>
                  <option value="">— Choose agent —</option>
                  <?php foreach ($agents_list as $ag): ?>
                  <option value="<?= (int)$ag['id'] ?>"><?= h($ag['full_name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn-cancel" onclick="this.closest('.modal-overlay').remove()">Cancel</button>
              <button type="submit" class="btn-save">Assign Agent</button>
            </div>
          </form>
        </div>
      `;
      document.body.appendChild(modal);
    }

    // ── Cover image tabs ──────────────────────────────────────────────────
    let activeCoverTab = 'upload';

    function switchCoverTab(tab) {
      activeCoverTab = tab;
      document.getElementById('p-panel-upload').style.display = tab === 'upload' ? '' : 'none';
      document.getElementById('p-panel-url').style.display    = tab === 'url'    ? '' : 'none';
      document.getElementById('p-tab-upload').classList.toggle('active', tab === 'upload');
      document.getElementById('p-tab-url').classList.toggle('active', tab === 'url');
      if (tab === 'upload') {
        // Clear URL field when switching to upload
        document.getElementById('p-cover-url').value = '';
        document.getElementById('p-cover-url-preview').style.display = 'none';
      }
    }

    function updateSlugPreview() {
      // Optional: could add live preview, but for now just ensure it's valid
    }

    function previewCoverFile(input) {
      const file = input.files[0];
      if (!file) return;
      const prev = document.getElementById('p-cover-upload-preview');
      const fn   = document.getElementById('p-cover-filename');
      prev.src = URL.createObjectURL(file);
      prev.style.display = 'block';
      fn.textContent = '📎 ' + file.name + ' (' + (file.size / 1024 / 1024).toFixed(2) + ' MB)';
      fn.style.display = 'block';
      document.getElementById('p-uploadZone').style.display = 'none';
    }

    function previewCoverUrl(url) {
      const prev = document.getElementById('p-cover-url-preview');
      if (url) { prev.src = url; prev.style.display = 'block'; }
      else { prev.style.display = 'none'; }
    }

    // Drag-and-drop for cover upload zone
    const pZone = document.getElementById('p-uploadZone');
    if (pZone) {
      pZone.addEventListener('dragover',  e => { e.preventDefault(); pZone.classList.add('drag-over'); });
      pZone.addEventListener('dragleave', () => pZone.classList.remove('drag-over'));
      pZone.addEventListener('drop',      e => {
        e.preventDefault();
        pZone.classList.remove('drag-over');
        const dt = e.dataTransfer;
        if (dt && dt.files.length) {
          document.getElementById('p-cover-file').files = dt.files;
          previewCoverFile(document.getElementById('p-cover-file'));
        }
      });
    }

    // ── Open Property Modal ───────────────────────────────────────────────
    function openPropertyModal(p) {
      // Reset cover section
      document.getElementById('propForm').reset();
      document.getElementById('p-cover-upload-preview').style.display  = 'none';
      document.getElementById('p-cover-url-preview').style.display     = 'none';
      document.getElementById('p-cover-filename').style.display        = 'none';
      document.getElementById('p-uploadZone').style.display            = '';
      switchCoverTab('upload');

      document.getElementById('prop-modal-title').textContent  = p ? 'Edit Property' : 'Add Property';
      document.getElementById('p-delete-btn').style.display    = p ? 'block' : 'none';
      document.getElementById('p-id').value           = p ? p.id : '';
      document.getElementById('p-title').value        = p ? p.title : '';
      document.getElementById('p-slug').value         = p ? p.slug : '';
      document.getElementById('p-category').value     = p ? p.category : 'condo';
      document.getElementById('p-listing-type').value = p ? p.listing_type : 'sale';
      document.getElementById('p-status').value       = p ? p.status : 'available';
      document.getElementById('p-badge').value        = p ? (p.badge || '') : '';
      document.getElementById('p-price').value        = p ? (p.price_display || '') : '';
      document.getElementById('p-price-sgd').value    = p ? (p.price_sgd || '') : '';
      document.getElementById('p-price-psf').value    = p ? (p.price_psf || '') : '';
      document.getElementById('p-rental-pcm').value   = p ? (p.rental_pcm || '') : '';
      document.getElementById('p-district').value     = p ? (p.district || '') : '';
      document.getElementById('p-area').value         = p ? (p.area || '') : '';
      document.getElementById('p-address').value      = p ? (p.address || '') : '';
      document.getElementById('p-land-area').value    = p ? (p.land_area_sqft || '') : '';
      document.getElementById('p-floor-level').value  = p ? (p.floor_level || '') : '';
      document.getElementById('p-built-year').value   = p ? (p.built_year || '') : '';
      document.getElementById('p-property-type').value= p ? (p.property_type || '') : '';
      document.getElementById('p-tenure').value       = p ? (p.tenure || 'other') : 'other';
      document.getElementById('p-furnishing').value   = p ? (p.furnishing || 'unfurnished') : 'unfurnished';
      document.getElementById('p-virtual-tour-url').value = p ? (p.virtual_tour_url || '') : '';
      document.getElementById('p-floor-plan-url').value   = p ? (p.floor_plan_url || '') : '';
      document.getElementById('p-meta-title').value   = p ? (p.meta_title || '') : '';
      document.getElementById('p-meta-desc').value    = p ? (p.meta_desc || '') : '';
      let featuresValue = '';
      if (p && p.features) {
        try {
          const parsed = JSON.parse(p.features);
          featuresValue = Array.isArray(parsed) ? parsed.join(', ') : p.features;
        } catch (err) {
          featuresValue = p.features;
        }
      }
      document.getElementById('p-features').value     = featuresValue;
      document.getElementById('p-beds').value         = p ? (p.bedrooms || 0) : '';
      document.getElementById('p-baths').value        = p ? (p.bathrooms || 0) : '';
      document.getElementById('p-sqft').value         = p ? (p.floor_area_sqft || 0) : '';
      document.getElementById('p-country').value      = p ? (p.country || 'United Kingdom') : 'United Kingdom';
      document.getElementById('p-agent').value        = p ? (p.agent_id || '') : '';
      document.getElementById('p-desc').value         = p ? (p.description || '') : '';
      document.getElementById('p-featured').checked   = p ? p.is_featured == 1 : false;

      // Show current cover if editing and one exists
      const coverCurrentEl = document.getElementById('p-cover-current');
      const coverImgEl     = document.getElementById('p-cover-current-img');
      if (p && p.cover_image_url) {
        coverCurrentEl.style.display = 'flex';
        coverImgEl.src = p.cover_image_url;
      } else {
        coverCurrentEl.style.display = 'none';
        coverImgEl.src = '';
      }

      document.getElementById('prop-modal').classList.add('open');
    }

    // ── Open Video Modal ──────────────────────────────────────────────────
    function openVideoModal(id, title, url) {
      document.getElementById('vid-id').value = id;
      document.getElementById('vid-prop-name').textContent = title;
      document.getElementById('vid-url').value = url || '';
      document.getElementById('video-modal').classList.add('open');
    }
    </script>

    <?php elseif ($page === 'images'): ?>
    <!-- ══════════════════════════════════════════════════════════════════════
         IMAGES
    ══════════════════════════════════════════════════════════════════════ -->
    <div class="page-title-row">
      <div class="page-title">Property Images</div>
      <button class="add-btn" onclick="openImageModal(null, null)">+ Add Image</button>
    </div>

    <?php
    $images_by_prop = [];
    foreach ($images as $img) {
        $images_by_prop[$img['property_title']][] = $img;
    }
    ?>

    <?php if (empty($images)): ?>
    <div class="section-card">
      <div style="padding:60px 40px;text-align:center;color:var(--muted)">
        <div style="font-size:40px;margin-bottom:12px">🖼️</div>
        <div style="font-size:16px;font-weight:700;color:var(--navy);margin-bottom:8px">No images yet</div>
        <div style="font-size:13px;margin-bottom:20px">Add images to your properties using the button above.</div>
        <button class="add-btn" onclick="openImageModal(null, null)">+ Add First Image</button>
      </div>
    </div>
    <?php else: ?>
    <?php foreach ($images_by_prop as $prop_title => $imgs): ?>
    <div class="section-card images-property-group">
      <div class="section-head">
        <div>
          <div class="section-head-title"><?= h($prop_title) ?></div>
          <div class="section-head-sub"><?= count($imgs) ?> image<?= count($imgs) !== 1 ? 's' : '' ?></div>
        </div>
        <button class="add-btn" style="font-size:11px"
          onclick="openImageModal(null, <?= (int)$imgs[0]['property_id'] ?>)">+ Add Image</button>
      </div>
      <div class="img-card-grid" id="img-grid-<?= (int)$imgs[0]['property_id'] ?>" ondrop="dropImage(event)" ondragover="allowDrop(event)">
        <?php foreach ($imgs as $img): ?>
        <div class="img-card" draggable="true" ondragstart="dragImage(event)" data-id="<?= (int)$img['id'] ?>" data-sort="<?= (int)$img['sort_order'] ?>">
          <img src="<?= h($img['image_url']) ?>"
            onerror="this.src='https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?w=160&auto=format&fit=crop'"
            alt="<?= h($img['caption'] ?? '') ?>"/>
          <?php if ($img['is_cover']): ?>
          <div class="img-card-cover-badge">Cover</div>
          <?php endif; ?>
          <div class="img-card-body">
            <div class="img-card-caption"><?= h($img['caption'] ?: '—') ?></div>
            <div class="img-card-meta">Order: <?= (int)$img['sort_order'] ?></div>
            <div class="img-card-actions">
              <button class="action-btn" style="flex:1"
                onclick="openImageModal(<?= htmlspecialchars(json_encode($img), ENT_QUOTES) ?>, <?= (int)$img['property_id'] ?>)">
                Edit
              </button>
              <button class="action-btn" style="color:#ef4444;border-color:#fca5a5"
                onclick="submitDelete('image', <?= (int)$img['id'] ?>)">✕</button>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <!-- ── IMAGE MODAL ───────────────────────────────────────────────────── -->
    <div class="modal-overlay" id="image-modal" onclick="if(event.target===this)closeModal('image-modal')">
      <div class="modal-box">
        <div class="modal-head">
          <div class="modal-title" id="img-modal-title">Add Image</div>
          <button class="modal-close" onclick="closeModal('image-modal')">&times;</button>
        </div>
        <form method="POST" enctype="multipart/form-data" id="imgForm">
          <input type="hidden" name="_action" value="save_image"/>
          <input type="hidden" name="_page" value="images"/>
          <input type="hidden" name="id" id="img-id"/>

          <div class="modal-body">
            <div class="form-field">
              <label>Property *</label>
              <select name="property_id" id="img-prop" required>
                <option value="">— Select a property —</option>
                <?php foreach ($prop_list as $pl): ?>
                <option value="<?= (int)$pl['id'] ?>"><?= h($pl['title']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="upload-method-tabs">
              <button type="button" class="umt-btn active" id="tab-upload" onclick="switchTab('upload')">📁 Upload File</button>
              <button type="button" class="umt-btn" id="tab-url" onclick="switchTab('url')">🔗 Paste URL</button>
            </div>

            <div id="panel-upload">
              <img id="upload-preview" class="upload-preview" src="" alt="Preview"/>
              <div class="upload-zone" id="uploadZone">
                <input type="file" name="image_file" id="img-file"
                  accept="image/jpeg,image/png,image/gif,image/webp"
                  onchange="previewFile(this)"/>
                <span class="upload-zone-icon">🖼️</span>
                <div class="upload-zone-title">Click to choose or drag &amp; drop</div>
                <div class="upload-zone-sub">JPG, PNG, GIF or WebP · Max 10 MB</div>
              </div>
              <div id="upload-filename" style="font-size:11px;color:var(--navy);font-weight:600;margin-top:8px;display:none"></div>
            </div>

            <div id="panel-url" style="display:none">
              <div class="form-field" style="margin-bottom:8px">
                <label>Image URL</label>
                <input type="text" id="img-url" placeholder="https://example.com/image.jpg"
                  oninput="previewUrl(this.value)"/>
              </div>
              <input type="hidden" name="image_url" id="img-url-hidden"/>
              <img id="url-preview" class="upload-preview" src="" alt="Preview"/>
            </div>

            <div class="form-row-2" style="margin-top:16px">
              <div class="form-field">
                <label>Caption</label>
                <input type="text" name="caption" id="img-caption" placeholder="Optional caption"/>
              </div>
              <div class="form-field">
                <label>Sort Order</label>
                <input type="number" name="sort_order" id="img-sort" value="99" min="0"/>
              </div>
            </div>
            <div style="display:flex;align-items:center;gap:10px;margin-top:-4px">
              <input type="checkbox" name="is_cover" id="img-cover" value="1"
                style="width:auto;height:auto;accent-color:var(--navy)"/>
              <label for="img-cover" style="font-size:13px;font-weight:600;cursor:pointer;text-transform:none;letter-spacing:0">
                Set as cover image for this property
              </label>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn-delete" id="img-delete-btn" style="display:none"
              onclick="submitDelete('image', document.getElementById('img-id').value)">Delete Image</button>
            <button type="button" class="btn-cancel" onclick="closeModal('image-modal')">Cancel</button>
            <button type="submit" class="btn-save" onclick="syncUrlField()">Save Image</button>
          </div>
        </form>
      </div>
    </div>

    <script>
    let activeTab = 'upload';

    function switchTab(tab) {
      activeTab = tab;
      document.getElementById('panel-upload').style.display = tab === 'upload' ? '' : 'none';
      document.getElementById('panel-url').style.display    = tab === 'url'    ? '' : 'none';
      document.getElementById('tab-upload').classList.toggle('active', tab === 'upload');
      document.getElementById('tab-url').classList.toggle('active', tab === 'url');
      if (tab === 'upload') {
        document.getElementById('img-url').value        = '';
        document.getElementById('img-url-hidden').value = '';
      }
    }

    function previewFile(input) {
      const file = input.files[0];
      if (!file) return;
      const prev = document.getElementById('upload-preview');
      const fn   = document.getElementById('upload-filename');
      prev.src = URL.createObjectURL(file);
      prev.style.display = 'block';
      fn.textContent = '📎 ' + file.name + ' (' + (file.size / 1024 / 1024).toFixed(2) + ' MB)';
      fn.style.display = 'block';
      document.getElementById('uploadZone').style.display = 'none';
    }

    function previewUrl(url) {
      const prev = document.getElementById('url-preview');
      if (url) { prev.src = url; prev.style.display = 'block'; }
      else { prev.style.display = 'none'; }
    }

    function syncUrlField() {
      if (activeTab === 'url') {
        document.getElementById('img-url-hidden').value = document.getElementById('img-url').value;
      }
    }

    const zone = document.getElementById('uploadZone');
    if (zone) {
      zone.addEventListener('dragover',  e => { e.preventDefault(); zone.classList.add('drag-over'); });
      zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
      zone.addEventListener('drop',      e => { e.preventDefault(); zone.classList.remove('drag-over'); });
    }

    function openImageModal(img, propId) {
      document.getElementById('imgForm').reset();
      document.getElementById('upload-preview').style.display  = 'none';
      document.getElementById('url-preview').style.display     = 'none';
      document.getElementById('upload-filename').style.display = 'none';
      document.getElementById('uploadZone').style.display      = '';
      switchTab('upload');

      document.getElementById('img-modal-title').textContent  = img ? 'Edit Image' : 'Add Image';
      document.getElementById('img-delete-btn').style.display = img ? 'block' : 'none';
      document.getElementById('img-id').value      = img ? img.id : '';
      document.getElementById('img-prop').value    = img ? img.property_id : (propId || '');
      document.getElementById('img-caption').value = img ? (img.caption || '') : '';
      document.getElementById('img-sort').value    = img ? img.sort_order : 99;
      document.getElementById('img-cover').checked = img ? img.is_cover == 1 : false;

      if (img && img.image_url) {
        switchTab('url');
        document.getElementById('img-url').value = img.image_url;
        previewUrl(img.image_url);
      }

      document.getElementById('image-modal').classList.add('open');
    }
    </script>

    <script>
    // ── Image drag and drop reordering ─────────────────────────────────────
    let draggedImage = null;

    function dragImage(e) {
      draggedImage = e.target.closest('.img-card');
    }

    function allowDrop(e) {
      e.preventDefault();
    }

    function dropImage(e) {
      e.preventDefault();
      if (!draggedImage) return;
      const dropTarget = e.target.closest('.img-card');
      if (!dropTarget || dropTarget === draggedImage) return;

      const grid = dropTarget.parentElement;
      const cards = Array.from(grid.children);
      const draggedIndex = cards.indexOf(draggedImage);
      const dropIndex = cards.indexOf(dropTarget);

      // Reorder in DOM
      if (draggedIndex < dropIndex) {
        grid.insertBefore(draggedImage, dropTarget.nextSibling);
      } else {
        grid.insertBefore(draggedImage, dropTarget);
      }

      // Update sort orders
      const newCards = Array.from(grid.children);
      const updates = newCards.map((card, index) => ({
        id: parseInt(card.dataset.id),
        sort: index
      }));

      // Send to server
      fetch('admin.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: '_action=update_image_sort&updates=' + encodeURIComponent(JSON.stringify(updates))
      }).then(() => {
        // Update display
        newCards.forEach((card, index) => {
          const meta = card.querySelector('.img-card-meta');
          if (meta) meta.textContent = 'Order: ' + index;
          card.dataset.sort = index;
        });
      });

      draggedImage = null;
    }
    </script>

    <?php elseif ($page === 'agents'): ?>
    <!-- ══════════════════════════════════════════════════════════════════════
         AGENTS
    ══════════════════════════════════════════════════════════════════════ -->
    <div class="page-title-row">
      <div class="page-title">Agents</div>
      <button class="add-btn" onclick="openAgentModal(null)">+ Add Agent</button>
    </div>
    <div class="section-card">
      <div class="section-head">
        <div><div class="section-head-title">Agent Directory</div>
        <div class="section-head-sub"><?= count($agents) ?> active agent<?= count($agents) !== 1 ? 's' : '' ?></div></div>
      </div>
      <div class="agents-grid">
        <?php foreach ($agents as $a): ?>
        <div class="agent-card">
          <img class="agent-photo"
            src="<?= h($a['photo_url'] ?: 'https://images.unsplash.com/photo-1560250097-0b93528c311a?w=80&auto=format&fit=crop') ?>"
            onerror="this.src='https://images.unsplash.com/photo-1560250097-0b93528c311a?w=80&auto=format&fit=crop'" alt=""/>
          <div style="flex:1;min-width:0">
            <div class="agent-name"><?= h($a['full_name']) ?></div>
            <div class="agent-title"><?= h($a['title']) ?> &middot; <?= h($a['cea_number']) ?></div>
            <div class="agent-stats">
              <div class="agent-stat">Deals: <strong><?= (int)$a['deals_closed'] ?></strong></div>
              <div class="agent-stat">Exp: <strong><?= (int)$a['years_exp'] ?> yrs</strong></div>
              <div class="agent-stat">Vol: <strong><?= vol_display((int)$a['portfolio_sgd']) ?></strong></div>
            </div>
          </div>
          <button class="action-btn" style="align-self:flex-start"
            onclick="openAgentModal(<?= htmlspecialchars(json_encode($a), ENT_QUOTES) ?>)">Edit</button>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Agent modal -->
    <div class="modal-overlay" id="agent-modal" onclick="if(event.target===this)closeModal('agent-modal')">
      <div class="modal-box">
        <div class="modal-head">
          <div class="modal-title" id="agent-modal-title">Agent</div>
          <button class="modal-close" onclick="closeModal('agent-modal')">&times;</button>
        </div>
        <form method="POST">
          <input type="hidden" name="_action" value="save_agent"/>
          <input type="hidden" name="_page" value="agents"/>
          <input type="hidden" name="id" id="a-id"/>
          <div class="modal-body">
            <div class="form-field"><label>Full Name</label><input name="full_name" id="a-name" type="text"/></div>
            <div class="form-field"><label>Title / Role</label><input name="title" id="a-title" type="text"/></div>
            <div class="form-field"><label>Specialisation</label><input name="specialisation" id="a-spec" type="text"/></div>
            <div class="form-field"><label>CEA Number</label><input name="cea_number" id="a-cea" type="text"/></div>
            <div class="form-field"><label>Email</label><input name="email" id="a-email" type="email"/></div>
            <div class="form-field"><label>Phone</label><input name="phone" id="a-phone" type="text"/></div>
            <div class="form-field"><label>Photo URL</label><input name="photo_url" id="a-photo" type="text"/></div>
            <div class="form-row">
              <div class="form-field"><label>Deals</label><input name="deals_closed" id="a-deals" type="number" placeholder="0"/></div>
              <div class="form-field"><label>Exp (yrs)</label><input name="years_exp" id="a-exp" type="number" placeholder="0"/></div>
              <div class="form-field"><label>Portfolio (SGD)</label><input name="portfolio_sgd" id="a-vol" type="number" placeholder="0"/></div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn-delete" id="a-delete-btn" style="display:none"
              onclick="submitDelete('agent', document.getElementById('a-id').value)">Delete Agent</button>
            <button type="button" class="btn-cancel" onclick="closeModal('agent-modal')">Cancel</button>
            <button type="submit" class="btn-save">Save Agent</button>
          </div>
        </form>
      </div>
    </div>
    <script>
    function openAgentModal(a) {
      document.getElementById('agent-modal-title').textContent = a ? 'Edit Agent' : 'Add Agent';
      document.getElementById('a-delete-btn').style.display = a ? 'block' : 'none';
      document.getElementById('a-id').value    = a ? a.id : '';
      document.getElementById('a-name').value  = a ? a.full_name : '';
      document.getElementById('a-title').value = a ? a.title : '';
      document.getElementById('a-spec').value  = a ? (a.specialisation || '') : '';
      document.getElementById('a-cea').value   = a ? a.cea_number : '';
      document.getElementById('a-email').value = a ? (a.email || '') : '';
      document.getElementById('a-phone').value = a ? (a.phone || '') : '';
      document.getElementById('a-photo').value = a ? (a.photo_url || '') : '';
      document.getElementById('a-deals').value = a ? a.deals_closed : '';
      document.getElementById('a-exp').value   = a ? a.years_exp : '';
      document.getElementById('a-vol').value   = a ? a.portfolio_sgd : '';
      document.getElementById('agent-modal').classList.add('open');
    }
    </script>

    <?php elseif ($page === 'enquiries'): ?>
    <!-- ══════════════════════════════════════════════════════════════════════
         ENQUIRIES
    ══════════════════════════════════════════════════════════════════════ -->
    <div class="page-title-row"><div class="page-title">Enquiries</div></div>
    <div class="section-card">
      <div class="section-head">
        <div><div class="section-head-title">All Enquiries</div>
        <div class="section-head-sub"><?= count($enquiries) ?> enquiries total</div></div>
      </div>
      <div class="filter-bar">
        <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap">
          <input type="hidden" name="page" value="enquiries"/>
          <span class="filter-label">Filter:</span>
          <select name="eq_status" class="filter-select" onchange="this.form.submit()">
            <option value="">All statuses</option>
            <?php foreach (['new', 'contacted', 'scheduled', 'closed', 'lost'] as $s): ?>
            <option value="<?= $s ?>" <?= $enq_status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
          </select>
          <input name="eq_search" class="filter-input" placeholder="Search name or email…" value="<?= h($enq_search) ?>" onkeydown="if(event.key==='Enter')this.form.submit()"/>
        </form>
      </div>
      <table class="data-table">
        <thead><tr>
          <th>Name</th><th>Type</th><th>Property</th><th>Agent</th>
          <th>Preferred Date</th><th>Status</th><th>Update</th>
        </tr></thead>
        <tbody id="enq-tbody">
        <?php foreach ($enquiries as $e): ?>
        <tr data-status="<?= h($e['status']) ?>">
          <td>
            <div class="prop-name"><?= h($e['first_name'] . ' ' . $e['last_name']) ?></div>
            <div class="prop-sub"><?= h($e['email']) ?> &middot; <?= h($e['phone']) ?></div>
          </td>
          <td><?= tag('condo', str_replace('_', ' ', $e['enquiry_type'])) ?></td>
          <td><?= h($e['property_title'] ?? '—') ?></td>
          <td><?= h($e['agent_name'] ?? '—') ?></td>
          <td><?= $e['preferred_date'] ? h(fmt_date($e['preferred_date'])) . ($e['preferred_time'] ? ' · ' . h($e['preferred_time']) : '') : '—' ?></td>
          <td><?= tag($e['status'], $e['status']) ?></td>
          <td>
            <div style="display:flex;gap:6px">
              <button class="action-btn" onclick="openEnquiryModal(<?= htmlspecialchars(json_encode($e), ENT_QUOTES) ?>)">View</button>
              <form method="POST" style="display:inline">
                <input type="hidden" name="_action" value="update_enquiry_status"/>
                <input type="hidden" name="_page" value="enquiries"/>
                <input type="hidden" name="id" value="<?= (int)$e['id'] ?>"/>
                <select name="status" class="status-select" onchange="this.form.submit()">
                  <?php foreach (['new', 'contacted', 'scheduled', 'closed', 'lost'] as $s): ?>
                  <option value="<?= $s ?>" <?= $s === $e['status'] ? 'selected' : '' ?>><?= $s ?></option>
                  <?php endforeach; ?>
                </select>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php if ($total_enq_pages > 1): ?>
      <div style="margin-top:20px;text-align:center">
        <?php if ($page_num > 1): ?>
        <a href="admin.php?page=enquiries&p=<?= $page_num - 1 ?>" class="action-btn">← Previous</a>
        <?php endif; ?>
        Page <?= $page_num ?> of <?= $total_enq_pages ?>
        <?php if ($page_num < $total_enq_pages): ?>
        <a href="admin.php?page=enquiries&p=<?= $page_num + 1 ?>" class="action-btn">Next →</a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- ── ENQUIRY DETAIL MODAL ───────────────────────────────────────────── -->
    <div class="modal-overlay" id="enquiry-modal" onclick="if(event.target===this)closeModal('enquiry-modal')">
      <div class="modal-box" style="width:600px">
        <div class="modal-head">
          <div class="modal-title">Enquiry Details</div>
          <button class="modal-close" onclick="closeModal('enquiry-modal')">&times;</button>
        </div>
        <div class="modal-body">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px">
            <div><strong>Name:</strong> <span id="enq-name"></span></div>
            <div><strong>Email:</strong> <span id="enq-email"></span></div>
            <div><strong>Phone:</strong> <span id="enq-phone"></span></div>
            <div><strong>Type:</strong> <span id="enq-type"></span></div>
            <div><strong>Property:</strong> <span id="enq-property"></span></div>
            <div><strong>Agent:</strong> <span id="enq-agent"></span></div>
            <div><strong>Preferred Date:</strong> <span id="enq-date"></span></div>
            <div><strong>Preferred Time:</strong> <span id="enq-time"></span></div>
            <div><strong>Status:</strong> <span id="enq-status"></span></div>
            <div><strong>Source:</strong> <span id="enq-source"></span></div>
            <div><strong>Created:</strong> <span id="enq-created"></span></div>
          </div>
          <div><strong>Message:</strong></div>
          <div style="background:#f9f9f9;padding:12px;border-radius:6px;margin-top:8px;white-space:pre-wrap" id="enq-message"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-cancel" onclick="closeModal('enquiry-modal')">Close</button>
        </div>
      </div>
    </div>

    <script>
    function openEnquiryModal(e) {
      document.getElementById('enq-name').textContent = e.first_name + ' ' + e.last_name;
      document.getElementById('enq-email').textContent = e.email;
      document.getElementById('enq-phone').textContent = e.phone || '—';
      document.getElementById('enq-type').textContent = e.enquiry_type.replace('_', ' ');
      document.getElementById('enq-property').textContent = e.property_title || 'General';
      document.getElementById('enq-agent').textContent = e.agent_name || 'Unassigned';
      document.getElementById('enq-date').textContent = e.preferred_date || '—';
      document.getElementById('enq-time').textContent = e.preferred_time || '—';
      document.getElementById('enq-status').textContent = e.status;
      document.getElementById('enq-source').textContent = e.source || '—';
      document.getElementById('enq-created').textContent = new Date(e.created_at).toLocaleString();
      document.getElementById('enq-message').textContent = e.message || 'No message';
      document.getElementById('enquiry-modal').classList.add('open');
    }
    function openPaymentModal() {
      document.getElementById('payment-modal-title').textContent = 'Add Payment';
      document.getElementById('pay-id').value = '';
      document.getElementById('pay-name').value = '';
      document.getElementById('pay-email').value = '';
      document.getElementById('pay-phone').value = '';
      document.getElementById('pay-amount').value = '';
      document.getElementById('pay-type').value = '';
      document.getElementById('pay-method').value = '';
      document.getElementById('pay-reference').value = '';
      document.getElementById('pay-property').value = '';
      document.getElementById('pay-notes').value = '';
      document.getElementById('pay-status').value = 'pending';
      document.getElementById('pay-active').checked = true;
      document.getElementById('pay-receipt').value = '';
      document.getElementById('payment-modal').classList.add('open');
    }

    function editPayment(payment) {
      document.getElementById('payment-modal-title').textContent = 'Edit Payment';
      document.getElementById('pay-id').value = payment.id || '';
      document.getElementById('pay-name').value = payment.payer_name || '';
      document.getElementById('pay-email').value = payment.email || '';
      document.getElementById('pay-phone').value = payment.phone || '';
      document.getElementById('pay-amount').value = payment.amount || '';
      document.getElementById('pay-type').value = payment.transaction_type || '';
      document.getElementById('pay-method').value = payment.payment_method || '';
      document.getElementById('pay-reference').value = payment.reference || '';
      document.getElementById('pay-property').value = payment.property || '';
      document.getElementById('pay-notes').value = payment.notes || '';
      document.getElementById('pay-status').value = payment.status || 'pending';
      document.getElementById('pay-active').checked = payment.active == 1;
      document.getElementById('pay-receipt').value = '';
      document.getElementById('payment-modal').classList.add('open');
    }

    function togglePaymentActive(id, active) {
      const label = active === 1 ? 'activate' : 'deactivate';
      if (!confirm('Are you sure you want to ' + label + ' this payment record?')) {
        return;
      }
      const form = document.createElement('form');
      form.method = 'POST';
      form.style.display = 'none';
      form.innerHTML = `
        <input type="hidden" name="_action" value="toggle_payment_active"/>
        <input type="hidden" name="_page" value="payments"/>
        <input type="hidden" name="id" value="${id}"/>
        <input type="hidden" name="active" value="${active}"/>
      `;
      document.body.appendChild(form);
      form.submit();
    }
    </script>

    <?php elseif ($page === 'payments'): ?>
    <!-- ══════════════════════════════════════════════════════════════════════
         PAYMENTS
    ══════════════════════════════════════════════════════════════════════ -->
    <div class="page-title-row">
      <div class="page-title">Payments</div>
      <button class="add-btn" onclick="openPaymentModal()">+ Add Payment</button>
    </div>
    <div class="section-card">
      <div class="section-head">
        <div><div class="section-head-title">Payment Records</div>
        <div class="section-head-sub"><?= count($payment_records) ?> records</div></div>
      </div>
      <div class="filter-bar">
        <span class="filter-label">Info:</span>
        <span style="font-size:12px;color:var(--muted);">Click Edit to update payment details or use Activate/Deactivate to change visibility.</span>
      </div>
      <table class="data-table">
        <thead><tr>
          <th>ID</th><th>Payer</th><th>Method</th><th>Type</th><th>Amount</th><th>Status</th><th>Active</th><th>Actions</th>
        </tr></thead>
        <tbody>
        <?php foreach ($payment_records as $pay): ?>
        <tr>
          <td><?= (int)$pay['id'] ?></td>
          <td><div class="prop-name"><?= h($pay['payer_name']) ?></div><div class="prop-sub"><?= h($pay['email']) ?></div></td>
          <td><?= h($pay['payment_method']) ?></td>
          <td><?= h($pay['transaction_type']) ?></td>
          <td><strong>$<?= h($pay['amount']) ?></strong></td>
          <td><?= h(ucfirst($pay['status'])) ?></td>
          <td><?= $pay['active'] ? '<span class="tag tag-active">Active</span>' : '<span class="tag tag-inactive">Inactive</span>' ?></td>
          <td>
            <button class="action-btn" onclick='editPayment(<?= json_encode($pay, JSON_HEX_APOS) ?>)'>Edit</button>
            <button class="action-btn" onclick="togglePaymentActive(<?= (int)$pay['id'] ?>, <?= $pay['active'] ? 0 : 1 ?>)"><?= $pay['active'] ? 'Deactivate' : 'Activate' ?></button>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- PAYMENT MODAL -->
    <div class="modal-overlay" id="payment-modal" onclick="if(event.target===this)closeModal('payment-modal')">
      <div class="modal-box" style="width:640px;max-width:95vw;">
        <div class="modal-head">
          <div class="modal-title" id="payment-modal-title">Add Payment</div>
          <button class="modal-close" onclick="closeModal('payment-modal')">&times;</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
          <input type="hidden" name="_action" value="save_payment_record"/>
          <input type="hidden" name="_page" value="payments"/>
          <input type="hidden" name="id" id="pay-id"/>
          <div class="modal-body">
            <div class="form-row-2">
              <div class="form-field"><label>Payer Name *</label><input name="payer_name" id="pay-name" type="text" required/></div>
              <div class="form-field"><label>Email *</label><input name="email" id="pay-email" type="email" required/></div>
            </div>
            <div class="form-row-2">
              <div class="form-field"><label>Phone</label><input name="phone" id="pay-phone" type="text"/></div>
              <div class="form-field"><label>Amount (USD) *</label><input name="amount" id="pay-amount" type="text" required/></div>
            </div>
            <div class="form-row-2">
              <div class="form-field"><label>Transaction Type *</label><input name="transaction_type" id="pay-type" type="text" required placeholder="Inspection Fee, Rental Deposit, etc."/></div>
              <div class="form-field"><label>Payment Method *</label><input name="payment_method" id="pay-method" type="text" required placeholder="Bank Transfer, Zelle, Cash App"/></div>
            </div>
            <div class="form-field"><label>Reference</label><input name="reference" id="pay-reference" type="text"/></div>
            <div class="form-field"><label>Property / Notes</label><input name="property" id="pay-property" type="text" placeholder="Address or listing reference"/></div>
            <div class="form-field"><label>Notes</label><textarea name="notes" id="pay-notes"></textarea></div>
            <div class="form-row-2">
              <div class="form-field"><label>Status</label>
                <select name="status" id="pay-status">
                  <option value="pending">Pending</option>
                  <option value="confirmed">Confirmed</option>
                  <option value="rejected">Rejected</option>
                </select>
              </div>
              <div class="form-field"><label><input name="is_active" id="pay-active" type="checkbox" checked/> Active</label></div>
            </div>
            <div class="form-field"><label>Receipt Upload</label><input name="receipt" id="pay-receipt" type="file" accept="application/pdf,image/*"/></div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn-cancel" onclick="closeModal('payment-modal')">Cancel</button>
            <button type="submit" class="btn-save">Save Payment</button>
          </div>
        </form>
      </div>
    </div>

    <?php elseif ($page === 'testimonials'): ?>
    <!-- ══════════════════════════════════════════════════════════════════════
         TESTIMONIALS
    ══════════════════════════════════════════════════════════════════════ -->
    <div class="page-title-row">
      <div class="page-title">Testimonials</div>
      <button class="add-btn" onclick="openTestiModal(null)">+ Add Testimonial</button>
    </div>
    <div class="section-card">
      <div class="section-head">
        <div><div class="section-head-title">All Testimonials</div>
        <div class="section-head-sub"><?= count($testimonials) ?> review<?= count($testimonials) !== 1 ? 's' : '' ?></div></div>
      </div>
      <div class="testi-list">
        <?php foreach ($testimonials as $t): ?>
        <div class="testi-card">
          <div class="testi-stars"><?= stars((int)$t['rating']) ?>
            <span class="testi-panel-badge <?= $t['panel'] === 'dark' ? 'panel-dark' : 'panel-light' ?>">
              <?= $t['panel'] === 'dark' ? 'Dark Panel' : 'Light Panel' ?>
            </span>
          </div>
          <div class="testi-quote">&ldquo;<?= h($t['quote']) ?>&rdquo;</div>
          <div style="display:flex;align-items:center;justify-content:space-between">
            <div>
              <span class="testi-author"><?= h($t['author_name']) ?></span>
              <?php if ($t['location']): ?><span class="testi-location"> &middot; <?= h($t['location']) ?></span><?php endif; ?>
            </div>
            <button class="action-btn" onclick="openTestiModal(<?= htmlspecialchars(json_encode($t), ENT_QUOTES) ?>)">Edit</button>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Testimonial modal -->
    <div class="modal-overlay" id="testi-modal" onclick="if(event.target===this)closeModal('testi-modal')">
      <div class="modal-box">
        <div class="modal-head">
          <div class="modal-title" id="testi-modal-title">Testimonial</div>
          <button class="modal-close" onclick="closeModal('testi-modal')">&times;</button>
        </div>
        <form method="POST">
          <input type="hidden" name="_action" value="save_testimonial"/>
          <input type="hidden" name="_page" value="testimonials"/>
          <input type="hidden" name="id" id="t-id"/>
          <div class="modal-body">
            <div class="form-field"><label>Author Name</label><input name="author_name" id="t-author" type="text"/></div>
            <div class="form-field"><label>Location</label><input name="location" id="t-loc" type="text"/></div>
            <div class="form-field"><label>Quote</label><textarea name="quote" id="t-quote"></textarea></div>
            <div class="form-row-2">
              <div class="form-field"><label>Panel</label>
                <select name="panel" id="t-panel">
                  <option value="dark">Dark Panel</option>
                  <option value="light">Light Panel</option>
                </select>
              </div>
              <div class="form-field"><label>Rating</label>
                <select name="rating" id="t-stars">
                  <option value="5">5 stars</option>
                  <option value="4">4 stars</option>
                  <option value="3">3 stars</option>
                </select>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn-delete" id="t-delete-btn" style="display:none"
              onclick="submitDelete('testimonial', document.getElementById('t-id').value)">Delete</button>
            <button type="button" class="btn-cancel" onclick="closeModal('testi-modal')">Cancel</button>
            <button type="submit" class="btn-save">Save Testimonial</button>
          </div>
        </form>
      </div>
    </div>
    <script>
    function openTestiModal(t) {
      document.getElementById('testi-modal-title').textContent = t ? 'Edit Testimonial' : 'Add Testimonial';
      document.getElementById('t-delete-btn').style.display = t ? 'block' : 'none';
      document.getElementById('t-id').value     = t ? t.id : '';
      document.getElementById('t-author').value = t ? t.author_name : '';
      document.getElementById('t-loc').value    = t ? (t.location || '') : '';
      document.getElementById('t-quote').value  = t ? t.quote : '';
      document.getElementById('t-panel').value  = t ? t.panel : 'dark';
      document.getElementById('t-stars').value  = t ? t.rating : '5';
      document.getElementById('testi-modal').classList.add('open');
    }
    </script>

    <!-- ══════════════════════════════════════════════════════════════════════
         ADMIN USER MODAL
    ══════════════════════════════════════════════════════════════════════ -->
    <div class="modal-overlay" id="admin-user-modal" onclick="if(event.target===this)closeModal('admin-user-modal')">
      <div class="modal-content">
        <div class="modal-header">
          <div class="modal-title" id="admin-user-modal-title">Admin User</div>
          <button class="modal-close" onclick="closeModal('admin-user-modal')">&times;</button>
        </div>
        <form method="POST">
          <input type="hidden" name="_action" value="save_admin_user"/>
          <input type="hidden" name="_page" value="admin_users"/>
          <input type="hidden" name="id" id="au-id"/>
          <div class="modal-body">
            <div class="form-row">
              <div class="form-field"><label>Name *</label><input type="text" name="name" id="au-name" required/></div>
              <div class="form-field"><label>Email *</label><input type="email" name="email" id="au-email" required/></div>
            </div>
            <div class="form-row">
              <div class="form-field"><label>Role</label>
                <select name="role" id="au-role">
                  <option value="admin">Admin</option>
                  <option value="editor">Editor</option>
                  <option value="viewer">Viewer</option>
                </select>
              </div>
              <div class="form-field"><label>Password <?= isset($_GET['edit']) ? '(leave blank to keep current)' : '*' ?></label><input type="password" name="password" id="au-password" <?= !isset($_GET['edit']) ? 'required' : '' ?>/></div>
            </div>
            <div class="form-field"><label><input type="checkbox" name="is_active" id="au-active" checked/> Active</label></div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn-cancel" onclick="closeModal('admin-user-modal')">Cancel</button>
            <button type="submit" class="btn-save">Save User</button>
          </div>
        </form>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════════
         RESET PASSWORD MODAL
    ══════════════════════════════════════════════════════════════════════ -->
    <div class="modal-overlay" id="reset-password-modal" onclick="if(event.target===this)closeModal('reset-password-modal')">
      <div class="modal-content">
        <div class="modal-header">
          <div class="modal-title">Reset Password</div>
          <button class="modal-close" onclick="closeModal('reset-password-modal')">&times;</button>
        </div>
        <form method="POST">
          <input type="hidden" name="_action" value="reset_admin_password"/>
          <input type="hidden" name="_page" value="admin_users"/>
          <input type="hidden" name="id" id="rp-id"/>
          <div class="modal-body">
            <div class="form-field"><label>New Password *</label><input type="password" name="new_password" id="rp-password" required/></div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn-cancel" onclick="closeModal('reset-password-modal')">Cancel</button>
            <button type="submit" class="btn-save">Reset Password</button>
          </div>
        </form>
      </div>
    </div>

    <script>
    function openAdminUserModal() {
      document.getElementById('admin-user-modal-title').textContent = 'Add Admin User';
      document.getElementById('au-id').value = '';
      document.getElementById('au-name').value = '';
      document.getElementById('au-email').value = '';
      document.getElementById('au-role').value = 'admin';
      document.getElementById('au-password').value = '';
      document.getElementById('au-active').checked = true;
      document.getElementById('au-password').required = true;
      document.getElementById('admin-user-modal').classList.add('open');
    }

    function editAdminUser(id, name, email, role, active) {
      document.getElementById('admin-user-modal-title').textContent = 'Edit Admin User';
      document.getElementById('au-id').value = id;
      document.getElementById('au-name').value = name;
      document.getElementById('au-email').value = email;
      document.getElementById('au-role').value = role;
      document.getElementById('au-password').value = '';
      document.getElementById('au-active').checked = active == 1;
      document.getElementById('au-password').required = false;
      document.getElementById('admin-user-modal').classList.add('open');
    }

    function resetPassword(id) {
      document.getElementById('rp-id').value = id;
      document.getElementById('rp-password').value = '';
      document.getElementById('reset-password-modal').classList.add('open');
    }

    function deleteAdminUser(id, name) {
      if (confirm('Are you sure you want to delete user "' + name + '"? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
          <input type="hidden" name="_action" value="delete_admin_user"/>
          <input type="hidden" name="_page" value="admin_users"/>
          <input type="hidden" name="id" value="${id}"/>
        `;
        document.body.appendChild(form);
        form.submit();
      }
    }

    function openProfileModal() {
      document.getElementById('profile-name').value = '<?= h($_SESSION['admin_name']) ?>';
      document.getElementById('profile-email').value = '<?= h($_SESSION['admin_email']) ?>';
      document.getElementById('profile-modal').classList.add('open');
    }
    </script>

    <!-- ══════════════════════════════════════════════════════════════════════
         PROFILE MODAL
    ══════════════════════════════════════════════════════════════════════ -->
    <div class="modal-overlay" id="profile-modal" onclick="if(event.target===this)closeModal('profile-modal')">
      <div class="modal-content">
        <div class="modal-header">
          <div class="modal-title">My Profile</div>
          <button class="modal-close" onclick="closeModal('profile-modal')">&times;</button>
        </div>
        <form method="POST">
          <input type="hidden" name="_action" value="update_profile"/>
          <input type="hidden" name="_page" value="<?= $page ?>"/>
          <div class="modal-body">
            <div class="form-field"><label>Name *</label><input type="text" name="name" id="profile-name" required/></div>
            <div class="form-field"><label>Email *</label><input type="email" name="email" id="profile-email" required/></div>
            <div class="form-field"><label>New Password (leave blank to keep current)</label><input type="password" name="password"/></div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn-cancel" onclick="closeModal('profile-modal')">Cancel</button>
            <button type="submit" class="btn-save">Update Profile</button>
          </div>
        </form>
      </div>
    </div>

    <?php elseif ($page === 'newsletter'): ?>
    <!-- ══════════════════════════════════════════════════════════════════════
         NEWSLETTER
    ══════════════════════════════════════════════════════════════════════ -->
    <div class="page-title-row">
      <div class="page-title">Newsletter Subscribers</div>
      <?php if ($newsletter): ?>
      <a class="add-btn" href="admin.php?page=newsletter&export=1">↓ Export CSV</a>
      <?php endif; ?>
    </div>
    <div class="section-card">
      <?php if (!$newsletter): ?>
      <div style="padding:40px;text-align:center;color:var(--muted)">
        <div style="font-size:16px;font-weight:700;color:var(--navy);margin-bottom:8px">No subscribers yet</div>
        <div style="font-size:13px">Subscribers will appear here once your newsletter form goes live.</div>
      </div>
      <?php else: ?>
      <div class="section-head">
        <div><div class="section-head-title">All Subscribers</div>
        <div class="section-head-sub"><?= count($newsletter) ?> subscriber<?= count($newsletter) !== 1 ? 's' : '' ?></div></div>
      </div>
      <table class="data-table">
        <thead><tr><th>Email</th><th>Status</th><th>Subscribed</th></tr></thead>
        <tbody>
        <?php foreach ($newsletter as $s): ?>
        <tr>
          <td><?= h($s['email']) ?></td>
          <td><?= tag($s['status'] === 'active' ? 'available' : 'sold', $s['status']) ?></td>
          <td><?= h(fmt_date($s['subscribed_at'])) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>

    <?php elseif ($page === 'views'): ?>
    <!-- ══════════════════════════════════════════════════════════════════════
         VIEWS
    ══════════════════════════════════════════════════════════════════════ -->
    <div class="page-title-row"><div class="page-title">Property Views</div></div>
    <div class="section-card">
      <div class="section-head">
        <div><div class="section-head-title">Views by Property</div>
        <div class="section-head-sub">Total page views from database</div></div>
      </div>
      <div class="views-list">
        <?php
        $max_views = max(1, array_reduce($views_data, fn($c, $v) => max($c, (int)$v['views']), 0));
        foreach ($views_data as $v): ?>
        <div class="view-row">
          <div class="view-prop"><?= h($v['title']) ?></div>
          <div class="view-bar-wrap"><div class="view-bar" style="width:<?= round(((int)$v['views'] / $max_views) * 100) ?>%"></div></div>
          <div class="view-count"><?= fmt_num($v['views']) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <?php elseif ($page === 'admin_users'): ?>
    <!-- ══════════════════════════════════════════════════════════════════════
         ADMIN USERS
    ══════════════════════════════════════════════════════════════════════ -->
    <div class="page-title-row">
      <div class="page-title">Admin Users</div>
      <button class="add-btn" onclick="openAdminUserModal()">+ Add User</button>
    </div>
    <div class="section-card">
      <div class="section-head">
        <div><div class="section-head-title">System Users</div>
        <div class="section-head-sub"><?= count($admin_users) ?> user<?= count($admin_users) !== 1 ? 's' : '' ?></div></div>
      </div>
      <table class="data-table">
        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Last Login</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($admin_users as $u): ?>
        <tr>
          <td><div class="prop-name"><?= h($u['name']) ?></div></td>
          <td><?= h($u['email']) ?></td>
          <td><?= tag('condo', str_replace('_', ' ', $u['role'])) ?></td>
          <td><?= h(fmt_date($u['last_login'])) ?></td>
          <td><?= tag($u['is_active'] == 1 ? 'active' : 'inactive', $u['is_active'] == 1 ? 'Active' : 'Inactive') ?></td>
          <td>
            <button class="action-btn" onclick="editAdminUser(<?= $u['id'] ?>, '<?= h($u['name']) ?>', '<?= h($u['email']) ?>', '<?= h($u['role']) ?>', <?= $u['is_active'] ?>)">Edit</button>
            <button class="action-btn" onclick="resetPassword(<?= $u['id'] ?>)">Reset Password</button>
            <?php if ($u['id'] !== $_SESSION['admin_id']): ?>
            <button class="action-btn danger" onclick="deleteAdminUser(<?= $u['id'] ?>, '<?= h($u['name']) ?>')">Delete</button>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php elseif ($page === 'settings'): ?>
    <!-- ══════════════════════════════════════════════════════════════════════
         SETTINGS
    ══════════════════════════════════════════════════════════════════════ -->
    <div class="page-title">Settings</div>
    <div class="section-card">
      <form method="POST">
        <input type="hidden" name="_action" value="save_settings"/>
        <input type="hidden" name="_page" value="settings"/>
        <div class="settings-grid">
          <div class="settings-field"><label>Site Name</label><input name="site_name" value="<?= h($settings['site_name']) ?>"/></div>
          <div class="settings-field"><label>Admin Email</label><input name="admin_email" value="<?= h($settings['admin_email']) ?>"/></div>
          <div class="settings-field"><label>Primary Markets</label><input name="primary_markets" value="<?= h($settings['primary_markets']) ?>"/></div>
          <div class="settings-field"><label>Currency</label><input name="currency" value="<?= h($settings['currency']) ?>"/></div>
        </div>
        <div class="settings-section" style="margin-top:24px;padding-top:24px;border-top:1px solid var(--border);">
          <div style="font-weight:700;margin-bottom:14px;color:var(--navy);">Payment Gateway Details</div>
          <div class="settings-field"><label>Bank Transfer Details</label><textarea name="bank_transfer_details" rows="3"><?= h($settings['bank_transfer_details']) ?></textarea></div>
          <div class="settings-field"><label>Wire Transfer Details</label><textarea name="wire_transfer_details" rows="3"><?= h($settings['wire_transfer_details']) ?></textarea></div>
          <div class="settings-field"><label>Zelle Email</label><input name="zelle_email" value="<?= h($settings['zelle_email']) ?>"/></div>
          <div class="settings-field"><label>Cash App Handle</label><input name="cash_app_handle" value="<?= h($settings['cash_app_handle']) ?>"/></div>
        </div>
        <div style="padding:0 24px 24px"><button type="submit" class="add-btn">Save Settings</button></div>
      </form>
    </div>
    <?php endif; ?>

    </div><!-- /content-left -->

    <!-- RIGHT PANEL -->
    <div class="content-right">
      <div class="balance-card">
        <div class="balance-title">Property Balance Overview</div>
        <div class="balance-grid">
          <div><div class="balance-item-label">Total Props</div><div class="balance-item-val"><?= $total_props ?></div></div>
          <div><div class="balance-item-label">Open Enquiries</div><div class="balance-item-val gold"><?= $enq_open ?> Active</div></div>
        </div>
        <div><div class="balance-item-label">Total Views</div><div class="balance-item-val"><?= fmt_num($total_views) ?></div></div>
      </div>

      <div class="activity-card">
        <div class="activity-head">Recent Enquiries</div>
        <?php if ($recent_activity): ?>
        <?php foreach ($recent_activity as $a): ?>
        <div class="activity-item">
          <div class="activity-thumb">
            <svg viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
          </div>
          <div class="activity-info">
            <div class="activity-type">Enquiry — <?= h($a['status']) ?></div>
            <div class="activity-title"><?= h($a['first_name'] . ' ' . $a['last_name']) ?> — <?= h($a['property_title'] ?? 'General') ?></div>
            <div class="activity-time"><?= h(fmt_date($a['created_at'])) ?></div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php else: ?>
        <div style="padding:20px;text-align:center;color:var(--muted);font-size:12px">No recent activity</div>
        <?php endif; ?>
      </div>
    </div>

  </div><!-- /page-content -->
</div><!-- /main -->

<!-- Shared delete form -->
<form method="POST" id="delete-form" style="display:none">
  <input type="hidden" name="_page" id="df-page"/>
  <input type="hidden" name="_action" id="df-action"/>
  <input type="hidden" name="id" id="df-id"/>
</form>

<script>
function closeModal(id) { document.getElementById(id)?.classList.remove('open'); }

function globalSearch() {
  const q = document.getElementById('global-search').value.toLowerCase().trim();
  const resultsDiv = document.getElementById('search-results');
  const listDiv = document.getElementById('search-list');
  const countDiv = document.getElementById('search-count');

  if (!q) {
    resultsDiv.style.display = 'none';
    return;
  }

  const props = <?= json_encode($properties) ?>;
  const enqs = <?= json_encode($enquiries) ?>;

  const propResults = props.filter(p =>
    (p.title + ' ' + p.area + ' ' + p.address + ' ' + p.badge).toLowerCase().includes(q)
  );

  const enqResults = enqs.filter(e =>
    (e.first_name + ' ' + e.last_name + ' ' + e.email + ' ' + (e.property_title || '')).toLowerCase().includes(q)
  );

  const allResults = [...propResults.map(p => ({type: 'property', data: p})), ...enqResults.map(e => ({type: 'enquiry', data: e}))];

  countDiv.textContent = allResults.length + ' results';

  listDiv.innerHTML = allResults.slice(0, 20).map(r => {
    if (r.type === 'property') {
      const p = r.data;
      return `
        <div style="padding:12px;border-bottom:1px solid var(--border);display:flex;gap:12px">
          <img src="${p.cover_image_url || 'https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?w=80&auto=format&fit=crop'}" style="width:48px;height:48px;object-fit:cover;border-radius:6px" onerror="this.src='https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?w=80&auto=format&fit=crop'"/>
          <div>
            <div style="font-weight:600;color:var(--ink)">${p.title}</div>
            <div style="font-size:12px;color:var(--muted)">${p.area} · ${p.price_display} · ${p.status}</div>
            <a href="admin.php?page=properties" style="font-size:11px;color:var(--navy)">View Property</a>
          </div>
        </div>
      `;
    } else {
      const e = r.data;
      return `
        <div style="padding:12px;border-bottom:1px solid var(--border);display:flex;gap:12px">
          <div style="width:48px;height:48px;background:var(--navy-lt);border-radius:6px;display:flex;align-items:center;justify-content:center">
            <svg viewBox="0 0 24 24" style="width:20px;height:20px;fill:var(--navy)"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
          </div>
          <div>
            <div style="font-weight:600;color:var(--ink)">${e.first_name} ${e.last_name}</div>
            <div style="font-size:12px;color:var(--muted)">${e.email} · ${e.property_title || 'General'} · ${e.status}</div>
            <a href="admin.php?page=enquiries" style="font-size:11px;color:var(--navy)">View Enquiry</a>
          </div>
        </div>
      `;
    }
  }).join('');

  resultsDiv.style.display = allResults.length ? 'block' : 'none';
}

function clearGlobalSearch() {
  document.getElementById('global-search').value = '';
  globalSearch();
}

function toggleSidebar() {
  document.querySelector('.sidebar')?.classList.toggle('open');
  document.querySelector('.sidebar-overlay')?.classList.toggle('open');
}

function closeSidebar() {
  document.querySelector('.sidebar')?.classList.remove('open');
  document.querySelector('.sidebar-overlay')?.classList.remove('open');
}

document.addEventListener('click', function(event) {
  if (!event.target.closest('.sidebar') && !event.target.closest('.hamburger-btn')) {
    closeSidebar();
  }
});

document.querySelectorAll('form').forEach(function(form) {
  form.addEventListener('submit', function(event) {
    if (!this.checkValidity()) {
      event.preventDefault();
      this.classList.add('form-invalid');
      this.reportValidity();
    }
  });
});

</script>

<?php
?>
</body>
</html>