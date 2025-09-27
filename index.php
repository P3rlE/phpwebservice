<?php
// Lightweight Ladder API for simple webspace deployments.
// Store match payloads as JSON files under data/.

declare(strict_types=1);

const DATA_DIR = __DIR__ . '/data';

if (!is_dir(DATA_DIR)) {
    if (!mkdir(DATA_DIR, 0775, true) && !is_dir(DATA_DIR)) {
        send_error(500, 'Failed to create data directory.');
    }
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// --- Minimal frontend for Q3Rally Ladder (HTML landing page) ---
try {
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $uri    = $_SERVER['REQUEST_URI'] ?? '/';
    $script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    $path   = parse_url($uri, PHP_URL_PATH) ?? '/';
    $path   = rtrim($path, '/');
    $isRoot = ($path === '' || $path === '/' || $path === $script);

    if ($method === 'GET' && ($isRoot) && (strpos($accept, 'application/json') === false)) {
        $apiBase = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
        header('Content-Type: text/html; charset=UTF-8');
        ?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Q3Rally Ladder Monitor</title>
  <meta name="description" content="Frontend zur Auswertung der gespeicherten Q3Rally-Ladder-Matches.">
  <style>
    :root {
      color-scheme: dark;
      --bg: radial-gradient(1200px 800px at 50% 0%, rgba(255,255,255,0.08), #05050c 60%);
      --surface: rgba(18, 21, 33, 0.72);
      --surface-strong: rgba(28, 31, 46, 0.92);
      --border: rgba(255, 255, 255, 0.12);
      --border-strong: rgba(255, 255, 255, 0.2);
      --text: #F5F6FF;
      --text-muted: #B7BCD6;
      --accent: #5D8BFF;
      --accent-soft: rgba(93, 139, 255, 0.18);
      font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Ubuntu, Cantarell, sans-serif;
    }

    body {
      margin: 0;
      min-height: 100vh;
      background: var(--bg);
      color: var(--text);
      display: flex;
      justify-content: center;
      padding: 32px 18px 48px;
    }

    .page {
      width: min(1100px, 100%);
      display: flex;
      flex-direction: column;
      gap: 28px;
    }

    .panel {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 24px;
      padding: 26px;
      box-shadow: 0 22px 60px rgba(0, 0, 0, 0.45), inset 0 1px 0 rgba(255, 255, 255, 0.04);
      backdrop-filter: blur(18px) saturate(120%);
      -webkit-backdrop-filter: blur(18px) saturate(120%);
    }

    .hero {
      display: grid;
      grid-template-columns: auto 1fr;
      gap: 22px;
      align-items: center;
    }

    .hero img {
      width: 78px;
      height: 78px;
      object-fit: contain;
      border-radius: 18px;
      border: 1px solid var(--border-strong);
      padding: 10px;
      background: rgba(255, 255, 255, 0.04);
    }

    .hero h1 {
      margin: 0 0 6px;
      font-size: clamp(1.7rem, 2.4vw, 2.15rem);
      letter-spacing: 0.01em;
    }

    .hero p {
      margin: 0;
      color: var(--text-muted);
      line-height: 1.6;
    }

    .hero dl {
      margin: 0;
      display: grid;
      gap: 6px 18px;
      grid-template-columns: repeat(2, auto);
      justify-content: end;
      text-align: right;
      font-size: 0.95rem;
    }

    .hero dt {
      color: var(--text-muted);
      font-weight: 500;
    }

    .hero dd {
      margin: 0;
      font-weight: 600;
    }

    .controls {
      display: grid;
      gap: 18px;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      align-items: end;
    }

    label {
      display: block;
      font-size: 0.85rem;
      letter-spacing: 0.02em;
      margin-bottom: 8px;
      color: var(--text-muted);
      text-transform: uppercase;
    }

    select,
    input[type="search"],
    button {
      width: 100%;
      padding: 12px 14px;
      border-radius: 14px;
      border: 1px solid var(--border);
      background: rgba(8, 12, 24, 0.4);
      color: inherit;
      font: inherit;
    }

    select:focus,
    input[type="search"]:focus,
    button:focus {
      outline: 2px solid var(--accent);
      outline-offset: 2px;
    }

    button {
      cursor: pointer;
      background: linear-gradient(135deg, var(--accent), rgba(125, 167, 255, 0.9));
      border: 1px solid rgba(93, 139, 255, 0.5);
      font-weight: 600;
      transition: transform 120ms ease, box-shadow 120ms ease;
    }

    button:hover {
      transform: translateY(-1px);
      box-shadow: 0 14px 32px rgba(93, 139, 255, 0.25);
    }

    .status {
      margin-top: 4px;
      font-size: 0.9rem;
      color: var(--text-muted);
    }

    .status.error {
      color: #ffb7b7;
    }

    .stats-grid {
      display: grid;
      gap: 18px;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    }

    .stat-card {
      background: var(--surface-strong);
      border-radius: 22px;
      padding: 20px 22px;
      border: 1px solid rgba(255, 255, 255, 0.08);
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    .stat-card h2 {
      margin: 0;
      font-size: 0.8rem;
      text-transform: uppercase;
      color: var(--text-muted);
      letter-spacing: 0.12em;
    }

    .stat-card p {
      margin: 0;
      font-size: clamp(1.4rem, 2vw, 1.9rem);
      font-weight: 600;
    }

    .stat-card span {
      font-size: 0.85rem;
      color: var(--text-muted);
    }

    #modeBreakdown {
      list-style: none;
      margin: 18px 0 0;
      padding: 0;
      display: grid;
      gap: 10px;
    }

    #modeBreakdown li {
      display: flex;
      justify-content: space-between;
      font-size: 0.92rem;
      background: rgba(255, 255, 255, 0.04);
      padding: 10px 14px;
      border-radius: 12px;
    }

    #matches {
      display: grid;
      gap: 16px;
    }

    details.match {
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: 20px;
      overflow: hidden;
      background: rgba(13, 18, 30, 0.85);
    }

    details.match[open] {
      box-shadow: 0 18px 36px rgba(0, 0, 0, 0.4);
      border-color: rgba(93, 139, 255, 0.4);
    }

    details.match summary {
      list-style: none;
      cursor: pointer;
      padding: 18px 22px;
      display: grid;
      gap: 12px;
      grid-template-columns: minmax(120px, auto) 1fr auto;
      align-items: center;
    }

    details.match summary::-webkit-details-marker {
      display: none;
    }

    .mode-badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      font-size: 0.85rem;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      padding: 8px 12px;
      border-radius: 999px;
      background: var(--accent-soft);
      border: 1px solid rgba(93, 139, 255, 0.3);
    }

    .summary-meta {
      display: grid;
      gap: 6px;
      grid-template-columns: repeat(auto-fit, minmax(120px, auto));
      font-size: 0.92rem;
      color: var(--text-muted);
    }

    .summary-meta strong {
      display: block;
      color: var(--text);
      font-size: 0.95rem;
    }

    .players-pill {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 10px;
      border-radius: 999px;
      background: rgba(255, 255, 255, 0.06);
      font-size: 0.85rem;
    }

    .match-body {
      padding: 0 22px 22px;
      border-top: 1px solid rgba(255, 255, 255, 0.05);
      display: grid;
      gap: 22px;
    }

    .match-meta {
      display: grid;
      gap: 18px;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    }

    .meta-list {
      margin: 0;
      display: grid;
      gap: 12px;
    }

    .meta-list div {
      background: rgba(255, 255, 255, 0.04);
      padding: 12px 14px;
      border-radius: 12px;
    }

    .meta-list dt {
      margin: 0 0 4px;
      font-size: 0.78rem;
      text-transform: uppercase;
      letter-spacing: 0.1em;
      color: var(--text-muted);
    }

    .meta-list dd {
      margin: 0;
      font-weight: 600;
      font-size: 0.95rem;
      word-break: break-word;
    }

    .players-list {
      margin: 0;
      padding: 0;
      list-style: none;
      display: grid;
      gap: 6px;
    }

    .players-list li {
      padding: 8px 12px;
      background: rgba(255, 255, 255, 0.04);
      border-radius: 10px;
      font-size: 0.92rem;
    }

    pre.payload {
      margin: 0;
      background: rgba(0, 0, 0, 0.55);
      border-radius: 16px;
      padding: 16px;
      overflow-x: auto;
      font-size: 0.82rem;
      line-height: 1.55;
      border: 1px solid rgba(255, 255, 255, 0.08);
    }

    .empty-state {
      padding: 48px 24px;
      text-align: center;
      color: var(--text-muted);
      border: 1px dashed rgba(255, 255, 255, 0.16);
      border-radius: 18px;
      background: rgba(14, 18, 29, 0.7);
    }

    @media (max-width: 720px) {
      body {
        padding: 24px 14px 38px;
      }

      .hero {
        grid-template-columns: 1fr;
        text-align: center;
      }

      .hero dl {
        justify-content: center;
        text-align: center;
      }

      details.match summary {
        grid-template-columns: 1fr;
        text-align: left;
      }

      .summary-meta {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>
  <main class="page">
    <section class="panel hero">
      <img src="logo.png" alt="Q3Rally Logo" onerror="this.style.display='none'">
      <div>
        <h1>Q3Rally Ladder Monitor</h1>
        <p>Direkte Vorschau aller gespeicherten Matches aus dem <code>data/</code>-Ordner. Filtere nach Spielmodus, suche nach Maps oder Spielern und inspiziere das vollständige JSON.</p>
      </div>
      <dl>
        <div><dt>Matches</dt><dd id="stat-total">–</dd></div>
        <div><dt>Letztes Update</dt><dd id="stat-last">–</dd></div>
        <div><dt>Spielmodi</dt><dd id="stat-modes">–</dd></div>
        <div><dt>Spieler erfasst</dt><dd id="stat-players">–</dd></div>
      </dl>
    </section>

    <section class="panel controls">
      <div>
        <label for="modeFilter">Spielmodus</label>
        <select id="modeFilter">
          <option value="__all">Alle Modi</option>
        </select>
      </div>
      <div>
        <label for="searchInput">Suche</label>
        <input type="search" id="searchInput" placeholder="Match-ID, Map oder Spieler…" autocomplete="off">
      </div>
      <div>
        <label for="limitSelect">Lade-Limit</label>
        <select id="limitSelect">
          <option value="25">25</option>
          <option value="50" selected>50</option>
          <option value="100">100</option>
          <option value="250">250</option>
          <option value="500">500</option>
        </select>
      </div>
      <div>
        <label>&nbsp;</label>
        <button id="refreshButton" type="button">Aktualisieren</button>
        <p class="status" id="statusMessage">Lade Matches…</p>
      </div>
    </section>

    <section class="panel">
      <h2 style="margin:0; font-size:1.05rem; letter-spacing:0.02em; text-transform:uppercase; color:var(--text-muted);">Modus-Verteilung</h2>
      <ul id="modeBreakdown"></ul>
    </section>

    <section class="panel">
      <h2 style="margin:0 0 18px; font-size:1.2rem;">Match-Archiv</h2>
      <div id="matches" aria-live="polite"></div>
      <noscript>
        <p class="empty-state">Bitte JavaScript aktivieren, um die gespeicherten Matches anzeigen zu können.</p>
      </noscript>
    </section>
  </main>

  <script>
    const API_BASE = <?= json_encode($apiBase, JSON_UNESCAPED_SLASHES); ?>;

    const state = {
      allMatches: [],
      filteredMatches: [],
      limit: 50
    };

    const elements = {
      modeFilter: document.getElementById('modeFilter'),
      searchInput: document.getElementById('searchInput'),
      limitSelect: document.getElementById('limitSelect'),
      refreshButton: document.getElementById('refreshButton'),
      statusMessage: document.getElementById('statusMessage'),
      matches: document.getElementById('matches'),
      statTotal: document.getElementById('stat-total'),
      statLast: document.getElementById('stat-last'),
      statModes: document.getElementById('stat-modes'),
      statPlayers: document.getElementById('stat-players'),
      modeBreakdown: document.getElementById('modeBreakdown')
    };

    const formatter = new Intl.DateTimeFormat('de-DE', {
      dateStyle: 'medium',
      timeStyle: 'short'
    });

    function setStatus(message, isError = false) {
      elements.statusMessage.textContent = message;
      elements.statusMessage.classList.toggle('error', Boolean(isError));
    }

    function valueAtPath(obj, path) {
      const parts = path.split('.');
      let current = obj;
      for (const part of parts) {
        if (current && Object.prototype.hasOwnProperty.call(current, part)) {
          current = current[part];
        } else {
          return undefined;
        }
      }
      return current;
    }

    function firstString(obj, paths) {
      for (const path of paths) {
        const value = valueAtPath(obj, path);
        if (typeof value === 'string' && value.trim() !== '') {
          return value.trim();
        }
      }
      return '';
    }

    function firstNumber(obj, paths) {
      for (const path of paths) {
        const value = valueAtPath(obj, path);
        if (typeof value === 'number' && Number.isFinite(value)) {
          return value;
        }
        if (typeof value === 'string' && value.trim() !== '' && !Number.isNaN(Number(value))) {
          return Number(value);
        }
      }
      return null;
    }

    function pickArray(obj, paths) {
      for (const path of paths) {
        const value = valueAtPath(obj, path);
        if (Array.isArray(value)) {
          return value;
        }
      }
      return [];
    }

    function parseDate(value) {
      if (value === null || value === undefined) {
        return null;
      }
      if (value instanceof Date) {
        return Number.isNaN(value.getTime()) ? null : value;
      }
      if (typeof value === 'number') {
        const ms = value > 1e12 ? value : value * 1000;
        const date = new Date(ms);
        return Number.isNaN(date.getTime()) ? null : date;
      }
      if (typeof value === 'string') {
        const trimmed = value.trim();
        if (trimmed === '') {
          return null;
        }
        const numeric = Number(trimmed);
        if (!Number.isNaN(numeric)) {
          return parseDate(numeric);
        }
        const date = new Date(trimmed);
        return Number.isNaN(date.getTime()) ? null : date;
      }
      return null;
    }

    function extractMode(match) {
      return firstString(match, [
        'mode',
        'matchMode',
        'match.mode',
        'gameType',
        'game_type',
        'gametype',
        'info.mode',
        'settings.mode',
        'rules.mode',
        'meta.mode',
        'type'
      ]) || 'Unbekannt';
    }

    function extractMap(match) {
      return firstString(match, [
        'map',
        'mapName',
        'match.map',
        'metadata.map',
        'level',
        'settings.map',
        'info.map'
      ]) || '–';
    }

    function extractDuration(match) {
      const seconds = firstNumber(match, [
        'duration',
        'match.duration',
        'length',
        'matchLength',
        'timing.durationSeconds'
      ]);
      if (seconds === null) {
        return '';
      }
      const totalSeconds = Math.max(0, Math.round(seconds));
      const minutes = Math.floor(totalSeconds / 60);
      const secs = totalSeconds % 60;
      if (minutes === 0) {
        return `${totalSeconds}s`;
      }
      return `${minutes}m ${secs.toString().padStart(2, '0')}s`;
    }

    function extractStart(match) {
      const candidate = firstString(match, [
        'startTime',
        'match.startTime',
        'startedAt',
        'match.startedAt',
        'serverStartTime',
        'start',
        'matchStart',
        'info.started',
        'receivedAt'
      ]);
      const numeric = firstNumber(match, [
        'startTimestamp',
        'startedAtUnix',
        'timestamps.start',
        'match.startTimestamp'
      ]);
      return parseDate(candidate || numeric || match.receivedAt || null);
    }

    function extractMatchId(match) {
      return firstString(match, [
        'matchId',
        'id',
        'match.id',
        'identifier',
        'metadata.id'
      ]) || 'Unbekannt';
    }

    function extractPlayers(match) {
      const candidates = pickArray(match, [
        'players',
        'participants',
        'scores',
        'scoreboard',
        'stats.players',
        'match.players'
      ]);

      const names = new Set();
      for (const entry of candidates) {
        if (typeof entry === 'string' && entry.trim() !== '') {
          names.add(entry.trim());
          continue;
        }
        if (entry && typeof entry === 'object') {
          const name = firstString(entry, ['name', 'nick', 'nickname', 'player', 'id', 'uid']);
          if (name) {
            names.add(name);
          }
        }
      }
      return Array.from(names);
    }

    function escapeHtml(value) {
      return value
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
    }

    function canonicalMode(mode) {
      return mode.toLowerCase();
    }

    function updateModeFilter() {
      const selected = elements.modeFilter.value;
      const options = new Map();
      for (const match of state.allMatches) {
        const mode = extractMode(match);
        const key = canonicalMode(mode);
        if (!options.has(key)) {
          options.set(key, mode);
        }
      }

      const entries = Array.from(options.entries()).sort((a, b) => a[1].localeCompare(b[1], 'de'));
      elements.modeFilter.innerHTML = '<option value="__all">Alle Modi</option>' + entries.map(([value, label]) => `<option value="${escapeHtml(value)}">${escapeHtml(label)}</option>`).join('');

      if (entries.some(([value]) => value === selected)) {
        elements.modeFilter.value = selected;
      } else {
        elements.modeFilter.value = '__all';
      }
    }

    function updateSummary() {
      const total = state.allMatches.length;
      elements.statTotal.textContent = total ? total.toString() : '–';

      const modes = new Set(state.allMatches.map((match) => canonicalMode(extractMode(match))));
      elements.statModes.textContent = modes.size ? modes.size.toString() : '–';

      const lastDate = state.allMatches
        .map((match) => extractStart(match))
        .filter((date) => date instanceof Date && !Number.isNaN(date.getTime()))
        .sort((a, b) => b.getTime() - a.getTime())[0];
      elements.statLast.textContent = lastDate ? formatter.format(lastDate) : '–';

      const playerSet = new Set();
      state.allMatches.forEach((match) => {
        extractPlayers(match).forEach((name) => playerSet.add(name));
      });
      elements.statPlayers.textContent = playerSet.size ? playerSet.size.toString() : '–';

      const breakdown = new Map();
      state.allMatches.forEach((match) => {
        const mode = extractMode(match);
        const key = canonicalMode(mode);
        const current = breakdown.get(key) || { label: mode, count: 0 };
        current.count += 1;
        breakdown.set(key, current);
      });
      const items = Array.from(breakdown.values()).sort((a, b) => b.count - a.count);
      if (!items.length) {
        elements.modeBreakdown.innerHTML = '<li>Keine Daten vorhanden.</li>';
      } else {
        elements.modeBreakdown.innerHTML = items
          .map((item) => `<li><span>${escapeHtml(item.label)}</span><span>${item.count}</span></li>`)
          .join('');
      }
    }

    function renderMatches() {
      elements.matches.innerHTML = '';
      if (!state.filteredMatches.length) {
        elements.matches.innerHTML = '<div class="empty-state">Keine Matches gefunden. Passe die Filter an oder lade neue Daten.</div>';
        return;
      }

      state.filteredMatches.forEach((match) => {
        const mode = extractMode(match);
        const mapName = extractMap(match);
        const duration = extractDuration(match);
        const matchId = extractMatchId(match);
        const players = extractPlayers(match);
        const date = extractStart(match);

        const details = document.createElement('details');
        details.className = 'match';

        const summary = document.createElement('summary');
        summary.innerHTML = `
          <span class="mode-badge">${escapeHtml(mode)}</span>
          <div class="summary-meta">
            <span><strong>${escapeHtml(mapName)}</strong>Map</span>
            <span><strong>${escapeHtml(matchId)}</strong>ID</span>
            <span><strong>${date ? formatter.format(date) : '–'}</strong>Start</span>
            <span><strong>${duration || '–'}</strong>Dauer</span>
          </div>
          <span class="players-pill" title="Spielerzahl">👥 ${players.length}</span>
        `;
        details.append(summary);

        const body = document.createElement('div');
        body.className = 'match-body';

        const metaWrapper = document.createElement('div');
        metaWrapper.className = 'match-meta';

        const metaList = document.createElement('dl');
        metaList.className = 'meta-list';
        metaList.innerHTML = `
          <div><dt>Server</dt><dd>${escapeHtml(firstString(match, ['server', 'serverName', 'info.server', 'metadata.server']) || '–')}</dd></div>
          <div><dt>Version</dt><dd>${escapeHtml(firstString(match, ['version', 'build', 'metadata.version']) || '–')}</dd></div>
          <div><dt>Aufgenommen</dt><dd>${escapeHtml(firstString(match, ['receivedAt']) || (date ? date.toISOString() : '–'))}</dd></div>
          <div><dt>Dateigröße</dt><dd>${escapeHtml(firstString(match, ['filesize']) || '–')}</dd></div>
        `;

        const playersBlock = document.createElement('div');
        playersBlock.innerHTML = `<h3 style="margin:0 0 10px; font-size:0.95rem; letter-spacing:0.04em; text-transform:uppercase; color:var(--text-muted);">Spieler (${players.length})</h3>`;
        const playerList = document.createElement('ul');
        playerList.className = 'players-list';
        if (players.length) {
          playerList.innerHTML = players.map((name) => `<li>${escapeHtml(name)}</li>`).join('');
        } else {
          playerList.innerHTML = '<li>Keine Spielerinformationen vorhanden.</li>';
        }
        playersBlock.append(playerList);

        metaWrapper.append(metaList, playersBlock);

        const pre = document.createElement('pre');
        pre.className = 'payload';
        pre.textContent = JSON.stringify(match, null, 2);

        body.append(metaWrapper, pre);
        details.append(body);

        elements.matches.append(details);
      });
    }

    function applyFilters() {
      const selectedMode = elements.modeFilter.value;
      const term = elements.searchInput.value.trim().toLowerCase();

      let matches = state.allMatches.slice();
      if (selectedMode !== '__all') {
        matches = matches.filter((match) => canonicalMode(extractMode(match)) === selectedMode);
      }

      if (term) {
        matches = matches.filter((match) => {
          const mapName = extractMap(match).toLowerCase();
          const matchId = extractMatchId(match).toLowerCase();
          const mode = extractMode(match).toLowerCase();
          const players = extractPlayers(match).map((name) => name.toLowerCase());
          return [mapName, matchId, mode, ...players].some((value) => value.includes(term));
        });
      }

      matches.sort((a, b) => {
        const dateA = extractStart(a);
        const dateB = extractStart(b);
        const timeA = dateA ? dateA.getTime() : 0;
        const timeB = dateB ? dateB.getTime() : 0;
        return timeB - timeA;
      });

      state.filteredMatches = matches;
      renderMatches();
    }

    async function loadMatches() {
      state.limit = Number(elements.limitSelect.value) || 50;
      setStatus('Lade Matches…');
      elements.refreshButton.disabled = true;
      try {
        const response = await fetch(`${API_BASE}/matches?limit=${state.limit}`);
        if (!response.ok) {
          throw new Error(`HTTP ${response.status}`);
        }
        const payload = await response.json();
        if (!payload || !Array.isArray(payload.matches)) {
          throw new Error('Unerwartete Antwort des Servers');
        }
        state.allMatches = payload.matches;
        if (!state.allMatches.length) {
          setStatus('Keine Matches gespeichert.');
        } else {
          setStatus(`Letzte Aktualisierung: ${formatter.format(new Date())}`);
        }
        updateModeFilter();
        updateSummary();
        applyFilters();
      } catch (error) {
        console.error(error);
        state.allMatches = [];
        setStatus(`Fehler beim Laden: ${error.message}`, true);
        updateModeFilter();
        updateSummary();
        applyFilters();
      } finally {
        elements.refreshButton.disabled = false;
      }
    }

    elements.modeFilter.addEventListener('change', () => {
      applyFilters();
    });

    elements.searchInput.addEventListener('input', () => {
      applyFilters();
    });

    elements.limitSelect.addEventListener('change', () => {
      loadMatches();
    });

    elements.refreshButton.addEventListener('click', () => {
      loadMatches();
    });

    document.addEventListener('keydown', (event) => {
      if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        elements.searchInput.focus();
      }
    });

    loadMatches();
  </script>
</body>
</html>
<?php
        exit;
    }
} catch (Throwable $e) {
    // if the frontend fails for any reason, continue with API logic
}
// --- End frontend ---


