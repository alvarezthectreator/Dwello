/**
 * ============================================================
 *  DWELRO — Error Logger (error-logger.js)
 *  Include this as the FIRST script on every page:
 *    <script src="error-logger.js"></script>
 *
 *  Shows a floating error panel in the bottom-right corner.
 *  Only visible when errors occur.
 *  Press Ctrl+Shift+E to toggle the panel open/closed.
 * ============================================================
 */

(function() {

        const logs = [];
        let panel = null;
        let badge = null;

        // ── Styles ────────────────────────────────────────────────
        const style = document.createElement('style');
        style.textContent = `
        #dw-error-badge {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 99999;
            background: #c0392b;
            color: #fff;
            font-family: monospace;
            font-size: 12px;
            font-weight: 700;
            padding: 6px 12px;
            border-radius: 20px;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0,0,0,.3);
            display: none;
            user-select: none;
        }
        #dw-error-badge:hover { background: #a93226; }

        #dw-error-panel {
            position: fixed;
            bottom: 60px;
            right: 20px;
            z-index: 99998;
            width: 480px;
            max-width: calc(100vw - 40px);
            max-height: 420px;
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 6px;
            box-shadow: 0 8px 32px rgba(0,0,0,.5);
            display: none;
            flex-direction: column;
            font-family: monospace;
            font-size: 12px;
            overflow: hidden;
        }
        #dw-error-panel.open { display: flex; }

        #dw-error-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 14px;
            background: #111;
            border-bottom: 1px solid #333;
            color: #fff;
            font-weight: 700;
            letter-spacing: .5px;
            font-size: 11px;
            text-transform: uppercase;
        }
        #dw-error-header-btns {
            display: flex;
            gap: 8px;
        }
        #dw-error-clear {
            background: none;
            border: 1px solid #444;
            color: #aaa;
            font-family: monospace;
            font-size: 10px;
            padding: 3px 8px;
            cursor: pointer;
            border-radius: 3px;
        }
        #dw-error-clear:hover { border-color: #c0392b; color: #c0392b; }
        #dw-error-close {
            background: none;
            border: none;
            color: #aaa;
            font-size: 16px;
            cursor: pointer;
            line-height: 1;
            padding: 0 2px;
        }
        #dw-error-close:hover { color: #fff; }

        #dw-error-list {
            overflow-y: auto;
            flex: 1;
            padding: 8px 0;
        }
        .dw-log-entry {
            padding: 8px 14px;
            border-bottom: 1px solid #2a2a2a;
            line-height: 1.5;
        }
        .dw-log-entry:last-child { border-bottom: none; }
        .dw-log-time  { color: #666; font-size: 10px; margin-bottom: 3px; }
        .dw-log-type  { font-weight: 700; margin-right: 6px; }
        .dw-log-type.error   { color: #e74c3c; }
        .dw-log-type.warn    { color: #f39c12; }
        .dw-log-type.network { color: #9b59b6; }
        .dw-log-type.info    { color: #3498db; }
        .dw-log-msg   { color: #ddd; word-break: break-all; }
        .dw-log-stack { color: #666; font-size: 10px; margin-top: 4px; white-space: pre-wrap; word-break: break-all; }

        #dw-error-footer {
            padding: 8px 14px;
            background: #111;
            border-top: 1px solid #333;
            color: #555;
            font-size: 10px;
        }
    `;
        document.head.appendChild(style);

        // ── Build panel ───────────────────────────────────────────
        function buildPanel() {
            badge = document.createElement('div');
            badge.id = 'dw-error-badge';
            badge.textContent = '0 errors';
            badge.addEventListener('click', togglePanel);
            document.body.appendChild(badge);

            panel = document.createElement('div');
            panel.id = 'dw-error-panel';
            panel.innerHTML = `
            <div id="dw-error-header">
                <span>🪲 Dwelro Error Log</span>
                <div id="dw-error-header-btns">
                    <button id="dw-error-clear">Clear</button>
                    <button id="dw-error-close">✕</button>
                </div>
            </div>
            <div id="dw-error-list"></div>
            <div id="dw-error-footer">Ctrl+Shift+E to toggle · errors auto-captured</div>
        `;
            document.body.appendChild(panel);

            document.getElementById('dw-error-close').addEventListener('click', () => panel.classList.remove('open'));
            document.getElementById('dw-error-clear').addEventListener('click', clearLogs);
        }

        // ── Toggle panel ──────────────────────────────────────────
        function togglePanel() {
            if (!panel) return;
            panel.classList.toggle('open');
        }

        // ── Add a log entry ───────────────────────────────────────
        function addLog(type, message, stack) {
            const now = new Date();
            const time = now.toLocaleTimeString() + '.' + String(now.getMilliseconds()).padStart(3, '0');

            logs.push({ type, message, stack, time });

            // Update badge
            const errorCount = logs.filter(l => l.type === 'error' || l.type === 'network').length;
            if (badge) {
                badge.textContent = `${errorCount} error${errorCount !== 1 ? 's' : ''}`;
                badge.style.display = 'block';
            }

            // Add to list
            const list = document.getElementById('dw-error-list');
            if (!list) return;

            const entry = document.createElement('div');
            entry.className = 'dw-log-entry';
            entry.innerHTML = `
            <div class="dw-log-time">${time}</div>
            <div>
                <span class="dw-log-type ${type}">${type.toUpperCase()}</span>
                <span class="dw-log-msg">${escHtml(message)}</span>
            </div>
            ${stack ? `<div class="dw-log-stack">${escHtml(stack)}</div>` : ''}
        `;
        list.appendChild(entry);
        list.scrollTop = list.scrollHeight;

        // Auto-open panel on first error
        if (panel && errorCount === 1) {
            panel.classList.add('open');
        }
    }

    // ── Clear logs ────────────────────────────────────────────
    function clearLogs() {
        logs.length = 0;
        const list = document.getElementById('dw-error-list');
        if (list) list.innerHTML = '';
        if (badge) badge.style.display = 'none';
    }

    // ── Escape HTML ───────────────────────────────────────────
    function escHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    // ── Intercept window errors ───────────────────────────────
    window.addEventListener('error', function (e) {
        const msg   = e.message || 'Unknown error';
        const loc   = `${e.filename || ''}:${e.lineno || ''}:${e.colno || ''}`;
        const stack = e.error ? e.error.stack : loc;
        addLog('error', msg, stack);
    });

    // ── Intercept unhandled promise rejections ────────────────
    window.addEventListener('unhandledrejection', function (e) {
        const msg   = e.reason?.message || String(e.reason) || 'Unhandled promise rejection';
        const stack = e.reason?.stack || '';
        addLog('error', msg, stack);
    });

    // ── Intercept console.error and console.warn ──────────────
    const _error = console.error.bind(console);
    const _warn  = console.warn.bind(console);

    console.error = function (...args) {
        addLog('error', args.map(String).join(' '));
        _error(...args);
    };
    console.warn = function (...args) {
        addLog('warn', args.map(String).join(' '));
        _warn(...args);
    };

    // ── Intercept fetch for network errors ────────────────────
    const _fetch = window.fetch;
    window.fetch = async function (...args) {
        const url = typeof args[0] === 'string' ? args[0] : args[0]?.url || '';
        try {
            const res = await _fetch(...args);
            if (!res.ok) {
                addLog('network', `${res.status} ${res.statusText} — ${url}`);
            }
            return res;
        } catch (err) {
            addLog('network', `Fetch failed — ${url} — ${err.message}`);
            throw err;
        }
    };

    // ── Keyboard shortcut Ctrl+Shift+E ────────────────────────
    document.addEventListener('keydown', function (e) {
        if (e.ctrlKey && e.shiftKey && e.key === 'E') {
            togglePanel();
        }
    });

    // ── Init after DOM ready ──────────────────────────────────
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', buildPanel);
    } else {
        buildPanel();
    }

    // ── Expose globally for manual logging ────────────────────
    window.DwelroLogger = { log: addLog, clear: clearLogs };

})();