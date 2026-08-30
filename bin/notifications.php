<?php

declare(strict_types=1);

if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$app=require dirname(__DIR__).'/app/bootstrap.php';
if(!$app->installed()){fwrite(STDERR,"FilamentManager is not installed.\n");exit(1);}
try{$service=new FilamentManager\Services\NotificationService($app);$queued=$service->evaluateAll();$result=$service->processQueue(100);fwrite(STDOUT,json_encode(['queued'=>$queued]+$result,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL);}catch(Throwable $error){FilamentManager\Core\Logger::error($error);fwrite(STDERR,$error->getMessage().PHP_EOL);exit(1);}
