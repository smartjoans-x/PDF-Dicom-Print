<?php
// ============================================================
//  rename.php - rename a saved print PDF (called from index.php)
//  POST: token, file, name, date, q
//  New file = <Name>_<original timestamp>.pdf (timestamp kept so
//  the PDF stays on the same day and names never clash)
// ============================================================
session_start();

$PDF_DIR = realpath(__DIR__ . '/data/pdfs');

function back($extra = []) {
    $date = $_POST['date'] ?? '';
    $q    = $_POST['q'] ?? '';
    $p = [];
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $p['date'] = $date;
    if ($q !== '') $p['q'] = $q;
    header('Location: index.php?' . http_build_query(array_merge($p, $extra)));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') back();

// same-page token (stops other sites from renaming files)
$token = $_POST['token'] ?? '';
if ($token === '' || !hash_equals($_SESSION['rename_token'] ?? '', $token)) back(['msg' => 'expired']);

// ---- old file ----
$old     = basename($_POST['file'] ?? '');
$oldPath = $PDF_DIR ? realpath($PDF_DIR . DIRECTORY_SEPARATOR . $old) : false;
if (!$old || !$oldPath || strpos($oldPath, $PDF_DIR) !== 0 || strtolower(pathinfo($oldPath, PATHINFO_EXTENSION)) !== 'pdf') {
    back(['msg' => 'notfound']);
}

// ---- new name ----
$display = trim(preg_replace('/\s+/u', ' ', (string)($_POST['name'] ?? '')));
if ($display === '') back(['msg' => 'empty']);
if (function_exists('mb_substr')) $display = mb_substr($display, 0, 80); else $display = substr($display, 0, 80);

$safe = preg_replace('/[^A-Za-z0-9 _.-]/', '', $display);
$safe = trim(preg_replace('/\s+/', '_', $safe), '._- ');
$safe = substr($safe, 0, 60);
if ($safe === '') $safe = 'FILM'; // e.g. name typed only in Tamil / Telugu: file gets FILM, page shows the typed name

// keep the original date-time part of the file name
$mtime = filemtime($oldPath);
$stamp = preg_match('/_(\d{8}_\d{6})(?:_\d+)?\.pdf$/i', $old, $m) ? $m[1] : date('Ymd_His', $mtime);

$newName = $safe . '_' . $stamp . '.pdf';
$newPath = $PDF_DIR . DIRECTORY_SEPARATOR . $newName;

// avoid overwriting another PDF
if (strcasecmp($newName, $old) !== 0) {
    $n = 2;
    while (file_exists($newPath)) {
        $newName = $safe . '_' . $stamp . '_' . $n . '.pdf';
        $newPath = $PDF_DIR . DIRECTORY_SEPARATOR . $newName;
        $n++;
    }
    if (!@rename($oldPath, $newPath)) back(['msg' => 'nowrite']);
    @touch($newPath, $mtime); // keep original time -> stays under the same date
} else {
    $newPath = $oldPath;
    $newName = $old;
}

// ---- move / update the .json info file ----
$oldJson = substr($oldPath, 0, -4) . '.json';
$newJson = substr($newPath, 0, -4) . '.json';
$meta = is_file($oldJson) ? (json_decode(file_get_contents($oldJson), true) ?: []) : [];
if (empty($meta['originalFile'])) $meta['originalFile'] = $old;
$meta['patientName'] = $display;
$meta['renamedAt'] = date('c');
@file_put_contents($newJson, json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
if (strcasecmp($oldJson, $newJson) !== 0 && is_file($oldJson)) @unlink($oldJson);

back(['msg' => 'renamed', 'f' => $newName]);