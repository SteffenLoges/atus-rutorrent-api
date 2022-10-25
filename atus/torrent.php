<?php

namespace ATUS;

require_once(__DIR__ . '/../../php/rtorrent.php');

use rTorrent;
use rXMLRPCCommand;
use rXMLRPCRequest;


class Torrent
{

  const PRIORITY_DONT_DOWNLOAD = "0";
  const PRIORITY_NORMAL = "1";
  const PRIORITY_HIGH = "2";

  protected $hash;

  // hash can be null if the torrent is not yet added to rtorrent
  function __construct($hash = null)
  {
    $this->hash = $hash;
  }

  // Adds a torrent to rtorrent
  // returns the sha1 hash of the newly added torrent or false if it failed
  function addNew($fname, $label)
  {
    $addition = [];
    if ($this->useFingerprint) {
      $addition[] = getCmd('d.set_custom2=') . $this->fingerprintEncoded;
    }

    $ret = rTorrent::sendTorrent($fname, true, true, null, $label, true, true, true,  $addition);

    if ($ret) {
      $this->hash = $ret;
    }

    return $ret;
  }

  function getFileList()
  {

    $cmds = [
      "f.get_completed_chunks=",
      "f.get_size_chunks=",
      "f.get_priority="
    ];

    $cmd = new rXMLRPCCommand("f.multicall", [$this->hash, '']);
    $cmd->addParameters(array_map("getCmd", $cmds));

    $req = new rXMLRPCRequest($cmd);


    if (!$req->success(false)) {
      return false;
    }

    $arr = [];
    $fileIndex = -1;
    $keyCnt = count($cmds);
    foreach ($req->val as $index => $value) {
      $modulo = $index % $keyCnt;

      if ($modulo === 0) {
        $fileIndex++;
        $arr[$fileIndex] = [];
      }


      $arr[$fileIndex][$cmds[$modulo]] = $value;
    }

    return $arr;
  }

  function getFileStatus($indices)
  {

    $fileList = $this->getFileList();
    if (!$fileList) {
      return false;
    }

    $files = [];
    foreach ($indices as $i) {
      if (!isset($fileList[$i])) {
        continue;
      }

      $file = $fileList[$i];

      $files[] = [
        'index' => $i,
        'priority' => $file['f.get_priority='],
        'completed' => $file['f.get_completed_chunks='] >= $file['f.get_size_chunks='],
      ];
    }

    return $files;
  }

  function setFilePriority($priority, $indices)
  {
    $req = new rXMLRPCRequest();
    foreach ($indices as $index) {
      $req->addCommand(new rXMLRPCCommand("f.set_priority", [$this->hash, $index, $priority]));
    }

    $req->addCommand(new rXMLRPCCommand("d.update_priorities", $this->hash));
    return $req->success(false);
  }

  // ref https://github.com/Novik/ruTorrent/blob/master/plugins/data/action.php
  function getFilePath($index)
  {
    $req = new rXMLRPCRequest(
      new rXMLRPCCommand("f.get_frozen_path", [$this->hash, $index])
    );

    if (!$req->success()) {
      return false;
    }

    if ($req->val[0] != '') {
      return $req->val[0];
    }

    $req = new rXMLRPCRequest(
      array(
        new rXMLRPCCommand("d.open", $this->hash),
        new rXMLRPCCommand("f.get_frozen_path", [$this->hash, $index])
      ),
      new rXMLRPCCommand("d.close", $this->hash)
    );

    if ($req->success()) {
      return $req->val[1];
    }
  }
}
