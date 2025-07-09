<?php

function login($data)
{
  global $db;

  $errors = [];
  if (empty($data['username'])) $errors['username'] = 'Username is required';
  if (empty($data['password'])) $errors['password'] = 'Password is required';

  if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['status' => false, 'errors' => $errors]);
    return;
  }

  try {
    $db->query("SELECT * FROM members WHERE username = :username OR email = :username LIMIT 1");
    $db->bind(':username', $data['username']);
    $user = $db->fetch();

    if (!$user || !password_verify($data['password'], $user['password'])) {
      http_response_code(401);
      echo json_encode(['status' => false, 'error' => 'Invalid credentials']);
      return;
    }

    // Generate new token
    $token = bin2hex(random_bytes(32));

    // Save token in DB
    $db->query("UPDATE members SET token = :token WHERE id = :id");
    $db->bind(':token', $token);
    $db->bind(':id', $user['id']);
    $db->execute();

    echo json_encode([
      'status' => true,
      'user' => [
        'id' => $user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'admin' => (bool)$user['admin'],
        'token' => $token
      ]
    ]);
  } catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => false, 'error' => $e->getMessage()]);
  }
}


function getAuthorizationHeader()
{
  if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
    return $_SERVER['HTTP_AUTHORIZATION'];
  } elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
    return $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
  } elseif (function_exists('apache_request_headers')) {
    $headers = apache_request_headers();
    return $headers['Authorization'] ?? $headers['authorization'] ?? '';
  }
  return '';
}

function validateTokenRequest()
{
  global $db;

  $authHeader = getAuthorizationHeader();

  if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    $token = $matches[1];

    $db->query("SELECT id FROM members WHERE token = :token LIMIT 1");
    $db->bind(':token', $token);
    $user = $db->fetch();

    if ($user) {
      echo json_encode(['status' => true]);
      return;
    }
  }

  http_response_code(401);
  echo json_encode(['status' => false, 'error' => 'Unauthorized']);
}

function requireAuth()
{
  global $db;

  $authHeader = getAuthorizationHeader();

  if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    $token = $matches[1];

    $db->query("SELECT id FROM members WHERE token = :token LIMIT 1");
    $db->bind(':token', $token);
    $user = $db->fetch();

    if ($user) {
      return $user;
    }
  }

  http_response_code(401);
  echo json_encode(['status' => false, 'error' => 'Unauthorized']);
  exit;
}
