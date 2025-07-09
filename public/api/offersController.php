<?php
require_once '../../../db.php';

function getOffers()
{
  global $db;

  try {
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $offset = ($page - 1) * $limit;

    $where = "";
    $params = [];

    if (!empty($search)) {
      $where = "WHERE client LIKE :search OR project_name LIKE :search OR quo_no LIKE :search";
      $params[':search'] = "%$search%";
    }

    // Count
    $db->query("SELECT COUNT(*) as total FROM offers $where");
    foreach ($params as $key => $val) $db->bind($key, $val);
    $total = $db->fetch()['total'];

    // Data
    $db->query("SELECT * FROM offers $where ORDER BY rec_date DESC LIMIT :limit OFFSET :offset");
    foreach ($params as $key => $val) $db->bind($key, $val);
    $db->bind(":limit", $limit);
    $db->bind(":offset", $offset);
    $offers = $db->fetchAll();

    echo json_encode([
      'status' => true,
      'data' => $offers,
      'pagination' => [
        'total' => $total,
        'page' => $page,
        'limit' => $limit,
        'total_pages' => ceil($total / $limit)
      ]
    ]);
  } catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => false, 'error' => $e->getMessage()]);
  }
}

function getSingleOffer($id)
{
  global $db;

  try {
    $db->query("SELECT * FROM offers WHERE id = :id LIMIT 1");
    $db->bind(':id', $id, PDO::PARAM_INT);
    $offer = $db->fetch();

    if ($offer) {
      echo json_encode(['status' => true, 'data' => $offer]);
    } else {
      http_response_code(404);
      echo json_encode(['status' => false, 'error' => 'Offer not found']);
    }
  } catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => false, 'error' => $e->getMessage()]);
  }
}

function addOffer($data)
{
  global $db;

  $requiredFields = ['client', 'rec_date', 'project_name', 'work_type'];
  $errors = [];

  foreach ($requiredFields as $field) {
    if (empty(trim($data[$field] ?? ''))) {
      $errors[$field] = "$field is required.";
    }
  }

  if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['status' => false, 'errors' => $errors]);
    return;
  }

  try {
    $db->beginTransaction();

    $query = "INSERT INTO offers (
        rec_date, client, project_name, description, work_type, quo_date, quo_values, quo_no, status
      ) VALUES (
        :rec_date, :client, :project_name, :description, :work_type, :quo_date, :quo_values, :quo_no, :status
      )";

    $db->query($query);
    $db->bind(':rec_date', $data['rec_date']);
    $db->bind(':client', $data['client']);
    $db->bind(':project_name', $data['project_name']);
    $db->bind(':description', $data['description'] ?? null);
    $db->bind(':work_type', $data['work_type']);
    $db->bind(':quo_date', $data['quo_date'] ?? null);
    $db->bind(':quo_values', $data['quo_values'] ?? null);
    $db->bind(':quo_no', $data['quo_no'] ?? null);
    $db->bind(':status', $data['status'] ?? 'Pending');

    $db->execute();
    $id = $db->lastInsertId();

    $db->query("SELECT * FROM offers WHERE id = :id");
    $db->bind(':id', $id, PDO::PARAM_INT);
    $offer = $db->fetch();

    $db->endTransaction();

    echo json_encode([
      'status' => true,
      'message' => 'Offer added successfully',
      'data' => $offer
    ]);
  } catch (Exception $e) {
    $db->cancelTransaction();
    http_response_code(500);
    echo json_encode(['status' => false, 'error' => $e->getMessage()]);
  }
}

function updateOffer($id, $data)
{
  global $db;

  $requiredFields = ['client', 'rec_date', 'project_name', 'work_type'];
  $errors = [];

  foreach ($requiredFields as $field) {
    if (empty(trim($data[$field] ?? ''))) {
      $errors[$field] = "$field is required.";
    }
  }

  if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['status' => false, 'errors' => $errors]);
    return;
  }

  try {
    $db->beginTransaction();

    $query = "UPDATE offers SET
        rec_date = :rec_date,
        client = :client,
        project_name = :project_name,
        description = :description,
        work_type = :work_type,
        quo_date = :quo_date,
        quo_values = :quo_values,
        quo_no = :quo_no,
        status = :status
      WHERE id = :id";

    $db->query($query);
    $db->bind(':rec_date', $data['rec_date']);
    $db->bind(':client', $data['client']);
    $db->bind(':project_name', $data['project_name']);
    $db->bind(':description', $data['description'] ?? null);
    $db->bind(':work_type', $data['work_type']);
    $db->bind(':quo_date', $data['quo_date'] ?? null);
    $db->bind(':quo_values', $data['quo_values'] ?? null);
    $db->bind(':quo_no', $data['quo_no'] ?? null);
    $db->bind(':status', $data['status'] ?? 'Pending');
    $db->bind(':id', $id, PDO::PARAM_INT);

    $db->execute();

    $db->query("SELECT * FROM offers WHERE id = :id");
    $db->bind(':id', $id, PDO::PARAM_INT);
    $offer = $db->fetch();

    $db->endTransaction();

    echo json_encode([
      'status' => true,
      'message' => 'Offer updated successfully',
      'data' => $offer
    ]);
  } catch (Exception $e) {
    $db->cancelTransaction();
    http_response_code(500);
    echo json_encode(['status' => false, 'error' => $e->getMessage()]);
  }
}

function deleteOffer($id)
{
  global $db;

  try {
    $db->beginTransaction();
    $db->query("DELETE FROM offers WHERE id = :id");
    $db->bind(':id', $id, PDO::PARAM_INT);
    $db->execute();
    $db->endTransaction();

    echo json_encode(['status' => true, 'message' => 'Offer deleted successfully']);
  } catch (Exception $e) {
    $db->cancelTransaction();
    http_response_code(500);
    echo json_encode(['status' => false, 'error' => $e->getMessage()]);
  }
}

function getNewNumber($data)
{
  global $db;

  try {
    $currentYear = date("Y");

    // Filter only offers for this code and this year
    $likeCode = $data['code'] . '-%-' . $currentYear . '-Rev.0';

    $db->query("SELECT quo_no FROM offers WHERE quo_no LIKE :code ORDER BY id DESC LIMIT 1");
    $db->bind(":code", $likeCode);
    $lastOffer = $db->fetch();

    if ($lastOffer) {
      $parts = explode('-', $lastOffer['quo_no']);
      $lastNumber = (int)$parts[1];
      $newNumber = $lastNumber + 1;
    } else {
      $newNumber = 1;
    }

    $newQuoNo = sprintf("%s-%03d-%s-Rev.0", $data['code'], $newNumber, $currentYear);

    // Update the row with the generated quo_no
    $db->query("UPDATE offers SET quo_no = :quo_no, quo_date = NOW() WHERE id = :id");
    $db->bind(":quo_no", $newQuoNo);
    $db->bind(":id", $data['id']);
    $db->execute();

    echo json_encode(['status' => true, 'quo_no' => $newQuoNo]);
  } catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => false, 'error' => $e->getMessage()]);
  }
}
