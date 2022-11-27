<?php
/**
 * Web Server sederhana menggunakan script PHP untuk menangkap, memproses dan merespon request
 * By Dannsbass
 * MIM Nilai, 9 Jan 2022
 * Update: 13 Nov 2022
 * */

require __DIR__.'/vendor/autoload.php';
$req = function(\Psr\Http\Message\ServerRequestInterface $request){
  $uri = $request->getUri();
  $target = $request->getRequestTarget();
  if(preg_match('/^\/([a-zA-Z_]+)\/(\d+)$/',$target,$cocok)){
    # /Abc_Def/123
    $kitab = trim($cocok[1]); # Abc_Def
    $id = (int)trim($cocok[2]); # 123
    $hasil = new CariHadis($kitab, $id);
    if($hasil->ada()){
      return CariHadis::respon(200, $hasil->ambilKonten());
    }else{
      return CariHadis::respon(404, $hasil->kontenKosong());
    }
  }
  // /=puasa+ramadhan
  elseif(preg_match('#/=(.+)#', $target, $cocok)){
    if(count($cocok)<1) return CariHadis::respon(404, "tidak ditemukan");
    $query = trim(urldecode($cocok[1]));
    $hasil = new CariHadis(null, null, $query);
    if($hasil->ada()){
      return CariHadis::respon(200, $hasil->ambilKonten());
    }else{
      return CariHadis::respon(200, $hasil->kontenKosong());
    }
  }else{
    return CariHadis::respon(404,["format"=>"/Nama_Kitab/123"]);
  }
};

$server = new \React\Http\HttpServer($req);
$url = '172.245.68.118:8081';
$socket = $server->listen(new \React\Socket\SocketServer($url));

echo "Server running at $url" . PHP_EOL;

final class CariHadis
{
  public $kitab='',
        $id=null, 
        $nass='', 
        $terjemah='', 
        $hasil=false, 
        $time=0, 
        $pesan='';
  
  public function __construct($kitab, $id, $query=null){
    
    $this->time = microtime(true);

    if(isset($query)){
      $query = urldecode($query);
      $matan_terjemah = self::matan_terjemah();
      foreach($matan_terjemah as $kitab){
        $file = self::arfile($kitab);
        if(false === $file){
          $this->pesan = "Maaf, file $kitab tidak ditemukan";
          return;
        }
        foreach ($file as $no => $baris) {
          if(strpos($baris, $query) !== false){
            $this->arBaris($baris);
            return;
          }elseif(strpos(self::bersihkanHarokat($baris), $query) !== false){
            $this->arBaris($baris);
            return;
          }
        }
      }
    }
    
    if(empty($kitab)){
      $this->pesan = 'kitab kosong';
      return;
    }
    
    if(empty($id)){
      $this->pesan = 'id kosong';
      return;
    } 
    
    if(!is_numeric($id)){
      $this->pesan = 'id harus angka';
      return;
    }
    
    $matan_terjemah = self::matan_terjemah();
    
    if(!in_array($kitab, $matan_terjemah)){
      $this->pesan = 'kitab tidak ditemukan';
      return;
    }
    
    $this->kitab = $kitab;
    $this->id = abs((int)$id);
    
    $file = self::arfile($kitab);

    if(false === $file){
      $this->pesan = "Maaf, file $kitab tidak ditemukan";
      return;
    }
    
    if(count($file)<$id){
      $this->pesan = 'id tidak ditemukan';
      return;
    } 
    
    $this->arBaris($file[$id - 1]); //index dimulai dari nol
    
  }
  
  public function arBaris($baris){
    $konten = explode('|', $baris);
    $this->id = (int)trim($konten[1]);
    $this->nass = trim($konten[2]);
    $this->terjemah = trim($konten[3]);
    $this->time = microtime(true) - $this->time;
    $this->hasil = true;
  }

  public static function bersihkanHarokat($baris){
    return str_replace(["َ", "ِ", "ُ", "ً", "ٍ", "ٌ", "ْ", "ّ"], '', $baris);
  }

  public static function arfile($kitab){
    $file = __DIR__."/../hadisdb/$kitab";
    if(!file_exists($file)) return false;
    return file($file);
  }

  public static function matan_terjemah(){
    include(__DIR__.'/../hadisdb/matan_terjemah.php');
    return $matan_terjemah;
  }
  
  public function ada(){
    return $this->hasil;
  }
  
  public function ambilKonten(){
    return [
      "hasil"=>true,
      "kitab"=>$this->kitab,
      "id" => $this->id,
      "nass"=>$this->nass,
      "terjemah"=>$this->terjemah,
      "durasi"=>$this->time
    ];
  }

  public function kontenKosong(){
    return [
      "hasil"=>false,
      "pesan"=>$this->pesan
    ];
  }
  
  public static function respon($code, $body){
    return new \React\Http\Message\Response(
            $code,
            ['Content-Type' => 'application/json'],
            \json_encode(
              $body,
              JSON_UNESCAPED_UNICODE |
              JSON_UNESCAPED_SLASHES |
              JSON_NUMERIC_CHECK
            )
      );
  }
}

