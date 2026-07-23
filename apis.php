<?php
/**
 * Dwelro Admin API
 * Place this file in the SAME folder as admin.html on your server.
 * Update the DB credentials below to match your MAMP/phpMyAdmin settings.
 */

// ─── DB CONFIG ────────────────────────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_NAME', 'vyjcjbwj_dwel');
define('DB_USER', 'vyjcjbwj_dwel');
define('DB_PASS', 'Alvarez.1000');
// ──────────────────────────────────────────────────────────────────────────────

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

// ─── DB CONNECTION ────────────────────────────────────────────────────────────
try {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB connection failed: ' . $e->getMessage()]);
    exit;
}

// ─── ROUTER ───────────────────────────────────────────────────────────────────
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

function ok($data)  { echo json_encode(['ok' => true, 'data' => $data]); exit; }
function err($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

switch ($action) {

    // ── DASHBOARD STATS ──────────────────────────────────────────────────────
    case 'stats':
        $stats = [];
        $stats['total_properties']    = $pdo->query("SELECT COUNT(*) FROM properties WHERE is_published=1")->fetchColumn();
        $stats['total_agents']        = $pdo->query("SELECT COUNT(*) FROM agents WHERE is_active=1")->fetchColumn();
        $stats['enquiries_open']      = $pdo->query("SELECT COUNT(*) FROM enquiries WHERE status IN ('new','contacted')")->fetchColumn();
        $stats['testimonials_live']   = $pdo->query("SELECT COUNT(*) FROM testimonials WHERE is_active=1")->fetchColumn();
        $stats['newsletter_subs']     = $pdo->query("SELECT COUNT(*) FROM newsletter_subscribers WHERE status='active'")->fetchColumn();
        $stats['total_views']         = $pdo->query("SELECT SUM(views) FROM properties WHERE is_published=1")->fetchColumn() ?? 0;
        $stats['featured_count']      = $pdo->query("SELECT COUNT(*) FROM properties WHERE is_featured=1 AND is_published=1")->fetchColumn();
        $stats['available_count']     = $pdo->query("SELECT COUNT(*) FROM properties WHERE status='available' AND is_published=1")->fetchColumn();
        $stats['sold_count']          = $pdo->query("SELECT COUNT(*) FROM properties WHERE status='sold'")->fetchColumn();
        $stats['under_offer_count']   = $pdo->query("SELECT COUNT(*) FROM properties WHERE status='under_offer'")->fetchColumn();
        $stats['new_enquiries']       = $pdo->query("SELECT COUNT(*) FROM enquiries WHERE status='new'")->fetchColumn();
        $stats['contacted_enquiries'] = $pdo->query("SELECT COUNT(*) FROM enquiries WHERE status='contacted'")->fetchColumn();
        $stats['scheduled_enquiries'] = $pdo->query("SELECT COUNT(*) FROM enquiries WHERE status='scheduled'")->fetchColumn();
        $stats['closed_enquiries']    = $pdo->query("SELECT COUNT(*) FROM enquiries WHERE status='closed'")->fetchColumn();
        ok($stats);

    // ── PROPERTIES ───────────────────────────────────────────────────────────
    case 'properties':
        if ($method === 'GET') {
            $rows = $pdo->query("
                SELECT p.id, p.title, p.category, p.listing_type, p.status,
                       p.price_display, p.district, p.area, p.country,
                       p.cover_image_url, p.is_featured, p.is_published,
                       p.views, p.badge, p.bedrooms, p.bathrooms,
                       p.floor_area_sqft, p.created_at,
                       a.full_name AS agent_name
                FROM properties p
                LEFT JOIN agents a ON a.id = p.agent_id
                ORDER BY p.created_at DESC
            ")->fetchAll();
            ok($rows);
        }
        err('Method not allowed', 405);

    // ── AGENTS ───────────────────────────────────────────────────────────────
    case 'agents':
        if ($method === 'GET') {
            $rows = $pdo->query("
                SELECT id, full_name, title, specialisation, cea_number,
                       photo_url, years_exp, deals_closed, portfolio_sgd,
                       email, phone, whatsapp, is_active
                FROM agents
                WHERE is_active = 1
                ORDER BY id ASC
            ")->fetchAll();
            // Format portfolio volume as human-readable
            foreach ($rows as &$r) {
                $vol = (int)$r['portfolio_sgd'];
                if ($vol >= 1000000000)      $r['vol_display'] = '$' . round($vol/1000000000, 1) . 'B';
                elseif ($vol >= 1000000)     $r['vol_display'] = '$' . round($vol/1000000, 0) . 'M';
                else                         $r['vol_display'] = '$' . number_format($vol);
            }
            ok($rows);
        }
        if ($method === 'POST') {
            $stmt = $pdo->prepare("
                INSERT INTO agents (full_name, title, specialisation, cea_number, photo_url, years_exp, deals_closed, portfolio_sgd, email, phone)
                VALUES (:name, :title, :spec, :cea, :photo, :exp, :deals, :vol, :email, :phone)
            ");
            $stmt->execute([
                'name'  => $body['full_name'] ?? 'Unnamed Agent',
                'title' => $body['title'] ?? '',
                'spec'  => $body['specialisation'] ?? '',
                'cea'   => $body['cea_number'] ?? '',
                'photo' => $body['photo_url'] ?? '',
                'exp'   => (int)($body['years_exp'] ?? 0),
                'deals' => (int)($body['deals_closed'] ?? 0),
                'vol'   => (int)($body['portfolio_sgd'] ?? 0),
                'email' => $body['email'] ?? '',
                'phone' => $body['phone'] ?? '',
            ]);
            ok(['id' => $pdo->lastInsertId()]);
        }
        if ($method === 'PUT') {
            $id = (int)($body['id'] ?? 0);
            if (!$id) err('Missing id');
            $stmt = $pdo->prepare("
                UPDATE agents SET full_name=:name, title=:title, specialisation=:spec,
                cea_number=:cea, photo_url=:photo, years_exp=:exp,
                deals_closed=:deals, portfolio_sgd=:vol
                WHERE id=:id
            ");
            $stmt->execute([
                'name'  => $body['full_name'] ?? '',
                'title' => $body['title'] ?? '',
                'spec'  => $body['specialisation'] ?? '',
                'cea'   => $body['cea_number'] ?? '',
                'photo' => $body['photo_url'] ?? '',
                'exp'   => (int)($body['years_exp'] ?? 0),
                'deals' => (int)($body['deals_closed'] ?? 0),
                'vol'   => (int)($body['portfolio_sgd'] ?? 0),
                'id'    => $id,
            ]);
            ok(['updated' => $id]);
        }
        if ($method === 'DELETE') {
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) err('Missing id');
            $pdo->prepare("UPDATE agents SET is_active=0 WHERE id=:id")->execute(['id' => $id]);
            ok(['deleted' => $id]);
        }
        err('Method not allowed', 405);

    // ── ENQUIRIES ────────────────────────────────────────────────────────────
    case 'enquiries':
        if ($method === 'GET') {
            $rows = $pdo->query("
                SELECT e.id, e.first_name, e.last_name, e.email, e.phone,
                       e.enquiry_type, e.preferred_date, e.preferred_time,
                       e.status, e.source, e.message, e.created_at,
                       p.title AS property_title,
                       a.full_name AS agent_name
                FROM enquiries e
                LEFT JOIN properties p ON p.id = e.property_id
                LEFT JOIN agents a ON a.id = e.agent_id
                ORDER BY e.created_at DESC
            ")->fetchAll();
            ok($rows);
        }
        if ($method === 'PUT') {
            // Update status only
            $id     = (int)($body['id'] ?? 0);
            $status = $body['status'] ?? '';
            $allowed = ['new','contacted','scheduled','closed','lost'];
            if (!$id || !in_array($status, $allowed)) err('Invalid data');
            $pdo->prepare("UPDATE enquiries SET status=:s WHERE id=:id")->execute(['s'=>$status,'id'=>$id]);
            ok(['updated' => $id]);
        }
        err('Method not allowed', 405);

    // ── TESTIMONIALS ─────────────────────────────────────────────────────────
    case 'testimonials':
        if ($method === 'GET') {
            $rows = $pdo->query("
                SELECT id, author_name, location, quote, rating, photo_url, panel, sort_order, is_active
                FROM testimonials
                WHERE is_active = 1
                ORDER BY sort_order ASC, id ASC
            ")->fetchAll();
            ok($rows);
        }
        if ($method === 'POST') {
            $stmt = $pdo->prepare("
                INSERT INTO testimonials (author_name, location, quote, rating, panel, sort_order, is_active)
                VALUES (:author, :loc, :quote, :rating, :panel, :sort, 1)
            ");
            $stmt->execute([
                'author' => $body['author_name'] ?? 'Anonymous',
                'loc'    => $body['location'] ?? '',
                'quote'  => $body['quote'] ?? '',
                'rating' => (int)($body['rating'] ?? 5),
                'panel'  => $body['panel'] ?? 'dark',
                'sort'   => (int)($body['sort_order'] ?? 99),
            ]);
            ok(['id' => $pdo->lastInsertId()]);
        }
        if ($method === 'PUT') {
            $id = (int)($body['id'] ?? 0);
            if (!$id) err('Missing id');
            $stmt = $pdo->prepare("
                UPDATE testimonials SET author_name=:author, location=:loc,
                quote=:quote, rating=:rating, panel=:panel WHERE id=:id
            ");
            $stmt->execute([
                'author' => $body['author_name'] ?? '',
                'loc'    => $body['location'] ?? '',
                'quote'  => $body['quote'] ?? '',
                'rating' => (int)($body['rating'] ?? 5),
                'panel'  => $body['panel'] ?? 'dark',
                'id'     => $id,
            ]);
            ok(['updated' => $id]);
        }
        if ($method === 'DELETE') {
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) err('Missing id');
            $pdo->prepare("UPDATE testimonials SET is_active=0 WHERE id=:id")->execute(['id' => $id]);
            ok(['deleted' => $id]);
        }
        err('Method not allowed', 405);

    // ── NEWSLETTER SUBSCRIBERS ───────────────────────────────────────────────
    case 'newsletter':
        if ($method === 'GET') {
            $rows = $pdo->query("
                SELECT id, email, status, subscribed_at
                FROM newsletter_subscribers
                ORDER BY subscribed_at DESC
            ")->fetchAll();
            ok($rows);
        }
        err('Method not allowed', 405);

    // ── PROPERTY IMAGES ──────────────────────────────────────────────────────
    case 'images':
        if ($method === 'GET') {
            $rows = $pdo->query("
                SELECT pi.id, pi.image_url, pi.caption, pi.sort_order, pi.is_cover,
                       pi.created_at, p.title AS property_title
                FROM property_images pi
                JOIN properties p ON p.id = pi.property_id
                ORDER BY pi.property_id ASC, pi.sort_order ASC
            ")->fetchAll();
            ok($rows);
        }
        err('Method not allowed', 405);

    // ── PROPERTY VIEWS ───────────────────────────────────────────────────────
    case 'views':
        if ($method === 'GET') {
            $rows = $pdo->query("
                SELECT title, views
                FROM properties
                WHERE is_published = 1
                ORDER BY views DESC
            ")->fetchAll();
            ok($rows);
        }
        err('Method not allowed', 405);

    // ── ADMIN USERS ──────────────────────────────────────────────────────────
    case 'admin_users':
        if ($method === 'GET') {
            $rows = $pdo->query("
                SELECT id, name, email, role, last_login, is_active, created_at
                FROM admin_users
                ORDER BY id ASC
            ")->fetchAll();
            ok($rows);
        }
        err('Method not allowed', 405);

    default:
        err('Unknown action', 404);
}