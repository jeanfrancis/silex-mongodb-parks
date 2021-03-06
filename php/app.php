<?php
require '../vendor/autoload.php';
$secret_key = getenv('SECRET_KEY') ? getenv('SECRET_KEY') : 'Ch4nG3_M3';
$app = new \Silex\Application();

$app->get('/', function () use ($app) {
  return $app->sendFile(getenv('OPENSHIFT_REPO_DIR') . 'static/index.html');
});

$app->get('/css/{filename}', function ($filename) use ($app){
  if (!file_exists(getenv('OPENSHIFT_REPO_DIR') . 'static/css/' . $filename)) {
    $app->abort(404);
  }
  return $app->sendFile(getenv('OPENSHIFT_REPO_DIR') . 'static/css/' . $filename, 200, array('Content-Type' => 'text/css'));
});

$app->get('/hello/{name}', function ($name) use ($app) {
  return new Response( "Hello, {$app->escape($name)}!");
});

$app->get('/parks', function () use ($app) {
  $db_connection = getenv('OPENSHIFT_MONGODB_DB_URL') ? getenv('OPENSHIFT_MONGODB_DB_URL') . getenv('OPENSHIFT_APP_NAME') : "mongodb://localhost:27017/";
  $client = new MongoClient($db_connection);
  $db = $client->selectDB(getenv('OPENSHIFT_APP_NAME'));
  $parks = new MongoCollection($db, 'parks');
  $result = $parks->find();

  $response = "[";
  foreach ($result as $park){
    $response .= json_encode($park);
    if( $result->hasNext()){ $response .= ","; }
  }
  $response .= "]";
  return $app->json(json_decode($response));
});

$app->get('/parks/within', function () use ($app) {
  $db_connection = getenv('OPENSHIFT_MONGODB_DB_URL') ? getenv('OPENSHIFT_MONGODB_DB_URL') . getenv('OPENSHIFT_APP_NAME') : "mongodb://localhost:27017/";
  $client = new MongoClient($db_connection);
  $db = $client->selectDB(getenv('OPENSHIFT_APP_NAME'));
  $parks = new MongoCollection($db, 'parks');

  #clean these input variables:
  $lat1 = floatval($app->escape($_GET['lat1']));
  $lat2 = floatval($app->escape($_GET['lat2']));
  $lon1 = floatval($app->escape($_GET['lon1']));
  $lon2 = floatval($app->escape($_GET['lon2']));
  
  if(!(is_float($lat1) && is_float($lat2) &&
       is_float($lon1) && is_float($lon2))){
    $app->json(array("error"=>"lon1,lat1,lon2,lat2 must be numeric values"), 500);
  }else{
    $result = $parks->find( 
      array( 'pos' => 
        array( '$within' => 
          array( '$box' =>
            array(
              array( $lon1, $lat1),
              array( $lon2, $lat2)
    )))));
  }
  try{ 
    $response = "[";
    foreach ($result as $park){
      $response .= json_encode($park);
      if( $result->hasNext()){ $response .= ","; }
    }
    $response .= "]";
    return $app->json(json_decode($response));
  } catch (Exception $e) {
    return $app->json(array("error"=>json_encode($e)), 500);
  }
});

$app->run();
?>
