<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$php = PHP_BINARY;
$index = $root . DIRECTORY_SEPARATOR . 'index.php';
$tests = [
	[
		'name' => 'default json',
		'query' => '',
		'assert' => static fn (string $out): bool => is_array(json_decode($out, true)) && isset(json_decode($out, true)['hitokoto']),
	],
	[
		'name' => 'category json',
		'query' => 'encode=json&c=a&min_length=5&max_length=30',
		'assert' => static function (string $out): bool {
			$data = json_decode($out, true);
			return is_array($data) && ($data['type'] ?? null) === 'a' && ($data['length'] ?? 0) >= 5 && ($data['length'] ?? 0) <= 30;
		},
	],
	[
		'name' => 'text output',
		'query' => 'encode=text&type=i',
		'assert' => static fn (string $out): bool => trim($out) !== '' && json_decode($out, true) === null,
	],
	[
		'name' => 'jsonp output',
		'query' => 'encode=json&callback=handle.hitokoto&c=k',
		'assert' => static fn (string $out): bool => str_starts_with($out, 'handle.hitokoto(') && str_ends_with(trim($out), ');'),
	],
	[
		'name' => 'invalid category',
		'query' => 'c=z',
		'assert' => static function (string $out): bool {
			$data = json_decode($out, true);
			return is_array($data) && ($data['status'] ?? null) === 400;
		},
	],
	[
		'name' => 'jsonp error',
		'query' => 'callback=handleError&c=z',
		'assert' => static fn (string $out): bool => str_starts_with($out, 'handleError(') && str_contains($out, '"status": 400') && str_ends_with(trim($out), ');'),
	],
	[
		'name' => 'health check',
		'query' => 'health=1',
		'assert' => static function (string $out): bool {
			$data = json_decode($out, true);
			return is_array($data) && ($data['ok'] ?? false) === true && ($data['total'] ?? 0) > 0 && ($data['manifest'] ?? 0) > 0;
		},
	],
];

$failures = 0;
foreach ($tests as $test) {
	$output = runRequest($php, $index, $test['query']);
	$passed = ($test['assert'])($output);

	echo ($passed ? 'PASS' : 'FAIL') . ' ' . $test['name'] . PHP_EOL;
	if (!$passed) {
		echo $output . PHP_EOL;
		$failures++;
	}
}

exit($failures > 0 ? 1 : 0);

function runRequest(string $php, string $index, string $query): string
{
	$code = 'parse_str(' . var_export($query, true) . ', $_GET); include ' . var_export($index, true) . ';';
	$command = escapeshellarg($php) . ' -r ' . escapeshellarg($code);
	$output = [];
	$status = 0;

	exec($command, $output, $status);
	return implode(PHP_EOL, $output);
}
