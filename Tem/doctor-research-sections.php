<?php
declare(strict_types=1);
// Structured evidence is escaped on output; unknown/empty sections are omitted.
$doctorResearchLabels = $doctorLanguage === 'en'
    ? ['education_json' => 'Education', 'experience_json' => 'Professional experience', 'publications_json' => 'Research & publications',
        'awards_json' => 'Awards', 'locations_json' => 'Practice locations', 'sources_json' => 'Sources & updates']
    : ['education_json' => 'Quá trình đào tạo', 'experience_json' => 'Quá trình công tác', 'publications_json' => 'Nghiên cứu & công bố',
        'awards_json' => 'Giải thưởng', 'locations_json' => 'Các nơi khám', 'sources_json' => 'Nguồn tham khảo & cập nhật'];
foreach ($doctorResearchLabels as $field => $label):
    $entries = array_filter($doctor[$field] ?? [], 'is_array');
    if ($entries === []) continue;
?>
<section class="card section-card">
  <div class="section-head"><h2><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></h2></div>
  <div class="intro-text">
    <?php foreach ($entries as $entry):
        $name = (string) ($entry['institution'] ?? $entry['facility_name'] ?? $entry['title'] ?? $entry['name'] ?? $entry['publisher'] ?? '');
        $parts = [];
        foreach (['degree', 'specialty', 'role', 'role_text', 'department', 'department_text', 'year', 'start_year', 'end_year', 'address_text', 'phone_text'] as $key) {
            if (isset($entry[$key]) && is_scalar($entry[$key]) && (string) $entry[$key] !== '') $parts[] = (string) $entry[$key];
        }
    ?>
      <p><strong><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></strong>
        <?php if ($parts !== []): ?><br><?php echo htmlspecialchars(implode(' · ', $parts), ENT_QUOTES, 'UTF-8'); ?><?php endif; ?>
        <?php $url = null; try { $url = medical_doctor_http_url($entry['url'] ?? $entry['website_url'] ?? null); } catch (InvalidArgumentException $e) {} ?>
        <?php if ($url): ?><br><a href="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="nofollow noopener noreferrer"><?php echo htmlspecialchars((string) ($entry['publisher'] ?? parse_url($url, PHP_URL_HOST)), ENT_QUOTES, 'UTF-8'); ?></a><?php endif; ?>
      </p>
    <?php endforeach; ?>
    <?php if ($field === 'sources_json' && !empty($doctor['last_researched_at'])): ?>
      <small><?php echo $doctorLanguage === 'en' ? 'Research updated: ' : 'Cập nhật dữ liệu: '; ?><?php echo htmlspecialchars($doctor['last_researched_at'], ENT_QUOTES, 'UTF-8'); ?></small>
    <?php endif; ?>
  </div>
</section>
<?php endforeach; ?>