$pathInfo = $_SERVER['PATH_INFO'] ?? '';
$path = trim($pathInfo, '/');
$segments = $path === '' ? [] : explode('/', $path);

try {
    switch ($method) {
        case 'POST':
            handle_post($segments);
            break;
        case 'GET':
            handle_get($segments);
            break;
        case 'DELETE':
            handle_delete($segments);
            break;
        default:
            send_error(405, 'Method not allowed.');
    }
} catch (RuntimeException $e) {
    send_error(400, $e->getMessage());
}

function handle_post(array $segments): void
{
    if ($segments !== ['matches']) {
        send_error(404, 'Endpoint not found.');
    }

    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        throw new RuntimeException('Invalid JSON payload.');
    }

    if (!isset($payload['matchId']) || !is_string($payload['matchId']) || trim($payload['matchId']) === '') {
        throw new RuntimeException('matchId is required.');
    }

    $matchId = normalize_match_id($payload['matchId']);
    if ($matchId === '') {
        throw new RuntimeException('matchId contains unsupported characters.');
    }

    $matchPath = DATA_DIR . '/' . $matchId . '.json';
    if (file_exists($matchPath)) {
        send_json(['matchId' => $payload['matchId']], 200);
        return;
    }

    $payload['receivedAt'] = gmdate('c');

    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('Failed to encode payload.');
    }

    if (file_put_contents($matchPath, $json . "\n") === false) {
        throw new RuntimeException('Unable to persist match.');
    }

    send_json(['matchId' => $payload['matchId']], 201);
}

