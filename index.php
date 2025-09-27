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
      display: flex;
      flex-direction: column;
      gap: 26px;
    }

    .hero-top {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 18px;
      flex-wrap: wrap;
    }

    .hero-brand {
      display: flex;
      align-items: center;
      gap: 18px;
    }

    .hero-info {
      display: flex;
      flex-direction: column;
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
      max-width: 52ch;
    }

    .language-toggle {
      display: inline-flex;
      gap: 10px;
      padding: 6px;
      border-radius: 999px;
      background: rgba(255, 255, 255, 0.05);
      border: 1px solid rgba(255, 255, 255, 0.12);
    }

    .language-button {
      appearance: none;
      border: none;
      background: transparent;
      width: 42px;
      height: 42px;
      border-radius: 50%;
      display: grid;
      place-items: center;
      font-size: 1.25rem;
      cursor: pointer;
      transition: transform 120ms ease, box-shadow 120ms ease, background 120ms ease;
    }

    .language-button:hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 18px rgba(0, 0, 0, 0.35);
      background: rgba(255, 255, 255, 0.08);
    }

    .language-button.active {
      box-shadow: inset 0 0 0 2px rgba(93, 139, 255, 0.55);
      background: rgba(93, 139, 255, 0.18);
    }

    .hero-stats {
      margin: 0;
      display: flex;
      flex-wrap: wrap;
      gap: 16px;
      padding: 0;
      list-style: none;
    }

    .hero-stats .stat {
      min-width: 140px;
      padding: 14px 18px;
      border-radius: 16px;
      background: rgba(255, 255, 255, 0.05);
      border: 1px solid rgba(255, 255, 255, 0.08);
      display: flex;
      flex-direction: column;
      gap: 6px;
    }

    .hero-stats dt {
      color: var(--text-muted);
      font-weight: 500;
      margin: 0;
      font-size: 0.9rem;
    }

    .hero-stats dd {
      margin: 0;
      font-weight: 600;
      font-size: 1.15rem;
    }

    .controls {
      display: grid;
      gap: 18px;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      align-items: end;
    }

    .tabbed-panel {
      display: flex;
      flex-direction: column;
      gap: 24px;
    }

    .tab-header {
      display: inline-flex;
      gap: 12px;
      padding: 6px;
      border-radius: 16px;
      background: rgba(255, 255, 255, 0.06);
      border: 1px solid rgba(255, 255, 255, 0.08);
      align-self: flex-start;
    }

    .tab-button {
      appearance: none;
      border: none;
      background: transparent;
      color: var(--text-muted);
      padding: 10px 18px;
      border-radius: 12px;
      font-weight: 600;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      transition: background 140ms ease, color 140ms ease, box-shadow 140ms ease;
    }

    .tab-button:hover {
      color: var(--text);
      background: rgba(255, 255, 255, 0.08);
    }

    .tab-button.active {
      color: var(--text);
      background: var(--accent-soft);
      box-shadow: inset 0 0 0 1px rgba(93, 139, 255, 0.45);
    }

    .tab-panel {
      display: none;
      flex-direction: column;
      gap: 24px;
    }

    .tab-panel.active {
      display: flex;
    }

    .table-wrapper {
      overflow-x: auto;
      border-radius: 18px;
      border: 1px solid rgba(255, 255, 255, 0.08);
      background: rgba(10, 15, 26, 0.75);
    }

    table.leaderboard-table {
      width: 100%;
      border-collapse: collapse;
      min-width: 560px;
    }

    table.leaderboard-table thead {
      text-transform: uppercase;
      font-size: 0.75rem;
      letter-spacing: 0.12em;
      color: var(--text-muted);
      background: rgba(255, 255, 255, 0.04);
    }

    table.leaderboard-table th,
    table.leaderboard-table td {
      padding: 14px 18px;
      border-bottom: 1px solid rgba(255, 255, 255, 0.06);
      text-align: left;
    }

    table.leaderboard-table tbody tr:last-child td {
      border-bottom: none;
    }

    table.leaderboard-table tbody tr:nth-child(even) {
      background: rgba(255, 255, 255, 0.02);
    }

    table.leaderboard-table td strong {
      font-weight: 600;
      font-size: 1rem;
    }

    table.leaderboard-table td .meta {
      display: flex;
      flex-direction: column;
      gap: 4px;
      color: var(--text-muted);
      font-size: 0.85rem;
    }

    .leaderboard-player {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }

    .leaderboard-player span {
      font-size: 0.82rem;
      color: var(--text-muted);
    }

    .mono {
      font-family: 'JetBrains Mono', 'Fira Code', 'SFMono-Regular', Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
      font-size: 0.88rem;
      letter-spacing: 0.02em;
    }

    @media (max-width: 720px) {
      table.leaderboard-table {
        min-width: 100%;
      }
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

      .hero-top {
        flex-direction: column;
        align-items: stretch;
      }

      .hero-brand {
        width: 100%;
        justify-content: center;
        text-align: center;
      }

      .hero-info {
        align-items: center;
        text-align: center;
      }

      .language-toggle {
        align-self: center;
      }

      .hero-stats {
        justify-content: center;
      }

      .hero-stats .stat {
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
      <div class="hero-top">
        <div class="hero-brand">
          <img src="logo.png" alt="Q3Rally Logo" onerror="this.style.display='none'">
          <div class="hero-info">
            <h1 data-i18n="hero.title">Q3Rally Ladder Monitor</h1>
            <p data-i18n-html="hero.description">Direkte Vorschau aller gespeicherten Matches aus dem <code>data/</code>-Ordner. Vergleiche Bestzeiten aus Renn-Modi oder filtere in der Matchübersicht nach Maps, IDs und Spielern – inklusive vollständiger JSON-Details.</p>
          </div>
        </div>
        <div class="language-toggle" role="group" aria-label="Sprachauswahl" data-i18n-aria-label="language.toggleLabel">
          <button class="language-button active" type="button" data-lang="de" aria-label="Deutsch" data-i18n-aria-label="language.deLabel" title="Deutsch" data-i18n-title="language.deLabel">🇩🇪</button>
          <button class="language-button" type="button" data-lang="en" aria-label="Englisch" data-i18n-aria-label="language.enLabel" title="Englisch" data-i18n-title="language.enLabel">🇬🇧</button>
        </div>
      </div>
      <dl class="hero-stats">
        <div class="stat"><dt data-i18n="stats.matches">Matches</dt><dd id="stat-total">–</dd></div>
        <div class="stat"><dt data-i18n="stats.lastUpdate">Letztes Update</dt><dd id="stat-last">–</dd></div>
        <div class="stat"><dt data-i18n="stats.modes">Spielmodi</dt><dd id="stat-modes">–</dd></div>
        <div class="stat"><dt data-i18n="stats.players">Spieler erfasst</dt><dd id="stat-players">–</dd></div>
      </dl>
    </section>

    <section class="panel tabbed-panel">
      <div class="tab-header" role="tablist" aria-label="Ansichten" data-i18n-aria-label="tabs.label">
        <button class="tab-button active" id="tab-button-leaderboard" role="tab" aria-selected="true" aria-controls="tab-leaderboard" data-tab="leaderboard" data-i18n="tabs.bestTimes">Bestzeiten</button>
        <button class="tab-button" id="tab-button-matches" role="tab" aria-selected="false" aria-controls="tab-matches" data-tab="matches" data-i18n="tabs.matches">Matchübersicht</button>
      </div>

      <div class="tab-panel active" id="tab-leaderboard" role="tabpanel" aria-labelledby="tab-button-leaderboard">
        <div class="controls leaderboard-controls">
          <div>
            <label for="leaderboardModeFilter" data-i18n="filters.leaderboard.mode.label">Spielmodus</label>
            <select id="leaderboardModeFilter">
              <option value="__all" data-i18n="filters.leaderboard.mode.all">Alle Renn-Modi</option>
            </select>
          </div>
          <div>
            <label for="leaderboardMapFilter" data-i18n="filters.leaderboard.map.label">Map</label>
            <select id="leaderboardMapFilter">
              <option value="__all" data-i18n="filters.leaderboard.map.all">Alle Maps</option>
            </select>
          </div>
          <div>
            <label for="leaderboardPlayerSearch" data-i18n="filters.leaderboard.player.label">Spielersuche</label>
            <input type="search" id="leaderboardPlayerSearch" placeholder="Spielername…" autocomplete="off" data-i18n-placeholder="filters.leaderboard.player.placeholder">
          </div>
        </div>
        <p class="status" id="leaderboardStatus"></p>
        <div class="table-wrapper" aria-live="polite">
          <table class="leaderboard-table">
            <thead>
              <tr>
                <th scope="col" data-i18n="leaderboard.headers.rank">Rang</th>
                <th scope="col" data-i18n="leaderboard.headers.player">Spieler</th>
                <th scope="col" data-i18n="leaderboard.headers.best">Bestzeit</th>
                <th scope="col" data-i18n="leaderboard.headers.map">Map</th>
                <th scope="col" data-i18n="leaderboard.headers.mode">Modus</th>
                <th scope="col" data-i18n="leaderboard.headers.recorded">Aufgestellt am</th>
              </tr>
            </thead>
            <tbody id="leaderboardBody"></tbody>
          </table>
        </div>
        <p class="empty-state" id="leaderboardEmpty" hidden></p>
      </div>

      <div class="tab-panel" id="tab-matches" role="tabpanel" aria-labelledby="tab-button-matches">
        <div class="controls match-controls">
          <div>
            <label for="modeFilter" data-i18n="filters.matches.mode.label">Spielmodus</label>
            <select id="modeFilter">
              <option value="__all" data-i18n="filters.matches.mode.all">Alle Modi</option>
            </select>
          </div>
          <div>
            <label for="searchInput" data-i18n="filters.matches.search.label">Suche</label>
            <input type="search" id="searchInput" placeholder="Match-ID, Map oder Spieler…" autocomplete="off" data-i18n-placeholder="filters.matches.search.placeholder">
          </div>
          <div>
            <label for="limitSelect" data-i18n="filters.matches.limit.label">Lade-Limit</label>
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
            <button id="refreshButton" type="button" data-i18n="filters.matches.refresh">Aktualisieren</button>
            <p class="status" id="statusMessage"></p>
          </div>
        </div>
        <div id="matches" aria-live="polite"></div>
        <noscript>
          <p class="empty-state" data-i18n="noscript.message">Bitte JavaScript aktivieren, um die gespeicherten Matches anzeigen zu können.</p>
        </noscript>
      </div>
    </section>

    <section class="panel">
      <h2 style="margin:0; font-size:1.05rem; letter-spacing:0.02em; text-transform:uppercase; color:var(--text-muted);" data-i18n="breakdown.heading">Modus-Verteilung</h2>
      <ul id="modeBreakdown"></ul>
    </section>
  </main>

  <script>
    const API_BASE = <?= json_encode($apiBase, JSON_UNESCAPED_SLASHES); ?>;

    const I18N = {
      de: {
        'meta.title': 'Q3Rally Ladder Monitor',
        'meta.description': 'Frontend zur Auswertung der gespeicherten Q3Rally-Ladder-Matches.',
        'hero.title': 'Q3Rally Ladder Monitor',
        'hero.description': 'Direkte Vorschau aller gespeicherten Matches aus dem <code>data/</code>-Ordner. Vergleiche Bestzeiten aus Renn-Modi oder filtere in der Matchübersicht nach Maps, IDs und Spielern – inklusive vollständiger JSON-Details.',
        'language.toggleLabel': 'Sprachauswahl',
        'language.deLabel': 'Deutsch',
        'language.enLabel': 'Englisch',
        'tabs.label': 'Ansichten',
        'tabs.bestTimes': 'Bestzeiten',
        'tabs.matches': 'Matchübersicht',
        'stats.matches': 'Matches',
        'stats.lastUpdate': 'Letztes Update',
        'stats.modes': 'Spielmodi',
        'stats.players': 'Spieler erfasst',
        'filters.leaderboard.mode.label': 'Spielmodus',
        'filters.leaderboard.mode.all': 'Alle Renn-Modi',
        'filters.leaderboard.map.label': 'Map',
        'filters.leaderboard.map.all': 'Alle Maps',
        'filters.leaderboard.player.label': 'Spielersuche',
        'filters.leaderboard.player.placeholder': 'Spielername…',
        'filters.matches.mode.label': 'Spielmodus',
        'filters.matches.mode.all': 'Alle Modi',
        'filters.matches.search.label': 'Suche',
        'filters.matches.search.placeholder': 'Match-ID, Map oder Spieler…',
        'filters.matches.limit.label': 'Lade-Limit',
        'filters.matches.refresh': 'Aktualisieren',
        'leaderboard.headers.rank': 'Rang',
        'leaderboard.headers.player': 'Spieler',
        'leaderboard.headers.best': 'Bestzeit',
        'leaderboard.headers.map': 'Map',
        'leaderboard.headers.mode': 'Modus',
        'leaderboard.headers.recorded': 'Aufgestellt am',
        'leaderboard.status.waiting': 'Warte auf Daten…',
        'leaderboard.status.loading': 'Lade Bestzeiten…',
        'leaderboard.status.noneData': 'Keine Renn-Daten in den geladenen Matches gefunden.',
        'leaderboard.status.noFilterMatches': 'Keine Ergebnisse für die aktuellen Filter.',
        'leaderboard.status.count': '{displayed} von {total} Bestzeiten angezeigt.',
        'leaderboard.status.error': 'Fehler beim Laden: {message}',
        'leaderboard.empty.noData': 'Keine Bestzeiten gefunden. Lade weitere Renn-Matches oder passe die Filter an.',
        'leaderboard.empty.noFilterMatches': 'Keine Ergebnisse passend zu den aktuellen Filtern.',
        'leaderboard.matchId.title': 'Match-ID',
        'leaderboard.meta.recorded': 'Aufgestellt am',
        'status.loadingMatches': 'Lade Matches…',
        'status.noMatches': 'Keine Matches gespeichert.',
        'status.lastUpdated': 'Letzte Aktualisierung: {timestamp}',
        'status.error': 'Fehler beim Laden: {message}',
        'matches.empty': 'Keine Matches gefunden. Passe die Filter an oder lade neue Daten.',
        'matches.summary.mapLabel': 'Map',
        'matches.summary.idLabel': 'ID',
        'matches.summary.startLabel': 'Start',
        'matches.summary.durationLabel': 'Dauer',
        'matches.summary.playersTitle': 'Spielerzahl',
        'matches.meta.server': 'Server',
        'matches.meta.version': 'Version',
        'matches.meta.recorded': 'Aufgenommen',
        'matches.meta.size': 'Dateigröße',
        'matches.players.heading': 'Spieler ({count})',
        'matches.players.empty': 'Keine Spielerinformationen vorhanden.',
        'matches.status.error': 'Fehler beim Laden: {message}',
        'errors.unexpectedResponse': 'Unerwartete Antwort des Servers.',
        'breakdown.heading': 'Modus-Verteilung',
        'breakdown.empty': 'Keine Daten vorhanden.',
        'noscript.message': 'Bitte JavaScript aktivieren, um die gespeicherten Matches anzeigen zu können.',
        'mode.unknown': 'Unbekannt',
        'common.unknown': 'Unbekannt'
      },
      en: {
        'meta.title': 'Q3Rally Ladder Monitor',
        'meta.description': 'Frontend for exploring the stored Q3Rally ladder matches.',
        'hero.title': 'Q3Rally Ladder Monitor',
        'hero.description': 'Live preview of every stored match from the <code>data/</code> folder. Compare best racing times or filter the match overview by maps, IDs, and players – complete with full JSON details.',
        'language.toggleLabel': 'Language selection',
        'language.deLabel': 'German',
        'language.enLabel': 'English',
        'tabs.label': 'Views',
        'tabs.bestTimes': 'Best times',
        'tabs.matches': 'Match overview',
        'stats.matches': 'Matches',
        'stats.lastUpdate': 'Last update',
        'stats.modes': 'Game modes',
        'stats.players': 'Players tracked',
        'filters.leaderboard.mode.label': 'Game mode',
        'filters.leaderboard.mode.all': 'All racing modes',
        'filters.leaderboard.map.label': 'Map',
        'filters.leaderboard.map.all': 'All maps',
        'filters.leaderboard.player.label': 'Player search',
        'filters.leaderboard.player.placeholder': 'Player name…',
        'filters.matches.mode.label': 'Game mode',
        'filters.matches.mode.all': 'All modes',
        'filters.matches.search.label': 'Search',
        'filters.matches.search.placeholder': 'Match ID, map, or player…',
        'filters.matches.limit.label': 'Load limit',
        'filters.matches.refresh': 'Refresh',
        'leaderboard.headers.rank': 'Rank',
        'leaderboard.headers.player': 'Player',
        'leaderboard.headers.best': 'Best time',
        'leaderboard.headers.map': 'Map',
        'leaderboard.headers.mode': 'Mode',
        'leaderboard.headers.recorded': 'Set on',
        'leaderboard.status.waiting': 'Waiting for data…',
        'leaderboard.status.loading': 'Loading best times…',
        'leaderboard.status.noneData': 'No racing data found in the loaded matches.',
        'leaderboard.status.noFilterMatches': 'No results for the current filters.',
        'leaderboard.status.count': 'Showing {displayed} of {total} best times.',
        'leaderboard.status.error': 'Load error: {message}',
        'leaderboard.empty.noData': 'No best times found. Upload more racing matches or adjust the filters.',
        'leaderboard.empty.noFilterMatches': 'No results match the current filters.',
        'leaderboard.matchId.title': 'Match ID',
        'leaderboard.meta.recorded': 'Recorded on',
        'status.loadingMatches': 'Loading matches…',
        'status.noMatches': 'No matches stored.',
        'status.lastUpdated': 'Last refreshed: {timestamp}',
        'status.error': 'Load error: {message}',
        'matches.empty': 'No matches found. Adjust the filters or upload new data.',
        'matches.summary.mapLabel': 'Map',
        'matches.summary.idLabel': 'ID',
        'matches.summary.startLabel': 'Start',
        'matches.summary.durationLabel': 'Duration',
        'matches.summary.playersTitle': 'Player count',
        'matches.meta.server': 'Server',
        'matches.meta.version': 'Version',
        'matches.meta.recorded': 'Recorded',
        'matches.meta.size': 'File size',
        'matches.players.heading': 'Players ({count})',
        'matches.players.empty': 'No player information available.',
        'matches.status.error': 'Load error: {message}',
        'errors.unexpectedResponse': 'Unexpected server response.',
        'breakdown.heading': 'Mode distribution',
        'breakdown.empty': 'No data available.',
        'noscript.message': 'Please enable JavaScript to display the stored matches.',
        'mode.unknown': 'Unknown',
        'common.unknown': 'Unknown'
      }
    };

    const MODE_TRANSLATIONS = {
      de: {
        'gt_racing': 'Rennen',
        'gt_quickrace': 'Schnellrennen',
        'gt_timeattack': 'Zeitfahren',
        'gt_championship': 'Meisterschaft',
        'gt_duel': 'Duell',
        'gt_capture': 'Capture the Flag'
      },
      en: {
        'gt_racing': 'Racing',
        'gt_quickrace': 'Quickrace',
        'gt_timeattack': 'Time Attack',
        'gt_championship': 'Championship',
        'gt_duel': 'Duel',
        'gt_capture': 'Capture the Flag'
      }
    };

    const state = {
      allMatches: [],
      filteredMatches: [],
      limit: 50,
      leaderboard: [],
      filteredLeaderboard: [],
      activeTab: 'leaderboard',
      language: 'de',
      statuses: {
        overview: { key: null, params: {}, isError: false },
        leaderboard: { key: null, params: {}, isError: false }
      }
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
      modeBreakdown: document.getElementById('modeBreakdown'),
      leaderboardStatus: document.getElementById('leaderboardStatus'),
      leaderboardBody: document.getElementById('leaderboardBody'),
      leaderboardEmpty: document.getElementById('leaderboardEmpty'),
      leaderboardModeFilter: document.getElementById('leaderboardModeFilter'),
      leaderboardMapFilter: document.getElementById('leaderboardMapFilter'),
      leaderboardPlayerSearch: document.getElementById('leaderboardPlayerSearch'),
      tabButtons: Array.from(document.querySelectorAll('[data-tab]')),
      tabPanels: {
        leaderboard: document.getElementById('tab-leaderboard'),
        matches: document.getElementById('tab-matches')
      },
      languageButtons: Array.from(document.querySelectorAll('.language-button')),
      html: document.documentElement,
      metaDescription: document.querySelector('meta[name="description"]')
    };

    function getLocale(lang = state.language) {
      return lang === 'de' ? 'de-DE' : 'en-US';
    }

    function createFormatter(lang = state.language) {
      return new Intl.DateTimeFormat(getLocale(lang), {
        dateStyle: 'medium',
        timeStyle: 'short'
      });
    }

    let formatter = createFormatter();

    function translateWithFallback(key, lang = state.language) {
      const primary = I18N[lang] || {};
      if (Object.prototype.hasOwnProperty.call(primary, key)) {
        return primary[key];
      }
      if (lang !== 'en' && I18N.en && Object.prototype.hasOwnProperty.call(I18N.en, key)) {
        return I18N.en[key];
      }
      if (lang !== 'de' && I18N.de && Object.prototype.hasOwnProperty.call(I18N.de, key)) {
        return I18N.de[key];
      }
      return key;
    }

    function t(key, params = {}) {
      const template = translateWithFallback(key);
      if (typeof template !== 'string') {
        return key;
      }
      return template.replace(/\{(\w+)\}/g, (match, token) => {
        if (Object.prototype.hasOwnProperty.call(params, token)) {
          return String(params[token]);
        }
        return match;
      });
    }

    function applyStaticTranslations() {
      document.querySelectorAll('[data-i18n]').forEach((element) => {
        const key = element.dataset.i18n;
        if (key) {
          element.textContent = t(key);
        }
      });
      document.querySelectorAll('[data-i18n-html]').forEach((element) => {
        const key = element.dataset.i18nHtml;
        if (key) {
          element.innerHTML = t(key);
        }
      });
      document.querySelectorAll('[data-i18n-placeholder]').forEach((element) => {
        const key = element.dataset.i18nPlaceholder;
        if (key) {
          element.setAttribute('placeholder', t(key));
        }
      });
      document.querySelectorAll('[data-i18n-title]').forEach((element) => {
        const key = element.dataset.i18nTitle;
        if (key) {
          element.setAttribute('title', t(key));
        }
      });
      document.querySelectorAll('[data-i18n-aria-label]').forEach((element) => {
        const key = element.dataset.i18nAriaLabel;
        if (key) {
          element.setAttribute('aria-label', t(key));
        }
      });
    }

    function updateLanguageButtons() {
      elements.languageButtons.forEach((button) => {
        const isActive = button.dataset.lang === state.language;
        button.classList.toggle('active', isActive);
        button.setAttribute('aria-pressed', String(isActive));
      });
    }

    function formatMessage(key, params = {}) {
      return t(key, params);
    }

    function syncStatuses() {
      if (state.statuses.overview.key) {
        setStatus(state.statuses.overview.key, state.statuses.overview.params, state.statuses.overview.isError, false);
      }
      if (state.statuses.leaderboard.key) {
        setLeaderboardStatus(state.statuses.leaderboard.key, state.statuses.leaderboard.params, state.statuses.leaderboard.isError, false);
      }
    }

    function applyLanguage(lang) {
      if (!I18N[lang]) {
        lang = 'de';
      }
      state.language = lang;
      elements.html.lang = lang === 'de' ? 'de' : 'en';
      formatter = createFormatter(lang);
      document.title = t('meta.title');
      if (elements.metaDescription) {
        elements.metaDescription.setAttribute('content', t('meta.description'));
      }
      applyStaticTranslations();
      updateLanguageButtons();
      updateModeFilter();
      updateSummary();
      buildLeaderboard();
      updateLeaderboardFilters();
      if (state.leaderboard.length) {
        applyLeaderboardFilters();
      } else {
        elements.leaderboardBody.innerHTML = '';
        elements.leaderboardEmpty.hidden = true;
      }
      if (state.allMatches.length) {
        applyFilters();
      } else {
        elements.matches.innerHTML = '';
      }
      syncStatuses();
    }

    const TIME_PATH_CANDIDATES = [
      'bestLap',
      'bestLapTime',
      'bestLapMs',
      'bestLapMilliseconds',
      'fastestLap',
      'fastestLapTime',
      'fastestLapMs',
      'fastestLapMilliseconds',
      'fastestTime',
      'bestTime',
      'raceTime',
      'totalTime',
      'total_time',
      'lapTime',
      'lap_time',
      'duration',
      'durationSeconds',
      'duration_seconds',
      'stats.bestLap',
      'stats.bestLapTime',
      'stats.fastestLap',
      'stats.fastestTime',
      'stats.raceTime',
      'result.bestLap',
      'result.bestLapTime',
      'result.fastestLap',
      'result.fastestTime',
      'timing.bestLap',
      'timing.bestTime',
      'timing.totalTime',
      'timings.bestLap',
      'timings.bestTime'
    ];

    const PLAYER_PATH_CANDIDATES = [
      'name',
      'nick',
      'nickname',
      'player',
      'playerName',
      'driver',
      'driverName',
      'racer',
      'pilot',
      'id',
      'uid',
      'guid',
      'stats.name',
      'stats.player',
      'result.name',
      'result.player'
    ];

    const VEHICLE_PATH_CANDIDATES = [
      'vehicle',
      'car',
      'bike',
      'kart',
      'ride',
      'stats.vehicle',
      'result.vehicle'
    ];

    const SCOREBOARD_PATHS = [
      'leaderboard',
      'leaderboard.entries',
      'results',
      'results.entries',
      'scoreboard',
      'scores',
      'stats.results',
      'match.scoreboard',
      'match.results',
      'timing.results',
      'timing.leaderboard',
      'timings',
      'players'
    ];

    const MAX_REASONABLE_TIME = 6 * 3600; // 6 Stunden

    function setStatus(key, params = {}, isError = false, persist = true) {
      const message = formatMessage(key, params);
      elements.statusMessage.textContent = message;
      elements.statusMessage.classList.toggle('error', Boolean(isError));
      if (persist) {
        state.statuses.overview = { key, params, isError };
      }
    }

    function setLeaderboardStatus(key, params = {}, isError = false, persist = true) {
      const message = formatMessage(key, params);
      elements.leaderboardStatus.textContent = message;
      elements.leaderboardStatus.classList.toggle('error', Boolean(isError));
      if (persist) {
        state.statuses.leaderboard = { key, params, isError };
      }
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
      ]) || '__unknown__';
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
      const id = firstString(match, [
        'matchId',
        'id',
        'match.id',
        'identifier',
        'metadata.id'
      ]);
      return id || t('common.unknown');
    }

    function extractRecordedAtFromMatchId(matchId) {
      if (typeof matchId !== 'string') {
        return null;
      }

      const digits = matchId.replace(/\D+/g, '');
      if (digits.length < 12) {
        return null;
      }

      const year = Number(digits.slice(0, 4));
      const month = Number(digits.slice(4, 6)) - 1;
      const day = Number(digits.slice(6, 8));
      const hour = Number(digits.slice(8, 10));
      const minute = Number(digits.slice(10, 12));
      const second = digits.length >= 14 ? Number(digits.slice(12, 14)) : 0;

      if (
        [year, month, day, hour, minute, second].some((part) => Number.isNaN(part)) ||
        month < 0 || month > 11 ||
        day < 1 || day > 31 ||
        hour > 23 ||
        minute > 59 ||
        second > 59
      ) {
        return null;
      }

      const candidate = new Date(year, month, day, hour, minute, second);
      return Number.isNaN(candidate.getTime()) ? null : candidate;
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
      if (typeof mode !== 'string') {
        return '__unknown__';
      }
      const trimmed = mode.trim();
      if (!trimmed) {
        return '__unknown__';
      }
      return trimmed.toLowerCase();
    }

    function humanizeMode(mode) {
      if (typeof mode !== 'string') {
        return t('mode.unknown');
      }

      const trimmed = mode.trim();
      if (!trimmed) {
        return t('mode.unknown');
      }

      const key = trimmed.toLowerCase();
      const translations = MODE_TRANSLATIONS[state.language] || {};
      if (Object.prototype.hasOwnProperty.call(translations, key)) {
        return translations[key];
      }

      const withoutPrefix = trimmed.replace(/^GT[_\-\s]?/i, '');
      const normalized = withoutPrefix.replace(/[_\-]+/g, ' ').toLowerCase();
      const locale = getLocale();
      return normalized.replace(/(^|\s)([\p{L}])/gu, (match, prefix, char) => prefix + char.toLocaleUpperCase(locale));
    }

    function isReasonableRaceTime(seconds) {
      return Number.isFinite(seconds) && seconds > 0 && seconds < MAX_REASONABLE_TIME;
    }

    function parseTimeToSeconds(value) {
      if (value === null || value === undefined) {
        return null;
      }

      if (typeof value === 'number') {
        const abs = Math.abs(value);
        if (!Number.isFinite(abs) || abs === 0) {
          return null;
        }
        if (abs < MAX_REASONABLE_TIME) {
          return abs;
        }
        if (abs >= 600 && abs < MAX_REASONABLE_TIME * 1000) {
          return abs / 1000;
        }
        if (abs >= 600 && abs < MAX_REASONABLE_TIME * 1_000_000) {
          return abs / 1_000_000;
        }
        return null;
      }

      if (typeof value === 'string') {
        const trimmed = value.trim();
        if (trimmed === '') {
          return null;
        }

        const normalized = trimmed.replace(',', '.');
        const numeric = Number(normalized);
        if (!Number.isNaN(numeric)) {
          return parseTimeToSeconds(numeric);
        }

        const msMatch = normalized.match(/^([0-9]+(?:\.[0-9]+)?)\s*ms$/i);
        if (msMatch) {
          return parseFloat(msMatch[1]) / 1000;
        }

        const secMatch = normalized.match(/^([0-9]+(?:\.[0-9]+)?)\s*s$/i);
        if (secMatch) {
          return parseFloat(secMatch[1]);
        }

        const minuteMatch = normalized.match(/^([0-9]+)\s*m(?:in)?\s*([0-9]+(?:\.[0-9]+)?)\s*s$/i);
        if (minuteMatch) {
          return Number(minuteMatch[1]) * 60 + Number(minuteMatch[2]);
        }

        const isoMatch = normalized.match(/^PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+(?:\.\d+)?)S)?$/i);
        if (isoMatch) {
          const hours = isoMatch[1] ? Number(isoMatch[1]) : 0;
          const minutes = isoMatch[2] ? Number(isoMatch[2]) : 0;
          const seconds = isoMatch[3] ? Number(isoMatch[3]) : 0;
          return hours * 3600 + minutes * 60 + seconds;
        }

        const colonMatch = normalized.match(/^(\d+):(\d{2})(?::(\d{2}))?(?:\.(\d+))?$/);
        if (colonMatch) {
          const first = Number(colonMatch[1]);
          const second = Number(colonMatch[2]);
          const third = colonMatch[3] !== undefined ? Number(colonMatch[3]) : null;
          const fraction = colonMatch[4] ? Number(`0.${colonMatch[4]}`) : 0;
          if (third !== null) {
            return first * 3600 + second * 60 + third + fraction;
          }
          return first * 60 + second + fraction;
        }

        const simpleMinuteMatch = normalized.match(/^([0-9]+)m(?:in)?$/i);
        if (simpleMinuteMatch) {
          return Number(simpleMinuteMatch[1]) * 60;
        }

        return null;
      }

      return null;
    }

    function findBestTime(entry) {
      if (!entry || typeof entry !== 'object') {
        return null;
      }

      let best = null;
      for (const path of TIME_PATH_CANDIDATES) {
        const candidate = valueAtPath(entry, path);
        const seconds = parseTimeToSeconds(candidate);
        if (seconds !== null && isReasonableRaceTime(seconds)) {
          if (best === null || seconds < best) {
            best = seconds;
          }
        }
      }

      if (best !== null) {
        return best;
      }

      const visited = new Set();
      function walk(node, hint = '') {
        if (node === null || node === undefined) {
          return;
        }
        if (typeof node === 'number' || typeof node === 'string') {
          const keyHint = String(hint).toLowerCase();
          if (keyHint.includes('time') || keyHint.includes('lap') || keyHint.includes('duration')) {
            const seconds = parseTimeToSeconds(node);
            if (seconds !== null && isReasonableRaceTime(seconds)) {
              if (best === null || seconds < best) {
                best = seconds;
              }
            }
          }
          return;
        }
        if (typeof node === 'object') {
          if (visited.has(node)) {
            return;
          }
          visited.add(node);
          if (Array.isArray(node)) {
            for (const item of node) {
              walk(item, hint);
            }
            return;
          }
          for (const [key, value] of Object.entries(node)) {
            walk(value, key);
          }
        }
      }

      walk(entry, '');
      return best;
    }

    function extractLeaderboardPlayer(entry) {
      if (entry === null || entry === undefined) {
        return '';
      }
      if (typeof entry === 'string') {
        return entry.trim();
      }
      if (typeof entry !== 'object') {
        return '';
      }
      const name = firstString(entry, PLAYER_PATH_CANDIDATES);
      if (name) {
        return name;
      }
      if (entry.stats && typeof entry.stats === 'object') {
        return firstString(entry.stats, PLAYER_PATH_CANDIDATES);
      }
      if (entry.result && typeof entry.result === 'object') {
        return firstString(entry.result, PLAYER_PATH_CANDIDATES);
      }
      return '';
    }

    function extractVehicle(entry) {
      if (!entry || typeof entry !== 'object') {
        return '';
      }
      const vehicle = firstString(entry, VEHICLE_PATH_CANDIDATES);
      if (vehicle) {
        return vehicle;
      }
      if (entry.stats && typeof entry.stats === 'object') {
        return firstString(entry.stats, VEHICLE_PATH_CANDIDATES);
      }
      if (entry.result && typeof entry.result === 'object') {
        return firstString(entry.result, VEHICLE_PATH_CANDIDATES);
      }
      return '';
    }

    function formatSeconds(seconds) {
      if (!Number.isFinite(seconds)) {
        return '–';
      }
      const totalMilliseconds = Math.round(seconds * 1000);
      const hours = Math.floor(totalMilliseconds / 3_600_000);
      const minutes = Math.floor((totalMilliseconds % 3_600_000) / 60_000);
      const secs = Math.floor((totalMilliseconds % 60_000) / 1000);
      const millis = totalMilliseconds % 1000;
      const millisStr = millis.toString().padStart(3, '0');
      if (hours > 0) {
        return `${hours}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}.${millisStr}`;
      }
      return `${minutes}:${secs.toString().padStart(2, '0')}.${millisStr}`;
    }

    function buildLeaderboard() {
      const bestByKey = new Map();

      for (const match of state.allMatches) {
        const entries = [];
        const seen = new Set();

        for (const path of SCOREBOARD_PATHS) {
          const value = valueAtPath(match, path);
          if (!Array.isArray(value) || value.length === 0) {
            continue;
          }
          for (const item of value) {
            if (item && typeof item === 'object') {
              if (seen.has(item)) {
                continue;
              }
              seen.add(item);
            }
            entries.push(item);
          }
        }

        if (entries.length === 0) {
          continue;
        }

        const rawMode = extractMode(match);
        const modeKey = canonicalMode(rawMode);
        const modeLabel = humanizeMode(rawMode);
        const map = extractMap(match);
        const mapKey = map.toLowerCase();
        const matchId = extractMatchId(match);
        const startedAt = extractStart(match);
        const recordedAt = extractRecordedAtFromMatchId(matchId);

        for (const entry of entries) {
          const player = extractLeaderboardPlayer(entry);
          if (!player) {
            continue;
          }
          const seconds = findBestTime(entry);
          if (seconds === null || !isReasonableRaceTime(seconds)) {
            continue;
          }
          const key = `${modeKey}||${mapKey}||${player.toLowerCase()}`;
          const vehicle = extractVehicle(entry);
          const current = bestByKey.get(key);
          if (!current || seconds < current.time) {
            bestByKey.set(key, {
              player,
              playerLower: player.toLowerCase(),
              time: seconds,
              map,
              mapKey,
              mode: modeLabel,
              modeKey,
              matchId,
              startedAt,
              recordedAt,
              vehicle
            });
          }
        }
      }

      const locale = getLocale();
      state.leaderboard = Array.from(bestByKey.values()).sort((a, b) => {
        if (a.time !== b.time) {
          return a.time - b.time;
        }
        const mapCompare = a.map.localeCompare(b.map, locale);
        if (mapCompare !== 0) {
          return mapCompare;
        }
        return a.player.localeCompare(b.player, locale);
      });
    }

    function populateSelect(select, defaultLabel, optionsMap, previousValue) {
      const fragment = document.createDocumentFragment();
      const defaultOption = document.createElement('option');
      defaultOption.value = '__all';
      defaultOption.textContent = defaultLabel;
      fragment.appendChild(defaultOption);

      const locale = getLocale();
      const sorted = Array.from(optionsMap.entries()).sort((a, b) => a[1].localeCompare(b[1], locale));
      for (const [value, label] of sorted) {
        const option = document.createElement('option');
        option.value = value;
        option.textContent = label;
        fragment.appendChild(option);
      }

      select.innerHTML = '';
      select.appendChild(fragment);

      if (previousValue && previousValue !== '__all' && optionsMap.has(previousValue)) {
        select.value = previousValue;
      } else {
        select.value = '__all';
      }
    }

    function updateLeaderboardFilters() {
      const previousMode = elements.leaderboardModeFilter.value;
      const previousMap = elements.leaderboardMapFilter.value;

      const modeOptions = new Map();
      const mapOptions = new Map();

      for (const entry of state.leaderboard) {
        if (!modeOptions.has(entry.modeKey)) {
          modeOptions.set(entry.modeKey, entry.mode);
        }
        if (!mapOptions.has(entry.mapKey)) {
          mapOptions.set(entry.mapKey, entry.map);
        }
      }

      populateSelect(elements.leaderboardModeFilter, t('filters.leaderboard.mode.all'), modeOptions, previousMode);
      populateSelect(elements.leaderboardMapFilter, t('filters.leaderboard.map.all'), mapOptions, previousMap);

      const hasEntries = state.leaderboard.length > 0;
      elements.leaderboardModeFilter.disabled = !hasEntries;
      elements.leaderboardMapFilter.disabled = !hasEntries;
      elements.leaderboardPlayerSearch.disabled = !hasEntries;
      if (!hasEntries) {
        elements.leaderboardPlayerSearch.value = '';
      }
    }

    function applyLeaderboardFilters() {
      const mode = elements.leaderboardModeFilter.value;
      const map = elements.leaderboardMapFilter.value;
      const playerTerm = elements.leaderboardPlayerSearch.value.trim().toLowerCase();

      state.filteredLeaderboard = state.leaderboard.filter((entry) => {
        if (mode !== '__all' && entry.modeKey !== mode) {
          return false;
        }
        if (map !== '__all' && entry.mapKey !== map) {
          return false;
        }
        if (playerTerm && !entry.playerLower.includes(playerTerm)) {
          return false;
        }
        return true;
      });

      renderLeaderboard();
    }

    function renderLeaderboard() {
      const rows = state.filteredLeaderboard;
      const hasEntries = state.leaderboard.length > 0;

      elements.leaderboardBody.innerHTML = '';

      if (!hasEntries) {
        elements.leaderboardEmpty.hidden = false;
        elements.leaderboardEmpty.textContent = t('leaderboard.empty.noData');
        setLeaderboardStatus('leaderboard.status.noneData');
        return;
      }

      if (!rows.length) {
        elements.leaderboardEmpty.hidden = false;
        elements.leaderboardEmpty.textContent = t('leaderboard.empty.noFilterMatches');
        setLeaderboardStatus('leaderboard.status.noFilterMatches');
        return;
      }

      elements.leaderboardEmpty.hidden = true;
      const markup = rows.map((entry, index) => {
        const rank = index + 1;
        const recordDate = entry.recordedAt || entry.startedAt;
        const dateLabel = recordDate ? formatter.format(recordDate) : '–';
        const vehicle = entry.vehicle ? `<span>${escapeHtml(entry.vehicle)}</span>` : '';
        const matchIdLabel = entry.matchId && entry.matchId !== t('common.unknown') ? entry.matchId : '';
        const matchIdHtml = matchIdLabel ? `<span class="mono" title="${escapeHtml(t('leaderboard.matchId.title'))}">${escapeHtml(matchIdLabel)}</span>` : '';
        return `
          <tr>
            <td>${rank}</td>
            <td>
              <div class="leaderboard-player">
                <strong>${escapeHtml(entry.player)}</strong>
                ${vehicle}
              </div>
            </td>
            <td>${escapeHtml(formatSeconds(entry.time))}</td>
            <td>${escapeHtml(entry.map)}</td>
            <td>${escapeHtml(entry.mode)}</td>
            <td>
              <div class="meta">
                <span>${escapeHtml(dateLabel)}</span>
                ${matchIdHtml}
              </div>
            </td>
          </tr>
        `;
      }).join('');

      elements.leaderboardBody.innerHTML = markup;
      setLeaderboardStatus('leaderboard.status.count', { displayed: rows.length, total: state.leaderboard.length });
    }

    function updateModeFilter() {
      const selected = elements.modeFilter.value;
      const options = new Map();
      for (const match of state.allMatches) {
        const mode = extractMode(match);
        const key = canonicalMode(mode);
        if (!options.has(key)) {
          options.set(key, humanizeMode(mode));
        }
      }

      const locale = getLocale();
      const entries = Array.from(options.entries()).sort((a, b) => a[1].localeCompare(b[1], locale));
      const defaultLabel = t('filters.matches.mode.all');
      elements.modeFilter.innerHTML = `<option value="__all">${escapeHtml(defaultLabel)}</option>` + entries.map(([value, label]) => `<option value="${escapeHtml(value)}">${escapeHtml(label)}</option>`).join('');

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
        const label = humanizeMode(mode);
        const current = breakdown.get(key) || { label, count: 0 };
        current.label = label;
        current.count += 1;
        breakdown.set(key, current);
      });
      const items = Array.from(breakdown.values()).sort((a, b) => b.count - a.count);
      if (!items.length) {
        elements.modeBreakdown.innerHTML = `<li>${escapeHtml(t('breakdown.empty'))}</li>`;
      } else {
        elements.modeBreakdown.innerHTML = items
          .map((item) => `<li><span>${escapeHtml(item.label)}</span><span>${item.count}</span></li>`)
          .join('');
      }
    }

    function renderMatches() {
      elements.matches.innerHTML = '';
      if (!state.filteredMatches.length) {
        elements.matches.innerHTML = `<div class="empty-state">${escapeHtml(t('matches.empty'))}</div>`;
        return;
      }

      state.filteredMatches.forEach((match) => {
        const rawMode = extractMode(match);
        const modeLabel = humanizeMode(rawMode);
        const mapName = extractMap(match);
        const duration = extractDuration(match);
        const matchId = extractMatchId(match);
        const players = extractPlayers(match);
        const date = extractStart(match);

        const labels = {
          map: t('matches.summary.mapLabel'),
          id: t('matches.summary.idLabel'),
          start: t('matches.summary.startLabel'),
          duration: t('matches.summary.durationLabel'),
          players: t('matches.summary.playersTitle'),
          server: t('matches.meta.server'),
          version: t('matches.meta.version'),
          recorded: t('matches.meta.recorded'),
          size: t('matches.meta.size')
        };

        const formattedDate = date ? formatter.format(date) : '–';
        const durationLabel = duration || '–';

        const details = document.createElement('details');
        details.className = 'match';

        const summary = document.createElement('summary');
        summary.innerHTML = `
          <span class="mode-badge">${escapeHtml(modeLabel)}</span>
          <div class="summary-meta">
            <span><strong>${escapeHtml(mapName)}</strong>${escapeHtml(labels.map)}</span>
            <span><strong>${escapeHtml(matchId)}</strong>${escapeHtml(labels.id)}</span>
            <span><strong>${escapeHtml(formattedDate)}</strong>${escapeHtml(labels.start)}</span>
            <span><strong>${escapeHtml(durationLabel)}</strong>${escapeHtml(labels.duration)}</span>
          </div>
          <span class="players-pill" title="${escapeHtml(labels.players)}">👥 ${players.length}</span>
        `;
        details.append(summary);

        const body = document.createElement('div');
        body.className = 'match-body';

        const metaWrapper = document.createElement('div');
        metaWrapper.className = 'match-meta';

        const metaList = document.createElement('dl');
        metaList.className = 'meta-list';
        metaList.innerHTML = `
          <div><dt>${escapeHtml(labels.server)}</dt><dd>${escapeHtml(firstString(match, ['server', 'serverName', 'info.server', 'metadata.server']) || '–')}</dd></div>
          <div><dt>${escapeHtml(labels.version)}</dt><dd>${escapeHtml(firstString(match, ['version', 'build', 'metadata.version']) || '–')}</dd></div>
          <div><dt>${escapeHtml(labels.recorded)}</dt><dd>${escapeHtml(firstString(match, ['receivedAt']) || (date ? date.toISOString() : '–'))}</dd></div>
          <div><dt>${escapeHtml(labels.size)}</dt><dd>${escapeHtml(firstString(match, ['filesize']) || '–')}</dd></div>
        `;

        const playersBlock = document.createElement('div');
        const playersHeading = t('matches.players.heading', { count: players.length });
        playersBlock.innerHTML = `<h3 style="margin:0 0 10px; font-size:0.95rem; letter-spacing:0.04em; text-transform:uppercase; color:var(--text-muted);">${escapeHtml(playersHeading)}</h3>`;
        const playerList = document.createElement('ul');
        playerList.className = 'players-list';
        if (players.length) {
          playerList.innerHTML = players.map((name) => `<li>${escapeHtml(name)}</li>`).join('');
        } else {
          playerList.innerHTML = `<li>${escapeHtml(t('matches.players.empty'))}</li>`;
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
          const rawMode = extractMode(match);
          const mode = rawMode.toLowerCase();
          const modeLabel = humanizeMode(rawMode).toLowerCase();
          const players = extractPlayers(match).map((name) => name.toLowerCase());
          return [mapName, matchId, mode, modeLabel, ...players].some((value) => value.includes(term));
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
      setStatus('status.loadingMatches');
      setLeaderboardStatus('leaderboard.status.loading');
      elements.refreshButton.disabled = true;
      try {
        const response = await fetch(`${API_BASE}/matches?limit=${state.limit}`);
        if (!response.ok) {
          throw new Error(`HTTP ${response.status}`);
        }
        const payload = await response.json();
        if (!payload || !Array.isArray(payload.matches)) {
          throw new Error(t('errors.unexpectedResponse'));
        }
        state.allMatches = payload.matches;
        if (!state.allMatches.length) {
          setStatus('status.noMatches');
        } else {
          setStatus('status.lastUpdated', { timestamp: formatter.format(new Date()) });
        }
        updateModeFilter();
        updateSummary();
        buildLeaderboard();
        updateLeaderboardFilters();
        applyLeaderboardFilters();
        applyFilters();
      } catch (error) {
        console.error(error);
        state.allMatches = [];
        setStatus('status.error', { message: error.message }, true);
        state.leaderboard = [];
        state.filteredLeaderboard = [];
        updateModeFilter();
        updateSummary();
        updateLeaderboardFilters();
        renderLeaderboard();
        applyFilters();
        setLeaderboardStatus('leaderboard.status.error', { message: error.message }, true);
      } finally {
        elements.refreshButton.disabled = false;
      }
    }

    function setActiveTab(tab) {
      if (!elements.tabPanels[tab]) {
        return;
      }
      state.activeTab = tab;
      elements.tabButtons.forEach((button) => {
        const isActive = button.dataset.tab === tab;
        button.classList.toggle('active', isActive);
        button.setAttribute('aria-selected', String(isActive));
        button.setAttribute('tabindex', isActive ? '0' : '-1');
      });
      Object.entries(elements.tabPanels).forEach(([key, panel]) => {
        panel.classList.toggle('active', key === tab);
      });
    }

    elements.tabButtons.forEach((button, index) => {
      button.addEventListener('click', () => {
        setActiveTab(button.dataset.tab);
      });
      button.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowRight' || event.key === 'ArrowLeft') {
          event.preventDefault();
          const direction = event.key === 'ArrowRight' ? 1 : -1;
          const nextIndex = (index + direction + elements.tabButtons.length) % elements.tabButtons.length;
          const nextButton = elements.tabButtons[nextIndex];
          nextButton.focus();
          setActiveTab(nextButton.dataset.tab);
        }
      });
    });

    elements.languageButtons.forEach((button) => {
      button.addEventListener('click', () => {
        const lang = button.dataset.lang;
        if (lang && lang !== state.language) {
          applyLanguage(lang);
        }
      });
    });

    elements.leaderboardModeFilter.addEventListener('change', () => {
      applyLeaderboardFilters();
    });

    elements.leaderboardMapFilter.addEventListener('change', () => {
      applyLeaderboardFilters();
    });

    elements.leaderboardPlayerSearch.addEventListener('input', () => {
      applyLeaderboardFilters();
    });

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
        if (state.activeTab === 'leaderboard' && !elements.leaderboardPlayerSearch.disabled) {
          elements.leaderboardPlayerSearch.focus();
        } else {
          if (state.activeTab !== 'matches') {
            setActiveTab('matches');
          }
          elements.searchInput.focus();
        }
      }
    });

    applyLanguage(state.language);
    setStatus('status.loadingMatches');
    setLeaderboardStatus('leaderboard.status.waiting');
    setActiveTab(state.activeTab);
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
