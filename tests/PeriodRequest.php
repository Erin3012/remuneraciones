<?php
declare(strict_types=1);
// CLI request runner used only against the disposable integration-test database.
if (PHP_SAPI!=='cli') {http_response_code(404);exit;}
$input=json_decode((string)stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
if (!preg_match('/dbname=payroll_periods_test_[a-f0-9]+(?:;|$)/',$input['dsn'])) throw new RuntimeException('Solo se permiten bases de pruebas.');
require_once __DIR__.'/../app/Database.php';
$pdo=new PDO($input['dsn'],$input['username'],$input['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
(new ReflectionProperty(Database::class,'pdo'))->setValue(null,$pdo);
session_id('global-period-test-'.sha1($input['dsn'].'-'.$input['user_id']));session_start();
$q=$pdo->prepare('SELECT * FROM users WHERE id=?');$q->execute([$input['user_id']]);$_SESSION['user']=$q->fetch();
$_SESSION['user']['company_id']=$input['company_id'];session_write_close();
$_GET=$input['get'];$_POST=$input['post'];$_FILES=$input['files']??[];
$_SERVER['REQUEST_METHOD']=$input['post']?'POST':'GET';
register_shutdown_function(static function() {fwrite(STDERR,'HTTP_STATUS='.(http_response_code()?:200)."\n");});
require __DIR__.'/../public/index.php';
