<?php
// Lightweight Ladder API for simple webspace deployments.
// Store match payloads as JSON files under data/.

declare(strict_types=1);

const DATA_DIR = __DIR__ . '/data';
const ARENA_DIR = __DIR__ . '/arena';
const VERSION_FILE = __DIR__ . '/version.json';
const VERSION_PASSWORD_FALLBACK_FILE = __DIR__ . '/version.password';
const MASTER_SERVERS = [
    ['host' => 'master.ioquake3.org', 'port' => 27950, 'label' => 'master.ioquake3.org'],
    ['host' => 'dpmaster.deathmask.net', 'port' => 27950, 'label' => 'dpmaster.deathmask.net'],
];
const SERVER_DEFAULT_PROTOCOL = 71;
const SERVER_STATUS_TIMEOUT = 1.5;
const SERVER_STATUS_MAX = 256;
if (!is_dir(DATA_DIR)) {
    if (!mkdir(DATA_DIR, 0775, true) && !is_dir(DATA_DIR)) {
        send_error(500, 'Failed to create data directory.');
    }
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$uri    = $_SERVER['REQUEST_URI'] ?? '/';
$script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
$path   = parse_url($uri, PHP_URL_PATH) ?? '/';
$path   = rtrim($path, '/');
$normalized = $path;
if ($script !== '' && strpos($normalized, $script) === 0) {
    $normalized = substr($normalized, strlen($script));
}
$normalized = trim($normalized, '/');

// --- Minimal frontend for Q3Rally Ladder (HTML landing page) ---
try {
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $isFrontendRoute = ($normalized === '' && ($path === '' || $path === '/' || $path === $script));

    if ($method === 'GET' && $isFrontendRoute && (strpos($accept, 'application/json') === false)) {
        $apiBase = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
        $assetBase = rtrim(str_replace('\\', '/', dirname($apiBase)), '/');
        $assetPrefix = $assetBase === '' ? '' : $assetBase;
        $flagDe = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA1IDMiPjxyZWN0IHdpZHRoPSI1IiBoZWlnaHQ9IjMiIGZpbGw9IiNmZmNlMDAiLz48cmVjdCB3aWR0aD0iNSIgaGVpZ2h0PSIyIiBmaWxsPSIjZGQwMDAwIi8+PHJlY3Qgd2lkdGg9IjUiIGhlaWdodD0iMSIgZmlsbD0iIzAwMCIvPjwvc3ZnPg==';
        $flagEn = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA2MCAzMCI+PGNsaXBQYXRoIGlkPSJhIj48cGF0aCBkPSJNMCAwaDYwdjMwSDB6Ii8+PC9jbGlwUGF0aD48Y2xpcFBhdGggaWQ9ImIiPjxwYXRoIGQ9Ik0zMCAxNUgwdjE1bDYwLTMwSDBWMGw2MCAzMEgzMHYxNUg2MFYwSDMwdjE1eiIvPjwvY2xpcFBhdGg+PGcgY2xpcC1wYXRoPSJ1cmwoI2EpIj48cGF0aCBkPSJNMCAwaDYwdjMwSDB6IiBmaWxsPSIjMDEyMTY5Ii8+PHBhdGggZD0iTTAgMGw2MCAzME02MCAwTDAgMzAiIHN0cm9rZT0iI2ZmZiIgc3Ryb2tlLXdpZHRoPSI2IiBjbGlwLXBhdGg9InVybCgjYikiLz48cGF0aCBkPSJNMCAwbDYwIDMwTTYwIDBMMCAzMCIgc3Ryb2tlPSIjQzgxMDJFIiBzdHJva2Utd2lkdGg9IjQiIGNsaXAtcGF0aD0idXJsKCNiKSIvPjxwYXRoIGQ9Ik0zMCAwdjMwTTAgMTVoNjAiIHN0cm9rZT0iI2ZmZiIgc3Ryb2tlLXdpZHRoPSIxMCIvPjxwYXRoIGQ9Ik0zMCAwdjMwTTAgMTVoNjAiIHN0cm9rZT0iI0M4MTAyRSIgc3Ryb2tlLXdpZHRoPSI2Ii8+PC9nPjwvc3ZnPg==';
        header('Content-Type: text/html; charset=UTF-8');
        ?>
<!doctype html>
<html lang="en">
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
      appearance: none;
      border: none;
      background: transparent;
      color: var(--text-muted);
      font-weight: 600;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      font-size: 0.78rem;
      padding: 8px 16px;
      border-radius: 12px;
      transition: background 140ms ease, color 140ms ease, box-shadow 140ms ease;
      cursor: pointer;
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

    .server-browser {
      display: flex;
      flex-direction: column;
      gap: 24px;
    }

    .server-browser-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 18px;
      flex-wrap: wrap;
    }

    .server-browser-headline {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    .server-browser-actions {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .server-browser-stats {
      margin: 0;
      padding: 0;
      list-style: none;
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 12px;
    }

    .server-browser-stats div {
      background: rgba(255, 255, 255, 0.05);
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: 14px;
      padding: 14px 16px;
      display: flex;
      flex-direction: column;
      gap: 6px;
    }

    .server-browser-stats dt {
      margin: 0;
      font-size: 0.78rem;
      text-transform: uppercase;
      color: var(--text-muted);
      letter-spacing: 0.08em;
    }

    .server-browser-stats dd {
      margin: 0;
      font-weight: 600;
      font-size: 1.1rem;
    }

    .server-browser-stats dd.error {
      color: #ffb7b7;
    }

    .server-table {
      width: 100%;
      border-collapse: collapse;
      min-width: 100%;
    }

    .server-table thead {
      background: rgba(255, 255, 255, 0.04);
    }

    .server-table th,
    .server-table td {
      padding: 12px 14px;
      text-align: left;
      border-bottom: 1px solid rgba(255, 255, 255, 0.05);
      vertical-align: top;
    }

    .server-table tbody tr:hover {
      background: rgba(255, 255, 255, 0.03);
    }

    .server-table strong {
      font-weight: 600;
    }

    .server-players-list {
      margin: 6px 0 0;
      font-size: 0.82rem;
      color: var(--text-muted);
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
    }

    .server-players-list span {
      background: rgba(255, 255, 255, 0.06);
      padding: 2px 8px 3px;
      border-radius: 999px;
    }

    .server-browser .status {
      margin: 0;
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
            <div data-view-section="matches">
              <h1 data-i18n-html="hero.title">Q3Rally Ladder Monitor <span class="badge-beta">beta</span></h1>
              <p data-i18n-html="hero.description">Direkte Vorschau der besten Platzierungen je Spielmodus und Map. Wähle oben einen Modus und vergleiche die Top 10 pro Strecke – inklusive Levelshot-Vorschau.</p>
            </div>
            <div data-view-section="servers" hidden>
              <h1 data-i18n-html="hero.servers.title">Q3Rally Serverbrowser <span class="badge-beta">beta</span></h1>
              <p data-i18n-html="hero.servers.description">Durchsuche die Q3Rally Masterserver nach aktiven Gameservern und sieh dir Spieler, Map und Modus direkt im Browser an.</p>
            </div>
          </div>
        </div>
        <div class="hero-actions">
          <nav class="main-nav" aria-label="Bereiche" data-i18n-aria-label="nav.label">
            <button class="nav-link active" type="button" data-view="matches" aria-current="page" aria-pressed="true" data-i18n="nav.matches">Matches</button>
            <button class="nav-link" type="button" data-view="servers" aria-pressed="false" data-i18n="nav.servers">Serverbrowser</button>
          </nav>
          <div class="language-toggle" role="group" aria-label="Sprachauswahl" data-i18n-aria-label="language.toggleLabel">
            <button class="language-button active" type="button" data-lang="en" aria-label="Englisch" data-i18n-aria-label="language.enLabel" title="Englisch" data-i18n-title="language.enLabel"><img src="<?= htmlspecialchars($flagEn, ENT_QUOTES); ?>" alt=""></button>
            <button class="language-button" type="button" data-lang="de" aria-label="Deutsch" data-i18n-aria-label="language.deLabel" title="Deutsch" data-i18n-title="language.deLabel"><img src="<?= htmlspecialchars($flagDe, ENT_QUOTES); ?>" alt=""></button>
          </div>
        </div>
        <p class="empty-state" id="leaderboardEmpty" hidden data-i18n="matches.empty">Keine Bestzeiten gefunden. Lade weitere Renn-Matches oder passe die Filter an.</p>
      </div>

      <dl class="hero-stats" data-view-section="matches">
        <div class="stat"><dt data-i18n="stats.matches">Matches</dt><dd id="stat-total">–</dd></div>
        <div class="stat"><dt data-i18n="stats.lastUpdate">Letztes Update</dt><dd id="stat-last">–</dd></div>
        <div class="stat"><dt data-i18n="stats.modes">Spielmodi</dt><dd id="stat-modes">–</dd></div>
        <div class="stat"><dt data-i18n="stats.players">Spieler erfasst</dt><dd id="stat-players">–</dd></div>
      </dl>
      <dl class="hero-stats" data-view-section="servers" hidden>
        <div class="stat"><dt data-i18n="servers.stats.servers">Server</dt><dd id="server-stat-total">–</dd></div>
        <div class="stat"><dt data-i18n="servers.stats.players">Spieler online</dt><dd id="server-stat-players">–</dd></div>
        <div class="stat"><dt data-i18n="servers.stats.updated">Stand</dt><dd id="server-stat-updated">–</dd></div>
      </dl>
    </section>

    <section class="panel tabbed-panel" data-view-section="matches">
      <div class="tab-header" id="modeTabs" role="tablist" aria-label="Spielmodi" data-i18n-aria-label="tabs.label"></div>
      <p class="status" id="modeStatus"></p>
      <div id="modePanels" class="mode-panels"></div>
    </section>

    <section class="panel" data-view-section="matches">

      <h2 style="margin:0; font-size:1.05rem; letter-spacing:0.02em; text-transform:uppercase; color:var(--text-muted);" data-i18n="breakdown.heading">Modus-Verteilung</h2>

      <ul id="modeBreakdown"></ul>
    </section>

    <section class="panel server-browser" id="serverBrowser" data-view-section="servers" hidden>
      <div class="server-browser-header">
        <div class="server-browser-headline">
          <h2 data-i18n="servers.heading">Serverbrowser</h2>
          <p class="status" id="serverStatus"></p>
        </div>
        <div class="server-browser-actions">
          <button type="button" id="serverRefresh" data-i18n="servers.refresh">Aktualisieren</button>
        </div>
      </div>
      <dl class="server-browser-stats" id="serverOverview"></dl>
      <p class="empty-state" id="serverEmpty" hidden data-i18n="servers.empty">Keine Server gefunden.</p>
      <div class="table-wrapper">
        <table class="server-table">
          <thead>
            <tr>
              <th scope="col" data-i18n="servers.table.headers.name">Server</th>
              <th scope="col" data-i18n="servers.table.headers.map">Map</th>
              <th scope="col" data-i18n="servers.table.headers.players">Spieler</th>
              <th scope="col" data-i18n="servers.table.headers.game">Spiel/Mod</th>
              <th scope="col" data-i18n="servers.table.headers.address">Adresse</th>
              <th scope="col" data-i18n="servers.table.headers.sources">Master</th>
            </tr>
          </thead>
          <tbody id="serverTable"></tbody>
        </table>
      </div>
    </section>
  </main>

  <script>
    const API_BASE = <?= json_encode($apiBase, JSON_UNESCAPED_SLASHES); ?>;

    const SERVER_PROTOCOL = 71;
    const SERVER_REFRESH_MIN_INTERVAL = 30_000;

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
        'servers.stats.servers': 'Server',
        'servers.stats.players': 'Spieler online',
        'servers.stats.updated': 'Stand',
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
        'hero.servers.title': 'Q3Rally Serverbrowser <span class="badge-beta">beta</span>',
        'hero.servers.description': 'Durchsuche die Q3Rally Masterserver nach aktiven Gameservern und sieh dir Spieler, Map und Modus direkt im Browser an.',
        'servers.heading': 'Serverbrowser',
        'servers.refresh': 'Aktualisieren',
        'servers.empty': 'Keine Server gefunden.',
        'servers.status.loading': 'Lade Serverliste…',
        'servers.status.error': 'Fehler beim Laden: {message}',
        'servers.status.empty': 'Keine Server mit dem aktuellen Protokoll gefunden.',
        'servers.status.count': 'Zeige {count} von {total} Servern.',
        'servers.table.headers.name': 'Server',
        'servers.table.headers.map': 'Map',
        'servers.table.headers.players': 'Spieler',
        'servers.table.headers.game': 'Spiel/Mod',
        'servers.table.headers.address': 'Adresse',
        'servers.table.headers.sources': 'Master',
        'servers.player.ping': 'Ping: {ping}',
        'servers.player.score': 'Score: {score}',
        'servers.unknown': 'Unbekannt',
        'servers.unknownMap': 'Unbekannte Map',
        'servers.gametype.value': 'Gametype {value}',
        'servers.overview.total': 'Gesamtserver',
        'servers.overview.protocol': 'Protokoll',
        'servers.overview.masterFallback': 'Masterserver',
        'servers.master.count': '{count} Server',
        'servers.master.error': 'Fehler: {message}',
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
        'servers.stats.servers': 'Servers',
        'servers.stats.players': 'Players online',
        'servers.stats.updated': 'Updated',
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
        'hero.servers.title': 'Q3Rally Server Browser <span class="badge-beta">beta</span>',
        'hero.servers.description': 'Browse the Q3Rally master servers for active game servers and inspect players, map, and mode right in your browser.',
        'servers.heading': 'Server Browser',
        'servers.refresh': 'Refresh',
        'servers.empty': 'No servers found.',
        'servers.status.loading': 'Loading servers…',
        'servers.status.error': 'Load error: {message}',
        'servers.status.empty': 'No servers found for the selected protocol.',
        'servers.status.count': 'Showing {count} of {total} servers.',
        'servers.table.headers.name': 'Server',
        'servers.table.headers.map': 'Map',
        'servers.table.headers.players': 'Players',
        'servers.table.headers.game': 'Game/Mod',
        'servers.table.headers.address': 'Address',
        'servers.table.headers.sources': 'Masters',
        'servers.player.ping': 'Ping: {ping}',
        'servers.player.score': 'Score: {score}',
        'servers.unknown': 'Unknown',
        'servers.unknownMap': 'Unknown map',
        'servers.gametype.value': 'Gametype {value}',
        'servers.overview.total': 'Total servers',
        'servers.overview.protocol': 'Protocol',
        'servers.overview.masterFallback': 'Master server',
        'servers.master.count': '{count} servers',
        'servers.master.error': 'Error: {message}',
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
  activeView: 'matches',
  language: 'en',
  modeStatus: { key: null, params: {}, isError: false },
  serverBrowser: {
    isLoading: false,
    error: null,
    servers: [],
    totalServers: 0,
    totalPlayers: 0,
    masters: [],
    lastUpdated: null,
    protocol: SERVER_PROTOCOL,
    lastFetch: 0,
    abortController: null
  }
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
  navLinks: Array.from(document.querySelectorAll('.nav-link[data-view]')),
  viewSections: Array.from(document.querySelectorAll('[data-view-section]')),
  html: document.documentElement,
  metaDescription: document.querySelector('meta[name="description"]'),
  serverPanel: document.getElementById('serverBrowser'),
  serverStatus: document.getElementById('serverStatus'),
  serverTable: document.getElementById('serverTable'),
  serverEmpty: document.getElementById('serverEmpty'),
  serverRefresh: document.getElementById('serverRefresh'),
  serverOverview: document.getElementById('serverOverview'),
  serverStatTotal: document.getElementById('server-stat-total'),
  serverStatPlayers: document.getElementById('server-stat-players'),
  serverStatUpdated: document.getElementById('server-stat-updated')
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

function updateViewVisibility() {
  elements.viewSections.forEach((element) => {
    const targets = (element.dataset.viewSection || '')
      .split(',')
      .map((entry) => entry.trim())
      .filter(Boolean);
    const visible = !targets.length || targets.includes(state.activeView);
    element.hidden = !visible;
  });
  elements.navLinks.forEach((link) => {
    const isActive = link.dataset.view === state.activeView;
    link.classList.toggle('active', isActive);
    if (isActive) {
      link.setAttribute('aria-current', 'page');
      link.setAttribute('aria-pressed', 'true');
    } else {
      link.removeAttribute('aria-current');
      link.setAttribute('aria-pressed', 'false');
    }
  });
}

function updateServerHeroStats() {
  if (!elements.serverStatTotal || !elements.serverStatPlayers || !elements.serverStatUpdated) {
    return;
  }
  elements.serverStatTotal.textContent = state.serverBrowser.totalServers
    ? state.serverBrowser.totalServers.toString()
    : '–';
  elements.serverStatPlayers.textContent = state.serverBrowser.totalPlayers
    ? state.serverBrowser.totalPlayers.toString()
    : '–';
  const timestamp = state.serverBrowser.lastUpdated;
  elements.serverStatUpdated.textContent = timestamp instanceof Date && !Number.isNaN(timestamp.getTime())
    ? formatter.format(timestamp)
    : '–';
}

function sanitizeServerPlayer(player) {
  if (!player || typeof player !== 'object') {
    return null;
  }
  const name = typeof player.name === 'string' ? player.name.trim() : '';
  if (!name) {
    return null;
  }
  const pingValue = Number(player.ping);
  const scoreValue = Number(player.score);
  return {
    name,
    ping: Number.isFinite(pingValue) ? pingValue : null,
    score: Number.isFinite(scoreValue) ? scoreValue : null
  };
}

function sanitizeServerPayload(server) {
  if (!server || typeof server !== 'object') {
    return null;
  }
  const address = typeof server.address === 'string' ? server.address.trim() : '';
  if (!address) {
    return null;
  }
  const hostname = typeof server.hostname === 'string' ? server.hostname.trim() : '';
  const map = typeof server.map === 'string' ? server.map.trim() : '';
  const gamename = typeof server.gamename === 'string' ? server.gamename.trim() : '';
  const mode = typeof server.mode === 'string' ? server.mode.trim() : '';
  const mod = typeof server.mod === 'string' ? server.mod.trim() : '';
  const protocolValue = Number(server.protocol);
  const gametypeValue = Number(server.gametype);
  const sources = Array.isArray(server.sources)
    ? server.sources
        .map((source) => (typeof source === 'string' ? source.trim() : ''))
        .filter(Boolean)
    : [];
  const players = Array.isArray(server.players)
    ? server.players.map(sanitizeServerPlayer).filter(Boolean)
    : [];
  const numPlayersValue = Number(server.numPlayers);
  const maxPlayersValue = Number(server.maxPlayers);
  const updatedAt = parseDate(server.updatedAt ?? server.statusAt ?? server.recordedAt);
  return {
    address,
    hostname: hostname || null,
    map: map || null,
    gamename: gamename || null,
    mode: mode || null,
    mod: mod || null,
    protocol: Number.isFinite(protocolValue) ? protocolValue : null,
    gametype: Number.isFinite(gametypeValue) ? gametypeValue : null,
    numPlayers: Number.isFinite(numPlayersValue) && numPlayersValue >= 0 ? numPlayersValue : players.length,
    maxPlayers: Number.isFinite(maxPlayersValue) && maxPlayersValue > 0 ? maxPlayersValue : null,
    players,
    sources,
    updatedAt: updatedAt instanceof Date && !Number.isNaN(updatedAt.getTime()) ? updatedAt : null
  };
}

function renderServerOverview() {
  if (!elements.serverOverview) {
    return;
  }
  elements.serverOverview.innerHTML = '';
  const fragment = document.createDocumentFragment();
  const totalWrapper = document.createElement('div');
  const totalLabel = document.createElement('dt');
  totalLabel.textContent = t('servers.overview.total');
  const totalValue = document.createElement('dd');
  totalValue.textContent = state.serverBrowser.totalServers
    ? state.serverBrowser.totalServers.toString()
    : '0';
  totalWrapper.appendChild(totalLabel);
  totalWrapper.appendChild(totalValue);
  fragment.appendChild(totalWrapper);

  const protocolWrapper = document.createElement('div');
  const protocolLabel = document.createElement('dt');
  protocolLabel.textContent = t('servers.overview.protocol');
  const protocolValue = document.createElement('dd');
  protocolValue.textContent = state.serverBrowser.protocol
    ? state.serverBrowser.protocol.toString()
    : SERVER_PROTOCOL.toString();
  protocolWrapper.appendChild(protocolLabel);
  protocolWrapper.appendChild(protocolValue);
  fragment.appendChild(protocolWrapper);

  state.serverBrowser.masters.forEach((master) => {
    const wrapper = document.createElement('div');
    const label = document.createElement('dt');
    label.textContent = master.label || master.host || t('servers.overview.masterFallback');
    const value = document.createElement('dd');
    if (master.error) {
      value.textContent = t('servers.master.error', { message: master.error });
      value.classList.add('error');
    } else {
      const count = typeof master.count === 'number' && master.count >= 0 ? master.count : 0;
      value.textContent = t('servers.master.count', { count });
    }
    wrapper.appendChild(label);
    wrapper.appendChild(value);
    fragment.appendChild(wrapper);
  });

  elements.serverOverview.appendChild(fragment);
}

function renderServerBrowser() {
  if (!elements.serverTable || !elements.serverStatus) {
    return;
  }
  const status = elements.serverStatus;
  const { isLoading, error, servers } = state.serverBrowser;
  status.classList.toggle('error', Boolean(error));
  if (isLoading) {
    status.textContent = t('servers.status.loading');
  } else if (error) {
    status.textContent = t('servers.status.error', { message: error.message || String(error) });
  } else if (!servers.length) {
    status.textContent = t('servers.status.empty');
  } else {
    status.textContent = t('servers.status.count', {
      count: servers.length,
      total: state.serverBrowser.totalServers || servers.length
    });
  }
  if (elements.serverEmpty) {
    elements.serverEmpty.hidden = Boolean(isLoading || error || servers.length);
  }
  elements.serverTable.innerHTML = '';
  if (servers.length) {
    const sorted = [...servers].sort((a, b) => {
      const left = Number.isFinite(a.numPlayers) ? a.numPlayers : 0;
      const right = Number.isFinite(b.numPlayers) ? b.numPlayers : 0;
      return right - left;
    });
    sorted.forEach((server) => {
      const row = document.createElement('tr');

      const nameCell = document.createElement('td');
      nameCell.textContent = server.hostname || t('servers.unknown');
      row.appendChild(nameCell);

      const mapCell = document.createElement('td');
      mapCell.textContent = server.map || t('servers.unknownMap');
      row.appendChild(mapCell);

      const playersCell = document.createElement('td');
      const playerCount = Number.isFinite(server.numPlayers) ? server.numPlayers : 0;
      const strong = document.createElement('strong');
      if (Number.isFinite(server.maxPlayers) && server.maxPlayers) {
        strong.textContent = `${playerCount} / ${server.maxPlayers}`;
      } else {
        strong.textContent = `${playerCount}`;
      }
      playersCell.appendChild(strong);
      if (server.players.length) {
        const list = document.createElement('div');
        list.className = 'server-players-list';
        server.players.forEach((player) => {
          const badge = document.createElement('span');
          badge.textContent = player.name;
          if (Number.isFinite(player.ping) || Number.isFinite(player.score)) {
            const pingPart = Number.isFinite(player.ping) ? t('servers.player.ping', { ping: player.ping }) : '';
            const scorePart = Number.isFinite(player.score) ? t('servers.player.score', { score: player.score }) : '';
            const meta = [pingPart, scorePart].filter(Boolean).join(' · ');
            if (meta) {
              badge.title = meta;
            }
          }
          list.appendChild(badge);
        });
        playersCell.appendChild(list);
      }
      row.appendChild(playersCell);

      const gameCell = document.createElement('td');
      const metaParts = [];
      if (server.gamename) {
        metaParts.push(server.gamename);
      }
      if (server.mode) {
        metaParts.push(server.mode);
      } else if (Number.isFinite(server.gametype)) {
        metaParts.push(t('servers.gametype.value', { value: server.gametype }));
      }
      if (server.mod) {
        metaParts.push(server.mod);
      }
      gameCell.textContent = metaParts.length ? metaParts.join(' · ') : t('servers.unknown');
      row.appendChild(gameCell);

      const addressCell = document.createElement('td');
      addressCell.textContent = server.address;
      row.appendChild(addressCell);

      const sourcesCell = document.createElement('td');
      sourcesCell.textContent = server.sources.length
        ? server.sources.join(', ')
        : t('servers.unknown');
      row.appendChild(sourcesCell);

      elements.serverTable.appendChild(row);
    });
  }
  updateServerHeroStats();
  renderServerOverview();
}

function setActiveView(view) {
  if (view !== 'matches' && view !== 'servers') {
    return;
  }
  if (state.activeView === view) {
    return;
  }
  state.activeView = view;
  updateViewVisibility();
  if (view === 'servers') {
    ensureServersLoaded();
  }
}

function ensureServersLoaded(force = false) {
  if (force) {
    loadServers(true);
    return;
  }
  if (!state.serverBrowser.servers.length) {
    loadServers();
    return;
  }
  const lastFetch = state.serverBrowser.lastFetch;
  const now = Date.now();
  if (now - lastFetch > SERVER_REFRESH_MIN_INTERVAL * 2) {
    loadServers();
  }
}

async function loadServers(force = false) {
  if (state.serverBrowser.isLoading && !force) {
    return;
  }
  const now = Date.now();
  if (!force && now - state.serverBrowser.lastFetch < SERVER_REFRESH_MIN_INTERVAL) {
    return;
  }
  if (state.serverBrowser.abortController) {
    try {
      state.serverBrowser.abortController.abort();
    } catch (abortError) {
      console.warn(abortError);
    }
  }
  let controller = null;
  if (typeof AbortController !== 'undefined') {
    controller = new AbortController();
    state.serverBrowser.abortController = controller;
  }
  state.serverBrowser.isLoading = true;
  state.serverBrowser.error = null;
  renderServerBrowser();
  try {
    const url = `${API_BASE}/servers?protocol=${encodeURIComponent(state.serverBrowser.protocol || SERVER_PROTOCOL)}`;
    const response = await fetch(url, controller ? { signal: controller.signal } : undefined);
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }
    const payload = await response.json();
    const servers = Array.isArray(payload.servers)
      ? payload.servers.map(sanitizeServerPayload).filter(Boolean)
      : [];
    state.serverBrowser.servers = servers;
    const totalServers = Number(payload.total);
    state.serverBrowser.totalServers = Number.isFinite(totalServers)
      ? totalServers
      : servers.length;
    state.serverBrowser.totalPlayers = servers.reduce((sum, server) => {
      const value = Number.isFinite(server.numPlayers) ? server.numPlayers : 0;
      return sum + value;
    }, 0);
    const protocolValue = Number(payload.protocol);
    state.serverBrowser.protocol = Number.isFinite(protocolValue)
      ? protocolValue
      : SERVER_PROTOCOL;
    const generatedAt = parseDate(payload.generatedAt);
    state.serverBrowser.lastUpdated = generatedAt instanceof Date && !Number.isNaN(generatedAt.getTime())
      ? generatedAt
      : new Date();
    state.serverBrowser.lastFetch = now;
    state.serverBrowser.masters = Array.isArray(payload.masters)
      ? payload.masters.map((master) => {
          const host = typeof master.host === 'string' ? master.host : '';
          const label = typeof master.label === 'string' && master.label.trim() ? master.label.trim() : host;
          const count = Number(master.count);
          const errorMessage = typeof master.error === 'string' ? master.error.trim() : '';
          return {
            host,
            label,
            count: Number.isFinite(count) && count >= 0 ? count : 0,
            error: errorMessage || null
          };
        })
      : [];
    state.serverBrowser.error = null;
  } catch (error) {
    if (error && error.name === 'AbortError') {
      return;
    }
    console.error(error);
    state.serverBrowser.error = error instanceof Error ? error : new Error(String(error));
  } finally {
    state.serverBrowser.lastFetch = now;
    state.serverBrowser.isLoading = false;
    state.serverBrowser.abortController = null;
    renderServerBrowser();
  }
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
  renderServerBrowser();
  updateServerHeroStats();
  updateViewVisibility();
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
elements.navLinks.forEach((link) => {
  link.addEventListener('click', (event) => {
    event.preventDefault();
    const view = link.dataset.view;
    if (view) {
      setActiveView(view);
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
if (elements.serverRefresh) {
  elements.serverRefresh.addEventListener('click', () => {
    ensureServersLoaded(true);
  });
}
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


<<<<<<< HEAD
    $pathInfo = $normalized;
    $path = trim($pathInfo, '/');
    $segments = $path === '' ? [] : explode('/', $path);
=======
$pathInfo = $_SERVER['PATH_INFO'] ?? '';
if ($pathInfo === '' && isset($_SERVER['REQUEST_URI'])) {
    $requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';

    if ($scriptName !== '' && strpos($requestPath, $scriptName) === 0) {
        $pathInfo = substr($requestPath, strlen($scriptName));
    } else {
        $scriptDir = $scriptName !== '' ? rtrim(dirname($scriptName), '/\\') : '';
        if ($scriptDir !== '' && strpos($requestPath, $scriptDir) === 0) {
            $pathInfo = substr($requestPath, strlen($scriptDir));
        } else {
            $pathInfo = $requestPath;
        }
    }
}

$path = trim($pathInfo, '/');
$segments = $path === '' ? [] : explode('/', $path);
>>>>>>> 8fbe6eb9a9fb916a56751d6ecc65e288f150d2f3

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
} catch (Throwable $e) {
    // if the frontend fails for any reason, continue with API logic
}

function handle_post(array $segments): void
{
    if ($segments === ['matches']) {
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
        return;
    }

    if ($segments === ['version']) {
        handle_post_version();
        return;
    }

    send_error(404, 'Endpoint not found.');
}

function handle_post_version(): void
{
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        throw new RuntimeException('Invalid JSON payload.');
    }

    $password = $payload['password'] ?? null;
    if (!is_string($password) || trim($password) === '') {
        throw new RuntimeException('password is required.');
    }

    $expected = resolve_version_password();
    if ($expected === null || $expected === '') {
        send_error(403, 'Version updates are disabled.');
    }

    if (!hash_equals($expected, $password)) {
        send_error(403, 'Forbidden.');
    }

    $versionInput = $payload['version'] ?? $payload['latest'] ?? $payload['latestVersion'] ?? null;
    if (!is_string($versionInput) || trim($versionInput) === '') {
        throw new RuntimeException('version is required.');
    }

    $version = normalize_version_string($versionInput);

    $downloadUrl = null;
    if (array_key_exists('downloadUrl', $payload)) {
        $downloadUrl = normalize_optional_url($payload['downloadUrl']);
    } elseif (array_key_exists('url', $payload)) {
        $downloadUrl = normalize_optional_url($payload['url']);
    }

    $message = null;
    if (array_key_exists('message', $payload)) {
        $message = normalize_optional_message($payload['message']);
    } elseif (array_key_exists('notes', $payload)) {
        $message = normalize_optional_message($payload['notes']);
    }

    $record = [
        'version' => $version,
        'downloadUrl' => $downloadUrl,
        'message' => $message,
        'updatedAt' => gmdate('c'),
    ];

    persist_version_record($record);

    send_version_payload($record);
}

function handle_get(array $segments): void
{
    if ($segments === ['version']) {
        $record = load_version_record();
        if ($record === null) {
            send_error(404, 'Version not configured.');
        }

        send_version_payload($record);
        return;
    }

    if ($segments === ['servers']) {
        $protocolParam = $_GET['protocol'] ?? null;
        $protocol = SERVER_DEFAULT_PROTOCOL;
        if (is_string($protocolParam) || is_numeric($protocolParam)) {
            $protocol = max(0, (int) $protocolParam);
        }
        $limitParam = $_GET['limit'] ?? null;
        $statusLimit = null;
        if ($limitParam !== null && $limitParam !== '') {
            $statusLimit = max(1, (int) $limitParam);
        }

        $payload = collect_master_server_data($protocol, $statusLimit);
        send_json($payload, 200);
        return;
    }

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

function collect_master_server_data(int $protocol, ?int $statusLimit = null): array
{
    $protocol = max(0, $protocol);
    $statusCap = SERVER_STATUS_MAX;
    if ($statusLimit !== null) {
        $statusCap = max(1, min($statusLimit, SERVER_STATUS_MAX));
    }

    $serverSources = [];
    $masters = [];
    foreach (MASTER_SERVERS as $definition) {
        $host = $definition['host'] ?? null;
        if (!is_string($host) || $host === '') {
            continue;
        }
        $port = isset($definition['port']) ? (int) $definition['port'] : 27950;
        $label = isset($definition['label']) && is_string($definition['label']) && trim($definition['label']) !== ''
            ? trim($definition['label'])
            : $host;
        try {
            $addresses = query_master_server_addresses($host, $port, $protocol, SERVER_STATUS_TIMEOUT);
            $masters[] = [
                'host' => $host,
                'port' => $port,
                'label' => $label,
                'count' => count($addresses),
            ];
            foreach ($addresses as $address) {
                if (!isset($serverSources[$address])) {
                    $serverSources[$address] = [];
                }
                $serverSources[$address][] = $label;
            }
        } catch (Throwable $e) {
            $masters[] = [
                'host' => $host,
                'port' => $port,
                'label' => $label,
                'count' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    $addresses = array_keys($serverSources);
    sort($addresses, SORT_NATURAL);

    $statusAddresses = $addresses;
    if (count($statusAddresses) > $statusCap) {
        $statusAddresses = array_slice($statusAddresses, 0, $statusCap);
    }

    $statusMap = fetch_server_statuses($statusAddresses, SERVER_STATUS_TIMEOUT);
    $servers = [];
    foreach ($addresses as $address) {
        $status = $statusMap[$address] ?? null;
        $servers[] = normalize_server_entry($address, $serverSources[$address] ?? [], $status);
    }

    return [
        'protocol' => $protocol,
        'generatedAt' => gmdate('c'),
        'total' => count($addresses),
        'masters' => $masters,
        'servers' => $servers,
    ];
}

function query_master_server_addresses(string $host, int $port, int $protocol, float $timeout = SERVER_STATUS_TIMEOUT): array
{
    $uri = sprintf('udp://%s:%d', $host, $port);
    $errno = 0;
    $errstr = '';
    $socket = @stream_socket_client($uri, $errno, $errstr, $timeout);
    if (!is_resource($socket)) {
        $reason = $errstr !== '' ? $errstr : (string) $errno;
        throw new RuntimeException(sprintf('Failed to contact master server: %s', $reason));
    }

    $seconds = (int) floor($timeout);
    $microseconds = (int) (($timeout - $seconds) * 1_000_000);
    stream_set_timeout($socket, $seconds, $microseconds);

    $payload = sprintf("\xFF\xFF\xFF\xFFgetservers %d empty full\n", $protocol);
    $written = fwrite($socket, $payload);
    if ($written === false || $written === 0) {
        fclose($socket);
        throw new RuntimeException('Failed to send request to master server.');
    }

    $response = '';
    while (!feof($socket)) {
        $chunk = fread($socket, 4096);
        if ($chunk === false) {
            break;
        }
        if ($chunk === '') {
            $meta = stream_get_meta_data($socket);
            if (!empty($meta['timed_out'])) {
                break;
            }
        }
        if ($chunk === '') {
            break;
        }
        $response .= $chunk;
        if (strpos($response, '\\EOT') !== false) {
            break;
        }
    }

    fclose($socket);

    if ($response === '') {
        throw new RuntimeException('Empty response from master server.');
    }

    return parse_master_server_response($response);
}

function parse_master_server_response(string $payload): array
{
    $marker = 'getserversResponse';
    $offset = strpos($payload, $marker);
    if ($offset === false) {
        throw new RuntimeException('Unexpected response from master server.');
    }

    $data = substr($payload, $offset + strlen($marker));
    if ($data === false) {
        return [];
    }
    if ($data !== '' && $data[0] === "\n") {
        $data = substr($data, 1);
    }

    $addresses = [];
    $length = strlen($data);
    for ($index = 0; $index < $length; $index++) {
        if ($data[$index] !== '\\') {
            continue;
        }
        if (substr($data, $index, 4) === '\\EOT') {
            break;
        }
        if ($index + 6 >= $length) {
            break;
        }
        $ipBytes = substr($data, $index + 1, 4);
        $portBytes = substr($data, $index + 5, 2);
        if ($ipBytes === false || $portBytes === false) {
            break;
        }
        $ipParts = array_map('ord', str_split($ipBytes));
        if (count($ipParts) !== 4) {
            $index += 6;
            continue;
        }
        $ip = implode('.', $ipParts);
        $port = (ord($portBytes[0]) << 8) + ord($portBytes[1]);
        if ($ip === '0.0.0.0' || $port <= 0) {
            $index += 6;
            continue;
        }
        $addresses[] = sprintf('%s:%d', $ip, $port);
        $index += 6;
    }

    return array_values(array_unique($addresses));
}

function fetch_server_statuses(array $addresses, float $timeout = SERVER_STATUS_TIMEOUT): array
{
    $results = [];
    $socketTargets = [];
    $fallbackTargets = [];

    foreach ($addresses as $address) {
        if (!is_string($address) || $address === '') {
            continue;
        }
        $parsed = parse_server_address($address);
        if ($parsed === null) {
            continue;
        }
        [$host, $port] = $parsed;
        $key = sprintf('%s:%d', $host, $port);
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $socketTargets[$key] = [$host, $port];
        } else {
            $fallbackTargets[$key] = [$host, $port];
        }
    }

    $payload = "\xFF\xFF\xFF\xFFgetstatus\n";
    if ($socketTargets && function_exists('socket_create')) {
        $socket = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if ($socket !== false) {
            $sec = (int) floor($timeout);
            $usec = (int) (($timeout - $sec) * 1_000_000);
            socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => $sec, 'usec' => $usec]);
            socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => $sec, 'usec' => $usec]);
            foreach ($socketTargets as $target) {
                @socket_sendto($socket, $payload, strlen($payload), 0, $target[0], $target[1]);
            }
            $deadline = microtime(true) + $timeout;
            $retryable = [];
            foreach (['SOCKET_EWOULDBLOCK', 'SOCKET_EAGAIN', 'SOCKET_ETIMEDOUT'] as $constant) {
                if (defined($constant)) {
                    $retryable[] = constant($constant);
                }
            }
            while (microtime(true) < $deadline) {
                $buffer = '';
                $remoteAddress = '';
                $remotePort = 0;
                $bytes = @socket_recvfrom($socket, $buffer, 8192, 0, $remoteAddress, $remotePort);
                if ($bytes === false) {
                    $errorCode = socket_last_error($socket);
                    if (in_array($errorCode, $retryable, true)) {
                        break;
                    }
                    break;
                }
                $key = sprintf('%s:%d', $remoteAddress, $remotePort);
                $parsed = parse_server_status_response($buffer);
                if ($parsed !== null) {
                    $results[$key] = $parsed;
                }
            }
            socket_close($socket);
        }
    }

    $allTargets = $socketTargets + $fallbackTargets;
    foreach ($allTargets as $key => [$host, $port]) {
        if (isset($results[$key])) {
            continue;
        }
        $status = query_single_server_status($host, $port, $timeout);
        if ($status !== null) {
            $results[$key] = $status;
        }
    }

    return $results;
}

function query_single_server_status(string $host, int $port, float $timeout = SERVER_STATUS_TIMEOUT): ?array
{
    $uri = sprintf('udp://%s:%d', $host, $port);
    $errno = 0;
    $errstr = '';
    $socket = @fsockopen($uri, $errno, $errstr, $timeout);
    if (!is_resource($socket)) {
        return null;
    }
    $sec = (int) floor($timeout);
    $usec = (int) (($timeout - $sec) * 1_000_000);
    stream_set_timeout($socket, $sec, $usec);

    $written = fwrite($socket, "\xFF\xFF\xFF\xFFgetstatus\n");
    if ($written === false || $written === 0) {
        fclose($socket);
        return null;
    }

    $response = stream_get_contents($socket);
    fclose($socket);
    if ($response === false || $response === '') {
        return null;
    }

    return parse_server_status_response($response);
}

function parse_server_status_response(string $payload): ?array
{
    if ($payload === '') {
        return null;
    }
    $trimmed = trim(ltrim($payload, "\xFF"));
    if ($trimmed === '') {
        return null;
    }
    if (stripos($trimmed, 'statusResponse') !== 0) {
        return null;
    }

    $lines = preg_split('/\r?\n/', $trimmed);
    if ($lines === false || !isset($lines[0])) {
        return null;
    }
    if (stripos($lines[0], 'statusResponse') !== 0) {
        return null;
    }

    $infoLine = $lines[1] ?? '';
    $info = [];
    if ($infoLine !== '') {
        $parts = explode('\\', $infoLine);
        $count = count($parts);
        for ($i = 1; $i + 1 < $count; $i += 2) {
            $key = strtolower(trim((string) $parts[$i]));
            if ($key === '') {
                continue;
            }
            $info[$key] = $parts[$i + 1] ?? '';
        }
    }

    $players = [];
    $totalLines = count($lines);
    for ($i = 2; $i < $totalLines; $i++) {
        $line = trim((string) $lines[$i]);
        if ($line === '') {
            continue;
        }
        if (!preg_match('/^(-?\d+)\s+(-?\d+)\s+(.*)$/', $line, $matches)) {
            continue;
        }
        $name = strip_color_codes(trim($matches[3], '"'));
        if ($name === '') {
            continue;
        }
        $players[] = [
            'score' => (int) $matches[1],
            'ping' => (int) $matches[2],
            'name' => $name,
        ];
    }

    return [
        'info' => $info,
        'players' => $players,
    ];
}

function normalize_server_entry(string $address, array $sources, ?array $status): array
{
    $entry = [
        'address' => $address,
        'sources' => array_values(array_unique(array_map(static function ($source) {
            return (string) $source;
        }, $sources))),
        'hostname' => null,
        'map' => null,
        'gamename' => null,
        'mode' => null,
        'mod' => null,
        'protocol' => null,
        'gametype' => null,
        'numPlayers' => 0,
        'maxPlayers' => null,
        'players' => [],
        'updatedAt' => null,
    ];

    if ($status === null) {
        return $entry;
    }

    $info = [];
    if (isset($status['info']) && is_array($status['info'])) {
        foreach ($status['info'] as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            $info[strtolower($key)] = $value;
        }
    }

    $players = [];
    if (isset($status['players']) && is_array($status['players'])) {
        foreach ($status['players'] as $player) {
            if (!is_array($player)) {
                continue;
            }
            $name = isset($player['name']) ? strip_color_codes((string) $player['name']) : '';
            if ($name === '') {
                continue;
            }
            $players[] = [
                'name' => $name,
                'score' => isset($player['score']) ? (int) $player['score'] : null,
                'ping' => isset($player['ping']) ? (int) $player['ping'] : null,
            ];
        }
    }

    $hostname = first_info_value($info, ['sv_hostname', 'hostname']);
    if ($hostname !== null) {
        $entry['hostname'] = strip_color_codes($hostname);
    }
    $map = first_info_value($info, ['mapname', 'map', 'map_name']);
    if ($map !== null) {
        $entry['map'] = strip_color_codes($map);
    }
    $gamename = first_info_value($info, ['gamename', 'sv_gamename']);
    if ($gamename !== null) {
        $entry['gamename'] = strip_color_codes($gamename);
    }
    $mode = first_info_value($info, ['gametypename', 'gametype_name', 'g_gametype_name', 'mode']);
    if ($mode !== null) {
        $entry['mode'] = strip_color_codes($mode);
    }
    $mod = first_info_value($info, ['game', 'fs_game', 'mod']);
    if ($mod !== null) {
        $entry['mod'] = strip_color_codes($mod);
    }

    $gametypeValue = first_info_value($info, ['g_gametype', 'gametype']);
    if ($gametypeValue !== null) {
        $entry['gametype'] = (int) $gametypeValue;
    }

    $protocolValue = first_info_value($info, ['protocol', 'sv_protocol']);
    if ($protocolValue !== null) {
        $entry['protocol'] = (int) $protocolValue;
    }

    $clientsValue = first_info_value($info, ['clients', 'sv_clients']);
    if ($clientsValue !== null) {
        $entry['numPlayers'] = max(0, (int) $clientsValue);
    } else {
        $entry['numPlayers'] = count($players);
    }

    $maxClientsValue = first_info_value($info, ['sv_maxclients', 'maxclients']);
    if ($maxClientsValue !== null) {
        $entry['maxPlayers'] = max(0, (int) $maxClientsValue);
    }

    $entry['players'] = $players;
    $entry['updatedAt'] = gmdate('c');

    return $entry;
}

function parse_server_address(string $address): ?array
{
    $parts = explode(':', $address, 2);
    if (count($parts) !== 2) {
        return null;
    }
    $host = trim($parts[0]);
    $port = (int) $parts[1];
    if ($host === '' || $port <= 0 || $port > 65535) {
        return null;
    }
    return [$host, $port];
}

function first_info_value(array $info, array $keys): ?string
{
    foreach ($keys as $key) {
        $normalizedKey = strtolower($key);
        if (!array_key_exists($normalizedKey, $info)) {
            continue;
        }
        $value = $info[$normalizedKey];
        if (!is_scalar($value)) {
            continue;
        }
        $string = trim((string) $value);
        if ($string !== '') {
            return $string;
        }
    }

    return null;
}

function strip_color_codes(string $value): string
{
    $result = preg_replace('/\^x[0-9a-fA-F]{6}/', '', $value);
    if ($result === null) {
        $result = $value;
    }
    $result = preg_replace('/\^\d{1,2}/', '', $result);
    if ($result === null) {
        $result = $value;
    }
    $result = preg_replace('/[\x00-\x1F\x7F]/', '', $result);
    if ($result === null) {
        $result = $value;
    }

    return ensure_utf8_string(trim($result));
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

function send_version_payload(array $record): void
{
    $response = [
        'latest' => $record['version'],
        'latestVersion' => $record['version'],
        'version' => $record['version'],
    ];

    if (isset($record['downloadUrl']) && $record['downloadUrl'] !== null) {
        $response['downloadUrl'] = $record['downloadUrl'];
        $response['url'] = $record['downloadUrl'];
    }

    if (isset($record['message']) && $record['message'] !== null) {
        $response['message'] = $record['message'];
        $response['notes'] = $record['message'];
    }

    if (isset($record['updatedAt']) && is_string($record['updatedAt'])) {
        $response['updatedAt'] = $record['updatedAt'];
    }

    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $wantsPlain = $accept !== ''
        && strpos($accept, 'text/plain') !== false
        && strpos($accept, 'application/json') === false;

    if ($wantsPlain) {
        header('Content-Type: text/plain; charset=UTF-8');

        $lines = [$record['version']];
        if (!empty($record['downloadUrl'])) {
            $lines[] = $record['downloadUrl'];
        }
        if (!empty($record['message'])) {
            $message = preg_replace('/\s+/', ' ', str_replace(["\r\n", "\r"], ' ', (string) $record['message']));
            if ($message !== null && $message !== '') {
                $lines[] = $message;
            }
        }

        echo implode("\n", $lines);
        exit;
    }

    send_json($response, 200);
}

function load_version_record(): ?array
{
    if (!is_file(VERSION_FILE)) {
        return null;
    }

    $json = file_get_contents(VERSION_FILE);
    if ($json === false) {
        return null;
    }

    $payload = json_decode($json, true);
    if (!is_array($payload)) {
        return null;
    }

    $versionField = $payload['version'] ?? $payload['latest'] ?? $payload['latestVersion'] ?? null;
    if (!is_string($versionField) || trim($versionField) === '') {
        return null;
    }

    try {
        $version = normalize_version_string($versionField);
    } catch (RuntimeException $e) {
        return null;
    }

    $record = [
        'version' => $version,
        'downloadUrl' => null,
        'message' => null,
    ];

    $urlField = $payload['downloadUrl'] ?? $payload['url'] ?? null;
    if ($urlField !== null) {
        try {
            $record['downloadUrl'] = normalize_optional_url($urlField);
        } catch (RuntimeException $e) {
            $record['downloadUrl'] = null;
        }
    }

    $messageField = $payload['message'] ?? $payload['notes'] ?? null;
    if ($messageField !== null) {
        try {
            $record['message'] = normalize_optional_message($messageField);
        } catch (RuntimeException $e) {
            $record['message'] = null;
        }
    }

    if (isset($payload['updatedAt']) && is_string($payload['updatedAt'])) {
        $record['updatedAt'] = $payload['updatedAt'];
    }

    return $record;
}

function persist_version_record(array $record): void
{
    $json = json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        throw new RuntimeException('Failed to encode version payload.');
    }

    if (file_put_contents(VERSION_FILE, $json . "\n", LOCK_EX) === false) {
        throw new RuntimeException('Unable to persist version payload.');
    }
}

function resolve_version_password(): ?string
{
    $keys = ['LADDER_VERSION_PASSWORD', 'VERSION_PASSWORD'];

    foreach ($keys as $key) {
        if (isset($_SERVER[$key]) && is_string($_SERVER[$key]) && $_SERVER[$key] !== '') {
            return $_SERVER[$key];
        }
        if (isset($_ENV[$key]) && is_string($_ENV[$key]) && $_ENV[$key] !== '') {
            return $_ENV[$key];
        }
        $env = getenv($key);
        if (is_string($env) && $env !== '') {
            return $env;
        }
    }

    $fileKeys = ['LADDER_VERSION_PASSWORD_FILE', 'VERSION_PASSWORD_FILE'];
    foreach ($fileKeys as $fileKey) {
        $path = getenv($fileKey);
        if ($path === false || !is_string($path) || $path === '') {
            if (isset($_SERVER[$fileKey]) && is_string($_SERVER[$fileKey]) && $_SERVER[$fileKey] !== '') {
                $path = $_SERVER[$fileKey];
            } elseif (isset($_ENV[$fileKey]) && is_string($_ENV[$fileKey]) && $_ENV[$fileKey] !== '') {
                $path = $_ENV[$fileKey];
            } else {
                $path = null;
            }
        }

        if ($path !== null) {
            $password = read_password_file($path);
            if ($password !== null) {
                return $password;
            }
        }
    }

    return read_password_file(VERSION_PASSWORD_FALLBACK_FILE);
}

function read_password_file(string $path): ?string
{
    if ($path === '') {
        return null;
    }

    if (!is_file($path)) {
        return null;
    }

    $contents = file_get_contents($path);
    if ($contents === false) {
        return null;
    }

    $trimmed = trim($contents);
    if ($trimmed === '') {
        return null;
    }

    return $trimmed;
}

function normalize_version_string(string $value): string
{
    $normalized = trim($value);
    if ($normalized === '') {
        throw new RuntimeException('version must not be empty.');
    }

    return $normalized;
}

function normalize_optional_url($value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }

    if (!is_string($value)) {
        throw new RuntimeException('downloadUrl must be a string.');
    }

    $trimmed = trim($value);
    if ($trimmed === '') {
        return null;
    }

    if (filter_var($trimmed, FILTER_VALIDATE_URL) === false) {
        throw new RuntimeException('downloadUrl must be a valid URL.');
    }

    return $trimmed;
}

function normalize_optional_message($value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }

    if (!is_string($value)) {
        throw new RuntimeException('message must be a string.');
    }

    $trimmed = trim($value);
    if ($trimmed === '') {
        return null;
    }

    $normalized = str_replace(["\r\n", "\r"], "\n", $trimmed);

    return $normalized;
}

function load_arena_metadata(): array
{
    if (!is_dir(ARENA_DIR)) {
        return [];
    }

    $files = glob(ARENA_DIR . '/*.arena');
    if ($files === false) {
        return [];
    }

    $maps = [];
    foreach ($files as $file) {
        if (!is_file($file)) {
            continue;
        }

        $contents = file_get_contents($file);
        if ($contents === false || $contents === '') {
            continue;
        }

        $length = strlen($contents);
        $depth = 0;
        $buffer = '';

        for ($i = 0; $i < $length; $i++) {
            $char = $contents[$i];

            if ($char === '{') {
                if ($depth === 0) {
                    $buffer = '';
                } else {
                    $buffer .= $char;
                }
                $depth++;
                continue;
            }

            if ($char === '}') {
                if ($depth === 0) {
                    continue;
                }
                $depth--;
                if ($depth === 0) {
                    $map = extract_arena_value($buffer, 'map');
                    $longname = extract_arena_value($buffer, 'longname');
                    if ($map !== null && $longname !== null) {
                        $maps[$map] = $longname;
                    }
                    $buffer = '';
                    continue;
                }
            }

            if ($depth >= 1) {
                $buffer .= $char;
            }
        }
    }

    return $maps;
}

function extract_arena_value(string $block, string $key): ?string
{
    $normalized = str_replace(["\r\n", "\r"], "\n", $block);
    $lines = explode("\n", $normalized);

    foreach ($lines as $line) {
        if (!is_string($line)) {
            continue;
        }

        $trimmedLine = trim($line);
        if ($trimmedLine === '' || strncmp($trimmedLine, '//', 2) === 0) {
            continue;
        }

        if (strncasecmp($trimmedLine, $key, strlen($key)) !== 0) {
            continue;
        }

        $valuePart = trim(substr($trimmedLine, strlen($key)));
        if ($valuePart === '') {
            continue;
        }

        $firstChar = $valuePart[0];
        if ($firstChar === '"' || $firstChar === "'") {
            $quote = $firstChar;
            $value = '';
            $escaped = false;
            $valueLength = strlen($valuePart);
            for ($i = 1; $i < $valueLength; $i++) {
                $char = $valuePart[$i];
                if ($escaped) {
                    $value .= $char;
                    $escaped = false;
                    continue;
                }
                if ($char === '\\') {
                    $escaped = true;
                    continue;
                }
                if ($char === $quote) {
                    break;
                }
                $value .= $char;
            }

            $decoded = stripcslashes($value);
            $trimmedValue = trim($decoded);
            if ($trimmedValue !== '') {
                return $trimmedValue;
            }
        } else {
            $end = strcspn($valuePart, " \t");
            $candidate = $end === strlen($valuePart) ? $valuePart : substr($valuePart, 0, $end);
            $candidate = trim($candidate);
            if ($candidate !== '') {
                return $candidate;
            }
        }
    }

    return null;
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

function normalize_map_key(string $raw): string
{
    $lower = strtolower($raw);
    $normalized = preg_replace('/[^a-z0-9._-]+/', '_', $lower);
    if ($normalized === null) {
        return '';
    }

    return trim((string) $normalized, '_');
}

function attempt_json_encode($payload, int $options, ?string &$error = null)
{
    try {
        $json = json_encode($payload, $options);
        if ($json === false) {
            $error = function_exists('json_last_error_msg') ? json_last_error_msg() : 'json_encode failed.';
        }

        return $json;
    } catch (Throwable $encodeError) {
        $class = get_class($encodeError);
        if ($class === 'JsonException') {
            $error = $encodeError->getMessage();

            return false;
        }

        throw $encodeError;
    }
}

function sanitize_json_data($value)
{
    if (is_array($value)) {
        $result = [];
        foreach ($value as $key => $item) {
            $result[$key] = sanitize_json_data($item);
        }

        return $result;
    }

    if (is_object($value)) {
        $result = [];
        foreach (get_object_vars($value) as $key => $item) {
            $result[$key] = sanitize_json_data($item);
        }

        return $result;
    }

    if (is_string($value)) {
        return ensure_utf8_string($value);
    }

    return $value;
}

function ensure_utf8_string(string $value): string
{
    if ($value === '') {
        return '';
    }

    if (@preg_match('//u', $value) === 1) {
        return $value;
    }

    $candidates = [];
    if (function_exists('iconv')) {
        $candidates[] = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
        $candidates[] = @iconv('ISO-8859-1', 'UTF-8//IGNORE', $value);
    }
    if (function_exists('mb_convert_encoding')) {
        $candidates[] = @mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        $candidates[] = @mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
    }

    foreach ($candidates as $candidate) {
        if (is_string($candidate) && $candidate !== '') {
            return $candidate;
        }
    }

    $stripped = preg_replace('/[\x00-\x1F\x7F-\x9F]/', '', $value);
    if ($stripped === null) {
        return '';
    }

    if (@preg_match('//u', $stripped) === 1) {
        return $stripped;
    }

    return '';
}

function send_json(array $payload, int $statusCode): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');

    $options = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
    $error = null;
    $json = attempt_json_encode($payload, $options, $error);
    if ($json === false) {
        if ($error !== null) {
            error_log('JSON encode failed: ' . $error);
        }
        $sanitized = sanitize_json_data($payload);
        $json = attempt_json_encode($sanitized, $options | JSON_PARTIAL_OUTPUT_ON_ERROR, $error);
        if ($json === false) {
            if ($error !== null) {
                error_log('JSON encode retry failed: ' . $error);
            }
            $fallback = ['error' => 'Failed to encode response'];
            $json = attempt_json_encode($fallback, $options | JSON_PARTIAL_OUTPUT_ON_ERROR, $error);
            if ($json === false || $json === null) {
                $json = '{"error":"Failed to encode response"}';
            }
        }
    }

    echo $json;
    exit;
}

function send_error(int $statusCode, string $message): void
{
    send_json(['error' => $message], $statusCode);
}

