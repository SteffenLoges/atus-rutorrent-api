<?php

namespace ATUS;

require_once __DIR__ . '/torrent.php';
require_once __DIR__ . '/../../php/xmlrpc.php';
require_once __DIR__ . '/../../php/settings.php';

use rTorrentSettings;
use rXMLRPCCommand;
use rXMLRPCRequest;

class Controller
{

  protected $downloadsDir = '/';

  function __construct()
  {
    if (file_exists(rTorrentSettings::get()->directory)) {
      $this->downloadsDir = rTorrentSettings::get()->directory;
    }
  }

  // Adds a torrent to rTorrent
  // @param $fname - The path to the torrent file
  // @return - The hash of the torrent if successful, false otherwise
  function addTorrent($fname, $label)
  {
    $t = new Torrent();
    return $t->addNew($fname, $label);
  }

  const FILE_STATE_STARTED = "STARTED";
  const FILE_STATE_PAUSED = "PAUSED";
  const FILE_STATE_STOPPED = "STOPPED";
  const FILE_STATE_HASHING = "HASHING";
  const FILE_STATE_CHECKING = "CHECKING";
  const FILE_STATE_ERROR = "ERROR";

  // gets a list of active torrents from rTorrent
  function getList($label)
  {

    $cmds = [
      "d.get_custom2=", // Must be at index 0
      "d.get_hash=",
      "d.is_open=",
      "d.is_hash_checking=",
      "d.get_completed_chunks=",
      "d.get_hashing=",
      "d.get_size_chunks=",
      "d.get_chunks_hashed=",
      "d.get_state=",
      "d.get_down_rate=",
      "d.get_chunk_size=",
      "d.get_custom1=",
      "d.is_active=",
      "d.get_message=",
    ];

    $cmd = new rXMLRPCCommand("d.multicall", "main");
    $cmd->addParameters(array_map("getCmd", $cmds));

    $cnt = count($cmds);
    $req = new rXMLRPCRequest($cmd);
    if (!$req->success(false)) {
      return false;
    }


    $labelEnc = "VRS24mrker" . rawurlencode($label);

    $list = [];
    $fileIndex = -1;
    $skipFile = false;
    foreach ($req->val as $index => $value) {
      $modulo = $index % $cnt;

      if ($modulo === 0) {
        $isMatchingFingerprint = $value === $labelEnc;
        $skipFile = $label != '' && !$isMatchingFingerprint;

        if (!$skipFile) {
          $fileIndex++;
          $list[$fileIndex] = [];
        }

        continue;
      }

      if ($skipFile) {
        continue;
      }

      if ($cmds[$modulo] === "d.get_hash=") {
        $value = strtolower($value);
      }

      $list[$fileIndex][$cmds[$modulo]] = $value;
    }

    // We now have a list of torrents in a usable format
    // Now we need to prepare the array for atus
    // ref https://github.com/Novik/ruTorrent/blob/master/js/rtorrent.js#L1147
    $listFormatted = [];
    foreach ($list as $l) {

      $state = self::FILE_STATE_STOPPED;
      if ($l['d.is_open='] != "0") {
        $state = self::FILE_STATE_STARTED;
        if ($l['d.get_state='] == "0"  || $l['d.is_active='] == "0") {
          $state = self::FILE_STATE_PAUSED;
        }
      } else if ($l['d.get_hashing='] != "0") {
        $state = self::FILE_STATE_HASHING;
      } else if ($l['d.is_hash_checking='] != "0") {
        $state = self::FILE_STATE_CHECKING;
      } else if ($l['d.get_message='] != "" && $l['d.get_message='] != "Tracker: [Tried all trackers.]") {
        $state = self::FILE_STATE_ERROR;
      }

      $chunksProcessing = intval($l['d.is_hash_checking='] == "0" ? $l['d.get_completed_chunks='] : $l['d.get_chunks_hashed=']);
      $sizeChunks = intval($l['d.get_size_chunks=']);
      $chunkSize = intval($l['d.get_chunk_size=']);

      $done = floor(($chunksProcessing / $sizeChunks) * 10000) / 100;

      $downRate = intval($l['d.get_down_rate=']);
      $completedChunks = intval($l['d.get_completed_chunks=']);

      $eta = 0;
      if ($downRate > 0) {
        $eta = floor((($sizeChunks - $completedChunks) * $chunkSize) / $downRate);
      }

      $listFormatted[] = [
        'hash' => $l['d.get_hash='],
        'state' => $state,
        'downloadRate' => $downRate,
        'done' => $done,
        'eta' => $eta,
      ];
    }

    return $listFormatted;
  }

  function getServerLoad()
  {
    return sys_getloadavg();
  }

  function getDiskTotalSpace()
  {
    return disk_total_space($this->downloadsDir);
  }

  function getDiskFreeSpace()
  {
    return disk_free_space($this->downloadsDir);
  }
}
