<?php
declare(strict_types=1);

require_once __DIR__ . '/medical_replacement_images.php';

/**
 * Brand then compress a replacement portrait synchronously. Only the final
 * JPEG is published in the library; source/staging files stay outside uploads.
 * No database access, network requests or changes to the old doctor image.
 * @return array{ok:bool,url:string,width:int,height:int,size:int,original_size:int,branded_size:int,target_bytes:int}
 */
function medical_doctor_image_store(string $bytes, string $folder): array
{
    return medical_replacement_image_store($bytes, $folder, 'doctor');
}
