<?php

/**
 * Web Server sederhana menggunakan script PHP untuk menangkap, memproses dan merespon request
 * By Dannsbass
 * MIM Nilai, 9 Jan 2022
 * Update: 13 Nov 2022
 * */

require __DIR__ . '/vendor/autoload.php';

use \React\Http\Message\Response;
use \Psr\Http\Message\ServerRequestInterface;
use \React\Http\Server as HttpServer;
use \React\Socket\Server as SocketServer;

$matan_terjemah = ['Shahih_Bukhari', 'Shahih_Muslim', 'Sunan_Abu_Daud', 'Sunan_Tirmidzi', 'Sunan_Nasai', 'Sunan_Ibnu_Majah', 'Musnad_Darimi', 'Muwatho_Malik', 'Musnad_Ahmad', 'Sunan_Daraquthni', 'Musnad_Syafii', 'Mustadrak_Hakim', 'Shahih_Ibnu_Khuzaimah', 'Shahih_Ibnu_Hibban', 'Bulughul_Maram', 'Riyadhus_Shalihin'];

// buang beberapa kitab terakhir
//for($i=1; $i<=7; $i++){
//  array_pop($matan_terjemah);
//}

$hds = [];
foreach ($matan_terjemah as $kitab) {
  $file = file(__DIR__ . "/../hadisdb/$kitab");
  foreach ($file as $baris) {
    $hds[] = $baris;
  }
}

$req = function (ServerRequestInterface $request) use ($hds) {
  $time = microtime(true);
  $uri = $request->getUri();
  $target = $request->getRequestTarget();

  if (preg_match('/^\/([a-zA-Z_]+)\/(\d+)$/', $target, $cocok)) {
    # /Abc_Def/123
    $kitab = trim($cocok[1]); # Abc_Def
    $id = (int)trim($cocok[2]); # 123
    return Response::plaintext("$kitab: $id");
  }

  if (preg_match('#/=(.+)#i', $target, $cocok)) {
    if(empty($cocok[1])) return Response::plaintext('kosong');
    $hsl = [];
    foreach (preg_grep("/" . urldecode($cocok[1]) . "/i", $hds) as $y) {
      $z = explode('|', $y);
      $hsl[] = [(int)trim($z[0]) => (int)trim($z[1])];
    }
    return Response::plaintext(json_encode([['durasi' => (microtime(true) - $time)], ['jumlah' => count($hsl)], ['hasil' => $hsl]]));
  }

  return Response::plaintext('Hello World!');
};

$server = new HttpServer($req);
$url = '172.245.68.118:80';
$socket = $server->listen(new SocketServer($url));

echo "Server running at $url" . PHP_EOL;
