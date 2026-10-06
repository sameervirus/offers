<?php
// Queries for the daily / weekly report emails.
// Dates are computed in PHP (Africa/Cairo) and passed to MySQL as parameters.

const REPORT_COLUMNS = "id, rec_date, client, project_name, work_type, quo_no, quo_date, quo_values, status, status_changed_at, due_date";
const NO_STATUS_SQL = "(status IS NULL OR status = '')";

function reportRows($sql, $params = [])
{
  global $db;
  $db->query($sql);
  foreach ($params as $key => $val) $db->bind($key, $val);
  return $db->fetchAll();
}

function reportCount($sql, $params = [])
{
  global $db;
  $db->query($sql);
  foreach ($params as $key => $val) $db->bind($key, $val);
  return (int)$db->fetch()['n'];
}

// Offers table limited to offers received on or after $startDate (config 'start_date').
// $startDate is validated in run.php, so it is safe to inline.
function offersSource($startDate)
{
  return $startDate ? "(SELECT * FROM offers WHERE rec_date >= '$startDate') AS offers" : "offers";
}

function shiftDate($date, $days)
{
  return date('Y-m-d', strtotime("$date $days days"));
}

// Whole days from $today to $date (negative = in the past). null when there is no date.
function daysFrom($date, $today)
{
  if (empty($date) || substr($date, 0, 10) === '0000-00-00') return null;
  return (int)round((strtotime(substr($date, 0, 10)) - strtotime($today)) / 86400);
}

function dailyReportData($today, $dueSoonDays, $startDate)
{
  $offers = offersSource($startDate);

  // Offers with a due date first (earliest first), then the rest by oldest received.
  $rows = reportRows(
    "SELECT " . REPORT_COLUMNS . " FROM $offers
     WHERE " . NO_STATUS_SQL . "
     ORDER BY due_date IS NULL, due_date, rec_date"
  );

  $groups = ['overdue' => [], 'due_soon' => [], 'no_due' => [], 'later' => []];
  foreach ($rows as $row) {
    $days = daysFrom($row['due_date'], $today);
    if ($days === null) $groups['no_due'][] = $row;
    elseif ($days < 0) $groups['overdue'][] = $row;
    elseif ($days <= $dueSoonDays) $groups['due_soon'][] = $row;
    else $groups['later'][] = $row;
  }

  $received = reportRows(
    "SELECT " . REPORT_COLUMNS . " FROM $offers WHERE rec_date >= :since ORDER BY rec_date DESC, id DESC",
    [':since' => shiftDate($today, -1)]
  );

  return [
    'today' => $today,
    'total' => count($rows),
    'groups' => $groups,
    'received' => $received,
  ];
}

function weeklyReportData($today, $followUpDays, $startDate)
{
  $offers = offersSource($startDate);

  // Report covers the 7 days ending yesterday, compared with the 7 days before that.
  $end = shiftDate($today, -1);
  $start = shiftDate($today, -7);
  $prevEnd = shiftDate($today, -8);
  $prevStart = shiftDate($today, -14);
  $yearStart = max(substr($end, 0, 4) . '-01-01', (string)$startDate);

  $week = [':start' => $start, ':end' => $end];
  $prevWeek = [':start' => $prevStart, ':end' => $prevEnd];

  $receivedSql = "FROM $offers WHERE DATE(rec_date) BETWEEN :start AND :end";
  $quotedSql = "FROM $offers WHERE DATE(quo_date) BETWEEN :start AND :end";

  $received = reportRows("SELECT " . REPORT_COLUMNS . " $receivedSql ORDER BY rec_date, id", $week);
  $quoted = reportRows("SELECT " . REPORT_COLUMNS . " $quotedSql ORDER BY quo_date, id", $week);

  $decided = reportRows(
    "SELECT " . REPORT_COLUMNS . " FROM $offers
     WHERE status IN ('Awarded', 'Rejected') AND DATE(status_changed_at) BETWEEN :start AND :end
     ORDER BY status, status_changed_at",
    $week
  );

  // Year-to-date pipeline (or since start_date if later): work scope x status
  $matrixRows = reportRows(
    "SELECT COALESCE(NULLIF(work_type, ''), 'Unspecified') AS work_type,
            COALESCE(NULLIF(status, ''), 'No status') AS status,
            COUNT(*) AS n
     FROM $offers
     WHERE DATE(rec_date) BETWEEN :start AND :end
     GROUP BY 1, 2",
    [':start' => $yearStart, ':end' => $end]
  );
  $matrix = [];
  foreach ($matrixRows as $row) {
    $matrix[$row['work_type']][$row['status']] = (int)$row['n'];
  }

  $followUp = reportRows(
    "SELECT " . REPORT_COLUMNS . " FROM $offers
     WHERE status = 'Pending' AND quo_date IS NOT NULL AND DATE(quo_date) <= :before
     ORDER BY quo_date",
    [':before' => shiftDate($today, -$followUpDays)]
  );

  $noStatusOldest = reportRows(
    "SELECT " . REPORT_COLUMNS . " FROM $offers WHERE " . NO_STATUS_SQL . " ORDER BY rec_date, id LIMIT 10"
  );

  return [
    'today' => $today,
    'start' => $start,
    'end' => $end,
    'year_start' => $yearStart,
    'received' => $received,
    'received_prev' => reportCount("SELECT COUNT(*) AS n $receivedSql", $prevWeek),
    'quoted' => $quoted,
    'quoted_prev' => reportCount("SELECT COUNT(*) AS n $quotedSql", $prevWeek),
    'decided' => $decided,
    'matrix' => $matrix,
    'follow_up' => $followUp,
    'follow_up_days' => $followUpDays,
    'no_status_total' => reportCount("SELECT COUNT(*) AS n FROM $offers WHERE " . NO_STATUS_SQL),
    'no_status_overdue' => reportCount(
      "SELECT COUNT(*) AS n FROM $offers WHERE " . NO_STATUS_SQL . " AND due_date < :today",
      [':today' => $today]
    ),
    'no_status_oldest' => $noStatusOldest,
  ];
}
