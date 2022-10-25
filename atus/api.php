<?php

namespace ATUS;

require_once __DIR__ . '/polyfill.php';
require_once __DIR__ . '/torrent.php';
require_once __DIR__ . '/controller.php';
require_once __DIR__ . '/../../php/util.php';

class API extends Controller
{

  function __construct()
  {
    parent::__construct();
  }

  function sendResponse($statusCode, $payload)
  {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($statusCode);

    if (is_string($payload)) {
      echo $payload;
      exit;
    }

    echo json_encode($payload);
    exit;
  }

  function handleRequest($action)
  {
    if (method_exists(__CLASS__, $action)) {
      return self::$action();
    }

    throw new \Exception("Invalid action");
  }

  function list()
  {
    $label = isset($_POST['label']) ? $_POST['label'] : '';

    return $this->getList($label);
  }

  function filelist()
  {
    $hash = isset($_GET['hash']) ? $_GET['hash'] : null;
    if (!$hash) {
      throw new \Exception("No hash specified");
    }

    return (new Torrent($hash))->getFileList($hash);
  }

  function statistics()
  {
    return [
      'serverLoad' => $this->getServerLoad(),
      'diskTotalSpace' => $this->getDiskTotalSpace(),
      'diskFreeSpace' => $this->getDiskFreeSpace(),
    ];
  }

  function add()
  {
    if (!isset($_FILES['meta'])) {
      throw new \Exception("Missing torrent file");
    }

    $filename = $_FILES['meta']['name'] ?: uniqid('atus');
    if (pathinfo($filename, PATHINFO_EXTENSION) != "torrent") {
      $filename .= ".torrent";
    }
    $path = getUniqueUploadedFilename($filename);

    $label = isset($_POST['label']) ? $_POST['label'] : '';

    if (!move_uploaded_file($_FILES['meta']['tmp_name'], $path)) {
      throw new \Exception("Failed to move uploaded file");
    }

    $uploadRes = $this->addTorrent($path, $label);
    if (!$uploadRes) {
      throw new \Exception("Failed to add torrent");
    }

    return ['hash' => strtolower($uploadRes)];
  }

  function getFileStatus()
  {
    $hash = isset($_GET['hash']) ? $_GET['hash'] : null;
    if (!$hash) {
      throw new \Exception("No hash specified");
    }
    $indices = isset($_GET['indices']) ? $_GET['indices'] : null;
    if ($indices === '') {
      throw new \Exception("No indices specified");
    }

    $t = new Torrent($hash);

    $status = $t->getFileStatus(array_map('intval', explode(',', $_GET['indices'])));
    if (!$status) {
      throw new \Exception("Failed to get file status");
    }

    // prioritize requested files
    $prioritize = [];
    foreach ($status as $s) {
      if ($s['priority'] !== Torrent::PRIORITY_HIGH && !$s['completed']) {
        $prioritize[] = $s['index'];
      }
    }

    if (count($prioritize) > 0) {
      $t->setFilePriority(Torrent::PRIORITY_HIGH, $prioritize);
    }

    return $status;
  }

  function downloadFile()
  {
    $hash = isset($_GET['hash']) ? $_GET['hash'] : null;
    if (!$hash) {
      throw new \Exception("No hash specified");
    }
    $index = isset($_GET['index']) && is_numeric($_GET['index']) ? (int)$_GET['index'] : null;
    if ($index === null) {
      throw new \Exception("No file specified");
    }

    $torrent = new Torrent($hash);
    $path = $torrent->getFilePath($index);

    if (!$path) {
      throw new \Exception("File not found");
    }

    if (sendFile($path)) {
      exit;
    }

    throw new \Exception("Failed to send file");
  }
}



$api = new API();

try {
  $r = $api->handleRequest(isset($_GET['action']) ? $_GET['action'] : '');
  $api->sendResponse(200, $r);
} catch (\Exception $e) {
  $api->sendResponse(400, $e->getMessage());
}
