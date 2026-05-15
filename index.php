<?php
declare(strict_types=1);

const JSON_FLAGS = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
const VALID_CATEGORIES = 'abcdefghijkl';

$allowedOrigins = [
	'https://artisticcode.cn',
	'https://www.artisticcode.cn',
	'http://localhost:3000',
	'http://127.0.0.1:3000',
	'http://localhost:12445',
	'http://127.0.0.1:12445',
];

sendSecurityHeaders();
handleCors($allowedOrigins);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'OPTIONS') {
	http_response_code(204);
	exit;
}

if ($method !== 'GET') {
	respondError('Method not allowed.', 405);
}

$charset = strtolower((string)($_GET['charset'] ?? 'utf-8'));
if ($charset !== 'utf-8') {
	respondError('Unsupported charset.', 400);
}

$format = getResponseFormat();
$callback = getJsonpCallback();
$GLOBALS['hitokoto_jsonp_callback'] = $callback;
$baseDir = __DIR__;
$version = readJson($baseDir . '/sentences/version.json');
$manifest = readManifest($baseDir);
if (isHealthCheck()) {
	respondJson(getHealthStatus($baseDir, $version, $manifest));
	exit;
}

$categories = getRequestedCategories($version['sentences'] ?? []);
$lengthFilter = getLengthFilter();
$hitokoto = pickHitokoto($baseDir, $categories, $lengthFilter, $manifest);

if ($hitokoto === null) {
	respondError('No sentence matched the request.', 404);
}

if ($format === 'text') {
	header('Content-Type: text/plain; charset=utf-8');
	echo (string)($hitokoto['hitokoto'] ?? '');
	exit;
}

if ($callback !== null) {
	header('Content-Type: application/javascript; charset=utf-8');
	echo $callback . '(' . encodeJson($hitokoto) . ');';
	exit;
}

respondJson($hitokoto);

function sendSecurityHeaders(): void
{
	header('X-Content-Type-Options: nosniff');
	header('Referrer-Policy: no-referrer');
	header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
	header('Cache-Control: no-store');
}

function handleCors(array $allowedOrigins): void
{
	$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
	if ($origin === '') {
		return;
	}

	if (!in_array($origin, $allowedOrigins, true)) {
		respondError('Origin is not allowed.', 403);
	}

	header('Access-Control-Allow-Origin: ' . $origin);
	header('Vary: Origin');
	header('Access-Control-Allow-Methods: GET, OPTIONS');
	header('Access-Control-Allow-Headers: Content-Type');
}

function getResponseFormat(): string
{
	$format = strtolower((string)($_GET['encode'] ?? ($_GET['do'] ?? 'json')));

	if (!in_array($format, ['json', 'text'], true)) {
		respondError('Invalid response format.', 400);
	}

	return $format;
}

function getJsonpCallback(): ?string
{
	$callback = $_GET['callback'] ?? null;
	if ($callback === null || $callback === '') {
		return null;
	}

	if (!is_string($callback) || !preg_match('/^[A-Za-z_$][A-Za-z0-9_$]*(\.[A-Za-z_$][A-Za-z0-9_$]*)*$/', $callback)) {
		respondError('Invalid callback.', 400);
	}

	return $callback;
}

function isHealthCheck(): bool
{
	return isset($_GET['health']) && $_GET['health'] !== '0' && $_GET['health'] !== '';
}

function getRequestedCategories(array $sentences): array
{
	$requested = $_GET['c'] ?? ($_GET['type'] ?? null);
	$keys = [];

	if (is_array($requested)) {
		$keys = $requested;
	} elseif (is_string($requested) && $requested !== '') {
		$keys = str_split($requested);
	}

	if ($keys === []) {
		return $sentences;
	}

	$keys = array_values(array_unique($keys));
	foreach ($keys as $key) {
		if (!is_string($key) || strlen($key) !== 1 || !str_contains(VALID_CATEGORIES, $key)) {
			respondError('Invalid category.', 400);
		}
	}

	$allowed = array_flip($keys);
	$categories = array_values(array_filter($sentences, static function ($sentence) use ($allowed) {
		return isset($sentence['key'], $allowed[$sentence['key']]);
	}));

	if ($categories === []) {
		respondError('Invalid category.', 400);
	}

	return $categories;
}

