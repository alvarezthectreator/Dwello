<?php
/**
 * Dwelro Admin API  —  admin_api.php
 *
 * URL pattern:  admin_api.php?resource=<name>
 * HTTP methods: GET · POST · PUT · DELETE
 *
 * Requires config.php + db.php in the same directory.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// ── CORS / preflight ─────────────────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ── Tiny response helpers ─────────────────────────────────────────────────────
function send_ok($data): void {
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

function send_err(string $message, int $status = 400): void {
    http_response_code($status);
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Request context ───────────────────────────────────────────────────────────
$resource = strtolower(trim($_GET['resource'] ?? $_GET['action'] ?? ''));
$method   = $_SERVER['REQUEST_METHOD'];
$body     = (array) (json_decode(file_get_contents('php://input'), true) ?? []);

// ── Route map ─────────────────────────────────────────────────────────────────
//  Each resource maps to a handler function defined below.
$routes = [
    'stats'        => 'handle_stats',
    'properties'   => 'handle_properties',
    'agents'       => 'handle_agents',
    'enquiries'    => 'handle_enquiries',
    'testimonials' => 'handle_testimonials',
    'newsletter'   => 'handle_newsletter',
    'images'       => 'handle_images',
    'views'        => 'handle_views',
    'admin_users'  => 'handle_admin_users',
    'publish'      => 'handle_publish',
];

if (!isset($routes[$resource])) {
    send_err("Unknown resource: " . htmlspecialchars($resource), 404);
}

call_user_func($routes[$resource], $method, $body);


// ══════════════════════════════════════════════════════════════════════════════
//  HANDLERS
// ══════════════════════════════════════════════════════════════════════════════

// ── STATS ─────────────────────────────────────────────────────────────────────
function handle_stats(string $method, array $body): void {
    if ($method !== 'GET') send_err('Method not allowed', 405);

    $stats = [
        'total_all_properties' => (int) db_fetch_value("SELECT COUNT(*) FROM properties"),
        'published_count'      => (int) db_fetch_value("SELECT COUNT(*) FROM properties WHERE is_published = 1"),
        'total_agents'         => (int) db_fetch_value("SELECT COUNT(*) FROM agents WHERE is_active = 1"),
        'enquiries_open'       => (int) db_fetch_value("SELECT COUNT(*) FROM enquiries WHERE status IN ('new','contacted')"),
        'testimonials_live'    => (int) db_fetch_value("SELECT COUNT(*) FROM testimonials WHERE is_active = 1"),
        'newsletter_subs'      => (int) db_fetch_value("SELECT COUNT(*) FROM newsletter_subscribers WHERE status = 'active'"),
        'total_views'          => (int) db_fetch_value("SELECT COALESCE(SUM(views),0) FROM properties"),
        'featured_count'       => (int) db_fetch_value("SELECT COUNT(*) FROM properties WHERE is_featured = 1 AND is_published = 1"),
        'available_count'      => (int) db_fetch_value("SELECT COUNT(*) FROM properties WHERE status = 'available'"),
        'sold_count'           => (int) db_fetch_value("SELECT COUNT(*) FROM properties WHERE status = 'sold'"),
        'under_offer_count'    => (int) db_fetch_value("SELECT COUNT(*) FROM properties WHERE status = 'under_offer'"),
        'new_enquiries'        => (int) db_fetch_value("SELECT COUNT(*) FROM enquiries WHERE status = 'new'"),
        'contacted_enquiries'  => (int) db_fetch_value("SELECT COUNT(*) FROM enquiries WHERE status = 'contacted'"),
        'scheduled_enquiries'  => (int) db_fetch_value("SELECT COUNT(*) FROM enquiries WHERE status = 'scheduled'"),
        'closed_enquiries'     => (int) db_fetch_value("SELECT COUNT(*) FROM enquiries WHERE status = 'closed'"),
        'lost_enquiries'       => (int) db_fetch_value("SELECT COUNT(*) FROM enquiries WHERE status = 'lost'"),
    ];

    $stats['draft_count'] = $stats['total_all_properties'] - $stats['published_count'];

    send_ok($stats);
}


// ── PROPERTIES ────────────────────────────────────────────────────────────────
function handle_properties(string $method, array $body): void {

    if ($method === 'GET') {
        $rows = db_fetch_all("
            SELECT
                p.id, p.title, p.category, p.listing_type, p.status,
                p.price_display, p.district, p.area, p.country,
                p.cover_image_url, p.is_featured, p.is_published,
                p.views, p.badge, p.bedrooms, p.bathrooms,
                p.floor_area_sqft, p.created_at,
                a.full_name AS agent_name
            FROM properties p
            LEFT JOIN agents a ON a.id = p.agent_id
            ORDER BY p.is_published DESC, p.created_at DESC
        ");
        send_ok($rows);
    }

    if ($method === 'PUT') {
        $id = (int) ($body['id'] ?? 0);
        if (!$id) send_err('Missing property id');

        $allowed = [
            'title', 'status', 'is_published', 'is_featured',
            'price_display', 'category', 'listing_type',
            'area', 'district', 'country', 'agent_id',
        ];

        $sets   = [];
        $params = ['id' => $id];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $body)) {
                $sets[]       = "$field = :$field";
                $params[$field] = $body[$field];
            }
        }

        if (empty($sets)) send_err('Nothing to update');

        db_execute(
            "UPDATE properties SET " . implode(', ', $sets) . " WHERE id = :id",
            $params
        );
        send_ok(['updated' => $id]);
    }

    send_err('Method not allowed', 405);
}


// ── AGENTS ────────────────────────────────────────────────────────────────────
function handle_agents(string $method, array $body): void {

    if ($method === 'GET') {
        $rows = db_fetch_all("
            SELECT id, full_name, title, specialisation, cea_number,
                   photo_url, years_exp, deals_closed, portfolio_sgd,
                   email, phone, whatsapp, is_active
            FROM agents
            WHERE is_active = 1
            ORDER BY id ASC
        ");

        foreach ($rows as &$r) {
            $vol = (int) $r['portfolio_sgd'];
            if ($vol >= 1_000_000_000)   $r['vol_display'] = '$' . round($vol / 1_000_000_000, 1) . 'B';
            elseif ($vol >= 1_000_000)   $r['vol_display'] = '$' . round($vol / 1_000_000) . 'M';
            else                         $r['vol_display'] = '$' . number_format($vol);
        }
        unset($r);

        send_ok($rows);
    }

    if ($method === 'POST') {
        db_execute("
            INSERT INTO agents
                (full_name, title, specialisation, cea_number, photo_url,
                 years_exp, deals_closed, portfolio_sgd, email, phone)
            VALUES
                (:name, :title, :spec, :cea, :photo, :exp, :deals, :vol, :email, :phone)
        ", [
            'name'  => $body['full_name']       ?? 'Unnamed Agent',
            'title' => $body['title']           ?? '',
            'spec'  => $body['specialisation']  ?? '',
            'cea'   => $body['cea_number']      ?? '',
            'photo' => $body['photo_url']       ?? '',
            'exp'   => (int) ($body['years_exp']    ?? 0),
            'deals' => (int) ($body['deals_closed'] ?? 0),
            'vol'   => (int) ($body['portfolio_sgd'] ?? 0),
            'email' => $body['email'] ?? '',
            'phone' => $body['phone'] ?? '',
        ]);
        send_ok(['id' => get_db()->lastInsertId()]);
    }

    if ($method === 'PUT') {
        $id = (int) ($body['id'] ?? 0);
        if (!$id) send_err('Missing agent id');

        db_execute("
            UPDATE agents
            SET full_name      = :name,
                title          = :title,
                specialisation = :spec,
                cea_number     = :cea,
                photo_url      = :photo,
                years_exp      = :exp,
                deals_closed   = :deals,
                portfolio_sgd  = :vol
            WHERE id = :id
        ", [
            'name'  => $body['full_name']       ?? '',
            'title' => $body['title']           ?? '',
            'spec'  => $body['specialisation']  ?? '',
            'cea'   => $body['cea_number']      ?? '',
            'photo' => $body['photo_url']       ?? '',
            'exp'   => (int) ($body['years_exp']    ?? 0),
            'deals' => (int) ($body['deals_closed'] ?? 0),
            'vol'   => (int) ($body['portfolio_sgd'] ?? 0),
            'id'    => $id,
        ]);
        send_ok(['updated' => $id]);
    }

    if ($method === 'DELETE') {
        $id = (int) ($_GET['id'] ?? $body['id'] ?? 0);
        if (!$id) send_err('Missing agent id');
        // Soft-delete: keep the row, just hide from active list
        db_execute("UPDATE agents SET is_active = 0 WHERE id = :id", ['id' => $id]);
        send_ok(['deleted' => $id]);
    }

    send_err('Method not allowed', 405);
}


// ── ENQUIRIES ─────────────────────────────────────────────────────────────────
function handle_enquiries(string $method, array $body): void {

    if ($method === 'GET') {
        $rows = db_fetch_all("
            SELECT
                e.id, e.first_name, e.last_name, e.email, e.phone,
                e.enquiry_type, e.preferred_date, e.preferred_time,
                e.status, e.source, e.message, e.created_at,
                p.title     AS property_title,
                a.full_name AS agent_name
            FROM enquiries e
            LEFT JOIN properties p ON p.id = e.property_id
            LEFT JOIN agents     a ON a.id = e.agent_id
            ORDER BY
                FIELD(e.status, 'new','contacted','scheduled','closed','lost'),
                e.created_at DESC
        ");
        send_ok($rows);
    }

    if ($method === 'PUT') {
        $id      = (int) ($body['id'] ?? 0);
        $status  = trim($body['status'] ?? '');
        $allowed = ['new', 'contacted', 'scheduled', 'closed', 'lost'];

        if (!$id || !in_array($status, $allowed, true)) send_err('Invalid data');

        db_execute(
            "UPDATE enquiries SET status = :status WHERE id = :id",
            ['status' => $status, 'id' => $id]
        );
        send_ok(['updated' => $id]);
    }

    send_err('Method not allowed', 405);
}


// ── TESTIMONIALS ──────────────────────────────────────────────────────────────
function handle_testimonials(string $method, array $body): void {

    if ($method === 'GET') {
        $rows = db_fetch_all("
            SELECT id, author_name, location, quote, rating,
                   photo_url, panel, sort_order, is_active
            FROM testimonials
            WHERE is_active = 1
            ORDER BY sort_order ASC, id ASC
        ");
        send_ok($rows);
    }

    if ($method === 'POST') {
        db_execute("
            INSERT INTO testimonials
                (author_name, location, quote, rating, panel, sort_order, is_active)
            VALUES
                (:author, :loc, :quote, :rating, :panel, :sort, 1)
        ", [
            'author' => $body['author_name'] ?? 'Anonymous',
            'loc'    => $body['location']    ?? '',
            'quote'  => $body['quote']       ?? '',
            'rating' => (int) ($body['rating']     ?? 5),
            'panel'  => $body['panel']       ?? 'dark',
            'sort'   => (int) ($body['sort_order'] ?? 99),
        ]);
        send_ok(['id' => get_db()->lastInsertId()]);
    }

    if ($method === 'PUT') {
        $id = (int) ($body['id'] ?? 0);
        if (!$id) send_err('Missing testimonial id');

        db_execute("
            UPDATE testimonials
            SET author_name = :author,
                location    = :loc,
                quote       = :quote,
                rating      = :rating,
                panel       = :panel
            WHERE id = :id
        ", [
            'author' => $body['author_name'] ?? '',
            'loc'    => $body['location']    ?? '',
            'quote'  => $body['quote']       ?? '',
            'rating' => (int) ($body['rating'] ?? 5),
            'panel'  => $body['panel']       ?? 'dark',
            'id'     => $id,
        ]);
        send_ok(['updated' => $id]);
    }

    if ($method === 'DELETE') {
        $id = (int) ($_GET['id'] ?? $body['id'] ?? 0);
        if (!$id) send_err('Missing testimonial id');
        db_execute("UPDATE testimonials SET is_active = 0 WHERE id = :id", ['id' => $id]);
        send_ok(['deleted' => $id]);
    }

    send_err('Method not allowed', 405);
}


// ── NEWSLETTER ────────────────────────────────────────────────────────────────
function handle_newsletter(string $method, array $body): void {
    if ($method !== 'GET') send_err('Method not allowed', 405);

    $rows = db_fetch_all("
        SELECT id, email, status, subscribed_at
        FROM newsletter_subscribers
        ORDER BY subscribed_at DESC
    ");
    send_ok($rows);
}


// ── PROPERTY IMAGES ───────────────────────────────────────────────────────────
function handle_images(string $method, array $body): void {
    if ($method !== 'GET') send_err('Method not allowed', 405);

    $rows = db_fetch_all("
        SELECT
            pi.id, pi.image_url, pi.caption,
            pi.sort_order, pi.is_cover, pi.created_at,
            p.title AS property_title
        FROM property_images pi
        JOIN properties p ON p.id = pi.property_id
        ORDER BY pi.property_id ASC, pi.sort_order ASC
    ");
    send_ok($rows);
}


// ── PROPERTY VIEWS ────────────────────────────────────────────────────────────
function handle_views(string $method, array $body): void {
    if ($method !== 'GET') send_err('Method not allowed', 405);

    $rows = db_fetch_all("
        SELECT title, views, is_published
        FROM properties
        ORDER BY views DESC
    ");
    send_ok($rows);
}


// ── ADMIN USERS ───────────────────────────────────────────────────────────────
function handle_admin_users(string $method, array $body): void {
    if ($method !== 'GET') send_err('Method not allowed', 405);

    $rows = db_fetch_all("
        SELECT id, name, email, role, last_login, is_active, created_at
        FROM admin_users
        ORDER BY id ASC
    ");
    send_ok($rows);
}


// ── PUBLISH TOGGLE ────────────────────────────────────────────────────────────
function handle_publish(string $method, array $body): void {
    if ($method !== 'POST') send_err('Method not allowed', 405);

    $id  = (int) ($body['id'] ?? 0);
    $pub = (int) ($body['is_published'] ?? 0);
    if (!$id) send_err('Missing property id');

    db_execute(
        "UPDATE properties SET is_published = :pub WHERE id = :id",
        ['pub' => $pub ? 1 : 0, 'id' => $id]
    );
    send_ok(['id' => $id, 'is_published' => $pub ? 1 : 0]);
}