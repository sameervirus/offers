<?php
// Settings for the daily / weekly report emails.
// Fill in the recipients and set a long random cron_key before enabling the cron jobs.

return [
  // Used for "Open" links in the emails.
  'app_url' => 'https://offers.arconsegypt.com',

  // Sender. Use an address on the arconsegypt.com domain so the mail is not marked as spam.
  'from_email' => 'no-reply@arconsegypt.com',
  'from_name' => 'ARCONS Offers',

  'daily' => [
    'to' => ['amr.elshamy@arconsegypt.com', 'samir.nabil@arconsegypt.com'],
    'cc' => ['atef.adly@arconsegypt.com'],
    // false = skip the daily email when every offer already has a status.
    'send_when_empty' => true,
  ],

  'weekly' => [
    'to' => ['amr.elshamy@arconsegypt.com', 'samir.nabil@arconsegypt.com'],
    'cc' => ['atef.adly@arconsegypt.com'],
  ],

  // Both reports ignore offers received before this date (YYYY-MM-DD). Empty = include all offers.
  'start_date' => '2026-09-01',

  // Offers due within this many days are flagged as "due soon".
  'due_soon_days' => 7,

  // Pending quotations older than this many days are listed for client follow-up.
  'follow_up_days' => 14,

  // Required to run a report from a URL (e.g. cron with wget/curl, or preview in the browser).
  // Not needed when cron runs the script with the php command.
  'cron_key' => 'e19df867629f4921d5d78d18cc6baf11',
];
