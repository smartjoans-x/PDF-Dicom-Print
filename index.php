<?php
// ============================================================
//  DICOM Print PDFs - SL Diagnostics
//  Date-wise list (default: today), search, view / download
// ============================================================
session_start();
if (empty($_SESSION['rename_token'])) $_SESSION['rename_token'] = bin2hex(random_bytes(16));
$TOKEN = $_SESSION['rename_token'];
date_default_timezone_set('Asia/Kolkata');

$PDF_DIR  = __DIR__ . '/data/pdfs';
$AE_TITLE = 'PDF_DICOM';
$PORT     = 1122;
$HOST_IP  = gethostbyname(gethostname());

// ---- Listener status (file written by scp-runner.js) ----
$statusFile = __DIR__ . '/data/scp-status.json';
$st = is_file($statusFile) ? json_decode(file_get_contents($statusFile), true) : null;
$online = $st && !empty($st['running']) && (time() - filemtime($statusFile)) < 90;

// ---- Selected date (default today) ----
$today = date('Y-m-d');
$date  = $_GET['date'] ?? $today;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) $date = $today;
$isToday  = ($date === $today);
$prevDate = date('Y-m-d', strtotime($date . ' -1 day'));
$nextDate = date('Y-m-d', strtotime($date . ' +1 day'));
$dayStart = strtotime($date . ' 00:00:00');
$dayEnd   = $dayStart + 86400;

$q = trim($_GET['q'] ?? '');

// ---- Message after rename ----
$MSGS = [
    'renamed'  => ['ok',  'File renamed.'],
    'empty'    => ['err', 'Please type a name.'],
    'notfound' => ['err', 'That PDF was not found. It may have been renamed already.'],
    'nowrite'  => ['err', 'Could not rename: the web server has no Modify permission on data\\pdfs.'],
    'expired'  => ['err', 'Page was open too long. Please try again.'],
];
$msg = $MSGS[$_GET['msg'] ?? ''] ?? null;
$hl  = basename($_GET['f'] ?? '');