function handle_get(array $segments): void
{
    if ($segments === ['matches']) {
        $matches = load_all_matches();
        $mode = $_GET['mode'] ?? null;
        if (is_string($mode) && $mode !== '') {
            $matches = array_filter($matches, static function ($match) use ($mode) {
                return isset($match['mode']) && strcasecmp((string) $match['mode'], $mode) === 0;
            });
        }

        usort($matches, static function ($a, $b) {
            $aTime = strtotime($a['receivedAt'] ?? 'now');
            $bTime = strtotime($b['receivedAt'] ?? 'now');
            return $bTime <=> $aTime;
        });

        $offset = isset($_GET['offset']) ? max(0, (int) $_GET['offset']) : 0;
        $limit = isset($_GET['limit']) ? max(1, (int) $_GET['limit']) : 50;
        $slice = array_slice($matches, $offset, $limit);

        send_json(['matches' => array_values($slice)], 200);
        return;
    }

    if (count($segments) === 2 && $segments[0] === 'matches') {
        $matchId = normalize_match_id($segments[1]);
        $matchPath = DATA_DIR . '/' . $matchId . '.json';
        if (!is_readable($matchPath)) {
            send_error(404, 'Match not found.');
        }

        $json = file_get_contents($matchPath);
        if ($json === false) {
            throw new RuntimeException('Failed to read match.');
        }

        $payload = json_decode($json, true);
        if (!is_array($payload)) {
            throw new RuntimeException('Stored match is corrupted.');
        }

        send_json($payload, 200);
        return;
    }

    send_error(404, 'Endpoint not found.');
}

function handle_delete(array $segments): void
{
    if (count($segments) !== 2 || $segments[0] !== 'matches') {
        send_error(404, 'Endpoint not found.');
    }

    $matchId = normalize_match_id($segments[1]);
    $matchPath = DATA_DIR . '/' . $matchId . '.json';
    if (!file_exists($matchPath)) {
        send_error(404, 'Match not found.');
    }

    if (!unlink($matchPath)) {
        throw new RuntimeException('Failed to delete match.');
    }

    http_response_code(204);
}

function load_all_matches(): array
{
    $files = glob(DATA_DIR . '/*.json');
    if ($files === false) {
        return [];
    }

    $matches = [];
    foreach ($files as $file) {
        $json = file_get_contents($file);
        if ($json === false) {
            continue;
        }
        $payload = json_decode($json, true);
        if (is_array($payload)) {
            $matches[] = $payload;
        }
    }

    return $matches;
}

function normalize_match_id(string $raw): string
{
    $normalized = preg_replace('/[^A-Za-z0-9._-]/', '_', $raw);
    return trim((string) $normalized);
}

function send_json(array $payload, int $statusCode): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function send_error(int $statusCode, string $message): void
{
    send_json(['error' => $message], $statusCode);
}