function pickHitokoto(string $baseDir, array $categories, array $lengthFilter, array $manifest): ?array
{
	if ($lengthFilter['min'] === null && $lengthFilter['max'] === null) {
		$category = pickWeightedCategory($baseDir, $categories, $manifest);
		$sentences = readCategorySentences($baseDir, $category);

		return $sentences === [] ? null : $sentences[array_rand($sentences)];
	}

	$sentences = [];
	foreach ($categories as $category) {
		$sentences = array_merge($sentences, readCategorySentences($baseDir, $category));
	}

	$sentences = filterByLength($sentences, $lengthFilter);
	return $sentences === [] ? null : $sentences[array_rand($sentences)];
}

function pickWeightedCategory(string $baseDir, array $categories, array $manifest): array
{
	$weights = [];
	$total = 0;

	foreach ($categories as $index => $category) {
		$count = getCategoryCount($baseDir, $category, $manifest);
		if ($count <= 0) {
			continue;
		}

		$weights[$index] = $count;
		$total += $count;
	}

	if ($total <= 0) {
		respondError('Sentence file is empty.', 500);
	}

	$pick = random_int(1, $total);
	foreach ($weights as $index => $weight) {
		$pick -= $weight;
		if ($pick <= 0) {
			return $categories[$index];
		}
	}

	return $categories[array_key_first($weights)];
}

function readCategorySentences(string $baseDir, array $category): array
{
	$path = $category['path'] ?? null;
	if (!is_string($path) || $path === '') {
		respondError('Sentence category is unavailable.', 500);
	}

	return readJson(normalizeSentencePath($baseDir, $path));
}

function getHealthStatus(string $baseDir, array $version, array $manifest): array
{
	$categories = [];
	$total = 0;
	$healthy = true;

	foreach (($version['sentences'] ?? []) as $category) {
		$key = (string)($category['key'] ?? '');
		$path = (string)($category['path'] ?? '');
		$file = $path !== '' ? normalizeSentencePath($baseDir, $path) : null;
		$count = 0;
		$ok = false;
		$manifestCategory = $manifest['categories'][$key] ?? null;

		if (is_array($manifestCategory) && isset($manifestCategory['count'], $manifestCategory['ok'])) {
			$count = (int)$manifestCategory['count'];
			$ok = (bool)$manifestCategory['ok'];
		} elseif ($file !== null && is_file($file) && is_readable($file)) {
			$count = getCategoryCount($baseDir, $category, $manifest);
			$ok = $count > 0;
		}

		$healthy = $healthy && $ok;
		$total += $count;
		$categories[$key] = [
			'path' => $path,
			'count' => $count,
			'ok' => $ok,
		];
	}

	if (!$healthy) {
		http_response_code(503);
	}

	return [
		'ok' => $healthy,
		'bundle_version' => $version['bundle_version'] ?? null,
		'updated_at' => $version['updated_at'] ?? null,
		'manifest' => $manifest['total'] ?? null,
		'total' => $total,
		'categories' => $categories,
	];
}

function getCategoryCount(string $baseDir, array $category, array $manifest): int
{
	$path = $category['path'] ?? null;
	if (!is_string($path) || $path === '') {
		respondError('Sentence category is unavailable.', 500);
	}

	$file = normalizeSentencePath($baseDir, $path);
	$key = (string)($category['key'] ?? '');
	if ($key !== '' && isset($manifest['categories'][$key]['count'])) {
		return (int)$manifest['categories'][$key]['count'];
	}

	$cacheKey = 'hitokoto-count:' . sha1($file . ':' . (string)filemtime($file));
	if (function_exists('apcu_fetch')) {
		$cached = apcu_fetch($cacheKey, $success);
		if ($success && is_int($cached)) {
			return $cached;
		}
	}

	$count = count(readJson($file));
	if (function_exists('apcu_store')) {
		apcu_store($cacheKey, $count, 300);
	}

	return $count;
}

