<?php
// HTML builders for the report emails.
// Email clients (Outlook especially) ignore most CSS, so everything is table-based with inline styles.

const C_TEXT = '#1f2937';
const C_MUTED = '#6b7280';
const C_BORDER = '#e5e7eb';
const C_HEAD_BG = '#1e3a5f';
const C_RED = '#b91c1c';
const C_RED_BG = '#fee2e2';
const C_AMBER = '#92400e';
const C_AMBER_BG = '#fef3c7';
const C_GREEN = '#166534';
const C_GREEN_BG = '#dcfce7';
const C_BLUE = '#1e40af';
const C_BLUE_BG = '#dbeafe';
const C_GREY_BG = '#f3f4f6';

function h($value)
{
  return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function fmtDate($date)
{
  if (empty($date) || substr($date, 0, 10) === '0000-00-00') return '—';
  return date('d M Y', strtotime(substr($date, 0, 10)));
}

function badge($text, $color, $bg)
{
  return '<span style="display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:bold;white-space:nowrap;color:'
    . $color . ';background:' . $bg . ';">' . h($text) . '</span>';
}

function statusBadge($status)
{
  switch ($status) {
    case 'Awarded': return badge('Awarded', C_GREEN, C_GREEN_BG);
    case 'Rejected': return badge('Rejected', C_RED, C_RED_BG);
    case 'Pending': return badge('Pending', C_BLUE, C_BLUE_BG);
    default: return badge('No status', C_AMBER, C_AMBER_BG);
  }
}

function dueBadge($dueDate, $today)
{
  $days = daysFrom($dueDate, $today);
  if ($days === null) return '';
  if ($days < 0) return badge(-$days . ' d overdue', C_RED, C_RED_BG);
  if ($days === 0) return badge('Due today', C_AMBER, C_AMBER_BG);
  if ($days <= 7) return badge("in $days d", C_AMBER, C_AMBER_BG);
  return badge("in $days d", C_MUTED, C_GREY_BG);
}

function small($text)
{
  return '<div style="font-size:12px;color:' . C_MUTED . ';margin-top:2px;">' . $text . '</div>';
}

function deltaNote($current, $previous)
{
  $diff = $current - $previous;
  if ($diff === 0) return 'same as last week';
  return ($diff > 0 ? '+' : '−') . abs($diff) . ' vs last week (' . $previous . ')';
}

// $tiles: [['label' => , 'value' => , 'note' => , 'tone' => 'red'|'amber'|'green'|'blue'|null], ...]
function kpiRow(array $tiles)
{
  $tones = [
    'red' => [C_RED, C_RED_BG],
    'amber' => [C_AMBER, C_AMBER_BG],
    'green' => [C_GREEN, C_GREEN_BG],
    'blue' => [C_BLUE, C_BLUE_BG],
  ];
  $width = floor(100 / count($tiles));
  $cells = '';
  foreach ($tiles as $tile) {
    [$color, $bg] = $tones[$tile['tone'] ?? ''] ?? [C_TEXT, C_GREY_BG];
    $cells .= '<td width="' . $width . '%" style="padding:4px;" valign="top">'
      . '<div style="background:' . $bg . ';border-radius:8px;padding:12px 10px;text-align:center;">'
      . '<div style="font-size:26px;font-weight:bold;line-height:1.1;color:' . $color . ';">' . h($tile['value']) . '</div>'
      . '<div style="font-size:12px;font-weight:bold;color:' . C_TEXT . ';margin-top:4px;">' . h($tile['label']) . '</div>'
      . (isset($tile['note']) ? '<div style="font-size:11px;color:' . C_MUTED . ';margin-top:2px;">' . h($tile['note']) . '</div>' : '')
      . '</div></td>';
  }
  return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 8px;"><tr>' . $cells . '</tr></table>';
}

function section($title, $content, $note = '', $accent = C_HEAD_BG)
{
  return '<div style="margin:24px 0 0;">'
    . '<div style="font-size:16px;font-weight:bold;color:' . C_TEXT . ';border-left:4px solid ' . $accent . ';padding-left:8px;">' . h($title) . '</div>'
    . ($note ? '<div style="font-size:12px;color:' . C_MUTED . ';margin:4px 0 0 12px;">' . h($note) . '</div>' : '')
    . '<div style="margin-top:10px;">' . $content . '</div></div>';
}

function emptyNote($text)
{
  return '<div style="font-size:13px;color:' . C_MUTED . ';padding:10px 12px;background:' . C_GREY_BG . ';border-radius:6px;">' . h($text) . '</div>';
}

/**
 * Offers table. $columns is a list of keys:
 *   project, work_type, received, due, offer, value, status, decided
 */
function offersTable(array $rows, array $columns, array $ctx)
{
  $headers = [
    'project' => 'Project / Client',
    'work_type' => 'Work scope',
    'received' => 'Received',
    'due' => 'Due date',
    'offer' => 'Offer',
    'value' => 'Value',
    'status' => 'Status',
    'decided' => 'Decided on',
  ];
  $th = 'style="text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.03em;color:' . C_MUTED
    . ';padding:8px;border-bottom:2px solid ' . C_BORDER . ';"';
  $td = 'style="font-size:13px;color:' . C_TEXT . ';padding:8px;border-bottom:1px solid ' . C_BORDER . ';" valign="top"';

  $html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;"><tr>';
  foreach ($columns as $col) $html .= "<th $th>" . $headers[$col] . '</th>';
  $html .= '</tr>';

  foreach ($rows as $row) {
    $html .= '<tr>';
    foreach ($columns as $col) {
      $html .= "<td $td>" . offerCell($col, $row, $ctx) . '</td>';
    }
    $html .= '</tr>';
  }
  return $html . '</table>';
}

function offerCell($col, $row, $ctx)
{
  $today = $ctx['today'];
  switch ($col) {
    case 'project':
      $url = $ctx['app_url'] . '/offers/' . (int)$row['id'];
      return '<a href="' . h($url) . '" style="color:' . C_BLUE . ';font-weight:bold;text-decoration:none;">'
        . h($row['project_name'] ?: '(no project name)') . '</a>' . small(h($row['client']));
    case 'work_type':
      return h($row['work_type'] ?: '—');
    case 'received':
      $age = daysFrom($row['rec_date'], $today);
      return '<span style="white-space:nowrap;">' . fmtDate($row['rec_date']) . '</span>'
        . ($age !== null ? small(-$age . ' d ago') : '');
    case 'due':
      if (daysFrom($row['due_date'], $today) === null) return '<span style="color:' . C_MUTED . ';">Not set</span>';
      return '<span style="white-space:nowrap;">' . fmtDate($row['due_date']) . '</span><div style="margin-top:3px;">' . dueBadge($row['due_date'], $today) . '</div>';
    case 'offer':
      if (empty($row['quo_no'])) return '<span style="color:' . C_MUTED . ';font-style:italic;">Not quoted</span>';
      return '<span style="white-space:nowrap;">' . h($row['quo_no']) . '</span>' . small(fmtDate($row['quo_date']));
    case 'value':
      return h($row['quo_values'] ?: '—');
    case 'status':
      return statusBadge($row['status']);
    case 'decided':
      return '<span style="white-space:nowrap;">' . fmtDate($row['status_changed_at']) . '</span>';
  }
  return '';
}

function emailLayout($title, $subtitle, $body, $appUrl)
{
  return '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . h($title) . '</title></head>'
    . '<body style="margin:0;padding:0;background:#f4f5f7;font-family:Segoe UI,Arial,sans-serif;">'
    . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f5f7;"><tr><td align="center" style="padding:20px 10px;">'
    . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:760px;background:#ffffff;border-radius:10px;overflow:hidden;">'
    . '<tr><td style="background:' . C_HEAD_BG . ';padding:20px 24px;">'
    . '<div style="font-size:20px;font-weight:bold;color:#ffffff;">' . h($title) . '</div>'
    . '<div style="font-size:13px;color:#cbd5e1;margin-top:4px;">' . h($subtitle) . '</div>'
    . '</td></tr>'
    . '<tr><td style="padding:20px 24px 28px;">' . $body . '</td></tr>'
    . '<tr><td style="padding:14px 24px;background:' . C_GREY_BG . ';font-size:11px;color:' . C_MUTED . ';">'
    . 'Automated report from the ARCONS Offers system. <a href="' . h($appUrl) . '/offers" style="color:' . C_BLUE . ';">Open the offers list</a>.'
    . '</td></tr>'
    . '</table></td></tr></table></body></html>';
}

// ---------------------------------------------------------------- Daily

function renderDailyReport(array $data, array $config)
{
  $ctx = ['today' => $data['today'], 'app_url' => $config['app_url']];
  $g = $data['groups'];
  $dueSoonDays = $config['due_soon_days'];
  $cols = ['project', 'work_type', 'received', 'due', 'offer'];

  $body = kpiRow([
    ['label' => 'Without status', 'value' => $data['total'], 'tone' => $data['total'] ? 'amber' : 'green'],
    ['label' => 'Overdue', 'value' => count($g['overdue']), 'tone' => count($g['overdue']) ? 'red' : null],
    ['label' => "Due in $dueSoonDays days", 'value' => count($g['due_soon']), 'tone' => count($g['due_soon']) ? 'amber' : null],
    ['label' => 'No due date', 'value' => count($g['no_due'])],
    ['label' => 'Received since yesterday', 'value' => count($data['received']), 'tone' => 'blue'],
  ]);

  if ($data['total'] === 0) {
    $body .= section('Offers without status', emptyNote('Every offer has a status. Nothing to review today.'), '', C_GREEN);
  } else {
    if ($g['overdue']) {
      $body .= section('Overdue', offersTable($g['overdue'], $cols, $ctx), 'Due date has passed and the offer still has no status.', C_RED);
    }
    if ($g['due_soon']) {
      $body .= section("Due in the next $dueSoonDays days", offersTable($g['due_soon'], $cols, $ctx), 'Earliest due date first.', '#d97706');
    }
    if ($g['no_due']) {
      $body .= section('No due date', offersTable($g['no_due'], $cols, $ctx), 'Oldest first. Consider setting a due date or a status.');
    }
    if ($g['later']) {
      $body .= section('Due later', offersTable($g['later'], $cols, $ctx), '', C_MUTED);
    }
  }

  $body .= section(
    'New offers received',
    $data['received'] ? offersTable($data['received'], ['project', 'work_type', 'received', 'due', 'status'], $ctx) : emptyNote('No new offers since yesterday.'),
    'Received date is yesterday or today.',
    C_BLUE
  );

  $subtitle = date('l, d M Y', strtotime($data['today']));
  return emailLayout('Daily review — offers without status', $subtitle, $body, $config['app_url']);
}

function dailySubject(array $data)
{
  $overdue = count($data['groups']['overdue']);
  $summary = $data['total'] === 0
    ? 'all offers have a status'
    : $data['total'] . ' offer' . ($data['total'] === 1 ? '' : 's') . ' without status' . ($overdue ? " ($overdue overdue)" : '');
  return 'Daily offers review: ' . $summary . ' — ' . date('d M Y', strtotime($data['today']));
}

// ---------------------------------------------------------------- Weekly

function pipelineTable(array $matrix)
{
  $statuses = ['No status', 'Pending', 'Awarded', 'Rejected'];
  $th = 'style="text-align:right;font-size:11px;text-transform:uppercase;color:' . C_MUTED . ';padding:8px;border-bottom:2px solid ' . C_BORDER . ';"';
  $td = 'style="text-align:right;font-size:13px;color:' . C_TEXT . ';padding:8px;border-bottom:1px solid ' . C_BORDER . ';"';
  $tdLeft = 'style="text-align:left;font-size:13px;color:' . C_TEXT . ';padding:8px;border-bottom:1px solid ' . C_BORDER . ';"';

  $html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;"><tr>'
    . '<th style="text-align:left;font-size:11px;text-transform:uppercase;color:' . C_MUTED . ';padding:8px;border-bottom:2px solid ' . C_BORDER . ';">Work scope</th>';
  foreach ($statuses as $s) $html .= "<th $th>$s</th>";
  $html .= "<th $th>Total</th><th $th>Win rate</th></tr>";

  $totals = array_fill_keys($statuses, 0);
  ksort($matrix);
  foreach ($matrix as $workType => $counts) {
    $html .= "<tr><td $tdLeft>" . h($workType) . '</td>';
    $rowTotal = 0;
    foreach ($statuses as $s) {
      $n = $counts[$s] ?? 0;
      $totals[$s] += $n;
      $rowTotal += $n;
      $html .= "<td $td>" . ($n ?: '<span style="color:#d1d5db;">0</span>') . '</td>';
    }
    $html .= "<td $td><b>$rowTotal</b></td><td $td>" . winRate($counts['Awarded'] ?? 0, $counts['Rejected'] ?? 0) . '</td></tr>';
  }

  $bold = 'style="text-align:right;font-size:13px;font-weight:bold;color:' . C_TEXT . ';padding:8px;background:' . C_GREY_BG . ';"';
  $html .= '<tr><td style="text-align:left;font-size:13px;font-weight:bold;padding:8px;background:' . C_GREY_BG . ';">Total</td>';
  foreach ($statuses as $s) $html .= "<td $bold>" . $totals[$s] . '</td>';
  $html .= "<td $bold>" . array_sum($totals) . "</td><td $bold>" . winRate($totals['Awarded'], $totals['Rejected']) . '</td></tr>';

  return $html . '</table>';
}

function winRate($awarded, $rejected)
{
  $decided = $awarded + $rejected;
  return $decided ? round($awarded * 100 / $decided) . '%' : '—';
}

function renderWeeklyReport(array $data, array $config)
{
  $ctx = ['today' => $data['today'], 'app_url' => $config['app_url']];

  $awarded = array_values(array_filter($data['decided'], function ($r) { return $r['status'] === 'Awarded'; }));
  $rejected = array_values(array_filter($data['decided'], function ($r) { return $r['status'] === 'Rejected'; }));

  $body = kpiRow([
    ['label' => 'Received', 'value' => count($data['received']), 'note' => deltaNote(count($data['received']), $data['received_prev']), 'tone' => 'blue'],
    ['label' => 'Quoted', 'value' => count($data['quoted']), 'note' => deltaNote(count($data['quoted']), $data['quoted_prev']), 'tone' => 'blue'],
    ['label' => 'Awarded', 'value' => count($awarded), 'note' => 'this week', 'tone' => count($awarded) ? 'green' : null],
    ['label' => 'Rejected', 'value' => count($rejected), 'note' => 'this week', 'tone' => count($rejected) ? 'red' : null],
    [
      'label' => 'Without status',
      'value' => $data['no_status_total'],
      'note' => $data['no_status_overdue'] ? $data['no_status_overdue'] . ' overdue' : 'none overdue',
      'tone' => $data['no_status_overdue'] ? 'red' : ($data['no_status_total'] ? 'amber' : 'green'),
    ],
  ]);

  $year = substr($data['year_start'], 0, 4);
  $body .= section(
    "Pipeline $year by work scope",
    $data['matrix'] ? pipelineTable($data['matrix']) : emptyNote("No offers received in $year yet."),
    'Offers received since 1 Jan, by current status. Win rate = Awarded ÷ (Awarded + Rejected).'
  );

  $body .= section(
    'Awarded & rejected this week',
    $data['decided'] ? offersTable($data['decided'], ['project', 'work_type', 'offer', 'value', 'status', 'decided'], $ctx) : emptyNote('No offers were awarded or rejected this week.'),
    'Based on the date the status was changed in the system.',
    C_GREEN
  );

  $body .= section(
    'Quotations sent this week',
    $data['quoted'] ? offersTable($data['quoted'], ['project', 'work_type', 'offer', 'value', 'status'], $ctx) : emptyNote('No quotations were issued this week.'),
    '',
    C_BLUE
  );

  $days = $data['follow_up_days'];
  $body .= section(
    'Follow up with client',
    $data['follow_up'] ? offersTable($data['follow_up'], ['project', 'work_type', 'offer', 'value'], $ctx) : emptyNote("No pending quotations older than $days days."),
    "Pending quotations sent more than $days days ago with no decision. Oldest first.",
    '#d97706'
  );

  $body .= section(
    'Offers received this week',
    $data['received'] ? offersTable($data['received'], ['project', 'work_type', 'received', 'due', 'status'], $ctx) : emptyNote('No offers were received this week.'),
    '',
    C_BLUE
  );

  $oldest = $data['no_status_oldest'];
  $more = $data['no_status_total'] - count($oldest);
  $body .= section(
    'Longest without status',
    $oldest
      ? offersTable($oldest, ['project', 'work_type', 'received', 'due', 'offer'], $ctx)
        . ($more > 0 ? '<div style="font-size:12px;color:' . C_MUTED . ';margin-top:6px;">…and ' . $more . ' more. See the daily review email for the full list.</div>' : '')
      : emptyNote('Every offer has a status.'),
    'Oldest received first.',
    C_RED
  );

  $subtitle = fmtDate($data['start']) . ' – ' . fmtDate($data['end']);
  return emailLayout('Weekly offers report', $subtitle, $body, $config['app_url']);
}

function weeklySubject(array $data)
{
  return 'Weekly offers report: ' . date('d M', strtotime($data['start'])) . ' – ' . date('d M Y', strtotime($data['end']));
}
