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
use \React\Http\HttpServer;
use \React\Socket\SocketServer;

$dir_path = __DIR__ . "/../hadisdb";
$hadis = CariHadis::ambil_kitab($dir_path);

$req = function (ServerRequestInterface $request) use ($hadis) {
  return (new CariHadis($request, $hadis))->ambil_respon();
};

$server = new HttpServer($req);
$url = '172.245.68.118:80';
#$url = '127.0.0.1:80';
$socket = $server->listen(new SocketServer($url));

echo "Server running at $url" . PHP_EOL;


class CariHadis
{
  public $response;
  public static $dir_path;
  public static $matan_terjemah = ['Shahih_Bukhari', 'Shahih_Muslim', 'Sunan_Abu_Daud', 'Sunan_Tirmidzi', 'Sunan_Nasai', 'Sunan_Ibnu_Majah', 'Musnad_Darimi', 'Muwatho_Malik', 'Musnad_Ahmad', 'Sunan_Daraquthni', 'Musnad_Syafii', 'Mustadrak_Hakim', 'Shahih_Ibnu_Khuzaimah', 'Shahih_Ibnu_Hibban', 'Bulughul_Maram', 'Riyadhus_Shalihin'];

  public function __construct(ServerRequestInterface $request, array $hds)
  {
    $time = microtime(true);
    $uri = $request->getUri();
    $target = $request->getRequestTarget();

    if (preg_match('/^\/([a-zA-Z_]+)\/(\d+)$/', $target, $cocok)) {
      # /Abc_Def/123
      $kitab = trim($cocok[1]); # Abc_Def
      $id = (int)trim($cocok[2]); # 123

      if (!in_array($kitab, self::$matan_terjemah)) {
        $this->response = new Response(404, ['Content-Type' => 'application/json'], json_encode(['status' => false, 'error' => 'Kitab tidak ditemukan']));
      } else {
        $hadis = $this->ambil_hadis($kitab, $id);
        if (false === $hadis) {
          $this->response = new Response(404, ['Content-Type' => 'application/json'], json_encode(['status' => false, 'error' => 'ID hadis tidak ditemukan']));
        } else {
          $hadis = explode('|', $hadis);
          if (count($hadis) < 4) {
            $this->response = new Response(404, ['Content-Type' => 'application/json'], json_encode(['status' => false, 'error' => 'Hadis tidak ditemukan']));
            error_log('Hadis tidak ditemukan. Periksa file ' . __FILE__ . ' baris ' . __LINE__ . ' class ' . __CLASS__ . ' method ' . __METHOD__);
          } else {
            $this->response = new Response(200, ['Content-Type' => 'application/json'], json_encode(['status' => true, 'durasi' => (microtime(true) - $time), 'nass' => trim($hadis[2]), 'terjemah' => trim($hadis[3])], JSON_UNESCAPED_UNICODE));
          }
        }
      }
    } elseif (preg_match('#/=(.+)#i', $target, $cocok)) {
      if (empty($cocok[1])) return new Response(404);
      $hsl = [];
      foreach (preg_grep("/" . urldecode($cocok[1]) . "/i", $hds) as $y) {
        $z = explode('|', $y);
        $hsl[] = [((int)trim($z[0]) + 1) => (int)trim($z[1])];
      }
      $this->response = new Response(200, ['Content-Type' => 'application/json'], json_encode(['status'=>true, 'durasi' => (microtime(true) - $time), 'jumlah' => count($hsl), 'hasil' => $hsl]));
    } else {
      $this->response = new Response(200, ['Content-Type' => 'text/html'], 'Hello World!');
    }
  }

  /**
   * @return array | false
   */
  public static function ambil_kitab(string $dir_path): array
  {
    if (!is_dir($dir_path)) return false;
    self::$dir_path = $dir_path;
    $array = [];
    foreach (self::$matan_terjemah as $kitab) {
      $file = $dir_path . "/$kitab";
      if (!file_exists($file)) continue;
      $file = file($file);
      foreach ($file as $baris) {
        $array[] = $baris;
      }
    }
    return $array;
  }

  /**
   * @return string | false
   */
  private function ambil_hadis(string $kitab, int $id)
  {
    $file = self::$dir_path . "/$kitab";
    if (!file_exists($file)) return false;
    $file = file($file);
    if ($id > count($file)) return false;
    return $file[($id - 1)];
  }

  /**
   * @return object
   */
  public function ambil_respon()
  {
    return $this->response;
  }
}
