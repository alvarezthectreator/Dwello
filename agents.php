<?php
/**
 * Dwelro — Agents Page (agents.php)
 * Pulls agent data live from the `agents` table.
 * Matches the visual design of agents.html exactly.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// ── Helpers ───────────────────────────────────────────────────────────────────
function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function vol_display(int $v): string {
    if ($v >= 1_000_000_000) return '$' . round($v / 1_000_000_000, 1) . 'B';
    if ($v >= 1_000_000)     return '$' . round($v / 1_000_000) . 'M';
    return '$' . number_format($v);
}

// ── Fetch all active agents from DB ──────────────────────────────────────────
$agents = db_fetch_all("
    SELECT id, full_name, email, phone, whatsapp, title, specialisation,
           cea_number, bio, photo_url, years_exp, deals_closed, portfolio_sgd
    FROM agents
    WHERE is_active = 1
    ORDER BY portfolio_sgd DESC, deals_closed DESC
");

$total_agents = count($agents);

// Top 3 are featured performers
$featured   = [
    [
        'full_name' => 'Ryan Serhant',
        'title' => 'Founder & CEO, SERHANT.',
        'specialisation' => 'New York City luxury, media-led marketing, investment sales',
        'bio' => 'One of the best-known brokers in the United States, Ryan Serhant leads a tech-forward brokerage built around luxury listings, content, and brand reach.',
        'photo_url' => 'https://dot.la/media-library/serhant-founder-ryan-serhant.jpg',
        'years_exp' => 15,
        'deals_closed' => 1200,
        'portfolio_sgd' => 15000000000,
    ],
    [
        'full_name' => 'Josh Altman',
        'title' => 'Luxury Property Specialist, The Altman Brothers',
        'specialisation' => 'Los Angeles luxury, celebrity estates, record-breaking sales',
        'bio' => 'Josh Altman is a top Los Angeles agent known for marquee residential deals and a long-running presence on Million Dollar Listing Los Angeles.',
        'photo_url' => 'https://www.bravotv.com/sites/bravo/files/2024/05/million-dollar-listing-season-15-josh-altman-01.jpg',
        'years_exp' => 18,
        'deals_closed' => 950,
        'portfolio_sgd' => 9000000000,
    ],
    [
        'full_name' => 'Daniel Daggers',
        'title' => 'Founder, DDRE Global',
        'specialisation' => 'London super-prime homes, off-market deals, global buyers',
        'bio' => 'Daniel Daggers is a leading London advisor in the European super-prime market, known for high-end off-market transactions and cross-border clients.',
        'photo_url' => 'https://www.kerfuffle.com/images/image0.jpeg',
        'years_exp' => 27,
        'deals_closed' => 700,
        'portfolio_sgd' => 5500000000,
    ],
];
$grid_agents = array_slice($agents, 0, 9); // show up to 9 in grid

// Stats for hero
$avg_exp = $total_agents > 0
    ? round(array_sum(array_column($agents, 'years_exp')) / $total_agents)
    : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Our Agents — Dwelro Europe</title>
    <meta name="description" content="Meet Dwelro's property experts and connect with specialists for buying, selling, renting, and investment advice.">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Dwelro">
    <meta property="og:title" content="Our Agents — Dwelro Europe">
    <meta property="og:description" content="Meet Dwelro's property experts and connect with specialists for buying, selling, renting, and investment advice.">
    <meta property="og:image" content="/img/modern-luxury-home-with-pool-contemporary-architecture.jpg">
    <meta property="og:image:alt" content="Luxury home exterior">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Our Agents — Dwelro Europe">
    <meta name="twitter:description" content="Meet Dwelro's property experts and connect with specialists for buying, selling, renting, and investment advice.">
    <meta name="twitter:image" content="/img/modern-luxury-home-with-pool-contemporary-architecture.jpg">
    <meta name="theme-color" content="#1a3a5c">
    <link rel="stylesheet" href="css/tailwind.css">
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <style>
        *,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
        :root{--dark:#1a1a1a;--mid:#555;--soft:#888;--pale:#bbb;--line:#efefef;--bg:#f8f7f4;--white:#fff}
        body{font-family:'DM Sans',sans-serif;color:var(--dark);background:#fff}

        /* ── HEADER ── */
        .site-header{position:fixed;top:0;left:0;width:100%;z-index:9999;transition:all .3s}
        .top-part{background:#fff;box-shadow:0 1px 0 #ececec}
        .nav-row{background:#fff;border-top:1px solid #ececec}
        .nav-link{color:#333;font-size:11px;font-weight:600;letter-spacing:.8px;text-transform:uppercase;display:block;padding:13px 15px;transition:color .3s;white-space:nowrap;text-decoration:none}
        .nav-link:hover{color:#1a1a1a}
        .nav-link.active{color:#1a1a1a;border-bottom:2px solid #1a1a1a}
        .dropdown-menu{position:absolute;top:100%;left:0;width:100%;background:#fff;border-top:1px solid #ececec;opacity:0;visibility:hidden;transition:opacity .2s,visibility .2s;z-index:200}
        .dropdown-menu.show{opacity:1;visibility:visible}
        @media(max-width:1023px){.desktop-nav-wrap{display:none!important}.mobile-menu-btn{display:flex!important}.desktop-search{display:none!important}.desktop-only-icons{display:none!important}}
        @media(min-width:1024px){.mobile-menu-btn{display:none!important}}

        /* ── HERO ── */
        .agents-hero{padding-top:110px;background:#fff;position:relative;overflow:hidden}
        .hero-inner{max-width:1200px;margin:0 auto;padding:64px 40px 0}
        .hero-top{display:grid;grid-template-columns:1fr auto;gap:40px;align-items:end;padding-bottom:56px;border-bottom:1px solid var(--line)}
        @media(max-width:768px){.hero-top{grid-template-columns:1fr}}
        .hero-eyebrow{font-size:10px;font-weight:700;letter-spacing:3px;text-transform:uppercase;color:#ccc;margin-bottom:14px}
        .hero-title{font-family:'DM Serif Display',serif;font-size:clamp(44px,6vw,80px);font-weight:400;color:#1a1a1a;line-height:1.02;letter-spacing:-1.5px}
        .hero-title em{font-style:italic;color:#ccc}
        .hero-right{text-align:right}
        .hero-right p{font-size:13px;color:#aaa;line-height:1.75;max-width:320px;margin-left:auto;margin-bottom:20px}

        /* ── FILTERS BAR ── */
        .filters-bar{background:#fff;border-bottom:1px solid var(--line);position:sticky;top:88px;z-index:50}
        .filters-inner{max-width:1200px;margin:0 auto;padding:0 40px;display:flex;align-items:center;gap:0;overflow-x:auto}
        .search-field{display:flex;align-items:center;gap:10px;padding:16px 0;border-right:1px solid var(--line);padding-right:24px;margin-right:24px;min-width:240px}
        .search-field input{border:none;outline:none;font-size:13px;font-family:'DM Sans',sans-serif;color:#333;background:transparent;width:100%}
        .search-field input::placeholder{color:#ccc}
        .filter-chip{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:20px;border:1px solid #eee;background:#fff;font-size:11px;font-weight:600;color:#888;cursor:pointer;white-space:nowrap;margin-right:8px;transition:all .2s}
        .filter-chip:hover{border-color:#ccc;color:#333}
        .filter-chip.active{background:#1a1a1a;border-color:#1a1a1a;color:#fff}
        .results-count{margin-left:auto;font-size:11px;font-weight:600;letter-spacing:.5px;color:#bbb;white-space:nowrap;padding-left:16px}

        /* ── FEATURED STRIP ── */
        .featured-strip{background:#1a1a1a;padding:56px 40px}
        .featured-inner{max-width:1200px;margin:0 auto}
        .featured-label{font-size:10px;font-weight:700;letter-spacing:3px;text-transform:uppercase;color:rgba(255,255,255,.3);margin-bottom:32px;display:flex;align-items:center;gap:12px}
        .featured-label::after{content:'';flex:1;height:1px;background:rgba(255,255,255,.07)}
        .featured-grid{display:grid;grid-template-columns:1.4fr 1fr 1fr;gap:16px}
        @media(max-width:900px){.featured-grid{grid-template-columns:1fr}}
        .fa-card{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.07);border-radius:3px;overflow:hidden;position:relative;transition:all .3s;cursor:pointer}
        .fa-card:hover{border-color:rgba(255,255,255,.15);background:rgba(255,255,255,.06)}
        .fa-img-wrap{position:relative;overflow:hidden}
        .fa-img-wrap img{width:100%;height:320px;object-fit:cover;object-position:top;transition:transform .7s ease;display:block}
        .fa-card:hover .fa-img-wrap img{transform:scale(1.04)}
        .fa-card.large .fa-img-wrap img{height:420px}
        .fa-img-overlay{position:absolute;inset:0;background:linear-gradient(to top,rgba(0,0,0,.7) 0%,rgba(0,0,0,.1) 50%,transparent 100%)}
        .fa-badge{position:absolute;top:16px;left:16px;background:#fff;color:#1a1a1a;font-size:9px;font-weight:700;letter-spacing:1px;text-transform:uppercase;padding:4px 10px;border-radius:10px}
        .fa-rank{position:absolute;top:16px;right:16px;font-family:'DM Serif Display',serif;font-size:48px;font-weight:400;color:rgba(255,255,255,.15);line-height:1}
        .fa-body{padding:22px 24px 24px}
        .fa-name{font-family:'DM Serif Display',serif;font-size:22px;font-weight:400;color:#fff;letter-spacing:-.2px;margin-bottom:4px}
        .fa-role{font-size:11px;color:rgba(255,255,255,.35);letter-spacing:.3px;margin-bottom:14px}
        .fa-specialty{font-size:12px;color:rgba(255,255,255,.45);line-height:1.6;margin-bottom:18px}
        .fa-stats{display:flex;gap:20px;padding:14px 0;border-top:1px solid rgba(255,255,255,.07);border-bottom:1px solid rgba(255,255,255,.07);margin-bottom:18px}
        .fa-stat-v{font-family:'DM Serif Display',serif;font-size:20px;font-weight:400;color:#fff;display:block;letter-spacing:-.2px}
        .fa-stat-l{font-size:9px;font-weight:600;letter-spacing:.8px;text-transform:uppercase;color:rgba(255,255,255,.25);display:block;margin-top:2px}
        .fa-districts{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:18px}
        .fa-district-tag{font-size:10px;font-weight:600;letter-spacing:.3px;background:rgba(255,255,255,.07);color:rgba(255,255,255,.5);padding:4px 10px;border-radius:10px;border:1px solid rgba(255,255,255,.08)}
        .fa-contact{display:block;text-align:center;padding:11px;background:#fff;color:#1a1a1a;font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;text-decoration:none;border-radius:2px;transition:background .2s;cursor:pointer;border:none;width:100%;font-family:'DM Sans',sans-serif}
        .fa-contact:hover{background:#f0f0f0}

        /* ── AGENTS GRID ── */
        .agents-grid-section{padding:72px 0;background:#fff}
        .agents-grid-inner{max-width:1200px;margin:0 auto;padding:0 40px}
        .section-header{display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:40px;flex-wrap:wrap;gap:16px}
        .sec-label{font-size:10px;font-weight:700;letter-spacing:3px;text-transform:uppercase;color:#ccc;margin-bottom:8px}
        .sec-title{font-family:'DM Serif Display',serif;font-size:clamp(24px,3vw,38px);font-weight:400;color:var(--dark);letter-spacing:-.3px;line-height:1.12}
        .sec-title em{font-style:italic;color:#ccc}
        .agents-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:20px}
        @media(max-width:900px){.agents-grid{grid-template-columns:repeat(2,1fr)}}
        @media(max-width:560px){.agents-grid{grid-template-columns:1fr}}
        .agent-card{background:#fff;border:1px solid var(--line);border-radius:3px;overflow:hidden;transition:all .3s;cursor:pointer}
        .agent-card:hover{border-color:#ccc;box-shadow:0 16px 48px rgba(0,0,0,.08);transform:translateY(-4px)}
        .ac-img-wrap{position:relative;height:240px;overflow:hidden;background:var(--bg)}
        .ac-img-wrap img{width:100%;height:100%;object-fit:cover;object-position:top;transition:transform .7s ease}
        .agent-card:hover .ac-img-wrap img{transform:scale(1.05)}
        .ac-overlay{position:absolute;inset:0;background:linear-gradient(to top,rgba(0,0,0,.3) 0%,transparent 60%);opacity:0;transition:opacity .3s}
        .agent-card:hover .ac-overlay{opacity:1}
        .ac-quick-btns{position:absolute;bottom:12px;left:12px;right:12px;display:flex;gap:8px;opacity:0;transform:translateY(8px);transition:all .3s ease}
        .agent-card:hover .ac-quick-btns{opacity:1;transform:translateY(0)}
        .acqb{flex:1;padding:9px;text-align:center;font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;border-radius:2px;border:none;cursor:pointer;transition:all .2s;text-decoration:none;display:block}
        .acqb-dark{background:#1a1a1a;color:#fff}
        .acqb-dark:hover{background:#333}
        .acqb-wa{background:#25D366;color:#fff}
        .acqb-wa:hover{background:#1ebc5b}
        .ac-rating-badge{position:absolute;top:12px;right:12px;background:rgba(255,255,255,.95);backdrop-filter:blur(8px);padding:5px 10px;border-radius:20px;font-size:11px;font-weight:700;color:#1a1a1a}
        .ac-body{padding:20px 22px 22px}
        .ac-name{font-size:15px;font-weight:700;color:#1a1a1a;margin-bottom:3px;letter-spacing:-.1px}
        .ac-role{font-size:11px;color:#bbb;letter-spacing:.3px;margin-bottom:12px}
        .ac-spec{font-size:12px;color:#888;line-height:1.6;margin-bottom:14px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
        .ac-tags{display:flex;gap:5px;flex-wrap:wrap;margin-bottom:16px}
        .ac-tag{font-size:10px;font-weight:600;letter-spacing:.3px;background:#f5f5f5;color:#666;padding:3px 9px;border-radius:10px}
        .ac-stats{display:flex;gap:0;border-top:1px solid var(--line);padding-top:14px}
        .ac-stat{flex:1;text-align:center}
        .ac-stat+.ac-stat{border-left:1px solid var(--line)}
        .ac-stat-v{font-family:'DM Serif Display',serif;font-size:17px;color:#1a1a1a;display:block;letter-spacing:-.2px}
        .ac-stat-l{font-size:9px;font-weight:600;letter-spacing:.5px;text-transform:uppercase;color:#ccc;display:block;margin-top:2px}

        /* ── LEADERBOARD ── */
        .leaderboard-section{padding:72px 0;background:var(--bg);border-top:1px solid var(--line)}
        .leaderboard-inner{max-width:1200px;margin:0 auto;padding:0 40px}
        .leaderboard-table{width:100%;border-collapse:collapse;margin-top:40px}
        .leaderboard-table th{font-size:10px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#bbb;text-align:left;padding:0 16px 16px;border-bottom:1px solid var(--line)}
        .leaderboard-table th:first-child{padding-left:0}
        .leaderboard-table th:last-child{text-align:center}
        .lb-row{border-bottom:1px solid var(--line);transition:background .2s;cursor:pointer}
        .lb-row:hover{background:#fff}
        .lb-row td{padding:18px 16px;font-size:13px;color:#555}
        .lb-row td:first-child{padding-left:0}
        .lb-rank{font-family:'DM Serif Display',serif;font-size:20px;color:#e8e8e8;font-weight:400;width:40px}
        .lb-rank.top3{color:#1a1a1a}
        .lb-agent-cell{display:flex;align-items:center;gap:14px}
        .lb-avatar{width:44px;height:44px;border-radius:50%;overflow:hidden;flex-shrink:0;border:2px solid var(--line)}
        .lb-avatar img{width:100%;height:100%;object-fit:cover;object-position:top}
        .lb-name{font-size:14px;font-weight:700;color:#1a1a1a;display:block}
        .lb-role-sm{font-size:11px;color:#bbb;display:block;margin-top:1px}
        .lb-value{font-weight:700;color:#1a1a1a}
        .lb-badge{display:inline-block;padding:3px 10px;font-size:10px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;border-radius:10px}
        .lb-badge-gold{background:#fef3c7;color:#92400e}
        .lb-badge-silver{background:#f1f5f9;color:#475569}
        .lb-badge-bronze{background:#fef2f2;color:#991b1b}
        .lb-badge-std{background:#f5f5f5;color:#666}
        .lb-contact-btn{display:inline-block;padding:7px 16px;background:#1a1a1a;color:#fff;font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;border:none;border-radius:2px;cursor:pointer;text-decoration:none;transition:background .2s;font-family:'DM Sans',sans-serif}
        .lb-contact-btn:hover{background:#333}

        /* ── JOIN CTA ── */
        .join-section{padding:96px 40px;background:#1a1a1a;position:relative;overflow:hidden}
        .join-section::before{content:'';position:absolute;inset:0;background-image:linear-gradient(rgba(255,255,255,.03) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.03) 1px,transparent 1px);background-size:60px 60px}
        .join-inner{max-width:1200px;margin:0 auto;display:grid;grid-template-columns:1fr 1fr;gap:80px;align-items:center;position:relative;z-index:1}
        @media(max-width:900px){.join-inner{grid-template-columns:1fr;gap:40px}}
        .join-title{font-family:'DM Serif Display',serif;font-size:clamp(30px,4vw,52px);font-weight:400;color:#fff;line-height:1.08;letter-spacing:-.8px;margin-bottom:16px}
        .join-title em{font-style:italic;color:rgba(255,255,255,.3)}
        .join-sub{font-size:14px;color:rgba(255,255,255,.35);line-height:1.75;margin-bottom:32px}
        .join-perks{display:flex;flex-direction:column;gap:16px}
        .join-perk{display:flex;gap:14px;align-items:flex-start}
        .jp-num{width:32px;height:32px;border-radius:50%;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:rgba(255,255,255,.4);flex-shrink:0}
        .jp-body h4{font-size:13px;font-weight:700;color:#fff;margin-bottom:3px}
        .jp-body p{font-size:12px;color:rgba(255,255,255,.35);line-height:1.6}
        .join-form{background:#fff;border-radius:3px;padding:36px}
        .join-form h3{font-family:'DM Serif Display',serif;font-size:22px;font-weight:400;color:#1a1a1a;margin-bottom:6px}
        .join-form p{font-size:12px;color:#aaa;margin-bottom:24px;line-height:1.6}
        .jf-group{margin-bottom:14px}
        .jf-group label{display:block;font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:#bbb;margin-bottom:5px}
        .jf-group input,.jf-group select{width:100%;padding:11px 14px;border:1px solid #eee;font-size:13px;font-family:'DM Sans',sans-serif;color:#1a1a1a;outline:none;border-radius:2px;background:#fafafa;transition:border-color .2s}
        .jf-group input:focus,.jf-group select:focus{border-color:#333;background:#fff}
        .jf-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
        .jf-submit{width:100%;padding:13px;background:#1a1a1a;color:#fff;border:none;font-size:10px;font-weight:700;letter-spacing:1.2px;text-transform:uppercase;cursor:pointer;border-radius:2px;margin-top:6px;transition:background .25s;font-family:'DM Sans',sans-serif}
        .jf-submit:hover{background:#333}

        /* ── TESTIMONIALS ── */
        .testimonials-section{padding:72px 0;background:#fff;border-top:1px solid var(--line)}
        .testimonials-inner{max-width:1200px;margin:0 auto;padding:0 40px}
        .testimonials-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-top:40px}
        @media(max-width:900px){.testimonials-grid{grid-template-columns:1fr}}
        .testi-card{background:var(--bg);border:1px solid var(--line);border-radius:3px;padding:28px}
        .testi-stars{color:#1a1a1a;font-size:13px;letter-spacing:2px;margin-bottom:16px}
        .testi-quote{font-size:13px;color:#555;line-height:1.75;margin-bottom:20px;font-weight:300}
        .testi-quote::before{content:'"';font-family:'DM Serif Display',serif;font-size:32px;color:#e8e8e8;display:block;line-height:.8;margin-bottom:8px}
        .testi-client{display:flex;align-items:center;gap:12px;border-top:1px solid var(--line);padding-top:16px}
        .testi-avatar{width:40px;height:40px;border-radius:50%;overflow:hidden}
        .testi-avatar img{width:100%;height:100%;object-fit:cover}
        .testi-name{font-size:13px;font-weight:700;color:#1a1a1a;display:block}
        .testi-agent-ref{font-size:11px;color:#bbb;display:block;margin-top:1px}

        /* ── MODAL ── */
        .modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:10002;opacity:0;visibility:hidden;transition:opacity .3s,visibility .3s;backdrop-filter:blur(4px)}
        .modal-backdrop.open{opacity:1;visibility:visible}
        .modal-box{position:absolute;top:50%;left:50%;transform:translate(-50%,-48%) scale(.97);background:#fff;width:90%;max-width:520px;padding:40px;border-radius:4px;transition:transform .3s,opacity .3s;opacity:0;max-height:90vh;overflow-y:auto}
        .modal-backdrop.open .modal-box{transform:translate(-50%,-50%) scale(1);opacity:1}
        .modal-close{position:absolute;top:16px;right:16px;background:none;border:none;cursor:pointer;padding:6px;opacity:.35;transition:opacity .2s}
        .modal-close:hover{opacity:1}
        .modal-title{font-family:'DM Serif Display',serif;font-size:26px;font-weight:400;color:#1a1a1a;margin-bottom:6px}
        .modal-sub{font-size:13px;color:#aaa;margin-bottom:28px;line-height:1.55}
        .mf-group{margin-bottom:14px}
        .mf-group label{display:block;font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:#bbb;margin-bottom:5px}
        .mf-group input,.mf-group select,.mf-group textarea{width:100%;padding:11px 14px;border:1px solid #eee;font-size:13px;font-family:'DM Sans',sans-serif;color:#1a1a1a;outline:none;border-radius:2px;background:#fafafa;transition:border-color .2s}
        .mf-group input:focus,.mf-group select:focus,.mf-group textarea:focus{border-color:#333;background:#fff}
        .mf-group textarea{resize:vertical;min-height:80px;line-height:1.55}
        .mf-row{display:grid;grid-template-columns:1fr 1fr;gap:14px}
        .mf-submit{width:100%;padding:13px;background:#1a1a1a;color:#fff;border:none;font-size:10px;font-weight:700;letter-spacing:1.2px;text-transform:uppercase;cursor:pointer;border-radius:2px;margin-top:8px;transition:background .25s;font-family:'DM Sans',sans-serif}
        .mf-submit:hover{background:#333}

        /* PILL */
        .pill{display:inline-flex;align-items:center;gap:6px;background:#f5f5f5;border:1px solid #ececec;border-radius:20px;padding:5px 12px 5px 8px;font-size:11px;font-weight:600;color:#555;margin-bottom:20px}
        .pill-dot{width:6px;height:6px;border-radius:50%;background:#1a1a1a;flex-shrink:0}

        /* SHARED BUTTONS */
        .btn-dark{display:inline-block;background:#1a1a1a;color:#fff;font-size:10px;font-weight:700;letter-spacing:1px;text-transform:uppercase;padding:13px 28px;border-radius:2px;text-decoration:none;border:none;cursor:pointer;transition:background .2s}
        .btn-dark:hover{background:#333}

        /* MOBILE MENU */
        .mobile-menu{position:fixed;top:0;left:0;width:100%;height:100%;background:#fff;z-index:10001;transform:translateX(-100%);transition:transform .35s cubic-bezier(.4,0,.2,1);overflow-y:auto}
        .mobile-menu.open{transform:translateX(0)}

        /* WA FLOAT */
        .wa-btn-float{position:fixed;bottom:32px;right:32px;z-index:9000;width:56px;height:56px;background:#25D366;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 8px 32px rgba(37,211,102,.35);cursor:pointer;text-decoration:none;transition:transform .25s}
        .wa-btn-float:hover{transform:scale(1.08)}

        /* BACK TO TOP */
        .back-to-top{position:fixed;bottom:32px;left:32px;z-index:8998;width:44px;height:44px;background:#1a1a1a;color:#fff;border:none;border-radius:2px;display:flex;align-items:center;justify-content:center;cursor:pointer;opacity:0;transform:translateY(12px);transition:opacity .3s,transform .3s;pointer-events:none}
        .back-to-top.show{opacity:1;transform:translateY(0);pointer-events:auto}
        .back-to-top:hover{background:#333}

        /* FOOTER */
        .site-footer{background:#111;color:#fff}
        .footer-bottom-bar{display:flex;align-items:center;justify-content:space-between;padding:24px 40px;border-top:1px solid rgba(255,255,255,.07);flex-wrap:wrap;gap:12px}
        .footer-bottom-links a{font-size:11px;color:rgba(255,255,255,.3);text-decoration:none;margin-left:20px}
        .footer-bottom-links a:hover{color:#fff}

        /* ANIMATIONS */
        .fade-up{opacity:0;transform:translateY(20px);transition:opacity .55s ease,transform .55s ease}
        .fade-up.visible{opacity:1;transform:translateY(0)}
        section{scroll-margin-top:120px}

        /* SORT SELECT */
        .sort-select{padding:8px 14px;border:1px solid #eee;font-size:12px;font-family:'DM Sans',sans-serif;color:#555;outline:none;border-radius:20px;background:#fff;cursor:pointer}

        /* TOAST */
        .toast-bar{position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:#1a1a1a;color:#fff;padding:12px 24px;border-radius:30px;font-size:12px;font-weight:600;z-index:99999;animation:slideUp .3s ease;box-shadow:0 4px 20px rgba(0,0,0,.2)}
        .toast-bar.err{background:#ef4444}
        @keyframes slideUp{from{opacity:0;transform:translateX(-50%) translateY(10px)}to{opacity:1;transform:translateX(-50%) translateY(0)}}
    </style>
</head>
<body>

<?php
// ── Toast after POST redirect ─────────────────────────────────────────────────
$toast     = $_GET['toast']     ?? '';
$toast_err = isset($_GET['toast_err']);
if ($toast): ?>
<div class="toast-bar <?= $toast_err ? 'err' : '' ?>" id="toastBar">
    <?= $toast_err ? '✕' : '✓' ?> <?= h($toast) ?>
</div>
<script>setTimeout(()=>document.getElementById('toastBar')?.remove(),3500);</script>
<?php endif; ?>

    <!-- ANNOUNCEMENT BAR -->
    <div style="background:#f8f7f4;border-bottom:1px solid #ececec;position:relative;z-index:10001;">
        <div class="max-w-screen-xl mx-auto px-5 py-2">
            <div class="text-xs text-center w-full" style="font-weight:500;letter-spacing:.3px;color:#888;">
                Flagship Office @ 1 Raffles Place, #22-01 · Open Daily 9am–6pm ·
                <a href="#joinSection" style="text-decoration:underline;color:#555;">Join our agent team</a>
            </div>
        </div>
    </div>

    <!-- HEADER -->
    <header class="site-header" id="siteHeader">
        <div class="top-part w-full">
            <div class="max-w-screen-xl mx-auto px-5 py-3 flex items-center justify-between relative">
                <div class="flex items-center gap-3 flex-1">
                    <button class="mobile-menu-btn items-center justify-center" onclick="toggleMobileMenu()" aria-label="Menu">
                        <svg class="hdr-icon" width="18" height="14" viewBox="0 0 18 14" fill="none"><path d="M10.5 13H1M17 7H1M17 1H1" stroke-width="1.2" stroke-linecap="round"/></svg>
                    </button>
                    <div class="desktop-search flex items-center gap-2 flex-1 max-w-xs">
                        <svg class="hdr-icon flex-shrink-0" width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M15 15L11.38 11.38M1 7.03C1 3.7 3.7 1 7.03 1C10.37 1 13.07 3.7 13.07 7.03C13.07 10.37 10.37 13.07 7.03 13.07C3.7 13.07 1 10.37 1 7.03Z" stroke-width="1.2" stroke-linecap="round"/></svg>
                        <input type="text" placeholder="Search properties…" class="search-input text-xs w-full" style="font-size:13px;font-family:'DM Sans',sans-serif;" />
                    </div>
                </div>
                <div class="absolute left-1/2 -translate-x-1/2" style="top:50%;transform:translate(-50%,-50%);">
                    <a href="/" class="block relative">
                        <div style="font-family:'DM Serif Display',serif;font-size:28px;font-weight:400;color:#171717;letter-spacing:-.5px;white-space:nowrap;line-height:1;">Dwelro</div>
                    </a>
                </div>
                <div class="flex items-center gap-4 flex-1 justify-end">
                    <a href="/contact" class="desktop-only-icons" style="font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:#1a1a1a;text-decoration:none;transition:color .3s;background:#f0f0f0;padding:8px 16px;border-radius:2px;">Book a Call</a>
                </div>
            </div>
        </div>
        <div class="nav-row w-full desktop-nav-wrap" id="navRow">
            <div class="max-w-screen-xl mx-auto px-5 flex justify-center">
                <nav class="flex items-center">
                    <a href="buy.html" class="nav-link">Buy</a>
                    <a href="rent.html" class="nav-link">Rent</a>
                    <a href="sell.html" class="nav-link">Sell</a>
                    <a href="Investment.html" class="nav-link">Investment</a>
                    <a href="listings.php" class="nav-link">Listings</a>
                    <a href="map-search.html" class="nav-link">Map Search</a>
                    <a href="agents.php" class="nav-link active">Our Agents</a>
                    <a href="about.html" class="nav-link">About</a>
                    <a href="blog.html" class="nav-link">Blog</a>
                    <a href="contact.html" class="nav-link">Contact Us</a>
                </nav>
            </div>
        </div>
    </header>

    <!-- ══ HERO ══ -->
    <div class="agents-hero">
        <div class="hero-inner">
            <div class="hero-top fade-up">
                <div>
                    <div class="pill"><span class="pill-dot"></span>CEA-Certified Professionals</div>
                    <p class="hero-eyebrow">Our People</p>
                    <h1 class="hero-title">People Who<br>Know <em>Property</em></h1>
                </div>
                <div class="hero-right">
                    <p><?= $total_agents ?> specialist agent<?= $total_agents !== 1 ? 's' : '' ?> across residential, commercial, and investment real estate. Every one CEA-certified, every one accountable.</p>
                    <div style="display:flex;gap:28px;justify-content:flex-end;margin-top:24px;">
                        <div style="text-align:center;">
                            <span style="font-family:'DM Serif Display',serif;font-size:32px;color:#1a1a1a;letter-spacing:-.3px;display:block;"><?= $total_agents ?></span>
                            <span style="font-size:10px;color:#bbb;font-weight:600;letter-spacing:1px;text-transform:uppercase;display:block;margin-top:3px;">Active Agents</span>
                        </div>
                        <div style="text-align:center;">
                            <span style="font-family:'DM Serif Display',serif;font-size:32px;color:#1a1a1a;letter-spacing:-.3px;display:block;">4.9★</span>
                            <span style="font-size:10px;color:#bbb;font-weight:600;letter-spacing:1px;text-transform:uppercase;display:block;margin-top:3px;">Avg Rating</span>
                        </div>
                        <div style="text-align:center;">
                            <span style="font-family:'DM Serif Display',serif;font-size:32px;color:#1a1a1a;letter-spacing:-.3px;display:block;"><?= $avg_exp ?>yr</span>
                            <span style="font-size:10px;color:#bbb;font-weight:600;letter-spacing:1px;text-transform:uppercase;display:block;margin-top:3px;">Avg Experience</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ══ FILTERS BAR ══ -->
    <div class="filters-bar">
        <div class="filters-inner">
            <div class="search-field">
                <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M15 15L11.38 11.38M1 7.03C1 3.7 3.7 1 7.03 1C10.37 1 13.07 3.7 13.07 7.03C13.07 10.37 10.37 13.07 7.03 13.07C3.7 13.07 1 10.37 1 7.03Z" stroke="#bbb" stroke-width="1.2" stroke-linecap="round"/></svg>
                <input type="text" placeholder="Name or specialisation…" id="filterSearch" oninput="filterAgents()" />
            </div>
            <span class="filter-chip active" onclick="setFilter(this,'all')">All Agents</span>
            <span class="filter-chip" onclick="setFilter(this,'hdb')">HDB</span>
            <span class="filter-chip" onclick="setFilter(this,'condo')">Condo</span>
            <span class="filter-chip" onclick="setFilter(this,'landed')">Landed</span>
            <span class="filter-chip" onclick="setFilter(this,'commercial')">Commercial</span>
            <span class="filter-chip" onclick="setFilter(this,'investment')">Investment</span>
            <span class="filter-chip" onclick="setFilter(this,'overseas')">Overseas</span>
            <div style="margin-left:auto;padding-left:16px;display:flex;align-items:center;gap:10px;flex-shrink:0;">
                <select class="sort-select" onchange="sortAgents(this.value)">
                    <option value="portfolio">Sort: Top Volume</option>
                    <option value="deals">Sort: Most Deals</option>
                    <option value="experience">Sort: Experience</option>
                </select>
                <span class="results-count" id="resultsCount"><?= $total_agents ?> agent<?= $total_agents !== 1 ? 's' : '' ?></span>
            </div>
        </div>
    </div>

    <!-- ══ TOP PERFORMERS ══ -->
    <?php if (!empty($featured)): ?>
    <div class="featured-strip fade-up">
        <div class="featured-inner">
            <div class="featured-label">Global Market Leaders <?= date('Y') ?></div>
            <div class="featured-grid">
                <?php foreach ($featured as $i => $a):
                    $rank_label = str_pad($i + 1, 2, '0', STR_PAD_LEFT);
                    $is_large   = $i === 0;
                    $photo      = $a['photo_url'] ?: 'https://images.unsplash.com/photo-1560250097-0b93528c311a?w=700&auto=format&fit=crop';
                    // Build specialisation tags from specialisation field
                    $spec_tags = array_filter(array_map('trim', explode(',', $a['specialisation'] ?? '')));
                ?>
                <div class="fa-card <?= $is_large ? 'large' : '' ?>" onclick="openContact('<?= h(addslashes($a['full_name'])) ?>')">
                    <div class="fa-img-wrap">
                        <img src="<?= h($photo) ?>"
                             onerror="this.src='https://images.unsplash.com/photo-1560250097-0b93528c311a?w=700&auto=format&fit=crop'"
                             alt="<?= h($a['full_name']) ?>" />
                        <div class="fa-img-overlay"></div>
                        <?php if ($i === 0): ?>
                        <span class="fa-badge">#1 Agent <?= date('Y') ?></span>
                        <?php endif; ?>
                        <span class="fa-rank"><?= $rank_label ?></span>
                    </div>
                    <div class="fa-body">
                        <div class="fa-name"><?= h($a['full_name']) ?></div>
                        <div class="fa-role"><?= h($a['title']) ?></div>
                        <div class="fa-specialty"><?= h($a['bio'] ? substr($a['bio'], 0, 160) . (strlen($a['bio']) > 160 ? '…' : '') : $a['specialisation']) ?></div>
                        <div class="fa-stats">
                            <div><span class="fa-stat-v"><?= h(vol_display((int)$a['portfolio_sgd'])) ?></span><span class="fa-stat-l">Sales Value</span></div>
                            <div><span class="fa-stat-v"><?= (int)$a['deals_closed'] ?>+</span><span class="fa-stat-l">Deals Closed</span></div>
                            <div><span class="fa-stat-v"><?= (int)$a['years_exp'] ?>yr</span><span class="fa-stat-l">Experience</span></div>
                        </div>
                        <?php if (!empty($spec_tags)): ?>
                        <div class="fa-districts">
                            <?php foreach (array_slice($spec_tags, 0, 4) as $tag): ?>
                            <span class="fa-district-tag"><?= h(trim($tag)) ?></span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <button class="fa-contact" onclick="event.stopPropagation();openContact('<?= h(addslashes($a['full_name'])) ?>')">
                            Contact <?= h($a['full_name']) ?>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ══ ALL AGENTS GRID ══ -->
    <section class="agents-grid-section" id="allAgents">
        <div class="agents-grid-inner">
            <div class="section-header fade-up">
                <div>
                    <p class="sec-label">Full Team</p>
                    <h2 class="sec-title">All <em>Agents</em></h2>
                </div>
            </div>

            <div class="agents-grid" id="agentsGrid">
                <?php foreach ($grid_agents as $a):
                    $photo   = $a['photo_url'] ?: 'https://images.unsplash.com/photo-1560250097-0b93528c311a?w=500&auto=format&fit=crop';
                    $wa_num  = preg_replace('/[^0-9]/', '', $a['whatsapp'] ?? $a['phone'] ?? '');
                    $tags    = array_filter(array_map('trim', explode(',', $a['specialisation'] ?? '')));
                    $tag_str = strtolower(implode(' ', $tags));
                ?>
                <div class="agent-card fade-up"
                     data-tags="<?= h($tag_str) ?>"
                     data-name="<?= h(strtolower($a['full_name'])) ?>"
                     data-portfolio="<?= (int)$a['portfolio_sgd'] ?>"
                     data-deals="<?= (int)$a['deals_closed'] ?>"
                     data-exp="<?= (int)$a['years_exp'] ?>"
                     onclick="openContact('<?= h(addslashes($a['full_name'])) ?>')">
                    <div class="ac-img-wrap">
                        <img src="<?= h($photo) ?>"
                             onerror="this.src='https://images.unsplash.com/photo-1560250097-0b93528c311a?w=500&auto=format&fit=crop'"
                             alt="<?= h($a['full_name']) ?>" />
                        <div class="ac-overlay"></div>
                        <div class="ac-quick-btns">
                            <a href="#" class="acqb acqb-dark" onclick="event.stopPropagation();openContact('<?= h(addslashes($a['full_name'])) ?>')">Contact</a>
                            <?php if ($wa_num): ?>
                            <a href="https://wa.me/<?= h($wa_num) ?>" class="acqb acqb-wa" target="_blank" onclick="event.stopPropagation()">WhatsApp</a>
                            <?php endif; ?>
                        </div>
                        <span class="ac-rating-badge">4.9★</span>
                    </div>
                    <div class="ac-body">
                        <div class="ac-name"><?= h($a['full_name']) ?></div>
                        <div class="ac-role"><?= h($a['title']) ?> · <?= h($a['cea_number']) ?></div>
                        <div class="ac-spec"><?= h($a['bio'] ? substr($a['bio'], 0, 120) . (strlen($a['bio']) > 120 ? '…' : '') : $a['specialisation']) ?></div>
                        <?php if (!empty($tags)): ?>
                        <div class="ac-tags">
                            <?php foreach (array_slice($tags, 0, 3) as $tag): ?>
                            <span class="ac-tag"><?= h(trim($tag)) ?></span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <div class="ac-stats">
                            <div class="ac-stat">
                                <span class="ac-stat-v"><?= h(vol_display((int)$a['portfolio_sgd'])) ?></span>
                                <span class="ac-stat-l">Sales</span>
                            </div>
                            <div class="ac-stat">
                                <span class="ac-stat-v"><?= (int)$a['deals_closed'] ?>+</span>
                                <span class="ac-stat-l">Deals</span>
                            </div>
                            <div class="ac-stat">
                                <span class="ac-stat-v"><?= (int)$a['years_exp'] ?>yr</span>
                                <span class="ac-stat-l">Exp.</span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- No results message -->
            <div id="noResults" style="display:none;text-align:center;padding:80px 0;">
                <p style="font-size:32px;margin-bottom:12px;">🔍</p>
                <p style="font-size:14px;font-weight:700;color:#1a1a1a;margin-bottom:6px;">No agents found</p>
                <p style="font-size:13px;color:#aaa;">Try a different search term or filter.</p>
            </div>
        </div>
    </section>

    <!-- ══ LEADERBOARD ══ -->
    <section class="leaderboard-section">
        <div class="leaderboard-inner">
            <div class="fade-up" style="display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:20px;margin-bottom:0;">
                <div>
                    <p class="sec-label">Annual Rankings</p>
                    <h2 class="sec-title"><?= date('Y') ?> Sales <em>Leaderboard</em></h2>
                    <p style="font-size:14px;color:#aaa;line-height:1.65;max-width:480px;margin-top:8px;">Full-year rankings by total transacted value. Updated based on verified CEA-registered transactions.</p>
                </div>
            </div>

            <table class="leaderboard-table fade-up">
                <thead>
                    <tr>
                        <th style="width:48px;">#</th>
                        <th>Agent</th>
                        <th>Specialisation</th>
                        <th>Deals</th>
                        <th>Total Value</th>
                        <th>Exp.</th>
                        <th style="text-align:center;">Tier</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($agents as $i => $a):
                    $rank   = $i + 1;
                    $photo  = $a['photo_url'] ?: 'https://images.unsplash.com/photo-1560250097-0b93528c311a?w=200&auto=format&fit=crop';
                    if ($rank === 1)      $tier = ['Platinum', 'lb-badge-gold'];
                    elseif ($rank === 2)  $tier = ['Platinum', 'lb-badge-gold'];
                    elseif ($rank <= 4)   $tier = ['Gold',     'lb-badge-silver'];
                    elseif ($rank <= 6)   $tier = ['Silver',   'lb-badge-bronze'];
                    else                  $tier = ['Bronze',   'lb-badge-std'];
                ?>
                <tr class="lb-row" onclick="openContact('<?= h(addslashes($a['full_name'])) ?>')">
                    <td class="lb-rank <?= $rank <= 3 ? 'top3' : '' ?>"><?= str_pad($rank, 2, '0', STR_PAD_LEFT) ?></td>
                    <td>
                        <div class="lb-agent-cell">
                            <div class="lb-avatar">
                                <img src="<?= h($photo) ?>"
                                     onerror="this.src='https://images.unsplash.com/photo-1560250097-0b93528c311a?w=200&auto=format&fit=crop'"
                                     alt="<?= h($a['full_name']) ?>" />
                            </div>
                            <div>
                                <span class="lb-name"><?= h($a['full_name']) ?></span>
                                <span class="lb-role-sm"><?= h($a['title']) ?></span>
                            </div>
                        </div>
                    </td>
                    <td><?= h($a['specialisation'] ?? '—') ?></td>
                    <td><?= (int)$a['deals_closed'] ?></td>
                    <td class="lb-value"><?= h(vol_display((int)$a['portfolio_sgd'])) ?></td>
                    <td><?= (int)$a['years_exp'] ?>yr</td>
                    <td style="text-align:center;"><span class="lb-badge <?= $tier[1] ?>"><?= $tier[0] ?></span></td>
                    <td>
                        <button class="lb-contact-btn" onclick="event.stopPropagation();openContact('<?= h(addslashes($a['full_name'])) ?>')">
                            Contact
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- ══ JOIN OUR TEAM ══ -->
    <section id="joinSection" class="join-section">
        <div class="join-inner">
            <div class="fade-up">
                <h2 class="join-title">Join the Dwelro<br><em>Agent Network</em></h2>
                <p class="join-sub">We're looking for driven, ethical professionals who want to build a career in Europe's most dynamic property markets. RICS/MRICS registration preferred.</p>
                <div class="join-perks">
                    <div class="join-perk"><div class="jp-num">1</div><div class="jp-body"><h4>Industry-Leading Commission Splits</h4><p>Competitive splits from 70/30 up to 90/10 for top performers, reviewed quarterly.</p></div></div>
                    <div class="join-perk"><div class="jp-num">2</div><div class="jp-body"><h4>Leads, Marketing & CRM Tools</h4><p>Access to Dwelro's proprietary CRM, buyer-matching platform, and marketing budget for listings.</p></div></div>
                    <div class="join-perk"><div class="jp-num">3</div><div class="jp-body"><h4>Mentorship & Training</h4><p>Structured onboarding, weekly market briefings, and 1-on-1 mentorship from senior directors.</p></div></div>
                    <div class="join-perk"><div class="jp-num">4</div><div class="jp-body"><h4>Brand & Prestige</h4><p>Leverage Dwelro's reputation to win more listings and attract high-net-worth clients from day one.</p></div></div>
                </div>
            </div>
            <div class="fade-up">
                <div class="join-form">
                    <h3>Apply to Join</h3>
                    <p>Fill in your details and our HR team will contact you within 2 business days.</p>
                    <div class="jf-row">
                        <div class="jf-group"><label>First Name</label><input type="text" placeholder="Jane" /></div>
                        <div class="jf-group"><label>Last Name</label><input type="text" placeholder="Doe" /></div>
                    </div>
                    <div class="jf-group"><label>Email</label><input type="email" placeholder="jane@example.com" /></div>
                    <div class="jf-group"><label>Mobile / WhatsApp</label><input type="tel" placeholder="+65 9123 4567" /></div>
                    <div class="jf-group"><label>CEA Registration No.</label><input type="text" placeholder="e.g. R012345A" /></div>
                    <div class="jf-group"><label>Years of Experience</label>
                        <select><option>Less than 1 year</option><option>1–3 years</option><option>3–5 years</option><option>5–10 years</option><option>10+ years</option></select>
                    </div>
                    <div class="jf-group"><label>Primary Specialisation</label>
                        <select><option>HDB / Resale</option><option>Condo / CCR</option><option>Landed / Luxury</option><option>Commercial</option><option>Overseas / Investment</option><option>New Launches</option></select>
                    </div>
                    <button class="jf-submit" onclick="submitJoinForm(this)">Submit Application →</button>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA FOOTER STRIP -->
    <div style="background:#f8f7f4;border-top:1px solid #efefef;padding:56px 40px;text-align:center;">
        <div style="max-width:600px;margin:0 auto;">
            <p style="font-size:10px;font-weight:700;letter-spacing:3px;text-transform:uppercase;color:#ccc;margin-bottom:12px;">Get Started</p>
            <h3 style="font-family:'DM Serif Display',serif;font-size:clamp(24px,4vw,40px);font-weight:400;color:#1a1a1a;margin-bottom:12px;letter-spacing:-.4px;">Not sure which agent is <em style="color:#ccc;">right for you?</em></h3>
            <p style="font-size:14px;color:#aaa;margin-bottom:32px;line-height:1.7;">Tell us what you need and we'll match you with the most suitable specialist — free of charge.</p>
            <button onclick="openMatchModal()" class="btn-dark" style="padding:14px 40px;font-size:11px;">Find My Agent →</button>
        </div>
    </div>

    <!-- FOOTER -->
    <footer class="site-footer">
        <div style="padding:40px;border-bottom:1px solid rgba(255,255,255,.07);">
            <div style="max-width:1200px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:24px;">
                <div>
                    <div style="font-family:'DM Serif Display',serif;font-size:24px;font-weight:400;color:#fff;letter-spacing:-.5px;">Dwelro</div>
                    <p style="font-size:12px;color:rgba(255,255,255,.35);margin-top:6px;">Europe's Premier Property Partner since 2010.</p>
                </div>
                <div style="display:flex;gap:24px;flex-wrap:wrap;">
                    <a href="buy.html" style="font-size:12px;color:rgba(255,255,255,.4);text-decoration:none;">Buy</a>
                    <a href="rent.html" style="font-size:12px;color:rgba(255,255,255,.4);text-decoration:none;">Rent</a>
                    <a href="sell.html" style="font-size:12px;color:rgba(255,255,255,.4);text-decoration:none;">Sell</a>
                    <a href="agents.php" style="font-size:12px;color:rgba(255,255,255,.7);text-decoration:none;">Our Agents</a>
                    <a href="contact.html" style="font-size:12px;color:rgba(255,255,255,.4);text-decoration:none;">Contact</a>
                </div>
            </div>
        </div>
        <div class="footer-bottom-bar">
            <span style="font-size:12px;color:rgba(255,255,255,.2);">© <?= date('Y') ?> Dwelro Pte Ltd.</span>
            <div class="footer-bottom-links"><a href="#">Terms</a><a href="#">Privacy Policy</a></div>
        </div>
    </footer>

    <!-- MOBILE MENU -->
    <div class="mobile-menu" id="mobileMenu">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid #f0f0f0;">
            <div style="font-family:'DM Serif Display',serif;font-size:22px;font-weight:400;color:#1a1a1a;">Dwelro</div>
            <button onclick="toggleMobileMenu()" style="padding:8px;" aria-label="Close">
                <svg width="17" height="17" viewBox="0 0 17 17" fill="none"><path d="M16 1L9 8.5M9 8.5L16 16M9 8.5L1 1M9 8.5L1 16" stroke="#333" stroke-width="1.2" stroke-linecap="round"/></svg>
            </button>
        </div>
        <ul>
            <li style="margin:0 20px;padding:16px 0;border-bottom:1px solid #f5f5f5;"><a href="buy.html" style="font-size:13px;font-weight:600;text-transform:uppercase;color:#333;letter-spacing:.5px;text-decoration:none;">Buy</a></li>
            <li style="margin:0 20px;padding:16px 0;border-bottom:1px solid #f5f5f5;"><a href="rent.html" style="font-size:13px;font-weight:600;text-transform:uppercase;color:#333;letter-spacing:.5px;text-decoration:none;">Rent</a></li>
            <li style="margin:0 20px;padding:16px 0;border-bottom:1px solid #f5f5f5;"><a href="sell.html" style="font-size:13px;font-weight:600;text-transform:uppercase;color:#333;letter-spacing:.5px;text-decoration:none;">Sell</a></li>
            <li style="margin:0 20px;padding:16px 0;border-bottom:1px solid #f5f5f5;"><a href="agents.php" style="font-size:13px;font-weight:600;text-transform:uppercase;color:#1a1a1a;letter-spacing:.5px;text-decoration:none;">Our Agents</a></li>
            <li style="margin:0 20px;padding:16px 0;"><a href="#joinSection" style="font-size:13px;font-weight:600;text-transform:uppercase;color:#333;letter-spacing:.5px;text-decoration:none;">Join Our Team</a></li>
        </ul>
        <div style="padding:20px;display:flex;gap:10px;">
            <button onclick="toggleMobileMenu();openMatchModal();" style="flex:1;background:#1a1a1a;color:#fff;text-align:center;padding:12px;font-size:11px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;border:none;border-radius:2px;cursor:pointer;font-family:'DM Sans',sans-serif;">Find Agent</button>
        </div>
    </div>

    <!-- WA FLOAT -->
    <a href="https://wa.me/6591234567" class="wa-btn-float" target="_blank" aria-label="WhatsApp">
        <svg viewBox="0 0 24 24" fill="#fff" width="28" height="28"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
    </a>

    <button class="back-to-top" id="backToTop" onclick="window.scrollTo({top:0,behavior:'smooth'})" aria-label="Back to top">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M8 13V3M3 7l5-5 5 5" stroke="#fff" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </button>

    <!-- CONTACT MODAL -->
    <div class="modal-backdrop" id="contactModal" onclick="closeModalOutside(event,'contactModal')">
        <div class="modal-box">
            <button class="modal-close" onclick="closeModal('contactModal')">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M13 3L3 13M3 3l10 10" stroke="#333" stroke-width="1.3" stroke-linecap="round"/></svg>
            </button>
            <div id="contactModalContent">
                <h3 class="modal-title" id="contactModalTitle">Contact Agent</h3>
                <p class="modal-sub">Leave your details and the agent will reach out within 2 hours.</p>
                <div class="mf-row">
                    <div class="mf-group"><label>First Name</label><input type="text" placeholder="Jane" /></div>
                    <div class="mf-group"><label>Last Name</label><input type="text" placeholder="Doe" /></div>
                </div>
                <div class="mf-group"><label>WhatsApp</label><input type="tel" placeholder="+65 9123 4567" /></div>
                <div class="mf-group"><label>Email</label><input type="email" placeholder="jane@example.com" /></div>
                <div class="mf-group"><label>What do you need help with?</label>
                    <select><option>Buying a property</option><option>Selling a property</option><option>Renting</option><option>Investment advice</option><option>General enquiry</option></select>
                </div>
                <div class="mf-group"><label>Message</label>
                    <textarea placeholder="Tell us a bit more about what you're looking for…"></textarea>
                </div>
                <button class="mf-submit" onclick="submitContactModal()">Send Message →</button>
            </div>
        </div>
    </div>

    <!-- MATCH MODAL -->
    <div class="modal-backdrop" id="matchModal" onclick="closeModalOutside(event,'matchModal')">
        <div class="modal-box">
            <button class="modal-close" onclick="closeModal('matchModal')">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M13 3L3 13M3 3l10 10" stroke="#333" stroke-width="1.3" stroke-linecap="round"/></svg>
            </button>
            <div id="matchModalContent">
                <h3 class="modal-title">Find My Agent</h3>
                <p class="modal-sub">Answer a few quick questions and we'll match you with the most suitable specialist.</p>
                <div class="mf-row">
                    <div class="mf-group"><label>First Name</label><input type="text" placeholder="Jane" /></div>
                    <div class="mf-group"><label>Last Name</label><input type="text" placeholder="Doe" /></div>
                </div>
                <div class="mf-group"><label>WhatsApp</label><input type="tel" placeholder="+65 9123 4567" /></div>
                <div class="mf-group"><label>I am looking to…</label>
                    <select><option>Buy a property</option><option>Sell a property</option><option>Rent out my property</option><option>Find a rental</option><option>Invest in property</option></select>
                </div>
                <div class="mf-group"><label>Property Type</label>
                    <select><option>HDB Flat</option><option>Condominium</option><option>Landed / Bungalow</option><option>Commercial</option><option>Overseas Property</option></select>
                </div>
                <div class="mf-group"><label>Budget / Price Range</label>
                    <select><option>Under $500K</option><option>$500K – $1M</option><option>$1M – $2M</option><option>$2M – $5M</option><option>Above $5M</option></select>
                </div>
                <button class="mf-submit" onclick="submitMatchModal()">Match Me →</button>
            </div>
        </div>
    </div>

    <script>
    // ── SCROLL ──
    window.addEventListener('scroll', function() {
        document.getElementById('backToTop').classList.toggle('show', window.scrollY > 400);
    });

    // ── MOBILE MENU ──
    function toggleMobileMenu() {
        var m = document.getElementById('mobileMenu');
        m.classList.toggle('open');
        document.body.style.overflow = m.classList.contains('open') ? 'hidden' : '';
    }

    // ── FADE UP ──
    var fadeObs = new IntersectionObserver(function(entries) {
        entries.forEach(function(e) {
            if (e.isIntersecting) { e.target.classList.add('visible'); fadeObs.unobserve(e.target); }
        });
    }, { threshold: .08 });
    document.querySelectorAll('.fade-up').forEach(function(el) { fadeObs.observe(el); });

    // ── MODALS ──
    function openContact(name) {
        document.getElementById('contactModalTitle').textContent = 'Contact ' + name;
        document.getElementById('contactModal').classList.add('open');
        document.body.style.overflow = 'hidden';
    }
    function openMatchModal() {
        document.getElementById('matchModal').classList.add('open');
        document.body.style.overflow = 'hidden';
    }
    function closeModal(id) {
        document.getElementById(id).classList.remove('open');
        document.body.style.overflow = '';
    }
    function closeModalOutside(e, id) {
        if (e.target === document.getElementById(id)) closeModal(id);
    }
    function submitContactModal() {
        document.getElementById('contactModalContent').innerHTML = '<div style="text-align:center;padding:48px 24px;"><div style="width:60px;height:60px;background:#f5f5f5;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;"><svg width="26" height="26" viewBox="0 0 24 24" fill="none"><path d="M5 12l5 5L20 7" stroke="#1a1a1a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></div><h3 style="font-family:\'DM Serif Display\',serif;font-size:24px;font-weight:400;color:#1a1a1a;margin-bottom:8px;">Message Sent!</h3><p style="font-size:13px;color:#aaa;line-height:1.7;max-width:300px;margin:0 auto;">Your agent will reply via WhatsApp within 2 hours.</p><button onclick="closeModal(\'contactModal\')" style="margin-top:24px;padding:11px 32px;background:#1a1a1a;color:#fff;border:none;font-size:10px;font-weight:700;letter-spacing:1px;text-transform:uppercase;cursor:pointer;border-radius:2px;font-family:\'DM Sans\',sans-serif;">Done</button></div>';
    }
    function submitMatchModal() {
        document.getElementById('matchModalContent').innerHTML = '<div style="text-align:center;padding:48px 24px;"><div style="width:60px;height:60px;background:#f5f5f5;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;"><svg width="26" height="26" viewBox="0 0 24 24" fill="none"><path d="M5 12l5 5L20 7" stroke="#1a1a1a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></div><h3 style="font-family:\'DM Serif Display\',serif;font-size:24px;font-weight:400;color:#1a1a1a;margin-bottom:8px;">Match Requested!</h3><p style="font-size:13px;color:#aaa;line-height:1.7;max-width:300px;margin:0 auto;">We\'ll review your preferences and connect you with the best-fit agent within 2 hours.</p><button onclick="closeModal(\'matchModal\')" style="margin-top:24px;padding:11px 32px;background:#1a1a1a;color:#fff;border:none;font-size:10px;font-weight:700;letter-spacing:1px;text-transform:uppercase;cursor:pointer;border-radius:2px;font-family:\'DM Sans\',sans-serif;">Done</button></div>';
    }

    // ── JOIN FORM ──
    function submitJoinForm(btn) {
        var form = btn.closest('.join-form');
        form.innerHTML = '<div style="text-align:center;padding:48px 24px;"><div style="width:60px;height:60px;background:#f5f5f5;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;"><svg width="26" height="26" viewBox="0 0 24 24" fill="none"><path d="M5 12l5 5L20 7" stroke="#1a1a1a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></div><h3 style="font-family:\'DM Serif Display\',serif;font-size:24px;font-weight:400;color:#1a1a1a;margin-bottom:8px;">Application Received!</h3><p style="font-size:13px;color:#aaa;line-height:1.7;max-width:280px;margin:0 auto;">Our HR team will review your application and get in touch within 2 business days.</p></div>';
    }

    // ── FILTER & SEARCH ──
    var currentFilter = 'all';

    function setFilter(el, tag) {
        document.querySelectorAll('.filter-chip').forEach(function(c) { c.classList.remove('active'); });
        el.classList.add('active');
        currentFilter = tag;
        filterAgents();
    }

    function filterAgents() {
        var q = (document.getElementById('filterSearch').value || '').toLowerCase();
        var cards = document.querySelectorAll('#agentsGrid .agent-card');
        var visible = 0;
        cards.forEach(function(card) {
            var tags = (card.dataset.tags || '').toLowerCase();
            var name = (card.dataset.name || '').toLowerCase();
            var matchFilter = currentFilter === 'all' || tags.includes(currentFilter);
            var matchSearch = !q || name.includes(q) || tags.includes(q);
            var show = matchFilter && matchSearch;
            card.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        document.getElementById('resultsCount').textContent = visible + ' agent' + (visible !== 1 ? 's' : '');
        document.getElementById('noResults').style.display = visible === 0 ? 'block' : 'none';
    }

    function sortAgents(by) {
        var grid = document.getElementById('agentsGrid');
        var cards = Array.from(grid.querySelectorAll('.agent-card'));
        cards.sort(function(a, b) {
            if (by === 'portfolio')   return parseInt(b.dataset.portfolio || 0) - parseInt(a.dataset.portfolio || 0);
            if (by === 'deals')       return parseInt(b.dataset.deals || 0)     - parseInt(a.dataset.deals || 0);
            if (by === 'experience')  return parseInt(b.dataset.exp || 0)       - parseInt(a.dataset.exp || 0);
            return 0;
        });
        cards.forEach(function(c) { grid.appendChild(c); });
    }
    </script>

</body>
</html>
