/**
 * ============================================================
 *  DWELRO — Property Detail Modal  (property-modal.js)
 *  Include AFTER dwelro-api.js in every page that has property cards
 *
 *  Usage:
 *    "View →" button calls: openPropertyModal(propertyId)
 *    Inside the modal, "Book Viewing" calls the existing openBooking()
 * ============================================================
 */

(function() {

        // ── Inject styles ─────────────────────────────────────────
        const STYLES = `
      /* ── PROPERTY MODAL BACKDROP ── */
      #propModal {
        position: fixed;
        inset: 0;
        z-index: 10100;
        display: flex;
        align-items: stretch;
        justify-content: flex-end;
        opacity: 0;
        visibility: hidden;
        transition: opacity .35s ease, visibility .35s ease;
      }
      #propModal.open {
        opacity: 1;
        visibility: visible;
      }
      #propModalDim {
        position: absolute;
        inset: 0;
        background: rgba(10,15,25,.72);
        backdrop-filter: blur(4px);
        cursor: pointer;
      }
  
      /* ── DRAWER PANEL ── */
      #propModalPanel {
        position: relative;
        z-index: 2;
        width: 100%;
        max-width: 860px;
        height: 100%;
        background: #fff;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        transform: translateX(100%);
        transition: transform .42s cubic-bezier(.22,1,.36,1);
        box-shadow: -24px 0 80px rgba(0,0,0,.22);
      }
      #propModal.open #propModalPanel {
        transform: translateX(0);
      }
  
      /* ── CLOSE BUTTON ── */
      #propModalClose {
        position: absolute;
        top: 20px;
        right: 20px;
        z-index: 20;
        width: 40px;
        height: 40px;
        background: rgba(255,255,255,.92);
        border: none;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 2px 16px rgba(0,0,0,.14);
        transition: background .2s, transform .2s;
      }
      #propModalClose:hover {
        background: #fff;
        transform: scale(1.08);
      }
  
      /* ── IMAGE GALLERY ── */
      #propGallery {
        position: relative;
        width: 100%;
        height: 420px;
        flex-shrink: 0;
        background: #111;
        overflow: hidden;
      }
      @media(max-width:600px) { #propGallery { height: 260px; } }
  
      .prop-gallery-slide {
        position: absolute;
        inset: 0;
        opacity: 0;
        transition: opacity .55s ease;
      }
      .prop-gallery-slide.active { opacity: 1; z-index: 2; }
      .prop-gallery-slide img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
      }
      .prop-gallery-overlay {
        position: absolute;
        inset: 0;
        background: linear-gradient(180deg, rgba(0,0,0,.35) 0%, transparent 40%, rgba(0,0,0,.45) 100%);
        z-index: 3;
        pointer-events: none;
      }
  
      /* Gallery arrows */
      .prop-gal-arrow {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        z-index: 10;
        width: 40px;
        height: 40px;
        background: rgba(255,255,255,.18);
        border: 1px solid rgba(255,255,255,.3);
        backdrop-filter: blur(8px);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        border-radius: 2px;
        transition: background .2s;
      }
      .prop-gal-arrow:hover { background: rgba(255,255,255,.32); }
      #propGalPrev { left: 16px; }
      #propGalNext { right: 16px; }
  
      /* Gallery dots */
      #propGalDots {
        position: absolute;
        bottom: 16px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 10;
        display: flex;
        gap: 6px;
        align-items: center;
      }
      .pgd {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: rgba(255,255,255,.4);
        cursor: pointer;
        transition: all .25s;
      }
      .pgd.on {
        background: #fff;
        width: 20px;
        border-radius: 3px;
      }
  
      /* Gallery badge & counter */
      #propGalBadge {
        position: absolute;
        top: 16px;
        left: 16px;
        z-index: 10;
        font-size: 9px;
        font-weight: 700;
        letter-spacing: 1.2px;
        text-transform: uppercase;
        padding: 5px 10px;
        background: #1a3a5c;
        color: #fff;
      }
      #propGalCounter {
        position: absolute;
        bottom: 16px;
        right: 16px;
        z-index: 10;
        font-size: 11px;
        font-weight: 600;
        color: rgba(255,255,255,.7);
        letter-spacing: .5px;
      }
  
      /* ── THUMBNAIL STRIP ── */
      #propThumbs {
        display: flex;
        gap: 6px;
        padding: 10px 24px;
        background: #f5f5f3;
        overflow-x: auto;
        flex-shrink: 0;
        scrollbar-width: none;
      }
      #propThumbs::-webkit-scrollbar { display: none; }
      .prop-thumb {
        width: 72px;
        height: 52px;
        flex-shrink: 0;
        border-radius: 2px;
        overflow: hidden;
        cursor: pointer;
        opacity: .55;
        border: 2px solid transparent;
        transition: opacity .2s, border-color .2s;
      }
      .prop-thumb:hover { opacity: .85; }
      .prop-thumb.on { opacity: 1; border-color: #1a3a5c; }
      .prop-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
  
      /* ── SCROLLABLE CONTENT ── */
      #propModalContent {
        flex: 1;
        overflow-y: auto;
        padding: 28px 32px 40px;
        scrollbar-width: thin;
        scrollbar-color: #e0e0e0 transparent;
      }
      @media(max-width:600px) { #propModalContent { padding: 20px 18px 32px; } }
  
      /* Header row */
      #propModalHeader {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 20px;
        flex-wrap: wrap;
      }
      #propModalTitle {
        font-family: 'DM Serif Display', serif;
        font-size: clamp(22px, 3vw, 30px);
        font-weight: 400;
        color: #171717;
        letter-spacing: -.3px;
        line-height: 1.15;
      }
      #propModalPrice {
        font-family: 'DM Serif Display', serif;
        font-size: clamp(20px, 2.5vw, 26px);
        font-weight: 400;
        color: #1a3a5c;
        white-space: nowrap;
        letter-spacing: -.3px;
      }
  
      /* Location */
      #propModalLocation {
        display: flex;
        align-items: center;
        gap: 5px;
        font-size: 12px;
        color: #999;
        margin-bottom: 20px;
      }
  
      /* Key stats bar */
      #propModalStats {
        display: flex;
        gap: 0;
        border: 1px solid #ebebeb;
        border-radius: 2px;
        margin-bottom: 24px;
        overflow: hidden;
        flex-wrap: wrap;
      }
      .pms-item {
        flex: 1;
        min-width: 80px;
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 14px 10px;
        border-right: 1px solid #ebebeb;
        gap: 5px;
      }
      .pms-item:last-child { border-right: none; }
      .pms-icon { color: #1a3a5c; }
      .pms-val {
        font-size: 14px;
        font-weight: 700;
        color: #171717;
        letter-spacing: -.2px;
      }
      .pms-label {
        font-size: 10px;
        color: #bbb;
        letter-spacing: .5px;
        text-transform: uppercase;
        font-weight: 600;
      }
  
      /* Section headings */
      .pm-section-title {
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 2px;
        text-transform: uppercase;
        color: #1a3a5c;
        margin-bottom: 10px;
        margin-top: 28px;
      }
      .pm-section-title:first-of-type { margin-top: 0; }
  
      /* Description */
      #propModalDesc {
        font-size: 14px;
        color: #555;
        line-height: 1.75;
        margin-bottom: 8px;
      }
  
      /* Features */
      #propModalFeatures {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 8px;
      }
      .pmf-tag {
        font-size: 11px;
        font-weight: 600;
        color: #444;
        background: #f5f5f3;
        border: 1px solid #e8e8e8;
        padding: 5px 12px;
        border-radius: 2px;
        display: flex;
        align-items: center;
        gap: 5px;
      }
      .pmf-tag::before {
        content: '';
        width: 5px;
        height: 5px;
        border-radius: 50%;
        background: #1a3a5c;
        flex-shrink: 0;
      }
  
      /* Details grid */
      #propModalDetails {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1px;
        background: #ebebeb;
        border: 1px solid #ebebeb;
        border-radius: 2px;
        overflow: hidden;
        margin-bottom: 8px;
      }
      .pmd-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 11px 14px;
        background: #fff;
        gap: 8px;
      }
      .pmd-key {
        font-size: 11px;
        color: #aaa;
        font-weight: 600;
        letter-spacing: .3px;
      }
      .pmd-val {
        font-size: 12px;
        color: #171717;
        font-weight: 700;
        text-align: right;
      }
  
      /* Map embed */
      #propModalMap {
        width: 100%;
        height: 220px;
        border: none;
        border-radius: 2px;
        margin-bottom: 8px;
      }
  
      /* Agent card */
      #propModalAgent {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 18px;
        border: 1px solid #ebebeb;
        border-radius: 2px;
        margin-bottom: 8px;
      }
      #propModalAgent img {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        object-fit: cover;
        flex-shrink: 0;
      }
      .pma-name { font-size: 14px; font-weight: 700; color: #171717; margin-bottom: 2px; }
      .pma-title { font-size: 11px; color: #999; margin-bottom: 10px; }
      .pma-btns { display: flex; gap: 8px; flex-wrap: wrap; }
      .pma-btn {
        font-size: 10px;
        font-weight: 700;
        letter-spacing: .8px;
        text-transform: uppercase;
        padding: 7px 14px;
        border-radius: 2px;
        border: none;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        transition: all .2s;
      }
      .pma-wa { background: #25D366; color: #fff; }
      .pma-wa:hover { background: #1db954; }
      .pma-call { background: #f5f5f3; color: #171717; border: 1px solid #e0e0e0; }
      .pma-call:hover { border-color: #171717; }
  
      /* ── STICKY FOOTER CTA ── */
      #propModalFooter {
        padding: 16px 32px;
        border-top: 1px solid #ebebeb;
        background: #fff;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-shrink: 0;
      }
      @media(max-width:600px) { #propModalFooter { padding: 14px 18px; } }
      #propModalFooterPrice {
        flex: 1;
      }
      #propModalFooterPrice .fp-label {
        font-size: 10px;
        color: #bbb;
        font-weight: 600;
        letter-spacing: 1px;
        text-transform: uppercase;
      }
      #propModalFooterPrice .fp-val {
        font-family: 'DM Serif Display', serif;
        font-size: 20px;
        color: #171717;
        letter-spacing: -.2px;
      }
      #propModalBookBtn {
        background: #1a3a5c;
        color: #fff;
        border: none;
        padding: 13px 32px;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 1px;
        text-transform: uppercase;
        cursor: pointer;
        border-radius: 2px;
        transition: background .2s;
        white-space: nowrap;
      }
      #propModalBookBtn:hover { background: #0f2640; }
  
      /* Loading state */
      #propModalLoading {
        position: absolute;
        inset: 0;
        background: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 50;
        flex-direction: column;
        gap: 16px;
      }
      .pm-spinner {
        width: 36px;
        height: 36px;
        border: 2px solid #ebebeb;
        border-top-color: #1a3a5c;
        border-radius: 50%;
        animation: pmSpin .7s linear infinite;
      }
      @keyframes pmSpin { to { transform: rotate(360deg); } }
      .pm-loading-text {
        font-size: 12px;
        color: #bbb;
        letter-spacing: 1px;
        text-transform: uppercase;
        font-weight: 600;
      }
    `;

        const styleEl = document.createElement('style');
        styleEl.textContent = STYLES;
        document.head.appendChild(styleEl);

        // ── Inject HTML shell ──────────────────────────────────────
        const SHELL = `
      <div id="propModal">
        <div id="propModalDim"></div>
        <div id="propModalPanel">
  
          <!-- Loading overlay -->
          <div id="propModalLoading">
            <div class="pm-spinner"></div>
            <div class="pm-loading-text">Loading property…</div>
          </div>
  
          <!-- Close -->
          <button id="propModalClose" aria-label="Close">
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
              <path d="M12 2L2 12M2 2l10 10" stroke="#171717" stroke-width="1.4" stroke-linecap="round"/>
            </svg>
          </button>
  
          <!-- Gallery -->
          <div id="propGallery">
            <div id="propGalSlides"></div>
            <div class="prop-gallery-overlay"></div>
            <div id="propGalBadge" style="display:none;"></div>
            <div id="propGalCounter"></div>
            <button class="prop-gal-arrow" id="propGalPrev">
              <svg width="16" height="12" viewBox="0 0 16 12" fill="none"><path d="M5 1L1 6M1 6L5 11M1 6H15" stroke="#fff" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
            <button class="prop-gal-arrow" id="propGalNext">
              <svg width="16" height="12" viewBox="0 0 16 12" fill="none"><path d="M11 1L15 6M15 6L11 11M15 6H1" stroke="#fff" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
            <div id="propGalDots"></div>
          </div>
  
          <!-- Thumbnails -->
          <div id="propThumbs"></div>
  
          <!-- Scrollable content -->
          <div id="propModalContent">
            <div id="propModalHeader">
              <div id="propModalTitle"></div>
              <div id="propModalPrice"></div>
            </div>
            <div id="propModalLocation"></div>
            <div id="propModalStats"></div>
  
            <p class="pm-section-title">About This Property</p>
            <p id="propModalDesc"></p>
  
            <p class="pm-section-title" id="propFeaturesLabel" style="display:none;">Features & Amenities</p>
            <div id="propModalFeatures"></div>
  
            <p class="pm-section-title">Property Details</p>
            <div id="propModalDetails"></div>
  
            <p class="pm-section-title">Location</p>
            <iframe id="propModalMap" loading="lazy" allowfullscreen referrerpolicy="no-referrer-when-downgrade" src=""></iframe>
  
            <p class="pm-section-title">Your Agent</p>
            <div id="propModalAgent"></div>
          </div>
  
          <!-- Sticky footer -->
          <div id="propModalFooter">
            <div id="propModalFooterPrice">
              <div class="fp-label">Asking Price</div>
              <div class="fp-val" id="propFooterPriceVal"></div>
            </div>
            <button id="propModalBookBtn">Book a Viewing</button>
          </div>
  
        </div>
      </div>
    `;

        document.body.insertAdjacentHTML('beforeend', SHELL);

        // ── State ──────────────────────────────────────────────────
        let currentProp = null;
        let galIdx = 0;
        let galImages = [];

        // ── DOM refs ───────────────────────────────────────────────
        const modal = document.getElementById('propModal');
        const panel = document.getElementById('propModalPanel');
        const dim = document.getElementById('propModalDim');
        const closeBtn = document.getElementById('propModalClose');
        const loading = document.getElementById('propModalLoading');
        const slides = document.getElementById('propGalSlides');
        const thumbs = document.getElementById('propThumbs');
        const dots = document.getElementById('propGalDots');
        const badge = document.getElementById('propGalBadge');
        const counter = document.getElementById('propGalCounter');
        const prevBtn = document.getElementById('propGalPrev');
        const nextBtn = document.getElementById('propGalNext');
        const bookBtn = document.getElementById('propModalBookBtn');

        // ── Open modal ─────────────────────────────────────────────
        window.openPropertyModal = async function(propId, propName, agentId) {
            modal.classList.add('open');
            document.body.style.overflow = 'hidden';
            loading.style.display = 'flex';

            try {
                // Try fetching from API first
                let prop = null;
                try {
                    prop = await DwelroAPI.getProperty(propId);
                } catch (e) {
                    // API not reachable — fall back to static data
                    prop = getStaticProperty(propId, propName, agentId);
                }

                if (!prop) {
                    prop = getStaticProperty(propId, propName, agentId);
                }

                currentProp = prop;
                renderModal(prop);

            } catch (err) {
                console.error('Property modal error:', err);
            } finally {
                loading.style.display = 'none';
            }
        };

        // ── Render ─────────────────────────────────────────────────
        function renderModal(p) {
            galIdx = 0;

            // Build image array from all sources
            galImages = [];
            if (p.cover_image_url) galImages.push(p.cover_image_url);
            if (p.images && p.images.length) {
                p.images.forEach(img => {
                    if (img.image_url && !galImages.includes(img.image_url)) {
                        galImages.push(img.image_url);
                    }
                });
            }
            if (p.image_urls) {
                const extra = Array.isArray(p.image_urls) ? p.image_urls : JSON.parse(p.image_urls || '[]');
                extra.forEach(u => { if (u && !galImages.includes(u)) galImages.push(u); });
            }
            if (!galImages.length) galImages.push('https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?w=1200&auto=format&fit=crop');

            // Gallery slides
            slides.innerHTML = galImages.map((url, i) => `
        <div class="prop-gallery-slide ${i === 0 ? 'active' : ''}">
          <img src="${url}" alt="Property image ${i+1}" loading="${i === 0 ? 'eager' : 'lazy'}" />
        </div>
      `).join('');

            // Thumbnails
            thumbs.innerHTML = galImages.map((url, i) => `
        <div class="prop-thumb ${i === 0 ? 'on' : ''}" onclick="propGalGoTo(${i})">
          <img src="${url}" alt="" loading="lazy" />
        </div>
      `).join('');
            thumbs.style.display = galImages.length > 1 ? 'flex' : 'none';

            // Dots
            dots.innerHTML = galImages.length > 1 ? galImages.map((_, i) =>
                `<div class="pgd ${i === 0 ? 'on' : ''}" onclick="propGalGoTo(${i})"></div>`
            ).join('') : '';

            // Counter & badge
            counter.textContent = galImages.length > 1 ? `1 / ${galImages.length}` : '';
            if (p.badge) {
                badge.textContent = p.badge;
                badge.style.display = '';
            } else badge.style.display = 'none';

            // Show/hide arrows
            prevBtn.style.display = galImages.length > 1 ? 'flex' : 'none';
            nextBtn.style.display = galImages.length > 1 ? 'flex' : 'none';

            // Title & price
            document.getElementById('propModalTitle').textContent = p.title || '';
            document.getElementById('propModalPrice').textContent = p.price_display || '';
            document.getElementById('propFooterPriceVal').textContent = p.price_display || '';

            // Location
            document.getElementById('propModalLocation').innerHTML = `
        <svg width="12" height="14" viewBox="0 0 10 12" fill="none">
          <path d="M5 1C3.067 1 1.5 2.567 1.5 4.5C1.5 7.5 5 11 5 11S8.5 7.5 8.5 4.5C8.5 2.567 6.933 1 5 1Z" stroke="#bbb" stroke-width="1"/>
          <circle cx="5" cy="4.5" r="1.2" stroke="#bbb" stroke-width="1"/>
        </svg>
        ${[p.address, p.area, p.district, p.country].filter(Boolean).join(', ')}
      `;

            // Stats bar
            const stats = [];
            if (p.bedrooms) stats.push({ icon: bedIcon(), val: p.bedrooms, label: 'Bedrooms' });
            if (p.bathrooms) stats.push({ icon: bathIcon(), val: p.bathrooms, label: 'Bathrooms' });
            if (p.floor_area_sqft) stats.push({ icon: areaIcon(), val: p.floor_area_sqft.toLocaleString() + ' sqft', label: 'Floor Area' });
            if (p.tenure) stats.push({ icon: keyIcon(), val: formatTenure(p.tenure), label: 'Tenure' });
            if (p.TOP_year) stats.push({ icon: calIcon(), val: p.TOP_year, label: 'TOP Year' });

            document.getElementById('propModalStats').innerHTML = stats.map(s => `
        <div class="pms-item">
          <div class="pms-icon">${s.icon}</div>
          <div class="pms-val">${s.val}</div>
          <div class="pms-label">${s.label}</div>
        </div>
      `).join('');
            document.getElementById('propModalStats').style.display = stats.length ? 'flex' : 'none';

            // Description
            document.getElementById('propModalDesc').textContent = p.description || '';

            // Features
            let features = [];
            try { features = Array.isArray(p.features) ? p.features : JSON.parse(p.features || '[]'); } catch (e) {}
            const featuresEl = document.getElementById('propModalFeatures');
            const featuresLabel = document.getElementById('propFeaturesLabel');
            if (features.length) {
                featuresEl.innerHTML = features.map(f => `<div class="pmf-tag">${f}</div>`).join('');
                featuresLabel.style.display = '';
            } else {
                featuresEl.innerHTML = '';
                featuresLabel.style.display = 'none';
            }

            // Details grid
            const details = [
                ['Category', formatCategory(p.category)],
                ['Listing Type', p.listing_type === 'rent' ? 'For Rent' : 'For Sale'],
                ['Status', formatStatus(p.status)],
                ['District', p.district],
                ['Floor Level', p.floor_level],
                ['Furnishing', p.furnishing ? p.furnishing.replace('_', ' ') : null],
                ['Land Area', p.land_area_sqft ? p.land_area_sqft.toLocaleString() + ' sqft' : null],
                ['Price PSF', p.price_psf ? '$' + Number(p.price_psf).toLocaleString() : null],
            ].filter(r => r[1]);

            document.getElementById('propModalDetails').innerHTML = details.map(([k, v]) => `
        <div class="pmd-row">
          <span class="pmd-key">${k}</span>
          <span class="pmd-val">${v}</span>
        </div>
      `).join('');

            // Map
            const mapEl = document.getElementById('propModalMap');
            if (p.latitude && p.longitude) {
                mapEl.src = `https://www.google.com/maps?q=${p.latitude},${p.longitude}&z=15&output=embed`;
                mapEl.style.display = 'block';
            } else if (p.address) {
                mapEl.src = `https://www.google.com/maps?q=${encodeURIComponent((p.address || '') + ' ' + (p.country || 'United Kingdom'))}&output=embed`;
                mapEl.style.display = 'block';
            } else {
                mapEl.style.display = 'none';
            }

            // Agent
            const agentEl = document.getElementById('propModalAgent');
            if (p.agent_name) {
                const waNum = (p.agent_whatsapp || p.agent_phone || '').replace(/\D/g, '');
                agentEl.innerHTML = `
          <img src="${p.agent_photo || 'https://images.unsplash.com/photo-1560250097-0b93528c311a?w=200&auto=format&fit=crop'}" alt="${p.agent_name}" />
          <div>
            <div class="pma-name">${p.agent_name}</div>
            <div class="pma-title">${p.agent_title || 'Property Consultant'} ${p.agent_cea ? '· CEA ' + p.agent_cea : ''}</div>
            <div class="pma-btns">
              ${waNum ? `<a href="https://wa.me/${waNum}?text=Hi! I'm interested in ${encodeURIComponent(p.title || 'your listing')}." class="pma-btn pma-wa" target="_blank">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                WhatsApp
              </a>` : ''}
              ${p.agent_phone ? `<a href="tel:${p.agent_phone}" class="pma-btn pma-call">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 9.81a19.79 19.79 0 01-3.07-8.68A2 2 0 012 .15h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.09 7.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 14.92z"/></svg>
                Call
              </a>` : ''}
            </div>
          </div>
        `;
        agentEl.style.display = 'flex';
      } else {
        agentEl.style.display = 'none';
      }
  
      // Book button
      bookBtn.onclick = function () {
        closePropertyModal();
        setTimeout(() => {
          if (window.openBooking) openBooking(p.title, p.id, p.agent_id);
        }, 300);
      };
  
      // Scroll content to top
      document.getElementById('propModalContent').scrollTop = 0;
    }
  
    // ── Gallery navigation ─────────────────────────────────────
    window.propGalGoTo = function (i) {
      const allSlides = slides.querySelectorAll('.prop-gallery-slide');
      const allDots   = dots.querySelectorAll('.pgd');
      const allThumbs = thumbs.querySelectorAll('.prop-thumb');
      if (!allSlides[i]) return;
  
      allSlides[galIdx].classList.remove('active');
      if (allDots[galIdx])   allDots[galIdx].classList.remove('on');
      if (allThumbs[galIdx]) allThumbs[galIdx].classList.remove('on');
  
      galIdx = i;
  
      allSlides[galIdx].classList.add('active');
      if (allDots[galIdx])   allDots[galIdx].classList.add('on');
      if (allThumbs[galIdx]) {
        allThumbs[galIdx].classList.add('on');
        allThumbs[galIdx].scrollIntoView({ behavior: 'smooth', inline: 'nearest', block: 'nearest' });
      }
      counter.textContent = galImages.length > 1 ? `${galIdx + 1} / ${galImages.length}` : '';
    };
  
    prevBtn.addEventListener('click', () => propGalGoTo((galIdx - 1 + galImages.length) % galImages.length));
    nextBtn.addEventListener('click', () => propGalGoTo((galIdx + 1) % galImages.length));
  
    // Keyboard navigation
    document.addEventListener('keydown', e => {
      if (!modal.classList.contains('open')) return;
      if (e.key === 'Escape')      closePropertyModal();
      if (e.key === 'ArrowRight')  propGalGoTo((galIdx + 1) % galImages.length);
      if (e.key === 'ArrowLeft')   propGalGoTo((galIdx - 1 + galImages.length) % galImages.length);
    });
  
    // ── Close ──────────────────────────────────────────────────
    function closePropertyModal() {
      modal.classList.remove('open');
      document.body.style.overflow = '';
      setTimeout(() => { currentProp = null; }, 400);
    }
    window.closePropertyModal = closePropertyModal;
  
    closeBtn.addEventListener('click', closePropertyModal);
    dim.addEventListener('click', closePropertyModal);
  
    // ── Static fallback data (when api.php not yet connected) ──
    const STATIC = {
      1: { id:1, title:'South Kensington Glass Tower', badge:'New Launch', category:'condo', listing_type:'sale', status:'available', district:'SW7', area:'South Kensington', address:'45 Cromwell Road, London SW7 2EA', country:'United Kingdom', latitude:51.4939, longitude:-0.1816, price_display:'From €4.8M', bedrooms:3, bathrooms:3, floor_area_sqft:1650, tenure:'freehold', description:'Panoramic park and city views from every unit. Full-facility luxury tower with infinity pool, spa, gym & concierge. Situated in prestigious South Kensington, residents enjoy seamless access to museums, fine dining and world-class living at its finest.', features:['Infinity Pool','Concierge Service','Spa & Wellness','Private Cinema','24hr Security','Park Views','Smart Home System','EV Charging'], cover_image_url:'https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?w=1200&auto=format&fit=crop', image_urls:['https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?w=1200&auto=format&fit=crop','https://images.unsplash.com/photo-1502672260266-1c1ef2d93688?w=1200&auto=format&fit=crop','https://images.unsplash.com/photo-1600210492486-724fe5c67fb0?w=1200&auto=format&fit=crop'], agent_name:'Charlotte Ashford', agent_title:'Luxury Property Specialist', agent_photo:'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=400&auto=format&fit=crop', agent_phone:'+441202555432', agent_whatsapp:'+441202555432', agent_cea:'RICS001234', agent_id:2 },
      2: { id:2, title:'Mayfair Georgian Town House', badge:null, category:'landed', listing_type:'sale', status:'available', district:'W1', area:'Mayfair', address:'12 Mount Street, London W1K 2QA', country:'United Kingdom', latitude:51.5093, longitude:-0.1508, price_display:'€28.5M', bedrooms:7, bathrooms:8, floor_area_sqft:14000, tenure:'freehold', description:"Sprawling 14,000 sqft Georgian masterpiece in Mayfair's most coveted address. Lush private garden, wine cellar, cinema room and separate guest wing. Elegant grand living at the heart of London's most prestigious district.", features:['Reception Rooms','Private Garden','Wine Cellar','Cinema Room','Guest Wing','4-Car Garage','Smart Security','Period Features'], cover_image_url:'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?w=1200&auto=format&fit=crop', image_urls:['https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?w=1200&auto=format&fit=crop','https://images.unsplash.com/photo-1583608205776-bfd35f0d9f83?w=1200&auto=format&fit=crop','https://images.unsplash.com/photo-1568605114967-8130f3a36994?w=1200&auto=format&fit=crop'], agent_name:'James Pembroke', agent_title:'Senior Property Consultant', agent_photo:'https://images.unsplash.com/photo-1560250097-0b93528c311a?w=400&auto=format&fit=crop', agent_phone:'+441202555567', agent_whatsapp:'+441202555567', agent_cea:'RICS005678', agent_id:1 },
      3: { id:3, title:'Provence Luxury Estate', badge:'Hot Pick', category:'overseas', listing_type:'sale', status:'available', district:null, area:'Luberon Valley', address:'Chemin de la Garaye, 84400 Lourmarin, France', country:'France', latitude:43.9215, longitude:5.8210, price_display:'€2.4M', bedrooms:5, bathrooms:5, floor_area_sqft:5200, tenure:'freehold', description:"Refurbished 18th century manor with contemporary luxury. Private infinity pool overlooking lavender fields, wine cellar, and guest house. Positioned in the heart of Provence — France's most sought-after lifestyle destination. Ideal for owner-occupiers and investment alike.", features:['Infinity Pool','Heated Spa','Wine Cellar','Guest House','Helipad','Vineyard Access','High Rental Yield','Luxury Finishes'], cover_image_url:'https://images.unsplash.com/photo-1512917774080-9991f1c4c750?w=1200&auto=format&fit=crop', image_urls:['https://images.unsplash.com/photo-1512917774080-9991f1c4c750?w=1200&auto=format&fit=crop','https://images.unsplash.com/photo-1582268611958-ebfd161ef9cf?w=1200&auto=format&fit=crop'], agent_name:'Laurent Dubois', agent_title:'Senior Property Consultant', agent_photo:'https://images.unsplash.com/photo-1560250097-0b93528c311a?w=400&auto=format&fit=crop', agent_phone:'+33677884455', agent_whatsapp:'+33677884455', agent_cea:'FNAIM001', agent_id:1 },
      4: { id:4, title:'Amsterdam Canal Palace', badge:null, category:'condo', listing_type:'sale', status:'available', district:'Canal Ring', area:'Grachtengordel', address:'Blk 567 Prinsengracht 234, Amsterdam 1015 JR', country:'Netherlands', latitude:52.3738, longitude:4.8829, price_display:'€1.95M', bedrooms:5, bathrooms:4, floor_area_sqft:2150, tenure:'freehold', description:'Stunning 17th century canal palace renovation with modern luxury. High-ceilinged rooms, original parquet, heated garden, and private boat access. Walking distance to galleries, museums and Amsterdam's finest dining district.', features:['Canal Views','Period Parquet','Heated Garden','Boat Access','High Ceilings','Luxury Restoration','Modern Amenities','Museum District'], cover_image_url:'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?w=1200&auto=format&fit=crop', image_urls:['https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?w=1200&auto=format&fit=crop'], agent_name:'Sophia van der Berg', agent_title:'Luxury Canal Specialist', agent_photo:'https://images.unsplash.com/photo-1580489944761-15a19d654956?w=400&auto=format&fit=crop', agent_phone:'+31205559876', agent_whatsapp:'+31205559876', agent_cea:'NVM001234', agent_id:4 },
      5: { id:5, title:'Paris 8th Arrondissement Penthouse', badge:null, category:'condo', listing_type:'sale', status:'available', district:'8e', area:'Champs-Élysées', address:'25 Avenue Montaigne, Paris 75008', country:'France', latitude:48.8707, longitude:2.3057, price_display:'€18.9M', bedrooms:6, bathrooms:6, floor_area_sqft:6200, tenure:'freehold', description:'Exquisite 6-bedroom penthouse with private rooftop gardens and plunge pool. The pinnacle of Parisian luxury — offering sweeping Eiffel Tower and Seine Valley views, contemporary design with period architectural details throughout.', features:['Eiffel Tower Views','Private Rooftop','Plunge Pool','6 Bedrooms','Wine Cellar','Private Lift','Italian Marble','Parquet Flooring','Freehold'], cover_image_url:'https://images.unsplash.com/photo-1502672260266-1c1ef2d93688?w=1200&auto=format&fit=crop', image_urls:['https://images.unsplash.com/photo-1502672260266-1c1ef2d93688?w=1200&auto=format&fit=crop','https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?w=1200&auto=format&fit=crop'], agent_name:'Margot Leclerc', agent_title:'Luxury Property Specialist', agent_photo:'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=400&auto=format&fit=crop', agent_phone:'+33144559898', agent_whatsapp:'+33144559898', agent_cea:'API001122', agent_id:2 },
      6: { id:6, title:'Berlin TechPark Office Suite', badge:'As-Is Deal', category:'commercial', listing_type:'sale', status:'available', district:'Tiergarten', area:'Government District', address:'Rahstätter Strasse 45, Berlin 10557', country:'Germany', latitude:52.5217, longitude:13.3814, price_display:'€5.2M', bedrooms:null, bathrooms:null, floor_area_sqft:4800, tenure:'freehold', description:'5,000 sqft of premium Grade A office space in Berlin\'s burgeoning tech hub. Ideal for tech headquarters or investment. Features open floor plans, full IT infrastructure fit-out, and panoramic city views overlooking the Tiergarten. S-Bahn connectivity.', features:['Fitted Office Ready','Open Floor Plans','Full IT Infrastructure','City Views','S-Bahn Connected','24hr Building Access','Concierge Service','Green Building Cert','Strata Title'], cover_image_url:'https://images.unsplash.com/photo-1497366216548-37526070297c?w=1200&auto=format&fit=crop', image_urls:['https://images.unsplash.com/photo-1497366216548-37526070297c?w=1200&auto=format&fit=crop'], agent_name:'Klaus Mueller', agent_title:'Commercial & Investment Lead', agent_photo:'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=400&auto=format&fit=crop', agent_phone:'+493058889900', agent_whatsapp:'+493058889900', agent_cea:'IVD001233', agent_id:3 },
    };
  
    function getStaticProperty(id, name) {
      return STATIC[id] || { id, title: name || 'Property', price_display: '', description: '', cover_image_url: 'https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?w=1200&auto=format&fit=crop', image_urls: [], features: [] };
    }
  
    // ── Icon helpers ───────────────────────────────────────────
    function bedIcon()  { return `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1a3a5c" stroke-width="1.5"><path d="M3 9V19M21 9V19M3 14H21M3 9C3 9 5 7 12 7C19 7 21 9 21 9"/><path d="M7 14V11C7 10.4477 7.44772 10 8 10H10C10.5523 10 11 10.4477 11 11V14"/></svg>`; }
    function bathIcon() { return `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1a3a5c" stroke-width="1.5"><path d="M4 12H20V16C20 18.2091 18.2091 20 16 20H8C5.79086 20 4 18.2091 4 16V12Z"/><path d="M4 12V7C4 5.89543 4.89543 5 6 5H8C9.10457 5 10 5.89543 10 7V12"/></svg>`; }
    function areaIcon() { return `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1a3a5c" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="1"/><path d="M3 9H21M9 3V21"/></svg>`; }
    function keyIcon()  { return `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1a3a5c" stroke-width="1.5"><circle cx="8" cy="12" r="4"/><path d="M12 12H21M18 9V15"/></svg>`; }
    function calIcon()  { return `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1a3a5c" stroke-width="1.5"><rect x="3" y="4" width="18" height="17" rx="1"/><path d="M3 9H21M8 2V4M16 2V4"/></svg>`; }
  
    function formatTenure(t) {
      const map = { freehold:'Freehold', '99_year':'99-Year', '999_year':'999-Year', leasehold:'Leasehold', other:'Other' };
      return map[t] || t;
    }
    function formatCategory(c) {
      const map = { condo:'Condominium', landed:'Landed', hdb:'HDB', commercial:'Commercial', overseas:'Overseas', new_dev:'New Development', investment:'Investment' };
      return map[c] || c;
    }
    function formatStatus(s) {
      const map = { available:'Available', sold:'Sold', rented:'Rented', under_offer:'Under Offer' };
      return map[s] || s;
    }
  
  })();