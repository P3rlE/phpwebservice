<?php
// Lightweight Ladder API for simple webspace deployments.
// Store match payloads as JSON files under data/.

declare(strict_types=1);

const DATA_DIR = __DIR__ . '/data';
const ARENA_DIR = __DIR__ . '/arena';
const DEFAULT_MASTER_HOST = 'master.ioquake3.org';

const SECONDARY_MASTER_HOST = 'dpmaster.deathmask.net';
const DEFAULT_MASTER_PORT = 27950;
const DEFAULT_MASTER_TIMEOUT = 2;
const DEFAULT_MASTER_LIMIT = 64;
const DEFAULT_MASTER_GAME = 'q3rally';
const DEFAULT_MASTER_SERVERS = [
    [
        'host' => DEFAULT_MASTER_HOST,
        'port' => DEFAULT_MASTER_PORT,
    ],
    [
        'host' => SECONDARY_MASTER_HOST,
        'port' => DEFAULT_MASTER_PORT,
    ],
];

const MASTER_GAMETYPE_LABELS = [
    'gametype_0' => 'Free For All',
    'gametype_1' => 'Tournament',
    'gametype_2' => 'Single Player',
    'gametype_3' => 'Team Deathmatch',
    'gametype_4' => 'Capture The Flag',
    'gametype_5' => 'One Flag CTF',
    'gametype_6' => 'Overload',
    'gametype_7' => 'Harvester',
    'gametype_8' => 'Domination',
    'gametype_9' => 'Double Domination',
    'gametype_10' => 'Elimination',
    'gametype_11' => 'CTF Elimination',
    'gametype_12' => 'Last Man Standing',
    'gt_racing' => 'Racing',
    'gt_racing_dm' => 'Racing Deathmatch',
    'gt_derby' => 'Demolition Derby',
    'gt_lcs' => 'Last Car Standing',
    'gt_elimination' => 'Elimination',
    'gt_deathmatch' => 'Deathmatch',
    'gt_team' => 'Team Deathmatch',
    'gt_team_racing' => 'Team Racing',
    'gt_team_racing_dm' => 'Team Racing Deathmatch',
    'gt_ctf' => 'Capture the Flag',
    'gt_ctf4' => '4-Team CTF',
    'gt_domination' => 'Domination',
];

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
    $normalized = $path;
    if ($script !== '' && strpos($normalized, $script) === 0) {
        $normalized = substr($normalized, strlen($script));
    }
    $normalized = trim($normalized, '/');
    $view = $normalized === 'servers' ? 'servers' : 'ladder';
    $isFrontendRoute = ($view === 'ladder' && ($path === '' || $path === '/' || $path === $script)) || $view === 'servers';

    if ($method === 'GET' && $isFrontendRoute && (strpos($accept, 'application/json') === false)) {
        $apiBase = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
        $serversBase = rtrim($apiBase, '/') . '/servers';
        $assetBase = rtrim(str_replace('\\', '/', dirname($apiBase)), '/');
        $assetPrefix = $assetBase === '' ? '' : $assetBase;
        $flagDe = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA1IDMiPjxyZWN0IHdpZHRoPSI1IiBoZWlnaHQ9IjMiIGZpbGw9IiNmZmNlMDAiLz48cmVjdCB3aWR0aD0iNSIgaGVpZ2h0PSIyIiBmaWxsPSIjZGQwMDAwIi8+PHJlY3Qgd2lkdGg9IjUiIGhlaWdodD0iMSIgZmlsbD0iIzAwMCIvPjwvc3ZnPg==';
        $flagEn = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA2MCAzMCI+PGNsaXBQYXRoIGlkPSJhIj48cGF0aCBkPSJNMCAwaDYwdjMwSDB6Ii8+PC9jbGlwUGF0aD48Y2xpcFBhdGggaWQ9ImIiPjxwYXRoIGQ9Ik0zMCAxNUgwdjE1bDYwLTMwSDBWMGw2MCAzMEgzMHYxNUg2MFYwSDMwdjE1eiIvPjwvY2xpcFBhdGg+PGcgY2xpcC1wYXRoPSJ1cmwoI2EpIj48cGF0aCBkPSJNMCAwaDYwdjMwSDB6IiBmaWxsPSIjMDEyMTY5Ii8+PHBhdGggZD0iTTAgMGw2MCAzME02MCAwTDAgMzAiIHN0cm9rZT0iI2ZmZiIgc3Ryb2tlLXdpZHRoPSI2IiBjbGlwLXBhdGg9InVybCgjYikiLz48cGF0aCBkPSJNMCAwbDYwIDMwTTYwIDBMMCAzMCIgc3Ryb2tlPSIjQzgxMDJFIiBzdHJva2Utd2lkdGg9IjQiIGNsaXAtcGF0aD0idXJsKCNiKSIvPjxwYXRoIGQ9Ik0zMCAwdjMwTTAgMTVoNjAiIHN0cm9rZT0iI2ZmZiIgc3Ryb2tlLXdpZHRoPSIxMCIvPjxwYXRoIGQ9Ik0zMCAwdjMwTTAgMTVoNjAiIHN0cm9rZT0iI0M4MTAyRSIgc3Ryb2tlLXdpZHRoPSI2Ii8+PC9nPjwvc3ZnPg==';
        header('Content-Type: text/html; charset=UTF-8');
        if ($view === 'servers') {
        ?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Q3Rally Serverbrowser</title>
  <meta name="description" content="Live-Übersicht der erreichbaren Q3Rally-Server von master.ioquake3.org und dpmaster.deathmask.net.">
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
      font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Ubuntu, Cantarell, 'Apple Color Emoji', 'Segoe UI Emoji', 'Noto Color Emoji', sans-serif;
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
      width: min(1280px, 100%);
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
      display: inline-flex;
      align-items: baseline;
      gap: 10px;
    }

    .badge-beta {
      display: inline-flex;
      align-items: center;
      padding: 2px 10px 4px;
      border-radius: 999px;
      background: rgba(93, 139, 255, 0.18);
      border: 1px solid rgba(93, 139, 255, 0.45);
      color: var(--accent);
      font-size: 0.75rem;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      font-weight: 600;
    }

    .hero p {
      margin: 0;
      color: var(--text-muted);
      line-height: 1.6;
      max-width: 52ch;
    }

    .hero-actions {
      display: flex;
      flex-direction: column;
      align-items: flex-end;
      gap: 12px;
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
      display: inline-flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      line-height: 1;
      transition: transform 120ms ease, box-shadow 120ms ease, background 120ms ease;
    }

    .language-button img {
      display: block;
      width: 26px;
      height: 26px;
      object-fit: contain;
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

    .main-nav {
      display: inline-flex;
      flex-wrap: wrap;
      gap: 8px;
      padding: 6px;
      border-radius: 16px;
      background: rgba(255, 255, 255, 0.06);
      border: 1px solid rgba(255, 255, 255, 0.08);
    }

    .nav-link {
      color: var(--text-muted);
      text-decoration: none;
      font-weight: 600;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      font-size: 0.78rem;
      padding: 8px 16px;
      border-radius: 12px;
      transition: background 140ms ease, color 140ms ease, box-shadow 140ms ease;
    }

    .nav-link:hover {
      color: var(--text);
      background: rgba(255, 255, 255, 0.08);
    }

    .nav-link.active {
      color: var(--text);
      background: var(--accent-soft);
      box-shadow: inset 0 0 0 1px rgba(93, 139, 255, 0.45);
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
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
    button:not(.tab-button) {
      width: 100%;
      padding: 12px 14px;
      border-radius: 14px;
      border: 1px solid var(--border);
      background: rgba(8, 12, 24, 0.4);
      color: inherit;
      font: inherit;
    }

    button.primary {
      background: linear-gradient(135deg, rgba(93, 139, 255, 0.85), rgba(99, 122, 255, 0.7));
      border: 1px solid rgba(93, 139, 255, 0.6);
      color: #fff;
      font-weight: 600;
      cursor: pointer;
      transition: transform 120ms ease, box-shadow 120ms ease;
    }

    button.primary:hover {
      transform: translateY(-1px);
      box-shadow: 0 10px 24px rgba(32, 52, 120, 0.45);
    }

    button.primary:disabled {
      opacity: 0.6;
      cursor: not-allowed;
      transform: none;
      box-shadow: none;
    }

    .status {
      margin: 0;
      font-size: 0.9rem;
      color: var(--text-muted);
    }

    .empty-state {
      margin: 0;
      font-size: 0.95rem;
      color: var(--text-muted);
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
      vertical-align: top;
    }

    table.leaderboard-table td strong {
      font-weight: 600;
      font-size: 1rem;
    }

    table.leaderboard-table tbody tr:nth-child(even) {
      background: rgba(255, 255, 255, 0.02);
    }

    table.leaderboard-table tbody tr:last-child td {
      border-bottom: none;
    }

    .meta {
      display: flex;
      flex-direction: column;
      gap: 4px;
      color: var(--text-muted);
      font-size: 0.85rem;
    }

    .tag {
      display: inline-flex;
      align-items: center;
      padding: 2px 10px 3px;
      border-radius: 999px;
      background: rgba(255, 255, 255, 0.08);
      border: 1px solid rgba(255, 255, 255, 0.18);
      color: var(--text-muted);
      font-size: 0.72rem;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      font-weight: 600;
      width: fit-content;
    }

    .mono {
      font-family: 'JetBrains Mono', 'Fira Code', 'SFMono-Regular', Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
      font-size: 0.88rem;
      letter-spacing: 0.02em;
    }

    @media (max-width: 900px) {
      .hero {
        gap: 20px;
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

      .hero-actions {
        align-items: center;
      }

      .hero-stats {
        justify-content: center;
      }

      .hero-stats .stat {
        text-align: center;
      }

      table.leaderboard-table {
        min-width: 100%;
      }
    }
  </style>
</head>
<body>
  <main class="page">
    <section class="panel hero">
      <div class="hero-top">
        <div class="hero-brand">
          <img src="<?= htmlspecialchars($assetPrefix . '/logo.png', ENT_QUOTES); ?>" alt="Q3Rally Logo" onerror="this.style.display='none'">
          <div class="hero-info">
            <h1 data-i18n-html="servers.hero.title">Q3Rally Serverbrowser <span class="badge-beta">beta</span></h1>
            <p data-i18n="servers.hero.description">Fragt master.ioquake3.org und dpmaster.deathmask.net nach Q3Rally-Servern ab und zeigt erreichbare Spielserver samt Spielern, Map und Modus.</p>
          </div>
        </div>
        <div class="hero-actions">
          <nav class="main-nav" aria-label="Bereiche" data-i18n-aria-label="nav.label">
            <a class="nav-link" href="<?= htmlspecialchars($apiBase, ENT_QUOTES); ?>" data-i18n="nav.matches">Matches</a>
            <a class="nav-link active" aria-current="page" href="<?= htmlspecialchars($serversBase, ENT_QUOTES); ?>" data-i18n="nav.servers">Serverbrowser</a>
          </nav>
          <div class="language-toggle" role="group" aria-label="Sprachauswahl" data-i18n-aria-label="language.toggleLabel">
            <button class="language-button active" type="button" data-lang="de" aria-label="Deutsch" data-i18n-aria-label="language.deLabel" title="Deutsch" data-i18n-title="language.deLabel"><img src="<?= htmlspecialchars($flagDe, ENT_QUOTES); ?>" alt=""></button>
            <button class="language-button" type="button" data-lang="en" aria-label="Englisch" data-i18n-aria-label="language.enLabel" title="Englisch" data-i18n-title="language.enLabel"><img src="<?= htmlspecialchars($flagEn, ENT_QUOTES); ?>" alt=""></button>
          </div>
        </div>
      </div>
      <p class="status" id="statusMessage"></p>
      <dl class="hero-stats">
        <div class="stat"><dt data-i18n="servers.stats.total">Server</dt><dd id="stat-servers">–</dd></div>
        <div class="stat"><dt data-i18n="servers.stats.lastUpdate">Letztes Update</dt><dd id="stat-last">–</dd></div>
        <div class="stat"><dt data-i18n="servers.stats.modes">Modi</dt><dd id="stat-modes">–</dd></div>
        <div class="stat"><dt data-i18n="servers.stats.players">Spieler online</dt><dd id="stat-players">–</dd></div>
      </dl>
    </section>

    <section class="panel">
      <div class="controls">
        <div>
          <label for="filterSearch" data-i18n="servers.filters.search.label">Suche</label>
          <input type="search" id="filterSearch" data-i18n-placeholder="servers.filters.search.placeholder" placeholder="Server, Map oder Spieler…">
        </div>
        <div>
          <label for="filterMode" data-i18n="servers.filters.mode.label">Spielmodus</label>
          <select id="filterMode">
            <option value="all" data-i18n="servers.filters.mode.all">Alle Modi</option>
          </select>
        </div>
        <div>
          <label for="refreshButton" data-i18n="servers.actions.refreshLabel">Aktualisieren</label>
          <button type="button" id="refreshButton" class="primary" data-i18n="servers.actions.refresh">Neu laden</button>
        </div>
      </div>
    </section>

    <section class="panel">
      <div class="table-wrapper">
        <table class="leaderboard-table">
          <thead>
            <tr>
              <th scope="col" data-i18n="servers.table.server">Server</th>
              <th scope="col" data-i18n="servers.table.mode">Modus</th>
              <th scope="col" data-i18n="servers.table.players">Spieler</th>
              <th scope="col" data-i18n="servers.table.ping">Ping</th>
            </tr>
          </thead>
          <tbody id="serverTableBody"></tbody>
        </table>
      </div>
      <p class="empty-state" id="serverEmpty" hidden data-i18n="servers.empty">Keine Server gefunden. Passe die Filter an oder lade erneut.</p>
    </section>
  </main>

  <script>
    const API_BASE = <?= json_encode($apiBase, JSON_UNESCAPED_SLASHES); ?>;
    const MODE_TRANSLATIONS = {
      de: {
        gametype_0: 'Jeder gegen Jeden',
        gametype_1: 'Turnier',
        gametype_2: 'Einzelspieler',
        gametype_3: 'Team-Deathmatch',
        gametype_4: 'Capture the Flag',
        gametype_5: 'One Flag CTF',
        gametype_6: 'Overload',
        gametype_7: 'Harvester',
        gametype_8: 'Domination',
        gametype_9: 'Double Domination',
        gametype_10: 'Elimination',
        gametype_11: 'CTF Elimination',
        gametype_12: 'Last Man Standing',
        gt_racing: 'Rennen',
        gt_racing_dm: 'Deathmatch Rennen',
        gt_derby: 'Demolition Derby',
        gt_lcs: 'Last Car Standing',
        gt_elimination: 'Elimination',
        gt_deathmatch: 'Deathmatch',
        gt_team: 'Team Deathmatch',
        gt_team_racing: 'Team Racing',
        gt_team_racing_dm: 'Team Racing Deathmatch',
        gt_ctf: 'Capture the Flag',
        gt_ctf4: '4-Teams-CTF',
        gt_domination: 'Domination'
      },
      en: {
        gametype_0: 'Free For All',
        gametype_1: 'Tournament',
        gametype_2: 'Single Player',
        gametype_3: 'Team Deathmatch',
        gametype_4: 'Capture the Flag',
        gametype_5: 'One Flag CTF',
        gametype_6: 'Overload',
        gametype_7: 'Harvester',
        gametype_8: 'Domination',
        gametype_9: 'Double Domination',
        gametype_10: 'Elimination',
        gametype_11: 'CTF Elimination',
        gametype_12: 'Last Man Standing',
        gt_racing: 'Racing',
        gt_racing_dm: 'Racing Deathmatch',
        gt_derby: 'Demolition Derby',
        gt_lcs: 'Last Car Standing',
        gt_elimination: 'Elimination',
        gt_deathmatch: 'Deathmatch',
        gt_team: 'Team Deathmatch',
        gt_team_racing: 'Team Racing',
        gt_team_racing_dm: 'Team Racing Deathmatch',
        gt_ctf: 'Capture the Flag',
        gt_ctf4: '4-Team CTF',
        gt_domination: 'Domination'
      }
    };

    const I18N = {
      de: {

        'meta.title': 'Q3Rally Serverbrowser',
        'meta.description': 'Live-Übersicht der erreichbaren Q3Rally-Server von master.ioquake3.org und dpmaster.deathmask.net.',
        'servers.hero.title': 'Q3Rally Serverbrowser <span class="badge-beta">beta</span>',
        'servers.hero.description': 'Fragt master.ioquake3.org und dpmaster.deathmask.net nach Q3Rally-Servern ab und zeigt erreichbare Spielserver samt Spielern, Map und Modus.',

        'language.toggleLabel': 'Sprachauswahl',
        'language.deLabel': 'Deutsch',
        'language.enLabel': 'Englisch',
        'nav.label': 'Bereiche',
        'nav.matches': 'Matches',
        'nav.servers': 'Serverbrowser',
        'servers.stats.total': 'Server',
        'servers.stats.lastUpdate': 'Letztes Update',
        'servers.stats.modes': 'Modi',
        'servers.stats.players': 'Spieler online',
        'servers.filters.search.label': 'Suche',
        'servers.filters.search.placeholder': 'Server, Map oder Spieler…',
        'servers.filters.mode.label': 'Spielmodus',
        'servers.filters.mode.all': 'Alle Modi',
        'servers.actions.refreshLabel': 'Aktualisieren',
        'servers.actions.refresh': 'Neu laden',

        'servers.status.loading': 'Lade Q3Rally-Serverliste…',
        'servers.status.error': 'Masterserver konnten nicht abgefragt werden.',
        'servers.empty': 'Keine Q3Rally-Server gefunden. Passe die Filter an oder versuche es erneut.',

        'servers.table.server': 'Server',
        'servers.table.mode': 'Modus',
        'servers.table.players': 'Spieler',
        'servers.table.ping': 'Ping',
        'servers.table.unknown': 'Unbekannter Server',
        'servers.table.noPlayers': 'Keine Spieler erfasst',
        'servers.table.playersShort': '{current}/{max} Spieler',
        'servers.table.playersOnly': '{current} Spieler',
        'servers.table.playerList': 'Spieler: {list}',
        'servers.table.pingValue': '{ping} ms',
        'servers.table.mod': 'Mod: {mod}'
      },
      en: {

        'meta.title': 'Q3Rally Server Browser',
        'meta.description': 'Live overview of reachable Q3Rally servers from master.ioquake3.org and dpmaster.deathmask.net.',
        'servers.hero.title': 'Q3Rally Server Browser <span class="badge-beta">beta</span>',
        'servers.hero.description': 'Queries master.ioquake3.org and dpmaster.deathmask.net for Q3Rally servers and lists reachable game servers including players, map and mode.',

        'language.toggleLabel': 'Language selection',
        'language.deLabel': 'German',
        'language.enLabel': 'English',
        'nav.label': 'Sections',
        'nav.matches': 'Matches',
        'nav.servers': 'Server Browser',
        'servers.stats.total': 'Servers',
        'servers.stats.lastUpdate': 'Last update',
        'servers.stats.modes': 'Modes',
        'servers.stats.players': 'Players online',
        'servers.filters.search.label': 'Search',
        'servers.filters.search.placeholder': 'Server, map or player…',
        'servers.filters.mode.label': 'Game mode',
        'servers.filters.mode.all': 'All modes',
        'servers.actions.refreshLabel': 'Refresh',
        'servers.actions.refresh': 'Reload',

        'servers.status.loading': 'Loading the Q3Rally server list…',
        'servers.status.error': 'Failed to query the master servers.',
        'servers.empty': 'No Q3Rally servers responded. Adjust the filters or try again.',

        'servers.table.server': 'Server',
        'servers.table.mode': 'Mode',
        'servers.table.players': 'Players',
        'servers.table.ping': 'Ping',
        'servers.table.unknown': 'Unknown server',
        'servers.table.noPlayers': 'No players recorded',
        'servers.table.playersShort': '{current}/{max} players',
        'servers.table.playersOnly': '{current} players',
        'servers.table.playerList': 'Players: {list}',
        'servers.table.pingValue': '{ping} ms',
        'servers.table.mod': 'Mod: {mod}'
      }
    };

    const state = {
      language: 'de',
      servers: [],
      search: '',
      mode: 'all',
      lastUpdated: null,
      summary: null,
      autoRefreshHandle: null
    };

    const elements = {
      languageButtons: Array.from(document.querySelectorAll('.language-button')),
      status: document.getElementById('statusMessage'),
      tableBody: document.getElementById('serverTableBody'),
      empty: document.getElementById('serverEmpty'),
      search: document.getElementById('filterSearch'),
      mode: document.getElementById('filterMode'),
      refresh: document.getElementById('refreshButton'),
      statServers: document.getElementById('stat-servers'),
      statLast: document.getElementById('stat-last'),
      statModes: document.getElementById('stat-modes'),
      statPlayers: document.getElementById('stat-players')
    };

    function getLocale(lang = state.language) {
      return lang === 'de' ? 'de-DE' : 'en-US';
    }

    let dateFormatter = new Intl.DateTimeFormat(getLocale(), { dateStyle: 'medium', timeStyle: 'short' });

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
      document.title = t('meta.title');
      const description = document.querySelector('meta[name="description"]');
      if (description) {
        description.setAttribute('content', t('meta.description'));
      }
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

    function setLanguage(lang) {
      if (state.language === lang) {
        return;
      }
      state.language = lang;
      document.documentElement.lang = lang === 'de' ? 'de' : 'en';
      dateFormatter = new Intl.DateTimeFormat(getLocale(), { dateStyle: 'medium', timeStyle: 'short' });
      applyStaticTranslations();
      updateLanguageButtons();
      updateModeOptions();
      render();
    }

    function escapeHtml(value) {
      if (value == null) {
        return '';
      }
      return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    }

    function normalizeServer(raw) {
      const name = typeof raw.name === 'string' && raw.name.trim() !== '' ? raw.name.trim() : null;
      const address = typeof raw.address === 'string' && raw.address.trim() !== '' ? raw.address.trim() : null;
      const ip = typeof raw.ip === 'string' && raw.ip.trim() !== '' ? raw.ip.trim() : null;
      const port = Number.isFinite(raw.port) ? raw.port : null;
      const mode = typeof raw.mode === 'string' && raw.mode.trim() !== '' ? raw.mode.trim() : null;
      const modeLabel = typeof raw.modeName === 'string' && raw.modeName.trim() !== '' ? raw.modeName.trim() : null;
      const map = typeof raw.map === 'string' && raw.map.trim() !== '' ? raw.map.trim() : null;
      const mod = typeof raw.mod === 'string' && raw.mod.trim() !== '' ? raw.mod.trim() : null;
      const playerCount = Number.isFinite(raw.playerCount)
        ? raw.playerCount
        : Number.isFinite(raw.numPlayers)
          ? raw.numPlayers
          : null;
      const maxPlayers = Number.isFinite(raw.maxPlayers) ? raw.maxPlayers : null;
      const ping = Number.isFinite(raw.ping) ? raw.ping : null;
      const respondedAt = typeof raw.respondedAt === 'string' && raw.respondedAt.trim() !== '' ? raw.respondedAt : null;
      const players = Array.isArray(raw.players)
        ? raw.players
            .filter((entry) => typeof entry === 'string' && entry.trim() !== '')
            .map((entry) => entry.trim())
        : [];
      const playerDetails = Array.isArray(raw.playerDetails)
        ? raw.playerDetails
            .filter((entry) => entry && typeof entry === 'object')
            .map((entry) => ({
              name: typeof entry.name === 'string' && entry.name.trim() !== '' ? entry.name.trim() : null,
              score: Number.isFinite(Number(entry.score)) ? Number(entry.score) : null,
              ping: Number.isFinite(Number(entry.ping)) ? Number(entry.ping) : null
            }))
        : [];
      return {
        name,
        address,
        ip,
        port,
        mode,
        modeLabel,
        map,
        mod,
        playerCount,
        maxPlayers,
        players,
        playerDetails,
        ping,
        respondedAt
      };
    }

    function setStatus(key, params = {}) {
      const message = key ? t(key, params) : '';
      elements.status.textContent = message;
    }

    function updateStats(filteredServers) {
      elements.statServers.textContent = filteredServers.length > 0 ? String(filteredServers.length) : '–';
      if (state.lastUpdated) {
        const date = new Date(state.lastUpdated);
        if (!Number.isNaN(date.getTime())) {
          elements.statLast.textContent = dateFormatter.format(date);
        } else {
          elements.statLast.textContent = '–';
        }
      } else {
        elements.statLast.textContent = '–';
      }

      const uniqueModes = new Set();
      filteredServers.forEach((server) => {
        if (server.mode) {
          uniqueModes.add(server.mode);
        }
      });
      elements.statModes.textContent = uniqueModes.size > 0 ? String(uniqueModes.size) : '–';

      let totalPlayers = 0;
      filteredServers.forEach((server) => {
        if (Number.isFinite(server.playerCount)) {
          totalPlayers += server.playerCount;
        } else {
          totalPlayers += server.players.length;
        }
      });
      elements.statPlayers.textContent = totalPlayers > 0 ? String(totalPlayers) : '–';
    }

    function humanizeMode(mode, fallbackLabel = null) {
      if (!mode) {
        return fallbackLabel || '';
      }
      const translations = MODE_TRANSLATIONS[state.language] || {};
      if (Object.prototype.hasOwnProperty.call(translations, mode)) {
        return translations[mode];
      }
      const fallback = MODE_TRANSLATIONS.en || {};
      if (Object.prototype.hasOwnProperty.call(fallback, mode)) {
        return fallback[mode];
      }
      if (fallbackLabel) {
        return fallbackLabel;
      }
      return mode;
    }

    function formatPlayers(server) {
      if (server.playerCount == null && server.players.length === 0) {
        return t('servers.table.noPlayers');
      }
      const current = server.playerCount != null ? server.playerCount : server.players.length;
      if (server.maxPlayers != null && server.maxPlayers > 0) {
        return t('servers.table.playersShort', { current, max: server.maxPlayers });
      }
      return t('servers.table.playersOnly', { current });
    }

    function formatPing(server) {
      if (!Number.isFinite(server.ping)) {
        return '–';
      }
      const value = Math.max(0, Math.round(server.ping));
      return t('servers.table.pingValue', { ping: value });
    }

    function render() {
      const searchTerm = state.search.trim().toLowerCase();
      const filtered = state.servers.filter((server) => {
        if (state.mode !== 'all' && server.mode !== state.mode) {
          return false;
        }
        if (searchTerm === '') {
          return true;
        }
        const haystack = [
          server.name || '',
          server.address || '',
          server.map || '',
          server.modeLabel || '',
          server.mode || '',
          server.mod || '',
          server.players.join(' ')
        ]
          .join(' ')
          .toLowerCase();
        return haystack.includes(searchTerm);
      });

      elements.tableBody.innerHTML = '';
      if (filtered.length === 0) {
        elements.empty.hidden = false;
      } else {
        elements.empty.hidden = true;
        filtered
          .slice()
          .sort((a, b) => {
            const pingA = Number.isFinite(a.ping) ? a.ping : Number.POSITIVE_INFINITY;
            const pingB = Number.isFinite(b.ping) ? b.ping : Number.POSITIVE_INFINITY;
            if (pingA !== pingB) {
              return pingA - pingB;
            }
            const nameA = a.name || '';
            const nameB = b.name || '';
            return nameA.localeCompare(nameB, getLocale(), { sensitivity: 'base' });
          })
          .forEach((server) => {
            const row = document.createElement('tr');
            const displayName = server.name || t('servers.table.unknown');
            const displayMode = humanizeMode(server.mode, server.modeLabel);
            const players = formatPlayers(server);
            const ping = formatPing(server);
            const details = [];
            if (server.address) {
              details.push(`<span class="mono">${escapeHtml(server.address)}</span>`);
            } else if (server.ip && server.port != null) {
              details.push(`<span class="mono">${escapeHtml(`${server.ip}:${server.port}`)}</span>`);
            }
            if (server.map) {
              details.push(`<span>${escapeHtml(server.map)}</span>`);
            }
            if (server.mod) {
              details.push(`<span>${escapeHtml(t('servers.table.mod', { mod: server.mod }))}</span>`);
            }
            const detailHtml = details.length > 0 ? `<div class="meta">${details.join('')}</div>` : '';
            const playerMeta = server.players.length > 0
              ? `<div class="meta">${escapeHtml(t('servers.table.playerList', { list: server.players.join(', ') }))}</div>`
              : '';
            row.innerHTML = `
              <td>
                <strong>${escapeHtml(displayName)}</strong>
                ${detailHtml}
              </td>
              <td>${escapeHtml(displayMode)}</td>
              <td>
                ${escapeHtml(players)}
                ${playerMeta}
              </td>
              <td>${escapeHtml(ping)}</td>
            `;
            elements.tableBody.appendChild(row);
          });
      }

      updateStats(filtered);
    }

    async function fetchServers() {
      try {
        setStatus('servers.status.loading');
        const response = await fetch(`${API_BASE}/servers`, {
          headers: {
            Accept: 'application/json'
          }
        });
        if (!response.ok) {
          throw new Error('Unexpected response');
        }
        const payload = await response.json();
        const list = Array.isArray(payload.servers) ? payload.servers : [];
        state.servers = list.map(normalizeServer);
        state.summary = payload.summary && typeof payload.summary === 'object' ? payload.summary : null;
        state.lastUpdated = typeof payload.generatedAt === 'string' ? payload.generatedAt : new Date().toISOString();
        updateModeOptions();
        setStatus('');
        render();
      } catch (error) {
        console.error(error);
        setStatus('servers.status.error');
      }
    }

    function updateModeOptions() {
      const existing = new Set(['all']);
      const currentValue = elements.mode.value;
      elements.mode.innerHTML = '';
      const allOption = document.createElement('option');
      allOption.value = 'all';
      allOption.textContent = t('servers.filters.mode.all');
      elements.mode.appendChild(allOption);
      const modes = [];
      const labels = new Map();
      state.servers.forEach((server) => {
        if (!server.mode) {
          return;
        }
        if (!labels.has(server.mode)) {
          labels.set(server.mode, humanizeMode(server.mode, server.modeLabel));
        }
        if (!existing.has(server.mode)) {
          existing.add(server.mode);
          modes.push(server.mode);
        }
      });
      modes
        .sort((a, b) => {
          const labelA = labels.get(a) || humanizeMode(a);
          const labelB = labels.get(b) || humanizeMode(b);
          return labelA.localeCompare(labelB, getLocale());
        })
        .forEach((mode) => {
          const option = document.createElement('option');
          option.value = mode;
          option.textContent = labels.get(mode) || humanizeMode(mode);
          elements.mode.appendChild(option);
        });
      if (existing.has(currentValue)) {
        elements.mode.value = currentValue;
        state.mode = currentValue;
      } else {
        state.mode = 'all';
      }
    }

    function setupEventListeners() {
      elements.languageButtons.forEach((button) => {
        button.addEventListener('click', () => {
          const lang = button.dataset.lang;
          if (lang) {
            setLanguage(lang);
          }
        });
      });

      elements.search.addEventListener('input', (event) => {
        state.search = event.target.value || '';
        render();
      });

      elements.mode.addEventListener('change', () => {
        state.mode = elements.mode.value;
        render();
      });

      elements.refresh.addEventListener('click', () => {
        fetchServers();
      });
    }

    function setupAutoRefresh() {
      if (state.autoRefreshHandle) {
        clearInterval(state.autoRefreshHandle);
      }
      state.autoRefreshHandle = setInterval(() => {
        if (document.visibilityState === 'visible') {
          fetchServers();
        }
      }, 60000);
    }

    applyStaticTranslations();
    updateLanguageButtons();
    setupEventListeners();
    setupAutoRefresh();
    fetchServers();
  </script>
</body>
</html>
<?php
            exit;
        }
        ?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Q3Rally Ladder Monitor beta</title>
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
      font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Ubuntu, Cantarell, 'Apple Color Emoji', 'Segoe UI Emoji', 'Noto Color Emoji', sans-serif;
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
      width: min(1280px, 100%);
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
      display: inline-flex;
      align-items: baseline;
      gap: 10px;
    }

    .badge-beta {
      display: inline-flex;
      align-items: center;
      padding: 2px 10px 4px;
      border-radius: 999px;
      background: rgba(93, 139, 255, 0.18);
      border: 1px solid rgba(93, 139, 255, 0.45);
      color: var(--accent);
      font-size: 0.75rem;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      font-weight: 600;
    }

    .hero p {
      margin: 0;
      color: var(--text-muted);
      line-height: 1.6;

      max-width: 52ch;
    }

    .hero-actions {
      display: flex;
      flex-direction: column;
      align-items: flex-end;
      gap: 12px;
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
      display: inline-flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      line-height: 1;
      transition: transform 120ms ease, box-shadow 120ms ease, background 120ms ease;
    }

    .language-button img {
      display: block;
      width: 26px;
      height: 26px;
      object-fit: contain;
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

    .main-nav {
      display: inline-flex;
      flex-wrap: wrap;
      gap: 8px;
      padding: 6px;
      border-radius: 16px;
      background: rgba(255, 255, 255, 0.06);
      border: 1px solid rgba(255, 255, 255, 0.08);
    }

    .nav-link {
      color: var(--text-muted);
      text-decoration: none;
      font-weight: 600;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      font-size: 0.78rem;
      padding: 8px 16px;
      border-radius: 12px;
      transition: background 140ms ease, color 140ms ease, box-shadow 140ms ease;
    }

    .nav-link:hover {
      color: var(--text);
      background: rgba(255, 255, 255, 0.08);
    }

    .nav-link.active {
      color: var(--text);
      background: var(--accent-soft);
      box-shadow: inset 0 0 0 1px rgba(93, 139, 255, 0.45);
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
      flex-wrap: wrap;
      gap: 8px;
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
      padding: 8px 14px;
      border-radius: 12px;
      font-weight: 600;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      font-size: 0.78rem;
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

    #modePanels {
      display: flex;
      flex-direction: column;
    }

    .mode-panel {
      display: none;
      flex-direction: column;
      gap: 24px;
    }

    .mode-panel.active {
      display: flex;
    }

    .mode-controls {
      display: flex;
      flex-wrap: wrap;
      gap: 18px;
      align-items: flex-end;
    }

    .mode-controls label {
      display: block;
      font-size: 0.75rem;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: var(--text-muted);
      margin-bottom: 6px;
    }

    .mode-controls select {
      appearance: none;
      border: 1px solid var(--border);
      border-radius: 12px;
      background: rgba(9, 12, 20, 0.85);
      color: var(--text);
      padding: 10px 14px;
      font-size: 0.95rem;
      min-width: 220px;
    }

    .mode-layout {
      display: grid;
      grid-template-columns: minmax(260px, 380px) minmax(0, 1fr);
      gap: 24px;
      align-items: flex-start;
    }

    .mode-levelshot {
      display: flex;
      flex-direction: column;
      gap: 12px;
      align-items: center;
      padding: 16px;
      border-radius: 18px;
      background: rgba(10, 15, 26, 0.68);
      border: 1px solid rgba(255, 255, 255, 0.08);
    }

    .mode-levelshot img {
      width: 100%;
      border-radius: 12px;
      border: 1px solid rgba(255, 255, 255, 0.08);
      background: rgba(255, 255, 255, 0.03);
      aspect-ratio: 4 / 3;
      object-fit: cover;
    }

    .mode-levelshot figcaption {
      font-size: 0.85rem;
      color: var(--text-muted);
      text-align: center;
    }

    .mode-levelshot-fallback {
      margin: 0;
      font-size: 0.85rem;
      color: var(--text-muted);
      text-align: center;
    }

    .mode-meta {
      display: grid;
      gap: 6px;
      width: 100%;
    }

    .mode-meta span {
      font-size: 0.85rem;
      color: var(--text-muted);
      text-transform: uppercase;
      letter-spacing: 0.08em;
    }

    .mode-meta strong {
      font-size: 1rem;
      color: var(--text);
    }

    .mode-table-wrapper {
      display: flex;
      flex-direction: column;
      gap: 14px;
    }

    .mode-table-wrapper h3 {
      margin: 0;
      font-size: 0.85rem;
      letter-spacing: 0.1em;
      text-transform: uppercase;
      color: var(--text-muted);
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

    table.leaderboard-table th:last-child,
    table.leaderboard-table td:last-child {
      white-space: nowrap;
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
      align-items: baseline;
      gap: 10px;
      flex-wrap: wrap;
    }

    .leaderboard-player span {
      font-size: 0.82rem;
      color: var(--text-muted);
    }

    .bot-tag {
      display: inline-flex;
      align-items: center;
      padding: 2px 10px 3px;
      border-radius: 999px;
      background: rgba(255, 255, 255, 0.08);
      border: 1px solid rgba(255, 255, 255, 0.18);
      color: var(--text-muted);
      font-size: 0.7rem;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      font-weight: 600;
      width: fit-content;
    }

    .leaderboard-value {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }

    .leaderboard-value span {
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
      .hero-actions {
        align-items: center;
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
    button:not(.tab-button) {
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
    button:not(.tab-button):focus {
      outline: 2px solid var(--accent);
      outline-offset: 2px;
    }

    button:not(.tab-button) {
      cursor: pointer;
      background: linear-gradient(135deg, var(--accent), rgba(125, 167, 255, 0.9));
      border: 1px solid rgba(93, 139, 255, 0.5);
      font-weight: 600;
      transition: transform 120ms ease, box-shadow 120ms ease;
    }

    button:not(.tab-button):hover {
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

      .tab-header {
        width: 100%;
        justify-content: center;
      }

      .mode-layout {
        grid-template-columns: 1fr;
      }

      .mode-controls select {
        min-width: 0;
        width: 100%;
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
          <img src="<?= htmlspecialchars($assetPrefix . '/logo.png', ENT_QUOTES); ?>" alt="Q3Rally Logo" onerror="this.style.display='none'">
          <div class="hero-info">
            <h1 data-i18n-html="hero.title">Q3Rally Ladder Monitor <span class="badge-beta">beta</span></h1>
            <p data-i18n-html="hero.description">Direkte Vorschau der besten Platzierungen je Spielmodus und Map. Wähle oben einen Modus und vergleiche die Top 10 pro Strecke – inklusive Levelshot-Vorschau.</p>
          </div>
        </div>
        <div class="hero-actions">
          <nav class="main-nav" aria-label="Bereiche" data-i18n-aria-label="nav.label">
            <a class="nav-link active" aria-current="page" href="<?= htmlspecialchars($apiBase, ENT_QUOTES); ?>" data-i18n="nav.matches">Matches</a>
            <a class="nav-link" href="<?= htmlspecialchars($serversBase, ENT_QUOTES); ?>" data-i18n="nav.servers">Serverbrowser</a>
          </nav>
            <div class="language-toggle" role="group" aria-label="Sprachauswahl" data-i18n-aria-label="language.toggleLabel">
              <button class="language-button active" type="button" data-lang="de" aria-label="Deutsch" data-i18n-aria-label="language.deLabel" title="Deutsch" data-i18n-title="language.deLabel"><img src="<?= htmlspecialchars($flagDe, ENT_QUOTES); ?>" alt=""></button>
              <button class="language-button" type="button" data-lang="en" aria-label="Englisch" data-i18n-aria-label="language.enLabel" title="Englisch" data-i18n-title="language.enLabel"><img src="<?= htmlspecialchars($flagEn, ENT_QUOTES); ?>" alt=""></button>
          </div>
        </div>
        <p class="empty-state" id="leaderboardEmpty" hidden data-i18n="matches.empty">Keine Bestzeiten gefunden. Lade weitere Renn-Matches oder passe die Filter an.</p>
      </div>

      <dl class="hero-stats">
        <div class="stat"><dt data-i18n="stats.matches">Matches</dt><dd id="stat-total">–</dd></div>
        <div class="stat"><dt data-i18n="stats.lastUpdate">Letztes Update</dt><dd id="stat-last">–</dd></div>
        <div class="stat"><dt data-i18n="stats.modes">Spielmodi</dt><dd id="stat-modes">–</dd></div>
        <div class="stat"><dt data-i18n="stats.players">Spieler erfasst</dt><dd id="stat-players">–</dd></div>
      </dl>
    </section>

    <section class="panel tabbed-panel">
      <div class="tab-header" id="modeTabs" role="tablist" aria-label="Spielmodi" data-i18n-aria-label="tabs.label"></div>
      <p class="status" id="modeStatus"></p>
      <div id="modePanels" class="mode-panels"></div>
    </section>

    <section class="panel">

      <h2 style="margin:0; font-size:1.05rem; letter-spacing:0.02em; text-transform:uppercase; color:var(--text-muted);" data-i18n="breakdown.heading">Modus-Verteilung</h2>

      <ul id="modeBreakdown"></ul>
    </section>
  </main>

  <script>
    const API_BASE = <?= json_encode($apiBase, JSON_UNESCAPED_SLASHES); ?>;

    const MODE_CONFIG = [
      { key: 'gt_racing', type: 'race' },
      { key: 'gt_racing_dm', type: 'race' },
      { key: 'gt_derby', type: 'objective' },
      { key: 'gt_lcs', type: 'objective' },
      { key: 'gt_elimination', type: 'objective' },
      { key: 'gt_deathmatch', type: 'deathmatch' },
      { key: 'gt_team', type: 'deathmatch' },
      { key: 'gt_team_racing', type: 'race' },
      { key: 'gt_team_racing_dm', type: 'race' },
      { key: 'gt_ctf', type: 'objective' },
      { key: 'gt_ctf4', type: 'objective' },
      { key: 'gt_domination', type: 'objective' }
    ];

    const LEVELSHOT_EXTENSIONS = ['webp', 'png', 'jpg', 'jpeg', 'tga'];
    const levelshotAssetCache = new Map();
    const levelshotMapCache = new Map();
    let levelshotRequestCounter = 0;

    const I18N = {
      de: {
        'meta.title': 'Q3Rally Ladder Monitor beta',
        'meta.description': 'Frontend zur Auswertung der gespeicherten Q3Rally-Ladder-Matches.',
        'hero.title': 'Q3Rally Ladder Monitor <span class="badge-beta">beta</span>',
        'hero.description': 'Direkte Vorschau der besten Platzierungen je Spielmodus und Map. Wähle oben einen Modus und vergleiche die Top 10 pro Strecke – inklusive Levelshot-Vorschau.',
        'language.toggleLabel': 'Sprachauswahl',
        'language.deLabel': 'Deutsch',
        'language.enLabel': 'Englisch',
        'nav.label': 'Bereiche',
        'nav.matches': 'Matches',
        'nav.servers': 'Serverbrowser',
        'tabs.label': 'Spielmodi',
        'tabs.racing': 'RACING LEADERBOARD',
        'tabs.deathmatch': 'DEATHMATCH LEADERBOARD',
        'tabs.elimination': 'ELIMINATION LEADERBOARD',
        'tabs.ctf': 'CTF LEADERBOARD',
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
        'filters.deathmatch.mode.label': 'Spielmodus',
        'filters.deathmatch.mode.all': 'Alle Deathmatch-Modi',
        'filters.deathmatch.map.label': 'Map',
        'filters.deathmatch.map.all': 'Alle Maps',
        'filters.deathmatch.player.label': 'Spielersuche',
        'filters.deathmatch.player.placeholder': 'Spielername…',
        'filters.elimination.mode.label': 'Spielmodus',
        'filters.elimination.mode.all': 'Alle Elimination-Modi',
        'filters.elimination.map.label': 'Map',
        'filters.elimination.map.all': 'Alle Maps',
        'filters.elimination.player.label': 'Spielersuche',
        'filters.elimination.player.placeholder': 'Spielername…',
        'filters.ctf.mode.label': 'Spielmodus',
        'filters.ctf.mode.all': 'Alle Objective-Modi',
        'filters.ctf.map.label': 'Map',
        'filters.ctf.map.all': 'Alle Maps',
        'filters.ctf.player.label': 'Spielersuche',
        'filters.ctf.player.placeholder': 'Spielername…',
        'filters.matches.mode.label': 'Spielmodus',
        'filters.matches.mode.all': 'Alle Modi',
        'filters.matches.search.label': 'Suche',
        'filters.matches.search.placeholder': 'Match-ID, Map oder Spieler…',
        'filters.matches.limit.label': 'Lade-Limit',
        'filters.matches.limit.all': 'Alle',
        'filters.matches.refresh': 'Aktualisieren',
        'matches.empty': 'Keine Bestzeiten gefunden. Lade weitere Renn-Matches oder passe die Filter an.',
        'leaderboard.headers.rank': 'Rang',
        'leaderboard.headers.player': 'Spieler',
        'leaderboard.headers.time': 'Zeit',
        'leaderboard.headers.best': 'Bestzeit',
        'leaderboard.headers.map': 'Map',
        'leaderboard.headers.mode': 'Modus',
        'leaderboard.headers.vehicle': 'Fahrzeug',
        'leaderboard.headers.recorded': 'Aufgestellt am',
        'leaderboard.player.botTag': 'Bot',
        'leaderboard.player.botTooltip': 'Bestzeit von einem Bot erzielt',
        'deathmatch.headers.rank': 'Rang',
        'deathmatch.headers.player': 'Spieler',
        'deathmatch.headers.kdr': 'K/D',
        'deathmatch.headers.kills': 'Kills',
        'deathmatch.headers.deaths': 'Tode',
        'deathmatch.headers.map': 'Map',
        'deathmatch.headers.mode': 'Modus',
        'deathmatch.headers.recorded': 'Aufgestellt am',
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
        'deathmatch.status.waiting': 'Warte auf Daten…',
        'deathmatch.status.loading': 'Lade K/D-Werte…',
        'deathmatch.status.noneData': 'Keine Deathmatch-Daten in den geladenen Matches gefunden.',
        'deathmatch.status.noFilterMatches': 'Keine Ergebnisse für die aktuellen Filter.',
        'deathmatch.status.count': '{displayed} von {total} K/D-Werten angezeigt.',
        'deathmatch.status.error': 'Fehler beim Laden: {message}',
        'deathmatch.empty.noData': 'Keine K/D-Werte gefunden. Lade weitere Deathmatch-Matches oder passe die Filter an.',
        'deathmatch.empty.noFilterMatches': 'Keine Ergebnisse passend zu den aktuellen Filtern.',
        'deathmatch.matchId.title': 'Match-ID',
        'deathmatch.meta.recorded': 'Aufgestellt am',
        'elimination.headers.rank': 'Rang',
        'elimination.headers.player': 'Spieler',
        'elimination.headers.value': 'Wertung',
        'elimination.headers.map': 'Map',
        'elimination.headers.mode': 'Modus',
        'elimination.headers.recorded': 'Aufgestellt am',
        'elimination.status.waiting': 'Warte auf Daten…',
        'elimination.status.loading': 'Lade Elimination-Werte…',
        'elimination.status.noneData': 'Keine Elimination-Daten in den geladenen Matches gefunden.',
        'elimination.status.noFilterMatches': 'Keine Ergebnisse für die aktuellen Filter.',
        'elimination.status.count': '{displayed} von {total} Elimination-Werten angezeigt.',
        'elimination.status.error': 'Fehler beim Laden: {message}',
        'elimination.empty.noData': 'Keine Elimination-Werte gefunden. Lade weitere Elimination-Matches oder passe die Filter an.',
        'elimination.empty.noFilterMatches': 'Keine Ergebnisse passend zu den aktuellen Filtern.',
        'elimination.matchId.title': 'Match-ID',
        'ctf.headers.rank': 'Rang',
        'ctf.headers.player': 'Spieler',
        'ctf.headers.value': 'Zielwertung',
        'ctf.headers.map': 'Map',
        'ctf.headers.mode': 'Modus',
        'ctf.headers.recorded': 'Aufgestellt am',
        'ctf.status.waiting': 'Warte auf Daten…',
        'ctf.status.loading': 'Lade Objective-Werte…',
        'ctf.status.noneData': 'Keine Objective-Daten in den geladenen Matches gefunden.',
        'ctf.status.noFilterMatches': 'Keine Ergebnisse für die aktuellen Filter.',
        'ctf.status.count': '{displayed} von {total} Objective-Werten angezeigt.',
        'ctf.status.error': 'Fehler beim Laden: {message}',
        'ctf.empty.noData': 'Keine Objective-Werte gefunden. Lade weitere CTF/Objective-Matches oder passe die Filter an.',
        'ctf.empty.noFilterMatches': 'Keine Ergebnisse passend zu den aktuellen Filtern.',
        'ctf.matchId.title': 'Match-ID',
        'ctf.meta.recorded': 'Aufgestellt am',
        'ctf.metric.captures': 'Flag-Eroberungen',
        'ctf.metric.score': 'Punkte',
        'ctf.metric.wins': 'Runden gewonnen',
        'ctf.metric.objectives': 'Ziele',
        'ctf.metric.value': 'Wertung',
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
        'mode.controls.map': 'Map',
        'mode.table.heading': 'Top 10 Platzierungen',
        'mode.empty': 'Keine Einträge für diese Map.',
        'mode.status.loading': 'Lade Matches…',
        'mode.status.ready': 'Top-Ergebnisse aus {count} Matches geladen.',
        'mode.status.empty': 'Keine Matches gespeichert.',
        'mode.status.error': 'Fehler beim Laden: {message}',
        'mode.headers.metric': 'Metrik',
        'mode.levelshot.missing': 'Kein Levelshot verfügbar.',
        'map.unknown': 'Unbekannte Map',
        'common.unknown': 'Unbekannt'
      },
      en: {
        'meta.title': 'Q3Rally Ladder Monitor beta',
        'meta.description': 'Frontend for exploring the stored Q3Rally ladder matches.',
        'hero.title': 'Q3Rally Ladder Monitor <span class="badge-beta">beta</span>',
        'hero.description': 'Live preview of the best placements per game mode and map. Pick a mode above and compare the top 10 per track – complete with levelshot previews.',
        'language.toggleLabel': 'Language selection',
        'language.deLabel': 'German',
        'language.enLabel': 'English',
        'nav.label': 'Sections',
        'nav.matches': 'Matches',
        'nav.servers': 'Server Browser',
        'tabs.label': 'Game modes',
        'tabs.racing': 'RACING LEADERBOARD',
        'tabs.deathmatch': 'DEATHMATCH LEADERBOARD',
        'tabs.elimination': 'ELIMINATION LEADERBOARD',
        'tabs.ctf': 'CTF LEADERBOARD',
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
        'filters.deathmatch.mode.label': 'Game mode',
        'filters.deathmatch.mode.all': 'All deathmatch modes',
        'filters.deathmatch.map.label': 'Map',
        'filters.deathmatch.map.all': 'All maps',
        'filters.deathmatch.player.label': 'Player search',
        'filters.deathmatch.player.placeholder': 'Player name…',
        'filters.elimination.mode.label': 'Game mode',
        'filters.elimination.mode.all': 'All elimination modes',
        'filters.elimination.map.label': 'Map',
        'filters.elimination.map.all': 'All maps',
        'filters.elimination.player.label': 'Player search',
        'filters.elimination.player.placeholder': 'Player name…',
        'filters.ctf.mode.label': 'Game mode',
        'filters.ctf.mode.all': 'All objective modes',
        'filters.ctf.map.label': 'Map',
        'filters.ctf.map.all': 'All maps',
        'filters.ctf.player.label': 'Player search',
        'filters.ctf.player.placeholder': 'Player name…',
        'filters.matches.mode.label': 'Game mode',
        'filters.matches.mode.all': 'All modes',
        'filters.matches.search.label': 'Search',
        'filters.matches.search.placeholder': 'Match ID, map, or player…',
        'filters.matches.limit.label': 'Load limit',
        'filters.matches.limit.all': 'All',
        'filters.matches.refresh': 'Refresh',
        'matches.empty': 'No best times found. Load more racing matches or adjust the filters.',
        'leaderboard.headers.rank': 'Rank',
        'leaderboard.headers.player': 'Player',
        'leaderboard.headers.time': 'Time',
        'leaderboard.headers.best': 'Best time',
        'leaderboard.headers.map': 'Map',
        'leaderboard.headers.mode': 'Mode',
        'leaderboard.headers.vehicle': 'Vehicle',
        'leaderboard.headers.recorded': 'Set on',
        'leaderboard.player.botTag': 'Bot',
        'leaderboard.player.botTooltip': 'Record set by a bot',
        'deathmatch.headers.rank': 'Rank',
        'deathmatch.headers.player': 'Player',
        'deathmatch.headers.kdr': 'K/D ratio',
        'deathmatch.headers.kills': 'Kills',
        'deathmatch.headers.deaths': 'Deaths',
        'deathmatch.headers.map': 'Map',
        'deathmatch.headers.mode': 'Mode',
        'deathmatch.headers.recorded': 'Set on',
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
        'deathmatch.status.waiting': 'Waiting for data…',
        'deathmatch.status.loading': 'Loading K/D records…',
        'deathmatch.status.noneData': 'No deathmatch data found in the loaded matches.',
        'deathmatch.status.noFilterMatches': 'No results for the current filters.',
        'deathmatch.status.count': 'Showing {displayed} of {total} K/D records.',
        'deathmatch.status.error': 'Load error: {message}',
        'deathmatch.empty.noData': 'No K/D records found. Upload more deathmatch matches or adjust the filters.',
        'deathmatch.empty.noFilterMatches': 'No results match the current filters.',
        'deathmatch.matchId.title': 'Match ID',
        'deathmatch.meta.recorded': 'Recorded on',
        'elimination.headers.rank': 'Rank',
        'elimination.headers.player': 'Player',
        'elimination.headers.value': 'Score',
        'elimination.headers.map': 'Map',
        'elimination.headers.mode': 'Mode',
        'elimination.headers.recorded': 'Recorded on',
        'elimination.status.waiting': 'Waiting for data…',
        'elimination.status.loading': 'Loading elimination stats…',
        'elimination.status.noneData': 'No elimination stats found in the loaded matches.',
        'elimination.status.noFilterMatches': 'No results for the current filters.',
        'elimination.status.count': 'Showing {displayed} of {total} elimination records.',
        'elimination.status.error': 'Load error: {message}',
        'elimination.empty.noData': 'No elimination stats found. Upload more elimination matches or adjust the filters.',
        'elimination.empty.noFilterMatches': 'No results match the current filters.',
        'elimination.matchId.title': 'Match ID',
        'ctf.headers.rank': 'Rank',
        'ctf.headers.player': 'Player',
        'ctf.headers.value': 'Objective value',
        'ctf.headers.map': 'Map',
        'ctf.headers.mode': 'Mode',
        'ctf.headers.recorded': 'Recorded on',
        'ctf.status.waiting': 'Waiting for data…',
        'ctf.status.loading': 'Loading objective values…',
        'ctf.status.noneData': 'No objective data found in the loaded matches.',
        'ctf.status.noFilterMatches': 'No results for the current filters.',
        'ctf.status.count': 'Showing {displayed} of {total} objective values.',
        'ctf.status.error': 'Load error: {message}',
        'ctf.empty.noData': 'No objective values found. Upload more CTF/objective matches or adjust the filters.',
        'ctf.empty.noFilterMatches': 'No results match the current filters.',
        'ctf.matchId.title': 'Match ID',
        'ctf.meta.recorded': 'Recorded on',
        'ctf.metric.captures': 'Flag captures',
        'ctf.metric.score': 'Points',
        'ctf.metric.wins': 'Rounds won',
        'ctf.metric.objectives': 'Objectives',
        'ctf.metric.value': 'Value',
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
        'mode.controls.map': 'Map',
        'mode.table.heading': 'Top 10 placements',
        'mode.empty': 'No entries for this map.',
        'mode.status.loading': 'Loading matches…',
        'mode.status.ready': 'Loaded top entries from {count} matches.',
        'mode.status.empty': 'No matches stored.',
        'mode.status.error': 'Load error: {message}',
        'mode.headers.metric': 'Metric',
        'mode.levelshot.missing': 'No levelshot available.',
        'map.unknown': 'Unknown map',
        'common.unknown': 'Unknown'
      }
    };

    const MODE_TRANSLATIONS = {
      de: {
        gt_racing: 'Rennen',
        gt_racing_dm: 'Deathmatch Rennen',
        gt_derby: 'Demolition Derby',
        gt_lcs: 'Last Car Standing',
        gt_elimination: 'Elimination',
        gt_deathmatch: 'Deathmatch',
        gt_team: 'Team Deathmatch',
        gt_team_racing: 'Team Racing',
        gt_team_racing_dm: 'Team Racing Deathmatch',
        gt_ctf: 'Capture the Flag',
        gt_ctf4: '4-Teams-CTF',
        gt_domination: 'Domination'
      },
      en: {
        gt_racing: 'Racing',
        gt_racing_dm: 'Racing Deathmatch',
        gt_derby: 'Demolition Derby',
        gt_lcs: 'Last Car Standing',
        gt_elimination: 'Elimination',
        gt_deathmatch: 'Deathmatch',
        gt_team: 'Team Deathmatch',
        gt_team_racing: 'Team Racing',
        gt_team_racing_dm: 'Team Racing Deathmatch',
        gt_ctf: 'Capture the Flag',
        gt_ctf4: '4-Team CTF',
        gt_domination: 'Domination'
      }
    };

    const RACE_MODE_KEYS = new Set(['gt_racing', 'gt_racing_dm', 'gt_team_racing', 'gt_team_racing_dm']);
    const DEATHMATCH_MODE_KEYS = new Set(['gt_deathmatch', 'gt_team']);
    const OBJECTIVE_MODE_KEYS = new Set(['gt_ctf', 'gt_ctf4', 'gt_elimination', 'gt_domination', 'gt_derby', 'gt_lcs']);

    const OBJECTIVE_METRIC_DEFINITIONS = {
      captures: {
        paths: [
          'captures',
          'flagCaptures',
          'flags',
          'stats.captures',
          'stats.flags',
          'result.captures',
          'result.flags',
          'summary.captures',
          'totals.captures'
        ],
        keywords: ['capture', 'flag']
      },
      score: {
        paths: [
          'score',
          'points',
          'value',
          'stats.score',
          'stats.points',
          'result.score',
          'summary.score',
          'summary.points',
          'totals.score',
          'totals.points'
        ],
        keywords: ['score', 'point']
      },
      objectives: {
        paths: [
          'objectives',
          'objectiveScore',
          'objectivePoints',
          'stats.objectives',
          'stats.objectiveScore',
          'result.objectives',
          'summary.objectives',
          'totals.objectives'
        ],
        keywords: ['objective', 'domination']
      },
      wins: {
        paths: [
          'wins',
          'roundsWon',
          'roundWins',
          'round_wins',
          'rounds_won',
          'rounds',
          'victories',
          'stats.wins',
          'stats.roundsWon',
          'stats.round_wins',
          'stats.rounds_won',
          'result.wins',
          'result.round_wins',
          'result.rounds_won',
          'summary.wins',
          'summary.round_wins',
          'summary.rounds_won',
          'totals.wins'
        ],
        keywords: ['win', 'round', 'elimination']
      }
    };

    const OBJECTIVE_MODE_PRIORITY = {
      gt_ctf: ['captures', 'score', 'objectives', 'wins'],
      gt_ctf4: ['captures', 'score', 'objectives', 'wins'],
      gt_elimination: ['wins', 'score', 'captures', 'objectives'],
      gt_domination: ['objectives', 'score', 'captures', 'wins'],
      gt_derby: ['score', 'wins', 'objectives', 'captures'],
      gt_lcs: ['wins', 'score', 'captures', 'objectives']
    };

    const OBJECTIVE_DEFAULT_PRIORITY = ['score', 'captures', 'objectives', 'wins'];

    const OBJECTIVE_METRIC_LABEL_KEYS = {
      captures: 'ctf.metric.captures',
      score: 'ctf.metric.score',
      objectives: 'ctf.metric.objectives',
      wins: 'ctf.metric.wins',
      value: 'ctf.metric.value'
    };


const state = {
  allMatches: [],
  leaderboard: [],
  deathmatchLeaderboard: [],
  objectiveLeaderboard: [],
  eliminationLeaderboard: [],
  modeData: new Map(),
  selectedMaps: new Map(),
  mapMetadata: new Map(),
  activeMode: MODE_CONFIG.length ? MODE_CONFIG[0].key : 'gt_racing',
  language: 'de',
  modeStatus: { key: null, params: {}, isError: false }
};

const elements = {
  modeTabs: document.getElementById('modeTabs'),
  modePanels: document.getElementById('modePanels'),
  modeStatus: document.getElementById('modeStatus'),
  statTotal: document.getElementById('stat-total'),
  statLast: document.getElementById('stat-last'),
  statModes: document.getElementById('stat-modes'),
  statPlayers: document.getElementById('stat-players'),
  modeBreakdown: document.getElementById('modeBreakdown'),
  languageButtons: Array.from(document.querySelectorAll('.language-button')),
  html: document.documentElement,
  metaDescription: document.querySelector('meta[name="description"]')
};

const modeElements = new Map();
const MODE_CONFIG_MAP = new Map(MODE_CONFIG.map((config) => [config.key, config]));

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

const BOT_PATH_CANDIDATES = [
  'isBot',
  'is_bot',
  'bot',
  'botPlayer',
  'isAI',
  'isAi',
  'ai',
  'stats.isBot',
  'stats.is_bot',
  'stats.bot',
  'result.isBot',
  'result.is_bot',
  'result.bot'
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

const ELIMINATION_POSITION_PATHS = [
  'position',
  'place',
  'rank',
  'standing',
  'finishPosition',
  'finishRank',
  'finish.position',
  'finish.place',
  'finish.rank',
  'finalPosition',
  'finalRank',
  'stats.position',
  'stats.place',
  'stats.rank',
  'result.position',
  'result.place',
  'result.rank',
  'summary.position',
  'summary.place',
  'totals.position',
  'totals.place'
];

const ELIMINATION_REMAINING_PATHS = [
  'remainingPlayers',
  'playersRemaining',
  'playersLeft',
  'playersAlive',
  'alivePlayers',
  'stillAlive',
  'stats.remainingPlayers',
  'stats.playersRemaining',
  'result.remainingPlayers',
  'result.playersRemaining',
  'summary.remainingPlayers',
  'summary.playersRemaining',
  'totals.remainingPlayers',
  'totals.playersRemaining'
];

const ELIMINATION_ROSTER_PATHS = [
  'rosterSize',
  'roster',
  'playerCount',
  'totalPlayers',
  'participants',
  'stats.rosterSize',
  'stats.playerCount',
  'stats.totalPlayers',
  'result.rosterSize',
  'result.playerCount',
  'result.totalPlayers',
  'summary.rosterSize',
  'summary.playerCount',
  'summary.totalPlayers',
  'totals.rosterSize',
  'totals.playerCount',
  'totals.totalPlayers'
];

const MAX_REASONABLE_TIME = 6 * 3600;

const KILL_PATH_CANDIDATES = [
  'kills',
  'frags',
  'stats.kills',
  'stats.frags',
  'result.kills',
  'result.frags',
  'summary.kills',
  'summary.frags',
  'totals.kills',
  'totals.frags'
];

const DEATH_PATH_CANDIDATES = [
  'deaths',
  'death',
  'stats.deaths',
  'stats.death',
  'result.deaths',
  'summary.deaths',
  'totals.deaths'
];

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

function setModeStatus(key, params = {}, isError = false, persist = true) {
  const message = t(key, params);
  elements.modeStatus.textContent = message;
  elements.modeStatus.classList.toggle('error', Boolean(isError));
  if (persist) {
    state.modeStatus = { key, params, isError };
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
  updateModeTabsLanguage();
  updateSummary();
  updateModeOptions();
  MODE_CONFIG.forEach(({ key }) => {
    renderModeTable(key);
  });
  if (state.modeStatus.key) {
    setModeStatus(state.modeStatus.key, state.modeStatus.params, state.modeStatus.isError, false);
  }
}

function createModeTabs() {
  const tabFragment = document.createDocumentFragment();
  const panelFragment = document.createDocumentFragment();
  MODE_CONFIG.forEach((config, index) => {
    const button = document.createElement('button');
    button.className = 'tab-button';
    button.type = 'button';
    button.dataset.mode = config.key;
    button.id = `mode-tab-${config.key}`;
    button.setAttribute('role', 'tab');
    button.setAttribute('aria-controls', `mode-panel-${config.key}`);
    button.setAttribute('aria-selected', index === 0 ? 'true' : 'false');
    button.setAttribute('tabindex', index === 0 ? '0' : '-1');
    button.addEventListener('click', () => {
      setActiveMode(config.key);
    });
    tabFragment.appendChild(button);

    const panel = document.createElement('div');
    panel.className = 'mode-panel tab-panel';
    if (index === 0) {
      panel.classList.add('active');
    }
    panel.id = `mode-panel-${config.key}`;
    panel.setAttribute('role', 'tabpanel');
    panel.setAttribute('aria-labelledby', button.id);

    const controls = document.createElement('div');
    controls.className = 'mode-controls';
    const mapWrapper = document.createElement('div');
    const mapLabel = document.createElement('label');
    mapLabel.setAttribute('for', `mode-map-${config.key}`);
    mapLabel.dataset.i18n = 'mode.controls.map';
    mapWrapper.appendChild(mapLabel);
    const mapSelect = document.createElement('select');
    mapSelect.id = `mode-map-${config.key}`;
    mapSelect.disabled = true;
    mapSelect.addEventListener('change', () => {
      state.selectedMaps.set(config.key, mapSelect.value);
      renderModeTable(config.key);
    });
    mapWrapper.appendChild(mapSelect);
    controls.appendChild(mapWrapper);
    panel.appendChild(controls);

    const layout = document.createElement('div');
    layout.className = 'mode-layout';

    const figure = document.createElement('figure');
    figure.className = 'mode-levelshot';
    const shot = document.createElement('img');
    shot.alt = '';
    shot.loading = 'lazy';
    shot.decoding = 'async';
    const fallback = document.createElement('p');
    fallback.className = 'mode-levelshot-fallback';
    fallback.dataset.i18n = 'mode.levelshot.missing';
    fallback.hidden = true;
    shot.addEventListener('error', () => {
      shot.hidden = true;
      fallback.hidden = false;
    });
    shot.addEventListener('load', () => {
      shot.hidden = false;
      fallback.hidden = true;
    });
    const caption = document.createElement('figcaption');
    figure.appendChild(shot);
    figure.appendChild(fallback);
    figure.appendChild(caption);
    layout.appendChild(figure);

    const tableWrapper = document.createElement('div');
    tableWrapper.className = 'mode-table-wrapper';
    const heading = document.createElement('h3');
    heading.dataset.i18n = 'mode.table.heading';
    tableWrapper.appendChild(heading);
    const tableContainer = document.createElement('div');
    tableContainer.className = 'table-wrapper';
    const table = document.createElement('table');
    table.className = 'leaderboard-table';
    const thead = document.createElement('thead');
    const headerRow = document.createElement('tr');
    thead.appendChild(headerRow);
    table.appendChild(thead);
    const tbody = document.createElement('tbody');
    table.appendChild(tbody);
    tableContainer.appendChild(table);
    tableWrapper.appendChild(tableContainer);
    const empty = document.createElement('p');
    empty.className = 'empty-state';
    empty.dataset.i18n = 'mode.empty';
    empty.hidden = true;
    tableWrapper.appendChild(empty);
    layout.appendChild(tableWrapper);

    panel.appendChild(layout);
    panelFragment.appendChild(panel);

    modeElements.set(config.key, {
      button,
      panel,
      mapSelect,
      table,
      tableHeadRow: headerRow,
      tableBody: tbody,
      tableContainer,
      empty,
      levelshot: shot,
      levelshotFallback: fallback,
      levelshotCaption: caption,
      tableHeading: heading
    });
  });

  elements.modeTabs.innerHTML = '';
  elements.modeTabs.appendChild(tabFragment);
  elements.modePanels.innerHTML = '';
  elements.modePanels.appendChild(panelFragment);

  const buttons = Array.from(elements.modeTabs.querySelectorAll('.tab-button'));
  buttons.forEach((button, index) => {
    button.addEventListener('keydown', (event) => {
      if (event.key === 'ArrowRight' || event.key === 'ArrowLeft') {
        event.preventDefault();
        const direction = event.key === 'ArrowRight' ? 1 : -1;
        const nextIndex = (index + direction + buttons.length) % buttons.length;
        const nextButton = buttons[nextIndex];
        nextButton.focus();
        setActiveMode(nextButton.dataset.mode);
      }
    });
  });
}

function setActiveMode(modeKey) {
  if (!modeElements.has(modeKey)) {
    return;
  }
  state.activeMode = modeKey;
  modeElements.forEach((refs, key) => {
    const isActive = key === modeKey;
    refs.button.classList.toggle('active', isActive);
    refs.button.setAttribute('aria-selected', String(isActive));
    refs.button.setAttribute('tabindex', isActive ? '0' : '-1');
    refs.panel.classList.toggle('active', isActive);
  });
  renderModeTable(modeKey);
}

function updateModeTabsLanguage() {
  modeElements.forEach((refs, key) => {
    refs.button.textContent = humanizeMode(key);
  });
}

function getColumnsForMode(config) {
  if (config.type === 'race') {
    return [
      { key: 'rank', labelKey: 'leaderboard.headers.rank' },
      { key: 'player', labelKey: 'leaderboard.headers.player' },
      { key: 'time', labelKey: 'leaderboard.headers.time' },
      { key: 'vehicle', labelKey: 'leaderboard.headers.vehicle' },
      { key: 'recorded', labelKey: 'leaderboard.headers.recorded' }
    ];
  }
  if (config.type === 'deathmatch') {
    return [
      { key: 'rank', labelKey: 'deathmatch.headers.rank' },
      { key: 'player', labelKey: 'deathmatch.headers.player' },
      { key: 'kdr', labelKey: 'deathmatch.headers.kdr' },
      { key: 'kills', labelKey: 'deathmatch.headers.kills' },
      { key: 'deaths', labelKey: 'deathmatch.headers.deaths' },
      { key: 'recorded', labelKey: 'deathmatch.headers.recorded' }
    ];
  }
  return [
    { key: 'rank', labelKey: 'leaderboard.headers.rank' },
    { key: 'player', labelKey: 'leaderboard.headers.player' },
    { key: 'metric', labelKey: 'mode.headers.metric' },
    { key: 'value', labelKey: 'ctf.headers.value' },
    { key: 'recorded', labelKey: 'ctf.headers.recorded' }
  ];
}

function updateModeOptions() {
  MODE_CONFIG.forEach(({ key }) => updateModeOptionsForMode(key));
}

function updateModeOptionsForMode(modeKey) {
  const refs = modeElements.get(modeKey);
  if (!refs) {
    return;
  }
  const modeMap = state.modeData.get(modeKey);
  const previous = state.selectedMaps.get(modeKey);
  refs.mapSelect.innerHTML = '';
  if (!modeMap || modeMap.size === 0) {
    refs.mapSelect.disabled = true;
    state.selectedMaps.delete(modeKey);
    return;
  }
  const locale = getLocale();
  const options = Array.from(modeMap.values())
    .map((entry) => ({
      value: entry.mapKey,
      label: humanizeMapName(entry.map)
    }))
    .sort((a, b) => a.label.localeCompare(b.label, locale));
  options.forEach(({ value, label }) => {
    const option = document.createElement('option');
    option.value = value;
    option.textContent = label;
    refs.mapSelect.appendChild(option);
  });
  refs.mapSelect.disabled = false;
  if (previous && modeMap.has(previous)) {
    refs.mapSelect.value = previous;
  } else {
    refs.mapSelect.value = options[0].value;
    state.selectedMaps.set(modeKey, options[0].value);
  }
}

function renderModeTable(modeKey) {
  const refs = modeElements.get(modeKey);
  if (!refs) {
    return;
  }
  const config = MODE_CONFIG_MAP.get(modeKey);
  const modeMap = state.modeData.get(modeKey) || new Map();
  if (!state.selectedMaps.has(modeKey) && modeMap.size) {
    const firstKey = modeMap.keys().next().value;
    state.selectedMaps.set(modeKey, firstKey);
    if (refs.mapSelect && !refs.mapSelect.value) {
      refs.mapSelect.value = firstKey;
    }
  }
  const selectedMap = state.selectedMaps.get(modeKey);
  if (refs.mapSelect && selectedMap && refs.mapSelect.value !== selectedMap) {
    refs.mapSelect.value = selectedMap;
  }

  const columns = getColumnsForMode(config);
  refs.tableHeadRow.innerHTML = '';
  columns.forEach((column) => {
    const th = document.createElement('th');
    th.scope = 'col';
    th.dataset.i18n = column.labelKey;
    th.textContent = t(column.labelKey);
    refs.tableHeadRow.appendChild(th);
  });

  if (!selectedMap || !modeMap.has(selectedMap)) {
    refs.tableBody.innerHTML = '';
    refs.tableContainer.hidden = true;
    refs.empty.hidden = false;
    updateLevelshot(modeKey, null);
    return;
  }

  const mapData = modeMap.get(selectedMap);
  updateLevelshot(modeKey, mapData);

  const rows = mapData.entries.slice(0, 10);
  refs.tableBody.innerHTML = '';
  if (!rows.length) {
    refs.tableContainer.hidden = true;
    refs.empty.hidden = false;
    return;
  }

  refs.tableContainer.hidden = false;
  refs.empty.hidden = true;

  rows.forEach((entry, index) => {
    const tr = document.createElement('tr');
    columns.forEach((column) => {
      const td = document.createElement('td');
      switch (column.key) {
        case 'rank':
          td.textContent = String(index + 1);
          break;
        case 'player': {
          td.classList.add('leaderboard-player');
          const strong = document.createElement('strong');
          strong.textContent = entry.player || t('common.unknown');
          td.appendChild(strong);
          if (entry.isBot) {
            const badge = document.createElement('span');
            badge.className = 'bot-tag';
            const tagText = t('leaderboard.player.botTag');
            badge.textContent = tagText;
            const tooltip = t('leaderboard.player.botTooltip');
            badge.title = tooltip;
            badge.setAttribute('aria-label', tooltip);
            td.appendChild(badge);
          }
          break;
        }
        case 'time':
          td.textContent = formatRaceTime(entry.time);
          break;
        case 'vehicle':
          td.textContent = entry.vehicle ? entry.vehicle : '–';
          break;
        case 'kdr':
          td.textContent = formatRatio(entry.ratio);
          break;
        case 'kills':
          td.textContent = entry.kills != null ? String(entry.kills) : '–';
          break;
        case 'deaths':
          td.textContent = entry.deaths != null ? String(entry.deaths) : '–';
          break;
        case 'metric': {
          const labelKey = OBJECTIVE_METRIC_LABEL_KEYS[entry.metricType] || OBJECTIVE_METRIC_LABEL_KEYS.value;
          td.textContent = t(labelKey);
          break;
        }
        case 'value':
          td.textContent = entry.value != null ? String(entry.value) : '–';
          break;
        case 'recorded':
          td.textContent = formatDate(entry.recordedAt || entry.startedAt);
          break;
        default:
          td.textContent = '';
      }
      tr.appendChild(td);
    });
    refs.tableBody.appendChild(tr);
  });
}

async function updateLevelshot(modeKey, mapData) {
  const refs = modeElements.get(modeKey);
  if (!refs) {
    return;
  }

  const mapName = mapData ? mapData.map : '';
  const humanized = mapName ? humanizeMapName(mapName) : '';
  refs.levelshotCaption.textContent = humanized;
  refs.levelshot.alt = humanized || '';

  const requestId = `${modeKey}-${++levelshotRequestCounter}`;
  refs.levelshot.dataset.requestId = requestId;
  refs.levelshot.hidden = true;
  refs.levelshotFallback.hidden = false;
  refs.levelshot.removeAttribute('src');

  if (!mapData) {
    return;
  }

  try {
    const resolved = await resolveLevelshotAsset(mapName);
    if (refs.levelshot.dataset.requestId !== requestId) {
      return;
    }
    if (!resolved || !resolved.src) {
      refs.levelshot.hidden = true;
      refs.levelshotFallback.hidden = false;
      return;
    }
    refs.levelshot.hidden = false;
    refs.levelshotFallback.hidden = true;
    refs.levelshot.src = resolved.src;
  } catch (error) {
    console.error('Failed to load levelshot', error);
    refs.levelshot.hidden = true;
    refs.levelshotFallback.hidden = false;
  }
}

function formatRaceTime(seconds) {
  if (seconds === null || seconds === undefined) {
    return '–';
  }
  return formatSeconds(seconds);
}

function formatRatio(value) {
  if (!Number.isFinite(value)) {
    return '–';
  }
  if (value >= 10) {
    return value.toFixed(1);
  }
  return value.toFixed(2);
}

function formatDate(value) {
  if (!(value instanceof Date) || Number.isNaN(value.getTime())) {
    return '–';
  }
  return formatter.format(value);
}

async function resolveLevelshotAsset(mapName) {
  const mapKey = normalizeLevelshotKey(mapName);
  if (mapKey && levelshotMapCache.has(mapKey)) {
    return levelshotMapCache.get(mapKey);
  }

  const candidates = getLevelshotCandidates(mapName);
  for (const candidate of candidates) {
    if (levelshotAssetCache.has(candidate.cacheKey)) {
      const cached = levelshotAssetCache.get(candidate.cacheKey);
      if (cached) {
        if (mapKey) {
          levelshotMapCache.set(mapKey, cached);
        }
        return cached;
      }
      continue;
    }

    try {
      let resolved = null;
      if (candidate.ext === 'tga') {
        const dataUrl = await loadTgaAsDataUrl(candidate.url);
        if (dataUrl) {
          resolved = { src: dataUrl, isDataUrl: true };
        }
      } else {
        await probeImageCandidate(candidate.url);
        resolved = { src: candidate.url, isDataUrl: false };
      }
      if (resolved) {
        levelshotAssetCache.set(candidate.cacheKey, resolved);
        if (mapKey) {
          levelshotMapCache.set(mapKey, resolved);
        }
        return resolved;
      }
      levelshotAssetCache.set(candidate.cacheKey, null);
    } catch (error) {
      levelshotAssetCache.set(candidate.cacheKey, null);
    }
  }

  if (mapKey) {
    levelshotMapCache.set(mapKey, null);
  }
  return null;
}

function getLevelshotCandidates(mapName) {
  const { original, base } = extractMapBase(mapName);
  const variants = new Set();
  [original, base].forEach((value) => {
    if (value) {
      variants.add(value);
      variants.add(value.toLowerCase());
    }
  });
  const seen = new Set();
  const candidates = [];
  variants.forEach((variant) => {
    LEVELSHOT_EXTENSIONS.forEach((ext) => {
      const cacheKey = `${variant}|${ext}`;
      if (!seen.has(cacheKey)) {
        seen.add(cacheKey);
        candidates.push({
          url: `images/${encodeURIComponent(variant)}.${ext}`,
          ext,
          cacheKey
        });
      }
    });
  });
  return candidates;
}

function normalizeLevelshotKey(mapName) {
  const { base } = extractMapBase(mapName);
  return base ? base.toLowerCase() : '';
}

function extractMapBase(mapName) {
  if (typeof mapName !== 'string') {
    return { original: '', base: '' };
  }
  const trimmed = mapName.trim();
  if (!trimmed || trimmed === '–' || trimmed === '-') {
    return { original: '', base: '' };
  }
  const withoutExtension = trimmed.replace(/\.[^./\\]+$/, '');
  if (!withoutExtension) {
    return { original: '', base: '' };
  }
  const segments = withoutExtension.split(/[\\/]/);
  const base = segments[segments.length - 1] || withoutExtension;
  return { original: withoutExtension, base };
}

function isImageContentType(contentType) {
  if (typeof contentType !== 'string') {
    return false;
  }
  const normalized = contentType.split(';', 1)[0].trim().toLowerCase();
  return normalized.startsWith('image/');
}

async function probeImageCandidate(url) {
  let headResponse = null;
  let headError = null;
  try {
    headResponse = await fetch(url, { method: 'HEAD' });
  } catch (error) {
    headError = error;
  }

  if (headResponse) {
    if (headResponse.ok) {
      const contentType = headResponse.headers.get('Content-Type');
      if (isImageContentType(contentType)) {
        return;
      }
      throw new Error(`Unsupported content type: ${contentType || 'unknown'}`);
    }

    if (headResponse.status !== 405) {
      headError = new Error(`HTTP ${headResponse.status}`);
    }
  }

  let fallbackResponse;
  try {
    fallbackResponse = await fetch(url);
  } catch (error) {
    throw headError ?? error;
  }

  if (!fallbackResponse.ok) {
    throw headError ?? new Error(`HTTP ${fallbackResponse.status}`);
  }

  const fallbackContentType = fallbackResponse.headers.get('Content-Type');
  if (isImageContentType(fallbackContentType)) {
    return;
  }
  throw new Error(`Unsupported content type: ${fallbackContentType || 'unknown'}`);
}

async function loadTgaAsDataUrl(url) {
  const response = await fetch(url);
  if (!response.ok) {
    throw new Error(`HTTP ${response.status}`);
  }

  const buffer = await response.arrayBuffer();
  if (buffer.byteLength < 18) {
    throw new Error('Invalid TGA file');
  }

  const view = new DataView(buffer);
  const bytes = new Uint8Array(buffer);
  const idLength = view.getUint8(0);
  const colorMapType = view.getUint8(1);
  const imageType = view.getUint8(2);
  const width = view.getUint16(12, true);
  const height = view.getUint16(14, true);
  const pixelDepth = view.getUint8(16);
  const descriptor = view.getUint8(17);

  if (!width || !height) {
    throw new Error('Invalid TGA dimensions');
  }
  if (colorMapType !== 0) {
    throw new Error('Unsupported TGA color map');
  }
  if (![2, 10].includes(imageType)) {
    throw new Error('Unsupported TGA image type');
  }
  if (pixelDepth !== 24 && pixelDepth !== 32) {
    throw new Error('Unsupported TGA pixel depth');
  }

  const bytesPerPixel = pixelDepth / 8;
  let offset = 18 + idLength;
  if (offset > bytes.length) {
    throw new Error('Corrupted TGA file');
  }

  const totalPixels = width * height;
  const pixels = new Uint8ClampedArray(totalPixels * 4);
  const flipVertically = (descriptor & 0x20) === 0;
  const flipHorizontally = (descriptor & 0x10) !== 0;

  const setPixel = (index, r, g, b, a) => {
    let x = index % width;
    let y = Math.floor(index / width);
    if (flipHorizontally) {
      x = width - 1 - x;
    }
    if (flipVertically) {
      y = height - 1 - y;
    }
    const dest = (y * width + x) * 4;
    pixels[dest] = r;
    pixels[dest + 1] = g;
    pixels[dest + 2] = b;
    pixels[dest + 3] = a;
  };

  const readColor = () => {
    if (offset + bytesPerPixel > bytes.length) {
      throw new Error('Unexpected end of TGA data');
    }
    const b = bytes[offset++];
    const g = bytes[offset++];
    const r = bytes[offset++];
    const a = bytesPerPixel === 4 ? bytes[offset++] : 255;
    return { r, g, b, a };
  };

  if (imageType === 2) {
    for (let i = 0; i < totalPixels; i += 1) {
      const color = readColor();
      setPixel(i, color.r, color.g, color.b, color.a);
    }
  } else {
    let pixelIndex = 0;
    while (pixelIndex < totalPixels) {
      if (offset >= bytes.length) {
        throw new Error('Unexpected end of TGA data');
      }
      const packet = bytes[offset++];
      const count = (packet & 0x7f) + 1;
      if (packet & 0x80) {
        const color = readColor();
        for (let i = 0; i < count && pixelIndex < totalPixels; i += 1) {
          setPixel(pixelIndex++, color.r, color.g, color.b, color.a);
        }
      } else {
        for (let i = 0; i < count && pixelIndex < totalPixels; i += 1) {
          const color = readColor();
          setPixel(pixelIndex++, color.r, color.g, color.b, color.a);
        }
      }
    }
  }

  const canvas = document.createElement('canvas');
  canvas.width = width;
  canvas.height = height;
  const context = canvas.getContext('2d');
  if (!context) {
    throw new Error('Canvas API unavailable');
  }
  context.putImageData(new ImageData(pixels, width, height), 0, 0);
  return canvas.toDataURL('image/png');
}

function humanizeMapName(map) {
  if (typeof map !== 'string') {
    return t('map.unknown');
  }
  const trimmed = map.trim();
  if (!trimmed) {
    return t('map.unknown');
  }
  if (state.mapMetadata instanceof Map && state.mapMetadata.size > 0) {
    const normalizedKey = normalizeLevelshotKey(trimmed);
    if (normalizedKey && state.mapMetadata.has(normalizedKey)) {
      const displayName = state.mapMetadata.get(normalizedKey);
      if (typeof displayName === 'string') {
        const cleaned = displayName.trim();
        if (cleaned) {
          return cleaned;
        }
      }
    }
  }
  const base = trimmed.replace(/\.[^./\\]+$/, '');
  const normalized = base.replace(/[_-]+/g, ' ').replace(/\s+/g, ' ').toLowerCase();
  const locale = getLocale();
  return normalized.replace(/(^|\s)([\p{L}])/gu, (match, prefix, char) => prefix + char.toLocaleUpperCase(locale));
}

function prepareModeData() {
  const modeData = new Map();
  MODE_CONFIG.forEach(({ key }) => {
    modeData.set(key, new Map());
  });

  function addEntry(modeKey, entry) {
    const maps = modeData.get(modeKey);
    if (!maps) {
      return;
    }
    const mapKey = entry.mapKey || entry.map.toLowerCase();
    if (!maps.has(mapKey)) {
      maps.set(mapKey, { map: entry.map, mapKey, entries: [] });
    }
    maps.get(mapKey).entries.push(entry);
  }

  for (const entry of state.leaderboard) {
    addEntry(entry.modeKey, entry);
  }
  for (const entry of state.deathmatchLeaderboard) {
    addEntry(entry.modeKey, entry);
  }
  for (const entry of state.objectiveLeaderboard) {
    addEntry(entry.modeKey, entry);
  }
  for (const entry of state.eliminationLeaderboard) {
    addEntry(entry.modeKey, entry);
  }

  const locale = getLocale();
  modeData.forEach((maps, modeKey) => {
    const config = MODE_CONFIG_MAP.get(modeKey);
    maps.forEach((mapData) => {
      if (config.type === 'race') {
        mapData.entries.sort((a, b) => {
          if (a.time !== b.time) {
            return a.time - b.time;
          }
          return a.player.localeCompare(b.player, locale);
        });
      } else if (config.type === 'deathmatch') {
        mapData.entries.sort((a, b) => {
          if (b.ratio !== a.ratio) {
            return b.ratio - a.ratio;
          }
          if (b.kills !== a.kills) {
            return b.kills - a.kills;
          }
          if (a.deaths !== b.deaths) {
            return a.deaths - b.deaths;
          }
          return a.player.localeCompare(b.player, locale);
        });
      } else {
        const priority = OBJECTIVE_MODE_PRIORITY[modeKey] || OBJECTIVE_DEFAULT_PRIORITY;
        mapData.entries.sort((a, b) => {
          if (b.value !== a.value) {
            return b.value - a.value;
          }
          const priorityA = priorityIndex(a.metricType, priority);
          const priorityB = priorityIndex(b.metricType, priority);
          if (priorityA !== priorityB) {
            return priorityA - priorityB;
          }
          const dateA = a.recordedAt || a.startedAt;
          const dateB = b.recordedAt || b.startedAt;
          const timeA = dateA instanceof Date ? dateA.getTime() : 0;
          const timeB = dateB instanceof Date ? dateB.getTime() : 0;
          if (timeA !== timeB) {
            return timeB - timeA;
          }
          return a.player.localeCompare(b.player, locale);
        });
      }
    });
  });

  state.modeData = modeData;
}

function buildRaceLeaderboard() {
  const bestByKey = new Map();
  for (const match of state.allMatches) {
    const rawMode = extractMode(match);
    const modeKey = canonicalMode(rawMode);
    if (!RACE_MODE_KEYS.has(modeKey)) {
      continue;
    }
    const entries = extractScoreboardEntries(match);
    if (!entries.length) {
      continue;
    }
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
      const isBot = extractIsBot(entry);
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
          vehicle,
          isBot
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

function buildDeathmatchLeaderboard() {
  const bestByKey = new Map();
  for (const match of state.allMatches) {
    const rawMode = extractMode(match);
    const modeKey = canonicalMode(rawMode);
    if (!DEATHMATCH_MODE_KEYS.has(modeKey)) {
      continue;
    }
    const entries = extractScoreboardEntries(match);
    if (!entries.length) {
      continue;
    }
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
      const { kills, deaths } = extractKillDeath(entry);
      if (kills === null && deaths === null) {
        continue;
      }
      const safeKills = kills ?? 0;
      const safeDeaths = deaths ?? 0;
      const ratio = safeKills / Math.max(1, safeDeaths);
      if (!Number.isFinite(ratio)) {
        continue;
      }
      const key = `${modeKey}||${mapKey}||${player.toLowerCase()}`;
      const current = bestByKey.get(key);
      const shouldUpdate =
        !current ||
        ratio > current.ratio ||
        (ratio === current.ratio && safeKills > current.kills) ||
        (ratio === current.ratio && safeKills === current.kills && safeDeaths < current.deaths);
      if (shouldUpdate) {
        const isBot = extractIsBot(entry);
        bestByKey.set(key, {
          player,
          playerLower: player.toLowerCase(),
          ratio,
          kills: safeKills,
          deaths: safeDeaths,
          map,
          mapKey,
          mode: modeLabel,
          modeKey,
          matchId,
          startedAt,
          recordedAt,
          isBot
        });
      }
    }
  }
  const locale = getLocale();
  state.deathmatchLeaderboard = Array.from(bestByKey.values()).sort((a, b) => {
    if (b.ratio !== a.ratio) {
      return b.ratio - a.ratio;
    }
    if (b.kills !== a.kills) {
      return b.kills - a.kills;
    }
    if (a.deaths !== b.deaths) {
      return a.deaths - b.deaths;
    }
    return a.player.localeCompare(b.player, locale);
  });
}

function buildObjectiveLeaderboard() {
  const bestByKey = new Map();
  const eliminationBestByKey = new Map();
  for (const match of state.allMatches) {
    const rawMode = extractMode(match);
    const modeKey = canonicalMode(rawMode);
    if (!OBJECTIVE_MODE_KEYS.has(modeKey)) {
      continue;
    }
    const entries = extractScoreboardEntries(match);
    if (!entries.length) {
      continue;
    }
    const modeLabel = humanizeMode(rawMode);
    const map = extractMap(match);
    const mapKey = map.toLowerCase();
    const matchId = extractMatchId(match);
    const startedAt = extractStart(match);
    const recordedAt = extractRecordedAtFromMatchId(matchId);
    const priority = OBJECTIVE_MODE_PRIORITY[modeKey] || OBJECTIVE_DEFAULT_PRIORITY;
    const isElimination = modeKey === 'gt_elimination';
    const targetMap = isElimination ? eliminationBestByKey : bestByKey;
    const eliminationContext = isElimination ? prepareEliminationMetricContext(entries) : null;
    for (let entryIndex = 0; entryIndex < entries.length; entryIndex += 1) {
      const entry = entries[entryIndex];
      const player = extractLeaderboardPlayer(entry);
      if (!player) {
        continue;
      }
      const eliminationInfo =
        isElimination && eliminationContext ? eliminationContext.positions.get(entryIndex) : null;
      const metricContext = isElimination
        ? {
            rosterSize: eliminationContext ? eliminationContext.rosterSize : null,
            position: eliminationInfo ? eliminationInfo.explicitPosition : null,
            ordinalPosition: eliminationInfo ? eliminationInfo.ordinalPosition : null
          }
        : undefined;
      const metric = extractObjectiveMetric(entry, modeKey, metricContext);
      if (metric.value === null) {
        continue;
      }
      const playerLower = player.toLowerCase();
      const key = `${modeKey}||${mapKey}||${playerLower}`;
      const current = targetMap.get(key);
      const metricPriority = priorityIndex(metric.type, priority);
      const recordDate = recordedAt || startedAt;
      const recordTime = recordDate instanceof Date ? recordDate.getTime() : 0;
      let shouldUpdate = false;
      if (!current) {
        shouldUpdate = true;
      } else if (metric.value > current.value) {
        shouldUpdate = true;
      } else if (metric.value === current.value) {
        const currentPriority = priorityIndex(current.metricType, priority);
        if (metricPriority < currentPriority) {
          shouldUpdate = true;
        } else if (metricPriority === currentPriority) {
          const currentDate = current.recordedAt || current.startedAt;
          const currentTime = currentDate instanceof Date ? currentDate.getTime() : 0;
          if (recordTime > currentTime) {
            shouldUpdate = true;
          } else if (recordTime === currentTime && playerLower < current.playerLower) {
            shouldUpdate = true;
          }
        }
      }
      if (shouldUpdate) {
        const isBot = extractIsBot(entry);
        targetMap.set(key, {
          player,
          playerLower,
          value: metric.value,
          metricType: metric.type || 'value',
          map,
          mapKey,
          mode: modeLabel,
          modeKey,
          matchId,
          startedAt,
          recordedAt,
          isBot
        });
      }
    }
  }
  const locale = getLocale();
  function sortObjectiveEntries(map) {
    return Array.from(map.values()).sort((a, b) => {
      if (b.value !== a.value) {
        return b.value - a.value;
      }
      const metricPriorityA = priorityIndex(a.metricType, OBJECTIVE_MODE_PRIORITY[a.modeKey] || OBJECTIVE_DEFAULT_PRIORITY);
      const metricPriorityB = priorityIndex(b.metricType, OBJECTIVE_MODE_PRIORITY[b.modeKey] || OBJECTIVE_DEFAULT_PRIORITY);
      if (metricPriorityA !== metricPriorityB) {
        return metricPriorityA - metricPriorityB;
      }
      const dateA = a.recordedAt || a.startedAt;
      const dateB = b.recordedAt || b.startedAt;
      const timeA = dateA instanceof Date ? dateA.getTime() : 0;
      const timeB = dateB instanceof Date ? dateB.getTime() : 0;
      if (timeA !== timeB) {
        return timeB - timeA;
      }
      return a.player.localeCompare(b.player, locale);
    });
  }
  state.objectiveLeaderboard = sortObjectiveEntries(bestByKey);
  state.eliminationLeaderboard = sortObjectiveEntries(eliminationBestByKey);
}

function prepareEliminationMetricContext(entries) {
  const positions = new Map();
  let ordinal = 0;
  if (!Array.isArray(entries)) {
    return { rosterSize: 0, positions };
  }
  for (let index = 0; index < entries.length; index += 1) {
    const entry = entries[index];
    const player = extractLeaderboardPlayer(entry);
    if (!player) {
      continue;
    }
    ordinal += 1;
    positions.set(index, {
      ordinalPosition: ordinal,
      explicitPosition: extractEliminationPosition(entry)
    });
  }
  return { rosterSize: ordinal, positions };
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

async function fetchMapMetadata() {
  const metadata = new Map();
  try {
    const response = await fetch(`${API_BASE}/maps/metadata`);
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }
    const payload = await response.json();
    const mapEntries = payload && typeof payload === 'object' && payload.maps && typeof payload.maps === 'object'
      ? payload.maps
      : null;
    if (!mapEntries) {
      return metadata;
    }
    Object.entries(mapEntries).forEach(([rawKey, rawName]) => {
      if (typeof rawName !== 'string') {
        return;
      }
      const displayName = rawName.trim();
      if (!displayName) {
        return;
      }
      const normalizedKey = normalizeLevelshotKey(rawKey);
      if (!normalizedKey) {
        const { base } = extractMapBase(rawKey);
        if (base) {
          const fallbackKey = normalizeLevelshotKey(base);
          if (fallbackKey) {
            metadata.set(fallbackKey, displayName);
          }
        }
        return;
      }
      metadata.set(normalizedKey, displayName);
    });
  } catch (error) {
    console.warn('Failed to load map metadata', error);
  }
  return metadata;
}

async function loadMatches() {
  setModeStatus('mode.status.loading');
  try {
    state.mapMetadata = new Map();
    const response = await fetch(`${API_BASE}/matches?limit=all`);
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }
    const payload = await response.json();
    if (!payload || !Array.isArray(payload.matches)) {
      throw new Error(t('errors.unexpectedResponse'));
    }
    state.allMatches = payload.matches;
    if (!state.allMatches.length) {
      state.leaderboard = [];
      state.deathmatchLeaderboard = [];
      state.objectiveLeaderboard = [];
      state.eliminationLeaderboard = [];
      state.modeData = new Map();
      state.mapMetadata = new Map();
      updateSummary();
      updateModeOptions();
      MODE_CONFIG.forEach(({ key }) => renderModeTable(key));
      setModeStatus('mode.status.empty');
      return;
    }
    state.mapMetadata = await fetchMapMetadata();
    buildRaceLeaderboard();
    buildDeathmatchLeaderboard();
    buildObjectiveLeaderboard();
    prepareModeData();
    updateSummary();
    updateModeOptions();
    MODE_CONFIG.forEach(({ key }) => renderModeTable(key));
    setModeStatus('mode.status.ready', { count: state.allMatches.length });
  } catch (error) {
    console.error(error);
    state.allMatches = [];
    state.leaderboard = [];
    state.deathmatchLeaderboard = [];
    state.objectiveLeaderboard = [];
    state.eliminationLeaderboard = [];
    state.modeData = new Map();
    state.mapMetadata = new Map();
    updateSummary();
    updateModeOptions();
    MODE_CONFIG.forEach(({ key }) => renderModeTable(key));
    setModeStatus('mode.status.error', { message: error.message }, true);
  }
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

function normalizeVehicleName(value) {
  if (value === null || value === undefined) {
    return '';
  }
  const text = String(value).trim();
  if (text === '') {
    return '';
  }
  const slashIndex = text.indexOf('/');
  const base = (slashIndex === -1 ? text : text.slice(0, slashIndex)).trim();
  if (base === '') {
    return '';
  }
  const lower = base.toLowerCase();
  return lower.charAt(0).toUpperCase() + lower.slice(1);
}

function extractVehicle(entry) {
  if (!entry || typeof entry !== 'object') {
    return '';
  }
  const vehicle = normalizeVehicleName(firstString(entry, VEHICLE_PATH_CANDIDATES));
  if (vehicle) {
    return vehicle;
  }
  if (entry.stats && typeof entry.stats === 'object') {
    const statsVehicle = normalizeVehicleName(firstString(entry.stats, VEHICLE_PATH_CANDIDATES));
    if (statsVehicle) {
      return statsVehicle;
    }
  }
  if (entry.result && typeof entry.result === 'object') {
    return normalizeVehicleName(firstString(entry.result, VEHICLE_PATH_CANDIDATES));
  }
  return '';
}

function extractIsBot(entry) {
  if (!entry || typeof entry !== 'object') {
    return false;
  }
  for (const path of BOT_PATH_CANDIDATES) {
    const value = valueAtPath(entry, path);
    const interpreted = interpretBoolean(value);
    if (interpreted !== null) {
      return interpreted;
    }
  }
  return false;
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
  if (hours > 0) {
    return `${hours}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}.${millis.toString().padStart(3, '0')}`;
  }
  return `${minutes}:${secs.toString().padStart(2, '0')}.${millis.toString().padStart(3, '0')}`;
}

function extractKillDeath(entry) {
  if (!entry || typeof entry !== 'object') {
    return { kills: null, deaths: null };
  }
  let kills = numericAtPaths(entry, KILL_PATH_CANDIDATES);
  let deaths = numericAtPaths(entry, DEATH_PATH_CANDIDATES);
  if (kills === null) {
    kills = searchNumericByKeywords(entry, ['kill', 'frag']);
  }
  if (deaths === null) {
    deaths = searchNumericByKeywords(entry, ['death']);
  }
  if (kills === null && deaths === null) {
    return { kills: null, deaths: null };
  }
  const safeKills = Math.max(0, kills ?? 0);
  const safeDeaths = Math.max(0, deaths ?? 0);
  return { kills: safeKills, deaths: safeDeaths };
}

function priorityIndex(type, priority) {
  const index = priority.indexOf(type);
  return index === -1 ? priority.length : index;
}

function extractObjectiveMetric(entry, modeKey, context = null) {
  if (!entry || typeof entry !== 'object') {
    return { value: null, type: null };
  }
  if (modeKey === 'gt_elimination') {
    const winsDefinition = OBJECTIVE_METRIC_DEFINITIONS.wins;
    let value = null;
    if (winsDefinition && winsDefinition.paths) {
      value = numericAtPaths(entry, winsDefinition.paths);
    }
    if (value === null) {
      value = searchNumericByKeywords(entry, ['win', 'won', 'victor']);
    }
    if (value === null) {
      value = deriveEliminationWins(entry, context);
    }
    if (value !== null) {
      return { value: Math.max(0, value), type: 'wins' };
    }
    const fallbackPriority = (OBJECTIVE_MODE_PRIORITY[modeKey] || OBJECTIVE_DEFAULT_PRIORITY).filter(
      (type) => type !== 'wins'
    );
    for (const type of fallbackPriority) {
      const definition = OBJECTIVE_METRIC_DEFINITIONS[type];
      if (!definition) {
        continue;
      }
      let fallbackValue = null;
      if (definition.paths) {
        fallbackValue = numericAtPaths(entry, definition.paths);
      }
      if (fallbackValue === null && definition.keywords) {
        fallbackValue = searchNumericByKeywords(entry, definition.keywords);
      }
      if (fallbackValue !== null) {
        return { value: Math.max(0, fallbackValue), type };
      }
    }
    return { value: null, type: null };
  }
  const priority = OBJECTIVE_MODE_PRIORITY[modeKey] || OBJECTIVE_DEFAULT_PRIORITY;
  for (const type of priority) {
    const definition = OBJECTIVE_METRIC_DEFINITIONS[type];
    if (!definition) {
      continue;
    }
    let value = null;
    if (definition.paths) {
      value = numericAtPaths(entry, definition.paths);
    }
    if (value === null && definition.keywords) {
      value = searchNumericByKeywords(entry, definition.keywords);
    }
    if (value !== null) {
      return { value: Math.max(0, value), type };
    }
  }
  return { value: null, type: null };
}

function deriveEliminationWins(entry, context = null) {
  const rosterFromContext = normalizePositiveInteger(context && context.rosterSize);
  const contextPosition = normalizePositiveInteger(context && context.position);
  const ordinalPosition = normalizePositiveInteger(context && context.ordinalPosition);
  const entryPosition = contextPosition ?? extractEliminationPosition(entry);
  const basePosition = entryPosition ?? ordinalPosition ?? null;
  const rosterFromEntry = normalizePositiveInteger(numericAtPaths(entry, ELIMINATION_ROSTER_PATHS));
  const effectiveRoster = rosterFromContext ?? rosterFromEntry ?? null;
  if (effectiveRoster && basePosition) {
    return Math.max(0, effectiveRoster - basePosition + 1);
  }
  const remainingPlayers = normalizePositiveInteger(numericAtPaths(entry, ELIMINATION_REMAINING_PATHS));
  if (effectiveRoster && remainingPlayers) {
    return Math.max(0, effectiveRoster - remainingPlayers + 1);
  }
  if (remainingPlayers !== null) {
    return Math.max(0, 1000 - remainingPlayers);
  }
  if (basePosition !== null) {
    return Math.max(0, 1000 - basePosition);
  }
  return null;
}

function extractEliminationPosition(entry) {
  if (!entry || typeof entry !== 'object') {
    return null;
  }
  const position = numericAtPaths(entry, ELIMINATION_POSITION_PATHS);
  return normalizePositiveInteger(position);
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

function parseNumericValue(value) {
  if (typeof value === 'number' && Number.isFinite(value)) {
    return value;
  }
  if (typeof value === 'string') {
    const normalized = value.trim().replace(',', '.');
    if (normalized === '') {
      return null;
    }
    const numeric = Number(normalized);
    return Number.isFinite(numeric) ? numeric : null;
  }
  return null;
}

function normalizePositiveInteger(value) {
  if (typeof value === 'string') {
    const numeric = parseNumericValue(value);
    if (numeric === null) {
      return null;
    }
    value = numeric;
  }
  if (typeof value !== 'number' || !Number.isFinite(value)) {
    return null;
  }
  const integer = Math.floor(value);
  return integer > 0 ? integer : null;
}

function interpretBoolean(value) {
  if (value === null || value === undefined) {
    return null;
  }
  if (typeof value === 'boolean') {
    return value;
  }
  if (typeof value === 'number') {
    return Number.isFinite(value) ? value !== 0 : null;
  }
  if (typeof value === 'string') {
    const normalized = value.trim().toLowerCase();
    if (!normalized) {
      return null;
    }
    if (
      normalized === '1' ||
      normalized === 'true' ||
      normalized === 'yes' ||
      normalized === 'y' ||
      normalized === 'ja' ||
      normalized === 'bot' ||
      normalized === 'ai' ||
      normalized === 'cpu' ||
      normalized === 'computer' ||
      normalized === 'on' ||
      normalized === 't'
    ) {
      return true;
    }
    if (
      normalized === '0' ||
      normalized === 'false' ||
      normalized === 'no' ||
      normalized === 'n' ||
      normalized === 'nein' ||
      normalized === 'human' ||
      normalized === 'player' ||
      normalized === 'real' ||
      normalized === 'off' ||
      normalized === 'f'
    ) {
      return false;
    }
  }
  return null;
}

function numericAtPaths(obj, paths) {
  for (const path of paths) {
    const value = valueAtPath(obj, path);
    const numeric = parseNumericValue(value);
    if (numeric !== null) {
      return numeric;
    }
  }
  return null;
}

function searchNumericByKeywords(node, keywords, visited = new Set()) {
  if (node === null || node === undefined) {
    return null;
  }
  if (typeof node !== 'object') {
    return null;
  }
  if (visited.has(node)) {
    return null;
  }
  visited.add(node);
  if (Array.isArray(node)) {
    for (const item of node) {
      const result = searchNumericByKeywords(item, keywords, visited);
      if (result !== null) {
        return result;
      }
    }
    return null;
  }
  for (const [key, value] of Object.entries(node)) {
    const lowerKey = key.toLowerCase();
    if (keywords.some((keyword) => lowerKey.includes(keyword))) {
      const numeric = parseNumericValue(value);
      if (numeric !== null) {
        return numeric;
      }
    }
    const nested = searchNumericByKeywords(value, keywords, visited);
    if (nested !== null) {
      return nested;
    }
  }
  return null;
}

function searchStringByKeywords(node, keywords, visited = new Set()) {
  if (node === null || node === undefined) {
    return '';
  }
  if (typeof node === 'string') {
    return node.trim();
  }
  if (typeof node !== 'object') {
    return '';
  }
  if (visited.has(node)) {
    return '';
  }
  visited.add(node);
  if (Array.isArray(node)) {
    for (const item of node) {
      const found = searchStringByKeywords(item, keywords, visited);
      if (found) {
        return found;
      }
    }
    return '';
  }
  for (const [key, value] of Object.entries(node)) {
    const lowerKey = key.toLowerCase();
    if (keywords.some((keyword) => lowerKey.includes(keyword))) {
      if (typeof value === 'string' && value.trim() !== '') {
        return value.trim();
      }
    }
    const nested = searchStringByKeywords(value, keywords, visited);
    if (nested) {
      return nested;
    }
  }
  return '';
}

function extractMode(match) {
  return firstString(match, [
    'mode',
    'match.mode',
    'gameMode',
    'game_type',
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

function extractScoreboardEntries(match) {
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
  return entries;
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
    'match.players',
    'participants',
    'scoreboard',
    'results',
    'leaderboard'
  ]);
  if (!candidates.length) {
    return [];
  }
  return candidates
    .map((entry) => {
      if (typeof entry === 'string') {
        return entry.trim();
      }
      if (entry && typeof entry === 'object') {
        const name = firstString(entry, PLAYER_PATH_CANDIDATES);
        if (name) {
          return name;
        }
        return searchStringByKeywords(entry, ['name', 'player', 'driver']);
      }
      return '';
    })
    .filter(Boolean);
}

function parseDate(value) {
  if (value instanceof Date && !Number.isNaN(value.getTime())) {
    return value;
  }
  if (typeof value === 'number' && Number.isFinite(value)) {
    const date = new Date(value * (value > 1_000_000_000 ? 1 : 1000));
    return Number.isNaN(date.getTime()) ? null : date;
  }
  if (typeof value === 'string') {
    const trimmed = value.trim();
    if (!trimmed) {
      return null;
    }
    const numeric = Number(trimmed);
    if (!Number.isNaN(numeric)) {
      return parseDate(numeric);
    }
    const parsed = new Date(trimmed);
    return Number.isNaN(parsed.getTime()) ? null : parsed;
  }
  return null;
}

createModeTabs();
applyLanguage(state.language);
setActiveMode(state.activeMode);
elements.languageButtons.forEach((button) => {
  button.addEventListener('click', () => {
    const lang = button.dataset.lang;
    if (lang && lang !== state.language) {
      applyLanguage(lang);
    }
  });
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
    if ($segments === ['maps', 'metadata']) {
        $entries = load_arena_metadata();
        $normalized = [];
        foreach ($entries as $map => $longname) {
            if (!is_string($map) || !is_string($longname)) {
                continue;
            }
            $normalizedKey = normalize_map_key($map);
            if ($normalizedKey === '') {
                continue;
            }
            $label = trim($longname);
            if ($label === '') {
                continue;
            }
            $normalized[$normalizedKey] = $label;
        }

        ksort($normalized, SORT_NATURAL | SORT_FLAG_CASE);

        send_json(['maps' => $normalized], 200);
        return;
    }

    if ($segments === ['servers']) {
        if (!function_exists('socket_create')) {
            send_error(500, 'PHP sockets extension is required to query the master server.');
        }

        $payload = build_server_overview();
        send_json($payload, 200);
        return;
    }

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
        $limitParam = $_GET['limit'] ?? null;
        if (is_string($limitParam) && strcasecmp($limitParam, 'all') === 0) {
            $slice = array_slice($matches, $offset);
        } else {
            $limit = $limitParam !== null ? max(1, (int) $limitParam) : 100;
            $slice = array_slice($matches, $offset, $limit);
        }

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

function build_server_overview(): array
{
    $host = DEFAULT_MASTER_HOST;
    $hostParam = $_GET['masterHost'] ?? $_GET['master_host'] ?? null;
    if (is_string($hostParam)) {
        $candidate = trim($hostParam);
        if ($candidate !== '' && preg_match('/^[A-Za-z0-9._-]+$/', $candidate)) {
            $host = $candidate;

        }
    }

    $port = DEFAULT_MASTER_PORT;
    $portParam = $_GET['masterPort'] ?? $_GET['master_port'] ?? null;
    if ($portParam !== null && $portParam !== '') {
        $portCandidate = (int) $portParam;
        if ($portCandidate > 0 && $portCandidate <= 65535) {
            $port = $portCandidate;
        }
    }

    $limit = DEFAULT_MASTER_LIMIT;
    $limitParam = $_GET['limit'] ?? null;
    if ($limitParam !== null && $limitParam !== '') {
        $limitCandidate = (int) $limitParam;
        if ($limitCandidate > 0) {
            $limit = min($limitCandidate, 256);
        }
    }

    $timeout = DEFAULT_MASTER_TIMEOUT;
    $timeoutParam = $_GET['timeout'] ?? $_GET['masterTimeout'] ?? null;
    if ($timeoutParam !== null && $timeoutParam !== '') {
        $timeoutCandidate = (int) $timeoutParam;
        if ($timeoutCandidate >= 1 && $timeoutCandidate <= 10) {
            $timeout = $timeoutCandidate;
        }
    }

    $game = DEFAULT_MASTER_GAME;
    $gameParam = $_GET['game'] ?? null;
    if (is_string($gameParam)) {
        $gameCandidate = trim($gameParam);
        if ($gameCandidate !== '' && preg_match('/^[A-Za-z0-9._-]+$/', $gameCandidate)) {
            $game = strtolower($gameCandidate);
        }
    }

    $addressLimit = min(max($limit * 3, $limit), 256);
    $useOverride = $hostParam !== null || $portParam !== null;
    $masters = $useOverride ? [[
        'host' => $host,
        'port' => $port,
    ]] : DEFAULT_MASTER_SERVERS;

    $addressMap = [];
    $addressList = [];
    $sources = [];

    foreach ($masters as $master) {
        $masterHost = $master['host'];
        $masterPort = $master['port'];
        $entries = query_master_server_list($masterHost, $masterPort, $timeout, max($addressLimit, 1), $game);
        $sources[] = [
            'host' => $masterHost,
            'port' => $masterPort,
            'listed' => count($entries),
        ];

        foreach ($entries as $entry) {
            $key = $entry['address'];
            if (!isset($addressMap[$key])) {
                $addressMap[$key] = true;
                $addressList[] = $entry;
            }
        }
    }

    $servers = [];
    $gameFilter = $game !== '' ? strtolower($game) : null;

    foreach ($addressList as $entry) {
        if (count($servers) >= $limit) {
            break;
        }

        $status = query_server_status($entry['ip'], $entry['port'], $timeout);
        if ($status === null) {
            continue;
        }

        if ($gameFilter !== null) {
            $infoGame = null;
            $info = $status['info'];
            if (isset($info['game']) && is_string($info['game'])) {
                $infoGame = $info['game'];
            } elseif (isset($info['fs_game']) && is_string($info['fs_game'])) {
                $infoGame = $info['fs_game'];
            } elseif ($status['mod'] !== null && $status['mod'] !== '') {
                $infoGame = $status['mod'];
            } elseif (isset($info['gamename']) && is_string($info['gamename'])) {
                $infoGame = $info['gamename'];
            } elseif (isset($info['gametype_name']) && is_string($info['gametype_name'])) {
                $infoGame = $info['gametype_name'];
            }

            if ($infoGame === null) {
                continue;
            }

            $infoGame = trim((string) $infoGame);

            if ($infoGame === '' || stripos($infoGame, $gameFilter) === false) {
                continue;
            }
        }


        $servers[] = [
            'name' => $status['hostname'],
            'rawName' => $status['hostnameRaw'],
            'address' => $entry['address'],
            'ip' => $entry['ip'],
            'port' => $entry['port'],
            'map' => $status['map'],
            'mode' => $status['modeKey'],
            'modeName' => $status['modeName'],
            'mod' => $status['mod'],
            'players' => $status['players'],
            'playerDetails' => $status['playerDetails'],
            'playerCount' => $status['numPlayers'],
            'maxPlayers' => $status['maxPlayers'],
            'ping' => $status['ping'],
            'respondedAt' => $status['respondedAt'],
            'info' => $status['info'],
        ];
    }


    $totalListed = count($addressList);
    $reachable = count($servers);
    $unreachable = max(0, $totalListed - $reachable);

    $primaryMaster = $masters[0] ?? ['host' => $host, 'port' => $port];

    return [
        'master' => [
            'host' => $primaryMaster['host'] ?? $host,
            'port' => $primaryMaster['port'] ?? $port,
            'timeout' => $timeout,
        ],
        'masters' => array_map(static function ($source) use ($timeout) {
            return [
                'host' => $source['host'],
                'port' => $source['port'],
                'timeout' => $timeout,
                'listed' => $source['listed'],
            ];
        }, $sources),
        'filters' => [
            'game' => $game,
        ],

        'summary' => [
            'totalListed' => $totalListed,
            'reachable' => $reachable,
            'unreachable' => $unreachable,
        ],
        'limit' => $limit,
        'generatedAt' => gmdate('c'),
        'servers' => array_values($servers),
    ];
}


function query_master_server_list(string $host, int $port, int $timeout, int $limit, ?string $game = null): array

{
    $limit = max(1, min($limit, 512));
    $socket = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
    if ($socket === false) {
        return [];

    }

    @socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => $timeout, 'usec' => 0]);
    @socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => $timeout, 'usec' => 0]);

    $message = "\xFF\xFF\xFF\xFFgetservers 68 empty full";
    if (is_string($game) && $game !== '') {
        $message .= '\\game\\' . $game;
    }
    $message .= "\x00";
    @socket_sendto($socket, $message, strlen($message), 0, $host, $port);


    $servers = [];
    $seen = [];
    $complete = false;

    while (!$complete && count($servers) < $limit) {
        $buffer = '';
        $remoteHost = '';
        $remotePort = 0;
        $bytes = @socket_recvfrom($socket, $buffer, 4096, 0, $remoteHost, $remotePort);
        if ($bytes === false || $bytes === 0) {
            break;
        }

        $parsed = parse_master_server_payload($buffer);
        foreach ($parsed['servers'] as $entry) {
            $key = $entry['address'];
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $servers[] = $entry;
                if (count($servers) >= $limit) {
                    break;
                }
            }
        }

        if ($parsed['complete']) {
            $complete = true;
        }
    }

    @socket_close($socket);

    return $servers;
}

function parse_master_server_payload(string $payload): array
{
    $result = [
        'servers' => [],
        'complete' => false,
    ];

    $marker = 'getserversResponse';
    $start = strpos($payload, $marker);
    if ($start === false) {
        return $result;
    }

    $dataPosition = strpos($payload, '\\', $start);
    if ($dataPosition === false) {
        return $result;
    }

    $data = substr($payload, $dataPosition);
    if ($data === false) {
        return $result;
    }

    $length = strlen($data);
    $offset = 0;

    while ($offset < $length) {
        if ($data[$offset] !== '\\') {
            $offset++;
            continue;
        }

        $offset++;
        if ($offset >= $length) {
            break;
        }

        if (substr($data, $offset, 3) === 'EOT') {
            $result['complete'] = true;
            break;
        }

        if ($offset + 5 >= $length) {
            break;
        }

        $chunk = substr($data, $offset, 6);
        if ($chunk === false || strlen($chunk) < 6) {
            break;
        }

        $ipBytes = unpack('C4', substr($chunk, 0, 4));
        $portBytes = unpack('n', substr($chunk, 4, 2));
        if ($ipBytes === false || $portBytes === false) {
            break;
        }

        $ip = implode('.', $ipBytes);
        $port = (int) $portBytes[1];
        if ($port <= 0 || $port > 65535) {
            $offset += 6;
            continue;
        }

        $result['servers'][] = [
            'ip' => $ip,
            'port' => $port,
            'address' => $ip . ':' . $port,
        ];

        $offset += 6;
    }

    return $result;
}

function query_server_status(string $ip, int $port, int $timeout): ?array
{
    $socket = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
    if ($socket === false) {
        return null;
    }

    @socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => $timeout, 'usec' => 0]);
    @socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => $timeout, 'usec' => 0]);

    $request = "\xFF\xFF\xFF\xFFgetstatus\x00";
    $start = microtime(true);
    @socket_sendto($socket, $request, strlen($request), 0, $ip, $port);

    $buffer = '';
    $remoteHost = '';
    $remotePort = 0;
    $bytes = @socket_recvfrom($socket, $buffer, 65535, 0, $remoteHost, $remotePort);
    @socket_close($socket);

    if ($bytes === false || $bytes === 0) {
        return null;
    }

    $parsed = parse_status_response($buffer);
    if ($parsed === null) {
        return null;
    }

    $ping = (int) round((microtime(true) - $start) * 1000);

    $info = $parsed['info'];
    $infoLower = array_change_key_case($info, CASE_LOWER);

    $hostnameRaw = $info['sv_hostname'] ?? $info['hostname'] ?? null;
    $hostname = $hostnameRaw !== null ? trim(strip_quake_color_codes($hostnameRaw)) : null;
    if ($hostname === '') {
        $hostname = null;
    }

    $mapRaw = $info['mapname'] ?? $info['map'] ?? null;
    $map = $mapRaw !== null ? trim($mapRaw) : null;
    if ($map === '') {
        $map = null;
    }

    $modRaw = $infoLower['game'] ?? $infoLower['fs_game'] ?? null;
    $mod = is_string($modRaw) ? trim($modRaw) : null;
    if ($mod === '') {
        $mod = null;
    }

    [$modeKey, $modeName] = resolve_gametype_from_info($infoLower, $info);

    $playerDetails = [];
    foreach ($parsed['players'] as $player) {
        $nameRaw = is_string($player['name']) ? $player['name'] : '';
        $decoded = stripcslashes($nameRaw);
        $name = trim(strip_quake_color_codes($decoded));
        if ($name === '') {
            $name = null;
        }

        $playerDetails[] = [
            'name' => $name,
            'rawName' => $decoded,
            'score' => (int) $player['score'],
            'ping' => (int) $player['ping'],
        ];
    }

    $playerNames = array_values(array_filter(array_map(static function (array $player): ?string {
        return $player['name'];
    }, $playerDetails)));

    $numPlayers = null;
    foreach (['clients', 'players'] as $key) {
        if (isset($infoLower[$key]) && is_numeric($infoLower[$key])) {
            $numPlayers = max(0, (int) $infoLower[$key]);
            break;
        }
    }
    if ($numPlayers === null) {
        $numPlayers = count($playerNames);
    }

    $maxPlayers = null;
    foreach (['sv_maxclients', 'maxclients', 'sv_max_clients'] as $key) {
        if (isset($infoLower[$key]) && is_numeric($infoLower[$key])) {
            $maxPlayers = max(0, (int) $infoLower[$key]);
            break;
        }
    }

    return [
        'hostname' => $hostname,
        'hostnameRaw' => $hostnameRaw,
        'map' => $map,
        'modeKey' => $modeKey,
        'modeName' => $modeName,
        'mod' => $mod,
        'numPlayers' => $numPlayers,
        'maxPlayers' => $maxPlayers,
        'ping' => $ping >= 0 ? $ping : null,
        'players' => $playerNames,
        'playerDetails' => $playerDetails,
        'info' => $info,
        'respondedAt' => gmdate('c'),
    ];
}

function parse_status_response(string $payload): ?array
{
    $prefix = "\xFF\xFF\xFF\xFFstatusResponse\n";
    if (strncmp($payload, $prefix, strlen($prefix)) !== 0) {
        return null;
    }

    $body = substr($payload, strlen($prefix));
    if ($body === false) {
        return null;

    }

    $lines = preg_split('/\r?\n/', $body);
    if ($lines === false || $lines === []) {
        return null;
    }

    $infoLine = array_shift($lines);
    if ($infoLine === null) {
        return null;
    }


    $info = [];
    $tokens = explode('\\', $infoLine);
    $count = count($tokens);
    for ($i = 1; $i + 1 < $count; $i += 2) {
        $key = trim($tokens[$i]);
        $value = $tokens[$i + 1];
        if ($key === '') {
            continue;
        }
        $info[$key] = $value;
    }

    $players = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        if (preg_match('/^(-?\d+)\s+(-?\d+)\s+"(.*)"$/', $line, $matches) === 1) {
            $players[] = [
                'score' => (int) $matches[1],
                'ping' => (int) $matches[2],
                'name' => $matches[3],
            ];
        }
    }

    return [
        'info' => $info,
        'players' => $players,
    ];
}

function strip_quake_color_codes(string $value): string
{
    return preg_replace('/\^[0-9A-Za-z]/', '', $value) ?? $value;
}

function resolve_gametype_from_info(array $infoLower, array $infoOriginal): array
{
    $value = null;
    foreach (['g_gametype', 'gametype', 'gametype_current', 'gametypevalue', 'gt_gametype'] as $key) {
        if (isset($infoLower[$key]) && $infoLower[$key] !== '') {
            $value = $infoLower[$key];
            break;
        }
    }
    if ($value === null) {
        foreach (['g_gametype', 'gametype'] as $key) {
            if (isset($infoOriginal[$key]) && $infoOriginal[$key] !== '') {
                $value = $infoOriginal[$key];
                break;
            }
        }
    }

    $label = null;
    foreach (['gametype_name', 'gamename', 'game'] as $key) {
        if (isset($infoLower[$key]) && $infoLower[$key] !== '') {
            $label = $infoLower[$key];
            break;
        }
    }
    if ($label === null) {
        foreach (['gametype_name', 'gamename', 'game'] as $key) {
            if (isset($infoOriginal[$key]) && $infoOriginal[$key] !== '') {
                $label = $infoOriginal[$key];
                break;
            }
        }
    }

    $valueString = $value !== null ? trim((string) $value) : '';
    if ($valueString === '') {
        $labelString = $label !== null ? trim((string) $label) : '';
        if ($labelString === '') {
            return [null, null];
        }
        $normalized = strtolower($labelString);
        $normalized = preg_replace('/[^a-z0-9]+/', '_', $normalized) ?? '';
        $normalized = trim($normalized, '_');
        return [$normalized !== '' ? $normalized : null, $labelString];
    }

    if (ctype_digit($valueString)) {
        $key = 'gametype_' . $valueString;
        $labelString = $label !== null ? trim((string) $label) : '';
        if ($labelString === '' && isset(MASTER_GAMETYPE_LABELS[$key])) {
            $labelString = MASTER_GAMETYPE_LABELS[$key];
        }
        return [$key, $labelString !== '' ? $labelString : null];
    }

    $normalized = strtolower($valueString);
    $normalized = preg_replace('/[^a-z0-9]+/', '_', $normalized) ?? '';
    $normalized = trim($normalized, '_');
    if ($normalized === '') {
        $normalized = null;
    }
    $labelString = $label !== null ? trim((string) $label) : '';
    if ($labelString === '' && $normalized !== null && isset(MASTER_GAMETYPE_LABELS[$normalized])) {
        $labelString = MASTER_GAMETYPE_LABELS[$normalized];
    }

    return [$normalized, $labelString !== '' ? $labelString : null];
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
