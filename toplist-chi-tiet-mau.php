<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/toplist_directory.php';
require_once __DIR__ . '/medical_directory.php';

$incomingToplistSlug = trim((string) ($_GET['slug'] ?? ''));
medical_redirect_legacy_path(
    '/toplist-chi-tiet-mau.php',
    medical_public_toplist_path($incomingToplistSlug),
    ['slug']
);

$pdo = db();
toplist_directory_ensure_tables($pdo);
medical_directory_ensure_tables($pdo);
$slug = trim((string) ($_GET['slug'] ?? ''));
$stmt = $pdo->prepare("SELECT * FROM medical_toplists WHERE slug=:slug AND status='published' LIMIT 1");
$stmt->execute([':slug' => $slug]);
$toplist = $stmt->fetch(PDO::FETCH_ASSOC);
if (!is_array($toplist)) { http_response_code(404); $toplist = ['id'=>0,'title'=>'Không tìm thấy bài viết','excerpt'=>'Bài Toplist chưa tồn tại hoặc chưa được xuất bản.','content'=>'','featured_image_url'=>'','updated_at'=>date('Y-m-d H:i:s')]; }
$facilityStmt = $pdo->prepare("SELECT f.*,tf.rank_order FROM medical_toplist_facilities tf JOIN medical_facilities f ON f.id=tf.facility_id WHERE tf.toplist_id=:id AND f.status='published' ORDER BY tf.rank_order ASC,tf.id ASC");
$facilityStmt->execute([':id'=>(int)$toplist['id']]);
$facilities = [];
foreach ($facilityStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $item = medical_directory_facility_with_linked_reviews(medical_directory_facility_from_row($row), true);
    // The Toplist gallery/lightbox consumes URLs. Keep gallery metadata in the
    // facility record itself, but avoid rendering PHP arrays as image sources.
    $item['gallery'] = medical_directory_gallery_urls((array) ($item['gallery'] ?? []));
    $item['rank'] = (int) $row['rank_order'];
    if (trim((string) ($item['image'] ?? '')) === '') {
        $gallery = (array) ($item['gallery'] ?? []);
        $item['image'] = (string) ($gallery[0] ?? '');
    }
    $facilities[] = $item;
}
$title = (string) $toplist['title'];
$description = (string) ($toplist['excerpt'] ?? '');
$heroImage = trim((string) ($toplist['featured_image_url'] ?? ''));
if ($heroImage === '' && isset($facilities[0])) $heroImage = (string) ($facilities[0]['image'] ?? '');
function toplist_services(array $facility): array {
    $services = array_filter((array) ($facility['services'] ?? []), static function ($service): bool {
        return is_string($service) && !preg_match('/khám/iu', $service);
    });
    return array_slice(array_values($services), 0, 6);
}
function toplist_facility_image(array $facility): string { $image=trim((string)($facility['image']??$facility['image_url']??'')); if($image===''){ $gallery=(array)($facility['gallery']??[]); $image=trim((string)($gallery[0]??'')); } return $image; }
ob_start(static function (string $html) use ($facilities): string {
    $css = '<style>.reviews{border:1px solid var(--border);border-radius:15px;overflow:hidden;padding-top:0}.reviews>h3{padding:15px 17px;margin:0!important;background:#f8fbff;border-bottom:1px solid var(--border)}.reviews .review-list{gap:0}.reviews .review{display:grid;grid-template-columns:190px minmax(0,1fr);gap:16px;padding:17px;border:0;border-radius:0;background:#fff;border-top:1px solid var(--border)}.reviews .review:first-child{border-top:0}.reviews .review-top{display:flex;flex-direction:column;align-items:flex-start;justify-content:flex-start}.reviews .review-top strong{font-size:14px}.reviews .stars{margin-top:7px}.reviews .review p{margin:0;color:#475569;font-size:13px;line-height:1.7}.reviews .review-meta{grid-column:2;display:flex;flex-wrap:wrap;gap:8px;margin-top:-7px}.reviews .review-meta span{padding:5px 8px;border-radius:7px;background:#f3f7fc;color:#64748b}@media(max-width:700px){.reviews .review{grid-template-columns:1fr}.reviews .review-meta{grid-column:1}}</style>';
    $css .= '<style>.reviews.review-shell{display:grid;grid-template-columns:210px minmax(0,1fr);padding:0;border:0;overflow:visible;background:transparent}.review-summary-box{padding:18px;border:1px solid var(--border);border-radius:15px;background:#fff}.review-summary-box h3{margin:0;font-size:15px}.review-summary-box small{color:#94a3b8}.review-summary-score{margin:18px 0 7px;color:#2563eb;font-size:44px;font-weight:800}.review-summary-score i{font-size:15px;color:#475569}.review-summary-stars{color:#f59e0b;letter-spacing:2px}.review-summary-count{margin-top:10px;color:#64748b;font-size:12px;font-weight:700}.review-feed-box{overflow:hidden;border:1px solid var(--border);border-radius:15px;background:#fff}.review-toolbar{display:flex;gap:9px;padding:12px;border-bottom:1px solid var(--border)}.review-toolbar span{padding:8px 10px;border:1px solid var(--border);border-radius:9px;color:#475569;font-size:11px;font-weight:750}.review-feed-box .review-list{border:0;border-radius:0}.review-feed-box .review{grid-template-columns:155px minmax(0,1fr);border-radius:0}.review-feed-box .review-meta{grid-column:2}@media(max-width:760px){.reviews.review-shell{grid-template-columns:1fr}.review-feed-box .review{grid-template-columns:1fr}.review-feed-box .review-meta{grid-column:1}}</style>';
    $css .= '<style>.facility-gallery-strip{display:grid;grid-template-columns:repeat(6,1fr);gap:7px;padding:9px 12px;background:#f8fbff;border-bottom:1px solid var(--border)}.facility-gallery-strip button{height:62px;padding:0;border:1px solid var(--border);border-radius:9px;overflow:hidden;background:#fff;cursor:pointer}.facility-gallery-strip img{width:100%;height:100%;object-fit:cover}.review-toolbar select{height:36px;padding:0 30px 0 10px;border:1px solid var(--border);border-radius:9px;background:#fff;color:#475569;font:700 11px inherit}.load-more-wrap{display:flex;justify-content:center;padding:14px;border-top:1px solid var(--border)}.load-more-review{padding:9px 15px;border:1px solid #bfdbfe;border-radius:999px;background:#fff;color:#2563eb;font-weight:800;cursor:pointer}@media(max-width:700px){.facility-gallery-strip{grid-template-columns:repeat(3,1fr)}.facility-gallery-strip button{height:54px}}</style>';
    $galleryMap=[]; foreach($facilities as $f){$galleryMap[(string)$f['slug']]=array_values(array_filter((array)($f['gallery']??[])));}
    $script = '<script>const toplistGalleryMap='.json_encode($galleryMap,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).';document.addEventListener("DOMContentLoaded",()=>{const esc=v=>String(v??"").replace(/[&<>\"]/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;"}[c]));const render=r=>`<article class="review"><div class="review-top"><strong>${esc(r.author||"Khách hàng")}</strong><span class="stars">★★★★★ ${esc(r.rating||"")}</span></div><p>${esc(r.excerpt||r.content||"")}</p><div class="review-meta"><span>${esc(r.date||"")}</span><span>${esc(r.service||"")}</span>${r.source?`<span>Nguồn: ${esc(r.source)}</span>`:""}</div></article>`;document.querySelectorAll(".facility").forEach(f=>{const href=f.querySelector(".detail-btn")?.getAttribute("href")||"",slug=new URL(href,location.origin).searchParams.get("slug")||"",cover=f.querySelector(".facility-cover"),imgs=toplistGalleryMap[slug]||[];if(cover&&imgs.length>1){const strip=document.createElement("div");strip.className="facility-gallery-strip";strip.innerHTML=imgs.slice(0,6).map(x=>`<button type="button"><img src="${esc(x)}" alt="Gallery"></button>`).join("");strip.onclick=e=>{const img=e.target.closest("img");if(img)cover.querySelector(":scope>img").src=img.src};cover.after(strip)}});document.querySelectorAll(".reviews").forEach(box=>{const facility=box.closest(".facility"),href=facility?.querySelector(".detail-btn")?.getAttribute("href")||"",slug=new URL(href,location.origin).searchParams.get("slug")||"",rating=(facility?.querySelector(".rating")?.textContent||"0.0/5 · 0 đánh giá").split("·"),score=rating[0].replace("/5","").trim(),count=(rating[1]||"0 đánh giá").trim(),list=box.querySelector(".review-list");box.classList.add("review-shell");box.innerHTML=`<aside class="review-summary-box"><h3>Đánh giá thực tế</h3><small>Dựa trên ${count}</small><div class="review-summary-score">${score}<i>/5</i></div><div class="review-summary-stars">★★★★★</div><div class="review-summary-count">${count}</div></aside><div class="review-feed-box"><div class="review-toolbar"><select class="service-filter"><option value="">Tất cả dịch vụ</option></select><select class="sort-filter"><option value="newest">Mới nhất</option><option value="highest">Điểm cao nhất</option></select></div><div class="load-more-wrap"><button class="load-more-review" type="button">Xem thêm đánh giá</button></div></div>`;const feed=box.querySelector(".review-feed-box");feed.insertBefore(list,feed.querySelector(".load-more-wrap"));const services=[...new Set([...list.querySelectorAll(".review-meta span:nth-child(2)")].map(x=>x.textContent.trim()).filter(Boolean))];box.querySelector(".service-filter").insertAdjacentHTML("beforeend",services.map(x=>`<option>${esc(x)}</option>`).join(""));let page=2;const load=async(reset=false)=>{const p=new URLSearchParams({facility_slug:slug,page:reset?1:page,limit:"3",service:box.querySelector(".service-filter").value,sort:box.querySelector(".sort-filter").value}),res=await fetch("/api/medical/facility-reviews.php?"+p),data=await res.json();if(reset){list.innerHTML=(data.items||[]).map(render).join("");page=2}else{list.insertAdjacentHTML("beforeend",(data.items||[]).map(render).join(""));page++}box.querySelector(".load-more-wrap").style.display=data.has_more?"flex":"none"};box.querySelectorAll("select").forEach(s=>s.onchange=()=>load(true));box.querySelector(".load-more-review").onclick=()=>load(false)});});</script>';
    $css .= '<style>.facility-profile-top{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-bottom:18px}.facility-verified{display:inline-flex;align-items:center;gap:7px;color:#047857;font-size:11px;font-weight:800;text-transform:uppercase}.facility-profile-actions{display:flex;gap:7px}.facility-profile-actions button{display:inline-flex;align-items:center;gap:6px;height:36px;padding:0 11px;border:1px solid var(--border);border-radius:10px;background:#fff;color:#334155;font:700 11px inherit;cursor:pointer}.facility-profile-actions svg{width:15px}.facility .facility-title{display:block}.facility .facility-title h2{font-size:30px}.facility .rating{display:inline-flex;margin-top:15px;font-size:14px;padding:10px 13px}.facility .rating:before{content:"★★★★★";margin-right:10px;color:#f59e0b;letter-spacing:2px}@media(max-width:600px){.facility-profile-top{align-items:flex-start;flex-direction:column}.facility-profile-actions{width:100%}.facility-profile-actions button{flex:1;justify-content:center}.facility .facility-title h2{font-size:24px}}</style>';
    $css .= '<style>.reviews.review-shell{grid-template-columns:248px minmax(0,1fr);gap:16px}.review-summary-box{padding:18px 16px;border-radius:14px}.review-summary-score{font-size:52px}.review-feed-box{border-radius:14px}.review-toolbar{justify-content:space-between}.review-toolbar:after{content:"Chỉ hiển thị đánh giá có hình ảnh";color:#667085;font-size:11px;font-weight:700}.review-feed-box .review-list{display:grid}.review-feed-box .review,.review-feed-box .review-row{display:grid;grid-template-columns:190px minmax(0,1fr) 214px 78px;gap:14px;align-items:start;padding:16px;border-top:1px solid var(--border)}.review-feed-box .review:first-child{border-top:0}.review-author{display:flex;gap:10px}.review-avatar{display:grid;place-items:center;width:46px;height:46px;border-radius:50%;background:#e8eef9;color:#31538d;font-weight:800}.review-author strong{display:block;font-size:13px}.review-author small{display:block;margin-top:4px;color:#98a2b3;font-size:11px}.review-content-top{display:flex;justify-content:space-between;gap:12px}.review-content-top .stars{font-size:11px}.review-date{color:#98a2b3;font-size:11px;font-weight:700}.review-content p{margin:8px 0 0;color:#475467;font-size:12px;line-height:1.7}.review-service{margin-top:8px;color:#667085;font-size:11px}.review-media{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}.review-media img{width:100%;aspect-ratio:1/1;border-radius:10px;object-fit:cover}.review-actions{display:flex;gap:9px;color:#98a2b3;font-size:11px}.review-more{display:flex;justify-content:center;padding:16px;border-top:1px solid var(--border)}.review-more button{border:1px solid #cfe0ff;border-radius:999px;background:#fff;color:#2563eb;padding:10px 16px;font-weight:800}@media(max-width:1100px){.review-feed-box .review,.review-feed-box .review-row{grid-template-columns:190px minmax(0,1fr)}.review-media,.review-actions{display:none}}@media(max-width:760px){.reviews.review-shell{grid-template-columns:1fr}.review-feed-box .review,.review-feed-box .review-row{grid-template-columns:1fr}.review-toolbar:after{display:none}}</style>';
    $css .= '<style>.review-summary-box{background:linear-gradient(145deg,#f4f8ff 0%,#fff 68%);border:1px solid #cfe0ff!important;padding:32px 40px!important}.review-summary-box h3{font-size:20px!important}.review-summary-box>small{display:block;margin-top:8px;font-size:13px!important}.review-summary-score{font-size:64px!important;margin-top:30px!important}.review-summary-stars{font-size:22px}.review-summary-count{font-size:16px!important;margin:15px 0 20px!important}.rating-breakdown{display:grid;gap:13px}.rating-breakdown div{display:grid;grid-template-columns:50px 1fr 45px;gap:10px;align-items:center;color:#64748b;font-size:12px}.rating-breakdown i{height:7px;border-radius:999px;background:#e8eef8;overflow:hidden}.rating-breakdown i b{display:block;height:100%;border-radius:inherit;background:linear-gradient(90deg,#60a5fa,#2563eb)}.review-verified-box{margin-top:22px;padding:17px;border:1px solid #dbe7f5;border-radius:14px;background:#fff}.review-verified-box strong{display:block;color:#2563eb;font-size:40px;line-height:1}.review-verified-box span{display:block;margin-top:7px;color:#475467;font-size:13px;font-weight:800}.review-verified-box small{display:block;margin-top:6px;color:#94a3b8;font-size:11px;line-height:1.55}.review-write{display:flex;justify-content:center;margin-top:18px;padding:11px;border:1px solid #bfd7ff;border-radius:11px;color:#2563eb;font-size:13px;font-weight:800}.review-media{display:none!important}.review-verified{display:inline-flex;margin-top:7px;padding:5px 8px;border-radius:999px;background:#ecfdf3;color:#16a34a;font-size:11px;font-weight:800}.review-toolbar select{font-size:13px!important;min-height:40px!important}.load-more-review{font-size:14px!important;padding:11px 18px!important}.review-feed-box .review,.review-feed-box .review-row{grid-template-columns:190px minmax(0,1fr) 78px!important}@media(max-width:1100px){.review-feed-box .review,.review-feed-box .review-row{grid-template-columns:190px minmax(0,1fr)!important}}</style>';
    $script .= '<script>document.addEventListener("DOMContentLoaded",()=>{document.querySelectorAll(".facility-body").forEach(body=>{const title=body.querySelector(".facility-title");if(!title)return;const top=document.createElement("div");top.className="facility-profile-top";top.innerHTML=`<span class="facility-verified"><i data-lucide="badge-check"></i>Hồ sơ cơ sở đã xác thực</span><div class="facility-profile-actions"><button type="button"><i data-lucide="heart"></i>Lưu</button><button type="button"><i data-lucide="share-2"></i>Chia sẻ</button><button type="button"><i data-lucide="triangle-alert"></i>Báo cáo</button></div>`;title.before(top)});window.lucide&&lucide.createIcons()});</script>';
    $script .= '<script>document.addEventListener("DOMContentLoaded",()=>{const shape=box=>{const facility=box.closest(".facility"),slug=new URL(facility.querySelector(".detail-btn").href).searchParams.get("slug"),media=(toplistGalleryMap[slug]||[]).slice(0,3);box.querySelectorAll(".review").forEach(row=>{if(row.dataset.shaped)return;row.dataset.shaped="1";const top=row.querySelector(".review-top"),text=row.querySelector("p"),meta=row.querySelector(".review-meta"),author=top.querySelector("strong").textContent,stars=top.querySelector(".stars").outerHTML;row.className="review-row";row.innerHTML=`<div class="review-author"><span class="review-avatar">${author.slice(0,1)}</span><div><strong>${author}</strong><small>${meta?.querySelector("span")?.textContent||""}</small><span class="review-verified">✓ Đã xác thực</span></div></div><div class="review-content"><div class="review-content-top">${stars}<span class="review-date">${meta?.querySelector("span")?.textContent||""}</span></div><p>${text?.textContent||""}</p><div class="review-service">Dịch vụ: ${meta?.querySelectorAll("span")[1]?.textContent||""}</div></div><div class="review-media">${media.map(x=>`<img src="${x}" alt="Review">`).join("")}</div><div class="review-actions"><span>♡ 0</span><span>◯ 0</span><span>⋮</span></div>`})};document.querySelectorAll(".reviews").forEach(shape);const oldFetch=window.fetch;window.fetch=async(...a)=>{const r=await oldFetch(...a);setTimeout(()=>document.querySelectorAll(".reviews").forEach(shape),0);return r};document.querySelectorAll(".load-more-wrap").forEach(w=>{const b=w.querySelector("button");if(b){const more=document.createElement("div");more.className="review-more";more.appendChild(b);w.replaceWith(more)}});window.lucide&&lucide.createIcons()});</script>';
    $script .= '<script>document.addEventListener("DOMContentLoaded",()=>{document.querySelectorAll(".review-summary-box").forEach(box=>{const score=box.querySelector(".review-summary-score")?.textContent.replace("/5","").trim()||"0.0",count=box.querySelector(".review-summary-count")?.textContent||"0 đánh giá",rate=Math.round(parseFloat(score)||0);let bars="";for(let star=5;star>=1;star--){const width=star===rate?100:0;bars+=`<div><span>${star} sao</span><i><b style="width:${width}%"></b></i><span>${width}%</span></div>`}box.innerHTML=`<h3>Đánh giá thực tế</h3><small>Dựa trên ${count} xác thực</small><div class="review-summary-score">${score}<i>/5</i></div><div class="review-summary-stars">★★★★★</div><div class="review-summary-count">${count}</div><div class="rating-breakdown">${bars}</div><div class="review-verified-box"><strong>100%</strong><span>Đánh giá xác thực</span><small>Tất cả đánh giá đều được xác minh thông tin.</small></div><a class="review-write" href="/review.php">Viết đánh giá</a>`});document.querySelectorAll(".review-media").forEach(el=>el.remove())});</script>';
    // Render lại review theo cấu trúc ổn định của trang chi tiết bác sĩ.
    $script = '';
    $reviewIndex = 0;
    $css = '<style>.reviews{margin-top:20px!important;padding:0!important;border:0!important;background:transparent!important}.doctor-review-shell{display:grid;grid-template-columns:248px minmax(0,1fr);gap:16px}.review-summary-card,.review-feed{border:1px solid #dfe8f5;border-radius:14px;background:#fff}.review-summary-card{padding:18px 16px}.review-summary-card h3{margin:0;font-size:15px}.review-summary-card p{margin:6px 0;color:#98a2b3;font-size:11px}.review-score{display:flex;align-items:flex-end;gap:6px;margin-top:16px}.review-score strong{font-size:52px;line-height:1;color:#2563eb}.review-score span{padding-bottom:8px;font-weight:800;color:#475467}.review-stars{margin-top:8px;color:#f59e0b;letter-spacing:2px}.review-total{margin-top:10px;color:#667085;font-size:13px;font-weight:700}.review-summary-bars{display:grid;gap:8px;margin-top:16px}.review-summary-row{display:grid;grid-template-columns:26px 1fr 34px;gap:8px;align-items:center;color:#667085;font-size:11px}.review-summary-row i{height:5px;border-radius:99px;background:#e9eff8;overflow:hidden}.review-summary-row b{display:block;height:100%;border-radius:inherit;background:linear-gradient(90deg,#60a5fa,#2563eb)}.review-verified-card{margin-top:14px;padding:14px;border:1px solid #dfe8f5;border-radius:12px;background:#f5f8ff}.review-verified-card strong{display:block;color:#2563eb;font-size:28px}.review-verified-card span{display:block;margin-top:6px;color:#475467;font-size:12px;font-weight:800}.review-verified-card small{display:block;margin-top:6px;color:#98a2b3;font-size:11px;line-height:1.6}.review-write-btn{display:flex;justify-content:center;margin-top:14px;padding:11px;border:1px solid #cfe0ff;border-radius:10px;color:#2563eb;font-size:12px;font-weight:800}.review-feed{overflow:hidden}.review-feed-toolbar{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:14px 16px;border-bottom:1px solid #dfe8f5}.review-toolbar-group{display:flex;gap:10px}.review-filter-pill,.review-sort-pill{padding:9px 12px;border:1px solid #dfe8f5;border-radius:10px;color:#475467;font-size:11px;font-weight:800}.review-switch{color:#667085;font-size:11px;font-weight:700}.review-list{display:grid}.review-row{display:grid;grid-template-columns:190px minmax(0,1fr) 78px;gap:14px;padding:16px;border-top:1px solid #dfe8f5}.review-row:first-child{border-top:0}.review-author{display:flex;gap:10px}.review-avatar{display:grid;place-items:center;width:46px;height:46px;border-radius:50%;background:#e8eef9;color:#31538d;font-weight:800}.review-author strong{display:block;font-size:13px}.review-author small{display:block;margin-top:4px;color:#98a2b3;font-size:11px}.verified-tag{display:inline-block;margin-top:8px;color:#16a34a;font-size:11px;font-weight:800}.review-content-top{display:flex;justify-content:space-between;gap:12px}.review-content-top .stars{color:#f59e0b;font-size:11px}.review-date{color:#98a2b3;font-size:11px;font-weight:700}.review-content p{margin:8px 0 0;color:#475467;font-size:12px;line-height:1.7}.review-service{margin-top:8px;color:#667085;font-size:11px}.review-actions{display:flex;gap:9px;color:#98a2b3;font-size:11px}.review-more{display:flex;justify-content:center;padding:16px;border-top:1px solid #dfe8f5}.review-more button{padding:10px 16px;border:1px solid #cfe0ff;border-radius:999px;background:#fff;color:#2563eb;font-weight:800}@media(max-width:900px){.doctor-review-shell{grid-template-columns:1fr}.review-row{grid-template-columns:1fr}.review-actions{display:none}.review-feed-toolbar{align-items:flex-start;flex-direction:column}}</style>';
    $html = preg_replace_callback('~<section class="reviews">.*?</section>~s', static function () use (&$reviewIndex, $facilities): string {
        $f = $facilities[$reviewIndex++] ?? [];
        $reviews = array_slice((array) ($f['reviews_list'] ?? []), 0, 3);
        if ($reviews === []) return '';
        $rating = (string) (($f['review_summary']['rating'] ?? $f['rating'] ?? '0.0'));
        // Tổng số dùng dữ liệu thật của cơ sở; danh sách bên dưới chỉ tải 3 review đầu.
        $count = (int) ($f['review_summary']['reviews'] ?? $f['reviews'] ?? count($reviews));
        if ($count < count($reviews)) { $count = count($reviews); }
        $bars = '';
        for ($star = 5; $star >= 1; $star--) { $width = $star === (int) round((float) $rating) ? 100 : 0; $bars .= '<div class="review-summary-row"><span>'.$star.' sao</span><i><b style="width:'.$width.'%"></b></i><span>'.$width.'%</span></div>'; }
        $rows = ''; foreach ($reviews as $r) { $author=htmlspecialchars((string)($r['author'] ?? 'Khách hàng'),ENT_QUOTES); $content=htmlspecialchars((string)($r['excerpt'] ?? $r['content'] ?? ''),ENT_QUOTES); $date=htmlspecialchars((string)($r['date'] ?? ''),ENT_QUOTES); $service=htmlspecialchars((string)($r['service'] ?? ''),ENT_QUOTES); $score=htmlspecialchars((string)($r['rating'] ?? '5.0'),ENT_QUOTES); $rows .= '<article class="review-row"><div class="review-author"><span class="review-avatar">'.htmlspecialchars(mb_substr(strip_tags($author),0,1),ENT_QUOTES).'</span><div><strong>'.$author.'</strong><small>'.htmlspecialchars((string)($r['location'] ?? ''),ENT_QUOTES).'</small><span class="verified-tag">✓ Đã xác thực</span></div></div><div class="review-content"><div class="review-content-top"><span class="stars">★★★★★ <b>'.$score.'</b></span><span class="review-date">'.$date.'</span></div><p>'.$content.'</p><div class="review-service">Dịch vụ: '.$service.'</div></div><div class="review-actions"><span>♡ '.(int)($r['likes']??0).'</span><span>◯ '.(int)($r['comments']??0).'</span><span>⋮</span></div></article>'; }
        return '<section class="reviews"><div class="doctor-review-shell"><aside class="review-summary-card"><h3>Đánh giá thực tế</h3><p>Dựa trên '.$count.' đánh giá xác thực</p><div class="review-score"><strong>'.htmlspecialchars($rating,ENT_QUOTES).'</strong><span>/5</span></div><div class="review-stars">★★★★★</div><div class="review-total">'.$count.' đánh giá</div><div class="review-summary-bars">'.$bars.'</div><div class="review-verified-card"><strong>100%</strong><span>Đánh giá xác thực</span><small>Tất cả đánh giá đều được xác minh thông tin.</small></div><a class="review-write-btn" href="/review.php">Viết đánh giá</a></aside><div class="review-feed"><div class="review-feed-toolbar"><div class="review-toolbar-group"><span class="review-filter-pill">⚙ Tất cả dịch vụ</span><span class="review-sort-pill">⇅ Sắp xếp: Mới nhất</span></div><span class="review-switch">Chỉ hiển thị đánh giá có hình ảnh</span></div><div class="review-list">'.$rows.'</div><div class="review-more"><button type="button">⌄ Xem thêm đánh giá</button></div></div></div></section>';
    }, $html) ?? $html;
    $css .= '<style>.verified-tag{display:inline-flex!important;align-items:center;gap:6px;margin-top:8px;padding:6px 11px;border-radius:999px;background:#edf9f1;color:#16a34a;font-size:11px!important;font-weight:800;line-height:1}.verified-tag::first-letter{font-size:15px}.review-author .verified-tag{width:max-content}</style>';
    $css .= '<style>.review-summary-card{background:linear-gradient(145deg,#f3f8ff 0%,#fff 65%,#f8fbff 100%);border-color:#cfe0ff!important}.review-toolbar-group select{height:36px;padding:0 10px;border:1px solid #dfe8f5;border-radius:9px;background:#fff;color:#475467;font:800 12px inherit}.review-more{gap:10px;flex-wrap:wrap}.review-more .review-write-inline{display:inline-flex;align-items:center;padding:10px 16px;border-radius:999px;background:#2563eb;color:#fff;font-size:12px;font-weight:800}.review-image-toggle{display:inline-flex;align-items:center;gap:8px;color:#667085;font-size:11px;font-weight:700;cursor:pointer}.review-image-toggle input{display:none}.review-image-toggle i{position:relative;width:34px;height:20px;border-radius:99px;background:#cbd5e1}.review-image-toggle i:after{content:"";position:absolute;top:3px;left:3px;width:14px;height:14px;border-radius:50%;background:#fff;transition:.2s}.review-image-toggle input:checked+i{background:#2563eb}.review-image-toggle input:checked+i:after{left:17px}.compare-meta .compare-rating{color:#f59e0b;font-weight:800}</style>';
    $script = '<script>document.addEventListener("DOMContentLoaded",()=>{const esc=v=>String(v??"").replace(/[&<>\"]/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;"}[c]));const row=r=>`<article class="review-row" data-has-image="0"><div class="review-author"><span class="review-avatar">${esc((r.author||"K").charAt(0))}</span><div><strong>${esc(r.author||"Khách hàng")}</strong><small>${esc(r.location||"")}</small><span class="verified-tag">✓ Đã xác thực</span></div></div><div class="review-content"><div class="review-content-top"><span class="stars">★★★★★ <b>${esc(r.rating||"5.0")}</b></span><span class="review-date">${esc(r.date||"")}</span></div><p>${esc(r.excerpt||r.content||"")}</p><div class="review-service">Dịch vụ: ${esc(r.service||"")}</div></div><div class="review-actions"><span>♡ ${Number(r.likes||0)}</span><span>◯ ${Number(r.comments||0)}</span><span>⋮</span></div></article>`;document.querySelectorAll(".doctor-review-shell").forEach(shell=>{const facility=shell.closest(".facility"),href=facility?.querySelector(".detail-btn")?.href||"",slug=new URL(href,location.origin).searchParams.get("slug")||"",feed=shell.querySelector(".review-feed"),list=feed.querySelector(".review-list"),toolbar=feed.querySelector(".review-toolbar-group"),services=[...new Set([...list.querySelectorAll(".review-service")].map(x=>x.textContent.replace("Dịch vụ:","").trim()).filter(Boolean))];toolbar.innerHTML=`<select class="tl-service"><option value="">Tất cả dịch vụ</option>${services.map(x=>`<option>${esc(x)}</option>`).join("")}</select><select class="tl-sort"><option value="newest">Mới nhất</option><option value="highest">Điểm cao nhất</option></select>`;feed.querySelector(".review-switch").innerHTML=`<label class="review-image-toggle">Chỉ hiển thị đánh giá có hình ảnh<input type="checkbox"><i></i></label>`;const imageToggle=feed.querySelector(".review-image-toggle input");imageToggle.onchange=()=>list.querySelectorAll(".review-row").forEach(x=>x.style.display=imageToggle.checked&&x.dataset.hasImage!=="1"?"none":"grid");const more=feed.querySelector(".review-more");more.innerHTML=`<button type="button" class="tl-more">⌄ Xem thêm đánh giá</button><a class="review-write-inline" href="/review.php">Viết đánh giá</a>`;let page=2;const load=async(reset=false)=>{const p=new URLSearchParams({facility_slug:slug,page:reset?1:page,limit:"3",service:toolbar.querySelector(".tl-service").value,sort:toolbar.querySelector(".tl-sort").value}),res=await fetch("/api/medical/facility-reviews.php?"+p),data=await res.json();if(!data.ok)return;if(reset){list.innerHTML=(data.items||[]).map(row).join("");page=2}else{list.insertAdjacentHTML("beforeend",(data.items||[]).map(row).join(""));page++}imageToggle.onchange();more.querySelector(".tl-more").style.display=data.has_more?"":"none"};toolbar.querySelectorAll("select").forEach(el=>el.addEventListener("change",()=>load(true)));more.querySelector(".tl-more").addEventListener("click",()=>load(false))});document.querySelectorAll(".compare-item").forEach(item=>{const target=document.querySelector(item.getAttribute("href")),rating=target?.querySelector(".rating")?.textContent||"",reviews=rating.split("·")[1]?.trim()||"0 đánh giá";const meta=item.querySelector(".compare-meta");if(meta){const first=meta.querySelector("span");if(first)first.classList.add("compare-rating");meta.insertAdjacentHTML("beforeend",`<span>${esc(reviews)}</span>`)}});});</script>';
    // Gallery của từng cơ sở trong Toplist: chỉ hiển thị ảnh thực có trong DB.
    $toplistFacilityGalleries = [];
    foreach ($facilities as $facility) {
        $images = array_merge(
            [(string) ($facility['image'] ?? $facility['image_url'] ?? '')],
            (array) ($facility['gallery'] ?? [])
        );
        $images = array_values(array_unique(array_filter(array_map('trim', $images))));
        $toplistFacilityGalleries[(string) ($facility['slug'] ?? '')] = $images;
    }
    $css .= '<style>.toplist-gallery-strip{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:8px;padding:12px;background:#f8fbff;border-bottom:1px solid #dfe8f5}.toplist-gallery-thumb{height:64px;padding:0;border:1px solid #dfe8f5;border-radius:10px;overflow:hidden;background:#fff;cursor:pointer}.toplist-gallery-thumb img{display:block;width:100%;height:100%;object-fit:cover}@media(max-width:700px){.toplist-gallery-strip{grid-template-columns:repeat(3,minmax(0,1fr))}.toplist-gallery-thumb{height:58px}}</style>';
    $script .= '<script>document.addEventListener("DOMContentLoaded",()=>{const galleries='.json_encode($toplistFacilityGalleries, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).';document.querySelectorAll(".facility").forEach(card=>{const href=card.querySelector(".detail-btn")?.getAttribute("href")||"",slug=new URL(href,location.origin).searchParams.get("slug")||"",images=galleries[slug]||[],cover=card.querySelector(".facility-cover");if(!cover||images.length<2)return;const strip=document.createElement("div");strip.className="toplist-gallery-strip";images.slice(0,6).forEach(src=>{const button=document.createElement("button"),image=document.createElement("img");button.type="button";button.className="toplist-gallery-thumb";image.src=src;image.alt="Ảnh cơ sở";button.append(image);button.addEventListener("click",()=>{const main=cover.querySelector("img");if(main){const current=main.src;main.src=image.src;image.src=current;}});strip.append(button);});cover.after(strip);});});</script>';
    $css .= '<style>.facility .facility-title{display:block!important}.facility .facility-title>div{display:block}.facility .facility-title h2{margin:0!important;color:#0f172a!important;font-size:clamp(30px,3vw,42px)!important;line-height:1.12!important;letter-spacing:-.045em!important}.facility .facility-title h2:after{content:"✓";display:inline-grid;place-items:center;width:32px;height:32px;margin-left:12px;vertical-align:middle;border-radius:50%;background:rgba(37,99,235,.12);color:#2563eb;font-size:17px;font-weight:900;letter-spacing:0}.facility .facility-title .subtitle{max-width:900px;margin:20px 0 0!important;color:#64748b!important;font-size:16px!important;line-height:1.7!important}.facility .facility-title .rating{display:inline-flex!important;align-items:center;margin-top:22px;padding:13px 18px!important;border:1px solid #d8e5fb!important;border-radius:16px!important;background:linear-gradient(100deg,#fffbed,#fff)!important;color:#0f172a!important;font-size:16px!important;font-weight:800!important}.facility .facility-title .rating:before{content:"★★★★★";margin-right:14px;color:#f59e0b;font-size:23px;letter-spacing:2px;line-height:1}.facility-profile-eyebrow{display:inline-flex;align-items:center;gap:8px;margin-bottom:28px;color:#047857;font-size:13px;font-weight:800;text-transform:uppercase}.facility-profile-eyebrow:before{content:"✓";display:grid;place-items:center;width:22px;height:22px;border:2px solid currentColor;border-radius:50%;font-size:12px;font-weight:900}</style>';
    $css .= '<style>.facility .facility-title h2{font-size:clamp(26px,2.35vw,34px)!important}.facility .facility-title h2:after{width:26px;height:26px;margin-left:9px;font-size:14px}.facility .facility-title .subtitle{margin-top:13px!important;font-size:14px!important;line-height:1.65!important}.facility .facility-title .rating{margin-top:16px;padding:10px 13px!important;border-radius:13px!important;font-size:13px!important}.facility .facility-title .rating:before{margin-right:10px;font-size:18px;letter-spacing:1px}.facility-profile-eyebrow{margin-bottom:18px;font-size:11px}.facility-profile-eyebrow:before{width:18px;height:18px;font-size:10px}</style>';
    $css .= '<style>.toplist-facility-actions{position:absolute;z-index:3;top:14px;right:14px;display:flex;gap:7px}.toplist-facility-actions button{display:inline-flex;align-items:center;gap:6px;height:34px;padding:0 10px;border:1px solid rgba(255,255,255,.66);border-radius:10px;background:rgba(255,255,255,.62);box-shadow:0 6px 18px rgba(15,23,42,.14);color:#334155;font:800 11px inherit;cursor:pointer;backdrop-filter:blur(14px) saturate(150%);-webkit-backdrop-filter:blur(14px) saturate(150%)}.toplist-facility-actions button:hover{background:rgba(255,255,255,.82)}.toplist-facility-actions svg{width:15px;height:15px;stroke-width:2}@media(max-width:700px){.toplist-facility-actions{top:10px;right:10px}.toplist-facility-actions button{width:32px;padding:0;justify-content:center}.toplist-facility-actions button span{display:none}}</style>';
    /* Ảnh gallery theo từng cơ sở: lightbox độc lập, không trộn ảnh giữa các cơ sở. */
    $galleryLightboxJson = json_encode(
        $toplistFacilityGalleries,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    );
    $css .= <<<'HTML'
<style id="toplist-gallery-lightbox-style">
.facility-cover.has-toplist-gallery{cursor:zoom-in}.facility-cover.has-toplist-gallery:focus-visible{outline:3px solid #93c5fd;outline-offset:-3px}.toplist-gallery-open-hint{position:absolute;z-index:2;right:14px;bottom:14px;display:inline-flex;align-items:center;gap:6px;padding:7px 10px;border:1px solid rgba(255,255,255,.62);border-radius:999px;background:rgba(15,23,42,.58);box-shadow:0 8px 18px rgba(15,23,42,.18);color:#fff;font-size:11px;font-weight:800;line-height:1;pointer-events:none;opacity:0;transform:translateY(4px);transition:opacity .2s ease,transform .2s ease;backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px)}.toplist-gallery-open-hint svg{width:14px;height:14px}.facility-cover.has-toplist-gallery:hover .toplist-gallery-open-hint,.facility-cover.has-toplist-gallery:focus-within .toplist-gallery-open-hint{opacity:1;transform:translateY(0)}.toplist-gallery-strip{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:8px;padding:12px;background:linear-gradient(180deg,#fbfdff,#f7faff);border-bottom:1px solid #dfe8f5}.toplist-gallery-thumb{position:relative;height:64px;padding:0;border:1px solid #dfe8f5;border-radius:10px;overflow:hidden;background:#fff;cursor:zoom-in;box-shadow:0 2px 5px rgba(15,23,42,.025);transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease}.toplist-gallery-thumb img{display:block;width:100%;height:100%;object-fit:cover}.toplist-gallery-thumb:hover{z-index:1;border-color:#93c5fd;box-shadow:0 8px 16px rgba(37,99,235,.14);transform:translateY(-2px)}.toplist-gallery-thumb:focus-visible{z-index:2;outline:3px solid #93c5fd;outline-offset:2px}.toplist-lightbox{position:fixed;z-index:9999;inset:0;display:grid;place-items:center;padding:clamp(12px,3vw,36px);visibility:hidden;pointer-events:none;opacity:0;transition:opacity .22s ease,visibility 0s linear .22s}.toplist-lightbox.is-open{visibility:visible;pointer-events:auto;opacity:1;transition:opacity .22s ease}.toplist-lightbox-backdrop{position:absolute;inset:0;background:rgba(8,15,30,.76);backdrop-filter:blur(10px) saturate(115%);-webkit-backdrop-filter:blur(10px) saturate(115%)}.toplist-lightbox-dialog{position:relative;z-index:1;display:grid;grid-template-rows:auto minmax(0,1fr) auto;width:min(1120px,100%);max-height:min(860px,calc(100vh - 28px));overflow:hidden;border:1px solid rgba(255,255,255,.18);border-radius:22px;background:linear-gradient(145deg,rgba(25,38,62,.96),rgba(10,18,34,.98));box-shadow:0 26px 90px rgba(2,8,23,.5);color:#fff;opacity:0;transform:translateY(12px) scale(.982);transition:opacity .24s ease,transform .24s cubic-bezier(.2,.75,.25,1)}.toplist-lightbox.is-open .toplist-lightbox-dialog{opacity:1;transform:translateY(0) scale(1)}.toplist-lightbox-top{display:flex;align-items:center;justify-content:space-between;gap:16px;min-height:56px;padding:12px 14px 10px 18px;border-bottom:1px solid rgba(255,255,255,.1)}.toplist-lightbox-title{overflow:hidden;color:#f8fbff;font-size:13px;font-weight:800;line-height:1.35;white-space:nowrap;text-overflow:ellipsis}.toplist-lightbox-close,.toplist-lightbox-nav{display:grid;place-items:center;border:0;color:#fff;cursor:pointer;transition:background .18s ease,transform .18s ease}.toplist-lightbox-close{flex:0 0 auto;width:34px;height:34px;border-radius:10px;background:rgba(255,255,255,.1)}.toplist-lightbox-close:hover,.toplist-lightbox-nav:hover:not(:disabled){background:rgba(255,255,255,.2);transform:scale(1.04)}.toplist-lightbox-close:focus-visible,.toplist-lightbox-nav:focus-visible{outline:3px solid #93c5fd;outline-offset:2px}.toplist-lightbox-close svg{width:20px;height:20px}.toplist-lightbox-stage{position:relative;display:grid;min-height:220px;place-items:center;padding:20px 64px;background:radial-gradient(circle at center,rgba(57,87,134,.28),transparent 62%);touch-action:pan-y}.toplist-lightbox-image{display:block;max-width:100%;max-height:calc(min(860px,100vh - 28px) - 126px);border-radius:12px;box-shadow:0 14px 42px rgba(0,0,0,.3);opacity:0;transform:scale(.986);transition:opacity .2s ease,transform .2s ease}.toplist-lightbox-image.is-visible{opacity:1;transform:scale(1)}.toplist-lightbox-loader{position:absolute;width:32px;height:32px;border:3px solid rgba(255,255,255,.22);border-top-color:#fff;border-radius:50%;opacity:0;animation:toplistGallerySpin .75s linear infinite;transition:opacity .16s ease}.toplist-lightbox.is-loading .toplist-lightbox-loader{opacity:1}.toplist-lightbox.is-loading .toplist-lightbox-image{opacity:0}.toplist-lightbox.is-error .toplist-lightbox-loader{display:none}.toplist-lightbox-error{position:absolute;max-width:calc(100% - 44px);padding:10px 13px;border-radius:10px;background:rgba(127,29,29,.85);font-size:12px;line-height:1.45;text-align:center;opacity:0;transform:translateY(5px);transition:opacity .18s ease,transform .18s ease}.toplist-lightbox.is-error .toplist-lightbox-error{opacity:1;transform:translateY(0)}.toplist-lightbox-nav{position:absolute;top:50%;width:44px;height:44px;border:1px solid rgba(255,255,255,.12);border-radius:50%;background:rgba(15,23,42,.58);box-shadow:0 8px 22px rgba(0,0,0,.18);transform:translateY(-50%);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px)}.toplist-lightbox-nav:hover:not(:disabled){transform:translateY(-50%) scale(1.05)}.toplist-lightbox-nav.prev{left:14px}.toplist-lightbox-nav.next{right:14px}.toplist-lightbox-nav:disabled{visibility:hidden;pointer-events:none}.toplist-lightbox-nav svg{width:22px;height:22px}.toplist-lightbox-foot{display:flex;align-items:center;justify-content:space-between;gap:12px;min-height:48px;padding:12px 18px;border-top:1px solid rgba(255,255,255,.1);color:#cbd5e1;font-size:11px}.toplist-lightbox-counter{flex:0 0 auto;color:#fff;font-weight:800}.toplist-lightbox-caption{overflow:hidden;margin:0;white-space:nowrap;text-overflow:ellipsis;text-align:right}@keyframes toplistGallerySpin{to{transform:rotate(360deg)}}body.toplist-lightbox-open{overflow:hidden}@media(max-width:700px){.toplist-gallery-strip{grid-template-columns:repeat(3,minmax(0,1fr));gap:6px;padding:8px}.toplist-gallery-thumb{height:54px;border-radius:8px}.toplist-gallery-open-hint{right:10px;bottom:10px;padding:6px 8px;font-size:10px;opacity:.92;transform:none}.toplist-lightbox{padding:10px}.toplist-lightbox-dialog{max-height:calc(100vh - 20px);border-radius:18px}.toplist-lightbox-top{min-height:52px;padding:10px 11px 10px 14px}.toplist-lightbox-title{font-size:12px}.toplist-lightbox-stage{min-height:180px;padding:16px 52px}.toplist-lightbox-image{max-height:calc(100vh - 140px);border-radius:9px}.toplist-lightbox-nav{width:38px;height:38px}.toplist-lightbox-nav.prev{left:8px}.toplist-lightbox-nav.next{right:8px}.toplist-lightbox-foot{min-height:42px;padding:9px 13px;font-size:10px}.toplist-lightbox-caption{display:none}}@media(prefers-reduced-motion:reduce){.toplist-gallery-thumb,.toplist-gallery-open-hint,.toplist-lightbox,.toplist-lightbox-dialog,.toplist-lightbox-image,.toplist-lightbox-error,.toplist-lightbox-close,.toplist-lightbox-nav{transition-duration:.01ms!important;animation-duration:.01ms!important;animation-iteration-count:1!important;scroll-behavior:auto!important}}
</style>
HTML;
    /* Chuyển động gallery kiểu iOS: nền kính mờ, nảy nhẹ khi mở và trượt theo hướng khi đổi ảnh. */
    $css .= <<<'HTML'
<style id="toplist-gallery-lightbox-ios-motion">
.toplist-lightbox{
    --toplist-ios-ease:cubic-bezier(.22,1,.36,1);
    --toplist-ios-spring:cubic-bezier(.16,1,.3,1.16);
    transition:opacity .34s var(--toplist-ios-ease),visibility 0s linear .34s;
    -webkit-tap-highlight-color:transparent;
}
.toplist-lightbox.is-open{transition:opacity .34s var(--toplist-ios-ease)}
.toplist-lightbox-backdrop{
    background:radial-gradient(circle at 50% 18%,rgba(86,131,218,.22),transparent 44%),rgba(3,10,24,.72);
    opacity:0;
    transform:scale(1.045);
    transition:opacity .38s var(--toplist-ios-ease),transform .56s var(--toplist-ios-ease);
}
.toplist-lightbox.is-open .toplist-lightbox-backdrop{opacity:1;transform:scale(1)}
.toplist-lightbox-dialog{
    border-color:rgba(255,255,255,.23);
    border-radius:28px;
    background:linear-gradient(145deg,rgba(29,43,70,.97),rgba(7,15,29,.985));
    box-shadow:0 34px 100px rgba(0,0,0,.52),inset 0 1px 0 rgba(255,255,255,.14);
    opacity:0;
    transform:translate3d(0,26px,0) scale(.91);
    filter:blur(3px);
    transition:opacity .34s var(--toplist-ios-ease),transform .54s var(--toplist-ios-spring),filter .32s ease;
    will-change:transform,opacity;
}
.toplist-lightbox-dialog:before{
    position:absolute;
    z-index:-1;
    inset:0;
    border-radius:inherit;
    background:linear-gradient(130deg,rgba(255,255,255,.11),transparent 28%,transparent 72%,rgba(120,169,255,.09));
    content:"";
    pointer-events:none;
}
.toplist-lightbox.is-open .toplist-lightbox-dialog{opacity:1;transform:translate3d(0,0,0) scale(1);filter:blur(0)}
.toplist-lightbox.is-closing .toplist-lightbox-dialog{transform:translate3d(0,18px,0) scale(.96)}
.toplist-lightbox-backdrop{backdrop-filter:blur(1px) saturate(1.04);-webkit-backdrop-filter:blur(1px) saturate(1.04)}
.toplist-lightbox.is-open .toplist-lightbox-backdrop{backdrop-filter:blur(20px) saturate(1.22);-webkit-backdrop-filter:blur(20px) saturate(1.22)}
.toplist-lightbox-dialog{backdrop-filter:blur(26px) saturate(1.3);-webkit-backdrop-filter:blur(26px) saturate(1.3)}
.toplist-lightbox-top{border-bottom-color:rgba(255,255,255,.12);background:linear-gradient(180deg,rgba(255,255,255,.085),rgba(255,255,255,.015))}
.toplist-lightbox-title{color:#fff;text-shadow:0 1px 14px rgba(0,0,0,.24)}
.toplist-lightbox-stage{
    overflow:hidden;
    background:radial-gradient(ellipse at center,rgba(94,132,191,.34),rgba(9,18,34,.04) 59%),linear-gradient(135deg,rgba(255,255,255,.025),rgba(255,255,255,0));
}
.toplist-lightbox-stage:after{
    position:absolute;
    z-index:0;
    inset:0;
    background:linear-gradient(90deg,rgba(255,255,255,.035),transparent 24%,transparent 76%,rgba(255,255,255,.025));
    content:"";
    pointer-events:none;
}
.toplist-lightbox-image{
    position:relative;
    z-index:1;
    border:1px solid rgba(255,255,255,.13);
    border-radius:16px;
    box-shadow:0 20px 48px rgba(0,0,0,.34),0 2px 7px rgba(0,0,0,.26);
    transform:translate3d(0,10px,0) scale(.968);
    transition:opacity .2s ease,transform .28s var(--toplist-ios-ease),filter .2s ease;
    will-change:transform,opacity;
}
.toplist-lightbox-image.is-visible{transform:translate3d(0,0,0) scale(1)}
.toplist-lightbox[data-gallery-direction="next"] .toplist-lightbox-image.is-visible{animation:toplistGalleryImageNext .42s var(--toplist-ios-spring) both}
.toplist-lightbox[data-gallery-direction="previous"] .toplist-lightbox-image.is-visible{animation:toplistGalleryImagePrevious .42s var(--toplist-ios-spring) both}
.toplist-lightbox[data-gallery-direction="open"] .toplist-lightbox-image.is-visible{animation:toplistGalleryImageOpen .46s var(--toplist-ios-spring) both}
.toplist-lightbox.is-image-transitioning[data-gallery-direction="next"] .toplist-lightbox-image{transform:translate3d(-16px,0,0) scale(.974)}
.toplist-lightbox.is-image-transitioning[data-gallery-direction="previous"] .toplist-lightbox-image{transform:translate3d(16px,0,0) scale(.974)}
.toplist-lightbox-close,.toplist-lightbox-nav,.toplist-gallery-thumb{touch-action:manipulation;-webkit-tap-highlight-color:transparent}
.toplist-lightbox-close,.toplist-lightbox-nav{
    border:1px solid rgba(255,255,255,.14);
    background:rgba(255,255,255,.105);
    box-shadow:0 8px 20px rgba(0,0,0,.16),inset 0 1px 0 rgba(255,255,255,.13);
    backdrop-filter:blur(15px) saturate(160%);
    -webkit-backdrop-filter:blur(15px) saturate(160%);
    transition:background .18s ease,transform .3s var(--toplist-ios-spring),box-shadow .2s ease;
}
.toplist-lightbox-close:hover,.toplist-lightbox-nav:hover:not(:disabled){background:rgba(255,255,255,.2);box-shadow:0 12px 24px rgba(0,0,0,.23),inset 0 1px 0 rgba(255,255,255,.16)}
.toplist-lightbox-close:active{transform:scale(.87)!important;transition-duration:.12s}
.toplist-lightbox-nav:active:not(:disabled){transform:translateY(-50%) scale(.87)!important;transition-duration:.12s}
.toplist-lightbox-loader,.toplist-lightbox-error,.toplist-lightbox-nav{z-index:2}
.toplist-lightbox-loader{border-color:rgba(255,255,255,.2);border-top-color:#fff;box-shadow:0 4px 16px rgba(0,0,0,.24)}
.toplist-lightbox-error{border:1px solid rgba(255,255,255,.16);background:rgba(125,32,47,.76);box-shadow:0 12px 32px rgba(0,0,0,.24);backdrop-filter:blur(16px) saturate(1.25);-webkit-backdrop-filter:blur(16px) saturate(1.25)}
.toplist-lightbox-foot{border-top-color:rgba(255,255,255,.12);background:linear-gradient(0deg,rgba(0,0,0,.23),rgba(255,255,255,.045))}
.toplist-lightbox-counter{display:inline-flex;align-items:center;min-height:26px;padding:0 9px;border:1px solid rgba(255,255,255,.15);border-radius:999px;background:rgba(255,255,255,.085);box-shadow:inset 0 1px 0 rgba(255,255,255,.12);color:#fff;font-variant-numeric:tabular-nums;backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px)}
.toplist-lightbox-caption{color:rgba(226,232,240,.86);text-shadow:0 1px 10px rgba(0,0,0,.22)}
.toplist-gallery-thumb:active{transform:scale(.94);transition-duration:.12s}
.facility-cover.has-toplist-gallery:active>img{transform:scale(1.018)}
.facility-cover.has-toplist-gallery>img{transition:transform .52s var(--toplist-ios-ease),filter .32s ease}
.facility-cover.has-toplist-gallery:hover>img{transform:scale(1.018);filter:saturate(1.05)}
@keyframes toplistGalleryImageOpen{from{opacity:0;transform:translate3d(0,18px,0) scale(.94)}to{opacity:1;transform:translate3d(0,0,0) scale(1)}}
@keyframes toplistGalleryImageNext{from{opacity:0;transform:translate3d(28px,0,0) scale(.975)}to{opacity:1;transform:translate3d(0,0,0) scale(1)}}
@keyframes toplistGalleryImagePrevious{from{opacity:0;transform:translate3d(-28px,0,0) scale(.975)}to{opacity:1;transform:translate3d(0,0,0) scale(1)}}
@media(max-width:700px){
    .toplist-lightbox-dialog{border-radius:23px}
    .toplist-lightbox-image{border-radius:12px}
    .toplist-lightbox-backdrop{background:radial-gradient(circle at 50% 12%,rgba(82,126,214,.18),transparent 43%),rgba(3,10,24,.78)}
}
@media(prefers-reduced-motion:reduce){
    .toplist-lightbox,.toplist-lightbox-backdrop,.toplist-lightbox-dialog,.toplist-lightbox-image,.toplist-lightbox-close,.toplist-lightbox-nav,.toplist-gallery-thumb,.facility-cover.has-toplist-gallery>img{animation:none!important;transition-duration:.01ms!important;transform:none!important;filter:none!important}
}
</style>
HTML;
    $script .= '<script id="toplist-gallery-lightbox-data" type="application/json">' . ($galleryLightboxJson ?: '{}') . '</script>';
    $script .= <<<'HTML'
<script>
document.addEventListener("DOMContentLoaded", () => {
    const dataNode = document.getElementById("toplist-gallery-lightbox-data");
    let galleries = {};
    try { galleries = JSON.parse(dataNode?.textContent || "{}"); } catch (_) { galleries = {}; }

    const uniqueImages = values => {
        const seen = new Set();
        return (Array.isArray(values) ? values : []).map(value => String(value || "").trim()).filter(value => {
            if (!value || seen.has(value)) return false;
            seen.add(value);
            return true;
        });
    };
    const svg = {
        close: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
        previous: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        next: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        zoom: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="5.5" fill="none" stroke="currentColor" stroke-width="2"/><path d="m16 16 4 4M11 8v6M8 11h6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>'
    };
    const lightbox = document.createElement("section");
    lightbox.className = "toplist-lightbox";
    lightbox.setAttribute("aria-hidden", "true");
    lightbox.innerHTML = `<div class="toplist-lightbox-backdrop" data-lightbox-dismiss></div><div class="toplist-lightbox-dialog" role="dialog" aria-modal="true" aria-labelledby="toplistLightboxTitle"><div class="toplist-lightbox-top"><strong class="toplist-lightbox-title" id="toplistLightboxTitle"></strong><button class="toplist-lightbox-close" type="button" aria-label="Đóng ảnh" title="Đóng ảnh">${svg.close}</button></div><div class="toplist-lightbox-stage"><img class="toplist-lightbox-image" alt=""><span class="toplist-lightbox-loader" aria-hidden="true"></span><span class="toplist-lightbox-error" role="status">Không thể tải ảnh này.</span><button class="toplist-lightbox-nav prev" type="button" aria-label="Ảnh trước" title="Ảnh trước">${svg.previous}</button><button class="toplist-lightbox-nav next" type="button" aria-label="Ảnh tiếp theo" title="Ảnh tiếp theo">${svg.next}</button></div><div class="toplist-lightbox-foot"><span class="toplist-lightbox-counter"></span><p class="toplist-lightbox-caption"></p></div></div>`;
    document.body.append(lightbox);

    const dialog = lightbox.querySelector(".toplist-lightbox-dialog");
    const stage = lightbox.querySelector(".toplist-lightbox-stage");
    const stageImage = lightbox.querySelector(".toplist-lightbox-image");
    const titleNode = lightbox.querySelector(".toplist-lightbox-title");
    const captionNode = lightbox.querySelector(".toplist-lightbox-caption");
    const counterNode = lightbox.querySelector(".toplist-lightbox-counter");
    const previousButton = lightbox.querySelector(".toplist-lightbox-nav.prev");
    const nextButton = lightbox.querySelector(".toplist-lightbox-nav.next");
    const closeButton = lightbox.querySelector(".toplist-lightbox-close");
    let activeImages = [];
    let activeIndex = 0;
    let activeName = "Cơ sở y tế";
    let activeTrigger = null;
    let imageToken = 0;
    let swipeStart = null;
    let closeTimer = 0;

    const isOpen = () => lightbox.classList.contains("is-open");
    const wrapIndex = index => (index + activeImages.length) % activeImages.length;
    const updateNavigation = () => {
        const hasNavigation = activeImages.length > 1;
        previousButton.disabled = !hasNavigation;
        nextButton.disabled = !hasNavigation;
        counterNode.textContent = `${activeIndex + 1} / ${activeImages.length}`;
    };
    const preloadNearby = () => {
        if (activeImages.length < 2) return;
        [activeIndex - 1, activeIndex + 1].forEach(index => {
            const preload = new Image();
            preload.src = activeImages[wrapIndex(index)];
        });
    };
    const showImage = (requestedIndex, direction = "next") => {
        if (!activeImages.length) return;
        activeIndex = wrapIndex(requestedIndex);
        const source = activeImages[activeIndex];
        const token = ++imageToken;
        lightbox.dataset.galleryDirection = direction === "previous" ? "previous" : (direction === "open" ? "open" : "next");
        titleNode.textContent = activeName;
        captionNode.textContent = `Ảnh ${activeIndex + 1} trong thư viện của ${activeName}`;
        updateNavigation();
        lightbox.classList.add("is-loading", "is-image-transitioning");
        lightbox.classList.remove("is-error");
        stageImage.classList.remove("is-visible");
        const preload = new Image();
        const complete = () => {
            if (token !== imageToken) return;
            stageImage.src = source;
            stageImage.alt = `${activeName} — ảnh ${activeIndex + 1}`;
            requestAnimationFrame(() => {
                stageImage.classList.add("is-visible");
                lightbox.classList.remove("is-image-transitioning");
            });
            lightbox.classList.remove("is-loading");
            preloadNearby();
        };
        const fail = () => {
            if (token !== imageToken) return;
            stageImage.removeAttribute("src");
            lightbox.classList.remove("is-loading");
            lightbox.classList.remove("is-image-transitioning");
            lightbox.classList.add("is-error");
        };
        preload.addEventListener("load", complete, { once: true });
        preload.addEventListener("error", fail, { once: true });
        preload.src = source;
        if (preload.complete) (preload.naturalWidth ? complete : fail)();
    };
    const openLightbox = (images, index, name, trigger) => {
        activeImages = uniqueImages(images);
        if (!activeImages.length) return;
        activeName = name || "Cơ sở y tế";
        activeTrigger = trigger || document.activeElement;
        activeIndex = Math.max(0, Math.min(Number(index) || 0, activeImages.length - 1));
        window.clearTimeout(closeTimer);
        lightbox.classList.remove("is-closing", "is-error");
        lightbox.dataset.galleryDirection = "open";
        document.body.classList.add("toplist-lightbox-open");
        lightbox.setAttribute("aria-hidden", "false");
        lightbox.classList.add("is-open");
        showImage(activeIndex, "open");
        window.setTimeout(() => closeButton.focus({ preventScroll: true }), 0);
    };
    const closeLightbox = () => {
        if (!isOpen()) return;
        ++imageToken;
        lightbox.classList.add("is-closing");
        lightbox.classList.remove("is-open", "is-loading", "is-error");
        lightbox.setAttribute("aria-hidden", "true");
        document.body.classList.remove("toplist-lightbox-open");
        window.clearTimeout(closeTimer);
        closeTimer = window.setTimeout(() => lightbox.classList.remove("is-closing", "is-image-transitioning"), 390);
        const trigger = activeTrigger;
        activeTrigger = null;
        if (trigger && typeof trigger.focus === "function") window.setTimeout(() => trigger.focus({ preventScroll: true }), 0);
    };
    const move = offset => { if (activeImages.length > 1) showImage(activeIndex + offset, offset < 0 ? "previous" : "next"); };

    closeButton.addEventListener("click", closeLightbox);
    lightbox.querySelector("[data-lightbox-dismiss]").addEventListener("click", closeLightbox);
    previousButton.addEventListener("click", () => move(-1));
    nextButton.addEventListener("click", () => move(1));
    document.addEventListener("keydown", event => {
        if (!isOpen()) return;
        if (event.key === "Escape") { event.preventDefault(); closeLightbox(); return; }
        if (event.key === "ArrowLeft") { event.preventDefault(); move(-1); return; }
        if (event.key === "ArrowRight") { event.preventDefault(); move(1); return; }
        if (event.key === "Tab") {
            const focusable = [...dialog.querySelectorAll("button:not([disabled])")];
            if (!focusable.length) return;
            const first = focusable[0], last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        }
    });
    stage.addEventListener("pointerdown", event => {
        if (event.pointerType === "touch" && !event.target.closest("button")) swipeStart = { x: event.clientX, y: event.clientY };
    });
    stage.addEventListener("pointerup", event => {
        if (!swipeStart || event.pointerType !== "touch") return;
        const deltaX = event.clientX - swipeStart.x, deltaY = event.clientY - swipeStart.y;
        swipeStart = null;
        if (Math.abs(deltaX) > 54 && Math.abs(deltaX) > Math.abs(deltaY)) move(deltaX > 0 ? -1 : 1);
    });
    stage.addEventListener("pointercancel", () => { swipeStart = null; });

    document.querySelectorAll(".facility").forEach(card => {
        const href = card.querySelector(".detail-btn")?.getAttribute("href") || "";
        let slug = "";
        try { slug = new URL(href, location.origin).searchParams.get("slug") || ""; } catch (_) {}
        const images = uniqueImages(galleries[slug]);
        const cover = card.querySelector(".facility-cover");
        if (!cover || !images.length) return;
        const name = card.querySelector(".facility-title h2")?.textContent.trim() || "Cơ sở y tế";
        const mainImage = cover.querySelector(":scope > img");
        const initialIndex = Math.max(0, images.findIndex(source => source === mainImage?.getAttribute("src")));
        cover.classList.add("has-toplist-gallery");
        cover.tabIndex = 0;
        cover.setAttribute("aria-label", `Xem thư viện ảnh của ${name}`);
        if (!cover.querySelector(".toplist-gallery-open-hint")) {
            const hint = document.createElement("span");
            hint.className = "toplist-gallery-open-hint";
            hint.innerHTML = `${svg.zoom}<span>Xem ảnh</span>`;
            cover.append(hint);
        }
        const openCover = event => {
            if (event.target.closest("button, a, .toplist-facility-actions")) return;
            openLightbox(images, initialIndex, name, cover);
        };
        cover.addEventListener("click", openCover);
        cover.addEventListener("keydown", event => {
            if (event.key === "Enter" || event.key === " ") { event.preventDefault(); openLightbox(images, initialIndex, name, cover); }
        });
        card.querySelectorAll(".toplist-gallery-strip").forEach(strip => strip.remove());
        if (images.length < 2) return;
        const strip = document.createElement("div");
        strip.className = "toplist-gallery-strip";
        images.slice(0, 6).forEach((source, index) => {
            const button = document.createElement("button");
            const image = document.createElement("img");
            button.type = "button";
            button.className = "toplist-gallery-thumb";
            button.setAttribute("aria-label", `Xem ảnh ${index + 1} của ${name}`);
            image.src = source;
            image.alt = `Ảnh ${index + 1} của ${name}`;
            image.loading = "lazy";
            button.append(image);
            button.addEventListener("click", () => openLightbox(images, index, name, button));
            strip.append(button);
        });
        cover.after(strip);
    });
});
</script>
HTML;
    $toplistReviewSources = [];
    foreach ($facilities as $facility) {
        $toplistReviewSources[] = array_map(static fn(array $review): string => trim((string) ($review['source'] ?? $review['source_text'] ?? '')), array_slice((array) ($facility['reviews_list'] ?? []), 0, 3));
    }
    $css .= '<style>.doctor-review-shell .review-service{display:none}.review-source{display:block!important;margin-top:7px;color:#64748b;font-size:11px;line-height:1.45}.review-source b{color:#475569}</style>';
    $script .= '<script>document.addEventListener("DOMContentLoaded",()=>{const sources='.json_encode($toplistReviewSources, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).';document.querySelectorAll(".facility").forEach((facility,facilityIndex)=>{facility.querySelectorAll(".review-row").forEach((row,reviewIndex)=>{row.querySelector(".review-service")?.remove();const source=sources[facilityIndex]?.[reviewIndex]||"";if(!source||row.querySelector(".review-source"))return;const target=row.querySelector(".review-content");if(!target)return;const line=document.createElement("div");line.className="review-source";line.innerHTML="<b>Nguồn:</b> ";line.append(document.createTextNode(source));target.append(line);});});});</script>';
    $script .= '<script>document.addEventListener("DOMContentLoaded",()=>{const icons={save:"<svg viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\"><path d=\"M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 1 0-7.8 7.8L12 21l8.9-8.6a5.5 5.5 0 0 0-.1-7.8Z\"/></svg>",share:"<svg viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\"><circle cx=\"18\" cy=\"5\" r=\"3\"/><circle cx=\"6\" cy=\"12\" r=\"3\"/><circle cx=\"18\" cy=\"19\" r=\"3\"/><path d=\"m8.7 10.5 6.6-4M8.7 13.5l6.6 4\"/></svg>",report:"<svg viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\"><path d=\"m10.3 3-8 14A2 2 0 0 0 4 20h16a2 2 0 0 0 1.7-3l-8-14a2 2 0 0 0-3.4 0Z\"/><path d=\"M12 9v4m0 4h.01\"/></svg>"};document.querySelectorAll(".facility-cover").forEach(cover=>{if(cover.querySelector(".toplist-facility-actions"))return;const actions=document.createElement("div");actions.className="toplist-facility-actions";actions.innerHTML=`<button type="button" aria-label="Lưu">${icons.save}<span>Lưu</span></button><button type="button" aria-label="Chia sẻ">${icons.share}<span>Chia sẻ</span></button><button type="button" aria-label="Báo cáo">${icons.report}<span>Báo cáo</span></button>`;cover.append(actions);});});</script>';
    $script .= '<script>document.addEventListener("DOMContentLoaded",()=>{document.querySelectorAll(".facility .facility-title").forEach(title=>{if(title.previousElementSibling?.classList.contains("facility-profile-eyebrow"))return;const badge=document.createElement("span");badge.className="facility-profile-eyebrow";badge.textContent="Hồ sơ cơ sở đã xác thực";title.before(badge);});});</script>';
    $quickComparisonRows = [];
    foreach ($facilities as $facility) {
        $summary = (array) ($facility['review_summary'] ?? []);
        $quickComparisonRows[] = [
            'rank' => (int) ($facility['rank'] ?? 0),
            'name' => (string) ($facility['name'] ?? ''),
            'services' => array_slice(toplist_services($facility), 0, 3),
            'price' => (string) ($facility['price'] ?? $facility['price_text'] ?? 'Liên hệ cơ sở'),
            'rating' => (string) ($summary['rating'] ?? $facility['rating'] ?? '0.0'),
            'reviews' => (string) ($summary['reviews'] ?? $facility['reviews'] ?? '0 đánh giá'),
        ];
    }
    $css .= '<style>.toplist-quick-compare{padding:22px;border:1px solid #dfe8f5;border-radius:20px;background:linear-gradient(135deg,#f8fbff,#fff);box-shadow:0 8px 24px rgba(15,23,42,.035)}.toplist-quick-compare-head{display:flex;justify-content:space-between;align-items:end;gap:12px;margin-bottom:16px}.toplist-quick-compare h2{margin:0;color:#153e78;font-size:20px}.toplist-quick-compare p{margin:5px 0 0;color:#64748b;font-size:12px}.toplist-quick-compare-scroll{overflow-x:auto}.toplist-quick-compare table{width:100%;min-width:760px;border-collapse:separate;border-spacing:0;font-size:12px}.toplist-quick-compare th{padding:11px 12px;border-top:1px solid #dfe8f5;border-bottom:1px solid #dfe8f5;background:#eff6ff;color:#475569;text-align:left;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.04em}.toplist-quick-compare th:first-child{border-radius:10px 0 0 10px}.toplist-quick-compare th:last-child{border-radius:0 10px 10px 0}.toplist-quick-compare td{padding:13px 12px;border-bottom:1px solid #edf2f7;color:#475569;vertical-align:middle}.toplist-quick-compare tr:last-child td{border-bottom:0}.quick-rank{display:inline-grid;place-items:center;width:24px;height:24px;margin-right:8px;border-radius:7px;background:#e8f0ff;color:#2563eb;font-size:10px;font-weight:800}.quick-name{color:#0f172a;font-weight:800}.quick-services{display:flex;flex-wrap:wrap;gap:5px}.quick-services span{padding:5px 7px;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-size:10px;font-weight:700}.quick-score{color:#b45309;font-weight:800;white-space:nowrap}.quick-score b{color:#f59e0b;font-size:14px}@media(max-width:700px){.toplist-quick-compare{padding:17px}.toplist-quick-compare h2{font-size:18px}}</style>';
    $comparisonHtml = '<section class="toplist-quick-compare"><div class="toplist-quick-compare-head"><div><h2>So sánh nhanh các cơ sở</h2><p>Đối chiếu dịch vụ, giá tham khảo và đánh giá trước khi xem hồ sơ chi tiết.</p></div></div><div class="toplist-quick-compare-scroll"><table><thead><tr><th>Cơ sở</th><th>Dịch vụ nổi bật</th><th>Giá tham khảo</th><th>Điểm · số lượng đánh giá</th></tr></thead><tbody>';
    foreach ($quickComparisonRows as $row) {
        $serviceTags = '';
        foreach ((array) $row['services'] as $service) $serviceTags .= '<span>' . htmlspecialchars((string) $service, ENT_QUOTES) . '</span>';
        if ($serviceTags === '') $serviceTags = '—';
        $comparisonHtml .= '<tr><td><span class="quick-rank">' . (int) $row['rank'] . '</span><span class="quick-name">' . htmlspecialchars((string) $row['name'], ENT_QUOTES) . '</span></td><td><div class="quick-services">' . $serviceTags . '</div></td><td>' . htmlspecialchars((string) ($row['price'] ?: 'Liên hệ cơ sở'), ENT_QUOTES) . '</td><td class="quick-score"><b>★ ' . htmlspecialchars((string) $row['rating'], ENT_QUOTES) . '</b>/5 · ' . htmlspecialchars((string) $row['reviews'], ENT_QUOTES) . '</td></tr>';
    }
    $comparisonHtml .= '</tbody></table></div></section>';
    $insertedComparison = 0;
    $html = preg_replace('~(<article class="intro">.*?</article>)~s', '$1' . $comparisonHtml, $html, 1, $insertedComparison) ?? $html;
    if ($insertedComparison === 0) $html = preg_replace('~(<div class="section-head">)~', $comparisonHtml . '$1', $html, 1) ?? $html;
    // Lớp thiết kế thống nhất cho toàn bộ trang chi tiết Toplist.
    $css .= '<style id="toplist-detail-unified-design">.page{padding:30px 0 80px;background:radial-gradient(circle at 16% 0,rgba(219,234,254,.55),transparent 31%),#f6f8fc}.breadcrumb{margin:0 0 14px}.hero{margin-top:0;padding:30px;gap:30px;border-color:#dbe7f5;border-radius:24px;background:linear-gradient(135deg,#fff 0%,#f8fbff 100%);box-shadow:0 18px 44px rgba(15,23,42,.06)}.hero h1{max-width:780px;font-size:clamp(30px,3.2vw,44px);color:#0f172a}.hero p{max-width:760px;color:#5f718d}.hero-meta{margin-top:22px}.hero-meta span{padding:7px 10px;border:1px solid #e4edf8;border-radius:999px;background:#fff}.hero-image{min-height:250px;border:1px solid #dbe7f5;border-radius:18px;box-shadow:0 12px 30px rgba(37,99,235,.11)}.layout{gap:24px;margin-top:24px}.main{gap:22px}.intro,.toplist-quick-compare,.facility,.side{border-color:#dfe8f5;box-shadow:0 10px 28px rgba(15,23,42,.045)}.intro{padding:28px;border-radius:20px}.intro h2,.intro h3{color:#123a72}.intro p{font-size:14px;line-height:1.85;color:#52637d}.section-head{padding:0 4px}.section-head h2{font-size:24px;letter-spacing:-.03em}.section-head span{padding:7px 10px;border-radius:999px;background:#eef5ff;color:#2563eb;font-weight:800}.toplist-quick-compare{padding:24px;border-radius:20px}.toplist-quick-compare-head{margin-bottom:18px}.toplist-quick-compare h2{font-size:21px;letter-spacing:-.025em}.toplist-quick-compare th{padding:12px 14px;background:#edf5ff}.toplist-quick-compare td{padding:14px}.facility{border-radius:22px;background:#fff}.facility-cover{height:300px;background:#eff6ff}.facility-cover:after{content:"";position:absolute;inset:0;background:linear-gradient(180deg,rgba(15,23,42,.04),transparent 38%);pointer-events:none}.facility-body{padding:28px}.facility-profile-eyebrow{margin-bottom:16px!important}.facility .facility-title .subtitle{max-width:900px}.tags{gap:8px;margin-top:18px}.tag{padding:7px 11px;border:1px solid #dbeafe;background:#f4f8ff}.facility-content{margin-top:22px;padding-top:22px;font-size:14px;line-height:1.9}.facts{gap:12px;margin-top:22px}.fact{min-height:82px;padding:15px;border-color:#e1eaf6;background:#fbfdff}.fact small{color:#7890b0}.fact strong,.fact a{font-size:12px}.price-box,.reviews{margin-top:24px;padding-top:24px}.price-box h3{display:flex;align-items:center;gap:8px;font-size:19px}.price-box h3:before{content:"₫";display:grid;place-items:center;width:25px;height:25px;border-radius:8px;background:#eff6ff;color:#2563eb}.price-box table{border-color:#dce7f5;box-shadow:0 4px 14px rgba(15,23,42,.025)}.price-box th{padding:13px 14px;background:#edf5ff;color:#174ea6}.price-box td{padding:13px 14px}.doctor-review-shell{gap:18px}.review-summary-card,.review-feed{border-color:#dce7f5;border-radius:18px}.review-summary-card{padding:22px 18px}.review-feed-toolbar{padding:15px 18px;background:#fbfdff}.review-row{padding:18px}.review-more{padding:18px}.facility-actions{margin-top:22px}.detail-btn{padding:12px 16px;border-radius:12px;box-shadow:0 8px 18px rgba(37,99,235,.18)}.sidebar{gap:16px}.side{padding:20px;border-radius:18px}.side h3{font-size:17px}.compare-item{padding:13px 0}.compare-rank{width:29px;height:29px}.toc a{padding:9px 10px;border-bottom:1px solid #eff4fa}.toc a:last-child{border-bottom:0}@media(max-width:1040px){.hero{grid-template-columns:1fr}.hero-image{height:280px}.sidebar{grid-template-columns:1fr 1fr}.facility-cover{height:270px}}@media(max-width:700px){.page{padding:18px 0 54px}.hero{padding:20px;border-radius:20px}.hero h1{font-size:30px}.hero-image{height:210px;min-height:0}.layout{margin-top:18px;gap:18px}.main{gap:18px}.intro,.toplist-quick-compare,.facility-body{padding:18px}.section-head{align-items:flex-start;flex-direction:column}.facility-cover{height:220px}.facts{grid-template-columns:1fr}.sidebar{grid-template-columns:1fr}.side{padding:18px}.doctor-review-shell{gap:14px}.review-summary-card{padding:18px}.toplist-quick-compare table{font-size:11px}}</style>';
    $css .= '<style>.doctor-review-shell .review-toolbar-group{gap:8px}.doctor-review-shell .review-toolbar-group select{height:34px!important;min-height:34px!important;padding:0 28px 0 10px!important;border-radius:9px!important;font-size:12px!important;font-weight:700!important;line-height:34px!important}.doctor-review-shell .review-feed-toolbar{padding:12px 14px!important}@media(max-width:700px){.doctor-review-shell .review-toolbar-group{width:100%}.doctor-review-shell .review-toolbar-group select{flex:1;min-width:0}}</style>';
    $css .= '<style>.toplist-facility-actions button{height:32px!important;padding:0 9px!important;border-radius:9px!important;font-size:11px!important}.toplist-facility-actions svg{width:14px!important;height:14px!important}.detail-btn,.review-write-btn,.review-more button,.review-more .review-write-inline{min-height:36px!important;padding:9px 13px!important;border-radius:10px!important;font-size:12px!important;line-height:1.2!important}.review-write-btn{margin-top:14px!important}.review-more{gap:8px!important;padding:14px!important}.facility-actions{margin-top:18px!important}.hero-meta,.hero-meta span{font-size:11px!important}.side h3,.toplist-quick-compare h2{font-size:17px!important}.tag{font-size:10px!important}.toplist-quick-compare th{font-size:9px!important}.toplist-quick-compare td{font-size:11px!important}@media(max-width:700px){.toplist-facility-actions button{height:30px!important;width:30px!important}.detail-btn,.review-write-btn,.review-more button,.review-more .review-write-inline{min-height:34px!important;font-size:11px!important}}</style>';
    $css .= '<style>.toplist-quick-compare tbody tr{cursor:pointer;transition:background .18s ease}.toplist-quick-compare tbody tr:hover{background:#f4f8ff}.toplist-quick-compare tbody tr:focus{outline:2px solid #93c5fd;outline-offset:-2px}</style>';
    $script .= '<script>document.addEventListener("DOMContentLoaded",()=>{document.querySelectorAll(".toplist-quick-compare tbody tr").forEach((row,index)=>{const rank='.json_encode(array_column($quickComparisonRows, 'rank')).'[index];if(!rank)return;row.tabIndex=0;row.setAttribute("role","link");row.setAttribute("aria-label","Xem cơ sở xếp hạng "+rank);const go=()=>{const target=document.getElementById("rank-"+rank);if(target){target.scrollIntoView({behavior:"smooth",block:"start"});history.replaceState(null,"","#rank-"+rank);}};row.addEventListener("click",go);row.addEventListener("keydown",event=>{if(event.key==="Enter"||event.key===" "){event.preventDefault();go();}});});});</script>';
    $css .= '<style id="toplist-mobile-final">@media(max-width:700px){.page .container{width:calc(100% - 24px)}.page{padding:14px 0 46px}.breadcrumb{overflow:hidden;white-space:nowrap;text-overflow:ellipsis;font-size:11px}.hero{padding:18px;gap:18px;border-radius:18px}.hero h1{margin:12px 0 8px;font-size:28px;line-height:1.18}.hero p{font-size:13px;line-height:1.65}.hero-meta{gap:7px;margin-top:16px}.hero-meta span{padding:6px 8px;font-size:10px!important}.hero-image{height:190px;border-radius:14px}.layout{margin-top:16px;gap:16px}.main{gap:16px}.intro{padding:17px;border-radius:17px}.intro p{font-size:13px;line-height:1.75}.section-head{padding:0;gap:7px}.section-head h2{font-size:20px}.section-head span{font-size:10px}.toplist-quick-compare{padding:16px;border-radius:17px}.toplist-quick-compare-head{margin-bottom:12px}.toplist-quick-compare h2{font-size:17px!important}.toplist-quick-compare p{font-size:11px;line-height:1.55}.toplist-quick-compare-scroll{margin:0 -2px}.toplist-quick-compare table{min-width:650px;font-size:10px}.toplist-quick-compare th{padding:9px 10px;font-size:8px!important}.toplist-quick-compare td{padding:10px;font-size:10px!important}.quick-rank{width:20px;height:20px;margin-right:5px;font-size:9px}.quick-services{gap:4px}.quick-services span{padding:4px 6px;font-size:9px}.facility{border-radius:18px}.facility-cover{height:205px}.facility-body{padding:18px}.toplist-gallery-strip{grid-template-columns:repeat(3,minmax(0,1fr));gap:6px;padding:8px}.toplist-gallery-thumb{height:52px;border-radius:8px}.rank{top:10px;left:10px;width:36px;height:36px;border-radius:10px;font-size:13px}.toplist-facility-actions{top:9px;right:9px;gap:5px}.facility-profile-eyebrow{margin-bottom:12px!important;font-size:10px}.facility .facility-title h2{font-size:25px!important;line-height:1.15!important}.facility .facility-title h2:after{width:23px;height:23px;margin-left:7px;font-size:12px}.facility .facility-title .subtitle{margin-top:10px!important;font-size:13px!important;line-height:1.6!important}.facility .facility-title .rating{margin-top:13px;padding:9px 11px!important;border-radius:11px!important;font-size:12px!important}.facility .facility-title .rating:before{font-size:16px;margin-right:7px}.tags{gap:6px;margin-top:14px}.tag{padding:6px 8px;font-size:9px!important}.facility-content{margin-top:17px;padding-top:17px;font-size:13px;line-height:1.75}.facts{margin-top:16px;gap:8px}.fact{min-height:0;padding:12px}.fact strong,.fact a{font-size:11px}.price-box,.reviews{margin-top:18px;padding-top:18px}.price-box{overflow:hidden}.price-box h3{font-size:16px}.price-box table{display:block;overflow-x:auto;white-space:nowrap;font-size:11px}.price-box th,.price-box td{padding:10px}.doctor-review-shell{grid-template-columns:1fr!important;gap:12px}.review-summary-card{padding:17px!important;border-radius:15px}.review-score{margin-top:12px}.review-score strong{font-size:44px}.review-summary-bars{gap:7px;margin-top:13px}.review-verified-card{margin-top:12px;padding:12px}.review-feed{border-radius:15px}.review-feed-toolbar{padding:11px 12px!important;gap:9px}.doctor-review-shell .review-toolbar-group{width:100%;display:grid;grid-template-columns:1fr 1fr}.doctor-review-shell .review-toolbar-group select{width:100%;height:33px!important;min-height:33px!important;font-size:11px!important}.review-switch{font-size:10px}.review-image-toggle{font-size:10px}.review-row{grid-template-columns:1fr!important;gap:10px;padding:14px!important}.review-actions{display:none!important}.review-author{gap:8px}.review-avatar{width:40px;height:40px}.review-content-top{align-items:flex-start}.review-content p{font-size:12px;line-height:1.65}.review-date{font-size:10px}.review-more{padding:12px!important}.detail-btn{width:100%;justify-content:center}.facility-actions{margin-top:16px!important}.sidebar{display:grid!important;grid-template-columns:1fr!important;gap:12px}.sidebar .side:first-child{display:none}.side{padding:16px;border-radius:16px}.toc{max-height:240px;overflow:auto}.toc a{font-size:11px;padding:8px}.toplist-facility-actions button{width:29px!important;height:29px!important}.toplist-facility-actions button span{display:none!important}}</style>';
    $css .= '<style id="toplist-responsive-layout-fix">html,body{width:100%;min-width:0}.facility-detail,.page{width:100%;min-width:0}.page .container{max-width:var(--max);min-width:0}@media(max-width:1180px){.hero{grid-template-columns:1fr}.hero-image{height:260px;min-height:0}.layout{grid-template-columns:minmax(0,1fr)!important}.sidebar{position:static!important;grid-template-columns:repeat(2,minmax(0,1fr))}.main{min-width:0}.facility,.intro,.toplist-quick-compare{min-width:0}}@media(max-width:700px){.page .container{width:calc(100% - 24px)!important;max-width:none}.sidebar{grid-template-columns:1fr}.hero-image{height:190px}}</style>';
    $css .= '<style id="toplist-hero-image-fallback">.hero-image{position:relative;isolation:isolate}.hero-image-fallback-label{display:none}.hero-image.is-fallback{display:grid;place-items:center;overflow:hidden;background:linear-gradient(135deg,#edf5ff 0%,#f8fbff 55%,#e7f0ff 100%)}.hero-image.is-fallback:before{position:absolute;z-index:-1;inset:16%;border:1px solid rgba(37,99,235,.14);border-radius:50%;background:radial-gradient(circle at 35% 30%,rgba(96,165,250,.25),transparent 45%);content:""}.hero-image.is-fallback .hero-image-fallback-label{display:inline-flex;align-items:center;gap:8px;max-width:calc(100% - 32px);padding:10px 13px;border:1px solid rgba(37,99,235,.16);border-radius:999px;background:rgba(255,255,255,.88);box-shadow:0 7px 20px rgba(37,99,235,.08);color:#31538d;font-size:12px;font-weight:800;line-height:1.35;text-align:center}.hero-image.is-fallback .hero-image-fallback-label:before{display:grid;place-items:center;width:21px;height:21px;border-radius:7px;background:#dbeafe;color:#2563eb;content:"✦";font-size:12px}@media(max-width:700px){.hero-image.is-fallback .hero-image-fallback-label{font-size:11px}}</style>';
    $css .= '<style>.hero-image.is-fallback>img{display:none!important}.hero-image.is-fallback:before{z-index:0}.hero-image.is-fallback .hero-image-fallback-label{position:relative;z-index:1}</style>';
    /* The detail link remains in markup for gallery/review AJAX to resolve the facility slug. */
    $css .= '<style id="toplist-hide-facility-profile-link">.facility-actions{display:none!important}</style>';
    $css .= '<link rel="stylesheet" href="/assets/css/pages/toplist-detail.css?v=' . filemtime(__DIR__ . '/assets/css/pages/toplist-detail.css') . '">';
    $script .= '<script>document.addEventListener("DOMContentLoaded",()=>{document.querySelectorAll(".hero-image img").forEach(image=>{const fallback=()=>{const container=image.closest(".hero-image");if(!container)return;container.classList.add("is-fallback");image.setAttribute("alt","");image.setAttribute("aria-hidden","true");if(!container.querySelector(".hero-image-fallback-label")){const label=document.createElement("span");label.className="hero-image-fallback-label";label.setAttribute("role","status");label.textContent="Ảnh bìa đang được cập nhật";container.append(label)}};image.addEventListener("error",fallback,{once:true});if(image.complete&&image.naturalWidth===0)fallback()})});</script>';
    $css .= <<<'HTML'
<style id="toplist-detail-motion-polish">
  /* Small, app-like feedback for this document only.  Gallery motion keeps its
     dedicated rules below and is deliberately not replaced here. */
  html{scroll-behavior:smooth}
  .page .hero,.page .intro,.page .toplist-quick-compare,.page .facility,.page .side{
    transition:border-color .22s ease,box-shadow .22s ease,background-color .22s ease;
  }
  .page :is(.detail-btn,.facility-profile-actions button,.toplist-facility-actions button,.compare-item,.toc a,.review-more a,.review-write-inline,.review-write,.load-more-review){
    -webkit-tap-highlight-color:transparent;
    touch-action:manipulation;
    transition:transform .2s cubic-bezier(.2,.78,.25,1),box-shadow .2s ease,background-color .2s ease,border-color .2s ease,color .2s ease;
  }
  .page :is(.facility,.fact,.tag,.review-row,.review,.toplist-quick-compare tbody tr){
    -webkit-tap-highlight-color:transparent;
    transition:transform .22s cubic-bezier(.2,.78,.25,1),box-shadow .22s ease,border-color .22s ease,background-color .22s ease;
  }
  .page .hero-image img,.page .facility-cover:not(.has-toplist-gallery) > img{
    transition:transform .46s cubic-bezier(.16,1,.3,1),filter .28s ease;
  }
  .page :is(a,button,select):focus-visible{
    outline:3px solid rgba(59,130,246,.58);
    outline-offset:3px;
  }
  .page .review-toolbar select:focus-visible{
    border-color:#93c5fd;
    box-shadow:0 0 0 3px rgba(59,130,246,.12);
  }
  .page .toplist-quick-compare tbody tr{transition:background-color .18s ease,box-shadow .18s ease}
  body.toplist-page-exiting .page{opacity:0;transition:opacity .16s ease}
  @media (hover:hover) and (pointer:fine){
    .page .hero:hover{border-color:#cfe0ff;box-shadow:0 18px 42px rgba(15,23,42,.075)}
    .page .intro:hover,.page .toplist-quick-compare:hover,.page .side:hover{border-color:#d3e2fb;box-shadow:0 12px 28px rgba(15,23,42,.055)}
    .page .facility:hover{border-color:#cfe0ff;box-shadow:0 16px 34px rgba(15,23,42,.075);transform:translateY(-2px)}
    .page .facility:hover .facility-cover:not(.has-toplist-gallery)>img,.page .hero-image:hover img{transform:scale(1.022);filter:saturate(1.04)}
    .page .fact:hover{border-color:#bfdbfe;background:#f8fbff;transform:translateY(-2px)}
    .page .review-row:hover,.page .review:hover{background:linear-gradient(90deg,rgba(248,251,255,.92),rgba(255,255,255,.98));box-shadow:inset 3px 0 0 rgba(96,165,250,.72)}
    .page .toplist-quick-compare tbody tr:hover{background:#f4f8ff;box-shadow:inset 3px 0 0 rgba(96,165,250,.7)}
    .page :is(.detail-btn,.facility-profile-actions button,.toplist-facility-actions button,.compare-item,.toc a,.review-more a,.review-write-inline,.review-write,.load-more-review):hover{transform:translateY(-1px)}
  }
  .page :is(.detail-btn,.facility-profile-actions button,.toplist-facility-actions button,.compare-item,.toc a,.review-more a,.review-write-inline,.review-write,.load-more-review,.facility,.fact):active{transform:scale(.976);transition-duration:.1s}
  @media (prefers-reduced-motion:no-preference){
    .page .breadcrumb{animation:toplistDetailFadeIn .26s ease both}
    .page .hero{animation:toplistDetailEnter .42s cubic-bezier(.16,1,.3,1) both}
    .page .motion-reveal{opacity:0;transform:translate3d(0,12px,0)}
    .page .motion-reveal.is-visible{animation:toplistDetailEnter .42s cubic-bezier(.16,1,.3,1) both}
    .page .motion-row-in{animation:toplistDetailRowIn .32s cubic-bezier(.16,1,.3,1) both}
    .page .motion-reveal.is-visible .review-summary-row b{transform-origin:left center;animation:toplistDetailBar .52s .12s cubic-bezier(.16,1,.3,1) both}
  }
  @keyframes toplistDetailFadeIn{from{opacity:0}to{opacity:1}}
  @keyframes toplistDetailEnter{from{opacity:0;transform:translate3d(0,12px,0)}to{opacity:1;transform:none}}
  @keyframes toplistDetailRowIn{from{opacity:0;transform:translate3d(0,8px,0)}to{opacity:1;transform:none}}
  @keyframes toplistDetailBar{from{transform:scaleX(0)}to{transform:scaleX(1)}}
  @media (prefers-reduced-motion:reduce){
    html{scroll-behavior:auto}
    .page *,body.toplist-page-exiting .page{animation-duration:.01ms!important;animation-iteration-count:1!important;transition-duration:.01ms!important;scroll-behavior:auto!important}
  }
</style>
HTML;
    $script .= <<<'HTML'
<script id="toplist-detail-motion-script">
  (() => {
    const init = () => {
      if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
      const page = document.querySelector('.page');
      if (!page) return;

      const targets = Array.from(page.querySelectorAll('.intro, .toplist-quick-compare, .section-head, .facility, .sidebar .side'));
      const reveal = (element) => element.classList.add('is-visible');
      const observer = 'IntersectionObserver' in window
        ? new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
              if (!entry.isIntersecting) return;
              reveal(entry.target);
              observer.unobserve(entry.target);
            });
          }, { rootMargin: '0px 0px -9% 0px', threshold: 0.06 })
        : null;
      targets.forEach((element) => {
        element.classList.add('motion-reveal');
        if (element.getBoundingClientRect().top < window.innerHeight * .88 || !observer) reveal(element);
        else observer.observe(element);
      });

      page.querySelectorAll('.review-list').forEach((list) => {
        if (!('MutationObserver' in window)) return;
        const rowObserver = new MutationObserver((mutations) => {
          mutations.forEach((mutation) => mutation.addedNodes.forEach((node) => {
            if (!(node instanceof Element) || !node.matches('.review-row, .review')) return;
            node.classList.add('motion-row-in');
            window.setTimeout(() => node.classList.remove('motion-row-in'), 420);
          }));
        });
        rowObserver.observe(list, { childList: true });
      });

      document.addEventListener('click', (event) => {
        const link = event.target.closest('.page a[href]');
        if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        if (link.target && link.target !== '_self' || link.hasAttribute('download') || link.dataset.noPageMotion !== undefined) return;
        let destination;
        try { destination = new URL(link.href, window.location.href); } catch (_) { return; }
        if (!/^https?:$/.test(destination.protocol) || destination.origin !== window.location.origin) return;
        if (destination.pathname === window.location.pathname && destination.search === window.location.search && destination.hash) return;
        event.preventDefault();
        document.body.classList.add('toplist-page-exiting');
        window.setTimeout(() => { window.location.href = destination.href; }, 155);
      });
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
    else init();
  })();
    </script>
HTML;
    // Keep the compact quick-comparison list in the right sidebar alongside
    // the full comparison table below the intro; the two serve different layouts.
    $css = str_replace('.sidebar .side:first-child{display:none}', '', $css);
    $html = str_replace('</head>', $css . '</head>', $html);
    return str_replace('</body>', $script . '</body>', $html);
});
?>
<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=htmlspecialchars($title,ENT_QUOTES)?> | MedReview</title><meta name="description" content="<?=htmlspecialchars($description,ENT_QUOTES)?>"><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"><link rel="stylesheet" href="/assets/css/core/shared-typography.css"><style>
:root{--brand:#2563eb;--brand-dark:#174ea6;--text:#0f172a;--muted:#64748b;--border:#dfe8f5;--soft:#f4f8ff;--max:1320px}*{box-sizing:border-box}body{margin:0;background:#f6f8fc;color:var(--text);font-family:"Plus Jakarta Sans",system-ui,sans-serif}a{text-decoration:none;color:inherit}.container{width:min(var(--max),calc(100% - 32px));margin:auto}.page{padding:24px 0 70px}.breadcrumb{display:flex;gap:8px;align-items:center;color:#94a3b8;font-size:12px;font-weight:700}.breadcrumb .active{color:var(--brand)}.hero{display:grid;grid-template-columns:minmax(0,1fr) 380px;gap:24px;margin-top:16px;padding:28px;border:1px solid var(--border);border-radius:22px;background:#fff;box-shadow:0 14px 38px rgba(15,23,42,.055)}.eyebrow{display:inline-flex;align-items:center;gap:7px;padding:7px 10px;border-radius:999px;background:#ecfdf5;color:#047857;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.04em}.hero h1{margin:15px 0 12px;font-size:clamp(28px,3vw,42px);line-height:1.16;letter-spacing:-.045em}.hero p{max-width:760px;margin:0;color:var(--muted);font-size:15px;line-height:1.75}.hero-meta{display:flex;gap:18px;flex-wrap:wrap;margin-top:20px;color:#64748b;font-size:12px;font-weight:700}.hero-meta span{display:flex;align-items:center;gap:6px}.hero-meta svg{width:16px}.hero-image{min-height:260px;border-radius:17px;overflow:hidden;background:#eaf2ff}.hero-image img{width:100%;height:100%;object-fit:cover}.layout{display:grid;grid-template-columns:minmax(0,1fr) 310px;gap:20px;margin-top:22px}.main{display:grid;gap:18px}.intro,.facility{border:1px solid var(--border);border-radius:20px;background:#fff;box-shadow:0 8px 24px rgba(15,23,42,.035)}.intro{padding:25px}.intro h2,.intro h3{color:#153e78}.intro p{color:#475569;line-height:1.85}.section-head{display:flex;align-items:end;justify-content:space-between;gap:12px}.section-head h2{margin:0;font-size:23px}.section-head span{color:#64748b;font-size:12px}.facility{overflow:hidden}.facility-cover{position:relative;height:270px;background:#eaf2ff}.facility-cover img{width:100%;height:100%;object-fit:cover}.rank{position:absolute;top:16px;left:16px;display:grid;place-items:center;width:48px;height:48px;border-radius:14px;background:#2563eb;color:#fff;font-size:17px;font-weight:800;box-shadow:0 10px 22px rgba(37,99,235,.28)}.facility-body{padding:24px}.facility-title{display:flex;justify-content:space-between;gap:18px;align-items:flex-start}.facility-title h2{margin:0;font-size:24px;letter-spacing:-.03em}.rating{flex:0 0 auto;padding:8px 11px;border-radius:11px;background:#fffbeb;color:#b45309;font-size:13px;font-weight:800}.subtitle{margin:9px 0 0;color:#64748b;line-height:1.7}.tags{display:flex;flex-wrap:wrap;gap:7px;margin-top:14px}.tag{padding:7px 10px;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-size:11px;font-weight:750}.facility-content{margin-top:20px;padding-top:20px;border-top:1px solid #edf2f7;color:#334155;font-size:14px;line-height:1.85}.facility-content h2,.facility-content h3{color:#153e78}.facts{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:18px}.fact{padding:13px;border:1px solid var(--border);border-radius:12px;background:#fbfdff}.fact small{display:block;color:#94a3b8;font-size:10px;font-weight:800;text-transform:uppercase}.fact strong,.fact a{display:block;margin-top:5px;color:#334155;font-size:12px;line-height:1.45;word-break:break-word}.price-box,.reviews{margin-top:20px;padding-top:20px;border-top:1px solid #edf2f7}.price-box h3,.reviews h3{margin:0 0 13px;font-size:17px}.price-box table{width:100%;border-collapse:separate;border-spacing:0;border:1px solid var(--border);border-radius:13px;overflow:hidden;font-size:13px}.price-box th{padding:11px 13px;text-align:left;background:#eff6ff;color:#174ea6}.price-box td{padding:11px 13px;border-top:1px solid #edf2f7}.price-box tr:nth-child(even) td{background:#fafcff}.review-list{display:grid;gap:10px}.review{padding:14px;border:1px solid var(--border);border-radius:13px;background:#fbfdff}.review-top{display:flex;justify-content:space-between;gap:10px}.review strong{font-size:13px}.stars{color:#f59e0b;letter-spacing:1px}.review p{margin:8px 0;color:#475569;font-size:13px;line-height:1.65}.review-meta{display:flex;gap:12px;color:#94a3b8;font-size:11px}.facility-actions{display:flex;justify-content:flex-end;margin-top:18px}.detail-btn{display:inline-flex;align-items:center;gap:7px;padding:11px 15px;border-radius:11px;background:#2563eb;color:#fff;font-size:12px;font-weight:800}.sidebar{align-self:start;position:sticky;top:92px;display:grid;gap:14px}.side{padding:18px;border:1px solid var(--border);border-radius:18px;background:#fff}.side h3{margin:0 0 13px;font-size:16px}.compare{display:grid}.compare-item{display:grid;grid-template-columns:29px minmax(0,1fr);gap:9px;padding:12px 0;border-top:1px solid #edf2f7}.compare-item:first-child{border-top:0}.compare-rank{display:grid;place-items:center;width:27px;height:27px;border-radius:8px;background:#eff6ff;color:#2563eb;font-size:11px;font-weight:800}.compare-item strong{display:block;font-size:12px;line-height:1.4}.compare-meta{display:flex;gap:8px;margin-top:5px;color:#64748b;font-size:10px}.toc{display:grid;gap:5px}.toc a{padding:8px 9px;border-radius:8px;color:#475569;font-size:12px}.toc a:hover{background:#eff6ff;color:#1d4ed8}@media(max-width:1040px){.hero{grid-template-columns:1fr}.hero-image{height:280px}.layout{grid-template-columns:1fr}.sidebar{position:static;grid-template-columns:1fr 1fr}}@media(max-width:700px){.container{width:calc(100% - 24px)}.page{padding-top:16px}.hero{padding:18px}.hero-image{height:210px;min-height:0}.facility-cover{height:220px}.facility-body{padding:17px}.facility-title{display:block}.rating{display:inline-block;margin-top:10px}.facts{grid-template-columns:1fr}.sidebar{grid-template-columns:1fr}.intro{padding:18px}}
</style></head><body><?php include __DIR__.'/Tem/header.php'; ?><main class="page site-typo"><div class="container"><nav class="breadcrumb"><a href="/">Trang chủ</a><span>›</span><a href="/toplist.php">Toplist</a><span>›</span><span class="active"><?=htmlspecialchars($title,ENT_QUOTES)?></span></nav><section class="hero"><div><span class="eyebrow"><i data-lucide="badge-check"></i>Bài viết Toplist</span><h1><?=htmlspecialchars($title,ENT_QUOTES)?></h1><p><?=htmlspecialchars($description,ENT_QUOTES)?></p><div class="hero-meta"><span><i data-lucide="calendar-days"></i>Cập nhật <?=htmlspecialchars(date('d/m/Y',strtotime((string)$toplist['updated_at'])),ENT_QUOTES)?></span><span><i data-lucide="list-ordered"></i><?=count($facilities)?> cơ sở</span></div></div><?php if($heroImage!==''): ?><div class="hero-image"><img src="<?=htmlspecialchars($heroImage,ENT_QUOTES)?>" alt="<?=htmlspecialchars($title,ENT_QUOTES)?>"></div><?php endif; ?></section><div class="layout"><div class="main"><?php if(trim((string)$toplist['content'])!==''): ?><article class="intro"><?=$toplist['content']?></article><?php endif; ?><div class="section-head"><h2>Danh sách cơ sở</h2><span><?=count($facilities)?> cơ sở được xếp hạng</span></div><?php foreach($facilities as $facility): $summary=(array)($facility['review_summary']??[]); $reviews=(array)($facility['reviews_list']??[]); ?><article class="facility" id="rank-<?= (int)$facility['rank'] ?>"><?php if(trim((string)($facility['image']??''))!==''): ?><div class="facility-cover"><span class="rank"><?=str_pad((string)$facility['rank'],2,'0',STR_PAD_LEFT)?></span><img src="<?=htmlspecialchars((string)$facility['image'],ENT_QUOTES)?>" alt="<?=htmlspecialchars((string)$facility['name'],ENT_QUOTES)?>"></div><?php endif; ?><div class="facility-body"><div class="facility-title"><div><h2><?=htmlspecialchars((string)$facility['name'],ENT_QUOTES)?></h2><p class="subtitle"><?=htmlspecialchars((string)($facility['subtitle']??''),ENT_QUOTES)?></p></div><span class="rating"><?=htmlspecialchars((string)($summary['rating']??$facility['rating']??'0.0'),ENT_QUOTES)?>/5 · <?=htmlspecialchars((string)($summary['reviews']??$facility['reviews']??'0 đánh giá'),ENT_QUOTES)?></span></div><?php $services=toplist_services($facility); if($services): ?><div class="tags"><?php foreach($services as $service): ?><span class="tag"><?=htmlspecialchars($service,ENT_QUOTES)?></span><?php endforeach; ?></div><?php endif; ?><?php if(trim((string)($facility['content']??''))!==''): ?><div class="facility-content"><?=$facility['content']?></div><?php endif; ?><div class="facts"><div class="fact"><small>Địa chỉ</small><strong><?=htmlspecialchars((string)($facility['address']??''),ENT_QUOTES)?></strong></div><div class="fact"><small>Điện thoại</small><strong><?=htmlspecialchars((string)($facility['phone']??''),ENT_QUOTES)?></strong></div><div class="fact"><small>Website</small><a href="<?=htmlspecialchars((string)($facility['website']??''),ENT_QUOTES)?>" target="_blank" rel="noopener"><?=htmlspecialchars((string)($facility['website']??''),ENT_QUOTES)?></a></div></div><?php if(trim((string)($facility['price_table_html']??''))!==''): ?><section class="price-box"><h3>Bảng giá dịch vụ</h3><?=$facility['price_table_html']?></section><?php endif; ?><?php if($reviews): ?><section class="reviews"><h3>Đánh giá khách hàng</h3><div class="review-list"><?php foreach(array_slice($reviews,0,3) as $review): ?><article class="review"><div class="review-top"><strong><?=htmlspecialchars((string)($review['author']??'Khách hàng'),ENT_QUOTES)?></strong><span class="stars">★★★★★ <?=htmlspecialchars((string)($review['rating']??''),ENT_QUOTES)?></span></div><p><?=htmlspecialchars((string)($review['excerpt']??$review['content']??''),ENT_QUOTES)?></p><div class="review-meta"><span><?=htmlspecialchars((string)($review['date']??''),ENT_QUOTES)?></span><span><?=htmlspecialchars((string)($review['service']??''),ENT_QUOTES)?></span><?php if(trim((string)($review['source']??''))!==''): ?><span>Nguồn: <?=htmlspecialchars((string)$review['source'],ENT_QUOTES)?></span><?php endif; ?></div></article><?php endforeach; ?></div></section><?php endif; ?><div class="facility-actions"><a class="detail-btn" href="/co-so-y-te-chi-tiet.php?slug=<?=rawurlencode((string)$facility['slug'])?>">Xem hồ sơ cơ sở <i data-lucide="arrow-right"></i></a></div></div></article><?php endforeach; ?></div><aside class="sidebar"><section class="side"><h3>So sánh nhanh</h3><div class="compare"><?php foreach($facilities as $facility): $summary=(array)($facility['review_summary']??[]); ?><a class="compare-item" href="#rank-<?=(int)$facility['rank']?>"><span class="compare-rank"><?=(int)$facility['rank']?></span><div><strong><?=htmlspecialchars((string)$facility['name'],ENT_QUOTES)?></strong><span class="compare-meta"><span><?=htmlspecialchars((string)($summary['rating']??$facility['rating']??'0.0'),ENT_QUOTES)?>/5</span><span><?=htmlspecialchars((string)($facility['price']??''),ENT_QUOTES)?></span></span></div></a><?php endforeach; ?></div></section><section class="side"><h3>Mục lục</h3><nav class="toc"><?php foreach($facilities as $facility): ?><a href="#rank-<?=(int)$facility['rank']?>">#<?=(int)$facility['rank']?> <?=htmlspecialchars((string)$facility['name'],ENT_QUOTES)?></a><?php endforeach; ?></nav></section></aside></div></div></main><?php include __DIR__.'/Tem/footer.php'; ?><script src="https://unpkg.com/lucide@latest"></script><script>window.lucide&&lucide.createIcons()</script></body></html>
