<?php
declare(strict_types=1);

require_once __DIR__ . '/medical_replacement_images.php';

/** Brand and synchronously store a facility replacement JPEG below 150 KB. */
function medical_facility_image_store(string $bytes, string $folder): array
{
    return medical_replacement_image_store($bytes, $folder, 'facility');
}
