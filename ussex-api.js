/**
 * ============================================================
 *  DWELRO — API Client  (dwelro-api.js)
 *  Include this script in every HTML page:
 *    <script src="dwelro-api.js"></script>
 * ============================================================
 */

const DwelroAPI = (() => {

    const BASE = 'api.php';
    const TIMEOUT = 8000;

    async function request(url) {
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), TIMEOUT);
        try {
            const res = await fetch(url, { signal: controller.signal });
            if (!res.ok) {
                const err = await res.json().catch(() => ({}));
                throw new Error(err.error || `HTTP ${res.status}`);
            }
            return await res.json();
        } finally {
            clearTimeout(timer);
        }
    }

    async function postRequest(action, body = {}) {
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), TIMEOUT);
        try {
            const res = await fetch(`${BASE}?action=${action}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body),
                signal: controller.signal,
            });
            return await res.json();
        } finally {
            clearTimeout(timer);
        }
    }

    function qs(params = {}) {
        const parts = Object.entries(params)
            .filter(([, v]) => v !== null && v !== undefined && v !== '')
            .map(([k, v]) => `${encodeURIComponent(k)}=${encodeURIComponent(v)}`);
        return parts.length ? '?' + parts.join('&') : '';
    }

    async function getProperties(filters = {}) {
        const params = { action: 'properties', ...filters };
        return request(BASE + qs(params));
    }

    async function getProperty(id) {
        return request(`${BASE}?action=properties&id=${id}`);
    }

    async function getAgents() {
        return request(`${BASE}?action=agents`);
    }

    async function getAgent(id) {
        return request(`${BASE}?action=agents&id=${id}`);
    }

    async function getTestimonials(panel = null) {
        const params = { action: 'testimonials' };
        if (panel) params.panel = panel;
        return request(BASE + qs(params));
    }

    async function submitEnquiry() {
        const modal = document.getElementById('bookingModal');
        if (!modal) return;

        const inputs = [...modal.querySelectorAll('input, select')];
        const byName = {};
        inputs.forEach(inp => {
            const label = inp.closest('.modal-form-group')?.querySelector('label')?.textContent?.trim().toLowerCase() || '';
            if (label.includes('first')) byName.first_name = inp;
            else if (label.includes('last')) byName.last_name = inp;
            else if (label.includes('phone') || label.includes('whatsapp')) byName.phone = inp;
            else if (label.includes('email')) byName.email = inp;
            else if (label.includes('date')) byName.preferred_date = inp;
            else if (label.includes('time') || label.includes('lease')) byName.preferred_time = inp;
        });

        const first = byName.first_name?.value?.trim();
        const last = byName.last_name?.value?.trim();
        const phone = byName.phone?.value?.trim();
        const email = byName.email?.value?.trim();

        if (!first || !last || !phone || !email) {
            alert('Please fill in your name, phone and email.');
            return;
        }

        const propId = modal.dataset.propertyId || null;
        const agentId = modal.dataset.agentId || null;

        const btn = modal.querySelector('.modal-submit');
        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Submitting…';
        }

        const result = await postRequest('enquiry', {
            property_id: propId,
            agent_id: agentId,
            first_name: first,
            last_name: last,
            email,
            phone,
            preferred_date: byName.preferred_date?.value || null,
            preferred_time: byName.preferred_time?.value || null,
            enquiry_type: 'book_viewing',
            source: 'website',
        });

        if (result.success) {
            modal.querySelector('.modal-box').innerHTML = `
                <div style="text-align:center;padding:40px 0;">
                    <div style="width:56px;height:56px;background:#f0f7f0;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M5 12l5 5L20 7" stroke="#25a244" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </div>
                    <h3 style="font-family:'DM Serif Display',serif;font-size:22px;font-weight:400;color:#171717;margin-bottom:8px;">Viewing Requested!</h3>
                    <p style="font-size:14px;color:#999;line-height:1.6;">Our agent will WhatsApp you within 2 hours to confirm your slot.</p>
                    <button onclick="closeBooking()" style="margin-top:24px;padding:11px 32px;background:#1a3a5c;color:#fff;border:none;font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;cursor:pointer;border-radius:2px;">Done</button>
                </div>`;
        } else {
            alert(result.error || 'Something went wrong. Please try again.');
            if (btn) {
                btn.disabled = false;
                btn.textContent = 'Confirm Viewing Request';
            }
        }
    }

    function initNewsletter() {
        const forms = document.querySelectorAll('.footer-newsletter-form');
        forms.forEach(form => {
            const btn = form.querySelector('button');
            const input = form.querySelector('input[type=email]');
            if (!btn || !input) return;

            btn.addEventListener('click', async() => {
                const email = input.value.trim();
                if (!email) { input.focus(); return; }

                btn.disabled = true;
                btn.textContent = 'Subscribing…';

                const result = await postRequest('newsletter', { email });
                btn.disabled = false;

                if (result.success) {
                    input.value = '';
                    btn.textContent = '✓ Subscribed!';
                    btn.style.background = '#25a244';
                    setTimeout(() => {
                        btn.textContent = 'Subscribe';
                        btn.style.background = '';
                    }, 3000);
                } else {
                    btn.textContent = 'Try again';
                    setTimeout(() => { btn.textContent = 'Subscribe'; }, 2000);
                }
            });
        });
    }

    function esc(str) {
        if (str == null) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function categoryLabel(cat) {
        const map = {
            condo: 'Condo',
            landed: 'Landed',
            hdb: 'HDB',
            commercial: 'Commercial',
            overseas: 'Overseas',
            new_dev: 'New Dev',
            investment: 'Investment',
        };
        return map[cat] || cat;
    }

    function formatVolume(sgd) {
        if (!sgd) return 'N/A';
        const n = Number(sgd);
        if (n >= 1e9) return '$' + (n / 1e9).toFixed(1).replace(/\.0$/, '') + 'B';
        if (n >= 1e6) return '$' + (n / 1e6).toFixed(0) + 'M';
        return '$' + n.toLocaleString();
    }

    function buildPropertyCard(p) {
        const badge = p.badge ? `<div class="prop-badge">${esc(p.badge)}</div>` : '';
        const img = p.cover_image_url || 'https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?w=800&auto=format&fit=crop';
        const tagLabel = categoryLabel(p.category);

        return `
<div class="prop-card" data-cat="${esc(p.category)}" data-name="${esc(p.title)}" data-loc="${esc(p.area)}">
    <div class="prop-img-wrap">
        ${badge}
        <img src="${esc(img)}" alt="${esc(p.title)}" loading="lazy" />
        <div class="prop-overlay">
            <div class="prop-overlay-actions">
                <button class="prop-overlay-btn prop-btn-view" onclick="location.href='property.php?id=${p.id}'">View →</button>
                <button class="prop-overlay-btn prop-btn-book" onclick="openBooking('${esc(p.title)}', ${p.id}, ${p.agent_id || 'null'})">Book Viewing</button>
            </div>
        </div>
    </div>
    <div class="prop-body">
        <div class="prop-location">
            <svg width="10" height="12" viewBox="0 0 10 12" fill="none"><path d="M5 1C3.067 1 1.5 2.567 1.5 4.5C1.5 7.5 5 11 5 11S8.5 7.5 8.5 4.5C8.5 2.567 6.933 1 5 1Z" stroke="#bbb" stroke-width="1"/><circle cx="5" cy="4.5" r="1.2" stroke="#bbb" stroke-width="1"/></svg>
            ${esc(p.area)}${p.district ? ', ' + esc(p.district) : ''}
        </div>
        <div class="prop-name">${esc(p.title)}</div>
        <div class="prop-price-row">
            <span class="prop-price">${esc(p.price_display)}</span>
            <span class="prop-tag">${tagLabel}</span>
        </div>
    </div>
</div>`.trim();
    }

    function buildAgentCard(a) {
        const vol = formatVolume(a.portfolio_sgd);
        return `
<div class="agent-card">
    <div class="agent-photo-wrap">
        <img src="${esc(a.photo_url || 'https://images.unsplash.com/photo-1560250097-0b93528c311a?w=400')}" alt="${esc(a.full_name)}" />
        <div class="agent-overlay">
            <a href="https://wa.me/${esc(a.whatsapp || a.phone).replace(/\D/g, '')}" class="agent-overlay-btn agent-btn-wa" target="_blank">WhatsApp</a>
            <a href="agents.php?id=${a.id}" class="agent-overlay-btn agent-btn-profile">Profile</a>
        </div>
    </div>
    <div class="agent-name">${esc(a.full_name)}</div>
    <div class="agent-title">${esc(a.title)}</div>
    <div class="agent-stats">
        <div class="agent-stat"><div class="agent-stat-num">${a.deals_closed}</div><div class="agent-stat-label">Deals</div></div>
        <div class="agent-stat"><div class="agent-stat-num">${vol}</div><div class="agent-stat-label">Volume</div></div>
        <div class="agent-stat"><div class="agent-stat-num">${a.years_exp}yr</div><div class="agent-stat-label">Exp.</div></div>
    </div>
</div>`.trim();
    }

    async function renderPropertyGrid(gridId = 'propertyGrid', filters = {}) {
        const grid = document.getElementById(gridId);
        if (!grid) return;

        grid.innerHTML = `<div style="grid-column:1/-1;text-align:center;padding:60px 0;color:#bbb;">Loading listings…</div>`;

        try {
            const props = await getProperties(filters);

            if (!props.length) {
                grid.innerHTML = `<p style="grid-column:1/-1;text-align:center;color:#aaa;padding:60px 0;">No properties found.</p>`;
                return;
            }

            grid.innerHTML = props.map(p => buildPropertyCard(p)).join('');
            grid.querySelectorAll('.prop-card').forEach((card, i) => {
                setTimeout(() => card.classList.add('visible'), i * 90);
            });

            const counter = document.getElementById('countNum');
            if (counter) counter.textContent = props.length;

        } catch (err) {
            console.error('DwelroAPI.renderPropertyGrid:', err);
            grid.innerHTML = `<p style="grid-column:1/-1;text-align:center;color:#aaa;padding:60px 0;">Could not load listings. Please refresh.</p>`;
        }
    }

    async function renderAgentsGrid(gridId = 'agentsGrid') {
        const grid = document.getElementById(gridId);
        if (!grid) return;

        try {
            const agents = await getAgents();
            grid.innerHTML = agents.map(a => buildAgentCard(a)).join('');
            grid.querySelectorAll('.agent-card').forEach((card, i) => {
                setTimeout(() => card.classList.add('revealed'), i * 120);
            });
        } catch (err) {
            console.error('DwelroAPI.renderAgentsGrid:', err);
        }
    }

    function patchOpenBooking() {
        const _orig = window.openBooking;
        window.openBooking = function(propName, propId, agentId) {
            if (_orig) _orig(propName, propId, agentId);

            const modal = document.getElementById('bookingModal');
            if (!modal) return;
            if (propId) modal.dataset.propertyId = propId;
            if (agentId) modal.dataset.agentId = agentId;

            const tag = document.getElementById('modalPropertyTag');
            if (tag && propName) tag.textContent = propName;

            modal.classList.add('open');
            document.body.style.overflow = 'hidden';
        };
    }

    async function init() {
        const page = window.location.pathname
            .split('/').pop()
            .replace('.html', '')
            .replace('.php', '') || 'index';

        initNewsletter();
        patchOpenBooking();

        if (page === 'index' || page === '') {
            await Promise.all([
                renderPropertyGrid('propertyGrid', { featured: 1 }),
                renderAgentsGrid('agentsGrid'),
            ]);
        } else if (page === 'buy') {
            await renderPropertyGrid('propertyGrid', { listing_type: 'sale' });
        } else if (page === 'agents') {
            await renderAgentsGrid('agentsGrid');
        }

        document.querySelectorAll('.modal-submit').forEach(btn => {
            btn.addEventListener('click', () => DwelroAPI.submitEnquiry());
        });
    }

    return {
        init,
        getProperties,
        getProperty,
        getAgents,
        getAgent,
        getTestimonials,
        submitEnquiry,
        initNewsletter,
        renderPropertyGrid,
        renderAgentsGrid,
        buildPropertyCard,
        buildAgentCard,
    };

})();

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', DwelroAPI.init);
} else {
    DwelroAPI.init();
}
