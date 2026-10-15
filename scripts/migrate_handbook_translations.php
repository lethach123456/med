<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/db.php';
require_once dirname(__DIR__).'/medical_handbook_translations.php';
try{
    $pdo=db();medreview_with_schema_migration(static fn()=>handbook_translations_migrate($pdo));
    echo "Handbook VI/EN linking ready. Existing posts preserved; no pairs guessed or seeded.\n";
}catch(Throwable $error){fwrite(STDERR,'Handbook translation migration failed: '.$error->getMessage()."\n");exit(1);}