function readManifest(string $baseDir): array
{
	$file = $baseDir . '/sentences/manifest.json';
	if (!is_file($file)) {
		return [];
	}

	$data = readJson($file);
	return is_array($data) ? $data : [];
}

function getLengthFilter(): array
{
	$min = getOptionalInt('min_length');
	$max = getOptionalInt('max_length');

	if ($min !== null && $max !== null && $min > $max) {
		respondError('min_length cannot be greater than max_length.', 400);
	}

	return [
		'min' => $min,
		'max' => $max,
	];
}

function filterByLength(array $sentences, array $lengthFilter): array
{
	$min = $lengthFilter['min'];
	$max = $lengthFilter['max'];

	if ($min === null && $max === null) {
		return $sentences;
	}

	return array_values(array_filter($sentences, static function ($sentence) use ($min, $max) {
		$length = isset($sentence['length']) ? (int)$sentence['length'] : utf8Length((string)($sentence['hitokoto'] ?? ''));

		if ($min !== null && $length < $min) {
			return false;
		}

		if ($max !== null && $length > $max) {
			return false;
		}

		return true;
	}));
}

function getOptionalInt(string $name): ?int
{
	if (!isset($_GET[$name]) || $_GET[$name] === '') {
		return null;
	}

	$value = $_GET[$name];
	if (!is_scalar($value) || !preg_match('/^\d+$/', (string)$value)) {
		respondError($name . ' must be a non-negative integer.', 400);
	}

	return (int)$value;
}

function utf8Length(string $value): int
{
	return preg_match_all('/./u', $value);
}

function normalizeSentencePath(string $baseDir, string $path): string
{
	$file = $baseDir . '/' . ltrim($path, './\\');
	$realBase = realpath($baseDir . '/sentences');
	$realFile = realpath($file);

	if ($realBase === false || $realFile === false || !str_starts_with($realFile, $realBase . DIRECTORY_SEPARATOR)) {
		respondError('Sentence path is invalid.', 500);
	}

	return $realFile;
}

function readJson(string $file): array
{
	static $localCache = [];

	if (isset($localCache[$file])) {
		return $localCache[$file];
	}

	$cacheKey = 'hitokoto:' . sha1($file . ':' . (string)filemtime($file));
	if (function_exists('apcu_fetch')) {
		$cached = apcu_fetch($cacheKey, $success);
		if ($success && is_array($cached)) {
			$localCache[$file] = $cached;
			return $cached;
		}
	}

	if (!is_file($file) || !is_readable($file)) {
		respondError('Data file is unavailable.', 500);
	}

	$content = file_get_contents($file);
	if ($content === false) {
		respondError('Data file cannot be read.', 500);
	}

	$data = json_decode($content, true);
	if (!is_array($data)) {
		respondError('Data file contains invalid JSON.', 500);
	}

	if (function_exists('apcu_store')) {
		apcu_store($cacheKey, $data, 300);
	}

	$localCache[$file] = $data;
	return $data;
}

function respondError(string $message, int $status): void
{
	http_response_code($status);
	$payload = [
		'error' => $message,
		'status' => $status,
	];

	$callback = $GLOBALS['hitokoto_jsonp_callback'] ?? null;
	if (is_string($callback) && $callback !== '') {
		header('Content-Type: application/javascript; charset=utf-8');
		echo $callback . '(' . encodeJson($payload) . ');';
		exit;
	}

	header('Content-Type: application/json; charset=utf-8');
	echo encodeJson($payload);
	exit;
}

function respondJson(array $payload): void
{
	$callback = $GLOBALS['hitokoto_jsonp_callback'] ?? null;
	if (is_string($callback) && $callback !== '') {
		header('Content-Type: application/javascript; charset=utf-8');
		echo $callback . '(' . encodeJson($payload) . ');';
		return;
	}

	header('Content-Type: application/json; charset=utf-8');
	echo encodeJson($payload);
}

function encodeJson(array $payload): string
{
	$json = json_encode($payload, JSON_FLAGS);
	if ($json === false) {
		respondError('Failed to encode response.', 500);
	}

	return $json;
}
