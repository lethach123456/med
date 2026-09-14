<?php
declare(strict_types=1);

function front_admin_render_edit_bar(array $links): void
{
    if (!function_exists('admin_front_is_logged_in') || !admin_front_is_logged_in() || count($links) === 0) {
        return;
    }

    $safeLinks = [];
    foreach ($links as $l) {
        if (!is_array($l)) continue;
        $href = isset($l['href']) ? trim((string) $l['href']) : '';
        $label = isset($l['label']) ? trim((string) $l['label']) : '';
        $icon = isset($l['icon']) ? trim((string) $l['icon']) : '';
        if ($href === '' || $label === '') continue;
        $safeLinks[] = ['href' => $href, 'label' => $label, 'icon' => $icon];
    }
    if (count($safeLinks) === 0) return;
    ?>
    <style>
      .front-admin-bar{
        position: fixed;
        right: 14px;
        bottom: 14px;
        z-index: 2147483646;
        display:flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items:center;
        padding: 10px;
        border-radius: 12px;
        border: 1px solid rgba(255,255,255,0.18);
        background: rgba(0,0,0,0.58);
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
        box-shadow: 0 26px 80px rgba(0,0,0,0.30);
      }
      .front-admin-btn{
        display:inline-flex;
        align-items:center;
        gap: 8px;
        padding: 10px 12px;
        border-radius: 10px;
        border: 1px solid rgba(255,255,255,0.18);
        background: rgba(255,255,255,0.10);
        color: rgba(255,255,255,0.95);
        font: 600 13px/1.2 Inter, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial;
        text-decoration: none;
        cursor: pointer;
      }
      .front-admin-btn:hover{
        background: rgba(255,255,255,0.14);
      }
      @media (max-width: 680px){
        .front-admin-bar{ left: 12px; right: 12px; bottom: 12px; justify-content: center; }
        .front-admin-btn{ flex: 1 1 auto; justify-content: center; }
      }
    </style>
    <div class="front-admin-bar" role="navigation" aria-label="admin quick actions">
      <?php foreach ($safeLinks as $l): ?>
        <a class="front-admin-btn" href="<?php echo htmlspecialchars($l['href'], ENT_QUOTES, 'UTF-8'); ?>">
          <?php if ($l['icon'] !== ''): ?><i class="<?php echo htmlspecialchars($l['icon'], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i><?php endif; ?>
          <span><?php echo htmlspecialchars($l['label'], ENT_QUOTES, 'UTF-8'); ?></span>
        </a>
      <?php endforeach; ?>
    </div>
    <?php
}
