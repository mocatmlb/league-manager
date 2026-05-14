<?php
$__dir = __DIR__;
$__found = false;
for ($__i = 0; $__i < 6; $__i++) {
    $__candidate = $__dir . '/includes/env-loader.php';
    if (file_exists($__candidate)) {
        require_once $__candidate;
        $__found = true;
        break;
    }
    $__dir = dirname($__dir);
}
if (!$__found) {
    if (!empty($_SERVER['DOCUMENT_ROOT']) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/includes/env-loader.php')) {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/env-loader.php';
        $__found = true;
    }
}
if (!$__found) {
    http_response_code(500);
    exit('Configuration error');
}
unset($__dir, $__found, $__i, $__candidate);

@include_once EnvLoader::getPath('includes/bootstrap.php');

$db = Database::getInstance();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('Invalid request');
}

$doc = $db->fetchOne("SELECT * FROM documents WHERE document_id = ?", [$id]);
if (!$doc) {
    http_response_code(404);
    exit('Document not found');
}

if (!$doc['is_public']) {
    @include_once EnvLoader::getPath('includes/auth.php');
    if (!Auth::isAdmin()) {
        http_response_code(403);
        exit('Access denied');
    }
}

$uploadDir = file_exists(__DIR__ . '/includes/env-loader.php')
    ? __DIR__ . '/uploads/documents/'
    : __DIR__ . '/../uploads/documents/';

$filePath = $uploadDir . $doc['filename'];

if (!file_exists($filePath)) {
    $legacyDir = __DIR__ . '/../../uploads/documents/';
    $filePath = $legacyDir . $doc['filename'];
}

if (!file_exists($filePath)) {
    http_response_code(404);
    exit('File not found');
}

$contentType = $doc['file_type'] ?: 'application/octet-stream';
header('Content-Type: ' . $contentType);
header('Content-Disposition: inline; filename="' . $doc['original_filename'] . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: private, max-age=3600');
header('Pragma: public');

readfile($filePath);
exit;
