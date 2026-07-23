/**
 * ============================================================
 *  DWELRO — API Client  (dwelro-api.js)
 *  Include BEFORE property-modal.js on every page
 *
 *  Usage:
 *    DwelroAPI.getListings({ city:'New York', type:'sale', page:1 })
 *    DwelroAPI.getProperty(5)
 *    DwelroAPI.submitInquiry({ name, email, property_id, ... })
 * ============================================================
 */

const DwelroAPI = (() => {

  // ── Config — update BASE_URL to your server path ────────────
  const BASE_URL = '/api/api.php';   // e.g. 'https://yourdomain.com/api/api.php'
  const TIMEOUT  = 8000;             // ms before request is abandoned

  // ── Internal fetch wrapper ───────────────────────────────────
  async function request(url, options = {}) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), TIMEOUT);
    try {
      const res = await fetch(url, { ...options, signal: controller.signal });
      if (!res.ok) {
        const err = await res.json().catch(() => ({}));
        throw new Error(err.error || `HTTP ${res.status}`);
      }
      return await res.json();
    } finally {
      clearTimeout(timer);
    }
  }

  // ── Build query string from a params object ──────────────────
  function qs(params = {}) {
    const p = Object.entries(params)
      .filter(([, v]) => v !== null && v !== undefined && v !== '')
      .map(([k, v]) => `${encodeURIComponent(k)}=${encodeURIComponent(v)}`);
    return p.length ? '?' + p.join('&') : '';
  }

  // ── Public API ───────────────────────────────────────────────

  /**
   * Get listings list with optional filters.
   *
   * Supported params:
   *   status       — active | pending | sold | rented
   *   type         — sale | rent
   *   category     — single_family | condo | townhouse | multi_family | land | commercial | rental | other
   *   city, state, zip
   *   agent_id
   *   beds_min, beds_max
   *   baths_min
   *   price_min, price_max
   *   sqft_min
   *   pool, waterfront, new_construction  (1 to filter)
   *   q            — full-text search string
   *   sort         — price_asc | price_desc | beds_desc | sqft_desc | newest | oldest
   *   page, per_page
   */
  async function getListings(params = {}) {
    return request(BASE_URL + qs(params));
  }

  /**
   * Get a single property by ID.
   * Returns the full property object including agent info, images, schools, open houses.
   */
  async function getProperty(id) {
    return request(`${BASE_URL}?id=${id}`);
  }

  /**
   * Submit a viewing inquiry / booking request.
   *
   * Required: name
   * Optional: email, phone, message, property_id, agent_id, viewing_date, viewing_time, source
   */
  async function submitInquiry(data) {
    return request(`${BASE_URL}?action=inquiry`, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify(data),
    });
  }

  /**
   * Create a new listing. Requires API key header.
   * Send X-API-Key: your_secret_api_key in headers (admin only).
   */
  async function createListing(data, apiKey) {
    return request(`${BASE_URL}?action=listing`, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json', 'X-API-Key': apiKey },
      body:    JSON.stringify(data),
    });
  }

  /**
   * Update a listing by ID. Requires API key.
   */
  async function updateListing(id, data, apiKey) {
    return request(`${BASE_URL}?action=listing&id=${id}`, {
      method:  'PUT',
      headers: { 'Content-Type': 'application/json', 'X-API-Key': apiKey },
      body:    JSON.stringify(data),
    });
  }

  /**
   * Soft-delete (mark off_market) a listing. Requires API key.
   */
  async function deleteListing(id, apiKey) {
    return request(`${BASE_URL}?action=listing&id=${id}`, {
      method:  'DELETE',
      headers: { 'X-API-Key': apiKey },
    });
  }

  return { getListings, getProperty, submitInquiry, createListing, updateListing, deleteListing };

})();