// ---- Collect PDFs for the selected date ----
$rows = [];
$films = 0;
foreach (glob($PDF_DIR . '/*.pdf') ?: [] as $file) {
    $t = filemtime($file);
    if ($t < $dayStart || $t >= $dayEnd) continue;

    $name = basename($file);
    $meta = [];
    $metaFile = substr($file, 0, -4) . '.json';
    if (is_file($metaFile)) $meta = json_decode(file_get_contents($metaFile), true) ?: [];

    $row = [
        'file'  => $name,
        'title' => ($meta['patientName'] ?? '') !== '' ? $meta['patientName'] : pathinfo($name, PATHINFO_FILENAME),
        'pid'   => $meta['patientId'] ?? '',
        'pages' => (int)($meta['pages'] ?? 0),
        'film'  => $meta['filmSize'] ?? '',
        'size'  => filesize($file),
        'time'  => $t,
    ];
    // name to pre-fill in the rename box (without the date-time part)
    $edit = preg_replace('/_\d{8}_\d{6}(_\d+)?$/', '', $row['title']);
    $row['edit'] = in_array(strtoupper($edit), ['FILM', 'UNKNOWN'], true) ? '' : $edit;
    if ($q !== '' && stripos($row['title'] . ' ' . $row['pid'] . ' ' . $name, $q) === false) continue;
    $rows[] = $row;
    $films += max(1, $row['pages']);
}
usort($rows, function ($a, $b) { return $b['time'] <=> $a['time']; });
$latest = $rows[0] ?? null;

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function link_to($f, $mode) { return 'file.php?f=' . rawurlencode($f) . '&mode=' . $mode; }
function nice_size($b) { return $b >= 1048576 ? round($b / 1048576, 1) . ' MB' : round($b / 1024) . ' KB'; }
function rename_btn($r, $cls = '') {
    return '<button type="button" class="btn ' . $cls . '" data-file="' . h($r['file']) . '" data-name="' . h($r['edit']) . '" onclick="openRename(this)">Rename</button>';
}
function url_with($params) {
    $base = ['date' => $_GET['date'] ?? null, 'q' => $_GET['q'] ?? null];
    $all = array_filter(array_merge($base, $params), function ($v) { return $v !== null && $v !== ''; });
    return 'index.php?' . http_build_query($all);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Film Prints · <?= h(date('d M Y', $dayStart)) ?> · SL Diagnostics</title>
<style>
  :root {
    --room: #141b24;
    --room-2: #1c2531;
    --room-3: #243040;
    --line: #2e3b4d;
    --panel: #eef3f6;
    --ink: #121a23;
    --text: #d9e2ea;
    --muted: #8796a6;
    --ok: #3ecf8e;
    --off: #ef5b66;
    --action: #3b9fd1;
    --action-ink: #ffffff;
    --radius: 8px;
  }
  * { box-sizing: border-box; }
  html, body { margin: 0; }
  body {
    background: var(--room); color: var(--text);
    font: 15px/1.5 "Segoe UI", system-ui, -apple-system, Roboto, Arial, sans-serif;
  }
  a { color: inherit; }
  .wrap { max-width: 1180px; margin: 0 auto; padding: 20px 20px 56px; }

  /* ---------- Top bar ---------- */
  .top { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px 20px; }
  .brand h1 { margin: 0; font-size: 21px; font-weight: 600; color: #fff; letter-spacing: .2px; }
  .brand p { margin: 2px 0 0; font-size: 13px; color: var(--muted); }
  .pill {
    display: inline-flex; align-items: center; gap: 8px; padding: 7px 12px;
    border: 1px solid var(--line); border-radius: 999px; background: var(--room-2);
    font-size: 13px; color: var(--muted);
  }
  .pill b { color: #fff; font-weight: 600; }
  .dot { width: 9px; height: 9px; border-radius: 50%; background: var(--off); flex: none; }
  .dot.on { background: var(--ok); box-shadow: 0 0 0 4px rgba(62,207,142,.18); }

  /* ---------- Date bar ---------- */
  .datebar {
    margin-top: 20px; display: flex; flex-wrap: wrap; align-items: center; gap: 10px;
    padding: 12px; background: var(--room-2); border: 1px solid var(--line); border-radius: var(--radius);
  }
  .daynav { display: flex; align-items: center; gap: 6px; }
  .daylabel { min-width: 190px; text-align: center; }
  .daylabel strong { display: block; color: #fff; font-size: 17px; font-weight: 600; line-height: 1.2; }
  .daylabel span { font-size: 12px; color: var(--muted); }
  .datebar form { display: flex; flex-wrap: wrap; gap: 8px; margin-left: auto; flex: 1 1 360px; justify-content: flex-end; }
  input[type=date], input[type=search] {
    padding: 8px 11px; border-radius: 6px; border: 1px solid var(--line);
    background: var(--room); color: #fff; font: inherit; font-size: 14px;
  }
  input[type=date] { color-scheme: dark; }
  input[type=search] { flex: 1 1 180px; min-width: 0; }

  .btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 6px;
    padding: 8px 14px; border-radius: 6px; text-decoration: none; cursor: pointer;
    font: inherit; font-size: 14px; font-weight: 600; white-space: nowrap;
    border: 1px solid var(--line); background: var(--room-3); color: var(--text);
  }
  .btn:hover { border-color: #46586e; }
  .btn.primary { background: var(--action); border-color: var(--action); color: var(--action-ink); }
  .btn.primary:hover { filter: brightness(1.08); }
  .btn.icon { padding: 8px 11px; }
  .btn.disabled { opacity: .35; pointer-events: none; }
  .btn:focus-visible, input:focus-visible { outline: 3px solid #8fd0f0; outline-offset: 2px; }

  /* ---------- Stats ---------- */
  .stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin-top: 14px; }
  .stat { background: var(--room-2); border: 1px solid var(--line); border-radius: var(--radius); padding: 14px 16px; }
  .stat .k { font-size: 12px; color: var(--muted); text-transform: uppercase; letter-spacing: .6px; }
  .stat .v { font-size: 24px; font-weight: 600; color: #fff; margin-top: 2px; font-variant-numeric: tabular-nums; }

  /* ---------- Lightbox: latest film ---------- */
  .lightbox {
    margin-top: 16px; background: var(--panel); color: var(--ink); border-radius: var(--radius);
    padding: 20px 22px; display: grid; grid-template-columns: 1fr auto; gap: 14px; align-items: center;
    box-shadow: 0 0 0 1px rgba(255,255,255,.06), 0 0 48px rgba(238,243,246,.10);
  }
  .lightbox .tag { font-size: 12px; font-weight: 700; letter-spacing: .8px; text-transform: uppercase; color: #2a7fa8; }
  .lightbox .who { font-size: 24px; font-weight: 600; line-height: 1.25; margin-top: 2px; word-break: break-word; }
  .lightbox .when { color: #4d5b69; font-size: 14px; margin-top: 4px; }
  .lightbox .btn { background: #fff; border-color: #c9d4dc; color: var(--ink); }
  .lightbox .btn.primary { background: var(--action); border-color: var(--action); color: #fff; }
  .actions { display: flex; gap: 8px; flex-wrap: wrap; }

  /* ---------- List ---------- */
  .listhead { display: flex; align-items: baseline; justify-content: space-between; margin: 26px 0 10px; gap: 10px; }
  .listhead h2 { margin: 0; font-size: 16px; font-weight: 600; color: #fff; }
  .listhead span { font-size: 13px; color: var(--muted); }
  .table-wrap { overflow-x: auto; border: 1px solid var(--line); border-radius: var(--radius); background: var(--room-2); }
  table { width: 100%; border-collapse: collapse; min-width: 720px; }
  th, td { padding: 12px 14px; text-align: left; border-bottom: 1px solid var(--line); vertical-align: middle; }
  th { font-size: 12px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .6px; background: var(--room-3); }
  tr:last-child td { border-bottom: 0; }
  tbody tr:hover td { background: #212c3a; }
  td.time { font-variant-numeric: tabular-nums; color: #fff; white-space: nowrap; }
  td.name { color: #fff; font-weight: 500; word-break: break-word; }
  td.name small { display: block; color: var(--muted); font-weight: 400; font-size: 12px; }
  td .btn { padding: 6px 11px; font-size: 13px; }
  .badge { display: inline-block; padding: 2px 8px; border-radius: 999px; background: var(--room-3); border: 1px solid var(--line); font-size: 12px; color: var(--text); white-space: nowrap; }
  .badge.new { background: rgba(62,207,142,.14); border-color: rgba(62,207,142,.4); color: #7be3b4; margin-left: 6px; }

  .empty { text-align: center; padding: 44px 16px; color: var(--muted); }
  .empty strong { display: block; color: #fff; font-size: 16px; margin-bottom: 4px; }

  .foot { margin-top: 18px; font-size: 12px; color: var(--muted); display: flex; justify-content: space-between; flex-wrap: wrap; gap: 8px; }

  /* ---------- Messages ---------- */
  .msg { margin-top: 16px; padding: 10px 14px; border-radius: var(--radius); font-size: 14px; border: 1px solid; }
  .msg.ok  { background: rgba(62,207,142,.12); border-color: rgba(62,207,142,.45); color: #8ee8bf; }
  .msg.err { background: rgba(239,91,102,.12); border-color: rgba(239,91,102,.5); color: #ffadb3; }
  tbody tr.hl td { background: rgba(59,159,209,.16); }

  /* ---------- Rename dialog ---------- */
  dialog {
    border: 1px solid var(--line); border-radius: 10px; padding: 0; width: min(460px, 92vw);
    background: var(--room-2); color: var(--text); box-shadow: 0 20px 60px rgba(0,0,0,.5);
  }
  dialog::backdrop { background: rgba(5,9,14,.65); }
  dialog form { padding: 20px 20px 18px; margin: 0; display: block; }
  dialog h3 { margin: 0 0 4px; font-size: 17px; color: #fff; }
  dialog .sub { margin: 0 0 14px; font-size: 13px; color: var(--muted); word-break: break-all; }
  dialog label { display: block; font-size: 13px; color: var(--muted); margin-bottom: 6px; }
  dialog input[type=text] {
    width: 100%; padding: 10px 12px; border-radius: 6px; border: 1px solid var(--line);
    background: var(--room); color: #fff; font: inherit; font-size: 15px;
  }
  dialog .preview { margin-top: 10px; font-size: 13px; color: var(--muted); word-break: break-all; }
  dialog .preview b { color: #fff; font-weight: 600; font-family: Consolas, "Courier New", monospace; font-size: 12.5px; }
  dialog .row { display: flex; justify-content: flex-end; gap: 8px; margin-top: 18px; }

  @media (max-width: 720px) {
    .stats { grid-template-columns: 1fr 1fr 1fr; gap: 8px; }
    .stat { padding: 10px 12px; }
    .stat .v { font-size: 19px; }
    .lightbox { grid-template-columns: 1fr; }
    .daylabel { min-width: 0; flex: 1; }
    .datebar form { margin-left: 0; justify-content: stretch; }
  }
</style>
</head>
<body>
<div class="wrap">

  <!-- Top bar -->
  <header class="top">
    <div class="brand">
      <h1>Film Prints</h1>
      <p>SL Diagnostics · DICOM print to PDF</p>
    </div>
    <div class="pill" title="Modality printer settings">
      <span class="dot <?= $online ? 'on' : '' ?>"></span>
      <?php if ($online): ?>
        <span>Receiving · AE <b><?= h($AE_TITLE) ?></b> · <b><?= h($HOST_IP) ?></b>:<b><?= h($PORT) ?></b></span>
      <?php else: ?>
        <span><b>Listener offline</b> · run <b>pm2 restart all</b> on server</span>
      <?php endif; ?>
    </div>
  </header>

  <?php if ($msg): ?>
    <div class="msg <?= $msg[0] ?>" role="status"><?= h($msg[1]) ?><?= ($msg[0] === 'ok' && $hl) ? ' Saved as <b>' . h($hl) . '</b>' : '' ?></div>
  <?php endif; ?>

  <!-- Date navigation + search -->
  <section class="datebar" aria-label="Choose date">
    <div class="daynav">
      <a class="btn icon" href="<?= h(url_with(['date' => $prevDate])) ?>" aria-label="Previous day">&#8249;</a>
      <div class="daylabel">
        <strong><?= $isToday ? 'Today' : h(date('l', $dayStart)) ?></strong>
        <span><?= h(date('d M Y', $dayStart)) ?></span>
      </div>
      <a class="btn icon <?= $isToday ? 'disabled' : '' ?>" href="<?= h(url_with(['date' => $nextDate])) ?>" aria-label="Next day">&#8250;</a>
      <?php if (!$isToday): ?>
        <a class="btn" href="index.php">Today</a>
      <?php endif; ?>
    </div>

    <form method="get">
      <input type="date" name="date" value="<?= h($date) ?>" max="<?= h($today) ?>" onchange="this.form.submit()" aria-label="Pick a date">
      <input type="search" name="q" value="<?= h($q) ?>" placeholder="Search name or ID">
      <button class="btn primary" type="submit">Search</button>
      <?php if ($q !== ''): ?>
        <a class="btn" href="<?= h(url_with(['q' => ''])) ?>">Clear</a>
      <?php endif; ?>
    </form>
  </section>

  <!-- Stats for the day -->
  <section class="stats" aria-label="Day summary">
    <div class="stat"><div class="k">Print jobs</div><div class="v"><?= count($rows) ?></div></div>
    <div class="stat"><div class="k">Films</div><div class="v"><?= $films ?></div></div>
    <div class="stat"><div class="k">Last print</div><div class="v"><?= $latest ? date('h:i A', $latest['time']) : '—' ?></div></div>
  </section>

  <!-- Latest film of the day -->
  <?php if ($latest): ?>
    <section class="lightbox" aria-label="Latest print">
      <div>
        <div class="tag"><?= $isToday ? 'Latest print' : 'Last print of the day' ?></div>
        <div class="who"><?= h($latest['title']) ?></div>
        <div class="when">
          <?= date('h:i:s A', $latest['time']) ?>
          <?= $latest['pid'] ? ' · ID ' . h($latest['pid']) : '' ?>
          <?= $latest['pages'] ? ' · ' . $latest['pages'] . ' film' . ($latest['pages'] > 1 ? 's' : '') : '' ?>
          <?= $latest['film'] ? ' · ' . h($latest['film']) : '' ?>
        </div>
      </div>
      <div class="actions">
        <a class="btn primary" href="<?= h(link_to($latest['file'], 'view')) ?>" target="_blank">View PDF</a>
        <a class="btn" href="<?= h(link_to($latest['file'], 'download')) ?>">Download</a>
        <?= rename_btn($latest) ?>
      </div>
    </section>
  <?php endif; ?>

  <!-- List -->
  <div class="listhead">
    <h2><?= $isToday ? "Today's prints" : 'Prints on ' . h(date('d M Y', $dayStart)) ?></h2>
    <span><?= $q !== '' ? count($rows) . ' match' . (count($rows) === 1 ? '' : 'es') . ' for "' . h($q) . '"' : 'Newest first' ?></span>
  </div>

  <div class="table-wrap">
    <?php if (!$rows): ?>
      <div class="empty">
        <?php if ($q !== ''): ?>
          <strong>No prints match "<?= h($q) ?>"</strong>
          Try a different name or clear the search.
        <?php elseif ($isToday): ?>
          <strong>No films printed today yet</strong>
          New prints from the modality appear here automatically.
        <?php else: ?>
          <strong>No films on this day</strong>
          Use the arrows or the date picker to choose another day.
        <?php endif; ?>
      </div>
    <?php else: ?>
      <table>
        <thead>
          <tr><th>Time</th><th>Patient / File</th><th>Films</th><th>Film size</th><th>PDF</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $i => $r): ?>
          <tr<?= ($hl !== '' && $r['file'] === $hl) ? ' class="hl"' : '' ?>>
            <td class="time"><?= date('h:i A', $r['time']) ?></td>
            <td class="name">
              <?= h($r['title']) ?><?= ($i === 0 && $isToday && $q === '') ? '<span class="badge new">Latest</span>' : '' ?>
              <?= $r['pid'] ? '<small>ID ' . h($r['pid']) . '</small>' : '' ?>
            </td>
            <td><?= $r['pages'] ?: '—' ?></td>
            <td><?= $r['film'] ? '<span class="badge">' . h($r['film']) . '</span>' : '—' ?></td>
            <td><span class="badge"><?= nice_size($r['size']) ?></span></td>
            <td>
              <div class="actions">
                <a class="btn primary" href="<?= h(link_to($r['file'], 'view')) ?>" target="_blank">View</a>
                <a class="btn" href="<?= h(link_to($r['file'], 'download')) ?>">Download</a>
                <?= rename_btn($r) ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <div class="foot">
    <span><?= $isToday && $q === '' ? 'Auto-refreshes every 15 seconds' : 'Showing ' . h(date('d M Y', $dayStart)) ?></span>
    <span>Updated <?= date('h:i:s A') ?></span>
  </div>
</div>

<!-- Rename dialog -->
<dialog id="rn" aria-labelledby="rnTitle">
  <form method="post" action="rename.php" autocomplete="off">
    <h3 id="rnTitle">Rename PDF</h3>
    <p class="sub" id="rnOld"></p>
    <input type="hidden" name="token" value="<?= h($TOKEN) ?>">
    <input type="hidden" name="file" value="">
    <input type="hidden" name="date" value="<?= h($date) ?>">
    <input type="hidden" name="q" value="<?= h($q) ?>">
    <label for="rnName">Patient name</label>
    <input type="text" id="rnName" name="name" maxlength="80" required placeholder="e.g. Sampath Krishna">
    <div class="preview">File will be saved as <b id="rnPreview"></b></div>
    <div class="row">
      <button type="button" class="btn" onclick="document.getElementById('rn').close()">Cancel</button>
      <button type="submit" class="btn primary">Save name</button>
    </div>
  </form>
</dialog>

<script>
  var dlg = document.getElementById('rn');
  var nameIn = document.getElementById('rnName');
  var fileIn = dlg.querySelector('input[name=file]');

  function stampOf(f) { var m = f.match(/_(\d{8}_\d{6})(?:_\d+)?\.pdf$/i); return m ? m[1] : 'date_time'; }
  function safeOf(v) {
    var s = v.replace(/[^A-Za-z0-9 _.-]/g, '').trim().replace(/\s+/g, '_').replace(/^[._-]+|[._-]+$/g, '').slice(0, 60);
    return s || 'FILM';
  }
  function updatePreview() {
    document.getElementById('rnPreview').textContent = safeOf(nameIn.value) + '_' + stampOf(fileIn.value) + '.pdf';
  }
  function openRename(btn) {
    fileIn.value = btn.getAttribute('data-file');
    nameIn.value = btn.getAttribute('data-name') || '';
    document.getElementById('rnOld').textContent = 'Current: ' + fileIn.value;
    updatePreview();
    dlg.showModal();
    nameIn.focus();
    nameIn.select();
  }
  nameIn.addEventListener('input', updatePreview);

  // remove ?msg / ?f from the address bar so a refresh does not repeat the message
  if (window.history.replaceState && /[?&](msg|f)=/.test(location.search)) {
    var u = new URL(location.href); u.searchParams.delete('msg'); u.searchParams.delete('f');
    history.replaceState(null, '', u.pathname + (u.search ? u.search : ''));
  }

<?php if ($isToday && $q === ''): ?>
  // auto refresh every 15 s, but never while the rename box is open
  (function tick() {
    setTimeout(function () { if (dlg.open) { tick(); } else { location.reload(); } }, 15000);
  })();
<?php endif; ?>
</script>
</body>
</html>