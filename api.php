<?php
// ============================================================
//  DWELRO — JSON REST API
//  api.php
//
//  Endpoints:
//    GET  api.php?action=properties            All published properties
//    GET  api.php?action=properties&id=3       Single property
//    GET  api.php?action=properties&category=condo
//    GET  api.php?action=properties&featured=1
//    GET  api.php?action=agents                All active agents
//    GET  api.php?action=agents&id=2           Single agent
//    GET  api.php?action=testimonials          All active testimonials
//    POST api.php?action=enquiry               Submit booking/enquiry
//    POST api.php?action=newsletter            Subscribe to newsletter
// ============================================================

require_once __DIR__ . '/config.php';

// ── CORS & headers ──────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');          // tighten in production
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Router ──────────────────────────────────────────────────
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($action) {

        // ── GET PROPERTIES ───────────────────────────────────
        case 'properties':
            if ($method !== 'GET') methodNotAllowed();

            $db   = getDB();
            $id   = isset($_GET['id'])       ? (int)$_GET['id']       : null;
            $cat  = $_GET['category']        ?? null;
            $feat = isset($_GET['featured']) ? (int)$_GET['featured'] : null;
            $q    = $_GET['q']               ?? null;   // search query
            $dist = $_GET['district']        ?? null;

            if ($id) {
                // Single property + agent info
                $stmt = $db->prepare("
                    SELECT p.*,
                           a.full_name  AS agent_name,
                           a.phone      AS agent_phone,
                           a.whatsapp   AS agent_whatsapp,
                           a.photo_url  AS agent_photo,
                           a.title      AS agent_title,
                           a.cea_number AS agent_cea
                    FROM properties p
                    LEFT JOIN agents a ON a.id = p.agent_id
                    WHERE p.id = :id AND p.is_published = 1
                ");
                $stmt->execute([':id' => $id]);
                $row = $stmt->fetch();

                if (!$row) { notFound('Property not found'); }

                // Decode JSON columns
                $row['features']   = json_decode($row['features']   ?? '[]');
                $row['image_urls'] = json_decode($row['image_urls']  ?? '[]');

                // Fetch normalised images
                $imgs = $db->prepare("SELECT image_url, caption, sort_order, is_cover FROM property_images WHERE property_id = :id ORDER BY sort_order");
                $imgs->execute([':id' => $id]);
                $row['images'] = $imgs->fetchAll();

                // Record page view (best-effort, non-blocking)
                $db->prepare("UPDATE properties SET views = views + 1 WHERE id = :id")->execute([':id' => $id]);
                $db->prepare("INSERT INTO property_views (property_id, ip_address) VALUES (:pid, :ip)")
                   ->execute([':pid' => $id, ':ip' => $_SERVER['REMOTE_ADDR'] ?? null]);

                json($row);

            } else {
                // List with optional filters
                $where  = ['p.is_published = 1'];
                $params = [];

                if ($cat)  { $where[] = 'p.category = :cat';           $params[':cat']  = $cat;  }
                if ($feat) { $where[] = 'p.is_featured = 1';                                       }
                if ($dist) { $where[] = 'p.district = :dist';          $params[':dist'] = $dist; }
                if ($q)    {
                    $where[] = '(p.title LIKE :q OR p.area LIKE :q OR p.description LIKE :q)';
                    $params[':q'] = '%' . $q . '%';
                }

                $sql = "
                    SELECT p.id, p.title, p.slug, p.badge, p.category, p.listing_type,
                           p.status, p.district, p.area, p.country,
                           p.price_display, p.price_sgd, p.price_psf, p.rental_pcm,
                           p.bedrooms, p.bathrooms, p.floor_area_sqft, p.tenure,
                           p.cover_image_url, p.is_featured, p.views, p.created_at,
                           a.full_name AS agent_name, a.phone AS agent_phone, a.whatsapp AS agent_whatsapp
                    FROM properties p
                    LEFT JOIN agents a ON a.id = p.agent_id
                    WHERE " . implode(' AND ', $where) . "
                    ORDER BY p.is_featured DESC, p.created_at DESC
                ";
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                json($stmt->fetchAll());
            }
            break;

        // ── GET AGENTS ───────────────────────────────────────
        case 'agents':
            if ($method !== 'GET') methodNotAllowed();

            $db = getDB();
            $id = isset($_GET['id']) ? (int)$_GET['id'] : null;

            if ($id) {
                $stmt = $db->prepare("SELECT * FROM agents WHERE id = :id AND is_active = 1");
                $stmt->execute([':id' => $id]);
                $row = $stmt->fetch();
                if (!$row) notFound('Agent not found');

                // Agent's listings
                $listings = $db->prepare("
                    SELECT id, title, slug, category, price_display, cover_image_url, area, district, status
                    FROM properties WHERE agent_id = :id AND is_published = 1 ORDER BY is_featured DESC
                ");
                $listings->execute([':id' => $id]);
                $row['listings'] = $listings->fetchAll();

                json($row);
            } else {
                $stmt = $db->query("
                    SELECT id, full_name, email, phone, whatsapp, title, specialisation,
                           bio, photo_url, years_exp, deals_closed, portfolio_sgd
                    FROM agents WHERE is_active = 1 ORDER BY deals_closed DESC
                ");
                json($stmt->fetchAll());
            }
            break;

        // ── GET TESTIMONIALS ─────────────────────────────────
        case 'testimonials':
            if ($method !== 'GET') methodNotAllowed();

            $db    = getDB();
            $panel = $_GET['panel'] ?? null;   // 'dark' or 'light'
            $where = 'is_active = 1';
            $params = [];
            if ($panel) { $where .= ' AND panel = :panel'; $params[':panel'] = $panel; }

            $stmt = $db->prepare("SELECT * FROM testimonials WHERE $where ORDER BY panel, sort_order");
            $stmt->execute($params);
            json($stmt->fetchAll());
            break;

        // ── POST ENQUIRY (Book Viewing / Lead) ───────────────
        case 'enquiry':
            if ($method !== 'POST') methodNotAllowed();

            $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;

            // Validate required fields
            $required = ['first_name', 'last_name', 'email', 'phone'];
            foreach ($required as $f) {
                if (empty($body[$f])) error(400, "Field '$f' is required.");
            }

            if (!filter_var($body['email'], FILTER_VALIDATE_EMAIL)) {
                error(400, 'Invalid email address.');
            }

            // Map enquiry_type from form to DB enum
            $enquiry_type_map = [
                'viewing' => 'book_viewing',
                'information' => 'general',
                'offer' => 'general',
                'general' => 'general'
            ];
            $enquiry_type = $enquiry_type_map[$body['enquiry_type']] ?? 'book_viewing';

            // Convert preferred_time to enum
            $preferred_time = null;
            if (!empty($body['preferred_time'])) {
                $time = strtotime($body['preferred_time']);
                if ($time !== false) {
                    $hour = (int)date('H', $time);
                    if ($hour < 12) $preferred_time = 'morning';
                    elseif ($hour < 17) $preferred_time = 'afternoon';
                    else $preferred_time = 'evening';
                }
            }

            $db = getDB();
            $stmt = $db->prepare("
                INSERT INTO enquiries
                  (property_id, agent_id, first_name, last_name, email, phone,
                   enquiry_type, preferred_date, preferred_time, message, source, ip_address, user_agent)
                VALUES
                  (:property_id, :agent_id, :first_name, :last_name, :email, :phone,
                   :enquiry_type, :preferred_date, :preferred_time, :message, :source, :ip, :ua)
            ");
            $stmt->execute([
                ':property_id'   => !empty($body['property_id']) ? (int)$body['property_id'] : null,
                ':agent_id'      => !empty($body['agent_id'])    ? (int)$body['agent_id']    : null,
                ':first_name'    => trim($body['first_name']),
                ':last_name'     => trim($body['last_name']),
                ':email'         => strtolower(trim($body['email'])),
                ':phone'         => trim($body['phone']),
                ':enquiry_type'  => $enquiry_type,
                ':preferred_date'=> $body['preferred_date'] ?? null,
                ':preferred_time'=> $preferred_time,
                ':message'       => $body['message']        ?? null,
                ':source'        => $body['source']         ?? 'website',
                ':ip'            => $_SERVER['REMOTE_ADDR'] ?? null,
                ':ua'            => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ]);

            json(['success' => true, 'id' => (int)$db->lastInsertId(), 'message' => 'Enquiry received. An agent will contact you shortly.']);
            break;

        // ── POST NEWSLETTER SUBSCRIBE ─────────────────────────
        case 'newsletter':
            if ($method !== 'POST') methodNotAllowed();

            $body  = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $email = strtolower(trim($body['email'] ?? ''));

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                error(400, 'Invalid email address.');
            }

            $db = getDB();

            // Check if already subscribed
            $check = $db->prepare("SELECT id, status FROM newsletter_subscribers WHERE email = :email");
            $check->execute([':email' => $email]);
            $existing = $check->fetch();

            if ($existing) {
                if ($existing['status'] === 'active') {
                    json(['success' => true, 'message' => 'You are already subscribed!']);
                } else {
                    // Re-subscribe
                    $db->prepare("UPDATE newsletter_subscribers SET status = 'active', unsubscribed_at = NULL WHERE email = :email")
                       ->execute([':email' => $email]);
                    json(['success' => true, 'message' => 'Welcome back! You have been re-subscribed.']);
                }
            } else {
                $db->prepare("INSERT INTO newsletter_subscribers (email, ip_address) VALUES (:email, :ip)")
                   ->execute([':email' => $email, ':ip' => $_SERVER['REMOTE_ADDR'] ?? null]);
                json(['success' => true, 'message' => 'Thank you for subscribing!']);
            }
            break;

        // ── GET MAP PROPERTIES ───────────────────────────────
        case 'map-properties':
            if ($method !== 'GET') methodNotAllowed();

            $db = getDB();
            $category = $_GET['category'] ?? null;
            $listing_type = $_GET['listing_type'] ?? null;
            $min_price = isset($_GET['min_price']) ? (int)$_GET['min_price'] : null;
            $max_price = isset($_GET['max_price']) ? (int)$_GET['max_price'] : null;
            $bedrooms = isset($_GET['bedrooms']) ? (int)$_GET['bedrooms'] : null;

            $where = ['p.is_published = 1', 'p.latitude IS NOT NULL', 'p.longitude IS NOT NULL'];
            $params = [];

            if ($category) {
                $where[] = 'p.category = :category';
                $params[':category'] = $category;
            }

            if ($listing_type) {
                if ($listing_type === 'sale') {
                    $where[] = "p.listing_type IN ('sale', 'both')";
                } elseif ($listing_type === 'rent') {
                    $where[] = "p.listing_type IN ('rent', 'both')";
                }
            }

            if ($min_price !== null) {
                $where[] = 'p.price_sgd >= :min_price';
                $params[':min_price'] = $min_price;
            }

            if ($max_price !== null) {
                $where[] = 'p.price_sgd <= :max_price';
                $params[':max_price'] = $max_price;
            }

            if ($bedrooms !== null) {
                $where[] = 'p.bedrooms >= :bedrooms';
                $params[':bedrooms'] = $bedrooms;
            }

            $sql = "
                SELECT
                    p.id, p.title, p.category, p.listing_type, p.status,
                    p.price_display, p.price_sgd, p.district, p.area, p.address, p.country,
                    p.latitude, p.longitude, p.bedrooms, p.bathrooms, p.floor_area_sqft,
                    p.cover_image_url, p.is_featured, p.created_at,
                    a.full_name AS agent_name, a.phone AS agent_phone
                FROM properties p
                LEFT JOIN agents a ON a.id = p.agent_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY p.is_featured DESC, p.created_at DESC
                LIMIT 500
            ";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $properties = $stmt->fetchAll();

            // Format for map consumption
            $formatted = array_map(function($p) {
                return [
                    'id' => $p['id'],
                    'title' => $p['title'],
                    'category' => $p['category'],
                    'listing_type' => $p['listing_type'],
                    'status' => $p['status'],
                    'price_display' => $p['price_display'],
                    'price' => $p['price_sgd'],
                    'district' => $p['district'],
                    'area' => $p['area'],
                    'address' => $p['address'],
                    'country' => $p['country'],
                    'latitude' => (float)$p['latitude'],
                    'longitude' => (float)$p['longitude'],
                    'bedrooms' => (int)$p['bedrooms'],
                    'bathrooms' => (int)$p['bathrooms'],
                    'floor_area_sqft' => (int)$p['floor_area_sqft'],
                    'cover_image_url' => $p['cover_image_url'],
                    'is_featured' => (bool)$p['is_featured'],
                    'agent_name' => $p['agent_name'],
                    'agent_phone' => $p['agent_phone'],
                    'created_at' => $p['created_at']
                ];
            }, $properties);

            json($formatted);
            break;

        default:
            error(404, 'Unknown action. Valid actions: properties, agents, testimonials, enquiry, newsletter, map-properties');
    }

} catch (PDOException $e) {
    // Don't expose SQL errors in production — log them instead
    error_log('[Dwelro API] DB Error: ' . $e->getMessage());
    error(500, 'A database error occurred. Please try again later.');
} catch (Exception $e) {
    error(500, $e->getMessage());
}

// ── Helpers ─────────────────────────────────────────────────
function json(mixed $data, int $code = 200): never {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function error(int $code, string $msg): never {
    json(['success' => false, 'error' => $msg], $code);
}

function notFound(string $msg = 'Not found'): never {
    error(404, $msg);
}

function methodNotAllowed(): never {
    error(405, 'Method not allowed.');
}