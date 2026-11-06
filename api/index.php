<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
session_name('portosin_admin');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off','httponly'=>true,'samesite'=>'Strict']);
session_start();
function out(array $v,int $code=200): never { http_response_code($code); echo json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
function config(): array { $c=require dirname(__DIR__).'/private/server-config.php'; if(!is_array($c)) out(['error'=>'Configuración del servidor inválida'],500); return $c; }
function db(array $c): PDO {
  $dir=dirname($c['database']); if(!is_dir($dir) && !mkdir($dir,0750,true) && !is_dir($dir)) out(['error'=>'No se pudo crear storage'],500);
  try{$p=new PDO('sqlite:'.$c['database'],null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$p->exec('PRAGMA journal_mode=WAL');$p->exec('PRAGMA foreign_keys=ON');$p->exec('CREATE TABLE IF NOT EXISTS site_data (id INTEGER PRIMARY KEY CHECK(id=1), data_json TEXT NOT NULL, updated_at TEXT NOT NULL, schema_version INTEGER NOT NULL DEFAULT 2)');$p->exec('CREATE TABLE IF NOT EXISTS draft_data (id INTEGER PRIMARY KEY CHECK(id=1), data_json TEXT NOT NULL, updated_at TEXT NOT NULL)');$p->exec('CREATE TABLE IF NOT EXISTS site_versions (id INTEGER PRIMARY KEY AUTOINCREMENT, data_json TEXT NOT NULL, created_at TEXT NOT NULL)');return $p;}catch(Throwable $e){out(['error'=>'SQLite no está disponible o no se puede abrir: '.$e->getMessage()],500);}
}
function require_admin(): void { if(empty($_SESSION['portosin_admin'])) out(['error'=>'Sesión caducada. Vuelve a iniciar sesión.'],401); }
function save_images(mixed $v,array $c): mixed {
  if(is_array($v)){foreach($v as $k=>$x)$v[$k]=save_images($x,$c);return $v;}
  if(!is_string($v)||!preg_match('~^data:image/(webp|jpeg|png);base64,([A-Za-z0-9+/=]+)$~',$v,$m)) return $v;
  $bin=base64_decode($m[2],true); if($bin===false||strlen($bin)>$c['max_image_bytes']) throw new RuntimeException('Imagen inválida o superior a 8 MB');
  $info=@getimagesizefromstring($bin); if(!$info||!in_array($info['mime'],['image/webp','image/jpeg','image/png'],true)) throw new RuntimeException('Formato de imagen no permitido');
  $ext=['image/webp'=>'webp','image/jpeg'=>'jpg','image/png'=>'png'][$info['mime']];$dir=$c['images'];if(!is_dir($dir)&&!mkdir($dir,0755,true)&&!is_dir($dir))throw new RuntimeException('No se pudo crear el directorio de imágenes');
  $name=hash('sha256',$bin).'.'.$ext;$path=$dir.'/'.$name;if(!is_file($path)&&file_put_contents($path,$bin,LOCK_EX)===false)throw new RuntimeException('No se pudo guardar una imagen');
  return 'imagenes/'.$name;
}
try {
 $c=config();$method=$_SERVER['REQUEST_METHOD']??'GET';
 if($method==='GET'){
   $p=db($c);$admin=!empty($_SESSION['portosin_admin']);$row=null;
   if($admin)$row=$p->query('SELECT data_json,updated_at FROM draft_data WHERE id=1')->fetch();
   if(!$row)$row=$p->query('SELECT data_json,updated_at FROM site_data WHERE id=1')->fetch();
   $versions=$admin?$p->query('SELECT id,created_at FROM site_versions ORDER BY id DESC LIMIT 20')->fetchAll():[];
   if(!$row) out(['ok'=>true,'migrated'=>false,'data'=>null,'admin'=>$admin,'versions'=>$versions]);
   $data=json_decode($row['data_json'],true);if(!is_array($data))out(['error'=>'La base de datos contiene datos inválidos'],500);
   out(['ok'=>true,'migrated'=>true,'updated_at'=>$row['updated_at'],'data'=>$data,'admin'=>$admin,'versions'=>$versions]);
 }
 if($method!=='POST')out(['error'=>'Método no permitido'],405);
 $origin=$_SERVER['HTTP_ORIGIN']??'';$host=parse_url('http://'.($_SERVER['HTTP_HOST']??''),PHP_URL_HOST);if($origin!=='' && !hash_equals(strtolower((string)$host),strtolower((string)(parse_url($origin,PHP_URL_HOST)??''))))out(['error'=>'Origen no permitido'],403);
 $raw=file_get_contents('php://input');$req=json_decode($raw?:'',true);if(!is_array($req))out(['error'=>'Solicitud JSON inválida'],400);
 $action=$req['action']??'';
 if($action==='auth'){
   $now=time();if(!isset($_SESSION['login_attempts'])||$now-(int)($_SESSION['login_window']??$now)>600){$_SESSION['login_attempts']=0;$_SESSION['login_window']=$now;}
   if((int)$_SESSION['login_attempts']>=6)out(['error'=>'Demasiados intentos. Espera diez minutos y vuelve a intentarlo.'],429);
   $password=(string)($req['password']??'');$hash=(string)($c['admin_password_hash']??'');
   if(strlen($password)<1||!password_verify($password,$hash)){$_SESSION['login_attempts']=(int)$_SESSION['login_attempts']+1;out(['error'=>'Contraseña incorrecta'],403);}
   unset($_SESSION['login_attempts'],$_SESSION['login_window']);session_regenerate_id(true);$_SESSION['portosin_admin']=true;out(['ok'=>true]);
 }
 if($action==='logout'){$_SESSION=[];if(ini_get('session.use_cookies')){$p=session_get_cookie_params();setcookie(session_name(),'',time()-42000,$p['path'],$p['domain']??'',$p['secure'],$p['httponly']);}session_destroy();out(['ok'=>true]);}
 require_admin();
 if($action==='save'||$action==='migrate'){
   $data=$req['data']??null;if(!is_array($data))out(['error'=>'No se han recibido datos válidos'],400);
   if(($action==='migrate')){
     $data['version']=2;$data['migration']=['source'=>'GitHub JSON','migrated_at'=>gmdate('c')];
   }
   $data=save_images($data,$c);$data['updated']=gmdate('c');$json=json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
   $p=db($c);$table=$action==='migrate'?'site_data':'draft_data';
   if($action==='migrate'){$old=$p->query('SELECT data_json FROM site_data WHERE id=1')->fetch();if($old){$bk=$p->prepare('INSERT INTO site_versions(data_json,created_at) VALUES(:d,:t)');$bk->execute([':d'=>$old['data_json'],':t'=>gmdate('c')]);}$st=$p->prepare('INSERT INTO site_data(id,data_json,updated_at,schema_version) VALUES(1,:d,:u,2) ON CONFLICT(id) DO UPDATE SET data_json=excluded.data_json,updated_at=excluded.updated_at,schema_version=2');$st->execute([':d'=>$json,':u'=>$data['updated']]);}
   $st=$p->prepare('INSERT INTO draft_data(id,data_json,updated_at) VALUES(1,:d,:u) ON CONFLICT(id) DO UPDATE SET data_json=excluded.data_json,updated_at=excluded.updated_at');$st->execute([':d'=>$json,':u'=>$data['updated']]);
   out(['ok'=>true,'data'=>$data,'updated_at'=>$data['updated'],'action'=>$action]);
 }
 if($action==='publish'){
   $p=db($c);$draft=$p->query('SELECT data_json,updated_at FROM draft_data WHERE id=1')->fetch();if(!$draft)out(['error'=>'No hay cambios guardados para publicar'],400);
   $old=$p->query('SELECT data_json FROM site_data WHERE id=1')->fetch();if($old && $old['data_json']!==$draft['data_json']){$st=$p->prepare('INSERT INTO site_versions(data_json,created_at) VALUES(:d,:t)');$st->execute([':d'=>$old['data_json'],':t'=>gmdate('c')]);$p->exec('DELETE FROM site_versions WHERE id NOT IN (SELECT id FROM site_versions ORDER BY id DESC LIMIT 30)');}
   $st=$p->prepare('INSERT INTO site_data(id,data_json,updated_at,schema_version) VALUES(1,:d,:u,2) ON CONFLICT(id) DO UPDATE SET data_json=excluded.data_json,updated_at=excluded.updated_at,schema_version=2');$st->execute([':d'=>$draft['data_json'],':u'=>$draft['updated_at']]);out(['ok'=>true,'published_at'=>gmdate('c')]);
 }
 if($action==='restore'){$id=(int)($req['version_id']??0);$p=db($c);$st=$p->prepare('SELECT data_json FROM site_versions WHERE id=:id');$st->execute([':id'=>$id]);$v=$st->fetch();if(!$v)out(['error'=>'Versión no encontrada'],404);$st=$p->prepare('INSERT INTO draft_data(id,data_json,updated_at) VALUES(1,:d,:u) ON CONFLICT(id) DO UPDATE SET data_json=excluded.data_json,updated_at=excluded.updated_at');$st->execute([':d'=>$v['data_json'],':u'=>gmdate('c')]);out(['ok'=>true]);}
 out(['error'=>'Acción desconocida'],400);
} catch(Throwable $e){out(['error'=>'Error del servidor: '.$e->getMessage()],500);}
