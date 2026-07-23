<?php
/**
 * Dwelro — Property Detail (property.php)
 * Loads a single property and all its images from the DB.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: listings.php'); exit; }

// ── Fetch property ────────────────────────────────────────────────────────────
$property = db_fetch_all("
    SELECT
        p.id, p.title, p.category, p.listing_type, p.status,
        p.price_display, p.price_sgd, p.district, p.area, p.country,
        p.cover_image_url, p.bedrooms, p.bathrooms, p.floor_area_sqft,
        p.is_featured, p.badge, p.description, p.address,
       p.tenure, p.property_type,
        p.created_at,
        a.full_name  AS agent_name,
        a.photo_url  AS agent_photo,
        a.title      AS agent_title,
        a.phone      AS agent_phone,
        a.email      AS agent_email,
        a.cea_number AS agent_cea
    FROM properties p
    LEFT JOIN agents a ON a.id = p.agent_id
    WHERE p.id = :id AND p.is_published = 1
    LIMIT 1
", ['id' => $id]);

if (empty($property)) { header('Location: listings.php'); exit; }
$p = $property[0];

// ── Fetch gallery images ──────────────────────────────────────────────────────
$images = db_fetch_all("
    SELECT image_url, caption, is_cover, sort_order
    FROM property_images
    WHERE property_id = :id
    ORDER BY is_cover DESC, sort_order ASC
", ['id' => $id]);

// If no gallery rows, fall back to cover image
if (empty($images) && $p['cover_image_url']) {
    $images = [['image_url' => $p['cover_image_url'], 'caption' => $p['title'], 'is_cover' => 1]];
}

// ── Increment view count (best-effort) ────────────────────────────────────────
try { db_execute("UPDATE properties SET views = COALESCE(views,0) + 1 WHERE id = :id", ['id' => $id]); } catch(Exception $e) {}

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function fv($v, string $suffix = ''): string { return ($v !== null && $v !== '') ? h($v) . $suffix : '—'; }

$images_json = json_encode(array_values($images), JSON_HEX_TAG | JSON_HEX_AMP);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title><?= h($p['title']) ?> — Dwelro</title>
    <meta name="description" content="<?= h(trim(preg_replace('/\s+/', ' ', strip_tags($p['description'] ?? 'Explore this property with Dwelro.')))) ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Dwelro">
    <meta property="og:title" content="<?= h($p['title']) ?> — Dwelro">
    <meta property="og:description" content="<?= h(trim(preg_replace('/\s+/', ' ', strip_tags($p['description'] ?? 'Explore this property with Dwelro.')))) ?>">
    <meta property="og:image" content="<?= h($images[0]['image_url'] ?? '/img/modern-luxury-home-with-pool-contemporary-architecture.jpg') ?>">
    <meta property="og:image:alt" content="<?= h($p['title']) ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= h($p['title']) ?> — Dwelro">
    <meta name="twitter:description" content="<?= h(trim(preg_replace('/\s+/', ' ', strip_tags($p['description'] ?? 'Explore this property with Dwelro.')))) ?>">
    <meta name="twitter:image" content="<?= h($images[0]['image_url'] ?? '/img/modern-luxury-home-with-pool-contemporary-architecture.jpg') ?>">
    <meta name="theme-color" content="#1a3a5c">
    <link rel="preconnect" href="https://fonts.googleapis.com"/>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        :root{
            --navy:#1a3a5c;--navy-dk:#0f2640;--navy-lt:#e8eef5;
            --ink:#171717;--muted:#6b7280;--border:#e5e7eb;
            --bg:#f8fafc;--white:#fff;--gold:#c9a84c;
            --serif:'DM Serif Display',serif;--sans:'DM Sans',sans-serif;
            --radius:8px;
        }
        body{font-family:var(--sans);background:var(--bg);color:var(--ink);-webkit-font-smoothing:antialiased}

        /* ── NAV ── */
        nav{position:sticky;top:0;z-index:200;background:var(--white);border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;padding:0 40px;height:64px}
        .nav-logo{font-family:var(--serif);font-size:22px;color:var(--navy);text-decoration:none}
        .nav-logo span{color:var(--gold)}
        .nav-back{display:inline-flex;align-items:center;gap:8px;font-size:11px;font-weight:700;text-transform:uppercase;color:var(--muted);text-decoration:none;transition:color .15s}
        .nav-back:hover{color:var(--navy)}
        .nav-back svg{width:14px;height:14px;fill:currentColor}

        /* ── LAYOUT ── */
        .page-wrap{max-width:1120px;margin:0 auto;padding:36px 40px 80px;display:grid;grid-template-columns:1fr 340px;gap:32px;align-items:start}

        /* ── LEFT COL ── */
        .left-col{display:flex;flex-direction:column;gap:28px}

        /* ── GALLERY ── */
        .gallery{border-radius:var(--radius);overflow:hidden;background:#111;position:relative}
        .gallery-main{position:relative;width:100%;aspect-ratio:16/10;overflow:hidden;cursor:zoom-in}
        .gallery-main img{width:100%;height:100%;object-fit:cover;display:block;transition:opacity .25s}
        .gallery-nav{position:absolute;top:50%;transform:translateY(-50%);display:flex;justify-content:space-between;width:100%;padding:0 16px;pointer-events:none}
        .gn-btn{pointer-events:all;width:40px;height:40px;border-radius:50%;background:rgba(0,0,0,.55);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .2s}
        .gn-btn:hover{background:rgba(0,0,0,.8)}
        .gn-btn svg{width:18px;height:18px;fill:white}
        .gallery-count{position:absolute;bottom:16px;right:16px;background:rgba(0,0,0,.6);color:white;font-size:11px;font-weight:700;padding:4px 10px;border-radius:20px}
        .gallery-caption{position:absolute;bottom:16px;left:16px;background:rgba(0,0,0,.55);color:white;font-size:11px;padding:4px 10px;border-radius:4px;max-width:60%;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}

        /* THUMBS */
        .gallery-thumbs{display:flex;gap:6px;padding:8px;background:#1a1a1a;overflow-x:auto;scrollbar-width:thin;scrollbar-color:#444 #1a1a1a}
        .gallery-thumbs::-webkit-scrollbar{height:4px}.gallery-thumbs::-webkit-scrollbar-thumb{background:#444;border-radius:2px}
        .g-thumb{width:80px;height:56px;flex-shrink:0;border-radius:4px;overflow:hidden;cursor:pointer;border:2px solid transparent;transition:border-color .15s;opacity:.7;transition:all .15s}
        .g-thumb:hover{opacity:1}
        .g-thumb.active{border-color:var(--gold);opacity:1}
        .g-thumb img{width:100%;height:100%;object-fit:cover;display:block}

        /* VIDEO SLOT */
        .video-section{border:1px solid var(--border);border-radius:var(--radius);overflow:hidden}
        .section-head{padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
        .section-title{font-size:13px;font-weight:700;color:var(--ink);text-transform:uppercase;letter-spacing:.5px}
        .video-placeholder{aspect-ratio:16/9;background:#f1f5f9;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;color:var(--muted)}
        .video-placeholder svg{width:48px;height:48px;fill:var(--border)}
        .video-placeholder p{font-size:13px;font-weight:500}
        .video-embed{width:100%;aspect-ratio:16/9}
        .video-embed iframe{width:100%;height:100%;border:none;display:block}

        /* PROPERTY DETAILS CARD */
        .details-card{background:var(--white);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden}
        .details-hero{padding:24px 24px 20px;border-bottom:1px solid var(--border)}
        .details-badges{display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap}
        .badge{padding:4px 12px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;border-radius:3px}
        .badge-sale{background:var(--gold);color:var(--navy-dk)}
        .badge-rent{background:#d1fae5;color:#065f46}
        .badge-cat{background:var(--navy-lt);color:var(--navy)}
        .badge-featured{background:#fef3c7;color:#92400e}
        .badge-status{background:#f1f5f9;color:#475569}
        .details-price{font-family:var(--serif);font-size:36px;color:var(--navy);font-weight:400;line-height:1;margin-bottom:8px}
        .details-title{font-size:20px;font-weight:700;color:var(--ink);margin-bottom:6px;line-height:1.3}
        .details-location{font-size:13px;color:var(--muted)}

        /* KEY STATS */
        .key-stats{display:grid;grid-template-columns:repeat(3,1fr);border-bottom:1px solid var(--border)}
        .ks-item{padding:18px 16px;text-align:center;border-right:1px solid var(--border)}
        .ks-item:last-child{border-right:none}
        .ks-val{font-size:22px;font-weight:800;color:var(--navy);font-family:var(--serif)}
        .ks-lbl{font-size:10px;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);margin-top:2px}

        /* SPEC TABLE */
        .spec-section{padding:20px 24px}
        .spec-title{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--gold);margin-bottom:14px}
        .spec-grid{display:grid;grid-template-columns:1fr 1fr;gap:0;border:1px solid var(--border);border-radius:6px;overflow:hidden}
        .spec-row{display:contents}
        .spec-row:nth-child(even) .spec-k,
        .spec-row:nth-child(even) .spec-v{background:#fafbfc}
        .spec-k{padding:10px 14px;font-size:12px;font-weight:600;color:var(--muted);border-bottom:1px solid var(--border);border-right:1px solid var(--border)}
        .spec-v{padding:10px 14px;font-size:12px;font-weight:600;color:var(--ink);border-bottom:1px solid var(--border)}
        .spec-row:last-child .spec-k,
        .spec-row:last-child .spec-v{border-bottom:none}

        /* DESCRIPTION */
        .desc-section{padding:0 24px 24px}
        .desc-text{font-size:14px;line-height:1.8;color:#374151;white-space:pre-line}
        .desc-empty{font-size:13px;color:var(--muted);font-style:italic}

        /* ── RIGHT COL (STICKY SIDEBAR) ── */
        .right-col{display:flex;flex-direction:column;gap:20px;position:sticky;top:84px}

        /* AGENT CARD */
        .agent-card{background:var(--white);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden}
        .agent-head{background:var(--navy);padding:20px;display:flex;align-items:center;gap:14px}
        .agent-avatar{width:56px;height:56px;border-radius:50%;object-fit:cover;border:2px solid rgba(255,255,255,.25);flex-shrink:0}
        .agent-avatar-placeholder{width:56px;height:56px;border-radius:50%;background:rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;flex-shrink:0}
        .agent-avatar-placeholder svg{width:28px;height:28px;fill:rgba(255,255,255,.6)}
        .agent-info-name{font-size:15px;font-weight:700;color:white}
        .agent-info-title{font-size:11px;color:rgba(255,255,255,.65);margin-top:2px}
        .agent-info-cea{font-size:10px;color:var(--gold);font-weight:700;margin-top:3px}
        .agent-body{padding:16px 20px;display:flex;flex-direction:column;gap:10px}
        .agent-contact-row{display:flex;align-items:center;gap:10px;font-size:13px;color:var(--ink)}
        .agent-contact-row svg{width:15px;height:15px;fill:var(--muted);flex-shrink:0}
        .agent-contact-row a{color:var(--navy);text-decoration:none;font-weight:500}
        .agent-contact-row a:hover{text-decoration:underline}
        .agent-no-data{padding:20px;font-size:13px;color:var(--muted);text-align:center}

        /* ENQUIRY FORM */
        .enquiry-card{background:var(--white);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden}
        .enquiry-head{background:var(--gold);padding:14px 20px}
        .enquiry-head-title{font-size:13px;font-weight:700;color:var(--navy-dk);text-transform:uppercase;letter-spacing:.5px}
        .enquiry-head-sub{font-size:11px;color:rgba(26,58,92,.6);margin-top:2px}
        .enquiry-body{padding:18px 20px;display:flex;flex-direction:column;gap:12px}
        .eq-field label{display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--muted);margin-bottom:5px}
        .eq-field input,.eq-field textarea,.eq-field select{width:100%;border:1px solid var(--border);border-radius:6px;padding:9px 12px;font-family:var(--sans);font-size:13px;color:var(--ink);outline:none;transition:border-color .15s}
        .eq-field input:focus,.eq-field textarea:focus,.eq-field select:focus{border-color:var(--navy)}
        .eq-field textarea{resize:vertical;min-height:72px;line-height:1.5}
        .eq-row{display:grid;grid-template-columns:1fr 1fr;gap:10px}
        .eq-submit{width:100%;padding:12px;background:var(--navy);color:white;border:none;font-family:var(--sans);font-size:13px;font-weight:700;cursor:pointer;border-radius:6px;text-transform:uppercase;letter-spacing:.5px;transition:background .15s}
        .eq-submit:hover{background:var(--navy-dk)}
        .eq-success{display:none;background:#d1fae5;border:1px solid #a7f3d0;color:#065f46;padding:12px 16px;border-radius:6px;font-size:13px;font-weight:600;text-align:center}

        /* LIGHTBOX */
        .lightbox{position:fixed;inset:0;background:rgba(0,0,0,.92);z-index:1000;display:none;align-items:center;justify-content:center}
        .lightbox.open{display:flex}
        .lb-img{max-width:90vw;max-height:88vh;object-fit:contain;border-radius:4px}
        .lb-close{position:absolute;top:20px;right:24px;color:white;font-size:28px;cursor:pointer;background:none;border:none;line-height:1;opacity:.7}
        .lb-close:hover{opacity:1}
        .lb-nav{position:absolute;top:50%;transform:translateY(-50%);display:flex;justify-content:space-between;width:100%;padding:0 20px;pointer-events:none}
        .lb-nav-btn{pointer-events:all;background:rgba(255,255,255,.15);border:none;color:white;width:48px;height:48px;border-radius:50%;cursor:pointer;font-size:20px;display:flex;align-items:center;justify-content:center}
        .lb-nav-btn:hover{background:rgba(255,255,255,.3)}
        .lb-caption{position:absolute;bottom:24px;left:50%;transform:translateX(-50%);color:rgba(255,255,255,.7);font-size:12px;white-space:nowrap}

        /* RESPONSIVE */
        @media(max-width:900px){
            .page-wrap{grid-template-columns:1fr;padding:20px}
            .right-col{position:static}
            nav{padding:0 20px}
        }
        @media(max-width:600px){
            .key-stats{grid-template-columns:1fr 1fr}
            .spec-grid{grid-template-columns:1fr}
            .spec-k{border-right:none;border-bottom:none;padding-bottom:2px;color:var(--navy);font-size:10px;text-transform:uppercase;letter-spacing:.5px}
            .spec-v{border-bottom:1px solid var(--border)}
        }
    </style>
</head>
<body>

<nav>
    <a href="index.php" class="nav-logo">dwelro</a>
    <a href="listings.php" class="nav-back">
        <svg viewBox="0 0 24 24"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
        Back to Listings
    </a>
</nav>

<div class="page-wrap">

    <!-- ── LEFT COLUMN ── -->
    <div class="left-col">

        <!-- GALLERY -->
        <div class="gallery" id="gallery">
            <div class="gallery-main" onclick="Lightbox.open(Gallery.current)">
                <img id="galleryMain" src="" alt="<?= h($p['title']) ?>"/>
                <div class="gallery-nav">
                    <button class="gn-btn" onclick="event.stopPropagation();Gallery.prev()">
                        <svg viewBox="0 0 24 24"><path d="M15.41 16.59L10.83 12l4.58-4.59L14 6l-6 6 6 6z"/></svg>
                    </button>
                    <button class="gn-btn" onclick="event.stopPropagation();Gallery.next()">
                        <svg viewBox="0 0 24 24"><path d="M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6z"/></svg>
                    </button>
                </div>
                <div class="gallery-count" id="galleryCount"></div>
                <div class="gallery-caption" id="galleryCaption"></div>
            </div>
            <?php if (count($images) > 1): ?>
            <div class="gallery-thumbs" id="galleryThumbs"></div>
            <?php endif; ?>
        </div>

        <!-- VIDEO SLOT -->
        <div class="video-section">
            <div class="section-head">
                <span class="section-title">Property Video</span>
            </div>
            <?php
            // Check for a video_url column — if not available, show placeholder
            $video_url = $p['video_url'] ?? null;
            if ($video_url):
                // Support YouTube / Vimeo embeds
                $embed = $video_url;
                if (preg_match('/youtube\.com\/watch\?v=([\w-]+)/', $video_url, $m)) {
                    $embed = 'https://www.youtube.com/embed/' . $m[1];
                } elseif (preg_match('/youtu\.be\/([\w-]+)/', $video_url, $m)) {
                    $embed = 'https://www.youtube.com/embed/' . $m[1];
                } elseif (preg_match('/vimeo\.com\/(\d+)/', $video_url, $m)) {
                    $embed = 'https://player.vimeo.com/video/' . $m[1];
                }
            ?>
            <div class="video-embed">
                <iframe src="<?= h($embed) ?>" allowfullscreen allow="autoplay; encrypted-media"></iframe>
            </div>
            <?php else: ?>
            <div class="video-placeholder">
                <svg viewBox="0 0 24 24"><path d="M10 16.5l6-4.5-6-4.5v9zM12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"/></svg>
                <p>No video available for this property</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- PROPERTY DETAILS -->
        <div class="details-card">

            <!-- Hero: price, title, badges -->
            <div class="details-hero">
                <div class="details-badges">
                    <?php if ($p['listing_type']): ?>
                    <span class="badge badge-<?= h($p['listing_type']) ?>"><?= $p['listing_type'] === 'sale' ? 'For Sale' : 'For Rent' ?></span>
                    <?php endif; ?>
                    <?php if ($p['category']): ?>
                    <span class="badge badge-cat"><?= h(str_replace('_', ' ', $p['category'])) ?></span>
                    <?php endif; ?>
                    <?php if ($p['is_featured']): ?>
                    <span class="badge badge-featured">Featured</span>
                    <?php endif; ?>
                    <?php if ($p['status']): ?>
                    <span class="badge badge-status"><?= h(str_replace('_', ' ', $p['status'])) ?></span>
                    <?php endif; ?>
                </div>
                <div class="details-price"><?= h($p['price_display'] ?? '—') ?></div>
                <div class="details-title"><?= h($p['title']) ?></div>
                <div class="details-location">
                    <?= implode(', ', array_filter([h($p['address'] ?? ''), h($p['area'] ?? ''), h($p['district'] ?? ''), h($p['country'] ?? '')])) ?>
                </div>
            </div>

            <!-- Key stats: beds/baths/sqft -->
            <div class="key-stats">
                <div class="ks-item">
                    <div class="ks-val"><?= (int)($p['bedrooms'] ?? 0) ?></div>
                    <div class="ks-lbl">Bedrooms</div>
                </div>
                <div class="ks-item">
                    <div class="ks-val"><?= (int)($p['bathrooms'] ?? 0) ?></div>
                    <div class="ks-lbl">Bathrooms</div>
                </div>
                <div class="ks-item">
                    <div class="ks-val"><?= $p['floor_area_sqft'] ? number_format((float)$p['floor_area_sqft']) : '—' ?></div>
                    <div class="ks-lbl">Sqft</div>
                </div>
            </div>

            <!-- Spec table -->
            <div class="spec-section">
                <div class="spec-title">Property Details</div>
                <div class="spec-grid">
                    <div class="spec-row">
                        <div class="spec-k">Property Type</div>
                        <div class="spec-v"><?= fv($p['property_type'] ?? null) ?></div>
                    </div>
                    <div class="spec-row">
                        <div class="spec-k">Category</div>
                        <div class="spec-v"><?= $p['category'] ? h(ucwords(str_replace('_',' ',$p['category']))) : '—' ?></div>
                    </div>
                    <div class="spec-row">
                        <div class="spec-k">Listing Type</div>
                        <div class="spec-v"><?= $p['listing_type'] ? h(ucfirst($p['listing_type'])) : '—' ?></div>
                    </div>
                    <div class="spec-row">
                        <div class="spec-k">Status</div>
                        <div class="spec-v"><?= $p['status'] ? h(ucwords(str_replace('_',' ',$p['status']))) : '—' ?></div>
                    </div>
                    <div class="spec-row">
                        <div class="spec-k">Floor Area</div>
                        <div class="spec-v"><?= $p['floor_area_sqft'] ? number_format((float)$p['floor_area_sqft']) . ' sqft' : '—' ?></div>
                    </div>
                    <div class="spec-row">
                        <div class="spec-k">Bedrooms</div>
                        <div class="spec-v"><?= fv($p['bedrooms'] ?? null) ?></div>
                    </div>
                    <div class="spec-row">
                        <div class="spec-k">Bathrooms</div>
                        <div class="spec-v"><?= fv($p['bathrooms'] ?? null) ?></div>
                    </div>
                    <div class="spec-row">
                        <div class="spec-k">Tenure</div>
                        <div class="spec-v"><?= fv($p['tenure'] ?? null) ?></div>
                    </div>
                    <div class="spec-row">
                        <div class="spec-k">Built Year</div>
                        <div class="spec-v"><?= fv($p['built_year'] ?? null) ?></div>
                    </div>
                    <div class="spec-row">
                        <div class="spec-k">District</div>
                        <div class="spec-v"><?= fv($p['district'] ?? null) ?></div>
                    </div>
                    <div class="spec-row">
                        <div class="spec-k">Area</div>
                        <div class="spec-v"><?= fv($p['area'] ?? null) ?></div>
                    </div>
                    <div class="spec-row">
                        <div class="spec-k">Country</div>
                        <div class="spec-v"><?= fv($p['country'] ?? null) ?></div>
                    </div>
                </div>
            </div>

            <!-- Description -->
            <?php if (!empty($p['description'])): ?>
            <div class="desc-section">
                <div class="spec-title">About This Property</div>
                <div class="desc-text"><?= h($p['description']) ?></div>
            </div>
            <?php else: ?>
            <div class="desc-section">
                <div class="desc-empty">No description provided for this listing.</div>
            </div>
            <?php endif; ?>

        </div><!-- /details-card -->

    </div><!-- /left-col -->

    <!-- ── RIGHT COLUMN ── -->
    <div class="right-col">

        <!-- AGENT CARD -->
        <div class="agent-card">
            <?php if ($p['agent_name']): ?>
            <div class="agent-head">
                <?php if ($p['agent_photo']): ?>
                <img class="agent-avatar" src="<?= h($p['agent_photo']) ?>"
                     onerror="this.style.display='none'" alt="<?= h($p['agent_name']) ?>"/>
                <?php else: ?>
                <div class="agent-avatar-placeholder">
                    <svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                </div>
                <?php endif; ?>
                <div>
                    <div class="agent-info-name"><?= h($p['agent_name']) ?></div>
                    <?php if ($p['agent_title']): ?>
                    <div class="agent-info-title"><?= h($p['agent_title']) ?></div>
                    <?php endif; ?>
                    <?php if ($p['agent_cea']): ?>
                    <div class="agent-info-cea">CEA <?= h($p['agent_cea']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="agent-body">
                <?php if ($p['agent_phone']): ?>
                <div class="agent-contact-row">
                    <svg viewBox="0 0 24 24"><path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/></svg>
                    <a href="tel:<?= h($p['agent_phone']) ?>"><?= h($p['agent_phone']) ?></a>
                </div>
                <?php endif; ?>
                <?php if ($p['agent_email']): ?>
                <div class="agent-contact-row">
                    <svg viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
                    <a href="mailto:<?= h($p['agent_email']) ?>"><?= h($p['agent_email']) ?></a>
                </div>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="agent-no-data">No agent assigned to this listing.</div>
            <?php endif; ?>
        </div>

        <!-- ENQUIRY FORM -->
        <div class="enquiry-card">
            <div class="enquiry-head">
                <div class="enquiry-head-title">Enquire About This Property</div>
                <div class="enquiry-head-sub">We'll get back to you within 24 hours</div>
            </div>
            <form class="enquiry-body" id="enquiryForm" method="POST" action="api.php?action=enquiry">
                <input type="hidden" name="property_id" value="<?= (int)$p['id'] ?>"/>
                <input type="hidden" name="property_title" value="<?= h($p['title']) ?>"/>
                <div class="eq-row">
                    <div class="eq-field"><label>First Name</label><input type="text" name="first_name" placeholder="John" required/></div>
                    <div class="eq-field"><label>Last Name</label><input type="text" name="last_name" placeholder="Smith" required/></div>
                </div>
                <div class="eq-field"><label>Email</label><input type="email" name="email" placeholder="john@example.com" required/></div>
                <div class="eq-field"><label>Phone</label><input type="tel" name="phone" placeholder="+65 9123 4567"/></div>
                <div class="eq-field">
                    <label>Enquiry Type</label>
                    <select name="enquiry_type">
                        <option value="viewing">Schedule a Viewing</option>
                        <option value="information">Request Information</option>
                        <option value="offer">Make an Offer</option>
                        <option value="general">General Enquiry</option>
                    </select>
                </div>
                <div class="eq-row">
                    <div class="eq-field"><label>Preferred Date</label><input type="date" name="preferred_date"/></div>
                    <div class="eq-field"><label>Preferred Time</label><input type="time" name="preferred_time"/></div>
                </div>
                <div class="eq-field"><label>Message</label><textarea name="message" placeholder="I am interested in this property and would like to..."></textarea></div>
                <button type="submit" class="eq-submit">Send Enquiry</button>
                <div class="eq-success" id="eqSuccess">Your enquiry has been sent. We will be in touch shortly.</div>
            </form>
        </div>

    </div><!-- /right-col -->

</div><!-- /page-wrap -->

<!-- LIGHTBOX -->
<div class="lightbox" id="lightbox" onclick="if(event.target===this)Lightbox.close()">
    <button class="lb-close" onclick="Lightbox.close()">&times;</button>
    <img class="lb-img" id="lbImg" src="" alt=""/>
    <div class="lb-nav">
        <button class="lb-nav-btn" onclick="Lightbox.prev()">&#8592;</button>
        <button class="lb-nav-btn" onclick="Lightbox.next()">&#8594;</button>
    </div>
    <div class="lb-caption" id="lbCaption"></div>
</div>

<script>
const IMAGES = <?= $images_json ?>;

// ── Gallery ──────────────────────────────────────────────────────────────────
const Gallery = (() => {
    let cur = 0;

    function show(idx) {
        if (!IMAGES.length) return;
        cur = (idx + IMAGES.length) % IMAGES.length;
        const img = IMAGES[cur];
        const el = document.getElementById('galleryMain');
        el.style.opacity = '0';
        setTimeout(() => {
            el.src = img.image_url || 'https://placehold.co/900x560/e8eef5/1a3a5c?text=No+Image';
            el.onload = () => { el.style.opacity = '1'; };
            el.onerror = () => { el.src = 'https://placehold.co/900x560/e8eef5/1a3a5c?text=No+Image'; el.style.opacity='1'; };
        }, 100);

        const cap = document.getElementById('galleryCaption');
        if (cap) cap.textContent = img.caption || '';

        const cnt = document.getElementById('galleryCount');
        if (cnt) cnt.textContent = (cur + 1) + ' / ' + IMAGES.length;

        // update thumbs
        document.querySelectorAll('.g-thumb').forEach((t, i) => {
            t.classList.toggle('active', i === cur);
            if (i === cur) t.scrollIntoView({behavior:'smooth', block:'nearest', inline:'center'});
        });
    }

    function init() {
        if (!IMAGES.length) {
            document.getElementById('gallery').style.display = 'none';
            return;
        }
        // render thumbs
        const thumbs = document.getElementById('galleryThumbs');
        if (thumbs) {
            thumbs.innerHTML = IMAGES.map((img, i) => `
                <div class="g-thumb ${i===0?'active':''}" onclick="Gallery.show(${i})">
                    <img src="${img.image_url}" alt="${(img.caption||'').replace(/"/g,'&quot;')}"
                         onerror="this.src='https://placehold.co/80x56/e8eef5/1a3a5c?text=...'"/>
                </div>`).join('');
        }
        show(0);
    }

    return { init, show, prev: () => show(cur - 1), next: () => show(cur + 1), get current() { return cur; } };
})();

// ── Lightbox ─────────────────────────────────────────────────────────────────
const Lightbox = (() => {
    let cur = 0;
    function open(idx) {
        cur = idx;
        update();
        document.getElementById('lightbox').classList.add('open');
        document.body.style.overflow = 'hidden';
    }
    function close() {
        document.getElementById('lightbox').classList.remove('open');
        document.body.style.overflow = '';
    }
    function update() {
        if (!IMAGES.length) return;
        cur = (cur + IMAGES.length) % IMAGES.length;
        document.getElementById('lbImg').src = IMAGES[cur].image_url;
        document.getElementById('lbCaption').textContent = IMAGES[cur].caption || '';
    }
    function prev() { cur--; update(); }
    function next() { cur++; update(); }

    document.addEventListener('keydown', e => {
        if (!document.getElementById('lightbox').classList.contains('open')) return;
        if (e.key === 'ArrowLeft')  prev();
        if (e.key === 'ArrowRight') next();
        if (e.key === 'Escape')     close();
    });

    return { open, close, prev, next };
})();

// ── Enquiry form (AJAX) ──────────────────────────────────────────────────────
document.getElementById('enquiryForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = this.querySelector('.eq-submit');
    btn.textContent = 'Sending...';
    btn.disabled = true;

    fetch('api.php?action=enquiry', { method: 'POST', body: new FormData(this) })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('eqSuccess').style.display = 'block';
                this.querySelectorAll('input,textarea,select').forEach(f => f.value = '');
                btn.style.display = 'none';
            } else {
                throw new Error(data.error || 'Something went wrong. Please try again.');
            }
        })
        .catch(err => {
            btn.textContent = 'Send Enquiry';
            btn.disabled = false;
            alert(err.message);
        });
});

// ── Boot ─────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', Gallery.init);
</script>
</body>
</html>
