<?php
// file.php?f=NAME.pdf&mode=view|download
$PDF_DIR = realpath(__DIR__ . '/data/pdfs');

$f    = basename($_GET['f'] ?? '');            // blocks ../ path tricks
$path = $PDF_DIR ? realpath($PDF_DIR . DIRECTORY_SEPARATOR . $f) : false;

if (!$f || !$path || strpos($path, $PDF_DIR) !== 0 || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'pdf') {
    http_response_code(404);
    exit('PDF not found.');
}

$disposition = (($_GET['mode'] ?? 'view') === 'download') ? 'attachment' : 'inline';

header('Content-Type: application/pdf');
header('Content-Disposition: ' . $disposition . '; filename="' . str_replace('"', '', $f) . '"');
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, max-age=0');
readfile($path);