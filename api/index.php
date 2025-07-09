<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

require_once 'offersController.php';

$uri = explode('/', trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/'));
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

// Add login route
if ($uri[0] === 'api' && $uri[1] === 'login' && $method === 'POST') {
  require_once 'authController.php';
  login($input);
  exit;
}

// Add Validation Route
if ($uri[0] === 'api' && $uri[1] === 'validate' && $method === 'GET') {
  require_once 'authController.php';
  validateTokenRequest();
  exit;
}

// Offers routing
if ($uri[0] === 'api' && $uri[1] === 'offers') {
  require_once 'offersController.php';
  require_once 'authController.php';

  requireAuth();

  $id = $uri[2] ?? null;

  switch ($method) {
    case 'GET':
      $id ? getSingleOffer($id) : getOffers();
      break;
    case 'POST':
      if ($id) {
        updateOffer($id, $input);
      } else {
        addOffer($input);
      }
      break;
    case 'DELETE':
      deleteOffer($id);
      break;
    case 'PATCH':
      getNewNumber($input);
      break;
    default:
      http_response_code(405);
      echo json_encode(['error' => 'Method Not Allowed']);
  }
  exit;
}

http_response_code(404);
echo json_encode(['error' => 'Not Found']);
