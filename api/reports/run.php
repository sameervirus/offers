<?php
// Sends the daily / weekly offers report email.
//
// Cron (recommended):
//   php /path/to/api/reports/run.php daily
//   php /path/to/api/reports/run.php weekly
// Options:
//   --preview          print the HTML instead of sending
//   --to=a@x.com,b@y   send only to these addresses (for testing)
//
// From a URL (cron with wget/curl, or preview in a browser) — needs cron_key from config.php:
//   /api/reports/run.php?type=daily&key=SECRET
//   /api/reports/run.php?type=weekly&key=SECRET&preview=1
//   /api/reports/run.php?type=daily&key=SECRET&to=me@arconsegypt.com

date_default_timezone_set('Africa/Cairo');

$config = require __DIR__ . '/config.php';
$isCli = PHP_SAPI === 'cli';

if ($isCli) {
  $type = $argv[1] ?? '';
  $preview = in_array('--preview', $argv, true);
  $toOverride = '';
  foreach ($argv as $arg) {
    if (strpos($arg, '--to=') === 0) $toOverride = substr($arg, 5);
  }
} else {
  $key = (string)($_GET['key'] ?? '');
  if (empty($config['cron_key']) || $config['cron_key'] === 'CHANGE_ME' || !hash_equals($config['cron_key'], $key)) {
    http_response_code(403);
    exit('Forbidden');
  }
  $type = $_GET['type'] ?? '';
  $preview = !empty($_GET['preview']);
  $toOverride = (string)($_GET['to'] ?? '');
}

if (!in_array($type, ['daily', 'weekly'], true)) {
  if (!$isCli) http_response_code(400);
  exit("Usage: run.php daily|weekly [--preview] [--to=email]\n");
}

// Same relative path as the API controllers, resolved from the api/ folder.
chdir(dirname(__DIR__));
require_once '../../../db.php';
require_once __DIR__ . '/data.php';
require_once __DIR__ . '/html.php';

$today = date('Y-m-d');

// Offers received before start_date are ignored by both reports.
$startDate = $config['start_date'] ?? '';
if ($startDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
  exit("Invalid start_date in config.php, expected YYYY-MM-DD.\n");
}

try {
  if ($type === 'daily') {
    $data = dailyReportData($today, (int)$config['due_soon_days'], $startDate);
    $html = renderDailyReport($data, $config);
    $subject = dailySubject($data);
    $skip = $data['total'] === 0 && empty($config['daily']['send_when_empty']);
  } else {
    $data = weeklyReportData($today, (int)$config['follow_up_days'], $startDate);
    $html = renderWeeklyReport($data, $config);
    $subject = weeklySubject($data);
    $skip = false;
  }
} catch (Exception $e) {
  if (!$isCli) http_response_code(500);
  exit("Report failed: " . $e->getMessage() . "\n");
}

if ($preview) {
  if (!$isCli) header('Content-Type: text/html; charset=utf-8');
  echo $html;
  exit;
}

if ($skip) {
  exit("Daily report skipped: no offers without status.\n");
}

$recipients = $config[$type];
$to = $toOverride !== '' ? array_map('trim', explode(',', $toOverride)) : $recipients['to'];
$cc = $toOverride !== '' ? [] : ($recipients['cc'] ?? []);

if (!$to) {
  exit("No recipients configured for the $type report.\n");
}

if (sendReportMail($to, $cc, $subject, $html, $config)) {
  echo "Sent $type report to " . implode(', ', array_merge($to, $cc)) . "\n";
} else {
  if (!$isCli) http_response_code(500);
  echo "mail() failed to send the $type report.\n";
}

function sendReportMail(array $to, array $cc, $subject, $html, array $config)
{
  $fromName = '=?UTF-8?B?' . base64_encode($config['from_name']) . '?=';
  $headers = [
    'MIME-Version: 1.0',
    'Content-Type: text/html; charset=UTF-8',
    'Content-Transfer-Encoding: base64',
    'From: ' . $fromName . ' <' . $config['from_email'] . '>',
    'Reply-To: ' . $config['from_email'],
  ];
  if ($cc) $headers[] = 'Cc: ' . implode(', ', $cc);

  return mail(
    implode(', ', $to),
    '=?UTF-8?B?' . base64_encode($subject) . '?=',
    chunk_split(base64_encode($html)),
    implode("\r\n", $headers),
    '-f' . $config['from_email']
  );
}
