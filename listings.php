<?php
/**
 * Dwelro — All Listings (listings.php)
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$properties = db_fetch_all("
    SELECT
        p.id, p.title, p.category, p.listing_type, p.status,
        p.price_display, p.price_sgd, p.district, p.area, p.country,
        p.address, p.latitude, p.longitude, p.cover_image_url, p.bedrooms, p.bathrooms, p.floor_area_sqft,
        p.is_featured, p.badge, p.created_at,
        a.full_name AS agent_name
    FROM properties p
    LEFT JOIN agents a ON a.id = p.agent_id
    WHERE p.is_published = 1
    ORDER BY p.created_at DESC
");

$total    = count($properties);
$for_sale = count(array_filter($properties, fn($p) => $p['listing_type'] === 'sale'));
$for_rent = count(array_filter($properties, fn($p) => $p['listing_type'] === 'rent'));

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$props_json = json_encode(array_values($properties), JSON_HEX_TAG | JSON_HEX_AMP);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>All Listings — Dwelro</title>
    <meta name="description" content="Browse Dwelro's curated property listings for sale and rent, with filters for the right home or investment opportunity.">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Dwelro">
    <meta property="og:title" content="All Listings — Dwelro">
    <meta property="og:description" content="Browse Dwelro's curated property listings for sale and rent, with filters for the right home or investment opportunity.">
    <meta property="og:image" content="/img/modern-apartment-architecture.jpg">
    <meta property="og:image:alt" content="Modern apartment building">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="All Listings — Dwelro">
    <meta name="twitter:description" content="Browse Dwelro's curated property listings for sale and rent, with filters for the right home or investment opportunity.">
    <meta name="twitter:image" content="/img/modern-apartment-architecture.jpg">
    <meta name="theme-color" content="#1a3a5c">
    <link rel="preconnect" href="https://fonts.googleapis.com"/>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        :root{
            --navy:#1a3a5c;--navy-dk:#0f2640;--navy-lt:#e8eef5;
            --ink:#171717;--muted:#888;--border:#ebebeb;
            --bg:#fafafa;--white:#fff;--gold:#c9a84c;
            --serif:'DM Serif Display',serif;--sans:'DM Sans',sans-serif;
        }
        body{font-family:var(--sans);background:var(--bg);color:var(--ink);-webkit-font-smoothing:antialiased}

        /* NAV */
        .listing-nav{position:sticky;top:0;z-index:300;background:var(--white);border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;padding:14px 40px;gap:20px;}
        .nav-left{display:flex;align-items:center;gap:16px;}
        .nav-logo{font-family:var(--serif);font-size:24px;color:var(--navy);text-decoration:none;letter-spacing:-.5px}
        .nav-logo span{color:var(--gold)}
        .nav-links{display:flex;align-items:center;gap:18px;flex-wrap:wrap}
        .nav-link{font-size:13px;font-weight:600;color:var(--ink);text-decoration:none;transition:color .2s ease}
        .nav-link:hover{color:var(--navy)}
        .nav-right{display:flex;align-items:center}
        .nav-cta{font-size:11px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--white);text-decoration:none;background:var(--navy);padding:10px 16px;border-radius:4px;transition:background .2s ease}
        .nav-cta:hover{background:#0f2640}
        .page-header{background:var(--navy);padding:48px 40px 40px;color:white;display:grid;gap:22px}
        .page-search{max-width:640px;width:100%;display:flex;align-items:center;gap:12px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.18);border-radius:10px;padding:12px 16px}
        .page-search svg{flex-shrink:0;stroke:#fff}
        .page-search input{flex:1;border:none;background:transparent;color:white;font-size:14px;font-family:var(--sans);outline:none}
        .page-search input::placeholder{color:rgba(255,255,255,.75)}
        .page-header-inner{max-width:1060px;margin:0 auto}
        .page-title{font-family:var(--serif);font-size:52px;margin-bottom:8px;font-weight:400;line-height:1.05}
        .page-subtitle{font-size:16px;opacity:.8;max-width:760px}
        .header-stats{display:none}
        #resultCount{display:none}

        /* SIDEBAR */
        .cat-sidebar{position:fixed;top:64px;left:0;bottom:0;width:260px;background:var(--white);border-right:1px solid var(--border);overflow-y:auto;z-index:100;transition:transform .3s ease}
        .cat-sidebar::-webkit-scrollbar{width:4px}.cat-sidebar::-webkit-scrollbar-thumb{background:var(--border);border-radius:2px}
        .cat-group{padding:24px 0 16px;border-bottom:1px solid var(--border)}.cat-group:last-child{border-bottom:none}
        .cat-group-label{padding:0 20px 12px;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;color:var(--gold)}
        .cat-item{display:flex;align-items:center;justify-content:space-between;padding:9px 20px;font-size:13px;font-weight:500;color:#444;cursor:pointer;border-left:3px solid transparent;transition:all .15s ease;text-decoration:none}
        .cat-item:hover{background:var(--navy-lt);color:var(--navy);border-left-color:var(--navy-lt)}
        .cat-item.active{background:var(--navy-lt);color:var(--navy);border-left-color:var(--navy);font-weight:700}
        .cat-label{flex:1}
        .cat-count{display:none}
        .cat-item.active .cat-count{background:var(--navy);color:white}
        .tc{display:none}

        /* MOBILE TOGGLE */
        .sidebar-toggle{display:none;position:fixed;bottom:24px;left:24px;z-index:300;background:var(--navy);color:white;border:none;border-radius:50%;width:50px;height:50px;font-size:18px;cursor:pointer;box-shadow:0 4px 20px rgba(0,0,0,.2);align-items:center;justify-content:center}

        /* MAIN */
        .main-content{margin-left:260px;transition:margin-left .3s ease}

        /* PAGE HEADER */

        /* FILTER TABS */
        .filter-bar{background:var(--white);border-bottom:1px solid var(--border);position:sticky;top:64px;z-index:90}
        .filter-bar-inner{max-width:1060px;margin:0 auto;display:flex;height:52px;padding:0 40px;overflow-x:auto;scrollbar-width:none}
        .filter-bar-inner::-webkit-scrollbar{display:none}
        .filter-tab{background:none;border:none;padding:0 18px;font-size:11px;font-weight:700;text-transform:uppercase;cursor:pointer;color:var(--muted);border-bottom:2px solid transparent;display:flex;align-items:center;gap:7px;white-space:nowrap;transition:color .15s}
        .filter-tab:hover{color:var(--ink)}
        .filter-tab.active{color:var(--navy);border-bottom-color:var(--navy)}
        .tc{background:#eee;padding:2px 6px;border-radius:10px;font-size:9px;font-weight:700}

        /* CONTROLS */
        .controls-row{max-width:1060px;margin:0 auto;padding:16px 40px;display:flex;gap:12px;align-items:center}
        .search-input{flex:1;height:40px;padding:0 15px;border:1px solid var(--border);border-radius:4px;font-family:var(--sans);font-size:13px;outline:none}
        .search-input:focus{border-color:var(--navy)}
        .sort-select{height:40px;padding:0 10px;border:1px solid var(--border);border-radius:4px;font-size:11px;text-transform:uppercase;font-weight:700;font-family:var(--sans)}
        #resultCount{font-size:11px;font-weight:700;white-space:nowrap;color:var(--muted)}

        /* GRID */
        .listings-wrapper{max-width:1060px;margin:0 auto;padding:4px 40px 80px}
        .listings-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:22px}
        .prop-card{background:white;border:1px solid var(--border);transition:.25s;position:relative;cursor:pointer}
        .prop-card:hover{transform:translateY(-3px);box-shadow:0 10px 30px rgba(0,0,0,.07)}
        .card-img{height:210px;background:#eee;overflow:hidden;position:relative}
        .card-img img{width:100%;height:100%;object-fit:cover;transition:transform .4s ease}
        .prop-card:hover .card-img img{transform:scale(1.03)}
        .card-badge{position:absolute;top:14px;left:14px;background:var(--navy);color:white;padding:4px 10px;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.5px}
        .card-type-badge{position:absolute;top:14px;right:14px;padding:4px 10px;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.5px}
        .type-sale{background:var(--gold);color:var(--navy-dk)}
        .type-rent{background:#22c55e;color:white}
        .card-body{padding:18px 20px 14px}
        .card-price{font-family:var(--serif);font-size:22px;color:var(--navy);margin-bottom:4px}
        .card-title{font-size:14px;font-weight:600;margin-bottom:6px;color:var(--ink)}
        .card-location{font-size:12px;color:var(--muted);margin-bottom:13px}
        .card-stats{display:flex;border-top:1px solid var(--border);padding-top:12px;gap:0}
        .card-stat{flex:1;text-align:center;font-size:11px;font-weight:600;color:#555;padding:0 4px}
        .card-stat-val{display:block;font-size:14px;font-weight:700;color:var(--navy);margin-bottom:2px}
        .card-stat-lbl{font-size:9px;text-transform:uppercase;letter-spacing:.5px;color:var(--muted)}
        .card-stat + .card-stat{border-left:1px solid var(--border)}
        .card-footer{padding:12px 20px;border-top:1px solid var(--border);display:flex;justify-content:space-between;align-items:center}
        .card-cat{font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px}
        .card-view-btn{border:1px solid var(--navy);background:none;color:var(--navy);padding:7px 16px;font-size:10px;font-weight:700;cursor:pointer;text-transform:uppercase;letter-spacing:.5px;transition:.15s;font-family:var(--sans)}
        .card-view-btn:hover{background:var(--navy);color:white}

        /* EMPTY */
        .empty-state{grid-column:1/-1;text-align:center;padding:80px 20px;color:var(--muted)}
        .empty-state h3{font-family:var(--serif);font-size:22px;color:var(--navy);margin-bottom:8px}
        .empty-state p{font-size:13px}

        /* RESPONSIVE */
        @media(max-width:900px){
            .cat-sidebar{transform:translateX(-100%)}.cat-sidebar.open{transform:translateX(0)}
            .main-content{margin-left:0}
            .sidebar-toggle{display:flex}
            nav,.page-header,.controls-row,.listings-wrapper,.filter-bar-inner{padding-left:20px;padding-right:20px}
        }

        /* MAP VIEW STYLES */
        .view-toggle{position:absolute;top:16px;right:16px;z-index:10;display:flex;border:1px solid var(--border);border-radius:6px;overflow:hidden;background:var(--white)}
        .view-toggle-btn{background:none;border:none;padding:8px 16px;font-size:11px;font-weight:700;text-transform:uppercase;cursor:pointer;color:var(--muted);transition:background .15s}
        .view-toggle-btn.active{background:var(--navy);color:white}
        .view-toggle-btn:hover:not(.active){background:var(--navy-lt);color:var(--navy)}

.map-container{display:none;position:fixed;top:64px;left:260px;right:0;bottom:0;z-index:50;background:#f0f3f8}
.map-container.active{display:block}
.listings-wrapper.map-active{display:none}

#map{height:100%;width:100%;background:#e8eef5}
#mapStatus{position:absolute;top:18px;left:18px;z-index:500;background:rgba(255,255,255,.94);border:1px solid rgba(26,58,92,.12);border-radius:8px;padding:10px 14px;font-size:12px;color:#1a3a5c;box-shadow:0 8px 20px rgba(0,0,0,.08);max-width:260px}
#mapStatus strong{display:block;margin-bottom:4px;font-size:13px}

        .leaflet-popup-content-wrapper{border-radius:6px;box-shadow:0 4px 20px rgba(0,0,0,.15);border:none;padding:0;overflow:hidden}
        .leaflet-popup-content{margin:0!important;width:220px!important}
        .map-popup img{width:100%;height:110px;object-fit:cover;display:block}
        .map-popup-body{padding:10px 12px}
        .map-popup-price{font-family:var(--serif);font-size:16px;color:var(--navy);margin-bottom:2px}
        .map-popup-addr{font-size:11px;color:var(--muted);margin-bottom:6px}
        .map-popup-meta{font-size:11px;font-weight:600;color:#555;margin-bottom:8px}
        .map-popup-btn{display:block;width:100%;padding:7px;background:var(--navy);color:#fff;text-align:center;font-size:11px;font-weight:700;text-transform:uppercase;border:none;cursor:pointer}
        .map-popup-btn:hover{background:var(--navy-dk)}

        .price-label{background:var(--navy);color:#fff;padding:4px 8px;border-radius:4px;font-size:11px;font-weight:700;white-space:nowrap;cursor:pointer;box-shadow:0 2px 8px rgba(0,0,0,.25);transition:background .15s,transform .15s;transform-origin:center bottom;position:relative}
        .price-label::after{content:'';position:absolute;bottom:-5px;left:50%;transform:translateX(-50%);border:5px solid transparent;border-top-color:var(--navy);border-bottom:none}
        .price-label:hover,.price-label.active{background:var(--gold);transform:scale(1.1)}
        .price-label.active::after{border-top-color:var(--gold)}

        @media(max-width:900px){
            .map-container{left:0}
        }
    </style>
</head>
<body>

<nav class="listing-nav">
    <div class="nav-left">
        <a href="index.php" class="nav-logo">dwelro</a>
    </div>
    <div class="nav-links">
        <a href="buy.html" class="nav-link">Buy</a>
        <a href="rent.html" class="nav-link">Rent</a>
        <a href="sell.html" class="nav-link">Sell</a>
        <a href="Investment.html" class="nav-link">Investment</a>
        <a href="listings.php" class="nav-link active">Listings</a>
        <a href="map-search.html" class="nav-link">Map Search</a>
        <a href="agents.php" class="nav-link">Agents</a>
        <a href="about.html" class="nav-link">About</a>
        <a href="blog.html" class="nav-link">Blog</a>
        <a href="Valuation.html" class="nav-link">Valuation</a>
        <a href="contact.html" class="nav-link">Contact</a>
    </div>
    <div class="nav-right">
        <a href="contact.html" class="nav-cta">Book a Call</a>
    </div>
</nav>

<aside class="cat-sidebar" id="catSidebar">

    <div class="cat-group">
        <div class="cat-group-label">Browse By Type</div>
        <a class="cat-item active" data-filter="all" href="#" onclick="CatNav.select(this,'all');return false;">
            <span class="cat-label">All Listings</span>
            <span class="cat-count" id="cnt-all"><?= $total ?></span>
        </a>
        <a class="cat-item" data-filter="cat:condo" href="#" onclick="CatNav.select(this,'cat:condo');return false;">
            <span class="cat-label">Condo</span>
            <span class="cat-count" id="cnt-cat:condo">0</span>
        </a>
        <a class="cat-item" data-filter="cat:landed" href="#" onclick="CatNav.select(this,'cat:landed');return false;">
            <span class="cat-label">Landed</span>
            <span class="cat-count" id="cnt-cat:landed">0</span>
        </a>
        <a class="cat-item" data-filter="cat:hdb" href="#" onclick="CatNav.select(this,'cat:hdb');return false;">
            <span class="cat-label">HDB</span>
            <span class="cat-count" id="cnt-cat:hdb">0</span>
        </a>
        <a class="cat-item" data-filter="cat:commercial" href="#" onclick="CatNav.select(this,'cat:commercial');return false;">
            <span class="cat-label">Commercial</span>
            <span class="cat-count" id="cnt-cat:commercial">0</span>
        </a>
        <a class="cat-item" data-filter="cat:overseas" href="#" onclick="CatNav.select(this,'cat:overseas');return false;">
            <span class="cat-label">Overseas</span>
            <span class="cat-count" id="cnt-cat:overseas">0</span>
        </a>
        <a class="cat-item" data-filter="cat:new_dev" href="#" onclick="CatNav.select(this,'cat:new_dev');return false;">
            <span class="cat-label">New Development</span>
            <span class="cat-count" id="cnt-cat:new_dev">0</span>
        </a>
        <a class="cat-item" data-filter="cat:investment" href="#" onclick="CatNav.select(this,'cat:investment');return false;">
            <span class="cat-label">Investment</span>
            <span class="cat-count" id="cnt-cat:investment">0</span>
        </a>
    </div>

    <div class="cat-group">
        <div class="cat-group-label">Listing Type</div>
        <a class="cat-item" data-filter="type:sale" href="#" onclick="CatNav.select(this,'type:sale');return false;">
            <span class="cat-label">For Sale</span>
            <span class="cat-count" id="cnt-type:sale"><?= $for_sale ?></span>
        </a>
        <a class="cat-item" data-filter="type:rent" href="#" onclick="CatNav.select(this,'type:rent');return false;">
            <span class="cat-label">For Rent</span>
            <span class="cat-count" id="cnt-type:rent"><?= $for_rent ?></span>
        </a>
    </div>

    <div class="cat-group">
        <div class="cat-group-label">Discover</div>
        <a class="cat-item" data-filter="featured" href="#" onclick="CatNav.select(this,'featured');return false;">
            <span class="cat-label">Featured</span>
            <span class="cat-count" id="cnt-featured">0</span>
        </a>
        <a class="cat-item" data-filter="status:available" href="#" onclick="CatNav.select(this,'status:available');return false;">
            <span class="cat-label">Available Now</span>
            <span class="cat-count" id="cnt-status:available">0</span>
        </a>
        <a class="cat-item" data-filter="status:under_offer" href="#" onclick="CatNav.select(this,'status:under_offer');return false;">
            <span class="cat-label">Under Offer</span>
            <span class="cat-count" id="cnt-status:under_offer">0</span>
        </a>
    </div>

</aside>

<div class="main-content">

    <header class="page-header">
        <div class="page-header-inner">
            <div class="view-toggle">
                <button class="view-toggle-btn active" onclick="setView('grid')">Grid View</button>
                <button class="view-toggle-btn" onclick="setView('map')">Map View</button>
            </div>
            <h1 class="page-title" id="pageTitle">All Properties</h1>
            <p class="page-subtitle">Search Europe’s best listings, curated for investors and homebuyers.</p>
            <div class="page-search">
                <svg width="18" height="18" viewBox="0 0 16 16" fill="none"><path d="M15 15L11.38 11.38M1 7.03C1 3.7 3.7 1 7.03 1C10.37 1 13.07 3.7 13.07 7.03C13.07 10.37 10.37 13.07 7.03 13.07C3.7 13.07 1 10.37 1 7.03Z" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
                <input type="text" id="searchInput" class="search-input" placeholder="Search by title, area, or district..." />
            </div>
            <div id="activePill" style="display:none">
                <div class="active-filter-pill">
                    <span id="pillLabel"></span>
                    <button onclick="CatNav.clearFilter()" title="Clear">&times;</button>
                </div>
            </div>
        </div>
    </header>

    <div class="filter-bar">
        <div class="filter-bar-inner" id="filterTabs"></div>
    </div>

    <div class="controls-row">
        <select id="sortSelect" class="sort-select">
            <option value="newest">Newest First</option>
            <option value="price_asc">Price: Low to High</option>
            <option value="price_desc">Price: High to Low</option>
        </select>
        <span id="resultCount">0 Results</span>
    </div>

    <main class="listings-wrapper">
        <div class="listings-grid" id="propertyGrid"></div>
    </main>

</div>

<div class="map-container" id="mapContainer">
    <div id="map"></div>
</div>

<button class="sidebar-toggle" onclick="document.getElementById('catSidebar').classList.toggle('open')">&#9776;</button>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const ALL_DATA = <?= $props_json ?>;

const LABEL_MAP = {
    'all':'All Listings','cat:condo':'Condo','cat:landed':'Landed','cat:hdb':'HDB',
    'cat:commercial':'Commercial','cat:overseas':'Overseas','cat:new_dev':'New Development',
    'cat:investment':'Investment','type:sale':'For Sale','type:rent':'For Rent',
    'featured':'Featured','status:available':'Available Now','status:under_offer':'Under Offer'
};

function matchesFilter(p, f) {
    if (f === 'all')      return true;
    if (f === 'featured') return p.is_featured == 1;
    if (f.startsWith('cat:'))    return (p.category     || '').toLowerCase() === f.slice(4);
    if (f.startsWith('type:'))   return (p.listing_type || '').toLowerCase() === f.slice(5);
    if (f.startsWith('status:')) return (p.status       || '').toLowerCase() === f.slice(7);
    return false;
}

function updateCounts() {
    Object.keys(LABEL_MAP).forEach(k => {
        const el = document.getElementById('cnt-' + k);
        if (el) el.textContent = k === 'all' ? ALL_DATA.length : ALL_DATA.filter(p => matchesFilter(p, k)).length;
    });
}

const CatNav = (() => {
    let active = 'all';
    function select(el, f) {
        document.querySelectorAll('.cat-item').forEach(i => i.classList.remove('active'));
        el.classList.add('active');
        active = f;
        document.getElementById('pageTitle').textContent = f === 'all' ? 'All Properties' : (LABEL_MAP[f] || f);
        const pill = document.getElementById('activePill');
        if (f === 'all') { pill.style.display = 'none'; }
        else { pill.style.display = ''; document.getElementById('pillLabel').textContent = LABEL_MAP[f] || f; }
        filterAndRender();
        if (window.innerWidth < 900) document.getElementById('catSidebar').classList.remove('open');
    }
    return {
        select,
        clearFilter: () => select(document.querySelector('.cat-item[data-filter="all"]'), 'all'),
        getFilter: () => active
    };
})();

let currentTab = 'all';
function renderTabs() {
    const cats = ['all', ...new Set(ALL_DATA.map(p => p.category).filter(Boolean).sort())];
    document.getElementById('filterTabs').innerHTML = cats.map(c => {
        const n = c === 'all' ? ALL_DATA.length : ALL_DATA.filter(p => p.category === c).length;
        return `<button class="filter-tab ${currentTab===c?'active':''}" onclick="setTab('${c}')">${c} <span class="tc">${n}</span></button>`;
    }).join('');
}
function setTab(c) { currentTab = c; renderTabs(); filterAndRender(); }

function filterAndRender() {
    const q    = document.getElementById('searchInput').value.toLowerCase();
    const sort = document.getElementById('sortSelect').value;
    const sf   = CatNav.getFilter();
    let data   = ALL_DATA.filter(p => {
        return (currentTab === 'all' || p.category === currentTab)
            && matchesFilter(p, sf)
            && ((p.title||'').toLowerCase().includes(q) || (p.area||'').toLowerCase().includes(q) || (p.district||'').toLowerCase().includes(q));
    });
    if (sort === 'price_asc')  data.sort((a,b) => (a.price_sgd||0)-(b.price_sgd||0));
    if (sort === 'price_desc') data.sort((a,b) => (b.price_sgd||0)-(a.price_sgd||0));
    if (sort === 'newest')     data.sort((a,b) => new Date(b.created_at||0)-new Date(a.created_at||0));

    document.getElementById('resultCount').textContent = data.length + ' Properties';
    const grid = document.getElementById('propertyGrid');

    if (!data.length) {
        grid.innerHTML = '<div class="empty-state"><h3>No properties found</h3><p>Try adjusting your filters or search term.</p></div>';
        return;
    }

    grid.innerHTML = data.map(p => `
        <div class="prop-card" onclick="location.href='property.php?id=${p.id}'">
            <div class="card-img">
                ${p.badge ? `<div class="card-badge">${p.badge}</div>` : ''}
                ${p.listing_type ? `<div class="card-type-badge type-${p.listing_type}">${p.listing_type === 'sale' ? 'For Sale' : 'For Rent'}</div>` : ''}
                <img src="${p.cover_image_url || 'https://placehold.co/400x280/e8eef5/1a3a5c?text=No+Image'}"
                     alt="${(p.title||'').replace(/"/g,'&quot;')}"
                     onerror="this.src='https://placehold.co/400x280/e8eef5/1a3a5c?text=No+Image'"
                     loading="lazy">
            </div>
            <div class="card-body">
                <div class="card-price">${p.price_display || '—'}</div>
                <div class="card-title">${p.title || 'Untitled'}</div>
                <div class="card-location">${[p.area, p.district].filter(Boolean).join(', ')}</div>
                <div class="card-stats">
                    <div class="card-stat"><span class="card-stat-val">${p.bedrooms || 0}</span><span class="card-stat-lbl">Beds</span></div>
                    <div class="card-stat"><span class="card-stat-val">${p.bathrooms || 0}</span><span class="card-stat-lbl">Baths</span></div>
                    <div class="card-stat"><span class="card-stat-val">${p.floor_area_sqft || '—'}</span><span class="card-stat-lbl">sqft</span></div>
                </div>
            </div>
            <div class="card-footer">
                <span class="card-cat">${(p.category||'').replace(/_/g,' ')}</span>
                <button class="card-view-btn" onclick="event.stopPropagation();location.href='property.php?id=${p.id}'">View Details</button>
            </div>
        </div>
    `).join('');
}

document.addEventListener('DOMContentLoaded', () => {
    initMap();
    updateCounts(); renderTabs(); filterAndRender();
    document.getElementById('searchInput').addEventListener('input', filterAndRender);
    document.getElementById('sortSelect').addEventListener('change', filterAndRender);
});

// MAP FUNCTIONALITY
let map, markers = [];
let currentView = 'grid';

function initMap() {
    if (map) return; // Already initialized

    map = L.map('map').setView([1.3521, 103.8198], 11); // Singapore coordinates
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19
    }).addTo(map);
    setTimeout(() => { if (map) map.invalidateSize(); }, 200);
}

function setView(view) {
    currentView = view;
    document.querySelectorAll('.view-toggle-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelector(`[onclick="setView('${view}')"]`).classList.add('active');

    const mapContainer = document.getElementById('mapContainer');
    const listingsWrapper = document.querySelector('.listings-wrapper');

    if (view === 'map') {
        mapContainer.classList.add('active');
        listingsWrapper.classList.add('map-active');
        initMap();
        setTimeout(() => {
            if (map) map.invalidateSize(true);
            renderMapMarkers();
        }, 250);
    } else {
        mapContainer.classList.remove('active');
        listingsWrapper.classList.remove('map-active');
    }
}

function renderMapMarkers() {
    if (!map) return;

    // Clear existing markers
    markers.forEach(marker => map.removeLayer(marker));
    markers = [];

    // Get current filtered data
    const q = document.getElementById('searchInput').value.toLowerCase();
    const sort = document.getElementById('sortSelect').value;
    const sf = CatNav.getFilter();

    let data = ALL_DATA.filter(p => {
        return (currentTab === 'all' || p.category === currentTab)
            && matchesFilter(p, sf)
            && ((p.title||'').toLowerCase().includes(q) || (p.area||'').toLowerCase().includes(q) || (p.district||'').toLowerCase().includes(q))
            && p.latitude && p.longitude; // Only properties with coordinates
    });

    if (sort === 'price_asc')  data.sort((a,b) => (a.price_sgd||0)-(b.price_sgd||0));
    if (sort === 'price_desc') data.sort((a,b) => (b.price_sgd||0)-(a.price_sgd||0));
    if (sort === 'newest')     data.sort((a,b) => new Date(b.created_at||0)-new Date(a.created_at||0));

    // Fit map to show all markers
    if (data.length > 0) {
        const bounds = data.map(p => [p.latitude, p.longitude]);
        map.fitBounds(bounds, { padding: [20, 20] });
        setTimeout(() => { if (map) map.invalidateSize(true); }, 150);
    } else {
        map.setView([1.3521, 103.8198], 11);
    }

    // Add markers
    data.forEach(p => {
        const price = p.price_sgd || 0;
        const label = price >= 1e6 ? `$${(price / 1e6).toFixed(1)}M` : price >= 1000 ? `$${Math.round(price / 1000)}K` : `$${price}`;
        const icon = L.divIcon({
            className: '',
            html: `<div class="price-label" data-id="${p.id}">${label}</div>`,
            iconAnchor: [0, 0]
        });

        const addr = p.address || p.title || '';
        const img = p.cover_image_url || null;

        const marker = L.marker([p.latitude, p.longitude], { icon })
            .addTo(map)
            .bindPopup(`
                <div class="map-popup">
                    ${img ? `<img src="${img}" alt="">` : ''}
                    <div class="map-popup-body">
                        <div class="map-popup-price">${p.price_display || 'Price N/A'}</div>
                        <div class="map-popup-addr">${p.title || addr}</div>
                        <div class="map-popup-meta">🛏 ${p.bedrooms || '—'} &nbsp; 🛁 ${p.bathrooms || '—'} &nbsp; 📏 ${p.floor_area_sqft ? p.floor_area_sqft.toLocaleString() : '—'} sqft</div>
                        <button class="map-popup-btn" onclick="window.location.href='property.php?id=${p.id}'">View Details</button>
                    </div>
                </div>`, { maxWidth: 220 });

        markers.push(marker);
    });

    const status = document.getElementById('mapStatus');
    if (status) {
        status.innerHTML = `<strong>Map mode</strong>${data.length} marker${data.length===1?'':'s'} found.`;
        if (!data.length) {
            status.innerHTML = `<strong>Map mode</strong>No geotagged listings match this filter.`;
        }
    }
}

window.addEventListener('resize', () => {
    if (map) map.invalidateSize(true);
});

// Override the existing filterAndRender to also update map when in map view
const originalFilterAndRender = filterAndRender;
filterAndRender = function() {
    originalFilterAndRender();
    if (currentView === 'map') {
        renderMapMarkers();
    }
};
</script>
</body>
</html>
